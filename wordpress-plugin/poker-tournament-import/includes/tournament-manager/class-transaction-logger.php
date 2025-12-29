<?php
/**
 * Transaction Logger for Tournament Manager
 *
 * Provides immutable audit log for all tournament financial operations.
 * This class implements append-only pattern - no UPDATE or DELETE operations
 * are exposed to maintain complete audit trail for compliance.
 *
 * Transaction Types:
 * - bust_out: Player elimination with finish position
 * - rebuy: Player rebuy during rebuy period
 * - add_on: Player add-on during add-on period
 * - chip_adjustment: Manual chip count correction
 * - late_registration: Player registration after tournament start
 * - bounty_award: Bounty earnings from eliminating another player
 * - withdrawal: Player withdrawal from tournament (voluntary or declined re-entry)
 * - winner_announcement: Tournament winner announcement and completion
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
 * Transaction logger class
 *
 * @since 3.2.0
 */
class TDWP_Transaction_Logger {

	/**
	 * Valid transaction types
	 *
	 * @var array
	 */
	const VALID_TYPES = array(
		'bust_out',
		'rebuy',
		'add_on',
		'chip_adjustment',
		'late_registration',
		'bounty_award',
		'withdrawal',
		'winner_announcement',
	);

	/**
	 * Log a transaction to the immutable audit log
	 *
	 * This method performs an INSERT operation only. No UPDATE or DELETE
	 * operations are allowed to maintain audit trail integrity.
	 *
	 * @since 3.2.0
	 * @since 3.3.0 Enhanced to support additional transaction data
	 * @param int    $tournament_id    Tournament ID.
	 * @param int    $player_id        Player ID.
	 * @param string $transaction_type Transaction type (must be in VALID_TYPES).
	 * @param float  $amount           Financial amount (optional, default 0).
	 * @param int    $chips            Chip count change (optional, default 0).
	 * @param string $reason           Reason for transaction (required for chip_adjustment).
	 * @param array  $additional_data  Additional transaction data (optional).
	 * @return int|false Transaction ID on success, false on failure
	 */
	public static function log_transaction( $tournament_id, $player_id, $transaction_type, $amount = 0, $chips = 0, $reason = '', $additional_data = array() ) {
		global $wpdb;

		// Validate inputs
		$tournament_id = absint( $tournament_id );
		$player_id     = absint( $player_id );
		$amount        = floatval( $amount );
		$chips         = intval( $chips );
		$reason        = sanitize_text_field( $reason );

		// Validate and sanitize additional data
		if ( ! is_array( $additional_data ) ) {
			$additional_data = array();
		}

		// Validate specific transaction types with required additional data
		if ( 'withdrawal' === $transaction_type && empty( $additional_data['withdrawal_type'] ) ) {
			error_log( 'Withdrawal transaction requires withdrawal_type in additional_data' );
			return false;
		}

		if ( 'winner_announcement' === $transaction_type && empty( $additional_data['finish_position'] ) ) {
			error_log( 'Winner announcement requires finish_position in additional_data' );
			return false;
		}

		// For withdrawal transactions, enhance reason with withdrawal details
		if ( 'withdrawal' === $transaction_type && ! empty( $additional_data ) ) {
			$withdrawal_info = array();

			if ( ! empty( $additional_data['withdrawal_type'] ) ) {
				$withdrawal_info[] = 'Type: ' . sanitize_text_field( $additional_data['withdrawal_type'] );
			}

			if ( ! empty( $additional_data['withdrawal_reason'] ) ) {
				$withdrawal_info[] = 'Reason: ' . sanitize_text_field( $additional_data['withdrawal_reason'] );
			}

			if ( ! empty( $additional_data['finish_position'] ) ) {
				$withdrawal_info[] = 'Position: ' . absint( $additional_data['finish_position'] );
			}

			if ( ! empty( $additional_data['elimination_reason'] ) ) {
				$withdrawal_info[] = 'Elimination: ' . sanitize_text_field( $additional_data['elimination_reason'] );
			}

			if ( ! empty( $withdrawal_info ) ) {
				$enhanced_reason = empty( $reason ) ? '' : $reason . ' | ';
				$reason = $enhanced_reason . implode( '; ', $withdrawal_info );
			}
		}

		// For winner announcement, enhance reason with position details
		if ( 'winner_announcement' === $transaction_type && ! empty( $additional_data['finish_position'] ) ) {
			$position_info = 'Position: ' . absint( $additional_data['finish_position'] );
			$reason = empty( $reason ) ? $position_info : $reason . ' | ' . $position_info;
		}

		// Validate transaction type
		if ( ! in_array( $transaction_type, self::VALID_TYPES, true ) ) {
			error_log( sprintf( 'Invalid transaction type: %s', $transaction_type ) );
			return false;
		}

		// Chip adjustments require a reason
		if ( 'chip_adjustment' === $transaction_type && empty( $reason ) ) {
			error_log( 'Chip adjustment requires a reason' );
			return false;
		}

		// Get current user ID
		$actor_user_id = get_current_user_id();
		if ( ! $actor_user_id ) {
			error_log( 'Transaction requires authenticated user' );
			return false;
		}

		// Insert transaction record
		$table_name = $wpdb->prefix . 'tdwp_transactions';
		$result     = $wpdb->insert(
			$table_name,
			array(
				'tournament_id'    => $tournament_id,
				'player_id'        => $player_id,
				'transaction_type' => $transaction_type,
				'amount'           => $amount,
				'chips'            => $chips,
				'reason'           => $reason,
				'actor_user_id'    => $actor_user_id,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%f', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			$error_context = sprintf(
				'TDWP Transaction Log Failed - Tournament: %d, Player: %d, Type: %s, Amount: %.2f, Chips: %d, User: %d, DB Error: %s',
				$tournament_id,
				$player_id,
				$transaction_type,
				$amount,
				$chips,
				$actor_user_id,
				$wpdb->last_error ?: 'Unknown DB error'
			);
			error_log( $error_context );
			return false;
		}

		$transaction_id = $wpdb->insert_id;

		/**
		 * Fires after a transaction is logged
		 *
		 * @since 3.2.0
		 * @param int    $transaction_id   Transaction ID.
		 * @param int    $tournament_id    Tournament ID.
		 * @param int    $player_id        Player ID.
		 * @param string $transaction_type Transaction type.
		 * @param float  $amount           Financial amount.
		 * @param int    $chips            Chip count change.
		 */
		do_action( 'tdwp_transaction_logged', $transaction_id, $tournament_id, $player_id, $transaction_type, $amount, $chips );

		return $transaction_id;
	}

