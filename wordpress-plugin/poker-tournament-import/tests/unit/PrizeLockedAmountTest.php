<?php
/**
 * Unit tests for tdwp-cma.15: fixed-amount, locked, recipient, and display
 * per-place prize fields.
 *
 * Covers:
 *  1. Locked/fixed-amount place keeps its value while unlocked places split
 *     the remainder proportionally.
 *  2. Backward-compat decode of an old {place, percentage}-only structure.
 *  3. validate_structure_data() accepts a mix of fixed-amount and percentage
 *     places without requiring the percentage-sum check on locked/fixed places.
 *
 * Pure-logic — no DB access — runs offline under the no-database harness.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// ── Additional stubs needed beyond what bootstrap.php provides ────────────────

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return sanitize_text_field( $str );
	}
}

// ── Load classes under test ───────────────────────────────────────────────────

$plugin_dir = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR;

if ( ! class_exists( 'TDWP_Prize_Structure' ) ) {
	require $plugin_dir . 'includes/tournament-manager/class-prize-structure.php';
}

if ( ! class_exists( 'TDWP_Prize_Calculator' ) ) {
	require $plugin_dir . 'includes/tournament-manager/class-prize-calculator.php';
}

/**
 * Helper: expose private methods on TDWP_Prize_Structure via reflection.
 */
function call_prize_structure_private( string $method, ...$args ) {
	$obj = new TDWP_Prize_Structure();
	$ref = new ReflectionMethod( $obj, $method );
	// setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5 — omit it.
	return $ref->invoke( $obj, ...$args );
}

/**
 * Helper: build a minimal valid data array for validate_structure_data().
 */
function make_structure_data( array $places, int $max_players = 50 ): array {
	return array(
		'name'           => 'Test',
		'description'    => '',
		'is_template'    => 0,
		'min_players'    => 1,
		'max_players'    => $max_players,
		'structure_json' => wp_json_encode( $places ),
	);
}

// ─────────────────────────────────────────────────────────────────────────────

class PrizeLockedAmountTest extends TestCase {

	// ── 1. Calculator: locked / fixed-amount behavior ─────────────────────────

