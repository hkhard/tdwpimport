<?php
/**
 * Live Tournament Control Admin Page
 *
 * Provides real-time tournament control interface
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
 * Live Tournament Control admin page class
 *
 * @since 3.1.0
 */
class TDWP_Live_Control_Page {

	/**
	 * Clock manager instance
	 *
	 * @var TDWP_Tournament_Clock
	 */
	private $clock_manager;

	/**
	 * Live state manager instance
	 *
	 * @var TDWP_Tournament_Live
	 */
	private $live_manager;

	/**
	 * Events manager instance
	 *
	 * @var TDWP_Tournament_Events
	 */
	private $events_manager;

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		$this->clock_manager  = new TDWP_Tournament_Clock();
		$this->live_manager   = new TDWP_Tournament_Live();
		$this->events_manager = new TDWP_Tournament_Events();

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_tdwp_start_tournament', array( $this, 'ajax_start_tournament' ) );
		add_action( 'wp_ajax_tdwp_pause_tournament', array( $this, 'ajax_pause_tournament' ) );
		add_action( 'wp_ajax_tdwp_resume_tournament', array( $this, 'ajax_resume_tournament' ) );
		add_action( 'wp_ajax_tdwp_advance_level', array( $this, 'ajax_advance_level' ) );
		add_action( 'wp_ajax_tdwp_complete_tournament', array( $this, 'ajax_complete_tournament' ) );
		add_action( 'wp_ajax_tdwp_get_live_state', array( $this, 'ajax_get_live_state' ) );

