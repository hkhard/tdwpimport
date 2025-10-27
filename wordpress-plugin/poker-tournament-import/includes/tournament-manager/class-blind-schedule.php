<?php
/**
 * Blind Schedule Manager Class
 *
 * Handles CRUD operations for tournament blind schedules.
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
 * TDWP Blind Schedule Class
 *
 * Manages blind schedule creation, retrieval, updating, and deletion
 * with comprehensive validation and security measures.
 *
 * @since 3.0.0
 */
class TDWP_Blind_Schedule {

	/**
	 * Database instance
	 *
	 * @since 3.0.0
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name for blind schedules
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Table name for blind levels
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private $levels_table;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb         = $wpdb;
		$this->table_name   = $wpdb->prefix . 'tdwp_blind_schedules';
		$this->levels_table = $wpdb->prefix . 'tdwp_blind_levels';
	}

	/**
	 * Create a new blind schedule
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Blind schedule data.
	 * @return int|WP_Error Schedule ID on success, WP_Error on failure.
	 */
	public function create( $data ) {
		// Sanitize input data.
		$sanitized_data = $this->sanitize_schedule_data( $data );

		// Validate data.
		$validation = $this->validate_schedule_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Insert schedule.
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'name'               => $sanitized_data['name'],
				'description'        => $sanitized_data['description'],
				'level_duration'     => $sanitized_data['level_duration'],
				'break_frequency'    => $sanitized_data['break_frequency'],
				'break_duration'     => $sanitized_data['break_duration'],
				'is_default_turbo'   => $sanitized_data['is_default_turbo'],
				'is_default_regular' => $sanitized_data['is_default_regular'],
				'is_default_deep'    => $sanitized_data['is_default_deep'],
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to create blind schedule.', 'poker-tournament-import' )
			);
		}

		$schedule_id = $this->wpdb->insert_id;

		/**
		 * Fires after a blind schedule is created
		 *
		 * @since 3.0.0
		 *
		 * @param int   $schedule_id Created schedule ID.
		 * @param array $data        Sanitized schedule data.
		 */
		do_action( 'tdwp_blind_schedule_created', $schedule_id, $sanitized_data );

		return $schedule_id;
	}

	/**
	 * Get blind schedule by ID
	 *
	 * @since 3.0.0
	 *
	 * @param int  $schedule_id   Schedule ID.
	 * @param bool $with_levels   Whether to include blind levels.
	 * @return object|null|WP_Error Schedule object or null if not found.
	 */
	public function get( $schedule_id, $with_levels = false ) {
		$schedule_id = absint( $schedule_id );

		if ( 0 === $schedule_id ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid schedule ID.', 'poker-tournament-import' )
			);
		}

		$schedule = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$schedule_id
			)
		);

		if ( null === $schedule ) {
			return null;
		}

		// Load blind levels if requested.
		if ( $with_levels ) {
			$schedule->levels = $this->get_levels( $schedule_id );
		}

		return $schedule;
	}

	/**
	 * Get all blind levels for a schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return array Array of level objects.
	 */
	public function get_levels( $schedule_id ) {
		$schedule_id = absint( $schedule_id );

		if ( 0 === $schedule_id ) {
			return array();
		}

		$levels = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->levels_table}
				WHERE schedule_id = %d
				ORDER BY level_order ASC",
				$schedule_id
			)
		);

		return $levels ? $levels : array();
	}

	/**
	 * Update blind schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int   $schedule_id Schedule ID.
	 * @param array $data        Updated data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $schedule_id, $data ) {
		$schedule_id = absint( $schedule_id );

		// Verify schedule exists.
		$existing = $this->get( $schedule_id );
		if ( null === $existing ) {
			return new WP_Error(
				'schedule_not_found',
				__( 'Blind schedule not found.', 'poker-tournament-import' )
			);
		}

		// Sanitize input data.
		$sanitized_data = $this->sanitize_schedule_data( $data );

		// Validate data.
		$validation = $this->validate_schedule_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Update schedule.
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'name'               => $sanitized_data['name'],
				'description'        => $sanitized_data['description'],
				'level_duration'     => $sanitized_data['level_duration'],
				'break_frequency'    => $sanitized_data['break_frequency'],
				'break_duration'     => $sanitized_data['break_duration'],
				'is_default_turbo'   => $sanitized_data['is_default_turbo'],
				'is_default_regular' => $sanitized_data['is_default_regular'],
				'is_default_deep'    => $sanitized_data['is_default_deep'],
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $schedule_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update blind schedule.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a blind schedule is updated
		 *
		 * @since 3.0.0
		 *
		 * @param int   $schedule_id Schedule ID.
		 * @param array $data        Sanitized schedule data.
		 */
		do_action( 'tdwp_blind_schedule_updated', $schedule_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete blind schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $schedule_id ) {
		$schedule_id = absint( $schedule_id );

		// Verify schedule exists.
		$existing = $this->get( $schedule_id );
		if ( null === $existing ) {
			return new WP_Error(
				'schedule_not_found',
				__( 'Blind schedule not found.', 'poker-tournament-import' )
			);
		}

		// Check if schedule is in use by templates.
		$templates_count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->prefix}tdwp_tournament_templates
				WHERE blind_schedule_id = %d",
				$schedule_id
			)
		);

		if ( $templates_count > 0 ) {
			return new WP_Error(
				'schedule_in_use',
				sprintf(
					/* translators: %d: number of templates using this schedule */
					__( 'Cannot delete schedule. It is used by %d tournament template(s).', 'poker-tournament-import' ),
					$templates_count
				)
			);
		}

		/**
		 * Fires before a blind schedule is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int    $schedule_id Schedule ID.
		 * @param object $existing    Schedule object being deleted.
		 */
		do_action( 'tdwp_before_blind_schedule_deleted', $schedule_id, $existing );

		// Delete all associated levels.
		$this->wpdb->delete(
			$this->levels_table,
			array( 'schedule_id' => $schedule_id ),
			array( '%d' )
		);

		// Delete schedule.
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $schedule_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete blind schedule.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a blind schedule is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int $schedule_id Deleted schedule ID.
		 */
		do_action( 'tdwp_blind_schedule_deleted', $schedule_id );

		return true;
	}

	/**
	 * Clone blind schedule
	 *
	 * @since 3.0.0
	 *
	 * @param int $schedule_id Schedule ID to clone.
	 * @return int|WP_Error New schedule ID on success, WP_Error on failure.
	 */
	public function clone_schedule( $schedule_id ) {
		$schedule_id = absint( $schedule_id );

		// Get original schedule with levels.
		$original = $this->get( $schedule_id, true );
		if ( null === $original ) {
			return new WP_Error(
				'schedule_not_found',
				__( 'Blind schedule not found.', 'poker-tournament-import' )
			);
		}

		// Create new schedule.
		$new_data = array(
			'name'               => $original->name . ' (Copy)',
			'description'        => $original->description,
			'level_duration'     => $original->level_duration,
			'break_frequency'    => $original->break_frequency,
			'break_duration'     => $original->break_duration,
			'is_default_turbo'   => 0,
			'is_default_regular' => 0,
			'is_default_deep'    => 0,
		);

		$new_schedule_id = $this->create( $new_data );

		if ( is_wp_error( $new_schedule_id ) ) {
			return $new_schedule_id;
		}

		// Clone all levels.
		if ( ! empty( $original->levels ) ) {
			foreach ( $original->levels as $level ) {
				$level_data = array(
					'schedule_id'  => $new_schedule_id,
					'level_order'  => $level->level_order,
					'small_blind'  => $level->small_blind,
					'big_blind'    => $level->big_blind,
					'ante'         => $level->ante,
					'is_break'     => $level->is_break,
					'break_length' => $level->break_length,
				);

				$this->wpdb->insert(
					$this->levels_table,
					$level_data,
					array( '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
				);
			}
		}

		/**
		 * Fires after a blind schedule is cloned
		 *
		 * @since 3.0.0
		 *
		 * @param int $new_schedule_id New schedule ID.
		 * @param int $schedule_id     Original schedule ID.
		 */
		do_action( 'tdwp_blind_schedule_cloned', $new_schedule_id, $schedule_id );

		return $new_schedule_id;
	}

	/**
	 * Get all blind schedules
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of schedule objects.
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'search'      => '',
			'orderby'     => 'name',
			'order'       => 'ASC',
			'per_page'    => 20,
			'page'        => 1,
			'with_levels' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize arguments.
		$search   = sanitize_text_field( wp_unslash( $args['search'] ) );
		$orderby  = in_array( $args['orderby'], array( 'name', 'level_duration', 'created_at' ), true ) ? $args['orderby'] : 'name';
		$order    = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'ASC';
		$per_page = absint( $args['per_page'] );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Build WHERE clause.
		$where = '1=1';
		$where_values = array();

		if ( ! empty( $search ) ) {
			$where .= ' AND (name LIKE %s OR description LIKE %s)';
			$like_search = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_values[] = $like_search;
			$where_values[] = $like_search;
		}

		// Build query.
		$sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} {$order}";

		if ( $per_page > 0 ) {
			$sql .= ' LIMIT %d OFFSET %d';
			$where_values[] = $per_page;
			$where_values[] = $offset;
		}

		// Prepare and execute query.
		if ( ! empty( $where_values ) ) {
			$schedules = $this->wpdb->get_results(
				$this->wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$where_values
				)
			);
		} else {
			$schedules = $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Load levels if requested.
		if ( $args['with_levels'] && ! empty( $schedules ) ) {
			foreach ( $schedules as $schedule ) {
				$schedule->levels = $this->get_levels( $schedule->id );
			}
		}

		return $schedules ? $schedules : array();
	}

	/**
	 * Get total count of schedules
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Query arguments.
	 * @return int Total count.
	 */
	public function get_total( $args = array() ) {
		$defaults = array(
			'search' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize arguments.
		$search = sanitize_text_field( wp_unslash( $args['search'] ) );

		// Build WHERE clause.
		$where = '1=1';
		$where_values = array();

		if ( ! empty( $search ) ) {
			$where .= ' AND (name LIKE %s OR description LIKE %s)';
			$like_search = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_values[] = $like_search;
			$where_values[] = $like_search;
		}

		// Build query.
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";

		// Prepare and execute query.
		if ( ! empty( $where_values ) ) {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$where_values
				)
			);
		} else {
			$count = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return absint( $count );
	}

	/**
	 * Sanitize schedule data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitize_schedule_data( $data ) {
		return array(
			'name'               => isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '',
			'description'        => isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '',
			'level_duration'     => isset( $data['level_duration'] ) ? absint( $data['level_duration'] ) : 15,
			'break_frequency'    => isset( $data['break_frequency'] ) ? absint( $data['break_frequency'] ) : 0,
			'break_duration'     => isset( $data['break_duration'] ) ? absint( $data['break_duration'] ) : 0,
			'is_default_turbo'   => isset( $data['is_default_turbo'] ) ? absint( $data['is_default_turbo'] ) : 0,
			'is_default_regular' => isset( $data['is_default_regular'] ) ? absint( $data['is_default_regular'] ) : 0,
			'is_default_deep'    => isset( $data['is_default_deep'] ) ? absint( $data['is_default_deep'] ) : 0,
		);
	}

	/**
	 * Validate schedule data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Sanitized data to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_schedule_data( $data ) {
		$errors = array();

		// Validate name.
		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Schedule name is required.', 'poker-tournament-import' );
		}

		if ( strlen( $data['name'] ) > 255 ) {
			$errors[] = __( 'Schedule name must be 255 characters or less.', 'poker-tournament-import' );
		}

		// Validate level duration.
		if ( $data['level_duration'] < 1 || $data['level_duration'] > 120 ) {
			$errors[] = __( 'Level duration must be between 1 and 120 minutes.', 'poker-tournament-import' );
		}

		// Validate break settings.
		if ( $data['break_frequency'] < 0 || $data['break_frequency'] > 20 ) {
			$errors[] = __( 'Break frequency must be between 0 and 20 levels.', 'poker-tournament-import' );
		}

		if ( $data['break_duration'] < 0 || $data['break_duration'] > 60 ) {
			$errors[] = __( 'Break duration must be between 0 and 60 minutes.', 'poker-tournament-import' );
		}

		// Return errors if any.
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}
}
