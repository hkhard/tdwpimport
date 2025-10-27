<?php
/**
 * Player Management Admin Page
 *
 * Provides admin interface for managing players via WordPress posts.
 * Includes list view, add/edit form, and CSV import functionality.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player Management Page class
 *
 * @since 3.0.0
 */
class TDWP_Player_Management_Page {

	/**
	 * Player Manager instance
	 *
	 * @var TDWP_Player_Manager
	 */
	private $player_manager;

	/**
	 * Player Importer instance
	 *
	 * @var TDWP_Player_Importer
	 */
	private $player_importer;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->player_manager  = new TDWP_Player_Manager();
		$this->player_importer = new TDWP_Player_Importer();

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_tdwp_delete_player', array( $this, 'ajax_delete_player' ) );
		add_action( 'wp_ajax_tdwp_quick_edit_player', array( $this, 'ajax_quick_edit_player' ) );
		add_action( 'wp_ajax_tdwp_search_players', array( $this, 'ajax_search_players' ) );
		add_action( 'wp_ajax_tdwp_import_players', array( $this, 'ajax_import_players' ) );
	}

	/**
	 * Add menu page
	 *
	 * @since 3.0.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=tournament',
			__( 'Player Management', 'poker-tournament-import' ),
			__( 'Player Management', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-player-management',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tournament_page_tdwp-player-management' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tdwp-player-management-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/player-management-admin.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		wp_enqueue_script(
			'tdwp-player-management-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/player-management-admin.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		wp_localize_script(
			'tdwp-player-management-admin',
			'tdwpPlayerMgmt',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tdwp_player_management' ),
				'i18n'    => array(
					'confirmDelete'   => __( 'Are you sure you want to delete this player? This action cannot be undone.', 'poker-tournament-import' ),
					'errorDeleting'   => __( 'Error deleting player. Please try again.', 'poker-tournament-import' ),
					'errorSaving'     => __( 'Error saving player. Please try again.', 'poker-tournament-import' ),
					'errorImporting'  => __( 'Error importing players. Please try again.', 'poker-tournament-import' ),
					'importSuccess'   => __( 'Players imported successfully!', 'poker-tournament-import' ),
					'noFileSelected'  => __( 'Please select a file to import.', 'poker-tournament-import' ),
				),
			)
		);
	}

	/**
	 * Handle form submissions
	 *
	 * @since 3.0.0
	 */
	public function handle_form_submissions() {
		if ( ! isset( $_POST['tdwp_player_action'] ) ) {
			return;
		}

		check_admin_referer( 'tdwp_player_form', 'tdwp_player_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['tdwp_player_action'] ) );

		switch ( $action ) {
			case 'create':
				$this->handle_create_player();
				break;
			case 'update':
				$this->handle_update_player();
				break;
			case 'delete':
				$this->handle_delete_player();
				break;
		}
	}

	/**
	 * Handle create player
	 *
	 * @since 3.0.0
	 */
	private function handle_create_player() {
		$player_data = array(
			'name'   => isset( $_POST['player_name'] ) ? sanitize_text_field( wp_unslash( $_POST['player_name'] ) ) : '',
			'email'  => isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '',
			'phone'  => isset( $_POST['player_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['player_phone'] ) ) : '',
			'bio'    => isset( $_POST['player_bio'] ) ? wp_kses_post( wp_unslash( $_POST['player_bio'] ) ) : '',
			'status' => isset( $_POST['player_status'] ) ? sanitize_text_field( wp_unslash( $_POST['player_status'] ) ) : 'publish',
		);

		$result = $this->player_manager->create( $player_data );

		if ( is_wp_error( $result ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-player-management',
						'tab'     => 'add-edit',
						'error'   => urlencode( $result->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=tournament' )
				)
			);
			exit;
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-player-management',
					'tab'     => 'list',
					'message' => 'created',
				),
				admin_url( 'edit.php?post_type=tournament' )
			)
		);
		exit;
	}

	/**
	 * Handle update player
	 *
	 * @since 3.0.0
	 */
	private function handle_update_player() {
		$player_id = isset( $_POST['player_id'] ) ? absint( $_POST['player_id'] ) : 0;

		if ( ! $player_id ) {
			return;
		}

		$player_data = array(
			'name'   => isset( $_POST['player_name'] ) ? sanitize_text_field( wp_unslash( $_POST['player_name'] ) ) : '',
			'email'  => isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '',
			'phone'  => isset( $_POST['player_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['player_phone'] ) ) : '',
			'bio'    => isset( $_POST['player_bio'] ) ? wp_kses_post( wp_unslash( $_POST['player_bio'] ) ) : '',
			'status' => isset( $_POST['player_status'] ) ? sanitize_text_field( wp_unslash( $_POST['player_status'] ) ) : 'publish',
		);

		$result = $this->player_manager->update( $player_id, $player_data );

		if ( is_wp_error( $result ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'      => 'tdwp-player-management',
						'tab'       => 'add-edit',
						'player_id' => $player_id,
						'error'     => urlencode( $result->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=tournament' )
				)
			);
			exit;
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-player-management',
					'tab'     => 'list',
					'message' => 'updated',
				),
				admin_url( 'edit.php?post_type=tournament' )
			)
		);
		exit;
	}

	/**
	 * Handle delete player
	 *
	 * @since 3.0.0
	 */
	private function handle_delete_player() {
		$player_id = isset( $_POST['player_id'] ) ? absint( $_POST['player_id'] ) : 0;

		if ( ! $player_id ) {
			return;
		}

		$result = $this->player_manager->delete( $player_id );

		if ( is_wp_error( $result ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'  => 'tdwp-player-management',
						'tab'   => 'list',
						'error' => urlencode( $result->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=tournament' )
				)
			);
			exit;
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-player-management',
					'tab'     => 'list',
					'message' => 'deleted',
				),
				admin_url( 'edit.php?post_type=tournament' )
			)
		);
		exit;
	}

	/**
	 * Render admin page
	 *
	 * @since 3.0.0
	 */
	public function render_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'list';

		?>
		<div class="wrap tdwp-player-management">
			<h1><?php esc_html_e( 'Player Management', 'poker-tournament-import' ); ?></h1>

			<?php $this->render_messages(); ?>

			<nav class="nav-tab-wrapper tdwp-player-tabs">
				<a href="<?php echo esc_url( $this->get_tab_url( 'list' ) ); ?>"
				   class="nav-tab <?php echo 'list' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Players List', 'poker-tournament-import' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'add-edit' ) ); ?>"
				   class="nav-tab <?php echo 'add-edit' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Add/Edit Player', 'poker-tournament-import' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'import' ) ); ?>"
				   class="nav-tab <?php echo 'import' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Import Players', 'poker-tournament-import' ); ?>
				</a>
			</nav>

			<div class="tdwp-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'add-edit':
						$this->render_add_edit_tab();
						break;
					case 'import':
						$this->render_import_tab();
						break;
					case 'list':
					default:
						$this->render_list_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render players list tab
	 *
	 * @since 3.0.0
	 */
	private function render_list_tab() {
		$page      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'title';
		$order     = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'ASC';

		$results = $this->player_manager->get_all(
			array(
				'page'     => $page,
				'per_page' => 20,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		?>
		<div class="tdwp-players-list">
			<div class="tdwp-list-header">
				<div class="tdwp-list-actions">
					<a href="<?php echo esc_url( $this->get_tab_url( 'add-edit' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Add New Player', 'poker-tournament-import' ); ?>
					</a>
				</div>

				<div class="tdwp-list-search">
					<form method="get" action="">
						<input type="hidden" name="post_type" value="tournament">
						<input type="hidden" name="page" value="tdwp-player-management">
						<input type="hidden" name="tab" value="list">
						<input type="text"
							   name="s"
							   value="<?php echo esc_attr( $search ); ?>"
							   placeholder="<?php esc_attr_e( 'Search players...', 'poker-tournament-import' ); ?>">
						<button type="submit" class="button">
							<?php esc_html_e( 'Search', 'poker-tournament-import' ); ?>
						</button>
					</form>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-name">
							<a href="<?php echo esc_url( $this->get_sorted_url( 'title', $order ) ); ?>">
								<?php esc_html_e( 'Name', 'poker-tournament-import' ); ?>
								<?php $this->render_sort_indicator( 'title', $orderby, $order ); ?>
							</a>
						</th>
						<th class="column-email"><?php esc_html_e( 'Email', 'poker-tournament-import' ); ?></th>
						<th class="column-uuid"><?php esc_html_e( 'UUID', 'poker-tournament-import' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></th>
						<th class="column-date">
							<a href="<?php echo esc_url( $this->get_sorted_url( 'date', $order ) ); ?>">
								<?php esc_html_e( 'Date', 'poker-tournament-import' ); ?>
								<?php $this->render_sort_indicator( 'date', $orderby, $order ); ?>
							</a>
						</th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'poker-tournament-import' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $results['players'] ) ) : ?>
						<?php foreach ( $results['players'] as $player ) : ?>
							<tr data-player-id="<?php echo esc_attr( $player['id'] ); ?>">
								<td class="column-name">
									<strong><?php echo esc_html( $player['name'] ); ?></strong>
								</td>
								<td class="column-email">
									<?php echo esc_html( $player['email'] ); ?>
								</td>
								<td class="column-uuid">
									<code><?php echo esc_html( $player['uuid'] ); ?></code>
								</td>
								<td class="column-status">
									<span class="status-badge status-<?php echo esc_attr( $player['status'] ); ?>">
										<?php echo esc_html( ucfirst( $player['status'] ) ); ?>
									</span>
								</td>
								<td class="column-date">
									<?php echo esc_html( mysql2date( get_option( 'date_format' ), $player['date'] ) ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( $this->get_edit_url( $player['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'poker-tournament-import' ); ?>
									</a>
									<a href="<?php echo esc_url( get_permalink( $player['id'] ) ); ?>" class="button button-small" target="_blank">
										<?php esc_html_e( 'View', 'poker-tournament-import' ); ?>
									</a>
									<button type="button" class="button button-small delete-player" data-player-id="<?php echo esc_attr( $player['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6" class="no-items">
								<?php esc_html_e( 'No players found.', 'poker-tournament-import' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $results['total_pages'] > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $page,
									'total'     => $results['total_pages'],
									'prev_text' => __( '&laquo; Previous', 'poker-tournament-import' ),
									'next_text' => __( 'Next &raquo;', 'poker-tournament-import' ),
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Delete Form (hidden) -->
		<form id="delete-player-form" method="post" style="display:none;">
			<?php wp_nonce_field( 'tdwp_player_form', 'tdwp_player_nonce' ); ?>
			<input type="hidden" name="tdwp_player_action" value="delete">
			<input type="hidden" name="player_id" id="delete-player-id">
		</form>
		<?php
	}

	/**
	 * Render add/edit tab
	 *
	 * @since 3.0.0
	 */
	private function render_add_edit_tab() {
		$player_id = isset( $_GET['player_id'] ) ? absint( $_GET['player_id'] ) : 0;
		$player    = null;

		if ( $player_id ) {
			$player = $this->player_manager->get( $player_id, true, true );
			if ( is_wp_error( $player ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $player->get_error_message() ) . '</p></div>';
				return;
			}
		}

		$is_edit = ! empty( $player );
		?>
		<div class="tdwp-player-form-container">
			<h2>
				<?php
				echo $is_edit ?
					esc_html__( 'Edit Player', 'poker-tournament-import' ) :
					esc_html__( 'Add New Player', 'poker-tournament-import' );
				?>
			</h2>

			<?php if ( $is_edit && ! empty( $player['stats'] ) ) : ?>
				<div class="player-stats-summary">
					<h3><?php esc_html_e( 'Player Statistics', 'poker-tournament-import' ); ?></h3>
					<div class="stats-grid">
						<div class="stat-box">
							<div class="stat-value"><?php echo esc_html( number_format( $player['stats']['tournaments'] ) ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Tournaments', 'poker-tournament-import' ); ?></div>
						</div>
						<div class="stat-box">
							<div class="stat-value"><?php echo esc_html( number_format( $player['stats']['wins'] ) ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Wins', 'poker-tournament-import' ); ?></div>
						</div>
						<div class="stat-box">
							<div class="stat-value"><?php echo esc_html( number_format( $player['stats']['final_tables'] ) ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Final Tables', 'poker-tournament-import' ); ?></div>
						</div>
						<div class="stat-box">
							<div class="stat-value">$<?php echo esc_html( number_format( $player['stats']['total_winnings'], 2 ) ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Total Winnings', 'poker-tournament-import' ); ?></div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" action="" class="tdwp-player-form">
				<?php wp_nonce_field( 'tdwp_player_form', 'tdwp_player_nonce' ); ?>
				<input type="hidden" name="tdwp_player_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="player_id" value="<?php echo esc_attr( $player_id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="player_name">
								<?php esc_html_e( 'Name', 'poker-tournament-import' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="text"
								   name="player_name"
								   id="player_name"
								   class="regular-text"
								   value="<?php echo $is_edit ? esc_attr( $player['name'] ) : ''; ?>"
								   required>
							<p class="description">
								<?php esc_html_e( 'Full name of the player.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="player_email">
								<?php esc_html_e( 'Email', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<input type="email"
								   name="player_email"
								   id="player_email"
								   class="regular-text"
								   value="<?php echo $is_edit && ! empty( $player['meta']['email'] ) ? esc_attr( $player['meta']['email'] ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Email address for contact and notifications.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="player_phone">
								<?php esc_html_e( 'Phone', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<input type="tel"
								   name="player_phone"
								   id="player_phone"
								   class="regular-text"
								   value="<?php echo $is_edit && ! empty( $player['meta']['phone'] ) ? esc_attr( $player['meta']['phone'] ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Contact phone number.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<?php if ( $is_edit && ! empty( $player['meta']['uuid'] ) ) : ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'UUID', 'poker-tournament-import' ); ?></label>
							</th>
							<td>
								<code><?php echo esc_html( $player['meta']['uuid'] ); ?></code>
								<p class="description">
									<?php esc_html_e( 'Unique identifier (cannot be changed).', 'poker-tournament-import' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row">
							<label for="player_bio">
								<?php esc_html_e( 'Biography', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<textarea name="player_bio"
									  id="player_bio"
									  class="large-text"
									  rows="5"><?php echo $is_edit ? esc_textarea( $player['bio'] ) : ''; ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Player biography or notes.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="player_status">
								<?php esc_html_e( 'Status', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<select name="player_status" id="player_status">
								<option value="publish" <?php echo ( ! $is_edit || 'publish' === $player['status'] ) ? 'selected' : ''; ?>>
									<?php esc_html_e( 'Active', 'poker-tournament-import' ); ?>
								</option>
								<option value="draft" <?php echo ( $is_edit && 'draft' === $player['status'] ) ? 'selected' : ''; ?>>
									<?php esc_html_e( 'Inactive', 'poker-tournament-import' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Active players appear in public listings.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<?php if ( $is_edit && ! empty( $player['meta']['registration_date'] ) ) : ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Registration Date', 'poker-tournament-import' ); ?></label>
							</th>
							<td>
								<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $player['meta']['registration_date'] ) ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						<?php echo $is_edit ? esc_html__( 'Update Player', 'poker-tournament-import' ) : esc_html__( 'Create Player', 'poker-tournament-import' ); ?>
					</button>
					<a href="<?php echo esc_url( $this->get_tab_url( 'list' ) ); ?>" class="button button-large">
						<?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render import tab
	 *
	 * @since 3.0.0
	 */
	private function render_import_tab() {
		?>
		<div class="tdwp-import-container">
			<h2><?php esc_html_e( 'Import Players from CSV/Excel', 'poker-tournament-import' ); ?></h2>

			<div class="import-instructions">
				<h3><?php esc_html_e( 'Instructions', 'poker-tournament-import' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Prepare a CSV or Excel file with the following columns:', 'poker-tournament-import' ); ?></li>
					<ul>
						<li><strong><?php esc_html_e( 'name', 'poker-tournament-import' ); ?></strong> - <?php esc_html_e( 'Player name (required)', 'poker-tournament-import' ); ?></li>
						<li><strong><?php esc_html_e( 'email', 'poker-tournament-import' ); ?></strong> - <?php esc_html_e( 'Email address (optional)', 'poker-tournament-import' ); ?></li>
						<li><strong><?php esc_html_e( 'phone', 'poker-tournament-import' ); ?></strong> - <?php esc_html_e( 'Phone number (optional)', 'poker-tournament-import' ); ?></li>
						<li><strong><?php esc_html_e( 'uuid', 'poker-tournament-import' ); ?></strong> - <?php esc_html_e( 'Unique ID (optional, auto-generated if blank)', 'poker-tournament-import' ); ?></li>
					</ul>
					<li><?php esc_html_e( 'Select your file and configure import options below.', 'poker-tournament-import' ); ?></li>
					<li><?php esc_html_e( 'Click "Preview Import" to validate data before importing.', 'poker-tournament-import' ); ?></li>
				</ol>
			</div>

			<form id="player-import-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'tdwp_player_import', 'import_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="import_file">
								<?php esc_html_e( 'Select File', 'poker-tournament-import' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="file"
								   name="import_file"
								   id="import_file"
								   accept=".csv,.xls,.xlsx"
								   required>
							<p class="description">
								<?php esc_html_e( 'Accepted formats: CSV, XLS, XLSX', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="duplicate_handling">
								<?php esc_html_e( 'Duplicate Handling', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<select name="duplicate_handling" id="duplicate_handling">
								<option value="skip"><?php esc_html_e( 'Skip duplicates', 'poker-tournament-import' ); ?></option>
								<option value="update"><?php esc_html_e( 'Update existing players', 'poker-tournament-import' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'How to handle players with matching email or UUID.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="import_status">
								<?php esc_html_e( 'Import As', 'poker-tournament-import' ); ?>
							</label>
						</th>
						<td>
							<select name="import_status" id="import_status">
								<option value="publish"><?php esc_html_e( 'Active (Publish)', 'poker-tournament-import' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Inactive (Draft)', 'poker-tournament-import' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Status for imported players.', 'poker-tournament-import' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" id="preview-import-button" class="button button-secondary button-large">
						<?php esc_html_e( 'Preview Import', 'poker-tournament-import' ); ?>
					</button>
					<button type="button" id="import-button" class="button button-primary button-large" disabled>
						<?php esc_html_e( 'Import Players', 'poker-tournament-import' ); ?>
					</button>
				</p>
			</form>

			<div id="import-preview" class="import-preview" style="display:none;">
				<h3><?php esc_html_e( 'Import Preview', 'poker-tournament-import' ); ?></h3>
				<div id="import-preview-content"></div>
			</div>

			<div id="import-results" class="import-results" style="display:none;">
				<h3><?php esc_html_e( 'Import Results', 'poker-tournament-import' ); ?></h3>
				<div id="import-results-content"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Delete player
	 *
	 * @since 3.0.0
	 */
	public function ajax_delete_player() {
		check_ajax_referer( 'tdwp_player_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'poker-tournament-import' ) ) );
		}

		$player_id = isset( $_POST['player_id'] ) ? absint( $_POST['player_id'] ) : 0;

		if ( ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid player ID.', 'poker-tournament-import' ) ) );
		}

		$result = $this->player_manager->delete( $player_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Player deleted successfully.', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Quick edit player
	 *
	 * @since 3.0.0
	 */
	public function ajax_quick_edit_player() {
		check_ajax_referer( 'tdwp_player_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'poker-tournament-import' ) ) );
		}

		$player_id = isset( $_POST['player_id'] ) ? absint( $_POST['player_id'] ) : 0;

		if ( ! $player_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid player ID.', 'poker-tournament-import' ) ) );
		}

		$player_data = array();

		if ( isset( $_POST['name'] ) ) {
			$player_data['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		}

		if ( isset( $_POST['email'] ) ) {
			$player_data['email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
		}

		if ( isset( $_POST['status'] ) ) {
			$player_data['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
		}

		$result = $this->player_manager->update( $player_id, $player_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Player updated successfully.', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX: Search players
	 *
	 * @since 3.0.0
	 */
	public function ajax_search_players() {
		check_ajax_referer( 'tdwp_player_management', 'nonce' );

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json_success( array( 'players' => array() ) );
		}

		$players = $this->player_manager->search( $term, 10 );

		wp_send_json_success( array( 'players' => $players ) );
	}

	/**
	 * AJAX: Import players
	 *
	 * @since 3.0.0
	 */
	public function ajax_import_players() {
		check_ajax_referer( 'tdwp_player_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'poker-tournament-import' ) ) );
		}

		$action = isset( $_POST['import_action'] ) ? sanitize_text_field( wp_unslash( $_POST['import_action'] ) ) : '';

		if ( 'preview' === $action ) {
			$this->handle_import_preview();
		} elseif ( 'import' === $action ) {
			$this->handle_import();
		}
	}

	/**
	 * Handle import preview
	 *
	 * @since 3.0.0
	 */
	private function handle_import_preview() {
		if ( empty( $_FILES['import_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'poker-tournament-import' ) ) );
		}

		$file = $_FILES['import_file'];

		// Validate file upload.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'File upload error.', 'poker-tournament-import' ) ) );
		}

		$result = $this->player_importer->parse_file( $file['tmp_name'], $file['name'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle import execution
	 *
	 * @since 3.0.0
	 */
	private function handle_import() {
		$players            = isset( $_POST['players'] ) ? json_decode( wp_unslash( $_POST['players'] ), true ) : array();
		$duplicate_handling = isset( $_POST['duplicate_handling'] ) ? sanitize_text_field( wp_unslash( $_POST['duplicate_handling'] ) ) : 'skip';
		$status             = isset( $_POST['import_status'] ) ? sanitize_text_field( wp_unslash( $_POST['import_status'] ) ) : 'publish';

		if ( empty( $players ) ) {
			wp_send_json_error( array( 'message' => __( 'No player data provided.', 'poker-tournament-import' ) ) );
		}

		$result = $this->player_importer->import_players( $players, $duplicate_handling, $status );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Render admin messages
	 *
	 * @since 3.0.0
	 */
	private function render_messages() {
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			$messages = array(
				'created' => __( 'Player created successfully.', 'poker-tournament-import' ),
				'updated' => __( 'Player updated successfully.', 'poker-tournament-import' ),
				'deleted' => __( 'Player deleted successfully.', 'poker-tournament-import' ),
			);

			if ( isset( $messages[ $message ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>';
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * Get tab URL
	 *
	 * @since 3.0.0
	 *
	 * @param string $tab Tab slug.
	 * @return string Tab URL.
	 */
	private function get_tab_url( $tab ) {
		return add_query_arg(
			array(
				'post_type' => 'tournament',
				'page'      => 'tdwp-player-management',
				'tab'       => $tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Get edit URL
	 *
	 * @since 3.0.0
	 *
	 * @param int $player_id Player ID.
	 * @return string Edit URL.
	 */
	private function get_edit_url( $player_id ) {
		return add_query_arg(
			array(
				'player_id' => $player_id,
			),
			$this->get_tab_url( 'add-edit' )
		);
	}

	/**
	 * Get sorted URL
	 *
	 * @since 3.0.0
	 *
	 * @param string $orderby Order by field.
	 * @param string $order   Current order direction.
	 * @return string Sorted URL.
	 */
	private function get_sorted_url( $orderby, $order ) {
		$new_order = ( 'ASC' === $order ) ? 'DESC' : 'ASC';

		return add_query_arg(
			array(
				'orderby' => $orderby,
				'order'   => $new_order,
			),
			$this->get_tab_url( 'list' )
		);
	}

	/**
	 * Render sort indicator
	 *
	 * @since 3.0.0
	 *
	 * @param string $column  Column name.
	 * @param string $orderby Current orderby value.
	 * @param string $order   Current order direction.
	 */
	private function render_sort_indicator( $column, $orderby, $order ) {
		if ( $column !== $orderby ) {
			return;
		}

		$arrow = 'ASC' === $order ? '&#9650;' : '&#9660;';
		echo '<span class="sort-indicator">' . wp_kses_post( $arrow ) . '</span>';
	}
}

// Initialize.
new TDWP_Player_Management_Page();