		// Heartbeat integration
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
	}

	/**
	 * Add admin menu item
	 *
	 * @since 3.1.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tdwp-tournament-manager',
			__( 'Live Control', 'poker-tournament-import' ),
			__( 'Live Control', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-live-control',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 3.1.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin page
		if ( 'tournament-manager_page_tdwp-live-control' !== $hook ) {
			return;
		}

		// Enqueue styles
		wp_enqueue_style(
			'tdwp-live-control',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/live-control-admin.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'tdwp-live-control',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/live-control-admin.js',
			array( 'jquery', 'heartbeat' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'tdwp-live-control',
			'tdwpLiveControl',
			array(
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'tdwp_live_control' ),
				'heartbeatInterval' => 15, // seconds
				'i18n'             => array(
					'confirmStart'    => __( 'Start this tournament?', 'poker-tournament-import' ),
					'confirmPause'    => __( 'Pause tournament?', 'poker-tournament-import' ),
					'confirmResume'   => __( 'Resume tournament?', 'poker-tournament-import' ),
					'confirmAdvance'  => __( 'Advance to next level?', 'poker-tournament-import' ),
					'confirmComplete' => __( 'Complete tournament? This cannot be undone.', 'poker-tournament-import' ),
					'starting'        => __( 'Starting...', 'poker-tournament-import' ),
					'pausing'         => __( 'Pausing...', 'poker-tournament-import' ),
					'resuming'        => __( 'Resuming...', 'poker-tournament-import' ),
					'advancing'       => __( 'Advancing...', 'poker-tournament-import' ),
					'completing'      => __( 'Completing...', 'poker-tournament-import' ),
					'error'           => __( 'An error occurred. Please try again.', 'poker-tournament-import' ),
				),
			)
		);
	}

	/**
	 * Render the main page
	 *
	 * @since 3.1.0
	 */
	public function render_page() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'poker-tournament-import' ) );
		}

		// Get active tournaments
		$active_tournaments = $this->live_manager->get_active();

		// Get templates for new tournament
		global $wpdb;
		$templates = $wpdb->get_results(
			"SELECT id, name FROM {$wpdb->prefix}tdwp_tournament_templates ORDER BY name ASC"
		);

		?>
		<div class="wrap tdwp-live-control">
			<h1><?php esc_html_e( 'Live Tournament Control', 'poker-tournament-import' ); ?></h1>

			<div class="tdwp-live-grid">
				<!-- Left Column: Control Panel -->
				<div class="tdwp-control-panel">
					<div class="tdwp-card">
						<h2><?php esc_html_e( 'Tournament Selection', 'poker-tournament-import' ); ?></h2>

						<?php if ( empty( $active_tournaments ) ) : ?>
							<!-- Start New Tournament -->
							<div class="tdwp-start-new">
								<p><?php esc_html_e( 'No active tournaments. Start a new one:', 'poker-tournament-import' ); ?></p>

								<form id="tdwp-start-form" class="tdwp-form">
									<?php wp_nonce_field( 'tdwp_start_tournament', 'start_nonce' ); ?>

									<div class="tdwp-form-row">
										<label for="template_id">
											<?php esc_html_e( 'Template', 'poker-tournament-import' ); ?>
											<span class="required">*</span>
										</label>
										<select id="template_id" name="template_id" required>
											<option value=""><?php esc_html_e( 'Select Template', 'poker-tournament-import' ); ?></option>
											<?php foreach ( $templates as $template ) : ?>
												<option value="<?php echo absint( $template->id ); ?>">
													<?php echo esc_html( $template->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<div class="tdwp-form-row">
										<label for="total_players">
											<?php esc_html_e( 'Total Players', 'poker-tournament-import' ); ?>
										</label>
										<input type="number" id="total_players" name="total_players" min="2" value="9" class="small-text">
									</div>

									<button type="submit" class="button button-primary button-hero">
										<?php esc_html_e( 'Start Tournament', 'poker-tournament-import' ); ?>
									</button>
								</form>
							</div>

						<?php else : ?>
							<!-- Active Tournament Controls -->
							<?php foreach ( $active_tournaments as $tournament ) : ?>
								<div class="tdwp-tournament-active" data-tournament-id="<?php echo absint( $tournament->tournament_id ); ?>">

									<!-- Clock Display -->
									<div class="tdwp-clock-display">
										<div class="tdwp-clock-time">
											<span class="tdwp-time-value" data-seconds="<?php echo absint( $tournament->time_remaining ); ?>">
												<?php echo esc_html( $this->format_time( $tournament->time_remaining ) ); ?>
											</span>
										</div>
										<div class="tdwp-clock-level">
											<?php
											printf(
												/* translators: %d: level number */
												esc_html__( 'Level %d', 'poker-tournament-import' ),
												absint( $tournament->current_level )
											);
											?>
										</div>
										<div class="tdwp-clock-status status-<?php echo esc_attr( $tournament->status ); ?>">
											<?php echo esc_html( ucfirst( $tournament->status ) ); ?>
										</div>
									</div>

									<!-- Control Buttons -->
									<div class="tdwp-control-buttons">
										<button class="button button-large tdwp-pause-btn" style="display: <?php echo 'running' === $tournament->status ? 'inline-block' : 'none'; ?>">
											<?php esc_html_e( 'Pause', 'poker-tournament-import' ); ?>
										</button>
										<button class="button button-large tdwp-resume-btn" style="display: <?php echo 'paused' === $tournament->status ? 'inline-block' : 'none'; ?>">
											<?php esc_html_e( 'Resume', 'poker-tournament-import' ); ?>
										</button>

										<button class="button button-large tdwp-advance-btn">
											<?php esc_html_e( 'Next Level', 'poker-tournament-import' ); ?>
										</button>

										<button class="button button-large button-primary tdwp-complete-btn">
											<?php esc_html_e( 'End Tournament', 'poker-tournament-import' ); ?>
										</button>
									</div>

									<!-- Statistics Panel -->
									<div class="tdwp-stats-panel">
										<h3><?php esc_html_e( 'Tournament Statistics', 'poker-tournament-import' ); ?></h3>
										<div class="tdwp-stats-grid">
											<div class="tdwp-stat">
												<span class="tdwp-stat-label"><?php esc_html_e( 'Total Players', 'poker-tournament-import' ); ?></span>
												<span class="tdwp-stat-value"><?php echo absint( $tournament->total_players ); ?></span>
											</div>
											<div class="tdwp-stat">
												<span class="tdwp-stat-label"><?php esc_html_e( 'Remaining', 'poker-tournament-import' ); ?></span>
												<span class="tdwp-stat-value"><?php echo absint( $tournament->remaining_players ); ?></span>
											</div>
											<div class="tdwp-stat">
												<span class="tdwp-stat-label"><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></span>
												<span class="tdwp-stat-value"><?php echo absint( $tournament->total_rebuys ); ?></span>
											</div>
											<div class="tdwp-stat">
												<span class="tdwp-stat-label"><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></span>
												<span class="tdwp-stat-value"><?php echo absint( $tournament->total_addons ); ?></span>
											</div>
											<div class="tdwp-stat tdwp-stat-wide">
												<span class="tdwp-stat-label"><?php esc_html_e( 'Prize Pool', 'poker-tournament-import' ); ?></span>
												<span class="tdwp-stat-value"><?php echo esc_html( $this->format_currency( $tournament->prize_pool ) ); ?></span>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Right Column: Event Log -->
				<div class="tdwp-event-log">
					<div class="tdwp-card">
						<h2><?php esc_html_e( 'Event Log', 'poker-tournament-import' ); ?></h2>
						<div class="tdwp-events-container">
							<?php if ( ! empty( $active_tournaments ) ) : ?>
								<?php
								$events = $this->events_manager->get_events(
									$active_tournaments[0]->tournament_id,
									array( 'limit' => 20 )
								);
								?>
								<?php if ( ! empty( $events ) ) : ?>
									<ul class="tdwp-events-list">
										<?php foreach ( $events as $event ) : ?>
											<li class="tdwp-event-item event-type-<?php echo esc_attr( $event->event_type ); ?>">
												<?php echo esc_html( TDWP_Tournament_Events::format_event( $event ) ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p class="tdwp-no-events"><?php esc_html_e( 'No events yet.', 'poker-tournament-import' ); ?></p>
								<?php endif; ?>
							<?php else : ?>
								<p class="tdwp-no-events"><?php esc_html_e( 'No active tournament.', 'poker-tournament-import' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Hidden tournament ID for JS -->
			<?php if ( ! empty( $active_tournaments ) ) : ?>
				<input type="hidden" id="tdwp-current-tournament-id" value="<?php echo absint( $active_tournaments[0]->tournament_id ); ?>">
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Start tournament
	 *
	 * @since 3.1.0
	 */
	public function ajax_start_tournament() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'poker-tournament-import' ) ) );
		}

		$template_id   = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$total_players = isset( $_POST['total_players'] ) ? absint( $_POST['total_players'] ) : 0;

		// Create temporary tournament ID (in production, this would create actual tournament post)
		$tournament_id = time();

		$result = $this->clock_manager->start( $tournament_id, $template_id, $total_players );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Tournament started successfully', 'poker-tournament-import' ),
				'tournament_id' => $tournament_id,
			)
		);
	}

	/**
	 * AJAX: Pause tournament
	 *
	 * @since 3.1.0
	 */
	public function ajax_pause_tournament() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'poker-tournament-import' ) ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		$result = $this->clock_manager->pause( $tournament_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Tournament paused', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Resume tournament
	 *
	 * @since 3.1.0
	 */
	public function ajax_resume_tournament() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'poker-tournament-import' ) ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		$result = $this->clock_manager->resume( $tournament_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Tournament resumed', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Advance level
	 *
	 * @since 3.1.0
	 */
	public function ajax_advance_level() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'poker-tournament-import' ) ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		$result = $this->clock_manager->advance_level( $tournament_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Advanced to next level', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Complete tournament
	 *
	 * @since 3.1.0
	 */
	public function ajax_complete_tournament() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'poker-tournament-import' ) ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		$result = $this->clock_manager->complete( $tournament_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Tournament completed', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Get live state
	 *
	 * @since 3.1.0
	 */
	public function ajax_get_live_state() {
		check_ajax_referer( 'tdwp_live_control', 'nonce' );

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		$state = $this->live_manager->get_by_tournament_id( $tournament_id );

		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( 'Tournament not found', 'poker-tournament-import' ) ) );
		}

		wp_send_json_success(
			array(
				'state'  => $state,
				'events' => $this->events_manager->get_events( $tournament_id, array( 'limit' => 5 ) ),
			)
		);
	}

	/**
	 * Heartbeat integration
	 *
	 * @since 3.1.0
	 *
	 * @param array $response Heartbeat response.
	 * @param array $data     Heartbeat data.
	 * @return array Modified response.
	 */
	public function heartbeat_received( $response, $data ) {
		// Check if this is a tournament clock heartbeat
		if ( empty( $data['tdwp_tournament_id'] ) ) {
			return $response;
		}

		$tournament_id = absint( $data['tdwp_tournament_id'] );

		// Get current state first
		$state = $this->live_manager->get_by_tournament_id( $tournament_id );

		if ( $state ) {
			// Calculate actual elapsed time since last update
			$elapsed = time() - strtotime( $state->updated_at );
			$elapsed = min( max( 0, $elapsed ), 30 ); // Cap between 0-30 seconds

			// Tick by actual elapsed time
			$this->clock_manager->tick( $tournament_id, $elapsed );

			// Refresh state after tick
			$state = $this->live_manager->get_by_tournament_id( $tournament_id );
		}

		if ( $state ) {
			$response['tdwp_live_state'] = array(
				'tournament_id'     => $state->tournament_id,
				'status'            => $state->status,
				'current_level'     => $state->current_level,
				'time_remaining'    => $state->time_remaining,
				'total_players'     => $state->total_players,
				'remaining_players' => $state->remaining_players,
				'total_rebuys'      => $state->total_rebuys,
				'total_addons'      => $state->total_addons,
				'prize_pool'        => $state->prize_pool,
			);

			// Get recent events
			$events = $this->events_manager->get_events(
				$tournament_id,
				array( 'limit' => 5 )
			);

			$response['tdwp_events'] = array_map(
				function( $event ) {
					return TDWP_Tournament_Events::format_event( $event );
				},
				$events
			);
		}

		return $response;
	}

	/**
	 * Format time in MM:SS
	 *
	 * @since 3.1.0
	 *
	 * @param int $seconds Seconds.
	 * @return string Formatted time.
	 */
	private function format_time( $seconds ) {
		$seconds = absint( $seconds );
		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;

		return sprintf( '%02d:%02d', $minutes, $secs );
	}

	/**
	 * Format currency
	 *
	 * @since 3.1.0
	 *
	 * @param float $amount Amount.
	 * @return string Formatted currency.
	 */
	private function format_currency( $amount ) {
		$symbol = get_option( 'tdwp_currency_symbol', '$' );
		return $symbol . number_format_i18n( $amount, 2 );
	}
}

// Initialize
new TDWP_Live_Control_Page();
