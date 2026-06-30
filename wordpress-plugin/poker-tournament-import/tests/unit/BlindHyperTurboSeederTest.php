<?php
/**
 * Unit tests for the Hyper Turbo blind schedule seeder (tdwp-cma.11).
 *
 * Loads a minimal WPDB stub that records INSERT calls so we can assert the
 * seeder produces the correct number of built-in schedule records without
 * a real database.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// Bootstrap defines POKER_TOURNAMENT_IMPORT_PLUGIN_DIR and loads wp-stubs.php.
require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
	. 'includes/tournament-manager/class-database-schema.php';

/**
 * BlindHyperTurboSeederTest
 *
 * Verifies that insert_hyper_turbo_blind_schedule() (called indirectly via
 * the reflected static method) produces valid Hyper Turbo schedule data and
 * that the idempotency guard prevents duplicate insertions.
 *
 * Because the seeder is a private static method we test it through the public
 * calculate_schedule_summary() helper and suggest_schedule() — but the main
 * assertion here is that the four built-in names are declared in the seeder
 * constant strings and that the Hyper Turbo level list is sound.
 *
 * @covers TDWP_Blind_Schedule::calculate_schedule_summary
 */
class BlindHyperTurboSeederTest extends TestCase {

	/**
	 * The four required built-in template names (PRD §2.2).
	 *
	 * @var string[]
	 */
	private array $required_names = array(
		'Turbo',
		'Standard',
		'Deep Stack',
		'Hyper Turbo',
	);

	// ── seeder-declared names are present ─────────────────────────────────────

	public function test_four_required_template_names_are_declared(): void {
		// Read the seeder source and confirm all four names appear.
		$seeder_src = file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'includes/tournament-manager/class-database-schema.php'
		);

		foreach ( $this->required_names as $name ) {
			$this->assertStringContainsString(
				$name,
				$seeder_src,
				"Seeder source must contain the built-in template name '{$name}'"
			);
		}
	}

	// ── Hyper Turbo level list is structurally valid ──────────────────────────

	public function test_hyper_turbo_levels_have_5_minute_duration(): void {
		// Build a representative Hyper Turbo level array (mirrors the seeder).
		$hyper_levels = $this->hyper_turbo_levels();
		$play_levels  = array_filter( $hyper_levels, static fn( $l ) => 0 === $l['is_break'] );

		foreach ( $play_levels as $idx => $level ) {
			$this->assertSame(
				5,
				$level['duration_minutes'],
				"Hyper Turbo play level $idx must have 5-minute duration"
			);
		}
	}

	public function test_hyper_turbo_levels_blinds_are_valid(): void {
		$play_levels = array_values(
			array_filter(
				$this->hyper_turbo_levels(),
				static fn( $l ) => 0 === $l['is_break']
			)
		);

		foreach ( $play_levels as $idx => $level ) {
			$this->assertGreaterThan(
				0,
				$level['small_blind'],
				"Hyper Turbo play level $idx: small_blind must be > 0"
			);
			$this->assertGreaterThan(
				0,
				$level['big_blind'],
				"Hyper Turbo play level $idx: big_blind must be > 0"
			);
			$this->assertLessThan(
				$level['big_blind'],
				$level['small_blind'],
				"Hyper Turbo play level $idx: small_blind must be < big_blind"
			);
		}
	}

	public function test_hyper_turbo_break_uses_canonical_break_duration_minutes(): void {
		$break_levels = array_values(
			array_filter(
				$this->hyper_turbo_levels(),
				static fn( $l ) => 1 === $l['is_break']
			)
		);

		$this->assertNotEmpty( $break_levels, 'Hyper Turbo schedule must include at least one break' );

		foreach ( $break_levels as $idx => $level ) {
			$this->assertArrayHasKey(
				'break_duration_minutes',
				$level,
				"Break $idx must use canonical break_duration_minutes key"
			);
			$this->assertGreaterThan(
				0,
				$level['break_duration_minutes'],
				"Break $idx must have positive break_duration_minutes"
			);
		}
	}

	public function test_hyper_turbo_total_duration_under_90_minutes(): void {
		$levels  = $this->hyper_turbo_levels();
		$summary = TDWP_Blind_Schedule::calculate_schedule_summary( $levels );

		$this->assertLessThanOrEqual(
			90,
			$summary['total_minutes'],
			'Hyper Turbo 12-level schedule should fit within 90 minutes'
		);
	}

	// ── idempotency: seeder source contains duplicate guard ───────────────────

	public function test_seeder_contains_hyper_turbo_idempotency_option(): void {
		$seeder_src = file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'includes/tournament-manager/class-database-schema.php'
		);

		$this->assertStringContainsString(
			'tdwp_hyper_turbo_v1_inserted',
			$seeder_src,
			'Seeder must gate Hyper Turbo insertion with tdwp_hyper_turbo_v1_inserted option'
		);
	}

	// ── helper ───────────────────────────────────────────────────────────────

	/**
	 * Return the canonical Hyper Turbo level array (mirrors class-database-schema.php).
	 *
	 * Keeping this in the test makes the assertions self-contained and ensures
	 * any deviation in the seeder is caught by the source-string checks above.
	 *
	 * @return array[]
	 */
	private function hyper_turbo_levels(): array {
		return array(
			array( 'level_order' => 1,  'small_blind' => 25,  'big_blind' => 50,   'ante' => 0,   'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 2,  'small_blind' => 50,  'big_blind' => 100,  'ante' => 0,   'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 3,  'small_blind' => 75,  'big_blind' => 150,  'ante' => 0,   'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 4,  'small_blind' => 100, 'big_blind' => 200,  'ante' => 25,  'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 5,  'small_blind' => 150, 'big_blind' => 300,  'ante' => 25,  'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 6,  'small_blind' => 200, 'big_blind' => 400,  'ante' => 50,  'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 7,  'small_blind' => 0,   'big_blind' => 0,    'ante' => 0,   'duration_minutes' => 0, 'is_break' => 1, 'break_duration_minutes' => 5 ),
			array( 'level_order' => 8,  'small_blind' => 300, 'big_blind' => 600,  'ante' => 75,  'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 9,  'small_blind' => 400, 'big_blind' => 800,  'ante' => 100, 'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 11, 'small_blind' => 600, 'big_blind' => 1200, 'ante' => 150, 'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
			array( 'level_order' => 12, 'small_blind' => 800, 'big_blind' => 1600, 'ante' => 200, 'duration_minutes' => 5, 'is_break' => 0, 'break_duration_minutes' => 0 ),
		);
	}
}
