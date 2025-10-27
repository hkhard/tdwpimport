<?php
/**
 * Blind Builder Admin Page
 *
 * Handles the blind schedule builder interface in WordPress admin.
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Blind Builder Page Class
 *
 * Provides admin interface for creating and managing blind schedules.
 *
 * @since 3.0.0
 */
class TDWP_Blind_Builder_Page {

	/**
	 * Blind Schedule Manager instance
	 *
	 * @since 3.0.0
	 * @var TDWP_Blind_Schedule
	 */
	private $schedule_manager;

	/**
	 * Blind Level Manager instance
	 *
	 * @since 3.0.0
	 * @var TDWP_Blind_Level
	 */
	private $level_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->schedule_manager = new TDWP_Blind_Schedule();
		$this->level_manager    = new TDWP_Blind_Level();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_tdwp_save_blind_levels', array( $this, 'ajax_save_levels' ) );
		add_action( 'wp_ajax_tdwp_get_blind_levels', array( $this, 'ajax_get_levels' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @since 3.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tdwp-tournament-manager',
			__( 'Blind Builder', 'poker-tournament-import' ),
			__( 'Blind Builder', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-blind-builder',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue CSS and JavaScript
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tournament-manager_page_tdwp-blind-builder' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tdwp-blind-builder-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/blind-builder-admin.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'tdwp-blind-builder-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/blind-builder-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		wp_localize_script(
			'tdwp-blind-builder-admin',
			'tdwpBlindBuilder',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tdwp_blind_builder' ),
				'i18n'        => array(
					'confirmDelete'      => __( 'Are you sure you want to delete this schedule? This action cannot be undone.', 'poker-tournament-import' ),
					'confirmDeleteLevel' => __( 'Are you sure you want to delete this level?', 'poker-tournament-import' ),
					'errorSaving'        => __( 'Error saving levels. Please try again.', 'poker-tournament-import' ),
					'errorLoading'       => __( 'Error loading levels. Please refresh the page.', 'poker-tournament-import' ),
					'levelsSaved'        => __( 'Blind levels saved successfully.', 'poker-tournament-import' ),
				),
			)
		);
	}

	/**
	 * Handle form actions
	 *
	 * @since 3.0.0
	 */
	public function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'tdwp-blind-builder' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		// phpcs:enable

		if ( 'delete' === $action ) {
			$this->handle_delete();
		} elseif ( 'clone' === $action ) {
			$this->handle_clone();
		} elseif ( 'save' === $action ) {
			$this->handle_save();
		}
	}

	/**
	 * Handle schedule save
	 *
	 * @since 3.0.0
	 */
	private function handle_save() {
		// Verify nonce.
		if ( ! isset( $_POST['schedule_nonce'] ) ) {
			return;
		}

		$schedule_id = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;
		check_admin_referer( 'tdwp_save_schedule_' . $schedule_id, 'schedule_nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Collect schedule data.
		$data = array(
			'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description'        => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'level_duration'     => isset( $_POST['level_duration'] ) ? absint( $_POST['level_duration'] ) : 15,
			'break_frequency'    => isset( $_POST['break_frequency'] ) ? absint( $_POST['break_frequency'] ) : 0,
			'break_duration'     => isset( $_POST['break_duration'] ) ? absint( $_POST['break_duration'] ) : 0,
			'is_default_turbo'   => isset( $_POST['is_default_turbo'] ) ? 1 : 0,
			'is_default_regular' => isset( $_POST['is_default_regular'] ) ? 1 : 0,
			'is_default_deep'    => isset( $_POST['is_default_deep'] ) ? 1 : 0,
		);

		// Create or update schedule.
		if ( $schedule_id > 0 ) {
			$result = $this->schedule_manager->update( $schedule_id, $data );
			$message = 'updated';
		} else {
			$result = $this->schedule_manager->create( $data );
			$schedule_id = $result;
			$message = 'created';
		}

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-blind-builder',
						'message' => 'error',
						'details' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Redirect to edit page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'tdwp-blind-builder',
					'action'      => 'edit',
					'schedule_id' => $schedule_id,
					'message'     => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle schedule delete
	 *
	 * @since 3.0.0
	 */
	private function handle_delete() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['schedule_id'] ) ) {
			return;
		}

