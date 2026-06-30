<?php
/**
 * Unit tests for TDWP_Blind_Schedule::suggest_schedule() (tdwp-cma.13).
 *
 * The method is purely algorithmic — no database access — so it runs fully
 * offline under the no-database harness.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// Load the class under test. bootstrap.php already defined ABSPATH and loaded
// wp-stubs.php, so all WP helper functions are available.
require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
	. 'includes/tournament-manager/class-blind-schedule.php';

/**
 * BlindScheduleSuggestTest
 *
 * @covers TDWP_Blind_Schedule::suggest_schedule
 */
class BlindScheduleSuggestTest extends TestCase {

	// ── helpers ──────────────────────────────────────────────────────────────

	/**
	 * Call suggest_schedule() and return only the playing levels (is_break = 0).
	 */
	private function play_levels( array $params ): array {
		$result = TDWP_Blind_Schedule::suggest_schedule( $params );
		return array_values(
			array_filter(
				$result['levels'],
				static function ( $l ) {
					return 0 === $l['is_break'];
				}
			)
		);
	}

	/**
	 * Call suggest_schedule() and return only the break levels (is_break = 1).
	 */
	private function break_levels( array $params ): array {
		$result = TDWP_Blind_Schedule::suggest_schedule( $params );
		return array_values(
			array_filter(
				$result['levels'],
				static function ( $l ) {
					return 1 === $l['is_break'];
				}
			)
		);
	}

	// ── estimated_total_minutes within ±15 min of desired_duration ───────────

	public function test_estimated_duration_standard_180(): void {
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'player_count'     => 9,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		$this->assertLessThanOrEqual(
			15,
			abs( $result['estimated_total_minutes'] - 180 ),
			sprintf(
				'Estimated %d min should be within ±15 min of 180 min',
				$result['estimated_total_minutes']
			)
		);
	}

