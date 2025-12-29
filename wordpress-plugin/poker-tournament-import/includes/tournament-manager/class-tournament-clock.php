<?php
/**
 * Tournament Clock Manager
 *
 * Handles tournament clock operations: start, pause, resume, advance level
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
 * TDWP Tournament Clock Class
 *
 * Manages tournament timing and level progression
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Clock {

	/**
	 * Live state manager
	 *
	 * @since 3.1.0
	 * @var TDWP_Tournament_Live
	 */
	private $live_manager;

	/**
	 * Events manager
	 *
	 * @since 3.1.0
	 * @var TDWP_Tournament_Events
	 */
	private $events_manager;

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		$this->live_manager   = new TDWP_Tournament_Live();
		$this->events_manager = new TDWP_Tournament_Events();
	}

	/**
	 * Start tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int  $tournament_id Tournament ID.
	 * @param int  $template_id   Template ID.
	 * @param int  $total_players Total starting players.
	 * @param bool $is_practice   Whether this is a practice tournament.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function start( $tournament_id, $template_id, $total_players = 0, $is_practice = false ) {
		$tournament_id = absint( $tournament_id );
		$template_id   = absint( $template_id );
		$total_players = absint( $total_players );
		$is_practice   = rest_sanitize_boolean( $is_practice );

		// Check if already started.
		$existing = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( $existing ) {
			return new WP_Error(
				'already_started',
				__( 'Tournament has already been started.', 'poker-tournament-import' )
			);
		}

		// Get blind schedule duration for first level.
		$template     = $this->get_tournament_template( $template_id );
		$time_seconds = $this->get_level_duration_seconds( $template );

		// Log start operation.
		TDWP_Debug_Logger::log(
			'CLOCK',
			'START Tournament #' . $tournament_id,
			array(
				'template_id'   => $template_id,
				'total_players' => $total_players,
				'time_seconds'  => $time_seconds,
				'is_practice'   => $is_practice,
			)
		);

		// Create live state.
		$state_id = $this->live_manager->create(
			array(
				'tournament_id'     => $tournament_id,
				'template_id'       => $template_id,
				'status'            => 'running',
				'current_level'     => 1,
				'time_remaining'    => $time_seconds,
				'total_players'     => $total_players,
				'remaining_players' => $total_players,
				'total_rebuys'      => 0,
				'total_addons'      => 0,
				'prize_pool'        => 0,
				'is_practice'       => $is_practice,
			)
		);

		if ( is_wp_error( $state_id ) ) {
			return $state_id;
		}

		// Update started_at timestamp.
		$this->live_manager->update(
			$state_id,
			array(
				'started_at' => current_time( 'mysql' ),
			)
		);

		// Log event.
		$this->events_manager->log(
			$tournament_id,
			'tournament_started',
			array(
				'template_id'   => $template_id,
				'total_players' => $total_players,
			)
		);

		/**
		 * Fires after tournament is started
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 * @param int $state_id      Live state ID.
		 */
		do_action( 'tdwp_tournament_started', $tournament_id, $state_id );

		return true;
	}

	/**
	 * Pause tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @param int $time_remaining Optional. Current time remaining in seconds from client.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function pause( $tournament_id, $time_remaining = null ) {
		$tournament_id = absint( $tournament_id );

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( ! $state ) {
			return new WP_Error(
				'not_started',
				__( 'Tournament has not been started.', 'poker-tournament-import' )
			);
		}

		if ( 'running' !== $state->status ) {
			return new WP_Error(
				'not_running',
				__( 'Tournament is not currently running.', 'poker-tournament-import' )
			);
		}

		// Log pause operation.
		TDWP_Debug_Logger::log(
			'CLOCK',
			'PAUSE Tournament #' . $tournament_id,
			array(
				'client_time_remaining' => $time_remaining,
				'db_time_remaining'     => $state->time_remaining,
				'status_before'         => $state->status,
			)
		);

		// Prepare update data
		$update_data = array(
			'status'    => 'paused',
			'paused_at' => current_time( 'mysql' ),
		);

		// Use client time if provided, otherwise keep current database time
		if ( null !== $time_remaining ) {
			$update_data['time_remaining'] = absint( $time_remaining );
		}

		// Update status to paused.
		$result = $this->live_manager->update(
			$state->id,
			$update_data
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log event.
		$this->events_manager->log(
			$tournament_id,
			'tournament_paused',
			array(
				'current_level'  => $state->current_level,
				'time_remaining' => $state->time_remaining,
			)
		);

		/**
		 * Fires after tournament is paused
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 */
		do_action( 'tdwp_tournament_paused', $tournament_id );

		return true;
	}

	/**
	 * Resume tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function resume( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( ! $state ) {
			return new WP_Error(
				'not_started',
				__( 'Tournament has not been started.', 'poker-tournament-import' )
			);
		}

		if ( 'paused' !== $state->status ) {
			return new WP_Error(
				'not_paused',
				__( 'Tournament is not paused.', 'poker-tournament-import' )
			);
		}

		// Log resume operation.
		TDWP_Debug_Logger::log(
			'CLOCK',
			'RESUME Tournament #' . $tournament_id,
			array(
				'time_remaining' => $state->time_remaining,
				'current_level'  => $state->current_level,
				'status_before'  => 'paused',
			)
		);

		// Update status to running.
		$result = $this->live_manager->update(
			$state->id,
			array(
				'status'    => 'running',
				'paused_at' => null,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log event.
		$this->events_manager->log(
			$tournament_id,
			'tournament_resumed',
			array(
				'current_level'  => $state->current_level,
				'time_remaining' => $state->time_remaining,
			)
		);

		/**
		 * Fires after tournament is resumed
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 */
		do_action( 'tdwp_tournament_resumed', $tournament_id );

		return true;
	}

	/**
	 * Advance to next level
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function advance_level( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( ! $state ) {
			return new WP_Error(
				'not_started',
				__( 'Tournament has not been started.', 'poker-tournament-import' )
			);
		}

		// Get next level number.
		$next_level = absint( $state->current_level ) + 1;

		// Get template to calculate time.
		$template     = $this->get_tournament_template( $state->template_id );
		$time_seconds = $this->get_level_duration_seconds( $template );

		// Update to next level.
		$result = $this->live_manager->update(
			$state->id,
			array(
				'current_level'  => $next_level,
				'time_remaining' => $time_seconds,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log event.
		$this->events_manager->log(
			$tournament_id,
			'level_advanced',
			array(
				'previous_level' => $state->current_level,
				'new_level'      => $next_level,
			)
		);

		/**
		 * Fires after level is advanced
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 * @param int $new_level     New level number.
		 */
		do_action( 'tdwp_tournament_level_advanced', $tournament_id, $next_level );

		return true;
	}

	/**
	 * Complete tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function complete( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( ! $state ) {
			return new WP_Error(
				'not_started',
				__( 'Tournament has not been started.', 'poker-tournament-import' )
			);
		}

		// Update status to completed.
		$result = $this->live_manager->update(
			$state->id,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log event.
		$this->events_manager->log(
			$tournament_id,
			'tournament_completed',
			array(
				'final_level'        => $state->current_level,
				'total_players'      => $state->total_players,
				'remaining_players'  => $state->remaining_players,
				'prize_pool'         => $state->prize_pool,
			)
		);

		/**
		 * Fires after tournament is completed
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 */
		do_action( 'tdwp_tournament_completed', $tournament_id );

		return true;
	}

	/**
	 * Update clock time
	 *
	 * Called by heartbeat to decrement time remaining
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @param int $elapsed       Seconds elapsed since last update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function tick( $tournament_id, $elapsed = 15 ) {
		$tournament_id = absint( $tournament_id );
		$elapsed       = absint( $elapsed );

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		if ( ! $state ) {
			return new WP_Error(
				'not_started',
				__( 'Tournament has not been started.', 'poker-tournament-import' )
			);
		}

		// Only tick if running.
		if ( 'running' !== $state->status ) {
			return true; // Not an error, just don't update.
		}

		// Calculate new time remaining.
		$old_time = absint( $state->time_remaining );
		$new_time = max( 0, $old_time - $elapsed );

		// Log tick operation.
		TDWP_Debug_Logger::log_tick( $tournament_id, $elapsed, $old_time, $new_time );

		// Update time.
		$result = $this->live_manager->update(
			$state->id,
			array(
				'time_remaining' => $new_time,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Auto-advance if time expired.
		if ( 0 === $new_time ) {
			$this->advance_level( $tournament_id );
		}

		return true;
	}

	/**
	 * Get tournament template
	 *
	 * @since 3.1.0
	 *
	 * @param int $template_id Template ID.
	 * @return object|null Template object or null.
	 */
	private function get_tournament_template( $template_id ) {
		$template_manager = new TDWP_Tournament_Template();
		return $template_manager->get( $template_id, true );
	}

	/**
	 * Get level duration in seconds
	 *
	 * @since 3.1.0
	 *
	 * @param object $template Template object with blind schedule.
	 * @return int Duration in seconds.
	 */
	private function get_level_duration_seconds( $template ) {
		// Default to 15 minutes.
		$minutes = 15;

		if ( $template && ! empty( $template->blind_schedule ) ) {
			$minutes = absint( $template->blind_schedule->level_duration );
		}

		return $minutes * 60; // Convert to seconds.
	}
}
