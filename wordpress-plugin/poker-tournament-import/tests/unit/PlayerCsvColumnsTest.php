<?php
/**
 * Unit tests for the player CSV importer column handling (tdwp-cma.7).
 *
 * Verifies the importer recognises and imports the 'Buy-in Status' and 'Seat
 * Number' columns in addition to the previously supported name/email/phone/uuid,
 * and that the previously supported columns keep working.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerCsvColumnsTest extends TestCase {

	/** @var string */
	private $tmp_file = '';

	protected function setUp(): void {
		tdwp_test_reset();
	}

	protected function tearDown(): void {
		if ( $this->tmp_file && file_exists( $this->tmp_file ) ) {
			unlink( $this->tmp_file );
		}
	}

	private function writeCsv( string $contents ): string {
		$this->tmp_file = tempnam( sys_get_temp_dir(), 'tdwp_csv_' );
		file_put_contents( $this->tmp_file, $contents );
		return $this->tmp_file;
	}

	public function test_imports_buyin_status_and_seat_number_columns(): void {
		$csv  = "Name,Phone,Buy-in Status,Seat Number\n";
		$csv .= "Alice,555-0001,Paid,7\n";

		$path     = $this->writeCsv( $csv );
		$importer = new TDWP_Player_Importer();
		$result   = $importer->parse_file( $path, 'players.csv' );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['valid'] );

		$player = $result['players'][0];
		$this->assertSame( 'Alice', $player['name'] );
		$this->assertSame( '555-0001', $player['phone'] );
		$this->assertSame( 'Paid', $player['buyin_status'] );
		$this->assertSame( '7', $player['seat_number'] );
	}

	public function test_seat_alias_column_is_recognised(): void {
		$csv  = "Name,Seat,Buyin\n";
		$csv .= "Bob,12,Unpaid\n";

		$path     = $this->writeCsv( $csv );
		$importer = new TDWP_Player_Importer();
		$result   = $importer->parse_file( $path, 'players.csv' );

		$player = $result['players'][0];
		$this->assertSame( '12', $player['seat_number'] );
		$this->assertSame( 'Unpaid', $player['buyin_status'] );
	}

	public function test_prior_columns_still_work_without_new_columns(): void {
		$csv  = "Name,Email,Phone\n";
		$csv .= "Carol,carol@example.com,555-9999\n";

		$path     = $this->writeCsv( $csv );
		$importer = new TDWP_Player_Importer();
		$result   = $importer->parse_file( $path, 'players.csv' );

		$player = $result['players'][0];
		$this->assertSame( 'Carol', $player['name'] );
		$this->assertSame( 'carol@example.com', $player['email'] );
		$this->assertSame( '555-9999', $player['phone'] );
		$this->assertArrayNotHasKey( 'buyin_status', $player );
		$this->assertArrayNotHasKey( 'seat_number', $player );
	}
}
