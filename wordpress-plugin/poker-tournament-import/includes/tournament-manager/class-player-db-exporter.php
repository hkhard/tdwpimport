<?php
/**
 * Player Database Exporter
 *
 * Exports the player DATABASE (profile fields and aggregate statistics) as CSV.
 * This is distinct from the tournament-results export in TDWP_CSV_Exporter: that
 * one exports a single tournament's finishing order, this one exports the roster
 * of player profiles (name, email, phone, notes/bio, avatar, aggregate stats).
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.6.1
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player Database Exporter class
 *
 * @since 3.6.1
 */
class TDWP_Player_DB_Exporter {

	/**
	 * CSV column headers, in output order.
	 *
	 * @since 3.6.1
	 *
	 * @return array List of header labels.
	 */
	public static function get_columns() {
		return array(
			'Name',
			'Email',
			'Phone',
			'Notes',
			'Avatar URL',
			'UUID',
			'Tournaments',
			'Wins',
			'Final Tables',
			'Total Winnings',
			'Average Finish',
		);
	}

	/**
	 * Map a single player record (as returned by TDWP_Player_Manager::get())
	 * to an ordered CSV row matching get_columns().
	 *
	 * @since 3.6.1
	 *
	 * @param array $player Player data with 'meta' and (optionally) 'stats'.
	 * @return array Ordered row values.
	 */
	public static function format_row( $player ) {
		$meta  = isset( $player['meta'] ) && is_array( $player['meta'] ) ? $player['meta'] : array();
		$stats = isset( $player['stats'] ) && is_array( $player['stats'] ) ? $player['stats'] : array();

		return array(
			isset( $player['name'] ) ? $player['name'] : '',
			isset( $meta['email'] ) ? $meta['email'] : '',
			isset( $meta['phone'] ) ? $meta['phone'] : '',
			isset( $player['bio'] ) ? wp_strip_all_tags( $player['bio'] ) : '',
			isset( $meta['avatar_url'] ) ? $meta['avatar_url'] : '',
			isset( $meta['uuid'] ) ? $meta['uuid'] : '',
			isset( $stats['tournaments'] ) ? (int) $stats['tournaments'] : 0,
			isset( $stats['wins'] ) ? (int) $stats['wins'] : 0,
			isset( $stats['final_tables'] ) ? (int) $stats['final_tables'] : 0,
			isset( $stats['total_winnings'] ) ? number_format( (float) $stats['total_winnings'], 2, '.', '' ) : '0.00',
			isset( $stats['average_finish'] ) ? number_format( (float) $stats['average_finish'], 2, '.', '' ) : '0.00',
		);
	}

	/**
	 * Build the full CSV document for the supplied player records.
	 *
	 * Pure string builder (no I/O), so it can be unit-tested offline and reused
	 * by both a streamed download and a file export.
	 *
	 * @since 3.6.1
	 *
	 * @param array $players List of player records (see format_row()).
	 * @return string CSV text.
	 */
	public static function build_csv( $players ) {
		$handle = fopen( 'php://temp', 'r+' );

		fputcsv( $handle, self::get_columns(), ',', '"', '\\' );

		foreach ( $players as $player ) {
			fputcsv( $handle, self::format_row( $player ), ',', '"', '\\' );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * Gather every player (with meta and aggregate stats) for export.
	 *
	 * @since 3.6.1
	 *
	 * @return array List of player records.
	 */
	public static function collect_players() {
		$manager = new TDWP_Player_Manager();
		$players = array();
		$page    = 1;

		do {
			$batch = $manager->get_all(
				array(
					'page'     => $page,
					'per_page' => 100,
					'status'   => array( 'publish', 'draft' ),
				)
			);

			if ( empty( $batch['players'] ) ) {
				break;
			}

			foreach ( $batch['players'] as $summary ) {
				$record = $manager->get( $summary['id'], true, true );
				if ( ! is_wp_error( $record ) ) {
					$players[] = $record;
				}
			}

			$page++;
		} while ( $page <= (int) $batch['total_pages'] );

		return $players;
	}

	/**
	 * Build the complete player-database CSV for all players.
	 *
	 * @since 3.6.1
	 *
	 * @return string CSV text.
	 */
	public static function generate() {
		return self::build_csv( self::collect_players() );
	}
}
