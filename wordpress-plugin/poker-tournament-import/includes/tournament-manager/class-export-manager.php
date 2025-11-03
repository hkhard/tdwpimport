<?php
/**
 * Export Manager for Tournament Manager
 *
 * Factory for tournament exports in multiple formats
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDWP_Export_Manager {

	const SUPPORTED_FORMATS = array( 'csv', 'tdt' );

	/**
	 * Export tournament in specified format
	 *
	 * @since 3.2.0
	 * @param int    $tournament_id Tournament ID.
	 * @param string $format Export format (pdf, csv, tdt).
	 * @param array  $options Export options.
	 * @return array|WP_Error Export result with file path or error
	 */
	public static function export( $tournament_id, $format, $options = array() ) {
		$tournament_id = absint( $tournament_id );
		$format        = strtolower( sanitize_text_field( $format ) );

		if ( ! $tournament_id ) {
			return new WP_Error( 'invalid_tournament', __( 'Invalid tournament ID', 'poker-tournament-import' ) );
		}

		if ( ! in_array( $format, self::SUPPORTED_FORMATS, true ) ) {
			return new WP_Error( 'invalid_format', __( 'Unsupported export format', 'poker-tournament-import' ) );
		}

		// Dispatch to format-specific exporter
		switch ( $format ) {
			case 'csv':
				return TDWP_CSV_Exporter::generate( $tournament_id, $options );
			case 'tdt':
				return TDWP_TDT_Exporter::generate( $tournament_id, $options );
		}

		return new WP_Error( 'export_failed', __( 'Export failed', 'poker-tournament-import' ) );
	}

	/**
	 * Schedule export file cleanup
	 *
	 * @since 3.2.0
	 * @param string $file_path File path to clean up.
	 */
	public static function schedule_cleanup( $file_path ) {
		if ( file_exists( $file_path ) ) {
			wp_schedule_single_event( time() + 3600, 'tdwp_cleanup_export_file', array( $file_path ) );
		}
	}
}
