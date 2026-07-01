<?php
/**
 * Unit tests for TDWP_Prize_Calculator ICM (Malmuth-Harville) logic.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';

/**
 * @covers TDWP_Prize_Calculator::calculate_icm
 * @covers TDWP_Prize_Calculator::calculate_icm_chop
 */
class IcmCalculatorTest extends TestCase {

	public function test_two_player_exact_icm() {
		// Classic textbook case: stacks 6000/4000, prizes 50/30.
		// A: 0.6*50 + 0.4*30 = 42 ; B: 0.4*50 + 0.6*30 = 38.
		$icm = TDWP_Prize_Calculator::calculate_icm( array( 50, 30 ), array( 'A' => 6000, 'B' => 4000 ) );
		$this->assertEqualsWithDelta( 42.0, $icm['A'], 0.0001 );
		$this->assertEqualsWithDelta( 38.0, $icm['B'], 0.0001 );
	}

	public function test_single_prize_is_proportional_to_chips() {
		$icm = TDWP_Prize_Calculator::calculate_icm( array( 100 ), array( 'A' => 60, 'B' => 40 ) );
		$this->assertEqualsWithDelta( 60.0, $icm['A'], 0.0001 );
		$this->assertEqualsWithDelta( 40.0, $icm['B'], 0.0001 );
	}

	public function test_equal_stacks_split_evenly() {
		$icm = TDWP_Prize_Calculator::calculate_icm(
			array( 100, 60, 20 ),
			array(
				'A' => 1000,
				'B' => 1000,
				'C' => 1000,
			)
		);
		foreach ( array( 'A', 'B', 'C' ) as $p ) {
			$this->assertEqualsWithDelta( 60.0, $icm[ $p ], 0.0001 );
		}
	}

	public function test_equity_sums_to_prize_pool() {
		$prizes = array( 500, 300, 150, 50 );
		$icm    = TDWP_Prize_Calculator::calculate_icm(
			$prizes,
			array(
				'A' => 5200,
				'B' => 2500,
				'C' => 1400,
				'D' => 900,
			)
		);
		$this->assertEqualsWithDelta( array_sum( $prizes ), array_sum( $icm ), 0.0001 );
	}

	public function test_bigger_stack_earns_more_but_less_than_chip_share() {
		// ICM compresses equity: the chip leader's payout share is below their
		// chip share (the essence of ICM vs a chip-chop).
		$prizes    = array( 100, 60, 40 );
		$icm       = TDWP_Prize_Calculator::calculate_icm(
			$prizes,
			array(
				'big'   => 8000,
				'mid'   => 1500,
				'small' => 500,
			)
		);
		$pool       = array_sum( $prizes );
		$chip_share = 8000 / 10000; // 0.8
		$icm_share  = $icm['big'] / $pool;
		$this->assertGreaterThan( $icm['mid'], $icm['big'] );
		$this->assertLessThan( $chip_share, $icm_share );
	}

	public function test_zero_stacks_are_dropped() {
		// B has no chips -> not a live player; the single prize goes to A.
		$icm = TDWP_Prize_Calculator::calculate_icm( array( 100 ), array( 'A' => 1000, 'B' => 0 ) );
		$this->assertArrayNotHasKey( 'B', $icm );
		$this->assertEqualsWithDelta( 100.0, $icm['A'], 0.0001 );
	}

	public function test_more_prizes_than_players_conserves_pool() {
		// Degenerate case: 4 prizes but only 2 players. All money must still be
		// distributed (the survivors collect the unpayable tail).
		$prizes = array( 100, 60, 30, 10 );
		$icm    = TDWP_Prize_Calculator::calculate_icm( $prizes, array( 'A' => 7000, 'B' => 3000 ) );
		$this->assertEqualsWithDelta( array_sum( $prizes ), array_sum( $icm ), 0.0001 );
	}

	public function test_chop_helper_rounds_and_matches_pool() {
		$prizes = array( 55, 33, 12 );
		$out    = TDWP_Prize_Calculator::calculate_icm_chop(
			$prizes,
			array(
				'A' => 3333,
				'B' => 3333,
				'C' => 3334,
			)
		);
		$this->assertEqualsWithDelta( array_sum( $prizes ), array_sum( $out ), 0.01 );
	}
}