		$schedule_id = absint( $_GET['schedule_id'] );
		// phpcs:enable

		// Verify nonce.
		check_admin_referer( 'tdwp_delete_schedule_' . $schedule_id );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Delete schedule.
		$result = $this->schedule_manager->delete( $schedule_id );

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-blind-builder',
						'message' => 'error',
						'details' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Redirect to list page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'tdwp-blind-builder',
					'message' => 'deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle schedule clone
	 *
	 * @since 3.0.0
	 */
	private function handle_clone() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['schedule_id'] ) ) {
			return;
		}

		$schedule_id = absint( $_GET['schedule_id'] );
		// phpcs:enable

		// Verify nonce.
		check_admin_referer( 'tdwp_clone_schedule_' . $schedule_id );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Clone schedule.
		$result = $this->schedule_manager->clone_schedule( $schedule_id );

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-blind-builder',
						'message' => 'error',
						'details' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Redirect to edit cloned schedule.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'tdwp-blind-builder',
					'action'      => 'edit',
					'schedule_id' => $result,
					'message'     => 'cloned',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX handler for saving blind levels
	 *
	 * @since 3.0.0
	 */
	public function ajax_save_levels() {
		// Verify nonce.
		check_ajax_referer( 'tdwp_blind_builder', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'poker-tournament-import' ) ) );
		}

		// Get schedule ID.
		$schedule_id = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;

		if ( 0 === $schedule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid schedule ID.', 'poker-tournament-import' ) ) );
		}

		// Get levels data.
		$levels = isset( $_POST['levels'] ) ? json_decode( wp_unslash( $_POST['levels'] ), true ) : array();

		if ( ! is_array( $levels ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid levels data.', 'poker-tournament-import' ) ) );
		}

		// Delete existing levels.
		$this->level_manager->delete_by_schedule( $schedule_id );

		// Create new levels.
		$order = 1;
		foreach ( $levels as $level ) {
			$level_data = array(
				'schedule_id'  => $schedule_id,
				'level_order'  => $order,
				'small_blind'  => isset( $level['small_blind'] ) ? absint( $level['small_blind'] ) : 0,
				'big_blind'    => isset( $level['big_blind'] ) ? absint( $level['big_blind'] ) : 0,
				'ante'         => isset( $level['ante'] ) ? absint( $level['ante'] ) : 0,
				'is_break'     => isset( $level['is_break'] ) ? absint( $level['is_break'] ) : 0,
				'break_length' => isset( $level['break_length'] ) ? absint( $level['break_length'] ) : 0,
			);

			$result = $this->level_manager->create( $level_data );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$order++;
		}

		wp_send_json_success( array( 'message' => __( 'Levels saved successfully.', 'poker-tournament-import' ) ) );
	}

	/**
	 * AJAX handler for getting blind levels
	 *
	 * @since 3.0.0
	 */
	public function ajax_get_levels() {
		// Verify nonce.
		check_ajax_referer( 'tdwp_blind_builder', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'poker-tournament-import' ) ) );
		}

		// Get schedule ID.
		$schedule_id = isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0;

		if ( 0 === $schedule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid schedule ID.', 'poker-tournament-import' ) ) );
		}

		// Get levels.
		$levels = $this->schedule_manager->get_levels( $schedule_id );

		wp_send_json_success( array( 'levels' => $levels ) );
	}

	/**
	 * Render main page
	 *
	 * @since 3.0.0
	 */
	public function render_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action      = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$schedule_id = isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0;
		$message     = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		// phpcs:enable

		?>
		<div class="wrap tdwp-blind-builder">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Blind Builder', 'poker-tournament-import' ); ?></h1>

			<?php if ( 'list' === $action ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-blind-builder', 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Schedule', 'poker-tournament-import' ); ?>
				</a>
			<?php elseif ( 'edit' === $action && $schedule_id > 0 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-blind-builder' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Back to Schedules', 'poker-tournament-import' ); ?>
				</a>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php $this->display_messages( $message ); ?>

			<?php
			if ( 'new' === $action || 'edit' === $action ) {
				$this->render_form( $schedule_id );
			} else {
				$this->render_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display admin messages
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Message type.
	 */
	private function display_messages( $message ) {
		if ( empty( $message ) ) {
			return;
		}

		$messages = array(
			'created' => __( 'Schedule created successfully.', 'poker-tournament-import' ),
			'updated' => __( 'Schedule updated successfully.', 'poker-tournament-import' ),
			'deleted' => __( 'Schedule deleted successfully.', 'poker-tournament-import' ),
			'cloned'  => __( 'Schedule cloned successfully.', 'poker-tournament-import' ),
		);

		if ( 'error' === $message ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$details = isset( $_GET['details'] ) ? sanitize_text_field( wp_unslash( $_GET['details'] ) ) : '';
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $details ); ?></p>
			</div>
			<?php
		} elseif ( isset( $messages[ $message ] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $messages[ $message ] ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render schedules list
	 *
	 * @since 3.0.0
	 */
	private function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$per_page    = 20;

		$args = array(
			'search'      => $search,
			'page'        => $paged,
			'per_page'    => $per_page,
			'with_levels' => false,
		);

		$schedules = $this->schedule_manager->get_all( $args );
		$total     = $this->schedule_manager->get_total( array( 'search' => $search ) );

		?>
		<form method="get">
			<input type="hidden" name="page" value="tdwp-blind-builder">
			<?php
			wp_nonce_field( 'tdwp_blind_builder_search', 'search_nonce' );
			?>
			<p class="search-box">
				<label class="screen-reader-text" for="schedule-search-input"><?php esc_html_e( 'Search Schedules:', 'poker-tournament-import' ); ?></label>
				<input type="search" id="schedule-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
				<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Schedules', 'poker-tournament-import' ); ?>">
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-name"><?php esc_html_e( 'Name', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-duration"><?php esc_html_e( 'Level Duration', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-breaks"><?php esc_html_e( 'Breaks', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-levels"><?php esc_html_e( 'Levels', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-date"><?php esc_html_e( 'Created', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $schedules ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No schedules found.', 'poker-tournament-import' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $schedules as $schedule ) : ?>
						<?php
						$levels = $this->schedule_manager->get_levels( $schedule->id );
						$edit_url = add_query_arg(
							array(
								'page'        => 'tdwp-blind-builder',
								'action'      => 'edit',
								'schedule_id' => $schedule->id,
							),
							admin_url( 'admin.php' )
						);
						$delete_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'        => 'tdwp-blind-builder',
									'action'      => 'delete',
									'schedule_id' => $schedule->id,
								),
								admin_url( 'admin.php' )
							),
							'tdwp_delete_schedule_' . $schedule->id
						);
						$clone_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'        => 'tdwp-blind-builder',
									'action'      => 'clone',
									'schedule_id' => $schedule->id,
								),
								admin_url( 'admin.php' )
							),
							'tdwp_clone_schedule_' . $schedule->id
						);
						?>
						<tr>
							<td class="column-name">
								<strong>
									<a href="<?php echo esc_url( $edit_url ); ?>">
										<?php echo esc_html( $schedule->name ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'poker-tournament-import' ); ?></a> |
									</span>
									<span class="clone">
										<a href="<?php echo esc_url( $clone_url ); ?>"><?php esc_html_e( 'Clone', 'poker-tournament-import' ); ?></a> |
									</span>
									<span class="delete">
										<a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></a>
									</span>
								</div>
							</td>
							<td class="column-duration">
								<?php
								/* translators: %d: level duration in minutes */
								echo esc_html( sprintf( __( '%d minutes', 'poker-tournament-import' ), $schedule->level_duration ) );
								?>
							</td>
							<td class="column-breaks">
								<?php if ( $schedule->break_frequency > 0 ) : ?>
									<?php
									/* translators: 1: break frequency, 2: break duration */
									echo esc_html( sprintf( __( 'Every %1$d levels (%2$d min)', 'poker-tournament-import' ), $schedule->break_frequency, $schedule->break_duration ) );
									?>
								<?php else : ?>
									<?php esc_html_e( 'None', 'poker-tournament-import' ); ?>
								<?php endif; ?>
							</td>
							<td class="column-levels">
								<?php echo absint( count( $levels ) ); ?>
							</td>
							<td class="column-date">
								<?php echo esc_html( mysql2date( get_option( 'date_format' ), $schedule->created_at ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		// Pagination.
		$total_pages = ceil( $total / $per_page );
		if ( $total_pages > 1 ) {
			?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => __( '&laquo; Previous', 'poker-tournament-import' ),
								'next_text' => __( 'Next &raquo;', 'poker-tournament-import' ),
							)
						),
						array(
							'a'    => array(
								'class' => array(),
								'href'  => array(),
							),
							'span' => array(
								'class'       => array(),
								'aria-hidden' => array(),
							),
						)
					);
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render schedule form
	 *
	 * @since 3.0.0
	 *
	 * @param int $schedule_id Schedule ID (0 for new).
	 */
	private function render_form( $schedule_id ) {
		$schedule = null;
		$levels   = array();

		if ( $schedule_id > 0 ) {
			$schedule = $this->schedule_manager->get( $schedule_id, true );

			if ( null === $schedule ) {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Schedule not found.', 'poker-tournament-import' ); ?></p>
				</div>
				<?php
				return;
			}

			$levels = isset( $schedule->levels ) ? $schedule->levels : array();
		}

		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-blind-builder', 'action' => 'save' ), admin_url( 'admin.php' ) ) ); ?>" class="tdwp-schedule-form">
			<?php wp_nonce_field( 'tdwp_save_schedule_' . $schedule_id, 'schedule_nonce' ); ?>
			<input type="hidden" name="schedule_id" value="<?php echo esc_attr( $schedule_id ); ?>">

			<h2><?php echo $schedule_id > 0 ? esc_html__( 'Edit Schedule', 'poker-tournament-import' ) : esc_html__( 'New Schedule', 'poker-tournament-import' ); ?></h2>

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="schedule-name"><?php esc_html_e( 'Schedule Name', 'poker-tournament-import' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="name" id="schedule-name" class="regular-text" value="<?php echo $schedule ? esc_attr( $schedule->name ) : ''; ?>" required>
							<p class="description"><?php esc_html_e( 'Enter a descriptive name for this blind schedule.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="schedule-description"><?php esc_html_e( 'Description', 'poker-tournament-import' ); ?></label>
						</th>
						<td>
							<textarea name="description" id="schedule-description" rows="3" class="large-text"><?php echo $schedule ? esc_textarea( $schedule->description ) : ''; ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of this blind schedule.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="level-duration"><?php esc_html_e( 'Level Duration', 'poker-tournament-import' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="number" name="level_duration" id="level-duration" class="small-text" value="<?php echo $schedule ? esc_attr( $schedule->level_duration ) : '15'; ?>" min="1" max="120" required>
							<span><?php esc_html_e( 'minutes', 'poker-tournament-import' ); ?></span>
							<p class="description"><?php esc_html_e( 'Duration of each blind level in minutes.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="break-frequency"><?php esc_html_e( 'Break Frequency', 'poker-tournament-import' ); ?></label>
						</th>
						<td>
							<input type="number" name="break_frequency" id="break-frequency" class="small-text" value="<?php echo $schedule ? esc_attr( $schedule->break_frequency ) : '0'; ?>" min="0" max="20">
							<span><?php esc_html_e( 'levels (0 for no auto-breaks)', 'poker-tournament-import' ); ?></span>
							<p class="description"><?php esc_html_e( 'Automatically insert breaks every X levels.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="break-duration"><?php esc_html_e( 'Break Duration', 'poker-tournament-import' ); ?></label>
						</th>
						<td>
							<input type="number" name="break_duration" id="break-duration" class="small-text" value="<?php echo $schedule ? esc_attr( $schedule->break_duration ) : '0'; ?>" min="0" max="60">
							<span><?php esc_html_e( 'minutes', 'poker-tournament-import' ); ?></span>
							<p class="description"><?php esc_html_e( 'Duration of automatic breaks.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Default Templates', 'poker-tournament-import' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="is_default_turbo" value="1" <?php checked( $schedule && $schedule->is_default_turbo, 1 ); ?>>
									<?php esc_html_e( 'Turbo Default', 'poker-tournament-import' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="is_default_regular" value="1" <?php checked( $schedule && $schedule->is_default_regular, 1 ); ?>>
									<?php esc_html_e( 'Regular Default', 'poker-tournament-import' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="is_default_deep" value="1" <?php checked( $schedule && $schedule->is_default_deep, 1 ); ?>>
									<?php esc_html_e( 'Deep Stack Default', 'poker-tournament-import' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Mark this schedule as default for template suggestions.', 'poker-tournament-import' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Schedule', 'poker-tournament-import' ); ?>">
			</p>
		</form>

		<?php if ( $schedule_id > 0 ) : ?>
			<hr>
			<h2><?php esc_html_e( 'Blind Levels', 'poker-tournament-import' ); ?></h2>
			<?php $this->render_level_builder( $schedule_id, $levels ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render level builder
	 *
	 * @since 3.0.0
	 *
	 * @param int   $schedule_id Schedule ID.
	 * @param array $levels      Existing levels.
	 */
	private function render_level_builder( $schedule_id, $levels ) {
		?>
		<div class="tdwp-level-builder" data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>">
			<div class="level-builder-controls">
				<button type="button" class="button button-secondary" id="add-blind-level">
					<?php esc_html_e( 'Add Blind Level', 'poker-tournament-import' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="add-break-level">
					<?php esc_html_e( 'Add Break', 'poker-tournament-import' ); ?>
				</button>
				<button type="button" class="button button-primary" id="save-levels">
					<?php esc_html_e( 'Save All Levels', 'poker-tournament-import' ); ?>
				</button>
			</div>

			<div class="levels-list-container">
				<table class="wp-list-table widefat fixed striped levels-list">
					<thead>
						<tr>
							<th class="column-drag">&nbsp;</th>
							<th class="column-order"><?php esc_html_e( '#', 'poker-tournament-import' ); ?></th>
							<th class="column-sb"><?php esc_html_e( 'Small Blind', 'poker-tournament-import' ); ?></th>
							<th class="column-bb"><?php esc_html_e( 'Big Blind', 'poker-tournament-import' ); ?></th>
							<th class="column-ante"><?php esc_html_e( 'Ante', 'poker-tournament-import' ); ?></th>
							<th class="column-type"><?php esc_html_e( 'Type', 'poker-tournament-import' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'poker-tournament-import' ); ?></th>
						</tr>
					</thead>
					<tbody id="levels-sortable">
						<?php if ( ! empty( $levels ) ) : ?>
							<?php foreach ( $levels as $level ) : ?>
								<?php $this->render_level_row( $level ); ?>
							<?php endforeach; ?>
						<?php else : ?>
							<tr class="no-levels">
								<td colspan="7"><?php esc_html_e( 'No levels yet. Click "Add Blind Level" to get started.', 'poker-tournament-import' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="level-preview">
				<h3><?php esc_html_e( 'Schedule Preview', 'poker-tournament-import' ); ?></h3>
				<div id="schedule-preview"></div>
			</div>
		</div>

		<!-- Level row template -->
		<script type="text/template" id="blind-level-template">
			<tr class="level-row" data-level-type="blind">
				<td class="column-drag"><span class="dashicons dashicons-menu"></span></td>
				<td class="column-order"><span class="level-number">1</span></td>
				<td class="column-sb"><input type="number" class="small-blind" value="25" min="1"></td>
				<td class="column-bb"><input type="number" class="big-blind" value="50" min="1"></td>
				<td class="column-ante"><input type="number" class="ante" value="0" min="0"></td>
				<td class="column-type"><?php esc_html_e( 'Blind Level', 'poker-tournament-import' ); ?></td>
				<td class="column-actions">
					<button type="button" class="button button-small delete-level"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></button>
				</td>
			</tr>
		</script>

		<script type="text/template" id="break-level-template">
			<tr class="level-row level-break" data-level-type="break">
				<td class="column-drag"><span class="dashicons dashicons-menu"></span></td>
				<td class="column-order"><span class="level-number">1</span></td>
				<td class="column-sb" colspan="3">
					<strong><?php esc_html_e( 'BREAK', 'poker-tournament-import' ); ?></strong> -
					<input type="number" class="break-length" value="15" min="1" max="60"> <?php esc_html_e( 'minutes', 'poker-tournament-import' ); ?>
				</td>
				<td class="column-type"><?php esc_html_e( 'Break', 'poker-tournament-import' ); ?></td>
				<td class="column-actions">
					<button type="button" class="button button-small delete-level"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></button>
				</td>
			</tr>
		</script>
		<?php
	}

	/**
	 * Render single level row
	 *
	 * @since 3.0.0
	 *
	 * @param object $level Level object.
	 */
	private function render_level_row( $level ) {
		if ( 1 === absint( $level->is_break ) ) {
			?>
			<tr class="level-row level-break" data-level-type="break">
				<td class="column-drag"><span class="dashicons dashicons-menu"></span></td>
				<td class="column-order"><span class="level-number"><?php echo esc_html( $level->level_order ); ?></span></td>
				<td class="column-sb" colspan="3">
					<strong><?php esc_html_e( 'BREAK', 'poker-tournament-import' ); ?></strong> -
					<input type="number" class="break-length" value="<?php echo esc_attr( $level->break_length ); ?>" min="1" max="60"> <?php esc_html_e( 'minutes', 'poker-tournament-import' ); ?>
				</td>
				<td class="column-type"><?php esc_html_e( 'Break', 'poker-tournament-import' ); ?></td>
				<td class="column-actions">
					<button type="button" class="button button-small delete-level"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></button>
				</td>
			</tr>
			<?php
		} else {
			?>
			<tr class="level-row" data-level-type="blind">
				<td class="column-drag"><span class="dashicons dashicons-menu"></span></td>
				<td class="column-order"><span class="level-number"><?php echo esc_html( $level->level_order ); ?></span></td>
				<td class="column-sb"><input type="number" class="small-blind" value="<?php echo esc_attr( $level->small_blind ); ?>" min="1"></td>
				<td class="column-bb"><input type="number" class="big-blind" value="<?php echo esc_attr( $level->big_blind ); ?>" min="1"></td>
				<td class="column-ante"><input type="number" class="ante" value="<?php echo esc_attr( $level->ante ); ?>" min="0"></td>
				<td class="column-type"><?php esc_html_e( 'Blind Level', 'poker-tournament-import' ); ?></td>
				<td class="column-actions">
					<button type="button" class="button button-small delete-level"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></button>
				</td>
			</tr>
			<?php
		}
	}
}

// Initialize the page.
new TDWP_Blind_Builder_Page();
