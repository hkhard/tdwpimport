<?php
/**
 * Unit tests for TDWP_Prize_Structure::recommend_place_count() and
 * TDWP_Prize_Structure::generate_suggested_structure() (tdwp-cma.17).
 *
 * Pure-logic methods — no DB access — so they run fully offline under the
 * no-database harness. The class is loaded here rather than in bootstrap.php
 * because it depends on wp_json_encode / WP_Error stubs that are not needed
 * by any other test suite.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// ── Minimal stubs for symbols used by class-prize-structure.php ──────────────

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	// Already defined in wp-stubs.php, but guard in case load order changes.
	function current_time( $type = 'mysql' ) {
		return date( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public function __construct( $code = '', $message = '' ) {
			$this->errors[ $code ][] = $message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// Load the class under test.
require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-structure.php';

// ── Test suite ────────────────────────────────────────────────────────────────

final class PrizeStructureSuggestTest extends TestCase {

	private TDWP_Prize_Structure $sut;

	protected function setUp(): void {
		$this->sut = new TDWP_Prize_Structure();
	}

	// ── recommend_place_count() ───────────────────────────────────────────────

	public function test_recommend_place_count_zero_returns_zero(): void {
		$this->assertSame( 0, $this->sut->recommend_place_count( 0 ) );
	}

	public function test_recommend_place_count_one_returns_three(): void {
		$this->assertSame( 3, $this->sut->recommend_place_count( 1 ) );
	}

	public function test_recommend_place_count_boundary_20_returns_three(): void {
		$this->assertSame( 3, $this->sut->recommend_place_count( 20 ) );
	}

	public function test_recommend_place_count_boundary_21_returns_five(): void {
		$this->assertSame( 5, $this->sut->recommend_place_count( 21 ) );
	}

	public function test_recommend_place_count_boundary_50_returns_five(): void {
		$this->assertSame( 5, $this->sut->recommend_place_count( 50 ) );
	}

	public function test_recommend_place_count_boundary_51_returns_nine(): void {
		$this->assertSame( 9, $this->sut->recommend_place_count( 51 ) );
	}

	public function test_recommend_place_count_boundary_100_returns_nine(): void {
		$this->assertSame( 9, $this->sut->recommend_place_count( 100 ) );
	}

	public function test_recommend_place_count_101_returns_fifteen_percent(): void {
		// 15 % of 101 = 15.15 → round → 15.
		$this->assertSame( 15, $this->sut->recommend_place_count( 101 ) );
	}

	public function test_recommend_place_count_200_returns_thirty(): void {
		// 15 % of 200 = 30.
		$this->assertSame( 30, $this->sut->recommend_place_count( 200 ) );
	}

	public function test_recommend_place_count_minimum_nine_for_large_fields(): void {
		// For 101+ players the minimum is always >= 9 regardless.
		$result = $this->sut->recommend_place_count( 101 );
		$this->assertGreaterThanOrEqual( 9, $result );
	}

	// ── generate_suggested_structure() ───────────────────────────────────────

	/** Assert that a structure array has the expected place count and sums to 100. */
	private function assert_valid_structure( array $structure, int $expected_places ): void {
		$this->assertCount(
			$expected_places,
			$structure,
			"Expected {$expected_places} places, got " . count( $structure )
		);

		$total = array_sum( array_column( $structure, 'percentage' ) );
		$this->assertEqualsWithDelta(
			100.0,
			$total,
			0.01,
			"Percentages must sum to 100, got {$total}"
		);

		// Places must be numbered 1..N in order.
		foreach ( $structure as $index => $entry ) {
			$this->assertSame( $index + 1, $entry['place'] );
		}
	}

	public function test_generate_suggested_structure_zero_players_returns_empty(): void {
		$this->assertSame( array(), $this->sut->generate_suggested_structure( 0 ) );
	}

	public function test_generate_suggested_structure_3_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 10 ); // tier: 1-20 → 3 places
		$this->assert_valid_structure( $structure, 3 );
	}

	public function test_generate_suggested_structure_3_places_canonical_percentages(): void {
		$structure = $this->sut->generate_suggested_structure( 10 );
		$this->assertEqualsWithDelta( 50.0, $structure[0]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 30.0, $structure[1]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 20.0, $structure[2]['percentage'], 0.001 );
	}

	public function test_generate_suggested_structure_5_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 30 ); // tier: 21-50 → 5 places
		$this->assert_valid_structure( $structure, 5 );
	}

	public function test_generate_suggested_structure_5_places_canonical_percentages(): void {
		$structure = $this->sut->generate_suggested_structure( 30 );
		$this->assertEqualsWithDelta( 40.0, $structure[0]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 25.0, $structure[1]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 15.0, $structure[2]['percentage'], 0.001 );
		$this->assertEqualsWithDelta( 12.0, $structure[3]['percentage'], 0.001 );
		$this->assertEqualsWithDelta(  8.0, $structure[4]['percentage'], 0.001 );
	}

	public function test_generate_suggested_structure_9_places_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 75 ); // tier: 51-100 → 9 places
		$this->assert_valid_structure( $structure, 9 );
	}

	public function test_generate_suggested_structure_large_field_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 200 ); // 15% of 200 = 30 places
		$this->assert_valid_structure( $structure, 30 );
	}

	public function test_generate_suggested_structure_101_players_sums_to_100(): void {
		$structure = $this->sut->generate_suggested_structure( 101 ); // 15 places
		$this->assert_valid_structure( $structure, 15 );
	}

	public function test_generate_suggested_structure_first_place_highest_pct(): void {
		// First place must always receive the highest single percentage.
		foreach ( array( 10, 30, 75, 101, 200 ) as $players ) {
			$structure = $this->sut->generate_suggested_structure( $players );
			$first_pct = $structure[0]['percentage'];
			$last_pct  = end( $structure )['percentage'];
			$this->assertGreaterThan(
				$last_pct,
				$first_pct,
				"First place pct ({$first_pct}) should exceed last ({$last_pct}) for {$players} players"
			);
		}
	}
}
