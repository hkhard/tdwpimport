<?php
/**
 * Tournament Template Class
 *
 * Handles CRUD operations for tournament templates
 * with full WordPress security compliance
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tournament Template management class
 *
 * @since 3.0.0
 */
class TDWP_Tournament_Template {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Database object
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'tdwp_tournament_templates';
	}

	/**
	 * Create a new tournament template
	 *
	 * @since 3.0.0
	 * @param array $data Template data (will be sanitized).
	 * @return int|WP_Error Template ID on success, WP_Error on failure
	 */
	public function create( $data ) {
		// Sanitize input data
		$sanitized_data = $this->sanitize_template_data( $data );

		// Validate data
		$validation = $this->validate_template_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get current user ID
		$user_id = get_current_user_id();

		// Insert template
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'name'                => $sanitized_data['name'],
				'description'         => $sanitized_data['description'],
				'buy_in'              => $sanitized_data['buy_in'],
				'rebuy_cost'          => $sanitized_data['rebuy_cost'],
				'rebuy_chips'         => $sanitized_data['rebuy_chips'],
				'addon_cost'          => $sanitized_data['addon_cost'],
				'addon_chips'         => $sanitized_data['addon_chips'],
				'starting_chips'      => $sanitized_data['starting_chips'],
				'rake_percentage'     => $sanitized_data['rake_percentage'],
				'blind_schedule_id'   => $sanitized_data['blind_schedule_id'],
				'prize_structure_id'  => $sanitized_data['prize_structure_id'],
				'created_by'          => $user_id,
			),
			array( '%s', '%s', '%f', '%f', '%d', '%f', '%d', '%d', '%f', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to create tournament template', 'poker-tournament-import' )
			);
		}

		$template_id = $this->wpdb->insert_id;

		/**
		 * Fires after a tournament template is created
		 *
		 * @since 3.0.0
		 * @param int   $template_id The template ID.
		 * @param array $data        The template data.
		 */
		do_action( 'tdwp_tournament_template_created', $template_id, $sanitized_data );

		return $template_id;
	}

	/**
	 * Get a tournament template by ID
	 *
	 * @since 3.0.0
	 * @param int  $template_id Template ID.
	 * @param bool $with_relations Include related blind schedule and prize structure.
	 * @return object|null Template object or null if not found
	 */
	public function get( $template_id, $with_relations = false ) {
		$template_id = absint( $template_id );

		if ( 0 === $template_id ) {
			return null;
		}

		$template = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$template_id
			)
		);

		if ( ! $template ) {
			return null;
		}

		// Load related data if requested
		if ( $with_relations ) {
			$template = $this->load_relations( $template );
		}

		return $template;
	}

	/**
	 * Update a tournament template
	 *
	 * @since 3.0.0
	 * @param int   $template_id Template ID.
	 * @param array $data        Template data (will be sanitized).
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function update( $template_id, $data ) {
		$template_id = absint( $template_id );

		// Check if template exists
		$existing = $this->get( $template_id );
		if ( ! $existing ) {
			return new WP_Error(
				'template_not_found',
				__( 'Tournament template not found', 'poker-tournament-import' )
			);
		}

		// Sanitize input data
		$sanitized_data = $this->sanitize_template_data( $data );

		// Validate data
		$validation = $this->validate_template_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Update template
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'name'                => $sanitized_data['name'],
				'description'         => $sanitized_data['description'],
				'buy_in'              => $sanitized_data['buy_in'],
				'rebuy_cost'          => $sanitized_data['rebuy_cost'],
				'rebuy_chips'         => $sanitized_data['rebuy_chips'],
				'addon_cost'          => $sanitized_data['addon_cost'],
				'addon_chips'         => $sanitized_data['addon_chips'],
				'starting_chips'      => $sanitized_data['starting_chips'],
				'rake_percentage'     => $sanitized_data['rake_percentage'],
				'blind_schedule_id'   => $sanitized_data['blind_schedule_id'],
				'prize_structure_id'  => $sanitized_data['prize_structure_id'],
			),
			array( 'id' => $template_id ),
			array( '%s', '%s', '%f', '%f', '%d', '%f', '%d', '%d', '%f', '%d', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update tournament template', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a tournament template is updated
		 *
		 * @since 3.0.0
		 * @param int   $template_id    The template ID.
		 * @param array $sanitized_data The updated template data.
		 */
		do_action( 'tdwp_tournament_template_updated', $template_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete a tournament template
	 *
	 * @since 3.0.0
	 * @param int $template_id Template ID.
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete( $template_id ) {
		$template_id = absint( $template_id );

		// Check if template exists
		$existing = $this->get( $template_id );
		if ( ! $existing ) {
			return new WP_Error(
				'template_not_found',
				__( 'Tournament template not found', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires before a tournament template is deleted
		 *
		 * Allows plugins to prevent deletion or clean up related data
		 *
		 * @since 3.0.0
		 * @param int    $template_id Template ID.
		 * @param object $existing    Template object.
		 */
		do_action( 'tdwp_before_tournament_template_delete', $template_id, $existing );

		// Delete template
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $template_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete tournament template', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a tournament template is deleted
		 *
		 * @since 3.0.0
		 * @param int $template_id Template ID.
		 */
		do_action( 'tdwp_tournament_template_deleted', $template_id );

		return true;
	}

	/**
	 * Get all tournament templates
	 *
	 * @since 3.0.0
	 * @param array $args Query arguments.
	 * @return array Array of template objects
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'orderby'        => 'created_at',
			'order'          => 'DESC',
			'limit'          => 20,
			'offset'         => 0,
			'search'         => '',
			'with_relations' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$query = "SELECT * FROM {$this->table_name}";

		$where_clauses = array();

		// Search
		if ( ! empty( $args['search'] ) ) {
			$search_term     = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where_clauses[] = $this->wpdb->prepare(
				'(name LIKE %s OR description LIKE %s)',
				$search_term,
				$search_term
			);
		}

		if ( ! empty( $where_clauses ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Order by
		$allowed_orderby = array( 'id', 'name', 'created_at', 'updated_at', 'buy_in' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$query .= $this->wpdb->prepare( ' ORDER BY %1s %2s', $orderby, $order );

		// Limit and offset
		$limit  = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		if ( $limit > 0 ) {
			$query .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		// Execute query
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$templates = $this->wpdb->get_results( $query );

		// Load relations if requested
		if ( $args['with_relations'] && ! empty( $templates ) ) {
			foreach ( $templates as &$template ) {
				$template = $this->load_relations( $template );
			}
		}

		return $templates;
	}

	/**
	 * Clone a tournament template
	 *
	 * @since 3.0.0
	 * @param int $template_id Template ID to clone.
	 * @return int|WP_Error New template ID on success, WP_Error on failure
	 */
	public function clone_template( $template_id ) {
		$template_id = absint( $template_id );

		// Get existing template
		$existing = $this->get( $template_id );
		if ( ! $existing ) {
			return new WP_Error(
				'template_not_found',
				__( 'Tournament template not found', 'poker-tournament-import' )
			);
		}

		// Prepare data for new template
		$clone_data = array(
			'name'               => sprintf(
				/* translators: %s: original template name */
				__( '%s (Copy)', 'poker-tournament-import' ),
				$existing->name
			),
			'description'        => $existing->description,
			'buy_in'             => $existing->buy_in,
			'rebuy_cost'         => $existing->rebuy_cost,
			'rebuy_chips'        => $existing->rebuy_chips,
			'addon_cost'         => $existing->addon_cost,
			'addon_chips'        => $existing->addon_chips,
			'starting_chips'     => $existing->starting_chips,
			'rake_percentage'    => $existing->rake_percentage,
			'blind_schedule_id'  => $existing->blind_schedule_id,
			'prize_structure_id' => $existing->prize_structure_id,
		);

		// Create new template
		$new_id = $this->create( $clone_data );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		/**
		 * Fires after a tournament template is cloned
		 *
		 * @since 3.0.0
		 * @param int $new_id      New template ID.
		 * @param int $template_id Original template ID.
		 */
		do_action( 'tdwp_tournament_template_cloned', $new_id, $template_id );

		return $new_id;
	}

	/**
	 * Get total count of templates
	 *
	 * @since 3.0.0
	 * @param string $search Optional search term.
	 * @return int Total count
	 */
	public function get_count( $search = '' ) {
		$query = "SELECT COUNT(*) FROM {$this->table_name}";

		if ( ! empty( $search ) ) {
			$search_term = '%' . $this->wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$query      .= $this->wpdb->prepare(
				' WHERE name LIKE %s OR description LIKE %s',
				$search_term,
				$search_term
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Sanitize template data
	 *
	 * Recursively sanitizes all input data
	 *
	 * @since 3.0.0
	 * @param array $data Template data.
	 * @return array Sanitized data
	 */
	private function sanitize_template_data( $data ) {
		$sanitized = array();

		// Name (required)
		$sanitized['name'] = isset( $data['name'] )
			? sanitize_text_field( wp_unslash( $data['name'] ) )
			: '';

		// Description (optional)
		$sanitized['description'] = isset( $data['description'] )
			? sanitize_textarea_field( wp_unslash( $data['description'] ) )
			: '';

		// Financial values
		$sanitized['buy_in']          = isset( $data['buy_in'] ) ? floatval( $data['buy_in'] ) : 0;
		$sanitized['rebuy_cost']      = isset( $data['rebuy_cost'] ) ? floatval( $data['rebuy_cost'] ) : 0;
		$sanitized['addon_cost']      = isset( $data['addon_cost'] ) ? floatval( $data['addon_cost'] ) : 0;
		$sanitized['rake_percentage'] = isset( $data['rake_percentage'] ) ? floatval( $data['rake_percentage'] ) : 0;

		// Chip values
		$sanitized['rebuy_chips']    = isset( $data['rebuy_chips'] ) ? absint( $data['rebuy_chips'] ) : 0;
		$sanitized['addon_chips']    = isset( $data['addon_chips'] ) ? absint( $data['addon_chips'] ) : 0;
		$sanitized['starting_chips'] = isset( $data['starting_chips'] ) ? absint( $data['starting_chips'] ) : 10000;

		// Foreign keys
		$sanitized['blind_schedule_id']  = isset( $data['blind_schedule_id'] ) ? absint( $data['blind_schedule_id'] ) : 0;
		$sanitized['prize_structure_id'] = isset( $data['prize_structure_id'] ) ? absint( $data['prize_structure_id'] ) : 0;

		return $sanitized;
	}

	/**
	 * Validate template data
	 *
	 * @since 3.0.0
	 * @param array $data Sanitized template data.
	 * @return true|WP_Error True if valid, WP_Error if invalid
	 */
	private function validate_template_data( $data ) {
		$errors = new WP_Error();

		// Name is required
		if ( empty( $data['name'] ) ) {
			$errors->add(
				'name_required',
				__( 'Template name is required', 'poker-tournament-import' )
			);
		}

		// Name length
		if ( strlen( $data['name'] ) > 255 ) {
			$errors->add(
				'name_too_long',
				__( 'Template name must be 255 characters or less', 'poker-tournament-import' )
			);
		}

		// Buy-in must be non-negative
		if ( $data['buy_in'] < 0 ) {
			$errors->add(
				'invalid_buy_in',
				__( 'Buy-in must be zero or positive', 'poker-tournament-import' )
			);
		}

		// Rake percentage must be between 0 and 100
		if ( $data['rake_percentage'] < 0 || $data['rake_percentage'] > 100 ) {
			$errors->add(
				'invalid_rake',
				__( 'Rake percentage must be between 0 and 100', 'poker-tournament-import' )
			);
		}

		// Starting chips must be positive
		if ( $data['starting_chips'] <= 0 ) {
			$errors->add(
				'invalid_starting_chips',
				__( 'Starting chips must be greater than zero', 'poker-tournament-import' )
			);
		}

		// Validate foreign keys if provided
		if ( $data['blind_schedule_id'] > 0 ) {
			$schedule_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->wpdb->prefix}tdwp_blind_schedules WHERE id = %d",
					$data['blind_schedule_id']
				)
			);

			if ( ! $schedule_exists ) {
				$errors->add(
					'invalid_blind_schedule',
					__( 'Selected blind schedule does not exist', 'poker-tournament-import' )
				);
			}
		}

		if ( $data['prize_structure_id'] > 0 ) {
			$structure_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->wpdb->prefix}tdwp_prize_structures WHERE id = %d",
					$data['prize_structure_id']
				)
			);

			if ( ! $structure_exists ) {
				$errors->add(
					'invalid_prize_structure',
					__( 'Selected prize structure does not exist', 'poker-tournament-import' )
				);
			}
		}

		// Return errors or true
		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Load related data for a template
	 *
	 * @since 3.0.0
	 * @param object $template Template object.
	 * @return object Template with relations loaded
	 */
	private function load_relations( $template ) {
		// Load blind schedule if set
		if ( $template->blind_schedule_id > 0 ) {
			$template->blind_schedule = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->wpdb->prefix}tdwp_blind_schedules WHERE id = %d",
					$template->blind_schedule_id
				)
			);
		} else {
			$template->blind_schedule = null;
		}

		// Load prize structure if set
		if ( $template->prize_structure_id > 0 ) {
			$template->prize_structure = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->wpdb->prefix}tdwp_prize_structures WHERE id = %d",
					$template->prize_structure_id
				)
			);
		} else {
			$template->prize_structure = null;
		}

		return $template;
	}
}
