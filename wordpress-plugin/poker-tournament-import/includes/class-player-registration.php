<?php
/**
 * Player Registration Class
 *
 * Handles frontend player registration via shortcode.
 * Provides form rendering, validation, player creation, and (when a
 * tournament_id is supplied) tournament-specific capacity / waitlist logic.
 *
 * @package Poker_Tournament_Import
 * @since 3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player Registration class
 *
 * @since 3.0.0
 */
class TDWP_Player_Registration {

	/**
	 * Player Manager instance
	 *
	 * @var TDWP_Player_Manager
	 */
	private $player_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-manager.php';
		$this->player_manager = new TDWP_Player_Manager();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_tdwp_register_player', array( $this, 'ajax_register_player' ) );
		add_action( 'wp_ajax_nopriv_tdwp_register_player', array( $this, 'ajax_register_player' ) );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @since 3.0.0
	 */
	public function enqueue_assets() {
		if ( ! is_singular() && ! has_shortcode( get_the_content(), 'player_registration' ) ) {
			return;
		}

		wp_enqueue_style(
			'tdwp-player-registration',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/player-registration-frontend.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		wp_enqueue_script(
			'tdwp-player-registration',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/player-registration-frontend.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		wp_localize_script(
			'tdwp-player-registration',
			'tdwpPlayerReg',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tdwp_player_registration' ),
				'i18n'    => array(
					'requiredName'    => __( 'Please enter your name.', 'poker-tournament-import' ),
					'requiredEmail'   => __( 'Please enter a valid email address.', 'poker-tournament-import' ),
					'errorSubmitting' => __( 'Error submitting registration. Please try again.', 'poker-tournament-import' ),
					'successMessage'  => __( 'Registration successful! Thank you for registering.', 'poker-tournament-import' ),
				),
			)
		);
	}

	/**
	 * Render registration form shortcode
	 *
	 * Accepts an optional tournament_id attribute. When supplied, remaining
	 * capacity is displayed and over-capacity registrations are waitlisted.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_registration_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'           => __( 'Player Registration', 'poker-tournament-import' ),
				'require_email'   => 'yes',
				'require_phone'   => 'no',
				'show_bio'        => 'no',
				'success_message' => __( 'Thank you for registering! We will contact you soon.', 'poker-tournament-import' ),
				'tournament_id'   => 0,
			),
			$atts,
			'player_registration'
		);

		$tournament_id = absint( $atts['tournament_id'] );
		$capacity_info = $this->get_capacity_info( $tournament_id );

		ob_start();
		?>
		<div class="tdwp-player-registration-form">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h2 class="registration-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<?php if ( $tournament_id > 0 ) : ?>
				<div class="tdwp-capacity-notice">
					<?php if ( $capacity_info['is_unlimited'] ) : ?>
						<?php /* unlimited — show nothing */ ?>
					<?php elseif ( $capacity_info['is_full'] ) : ?>
						<p class="capacity-waitlist">
							<?php esc_html_e( 'This tournament is full — you will be added to the waiting list.', 'poker-tournament-import' ); ?>
						</p>
					<?php elseif ( 1 === $capacity_info['remaining'] ) : ?>
						<p class="capacity-low">
							<?php esc_html_e( '1 seat remaining', 'poker-tournament-import' ); ?>
						</p>
					<?php else : ?>
						<p class="capacity-available">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of available seats */
									_n( '%d seat remaining', '%d seats remaining', $capacity_info['remaining'], 'poker-tournament-import' ),
									$capacity_info['remaining']
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="registration-messages">
				<div class="registration-success" style="display:none;">
					<p><?php echo esc_html( $atts['success_message'] ); ?></p>
				</div>
				<div class="registration-error" style="display:none;">
					<p></p>
				</div>
			</div>

			<form class="player-registration-form" method="post">
				<?php wp_nonce_field( 'tdwp_player_registration', 'player_registration_nonce' ); ?>

				<?php if ( $tournament_id > 0 ) : ?>
					<input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
				<?php endif; ?>

				<div class="form-row">
					<label for="player_name">
						<?php esc_html_e( 'Full Name', 'poker-tournament-import' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text"
						   name="player_name"
						   id="player_name"
						   required
						   placeholder="<?php esc_attr_e( 'Enter your full name', 'poker-tournament-import' ); ?>">
				</div>

				<div class="form-row">
					<label for="player_email">
						<?php esc_html_e( 'Email Address', 'poker-tournament-import' ); ?>
						<?php if ( 'yes' === $atts['require_email'] ) : ?>
							<span class="required">*</span>
						<?php endif; ?>
					</label>
					<input type="email"
						   name="player_email"
						   id="player_email"
						   <?php echo 'yes' === $atts['require_email'] ? 'required' : ''; ?>
						   placeholder="<?php esc_attr_e( 'Enter your email address', 'poker-tournament-import' ); ?>">
				</div>

				<?php if ( 'yes' === $atts['require_phone'] || 'optional' === $atts['require_phone'] ) : ?>
					<div class="form-row">
						<label for="player_phone">
							<?php esc_html_e( 'Phone Number', 'poker-tournament-import' ); ?>
							<?php if ( 'yes' === $atts['require_phone'] ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
						</label>
						<input type="tel"
							   name="player_phone"
							   id="player_phone"
							   <?php echo 'yes' === $atts['require_phone'] ? 'required' : ''; ?>
							   placeholder="<?php esc_attr_e( 'Enter your phone number', 'poker-tournament-import' ); ?>">
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === $atts['show_bio'] ) : ?>
					<div class="form-row">
						<label for="player_bio">
							<?php esc_html_e( 'About You (Optional)', 'poker-tournament-import' ); ?>
						</label>
						<textarea name="player_bio"
								  id="player_bio"
								  rows="4"
								  placeholder="<?php esc_attr_e( 'Tell us about yourself...', 'poker-tournament-import' ); ?>"></textarea>
					</div>
				<?php endif; ?>

				<!-- Honeypot field for spam protection -->
				<div class="hp-field" style="display:none;">
					<label for="player_website"><?php esc_html_e( 'Website', 'poker-tournament-import' ); ?></label>
					<input type="text"
						   name="player_website"
						   id="player_website"
						   tabindex="-1"
						   autocomplete="off">
				</div>

				<div class="form-row submit-row">
					<button type="submit" class="button submit-button">
						<?php esc_html_e( 'Register', 'poker-tournament-import' ); ?>
					</button>
					<span class="spinner" style="display:none;"></span>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Register new player
	 *
	 * @since 3.0.0
	 */
	public function ajax_register_player() {
		check_ajax_referer( 'tdwp_player_registration', 'nonce' );

		// Honeypot check - if filled, it's spam.
		if ( ! empty( $_POST['player_website'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid submission.', 'poker-tournament-import' ) ) );
		}

		// Per-IP rate limit: this is a public (nopriv) form, so cap how many
		// registrations a single IP can submit per window to blunt spam without
		// blocking other users. (tdwp-hk3)
		if ( class_exists( 'TDWP_Ajax_Guards' ) ) {
			$max    = (int) apply_filters( 'tdwp_register_player_max_per_window', 5 );
			$window = (int) apply_filters( 'tdwp_register_player_window', HOUR_IN_SECONDS );
			if ( TDWP_Ajax_Guards::is_rate_limited( 'tdwp_register_player', $max, $window ) ) {
				wp_send_json_error( array( 'message' => __( 'Too many registration attempts. Please try again later.', 'poker-tournament-import' ) ) );
			}
		}

		// Sanitize and validate input.
		$player_data = array(
			'name'   => isset( $_POST['player_name'] ) ? sanitize_text_field( wp_unslash( $_POST['player_name'] ) ) : '',
			'email'  => isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '',
			'phone'  => isset( $_POST['player_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['player_phone'] ) ) : '',
			'bio'    => isset( $_POST['player_bio'] ) ? wp_kses_post( wp_unslash( $_POST['player_bio'] ) ) : '',
			'status' => 'pending', // Pending review by default.
		);

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

		// Validate required fields.
		if ( empty( $player_data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'poker-tournament-import' ) ) );
		}

		// Create player profile.
		$result = $this->player_manager->create( $player_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Determine registration status and optionally link to a tournament.
		$registration_status = 'confirmed';
		$waitlist_position   = 0;

		if ( $tournament_id > 0 && class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$capacity_info = $this->get_capacity_info( $tournament_id );

			if ( $capacity_info['is_full'] ) {
				$registration_status = 'waitlisted';
				$waitlist_position   = TDWP_Tournament_Player_Manager::get_next_waitlist_position( $tournament_id );

				TDWP_Tournament_Player_Manager::add_player(
					$tournament_id,
					$result,
					array(
						'status'           => 'waitlisted',
						'waitlist_position' => $waitlist_position,
					)
				);
			} else {
				TDWP_Tournament_Player_Manager::add_player(
					$tournament_id,
					$result,
					array( 'status' => 'registered' )
				);
			}
		}

		// Retrieve tournament name (if applicable) for emails.
		$tournament_name = '';
		if ( $tournament_id > 0 ) {
			$tournament_post = get_post( $tournament_id );
			if ( $tournament_post ) {
				$tournament_name = $tournament_post->post_title;
			}
		}

		// Send notification email to admin.
		$this->send_admin_notification( $player_data, $result );

		// Send confirmation email to the registrant (cma.2).
		if ( ! empty( $player_data['email'] ) ) {
			$this->send_registrant_confirmation( $player_data, $registration_status, $tournament_name, $waitlist_position );
		}

		$success_message = 'waitlisted' === $registration_status
			? __( 'You have been added to the waiting list. We will contact you if a spot opens up.', 'poker-tournament-import' )
			: __( 'Registration successful! Thank you for registering.', 'poker-tournament-import' );

		wp_send_json_success(
			array(
				'message'              => $success_message,
				'player_id'            => $result,
				'registration_status'  => $registration_status,
				'waitlist_position'    => $waitlist_position,
			)
		);
	}

	/**
	 * Get capacity information for a tournament
	 *
	 * When no tournament_id is provided (0) or no max_players is configured,
	 * the tournament is treated as unlimited. This preserves backward
	 * compatibility with tournaments that predate the capacity feature.
	 *
	 * @since 3.5.0
	 *
	 * @param int $tournament_id Tournament post ID (0 = no tournament).
	 * @return array {
	 *     @type bool $is_unlimited True when capacity is unconfigured.
	 *     @type int  $max         Maximum players (0 = unlimited).
	 *     @type int  $confirmed   Current confirmed registration count.
	 *     @type int  $remaining   Available seats (0 when full or unlimited).
	 *     @type bool $is_full     True when confirmed >= max and max > 0.
	 * }
	 */
	public function get_capacity_info( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		if ( 0 === $tournament_id ) {
			return array(
				'is_unlimited' => true,
				'max'          => 0,
				'confirmed'    => 0,
				'remaining'    => 0,
				'is_full'      => false,
			);
		}

		$max_players = absint( get_post_meta( $tournament_id, '_max_players', true ) );

		if ( 0 === $max_players ) {
			return array(
				'is_unlimited' => true,
				'max'          => 0,
				'confirmed'    => 0,
				'remaining'    => 0,
				'is_full'      => false,
			);
		}

		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$confirmed = TDWP_Tournament_Player_Manager::get_confirmed_count( $tournament_id );
		} else {
			$confirmed = 0;
		}

		$remaining = max( 0, $max_players - $confirmed );

		return array(
			'is_unlimited' => false,
			'max'          => $max_players,
			'confirmed'    => $confirmed,
			'remaining'    => $remaining,
			'is_full'      => $confirmed >= $max_players,
		);
	}

	/**
	 * Send admin notification email
	 *
	 * @since 3.0.0
	 *
	 * @param array $player_data Player data.
	 * @param int   $player_id   Created player ID.
	 */
	private function send_admin_notification( $player_data, $player_id ) {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] New Player Registration', 'poker-tournament-import' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: player name, 2: player email, 3: admin URL */
			__( "A new player has registered:\n\nName: %1\$s\nEmail: %2\$s\n\nReview this player: %3\$s", 'poker-tournament-import' ),
			$player_data['name'],
			$player_data['email'],
			admin_url( 'post.php?post=' . $player_id . '&action=edit' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send confirmation email to the registrant (cma.2)
	 *
	 * Sends a translatable, escaped email to the address the player supplied,
	 * reflecting whether they are confirmed or waitlisted.
	 *
	 * @since 3.5.0
	 *
	 * @param array  $player_data         Player data (name, email).
	 * @param string $registration_status 'confirmed' or 'waitlisted'.
	 * @param string $tournament_name     Tournament name, empty when not tournament-linked.
	 * @param int    $waitlist_position   Waitlist position (only meaningful when waitlisted).
	 */
	public function send_registrant_confirmation( $player_data, $registration_status, $tournament_name = '', $waitlist_position = 0 ) {
		$to   = sanitize_email( $player_data['email'] );
		$name = sanitize_text_field( $player_data['name'] );

		if ( empty( $to ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );

		if ( 'waitlisted' === $registration_status ) {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] You are on the waiting list', 'poker-tournament-import' ),
				$site_name
			);

			if ( ! empty( $tournament_name ) ) {
				$body = sprintf(
					/* translators: 1: player name, 2: tournament name, 3: waitlist position, 4: site name */
					__( "Hi %1\$s,\n\nThank you for registering for %2\$s.\n\nThe tournament is currently full. You have been added to the waiting list at position #%3\$d.\n\nWe will contact you if a spot becomes available.\n\n%4\$s", 'poker-tournament-import' ),
					$name,
					$tournament_name,
					(int) $waitlist_position,
					$site_name
				);
			} else {
				$body = sprintf(
					/* translators: 1: player name, 2: waitlist position, 3: site name */
					__( "Hi %1\$s,\n\nThank you for registering. The tournament is currently full. You have been added to the waiting list at position #%2\$d.\n\nWe will contact you if a spot becomes available.\n\n%3\$s", 'poker-tournament-import' ),
					$name,
					(int) $waitlist_position,
					$site_name
				);
			}
		} else {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Registration confirmed', 'poker-tournament-import' ),
				$site_name
			);

			if ( ! empty( $tournament_name ) ) {
				$body = sprintf(
					/* translators: 1: player name, 2: tournament name, 3: site name */
					__( "Hi %1\$s,\n\nYour registration for %2\$s has been confirmed.\n\nWe look forward to seeing you!\n\n%3\$s", 'poker-tournament-import' ),
					$name,
					$tournament_name,
					$site_name
				);
			} else {
				$body = sprintf(
					/* translators: 1: player name, 2: site name */
					__( "Hi %1\$s,\n\nYour registration has been confirmed. Thank you!\n\n%2\$s", 'poker-tournament-import' ),
					$name,
					$site_name
				);
			}
		}

		wp_mail( $to, $subject, $body );
	}
}
