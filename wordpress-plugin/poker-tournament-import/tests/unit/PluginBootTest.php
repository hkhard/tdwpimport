<?php
/**
 * End-to-end boot tests: run the real plugin entry point, not just its classes.
 *
 * The other gate tests are static (they walk require statements). These execute
 * `Poker_Tournament_Import::init()` in a subprocess against a WordPress shim and
 * assert on observable outcomes — no fatal, which shortcodes and post types got
 * registered, and how much memory and error-log output the boot produced.
 *
 * Running in a subprocess is deliberate: a fatal in the plugin must be reported
 * as a test failure rather than killing the test runner, and each boot needs a
 * clean class table.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PluginBootTest extends TestCase {

	/** @var string */
	private $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__, 2 );

		if ( ! is_file( $this->root . '/tests/tools/boot-harness.php' ) ) {
			$this->markTestSkipped( 'Boot harness not present.' );
		}
	}

	/**
	 * Boot the plugin in a subprocess and return the harness report.
	 */
	private function boot( bool $tm_enabled, string $context = 'admin' ): array {
		$command = sprintf(
			'BOOT_DUMP_LOG=1 %s %s %s %s %s %s 2>/dev/null',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $this->root . '/tests/tools/boot-harness.php' ),
			escapeshellarg( $this->root ),
			escapeshellarg( $this->root . '/tests/stubs/wp-stubs.php' ),
			$tm_enabled ? 'on' : 'off',
			escapeshellarg( $context )
		);

		$output = shell_exec( $command );
		$this->assertNotEmpty( $output, 'Boot harness produced no output.' );

		$report = json_decode( trim( (string) $output ), true );
		$this->assertIsArray( $report, 'Boot harness output was not valid JSON: ' . $output );

		return $report;
	}

	/**
	 * @return array<string,array{bool,string}>
	 */
	public static function bootMatrix(): array {
		return array(
			'module off, admin'      => array( false, 'admin' ),
			'module off, front end'  => array( false, 'front' ),
			'module on, admin'       => array( true, 'admin' ),
			'module on, front end'   => array( true, 'front' ),
		);
	}

	/**
	 * The plugin must boot without a fatal in every combination of the gate and
	 * request context. This is the check that would have caught the
	 * create_tables() -> TD3_Migration -> TD3_Database_Schema crash.
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'bootMatrix' )]
	public function test_plugin_boots_without_fatal( bool $tm_enabled, string $context ): void {
		$report = $this->boot( $tm_enabled, $context );

		$this->assertNull(
			$report['fatal'],
			sprintf(
				"Plugin fataled during boot (Tournament Manager %s, %s):\n%s",
				$tm_enabled ? 'ON' : 'OFF',
				$context,
				(string) $report['fatal']
			)
		);
	}

	/**
	 * The reported production symptom was ~19 diagnostic lines written to the
	 * error log on every request, the bulk of them from the display subsystem.
	 *
	 * SCOPE, honestly stated: this harness has no real database, so schema
	 * creation short-circuits and the boot does not reach every display code
	 * path. What it does prove is that a boot no longer emits the breadcrumbs it
	 * reaches. The stronger guarantee — that those call sites cannot log at all —
	 * is enforced by DebugTraceGateTest, which asserts zero raw error_log() in
	 * the hot-path files and that trace() writes nothing while disabled. Treat
	 * this test as defence in depth, not as the primary proof.
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'bootMatrix' )]
	public function test_boot_emits_no_diagnostic_log_spam( bool $tm_enabled, string $context ): void {
		$report  = $this->boot( $tm_enabled, $context );
		$excerpt = (string) ( $report['log_excerpt'] ?? '' );

		$noisy = array_values(
			array_filter(
				explode( "\n", $excerpt ),
				static function ( $line ) {
					foreach ( array( 'Display Manager', 'Template Engine', 'Dependency Manager', 'Admin Scripts Hook' ) as $needle ) {
						if ( str_contains( $line, $needle ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);

		$this->assertSame(
			array(),
			$noisy,
			sprintf(
				"These diagnostic lines were written to the error log during a single boot\n"
				. "(Tournament Manager %s, %s). They must be gated behind TDWP_Debug_Logger::trace():\n  %s",
				$tm_enabled ? 'ON' : 'OFF',
				$context,
				implode( "\n  ", $noisy )
			)
		);
	}

	/**
	 * Importing must keep updating the statistics marts with the module off.
	 * TDWP_Stats_Rollup is the sole writer, so it must be loaded and hooked.
	 */
	public function test_import_to_data_mart_path_survives_with_module_off(): void {
		$report = $this->boot( false );

		$this->assertTrue( $report['rollup'], 'TDWP_Stats_Rollup must load with the module off.' );
		$this->assertTrue( $report['stats'], 'Poker_Statistics_Engine must load with the module off.' );
		$this->assertTrue( $report['parser'], 'The .tdt parser must load with the module off.' );

		$this->assertContains(
			'poker_tournament_imported',
			$report['rollup_hooks'],
			'The rollup must still listen for imports, or .tdt files would stop updating statistics.'
		);
	}

	/**
	 * Disabling the module must not remove any statistics-side shortcode. The
	 * live-play and display shortcodes are expected to disappear; these are the
	 * ones documented in the changelog and the settings-page warning.
	 */
	public function test_only_live_shortcodes_are_lost_when_module_disabled(): void {
		$off = $this->boot( false )['shortcode_list'];
		$on  = $this->boot( true )['shortcode_list'];

		$lost = array_values( array_diff( $on, $off ) );
		sort( $lost );

		$this->assertSame(
			array(
				'tdwp_current_blinds',
				'tdwp_leaderboard',
				'tdwp_live_clock',
				'tdwp_player_count',
				'tdwp_prize_pool',
				'tdwp_screen_preview',
				'tdwp_tournament_clock',
				'tdwp_tournament_display',
				'tournament_clock',
			),
			$lost,
			'Only live-play and TD3 display shortcodes should be lost when the module '
			. 'is disabled. Anything else here is a statistics-side regression.'
		);

		// The statistics surface must be completely intact.
		foreach ( array( 'tournament_results', 'player_profile', 'season_standings', 'series_standings' ) as $required ) {
			$this->assertContains(
				$required,
				$off,
				sprintf( '[%s] is statistics-side and must survive the gate.', $required )
			);
		}

		$this->assertGreaterThanOrEqual(
			36,
			count( $off ),
			'The statistics shortcode surface should be essentially unchanged with the module off.'
		);
	}

	/**
	 * The settings page is the surface this release changes most, and it is the
	 * only place the new toggle is exposed. Render it for real: a fatal or a
	 * missing control there would be invisible to a hooks-only boot.
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'gateStates' )]
	public function test_settings_page_renders_with_the_toggle( bool $tm_enabled ): void {
		$report = $this->boot( $tm_enabled );

		$this->assertNull(
			$report['settings_page_error'],
			sprintf(
				'Rendering the settings page failed with the module %s: %s',
				$tm_enabled ? 'ON' : 'OFF',
				(string) $report['settings_page_error']
			)
		);

		$this->assertGreaterThan(
			1000,
			$report['settings_page_bytes'],
			'The settings page rendered suspiciously little markup.'
		);

		$this->assertTrue(
			$report['settings_has_toggle'],
			'The Tournament Manager toggle must be present on the settings page, '
			. 'otherwise the feature cannot be turned back on through the UI.'
		);

		$this->assertTrue(
			$report['settings_has_memory'],
			'The memory readout must render so operators can see their real headroom.'
		);
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function gateStates(): array {
		return array(
			'module off' => array( false ),
			'module on'  => array( true ),
		);
	}

	/**
	 * The live_tournament post type belongs to the module and should not appear
	 * as an orphan admin menu when it is off.
	 */
	public function test_live_tournament_post_type_follows_the_gate(): void {
		$off = $this->boot( false )['post_types'];
		$on  = $this->boot( true )['post_types'];

		$this->assertNotContains( 'live_tournament', $off );
		$this->assertContains( 'live_tournament', $on );

		foreach ( array( 'tournament', 'tournament_series', 'tournament_season', 'player' ) as $required ) {
			$this->assertContains( $required, $off, sprintf( '%s must always be registered.', $required ) );
		}
	}

	/**
	 * The reason the gate exists: a materially lighter request. Compare real peak
	 * memory of the two boots rather than trusting the static estimate.
	 */
	public function test_disabling_the_module_lowers_peak_memory(): void {
		$off = $this->boot( false );
		$on  = $this->boot( true );

		$this->assertLessThan(
			$on['peak_kb'],
			$off['peak_kb'],
			sprintf(
				'Expected a lighter boot with the module off, but measured %d KB off vs %d KB on.',
				$off['peak_kb'],
				$on['peak_kb']
			)
		);

		$this->assertLessThan(
			$on['classes'],
			$off['classes'],
			'Fewer classes should be compiled with the module off.'
		);
	}
}
