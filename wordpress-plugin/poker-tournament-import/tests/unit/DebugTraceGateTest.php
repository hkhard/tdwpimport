<?php
/**
 * Regression tests for the per-request log-spam fix (3.9.9).
 *
 * Production Apache logs showed ~19 unconditional error_log() writes per request
 * from the TM display subsystem, on every request including anonymous front-end
 * traffic and REST callbacks. These tests pin the behaviour that made that
 * possible so it cannot silently return:
 *
 *   1. TDWP_Debug_Logger::trace() is a no-op unless tracing is explicitly enabled.
 *   2. The display/template/dependency classes contain no unconditional error_log().
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class DebugTraceGateTest extends TestCase {

	/** Files that ran on every request and must stay quiet by default. */
	private const HOT_PATH_FILES = array(
		'includes/tournament-manager/class-display-manager.php',
		'includes/tournament-manager/class-template-engine.php',
		'includes/tournament-manager/class-dependency-manager.php',
	);

	protected function setUp(): void {
		tdwp_test_reset();
		$this->resetTraceCache();
	}

	protected function tearDown(): void {
		$this->resetTraceCache();
	}

	/**
	 * The trace-enabled result is memoised per request; clear it between tests.
	 */
	private function resetTraceCache(): void {
		$ref  = new ReflectionClass( TDWP_Debug_Logger::class );
		$prop = $ref->getProperty( 'trace_enabled' );
		$prop->setValue( null, null );
	}

	public function test_trace_is_disabled_by_default(): void {
		$this->assertFalse(
			TDWP_Debug_Logger::trace_enabled(),
			'Tracing must default to OFF; it runs on every request.'
		);
	}

	public function test_trace_enabled_when_option_set(): void {
		update_option( 'tdwp_trace_enabled', true );
		$this->resetTraceCache();

		$this->assertTrue( TDWP_Debug_Logger::trace_enabled() );
	}

	/**
	 * trace() must not write to the log file when tracing is off. This is the
	 * actual regression: the log file must stay untouched.
	 */
	public function test_trace_writes_nothing_when_disabled(): void {
		$log = sys_get_temp_dir() . '/tdwp-trace-gate-test.log';
		@unlink( $log );

		$ref  = new ReflectionClass( TDWP_Debug_Logger::class );
		$file = $ref->getProperty( 'log_file' );
		$file->setValue( null, $log );

		$enabled = $ref->getProperty( 'enabled' );
		$enabled->setValue( null, true );

		// Replay the exact breadcrumbs that flooded the production log.
		for ( $i = 0; $i < 20; $i++ ) {
			TDWP_Debug_Logger::trace( 'DISPLAY', 'Constructor called - Display Manager instance created' );
			TDWP_Debug_Logger::trace( 'TEMPLATE_ENGINE', 'Starting token registry initialization' );
		}

		$this->assertFileDoesNotExist( $log, 'Disabled trace() must not create or write the log file.' );

		// And when explicitly enabled, it must still work.
		update_option( 'tdwp_trace_enabled', true );
		$this->resetTraceCache();
		TDWP_Debug_Logger::trace( 'DISPLAY', 'now visible' );

		$this->assertFileExists( $log );
		$this->assertStringContainsString( 'now visible', (string) file_get_contents( $log ) );

		@unlink( $log );
		$file->setValue( null, null );
	}

	/**
	 * Guard the hot-path files against unconditional error_log() creeping back.
	 */
	public function test_hot_path_files_have_no_unconditional_error_log(): void {
		$root = dirname( __DIR__, 2 ) . '/';

		foreach ( self::HOT_PATH_FILES as $relative ) {
			$path = $root . $relative;
			$this->assertFileExists( $path );

			$source = (string) file_get_contents( $path );
			$count  = preg_match_all( '/(?<![A-Za-z_])error_log\s*\(/', $source );

			$this->assertSame(
				0,
				$count,
				sprintf(
					'%s must route diagnostics through TDWP_Debug_Logger, but has %d raw error_log() call(s). '
					. 'These run on every request and flooded the production log.',
					$relative,
					$count
				)
			);
		}
	}
}
