<?php
/**
 * Unit tests: encoding normalisation in Poker_Tournament_Parser.
 *
 * Tournament Director .tdt files saved on Windows (pre-2020 / V3.3 era) are
 * Windows-1252 / Latin-1, not UTF-8. The parser must convert them to UTF-8
 * before lexing so that Swedish names like "Håkan" survive import instead of
 * being corrupted to an empty string.
 *
 * Two assertions:
 *   1. Latin-1 input  → nickname decoded to valid UTF-8 ("Håkan").
 *   2. UTF-8 input    → nickname passes through unchanged (no double-encode).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class ParserEncodingTest extends TestCase {

	/** @var Poker_Tournament_Parser */
	private $parser;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->parser = new Poker_Tournament_Parser();
	}

	/** Suppress parser's incidental diagnostic output, return the parsed data. */
	private function parseQuietly( string $content ): array {
		ob_start();
		try {
			return $this->parser->parse_content( $content );
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Build a minimal V3.3-style .tdt snippet with one player whose Nickname
	 * contains the supplied raw bytes.
	 */
	private function make_tdt( string $nickname_bytes ): string {
		// Use the V3.3 Map-of-GamePlayer format (confirmed in v33 fixture).
		return '{ V: "3.3", T: new Tournament({UUID: "33333333-3333-4333-8333-333333333333",'
			. ' LeagueName: "ORF", SeasonName: "2015",'
			. ' Title: "Encoding Test",'
			. ' Description: "",'
			. ' Players: new GamePlayers({Players: new Map({"u1": new GamePlayer({'
			. 'UUID: "u1", Name: new PlayerName({Nickname: "' . $nickname_bytes . '"})})})}),'
			. ' Results: []}) }';
	}

	// -----------------------------------------------------------------------
	// Latin-1 / Windows-1252 input → UTF-8 output
	// -----------------------------------------------------------------------

	public function test_latin1_nickname_is_decoded_to_utf8(): void {
		// "H\xe5kan" in Latin-1/Windows-1252 — 0xE5 is å.
		$latin1_tdt = $this->make_tdt( 'H' . chr( 0xE5 ) . 'kan' );

		$data = $this->parseQuietly( $latin1_tdt );

		// Players are keyed by UUID — use array_column to extract nicknames.
		$nicknames = array_column( array_values( $data['players'] ), 'nickname' );

		$this->assertCount( 1, $nicknames, 'Expected exactly one player.' );
		$this->assertSame(
			"H\u{00E5}kan",
			$nicknames[0],
			'Latin-1 byte 0xE5 (å) must be decoded to the correct UTF-8 character.'
		);
		$this->assertTrue(
			mb_check_encoding( $nicknames[0], 'UTF-8' ),
			'Decoded nickname must be valid UTF-8.'
		);
	}

	public function test_latin1_with_multiple_swedish_chars(): void {
		// "Åsa Öberg" — 0xC5=Å, 0xD6=Ö in Windows-1252.
		$latin1_tdt = $this->make_tdt( chr( 0xC5 ) . 'sa ' . chr( 0xD6 ) . 'berg' );

		$data = $this->parseQuietly( $latin1_tdt );

		$nicknames = array_column( array_values( $data['players'] ), 'nickname' );

		$this->assertSame(
			"\u{00C5}sa \u{00D6}berg",
			$nicknames[0],
			'Windows-1252 bytes for Å (0xC5) and Ö (0xD6) must decode correctly.'
		);
		$this->assertTrue( mb_check_encoding( $nicknames[0], 'UTF-8' ) );
	}

	// -----------------------------------------------------------------------
	// UTF-8 input → passes through unchanged (no double-encode)
	// -----------------------------------------------------------------------

	public function test_utf8_nickname_is_not_double_encoded(): void {
		// Already valid UTF-8 — must not be mojibaked.
		$utf8_tdt = $this->make_tdt( "H\u{00E5}kan" );

		$data = $this->parseQuietly( $utf8_tdt );

		$nicknames = array_column( array_values( $data['players'] ), 'nickname' );

		$this->assertSame(
			"H\u{00E5}kan",
			$nicknames[0],
			'A UTF-8 nickname must not be double-converted.'
		);
		$this->assertTrue( mb_check_encoding( $nicknames[0], 'UTF-8' ) );
	}
}
