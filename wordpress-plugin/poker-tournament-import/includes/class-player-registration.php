<?php
/**
 * Player Registration Class
 *
 * Handles frontend player registration via shortcode.
 * Provides form rendering, validation, and player creation.
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
					'requiredName'  => __( 'Please enter your name.', 'poker-tournament-import' ),
					'requiredEmail' => __( 'Please enter a valid email address.', 'poker-tournament-import' ),
					'errorSubmitting' => __( 'Error submitting registration. Please try again.', 'poker-tournament-import' ),
					'successMessage' => __( 'Registration successful! Thank you for registering.', 'poker-tournament-import' ),
				),
			)
		);
	}

	/**
	 * Render registration form shortcode
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
			),
			$atts,
			'player_registration'
		);

		ob_start();
		?>
		<div class="tdwp-player-registration-form">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h2 class="registration-title"><?php echo esc_html( $atts['title'] ); ?></h2>
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

		// Sanitize and validate input.
		$player_data = array(
			'name'   => isset( $_POST['player_name'] ) ? sanitize_text_field( wp_unslash( $_POST['player_name'] ) ) : '',
			'email'  => isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '',
			'phone'  => isset( $_POST['player_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['player_phone'] ) ) : '',
			'bio'    => isset( $_POST['player_bio'] ) ? wp_kses_post( wp_unslash( $_POST['player_bio'] ) ) : '',
			'status' => 'pending', // Pending review by default.
		);

		// Validate required fields.
		if ( empty( $player_data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'poker-tournament-import' ) ) );
		}

		// Create player.
		$result = $this->player_manager->create( $player_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Send notification email to admin (optional).
		$this->send_admin_notification( $player_data, $result );

		wp_send_json_success(
			array(
				'message'   => __( 'Registration successful! Thank you for registering.', 'poker-tournament-import' ),
				'player_id' => $result,
			)
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
}
