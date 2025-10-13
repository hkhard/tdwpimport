<?php
/**
 * Migration Tools Class
 *
 * Handles migration of existing tournament data to fix series/season relationships
 * and apply taxonomy categorization to existing tournaments
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Migration_Tools {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'handle_migration_actions'));
    }

    /**
     * Get tournaments needing relationship migration
     */
    public function get_tournaments_needing_migration() {
        $args = array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_series_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_season_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $tournaments = get_posts($args);
        return $tournaments;
    }

    /**
     * Get count of tournaments needing migration
     */
    public function get_migration_count() {
        $tournaments = $this->get_tournaments_needing_migration();
        return count($tournaments);
    }

    /**
     * Migrate tournament relationships
     */
    public function migrate_tournament_relationships($tournament_id = null) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        if ($tournament_id) {
            // Migrate single tournament
            $tournament_ids = array($tournament_id);
        } else {
            // Migrate all tournaments
            $tournament_ids = $this->get_tournaments_needing_migration();
        }

        if (empty($tournament_ids)) {
            return $results;
        }

        foreach ($tournament_ids as $tournament_id) {
            try {
                $success = $this->migrate_single_tournament($tournament_id);
                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to migrate tournament ID: {$tournament_id}";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Exception for tournament ID {$tournament_id}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Migrate a single tournament
     */
    private function migrate_single_tournament($tournament_id) {
        // Get tournament data
        $tournament_data = get_post_meta($tournament_id, 'tournament_data', true);

        // If no tournament_data meta field, try to extract from other available meta fields
        if (empty($tournament_data)) {
            $tournament_data = $this->reconstruct_tournament_data($tournament_id);
            if (empty($tournament_data)) {
                Poker_Tournament_Import_Debug::log("No tournament data available for migration: Tournament ID {$tournament_id}");
                return false;
            }
        }

        $migrated = false;
        $metadata = $tournament_data['metadata'] ?? array();

        // Migrate series relationship
        if (!empty($metadata['league_name'])) {
            $series_id = $this->find_or_create_series(
                $metadata['league_name'],
                $metadata['league_uuid'] ?? ''
            );

            if ($series_id) {
                update_post_meta($tournament_id, '_series_id', $series_id);
                $migrated = true;
            }
        }

        // Migrate season relationship
        if (!empty($metadata['season_name'])) {
            $season_id = $this->find_or_create_season(
                $metadata['season_name'],
                $metadata['season_uuid'] ?? ''
            );

            if ($season_id) {
                update_post_meta($tournament_id, '_season_id', $season_id);
                $migrated = true;
            }
        }

        // Apply taxonomy categorization if migrated
        if ($migrated && class_exists('Poker_Tournament_Import_Taxonomies')) {
            $taxonomies = new Poker_Tournament_Import_Taxonomies();
            $taxonomies->auto_categorize_tournament($tournament_id, $tournament_data);
        }

        return $migrated;
    }

    /**
     * Sync tournament player data from meta fields to database table
     */
    public function sync_tournament_player_data($tournament_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        $results = array(
            'synced' => 0,
            'failed' => 0,
            'errors' => array(),
            'diagnostics' => array()
        );

        if ($tournament_id) {
            $tournament_ids = array($tournament_id);
        } else {
            // Get all tournaments
            $args = array(
                'post_type' => 'tournament',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            $tournament_ids = get_posts($args);
        }

        foreach ($tournament_ids as $tid) {
            try {
                // Comprehensive diagnostic check
                $tournament_data = get_post_meta($tid, 'tournament_data', true);
                $tournament_uuid = get_post_meta($tid, 'tournament_uuid', true);
                $post_title = get_the_title($tid);

                $diagnostic = array(
                    'tournament_id' => $tid,
                    'title' => $post_title,
                    'has_tournament_data' => !empty($tournament_data),
                    'has_players' => !empty($tournament_data['players']),
                    'player_count' => !empty($tournament_data['players']) ? count($tournament_data['players']) : 0,
                    'has_uuid' => !empty($tournament_uuid),
                    'uuid' => $tournament_uuid,
                    'all_meta_keys' => get_post_meta($tid)
                );

                // Debug logging
                Poker_Tournament_Import_Debug::log("Processing tournament ID: {$tid} - {$post_title}");
                Poker_Tournament_Import_Debug::log("Has tournament_data: " . ($diagnostic['has_tournament_data'] ? 'YES' : 'NO'));
                Poker_Tournament_Import_Debug::log("Has players: " . ($diagnostic['has_players'] ? 'YES - ' . $diagnostic['player_count'] : 'NO'));
                Poker_Tournament_Import_Debug::log("Has UUID: " . ($diagnostic['has_uuid'] ? 'YES - ' . $tournament_uuid : 'NO'));

                // Try to find player data in alternative locations
                if (empty($tournament_data)) {
                    // Check for other meta fields that might contain player data
                    $alternative_data = array();
                    $all_meta = get_post_meta($tid);

                    foreach ($all_meta as $key => $value) {
                        if (is_array($value) && count($value) == 1) {
                            $value = $value[0];
                        }
                        if (is_string($value) && (strpos($key, 'player') !== false || strpos($key, 'result') !== false)) {
                            $alternative_data[$key] = $value;
                        }
                    }

                    if (!empty($alternative_data)) {
                        $diagnostic['alternative_data_found'] = $alternative_data;
                        Poker_Tournament_Import_Debug::log("Alternative data found: " . print_r($alternative_data, true));
                    }
                }

                $results['diagnostics'][] = $diagnostic;

                if (!empty($tournament_data) && !empty($tournament_data['players']) && !empty($tournament_uuid)) {
                    // Remove existing player data for this tournament
                    $wpdb->delete($table_name, array('tournament_id' => $tournament_uuid));

                    // Insert player data
                    $players_inserted = 0;
                    foreach ($tournament_data['players'] as $player) {
                        // Calculate buyins, rebuys, and addons from buyins array
                        $buyin_count = 0;
                        $rebuy_count = 0;
                        $addon_count = 0;

                        if (!empty($player['buyins']) && is_array($player['buyins'])) {
                            $buyin_count = count($player['buyins']);
                            foreach ($player['buyins'] as $buyin) {
                                $profile = $buyin['profile'] ?? '';
                                if (stripos($profile, 'rebuy') !== false) {
                                    $rebuy_count++;
                                } elseif (stripos($profile, 'addon') !== false) {
                                    $addon_count++;
                                }
                            }
                        }

                        $result = $wpdb->insert($table_name, array(
                            'tournament_id' => $tournament_uuid,
                            'player_id' => $player['uuid'] ?? '',
                            'finish_position' => intval($player['finish_position'] ?? 0),
                            'winnings' => floatval($player['winnings'] ?? 0),
                            'buyins' => intval($buyin_count),
                            'rebuys' => intval($rebuy_count),
                            'addons' => intval($addon_count),
                            'hits' => intval($player['hits'] ?? 0),
                            'points' => floatval($player['points'] ?? 0)
                        ));

                        if ($result) {
                            $players_inserted++;
                        }
                    }

                    if ($players_inserted > 0) {
                        $results['synced']++;
                        Poker_Tournament_Import_Debug::log("Synced {$players_inserted} players for tournament {$tid}");
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "No players inserted for tournament ID: {$tid}";
                    }
                } else {
                    $results['failed']++;
                    $results['errors'][] = "No tournament data available for tournament ID: {$tid}";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Exception for tournament ID {$tid}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Reconstruct tournament data from available meta fields
     */
    private function reconstruct_tournament_data($tournament_id) {
        $metadata = array();

        // Try to get series and season information from existing meta fields
        $series_id = get_post_meta($tournament_id, '_series_id', true);
        $season_id = get_post_meta($tournament_id, '_season_id', true);

        // If we already have relationships, no need to migrate
        if (!empty($series_id) || !empty($season_id)) {
            return array(); // Already migrated
        }

        // Try to get league/season info from other meta fields
        $league_name = get_post_meta($tournament_id, '_league_name', true);
        $season_name = get_post_meta($tournament_id, '_season_name', true);
        $tournament_uuid = get_post_meta($tournament_id, '_tournament_uuid', true);

        if (empty($league_name) && empty($season_name)) {
            return array(); // No data to reconstruct
        }

        // Build metadata structure
        if (!empty($league_name)) {
            $metadata['league_name'] = $league_name;
            $metadata['league_uuid'] = get_post_meta($tournament_id, '_league_uuid', true) ?: '';
        }

        if (!empty($season_name)) {
            $metadata['season_name'] = $season_name;
            $metadata['season_uuid'] = get_post_meta($tournament_id, '_season_uuid', true) ?: '';
        }

        if (!empty($tournament_uuid)) {
            $metadata['tournament_uuid'] = $tournament_uuid;
        }

        return array(
            'metadata' => $metadata
        );
    }

    /**
     * Find or create series post
     */
    private function find_or_create_series($series_name, $series_uuid = '') {
        // Try UUID first
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

            $existing = get_posts($args);
            if (!empty($existing)) {
                return $existing[0]->ID;
            }
        }

        // Try name next
        $args = array(
            'post_type' => 'tournament_series',
            'title' => $series_name,
            'posts_per_page' => 1
        );

        $existing = get_posts($args);
        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Create new series
        $post_data = array(
            'post_title' => $series_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'tournament_series'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id) && !empty($series_uuid)) {
            update_post_meta($post_id, 'series_uuid', $series_uuid);
        }

        return $post_id && !is_wp_error($post_id) ? $post_id : 0;
    }

    /**
     * Find or create season post
     */
    private function find_or_create_season($season_name, $season_uuid = '') {
        // Try UUID first
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

            $existing = get_posts($args);
            if (!empty($existing)) {
                return $existing[0]->ID;
            }
        }

        // Try name next
        $args = array(
            'post_type' => 'tournament_season',
            'title' => $season_name,
            'posts_per_page' => 1
        );

        $existing = get_posts($args);
        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Create new season
        $post_data = array(
            'post_title' => $season_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'tournament_season'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id) && !empty($season_uuid)) {
            update_post_meta($post_id, 'season_uuid', $season_uuid);
        }

        return $post_id && !is_wp_error($post_id) ? $post_id : 0;
    }

    /**
     * Verify tournament relationships
     */
    public function verify_relationships() {
        $args = array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $all_tournaments = get_posts($args);
        $verification = array(
            'total' => count($all_tournaments),
            'has_series' => 0,
            'has_season' => 0,
            'has_both' => 0,
            'has_neither' => 0,
            'orphaned_series' => array(),
            'orphaned_seasons' => array()
        );

        foreach ($all_tournaments as $tournament_id) {
            $series_id = get_post_meta($tournament_id, '_series_id', true);
            $season_id = get_post_meta($tournament_id, '_season_id', true);

            if (!empty($series_id)) {
                $verification['has_series']++;
                if (!get_post($series_id)) {
                    $verification['orphaned_series'][] = $tournament_id;
                }
            }

            if (!empty($season_id)) {
                $verification['has_season']++;
                if (!get_post($season_id)) {
                    $verification['orphaned_seasons'][] = $tournament_id;
                }
            }

            if (!empty($series_id) && !empty($season_id)) {
                $verification['has_both']++;
            }

            if (empty($series_id) && empty($season_id)) {
                $verification['has_neither']++;
            }
        }

        return $verification;
    }

    /**
     * Handle migration actions from admin interface
     */
    public function handle_migration_actions() {
        if (!isset($_POST['poker_migration_action']) || !check_admin_referer('poker_migration_action', 'nonce')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'poker-tournament-import'));
        }

        $action = sanitize_text_field($_POST['poker_migration_action']);

        switch ($action) {
            case 'migrate_all':
                $results = $this->migrate_tournament_relationships();
                $this->set_admin_notice('migration_results', $results);
                break;

            case 'migrate_single':
                $tournament_id = intval($_POST['tournament_id']);
                $results = $this->migrate_tournament_relationships($tournament_id);
                $this->set_admin_notice('single_migration_results', $results);
                break;

            case 'verify':
                $verification = $this->verify_relationships();
                $this->set_admin_notice('verification_results', $verification);
                break;

            case 'sync_players':
                $sync_results = $this->sync_tournament_player_data();
                $this->set_admin_notice('sync_results', $sync_results);
                break;
        }

        // Get the referer URL and add migration status
        $referer = wp_get_referer();
        if ($referer) {
            // Add success parameter to the URL for better UX feedback
            $redirect_url = add_query_arg(array(
                'migration_status' => 'completed',
                'action' => $action
            ), $referer);

            wp_safe_redirect($redirect_url);
        } else {
            // Fallback to admin page
            wp_safe_redirect(admin_url('admin.php?page=poker-migration-tools&migration_status=completed'));
        }
        exit;
    }

    /**
     * Set admin notice for results display
     */
    private function set_admin_notice($key, $data) {
        set_transient('poker_migration_' . $key, $data, 60);
    }

    /**
     * Get admin notice data
     */
    public function get_admin_notice($key) {
        return get_transient('poker_migration_' . $key);
    }

    /**
     * Clear admin notice data
     */
    public function clear_admin_notice($key) {
        delete_transient('poker_migration_' . $key);
    }
}