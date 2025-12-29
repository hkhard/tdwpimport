<?php
/**
 * Tournament Templates Admin Page
 *
 * Provides UI for creating, editing, and managing tournament templates
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tournament Templates admin page class
 *
 * @since 3.0.0
 */
class TDWP_Tournament_Templates_Page {

	/**
	 * Template manager instance
	 *
	 * @var TDWP_Tournament_Template
	 */
	private $template_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->template_manager = new TDWP_Tournament_Template();

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handle form submissions
		add_action( 'admin_post_tdwp_save_tournament_template', array( $this, 'handle_save' ) );
		add_action( 'admin_post_tdwp_delete_tournament_template', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_tdwp_clone_tournament_template', array( $this, 'handle_clone' ) );
	}

	/**
	 * Add admin menu item
	 *
	 * @since 3.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Tournament Manager', 'poker-tournament-import' ),
			__( 'Tournament Manager', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-tournament-manager',
			array( $this, 'render_page' ),
			'dashicons-trophy',
			30
		);

		add_submenu_page(
			'tdwp-tournament-manager',
			__( 'Tournament Templates', 'poker-tournament-import' ),
			__( 'Templates', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-tournament-manager',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 3.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin page
		if ( 'toplevel_page_tdwp-tournament-manager' !== $hook ) {
			return;
		}

		// Enqueue styles
		wp_enqueue_style(
			'tdwp-tournament-templates',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/tournament-manager-admin.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'tdwp-tournament-templates',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-template-admin.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		// Localize script for AJAX and i18n
		wp_localize_script(
			'tdwp-tournament-templates',
			'tdwpTemplates',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tdwp_tournament_template_nonce' ),
				'i18n'        => array(
					'confirmDelete' => __( 'Are you sure you want to delete this template?', 'poker-tournament-import' ),
					'saving'        => __( 'Saving...', 'poker-tournament-import' ),
					'saved'         => __( 'Template saved successfully!', 'poker-tournament-import' ),
					'error'         => __( 'An error occurred. Please try again.', 'poker-tournament-import' ),
				),
				'currency'    => get_option( 'tdwp_currency_symbol', '$' ),
			)
		);
	}

	/**
	 * Render the main page
	 *
	 * @since 3.0.0
	 */
	public function render_page() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'poker-tournament-import' ) );
		}

		// Get action
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		// Render appropriate view
		switch ( $action ) {
			case 'edit':
				$this->render_edit_form();
				break;
			case 'add':
				$this->render_add_form();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render template list
	 *
	 * @since 3.0.0
	 */
	private function render_list() {
		// Get search term
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Get pagination
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		// Get templates
		$templates = $this->template_manager->get_all(
			array(
				'search'         => $search,
				'limit'          => $per_page,
				'offset'         => $offset,
				'with_relations' => true,
			)
		);

		// Get total count for pagination
		$total_items = $this->template_manager->get_count( $search );
		$total_pages = ceil( $total_items / $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Tournament Templates', 'poker-tournament-import' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tdwp-tournament-manager&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'poker-tournament-import' ); ?>
			</a>

			<?php if ( isset( $_GET['message'] ) ) : ?>
				<?php $this->render_admin_notice( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ); ?>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="tdwp-tournament-manager">
				<?php
				wp_nonce_field( 'tdwp_search_templates', 'search_nonce' );
				?>
				<p class="search-box">
					<label class="screen-reader-text" for="template-search-input">
						<?php esc_html_e( 'Search Templates:', 'poker-tournament-import' ); ?>
					</label>
					<input type="search" id="template-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Templates', 'poker-tournament-import' ); ?>">
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary">
							<?php esc_html_e( 'Template Name', 'poker-tournament-import' ); ?>
						</th>
						<th scope="col" class="manage-column column-buyin">
							<?php esc_html_e( 'Buy-in', 'poker-tournament-import' ); ?>
						</th>
						<th scope="col" class="manage-column column-chips">
							<?php esc_html_e( 'Starting Chips', 'poker-tournament-import' ); ?>
						</th>
						<th scope="col" class="manage-column column-blind">
							<?php esc_html_e( 'Blind Schedule', 'poker-tournament-import' ); ?>
						</th>
						<th scope="col" class="manage-column column-prize">
							<?php esc_html_e( 'Prize Structure', 'poker-tournament-import' ); ?>
						</th>
						<th scope="col" class="manage-column column-date">
							<?php esc_html_e( 'Created', 'poker-tournament-import' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $templates ) ) : ?>
						<tr>
							<td colspan="6" class="colspanchange">
								<?php esc_html_e( 'No templates found.', 'poker-tournament-import' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $templates as $template ) : ?>
							<tr>
								<td class="column-name column-primary" data-colname="<?php esc_attr_e( 'Template Name', 'poker-tournament-import' ); ?>">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tdwp-tournament-manager&action=edit&template_id=' . absint( $template->id ) ) ); ?>">
											<?php echo esc_html( $template->name ); ?>
										</a>
									</strong>
									<?php $this->render_row_actions( $template ); ?>
								</td>
								<td class="column-buyin" data-colname="<?php esc_attr_e( 'Buy-in', 'poker-tournament-import' ); ?>">
									<?php echo esc_html( $this->format_currency( $template->buy_in ) ); ?>
								</td>
								<td class="column-chips" data-colname="<?php esc_attr_e( 'Starting Chips', 'poker-tournament-import' ); ?>">
									<?php echo esc_html( number_format_i18n( $template->starting_chips ) ); ?>
								</td>
								<td class="column-blind" data-colname="<?php esc_attr_e( 'Blind Schedule', 'poker-tournament-import' ); ?>">
									<?php
									if ( ! empty( $template->blind_schedule ) ) {
										echo esc_html( $template->blind_schedule->name );
									} else {
										esc_html_e( 'None', 'poker-tournament-import' );
									}
									?>
								</td>
								<td class="column-prize" data-colname="<?php esc_attr_e( 'Prize Structure', 'poker-tournament-import' ); ?>">
									<?php
									if ( ! empty( $template->prize_structure ) ) {
										echo esc_html( $template->prize_structure->name );
									} else {
										esc_html_e( 'None', 'poker-tournament-import' );
									}
									?>
								</td>
								<td class="column-date" data-colname="<?php esc_attr_e( 'Created', 'poker-tournament-import' ); ?>">
									<?php echo esc_html( mysql2date( get_option( 'date_format' ), $template->created_at ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => __( '&laquo;', 'poker-tournament-import' ),
									'next_text' => __( '&raquo;', 'poker-tournament-import' ),
									'total'     => $total_pages,
									'current'   => $current_page,
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render row actions
	 *
	 * @since 3.0.0
	 * @param object $template Template object.
	 */
	private function render_row_actions( $template ) {
		$actions = array();

		// Edit action
		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=tdwp-tournament-manager&action=edit&template_id=' . absint( $template->id ) ) ),
			esc_html__( 'Edit', 'poker-tournament-import' )
		);

		// Clone action
		$actions['clone'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=tdwp_clone_tournament_template&template_id=' . absint( $template->id ) ),
					'tdwp_clone_template_' . $template->id
				)
			),
			esc_html__( 'Clone', 'poker-tournament-import' )
		);

		// Delete action
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=tdwp_delete_tournament_template&template_id=' . absint( $template->id ) ),
					'tdwp_delete_template_' . $template->id
				)
			),
			esc_js( __( 'Are you sure you want to delete this template?', 'poker-tournament-import' ) ),
			esc_html__( 'Delete', 'poker-tournament-import' )
		);

		echo '<div class="row-actions">';
		echo wp_kses_post( implode( ' | ', $actions ) );
		echo '</div>';
	}

	/**
	 * Render add form
	 *
	 * @since 3.0.0
	 */
	private function render_add_form() {
		$this->render_form();
	}

	/**
	 * Render edit form
	 *
	 * @since 3.0.0
	 */
	private function render_edit_form() {
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

		if ( 0 === $template_id ) {
			wp_die( esc_html__( 'Invalid template ID', 'poker-tournament-import' ) );
		}

		$template = $this->template_manager->get( $template_id, true );

		if ( ! $template ) {
			wp_die( esc_html__( 'Template not found', 'poker-tournament-import' ) );
		}

		$this->render_form( $template );
	}

	/**
	 * Render template form
	 *
	 * @since 3.0.0
	 * @param object|null $template Template object for edit, null for add.
	 */
	private function render_form( $template = null ) {
		$is_edit     = ! empty( $template );
		$template_id = $is_edit ? $template->id : 0;

		// Get blind schedules for dropdown
		global $wpdb;
		$blind_schedules = $wpdb->get_results(
			"SELECT id, name FROM {$wpdb->prefix}tdwp_blind_schedules ORDER BY name ASC"
		);

		// Get prize structures for dropdown
		$prize_structures = $wpdb->get_results(
			"SELECT id, name FROM {$wpdb->prefix}tdwp_prize_structures ORDER BY name ASC"
		);

		?>
		<div class="wrap">
			<h1>
				<?php
				echo $is_edit
					? esc_html__( 'Edit Tournament Template', 'poker-tournament-import' )
					: esc_html__( 'Add Tournament Template', 'poker-tournament-import' );
				?>
			</h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tdwp-template-form">
				<input type="hidden" name="action" value="tdwp_save_tournament_template">
				<input type="hidden" name="template_id" value="<?php echo absint( $template_id ); ?>">
				<?php wp_nonce_field( 'tdwp_save_template_' . $template_id, 'template_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Template Name -->
						<tr>
							<th scope="row">
								<label for="template_name">
									<?php esc_html_e( 'Template Name', 'poker-tournament-import' ); ?>
									<span class="required">*</span>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="template_name"
									name="name"
									value="<?php echo $is_edit ? esc_attr( $template->name ) : ''; ?>"
									class="regular-text"
									required
								>
								<p class="description">
									<?php esc_html_e( 'A descriptive name for this tournament template', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Description -->
						<tr>
							<th scope="row">
								<label for="template_description">
									<?php esc_html_e( 'Description', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<textarea
									id="template_description"
									name="description"
									rows="3"
									class="large-text"
								><?php echo $is_edit ? esc_textarea( $template->description ) : ''; ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Optional description for this template', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Buy-in Amount -->
						<tr>
							<th scope="row">
								<label for="template_buyin">
									<?php esc_html_e( 'Buy-in Amount', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_buyin"
									name="buy_in"
									value="<?php echo $is_edit ? esc_attr( $template->buy_in ) : '0'; ?>"
									step="0.01"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Tournament buy-in cost', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Starting Chips -->
						<tr>
							<th scope="row">
								<label for="template_chips">
									<?php esc_html_e( 'Starting Chips', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_chips"
									name="starting_chips"
									value="<?php echo $is_edit ? esc_attr( $template->starting_chips ) : '10000'; ?>"
									step="100"
									min="100"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Starting chip stack for each player', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Rebuy Cost -->
						<tr>
							<th scope="row">
								<label for="template_rebuy_cost">
									<?php esc_html_e( 'Rebuy Cost', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_rebuy_cost"
									name="rebuy_cost"
									value="<?php echo $is_edit ? esc_attr( $template->rebuy_cost ) : '0'; ?>"
									step="0.01"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Cost per rebuy (0 for no rebuys)', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Rebuy Chips -->
						<tr>
							<th scope="row">
								<label for="template_rebuy_chips">
									<?php esc_html_e( 'Rebuy Chips', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_rebuy_chips"
									name="rebuy_chips"
									value="<?php echo $is_edit ? esc_attr( $template->rebuy_chips ) : '0'; ?>"
									step="100"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Chips received per rebuy', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Add-on Cost -->
						<tr>
							<th scope="row">
								<label for="template_addon_cost">
									<?php esc_html_e( 'Add-on Cost', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_addon_cost"
									name="addon_cost"
									value="<?php echo $is_edit ? esc_attr( $template->addon_cost ) : '0'; ?>"
									step="0.01"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Cost per add-on (0 for no add-ons)', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Add-on Chips -->
						<tr>
							<th scope="row">
								<label for="template_addon_chips">
									<?php esc_html_e( 'Add-on Chips', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_addon_chips"
									name="addon_chips"
									value="<?php echo $is_edit ? esc_attr( $template->addon_chips ) : '0'; ?>"
									step="100"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Chips received per add-on', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Rake Percentage -->
						<tr>
							<th scope="row">
								<label for="template_rake">
									<?php esc_html_e( 'Rake Percentage', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="template_rake"
									name="rake_percentage"
									value="<?php echo $is_edit ? esc_attr( $template->rake_percentage ) : '0'; ?>"
									step="0.1"
									min="0"
									max="100"
									class="small-text"
								>
								<span>%</span>
								<p class="description">
									<?php esc_html_e( 'House rake as percentage (0-100)', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Blind Schedule -->
						<tr>
							<th scope="row">
								<label for="template_blind_schedule">
									<?php esc_html_e( 'Blind Schedule', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<select id="template_blind_schedule" name="blind_schedule_id" class="regular-text">
									<option value="0"><?php esc_html_e( 'None', 'poker-tournament-import' ); ?></option>
									<?php foreach ( $blind_schedules as $schedule ) : ?>
										<option
											value="<?php echo absint( $schedule->id ); ?>"
											<?php selected( $is_edit && absint( $template->blind_schedule_id ) === absint( $schedule->id ) ); ?>
										>
											<?php echo esc_html( $schedule->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select a blind structure for this tournament', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>

						<!-- Prize Structure -->
						<tr>
							<th scope="row">
								<label for="template_prize_structure">
									<?php esc_html_e( 'Prize Structure', 'poker-tournament-import' ); ?>
								</label>
							</th>
							<td>
								<select id="template_prize_structure" name="prize_structure_id" class="regular-text">
									<option value="0"><?php esc_html_e( 'None', 'poker-tournament-import' ); ?></option>
									<?php foreach ( $prize_structures as $structure ) : ?>
										<option
											value="<?php echo absint( $structure->id ); ?>"
											<?php selected( $is_edit && absint( $template->prize_structure_id ) === absint( $structure->id ) ); ?>
										>
											<?php echo esc_html( $structure->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select a prize distribution structure', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input
						type="submit"
						name="submit"
						id="submit"
						class="button button-primary"
						value="<?php echo $is_edit ? esc_attr__( 'Update Template', 'poker-tournament-import' ) : esc_attr__( 'Create Template', 'poker-tournament-import' ); ?>"
					>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tdwp-tournament-manager' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle template save
	 *
	 * @since 3.0.0
	 */
	public function handle_save() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'poker-tournament-import' ) );
		}

		// Get template ID
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		// Verify nonce
		check_admin_referer( 'tdwp_save_template_' . $template_id, 'template_nonce' );

		// Collect and sanitize data (class will handle sanitization)
		$data = array(
			'name'               => isset( $_POST['name'] ) ? $_POST['name'] : '',
			'description'        => isset( $_POST['description'] ) ? $_POST['description'] : '',
			'buy_in'             => isset( $_POST['buy_in'] ) ? $_POST['buy_in'] : 0,
			'rebuy_cost'         => isset( $_POST['rebuy_cost'] ) ? $_POST['rebuy_cost'] : 0,
			'rebuy_chips'        => isset( $_POST['rebuy_chips'] ) ? $_POST['rebuy_chips'] : 0,
			'addon_cost'         => isset( $_POST['addon_cost'] ) ? $_POST['addon_cost'] : 0,
			'addon_chips'        => isset( $_POST['addon_chips'] ) ? $_POST['addon_chips'] : 0,
			'starting_chips'     => isset( $_POST['starting_chips'] ) ? $_POST['starting_chips'] : 10000,
			'rake_percentage'    => isset( $_POST['rake_percentage'] ) ? $_POST['rake_percentage'] : 0,
			'blind_schedule_id'  => isset( $_POST['blind_schedule_id'] ) ? $_POST['blind_schedule_id'] : 0,
			'prize_structure_id' => isset( $_POST['prize_structure_id'] ) ? $_POST['prize_structure_id'] : 0,
		);

		// Create or update
		if ( $template_id > 0 ) {
			// Update existing
			$result = $this->template_manager->update( $template_id, $data );
			$message = 'updated';
		} else {
			// Create new
			$result = $this->template_manager->create( $data );
			$message = 'created';
		}

		// Handle result
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-tournament-manager',
					'message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle template delete
	 *
	 * @since 3.0.0
	 */
	public function handle_delete() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'poker-tournament-import' ) );
		}

		// Get template ID
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

		// Verify nonce
		check_admin_referer( 'tdwp_delete_template_' . $template_id );

		// Delete template
		$result = $this->template_manager->delete( $template_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-tournament-manager',
					'message' => 'deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle template clone
	 *
	 * @since 3.0.0
	 */
	public function handle_clone() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'poker-tournament-import' ) );
		}

		// Get template ID
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

		// Verify nonce
		check_admin_referer( 'tdwp_clone_template_' . $template_id );

		// Clone template
		$result = $this->template_manager->clone_template( $template_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-tournament-manager',
					'message' => 'cloned',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin notice
	 *
	 * @since 3.0.0
	 * @param string $message Message type.
	 */
	private function render_admin_notice( $message ) {
		$messages = array(
			'created' => __( 'Template created successfully.', 'poker-tournament-import' ),
			'updated' => __( 'Template updated successfully.', 'poker-tournament-import' ),
			'deleted' => __( 'Template deleted successfully.', 'poker-tournament-import' ),
			'cloned'  => __( 'Template cloned successfully.', 'poker-tournament-import' ),
		);

		if ( isset( $messages[ $message ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $message ] )
			);
		}
	}

	/**
	 * Format currency
	 *
	 * @since 3.0.0
	 * @param float $amount Amount to format.
	 * @return string Formatted amount
	 */
	private function format_currency( $amount ) {
		$symbol = get_option( 'tdwp_currency_symbol', '$' );
		return $symbol . number_format_i18n( $amount, 2 );
	}
}

// Initialize
new TDWP_Tournament_Templates_Page();
