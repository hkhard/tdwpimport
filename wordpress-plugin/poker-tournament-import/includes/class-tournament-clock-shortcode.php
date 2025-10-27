<?php
/**
 * Tournament Clock Shortcode
 *
 * Displays live tournament clock on frontend
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
 * Tournament Clock Shortcode Class
 *
 * Renders [tournament_clock] shortcode for public display
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Clock_Shortcode {

	/**
	 * Live state manager
	 *
	 * @var TDWP_Tournament_Live
	 */
	private $live_manager;

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		$this->live_manager = new TDWP_Tournament_Live();

		// Register shortcode
		add_shortcode( 'tournament_clock', array( $this, 'render_shortcode' ) );
		add_shortcode( 'tdwp_tournament_clock', array( $this, 'render_shortcode' ) );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// AJAX handler for frontend updates
		add_action( 'wp_ajax_tdwp_get_clock_state', array( $this, 'ajax_get_clock_state' ) );
		add_action( 'wp_ajax_nopriv_tdwp_get_clock_state', array( $this, 'ajax_get_clock_state' ) );

		// Lightweight status check endpoint
		add_action( 'wp_ajax_tdwp_check_clock_status', array( $this, 'ajax_check_status' ) );
		add_action( 'wp_ajax_nopriv_tdwp_check_clock_status', array( $this, 'ajax_check_status' ) );

		// Heartbeat integration for public
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'heartbeat_received' ), 10, 2 );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @since 3.1.0
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue if shortcode is present
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'tournament_clock' ) ) {
			if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'tdwp_tournament_clock' ) ) {
				return;
			}
		}

		// Enqueue styles
		wp_enqueue_style(
			'tdwp-tournament-clock',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/tournament-clock-frontend.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'tdwp-tournament-clock',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-clock-frontend.js',
			array( 'jquery', 'heartbeat' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'tdwp-tournament-clock',
			'tdwpClock',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'tdwp_clock_frontend' ),
				'refreshInterval' => 15, // seconds
			)
		);
	}

	/**
	 * Render shortcode
	 *
	 * @since 3.1.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'tournament_id' => 0,
				'show_stats'    => 'yes',
				'show_level'    => 'yes',
				'theme'         => 'default', // default, dark, light
				'size'          => 'large',   // small, medium, large
			),
			$atts,
			'tournament_clock'
		);

		// Get active tournament if no ID specified
		$tournament_id = absint( $atts['tournament_id'] );
		if ( 0 === $tournament_id ) {
			$active = $this->live_manager->get_active( array( 'limit' => 1 ) );
			if ( empty( $active ) ) {
				return $this->render_no_tournament();
			}
			$state = $active[0];
		} else {
			$state = $this->live_manager->get_by_tournament_id( $tournament_id );
			if ( ! $state ) {
				return $this->render_no_tournament();
			}
		}

		// Build CSS classes
		$classes = array(
			'tdwp-clock-widget',
			'tdwp-clock-theme-' . sanitize_html_class( $atts['theme'] ),
			'tdwp-clock-size-' . sanitize_html_class( $atts['size'] ),
			'tdwp-clock-status-' . sanitize_html_class( $state->status ),
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-tournament-id="<?php echo absint( $state->tournament_id ); ?>">

			<!-- Clock Display -->
			<div class="tdwp-clock-main">
				<div class="tdwp-clock-time" data-seconds="<?php echo absint( $state->time_remaining ); ?>">
					<?php echo esc_html( $this->format_time( $state->time_remaining ) ); ?>
				</div>

				<?php if ( 'yes' === $atts['show_level'] ) : ?>
					<div class="tdwp-clock-level">
						<?php
						printf(
							/* translators: %d: level number */
							esc_html__( 'Level %d', 'poker-tournament-import' ),
							absint( $state->current_level )
						);
						?>
					</div>
				<?php endif; ?>

				<div class="tdwp-clock-status">
					<span class="tdwp-status-indicator"></span>
					<?php echo esc_html( ucfirst( $state->status ) ); ?>
				</div>
			</div>

			<?php if ( 'yes' === $atts['show_stats'] ) : ?>
				<!-- Statistics -->
				<div class="tdwp-clock-stats">
					<div class="tdwp-clock-stat">
						<span class="tdwp-stat-label"><?php esc_html_e( 'Players', 'poker-tournament-import' ); ?></span>
						<span class="tdwp-stat-value"><?php echo absint( $state->remaining_players ); ?>/<?php echo absint( $state->total_players ); ?></span>
					</div>

					<?php if ( $state->prize_pool > 0 ) : ?>
						<div class="tdwp-clock-stat">
							<span class="tdwp-stat-label"><?php esc_html_e( 'Prize Pool', 'poker-tournament-import' ); ?></span>
							<span class="tdwp-stat-value"><?php echo esc_html( $this->format_currency( $state->prize_pool ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $state->total_rebuys > 0 || $state->total_addons > 0 ) : ?>
						<div class="tdwp-clock-stat">
							<span class="tdwp-stat-label"><?php esc_html_e( 'Rebuys/Add-ons', 'poker-tournament-import' ); ?></span>
							<span class="tdwp-stat-value"><?php echo absint( $state->total_rebuys ); ?>/<?php echo absint( $state->total_addons ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Powered by (optional) -->
			<div class="tdwp-clock-footer">
				<small><?php esc_html_e( 'Live Tournament Clock', 'poker-tournament-import' ); ?></small>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render "no tournament" message
	 *
	 * @since 3.1.0
	 *
	 * @return string HTML output.
	 */
	private function render_no_tournament() {
		ob_start();
		?>
		<div class="tdwp-clock-widget tdwp-clock-empty">
			<div class="tdwp-clock-message">
				<p><?php esc_html_e( 'No active tournament at this time.', 'poker-tournament-import' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Get clock state
	 *
	 * @since 3.1.0
	 */
	public function ajax_get_clock_state() {
		check_ajax_referer( 'tdwp_clock_frontend', 'nonce' );

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		if ( 0 === $tournament_id ) {
			// Get first active tournament
			$active = $this->live_manager->get_active( array( 'limit' => 1 ) );
			if ( empty( $active ) ) {
				wp_send_json_error( array( 'message' => __( 'No active tournament', 'poker-tournament-import' ) ) );
			}
			$state = $active[0];
		} else {
			$state = $this->live_manager->get_by_tournament_id( $tournament_id );
			if ( ! $state ) {
				wp_send_json_error( array( 'message' => __( 'Tournament not found', 'poker-tournament-import' ) ) );
			}
		}

		wp_send_json_success(
			array(
				'tournament_id'     => $state->tournament_id,
				'status'            => $state->status,
				'current_level'     => $state->current_level,
				'time_remaining'    => $state->time_remaining,
				'total_players'     => $state->total_players,
				'remaining_players' => $state->remaining_players,
				'total_rebuys'      => $state->total_rebuys,
				'total_addons'      => $state->total_addons,
				'prize_pool'        => $state->prize_pool,
			)
		);
	}

	/**
	 * AJAX: Lightweight status check
	 *
	 * Returns only status for fast polling
	 *
	 * @since 3.1.0
	 */
	public function ajax_check_status() {
		check_ajax_referer( 'tdwp_clock_frontend', 'nonce' );

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		if ( 0 === $tournament_id ) {
			// Get first active tournament
			$active = $this->live_manager->get_active( array( 'limit' => 1 ) );
			if ( empty( $active ) ) {
				wp_send_json_success( array( 'status' => 'none' ) );
				return;
			}
			$state = $active[0];
		} else {
			$state = $this->live_manager->get_by_tournament_id( $tournament_id );
			if ( ! $state ) {
				wp_send_json_success( array( 'status' => 'none' ) );
				return;
			}
		}

		// Return only status - minimal payload
		wp_send_json_success(
			array(
				'status' => $state->status,
			)
		);
	}

	/**
	 * Heartbeat integration for public
	 *
	 * @since 3.1.0
	 *
	 * @param array $response Heartbeat response.
	 * @param array $data     Heartbeat data.
	 * @return array Modified response.
	 */
	public function heartbeat_received( $response, $data ) {
		// Check if this is a tournament clock heartbeat
		if ( empty( $data['tdwp_clock_id'] ) ) {
			return $response;
		}

		$tournament_id = absint( $data['tdwp_clock_id'] );

		// Get updated state
		$state = $this->live_manager->get_by_tournament_id( $tournament_id );

		if ( $state ) {
			$response['tdwp_clock_state'] = array(
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
