<?php
/**
 * Excel (.xlsx) Exporter (tdwp-871.27)
 *
 * Exports tournament results to a spreadsheet via PhpSpreadsheet. The row
 * builder is pure (unit-testable); the binary generation is guarded by
 * class_exists so the feature degrades gracefully when the library is not
 * installed (the repo gitignores vendor/ — run `composer install`).
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates .xlsx exports of tournament results.
 */
class TDWP_Excel_Exporter {

	/**
	 * Neutralise spreadsheet formula injection (pure).
	 *
	 * A cell value beginning with =, +, -, @, or a control char can execute as
	 * a formula when the file is opened. Prefix such values with an apostrophe
	 * so they are treated as literal text.
	 *
	 * @param string $value Cell text.
	 * @return string Safe cell text.
	 */
	public static function escape_formula( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Build the spreadsheet rows from result data (pure; no library).
	 *
	 * @param array $results Rows with position, name, prize.
	 * @return array Two-dimensional array: header row + one row per finisher.
	 */
	public static function build_rows( $results ) {
		$results = (array) $results;
		usort(
			$results,
			static function ( $a, $b ) {
				$pa = ( isset( $a['position'] ) && $a['position'] ) ? (int) $a['position'] : PHP_INT_MAX;
				$pb = ( isset( $b['position'] ) && $b['position'] ) ? (int) $b['position'] : PHP_INT_MAX;
				return $pa <=> $pb;
			}
		);

		$rows = array(
			array(
				__( 'Position', 'poker-tournament-import' ),
				__( 'Player', 'poker-tournament-import' ),
				__( 'Prize', 'poker-tournament-import' ),
			),
		);
		foreach ( $results as $result ) {
			$rows[] = array(
				( isset( $result['position'] ) && $result['position'] ) ? (int) $result['position'] : '',
				self::escape_formula( isset( $result['name'] ) ? $result['name'] : '' ),
				isset( $result['prize'] ) ? (float) $result['prize'] : 0.0,
			);
		}

		return $rows;
	}

	/**
	 * Whether the spreadsheet library is available.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' );
	}

	/**
	 * Generate an .xlsx export of a tournament's results.
	 *
	 * @param int   $tournament_id Tournament post ID.
	 * @param array $options       Unused (interface parity).
	 * @return array|WP_Error Export result (file_path, download_url) or error.
	 */
	public static function generate( $tournament_id, $options = array() ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'export_unavailable', __( 'Excel export requires the PhpSpreadsheet library (run composer install).', 'poker-tournament-import' ) );
		}

		$tournament_id = absint( $tournament_id );
		$post          = get_post( $tournament_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found', 'poker-tournament-import' ) );
		}

		$results = class_exists( 'TDWP_Results_Emailer' ) ? TDWP_Results_Emailer::get_results( $tournament_id ) : array();
		$rows    = self::build_rows( $results );

		try {
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet       = $spreadsheet->getActiveSheet();
			$sheet->setTitle( 'Results' );
			$sheet->fromArray( $rows, null, 'A1' );
			$sheet->getStyle( 'A1:C1' )->getFont()->setBold( true );
			foreach ( array( 'A', 'B', 'C' ) as $col ) {
				$sheet->getColumnDimension( $col )->setAutoSize( true );
			}

			$upload = wp_upload_dir();
			$dir    = $upload['basedir'] . '/tdwp-exports';
			wp_mkdir_p( $dir );
			$filename  = 'tournament-' . $tournament_id . '.xlsx';
			$file_path = $dir . '/' . $filename;

			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
			$writer->save( $file_path );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'export_failed', $e->getMessage() );
		}

		if ( class_exists( 'TDWP_Export_Manager' ) ) {
			TDWP_Export_Manager::schedule_cleanup( $file_path );
		}

		return array(
			'success'      => true,
			'file_path'    => $file_path,
			'download_url' => $upload['baseurl'] . '/tdwp-exports/' . $filename,
			'filename'     => $filename,
			'row_count'    => count( $rows ) - 1,
		);
	}
}
