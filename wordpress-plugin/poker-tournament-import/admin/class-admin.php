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

        // Prepare tournament content
        $content = '<!-- Tournament content will be displayed via shortcode -->';

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
            }

            // Store full tournament data
            update_post_meta($tournament_id, 'tournament_data', $tournament_data);
            Poker_Tournament_Import_Debug::log('Stored full tournament data');

            // Store player relationships
            if (!empty($player_ids)) {
                update_post_meta($tournament_id, 'tournament_players', $player_ids);
                Poker_Tournament_Import_Debug::log('Stored player relationships', count($player_ids) . ' players');
            }

            // Set series and season relationships
            if ($series_id > 0) {
                Poker_Tournament_Import_Debug::log('Setting series terms', $series_id);
                wp_set_post_terms($tournament_id, array($series_id), 'tournament_series_taxonomy');
            }
            if ($season_id > 0) {
                Poker_Tournament_Import_Debug::log('Setting season terms', $season_id);
                wp_set_post_terms($tournament_id, array($season_id), 'tournament_season_taxonomy');
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
            echo '<li>' . sprintf(__('%d players imported', 'poker-tournament-import'), $created_posts['players']) . '</li>';
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
        </div>
        <?php
    }
}