	/**
	 * A fixed-amount place is paid its dollar value; the remainder goes to the
	 * unlocked, percentage-based places in proportion.
	 */
	public function test_fixed_amount_place_is_paid_first_and_remainder_split() {
		$structure = array(
			array( 'place' => 1, 'percentage' => 0.0, 'amount' => 200.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 60.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 40.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		// Place 1: fixed $200.
		$this->assertSame( 200.0, $payouts[1]['amount'] );

		// Remaining pool = $800; place 2 gets 60 % = $480, place 3 gets 40 % = $320.
		$this->assertSame( 480.0, $payouts[2]['amount'] );
		$this->assertSame( 320.0, $payouts[3]['amount'] );

		// No money left over.
		$total = $payouts[1]['amount'] + $payouts[2]['amount'] + $payouts[3]['amount'];
		$this->assertEqualsWithDelta( 1000.0, $total, 0.01 );
	}

	/**
	 * A locked percentage place is frozen against the full pool; remaining
	 * (percentage) places share what's left.
	 */
	public function test_locked_percentage_place_is_frozen_against_full_pool() {
		$structure = array(
			// Locked at 50 % of the full $1 000 = $500.
			array( 'place' => 1, 'percentage' => 50.0, 'amount' => null, 'locked' => true,  'recipient_player_id' => null, 'display' => true ),
			// Unlocked; together they own 100 % of the remaining $500.
			array( 'place' => 2, 'percentage' => 60.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 40.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertSame( 500.0, $payouts[1]['amount'] );   // 50 % of $1 000.
		$this->assertSame( 300.0, $payouts[2]['amount'] );   // 60 % of $500.
		$this->assertSame( 200.0, $payouts[3]['amount'] );   // 40 % of $500.
	}

	/**
	 * A structure with only unlocked percentage places behaves identically to
	 * the old code path (backward-compat regression check).
	 */
	public function test_all_unlocked_percentage_places_split_full_pool() {
		$structure = array(
			array( 'place' => 1, 'percentage' => 50.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 30.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 20.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 1000.0, $structure );

		$this->assertSame( 500.0, $payouts[1]['amount'] );
		$this->assertSame( 300.0, $payouts[2]['amount'] );
		$this->assertSame( 200.0, $payouts[3]['amount'] );
	}

	/**
	 * Recipient player ID and display flag are carried through into payout rows.
	 */
	public function test_metadata_carried_through_to_payout_rows() {
		$structure = array(
			array( 'place' => 1, 'percentage' => 100.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => 42, 'display' => false ),
		);

		$payouts = TDWP_Prize_Calculator::calculate_payouts_from_array( 500.0, $structure );

		$this->assertSame( 42, $payouts[1]['recipient_player_id'] );
		$this->assertFalse( $payouts[1]['display'] );
	}

	// ── 2. Backward-compat decode ─────────────────────────────────────────────

	/**
	 * Old {place, percentage}-only JSON decodes cleanly with safe defaults for
	 * all new keys.
	 */
	public function test_decode_old_structure_json_backward_compat() {
		$old_json = wp_json_encode( array(
			array( 'place' => 1, 'percentage' => 50.0 ),
			array( 'place' => 2, 'percentage' => 30.0 ),
			array( 'place' => 3, 'percentage' => 20.0 ),
		) );

		$decoded = call_prize_structure_private( 'decode_structure_json', $old_json );

		$this->assertCount( 3, $decoded );

		foreach ( $decoded as $place ) {
			$this->assertNull( $place['amount'] );
			$this->assertFalse( $place['locked'] );
			$this->assertNull( $place['recipient_player_id'] );
			$this->assertTrue( $place['display'] );
		}

		$this->assertSame( 1, $decoded[0]['place'] );
		$this->assertSame( 50.0, $decoded[0]['percentage'] );
	}

	// ── 3. Validation: mixed fixed-amount + percentage ────────────────────────

	/**
	 * validate_structure_data() accepts a structure where one place has a fixed
	 * amount and the remaining unlocked percentage places sum to 100.
	 */
	public function test_validation_accepts_mixed_fixed_amount_and_percentage_places() {
		$places = array(
			// Fixed $200 — excluded from percentage-sum check.
			array( 'place' => 1, 'percentage' => 0.0,  'amount' => 200.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			// Unlocked percentage places that sum to 100.
			array( 'place' => 2, 'percentage' => 60.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 40.0, 'amount' => null,  'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$data   = make_structure_data( $places );
		$result = call_prize_structure_private( 'validate_structure_data', $data );

		$this->assertTrue( $result );
	}

	/**
	 * validate_structure_data() accepts a structure where a locked percentage
	 * place is excluded from the sum and the remaining places sum to 100.
	 */
	public function test_validation_accepts_locked_percentage_place_excluded_from_sum() {
		$places = array(
			// Locked — excluded from sum.
			array( 'place' => 1, 'percentage' => 50.0, 'amount' => null, 'locked' => true,  'recipient_player_id' => null, 'display' => true ),
			// Unlocked sum = 100.
			array( 'place' => 2, 'percentage' => 60.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 3, 'percentage' => 40.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$data   = make_structure_data( $places );
		$result = call_prize_structure_private( 'validate_structure_data', $data );

		$this->assertTrue( $result );
	}

	/**
	 * validate_structure_data() rejects a structure where the unlocked percentage
	 * places do NOT sum to 100.
	 */
	public function test_validation_rejects_unlocked_percentages_not_summing_to_100() {
		$places = array(
			array( 'place' => 1, 'percentage' => 60.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 20.0, 'amount' => null, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			// Sums to 80, not 100.
		);

		$data   = make_structure_data( $places );
		$result = call_prize_structure_private( 'validate_structure_data', $data );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * validate_structure_data() accepts a structure that consists entirely of
	 * fixed-amount places (percentage-sum check is skipped because there are no
	 * unlocked percentage places).
	 */
	public function test_validation_accepts_all_fixed_amount_places() {
		$places = array(
			array( 'place' => 1, 'percentage' => 0.0, 'amount' => 500.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
			array( 'place' => 2, 'percentage' => 0.0, 'amount' => 300.0, 'locked' => false, 'recipient_player_id' => null, 'display' => true ),
		);

		$data   = make_structure_data( $places );
		$result = call_prize_structure_private( 'validate_structure_data', $data );

		$this->assertTrue( $result );
	}
}
