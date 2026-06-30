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
	 * Recommend place count based on player count (tdwp-cma.17)
	 *
	 * PRD §4.2 tiers:
	 *   1–20   → 3 places
	 *   21–50  → 5 places
	 *   51–100 → 9 places
	 *   100+   → ~15 % of field (rounded, minimum 9)
	 *
	 * Pure logic — no DB access — so it is fully unit-testable offline.
	 *
	 * @since 3.5.0
	 *
	 * @param int $player_count Total players registered.
	 * @return int Recommended number of paid places.
	 */
	public function recommend_place_count( $player_count ) {
		$player_count = absint( $player_count );

		if ( $player_count <= 0 ) {
			return 0;
		}

		if ( $player_count <= 20 ) {
			return 3;
		}

		if ( $player_count <= 50 ) {
			return 5;
		}

		if ( $player_count <= 100 ) {
			return 9;
		}

		// 100+ → 15 % of field, minimum 9.
		return max( 9, (int) round( 0.15 * $player_count ) );
	}

	/**
	 * Generate a suggested prize structure for a given player count (tdwp-cma.17, .18)
	 *
	 * Returns an array of place entries [{ place, percentage }] whose percentages
	 * sum to exactly 100.
	 *
	 * Supported styles (tdwp-cma.18):
	 *  - 'standard' (default): decreasing-weight distribution matching industry
	 *    norms; canonical tables for 3/5/9 places, computed decay for larger fields.
	 *  - 'top_heavy': strongly concentrates prizes at the top — approximately
	 *    65 % / 25 % / 10 % split across the first three positions; extra places
	 *    (when the field recommends more than 3) receive a small equal share carved
	 *    from the bottom percentages while the top-three ratio is maintained.
	 *  - 'flat': distributes prizes as evenly as possible; each place receives
	 *    roughly 100/N %, with remainders absorbed into place 1.
	 *
	 * Pure logic — no DB access — so it is fully unit-testable offline.
	 *
	 * @since 3.5.0
	 *
	 * @param int    $player_count Total players registered.
	 * @param string $style        Distribution style: 'standard', 'top_heavy', or 'flat'.
	 * @return array Array of ['place' => int, 'percentage' => float].
	 */
	public function generate_suggested_structure( $player_count, $style = 'standard' ) {
		$place_count = $this->recommend_place_count( $player_count );

		if ( 0 === $place_count ) {
			return array();
		}

		if ( 'top_heavy' === $style ) {
			$percentages = $this->compute_top_heavy_percentages( $place_count );
		} elseif ( 'flat' === $style ) {
			$percentages = $this->compute_flat_percentages( $place_count );
		} else {
			// 'standard' — original canonical / decay logic.
			$canonical = array(
				3 => array( 50.00, 30.00, 20.00 ),
				5 => array( 40.00, 25.00, 15.00, 12.00, 8.00 ),
				9 => array( 30.00, 20.00, 14.00, 10.00, 8.00, 7.00, 5.00, 4.00, 2.00 ),
			);

			if ( isset( $canonical[ $place_count ] ) ) {
				$percentages = $canonical[ $place_count ];
			} else {
				$percentages = $this->compute_decay_percentages( $place_count );
			}
		}

		$structure = array();
		foreach ( $percentages as $index => $pct ) {
			$structure[] = array(
				'place'      => $index + 1,
				'percentage' => $pct,
			);
		}

		return $structure;
	}

	/**
	 * Compute top-heavy percentage distribution (tdwp-cma.18)
	 *
	 * The top three places receive approximately 65 / 25 / 10 of the total,
	 * scaled so that when more places are paid the extra places each receive a
	 * small equal slice carved proportionally from the top-3 shares. The result
	 * always sums to exactly 100.
	 *
	 * For 1-place fields: 100 %.
	 * For 2-place fields: 75 % / 25 %.
	 * For 3+ place fields: top-3 nominally 65/25/10, remaining split evenly from
	 * the remainder after assigning the extra-place budget.
	 *
	 * @since 3.7.0
	 *
	 * @param int $place_count Number of paid places.
	 * @return float[] Percentages (0-indexed), summing to exactly 100.
	 */
	private function compute_top_heavy_percentages( $place_count ) {
		if ( 1 === $place_count ) {
			return array( 100.00 );
		}

		if ( 2 === $place_count ) {
			return array( 75.00, 25.00 );
		}

		// For 3+ places: allocate a small equal share per extra place, then
		// distribute the remaining 100 in 65/25/10 ratio among the top three.
		$extra_places = $place_count - 3;

		if ( 0 === $extra_places ) {
			$percentages = array( 65.00, 25.00, 10.00 );
		} else {
			// Give each extra place a fixed 3 % slice, cap so top-3 still dominates.
			$extra_pct_each = min( 3.0, round( 20.0 / max( 1, $extra_places ), 2 ) );
			$extra_total    = round( $extra_pct_each * $extra_places, 2 );
			$top_budget     = 100.0 - $extra_total;

			// Scale the 65/25/10 ratio into the top budget.
			$top_ratios = array( 65, 25, 10 );
			$ratio_sum  = 100; // they sum to 100 by definition.

			$percentages = array();
			$running     = 0.0;
			$last_top    = 2; // index of 3rd place.

			foreach ( $top_ratios as $idx => $ratio ) {
				if ( $idx === $last_top ) {
					$pct = round( $top_budget - $running, 2 );
				} else {
					$pct     = round( ( $ratio / $ratio_sum ) * $top_budget, 2 );
					$running += $pct;
				}
				$percentages[] = $pct;
			}

			// Extra places — all equal, last one closes to exactly 100.
			$running_extra = 0.0;
			for ( $i = 0; $i < $extra_places; $i++ ) {
				if ( $i === $extra_places - 1 ) {
					$percentages[] = round( $extra_total - $running_extra, 2 );
				} else {
					$percentages[] = $extra_pct_each;
					$running_extra += $extra_pct_each;
				}
			}
		}

		// Final guard: force sum to exactly 100 by adjusting place 1.
		$total = array_sum( $percentages );
		if ( abs( $total - 100.0 ) > 0.001 ) {
			$percentages[0] = round( $percentages[0] + ( 100.0 - $total ), 2 );
		}

		return $percentages;
	}

	/**
	 * Compute flat (roughly even) percentage distribution (tdwp-cma.18)
	 *
	 * Each place receives floor(100/N) %, and the remainder is distributed one
	 * percentage point at a time starting from place 1. The last place's value is
	 * calculated as 100 minus the running total to guarantee exactness.
	 *
	 * @since 3.7.0
	 *
	 * @param int $place_count Number of paid places.
	 * @return float[] Percentages (0-indexed), summing to exactly 100.
	 */
	private function compute_flat_percentages( $place_count ) {
		if ( $place_count <= 0 ) {
			return array();
		}

		$base        = floor( ( 100 / $place_count ) * 100 ) / 100; // floor to 2dp.
		$percentages = array_fill( 0, $place_count, $base );

		// Distribute remainder (always < 1 after floor) into place 1.
		$running = round( $base * $place_count, 2 );
		$remainder = round( 100.0 - $running, 2 );

		// Force last entry to close to exactly 100 regardless of rounding path.
		$percentages[ $place_count - 1 ] = round( 100.0 - ( $base * ( $place_count - 1 ) ), 2 );

		// Absorb any leftover into place 1 (first place).
		$actual_total = array_sum( $percentages );
		if ( abs( $actual_total - 100.0 ) > 0.001 ) {
			$percentages[0] = round( $percentages[0] + ( 100.0 - $actual_total ), 2 );
		}

		return $percentages;
	}

	/**
	 * Compute a decaying percentage distribution for an arbitrary place count
	 *
	 * Uses weight = (n - place + 1)^1.5 for each place to model a natural
	 * top-heavy prize pool, then normalises to exactly 100.
	 *
	 * @since 3.5.0
	 *
	 * @param int $place_count Number of paid places.
	 * @return float[] Percentages indexed from 0, summing to exactly 100.
	 */
	private function compute_decay_percentages( $place_count ) {
		$weights = array();
		for ( $i = 1; $i <= $place_count; $i++ ) {
			$weights[] = pow( $place_count - $i + 1, 1.5 );
		}

		$total_weight = array_sum( $weights );

		// Round each share to two decimal places.
		$percentages = array();
		$running     = 0.0;
		$last_index  = $place_count - 1;

		foreach ( $weights as $index => $weight ) {
			if ( $index === $last_index ) {
				// Force last place to close out to exactly 100.
				$percentages[] = round( 100.0 - $running, 2 );
			} else {
				$pct           = round( ( $weight / $total_weight ) * 100, 2 );
				$percentages[] = $pct;
				$running      += $pct;
			}
		}

		return $percentages;
	}

	/**
	 * Decode structure JSON
	 *
	 * Backward-compatible: old rows with only {place, percentage} are decoded with
	 * sensible defaults for the new keys (amount null, locked false,
	 * recipient_player_id null, display true).
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

		// Sanitize each place — carry all known keys with safe defaults.
		return array_map(
			function( $place ) {
				// Fixed dollar amount: null when absent or explicitly null.
				$amount = null;
				if ( isset( $place['amount'] ) && null !== $place['amount'] ) {
					$amount = floatval( $place['amount'] );
					if ( $amount < 0 ) {
						$amount = 0.0;
					}
				}

				return array(
					'place'               => isset( $place['place'] ) ? absint( $place['place'] ) : 0,
					'percentage'          => isset( $place['percentage'] ) ? floatval( $place['percentage'] ) : 0.0,
					'amount'              => $amount,
					'locked'              => isset( $place['locked'] ) ? (bool) $place['locked'] : false,
					'recipient_player_id' => ( isset( $place['recipient_player_id'] ) && null !== $place['recipient_player_id'] )
						? absint( $place['recipient_player_id'] )
						: null,
					'display'             => isset( $place['display'] ) ? (bool) $place['display'] : true,
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
				/*
				 * Validate each place individually:
				 *  - A place is valid if it has EITHER a percentage (>=0) OR a fixed
				 *    dollar amount (>0). Both may be present on the same place; the
				 *    calculator will use fixed amount when it is set.
				 *  - A place flagged locked=true is exempt from the percentage-sum check
				 *    because its value is pinned regardless of the pot size.
				 *  - Fixed-amount places (amount > 0) are also excluded from the
				 *    percentage-sum check for the same reason.
				 */
				$percentage_total          = 0.0;
				$has_percentage_place_error = false;

				foreach ( $places as $place ) {
					$has_percentage = isset( $place['percentage'] ) && floatval( $place['percentage'] ) >= 0;
					$has_amount     = isset( $place['amount'] ) && null !== $place['amount'] && floatval( $place['amount'] ) > 0;
					$is_locked      = isset( $place['locked'] ) && (bool) $place['locked'];

					// Each place must provide at least one of: percentage or fixed amount.
					if ( ! $has_percentage && ! $has_amount ) {
						$errors[] = __( 'Each place must have either a percentage or a fixed dollar amount.', 'poker-tournament-import' );
						$has_percentage_place_error = true;
						break;
					}

					// Percentage range check (when present).
					if ( $has_percentage ) {
						$percentage = floatval( $place['percentage'] );
						if ( $percentage < 0 || $percentage > 100 ) {
							$errors[] = __( 'Percentages must be between 0 and 100.', 'poker-tournament-import' );
							$has_percentage_place_error = true;
							break;
						}

						// Accumulate only for unlocked, non-fixed-amount places.
						if ( ! $is_locked && ! $has_amount ) {
							$percentage_total += $percentage;
						}
					}
				}

				// The percentage-sum rule applies only to the unlocked, percentage-based
				// places. Allow a 0.01 % rounding tolerance.
				if ( ! $has_percentage_place_error && $percentage_total > 0 && abs( $percentage_total - 100 ) > 0.01 ) {
					$errors[] = sprintf(
						/* translators: %s: actual total percentage */
						__( 'Percentages for unlocked places must sum to 100%%. Current total: %s%%', 'poker-tournament-import' ),
						number_format( $percentage_total, 2 )
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
