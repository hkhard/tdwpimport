<?php
/**
 * Series Standings Calculator Class
 *
 * Calculates cumulative series standings with tie-breaker logic
 * and supports customizable formulas for season calculations
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Series_Standings_Calculator
{

    /**
     * Calculate series standings with tie-breakers
     */
    public function calculate_series_standings($series_id, $formula_key = null)
    {
        global $wpdb;

        if (!$formula_key) {
            $formula_key = get_option('tdwp_active_season_formula', 'season_total');
        }

        // Try transient cache first
        $cache_key = 'poker_series_standings_' . $series_id . '_' . $formula_key;
        $cached_standings = get_transient($cache_key);

        if ($cached_standings !== false) {
            return $cached_standings;
        }

        // Get all tournaments in this series
        $tournaments = $this->get_series_tournaments($series_id);

        if (empty($tournaments)) {
            return array();
        }

        // Get all players who participated in the series
        $players = $this->get_series_players($tournaments);

        if (empty($players)) {
            return array();
        }

        // Calculate standings for each player
        $standings = array();
        foreach ($players as $player_id) {
            $player_data = $this->calculate_player_series_data($player_id, $tournaments, $formula_key);
            if ($player_data) {
                $standings[] = $player_data;
            }
        }

        // Sort standings by points, then apply tie-breakers
        $standings = $this->sort_standings_with_tiebreakers($standings);

        // Assign final rankings
        $standings = $this->assign_final_rankings($standings);

        // Cache standings for 1 hour
        set_transient($cache_key, $standings, HOUR_IN_SECONDS);

        return $standings;
    }

    /**
     * Get tournaments in a series
     */
    private function get_series_tournaments($series_id)
    {
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_series_id',
                    'value' => $series_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'ASC'
        ));

        return $tournaments;
    }

    /**
     * Get all players who participated in series tournaments
     */
    private function get_series_players($tournaments)
    {
        global $wpdb;
        $players = array();

        $tournament_ids = wp_list_pluck($tournaments, 'ID');

        if (empty($tournament_ids)) {
            return $players;
        }

        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $unique_players = $wpdb->get_col($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
            "SELECT DISTINCT player_id FROM $table_name
             WHERE tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")",
            $tournament_ids
        ));

        return array_filter($unique_players);
    }

    /**
     * Calculate series data for a single player
     */
    private function calculate_player_series_data($player_id, $tournaments, $formula_key)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $tournament_ids = wp_list_pluck($tournaments, 'ID');

        // Get all tournament results for this player
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT tournament_id, finish_position, winnings, points, hits
             FROM $table_name
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
             WHERE player_id = %s AND tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")
             ORDER BY tournament_id",
            array_merge(array($player_id), $tournament_ids)
        ));

        if (empty($results)) {
            return null;
        }

        // Calculate cumulative statistics
        $total_points = 0;
        $total_winnings = 0;
        $total_hits = 0;
        $best_finish = PHP_INT_MAX;
        $worst_finish = 0;
        $tournaments_played = count($results);
        $tournament_points_list = array();
        $finishes = array();

        foreach ($results as $result) {
            $total_points += floatval($result->points);
            $total_winnings += floatval($result->winnings);
            $total_hits += intval($result->hits);

            if ($result->finish_position < $best_finish) {
                $best_finish = $result->finish_position;
            }
            if ($result->finish_position > $worst_finish) {
                $worst_finish = $result->finish_position;
            }

            $tournament_points_list[] = floatval($result->points);
            $finishes[] = intval($result->finish_position);
        }

        $avg_finish = array_sum($finishes) / count($finishes);

        // Get player information
        $player_post = get_posts(array(
            'post_type' => 'player',
            'meta_query' => array(
                array(
                    'key' => array('player_uuid', '_player_uuid'),
                    'value' => $player_id,
                    'compare' => 'IN'
                )
            ),
            'posts_per_page' => 1
        ));

        $player_name = $player_id; // Fallback to UUID
        $player_url = '';

        if (!empty($player_post)) {
            $player_name = $player_post[0]->post_title;
            $player_url = esc_url(get_permalink($player_post[0]->ID));
        }

        // Calculate series points using formula
        $series_data = array(
            'player_id' => $player_id,
            'player_name' => $player_name,
            'player_url' => $player_url,
            'tournaments_played' => $tournaments_played,
            'total_points' => $total_points,
            'total_winnings' => $total_winnings,
            'total_hits' => $total_hits,
            'best_finish' => $best_finish === PHP_INT_MAX ? 0 : $best_finish,
            'worst_finish' => $worst_finish,
            'avg_finish' => $avg_finish,
            'tournament_points' => $tournament_points_list,
            'finishes' => $finishes,
            'results_detail' => $results
        );

        // Apply series formula if specified
        if ($formula_key && $formula_key !== 'direct_sum') {
            $series_points = $this->apply_series_formula($series_data, $formula_key);
            $series_data['series_points'] = $series_points;
            $series_data['formula_used'] = $formula_key;
        } else {
            $series_data['series_points'] = $total_points;
            $series_data['formula_used'] = 'direct_sum';
        }

        // Calculate tie-breaker scores
        $series_data['tie_breakers'] = $this->calculate_tie_breakers($series_data);

        return $series_data;
    }

    /**
     * Apply series formula to calculate final points
     */
    private function apply_series_formula($series_data, $formula_key)
    {
        $formula_validator = new Poker_Tournament_Formula_Validator();
        $formula_data = $formula_validator->get_formula($formula_key);

        if (!$formula_data) {
            return $series_data['total_points']; // Fallback to simple sum
        }

        // Prepare formula data
        $formula_input = array(
            'tournament_points' => $series_data['tournament_points'],
            'total_tournaments' => count($series_data['tournament_points']),
            'tournaments_played' => $series_data['tournaments_played'],
            'total_winnings' => $series_data['total_winnings'],
            'total_hits' => $series_data['total_hits'],
            'best_finish' => $series_data['best_finish'],
            'avg_finish' => $series_data['avg_finish'],
            'player_id' => $series_data['player_id']
        );

        // Calculate using formula
        $result = $formula_validator->calculate_formula($formula_data['formula'], $formula_input, 'season');

        return $result['success'] ? $result['result'] : $series_data['total_points'];
    }

    /**
     * Calculate tie-breaker scores for ranking
     */
    private function calculate_tie_breakers($series_data)
    {
        $tie_breakers = array();

        // Tie-breaker 1: Most first place finishes
        $tie_breakers['first_places'] = count(array_filter($series_data['finishes'], function ($finish) {
            return $finish === 1;
        }));

        // Tie-breaker 2: Most top 3 finishes
        $tie_breakers['top3_finishes'] = count(array_filter($series_data['finishes'], function ($finish) {
            return $finish <= 3;
        }));

        // Tie-breaker 3: Most top 5 finishes
        $tie_breakers['top5_finishes'] = count(array_filter($series_data['finishes'], function ($finish) {
            return $finish <= 5;
        }));

        // Tie-breaker 4: Best single tournament points
        $tie_breakers['best_points'] = !empty($series_data['tournament_points']) ? max($series_data['tournament_points']) : 0;

        // Tie-breaker 5: Highest total winnings
        $tie_breakers['total_winnings'] = $series_data['total_winnings'];

        // Tie-breaker 6: Best average finish (lower is better)
        $tie_breakers['avg_finish'] = $series_data['avg_finish'];

        // Tie-breaker 7: Most tournaments played
        $tie_breakers['tournaments_played'] = $series_data['tournaments_played'];

        return $tie_breakers;
    }

    /**
     * Sort standings with tie-breaker logic
     */
    private function sort_standings_with_tiebreakers($standings)
    {
        usort($standings, function ($a, $b) {
            // Primary sort: Series points (descending)
            if ($a['series_points'] != $b['series_points']) {
                return $b['series_points'] <=> $a['series_points'];
            }

            // Apply tie-breakers in order
            $tie_breaker_order = array(
                'first_places',
                'top3_finishes',
                'top5_finishes',
                'best_points',
                'total_winnings',
                'tournaments_played'
            );

            foreach ($tie_breaker_order as $breaker) {
                if ($a['tie_breakers'][$breaker] != $b['tie_breakers'][$breaker]) {
                    return $b['tie_breakers'][$breaker] <=> $a['tie_breakers'][$breaker];
                }
            }

            // Final tie-breaker: Average finish (lower is better)
            if ($a['tie_breakers']['avg_finish'] != $b['tie_breakers']['avg_finish']) {
                return $a['tie_breakers']['avg_finish'] <=> $b['tie_breakers']['avg_finish'];
            }

            // If still tied, sort by player name
            return strcmp($a['player_name'], $b['player_name']);
        });

        return $standings;
    }

    /**
     * Assign final rankings with tie handling
     */
    private function assign_final_rankings($standings)
    {
        $current_rank = 1;
        $previous_points = null;
        $previous_tie_breakers = null;

        foreach ($standings as $index => &$standing) {
            if ($index > 0) {
                $is_tied = $standing['series_points'] === $previous_points;

                // Check all tie-breakers
                if ($is_tied) {
                    foreach ($standing['tie_breakers'] as $key => $value) {
                        if ($value != $previous_tie_breakers[$key]) {
                            $is_tied = false;
                            break;
                        }
                    }
                }

                if (!$is_tied) {
                    $current_rank = $index + 1;
                }
            }

            $standing['rank'] = $current_rank;
            $standing['is_tied'] = ($index > 0 &&
                $standing['series_points'] === $previous_points &&
                $standing['tie_breakers'] === $previous_tie_breakers);

            $previous_points = $standing['series_points'];
            $previous_tie_breakers = $standing['tie_breakers'];
        }

        return $standings;
    }

    /**
     * Calculate overall standings across all tournaments
     * Supports season filtering and formula application
     *
     * @param array|null $tournament_ids Optional tournament IDs to filter by
     * @param string|null $formula_key Optional formula key for calculation
     * @return array Overall standings with tie-breakers
     */
    public function calculate_overall_standings($tournament_ids = null, $formula_key = null)
    {
        global $wpdb;

        if (!$formula_key) {
            $formula_key = get_option('tdwp_active_season_formula', 'season_total');
        }

        // Generate cache key including tournament IDs
        $cache_key = 'poker_overall_standings_' . md5(serialize($tournament_ids) . $formula_key);
        $cached_standings = get_transient($cache_key);

        if ($cached_standings !== false) {
            return $cached_standings;
        }

        // Get tournament IDs if not provided
        if ($tournament_ids === null) {
            $tournament_ids = get_posts(array(
                'post_type' => 'tournament',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ));
        }

        if (empty($tournament_ids)) {
            return array();
        }

        // Get all players who participated
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unique_players = $wpdb->get_col($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
            "SELECT DISTINCT player_id FROM $table_name
         WHERE tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")",
            $tournament_ids
        ));

        if (empty($unique_players)) {
            return array();
        }

        // Calculate standings for each player
        $standings = array();
        foreach ($unique_players as $player_id) {
            $player_data = $this->calculate_overall_player_data($player_id, $tournament_ids, $formula_key);
            if ($player_data) {
                $standings[] = $player_data;
            }
        }

        // Sort with tie-breakers
        $standings = $this->sort_standings_with_tiebreakers($standings);

        // Assign rankings
        $standings = $this->assign_final_rankings($standings);

        // Cache for 1 hour
        set_transient($cache_key, $standings, HOUR_IN_SECONDS);

        return $standings;
    }

    /**
     * Calculate overall data for a single player
     * Mirrors calculate_player_series_data but for overall/all-time
     *
     * @param string $player_id Player UUID
     * @param array $tournament_ids Tournament IDs to include
     * @param string $formula_key Formula key for calculation
     * @return array|null Player data or null if no results
     */
    private function calculate_overall_player_data($player_id, $tournament_ids, $formula_key)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get all tournament results for this player
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT tournament_id, finish_position, winnings, points, hits
         FROM $table_name
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
         WHERE player_id = %s AND tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")
         ORDER BY tournament_id",
            array_merge(array($player_id), $tournament_ids)
        ));

        if (empty($results)) {
            return null;
        }

        // Calculate cumulative statistics (same logic as series)
        $total_points = 0;
        $total_winnings = 0;
        $total_hits = 0;
        $best_finish = PHP_INT_MAX;
        $worst_finish = 0;
        $tournaments_played = count($results);
        $tournament_points_list = array();
        $finishes = array();

        foreach ($results as $result) {
            $total_points += floatval($result->points);
            $total_winnings += floatval($result->winnings);
            $total_hits += intval($result->hits);

            if ($result->finish_position < $best_finish) {
                $best_finish = $result->finish_position;
            }
            if ($result->finish_position > $worst_finish) {
                $worst_finish = $result->finish_position;
            }

            $tournament_points_list[] = floatval($result->points);
            $finishes[] = intval($result->finish_position);
        }

        $avg_finish = array_sum($finishes) / count($finishes);

        // Get player information
        $player_post = get_posts(array(
            'post_type' => 'player',
            'meta_query' => array(
                array(
                    'key' => array('player_uuid', '_player_uuid'),
                    'value' => $player_id,
                    'compare' => 'IN'
                )
            ),
            'posts_per_page' => 1
        ));

        $player_name = $player_id;
        $player_url = '';

        if (!empty($player_post)) {
            $player_name = $player_post[0]->post_title;
            $player_url = esc_url(get_permalink($player_post[0]->ID));
        }

        // Build overall data array
        $overall_data = array(
            'player_id' => $player_id,
            'player_name' => $player_name,
            'player_url' => $player_url,
            'tournaments_played' => $tournaments_played,
            'total_points' => $total_points,
            'total_winnings' => $total_winnings,
            'total_hits' => $total_hits,
            'best_finish' => $best_finish === PHP_INT_MAX ? 0 : $best_finish,
            'worst_finish' => $worst_finish,
            'avg_finish' => $avg_finish,
            'tournament_points' => $tournament_points_list,
            'finishes' => $finishes,
            'results_detail' => $results
        );

        // Apply formula if specified
        if ($formula_key && $formula_key !== 'direct_sum') {
            $overall_points = $this->apply_series_formula($overall_data, $formula_key);
            $overall_data['overall_points'] = $overall_points;
            $overall_data['formula_used'] = $formula_key;
        } else {
            $overall_data['overall_points'] = $total_points;
            $overall_data['formula_used'] = 'direct_sum';
        }

        // Calculate tie-breakers
        $overall_data['tie_breakers'] = $this->calculate_tie_breakers($overall_data);

        return $overall_data;
    }

    /**
     * Clear overall standings cache
     * Call this when tournaments are saved/deleted
     *
     * @return void
     */
    public function clear_overall_standings_cache()
    {
        global $wpdb;

        // Clear all overall standings transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_poker_overall_standings_%'"
        );
    }

    /**
     * Get series statistics summary
     */
    public function get_series_statistics($series_id)
    {
        $standings = $this->calculate_series_standings($series_id);

        if (empty($standings)) {
            return array();
        }

        $total_players = count($standings);
        $total_tournaments = 0;
        $total_points = 0;
        $total_winnings = 0;
        $total_hits = 0;

        foreach ($standings as $standing) {
            $total_tournaments = max($total_tournaments, $standing['tournaments_played']);
            $total_points += $standing['total_points'];
            $total_winnings += $standing['total_winnings'];
            $total_hits += $standing['total_hits'];
        }

        return array(
            'total_players' => $total_players,
            'total_tournaments' => $total_tournaments,
            'total_points' => $total_points,
            'total_winnings' => $total_winnings,
            'total_hits' => $total_hits,
            'avg_points_per_player' => $total_points / $total_players,
            'avg_winnings_per_player' => $total_winnings / $total_players
        );
    }

    /**
     * Export series standings to CSV
     */
    public function export_series_standings_csv($series_id, $formula_key = null)
    {
        $standings = $this->calculate_series_standings($series_id, $formula_key);

        if (empty($standings)) {
            return false;
        }

        $filename = 'series-standings-' . sanitize_title(get_the_title($series_id)) . '.csv';
        $filepath = get_temp_dir() . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Required for CSV export to output buffer
        $handle = fopen($filepath, 'w');

        // CSV headers
        fputcsv($handle, array(
            'Rank',
            'Player',
            'Series Points',
            'Tournaments Played',
            'Total Points',
            'Total Winnings',
            'Best Finish',
            'Average Finish',
            'Total Hits',
            'First Places',
            'Top 3 Finishes',
            'Top 5 Finishes'
        ));

        // CSV data
        foreach ($standings as $standing) {
            fputcsv($handle, array(
                $standing['rank'] . ($standing['is_tied'] ? 'T' : ''),
                $standing['player_name'],
                number_format($standing['series_points'], 2),
                $standing['tournaments_played'],
                number_format($standing['total_points'], 2),
                number_format($standing['total_winnings'], 2),
                $standing['best_finish'],
                number_format($standing['avg_finish'], 1),
                $standing['total_hits'],
                $standing['tie_breakers']['first_places'],
                $standing['tie_breakers']['top3_finishes'],
                $standing['tie_breakers']['top5_finishes']
            ));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing CSV output buffer
        fclose($handle);

        return $filepath;
    }

    /**
     * Display series standings table
     */
    public function display_series_standings_table($series_id, $formula_key = null, $show_details = false)
    {
        $standings = $this->calculate_series_standings($series_id, $formula_key);

        if (empty($standings)) {
            echo '<p>' . esc_html__('No standings data available for this series.', 'poker-tournament-import') . '</p>';
            return;
        }

        echo '<div class="series-standings-table" id="series-standings-' . esc_attr($series_id) . '">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Rank', 'poker-tournament-import') . '</th>';
        echo '<th>' . esc_html__('Player', 'poker-tournament-import') . '</th>';
        echo '<th>' . esc_html__('Series Points', 'poker-tournament-import') . '</th>';
        echo '<th>' . esc_html__('Tournaments', 'poker-tournament-import') . '</th>';
        echo '<th>' . esc_html__('Best Finish', 'poker-tournament-import') . '</th>';
        echo '<th>' . esc_html__('Avg Finish', 'poker-tournament-import') . '</th>';

        if ($show_details) {
            echo '<th>' . esc_html__('Total Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('First Places', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Top 3', 'poker-tournament-import') . '</th>';
            echo '<th>' . esc_html__('Top 5', 'poker-tournament-import') . '</th>';
        }

        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($standings as $standing) {
            $rank_display = $standing['rank'];
            if ($standing['is_tied']) {
                $rank_display .= 'T';
            }

            // Add position indicators for top ranks
            $rank_class = '';
            $rank_indicator = '';
            if ($standing['rank'] === 1) {
                $rank_class = ' rank-first';
                $rank_indicator = ' 🥇';
            } elseif ($standing['rank'] === 2) {
                $rank_class = ' rank-second';
                $rank_indicator = ' 🥈';
            } elseif ($standing['rank'] === 3) {
                $rank_class = ' rank-third';
                $rank_indicator = ' 🥉';
            }

            echo '<tr class="' . esc_attr($rank_class) . '">';
            echo '<td class="rank-cell">' . esc_html($rank_display) . esc_html($rank_indicator) . '</td>';

            if ($standing['player_url']) {
                echo '<td class="player-cell"><a href="' . esc_url($standing['player_url']) . '">' . esc_html($standing['player_name']) . '</a></td>';
            } else {
                echo '<td class="player-cell">' . esc_html($standing['player_name']) . '</td>';
            }

            echo '<td class="points-cell">' . esc_html(number_format($standing['series_points'], 1)) . '</td>';
            echo '<td class="tournaments-cell">' . esc_html($standing['tournaments_played']) . '</td>';
            echo '<td class="best-finish-cell">' . esc_html($standing['best_finish']) . '</td>';
            echo '<td class="avg-finish-cell">' . esc_html(number_format($standing['avg_finish'], 1)) . '</td>';

            if ($show_details) {
                echo '<td class="winnings-cell">$' . esc_html(number_format($standing['total_winnings'], 0)) . '</td>';
                echo '<td class="first-places-cell">' . esc_html($standing['tie_breakers']['first_places']) . '</td>';
                echo '<td class="top3-cell">' . esc_html($standing['tie_breakers']['top3_finishes']) . '</td>';
                echo '<td class="top5-cell">' . esc_html($standing['tie_breakers']['top5_finishes']) . '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Add CSS for ranking indicators
        echo '<style>
        .rank-first { background-color: #fff9e6 !important; }
        .rank-second { background-color: #f0f8ff !important; }
        .rank-third { background-color: #fff0f5 !important; }
        .rank-cell { font-weight: bold; }
        .points-cell { font-weight: bold; }
        </style>';
    }

    /**
     * Cached database query helper
     * Wraps $wpdb queries with WordPress object cache
     *
     * @param string $query_type 'get_results', 'get_col', or 'get_var'
     * @param string $sql SQL query
     * @param mixed $args Optional query arguments
     * @param int $cache_time Cache duration in seconds (default: 1 hour)
     * @return mixed Query results
     */
    private function cached_query($query_type, $sql, $args = null, $cache_time = HOUR_IN_SECONDS)
    {
        global $wpdb;

        // Generate cache key from query
        $cache_key = 'poker_' . md5($sql . serialize($args));
        $cache_group = 'poker_tournament';

        // Try to get from cache
        $results = wp_cache_get($cache_key, $cache_group);

        if (false === $results) {
            // Cache miss - query database
            if ($args !== null) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql parameter is being prepared here
                $prepared_sql = $wpdb->prepare($sql, $args);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL prepared above with $wpdb->prepare()
                $results = $wpdb->$query_type($prepared_sql);
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL passed directly without placeholders
                $results = $wpdb->$query_type($sql);
            }

            // Store in cache
            wp_cache_set($cache_key, $results, $cache_group, $cache_time);
        }

        return $results;
    }
}