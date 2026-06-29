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
