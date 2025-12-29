<?php
/**
 * Tournament Live State Manager
 *
 * Manages tournament clock state for Phase 2 Week 1
 * Handles start/pause/resume, level advancement, breaks, and time calculation
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
 * Live State Manager class
 *
 * @since 3.1.0
 */
class TDWP_Live_State_Manager {

	/**
	 * Tournament statuses
	 */
	const STATUS_SETUP = 'setup';
	const STATUS_RUNNING = 'running';
	const STATUS_PAUSED = 'paused';
	const STATUS_BREAK = 'break';
	const STATUS_FINISHED = 'finished';

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
	 * Get tournament live state
	 *
	 * Supports both 'tournament' and 'live_tournament' post types.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return object|null State object or null if not found
	 */
	public static function get_state( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$state = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d",
				$tournament_id
			)
		);

		return $state;
	}

	/**
	 * Initialize tournament state
	 *
	 * Supports both 'tournament' and 'live_tournament' post types.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $template_id   Optional template ID.
	 * @return int|false State ID or false on failure
	 */
	public static function initialize( $tournament_id, $template_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// Validate tournament post type.
		if ( ! self::is_valid_tournament( $tournament_id ) ) {
			return false;
		}

		// Check if state already exists
		$existing = self::get_state( $tournament_id );
		if ( $existing ) {
			return $existing->id;
		}

		// Get is_practice from post meta
		$is_practice = (int) get_post_meta( $tournament_id, '_is_practice', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'tournament_id' => $tournament_id,
				'template_id'   => $template_id,
				'status'        => self::STATUS_SETUP,
				'current_level' => 1,
				'time_remaining' => 0,
				'is_practice'   => $is_practice,
			),
			array( '%d', '%d', '%s', '%d', '%d', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Start tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $level_duration_seconds Duration of first level in seconds.
	 * @return bool True on success
	 */
	public static function start( $tournament_id, $level_duration_seconds ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'         => self::STATUS_RUNNING,
				'current_level'  => 1,
				'time_remaining' => $level_duration_seconds,
				'started_at'     => current_time( 'mysql' ),
				'paused_at'      => null,
				'break_until'    => null,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%d', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_tournament_started', $tournament_id );
		}

		return $result !== false;
	}

	/**
	 * Pause tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $time_remaining_seconds Current time remaining in seconds.
	 * @return bool True on success
	 */
	public static function pause( $tournament_id, $time_remaining_seconds ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'         => self::STATUS_PAUSED,
				'time_remaining' => $time_remaining_seconds,
				'paused_at'      => current_time( 'mysql' ),
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_tournament_paused', $tournament_id, $time_remaining_seconds );
		}

		return $result !== false;
	}

	/**
	 * Resume tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return bool True on success
	 */
	public static function resume( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'    => self::STATUS_RUNNING,
				'paused_at' => null,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_tournament_resumed', $tournament_id );
		}

		return $result !== false;
	}

	/**
	 * Advance to next level
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $next_level_duration_seconds Duration of next level in seconds.
	 * @return bool True on success
	 */
	public static function advance_level( $tournament_id, $next_level_duration_seconds ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		$state = self::get_state( $tournament_id );
		if ( ! $state ) {
			return false;
		}

		$new_level = $state->current_level + 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'current_level'  => $new_level,
				'time_remaining' => $next_level_duration_seconds,
				'status'         => self::STATUS_RUNNING,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_level_advanced', $tournament_id, $new_level );
		}

		return $result !== false;
	}

	/**
	 * Start break
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $break_duration_minutes Break duration in minutes.
	 * @return bool True on success
	 */
	public static function start_break( $tournament_id, $break_duration_minutes ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		$break_end_time = gmdate( 'Y-m-d H:i:s', time() + ( $break_duration_minutes * 60 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'      => self::STATUS_BREAK,
				'break_until' => $break_end_time,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_break_started', $tournament_id, $break_duration_minutes );
		}

		return $result !== false;
	}

	/**
	 * End break
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $next_level_duration_seconds Duration of next level in seconds.
	 * @return bool True on success
	 */
	public static function end_break( $tournament_id, $next_level_duration_seconds ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		$state = self::get_state( $tournament_id );
		if ( ! $state ) {
			return false;
		}

		$new_level = $state->current_level + 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'         => self::STATUS_RUNNING,
				'current_level'  => $new_level,
				'time_remaining' => $next_level_duration_seconds,
				'break_until'    => null,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_break_ended', $tournament_id, $new_level );
		}

		return $result !== false;
	}

	/**
	 * Add time to current level
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $seconds_to_add Seconds to add.
	 * @return bool True on success
	 */
	public static function add_time( $tournament_id, $seconds_to_add ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		$state = self::get_state( $tournament_id );
		if ( ! $state ) {
			return false;
		}

		$new_time_remaining = $state->time_remaining + $seconds_to_add;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array( 'time_remaining' => $new_time_remaining ),
			array( 'tournament_id' => $tournament_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_time_added', $tournament_id, $seconds_to_add );
		}

		return $result !== false;
	}

	/**
	 * Update time remaining
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $time_remaining_seconds Current time remaining.
	 * @return bool True on success
	 */
	public static function update_time( $tournament_id, $time_remaining_seconds ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array( 'time_remaining' => $time_remaining_seconds ),
			array( 'tournament_id' => $tournament_id ),
			array( '%d' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Finish tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return bool True on success
	 */
	public static function finish( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'       => self::STATUS_FINISHED,
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'tdwp_tournament_finished', $tournament_id );
		}

		return $result !== false;
	}

	/**
	 * Update player counts
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @param int $total_players Total registered players.
	 * @param int $remaining_players Players still in tournament.
	 * @return bool True on success
	 */
	public static function update_player_counts( $tournament_id, $total_players, $remaining_players ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array(
				'total_players'     => $total_players,
				'remaining_players' => $remaining_players,
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete tournament state
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return bool True on success
	 */
	public static function delete( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$table,
			array( 'tournament_id' => $tournament_id ),
			array( '%d' )
		);

		return $result !== false;
	}
}
