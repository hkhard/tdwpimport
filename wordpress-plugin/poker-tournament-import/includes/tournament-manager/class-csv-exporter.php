<?php
/**
 * CSV Exporter for Tournament Manager
 *
 * Generates CSV tournament results with streaming support
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDWP_CSV_Exporter {

	/**
	 * Generate CSV export
	 *
	 * @since 3.2.0
	 * @param int   $tournament_id Tournament ID.
	 * @param array $options Export options.
	 * @return array|WP_Error Export result
	 */
	public static function generate( $tournament_id, $options = array() ) {
		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return new WP_Error( 'invalid_tournament', __( 'Invalid tournament ID', 'poker-tournament-import' ) );
		}

		// Get tournament data
		global $wpdb;
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';
		$players       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, pm.display_name
				FROM {$players_table} p
				LEFT JOIN {$wpdb->prefix}tdwp_players pm ON p.player_id = pm.id
				WHERE p.tournament_id = %d
				ORDER BY p.finish_position ASC, p.chip_count DESC",
				$tournament_id
			)
		);

		if ( ! $players ) {
			return new WP_Error( 'no_data', __( 'No player data found', 'poker-tournament-import' ) );
		}

		// Create upload directory
		$upload_dir = wp_upload_dir();
		$filename   = sprintf( 'tournament-%d-%s.csv', $tournament_id, time() );
		$file_path  = $upload_dir['basedir'] . '/tdwp-exports/' . $filename;

		wp_mkdir_p( dirname( $file_path ) );

		// Open file for writing
		$handle = fopen( $file_path, 'w' );
		if ( ! $handle ) {
			return new WP_Error( 'file_error', __( 'Could not create export file', 'poker-tournament-import' ) );
		}

		// Write CSV header
		$headers = array(
			'Position',
			'Player Name',
			'Player ID',
			'Status',
			'Chip Count',
			'Prize Amount',
			'Buyin Count',
			'Rebuy Count',
			'Addon Count',
		);
		fputcsv( $handle, $headers );

		// Write player rows
		foreach ( $players as $player ) {
			$row = array(
				$player->finish_position ?: '',
				$player->display_name,
				$player->player_id,
				$player->status,
				$player->chip_count,
				number_format( $player->prize_amount, 2, '.', '' ),
				$player->buyin_count,
				$player->rebuy_count,
				$player->addon_count,
			);
			fputcsv( $handle, $row );
		}

		fclose( $handle );

		// Schedule cleanup
		TDWP_Export_Manager::schedule_cleanup( $file_path );

		return array(
			'success'      => true,
			'file_path'    => $file_path,
			'download_url' => $upload_dir['baseurl'] . '/tdwp-exports/' . $filename,
			'filename'     => $filename,
			'row_count'    => count( $players ),
		);
	}
}
