<?php
/**
 * Plugin Name: Poker Tournament Import
 * Plugin URI: https://nikielhard.se/tdwpimport
 * Description: Import and display poker tournament results from Tournament Director (.tdt) files. Now with Tournament Manager for creating tournaments without TD software!
 * Version: 3.5.0-beta11
 * Author: Hans Kästel Hård
 * Author URI: https://nikielhard.se
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
define('POKER_TOURNAMENT_IMPORT_VERSION', '3.5.0-beta11');
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
     * PHP 8.2+ compatibility - declare dynamic properties
     */
    private $taxonomies;
    private $formula_validator;
    private $statistics_engine;

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
        $this->includes();

        // Initialize TD3 dependency manager for graceful degradation
        if (class_exists('TDWP_Dependency_Manager')) {
            TDWP_Dependency_Manager::init();
        }


        // Ensure database schema is up to date (has built-in version checking)
        if (class_exists('TDWP_Database_Schema')) {
            TDWP_Database_Schema::create_tables();

            // Force create display tables if they don't exist (for debugging)
            if (isset($_GET['tdwp_force_display_tables']) && current_user_can('manage_options')) {
                TDWP_Database_Schema::force_create_display_tables();
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>TDWP Display System: Forced table creation completed. Check debug.log for details.</p></div>';
                });
            }

            // Reset database version if requested (for debugging)
            if (isset($_GET['tdwp_reset_db_version']) && current_user_can('manage_options')) {
                TDWP_Database_Schema::reset_database_version();
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible"><p>TDWP Display System: Database version reset. Tables will be re-created on next page load.</p></div>';
                });
            }
        }

        // Initialize TD3 display system options if not set
        $this->init_td3_display_options();

        // Initialize TD3 Display Manager for screen management and URL rewriting
        // Use earlier priority to prevent hook timing race conditions
        add_action('init', function() {
            if (class_exists('TDWP_Display_Manager')) {
                TDWP_Display_Manager::get_instance();
            }
        }, 8); // Run before rewrite rule registration (priority 11)

        $this->init_post_types();
        $this->init_taxonomies();
        $this->init_formula_validator();
        $this->init_shortcodes();
        $this->init_tournament_clock_shortcode();
        $this->init_statistics_engine();
        $this->init_admin_bar_widget();

        // Global heartbeat for continuous tournament clock updates
        add_action('admin_enqueue_scripts', array($this, 'enqueue_global_heartbeat'));
        add_filter('heartbeat_received', array($this, 'global_heartbeat_handler'), 10, 2);

        // Check for plugin update and refresh statistics if needed
        $this->check_plugin_upgmdate();

        // Register custom template loading for our post types
        add_filter('template_include', array($this, 'load_custom_templates'));

        // Frontend assets - only load on frontend, NOT in admin
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        }

        
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

            // Initialize data mart cleaner
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-data-mart-cleaner.php';
            new Poker_Data_Mart_Cleaner();

            // **PHASE 1: Tournament Manager admin pages**
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/tournament-templates-page.php';
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/layout-builder-page.php';
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/blind-builder-page.php';
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/prize-calculator-page.php';
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/player-management-page.php';

            // **PHASE 2: Live Operations admin**
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-control-page.php';

            // **PHASE 3: Live Tournament Wizard & Converter**
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php';
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-converter.php';
            new TDWP_Live_Tournament_Wizard();

            // **PHASE 4: TD3 Display System - Screen Management**
            if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/screen-management-page.php')) {
                require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/screen-management-page.php';
            }

            // **PHASE 2 Week 2-3: Tournament Manager AJAX & Control Page**
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-tournament-manager-ajax.php';
        }

        // Initialize bulk import (OUTSIDE is_admin() for REST API access)
        // Security: All REST endpoints require 'manage_options' capability
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-bulk-import.php';
        Poker_Tournament_Bulk_Import::get_instance();

        // AJAX handlers for tabbed interface
        add_action('wp_ajax_tdwp_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_nopriv_tdwp_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_tdwp_series_load_more', array($this, 'ajax_series_load_more'));
        add_action('wp_ajax_nopriv_tdwp_series_load_more', array($this, 'ajax_series_load_more'));

        // AJAX handlers for formula validator
        add_action('wp_ajax_tdwp_validate_formula', array($this, 'ajax_validate_formula'));
        add_action('wp_ajax_tdwp_save_formula', array($this, 'ajax_save_formula'));
        add_action('wp_ajax_tdwp_delete_formula', array($this, 'ajax_delete_formula'));
        add_action('wp_ajax_tdwp_get_formula', array($this, 'ajax_get_formula'));

        // AJAX handlers for series standings
        add_action('wp_ajax_tdwp_export_standings', array($this, 'ajax_export_standings'));

        // Dashboard AJAX handlers are now registered in admin/class-admin.php to avoid conflicts

        // **PHASE 1: AJAX handlers for player drill-through**
        add_action('wp_ajax_tdwp_get_player_details', array($this, 'ajax_get_player_details'));
        add_action('wp_ajax_nopriv_tdwp_get_player_details', array($this, 'ajax_get_player_details'));

        // AJAX handlers for statistics refresh
        add_action('wp_ajax_tdwp_refresh_statistics', array($this, 'ajax_refresh_statistics'));

        // AJAX handlers for data mart cleaner
        add_action('wp_ajax_tdwp_clean_data_mart', array($this, 'ajax_clean_data_mart'));

        // AJAX handlers for tournament chronology reconstruction
        add_action('wp_ajax_tdwp_reconstruct_chronology', array($this, 'ajax_reconstruct_chronology'));
        add_action('wp_ajax_tdwp_upload_tdt_for_tournament', array($this, 'ajax_upload_tdt_for_tournament'));

        // AJAX handlers for enhanced data mart cleaning
        add_action('wp_ajax_tdwp_clean_statistics_enhanced', array($this, 'ajax_clean_statistics_enhanced'));
        add_action('wp_ajax_tdwp_clean_financial_enhanced', array($this, 'ajax_clean_financial_enhanced'));
        add_action('wp_ajax_tdwp_clean_player_data_enhanced', array($this, 'ajax_clean_player_data_enhanced'));
        add_action('wp_ajax_tdwp_clean_analytics_enhanced', array($this, 'ajax_clean_analytics_enhanced'));
        add_action('wp_ajax_tdwp_clean_options_enhanced', array($this, 'ajax_clean_options_enhanced'));
        add_action('wp_ajax_tdwp_clean_all_enhanced', array($this, 'ajax_clean_all_enhanced'));
        add_action('wp_ajax_tdwp_get_cleaning_status', array($this, 'ajax_get_cleaning_status'));

        // AJAX handlers for frontend dashboard (logged-in and public users)
        add_action('wp_ajax_tdwp_frontend_import_tournament', array($this, 'ajax_frontend_import_tournament'));
        add_action('wp_ajax_nopriv_tdwp_frontend_import_tournament', array($this, 'ajax_frontend_import_tournament'));
        add_action('wp_ajax_tdwp_frontend_refresh_statistics', array($this, 'ajax_frontend_refresh_statistics'));
        add_action('wp_ajax_nopriv_tdwp_frontend_refresh_statistics', array($this, 'ajax_frontend_refresh_statistics'));

        // Beta21: Log handler registration confirmation
        error_log('[TDWP Beta21] AJAX handlers registered successfully');

        // Beta21: Early AJAX detection hook
        add_action('admin_init', function() {
            if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action'])) {
                error_log('[TDWP Beta21] AJAX request detected, action: ' . $_REQUEST['action']);
                error_log('[TDWP Beta21] User logged in: ' . (is_user_logged_in() ? 'yes (' . get_current_user_id() . ')' : 'no'));
            }
        });

        // Beta21: Test handler to verify AJAX system works
        add_action('wp_ajax_tdwp_ajax_test', 'tdwp_ajax_test_handler');
        add_action('wp_ajax_nopriv_tdwp_ajax_test', 'tdwp_ajax_test_handler');

        // **PHASE 4: TD3 Display System AJAX handlers**
        add_action('wp_ajax_tdwp_get_tournament_data', array($this, 'ajax_get_tournament_data'));
        add_action('wp_ajax_nopriv_tdwp_get_tournament_data', array($this, 'ajax_get_tournament_data'));
        add_action('wp_ajax_tdwp_unregister_screen', array($this, 'ajax_unregister_screen'));
        add_action('wp_ajax_nopriv_tdwp_unregister_screen', array($this, 'ajax_unregister_screen'));
        add_action('wp_ajax_tdwp_dashboard_tournaments_filtered', array($this, 'ajax_dashboard_tournaments_filtered'));
        add_action('wp_ajax_nopriv_tdwp_dashboard_tournaments_filtered', array($this, 'ajax_dashboard_tournaments_filtered'));
        // Note: tdwp_load_tournaments_data handler is in admin/class-admin.php (duplicate removed)

        // **PHASE 4.1: Display System - Live Tournament Integration AJAX handlers**
        if (class_exists('TDWP_Display_Manager')) {
            $display_manager = TDWP_Display_Manager::get_instance();
            add_action('wp_ajax_tdwp_get_tournament_options', array($display_manager, 'ajax_get_tournament_options'));
            add_action('wp_ajax_tdwp_auto_assign_screen', array($display_manager, 'ajax_auto_assign_screen'));
        }

        // AJAX handlers for dependency manager
        
        // Hook into tournament creation and updates
        add_action('save_post_tournament', array($this, 'on_tournament_save'), 10, 3);
        add_action('wp_trash_post', array($this, 'on_tournament_delete'));
        add_action('untrash_post', array($this, 'on_tournament_restore'));

        // Clear overall standings cache on tournament changes
        if (class_exists('Poker_Series_Standings_Calculator')) {
            $standings_calculator = new Poker_Series_Standings_Calculator();
            add_action('save_post_tournament', array($standings_calculator, 'clear_overall_standings_cache'));
            add_action('before_delete_post', array($standings_calculator, 'clear_overall_standings_cache'));
        }

        // Hook for asynchronous statistics refresh
        add_action('poker_refresh_statistics_async', array($this, 'async_refresh_statistics'));
    }

    /**
     * Check for plugin update and refresh statistics if needed
     */
    private function check_plugin_upgmdate() {
        $last_version = get_option('tdwp_import_last_version', '0');

        if ($last_version !== POKER_TOURNAMENT_IMPORT_VERSION) {
            // Plugin was updated, force refresh statistics
            error_log("Poker Import: Plugin updated from {$last_version} to " . POKER_TOURNAMENT_IMPORT_VERSION . ", refreshing statistics");

            if (class_exists('Poker_Statistics_Engine')) {
                $stats_engine = Poker_Statistics_Engine::get_instance();
                $result = $stats_engine->calculate_all_statistics();

                if ($result) {
                    error_log("Poker Import: Statistics refreshed successfully after plugin update");
                    update_option('tdwp_statistics_last_refresh', current_time('mysql'));
                } else {
                    error_log("Poker Import: Failed to refresh statistics after plugin update");
                }

                // **v2.4.34: ONE-TIME MIGRATION** - Populate ROI table if empty
                global $wpdb;
                $roi_table = $wpdb->prefix . 'poker_player_roi';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $roi_count = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table");

                if ($roi_count == 0) {
                    error_log("ROI Migration: ROI table is empty, triggering migration");
                    $migration_result = $stats_engine->migrate_populate_roi_table();

                    if ($migration_result['success']) {
                        error_log("ROI Migration: SUCCESS - {$migration_result['records_created']} records created from {$migration_result['tournaments_processed']} tournaments");
                        update_option('tdwp_roi_migration_complete', POKER_TOURNAMENT_IMPORT_VERSION);
                    } else {
                        error_log("ROI Migration: FAILED - See previous log entries for details");
                    }
                } else {
                    error_log("ROI Migration: Skipped - ROI table already has {$roi_count} records");
                }
            }

            // Update the stored version
            update_option('tdwp_import_last_version', POKER_TOURNAMENT_IMPORT_VERSION);

            // Flush rewrite rules after version update (v2.8.6: auto-refresh permalinks)
            flush_rewrite_rules();
        }

        // Run prefix migration (v2.9.15: WordPress.org compliance)
        $this->migrate_poker_to_tdwp_prefixes();

        // **v3.0.0+: Ensure Tournament Manager tables exist (Phase 1 & 2)**
        $this->ensure_all_tables_exist();
    }

    /**
     * Handle background PDF library download
     *
     * @since 3.3.0
     */
    
    /**
     * Migrate poker_ prefixes to tdwp_ prefixes
     * Version 2.9.15 - WordPress.org compliance
     */
    private function migrate_poker_to_tdwp_prefixes() {
        $migrated = get_option('tdwp_prefix_migration_v1', false);
        if ($migrated) {
            return;
        }

        error_log('TDWP Migration: Starting poker_ to tdwp_ prefix migration');

        // Map of old option names to new ones
        $options_map = array(
            'poker_active_season_formula' => 'tdwp_active_season_formula',
            'poker_active_tournament_formula' => 'tdwp_active_tournament_formula',
            'poker_currency_position' => 'tdwp_currency_position',
            'poker_currency_symbol' => 'tdwp_currency_symbol',
            'poker_formula_debug_mode' => 'tdwp_formula_debug_mode',
            'poker_formulas' => 'tdwp_formulas',
            'poker_hit_counting_method' => 'tdwp_hit_counting_method',
            'poker_import_auto_publish' => 'tdwp_import_auto_publish',
            'poker_import_debug_logging' => 'tdwp_import_debug_logging',
            'poker_import_debug_mode' => 'tdwp_import_debug_mode',
            'poker_import_default_buyin' => 'tdwp_import_default_buyin',
            'poker_import_last_version' => 'tdwp_import_last_version',
            'poker_import_show_debug_stats' => 'tdwp_import_show_debug_stats',
            'poker_roi_migration_complete' => 'tdwp_roi_migration_complete',
            'poker_statistics_last_refresh' => 'tdwp_statistics_last_refresh',
            'poker_tournament_formulas' => 'tdwp_tournament_formulas',
        );

        $migrated_count = 0;
        foreach ($options_map as $old => $new) {
            $value = get_option($old);
            if ($value !== false) {
                update_option($new, $value);
                $migrated_count++;
                error_log("TDWP Migration: Migrated {$old} to {$new}");
            }
        }

        update_option('tdwp_prefix_migration_v1', true);
        error_log("TDWP Migration: Complete - {$migrated_count} options migrated");
    }

    /**
     * Ensure Tournament Manager tables exist (Phase 1 & 2)
     *
     * Checks if tables exist and forces creation if missing.
     * Uses transient to avoid checking on every page load.
     *
     * @since 3.0.0
     * @since 3.1.0 Updated to check Phase 2 tables
     */
    private function ensure_all_tables_exist() {
        // Run schema migration FIRST (has its own completion check, runs until done)
        if (class_exists('TDWP_Database_Schema')) {
            TDWP_Database_Schema::migrate_schema();
            TDWP_Database_Schema::migrate_beta20_financial_policy();
            TDWP_Database_Schema::migrate_beta_seating_registration_id();
        }

        // Check once per hour using transient for table existence verification
        if (get_transient('tdwp_all_tables_checked')) {
            return;
        }

        global $wpdb;

        // Check if all Tournament Manager tables exist (Phase 1 & 2)
        $tables_to_check = array(
            // Phase 1: Tournament Setup
            $wpdb->prefix . 'tdwp_tournament_templates',
            $wpdb->prefix . 'tdwp_blind_schedules',
            $wpdb->prefix . 'tdwp_blind_levels',
            $wpdb->prefix . 'tdwp_prize_structures',
            // Phase 2: Live Operations
            $wpdb->prefix . 'tdwp_tournament_live_state',
            $wpdb->prefix . 'tdwp_tournament_events',
            // Phase 2 Week 2-3: Table Management
            $wpdb->prefix . 'tdwp_tournament_tables',
            $wpdb->prefix . 'tdwp_tournament_seats',
        );

        $missing_tables = array();
        foreach ($tables_to_check as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table existence check
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                $missing_tables[] = $table;
            }
        }

        if (!empty($missing_tables)) {
            error_log('Tournament Manager Tables: Missing tables detected - ' . implode(', ', $missing_tables));

            // Force table creation by resetting db version option
            delete_option('tdwp_db_version');

            if (class_exists('TDWP_Database_Schema')) {
                $result = TDWP_Database_Schema::create_tables();
                if ($result) {
                    error_log('Tournament Manager Tables: Successfully created missing tables');
                    TDWP_Database_Schema::insert_default_templates();
                    error_log('Tournament Manager Tables: Default templates inserted');
                } else {
                    error_log('Tournament Manager Tables: ERROR - Failed to create tables');
                }
            }
        }

        // Set transient to check again in 1 hour
        set_transient('tdwp_all_tables_checked', true, HOUR_IN_SECONDS);
    }

    /**
     * Enqueue frontend styles and scripts
     */
    public function enqueue_frontend_assets() {
        // CRITICAL: Double-check we're not in admin - prevent frontend scripts from loading in dashboard
        if (is_admin()) {
            return;
        }

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
        // IMPORTANT: Use pokerImportFrontend to avoid collision with admin pokerImport
        wp_localize_script(
            'poker-tournament-import-frontend',
            'pokerImportFrontend',
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
        // v2.4.9: AST-based TDT Parser (replaces regex-based extraction)
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-tdt-lexer.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-tdt-ast-parser.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-tdt-domain-mapper.php';

        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-parser.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-debug.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-formula-validator.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-series-standings.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-statistics-engine.php';

        // **PHASE 1: Tournament Manager**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-database-schema.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-debug-logger.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-template.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-blind-schedule.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-blind-level.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-structure.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-prize-calculator.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-manager.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/class-player-importer.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-player-registration.php';

        // **PHASE 2: Live Operations**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-live.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-clock.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-events.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-tournament-clock-shortcode.php';

        // **PHASE 2 Week 2-3: Table Management**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-live-state-manager.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-table-manager.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-seat-manager.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-table-balancer.php';

        // **PHASE 1 Beta16: Tournament Player Manager**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-player-manager.php';

        // **PHASE 2 Completion: Player Operations & Transactions**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-transaction-logger.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-operations.php';

        // **PHASE 3: Active Tournament Persistence & Admin Bar**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-active-tournament-manager.php';
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-admin-bar-widget.php';

        // **TD3 INTEGRATION: Display System Classes**
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-td3-database-schema.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-td3-database-schema.php';
        }
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-td3-migration.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-td3-migration.php';
        }

        // Include TD3 Display System classes
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-template-engine.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-template-engine.php';
        }
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-layout-builder.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-layout-builder.php';
        }
        // Initialize TD3 Dependency Manager first
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-dependency-manager.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-dependency-manager.php';
        }

        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-display-manager.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-display-manager.php';
        }
        if (file_exists(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-display-shortcode.php')) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-display-shortcode.php';
            TDWP_Display_Shortcode::get_instance();
        }

        // **Poker Dashboard Shortcode**
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-poker-dashboard-shortcode.php';
      }

