<?php
/**
 * Shortcodes Class
 *
 * Handles shortcode functionality for displaying tournament data
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Import_Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('tournament_results', array($this, 'tournament_results_shortcode'));
        add_shortcode('tournament_series', array($this, 'tournament_series_shortcode'));
        add_shortcode('player_profile', array($this, 'player_profile_shortcode'));

        // New tabbed interface shortcodes
        add_shortcode('series_overview', array($this, 'series_overview_shortcode'));
        add_shortcode('series_results', array($this, 'series_results_shortcode'));
        add_shortcode('series_statistics', array($this, 'series_statistics_shortcode'));
        add_shortcode('series_players', array($this, 'series_players_shortcode'));
        add_shortcode('series_leaderboard', array($this, 'series_leaderboard_shortcode'));
        add_shortcode('series_standings', array($this, 'series_standings_shortcode'));

        // Tab navigation shortcode
        add_shortcode('series_tabs', array($this, 'series_tabs_shortcode'));

        // Season shortcodes
        add_shortcode('season_tabs', array($this, 'season_tabs_shortcode'));
        add_shortcode('season_overview', array($this, 'season_overview_shortcode'));
        add_shortcode('season_results', array($this, 'season_results_shortcode'));
        add_shortcode('season_statistics', array($this, 'season_statistics_shortcode'));
        add_shortcode('season_players', array($this, 'season_players_shortcode'));
        add_shortcode('season_standings', array($this, 'season_standings_shortcode'));

        // Dashboard shortcode
        add_shortcode('poker_dashboard', array($this, 'poker_dashboard_shortcode'));
    }

    /**
     * Tournament results shortcode
     * Usage: [tournament_results id="123"]
     */
    public function tournament_results_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'show_players' => 'true',
                'show_structure' => 'false',
            ),
            $atts,
            'tournament_results'
        );

        if (empty($atts['id'])) {
            return '<p>' . __('Please specify a tournament ID', 'poker-tournament-import') . '</p>';
        }

        $tournament_id = intval($atts['id']);
        $tournament = get_post($tournament_id);

        if (!$tournament || $tournament->post_type !== 'tournament') {
            return '<p>' . __('Tournament not found', 'poker-tournament-import') . '</p>';
        }

        ob_start();
        $this->render_tournament_results($tournament, $atts);
        return ob_get_clean();
    }

    /**
     * Tournament series shortcode
     * Usage: [tournament_series id="456"]
     */
    public function tournament_series_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'show_standings' => 'true',
                'limit' => 10,
            ),
            $atts,
            'tournament_series'
        );

        if (empty($atts['id'])) {
            return '<p>' . __('Please specify a series ID', 'poker-tournament-import') . '</p>';
        }

        $series_id = intval($atts['id']);
        $series = get_post($series_id);

        if (!$series || $series->post_type !== 'tournament_series') {
            return '<p>' . __('Series not found', 'poker-tournament-import') . '</p>';
        }

        ob_start();
        $this->render_series_overview($series, $atts);
        return ob_get_clean();
    }

    /**
     * Player profile shortcode
     * Usage: [player_profile name="John Doe"]
     */
    public function player_profile_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'name' => '',
                'id' => 0,
                'show_stats' => 'true',
            ),
            $atts,
            'player_profile'
        );

        $player = null;

        if (!empty($atts['id'])) {
            $player = get_post(intval($atts['id']));
        } elseif (!empty($atts['name'])) {
            $players = get_posts(array(
                'post_type' => 'player',
                'title' => $atts['name'],
                'numberposts' => 1
            ));

            if (!empty($players)) {
                $player = $players[0];
            }
        }

        if (!$player || $player->post_type !== 'player') {
            return '<p>' . __('Player not found', 'poker-tournament-import') . '</p>';
        }

        ob_start();
        $this->render_player_profile($player, $atts);
        return ob_get_clean();
    }

    /**
     * Render tournament results
     */
    private function render_tournament_results($tournament, $atts) {
        // Get comprehensive tournament data
        $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
        $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
        $buy_in = get_post_meta($tournament->ID, '_buy_in', true);
        $players_count = get_post_meta($tournament->ID, '_players_count', true);
        $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
        $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

        // Get series and season information
        $series_id = get_post_meta($tournament->ID, '_series_id', true);
        $season_id = get_post_meta($tournament->ID, '_season_id', true);
        $series_name = $series_id ? get_the_title($series_id) : '';
        $season_name = $season_id ? get_the_title($season_id) : '';

        // Calculate additional statistics
        $total_winnings = $this->calculate_total_winnings($tournament_uuid);
        $paid_positions = $this->get_paid_positions($tournament_uuid);
        $divisor = max(1, min($players_count, $paid_positions)); // Ensure divisor is at least 1 to prevent division by zero
        $average_winnings = $players_count > 0 ? $total_winnings / $divisor : 0;

        echo '<div class="tournament-results">';
        echo '<header class="tournament-header">';
        echo '<h2>' . esc_html($tournament->post_title) . '</h2>';

        // Tournament actions
        echo '<div class="tournament-actions">';
        echo '<button class="print-tournament" onclick="window.print()">' . __('Print', 'poker-tournament-import') . '</button>';
        echo '<button class="export-tournament" data-tournament-id="' . esc_attr($tournament->ID) . '" data-format="csv">' . __('Export CSV', 'poker-tournament-import') . '</button>';
        echo '</div>';
        echo '</header>';

        // Enhanced tournament metadata
        echo '<div class="tournament-meta">';
        if ($tournament_date) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Date:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html(date_i18n(get_option('date_format'), strtotime($tournament_date))) . '</span>';
            echo '</div>';
        }

        if ($series_name) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Series:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value"><a href="' . get_permalink($series_id) . '">' . esc_html($series_name) . '</a></span>';
            echo '</div>';
        }

        if ($season_name) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Season:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value"><a href="' . get_permalink($season_id) . '">' . esc_html($season_name) . '</a></span>';
            echo '</div>';
        }

        if ($buy_in) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Buy-in:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html($currency . $buy_in) . '</span>';
            echo '</div>';
        }

        if ($players_count) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Players:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html($players_count) . '</span>';
            echo '</div>';
        }

        if ($prize_pool) {
            echo '<div class="meta-item highlight">';
            echo '<span class="meta-label">' . __('Prize Pool:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html($currency . number_format(floatval($prize_pool ?: 0), 2)) . '</span>';
            echo '</div>';
        }

        if ($total_winnings > 0) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Total Paid:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html($currency . number_format(floatval($total_winnings ?: 0), 2)) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        // Tournament statistics summary
        if ($atts['show_structure'] === 'true') {
            $this->render_tournament_statistics($tournament_uuid, $players_count);
        }

        // Display content
        if ($tournament->post_content) {
            echo '<div class="tournament-description">';
            echo '<h3>' . __('Tournament Details', 'poker-tournament-import') . '</h3>';
            echo wpautop($tournament->post_content);
            echo '</div>';
        }

        // Display players if requested
        if ($atts['show_players'] === 'true') {
            $this->render_tournament_players($tournament->ID);
        }

        echo '</div>';
    }

    /**
     * Render tournament players table
     */
    private function render_tournament_players($tournament_id) {
        global $wpdb;

        // DEBUG: Log processing attempt
        Poker_Tournament_Import_Debug::log("Attempting to render tournament players for tournament ID: {$tournament_id}");

        // CRITICAL FIX: Try to get real-time chronological results first
        $realtime_results = $this->get_realtime_tournament_results($tournament_id);
        if ($realtime_results) {
            Poker_Tournament_Import_Debug::log_success("Using real-time chronological processing for tournament ID: {$tournament_id}");
            $this->render_realtime_players($realtime_results, $tournament_id);
            return;
        }

        // DEBUG: Log why real-time processing failed
        $raw_content = get_post_meta($tournament_id, '_tournament_raw_content', true);
        if (!$raw_content) {
            Poker_Tournament_Import_Debug::log_warning("FALLBACK: No raw TDT content found for tournament ID: {$tournament_id} - tournament was imported before v2.1.3");
        } else {
            Poker_Tournament_Import_Debug::log_error("FALLBACK: Real-time processing failed for tournament ID: {$tournament_id} despite having raw content");
        }

        // Fallback to old method if real-time processing fails
        Poker_Tournament_Import_Debug::log("FALLBACK: Using database method (buy-in order) for tournament ID: {$tournament_id}");
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tournament_id = %s ORDER BY finish_position ASC",
            get_post_meta($tournament_id, 'tournament_uuid', true)
        ));

        if ($players) {
            // Get tournament metadata for ranking calculations
            $players_count = count($players);
            $paid_positions = 0;
            foreach ($players as $player) {
                if ($player->winnings > 0) $paid_positions++;
            }

            $final_table_count = min(9, $players_count);
            $bubble_position = $paid_positions + 1;

            echo '<div class="tournament-players">';
            echo '<h3>' . __('Tournament Results', 'poker-tournament-import') . '</h3>';

            // CRITICAL FIX: Add enhanced fallback notice with actionable solutions
            echo '<div class="processing-notice" style="background: #fef7f7; border: 1px solid #d63638; border-radius: 4px; padding: 15px; margin-bottom: 20px;">';
            echo '<p><strong>⚠️ Using Legacy Database Results</strong> - This tournament is showing results in buy-in order because it was imported before version 2.1.3.</p>';

            // Add tournament ID for debugging
            echo '<p><small><strong>Tournament ID:</strong> ' . esc_html($tournament_id) . '</small></p>';

            // Add actionable solutions
            echo '<div class="solution-options" style="margin-top: 10px;">';
            echo '<p><strong>Solutions:</strong></p>';
            echo '<ol style="margin: 5px 0; padding-left: 20px;">';
            echo '<li><button type="button" class="button button-small" onclick="attemptChronologicalReconstruction(' . esc_js($tournament_id) . ')">Try Chronological Reconstruction</button></li>';
            echo '<li><button type="button" class="button button-small" onclick="showTdtUploadInterface(' . esc_js($tournament_id) . ')">Upload Original .tdt File</button></li>';
            echo '<li><a href="' . admin_url('admin.php?page=poker-data-mart-cleaner&tab=migration') . '" class="button button-small">Use Migration Tools</a></li>';
            echo '</ol>';
            echo '</div>';

            echo '</div>';

            // Add hidden container for reconstruction results
            echo '<div id="reconstruction-results-' . esc_attr($tournament_id) . '" style="display: none; margin: 15px 0;"></div>';

            // Add tournament summary
            echo '<div class="tournament-summary-grid">';
            echo '<div class="summary-card">';
            echo '<span class="summary-label">' . __('Entries:', 'poker-tournament-import') . '</span>';
            echo '<span class="summary-value">' . esc_html($players_count) . '</span>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<span class="summary-label">' . __('Paid Positions:', 'poker-tournament-import') . '</span>';
            echo '<span class="summary-value">' . esc_html($paid_positions) . '</span>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<span class="summary-label">' . __('Final Table:', 'poker-tournament-import') . '</span>';
            echo '<span class="summary-value">' . esc_html($final_table_count) . '</span>';
            echo '</div>';
            echo '</div>';

                        echo '<table class="tournament-results-table enhanced" id="tournament-results-' . esc_attr($tournament_id) . '">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="position-col">' . __('Pos', 'poker-tournament-import') . '</th>';
            echo '<th class="player-col">' . __('Player', 'poker-tournament-import') . '</th>';
            echo '<th class="winnings-col">' . __('Winnings', 'poker-tournament-import') . '</th>';
            echo '<th class="points-col">' . __('Points', 'poker-tournament-import') . '</th>';
            echo '<th class="achievements-col">' . __('Achievements', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($players as $index => $player) {
                $player_post = get_posts(array(
                    'post_type' => 'player',
                    'meta_key' => 'player_uuid',
                    'meta_value' => $player->player_id,
                    'numberposts' => 1
                ));

                $player_name = !empty($player_post) ? $player_post[0]->post_title : __('Unknown Player', 'poker-tournament-import');
                $player_id = !empty($player_post) ? $player_post[0]->ID : 0;

                // Determine row class based on position
                $row_class = '';
                $position_badge = '';
                $achievements = array();

                if ($player->finish_position == 1) {
                    $row_class = 'gold-medal';
                    $position_badge = '🥇';
                    $achievements[] = '<span class="achievement-badge winner" title="' . __('Champion!', 'poker-tournament-import') . '">🏆</span>';
                } elseif ($player->finish_position == 2) {
                    $row_class = 'silver-medal';
                    $position_badge = '🥈';
                    $achievements[] = '<span class="achievement-badge runner-up" title="' . __('Runner-up', 'poker-tournament-import') . '">🥈</span>';
                } elseif ($player->finish_position == 3) {
                    $row_class = 'bronze-medal';
                    $position_badge = '🥉';
                    $achievements[] = '<span class="achievement-badge third-place" title="' . __('Third Place', 'poker-tournament-import') . '">🥉</span>';
                } elseif ($player->finish_position <= $final_table_count) {
                    $row_class = 'final-table';
                    $achievements[] = '<span class="achievement-badge final-table" title="' . __('Final Table', 'poker-tournament-import') . '">🎯</span>';
                } elseif ($player->finish_position == $bubble_position && $paid_positions > 0) {
                    $row_class = 'bubble';
                    $achievements[] = '<span class="achievement-badge bubble" title="' . __('Bubble Finish', 'poker-tournament-import') . '">💭</span>';
                }

                // Add elimination achievement
                if ($player->hits > 0) {
                    /* translators: %d: number of eliminations */
                    $achievements[] = '<span class="achievement-badge eliminations" title="' . sprintf(_n('%d Elimination', '%d Eliminations', $player->hits, 'poker-tournament-import'), $player->hits) . '">⚔️</span>';
                }

                echo '<tr class="' . esc_attr($row_class) . '">';

                // Position with badge
                echo '<td class="position-cell">';
                if ($position_badge) {
                    echo '<span class="position-badge">' . esc_html($position_badge) . '</span>';
                }
                echo '<span class="position-number">' . esc_html($player->finish_position) . get_ordinal_suffix($player->finish_position) . '</span>';
                echo '</td>';

                // Player with link
                echo '<td class="player-cell">';
                if ($player_id > 0) {
                    echo '<a href="' . esc_url(get_permalink($player_id)) . '" class="player-link">';
                    echo '<span class="player-avatar">' . esc_html(substr($player_name, 0, 2)) . '</span>';
                    echo '<span class="player-name">' . esc_html($player_name) . '</span>';
                    echo '</a>';
                } else {
                    echo '<span class="player-avatar unknown">?</span>';
                    echo '<span class="player-name">' . esc_html($player_name) . '</span>';
                }
                echo '</td>';

                // Winnings
                echo '<td class="winnings-cell">';
                if ($player->winnings > 0) {
                    echo '<span class="winnings-amount">' . esc_html(poker_format_currency($player->winnings)) . '</span>';
                } else {
                    echo '<span class="no-winnings">-</span>';
                }
                echo '</td>';

                // Points with highlighting
                echo '<td class="points-cell">';
                if ($player->points > 0) {
                    echo '<span class="points-amount ' . ($player->points >= 100 ? 'high-points' : '') . '">' . esc_html(number_format($player->points, 1)) . '</span>';
                } else {
                    echo '<span class="no-points">-</span>';
                }
                echo '</td>';

                // Achievements
                echo '<td class="achievements-cell">';
                if (!empty($achievements)) {
                    echo '<div class="achievements-list">' . implode('', $achievements) . '</div>';
                }
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Add legend
            echo '<div class="results-legend">';
            echo '<h4>' . __('Achievement Legend', 'poker-tournament-import') . '</h4>';
            echo '<div class="legend-items">';
            echo '<span class="legend-item">🏆 ' . __('Champion', 'poker-tournament-import') . '</span>';
            echo '<span class="legend-item">🥈 ' . __('Runner-up', 'poker-tournament-import') . '</span>';
            echo '<span class="legend-item">🥉 ' . __('Third Place', 'poker-tournament-import') . '</span>';
            echo '<span class="legend-item">🎯 ' . __('Final Table', 'poker-tournament-import') . '</span>';
            echo '<span class="legend-item">💭 ' . __('Bubble', 'poker-tournament-import') . '</span>';
            echo '<span class="legend-item">⚔️ ' . __('Eliminations', 'poker-tournament-import') . '</span>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        }
    }

    /**
     * Render series overview
     */
    private function render_series_overview($series, $atts) {
        echo '<div class="tournament-series">';
        echo '<h2>' . esc_html($series->post_title) . '</h2>';

        if ($series->post_content) {
            echo '<div class="series-description">';
            echo wpautop($series->post_content);
            echo '</div>';
        }

        // Get tournaments in this series
        $tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_uuid',
            'meta_value' => get_post_meta($series->ID, '_series_uuid', true),
            'numberposts' => intval($atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if ($tournaments) {
            echo '<div class="series-tournaments">';
            echo '<h3>' . __('Recent Tournaments', 'poker-tournament-import') . '</h3>';
            echo '<ul>';

            foreach ($tournaments as $tournament) {
                echo '<li>';
                echo '<a href="' . get_permalink($tournament->ID) . '">' . esc_html($tournament->post_title) . '</a>';
                $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                if ($tournament_date) {
                    echo ' - ' . esc_html(date('M j, Y', strtotime($tournament_date)));
                }
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render player profile
     */
    private function render_player_profile($player, $atts) {
        echo '<div class="player-profile">';
        echo '<h2>' . esc_html($player->post_title) . '</h2>';

        if ($atts['show_stats'] === 'true') {
            $this->render_player_stats($player->ID);
        }

        echo '</div>';
    }

    /**
     * Render player statistics
     */
    private function render_player_stats($player_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $player_uuid = get_post_meta($player_id, 'player_uuid', true);

        if (!$player_uuid) {
            return;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as tournaments_played,
                SUM(winnings) as total_winnings,
                AVG(finish_position) as avg_finish,
                SUM(points) as total_points,
                SUM(hits) as total_hits,
                MAX(winnings) as best_cash,
                MIN(finish_position) as best_finish
            FROM $table_name
            WHERE player_id = %s",
            $player_uuid
        ));

        if ($stats) {
            echo '<div class="player-stats">';
            echo '<h3>' . __('Career Statistics', 'poker-tournament-import') . '</h3>';
            echo '<table class="player-stats-table">';
            echo '<tr><td>' . __('Tournaments Played:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->tournaments_played) . '</td></tr>';
            echo '<tr><td>' . __('Total Winnings:', 'poker-tournament-import') . '</td><td>' . esc_html(poker_format_currency($stats->total_winnings)) . '</td></tr>';
            echo '<tr><td>' . __('Average Finish:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->avg_finish, 1)) . '</td></tr>';
            echo '<tr><td>' . __('Total Points:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->total_points, 2)) . '</td></tr>';
            echo '<tr><td>' . __('Total Eliminations:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->total_hits) . '</td></tr>';

            if ($stats->best_cash > 0) {
                echo '<tr><td>' . __('Best Cash:', 'poker-tournament-import') . '</td><td>' . esc_html(poker_format_currency($stats->best_cash)) . '</td></tr>';
            }

            if ($stats->best_finish > 0) {
                echo '<tr><td>' . __('Best Finish:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->best_finish) . get_ordinal_suffix($stats->best_finish) . '</td></tr>';
            }

            echo '</table>';
            echo '</div>';

            // Add tournament history
            $this->render_player_tournament_history($player_uuid);
        }
    }

    /**
     * Render player tournament history
     */
    private function render_player_tournament_history($player_uuid) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $tournaments = $wpdb->get_results($wpdb->prepare(
            "SELECT tp.*, p.post_title as tournament_name, p.post_date,
                    pm.meta_value as tournament_date
            FROM $table_name tp
            LEFT JOIN {$wpdb->posts} p ON tp.tournament_id = (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'tournament_uuid')
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tournament_date'
            WHERE tp.player_id = %s
            ORDER BY tp.finish_position ASC
            LIMIT 10",
            $player_uuid
        ));

        if ($tournaments) {
            echo '<div class="player-tournament-history">';
            echo '<h3>' . __('Recent Tournament Results', 'poker-tournament-import') . '</h3>';
            echo '<table class="player-history-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Position', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Tournament', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Date', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Points', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($tournaments as $tournament) {
                $tournament_link = get_permalink($tournament->tournament_id);
                $display_date = $tournament->tournament_date ?
                    date_i18n(get_option('date_format'), strtotime($tournament->tournament_date)) :
                    date_i18n(get_option('date_format'), strtotime($tournament->post_date));

                echo '<tr>';
                echo '<td>' . esc_html($tournament->finish_position) . get_ordinal_suffix($tournament->finish_position) . '</td>';
                echo '<td><a href="' . esc_url($tournament_link) . '">' . esc_html($tournament->tournament_name) . '</a></td>';
                echo '<td>' . esc_html($display_date) . '</td>';
                echo '<td>' . esc_html(poker_format_currency($tournament->winnings)) . '</td>';
                echo '<td>' . esc_html(number_format($tournament->points, 2)) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
    }

    /**
     * Calculate total winnings for a tournament
     */
    private function calculate_total_winnings($tournament_uuid) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(winnings) FROM $table_name WHERE tournament_id = %s AND winnings > 0",
            $tournament_uuid
        ));

        return floatval($result);
    }

    /**
     * Get paid positions count
     */
    private function get_paid_positions($tournament_uuid) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE tournament_id = %s AND winnings > 0",
            $tournament_uuid
        ));

        return intval($result);
    }

    /**
     * Render tournament statistics
     */
    private function render_tournament_statistics($tournament_uuid, $total_players) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as paid_positions,
                SUM(winnings) as total_paid,
                AVG(winnings) as avg_winnings,
                MAX(winnings) as first_place
            FROM $table_name
            WHERE tournament_id = %s AND winnings > 0",
            $tournament_uuid
        ));

        if ($stats && $stats->paid_positions > 0) {
            echo '<div class="tournament-statistics">';
            echo '<h3>' . __('Tournament Statistics', 'poker-tournament-import') . '</h3>';
            echo '<div class="stats-grid">';

            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . esc_html($stats->paid_positions) . '</div>';
            echo '<div class="stat-label">' . __('Paid Positions', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . esc_html(round(($stats->paid_positions / $total_players) * 100, 1)) . '%</div>';
            echo '<div class="stat-label">' . __('Cash Rate', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . esc_html(poker_format_currency($stats->avg_winnings)) . '</div>';
            echo '<div class="stat-label">' . __('Average Cash', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . esc_html(poker_format_currency($stats->first_place)) . '</div>';
            echo '<div class="stat-label">' . __('First Prize', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
        }
    }
  /**
     * Series tab navigation shortcode
     * Usage: [series_tabs active="overview" series_id="123"]
     */
    public function series_tabs_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'active' => 'overview',
                'series_id' => 0,
                'series_uuid' => ''
            ),
            $atts,
            'series_tabs'
        );

        $series_id = intval($atts['series_id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        $tabs = array(
            'overview' => __('Overview', 'poker-tournament-import'),
            'results' => __('Results', 'poker-tournament-import'),
            'statistics' => __('Statistics', 'poker-tournament-import'),
            'players' => __('Players', 'poker-tournament-import')
        );

        ob_start();
        ?>
        <div class="series-tabs-container" id="series-tabs-<?php echo esc_attr($series_id); ?>" data-series-id="<?php echo esc_attr($series_id); ?>">
            <div class="series-tabs-nav">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <button class="series-tab-btn <?php echo $atts['active'] === $tab_key ? 'active' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab_key); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="series-tabs-content">
                <?php
                // Load active tab content
                echo do_shortcode('[series_' . $atts['active'] . ' id="' . $series_id . '"]');
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series overview shortcode
     * Usage: [series_overview id="123"]
     */
    public function series_overview_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'limit' => 10
            ),
            $atts,
            'series_overview'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        $series = get_post($series_id);
        if (!$series || $series->post_type !== 'tournament_series') {
            return '<p>' . __('Series not found', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $series_uuid = get_post_meta($series_id, '_series_uuid', true);

        // Get series statistics
        $series_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_id',
            'meta_value' => $series_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $total_tournaments = count($series_tournaments);
        $total_players = 0;
        $total_prize_pool = 0;
        $unique_players = array();
        $tournament_uuids = array();

        foreach ($series_tournaments as $tournament) {
            $players_count = get_post_meta($tournament->ID, '_players_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);

            $total_players += intval($players_count);
            $total_prize_pool += floatval($prize_pool);

            if ($tournament_uuid) {
                $tournament_uuids[] = $tournament_uuid;
                $players = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT player_id FROM $table_name WHERE tournament_id = %s",
                    $tournament_uuid
                ));
                $unique_players = array_merge($unique_players, $players);
            }
        }

        $unique_players_count = count(array_unique($unique_players));
        $avg_prize_pool = $total_tournaments > 0 ? $total_prize_pool / $total_tournaments : 0;

        // Get top player
        $top_player = null;
        if (!empty($tournament_uuids)) {
            $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
            $query = $wpdb->prepare(
                "SELECT tp.player_id, SUM(tp.points) as total_points,
                        SUM(tp.winnings) as total_winnings,
                        MIN(tp.finish_position) as best_finish
                 FROM $table_name tp
                 WHERE tp.tournament_id IN ($placeholders)
                 GROUP BY tp.player_id
                 ORDER BY total_points DESC
                 LIMIT 1",
                ...$tournament_uuids
            );
            $top_player_data = $wpdb->get_row($query);

            if ($top_player_data) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                     LIMIT 1",
                    $top_player_data->player_id
                ));

                if ($player_post) {
                    $top_player = array(
                        'name' => $player_post->post_title,
                        'id' => $player_post->ID,
                        'points' => $top_player_data->total_points,
                        'winnings' => $top_player_data->total_winnings,
                        'best_finish' => $top_player_data->best_finish
                    );
                }
            }
        }

        ob_start();
        ?>
        <div class="series-overview-content" id="series-overview-<?php echo esc_attr($series_id); ?>">
            <!-- Statistics Cards -->
            <div class="series-stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($total_tournaments); ?></div>
                    <div class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($unique_players_count); ?></div>
                    <div class="stat-label"><?php _e('Unique Players', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($total_prize_pool ?: 0))); ?></div>
                    <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($avg_prize_pool ?: 0))); ?></div>
                    <div class="stat-label"><?php _e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
            </div>

            <!-- Best Player -->
            <?php if ($top_player): ?>
            <div class="best-player-card" id="series-best-player-<?php echo esc_attr($series_id); ?>">
                <h3><?php _e('Best Player', 'poker-tournament-import'); ?></h3>
                <div class="player-info">
                    <div class="player-avatar"><?php echo substr($top_player['name'], 0, 2); ?></div>
                    <div class="player-details">
                        <a href="<?php echo get_permalink($top_player['id']); ?>" class="player-name">
                            <?php echo esc_html($top_player['name']); ?>
                        </a>
                        <div class="player-stats">
                            <span class="player-points"><?php echo esc_html(number_format($top_player['points'], 0)); ?> <?php _e('points', 'poker-tournament-import'); ?></span>
                            <span class="player-winnings"><?php echo esc_html(poker_format_currency($top_player['winnings'])); ?></span>
                            <span class="player-best"><?php _e('Best:', 'poker-tournament-import'); ?> <?php echo esc_html($top_player['best_finish']); ?><?php echo get_ordinal_suffix($top_player['best_finish']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Tournaments -->
            <div class="recent-tournaments-section" id="recent-tournaments-<?php echo esc_attr($series_id); ?>">
                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                <?php
                $recent_tournaments = array_slice($series_tournaments, 0, intval($atts['limit']));
                if (!empty($recent_tournaments)):
                ?>
                    <div class="recent-tournaments-list">
                        <?php foreach ($recent_tournaments as $tournament):
                            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                            $players_count = get_post_meta($tournament->ID, '_players_count', true);
                            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                            $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                            $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                            // Get winner
                            $winner_name = '';
                            if ($tournament_uuid) {
                                $winner = $wpdb->get_row($wpdb->prepare(
                                    "SELECT p.post_title
                                     FROM $table_name tp
                                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                     WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                     LIMIT 1",
                                    $tournament_uuid
                                ));
                                if ($winner) $winner_name = $winner->post_title;
                            }
                        ?>
                            <div class="recent-tournament-item">
                                <div class="tournament-info">
                                    <div class="tournament-name">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>"><?php echo esc_html($tournament->post_title); ?></a>
                                    </div>
                                    <div class="tournament-meta">
                                        <span class="tournament-date"><?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?></span>
                                        <span class="tournament-players"><?php echo esc_html($players_count); ?> <?php _e('players', 'poker-tournament-import'); ?></span>
                                        <span class="tournament-prize"><?php echo esc_html($currency . number_format(floatval($prize_pool ?: 0), 0)); ?></span>
                                    </div>
                                </div>
                                <?php if ($winner_name): ?>
                                    <div class="tournament-winner">
                                        <span class="winner-label"><?php _e('Winner:', 'poker-tournament-import'); ?></span>
                                        <span class="winner-name"><?php echo esc_html($winner_name); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No tournaments found in this series.', 'poker-tournament-import'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series results shortcode
     * Usage: [series_results id="123" limit="20"]
     */
    public function series_results_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'limit' => 20,
                'show_all' => 'false'
            ),
            $atts,
            'series_results'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        $series_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_id',
            'meta_value' => $series_id,
            'posts_per_page' => $atts['show_all'] === 'true' ? -1 : intval($atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();
        ?>
        <div class="series-results-content" id="series-results-<?php echo esc_attr($series_id); ?>">
            <?php if (!empty($series_tournaments)): ?>
                <div class="series-results-table-wrapper">
                    <table class="series-results-table">
                        <thead>
                            <tr>
                                <th><?php _e('Tournament', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Date', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Players', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Prize Pool', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Winner', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Actions', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            global $wpdb;
                            $table_name = $wpdb->prefix . 'poker_tournament_players';

                            foreach ($series_tournaments as $tournament):
                                $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                                $players_count = get_post_meta($tournament->ID, '_players_count', true);
                                $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                                $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                                $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                                // Get winner
                                $winner_name = '';
                                if ($tournament_uuid) {
                                    $winner = $wpdb->get_row($wpdb->prepare(
                                        "SELECT p.post_title
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                         LIMIT 1",
                                        $tournament_uuid
                                    ));
                                    if ($winner) $winner_name = $winner->post_title;
                                }
                            ?>
                                <tr>
                                    <td class="tournament-name">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>">
                                            <?php echo esc_html($tournament->post_title); ?>
                                        </a>
                                    </td>
                                    <td class="tournament-date">
                                        <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?>
                                    </td>
                                    <td class="tournament-players"><?php echo esc_html($players_count ?: '--'); ?></td>
                                    <td class="tournament-prize"><?php echo esc_html($currency . number_format(floatval($prize_pool ?: 0), 0)); ?></td>
                                    <td class="tournament-winner">
                                        <?php if ($winner_name): ?>
                                            <a href="#" class="winner-link"><?php echo esc_html($winner_name); ?></a>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                    <td class="tournament-actions">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>" class="btn-small">
                                            <?php _e('View', 'poker-tournament-import'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($atts['show_all'] !== 'true' && count($series_tournaments) >= intval($atts['limit'])): ?>
                    <div class="series-results-more">
                        <button class="load-more-results" data-series-id="<?php echo esc_attr($series_id); ?>" data-offset="<?php echo esc_attr(intval($atts['limit'])); ?>">
                            <?php _e('Load More', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?php _e('No tournaments found in this series.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series statistics shortcode
     * Usage: [series_statistics id="123"]
     */
    public function series_statistics_shortcode($atts) {
        $atts = shortcode_atts(
            array('id' => 0),
            $atts,
            'series_statistics'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get tournament UUIDs for this series
        $series_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_id',
            'meta_value' => $series_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $tournament_uuids = array();
        foreach ($series_tournaments as $tournament_id) {
            $uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if ($uuid) $tournament_uuids[] = $uuid;
        }

        if (empty($tournament_uuids)) {
            return '<p>' . __('No statistics available for this series.', 'poker-tournament-import') . '</p>';
        }

        // Get series leaderboard
        $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
        $leaderboard = $wpdb->get_results($wpdb->prepare(
            "SELECT tp.player_id, COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish,
                    AVG(tp.finish_position) as avg_finish
             FROM $table_name tp
             WHERE tp.tournament_id IN ($placeholders)
             GROUP BY tp.player_id
             ORDER BY total_points DESC, total_winnings DESC
             LIMIT 20",
            ...$tournament_uuids
        ));

        ob_start();
        ?>
        <div class="series-statistics-content" id="series-statistics-<?php echo esc_attr($series_id); ?>">
            <?php if (!empty($leaderboard)): ?>
                <div class="series-leaderboard-wrapper">
                    <h3><?php _e('Series Leaderboard', 'poker-tournament-import'); ?></h3>
                    <div class="leaderboard-table-wrapper">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Rank', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Player', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Tournaments', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Net Profit', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Total Points', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Best Finish', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Avg Finish', 'poker-tournament-import'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $index => $player):
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
                                ?>
                                    <tr class="<?php echo esc_attr($rank_class); ?>">
                                        <td class="rank">
                                            <span class="rank-number"><?php echo esc_html($index + 1); ?></span>
                                            <?php if ($index < 3): ?>
                                                <span class="rank-medal"><?php echo ($index === 0) ? '🥇' : (($index === 1) ? '🥈' : '🥉'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="player">
                                            <?php if ($player_post): ?>
                                                <a href="<?php echo get_permalink($player_post->ID); ?>" class="player-link">
                                                    <span class="player-avatar"><?php echo substr($player_post->post_title, 0, 2); ?></span>
                                                    <?php echo esc_html($player_post->post_title); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo esc_html($player->player_id); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="tournaments"><?php echo esc_html($player->tournaments_played); ?></td>
                                        <td class="winnings"><?php echo esc_html(poker_format_currency($player->total_winnings)); ?></td>
                                        <td class="points"><?php echo esc_html(number_format($player->total_points, 1)); ?></td>
                                        <td class="best-finish"><?php echo esc_html($player->best_finish); ?><?php echo get_ordinal_suffix($player->best_finish); ?></td>
                                        <td class="avg-finish"><?php echo esc_html(number_format($player->avg_finish, 1)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p><?php _e('No statistics available for this series.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series players shortcode
     * Usage: [series_players id="123"]
     */
    public function series_players_shortcode($atts) {
        $atts = shortcode_atts(
            array('id' => 0),
            $atts,
            'series_players'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get tournament UUIDs for this series
        $series_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_series_id',
            'meta_value' => $series_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $tournament_uuids = array();
        foreach ($series_tournaments as $tournament_id) {
            $uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if ($uuid) $tournament_uuids[] = $uuid;
        }

        if (empty($tournament_uuids)) {
            return '<p>' . __('No players found in this series.', 'poker-tournament-import') . '</p>';
        }

        // Get all unique players
        $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT tp.player_id, COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish
             FROM $table_name tp
             WHERE tp.tournament_id IN ($placeholders)
             GROUP BY tp.player_id
             ORDER BY player_name ASC",
            ...$tournament_uuids
        ));

        ob_start();
        ?>
        <div class="series-players-content">
            <?php if (!empty($players)): ?>
                <div class="players-search">
                    <input type="search" id="player-search" placeholder="<?php _e('Search players...', 'poker-tournament-import'); ?>">
                    <div class="search-results-count">
                        <span id="visible-players"><?php echo count($players); ?></span> / <span id="total-players"><?php echo count($players); ?></span> <?php _e('players', 'poker-tournament-import'); ?>
                    </div>
                </div>

                <div class="players-grid">
                    <?php foreach ($players as $player):
                        $player_post = $wpdb->get_row($wpdb->prepare(
                            "SELECT p.ID, p.post_title
                             FROM {$wpdb->postmeta} pm
                             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                             WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                             LIMIT 1",
                            $player->player_id
                        ));
                    ?>
                        <div class="player-card" data-player-name="<?php echo esc_attr(strtolower($player_post->post_title ?? '')); ?>">
                            <?php if ($player_post): ?>
                                <a href="<?php echo get_permalink($player_post->ID); ?>" class="player-link">
                                    <div class="player-avatar"><?php echo substr($player_post->post_title, 0, 2); ?></div>
                                    <div class="player-info">
                                        <h4 class="player-name"><?php echo esc_html($player_post->post_title); ?></h4>
                                        <div class="player-stats">
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->tournaments_played); ?></span>
                                                <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html(poker_format_currency($player->total_winnings)); ?></span>
                                                <span class="stat-label"><?php _e('Winnings', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html(number_format($player->total_points, 0)); ?></span>
                                                <span class="stat-label"><?php _e('Points', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->best_finish); ?><?php echo get_ordinal_suffix($player->best_finish); ?></span>
                                                <span class="stat-label"><?php _e('Best', 'poker-tournament-import'); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="player-card-unlinked">
                                    <div class="player-avatar">?</div>
                                    <div class="player-info">
                                        <h4 class="player-name"><?php echo esc_html($player->player_id); ?></h4>
                                        <div class="player-stats">
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->tournaments_played); ?></span>
                                                <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No players found in this series.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series leaderboard shortcode
     * Usage: [series_leaderboard id="123" limit="10"]
     */
    public function series_leaderboard_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'limit' => 10
            ),
            $atts,
            'series_leaderboard'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        // For now, delegate to series statistics with limited results
        return $this->series_statistics_shortcode($atts);
    }

    /**
     * Season tab navigation shortcode
     * Usage: [season_tabs active="overview" season_id="123"]
     */
    public function season_tabs_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'active' => 'overview',
                'season_id' => 0
            ),
            $atts,
            'season_tabs'
        );

        $season_id = intval($atts['season_id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        $tabs = array(
            'overview' => __('Overview', 'poker-tournament-import'),
            'results' => __('Results', 'poker-tournament-import'),
            'statistics' => __('Statistics', 'poker-tournament-import'),
            'players' => __('Players', 'poker-tournament-import')
        );

        ob_start();
        ?>
        <div class="season-tabs-container" id="season-tabs-<?php echo esc_attr($season_id); ?>" data-season-id="<?php echo esc_attr($season_id); ?>">
            <div class="season-tabs-nav">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <button class="season-tab-btn <?php echo $atts['active'] === $tab_key ? 'active' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab_key); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="series-tabs-content">
                <?php
                // Load active tab content
                echo do_shortcode('[season_' . $atts['active'] . ' id="' . $season_id . '"]');
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Season overview shortcode
     * Usage: [season_overview id="123"]
     */
    public function season_overview_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'limit' => 10
            ),
            $atts,
            'season_overview'
        );

        $season_id = intval($atts['id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        $season = get_post($season_id);
        if (!$season || $season->post_type !== 'tournament_season') {
            return '<p>' . __('Season not found', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get season statistics
        $season_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_season_id',
            'meta_value' => $season_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $total_tournaments = count($season_tournaments);
        $total_players = 0;
        $total_prize_pool = 0;
        $unique_players = array();
        $tournament_uuids = array();

        foreach ($season_tournaments as $tournament) {
            $players_count = get_post_meta($tournament->ID, '_players_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);

            $total_players += intval($players_count);
            $total_prize_pool += floatval($prize_pool);

            if ($tournament_uuid) {
                $tournament_uuids[] = $tournament_uuid;
                $players = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT player_id FROM $table_name WHERE tournament_id = %s",
                    $tournament_uuid
                ));
                $unique_players = array_merge($unique_players, $players);
            }
        }

        $unique_players_count = count(array_unique($unique_players));
        $avg_prize_pool = $total_tournaments > 0 ? $total_prize_pool / $total_tournaments : 0;

        // Get top player
        $top_player = null;
        if (!empty($tournament_uuids)) {
            $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
            $query = $wpdb->prepare(
                "SELECT tp.player_id, SUM(tp.points) as total_points,
                        SUM(tp.winnings) as total_winnings,
                        MIN(tp.finish_position) as best_finish
                 FROM $table_name tp
                 WHERE tp.tournament_id IN ($placeholders)
                 GROUP BY tp.player_id
                 ORDER BY total_points DESC
                 LIMIT 1",
                ...$tournament_uuids
            );
            $top_player_data = $wpdb->get_row($query);

            if ($top_player_data) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                     LIMIT 1",
                    $top_player_data->player_id
                ));

                if ($player_post) {
                    $top_player = array(
                        'name' => $player_post->post_title,
                        'id' => $player_post->ID,
                        'points' => $top_player_data->total_points,
                        'winnings' => $top_player_data->total_winnings,
                        'best_finish' => $top_player_data->best_finish
                    );
                }
            }
        }

        ob_start();
        ?>
        <div class="season-overview-content" id="season-overview-<?php echo esc_attr($season_id); ?>">
            <!-- Statistics Cards -->
            <div class="season-stats-grid" id="season-stats-<?php echo esc_attr($season_id); ?>">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($total_tournaments); ?></div>
                    <div class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($unique_players_count); ?></div>
                    <div class="stat-label"><?php _e('Unique Players', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($total_prize_pool ?: 0))); ?></div>
                    <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($avg_prize_pool ?: 0))); ?></div>
                    <div class="stat-label"><?php _e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
            </div>

            <!-- Best Player -->
            <?php if ($top_player): ?>
            <div class="best-player-card" id="season-best-player-<?php echo esc_attr($season_id); ?>">
                <h3><?php _e('Best Player', 'poker-tournament-import'); ?></h3>
                <div class="player-info">
                    <div class="player-avatar"><?php echo substr($top_player['name'], 0, 2); ?></div>
                    <div class="player-details">
                        <a href="<?php echo get_permalink($top_player['id']); ?>" class="player-name">
                            <?php echo esc_html($top_player['name']); ?>
                        </a>
                        <div class="player-stats">
                            <span class="player-points"><?php echo esc_html(number_format($top_player['points'], 0)); ?> <?php _e('points', 'poker-tournament-import'); ?></span>
                            <span class="player-winnings"><?php echo esc_html(poker_format_currency($top_player['winnings'])); ?></span>
                            <span class="player-best"><?php _e('Best:', 'poker-tournament-import'); ?> <?php echo esc_html($top_player['best_finish']); ?><?php echo get_ordinal_suffix($top_player['best_finish']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Tournaments -->
            <div class="recent-tournaments-section" id="recent-tournaments-<?php echo esc_attr($season_id); ?>">
                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                <?php
                $recent_tournaments = array_slice($season_tournaments, 0, intval($atts['limit']));
                if (!empty($recent_tournaments)):
                ?>
                    <div class="recent-tournaments-list">
                        <?php foreach ($recent_tournaments as $tournament):
                            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                            $players_count = get_post_meta($tournament->ID, '_players_count', true);
                            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                            $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                            $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                            // Get winner
                            $winner_name = '';
                            if ($tournament_uuid) {
                                $winner = $wpdb->get_row($wpdb->prepare(
                                    "SELECT p.post_title
                                     FROM $table_name tp
                                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                     WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                     LIMIT 1",
                                    $tournament_uuid
                                ));
                                if ($winner) $winner_name = $winner->post_title;
                            }
                        ?>
                            <div class="recent-tournament-item">
                                <div class="tournament-info">
                                    <div class="tournament-name">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>"><?php echo esc_html($tournament->post_title); ?></a>
                                    </div>
                                    <div class="tournament-meta">
                                        <span class="tournament-date"><?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?></span>
                                        <span class="tournament-players"><?php echo esc_html($players_count); ?> <?php _e('players', 'poker-tournament-import'); ?></span>
                                        <span class="tournament-prize"><?php echo esc_html($currency . number_format(floatval($prize_pool ?: 0), 0)); ?></span>
                                    </div>
                                </div>
                                <?php if ($winner_name): ?>
                                    <div class="tournament-winner">
                                        <span class="winner-label"><?php _e('Winner:', 'poker-tournament-import'); ?></span>
                                        <span class="winner-name"><?php echo esc_html($winner_name); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No tournaments found in this season.', 'poker-tournament-import'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Season results shortcode
     * Usage: [season_results id="123" limit="20"]
     */
    public function season_results_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'limit' => 20,
                'show_all' => 'false'
            ),
            $atts,
            'season_results'
        );

        $season_id = intval($atts['id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        $season_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_season_id',
            'meta_value' => $season_id,
            'posts_per_page' => $atts['show_all'] === 'true' ? -1 : intval($atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();
        ?>
        <div class="season-results-content" id="season-results-<?php echo esc_attr($season_id); ?>">
            <?php if (!empty($season_tournaments)): ?>
                <div class="series-results-table-wrapper">
                    <table class="series-results-table">
                        <thead>
                            <tr>
                                <th><?php _e('Tournament', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Date', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Players', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Prize Pool', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Winner', 'poker-tournament-import'); ?></th>
                                <th><?php _e('Actions', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            global $wpdb;
                            $table_name = $wpdb->prefix . 'poker_tournament_players';

                            foreach ($season_tournaments as $tournament):
                                $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
                                $players_count = get_post_meta($tournament->ID, '_players_count', true);
                                $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
                                $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
                                $currency = get_post_meta($tournament->ID, '_currency', true) ?: '$';

                                // Get winner
                                $winner_name = '';
                                if ($tournament_uuid) {
                                    $winner = $wpdb->get_row($wpdb->prepare(
                                        "SELECT p.post_title
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                         LIMIT 1",
                                        $tournament_uuid
                                    ));
                                    if ($winner) $winner_name = $winner->post_title;
                                }
                            ?>
                                <tr>
                                    <td class="tournament-name">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>">
                                            <?php echo esc_html($tournament->post_title); ?>
                                        </a>
                                    </td>
                                    <td class="tournament-date">
                                        <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?>
                                    </td>
                                    <td class="tournament-players"><?php echo esc_html($players_count ?: '--'); ?></td>
                                    <td class="tournament-prize"><?php echo esc_html($currency . number_format(floatval($prize_pool ?: 0), 0)); ?></td>
                                    <td class="tournament-winner">
                                        <?php if ($winner_name): ?>
                                            <a href="#" class="winner-link"><?php echo esc_html($winner_name); ?></a>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                    <td class="tournament-actions">
                                        <a href="<?php echo get_permalink($tournament->ID); ?>" class="btn-small">
                                            <?php _e('View', 'poker-tournament-import'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p><?php _e('No tournaments found in this season.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Season statistics shortcode
     * Usage: [season_statistics id="123"]
     */
    public function season_statistics_shortcode($atts) {
        $atts = shortcode_atts(
            array('id' => 0),
            $atts,
            'season_statistics'
        );

        $season_id = intval($atts['id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get tournament UUIDs for this season
        $season_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_season_id',
            'meta_value' => $season_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $tournament_uuids = array();
        foreach ($season_tournaments as $tournament_id) {
            $uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if ($uuid) $tournament_uuids[] = $uuid;
        }

        if (empty($tournament_uuids)) {
            return '<p>' . __('No statistics available for this season.', 'poker-tournament-import') . '</p>';
        }

        // Get season leaderboard
        $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
        $leaderboard = $wpdb->get_results($wpdb->prepare(
            "SELECT tp.player_id, COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish,
                    AVG(tp.finish_position) as avg_finish
             FROM $table_name tp
             WHERE tp.tournament_id IN ($placeholders)
             GROUP BY tp.player_id
             ORDER BY total_points DESC, total_winnings DESC
             LIMIT 20",
            ...$tournament_uuids
        ));

        ob_start();
        ?>
        <div class="season-statistics-content" id="season-statistics-<?php echo esc_attr($season_id); ?>">
            <?php if (!empty($leaderboard)): ?>
                <div class="series-leaderboard-wrapper">
                    <h3><?php _e('Season Leaderboard', 'poker-tournament-import'); ?></h3>
                    <div class="leaderboard-table-wrapper">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Rank', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Player', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Tournaments', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Net Profit', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Total Points', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Best Finish', 'poker-tournament-import'); ?></th>
                                    <th><?php _e('Avg Finish', 'poker-tournament-import'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $index => $player):
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
                                ?>
                                    <tr class="<?php echo esc_attr($rank_class); ?>">
                                        <td class="rank">
                                            <span class="rank-number"><?php echo esc_html($index + 1); ?></span>
                                            <?php if ($index < 3): ?>
                                                <span class="rank-medal"><?php echo ($index === 0) ? '🥇' : (($index === 1) ? '🥈' : '🥉'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="player">
                                            <?php if ($player_post): ?>
                                                <a href="<?php echo get_permalink($player_post->ID); ?>" class="player-link">
                                                    <span class="player-avatar"><?php echo substr($player_post->post_title, 0, 2); ?></span>
                                                    <?php echo esc_html($player_post->post_title); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo esc_html($player->player_id); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="tournaments"><?php echo esc_html($player->tournaments_played); ?></td>
                                        <td class="winnings"><?php echo esc_html(poker_format_currency($player->total_winnings)); ?></td>
                                        <td class="points"><?php echo esc_html(number_format($player->total_points, 1)); ?></td>
                                        <td class="best-finish"><?php echo esc_html($player->best_finish); ?><?php echo get_ordinal_suffix($player->best_finish); ?></td>
                                        <td class="avg-finish"><?php echo esc_html(number_format($player->avg_finish, 1)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p><?php _e('No statistics available for this season.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Season players shortcode
     * Usage: [season_players id="123"]
     */
    public function season_players_shortcode($atts) {
        $atts = shortcode_atts(
            array('id' => 0),
            $atts,
            'season_players'
        );

        $season_id = intval($atts['id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get tournament UUIDs for this season
        $season_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'meta_key' => '_season_id',
            'meta_value' => $season_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $tournament_uuids = array();
        foreach ($season_tournaments as $tournament_id) {
            $uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
            if ($uuid) $tournament_uuids[] = $uuid;
        }

        if (empty($tournament_uuids)) {
            return '<p>' . __('No players found in this season.', 'poker-tournament-import') . '</p>';
        }

        // Get all unique players
        $placeholders = implode(',', array_fill(0, count($tournament_uuids), '%s'));
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT tp.player_id, COUNT(*) as tournaments_played,
                    SUM(tp.winnings) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish
             FROM $table_name tp
             WHERE tp.tournament_id IN ($placeholders)
             GROUP BY tp.player_id
             ORDER BY player_name ASC",
            ...$tournament_uuids
        ));

        ob_start();
        ?>
        <div class="series-players-content">
            <?php if (!empty($players)): ?>
                <div class="players-search">
                    <input type="search" id="player-search" placeholder="<?php _e('Search players...', 'poker-tournament-import'); ?>">
                    <div class="search-results-count">
                        <span id="visible-players"><?php echo count($players); ?></span> / <span id="total-players"><?php echo count($players); ?></span> <?php _e('players', 'poker-tournament-import'); ?>
                    </div>
                </div>

                <div class="players-grid">
                    <?php foreach ($players as $player):
                        $player_post = $wpdb->get_row($wpdb->prepare(
                            "SELECT p.ID, p.post_title
                             FROM {$wpdb->postmeta} pm
                             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                             WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                             LIMIT 1",
                            $player->player_id
                        ));
                    ?>
                        <div class="player-card" data-player-name="<?php echo esc_attr(strtolower($player_post->post_title ?? '')); ?>">
                            <?php if ($player_post): ?>
                                <a href="<?php echo get_permalink($player_post->ID); ?>" class="player-link">
                                    <div class="player-avatar"><?php echo substr($player_post->post_title, 0, 2); ?></div>
                                    <div class="player-info">
                                        <h4 class="player-name"><?php echo esc_html($player_post->post_title); ?></h4>
                                        <div class="player-stats">
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->tournaments_played); ?></span>
                                                <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html(poker_format_currency($player->total_winnings)); ?></span>
                                                <span class="stat-label"><?php _e('Winnings', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html(number_format($player->total_points, 0)); ?></span>
                                                <span class="stat-label"><?php _e('Points', 'poker-tournament-import'); ?></span>
                                            </span>
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->best_finish); ?><?php echo get_ordinal_suffix($player->best_finish); ?></span>
                                                <span class="stat-label"><?php _e('Best', 'poker-tournament-import'); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="player-card-unlinked">
                                    <div class="player-avatar">?</div>
                                    <div class="player-info">
                                        <h4 class="player-name"><?php echo esc_html($player->player_id); ?></h4>
                                        <div class="player-stats">
                                            <span class="stat-item">
                                                <span class="stat-value"><?php echo esc_html($player->tournaments_played); ?></span>
                                                <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No players found in this season.', 'poker-tournament-import'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Series standings shortcode
     * Usage: [series_standings id="123" formula="season_total" show_details="true"]
     */
    public function series_standings_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'formula' => '',
                'show_details' => 'false',
                'show_export' => 'true'
            ),
            $atts,
            'series_standings'
        );

        $series_id = intval($atts['id']);
        if ($series_id === 0) {
            return '<p>' . __('Series ID required', 'poker-tournament-import') . '</p>';
        }

        $series = get_post($series_id);
        if (!$series || $series->post_type !== 'tournament_series') {
            return '<p>' . __('Series not found', 'poker-tournament-import') . '</p>';
        }

        // Initialize series standings calculator
        $standings_calculator = new Poker_Series_Standings_Calculator();
        $formula_key = !empty($atts['formula']) ? $atts['formula'] : null;

        ob_start();
        ?>
        <div class="series-standings-container">
            <div class="standings-header">
                <h2><?php echo esc_html($series->post_title); ?> - <?php _e('Standings', 'poker-tournament-import'); ?></h2>

                <?php if ($atts['show_export'] === 'true'): ?>
                <div class="standings-actions">
                    <button class="export-standings" data-series-id="<?php echo esc_attr($series_id); ?>" data-formula="<?php echo esc_attr($formula_key); ?>">
                        <?php _e('Export CSV', 'poker-tournament-import'); ?>
                    </button>
                    <button class="print-standings" onclick="window.print()">
                        <?php _e('Print', 'poker-tournament-import'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Formula Information -->
            <?php if ($formula_key): ?>
            <div class="formula-info">
                <p><strong><?php _e('Formula:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($formula_key); ?></p>
            </div>
            <?php endif; ?>

            <!-- Series Statistics -->
            <?php
            $series_stats = $standings_calculator->get_series_statistics($series_id);
            if (!empty($series_stats)):
            ?>
            <div class="series-stats-summary">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html($series_stats['total_players']); ?></span>
                        <span class="stat-label"><?php _e('Players', 'poker-tournament-import'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html($series_stats['total_tournaments']); ?></span>
                        <span class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html(poker_format_currency($series_stats['total_winnings'])); ?></span>
                        <span class="stat-label"><?php _e('Total Winnings', 'poker-tournament-import'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo esc_html(number_format($series_stats['avg_points_per_player'], 1)); ?></span>
                        <span class="stat-label"><?php _e('Avg Points', 'poker-tournament-import'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Standings Table -->
            <?php
            $standings_calculator->display_series_standings_table(
                $series_id,
                $formula_key,
                $atts['show_details'] === 'true'
            );
            ?>

            <!-- Tie-breaker Legend -->
            <div class="tie-breaker-legend">
                <h4><?php _e('Tie-breaker Order', 'poker-tournament-import'); ?></h4>
                <ol>
                    <li><?php _e('Most First Place Finishes', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Most Top 3 Finishes', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Most Top 5 Finishes', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Best Single Tournament Points', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Highest Total Winnings', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Most Tournaments Played', 'poker-tournament-import'); ?></li>
                    <li><?php _e('Best Average Finish (lower is better)', 'poker-tournament-import'); ?></li>
                </ol>
            </div>
        </div>

        <style>
        .series-standings-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .standings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .standings-actions {
            display: flex;
            gap: 10px;
        }
        .export-standings,
        .print-standings {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .export-standings:hover,
        .print-standings:hover {
            background: #005a87;
        }
        .formula-info {
            background: #f0f8ff;
            padding: 10px 15px;
            border-left: 4px solid #0073aa;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .series-stats-summary {
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .tie-breaker-legend {
            margin-top: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            font-size: 14px;
        }
        .tie-breaker-legend h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .tie-breaker-legend ol {
            margin: 0;
            padding-left: 20px;
        }
        .tie-breaker-legend li {
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .standings-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.export-standings').click(function() {
                var seriesId = $(this).data('series-id');
                var formula = $(this).data('formula');

                $.post(ajaxurl, {
                    action: 'poker_export_standings',
                    series_id: seriesId,
                    formula: formula,
                    nonce: '<?php echo wp_create_nonce('poker_export_standings'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Create download link
                        var link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert('Export failed: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Season standings shortcode
     * Usage: [season_standings id="123" formula="season_total" show_details="true"]
     */
    public function season_standings_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'formula' => '',
                'show_details' => 'false',
                'show_export' => 'true'
            ),
            $atts,
            'season_standings'
        );

        $season_id = intval($atts['id']);
        if ($season_id === 0) {
            return '<p>' . __('Season ID required', 'poker-tournament-import') . '</p>';
        }

        $season = get_post($season_id);
        if (!$season || $season->post_type !== 'tournament_season') {
            return '<p>' . __('Season not found', 'poker-tournament-import') . '</p>';
        }

        // For now, use series standings calculator with season context
        $standings_calculator = new Poker_Series_Standings_Calculator();
        $formula_key = !empty($atts['formula']) ? $atts['formula'] : null;

        ob_start();
        ?>
        <div class="season-standings-container">
            <div class="standings-header">
                <h2><?php echo esc_html($season->post_title); ?> - <?php _e('Standings', 'poker-tournament-import'); ?></h2>

                <?php if ($atts['show_export'] === 'true'): ?>
                <div class="standings-actions">
                    <button class="export-standings" data-season-id="<?php echo esc_attr($season_id); ?>" data-formula="<?php echo esc_attr($formula_key); ?>">
                        <?php _e('Export CSV', 'poker-tournament-import'); ?>
                    </button>
                    <button class="print-standings" onclick="window.print()">
                        <?php _e('Print', 'poker-tournament-import'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($formula_key): ?>
            <div class="formula-info">
                <p><strong><?php _e('Formula:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($formula_key); ?></p>
            </div>
            <?php endif; ?>

            <!-- Use series standings for season -->
            <?php
            // Get series associated with this season and show standings
            $series_query = new WP_Query(array(
                'post_type' => 'tournament_series',
                'meta_query' => array(
                    array(
                        'key' => '_season_id',
                        'value' => $season_id,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));

            if ($series_query->have_posts()) {
                $series = $series_query->next_post();
                $standings_calculator->display_series_standings_table(
                    $series->ID,
                    $formula_key,
                    $atts['show_details'] === 'true'
                );
            } else {
                echo '<p>' . __('No series found for this season.', 'poker-tournament-import') . '</p>';
            }
            ?>
        </div>

        <style>
        /* Use same styles as series standings */
        .season-standings-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .standings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .standings-actions {
            display: flex;
            gap: 10px;
        }
        .export-standings,
        .print-standings {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .export-standings:hover,
        .print-standings:hover {
            background: #005a87;
        }
        .formula-info {
            background: #f0f8ff;
            padding: 10px 15px;
            border-left: 4px solid #0073aa;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .standings-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Modern Poker Dashboard shortcode
     * Usage: [poker_dashboard view="overview" limit="10" show_stats="true"]
     */
    public function poker_dashboard_shortcode($atts) {
        // Prevent execution in admin context to avoid HTML duplication
        if (is_admin()) {
            return '<!-- Poker Dashboard shortcode disabled in admin context -->';
        }

        // Require user login to access dashboard
        if (!is_user_logged_in()) {
            return '<div class="poker-login-required" style="text-align:center;padding:40px;background:#f8f9fa;border-radius:8px;margin:20px 0;">
                <h3>' . __('Login Required', 'poker-tournament-import') . '</h3>
                <p>' . __('You must be logged in to view the poker dashboard.', 'poker-tournament-import') . '</p>
                <p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button button-primary">' . __('Log In', 'poker-tournament-import') . '</a></p>
            </div>';
        }

        // Enqueue dashboard scripts when shortcode is used on frontend
        wp_enqueue_script(
            'poker-dashboard-frontend',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            POKER_TOURNAMENT_IMPORT_VERSION . '-' . filemtime(POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'assets/js/admin.js'),
            true
        );

        // Define ajaxurl for frontend context
        wp_add_inline_script(
            'poker-dashboard-frontend',
            'var ajaxurl = "' . admin_url('admin-ajax.php') . '";',
            'before'
        );

        // Localize script with dashboard nonce and settings
        wp_localize_script(
            'poker-dashboard-frontend',
            'pokerImport',
            array(
                'dashboardNonce' => wp_create_nonce('poker_dashboard_nonce'),
                'refreshNonce' => wp_create_nonce('poker_refresh_statistics'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'messages' => array(
                    'dashboardError' => __('Error loading dashboard content.', 'poker-tournament-import'),
                    'loadingTournaments' => __('Loading tournaments...', 'poker-tournament-import'),
                    'loadingPlayers' => __('Loading players...', 'poker-tournament-import'),
                    'loadingSeries' => __('Loading series...', 'poker-tournament-import'),
                    'loadingAnalytics' => __('Loading analytics...', 'poker-tournament-import')
                )
            )
        );

        $atts = shortcode_atts(
            array(
                'view' => 'overview',
                'limit' => 10,
                'show_stats' => 'true',
                'drill_through' => 'true'
            ),
            $atts,
            'poker_dashboard'
        );

        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';

        // Get current season from URL parameter
        $current_season_id = isset($_GET['season_id']) ? sanitize_text_field($_GET['season_id']) : 'all';

        // Get global statistics (season-aware)
        $global_stats = $this->get_dashboard_statistics($current_season_id);
        $recent_tournaments = $this->get_recent_tournaments(intval($atts['limit']));

        // **PHASE 1: Enhanced Player Data** (season-aware)
        if (class_exists('Poker_Statistics_Engine')) {
            $stats_engine = Poker_Statistics_Engine::get_instance();
            $player_leaderboard = $stats_engine->get_player_leaderboard(9999, $current_season_id); // Get all players for ranking
            $participation_trends = $stats_engine->get_player_participation_trends(30);
        } else {
            $player_leaderboard = $this->get_top_players(9999); // Get all players for ranking
            $participation_trends = array();
        }

        // Get active seasons for dashboard
        $active_seasons = $this->get_active_seasons(5);

        // **DEBUG**: Log data availability for troubleshooting
        if (current_user_can('manage_options')) {
            error_log("Poker Dashboard Debug: Player Leaderboard Count: " . count($player_leaderboard));
            error_log("Poker Dashboard Debug: Active Seasons Count: " . count($active_seasons));
            error_log("Poker Dashboard Debug: Recent Tournaments Count: " . count($recent_tournaments));

            if (empty($player_leaderboard)) {
                error_log("Poker Dashboard Debug: Player leaderboard empty - checking if statistics engine exists: " . (class_exists('Poker_Statistics_Engine') ? 'YES' : 'NO'));
            }

            if (empty($active_seasons)) {
                error_log("Poker Dashboard Debug: Active seasons empty - checking tournament season count: " . wp_count_posts('tournament_season')->publish);
            }
        }

        // Get current season from URL parameter
        $current_season_id = isset($_GET['season_id']) ? sanitize_text_field($_GET['season_id']) : 'all';

        // Get all seasons with tournament counts
        $all_seasons = get_posts(array(
            'post_type' => 'tournament_season',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ob_start();
        ?>
        <div class="poker-dashboard-container" id="poker-dashboard-main">

            <!-- Dashboard Header -->
            <header class="dashboard-header">
                <div class="dashboard-title">
                    <h1><?php _e('Poker Tournament Dashboard', 'poker-tournament-import'); ?></h1>
                    <p class="dashboard-subtitle"><?php _e('Complete tournament analytics and insights', 'poker-tournament-import'); ?></p>
                </div>
                <div class="dashboard-actions">
                    <div class="season-selector-wrapper">
                        <label for="season-selector"><?php _e('Season:', 'poker-tournament-import'); ?></label>
                        <select id="season-selector" class="season-filter">
                            <option value="all" <?php selected($current_season_id, 'all'); ?>><?php _e('All Seasons', 'poker-tournament-import'); ?></option>
                            <?php foreach ($all_seasons as $season):
                                // Count tournaments in this season
                                $tournament_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                                     WHERE p.post_type = 'tournament'
                                     AND p.post_status = 'publish'
                                     AND pm.meta_key = '_season_id'
                                     AND pm.meta_value = %d",
                                    $season->ID
                                ));
                            ?>
                                <option value="<?php echo esc_attr($season->ID); ?>" <?php selected($current_season_id, $season->ID); ?>>
                                    <?php echo esc_html($season->post_title); ?> (<?php echo intval($tournament_count); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="dashboard-refresh" id="refresh-statistics" onclick="refreshStatistics()">
                        <i class="icon-refresh"></i> <span class="button-text"><?php _e('Refresh Statistics', 'poker-tournament-import'); ?></span>
                    </button>
                    <button class="dashboard-export" data-export="dashboard">
                        <i class="icon-download"></i> <?php _e('Export', 'poker-tournament-import'); ?>
                    </button>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <nav class="dashboard-nav">
                <div class="nav-tabs">
                    <button class="nav-tab active" data-view="overview">
                        <i class="icon-dashboard"></i> <?php _e('Overview', 'poker-tournament-import'); ?>
                    </button>
                    <button class="nav-tab" data-view="tournaments">
                        <i class="icon-trophy"></i> <?php _e('Tournaments', 'poker-tournament-import'); ?>
                    </button>
                    <button class="nav-tab" data-view="players">
                        <i class="icon-users"></i> <?php _e('Players', 'poker-tournament-import'); ?>
                    </button>
                    <button class="nav-tab" data-view="seasons">
                        <i class="icon-calendar"></i> <?php _e('Seasons', 'poker-tournament-import'); ?>
                    </button>
                    <button class="nav-tab" data-view="analytics">
                        <i class="icon-chart"></i> <?php _e('Analytics', 'poker-tournament-import'); ?>
                    </button>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <main class="dashboard-content">

                <!-- Overview Tab -->
                <div class="dashboard-tab active" id="overview-tab">

                    <!-- Key Statistics Cards -->
                    <section class="dashboard-stats-grid">
                        <div class="stat-card primary clickable" data-drill="tournaments">
                            <div class="stat-icon">🏆</div>
                            <div class="stat-number"><?php echo esc_html($global_stats['total_tournaments']); ?></div>
                            <div class="stat-label"><?php _e('Total Tournaments', 'poker-tournament-import'); ?></div>
                            <div class="stat-change">+<?php echo esc_html($global_stats['recent_tournaments']); ?> <?php _e('this month', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card success clickable" data-drill="players">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number"><?php echo esc_html($global_stats['total_players']); ?></div>
                            <div class="stat-label"><?php _e('Unique Players', 'poker-tournament-import'); ?></div>
                            <div class="stat-change">+<?php echo esc_html($global_stats['new_players']); ?> <?php _e('new players', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card info clickable" data-drill="prizes">
                            <div class="stat-icon">💰</div>
                            <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($global_stats['total_prize_pool'] ?: 0))); ?></div>
                            <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                            <div class="stat-change"><?php echo esc_html(poker_format_currency(floatval($global_stats['avg_prize_pool'] ?: 0))); ?> <?php _e('average', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card warning clickable" data-drill="events">
                            <div class="stat-icon">📅</div>
                            <div class="stat-number"><?php echo esc_html($global_stats['active_series']); ?></div>
                            <div class="stat-label"><?php _e('Active Series', 'poker-tournament-import'); ?></div>
                            <div class="stat-change"><?php echo esc_html($global_stats['upcoming_events']); ?> <?php _e('upcoming', 'poker-tournament-import'); ?></div>
                        </div>
                    </section>

                    <!-- Recent Activity Grid -->
                    <section class="dashboard-grid">
                        <!-- Recent Tournaments -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                                <button class="card-action" data-drill="all-tournaments">
                                    <?php _e('View All', 'poker-tournament-import'); ?> →
                                </button>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($recent_tournaments)): ?>
                                    <div class="tournaments-list">
                                        <?php foreach ($recent_tournaments as $tournament): ?>
                                            <div class="tournament-item clickable" data-tournament-id="<?php echo esc_attr($tournament->ID); ?>">
                                                <div class="tournament-info">
                                                    <div class="tournament-name"><?php echo esc_html($tournament->post_title); ?></div>
                                                    <div class="tournament-meta">
                                                        <span class="date"><?php echo esc_html(date_i18n('M j', strtotime($tournament->tournament_date))); ?></span>
                                                        <span class="players"><?php echo esc_html($tournament->players_count); ?> <?php _e('players', 'poker-tournament-import'); ?></span>
                                                        <span class="prize"><?php echo esc_html(poker_format_currency(floatval($tournament->prize_pool ?: 0))); ?></span>
                                                    </div>
                                                </div>
                                                <div class="tournament-winner">
                                                    <?php if ($tournament->winner_name): ?>
                                                        <span class="winner-avatar"><?php echo substr($tournament->winner_name, 0, 2); ?></span>
                                                        <span class="winner-name"><?php echo esc_html($tournament->winner_name); ?></span>
                                                    <?php else: ?>
                                                        <span class="no-winner">--</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-data"><?php _e('No recent tournaments found.', 'poker-tournament-import'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top Players -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><?php _e('Top Players', 'poker-tournament-import'); ?></h3>
                                <button class="card-action" data-drill="leaderboard">
                                    <?php _e('Full Leaderboard', 'poker-tournament-import'); ?> →
                                </button>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($player_leaderboard)): ?>
                                    <div class="players-list">
                                        <?php foreach ($player_leaderboard as $index => $player): ?>
                                            <div class="player-item clickable" data-player-id="<?php echo esc_attr($player->player_post_id); ?>">
                                                <div class="player-rank"><?php echo esc_html($index + 1); ?></div>
                                                <div class="player-avatar"><?php echo esc_html(substr($player->player_name, 0, 2)); ?></div>
                                                <div class="player-info">
                                                    <div class="player-name"><?php echo esc_html($player->player_name); ?></div>
                                                    <div class="player-stats">
                                                        <span class="winnings"><?php echo esc_html(poker_format_currency($player->total_winnings)); ?></span>
                                                        <span class="tournaments"><?php echo esc_html($player->tournaments_played); ?> <?php _e('events', 'poker-tournament-import'); ?></span>
                                                    </div>
                                                </div>
                                                <div class="player-achievement">
                                                    <?php if ($player->best_finish == 1): ?>
                                                        <span class="badge gold">🏆</span>
                                                    <?php elseif ($player->best_finish <= 3): ?>
                                                        <span class="badge bronze">🥈</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-data"><?php _e('No player data available.', 'poker-tournament-import'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Active Seasons -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><?php _e('Active Seasons', 'poker-tournament-import'); ?></h3>
                                <button class="card-action" data-drill="all-seasons">
                                    <?php _e('All Seasons', 'poker-tournament-import'); ?> →
                                </button>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($active_seasons)): ?>
                                    <div class="seasons-list">
                                        <?php foreach ($active_seasons as $season): ?>
                                            <a href="<?php echo get_permalink($season->ID); ?>" class="season-item clickable" data-season-id="<?php echo esc_attr($season->ID); ?>">
                                                <div class="season-info">
                                                    <div class="season-name"><?php echo esc_html($season->post_title); ?></div>
                                                    <div class="season-meta">
                                                        <span class="tournaments"><?php echo esc_html($season->tournament_count); ?> <?php _e('tournaments', 'poker-tournament-import'); ?></span>
                                                        <span class="players"><?php echo esc_html($season->player_count); ?> <?php _e('players', 'poker-tournament-import'); ?></span>
                                                    </div>
                                                </div>
                                                <div class="season-progress">
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php echo esc_attr($season->progress_percent); ?>%"></div>
                                                    </div>
                                                    <span class="progress-text"><?php echo esc_html($season->progress_percent); ?>%</span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-data"><?php _e('No active seasons found.', 'poker-tournament-import'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><?php _e('Quick Actions', 'poker-tournament-import'); ?></h3>
                            </div>
                            <div class="card-content">
                                <div class="quick-actions-grid">
                                    <button class="quick-action-btn" data-action="import">
                                        <i class="icon-upload"></i>
                                        <span><?php _e('Import Tournament', 'poker-tournament-import'); ?></span>
                                    </button>
                                    <button class="quick-action-btn" data-action="create-series">
                                        <i class="icon-plus"></i>
                                        <span><?php _e('Create Series', 'poker-tournament-import'); ?></span>
                                    </button>
                                    <button class="quick-action-btn" data-action="view-calendar">
                                        <i class="icon-calendar"></i>
                                        <span><?php _e('Tournament Calendar', 'poker-tournament-import'); ?></span>
                                    </button>
                                    <button class="quick-action-btn" data-action="generate-report">
                                        <i class="icon-report"></i>
                                        <span><?php _e('Generate Report', 'poker-tournament-import'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Tournaments Tab (Loaded via AJAX) -->
                <div class="dashboard-tab" id="tournaments-tab">
                    <div class="loading-spinner">
                        <i class="icon-spinner"></i> <?php _e('Loading tournaments...', 'poker-tournament-import'); ?>
                    </div>
                </div>

                <!-- Players Tab -->
                <div class="dashboard-tab" id="players-tab">
                    <!-- **PHASE 1: Enhanced Player Statistics** -->

                    <!-- Player Stats Summary -->
                    <section class="dashboard-stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number"><?php echo esc_html($global_stats['total_unique_players']); ?></div>
                            <div class="stat-label"><?php _e('Total Players', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-number"><?php echo esc_html(number_format(floatval($global_stats['average_finish_position'] ?: 0), 1)); ?></div>
                            <div class="stat-label"><?php _e('Average Finish', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon">💰</div>
                            <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($global_stats['total_payouts'] ?: 0))); ?></div>
                            <div class="stat-label"><?php _e('Total Payouts', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon">🏆</div>
                            <div class="stat-number"><?php echo esc_html(poker_format_currency(floatval($global_stats['highest_single_payout'] ?: 0))); ?></div>
                            <div class="stat-label"><?php _e('Highest Payout', 'poker-tournament-import'); ?></div>
                        </div>
                    </section>

                    <!-- Player Leaderboard -->
                    <section class="dashboard-grid">
                        <div class="dashboard-card full-width">
                            <div class="card-header">
                                <h3><?php _e('Ranking', 'poker-tournament-import'); ?></h3>
                                <button class="card-action" data-drill="all-players">
                                    <?php _e('View All Players', 'poker-tournament-import'); ?> →
                                </button>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($player_leaderboard)): ?>
                                    <div class="player-leaderboard-scroll-wrapper">
                                        <div class="player-leaderboard-table">
                                            <div class="table-header">
                                                <div class="header-rank"><?php _e('Rank', 'poker-tournament-import'); ?></div>
                                                <div class="header-player"><?php _e('Player', 'poker-tournament-import'); ?></div>
                                                <div class="header-tournaments"><?php _e('Tournaments', 'poker-tournament-import'); ?></div>
                                                <div class="header-first"><?php _e('1st', 'poker-tournament-import'); ?></div>
                                                <div class="header-second"><?php _e('2nd', 'poker-tournament-import'); ?></div>
                                                <div class="header-third"><?php _e('3rd', 'poker-tournament-import'); ?></div>
                                                <div class="header-bubble"><?php _e('Bubble', 'poker-tournament-import'); ?></div>
                                                <div class="header-last"><?php _e('Last', 'poker-tournament-import'); ?></div>
                                                <div class="header-hits"><?php _e('Hits', 'poker-tournament-import'); ?></div>
                                                <div class="header-points sortable" data-sort="points"><?php _e('Points', 'poker-tournament-import'); ?><span class="sort-indicator"></span></div>
                                                <div class="header-best"><?php _e('Best', 'poker-tournament-import'); ?></div>
                                                <div class="header-avg"><?php _e('Avg', 'poker-tournament-import'); ?></div>
                                                <div class="header-season-points sortable active" data-sort="season_points"><?php _e('Season Pts', 'poker-tournament-import'); ?><span class="sort-indicator">▼</span></div>
                                            </div>
                                        <?php foreach ($player_leaderboard as $index => $player): ?>
                                            <div class="table-row" data-player-id="<?php echo esc_attr($player->player_id); ?>"
                                                 data-points="<?php echo esc_attr($player->total_points); ?>"
                                                 data-season-points="<?php echo esc_attr($player->season_points); ?>">
                                                <div class="rank-cell"><?php echo $index + 1; ?></div>
                                                <div class="player-cell">
                                                    <?php if ($player->player_post_id): ?>
                                                        <a href="<?php echo esc_url(get_permalink($player->player_post_id)); ?>"
                                                           class="player-link drill-through"
                                                           data-drill-type="player"
                                                           data-player-id="<?php echo esc_attr($player->player_id); ?>"
                                                           data-player-post-id="<?php echo esc_attr($player->player_post_id); ?>">
                                                            <?php echo esc_html($player->player_name); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="player-name drill-through"
                                                              data-drill-type="player"
                                                              data-player-id="<?php echo esc_attr($player->player_id); ?>"
                                                              style="color: #0073aa; cursor: pointer; text-decoration: underline;">
                                                            <?php echo esc_html($player->player_name); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tournaments-cell"><?php echo esc_html($player->tournaments_played); ?></div>
                                                <div class="first-cell"><?php echo esc_html($player->first_place_count ?? 0); ?></div>
                                                <div class="second-cell"><?php echo esc_html($player->second_place_count ?? 0); ?></div>
                                                <div class="third-cell"><?php echo esc_html($player->third_place_count ?? 0); ?></div>
                                                <div class="bubble-cell"><?php echo esc_html($player->bubble_count ?? 0); ?></div>
                                                <div class="last-cell"><?php echo esc_html($player->last_place_count ?? 0); ?></div>
                                                <div class="hits-cell"><?php echo esc_html($player->total_hits ?? 0); ?></div>
                                                <div class="points-cell"><?php echo esc_html(number_format(floatval($player->total_points), 1)); ?></div>
                                                <div class="best-finish-cell"><?php echo esc_html($player->best_finish); ?></div>
                                                <div class="avg-finish-cell"><?php echo esc_html(number_format(floatval($player->avg_finish), 1)); ?></div>
                                                <div class="season-points-cell"><?php echo esc_html(number_format(floatval($player->season_points), 1)); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="no-data"><?php _e('No player data available. Import tournaments to see player statistics.', 'poker-tournament-import'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- Participation Trends -->
                    <?php if (!empty($participation_trends)): ?>
                    <section class="dashboard-card">
                        <div class="card-header">
                            <h3><?php _e('30-Day Participation Trends', 'poker-tournament-import'); ?></h3>
                        </div>
                        <div class="card-content">
                            <div class="trends-chart">
                                <?php foreach (array_slice($participation_trends, 0, 7) as $trend): ?>
                                    <div class="trend-item">
                                        <div class="trend-date"><?php echo esc_html(date('M j', strtotime($trend->date))); ?></div>
                                        <div class="trend-stats">
                                            <span class="trend-players"><?php echo esc_html($trend->unique_players); ?> players</span>
                                            <span class="trend-entries"><?php echo esc_html($trend->total_entries); ?> entries</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Series Tab (Loaded via AJAX) -->
                <div class="dashboard-tab" id="series-tab">
                    <div class="loading-spinner">
                        <i class="icon-spinner"></i> <?php _e('Loading series...', 'poker-tournament-import'); ?>
                    </div>
                </div>

                <!-- Seasons Tab (Loaded via AJAX) -->
                <div class="dashboard-tab" id="seasons-tab">
                    <div class="loading-spinner">
                        <i class="icon-spinner"></i> <?php _e('Loading seasons...', 'poker-tournament-import'); ?>
                    </div>
                </div>

                <!-- Analytics Tab (Loaded via AJAX) -->
                <div class="dashboard-tab" id="analytics-tab">
                    <div class="loading-spinner">
                        <i class="icon-spinner"></i> <?php _e('Loading analytics...', 'poker-tournament-import'); ?>
                    </div>
                </div>

            </main>
        </div>

        <!-- Dashboard Styles -->
        <style>
        .poker-dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .dashboard-title h1 {
            margin: 0 0 5px 0;
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .dashboard-subtitle {
            margin: 0;
            color: #666;
            font-size: 16px;
        }

        .dashboard-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .season-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 2px solid #e0e0e0;
            padding: 10px 16px;
            border-radius: 8px;
        }

        .season-selector-wrapper label {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin: 0;
        }

        .season-filter {
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 500;
            color: #0073aa;
            cursor: pointer;
            outline: none;
            padding: 0;
            min-width: 120px;
        }

        .season-filter:focus {
            outline: 2px solid #0073aa;
            outline-offset: 2px;
            border-radius: 4px;
        }

        .dashboard-refresh,
        .dashboard-export {
            background: #fff;
            border: 2px solid #e0e0e0;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .dashboard-refresh:hover,
        .dashboard-export:hover {
            border-color: #0073aa;
            background: #f0f8ff;
        }

        .dashboard-nav {
            margin-bottom: 30px;
        }

        .nav-tabs {
            display: flex;
            background: #fff;
            border-radius: 12px;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .nav-tab {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nav-tab.active {
            background: #0073aa;
            color: white;
        }

        .nav-tab:hover:not(.active) {
            background: #f5f5f5;
        }

        .dashboard-tab {
            display: none;
        }

        .dashboard-tab.active {
            display: block;
        }

        .dashboard-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card.clickable {
            cursor: pointer;
        }

        .stat-card.clickable:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .stat-card.primary { border-left: 4px solid #0073aa; }
        .stat-card.success { border-left: 4px solid #46b450; }
        .stat-card.info { border-left: 4px solid #666; }
        .stat-card.warning { border-left: 4px solid #ffb900; }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-change {
            color: #46b450;
            font-size: 12px;
            font-weight: 500;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .dashboard-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .card-action {
            background: none;
            border: none;
            color: #0073aa;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .card-action:hover {
            color: #005a87;
        }

        .card-content {
            padding: 20px;
        }

        .tournaments-list,
        .players-list,
        .series-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .tournament-item,
        .player-item,
        .series-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .tournament-item:hover,
        .player-item:hover,
        .series-item:hover {
            background: #f8f9fa;
        }

        .tournament-name,
        .player-name,
        .series-name {
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .tournament-meta,
        .player-stats,
        .series-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: #666;
        }

        .winner-avatar,
        .player-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0073aa;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        .player-rank {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #f0f0f0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        .badge.gold { color: #ffb900; }
        .badge.bronze { color: #cd7f32; }

        .series-progress {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-bar {
            width: 60px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #46b450;
            transition: width 0.3s;
        }

        .progress-text {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quick-action-btn:hover {
            border-color: #0073aa;
            background: #f0f8ff;
        }

        .quick-action-btn i {
            font-size: 24px;
            color: #0073aa;
        }

        .quick-action-btn span {
            font-size: 12px;
            font-weight: 500;
            color: #1a1a1a;
            text-align: center;
        }

        .loading-spinner {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .dashboard-actions {
                flex-direction: column;
                width: 100%;
            }

            .season-selector-wrapper {
                width: 100%;
                justify-content: space-between;
            }

            .season-filter {
                flex: 1;
                text-align: right;
            }

            .nav-tabs {
                flex-direction: column;
            }

            .dashboard-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }

        /* **PHASE 1: Player Leaderboard Table Styles** */
        .player-leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table-header {
            display: grid;
            grid-template-columns: 60px 1fr 120px 120px 100px 100px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 12px 8px;
            font-size: 14px;
        }

        .table-row {
            display: grid;
            grid-template-columns: 60px 1fr 120px 120px 100px 100px;
            border-bottom: 1px solid #e9ecef;
            padding: 12px 8px;
            align-items: center;
            transition: background-color 0.2s ease;
        }

        .table-row:hover {
            background-color: #f8f9fa;
        }

        .table-row:nth-child(even) {
            background-color: #fdfdfd;
        }

        .rank-cell {
            font-weight: 600;
            color: #0073aa;
            text-align: center;
        }

        .player-cell {
            font-weight: 500;
        }

        .player-link {
            color: #0073aa;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .player-link:hover {
            color: #005a87;
            text-decoration: underline;
        }

        .tournaments-cell {
            text-align: center;
            color: #6c757d;
        }

        .winnings-cell {
            font-weight: 600;
            color: #28a745;
            text-align: right;
        }

        .best-finish-cell {
            text-align: center;
            color: #17a2b8;
        }

        .avg-finish-cell {
            text-align: center;
            color: #6c757d;
        }

        /* Series and Seasons Leaderboard Styles */
        .series-leaderboard-scroll-wrapper,
        .seasons-leaderboard-scroll-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        .series-leaderboard-table,
        .seasons-leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        /* Series Table - Green Gradient Header */
        .series-header {
            display: grid;
            grid-template-columns: 60px 1fr 120px 140px 140px 120px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-weight: 600;
            padding: 16px 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 8px 8px 0 0;
        }

        /* Seasons Table - Purple Gradient Header */
        .seasons-header {
            display: grid;
            grid-template-columns: 60px 1fr 120px 140px 140px 120px;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            font-weight: 600;
            padding: 16px 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 8px 8px 0 0;
        }

        .series-leaderboard-table .table-row,
        .seasons-leaderboard-table .table-row {
            display: grid;
            grid-template-columns: 60px 1fr 120px 140px 140px 120px;
            border-bottom: 1px solid #e9ecef;
            padding: 14px 8px;
            align-items: center;
            transition: all 0.2s ease;
        }

        .series-drillthrough:hover,
        .season-drillthrough:hover {
            background-color: #f8f9fa;
            transform: translateX(4px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .series-leaderboard-table .table-row:nth-child(even),
        .seasons-leaderboard-table .table-row:nth-child(even) {
            background-color: #fdfdfd;
        }

        .series-cell,
        .season-cell {
            font-weight: 500;
            color: #333;
        }

        .header-series,
        .header-season {
            font-weight: 700;
        }

        .players-cell {
            text-align: center;
            color: #6c757d;
        }

        .prize-cell {
            font-weight: 600;
            color: #28a745;
            text-align: right;
        }

        .avg-cell {
            text-align: center;
            color: #6c757d;
        }

        /* Responsive adjustments for series/seasons tables */
        @media (max-width: 768px) {
            .series-header,
            .seasons-header,
            .series-leaderboard-table .table-row,
            .seasons-leaderboard-table .table-row {
                grid-template-columns: 50px 1fr 80px 100px 100px 80px;
                font-size: 12px;
                padding: 10px 4px;
            }
        }

        /* Participation Trends Styles */
        .trends-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .trend-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0073aa;
        }

        .trend-date {
            font-weight: 600;
            color: #495057;
            min-width: 60px;
        }

        .trend-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .trend-players,
        .trend-entries {
            font-size: 14px;
            color: #6c757d;
        }

        .trend-players {
            color: #0073aa;
            font-weight: 500;
        }

        /* Mobile responsive for player table */
        @media (max-width: 768px) {
            .table-header,
            .table-row {
                grid-template-columns: 40px 1fr 80px 80px;
            }

            .header-rank,
            .rank-cell {
                grid-column: 1;
            }

            .header-player,
            .player-cell {
                grid-column: 2;
            }

            .header-winnings,
            .winnings-cell {
                grid-column: 3;
            }

            .header-best,
            .best-finish-cell {
                grid-column: 4;
            }

            .header-tournaments,
            .tournaments-cell,
            .header-avg,
            .avg-finish-cell {
                display: none;
            }

            .trend-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .trend-stats {
                align-self: stretch;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .table-header,
            .table-row {
                padding: 8px 4px;
                font-size: 12px;
            }

            .trend-item {
                padding: 8px 12px;
            }
        }

        /* **PHASE 1: Player Details Modal Styles** */
        .player-details-modal,
        .loading-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: none;
        }

        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .player-details-modal .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .loading-modal .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-header h2 {
            margin: 0;
            color: #1a1a1a;
            font-size: 24px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .modal-body {
            padding: 24px;
        }

        .player-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .player-stat {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .player-stat label {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-weight: 500;
        }

        .player-stat span {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .recent-tournaments h3 {
            margin: 0 0 16px 0;
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 600;
        }

        .tournament-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .tournament-result {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #0073aa;
        }

        .tournament-name {
            font-weight: 500;
            color: #1a1a1a;
            flex: 1;
        }

        .finish-position {
            color: #0073aa;
            font-weight: 600;
            margin: 0 16px;
        }

        .winnings {
            color: #28a745;
            font-weight: 600;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0073aa;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10001;
            display: none;
            font-weight: 500;
        }

        .notification.error {
            background: #dc3545;
        }

        .notification.success {
            background: #28a745;
        }

        /* Mobile responsive for modal */
        @media (max-width: 768px) {
            .player-details-modal .modal-content {
                width: 95%;
                max-height: 90vh;
            }

            .modal-header {
                padding: 16px 20px 12px;
            }

            .modal-header h2 {
                font-size: 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .player-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .player-stat {
                padding: 12px;
            }

            .player-stat span {
                font-size: 16px;
            }

            .tournament-result {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .finish-position,
            .winnings {
                margin: 0;
            }

            .notification {
                left: 20px;
                right: 20px;
                top: 10px;
            }
        }
        </style>

        <!-- Import Tournament Modal -->
        <div id="import-tournament-modal" class="poker-modal" style="display:none;">
            <div class="poker-modal-content">
                <div class="poker-modal-header">
                    <h2><?php _e('Import Tournament', 'poker-tournament-import'); ?></h2>
                    <span class="poker-modal-close">&times;</span>
                </div>
                <div class="poker-modal-body">
                    <form id="frontend-import-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('poker_frontend_import', 'import_nonce'); ?>

                        <div class="form-group">
                            <label for="tdt-file-input"><?php _e('Select .tdt File', 'poker-tournament-import'); ?></label>
                            <input type="file" id="tdt-file-input" name="tdt_file" accept=".tdt" required>
                            <small><?php _e('Upload a Tournament Director (.tdt) file', 'poker-tournament-import'); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="import-series-select"><?php _e('Tournament Series', 'poker-tournament-import'); ?></label>
                            <select id="import-series-select" name="series_id">
                                <option value=""><?php _e('Select Series...', 'poker-tournament-import'); ?></option>
                                <?php
                                $series = get_terms(array(
                                    'taxonomy' => 'tournament_series',
                                    'hide_empty' => false
                                ));
                                foreach ($series as $s) {
                                    echo '<option value="' . esc_attr($s->term_id) . '">' . esc_html($s->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="import-season-select"><?php _e('Season', 'poker-tournament-import'); ?></label>
                            <select id="import-season-select" name="season_id">
                                <option value=""><?php _e('Select Season...', 'poker-tournament-import'); ?></option>
                                <?php
                                $seasons = get_terms(array(
                                    'taxonomy' => 'season',
                                    'hide_empty' => false,
                                    'orderby' => 'name',
                                    'order' => 'DESC'
                                ));
                                foreach ($seasons as $season) {
                                    echo '<option value="' . esc_attr($season->term_id) . '">' . esc_html($season->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="import-progress" style="display:none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p class="progress-message"></p>
                        </div>

                        <div class="import-result" style="display:none;"></div>

                        <div class="form-actions">
                            <button type="submit" class="button button-primary" id="import-submit-btn">
                                <?php _e('Import Tournament', 'poker-tournament-import'); ?>
                            </button>
                            <button type="button" class="button poker-modal-close">
                                <?php _e('Cancel', 'poker-tournament-import'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Dashboard JavaScript -->
        <script>
        // Currency symbol for JavaScript usage
        const pokerCurrencySymbol = '<?php echo esc_js(get_option('poker_currency_symbol', '$')); ?>';

        jQuery(document).ready(function($) {
            // Season selector handler
            $('#season-selector').on('change', function() {
                const seasonId = $(this).val();
                const currentUrl = new URL(window.location.href);

                if (seasonId === 'all') {
                    currentUrl.searchParams.delete('season_id');
                } else {
                    currentUrl.searchParams.set('season_id', seasonId);
                }

                // Reload page with new season filter
                window.location.href = currentUrl.toString();
            });

            // Tab navigation
            $('.nav-tab').click(function() {
                const view = $(this).data('view');

                // Update active tab
                $('.nav-tab').removeClass('active');
                $(this).addClass('active');

                // Update content
                $('.dashboard-tab').removeClass('active');
                $(`#${view}-tab`).addClass('active');

                // Load content via AJAX if not overview or players (players tab has static content)
                if (view !== 'overview' && view !== 'players') {
                    loadDashboardContent(view);
                }
            });

            // Drill-through functionality
            $('.clickable, .tournament-item, .player-item, .series-item').click(function() {
                const drillType = $(this).data('drill') || $(this).data('tournament-id') || $(this).data('player-id') || $(this).data('series-id');
                handleDrillThrough($(this));
            });

            // **PHASE 1: Player Drill-through Handler**
            $('.drill-through').click(function(e) {
                e.preventDefault();
                const drillType = $(this).data('drill-type');
                const playerId = $(this).data('player-id');
                const playerPostId = $(this).data('player-post-id');

                if (drillType === 'player') {
                    handlePlayerDrillThrough(playerId, playerPostId, $(this));
                }
            });

            // Quick actions
            $('.quick-action-btn').click(function() {
                const action = $(this).data('action');
                handleQuickAction(action);
            });

            // Load dashboard content
            function loadDashboardContent(view) {
                const $tab = $(`#${view}-tab`);
                $tab.html('<div class="loading-spinner"><i class="icon-spinner"></i> Loading...</div>');

                $.post(ajaxurl, {
                    action: 'poker_dashboard_load_content',
                    view: view,
                    nonce: '<?php echo wp_create_nonce('poker_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $tab.html(response.data.content);
                    } else {
                        $tab.html('<div class="error">Failed to load content</div>');
                    }
                }).fail(function() {
                    $tab.html('<div class="error">Connection error</div>');
                });
            }

            // Handle drill-through navigation
            function handleDrillThrough($element) {
                const tournamentId = $element.data('tournament-id');
                const playerId = $element.data('player-id');
                const seriesId = $element.data('series-id');
                const drillType = $element.data('drill');

                if (tournamentId) {
                    // Navigate to tournament page
                    window.location.href = $element.data('tournament-url') || `?p=${tournamentId}`;
                } else if (playerId) {
                    // Navigate to player profile
                    window.location.href = $element.data('player-url') || `?p=${playerId}`;
                } else if (seriesId) {
                    // Navigate to series page
                    window.location.href = $element.data('series-url') || `?p=${seriesId}`;
                } else if (drillType) {
                    // Load detailed view
                    loadDetailedView(drillType);
                }
            }

            // **PHASE 1: Handle Player Drill-through**
            function handlePlayerDrillThrough(playerId, playerPostId, $element) {
                // If player has a WordPress post, navigate to it directly
                // (Don't show loading modal - browser will show its own loading indicator)
                if (playerPostId) {
                    window.location.href = $element.attr('href') || `?p=${playerPostId}`;
                    return;
                }

                // For AJAX requests, show loading modal
                const playerName = $element.text().trim();
                showLoadingModal(`Loading ${playerName}'s detailed profile...`);

                // Load player details via AJAX
                $.post(ajaxurl, {
                    action: 'poker_get_player_details',
                    player_id: playerId,
                    nonce: '<?php echo wp_create_nonce('poker_player_details'); ?>'
                }, function(response) {
                    hideLoadingModal();
                    if (response.success) {
                        showPlayerDetailsModal(response.data);
                    } else {
                        showNotification('Failed to load player details', 'error');
                    }
                }).fail(function() {
                    hideLoadingModal();
                    showNotification('Connection error while loading player details', 'error');
                });
            }

            // Show player details modal
            function showPlayerDetailsModal(playerData) {
                const modal = `
                    <div class="player-details-modal" id="player-details-modal">
                        <div class="modal-backdrop" onclick="closePlayerDetailsModal()"></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>${playerData.player_name}</h2>
                                <button class="modal-close" onclick="closePlayerDetailsModal()">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="player-stats-grid">
                                    <div class="player-stat">
                                        <label>Total Tournaments</label>
                                        <span>${playerData.tournaments_played || 0}</span>
                                    </div>
                                    <div class="player-stat">
                                        <label>Net Profit</label>
                                        <span>${pokerCurrencySymbol}${number_format(playerData.total_winnings || 0, 0)}</span>
                                    </div>
                                    <div class="player-stat">
                                        <label>Best Finish</label>
                                        <span>${playerData.best_finish || '-'}</span>
                                    </div>
                                    <div class="player-stat">
                                        <label>Average Finish</label>
                                        <span>${number_format(playerData.avg_finish || 0, 1)}</span>
                                    </div>
                                    <div class="player-stat">
                                        <label>Total Points</label>
                                        <span>${number_format(playerData.total_points || 0, 0)}</span>
                                    </div>
                                    <div class="player-stat">
                                        <label>Highest Payout</label>
                                        <span>${pokerCurrencySymbol}${number_format(playerData.highest_payout || 0, 0)}</span>
                                    </div>
                                </div>
                                ${playerData.recent_tournaments ? `
                                    <div class="recent-tournaments">
                                        <h3>Recent Tournament Results</h3>
                                        <div class="tournament-list">
                                            ${playerData.recent_tournaments.map(tournament => `
                                                <div class="tournament-result">
                                                    <span class="tournament-name">${tournament.tournament_name}</span>
                                                    <span class="finish-position">Position: ${tournament.finish_position}</span>
                                                    <span class="winnings">${pokerCurrencySymbol}${number_format(tournament.winnings || 0, 0)}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modal);
                $('#player-details-modal').fadeIn(300);
            }

            // Close player details modal
            function closePlayerDetailsModal() {
                $('#player-details-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            }

            // Show loading modal
            function showLoadingModal(message) {
                const modal = `
                    <div class="loading-modal" id="loading-modal">
                        <div class="modal-backdrop"></div>
                        <div class="modal-content">
                            <div class="loading-spinner"></div>
                            <p>${message}</p>
                        </div>
                    </div>
                `;
                $('body').append(modal);
                $('#loading-modal').fadeIn(200);
            }

            // Hide loading modal
            function hideLoadingModal() {
                $('#loading-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            }

            // Show notification
            function showNotification(message, type = 'info') {
                const notification = `
                    <div class="notification ${type}" id="notification">
                        ${message}
                    </div>
                `;
                $('body').append(notification);
                $('#notification').fadeIn(300).delay(3000).fadeOut(300, function() {
                    $(this).remove();
                });
            }

            // Handle quick actions
            function handleQuickAction(action) {
                switch(action) {
                    case 'import':
                        <?php if (is_user_logged_in()): ?>
                        $('#import-tournament-modal').fadeIn(300);
                        <?php else: ?>
                        showNotification('<?php _e('Please log in to import tournaments.', 'poker-tournament-import'); ?>', 'error');
                        <?php endif; ?>
                        break;
                    case 'create-series':
                        // Open create series modal
                        break;
                    case 'view-calendar':
                        loadDetailedView('calendar');
                        break;
                    case 'generate-report':
                        generateReport();
                        break;
                }
            }

            // Modal close handlers
            $('.poker-modal-close').on('click', function() {
                $(this).closest('.poker-modal').fadeOut(300);
            });

            $(window).on('click', function(e) {
                if ($(e.target).hasClass('poker-modal')) {
                    $(e.target).fadeOut(300);
                }
            });

            // Frontend import form handler
            $('#frontend-import-form').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $submitBtn = $('#import-submit-btn');
                const $progress = $('.import-progress');
                const $result = $('.import-result');
                const formData = new FormData(this);

                formData.append('action', 'poker_frontend_import_tournament');
                formData.append('nonce', $('#import_nonce').val());

                // Disable submit button
                $submitBtn.prop('disabled', true).text('<?php _e('Importing...', 'poker-tournament-import'); ?>');
                $progress.show().find('.progress-message').text('<?php _e('Uploading and parsing tournament data...', 'poker-tournament-import'); ?>');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $result.html(
                                '<div class="success-message">' +
                                '<strong><?php _e('Success!', 'poker-tournament-import'); ?></strong> ' +
                                response.data.message +
                                '<br><a href="' + response.data.tournament_url + '"><?php _e('View Tournament', 'poker-tournament-import'); ?></a>' +
                                '</div>'
                            ).show();
                            $form[0].reset();

                            // Refresh tournaments tab if active
                            if ($('.nav-tab[data-view="tournaments"]').hasClass('active')) {
                                loadTab('tournaments');
                            }

                            // Close modal after 3 seconds
                            setTimeout(function() {
                                $('#import-tournament-modal').fadeOut(300);
                            }, 3000);
                        } else {
                            $result.html(
                                '<div class="error-message">' +
                                '<strong><?php _e('Error:', 'poker-tournament-import'); ?></strong> ' +
                                response.data.message +
                                '</div>'
                            ).show();
                        }
                    },
                    error: function() {
                        $result.html(
                            '<div class="error-message">' +
                            '<?php _e('An unexpected error occurred. Please try again.', 'poker-tournament-import'); ?>' +
                            '</div>'
                        ).show();
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('<?php _e('Import Tournament', 'poker-tournament-import'); ?>');
                        $progress.hide();
                    }
                });
            });

            // Load detailed views
            function loadDetailedView(type) {
                const $tab = $('#tournaments-tab'); // Reuse tournaments tab for detailed views
                $('.nav-tab').removeClass('active');
                $('.nav-tab[data-view="tournaments"]').addClass('active');
                $('.dashboard-tab').removeClass('active');
                $tab.addClass('active');

                $tab.html('<div class="loading-spinner"><i class="icon-spinner"></i> Loading...</div>');

                $.post(ajaxurl, {
                    action: 'poker_dashboard_detailed_view',
                    type: type,
                    nonce: '<?php echo wp_create_nonce('poker_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $tab.html(response.data.content);
                    } else {
                        $tab.html('<div class="error">Failed to load content</div>');
                    }
                });
            }

            // Generate report
            function generateReport() {
                $.post(ajaxurl, {
                    action: 'poker_dashboard_generate_report',
                    nonce: '<?php echo wp_create_nonce('poker_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Download report
                        const link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert('Failed to generate report: ' + response.data.message);
                    }
                });
            }

            // Auto-refresh every 5 minutes
            setInterval(function() {
                if ($('.nav-tab.active').data('view') === 'overview') {
                    location.reload();
                }
            }, 300000);
        });

        // Statistics refresh function
        function refreshStatistics() {
            const $button = $('#refresh-statistics');
            const $icon = $button.find('.icon-refresh');

            // Show loading state
            $button.prop('disabled', true);
            $icon.addClass('spinning');
            $button.find('.button-text').text('<?php _e('Refreshing...', 'poker-tournament-import'); ?>');

            $.ajax({
                url: pokerImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'poker_refresh_statistics',
                    nonce: '<?php echo wp_create_nonce('poker_refresh_statistics'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        const $alert = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.poker-dashboard-container').prepend($alert);

                        // Remove alert after 3 seconds
                        setTimeout(function() {
                            $alert.fadeOut();
                        }, 3000);

                        // Reload the page to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        // Show error message
                        const $alert = $('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.poker-dashboard-container').prepend($alert);
                    }
                },
                error: function() {
                    // Show generic error message
                    const $alert = $('<div class="notice notice-error is-dismissible"><p><?php _e('Failed to refresh statistics. Please try again.', 'poker-tournament-import'); ?></p></div>');
                    $('.poker-dashboard-container').prepend($alert);
                },
                complete: function() {
                    // Restore button state
                    $button.prop('disabled', false);
                    $icon.removeClass('spinning');
                    $button.find('.button-text').text('<?php _e('Refresh Statistics', 'poker-tournament-import'); ?>');
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get dashboard statistics
     */
    /**
     * Get dashboard statistics with optional season filtering
     *
     * @param int|string|null $season_id Season ID to filter by, or 'all' for all seasons
     */
    private function get_dashboard_statistics($season_id = null) {
        // Use the statistics engine for fast data retrieval
        $stats_engine = Poker_Statistics_Engine::get_instance();

        // Get the core statistics from data mart (season-aware)
        $stats = $stats_engine->get_dashboard_statistics($season_id);

        // Ensure all expected keys exist with fallbacks
        return array(
            'total_tournaments' => intval($stats['total_tournaments']),
            'total_players' => intval($stats['total_players']),
            'total_prize_pool' => floatval($stats['total_prize_pool']),
            'avg_prize_pool' => floatval($stats['avg_prize_pool']),
            'recent_tournaments' => intval($stats['recent_tournaments_30d']),
            'new_players' => intval($stats['new_players_30d']),
            'active_series' => intval($stats['active_series']),
            'upcoming_events' => 0, // TODO: Calculate upcoming events
            'last_updated' => $stats['last_updated'],
            // Player-specific stats for Players tab
            'total_unique_players' => intval($stats['total_unique_players'] ?? 0),
            'average_finish_position' => floatval($stats['average_finish_position'] ?? 0),
            'total_payouts' => floatval($stats['total_payouts'] ?? 0),
            'highest_single_payout' => floatval($stats['highest_single_payout'] ?? 0)
        );
    }

    /**
     * Get recent tournaments for dashboard
     */
    private function get_recent_tournaments($limit = 10) {
        $recent_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($recent_tournaments as $tournament) {
            $tournament->tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
            $tournament->players_count = get_post_meta($tournament->ID, '_players_count', true);
            $tournament->prize_pool = get_post_meta($tournament->ID, '_prize_pool', true); // Fixed: added underscore

            // Get winner
            $tournament_uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
            if ($tournament_uuid) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'poker_tournament_players';
                $winner = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.post_title as winner_name
                     FROM $table_name tp
                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE tp.tournament_id = %s AND tp.finish_position = 1
                     LIMIT 1",
                    $tournament_uuid
                ));
                $tournament->winner_name = $winner ? $winner->winner_name : '';
            } else {
                $tournament->winner_name = '';
            }
        }

        return $recent_tournaments;
    }

    /**
     * Get top players for dashboard
     */
    private function get_top_players($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $roi_table = $wpdb->prefix . 'poker_player_roi';

        // DEBUG: Check if tables exist and have data
        if (current_user_can('manage_options')) {
            $roi_exists = $wpdb->get_var("SHOW TABLES LIKE '$roi_table'") === $roi_table;
            $roi_count = $roi_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $roi_table") : 0;
            error_log("Top Players Debug - ROI table exists: " . ($roi_exists ? 'YES' : 'NO'));
            error_log("Top Players Debug - ROI table rows: " . $roi_count);

            // Check if ROI table has any non-null net_profit values
            if ($roi_exists && $roi_count > 0) {
                $non_null_count = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit IS NOT NULL");
                $positive_profit = $wpdb->get_var("SELECT COUNT(*) FROM $roi_table WHERE net_profit > 0");
                $sample_data = $wpdb->get_results("SELECT player_id, net_profit, total_invested, total_winnings FROM $roi_table LIMIT 5", ARRAY_A);
                error_log("Top Players Debug - Non-null net_profit rows: " . $non_null_count);
                error_log("Top Players Debug - Positive net_profit rows: " . $positive_profit);
                error_log("Top Players Debug - Sample data: " . print_r($sample_data, true));
            }
        }

        // Query poker_player_roi table for NET PROFIT (winnings - total_invested)
        // with finish position counts, bubble, last place, and hits
        $top_players = $wpdb->get_results($wpdb->prepare(
            "SELECT roi.player_id,
                    COUNT(*) as tournaments_played,
                    SUM(roi.net_profit) as total_winnings,
                    SUM(tp.points) as total_points,
                    MIN(tp.finish_position) as best_finish,
                    AVG(tp.finish_position) as avg_finish,
                    SUM(roi.total_invested) as total_invested,
                    SUM(roi.total_winnings) as gross_winnings,
                    SUM(CASE WHEN tp.finish_position = 1 THEN 1 ELSE 0 END) as first_place_count,
                    SUM(CASE WHEN tp.finish_position = 2 THEN 1 ELSE 0 END) as second_place_count,
                    SUM(CASE WHEN tp.finish_position = 3 THEN 1 ELSE 0 END) as third_place_count,
                    SUM(tp.hits) as total_hits,
                    SUM(CASE
                        WHEN tp.finish_position = (
                            SELECT COUNT(*) + 1
                            FROM $table_name tp2
                            WHERE tp2.tournament_id = tp.tournament_id AND tp2.winnings > 0
                        )
                        THEN 1 ELSE 0
                    END) as bubble_count,
                    SUM(CASE
                        WHEN tp.finish_position = (
                            SELECT MAX(finish_position)
                            FROM $table_name tp3
                            WHERE tp3.tournament_id = tp.tournament_id
                        )
                        THEN 1 ELSE 0
                    END) as last_place_count
             FROM $roi_table roi
             LEFT JOIN $table_name tp ON roi.player_id = tp.player_id AND roi.tournament_id = tp.tournament_id
             GROUP BY roi.player_id
             HAVING total_winnings IS NOT NULL
             ORDER BY total_winnings DESC, total_points DESC
             LIMIT %d",
            $limit
        ));

        // DEBUG: Log query results
        if (current_user_can('manage_options')) {
            error_log("Top Players Debug - Query returned " . count($top_players) . " results");
            if (empty($top_players)) {
                error_log("Top Players Debug - Query returned empty result set");
                // Try simpler query without HAVING clause
                $simple_query = $wpdb->get_results("SELECT player_id, net_profit FROM $roi_table LIMIT 5", ARRAY_A);
                error_log("Top Players Debug - Simple query results: " . print_r($simple_query, true));
            } else {
                error_log("Top Players Debug - First result: " . print_r($top_players[0], true));
            }
        }

        // Get player post information and calculate season points
        foreach ($top_players as $player) {
            $player_post = $wpdb->get_row($wpdb->prepare(
                "SELECT p.ID as player_post_id, p.post_title as player_name
                 FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'player_uuid' AND pm.meta_value = %s
                 LIMIT 1",
                $player->player_id
            ));

            if ($player_post) {
                $player->player_post_id = $player_post->player_post_id;
                $player->player_name = $player_post->player_name;
            } else {
                $player->player_post_id = null;
                $player->player_name = $player->player_id;
            }

            // Calculate season points using configured formula
            $player->season_points = $this->calculate_player_season_points(
                $player->player_id,
                floatval($player->total_points),
                intval($player->tournaments_played),
                floatval($player->total_winnings),
                intval($player->total_hits),
                intval($player->best_finish),
                floatval($player->avg_finish)
            );
        }

        // Sort by season points (primary) then total points (secondary)
        usort($top_players, function($a, $b) {
            if ($a->season_points != $b->season_points) {
                return $b->season_points <=> $a->season_points; // Descending
            }
            return $b->total_points <=> $a->total_points; // Descending
        });

        return $top_players;
    }

    /**
     * Calculate season points for a player using configured formula
     */
    private function calculate_player_season_points($player_id, $total_points, $tournaments_played, $total_winnings, $total_hits, $best_finish, $avg_finish) {
        global $wpdb;

        // Get configured season formula
        $formula_key = get_option('poker_active_season_formula', 'season_total');

        // Get individual tournament points for formula calculation
        $tournament_points = array();
        $players_table = $wpdb->prefix . 'poker_tournament_players';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT points FROM {$players_table} WHERE player_id = %s ORDER BY points DESC",
            $player_id
        ), ARRAY_A);

        foreach ($results as $result) {
            $tournament_points[] = floatval($result['points']);
        }

        // Apply formula if configured and validator class exists
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
                    'best_finish' => $best_finish,
                    'avg_finish' => $avg_finish,
                    'player_id' => $player_id
                );

                $result = $formula_validator->calculate_formula($formula_data['formula'], $formula_input, 'season');

                if (isset($result['success']) && $result['success']) {
                    return floatval($result['result']);
                }
            }
        }

        // Fallback: return total points
        return $total_points;
    }

    /**
     * Get active series for dashboard
     */
    private function get_active_series($limit = 5) {
        $active_series = get_posts(array(
            'post_type' => 'tournament_series',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));

        foreach ($active_series as $series) {
            // Get tournament count with correct field name
            $tournament_posts = get_posts(array(
                'post_type' => 'tournament',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'series_id',
                        'value' => $series->ID,
                        'compare' => '='
                    ),
                    array(
                        'key' => '_series_id',
                        'value' => $series->ID,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));
            $series->tournament_count = count($tournament_posts);

            // Calculate unique players in series
            $player_ids = array();
            foreach ($tournament_posts as $tournament_id) {
                $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
                if ($tournament_uuid) {
                    global $wpdb;
                    $players_in_tournament = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT player_id FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id = %s",
                        $tournament_uuid
                    ));
                    $player_ids = array_merge($player_ids, $players_in_tournament);
                }
            }
            $series->player_count = count(array_unique($player_ids));

            // Calculate progress (based on tournaments vs expected or recent activity)
            $recent_tournaments = 0;
            foreach ($tournament_posts as $tournament_id) {
                $tournament_date = get_post_meta($tournament_id, '_tournament_date', true);
                if ($tournament_date && strtotime($tournament_date) > strtotime('-30 days')) {
                    $recent_tournaments++;
                }
            }
            $series->progress_percent = $series->tournament_count > 0 ?
                min(100, round(($recent_tournaments / max(1, $series->tournament_count)) * 100)) : 0;
        }

        return $active_series;
    }

    /**
     * Get active seasons with tournament and player counts
     */
    private function get_active_seasons($limit = 5) {
        $active_seasons = get_posts(array(
            'post_type' => 'tournament_season',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));

        foreach ($active_seasons as $season) {
            // Get tournament count
            $tournament_posts = get_posts(array(
                'post_type' => 'tournament',
                'meta_key' => '_season_id',
                'meta_value' => $season->ID,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));
            $season->tournament_count = count($tournament_posts);

            // Calculate unique players in season
            $player_ids = array();
            foreach ($tournament_posts as $tournament_id) {
                $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true);
                if ($tournament_uuid) {
                    global $wpdb;
                    $players_in_tournament = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT player_id FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id = %s",
                        $tournament_uuid
                    ));
                    $player_ids = array_merge($player_ids, $players_in_tournament);
                }
            }
            $season->player_count = count(array_unique($player_ids));

            // Calculate progress (based on tournaments vs expected or recent activity)
            $recent_tournaments = 0;
            foreach ($tournament_posts as $tournament_id) {
                $tournament_date = get_post_meta($tournament_id, '_tournament_date', true);
                if ($tournament_date && strtotime($tournament_date) > strtotime('-30 days')) {
                    $recent_tournaments++;
                }
            }

            // Progress calculation
            $expected_tournaments = get_post_meta($season->ID, '_expected_tournaments', true);
            if ($expected_tournaments && $expected_tournaments > 0) {
                $season->progress_percent = min(100, round(($season->tournament_count / $expected_tournaments) * 100));
            } else {
                // Fallback: base progress on recent activity
                $season->progress_percent = $season->tournament_count > 0 ?
                    min(100, round(($recent_tournaments / max(1, $season->tournament_count)) * 100)) : 0;
            }
        }

        return $active_seasons;
    }

    /**
     * CRITICAL FIX: Get real-time tournament results using chronological GameHistory processing
     * This replaces the old stored data that was calculated using incorrect bust-out timestamps
     */
    private function get_realtime_tournament_results($tournament_id) {
        // CRITICAL FIX: Get raw TDT content for real-time chronological processing
        $raw_content = get_post_meta($tournament_id, '_tournament_raw_content', true);

        if (!$raw_content) {
            Poker_Tournament_Import_Debug::log_warning("No raw TDT content found for tournament ID: {$tournament_id}");
            return null;
        }

        Poker_Tournament_Import_Debug::log("Processing real-time tournament results from raw TDT content for tournament ID: {$tournament_id}");

        try {
            // Use modern AST-based parsing (same fix as v2.4.26 for single-tournament.php)
            $parser = new Poker_Tournament_Parser();
            $parsed_data = $parser->parse_content($raw_content);

            if ($parsed_data && !empty($parsed_data['players'])) {
                Poker_Tournament_Import_Debug::log_success("Real-time chronological processing completed for tournament ID: {$tournament_id}");

                return array(
                    'players' => $parsed_data['players'],
                    'game_history' => $parsed_data['game_history'] ?? null,
                    'metadata' => $parsed_data['metadata'] ?? $this->extract_basic_metadata($raw_content)
                );
            } else {
                Poker_Tournament_Import_Debug::log_error("Real-time processing failed: No player data found in parsed result");
            }
        } catch (Exception $e) {
            Poker_Tournament_Import_Debug::log_error("Real-time processing failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Extract basic metadata from tournament data
     */
    private function extract_basic_metadata($tournament_data) {
        $metadata = array();

        // Extract basic info using regex patterns
        if (preg_match('/UUID:\s*"([^"]+)"/', $tournament_data, $matches)) {
            $metadata['uuid'] = $matches[1];
        }

        if (preg_match('/Title:\s*"([^"]+)"/', $tournament_data, $matches)) {
            $metadata['title'] = $matches[1];
        }

        if (preg_match('/StartTime:\s*(\d+)/', $tournament_data, $matches)) {
            $metadata['start_time'] = date('Y-m-d H:i:s', intval($matches[1] / 1000));
        }

        return $metadata;
    }

    /**
     * Render real-time tournament players with correct chronological results
     */
    private function render_realtime_players($realtime_results, $tournament_id) {
        $players = $realtime_results['players'];
        $metadata = $realtime_results['metadata'];

        if (empty($players)) {
            echo '<p>' . __('No player data available.', 'poker-tournament-import') . '</p>';
            return;
        }

        // Sort players by finish position
        uasort($players, function($a, $b) {
            return $a['finish_position'] - $b['finish_position'];
        });

        $players_count = count($players);
        $paid_positions = 0;
        foreach ($players as $player) {
            if (isset($player['winnings']) && $player['winnings'] > 0) $paid_positions++;
        }

        $final_table_count = min(9, $players_count);
        $bubble_position = $paid_positions + 1;

        echo '<div class="tournament-players realtime-chronological">';
        echo '<h3>' . __('Tournament Results', 'poker-tournament-import') . '</h3>';

        // Add tournament summary
        echo '<div class="tournament-summary-grid">';
        echo '<div class="summary-card">';
        echo '<span class="summary-label">' . __('Entries:', 'poker-tournament-import') . '</span>';
        echo '<span class="summary-value">' . esc_html($players_count) . '</span>';
        echo '</div>';
        echo '<div class="summary-card">';
        echo '<span class="summary-label">' . __('Paid Positions:', 'poker-tournament-import') . '</span>';
        echo '<span class="summary-value">' . esc_html($paid_positions) . '</span>';
        echo '</div>';
        echo '<div class="summary-card">';
        echo '<span class="summary-label">' . __('Final Table:', 'poker-tournament-import') . '</span>';
        echo '<span class="summary-value">' . esc_html($final_table_count) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '<table class="tournament-results-table enhanced" id="tournament-results-' . esc_attr($tournament_id) . '">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="position-col">' . __('Pos', 'poker-tournament-import') . '</th>';
        echo '<th class="player-col">' . __('Player', 'poker-tournament-import') . '</th>';
        echo '<th class="winnings-col">' . __('Winnings', 'poker-tournament-import') . '</th>';
        echo '<th class="points-col">' . __('Points', 'poker-tournament-import') . '</th>';
        echo '<th class="achievements-col">' . __('Achievements', 'poker-tournament-import') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($players as $uuid => $player) {
            $player_name = $player['nickname'] ?? __('Unknown Player', 'poker-tournament-import');

            // Determine row class based on position
            $row_class = '';
            $position_badge = '';
            $achievements = array();

            if ($player['finish_position'] == 1) {
                $row_class = 'gold-medal';
                $position_badge = '🥇';
                $achievements[] = '<span class="achievement-badge winner" title="' . __('Champion!', 'poker-tournament-import') . '">🏆</span>';

                // Add winner source info
                if (isset($player['winner_source'])) {
                    $achievements[] = '<span class="achievement-badge chronological" title="Winner determined by: ' . esc_attr($player['winner_source']) . '">⚡</span>';
                }
            } elseif ($player['finish_position'] == 2) {
                $row_class = 'silver-medal';
                $position_badge = '🥈';
                $achievements[] = '<span class="achievement-badge runner-up" title="' . __('Runner-up', 'poker-tournament-import') . '">🥈</span>';
            } elseif ($player['finish_position'] == 3) {
                $row_class = 'bronze-medal';
                $position_badge = '🥉';
                $achievements[] = '<span class="achievement-badge third-place" title="' . __('Third Place', 'poker-tournament-import') . '">🥉</span>';
            } elseif ($player['finish_position'] <= $final_table_count) {
                $row_class = 'final-table';
                $achievements[] = '<span class="achievement-badge final-table" title="' . __('Final Table', 'poker-tournament-import') . '">🎯</span>';
            } elseif ($player['finish_position'] == $bubble_position && $paid_positions > 0) {
                $row_class = 'bubble';
                $achievements[] = '<span class="achievement-badge bubble" title="' . __('Bubble Finish', 'poker-tournament-import') . '">💭</span>';
            }

            // Add elimination achievement with chronological data
            if (isset($player['elimination_count']) && $player['elimination_count'] > 0) {
                /* translators: %d: number of eliminations */
                $achievements[] = '<span class="achievement-badge eliminations" title="' . sprintf(_n('%d Elimination', '%d Eliminations', $player['elimination_count'], 'poker-tournament-import'), $player['elimination_count']) . '">⚔️</span>';
            } elseif (isset($player['hits']) && $player['hits'] > 0) {
                /* translators: %d: number of eliminations */
                $achievements[] = '<span class="achievement-badge eliminations" title="' . sprintf(_n('%d Elimination', '%d Eliminations', $player['hits'], 'poker-tournament-import'), $player['hits']) . '">⚔️</span>';
            }

            echo '<tr class="' . esc_attr($row_class) . '">';

            // Position with badge
            echo '<td class="position-cell">';
            if ($position_badge) {
                echo '<span class="position-badge">' . esc_html($position_badge) . '</span>';
            }
            echo '<span class="position-number">' . esc_html($player['finish_position']) . get_ordinal_suffix($player['finish_position']) . '</span>';
            echo '</td>';

            // Player name
            echo '<td class="player-cell">';
            echo '<div class="player-name">' . esc_html($player_name) . '</div>';

            // Add elimination details if available from chronological processing
            if (isset($player['elimination_details'])) {
                $elim_details = $player['elimination_details'];
                echo '<div class="elimination-info">';
                echo '<span class="eliminated-by">by ' . esc_html($elim_details['eliminated_by_name']) . '</span>';
                echo '</div>';
            }
            echo '</td>';

            // Winnings
            echo '<td class="winnings-cell">';
            $winnings = $player['winnings'] ?? 0;
            $currency = get_post_meta($tournament_id, '_currency', true) ?: '$';
            if ($winnings > 0) {
                echo '<span class="winnings-amount">' . esc_html($currency . number_format($winnings, 0)) . '</span>';
            } else {
                echo '<span class="winnings-none">-</span>';
            }
            echo '</td>';

            // Points
            echo '<td class="points-cell">';
            $points = $player['points'] ?? 0;
            if ($points > 0) {
                echo '<span class="points-amount">' . esc_html(number_format($points, 1)) . '</span>';
            } else {
                echo '<span class="points-none">-</span>';
            }
            echo '</td>';

            // Achievements
            echo '<td class="achievements-cell">';
            if (!empty($achievements)) {
                echo '<div class="achievements-list">';
                echo implode('', $achievements);
                echo '</div>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

/**
 * Helper function to get ordinal suffix
 */
if (!function_exists('get_ordinal_suffix')) {
    function get_ordinal_suffix($number) {
        $number = intval($number);
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return 'th';
        } else {
            return $ends[$number % 10];
        }
    }
}