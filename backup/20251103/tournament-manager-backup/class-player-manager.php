<?php
/**
 * Player Manager Class
 *
 * Handles CRUD operations for player posts using WordPress post system.
 * Integrates with existing player custom post type and meta data.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player Manager class
 *
 * @since 3.0.0
 */
class TDWP_Player_Manager {

	/**
	 * Create new player post
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Player data array.
	 * @return int|WP_Error Player post ID on success, WP_Error on failure.
	 */
	public function create( $data ) {
		// Sanitize and validate.
		$sanitized_data = $this->sanitize_player_data( $data );
		$validation     = $this->validate_player_data( $sanitized_data );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for duplicate email if provided.
		if ( ! empty( $sanitized_data['email'] ) ) {
			$duplicate = $this->find_by_email( $sanitized_data['email'] );
			if ( $duplicate ) {
				return new WP_Error(
					'duplicate_email',
					sprintf(
						/* translators: %s: email address */
						__( 'A player with email %s already exists.', 'poker-tournament-import' ),
						$sanitized_data['email']
					)
				);
			}
		}

		// Generate UUID if not provided.
		if ( empty( $sanitized_data['uuid'] ) ) {
			$sanitized_data['uuid'] = $this->generate_uuid();
		}

		// Create player post.
		$post_data = array(
			'post_title'   => $sanitized_data['name'],
			'post_content' => ! empty( $sanitized_data['bio'] ) ? $sanitized_data['bio'] : '',
			'post_status'  => ! empty( $sanitized_data['status'] ) ? $sanitized_data['status'] : 'publish',
			'post_type'    => 'player',
		);

		$player_id = wp_insert_post( $post_data );

		if ( is_wp_error( $player_id ) || ! $player_id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create player post.', 'poker-tournament-import' ) );
		}

		// Save player meta data.
		$this->save_player_meta( $player_id, $sanitized_data );

		return $player_id;
	}