/**
     * Initialize TD3 Display System options
     *
     * @since 3.4.0
     */
    private function init_td3_display_options() {
        $display_options = array(
            'tdwp_display_system_enabled' => true,
            'tdwp_display_default_refresh_rate' => 5, // seconds
            'tdwp_display_cache_duration' => 300, // 5 minutes
            'tdwp_display_max_screens' => 10,
            'tdwp_display_token_validation' => true,
            'tdwp_display_responsive_breakpoints' => array(
                'small' => 768,
                'medium' => 1024,
                'large' => 1440,
            ),
        );

        foreach ( $display_options as $option => $default_value ) {
            if ( get_option( $option ) === false ) {
                add_option( $option, $default_value );
            }
        }
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
     * Initialize tournament clock shortcode
     */
    private function init_tournament_clock_shortcode() {
        new TDWP_Tournament_Clock_Shortcode();
    }

    /**
     * Initialize admin bar widget
     *
     * Shows active tournament in WordPress admin bar.
     *
     * @since 3.1.0
     */
    private function init_admin_bar_widget() {
        if (is_admin_bar_showing()) {
            new TDWP_Admin_Bar_Widget();
        }
    }

    /**
     * Enqueue global tournament heartbeat
     *
     * Runs on ALL admin pages to keep tournament clock ticking continuously.
     * Only enqueues if user has an active tournament.
     *
     * Uses WordPress Heartbeat API to send tournament updates every 60 seconds
     * regardless of which admin page user is viewing.
     *
     * @since 3.1.0
     */
    public function enqueue_global_heartbeat() {
        // Only for users who can manage tournaments
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get active tournament ID
        $active_tournament_id = TDWP_Active_Tournament_Manager::get_active_tournament(get_current_user_id());

        // Only enqueue if user has active tournament
        if (!$active_tournament_id) {
            return;
        }

        // Ensure WordPress Heartbeat is loaded
        wp_enqueue_script('heartbeat');

        // Enqueue our global heartbeat handler
        wp_enqueue_script(
            'tdwp-global-tournament-heartbeat',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tdwp-global-tournament-heartbeat.js',
            array('jquery', 'heartbeat'),
            POKER_TOURNAMENT_IMPORT_VERSION,
            true
        );

        // Localize with active tournament ID and i18n strings
        wp_localize_script(
            'tdwp-global-tournament-heartbeat',
            'tdwpGlobalHeartbeat',
            array(
                'activeTournamentId' => $active_tournament_id,
                'i18n' => array(
                    'level' => __('Level %d', 'poker-tournament-import'),
                    'paused' => __('Paused (L%d)', 'poker-tournament-import'),
                    'break' => __('On Break', 'poker-tournament-import'),
                    'setup' => __('Setup', 'poker-tournament-import'),
                ),
            )
        );
    }

    /**
     * Global heartbeat handler for tournament clock
     *
     * Processes heartbeat from ALL admin pages to keep tournament ticking continuously.
     *
     * @since 3.1.0
     * @param array $response Heartbeat response.
     * @param array $data     Heartbeat data.
     * @return array Modified response.
     */
    public function global_heartbeat_handler($response, $data) {
        // Check if this is tournament heartbeat from global script
        if (empty($data['tdwp_tournament_id'])) {
            return $response;
        }

        $tournament_id = absint($data['tdwp_tournament_id']);

        TDWP_Debug_Logger::log('HEARTBEAT', 'Global heartbeat received', array(
            'tournament_id' => $tournament_id,
            'page' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
        ));

        // Get current state
        $live_manager = new TDWP_Tournament_Live();
        $state = $live_manager->get_by_tournament_id($tournament_id);

        if (!$state) {
            TDWP_Debug_Logger::log('HEARTBEAT', 'No state found for tournament', array('tournament_id' => $tournament_id));
            return $response;
        }

        // Tick for elapsed time if running
        if ('running' === $state->status) {
            // Prevent double-ticking from multiple heartbeat handlers
            $last_tick_time = get_transient('tdwp_last_tick_' . $tournament_id);
            if ($last_tick_time && (time() - $last_tick_time) < 2) {
                TDWP_Debug_Logger::log('HEARTBEAT', 'Skipping duplicate tick', array(
                    'last_tick' => $last_tick_time,
                    'now' => time(),
                    'diff' => time() - $last_tick_time
                ));
                // Return current state without ticking
                $response['tdwp_live_state'] = array(
                    'tournament_id'     => $state->tournament_id,
                    'status'            => $state->status,
                    'current_level'     => $state->current_level,
                    'time_remaining'    => $state->time_remaining,
                    'total_players'     => $state->total_players,
                    'remaining_players' => $state->remaining_players,
                    'total_rebuys'      => $state->total_rebuys,
                    'total_addons'      => $state->total_addons,
                    'prize_pool'        => $state->prize_pool,
                );
                return $response;
            }

            $elapsed = time() - strtotime($state->updated_at);
            $elapsed = min(max(0, $elapsed), 30); // Cap between 0-30 seconds

            TDWP_Debug_Logger::log('HEARTBEAT', 'Preparing to tick', array(
                'tournament_id' => $tournament_id,
                'elapsed' => $elapsed,
                'time_before_tick' => $state->time_remaining,
                'updated_at' => $state->updated_at
            ));

            if ($elapsed > 0) {
                // Mark that we're ticking now
                set_transient('tdwp_last_tick_' . $tournament_id, time(), 5);

                $clock_manager = new TDWP_Tournament_Clock();
                $clock_manager->tick($tournament_id, $elapsed);
                // Refresh state after tick
                $state = $live_manager->get_by_tournament_id($tournament_id);

                TDWP_Debug_Logger::log('HEARTBEAT', 'State after tick', array(
                    'time_remaining' => $state->time_remaining,
                    'updated_at' => $state->updated_at,
                    'should_be_fresh' => 'YES - updated_at should equal current time'
                ));
            }
        }

        // Return fresh state for JavaScript
        if ($state) {
            TDWP_Debug_Logger::log('HEARTBEAT', 'Returning state to client', array(
                'tournament_id' => $state->tournament_id,
                'status' => $state->status,
                'time_remaining' => $state->time_remaining,
                'current_level' => $state->current_level
            ));

            $response['tdwp_live_state'] = array(
                'tournament_id'     => $state->tournament_id,
                'status'            => $state->status,
                'current_level'     => $state->current_level,
                'time_remaining'    => $state->time_remaining,
                'total_players'     => $state->total_players,
                'remaining_players' => $state->remaining_players,
                'total_rebuys'      => $state->total_rebuys,
                'total_addons'      => $state->total_addons,
                'prize_pool'        => $state->prize_pool,
            );
        }

        return $response;
    }

  
    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Create database tables if needed
        $this->create_database_tables();

        // **PHASE 1: Create Tournament Manager tables**
        if (class_exists('TDWP_Database_Schema')) {
            TDWP_Database_Schema::create_tables();
            TDWP_Database_Schema::insert_default_templates();

            // **PHASE 2: Create TD3 Display System tables**
            TDWP_Database_Schema::force_create_display_tables();
            error_log('TDWP Plugin Activation: TD3 Display tables creation completed');
        }

        // Force statistics table creation and initial calculation
        $this->ensure_statistics_table_exists();

        // Initialize and calculate statistics
        if (class_exists('Poker_Statistics_Engine')) {
            $stats_engine = Poker_Statistics_Engine::get_instance();
            $stats_engine->calculate_all_statistics();
        }

        // Set version to force refresh on first load
        update_option('tdwp_import_last_version', POKER_TOURNAMENT_IMPORT_VERSION);

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

        // **PHASE 2.9: Bulk Import Infrastructure Tables**

        // Table for bulk import batches
        $batches_table_name = $wpdb->prefix . 'poker_import_batches';
        $batches_sql = "CREATE TABLE $batches_table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_uuid varchar(36) NOT NULL COMMENT 'UUID for batch identification',
            user_id bigint(20) UNSIGNED NOT NULL,
            total_files int NOT NULL DEFAULT 0,
            processed_count int NOT NULL DEFAULT 0,
            success_count int NOT NULL DEFAULT 0,
            error_count int NOT NULL DEFAULT 0,
            status enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            processing_mode enum('sequential','parallel') NOT NULL DEFAULT 'sequential',
            options longtext DEFAULT NULL COMMENT 'JSON: skip_duplicates, update_existing, email_notify',
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_uuid (batch_uuid),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($batches_sql);

        // Table for bulk import batch files
        $batch_files_table_name = $wpdb->prefix . 'poker_import_batch_files';
        $batch_files_sql = "CREATE TABLE $batch_files_table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) UNSIGNED NOT NULL,
            filename varchar(255) NOT NULL,
            filepath varchar(500) NOT NULL COMMENT 'Temp storage path',
            filesize int NOT NULL DEFAULT 0,
            file_hash varchar(64) DEFAULT NULL COMMENT 'SHA256 for deduplication',
            status enum('pending','processing','completed','failed','skipped') NOT NULL DEFAULT 'pending',
            tournament_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Created post ID',
            error_message text DEFAULT NULL,
            parse_details longtext DEFAULT NULL COMMENT 'JSON: players, buy_ins, duration',
            processing_time_ms int DEFAULT NULL,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY batch_id (batch_id),
            KEY status (status),
            KEY file_hash (file_hash),
            KEY tournament_id (tournament_id)
        ) $charset_collate;";

        dbDelta($batch_files_sql);

        // Log successful table creation
        error_log("Poker Import: Phase 2.1 Financial tables created successfully");
        error_log("Poker Import: Phase 2.9 Bulk import tables created successfully");
    }

    /**
     * Ensure statistics table exists with proper error handling
     */
    private function ensure_statistics_table_exists() {
        global $wpdb;

        $stats_table_name = $wpdb->prefix . 'poker_statistics';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $stats_sql parameter is being prepared here
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
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
                        <a href="<?php echo esc_url(get_permalink($tournament->ID)); ?>">
                            <?php echo esc_html($tournament->post_title); ?>
                        </a>
                    </td>
                    <td class="tournament-date">
                        <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_gmdate('M j, Y', $tournament->ID)); ?>
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
                </tr>
                <?php
            endforeach;
        }

        $output = ob_get_clean();
        echo wp_kses_post($output);
        wp_die();
    }

    /**
     * AJAX handler for formula validation
     */
    public function ajax_validate_formula() {
        check_ajax_referer('poker_formula_validator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $formula = sanitize_textarea_field($_POST['formula']);
        $test_data = $_POST['test_data'];

        if (empty($formula)) {
            echo '<div class="error"><p>' . esc_html__('Formula cannot be empty.', 'poker-tournament-import') . '</p></div>';
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
        echo wp_kses_post($output);
        wp_die();
    }

    /**
     * AJAX handler for saving formulas
     */
    public function ajax_save_formula() {
        check_ajax_referer('poker_formula_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $name = sanitize_text_field($_POST['formula_name']);
        $formula_data = array(
            'name' => sanitize_text_field($_POST['display_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'formula' => sanitize_textarea_field(wp_unslash($_POST['formula'])),
            'dependencies' => sanitize_textarea_field(wp_unslash($_POST['dependencies'])),
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $name = sanitize_text_field($_POST['formula_key']);
        $validator = new Poker_Tournament_Formula_Validator();
        $formula = $validator->get_formula($name);

        if ($formula) {
            // Check if this is a default formula
            $default_formulas = $validator->get_default_formulas();
            $formula['is_default'] = isset($default_formulas[$name]);

            wp_send_json_success($formula);
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
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
                echo '<div class="error">' . esc_html__('Unknown view', 'poker-tournament-import') . '</div>';
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
                echo '<div class="error">' . esc_html__('Unknown view type', 'poker-tournament-import') . '</div>';
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
        $filename = 'poker-tournament-report-' . gmdate('Y-m-d') . '.csv';
        $filepath = get_temp_dir() . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Required for backup creation
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
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
                $tournament_date ?: get_the_gmdate('Y-m-d', $tournament->ID),
                $players_count ?: 0,
                $prize_pool ?: 0,
                $winner_name,
                $winner_winnings
            ));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing backup file
        fclose($handle);

        // Create download URL
        $upload_dir = wp_upload_dir();
        $report_dir = $upload_dir['basedir'] . '/poker-reports/';
        if (!file_exists($report_dir)) {
            wp_mkdir_p($report_dir);
        }

        $final_filepath = $report_dir . $filename;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Renaming backup file
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
        // Get all seasons for filter
        $seasons = get_terms(array(
            'taxonomy' => 'season',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'DESC'
        ));

        echo '<div class="tournaments-detail-view">';
        echo '<div class="tournaments-header">';
        echo '<h3>' . esc_html__('All Tournaments', 'poker-tournament-import') . '</h3>';
        
        // Season filter dropdown
        if (!empty($seasons)) {
            echo '<div class="tournament-season-filter">';
            echo '<label for="tournament-season-select">' . esc_html__('Season:', 'poker-tournament-import') . '</label>';
            echo '<select id="tournament-season-select" class="season-filter-select">';
            echo '<option value="all">' . esc_html__('All Seasons', 'poker-tournament-import') . '</option>';
            foreach ($seasons as $season) {
                echo '<option value="' . esc_attr($season->term_id) . '">' . esc_html($season->name) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }
        echo '</div>';

        // Container for AJAX-loaded tournament grid with scroll wrapper
        echo '<div class="tournament-leaderboard-scroll-wrapper">';
        echo '<div id="tournaments-grid-container" class="tournaments-grid-container">';
        echo '<div class="loading-spinner">' . esc_html__('Loading tournaments...', 'poker-tournament-import') . '</div>';
        echo '</div>';
        echo '</div>';

        // Load more button
        echo '<div class="tournament-load-more-container" style="display:none;">';
        echo '<button id="tournament-load-more-btn" class="button button-primary">' . esc_html__('Load More', 'poker-tournament-import') . '</button>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render players tab content with enhanced statistics
     */
    private function render_players_tab_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $roi_table = $wpdb->prefix . 'poker_player_roi';

        // Enhanced query with finish position counts, bubble, last place, and hits
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $top_players = $wpdb->get_results(
            "SELECT
                roi.player_id,
                COUNT(DISTINCT tp.tournament_id) as tournaments_played,
                SUM(roi.net_profit) as total_winnings,
                SUM(tp.points) as total_points,
                MIN(tp.finish_position) as best_finish,
                AVG(tp.finish_position) as avg_finish,
                SUM(CASE WHEN tp.finish_position = 1 THEN 1 ELSE 0 END) as first_place_count,
                SUM(CASE WHEN tp.finish_position = 2 THEN 1 ELSE 0 END) as second_place_count,
                SUM(CASE WHEN tp.finish_position = 3 THEN 1 ELSE 0 END) as third_place_count,
                SUM(tp.hits) as total_hits,
                SUM(CASE
                    WHEN tp.finish_position = (
                        SELECT COUNT(*) + 1
                        FROM {$table_name} tp2
                        WHERE tp2.tournament_id = tp.tournament_id AND tp2.winnings > 0
                    )
                    THEN 1 ELSE 0
                END) as bubble_count,
                SUM(CASE
                    WHEN tp.finish_position = (
                        SELECT MAX(finish_position)
                        FROM {$table_name} tp3
                        WHERE tp3.tournament_id = tp.tournament_id
                    )
                    THEN 1 ELSE 0
                END) as last_place_count
             FROM {$roi_table} roi
             LEFT JOIN {$table_name} tp ON roi.player_id = tp.player_id AND roi.tournament_id = tp.tournament_id
             GROUP BY roi.player_id
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT 50"
        );

        // Calculate season points for each player
        foreach ($top_players as &$player) {
            $player->season_points = $this->calculate_player_season_points($player->player_id);
        }

        // Sort by season points DESC, then total points DESC
        usort($top_players, function($a, $b) {
            if ($a->season_points != $b->season_points) {
                return $b->season_points <=> $a->season_points;
            }
            return $b->total_points <=> $a->total_points;
        });

        echo '<div class="players-detail-view">';
        echo '<h3>' . esc_html__('All Players', 'poker-tournament-import') . '</h3>';

        if (!empty($top_players)) {
            echo '<div class="player-leaderboard-scroll-wrapper">';
            echo '<div class="player-leaderboard-table">';

            // Header row
            echo '<div class="table-header">';
            echo '<div class="header-rank">' . esc_html__('Rank', 'poker-tournament-import') . '</div>';
            echo '<div class="header-player">' . esc_html__('Player', 'poker-tournament-import') . '</div>';
            echo '<div class="header-tournaments">' . esc_html__('Tournaments', 'poker-tournament-import') . '</div>';
            echo '<div class="header-first">' . esc_html__('1st', 'poker-tournament-import') . '</div>';
            echo '<div class="header-second">' . esc_html__('2nd', 'poker-tournament-import') . '</div>';
            echo '<div class="header-third">' . esc_html__('3rd', 'poker-tournament-import') . '</div>';
            echo '<div class="header-bubble">' . esc_html__('Bubble', 'poker-tournament-import') . '</div>';
            echo '<div class="header-last">' . esc_html__('Last', 'poker-tournament-import') . '</div>';
            echo '<div class="header-hits">' . esc_html__('Hits', 'poker-tournament-import') . '</div>';
            echo '<div class="header-points sortable" data-sort="points">' . esc_html__('Points', 'poker-tournament-import') . '<span class="sort-indicator"></span></div>';
            echo '<div class="header-best">' . esc_html__('Best', 'poker-tournament-import') . '</div>';
            echo '<div class="header-avg">' . esc_html__('Avg', 'poker-tournament-import') . '</div>';
            echo '<div class="header-season-points sortable active" data-sort="season_points">' . esc_html__('Season Pts', 'poker-tournament-import') . '<span class="sort-indicator">▼</span></div>';
            echo '</div>';

            // Player rows
            foreach ($top_players as $index => $player) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                     LIMIT 1",
                    $player->player_id
                ));

                echo '<div class="table-row" data-player-id="' . esc_attr($player->player_id) . '"';
                echo ' data-points="' . esc_attr($player->total_points) . '"';
                echo ' data-season-points="' . esc_attr($player->season_points) . '">';

                echo '<div class="rank-cell">' . esc_html($index + 1) . '</div>';

                echo '<div class="player-cell">';
                if ($player_post) {
                    echo '<a href="' . esc_url(get_permalink($player_post->ID)) . '">' . esc_html($player_post->post_title) . '</a>';
                } else {
                    echo esc_html($player->player_id);
                }
                echo '</div>';

                echo '<div class="tournaments-cell">' . esc_html($player->tournaments_played) . '</div>';
                echo '<div class="first-cell">' . esc_html($player->first_place_count ?? 0) . '</div>';
                echo '<div class="second-cell">' . esc_html($player->second_place_count ?? 0) . '</div>';
                echo '<div class="third-cell">' . esc_html($player->third_place_count ?? 0) . '</div>';
                echo '<div class="bubble-cell">' . esc_html($player->bubble_count ?? 0) . '</div>';
                echo '<div class="last-cell">' . esc_html($player->last_place_count ?? 0) . '</div>';
                echo '<div class="hits-cell">' . esc_html($player->total_hits ?? 0) . '</div>';
                echo '<div class="points-cell">' . esc_html(number_format(floatval($player->total_points), 1)) . '</div>';
                echo '<div class="best-finish-cell">' . esc_html($player->best_finish) . '</div>';
                echo '<div class="avg-finish-cell">' . esc_html(number_format(floatval($player->avg_finish), 1)) . '</div>';
                echo '<div class="season-points-cell">' . esc_html(number_format(floatval($player->season_points), 1)) . '</div>';

                echo '</div>';
            }

            echo '</div>'; // player-leaderboard-table
            echo '</div>'; // player-leaderboard-scroll-wrapper
        } else {
            echo '<p>' . esc_html__('No players found.', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Calculate season points for a player using configured formula
     */
    private function calculate_player_season_points($player_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get configured season formula
        $formula_key = get_option('tdwp_active_season_formula', 'season_total');

        // Get all tournament points for player
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT points, winnings, hits, finish_position
             FROM {$table_name}
             WHERE player_id = %s",
            $player_id
        ));

        if (empty($results)) {
            return 0;
        }

        // Collect data
        $tournament_points = array();
        $total_points = 0;
        $total_winnings = 0;
        $total_hits = 0;
        $best_finish = PHP_INT_MAX;
        $tournaments_played = count($results);
        $finishes = array();

        foreach ($results as $result) {
            $points = floatval($result->points);
            $tournament_points[] = $points;
            $total_points += $points;
            $total_winnings += floatval($result->winnings);
            $total_hits += intval($result->hits);
            $finish = intval($result->finish_position);
            $finishes[] = $finish;
            if ($finish < $best_finish && $finish > 0) {
                $best_finish = $finish;
            }
        }

        $avg_finish = !empty($finishes) ? array_sum($finishes) / count($finishes) : 0;

        // Apply formula if configured and not direct_sum
        if ($formula_key && $formula_key !== 'direct_sum' && class_exists('Poker_Tournament_Formula_Validator')) {
            $formula_validator = new Poker_Tournament_Formula_Validator();
            $formula_data = $formula_validator->get_formula($formula_key);

            if ($formula_data) {
                $formula_input = array(
                    'tournament_points' => $tournament_points,
                    'total_tournaments' => $tournaments_played,
                    'tournaments_played' => $tournaments_played,
                    'total_winnings' => $total_winnings,
                    'total_hits' => $total_hits,
                    'best_finish' => $best_finish === PHP_INT_MAX ? 0 : $best_finish,
                    'avg_finish' => $avg_finish,
                    'player_id' => $player_id
                );

                $result = $formula_validator->calculate_formula($formula_data['formula'], $formula_input, 'season');

                if (isset($result['success']) && $result['success']) {
                    return floatval($result['result']);
                }
            }
        }

        // Fallback: sum all points
        return $total_points;
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
        echo '<h3>' . esc_html__('All Series', 'poker-tournament-import') . '</h3>';

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
                echo '<h4><a href="' . esc_url(get_edit_post_link($series->ID)) . '">' . esc_html($series->post_title) . '</a></h4>';
                /* translators: %d: number of tournaments */
                echo '<p>' . esc_html(sprintf(_n('%d tournament', '%d tournaments', $tournament_count, 'poker-tournament-import'), $tournament_count)) . '</p>';
                echo '<div class="series-actions">';
                echo '[series_overview id="' . esc_html($series->ID) . '"]';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No series found.', 'poker-tournament-import') . '</p>';
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
        echo '<h3>' . esc_html__('Tournament Analytics', 'poker-tournament-import') . '</h3>';

        // Prize pool distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
        echo '<h4>' . esc_html__('Prize Pool Distribution', 'poker-tournament-import') . '</h4>';
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
            echo '<p>' . esc_html__('No data available', 'poker-tournament-import') . '</p>';
        }
        echo '</div>';

        // Player participation trends
        echo '<div class="analytics-card">';
        echo '<h4>' . esc_html__('Player Participation', 'poker-tournament-import') . '</h4>';
        echo '<p>' . esc_html__('Analytics features coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';

        echo '</div>'; // analytics-grid
        echo '</div>'; // analytics-detail-view
    }

    /**
     * Render detailed tournaments view
     */
    private function render_detailed_tournaments_view() {
        echo '<div class="detailed-tournaments-view">';
        echo '<h3>' . esc_html__('Tournament Details', 'poker-tournament-import') . '</h3>';
        echo '<p>' . esc_html__('Detailed tournament analytics coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';
    }

    /**
     * Render detailed players view
     */
    private function render_detailed_players_view() {
        echo '<div class="detailed-players-view">';
        echo '<h3>' . esc_html__('Player Details', 'poker-tournament-import') . '</h3>';
        echo '<p>' . esc_html__('Detailed player analytics coming soon', 'poker-tournament-import') . '</p>';
        echo '</div>';
    }

    /**
     * Render detailed leaderboard view
     */
    private function render_detailed_leaderboard_view() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
        echo '<h3>' . esc_html__('Complete Leaderboard', 'poker-tournament-import') . '</h3>';

        if (!empty($leaderboard)) {
            echo '<div class="leaderboard-table-wrapper">';
            echo '<table class="widefat leaderboard-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Rank', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Player', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Tournaments', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Points', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Best Finish', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Avg Finish', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($leaderboard as $index => $player) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
                    echo '<a href="' . esc_url(get_permalink($player_post->ID)) . '">' . esc_html($player_post->post_title) . '</a>';
                } else {
                    echo esc_html($player->player_id);
                }
                echo '</td>';
                echo '<td>' . esc_html($player->tournaments_played) . '</td>';
                echo '<td>$' . esc_html(number_format($player->total_winnings, 0)) . '</td>';
                echo '<td>' . esc_html(number_format($player->total_points, 1)) . '</td>';
                echo '<td>' . esc_html($player->best_finish) . esc_html(get_ordinal_suffix($player->best_finish)) . '</td>';
                echo '<td>' . esc_html(number_format($player->avg_finish, 1)) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No leaderboard data available', 'poker-tournament-import') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render calendar view
     */
    private function render_calendar_view() {
        echo '<div class="calendar-view">';
        echo '<h3>' . esc_html__('Tournament Calendar', 'poker-tournament-import') . '</h3>';
        echo '<p>' . esc_html__('Calendar view coming soon', 'poker-tournament-import') . '</p>';
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
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
                    update_option('tdwp_statistics_last_refresh', current_time('mysql'));
                } else {
                    error_log("Poker Statistics: Async refresh failed for tournament {$tournament_id}");
                }
            } catch (Exception $e) {
                error_log("Poker Statistics: Exception during async refresh: " . $e->getMessage());
            }
        }
    }

    /**
     * AJAX handler for data mart cleaning
     */
    public function ajax_clean_data_mart() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $cleaning_type = isset($_POST['cleaning_type']) ? sanitize_text_field($_POST['cleaning_type']) : '';

        if (empty($cleaning_type)) {
            wp_send_json_error(array(
                'message' => __('Cleaning type is required.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = false;
        $message = '';

        switch ($cleaning_type) {
            case 'statistics':
                $result = $data_mart_cleaner->clean_statistics_table();
                $message = $result ? __('Statistics cleaned successfully!', 'poker-tournament-import') : __('Failed to clean statistics.', 'poker-tournament-import');
                break;

            case 'financial':
                $result = $data_mart_cleaner->clean_financial_tables();
                $message = $result ? __('Financial data cleaned successfully!', 'poker-tournament-import') : __('Failed to clean financial data.', 'poker-tournament-import');
                break;

            case 'player_data':
                $result = $data_mart_cleaner->clean_player_data();
                $message = $result ? __('Player data cleaned successfully!', 'poker-tournament-import') : __('Failed to clean player data.', 'poker-tournament-import');
                break;

            case 'analytics':
                $result = $data_mart_cleaner->clean_analytics_tables();
                $message = $result ? __('Analytics cleaned successfully!', 'poker-tournament-import') : __('Failed to clean analytics.', 'poker-tournament-import');
                break;

            case 'options':
                $result = $data_mart_cleaner->clean_wordpress_options();
                $message = $result ? __('Options cleaned successfully!', 'poker-tournament-import') : __('Failed to clean options.', 'poker-tournament-import');
                break;

            case 'all':
                $result = $data_mart_cleaner->clean_all_data_mart();
                $message = $result ? __('All data mart cleaned successfully!', 'poker-tournament-import') : __('Failed to clean data mart.', 'poker-tournament-import');
                break;

            case 'reset_all':
                $result = $data_mart_cleaner->reset_all_plugin_data();
                $message = $result ? __('Complete reset successful!', 'poker-tournament-import') : __('Failed to reset plugin data.', 'poker-tournament-import');
                break;

            default:
                wp_send_json_error(array(
                    'message' => __('Invalid cleaning type.', 'poker-tournament-import')
                ));
                break;
        }

        if ($result) {
            wp_send_json_success(array(
                'message' => $message,
                'stats' => $data_mart_cleaner->get_data_mart_stats()
            ));
        } else {
            wp_send_json_error(array(
                'message' => $message
            ));
        }
    }

    /**
     * AJAX handler for chronological reconstruction
     */
    public function ajax_reconstruct_chronology() {
        check_ajax_referer('poker_series_tab_content', 'nonce');

        $tournament_id = intval($_POST['tournament_id']);

        if (empty($tournament_id)) {
            wp_send_json_error(array(
                'message' => __('Tournament ID is required.', 'poker-tournament-import')
            ));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);

        if (!$tournament_uuid) {
            wp_send_json_error(array(
                'message' => __('Tournament UUID not found.', 'poker-tournament-import')
            ));
        }

        // Get current player data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tournament_id = %s ORDER BY finish_position ASC",
            $tournament_uuid
        ));

        if (empty($players)) {
            wp_send_json_error(array(
                'message' => __('No player data found for this tournament.', 'poker-tournament-import')
            ));
        }

        // Apply chronological reconstruction algorithm
        $reconstructed_players = $this->reconstruct_chronological_order($players);

        if ($reconstructed_players) {
            // Update database with new chronological order
            foreach ($reconstructed_players as $index => $player) {
                $new_finish_position = $index + 1;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $wpdb->upgmdate(
                    $table_name,
                    array('finish_position' => $new_finish_position),
                    array('id' => $player->id),
                    array('%d'),
                    array('%d')
                );
            }

            // Mark tournament as chronologically processed
            update_post_meta($tournament_id, '_chronologically_processed', true);
            update_post_meta($tournament_id, '_chronological_processing_date', current_time('mysql'));

            wp_send_json_success(array(
                'message' => __('Tournament has been successfully updated with chronological order!', 'poker-tournament-import')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Chronological reconstruction failed. Please upload the original .tdt file.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for TDT file upload for existing tournament
     */
    public function ajax_upload_tdt_for_tournament() {
        check_ajax_referer('poker_series_tab_content', 'nonce');

        $tournament_id = intval($_POST['tournament_id']);

        if (empty($tournament_id)) {
            wp_send_json_error(array(
                'message' => __('Tournament ID is required.', 'poker-tournament-import')
            ));
        }

        if (!isset($_FILES['tdt_file']) || $_FILES['tdt_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array(
                'message' => __('File upload failed. Please try again.', 'poker-tournament-import')
            ));
        }

        $file = $_FILES['tdt_file'];

        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'tdt') {
            wp_send_json_error(array(
                'message' => __('Invalid file type. Please upload a .tdt file.', 'poker-tournament-import')
            ));
        }

        // Read file content
        $file_content = file_get_contents($file['tmp_name']);
        if ($file_content === false) {
            wp_send_json_error(array(
                'message' => __('Failed to read uploaded file.', 'poker-tournament-import')
            ));
        }

        // Parse the TDT file
        try {
            $parser = new Poker_Tournament_Parser();
            $tournament_data = $parser->parse_content($file_content);

            if (!$tournament_data || empty($tournament_data['players'])) {
                wp_send_json_error(array(
                    'message' => __('Invalid or empty TDT file.', 'poker-tournament-import')
                ));
            }

            // Store raw TDT content for real-time processing
            update_post_meta($tournament_id, '_tournament_raw_content', $file_content);

            // Get tournament UUID
            $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if (!$tournament_uuid) {
                $tournament_uuid = $tournament_data['metadata']['uuid'] ?? uniqid('tournament_');
                update_post_meta($tournament_id, 'tournament_uuid', $tournament_uuid);
            }

            // Update player data with chronological order from TDT
            global $wpdb;
            $table_name = $wpdb->prefix . 'poker_tournament_players';

            // Clear existing player data for this tournament
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $wpdb->delete(
                $table_name,
                array('tournament_id' => $tournament_uuid),
                array('%s')
            );

            // Insert updated player data in chronological order
            foreach ($tournament_data['players'] as $index => $player) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $wpdb->insert(
                    $table_name,
                    array(
                        'tournament_id' => $tournament_uuid,
                        'player_id' => $player['id'],
                        'finish_position' => $index + 1,
                        'winnings' => $player['winnings'] ?? 0,
                        'buyins' => $player['buyins'] ?? 1,
                        'rebuys' => $player['rebuys'] ?? 0,
                        'addons' => $player['addons'] ?? 0,
                        'hits' => $player['hits'] ?? 0,
                        'points' => $player['points'] ?? 0,
                    ),
                    array('%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%f')
                );
            }

            // Mark tournament as chronologically processed
            update_post_meta($tournament_id, '_chronologically_processed', true);
            update_post_meta($tournament_id, '_chronological_processing_date', current_time('mysql'));
            update_post_meta($tournament_id, '_tdt_file_uploaded', true);

            wp_send_json_success(array(
                'message' => __('Tournament has been successfully updated with chronological order from the .tdt file!', 'poker-tournament-import')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error parsing TDT file: ', 'poker-tournament-import') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for frontend tournament import (non-admin users)
     */
    public function ajax_frontend_import_tournament() {
        error_log('[TDWP Import Beta20] AJAX handler called');
        error_log('[TDWP Import Beta20] User logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
        error_log('[TDWP Import Beta20] User ID: ' . get_current_user_id());
        error_log('[TDWP Import Beta20] REQUEST: ' . print_r($_REQUEST, true));
        error_log('[TDWP Import Beta20] FILES: ' . print_r($_FILES, true));

        check_ajax_referer('tdwp_frontend_import_tournament', 'nonce');
        error_log('[TDWP Import Beta20] Nonce verified successfully');

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in to import tournaments.', 'poker-tournament-import')
            ));
        }

        if (!isset($_FILES['tdt_file']) || $_FILES['tdt_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array(
                'message' => __('File upload failed. Please try again.', 'poker-tournament-import')
            ));
        }

        $file = $_FILES['tdt_file'];

        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'tdt') {
            wp_send_json_error(array(
                'message' => __('Invalid file type. Please upload a .tdt file.', 'poker-tournament-import')
            ));
        }

        // Read file content
        $file_content = file_get_contents($file['tmp_name']);
        if ($file_content === false) {
            wp_send_json_error(array(
                'message' => __('Failed to read uploaded file.', 'poker-tournament-import')
            ));
        }

        // Parse the TDT file
        try {
            $parser = new Poker_Tournament_Parser();
            $tournament_data = $parser->parse_content($file_content);

            if (!$tournament_data || empty($tournament_data['players'])) {
                wp_send_json_error(array(
                    'message' => __('Invalid or empty TDT file.', 'poker-tournament-import')
                ));
            }

            // v2.8.9: Use complete admin import flow for consistency
            // Set import options for AJAX frontend import
            $_POST['create_players'] = '1';  // Create player posts
            $_POST['publish_immediately'] = '1';  // Publish immediately

            // Load admin class if not already loaded
            if (!class_exists('Poker_Tournament_Import_Admin')) {
                require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-admin.php';
            }

            // Use complete admin import method (creates series, season, players, tournament, ROI, statistics)
            $admin = new Poker_Tournament_Import_Admin();
            $result = $admin->import_tournament_data($tournament_data, $parser);

            if ($result['success']) {
                $tournament_id = $result['created_posts']['tournament'] ?? 0;
                wp_send_json_success(array(
                    'message' => __('Tournament imported successfully!', 'poker-tournament-import'),
                    'tournament_id' => $tournament_id,
                    'tournament_url' => get_permalink($tournament_id),
                    'tournament_title' => get_the_title($tournament_id),
                    'created_posts' => $result['created_posts']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message'] ?? __('Import failed.', 'poker-tournament-import')
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error parsing TDT file: ', 'poker-tournament-import') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for frontend statistics refresh (logged-in users)
     */
    public function ajax_frontend_refresh_statistics() {
        check_ajax_referer('poker_frontend_stats', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in to refresh statistics.', 'poker-tournament-import')
            ));
        }

        $season_id = isset($_POST['season_id']) ? sanitize_text_field($_POST['season_id']) : null;
        
        $result = $this->statistics_engine->refresh_statistics();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Statistics refreshed successfully!', 'poker-tournament-import'),
                'timestamp' => current_time('mysql'),
                'stats' => $this->statistics_engine->get_dashboard_statistics($season_id)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to refresh statistics.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * AJAX handler for loading tournaments filtered by season
     */
    public function ajax_dashboard_tournaments_filtered() {
        check_ajax_referer('poker_series_tab_content', 'nonce');

        $season_id = isset($_POST['season_id']) ? sanitize_text_field($_POST['season_id']) : 'all';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        $args = array(
            'post_type' => 'tournament',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Add season filter
        if ($season_id && $season_id !== 'all') {
            $args['meta_query'] = array(
                array(
                    'key' => '_season_id',
                    'value' => $season_id,
                    'compare' => '='
                )
            );
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $tournaments = array();
            $rank = ($page - 1) * $per_page + 1;

            while ($query->have_posts()) {
                $query->the_post();
                $tournament_id = get_the_ID();
                $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
                $players_count = get_post_meta($tournament_id, '_players_count', true);
                $prize_pool = get_post_meta($tournament_id, '_prize_pool', true);
                $tournament_date = get_post_meta($tournament_id, '_tournament_date', true);
                $currency = get_post_meta($tournament_id, '_currency', true) ?: '$';

                // Get winner
                $winner_name = '';
                if ($tournament_uuid) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
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

                $tournaments[] = array(
                    'rank' => $rank++,
                    'id' => $tournament_id,
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'date' => $tournament_date ? date_i18n('M j, Y', strtotime($tournament_date)) : get_the_gmdate('M j, Y'),
                    'players' => $players_count ?: '--',
                    'prize_pool' => $currency . number_format($prize_pool ?: 0, 0),
                    'winner' => $winner_name
                );
            }

            wp_reset_postdata();

            wp_send_json_success(array(
                'tournaments' => $tournaments,
                'has_more' => $query->max_num_pages > $page,
                'current_page' => $page,
                'total_pages' => $query->max_num_pages
            ));
        } else {
            wp_send_json_success(array(
                'tournaments' => array(),
                'has_more' => false,
                'current_page' => 1,
                'total_pages' => 0
            ));
        }
    }

    // Note: ajax_load_tournaments_data is now handled in admin/class-admin.php to avoid duplicate function definition

    /**
     * Reconstruct chronological order from buy-in data
     */
    private function reconstruct_chronological_order($players) {
        if (empty($players)) {
            return null;
        }

        // Advanced algorithm to reconstruct elimination order
        // Sort by multiple criteria to approximate chronological order

        // Primary sort: by winnings (descending - higher winnings = lasted longer)
        usort($players, function($a, $b) {
            // First compare winnings
            $winnings_diff = floatval($b->winnings) - floatval($a->winnings);
            if (abs($winnings_diff) > 0.01) {
                return $winnings_diff > 0 ? 1 : -1;
            }

            // Secondary sort: by points (descending)
            $points_diff = floatval($b->points) - floatval($a->points);
            if (abs($points_diff) > 0.01) {
                return $points_diff > 0 ? 1 : -1;
            }

            // Tertiary sort: by total buyins (descending - more buyins = played longer)
            $buyins_diff = ($b->buyins + $b->rebuys + $b->addons) - ($a->buyins + $a->rebuys + $a->addons);
            if ($buyins_diff !== 0) {
                return $buyins_diff > 0 ? 1 : -1;
            }

            // Final sort: keep original order as last resort
            return $a->finish_position - $b->finish_position;
        });

        // Validate the reconstructed order makes sense
        if ($this->is_valid_chronological_order($players)) {
            return $players;
        } else {
            return null;
        }
    }

    /**
     * Validate if the reconstructed order is logical
     */
    private function is_valid_chronological_order($players) {
        if (empty($players) || count($players) < 2) {
            return true;
        }

        // Basic validation: winner should have highest winnings
        $winner = $players[0];
        foreach (array_slice($players, 1) as $player) {
            if (floatval($player->winnings) > floatval($winner->winnings)) {
                return false;
            }
        }

        // Additional validation checks can be added here
        return true;
    }

    /**
     * Enhanced AJAX handler for statistics cleaning with real-time feedback
     */
    public function ajax_clean_statistics_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_statistics_table_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Statistics table cleaned successfully!', 'poker-tournament-import'),
                'records_removed' => $result['records_removed'],
                'verification' => $result['verification']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clean statistics table.', 'poker-tournament-import'),
                'error' => $result['error']
            ));
        }
    }

    /**
     * Enhanced AJAX handler for financial data cleaning
     */
    public function ajax_clean_financial_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_financial_tables_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Financial data cleaned successfully!', 'poker-tournament-import'),
                'tables_cleaned' => $result['tables_cleaned'],
                'records_removed' => $result['total_records']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clean financial data.', 'poker-tournament-import'),
                'errors' => $result['errors']
            ));
        }
    }

    /**
     * Enhanced AJAX handler for player data cleaning
     */
    public function ajax_clean_player_data_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_player_data_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Player data cleaned successfully!', 'poker-tournament-import'),
                'tables_cleaned' => $result['tables_cleaned'],
                'records_removed' => $result['total_records']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clean player data.', 'poker-tournament-import'),
                'errors' => $result['errors']
            ));
        }
    }

    /**
     * Enhanced AJAX handler for analytics data cleaning
     */
    public function ajax_clean_analytics_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_analytics_tables_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Analytics data cleaned successfully!', 'poker-tournament-import'),
                'tables_cleaned' => $result['tables_cleaned'],
                'records_removed' => $result['total_records']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clean analytics data.', 'poker-tournament-import'),
                'errors' => $result['errors']
            ));
        }
    }

    /**
     * Enhanced AJAX handler for options cleaning
     */
    public function ajax_clean_options_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_wordpress_options_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('WordPress options cleaned successfully!', 'poker-tournament-import'),
                'options_cleaned' => $result['options_cleaned'],
                'total_options' => $result['total_options']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clean WordPress options.', 'poker-tournament-import'),
                'errors' => $result['errors']
            ));
        }
    }

    /**
     * Enhanced AJAX handler for cleaning all data mart
     */
    public function ajax_clean_all_enhanced() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $result = $data_mart_cleaner->clean_all_data_mart_enhanced();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('All data mart tables cleaned successfully!', 'poker-tournament-import'),
                'tables_cleaned' => $result['tables_cleaned'],
                'total_records' => $result['total_records']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Some errors occurred during cleaning.', 'poker-tournament-import'),
                'errors' => $result['errors']
            ));
        }
    }

    /**
     * AJAX handler to get real-time cleaning status
     */
    public function ajax_get_cleaning_status() {
        check_ajax_referer('poker_data_mart_cleaner', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions to access this page.', 'poker-tournament-import')
            ));
        }

        $data_mart_cleaner = new Poker_Data_Mart_Cleaner();
        $status = $data_mart_cleaner->get_real_time_data_mart_status();

        wp_send_json_success($status);
    }

    /**
     * Load custom templates for our custom post types
     *
     * @param string $template The path to the template WordPress will load
     * @return string The modified template path
     */
    public function load_custom_templates($template) {
        // Check if this is a singular post of our custom post types
        if (is_singular('player')) {
            $custom_template = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'templates/single-player.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular('tournament')) {
            $custom_template = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'templates/single-tournament.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular('tournament_series')) {
            $custom_template = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'templates/taxonomy-tournament_series.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular('tournament_season')) {
            $custom_template = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'templates/single-tournament_season.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_post_type_archive('tournament')) {
            $custom_template = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'templates/archive-tournament.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        // Return original template if none of our conditions match
        return $template;
    }

    /**
     * AJAX handler for getting tournament data for shortcodes
     *
     * @since 3.4.0
     */
    public function ajax_get_tournament_data() {
        check_ajax_referer('tdwp_shortcode_nonce', 'nonce');

        $tournament_ids = isset($_POST['tournament_ids']) ? array_map('intval', $_POST['tournament_ids']) : array();

        if (empty($tournament_ids)) {
            wp_send_json_error(array(
                'message' => __('Tournament IDs are required.', 'poker-tournament-import')
            ));
        }

        $data = array();

        foreach ($tournament_ids as $tournament_id) {
            $tournament_data = $this->get_tournament_live_data($tournament_id);
            if ($tournament_data) {
                $data[$tournament_id] = $tournament_data;
            }
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for unregistering screen
     *
     * @since 3.4.0
     */
    public function ajax_unregister_screen() {
        check_ajax_referer('tdwp_display_nonce', 'nonce');

        $screen_id = isset($_POST['screen_id']) ? intval($_POST['screen_id']) : 0;

        if (!$screen_id) {
            wp_send_json_error(array(
                'message' => __('Screen ID is required.', 'poker-tournament-import')
            ));
        }

        // Update screen status to offline
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'poker_display_screens',
            array('is_online' => 0, 'last_ping' => current_time('mysql')),
            array('screen_id' => $screen_id),
            array('%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => __('Screen unregistered successfully.', 'poker-tournament-import')
        ));
    }

    /**
     * Get tournament live data for shortcodes
     *
     * @since 3.4.0
     * @param int $tournament_id Tournament ID.
     * @return array Tournament data.
     */
    private function get_tournament_live_data($tournament_id) {
        global $wpdb;

        $tournament = get_post($tournament_id);
        if (!$tournament || $tournament->post_type !== 'tournament') {
            return null;
        }

        // Try to get live state from live tournament manager
        if (class_exists('TDWP_Tournament_Live')) {
            $live_manager = TDWP_Tournament_Live::get_instance();
            $live_state = $live_manager->get_by_tournament_id($tournament_id);

            if ($live_state) {
                // Check tournament status - only show active clock for 'running' tournaments
                $is_running = ($live_state->status === 'running');

                return array_merge(array(
                    'tournament_name' => $tournament->post_title,
                    'tournament_id' => $tournament_id,
                    'tournament_status' => $live_state->status,
                    'clock_running' => $is_running,
                ), (array) $live_state);
            }
        }

        // Get basic tournament data for tournaments without live state
        $data = array(
            'tournament_name' => $tournament->post_title,
            'tournament_id' => $tournament_id,
            'tournament_status' => 'pending',
            'current_level' => 1,
            'current_blinds' => '10/20',
            'next_blinds' => '15/30',
            'time_remaining' => '--:--',
            'players_remaining' => 0,
            'prize_pool' => '$0',
            'clock_running' => false,
        );

        // Get player count from database
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $player_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE tournament_id = %d AND eliminated = 0",
            $tournament_id
        ));

        if ($player_count) {
            $data['players_remaining'] = intval($player_count);
        }

        // Get prize pool from tournament meta
        $prize_pool = get_post_meta($tournament_id, '_prize_pool', true);
        if ($prize_pool) {
            $data['prize_pool'] = '$' . number_format($prize_pool, 2);
        }

        return $data;
    }
}

