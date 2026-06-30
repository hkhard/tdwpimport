<?php
/**
 * Regression tests for V:3.3 object-argument Map player extraction.
 *
 * Covers the bug where extract_players() bailed on `new Map({"uuid": new
 * GamePlayer({...}), ...})` (TD v3.3 format) because the node is a New/Map
 * constructor with an Object arg, not an Array.  Also confirms the existing
 * Map.from([[key, GamePlayer]]) array form (V:3.7.2) continues to work.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class ParserMapObjectPlayersTest extends TestCase {

	/** @var Poker_Tournament_Parser */
	private $parser;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->parser = new Poker_Tournament_Parser();
	}

	/** Parse while swallowing incidental diagnostic output. */
	private function parseQuietly( string $content ): array {
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

	// ------------------------------------------------------------------
	// V:3.3 — new Map({"uuid": new GamePlayer({...})}) object-arg form
	// ------------------------------------------------------------------

	public function test_v33_map_object_players_are_extracted(): void {
		$data = $this->parseQuietly( $this->fixture( 'v33-map-object-players.tdt' ) );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'players', $data );
		$this->assertCount( 2, $data['players'], 'Both GamePlayer entries should be extracted.' );
	}

	public function test_v33_map_object_player_nicknames(): void {
		$data    = $this->parseQuietly( $this->fixture( 'v33-map-object-players.tdt' ) );
		$players = array_values( $data['players'] );

		$nicknames = array_column( $players, 'nickname' );
		sort( $nicknames );

		$this->assertSame( array( 'Alice', 'Bob' ), $nicknames );
	}

	// ------------------------------------------------------------------
	// V:3.7.2 — Map.from([[key, GamePlayer]]) array form must not regress
	// ------------------------------------------------------------------

	public function test_v372_map_from_players_still_extracted(): void {
		// The existing minimal fixture uses a plain Player array, so build a
		// targeted snippet with the Map.from([[k, v]]) structure.
		$content = '{ V: "3.7.2", T: new Tournament({UUID: "77777777-7777-4777-8777-777777777777", LeagueName: "ORF", SeasonName: "2022", Title: "V3.7.2 Test", Description: "", Players: new GamePlayers({Players: Map.from([["u3", new GamePlayer({UUID: "u3", Name: new PlayerName({Nickname: "Charlie"})})], ["u4", new GamePlayer({UUID: "u4", Name: new PlayerName({Nickname: "Diana"})})]])}), Results: []}) }';

		$data = $this->parseQuietly( $content );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'players', $data );
		$this->assertCount( 2, $data['players'], 'Map.from array form should still extract players.' );

		$nicknames = array_column( array_values( $data['players'] ), 'nickname' );
		sort( $nicknames );
		$this->assertSame( array( 'Charlie', 'Diana' ), $nicknames );
	}
}
