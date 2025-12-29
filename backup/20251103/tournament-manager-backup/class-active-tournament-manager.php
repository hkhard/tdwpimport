<?php
/**
 * Active Tournament Manager
 *
 * Manages persistence of "active tournament" for each user.
 * Allows users to navigate away from Live Control and return without losing context.
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
 * Active Tournament Manager class
 *
 * @since 3.1.0
 */
class TDWP_Active_Tournament_Manager {

	/**
	 * User meta key for active tournament
	 */
	const META_KEY = '_tdwp_active_tournament';

	/**
	 * Transient prefix for admin bar cache
	 */
	const CACHE_PREFIX = 'tdwp_adminbar_';

	/**
	 * Cache TTL in seconds (30 seconds)
	 */
	const CACHE_TTL = 30;

	/**
	 * Set active tournament for a user
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @param int $tournament_id Live tournament post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function set_active_tournament( $user_id, $tournament_id ) {
		// Verify user can manage tournaments.
		$user = get_userdata( $user_id );
		if ( ! $user || ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		// Validate tournament exists and is live_tournament.
		if ( ! self::is_valid_tournament( $tournament_id ) ) {
			return false;
		}

		// Save to user meta.
		$result = update_user_meta( $user_id, self::META_KEY, $tournament_id );

		// Invalidate admin bar cache.
		self::clear_admin_bar_cache( $user_id );

		return false !== $result;
	}

	/**
	 * Get active tournament for a user
	 *
	 * Returns tournament ID only if it exists, is valid, and not completed.
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @return int|false Tournament ID or false if none/invalid.
	 */
	public static function get_active_tournament( $user_id ) {
		// Verify user can manage tournaments.
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		$tournament_id = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! $tournament_id ) {
			return false;
		}

		// Validate tournament is still valid.
		if ( ! self::is_valid_tournament( $tournament_id ) ) {
			// Clear invalid tournament from user meta.
			self::clear_active_tournament( $user_id );
			return false;
		}

		return (int) $tournament_id;
	}

	/**
	 * Clear active tournament for a user
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public static function clear_active_tournament( $user_id ) {
		$result = delete_user_meta( $user_id, self::META_KEY );

		// Invalidate admin bar cache.
		self::clear_admin_bar_cache( $user_id );

		return $result;
	}

	/**
	 * Clear tournament from all users' active tournaments
	 *
	 * Called when tournament is completed/deleted.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return int Number of users cleared.
	 */
	public static function clear_tournament_from_all_users( $tournament_id ) {
		global $wpdb;

		// Find all users with this tournament as active.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE meta_key = %s AND meta_value = %d",
				self::META_KEY,
				$tournament_id
			)
		);

		if ( empty( $user_ids ) ) {
			return 0;
		}

		// Clear for each user.
		foreach ( $user_ids as $user_id ) {
			self::clear_active_tournament( $user_id );
		}

		return count( $user_ids );
	}

	/**
	 * Get all running tournaments
	 *
	 * @since 3.1.0
	 * @return array Array of tournament post objects.
	 */
	public static function get_running_tournaments() {
		$args = array(
			'post_type'      => 'live_tournament',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_status',
					'value'   => array( 'setup', 'running', 'paused', 'break' ),
					'compare' => 'IN',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_status',
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}

	/**
	 * Validate tournament ID
	 *
	 * Checks if tournament exists, is live_tournament type, and not completed.
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_valid_tournament( $tournament_id ) {
		$post = get_post( $tournament_id );

		// Check post exists and is live_tournament.
		if ( ! $post || 'live_tournament' !== $post->post_type ) {
			return false;
		}

		// Check tournament is not completed.
		$status = get_post_meta( $tournament_id, '_status', true );
		if ( 'completed' === $status ) {
			return false;
		}

		return true;
	}

	/**
	 * Get cached admin bar data for user
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @return array|false Cached data or false if not cached.
	 */
	public static function get_admin_bar_cache( $user_id ) {
		return get_transient( self::CACHE_PREFIX . $user_id );
	}

	/**
	 * Set admin bar cache for user
	 *
	 * @since 3.1.0
	 * @param int   $user_id User ID.
	 * @param array $data Data to cache.
	 * @return bool True on success.
	 */
	public static function set_admin_bar_cache( $user_id, $data ) {
		return set_transient( self::CACHE_PREFIX . $user_id, $data, self::CACHE_TTL );
	}

	/**
	 * Clear admin bar cache for user
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public static function clear_admin_bar_cache( $user_id ) {
		return delete_transient( self::CACHE_PREFIX . $user_id );
	}

	/**
	 * Get tournament data for admin bar display
	 *
	 * Returns cached data if available, otherwise queries and caches.
	 *
	 * @since 3.1.0
	 * @param int $user_id User ID.
	 * @return array|false Tournament data or false if no active tournament.
	 */
	public static function get_tournament_data_for_admin_bar( $user_id ) {
		// Check cache first.
		$cached = self::get_admin_bar_cache( $user_id );
		if ( false !== $cached ) {
			return $cached;
		}

		// Get active tournament.
		$tournament_id = self::get_active_tournament( $user_id );
		if ( ! $tournament_id ) {
			return false;
		}

		// Get tournament post.
		$post = get_post( $tournament_id );
		if ( ! $post ) {
			return false;
		}

		// Get tournament state.
		$state = TDWP_Live_State_Manager::get_state( $tournament_id );

		// Prepare data.
		$data = array(
			'id'            => $tournament_id,
			'title'         => $post->post_title,
			'status'        => $state ? $state->status : 'unknown',
			'current_level' => $state ? $state->current_level : 0,
			'is_practice'   => $state && isset( $state->is_practice ) ? (int) $state->is_practice : 0,
			'url'           => admin_url( 'admin.php?page=tdwp-live-control&tournament_id=' . $tournament_id ),
		);

		// Cache for 30 seconds.
		self::set_admin_bar_cache( $user_id, $data );

		return $data;
	}
}
