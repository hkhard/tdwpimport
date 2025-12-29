<?php
/**
 * Prize Calculator Admin Page
 *
 * Handles the prize calculator and structure management interface.
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
 * TDWP Prize Calculator Page Class
 *
 * Provides admin interface for prize structure management and calculations.
 *
 * @since 3.0.0
 */
class TDWP_Prize_Calculator_Page {

	/**
	 * Prize Structure Manager instance
	 *
	 * @since 3.0.0
	 * @var TDWP_Prize_Structure
	 */
	private $structure_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->structure_manager = new TDWP_Prize_Structure();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_tdwp_save_prize_structure', array( $this, 'ajax_save_structure' ) );
		add_action( 'wp_ajax_tdwp_calculate_prize_pool', array( $this, 'ajax_calculate_pool' ) );
		add_action( 'wp_ajax_tdwp_calculate_chop', array( $this, 'ajax_calculate_chop' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @since 3.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tdwp-tournament-manager',
			__( 'Prize Calculator', 'poker-tournament-import' ),
			__( 'Prize Calculator', 'poker-tournament-import' ),
			'manage_options',
			'tdwp-prize-calculator',
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
		if ( 'tournament-manager_page_tdwp-prize-calculator' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tdwp-prize-calculator-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/prize-calculator-admin.css',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION
		);

		wp_enqueue_script(
			'tdwp-prize-calculator-admin',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/prize-calculator-admin.js',
			array( 'jquery' ),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);

		wp_localize_script(
			'tdwp-prize-calculator-admin',
			'tdwpPrizeCalc',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tdwp_prize_calculator' ),
				'currency'    => get_option( 'tdwp_currency_symbol', '$' ),
				'i18n'        => array(
					'confirmDelete'   => __( 'Are you sure you want to delete this structure? This action cannot be undone.', 'poker-tournament-import' ),
					'errorSaving'     => __( 'Error saving structure. Please try again.', 'poker-tournament-import' ),
					'errorCalculating' => __( 'Error calculating payouts. Please check your inputs.', 'poker-tournament-import' ),
					'structureSaved'  => __( 'Structure saved successfully.', 'poker-tournament-import' ),
					'invalidPercentages' => __( 'Percentages must sum to 100%.', 'poker-tournament-import' ),
					'addPlace'        => __( 'Add Place', 'poker-tournament-import' ),
					'deletePlace'     => __( 'Delete', 'poker-tournament-import' ),
				),
				'commonStructures' => TDWP_Prize_Calculator::suggest_common_structures(),
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
		if ( ! isset( $_GET['page'] ) || 'tdwp-prize-calculator' !== $_GET['page'] ) {
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
	 * Handle structure save
	 *
	 * @since 3.0.0
	 */
	private function handle_save() {
		// Verify nonce.
		if ( ! isset( $_POST['structure_nonce'] ) ) {
			return;
		}

		$structure_id = isset( $_POST['structure_id'] ) ? absint( $_POST['structure_id'] ) : 0;
		check_admin_referer( 'tdwp_save_structure_' . $structure_id, 'structure_nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Collect structure data.
		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'is_template' => isset( $_POST['is_template'] ) ? 1 : 0,
			'min_players' => isset( $_POST['min_players'] ) ? absint( $_POST['min_players'] ) : 1,
			'max_players' => isset( $_POST['max_players'] ) ? absint( $_POST['max_players'] ) : 999,
		);

		// Parse structure JSON from form.
		$places = array();
		if ( isset( $_POST['places'] ) && is_array( $_POST['places'] ) ) {
			foreach ( $_POST['places'] as $place_data ) {
				if ( isset( $place_data['place'], $place_data['percentage'] ) ) {
					$places[] = array(
						'place'      => absint( $place_data['place'] ),
						'percentage' => floatval( $place_data['percentage'] ),
					);
				}
			}
		}

		$data['structure_json'] = wp_json_encode( $places );

		// Create or update structure.
		if ( $structure_id > 0 ) {
			$result = $this->structure_manager->update( $structure_id, $data );
			$message = 'updated';
		} else {
			$result = $this->structure_manager->create( $data );
			$structure_id = $result;
			$message = 'created';
		}

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-prize-calculator',
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
					'page'         => 'tdwp-prize-calculator',
					'action'       => 'edit',
					'structure_id' => $structure_id,
					'message'      => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle structure delete
	 *
	 * @since 3.0.0
	 */
	private function handle_delete() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['structure_id'] ) ) {
			return;
		}

		$structure_id = absint( $_GET['structure_id'] );
		// phpcs:enable

		// Verify nonce.
		check_admin_referer( 'tdwp_delete_structure_' . $structure_id );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Delete structure.
		$result = $this->structure_manager->delete( $structure_id );

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-prize-calculator',
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
					'page'    => 'tdwp-prize-calculator',
					'message' => 'deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle structure clone
	 *
	 * @since 3.0.0
	 */
	private function handle_clone() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['structure_id'] ) ) {
			return;
		}

		$structure_id = absint( $_GET['structure_id'] );
		// phpcs:enable

		// Verify nonce.
		check_admin_referer( 'tdwp_clone_structure_' . $structure_id );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}

