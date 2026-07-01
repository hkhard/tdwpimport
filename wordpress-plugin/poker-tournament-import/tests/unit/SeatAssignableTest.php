<?php
/**
 * Unit tests for TDWP_Seat_Manager::is_seat_assignable() (seat unavailability, tdwp-3lg.8).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-seat-manager.php';

/**
 * @covers TDWP_Seat_Manager::is_seat_assignable
 */
class SeatAssignableTest extends TestCase {

	private function seat( $player_id, $status ) {
		return (object) array( 'player_id' => $player_id, 'status' => $status );
	}

	public function test_empty_seat_is_assignable() {
		$this->assertTrue( TDWP_Seat_Manager::is_seat_assignable( $this->seat( null, 'empty' ) ) );
	}

	public function test_occupied_seat_is_not_assignable() {
		$this->assertFalse( TDWP_Seat_Manager::is_seat_assignable( $this->seat( 42, 'occupied' ) ) );
	}

	public function test_unavailable_empty_seat_is_not_assignable() {
		// The core bug: an empty but unavailable seat must NOT be assigned.
		$this->assertFalse( TDWP_Seat_Manager::is_seat_assignable( $this->seat( null, 'unavailable' ) ) );
		$this->assertSame( 'unavailable', TDWP_Seat_Manager::STATUS_UNAVAILABLE );
	}

	public function test_seat_without_status_defaults_to_assignable_when_empty() {
		$this->assertTrue( TDWP_Seat_Manager::is_seat_assignable( (object) array( 'player_id' => null ) ) );
	}

	public function test_array_seat_is_accepted() {
		$this->assertTrue( TDWP_Seat_Manager::is_seat_assignable( array( 'player_id' => null, 'status' => 'empty' ) ) );
		$this->assertFalse( TDWP_Seat_Manager::is_seat_assignable( array( 'player_id' => null, 'status' => 'unavailable' ) ) );
	}

	public function test_non_seat_input_is_not_assignable() {
		$this->assertFalse( TDWP_Seat_Manager::is_seat_assignable( null ) );
		$this->assertFalse( TDWP_Seat_Manager::is_seat_assignable( 'nope' ) );
	}
}
