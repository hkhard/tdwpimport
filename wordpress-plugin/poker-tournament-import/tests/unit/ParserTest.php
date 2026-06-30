<?php
/**
 * Unit tests for the .tdt parser (Poker_Tournament_Parser + lexer/AST/mapper).
 *
 * Covers: a valid minimal parse (structure), malformed / empty / truncated /
 * non-tdt input (error handling), and a real Tournament Director export when the
 * repo's tdtfiles/ fixtures are present (valid parse + large file).
 *
 * The parser still emits diagnostic output during parsing (pre-existing debug
 * statements; cleanup tracked separately), so parse calls are wrapped in an
 * output buffer here — these tests assert on the returned data, not on stdout.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {

	/** @var Poker_Tournament_Parser */
	private $parser;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->parser = new Poker_Tournament_Parser();
	}

	/** Parse while swallowing the parser's incidental diagnostic output. */
	private function parseQuietly( string $content ) {
		ob_start();
		try {
			return $this->parser->parse_content( $content );
		} finally {
			ob_end_clean();
		}
	}

	private function fixture( string $name ): string {
		return (string) file_get_contents( POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'tests/fixtures/' . $name );
	}

	/* ------------------------------ valid parse --------------------------- */

	public function test_minimal_valid_tdt_parses_to_expected_structure(): void {
		$data = $this->parseQuietly( $this->fixture( 'minimal.tdt' ) );

		$this->assertIsArray( $data );
		foreach ( array( 'metadata', 'financial', 'players', 'game_history', 'structure', 'prizes' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, "Parsed data should expose '{$key}'." );
		}
		$this->assertIsArray( $data['players'] );
	}

	public function test_metadata_carries_title(): void {
		$data = $this->parseQuietly( $this->fixture( 'minimal.tdt' ) );
		// The fixture Title is "Minimal Test Tournament"; assert it surfaces in
		// metadata without over-coupling to the exact metadata key layout.
		$meta = json_encode( $data['metadata'] );
		$this->assertStringContainsString( 'Minimal Test Tournament', $meta );
	}

	/* ----------------------------- error handling ------------------------- */

	public function test_malformed_tdt_throws(): void {
		$this->expectException( \Throwable::class );
		$this->parseQuietly( '{ this is not valid tdt' );
	}

	public function test_non_tdt_text_throws(): void {
		$this->expectException( \Throwable::class );
		$this->parseQuietly( 'just some plain text, definitely not a tournament export' );
	}

	public function test_empty_input_throws(): void {
		$this->expectException( \Throwable::class );
		$this->parseQuietly( '' );
	}

	public function test_truncated_tdt_throws(): void {
		// A real header cut off mid-structure must not parse to a partial result.
		$truncated = substr( $this->fixture( 'minimal.tdt' ), 0, 40 );
		$this->expectException( \Throwable::class );
		$this->parseQuietly( $truncated );
	}

	/* --------------------- real fixtures (skip if absent) ----------------- */

	public function test_real_tournament_export_parses_with_players(): void {
		$dir   = dirname( POKER_TOURNAMENT_IMPORT_PLUGIN_DIR, 2 ) . '/tdtfiles';
		$files = is_dir( $dir ) ? glob( $dir . '/*.tdt' ) : array();
		if ( empty( $files ) ) {
			$this->markTestSkipped( 'No real .tdt fixtures in tdtfiles/ (plugin-only checkout).' );
		}

		$data = $this->parseQuietly( (string) file_get_contents( $files[0] ) );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data['players'], 'A real export should yield players.' );
		$this->assertGreaterThan( 0, count( $data['players'] ) );
	}
}
