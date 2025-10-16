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
     * Get a single statistic value
     */
    public function get_statistic($name) {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT stat_value FROM {$this->stats_table} WHERE stat_name = %s LIMIT 1",
            $name
        ));

        return $value !== null ? floatval($value) : 0;
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
        return $wpdb->query("TRUNCATE TABLE {$this->stats_table}");
    }

  
    /**
     * Get unique players count
     */
    private function get_unique_players() {
        global $wpdb;
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

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'tournament' AND post_status = 'publish'
             AND post_date >= %s",
            date('Y-m-d', strtotime("-{$days} days"))
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
            $field_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $uuid_field
            ));

            if ($field_count && $field_count > 0) {
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
                    date('Y-m-d', strtotime("-{$days} days"))
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

        $last_updated = $wpdb->get_var("SELECT MAX(last_updated) FROM {$this->stats_table}");

        return $last_updated ? $last_updated : false;
    }

    /**
     * Get statistics for dashboard
     */
    public function get_dashboard_statistics() {
        // Auto-calculate if statistics table is empty
        if (!$this->has_statistics()) {
            error_log("Poker Statistics: No statistics found, calculating initial statistics");
            $this->calculate_all_statistics();
        }

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
            wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
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
        $total = $wpdb->get_var("SELECT SUM(buyins) FROM {$this->players_table}");
        return intval($total ?: 0);
    }

    /**
     * Get total cashouts (players who won money)
     */
    private function get_total_cashouts() {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->players_table} WHERE winnings > 0");
        return intval($count ?: 0);
    }

    /**
     * Get total payouts across all tournaments
     */
    private function get_total_payouts() {
        global $wpdb;
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
            $max = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $field
            ));
            if ($max && $max > 0) {
                return floatval($max);
            }
        }

        // Fallback: calculate from players table
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
        $max = $wpdb->get_var("SELECT MAX(winnings) FROM {$this->players_table}");
        return floatval($max ?: 0);
    }

    /**
     * Get average finish position across all players
     */
    private function get_average_finish_position() {
        global $wpdb;
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
     * Get total tournaments with date filtering
     */
    public function get_total_tournaments($start_date = null, $end_date = null, $series_id = 0) {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tournament' AND post_status = 'publish'";
        $params = array();

        if ($start_date && $end_date) {
            $query .= " AND post_date >= %s AND post_date <= %s";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        if (!empty($params)) {
            $count = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            $count = $wpdb->get_var($query);
        }

        return intval($count);
    }

    /**
     * Get total players with date filtering
     */
    public function get_total_players($start_date = null, $end_date = null, $series_id = 0) {
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

        if (!empty($params)) {
            $count = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            $count = $wpdb->get_var($query);
        }

        return intval($count);
    }

    /**
     * Get total prize pool with date filtering
     */
    public function get_total_prize_pool($start_date = null, $end_date = null, $series_id = 0) {
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

            $total = $wpdb->get_var($wpdb->prepare($query, $params));
            if ($total && $total > 0) {
                return floatval($total);
            }
        }

        return 0;
    }

    /**
     * Get average players per tournament with date filtering
     */
    public function get_average_players_per_tournament($start_date = null, $end_date = null, $series_id = 0) {
        $total_tournaments = $this->get_total_tournaments($start_date, $end_date, $series_id);
        $total_players = $this->get_total_players($start_date, $end_date, $series_id);

        return $total_tournaments > 0 ? $total_players / $total_tournaments : 0;
    }

    /**
     * Get average prize pool per tournament with date filtering
     */
    public function get_average_prize_pool($start_date = null, $end_date = null, $series_id = 0) {
        $total_tournaments = $this->get_total_tournaments($start_date, $end_date, $series_id);
        $total_prize_pool = $this->get_total_prize_pool($start_date, $end_date, $series_id);

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

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get total players count for pagination
     */
    public function get_total_players_count() {
        global $wpdb;
        return intval($wpdb->get_var("SELECT COUNT(DISTINCT player_id) FROM {$this->players_table}"));
    }

    /**
     * Get top players by winnings
     */
    public function get_top_players($start_date = null, $end_date = null, $series_id = 0, $limit = 10) {
        global $wpdb;

        $query = "SELECT
                    tp.player_id as player_id,
                    p.post_title as player_name,
                    SUM(tp.winnings) as total_winnings,
                    COUNT(*) as tournaments_played,
                    MIN(tp.finish_position) as best_finish
                  FROM {$this->players_table} tp
                  LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                  LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID";

        $where_clauses = array();
        $params = array();

        if ($start_date && $end_date) {
            // Add date filtering through tournament UUID lookup
            $where_clauses[] = "pm_other.post_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'tournament'
                AND post_date >= %s AND post_date <= %s
            )";
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " GROUP BY tp.player_id
                   ORDER BY total_winnings DESC
                   LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get monthly player growth statistics
     */
    public function get_monthly_player_growth() {
        global $wpdb;

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

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tp.player_id)
             FROM {$this->players_table} tp
             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.tournament_id AND pm.meta_key = 'tournament_uuid'
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_date >= %s",
            date('Y-m-d', strtotime("-{$days} days"))
        )));
    }

    /**
     * Get returning players count in last N days
     */
    public function get_returning_players_count($days = 30) {
        global $wpdb;

        // This is a simplified version - returning players are those who played
        // in the period but had their first tournament before this period
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
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d', strtotime("-{$days} days"))
        )));
    }

    /**
     * Get monthly tournament counts
     */
    public function get_monthly_tournament_counts() {
        global $wpdb;

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
     */
    public function get_player_leaderboard($limit = 10) {
        global $wpdb;

        $roi_table = $wpdb->prefix . 'poker_player_roi';

        // Query poker_player_roi table for NET PROFIT (winnings - total_invested)
        $leaderboard = $wpdb->get_results($wpdb->prepare(
            "SELECT
                roi.player_id,
                COUNT(*) as tournaments_played,
                SUM(roi.net_profit) as total_winnings,
                SUM(tp.points) as total_points,
                MIN(tp.finish_position) as best_finish,
                AVG(tp.finish_position) as avg_finish,
                SUM(tp.buyins) as total_buyins,
                MAX(roi.total_winnings) as highest_payout,
                SUM(roi.total_invested) as total_invested,
                SUM(roi.total_winnings) as gross_winnings
             FROM {$roi_table} roi
             LEFT JOIN {$this->players_table} tp ON roi.player_id = tp.player_id AND roi.tournament_id = tp.tournament_id
             GROUP BY roi.player_id
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT %d",
            $limit
        ));

        // Add player names from WordPress posts
        foreach ($leaderboard as &$player) {
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
        }

        return $leaderboard;
    }

    /**
     * Get player participation trends
     */
    public function get_player_participation_trends($days = 30) {
        global $wpdb;

        // Get daily participation for last N days
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
            date('Y-m-d', strtotime("-{$days} days")),
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
        $debug_info['stats_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$this->stats_table}'") ? true : false;
        $debug_info['players_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$this->players_table}'") ? true : false;

        // Check raw data counts
        $debug_info['raw_tournament_count'] = wp_count_posts('tournament')->publish;
        $debug_info['raw_series_count'] = wp_count_posts('tournament_series')->publish;
        $debug_info['raw_player_count'] = wp_count_posts('player')->publish;

        // **CRITICAL**: Check players table data
        $total_players_records = $wpdb->get_var("SELECT COUNT(*) FROM {$this->players_table}");
        $debug_info['players_table_records'] = intval($total_players_records);
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
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $field
            ));
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
        $current_month = date('Y-m');
        $previous_month = date('Y-m', strtotime('-1 month'));

        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT total_revenue, total_profit FROM {$revenue_analytics_table} WHERE analytics_period = %s",
            $current_month
        ));

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
            date('Y-m-d', strtotime("-{$days} days")),
            $days
        ));

        // Monthly trends
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
        $current_period = date('Y-m');

        // Check if record exists for current month
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
    private function process_player_roi_data($tournament_uuid, $tournament_data) {
        global $wpdb;

        $player_roi_table = $wpdb->prefix . 'poker_player_roi';
        $tournament_date = get_post_meta(get_the_ID(), '_tournament_date', true) ?: date('Y-m-d');
        $buy_in_amount = floatval($tournament_data['buy_in'] ?? 0);

        if (!empty($tournament_data['players']) && is_array($tournament_data['players'])) {
            foreach ($tournament_data['players'] as $player_data) {
                $player_uuid = $player_data['uuid'] ?? '';
                $winnings = floatval($player_data['winnings'] ?? 0);
                $finish_position = intval($player_data['finish_position'] ?? 0);
                $rebuys = intval($player_data['rebuys'] ?? 0);
                $addons = intval($player_data['addons'] ?? 0);

                if ($player_uuid) {
                    $total_invested = $buy_in_amount + ($buy_in_amount * $rebuys * 0.5) + ($buy_in_amount * $addons * 0.3);
                    $net_profit = $winnings - $total_invested;
                    $roi_percentage = $total_invested > 0 ? ($net_profit / $total_invested) * 100 : 0;

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
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$player_roi_table} WHERE player_id = %s ORDER BY tournament_date DESC",
                $player_id
            ));
        } else {
            // Get top ROI performers
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
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$costs_table} WHERE tournament_id = %s ORDER BY cost_amount DESC",
                    $tournament_uuid
                ));
            }
        } else {
            // Get cost analysis across all tournaments
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
}