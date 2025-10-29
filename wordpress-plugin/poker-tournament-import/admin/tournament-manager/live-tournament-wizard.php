<?php
/**
 * Live Tournament Wizard Admin Page
 *
 * Provides wizard interface for creating new live tournaments with options to:
 * - Start blank tournament
 * - Create from template
 * - Copy from existing tournament
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
 * Live Tournament Wizard Page class
 *
 * @since 3.1.0
 */
class TDWP_Live_Tournament_Wizard {

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_tdwp_create_live_tournament', array( $this, 'ajax_create_tournament' ) );
		add_action( 'wp_ajax_tdwp_get_template_data', array( $this, 'ajax_get_template_data' ) );
		add_action( 'wp_ajax_tdwp_get_tournament_data', array( $this, 'ajax_get_tournament_data' ) );
	}

	/**
	 * Add menu page
	 *
	 * @since 3.1.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'tdwp-tournament-manager',
			__( 'New Live Tournament', 'poker-tournament-import' ),
			__( 'New Live Tournament', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-live-tournament-wizard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 3.1.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tournament-manager_page_tdwp-live-tournament-wizard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tdwp-live-tournament-wizard',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/live-tournament-wizard.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		wp_enqueue_script(
			'tdwp-live-tournament-wizard',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/live-tournament-wizard.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		wp_localize_script(
			'tdwp-live-tournament-wizard',
			'tdwpWizard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tdwp_live_tournament_wizard' ),
			)
		);
	}

	/**
	 * Render wizard page
	 *
	 * @since 3.1.0
	 */
	public function render_page() {
		?>
		<div class="wrap tdwp-wizard-wrap">
			<h1><?php esc_html_e( 'Create New Live Tournament', 'poker-tournament-import' ); ?></h1>

			<div class="tdwp-wizard-container">
				<!-- Step 1: Choose creation method -->
				<div class="tdwp-wizard-step" id="step-method" style="display: block;">
					<h2><?php esc_html_e( 'Choose Creation Method', 'poker-tournament-import' ); ?></h2>

					<div class="tdwp-creation-methods">
						<div class="tdwp-method-card" data-method="blank">
							<div class="dashicons dashicons-plus-alt"></div>
							<h3><?php esc_html_e( 'Start Blank', 'poker-tournament-import' ); ?></h3>
							<p><?php esc_html_e( 'Create a new tournament from scratch', 'poker-tournament-import' ); ?></p>
							<button type="button" class="button button-primary tdwp-select-method"><?php esc_html_e( 'Select', 'poker-tournament-import' ); ?></button>
						</div>

						<div class="tdwp-method-card" data-method="template">
							<div class="dashicons dashicons-admin-page"></div>
							<h3><?php esc_html_e( 'From Template', 'poker-tournament-import' ); ?></h3>
							<p><?php esc_html_e( 'Use a pre-configured tournament template', 'poker-tournament-import' ); ?></p>
							<button type="button" class="button button-primary tdwp-select-method"><?php esc_html_e( 'Select', 'poker-tournament-import' ); ?></button>
						</div>

						<div class="tdwp-method-card" data-method="copy">
							<div class="dashicons dashicons-admin-post"></div>
							<h3><?php esc_html_e( 'Copy Tournament', 'poker-tournament-import' ); ?></h3>
							<p><?php esc_html_e( 'Copy settings from a previous tournament', 'poker-tournament-import' ); ?></p>
							<button type="button" class="button button-primary tdwp-select-method"><?php esc_html_e( 'Select', 'poker-tournament-import' ); ?></button>
						</div>
					</div>
				</div>

				<!-- Step 2: Configure tournament (method-specific) -->
				<div class="tdwp-wizard-step" id="step-configure" style="display: none;">
					<h2><?php esc_html_e( 'Configure Tournament', 'poker-tournament-import' ); ?></h2>

					<form id="tdwp-tournament-form">
						<?php wp_nonce_field( 'tdwp_live_tournament_wizard', 'tdwp_wizard_nonce' ); ?>
						<input type="hidden" name="creation_method" id="creation-method" value="">

						<!-- Basic Info (all methods) -->
						<div class="tdwp-form-section">
							<h3><?php esc_html_e( 'Basic Information', 'poker-tournament-import' ); ?></h3>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="tournament-name"><?php esc_html_e( 'Tournament Name', 'poker-tournament-import' ); ?> <span class="required">*</span></label>
									</th>
									<td>
										<input type="text" name="tournament_name" id="tournament-name" class="regular-text" required>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="buy-in"><?php esc_html_e( 'Buy-in Amount', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="buy_in" id="buy-in" class="small-text" step="0.01" min="0">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="starting-chips"><?php esc_html_e( 'Starting Chips', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="starting_chips" id="starting-chips" class="small-text" step="1" min="0">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="rebuy-cost"><?php esc_html_e( 'Rebuy Cost', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="rebuy_cost" id="rebuy-cost" class="small-text" step="0.01" min="0" value="0">
										<p class="description"><?php esc_html_e( 'Cost for a rebuy. Leave 0 if no rebuys allowed.', 'poker-tournament-import' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="rebuy-chips"><?php esc_html_e( 'Rebuy Chips', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="rebuy_chips" id="rebuy-chips" class="small-text" step="1" min="0" value="0">
										<p class="description"><?php esc_html_e( 'Chip amount for a rebuy.', 'poker-tournament-import' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="addon-cost"><?php esc_html_e( 'Add-on Cost', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="addon_cost" id="addon-cost" class="small-text" step="0.01" min="0" value="0">
										<p class="description"><?php esc_html_e( 'Cost for add-on. Leave 0 if no add-ons allowed.', 'poker-tournament-import' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="addon-chips"><?php esc_html_e( 'Add-on Chips', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="addon_chips" id="addon-chips" class="small-text" step="1" min="0" value="0">
										<p class="description"><?php esc_html_e( 'Chip amount for add-on.', 'poker-tournament-import' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="rake-percentage"><?php esc_html_e( 'Rake Percentage', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<input type="number" name="rake_percentage" id="rake-percentage" class="small-text" step="0.01" min="0" max="100" value="0">
										<span>%</span>
										<p class="description"><?php esc_html_e( 'Percentage of prize pool taken as rake/house fee.', 'poker-tournament-import' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="is-practice"><?php esc_html_e( 'Practice Mode', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="is_practice" id="is-practice" value="1">
											<?php esc_html_e( 'Exclude from statistics (for testing/practice)', 'poker-tournament-import' ); ?>
										</label>
									</td>
								</tr>
							</table>
						</div>

						<!-- Template Selection (template method) -->
						<div class="tdwp-form-section" id="template-section" style="display: none;">
							<h3><?php esc_html_e( 'Select Template', 'poker-tournament-import' ); ?></h3>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="template-select"><?php esc_html_e( 'Template', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<select name="template_id" id="template-select" class="regular-text">
											<option value=""><?php esc_html_e( 'Select a template...', 'poker-tournament-import' ); ?></option>
											<?php echo $this->get_template_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</select>
									</td>
								</tr>
							</table>
						</div>

						<!-- Tournament Selection (copy method) -->
						<div class="tdwp-form-section" id="copy-section" style="display: none;">
							<h3><?php esc_html_e( 'Select Tournament to Copy', 'poker-tournament-import' ); ?></h3>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="source-tournament"><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></label>
									</th>
									<td>
										<select name="source_tournament_id" id="source-tournament" class="regular-text">
											<option value=""><?php esc_html_e( 'Select a tournament...', 'poker-tournament-import' ); ?></option>
											<?php echo $this->get_tournament_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</select>
									</td>
								</tr>
							</table>

							<h4><?php esc_html_e( 'Copy Options', 'poker-tournament-import' ); ?></h4>
							<fieldset>
								<label>
									<input type="checkbox" name="copy_players" value="1" checked>
									<?php esc_html_e( 'Copy Players', 'poker-tournament-import' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="copy_tables" value="1" checked>
									<?php esc_html_e( 'Copy Table Configuration', 'poker-tournament-import' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="copy_blinds" value="1" checked>
									<?php esc_html_e( 'Copy Blind Schedule', 'poker-tournament-import' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="copy_prizes" value="1" checked>
									<?php esc_html_e( 'Copy Prize Structure', 'poker-tournament-import' ); ?>
								</label>
							</fieldset>
						</div>

						<p class="submit">
							<button type="button" class="button" id="btn-back"><?php esc_html_e( 'Back', 'poker-tournament-import' ); ?></button>
							<button type="submit" class="button button-primary" id="btn-create"><?php esc_html_e( 'Create Tournament', 'poker-tournament-import' ); ?></button>
						</p>
					</form>
				</div>

				<!-- Step 3: Success -->
				<div class="tdwp-wizard-step" id="step-success" style="display: none;">
					<div class="tdwp-success-message">
						<div class="dashicons dashicons-yes-alt"></div>
						<h2><?php esc_html_e( 'Tournament Created Successfully!', 'poker-tournament-import' ); ?></h2>
						<p id="success-message"></p>
						<p>
							<a href="" class="button button-primary" id="btn-manage"><?php esc_html_e( 'Manage Tournament', 'poker-tournament-import' ); ?></a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tdwp-live-tournament-wizard' ) ); ?>" class="button"><?php esc_html_e( 'Create Another', 'poker-tournament-import' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get template options for dropdown
	 *
	 * @since 3.1.0
	 * @return string HTML options.
	 */
	private function get_template_options() {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_templates';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$templates = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC" );

		$options = '';
		foreach ( $templates as $template ) {
			$options .= sprintf(
				'<option value="%d">%s</option>',
				esc_attr( $template->id ),
				esc_html( $template->name )
			);
		}

		return $options;
	}

	/**
	 * Get tournament options for dropdown
	 *
	 * @since 3.1.0
	 * @return string HTML options.
	 */
	private function get_tournament_options() {
		$tournaments = get_posts(
			array(
				'post_type'      => 'tournament',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$options = '';
		foreach ( $tournaments as $tournament ) {
			$options .= sprintf(
				'<option value="%d">%s</option>',
				esc_attr( $tournament->ID ),
				esc_html( $tournament->post_title )
			);
		}

		return $options;
	}

	/**
	 * AJAX: Create live tournament
	 *
	 * @since 3.1.0
	 */
	public function ajax_create_tournament() {
		check_ajax_referer( 'tdwp_live_tournament_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'poker-tournament-import' ) ) );
		}

		$method = isset( $_POST['creation_method'] ) ? sanitize_text_field( wp_unslash( $_POST['creation_method'] ) ) : '';
		$name   = isset( $_POST['tournament_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tournament_name'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Tournament name is required', 'poker-tournament-import' ) ) );
		}

		// Create live_tournament post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => $name,
				'post_type'   => 'live_tournament',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create tournament', 'poker-tournament-import' ) ) );
		}

		// Save meta fields.
		$buy_in          = isset( $_POST['buy_in'] ) ? floatval( $_POST['buy_in'] ) : 0;
		$starting_chips  = isset( $_POST['starting_chips'] ) ? intval( $_POST['starting_chips'] ) : 0;
		$rebuy_cost      = isset( $_POST['rebuy_cost'] ) ? floatval( $_POST['rebuy_cost'] ) : 0;
		$rebuy_chips     = isset( $_POST['rebuy_chips'] ) ? intval( $_POST['rebuy_chips'] ) : 0;
		$addon_cost      = isset( $_POST['addon_cost'] ) ? floatval( $_POST['addon_cost'] ) : 0;
		$addon_chips     = isset( $_POST['addon_chips'] ) ? intval( $_POST['addon_chips'] ) : 0;
		$rake_percentage = isset( $_POST['rake_percentage'] ) ? floatval( $_POST['rake_percentage'] ) : 0;
		$is_practice     = isset( $_POST['is_practice'] ) && '1' === $_POST['is_practice'] ? 1 : 0;

		update_post_meta( $post_id, '_status', 'pending' );
		update_post_meta( $post_id, '_buy_in', $buy_in );
		update_post_meta( $post_id, '_starting_chips', $starting_chips );
		update_post_meta( $post_id, '_rebuy_cost', $rebuy_cost );
		update_post_meta( $post_id, '_rebuy_chips', $rebuy_chips );
		update_post_meta( $post_id, '_addon_cost', $addon_cost );
		update_post_meta( $post_id, '_addon_chips', $addon_chips );
		update_post_meta( $post_id, '_rake_percentage', $rake_percentage );
		update_post_meta( $post_id, '_creation_method', $method );
		update_post_meta( $post_id, '_is_practice', $is_practice );

		// Handle method-specific data.
		switch ( $method ) {
			case 'template':
				$this->create_from_template( $post_id, $_POST );
				break;
			case 'copy':
				$this->create_from_tournament( $post_id, $_POST );
				break;
			case 'blank':
			default:
				// Nothing extra for blank.
				break;
		}

		// Set as active tournament for current user.
		TDWP_Active_Tournament_Manager::set_active_tournament( get_current_user_id(), $post_id );

		wp_send_json_success(
			array(
				'message'       => __( 'Tournament created successfully', 'poker-tournament-import' ),
				'tournament_id' => $post_id,
				'manage_url'    => admin_url( 'admin.php?page=tdwp-live-control&tournament_id=' . $post_id ),
			)
		);
	}

	/**
	 * Create tournament from template
	 *
	 * @since 3.1.0
	 * @param int   $post_id Tournament post ID.
	 * @param array $data    Form data.
	 */
	private function create_from_template( $post_id, $data ) {
		$template_id = isset( $data['template_id'] ) ? intval( $data['template_id'] ) : 0;

		if ( ! $template_id ) {
			return;
		}

		update_post_meta( $post_id, '_template_id', $template_id );

		// Load template data and copy blind schedule, prize structure.
		global $wpdb;
		$table    = $wpdb->prefix . 'tdwp_tournament_templates';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ) );

		if ( $template ) {
			if ( ! empty( $template->blind_schedule_id ) ) {
				update_post_meta( $post_id, '_blind_schedule_id', $template->blind_schedule_id );
			}
			if ( ! empty( $template->prize_structure_id ) ) {
				update_post_meta( $post_id, '_prize_structure_id', $template->prize_structure_id );
			}
		}
	}

	/**
	 * Create tournament from existing tournament
	 *
	 * @since 3.1.0
	 * @param int   $post_id Tournament post ID.
	 * @param array $data    Form data.
	 */
	private function create_from_tournament( $post_id, $data ) {
		$source_id = isset( $data['source_tournament_id'] ) ? intval( $data['source_tournament_id'] ) : 0;

		if ( ! $source_id ) {
			return;
		}

		update_post_meta( $post_id, '_source_tournament_id', $source_id );

		// Copy options.
		$copy_players = isset( $data['copy_players'] ) && '1' === $data['copy_players'];
		$copy_tables  = isset( $data['copy_tables'] ) && '1' === $data['copy_tables'];
		$copy_blinds  = isset( $data['copy_blinds'] ) && '1' === $data['copy_blinds'];
		$copy_prizes  = isset( $data['copy_prizes'] ) && '1' === $data['copy_prizes'];

		// Copy blind schedule.
		if ( $copy_blinds ) {
			$blind_schedule_id = get_post_meta( $source_id, 'blind_schedule_id', true );
			if ( $blind_schedule_id ) {
				update_post_meta( $post_id, '_blind_schedule_id', $blind_schedule_id );
			}
		}

		// Copy prize structure.
		if ( $copy_prizes ) {
			$prize_structure_id = get_post_meta( $source_id, 'prize_structure_id', true );
			if ( $prize_structure_id ) {
				update_post_meta( $post_id, '_prize_structure_id', $prize_structure_id );
			}
		}

		// Copy players (Phase 3 - will implement when player system ready).
		if ( $copy_players ) {
			// TODO: Copy players from source tournament.
			update_post_meta( $post_id, '_copy_players_pending', true );
		}

		// Copy tables (Phase 3 - will implement when table system ready).
		if ( $copy_tables ) {
			// TODO: Copy table configuration from source tournament.
			update_post_meta( $post_id, '_copy_tables_pending', true );
		}
	}

	/**
	 * AJAX: Get template data
	 *
	 * @since 3.1.0
	 */
	public function ajax_get_template_data() {
		check_ajax_referer( 'tdwp_live_tournament_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'poker-tournament-import' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID', 'poker-tournament-import' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_templates';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ) );

		if ( ! $template ) {
			wp_send_json_error( array( 'message' => __( 'Template not found', 'poker-tournament-import' ) ) );
		}

		wp_send_json_success( array( 'template' => $template ) );
	}

	/**
	 * AJAX: Get tournament data
	 *
	 * @since 3.1.0
	 */
	public function ajax_get_tournament_data() {
		check_ajax_referer( 'tdwp_live_tournament_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'poker-tournament-import' ) ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? intval( $_POST['tournament_id'] ) : 0;

		if ( ! $tournament_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tournament ID', 'poker-tournament-import' ) ) );
		}

		$tournament = get_post( $tournament_id );

		if ( ! $tournament || 'tournament' !== $tournament->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Tournament not found', 'poker-tournament-import' ) ) );
		}

		// Get tournament meta.
		$meta = array(
			'buy_in'              => get_post_meta( $tournament_id, 'buy_in', true ),
			'starting_chips'      => get_post_meta( $tournament_id, 'starting_chips', true ),
			'blind_schedule_id'   => get_post_meta( $tournament_id, 'blind_schedule_id', true ),
			'prize_structure_id'  => get_post_meta( $tournament_id, 'prize_structure_id', true ),
		);

		wp_send_json_success(
			array(
				'tournament' => $tournament,
				'meta'       => $meta,
			)
		);
	}
}
