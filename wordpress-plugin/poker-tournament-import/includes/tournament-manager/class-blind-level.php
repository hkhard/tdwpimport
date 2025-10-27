<?php
/**
 * Blind Level Manager Class
 *
 * Handles CRUD operations for individual blind levels within a schedule.
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Blind Level Class
 *
 * Manages individual blind level creation, retrieval, updating, and deletion
 * with comprehensive validation and security measures.
 *
 * @since 3.0.0
 */
class TDWP_Blind_Level {

	/**
	 * Database instance
	 *
	 * @since 3.0.0
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name for blind levels
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'tdwp_blind_levels';
	}

	/**
	 * Create a new blind level
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Blind level data.
	 * @return int|WP_Error Level ID on success, WP_Error on failure.
	 */
	public function create( $data ) {
		// Sanitize input data.
		$sanitized_data = $this->sanitize_level_data( $data );

		// Validate data.
		$validation = $this->validate_level_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Verify schedule exists.
		$schedule_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->prefix}tdwp_blind_schedules WHERE id = %d",
				$sanitized_data['schedule_id']
			)
		);

		if ( 0 === absint( $schedule_exists ) ) {
			return new WP_Error(
				'invalid_schedule',
				__( 'Invalid blind schedule ID.', 'poker-tournament-import' )
			);
		}

		// Insert level.
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'schedule_id'            => $sanitized_data['schedule_id'],
				'level_order'            => $sanitized_data['level_order'],
				'small_blind'            => $sanitized_data['small_blind'],
				'big_blind'              => $sanitized_data['big_blind'],
				'ante'                   => $sanitized_data['ante'],
				'duration_minutes'       => $sanitized_data['duration_minutes'],
				'is_break'               => $sanitized_data['is_break'],
				'break_duration_minutes' => $sanitized_data['break_duration_minutes'],
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to create blind level.', 'poker-tournament-import' )
			);
		}

		$level_id = $this->wpdb->insert_id;

		/**
		 * Fires after a blind level is created
		 *
		 * @since 3.0.0
		 *
		 * @param int   $level_id Created level ID.
		 * @param array $data     Sanitized level data.
		 */
		do_action( 'tdwp_blind_level_created', $level_id, $sanitized_data );

		return $level_id;
	}

	/**
	 * Get blind level by ID
	 *
	 * @since 3.0.0
	 *
	 * @param int $level_id Level ID.
	 * @return object|null|WP_Error Level object or null if not found.
	 */
	public function get( $level_id ) {
		$level_id = absint( $level_id );

		if ( 0 === $level_id ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid level ID.', 'poker-tournament-import' )
			);
		}

		$level = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$level_id
			)
		);

		return $level;
	}

	/**
	 * Update blind level
	 *
	 * @since 3.0.0
	 *
	 * @param int   $level_id Level ID.
	 * @param array $data     Updated data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $level_id, $data ) {
		$level_id = absint( $level_id );

		// Verify level exists.
		$existing = $this->get( $level_id );
		if ( null === $existing ) {
			return new WP_Error(
				'level_not_found',
				__( 'Blind level not found.', 'poker-tournament-import' )
			);
		}

		// Sanitize input data.
		$sanitized_data = $this->sanitize_level_data( $data );

		// Validate data.
		$validation = $this->validate_level_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Update level.
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'level_order'            => $sanitized_data['level_order'],
				'small_blind'            => $sanitized_data['small_blind'],
				'big_blind'              => $sanitized_data['big_blind'],
				'ante'                   => $sanitized_data['ante'],
				'duration_minutes'       => $sanitized_data['duration_minutes'],
				'is_break'               => $sanitized_data['is_break'],
				'break_duration_minutes' => $sanitized_data['break_duration_minutes'],
			),
			array( 'id' => $level_id ),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update blind level.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a blind level is updated
		 *
		 * @since 3.0.0
		 *
		 * @param int   $level_id Level ID.
		 * @param array $data     Sanitized level data.
		 */
		do_action( 'tdwp_blind_level_updated', $level_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete blind level
	 *
	 * @since 3.0.0
	 *
	 * @param int $level_id Level ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $level_id ) {
		$level_id = absint( $level_id );

		// Verify level exists.
		$existing = $this->get( $level_id );
		if ( null === $existing ) {
			return new WP_Error(
				'level_not_found',
				__( 'Blind level not found.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires before a blind level is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int    $level_id Level ID.
		 * @param object $existing Level object being deleted.
		 */
		do_action( 'tdwp_before_blind_level_deleted', $level_id, $existing );

		// Delete level.
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $level_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete blind level.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a blind level is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int $level_id Deleted level ID.
		 */
		do_action( 'tdwp_blind_level_deleted', $level_id );

		return true;
	}

	/**
	 * Reorder blind levels for a schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int   $schedule_id Schedule ID.
	 * @param array $level_ids   Array of level IDs in new order.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function reorder( $schedule_id, $level_ids ) {
		$schedule_id = absint( $schedule_id );

		if ( 0 === $schedule_id ) {
			return new WP_Error(
				'invalid_schedule',
				__( 'Invalid schedule ID.', 'poker-tournament-import' )
			);
		}

		if ( ! is_array( $level_ids ) || empty( $level_ids ) ) {
			return new WP_Error(
				'invalid_order',
				__( 'Invalid level order array.', 'poker-tournament-import' )
			);
		}

		// Sanitize level IDs.
		$level_ids = array_map( 'absint', $level_ids );

		// Update order for each level.
		$order = 1;
		foreach ( $level_ids as $level_id ) {
			$this->wpdb->update(
				$this->table_name,
				array( 'level_order' => $order ),
				array( 'id' => $level_id, 'schedule_id' => $schedule_id ),
				array( '%d' ),
				array( '%d', '%d' )
			);
			$order++;
		}

		/**
		 * Fires after blind levels are reordered
		 *
		 * @since 3.0.0
		 *
		 * @param int   $schedule_id Schedule ID.
		 * @param array $level_ids   Array of level IDs in new order.
		 */
		do_action( 'tdwp_blind_levels_reordered', $schedule_id, $level_ids );

		return true;
	}

	/**
	 * Bulk create blind levels
	 *
	 * @since 3.0.0
	 *
	 * @param int   $schedule_id Schedule ID.
	 * @param array $levels      Array of level data.
	 * @return array|WP_Error Array of created level IDs on success.
	 */
	public function bulk_create( $schedule_id, $levels ) {
		$schedule_id = absint( $schedule_id );

		if ( 0 === $schedule_id ) {
			return new WP_Error(
				'invalid_schedule',
				__( 'Invalid schedule ID.', 'poker-tournament-import' )
			);
		}

		if ( ! is_array( $levels ) || empty( $levels ) ) {
			return new WP_Error(
				'invalid_levels',
				__( 'Invalid levels array.', 'poker-tournament-import' )
			);
		}

		$created_ids = array();

		foreach ( $levels as $level_data ) {
			$level_data['schedule_id'] = $schedule_id;
			$level_id = $this->create( $level_data );

			if ( is_wp_error( $level_id ) ) {
				return $level_id;
			}

			$created_ids[] = $level_id;
		}

		return $created_ids;
	}

	/**
	 * Delete all levels for a schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_by_schedule( $schedule_id ) {
		$schedule_id = absint( $schedule_id );

		if ( 0 === $schedule_id ) {
			return new WP_Error(
				'invalid_schedule',
				__( 'Invalid schedule ID.', 'poker-tournament-import' )
			);
		}

		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'schedule_id' => $schedule_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete blind levels.', 'poker-tournament-import' )
			);
		}

		return true;
	}

	/**
	 * Sanitize level data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitize_level_data( $data ) {
		return array(
			'schedule_id'            => isset( $data['schedule_id'] ) ? absint( $data['schedule_id'] ) : 0,
			'level_order'            => isset( $data['level_order'] ) ? absint( $data['level_order'] ) : 1,
			'small_blind'            => isset( $data['small_blind'] ) ? absint( $data['small_blind'] ) : 0,
			'big_blind'              => isset( $data['big_blind'] ) ? absint( $data['big_blind'] ) : 0,
			'ante'                   => isset( $data['ante'] ) ? absint( $data['ante'] ) : 0,
			'duration_minutes'       => isset( $data['duration_minutes'] ) ? absint( $data['duration_minutes'] ) : 15,
			'is_break'               => isset( $data['is_break'] ) ? absint( $data['is_break'] ) : 0,
			'break_duration_minutes' => isset( $data['break_duration_minutes'] ) ? absint( $data['break_duration_minutes'] ) : 0,
		);
	}

	/**
	 * Validate level data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Sanitized data to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_level_data( $data ) {
		$errors = array();

		// Validate schedule ID.
		if ( $data['schedule_id'] <= 0 ) {
			$errors[] = __( 'Schedule ID is required.', 'poker-tournament-import' );
		}

		// Validate level order.
		if ( $data['level_order'] <= 0 ) {
			$errors[] = __( 'Level order must be greater than zero.', 'poker-tournament-import' );
		}

		// For regular levels, validate blinds.
		if ( 0 === $data['is_break'] ) {
			if ( $data['big_blind'] <= 0 ) {
				$errors[] = __( 'Big blind is required for regular levels.', 'poker-tournament-import' );
			}

			if ( $data['small_blind'] <= 0 ) {
				$errors[] = __( 'Small blind is required for regular levels.', 'poker-tournament-import' );
			}

			if ( $data['small_blind'] >= $data['big_blind'] ) {
				$errors[] = __( 'Small blind must be less than big blind.', 'poker-tournament-import' );
			}

			if ( $data['ante'] < 0 ) {
				$errors[] = __( 'Ante cannot be negative.', 'poker-tournament-import' );
			}
		} else {
			// For breaks, validate break duration.
			if ( $data['break_duration_minutes'] <= 0 ) {
				$errors[] = __( 'Break duration is required for break levels.', 'poker-tournament-import' );
			}

			if ( $data['break_duration_minutes'] > 60 ) {
				$errors[] = __( 'Break duration cannot exceed 60 minutes.', 'poker-tournament-import' );
			}
		}

		// Return errors if any.
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}
}
