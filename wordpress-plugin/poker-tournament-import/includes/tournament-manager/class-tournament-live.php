<?php
/**
 * Tournament Live State Manager
 *
 * Handles CRUD operations for live tournament state tracking
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Tournament Live State Class
 *
 * Manages live tournament state including status, players, prize pool
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Live {

	/**
	 * Database instance
	 *
	 * @since 3.1.0
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name for live state
	 *
	 * @since 3.1.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Tournament statuses
	 *
	 * @since 3.1.0
	 * @var array
	 */
	const STATUSES = array(
		'pending'   => 'Pending',
		'running'   => 'Running',
		'paused'    => 'Paused',
		'break'     => 'On Break',
		'completed' => 'Completed',
	);

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'tdwp_tournament_live_state';
	}

	/**
	 * Create new live tournament state
	 *
	 * @since 3.1.0
	 *
	 * @param array $data Live state data.
	 * @return int|WP_Error Tournament state ID on success, WP_Error on failure.
	 */
	public function create( $data ) {
		// Sanitize input data.
		$sanitized_data = $this->sanitize_state_data( $data );

		// Validate data.
		$validation = $this->validate_state_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check if tournament already has live state.
		$existing = $this->get_by_tournament_id( $sanitized_data['tournament_id'] );
		if ( $existing ) {
			return new WP_Error(
				'tournament_already_live',
				__( 'This tournament already has an active live state.', 'poker-tournament-import' )
			);
		}

		// Insert state.
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'tournament_id'      => $sanitized_data['tournament_id'],
				'template_id'        => $sanitized_data['template_id'],
				'status'             => $sanitized_data['status'],
				'current_level'      => $sanitized_data['current_level'],
				'time_remaining'     => $sanitized_data['time_remaining'],
				'total_players'      => $sanitized_data['total_players'],
				'remaining_players'  => $sanitized_data['remaining_players'],
				'total_rebuys'       => $sanitized_data['total_rebuys'],
				'total_addons'       => $sanitized_data['total_addons'],
				'prize_pool'         => $sanitized_data['prize_pool'],
			),
			array( '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to create tournament live state.', 'poker-tournament-import' )
			);
		}

		$state_id = $this->wpdb->insert_id;

		/**
		 * Fires after tournament live state is created
		 *
		 * @since 3.1.0
		 *
		 * @param int   $state_id       Created state ID.
		 * @param array $sanitized_data Sanitized state data.
		 */
		do_action( 'tdwp_tournament_live_state_created', $state_id, $sanitized_data );

		return $state_id;
	}

	/**
	 * Get live state by ID
	 *
	 * @since 3.1.0
	 *
	 * @param int $state_id State ID.
	 * @return object|null State object or null if not found.
	 */
	public function get( $state_id ) {
		$state_id = absint( $state_id );

		if ( 0 === $state_id ) {
			return null;
		}

		$state = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$state_id
			)
		);

		return $state;
	}

	/**
	 * Get live state by tournament ID
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return object|null State object or null if not found.
	 */
	public function get_by_tournament_id( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		if ( 0 === $tournament_id ) {
			return null;
		}

		$state = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE tournament_id = %d",
				$tournament_id
			)
		);

		return $state;
	}

	/**
	 * Update live state
	 *
	 * @since 3.1.0
	 *
	 * @param int   $state_id State ID.
	 * @param array $data     Updated data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $state_id, $data ) {
		$state_id = absint( $state_id );

		// Verify state exists.
		$existing = $this->get( $state_id );
		if ( ! $existing ) {
			return new WP_Error(
				'state_not_found',
				__( 'Tournament live state not found.', 'poker-tournament-import' )
			);
		}

		// Sanitize input data.
		$sanitized_data = $this->sanitize_state_data( $data );

		// Build update array (only include fields that are set).
		$update_data = array();
		$formats     = array();

		if ( isset( $sanitized_data['status'] ) ) {
			$update_data['status'] = $sanitized_data['status'];
			$formats[]             = '%s';
		}

		if ( isset( $sanitized_data['current_level'] ) ) {
			$update_data['current_level'] = $sanitized_data['current_level'];
			$formats[]                    = '%d';
		}

		if ( isset( $sanitized_data['time_remaining'] ) ) {
			$update_data['time_remaining'] = $sanitized_data['time_remaining'];
			$formats[]                     = '%d';
		}

		if ( isset( $sanitized_data['started_at'] ) ) {
			$update_data['started_at'] = $sanitized_data['started_at'];
			$formats[]                 = '%s';
		}

		if ( isset( $sanitized_data['paused_at'] ) ) {
			$update_data['paused_at'] = $sanitized_data['paused_at'];
			$formats[]                = '%s';
		}

		if ( isset( $sanitized_data['break_until'] ) ) {
			$update_data['break_until'] = $sanitized_data['break_until'];
			$formats[]                  = '%s';
		}

		if ( isset( $sanitized_data['completed_at'] ) ) {
			$update_data['completed_at'] = $sanitized_data['completed_at'];
			$formats[]                   = '%s';
		}

		if ( isset( $sanitized_data['total_players'] ) ) {
			$update_data['total_players'] = $sanitized_data['total_players'];
			$formats[]                    = '%d';
		}

		if ( isset( $sanitized_data['remaining_players'] ) ) {
			$update_data['remaining_players'] = $sanitized_data['remaining_players'];
			$formats[]                        = '%d';
		}

		if ( isset( $sanitized_data['total_rebuys'] ) ) {
			$update_data['total_rebuys'] = $sanitized_data['total_rebuys'];
			$formats[]                   = '%d';
		}

		if ( isset( $sanitized_data['total_addons'] ) ) {
			$update_data['total_addons'] = $sanitized_data['total_addons'];
			$formats[]                   = '%d';
		}

		if ( isset( $sanitized_data['prize_pool'] ) ) {
			$update_data['prize_pool'] = $sanitized_data['prize_pool'];
			$formats[]                 = '%f';
		}

		if ( isset( $sanitized_data['next_payout_position'] ) ) {
			$update_data['next_payout_position'] = $sanitized_data['next_payout_position'];
			$formats[]                           = '%d';
		}

		// Update state.
		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $state_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update tournament live state.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after tournament live state is updated
		 *
		 * @since 3.1.0
		 *
		 * @param int   $state_id       State ID.
		 * @param array $sanitized_data Updated data.
		 */
		do_action( 'tdwp_tournament_live_state_updated', $state_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete live state
	 *
	 * @since 3.1.0
	 *
	 * @param int $state_id State ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $state_id ) {
		$state_id = absint( $state_id );

		// Verify state exists.
		$existing = $this->get( $state_id );
		if ( ! $existing ) {
			return new WP_Error(
				'state_not_found',
				__( 'Tournament live state not found.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires before tournament live state is deleted
		 *
		 * @since 3.1.0
		 *
		 * @param int    $state_id State ID.
		 * @param object $existing State object being deleted.
		 */
		do_action( 'tdwp_before_tournament_live_state_deleted', $state_id, $existing );

		// Delete state.
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $state_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete tournament live state.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after tournament live state is deleted
		 *
		 * @since 3.1.0
		 *
		 * @param int $state_id Deleted state ID.
		 */
		do_action( 'tdwp_tournament_live_state_deleted', $state_id );

		return true;
	}

	/**
	 * Get all active tournaments
	 *
	 * @since 3.1.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of state objects.
	 */
	public function get_active( $args = array() ) {
		$defaults = array(
			'status'  => array( 'running', 'paused', 'break' ),
			'orderby' => 'started_at',
			'order'   => 'DESC',
			'limit'   => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build status list.
		$statuses = (array) $args['status'];
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// Build query.
		$query = "SELECT * FROM {$this->table_name} WHERE status IN ($placeholders)";

		// Order by.
		$allowed_orderby = array( 'id', 'started_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'started_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$query .= " ORDER BY $orderby $order";

		// Limit.
		$limit = absint( $args['limit'] );
		if ( $limit > 0 ) {
			$query .= " LIMIT $limit";
		}

		// Execute query.
		$states = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$query,
				...$statuses
			)
		);

		return $states;
	}

	/**
	 * Sanitize state data
	 *
	 * @since 3.1.0
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitize_state_data( $data ) {
		$sanitized = array();

		if ( isset( $data['tournament_id'] ) ) {
			$sanitized['tournament_id'] = absint( $data['tournament_id'] );
		}

		if ( isset( $data['template_id'] ) ) {
			$sanitized['template_id'] = absint( $data['template_id'] );
		}

		if ( isset( $data['status'] ) ) {
			$sanitized['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['current_level'] ) ) {
			$sanitized['current_level'] = absint( $data['current_level'] );
		}

		if ( isset( $data['time_remaining'] ) ) {
			$sanitized['time_remaining'] = absint( $data['time_remaining'] );
		}

		if ( isset( $data['started_at'] ) ) {
			$sanitized['started_at'] = sanitize_text_field( $data['started_at'] );
		}

		if ( isset( $data['paused_at'] ) ) {
			$sanitized['paused_at'] = sanitize_text_field( $data['paused_at'] );
		}

		if ( isset( $data['break_until'] ) ) {
			$sanitized['break_until'] = sanitize_text_field( $data['break_until'] );
		}

		if ( isset( $data['completed_at'] ) ) {
			$sanitized['completed_at'] = sanitize_text_field( $data['completed_at'] );
		}

		if ( isset( $data['total_players'] ) ) {
			$sanitized['total_players'] = absint( $data['total_players'] );
		}

		if ( isset( $data['remaining_players'] ) ) {
			$sanitized['remaining_players'] = absint( $data['remaining_players'] );
		}

		if ( isset( $data['total_rebuys'] ) ) {
			$sanitized['total_rebuys'] = absint( $data['total_rebuys'] );
		}

		if ( isset( $data['total_addons'] ) ) {
			$sanitized['total_addons'] = absint( $data['total_addons'] );
		}

		if ( isset( $data['prize_pool'] ) ) {
			$sanitized['prize_pool'] = floatval( $data['prize_pool'] );
		}

		if ( isset( $data['next_payout_position'] ) ) {
			$sanitized['next_payout_position'] = absint( $data['next_payout_position'] );
		}

		return $sanitized;
	}

	/**
	 * Validate state data
	 *
	 * @since 3.1.0
	 *
	 * @param array $data Sanitized data to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_state_data( $data ) {
		$errors = array();

		// Validate tournament ID.
		if ( empty( $data['tournament_id'] ) || $data['tournament_id'] <= 0 ) {
			$errors[] = __( 'Tournament ID is required.', 'poker-tournament-import' );
		}

		// Validate status.
		if ( isset( $data['status'] ) && ! array_key_exists( $data['status'], self::STATUSES ) ) {
			$errors[] = __( 'Invalid tournament status.', 'poker-tournament-import' );
		}

		// Return errors if any.
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}
}
