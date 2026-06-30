<?php
/**
 * Unit tests for TDWP_Player_Operations::shift_positions_below().
 *
 * Covers the ranking-renumber rule applied when a bust-out is undone
 * (pure logic; no DB).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-operations.php';

/**
 * @covers TDWP_Player_Operations::shift_positions_below
 */
class UndoBustoutTest extends TestCase {

	public function test_players_below_shift_up_by_one() {
		// Eliminated at 10,9,8,7; undo the player who finished 8th.
		// Better finishers (7) shift to 8; worse finishers (9,10) unchanged.
		$shifts = TDWP_Player_Operations::shift_positions_below( array( 10, 9, 7 ), 8 );
		$this->assertSame( array( 7 => 8 ), $shifts );
	}

	public function test_restoring_worst_position_shifts_everyone_below() {
		// Undo the first player out (position 10): everyone else moves one worse.
		$shifts = TDWP_Player_Operations::shift_positions_below( array( 9, 8, 7 ), 10 );
		$this->assertSame(
			array(
				9 => 10,
				8 => 9,
				7 => 8,
			),
			$shifts
		);
	}

	public function test_restoring_best_eliminated_position_shifts_nobody() {
		// Position 7 is the best among eliminated; nobody finished better.
		$shifts = TDWP_Player_Operations::shift_positions_below( array( 10, 9, 8 ), 7 );
		$this->assertSame( array(), $shifts );
	}

	public function test_zero_and_null_positions_are_ignored() {
		$shifts = TDWP_Player_Operations::shift_positions_below( array( 0, 5, 3 ), 6 );
		$this->assertSame(
			array(
				5 => 6,
				3 => 4,
			),
			$shifts
		);
	}

	public function test_restored_position_zero_shifts_nobody() {
		// Re-entry-eligible bust-out had no finish position.
		$shifts = TDWP_Player_Operations::shift_positions_below( array( 10, 9 ), 0 );
		$this->assertSame( array(), $shifts );
	}
}
