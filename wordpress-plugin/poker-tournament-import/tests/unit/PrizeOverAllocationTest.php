<?php
/**
 * Unit tests for the over-allocation guard in TDWP_Prize_Calculator (tdwp-cma.24).
 *
 * When locked + fixed-amount place values exceed the prize pool, unlocked
 * percentage-based places must receive $0 — never a negative payout.
 * The static flag TDWP_Prize_Calculator::$last_over_allocated must be set to
 * true in that situation and false otherwise.
 *
 * Pure-logic tests — no DB access — run offline under the no-database harness.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// ── Load class under test ─────────────────────────────────────────────────────

$plugin_dir = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR;

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require $plugin_dir . 'includes/tournament-manager/class-prize-calculator.php';
}

// ─────────────────────────────────────────────────────────────────────────────

/**
 * PrizeOverAllocationTest
 *
 * @covers TDWP_Prize_Calculator::calculate_payouts_from_array
 */
class PrizeOverAllocationTest extends TestCase {

	// ── Guard: unlocked places get 0, never negative ──────────────────────────

	/**
	 * When fixed amounts alone exceed the prize pool, unlocked percentage places
	 * must receive 0 — not a negative payout.
	 */
	public function test_fixed_amounts_exceed_pool_unlocked_places_receive_zero(): void {
		// Fixed: $600 + $600 = $1 200 > $1 000 pool.
		$structure = array(
			array( 'place' => 1, 'percentage' => 0.0,  'amount' => 600.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 0.0,  'amount' => 600.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 100.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		// Fixed places keep their configured amounts (no retroactive clipping).
		$this->assertSame( 600.0, $payouts[1]['amount'] );
		$this->assertSame( 600.0, $payouts[2]['amount'] );

		// Unlocked place must receive exactly 0, never negative.
		$this->assertGreaterThanOrEqual( 0.0, $payouts[3]['amount'], 'Unlocked place amount must not be negative' );
		$this->assertSame( 0.0, $payouts[3]['amount'] );
	}

	/**
	 * When a locked percentage place alone consumes the full pool, remaining
	 * unlocked places receive 0.
	 */
	public function test_locked_percentage_consumes_full_pool_unlocked_gets_zero(): void {
		// Locked: 100% of $1 000 = $1 000. Remaining for unlocked = $0.
		$structure = array(
			array( 'place' => 1, 'percentage' => 100.0, 'amount' => null, 'locked' => true,  'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 50.0,  'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertSame( 1000.0, $payouts[1]['amount'] );
		$this->assertGreaterThanOrEqual( 0.0, $payouts[2]['amount'], 'Unlocked place amount must not be negative' );
		$this->assertSame( 0.0, $payouts[2]['amount'] );
	}

	/**
	 * Mixed over-allocation: locked percentage + fixed amount both contribute.
	 * Remaining for unlocked = max(0, pool - locked_total).
	 */
	public function test_mixed_over_allocation_unlocked_never_negative(): void {
		// Locked 80% of $500 = $400; fixed $200 = $200; total locked = $600 > $500.
		$structure = array(
			array( 'place' => 1, 'percentage' => 80.0,  'amount' => null,  'locked' => true,  'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 0.0,   'amount' => 200.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 100.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 500.0, $structure );

		foreach ( $payouts as $place => $row ) {
			$this->assertGreaterThanOrEqual(
				0.0,
				$row['amount'],
				"Place {$place} amount must not be negative; got {$row['amount']}"
			);
		}
	}

	// ── Flag: $last_over_allocated ────────────────────────────────────────────

	/**
	 * The static flag is true when locked + fixed exceed the pool.
	 */
	public function test_over_allocated_flag_is_true_when_exceeded(): void {
		$structure = array(
			array( 'place' => 1, 'percentage' => 0.0,  'amount' => 800.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 0.0,  'amount' => 400.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 50.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertTrue(
			TDWP_Prize_Calculator::$last_over_allocated,
			'$last_over_allocated must be true when locked+fixed exceed the pool'
		);
	}

	/**
	 * The static flag is false when amounts are within the pool.
	 */
	public function test_over_allocated_flag_is_false_when_within_pool(): void {
		$structure = array(
			array( 'place' => 1, 'percentage' => 60.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 40.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertFalse(
			TDWP_Prize_Calculator::$last_over_allocated,
			'$last_over_allocated must be false when locked+fixed are within the pool'
		);
	}

	/**
	 * Flag is reset at the start of each call so a stale true from a prior
	 * over-allocated call is not observed on a subsequent normal call.
	 */
	public function test_flag_resets_between_calls(): void {
		// First call: over-allocated.
		$over_structure = array(
			array( 'place' => 1, 'percentage' => 0.0, 'amount' => 2000.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);
		TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $over_structure );
		$this->assertTrue( TDWP_Prize_Calculator::$last_over_allocated );

		// Second call: normal.
		$normal_structure = array(
			array( 'place' => 1, 'percentage' => 100.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);
		TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $normal_structure );
		$this->assertFalse( TDWP_Prize_Calculator::$last_over_allocated, 'Flag must be reset to false on non-over-allocated call' );
	}

	// ── Regression: normal structures still produce correct payouts ───────────

	/**
	 * Normal (no locked/fixed) structure: full pool is distributed correctly.
	 * This is a regression check that the guard does not break the happy path.
	 */
	public function test_normal_structure_unaffected_by_guard(): void {
		$structure = array(
			array( 'place' => 1, 'percentage' => 50.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 30.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 20.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertSame( 500.0, $payouts[1]['amount'] );
		$this->assertSame( 300.0, $payouts[2]['amount'] );
		$this->assertSame( 200.0, $payouts[3]['amount'] );
		$this->assertFalse( TDWP_Prize_Calculator::$last_over_allocated );
	}
}