	/**
	 * Update existing player post
	 *
	 * @since 3.0.0
	 *
	 * @param int   $player_id Player post ID.
	 * @param array $data      Updated player data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $player_id, $data ) {
		// Verify player exists.
		$player = get_post( $player_id );
		if ( ! $player || 'player' !== $player->post_type ) {
			return new WP_Error( 'invalid_player', __( 'Player not found.', 'poker-tournament-import' ) );
		}

		// Sanitize and validate.
		$sanitized_data = $this->sanitize_player_data( $data );
		$validation     = $this->validate_player_data( $sanitized_data, $player_id );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for duplicate email if changed.
		if ( ! empty( $sanitized_data['email'] ) ) {
			$current_email = get_post_meta( $player_id, 'player_email', true );
			if ( $sanitized_data['email'] !== $current_email ) {
				$duplicate = $this->find_by_email( $sanitized_data['email'] );
				if ( $duplicate && $duplicate !== $player_id ) {
					return new WP_Error(
						'duplicate_email',
						sprintf(
							/* translators: %s: email address */
							__( 'A player with email %s already exists.', 'poker-tournament-import' ),
							$sanitized_data['email']
						)
					);
				}
			}
		}

		// Update player post.
		$post_data = array(
			'ID' => $player_id,
		);

		if ( isset( $sanitized_data['name'] ) ) {
			$post_data['post_title'] = $sanitized_data['name'];
		}

		if ( isset( $sanitized_data['bio'] ) ) {
			$post_data['post_content'] = $sanitized_data['bio'];
		}

		if ( isset( $sanitized_data['status'] ) ) {
			$post_data['post_status'] = $sanitized_data['status'];
		}

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update player post.', 'poker-tournament-import' ) );
		}

		// Update player meta data.
		$this->save_player_meta( $player_id, $sanitized_data );

		return true;
	}

	/**
	 * Delete player post
	 *
	 * @since 3.0.0
	 *
	 * @param int  $player_id     Player post ID.
	 * @param bool $force_delete  Whether to bypass trash and force delete.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $player_id, $force_delete = false ) {
		// Verify player exists.
		$player = get_post( $player_id );
		if ( ! $player || 'player' !== $player->post_type ) {
			return new WP_Error( 'invalid_player', __( 'Player not found.', 'poker-tournament-import' ) );
		}

		$result = wp_delete_post( $player_id, $force_delete );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete player.', 'poker-tournament-import' ) );
		}

		return true;
	}

	/**
	 * Get player by ID
	 *
	 * @since 3.0.0
	 *
	 * @param int  $player_id     Player post ID.
	 * @param bool $with_meta     Whether to include meta data.
	 * @param bool $with_stats    Whether to include tournament statistics.
	 * @return array|WP_Error Player data on success, WP_Error on failure.
	 */
	public function get( $player_id, $with_meta = true, $with_stats = false ) {
		$player = get_post( $player_id );

		if ( ! $player || 'player' !== $player->post_type ) {
			return new WP_Error( 'invalid_player', __( 'Player not found.', 'poker-tournament-import' ) );
		}

		$player_data = array(
			'id'     => $player->ID,
			'name'   => $player->post_title,
			'bio'    => $player->post_content,
			'status' => $player->post_status,
			'date'   => $player->post_date,
		);

		if ( $with_meta ) {
			$player_data['meta'] = $this->get_player_meta( $player_id );
		}

		if ( $with_stats ) {
			$player_data['stats'] = $this->get_player_stats( $player_id );
		}

		return $player_data;
	}

	/**
	 * Get all players with filters
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of players with pagination info.
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
			'status'   => array( 'publish', 'draft' ),
			'orderby'  => 'title',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WP_Query args.
		$query_args = array(
			'post_type'      => 'player',
			'posts_per_page' => $args['per_page'],
			'paged'          => $args['page'],
			'post_status'    => $args['status'],
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
		);

		// Add search.
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		// Execute query.
		$query = new WP_Query( $query_args );

		$players = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$player_id = get_the_ID();

				$players[] = array(
					'id'    => $player_id,
					'name'  => get_the_title(),
					'email' => get_post_meta( $player_id, 'player_email', true ),
					'uuid'  => get_post_meta( $player_id, 'player_uuid', true ),
					'status' => get_post_status(),
					'date'  => get_the_date( 'Y-m-d H:i:s' ),
				);
			}
			wp_reset_postdata();
		}

		return array(
			'players'      => $players,
			'total'        => $query->found_posts,
			'total_pages'  => $query->max_num_pages,
			'current_page' => $args['page'],
		);
	}

	/**
	 * Find player by email
	 *
	 * @since 3.0.0
	 *
	 * @param string $email Player email address.
	 * @return int|false Player ID if found, false otherwise.
	 */
	public function find_by_email( $email ) {
		$args = array(
			'post_type'      => 'player',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => 'player_email',
					'value'   => sanitize_email( $email ),
					'compare' => '=',
				),
			),
		);

		$players = get_posts( $args );

		return ! empty( $players ) ? $players[0]->ID : false;
	}

	/**
	 * Find player by UUID
	 *
	 * @since 3.0.0
	 *
	 * @param string $uuid Player UUID.
	 * @return int|false Player ID if found, false otherwise.
	 */
	public function find_by_uuid( $uuid ) {
		$args = array(
			'post_type'      => 'player',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => 'player_uuid',
					'value'   => sanitize_text_field( $uuid ),
					'compare' => '=',
				),
			),
		);

		$players = get_posts( $args );

		return ! empty( $players ) ? $players[0]->ID : false;
	}

	/**
	 * Search players by name or email
	 *
	 * @since 3.0.0
	 *
	 * @param string $term    Search term.
	 * @param int    $limit   Maximum results to return.
	 * @return array Array of matching players.
	 */
	public function search( $term, $limit = 10 ) {
		global $wpdb;

		$term = sanitize_text_field( $term );

		// Search in post title (player name) and email meta.
		$query = $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, em.meta_value as email
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} em ON p.ID = em.post_id AND em.meta_key = 'player_email'
			WHERE p.post_type = 'player'
			AND p.post_status IN ('publish', 'draft')
			AND (
				p.post_title LIKE %s
				OR em.meta_value LIKE %s
			)
			ORDER BY p.post_title ASC
			LIMIT %d",
			'%' . $wpdb->esc_like( $term ) . '%',
			'%' . $wpdb->esc_like( $term ) . '%',
			$limit
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		$players = array();
		foreach ( $results as $result ) {
			$players[] = array(
				'id'    => $result->ID,
				'name'  => $result->post_title,
				'email' => $result->email,
			);
		}

		return $players;
	}

	/**
	 * Sanitize player data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Raw player data.
	 * @return array Sanitized player data.
	 */
	private function sanitize_player_data( $data ) {
		$sanitized = array();

		if ( isset( $data['name'] ) ) {
			$sanitized['name'] = sanitize_text_field( wp_unslash( $data['name'] ) );
		}

		if ( isset( $data['email'] ) ) {
			$sanitized['email'] = sanitize_email( wp_unslash( $data['email'] ) );
		}

		if ( isset( $data['phone'] ) ) {
			$sanitized['phone'] = sanitize_text_field( wp_unslash( $data['phone'] ) );
		}

		if ( isset( $data['bio'] ) ) {
			$sanitized['bio'] = wp_kses_post( wp_unslash( $data['bio'] ) );
		}

		if ( isset( $data['avatar_url'] ) ) {
			$sanitized['avatar_url'] = esc_url_raw( wp_unslash( $data['avatar_url'] ) );
		}

		if ( isset( $data['uuid'] ) ) {
			$sanitized['uuid'] = sanitize_text_field( wp_unslash( $data['uuid'] ) );
		}

		if ( isset( $data['status'] ) ) {
			$valid_statuses = array( 'publish', 'draft', 'pending', 'private' );
			$status         = sanitize_text_field( wp_unslash( $data['status'] ) );
			$sanitized['status'] = in_array( $status, $valid_statuses, true ) ? $status : 'publish';
		}

		return $sanitized;
	}

	/**
	 * Validate player data
	 *
	 * @since 3.0.0
	 *
	 * @param array $data       Sanitized player data.
	 * @param int   $player_id  Optional. Player ID if updating.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_player_data( $data, $player_id = 0 ) {
		// Name is required for new players.
		if ( ! $player_id && empty( $data['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Player name is required.', 'poker-tournament-import' ) );
		}

		// Name length validation.
		if ( isset( $data['name'] ) && strlen( $data['name'] ) > 255 ) {
			return new WP_Error( 'invalid_name_length', __( 'Player name must be 255 characters or less.', 'poker-tournament-import' ) );
		}

		// Email validation.
		if ( isset( $data['email'] ) && ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'poker-tournament-import' ) );
		}

		return true;
	}

	/**
	 * Save player meta data
	 *
	 * @since 3.0.0
	 *
	 * @param int   $player_id Player post ID.
	 * @param array $data      Sanitized player data.
	 * @return void
	 */
	private function save_player_meta( $player_id, $data ) {
		if ( isset( $data['uuid'] ) ) {
			update_post_meta( $player_id, 'player_uuid', $data['uuid'] );
		}

		if ( isset( $data['email'] ) ) {
			update_post_meta( $player_id, 'player_email', $data['email'] );
		}

		if ( isset( $data['phone'] ) ) {
			update_post_meta( $player_id, 'player_phone', $data['phone'] );
		}

		if ( isset( $data['avatar_url'] ) ) {
			update_post_meta( $player_id, 'player_avatar_url', $data['avatar_url'] );
		}

		// Set registration date if not already set.
		if ( ! get_post_meta( $player_id, 'player_registration_date', true ) ) {
			update_post_meta( $player_id, 'player_registration_date', current_time( 'mysql' ) );
		}
	}

	/**
	 * Get player meta data
	 *
	 * @since 3.0.0
	 *
	 * @param int $player_id Player post ID.
	 * @return array Player meta data.
	 */
	private function get_player_meta( $player_id ) {
		return array(
			'uuid'              => get_post_meta( $player_id, 'player_uuid', true ),
			'email'             => get_post_meta( $player_id, 'player_email', true ),
			'phone'             => get_post_meta( $player_id, 'player_phone', true ),
			'avatar_url'        => get_post_meta( $player_id, 'player_avatar_url', true ),
			'registration_date' => get_post_meta( $player_id, 'player_registration_date', true ),
		);
	}

	/**
	 * Get player tournament statistics
	 *
	 * @since 3.0.0
	 *
	 * @param int $player_id Player post ID.
	 * @return array Player statistics.
	 */
	private function get_player_stats( $player_id ) {
		global $wpdb;

		$player_uuid = get_post_meta( $player_id, 'player_uuid', true );

		if ( ! $player_uuid ) {
			return array(
				'tournaments'  => 0,
				'wins'         => 0,
				'final_tables' => 0,
				'total_winnings' => 0,
				'average_finish' => 0,
			);
		}

		$table_name = $wpdb->prefix . 'poker_tournament_players';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as tournaments,
					SUM(CASE WHEN finish_position = 1 THEN 1 ELSE 0 END) as wins,
					SUM(CASE WHEN finish_position <= 9 THEN 1 ELSE 0 END) as final_tables,
					SUM(winnings) as total_winnings,
					AVG(finish_position) as average_finish
				FROM {$table_name}
				WHERE player_id = %s",
				$player_uuid
			),
			ARRAY_A
		);

		return array(
			'tournaments'     => intval( $stats['tournaments'] ),
			'wins'            => intval( $stats['wins'] ),
			'final_tables'    => intval( $stats['final_tables'] ),
			'total_winnings'  => floatval( $stats['total_winnings'] ),
			'average_finish'  => floatval( $stats['average_finish'] ),
		);
	}

	/**
	 * Generate unique UUID
	 *
	 * @since 3.0.0
	 *
	 * @return string UUID.
	 */
	private function generate_uuid() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}
}
