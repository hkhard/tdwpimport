<?php
/**
 * Prize Structure Manager Class
 *
 * Handles CRUD operations for tournament prize structures.
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
 * TDWP Prize Structure Class
 *
 * Manages prize structure creation, retrieval, updating, and deletion
 * with comprehensive validation and security measures.
 *
 * @since 3.0.0
 */
class TDWP_Prize_Structure {

	/**
	 * Database instance
	 *
	 * @since 3.0.0
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name for prize structures
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
		$this->table_name = $wpdb->prefix . 'tdwp_prize_structures';
	}

	/**
	 * Create a new prize structure
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Prize structure data.
	 * @return int|WP_Error Structure ID on success, WP_Error on failure.
	 */
	public function create( $data ) {
		// Sanitize input data.
		$sanitized_data = $this->sanitize_structure_data( $data );

		// Validate data.
		$validation = $this->validate_structure_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Insert structure.
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'name'           => $sanitized_data['name'],
				'description'    => $sanitized_data['description'],
				'is_template'    => $sanitized_data['is_template'],
				'min_players'    => $sanitized_data['min_players'],
				'max_players'    => $sanitized_data['max_players'],
				'structure_json' => $sanitized_data['structure_json'],
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to create prize structure.', 'poker-tournament-import' )
			);
		}

		$structure_id = $this->wpdb->insert_id;

		/**
		 * Fires after a prize structure is created
		 *
		 * @since 3.0.0
		 *
		 * @param int   $structure_id Created structure ID.
		 * @param array $data         Sanitized structure data.
		 */
		do_action( 'tdwp_prize_structure_created', $structure_id, $sanitized_data );

		return $structure_id;
	}

	/**
	 * Get prize structure by ID
	 *
	 * @since 3.0.0
	 *
	 * @param int  $structure_id   Structure ID.
	 * @param bool $with_breakdown Whether to include calculated breakdown.
	 * @return object|null|WP_Error Structure object or null if not found.
	 */
	public function get( $structure_id, $with_breakdown = false ) {
		$structure_id = absint( $structure_id );

		if ( 0 === $structure_id ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid structure ID.', 'poker-tournament-import' )
			);
		}

		$structure = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$structure_id
			)
		);

		if ( null === $structure ) {
			return null;
		}

		// Decode and sanitize JSON.
		$structure->places = $this->decode_structure_json( $structure->structure_json );

		// Add breakdown if requested.
		if ( $with_breakdown && ! empty( $structure->places ) ) {
			$structure->breakdown = $this->calculate_example_breakdown( $structure->places );
		}

		return $structure;
	}

	/**
	 * Update prize structure
	 *
	 * @since 3.0.0
	 *
	 * @param int   $structure_id Structure ID.
	 * @param array $data         Updated data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $structure_id, $data ) {
		$structure_id = absint( $structure_id );

		// Verify structure exists.
		$existing = $this->get( $structure_id );
		if ( null === $existing ) {
			return new WP_Error(
				'structure_not_found',
				__( 'Prize structure not found.', 'poker-tournament-import' )
			);
		}

		// Sanitize input data.
		$sanitized_data = $this->sanitize_structure_data( $data );

		// Validate data.
		$validation = $this->validate_structure_data( $sanitized_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Update structure.
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'name'           => $sanitized_data['name'],
				'description'    => $sanitized_data['description'],
				'is_template'    => $sanitized_data['is_template'],
				'min_players'    => $sanitized_data['min_players'],
				'max_players'    => $sanitized_data['max_players'],
				'structure_json' => $sanitized_data['structure_json'],
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $structure_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update prize structure.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a prize structure is updated
		 *
		 * @since 3.0.0
		 *
		 * @param int   $structure_id Structure ID.
		 * @param array $data         Sanitized structure data.
		 */
		do_action( 'tdwp_prize_structure_updated', $structure_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete prize structure
	 *
	 * @since 3.0.0
	 *
	 * @param int $structure_id Structure ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $structure_id ) {
		$structure_id = absint( $structure_id );

		// Verify structure exists.
		$existing = $this->get( $structure_id );
		if ( null === $existing ) {
			return new WP_Error(
				'structure_not_found',
				__( 'Prize structure not found.', 'poker-tournament-import' )
			);
		}

		// Check if structure is in use by templates.
		$templates_count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->prefix}tdwp_tournament_templates
				WHERE prize_structure_id = %d",
				$structure_id
			)
		);

		if ( $templates_count > 0 ) {
			return new WP_Error(
				'structure_in_use',
				sprintf(
					/* translators: %d: number of templates using this structure */
					__( 'Cannot delete structure. It is used by %d tournament template(s).', 'poker-tournament-import' ),
					$templates_count
				)
			);
		}

		/**
		 * Fires before a prize structure is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int    $structure_id Structure ID.
		 * @param object $existing     Structure object being deleted.
		 */
		do_action( 'tdwp_before_prize_structure_deleted', $structure_id, $existing );

		// Delete structure.
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $structure_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete prize structure.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after a prize structure is deleted
		 *
		 * @since 3.0.0
		 *
		 * @param int $structure_id Deleted structure ID.
		 */
		do_action( 'tdwp_prize_structure_deleted', $structure_id );

		return true;
	}

	/**
	 * Clone prize structure
	 *
	 * @since 3.0.0
	 *
	 * @param int $structure_id Structure ID to clone.
	 * @return int|WP_Error New structure ID on success, WP_Error on failure.
	 */
	public function clone_structure( $structure_id ) {
		$structure_id = absint( $structure_id );

		// Get original structure.
		$original = $this->get( $structure_id );
		if ( null === $original ) {
			return new WP_Error(
				'structure_not_found',
				__( 'Prize structure not found.', 'poker-tournament-import' )
			);
		}

		// Create new structure.
		$new_data = array(
			'name'           => $original->name . ' (Copy)',
			'description'    => $original->description,
			'is_template'    => 0,
			'min_players'    => $original->min_players,
			'max_players'    => $original->max_players,
			'structure_json' => $original->structure_json,
		);

		$new_structure_id = $this->create( $new_data );

		if ( is_wp_error( $new_structure_id ) ) {
			return $new_structure_id;
		}

		/**
		 * Fires after a prize structure is cloned
		 *
		 * @since 3.0.0
		 *
		 * @param int $new_structure_id New structure ID.
		 * @param int $structure_id     Original structure ID.
		 */
		do_action( 'tdwp_prize_structure_cloned', $new_structure_id, $structure_id );

		return $new_structure_id;
	}

	/**
	 * Get all prize structures
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of structure objects.
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'search'       => '',
			'min_players'  => 0,
			'max_players'  => 0,
			'is_template'  => '',
			'orderby'      => 'name',
			'order'        => 'ASC',
			'per_page'     => 20,
			'page'         => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize arguments.
		$search       = sanitize_text_field( wp_unslash( $args['search'] ) );
		$min_players  = absint( $args['min_players'] );
		$max_players  = absint( $args['max_players'] );
		$is_template  = '' === $args['is_template'] ? '' : absint( $args['is_template'] );
		$orderby      = in_array( $args['orderby'], array( 'name', 'min_players', 'max_players', 'created_at' ), true ) ? $args['orderby'] : 'name';
		$order        = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'ASC';
		$per_page     = absint( $args['per_page'] );
		$page         = max( 1, absint( $args['page'] ) );
		$offset       = ( $page - 1 ) * $per_page;

		// Build WHERE clause.
		$where = '1=1';
		$where_values = array();

		if ( ! empty( $search ) ) {
			$where .= ' AND (name LIKE %s OR description LIKE %s)';
			$like_search = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_values[] = $like_search;
			$where_values[] = $like_search;
		}

		if ( $min_players > 0 ) {
			$where .= ' AND max_players >= %d';
			$where_values[] = $min_players;
		}

		if ( $max_players > 0 ) {
			$where .= ' AND min_players <= %d';
			$where_values[] = $max_players;
		}

		if ( '' !== $is_template ) {
			$where .= ' AND is_template = %d';
			$where_values[] = $is_template;
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
			$structures = $this->wpdb->get_results(
				$this->wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$where_values
				)
			);
		} else {
			$structures = $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Decode JSON for each structure.
		if ( ! empty( $structures ) ) {
			foreach ( $structures as $structure ) {
				$structure->places = $this->decode_structure_json( $structure->structure_json );
			}
		}

		return $structures ? $structures : array();
	}

	/**
	 * Get total count of structures
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Query arguments.
	 * @return int Total count.
	 */
	public function get_total( $args = array() ) {
		$defaults = array(
			'search'      => '',
			'min_players' => 0,
			'max_players' => 0,
			'is_template' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize arguments.
		$search      = sanitize_text_field( wp_unslash( $args['search'] ) );
		$min_players = absint( $args['min_players'] );
		$max_players = absint( $args['max_players'] );
		$is_template = '' === $args['is_template'] ? '' : absint( $args['is_template'] );

		// Build WHERE clause.
		$where = '1=1';
		$where_values = array();

		if ( ! empty( $search ) ) {
			$where .= ' AND (name LIKE %s OR description LIKE %s)';
			$like_search = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_values[] = $like_search;
			$where_values[] = $like_search;
		}

		if ( $min_players > 0 ) {
			$where .= ' AND max_players >= %d';
			$where_values[] = $min_players;
		}

		if ( $max_players > 0 ) {
			$where .= ' AND min_players <= %d';
			$where_values[] = $max_players;
		}

		if ( '' !== $is_template ) {
			$where .= ' AND is_template = %d';
			$where_values[] = $is_template;
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
	 * Suggest prize structures for player count
	 *
	 * @since 3.0.0
	 *
	 * @param int $player_count Number of players.
	 * @return array Array of structure objects.
	 */
	public function suggest_for_players( $player_count ) {
		$player_count = absint( $player_count );

		if ( 0 === $player_count ) {
			return array();
		}

		$structures = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE min_players <= %d
				AND max_players >= %d
				ORDER BY is_template DESC, min_players ASC",
				$player_count,
				$player_count
			)
		);

		// Decode JSON for each structure.
		if ( ! empty( $structures ) ) {
			foreach ( $structures as $structure ) {
				$structure->places = $this->decode_structure_json( $structure->structure_json );
			}
		}

		return $structures ? $structures : array();
	}

	/**
	 * Decode structure JSON
	 *
	 * @since 3.0.0
	 *
	 * @param string $json JSON string.
	 * @return array Decoded and sanitized array.
	 */
	private function decode_structure_json( $json ) {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		// Sanitize each place.
		return array_map(
			function( $place ) {
				return array(
					'place'      => isset( $place['place'] ) ? absint( $place['place'] ) : 0,
					'percentage' => isset( $place['percentage'] ) ? floatval( $place['percentage'] ) : 0,
				);
			},
			$decoded
		);
	}

	/**
	 * Calculate example breakdown for $10,000 prize pool
	 *
	 * @since 3.0.0
	 *
	 * @param array $places Array of places with percentages.
	 * @return array Example amounts for each place.
	 */
	private function calculate_example_breakdown( $places ) {
		$example_pool = 10000;
		$breakdown = array();

		foreach ( $places as $place ) {
			$breakdown[] = array(
				'place'  => $place['place'],
				'amount' => round( $example_pool * ( $place['percentage'] / 100 ), 2 ),
			);
		}

		return $breakdown;
	}

	/**
	 * Sanitize structure data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitize_structure_data( $data ) {
		// Sanitize structure JSON.
		$structure_json = '';
		if ( isset( $data['structure_json'] ) ) {
			if ( is_string( $data['structure_json'] ) ) {
				$decoded = json_decode( $data['structure_json'], true );
				if ( is_array( $decoded ) ) {
					$decoded = $this->decode_structure_json( $data['structure_json'] );
					$structure_json = wp_json_encode( $decoded );
				}
			} elseif ( is_array( $data['structure_json'] ) ) {
				$decoded = $this->decode_structure_json( wp_json_encode( $data['structure_json'] ) );
				$structure_json = wp_json_encode( $decoded );
			}
		}

		return array(
			'name'           => isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '',
			'description'    => isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '',
			'is_template'    => isset( $data['is_template'] ) ? absint( $data['is_template'] ) : 0,
			'min_players'    => isset( $data['min_players'] ) ? absint( $data['min_players'] ) : 1,
			'max_players'    => isset( $data['max_players'] ) ? absint( $data['max_players'] ) : 999,
			'structure_json' => $structure_json,
		);
	}

	/**
	 * Validate structure data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Sanitized data to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_structure_data( $data ) {
		$errors = array();

		// Validate name.
		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Structure name is required.', 'poker-tournament-import' );
		}

		if ( strlen( $data['name'] ) > 255 ) {
			$errors[] = __( 'Structure name must be 255 characters or less.', 'poker-tournament-import' );
		}

		// Validate player counts.
		if ( $data['min_players'] < 1 ) {
			$errors[] = __( 'Minimum players must be at least 1.', 'poker-tournament-import' );
		}

		if ( $data['max_players'] < $data['min_players'] ) {
			$errors[] = __( 'Maximum players must be greater than or equal to minimum players.', 'poker-tournament-import' );
		}

		// Validate structure JSON.
		if ( empty( $data['structure_json'] ) ) {
			$errors[] = __( 'Prize structure is required.', 'poker-tournament-import' );
		} else {
			$places = json_decode( $data['structure_json'], true );

			if ( ! is_array( $places ) || empty( $places ) ) {
				$errors[] = __( 'Invalid prize structure format.', 'poker-tournament-import' );
			} else {
				// Validate percentages sum to 100.
				$total = 0;
				foreach ( $places as $place ) {
					if ( ! isset( $place['percentage'] ) ) {
						$errors[] = __( 'Each place must have a percentage.', 'poker-tournament-import' );
						break;
					}

					$percentage = floatval( $place['percentage'] );
					if ( $percentage < 0 || $percentage > 100 ) {
						$errors[] = __( 'Percentages must be between 0 and 100.', 'poker-tournament-import' );
						break;
					}

					$total += $percentage;
				}

				// Allow small rounding error (0.01%).
				if ( abs( $total - 100 ) > 0.01 ) {
					$errors[] = sprintf(
						/* translators: %s: actual total percentage */
						__( 'Percentages must sum to 100%%. Current total: %s%%', 'poker-tournament-import' ),
						number_format( $total, 2 )
					);
				}

				// Validate places are sequential.
				$expected_place = 1;
				foreach ( $places as $place ) {
					if ( absint( $place['place'] ) !== $expected_place ) {
						$errors[] = __( 'Places must be sequential starting from 1.', 'poker-tournament-import' );
						break;
					}
					$expected_place++;
				}

				// Validate places don't exceed max players.
				if ( count( $places ) > $data['max_players'] ) {
					$errors[] = __( 'Number of paid places cannot exceed maximum players.', 'poker-tournament-import' );
				}
			}
		}

		// Return errors if any.
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}
}
