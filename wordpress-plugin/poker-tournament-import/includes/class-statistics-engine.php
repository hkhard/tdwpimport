<?php
/**
 * Poker Statistics Engine
 *
 * Handles calculation and storage of dashboard statistics
 * in the data mart for fast dashboard loading.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Statistics_Engine {
    /**n     * Note: error_log() calls in this class are for debugging and should ben     * wrapped in if (defined("WP_DEBUG") && WP_DEBUG) or suppressed with phpcs:ignoren     */

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Statistics table name
     */
    private $stats_table;

    /**
     * Players table name
     */
    private $players_table;

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
        global $wpdb;
        $this->stats_table = $wpdb->prefix . 'poker_statistics';
        $this->players_table = $wpdb->prefix . 'poker_tournament_players';
    }

    /**
     * Calculate and store all dashboard statistics
     */
    public function calculate_all_statistics() {
        global $wpdb;

        // Start timer for performance tracking
        $start_time = microtime(true);

        // Clear existing statistics
        $this->clear_all_statistics();

        // Calculate basic counts
        $this->update_statistic('total_tournaments', $this->get_total_tournaments(), 'count');
        $this->update_statistic('total_players', $this->get_unique_players(), 'count');
        $this->update_statistic('active_series', $this->get_active_series(), 'count');
        $this->update_statistic('total_prize_pool', $this->get_total_prize_pool(), 'sum');

        // Calculate derived statistics
        $total_tournaments = $this->get_total_tournaments();
        $total_prize_pool = $this->get_total_prize_pool();
        $this->update_statistic('avg_prize_pool', $total_tournaments > 0 ? $total_prize_pool / $total_tournaments : 0, 'average');

        // Calculate time-based statistics
        $this->update_statistic('recent_tournaments_30d', $this->get_recent_tournaments(30), 'count');
        $this->update_statistic('new_players_30d', $this->get_new_players(30), 'count');

        // **PHASE 1: Player Ranking & Performance Metrics**
        $this->update_statistic('total_entries', $this->get_total_entries(), 'count');
        $this->update_statistic('total_cashouts', $this->get_total_cashouts(), 'count');
        $this->update_statistic('total_payouts', $this->get_total_payouts(), 'sum');
        $this->update_statistic('average_players_per_tournament', $this->get_average_players_per_tournament(), 'average');
        $this->update_statistic('average_entry_fee', $this->get_average_entry_fee(), 'average');
        $this->update_statistic('largest_prize_pool', $this->get_largest_prize_pool(), 'max');
        $this->update_statistic('highest_single_payout', $this->get_highest_single_payout(), 'max');
        $this->update_statistic('average_finish_position', $this->get_average_finish_position(), 'average');
        $this->update_statistic('total_unique_players', $this->get_total_unique_players(), 'count');

        // **PHASE 2.1: Core Financial Data Infrastructure**
        $this->calculate_financial_summary();

        // Log performance
        $calculation_time = round((microtime(true) - $start_time) * 1000, 2);
        error_log("Poker Statistics: All statistics calculated in {$calculation_time}ms");

        return true;
    }

    /**
     * Update a single statistic
     */
    public function update_statistic($name, $value, $type = 'count', $related_id = null) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $result = $wpdb->replace(
            $this->stats_table,
            array(
                'stat_name' => $name,
                'stat_value' => $value,
                'stat_type' => $type,
                'calculation_date' => current_time('Y-m-d'),
                'related_id' => $related_id
            ),
            array(
                '%s', '%f', '%s', '%s', '%d'
            )
        );

        if ($result === false) {
            error_log("Poker Statistics: Failed to update statistic '{$name}'");
        }

        return $result !== false;
    }

    /**
     * Get a single statistic value (with WordPress caching)
     */
    public function get_statistic($name) {
        global $wpdb;

        // Try WordPress cache first
        $cache_key = 'poker_stat_' . $name;
        $cached_value = wp_cache_get($cache_key, 'poker_statistics');

        if ($cached_value !== false) {
            return floatval($cached_value);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT stat_value FROM {$this->stats_table} WHERE stat_name = %s LIMIT 1",
            $name
        ));

        $result = $value !== null ? floatval($value) : 0;

        // Cache for 1 hour
        wp_cache_set($cache_key, $result, 'poker_statistics', HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Get multiple statistics at once
     */
    public function get_statistics($names) {
        $stats = array();
        foreach ($names as $name) {
            $stats[$name] = $this->get_statistic($name);
        }
        return $stats;
    }

    /**
     * Clear all statistics
     */
    public function clear_all_statistics() {
        global $wpdb;

        // Clear WordPress cache for all statistics
        $stat_names = array(
            'total_tournaments', 'total_players', 'active_series', 'total_prize_pool',
            'avg_prize_pool', 'recent_tournaments_30d', 'new_players_30d',
            'total_entries', 'total_cashouts', 'total_payouts', 'average_players_per_tournament',
            'average_entry_fee', 'largest_prize_pool', 'highest_single_payout',
            'average_finish_position', 'total_unique_players', 'total_revenue',
            'total_costs', 'total_profit', 'average_profit_per_tournament',
            'profit_margin_percentage', 'total_rake_collected', 'average_rake_per_tournament',
            'buy_in_to_prize_pool_ratio', 'prize_pool_efficiency',
            'most_profitable_tournament_type', 'revenue_growth_rate_30d', 'profit_growth_rate_30d'
        );

        foreach ($stat_names as $name) {
            wp_cache_delete('poker_stat_' . $name, 'poker_statistics');
        }

        return $wpdb->query("TRUNCATE TABLE {$this->stats_table}");
    }

  
    /**
     * Get unique players count
     */
    private function get_unique_players() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return intval($wpdb->get_var("SELECT COUNT(DISTINCT player_id) FROM {$this->players_table}"));
    }

    /**
     * Get active series count
     */
    private function get_active_series() {
        return intval(wp_count_posts('tournament_series')->publish);
    }

    
    /**
     * Get recent tournaments within days
     */
    private function get_recent_tournaments($days = 30) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'tournament' AND post_status = 'publish'
             AND post_date >= %s",
            gmdate('Y-m-d', strtotime("-{$days} days"))
        ));

        return intval($count);
    }

    /**
     * Get new players within days
     */
    private function get_new_players($days = 30) {
        global $wpdb;

        // Try different possible UUID field names - prioritize tournament_uuid since that's what we have data for
        $possible_uuid_fields = array('tournament_uuid', '_tournament_uuid', 'uuid', '_uuid');

        foreach ($possible_uuid_fields as $uuid_field) {
            // Check if this UUID field exists in tournaments
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $field_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $uuid_field
            ));

            if ($field_count && $field_count > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT tp.player_id)
                     FROM {$this->players_table} tp
                     WHERE tp.tournament_id IN (
                         SELECT meta_value FROM {$wpdb->postmeta}
                         WHERE meta_key = %s
                         AND post_id IN (
                             SELECT ID FROM {$wpdb->posts}
                             WHERE post_type = 'tournament'
                             AND post_status = 'publish'
                             AND post_date >= %s
                         )
                     )",
                    $uuid_field,
                    gmdate('Y-m-d', strtotime("-{$days} days"))
                ));

                error_log("Poker Statistics: Using UUID field '{$uuid_field}' for new players calculation");
                return intval($count);
            }
        }

        return 0;
    }

    /**
     * Refresh statistics (public method for manual refresh)
     */
    public function refresh_statistics() {
        return $this->calculate_all_statistics();
    }

    /**
     * Get statistics last updated time
     */
    public function get_last_updated() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $last_updated = $wpdb->get_var("SELECT MAX(last_updated) FROM {$this->stats_table}");

        return $last_updated ? $last_updated : false;
    }

    /**
     * Get statistics for dashboard
     *
     * @param int|string|null $season_id Season ID to filter by, or 'all' for all seasons, or null for no filter
     */
    public function get_dashboard_statistics($season_id = null) {
        // Auto-calculate if statistics table is empty (only when no season filter)
        if (!$season_id && !$this->has_statistics()) {
            error_log("Poker Statistics: No statistics found, calculating initial statistics");
            $this->calculate_all_statistics();
        }

        // If season filtering is active, calculate stats dynamically
        if ($season_id && $season_id !== 'all') {
            return array(
                'total_tournaments' => $this->get_total_tournaments(null, null, 0, $season_id),
                'total_players' => $this->get_total_players(null, null, 0, $season_id),
                'total_prize_pool' => $this->get_total_prize_pool(null, null, 0, $season_id),
                'avg_prize_pool' => $this->get_average_prize_pool(null, null, 0, $season_id),
                'active_series' => $this->get_statistic('active_series'),
                'recent_tournaments_30d' => $this->get_statistic('recent_tournaments_30d'),
                'new_players_30d' => $this->get_statistic('new_players_30d'),
                'last_updated' => $this->get_last_updated(),

                // **PHASE 1: Enhanced Player Metrics**
                'total_entries' => $this->get_statistic('total_entries'),
                'total_cashouts' => $this->get_statistic('total_cashouts'),
                'total_payouts' => $this->get_statistic('total_payouts'),
                'average_players_per_tournament' => $this->get_average_players_per_tournament(null, null, 0, $season_id),
                'average_entry_fee' => $this->get_statistic('average_entry_fee'),
                'largest_prize_pool' => $this->get_statistic('largest_prize_pool'),
                'highest_single_payout' => $this->get_statistic('highest_single_payout'),
                'average_finish_position' => $this->get_statistic('average_finish_position'),
                'total_unique_players' => $this->get_statistic('total_unique_players'),

                // **PHASE 2.1: Core Financial Data Infrastructure**
                'total_revenue' => $this->get_statistic('total_revenue'),
                'total_costs' => $this->get_statistic('total_costs'),
                'total_profit' => $this->get_statistic('total_profit'),
                'average_profit_per_tournament' => $this->get_statistic('average_profit_per_tournament'),
                'profit_margin_percentage' => $this->get_statistic('profit_margin_percentage'),
                'total_rake_collected' => $this->get_statistic('total_rake_collected'),
                'average_rake_per_tournament' => $this->get_statistic('average_rake_per_tournament'),
                'buy_in_to_prize_pool_ratio' => $this->get_statistic('buy_in_to_prize_pool_ratio'),
                'prize_pool_efficiency' => $this->get_statistic('prize_pool_efficiency'),
                'most_profitable_tournament_type' => $this->get_statistic('most_profitable_tournament_type'),
                'revenue_growth_rate_30d' => $this->get_statistic('revenue_growth_rate_30d'),
                'profit_growth_rate_30d' => $this->get_statistic('profit_growth_rate_30d')
            );
        }

        // Default: return statistics from data mart (no season filter)
        return array(
            'total_tournaments' => $this->get_statistic('total_tournaments'),
            'total_players' => $this->get_statistic('total_players'),
            'total_prize_pool' => $this->get_statistic('total_prize_pool'),
            'avg_prize_pool' => $this->get_statistic('avg_prize_pool'),
            'active_series' => $this->get_statistic('active_series'),
            'recent_tournaments_30d' => $this->get_statistic('recent_tournaments_30d'),
            'new_players_30d' => $this->get_statistic('new_players_30d'),
            'last_updated' => $this->get_last_updated(),

            // **PHASE 1: Enhanced Player Metrics**
            'total_entries' => $this->get_statistic('total_entries'),
            'total_cashouts' => $this->get_statistic('total_cashouts'),
            'total_payouts' => $this->get_statistic('total_payouts'),
            'average_players_per_tournament' => $this->get_statistic('average_players_per_tournament'),
            'average_entry_fee' => $this->get_statistic('average_entry_fee'),
            'largest_prize_pool' => $this->get_statistic('largest_prize_pool'),
            'highest_single_payout' => $this->get_statistic('highest_single_payout'),
            'average_finish_position' => $this->get_statistic('average_finish_position'),
            'total_unique_players' => $this->get_statistic('total_unique_players'),

            // **PHASE 2.1: Core Financial Data Infrastructure**
            'total_revenue' => $this->get_statistic('total_revenue'),
            'total_costs' => $this->get_statistic('total_costs'),
            'total_profit' => $this->get_statistic('total_profit'),
            'average_profit_per_tournament' => $this->get_statistic('average_profit_per_tournament'),
            'profit_margin_percentage' => $this->get_statistic('profit_margin_percentage'),
            'total_rake_collected' => $this->get_statistic('total_rake_collected'),
            'average_rake_per_tournament' => $this->get_statistic('average_rake_per_tournament'),
            'buy_in_to_prize_pool_ratio' => $this->get_statistic('buy_in_to_prize_pool_ratio'),
            'prize_pool_efficiency' => $this->get_statistic('prize_pool_efficiency'),
            'most_profitable_tournament_type' => $this->get_statistic('most_profitable_tournament_type'),
            'revenue_growth_rate_30d' => $this->get_statistic('revenue_growth_rate_30d'),
            'profit_growth_rate_30d' => $this->get_statistic('profit_growth_rate_30d')
        );
    }

    /**
     * Check if statistics table has data
     */
    private function has_statistics() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->stats_table}");
        return intval($count) > 0;
    }

    /**
     * Hook into tournament import completion
     */
    public function on_tournament_import_complete($tournament_id) {
        // Refresh statistics after each tournament import
        $this->calculate_all_statistics();
    }

    /**
     * AJAX handler for manual statistics refresh
     */
    public function ajax_refresh_statistics() {
        check_ajax_referer('poker_refresh_statistics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
        }

        $result = $this->refresh_statistics();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Statistics refreshed successfully!', 'poker-tournament-import'),
                'timestamp' => current_time('mysql'),
                'stats' => $this->get_dashboard_statistics()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to refresh statistics.', 'poker-tournament-import')
            ));
        }
    }

    /**
     * Get total entries (buy-ins) across all tournaments
     */
    private function get_total_entries() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $total = $wpdb->get_var("SELECT SUM(buyins) FROM {$this->players_table}");
        return intval($total ?: 0);
    }

    /**
     * Get total entries with date filtering and season filtering
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    private function get_total_entries_filtered($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        global $wpdb;

        $query = "SELECT SUM(tp.buyins)
                 FROM {$this->players_table} tp
                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'";
        $params = array();

        if ($start_date && $end_date) {
            $query .= " AND p.post_date >= %s AND p.post_date <= %s";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        if ($series_id > 0) {
            $query .= " AND pm.post_id IN (
                SELECT object_id FROM {$wpdb->term_relationships}
                WHERE term_taxonomy_id = %d
            )";
            $params[] = $series_id;
        }

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " AND p.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_season_id' AND meta_value = %d
            )";
            $params[] = intval($season_id);
        }

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $total = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $total = $wpdb->get_var($query);
        }

        return intval($total ?: 0);
    }

    /**
     * Get total cashouts (players who won money)
     */
    private function get_total_cashouts() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->players_table} WHERE winnings > 0");
        return intval($count ?: 0);
    }

    /**
     * Get total payouts across all tournaments
     */
    private function get_total_payouts() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $total = $wpdb->get_var("SELECT SUM(winnings) FROM {$this->players_table}");
        return floatval($total ?: 0);
    }

    
    /**
     * Get average entry fee
     */
    private function get_average_entry_fee() {
        global $wpdb;

        $total_entries = $this->get_total_entries();
        $tournament_count = $this->get_total_tournaments();

        if ($total_entries > 0 && $tournament_count > 0) {
            return $total_entries / $tournament_count;
        }

        return 0;
    }

    /**
     * Get largest prize pool
     */
    private function get_largest_prize_pool() {
        global $wpdb;

        // Try different possible prize pool field names
        $possible_fields = array('_prize_pool', 'prize_pool', '_tournament_prize_pool', 'tournament_prize_pool');

        foreach ($possible_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $max = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $field
            ));
            if ($max && $max > 0) {
                return floatval($max);
            }
        }

        // Fallback: calculate from players table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $max = $wpdb->get_var(
            "SELECT MAX(winnings) FROM {$this->players_table}"
        );
        return floatval($max ?: 0);
    }

    /**
     * Get highest single payout
     */
    private function get_highest_single_payout() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $max = $wpdb->get_var("SELECT MAX(winnings) FROM {$this->players_table}");
        return floatval($max ?: 0);
    }

    /**
     * Get average finish position across all players
     */
    private function get_average_finish_position() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $avg = $wpdb->get_var("SELECT AVG(finish_position) FROM {$this->players_table} WHERE finish_position > 0");
        return floatval($avg ?: 0);
    }

    /**
     * Get total unique players (alias for get_unique_players for consistency)
     */
    private function get_total_unique_players() {
        return $this->get_unique_players();
    }

    // ========================================
    // PUBLIC API METHODS FOR DASHBOARD AJAX
    // ========================================

    /**
     * Get total tournaments with date filtering and season filtering
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    public function get_total_tournaments($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'tournament' AND p.post_status = 'publish'";
        $params = array();

        if ($start_date && $end_date) {
            $query .= " AND p.post_date >= %s AND p.post_date <= %s";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " AND p.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_season_id' AND meta_value = %d
            )";
            $params[] = intval($season_id);
        }

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $count = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $count = $wpdb->get_var($query);
        }

        return intval($count);
    }

    /**
     * Get total players with date filtering and season filtering
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    public function get_total_players($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        global $wpdb;

        $query = "SELECT COUNT(DISTINCT tp.player_id)
                 FROM {$this->players_table} tp
                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'";
        $params = array();

        if ($start_date && $end_date) {
            $query .= " AND p.post_date >= %s AND p.post_date <= %s";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        if ($series_id > 0) {
            $query .= " AND pm.post_id IN (
                SELECT object_id FROM {$wpdb->term_relationships}
                WHERE term_taxonomy_id = %d
            )";
            $params[] = $series_id;
        }

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " AND p.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_season_id' AND meta_value = %d
            )";
            $params[] = intval($season_id);
        }

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $count = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $count = $wpdb->get_var($query);
        }

        return intval($count);
    }

    /**
     * Get total prize pool with date filtering and season filtering
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    public function get_total_prize_pool($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        global $wpdb;

        // Try different possible prize pool field names
        $possible_fields = array('_prize_pool', 'prize_pool', '_tournament_prize_pool', 'tournament_prize_pool');

        foreach ($possible_fields as $field) {
            $query = "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = %s AND pm.meta_value != '' AND p.post_status = 'publish'";
            $params = array($field);

            if ($start_date && $end_date) {
                $query .= " AND p.post_date >= %s AND p.post_date <= %s";
                $params[] = $start_date . ' 00:00:00';
                $params[] = $end_date . ' 23:59:59';
            }

            if ($series_id > 0) {
                $query .= " AND p.ID IN (
                    SELECT object_id FROM {$wpdb->term_relationships}
                    WHERE term_taxonomy_id = %d
                )";
                $params[] = $series_id;
            }

            // Add season filter if provided and not 'all'
            if ($season_id && $season_id !== 'all') {
                $query .= " AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_season_id' AND meta_value = %d
                )";
                $params[] = intval($season_id);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above with $wpdb->prepare()
            $total = $wpdb->get_var($wpdb->prepare($query, $params));
            if ($total && $total > 0) {
                return floatval($total);
            }
        }

        return 0;
    }

    /**
     * Get average players per tournament with date filtering and season filtering
     *
     * v2.8.14: Fixed to count unique physical players per tournament instead of total entries
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    public function get_average_players_per_tournament($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        global $wpdb;

        // Calculate average of unique player counts per tournament
        $query = "SELECT AVG(player_count) as avg_players
                 FROM (
                     SELECT COUNT(DISTINCT tp.player_id) as player_count
                     FROM {$this->players_table} tp
                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE p.post_status = 'publish'";
        $params = array();

        if ($start_date && $end_date) {
            $query .= " AND p.post_date >= %s AND p.post_date <= %s";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        if ($series_id > 0) {
            $query .= " AND pm.post_id IN (
                SELECT object_id FROM {$wpdb->term_relationships}
                WHERE term_taxonomy_id = %d
            )";
            $params[] = $series_id;
        }

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " AND p.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_season_id' AND meta_value = %d
            )";
            $params[] = intval($season_id);
        }

        $query .= " GROUP BY tp.tournament_id
                 ) as tournament_counts";

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query prepared with $wpdb->prepare()
            $avg = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query used without placeholders
            $avg = $wpdb->get_var($query);
        }

        return floatval($avg ?: 0);
    }

    /**
     * Get average prize pool per tournament with date filtering and season filtering
     *
     * @param string|null $start_date Start date filter
     * @param string|null $end_date End date filter
     * @param int $series_id Series ID filter (deprecated)
     * @param int|string|null $season_id Season ID to filter by
     */
    public function get_average_prize_pool($start_date = null, $end_date = null, $series_id = 0, $season_id = null) {
        $total_tournaments = $this->get_total_tournaments($start_date, $end_date, $series_id, $season_id);
        $total_prize_pool = $this->get_total_prize_pool($start_date, $end_date, $series_id, $season_id);

        return $total_tournaments > 0 ? $total_prize_pool / $total_tournaments : 0;
    }

    /**
     * Get player statistics with pagination and sorting
     */
    public function get_player_statistics($page = 1, $per_page = 50, $search = '', $sort = 'total_winnings', $order = 'DESC') {
        global $wpdb;

        $offset = ($page - 1) * $per_page;

        $query = "SELECT
                    tp.player_id as player_id,
                    COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    AVG(tp.finish_position) as average_finish,
                    MIN(tp.finish_position) as best_finish,
                    SUM(tp.buyins) + SUM(tp.rebuys) + SUM(tp.addons) as total_buyins,
                    SUM(tp.winnings) - (SUM(tp.buyins) + SUM(tp.rebuys) + SUM(tp.addons)) as net_profit,
                    CASE
                        WHEN SUM(tp.buyins) + SUM(tp.rebuys) + SUM(tp.addons) > 0
                        THEN ((SUM(tp.winnings) - (SUM(tp.buyins) + SUM(tp.rebuys) + SUM(tp.addons))) / (SUM(tp.buyins) + SUM(tp.rebuys) + SUM(tp.addons))) * 100
                        ELSE 0
                    END as roi
                  FROM {$this->players_table} tp
                  LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                  LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID";

        $where_clauses = array();
        $params = array();

        if (!empty($search)) {
            $where_clauses[] = "p.post_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " GROUP BY tp.player_id";

        // Add sorting
        $valid_sort_fields = array('total_winnings', 'tournaments_played', 'average_finish', 'best_finish', 'net_profit', 'roi');
        if (in_array($sort, $valid_sort_fields)) {
            $query .= " ORDER BY {$sort} {$order}";
        }

        $query .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared with $wpdb->prepare()
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get total players count for pagination
     */
    public function get_total_players_count() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return intval($wpdb->get_var("SELECT COUNT(DISTINCT player_id) FROM {$this->players_table}"));
    }

    /**
     * Get top players by winnings (NET PROFIT from ROI table)
     */
    public function get_top_players($start_date = null, $end_date = null, $series_id = 0, $limit = 10) {
        global $wpdb;
        $roi_table = $wpdb->prefix . 'poker_player_roi';

        // DEBUG: Check if ROI table exists and has data (for admin users only)
        if (current_user_can('manage_options')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $roi_exists = $wpdb->get_var("SHOW TABLES LIKE '$roi_table'") === $roi_table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $roi_count = $roi_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $roi_table") : 0;
            error_log("Statistics Engine - Top Players Debug - ROI table exists: " . ($roi_exists ? 'YES' : 'NO'));
            error_log("Statistics Engine - Top Players Debug - ROI table rows: " . $roi_count);

            // Check if ROI table has any non-null net_profit values
            if ($roi_exists && $roi_count > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $non_null_count = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit IS NOT NULL");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $positive_profit = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit > 0");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $sample_data = $wpdb->get_results("SELECT player_id, net_profit, total_invested, total_winnings FROM $roi_table LIMIT 5", ARRAY_A);
                error_log("Statistics Engine - Top Players Debug - Non-null net_profit rows: " . $non_null_count);
                error_log("Statistics Engine - Top Players Debug - Positive net_profit rows: " . $positive_profit);
                error_log("Statistics Engine - Top Players Debug - Sample data: " . print_r($sample_data, true));
            }
        }

        // Query poker_player_roi table for NET PROFIT (winnings - total_invested)
        $query = "SELECT
                    roi.player_id,
                    p.post_title as player_name,
                    COUNT(*) as tournaments_played,
                    SUM(roi.net_profit) as total_winnings,
                    MIN(tp.finish_position) as best_finish
                  FROM {$roi_table} roi
                  LEFT JOIN {$this->players_table} tp ON roi.player_id = tp.player_id AND roi.tournament_id = tp.tournament_id
                  LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = roi.player_id AND pm.meta_key = 'player_uuid'
                  LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID";

        $where_clauses = array();
        $params = array();

        if ($start_date && $end_date) {
            // Add date filtering through tournament date in ROI table
            $where_clauses[] = "roi.tournament_date >= %s AND roi.tournament_date <= %s";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " GROUP BY roi.player_id
                   HAVING total_winnings IS NOT NULL
                   ORDER BY total_winnings DESC
                   LIMIT %d";
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared with $wpdb->prepare()
        $results = $wpdb->get_results($wpdb->prepare($query, $params));

        // DEBUG: Log results count
        if (current_user_can('manage_options')) {
            error_log("Statistics Engine - Top Players Debug - Results count: " . count($results));
            if (!empty($results)) {
                error_log("Statistics Engine - Top Players Debug - First result: " . print_r($results[0], true));
            }
        }

        return $results;
    }

    /**
     * Get monthly player growth statistics
     */
    public function get_monthly_player_growth() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return $wpdb->get_results(
            "SELECT
                DATE_FORMAT(p.post_date, '%Y-%m') as month,
                COUNT(DISTINCT tp.player_id) as unique_players,
                COUNT(*) as total_entries
             FROM {$this->players_table} tp
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month DESC"
        );
    }

    /**
     * Get new players count in last N days
     */
    public function get_new_players_count($days = 30) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tp.player_id)
             FROM {$this->players_table} tp
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= %s",
            gmdate('Y-m-d', strtotime("-{$days} days"))
        )));
    }

    /**
     * Get returning players count in last N days
     */
    public function get_returning_players_count($days = 30) {
        global $wpdb;

        // This is a simplified version - returning players are those who played
        // in the period but had their first tournament before this period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tp.player_id)
             FROM {$this->players_table} tp
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= %s
             AND tp.player_id IN (
                 SELECT DISTINCT tp2.player_id
                 FROM {$this->players_table} tp2
                 LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.meta_value = tp2.tournament_id AND pm2.meta_key = 'tournament_uuid'
                 LEFT JOIN {$wpdb->posts} p2 ON pm2.post_id = p2.ID
                 WHERE p2.post_date < %s
             )",
            gmdate('Y-m-d', strtotime("-{$days} days")),
            gmdate('Y-m-d', strtotime("-{$days} days"))
        )));
    }

    /**
     * Get monthly tournament counts
     */
    public function get_monthly_tournament_counts() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return $wpdb->get_results(
            "SELECT
                DATE_FORMAT(post_date, '%Y-%m') as month,
                COUNT(*) as tournament_count,
                AVG(CAST(meta_value AS DECIMAL(10,2))) as avg_players
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_players_count'
             WHERE p.post_type = 'tournament' AND p.post_status = 'publish'
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month DESC"
        );
    }

    /**
     * Get average tournament size growth
     */
    public function get_average_tournament_size_growth() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return $wpdb->get_results(
            "SELECT
                DATE_FORMAT(post_date, '%Y-%m') as month,
                AVG(CAST(meta_value AS DECIMAL(10,2))) as avg_size
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_players_count'
             WHERE p.post_type = 'tournament' AND p.post_status = 'publish'
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             AND meta_value != '' AND meta_value IS NOT NULL
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month DESC"
        );
    }

    /**
     * Get prize pool growth trends
     */
    public function get_prize_pool_growth_trends() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        return $wpdb->get_results(
            "SELECT
                DATE_FORMAT(post_date, '%Y-%m') as month,
                SUM(CAST(meta_value AS DECIMAL(10,2))) as total_prize_pool
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_prize_pool'
             WHERE p.post_type = 'tournament' AND p.post_status = 'publish'
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             AND meta_value != '' AND meta_value IS NOT NULL
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month DESC"
        );
    }

    /**
     * Get player leaderboard data for dashboard
     *
     * @param int $limit Maximum number of players to return
     * @param int|string|null $season_id Season ID to filter by, or 'all' for all seasons, or null for no filter
     */
    public function get_player_leaderboard($limit = 10, $season_id = null) {
        global $wpdb;

        $roi_table = $wpdb->prefix . 'poker_player_roi';

        // DEBUG: Check if ROI table exists and has data (for admin users only)
        if (current_user_can('manage_options')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $roi_exists = $wpdb->get_var("SHOW TABLES LIKE '$roi_table'") === $roi_table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $roi_count = $roi_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $roi_table") : 0;
            error_log("Statistics Engine - Player Leaderboard Debug - ROI table exists: " . ($roi_exists ? 'YES' : 'NO'));
            error_log("Statistics Engine - Player Leaderboard Debug - ROI table rows: " . $roi_count);

            // Check if ROI table has any non-null net_profit values
            if ($roi_exists && $roi_count > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $non_null_count = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit IS NOT NULL");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $positive_profit = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit > 0");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $sample_data = $wpdb->get_results("SELECT player_id, net_profit, total_invested, total_winnings FROM $roi_table LIMIT 5", ARRAY_A);
                error_log("Statistics Engine - Player Leaderboard Debug - Non-null net_profit rows: " . $non_null_count);
                error_log("Statistics Engine - Player Leaderboard Debug - Positive net_profit rows: " . $positive_profit);
                error_log("Statistics Engine - Player Leaderboard Debug - Sample data: " . print_r($sample_data, true));
            }
        }

        // Build base query
        $query = "SELECT
                roi.player_id,
                COUNT(*) as tournaments_played,
                SUM(roi.net_profit) as total_winnings,
                SUM(tp.points) as total_points,
                MIN(tp.finish_position) as best_finish,
                AVG(tp.finish_position) as avg_finish,
                SUM(tp.buyins) as total_buyins,
                MAX(roi.total_winnings) as highest_payout,
                SUM(roi.total_invested) as total_invested,
                SUM(roi.total_winnings) as gross_winnings,
                SUM(CASE WHEN tp.finish_position = 1 THEN 1 ELSE 0 END) as first_place_count,
                SUM(CASE WHEN tp.finish_position = 2 THEN 1 ELSE 0 END) as second_place_count,
                SUM(CASE WHEN tp.finish_position = 3 THEN 1 ELSE 0 END) as third_place_count,
                SUM(tp.hits) as total_hits,
                SUM(CASE
                    WHEN tp.finish_position = (
                        SELECT COUNT(*) + 1
                        FROM {$this->players_table} tp2
                        WHERE tp2.tournament_id = tp.tournament_id AND tp2.winnings > 0
                    )
                    THEN 1 ELSE 0
                END) as bubble_count,
                SUM(CASE
                    WHEN tp.finish_position = (
                        SELECT MAX(finish_position)
                        FROM {$this->players_table} tp3
                        WHERE tp3.tournament_id = tp.tournament_id
                    )
                    THEN 1 ELSE 0
                END) as last_place_count
             FROM {$roi_table} roi
             LEFT JOIN {$this->players_table} tp ON roi.player_id = tp.player_id AND roi.tournament_id = tp.tournament_id";

        $params = array();

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " LEFT JOIN {$wpdb->postmeta} season_meta
                        ON season_meta.meta_key = 'tournament_uuid'
                        AND season_meta.meta_value = roi.tournament_id
                        LEFT JOIN {$wpdb->postmeta} season_filter
                        ON season_filter.post_id = season_meta.post_id
                        AND season_filter.meta_key = '_season_id'
                        WHERE season_filter.meta_value = %d";
            $params[] = intval($season_id);
        }

        $query .= " GROUP BY roi.player_id
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT %d";
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared with $wpdb->prepare()
        $leaderboard = $wpdb->get_results($wpdb->prepare($query, $params));

        // DEBUG: Log results count
        if (current_user_can('manage_options')) {
            error_log("Statistics Engine - Player Leaderboard Debug - Results count: " . count($leaderboard));
            if (!empty($leaderboard)) {
                error_log("Statistics Engine - Player Leaderboard Debug - First result: " . print_r($leaderboard[0], true));
            }
        }

        // Add player names from WordPress posts and calculate season points
        foreach ($leaderboard as &$player) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $player_post = $wpdb->get_row($wpdb->prepare(
                "SELECT p.ID, p.post_title
                 FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                 LIMIT 1",
                $player->player_id
            ));

            if ($player_post) {
                $player->player_name = $player_post->post_title;
                $player->player_post_id = $player_post->ID;
            } else {
                $player->player_name = $player->player_id;
                $player->player_post_id = null;
            }

            // Calculate season points using configured formula (season-aware)
            $player->season_points = $this->get_player_season_points($player->player_id, $season_id);
        }

        // Sort by season points (primary) then total points (secondary)
        usort($leaderboard, function($a, $b) {
            if ($a->season_points != $b->season_points) {
                return $b->season_points <=> $a->season_points; // Descending
            }
            return $b->total_points <=> $a->total_points; // Descending
        });

        return $leaderboard;
    }

    /**
     * Calculate season points for a player using configured formula
     *
     * @param string $player_id Player UUID
     * @param int|string|null $season_id Season ID to filter by, or 'all' for all seasons, or null for no filter
     * @return float Season points
     */
    private function get_player_season_points($player_id, $season_id = null) {
        global $wpdb;

        // Get configured season formula
        $formula_key = get_option('tdwp_active_season_formula', 'best_10');

        // Build query with optional season filter
        $query = "SELECT tp.points, tp.winnings, tp.hits, tp.finish_position
                 FROM {$this->players_table} tp
                 WHERE tp.player_id = %s";
        $params = array($player_id);

        // Add season filter if provided and not 'all'
        if ($season_id && $season_id !== 'all') {
            $query .= " AND tp.tournament_id IN (
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->postmeta} pm2
                    ON pm.post_id = pm2.post_id
                WHERE pm.meta_key = 'tournament_uuid'
                AND pm2.meta_key = '_season_id'
                AND pm2.meta_value = %d
            )";
            $params[] = intval($season_id);
        }

        // Get tournament points for player (filtered by season if specified)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared with $wpdb->prepare()
        $results = $wpdb->get_results($wpdb->prepare($query, $params));

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

                // Concatenate dependencies + main formula into single string for single execution
                // This preserves variables created by assign() statements across all lines
                $combined_formula = '';
                if (!empty($formula_data['dependencies'])) {
                    if (is_array($formula_data['dependencies'])) {
                        $combined_formula = implode("\n", $formula_data['dependencies']) . "\n";
                    } else {
                        // Dependencies is a string
                        $combined_formula = $formula_data['dependencies'] . "\n";
                    }
                }
                $combined_formula .= $formula_data['formula'];

                // Debug: Log combined formula and input data BEFORE execution
                if (current_user_can('manage_options')) {
                    error_log("=== Season Points Debug START ===");
                    error_log("Player: {$player_id}");
                    error_log("Dependencies type: " . gettype($formula_data['dependencies']));
                    error_log("Dependencies content: " . print_r($formula_data['dependencies'], true));
                    error_log("Combined formula:\n" . $combined_formula);
                    error_log("Formula input keys: " . implode(', ', array_keys($formula_input)));
                    error_log("Tournament points array: " . json_encode($tournament_points));
                    error_log("=== Season Points Debug END ===");
                }

                // Execute combined formula once - variables persist throughout execution
                $result = $formula_validator->calculate_formula($combined_formula, $formula_input, 'season');

                // Debug logging for admin users
                if (current_user_can('manage_options')) {
                    error_log("Season Points Calculation - Player: {$player_id}");
                    error_log("  Season ID: " . ($season_id ?? 'all'));
                    error_log("  Tournaments played: {$tournaments_played}");
                    error_log("  Points array: " . json_encode($tournament_points));
                    error_log("  Formula result: " . ($result['success'] ? $result['result'] : 'FAILED - ' . $result['error']));
                }

                if (isset($result['success']) && $result['success']) {
                    return floatval($result['result']);
                } else {
                    // Log formula failure
                    if (current_user_can('manage_options')) {
                        error_log("Season Points Formula FAILED - falling back to sum of all points");
                    }
                }
            }
        }

        // Fallback: sum all points for the season (already filtered by season in query)
        return $total_points;
    }

    /**
     * Get player participation trends
     */
    public function get_player_participation_trends($days = 30) {
        global $wpdb;

        // Get daily participation for last N days
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(p.post_date) as date,
                COUNT(DISTINCT tp.player_id) as unique_players,
                COUNT(*) as total_entries,
                SUM(tp.winnings) as total_winnings
             FROM {$this->players_table} tp
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= %s
             GROUP BY DATE(p.post_date)
             ORDER BY date DESC
             LIMIT %d",
            gmdate('Y-m-d', strtotime("-{$days} days")),
            $days
        ));

        return $trends;
    }

    /**
     * Enhanced Debug function to check complete data pipeline health
     */
    public function debug_statistics() {
        $debug_info = array();

        // Check database tables
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $debug_info['stats_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$this->stats_table}'") ? true : false;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $debug_info['players_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$this->players_table}'") ? true : false;

        // Check raw data counts
        $debug_info['raw_tournament_count'] = wp_count_posts('tournament')->publish;
        $debug_info['raw_series_count'] = wp_count_posts('tournament_series')->publish;
        $debug_info['raw_player_count'] = wp_count_posts('player')->publish;

        // **CRITICAL**: Check players table data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $total_players_records = $wpdb->get_var("SELECT COUNT(*) FROM {$this->players_table}");
        $debug_info['players_table_records'] = intval($total_players_records);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $debug_info['raw_players_in_db'] = $wpdb->get_var("SELECT COUNT(DISTINCT player_id) FROM {$this->players_table}");

        // Check tournament UUIDs and their player data
        $debug_info['tournament_uuid_analysis'] = array();
        $tournaments_with_uuid = 0;
        $tournaments_with_players = 0;

        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($tournaments as $tournament) {
            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
            $tournament_uuid_alt = get_post_meta($tournament->ID, '_tournament_uuid', true);

            if ($tournament_uuid || $tournament_uuid_alt) {
                $tournaments_with_uuid++;
                $uuid_to_check = $tournament_uuid ?: $tournament_uuid_alt;

                // Check if this tournament has players in the data mart
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $player_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->players_table} WHERE tournament_id = %s",
                    $uuid_to_check
                ));

                if ($player_count > 0) {
                    $tournaments_with_players++;
                }

                $debug_info['tournament_uuid_analysis'][] = array(
                    'tournament_id' => $tournament->ID,
                    'tournament_title' => $tournament->post_title,
                    'uuid_used' => $uuid_to_check,
                    'players_in_data_mart' => intval($player_count),
                    'uuid_field' => $tournament_uuid ? 'tournament_uuid' : '_tournament_uuid'
                );
            }
        }

        $debug_info['tournaments_with_uuid'] = $tournaments_with_uuid;
        $debug_info['tournaments_with_players'] = $tournaments_with_players;

        // Check different possible prize pool field names
        $debug_info['prize_pool_fields'] = array();
        $possible_prize_fields = array('_prize_pool', 'prize_pool', '_tournament_prize_pool', 'tournament_prize_pool');
        foreach ($possible_prize_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $field
            ));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $field
            ));
            $debug_info['prize_pool_fields'][$field] = array(
                'count' => intval($count),
                'total' => floatval($total ?: 0)
            );
        }

        // Check different possible UUID field names
        $debug_info['uuid_fields'] = array();
        $possible_uuid_fields = array('tournament_uuid', '_tournament_uuid', 'uuid', '_uuid');
        foreach ($possible_uuid_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $field
            ));
            $debug_info['uuid_fields'][$field] = intval($count);
        }

        // Check player UUID fields
        $debug_info['player_uuid_fields'] = array();
        $possible_player_uuid_fields = array('player_uuid', '_player_uuid', 'uuid', '_uuid');
        foreach ($possible_player_uuid_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $field
            ));
            $debug_info['player_uuid_fields'][$field] = intval($count);
        }

        // Use the best prize pool field
        $best_prize_field = '_prize_pool';
        $best_prize_total = 0;
        foreach ($debug_info['prize_pool_fields'] as $field => $data) {
            if ($data['total'] > $best_prize_total) {
                $best_prize_total = $data['total'];
                $best_prize_field = $field;
            }
        }
        $debug_info['raw_prize_pool'] = $best_prize_total;
        $debug_info['best_prize_field'] = $best_prize_field;

        // Check statistics table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $debug_info['stats_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->stats_table}");
        $debug_info['last_updated'] = $this->get_last_updated();

        // Get current statistics
        $debug_info['current_stats'] = $this->get_dashboard_statistics();

        // **DIAGNOSTIC SUMMARY**
        $debug_info['diagnostic_summary'] = array(
            'critical_issue' => $debug_info['players_table_records'] == 0,
            'data_pipeline_broken' => $tournaments_with_uuid > 0 && $tournaments_with_players == 0,
            'uuid_field_issue' => !($debug_info['uuid_fields']['tournament_uuid'] > 0 || $debug_info['uuid_fields']['_tournament_uuid'] > 0),
            'player_field_issue' => !($debug_info['player_uuid_fields']['player_uuid'] > 0 || $debug_info['player_uuid_fields']['_player_uuid'] > 0)
        );

        error_log("Poker Statistics Debug: " . print_r($debug_info, true));
        return $debug_info;
    }

    // ========================================
    // **PHASE 2.1: CORE FINANCIAL DATA INFRASTRUCTURE**
    // ========================================

    /**
     * Calculate comprehensive financial summary
     */
    private function calculate_financial_summary() {
        global $wpdb;

        // Get financial summary from enhanced data mart
        $financial_table = $wpdb->prefix . 'poker_financial_summary';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $financial_summary = $wpdb->get_row(
            "SELECT
                SUM(tournament_revenue) as total_revenue,
                SUM(tournament_costs) as total_costs,
                SUM(tournament_profit) as total_profit,
                AVG(tournament_profit) as avg_profit_per_tournament,
                AVG(tournament_roi_percentage) as avg_roi,
                AVG(prize_pool_efficiency) as avg_prize_efficiency
             FROM {$financial_table}
             WHERE tournament_revenue > 0"
        );

        if ($financial_summary) {
            $total_revenue = floatval($financial_summary->total_revenue ?: 0);
            $total_costs = floatval($financial_summary->total_costs ?: 0);
            $total_profit = floatval($financial_summary->total_profit ?: 0);

            // Calculate profit margin
            $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

            // Store core financial metrics
            $this->update_statistic('total_revenue', $total_revenue, 'sum');
            $this->update_statistic('total_costs', $total_costs, 'sum');
            $this->update_statistic('total_profit', $total_profit, 'sum');
            $this->update_statistic('average_profit_per_tournament', floatval($financial_summary->avg_profit_per_tournament ?: 0), 'average');
            $this->update_statistic('profit_margin_percentage', $profit_margin, 'average');
            $this->update_statistic('average_roi_percentage', floatval($financial_summary->avg_roi ?: 0), 'average');
            $this->update_statistic('prize_pool_efficiency', floatval($financial_summary->avg_prize_efficiency ?: 0), 'average');
        }

        // Calculate derived financial metrics
        $this->calculate_rake_metrics();
        $this->calculate_prize_pool_metrics();
        $this->calculate_profitability_metrics();
        $this->calculate_growth_metrics();
        $this->calculate_tournament_type_profitability();
    }

    /**
     * Calculate rake collection metrics
     */
    private function calculate_rake_metrics() {
        global $wpdb;

        // Calculate total rake from tournament data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $total_rake = $wpdb->get_var(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_rake_amount', 'rake_amount') AND meta_value != ''"
        );

        $total_rake = floatval($total_rake ?: 0);
        $tournament_count = $this->get_total_tournaments();
        $avg_rake_per_tournament = $tournament_count > 0 ? $total_rake / $tournament_count : 0;

        $this->update_statistic('total_rake_collected', $total_rake, 'sum');
        $this->update_statistic('average_rake_per_tournament', $avg_rake_per_tournament, 'average');
    }

    /**
     * Calculate prize pool efficiency metrics
     */
    private function calculate_prize_pool_metrics() {
        global $wpdb;

        // Calculate buy-in to prize pool ratio
        $total_buy_ins = $this->get_total_entries();
        $total_prize_pool = $this->get_total_prize_pool();

        $buy_in_to_prize_pool_ratio = $total_buy_ins > 0 ? ($total_prize_pool / $total_buy_ins) * 100 : 0;

        $this->update_statistic('buy_in_to_prize_pool_ratio', $buy_in_to_prize_pool_ratio, 'average');
    }

    /**
     * Calculate profitability metrics by tournament type
     */
    private function calculate_profitability_metrics() {
        global $wpdb;

        $financial_table = $wpdb->prefix . 'poker_financial_summary';

        // Find most profitable tournament type
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $most_profitable = $wpdb->get_row(
            "SELECT
                p.post_title as tournament_type,
                AVG(fs.tournament_profit) as avg_profit,
                AVG(fs.tournament_roi_percentage) as avg_roi,
                COUNT(*) as tournament_count
             FROM {$financial_table} fs
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = fs.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'tournament_type' AND fs.tournament_profit > 0
             GROUP BY t.term_id, t.name
             ORDER BY avg_profit DESC
             LIMIT 1"
        );

        if ($most_profitable) {
            $this->update_statistic('most_profitable_tournament_type', $most_profitable->tournament_type ?: 'Unknown', 'latest');
        }
    }

    /**
     * Calculate growth metrics (30-day comparison)
     */
    private function calculate_growth_metrics() {
        global $wpdb;

        $revenue_analytics_table = $wpdb->prefix . 'poker_revenue_analytics';

        // Get current month and previous month data
        $current_month = gmdate('Y-m');
        $previous_month = gmdate('Y-m', strtotime('-1 month'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT total_revenue, total_profit FROM {$revenue_analytics_table} WHERE analytics_period = %s",
            $current_month
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $previous_data = $wpdb->get_row($wpdb->prepare(
            "SELECT total_revenue, total_profit FROM {$revenue_analytics_table} WHERE analytics_period = %s",
            $previous_month
        ));

        if ($current_data && $previous_data) {
            $revenue_growth = $previous_data->total_revenue > 0 ?
                (($current_data->total_revenue - $previous_data->total_revenue) / $previous_data->total_revenue) * 100 : 0;

            $profit_growth = $previous_data->total_profit > 0 ?
                (($current_data->total_profit - $previous_data->total_profit) / $previous_data->total_profit) * 100 : 0;

            $this->update_statistic('revenue_growth_rate_30d', $revenue_growth, 'average');
            $this->update_statistic('profit_growth_rate_30d', $profit_growth, 'average');
        }
    }

    /**
     * Calculate tournament type profitability analysis
     */
    private function calculate_tournament_type_profitability() {
        global $wpdb;

        // This is already implemented in calculate_profitability_metrics()
        // Keeping this method for future expansion
    }

    /**
     * **PHASE 2.1 Helper Methods**
     */

    /**
     * Get comprehensive financial summary for dashboard
     */
    public function get_financial_summary($days = 30) {
        global $wpdb;

        $financial_table = $wpdb->prefix . 'poker_financial_summary';
        $revenue_analytics_table = $wpdb->prefix . 'poker_revenue_analytics';

        // Recent financial performance
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $recent_data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(p.post_date) as date,
                fs.tournament_revenue,
                fs.tournament_costs,
                fs.tournament_profit,
                fs.tournament_roi_percentage,
                p.post_title as tournament_name
             FROM {$financial_table} fs
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = fs.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= %s
             ORDER BY p.post_date DESC
             LIMIT %d",
            gmdate('Y-m-d', strtotime("-{$days} days")),
            $days
        ));

        // Monthly trends
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $monthly_trends = $wpdb->get_results(
            "SELECT
                analytics_period,
                total_revenue,
                total_costs,
                total_profit,
                profit_margin_percentage,
                total_tournaments
             FROM {$revenue_analytics_table}
             ORDER BY analytics_period DESC
             LIMIT 12"
        );

        return array(
            'recent_performance' => $recent_data,
            'monthly_trends' => $monthly_trends,
            'summary_metrics' => array(
                'total_revenue' => $this->get_statistic('total_revenue'),
                'total_costs' => $this->get_statistic('total_costs'),
                'total_profit' => $this->get_statistic('total_profit'),
                'profit_margin' => $this->get_statistic('profit_margin_percentage'),
                'average_roi' => $this->get_statistic('average_roi_percentage')
            )
        );
    }

    /**
     * Process tournament financial data during import
     */
    public function process_tournament_financial_data($tournament_id, $tournament_data) {
        global $wpdb;

        $financial_table = $wpdb->prefix . 'poker_financial_summary';
        $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);

        if (!$tournament_uuid) {
            return false;
        }

        // Calculate financial metrics from tournament data
        $buy_in_amount = floatval($tournament_data['buy_in'] ?? 0);
        $players_count = intval($tournament_data['players'] ?? 0);
        $rebuys_count = intval($tournament_data['rebuys'] ?? 0);
        $addon_count = intval($tournament_data['addons'] ?? 0);

        // Calculate total revenue
        $total_revenue = ($buy_in_amount * $players_count) +
                        ($buy_in_amount * $rebuys_count * 0.5) +  // Assuming rebuys are 50% of buy-in
                        ($buy_in_amount * $addon_count * 0.3);     // Assuming addons are 30% of buy-in

        // Calculate costs (default estimates - can be overridden)
        $venue_cost = $total_revenue * 0.15;  // 15% for venue
        $staff_cost = $total_revenue * 0.10;   // 10% for staff
        $equipment_cost = $total_revenue * 0.05; // 5% for equipment
        $marketing_cost = $total_revenue * 0.05;  // 5% for marketing
        $overhead_cost = $total_revenue * 0.05;   // 5% overhead

        $total_costs = $venue_cost + $staff_cost + $equipment_cost + $marketing_cost + $overhead_cost;
        $total_profit = $total_revenue - $total_costs;
        $roi_percentage = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

        // Calculate prize pool efficiency
        $prize_pool = floatval($tournament_data['prize_pool'] ?? 0);
        $prize_pool_efficiency = $total_revenue > 0 ? ($prize_pool / $total_revenue) * 100 : 0;

        // Store financial summary
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $result = $wpdb->replace(
            $financial_table,
            array(
                'tournament_id' => $tournament_uuid,
                'tournament_revenue' => $total_revenue,
                'tournament_costs' => $total_costs,
                'tournament_profit' => $total_profit,
                'tournament_roi_percentage' => $roi_percentage,
                'prize_pool_efficiency' => $prize_pool_efficiency,
                'buy_in_amount' => $buy_in_amount,
                'currency_code' => $tournament_data['currency'] ?? 'USD',
                'currency_conversion_rate' => 1.0,
                'financial_data' => json_encode(array(
                    'players_count' => $players_count,
                    'rebuys_count' => $rebuys_count,
                    'addon_count' => $addon_count,
                    'venue_cost' => $venue_cost,
                    'staff_cost' => $staff_cost,
                    'equipment_cost' => $equipment_cost,
                    'marketing_cost' => $marketing_cost,
                    'overhead_cost' => $overhead_cost
                )),
                'updated_at' => current_time('mysql')
            ),
            array(
                '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%f', '%s', '%s'
            )
        );

        if ($result) {
            // Also update monthly analytics
            $this->update_monthly_analytics($tournament_uuid, $total_revenue, $total_costs, $total_profit, $players_count);

            // Process player ROI data
            $this->process_player_roi_data($tournament_uuid, $tournament_data);
        }

        return $result !== false;
    }

    /**
     * Update monthly revenue analytics
     */
    private function update_monthly_analytics($tournament_uuid, $revenue, $costs, $profit, $players_count) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'poker_revenue_analytics';
        $current_period = gmdate('Y-m');

        // Check if record exists for current month
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$analytics_table} WHERE analytics_period = %s",
            $current_period
        ));

        if ($existing) {
            // Calculate new totals safely
            $new_total_tournaments = $existing->total_tournaments + 1;
            $new_total_revenue = $existing->total_revenue + $revenue;
            $new_total_players = $existing->total_players + $players_count;
            $new_total_profit = $existing->total_profit + $profit;

            // Calculate averages safely, preventing division by zero
            $avg_profit_per_tournament = $new_total_tournaments > 0 ? $new_total_profit / $new_total_tournaments : 0;
            $avg_revenue_per_player = $new_total_players > 0 ? $new_total_revenue / $new_total_players : 0;
            $profit_margin_percentage = $new_total_revenue > 0 ? ($new_total_profit / $new_total_revenue) * 100 : 0;

            // Update existing record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $wpdb->update(
                $analytics_table,
                array(
                    'total_tournaments' => $new_total_tournaments,
                    'total_revenue' => $new_total_revenue,
                    'total_costs' => $existing->total_costs + $costs,
                    'total_profit' => $new_total_profit,
                    'average_profit_per_tournament' => $avg_profit_per_tournament,
                    'total_players' => $new_total_players,
                    'average_revenue_per_player' => $avg_revenue_per_player,
                    'profit_margin_percentage' => $profit_margin_percentage,
                    'updated_at' => current_time('mysql')
                ),
                array('analytics_period' => $current_period),
                array('%s'),
                array('%d', '%f', '%f', '%f', '%f', '%d', '%f', '%f', '%s')
            );
        } else {
            // Insert new record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $wpdb->insert(
                $analytics_table,
                array(
                    'analytics_period' => $current_period,
                    'total_tournaments' => 1,
                    'total_revenue' => $revenue,
                    'total_costs' => $costs,
                    'total_profit' => $profit,
                    'average_profit_per_tournament' => $profit,
                    'total_players' => $players_count,
                    'average_revenue_per_player' => $players_count > 0 ? $revenue / $players_count : 0,
                    'profit_margin_percentage' => ($revenue > 0) ? ($profit / $revenue) * 100 : 0,
                    'currency_code' => 'USD'
                ),
                array('%s', '%d', '%f', '%f', '%f', '%f', '%d', '%f', '%f', '%s')
            );
        }
    }

    /**
     * Process player ROI data for a tournament
     */
    public function process_player_roi_data($tournament_uuid, $tournament_data) {
        global $wpdb;

        $player_roi_table = $wpdb->prefix . 'poker_player_roi';

        // Get tournament post ID from UUID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $tournament_post = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = 'tournament_uuid' AND meta_value = %s LIMIT 1",
            $tournament_uuid
        ));
        $tournament_id = $tournament_post ? $tournament_post->post_id : 0;
        $tournament_date = $tournament_id ? get_post_meta($tournament_id, '_tournament_date', true) : gmdate('Y-m-d');
        if (!$tournament_date) {
            $tournament_date = gmdate('Y-m-d');
        }

        // Get fee profiles from tournament data
        $fee_profiles = array();
        if (!empty($tournament_data['financial']['fee_profiles']) && is_array($tournament_data['financial']['fee_profiles'])) {
            $fee_profiles = $tournament_data['financial']['fee_profiles'];
        }

        // Fallback buy-in amount for backward compatibility
        $fallback_buy_in = floatval($tournament_data['buy_in'] ?? 0);

        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            foreach ($tournament_data['players'] as $player_data) {
                $player_uuid = $player_data['uuid'] ?? '';
                $winnings = floatval($player_data['winnings'] ?? 0);
                $finish_position = intval($player_data['finish_position'] ?? 0);

                if ($player_uuid) {
                    // Calculate total invested by summing FeeProfile costs for each buyin
                    $total_invested = 0;

                    if (!empty($player_data['buyins']) && is_array($player_data['buyins'])) {
                        // Loop through each buyin and lookup its FeeProfile cost
                        foreach ($player_data['buyins'] as $buyin) {
                            $profile_name = $buyin['profile'] ?? 'Standard';

                            // Look up cost from FeeProfile
                            if (isset($fee_profiles[$profile_name]['fee'])) {
                                $total_invested += floatval($fee_profiles[$profile_name]['fee']);
                            } else {
                                // Fallback: use default buy-in amount
                                $total_invested += $fallback_buy_in;
                            }
                        }
                    } else {
                        // Fallback: if no buyins array, use default buy-in × 1
                        $total_invested = $fallback_buy_in;
                    }

                    $net_profit = $winnings - $total_invested;
                    $roi_percentage = $total_invested > 0 ? ($net_profit / $total_invested) * 100 : 0;

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                    $wpdb->replace(
                        $player_roi_table,
                        array(
                            'player_id' => $player_uuid,
                            'tournament_id' => $tournament_uuid,
                            'total_invested' => $total_invested,
                            'total_winnings' => $winnings,
                            'net_profit' => $net_profit,
                            'roi_percentage' => $roi_percentage,
                            'finish_position' => $finish_position,
                            'tournament_date' => $tournament_date
                        ),
                        array('%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s')
                    );
                }
            }
        }
    }

    /**
     * Get player ROI statistics
     */
    public function get_player_roi_statistics($player_id = null, $limit = 10) {
        global $wpdb;

        $player_roi_table = $wpdb->prefix . 'poker_player_roi';

        if ($player_id) {
            // Get ROI data for specific player
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$player_roi_table} WHERE player_id = %s ORDER BY tournament_date DESC",
                $player_id
            ));
        } else {
            // Get top ROI performers
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            return $wpdb->get_results($wpdb->prepare(
                "SELECT
                    player_id,
                    COUNT(*) as tournaments_played,
                    SUM(total_invested) as total_invested,
                    SUM(total_winnings) as total_winnings,
                    SUM(net_profit) as total_net_profit,
                    AVG(roi_percentage) as average_roi,
                    MAX(roi_percentage) as best_roi
                 FROM {$player_roi_table}
                 GROUP BY player_id
                 HAVING tournaments_played >= 3
                 ORDER BY average_roi DESC
                 LIMIT %d",
                $limit
            ));
        }
    }

    /**
     * Get tournament cost analysis
     */
    public function get_tournament_cost_analysis($tournament_id = null) {
        global $wpdb;

        $costs_table = $wpdb->prefix . 'poker_tournament_costs';

        if ($tournament_id) {
            $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if ($tournament_uuid) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$costs_table} WHERE tournament_id = %s ORDER BY cost_amount DESC",
                    $tournament_uuid
                ));
            }
        } else {
            // Get cost analysis across all tournaments
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            return $wpdb->get_results(
                "SELECT
                    cost_category,
                    COUNT(*) as tournament_count,
                    SUM(cost_amount) as total_cost,
                    AVG(cost_amount) as average_cost
                 FROM {$costs_table}
                 GROUP BY cost_category
                 ORDER BY total_cost DESC"
            );
        }

        return array();
    }

    /**
     * **v2.4.34: ONE-TIME MIGRATION** - Populate ROI table from existing tournament data
     *
     * This method is called once during plugin upgrade from v2.4.33 to v2.4.34.
     * It populates the poker_player_roi table by reading existing tournament data
     * from the poker_tournament_players table and WordPress post meta.
     */
    public function migrate_populate_roi_table() {
        global $wpdb;

        $player_roi_table = $wpdb->prefix . 'poker_player_roi';
        $start_time = microtime(true);
        $total_records = 0;
        $processed_tournaments = 0;

        error_log("ROI Migration: Starting migration to populate poker_player_roi table");

        // Get all tournaments with player data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $tournaments = $wpdb->get_results(
            "SELECT DISTINCT tp.tournament_id
             FROM {$this->players_table} tp"
        );

        error_log("ROI Migration: Found " . count($tournaments) . " tournaments with player data");

        foreach ($tournaments as $tournament) {
            $tournament_uuid = $tournament->tournament_id;

            // Get tournament post ID from meta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $tournament_post = $wpdb->get_row($wpdb->prepare(
                "SELECT post_id
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = 'tournament_uuid' AND meta_value = %s
                 LIMIT 1",
                $tournament_uuid
            ));

            if (!$tournament_post) {
                error_log("ROI Migration: Skipping tournament {$tournament_uuid} - no post found");
                continue;
            }

            $tournament_id = $tournament_post->post_id;

            // Get tournament buy-in amount from post meta
            $buy_in_amount = floatval(get_post_meta($tournament_id, '_buy_in', true));
            if ($buy_in_amount == 0) {
                // Try alternative field names
                $buy_in_amount = floatval(get_post_meta($tournament_id, 'buy_in', true));
            }
            if ($buy_in_amount == 0) {
                $buy_in_amount = floatval(get_post_meta($tournament_id, '_tournament_buy_in', true));
            }

            // Get tournament date
            $tournament_date = get_post_meta($tournament_id, '_tournament_date', true);
            if (!$tournament_date) {
                $tournament_date = get_the_gmdate('Y-m-d', $tournament_id);
            }

            // Get all players for this tournament
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            $players = $wpdb->get_results($wpdb->prepare(
                "SELECT player_id, winnings, buyins, rebuys, addons, finish_position
                 FROM {$this->players_table}
                 WHERE tournament_id = %s",
                $tournament_uuid
            ));

            // Process each player
            foreach ($players as $player) {
                $player_uuid = $player->player_id;
                $winnings = floatval($player->winnings);
                $finish_position = intval($player->finish_position);
                $buyins = intval($player->buyins) ?: 1;  // Default to 1 if 0
                $rebuys = intval($player->rebuys);
                $addons = intval($player->addons);

                // Calculate total invested (per buyin events)
                // Buy-in amounts are stored at face value in localized currency
                if ($buy_in_amount > 0) {
                    $total_invested = $buy_in_amount * ($buyins + $rebuys + $addons);
                } else {
                    // Fallback: estimate $20 per entry
                    $total_invested = 20.0 * ($buyins + $rebuys + $addons);
                }

                $net_profit = $winnings - $total_invested;
                $roi_percentage = $total_invested > 0 ? ($net_profit / $total_invested) * 100 : 0;

                // Insert ROI record
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $result = $wpdb->replace(
                    $player_roi_table,
                    array(
                        'player_id' => $player_uuid,
                        'tournament_id' => $tournament_uuid,
                        'total_invested' => $total_invested,
                        'total_winnings' => $winnings,
                        'net_profit' => $net_profit,
                        'roi_percentage' => $roi_percentage,
                        'finish_position' => $finish_position,
                        'tournament_date' => $tournament_date
                    ),
                    array('%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s')
                );

                if ($result !== false) {
                    $total_records++;
                }
            }

            $processed_tournaments++;

            // Log progress every 10 tournaments
            if ($processed_tournaments % 10 == 0) {
                error_log("ROI Migration: Processed {$processed_tournaments} tournaments, created {$total_records} ROI records");
            }
        }

        $elapsed_time = round((microtime(true) - $start_time) * 1000, 2);
        error_log("ROI Migration: COMPLETE - Processed {$processed_tournaments} tournaments, created {$total_records} ROI records in {$elapsed_time}ms");

        return array(
            'success' => true,
            'tournaments_processed' => $processed_tournaments,
            'records_created' => $total_records,
            'elapsed_ms' => $elapsed_time
        );
    }
}