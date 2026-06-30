<?php
/**
 * Unit tests for tdwp-cma.27: admin waitlist promotion.
 *
 * Covers TDWP_Tournament_Player_Manager::get_waitlist() and
 * promote_waitlisted_player(): promotion flips status to 'registered', clears the
 * promoted row's waitlist position, and repacks the remaining queue sequentially.
 *
 * Offline — uses the seedable tdwp_tournament_players store in the fake $wpdb.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerWaitlistPromoteTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	/** Look up a seeded row by player_id from the fake store. */
	private function row( int $player_id ): ?array {
		foreach ( $GLOBALS['wpdb']->get_tdwp_players() as $row ) {
			if ( (int) $row['player_id'] === $player_id ) {
				return $row;
			}
		}
		return null;
	}

	public function test_get_waitlist_returns_rows_ordered_by_position(): void {
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 30, 'status' => 'waitlisted', 'waitlist_position' => 2 ) );
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 10, 'status' => 'waitlisted', 'waitlist_position' => 1 ) );
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 2, 'player_id' => 99, 'status' => 'waitlisted', 'waitlist_position' => 1 ) );

		$waitlist = TDWP_Tournament_Player_Manager::get_waitlist( 1 );

		$this->assertCount( 2, $waitlist );
		$this->assertSame( 10, (int) $waitlist[0]->player_id );
		$this->assertSame( 30, (int) $waitlist[1]->player_id );
	}

	public function test_promote_flips_status_and_clears_position(): void {
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 10, 'status' => 'waitlisted', 'waitlist_position' => 1 ) );

		$result = TDWP_Tournament_Player_Manager::promote_waitlisted_player( 1, 10 );

		$this->assertTrue( $result );
		$row = $this->row( 10 );
		$this->assertSame( 'registered', $row['status'] );
		$this->assertSame( 0, (int) $row['waitlist_position'] );
	}

	public function test_promote_repacks_remaining_waitlist(): void {
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 10, 'status' => 'waitlisted', 'waitlist_position' => 1 ) );
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 20, 'status' => 'waitlisted', 'waitlist_position' => 2 ) );
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 30, 'status' => 'waitlisted', 'waitlist_position' => 3 ) );

		TDWP_Tournament_Player_Manager::promote_waitlisted_player( 1, 10 );

		// Remaining two should be repacked to positions 1 and 2.
		$this->assertSame( 1, (int) $this->row( 20 )['waitlist_position'] );
		$this->assertSame( 2, (int) $this->row( 30 )['waitlist_position'] );

		$waitlist = TDWP_Tournament_Player_Manager::get_waitlist( 1 );
		$this->assertCount( 2, $waitlist );
	}

	public function test_promote_errors_when_player_not_registered(): void {
		$result = TDWP_Tournament_Player_Manager::promote_waitlisted_player( 1, 999 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'not_registered', $result->get_error_code() );
	}

	public function test_promote_errors_when_player_not_waitlisted(): void {
		$GLOBALS['wpdb']->seed_tdwp_player_row( array( 'tournament_id' => 1, 'player_id' => 10, 'status' => 'registered', 'waitlist_position' => 0 ) );

		$result = TDWP_Tournament_Player_Manager::promote_waitlisted_player( 1, 10 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'not_waitlisted', $result->get_error_code() );
	}
}
