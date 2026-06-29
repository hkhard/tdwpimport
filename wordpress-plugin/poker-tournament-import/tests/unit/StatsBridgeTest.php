<?php
/**
 * Unit tests for TDWP_Stats_Bridge (Option A stats-mart bridge).
 *
 * Verifies the just-added functionality that projects finished LIVE tournaments
 * (tdwp_tournament_players, post-ID keyed) into the legacy stats data-mart
 * (poker_tournament_players, UUID keyed) without touching imported data.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class StatsBridgeTest extends TestCase {

	/** @var TDWP_Fake_WPDB */
	private $wpdb;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->wpdb = $GLOBALS['wpdb'];
	}

	/** Helper: build a live player row. */
	private function liveRow( int $player_id, $finish, float $prize, int $entry = 1 ): array {
		return array(
			'player_id'       => $player_id,
			'finish_position' => $finish,
			'prize_amount'    => $prize,
			'entry_number'    => $entry,
		);
	}

	public function test_init_registers_both_completion_hooks_and_refresh(): void {
		TDWP_Stats_Bridge::init();

		$this->assertArrayHasKey( 'tdwp_tournament_finished', $GLOBALS['tdwp_test_actions'] );
		$this->assertArrayHasKey( 'tdwp_tournament_completed', $GLOBALS['tdwp_test_actions'] );
		$this->assertArrayHasKey( 'tdwp_stats_bridge_refresh', $GLOBALS['tdwp_test_actions'] );
	}

	public function test_projects_one_legacy_row_per_player(): void {
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 1, 500.0 ),
			$this->liveRow( 102, 2, 300.0 ),
			$this->liveRow( 103, 3, 0.0 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9001 );

		$rows = $this->wpdb->get_legacy_rows();
		$this->assertCount( 3, $rows );

		// Winner row carries the right finish + winnings.
		$winner = $this->rowForFinish( $rows, 1 );
		$this->assertSame( 500.0, (float) $winner['winnings'] );
		$this->assertSame( 1, (int) $winner['buyins'] );
	}

	public function test_reentries_aggregate_into_single_row(): void {
		// Player 101 entered twice: busted entry (finish 8, no prize) + final run (finish 1, prize 500).
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 8, 0.0, 1 ),
			$this->liveRow( 101, 1, 500.0, 2 ),
			$this->liveRow( 102, 2, 300.0, 1 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9002 );

		$rows = $this->wpdb->get_legacy_rows();
		$this->assertCount( 2, $rows, 'Re-entries must collapse to one row per player' );

		// Find player 101's aggregated row by its (generated) player_uuid via meta.
		$p101 = $this->rowForFinish( $rows, 1 );
		$this->assertSame( 1, (int) $p101['finish_position'], 'Best (lowest) finish wins' );
		$this->assertSame( 500.0, (float) $p101['winnings'], 'Winnings summed across entries' );
		$this->assertSame( 2, (int) $p101['buyins'], 'Each entry counts as a buy-in' );
	}

	public function test_projection_is_idempotent(): void {
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 1, 500.0 ),
			$this->liveRow( 102, 2, 300.0 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9003 );
		TDWP_Stats_Bridge::project_to_stats_mart( 9003 ); // re-finish / both hooks fire

		$this->assertCount( 2, $this->wpdb->get_legacy_rows(), 'Re-projection must not duplicate rows' );
	}

	public function test_uses_uuid_join_keys_not_raw_post_ids(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9004 );

		$row = $this->wpdb->get_legacy_rows()[0];
		// tournament_id / player_id must be UUID strings, never the numeric post IDs.
		$this->assertNotSame( '9004', (string) $row['tournament_id'] );
		$this->assertNotSame( '101', (string) $row['player_id'] );
		$this->assertSame( get_post_meta( 9004, 'tournament_uuid', true ), $row['tournament_id'] );
		$this->assertSame( get_post_meta( 101, 'player_uuid', true ), $row['player_id'] );
	}

	public function test_reuses_existing_player_uuid_to_unify_stats(): void {
		// Player 101 was previously imported and already has a player_uuid.
		update_post_meta( 101, 'player_uuid', 'imported-uuid-xyz' );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9005 );

		$row = $this->wpdb->get_legacy_rows()[0];
		$this->assertSame( 'imported-uuid-xyz', $row['player_id'], 'Existing player_uuid must be reused' );
	}

	public function test_reuses_existing_tournament_uuid(): void {
		update_post_meta( 9006, 'tournament_uuid', 'existing-tourney-uuid' );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9006 );

		$row = $this->wpdb->get_legacy_rows()[0];
		$this->assertSame( 'existing-tourney-uuid', $row['tournament_id'] );
	}

	public function test_generated_uuid_is_prefixed_live(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9007 );

		$this->assertStringStartsWith( 'live-', get_post_meta( 9007, 'tournament_uuid', true ) );
		$this->assertStringStartsWith( 'live-', get_post_meta( 101, 'player_uuid', true ) );
	}

	public function test_points_default_to_zero_without_active_formula(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9008 );

		$this->assertSame( 0.0, (float) $this->wpdb->get_legacy_rows()[0]['points'] );
	}

	public function test_null_finish_position_maps_to_zero(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, null, 0.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9009 );

		$this->assertSame( 0, (int) $this->wpdb->get_legacy_rows()[0]['finish_position'] );
	}

	public function test_schedules_async_stats_refresh(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9010 );

		$this->assertNotFalse( wp_next_scheduled( 'tdwp_stats_bridge_refresh' ) );
	}

	public function test_noop_when_tables_missing(): void {
		$this->wpdb->tables_exist = false;
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9011 );

		$this->assertCount( 0, $this->wpdb->get_legacy_rows() );
	}

	public function test_noop_when_no_live_rows(): void {
		$this->wpdb->set_live_rows( array() );

		TDWP_Stats_Bridge::project_to_stats_mart( 9012 );

		$this->assertCount( 0, $this->wpdb->get_legacy_rows() );
		$this->assertFalse( wp_next_scheduled( 'tdwp_stats_bridge_refresh' ) );
	}

	public function test_noop_on_invalid_tournament_id(): void {
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 0 );

		$this->assertCount( 0, $this->wpdb->get_legacy_rows() );
	}

	/*
	 * ---------------------------------------------------------------------
	 * ROI mart projection (leaderboard / top-players read poker_player_roi).
	 * ------------------------------------------------------------------- */

	public function test_projects_roi_row_per_player_for_leaderboard(): void {
		$this->wpdb->set_buyin( 100.0 );
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 1, 500.0 ),
			$this->liveRow( 102, 2, 300.0 ),
			$this->liveRow( 103, 3, 0.0 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9101 );

		$roi = $this->wpdb->get_roi_rows();
		$this->assertCount( 3, $roi, 'One ROI row per player so live tournaments hit the leaderboard' );

		$winner = $this->rowForFinish( $roi, 1 );
		$this->assertSame( 100.0, (float) $winner['total_invested'], 'buy-in * 1 entry' );
		$this->assertSame( 500.0, (float) $winner['total_winnings'] );
		$this->assertSame( 400.0, (float) $winner['net_profit'], 'winnings - invested' );
		$this->assertSame( 400.0, (float) $winner['roi_percentage'], '400/100 * 100' );
	}

	public function test_roi_total_invested_counts_each_reentry(): void {
		$this->wpdb->set_buyin( 100.0 );
		// Player 101 entered twice (re-entry).
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 8, 0.0, 1 ),
			$this->liveRow( 101, 1, 500.0, 2 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9102 );

		$roi = $this->wpdb->get_roi_rows();
		$this->assertCount( 1, $roi );
		$this->assertSame( 200.0, (float) $roi[0]['total_invested'], 'buy-in * 2 entries' );
		$this->assertSame( 300.0, (float) $roi[0]['net_profit'], '500 - 200' );
	}

	public function test_roi_projection_is_idempotent(): void {
		$this->wpdb->set_buyin( 50.0 );
		$this->wpdb->set_live_rows( array(
			$this->liveRow( 101, 1, 500.0 ),
			$this->liveRow( 102, 2, 300.0 ),
		) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9103 );
		TDWP_Stats_Bridge::project_to_stats_mart( 9103 ); // both hooks / re-finish

		$this->assertCount( 2, $this->wpdb->get_roi_rows(), 'Re-projection must not duplicate ROI rows' );
	}

	public function test_roi_uses_uuid_join_keys(): void {
		$this->wpdb->set_buyin( 100.0 );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9104 );

		$row = $this->wpdb->get_roi_rows()[0];
		$this->assertSame( get_post_meta( 9104, 'tournament_uuid', true ), $row['tournament_id'] );
		$this->assertSame( get_post_meta( 101, 'player_uuid', true ), $row['player_id'] );
	}

	public function test_roi_skipped_when_only_roi_table_missing_but_legacy_recorded(): void {
		// Partial install: ROI table absent, legacy table present.
		$this->wpdb->missing_tables = array( 'wp_poker_player_roi' );
		$this->wpdb->set_buyin( 100.0 );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9105 );

		$this->assertCount( 1, $this->wpdb->get_legacy_rows(), 'Dashboard stats still recorded' );
		$this->assertCount( 0, $this->wpdb->get_roi_rows(), 'ROI guarded independently' );
	}

	public function test_zero_buyin_yields_zero_invested_and_roi(): void {
		$this->wpdb->set_buyin( 0.0 );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 500.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9106 );

		$row = $this->wpdb->get_roi_rows()[0];
		$this->assertSame( 0.0, (float) $row['total_invested'] );
		$this->assertSame( 0.0, (float) $row['roi_percentage'], 'No divide-by-zero; ROI is 0 for free entry' );
		$this->assertSame( 500.0, (float) $row['net_profit'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Live buy-in sourcing (template lookup, with legacy meta fallback).
	 * ------------------------------------------------------------------- */

	public function test_buyin_sourced_from_template_lookup(): void {
		// Live tournament: buy-in comes from the template, NOT '_buy_in' post meta.
		$this->wpdb->set_buyin( 250.0 );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 1000.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9107 );

		$this->assertSame( 250.0, (float) $this->wpdb->get_roi_rows()[0]['total_invested'] );
	}

	public function test_buyin_falls_back_to_legacy_meta_when_no_template(): void {
		// Template lookup returns null (e.g. imported tournament run with no template).
		$this->wpdb->set_buyin( null );
		update_post_meta( 9108, '_buy_in', 75.0 );
		$this->wpdb->set_live_rows( array( $this->liveRow( 101, 1, 1000.0 ) ) );

		TDWP_Stats_Bridge::project_to_stats_mart( 9108 );

		$this->assertSame( 75.0, (float) $this->wpdb->get_roi_rows()[0]['total_invested'], 'Falls back to _buy_in meta' );
	}

	/** Find a legacy row by finish_position. */
	private function rowForFinish( array $rows, int $finish ): array {
		foreach ( $rows as $row ) {
			if ( (int) $row['finish_position'] === $finish ) {
				return $row;
			}
		}
		$this->fail( "No legacy row with finish_position={$finish}" );
	}
}
