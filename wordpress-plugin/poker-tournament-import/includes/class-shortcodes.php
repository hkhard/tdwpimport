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
        echo '<div class="tournament-results">';
        echo '<h2>' . esc_html($tournament->post_title) . '</h2>';

        // Display tournament metadata
        $tournament_date = get_post_meta($tournament->ID, '_tournament_date', true);
        $buy_in = get_post_meta($tournament->ID, '_buy_in', true);
        $players_count = get_post_meta($tournament->ID, '_players_count', true);

        echo '<div class="tournament-meta">';
        if ($tournament_date) {
            echo '<p><strong>' . __('Date:', 'poker-tournament-import') . '</strong> ' . esc_html(date('F j, Y', strtotime($tournament_date))) . '</p>';
        }
        if ($buy_in) {
            echo '<p><strong>' . __('Buy-in:', 'poker-tournament-import') . '</strong> ' . esc_html($buy_in) . '</p>';
        }
        if ($players_count) {
            echo '<p><strong>' . __('Players:', 'poker-tournament-import') . '</strong> ' . esc_html($players_count) . '</p>';
        }
        echo '</div>';

        // Display content
        if ($tournament->post_content) {
            echo '<div class="tournament-description">';
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
                SUM(hits) as total_hits
            FROM $table_name
            WHERE player_id = %s",
            $player_uuid
        ));

        if ($stats) {
            echo '<div class="player-stats">';
            echo '<h3>' . __('Career Statistics', 'poker-tournament-import') . '</h3>';
            echo '<table class="player-stats-table">';
            echo '<tr><td>' . __('Tournaments Played:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->tournaments_played) . '</td></tr>';
            echo '<tr><td>' . __('Total Winnings:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->total_winnings, 2)) . '</td></tr>';
            echo '<tr><td>' . __('Average Finish:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->avg_finish, 1)) . '</td></tr>';
            echo '<tr><td>' . __('Total Points:', 'poker-tournament-import') . '</td><td>' . esc_html(number_format($stats->total_points, 2)) . '</td></tr>';
            echo '<tr><td>' . __('Total Eliminations:', 'poker-tournament-import') . '</td><td>' . esc_html($stats->total_hits) . '</td></tr>';
            echo '</table>';
            echo '</div>';
        }
    }
}