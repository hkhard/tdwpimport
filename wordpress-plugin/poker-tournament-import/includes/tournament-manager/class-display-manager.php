<?php
/**
 * TD3 Integration: Display Manager
 *
 * Coordinates display system functionality for Tournament Director 3 integration.
 * Manages templates, layouts, screens, and token rendering for tournament displays.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display Manager Class
 *
 * Handles coordination of all display system components including:
 * - Template management and rendering
 * - Layout configuration and assignment
 * - Screen endpoint management
 * - Token processing and caching
 * - Multi-screen synchronization
 *
 * @since 3.4.0
 */
class TDWP_Display_Manager {

	/**
	 * Singleton instance
	 *
	 * @var TDWP_Display_Manager|null
	 */
	private static $instance = null;

	/**
	 * Template engine instance
	 *
	 * @var TDWP_Template_Engine|null
	 */
	private $template_engine = null;

	/**
	 * Layout builder instance
	 *
	 * @var TDWP_Layout_Builder|null
	 */
	private $layout_builder = null;

	/**
	 * Get singleton instance
	 *
	 * @since 3.4.0
	 * @return TDWP_Display_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 3.4.0
	 */
	private function __construct() {
		error_log( 'TDWP Display Manager: Constructor called - Display Manager instance created' );

		// Initialize dependencies immediately when classes are available
		$this->init_dependencies();

		// Register AJAX handlers
		add_action( 'wp_ajax_tdwp_save_display_template', array( $this, 'ajax_save_display_template' ) );
		add_action( 'wp_ajax_tdwp_get_display_templates', array( $this, 'ajax_get_display_templates' ) );
		add_action( 'wp_ajax_tdwp_save_display_layout', array( $this, 'ajax_save_display_layout' ) );
		add_action( 'wp_ajax_tdwp_get_display_screens', array( $this, 'ajax_get_display_screens' ) );

		// Screen management AJAX handlers
		add_action( 'wp_ajax_tdwp_register_screen', array( $this, 'ajax_register_screen' ) );
		add_action( 'wp_ajax_tdwp_update_screen', array( $this, 'ajax_update_screen' ) );
		add_action( 'wp_ajax_tdwp_delete_screen', array( $this, 'ajax_delete_screen' ) );
		add_action( 'wp_ajax_tdwp_get_screen_health', array( $this, 'ajax_get_screen_health' ) );
		add_action( 'wp_ajax_tdwp_get_all_screens_health', array( $this, 'ajax_get_all_screens_health' ) );

		// Register display endpoint rewrite rules at proper WordPress timing
		add_action( 'init', array( $this, 'register_display_endpoints' ), 11 );
		error_log( 'TDWP Display Manager: Hook registered register_display_endpoints to init action with priority 11' );

		add_filter( 'query_vars', array( $this, 'add_display_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_display_request' ) );

		// Initialize heartbeat synchronization
		add_action( 'init', array( $this, 'init_heartbeat_sync' ) );

		error_log( 'TDWP Display Manager: Constructor completed - All hooks registered successfully' );
	}

	/**
	 * Initialize dependencies
	 *
	 * @since 3.4.0
	 */
	public function init_dependencies() {
		// Load template engine if available
		if ( class_exists( 'TDWP_Template_Engine' ) ) {
			$this->template_engine = TDWP_Template_Engine::get_instance();
			error_log( 'TDWP Display Manager: Template engine loaded successfully' );
		} else {
			error_log( 'TDWP Display Manager: Template engine class not found - display rendering will use fallback' );
		}

		// Load layout builder if available
		if ( class_exists( 'TDWP_Layout_Builder' ) ) {
			$this->layout_builder = TDWP_Layout_Builder::get_instance();
			error_log( 'TDWP Display Manager: Layout builder loaded successfully' );
		} else {
			error_log( 'TDWP Display Manager: Layout builder class not found - layout features will be limited' );
		}
	}

	/**
	 * Register display endpoint rewrite rules
	 *
	 * @since 3.4.0
	 */
	public function register_display_endpoints() {
		try {
			error_log( 'TDWP Display Manager: Starting rewrite rule registration process' );

			$rules_registered = 0;

			// Main display endpoint
			add_rewrite_rule(
				'^tdwp-display/([^/]+)/?$',
				'index.php?tdwp_display=$matches[1]',
				'top'
			);
			$rules_registered++;
			error_log( 'TDWP Display Manager: Main display endpoint rule registered' );

			// Tournament-specific display endpoints
			add_rewrite_rule(
				'^tdwp-display/tournament/([0-9]+)/([^/]+)/?$',
				'index.php?tdwp_tournament=$matches[1]&tdwp_display=$matches[2]',
				'top'
			);
			$rules_registered++;
			error_log( 'TDWP Display Manager: Tournament display endpoint rule registered' );

			// Screen preview endpoints
			add_rewrite_rule(
				'^tdwp-preview/([^/]+)/?$',
				'index.php?tdwp_preview=$matches[1]',
				'top'
			);
			$rules_registered++;
			error_log( 'TDWP Display Manager: Screen preview endpoint rule registered' );

			error_log( 'TDWP Display Manager: Total rewrite rules registered: ' . $rules_registered . '/3' );

			// Flush rewrite rules once
			if ( ! get_option( 'tdwp_display_endpoints_flushed' ) ) {
				error_log( 'TDWP Display Manager: Flushing rewrite rules for first time' );

				$flush_result = flush_rewrite_rules();

				if ($flush_result !== false) {
					update_option( 'tdwp_display_endpoints_flushed', true );
					error_log( 'TDWP Display Manager: Rewrite rules flushed successfully and option set' );
				} else {
					error_log( 'TDWP Display Manager: WARNING - Rewrite rules flush returned false' );
				}
			} else {
				error_log( 'TDWP Display Manager: Rewrite rules already flushed previously' );
			}

			error_log( 'TDWP Display Manager: Rewrite rule registration process completed successfully' );

		} catch ( Exception $e ) {
			error_log( 'TDWP Display Manager: ERROR in register_display_endpoints: ' . $e->getMessage() );
			error_log( 'TDWP Display Manager: Error trace: ' . $e->getTraceAsString() );
		}
	}

	/**
	 * Manual rewrite rule flush for debugging
	 *
	 * @since 3.4.1
	 * @return array Result of the flush operation
	 */
	public function manual_flush_rewrite_rules() {
		try {
			error_log( 'TDWP Display Manager: Starting manual rewrite rule flush' );

			// Clear the option to force re-flush
			delete_option( 'tdwp_display_endpoints_flushed' );

			// Manually register endpoints
			$this->register_display_endpoints();

			// Get current rewrite rules
			global $wp_rewrite;
			$rewrite_rules = get_option( 'rewrite_rules' );

			$found_rules = array();
			if ( is_array( $rewrite_rules ) ) {
				foreach ( $rewrite_rules as $pattern => $substitution ) {
					if ( strpos( $pattern, 'tdwp-display' ) !== false || strpos( $pattern, 'tdwp-preview' ) !== false ) {
						$found_rules[] = $pattern;
					}
				}
			}

			$result = array(
				'success' => true,
				'flushed' => true,
				'rules_found' => count( $found_rules ),
				'rules' => $found_rules,
				'option_set' => get_option( 'tdwp_display_endpoints_flushed', false ),
				'message' => 'Manual rewrite rule flush completed successfully'
			);

			error_log( 'TDWP Display Manager: Manual flush result - Rules found: ' . count( $found_rules ) );

			return $result;

		} catch ( Exception $e ) {
			error_log( 'TDWP Display Manager: ERROR in manual_flush_rewrite_rules: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error' => $e->getMessage(),
				'message' => 'Manual rewrite rule flush failed'
			);
		}
	}

	/**
	 * Add display query variables
	 *
	 * @since 3.4.0
	 * @param array $query_vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function add_display_query_vars( $query_vars ) {
		$query_vars[] = 'tdwp_display';
		$query_vars[] = 'tdwp_tournament';
		$query_vars[] = 'tdwp_preview';
		return $query_vars;
	}

	/**
	 * Handle display requests
	 *
	 * @since 3.4.0
	 */
	public function handle_display_request() {
		$display_id = get_query_var( 'tdwp_display' );
		$tournament_id = get_query_var( 'tdwp_tournament' );
		$preview_id = get_query_var( 'tdwp_preview' );

		// Handle preview requests
		if ( ! empty( $preview_id ) ) {
			$this->render_preview( $preview_id );
			exit;
		}

		// Handle tournament-specific display requests
		if ( ! empty( $display_id ) && ! empty( $tournament_id ) ) {
			$this->render_tournament_display( $display_id, $tournament_id );
			exit;
		}

		// Handle regular display requests
		if ( ! empty( $display_id ) ) {
			$this->render_display( $display_id );
			exit;
		}
	}