/**
 * Global helper function for currency formatting
 * Accessible from both admin and frontend contexts
 *
 * @param float|int $amount The amount to format
 * @return string Formatted currency string with symbol
 */
if (!function_exists('poker_format_currency')) {
    function poker_format_currency($amount) {
        $symbol = get_option('tdwp_currency_symbol', '$');
        $position = get_option('tdwp_currency_position', 'prefix');

        // Format the amount with 2 decimal places
        $formatted_amount = number_format((float)$amount, 2, '.', ',');

        if ($position === 'postfix') {
            return $formatted_amount . $symbol;
        } else {
            return $symbol . $formatted_amount;
        }
    }
}

/**
 * Cached database query helper for templates and non-class contexts
 * Wraps $wpdb queries with WordPress object cache
 *
 * @param string $query_type 'get_results', 'get_var', 'get_col', or 'query'
 * @param string $sql SQL query
 * @param mixed $args Optional query arguments
 * @param int $cache_time Cache duration in seconds (default: 1 hour)
 * @return mixed Query results
 */
if (!function_exists('poker_cached_query')) {
    function poker_cached_query($query_type, $sql, $args = null, $cache_time = HOUR_IN_SECONDS) {
        global $wpdb;

        // Generate cache key from query
        $cache_key = 'poker_' . md5($sql . serialize($args));
        $cache_group = 'poker_tournament';

        // Try to get from cache
        $results = wp_cache_get($cache_key, $cache_group);

        if (false === $results) {
            // Cache miss - query database
            if ($args !== null) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql parameter is being prepared here
                $prepared_sql = $wpdb->prepare($sql, $args);
                $results = $wpdb->$query_type($prepared_sql);
            } else {
                $results = $wpdb->$query_type($sql);
            }

            // Store in cache
            wp_cache_set($cache_key, $results, $cache_group, $cache_time);
        }

        return $results;
    }

  }

/**
 * Beta21: Test AJAX handler to verify AJAX system works
 */
function tdwp_ajax_test_handler() {
    error_log('[TDWP Beta21] TEST handler called!');
    wp_send_json_success(array('message' => 'AJAX works!', 'time' => time()));
}

// Initialize the plugin
Poker_Tournament_Import::get_instance();