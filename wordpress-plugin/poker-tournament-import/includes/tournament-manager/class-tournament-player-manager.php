<?php
/**
 * Tournament Player Manager
 *
 * Manages player registrations for tournaments
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tournament Player Manager class
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Player_Manager {

	/**
	 * Add player to tournament
	 *
	 * @since 3.1.0
	 * @param int   $tournament_id Tournament ID.
	 * @param int   $player_id     Player ID.
	 * @param array $args          Additional arguments.
	 * @return int|WP_Error Player registration ID on success, WP_Error on failure.
	 */
	public static function add_player( $tournament_id, $player_id, $args = array() ) {
		global $wpdb;

		// Validate tournament exists.
		if ( ! get_post( $tournament_id ) || 'live_tournament' !== get_post_type( $tournament_id ) ) {
			return new WP_Error( 'invalid_tournament', __( 'Invalid tournament ID', 'poker-tournament-import' ) );
		}

		// Validate player exists.
		if ( ! get_post( $player_id ) || 'player' !== get_post_type( $player_id ) ) {
			return new WP_Error( 'invalid_player', __( 'Invalid player ID', 'poker-tournament-import' ) );
		}

		// Check if player already registered.
		if ( self::is_player_registered( $tournament_id, $player_id ) ) {
			return new WP_Error( 'player_already_registered', __( 'Player is already registered for this tournament', 'poker-tournament-import' ) );
		}

		// Parse args.
		$defaults = array(
			'status'            => 'registered',
			'paid_amount'       => 0,
			'rebuys_count'      => 0,
			'addons_count'      => 0,
			'seat_assignment'   => null,
			'finish_position'   => null,
			'prize_amount'      => 0,
			'notes'             => '',
		);

		$data = wp_parse_args( $args, $defaults );

		// Insert player registration.
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'tournament_id'   => $tournament_id,
				'player_id'       => $player_id,
				'status'          => sanitize_text_field( $data['status'] ),
				'paid_amount'     => floatval( $data['paid_amount'] ),
				'rebuys_count'    => intval( $data['rebuys_count'] ),
				'addons_count'    => intval( $data['addons_count'] ),
				'seat_assignment' => $data['seat_assignment'] ? sanitize_text_field( $data['seat_assignment'] ) : null,
				'finish_position' => $data['finish_position'] ? intval( $data['finish_position'] ) : null,
				'prize_amount'    => floatval( $data['prize_amount'] ),
				'notes'           => wp_kses_post( $data['notes'] ),
			),
			array( '%d', '%d', '%s', '%f', '%d', '%d', '%s', '%d', '%f', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to add player to tournament', 'poker-tournament-import' ) );
		}

		do_action( 'tdwp_player_added_to_tournament', $wpdb->insert_id, $tournament_id, $player_id );

		return $wpdb->insert_id;
	}

	/**
	 * Remove player from tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id     Player ID.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_player( $tournament_id, $player_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		do_action( 'tdwp_player_removed_from_tournament', $tournament_id, $player_id );

		return true;
	}

	/**
	 * Update player status
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $player_id     Player ID.
	 * @param string $status        New status.
	 * @return bool True on success, false on failure.
	 */
	public static function update_player_status( $tournament_id, $player_id, $status ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'status' => sanitize_text_field( $status ) ),
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		do_action( 'tdwp_player_status_updated', $tournament_id, $player_id, $status );

		return true;
	}

	/**
	 * Get tournament players
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament ID.
	 * @param string $status        Optional. Filter by status.
	 * @return array Array of player registration objects.
	 */
	public static function get_tournament_players( $tournament_id, $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$players = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE tournament_id = %d AND status = %s ORDER BY registration_date ASC",
					$tournament_id,
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$players = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE tournament_id = %d ORDER BY registration_date ASC",
					$tournament_id
				)
			);
		}

		return $players;
	}

	/**
	 * Check if player is registered
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id     Player ID.
	 * @return bool True if registered, false otherwise.
	 */
	public static function is_player_registered( $tournament_id, $player_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE tournament_id = %d AND player_id = %d",
				$tournament_id,
				$player_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get player count for tournament
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament ID.
	 * @param string $status        Optional. Filter by status.
	 * @return int Player count.
	 */
	public static function get_player_count( $tournament_id, $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE tournament_id = %d AND status = %s",
					$tournament_id,
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE tournament_id = %d",
					$tournament_id
				)
			);
		}

		return (int) $count;
	}

	/**
	 * Get player registration data
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id     Player ID.
	 * @return object|null Player registration object or null if not found.
	 */
	public static function get_player_registration( $tournament_id, $player_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$registration = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d",
				$tournament_id,
				$player_id
			)
		);

		return $registration;
	}
}
