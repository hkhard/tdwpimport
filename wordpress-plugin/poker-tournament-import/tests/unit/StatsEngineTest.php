<?php
/**
 * Unit tests for Poker_Statistics_Engine.
 *
 * The engine is heavily $wpdb-backed. The no-database harness models the two
 * tables the engine reads/writes directly — poker_statistics (the data mart)
 * and poker_tournament_players (the participation read-model) — plus the
 * wp_cache_* object cache, so the data-mart round-trip, the single-table
 * aggregates, cache invalidation, and the recompute-on-empty path can all be
 * asserted offline.
 *
 * Queries that join wp_posts / wp_postmeta / poker_financial_summary degrade to
 * 0 here (those tables are intentionally not modelled), so derived metrics that
 * depend on them are asserted only for shape, not value.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class StatsEngineTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	private function engine(): Poker_Statistics_Engine {
		return Poker_Statistics_Engine::get_instance();
	}

	private function wpdb(): TDWP_Fake_WPDB {
		return $GLOBALS['wpdb'];
	}

	public function test_get_instance_returns_singleton(): void {
		$a = Poker_Statistics_Engine::get_instance();
		$b = Poker_Statistics_Engine::get_instance();
		$this->assertInstanceOf( Poker_Statistics_Engine::class, $a );
		$this->assertSame( $a, $b, 'Statistics engine must be a singleton.' );
	}

	public function test_get_last_updated_is_safe_when_never_run(): void {
		// Should not fatal; returns a falsy/empty value (or a timestamp string)
		// when statistics have never been computed.
		$last = $this->engine()->get_last_updated();
		$this->assertTrue( $last === false || $last === null || is_string( $last ) );
	}

	public function test_update_statistic_round_trips_through_data_mart(): void {
		$this->assertTrue( $this->engine()->update_statistic( 'total_tournaments', 7, 'count' ) );
		$this->assertSame( 7.0, $this->engine()->get_statistic( 'total_tournaments' ) );
	}

	public function test_get_statistic_defaults_to_zero_when_absent(): void {
		$this->assertEquals( 0, $this->engine()->get_statistic( 'never_set' ) );
	}

	public function test_get_statistic_is_served_from_object_cache(): void {
		$engine = $this->engine();
		$engine->update_statistic( 'total_players', 12, 'count' );

		// Prime the cache.
		$this->assertSame( 12.0, $engine->get_statistic( 'total_players' ) );

		// Mutate the underlying mart directly; the cached read must still win.
		$this->wpdb()->replace( 'wp_poker_statistics', array( 'stat_name' => 'total_players', 'stat_value' => 99 ) );
		$this->assertSame( 12.0, $engine->get_statistic( 'total_players' ), 'Cached value should be served until invalidated.' );
	}

	public function test_clear_all_statistics_invalidates_cache_and_table(): void {
		$engine = $this->engine();
		$engine->update_statistic( 'total_players', 12, 'count' );
		$engine->get_statistic( 'total_players' ); // prime cache

		$engine->clear_all_statistics();

		// Table truncated AND cache deleted => fresh read falls back to 0.
		$this->assertEquals( 0, $engine->get_statistic( 'total_players' ) );
		$this->assertSame( array(), $this->wpdb()->get_stats() );
	}

	public function test_get_total_players_count_aggregates_distinct_player_ids(): void {
		$this->wpdb()->set_player_rows( array(
			array( 'player_id' => 'a', 'buyins' => 1, 'winnings' => 100, 'finish_position' => 1 ),
			array( 'player_id' => 'a', 'buyins' => 1, 'winnings' => 0,   'finish_position' => 5 ),
			array( 'player_id' => 'b', 'buyins' => 1, 'winnings' => 50,  'finish_position' => 2 ),
		) );

		$this->assertSame( 2, $this->engine()->get_total_players_count() );
	}

	public function test_get_dashboard_statistics_returns_full_shape_from_seeded_mart(): void {
		$engine = $this->engine();
		// Pre-seed the mart so has_statistics() is true and no recompute runs.
		$engine->update_statistic( 'total_tournaments', 5, 'count' );
		$engine->update_statistic( 'total_players', 20, 'count' );
		$engine->update_statistic( 'total_prize_pool', 1000, 'sum' );

		$stats = $engine->get_dashboard_statistics();

		$this->assertIsArray( $stats );
		foreach ( array(
			'total_tournaments', 'total_players', 'total_prize_pool', 'avg_prize_pool',
			'total_entries', 'total_cashouts', 'total_payouts', 'total_unique_players',
			'total_revenue', 'total_profit', 'last_updated',
		) as $key ) {
			$this->assertArrayHasKey( $key, $stats, "Dashboard payload must include {$key}." );
		}
		$this->assertSame( 5.0, $stats['total_tournaments'] );
		$this->assertSame( 20.0, $stats['total_players'] );
		$this->assertSame( 1000.0, $stats['total_prize_pool'] );
	}

	public function test_get_dashboard_statistics_recomputes_when_mart_is_empty(): void {
		$engine = $this->engine();
		$this->wpdb()->set_player_rows( array(
			array( 'player_id' => 'a', 'buyins' => 2, 'winnings' => 300, 'finish_position' => 1 ),
			array( 'player_id' => 'b', 'buyins' => 1, 'winnings' => 0,   'finish_position' => 2 ),
			array( 'player_id' => 'c', 'buyins' => 1, 'winnings' => 75,  'finish_position' => 3 ),
		) );

		// Mart starts empty => get_dashboard_statistics() triggers calculate_all_statistics().
		$stats = $engine->get_dashboard_statistics();

		// The player-derived aggregates should reflect the seeded rows after recompute.
		$this->assertSame( 3.0, $stats['total_unique_players'] );
		$this->assertSame( 4.0, $stats['total_entries'], 'SUM(buyins) across seeded rows.' );
		$this->assertSame( 2.0, $stats['total_cashouts'], 'Rows with winnings > 0.' );
		$this->assertSame( 375.0, $stats['total_payouts'], 'SUM(winnings) across seeded rows.' );

		// Recompute populated the mart, so a timestamp is now reported.
		$this->assertIsString( $engine->get_last_updated() );
	}
}
