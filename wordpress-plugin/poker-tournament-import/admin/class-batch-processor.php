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
     * Create new tournament post from parsed data
     *
     * @param array $tournament_data Parsed tournament data
     * @return array Result with tournament_id
     */
    private function create_tournament($tournament_data) {
        // Sanitize data
        $tournament_name = sanitize_text_field($tournament_data['name'] ?? 'Untitled Tournament');
        $tournament_date = sanitize_text_field($tournament_data['date'] ?? current_time('Y-m-d'));

        // Create post
        $post_id = wp_insert_post(array(
            'post_title' => $tournament_name,
            'post_type' => 'tournament',
            'post_status' => 'publish',
            'post_content' => wp_kses_post($tournament_data['description'] ?? ''),
        ));

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Save tournament meta data
        $this->save_tournament_meta($post_id, $tournament_data);

        // Save players data
        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            $this->save_tournament_players($post_id, $tournament_data['players']);
        }

        // Trigger statistics refresh (async)
        do_action('poker_tournament_imported', $post_id);

        return array(
            'tournament_id' => $post_id,
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
        $wpdb->delete($players_table, array('tournament_id' => $tournament_id), array('%d'));

        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            $this->save_tournament_players($tournament_id, $tournament_data['players']);
        }

        // Trigger statistics refresh (async)
        do_action('poker_tournament_updated', $tournament_id);

        return array(
            'tournament_id' => $tournament_id,
        );
    }

    /**
     * Save tournament meta data
     *
     * @param int   $post_id Tournament post ID
     * @param array $tournament_data Parsed tournament data
     */
    private function save_tournament_meta($post_id, $tournament_data) {
        // Save all meta fields
        $meta_fields = array(
            'tournament_name',
            'tournament_date',
            'tournament_venue',
            'buy_in',
            'prize_pool',
            'total_players',
            'start_time',
            'end_time',
            'blind_structure',
            'prize_structure',
            'game_type',
            'tournament_director',
            'tournament_notes',
        );

        foreach ($meta_fields as $field) {
            if (isset($tournament_data[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($tournament_data[$field]));
            }
        }

        // Save structured data as JSON
        if (isset($tournament_data['blind_levels']) && is_array($tournament_data['blind_levels'])) {
            update_post_meta($post_id, 'blind_levels', wp_json_encode($tournament_data['blind_levels']));
        }

        if (isset($tournament_data['prizes']) && is_array($tournament_data['prizes'])) {
            update_post_meta($post_id, 'prizes', wp_json_encode($tournament_data['prizes']));
        }
    }

    /**
     * Save tournament players to database
     *
     * @param int   $post_id Tournament post ID
     * @param array $players Array of player data
     */
    private function save_tournament_players($post_id, $players) {
        global $wpdb;
        $players_table = $wpdb->prefix . 'poker_tournament_players';

        foreach ($players as $player) {
            $wpdb->insert(
                $players_table,
                array(
                    'tournament_id' => $post_id,
                    'player_name' => sanitize_text_field($player['name'] ?? ''),
                    'finish_position' => intval($player['position'] ?? 0),
                    'prize_won' => floatval($player['prize'] ?? 0),
                    'buy_ins' => intval($player['buy_ins'] ?? 1),
                    'rebuys' => intval($player['rebuys'] ?? 0),
                    'add_ons' => intval($player['add_ons'] ?? 0),
                    'eliminated_by' => sanitize_text_field($player['eliminated_by'] ?? ''),
                ),
                array('%d', '%s', '%d', '%f', '%d', '%d', '%d', '%s')
            );
        }
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
