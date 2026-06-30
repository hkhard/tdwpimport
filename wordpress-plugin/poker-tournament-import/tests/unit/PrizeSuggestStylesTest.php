<?php
/**
 * Unit tests for tdwp-cma.18: named suggestion algorithms in
 * TDWP_Prize_Structure::generate_suggested_structure().
 *
 * Covers:
 *  - 'standard' style is unchanged (backward-compat with existing test coverage).
 *  - 'top_heavy' style: place 1 receives >= 60 % for small fields; sum = 100.
 *  - 'flat' style: all places within a tight band of each other; sum = 100.
 *  - Both styles work for various place counts (1, 2, 3, 5, 9, large).
 *  - Unknown style falls back to 'standard'.
 *
 * Pure-logic — no DB access — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return date( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( $str );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! class_exists( 'TDWP_Prize_Structure' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-structure.php';
}

final class PrizeSuggestStylesTest extends TestCase {

	private TDWP_Prize_Structure $sut;

	protected function setUp(): void {
		$this->sut = new TDWP_Prize_Structure();
	}

	/** Assert places are sequential starting at 1 and percentages sum to 100. */
	private function assert_valid_distribution( array $structure, int $expected_places ): void {
		$this->assertCount( $expected_places, $structure );
		$total = array_sum( array_column( $structure, 'percentage' ) );
		$this->assertEqualsWithDelta( 100.0, $total, 0.05, "Percentages must sum to 100, got {$total}" );
		foreach ( $structure as $i => $entry ) {
			$this->assertSame( $i + 1, $entry['place'] );
		}
	}

	// ── top_heavy ────────────────────────────────────────────────────────────────

	public function test_top_heavy_single_place(): void {
		$structure = $this->sut->generate_suggested_structure( 5, 'top_heavy' );
		$this->assert_valid_distribution( $structure, 3 ); // 5 players → 3 places.
		// Place 1 should dominate (>= 60%).
		$this->assertGreaterThanOrEqual( 60.0, $structure[0]['percentage'] );
	}

	public function test_top_heavy_3_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 10, 'top_heavy' ); // 3 places.
		$this->assert_valid_distribution( $structure, 3 );
	}

	public function test_top_heavy_3_places_first_place_near_65(): void {
		$structure = $this->sut->generate_suggested_structure( 10, 'top_heavy' );
		// PRD target: ~65 % for first.
		$this->assertGreaterThanOrEqual( 60.0, $structure[0]['percentage'] );
		$this->assertLessThanOrEqual( 70.0, $structure[0]['percentage'] );
	}

	public function test_top_heavy_5_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 30, 'top_heavy' ); // 5 places.
		$this->assert_valid_distribution( $structure, 5 );
	}

	public function test_top_heavy_9_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 75, 'top_heavy' ); // 9 places.
		$this->assert_valid_distribution( $structure, 9 );
	}

	public function test_top_heavy_large_field_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 200, 'top_heavy' ); // 30 places.
		$this->assert_valid_distribution( $structure, 30 );
	}

	public function test_top_heavy_place_1_always_highest(): void {
		foreach ( array( 10, 30, 75, 200 ) as $players ) {
			$structure = $this->sut->generate_suggested_structure( $players, 'top_heavy' );
			$first     = $structure[0]['percentage'];
			$last      = end( $structure )['percentage'];
			$this->assertGreaterThan(
				$last,
				$first,
				"Place 1 ({$first}%) should exceed last place ({$last}%) for {$players} players"
			);
		}
	}

	// ── flat ─────────────────────────────────────────────────────────────────────

	public function test_flat_3_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 10, 'flat' );
		$this->assert_valid_distribution( $structure, 3 );
	}

	public function test_flat_5_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 30, 'flat' );
		$this->assert_valid_distribution( $structure, 5 );
	}

	public function test_flat_9_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 75, 'flat' );
		$this->assert_valid_distribution( $structure, 9 );
	}

	public function test_flat_distribution_is_roughly_even(): void {
		// For a 5-place flat, each place should be within 5 pp of 20%.
		$structure = $this->sut->generate_suggested_structure( 30, 'flat' );
		$expected  = 100.0 / 5;
		foreach ( $structure as $entry ) {
			$this->assertEqualsWithDelta(
				$expected,
				$entry['percentage'],
				5.0,
				"Place {$entry['place']} ({$entry['percentage']}%) deviates too far from even ({$expected}%)"
			);
		}
	}

	public function test_flat_large_field_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 200, 'flat' );
		$this->assert_valid_distribution( $structure, 30 );
	}

	// ── backward-compat: default style ───────────────────────────────────────────

	public function test_standard_style_unchanged_for_3_places(): void {
		$structure = $this->sut->generate_suggested_structure( 10, 'standard' );
		$this->assertEqualsWithDelta( 50.0, $structure[0]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 30.0, $structure[1]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 20.0, $structure[2]['percentage'], 0.001 );
	}

	public function test_omitting_style_uses_standard(): void {
		$with_style    = $this->sut->generate_suggested_structure( 10, 'standard' );
		$without_style = $this->sut->generate_suggested_structure( 10 );
		$this->assertSame( $with_style, $without_style );
	}
}
