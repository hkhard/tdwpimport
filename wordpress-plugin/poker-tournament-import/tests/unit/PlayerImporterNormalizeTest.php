<?php
/**
 * Unit tests for TDWP_Player_Importer::normalize_rows() (Excel import, tdwp-3lg.2).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/class-player-importer.php';

/**
 * @covers TDWP_Player_Importer::normalize_rows
 */
class PlayerImporterNormalizeTest extends TestCase {

	public function test_trims_cells_to_strings() {
		$rows = array( array( '  Alice  ', ' a@x.io ', 123 ) );
		$this->assertSame(
			array( array( 'Alice', 'a@x.io', '123' ) ),
			TDWP_Player_Importer::normalize_rows( $rows )
		);
	}

	public function test_drops_fully_empty_rows() {
		$rows = array(
			array( 'Name', 'Email' ),
			array( null, null ),
			array( '', '   ' ),
			array( 'Bob', 'b@x.io' ),
		);
		$out = TDWP_Player_Importer::normalize_rows( $rows );
		$this->assertCount( 2, $out );
		$this->assertSame( array( 'Name', 'Email' ), $out[0] );
		$this->assertSame( array( 'Bob', 'b@x.io' ), $out[1] );
	}

	public function test_keeps_row_with_one_nonempty_cell() {
		$rows = array( array( '', 'x', null ) );
		$this->assertSame( array( array( '', 'x', '' ) ), TDWP_Player_Importer::normalize_rows( $rows ) );
	}

	public function test_null_cells_become_empty_strings() {
		$rows = array( array( 'A', null, 'C' ) );
		$this->assertSame( array( array( 'A', '', 'C' ) ), TDWP_Player_Importer::normalize_rows( $rows ) );
	}

	public function test_non_array_input_returns_empty() {
		$this->assertSame( array(), TDWP_Player_Importer::normalize_rows( 'nope' ) );
		$this->assertSame( array(), TDWP_Player_Importer::normalize_rows( array() ) );
	}

	public function test_skips_non_array_rows() {
		$rows = array( 'scalar', array( 'ok' ) );
		$this->assertSame( array( array( 'ok' ) ), TDWP_Player_Importer::normalize_rows( $rows ) );
	}
}
