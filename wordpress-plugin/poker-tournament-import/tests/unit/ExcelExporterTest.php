<?php
/**
 * Unit tests for TDWP_Excel_Exporter::build_rows() (pure; no library).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-excel-exporter.php';

/**
 * @covers TDWP_Excel_Exporter::build_rows
 */
class ExcelExporterTest extends TestCase {

	private function results() {
		return array(
			array(
				'position' => 3,
				'name'     => 'Carol',
				'prize'    => 0,
			),
			array(
				'position' => 1,
				'name'     => 'Alice',
				'prize'    => 500.5,
			),
			array(
				'position' => 2,
				'name'     => 'Bob',
				'prize'    => 250,
			),
		);
	}

	public function test_header_row_present() {
		$rows = TDWP_Excel_Exporter::build_rows( array() );
		$this->assertCount( 1, $rows );
		$this->assertCount( 3, $rows[0] );
	}

	public function test_rows_ordered_by_position() {
		$rows = TDWP_Excel_Exporter::build_rows( $this->results() );
		// Row 0 is the header; data rows follow in finishing order.
		$this->assertSame( 'Alice', $rows[1][1] );
		$this->assertSame( 'Bob', $rows[2][1] );
		$this->assertSame( 'Carol', $rows[3][1] );
		$this->assertSame( 1, $rows[1][0] );
		$this->assertSame( 500.5, $rows[1][2] );
	}

	public function test_row_count_matches_results() {
		$rows = TDWP_Excel_Exporter::build_rows( $this->results() );
		$this->assertCount( 4, $rows ); // 1 header + 3 players.
	}

	public function test_missing_position_sorts_last_and_blank() {
		$rows = TDWP_Excel_Exporter::build_rows(
			array(
				array(
					'name'  => 'NoPos',
					'prize' => 0,
				),
				array(
					'position' => 1,
					'name'     => 'Winner',
					'prize'    => 100,
				),
			)
		);
		$this->assertSame( 'Winner', $rows[1][1] );
		$this->assertSame( 'NoPos', $rows[2][1] );
		$this->assertSame( '', $rows[2][0] );
	}
}
