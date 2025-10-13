<?php
/**
 * Plugin Name: Poker Tournament Import
 * Plugin URI: https://nikielhard.se/tdwpimport
 * Description: Import and display poker tournament results from Tournament Director (.tdt) files
 * Version: 2.0.2
 * Author: Hans Kästel Hård
 * Author URI: https://nikielhard.se/tdwpimport
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: poker-tournament-import
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('POKER_TOURNAMENT_IMPORT_VERSION', '2.0.2');
define('POKER_TOURNAMENT_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POKER_TOURNAMENT_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Poker_Tournament_Import {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        $this->load_textdomain();
        $this->includes();
        $this->init_post_types();
        $this->init_taxonomies();
        $this->init_formula_validator();
        $this->init_shortcodes();
        $this->init_statistics_engine();

        // Check for plugin update and refresh statistics if needed
        $this->check_plugin_update();

        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Admin hooks
        if (is_admin()) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-admin.php';
            new Poker_Tournament_Import_Admin();

            // Initialize migration tools
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-migration-tools.php';
            new Poker_Tournament_Migration_Tools();

            // Initialize shortcode help page
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/shortcode-help.php';
            $shortcode_help = new Poker_Shortcode_Help_Page();
            $shortcode_help->add_help_admin_menu();

            // Initialize shortcode helper meta boxes
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/shortcode-helper.php';
            new Poker_Shortcode_Helper();
        }

        // AJAX handlers for tabbed interface
        add_action('wp_ajax_poker_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_nopriv_poker_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_poker_series_load_more', array($this, 'ajax_series_load_more'));
        add_action('wp_ajax_nopriv_poker_series_load_more', array($this, 'ajax_series_load_more'));

        // AJAX handlers for formula validator
        add_action('wp_ajax_poker_validate_formula', array($this, 'ajax_validate_formula'));
        add_action('wp_ajax_poker_save_formula', array($this, 'ajax_save_formula'));
        add_action('wp_ajax_poker_delete_formula', array($this, 'ajax_delete_formula'));
        add_action('wp_ajax_poker_get_formula', array($this, 'ajax_get_formula'));

        // AJAX handlers for series standings
        add_action('wp_ajax_poker_export_standings', array($this, 'ajax_export_standings'));

        // AJAX handlers for dashboard (accessible to all users)
        add_action('wp_ajax_poker_dashboard_load_content', array($this, 'ajax_dashboard_load_content'));
        add_action('wp_ajax_nopriv_poker_dashboard_load_content', array($this, 'ajax_dashboard_load_content'));
        add_action('wp_ajax_poker_dashboard_detailed_view', array($this, 'ajax_dashboard_detailed_view'));
        add_action('wp_ajax_nopriv_poker_dashboard_detailed_view', array($this, 'ajax_dashboard_detailed_view'));
        add_action('wp_ajax_poker_dashboard_generate_report', array($this, 'ajax_dashboard_generate_report'));
        add_action('wp_ajax_nopriv_poker_dashboard_generate_report', array($this, 'ajax_dashboard_generate_report'));

        // **PHASE 1: AJAX handlers for player drill-through**
        add_action('wp_ajax_poker_get_player_details', array($this, 'ajax_get_player_details'));
        add_action('wp_ajax_nopriv_poker_get_player_details', array($this, 'ajax_get_player_details'));

        // AJAX handlers for statistics refresh
        add_action('wp_ajax_poker_refresh_statistics', array($this, 'ajax_refresh_statistics'));

        // Hook into tournament creation and updates
        add_action('save_post_tournament', array($this, 'on_tournament_save'), 10, 3);
        add_action('wp_trash_post', array($this, 'on_tournament_delete'));
        add_action('untrash_post', array($this, 'on_tournament_restore'));

        // Hook for asynchronous statistics refresh
        add_action('poker_refresh_statistics_async', array($this, 'async_refresh_statistics'));
    }

    /**
     * Check for plugin update and refresh statistics if needed
     */
    private function check_plugin_update() {
        $last_version = get_option('poker_import_last_version', '0');

        if ($last_version !== POKER_TOURNAMENT_IMPORT_VERSION) {
            // Plugin was updated, force refresh statistics
            error_log("Poker Import: Plugin updated from {$last_version} to " . POKER_TOURNAMENT_IMPORT_VERSION . ", refreshing statistics");

            if (class_exists('Poker_Statistics_Engine')) {
                $stats_engine = Poker_Statistics_Engine::get_instance();
                $result = $stats_engine->calculate_all_statistics();

                if ($result) {
                    error_log("Poker Import: Statistics refreshed successfully after plugin update");
                    update_option('poker_statistics_last_refresh', current_time('mysql'));
                } else {
                    error_log("Poker Import: Failed to refresh statistics after plugin update");
                }
            }

            // Update the stored version
            update_option('poker_import_last_version', POKER_TOURNAMENT_IMPORT_VERSION);
        }
    }

    /**
     * Load plugin text domain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'poker-tournament-import',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Enqueue frontend styles and scripts
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'poker-tournament-import-frontend',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        wp_enqueue_script(
            'poker-tournament-import-frontend',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            POKER_TOURNAMENT_IMPORT_VERSION,
            true
        );

        // Localize script with nonce and other data
        wp_localize_script(
            'poker-tournament-import-frontend',
            'pokerImport',
            array(
                'nonce' => wp_create_nonce('poker_series_tab_content'),
                'loadMoreNonce' => wp_create_nonce('poker_series_load_more'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'messages' => array(
                    'tabError' => __('Error loading content. Please try again.', 'poker-tournament-import'),
                    'loadMoreError' => __('Error loading more results. Please try again.', 'poker-tournament-import')
                )
            )
        );
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-parser.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-debug.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-formula-validator.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-series-standings.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-statistics-engine.php';
    }

    /**
     * Initialize custom post types
     */
    private function init_post_types() {
        $post_types = new Poker_Tournament_Import_Post_Types();
        $post_types->register();
    }

    /**
     * Initialize custom taxonomies
     */
    private function init_taxonomies() {
        // Load and initialize the taxonomy class
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-taxonomies.php';
        $this->taxonomies = new Poker_Tournament_Import_Taxonomies();
    }

    /**
     * Initialize formula validator
     */
    private function init_formula_validator() {
        $this->formula_validator = new Poker_Tournament_Formula_Validator();
    }

    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        new Poker_Tournament_Import_Shortcodes();
    }

    /**
     * Initialize statistics engine
     */
    private function init_statistics_engine() {
        $this->statistics_engine = Poker_Statistics_Engine::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Create database tables if needed
        $this->create_database_tables();

        // Force statistics table creation and initial calculation
        $this->ensure_statistics_table_exists();

        // Initialize and calculate statistics
        if (class_exists('Poker_Statistics_Engine')) {
            $stats_engine = Poker_Statistics_Engine::get_instance();
            $stats_engine->calculate_all_statistics();
        }

        // Set version to force refresh on first load
        update_option('poker_import_last_version', POKER_TOURNAMENT_IMPORT_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for tournament players
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tournament_id varchar(100) NOT NULL,
            player_id varchar(100) NOT NULL,
            finish_position int NOT NULL,
            winnings decimal(10,2) DEFAULT 0,
            buyins int DEFAULT 1,
            rebuys int DEFAULT 0,
            addons int DEFAULT 0,
            hits int DEFAULT 0,
            points decimal(10,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tournament_id (tournament_id),
            KEY player_id (player_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Table for dashboard statistics (data mart)
        $stats_table_name = $wpdb->prefix . 'poker_statistics';
        $stats_sql = "CREATE TABLE $stats_table_name (
            stat_id mediumint(9) NOT NULL AUTO_INCREMENT,
            stat_name varchar(100) NOT NULL,
            stat_value decimal(20,2) NOT NULL,
            stat_type enum('count', 'sum', 'average', 'latest') NOT NULL DEFAULT 'count',
            last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            calculation_date date DEFAULT NULL,
            related_id int DEFAULT NULL,
            PRIMARY KEY  (stat_id),
            UNIQUE KEY stat_name (stat_name),
            KEY stat_type (stat_type),
            KEY last_updated (last_updated)
        ) $charset_collate;";

        dbDelta($stats_sql);

        // **PHASE 2.1: Financial Data Infrastructure Tables**

        // Table for tournament costs
        $costs_table_name = $wpdb->prefix . 'poker_tournament_costs';
        $costs_sql = "CREATE TABLE $costs_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tournament_id varchar(100) NOT NULL,
            cost_category varchar(50) NOT NULL COMMENT 'venue, staff, equipment, marketing, prize_pool, overhead',
            cost_amount decimal(10,2) NOT NULL DEFAULT 0,
            cost_description text,
            cost_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tournament_id (tournament_id),
            KEY cost_category (cost_category),
            KEY cost_date (cost_date)
        ) $charset_collate;";

        dbDelta($costs_sql);

        // Table for tournament financial summary (enhanced data mart)
        $financial_table_name = $wpdb->prefix . 'poker_financial_summary';
        $financial_sql = "CREATE TABLE $financial_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tournament_id varchar(100) NOT NULL,
            tournament_revenue decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Total revenue from buy-ins, rebuys, etc',
            tournament_costs decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Total operating costs',
            tournament_profit decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Revenue minus costs',
            tournament_roi_percentage decimal(5,2) DEFAULT 0 COMMENT 'ROI as percentage',
            prize_pool_efficiency decimal(5,2) DEFAULT 0 COMMENT 'Prize pool to revenue ratio',
            buy_in_amount decimal(10,2) DEFAULT 0 COMMENT 'Standardized buy-in amount',
            currency_code varchar(3) DEFAULT 'USD' COMMENT 'ISO currency code',
            currency_conversion_rate decimal(10,6) DEFAULT 1.000000 COMMENT 'Conversion rate to base currency',
            financial_data json DEFAULT NULL COMMENT 'Additional financial metrics as JSON',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tournament_id (tournament_id),
            KEY tournament_roi (tournament_roi_percentage),
            KEY currency_code (currency_code),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($financial_sql);

        // Table for player ROI tracking
        $player_roi_table_name = $wpdb->prefix . 'poker_player_roi';
        $player_roi_sql = "CREATE TABLE $player_roi_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id varchar(100) NOT NULL,
            tournament_id varchar(100) NOT NULL,
            total_invested decimal(10,2) NOT NULL DEFAULT 0 COMMENT 'Buy-ins + rebuys + addons',
            total_winnings decimal(10,2) NOT NULL DEFAULT 0 COMMENT 'Tournament winnings',
            net_profit decimal(10,2) NOT NULL DEFAULT 0 COMMENT 'Winnings minus invested',
            roi_percentage decimal(5,2) DEFAULT 0 COMMENT 'Return on investment percentage',
            finish_position int NOT NULL DEFAULT 0,
            tournament_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY player_id (player_id),
            KEY tournament_id (tournament_id),
            KEY roi_percentage (roi_percentage),
            KEY tournament_date (tournament_date)
        ) $charset_collate;";

        dbDelta($player_roi_sql);

        // Table for revenue analytics (monthly aggregation)
        $revenue_analytics_table_name = $wpdb->prefix . 'poker_revenue_analytics';
        $revenue_analytics_sql = "CREATE TABLE $revenue_analytics_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            analytics_period varchar(7) NOT NULL COMMENT 'YYYY-MM format',
            total_tournaments int NOT NULL DEFAULT 0,
            total_revenue decimal(12,2) NOT NULL DEFAULT 0,
            total_costs decimal(12,2) NOT NULL DEFAULT 0,
            total_profit decimal(12,2) NOT NULL DEFAULT 0,
            average_profit_per_tournament decimal(10,2) NOT NULL DEFAULT 0,
            total_players int NOT NULL DEFAULT 0,
            average_revenue_per_player decimal(10,2) NOT NULL DEFAULT 0,
            profit_margin_percentage decimal(5,2) DEFAULT 0,
            revenue_growth_percentage decimal(5,2) DEFAULT 0,
            currency_code varchar(3) DEFAULT 'USD',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY analytics_period (analytics_period),
            KEY profit_margin (profit_margin_percentage),
            KEY revenue_growth (revenue_growth_percentage),
            KEY currency_code (currency_code)
        ) $charset_collate;";

        dbDelta($revenue_analytics_sql);

        // Log successful table creation
        error_log("Poker Import: Phase 2.1 Financial tables created successfully");
    }

    /**
     * Ensure statistics table exists with proper error handling
     */
    private function ensure_statistics_table_exists() {
        global $wpdb;

        $stats_table_name = $wpdb->prefix . 'poker_statistics';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$stats_table_name}'");

        if (!$table_exists) {
            error_log("Poker Statistics: Creating statistics table - it doesn't exist");

            // Create table directly
            $stats_sql = "CREATE TABLE {$stats_table_name} (
                stat_id mediumint(9) NOT NULL AUTO_INCREMENT,
                stat_name varchar(100) NOT NULL,
                stat_value decimal(20,2) NOT NULL,
                stat_type enum('count', 'sum', 'average', 'latest') NOT NULL DEFAULT 'count',
                last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                calculation_date date DEFAULT NULL,
                related_id int DEFAULT NULL,
                PRIMARY KEY  (stat_id),
                UNIQUE KEY stat_name (stat_name),
                KEY stat_type (stat_type),
                KEY last_updated (last_updated)
            ) {$charset_collate};";

            $result = $wpdb->query($stats_sql);

            if ($result === false) {
                error_log("Poker Statistics: Failed to create statistics table: " . $wpdb->last_error);
            } else {
                error_log("Poker Statistics: Statistics table created successfully");
            }
        } else {
            error_log("Poker Statistics: Statistics table already exists");
        }
    }

    /**
     * AJAX handler for series tab content
     */
    public function ajax_series_tab_content() {
        check_ajax_referer('poker_series_tab_content', 'nonce');

        $series_id = intval($_POST['series_id']);
        $tab = sanitize_text_field($_POST['tab']);

        if (!$series_id || !in_array($tab, array('overview', 'results', 'statistics', 'players'))) {
            wp_die('Invalid request');
        }

        // Output the appropriate shortcode content
        $shortcode = '[series_' . $tab . ' id="' . $series_id . '"]';
        echo do_shortcode($shortcode);
        wp_die();
    }

    /**
     * AJAX handler for loading more series results
     */
    public function ajax_series_load_more() {
        check_ajax_referer('poker_series_load_more', 'nonce');

        $series_id = intval($_POST['series_id']);
        $offset = intval($_POST['offset']);
        $limit = intval($_POST['limit']);

        if (!$series_id) {
            wp_die('Invalid request');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get tournaments for this series
        $series_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_id',
            'meta_value' => $series_id,
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();

        if (!empty($series_tournaments)) {
            foreach ($series_tournaments as $tournament):
                $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                $players_count = get_post_meta($tournament->ID, '_players_count', true);
                $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                // Get winner
                $winner_name = '';
                if ($tournament_uuid) {
                    $winner = $wpdb->get_row($wpdb->prepare(
                        "SELECT p.post_title
                         FROM $table_name tp
                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE tp.tournament_id = %s AND tp.finish_position = 1
                         LIMIT 1",
                        $tournament_uuid
                    ));
                    if ($winner) $winner_name = $winner->post_title;
                }
                ?>
                <tr>
                    <td class="tournament-name">
                        <a href="<?php echo get_permalink($tournament->ID); ?>">
                            <?php echo esc_html($tournament->post_title); ?>
                        </a>
                    </td>
                    <td class="tournament-date">
                        <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?>
                    </td>
                    <td class="tournament-players"><?php echo esc_html($players_count ?: '--'); ?></td>
                    <td class="tournament-prize"><?php echo esc_html($currency . number_format(floatval($prize_pool ?: 0), 0)); ?></td>
                    <td class="tournament-winner">
                        <?php if ($winner_name): ?>
                            <a href="#" class="winner-link"><?php echo esc_html($winner_name); ?></a>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    <td class="tournament-actions">
                        <a href="<?php echo get_permalink($tournament->ID); ?>" class="btn-small">
                            <?php _e('View', 'poker-tournament-import'); ?>
                        </a>
                    </td>
                </tr>
                <?php
            endforeach;
        }

        $output = ob_get_clean();
        echo $output;
        wp_die();
    }

    /**
     * AJAX handler for formula validation
     */
    public function ajax_validate_formula() {
        check_ajax_referer('poker_formula_validator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $formula = sanitize_textarea_field($_POST['formula']);
        $test_data = $_POST['test_data'];

        if (empty($formula)) {
            echo '<div class="error"><p>' . __('Formula cannot be empty.', 'poker-tournament-import') . '</p></div>';
            wp_die();
        }

        $validator = new Poker_Tournament_Formula_Validator();
        $validation = $validator->validate_formula($formula);

        // Prepare test data
        $prepared_data = array(
            'total_players' => intval($test_data['n'] ?? 20),
            'finish_position' => intval($test_data['r'] ?? 3),
            'hits' => intval($test_data['hits'] ?? 0),
            'total_money' => floatval($test_data['total_money'] ?? 2000),
            'total_buyins' => intval($test_data['total_buyins'] ?? 20),
            'total_buyins_amount' => floatval($test_data['total_money'] ?? 2000),
            'total_rebuys_amount' => 0,
            'total_addons_amount' => 0,
        );

        $calculation = $validator->calculate_formula($formula, $prepared_data);

        // Display results
        $output = '<div class="validation-results">';

        if ($validation['valid']) {
            $output .= '<div class="success"><p>✅ <strong>' . __('Formula is valid!', 'poker-tournament-import') . '</strong></p></div>';

            if (!empty($validation['warnings'])) {
                $output .= '<div class="warning"><p><strong>' . __('Warnings:', 'poker-tournament-import') . '</strong></p><ul>';
                foreach ($validation['warnings'] as $warning) {
                    $output .= '<li>' . esc_html($warning) . '</li>';
                }
                $output .= '</ul></div>';
            }

            if ($calculation['success']) {
                $output .= '<div class="calculation-result">';
                $output .= '<h4>' . __('Test Result:', 'poker-tournament-import') . '</h4>';
                $output .= '<p><strong>' . __('Calculated Points:', 'poker-tournament-import') . '</strong> ' . esc_html(number_format($calculation['result'], 2)) . '</p>';

                if (!empty($calculation['variables'])) {
                    $output .= '<h4>' . __('Variables Used:', 'poker-tournament-import') . '</h4>';
                    $output .= '<table class="wp-list-table widefat striped" style="max-width: 400px;">';
                    $output .= '<thead><tr><th>Variable</th><th>Value</th></tr></thead><tbody>';

                    $important_vars = array('n', 'r', 'hits', 'monies', 'avgBC', 'T33', 'T80', 'points');
                    foreach ($important_vars as $var) {
                        if (isset($calculation['variables'][$var])) {
                            $value = $calculation['variables'][$var];
                            if (is_float($value)) {
                                $value = number_format($value, 2);
                            }
                            $output .= '<tr><td><code>' . esc_html($var) . '</code></td><td>' . esc_html($value) . '</td></tr>';
                        }
                    }
                    $output .= '</tbody></table>';
                }
                $output .= '</div>';
            } else {
                $output .= '<div class="error"><p><strong>' . __('Calculation Error:', 'poker-tournament-import') . '</strong> ' . esc_html($calculation['error']) . '</p></div>';
            }
        } else {
            $output .= '<div class="error"><p><strong>' . __('Formula is invalid:', 'poker-tournament-import') . '</strong></p><ul>';
            foreach ($validation['errors'] as $error) {
                $output .= '<li>' . esc_html($error) . '</li>';
            }
            $output .= '</ul></div>';
        }

        $output .= '</div>';
        echo $output;
        wp_die();
    }

    /**
     * AJAX handler for saving formulas
     */
    public function ajax_save_formula() {
        check_ajax_referer('poker_formula_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $name = sanitize_text_field($_POST['formula_name']);
        $formula_data = array(
            'name' => sanitize_text_field($_POST['display_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'formula' => sanitize_textarea_field($_POST['formula']),
            'dependencies' => sanitize_textarea_field($_POST['dependencies']),
            'category' => sanitize_text_field($_POST['category'])
        );

        $validator = new Poker_Tournament_Formula_Validator();
        $validation = $validator->validate_formula($formula_data['formula']);

        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => __('Formula is invalid and cannot be saved.', 'poker-tournament-import'),
                'errors' => $validation['errors']
            ));
        }

        $validator->save_formula($name, $formula_data);
        wp_send_json_success(array(
            'message' => __('Formula saved successfully!', 'poker-tournament-import')
        ));
    }

    /**
     * AJAX handler for deleting formulas
     */
    public function ajax_delete_formula() {
        check_ajax_referer('poker_formula_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $name = sanitize_text_field($_POST['formula_name']);
        $validator = new Poker_Tournament_Formula_Validator();

        if ($validator->delete_formula($name)) {
            wp_send_json_success(array(
                'message' => __('Formula deleted successfully!', 'poker-tournament-import')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Formula not found or cannot be deleted.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for getting formula data
     */
    public function ajax_get_formula() {
        check_ajax_referer('poker_formula_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $name = sanitize_text_field($_POST['formula_key']);
        $validator = new Poker_Tournament_Formula_Validator();
        $formula = $validator->get_formula($name);

        if ($formula) {
            wp_send_json_success(array(
                'data' => $formula
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Formula not found.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for exporting standings
     */
    public function ajax_export_standings() {
        check_ajax_referer('poker_export_standings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $series_id = intval($_POST['series_id']);
        $formula_key = sanitize_text_field($_POST['formula']);

        $standings_calculator = new Poker_Series_Standings_Calculator();
        $csv_file = $standings_calculator->export_series_standings_csv($series_id, $formula_key);

        if ($csv_file && file_exists($csv_file)) {
            // Create download URL
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace($upload_dir['basedir'], '', $csv_file);
            $download_url = $upload_dir['baseurl'] . $relative_path;
            $filename = basename($csv_file);

            wp_send_json_success(array(
                'download_url' => $download_url,
                'filename' => $filename
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to generate CSV file.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for dashboard content loading
     */
    public function ajax_dashboard_load_content() {
        check_ajax_referer('poker_dashboard_nonce', 'nonce');

        $view = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : 'overview';

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        ob_start();

        switch ($view) {
            case 'tournaments':
                $this->render_tournaments_tab_content();
                break;
            case 'players':
                $this->render_players_tab_content();
                break;
            case 'series':
                $this->render_series_tab_content();
                break;
            case 'analytics':
                $this->render_analytics_tab_content();
                break;
            default:
                echo '<div class="error">' . __('Unknown view', 'poker-tournament-import') . '</div>';
                break;
        }

        $content = ob_get_clean();

        wp_send_json_success(array('content' => $content));
    }

    /**
     * AJAX handler for dashboard detailed view
     */
    public function ajax_dashboard_detailed_view() {
        check_ajax_referer('poker_dashboard_nonce', 'nonce');

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        ob_start();

        switch ($type) {
            case 'tournaments':
                $this->render_detailed_tournaments_view();
                break;
            case 'players':
                $this->render_detailed_players_view();
                break;
            case 'leaderboard':
                $this->render_detailed_leaderboard_view();
                break;
            case 'calendar':
                $this->render_calendar_view();
                break;
            default:
                echo '<div class="error">' . __('Unknown view type', 'poker-tournament-import') . '</div>';
                break;
        }

        $content = ob_get_clean();

        wp_send_json_success(array('content' => $content));
    }

    /**
     * AJAX handler for dashboard report generation
     */
    public function ajax_dashboard_generate_report() {
        check_ajax_referer('poker_dashboard_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Generate CSV report
        $filename = 'poker-tournament-report-' . date('Y-m-d') . '.csv';
        $filepath = get_temp_dir() . $filename;

        $handle = fopen($filepath, 'w');

        // CSV headers
        fputcsv($handle, array(
            'Tournament Name',
            'Date',
            'Players',
            'Prize Pool',
            'Winner',
            'Winner Earnings'
        ));

        // Get tournament data
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($tournaments as $tournament) {
            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
            $players_count = get_post_meta($tournament->ID, '_players_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
            $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);

            $winner_name = '';
            $winner_winnings = 0;

            if ($tournament_uuid) {
                $winner = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.post_title as winner_name, tp.winnings as winner_winnings
                     FROM $table_name tp
                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE tp.tournament_id = %s AND tp.finish_position = 1
                     LIMIT 1",
                    $tournament_uuid
                ));

                if ($winner) {
                    $winner_name = $winner->winner_name;
                    $winner_winnings = $winner->winner_winnings;
                }
            }

            fputcsv($handle, array(
                $tournament->post_title,
                $tournament_date ?: get_the_date('Y-m-d', $tournament->ID),
                $players_count ?: 0,
                $prize_pool ?: 0,
                $winner_name,
                $winner_winnings
            ));
        }

        fclose($handle);

        // Create download URL
        $upload_dir = wp_upload_dir();
        $report_dir = $upload_dir['basedir'] . '/poker-reports/';
        if (!file_exists($report_dir)) {
            wp_mkdir_p($report_dir);
        }

        $final_filepath = $report_dir . $filename;
        rename($filepath, $final_filepath);

        $download_url = $upload_dir['baseurl'] . '/poker-reports/' . $filename;

        wp_send_json_success(array(
            'download_url' => $download_url,
            'filename' => $filename
        ));
    }

    /**
     * Render tournaments tab content
     */
    private function render_tournaments_tab_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        echo '<div class="tournaments-detail-view">';
        echo '<h3>' . __('All Tournaments', 'poker-tournament-import') . '</h3>';

        if (!empty($tournaments)) {
            echo '<div class="tournaments-table-wrapper">';
            echo '<table class="widefat tournaments-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Tournament', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Date', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Players', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Prize Pool', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Winner', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Actions', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($tournaments as $tournament) {
                $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                $players_count = get_post_meta($tournament->ID, '_players_count', true);
                $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                // Get winner
                $winner_name = '';
                if ($tournament_uuid) {
                    $winner = $wpdb->get_row($wpdb->prepare(
                        "SELECT p.post_title as winner_name
                         FROM $table_name tp
                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE tp.tournament_id = %s AND tp.finish_position = 1
                         LIMIT 1",
                        $tournament_uuid
                    ));
                    if ($winner) $winner_name = $winner->winner_name;
                }

                echo '<tr>';
                echo '<td><strong><a href="' . get_permalink($tournament->ID) . '">' . esc_html($tournament->post_title) . '</a></strong></td>';
                echo '<td>' . esc_html($tournament_date ? date_i18n('M j, Y', strtotime($tournament_date)) : get_the_date('M j, Y', $tournament->ID)) . '</td>';
                echo '<td>' . esc_html($players_count ?: '--') . '</td>';
                echo '<td>' . esc_html($currency . number_format($prize_pool ?: 0, 0)) . '</td>';
                echo '<td>' . ($winner_name ? '<a href="#">' . esc_html($winner_name) . '</a>' : '--') . '</td>';
                echo '<td>';
                echo '<a href="' . get_edit_post_link($tournament->ID) . '" class="button button-small">' . __('Edit', 'poker-tournament-import') . '</a> ';
                echo '<a href="' . get_permalink($tournament->ID) . '" class="button button-small">' . __('View', 'poker-tournament-import') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . __('No tournaments found.', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render players tab content
     */
    private function render_players_tab_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        $top_players = $wpdb->get_results($wpdb->prepare(
            "SELECT tp.player_id,
                    COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish,
                    AVG(tp.finish_position) as avg_finish
             FROM $table_name tp
             GROUP BY tp.player_id
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT 50"
        ));

        echo '<div class="players-detail-view">';
        echo '<h3>' . __('All Players', 'poker-tournament-import') . '</h3>';

        if (!empty($top_players)) {
            echo '<div class="players-table-wrapper">';
            echo '<table class="widefat players-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Player', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Tournaments', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Total Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Total Points', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Best Finish', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Avg Finish', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Actions', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($top_players as $index => $player) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                     LIMIT 1",
                    $player->player_id
                ));

                $rank_class = '';
                if ($index === 0) $rank_class = 'gold';
                elseif ($index === 1) $rank_class = 'silver';
                elseif ($index === 2) $rank_class = 'bronze';

                echo '<tr class="' . esc_attr($rank_class) . '">';
                echo '<td>';
                if ($player_post) {
                    echo '<a href="' . get_permalink($player_post->ID) . '">' . esc_html($player_post->post_title) . '</a>';
                } else {
                    echo esc_html($player->player_id);
                }
                echo '</td>';
                echo '<td>' . esc_html($player->tournaments_played) . '</td>';
                echo '<td>$' . esc_html(number_format($player->total_winnings, 0)) . '</td>';
                echo '<td>' . esc_html(number_format($player->total_points, 1)) . '</td>';
                echo '<td>' . esc_html($player->best_finish) . get_ordinal_suffix($player->best_finish) . '</td>';
                echo '<td>' . esc_html(number_format($player->avg_finish, 1)) . '</td>';
                echo '<td>';
                if ($player_post) {
                    echo '<a href="' . get_edit_post_link($player_post->ID) . '" class="button button-small">' . __('Edit', 'poker-tournament-import') . '</a> ';
                    echo '<a href="' . get_permalink($player_post->ID) . '" class="button button-small">' . __('View', 'poker-tournament-import') . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . __('No players found.', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render series tab content
     */
    private function render_series_tab_content() {
        $series_list = get_posts(array(
            'post_type' => 'tournament_series',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        echo '<div class="series-detail-view">';
        echo '<h3>' . __('All Series', 'poker-tournament-import') . '</h3>';

        if (!empty($series_list)) {
            echo '<div class="series-grid">';
            foreach ($series_list as $series) {
                // Get tournament count
                $tournament_count = count(get_posts(array(
                    'post_type' => 'tournament',
                    'meta_key' => 'series_id',
                    'meta_value' => $series->ID,
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                )));

                echo '<div class="series-card">';
                echo '<h4><a href="' . get_edit_post_link($series->ID) . '">' . esc_html($series->post_title) . '</a></h4>';
                echo '<p>' . sprintf(_n('%d tournament', '%d tournaments', $tournament_count, 'poker-tournament-import'), $tournament_count) . '</p>';
                echo '<div class="series-actions">';
                echo '<a href="' . get_edit_post_link($series->ID) . '" class="button button-small">' . __('Edit', 'poker-tournament-import') . '</a> ';
                echo '[series_overview id="' . $series->ID . '"]';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . __('No series found.', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render analytics tab content
     */
    private function render_analytics_tab_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        echo '<div class="analytics-detail-view">';
        echo '<h3>' . __('Tournament Analytics', 'poker-tournament-import') . '</h3>';

        // Prize pool distribution
        $prize_distribution = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN prize_pool < 500 THEN '< $500'
                    WHEN prize_pool < 1000 THEN '$500-$1,000'
                    WHEN prize_pool < 2000 THEN '$1,000-$2,000'
                    WHEN prize_pool < 5000 THEN '$2,000-$5,000'
                    ELSE '> $5,000'
                END as prize_range,
                COUNT(*) as tournament_count
             FROM (
                 SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) as prize_pool
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_prize_pool'
                 GROUP BY post_id
             ) as prize_data
             GROUP BY prize_range
             ORDER BY MIN(prize_pool)"
        );

        echo '<div class="analytics-grid">';
        echo '<div class="analytics-card">';
        echo '<h4>' . __('Prize Pool Distribution', 'poker-tournament-import') . '</h4>';
        if (!empty($prize_distribution)) {
            echo '<div class="chart-container">';
            foreach ($prize_distribution as $range) {
                $percentage = 0;
                if (isset($total_tournaments)) {
                    $percentage = ($range->tournament_count / $total_tournaments) * 100;
                } else {
                    $total_tournaments = array_sum(array_column($prize_distribution, 'tournament_count'));
                    $percentage = ($range->tournament_count / $total_tournaments) * 100;
                }
                echo '<div class="chart-bar">';
                echo '<div class="chart-label">' . esc_html($range->prize_range) . '</div>';
                echo '<div class="chart-bar-container">';
                echo '<div class="chart-bar-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
                echo '</div>';
                echo '<div class="chart-value">' . esc_html($range->tournament_count) . ' (' . esc_html(round($percentage)) . '%)</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . __('No data available', 'poker-tournament-import') . '</p>';
        }
        echo '</div>';

        // Player participation trends
        echo '<div class="analytics-card">';
        echo '<h4>' . __('Player Participation', 'poker-tournament-import') . '</h4>';
        echo '<p>' . __('Analytics features coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';

        echo '</div>'; // analytics-grid
        echo '</div>'; // analytics-detail-view
    }

    /**
     * Render detailed tournaments view
     */
    private function render_detailed_tournaments_view() {
        echo '<div class="detailed-tournaments-view">';
        echo '<h3>' . __('Tournament Details', 'poker-tournament-import') . '</h3>';
        echo '<p>' . __('Detailed tournament analytics coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';
    }

    /**
     * Render detailed players view
     */
    private function render_detailed_players_view() {
        echo '<div class="detailed-players-view">';
        echo '<h3>' . __('Player Details', 'poker-tournament-import') . '</h3>';
        echo '<p>' . __('Detailed player analytics coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';
    }

    /**
     * Render detailed leaderboard view
     */
    private function render_detailed_leaderboard_view() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        $leaderboard = $wpdb->get_results($wpdb->prepare(
            "SELECT tp.player_id,
                    COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish,
                    AVG(tp.finish_position) as avg_finish
             FROM $table_name tp
             GROUP BY tp.player_id
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT 100"
        ));

        echo '<div class="detailed-leaderboard-view">';
        echo '<h3>' . __('Complete Leaderboard', 'poker-tournament-import') . '</h3>';

        if (!empty($leaderboard)) {
            echo '<div class="leaderboard-table-wrapper">';
            echo '<table class="widefat leaderboard-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Rank', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Player', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Tournaments', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Points', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Best Finish', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Avg Finish', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($leaderboard as $index => $player) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                     LIMIT 1",
                    $player->player_id
                ));

                $rank_class = '';
                if ($index === 0) $rank_class = 'gold';
                elseif ($index === 1) $rank_class = 'silver';
                elseif ($index === 2) $rank_class = 'bronze';

                echo '<tr class="' . esc_attr($rank_class) . '">';
                echo '<td class="rank"><span class="rank-number">' . esc_html($index + 1) . '</span></td>';
                echo '<td>';
                if ($player_post) {
                    echo '<a href="' . get_permalink($player_post->ID) . '">' . esc_html($player_post->post_title) . '</a>';
                } else {
                    echo esc_html($player->player_id);
                }
                echo '</td>';
                echo '<td>' . esc_html($player->tournaments_played) . '</td>';
                echo '<td>$' . esc_html(number_format($player->total_winnings, 0)) . '</td>';
                echo '<td>' . esc_html(number_format($player->total_points, 1)) . '</td>';
                echo '<td>' . esc_html($player->best_finish) . get_ordinal_suffix($player->best_finish) . '</td>';
                echo '<td>' . esc_html(number_format($player->avg_finish, 1)) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . __('No leaderboard data available', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render calendar view
     */
    private function render_calendar_view() {
        echo '<div class="calendar-view">';
        echo '<h3>' . __('Tournament Calendar', 'poker-tournament-import') . '</h3>';
        echo '<p>' . __('Calendar view coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';
    }

    /**
     * **PHASE 1: AJAX handler for player details (drill-through)**
     */
    public function ajax_get_player_details() {
        check_ajax_referer('poker_player_details', 'nonce');

        $player_id = sanitize_text_field($_POST['player_id']);

        if (empty($player_id)) {
            wp_send_json_error(array(
                'message' => __('Player ID is required.', 'poker-tournament-import')
            ));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get player statistics
        $player_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                tp.player_id,
                COUNT(*) as tournaments_played,
                SUM(tp.winnings) as total_winnings,
                SUM(tp.points) as total_points,
                MIN(tp.finish_position) as best_finish,
                AVG(tp.finish_position) as avg_finish,
                SUM(tp.buyins) as total_buyins,
                MAX(tp.winnings) as highest_payout
             FROM {$table_name} tp
             WHERE tp.player_id = %s
             GROUP BY tp.player_id",
            $player_id
        ));

        if (!$player_stats) {
            wp_send_json_error(array(
                'message' => __('Player not found.', 'poker-tournament-import')
            ));
        }

        // Get player name from WordPress posts
        $player_post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID, p.post_title
             FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
             LIMIT 1",
            $player_id
        ));

        $player_name = $player_post ? $player_post->post_title : $player_id;

        // Get recent tournament results for this player
        $recent_tournaments = $wpdb->get_results($wpdb->prepare(
            "SELECT
                tp.finish_position,
                tp.winnings,
                tp.points,
                p.post_title as tournament_name,
                p.post_date as tournament_date,
                pm.meta_value as tournament_date_meta
             FROM {$table_name} tp
             LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.meta_value = tp.tournament_id AND pm2.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm2.post_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_tournament_date'
             WHERE tp.player_id = %s
             ORDER BY p.post_date DESC, tp.finish_position ASC
             LIMIT 10",
            $player_id
        ));

        // Format recent tournaments data
        $formatted_tournaments = array();
        foreach ($recent_tournaments as $tournament) {
            $formatted_tournaments[] = array(
                'tournament_name' => $tournament->tournament_name,
                'finish_position' => intval($tournament->finish_position),
                'winnings' => floatval($tournament->winnings),
                'points' => floatval($tournament->points),
                'tournament_date' => $tournament->tournament_date_meta ?: $tournament->tournament_date
            );
        }

        // Prepare response data
        $player_data = array(
            'player_id' => $player_id,
            'player_name' => $player_name,
            'player_post_id' => $player_post ? $player_post->ID : null,
            'tournaments_played' => intval($player_stats->tournaments_played),
            'total_winnings' => floatval($player_stats->total_winnings),
            'total_points' => floatval($player_stats->total_points),
            'best_finish' => intval($player_stats->best_finish),
            'avg_finish' => floatval($player_stats->avg_finish),
            'total_buyins' => intval($player_stats->total_buyins),
            'highest_payout' => floatval($player_stats->highest_payout),
            'recent_tournaments' => $formatted_tournaments
        );

        wp_send_json_success(array('data' => $player_data));
    }

    /**
     * AJAX handler for statistics refresh
     */
    public function ajax_refresh_statistics() {
        check_ajax_referer('poker_refresh_statistics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $result = $this->statistics_engine->refresh_statistics();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Statistics refreshed successfully!', 'poker-tournament-import'),
                'timestamp' => current_time('mysql'),
                'stats' => $this->statistics_engine->get_dashboard_statistics()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to refresh statistics.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * Handle tournament save/update - refresh statistics
     */
    public function on_tournament_save($post_id, $post, $update) {
        // Only refresh if not an auto-save and not a revision
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        // Only act on tournament post type
        if ($post->post_type !== 'tournament') {
            return;
        }

        // Refresh statistics asynchronously to avoid blocking save
        wp_schedule_single_event(time(), 'poker_refresh_statistics_async', array($post_id));
    }

    /**
     * Handle tournament deletion - refresh statistics
     */
    public function on_tournament_delete($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'tournament') {
            // Refresh statistics asynchronously
            wp_schedule_single_event(time(), 'poker_refresh_statistics_async', array($post_id));
        }
    }

    /**
     * Handle tournament restoration - refresh statistics
     */
    public function on_tournament_restore($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'tournament') {
            // Refresh statistics asynchronously
            wp_schedule_single_event(time(), 'poker_refresh_statistics_async', array($post_id));
        }
    }

    /**
     * Asynchronous statistics refresh handler
     */
    public function async_refresh_statistics($tournament_id = null) {
        if (class_exists('Poker_Statistics_Engine')) {
            try {
                $stats_engine = Poker_Statistics_Engine::get_instance();
                $result = $stats_engine->calculate_all_statistics();

                if ($result) {
                    error_log("Poker Statistics: Async refresh completed for tournament {$tournament_id}");
                    update_option('poker_statistics_last_refresh', current_time('mysql'));
                } else {
                    error_log("Poker Statistics: Async refresh failed for tournament {$tournament_id}");
                }
            } catch (Exception $e) {
                error_log("Poker Statistics: Exception during async refresh: " . $e->getMessage());
            }
        }
    }
}

// Initialize the plugin
Poker_Tournament_Import::get_instance();