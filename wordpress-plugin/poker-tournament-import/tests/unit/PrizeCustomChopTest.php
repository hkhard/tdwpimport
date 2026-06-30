<?php
/**
 * Unit tests for tdwp-cma.21: custom chop type in
 * TDWP_Prize_Calculator::calculate_custom_chop().
 *
 * Covers:
 *  - Accepts amounts that exactly sum to the pool.
 *  - Accepts amounts within $0.02 tolerance (floating-point input).
 *  - Rejects amounts that differ from the pool by more than $0.02.
 *  - Rejects empty amounts array.
 *  - Rejects pool <= 0.
 *  - Returned amounts are rounded to 2 decimal places.
 *
 * Pure-logic — no DB access — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';
}

final class PrizeCustomChopTest extends TestCase {

	public function test_exact_sum_accepted(): void {
		$pool    = 1000.00;
		$amounts = array( 'Alice' => 600.00, 'Bob' => 400.00 );
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );

		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 600.00, $result['Alice'], 0.001 );
		$this->assertEqualsWithDelta( 400.00, $result['Bob'], 0.001 );
	}

	public function test_within_tolerance_accepted(): void {
		// Sum is 1000.01 — within $0.02 tolerance.
		$pool    = 1000.00;
		$amounts = array( 'Alice' => 600.01, 'Bob' => 400.00 );
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		$this->assertIsArray( $result );
		$this->assertFalse( is_wp_error( $result ) );
	}

	public function test_mismatch_returns_wp_error(): void {
		$pool    = 1000.00;
		$amounts = array( 'Alice' => 600.00, 'Bob' => 300.00 ); // total 900.
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'amount_mismatch', $result->get_error_code() );
	}

	public function test_error_message_contains_totals(): void {
		$pool    = 1000.00;
		$amounts = array( 'Alice' => 600.00, 'Bob' => 300.00 );
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		// number_format outputs '900.00' and '1,000.00' (with thousands separator).
		$this->assertStringContainsString( '900.00', $result->get_error_message() );
		$this->assertStringContainsString( '1,000.00', $result->get_error_message() );
	}

	public function test_empty_amounts_returns_wp_error(): void {
		$result = TDWP_Prize_Calculator::calculate_custom_chop( 1000.00, array() );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	public function test_zero_pool_returns_wp_error(): void {
		$result = TDWP_Prize_Calculator::calculate_custom_chop( 0, array( 'Alice' => 0 ) );
		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_amounts_rounded_to_cents(): void {
		$pool    = 100.00;
		$amounts = array( 'Alice' => 66.666, 'Bob' => 33.334 );
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		$this->assertIsArray( $result );
		// Values must be rounded to 2 dp.
		$this->assertEqualsWithDelta( 66.67, $result['Alice'], 0.001 );
		$this->assertEqualsWithDelta( 33.33, $result['Bob'], 0.001 );
	}

	public function test_three_player_custom_chop(): void {
		$pool    = 1500.00;
		$amounts = array( 'Alice' => 700.00, 'Bob' => 500.00, 'Carol' => 300.00 );
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertEqualsWithDelta( $pool, array_sum( $result ), 0.02 );
	}

	public function test_over_by_more_than_tolerance_is_rejected(): void {
		$pool    = 1000.00;
		$amounts = array( 'Alice' => 700.00, 'Bob' => 400.00 ); // total 1100, over by 100.
		$result  = TDWP_Prize_Calculator::calculate_custom_chop( $pool, $amounts );
		$this->assertTrue( is_wp_error( $result ) );
	}
}
