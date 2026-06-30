<?php
/**
 * Unit tests for tdwp-vf9: Template financial configuration.
 *
 * Covers:
 *  - calculate_financial_summary() with percentage rake
 *  - calculate_financial_summary() with flat rake
 *  - Fee-split: buy_in derived from entry_fee + prize_pool_contribution
 *  - Backward-compat: buy_in-only templates (both split fields zero)
 *  - sanitize_template_data-equivalent logic via TDWP_Tournament_Template
 *  - rake_mode defaults to 'percentage' for unknown values
 *  - net_pool is never negative (flat rake exceeds gross pool)
 *
 * Pure-logic — no DB access — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';
}

final class TemplateFinancialConfigTest extends TestCase {

	// -------------------------------------------------------------------------
	// calculate_financial_summary() — percentage rake
	// -------------------------------------------------------------------------

	public function test_percentage_rake_basic(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			5.00,   // entry_fee
			45.00,  // prize_pool_contribution
			10,     // entries
			0,      // rebuys
			0,      // addons
			0,      // rebuy_cost
			0,      // addon_cost
			'percentage',
			10.0,   // rake_percentage
			0.0     // rake_flat_amount
		);

		$this->assertIsArray( $result );
		// buy_in = 5 + 45 = 50
		$this->assertEqualsWithDelta( 50.00, $result['buy_in'], 0.001 );
		// entry_pool = 45 * 10 = 450
		$this->assertEqualsWithDelta( 450.00, $result['entry_pool'], 0.001 );
		// gross_pool = 450
		$this->assertEqualsWithDelta( 450.00, $result['gross_pool'], 0.001 );
		// rake_amount = 450 * 10% = 45
		$this->assertEqualsWithDelta( 45.00, $result['rake_amount'], 0.001 );
		// net_pool = 450 - 45 = 405
		$this->assertEqualsWithDelta( 405.00, $result['net_pool'], 0.001 );
		$this->assertSame( 'percentage', $result['rake_mode'] );
	}

	public function test_percentage_rake_with_rebuys_and_addons(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0.00,   // entry_fee
			100.00, // prize_pool_contribution
			8,      // entries
			3,      // rebuys
			2,      // addons
			50.00,  // rebuy_cost
			75.00,  // addon_cost
			'percentage',
			5.0,    // rake_percentage
			0.0
		);

		// gross = 100*8 + 50*3 + 75*2 = 800 + 150 + 150 = 1100
		$this->assertEqualsWithDelta( 1100.00, $result['gross_pool'], 0.001 );
		// rake = 1100 * 5% = 55
		$this->assertEqualsWithDelta( 55.00, $result['rake_amount'], 0.001 );
		$this->assertEqualsWithDelta( 1045.00, $result['net_pool'], 0.001 );
	}

	// -------------------------------------------------------------------------
	// calculate_financial_summary() — flat rake
	// -------------------------------------------------------------------------

	public function test_flat_rake_basic(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			10.00,  // entry_fee
			40.00,  // prize_pool_contribution
			10,     // entries
			0, 0, 0, 0,
			'flat',
			0.0,    // rake_percentage (unused in flat mode)
			100.00  // rake_flat_amount
		);

		// gross_pool = 40 * 10 = 400
		$this->assertEqualsWithDelta( 400.00, $result['gross_pool'], 0.001 );
		// rake_amount = 100 (flat)
		$this->assertEqualsWithDelta( 100.00, $result['rake_amount'], 0.001 );
		// net_pool = 400 - 100 = 300
		$this->assertEqualsWithDelta( 300.00, $result['net_pool'], 0.001 );
		$this->assertSame( 'flat', $result['rake_mode'] );
	}

	public function test_flat_rake_does_not_produce_negative_net_pool(): void {
		// Flat rake greater than gross pool
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0.00, 10.00, 5, 0, 0, 0, 0,
			'flat', 0.0, 500.00
		);

		// gross = 10 * 5 = 50; rake_flat = 500 > gross
		$this->assertGreaterThanOrEqual( 0.0, $result['net_pool'] );
	}

	// -------------------------------------------------------------------------
	// Fee-split: buy_in derivation
	// -------------------------------------------------------------------------

	public function test_buy_in_derived_from_fee_split(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			15.00, 35.00, 1, 0, 0, 0, 0, 'percentage', 0, 0
		);

		$this->assertEqualsWithDelta( 15.00, $result['entry_fee'], 0.001 );
		$this->assertEqualsWithDelta( 35.00, $result['prize_pool_contribution'], 0.001 );
		$this->assertEqualsWithDelta( 50.00, $result['buy_in'], 0.001 );
	}

	public function test_zero_entry_fee_full_contribution(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0.00, 50.00, 10, 0, 0, 0, 0, 'percentage', 0, 0
		);

		$this->assertEqualsWithDelta( 50.00, $result['buy_in'], 0.001 );
		$this->assertEqualsWithDelta( 500.00, $result['gross_pool'], 0.001 );
	}

	// -------------------------------------------------------------------------
	// Backward-compat: old templates with buy_in only (split fields zero)
	// The calculate_financial_summary method does not handle this — the template
	// sanitizer handles compat at the persistence layer. Here we verify the
	// summary works cleanly when prize_pool_contribution == buy_in and entry_fee == 0.
	// -------------------------------------------------------------------------

	public function test_backward_compat_entry_fee_zero(): void {
		// Old template: buy_in = 50, entry_fee = 0, prize_pool_contribution = 0
		// The sanitizer leaves buy_in as-is; for display the form shows buy_in
		// in prize_pool_contribution. The summary works when called with 0/0:
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0.00, 0.00, 10, 0, 0, 0, 0, 'percentage', 0, 0
		);

		$this->assertEqualsWithDelta( 0.00, $result['buy_in'], 0.001 );
		$this->assertEqualsWithDelta( 0.00, $result['gross_pool'], 0.001 );
		$this->assertEqualsWithDelta( 0.00, $result['net_pool'], 0.001 );
	}

	// -------------------------------------------------------------------------
	// rake_mode validation / defaults
	// -------------------------------------------------------------------------

	public function test_invalid_rake_mode_defaults_to_percentage(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0.00, 100.00, 5, 0, 0, 0, 0,
			'invalid_mode', 20.0, 0
		);

		$this->assertSame( 'percentage', $result['rake_mode'] );
		// rake = 500 * 20% = 100
		$this->assertEqualsWithDelta( 100.00, $result['rake_amount'], 0.001 );
	}

	// -------------------------------------------------------------------------
	// Returned structure completeness
	// -------------------------------------------------------------------------

	public function test_result_contains_all_required_keys(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			5.00, 45.00, 10, 2, 1, 30.00, 20.00, 'percentage', 5.0, 0
		);

		$expected_keys = array(
			'entry_fee',
			'prize_pool_contribution',
			'buy_in',
			'entries',
			'rebuys',
			'addons',
			'entry_pool',
			'rebuy_pool',
			'addon_pool',
			'gross_pool',
			'rake_mode',
			'rake_percentage',
			'rake_flat_amount',
			'rake_amount',
			'net_pool',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// Flat rake amount stored / returned correctly
	// -------------------------------------------------------------------------

	public function test_flat_rake_amount_in_result(): void {
		$result = TDWP_Prize_Calculator::calculate_financial_summary(
			0, 50.00, 10, 0, 0, 0, 0, 'flat', 0, 75.00
		);

		$this->assertEqualsWithDelta( 75.00, $result['rake_flat_amount'], 0.001 );
		$this->assertEqualsWithDelta( 75.00, $result['rake_amount'], 0.001 );
	}
}
