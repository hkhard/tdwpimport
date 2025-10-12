<?php
/**
 * Plugin Name: Poker Tournament Import
 * Plugin URI: https://nikielhard.se/tdwpimport
 * Description: Import and display poker tournament results from Tournament Director (.tdt) files
 * Version: 1.6.1
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
define('POKER_TOURNAMENT_IMPORT_VERSION', '1.6.1');
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

        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Admin hooks
        if (is_admin()) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-admin.php';
            new Poker_Tournament_Import_Admin();
        }

        // AJAX handlers for tabbed interface
        add_action('wp_ajax_poker_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_nopriv_poker_series_tab_content', array($this, 'ajax_series_tab_content'));
        add_action('wp_ajax_poker_series_load_more', array($this, 'ajax_series_load_more'));
        add_action('wp_ajax_nopriv_poker_series_load_more', array($this, 'ajax_series_load_more'));
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
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Create database tables if needed
        $this->create_database_tables();
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
                $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
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
                    <td class="tournament-prize"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></td>
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
}

// Initialize the plugin
Poker_Tournament_Import::get_instance();