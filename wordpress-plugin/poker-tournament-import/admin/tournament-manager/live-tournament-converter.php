<?php
/**
 * Live Tournament Converter
 *
 * Handles conversion of live_tournament posts to historical tournament posts
 * when a tournament is completed.
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
 * Live Tournament Converter class
 *
 * @since 3.1.0
 */
class TDWP_Live_Tournament_Converter {

	/**
	 * Convert live tournament to historical tournament
	 *
	 * @since 3.1.0
	 * @param int $live_tournament_id Live tournament post ID.
	 * @return int|WP_Error Tournament post ID on success, WP_Error on failure.
	 */
	public function convert( $live_tournament_id ) {
		// Verify it's a live_tournament.
		$live_post = get_post( $live_tournament_id );

		if ( ! $live_post || 'live_tournament' !== $live_post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Invalid live tournament', 'poker-tournament-import' ) );
		}

		// Check if already converted.
		$existing_tournament_id = get_post_meta( $live_tournament_id, '_converted_to_tournament_id', true );
		if ( $existing_tournament_id ) {
			return new WP_Error( 'already_converted', __( 'Tournament already converted', 'poker-tournament-import' ) );
		}

		// Create new tournament post.
		$tournament_id = $this->create_tournament_post( $live_tournament_id, $live_post );

		if ( is_wp_error( $tournament_id ) ) {
			return $tournament_id;
		}

		// Copy meta fields.
		$this->copy_meta_fields( $live_tournament_id, $tournament_id );

		// Copy tournament data (players, events, etc.).
		$this->copy_tournament_data( $live_tournament_id, $tournament_id );

		// Update live tournament status and link.
		update_post_meta( $live_tournament_id, '_status', 'completed' );
		update_post_meta( $live_tournament_id, '_converted_to_tournament_id', $tournament_id );
		update_post_meta( $live_tournament_id, '_conversion_date', current_time( 'mysql' ) );

		// Add back-reference on tournament.
		update_post_meta( $tournament_id, '_source_live_tournament_id', $live_tournament_id );

		// Archive the live tournament (set to draft).
		wp_update_post(
			array(
				'ID'          => $live_tournament_id,
				'post_status' => 'draft',
			)
		);

		// Clear this tournament from all users' active tournaments.
		TDWP_Active_Tournament_Manager::clear_tournament_from_all_users( $live_tournament_id );

		/**
		 * Fires after a live tournament is converted
		 *
		 * @since 3.1.0
		 * @param int $tournament_id     New tournament post ID.
		 * @param int $live_tournament_id Source live tournament ID.
		 */
		do_action( 'tdwp_live_tournament_converted', $tournament_id, $live_tournament_id );

		return $tournament_id;
	}

