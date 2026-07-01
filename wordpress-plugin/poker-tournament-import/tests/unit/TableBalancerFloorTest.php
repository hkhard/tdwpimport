<?php
/**
 * Unit tests for TDWP_Table_Balancer::can_move_out() (min-per-table floor, tdwp-3lg.7).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-table-balancer.php';

/**
 * @covers TDWP_Table_Balancer::can_move_out
 */
class TableBalancerFloorTest extends TestCase {

	public function test_min_constant_is_four() {
		$this->assertSame( 4, TDWP_Table_Balancer::MIN_PLAYERS_PER_TABLE );
	}

	public function test_allows_move_when_above_floor() {
		// 6 players, floor 4: removing one leaves 5 -> allowed.
		$this->assertTrue( TDWP_Table_Balancer::can_move_out( 6, 4 ) );
	}

	public function test_allows_move_that_lands_exactly_on_floor() {
		// 5 players, floor 4: removing one leaves 4 -> allowed.
		$this->assertTrue( TDWP_Table_Balancer::can_move_out( 5, 4 ) );
	}

	public function test_blocks_move_that_would_break_floor() {
		// 4 players, floor 4: removing one leaves 3 -> blocked.
		$this->assertFalse( TDWP_Table_Balancer::can_move_out( 4, 4 ) );
	}

	public function test_blocks_move_below_floor() {
		$this->assertFalse( TDWP_Table_Balancer::can_move_out( 3, 4 ) );
		$this->assertFalse( TDWP_Table_Balancer::can_move_out( 0, 4 ) );
	}

	public function test_respects_custom_floor() {
		$this->assertTrue( TDWP_Table_Balancer::can_move_out( 3, 2 ) );
		$this->assertFalse( TDWP_Table_Balancer::can_move_out( 2, 2 ) );
	}
}
