<?php
/**
 * Unit tests for tdwp-cma.8: copy player roster from a previous tournament.
 *
 * Covers TDWP_Tournament_Player_Manager::copy_roster(): every source player is
 * re-registered into the target, players already registered are skipped, and the
 * copied/skipped counts are reported. Bad arguments return a WP_Error.
 *
 * Offline — source roster comes from the fake live-rows; target registrations and
 * post-type validation come from the seedable stores / post stubs.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerCopyRosterTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	/** Register the target tournament and the given player IDs as posts. */
	private function registerPosts( int $target_id, array $player_ids ): void {
		tdwp_test_register_player( $target_id, '', 'Target Tournament', 'live_tournament' );
		foreach ( $player_ids as $pid ) {
			tdwp_test_register_player( $pid, '', 'Player ' . $pid, 'player' );
		}
	}

	/** Seed the source roster (read by get_tournament_players via live-rows). */
	private function seedSourceRoster( array $player_ids ): void {
		$rows = array();
		foreach ( $player_ids as $pid ) {
			$rows[] = array( 'player_id' => $pid, 'status' => 'registered' );
		}
		$GLOBALS['wpdb']->set_live_rows( $rows );
	}

	public function test_copy_roster_registers_all_players(): void {
		$this->registerPosts( 200, array( 1, 2, 3 ) );
		$this->seedSourceRoster( array( 1, 2, 3 ) );

		$result = TDWP_Tournament_Player_Manager::copy_roster( 100, 200 );

		$this->assertIsArray( $result );
		$this->assertSame( 3, $result['copied'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertCount( 3, $GLOBALS['wpdb']->get_tdwp_player_inserts() );
	}

	public function test_copy_roster_skips_already_registered_players(): void {
		$this->registerPosts( 200, array( 1, 2, 3 ) );
		$this->seedSourceRoster( array( 1, 2, 3 ) );
		// Player 2 is already registered for the target.
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 200, 'player_id' => 2, 'status' => 'registered' ) );

		$result = TDWP_Tournament_Player_Manager::copy_roster( 100, 200 );

		$this->assertSame( 2, $result['copied'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	public function test_copy_roster_errors_on_missing_argument(): void {
		$result = TDWP_Tournament_Player_Manager::copy_roster( 0, 200 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_args', $result->get_error_code() );
	}

	public function test_copy_roster_errors_when_source_equals_target(): void {
		$result = TDWP_Tournament_Player_Manager::copy_roster( 200, 200 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'same_tournament', $result->get_error_code() );
	}
}
