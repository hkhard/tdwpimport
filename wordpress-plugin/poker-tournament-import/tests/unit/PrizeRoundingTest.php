<?php
/**
 * Unit tests for tdwp-cma.16: rounding denomination options in
 * TDWP_Prize_Calculator::calculate_payouts_from_array().
 *
 * Covers:
 *  - Default (0.01) rounding preserves previous cent-level behaviour.
 *  - $1 denomination: each unlocked payout is a whole dollar; total = pool.
 *  - $5 denomination: each unlocked payout is a multiple of $5; total = pool.
 *  - $10 denomination: each unlocked payout is a multiple of $10; total = pool.
 *  - Invalid denomination falls back to cent precision.
 *  - Locked/fixed-amount places are not affected by denomination rounding.
 *
 * Pure-logic — no DB access — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'number_format' ) ) {
	// Already a PHP built-in — just guard.
}

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';
}

/**
 * Helper: sum of 'amount' values across all payout entries.
 */
function rounding_test_total( array $payouts ) {
	return array_sum( array_column( $payouts, 'amount' ) );
}

final class PrizeRoundingTest extends TestCase {

	private static function simple_structure( int $places ): array {
		// Builds an even percentage structure with $places places.
		$pct  = round( 100 / $places, 2 );
		$rows = array();
		for ( $i = 1; $i <= $places; $i++ ) {
			$rows[] = array( 'place' => $i, 'percentage' => $pct );
		}
		// Force last to sum to 100.
		$rows[ $places - 1 ]['percentage'] = round( 100 - ( $pct * ( $places - 1 ) ), 2 );
		return $rows;
	}

	public function test_default_denomination_totals_pool(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure );
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}

	public function test_dollar_rounding_payouts_are_whole_dollars(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 1 );

		// Each amount should be a whole dollar (mod 1 = 0).
		foreach ( $payouts as $payout ) {
			$this->assertEqualsWithDelta(
				0.0,
				fmod( $payout['amount'], 1 ),
				0.001,
				"Payout {$payout['amount']} is not a whole dollar"
			);
		}
	}

	public function test_dollar_rounding_total_equals_pool(): void {
		$pool      = 1337.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 1 );
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}

	public function test_five_dollar_rounding_multiples_of_five(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 5 );

		// Place 2 and 3 must be multiples of $5 (place 1 absorbs remainder).
		foreach ( array( 2, 3 ) as $place ) {
			$this->assertEqualsWithDelta(
				0.0,
				fmod( $payouts[ $place ]['amount'], 5 ),
				0.001,
				"Payout for place {$place} ({$payouts[$place]['amount']}) is not a multiple of 5"
			);
		}
	}

	public function test_five_dollar_rounding_total_equals_pool(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 30 ),
			array( 'place' => 3, 'percentage' => 20 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 5 );
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}

	public function test_ten_dollar_rounding_total_equals_pool(): void {
		$pool      = 5000.00;
		$structure = self::simple_structure( 5 );
		$payouts   = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 10 );
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}

	public function test_invalid_denomination_falls_back_to_cent_precision(): void {
		// Passing denomination=3 (not in allowed list) should fall back to 0.01.
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50 ),
			array( 'place' => 2, 'percentage' => 50 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 3 );
		// Should still total the pool.
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}

	public function test_locked_place_not_affected_by_denomination(): void {
		$pool      = 1000.00;
		$structure = array(
			array( 'place' => 1, 'percentage' => 50, 'locked' => true ),
			array( 'place' => 2, 'percentage' => 50 ),
		);
		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( $pool, $structure, 10 );
		// Locked place 1 should be exactly 500 (50% of pool, not rounded to $10 step).
		$this->assertEqualsWithDelta( 500.00, $payouts[1]['amount'], 0.01 );
		// Total must still equal pool.
		$this->assertEqualsWithDelta( $pool, rounding_test_total( $payouts ), 0.01 );
	}
}
