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

        // Tab navigation shortcode
        add_shortcode('series_tabs', array($this, 'series_tabs_shortcode'));

        // Season shortcodes
        add_shortcode('season_tabs', array($this, 'season_tabs_shortcode'));
        add_shortcode('season_overview', array($this, 'season_overview_shortcode'));
        add_shortcode('season_results', array($this, 'season_results_shortcode'));
        add_shortcode('season_statistics', array($this, 'season_statistics_shortcode'));
        add_shortcode('season_players', array($this, 'season_players_shortcode'));
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
        <div class="series-tabs-container" data-series-id="<?php echo esc_attr($series_id); ?>">
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

        foreach ($series_tournaments as $tournament) {
            $players_count = get_post_meta($tournament->ID, '_players_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
            $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);

            $total_players += intval($players_count);
            $total_prize_pool += floatval($prize_pool);

            if ($tournament_uuid) {
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
        if (!empty($unique_players) && $series_uuid) {
            $top_player_data = $wpdb->get_row($wpdb->prepare(
                "SELECT tp.player_id, SUM(tp.points) as total_points, SUM(tp.winnings) as total_winnings,
                        MIN(tp.finish_position) as best_finish
                 FROM $table_name tp
                 WHERE tp.player_id IN (SELECT DISTINCT player_id FROM $table_name)
                 GROUP BY tp.player_id
                 ORDER BY total_points DESC, total_winnings DESC
                 LIMIT 1",
                $series_uuid
            ));

            if ($top_player_data) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
        <div class="series-overview-content">
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
                    <div class="stat-number">$<?php echo esc_html(number_format($total_prize_pool, 0)); ?></div>
                    <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo esc_html(number_format($avg_prize_pool, 0)); ?></div>
                    <div class="stat-label"><?php _e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
            </div>

            <!-- Best Player -->
            <?php if ($top_player): ?>
            <div class="best-player-card">
                <h3><?php _e('Best Player', 'poker-tournament-import'); ?></h3>
                <div class="player-info">
                    <div class="player-avatar"><?php echo substr($top_player['name'], 0, 2); ?></div>
                    <div class="player-details">
                        <a href="<?php echo get_permalink($top_player['id']); ?>" class="player-name">
                            <?php echo esc_html($top_player['name']); ?>
                        </a>
                        <div class="player-stats">
                            <span class="player-points"><?php echo esc_html(number_format($top_player['points'], 0)); ?> <?php _e('points', 'poker-tournament-import'); ?></span>
                            <span class="player-winnings">$<?php echo esc_html(number_format($top_player['winnings'], 0)); ?></span>
                            <span class="player-best"><?php _e('Best:', 'poker-tournament-import'); ?> <?php echo esc_html($top_player['best_finish']); ?><?php echo get_ordinal_suffix($top_player['best_finish']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Tournaments -->
            <div class="recent-tournaments-section">
                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                <?php
                $recent_tournaments = array_slice($series_tournaments, 0, intval($atts['limit']));
                if (!empty($recent_tournaments)):
                ?>
                    <div class="recent-tournaments-list">
                        <?php foreach ($recent_tournaments as $tournament):
                            $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
                                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
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
                                        <span class="tournament-prize"><?php echo esc_html($currency . number_format($prize_pool, 0)); ?></span>
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
        <div class="series-results-content">
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
                                $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
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
                                    <td class="tournament-prize"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></td>
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
            $uuid = get_post_meta($tournament_id, '_tournament_uuid', true);
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
        <div class="series-statistics-content">
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
                                    <th><?php _e('Total Winnings', 'poker-tournament-import'); ?></th>
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
                                         WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
                                        <td class="winnings">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></td>
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
            $uuid = get_post_meta($tournament_id, '_tournament_uuid', true);
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
                             WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
                                                <span class="stat-value">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></span>
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
        <div class="series-tabs-container" data-series-id="<?php echo esc_attr($season_id); ?>">
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

        foreach ($season_tournaments as $tournament) {
            $players_count = get_post_meta($tournament->ID, '_players_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);
            $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);

            $total_players += intval($players_count);
            $total_prize_pool += floatval($prize_pool);

            if ($tournament_uuid) {
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
        if (!empty($unique_players)) {
            $top_player_data = $wpdb->get_row($wpdb->prepare(
                "SELECT tp.player_id, SUM(tp.points) as total_points, SUM(tp.winnings) as total_winnings,
                        MIN(tp.finish_position) as best_finish
                 FROM $table_name tp
                 WHERE tp.player_id IN (SELECT DISTINCT player_id FROM $table_name)
                 GROUP BY tp.player_id
                 ORDER BY total_points DESC, total_winnings DESC
                 LIMIT 1"
            ));

            if ($top_player_data) {
                $player_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT p.ID, p.post_title
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
        <div class="series-overview-content">
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
                    <div class="stat-number">$<?php echo esc_html(number_format($total_prize_pool, 0)); ?></div>
                    <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo esc_html(number_format($avg_prize_pool, 0)); ?></div>
                    <div class="stat-label"><?php _e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
            </div>

            <!-- Best Player -->
            <?php if ($top_player): ?>
            <div class="best-player-card">
                <h3><?php _e('Best Player', 'poker-tournament-import'); ?></h3>
                <div class="player-info">
                    <div class="player-avatar"><?php echo substr($top_player['name'], 0, 2); ?></div>
                    <div class="player-details">
                        <a href="<?php echo get_permalink($top_player['id']); ?>" class="player-name">
                            <?php echo esc_html($top_player['name']); ?>
                        </a>
                        <div class="player-stats">
                            <span class="player-points"><?php echo esc_html(number_format($top_player['points'], 0)); ?> <?php _e('points', 'poker-tournament-import'); ?></span>
                            <span class="player-winnings">$<?php echo esc_html(number_format($top_player['winnings'], 0)); ?></span>
                            <span class="player-best"><?php _e('Best:', 'poker-tournament-import'); ?> <?php echo esc_html($top_player['best_finish']); ?><?php echo get_ordinal_suffix($top_player['best_finish']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Tournaments -->
            <div class="recent-tournaments-section">
                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                <?php
                $recent_tournaments = array_slice($season_tournaments, 0, intval($atts['limit']));
                if (!empty($recent_tournaments)):
                ?>
                    <div class="recent-tournaments-list">
                        <?php foreach ($recent_tournaments as $tournament):
                            $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
                                     LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
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
                                        <span class="tournament-prize"><?php echo esc_html($currency . number_format($prize_pool, 0)); ?></span>
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
        <div class="series-results-content">
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
                                $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
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
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
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
                                    <td class="tournament-prize"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></td>
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
            $uuid = get_post_meta($tournament_id, '_tournament_uuid', true);
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
        <div class="series-statistics-content">
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
                                    <th><?php _e('Total Winnings', 'poker-tournament-import'); ?></th>
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
                                         WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
                                        <td class="winnings">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></td>
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
            $uuid = get_post_meta($tournament_id, '_tournament_uuid', true);
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
                             WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
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
                                                <span class="stat-value">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></span>
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