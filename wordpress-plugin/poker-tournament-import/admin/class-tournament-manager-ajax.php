<?php
/**
 * Tournament Manager AJAX Handler
 *
 * Handles all AJAX endpoints for Phase 2 tournament management
 * Includes timer controls and table management operations
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tournament Manager AJAX class
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Manager_AJAX {

	/**
	 * Register AJAX handlers
	 *
	 * @since 3.1.0
	 */
	public static function init() {
		// Timer endpoints
		add_action( 'wp_ajax_tdwp_tm_start', array( __CLASS__, 'start_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_pause', array( __CLASS__, 'pause_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_resume', array( __CLASS__, 'resume_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_advance_level', array( __CLASS__, 'advance_level' ) );
		add_action( 'wp_ajax_tdwp_tm_add_time', array( __CLASS__, 'add_time' ) );
		add_action( 'wp_ajax_tdwp_tm_start_break', array( __CLASS__, 'start_break' ) );
		add_action( 'wp_ajax_tdwp_tm_end_break', array( __CLASS__, 'end_break' ) );
		add_action( 'wp_ajax_tdwp_tm_finish', array( __CLASS__, 'finish_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_trash', array( __CLASS__, 'trash_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_get_state', array( __CLASS__, 'get_state' ) );

		// Table management endpoints
		add_action( 'wp_ajax_tdwp_tm_add_table', array( __CLASS__, 'add_table' ) );
		add_action( 'wp_ajax_tdwp_tm_remove_table', array( __CLASS__, 'remove_table' ) );
		add_action( 'wp_ajax_tdwp_tm_move_player', array( __CLASS__, 'move_player' ) );
		add_action( 'wp_ajax_tdwp_tm_unseat_player', array( __CLASS__, 'unseat_player' ) );
		add_action( 'wp_ajax_tdwp_tm_auto_seat', array( __CLASS__, 'auto_seat_players' ) );
		add_action( 'wp_ajax_tdwp_tm_calculate_balance', array( __CLASS__, 'calculate_balance' ) );
		add_action( 'wp_ajax_tdwp_tm_execute_balance', array( __CLASS__, 'execute_balance' ) );
		add_action( 'wp_ajax_tdwp_tm_break_table', array( __CLASS__, 'break_table' ) );
		add_action( 'wp_ajax_tdwp_tm_get_tables', array( __CLASS__, 'get_tables' ) );

		// Player management endpoints (Phase 1 Beta16-17)
		add_action( 'wp_ajax_tdwp_tm_add_player', array( __CLASS__, 'add_player_to_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_remove_player', array( __CLASS__, 'remove_player_from_tournament' ) );
		add_action( 'wp_ajax_tdwp_tm_update_player_status', array( __CLASS__, 'update_player_status' ) );
		add_action( 'wp_ajax_tdwp_tm_process_buyins', array( __CLASS__, 'process_buyins' ) );

		// Live player operations endpoints (Phase 2 Beta22+)
		add_action( 'wp_ajax_tdwp_tm_bust_player', array( __CLASS__, 'bust_player' ) );
		add_action( 'wp_ajax_tdwp_tm_reentry_player', array( __CLASS__, 'reentry_player' ) );
		add_action( 'wp_ajax_tdwp_tm_process_declined_reentry', array( __CLASS__, 'process_declined_reentry' ) );
		add_action( 'wp_ajax_tdwp_tm_process_rebuy', array( __CLASS__, 'process_rebuy' ) );
		add_action( 'wp_ajax_tdwp_tm_process_addon', array( __CLASS__, 'process_addon' ) );
		add_action( 'wp_ajax_tdwp_tm_update_chip_count', array( __CLASS__, 'update_chip_count' ) );

		// Transaction log endpoint (Phase 2 Completion v3.2.0)
		add_action( 'wp_ajax_tdwp_tm_get_transaction_log', array( __CLASS__, 'get_transaction_log' ) );
	}

	/**
	 * Verify nonce and capabilities
	 *
	 * @since 3.1.0
	 * @return bool True if valid
	 */
	private static function verify_request() {
		check_ajax_referer( 'tdwp_tournament_manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'poker-tournament-import' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * Start tournament
	 *
	 * @since 3.1.0
	 */
	public static function start_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$level_duration = isset( $_POST['level_duration'] ) ? intval( $_POST['level_duration'] ) : 900; // 15 min default

		TDWP_Debug_Logger::log(
			'AJAX',
			'START AJAX called',
			array(
				'tournament_id'  => $tournament_id,
				'level_duration' => $level_duration,
			)
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::start( $tournament_id, $level_duration );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Tournament started', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to start tournament', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Pause tournament
	 *
	 * @since 3.1.0
	 */
	public static function pause_tournament() {
		self::verify_request();

		$tournament_id   = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$time_remaining  = isset( $_POST['time_remaining'] ) ? intval( $_POST['time_remaining'] ) : 0;

		TDWP_Debug_Logger::log(
			'AJAX',
			'PAUSE AJAX called',
			array(
				'tournament_id'  => $tournament_id,
				'time_remaining' => $time_remaining,
			)
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::pause( $tournament_id, $time_remaining );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Tournament paused', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to pause tournament', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Resume tournament
	 *
	 * @since 3.1.0
	 */
	public static function resume_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		TDWP_Debug_Logger::log(
			'AJAX',
			'RESUME AJAX called',
			array( 'tournament_id' => $tournament_id )
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::resume( $tournament_id );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Tournament resumed', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to resume tournament', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Advance to next level
	 *
	 * @since 3.1.0
	 */
	public static function advance_level() {
		self::verify_request();

		$tournament_id      = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$next_level_duration = isset( $_POST['next_level_duration'] ) ? intval( $_POST['next_level_duration'] ) : 900;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::advance_level( $tournament_id, $next_level_duration );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Advanced to next level', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to advance level', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Add time to current level
	 *
	 * @since 3.1.0
	 */
	public static function add_time() {
		self::verify_request();

		$tournament_id  = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$seconds_to_add = isset( $_POST['seconds'] ) ? intval( $_POST['seconds'] ) : 300; // 5 min default

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::add_time( $tournament_id, $seconds_to_add );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: seconds added */
					__( 'Added %d seconds', 'poker-tournament-import' ),
					$seconds_to_add
				),
				'state' => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add time', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Start break
	 *
	 * @since 3.1.0
	 */
	public static function start_break() {
		self::verify_request();

		$tournament_id    = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$break_duration   = isset( $_POST['break_duration'] ) ? intval( $_POST['break_duration'] ) : 10;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::start_break( $tournament_id, $break_duration );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: break duration in minutes */
					__( 'Break started (%d minutes)', 'poker-tournament-import' ),
					$break_duration
				),
				'state' => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to start break', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * End break
	 *
	 * @since 3.1.0
	 */
	public static function end_break() {
		self::verify_request();

		$tournament_id       = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$next_level_duration = isset( $_POST['next_level_duration'] ) ? intval( $_POST['next_level_duration'] ) : 900;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::end_break( $tournament_id, $next_level_duration );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Break ended', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to end break', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Finish tournament
	 *
	 * @since 3.1.0
	 */
	public static function finish_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		TDWP_Debug_Logger::log(
			'AJAX',
			'FINISH AJAX called',
			array( 'tournament_id' => $tournament_id )
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Live_State_Manager::finish( $tournament_id );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Tournament finished', 'poker-tournament-import' ),
				'state'   => TDWP_Live_State_Manager::get_state( $tournament_id ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to finish tournament', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Trash tournament
	 *
	 * @since 3.1.0
	 */
	public static function trash_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		TDWP_Debug_Logger::log(
			'AJAX',
			'TRASH AJAX called',
			array( 'tournament_id' => $tournament_id )
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		// Verify tournament is finished before allowing trash.
		$state = TDWP_Live_State_Manager::get_state( $tournament_id );
		if ( ! $state || 'finished' !== $state->status ) {
			wp_send_json_error( array( 'message' => __( 'Only finished tournaments can be trashed', 'poker-tournament-import' ) ) );
		}

		// Trash the tournament post.
		$result = wp_trash_post( $tournament_id );

		if ( $result ) {
			// Clear active tournament for current user.
			TDWP_Active_Tournament_Manager::clear_active_tournament( get_current_user_id() );

			wp_send_json_success( array(
				'message'      => __( 'Tournament trashed', 'poker-tournament-import' ),
				'redirect_url' => admin_url( 'admin.php?page=tdwp-live-control' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to trash tournament', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Get tournament state (for Heartbeat polling)
	 *
	 * @since 3.1.0
	 */
	public static function get_state() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		TDWP_Debug_Logger::log(
			'AJAX',
			'GET_STATE AJAX called',
			array( 'tournament_id' => $tournament_id )
		);

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$state = TDWP_Live_State_Manager::get_state( $tournament_id );

		TDWP_Debug_Logger::log(
			'AJAX',
			'GET_STATE initial state from DB',
			array(
				'status'         => $state ? $state->status : 'null',
				'time_remaining' => $state ? $state->time_remaining : 'null',
				'updated_at'     => $state ? $state->updated_at : 'null',
			)
		);

		// Tick for elapsed time to get fresh state (not stale database time)
		if ( $state && 'running' === $state->status ) {
			$elapsed = time() - strtotime( $state->updated_at );
			$elapsed = min( max( 0, $elapsed ), 30 ); // Cap between 0-30 seconds

			if ( $elapsed > 0 ) {
				$clock_manager = new TDWP_Tournament_Clock();
				$clock_manager->tick( $tournament_id, $elapsed );
				// Refresh state after tick
				$state = TDWP_Live_State_Manager::get_state( $tournament_id );

				TDWP_Debug_Logger::log(
					'AJAX',
					'GET_STATE after tick',
					array(
						'time_remaining' => $state->time_remaining,
						'updated_at'     => $state->updated_at,
					)
				);
			}
		}

		$tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );

		wp_send_json_success( array(
			'state'        => $state,
			'tables'       => $tables,
			'player_count' => TDWP_Table_Manager::get_seated_player_count( $tournament_id ),
			'timestamp'    => time(),
		) );
	}

	/**
	 * Add table
	 *
	 * @since 3.1.0
	 */
	public static function add_table() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$max_seats     = isset( $_POST['max_seats'] ) ? intval( $_POST['max_seats'] ) : 9;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$table_id = TDWP_Table_Manager::add_table( $tournament_id, $max_seats );

		if ( $table_id ) {
			$table = TDWP_Table_Manager::get_table( $table_id );
			wp_send_json_success( array(
				'message' => __( 'Table added', 'poker-tournament-import' ),
				'table'   => $table,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add table', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Remove table
	 *
	 * @since 3.1.0
	 */
	public static function remove_table() {
		self::verify_request();

		$table_id = isset( $_POST['table_id'] ) ? intval( $_POST['table_id'] ) : 0;

		if ( ! $table_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid table ID', 'poker-tournament-import' ) ) );
		}

		if ( ! TDWP_Table_Manager::is_table_empty( $table_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot remove table with players', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Table_Manager::remove_table( $table_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Table removed', 'poker-tournament-import' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to remove table', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Move player to seat
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Updated to use registration_id
	 */
	public static function move_player() {
		self::verify_request();

		$registration_id = isset( $_POST['registration_id'] ) ? intval( $_POST['registration_id'] ) : 0;
		$to_table_id     = isset( $_POST['to_table_id'] ) ? intval( $_POST['to_table_id'] ) : 0;
		$to_seat_number  = isset( $_POST['to_seat_number'] ) ? intval( $_POST['to_seat_number'] ) : 0;

		if ( ! $registration_id || ! $to_table_id || ! $to_seat_number ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Validate assignment
		$validation = TDWP_Seat_Manager::validate_assignment( $registration_id, $to_table_id, $to_seat_number );
		if ( ! $validation['valid'] ) {
			wp_send_json_error( array( 'message' => $validation['error'] ) );
		}

		$success = TDWP_Seat_Manager::move_player( $registration_id, $to_table_id, $to_seat_number );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Player moved', 'poker-tournament-import' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to move player', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Unseat player
	 *
	 * @since 3.1.0
	 */
	public static function unseat_player() {
		self::verify_request();

		$player_id = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;

		if ( ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid player ID', 'poker-tournament-import' ) ) );
		}

		$success = TDWP_Seat_Manager::unseat_player( $player_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Player unseated', 'poker-tournament-import' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unseat player', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Auto-seat all unseated players
	 *
	 * @since 3.1.0
	 */
	public static function auto_seat_players() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$result = TDWP_Seat_Manager::auto_seat_players( $tournament_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Calculate balance plan
	 *
	 * @since 3.1.0
	 */
	public static function calculate_balance() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$plan = TDWP_Table_Balancer::calculate_balance_plan( $tournament_id );

		wp_send_json_success( $plan );
	}

	/**
	 * Execute balance plan
	 *
	 * @since 3.1.0
	 */
	public static function execute_balance() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$moves         = isset( $_POST['moves'] ) ? json_decode( stripslashes( $_POST['moves'] ), true ) : array();

		if ( ! $tournament_id || empty( $moves ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		$results = TDWP_Table_Balancer::execute_balance( $tournament_id, $moves );

		if ( $results['success'] ) {
			wp_send_json_success( array(
				'message' => __( 'Tables balanced', 'poker-tournament-import' ),
				'results' => $results,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Some moves failed', 'poker-tournament-import' ),
				'results' => $results,
			) );
		}
	}

	/**
	 * Break table
	 *
	 * @since 3.1.0
	 */
	public static function break_table() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$table_id      = isset( $_POST['table_id'] ) ? intval( $_POST['table_id'] ) : 0;

		if ( ! $tournament_id || ! $table_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Get break suggestion
		$suggestion = TDWP_Table_Balancer::suggest_table_break( $tournament_id );

		if ( ! $suggestion || ! $suggestion['can_break'] ) {
			wp_send_json_error( array( 'message' => $suggestion['message'] ) );
		}

		// Execute break
		$success = TDWP_Table_Balancer::execute_table_break( $tournament_id, $table_id, $suggestion['moves'] );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Table broken', 'poker-tournament-import' ),
				'moves'   => $suggestion['moves'],
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to break table', 'poker-tournament-import' ) ) );
		}
	}

	/**
	 * Get tables data
	 *
	 * @since 3.1.0
	 */
	public static function get_tables() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$tables         = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );
		$balance_status = TDWP_Table_Balancer::get_balance_status( $tournament_id );

		wp_send_json_success( array(
			'tables'         => $tables,
			'balance_status' => $balance_status,
			'player_count'   => TDWP_Table_Manager::get_seated_player_count( $tournament_id ),
		) );
	}

	/**
	 * Add player to tournament (Phase 1 Beta16)
	 *
	 * @since 3.1.0
	 */
	public static function add_player_to_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$status        = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'registered';
		$paid_amount   = isset( $_POST['paid_amount'] ) ? floatval( $_POST['paid_amount'] ) : 0;

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament or player ID', 'poker-tournament-import' ) ) );
		}

		$result = TDWP_Tournament_Player_Manager::add_player(
			$tournament_id,
			$player_id,
			array(
				'status'      => $status,
				'paid_amount' => $paid_amount,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'      => __( 'Player added successfully', 'poker-tournament-import' ),
			'player_count' => TDWP_Tournament_Player_Manager::get_player_count( $tournament_id ),
		) );
	}

	/**
	 * Remove player from tournament (Phase 1 Beta16)
	 *
	 * @since 3.1.0
	 */
	public static function remove_player_from_tournament() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament or player ID', 'poker-tournament-import' ) ) );
		}

		$result = TDWP_Tournament_Player_Manager::remove_player( $tournament_id, $player_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to remove player', 'poker-tournament-import' ) ) );
		}

		wp_send_json_success( array(
			'message'      => __( 'Player removed successfully', 'poker-tournament-import' ),
			'player_count' => TDWP_Tournament_Player_Manager::get_player_count( $tournament_id ),
		) );
	}

	/**
	 * Update player status (Phase 1 Beta16)
	 *
	 * @since 3.1.0
	 */
	public static function update_player_status() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$status        = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $tournament_id || ! $player_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		$result = TDWP_Tournament_Player_Manager::update_player_status( $tournament_id, $player_id, $status );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update status', 'poker-tournament-import' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Status updated successfully', 'poker-tournament-import' ) ) );
	}

	/**
	 * Process buy-ins for multiple players
	 *
	 * @since 3.1.0
	 */
	public static function process_buyins() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$players_data  = isset( $_POST['players'] ) ? $_POST['players'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $tournament_id || empty( $players_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		global $wpdb;
		$table           = $wpdb->prefix . 'tdwp_tournament_players';
		$updated_count   = 0;
		$errors          = array();

		foreach ( $players_data as $player_data ) {
			$player_id    = isset( $player_data['player_id'] ) ? intval( $player_data['player_id'] ) : 0;
			$bought_in    = isset( $player_data['bought_in'] ) ? (bool) $player_data['bought_in'] : false;
			$rebuys       = isset( $player_data['rebuys'] ) ? intval( $player_data['rebuys'] ) : 0;
			$addons       = isset( $player_data['addons'] ) ? intval( $player_data['addons'] ) : 0;
			$total_amount = isset( $player_data['total'] ) ? floatval( $player_data['total'] ) : 0;

			if ( ! $player_id ) {
				continue;
			}

			// Only update if player actually bought in
			if ( ! $bought_in ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array(
					'status'       => 'paid',
					'paid_amount'  => $total_amount,
					'rebuys_count' => $rebuys,
					'addons_count' => $addons,
				),
				array(
					'tournament_id' => $tournament_id,
					'player_id'     => $player_id,
				),
				array( '%s', '%f', '%d', '%d' ),
				array( '%d', '%d' )
			);

			if ( false !== $result ) {
				++$updated_count;
			} else {
				$player_post = get_post( $player_id );
				$errors[]    = sprintf(
					/* translators: %s: player name */
					__( 'Failed to update %s', 'poker-tournament-import' ),
					$player_post ? $player_post->post_title : "Player #$player_id"
				);
			}
		}

		if ( $updated_count > 0 ) {
			wp_send_json_success(
				array(
					/* translators: %d: number of players processed */
					'message' => sprintf( __( 'Processed %d player(s) successfully', 'poker-tournament-import' ), $updated_count ),
					'count'   => $updated_count,
					'errors'  => $errors,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'No players were processed', 'poker-tournament-import' ),
					'errors'  => $errors,
				)
			);
		}
	}

	/**
	 * Bust player (Phase 2 Beta22)
	 *
	 * @since 3.1.0
	 */
	public static function bust_player() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Extract eliminator IDs array (multi-hitman support)
		$eliminated_by = array();
		if ( isset( $_POST['eliminated_by'] ) ) {
			$eliminated_by_raw = stripslashes( $_POST['eliminated_by'] );
			$eliminated_by     = json_decode( $eliminated_by_raw, true );

			// Ensure it's an array
			if ( ! is_array( $eliminated_by ) ) {
				$eliminated_by = array();
			}
		}

		// Use new Player Operations class with transaction logging and multi-hitman support
		$result = TDWP_Player_Operations::process_bustout( $tournament_id, $player_id, $eliminated_by );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Re-entry player (Phase 2 Beta23)
	 *
	 * @since 3.1.0
	 */
	public static function reentry_player() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		$result = TDWP_Tournament_Player_Manager::reentry_player( $tournament_id, $player_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process declined re-entry and player withdrawal (Phase 3 v3.3.0)
	 *
	 * @since 3.3.0
	 */
	public static function process_declined_reentry() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$reason        = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Use Tournament Player Manager for declined re-entry processing
		$result = TDWP_Tournament_Player_Manager::process_declined_reentry( $tournament_id, $player_id, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process rebuy (Phase 2 Beta25 - Updated v3.2.0)
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Uses TDWP_Player_Operations with transaction logging
	 */
	public static function process_rebuy() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$amount        = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 50.00; // Default rebuy amount

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Use new Player Operations class with transaction logging
		$result = TDWP_Player_Operations::process_rebuy( $tournament_id, $player_id, $amount );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process addon (Phase 2 Beta25 - Updated v3.2.0)
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Uses TDWP_Player_Operations with transaction logging
	 */
	public static function process_addon() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$amount        = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 25.00; // Default addon amount

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Use new Player Operations class with transaction logging
		$result = TDWP_Player_Operations::process_addon( $tournament_id, $player_id, $amount );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Update chip count (Phase 2 Beta26 - Updated v3.2.0)
	 *
	 * Now uses chip adjustment with transaction logging and required reason
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Uses TDWP_Player_Operations::process_chip_adjustment with transaction logging
	 */
	public static function update_chip_count() {
		self::verify_request();

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$player_id     = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$adjustment    = isset( $_POST['adjustment'] ) ? intval( $_POST['adjustment'] ) : 0;
		$reason        = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

		if ( ! $tournament_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
		}

		// Use new Player Operations class with transaction logging
		$result = TDWP_Player_Operations::process_chip_adjustment( $tournament_id, $player_id, $adjustment, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get transaction log for tournament (Phase 2 Completion v3.2.0)
	 *
	 * Returns complete immutable audit trail of all player operations
	 *
	 * @since 3.2.0
	 */
	public static function get_transaction_log() {
		self::verify_request();

		$tournament_id     = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;
		$transaction_type  = isset( $_POST['transaction_type'] ) ? sanitize_text_field( $_POST['transaction_type'] ) : '';
		$player_id         = isset( $_POST['player_id'] ) ? intval( $_POST['player_id'] ) : 0;
		$limit             = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$offset            = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$args = array(
			'transaction_type' => $transaction_type,
			'player_id'        => $player_id,
			'order_by'         => 'created_at',
			'order'            => 'DESC',
			'limit'            => $limit,
			'offset'           => $offset,
		);

		$raw_transactions = TDWP_Transaction_Logger::get_tournament_transactions( $tournament_id, $args );
		$total_count      = TDWP_Transaction_Logger::get_transaction_count( $tournament_id, $transaction_type );

		// Process transactions to add player and actor names
		$processed_transactions = array();
		$summary = array(
			'buyins' => 0,
			'rebuys' => 0,
			'addons' => 0,
			'prize_pool' => 0,
		);

		foreach ( $raw_transactions as $transaction ) {
			// Get player name
			$player_post = get_post( $transaction->player_id );
			$player_name = $player_post ? $player_post->post_title : __( 'Unknown Player', 'poker-tournament-import' );

			// Get actor name
			$actor_user = get_user_by( 'id', $transaction->actor_user_id );
			$actor_name = $actor_user ? $actor_user->display_name : __( 'System', 'poker-tournament-import' );

			// Normalize transaction type for frontend
			$normalized_type = $transaction->transaction_type;
			if ( 'bust_out' === $normalized_type ) {
				$normalized_type = 'bustout';
			}

			// Create processed transaction object
			$processed_transaction = array(
				'id' => $transaction->id,
				'tournament_id' => $transaction->tournament_id,
				'player_id' => $transaction->player_id,
				'player_name' => $player_name,
				'transaction_type' => $normalized_type,
				'amount' => floatval( $transaction->amount ),
				'chips' => intval( $transaction->chips ),
				'reason' => $transaction->reason,
				'actor_user_id' => $transaction->actor_user_id,
				'actor_name' => $actor_name,
				'created_at' => date( 'Y-m-d H:i:s', strtotime( $transaction->created_at ) ),
			);

			$processed_transactions[] = (object) $processed_transaction;

			// Calculate summary statistics
			switch ( $normalized_type ) {
				case 'buyin':
					$summary['buyins'] += floatval( $transaction->amount );
					$summary['prize_pool'] += floatval( $transaction->amount );
					break;
				case 'rebuy':
					$summary['rebuys'] += floatval( $transaction->amount );
					$summary['prize_pool'] += floatval( $transaction->amount );
					break;
				case 'addon':
					$summary['addons'] += floatval( $transaction->amount );
					$summary['prize_pool'] += floatval( $transaction->amount );
					break;
			}
		}

		wp_send_json_success( array(
			'transactions' => $processed_transactions,
			'summary' => $summary,
			'total' => $total_count,
			'limit' => $limit,
			'offset' => $offset,
		) );
	}
}

// Initialize AJAX handlers
TDWP_Tournament_Manager_AJAX::init();
