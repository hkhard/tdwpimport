<?php
/**
 * League Manager (tdwp-ee1.14)
 *
 * CRUD for leagues, their seasons, and player memberships. The schema
 * (tdwp_leagues / tdwp_seasons / tdwp_league_memberships) already existed;
 * this adds the management layer and API.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages leagues, seasons, and memberships.
 */
class TDWP_League_Manager {

	/**
	 * Allowed league types.
	 *
	 * @var string[]
	 */
	const LEAGUE_TYPES = array( 'points', 'knockout', 'hybrid' );

	/**
	 * Allowed membership statuses.
	 *
	 * @var string[]
	 */
	const MEMBERSHIP_STATUSES = array( 'active', 'inactive', 'suspended' );

	/**
	 * Table name helper.
	 *
	 * @param string $suffix Table suffix (leagues|seasons|league_memberships).
	 * @return string
	 */
	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'tdwp_' . $suffix;
	}

	/**
	 * Normalise a league type to an allowed value (pure).
	 *
	 * @param string $type Raw type.
	 * @return string A valid league type ('points' fallback).
	 */
	public static function sanitize_league_type( $type ) {
		$type = sanitize_text_field( (string) $type );
		return in_array( $type, self::LEAGUE_TYPES, true ) ? $type : 'points';
	}

	/**
	 * Whether a membership status is valid (pure).
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_valid_membership_status( $status ) {
		return in_array( $status, self::MEMBERSHIP_STATUSES, true );
	}

	/**
	 * Validate league input (pure).
	 *
	 * @param array $data Raw league data.
	 * @return true|WP_Error
	 */
	public static function validate_league( $data ) {
		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'League name is required', 'poker-tournament-import' ) );
		}

		if ( isset( $data['max_players'] ) && (int) $data['max_players'] < 0 ) {
			return new WP_Error( 'invalid_max_players', __( 'Max players cannot be negative', 'poker-tournament-import' ) );
		}

		return true;
	}

	// ---- Leagues -------------------------------------------------------

	/**
	 * Create a league.
	 *
	 * @param array $data League data.
	 * @return int|WP_Error New league ID or error.
	 */
	public static function create_league( $data ) {
		global $wpdb;

		$valid = self::validate_league( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $wpdb->insert(
			self::table( 'leagues' ),
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'league_type' => self::sanitize_league_type( isset( $data['league_type'] ) ? $data['league_type'] : 'points' ),
				'is_active'   => empty( $data['is_active'] ) ? 0 : 1,
				'is_private'  => empty( $data['is_private'] ) ? 0 : 1,
				'max_players' => isset( $data['max_players'] ) ? absint( $data['max_players'] ) : 0,
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return false === $inserted ? new WP_Error( 'insert_failed', __( 'Failed to create league', 'poker-tournament-import' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Update a league.
	 *
	 * @param int   $league_id League ID.
	 * @param array $data      Fields to update.
	 * @return true|WP_Error
	 */
	public static function update_league( $league_id, $data ) {
		global $wpdb;

		$valid = self::validate_league( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$wpdb->update(
			self::table( 'leagues' ),
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'league_type' => self::sanitize_league_type( isset( $data['league_type'] ) ? $data['league_type'] : 'points' ),
				'is_active'   => empty( $data['is_active'] ) ? 0 : 1,
				'is_private'  => empty( $data['is_private'] ) ? 0 : 1,
				'max_players' => isset( $data['max_players'] ) ? absint( $data['max_players'] ) : 0,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $league_id ) ),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get a league row.
	 *
	 * @param int $league_id League ID.
	 * @return array|null
	 */
	public static function get_league( $league_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'leagues' ) . ' WHERE id = %d', absint( $league_id ) ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * List leagues.
	 *
	 * @param bool $active_only Only active leagues.
	 * @return array[]
	 */
	public static function get_leagues( $active_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'leagues' );
		if ( $active_only ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY name ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a league and its seasons + memberships.
	 *
	 * @param int $league_id League ID.
	 * @return true
	 */
	public static function delete_league( $league_id ) {
		global $wpdb;
		$league_id = absint( $league_id );
		$wpdb->delete( self::table( 'league_memberships' ), array( 'league_id' => $league_id ), array( '%d' ) );
		$wpdb->delete( self::table( 'seasons' ), array( 'league_id' => $league_id ), array( '%d' ) );
		$wpdb->delete( self::table( 'leagues' ), array( 'id' => $league_id ), array( '%d' ) );
		return true;
	}

	// ---- Seasons -------------------------------------------------------

	/**
	 * Create a season under a league.
	 *
	 * @param int   $league_id League ID.
	 * @param array $data      Season data (name required).
	 * @return int|WP_Error
	 */
	public static function create_season( $league_id, $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Season name is required', 'poker-tournament-import' ) );
		}

		$inserted = $wpdb->insert(
			self::table( 'seasons' ),
			array(
				'league_id'   => absint( $league_id ),
				'name'        => sanitize_text_field( $name ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'is_active'   => empty( $data['is_active'] ) ? 0 : 1,
				'sort_order'  => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return false === $inserted ? new WP_Error( 'insert_failed', __( 'Failed to create season', 'poker-tournament-import' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Get the seasons of a league.
	 *
	 * @param int $league_id League ID.
	 * @return array[]
	 */
	public static function get_seasons( $league_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( 'seasons' ) . ' WHERE league_id = %d ORDER BY sort_order ASC, name ASC',
				absint( $league_id )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a season and its memberships.
	 *
	 * @param int $season_id Season ID.
	 * @return true
	 */
	public static function delete_season( $season_id ) {
		global $wpdb;
		$season_id = absint( $season_id );
		$wpdb->delete( self::table( 'league_memberships' ), array( 'season_id' => $season_id ), array( '%d' ) );
		$wpdb->delete( self::table( 'seasons' ), array( 'id' => $season_id ), array( '%d' ) );
		return true;
	}

	// ---- Memberships ---------------------------------------------------

	/**
	 * Add a player to a league (optionally a season). Idempotent on the
	 * unique (league, season, player) key.
	 *
	 * @param int $league_id League ID.
	 * @param int $player_id Player ID.
	 * @param int $season_id Season ID (0 for league-wide).
	 * @return int|WP_Error Membership ID, or error (incl. duplicate).
	 */
	public static function add_member( $league_id, $player_id, $season_id = 0 ) {
		global $wpdb;

		$league_id = absint( $league_id );
		$player_id = absint( $player_id );
		$season_id = absint( $season_id );

		if ( ! $league_id || ! $player_id ) {
			return new WP_Error( 'invalid_params', __( 'League and player are required', 'poker-tournament-import' ) );
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table( 'league_memberships' ) . ' WHERE league_id = %d AND season_id = %d AND player_id = %d',
				$league_id,
				$season_id,
				$player_id
			)
		);
		if ( $existing ) {
			return new WP_Error( 'duplicate_member', __( 'Player is already a member', 'poker-tournament-import' ) );
		}

		$inserted = $wpdb->insert(
			self::table( 'league_memberships' ),
			array(
				// Store 0 (not NULL) for league-wide members so the dup-check and
				// the UNIQUE (league_id, season_id, player_id) key both apply —
				// MySQL treats NULLs as distinct for unique constraints.
				'league_id'         => $league_id,
				'season_id'         => $season_id,
				'player_id'         => $player_id,
				'membership_status' => 'active',
				'join_date'         => current_time( 'mysql' ),
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? new WP_Error( 'insert_failed', __( 'Failed to add member', 'poker-tournament-import' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Remove a membership by ID.
	 *
	 * @param int $membership_id Membership row ID.
	 * @return true
	 */
	public static function remove_member( $membership_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'league_memberships' ), array( 'id' => absint( $membership_id ) ), array( '%d' ) );
		return true;
	}

	/**
	 * Set a membership's status.
	 *
	 * @param int    $membership_id Membership row ID.
	 * @param string $status        New status.
	 * @return true|WP_Error
	 */
	public static function set_member_status( $membership_id, $status ) {
		global $wpdb;

		$status = sanitize_text_field( $status );
		if ( ! self::is_valid_membership_status( $status ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid membership status', 'poker-tournament-import' ) );
		}

		$wpdb->update(
			self::table( 'league_memberships' ),
			array(
				'membership_status' => $status,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => absint( $membership_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get a league/season's members.
	 *
	 * @param int $league_id League ID.
	 * @param int $season_id Season ID (0 = league-wide rows).
	 * @return array[]
	 */
	public static function get_members( $league_id, $season_id = 0 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( 'league_memberships' ) . ' WHERE league_id = %d AND season_id = %d ORDER BY total_points DESC',
				absint( $league_id ),
				absint( $season_id )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
