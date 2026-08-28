<?php
/**
 * Regression tests for the Tournament Manager feature gate (3.9.9).
 *
 * The plugin loaded ~2.8 MB of PHP on every request, of which ~1.1 MB is the
 * Tournament Manager subsystem (live play + TD3 display). On a 128 MB host that
 * contributed to fatal "Allowed memory size exhausted" errors in wp-admin. The
 * gate lets a site that only imports results skip that code entirely.
 *
 * These tests pin the two properties that make the gate safe:
 *
 *   1. With the gate OFF, no gated tournament-manager file is reachable.
 *   2. The three tournament-manager files the statistics path depends on
 *      (schema, debug logger, stats rollup) stay reachable in BOTH states —
 *      the rollup is the sole writer of the statistics data marts.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class TournamentManagerGateTest extends TestCase {

	/** @var TmLoadSimulator */
	private $sim;

	/** @var string */
	private $root;

	protected function setUp(): void {
		tdwp_test_reset();

		$this->root = dirname( __DIR__, 2 );
		require_once $this->root . '/tests/tools/TmLoadSimulator.php';
		$this->sim = new TmLoadSimulator( $this->root );
	}

	/* -----------------------------------------------------------------
	 * The gate value itself.
	 * --------------------------------------------------------------- */

	public function test_gate_defaults_to_disabled(): void {
		$this->assertArrayNotHasKey(
			'tdwp_tournament_manager_enabled',
			$GLOBALS['tdwp_test_options'],
			'Precondition: option unset.'
		);

		$this->assertFalse(
			(bool) get_option( 'tdwp_tournament_manager_enabled', false ),
			'Tournament Manager must default to OFF.'
		);
	}

	/**
	 * An unchecked checkbox posts the paired hidden "0", so the sanitizer has to
	 * treat the usual falsey spellings as OFF and only "1"/true as ON.
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'booleanInputs' )]
	public function test_boolean_sanitisation( $input, bool $expected ): void {
		$this->assertSame(
			$expected,
			(bool) rest_sanitize_boolean( $input ),
			sprintf( 'Input %s should sanitise to %s.', var_export( $input, true ), var_export( $expected, true ) )
		);
	}

	public static function booleanInputs(): array {
		return array(
			'hidden field default' => array( '0', false ),
			'empty string'         => array( '', false ),
			'string false'         => array( 'false', false ),
			'checkbox checked'     => array( '1', true ),
			'string true'          => array( 'true', true ),
			'bool true'            => array( true, true ),
			'bool false'           => array( false, false ),
		);
	}

	/* -----------------------------------------------------------------
	 * What the gate actually loads.
	 * --------------------------------------------------------------- */

	public function test_no_gated_tm_file_is_reachable_when_disabled(): void {
		$reachable = $this->sim->reachable_files( false );

		$leaked = array_values(
			array_filter( $reachable, array( $this->sim, 'is_gated_tm_file' ) )
		);

		$this->assertSame(
			array(),
			$leaked,
			"These Tournament Manager files are still loaded with the module disabled.\n"
			. "Each one either needs gating, or must be justified in\n"
			. "TmLoadSimulator::ALWAYS_LOADED_TM_FILES:\n  "
			. implode( "\n  ", $leaked )
		);
	}

	/**
	 * The statistics rollup is the sole writer of poker_tournament_players and
	 * poker_player_roi, and runs on every .tdt import. Gating it would silently
	 * stop the data marts updating, which is far worse than the memory it saves.
	 */
	public function test_stats_critical_files_load_in_both_states(): void {
		$critical = array(
			'includes/tournament-manager/class-stats-rollup.php',
			'includes/tournament-manager/class-database-schema.php',
			'includes/tournament-manager/class-debug-logger.php',
		);

		foreach ( array( false, true ) as $enabled ) {
			$reachable = $this->sim->reachable_files( $enabled );
			$label     = $enabled ? 'ON' : 'OFF';

			foreach ( $critical as $file ) {
				$this->assertContains(
					$file,
					$reachable,
					sprintf( '%s must stay reachable with the gate %s.', $file, $label )
				);
			}
		}
	}

	/**
	 * Enabling the module must not lose anything: the OFF set is a strict subset
	 * of the ON set.
	 */
	public function test_disabled_set_is_a_subset_of_enabled_set(): void {
		$off = $this->sim->reachable_files( false );
		$on  = $this->sim->reachable_files( true );

		$only_in_off = array_values( array_diff( $off, $on ) );

		$this->assertSame(
			array(),
			$only_in_off,
			'Disabling the module must not pull in files that enabling it does not: '
			. implode( ', ', $only_in_off )
		);
	}

	/**
	 * Loading a file is only half the problem: code that stays loaded must not
	 * CALL a class that is no longer loaded, or the site fatals with
	 * "Class not found". Every such reference needs a class_exists() guard.
	 */
	public function test_no_unguarded_runtime_reference_to_a_gated_class(): void {
		$problems = $this->sim->unguarded_references( false );

		$this->assertSame(
			array(),
			$problems,
			"These call sites remain loaded but reference a class that is not, so they\n"
			. "would fatal with 'Class not found' while the module is disabled.\n"
			. "Wrap each in a class_exists() guard:\n  "
			. implode( "\n  ", $problems )
		);
	}

	/**
	 * The import -> data mart path must survive the gate intact.
	 *
	 * TDWP_Stats_Rollup is the sole writer of poker_tournament_players and
	 * poker_player_roi. Every collaborator it names must therefore also be
	 * loaded when the module is off, or importing a .tdt would fatal instead of
	 * updating the statistics.
	 */
	public function test_stats_rollup_collaborators_are_available_when_disabled(): void {
		$rollup = 'includes/tournament-manager/class-stats-rollup.php';

		$available = $this->sim->classes_in( $this->sim->reachable_files( false ) );
		$source    = (string) file_get_contents( $this->root . '/' . $rollup );

		// Strip comments/strings so docblock mentions are not counted.
		$code = '';
		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING ), true ) ) {
					$code .= "\n";
					continue;
				}
				$code .= $token[1];
				continue;
			}
			$code .= $token;
		}

		preg_match_all( '/\b((?:TDWP|Poker)_[A-Za-z0-9_]+)\s*(?:::|\()/', $code, $matches );

		$referenced = array_unique( $matches[1] );
		$this->assertNotEmpty( $referenced, 'Expected the rollup to reference collaborators.' );

		$missing = array();
		foreach ( $referenced as $class ) {
			if ( ! isset( $available[ $class ] ) ) {
				$missing[] = $class;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			'The statistics rollup references classes that are not loaded when the '
			. 'Tournament Manager module is disabled, which would break .tdt import: '
			. implode( ', ', $missing )
		);
	}

	/**
	 * The whole point of the gate is a materially smaller load. Assert a real,
	 * conservative reduction so a future refactor that quietly re-links the
	 * subsystem is caught.
	 */
	public function test_disabling_materially_reduces_the_load(): void {
		$off_files = $this->sim->reachable_files( false );
		$on_files  = $this->sim->reachable_files( true );

		$off_bytes = $this->sim->total_bytes( $off_files );
		$on_bytes  = $this->sim->total_bytes( $on_files );

		$this->assertLessThan(
			count( $on_files ),
			count( $off_files ),
			'Disabling the module must load strictly fewer files.'
		);

		$saved_kb = ( $on_bytes - $off_bytes ) / 1024;

		$this->assertGreaterThan(
			700,
			$saved_kb,
			sprintf(
				'Expected the gate to avoid >700 KB of PHP; measured %d KB (%d files vs %d).',
				$saved_kb,
				count( $off_files ),
				count( $on_files )
			)
		);
	}
}