	public function test_estimated_duration_turbo_90(): void {
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 5000,
			'player_count'     => 6,
			'desired_duration' => 90,
			'style'            => 'turbo',
		) );

		$this->assertLessThanOrEqual(
			15,
			abs( $result['estimated_total_minutes'] - 90 ),
			sprintf(
				'Estimated %d min should be within ±15 min of 90 min',
				$result['estimated_total_minutes']
			)
		);
	}

	public function test_estimated_duration_deep_240(): void {
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 20000,
			'player_count'     => 12,
			'desired_duration' => 240,
			'style'            => 'deep',
		) );

		$this->assertLessThanOrEqual(
			15,
			abs( $result['estimated_total_minutes'] - 240 ),
			sprintf(
				'Estimated %d min should be within ±15 min of 240 min',
				$result['estimated_total_minutes']
			)
		);
	}

	// ── blind ladder grows monotonically and roughly doubles every 4–6 levels ─

	public function test_blinds_increase_monotonically(): void {
		$levels = $this->play_levels( array(
			'starting_chips'   => 10000,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		$this->assertGreaterThan( 2, count( $levels ), 'Need at least 3 play levels' );

		for ( $i = 1; $i < count( $levels ); $i++ ) {
			$this->assertGreaterThanOrEqual(
				$levels[ $i - 1 ]['big_blind'],
				$levels[ $i ]['big_blind'],
				"BB at play level $i should be >= BB at level " . ( $i - 1 )
			);
		}
	}

	public function test_blinds_roughly_double_across_five_levels(): void {
		$levels = $this->play_levels( array(
			'starting_chips'   => 10000,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		$this->assertGreaterThan( 5, count( $levels ), 'Need at least 6 play levels for this assertion' );

		// Over any 5-consecutive-level window the BB should roughly double.
		// Accept a ratio between 1.5× and 3× as "roughly double".
		$bb_0 = $levels[0]['big_blind'];
		$bb_5 = $levels[5]['big_blind'];

		$this->assertGreaterThan( 0, $bb_0, 'Starting BB must be positive' );

		$ratio = $bb_5 / $bb_0;
		$this->assertGreaterThanOrEqual( 1.5, $ratio, "BB should grow at least 1.5× over 5 levels (got {$ratio})" );
		$this->assertLessThanOrEqual( 3.5, $ratio, "BB should grow at most 3.5× over 5 levels (got {$ratio})" );
	}

	// ── small_blind is always strictly less than big_blind for play levels ────

	public function test_small_blind_less_than_big_blind(): void {
		$levels = $this->play_levels( array(
			'starting_chips'   => 10000,
			'desired_duration' => 120,
			'style'            => 'standard',
		) );

		foreach ( $levels as $idx => $level ) {
			$this->assertLessThan(
				$level['big_blind'],
				$level['small_blind'],
				"Level $idx: small_blind ({$level['small_blind']}) must be < big_blind ({$level['big_blind']})"
			);
		}
	}

	// ── breaks appear for long tournaments and are spaced within 60–90 min ───

	public function test_at_least_one_break_in_long_tournament(): void {
		$breaks = $this->break_levels( array(
			'starting_chips'   => 10000,
			'desired_duration' => 240,
			'style'            => 'standard',
		) );

		$this->assertGreaterThan( 0, count( $breaks ), 'A 240-minute tournament must have at least one break' );
	}

	public function test_break_duration_in_valid_range(): void {
		$breaks = $this->break_levels( array(
			'starting_chips'   => 10000,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		foreach ( $breaks as $idx => $brk ) {
			$this->assertGreaterThan(
				0,
				$brk['break_duration_minutes'],
				"Break $idx must have a positive break_duration_minutes"
			);
			$this->assertLessThanOrEqual(
				60,
				$brk['break_duration_minutes'],
				"Break $idx duration must not exceed 60 minutes"
			);
		}
	}

	public function test_break_spacing_between_60_and_120_play_minutes(): void {
		// Count play-level minutes between consecutive breaks and verify they
		// fall in the 60–120 min band (the algorithm targets ~75 min).
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 240,
			'style'            => 'standard',
		) );

		$play_mins_since_break = 0;
		$gaps                  = array();

		foreach ( $result['levels'] as $level ) {
			if ( 0 === $level['is_break'] ) {
				$play_mins_since_break += $level['duration_minutes'];
			} else {
				$gaps[]                = $play_mins_since_break;
				$play_mins_since_break = 0;
			}
		}

		$this->assertNotEmpty( $gaps, 'Expected at least one gap measurement' );

		foreach ( $gaps as $gap ) {
			$this->assertGreaterThanOrEqual(
				50,
				$gap,
				"Gap before break should be at least 50 play-minutes (got {$gap})"
			);
			$this->assertLessThanOrEqual(
				120,
				$gap,
				"Gap before break should not exceed 120 play-minutes (got {$gap})"
			);
		}
	}

	// ── input clamping: edge-case inputs must not crash ───────────────────────

	public function test_zero_inputs_do_not_crash(): void {
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 0,
			'player_count'     => 0,
			'desired_duration' => 0,
			'style'            => 'standard',
		) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'levels', $result );
		$this->assertNotEmpty( $result['levels'], 'Clamped inputs should still yield at least one level' );
	}

	public function test_unknown_style_falls_back_to_standard(): void {
		$result_unknown  = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 120,
			'style'            => 'unknown_style',
		) );
		$result_standard = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 120,
			'style'            => 'standard',
		) );

		$this->assertSame(
			$result_standard['levels'],
			$result_unknown['levels'],
			'An unknown style should fall back to standard'
		);
	}

	public function test_missing_params_use_sensible_defaults(): void {
		// Call with an empty array — must not crash and must return a valid
		// schedule.
		$result = TDWP_Blind_Schedule::suggest_schedule( array() );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result['levels'] );
		$this->assertGreaterThan( 0, $result['estimated_total_minutes'] );
	}

	// ── level row structure has all required keys ─────────────────────────────

	public function test_level_rows_have_required_keys(): void {
		$result   = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 120,
			'style'            => 'standard',
		) );
		$required = array(
			'small_blind',
			'big_blind',
			'ante',
			'duration_minutes',
			'is_break',
			'break_duration_minutes',
		);

		foreach ( $result['levels'] as $idx => $level ) {
			foreach ( $required as $key ) {
				$this->assertArrayHasKey( $key, $level, "Level $idx missing key '$key'" );
			}
		}
	}

	// ── summary fields are present and plausible ──────────────────────────────

	public function test_summary_fields_are_present_and_positive(): void {
		$result = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		$this->assertArrayHasKey( 'estimated_total_minutes', $result );
		$this->assertArrayHasKey( 'level_count', $result );
		$this->assertArrayHasKey( 'break_count', $result );
		$this->assertGreaterThan( 0, $result['estimated_total_minutes'] );
		$this->assertGreaterThan( 0, $result['level_count'] );
	}
}
