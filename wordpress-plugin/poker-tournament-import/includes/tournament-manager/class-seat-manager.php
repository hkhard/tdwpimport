<?php
/**
 * Tournament Seat Manager
 *
 * Manages seat assignments for Phase 2 Week 2-3
 * Handles moving players, unseating, auto-seating
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seat Manager class
 *
 * @since 3.1.0
 */
class TDWP_Seat_Manager {

	/**
	 * Move player to a seat
	 *
	 * @since 3.1.0
	 * @param int $player_id Player post ID.
	 * @param int $to_table_id Destination table ID.
	 * @param int $to_seat_number Destination seat number.
	 * @return bool True on success
	 */
	public static function move_player( $player_id, $to_table_id, $to_seat_number ) {
		global $wpdb;

		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

		// Validate destination seat is empty
		if ( ! self::is_seat_empty( $to_table_id, $to_seat_number ) ) {
			return false;
		}

		// Check if player is currently seated
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$seats_table} WHERE player_id = %d",
				$player_id
			)
		);

		// If player is currently seated, save movement history
		$moved_from_table_id = null;
		$moved_from_seat_number = null;

		if ( $current_seat ) {
			$moved_from_table_id     = $current_seat->table_id;
			$moved_from_seat_number  = $current_seat->seat_number;

			// Clear old seat
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$seats_table,
				array(
					'player_id'   => null,
					'status'      => 'empty',
					'assigned_at' => null,
				),
				array( 'id' => $current_seat->id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		// Assign player to new seat
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$seats_table,
			array(
				'player_id'              => $player_id,
				'status'                 => 'occupied',
				'assigned_at'            => current_time( 'mysql' ),
				'moved_from_table_id'    => $moved_from_table_id,
				'moved_from_seat_number' => $moved_from_seat_number,
			),
			array(
				'table_id'    => $to_table_id,
				'seat_number' => $to_seat_number,
			),
			array( '%d', '%s', '%s', '%d', '%d' ),
			array( '%d', '%d' )
		);

		if ( $result !== false ) {
			$table = TDWP_Table_Manager::get_table( $to_table_id );
			if ( $table ) {
				do_action( 'tdwp_player_moved', $table->tournament_id, $player_id, $to_table_id, $to_seat_number );
			}
		}

		return $result !== false;
	}

	/**
	 * Unseat player
	 *
	 * @since 3.1.0
	 * @param int $player_id Player post ID.
	 * @return bool True on success
	 */
	public static function unseat_player( $player_id ) {
		global $wpdb;

		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

		// Find player's current seat
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$seats_table} WHERE player_id = %d",
				$player_id
			)
		);

		if ( ! $current_seat ) {
			return false; // Player not seated
		}

		// Clear seat
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$seats_table,
			array(
				'player_id'   => null,
				'status'      => 'empty',
				'assigned_at' => null,
			),
			array( 'id' => $current_seat->id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			$table = TDWP_Table_Manager::get_table( $current_seat->table_id );
			if ( $table ) {
				do_action( 'tdwp_player_unseated', $table->tournament_id, $player_id, $current_seat->table_id, $current_seat->seat_number );
			}
		}

		return $result !== false;
	}

	/**
	 * Auto-seat player at next available seat
	 *
	 * @since 3.1.0
	 * @param int $player_id Player post ID.
	 * @param int $tournament_id Tournament post ID.
	 * @return bool True on success
	 */
	public static function auto_seat_player( $player_id, $tournament_id ) {
		// Find optimal empty seat
		$seat = self::find_optimal_seat( $tournament_id );

		if ( ! $seat ) {
			return false; // No available seats
		}

		return self::move_player( $player_id, $seat->table_id, $seat->seat_number );
	}

	/**
	 * Find optimal empty seat for player
	 *
	 * Prioritizes balancing tables
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return object|null Seat object or null
	 */
	public static function find_optimal_seat( $tournament_id ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';

		// Find table with fewest players that has empty seat
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, COUNT(s2.player_id) as table_player_count
				FROM {$seats_table} s
				INNER JOIN {$tables_table} t ON s.table_id = t.id
				LEFT JOIN {$seats_table} s2 ON s.table_id = s2.table_id AND s2.player_id IS NOT NULL
				WHERE t.tournament_id = %d
				AND t.status = 'active'
				AND s.player_id IS NULL
				GROUP BY s.id
				ORDER BY table_player_count ASC, RAND()
				LIMIT 1",
				$tournament_id
			)
		);

		return $seat;
	}

	/**
	 * Auto-seat all unseated players
	 *
	 * Assigns unseated players to optimal seats, balancing tables
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array Results with seated count and any errors
	 */
	public static function auto_seat_players( $tournament_id ) {
		$unseated_players = self::get_unseated_players( $tournament_id );

		if ( empty( $unseated_players ) ) {
			return array(
				'success'      => true,
				'seated_count' => 0,
				'message'      => __( 'No unseated players to seat', 'poker-tournament-import' ),
			);
		}

		$seated_count = 0;
		$errors       = array();

		foreach ( $unseated_players as $player ) {
			$seat = self::find_optimal_seat( $tournament_id );

			if ( ! $seat ) {
				$errors[] = sprintf(
					/* translators: %s: player name */
					__( 'No available seat for %s', 'poker-tournament-import' ),
					$player->post_title
				);
				continue;
			}

			$success = self::move_player( $player->ID, $seat->table_id, $seat->seat_number );

			if ( $success ) {
				++$seated_count;
			} else {
				$errors[] = sprintf(
					/* translators: %s: player name */
					__( 'Failed to seat %s', 'poker-tournament-import' ),
					$player->post_title
				);
			}
		}

		return array(
			'success'      => true,
			'seated_count' => $seated_count,
			'errors'       => $errors,
			'message'      => sprintf(
				/* translators: %d: number of players */
				__( 'Seated %d player(s)', 'poker-tournament-import' ),
				$seated_count
			),
		);
	}

	/**
	 * Check if seat is empty
	 *
	 * @since 3.1.0
	 * @param int $table_id Table ID.
	 * @param int $seat_number Seat number.
	 * @return bool True if empty
	 */
	public static function is_seat_empty( $table_id, $seat_number ) {
		global $wpdb;

		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$seats_table}
				WHERE table_id = %d AND seat_number = %d",
				$table_id,
				$seat_number
			)
		);

		return $seat && ! $seat->player_id;
	}

	/**
	 * Get unseated players for tournament
	 *
	 * Returns registered players not currently seated
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array Array of player post objects
	 */
	public static function get_unseated_players( $tournament_id ) {
		global $wpdb;

		// Query tournament_players table for eligible unseated players
		// Only players with status 'paid' or 'active' are eligible for seating
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';
		$seats_table   = $wpdb->prefix . 'tdwp_tournament_seats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$player_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tp.player_id
				FROM {$players_table} tp
				LEFT JOIN {$seats_table} ts ON tp.player_id = ts.player_id
				WHERE tp.tournament_id = %d
				AND tp.status IN ('paid', 'active')
				AND ts.player_id IS NULL
				ORDER BY tp.registration_date ASC",
				$tournament_id
			)
		);

		if ( empty( $player_ids ) ) {
			return array();
		}

		// Get player post objects
		$players = get_posts(
			array(
				'post_type'      => 'player',
				'post__in'       => $player_ids,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
			)
		);

		return $players;
	}

	/**
	 * Get player's current seat
	 *
	 * @since 3.1.0
	 * @param int $player_id Player post ID.
	 * @return object|null Seat object with table data or null
	 */
	public static function get_player_seat( $player_id ) {
		global $wpdb;

		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';
		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, t.tournament_id, t.table_number, t.max_seats, t.status as table_status
				FROM {$seats_table} s
				INNER JOIN {$tables_table} t ON s.table_id = t.id
				WHERE s.player_id = %d",
				$player_id
			)
		);

		return $seat;
	}

	/**
	 * Validate seat assignment
	 *
	 * @since 3.1.0
	 * @param int $player_id Player post ID.
	 * @param int $table_id Table ID.
	 * @param int $seat_number Seat number.
	 * @return array Array with 'valid' bool and 'error' message
	 */
	public static function validate_assignment( $player_id, $table_id, $seat_number ) {
		global $wpdb;

		// Check if seat exists
		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$seat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$seats_table}
				WHERE table_id = %d AND seat_number = %d",
				$table_id,
				$seat_number
			)
		);

		if ( ! $seat ) {
			return array(
				'valid' => false,
				'error' => __( 'Seat does not exist', 'poker-tournament-import' ),
			);
		}

		// Check if seat is empty
		if ( $seat->player_id && $seat->player_id !== $player_id ) {
			return array(
				'valid' => false,
				'error' => __( 'Seat is occupied', 'poker-tournament-import' ),
			);
		}

		// Check table is active
		$table = TDWP_Table_Manager::get_table( $table_id );
		if ( ! $table || $table->status !== 'active' ) {
			return array(
				'valid' => false,
				'error' => __( 'Table is not active', 'poker-tournament-import' ),
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Bulk move players
	 *
	 * @since 3.1.0
	 * @param array $moves Array of move operations: [[player_id, to_table_id, to_seat_number], ...]
	 * @return array Results array with success/failure for each move
	 */
	public static function bulk_move( $moves ) {
		$results = array();

		foreach ( $moves as $move ) {
			list( $player_id, $to_table_id, $to_seat_number ) = $move;

			$success = self::move_player( $player_id, $to_table_id, $to_seat_number );

			$results[] = array(
				'player_id'      => $player_id,
				'to_table_id'    => $to_table_id,
				'to_seat_number' => $to_seat_number,
				'success'        => $success,
			);
		}

		return $results;
	}
}
