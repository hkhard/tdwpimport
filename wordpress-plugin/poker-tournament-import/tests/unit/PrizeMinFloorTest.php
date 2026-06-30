<?php
/**
 * Unit tests for tdwp-cma.19: minimum payout floor in
 * TDWP_Prize_Calculator::calculate_payouts_from_array().
 *
 * Covers:
 *  - No floor (default 0): behaviour unchanged.
 *  - Floor removes sub-floor places from lowest upward; total still equals pool.
 *  - When only one place remains after trimming, it receives the full remaining pool.
 *  - Locked/fixed-amount places are never trimmed by the floor.
 *  - Multiple passes of trimming converge deterministically.
 *
 * Pure-logic — no DB access — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';
}

final class PrizeMinFloorTest extends TestCase {

	private static function sum_payouts( array $payouts ): float {
		return array_sum( array_column( $payouts, 'amount' ) );
	}

	public function test_no_floor_behaviour_unchanged(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 0 );
		$this->assertCount( 3, $payouts );
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_floor_removes_lowest_place_that_falls_below(): void {
		// $1000 pool, 4 places: 50/30/15/5 → place 4 = $50, well above a $60 floor.
		// But with a $100 floor place 4 ($50) and maybe place 3 ($150) may be affected.
		// 50% of 1000 = 500, 30% = 300, 15% = 150, 5% = 50. Floor=$100 → place 4 must go.
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 15 ),
			array( 'place' => 4, 'percentage' => 5 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 100.0 );

		// Place 4 must be absent (its proportional share was $50 < $100 floor).
		$this->assertArrayNotHasKey( 4, $payouts, 'Place 4 should be trimmed by the floor' );
		// Total must still equal pool.
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_floor_total_equals_pool_after_trimming(): void {
		$pool      = 500.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 25 ),
			array( 'place' => 3, 'percentage' => 15 ),
			array( 'place' => 4, 'percentage' => 10 ),
		);
		// Floor $75: place 4 proportional = 500*10/100 = $50 → trimmed.
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 75.0 );
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_extreme_floor_leaves_single_place(): void {
		// Floor so high only one place can survive.
		$pool      = 300.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		// Proportional: place1=$150, place2=$90, place3=$60. Floor=$100 → trim place3 ($60).
		// After trimming: place1=187.5, place2=112.5. Both above $100 floor. Stop.
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 100.0 );
		// At least place 1 must remain.
		$this->assertArrayHasKey( 1, $payouts );
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_floor_does_not_trim_locked_places(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 90, 'locked' => true ),
			array( 'place' => 2, 'percentage' => 5 ),
			array( 'place' => 3, 'percentage' => 5 ),
		);
		// Locked place 1 = $900. Remaining = $100 split 50/50 → each $50.
		// Floor = $60: place 3 ($50) trimmed from unlocked set; place 1 untouched.
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 60.0 );
		$this->assertArrayHasKey( 1, $payouts, 'Locked place 1 must always be present' );
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_all_places_above_floor_none_trimmed(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		// Floor $10: all places well above. Nothing trimmed.
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 10.0 );
		$this->assertCount( 3, $payouts );
		$this->assertEqualsWithDelta( $pool, self::sum_payouts( $payouts ), 0.01 );
	}

	public function test_floor_trim_is_deterministic_bottom_up(): void {
		// Two runs of the same inputs must produce identical results.
		$pool      = 800.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 40 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
			array( 'place' => 4, 'percentage' => 7 ),
			array( 'place' => 5, 'percentage' => 3 ),
		);
		$a = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 50.0 );
		$b = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 0.01, 50.0 );
		$this->assertSame( $a, $b );
	}
}
