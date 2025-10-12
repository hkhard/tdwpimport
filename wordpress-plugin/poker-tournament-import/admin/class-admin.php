<?php
/**
 * Admin Class
 *
 * Handles admin interface functionality
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Import_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Poker Tournament Import', 'poker-tournament-import'),
            __('Poker Import', 'poker-tournament-import'),
            'manage_options',
            'poker-tournament-import',
            array($this, 'render_dashboard'),
            'dashicons-trophy',
            25
        );

        add_submenu_page(
            'poker-tournament-import',
            __('Import Tournament', 'poker-tournament-import'),
            __('Import Tournament', 'poker-tournament-import'),
            'manage_options',
            'poker-tournament-import-import',
            array($this, 'render_import_page')
        );

        add_submenu_page(
            'poker-tournament-import',
            __('Settings', 'poker-tournament-import'),
            __('Settings', 'poker-tournament-import'),
            'manage_options',
            'poker-tournament-import-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'poker-tournament-import') !== false) {
            wp_enqueue_style(
                'poker-tournament-import-admin',
                POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                POKER_TOURNAMENT_IMPORT_VERSION
            );

            wp_enqueue_script(
                'poker-tournament-import-admin',
                POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                POKER_TOURNAMENT_IMPORT_VERSION,
                true
            );
        }
    }

    /**
     * Render main dashboard
     */
    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="poker-import-dashboard">
                <div class="poker-import-card">
                    <h2><?php _e('Quick Stats', 'poker-tournament-import'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo wp_count_posts('tournament')->publish; ?></span>
                            <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo wp_count_posts('player')->publish; ?></span>
                            <span class="stat-label"><?php _e('Players', 'poker-tournament-import'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="poker-import-card">
                    <h2><?php _e('Recent Imports', 'poker-tournament-import'); ?></h2>
                    <?php
                    $recent_tournaments = get_posts(array(
                        'post_type' => 'tournament',
                        'numberposts' => 5,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ));

                    if ($recent_tournaments) {
                        echo '<ul>';
                        foreach ($recent_tournaments as $tournament) {
                            echo '<li>';
                            echo '<a href="' . get_edit_post_link($tournament->ID) . '">' . esc_html($tournament->post_title) . '</a>';
                            echo ' - ' . get_the_date('M j, Y', $tournament->ID);
                            echo '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p>' . __('No tournaments imported yet.', 'poker-tournament-import') . '</p>';
                    }
                    ?>
                </div>

                <div class="poker-import-card">
                    <h2><?php _e('Quick Actions', 'poker-tournament-import'); ?></h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=poker-tournament-import-import'); ?>" class="button button-primary">
                            <?php _e('Import New Tournament', 'poker-tournament-import'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('post-new.php?post_type=tournament'); ?>" class="button">
                            <?php _e('Add Tournament Manually', 'poker-tournament-import'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render import page
     */
    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="poker-import-upload-area">
                <h2><?php _e('Import Tournament from .tdt File', 'poker-tournament-import'); ?></h2>

                <form method="post" enctype="multipart/form-data" id="poker-import-form">
                    <?php wp_nonce_field('poker_import_tournament', 'poker_import_nonce'); ?>

                    <div class="upload-area">
                        <p><?php _e('Select a Tournament Director (.tdt) file to import:', 'poker-tournament-import'); ?></p>
                        <input type="file" name="tdt_file" id="tdt_file" accept=".tdt" required>
                    </div>

                    <div class="import-options">
                        <label>
                            <input type="checkbox" name="create_players" value="1" checked>
                            <?php _e('Create new players automatically', 'poker-tournament-import'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="publish_immediately" value="1">
                            <?php _e('Publish tournament immediately', 'poker-tournament-import'); ?>
                        </label>
                    </div>

                    <p class="submit">
                        <input type="submit" name="import_tournament" class="button button-primary" value="<?php _e('Import Tournament', 'poker-tournament-import'); ?>">
                    </p>
                </form>
            </div>

            <?php
            // Handle form submission
            if (isset($_POST['import_tournament']) && check_admin_referer('poker_import_tournament', 'poker_import_nonce')) {
                $this->handle_import_submission();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Handle import form submission
     */
    private function handle_import_submission() {
        if (!isset($_FILES['tdt_file']) || $_FILES['tdt_file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error"><p>' . __('Please select a valid .tdt file.', 'poker-tournament-import') . '</p></div>';
            return;
        }

        $file_path = $_FILES['tdt_file']['tmp_name'];

        try {
            // Parse the TDT file
            $parser = new Poker_Tournament_Parser();
            $tournament_data = $parser->parse_file($file_path);

            // Validate the data
            $errors = $parser->validate_data();
            if (!empty($errors)) {
                echo '<div class="notice notice-error"><p>' . __('Errors found in file:', 'poker-tournament-import') . '</p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul></div>';
                return;
            }

            // Import successful
            echo '<div class="notice notice-success"><p>' . __('Tournament imported successfully!', 'poker-tournament-import') . '</p></div>';

            // Show parsed data for review
            $this->show_import_preview($tournament_data);

        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . __('Import failed:', 'poker-tournament-import') . ' ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Show import preview
     */
    private function show_import_preview($tournament_data) {
        echo '<div class="import-preview">';
        echo '<h3>' . __('Import Preview', 'poker-tournament-import') . '</h3>';

        if (!empty($tournament_data['metadata'])) {
            echo '<h4>' . __('Tournament Information', 'poker-tournament-import') . '</h4>';
            echo '<table class="widefat">';
            echo '<tr><td>' . __('Title', 'poker-tournament-import') . '</td><td>' . esc_html($tournament_data['metadata']['title']) . '</td></tr>';
            if (!empty($tournament_data['metadata']['league_name'])) {
                echo '<tr><td>' . __('Series', 'poker-tournament-import') . '</td><td>' . esc_html($tournament_data['metadata']['league_name']) . '</td></tr>';
            }
            if (!empty($tournament_data['metadata']['season_name'])) {
                echo '<tr><td>' . __('Season', 'poker-tournament-import') . '</td><td>' . esc_html($tournament_data['metadata']['season_name']) . '</td></tr>';
            }
            if (!empty($tournament_data['metadata']['start_time'])) {
                echo '<tr><td>' . __('Date', 'poker-tournament-import') . '</td><td>' . esc_html($tournament_data['metadata']['start_time']) . '</td></tr>';
            }
            echo '</table>';
        }

        if (!empty($tournament_data['players'])) {
            echo '<h4>' . __('Players Found', 'poker-tournament-import') . '</h4>';
            echo '<p>' . sprintf(__('Found %d players in the tournament.', 'poker-tournament-import'), count($tournament_data['players'])) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('poker_tournament_import_settings');
                do_settings_sections('poker-tournament-import-settings');
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Buy-in Amount', 'poker-tournament-import'); ?></th>
                        <td>
                            <input type="number" name="poker_import_default_buyin" value="<?php echo esc_attr(get_option('poker_import_default_buyin', 200)); ?>" class="small-text">
                            <p class="description"><?php _e('Default buy-in amount for new tournaments.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-publish Tournaments', 'poker-tournament-import'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poker_import_auto_publish" value="1" <?php checked(get_option('poker_import_auto_publish', 0)); ?>>
                                <?php _e('Automatically publish tournaments after import', 'poker-tournament-import'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}