	/**
	 * Get all transactions for a tournament
	 *
	 * @since 3.2.0
	 * @param int   $tournament_id Tournament ID.
	 * @param array $args {
	 *     Optional query arguments.
	 *
	 *     @type string $transaction_type Filter by transaction type.
	 *     @type int    $player_id        Filter by player ID.
	 *     @type string $order_by         Order by column (default 'created_at').
	 *     @type string $order            Order direction ASC|DESC (default 'DESC').
	 *     @type int    $limit            Limit number of results.
	 *     @type int    $offset           Offset for pagination.
	 * }
	 * @return array Array of transaction objects
	 */
	public static function get_tournament_transactions( $tournament_id, $args = array() ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return array();
		}

		// Parse arguments
		$defaults = array(
			'transaction_type' => '',
			'player_id'        => 0,
			'order_by'         => 'created_at',
			'order'            => 'DESC',
			'limit'            => 0,
			'offset'           => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		// Build query
		$table_name = $wpdb->prefix . 'tdwp_transactions';
		$where      = array( $wpdb->prepare( 'tournament_id = %d', $tournament_id ) );

		// Add optional filters
		if ( ! empty( $args['transaction_type'] ) ) {
			$where[] = $wpdb->prepare( 'transaction_type = %s', $args['transaction_type'] );
		}

		if ( ! empty( $args['player_id'] ) ) {
			$where[] = $wpdb->prepare( 'player_id = %d', absint( $args['player_id'] ) );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		// Validate order by
		$valid_order_by = array( 'id', 'created_at', 'transaction_type', 'amount', 'chips' );
		$order_by       = in_array( $args['order_by'], $valid_order_by, true ) ? $args['order_by'] : 'created_at';

		// Validate order direction
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Build limit clause
		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', absint( $args['limit'] ) );
			if ( $args['offset'] > 0 ) {
				$limit_clause .= $wpdb->prepare( ' OFFSET %d', absint( $args['offset'] ) );
			}
		}

		// Execute query
		$query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$order_by} {$order} {$limit_clause}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get transaction count for a tournament
	 *
	 * @since 3.2.0
	 * @param int    $tournament_id    Tournament ID.
	 * @param string $transaction_type Optional transaction type filter.
	 * @return int Transaction count
	 */
	public static function get_transaction_count( $tournament_id, $transaction_type = '' ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'tdwp_transactions';
		$where      = array( $wpdb->prepare( 'tournament_id = %d', $tournament_id ) );

		if ( ! empty( $transaction_type ) ) {
			$where[] = $wpdb->prepare( 'transaction_type = %s', $transaction_type );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );
		$query        = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get transactions grouped by type for a tournament
	 *
	 * Useful for statistics and reporting
	 *
	 * @since 3.2.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Array of objects with transaction_type, count, total_amount, total_chips
	 */
	public static function get_transaction_summary( $tournament_id ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'tdwp_transactions';
		$query      = $wpdb->prepare(
			"SELECT
				transaction_type,
				COUNT(*) as count,
				SUM(amount) as total_amount,
				SUM(chips) as total_chips
			FROM {$table_name}
			WHERE tournament_id = %d
			GROUP BY transaction_type",
			$tournament_id
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Log a withdrawal transaction
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id    Tournament ID.
	 * @param int    $player_id        Player ID.
	 * @param string $withdrawal_type  Type of withdrawal ('voluntary', 'declined_reentry').
	 * @param string $withdrawal_reason Reason for withdrawal (optional).
	 * @param int    $finish_position  Finish position at time of withdrawal (optional).
	 * @param string $elimination_reason Elimination reason (optional).
	 * @return int|false Transaction ID on success, false on failure
	 */
	public static function log_withdrawal( $tournament_id, $player_id, $withdrawal_type, $withdrawal_reason = '', $finish_position = null, $elimination_reason = '' ) {
		$additional_data = array(
			'withdrawal_type' => $withdrawal_type,
			'withdrawal_reason' => $withdrawal_reason,
		);

		if ( null !== $finish_position ) {
			$additional_data['finish_position'] = $finish_position;
		}

		if ( ! empty( $elimination_reason ) ) {
			$additional_data['elimination_reason'] = $elimination_reason;
		}

		$reason = 'Player withdrew from tournament';
		if ( ! empty( $withdrawal_reason ) ) {
			$reason .= ': ' . $withdrawal_reason;
		}

		return self::log_transaction( $tournament_id, $player_id, 'withdrawal', 0, 0, $reason, $additional_data );
	}

	/**
	 * Log a winner announcement transaction
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $player_id     Winning player ID.
	 * @param int    $finish_position Finish position (should be 1 for winner).
	 * @param string $celebration_message Optional celebration message.
	 * @return int|false Transaction ID on success, false on failure
	 */
	public static function log_winner_announcement( $tournament_id, $player_id, $finish_position = 1, $celebration_message = '' ) {
		$additional_data = array(
			'finish_position' => $finish_position,
		);

		$reason = 'Tournament winner announced';
		if ( ! empty( $celebration_message ) ) {
			$reason .= ': ' . $celebration_message;
		}

		return self::log_transaction( $tournament_id, $player_id, 'winner_announcement', 0, 0, $reason, $additional_data );
	}

	/**
	 * Log enhanced bustout transaction with finish position
	 *
	 * @since 3.3.0
	 * @param int    $tournament_id   Tournament ID.
	 * @param int    $player_id       Player ID.
	 * @param int    $finish_position Final finish position.
	 * @param float  $amount          Prize amount won (0 for non-money finishes).
	 * @param string $elimination_type Type of elimination (optional).
	 * @return int|false Transaction ID on success, false on failure
	 */
	public static function log_bustout_with_position( $tournament_id, $player_id, $finish_position, $amount = 0, $elimination_type = '' ) {
		$additional_data = array(
			'finish_position' => $finish_position,
		);

		if ( ! empty( $elimination_type ) ) {
			$additional_data['elimination_type'] = $elimination_type;
		}

		$reason = "Player eliminated in position {$finish_position}";
		if ( ! empty( $elimination_type ) ) {
			$reason .= " ({$elimination_type})";
		}

		return self::log_transaction( $tournament_id, $player_id, 'bust_out', $amount, 0, $reason, $additional_data );
	}
}
