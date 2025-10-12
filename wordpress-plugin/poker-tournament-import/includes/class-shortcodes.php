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
        $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
        $average_winnings = $players_count > 0 ? $total_winnings / min($players_count, count($this->get_paid_positions($tournament_uuid))) : 0;

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
            echo '<span class="meta-value">' . esc_html($currency . number_format($prize_pool, 2)) . '</span>';
            echo '</div>';
        }

        if ($total_winnings > 0) {
            echo '<div class="meta-item">';
            echo '<span class="meta-label">' . __('Total Paid:', 'poker-tournament-import') . '</span>';
            echo '<span class="meta-value">' . esc_html($currency . number_format($total_winnings, 2)) . '</span>';
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

        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tournament_id = %s ORDER BY finish_position ASC",
            get_post_meta($tournament_id, '_tournament_uuid', true)
        ));

        if ($players) {
            echo '<div class="tournament-players">';
            echo '<h3>' . __('Results', 'poker-tournament-import') . '</h3>';
            echo '<table class="tournament-results-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Position', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Player', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Winnings', 'poker-tournament-import') . '</th>';
            echo '<th>' . __('Points', 'poker-tournament-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($players as $player) {
                $player_post = get_posts(array(
                    'post_type' => 'player',
                    'meta_key' => '_player_uuid',
                    'meta_value' => $player->player_id,
                    'numberposts' => 1
                ));

                $player_name = !empty($player_post) ? $player_post[0]->post_title : __('Unknown Player', 'poker-tournament-import');

                echo '<tr>';
                echo '<td>' . esc_html($player->finish_position) . '</td>';
                echo '<td>' . esc_html($player_name) . '</td>';
                echo '<td>' . esc_html(number_format($player->winnings, 2)) . '</td>';
                echo '<td>' . esc_html(number_format($player->points, 2)) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
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
        $player_uuid = get_post_meta($player_id, '_player_uuid', true);

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
            echo '<tr><td>' . __('Total Winnings:', 'poker-tournament-import') . '</td><td>$' . esc_html(number_format($stats->total_winnings, 2)) . '</td></tr>';
            echo '<tr><td>' . __('Average Finish:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->avg_finish, 1)) . '</td></tr>';
            echo '<tr><td>' . __('Total Points:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->total_points, 2)) . '</td></tr>';
            echo '<tr><td>' . __('Total Eliminations:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->total_hits) . '</td></tr>';

            if ($stats->best_cash > 0) {
                echo '<tr><td>' . __('Best Cash:', 'poker-tournament-import') . '</td><td>$' . esc_html(number_format($stats->best_cash, 2)) . '</td></tr>';
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
            LEFT JOIN {$wpdb->posts} p ON tp.tournament_id = (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_tournament_uuid')
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
                echo '<td>$' . esc_html(number_format($tournament->winnings, 2)) . '</td>';
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
            echo '<div class="stat-number">$' . esc_html(number_format($stats->avg_winnings, 0)) . '</div>';
            echo '<div class="stat-label">' . __('Average Cash', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '<div class="stat-card">';
            echo '<div class="stat-number">$' . esc_html(number_format($stats->first_place, 0)) . '</div>';
            echo '<div class="stat-label">' . __('First Prize', 'poker-tournament-import') . '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
        }
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
            return $number . 'th';
        } else {
            return $number . $ends[$number % 10];
        }
    }
}