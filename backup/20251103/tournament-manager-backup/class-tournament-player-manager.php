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

		// Get tournament settings for chip count and bounty
		$starting_chips = (int) get_post_meta( $tournament_id, '_starting_chips', true );
		$bounty_type    = get_post_meta( $tournament_id, '_bounty_type', true );
		$bounty_amount  = (float) get_post_meta( $tournament_id, '_bounty_amount', true );

		// Set default starting chips if not configured
		if ( ! $starting_chips ) {
			$starting_chips = 10000;
		}

		// Set bounty amount for PKO tournaments
		$player_bounty = 0;
		if ( 'none' !== $bounty_type && $bounty_amount > 0 ) {
			$player_bounty = $bounty_amount;
		}

		// Parse args.
		$defaults = array(
			'status'            => 'registered',
			'paid_amount'       => 0,
			'chip_count'        => $starting_chips,
			'bounty_amount'     => $player_bounty,
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
				'chip_count'      => intval( $data['chip_count'] ),
				'bounty_amount'   => floatval( $data['bounty_amount'] ),
				'rebuys_count'    => intval( $data['rebuys_count'] ),
				'addons_count'    => intval( $data['addons_count'] ),
				'seat_assignment' => $data['seat_assignment'] ? sanitize_text_field( $data['seat_assignment'] ) : null,
				'finish_position' => $data['finish_position'] ? intval( $data['finish_position'] ) : null,
				'prize_amount'    => floatval( $data['prize_amount'] ),
				'notes'           => wp_kses_post( $data['notes'] ),
			),
			array( '%d', '%d', '%s', '%f', '%d', '%f', '%d', '%d', '%s', '%d', '%f', '%s' )
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
	 * Bust player - Mark player as eliminated (Phase 2 Beta22)
	 *
	 * @since 3.1.0
	 * @param int      $tournament_id Tournament ID.
	 * @param int      $player_id Player ID.
	 * @param int      $entry_number Entry number (for re-entries).
	 * @param int|null $eliminated_by_id Player ID of eliminator (for bounties).
	 * @return array|WP_Error Success data or error
	 */
	public static function bust_player( $tournament_id, $player_id, $entry_number = 1, $eliminated_by_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Validate tournament is running (Beta 22.3)
		$state = TDWP_Live_State_Manager::get_state( $tournament_id );
		if ( ! $state || ! in_array( $state->status, array( 'running', 'paused', 'break' ), true ) ) {
			return new WP_Error(
				'tournament_not_running',
				__( 'Can only eliminate players when tournament is running', 'poker-tournament-import' )
			);
		}

		// Get tournament financial policy
		$allow_reentry         = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
		$reentry_until_level   = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );
		$bounty_type           = get_post_meta( $tournament_id, '_bounty_type', true );
		$bounty_amount         = (float) get_post_meta( $tournament_id, '_bounty_amount', true );
		$bounty_percentage     = (float) get_post_meta( $tournament_id, '_bounty_percentage', true );

		// Get current level from state (already fetched above)
		$current_level = $state->current_level;

		// Check if re-entry is available
		$can_reentry = false;
		if ( $allow_reentry && ( 0 === $reentry_until_level || $current_level <= $reentry_until_level ) ) {
			$can_reentry = true;
		}

		// Get player's current entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d AND entry_number = %d",
				$tournament_id,
				$player_id,
				$entry_number
			)
		);

		if ( ! $entry ) {
			return new WP_Error( 'invalid_entry', __( 'Player entry not found', 'poker-tournament-import' ) );
		}

		// Count active players to calculate finish position
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE tournament_id = %d AND status IN ('registered', 'paid', 'active')",
				$tournament_id
			)
		);

		// Calculate finish position (only if NOT eligible for re-entry)
		$finish_position = null;
		if ( ! $can_reentry ) {
			$finish_position = (int) $active_count;
		}

		// Prepare update data
		$update_data = array(
			'status'                  => 'eliminated',
			'elimination_time'        => current_time( 'mysql' ),
			'eliminations_count'      => (int) $entry->eliminations_count + 1,
		);

		if ( $eliminated_by_id ) {
			$update_data['eliminated_by_player_id'] = $eliminated_by_id;
		}

		// Only set finish position if NOT eligible for re-entry
		if ( null !== $finish_position ) {
			$update_data['finish_position'] = $finish_position;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$update_data,
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
				'entry_number'  => $entry_number,
			),
			array( '%s', '%s', '%d', '%d', '%d' ),
			array( '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update player status', 'poker-tournament-import' ) );
		}

		// Unseat eliminated player immediately (Beta 22.2)
		TDWP_Seat_Manager::unseat_player( $player_id );

		// Process bounty if applicable
		$bounty_earned = 0;
		if ( $eliminated_by_id && 'none' !== $bounty_type && $bounty_amount > 0 ) {
			$bounty_earned = self::award_bounty(
				$tournament_id,
				$entry->player_id,
				$eliminated_by_id,
				$bounty_type,
				$entry->bounty_amount > 0 ? $entry->bounty_amount : $bounty_amount,
				$bounty_percentage
			);
		}

		// Check tournament completion: 1 player remaining (Beta 22.2)
		$tournament_completed = false;
		$remaining            = $active_count - 1;
		if ( 1 === $remaining && ! $can_reentry ) {
			// Get the last remaining player
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$winner = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE tournament_id = %d AND status IN ('active', 'paid', 'checked_in') LIMIT 1",
					$tournament_id
				)
			);

			if ( $winner ) {
				// Set winner's finish position = 1
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$table,
					array( 'finish_position' => 1 ),
					array(
						'tournament_id' => $tournament_id,
						'player_id'     => $winner->player_id,
						'entry_number'  => $winner->entry_number,
					),
					array( '%d' ),
					array( '%d', '%d', '%d' )
				);

				// Mark tournament as finished
				TDWP_Live_State_Manager::finish( $tournament_id );
				$tournament_completed = true;
			}
		}

		// Get player post for name
		$player_post = get_post( $player_id );
		$player_name = $player_post ? $player_post->post_title : "Player #{$player_id}";

		return array(
			'message'              => sprintf(
				/* translators: %s: player name */
				__( '%s has been eliminated', 'poker-tournament-import' ),
				$player_name
			),
			'can_reentry'          => $can_reentry,
			'finish_position'      => $finish_position,
			'bounty_earned'        => $bounty_earned,
			'remaining_count'      => $remaining,
			'tournament_completed' => $tournament_completed,
		);
	}

	/**
	 * Award bounty to eliminator (Phase 2 Beta24)
	 *
	 * @since 3.1.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $eliminated_id Eliminated player ID.
	 * @param int    $eliminator_id Eliminator player ID.
	 * @param string $bounty_type Bounty type (fixed/pko).
	 * @param float  $bounty_value Bounty amount.
	 * @param float  $bounty_percentage PKO split percentage.
	 * @return float Amount earned by eliminator
	 */
	private static function award_bounty( $tournament_id, $eliminated_id, $eliminator_id, $bounty_type, $bounty_value, $bounty_percentage ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get eliminator's latest active entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$eliminator_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d AND status IN ('registered', 'paid', 'active') ORDER BY entry_number DESC LIMIT 1",
				$tournament_id,
				$eliminator_id
			)
		);

		if ( ! $eliminator_entry ) {
			return 0;
		}

		$earned = 0;

		if ( 'fixed' === $bounty_type ) {
			// Fixed bounty: Full amount to eliminator
			$earned = $bounty_value;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'bounties_earned'       => $eliminator_entry->bounties_earned + $earned,
					'bounties_from_players' => (int) $eliminator_entry->bounties_from_players + 1,
				),
				array(
					'id' => $eliminator_entry->id,
				),
				array( '%f', '%d' ),
				array( '%d' )
			);
		} elseif ( 'pko' === $bounty_type ) {
			// PKO: Split by percentage
			$pct     = min( max( 0, $bounty_percentage ), 100 );
			$earned  = $bounty_value * ( $pct / 100 );
			$added   = $bounty_value - $earned;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'bounties_earned'       => $eliminator_entry->bounties_earned + $earned,
					'bounty_amount'         => $eliminator_entry->bounty_amount + $added,
					'bounties_from_players' => (int) $eliminator_entry->bounties_from_players + 1,
				),
				array(
					'id' => $eliminator_entry->id,
				),
				array( '%f', '%f', '%d' ),
				array( '%d' )
			);
		}

		return $earned;
	}

	/**
	 * Re-entry player (Phase 2 Beta23)
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id Player ID.
	 * @return array|WP_Error Success data or error
	 */
	public static function reentry_player( $tournament_id, $player_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get tournament re-entry policy
		$allow_reentry       = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
		$reentry_cost        = (float) get_post_meta( $tournament_id, '_reentry_cost', true );
		$reentry_chips       = (int) get_post_meta( $tournament_id, '_reentry_chips', true );
		$reentry_limit       = (int) get_post_meta( $tournament_id, '_reentry_limit', true );
		$reentry_until_level = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );
		$starting_chips      = (int) get_post_meta( $tournament_id, '_starting_chips', true );

		// Validate re-entry allowed
		if ( ! $allow_reentry ) {
			return new WP_Error( 'reentry_not_allowed', __( 'Re-entry is not allowed for this tournament', 'poker-tournament-import' ) );
		}

		// Check current level
		$state         = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level = $state ? $state->current_level : 1;

		if ( $reentry_until_level > 0 && $current_level > $reentry_until_level ) {
			return new WP_Error( 'reentry_closed', __( 'Re-entry period has ended', 'poker-tournament-import' ) );
		}

		// Get player's entries
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d ORDER BY entry_number ASC",
				$tournament_id,
				$player_id
			)
		);

		if ( empty( $entries ) ) {
			return new WP_Error( 'no_entries', __( 'Player has no entries in this tournament', 'poker-tournament-import' ) );
		}

		// Check re-entry limit
		$entry_count = count( $entries );
		if ( $reentry_limit > 0 && $entry_count >= ( $reentry_limit + 1 ) ) {
			return new WP_Error( 'reentry_limit_reached', __( 'Re-entry limit reached', 'poker-tournament-import' ) );
		}

		// Unseat ALL existing active registrations before creating new entry
		// This prevents duplicate seat assignments during re-entry
		foreach ( $entries as $entry ) {
			if ( in_array( $entry->status, array( 'active', 'paid', 'checked_in' ), true ) ) {
				if ( class_exists( 'TDWP_Seat_Manager' ) ) {
					TDWP_Seat_Manager::unseat_player( $player_id, $entry->id );
				}
			}
		}

		// Get original entry
		$original_entry_id = $entries[0]->id;
		$new_entry_number  = $entry_count + 1;

		// Use reentry_chips if set, otherwise starting_chips
		$chips = $reentry_chips > 0 ? $reentry_chips : $starting_chips;

		// Insert new entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'tournament_id'     => $tournament_id,
				'player_id'         => $player_id,
				'entry_number'      => $new_entry_number,
				'is_reentry'        => 1,
				'original_entry_id' => $original_entry_id,
				'status'            => 'active',
				'paid_amount'       => $reentry_cost,
				'chip_count'        => $chips,
				'registration_date' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%f', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create re-entry', 'poker-tournament-import' ) );
		}

		// Update original entry's reentry_count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'reentry_count' => $entry_count ),
			array( 'id' => $original_entry_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Try to auto-seat
		TDWP_Seat_Manager::auto_seat_player( $player_id, $tournament_id );

		// Get player name
		$player_post = get_post( $player_id );
		$player_name = $player_post ? $player_post->post_title : "Player #{$player_id}";

		return array(
			'message'      => sprintf(
				/* translators: %s: player name */
				__( '%s has re-entered', 'poker-tournament-import' ),
				$player_name
			),
			'entry_number' => $new_entry_number,
			'chip_count'   => $chips,
		);
	}

	/**
	 * Process declined re-entry and player withdrawal
	 *
	 * This method handles when a player is offered re-entry but declines,
	 * resulting in withdrawal from the tournament.
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $player_id     Player ID.
	 * @param string $withdrawal_reason Reason for declining re-entry (optional).
	 * @return array|WP_Error Success data or error
	 */
	public static function process_declined_reentry( $tournament_id, $player_id, $withdrawal_reason = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get player's most recent eliminated entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE tournament_id = %d AND player_id = %d AND status = 'eliminated'
				ORDER BY elimination_timestamp DESC, id DESC LIMIT 1",
				$tournament_id,
				$player_id
			)
		);

		if ( ! $latest_entry ) {
			return new WP_Error( 'no_eliminated_entry', __( 'No eliminated entry found for player', 'poker-tournament-import' ) );
		}

		// Check if re-entry is still available for this player
		$allow_reentry       = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
		$reentry_until_level = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );
		$state               = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level       = $state ? $state->current_level : 1;

		$can_reentry = $allow_reentry && ( 0 === $reentry_until_level || $current_level <= $reentry_until_level );

		if ( ! $can_reentry ) {
			return new WP_Error( 'reentry_not_available', __( 'Re-entry is not available', 'poker-tournament-import' ) );
		}

		// Update the latest entry to mark as declined re-entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'withdrawal_status'   => 'declined_reentry',
				'withdrawal_timestamp' => current_time( 'mysql' ),
				'elimination_reason'  => 'declined_reentry',
			),
			array( 'id' => $latest_entry->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to process declined re-entry', 'poker-tournament-import' ) );
		}

		// Get player name for logging
		$player_post = get_post( $player_id );
		$player_name = $player_post ? $player_post->post_title : "Player #{$player_id}";

		// Log the declined re-entry transaction
		$transaction_id = TDWP_Transaction_Logger::log_withdrawal(
			$tournament_id,
			$player_id,
			'declined_reentry',
			$withdrawal_reason ?: 'Player declined re-entry offer',
			$latest_entry->finish_position,
			'declined_reentry'
		);

		if ( ! $transaction_id ) {
			error_log( "Failed to log declined re-entry transaction for tournament {$tournament_id}, player {$player_id}" );
		}

		// Update live state to reflect one less eligible player
		if ( class_exists( 'TDWP_Live_State_Manager' ) ) {
			$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$live_state_table}
					SET eligible_players = GREATEST(eligible_players - 1, 0),
					    updated_at = %s
					WHERE tournament_id = %d",
					current_time( 'mysql' ),
					$tournament_id
				)
			);
		}

		// Fire action for declined re-entry
		do_action( 'tdwp_player_declined_reentry', $tournament_id, $player_id, $latest_entry->id, $withdrawal_reason );

		return array(
			'message'       => sprintf(
				/* translators: %s: player name */
				__( '%s declined re-entry and has withdrawn from the tournament', 'poker-tournament-import' ),
				$player_name
			),
			'entry_id'      => $latest_entry->id,
			'finish_position' => $latest_entry->finish_position,
			'transaction_id' => $transaction_id,
		);
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

	/**
	 * Get players eligible for re-entry
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Array of player objects eligible for re-entry
	 */
	public static function get_reentry_eligible_players( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get tournament re-entry policy
		$allow_reentry       = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
		$reentry_until_level = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );
		$state               = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level       = $state ? $state->current_level : 1;

		// Check if re-entry is currently allowed
		$can_reentry = $allow_reentry && ( 0 === $reentry_until_level || $current_level <= $reentry_until_level );
		if ( ! $can_reentry ) {
			return array();
		}

		// Get eliminated players who haven't withdrawn or re-entered yet
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, post.post_title as player_name
				FROM {$table} p
				LEFT JOIN {$wpdb->posts} post ON p.player_id = post.ID
				WHERE p.tournament_id = %d
				  AND p.status = 'eliminated'
				  AND p.withdrawal_status = 'active'
				  AND p.elimination_reason != 'disqualified'
				ORDER BY p.elimination_timestamp DESC",
				$tournament_id
			)
		);

		return $players;
	}

	/**
	 * Get players who have declined re-entry
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Array of player objects who declined re-entry
	 */
	public static function get_declined_reentry_players( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, post.post_title as player_name
				FROM {$table} p
				LEFT JOIN {$wpdb->posts} post ON p.player_id = post.ID
				WHERE p.tournament_id = %d
				  AND p.withdrawal_status = 'declined_reentry'
				ORDER BY p.withdrawal_timestamp DESC",
				$tournament_id
			)
		);

		return $players;
	}

	/**
	 * Update player withdrawal status
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id     Tournament ID.
	 * @param int    $player_id         Player ID.
	 * @param string $withdrawal_status New withdrawal status ('active', 'withdrawn', 'declined_reentry').
	 * @param string $reason            Reason for status change (optional).
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function update_withdrawal_status( $tournament_id, $player_id, $withdrawal_status, $reason = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Validate withdrawal status
		$valid_statuses = array( 'active', 'withdrawn', 'declined_reentry' );
		if ( ! in_array( $withdrawal_status, $valid_statuses, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid withdrawal status', 'poker-tournament-import' ) );
		}

		// Get current player entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d AND status IN ('active', 'eliminated', 'paid') ORDER BY id DESC LIMIT 1",
				$tournament_id,
				$player_id
			)
		);

		if ( ! $current_entry ) {
			return new WP_Error( 'no_entry_found', __( 'No active entry found for player', 'poker-tournament-import' ) );
		}

		// Prepare update data
		$update_data = array(
			'withdrawal_status' => $withdrawal_status,
		);

		// Add withdrawal timestamp if withdrawing
		if ( in_array( $withdrawal_status, array( 'withdrawn', 'declined_reentry' ), true ) ) {
			$update_data['withdrawal_timestamp'] = current_time( 'mysql' );
		}

		// Add elimination reason for declined re-entry
		if ( 'declined_reentry' === $withdrawal_status ) {
			$update_data['elimination_reason'] = 'declined_reentry';
		}

		// Update the entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $current_entry->id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update withdrawal status', 'poker-tournament-import' ) );
		}

		// Fire action for status change
		do_action( 'tdwp_withdrawal_status_updated', $tournament_id, $player_id, $current_entry->id, $withdrawal_status, $current_entry->withdrawal_status, $reason );

		return true;
	}

	/**
	 * Get player withdrawal status
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id     Player ID.
	 * @return object|null Player entry with withdrawal status or null if not found
	 */
	public static function get_player_withdrawal_status( $tournament_id, $player_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT withdrawal_status, withdrawal_timestamp, elimination_reason, finish_position, status
				FROM {$table}
				WHERE tournament_id = %d AND player_id = %d
				ORDER BY id DESC LIMIT 1",
				$tournament_id,
				$player_id
			)
		);

		return $entry;
	}

	/**
	 * Get all withdrawn players for a tournament
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id Tournament ID.
	 * @param string $status_filter  Filter by specific withdrawal status (optional).
	 * @return array Array of withdrawn player objects
	 */
	public static function get_withdrawn_players( $tournament_id, $status_filter = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		$where_clause = "p.tournament_id = %d AND p.withdrawal_status != 'active'";
		$params = array( $tournament_id );

		if ( ! empty( $status_filter ) ) {
			$where_clause .= " AND p.withdrawal_status = %s";
			$params[] = $status_filter;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, post.post_title as player_name
				FROM {$table} p
				LEFT JOIN {$wpdb->posts} post ON p.player_id = post.ID
				WHERE {$where_clause}
				ORDER BY p.withdrawal_timestamp DESC, p.elimination_timestamp DESC",
				$params
			)
		);

		return $players;
	}

	/**
	 * Get withdrawal statistics for a tournament
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Withdrawal statistics
	 */
	public static function get_withdrawal_statistics( $tournament_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					withdrawal_status,
					COUNT(*) as count,
					AVG(finish_position) as avg_finish_position
				FROM {$table}
				WHERE tournament_id = %d AND withdrawal_status != 'active'
				GROUP BY withdrawal_status",
				$tournament_id
			)
		);

		$statistics = array(
			'total_withdrawn' => 0,
			'voluntary_withdrawals' => 0,
			'declined_reentries' => 0,
			'avg_finish_position' => null,
		);

		foreach ( $stats as $stat ) {
			$statistics['total_withdrawn'] += $stat->count;

			if ( 'withdrawn' === $stat->withdrawal_status ) {
				$statistics['voluntary_withdrawals'] = $stat->count;
			} elseif ( 'declined_reentry' === $stat->withdrawal_status ) {
				$statistics['declined_reentries'] = $stat->count;
			}

			if ( $stat->avg_finish_position ) {
				$statistics['avg_finish_position'] = round( $stat->avg_finish_position, 1 );
			}
		}

		return $statistics;
	}

	/**
	 * Process rebuy (Phase 2 Beta25)
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id Player ID.
	 * @param int $entry_number Entry number.
	 * @return array|WP_Error Success data or error
	 */
	public static function process_rebuy( $tournament_id, $player_id, $entry_number = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get tournament rebuy policy
		$rebuy_cost           = (float) get_post_meta( $tournament_id, '_rebuy_cost', true );
		$rebuy_chips          = (int) get_post_meta( $tournament_id, '_rebuy_chips', true );
		$rebuy_until_level    = (int) get_post_meta( $tournament_id, '_rebuy_until_level', true );
		$rebuy_limit_per_player = (int) get_post_meta( $tournament_id, '_rebuy_limit_per_player', true );

		// Check if rebuys allowed
		if ( $rebuy_until_level <= 0 ) {
			return new WP_Error( 'rebuy_not_allowed', __( 'Rebuys are not allowed for this tournament', 'poker-tournament-import' ) );
		}

		// Check current level
		$state         = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level = $state ? $state->current_level : 1;

		if ( $current_level > $rebuy_until_level ) {
			return new WP_Error( 'rebuy_period_ended', __( 'Rebuy period has ended', 'poker-tournament-import' ) );
		}

		// Get player entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d AND entry_number = %d",
				$tournament_id,
				$player_id,
				$entry_number
			)
		);

		if ( ! $entry ) {
			return new WP_Error( 'entry_not_found', __( 'Player entry not found', 'poker-tournament-import' ) );
		}

		// Check rebuy limit
		if ( $rebuy_limit_per_player > 0 && $entry->rebuys_count >= $rebuy_limit_per_player ) {
			return new WP_Error( 'rebuy_limit_reached', __( 'Rebuy limit reached', 'poker-tournament-import' ) );
		}

		// Process rebuy
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'rebuys_count' => $entry->rebuys_count + 1,
				'paid_amount'  => $entry->paid_amount + $rebuy_cost,
				'chip_count'   => $entry->chip_count + $rebuy_chips,
			),
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
				'entry_number'  => $entry_number,
			),
			array( '%d', '%f', '%d' ),
			array( '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to process rebuy', 'poker-tournament-import' ) );
		}

		return array(
			'message'       => __( 'Rebuy processed successfully', 'poker-tournament-import' ),
			'rebuys_count'  => $entry->rebuys_count + 1,
			'chip_count'    => $entry->chip_count + $rebuy_chips,
			'paid_amount'   => $entry->paid_amount + $rebuy_cost,
		);
	}

	/**
	 * Process addon (Phase 2 Beta25)
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id Player ID.
	 * @param int $entry_number Entry number.
	 * @return array|WP_Error Success data or error
	 */
	public static function process_addon( $tournament_id, $player_id, $entry_number = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// Get tournament addon policy
		$addon_cost        = (float) get_post_meta( $tournament_id, '_addon_cost', true );
		$addon_chips       = (int) get_post_meta( $tournament_id, '_addon_chips', true );
		$addon_at_level    = (int) get_post_meta( $tournament_id, '_addon_at_level', true );
		$addon_until_level = (int) get_post_meta( $tournament_id, '_addon_until_level', true );

		// Check if addons allowed
		if ( $addon_at_level <= 0 ) {
			return new WP_Error( 'addon_not_allowed', __( 'Add-ons are not allowed for this tournament', 'poker-tournament-import' ) );
		}

		// Check current level
		$state         = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level = $state ? $state->current_level : 1;

		if ( $current_level < $addon_at_level ) {
			return new WP_Error( 'addon_not_yet', __( 'Add-on not yet available', 'poker-tournament-import' ) );
		}

		if ( $addon_until_level > 0 && $current_level > $addon_until_level ) {
			return new WP_Error( 'addon_period_ended', __( 'Add-on period has ended', 'poker-tournament-import' ) );
		}

		// Get player entry
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND player_id = %d AND entry_number = %d",
				$tournament_id,
				$player_id,
				$entry_number
			)
		);

		if ( ! $entry ) {
			return new WP_Error( 'entry_not_found', __( 'Player entry not found', 'poker-tournament-import' ) );
		}

		// Check if addon already taken (usually only 1 per player)
		if ( $entry->addons_count > 0 ) {
			return new WP_Error( 'addon_already_taken', __( 'Add-on already taken', 'poker-tournament-import' ) );
		}

		// Process addon
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'addons_count' => $entry->addons_count + 1,
				'paid_amount'  => $entry->paid_amount + $addon_cost,
				'chip_count'   => $entry->chip_count + $addon_chips,
			),
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
				'entry_number'  => $entry_number,
			),
			array( '%d', '%f', '%d' ),
			array( '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to process add-on', 'poker-tournament-import' ) );
		}

		return array(
			'message'      => __( 'Add-on processed successfully', 'poker-tournament-import' ),
			'addons_count' => $entry->addons_count + 1,
			'chip_count'   => $entry->chip_count + $addon_chips,
			'paid_amount'  => $entry->paid_amount + $addon_cost,
		);
	}

	/**
	 * Update chip count (Phase 2 Beta26)
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id Player ID.
	 * @param int $entry_number Entry number.
	 * @param int $chip_count New chip count.
	 * @return bool Success
	 */
	public static function update_chip_count( $tournament_id, $player_id, $entry_number, $chip_count ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'chip_count' => max( 0, (int) $chip_count ) ),
			array(
				'tournament_id' => $tournament_id,
				'player_id'     => $player_id,
				'entry_number'  => $entry_number,
			),
			array( '%d' ),
			array( '%d', '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update tournament status management for enhanced bustout tracking
	 *
	 * Handles tournament status transitions including:
	 * - Setting tournament to 'completed' when winner is determined
	 * - Finalizing finish positions for all eliminated players
	 * - Calculating final standings and prize distributions
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @param string $new_status New tournament status ('completed', 'paused', etc.).
	 * @return bool True on success
	 */
	public static function update_tournament_status( $tournament_id, $new_status = 'completed' ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$new_status = sanitize_text_field( $new_status );

		if ( ! $tournament_id ) {
			return false;
		}

		// Validate tournament exists
		$tournament = get_post( $tournament_id );
		if ( ! $tournament || 'live_tournament' !== get_post_type( $tournament_id ) ) {
			return false;
		}

		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		// Update tournament live state
		$update_data = array(
			'status' => $new_status,
			'updated_at' => current_time( 'mysql' ),
		);

		// Add completion timestamp if completing tournament
		if ( 'completed' === $new_status ) {
			$update_data['completed_at'] = current_time( 'mysql' );
		}

		$updated = $wpdb->update(
			$live_state_table,
			$update_data,
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		// If tournament is completed, finalize player statuses
		if ( 'completed' === $new_status ) {
			// Mark any remaining active players as winner (position 1)
			$wpdb->update(
				$players_table,
				array(
					'finish_position' => 1,
					'elimination_reason' => 'winner',
					'status' => 'completed',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'tournament_id' => $tournament_id,
					'status' => 'active',
				),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);

			// Update all eliminated players to 'completed' status
			$wpdb->update(
				$players_table,
				array(
					'status' => 'completed',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'tournament_id' => $tournament_id,
					'status' => 'eliminated',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			// Update registered players to 'completed' (no-shows)
			$wpdb->update(
				$players_table,
				array(
					'status' => 'completed',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'tournament_id' => $tournament_id,
					'status' => 'registered',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);
		}

		/**
		 * Fires after tournament status is updated
		 *
		 * @since 3.3.0
		 * @param int    $tournament_id Tournament ID.
		 * @param string $new_status     New tournament status.
		 * @param string $old_status     Previous tournament status.
		 */
		do_action( 'tdwp_tournament_status_updated', $tournament_id, $new_status, $tournament->post_status );

		return true;
	}

	/**
	 * Get tournament final standings
	 *
	 * Returns players ordered by finish position for completed tournaments
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Players in finish order
	 */
	public static function get_final_standings( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tp.*, p.post_title as player_name
				FROM {$players_table} tp
				INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				WHERE tp.tournament_id = %d
				AND tp.finish_position IS NOT NULL
				ORDER BY tp.finish_position ASC",
				$tournament_id
			)
		);

		return $players;
	}

	/**
	 * Get bustout timeline for tournament
	 *
	 * Returns detailed timeline of eliminations with timestamps and positions
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Bustout timeline entries
	 */
	public static function get_bustout_timeline( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$bustouts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tp.*, p.post_title as player_name
				FROM {$players_table} tp
				INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				WHERE tp.tournament_id = %d
				AND tp.bustout_timestamp IS NOT NULL
				ORDER BY tp.bustout_timestamp ASC",
				$tournament_id
			)
		);

		return $bustouts;
	}
}
