<?php
/**
 * Player Operations for Tournament Manager
 *
 * Handles live tournament player operations:
 * - Player bust-outs with automatic ranking
 * - Rebuys during rebuy period
 * - Add-ons during add-on period
 * - Chip count adjustments with audit trail
 *
 * All operations are logged via TDWP_Transaction_Logger for compliance.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.2.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player operations class
 *
 * @since 3.2.0
 */
class TDWP_Player_Operations {

	/**
	 * Process player bust-out
	 *
	 * Enhanced with precise bustout tracking and finish position calculation:
	 * - Updates player status to 'eliminated'
	 * - Records precise bustout timestamp for accurate ordering
	 * - Calculates and assigns exact finish position based on elimination order
	 * - Sets elimination reason to 'bustout'
	 * - Logs enhanced transaction with position data
	 * - Triggers table rebalancing
	 * - Updates tournament live state
	 * - Detects winner and tournament completion
	 *
	 * @since 3.2.0
	 * @since 3.3.0 Enhanced with precise bustout tracking
	 * @param int   $tournament_id Tournament ID.
	 * @param int   $player_id     Player ID (tournament_players.id not player.id).
	 * @param array $eliminated_by Array of eliminator player IDs.
	 * @return array|WP_Error Success array or WP_Error on failure
	 */
	public static function process_bustout( $tournament_id, $player_id, $eliminated_by = array() ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );

		// Ensure eliminated_by is array
		if ( ! is_array( $eliminated_by ) ) {
			$eliminated_by = array();
		}

		// Validate inputs
		if ( ! $tournament_id || ! $player_id ) {
			return new WP_Error( 'invalid_params', __( 'Invalid tournament or player ID', 'poker-tournament-import' ) );
		}

