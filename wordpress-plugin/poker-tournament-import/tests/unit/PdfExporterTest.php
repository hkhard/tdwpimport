<?php
/**
 * Unit tests for TDWP_PDF_Exporter::build_html() (pure; no library).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-pdf-exporter.php';

/**
 * @covers TDWP_PDF_Exporter::build_html
 */
class PdfExporterTest extends TestCase {

	private function results() {
		return array(
			array(
				'position' => 2,
				'name'     => 'Bob',
				'prize'    => 250,
			),
			array(
				'position' => 1,
				'name'     => 'Alice',
				'prize'    => 500,
			),
		);
	}

	public function test_html_has_title_and_headers() {
		$html = TDWP_PDF_Exporter::build_html( 'Friday Deepstack', array() );
		$this->assertStringContainsString( 'Friday Deepstack', $html );
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'Player', $html );
	}

	public function test_rows_ordered_by_position() {
		$html  = TDWP_PDF_Exporter::build_html( 'X', $this->results() );
		$posA  = strpos( $html, 'Alice' );
		$posB  = strpos( $html, 'Bob' );
		$this->assertNotFalse( $posA );
		$this->assertLessThan( $posB, $posA );
	}

	public function test_names_are_html_escaped() {
		$html = TDWP_PDF_Exporter::build_html(
			'X',
			array(
				array(
					'position' => 1,
					'name'     => '<script>alert(1)</script>',
					'prize'    => 0,
				),
			)
		);
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_empty_name_does_not_break() {
		$html = TDWP_PDF_Exporter::build_html( 'X', array( array( 'position' => 1 ) ) );
		$this->assertStringContainsString( '<tbody>', $html );
	}
}
