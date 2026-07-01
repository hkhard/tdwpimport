<?php
/**
 * Unit tests for the fresh-activation template seeding self-heal (tdwp-luk).
 *
 * The activation path can create the template tables but leave them empty when
 * insert_default_templates() is skipped or aborts (tables not yet visible, or an
 * earlier fatal in activate()). The option-based guard inside
 * insert_default_templates() trusts its own flags, so an empty table never
 * re-seeds on its own.
 *
 * The self-heal in Poker_Tournament_Import::ensure_default_templates_seeded()
 * fixes this by clearing the three seed-gate options and re-calling
 * insert_default_templates(). This test locks that mechanism: the gate options
 * fully control whether seeding runs, and clearing them forces a re-seed.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
	. 'includes/tournament-manager/class-database-schema.php';

/**
 * @covers TDWP_Database_Schema::insert_default_templates
 */
class TemplateSeedSelfHealTest extends TestCase {

	/**
	 * The three seed-gate options the self-heal clears to force a re-seed.
	 *
	 * @var string[]
	 */
	private array $gate_options = array(
		'tdwp_default_templates_inserted',
		'tdwp_prd_prize_templates_v1_inserted',
		'tdwp_hyper_turbo_v1_inserted',
	);

	protected function setUp(): void {
		parent::setUp();
		// Start every test from a clean, unseeded option store.
		foreach ( $this->gate_options as $opt ) {
			delete_option( $opt );
		}
	}

	/**
	 * A fresh seed sets every gate option, marking each branch as completed.
	 */
	public function test_fresh_seed_sets_all_gate_options(): void {
		foreach ( $this->gate_options as $opt ) {
			$this->assertFalse( get_option( $opt, false ), "Precondition: {$opt} must be unset" );
		}

		$result = TDWP_Database_Schema::insert_default_templates();

		$this->assertTrue( $result );
		foreach ( $this->gate_options as $opt ) {
			$this->assertTrue(
				(bool) get_option( $opt, false ),
				"After seeding, gate option {$opt} must be set"
			);
		}
	}

	/**
	 * When all gate options are already set, seeding is a no-op that still
	 * succeeds — the guard prevents duplicate inserts on repeated activations.
	 */
	public function test_seed_is_idempotent_when_gates_set(): void {
		foreach ( $this->gate_options as $opt ) {
			update_option( $opt, true );
		}

		$this->assertTrue( TDWP_Database_Schema::insert_default_templates() );
	}

	/**
	 * The self-heal contract: clearing the gate options (as
	 * ensure_default_templates_seeded() does for empty tables) makes a
	 * subsequent insert_default_templates() re-run every seeding branch,
	 * re-setting all three gate options.
	 */
	public function test_clearing_gates_forces_reseed(): void {
		// Simulate a prior (interrupted) activation that set the flags but left
		// the tables empty.
		foreach ( $this->gate_options as $opt ) {
			update_option( $opt, true );
		}

		// Self-heal step: clear the gates.
		foreach ( $this->gate_options as $opt ) {
			delete_option( $opt );
			$this->assertFalse( get_option( $opt, false ), "Gate {$opt} must be cleared before re-seed" );
		}

		// Re-seed and confirm every branch ran again.
		$this->assertTrue( TDWP_Database_Schema::insert_default_templates() );
		foreach ( $this->gate_options as $opt ) {
			$this->assertTrue(
				(bool) get_option( $opt, false ),
				"After re-seed, gate option {$opt} must be set again"
			);
		}
	}
}
