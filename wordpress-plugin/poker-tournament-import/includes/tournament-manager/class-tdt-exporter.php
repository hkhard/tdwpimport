<?php
/**
 * TDT Exporter for Tournament Manager
 *
 * Exports tournaments in Tournament Director 3 (.tdt) format
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDWP_TDT_Exporter {

	/**
	 * Generate TDT export
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

		global $wpdb;

		// Get tournament metadata
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$live_state       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$live_state_table} WHERE tournament_id = %d", $tournament_id )
		);

		if ( ! $live_state ) {
			return new WP_Error( 'no_tournament', __( 'Tournament not found', 'poker-tournament-import' ) );
		}

		// Get player data
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';
		$players       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, pm.display_name, pm.email
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
		$filename   = sprintf( 'tournament-%d-%s.tdt', $tournament_id, time() );
		$file_path  = $upload_dir['basedir'] . '/tdwp-exports/' . $filename;

		wp_mkdir_p( dirname( $file_path ) );

		// Build TDT XML structure
		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><tournament></tournament>' );

		// Tournament metadata
		$info = $xml->addChild( 'info' );
		$info->addChild( 'name', htmlspecialchars( get_the_title( $tournament_id ), ENT_XML1, 'UTF-8' ) );
		$info->addChild( 'date', $live_state->started_at ?: current_time( 'mysql' ) );
		$info->addChild( 'status', $live_state->status );
		$info->addChild( 'buyin', $live_state->buyin_amount );
		$info->addChild( 'prizePool', $live_state->prize_pool );
		$info->addChild( 'totalPlayers', $live_state->total_players );
		$info->addChild( 'remainingPlayers', $live_state->remaining_players );

		// Players section
		$players_node = $xml->addChild( 'players' );
		foreach ( $players as $player ) {
			$player_node = $players_node->addChild( 'player' );
			$player_node->addChild( 'id', $player->player_id );
			$player_node->addChild( 'name', htmlspecialchars( $player->display_name, ENT_XML1, 'UTF-8' ) );
			$player_node->addChild( 'email', htmlspecialchars( $player->email ?: '', ENT_XML1, 'UTF-8' ) );
			$player_node->addChild( 'status', $player->status );
			$player_node->addChild( 'chipCount', $player->chip_count );
			$player_node->addChild( 'finishPosition', $player->finish_position ?: '' );
			$player_node->addChild( 'prizeAmount', number_format( $player->prize_amount, 2, '.', '' ) );
			$player_node->addChild( 'buyinCount', $player->buyin_count );
			$player_node->addChild( 'rebuyCount', $player->rebuy_count );
			$player_node->addChild( 'addonCount', $player->addon_count );
			$player_node->addChild( 'tableNumber', $player->table_number ?: '' );
			$player_node->addChild( 'seatNumber', $player->seat_number ?: '' );
		}

		// Write to file with pretty formatting
		$dom                     = new DOMDocument( '1.0', 'UTF-8' );
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = true;
		$dom->loadXML( $xml->asXML() );
		$dom->save( $file_path );

		// Schedule cleanup
		TDWP_Export_Manager::schedule_cleanup( $file_path );

		return array(
			'success'      => true,
			'file_path'    => $file_path,
			'download_url' => $upload_dir['baseurl'] . '/tdwp-exports/' . $filename,
			'filename'     => $filename,
			'player_count' => count( $players ),
		);
	}
}
