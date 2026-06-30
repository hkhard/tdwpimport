<?php
/**
 * PDF Exporter (tdwp-871.26)
 *
 * Exports tournament results to a formatted PDF via dompdf. The HTML builder is
 * pure (unit-testable) and escapes all dynamic values; the binary generation is
 * guarded by class_exists so the feature degrades gracefully when the library
 * is not installed (the repo gitignores vendor/ — run `composer install`).
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates PDF exports of tournament results.
 */
class TDWP_PDF_Exporter {

	/**
	 * Build the results HTML for the PDF (pure; no library).
	 *
	 * All dynamic values are escaped with esc_html, which also neutralises any
	 * markup/formula-style content in player names.
	 *
	 * @param string $tournament_name Tournament name.
	 * @param array  $results         Rows with position, name, prize.
	 * @return string HTML document.
	 */
	public static function build_html( $tournament_name, $results ) {
		$results = (array) $results;
		usort(
			$results,
			static function ( $a, $b ) {
				$pa = ( isset( $a['position'] ) && $a['position'] ) ? (int) $a['position'] : PHP_INT_MAX;
				$pb = ( isset( $b['position'] ) && $b['position'] ) ? (int) $b['position'] : PHP_INT_MAX;
				return $pa <=> $pb;
			}
		);

		$rows_html = '';
		foreach ( $results as $result ) {
			$position = ( isset( $result['position'] ) && $result['position'] ) ? (int) $result['position'] : '';
			$name     = isset( $result['name'] ) ? $result['name'] : '';
			$prize    = isset( $result['prize'] ) ? (float) $result['prize'] : 0.0;

			$rows_html .= '<tr>'
				. '<td class="pos">' . esc_html( (string) $position ) . '</td>'
				. '<td>' . esc_html( $name ) . '</td>'
				. '<td class="prize">' . esc_html( number_format( $prize, 2 ) ) . '</td>'
				. '</tr>';
		}

		$title = esc_html( '' !== trim( (string) $tournament_name ) ? $tournament_name : __( 'Tournament', 'poker-tournament-import' ) );

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
			. 'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222;}'
			. 'h1{font-size:18px;margin:0 0 12px;}'
			. 'table{width:100%;border-collapse:collapse;}'
			. 'th,td{border:1px solid #999;padding:6px 8px;text-align:left;}'
			. 'th{background:#eee;}'
			. 'td.pos{width:60px;}td.prize{text-align:right;width:120px;}'
			. '</style></head><body>'
			. '<h1>' . sprintf( /* translators: %s: tournament name */ esc_html__( 'Results: %s', 'poker-tournament-import' ), $title ) . '</h1>'
			. '<table><thead><tr>'
			. '<th>' . esc_html__( 'Position', 'poker-tournament-import' ) . '</th>'
			. '<th>' . esc_html__( 'Player', 'poker-tournament-import' ) . '</th>'
			. '<th>' . esc_html__( 'Prize', 'poker-tournament-import' ) . '</th>'
			. '</tr></thead><tbody>' . $rows_html . '</tbody></table>'
			. '</body></html>';
	}

	/**
	 * Whether the PDF library is available.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\Dompdf\Dompdf' );
	}

	/**
	 * Generate a PDF export of a tournament's results.
	 *
	 * @param int   $tournament_id Tournament post ID.
	 * @param array $options       Unused (interface parity).
	 * @return array|WP_Error Export result (file_path, download_url) or error.
	 */
	public static function generate( $tournament_id, $options = array() ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'export_unavailable', __( 'PDF export requires the dompdf library (run composer install).', 'poker-tournament-import' ) );
		}

		$tournament_id = absint( $tournament_id );
		$post          = get_post( $tournament_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found', 'poker-tournament-import' ) );
		}

		$results = class_exists( 'TDWP_Results_Emailer' ) ? TDWP_Results_Emailer::get_results( $tournament_id ) : array();
		$html    = self::build_html( $post->post_title, $results );

		try {
			$dompdf = new \Dompdf\Dompdf();
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$output = $dompdf->output();

			$upload = wp_upload_dir();
			$dir    = $upload['basedir'] . '/tdwp-exports';
			wp_mkdir_p( $dir );
			$filename  = 'tournament-' . $tournament_id . '.pdf';
			$file_path = $dir . '/' . $filename;

			if ( false === file_put_contents( $file_path, $output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new WP_Error( 'export_failed', __( 'Could not write the PDF file', 'poker-tournament-import' ) );
			}
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
		);
	}
}