		// Clone structure.
		$result = $this->structure_manager->clone_structure( $structure_id );

		// Handle result.
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'tdwp-prize-calculator',
						'message' => 'error',
						'details' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Redirect to edit cloned structure.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'tdwp-prize-calculator',
					'action'       => 'edit',
					'structure_id' => $result,
					'message'      => 'cloned',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX handler for saving structure
	 *
	 * @since 3.0.0
	 */
	public function ajax_save_structure() {
		// Verify nonce.
		check_ajax_referer( 'tdwp_prize_calculator', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'poker-tournament-import' ) ) );
		}

		// Get structure data.
		$structure_id = isset( $_POST['structure_id'] ) ? absint( $_POST['structure_id'] ) : 0;
		$name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$is_template  = isset( $_POST['is_template'] ) ? absint( $_POST['is_template'] ) : 0;
		$min_players  = isset( $_POST['min_players'] ) ? absint( $_POST['min_players'] ) : 1;
		$max_players  = isset( $_POST['max_players'] ) ? absint( $_POST['max_players'] ) : 999;

		// Get places.
		$places_raw = isset( $_POST['places'] ) ? json_decode( wp_unslash( $_POST['places'] ), true ) : array();

		if ( ! is_array( $places_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid places data.', 'poker-tournament-import' ) ) );
		}

		$places = array();
		foreach ( $places_raw as $place_data ) {
			$places[] = array(
				'place'      => absint( $place_data['place'] ),
				'percentage' => floatval( $place_data['percentage'] ),
			);
		}

		$data = array(
			'name'           => $name,
			'description'    => $description,
			'is_template'    => $is_template,
			'min_players'    => $min_players,
			'max_players'    => $max_players,
			'structure_json' => wp_json_encode( $places ),
		);

		// Create or update.
		if ( $structure_id > 0 ) {
			$result = $this->structure_manager->update( $structure_id, $data );
		} else {
			$result = $this->structure_manager->create( $data );
			$structure_id = $result;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Structure saved successfully.', 'poker-tournament-import' ),
				'structure_id' => $structure_id,
			)
		);
	}

	/**
	 * AJAX handler for calculating prize pool
	 *
	 * @since 3.0.0
	 */
	public function ajax_calculate_pool() {
		// Verify nonce.
		check_ajax_referer( 'tdwp_prize_calculator', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'poker-tournament-import' ) ) );
		}

		// Get inputs.
		$buy_in      = isset( $_POST['buy_in'] ) ? floatval( $_POST['buy_in'] ) : 0;
		$entries     = isset( $_POST['entries'] ) ? absint( $_POST['entries'] ) : 0;
		$rebuys      = isset( $_POST['rebuys'] ) ? absint( $_POST['rebuys'] ) : 0;
		$addons      = isset( $_POST['addons'] ) ? absint( $_POST['addons'] ) : 0;
		$rebuy_cost  = isset( $_POST['rebuy_cost'] ) ? floatval( $_POST['rebuy_cost'] ) : 0;
		$addon_cost  = isset( $_POST['addon_cost'] ) ? floatval( $_POST['addon_cost'] ) : 0;
		$rake_pct    = isset( $_POST['rake_percentage'] ) ? floatval( $_POST['rake_percentage'] ) : 0;

		// Calculate pool.
		$pool = TDWP_Prize_Calculator::calculate_prize_pool(
			$buy_in,
			$entries,
			$rebuys,
			$addons,
			$rebuy_cost,
			$addon_cost,
			$rake_pct
		);

		wp_send_json_success( $pool );
	}

	/**
	 * AJAX handler for calculating chop
	 *
	 * @since 3.0.0
	 */
	public function ajax_calculate_chop() {
		// Verify nonce.
		check_ajax_referer( 'tdwp_prize_calculator', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'poker-tournament-import' ) ) );
		}

		// Get inputs.
		$chop_type      = isset( $_POST['chop_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chop_type'] ) ) : 'chip';
		$remaining_pool = isset( $_POST['remaining_pool'] ) ? floatval( $_POST['remaining_pool'] ) : 0;
		$players_raw    = isset( $_POST['players'] ) ? json_decode( wp_unslash( $_POST['players'] ), true ) : array();

		if ( ! is_array( $players_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid players data.', 'poker-tournament-import' ) ) );
		}

		// Build chip counts array.
		$chip_counts = array();
		foreach ( $players_raw as $player_data ) {
			$name = sanitize_text_field( wp_unslash( $player_data['name'] ) );
			$chips = absint( $player_data['chips'] );
			$chip_counts[ $name ] = $chips;
		}

		// Calculate chop.
		if ( 'icm' === $chop_type ) {
			// For ICM, we need remaining prizes.
			$remaining_prizes = isset( $_POST['remaining_prizes'] ) ? array_map( 'floatval', (array) $_POST['remaining_prizes'] ) : array();

			if ( empty( $remaining_prizes ) ) {
				// Default: split remaining pool by number of players.
				$player_count = count( $chip_counts );
				for ( $i = 0; $i < $player_count; $i++ ) {
					$remaining_prizes[] = $remaining_pool / $player_count;
				}
			}

			$chop = TDWP_Prize_Calculator::calculate_icm_chop( $remaining_prizes, $chip_counts );
		} elseif ( 'even' === $chop_type ) {
			$players = array_keys( $chip_counts );
			$chop = TDWP_Prize_Calculator::calculate_even_chop( $remaining_pool, $players );
		} else {
			$chop = TDWP_Prize_Calculator::calculate_chip_chop( $remaining_pool, $chip_counts );
		}

		wp_send_json_success( array( 'chop' => $chop ) );
	}

	/**
	 * Render main page
	 *
	 * @since 3.0.0
	 */
	public function render_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action       = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$structure_id = isset( $_GET['structure_id'] ) ? absint( $_GET['structure_id'] ) : 0;
		$message      = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		// phpcs:enable

		?>
		<div class="wrap tdwp-prize-calculator">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Prize Calculator', 'poker-tournament-import' ); ?></h1>

			<?php if ( 'list' === $action ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Structure', 'poker-tournament-import' ); ?>
				</a>
			<?php elseif ( 'edit' === $action && $structure_id > 0 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Back to Structures', 'poker-tournament-import' ); ?>
				</a>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php $this->display_messages( $message ); ?>

			<?php
			if ( 'calculator' === $action ) {
				$this->render_calculator_tool();
			} elseif ( 'chop' === $action ) {
				$this->render_chop_calculator();
			} elseif ( 'new' === $action || 'edit' === $action ) {
				$this->render_form( $structure_id );
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
			'created' => __( 'Structure created successfully.', 'poker-tournament-import' ),
			'updated' => __( 'Structure updated successfully.', 'poker-tournament-import' ),
			'deleted' => __( 'Structure deleted successfully.', 'poker-tournament-import' ),
			'cloned'  => __( 'Structure cloned successfully.', 'poker-tournament-import' ),
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
	 * Render structures list
	 *
	 * @since 3.0.0
	 */
	private function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$per_page = 20;

		$args = array(
			'search'   => $search,
			'page'     => $paged,
			'per_page' => $per_page,
		);

		$structures = $this->structure_manager->get_all( $args );
		$total      = $this->structure_manager->get_total( array( 'search' => $search ) );

		?>
		<div class="tdwp-calculator-tabs">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab nav-tab-active">
				<?php esc_html_e( 'Structures', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Pool Calculator', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'chop' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Chop Calculator', 'poker-tournament-import' ); ?>
			</a>
		</div>

		<form method="get">
			<input type="hidden" name="page" value="tdwp-prize-calculator">
			<?php wp_nonce_field( 'tdwp_structure_search', 'search_nonce' ); ?>
			<p class="search-box">
				<label class="screen-reader-text" for="structure-search-input"><?php esc_html_e( 'Search Structures:', 'poker-tournament-import' ); ?></label>
				<input type="search" id="structure-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
				<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Structures', 'poker-tournament-import' ); ?>">
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-name"><?php esc_html_e( 'Name', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-players"><?php esc_html_e( 'Players', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-places"><?php esc_html_e( 'Places Paid', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-preview"><?php esc_html_e( 'Preview', 'poker-tournament-import' ); ?></th>
					<th scope="col" class="column-date"><?php esc_html_e( 'Created', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $structures ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No structures found.', 'poker-tournament-import' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $structures as $structure ) : ?>
						<?php
						$edit_url = add_query_arg(
							array(
								'page'         => 'tdwp-prize-calculator',
								'action'       => 'edit',
								'structure_id' => $structure->id,
							),
							admin_url( 'admin.php' )
						);
						$delete_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'         => 'tdwp-prize-calculator',
									'action'       => 'delete',
									'structure_id' => $structure->id,
								),
								admin_url( 'admin.php' )
							),
							'tdwp_delete_structure_' . $structure->id
						);
						$clone_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'         => 'tdwp-prize-calculator',
									'action'       => 'clone',
									'structure_id' => $structure->id,
								),
								admin_url( 'admin.php' )
							),
							'tdwp_clone_structure_' . $structure->id
						);

						$preview = array();
						if ( ! empty( $structure->places ) ) {
							foreach ( array_slice( $structure->places, 0, 3 ) as $place ) {
								$preview[] = number_format( $place['percentage'], 1 ) . '%';
							}
						}
						?>
						<tr>
							<td class="column-name">
								<strong>
									<a href="<?php echo esc_url( $edit_url ); ?>">
										<?php echo esc_html( $structure->name ); ?>
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
							<td class="column-players">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: min players, 2: max players */
										__( '%1$d - %2$d', 'poker-tournament-import' ),
										$structure->min_players,
										$structure->max_players
									)
								);
								?>
							</td>
							<td class="column-places">
								<?php echo absint( count( $structure->places ) ); ?>
							</td>
							<td class="column-preview">
								<?php echo esc_html( implode( ' / ', $preview ) ); ?>
								<?php if ( count( $structure->places ) > 3 ) : ?>
									<span class="tdwp-text-muted">...</span>
								<?php endif; ?>
							</td>
							<td class="column-date">
								<?php echo esc_html( mysql2date( get_option( 'date_format' ), $structure->created_at ) ); ?>
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
	 * Render structure form
	 *
	 * @since 3.0.0
	 *
	 * @param int $structure_id Structure ID (0 for new).
	 */
	private function render_form( $structure_id ) {
		$structure = null;

		if ( $structure_id > 0 ) {
			$structure = $this->structure_manager->get( $structure_id );

			if ( null === $structure ) {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Structure not found.', 'poker-tournament-import' ); ?></p>
				</div>
				<?php
				return;
			}
		}

		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'save' ), admin_url( 'admin.php' ) ) ); ?>" class="tdwp-structure-form">
			<?php wp_nonce_field( 'tdwp_save_structure_' . $structure_id, 'structure_nonce' ); ?>
			<input type="hidden" name="structure_id" value="<?php echo esc_attr( $structure_id ); ?>">

			<h2><?php echo $structure_id > 0 ? esc_html__( 'Edit Structure', 'poker-tournament-import' ) : esc_html__( 'New Structure', 'poker-tournament-import' ); ?></h2>

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="structure-name"><?php esc_html_e( 'Structure Name', 'poker-tournament-import' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="name" id="structure-name" class="regular-text" value="<?php echo $structure ? esc_attr( $structure->name ) : ''; ?>" required>
							<p class="description"><?php esc_html_e( 'Enter a descriptive name for this prize structure.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="structure-description"><?php esc_html_e( 'Description', 'poker-tournament-import' ); ?></label>
						</th>
						<td>
							<textarea name="description" id="structure-description" rows="3" class="large-text"><?php echo $structure ? esc_textarea( $structure->description ) : ''; ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of this prize structure.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="min-players"><?php esc_html_e( 'Player Range', 'poker-tournament-import' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="number" name="min_players" id="min-players" class="small-text" value="<?php echo $structure ? esc_attr( $structure->min_players ) : '1'; ?>" min="1" required>
							<span><?php esc_html_e( 'to', 'poker-tournament-import' ); ?></span>
							<input type="number" name="max_players" id="max-players" class="small-text" value="<?php echo $structure ? esc_attr( $structure->max_players ) : '999'; ?>" min="1" required>
							<span><?php esc_html_e( 'players', 'poker-tournament-import' ); ?></span>
							<p class="description"><?php esc_html_e( 'Minimum and maximum number of players for this structure.', 'poker-tournament-import' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Template', 'poker-tournament-import' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="is_template" value="1" <?php checked( $structure && $structure->is_template, 1 ); ?>>
								<?php esc_html_e( 'Mark as template for suggestions', 'poker-tournament-import' ); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Payout Structure', 'poker-tournament-import' ); ?></h3>

			<div class="tdwp-payout-builder">
				<div class="payout-builder-controls">
					<button type="button" class="button button-secondary" id="add-place-button">
						<?php esc_html_e( 'Add Place', 'poker-tournament-import' ); ?>
					</button>

					<div class="preset-buttons">
						<span><?php esc_html_e( 'Presets:', 'poker-tournament-import' ); ?></span>
						<button type="button" class="button button-small" data-preset="winner_takes_all"><?php esc_html_e( 'Winner', 'poker-tournament-import' ); ?></button>
						<button type="button" class="button button-small" data-preset="top_3_50_30_20"><?php esc_html_e( 'Top 3', 'poker-tournament-import' ); ?></button>
						<button type="button" class="button button-small" data-preset="top_4_40_30_20_10"><?php esc_html_e( 'Top 4', 'poker-tournament-import' ); ?></button>
						<button type="button" class="button button-small" data-preset="top_5_35_25_20_12_8"><?php esc_html_e( 'Top 5', 'poker-tournament-import' ); ?></button>
						<button type="button" class="button button-small" data-preset="top_6_30_22_18_14_10_6"><?php esc_html_e( 'Top 6', 'poker-tournament-import' ); ?></button>
					</div>

					<div class="percentage-sum-indicator">
						<?php esc_html_e( 'Total:', 'poker-tournament-import' ); ?>
						<span id="percentage-sum" class="sum-value">0.0</span>%
						<span id="sum-status" class="sum-status"></span>
					</div>
				</div>

				<div id="places-container" class="places-list">
					<?php if ( $structure && ! empty( $structure->places ) ) : ?>
						<?php foreach ( $structure->places as $place_data ) : ?>
							<?php $this->render_place_row( $place_data['place'], $place_data['percentage'] ); ?>
						<?php endforeach; ?>
					<?php else : ?>
						<?php $this->render_place_row( 1, 100 ); ?>
					<?php endif; ?>
				</div>

				<div class="payout-preview">
					<h4><?php esc_html_e( 'Example Payouts ($10,000 pool)', 'poker-tournament-import' ); ?></h4>
					<div id="payout-preview-content"></div>
				</div>
			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Structure', 'poker-tournament-import' ); ?>">
			</p>
		</form>

		<!-- Place row template -->
		<script type="text/template" id="place-row-template">
			<?php $this->render_place_row( '__PLACE__', '__PERCENTAGE__' ); ?>
		</script>
		<?php
	}

	/**
	 * Render single place row
	 *
	 * @since 3.0.0
	 *
	 * @param int   $place      Place number.
	 * @param float $percentage Percentage.
	 */
	private function render_place_row( $place, $percentage ) {
		?>
		<div class="place-row">
			<div class="place-number">
				<label>
					<?php esc_html_e( 'Place', 'poker-tournament-import' ); ?>
					<input type="number" name="places[<?php echo esc_attr( $place ); ?>][place]" value="<?php echo esc_attr( $place ); ?>" class="place-input" readonly>
				</label>
			</div>
			<div class="place-percentage">
				<label>
					<?php esc_html_e( 'Percentage', 'poker-tournament-import' ); ?>
					<input type="number" name="places[<?php echo esc_attr( $place ); ?>][percentage]" value="<?php echo esc_attr( $percentage ); ?>" class="percentage-input" min="0" max="100" step="0.01" required>
					<span>%</span>
				</label>
			</div>
			<div class="place-actions">
				<button type="button" class="button button-small delete-place"><?php esc_html_e( 'Delete', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render calculator tool
	 *
	 * @since 3.0.0
	 */
	private function render_calculator_tool() {
		?>
		<div class="tdwp-calculator-tabs">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Structures', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab nav-tab-active">
				<?php esc_html_e( 'Pool Calculator', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'chop' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Chop Calculator', 'poker-tournament-import' ); ?>
			</a>
		</div>

		<div class="tdwp-calculator-tool">
			<div class="calculator-inputs">
				<h3><?php esc_html_e( 'Tournament Details', 'poker-tournament-import' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><label for="calc-buyin"><?php esc_html_e( 'Buy-in', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-buyin" class="regular-text" min="0" step="0.01" value="100"></td>
					</tr>
					<tr>
						<th><label for="calc-entries"><?php esc_html_e( 'Entries', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-entries" class="regular-text" min="0" value="50"></td>
					</tr>
					<tr>
						<th><label for="calc-rebuys"><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-rebuys" class="regular-text" min="0" value="0"></td>
					</tr>
					<tr>
						<th><label for="calc-rebuy-cost"><?php esc_html_e( 'Rebuy Cost', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-rebuy-cost" class="regular-text" min="0" step="0.01" value="0"></td>
					</tr>
					<tr>
						<th><label for="calc-addons"><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-addons" class="regular-text" min="0" value="0"></td>
					</tr>
					<tr>
						<th><label for="calc-addon-cost"><?php esc_html_e( 'Add-on Cost', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-addon-cost" class="regular-text" min="0" step="0.01" value="0"></td>
					</tr>
					<tr>
						<th><label for="calc-rake"><?php esc_html_e( 'Rake %', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="calc-rake" class="regular-text" min="0" max="100" step="0.01" value="0"></td>
					</tr>
				</table>

				<p>
					<button type="button" id="calculate-pool-button" class="button button-primary"><?php esc_html_e( 'Calculate Prize Pool', 'poker-tournament-import' ); ?></button>
				</p>
			</div>

			<div class="calculator-results" id="calculator-results" style="display:none;">
				<h3><?php esc_html_e( 'Prize Pool Breakdown', 'poker-tournament-import' ); ?></h3>
				<div id="pool-breakdown"></div>

				<h3><?php esc_html_e( 'Payout Distribution', 'poker-tournament-import' ); ?></h3>
				<div>
					<label for="structure-select"><?php esc_html_e( 'Select Structure:', 'poker-tournament-import' ); ?></label>
					<select id="structure-select" class="regular-text">
						<option value=""><?php esc_html_e( '-- Select Structure --', 'poker-tournament-import' ); ?></option>
						<?php
						$structures = $this->structure_manager->get_all( array( 'per_page' => 100 ) );
						foreach ( $structures as $structure ) {
							echo '<option value="' . esc_attr( $structure->id ) . '">' . esc_html( $structure->name ) . '</option>';
						}
						?>
					</select>
				</div>
				<div id="payout-distribution"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render chop calculator
	 *
	 * @since 3.0.0
	 */
	private function render_chop_calculator() {
		?>
		<div class="tdwp-calculator-tabs">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Structures', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'calculator' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab">
				<?php esc_html_e( 'Pool Calculator', 'poker-tournament-import' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tdwp-prize-calculator', 'action' => 'chop' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab nav-tab-active">
				<?php esc_html_e( 'Chop Calculator', 'poker-tournament-import' ); ?>
			</a>
		</div>

		<div class="tdwp-chop-calculator">
			<div class="chop-inputs">
				<h3><?php esc_html_e( 'Deal Configuration', 'poker-tournament-import' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><label for="chop-type"><?php esc_html_e( 'Chop Type', 'poker-tournament-import' ); ?></label></th>
						<td>
							<select id="chop-type" class="regular-text">
								<option value="chip"><?php esc_html_e( 'Chip Chop (Proportional)', 'poker-tournament-import' ); ?></option>
								<option value="icm"><?php esc_html_e( 'ICM (Independent Chip Model)', 'poker-tournament-import' ); ?></option>
								<option value="even"><?php esc_html_e( 'Even Chop', 'poker-tournament-import' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="remaining-pool"><?php esc_html_e( 'Remaining Prize Pool', 'poker-tournament-import' ); ?></label></th>
						<td><input type="number" id="remaining-pool" class="regular-text" min="0" step="0.01" value="10000"></td>
					</tr>
				</table>

				<h4><?php esc_html_e( 'Players & Chip Counts', 'poker-tournament-import' ); ?></h4>

				<div id="chop-players-container">
					<div class="chop-player-row">
						<input type="text" class="player-name" placeholder="<?php esc_attr_e( 'Player Name', 'poker-tournament-import' ); ?>" value="Player 1">
						<input type="number" class="player-chips" placeholder="<?php esc_attr_e( 'Chips', 'poker-tournament-import' ); ?>" min="0" value="50000">
						<button type="button" class="button button-small remove-player"><?php esc_html_e( 'Remove', 'poker-tournament-import' ); ?></button>
					</div>
					<div class="chop-player-row">
						<input type="text" class="player-name" placeholder="<?php esc_attr_e( 'Player Name', 'poker-tournament-import' ); ?>" value="Player 2">
						<input type="number" class="player-chips" placeholder="<?php esc_attr_e( 'Chips', 'poker-tournament-import' ); ?>" min="0" value="30000">
						<button type="button" class="button button-small remove-player"><?php esc_html_e( 'Remove', 'poker-tournament-import' ); ?></button>
					</div>
				</div>

				<p>
					<button type="button" id="add-chop-player" class="button button-secondary"><?php esc_html_e( 'Add Player', 'poker-tournament-import' ); ?></button>
					<button type="button" id="calculate-chop-button" class="button button-primary"><?php esc_html_e( 'Calculate Chop', 'poker-tournament-import' ); ?></button>
				</p>
			</div>

			<div class="chop-results" id="chop-results" style="display:none;">
				<h3><?php esc_html_e( 'Recommended Deal', 'poker-tournament-import' ); ?></h3>
				<div id="chop-distribution"></div>
			</div>
		</div>
		<?php
	}
}

// Initialize the page.
new TDWP_Prize_Calculator_Page();
