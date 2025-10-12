<?php
/**
 * Plugin Name: Poker Tournament Import
 * Plugin URI: https://nikielhard.se/tdwpimport
 * Description: Import and display poker tournament results from Tournament Director (.tdt) files
 * Version: 1.3.1
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
define('POKER_TOURNAMENT_IMPORT_VERSION', '1.3.1');
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

        // Admin hooks
        if (is_admin()) {
            require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-admin.php';
            new Poker_Tournament_Import_Admin();
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
        // Will be implemented
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
            points decimal(10,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tournament_id (tournament_id),
            KEY player_id (player_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
Poker_Tournament_Import::get_instance();