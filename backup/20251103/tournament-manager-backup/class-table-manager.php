<?php
/**
 * Tournament Table Manager
 *
 * Manages poker tables for Phase 2 Week 2-3
 * Handles adding/removing tables, retrieving table data
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
 * Table Manager class
 *
 * @since 3.1.0
 */
class TDWP_Table_Manager {

	/**
	 * Table statuses
	 */
	const STATUS_ACTIVE = 'active';
	const STATUS_BREAKING = 'breaking';
	const STATUS_BROKEN = 'broken';

	/**
	 * Validate tournament post type
	 *
	 * Checks if the given post ID is a valid tournament or live_tournament post.
	 * This manager supports both post types.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Post ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_valid_tournament( $tournament_id ) {
		$post = get_post( $tournament_id );

		if ( ! $post ) {
			return false;
		}

		return in_array( $post->post_type, array( 'tournament', 'live_tournament' ), true );
	}

	/**
	 * Add new table
	 *
	 * Supports both 'tournament' and 'live_tournament' post types.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $max_seats Max seats for this table (2-12).
	 * @return int|false Table ID or false on failure
	 */
	public static function add_table( $tournament_id, $max_seats = 9 ) {
		global $wpdb;

		// Validate tournament post type.
		if ( ! self::is_valid_tournament( $tournament_id ) ) {
			return false;
		}

		// Validate max_seats
		$max_seats = max( 2, min( 12, intval( $max_seats ) ) );

		// Get next table number
		$table_number = self::get_next_table_number( $tournament_id );

		// Insert table
		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$tables_table,
			array(
				'tournament_id' => $tournament_id,
				'table_number'  => $table_number,
				'max_seats'     => $max_seats,
				'status'        => self::STATUS_ACTIVE,
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$table_id = $wpdb->insert_id;

		// Create empty seats for this table
		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';
		for ( $seat_num = 1; $seat_num <= $max_seats; $seat_num++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$seats_table,
				array(
					'table_id'    => $table_id,
					'seat_number' => $seat_num,
					'status'      => 'empty',
				),
				array( '%d', '%d', '%s' )
			);
		}

		do_action( 'tdwp_table_added', $tournament_id, $table_id, $table_number, $max_seats );

		return $table_id;
	}

	/**
	 * Remove table (must be empty)
	 *
	 * @since 3.1.0
	 * @param int $table_id Table ID.
	 * @return bool True on success, false on failure
	 */
	public static function remove_table( $table_id ) {
		global $wpdb;

		// Check if table is empty
		if ( ! self::is_table_empty( $table_id ) ) {
			return false;
		}

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';

		// Get table info before deletion
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tables_table} WHERE id = %d",
				$table_id
			)
		);

		if ( ! $table ) {
			return false;
		}

		// Delete seats first
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete(
			$seats_table,
			array( 'table_id' => $table_id ),
			array( '%d' )
		);

		// Delete table
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$tables_table,
			array( 'id' => $table_id ),
			array( '%d' )
		);

		if ( $result ) {
			do_action( 'tdwp_table_removed', $table->tournament_id, $table_id, $table->table_number );
		}

		return $result !== false;
	}

	/**
	 * Get all tables for tournament
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament post ID.
	 * @param string $status Optional filter by status.
	 * @return array Array of table objects with seat data
	 */
	public static function get_tables( $tournament_id, $status = null ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';

		// Build query
		$where_clauses = array( $wpdb->prepare( 't.tournament_id = %d', $tournament_id ) );

		if ( $status ) {
			$where_clauses[] = $wpdb->prepare( 't.status = %s', $status );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tables = $wpdb->get_results(
			"SELECT t.*,
			COUNT(CASE WHEN s.player_id IS NOT NULL THEN 1 END) as player_count
			FROM {$tables_table} t
			LEFT JOIN {$seats_table} s ON t.id = s.table_id
			WHERE {$where_sql}
			GROUP BY t.id
			ORDER BY t.table_number ASC"
		);

		// Add seat details to each table
		foreach ( $tables as $table ) {
			$table->seats = self::get_table_seats( $table->id );
		}

		return $tables;
	}

	/**
	 * Get single table by ID
	 *
	 * @since 3.1.0
	 * @param int $table_id Table ID.
	 * @return object|null Table object or null
	 */
	public static function get_table( $table_id ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tables_table} WHERE id = %d",
				$table_id
			)
		);

		if ( $table ) {
			$table->seats = self::get_table_seats( $table_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table->player_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tdwp_tournament_seats
					WHERE table_id = %d AND player_id IS NOT NULL",
					$table_id
				)
			);
		}

		return $table;
	}

	/**
	 * Get seats for a table
	 *
	 * @since 3.1.0
	 * @param int $table_id Table ID.
	 * @return array Array of seat objects
	 */
	public static function get_table_seats( $table_id ) {
		global $wpdb;

		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$seats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$seats_table}
				WHERE table_id = %d
				ORDER BY seat_number ASC",
				$table_id
			)
		);

		// Add player data to occupied seats
		foreach ( $seats as $seat ) {
			if ( $seat->player_id ) {
				$seat->player = get_post( $seat->player_id );
			}
		}

		return $seats;
	}

	/**
	 * Check if table is empty (no players)
	 *
	 * @since 3.1.0
	 * @param int $table_id Table ID.
	 * @return bool True if empty
	 */
	public static function is_table_empty( $table_id ) {
		global $wpdb;

		$seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$player_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$seats_table}
				WHERE table_id = %d AND player_id IS NOT NULL",
				$table_id
			)
		);

		return intval( $player_count ) === 0;
	}

	/**
	 * Update table status
	 *
	 * @since 3.1.0
	 * @param int    $table_id Table ID.
	 * @param string $status New status.
	 * @return bool True on success
	 */
	public static function update_status( $table_id, $status ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$tables_table,
			array( 'status' => $status ),
			array( 'id' => $table_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$tables_table} WHERE id = %d",
					$table_id
				)
			);

			if ( $table ) {
				do_action( 'tdwp_table_status_updated', $table->tournament_id, $table_id, $status );
			}
		}

		return $result !== false;
	}

	/**
	 * Get next table number for tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return int Next table number
	 */
	private static function get_next_table_number( $tournament_id ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(table_number) FROM {$tables_table} WHERE tournament_id = %d",
				$tournament_id
			)
		);

		return intval( $max_number ) + 1;
	}

	/**
	 * Get table count for tournament
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament post ID.
	 * @param string $status Optional filter by status.
	 * @return int Table count
	 */
	public static function get_table_count( $tournament_id, $status = null ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tables_table}
					WHERE tournament_id = %d AND status = %s",
					$tournament_id,
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tables_table} WHERE tournament_id = %d",
					$tournament_id
				)
			);
		}

		return intval( $count );
	}

	/**
	 * Get total seated players across all tables
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return int Seated player count
	 */
	public static function get_seated_player_count( $tournament_id ) {
		global $wpdb;

		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT s.player_id)
				FROM {$seats_table} s
				INNER JOIN {$tables_table} t ON s.table_id = t.id
				WHERE t.tournament_id = %d
				AND t.status = %s
				AND s.player_id IS NOT NULL",
				$tournament_id,
				self::STATUS_ACTIVE
			)
		);

		return intval( $count );
	}
}
