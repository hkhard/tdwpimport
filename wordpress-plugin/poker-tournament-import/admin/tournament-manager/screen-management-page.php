<?php
/**
 * TD3 Integration: Screen Management Page
 *
 * Admin interface for managing display screens and configurations
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Screen Management Page Class
 *
 * Handles admin interface for display screen management
 *
 * @since 3.4.0
 */
class TDWP_Screen_Management_Page {

    /**
     * Constructor
     *
     * @since 3.4.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_tdwp_screen_management', array($this, 'ajax_handler'));
    }

    /**
     * Add admin menu page
     *
     * @since 3.4.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tdwp-tournament-manager',
            __('Display Screens', 'poker-tournament-import'),
            __('Display Screens', 'poker-tournament-import'),
            'manage_options',
            'tdwp-screen-management',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts and styles
     *
     * @since 3.4.0
     * @param string $hook Current admin page.
     */
    public function enqueue_scripts($hook) {
        if ('tournament-manager_page_tdwp-screen-management' !== $hook) {
            return;
        }

        // Enqueue WordPress jQuery UI styles
        wp_enqueue_style('wp-jquery-ui-dialog');

        wp_enqueue_style('tdwp-screen-management',
            plugin_dir_url(dirname(dirname(__FILE__))) . '/admin/assets/css/screen-management.css',
            array('wp-jquery-ui-dialog'),
            '3.4.0'
        );

        wp_enqueue_script('tdwp-screen-management',
            plugin_dir_url(dirname(dirname(__FILE__))) . '/admin/assets/js/screen-management.js',
            array('jquery', 'jquery-ui-dialog', 'jquery-ui-tabs', 'jquery-effects-core'),
            '3.4.0',
            true
        );

        wp_localize_script('tdwp-screen-management', 'tdwp_screen_mgmt', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tdwp_screen_management_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this screen?', 'poker-tournament-import'),
            'confirm_unregister' => __('Are you sure you want to unregister this screen?', 'poker-tournament-import'),
            'auto_detect_text' => __('Searching for running tournaments...', 'poker-tournament-import'),
            'no_running_tournaments' => __('No running tournaments found', 'poker-tournament-import'),
            'tournament_assigned' => __('Screen assigned to running tournament', 'poker-tournament-import'),
        ));
    }

    /**
     * Render main page
     *
     * @since 3.4.0
     */
    public function render_page() {
        ?>
        <div class="wrap tdwp-screen-management">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="tdwp-screen-toolbar">
                <button type="button" class="button button-primary" id="add-new-screen">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add New Screen', 'poker-tournament-import'); ?>
                </button>

                <button type="button" class="button" id="refresh-screens">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh Status', 'poker-tournament-import'); ?>
                </button>

                <div class="screen-stats">
                    <span class="stat-item">
                        <span class="stat-label"><?php _e('Online:', 'poker-tournament-import'); ?></span>
                        <span class="stat-value online-count">0</span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-label"><?php _e('Offline:', 'poker-tournament-import'); ?></span>
                        <span class="stat-value offline-count">0</span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-label"><?php _e('Total:', 'poker-tournament-import'); ?></span>
                        <span class="stat-value total-count">0</span>
                    </span>
                </div>
            </div>

            <div id="screen-management-tabs">
                <ul>
                    <li><a href="#active-screens"><?php _e('Active Screens', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#screen-templates"><?php _e('Screen Templates', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#system-health"><?php _e('System Health', 'poker-tournament-import'); ?></a></li>
                </ul>

                <div id="active-screens">
                    <?php $this->render_screens_list(); ?>
                </div>

                <div id="screen-templates">
                    <?php $this->render_templates_list(); ?>
                </div>

                <div id="system-health">
                    <?php $this->render_system_health(); ?>
                </div>
            </div>
        </div>

        <!-- Add/Edit Screen Dialog -->
        <div id="screen-dialog" class="hidden" title="<?php esc_attr_e('Screen Configuration', 'poker-tournament-import'); ?>">
            <form id="screen-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="screen-name"><?php _e('Screen Name', 'poker-tournament-import'); ?></label>
                        <input type="text" id="screen-name" name="screen_name" class="regular-text" required>
                    </div>

                    <div class="form-group">
                        <label for="screen-location"><?php _e('Location', 'poker-tournament-import'); ?></label>
                        <input type="text" id="screen-location" name="screen_location" class="regular-text">
                    </div>

                    <div class="form-group">
                        <label for="screen-type"><?php _e('Screen Type', 'poker-tournament-import'); ?></label>
                        <select id="screen-type" name="screen_type">
                            <option value="tournament"><?php _e('Tournament Display', 'poker-tournament-import'); ?></option>
                            <option value="leaderboard"><?php _e('Leaderboard Only', 'poker-tournament-import'); ?></option>
                            <option value="clock"><?php _e('Clock Only', 'poker-tournament-import'); ?></option>
                            <option value="custom"><?php _e('Custom Layout', 'poker-tournament-import'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tournament-id"><?php _e('Assigned Tournament', 'poker-tournament-import'); ?></label>
                        <div class="tournament-selection">
                            <select id="tournament-id" name="tournament_id">
                                <option value=""><?php _e('Select Tournament...', 'poker-tournament-import'); ?></option>
                            </select>
                            <button type="button" class="button button-secondary" id="auto-detect-tournament">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Auto-Detect Running', 'poker-tournament-import'); ?>
                            </button>
                            <div class="tournament-status" id="tournament-status"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="layout-id"><?php _e('Layout Template', 'poker-tournament-import'); ?></label>
                        <select id="layout-id" name="layout_id">
                            <option value=""><?php _e('Default Layout', 'poker-tournament-import'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="refresh-rate"><?php _e('Refresh Rate (seconds)', 'poker-tournament-import'); ?></label>
                        <input type="number" id="refresh-rate" name="refresh_rate" min="1" max="300" value="30">
                    </div>

                    <div class="form-group">
                        <label for="screen-description"><?php _e('Description', 'poker-tournament-import'); ?></label>
                        <textarea id="screen-description" name="screen_description" rows="3" class="large-text"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="screen-active" name="is_active" value="1" checked>
                            <?php _e('Screen Active', 'poker-tournament-import'); ?>
                        </label>
                    </div>
                </div>

                <input type="hidden" id="screen-id" name="screen_id">
                <?php wp_nonce_field('tdwp_save_screen', 'screen_nonce'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render screens list
     *
     * @since 3.4.0
     */
    private function render_screens_list() {
        if (!class_exists('TDWP_Display_Manager')) {
            echo '<div class="notice notice-warning"><p>' .
                __('Display Manager not available. Please ensure TD3 Display System is properly installed.', 'poker-tournament-import') .
                '</p></div>';
            return;
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $screens = $display_manager->get_all_screens();

        if (empty($screens)) {
            echo '<div class="no-screens">';
            echo '<p>' . __('No display screens configured.', 'poker-tournament-import') . '</p>';
            echo '<button type="button" class="button button-primary" id="add-first-screen">' .
                __('Add Your First Screen', 'poker-tournament-import') . '</button>';
            echo '</div>';
            return;
        }

        echo '<div class="screens-grid">';
        foreach ($screens as $screen) {
            $this->render_screen_card($screen);
        }
        echo '</div>';
    }

    /**
     * Render individual screen card
     *
     * @since 3.4.0
     * @param array $screen Screen data.
     */
    private function render_screen_card($screen) {
        $status_class = $screen['is_online'] ? 'online' : 'offline';
        $status_text = $screen['is_online'] ? __('Online', 'poker-tournament-import') : __('Offline', 'poker-tournament-import');
        $last_seen = $screen['last_ping'] ? human_time_diff(strtotime($screen['last_ping']), current_time('timestamp')) . ' ' . __('ago', 'poker-tournament-import') : __('Never', 'poker-tournament-import');

        $preview_url = home_url("/tdwp-display/{$screen['endpoint_url']}/");
        ?>
        <div class="screen-card" data-screen-id="<?php echo esc_attr($screen['screen_id']); ?>">
            <div class="screen-header">
                <h3 class="screen-name"><?php echo esc_html($screen['screen_name']); ?></h3>
                <span class="screen-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
            </div>

            <div class="screen-info">
                <div class="info-row">
                    <span class="label"><?php _e('Type:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo esc_html(ucfirst($screen['screen_type'])); ?></span>
                </div>

                <?php if (!empty($screen['screen_location'])): ?>
                <div class="info-row">
                    <span class="label"><?php _e('Location:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo esc_html($screen['screen_location']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($screen['tournament_name'])): ?>
                <div class="info-row">
                    <span class="label"><?php _e('Tournament:', 'poker-tournament-import'); ?></span>
                    <span class="value">
                        <?php echo esc_html($screen['tournament_name']); ?>
                        <?php if (!empty($screen['tournament_status'])): ?>
                            <span class="tournament-status-badge <?php echo esc_attr($screen['tournament_status']); ?>">
                                <?php echo esc_html(ucfirst($screen['tournament_status'])); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <span class="label"><?php _e('Last Seen:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo esc_html($last_seen); ?></span>
                </div>

                <?php if (!empty($screen['layout_name'])): ?>
                <div class="info-row">
                    <span class="label"><?php _e('Layout:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo esc_html($screen['layout_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="screen-actions">
                <button type="button" class="button preview-screen" data-preview-url="<?php echo esc_url($preview_url); ?>">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Preview', 'poker-tournament-import'); ?>
                </button>

                <?php if (empty($screen['tournament_id'])): ?>
                <button type="button" class="button button-secondary auto-assign-tournament">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Auto-Assign Tournament', 'poker-tournament-import'); ?>
                </button>
                <?php endif; ?>

                <button type="button" class="button edit-screen">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Edit', 'poker-tournament-import'); ?>
                </button>

                <button type="button" class="button toggle-screen" data-active="<?php echo $screen['is_active'] ? '1' : '0'; ?>">
                    <span class="dashicons <?php echo $screen['is_active'] ? 'dashicons-pause' : 'dashicons-play'; ?>"></span>
                    <?php echo $screen['is_active'] ? __('Pause', 'poker-tournament-import') : __('Resume', 'poker-tournament-import'); ?>
                </button>

                <button type="button" class="button delete-screen">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Delete', 'poker-tournament-import'); ?>
                </button>
            </div>

            <?php if (!empty($screen['screen_description'])): ?>
            <div class="screen-description">
                <p><?php echo esc_html($screen['screen_description']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render templates list
     *
     * @since 3.4.0
     */
    private function render_templates_list() {
        if (!class_exists('TDWP_Layout_Builder')) {
            echo '<div class="notice notice-warning"><p>' .
                __('Layout Builder not available. Please ensure TD3 Display System is properly installed.', 'poker-tournament-import') .
                '</p></div>';
            return;
        }

        $layout_builder = TDWP_Layout_Builder::get_instance();
        $templates = $layout_builder->get_all_layouts();

        if (empty($templates)) {
            echo '<div class="no-templates">';
            echo '<p>' . __('No layout templates found.', 'poker-tournament-import') . '</p>';
            echo '<a href="' . admin_url('admin.php?page=tdwp-layout-builder') . '" class="button button-primary">' .
                __('Create Layout Template', 'poker-tournament-import') . '</a>';
            echo '</div>';
            return;
        }

        echo '<div class="templates-grid">';
        foreach ($templates as $template) {
            $this->render_template_card($template);
        }
        echo '</div>';
    }

    /**
     * Render template card
     *
     * @since 3.4.0
     * @param array $template Template data.
     */
    private function render_template_card($template) {
        ?>
        <div class="template-card" data-template-id="<?php echo esc_attr($template['layout_id']); ?>">
            <div class="template-header">
                <h3 class="template-name"><?php echo esc_html($template['layout_name']); ?></h3>
                <span class="template-type"><?php echo esc_html(ucfirst($template['layout_type'])); ?></span>
            </div>

            <div class="template-info">
                <div class="info-row">
                    <span class="label"><?php _e('Created:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo date_i18n(get_option('date_format'), strtotime($template['created_at'])); ?></span>
                </div>

                <div class="info-row">
                    <span class="label"><?php _e('Components:', 'poker-tournament-import'); ?></span>
                    <span class="value"><?php echo count($template['components']); ?></span>
                </div>
            </div>

            <div class="template-actions">
                <button type="button" class="button assign-template">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Assign to Screen', 'poker-tournament-import'); ?>
                </button>

                <a href="<?php echo admin_url('admin.php?page=tdwp-layout-builder&layout_id=' . $template['layout_id']); ?>" class="button">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Edit Layout', 'poker-tournament-import'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render system health information
     *
     * @since 3.4.0
     */
    private function render_system_health() {
        if (!class_exists('TDWP_Display_Manager')) {
            echo '<div class="notice notice-warning"><p>' .
                __('Display Manager not available.', 'poker-tournament-import') .
                '</p></div>';
            return;
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $health_status = $display_manager->get_system_health_status();
        ?>
        <div class="health-overview">
            <div class="health-cards">
                <div class="health-card good">
                    <h3><?php _e('System Status', 'poker-tournament-import'); ?></h3>
                    <div class="health-indicator <?php echo $health_status['system_health']; ?>">
                        <span class="dashicons <?php echo $health_status['system_health'] === 'healthy' ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
                        <span class="health-text"><?php echo esc_html(ucfirst($health_status['system_health'])); ?></span>
                    </div>
                </div>

                <div class="health-card">
                    <h3><?php _e('Total Screens', 'poker-tournament-import'); ?></h3>
                    <div class="metric-value"><?php echo $health_status['total_screens']; ?></div>
                </div>

                <div class="health-card">
                    <h3><?php _e('Online Screens', 'poker-tournament-import'); ?></h3>
                    <div class="metric-value online"><?php echo $health_status['online_screens']; ?></div>
                </div>

                <div class="health-card">
                    <h3><?php _e('Avg Connection Quality', 'poker-tournament-import'); ?></h3>
                    <div class="metric-value"><?php echo $health_status['avg_connection_quality']; ?>%</div>
                </div>
            </div>
        </div>

        <div class="health-details">
            <h3><?php _e('Connection Quality Details', 'poker-tournament-import'); ?></h3>
            <div class="quality-breakdown">
                <div class="quality-bar">
                    <div class="quality-segment excellent" style="width: <?php echo $health_status['quality_breakdown']['excellent']; ?>%">
                        <span class="quality-label">Excellent</span>
                    </div>
                    <div class="quality-segment good" style="width: <?php echo $health_status['quality_breakdown']['good']; ?>%">
                        <span class="quality-label">Good</span>
                    </div>
                    <div class="quality-segment fair" style="width: <?php echo $health_status['quality_breakdown']['fair']; ?>%">
                        <span class="quality-label">Fair</span>
                    </div>
                    <div class="quality-segment poor" style="width: <?php echo $health_status['quality_breakdown']['poor']; ?>%">
                        <span class="quality-label">Poor</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="system-checks">
            <h3><?php _e('System Checks', 'poker-tournament-import'); ?></h3>
            <div class="check-list">
                <div class="check-item">
                    <span class="dashicons dashicons-yes check-pass"></span>
                    <span class="check-text"><?php _e('Display Manager Active', 'poker-tournament-import'); ?></span>
                </div>
                <div class="check-item">
                    <span class="dashicons <?php echo $health_status['heartbeat_active'] ? 'dashicons-yes check-pass' : 'dashicons-no check-fail'; ?>"></span>
                    <span class="check-text"><?php _e('Heartbeat API Active', 'poker-tournament-import'); ?></span>
                </div>
                <div class="check-item">
                    <span class="dashicons <?php echo $health_status['rewrite_rules_active'] ? 'dashicons-yes check-pass' : 'dashicons-no check-fail'; ?>"></span>
                    <span class="check-text"><?php _e('URL Rewrite Rules Active', 'poker-tournament-import'); ?></span>
                </div>
                <div class="check-item">
                    <span class="dashicons <?php echo $health_status['shortcodes_registered'] ? 'dashicons-yes check-pass' : 'dashicons-no check-fail'; ?>"></span>
                    <span class="check-text"><?php _e('Display Shortcodes Registered', 'poker-tournament-import'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for screen management operations
     *
     * @since 3.4.0
     */
    public function ajax_handler() {
        check_ajax_referer('tdwp_screen_management_nonce', 'nonce');

        $action = isset($_POST['sub_action']) ? sanitize_text_field($_POST['sub_action']) : '';

        switch ($action) {
            case 'add_screen':
                $this->ajax_add_screen();
                break;
            case 'edit_screen':
                $this->ajax_edit_screen();
                break;
            case 'delete_screen':
                $this->ajax_delete_screen();
                break;
            case 'toggle_screen':
                $this->ajax_toggle_screen();
                break;
            case 'refresh_status':
                $this->ajax_refresh_status();
                break;
            case 'debug_info':
                $this->ajax_debug_info();
                break;
            case 'get_tournaments':
                $this->ajax_get_tournaments();
                break;
            case 'get_layouts':
                $this->ajax_get_layouts();
                break;
            case 'get_tournament_options':
                $this->ajax_get_tournament_options();
                break;
            case 'auto_assign_screen':
                $this->ajax_auto_assign_screen();
                break;
            default:
                wp_send_json_error(array(
                    'message' => __('Invalid action', 'poker-tournament-import')
                ));
        }
    }

    /**
     * AJAX handler for adding new screen
     *
     * @since 3.4.0
     */
    private function ajax_add_screen() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        // Log received data for debugging
        error_log('TDWP Screen Management: Add screen data received: ' . print_r($_POST, true));

        $screen_data = array(
            'screen_name' => sanitize_text_field($_POST['screen_name']),
            'screen_location' => sanitize_text_field($_POST['screen_location']),
            'screen_type' => sanitize_text_field($_POST['screen_type']),
            'tournament_id' => intval($_POST['tournament_id']),
            'layout_id' => intval($_POST['layout_id']),
            'refresh_rate' => intval($_POST['refresh_rate']),
            'screen_description' => sanitize_textarea_field($_POST['screen_description']),
            'endpoint_url' => isset($_POST['endpoint_url']) ? sanitize_text_field($_POST['endpoint_url']) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        // Validate required fields
        if (empty($screen_data['screen_name'])) {
            wp_send_json_error(array(
                'message' => __('Screen name is required', 'poker-tournament-import'),
                'details' => 'The screen_name field was empty after sanitization'
            ));
        }

        if (empty($screen_data['endpoint_url'])) {
            wp_send_json_error(array(
                'message' => __('Endpoint URL is required', 'poker-tournament-import'),
                'details' => 'The endpoint_url field was not provided or was empty after sanitization'
            ));
        }

        // Additional validation
        if (!preg_match('/^[a-z0-9-]+$/', $screen_data['endpoint_url'])) {
            error_log('TDWP Screen Management: Validation failed - endpoint_url format invalid: ' . $screen_data['endpoint_url']);
            wp_send_json_error(array(
                'message' => __('Invalid endpoint URL format', 'poker-tournament-import'),
                'details' => 'Endpoint URL must contain only lowercase letters, numbers, and hyphens',
                'field' => 'endpoint_url'
            ));
        }

        // Validate screen type
        $allowed_types = array('clock', 'rankings', 'prizes', 'seating', 'custom', 'tournament');
        if (!in_array($screen_data['screen_type'], $allowed_types)) {
            error_log('TDWP Screen Management: Validation failed - invalid screen_type: ' . $screen_data['screen_type']);
            wp_send_json_error(array(
                'message' => __('Invalid screen type', 'poker-tournament-import'),
                'details' => 'Screen type must be one of: ' . implode(', ', $allowed_types),
                'field' => 'screen_type'
            ));
        }

        error_log('TDWP Screen Management: Processed screen data: ' . print_r($screen_data, true));

        if (!class_exists('TDWP_Display_Manager')) {
            error_log('TDWP Screen Management: Display Manager class not found');
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import'),
                'details' => 'The TDWP_Display_Manager class is not loaded'
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $screen_id = $display_manager->register_screen($screen_data);

        if ($screen_id) {
            error_log('TDWP Screen Management: Screen created successfully with ID: ' . $screen_id);
            wp_send_json_success(array(
                'message' => __('Screen created successfully', 'poker-tournament-import'),
                'screen_id' => $screen_id,
                'endpoint_url' => $screen_data['endpoint_url']
            ));
        } else {
            error_log('TDWP Screen Management: Screen creation failed for screen: ' . $screen_data['screen_name']);
            wp_send_json_error(array(
                'message' => __('Failed to create screen', 'poker-tournament-import'),
                'details' => 'The Display Manager returned false when attempting to register the screen. Check error logs for details.'
            ));
        }
    }

    /**
     * AJAX handler for editing screen
     *
     * @since 3.4.0
     */
    private function ajax_edit_screen() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        $screen_id = intval($_POST['screen_id']);

        // Log received data for debugging
        error_log('TDWP Screen Management: Edit screen data received for screen ID ' . $screen_id . ': ' . print_r($_POST, true));

        $screen_data = array(
            'screen_name' => sanitize_text_field($_POST['screen_name']),
            'screen_location' => sanitize_text_field($_POST['screen_location']),
            'screen_type' => sanitize_text_field($_POST['screen_type']),
            'tournament_id' => intval($_POST['tournament_id']),
            'layout_id' => intval($_POST['layout_id']),
            'refresh_rate' => intval($_POST['refresh_rate']),
            'screen_description' => sanitize_textarea_field($_POST['screen_description']),
            'endpoint_url' => isset($_POST['endpoint_url']) ? sanitize_text_field($_POST['endpoint_url']) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        // Validate required fields
        if (empty($screen_data['screen_name'])) {
            wp_send_json_error(array(
                'message' => __('Screen name is required', 'poker-tournament-import'),
                'details' => 'The screen_name field was empty after sanitization'
            ));
        }

        if (empty($screen_data['endpoint_url'])) {
            wp_send_json_error(array(
                'message' => __('Endpoint URL is required', 'poker-tournament-import'),
                'details' => 'The endpoint_url field was not provided or was empty after sanitization'
            ));
        }

        error_log('TDWP Screen Management: Processed edit screen data: ' . print_r($screen_data, true));

        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $result = $display_manager->update_screen($screen_id, $screen_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Screen updated successfully', 'poker-tournament-import')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to update screen', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for deleting screen
     *
     * @since 3.4.0
     */
    private function ajax_delete_screen() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        $screen_id = intval($_POST['screen_id']);

        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $result = $display_manager->delete_screen($screen_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Screen deleted successfully', 'poker-tournament-import')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete screen', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for toggling screen status
     *
     * @since 3.4.0
     */
    private function ajax_toggle_screen() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        $screen_id = intval($_POST['screen_id']);
        $is_active = intval($_POST['is_active']);

        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $result = $display_manager->update_screen($screen_id, array('is_active' => $is_active));

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Screen status updated successfully', 'poker-tournament-import')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to update screen status', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for refreshing screen status
     *
     * @since 3.4.0
     */
    private function ajax_refresh_status() {
        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $screens = $display_manager->get_all_screens();
        $health_status = $display_manager->get_system_health_status();

        wp_send_json_success(array(
            'screens' => $screens,
            'health_status' => $health_status
        ));
    }

    /**
     * AJAX handler for getting tournaments
     *
     * @since 3.4.0
     */
    private function ajax_get_tournaments() {
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $tournament_list = array();
        foreach ($tournaments as $tournament) {
            $tournament_list[] = array(
                'id' => $tournament->ID,
                'title' => $tournament->post_title
            );
        }

        wp_send_json_success($tournament_list);
    }

    /**
     * AJAX handler for getting layouts
     *
     * @since 3.4.0
     */
    private function ajax_get_layouts() {
        if (!class_exists('TDWP_Layout_Builder')) {
            wp_send_json_error(array(
                'message' => __('Layout Builder not available', 'poker-tournament-import')
            ));
        }

        $layout_builder = TDWP_Layout_Builder::get_instance();
        $layouts = $layout_builder->get_all_layouts();

        $layout_list = array();
        foreach ($layouts as $layout) {
            $layout_list[] = array(
                'id' => $layout['layout_id'],
                'name' => $layout['layout_name'],
                'type' => $layout['layout_type']
            );
        }

        wp_send_json_success($layout_list);
    }

    /**
     * AJAX handler for getting tournament options with live status
     *
     * @since 3.4.1
     */
    private function ajax_get_tournament_options() {
        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $tournament_options = $display_manager->get_tournament_options_for_screens();

        wp_send_json_success($tournament_options);
    }

    /**
     * AJAX handler for auto-assigning screen to running tournament
     *
     * @since 3.4.1
     */
    private function ajax_auto_assign_screen() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        $screen_id = intval($_POST['screen_id']);

        if (!class_exists('TDWP_Display_Manager')) {
            wp_send_json_error(array(
                'message' => __('Display Manager not available', 'poker-tournament-import')
            ));
        }

        $display_manager = TDWP_Display_Manager::get_instance();
        $result = $display_manager->auto_assign_to_running_tournament($screen_id);

        if ($result && !empty($result['tournament_id'])) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Screen assigned to tournament: %s', 'poker-tournament-import'),
                    $result['tournament_name']
                ),
                'tournament_id' => $result['tournament_id'],
                'tournament_name' => $result['tournament_name'],
                'tournament_status' => $result['tournament_status']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No running tournaments found for auto-assignment', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for debug information
     *
     * @since 3.4.1
     */
    private function ajax_debug_info() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'poker-tournament-import')
            ));
        }

        error_log('TDWP Screen Management: Debug info requested');

        $debug_info = array(
            'timestamp' => current_time('mysql'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('POKER_TOURNAMENT_IMPORT_VERSION') ? POKER_TOURNAMENT_IMPORT_VERSION : 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        );

        // Check dependency classes
        $debug_info['dependencies'] = array(
            'TDWP_Display_Manager' => class_exists('TDWP_Display_Manager'),
            'TDWP_Database_Schema' => class_exists('TDWP_Database_Schema'),
            'TDWP_Template_Engine' => class_exists('TDWP_Template_Engine'),
            'TDWP_Heartbeat_Manager' => class_exists('TDWP_Heartbeat_Manager'),
            'TDWP_Tournament_Live' => class_exists('TDWP_Tournament_Live'),
        );

        // Check database tables
        global $wpdb;
        $debug_info['database'] = array(
            'prefix' => $wpdb->prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'tables' => array(
                'screens' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}poker_display_screens'") ? 'exists' : 'missing',
                'tournaments' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}poker_tournaments'") ? 'exists' : 'missing',
            ),
            'last_error' => $wpdb->last_error,
        );

        // Check if we can connect to Display Manager
        if (class_exists('TDWP_Display_Manager')) {
            try {
                $display_manager = TDWP_Display_Manager::get_instance();
                $debug_info['display_manager'] = array(
                    'status' => 'available',
                    'instance' => get_class($display_manager),
                );
            } catch (Exception $e) {
                $debug_info['display_manager'] = array(
                    'status' => 'error',
                    'error' => $e->getMessage(),
                );
            }
        } else {
            $debug_info['display_manager'] = array(
                'status' => 'not_available',
            );
        }

        // Test endpoint URL generation
        $test_screen_name = 'Test Debug Screen';
        $test_endpoint_url = strtolower($test_screen_name);
        $test_endpoint_url = preg_replace('/[^a-z0-9\s-]/', '', $test_endpoint_url);
        $test_endpoint_url = preg_replace('/\s+/', '-', $test_endpoint_url);
        $test_endpoint_url = preg_replace('/-+/', '-', $test_endpoint_url);
        $test_endpoint_url = preg_replace('/^-|-$/', '', $test_endpoint_url);

        $debug_info['endpoint_generation_test'] = array(
            'input' => $test_screen_name,
            'output' => $test_endpoint_url,
            'success' => !empty($test_endpoint_url),
        );

        // Current request info
        $debug_info['current_request'] = array(
            'method' => $_SERVER['REQUEST_METHOD'],
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_verified' => wp_verify_nonce($_POST['nonce'], 'tdwp_screen_management_nonce'),
            'user_can_manage' => current_user_can('manage_options'),
        );

        error_log('TDWP Screen Management: Debug info generated: ' . print_r($debug_info, true));

        wp_send_json_success(array(
            'message' => __('Debug information retrieved successfully', 'poker-tournament-import'),
            'debug_info' => $debug_info
        ));
    }
}

// Initialize the screen management page
new TDWP_Screen_Management_Page();