	/**
	 * Initialize heartbeat synchronization
	 *
	 * @since 3.4.0
	 */
	public function init_heartbeat_sync() {
		// Filter heartbeat data for display synchronization
		add_filter( 'heartbeat_received', array( $this, 'handle_heartbeat_sync' ), 10, 3 );
		add_filter( 'heartbeat_send', array( $this, 'prepare_heartbeat_data' ), 10, 2 );

		// Enqueue heartbeat scripts on display pages
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_display_scripts' ) );

		// Schedule periodic screen health checks
		add_action( 'tdwp_screen_health_check', array( $this, 'perform_screen_health_check' ) );

		if ( ! wp_next_scheduled( 'tdwp_screen_health_check' ) ) {
			wp_schedule_event( time(), 'tdwp_5min', 'tdwp_screen_health_check' );
		}

		// Add custom cron schedule for 5-minute intervals
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @since 3.4.0
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['tdwp_5min'] = array(
			'interval' => 300, // 5 minutes
			'display'  => 'Every 5 minutes',
		);

		$schedules['tdwp_30sec'] = array(
			'interval' => 30, // 30 seconds
			'display'  => 'Every 30 seconds',
		);

		return $schedules;
	}

	/**
	 * Enqueue display scripts
	 *
	 * @since 3.4.0
	 */
	public function enqueue_display_scripts() {
		// Only enqueue on display pages
		if ( get_query_var( 'tdwp_display' ) || get_query_var( 'tdwp_preview' ) ) {
			wp_enqueue_script( 'heartbeat' );

			// Enqueue display synchronization script
			wp_enqueue_script(
				'tdwp-display-sync',
				plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . '/assets/js/display-sync.js',
				array( 'jquery', 'heartbeat' ),
				'3.4.0',
				true
			);

			wp_localize_script( 'tdwp-display-sync', 'tdwp_display_sync', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'tdwp_display_nonce' ),
				'screen_id' => get_query_var( 'tdwp_display' ),
				'heartbeat_interval' => 'fast',
			) );
		}
	}

	/**
	 * Prepare heartbeat data to send to client
	 *
	 * @since 3.4.0
	 * @param array $response Heartbeat response.
	 * @param array $screen_id Screen ID.
	 * @return array Modified response.
	 */
	public function prepare_heartbeat_data( $response, $screen_id ) {
		if ( ! $screen_id ) {
			return $response;
		}

		$display_id = get_query_var( 'tdwp_display' );
		$tournament_id = get_query_var( 'tdwp_tournament' );

		if ( $display_id ) {
			// Add current tournament state to heartbeat response
			if ( $tournament_id && $this->template_engine ) {
				$tournament_data = $this->template_engine->get_tournament_data( $tournament_id );
				$response['tdwp_tournament_state'] = $tournament_data;
			}

			// Add screen health status
			$response['tdwp_screen_health'] = $this->get_screen_health_by_endpoint( $display_id );

			// Add last updated timestamp
			$response['tdwp_last_updated'] = current_time( 'mysql' );
		}

		return $response;
	}

	/**
	 * Handle heartbeat synchronization
	 *
	 * @since 3.4.0
	 * @param array $response Heartbeat response.
	 * @param array $data Heartbeat data from client.
	 * @param int    $screen_id Screen ID.
	 * @return array Modified response.
	 */
	public function handle_heartbeat_sync( $response, $data, $screen_id ) {
		if ( empty( $data['tdwp_screen_id'] ) ) {
			return $response;
		}

		$screen_id = $data['tdwp_screen_id'];
		$display_id = $data['tdwp_display_id'] ?? '';

		// Update screen ping time
		$this->update_screen_status( $screen_id, array(
			'last_ping' => current_time( 'mysql' ),
			'is_online' => true,
		) );

		// Get updated tournament data if requested
		if ( ! empty( $data['tdwp_tournament_id'] ) ) {
			$tournament_id = intval( $data['tdwp_tournament_id'] );
			if ( $this->template_engine ) {
				$tournament_data = $this->template_engine->get_tournament_data( $tournament_id );
				$response['tdwp_tournament_data'] = $tournament_data;
			}
		}

		// Check if layout/template updates are needed
		if ( ! empty( $data['tdwp_check_updates'] ) ) {
			$updates = $this->check_for_screen_updates( $screen_id, $display_id );
			$response['tdwp_updates'] = $updates;
		}

		// Add synchronization status
		$response['tdwp_sync_status'] = array(
			'status' => 'connected',
			'last_sync' => current_time( 'mysql' ),
			'next_sync' => date( 'Y-m-d H:i:s', time() + 30 ),
		);

		return $response;
	}

	/**
	 * Get screen health by endpoint
	 *
	 * @since 3.4.0
	 * @param string $endpoint_url Endpoint URL.
	 * @return array Health status.
	 */
	public function get_screen_health_by_endpoint( $endpoint_url ) {
		$screen = $this->get_screen_by_endpoint( $endpoint_url );

		if ( ! $screen ) {
			return array(
				'status' => 'error',
				'message' => 'Screen not found',
				'online' => false,
			);
		}

		return $this->get_screen_health( $screen->screen_id );
	}

	/**
	 * Check for screen updates
	 *
	 * @since 3.4.0
	 * @param int    $screen_id Screen ID.
	 * @param string $display_id Display identifier.
	 * @return array Update information.
	 */
	public function check_for_screen_updates( $screen_id, $display_id ) {
		$updates = array(
			'layout_changed' => false,
			'template_changed' => false,
			'tournament_data_changed' => false,
		);

		$screen = $this->get_screen_status( $screen_id );
		if ( ! $screen ) {
			return $updates;
		}

		// Check if layout was updated since last client check
		if ( $screen->layout_id ) {
			$last_layout_update = get_post_modified_time( 'U', true, $screen->layout_id );
			$client_last_check = $screen->last_ping ? strtotime( $screen->last_ping ) : 0;

			$updates['layout_changed'] = $last_layout_update > $client_last_check;
		}

		// Check if template was updated
		if ( $screen->template_id ) {
			$last_template_update = get_post_modified_time( 'U', true, $screen->template_id );
			$client_last_check = $screen->last_ping ? strtotime( $screen->last_ping ) : 0;

			$updates['template_changed'] = $last_template_update > $client_last_check;
		}

		// Check tournament data changes
		$tournament_id = get_query_var( 'tdwp_tournament' );
		if ( $tournament_id ) {
			$last_tournament_update = get_post_modified_time( 'U', true, $tournament_id );
			$client_last_check = $screen->last_ping ? strtotime( $screen->last_ping ) : 0;

			$updates['tournament_data_changed'] = $last_tournament_update > $client_last_check;
		}

		return $updates;
	}

	/**
	 * Perform scheduled screen health check
	 *
	 * @since 3.4.0
	 */
	public function perform_screen_health_check() {
		global $wpdb;

		// Get all screens that haven't pinged in the last 5 minutes
		$stale_time = date( 'Y-m-d H:i:s', time() - 300 );

		$stale_screens = $wpdb->get_results( $wpdb->prepare(
			"SELECT screen_id, screen_name, endpoint_url, last_ping
			 FROM {$wpdb->prefix}poker_display_screens
			 WHERE last_ping < %s AND is_online = 1",
			$stale_time
		) );

		foreach ( $stale_screens as $screen ) {
			// Mark screen as offline
			$this->update_screen_status( $screen->screen_id, array(
				'is_online' => false,
			) );

			// Trigger action for offline screen
			do_action( 'tdwp_screen_went_offline', $screen->screen_id, $screen );
		}

		// Log health check results
		if ( ! empty( $stale_screens ) ) {
			error_log( "TDWP Display: Marked " . count( $stale_screens ) . " screens as offline due to stale ping" );
		}
	}

	/**
	 * Render display output
	 *
	 * @since 3.4.0
	 * @param string $display_id Display identifier.
	 */
	public function render_display( $display_id ) {
		global $wpdb;

		// Get screen configuration
		$screen = $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, t.html_template, t.css_styles, l.grid_config, l.component_positions
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id
			 WHERE s.endpoint_url = %s OR s.screen_name = %s",
			$display_id, $display_id
		) );

		if ( ! $screen ) {
			wp_die( 'Display not found', 'Display Error', 404 );
		}

		// Update last ping
		$wpdb->update(
			$wpdb->prefix . 'poker_display_screens',
			array( 'last_ping' => current_time( 'mysql' ), 'is_online' => 1 ),
			array( 'screen_id' => $screen->screen_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		// Render template if template engine is available
		if ( $this->template_engine && $screen->html_template ) {
			$rendered_content = $this->template_engine->render_template(
				$screen->html_template,
				$screen->tournament_id
			);

			// Apply layout if available
			if ( $this->layout_builder && $screen->grid_config ) {
				$rendered_content = $this->layout_builder->apply_layout(
					$rendered_content,
					$screen->grid_config,
					$screen->component_positions
				);
			}

			// Include styles if available
			if ( $screen->css_styles ) {
				echo '<style>' . $screen->css_styles . '</style>';
			}

			echo $rendered_content;
		} else {
			// Enhanced fallback display with basic tournament information
			$this->render_fallback_display( $screen );
		}
	}

	/**
	 * Render tournament-specific display output
	 *
	 * @since 3.4.0
	 * @param string $display_id Display identifier.
	 * @param int    $tournament_id Tournament ID.
	 */
	public function render_tournament_display( $display_id, $tournament_id ) {
		global $wpdb;

		// Get screen configuration for tournament
		$screen = $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, t.html_template, t.css_styles, l.grid_config, l.component_positions
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id AND t.tournament_id = %d
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id AND l.tournament_id = %d
			 WHERE (s.endpoint_url = %s OR s.screen_name = %s)",
			$tournament_id, $tournament_id, $display_id, $display_id
		) );

		if ( ! $screen ) {
			wp_die( 'Display not found for tournament', 'Display Error', 404 );
		}

		// Update last ping
		$wpdb->update(
			$wpdb->prefix . 'poker_display_screens',
			array( 'last_ping' => current_time( 'mysql' ), 'is_online' => 1 ),
			array( 'screen_id' => $screen->screen_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		// Set headers for proper display
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Render template with tournament context
		if ( $this->template_engine && $screen->html_template ) {
			$rendered_content = $this->template_engine->render_template(
				$screen->html_template,
				$tournament_id
			);

			// Apply layout if available
			if ( $this->layout_builder && $screen->grid_config ) {
				$layout_config = json_decode( $screen->grid_config, true );
				$component_positions = json_decode( $screen->component_positions, true );
				$rendered_content = $this->layout_builder->apply_layout(
					$rendered_content,
					$layout_config,
					$component_positions
				);
			}

			// Include styles if available
			if ( $screen->css_styles ) {
				echo '<style>' . $screen->css_styles . '</style>';
			}

			echo $rendered_content;
		} else {
			// Fallback tournament display
			$tournament = get_post( $tournament_id );
			echo '<div class="tdwp-display tdwp-tournament-display" style="font-family: Arial, sans-serif; text-align: center; padding: 20px;">';
			echo '<h1>' . esc_html( $tournament ? $tournament->post_title : 'Tournament Display' ) . '</h1>';
			echo '<p>Tournament display is initializing...</p>';
			echo '</div>';
		}
	}

	/**
	 * Render preview display output
	 *
	 * @since 3.4.0
	 * @param string $preview_id Preview identifier.
	 */
	public function render_preview( $preview_id ) {
		// Parse preview ID to extract screen_id and tournament_id
		$preview_parts = explode( '-', $preview_id );
		$screen_id = isset( $preview_parts[0] ) ? intval( $preview_parts[0] ) : 0;
		$tournament_id = isset( $preview_parts[1] ) ? intval( $preview_parts[1] ) : 0;

		if ( ! $screen_id || ! $tournament_id ) {
			wp_die( 'Invalid preview parameters', 'Preview Error', 400 );
		}

		global $wpdb;

		// Get screen configuration
		$screen = $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, t.html_template, t.css_styles, l.grid_config, l.component_positions
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id AND t.tournament_id = %d
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id AND l.tournament_id = %d
			 WHERE s.screen_id = %d",
			$tournament_id, $tournament_id, $screen_id
		) );

		if ( ! $screen ) {
			wp_die( 'Preview not found', 'Preview Error', 404 );
		}

		// Set headers for preview
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Preview-Mode: active' );

		// Render preview with layout builder if available
		if ( $this->layout_builder && $screen->layout_id ) {
			$preview = $this->layout_builder->create_preview( $screen->layout_id, $tournament_id, array(
				'show_grid' => true,
				'live_data' => true,
				'auto_refresh' => false, // Disable auto-refresh for preview
			) );

			if ( $preview ) {
				echo '<div class="tdwp-preview-wrapper">';
				echo '<style>' . $preview['css'] . '</style>';
				echo $preview['html'];
				echo '<script>';
				echo 'window.addEventListener("load", function() {';
				echo '  console.log("TDWP Preview loaded for ' . esc_js( $screen->screen_name ) . '");';
				echo '});';
				echo '</script>';
				echo '</div>';
				return;
			}
		}

		// Fallback preview
		echo '<div class="tdwp-preview-fallback" style="font-family: Arial, sans-serif; text-align: center; padding: 20px; border: 2px dashed #ccc;">';
		echo '<h2>Preview: ' . esc_html( $screen->screen_name ) . '</h2>';
		echo '<p>Preview mode is not available for this configuration.</p>';
		echo '</div>';
	}

	/**
	 * Save display template via AJAX
	 *
	 * @since 3.4.0
	 */
	public function ajax_save_display_template() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$template_data = json_decode( stripslashes( $_POST['template_data'] ), true );

		if ( ! $template_data ) {
			wp_send_json_error( 'Invalid template data' );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'poker_display_templates',
			array(
				'tournament_id' => isset( $template_data['tournament_id'] ) ? intval( $template_data['tournament_id'] ) : null,
				'template_name' => sanitize_text_field( $template_data['template_name'] ),
				'template_type' => in_array( $template_data['template_type'], array( 'clock', 'rankings', 'prizes', 'seating', 'rules', 'custom' ) ) ? $template_data['template_type'] : 'custom',
				'html_template' => wp_kses_post( $template_data['html_template'] ),
				'css_styles' => wp_kses_post( $template_data['css_styles'] ),
				'tokens_used' => json_encode( $template_data['tokens_used'] ?? array() ),
				'is_default' => isset( $template_data['is_default'] ) ? (bool) $template_data['is_default'] : false,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			wp_send_json_success( array( 'template_id' => $wpdb->insert_id ) );
		} else {
			wp_send_json_error( 'Failed to save template' );
		}
	}

	/**
	 * Get display templates via AJAX
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_display_templates() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$tournament_id = isset( $_GET['tournament_id'] ) ? intval( $_GET['tournament_id'] ) : 0;
		$template_type = isset( $_GET['template_type'] ) ? sanitize_text_field( $_GET['template_type'] ) : '';

		global $wpdb;

		$where = array();
		$where_sql = '';

		if ( $tournament_id > 0 ) {
			$where[] = $wpdb->prepare( 'tournament_id = %d', $tournament_id );
		}

		if ( ! empty( $template_type ) ) {
			$where[] = $wpdb->prepare( 'template_type = %s', $template_type );
		}

		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$templates = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}poker_display_templates {$where_sql} ORDER BY template_name"
		);

		wp_send_json_success( $templates );
	}

	/**
	 * Save display layout via AJAX
	 *
	 * @since 3.4.0
	 */
	public function ajax_save_display_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_data = json_decode( stripslashes( $_POST['layout_data'] ), true );

		if ( ! $layout_data ) {
			wp_send_json_error( 'Invalid layout data' );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'poker_display_layouts',
			array(
				'tournament_id' => intval( $layout_data['tournament_id'] ),
				'layout_name' => sanitize_text_field( $layout_data['layout_name'] ),
				'screen_size' => sanitize_text_field( $layout_data['screen_size'] ?? '' ),
				'grid_config' => json_encode( $layout_data['grid_config'] ?? array() ),
				'component_positions' => json_encode( $layout_data['component_positions'] ?? array() ),
				'breakpoints' => json_encode( $layout_data['breakpoints'] ?? array() ),
				'is_active' => isset( $layout_data['is_active'] ) ? (bool) $layout_data['is_active'] : true,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			wp_send_json_success( array( 'layout_id' => $wpdb->insert_id ) );
		} else {
			wp_send_json_error( 'Failed to save layout' );
		}
	}

	/**
	 * Get display screens via AJAX
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_display_screens() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		global $wpdb;

		$screens = $wpdb->get_results(
			"SELECT s.*, t.template_name, l.layout_name
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id
			 ORDER BY s.screen_name"
		);

		wp_send_json_success( $screens );
	}

	/**
	 * Get screen status
	 *
	 * @since 3.4.0
	 * @param int $screen_id Screen ID.
	 * @return object|null Screen status data.
	 */
	public function get_screen_status( $screen_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}poker_display_screens WHERE screen_id = %d",
			$screen_id
		) );
	}

	/**
	 * Update screen status
	 *
	 * @since 3.4.0
	 * @param int    $screen_id Screen ID.
	 * @param array  $status_data Status data to update.
	 * @return bool Success status.
	 */
	public function update_screen_status( $screen_id, $status_data ) {
		global $wpdb;

		$allowed_fields = array( 'last_ping', 'is_online', 'location' );
		$update_data = array();
		$format = array();

		foreach ( $status_data as $field => $value ) {
			if ( in_array( $field, $allowed_fields ) ) {
				$update_data[ $field ] = $value;
				$format[] = is_bool( $value ) ? '%d' : '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return (bool) $wpdb->update(
			$wpdb->prefix . 'poker_display_screens',
			$update_data,
			array( 'screen_id' => $screen_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Register new display screen
	 *
	 * @since 3.4.0
	 * @param array $screen_data Screen configuration data.
	 * @return int|false Screen ID or false on failure.
	 */
	public function register_screen( $screen_data ) {
		global $wpdb;

		$screen_data = wp_parse_args( $screen_data, array(
			'screen_name' => '',
			'endpoint_url' => '',
			'screen_type' => 'custom',
			'location' => '',
			'tournament_id' => 0,
			'layout_id' => null,
			'template_id' => null,
		) );

		// Validate required fields
		if ( empty( $screen_data['screen_name'] ) ) {
			error_log( 'TDWP Display Manager: Screen registration failed - screen_name is empty' );
			return false;
		}

		if ( empty( $screen_data['endpoint_url'] ) ) {
			error_log( 'TDWP Display Manager: Screen registration failed - endpoint_url is empty for screen: ' . $screen_data['screen_name'] );
			return false;
		}

		error_log( 'TDWP Display Manager: Registering screen: ' . $screen_data['screen_name'] . ' with endpoint: ' . $screen_data['endpoint_url'] );

		// Generate unique endpoint URL if not provided
		if ( empty( $screen_data['endpoint_url'] ) ) {
			$screen_data['endpoint_url'] = $this->generate_unique_endpoint( $screen_data['screen_name'] );
		}

		// Validate endpoint uniqueness
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT screen_id FROM {$wpdb->prefix}poker_display_screens WHERE endpoint_url = %s",
			$screen_data['endpoint_url']
		) );

		if ( $existing ) {
			error_log( 'TDWP Display Manager: Screen registration failed - endpoint URL already exists: ' . $screen_data['endpoint_url'] );
			return false;
		}

		// Prepare insert data
		$insert_data = array(
			'screen_name' => sanitize_text_field( $screen_data['screen_name'] ),
			'endpoint_url' => sanitize_text_field( $screen_data['endpoint_url'] ),
			'screen_type' => in_array( $screen_data['screen_type'], array( 'clock', 'rankings', 'prizes', 'seating', 'custom', 'tournament' ) ) ? $screen_data['screen_type'] : 'custom',
			'location' => sanitize_text_field( $screen_data['screen_location'] ),
			'layout_id' => ! empty( $screen_data['layout_id'] ) ? intval( $screen_data['layout_id'] ) : null,
			'template_id' => ! empty( $screen_data['template_id'] ) ? intval( $screen_data['template_id'] ) : null,
			'is_online' => 0,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Insert the screen
		$result = $wpdb->insert(
			$wpdb->prefix . 'poker_display_screens',
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		error_log( 'TDWP Display Manager: Database insert result: ' . ($result ? 'SUCCESS' : 'FAILED') );

		if ( ! $result ) {
			error_log( 'TDWP Display Manager: Screen registration failed - database insert error: ' . $wpdb->last_error );
			return false;
		}

		$screen_id = $wpdb->insert_id;
		error_log( 'TDWP Display Manager: Screen registered successfully with ID: ' . $screen_id );

		// Trigger registration action
		do_action( 'tdwp_screen_registered', $screen_id, $screen_data );

		return $screen_id;
	}

	/**
	 * Update screen configuration
	 *
	 * @since 3.4.0
	 * @param int   $screen_id Screen ID.
	 * @param array $screen_data Updated screen data.
	 * @return bool Success status.
	 */
	public function update_screen( $screen_id, $screen_data ) {
		global $wpdb;

		$allowed_fields = array(
			'screen_name', 'endpoint_url', 'screen_type', 'location',
			'layout_id', 'template_id', 'is_online', 'tournament_id'
		);

		$update_data = array();
		$format = array();

		foreach ( $screen_data as $field => $value ) {
			if ( in_array( $field, $allowed_fields ) ) {
				switch ( $field ) {
					case 'screen_name':
					case 'endpoint_url':
					case 'location':
						$update_data[ $field ] = sanitize_text_field( $value );
						$format[] = '%s';
						break;
					case 'screen_type':
						$update_data[ $field ] = in_array( $value, array( 'clock', 'rankings', 'prizes', 'seating', 'custom' ) ) ? $value : 'custom';
						$format[] = '%s';
						break;
					case 'layout_id':
					case 'template_id':
					case 'tournament_id':
						$update_data[ $field ] = ! empty( $value ) ? intval( $value ) : null;
						$format[] = '%d';
						break;
					case 'is_online':
						$update_data[ $field ] = (bool) $value;
						$format[] = '%d';
						break;
				}
			}
		}

		if ( empty( $update_data ) ) {
			error_log( "TDWP Display Manager: Update screen {$screen_id} failed - no valid data provided" );
			return false;
		}

		// Log tournament assignment attempts
		if ( isset( $update_data['tournament_id'] ) ) {
			error_log( "TDWP Display Manager: Assigning screen {$screen_id} to tournament {$update_data['tournament_id']}" );
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$format[] = '%s';

		$result = $wpdb->update(
			$wpdb->prefix . 'poker_display_screens',
			$update_data,
			array( 'screen_id' => $screen_id ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			// Log successful assignment
			if ( isset( $update_data['tournament_id'] ) ) {
				error_log( "TDWP Display Manager: Successfully assigned screen {$screen_id} to tournament {$update_data['tournament_id']}" );
			}
			// Trigger update action
			do_action( 'tdwp_screen_updated', $screen_id, $update_data );
		} else {
			// Log failed assignment
			if ( isset( $update_data['tournament_id'] ) ) {
				error_log( "TDWP Display Manager: Failed to assign screen {$screen_id} to tournament {$update_data['tournament_id']}" );
			}
		}

		return $result !== false;
	}

	/**
	 * Delete screen
	 *
	 * @since 3.4.0
	 * @param int $screen_id Screen ID.
	 * @return bool Success status.
	 */
	public function delete_screen( $screen_id ) {
		global $wpdb;

		$screen = $this->get_screen_status( $screen_id );
		if ( ! $screen ) {
			return false;
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'poker_display_screens',
			array( 'screen_id' => $screen_id ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Trigger deletion action
			do_action( 'tdwp_screen_deleted', $screen_id, $screen );
		}

		return $result !== false;
	}

	/**
	 * Get all screens for a tournament
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return array List of screens.
	 */
	public function get_tournament_screens( $tournament_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, t.template_name, l.layout_name
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id AND t.tournament_id = %d
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id AND l.tournament_id = %d
			 WHERE 1=1
			 ORDER BY s.screen_name",
			$tournament_id, $tournament_id
		) );
	}

	/**
	 * Get all registered screens
	 *
	 * @since 3.4.0
	 * @param array $args Query arguments.
	 * @return array List of screens.
	 */
	public function get_all_screens( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'online_only' => false,
			'screen_type' => '',
			'orderby' => 'screen_name',
			'order' => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$joins = array(
			"LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id",
			"LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id",
			"LEFT JOIN {$wpdb->prefix}posts trn ON s.tournament_id = trn.ID"
		);

		if ( $args['online_only'] ) {
			$where_clauses[] = 's.is_online = 1';
		}

		if ( ! empty( $args['screen_type'] ) ) {
			$where_clauses[] = $wpdb->prepare( 's.screen_type = %s', $args['screen_type'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$join_sql = implode( ' ', $joins );
		$order_sql = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		$sql = "SELECT s.*, t.template_name, l.layout_name, trn.post_title as tournament_name
				FROM {$wpdb->prefix}poker_display_screens s
				{$join_sql}
				WHERE {$where_sql}
				ORDER BY {$order_sql}";

		$results = $wpdb->get_results( $sql );
		$screens = array();

		// Convert stdClass objects to associative arrays
		foreach ( $results as $screen ) {
			$screens[] = array(
				'screen_id' => $screen->screen_id,
				'screen_name' => $screen->screen_name,
				'endpoint_url' => $screen->endpoint_url,
				'screen_type' => $screen->screen_type,
				'location' => $screen->location,
				'tournament_id' => $screen->tournament_id,
				'layout_id' => $screen->layout_id,
				'template_id' => $screen->template_id,
				'is_active' => (bool) $screen->is_active,
				'is_online' => $screen->is_online,
				'last_ping' => $screen->last_ping,
				'created_at' => $screen->created_at,
				'updated_at' => $screen->updated_at,
				'template_name' => $screen->template_name,
				'layout_name' => $screen->layout_name,
				'tournament_name' => $screen->tournament_name ? $screen->tournament_name : '',
				// Add compatibility fields expected by render_screen_card
				'screen_location' => $screen->location,
			);
		}

		return $screens;
	}

	/**
	 * Generate unique endpoint URL
	 *
	 * @since 3.4.0
	 * @param string $screen_name Screen name.
	 * @return string Unique endpoint URL.
	 */
	private function generate_unique_endpoint( $screen_name ) {
		global $wpdb;

		$base = sanitize_title( $screen_name );
		$endpoint = $base;
		$counter = 1;

		while ( $wpdb->get_var( $wpdb->prepare(
			"SELECT screen_id FROM {$wpdb->prefix}poker_display_screens WHERE endpoint_url = %s",
			$endpoint
		) ) ) {
			$endpoint = $base . '-' . $counter;
			$counter++;
		}

		return $endpoint;
	}

	/**
	 * Get screen by endpoint URL
	 *
	 * @since 3.4.0
	 * @param string $endpoint_url Endpoint URL.
	 * @return object|null Screen data.
	 */
	public function get_screen_by_endpoint( $endpoint_url ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, t.template_name, l.layout_name, t.html_template, t.css_styles, l.layout_data
			 FROM {$wpdb->prefix}poker_display_screens s
			 LEFT JOIN {$wpdb->prefix}poker_display_templates t ON s.template_id = t.template_id
			 LEFT JOIN {$wpdb->prefix}poker_display_layouts l ON s.layout_id = l.layout_id
			 WHERE s.endpoint_url = %s",
			$endpoint_url
		) );
	}

	/**
	 * Assign layout to screen
	 *
	 * @since 3.4.0
	 * @param int $screen_id Screen ID.
	 * @param int $layout_id Layout ID.
	 * @return bool Success status.
	 */
	public function assign_layout_to_screen( $screen_id, $layout_id ) {
		return $this->update_screen( $screen_id, array( 'layout_id' => $layout_id ) );
	}

	/**
	 * Assign template to screen
	 *
	 * @since 3.4.0
	 * @param int $screen_id Screen ID.
	 * @param int $template_id Template ID.
	 * @return bool Success status.
	 */
	public function assign_template_to_screen( $screen_id, $template_id ) {
		return $this->update_screen( $screen_id, array( 'template_id' => $template_id ) );
	}

	/**
	 * Get screen health status
	 *
	 * @since 3.4.0
	 * @param int $screen_id Screen ID.
	 * @return array Health status information.
	 */
	public function get_screen_health( $screen_id ) {
		$screen = $this->get_screen_status( $screen_id );

		if ( ! $screen ) {
			return array(
				'status' => 'error',
				'message' => 'Screen not found',
				'online' => false,
				'last_ping' => null,
				'connection_quality' => 'unknown',
			);
		}

		$health = array(
			'status' => 'healthy',
			'message' => 'Screen is online and responding',
			'online' => (bool) $screen->is_online,
			'last_ping' => $screen->last_ping,
			'connection_quality' => 'unknown',
		);

		// Check if screen is offline
		if ( ! $screen->is_online ) {
			$health['status'] = 'offline';
			$health['message'] = 'Screen is offline';
			$health['connection_quality'] = 'poor';
		}

		// Check last ping time
		if ( $screen->last_ping ) {
			$time_diff = time() - strtotime( $screen->last_ping );

			if ( $time_diff > 300 ) { // 5 minutes
				$health['status'] = 'stale';
				$health['message'] = 'Screen has not responded recently';
				$health['connection_quality'] = 'poor';
			} elseif ( $time_diff > 60 ) { // 1 minute
				$health['status'] = 'warning';
				$health['message'] = 'Screen response delayed';
				$health['connection_quality'] = 'fair';
			} else {
				$health['connection_quality'] = 'good';
			}
		} else {
			$health['status'] = 'warning';
			$health['message'] = 'Screen has never responded';
			$health['connection_quality'] = 'unknown';
		}

		return apply_filters( 'tdwp_screen_health_status', $health, $screen_id );
	}

	/**
	 * Get all screens health status
	 *
	 * @since 3.4.0
	 * @return array All screens health information.
	 */
	public function get_all_screens_health() {
		$screens = $this->get_all_screens();
		$health_data = array();

		foreach ( $screens as $screen ) {
			$health_data[ $screen['screen_id'] ] = $this->get_screen_health( $screen['screen_id'] );
		}

		return $health_data;
	}

	/**
	 * Check rewrite rules status with comprehensive verification
	 *
	 * @since 3.4.1
	 * @return bool True if rewrite rules are properly registered and flushed
	 */
	private function check_rewrite_rules_status() {
		try {
			error_log( 'TDWP Display Manager: Starting rewrite rules status check' );

			// Method 1: Check if the option was set (our original fix)
			$option_check = get_option( 'tdwp_display_endpoints_flushed', false );
			if ( $option_check ) {
				error_log( 'TDWP Display Manager: Rewrite rules status OK via option check' );
				return true;
			}

			// Method 2: Check if rewrite rules were actually registered via pattern matching
			$rewrite_rules = get_option( 'rewrite_rules' );
			if ( is_array( $rewrite_rules ) ) {
				foreach ( $rewrite_rules as $pattern => $substitution ) {
					if ( strpos( $pattern, 'tdwp-display' ) !== false ) {
						error_log( 'TDWP Display Manager: Rewrite rules status OK via pattern detection' );
						return true;
					}
				}
			}

			// Method 3: Safe query variable detection using WordPress globals
			global $wp;
			if ( isset( $wp->public_query_vars ) && is_array( $wp->public_query_vars ) ) {
				if ( in_array( 'tdwp_display', $wp->public_query_vars ) ) {
					error_log( 'TDWP Display Manager: Rewrite rules status OK via public query vars detection' );
					return true;
				}
			}

			// Method 4: Check rewrite rules directly from WP_Rewrite object (safe approach)
			global $wp_rewrite;
			if ( isset( $wp_rewrite->rules ) && is_array( $wp_rewrite->rules ) ) {
				foreach ( $wp_rewrite->rules as $pattern => $substitution ) {
					if ( strpos( $pattern, 'tdwp-display' ) !== false ) {
						error_log( 'TDWP Display Manager: Rewrite rules status OK via WP_Rewrite rules detection' );
						return true;
					}
				}
			}

			error_log( 'TDWP Display Manager: Rewrite rules status FAILED - no evidence of registration' );
			return false;

		} catch ( Exception $e ) {
			error_log( 'TDWP Display Manager: Rewrite rules check error: ' . $e->getMessage() );
			error_log( 'TDWP Display Manager: Error trace: ' . $e->getTraceAsString() );
			return false; // Safe fallback
		}
	}

	/**
	 * Check shortcode registration status with comprehensive verification
	 *
	 * @since 3.4.1
	 * @return bool True if display shortcodes are properly registered
	 */
	private function check_shortcode_registration_status() {
		try {
			error_log( 'TDWP Display Manager: Starting shortcode registration status check' );

			// Method 1: Primary check - Direct shortcode existence
			if ( shortcode_exists( 'tdwp_tournament_display' ) ) {
				error_log( 'TDWP Display Manager: Shortcode status OK via direct check' );
				return true;
			}

			// Method 2: Check shortcode tags directly with proper validation
			global $shortcode_tags;
			if ( isset( $shortcode_tags ) && is_array( $shortcode_tags ) ) {
				if ( isset( $shortcode_tags['tdwp_tournament_display'] ) && is_callable( $shortcode_tags['tdwp_tournament_display'] ) ) {
					error_log( 'TDWP Display Manager: Shortcode status OK via tags array check' );
					return true;
				}
			}

			// Method 3: Check if Display Shortcode class exists and is instantiated
			if ( class_exists( 'TDWP_Display_Shortcode' ) ) {
				error_log( 'TDWP Display Manager: Shortcode status OK via Display Shortcode class existence' );
				return true;
			}

			// Method 4: Check if Display Manager has shortcode registration capability
			if ( class_exists( 'TDWP_Display_Manager' ) ) {
				try {
					$display_manager = TDWP_Display_Manager::get_instance();
					if ( method_exists( $display_manager, 'register_display_shortcodes' ) ) {
						error_log( 'TDWP Display Manager: Shortcode status OK via method existence check' );
						return true;
					}
				} catch ( Exception $e ) {
					error_log( 'TDWP Display Manager: Warning - Display Manager instantiation failed: ' . $e->getMessage() );
				}
			}

			error_log( 'TDWP Display Manager: Shortcode status FAILED - no evidence of registration' );
			return false;

		} catch ( Exception $e ) {
			error_log( 'TDWP Display Manager: Shortcode registration check error: ' . $e->getMessage() );
			error_log( 'TDWP Display Manager: Error trace: ' . $e->getTraceAsString() );
			return false; // Safe fallback
		}
	}

	/**
	 * Get aggregated system health status
	 *
	 * @since 3.4.1
	 * @return array Aggregated health status information for admin interface.
	 */
	public function get_system_health_status() {
		$screens = $this->get_all_screens();
		$individual_health = $this->get_all_screens_health();

		$total_screens = count( $screens );
		$online_screens = 0;
		$total_quality = 0;
		$quality_counts = array(
			'excellent' => 0,
			'good' => 0,
			'fair' => 0,
			'poor' => 0
		);

		foreach ( $individual_health as $health ) {
			// Check online status (fix key name inconsistency)
			if ( isset( $health['online'] ) && $health['online'] ) {
				$online_screens++;
			} elseif ( isset( $health['is_online'] ) && $health['is_online'] ) {
				$online_screens++;
			}

			if ( isset( $health['connection_quality'] ) ) {
				// Convert string quality to numeric value
				$quality_numeric = $this->convert_quality_to_numeric( $health['connection_quality'] );
				$total_quality += $quality_numeric;

				// Categorize quality by string value
				if ( $health['connection_quality'] === 'good' ) {
					$quality_counts['excellent']++; // Good counts as excellent in this context
				} elseif ( $health['connection_quality'] === 'fair' ) {
					$quality_counts['good']++;
				} elseif ( $health['connection_quality'] === 'poor' ) {
					$quality_counts['fair']++;
				} else {
					$quality_counts['poor']++; // unknown and other poor qualities
				}
			}
		}

		$avg_quality = $total_screens > 0 ? round( $total_quality / $total_screens ) : 0;

		// Calculate quality breakdown percentages
		$quality_breakdown = array();
		foreach ( $quality_counts as $quality => $count ) {
			$quality_breakdown[ $quality ] = $total_screens > 0 ? round( ( $count / $total_screens ) * 100 ) : 0;
		}

		// Determine overall system health
		$system_health = 'healthy';
		if ( $total_screens === 0 ) {
			$system_health = 'warning'; // No screens configured
		} elseif ( $online_screens === 0 ) {
			$system_health = 'error'; // All screens offline
		} elseif ( $online_screens < $total_screens * 0.5 ) {
			$system_health = 'warning'; // Less than 50% online
		}

		return array(
			'system_health' => $system_health,
			'total_screens' => $total_screens,
			'online_screens' => $online_screens,
			'avg_connection_quality' => $avg_quality,
			'quality_breakdown' => $quality_breakdown,
			'heartbeat_active' => class_exists( 'TDWP_Heartbeat_Manager' ),
			'rewrite_rules_active' => $this->check_rewrite_rules_status(),
			'shortcodes_registered' => $this->check_shortcode_registration_status()
		);
	}

	/**
	 * Convert string quality rating to numeric value
	 *
	 * @since 3.4.1
	 * @param string $quality String quality rating.
	 * @return int Numeric quality value (0-100).
	 */
	private function convert_quality_to_numeric( $quality ) {
		$quality_map = array(
			'good' => 95,
			'fair' => 65,
			'poor' => 25,
			'unknown' => 0,
			'excellent' => 100,
		);

		return isset( $quality_map[ $quality ] ) ? $quality_map[ $quality ] : 0;
	}

	/**
	 * AJAX handler for screen registration
	 *
	 * @since 3.4.0
	 */
	public function ajax_register_screen() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$screen_data = json_decode( stripslashes( $_POST['screen_data'] ), true );

		if ( ! $screen_data ) {
			wp_send_json_error( 'Invalid screen data' );
		}

		$screen_id = $this->register_screen( $screen_data );

		if ( ! $screen_id ) {
			wp_send_json_error( 'Failed to register screen' );
		}

		wp_send_json_success( array(
			'screen_id' => $screen_id,
			'message' => 'Screen registered successfully',
		) );
	}

	/**
	 * AJAX handler for screen update
	 *
	 * @since 3.4.0
	 */
	public function ajax_update_screen() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$screen_id = intval( $_POST['screen_id'] );
		$screen_data = json_decode( stripslashes( $_POST['screen_data'] ), true );

		if ( ! $screen_data ) {
			wp_send_json_error( 'Invalid screen data' );
		}

		$success = $this->update_screen( $screen_id, $screen_data );

		if ( ! $success ) {
			wp_send_json_error( 'Failed to update screen' );
		}

		wp_send_json_success( array(
			'message' => 'Screen updated successfully',
		) );
	}

	/**
	 * AJAX handler for screen deletion
	 *
	 * @since 3.4.0
	 */
	public function ajax_delete_screen() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$screen_id = intval( $_POST['screen_id'] );

		$success = $this->delete_screen( $screen_id );

		if ( ! $success ) {
			wp_send_json_error( 'Failed to delete screen' );
		}

		wp_send_json_success( array(
			'message' => 'Screen deleted successfully',
		) );
	}

	/**
	 * AJAX handler for getting screen health status
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_screen_health() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$screen_id = intval( $_POST['screen_id'] );
		$health = $this->get_screen_health( $screen_id );

		wp_send_json_success( $health );
	}

	/**
	 * AJAX handler for getting all screens health status
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_all_screens_health() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_display_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$health_data = $this->get_all_screens_health();

		wp_send_json_success( $health_data );
	}

	/**
	 * Get currently running tournaments for display assignment
	 *
	 * @since 3.4.1
	 * @return array Array of running tournaments with basic info
	 */
	public function get_running_tournaments_for_display() {
		global $wpdb;
		$tournaments = array();

		// First try to get tournaments from live state table (primary source)
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$live_states = $wpdb->get_results(
			"SELECT * FROM {$live_state_table} WHERE status IN ('setup', 'running', 'paused', 'break') ORDER BY created_at DESC"
		);

		foreach ( $live_states as $live_state ) {
			// Try to get tournament name from posts table
			$tournament_name = $this->get_tournament_name_by_id( $live_state->tournament_id );

			$tournaments[] = array(
				'id' => $live_state->tournament_id,
				'title' => $tournament_name,
				'status' => $live_state->status,
				'is_running' => true, // All from live state table are considered running
				'live_tournament_id' => $live_state->tournament_id,
				'is_practice' => (bool) $live_state->is_practice,
				'total_players' => intval( $live_state->total_players ),
				'remaining_players' => intval( $live_state->remaining_players ),
				'started_at' => $live_state->started_at,
			);
		}

		// Fallback: try traditional method if no live state tournaments found
		if ( empty( $tournaments ) && class_exists( 'TDWP_Active_Tournament_Manager' ) ) {
			$running_tournaments = TDWP_Active_Tournament_Manager::get_running_tournaments();

			foreach ( $running_tournaments as $tournament ) {
				$status = $this->get_tournament_live_status( $tournament->ID );

				if ( in_array( $status, array( 'setup', 'running', 'paused', 'break' ) ) ) {
					$tournaments[] = array(
						'id' => $tournament->ID,
						'title' => $tournament->post_title,
						'status' => $status,
						'is_running' => true,
						'live_tournament_id' => get_post_meta( $tournament->ID, '_live_tournament_id', true ),
					);
				}
			}
		}

		error_log( 'TDWP Display Manager: Found ' . count( $tournaments ) . ' tournaments for display assignment' );
		return $tournaments;
	}

	/**
	 * Get tournament name by tournament ID
	 *
	 * @since 3.4.1
	 * @param int $tournament_id Tournament ID.
	 * @return string Tournament name
	 */
	private function get_tournament_name_by_id( $tournament_id ) {
		global $wpdb;

		// Try to find tournament in posts table first
		$post = $wpdb->get_row( $wpdb->prepare(
			"SELECT post_title FROM {$wpdb->posts} WHERE ID = %d AND post_type IN ('tournament', 'live_tournament')",
			$tournament_id
		) );

		if ( $post && ! empty( $post->post_title ) ) {
			return $post->post_title;
		}

		// Try to get name from tournament meta data
		$meta_title = get_post_meta( $tournament_id, '_tournament_name', true );
		if ( $meta_title ) {
			return $meta_title;
		}

		// Fallback: generate a descriptive name
		return sprintf( __( 'Tournament %s', 'poker-tournament-import' ), $tournament_id );
	}

	/**
	 * Get tournament live status
	 *
	 * @since 3.4.1
	 * @param int $tournament_id Tournament ID.
	 * @return string Tournament status
	 */
	private function get_tournament_live_status( $tournament_id ) {
		if ( ! class_exists( 'TDWP_Tournament_Live' ) ) {
			return 'unknown';
		}

		$live_manager = TDWP_Tournament_Live::get_instance();
		$live_state = $live_manager->get_by_tournament_id( $tournament_id );

		if ( $live_state ) {
			return $live_state->status;
		}

		// Check tournament meta as fallback
		$status = get_post_meta( $tournament_id, '_status', true );
		return $status ?: 'pending';
	}

	/**
	 * Auto-assign screens to running tournaments
	 *
	 * @since 3.4.1
	 * @param int $screen_id Screen ID to auto-assign.
	 * @return bool True on success
	 */
	public function auto_assign_to_running_tournament( $screen_id ) {
		error_log( "TDWP Display Manager: Auto-assigning screen {$screen_id} to running tournament" );
		$running_tournaments = $this->get_running_tournaments_for_display();

		error_log( "TDWP Display Manager: Found " . count( $running_tournaments ) . " tournaments for auto-assignment" );
		foreach ( $running_tournaments as $tournament ) {
			error_log( "TDWP Display Manager: Available tournament: ID {$tournament['id']}, Title '{$tournament['title']}', Status '{$tournament['status']}', Running: " . ($tournament['is_running'] ? 'YES' : 'NO') );
		}

		if ( empty( $running_tournaments ) ) {
			error_log( "TDWP Display Manager: Auto-assign failed - no running tournaments available" );
			return false;
		}

		// Priority: 1) Running tournaments (most active), 2) Paused tournaments (temporarily stopped), 3) Setup tournaments (not started yet), 4) Any available tournament
		foreach ( $running_tournaments as $tournament ) {
			if ( $tournament['is_running'] && $tournament['status'] === 'running' ) {
				error_log( "TDWP Display Manager: Auto-assigning to RUNNING tournament {$tournament['id']} ({$tournament['title']})" );
				$result = $this->update_screen( $screen_id, array( 'tournament_id' => $tournament['id'] ) );
				error_log( "TDWP Display Manager: Auto-assign to running tournament result: " . ($result ? 'SUCCESS' : 'FAILED') );
				return $result;
			}
		}

		// Then prefer paused tournaments (active but temporarily stopped)
		foreach ( $running_tournaments as $tournament ) {
			if ( $tournament['is_running'] && $tournament['status'] === 'paused' ) {
				error_log( "TDWP Display Manager: Auto-assigning to PAUSED tournament {$tournament['id']} ({$tournament['title']})" );
				$result = $this->update_screen( $screen_id, array( 'tournament_id' => $tournament['id'] ) );
				error_log( "TDWP Display Manager: Auto-assign to paused tournament result: " . ($result ? 'SUCCESS' : 'FAILED') );
				return $result;
			}
		}

		// Finally fallback to setup tournaments (not started yet)
		foreach ( $running_tournaments as $tournament ) {
			if ( $tournament['is_running'] && $tournament['status'] === 'setup' ) {
				error_log( "TDWP Display Manager: Auto-assigning to SETUP tournament {$tournament['id']} ({$tournament['title']})" );
				$result = $this->update_screen( $screen_id, array( 'tournament_id' => $tournament['id'] ) );
				error_log( "TDWP Display Manager: Auto-assign to setup tournament result: " . ($result ? 'SUCCESS' : 'FAILED') );
				return $result;
			}
		}

		// If no running/paused/setup tournament, use the first available one
		$first_tournament = reset( $running_tournaments );
		error_log( "TDWP Display Manager: Auto-assigning to FALLBACK tournament {$first_tournament['id']} ({$first_tournament['title']})" );
		$result = $this->update_screen( $screen_id, array( 'tournament_id' => $first_tournament['id'] ) );
		error_log( "TDWP Display Manager: Auto-assign to fallback tournament result: " . ($result ? 'SUCCESS' : 'FAILED') );
		return $result;
	}

	/**
	 * Get active tournament for current user
	 *
	 * @since 3.4.1
	 * @return int|false Tournament ID or false if none
	 */
	public function get_user_active_tournament() {
		if ( ! class_exists( 'TDWP_Active_Tournament_Manager' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return TDWP_Active_Tournament_Manager::get_active_tournament( $user_id );
	}

	/**
	 * Update screen when tournament status changes
	 *
	 * @since 3.4.1
	 * @param int $tournament_id Tournament ID.
	 * @param string $old_status Previous tournament status.
	 * @param string $new_status New tournament status.
	 * @return bool True on success
	 */
	public function handle_tournament_status_change( $tournament_id, $old_status, $new_status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'poker_display_screens';

		// Log tournament status changes for debugging
		error_log( "TDWP Display Manager: Tournament {$tournament_id} status changed from {$old_status} to {$new_status}" );

		// Get all screens assigned to this tournament
		$screens = $wpdb->get_results( $wpdb->prepare(
			"SELECT screen_id FROM {$table_name} WHERE tournament_id = %d",
			$tournament_id
		) );

		foreach ( $screens as $screen ) {
			// Update screen's last activity and health status
			$this->update_screen_health( $screen->screen_id, array(
				'tournament_status' => $new_status,
				'timestamp' => current_time( 'mysql' )
			) );

			// Trigger display refresh for connected screens
			do_action( 'tdwp_tournament_status_changed', $tournament_id, $old_status, $new_status, $screen->screen_id );
		}

		return true;
	}

	/**
	 * Get tournament options for screen assignment dropdown
	 *
	 * @since 3.4.1
	 * @return array Formatted options for select dropdown
	 */
	public function get_tournament_options_for_screens() {
		$tournaments = $this->get_running_tournaments_for_display();
		$options = array();

		// Add "Auto-detect running tournament" option
		$options[] = array(
			'id' => 'auto',
			'title' => __( 'Auto-detect running tournament', 'poker-tournament-import' ),
			'description' => __( 'Automatically connect to the currently running tournament', 'poker-tournament-import' ),
			'status' => 'auto'
		);

		// Add manual tournament options
		foreach ( $tournaments as $tournament ) {
			// Enhanced status labels
			$status_labels = array(
				'setup' => __( 'Planned', 'poker-tournament-import' ),
				'running' => __( 'Running', 'poker-tournament-import' ),
				'paused' => __( 'Paused', 'poker-tournament-import' ),
				'break' => __( 'On Break', 'poker-tournament-import' )
			);
			$status_label = isset( $status_labels[$tournament['status']] ) ? $status_labels[$tournament['status']] : ucfirst( $tournament['status'] );

			// Add additional info for live state tournaments
			$extra_info = array();
			if ( isset( $tournament['total_players'] ) && $tournament['total_players'] > 0 ) {
				$extra_info[] = sprintf( __( '%d Players', 'poker-tournament-import' ), $tournament['total_players'] );
			}
			if ( isset( $tournament['is_practice'] ) && $tournament['is_practice'] ) {
				$extra_info[] = __( 'Practice', 'poker-tournament-import' );
			}

			$description_parts = array( $tournament['title'], $status_label );
			if ( ! empty( $extra_info ) ) {
				$description_parts[] = implode( ' | ', $extra_info );
			}

			$description = implode( ' - ', $description_parts );

			$options[] = array(
				'id' => $tournament['id'],
				'title' => $tournament['title'],
				'description' => $description,
				'status' => $tournament['status'],
				'is_running' => $tournament['is_running']
			);
		}

		return $options;
	}

	/**
	 * AJAX handler for getting tournament options
	 *
	 * @since 3.4.1
	 */
	public function ajax_get_tournament_options() {
		check_ajax_referer( 'tdwp_display_nonce', 'nonce' );
		current_user_can( 'manage_options' ) || wp_die( 'Security check failed' );

		$options = $this->get_tournament_options_for_screens();

		wp_send_json_success( array(
			'options' => $options
		) );
	}

	/**
	 * AJAX handler for auto-assigning screen to running tournament
	 *
	 * @since 3.4.1
	 */
	public function ajax_auto_assign_screen() {
		check_ajax_referer( 'tdwp_display_nonce', 'nonce' );
		current_user_can( 'manage_options' ) || wp_die( 'Security check failed' );

		$screen_id = intval( $_POST['screen_id'] );
		if ( ! $screen_id ) {
			wp_send_json_error( 'Invalid screen ID' );
		}

		$result = $this->auto_assign_to_running_tournament( $screen_id );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Screen successfully assigned to running tournament', 'poker-tournament-import' )
			) );
		} else {
			wp_send_json_error( __( 'No running tournaments found', 'poker-tournament-import' ) );
		}
	}

	/**
	 * Render fallback display when template engine or templates are not available
	 *
	 * @since 3.4.0
	 * @param object $screen Screen configuration object
	 */
	private function render_fallback_display( $screen ) {
		global $wpdb;

		// Set headers for proper display
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Get basic tournament information if tournament_id is set
		$tournament_info = null;
		if ( ! empty( $screen->tournament_id ) ) {
			$tournament_info = $this->get_basic_tournament_info( $screen->tournament_id );
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $screen->screen_name ); ?> - TD3 Display</title>
			<style>
				body {
					margin: 0;
					padding: 20px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					background: #000;
					color: #fff;
					text-align: center;
				}
				.tdwp-display-container {
					max-width: 1200px;
					margin: 0 auto;
				}
				.tdwp-screen-title {
					font-size: 3em;
					margin-bottom: 30px;
					color: #00ff00;
					text-shadow: 0 0 10px #00ff00;
				}
				.tdwp-tournament-info {
					background: rgba(255, 255, 255, 0.1);
					padding: 30px;
					border-radius: 10px;
					margin: 20px 0;
					backdrop-filter: blur(10px);
				}
				.tdwp-tournament-name {
					font-size: 2em;
					margin-bottom: 20px;
					color: #fff;
				}
				.tdwp-info-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 20px;
					margin-top: 20px;
				}
				.tdwp-info-item {
					background: rgba(0, 255, 0, 0.1);
					padding: 15px;
					border-radius: 5px;
					border: 1px solid #00ff00;
				}
				.tdwp-info-label {
					font-size: 0.9em;
					color: #00ff00;
					margin-bottom: 5px;
				}
				.tdwp-info-value {
					font-size: 1.2em;
					color: #fff;
				}
				.tdwp-status {
					margin-top: 30px;
					font-size: 1.2em;
					color: #00ff00;
				}
				.tdwp-no-tournament {
					font-size: 1.5em;
					color: #ffff00;
					margin: 50px 0;
				}
			</style>
		</head>
		<body>
			<div class="tdwp-display-container">
				<h1 class="tdwp-screen-title"><?php echo esc_html( $screen->screen_name ); ?></h1>

				<?php if ( $tournament_info ) : ?>
					<div class="tdwp-tournament-info">
						<div class="tdwp-tournament-name"><?php echo esc_html( $tournament_info['name'] ); ?></div>

						<div class="tdwp-info-grid">
							<div class="tdwp-info-item">
								<div class="tdwp-info-label"><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></div>
								<div class="tdwp-info-value"><?php echo esc_html( $tournament_info['status'] ); ?></div>
							</div>

							<div class="tdwp-info-item">
								<div class="tdwp-info-label"><?php esc_html_e( 'Players', 'poker-tournament-import' ); ?></div>
								<div class="tdwp-info-value"><?php echo esc_html( $tournament_info['players'] ); ?></div>
							</div>

							<div class="tdwp-info-item">
								<div class="tdwp-info-label"><?php esc_html_e( 'Blind Level', 'poker-tournament-import' ); ?></div>
								<div class="tdwp-info-value"><?php echo esc_html( $tournament_info['blind_level'] ); ?></div>
							</div>

							<div class="tdwp-info-item">
								<div class="tdwp-info-label"><?php esc_html_e( 'Clock', 'poker-tournament-import' ); ?></div>
								<div class="tdwp-info-value"><?php echo esc_html( $tournament_info['clock'] ); ?></div>
							</div>
						</div>
					</div>
				<?php else : ?>
					<div class="tdwp-no-tournament">
						<?php esc_html_e( 'No tournament assigned to this display', 'poker-tournament-import' ); ?>
						<br>
						<?php esc_html_e( 'Please assign a tournament in the Display Manager', 'poker-tournament-import' ); ?>
					</div>
				<?php endif; ?>

				<div class="tdwp-status">
					<?php
					if ( $this->template_engine ) {
						esc_html_e( 'Template Engine: Loaded | Layout Builder: ', 'poker-tournament-import' );
						echo $this->layout_builder ? esc_html__( 'Loaded', 'poker-tournament-import' ) : esc_html__( 'Not Available', 'poker-tournament-import' );
					} else {
						esc_html_e( 'Template Engine: Not Loaded | Using fallback display', 'poker-tournament-import' );
					}
					?>
				</div>
			</div>

			<script>
			// Auto-refresh every 30 seconds
			setTimeout(function() {
				window.location.reload();
			}, 30000);
			</script>
		</body>
		</html>
		<?php
	}

	/**
	 * Get basic tournament information for fallback display
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID
	 * @return array|null Tournament information
	 */
	private function get_basic_tournament_info( $tournament_id ) {
		global $wpdb;

		// Try live state table first
		$live_state = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tdwp_tournament_live_state WHERE tournament_id = %d",
			$tournament_id
		) );

		if ( $live_state ) {
			return array(
				'name' => $live_state->tournament_name,
				'status' => ucfirst( $live_state->status ),
				'players' => $live_state->players_remaining . '/' . $live_state->total_players,
				'blind_level' => $live_state->current_blind,
				'clock' => $this->format_time_display( $live_state->time_remaining )
			);
		}

		// Fallback to posts table
		$post = $wpdb->get_row( $wpdb->prepare(
			"SELECT post_title, meta_value FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.ID = %d AND pm.meta_key = 'tournament_status'",
			$tournament_id
		) );

		if ( $post ) {
			return array(
				'name' => $post->post_title,
				'status' => get_post_meta( $tournament_id, 'tournament_status', true ) ?: 'Unknown',
				'players' => get_post_meta( $tournament_id, 'total_players', true ) ?: '0',
				'blind_level' => get_post_meta( $tournament_id, 'current_blind', true ) ?: 'N/A',
				'clock' => get_post_meta( $tournament_id, 'clock_time', true ) ?: 'N/A'
			);
		}

		return null;
	}

	/**
	 * Format time display for fallback
	 *
	 * @since 3.4.0
	 * @param int $seconds Time in seconds
	 * @return string Formatted time
	 */
	private function format_time_display( $seconds ) {
		if ( ! $seconds || $seconds < 0 ) {
			return '00:00';
		}

		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;

		return sprintf( '%02d:%02d', $minutes, $seconds );
	}
}