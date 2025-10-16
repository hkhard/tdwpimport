<?php
/**
 * Data Mart Cleaner Class
 *
 * Provides comprehensive data mart cleaning and reset functionality
 * for all statistics, analytics, and derived data tables.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Data_Mart_Cleaner {

    /**
     * Data mart table definitions
     */
    private $data_mart_tables = array(
        'poker_statistics' => 'Core dashboard statistics and KPIs',
        'poker_financial_summary' => 'Tournament financial data and ROI metrics',
        'poker_player_roi' => 'Player return on investment tracking',
        'poker_revenue_analytics' => 'Monthly revenue and profit analytics',
        'poker_tournament_costs' => 'Tournament cost breakdown data',
        'poker_tournament_players' => 'Tournament results and player performance data (CRITICAL FIX: Now properly cleared)'
    );

    /**
     * WordPress options to clean
     */
    private $wp_options = array(
        'poker_statistics_last_refresh',
        'poker_import_last_version',
        'poker_import_debug_mode',
        'poker_import_debug_logging',
        'poker_tournament_formula_validator_settings',
        'poker_series_settings',
        'poker_dashboard_settings',
        'poker_formula_cache',
        'poker_import_settings'
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_data_cleaning_actions'));
        add_action('wp_ajax_poker_clean_data_mart', array($this, 'handle_ajax_cleaning'));
        add_action('wp_ajax_poker_migrate_tournaments', array($this, 'handle_ajax_migration'));
        add_action('wp_ajax_poker_get_cleaning_status', array($this, 'handle_ajax_status'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=tournament',
            __('Data Mart Cleaner', 'poker-tournament-import'),
            __('Data Mart Cleaner', 'poker-tournament-import'),
            'manage_options',
            'poker-data-mart-cleaner',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Handle data cleaning actions
     */
    public function handle_data_cleaning_actions() {
        if (!isset($_POST['poker_cleaner_action']) || !isset($_POST['_wpnonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'poker_data_mart_cleaner')) {
            wp_die(__('Security check failed.', 'poker-tournament-import'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $action = sanitize_text_field($_POST['poker_cleaner_action']);
        $redirect_url = admin_url('edit.php?post_type=tournament&page=poker-data-mart-cleaner');

        switch ($action) {
            case 'clean_statistics':
                $this->clean_statistics_table();
                $redirect_url = add_query_arg('cleaned', 'statistics', $redirect_url);
                break;

            case 'clean_financial':
                $this->clean_financial_tables();
                $redirect_url = add_query_arg('cleaned', 'financial', $redirect_url);
                break;

            case 'clean_player_data':
                $this->clean_player_data();
                $redirect_url = add_query_arg('cleaned', 'player_data', $redirect_url);
                break;

            case 'clean_analytics':
                $this->clean_analytics_tables();
                $redirect_url = add_query_arg('cleaned', 'analytics', $redirect_url);
                break;

            case 'clean_options':
                $this->clean_wordpress_options();
                $redirect_url = add_query_arg('cleaned', 'options', $redirect_url);
                break;

            case 'clean_all':
                $this->clean_all_data_mart();
                $redirect_url = add_query_arg('cleaned', 'all', $redirect_url);
                break;

            case 'reset_all':
                $this->reset_all_plugin_data();
                $redirect_url = add_query_arg('cleaned', 'reset_all', $redirect_url);
                break;

            case 'migrate_tournaments':
                $this->migrate_tournament_data();
                $redirect_url = add_query_arg('cleaned', 'migrated', $redirect_url);
                break;
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Clean statistics table with enhanced error handling and verification
     */
    private function clean_statistics_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_statistics';

        // Step 1: Verify table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            error_log("Poker Data Mart: Statistics table {$table_name} does not exist");
            return false;
        }

        // Step 2: Count records before cleaning for verification
        $record_count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        error_log("Poker Data Mart: Statistics table has {$record_count_before} records before cleaning");

        // Step 3: Try TRUNCATE first (most efficient)
        $result = $wpdb->query("TRUNCATE TABLE {$table_name}");

        if ($result === false) {
            // Step 4: Fallback to DELETE if TRUNCATE fails
            error_log("Poker Data Mart: TRUNCATE failed, trying DELETE - " . $wpdb->last_error);
            $result = $wpdb->query("DELETE FROM {$table_name}");

            if ($result === false) {
                error_log("Poker Data Mart: DELETE also failed - " . $wpdb->last_error);
                return false;
            } else {
                error_log("Poker Data Mart: Successfully cleaned statistics table using DELETE");
            }
        } else {
            error_log("Poker Data Mart: Successfully cleaned statistics table using TRUNCATE");
        }

        // Step 5: Verify the table was actually cleaned
        $record_count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($record_count_after == 0) {
            error_log("Poker Data Mart: Statistics table cleaning verified - removed {$record_count_before} records");

            // Step 6: Clean related options
            delete_option('poker_statistics_last_refresh');
            delete_option('poker_import_debug_mode');
            delete_option('poker_import_debug_logging');

            return true;
        } else {
            error_log("Poker Data Mart: CRITICAL - Statistics table still has {$record_count_after} records after cleaning!");
            return false;
        }
    }

    /**
     * Clean financial tables
     */
    private function clean_financial_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'poker_financial_summary',
            $wpdb->prefix . 'poker_revenue_analytics',
            $wpdb->prefix . 'poker_tournament_costs'
        );

        $success = true;
        foreach ($tables as $table) {
            $result = $wpdb->query("TRUNCATE TABLE {$table}");
            if ($result === false) {
                error_log("Poker Data Mart: Failed to clean {$table} - " . $wpdb->last_error);
                $success = false;
            } else {
                error_log("Poker Data Mart: Successfully cleaned {$table}");
            }
        }

        return $success;
    }

    /**
     * Clean player data
     */
    private function clean_player_data() {
        global $wpdb;

        $success = true;

        // Clean player ROI table
        $roi_table = $wpdb->prefix . 'poker_player_roi';
        $result = $wpdb->query("TRUNCATE TABLE {$roi_table}");
        if ($result !== false) {
            error_log('Poker Data Mart: Player ROI table cleaned successfully');
        } else {
            error_log('Poker Data Mart: Failed to clean player ROI table - ' . $wpdb->last_error);
            $success = false;
        }

        // Clean tournament players table (CRITICAL FIX: This was missing!)
        $players_table = $wpdb->prefix . 'poker_tournament_players';
        $result = $wpdb->query("TRUNCATE TABLE {$players_table}");
        if ($result !== false) {
            error_log('Poker Data Mart: Tournament players table cleaned successfully');
        } else {
            error_log('Poker Data Mart: Failed to clean tournament players table - ' . $wpdb->last_error);
            $success = false;
        }

        return $success;
    }

    /**
     * Clean analytics tables
     */
    private function clean_analytics_tables() {
        global $wpdb;

        $success = true;

        // Clean revenue analytics table
        $analytics_table = $wpdb->prefix . 'poker_revenue_analytics';
        $result = $wpdb->query("TRUNCATE TABLE {$analytics_table}");
        if ($result !== false) {
            error_log('Poker Data Mart: Revenue analytics table cleaned successfully');
        } else {
            error_log('Poker Data Mart: Failed to clean revenue analytics table - ' . $wpdb->last_error);
            $success = false;
        }

        return $success;
    }

    /**
     * Clean WordPress options
     */
    private function clean_wordpress_options() {
        $success = true;

        foreach ($this->wp_options as $option) {
            $deleted = delete_option($option);
            if ($deleted) {
                error_log("Poker Data Mart: Deleted option {$option}");
            } else {
                error_log("Poker Data Mart: Failed to delete option {$option}");
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clean all data mart tables (but keep core tournament data)
     */
    private function clean_all_data_mart() {
        $success = true;

        // Clean all data mart tables
        $success = $this->clean_statistics_table() && $success;
        $success = $this->clean_financial_tables() && $success;
        $success = $this->clean_player_data() && $success;
        $success = $this->clean_analytics_tables() && $success;
        $success = $this->clean_wordpress_options() && $success;

        if ($success) {
            error_log('Poker Data Mart: All data mart tables cleaned successfully');
        } else {
            error_log('Poker Data Mart: Some errors occurred during data mart cleaning');
        }

        return $success;
    }

    /**
     * Complete reset of ALL plugin data (including tournament results, posts, taxonomies, etc.)
     */
    private function reset_all_plugin_data() {
        global $wpdb;

        $success = true;

        error_log('Poker Data Mart: Starting COMPLETE plugin data reset - removing ALL plugin data');

        // Step 1: Clean all data mart tables with enhanced logging
        $plugin_tables = array_keys($this->data_mart_tables);
        error_log('Poker Data Mart: Processing ' . count($plugin_tables) . ' data mart tables for reset');

        foreach ($plugin_tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            error_log("Poker Data Mart: Processing table {$table_suffix} as {$table_name}");

            // Check if table exists before truncating
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            if ($table_exists) {
                // Count records before truncation for logging
                $record_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                error_log("Poker Data Mart: Table {$table_name} exists with {$record_count} records, truncating...");

                $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
                if ($result === false) {
                    error_log("Poker Data Mart: FAILED to reset {$table_name} - " . $wpdb->last_error);
                    $success = false;
                } else {
                    error_log("Poker Data Mart: Successfully reset {$table_name} (removed {$record_count} records)");
                }
            } else {
                error_log("Poker Data Mart: Table {$table_name} does not exist, skipping");
            }
        }

        // Step 2: Remove ALL WordPress content created by plugin
        error_log('Poker Data Mart: Removing WordPress content created by plugin...');
        $success = $this->clean_all_wordpress_posts() && $success;

        // Step 3: Clean all plugin taxonomies and terms
        error_log('Poker Data Mart: Removing plugin taxonomies and terms...');
        $success = $this->clean_all_taxonomies() && $success;

        // Step 4: Clean all post meta data
        error_log('Poker Data Mart: Removing plugin post meta data...');
        $success = $this->clean_all_post_meta() && $success;

        // Step 5: Clean WordPress options
        error_log('Poker Data Mart: Removing WordPress options...');
        $success = $this->clean_wordpress_options() && $success;

        // Step 6: Remove all user meta related to plugin
        error_log('Poker Data Mart: Removing user meta data...');
        $success = $this->clean_user_meta() && $success;

        // Step 7: Remove transients
        error_log('Poker Data Mart: Removing plugin transients...');
        $this->clean_plugin_transients();

        // Step 8: Clean uploaded files
        error_log('Poker Data Mart: Removing uploaded files...');
        $this->clean_uploaded_files();

        // Step 9: Clear WordPress cache
        error_log('Poker Data Mart: Clearing WordPress cache...');
        wp_cache_flush();

        // Step 10: Force flush rewrite rules
        error_log('Poker Data Mart: Flushing rewrite rules...');
        flush_rewrite_rules();

        if ($success) {
            error_log('Poker Data Mart: COMPLETE plugin data reset successful - ALL plugin data removed');
        } else {
            error_log('Poker Data Mart: Some errors occurred during complete reset - not all data may have been removed');
        }

        return $success;
    }

    /**
     * Clean plugin transients
     */
    private function clean_plugin_transients() {
        global $wpdb;

        // Get all transients related to our plugin
        $transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_poker_%'
                OR option_name LIKE '_transient_timeout_poker_%'"
        );

        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            delete_transient($transient_name);
        }

        error_log('Poker Data Mart: Cleaned plugin transients');
    }

    /**
     * Clean ALL WordPress posts created by the plugin
     */
    private function clean_all_wordpress_posts() {
        global $wpdb;

        $success = true;

        // Post types created by our plugin
        $post_types = array('tournament', 'tournament_series', 'tournament_season', 'player');

        foreach ($post_types as $post_type) {
            // Get all posts of this type
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'any'
            ));

            foreach ($posts as $post_id) {
                // Force delete all posts (bypass trash)
                $result = wp_delete_post($post_id, true);
                if (!$result) {
                    error_log("Poker Data Mart: Failed to delete {$post_type} post ID {$post_id}");
                    $success = false;
                } else {
                    error_log("Poker Data Mart: Deleted {$post_type} post ID {$post_id}");
                }
            }
        }

        return $success;
    }

    /**
     * Clean all plugin taxonomies and terms
     */
    private function clean_all_taxonomies() {
        global $wpdb;

        $success = true;

        // Taxonomies created by our plugin
        $taxonomies = array('tournament_type', 'tournament_format', 'tournament_category');

        foreach ($taxonomies as $taxonomy) {
            // Get all terms in this taxonomy
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids'
            ));

            foreach ($terms as $term_id) {
                $result = wp_delete_term($term_id, $taxonomy);
                if (is_wp_error($result)) {
                    error_log("Poker Data Mart: Failed to delete term ID {$term_id} from {$taxonomy}: " . $result->get_error_message());
                    $success = false;
                } else {
                    error_log("Poker Data Mart: Deleted term ID {$term_id} from {$taxonomy}");
                }
            }
        }

        return $success;
    }

    /**
     * Clean all plugin-related post meta data
     */
    private function clean_all_post_meta() {
        global $wpdb;

        $success = true;

        // Remove all post meta related to our plugin
        $meta_keys = array(
            'tournament_uuid', 'tournament_date', 'tournament_time', 'buy_in_amount', 'prize_pool',
            'players_count', 'winner_name', 'currency', 'tournament_location', 'tournament_description',
            '_tournament_date', '_tournament_time', '_buy_in_amount', '_prize_pool', '_players_count',
            '_winner_name', '_currency', '_tournament_location', '_tournament_description',
            '_series_id', '_season_id', '_tournament_type', '_tournament_format', '_tournament_category',
            'player_uuid', 'player_email', 'player_phone', 'player_address', 'player_bio', 'player_avatar',
            '_player_uuid', '_player_email', '_player_phone', '_player_address', '_player_bio', '_player_avatar',
            'series_start_date', 'series_end_date', 'series_description', 'series_settings',
            '_series_start_date', '_series_end_date', '_series_description', '_series_settings',
            'season_year', 'season_description', 'season_settings',
            '_season_year', '_season_description', '_season_settings'
        );

        foreach ($meta_keys as $meta_key) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                    $meta_key . '%'
                )
            );

            if ($result !== false) {
                error_log("Poker Data Mart: Cleaned post meta for {$meta_key} ({$result} rows deleted)");
            } else {
                error_log("Poker Data Mart: Failed to clean post meta for {$meta_key} - " . $wpdb->last_error);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clean user meta data related to plugin
     */
    private function clean_user_meta() {
        global $wpdb;

        $success = true;

        // User meta keys related to our plugin
        $user_meta_keys = array(
            'poker_player_profile', 'poker_tournament_preferences', 'poker_dashboard_layout',
            'poker_favorite_formulas', 'poker_last_import_settings', 'poker_tournament_history'
        );

        foreach ($user_meta_keys as $meta_key) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                    $meta_key . '%'
                )
            );

            if ($result !== false) {
                error_log("Poker Data Mart: Cleaned user meta for {$meta_key} ({$result} rows deleted)");
            } else {
                error_log("Poker Data Mart: Failed to clean user meta for {$meta_key} - " . $wpdb->last_error);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clean uploaded files created by plugin
     */
    private function clean_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dirs = array(
            $upload_dir['basedir'] . '/poker-imports/',
            $upload_dir['basedir'] . '/poker-reports/',
            $upload_dir['basedir'] . '/poker-temp/',
            $upload_dir['basedir'] . '/poker-cache/'
        );

        foreach ($plugin_upload_dirs as $dir) {
            if (is_dir($dir)) {
                $this->delete_directory_recursively($dir);
                error_log("Poker Data Mart: Cleaned upload directory: {$dir}");
            }
        }

        // Clean any .tdt files in uploads
        $this->clean_tdt_files();
    }

    /**
     * Recursively delete a directory
     */
    private function delete_directory_recursively($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory_recursively($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Clean .tdt files from uploads
     */
    private function clean_tdt_files() {
        $upload_dir = wp_upload_dir();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir['basedir'])
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'tdt') {
                unlink($file->getPathname());
                error_log("Poker Data Mart: Deleted .tdt file: " . $file->getPathname());
            }
        }
    }

    /**
     * Get data mart statistics
     */
    public function get_data_mart_stats() {
        global $wpdb;
        $stats = array();

        foreach ($this->data_mart_tables as $table_suffix => $description) {
            $table_name = $wpdb->prefix . $table_suffix;

            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

            if ($table_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $stats[$table_suffix] = array(
                    'exists' => true,
                    'count' => intval($count),
                    'description' => $description,
                    'table_name' => $table_name
                );
            } else {
                $stats[$table_suffix] = array(
                    'exists' => false,
                    'count' => 0,
                    'description' => $description,
                    'table_name' => $table_name
                );
            }
        }

        return $stats;
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Poker Tournament Data Mart Cleaner', 'poker-tournament-import'); ?></h1>

            <?php if (isset($_GET['cleaned'])): ?>
                <div class="notice notice-success is-dismissible">
                    <?php
                    $cleaned = sanitize_text_field($_GET['cleaned']);
                    switch ($cleaned) {
                        case 'statistics':
                            echo '<p>' . __('Statistics table cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'financial':
                            echo '<p>' . __('Financial data tables cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'player_data':
                            echo '<p>' . __('Player data cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'analytics':
                            echo '<p>' . __('Analytics tables cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'options':
                            echo '<p>' . __('WordPress options cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'all':
                            echo '<p>' . __('All data mart tables cleaned successfully!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'reset_all':
                            echo '<p>' . __('Complete plugin data reset successful!', 'poker-tournament-import') . '</p>';
                            break;
                        case 'migrated':
                            echo '<p>' . __('Tournament data migration completed successfully!', 'poker-tournament-import') . '</p>';
                            break;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php _e('Current Data Mart Status', 'poker-tournament-import'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'poker-tournament-import'); ?></th>
                            <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                            <th><?php _e('Records', 'poker-tournament-import'); ?></th>
                            <th><?php _e('Status', 'poker-tournament-import'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stats = $this->get_data_mart_stats();
                        foreach ($stats as $table_suffix => $info):
                            $status_class = $info['count'] > 0 ? 'status-active' : 'status-inactive';
                            $status_text = $info['count'] > 0 ? __('Has Data', 'poker-tournament-import') : __('Empty', 'poker-tournament-import');
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($table_suffix); ?></code></td>
                            <td><?php echo esc_html($info['description']); ?></td>
                            <td><?php echo number_format_i18n($info['count']); ?></td>
                            <td><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- CRITICAL FIX: Tournament Migration Section -->
            <div class="card">
                <h2><?php _e('Tournament Chronological Processing Status', 'poker-tournament-import'); ?></h2>
                <?php
                $migration_stats = $this->get_enhanced_data_mart_stats();
                $migration_status = $migration_stats['migration_status'];
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Status', 'poker-tournament-import'); ?></th>
                            <th><?php _e('Count', 'poker-tournament-import'); ?></th>
                            <th><?php _e('Action', 'poker-tournament-import'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="status-success"><?php _e('Tournaments with Chronological Processing', 'poker-tournament-import'); ?></span></td>
                            <td class="migration-status-migrated"><?php echo number_format_i18n($migration_status['migrated_count']); ?></td>
                            <td><span class="status-success"><?php _e('✓ Ready', 'poker-tournament-import'); ?></span></td>
                        </tr>
                        <tr>
                            <td><span class="status-warning"><?php _e('Tournaments Needing Re-import', 'poker-tournament-import'); ?></span></td>
                            <td class="migration-status-needs-reimport"><?php echo number_format_i18n($migration_status['needs_reimport_count']); ?></td>
                            <td>
                                <button type="button" class="button poker-ajax-button" data-action="migrate_tournaments" <?php echo $migration_status['needs_reimport_count'] > 0 ? '' : 'disabled'; ?>>
                                    <?php _e('Migrate Now', 'poker-tournament-import'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="status-info"><?php _e('Total Tournaments', 'poker-tournament-import'); ?></span></td>
                            <td class="migration-status-total"><?php echo number_format_i18n($migration_status['total_tournaments']); ?></td>
                            <td><span class="status-info"><?php _e('─', 'poker-tournament-import'); ?></span></td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($migration_status['needs_reimport_count'] > 0): ?>
                    <div class="migration-notice" style="background: #fcf9e6; border: 1px solid #f0ad4e; border-radius: 4px; padding: 10px; margin-top: 15px;">
                        <p><strong><?php _e('Migration Needed:', 'poker-tournament-import'); ?></strong> <?php echo sprintf(
                            __('%d tournaments were imported before version 2.1.3 and lack raw TDT content needed for chronological processing. Re-import these tournaments with their original .tdt files to enable accurate results.', 'poker-tournament-import'),
                            $migration_status['needs_reimport_count']
                        ); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2><?php _e('Data Cleaning Options', 'poker-tournament-import'); ?></h2>
                <p><strong><?php _e('⚠️ WARNING: These actions are irreversible and will permanently delete data!', 'poker-tournament-import'); ?></strong></p>

                <div class="cleaning-options">
                    <!-- Basic Cleaning -->
                    <div class="option-group">
                        <h3><?php _e('Basic Cleaning', 'poker-tournament-import'); ?></h3>
                        <p><?php _e('Remove calculated statistics and analytics while preserving raw tournament data.', 'poker-tournament-import'); ?></p>

                        <button type="button" class="button poker-ajax-button" data-action="clean_statistics">
                            <?php _e('Clean Statistics Only', 'poker-tournament-import'); ?>
                        </button>

                        <button type="button" class="button poker-ajax-button" data-action="clean_financial" style="margin-left: 10px;">
                            <?php _e('Clean Financial Data', 'poker-tournament-import'); ?>
                        </button>
                    </div>

                    <!-- Advanced Cleaning -->
                    <div class="option-group">
                        <h3><?php _e('Advanced Cleaning', 'poker-tournament-import'); ?></h3>
                        <p><?php _e('Remove all derived data including player analytics and options.', 'poker-tournament-import'); ?></p>

                        <button type="button" class="button poker-ajax-button" data-action="clean_all">
                            <?php _e('Clean All Data Mart', 'poker-tournament-import'); ?>
                        </button>
                    </div>

                    <!-- Complete Reset -->
                    <div class="option-group danger-zone">
                        <h3><?php _e('⚠️ DANGER ZONE - Complete Reset', 'poker-tournament-import'); ?></h3>
                        <p><strong><?php _e('This will remove ALL plugin data including tournament posts, player posts, series posts, taxonomies, post meta, statistics tables, uploaded files, options, transients, and user meta. This action cannot be undone!', 'poker-tournament-import'); ?></strong></p>

                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field('poker_data_mart_cleaner'); ?>
                            <input type="hidden" name="poker_cleaner_action" value="reset_all">
                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you absolutely sure you want to delete ALL plugin data? This cannot be undone!', 'poker-tournament-import')); ?>')">
                                <?php _e('Reset All Plugin Data', 'poker-tournament-import'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2><?php _e('Explanation of Data Types', 'poker-tournament-import'); ?></h2>
                <dl>
                    <dt><strong><?php _e('Statistics', 'poker-tournament-import'); ?></strong></dt>
                    <dd><?php _e('Calculated dashboard metrics like total tournaments, players, prize pools, and KPIs.', 'poker-tournament-import'); ?></dd>

                    <dt><strong><?php _e('Financial Data', 'poker-tournament-import'); ?></strong></dt>
                    <dd><?php _e('Tournament financial summaries, ROI calculations, cost breakdowns, and revenue analytics.', 'poker-tournament-import'); ?></dd>

                    <dt><strong><?php _e('Player Data', 'poker-tournament-import'); ?></strong></dt>
                    <dd><?php _e('Player ROI tracking, performance metrics, and investment analysis.', 'poker-tournament-import'); ?></dd>

                    <dt><strong><?php _e('Analytics', 'poker-tournament-import'); ?></strong></dt>
                    <dd><?php _e('Monthly revenue trends, profit analytics, and growth metrics.', 'poker-tournament-import'); ?></dd>

                    <dt><strong><?php _e('Complete Reset', 'poker-tournament-import'); ?></strong></dt>
                    <dd><?php _e('Removes EVERYTHING: All tournament posts, player posts, series posts, season posts, all taxonomies and terms, all post meta data, all data mart tables, uploaded .tdt files, plugin options, transients, and user meta data. Complete plugin factory reset.', 'poker-tournament-import'); ?></dd>
                </dl>
            </div>
        </div>

        <style>
        .cleaning-options {
            margin: 20px 0;
        }

        .option-group {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-left: 4px solid #2271b1;
            border-radius: 4px;
            margin-bottom: 20px;
            padding: 20px;
        }

        .option-group.danger-zone {
            border-left-color: #d63638;
            background: #fef7f7;
        }

        .option-group h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }

        .option-group.danger-zone h3 {
            color: #d63638;
        }

        .status-active {
            color: #00a32a;
            font-weight: bold;
        }

        .status-inactive {
            color: #787c82;
        }

        .status-success {
            color: #00a32a;
            font-weight: bold;
        }

        .status-warning {
            color: #d63638;
            font-weight: bold;
        }

        .status-info {
            color: #2271b1;
            font-weight: bold;
        }

        .card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            margin: 20px 0;
            padding: 20px;
        }

        .card h2 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        dl {
            margin: 0;
        }

        dt {
            margin-top: 15px;
            font-weight: bold;
        }

        dd {
            margin: 5px 0 0 20px;
            color: #50575e;
        }

        /* AJAX Loading Styles */
        .poker-ajax-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .poker-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2271b1;
            border-radius: 50%;
            animation: poker-spin 1s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }

        @keyframes poker-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .poker-ajax-message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }

        .poker-ajax-success {
            background: #f0f6fc;
            border: 1px solid #00a32a;
            color: #00a32a;
        }

        .poker-ajax-error {
            background: #fef7f7;
            border: 1px solid #d63638;
            color: #d63638;
        }

        .poker-ajax-warning {
            background: #fcf9e6;
            border: 1px solid #f0ad4e;
            color: #0073aa;
        }

        .button.poker-ajax-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .poker-progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .poker-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2271b1, #135e96);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce("poker_data_mart_cleaner"); ?>';

            // Initialize AJAX functionality
            $('.poker-ajax-button').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var action = $button.data('action');
                var confirmMessage = $button.data('confirm');

                if (confirmMessage && !confirm(confirmMessage)) {
                    return;
                }

                performAjaxAction($button, action);
            });

            // Perform AJAX action
            function performAjaxAction($button, action) {
                var $container = $button.closest('.option-group');
                var originalText = $button.html();

                // Show loading state
                $button.addClass('poker-ajax-disabled');
                $button.html('<span class="poker-spinner"></span>' + '<?php _e('Processing...', 'poker-tournament-import'); ?>');

                // Remove previous messages
                $('.poker-ajax-message').remove();

                var ajaxData = {
                    action: 'poker_clean_data_mart',
                    cleaning_action: action,
                    nonce: nonce
                };

                // Special handling for migration
                if (action === 'migrate_tournaments') {
                    ajaxData.action = 'poker_migrate_tournaments';
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        // Restore button
                        $button.removeClass('poker-ajax-disabled');
                        $button.html(originalText);

                        // Show result message
                        var messageClass = response.success ? 'poker-ajax-success' : 'poker-ajax-error';
                        var messageHtml = '<div class="poker-ajax-message ' + messageClass + '">' +
                                       response.message + '</div>';
                        $container.prepend(messageHtml);

                        // Update data if successful
                        if (response.success && response.data) {
                            updateDataDisplays(response.data);
                        }

                        // Auto-hide success messages
                        if (response.success) {
                            setTimeout(function() {
                                $('.poker-ajax-message').fadeOut();
                            }, 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Restore button
                        $button.removeClass('poker-ajax-disabled');
                        $button.html(originalText);

                        // Show error message
                        var errorMessage = '<?php _e('AJAX request failed. Please try again.', 'poker-tournament-import'); ?>';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        var messageHtml = '<div class="poker-ajax-message poker-ajax-error">' +
                                       errorMessage + '</div>';
                        $container.prepend(messageHtml);
                    }
                });
            }

            // Update data displays after AJAX operation
            function updateDataDisplays(data) {
                // Update stats table
                if (data.stats) {
                    updateStatsTable(data.stats);
                }

                // Update migration status
                if (data.migration_status) {
                    updateMigrationStatus(data.migration_status);
                }
            }

            // Update statistics table
            function updateStatsTable(stats) {
                $('tbody tr').each(function() {
                    var $row = $(this);
                    var tableCode = $row.find('td:first-child code').text();

                    if (stats[tableCode]) {
                        var info = stats[tableCode];
                        $row.find('td:nth-child(3)').text(info.count.toLocaleString());

                        var $statusCell = $row.find('td:nth-child(4) span');
                        $statusCell.removeClass('status-active status-inactive');

                        if (info.count > 0) {
                            $statusCell.addClass('status-active').text('<?php _e('Has Data', 'poker-tournament-import'); ?>');
                        } else {
                            $statusCell.addClass('status-inactive').text('<?php _e('Empty', 'poker-tournament-import'); ?>');
                        }
                    }
                });
            }

            // Update migration status
            function updateMigrationStatus(migrationStatus) {
                // Update counts
                $('.migration-status-migrated').text(migrationStatus.migrated_count.toLocaleString());
                $('.migration-status-needs-reimport').text(migrationStatus.needs_reimport_count.toLocaleString());
                $('.migration-status-total').text(migrationStatus.total_tournaments.toLocaleString());

                // Update migration button state
                var $migrateButton = $('button[data-action="migrate_tournaments"]');
                if (migrationStatus.needs_reimport_count > 0) {
                    $migrateButton.prop('disabled', false);
                } else {
                    $migrateButton.prop('disabled', true);
                }

                // Update migration notice
                var $migrationNotice = $('.migration-notice');
                if (migrationStatus.needs_reimport_count > 0) {
                    if ($migrationNotice.length === 0) {
                        var noticeHtml = '<div class="migration-notice" style="background: #fcf9e6; border: 1px solid #f0ad4e; border-radius: 4px; padding: 10px; margin-top: 15px;">' +
                                       '<p><strong><?php _e('Migration Needed:', 'poker-tournament-import'); ?></strong> ' +
                                       '<?php echo sprintf(__('%d tournaments were imported before version 2.1.3 and lack raw TDT content needed for chronological processing. Re-import these tournaments with their original .tdt files to enable accurate results.', 'poker-tournament-import'), '{COUNT}'); ?></p></div>';
                        noticeHtml = noticeHtml.replace('{COUNT}', migrationStatus.needs_reimport_count);
                        $('.migration-status-table').closest('.card').append(noticeHtml);
                    }
                } else {
                    $migrationNotice.remove();
                }
            }

            // Auto-refresh status every 30 seconds
            setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'GET',
                    data: {
                        action: 'poker_get_cleaning_status',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            updateDataDisplays(response.data);
                        }
                    }
                });
            }, 30000);
        });
        </script>
        <?php
    }

    /**
     * CRITICAL FIX: Migrate existing tournaments to store raw TDT content
     * This enables chronological processing for tournaments imported before v2.1.3
     */
    private function migrate_tournament_data() {
        global $wpdb;

        Poker_Tournament_Import_Debug::log('Starting tournament data migration for chronological processing');

        // Get all tournament posts
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'any'
        ));

        $migrated_count = 0;
        $error_count = 0;

        foreach ($tournaments as $tournament_id) {
            // Check if tournament already has raw content
            $existing_raw = get_post_meta($tournament_id, '_tournament_raw_content', true);
            if ($existing_raw) {
                continue; // Skip if already migrated
            }

            // Check if tournament has processed data (can identify tournaments that need original TDT files)
            $tournament_data = get_post_meta($tournament_id, 'tournament_data', true);
            if (!$tournament_data) {
                continue; // Skip if no data at all
            }

            // For this migration, we need to identify which tournaments have original .tdt files
            // Since we don't have the original files, we'll add a marker indicating re-import is needed
            update_post_meta($tournament_id, '_needs_reimport', true);
            update_post_meta($tournament_id, '_reimport_reason', 'Missing raw TDT content for chronological processing');

            $migrated_count++;
            Poker_Tournament_Import_Debug::log("Marked tournament {$tournament_id} for re-import (missing raw content)");
        }

        // Log migration results
        if ($migrated_count > 0) {
            error_log("Poker Migration: Marked {$migrated_count} tournaments for re-import to enable chronological processing");
            Poker_Tournament_Import_Debug::log_success("Migration completed: {$migrated_count} tournaments marked for re-import");
        } else {
            error_log("Poker Migration: No tournaments needed migration - all have raw content");
            Poker_Tournament_Import_Debug::log("Migration completed: All tournaments already have raw content");
        }

        if ($error_count > 0) {
            error_log("Poker Migration: {$error_count} errors occurred during migration");
        }

        return $migrated_count > 0;
    }

    /**
     * Get tournaments that need re-import for chronological processing
     */
    public function get_tournaments_needing_reimport() {
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'meta_key' => '_needs_reimport',
            'meta_value' => '1',
            'fields' => 'ids'
        ));

        return $tournaments;
    }

    /**
     * Get data mart statistics with migration status
     */
    public function get_enhanced_data_mart_stats() {
        $stats = $this->get_data_mart_stats();

        // Add migration status
        $needing_reimport = $this->get_tournaments_needing_reimport();
        $stats['migration_status'] = array(
            'needs_reimport_count' => count($needing_reimport),
            'total_tournaments' => count(get_posts(array('post_type' => 'tournament', 'posts_per_page' => -1, 'fields' => 'ids'))),
            'migrated_count' => count(get_posts(array(
                'post_type' => 'tournament',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => '_tournament_raw_content',
                'meta_compare' => 'EXISTS'
            )))
        );

        return $stats;
    }

    /**
     * Handle AJAX cleaning requests
     */
    public function handle_ajax_cleaning() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'poker_data_mart_cleaner')) {
            wp_die(__('Security check failed.', 'poker-tournament-import'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'poker-tournament-import'));
        }

        $action = sanitize_text_field($_POST['cleaning_action']);
        $response = array(
            'success' => false,
            'message' => '',
            'data' => array()
        );

        try {
            switch ($action) {
                case 'clean_statistics':
                    $result = $this->clean_statistics_table();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('Statistics table cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean statistics table.', 'poker-tournament-import');
                    }
                    break;

                case 'clean_financial':
                    $result = $this->clean_financial_tables();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('Financial data tables cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean financial data tables.', 'poker-tournament-import');
                    }
                    break;

                case 'clean_player_data':
                    $result = $this->clean_player_data();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('Player data cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean player data.', 'poker-tournament-import');
                    }
                    break;

                case 'clean_analytics':
                    $result = $this->clean_analytics_tables();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('Analytics tables cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean analytics tables.', 'poker-tournament-import');
                    }
                    break;

                case 'clean_options':
                    $result = $this->clean_wordpress_options();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('WordPress options cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean WordPress options.', 'poker-tournament-import');
                    }
                    break;

                case 'clean_all':
                    $result = $this->clean_all_data_mart();
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = __('All data mart tables cleaned successfully!', 'poker-tournament-import');
                    } else {
                        $response['message'] = __('Failed to clean all data mart tables.', 'poker-tournament-import');
                    }
                    break;

                default:
                    $response['message'] = __('Unknown cleaning action.', 'poker-tournament-import');
                    break;
            }

            // Get updated stats if successful
            if ($response['success']) {
                $response['data']['stats'] = $this->get_data_mart_stats();
            }

        } catch (Exception $e) {
            $response['message'] = sprintf(__('Error: %s', 'poker-tournament-import'), $e->getMessage());
            Poker_Tournament_Import_Debug::log_error('AJAX cleaning error: ' . $e->getMessage());
        }

        wp_send_json($response);
    }

    /**
     * Handle AJAX tournament migration requests
     */
    public function handle_ajax_migration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'poker_data_mart_cleaner')) {
            wp_die(__('Security check failed.', 'poker-tournament-import'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'poker-tournament-import'));
        }

        $response = array(
            'success' => false,
            'message' => '',
            'data' => array()
        );

        try {
            $result = $this->migrate_tournament_data();
            if ($result) {
                $response['success'] = true;
                $response['message'] = __('Tournament data migration completed successfully!', 'poker-tournament-import');
            } else {
                $response['message'] = __('No tournaments needed migration.', 'poker-tournament-import');
            }

            // Get updated migration status
            $response['data']['migration_status'] = $this->get_enhanced_data_mart_stats()['migration_status'];

        } catch (Exception $e) {
            $response['message'] = sprintf(__('Error: %s', 'poker-tournament-import'), $e->getMessage());
            Poker_Tournament_Import_Debug::log_error('AJAX migration error: ' . $e->getMessage());
        }

        wp_send_json($response);
    }

    /**
     * Handle AJAX status requests
     */
    public function handle_ajax_status() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'poker_data_mart_cleaner')) {
            wp_die(__('Security check failed.', 'poker-tournament-import'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'poker-tournament-import'));
        }

        $response = array(
            'success' => true,
            'data' => array()
        );

        try {
            $response['data']['stats'] = $this->get_data_mart_stats();
            $response['data']['migration_status'] = $this->get_enhanced_data_mart_stats()['migration_status'];
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = sprintf(__('Error: %s', 'poker-tournament-import'), $e->getMessage());
            Poker_Tournament_Import_Debug::log_error('AJAX status error: ' . $e->getMessage());
        }

        wp_send_json($response);
    }
}