	/**
	 * Create tournament post from live tournament
	 *
	 * @since 3.1.0
	 * @param int     $live_tournament_id Live tournament ID.
	 * @param WP_Post $live_post Live tournament post object.
	 * @return int|WP_Error Tournament ID on success, WP_Error on failure.
	 */
	private function create_tournament_post( $live_tournament_id, $live_post ) {
		// Get final state data.
		global $wpdb;
		$state_table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$state = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$state_table} WHERE tournament_id = %d ORDER BY updated_at DESC LIMIT 1",
				$live_tournament_id
			)
		);

		// Prepare post content with final state.
		$post_content = $this->generate_tournament_content( $live_tournament_id, $state );

		// Create tournament post.
		$tournament_id = wp_insert_post(
			array(
				'post_title'   => $live_post->post_title,
				'post_content' => $post_content,
				'post_type'    => 'tournament',
				'post_status'  => 'publish',
				'post_author'  => $live_post->post_author,
				'post_date'    => $live_post->post_date,
			)
		);

		if ( is_wp_error( $tournament_id ) ) {
			return new WP_Error( 'create_failed', __( 'Failed to create tournament post', 'poker-tournament-import' ) );
		}

		return $tournament_id;
	}

	/**
	 * Generate tournament post content
	 *
	 * @since 3.1.0
	 * @param int    $live_tournament_id Live tournament ID.
	 * @param object $state Final tournament state.
	 * @return string HTML content.
	 */
	private function generate_tournament_content( $live_tournament_id, $state ) {
		$content = '';

		if ( $state ) {
			$content .= '<h2>' . __( 'Tournament Details', 'poker-tournament-import' ) . '</h2>';
			$content .= '<ul>';

			if ( isset( $state->current_level ) ) {
				$content .= '<li>' . sprintf( __( 'Final Level: %d', 'poker-tournament-import' ), $state->current_level ) . '</li>';
			}

			if ( isset( $state->players_remaining ) ) {
				$content .= '<li>' . sprintf( __( 'Players Remaining: %d', 'poker-tournament-import' ), $state->players_remaining ) . '</li>';
			}

			if ( isset( $state->total_chips ) ) {
				$content .= '<li>' . sprintf( __( 'Total Chips: %s', 'poker-tournament-import' ), number_format( $state->total_chips ) ) . '</li>';
			}

			$content .= '</ul>';
		}

		// Add placeholder for results table (to be populated by shortcode later).
		$content .= "\n\n[tournament_results id=\"" . $live_tournament_id . '"]';

		return $content;
	}

	/**
	 * Copy meta fields from live tournament to tournament
	 *
	 * @since 3.1.0
	 * @param int $live_tournament_id Source live tournament ID.
	 * @param int $tournament_id Destination tournament ID.
	 */
	private function copy_meta_fields( $live_tournament_id, $tournament_id ) {
		// Meta fields to copy (with potential renaming).
		$meta_map = array(
			'_buy_in'              => 'buy_in',
			'_starting_chips'      => 'starting_chips',
			'_blind_schedule_id'   => 'blind_schedule_id',
			'_prize_structure_id'  => 'prize_structure_id',
			'_template_id'         => 'template_id',
			'_source_tournament_id' => 'source_tournament_id',
		);

		foreach ( $meta_map as $source_key => $dest_key ) {
			$value = get_post_meta( $live_tournament_id, $source_key, true );

			if ( $value ) {
				update_post_meta( $tournament_id, $dest_key, $value );
			}
		}
	}

	/**
	 * Copy tournament data (players, events, etc.)
	 *
	 * @since 3.1.0
	 * @param int $live_tournament_id Source live tournament ID.
	 * @param int $tournament_id Destination tournament ID.
	 */
	private function copy_tournament_data( $live_tournament_id, $tournament_id ) {
		global $wpdb;

		// Copy final tournament state.
		$this->copy_final_state( $live_tournament_id, $tournament_id, $wpdb );

		// Copy tournament events (history).
		$this->copy_tournament_events( $live_tournament_id, $tournament_id, $wpdb );

		// Copy table/seat data (snapshot at completion).
		$this->copy_table_data( $live_tournament_id, $tournament_id, $wpdb );
	}

	/**
	 * Copy final tournament state
	 *
	 * @since 3.1.0
	 * @param int    $live_tournament_id Source ID.
	 * @param int    $tournament_id Destination ID.
	 * @param wpdb   $wpdb WordPress database object.
	 */
	private function copy_final_state( $live_tournament_id, $tournament_id, $wpdb ) {
		$state_table = $wpdb->prefix . 'tdwp_tournament_live_state';

		// Get final state.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$state = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$state_table} WHERE tournament_id = %d ORDER BY updated_at DESC LIMIT 1",
				$live_tournament_id
			)
		);

		if ( ! $state ) {
			return;
		}

		// Store as meta on tournament (for historical reference).
		$state_data = array(
			'status'            => $state->status,
			'current_level'     => $state->current_level,
			'time_remaining'    => $state->time_remaining,
			'players_remaining' => $state->players_remaining,
			'total_chips'       => $state->total_chips,
			'average_stack'     => $state->average_stack,
			'completed_at'      => $state->updated_at,
		);

		update_post_meta( $tournament_id, '_final_state', $state_data );
	}

	/**
	 * Copy tournament events
	 *
	 * @since 3.1.0
	 * @param int    $live_tournament_id Source ID.
	 * @param int    $tournament_id Destination ID.
	 * @param wpdb   $wpdb WordPress database object.
	 */
	private function copy_tournament_events( $live_tournament_id, $tournament_id, $wpdb ) {
		$events_table = $wpdb->prefix . 'tdwp_tournament_events';

		// Get all events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$events_table} WHERE tournament_id = %d ORDER BY created_at ASC",
				$live_tournament_id
			)
		);

		if ( ! $events ) {
			return;
		}

		// Store as meta (serialized array for historical reference).
		$events_data = array();
		foreach ( $events as $event ) {
			$events_data[] = array(
				'event_type'  => $event->event_type,
				'description' => $event->description,
				'event_data'  => maybe_unserialize( $event->event_data ),
				'created_at'  => $event->created_at,
			);
		}

		update_post_meta( $tournament_id, '_tournament_events', $events_data );
	}

	/**
	 * Copy table and seat data
	 *
	 * @since 3.1.0
	 * @param int    $live_tournament_id Source ID.
	 * @param int    $tournament_id Destination ID.
	 * @param wpdb   $wpdb WordPress database object.
	 */
	private function copy_table_data( $live_tournament_id, $tournament_id, $wpdb ) {
		$tables_table = $wpdb->prefix . 'tdwp_tournament_tables';
		$seats_table  = $wpdb->prefix . 'tdwp_tournament_seats';

		// Get all tables.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tables_table} WHERE tournament_id = %d ORDER BY table_number ASC",
				$live_tournament_id
			)
		);

		if ( ! $tables ) {
			return;
		}

		$tables_data = array();

		foreach ( $tables as $table ) {
			// Get seats for this table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$seats = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$seats_table} WHERE table_id = %d ORDER BY seat_number ASC",
					$table->id
				)
			);

			$seats_data = array();
			foreach ( $seats as $seat ) {
				$seats_data[] = array(
					'seat_number' => $seat->seat_number,
					'player_id'   => $seat->player_id,
					'player_name' => $seat->player_name,
					'chip_count'  => $seat->chip_count,
					'is_active'   => $seat->is_active,
				);
			}

			$tables_data[] = array(
				'table_number' => $table->table_number,
				'status'       => $table->status,
				'seats'        => $seats_data,
			);
		}

		update_post_meta( $tournament_id, '_table_snapshot', $tables_data );
	}

	/**
	 * Check if a live tournament can be converted
	 *
	 * @since 3.1.0
	 * @param int $live_tournament_id Live tournament post ID.
	 * @return bool|WP_Error True if can convert, WP_Error otherwise.
	 */
	public function can_convert( $live_tournament_id ) {
		// Verify it's a live_tournament.
		$live_post = get_post( $live_tournament_id );

		if ( ! $live_post || 'live_tournament' !== $live_post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Invalid live tournament', 'poker-tournament-import' ) );
		}

		// Check if already converted.
		$existing_tournament_id = get_post_meta( $live_tournament_id, '_converted_to_tournament_id', true );
		if ( $existing_tournament_id ) {
			return new WP_Error( 'already_converted', __( 'Tournament already converted', 'poker-tournament-import' ) );
		}

		// Check tournament status.
		$status = get_post_meta( $live_tournament_id, '_status', true );
		if ( 'running' === $status ) {
			return new WP_Error( 'still_running', __( 'Cannot convert a running tournament', 'poker-tournament-import' ) );
		}

		return true;
	}
}
