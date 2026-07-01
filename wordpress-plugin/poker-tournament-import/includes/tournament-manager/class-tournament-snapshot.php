<?php
/**
 * Tournament config snapshot (tdwp-3lg.5)
 *
 * When a tournament starts, its full effective configuration — template fields,
 * resolved blind levels, and prize structure — is snapshotted into the
 * tournament's own postmeta. Consumers read the snapshot in preference to the
 * live template so that later template edits can never retroactively change a
 * running or historical tournament.
 *
 * The builder/accessors are pure so the merge + precedence logic is
 * unit-testable.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds, stores and reads per-tournament config snapshots.
 */
class TDWP_Tournament_Snapshot {

	/**
	 * Postmeta key holding the snapshot.
	 */
	const META_KEY = '_tournament_config_snapshot';

	/**
	 * Snapshot schema version.
	 */
	const VERSION = 1;

	/**
	 * Assemble a snapshot structure (pure; no DB, no time).
	 *
	 * @param array $template     Template row as an associative array.
	 * @param array $blind_levels List of blind-level rows.
	 * @param array $prizes       Prize structure rows.
	 * @return array Normalized snapshot.
	 */
	public static function build( $template, $blind_levels, $prizes ) {
		return array(
			'version'      => self::VERSION,
			'template'     => is_array( $template ) ? $template : array(),
			'blind_levels' => array_values( (array) $blind_levels ),
			'prizes'       => array_values( (array) $prizes ),
		);
	}

	/**
	 * Read a template config value from a snapshot (pure).
	 *
	 * @param array  $snapshot Snapshot array.
	 * @param string $key      Template field key.
	 * @param mixed  $default  Fallback when the key is absent.
	 * @return mixed
	 */
	public static function config( $snapshot, $key, $default = null ) {
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['template'] ) || ! is_array( $snapshot['template'] ) ) {
			return $default;
		}
		return array_key_exists( $key, $snapshot['template'] ) ? $snapshot['template'][ $key ] : $default;
	}

	/**
	 * Find the blind level for a given level order within a snapshot (pure).
	 *
	 * @param array $snapshot    Snapshot array.
	 * @param int   $level_order Level number.
	 * @return array|null The matching level row, or null.
	 */
	public static function blind_level_for( $snapshot, $level_order ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['blind_levels'] ) ) {
			return null;
		}
		$level_order = (int) $level_order;
		foreach ( $snapshot['blind_levels'] as $level ) {
			$level = (array) $level;
			if ( isset( $level['level_order'] ) && (int) $level['level_order'] === $level_order ) {
				return $level;
			}
		}
		return null;
	}

	/**
	 * Create and store the snapshot for a tournament at start (one-time).
	 *
	 * Does nothing if a snapshot already exists (start is authoritative).
	 *
	 * @param int $tournament_id Tournament ID.
	 * @param int $template_id   Template ID.
	 * @return array|WP_Error The stored snapshot, or WP_Error.
	 */
	public static function create( $tournament_id, $template_id ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$template_id   = absint( $template_id );
		if ( ! $tournament_id ) {
			return new WP_Error( 'invalid_tournament', __( 'Invalid tournament', 'poker-tournament-import' ) );
		}

		$existing = get_post_meta( $tournament_id, self::META_KEY, true );
		if ( ! empty( $existing ) && is_array( $existing ) ) {
			return $existing;
		}

		$template = array();
		$blinds   = array();
		$prizes   = array();

		if ( $template_id ) {
			$template_row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tdwp_tournament_templates WHERE id = %d", $template_id ),
				ARRAY_A
			);
			if ( is_array( $template_row ) ) {
				$template = $template_row;

				$schedule_id = isset( $template_row['blind_schedule_id'] ) ? (int) $template_row['blind_schedule_id'] : 0;
				if ( $schedule_id ) {
					$blinds = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT level_order, small_blind, big_blind, ante, duration_minutes, is_break, break_duration_minutes
							 FROM {$wpdb->prefix}tdwp_blind_levels WHERE schedule_id = %d ORDER BY level_order ASC",
							$schedule_id
						),
						ARRAY_A
					);
					$blinds = is_array( $blinds ) ? $blinds : array();
				}

				$prize_structure_id = isset( $template_row['prize_structure_id'] ) ? (int) $template_row['prize_structure_id'] : 0;
				if ( $prize_structure_id ) {
					$prizes = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}tdwp_prize_structures WHERE id = %d",
							$prize_structure_id
						),
						ARRAY_A
					);
					$prizes = is_array( $prizes ) ? $prizes : array();
				}
			}
		}

		$snapshot                = self::build( $template, $blinds, $prizes );
		$snapshot['snapshot_at'] = current_time( 'mysql' );

		update_post_meta( $tournament_id, self::META_KEY, $snapshot );

		return $snapshot;
	}

	/**
	 * Get a tournament's config snapshot.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array|null
	 */
	public static function get( $tournament_id ) {
		$snapshot = get_post_meta( absint( $tournament_id ), self::META_KEY, true );
		return ( ! empty( $snapshot ) && is_array( $snapshot ) ) ? $snapshot : null;
	}

	/**
	 * Read a config value for a tournament, preferring the snapshot.
	 *
	 * Falls back to the given default when there is no snapshot or key.
	 *
	 * @param int    $tournament_id Tournament ID.
	 * @param string $key           Template field key.
	 * @param mixed  $default       Fallback value.
	 * @return mixed
	 */
	public static function get_config( $tournament_id, $key, $default = null ) {
		$snapshot = self::get( $tournament_id );
		if ( null === $snapshot ) {
			return $default;
		}
		return self::config( $snapshot, $key, $default );
	}
}
