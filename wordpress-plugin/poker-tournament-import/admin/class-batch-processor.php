<?php
/**
 * Batch Processor
 *
 * Handles processing of individual files within bulk import batches.
 * Integrates with existing parser, handles duplicates, updates batch progress.
 *
 * @package Poker_Tournament_Import
 * @subpackage Admin
 * @since 2.9.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Poker_Tournament_Batch_Processor
 *
 * Processes individual .tdt files within bulk import batches.
 */
class Poker_Tournament_Batch_Processor {

    /**
     * Process a single file from a batch
     *
     * @param int    $file_id      The batch file record ID
     * @param string $batch_uuid   The batch UUID
     * @param array  $options      Processing options (skip_duplicates, update_existing, etc.)
     * @return array Response with status, tournament_id, and details
     */
    public function process_single_file($file_id, $batch_uuid, $options = array()) {
        global $wpdb;

        $start_time = microtime(true);

        // Default options
        $options = wp_parse_args($options, array(
            'skip_duplicates' => true,
            'update_existing' => false,
            'import_as_new' => false,
        ));

        // Get batch and file records
        $batches_table = $wpdb->prefix . 'poker_import_batches';
        $files_table = $wpdb->prefix . 'poker_import_batch_files';

        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $batches_table WHERE batch_uuid = %s",
            $batch_uuid
        ));

        if (!$batch) {
            return array(
                'success' => false,
                'error' => __('Batch not found.', 'poker-tournament-import'),
            );
        }

        $file_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND batch_id = %d",
            $file_id,
            $batch->id
        ));

        if (!$file_record) {
            return array(
                'success' => false,
                'error' => __('File record not found.', 'poker-tournament-import'),
            );
        }

        // Update file status to processing
        $wpdb->update(
            $files_table,
            array('status' => 'processing'),
            array('id' => $file_id),
            array('%s'),
            array('%d')
        );

        // Update batch status to processing
        if ($batch->status === 'pending') {
            $wpdb->update(
                $batches_table,
                array(
                    'status' => 'processing',
                    'started_at' => current_time('mysql')
                ),
                array('id' => $batch->id),
                array('%s', '%s'),
                array('%d')
            );
        }

        try {
            // Check if file exists
            if (!file_exists($file_record->filepath)) {
                throw new Exception(__('File not found in temporary storage.', 'poker-tournament-import'));
            }

            // Check for duplicate by file hash
            if ($options['skip_duplicates'] && !empty($file_record->file_hash)) {
                $duplicate_by_hash = $this->detect_duplicate_by_hash($file_record->file_hash, $file_id);
                if ($duplicate_by_hash) {
                    $this->mark_file_skipped($file_id, __('Duplicate file detected (identical file hash).', 'poker-tournament-import'), $duplicate_by_hash);
                    $this->cleanup_temp_file($file_record->filepath);
                    $this->update_batch_progress($batch->id);

                    $processing_time = (microtime(true) - $start_time) * 1000;
                    return array(
                        'success' => true,
                        'skipped' => true,
                        'duplicate_id' => $duplicate_by_hash,
                        'message' => __('File skipped (duplicate detected).', 'poker-tournament-import'),
                        'processing_time_ms' => round($processing_time),
                    );
                }
            }

            // Parse the .tdt file
            $parse_result = $this->parse_tdt_file($file_record->filepath);

            if (!$parse_result['success']) {
                throw new Exception($parse_result['error']);
            }

            $tournament_data = $parse_result['data'];

            // Check for duplicate by tournament signature
            if ($options['skip_duplicates'] && !$options['import_as_new']) {
                $duplicate_by_signature = $this->detect_duplicate_by_signature($tournament_data);
                if ($duplicate_by_signature) {
                    if ($options['update_existing']) {
                        // Update existing tournament
                        $result = $this->update_existing_tournament($duplicate_by_signature, $tournament_data);
                        $tournament_id = $duplicate_by_signature;
                        $action = 'updated';
                    } else {
                        // Skip duplicate
                        $this->mark_file_skipped($file_id, __('Duplicate tournament detected (same name, date, venue).', 'poker-tournament-import'), $duplicate_by_signature);
                        $this->cleanup_temp_file($file_record->filepath);
                        $this->update_batch_progress($batch->id);

                        $processing_time = (microtime(true) - $start_time) * 1000;
                        return array(
                            'success' => true,
                            'skipped' => true,
                            'duplicate_id' => $duplicate_by_signature,
                            'message' => __('File skipped (duplicate tournament).', 'poker-tournament-import'),
                            'processing_time_ms' => round($processing_time),
                        );
                    }
                } else {
                    // Create new tournament
                    $result = $this->create_tournament($tournament_data);
                    $tournament_id = $result['tournament_id'];
                    $action = 'created';
                }
            } else {
                // Import as new regardless of duplicates
                $result = $this->create_tournament($tournament_data);
                $tournament_id = $result['tournament_id'];
                $action = 'created';
            }

            // Calculate processing time
            $processing_time = (microtime(true) - $start_time) * 1000;

            // Prepare parse details
            $parse_details = json_encode(array(
                'tournament_name' => $tournament_data['name'] ?? '',
                'tournament_date' => $tournament_data['date'] ?? '',
                'player_count' => count($tournament_data['players'] ?? array()),
                'buy_in' => $tournament_data['buy_in'] ?? 0,
                'total_prize_pool' => $tournament_data['prize_pool'] ?? 0,
                'action' => $action,
            ));

            // Update file record with success
            $wpdb->update(
                $files_table,
                array(
                    'status' => 'completed',
                    'tournament_id' => $tournament_id,
                    'parse_details' => $parse_details,
                    'processing_time_ms' => round($processing_time),
                    'processed_at' => current_time('mysql'),
                ),
                array('id' => $file_id),
                array('%s', '%d', '%s', '%d', '%s'),
                array('%d')
            );

            // Update batch progress
            $this->update_batch_progress($batch->id);

            // Cleanup temp file
            $this->cleanup_temp_file($file_record->filepath);

            return array(
                'success' => true,
                'tournament_id' => $tournament_id,
                'action' => $action,
                'parse_details' => json_decode($parse_details, true),
                'processing_time_ms' => round($processing_time),
                'message' => sprintf(
                    /* translators: %s: tournament name */
                    __('Tournament "%s" imported successfully.', 'poker-tournament-import'),
                    $tournament_data['name'] ?? $file_record->filename
                ),
            );

        } catch (Exception $e) {
            // Handle error
            $processing_time = (microtime(true) - $start_time) * 1000;

            $wpdb->update(
                $files_table,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processing_time_ms' => round($processing_time),
                    'processed_at' => current_time('mysql'),
                ),
                array('id' => $file_id),
                array('%s', '%s', '%d', '%s'),
                array('%d')
            );

            // Update batch progress
            $this->update_batch_progress($batch->id);

            // Don't cleanup temp file on error (allow retry)

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => round($processing_time),
            );
        }
    }

    /**
     * Parse .tdt file using existing parser
     *
     * @param string $filepath Path to .tdt file
     * @return array Result with success flag and data/error
     */
    private function parse_tdt_file($filepath) {
        try {
            // Require parser if not already loaded
            if (!class_exists('Poker_Tournament_Parser')) {
                require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-parser.php';
            }

            $parser = new Poker_Tournament_Parser();
            $tournament_data = $parser->parse_file($filepath);

            if (!$tournament_data || empty($tournament_data)) {
                throw new Exception(__('Failed to parse .tdt file - no data returned.', 'poker-tournament-import'));
            }

            return array(
                'success' => true,
                'data' => $tournament_data,
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Detect duplicate by file hash
     *
     * @param string $file_hash SHA256 hash of file
     * @param int    $exclude_file_id Exclude this file_id from search
     * @return int|false Tournament ID if duplicate found, false otherwise
     */
    private function detect_duplicate_by_hash($file_hash, $exclude_file_id = 0) {
        global $wpdb;
        $files_table = $wpdb->prefix . 'poker_import_batch_files';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT tournament_id FROM $files_table
             WHERE file_hash = %s
             AND tournament_id IS NOT NULL
             AND id != %d
             AND status = 'completed'
             ORDER BY processed_at DESC
             LIMIT 1",
            $file_hash,
            $exclude_file_id
        ));

        return $existing ? (int) $existing->tournament_id : false;
    }

    /**
     * Detect duplicate by tournament signature (name + date + venue)
     *
     * @param array $tournament_data Parsed tournament data
     * @return int|false Tournament ID if duplicate found, false otherwise
     */
    private function detect_duplicate_by_signature($tournament_data) {
        // Build signature from key fields
        $name = sanitize_text_field($tournament_data['name'] ?? '');
        $date = sanitize_text_field($tournament_data['date'] ?? '');
        $venue = sanitize_text_field($tournament_data['venue'] ?? '');

        if (empty($name) || empty($date)) {
            return false; // Can't detect without name and date
        }

        // Search for existing tournament with same meta values
        $args = array(
            'post_type' => 'tournament',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'tournament_name',
                    'value' => $name,
                    'compare' => '=',
                ),
                array(
                    'key' => 'tournament_date',
                    'value' => $date,
                    'compare' => '=',
                ),
            ),
        );

        // Add venue to query if available
        if (!empty($venue)) {
            $args['meta_query'][] = array(
                'key' => 'tournament_venue',
                'value' => $venue,
                'compare' => '=',
            );
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $post = $query->posts[0];
            wp_reset_postdata();
            return $post->ID;
        }

        wp_reset_postdata();
        return false;
    }

    /**
     * Create new tournament post from parsed data - MATCHES regular import exactly
     *
     * @param array $tournament_data Parsed tournament data
     * @return array Result with tournament_id
     */
    private function create_tournament($tournament_data) {
        // Get metadata
        $metadata = $tournament_data['metadata'];
        $status = 'publish'; // Always publish in bulk import

        // Sanitize tournament data
        $tournament_name = sanitize_text_field($metadata['title'] ?? 'Untitled Tournament');

        // Create or find series post
        $series_id = 0;
        if (!empty($metadata['league_name'])) {
            $series_id = $this->create_or_find_series(
                $metadata['league_name'],
                $metadata['league_uuid'] ?? '',
                $status
            );
        }

        // Create or find season post
        $season_id = 0;
        if (!empty($metadata['season_name'])) {
            $season_id = $this->create_or_find_season(
                $metadata['season_name'],
                $metadata['season_uuid'] ?? '',
                $status
            );
        }

        // Create player posts
        $player_ids = array();
        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            foreach ($tournament_data['players'] as $player_uuid => $player_data) {
                $player_id = $this->create_or_find_player(
                    $player_data['nickname'],
                    $player_uuid,
                    $status
                );
                if ($player_id) {
                    $player_ids[$player_uuid] = $player_id;
                }
            }
        }

        // Prepare post data
        $post_data = array(
            'post_title' => $tournament_name,
            'post_type' => 'tournament',
            'post_status' => $status,
            'post_content' => wp_kses_post($tournament_data['description'] ?? ''),
        );

        // Set tournament date if available
        if (!empty($metadata['start_time'])) {
            $post_data['post_date'] = date('Y-m-d H:i:s', strtotime($metadata['start_time']));
            $post_data['post_date_gmt'] = get_gmt_from_date($post_data['post_date']);
        }

        // Create tournament post
        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Save tournament meta data (now with ALL metadata like regular import)
        $this->save_tournament_meta($post_id, $tournament_data);

        // Store series and season relationships
        if ($series_id > 0) {
            update_post_meta($post_id, '_series_id', $series_id);
        }
        if ($season_id > 0) {
            update_post_meta($post_id, '_season_id', $season_id);
        }

        // Store player relationships
        if (!empty($player_ids)) {
            update_post_meta($post_id, 'tournament_players', $player_ids);
        }

        // Save players data to database table
        $players_inserted = 0;
        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            $players_inserted = $this->save_tournament_players($post_id, $tournament_data);
        }

        // **CRITICAL**: Calculate and store prize pool (was missing!)
        $this->calculate_and_store_prize_pool($post_id, $tournament_data);

        // **CRITICAL**: Process player ROI data (was missing!)
        if (class_exists('Poker_Statistics_Engine')) {
            $stats_engine = Poker_Statistics_Engine::get_instance();
            $tournament_uuid = $metadata['uuid'] ?? '';
            if ($tournament_uuid) {
                $stats_engine->process_player_roi_data($tournament_uuid, $tournament_data);
            }
        }

        // Trigger statistics refresh (async)
        do_action('poker_tournament_imported', $post_id);

        return array(
            'tournament_id' => $post_id,
            'series_id' => $series_id,
            'season_id' => $season_id,
            'players_created' => count($player_ids),
            'players_inserted' => $players_inserted,
        );
    }

    /**
     * Update existing tournament post from parsed data
     *
     * @param int   $tournament_id Existing tournament post ID
     * @param array $tournament_data Parsed tournament data
     * @return array Result with tournament_id
     */
    private function update_existing_tournament($tournament_id, $tournament_data) {
        // Sanitize data
        $tournament_name = sanitize_text_field($tournament_data['name'] ?? 'Untitled Tournament');

        // Update post
        $result = wp_update_post(array(
            'ID' => $tournament_id,
            'post_title' => $tournament_name,
            'post_content' => wp_kses_post($tournament_data['description'] ?? ''),
        ));

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        // Update tournament meta data
        $this->save_tournament_meta($tournament_id, $tournament_data);

        // Update players data (delete old, insert new)
        global $wpdb;
        $players_table = $wpdb->prefix . 'poker_tournament_players';
        $tournament_uuid = $tournament_data['metadata']['uuid'] ?? '';
        $wpdb->delete($players_table, array('tournament_id' => $tournament_uuid), array('%s'));

        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            $this->save_tournament_players($tournament_id, $tournament_data);
        }

        // Trigger statistics refresh (async)
        do_action('poker_tournament_updated', $tournament_id);

        return array(
            'tournament_id' => $tournament_id,
        );
    }

    /**
     * Save tournament meta data - MATCHES regular import exactly
     *
     * @param int   $post_id Tournament post ID
     * @param array $tournament_data Parsed tournament data
     */
    private function save_tournament_meta($post_id, $tournament_data) {
        $metadata = $tournament_data['metadata'];

        // Store tournament UUID
        if (!empty($metadata['uuid'])) {
            update_post_meta($post_id, 'tournament_uuid', $metadata['uuid']);
        }

        // Store series info
        if (!empty($metadata['league_name'])) {
            update_post_meta($post_id, 'tournament_series_name', $metadata['league_name']);
        }
        if (!empty($metadata['league_uuid'])) {
            update_post_meta($post_id, 'tournament_series_uuid', $metadata['league_uuid']);
        }

        // Store season info
        if (!empty($metadata['season_name'])) {
            update_post_meta($post_id, 'tournament_season_name', $metadata['season_name']);
        }
        if (!empty($metadata['season_uuid'])) {
            update_post_meta($post_id, 'tournament_season_uuid', $metadata['season_uuid']);
        }

        // Store tournament date (with underscore prefix for template compatibility)
        if (!empty($metadata['start_time'])) {
            update_post_meta($post_id, 'tournament_date', $metadata['start_time']);
            update_post_meta($post_id, '_tournament_date', $metadata['start_time']);
        }

        // Store PointsForPlaying formula from .tdt file
        if (!empty($metadata['points_formula'])) {
            update_post_meta($post_id, '_tournament_points_formula', $metadata['points_formula']);
        }

        // Store which formula was actually used for points calculation
        if (isset($tournament_data['players'])) {
            $first_player = reset($tournament_data['players']);
            if (isset($first_player['points_calculation']['formula_used'])) {
                update_post_meta($post_id, '_formula_used', $first_player['points_calculation']['formula_used']);

                if (isset($first_player['points_calculation']['formula_description'])) {
                    update_post_meta($post_id, '_formula_description', $first_player['points_calculation']['formula_description']);
                }

                if (isset($first_player['points_calculation']['formula_code'])) {
                    update_post_meta($post_id, '_formula_code', $first_player['points_calculation']['formula_code']);
                }
            }
        }

        // Store full tournament data
        update_post_meta($post_id, 'tournament_data', $tournament_data);

        // Extract and store buy-in information
        $buy_in = $this->extract_buy_in_from_tournament_data($tournament_data);
        if ($buy_in > 0) {
            update_post_meta($post_id, '_buy_in', $buy_in);
        }

        // Extract and store currency information
        $currency = $this->extract_currency_from_tournament_data($tournament_data);
        update_post_meta($post_id, '_currency', $currency);

        // Extract and store game type
        $game_type = $this->extract_game_type_from_tournament_data($tournament_data);
        if ($game_type) {
            update_post_meta($post_id, '_game_type', $game_type);
        }

        // Extract and store tournament structure
        $structure = $this->extract_structure_from_tournament_data($tournament_data);
        if ($structure) {
            update_post_meta($post_id, '_tournament_structure', $structure);
        }

        // Calculate and store points calculation summary
        $points_summary = $this->calculate_points_summary($tournament_data);
        if ($points_summary) {
            update_post_meta($post_id, '_points_summary', $points_summary);
        }

        // Calculate and store enhanced tournament statistics
        $tournament_stats = $this->calculate_enhanced_tournament_stats($tournament_data);
        if ($tournament_stats) {
            update_post_meta($post_id, '_tournament_stats', $tournament_stats);
        }

        // Apply taxonomy auto-categorization
        if (class_exists('Poker_Tournament_Import_Taxonomies')) {
            $taxonomies = new Poker_Tournament_Import_Taxonomies();
            $taxonomies->auto_categorize_tournament($post_id, $tournament_data);
        }
    }

    /**
     * Save tournament players to database
     *
     * @param int   $post_id Tournament post ID
     * @param array $tournament_data Full tournament data with players and metadata
     * @return int Number of players inserted
     */
    private function save_tournament_players($post_id, $tournament_data) {
        global $wpdb;
        $players_table = $wpdb->prefix . 'poker_tournament_players';

        if (empty($tournament_data['players'])) {
            return 0;
        }

        $tournament_uuid = $tournament_data['metadata']['uuid'] ?? '';
        $players_inserted = 0;

        foreach ($tournament_data['players'] as $player_uuid => $player_data) {
            // Calculate total buy-ins COUNT for this player (not chip amounts!)
            $total_buyins = 0;
            if (!empty($player_data['buyins'])) {
                $total_buyins = count($player_data['buyins']);  // Count entries, not sum chip amounts
            }

            // Prepare player data to match ACTUAL table structure
            $player_record = array(
                'tournament_id' => $tournament_uuid, // Use UUID as tournament_id
                'player_id' => $player_uuid, // Use player UUID
                'finish_position' => $player_data['finish_position'] ?? 1,
                'winnings' => $player_data['winnings'] ?? 0,
                'buyins' => $total_buyins,  // Store COUNT of entries
                'rebuys' => 0, // Default to 0 for now
                'addons' => 0, // Default to 0 for now
                'points' => $player_data['points'] ?? 0,
                'hits' => $player_data['hits'] ?? 0
            );

            $result = $wpdb->insert($players_table, $player_record);

            if ($result !== false) {
                $players_inserted++;
            }
        }

        return $players_inserted;
    }

    /**
     * Create or find series post by name and UUID
     *
     * @param string $series_name Series name
     * @param string $series_uuid Series UUID
     * @param string $status      Post status (publish or draft)
     * @return int Post ID or 0 on failure
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
     * Create or find season post by name and UUID
     *
     * @param string $season_name Season name
     * @param string $season_uuid Season UUID
     * @param string $status      Post status (publish or draft)
     * @return int Post ID or 0 on failure
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
     * Create or find player post by name and UUID
     *
     * @param string $player_name Player name
     * @param string $player_uuid Player UUID
     * @param string $status      Post status (publish or draft)
     * @return int Post ID or 0 on failure
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
     * Count total rebuys across all players
     *
     * @param array $tournament_data Tournament data with players
     * @return int Total rebuys count
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
     * Count total addons across all players
     *
     * @param array $tournament_data Tournament data with players
     * @return int Total addons count
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
     * Calculate and store prize pool from tournament data
     *
     * @param int   $tournament_id   Tournament post ID
     * @param array $tournament_data Tournament data with players
     * @return float Total prize pool amount
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

        // Process tournament financial data
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

            $stats_engine->process_tournament_financial_data($tournament_id, $financial_data);
        }

        return $total_prize_pool;
    }

    /**
     * Extract buy-in amount from tournament data
     *
     * @param array $tournament_data Tournament data
     * @return float Buy-in amount
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
     * Extract currency from tournament data
     *
     * @param array $tournament_data Tournament data
     * @return string Currency symbol
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
     * Extract game type from tournament data
     *
     * @param array $tournament_data Tournament data
     * @return string Game type
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
     * Extract tournament structure from tournament data
     *
     * @param array $tournament_data Tournament data
     * @return string Structure description
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
     * Calculate points summary from tournament data
     *
     * @param array $tournament_data Tournament data with players
     * @return array Points summary statistics
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
     * Calculate enhanced tournament statistics
     *
     * @param array $tournament_data Tournament data with players
     * @return array Enhanced statistics
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

    /**
     * Mark file as skipped
     *
     * @param int    $file_id File record ID
     * @param string $reason Reason for skipping
     * @param int    $duplicate_id Tournament ID of duplicate
     */
    private function mark_file_skipped($file_id, $reason, $duplicate_id = 0) {
        global $wpdb;
        $files_table = $wpdb->prefix . 'poker_import_batch_files';

        $parse_details = json_encode(array(
            'skipped' => true,
            'reason' => $reason,
            'duplicate_id' => $duplicate_id,
        ));

        $wpdb->update(
            $files_table,
            array(
                'status' => 'skipped',
                'tournament_id' => $duplicate_id > 0 ? $duplicate_id : null,
                'parse_details' => $parse_details,
                'processed_at' => current_time('mysql'),
            ),
            array('id' => $file_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Update batch progress counters
     *
     * @param int $batch_id Batch record ID
     */
    private function update_batch_progress($batch_id) {
        global $wpdb;
        $batches_table = $wpdb->prefix . 'poker_import_batches';
        $files_table = $wpdb->prefix . 'poker_import_batch_files';

        // Get file counts by status
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('completed', 'skipped', 'failed') THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as errors
             FROM $files_table
             WHERE batch_id = %d",
            $batch_id
        ));

        // Determine batch status
        $batch_status = 'processing';
        if ($counts->processed >= $counts->total) {
            // All files processed
            if ($counts->errors > 0 && $counts->success == 0) {
                $batch_status = 'failed';
            } else {
                $batch_status = 'completed';
            }
        }

        // Update batch record
        $update_data = array(
            'processed_count' => $counts->processed,
            'success_count' => $counts->success,
            'error_count' => $counts->errors,
            'status' => $batch_status,
        );

        if ($batch_status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }

        $wpdb->update(
            $batches_table,
            $update_data,
            array('id' => $batch_id),
            array('%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Cleanup temporary file after processing
     *
     * @param string $filepath Path to temp file
     */
    private function cleanup_temp_file($filepath) {
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }

    /**
     * Retry processing a failed file
     *
     * @param int    $file_id File record ID
     * @param string $batch_uuid Batch UUID
     * @param array  $options Processing options
     * @return array Processing result
     */
    public function retry_failed_file($file_id, $batch_uuid, $options = array()) {
        global $wpdb;
        $files_table = $wpdb->prefix . 'poker_import_batch_files';

        // Reset file status to pending
        $wpdb->update(
            $files_table,
            array(
                'status' => 'pending',
                'error_message' => null,
            ),
            array('id' => $file_id),
            array('%s', '%s'),
            array('%d')
        );

        // Process the file again
        return $this->process_single_file($file_id, $batch_uuid, $options);
    }
}
