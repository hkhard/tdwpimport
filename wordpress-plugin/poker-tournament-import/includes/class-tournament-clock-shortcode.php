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

		// Enqueue sound manager script (dependency of the main clock script).
		wp_enqueue_script(
			'tdwp-clock-sound-manager',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-clock-sound-manager.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		// Enqueue scripts
		wp_enqueue_script(
			'tdwp-tournament-clock',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-clock-frontend.js',
			array( 'jquery', 'heartbeat', 'tdwp-clock-sound-manager' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';

		// Localize script
		wp_localize_script(
			'tdwp-tournament-clock',
			'tdwpClock',
			array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'tdwp_clock_frontend' ),
				'refreshInterval' => 15, // seconds
				'screenMode'      => ( 'clock' === $screen ),
				'sounds'          => array(
					'warn5min'    => POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/sounds/bell.wav',
					'warn1min'    => POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/sounds/bell.wav',
					'levelChange' => POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/sounds/bell.wav',
					'breakStart'  => POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/sounds/bell.wav',
				),
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
				'tournament_id'  => 0,
				'show_stats'     => 'yes',
				'show_level'     => 'yes',
				'theme'          => 'default', // default, dark, light
				'size'           => 'large',   // small, medium, large
				'show_prizes'    => 'no',
				'show_rankings'  => 'no',
				'rankings_limit' => 10,
				'logo_url'       => '',
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

		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';
		if ( 'clock' === $screen ) {
			$classes[] = 'tdwp-clock-fullscreen-mode';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-tournament-id="<?php echo absint( $state->tournament_id ); ?>">

			<?php
			$logo_url = '' !== $atts['logo_url'] ? esc_url_raw( $atts['logo_url'] ) : get_the_post_thumbnail_url( $state->tournament_id, 'medium' );
			?>
			<?php if ( ! empty( $logo_url ) ) : ?>
				<div class="tdwp-clock-logo-wrap">
					<img class="tdwp-clock-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="" />
				</div>
			<?php endif; ?>

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

				<div class="tdwp-clock-blinds">
					<span class="tdwp-clock-sb"></span> / <span class="tdwp-clock-bb"></span> <span class="tdwp-clock-ante"></span>
				</div>

				<div class="tdwp-clock-avg-stack"></div>
				<div class="tdwp-clock-pot"></div>

				<div class="tdwp-clock-break-info">
					<span class="tdwp-clock-back-at"></span>
				</div>

				<div class="tdwp-clock-next-level"></div>

				<button type="button" class="tdwp-clock-fullscreen-toggle"><?php echo esc_html__( 'Fullscreen', 'poker-tournament-import' ); ?></button>
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

			<?php if ( 'yes' === $atts['show_prizes'] ) : ?>
				<?php $prizes = $this->get_prize_payouts( $state->tournament_id ); ?>
				<?php if ( ! empty( $prizes ) ) : ?>
					<!-- Prize Payouts -->
					<div class="tdwp-clock-prizes">
						<h3 class="tdwp-clock-prizes-title"><?php esc_html_e( 'Payouts', 'poker-tournament-import' ); ?></h3>
						<table class="tdwp-clock-prizes-table">
							<tbody>
								<?php foreach ( $prizes as $prize ) : ?>
									<tr>
										<td class="tdwp-clock-prizes-place">
											<?php
											printf(
												/* translators: %d: finishing place */
												esc_html__( '%d.', 'poker-tournament-import' ),
												absint( $prize['place'] )
											);
											?>
										</td>
										<td class="tdwp-clock-prizes-amount">
											<?php
											if ( null !== $prize['amount'] ) {
												echo esc_html( $this->format_currency( $prize['amount'] ) );
											} elseif ( null !== $prize['percentage'] ) {
												echo esc_html( $prize['percentage'] . '%' );
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( 'yes' === $atts['show_rankings'] ) : ?>
				<?php $rankings = $this->get_rankings( $state->tournament_id, $atts['rankings_limit'] ); ?>
				<?php if ( ! empty( $rankings ) ) : ?>
					<!-- Rankings -->
					<div class="tdwp-clock-rankings">
						<h3 class="tdwp-clock-rankings-title"><?php esc_html_e( 'Chip Leaders', 'poker-tournament-import' ); ?></h3>
						<table class="tdwp-clock-rankings-table">
							<tbody>
								<?php foreach ( $rankings as $ranking ) : ?>
									<tr>
										<td class="tdwp-clock-rankings-rank"><?php echo absint( $ranking['rank'] ); ?></td>
										<td class="tdwp-clock-rankings-name"><?php echo esc_html( $ranking['name'] ); ?></td>
										<td class="tdwp-clock-rankings-chips"><?php echo esc_html( number_format_i18n( $ranking['chip_count'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
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

		wp_send_json_success( $this->build_payload( $state ) );
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
			$response['tdwp_clock_state'] = $this->build_payload( $state );
		}

		return $response;
	}

	/**
	 * Build the frontend clock state payload.
	 *
	 * @since 3.9.2
	 *
	 * @param object $state Live state object.
	 * @return array Payload array.
	 */
	private function build_payload( $state ) {
		$payload = array(
			'tournament_id'     => $state->tournament_id,
			'status'            => $state->status,
			'current_level'     => $state->current_level,
			'time_remaining'    => $state->time_remaining,
			'total_players'     => $state->total_players,
			'remaining_players' => $state->remaining_players,
			'total_rebuys'      => $state->total_rebuys,
			'total_addons'      => $state->total_addons,
			'prize_pool'        => $state->prize_pool,
			'break_until'       => isset( $state->break_until ) ? $state->break_until : null,
			'small_blind'       => null,
			'big_blind'         => null,
			'ante'              => null,
			'next_level'        => null,
		);

		$snapshot = TDWP_Tournament_Snapshot::get( $state->tournament_id );

		if ( is_array( $snapshot ) ) {
			$current_level = TDWP_Tournament_Snapshot::blind_level_for( $snapshot, $state->current_level );
			if ( is_array( $current_level ) ) {
				$payload['small_blind'] = isset( $current_level['small_blind'] ) ? $current_level['small_blind'] : null;
				$payload['big_blind']   = isset( $current_level['big_blind'] ) ? $current_level['big_blind'] : null;
				$payload['ante']        = isset( $current_level['ante'] ) ? $current_level['ante'] : null;
			}

			$next_level = TDWP_Tournament_Snapshot::blind_level_for( $snapshot, (int) $state->current_level + 1 );
			if ( is_array( $next_level ) ) {
				$payload['next_level'] = array(
					'small_blind'      => isset( $next_level['small_blind'] ) ? $next_level['small_blind'] : null,
					'big_blind'        => isset( $next_level['big_blind'] ) ? $next_level['big_blind'] : null,
					'ante'             => isset( $next_level['ante'] ) ? $next_level['ante'] : null,
					'duration_minutes' => isset( $next_level['duration_minutes'] ) ? $next_level['duration_minutes'] : null,
					'is_break'         => isset( $next_level['is_break'] ) ? $next_level['is_break'] : null,
				);
			}
		}

		$stack_and_pot         = $this->get_avg_stack_and_pot( $state->tournament_id );
		$payload['avg_stack']  = $stack_and_pot['avg_stack'];
		$payload['current_pot'] = $stack_and_pot['current_pot'];

		return $payload;
	}

	/**
	 * Compute average chip stack and current pot (total money collected) for a tournament.
	 *
	 * @since 3.9.2
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array Array with 'avg_stack' (int) and 'current_pot' (float).
	 */
	private function get_avg_stack_and_pot( $tournament_id ) {
		global $wpdb;

		$tournament_id = (int) $tournament_id;

		$result = array(
			'avg_stack'   => 0,
			'current_pot' => 0.0,
		);

		if ( ! $tournament_id ) {
			return $result;
		}

		$stack_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(chip_count), 0) AS total_chips, COUNT(*) AS cnt
				 FROM {$wpdb->prefix}tdwp_tournament_players
				 WHERE tournament_id = %d AND withdrawal_status = 'active' AND finish_position IS NULL",
				$tournament_id
			)
		);

		if ( $stack_row && (int) $stack_row->cnt > 0 ) {
			$result['avg_stack'] = (int) round( $stack_row->total_chips / $stack_row->cnt );
		}

		$pot = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(paid_amount), 0)
				 FROM {$wpdb->prefix}tdwp_tournament_players
				 WHERE tournament_id = %d",
				$tournament_id
			)
		);

		$result['current_pot'] = (float) $pot;

		return $result;
	}

	/**
	 * Get the configured prize payout structure for a tournament.
	 *
	 * Reads the tournament's config snapshot (tdwp-3lg.5) rather than the live
	 * prize-structure template so historical payouts never change retroactively.
	 *
	 * @since 3.10.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array Array of normalized payout rows: place, amount (float|null), percentage (float|null).
	 */
	private function get_prize_payouts( $tournament_id ) {
		$payouts = array();

		$snapshot = TDWP_Tournament_Snapshot::get( $tournament_id );

		if ( ! is_array( $snapshot ) || empty( $snapshot['prizes'] ) || ! is_array( $snapshot['prizes'] ) ) {
			return $payouts;
		}

		foreach ( $snapshot['prizes'] as $prize_structure_row ) {
			$prize_structure_row = (array) $prize_structure_row;

			if ( empty( $prize_structure_row['structure_json'] ) ) {
				continue;
			}

			$places = json_decode( $prize_structure_row['structure_json'], true );

			if ( ! is_array( $places ) ) {
				continue;
			}

			foreach ( $places as $place_row ) {
				$place_row = (array) $place_row;

				if ( isset( $place_row['display'] ) && ! $place_row['display'] ) {
					continue;
				}

				$amount = ( isset( $place_row['amount'] ) && null !== $place_row['amount'] ) ? floatval( $place_row['amount'] ) : null;

				$payouts[] = array(
					'place'      => isset( $place_row['place'] ) ? absint( $place_row['place'] ) : 0,
					'amount'     => $amount,
					'percentage' => isset( $place_row['percentage'] ) ? floatval( $place_row['percentage'] ) : null,
				);
			}
		}

		usort(
			$payouts,
			function( $a, $b ) {
				return $a['place'] <=> $b['place'];
			}
		);

		return $payouts;
	}

	/**
	 * Get the current chip-count rankings (still-in players) for a tournament.
	 *
	 * @since 3.10.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @param int $limit         Maximum number of rows to return.
	 * @return array Array of rows: rank, name, chip_count.
	 */
	private function get_rankings( $tournament_id, $limit ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$limit         = min( 50, max( 1, absint( $limit ) ) );

		$rankings = array();

		if ( ! $tournament_id ) {
			return $rankings;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tp.chip_count, p.post_title AS player_name
				 FROM {$wpdb->prefix}tdwp_tournament_players tp
				 INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
				 WHERE tp.tournament_id = %d
				 AND tp.withdrawal_status = 'active'
				 AND tp.finish_position IS NULL
				 ORDER BY tp.chip_count DESC
				 LIMIT %d",
				$tournament_id,
				$limit
			)
		);

		if ( ! is_array( $rows ) ) {
			return $rankings;
		}

		$rank = 1;
		foreach ( $rows as $row ) {
			$rankings[] = array(
				'rank'       => $rank,
				'name'       => $row->player_name,
				'chip_count' => (int) $row->chip_count,
			);
			++$rank;
		}

		return $rankings;
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
