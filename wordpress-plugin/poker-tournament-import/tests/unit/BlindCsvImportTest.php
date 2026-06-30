<?php
/**
 * Unit tests for TDWP_Blind_CSV_Importer (tdwp-cma.14).
 *
 * Exercises parse/validate logic without any database interaction.
 * The importer is a pure class — all paths are exercised offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class BlindCsvImportTest extends TestCase {

	/** @var TDWP_Blind_CSV_Importer */
	private $importer;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->importer = new TDWP_Blind_CSV_Importer();
	}

	// ------------------------------------------------------------------
	// Happy-path: well-formed CSV rows are accepted
	// ------------------------------------------------------------------

	public function test_valid_rows_are_accepted(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break,break_duration_minutes\n"
			. "25,50,0,20,0,\n"
			. "50,100,10,20,0,\n";

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 2, $result['valid'] );
		$this->assertSame( 0, $result['invalid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_break_row_is_accepted(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break,break_duration_minutes\n"
			. ",,,15,yes,15\n";

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 1, $result['valid'] );
		$row = $result['rows'][0];
		$this->assertSame( 1, $row['is_break'] );
	}

	public function test_is_break_truthy_variants_accepted(): void {
		foreach ( array( '1', 'true', 'yes', 'y' ) as $truthy ) {
			$csv    = "small_blind,big_blind,ante,duration_minutes,is_break\n"
				. ",,,15,$truthy\n";  // duration=15 (valid), is_break=$truthy, SB/BB not required for breaks.
			$result = $this->importer->parse_csv_content( $csv );
			$this->assertSame( 1, $result['valid'], "Expected truthy is_break for value: $truthy" );
		}
	}

	// ------------------------------------------------------------------
	// Canonical field names must be recognised
	// ------------------------------------------------------------------

	public function test_canonical_column_names_recognised(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break,break_duration_minutes\n"
			. "100,200,25,20,0,0\n";

		$result = $this->importer->parse_csv_content( $csv );
		$this->assertSame( 1, $result['valid'] );

		$row = $result['rows'][0];
		$this->assertArrayHasKey( 'small_blind', $row );
		$this->assertArrayHasKey( 'big_blind', $row );
		$this->assertArrayHasKey( 'ante', $row );
		$this->assertArrayHasKey( 'duration_minutes', $row );
		$this->assertArrayHasKey( 'is_break', $row );
		$this->assertArrayHasKey( 'break_duration_minutes', $row );
	}

	public function test_column_aliases_recognised(): void {
		// 'sb', 'bb', 'duration', 'break' are recognised aliases.
		$csv = "sb,bb,ante,duration,break\n"
			. "500,1000,0,20,0\n";

		$result = $this->importer->parse_csv_content( $csv );
		$this->assertSame( 1, $result['valid'] );
	}

	// ------------------------------------------------------------------
	// Validation errors carry correct row numbers
	// ------------------------------------------------------------------

	public function test_missing_duration_is_rejected_with_row_number(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. "25,50,0,,0\n";   // empty duration — row 2

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 0, $result['valid'] );
		$this->assertSame( 1, $result['invalid'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 2, $result['errors'][0]['row'] );
		$this->assertStringContainsString( 'duration_minutes', $result['errors'][0]['message'] );
	}

	public function test_missing_small_blind_on_non_break_row_is_rejected(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. ",200,0,20,0\n";  // missing small_blind — row 2

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 1, $result['invalid'] );
		$this->assertSame( 2, $result['errors'][0]['row'] );
		$this->assertStringContainsString( 'small_blind', $result['errors'][0]['message'] );
	}

	public function test_missing_big_blind_on_non_break_row_is_rejected(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. "100,,0,20,0\n";

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 1, $result['invalid'] );
		$this->assertStringContainsString( 'big_blind', $result['errors'][0]['message'] );
	}

	public function test_invalid_ante_string_is_rejected(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. "100,200,bad,20,0\n";

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 1, $result['invalid'] );
		$this->assertStringContainsString( 'ante', $result['errors'][0]['message'] );
	}

	public function test_row_numbers_increment_correctly_across_multiple_bad_rows(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. "100,200,0,20,0\n"   // row 2 — valid
			. ",200,0,20,0\n"      // row 3 — invalid (no SB)
			. "200,400,0,,0\n"     // row 4 — invalid (no duration)
			. "300,600,0,20,0\n";  // row 5 — valid

		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 2, $result['valid'] );
		$this->assertSame( 2, $result['invalid'] );
		$row_numbers = array_column( $result['errors'], 'row' );
		$this->assertContains( 3, $row_numbers );
		$this->assertContains( 4, $row_numbers );
	}

	// ------------------------------------------------------------------
	// Empty / degenerate input
	// ------------------------------------------------------------------

	public function test_empty_content_returns_zero_rows(): void {
		$result = $this->importer->parse_csv_content( '' );

		$this->assertSame( 0, $result['valid'] );
		$this->assertSame( 0, $result['invalid'] );
		$this->assertEmpty( $result['rows'] );
	}

	public function test_header_only_returns_zero_rows(): void {
		$csv    = "small_blind,big_blind,ante,duration_minutes,is_break\n";
		$result = $this->importer->parse_csv_content( $csv );

		$this->assertSame( 0, $result['valid'] );
	}

	// ------------------------------------------------------------------
	// normalize_rows: integer casting and level_order assignment
	// ------------------------------------------------------------------

	public function test_normalize_rows_casts_to_integers(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break,break_duration_minutes\n"
			. "100,200,25,20,0,0\n"
			. "200,400,50,20,0,0\n";

		$parsed = $this->importer->parse_csv_content( $csv );
		$norm   = $this->importer->normalize_rows( $parsed['rows'] );

		$this->assertIsInt( $norm[0]['small_blind'] );
		$this->assertIsInt( $norm[0]['duration_minutes'] );
		$this->assertSame( 1, $norm[0]['level_order'] );
		$this->assertSame( 2, $norm[1]['level_order'] );
	}

	public function test_normalize_rows_defaults_empty_ante_to_zero(): void {
		$csv = "small_blind,big_blind,ante,duration_minutes,is_break\n"
			. "100,200,,20,0\n";

		$parsed = $this->importer->parse_csv_content( $csv );
		$norm   = $this->importer->normalize_rows( $parsed['rows'] );

		$this->assertSame( 0, $norm[0]['ante'] );
	}

	// ------------------------------------------------------------------
	// validate_row is public and callable standalone
	// ------------------------------------------------------------------

	public function test_validate_row_returns_true_for_valid_level(): void {
		$data = array(
			'small_blind'            => '100',
			'big_blind'              => '200',
			'ante'                   => '0',
			'duration_minutes'       => '20',
			'is_break'               => 0,
			'break_duration_minutes' => '',
		);

		$result = $this->importer->validate_row( $data, 2 );
		$this->assertTrue( $result );
	}

	public function test_validate_row_returns_wp_error_for_bad_duration(): void {
		$data = array(
			'small_blind'            => '100',
			'big_blind'              => '200',
			'ante'                   => '',
			'duration_minutes'       => '0',
			'is_break'               => 0,
			'break_duration_minutes' => '',
		);

		$result = $this->importer->validate_row( $data, 5 );
		$this->assertInstanceOf( WP_Error::class, $result );
	}
}
