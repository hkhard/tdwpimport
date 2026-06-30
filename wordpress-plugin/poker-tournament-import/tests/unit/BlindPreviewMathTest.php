<?php
/**
 * Unit tests for TDWP_Blind_Schedule::calculate_schedule_summary() (tdwp-cma.10).
 *
 * The helper is purely stateless — no database access — so it runs fully
 * offline under the no-database harness.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// class-blind-schedule.php is already required by bootstrap.php.

/**
 * BlindPreviewMathTest
 *
 * @covers TDWP_Blind_Schedule::calculate_schedule_summary
 */
class BlindPreviewMathTest extends TestCase {

	// ── helpers ──────────────────────────────────────────────────────────────

	/**
	 * Build a minimal play level row.
	 *
	 * @param int $duration_minutes Per-level playing time in minutes.
	 * @return array
	 */
	private function play_level( int $duration_minutes = 15 ): array {
		return array(
			'small_blind'            => 50,
			'big_blind'              => 100,
			'ante'                   => 0,
			'duration_minutes'       => $duration_minutes,
			'is_break'               => 0,
			'break_duration_minutes' => 0,
		);
	}

	/**
	 * Build a minimal break level row.
	 *
	 * @param int $break_duration_minutes Break length in minutes.
	 * @return array
	 */
	private function break_level( int $break_duration_minutes = 15 ): array {
		return array(
			'small_blind'            => 0,
			'big_blind'              => 0,
			'ante'                   => 0,
			'duration_minutes'       => 0,
			'is_break'               => 1,
			'break_duration_minutes' => $break_duration_minutes,
		);
	}

	// ── empty input ──────────────────────────────────────────────────────────

	public function test_empty_levels_returns_zero_totals(): void {
		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( array() );

		$this->assertSame( 0, $summary['total_minutes'] );
		$this->assertSame( 0, $summary['play_minutes'] );
		$this->assertSame( 0, $summary['play_count'] );
		$this->assertSame( 0, $summary['break_count'] );
	}

	// ── play levels only ─────────────────────────────────────────────────────

	public function test_play_levels_sum_correctly(): void {
		$levels = array(
			$this->play_level( 15 ),
			$this->play_level( 15 ),
			$this->play_level( 15 ),
		);

		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $levels );

		$this->assertSame( 45, $summary['total_minutes'] );
		$this->assertSame( 45, $summary['play_minutes'] );
		$this->assertSame( 3,  $summary['play_count'] );
		$this->assertSame( 0,  $summary['break_count'] );
	}

	// ── break levels only ────────────────────────────────────────────────────

	public function test_break_levels_sum_correctly(): void {
		$levels = array(
			$this->break_level( 10 ),
			$this->break_level( 15 ),
		);

		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $levels );

		$this->assertSame( 25, $summary['total_minutes'] );
		$this->assertSame( 0,  $summary['play_minutes'] );
		$this->assertSame( 0,  $summary['play_count'] );
		$this->assertSame( 2,  $summary['break_count'] );
	}

	// ── mixed levels ─────────────────────────────────────────────────────────

	public function test_mixed_levels_include_breaks_in_total_duration(): void {
		$levels = array(
			$this->play_level( 15 ),
			$this->play_level( 15 ),
			$this->play_level( 15 ),
			$this->break_level( 10 ),
			$this->play_level( 15 ),
			$this->play_level( 15 ),
		);

		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $levels );

		// 5 play × 15 = 75 play minutes + 10 break = 85 total.
		$this->assertSame( 85, $summary['total_minutes'] );
		$this->assertSame( 75, $summary['play_minutes'] );
		$this->assertSame( 5,  $summary['play_count'] );
		$this->assertSame( 1,  $summary['break_count'] );
	}

	// ── default level duration fallback ──────────────────────────────────────

	public function test_default_duration_used_when_duration_minutes_absent(): void {
		// Level rows without duration_minutes key — should fall back to the
		// $default_level_minutes parameter (20 here).
		$levels = array(
			array( 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'is_break' => 0, 'break_duration_minutes' => 0 ),
		);

		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $levels, 20 );

		$this->assertSame( 40, $summary['total_minutes'] );
		$this->assertSame( 40, $summary['play_minutes'] );
		$this->assertSame( 2,  $summary['play_count'] );
	}

	// ── integration: suggest_schedule output feeds calculate_schedule_summary ─

	public function test_calculate_summary_matches_suggest_schedule_estimate(): void {
		$suggested = TDWP_Blind_Schedule::suggest_schedule( array(
			'starting_chips'   => 10000,
			'desired_duration' => 180,
			'style'            => 'standard',
		) );

		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $suggested['levels'] );

		// The suggest result's estimated_total_minutes should equal the summary.
		$this->assertSame(
			$suggested['estimated_total_minutes'],
			$summary['total_minutes'],
			'calculate_schedule_summary total should equal suggest_schedule estimated_total_minutes'
		);

		$this->assertSame( $suggested['level_count'], $summary['play_count'] );
		$this->assertSame( $suggested['break_count'], $summary['break_count'] );
	}

	// ── return shape ─────────────────────────────────────────────────────────

	public function test_return_array_has_all_required_keys(): void {
		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( array( $this->play_level() ) );

		$this->assertArrayHasKey( 'total_minutes', $summary );
		$this->assertArrayHasKey( 'play_minutes',  $summary );
		$this->assertArrayHasKey( 'play_count',    $summary );
		$this->assertArrayHasKey( 'break_count',   $summary );
	}
}
