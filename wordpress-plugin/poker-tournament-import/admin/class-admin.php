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
        add_action('admin_init', array($this, 'handle_overwrite_confirmation'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_statistics_refresh'));

        // Dashboard AJAX handlers
        add_action('wp_ajax_poker_dashboard_load_content', array($this, 'handle_dashboard_load_content'));
        add_action('wp_ajax_poker_dashboard_detailed_view', array($this, 'handle_dashboard_detailed_view'));
        add_action('wp_ajax_poker_dashboard_generate_report', array($this, 'handle_dashboard_generate_report'));
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

        add_submenu_page(
            'poker-tournament-import',
            __('Migration Tools', 'poker-tournament-import'),
            __('Migration Tools', 'poker-tournament-import'),
            'manage_options',
            'poker-migration-tools',
            array($this, 'render_migration_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'poker_tournament_import_settings',
            'poker_import_default_buyin',
            array(
                'type' => 'integer',
                'description' => 'Default buy-in amount for new tournaments',
                'sanitize_callback' => 'absint',
                'default' => 200,
            )
        );

        register_setting(
            'poker_tournament_import_settings',
            'poker_import_auto_publish',
            array(
                'type' => 'boolean',
                'description' => 'Automatically publish tournaments after import',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        register_setting(
            'poker_tournament_import_settings',
            'poker_import_debug_mode',
            array(
                'type' => 'boolean',
                'description' => 'Enable debug mode for troubleshooting',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        register_setting(
            'poker_tournament_import_settings',
            'poker_import_debug_logging',
            array(
                'type' => 'boolean',
                'description' => 'Enable debug logging to error log',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        register_setting(
            'poker_tournament_import_settings',
            'poker_statistics_last_refresh',
            array(
                'type' => 'string',
                'description' => 'Last time statistics were refreshed',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        // Handle database repair
        if (isset($_POST['repair_database']) && check_admin_referer('poker_repair_database', 'poker_repair_nonce')) {
            $this->handle_database_repair();
        }

        // Handle player data repair
        if (isset($_POST['repair_player_data']) && check_admin_referer('poker_repair_player_data', 'poker_repair_player_nonce')) {
            $this->handle_player_data_repair();
        }
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

            // Enqueue migration tools styles
            if (strpos($hook, 'poker-migration-tools') !== false) {
                wp_enqueue_style(
                    'poker-migration-tools',
                    POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/migration-tools.css',
                    array(),
                    POKER_TOURNAMENT_IMPORT_VERSION
                );
            }
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
                        <br>
                        <label>
                            <input type="checkbox" name="enable_debug_this_import" value="1">
                            <?php _e('Enable debug for this import only', 'poker-tournament-import'); ?>
                        </label>
                        <?php if (get_option('poker_import_debug_mode', 0)) { ?>
                            <p class="description"><?php _e('Note: Global debug mode is already enabled.', 'poker-tournament-import'); ?></p>
                        <?php } ?>
                    </div>

                    <p class="submit">
                        <input type="submit" name="import_tournament" class="button button-primary" value="<?php _e('Import Tournament', 'poker-tournament-import'); ?>">
                        <input type="hidden" name="import_tournament_hidden" value="1">
                    </p>
                </form>
            </div>

            <?php
            // Handle form submission
            if (isset($_POST['import_tournament']) || isset($_POST['import_tournament_hidden'])) {
                if (check_admin_referer('poker_import_tournament', 'poker_import_nonce')) {
                    $this->handle_import_submission();
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Security check failed. Please try again.', 'poker-tournament-import') . '</p></div>';
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Handle import form submission
     */
    private function handle_import_submission() {
        // Initialize debug system
        Poker_Tournament_Import_Debug::init();

        if (Poker_Tournament_Import_Debug::is_import_debug_enabled()) {
            Poker_Tournament_Import_Debug::log('=== IMPORT SUBMISSION STARTED ===');
            Poker_Tournament_Import_Debug::log('Import submission detected');
            Poker_Tournament_Import_Debug::log('$_POST data', array_keys($_POST));
            Poker_Tournament_Import_Debug::log('$_FILES data', array_keys($_FILES));
        }

        if (!isset($_FILES['tdt_file']) || $_FILES['tdt_file']['error'] !== UPLOAD_ERR_OK) {
            Poker_Tournament_Import_Debug::log_error('File upload error', $_FILES['tdt_file']);
            echo '<div class="notice notice-error"><p>' . __('Please select a valid .tdt file.', 'poker-tournament-import') . '</p></div>';

            // Always show debug status in case of error
            $this->show_debug_output();
            return;
        }

        $file_path = $_FILES['tdt_file']['tmp_name'];
        $file_size = filesize($file_path);
        Poker_Tournament_Import_Debug::log('File upload successful');
        Poker_Tournament_Import_Debug::log('File path', $file_path);
        Poker_Tournament_Import_Debug::log('File size', $file_size . ' bytes');

        try {
            // Parse the TDT file
            Poker_Tournament_Import_Debug::log_time('Starting file parsing');
            Poker_Tournament_Import_Debug::log_function('Poker_Tournament_Parser::parse_file');

            $parser = new Poker_Tournament_Parser();
            $tournament_data = $parser->parse_file($file_path);

            Poker_Tournament_Import_Debug::log_success('File parsing completed');
            Poker_Tournament_Import_Debug::log('Extracted data keys', array_keys($tournament_data));
            Poker_Tournament_Import_Debug::log_time('File parsing completed');

            // Validate the data
            Poker_Tournament_Import_Debug::log('Starting data validation');
            $errors = $parser->validate_data();

            if (!empty($errors)) {
                Poker_Tournament_Import_Debug::log_error('Validation failed', $errors);
                echo '<div class="notice notice-error"><p>' . __('Errors found in file:', 'poker-tournament-import') . '</p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul></div>';
                $this->show_debug_output();
                return;
            }
            Poker_Tournament_Import_Debug::log_success('Data validation passed');

            // Check for duplicate tournament
            $tournament_uuid = $tournament_data['metadata']['uuid'] ?? '';
            Poker_Tournament_Import_Debug::log('Tournament UUID', $tournament_uuid);

            Poker_Tournament_Import_Debug::log_function('check_duplicate_tournament', array($tournament_uuid));
            $duplicate_check = $this->check_duplicate_tournament($tournament_uuid);

            if ($duplicate_check) {
                Poker_Tournament_Import_Debug::log_warning('Duplicate tournament found', $duplicate_check);
                // Show duplicate confirmation dialog
                $this->show_duplicate_confirmation($tournament_data, $duplicate_check);
            } else {
                Poker_Tournament_Import_Debug::log('No duplicate found, proceeding with import');
                Poker_Tournament_Import_Debug::log_function('import_tournament_data');

                // Import the tournament
                $import_result = $this->import_tournament_data($tournament_data);

                Poker_Tournament_Import_Debug::log('Import result', $import_result);

                if ($import_result['success']) {
                    Poker_Tournament_Import_Debug::log_success('Tournament import completed successfully', $import_result['created_posts']);
                    echo '<div class="notice notice-success"><p>' .
                        sprintf(__('Tournament "%s" imported successfully!', 'poker-tournament-import'),
                            esc_html($tournament_data['metadata']['title'])) . '</p></div>';

                    // Show summary of what was created
                    $this->show_import_summary($import_result['created_posts']);
                } else {
                    Poker_Tournament_Import_Debug::log_error('Tournament import failed', $import_result['message']);
                    echo '<div class="notice notice-error"><p>' .
                        __('Import failed:', 'poker-tournament-import') . ' ' .
                        esc_html($import_result['message']) . '</p></div>';
                }
            }

            // Show parsed data for review
            $this->show_import_preview($tournament_data);
            Poker_Tournament_Import_Debug::log_time('Import process completed');

        } catch (Exception $e) {
            Poker_Tournament_Import_Debug::log_error('Import exception caught', $e);
            echo '<div class="notice notice-error"><p>' . __('Import failed:', 'poker-tournament-import') . ' ' . esc_html($e->getMessage()) . '</p></div>';
        }

        // Always show debug output for testing
        $this->show_debug_output();
        Poker_Tournament_Import_Debug::clear_debug_messages();
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
     * Show debug output
     */
    private function show_debug_output() {
        // Only show debug output if debug is enabled for this import
        if ((isset($_POST['import_tournament']) || isset($_POST['import_tournament_hidden'])) && Poker_Tournament_Import_Debug::is_import_debug_enabled()) {
            echo Poker_Tournament_Import_Debug::render_debug_output();
        }
    }

    /**
     * Check for duplicate tournament by UUID
     */
    private function check_duplicate_tournament($tournament_uuid) {
        $args = array(
            'post_type' => 'tournament',
            'meta_query' => array(
                array(
                    'key' => 'tournament_uuid',
                    'value' => $tournament_uuid,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );

        $existing_tournaments = get_posts($args);

        if (!empty($existing_tournaments)) {
            return array(
                'post_id' => $existing_tournaments[0]->ID,
                'title' => $existing_tournaments[0]->post_title,
                'edit_url' => get_edit_post_link($existing_tournaments[0]->ID)
            );
        }

        return false;
    }

    /**
     * Show duplicate confirmation dialog
     */
    private function show_duplicate_confirmation($tournament_data, $duplicate_tournament) {
        ?>
        <div class="notice notice-warning">
            <h3><?php _e('Duplicate Tournament Detected', 'poker-tournament-import'); ?></h3>
            <p>
                <?php
                printf(
                    __('A tournament with the same UUID already exists: <strong>%s</strong>. This tournament was imported on %s.', 'poker-tournament-import'),
                    sprintf('<a href="%s">%s</a>', esc_url($duplicate_tournament['edit_url']), esc_html($duplicate_tournament['title'])),
                    get_the_date('F j, Y g:i a', $duplicate_tournament['post_id'])
                );
                ?>
            </p>
            <p>
                <?php _e('Do you want to overwrite this tournament with the new data? This will replace all tournament data including player results.', 'poker-tournament-import'); ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('poker_import_overwrite', 'poker_import_overwrite_nonce'); ?>
                <input type="hidden" name="tournament_data" value="<?php echo esc_attr(json_encode($tournament_data)); ?>">
                <input type="hidden" name="overwrite_tournament_id" value="<?php echo esc_attr($duplicate_tournament['post_id']); ?>">

                <p>
                    <button type="submit" name="confirm_overwrite" class="button button-primary">
                        <?php _e('Yes, Overwrite Tournament', 'poker-tournament-import'); ?>
                    </button>
                    <button type="button" class="button" onclick="history.back()">
                        <?php _e('Cancel', 'poker-tournament-import'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Import tournament data into WordPress posts
     */
    private function import_tournament_data($tournament_data) {
        global $wpdb;
        Poker_Tournament_Import_Debug::log_function('import_tournament_data started');

        try {
            $created_posts = array();
            $publish_immediately = isset($_POST['publish_immediately']) && $_POST['publish_immediately'] == '1';
            $status = $publish_immediately ? 'publish' : 'draft';
            Poker_Tournament_Import_Debug::log('Publication status', $status);

            // Create or find series
            $series_id = 0;
            if (!empty($tournament_data['metadata']['league_name'])) {
                Poker_Tournament_Import_Debug::log('Creating series', $tournament_data['metadata']['league_name']);
                $series_id = $this->create_or_find_series(
                    $tournament_data['metadata']['league_name'],
                    $tournament_data['metadata']['league_uuid'] ?? '',
                    $status
                );
                Poker_Tournament_Import_Debug::log('Series creation result', $series_id);
                if ($series_id) {
                    $created_posts['series'] = $series_id;
                    Poker_Tournament_Import_Debug::log_success('Series created successfully');
                } else {
                    Poker_Tournament_Import_Debug::log_warning('Series creation returned 0');
                }
            } else {
                Poker_Tournament_Import_Debug::log('No league name found in metadata');
            }

            // Create or find season
            $season_id = 0;
            if (!empty($tournament_data['metadata']['season_name'])) {
                Poker_Tournament_Import_Debug::log('Creating season', $tournament_data['metadata']['season_name']);
                $season_id = $this->create_or_find_season(
                    $tournament_data['metadata']['season_name'],
                    $tournament_data['metadata']['season_uuid'] ?? '',
                    $status
                );
                Poker_Tournament_Import_Debug::log('Season creation result', $season_id);
                if ($season_id) {
                    $created_posts['season'] = $season_id;
                    Poker_Tournament_Import_Debug::log_success('Season created successfully');
                } else {
                    Poker_Tournament_Import_Debug::log_warning('Season creation returned 0');
                }
            } else {
                Poker_Tournament_Import_Debug::log('No season name found in metadata');
            }

            // Create players if needed
            $player_ids = array();
            $create_players = isset($_POST['create_players']) && $_POST['create_players'] == '1';
            Poker_Tournament_Import_Debug::log('Create players option', $create_players ? 'Yes' : 'No');

            if ($create_players && !empty($tournament_data['players'])) {
                $player_count = count($tournament_data['players']);
                Poker_Tournament_Import_Debug::log('Processing players', $player_count . ' players found');

                foreach ($tournament_data['players'] as $player_uuid => $player_data) {
                    Poker_Tournament_Import_Debug::log('Creating player', $player_data['nickname'] . ' (UUID: ' . $player_uuid . ')');
                    $player_id = $this->create_or_find_player(
                        $player_data['nickname'],
                        $player_uuid,
                        $status
                    );
                    Poker_Tournament_Import_Debug::log('Player creation result', $player_id);
                    if ($player_id) {
                        $player_ids[$player_uuid] = $player_id;
                    } else {
                        Poker_Tournament_Import_Debug::log_warning('Player creation failed for: ' . $player_data['nickname']);
                    }
                }
                $created_posts['players'] = count($player_ids);
                Poker_Tournament_Import_Debug::log_success('Player creation completed', $created_posts['players'] . ' of ' . $player_count . ' players created');
            } else {
                Poker_Tournament_Import_Debug::log('Skipping player creation', 'Option disabled or no players found');
            }

            // Create the tournament post
            Poker_Tournament_Import_Debug::log('Creating tournament post...');
            $tournament_id = $this->create_tournament_post(
                $tournament_data,
                $series_id,
                $season_id,
                $player_ids,
                $status
            );
            Poker_Tournament_Import_Debug::log('Tournament creation result', $tournament_id);

            if ($tournament_id && $tournament_id > 0) {
                $created_posts['tournament'] = $tournament_id;

                // **CRITICAL FIX**: Insert player results data into poker_tournament_players table
                Poker_Tournament_Import_Debug::log('Inserting player results into database');
                $players_inserted = $this->insert_tournament_players($tournament_id, $tournament_data, $player_ids);
                $created_posts['player_results'] = $players_inserted;
                Poker_Tournament_Import_Debug::log('Player results insertion completed', $players_inserted . ' players inserted');

                // **CRITICAL**: Calculate and store prize pool from tournament data
                $this->calculate_and_store_prize_pool($tournament_id, $tournament_data);

                // **CRITICAL**: Trigger statistics calculation after tournament import
                Poker_Tournament_Import_Debug::log('Triggering statistics calculation after tournament import');
                if (class_exists('Poker_Statistics_Engine')) {
                    $stats_engine = Poker_Statistics_Engine::get_instance();
                    $stats_result = $stats_engine->calculate_all_statistics();
                    if ($stats_result) {
                        Poker_Tournament_Import_Debug::log_success('Statistics calculation completed successfully');
                    } else {
                        Poker_Tournament_Import_Debug::log_warning('Statistics calculation failed');
                    }
                }

                Poker_Tournament_Import_Debug::log_success('Import process completed successfully', $created_posts);
                return array(
                    'success' => true,
                    'created_posts' => $created_posts
                );
            } else {
                Poker_Tournament_Import_Debug::log_error('Tournament post creation failed', array(
                    'series_id' => $series_id,
                    'season_id' => $season_id,
                    'player_count' => count($player_ids),
                    'status' => $status
                ));
                return array(
                    'success' => false,
                    'message' => __('Failed to create tournament post', 'poker-tournament-import')
                );
            }

        } catch (Exception $e) {
            Poker_Tournament_Import_Debug::log_error('Exception in import_tournament_data', $e);
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Create or find series post
     */
    private function create_or_find_series($series_name, $series_uuid = '', $status = 'draft') {
        // First, try to find existing series by UUID
        if (!empty($series_uuid)) {
            $args = array(
                'post_type' => 'tournament_series',
                'meta_query' => array(
                    array(
                        'key' => 'series_uuid',
                        'value' => $series_uuid,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            );

            $existing_series = get_posts($args);
            if (!empty($existing_series)) {
                return $existing_series[0]->ID;
            }
        }

        // Try to find by name if no UUID match
        $args = array(
            'post_type' => 'tournament_series',
            'title' => $series_name,
            'posts_per_page' => 1
        );

        $existing_series = get_posts($args);
        if (!empty($existing_series)) {
            return $existing_series[0]->ID;
        }

        // Create new series
        $post_data = array(
            'post_title' => $series_name,
            'post_content' => '',
            'post_status' => $status,
            'post_type' => 'tournament_series'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            if (!empty($series_uuid)) {
                update_post_meta($post_id, 'series_uuid', $series_uuid);
            }
            return $post_id;
        }

        return 0;
    }

    /**
     * Create or find season post
     */
    private function create_or_find_season($season_name, $season_uuid = '', $status = 'draft') {
        // First, try to find existing season by UUID
        if (!empty($season_uuid)) {
            $args = array(
                'post_type' => 'tournament_season',
                'meta_query' => array(
                    array(
                        'key' => 'season_uuid',
                        'value' => $season_uuid,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            );

            $existing_season = get_posts($args);
            if (!empty($existing_season)) {
                return $existing_season[0]->ID;
            }
        }

        // Try to find by name if no UUID match
        $args = array(
            'post_type' => 'tournament_season',
            'title' => $season_name,
            'posts_per_page' => 1
        );

        $existing_season = get_posts($args);
        if (!empty($existing_season)) {
            return $existing_season[0]->ID;
        }

        // Create new season
        $post_data = array(
            'post_title' => $season_name,
            'post_content' => '',
            'post_status' => $status,
            'post_type' => 'tournament_season'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            if (!empty($season_uuid)) {
                update_post_meta($post_id, 'season_uuid', $season_uuid);
            }
            return $post_id;
        }

        return 0;
    }

    /**
     * Create or find player post
     */
    private function create_or_find_player($player_name, $player_uuid, $status = 'draft') {
        // First, try to find existing player by UUID
        $args = array(
            'post_type' => 'player',
            'meta_query' => array(
                array(
                    'key' => 'player_uuid',
                    'value' => $player_uuid,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );

        $existing_player = get_posts($args);
        if (!empty($existing_player)) {
            return $existing_player[0]->ID;
        }

        // Try to find by name if no UUID match
        $args = array(
            'post_type' => 'player',
            'title' => $player_name,
            'posts_per_page' => 1
        );

        $existing_player = get_posts($args);
        if (!empty($existing_player)) {
            return $existing_player[0]->ID;
        }

        // Create new player
        $post_data = array(
            'post_title' => $player_name,
            'post_content' => '',
            'post_status' => $status,
            'post_type' => 'player'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'player_uuid', $player_uuid);
            return $post_id;
        }

        return 0;
    }

    /**
     * Create tournament post
     */
    private function create_tournament_post($tournament_data, $series_id = 0, $season_id = 0, $player_ids = array(), $status = 'draft') {
        Poker_Tournament_Import_Debug::log_function('create_tournament_post started');
        $metadata = $tournament_data['metadata'];
        Poker_Tournament_Import_Debug::log('Tournament metadata', $metadata);

        // **CRITICAL FIX**: Generate meaningful tournament content
        $content = $this->generate_tournament_content($tournament_data);

        $post_data = array(
            'post_title' => $metadata['title'],
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => 'tournament'
        );
        Poker_Tournament_Import_Debug::log('Post data prepared', $post_data);

        // Set tournament date if available
        if (!empty($metadata['start_time'])) {
            $post_data['post_date'] = date('Y-m-d H:i:s', strtotime($metadata['start_time']));
            $post_data['post_date_gmt'] = get_gmt_from_date($post_data['post_date']);
            Poker_Tournament_Import_Debug::log('Tournament date set', $post_data['post_date']);
        }

        Poker_Tournament_Import_Debug::log_wp_operation('wp_insert_post', $post_data);
        $tournament_id = wp_insert_post($post_data);
        Poker_Tournament_Import_Debug::log('wp_insert_post result', $tournament_id);

        if (is_wp_error($tournament_id)) {
            Poker_Tournament_Import_Debug::log_error('wp_insert_post failed', $tournament_id->get_error_message());
            return 0;
        }

        if ($tournament_id && $tournament_id > 0) {
            Poker_Tournament_Import_Debug::log_success('Tournament post created successfully', $tournament_id);

            // Store tournament metadata
            if (!empty($metadata['uuid'])) {
                update_post_meta($tournament_id, 'tournament_uuid', $metadata['uuid']);
                Poker_Tournament_Import_Debug::log('Stored tournament UUID', $metadata['uuid']);
            }
            if (!empty($metadata['league_name'])) {
                update_post_meta($tournament_id, 'tournament_series_name', $metadata['league_name']);
            }
            if (!empty($metadata['league_uuid'])) {
                update_post_meta($tournament_id, 'tournament_series_uuid', $metadata['league_uuid']);
            }
            if (!empty($metadata['season_name'])) {
                update_post_meta($tournament_id, 'tournament_season_name', $metadata['season_name']);
            }
            if (!empty($metadata['season_uuid'])) {
                update_post_meta($tournament_id, 'tournament_season_uuid', $metadata['season_uuid']);
            }
            if (!empty($metadata['start_time'])) {
                update_post_meta($tournament_id, 'tournament_date', $metadata['start_time']);
                // **CRITICAL FIX**: Also store with underscore prefix for template compatibility
                update_post_meta($tournament_id, '_tournament_date', $metadata['start_time']);
                Poker_Tournament_Import_Debug::log('Stored tournament date', $metadata['start_time']);
            }

            // Store full tournament data
            update_post_meta($tournament_id, 'tournament_data', $tournament_data);
            Poker_Tournament_Import_Debug::log('Stored full tournament data');

            // Store player relationships
            if (!empty($player_ids)) {
                update_post_meta($tournament_id, 'tournament_players', $player_ids);
                Poker_Tournament_Import_Debug::log('Stored player relationships', count($player_ids) . ' players');
            }

            // **CRITICAL FIX**: Store series and season relationship meta fields
            if ($series_id > 0) {
                update_post_meta($tournament_id, '_series_id', $series_id);
                Poker_Tournament_Import_Debug::log('Stored series relationship ID', $series_id);
            }
            if ($season_id > 0) {
                update_post_meta($tournament_id, '_season_id', $season_id);
                Poker_Tournament_Import_Debug::log('Stored season relationship ID', $season_id);
            }

            // **CRITICAL FIX**: Extract and store buy-in information for template display
            $buy_in = $this->extract_buy_in_from_tournament_data($tournament_data);
            if ($buy_in > 0) {
                update_post_meta($tournament_id, '_buy_in', $buy_in);
                Poker_Tournament_Import_Debug::log('Stored tournament buy-in', $buy_in);
            }

            // **CRITICAL FIX**: Extract and store currency information for template display
            $currency = $this->extract_currency_from_tournament_data($tournament_data);
            update_post_meta($tournament_id, '_currency', $currency);
            Poker_Tournament_Import_Debug::log('Stored tournament currency', $currency);

            // **CRITICAL FIX**: Store game type and format information for enhanced display
            $game_type = $this->extract_game_type_from_tournament_data($tournament_data);
            if ($game_type) {
                update_post_meta($tournament_id, '_game_type', $game_type);
                Poker_Tournament_Import_Debug::log('Stored game type', $game_type);
            }

            // **CRITICAL FIX**: Store tournament structure information
            $structure = $this->extract_structure_from_tournament_data($tournament_data);
            if ($structure) {
                update_post_meta($tournament_id, '_tournament_structure', $structure);
                Poker_Tournament_Import_Debug::log('Stored tournament structure', $structure);
            }

            // **CRITICAL FIX**: Store points calculation summary data for enhanced display
            $points_summary = $this->calculate_points_summary($tournament_data);
            if ($points_summary) {
                update_post_meta($tournament_id, '_points_summary', $points_summary);
                Poker_Tournament_Import_Debug::log('Stored points calculation summary', $points_summary);
            }

            // **CRITICAL FIX**: Store enhanced tournament statistics for dashboard
            $tournament_stats = $this->calculate_enhanced_tournament_stats($tournament_data);
            if ($tournament_stats) {
                update_post_meta($tournament_id, '_tournament_stats', $tournament_stats);
                Poker_Tournament_Import_Debug::log('Stored enhanced tournament stats', $tournament_stats);
            }

            // Apply taxonomy auto-categorization to tournament
            if (class_exists('Poker_Tournament_Import_Taxonomies')) {
                $taxonomies = new Poker_Tournament_Import_Taxonomies();
                $taxonomies->auto_categorize_tournament($tournament_id, $tournament_data);
                Poker_Tournament_Import_Debug::log('Applied taxonomy auto-categorization');
            }

            Poker_Tournament_Import_Debug::log_success('Tournament post creation completed successfully');
            return $tournament_id;
        }

        Poker_Tournament_Import_Debug::log_error('Tournament post creation failed - returned 0');
        return 0;
    }

    /**
     * Show import summary
     */
    private function show_import_summary($created_posts) {
        echo '<div class="import-summary">';
        echo '<h4>' . __('Import Summary', 'poker-tournament-import') . '</h4>';
        echo '<ul>';

        if (isset($created_posts['tournament'])) {
            echo '<li>' . sprintf(__('Tournament created: <a href="%s">Edit Tournament</a>', 'poker-tournament-import'),
                get_edit_post_link($created_posts['tournament'])) . '</li>';
        }

        if (isset($created_posts['series'])) {
            echo '<li>' . sprintf(__('Series: <a href="%s">Edit Series</a>', 'poker-tournament-import'),
                get_edit_post_link($created_posts['series'])) . '</li>';
        }

        if (isset($created_posts['season'])) {
            echo '<li>' . sprintf(__('Season: <a href="%s">Edit Season</a>', 'poker-tournament-import'),
                get_edit_post_link($created_posts['season'])) . '</li>';
        }

        if (isset($created_posts['players'])) {
            echo '<li>' . sprintf(__('%d player profiles created', 'poker-tournament-import'), $created_posts['players']) . '</li>';
        }

        if (isset($created_posts['player_results'])) {
            echo '<li>' . sprintf(__('%d player results imported (buy-ins, winnings, points)', 'poker-tournament-import'), $created_posts['player_results']) . '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    /**
     * Handle overwrite confirmation
     */
    public function handle_overwrite_confirmation() {
        if (isset($_POST['confirm_overwrite']) && check_admin_referer('poker_import_overwrite', 'poker_import_overwrite_nonce')) {
            $tournament_data = json_decode(stripslashes($_POST['tournament_data']), true);
            $overwrite_id = intval($_POST['overwrite_tournament_id']);

            if ($tournament_data && $overwrite_id > 0) {
                // Delete existing tournament
                wp_delete_post($overwrite_id, true);

                // Import new tournament data
                $import_result = $this->import_tournament_data($tournament_data);

                if ($import_result['success']) {
                    echo '<div class="notice notice-success"><p>' .
                        sprintf(__('Tournament "%s" has been overwritten successfully!', 'poker-tournament-import'),
                            esc_html($tournament_data['metadata']['title'])) . '</p></div>';

                    $this->show_import_summary($import_result['created_posts']);
                } else {
                    echo '<div class="notice notice-error"><p>' .
                        __('Overwrite failed:', 'poker-tournament-import') . ' ' .
                        esc_html($import_result['message']) . '</p></div>';
                }
            }
        }
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

                    <tr>
                        <th scope="row"><?php _e('Debug Mode', 'poker-tournament-import'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poker_import_debug_mode" value="1" <?php checked(get_option('poker_import_debug_mode', 0)); ?>>
                                <?php _e('Enable debug mode for troubleshooting', 'poker-tournament-import'); ?>
                            </label>
                            <p class="description"><?php _e('Show detailed debug information during import process. Useful for troubleshooting import issues.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Debug Logging', 'poker-tournament-import'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poker_import_debug_logging" value="1" <?php checked(get_option('poker_import_debug_logging', 0)); ?>>
                                <?php _e('Enable debug logging to error log', 'poker-tournament-import'); ?>
                            </label>
                            <p class="description"><?php _e('Write debug information to PHP error log. Check your server error logs.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <!-- Statistics Refresh Section -->
            <div class="statistics-refresh-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
                <h2><?php _e('Statistics Data Mart', 'poker-tournament-import'); ?></h2>
                <p><?php _e('The statistics data mart provides fast dashboard performance by pre-calculating tournament statistics. Refresh statistics after importing tournaments or when dashboard data appears outdated.', 'poker-tournament-import'); ?></p>

                <?php
                $last_refresh = get_option('poker_statistics_last_refresh', '');
                if ($last_refresh) {
                    echo '<p><strong>' . __('Last Refresh:', 'poker-tournament-import') . '</strong> ' . esc_html(date_i18n('F j, Y g:i a', strtotime($last_refresh))) . '</p>';
                } else {
                    echo '<p><strong>' . __('Last Refresh:', 'poker-tournament-import') . '</strong> ' . __('Never', 'poker-tournament-import') . '</p>';
                }

                if (class_exists('Poker_Statistics_Engine')) {
                    $stats_engine = Poker_Statistics_Engine::get_instance();
                    $dashboard_stats = $stats_engine->get_dashboard_statistics();
                    if (!empty($dashboard_stats['last_updated'])) {
                        echo '<p><strong>' . __('Data Last Updated:', 'poker-tournament-import') . '</strong> ' . esc_html(date_i18n('F j, Y g:i a', strtotime($dashboard_stats['last_updated']))) . '</p>';
                    }
                }
                ?>

                <form method="post" id="statistics-refresh-form">
                    <?php wp_nonce_field('poker_refresh_statistics_admin', 'poker_refresh_stats_nonce'); ?>
                    <p>
                        <button type="submit" name="refresh_statistics" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Refresh Statistics Now', 'poker-tournament-import'); ?>
                        </button>
                        <span class="spinner" style="display: none; vertical-align: middle; margin-left: 10px;"></span>
                    </p>
                    <p class="description"><?php _e('Recalculate all tournament statistics. This may take a few moments on sites with many tournaments.', 'poker-tournament-import'); ?></p>
                </form>

                <div id="statistics-refresh-result" style="margin-top: 15px;"></div>

                <hr style="margin: 30px 0;">

                <form method="post" id="database-repair-form">
                    <?php wp_nonce_field('poker_repair_database', 'poker_repair_nonce'); ?>
                    <p>
                        <button type="submit" name="repair_database" class="button button-secondary" onclick="return confirm('This will recreate missing database tables and recalculate statistics. Continue?')">
                            <span class="dashicons dashicons-tools" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Repair Database Tables', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                    <p class="description"><?php _e('Recreate missing database tables and fix statistics calculation issues. Use this if tables are missing or statistics are not calculating correctly.', 'poker-tournament-import'); ?></p>
                </form>

                <form method="post" id="data-repair-form">
                    <?php wp_nonce_field('poker_repair_player_data', 'poker_repair_player_nonce'); ?>
                    <p>
                        <button type="submit" name="repair_player_data" class="button button-secondary" onclick="return confirm('This will populate missing player data for all existing tournaments. This may take several minutes. Continue?')">
                            <span class="dashicons dashicons-database-import" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Repair Player Data', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                    <p class="description"><?php _e('Populate missing player data in the poker_tournament_players table for all existing tournaments. Use this if dashboard shows 0 players or empty top players section.', 'poker-tournament-import'); ?></p>
                </form>
            </div>

            <!-- Debug Information Section -->
            <div class="statistics-debug-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
                <h2><?php _e('Debug Information', 'poker-tournament-import'); ?></h2>
                <p><?php _e('Technical information to help diagnose statistics calculation issues.', 'poker-tournament-import'); ?></p>

                <?php if (class_exists('Poker_Statistics_Engine')): ?>
                    <?php
                    $stats_engine = Poker_Statistics_Engine::get_instance();
                    $debug_info = $stats_engine->debug_statistics();
                    ?>

                    <h3><?php _e('Database Tables', 'poker-tournament-import'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <td><?php _e('Statistics Table Exists:', 'poker-tournament-import'); ?></td>
                            <td><?php echo $debug_info['stats_table_exists'] ? '✅ Yes' : '❌ No'; ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Players Table Exists:', 'poker-tournament-import'); ?></td>
                            <td><?php echo $debug_info['players_table_exists'] ? '✅ Yes' : '❌ No'; ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Statistics Records:', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($debug_info['stats_count']); ?></td>
                        </tr>
                    </table>

                    <h3><?php _e('Raw Data Counts', 'poker-tournament-import'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <td><?php _e('Published Tournaments:', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($debug_info['raw_tournament_count']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Published Series:', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($debug_info['raw_series_count']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Players in Database:', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($debug_info['raw_players_in_db']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Total Prize Pool (from _prize_pool):', 'poker-tournament-import'); ?></td>
                            <td>$<?php echo esc_html(number_format($debug_info['raw_prize_pool'], 0)); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Best Prize Pool Field:', 'poker-tournament-import'); ?></td>
                            <td><code><?php echo esc_html($debug_info['best_prize_field']); ?></code></td>
                        </tr>
                    </table>

                    <h3><?php _e('Field Name Analysis', 'poker-tournament-import'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th colspan="3"><?php _e('Prize Pool Fields Found:', 'poker-tournament-import'); ?></th>
                        </tr>
                        <?php foreach ($debug_info['prize_pool_fields'] as $field => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($field); ?></code></td>
                            <td><?php echo esc_html($data['count']); ?> tournaments</td>
                            <td>$<?php echo esc_html(number_format($data['total'], 0)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th colspan="3"><?php _e('UUID Fields Found:', 'poker-tournament-import'); ?></th>
                        </tr>
                        <?php foreach ($debug_info['uuid_fields'] as $field => $count): ?>
                        <tr>
                            <td colspan="2"><code><?php echo esc_html($field); ?></code></td>
                            <td><?php echo esc_html($count); ?> tournaments</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>

                    <h3><?php _e('Current Statistics', 'poker-tournament-import'); ?></h3>
                    <table class="widefat">
                        <?php foreach ($debug_info['current_stats'] as $key => $value): ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</td>
                            <td><?php echo is_numeric($value) ? number_format($value, 0) : ($value ?: 'Never'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>

                    <?php if ($debug_info['raw_tournament_count'] > 0): ?>
                    <h3><?php _e('Sample Tournament Data', 'poker-tournament-import'); ?></h3>
                    <?php
                    $sample_tournaments = get_posts(array(
                        'post_type' => 'tournament',
                        'posts_per_page' => 3,
                        'post_status' => 'publish',
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ));

                    if ($sample_tournaments): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Tournament', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Date', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Prize Pool', 'poker-tournament-import'); ?></th>
                                <th><?php _e('UUID', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sample_tournaments as $tournament): ?>
                        <tr>
                            <td><a href="<?php echo get_edit_post_link($tournament->ID); ?>"><?php echo esc_html($tournament->post_title); ?></a></td>
                            <td><?php echo get_the_date('Y-m-d', $tournament->ID); ?></td>
                            <td><?php
                                $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                                echo $prize_pool ? '$' . number_format($prize_pool, 0) : 'Not set';
                            ?></td>
                            <td><?php
                                $uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                                echo $uuid ? esc_html(substr($uuid, 0, 8) . '...') : 'Not set';
                            ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <p><?php _e('Statistics engine not available.', 'poker-tournament-import'); ?></p>
                <?php endif; ?>
            </div>
            </form>
        </div>
        <?php
    }

    /**
     * Handle statistics refresh from settings page
     */
    public function handle_statistics_refresh() {
        if (isset($_POST['refresh_statistics']) && check_admin_referer('poker_refresh_statistics_admin', 'poker_refresh_stats_nonce')) {

            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
            }

            // Start output buffering to capture any messages
            ob_start();

            if (class_exists('Poker_Statistics_Engine')) {
                try {
                    $stats_engine = Poker_Statistics_Engine::get_instance();
                    $result = $stats_engine->calculate_all_statistics();

                    if ($result) {
                        // Update last refresh timestamp
                        update_option('poker_statistics_last_refresh', current_time('mysql'));

                        $dashboard_stats = $stats_engine->get_dashboard_statistics();

                        ob_clean();
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>' . __('Statistics refreshed successfully!', 'poker-tournament-import') . '</strong></p>';
                        echo '<ul>';
                        echo '<li>' . sprintf(__('Total Tournaments: %d', 'poker-tournament-import'), intval($dashboard_stats['total_tournaments'])) . '</li>';
                        echo '<li>' . sprintf(__('Total Players: %d', 'poker-tournament-import'), intval($dashboard_stats['total_players'])) . '</li>';
                        echo '<li>' . sprintf(__('Total Prize Pool: %s', 'poker-tournament-import'), esc_html('$' . number_format($dashboard_stats['total_prize_pool'], 0))) . '</li>';
                        echo '<li>' . sprintf(__('Active Series: %d', 'poker-tournament-import'), intval($dashboard_stats['active_series'])) . '</li>';
                        echo '</ul>';
                        echo '<p><em>' . __('Dashboard will now show updated data.', 'poker-tournament-import') . '</em></p>';
                        echo '</div>';
                    } else {
                        ob_clean();
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>' . __('Statistics refresh failed!', 'poker-tournament-import') . '</strong></p>';
                        echo '<p>' . __('Please check your error logs for details.', 'poker-tournament-import') . '</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    ob_clean();
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>' . __('Error refreshing statistics:', 'poker-tournament-import') . '</strong></p>';
                    echo '<p>' . esc_html($e->getMessage()) . '</p>';
                    echo '</div>';
                }
            } else {
                ob_clean();
                echo '<div class="notice notice-error">';
                echo '<p><strong>' . __('Statistics engine not available!', 'poker-tournament-import') . '</strong></p>';
                echo '<p>' . __('Please ensure the plugin is properly activated.', 'poker-tournament-import') . '</p>';
                echo '</div>';
            }

            // Clean output buffer and add to admin notices
            $message = ob_get_clean();
            add_action('admin_notices', function() use ($message) {
                echo $message;
            });
        }
    }

    /**
     * Handle database repair from settings page
     */
    public function handle_database_repair() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        // Start output buffering
        ob_start();

        try {
            global $wpdb;

            // Create missing statistics table
            $stats_table_name = $wpdb->prefix . 'poker_statistics';
            $charset_collate = $wpdb->get_charset_collate();

            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$stats_table_name}'");

            if (!$table_exists) {
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

                if ($result !== false) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>' . __('Statistics table created successfully!', 'poker-tournament-import') . '</strong></p>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>' . __('Failed to create statistics table:', 'poker-tournament-import') . '</strong></p>';
                    echo '<p>' . esc_html($wpdb->last_error) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . __('Statistics table already exists.', 'poker-tournament-import') . '</strong></p>';
                echo '</div>';
            }

            // Calculate initial statistics
            if (class_exists('Poker_Statistics_Engine')) {
                $stats_engine = Poker_Statistics_Engine::get_instance();
                $result = $stats_engine->calculate_all_statistics();

                if ($result) {
                    update_option('poker_statistics_last_refresh', current_time('mysql'));

                    $dashboard_stats = $stats_engine->get_dashboard_statistics();

                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>' . __('Statistics calculated successfully!', 'poker-tournament-import') . '</strong></p>';
                    echo '<ul>';
                    echo '<li>' . sprintf(__('Total Tournaments: %d', 'poker-tournament-import'), intval($dashboard_stats['total_tournaments'])) . '</li>';
                    echo '<li>' . sprintf(__('Total Players: %d', 'poker-tournament-import'), intval($dashboard_stats['total_players'])) . '</li>';
                    echo '<li>' . sprintf(__('Active Series: %d', 'poker-tournament-import'), intval($dashboard_stats['active_series'])) . '</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-warning">';
                    echo '<p><strong>' . __('Statistics calculation returned no results.', 'poker-tournament-import') . '</strong></p>';
                    echo '<p>' . __('This may indicate that tournaments don\'t have prize pool data set.', 'poker-tournament-import') . '</p>';
                    echo '</div>';
                }
            }

        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Database repair failed:', 'poker-tournament-import') . '</strong></p>';
            echo '<p>' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }

        // Clean output buffer and add to admin notices
        $message = ob_get_clean();
        add_action('admin_notices', function() use ($message) {
            echo $message;
        });
    }

    /**
     * Render migration tools page
     */
    public function render_migration_page() {
        // Initialize migration admin page
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/migration-tools.php';
        $migration_page = new Poker_Migration_Admin_Page();
        $migration_page->render_migration_page();
    }

    /**
     * Handle dashboard AJAX content loading
     */
    public function handle_dashboard_load_content() {
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
     * Handle dashboard detailed view AJAX
     */
    public function handle_dashboard_detailed_view() {
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
     * Handle dashboard report generation
     */
    public function handle_dashboard_generate_report() {
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
                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
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
     * Insert tournament player results into poker_tournament_players table
     */
    private function insert_tournament_players($tournament_id, $tournament_data, $player_ids) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $players_inserted = 0;

        if (empty($tournament_data['players'])) {
            Poker_Tournament_Import_Debug::log('No player data found in tournament');
            return 0;
        }

        $tournament_uuid = $tournament_data['metadata']['uuid'] ?? '';
        Poker_Tournament_Import_Debug::log('Starting player insertion for tournament', 'UUID: ' . $tournament_uuid . ', Players: ' . count($tournament_data['players']));

        // **ENHANCED DEBUGGING**: Check table structure and existing data
        $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        Poker_Tournament_Import_Debug::log('Table existence check', $table_check ? 'Table exists' : 'Table missing');

        if ($table_check) {
            $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            Poker_Tournament_Import_Debug::log('Existing records in table', $existing_count . ' records found');

            // Check table structure
            $table_structure = $wpdb->get_results("DESCRIBE {$table_name}");
            $structure_info = array();
            foreach ($table_structure as $column) {
                $structure_info[] = $column->Field . ' (' . $column->Type . ')';
            }
            Poker_Tournament_Import_Debug::log('Table structure', $structure_info);
        }

        foreach ($tournament_data['players'] as $player_uuid => $player_data) {
            try {
                // Calculate total buy-ins for this player
                $total_buyins = 0;
                if (!empty($player_data['buyins'])) {
                    foreach ($player_data['buyins'] as $buyin) {
                        $total_buyins += $buyin['amount'] ?? 0;
                    }
                }

                // Prepare player data to match ACTUAL table structure
                $player_record = array(
                    'tournament_id' => $tournament_uuid, // Use UUID as tournament_id
                    'player_id' => $player_uuid, // Use player UUID
                    'finish_position' => $player_data['finish_position'] ?? 1,
                    'winnings' => $player_data['winnings'] ?? 0,
                    'buyins' => $total_buyins,
                    'rebuys' => 0, // Default to 0 for now
                    'addons' => 0, // Default to 0 for now
                    'points' => $player_data['points'] ?? 0
                );

                // **ENHANCED DEBUGGING**: Log the exact data being inserted
                Poker_Tournament_Import_Debug::log('Attempting to insert player record', array(
                    'nickname' => $player_data['nickname'] ?? 'Unknown',
                    'tournament_uuid' => $tournament_uuid,
                    'player_uuid' => $player_uuid,
                    'finish_position' => $player_data['finish_position'] ?? 1,
                    'winnings' => $player_data['winnings'] ?? 0,
                    'buyins' => $total_buyins
                ));

                $result = $wpdb->insert($table_name, $player_record);
                $insert_id = $wpdb->insert_id;

                if ($result !== false && $insert_id > 0) {
                    $players_inserted++;
                    Poker_Tournament_Import_Debug::log_success('Player inserted successfully', array(
                        'nickname' => $player_data['nickname'] ?? 'Unknown',
                        'insert_id' => $insert_id,
                        'finish_position' => $player_data['finish_position'] ?? 1
                    ));
                } else {
                    Poker_Tournament_Import_Debug::log_error('Failed to insert player', array(
                        'nickname' => $player_data['nickname'] ?? 'Unknown',
                        'wpdb_result' => $result,
                        'wpdb_error' => $wpdb->last_error,
                        'insert_id' => $insert_id
                    ));
                }

            } catch (Exception $e) {
                Poker_Tournament_Import_Debug::log_error('Exception inserting player', array(
                    'nickname' => $player_data['nickname'] ?? 'Unknown',
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
        }

        // **ENHANCED DEBUGGING**: Final verification
        if ($table_check) {
            $final_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $tournament_players = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE tournament_id = %s",
                $tournament_uuid
            ));

            Poker_Tournament_Import_Debug::log('Final verification', array(
                'total_records_now' => $final_count,
                'tournament_records' => $tournament_players,
                'players_inserted_this_run' => $players_inserted,
                'expected_players' => count($tournament_data['players'])
            ));

            // Show sample of inserted data
            $sample_data = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE tournament_id = %s LIMIT 3",
                $tournament_uuid
            ));
            if ($sample_data) {
                foreach ($sample_data as $row) {
                    Poker_Tournament_Import_Debug::log('Sample inserted record', array(
                        'id' => $row->id,
                        'tournament_id' => $row->tournament_id,
                        'player_id' => $row->player_id,
                        'finish_position' => $row->finish_position,
                        'winnings' => $row->winnings,
                        'buyins' => $row->buyins,
                        'points' => $row->points
                    ));
                }
            }
        }

        Poker_Tournament_Import_Debug::log_success('Player insertion completed', $players_inserted . ' of ' . count($tournament_data['players']) . ' players inserted');
        return $players_inserted;
    }

    /**
     * Calculate and store tournament prize pool from player data
     */
    private function calculate_and_store_prize_pool($tournament_id, $tournament_data) {
        if (empty($tournament_data['players'])) {
            return 0;
        }

        // Calculate total prize pool from sum of all buy-ins
        $total_prize_pool = 0;
        foreach ($tournament_data['players'] as $player_data) {
            if (!empty($player_data['buyins'])) {
                foreach ($player_data['buyins'] as $buyin) {
                    $total_prize_pool += $buyin['amount'] ?? 0;
                }
            }
        }

        // Also calculate from winnings as verification
        $total_winnings = 0;
        foreach ($tournament_data['players'] as $player_data) {
            $total_winnings += $player_data['winnings'] ?? 0;
        }

        // Store the prize pool in tournament meta
        update_post_meta($tournament_id, '_prize_pool', $total_prize_pool);
        update_post_meta($tournament_id, 'prize_pool_calculated', $total_prize_pool);

        // Store player count
        update_post_meta($tournament_id, '_players_count', count($tournament_data['players']));

        // Store total winnings for verification
        update_post_meta($tournament_id, '_total_winnings', $total_winnings);

        Poker_Tournament_Import_Debug::log('Prize pool calculated and stored', array(
            'total_buyins' => $total_prize_pool,
            'total_winnings' => $total_winnings,
            'players_count' => count($tournament_data['players'])
        ));

        // **PHASE 2.1: Process tournament financial data**
        if (class_exists('Poker_Statistics_Engine')) {
            $stats_engine = Poker_Statistics_Engine::get_instance();

            // Prepare financial data for processing
            $financial_data = array(
                'buy_in' => $tournament_data['buy_in'] ?? 0,
                'players' => count($tournament_data['players']),
                'rebuys' => $this->count_rebuys($tournament_data),
                'addons' => $this->count_addons($tournament_data),
                'prize_pool' => $total_prize_pool,
                'currency' => $tournament_data['currency'] ?? 'USD'
            );

            $financial_result = $stats_engine->process_tournament_financial_data($tournament_id, $financial_data);

            if ($financial_result) {
                Poker_Tournament_Import_Debug::log('Financial data processed successfully', $financial_data);
            } else {
                Poker_Tournament_Import_Debug::log_warning('Financial data processing failed', $financial_data);
            }
        }

        return $total_prize_pool;
    }

    /**
     * **PHASE 2.1 Helper Methods for Financial Data Processing**
     */

    /**
     * Count total rebuys in tournament data
     */
    private function count_rebuys($tournament_data) {
        $total_rebuys = 0;

        if (!empty($tournament_data['players'])) {
            foreach ($tournament_data['players'] as $player_data) {
                $rebuys = intval($player_data['rebuys'] ?? 0);
                $total_rebuys += $rebuys;
            }
        }

        return $total_rebuys;
    }

    /**
     * Count total addons in tournament data
     */
    private function count_addons($tournament_data) {
        $total_addons = 0;

        if (!empty($tournament_data['players'])) {
            foreach ($tournament_data['players'] as $player_data) {
                $addons = intval($player_data['addons'] ?? 0);
                $total_addons += $addons;
            }
        }

        return $total_addons;
    }

    /**
     * Handle player data repair for existing tournaments
     */
    public function handle_player_data_repair() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        // Start output buffering
        ob_start();

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'poker_tournament_players';

            // Get all published tournaments
            $tournaments = get_posts(array(
                'post_type' => 'tournament',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'ASC'
            ));

            $total_tournaments = count($tournaments);
            $repaired_tournaments = 0;
            $total_players_inserted = 0;

            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Starting Player Data Repair Process...', 'poker-tournament-import') . '</strong></p>';
            echo '<p>' . sprintf(__('Found %d tournaments to process.', 'poker-tournament-import'), $total_tournaments) . '</p>';
            echo '</div>';

            foreach ($tournaments as $tournament) {
                $tournament_id = $tournament->ID;
                $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
                $tournament_uuid_alt = get_post_meta($tournament_id, '_tournament_uuid', true);

                if (!$tournament_uuid && !$tournament_uuid_alt) {
                    continue; // Skip tournaments without UUID
                }

                $uuid_to_use = $tournament_uuid ?: $tournament_uuid_alt;

                // Check if this tournament already has players in the data mart
                $existing_players = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE tournament_id = %s",
                    $uuid_to_use
                ));

                if ($existing_players > 0) {
                    continue; // Skip tournaments that already have player data
                }

                // Get the tournament data from post meta
                $tournament_data = get_post_meta($tournament_id, 'tournament_data', true);

                if (empty($tournament_data) || empty($tournament_data['players'])) {
                    // Try to reconstruct from other sources if tournament_data is missing
                    $tournament_data = $this->reconstruct_tournament_data($tournament_id);
                }

                if (!empty($tournament_data['players'])) {
                    // Insert player data for this tournament
                    $players_inserted = $this->insert_tournament_players($tournament_id, $tournament_data, array());

                    if ($players_inserted > 0) {
                        $repaired_tournaments++;
                        $total_players_inserted += $players_inserted;

                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>' . sprintf(
                            __('✅ Repaired tournament: %s - %d players added', 'poker-tournament-import'),
                            esc_html($tournament->post_title),
                            $players_inserted
                        ) . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="notice notice-warning">';
                        echo '<p>' . sprintf(
                            __('⚠️ Tournament: %s - No players inserted', 'poker-tournament-import'),
                            esc_html($tournament->post_title)
                        ) . '</p>';
                        echo '</div>';
                    }
                }
            }

            // Recalculate statistics after repair
            if (class_exists('Poker_Statistics_Engine')) {
                $stats_engine = Poker_Statistics_Engine::get_instance();
                $result = $stats_engine->calculate_all_statistics();

                if ($result) {
                    update_option('poker_statistics_last_refresh', current_time('mysql'));

                    $dashboard_stats = $stats_engine->get_dashboard_statistics();

                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>' . __('Player Data Repair Completed!', 'poker-tournament-import') . '</strong></p>';
                    echo '<ul>';
                    echo '<li>' . sprintf(__('Tournaments Repaired: %d of %d', 'poker-tournament-import'), $repaired_tournaments, $total_tournaments) . '</li>';
                    echo '<li>' . sprintf(__('Total Players Added: %d', 'poker-tournament-import'), $total_players_inserted) . '</li>';
                    echo '<li>' . sprintf(__('Current Total Players: %d', 'poker-tournament-import'), intval($dashboard_stats['total_players'])) . '</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-warning">';
                    echo '<p><strong>' . __('Data Repaired but Statistics Update Failed', 'poker-tournament-import') . '</strong></p>';
                    echo '<p>' . __('Please try refreshing statistics manually.', 'poker-tournament-import') . '</p>';
                    echo '</div>';
                }
            }

        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Player Data Repair Failed:', 'poker-tournament-import') . '</strong></p>';
            echo '<p>' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }

        // Clean output buffer and add to admin notices
        $message = ob_get_clean();
        add_action('admin_notices', function() use ($message) {
            echo $message;
        });
    }

    /**
     * Reconstruct tournament data from various sources if original data is missing
     */
    private function reconstruct_tournament_data($tournament_id) {
        $tournament = get_post($tournament_id);
        $reconstructed_data = array(
            'metadata' => array(
                'uuid' => get_post_meta($tournament_id, 'tournament_uuid', true) ?: get_post_meta($tournament_id, '_tournament_uuid', true),
                'title' => $tournament->post_title,
                'start_time' => get_post_meta($tournament_id, '_tournament_date', true) ?: $tournament->post_date
            ),
            'players' => array()
        );

        // Try to get player data from tournament_players post meta if it exists
        $players_data = get_post_meta($tournament_id, 'tournament_players', true);
        if (!empty($players_data)) {
            $reconstructed_data['players'] = $players_data;
        } else {
            // Try to get player data from individual player results if stored separately
            $player_results = get_post_meta($tournament_id, 'player_results', true);
            if (!empty($player_results)) {
                $reconstructed_data['players'] = $player_results;
            }
        }

        return $reconstructed_data;
    }

    /**
     **CRITICAL FIX**: Extract buy-in amount from tournament data
     */
    private function extract_buy_in_from_tournament_data($tournament_data) {
        // Method 1: Check if buy-in is in financial data
        if (!empty($tournament_data['financial']['buy_in'])) {
            return floatval($tournament_data['financial']['buy_in']);
        }

        // Method 2: Extract from first player's buy-in data
        if (!empty($tournament_data['players'])) {
            $first_player = reset($tournament_data['players']);
            if (!empty($first_player['buyins']) && is_array($first_player['buyins'])) {
                $first_buyin = reset($first_player['buyins']);
                if (!empty($first_buyin['amount'])) {
                    return floatval($first_buyin['amount']);
                }
            }
        }

        // Method 3: Extract from metadata
        if (!empty($tournament_data['metadata']['buy_in'])) {
            return floatval($tournament_data['metadata']['buy_in']);
        }

        // Method 4: Calculate from tournament data structure
        if (!empty($tournament_data['entries'])) {
            $total_buyins = 0;
            foreach ($tournament_data['entries'] as $entry) {
                if (!empty($entry['amount'])) {
                    $total_buyins = floatval($entry['amount']);
                    break; // Use first entry amount as standard buy-in
                }
            }
            if ($total_buyins > 0) {
                return $total_buyins;
            }
        }

        // Fallback to default if nothing found
        return floatval(get_option('poker_import_default_buyin', 200));
    }

    /**
     **CRITICAL FIX**: Extract currency from tournament data
     */
    private function extract_currency_from_tournament_data($tournament_data) {
        // Method 1: Check if currency is in financial data
        if (!empty($tournament_data['financial']['currency'])) {
            return $tournament_data['financial']['currency'];
        }

        // Method 2: Extract from metadata
        if (!empty($tournament_data['metadata']['currency'])) {
            return $tournament_data['metadata']['currency'];
        }

        // Method 3: Look for currency symbols in player data
        if (!empty($tournament_data['players'])) {
            $first_player = reset($tournament_data['players']);
            if (!empty($first_player['winnings']) && is_string($first_player['winnings'])) {
                if (strpos($first_player['winnings'], '$') !== false) {
                    return '$';
                } elseif (strpos($first_player['winnings'], '€') !== false) {
                    return '€';
                } elseif (strpos($first_player['winnings'], '£') !== false) {
                    return '£';
                }
            }
        }

        // Fallback to default currency
        return '$';
    }

    /**
     **CRITICAL FIX**: Extract game type from tournament data
     */
    private function extract_game_type_from_tournament_data($tournament_data) {
        // Method 1: Check metadata
        if (!empty($tournament_data['metadata']['game_type'])) {
            return $tournament_data['metadata']['game_type'];
        }

        if (!empty($tournament_data['metadata']['variant'])) {
            return $tournament_data['metadata']['variant'];
        }

        // Method 2: Check tournament description
        if (!empty($tournament_data['metadata']['description'])) {
            $description = strtolower($tournament_data['metadata']['description']);
            if (strpos($description, 'hold\'em') !== false || strpos($description, 'holdem') !== false) {
                if (strpos($description, 'omaha') !== false) {
                    return 'Omaha Hold\'em';
                } else {
                    return 'Texas Hold\'em';
                }
            } elseif (strpos($description, 'omaha') !== false) {
                return 'Omaha';
            } elseif (strpos($description, 'stud') !== false) {
                return 'Stud';
            } elseif (strpos($description, 'draw') !== false) {
                return 'Draw';
            }
        }

        // Method 3: Default to Texas Hold'em (most common)
        return 'Texas Hold\'em';
    }

    /**
     **CRITICAL FIX**: Extract tournament structure from tournament data
     */
    private function extract_structure_from_tournament_data($tournament_data) {
        $structure_parts = array();

        // Extract betting limits
        if (!empty($tournament_data['metadata']['betting_structure'])) {
            $structure_parts[] = $tournament_data['metadata']['betting_structure'];
        } elseif (!empty($tournament_data['metadata']['limits'])) {
            $structure_parts[] = $tournament_data['metadata']['limits'];
        }

        // Extract format
        if (!empty($tournament_data['metadata']['format'])) {
            $structure_parts[] = $tournament_data['metadata']['format'];
        }

        // Build structure description
        if (!empty($structure_parts)) {
            return implode(' - ', $structure_parts);
        }

        // Default structures based on common patterns
        if (!empty($tournament_data['metadata']['description'])) {
            $description = strtolower($tournament_data['metadata']['description']);
            if (strpos($description, 'no limit') !== false || strpos($description, 'nl') !== false) {
                return 'No Limit Hold\'em';
            } elseif (strpos($description, 'pot limit') !== false || strpos($description, 'pl') !== false) {
                return 'Pot Limit Hold\'em';
            } elseif (strpos($description, 'fixed limit') !== false || strpos($description, 'fl') !== false) {
                return 'Fixed Limit Hold\'em';
            }
        }

        // Fallback
        return 'No Limit Hold\'em';
    }

    /**
     **CRITICAL FIX**: Generate meaningful tournament content for display
     */
    private function generate_tournament_content($tournament_data) {
        $content = '';
        $metadata = $tournament_data['metadata'];

        // Calculate tournament statistics
        $players_count = count($tournament_data['players']);
        $buy_in = $this->extract_buy_in_from_tournament_data($tournament_data);
        $currency = $this->extract_currency_from_tournament_data($tournament_data);
        $game_type = $this->extract_game_type_from_tournament_data($tournament_data);
        $structure = $this->extract_structure_from_tournament_data($tournament_data);

        // Calculate prize pool
        $prize_pool = 0;
        foreach ($tournament_data['players'] as $player) {
            if (!empty($player['winnings'])) {
                $prize_pool += floatval($player['winnings']);
            }
        }

        // Start building content
        $content .= '<!-- Auto-generated tournament content -->';
        $content .= '<div class="tournament-overview">';

        // Tournament header information
        $content .= '<h3>' . __('Tournament Overview', 'poker-tournament-import') . '</h3>';
        $content .= '<p><strong>' . __('Game:', 'poker-tournament-import') . '</strong> ' . esc_html($game_type) . '</p>';
        $content .= '<p><strong>' . __('Structure:', 'poker-tournament-import') . '</strong> ' . esc_html($structure) . '</p>';
        $content .= '<p><strong>' . __('Buy-in:', 'poker-tournament-import') . '</strong> ' . esc_html($currency . number_format($buy_in, 0)) . '</p>';
        $content .= '<p><strong>' . __('Players:', 'poker-tournament-import') . '</strong> ' . esc_html($players_count) . '</p>';
        $content .= '<p><strong>' . __('Prize Pool:', 'poker-tournament-import') . '</strong> ' . esc_html($currency . number_format($prize_pool, 0)) . '</p>';

        if (!empty($metadata['start_time'])) {
            $content .= '<p><strong>' . __('Date:', 'poker-tournament-import') . '</strong> ' . esc_html(date_i18n(get_option('date_format'), strtotime($metadata['start_time']))) . '</p>';
        }

        $content .= '</div>';

        // Add series/season information if available
        if (!empty($metadata['league_name'])) {
            $content .= '<div class="tournament-series-info">';
            $content .= '<h4>' . __('Series Information', 'poker-tournament-import') . '</h4>';
            $content .= '<p><strong>' . __('Series:', 'poker-tournament-import') . '</strong> ' . esc_html($metadata['league_name']) . '</p>';
            if (!empty($metadata['season_name'])) {
                $content .= '<p><strong>' . __('Season:', 'poker-tournament-import') . '</strong> ' . esc_html($metadata['season_name']) . '</p>';
            }
            $content .= '</div>';
        }

        // Add points calculation information
        $content .= '<div class="tournament-points-info">';
        $content .= '<h4>' . __('Points System', 'poker-tournament-import') . '</h4>';
        $content .= '<p>' . __('This tournament uses the Tournament Director points calculation system. Points are awarded based on finish position, number of players, and knockout bonuses.', 'poker-tournament-import') . '</p>';
        $content .= '<p><em>' . __('Points are calculated using: T33, T80 thresholds, average buy-in, and knockout bonuses as per the Tournament Director formula.', 'poker-tournament-import') . '</em></p>';
        $content .= '</div>';

        // Add results preview
        $content .= '<div class="tournament-results-preview">';
        $content .= '<h4>' . __('Results Summary', 'poker-tournament-import') . '</h4>';

        if (!empty($tournament_data['players'])) {
            // Show top 3 finishers
            $top_players = array_slice($tournament_data['players'], 0, 3);
            $content .= '<ol>';
            foreach ($top_players as $index => $player) {
                $position = intval($index) + 1;
                $player_name = $player['nickname'] ?? __('Unknown Player', 'poker-tournament-import');
                $winnings = !empty($player['winnings']) ? $currency . number_format(floatval($player['winnings']), 0) : '$0';
                $points = !empty($player['points']) ? number_format(floatval($player['points']), 1) : '0';

                $content .= '<li><strong>' . esc_html($player_name) . '</strong> - ' . __('Winnings:', 'poker-tournament-import') . ' ' . esc_html($winnings) . ', ' . __('Points:', 'poker-tournament-import') . ' ' . esc_html($points) . '</li>';
            }
            $content .= '</ol>';

            if ($players_count > 3) {
                $content .= '<p><em>' . sprintf(__('... and %d more players. See complete results below.', 'poker-tournament-import'), $players_count - 3) . '</em></p>';
            }
        }

        $content .= '</div>';

        // Add shortcode for full results
        $content .= '<div class="full-tournament-results">';
        $content .= '<h4>' . __('Complete Tournament Results', 'poker-tournament-import') . '</h4>';
        $content .= '[tournament_results show_players="true" show_structure="true"]';
        $content .= '</div>';

        return $content;
    }

    /**
     **CRITICAL FIX**: Calculate points summary for tournament display
     */
    private function calculate_points_summary($tournament_data) {
        if (empty($tournament_data['players'])) {
            return array();
        }

        $players = $tournament_data['players'];
        $total_players = count($players);
        $points_summary = array(
            'total_players' => $total_players,
            'total_points_awarded' => 0,
            'max_points' => 0,
            'min_points' => PHP_FLOAT_MAX,
            'avg_points' => 0,
            'players_with_points' => 0,
            'points_distribution' => array(),
            'top_point_scorer' => null,
            'formula_used' => 'Tournament Director Formula'
        );

        $total_points = 0;
        foreach ($players as $player) {
            $points = isset($player['points']) ? floatval($player['points']) : 0;

            if ($points > 0) {
                $points_summary['players_with_points']++;
                $total_points += $points;

                if ($points > $points_summary['max_points']) {
                    $points_summary['max_points'] = $points;
                    $points_summary['top_point_scorer'] = array(
                        'name' => $player['nickname'] ?? 'Unknown',
                        'finish_position' => intval($player['finish_position'] ?? 0),
                        'points' => $points
                    );
                }

                if ($points < $points_summary['min_points']) {
                    $points_summary['min_points'] = $points;
                }
            }

            // Categorize points for distribution analysis
            if ($points >= 100) {
                $category = '100+';
            } elseif ($points >= 50) {
                $category = '50-99';
            } elseif ($points >= 25) {
                $category = '25-49';
            } elseif ($points >= 10) {
                $category = '10-24';
            } elseif ($points > 0) {
                $category = '1-9';
            } else {
                $category = '0';
            }

            if (!isset($points_summary['points_distribution'][$category])) {
                $points_summary['points_distribution'][$category] = 0;
            }
            $points_summary['points_distribution'][$category]++;
        }

        $points_summary['total_points_awarded'] = $total_points;
        $points_summary['avg_points'] = $total_players > 0 ? round($total_points / $total_players, 2) : 0;

        // Calculate T33 and T80 thresholds for reference
        $points_summary['t33_threshold'] = max(1, round($total_players * 0.33));
        $points_summary['t80_threshold'] = max(1, round($total_players * 0.80));

        // Sort distribution categories
        ksort($points_summary['points_distribution']);

        return $points_summary;
    }

    /**
     **CRITICAL FIX**: Calculate enhanced tournament statistics
     */
    private function calculate_enhanced_tournament_stats($tournament_data) {
        if (empty($tournament_data['players'])) {
            return array();
        }

        $players = $tournament_data['players'];
        $total_players = count($players);
        $currency = $this->extract_currency_from_tournament_data($tournament_data);
        $buy_in = $this->extract_buy_in_from_tournament_data($tournament_data);

        $stats = array(
            'players_count' => $total_players,
            'total_buyins' => $total_players,
            'total_rebuys' => 0,
            'total_addons' => 0,
            'gross_prize_pool' => 0,
            'net_prize_pool' => 0,
            'total_winnings' => 0,
            'total_fees' => 0,
            'average_buyin' => $buy_in,
            'paid_positions' => 0,
            'cash_rate' => 0,
            'first_place_prize' => 0,
            'smallest_cash' => PHP_FLOAT_MAX,
            'largest_cash' => 0,
            'average_cash' => 0,
            'currency' => $currency,
            'tournament_duration_hours' => 0,
            'players_per_hour' => 0,
            'profitable_players' => 0
        );

        $total_winnings = 0;
        $total_buyins_amount = 0;
        $paid_positions = 0;

        foreach ($players as $player) {
            // Calculate buy-ins, rebuys, addons
            $player_buyins = 0;
            if (!empty($player['buyins']) && is_array($player['buyins'])) {
                foreach ($player['buyins'] as $buyin) {
                    $player_buyins += floatval($buyin['amount'] ?? 0);
                }
            }
            $total_buyins_amount += $player_buyins;

            // Track rebuys and addons
            $stats['total_rebuys'] += intval($player['rebuys'] ?? 0);
            $stats['total_addons'] += intval($player['addons'] ?? 0);

            // Calculate winnings
            $winnings = floatval($player['winnings'] ?? 0);
            $total_winnings += $winnings;

            if ($winnings > 0) {
                $paid_positions++;
                $stats['profitable_players']++;

                if ($winnings > $stats['largest_cash']) {
                    $stats['largest_cash'] = $winnings;
                    if (intval($player['finish_position'] ?? 0) == 1) {
                        $stats['first_place_prize'] = $winnings;
                    }
                }

                if ($winnings < $stats['smallest_cash']) {
                    $stats['smallest_cash'] = $winnings;
                }
            }
        }

        $stats['total_winnings'] = $total_winnings;
        $stats['gross_prize_pool'] = $total_buyins_amount;
        $stats['average_buyin'] = $total_players > 0 ? round($total_buyins_amount / $total_players, 2) : $buy_in;
        $stats['paid_positions'] = $paid_positions;
        $stats['cash_rate'] = $total_players > 0 ? round(($paid_positions / $total_players) * 100, 2) : 0;
        $stats['average_cash'] = $paid_positions > 0 ? round($total_winnings / $paid_positions, 2) : 0;

        if ($stats['smallest_cash'] === PHP_FLOAT_MAX) {
            $stats['smallest_cash'] = 0;
        }

        // Calculate tournament duration if start/end times available
        if (!empty($tournament_data['metadata']['start_time']) && !empty($tournament_data['metadata']['end_time'])) {
            $start_time = strtotime($tournament_data['metadata']['start_time']);
            $end_time = strtotime($tournament_data['metadata']['end_time']);
            if ($start_time && $end_time && $end_time > $start_time) {
                $stats['tournament_duration_hours'] = round(($end_time - $start_time) / 3600, 2);
                $stats['players_per_hour'] = $stats['tournament_duration_hours'] > 0 ? round($total_players / $stats['tournament_duration_hours'], 2) : 0;
            }
        }

        return $stats;
    }
}