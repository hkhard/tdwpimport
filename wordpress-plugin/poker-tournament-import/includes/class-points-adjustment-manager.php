<?php
/**
 * Manual points-adjustment manager (tdwp-31i).
 *
 * Provides an insert-only audit log of per-player points overrides for a
 * tournament. The latest row per (tournament_uuid, player_uuid) is the
 * effective adjustment; season standings read it at aggregation time so an
 * override survives a re-import and is honoured everywhere.
 *
 * @package Poker_Tournament_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the tdwp_points_adjustments audit log.
 */
class Poker_Points_Adjustment_Manager {

	/**
	 * Transient name fragments that cache standings derived from points.
	 *
	 * @var string[]
	 */
	private $cache_patterns = array(
		'poker_season_standings_',
		'poker_series_standings_',
		'poker_overall_standings_',
		'poker_statistics_',
		'poker_leaderboard_',
		'poker_player_roi_',
	);

	/**
	 * Fully-qualified adjustments table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'tdwp_points_adjustments';
	}

	/**
	 * Get the effective (latest) adjustment for one player in a tournament.
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @param string $player_uuid     Player UUID.
	 * @return object|null Row object, or null if none.
	 */
	public function get_adjustment( $tournament_uuid, $player_uuid ) {
		global $wpdb;
		$table = $this->table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE tournament_uuid = %s AND player_uuid = %s
				 ORDER BY id DESC LIMIT 1",
				$tournament_uuid,
				$player_uuid
			)
		);
	}

	/**
	 * Build a map of effective adjusted points for a set of tournaments.
	 *
	 * Single query using a MAX(id) subselect so only the latest row per
	 * (tournament_uuid, player_uuid) wins.
	 *
	 * @param string[] $tournament_uuids Tournament UUIDs.
	 * @return array<string,float> Map of "tournament_uuid|player_uuid" => adjusted points.
	 */
	public function get_adjustment_map( $tournament_uuids ) {
		global $wpdb;

		$tournament_uuids = array_values( array_unique( array_filter( (array) $tournament_uuids ) ) );
		if ( empty( $tournament_uuids ) ) {
			return array();
		}

		$table        = $this->table();
		$placeholders = implode( ', ', array_fill( 0, count( $tournament_uuids ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from a counted array, values bound below.
		$sql = $wpdb->prepare(
			"SELECT a.tournament_uuid, a.player_uuid, a.adjusted_points
			 FROM {$table} a
			 INNER JOIN (
				SELECT MAX(id) AS max_id FROM {$table}
				WHERE tournament_uuid IN ( {$placeholders} )
				GROUP BY tournament_uuid, player_uuid
			 ) latest ON a.id = latest.max_id",
			$tournament_uuids
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql prepared above.

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ $row->tournament_uuid . '|' . $row->player_uuid ] = floatval( $row->adjusted_points );
		}

		return $map;
	}

	/**
	 * Get the effective adjustments for one tournament (latest per player).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return object[] Rows.
	 */
	public function get_adjustments_for_tournament( $tournament_uuid ) {
		global $wpdb;
		$table = $this->table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.* FROM {$table} a
				 INNER JOIN (
					SELECT MAX(id) AS max_id FROM {$table}
					WHERE tournament_uuid = %s GROUP BY player_uuid
				 ) latest ON a.id = latest.max_id",
				$tournament_uuid
			)
		);
	}

	/**
	 * Read the full audit history (all rows, newest first).
	 *
	 * @param array $filters Optional filters: tournament_uuid, player_uuid.
	 * @param int   $limit   Max rows.
	 * @param int   $offset  Offset.
	 * @return object[] Rows.
	 */
	public function get_audit_log( $filters = array(), $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = $this->table();

		$where  = '1=1';
		$params = array();
		if ( ! empty( $filters['tournament_uuid'] ) ) {
			$where   .= ' AND tournament_uuid = %s';
			$params[] = $filters['tournament_uuid'];
		}
		if ( ! empty( $filters['player_uuid'] ) ) {
			$where   .= ' AND player_uuid = %s';
			$params[] = $filters['player_uuid'];
		}

		$params[] = max( 1, intval( $limit ) );
		$params[] = max( 0, intval( $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where uses fixed fragments; all values bound below.
		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Record a points adjustment (insert-only).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @param string $player_uuid     Player UUID.
	 * @param float  $original_points Snapshot of the current points before override.
	 * @param float  $adjusted_points New points value.
	 * @param string $reason          Operator-supplied reason (required by callers).
	 * @param int    $actor_user_id   User making the change.
	 * @return int|false Insert ID, or false on failure.
	 */
	public function apply_adjustment( $tournament_uuid, $player_uuid, $original_points, $adjusted_points, $reason, $actor_user_id ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'tournament_uuid' => $tournament_uuid,
				'player_uuid'     => $player_uuid,
				'original_points' => floatval( $original_points ),
				'adjusted_points' => floatval( $adjusted_points ),
				'reason'          => $reason,
				'actor_user_id'   => $actor_user_id ? intval( $actor_user_id ) : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%f', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$this->bust_standings_cache();

		/**
		 * Fires after a manual points adjustment is recorded.
		 *
		 * @param string $tournament_uuid Tournament UUID.
		 * @param string $player_uuid     Player UUID.
		 * @param float  $adjusted_points New points value.
		 */
		do_action( 'tdwp_points_adjustment_applied', $tournament_uuid, $player_uuid, floatval( $adjusted_points ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all standings/statistics transients derived from points.
	 */
	public function bust_standings_cache() {
		global $wpdb;

		foreach ( $this->cache_patterns as $pattern ) {
			$like    = $wpdb->esc_like( '_transient_' . $pattern ) . '%';
			$options = $wpdb->get_col(
				$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
			);
			foreach ( (array) $options as $option ) {
				delete_transient( str_replace( '_transient_', '', $option ) );
			}
		}
	}
}