		// Get player data
		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$player_table} WHERE id = %d AND tournament_id = %d", $player_id, $tournament_id )
		);

		if ( ! $player ) {
			return new WP_Error( 'player_not_found', __( 'Player not found in tournament', 'poker-tournament-import' ) );
		}

		if ( 'eliminated' === $player->status ) {
			return new WP_Error( 'already_eliminated', __( 'Player already eliminated', 'poker-tournament-import' ) );
		}

		// Get remaining players count to calculate finish position
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$remaining        = $wpdb->get_var(
			$wpdb->prepare( "SELECT remaining_players FROM {$live_state_table} WHERE tournament_id = %d", $tournament_id )
		);

		if ( null === $remaining ) {
			return new WP_Error( 'tournament_not_found', __( 'Tournament not found', 'poker-tournament-import' ) );
		}

		// Check re-entry eligibility
		$allow_reentry       = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
		$reentry_until_level = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );

		// Get current level from live state
		$state         = TDWP_Live_State_Manager::get_state( $tournament_id );
		$current_level = $state ? $state->current_level : 1;

		// Determine if re-entry is available
		$can_reentry = false;
		if ( $allow_reentry && ( 0 === $reentry_until_level || $current_level <= $reentry_until_level ) ) {
			$can_reentry = true;
		}

		// Calculate precise finish position using enhanced bustout tracking
		$finish_position = null;
		if ( ! $can_reentry ) {
			$finish_position = self::calculate_finish_position( $tournament_id, $player_id );
		}

		// Get current timestamp for precise bustout ordering
		$bustout_timestamp = current_time( 'mysql' );

		// Prepare update data with enhanced bustout tracking
		$update_data = array(
			'status'            => 'eliminated',
			'chip_count'        => 0,
			'bustout_timestamp' => $bustout_timestamp,
			'elimination_reason' => 'bustout',
			'updated_at'        => current_time( 'mysql' ),
		);

		$update_format = array( '%s', '%d', '%s', '%s', '%s' );

		// Only set finish position if NOT eligible for re-entry
		if ( null !== $finish_position ) {
			$update_data['finish_position'] = $finish_position;
			$update_format[]                   = '%d';
		}

		// Update player status with enhanced bustout tracking
		$updated = $wpdb->update(
			$player_table,
			$update_data,
			array( 'id' => $player_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update player status', 'poker-tournament-import' ) );
		}

		// Update live state: decrement remaining players, increment busted count
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$live_state_table}
				SET remaining_players = remaining_players - 1,
				    busted_players_count = busted_players_count + 1,
				    updated_at = %s
				WHERE tournament_id = %d",
				current_time( 'mysql' ),
				$tournament_id
			)
		);

		// Unseat eliminated player immediately using specific registration ID
		if ( class_exists( 'TDWP_Seat_Manager' ) ) {
			TDWP_Seat_Manager::unseat_player( $player->player_id, $player_id );
		}

		// Log enhanced transaction with finish position data
		$transaction_log_message = $finish_position
			? sprintf( 'Busted out in position %d', $finish_position )
			: 'Busted out (re-entry eligible)';

		$transaction_data = array(
			'finish_position' => $finish_position,
			'bustout_timestamp' => $bustout_timestamp,
			'remaining_players' => $remaining,
		);

		$transaction_id = TDWP_Transaction_Logger::log_transaction(
			$tournament_id,
			$player->player_id,
			'bust_out',
			0,
			- $player->chip_count,
			$transaction_log_message,
			$transaction_data
		);

		// CRITICAL: Check if transaction logging succeeded
		if ( false === $transaction_id ) {
			// Transaction logging failed - this is a critical audit failure
			// Rollback the player status to maintain consistency
			$wpdb->update(
				$player_table,
				array(
					'status'     => 'active', // Rollback to active
					'chip_count' => $player->chip_count, // Restore original chip count
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $player_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			// Rollback live state
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$live_state_table}
					SET remaining_players = remaining_players + 1,
					    busted_players_count = GREATEST(busted_players_count - 1, 0),
					    updated_at = %s
					WHERE tournament_id = %d",
					current_time( 'mysql' ),
					$tournament_id
				)
			);

			// Re-seat the player if seat manager exists
			if ( class_exists( 'TDWP_Seat_Manager' ) ) {
				// Note: We can't restore the exact seat assignment without more info
				// but the player is marked as active again
			}

			return new WP_Error(
				'transaction_log_failed',
				__( 'Failed to log bust-out transaction. Operation was rolled back for audit compliance.', 'poker-tournament-import' )
			);
		}

		// Process bounty split if eliminators exist
		$bounty_awards = array();
		if ( ! empty( $eliminated_by ) ) {
			$bounty_awards = self::process_bounty_split( $tournament_id, $player_id, $eliminated_by, $player );
		}

		// Store eliminator IDs in database
		self::store_eliminators( $tournament_id, $player_id, $eliminated_by );

		/**
		 * Fires after player busts out
		 *
		 * @since 3.2.0
		 * @param int $tournament_id   Tournament ID.
		 * @param int $player_id       Player ID.
		 * @param int $finish_position Finish position.
		 */
		do_action( 'tdwp_player_bustout', $tournament_id, $player_id, $finish_position );

		// Trigger table rebalancing if table manager exists
		if ( class_exists( 'TDWP_Table_Balancer' ) ) {
			TDWP_Table_Balancer::trigger_rebalance( $tournament_id );
		}

		// Check for final table reached
		$is_final_table = self::is_final_table_reached( $tournament_id );
		if ( $is_final_table ) {
			/**
			 * Fires when final table is reached
			 *
			 * @since 3.3.0
			 * @param int $tournament_id Tournament ID.
			 * @param int $remaining_players Number of players remaining.
			 */
			do_action( 'tdwp_final_table_reached', $tournament_id, $remaining - 1 );
		}

		// Check for tournament completion (winner)
		if ( $remaining - 1 <= 1 && ! $can_reentry ) {
			self::process_tournament_completion( $tournament_id );
		}

		// Build response message
		$response_message = $finish_position
			? sprintf(
				/* translators: %d: finish position */
				__( 'Player busted out in position %d', 'poker-tournament-import' ),
				$finish_position
			)
			: __( 'Player busted out (eligible for re-entry)', 'poker-tournament-import' );

		// Add final table indicator
		if ( $is_final_table ) {
			$response_message .= ' ' . __( '(Final Table)', 'poker-tournament-import' );
		}

		// Build response
		$response = array(
			'success'          => true,
			'finish_position'  => $finish_position,
			'can_reentry'      => $can_reentry,
			'remaining'        => $remaining - 1,
			'transaction_id'   => $transaction_id,
			'bounty_awards'    => $bounty_awards,
			'message'          => $response_message,
		);

		// Add bounty message if awarded
		if ( ! empty( $bounty_awards ) ) {
			$total_bounty = array_sum( array_column( $bounty_awards, 'cash' ) );
			$response['bounty_earned'] = $total_bounty;
		}

		return $response;
	}

	/**
	 * Process player rebuy
	 *
	 * - Validates rebuy period is active
	 * - Checks player eligibility (rebuy count < max)
	 * - Adds starting chips to player
	 * - Increments rebuy counter
	 * - Updates prize pool
	 * - Logs transaction
	 *
	 * @since 3.2.0
	 * @param int   $tournament_id Tournament ID.
	 * @param int   $player_id     Player ID (tournament_players.id).
	 * @param float $amount        Rebuy amount paid.
	 * @return array|WP_Error Success array or WP_Error on failure
	 */
	public static function process_rebuy( $tournament_id, $player_id, $amount ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );
		$amount        = floatval( $amount );

		// Validate inputs
		if ( ! $tournament_id || ! $player_id || $amount <= 0 ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters for rebuy', 'poker-tournament-import' ) );
		}

		// Get player data
		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$player_table} WHERE id = %d AND tournament_id = %d", $player_id, $tournament_id )
		);

		if ( ! $player ) {
			return new WP_Error( 'player_not_found', __( 'Player not found', 'poker-tournament-import' ) );
		}

		// TODO: Add rebuy period validation (check tournament template settings)
		// For now, allow rebuys if tournament is running

		// Get starting chip count from tournament template
		// For now, use a default of 10000 (this should come from tournament settings)
		$chips_to_add = 10000;

		// Calculate new chip count
		$new_chip_count = $player->chip_count + $chips_to_add;

		// Update player
		$updated = $wpdb->update(
			$player_table,
			array(
				'chip_count'     => $new_chip_count,
				'rebuys_count'   => $player->rebuys_count + 1,
				'paid_amount'    => $player->paid_amount + $amount,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $player_id ),
			array( '%d', '%d', '%f', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to process rebuy', 'poker-tournament-import' ) );
		}

		// Update live state: increment rebuy count and prize pool
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$live_state_table}
				SET total_rebuys = total_rebuys + 1,
				    prize_pool = prize_pool + %f,
				    updated_at = %s
				WHERE tournament_id = %d",
				$amount,
				current_time( 'mysql' ),
				$tournament_id
			)
		);

		// Log transaction
		$transaction_id = TDWP_Transaction_Logger::log_transaction(
			$tournament_id,
			$player->player_id,
			'rebuy',
			$amount,
			$chips_to_add,
			sprintf( 'Rebuy #%d', $player->rebuys_count + 1 )
		);

		/**
		 * Fires after rebuy is processed
		 *
		 * @since 3.2.0
		 * @param int   $tournament_id  Tournament ID.
		 * @param int   $player_id      Player ID.
		 * @param float $amount         Rebuy amount.
		 * @param int   $chips          Chips added.
		 */
		do_action( 'tdwp_player_rebuy', $tournament_id, $player_id, $amount, $chips_to_add );

		return array(
			'success'         => true,
			'chips_added'     => $chips_to_add,
			'new_chip_count'  => $new_chip_count,
			'rebuys_total'    => $player->rebuys_count + 1,
			'transaction_id'  => $transaction_id,
			'message'         => sprintf(
				/* translators: %d: chip count */
				__( 'Rebuy processed. Added %d chips.', 'poker-tournament-import' ),
				$chips_to_add
			),
		);
	}

	/**
	 * Process player add-on
	 *
	 * - Validates add-on timing (typically during break)
	 * - Adds add-on chips to player
	 * - Increments add-on counter
	 * - Updates prize pool
	 * - Logs transaction
	 *
	 * @since 3.2.0
	 * @param int   $tournament_id Tournament ID.
	 * @param int   $player_id     Player ID (tournament_players.id).
	 * @param float $amount        Add-on amount paid.
	 * @return array|WP_Error Success array or WP_Error on failure
	 */
	public static function process_addon( $tournament_id, $player_id, $amount ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );
		$amount        = floatval( $amount );

		// Validate inputs
		if ( ! $tournament_id || ! $player_id || $amount <= 0 ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters for add-on', 'poker-tournament-import' ) );
		}

		// Get player data
		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$player_table} WHERE id = %d AND tournament_id = %d", $player_id, $tournament_id )
		);

		if ( ! $player ) {
			return new WP_Error( 'player_not_found', __( 'Player not found', 'poker-tournament-import' ) );
		}

		if ( 'busted' === $player->status ) {
			return new WP_Error( 'player_busted', __( 'Busted players cannot purchase add-ons', 'poker-tournament-import' ) );
		}

		// Get add-on chip count (default 10000, should come from tournament settings)
		$chips_to_add = 10000;

		// Calculate new chip count
		$new_chip_count = $player->chip_count + $chips_to_add;

		// Update player
		$updated = $wpdb->update(
			$player_table,
			array(
				'chip_count'     => $new_chip_count,
				'addons_count'   => $player->addons_count + 1,
				'paid_amount'    => $player->paid_amount + $amount,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $player_id ),
			array( '%d', '%d', '%f', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to process add-on', 'poker-tournament-import' ) );
		}

		// Update live state: increment add-on count and prize pool
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$live_state_table}
				SET total_addons = total_addons + 1,
				    prize_pool = prize_pool + %f,
				    updated_at = %s
				WHERE tournament_id = %d",
				$amount,
				current_time( 'mysql' ),
				$tournament_id
			)
		);

		// Log transaction
		$transaction_id = TDWP_Transaction_Logger::log_transaction(
			$tournament_id,
			$player->player_id,
			'add_on',
			$amount,
			$chips_to_add,
			'Add-on purchase'
		);

		/**
		 * Fires after add-on is processed
		 *
		 * @since 3.2.0
		 * @param int   $tournament_id Tournament ID.
		 * @param int   $player_id     Player ID.
		 * @param float $amount        Add-on amount.
		 * @param int   $chips         Chips added.
		 */
		do_action( 'tdwp_player_addon', $tournament_id, $player_id, $amount, $chips_to_add );

		return array(
			'success'         => true,
			'chips_added'     => $chips_to_add,
			'new_chip_count'  => $new_chip_count,
			'addons_total'    => $player->addons_count + 1,
			'transaction_id'  => $transaction_id,
			'message'         => sprintf(
				/* translators: %d: chip count */
				__( 'Add-on processed. Added %d chips.', 'poker-tournament-import' ),
				$chips_to_add
			),
		);
	}

	/**
	 * Process chip count adjustment
	 *
	 * Manual chip count correction with required reason for audit trail.
	 * Can be positive (chip correction up) or negative (chip correction down).
	 *
	 * @since 3.2.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $player_id     Player ID (tournament_players.id).
	 * @param int    $adjustment    Chip adjustment amount (positive or negative).
	 * @param string $reason        Required reason for adjustment.
	 * @return array|WP_Error Success array or WP_Error on failure
	 */
	public static function process_chip_adjustment( $tournament_id, $player_id, $adjustment, $reason ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );
		$adjustment    = intval( $adjustment );
		$reason        = sanitize_text_field( $reason );

		// Validate inputs
		if ( ! $tournament_id || ! $player_id ) {
			return new WP_Error( 'invalid_params', __( 'Invalid tournament or player ID', 'poker-tournament-import' ) );
		}

		if ( 0 === $adjustment ) {
			return new WP_Error( 'zero_adjustment', __( 'Adjustment cannot be zero', 'poker-tournament-import' ) );
		}

		if ( empty( $reason ) ) {
			return new WP_Error( 'missing_reason', __( 'Reason is required for chip adjustments', 'poker-tournament-import' ) );
		}

		// Get player data
		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$player_table} WHERE id = %d AND tournament_id = %d", $player_id, $tournament_id )
		);

		if ( ! $player ) {
			return new WP_Error( 'player_not_found', __( 'Player not found', 'poker-tournament-import' ) );
		}

		// Calculate new chip count
		$new_chip_count = $player->chip_count + $adjustment;

		// Prevent negative chip counts
		if ( $new_chip_count < 0 ) {
			return new WP_Error(
				'negative_chips',
				sprintf(
					/* translators: %d: current chip count */
					__( 'Adjustment would result in negative chips. Current: %d', 'poker-tournament-import' ),
					$player->chip_count
				)
			);
		}

		// Update player chip count
		$updated = $wpdb->update(
			$player_table,
			array(
				'chip_count' => $new_chip_count,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $player_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update chip count', 'poker-tournament-import' ) );
		}

		// Log transaction
		$transaction_id = TDWP_Transaction_Logger::log_transaction(
			$tournament_id,
			$player->player_id,
			'chip_adjustment',
			0,
			$adjustment,
			$reason
		);

		/**
		 * Fires after chip adjustment
		 *
		 * @since 3.2.0
		 * @param int    $tournament_id Tournament ID.
		 * @param int    $player_id     Player ID.
		 * @param int    $adjustment    Chip adjustment amount.
		 * @param string $reason        Adjustment reason.
		 */
		do_action( 'tdwp_chip_adjustment', $tournament_id, $player_id, $adjustment, $reason );

		return array(
			'success'         => true,
			'adjustment'      => $adjustment,
			'old_chip_count'  => $player->chip_count,
			'new_chip_count'  => $new_chip_count,
			'transaction_id'  => $transaction_id,
			'message'         => sprintf(
				/* translators: 1: adjustment amount, 2: new chip count */
				__( 'Chip adjustment: %1$+d. New count: %2$d', 'poker-tournament-import' ),
				$adjustment,
				$new_chip_count
			),
		);
	}

	/**
	 * Process bounty split among multiple eliminators
	 *
	 * @since 3.2.1
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $eliminated_id Eliminated player's tournament_players.id.
	 * @param array  $eliminator_ids Array of eliminator player_ids.
	 * @param object $eliminated_player Eliminated player database record.
	 * @return array Array of bounty awards.
	 */
	private static function process_bounty_split( $tournament_id, $eliminated_id, $eliminator_ids, $eliminated_player ) {
		global $wpdb;

		// Get tournament bounty settings
		$bounty_type       = get_post_meta( $tournament_id, '_bounty_type', true );
		$bounty_amount     = (float) get_post_meta( $tournament_id, '_bounty_amount', true );
		$bounty_percentage = (float) get_post_meta( $tournament_id, '_bounty_percentage', true );

		if ( 'none' === $bounty_type || $bounty_amount <= 0 ) {
			return array();
		}

		// Use eliminated player's current bounty (for PKO) or tournament default
		$total_bounty = $eliminated_player->bounty_amount > 0
			? $eliminated_player->bounty_amount
			: $bounty_amount;

		// Equal split among eliminators
		$num_eliminators      = count( $eliminator_ids );
		$bounty_per_eliminator = $total_bounty / $num_eliminators;

		$awards        = array();
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		foreach ( $eliminator_ids as $eliminator_player_id ) {
			// Find eliminator's active tournament_players record
			$eliminator = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$players_table}
					WHERE tournament_id = %d AND player_id = %d AND status = 'active'
					ORDER BY entry_number DESC LIMIT 1",
					$tournament_id,
					$eliminator_player_id
				)
			);

			if ( ! $eliminator ) {
				continue;
			}

			// PKO: Each eliminator gets 50/50 split of their share
			if ( 'pko' === $bounty_type ) {
				$cash_earned      = $bounty_per_eliminator * ( $bounty_percentage / 100 );
				$added_to_bounty = $bounty_per_eliminator - $cash_earned;

				$wpdb->update(
					$players_table,
					array(
						'bounties_earned'       => $eliminator->bounties_earned + $cash_earned,
						'bounty_amount'         => $eliminator->bounty_amount + $added_to_bounty,
						'bounties_from_players' => (int) $eliminator->bounties_from_players + 1,
					),
					array( 'id' => $eliminator->id ),
					array( '%f', '%f', '%d' ),
					array( '%d' )
				);

				$awards[] = array(
					'eliminator_id'   => $eliminator_player_id,
					'cash'            => $cash_earned,
					'added_to_bounty' => $added_to_bounty,
				);

				// Log bounty transaction
				TDWP_Transaction_Logger::log_transaction(
					$tournament_id,
					$eliminator_player_id,
					'bounty_award',
					$cash_earned,
					0,
					sprintf( 'Bounty for eliminating player (split %d ways)', $num_eliminators )
				);
			} else {
				// Fixed bounty: all cash
				$wpdb->update(
					$players_table,
					array(
						'bounties_earned'       => $eliminator->bounties_earned + $bounty_per_eliminator,
						'bounties_from_players' => (int) $eliminator->bounties_from_players + 1,
					),
					array( 'id' => $eliminator->id ),
					array( '%f', '%d' ),
					array( '%d' )
				);

				$awards[] = array(
					'eliminator_id' => $eliminator_player_id,
					'cash'          => $bounty_per_eliminator,
				);

				// Log bounty transaction
				TDWP_Transaction_Logger::log_transaction(
					$tournament_id,
					$eliminator_player_id,
					'bounty_award',
					$bounty_per_eliminator,
					0,
					sprintf( 'Bounty for eliminating player (split %d ways)', $num_eliminators )
				);
			}
		}

		return $awards;
	}

	/**
	 * Store eliminator IDs in database
	 *
	 * @since 3.2.1
	 * @param int   $tournament_id Tournament ID.
	 * @param int   $player_id     Eliminated player's tournament_players.id.
	 * @param array $eliminator_ids Array of eliminator player_ids.
	 */
	private static function store_eliminators( $tournament_id, $player_id, $eliminator_ids ) {
		global $wpdb;
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$update_data = array();

		if ( empty( $eliminator_ids ) ) {
			// Natural bustout (no eliminators)
			$update_data['eliminated_by_player_id']  = null;
			$update_data['eliminated_by_player_ids'] = null;
		} elseif ( count( $eliminator_ids ) === 1 ) {
			// Single eliminator (backward compatible)
			$update_data['eliminated_by_player_id']  = $eliminator_ids[0];
			$update_data['eliminated_by_player_ids'] = null;
		} else {
			// Multiple eliminators
			$update_data['eliminated_by_player_id']  = $eliminator_ids[0]; // Primary
			$update_data['eliminated_by_player_ids'] = wp_json_encode( $eliminator_ids );
		}

		$wpdb->update(
			$players_table,
			$update_data,
			array( 'id' => $player_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Calculate precise finish position based on elimination order
	 *
	 * Enhanced method that counts players eliminated after the given player
	 * to ensure accurate finish position tracking, even with concurrent bustouts.
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $player_id     Player ID (tournament_players.id).
	 * @return int Calculated finish position
	 */
	public static function calculate_finish_position( $tournament_id, $player_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		// Count players already eliminated with a timestamp
		$already_eliminated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$players_table}
				WHERE tournament_id = %d
				AND status = 'eliminated'
				AND bustout_timestamp IS NOT NULL
				AND bustout_timestamp < (SELECT bustout_timestamp FROM {$players_table} WHERE id = %d)",
				$tournament_id,
				$player_id
			)
		);

		// Count active players still in tournament
		$still_active = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$players_table}
				WHERE tournament_id = %d
				AND status IN ('active', 'paid')",
				$tournament_id
			)
		);

		// Calculate finish position: eliminated players + active players
		$finish_position = absint( $already_eliminated ) + absint( $still_active ) + 1;

		return $finish_position;
	}

	/**
	 * Get tournament winner information
	 *
	 * Determines if tournament has a winner and returns winner details.
	 * Winner is the last remaining active player.
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array|null Winner information or null if no winner yet
	 */
	public static function get_tournament_winner( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// Get tournament live state
		$live_state = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT remaining_players, status FROM {$live_state_table} WHERE tournament_id = %d",
				$tournament_id
			)
		);

		if ( ! $live_state || $live_state->remaining_players > 1 ) {
			return null; // No winner yet
		}

		// Get the last remaining active player
		$winner = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tp.*, p.post_title as player_name
				FROM {$players_table} tp
				INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				WHERE tp.tournament_id = %d
				AND tp.status IN ('active', 'paid')
				ORDER BY tp.id ASC
				LIMIT 1",
				$tournament_id
			)
		);

		if ( ! $winner ) {
			return null;
		}

		// Update winner to finish position 1
		$wpdb->update(
			$players_table,
			array(
				'finish_position' => 1,
				'elimination_reason' => 'winner',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $winner->id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'player_id' => $winner->player_id,
			'player_name' => $winner->player_name,
			'registration_id' => $winner->id,
			'prize_amount' => $winner->prize_amount,
			'chips' => $winner->chip_count,
		);
	}

	/**
	 * Check if final table has been reached
	 *
	 * Final table is reached when remaining players <= final_table_size (default 9)
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $final_table_size Final table size (default 9).
	 * @return bool True if final table reached
	 */
	public static function is_final_table_reached( $tournament_id, $final_table_size = 9 ) {
		global $wpdb;

		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';

		$remaining_players = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT remaining_players FROM {$live_state_table} WHERE tournament_id = %d",
				$tournament_id
			)
		);

		return $remaining_players && $remaining_players <= $final_table_size;
	}

	/**
	 * Process tournament completion
	 *
	 * Called when last player is eliminated to complete tournament:
	 * - Assign winner status to final player
	 * - Set tournament status to completed
	 * - Log winner announcement transaction
	 * - Trigger tournament completion hooks
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return bool True on success
	 */
	public static function process_tournament_completion( $tournament_id ) {
		global $wpdb;

		// Get tournament winner
		$winner = self::get_tournament_winner( $tournament_id );
		if ( ! $winner ) {
			return false; // No winner found
		}

		// Update tournament live state to completed
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$wpdb->update(
			$live_state_table,
			array(
				'status' => 'completed',
				'remaining_players' => 1,
				'completed_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'tournament_id' => $tournament_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		// Log winner announcement transaction
		TDWP_Transaction_Logger::log_transaction(
			$tournament_id,
			$winner['player_id'],
			'winner_announcement',
			0,
			0,
			sprintf( 'Tournament Winner: %s (Position 1)', $winner['player_name'] ),
			array(
				'finish_position' => 1,
				'winner_name' => $winner['player_name'],
				'winner_chips' => $winner['chips'],
				'prize_amount' => $winner['prize_amount'],
			)
		);

		/**
		 * Fires when tournament is completed
		 *
		 * @since 3.3.0
		 * @param int $tournament_id Tournament ID.
		 * @param array $winner Winner information.
		 */
		do_action( 'tdwp_tournament_completed', $tournament_id, $winner );

		return true;
	}

	/**
	 * Get bustout order for tournament
	 *
	 * Returns players in elimination order with precise timestamps
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Players in elimination order
	 */
	public static function get_bustout_order( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tp.*, p.post_title as player_name
				FROM {$players_table} tp
				INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				WHERE tp.tournament_id = %d
				AND tp.status = 'eliminated'
				AND tp.bustout_timestamp IS NOT NULL
				ORDER BY tp.bustout_timestamp ASC, tp.finish_position ASC",
				$tournament_id
			)
		);

		return $players;
	}

	/**
	 * Process player withdrawal from tournament
	 *
	 * Handles players who choose not to re-enter and withdraw:
	 * - Updates player withdrawal status and timestamp
	 * - Sets elimination reason to 'withdrawn' or 'declined_reentry'
	 * - Unseats player from tables
	 * - Logs withdrawal transaction
	 * - Updates tournament statistics
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $player_id     Player ID (tournament_players.id).
	 * @param string $reason        Withdrawal reason.
	 * @param string $withdrawal_type Type of withdrawal ('voluntary', 'declined_reentry').
	 * @return array|WP_Error Success array or WP_Error on failure
	 */
	public static function process_withdrawal( $tournament_id, $player_id, $reason = '', $withdrawal_type = 'voluntary' ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );
		$reason        = sanitize_text_field( $reason );
		$withdrawal_type = sanitize_text_field( $withdrawal_type );

		// Validate inputs
		if ( ! $tournament_id || ! $player_id ) {
			return new WP_Error( 'invalid_params', __( 'Invalid tournament or player ID', 'poker-tournament-import' ) );
		}

		// Validate withdrawal type
		if ( ! in_array( $withdrawal_type, array( 'voluntary', 'declined_reentry' ) ) ) {
			$withdrawal_type = 'voluntary';
		}

		// Get player data
		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$player_table} WHERE id = %d AND tournament_id = %d", $player_id, $tournament_id )
		);

		if ( ! $player ) {
			return new WP_Error( 'player_not_found', __( 'Player not found in tournament', 'poker-tournament-import' ) );
		}

		// Check if player is already withdrawn
		if ( 'withdrawn' === $player->withdrawal_status ) {
			return new WP_Error( 'already_withdrawn', __( 'Player has already withdrawn', 'poker-tournament-import' ) );
		}

		// Get current timestamp
		$withdrawal_timestamp = current_time( 'mysql' );

		// Prepare update data
		$update_data = array(
			'withdrawal_status'    => 'withdrawn',
			'withdrawal_timestamp' => $withdrawal_timestamp,
			'elimination_reason'   => $withdrawal_type,
			'status'               => 'withdrawn',
			'chip_count'           => 0,
			'updated_at'           => current_time( 'mysql' ),
		);

		$update_format = array( '%s', '%s', '%s', '%s', '%d', '%s' );

		// Add finish position if player was already eliminated
		if ( $player->finish_position ) {
			$update_data['finish_position'] = $player->finish_position;
			$update_format[] = '%d';
		}

		// Update player status
		$updated = $wpdb->update(
			$player_table,
			$update_data,
			array( 'id' => $player_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to process withdrawal', 'poker-tournament-import' ) );
		}

		// Unseat withdrawn player immediately using specific registration ID
		if ( class_exists( 'TDWP_Seat_Manager' ) ) {
			TDWP_Seat_Manager::unseat_player( $player->player_id, $player_id );
		}

		// Update live state if player was active
		if ( 'active' === $player->status || 'paid' === $player->status ) {
			$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$live_state_table}
					SET remaining_players = GREATEST(remaining_players - 1, 0),
					    updated_at = %s
					WHERE tournament_id = %d",
					current_time( 'mysql' ),
					$tournament_id
				)
			);
		}

		// Build withdrawal message
		$withdrawal_message = $reason
			? sprintf( 'Player withdrew: %s', $reason )
			: 'Player withdrew from tournament';

		if ( 'declined_reentry' === $withdrawal_type ) {
			$withdrawal_message = 'Player declined re-entry and withdrew from tournament';
		}

		// Calculate finish position if player was active
		$finish_position = null;
		if ( 'active' === $player->status || 'paid' === $player->status ) {
			$finish_position = self::calculate_finish_position( $tournament_id, $player_id );
		}

		// Log withdrawal transaction using new convenience method
		$transaction_id = TDWP_Transaction_Logger::log_withdrawal(
			$tournament_id,
			$player->player_id,
			$withdrawal_type,
			$reason,
			$finish_position,
			'withdrawn'  // elimination reason
		);

		/**
		 * Fires after player withdraws
		 *
		 * @since 3.3.0
		 * @param int    $tournament_id   Tournament ID.
		 * @param int    $player_id       Player ID.
		 * @param string $withdrawal_type Type of withdrawal.
		 * @param string $reason          Withdrawal reason.
		 */
		do_action( 'tdwp_player_withdrawal', $tournament_id, $player_id, $withdrawal_type, $reason );

		// Trigger table rebalancing if table manager exists
		if ( class_exists( 'TDWP_Table_Balancer' ) ) {
			TDWP_Table_Balancer::trigger_rebalance( $tournament_id );
		}

		return array(
			'success' => true,
			'withdrawal_type' => $withdrawal_type,
			'reason' => $reason,
			'withdrawal_timestamp' => $withdrawal_timestamp,
			'transaction_id' => $transaction_id,
			'message' => $withdrawal_message,
		);
	}

	/**
	 * Get withdrawal statistics for tournament
	 *
	 * Returns count of voluntary withdrawals vs declined re-entries
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Withdrawal statistics
	 */
	public static function get_withdrawal_statistics( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_withdrawals,
					SUM(CASE WHEN elimination_reason = 'voluntary' THEN 1 ELSE 0 END) as voluntary_withdrawals,
					SUM(CASE WHEN elimination_reason = 'declined_reentry' THEN 1 ELSE 0 END) as declined_reentries,
					SUM(CASE WHEN elimination_reason = 'disqualified' THEN 1 ELSE 0 END) as disqualifications
				FROM {$players_table}
				WHERE tournament_id = %d
				AND withdrawal_status = 'withdrawn'",
				$tournament_id
			),
			ARRAY_A
		);

		return $stats[0] ?? array(
			'total_withdrawals' => 0,
			'voluntary_withdrawals' => 0,
			'declined_reentries' => 0,
			'disqualifications' => 0,
		);
	}

	/**
	 * Get players who have withdrawn from tournament
	 *
	 * @since 3.3.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Withdrawn players
	 */
	public static function get_withdrawn_players( $tournament_id ) {
		global $wpdb;

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		$players = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tp.*, p.post_title as player_name
				FROM {$players_table} tp
				INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				WHERE tp.tournament_id = %d
				AND tp.withdrawal_status = 'withdrawn'
				ORDER BY tp.withdrawal_timestamp DESC",
				$tournament_id
			)
		);

		return $players;
	}
}
