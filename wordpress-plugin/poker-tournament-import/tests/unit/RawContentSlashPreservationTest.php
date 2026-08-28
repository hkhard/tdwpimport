<?php
/**
 * Regression tests for the 3.9.10 raw-content corruption bug.
 *
 * Versions up to 3.9.9 stored the original .tdt via
 * `update_post_meta( $id, '_tournament_raw_content', $file_content )`.
 * update_post_meta() runs its value through wp_unslash(), which strips one
 * level of backslash escaping. A .tdt escapes quotes and newlines inside its
 * Description and UserFormula Text values, so the stored copy lost those
 * escapes and could no longer be parsed — which in turn made it impossible to
 * recalculate points from it.
 *
 * The damage is lossy and cannot be undone after the fact: a stored `n` may
 * have been either `\n` or a literal `n`. The fix is therefore at the write
 * site (wp_slash before storing), and these tests lock that in.
 *
 * Covers:
 *  - Both write sites pass the content through wp_slash()
 *  - A wp_slash() round-trip through unslashing is byte-identical
 *  - The naive (pre-3.9.10) write loses bytes on every real fixture
 *  - Every real fixture parses pristine but fails after the naive round-trip
 *  - The corruption detector separates damaged copies from intact ones
 *
 * Runs offline — no live DB, no WordPress install.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class RawContentSlashPreservationTest extends TestCase {

	/**
	 * Plugin root directory.
	 *
	 * @return string
	 */
	private function root() {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Real tournament files shipped as fixtures.
	 *
	 * @return string[]
	 */
	private function fixture_files() {
		$files = glob( $this->root() . '/tests/fixtures/turrar/*.tdt' );
		return $files ? $files : array();
	}

	/**
	 * WordPress strips one level of slashes on meta writes. stripslashes() is
	 * exactly what wp_unslash() applies to a string.
	 *
	 * @param string $value Value as passed to update_post_meta().
	 * @return string Value as it would be persisted.
	 */
	private function simulate_meta_write( $value ) {
		return stripslashes( $value );
	}

	// -------------------------------------------------------------------------
	// Write sites
	// -------------------------------------------------------------------------

	/**
	 * The importer must slash the raw content before storing it.
	 */
	public function test_importer_write_site_uses_wp_slash() {
		$src = file_get_contents( $this->root() . '/poker-tournament-import.php' );

		$this->assertStringContainsString(
			"update_post_meta(\$tournament_id, '_tournament_raw_content', wp_slash(\$file_content));",
			$src,
			'The importer must wrap raw content in wp_slash() or the stored copy is corrupted.'
		);
	}

	/**
	 * The admin write site must slash the raw content too.
	 */
	public function test_admin_write_site_uses_wp_slash() {
		$src = file_get_contents( $this->root() . '/admin/class-admin.php' );

		$this->assertStringContainsString(
			"update_post_meta(\$tournament_id, '_tournament_raw_content', wp_slash(\$raw_content));",
			$src,
			'The admin importer must wrap raw content in wp_slash().'
		);
	}

	/**
	 * No unslashed write of the raw content may remain anywhere in the plugin.
	 */
	public function test_no_unslashed_raw_content_writes_remain() {
		$offenders = array();

		$dir = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root(), RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $dir as $file ) {
			if ( 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			if ( false !== strpos( $file->getPathname(), '/tests/' ) ) {
				continue;
			}

			foreach ( file( $file->getPathname() ) as $no => $line ) {
				if ( false === strpos( $line, '_tournament_raw_content' ) ) {
					continue;
				}
				if ( false === strpos( $line, 'update_post_meta' ) ) {
					continue;
				}
				if ( false === strpos( $line, 'wp_slash' ) ) {
					$offenders[] = basename( $file->getPathname() ) . ':' . ( $no + 1 );
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Raw content written without wp_slash() at: ' . implode( ', ', $offenders )
		);
	}

	// -------------------------------------------------------------------------
	// Round-trip behaviour
	// -------------------------------------------------------------------------

	/**
	 * wp_slash() before the write makes the round-trip lossless.
	 */
	public function test_slashed_write_round_trips_byte_identically() {
		foreach ( $this->fixture_files() as $file ) {
			$original = file_get_contents( $file );
			$stored   = $this->simulate_meta_write( addslashes( $original ) );

			$this->assertSame(
				$original,
				$stored,
				'Slashed round-trip must be byte-identical for ' . basename( $file )
			);
		}
	}

	/**
	 * The pre-3.9.10 naive write silently lost bytes on every real file.
	 */
	public function test_naive_write_loses_bytes_on_every_fixture() {
		$files = $this->fixture_files();
		$this->assertNotEmpty( $files, 'Expected .tdt fixtures to be present.' );

		foreach ( $files as $file ) {
			$original = file_get_contents( $file );
			$stored   = $this->simulate_meta_write( $original );

			$this->assertLessThan(
				strlen( $original ),
				strlen( $stored ),
				'The naive write should demonstrably lose bytes for ' . basename( $file )
			);
		}
	}

	/**
	 * Each fixture parses when pristine and fails after the naive round-trip.
	 * This is the defect the recalculator hit in production.
	 */
	public function test_naive_round_trip_makes_every_fixture_unparseable() {
		require_once $this->root() . '/includes/class-tdt-lexer.php';
		require_once $this->root() . '/includes/class-tdt-ast-parser.php';

		foreach ( $this->fixture_files() as $file ) {
			$original = file_get_contents( $file );

			$parser = new TDT_Parser( $original );
			$this->assertNotEmpty(
				$parser->parseDocument(),
				'Pristine fixture must parse: ' . basename( $file )
			);

			$corrupted = $this->simulate_meta_write( $original );

			try {
				$bad = new TDT_Parser( $corrupted );
				$bad->parseDocument();
				$this->fail( 'Corrupted copy unexpectedly parsed: ' . basename( $file ) );
			} catch ( Throwable $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Corruption detector
	// -------------------------------------------------------------------------

	/**
	 * Invoke the recalculator's private detector.
	 *
	 * @param string $raw Raw content.
	 * @return bool
	 */
	private function looks_corrupted( $raw ) {
		require_once $this->root() . '/includes/class-points-recalculator.php';

		$method = new ReflectionMethod( 'Poker_Points_Recalculator', 'looks_slash_corrupted' );

		return (bool) $method->invoke(
			( new ReflectionClass( 'Poker_Points_Recalculator' ) )->newInstanceWithoutConstructor(),
			$raw
		);
	}

	/**
	 * A damaged copy is recognised so the operator gets an actionable message.
	 */
	public function test_detector_flags_naively_stored_content() {
		foreach ( $this->fixture_files() as $file ) {
			$corrupted = $this->simulate_meta_write( file_get_contents( $file ) );

			$this->assertTrue(
				$this->looks_corrupted( $corrupted ),
				'Damaged copy should be detected: ' . basename( $file )
			);
		}
	}

	/**
	 * An intact copy is never flagged.
	 */
	public function test_detector_passes_intact_content() {
		foreach ( $this->fixture_files() as $file ) {
			$this->assertFalse(
				$this->looks_corrupted( file_get_contents( $file ) ),
				'Intact copy must not be flagged: ' . basename( $file )
			);
		}
	}
}
