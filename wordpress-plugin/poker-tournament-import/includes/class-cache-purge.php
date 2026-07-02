<?php
/**
 * Central cache invalidation for the public-facing stat surfaces.
 *
 * The dashboard, leaderboards, and player/tournament pages are aggregate views that a full-page
 * cache (LiteSpeed) and the plugin's own transients/object-cache cannot know are stale when the
 * underlying stats change. Historically only the points-adjustment handler purged LiteSpeed, so
 * after an import, a live-tournament finish, a bulk delete, or a manual "Refresh Statistics" the
 * site kept serving stale numbers until the operator purged by hand. This centralises the purge so
 * every stats-changing path invalidates the same set of caches exactly once per request.
 *
 * @package Poker_Tournament_Import
 * @since 3.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poker_Cache_Purge {

	/**
	 * Transient key prefixes the plugin uses for derived stat/standings caches.
	 *
	 * @var string[]
	 */
	private static $transient_prefixes = array(
		'poker_statistics_',
		'poker_leaderboard_',
		'poker_season_standings_',
		'poker_series_standings_',
		'poker_overall_standings_',
		'poker_player_roi_',
		'poker_dashboard_',
	);

	/**
	 * Purge every public-facing cache affected by a stats change. Idempotent and guarded to run at
	 * most once per request (so bulk operations that recompute repeatedly do not purge N times).
	 *
	 * Safe with no cache plugin installed: the LiteSpeed action is a no-op without a listener, and
	 * the object-cache group flush is skipped on backends that do not support it.
	 *
	 * @return void
	 */
	public static function purge_public() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		// 1. LiteSpeed (and compatible) full-page cache. No-op when LiteSpeed is absent.
		do_action( 'litespeed_purge_all' );

		// 2. The per-query object cache group used by the front-end shortcodes.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'poker_tournament' );
		}

		// 3. The plugin's derived stat/standings transients (DB + object cache).
		self::delete_stats_transients();

		/**
		 * Fires after the plugin has invalidated its public stat caches, so integrations can
		 * hook additional cache layers (Cloudflare, other page caches, etc.).
		 */
		do_action( 'poker_public_caches_purged' );
	}

	/**
	 * Delete every transient whose key starts with one of the plugin's stat prefixes.
	 *
	 * Resolves the matching transient names from the options table, then deletes each via
	 * delete_transient() so both the DB row AND any persistent object-cache copy (Redis/Memcached
	 * behind LiteSpeed) are cleared — a bare SQL DELETE would leave the object-cached value stale.
	 *
	 * @return int Number of transients removed.
	 */
	private static function delete_stats_transients() {
		global $wpdb;

		$names = array();
		foreach ( self::$transient_prefixes as $prefix ) {
			$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Resolve transient keys to purge.
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			foreach ( (array) $rows as $option_name ) {
				$names[] = substr( $option_name, strlen( '_transient_' ) );
			}
		}

		$names = array_unique( $names );
		foreach ( $names as $transient ) {
			delete_transient( $transient );
		}

		return count( $names );
	}
}
