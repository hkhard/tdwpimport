<?php
/**
 * Poker Dashboard Shortcode
 *
 * Displays poker tournament statistics on public pages
 * Shortcode: [poker_dashboard]
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Require filter system class
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-dashboard-filters.php';

class Poker_Dashboard_Shortcode
{

    public function __construct()
    {
        add_shortcode('poker_dashboard', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend CSS assets
     */
    public function enqueue_assets()
    {
        // Enqueue dashicons for frontend icons
        wp_enqueue_style('dashicons');

        // Enqueue dashboard CSS - load on all frontend pages
        wp_enqueue_style(
            'poker-dashboard-frontend',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/poker-dashboard-frontend.css',
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        // Enqueue filters CSS for dashboard filter controls
        wp_enqueue_style(
            'poker-dashboard-filters',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css-dashboard/filters.css',
            array('poker-dashboard-frontend'),
            POKER_TOURNAMENT_IMPORT_VERSION
        );
    }

    /**
     * Render shortcode
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'show_stats' => 'true',
            'show_recent' => 'true',
            'show_health' => 'false',
            'recent_count' => 5,
        ), $atts);

        // Parse boolean attributes
        $show_stats = filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN);
        $show_recent = filter_var($atts['show_recent'], FILTER_VALIDATE_BOOLEAN);
        $show_health = filter_var($atts['show_health'], FILTER_VALIDATE_BOOLEAN);
        $recent_count = intval($atts['recent_count']);

        if ($recent_count < 1) $recent_count = 5;
        if ($recent_count > 20) $recent_count = 20;

        ob_start();
        echo '<div id="poker-dashboard">';
        $this->render_dashboard_content($show_stats, $show_recent, $show_health, $recent_count);
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render dashboard content
     */
    private function render_dashboard_content($show_stats, $show_recent, $show_health, $recent_count)
    {
        global $wpdb;

        // Initialize filter system
        $filters = new Poker_Dashboard_Filters();
        $current_url = remove_query_arg(array_keys($_GET));

        // Render filter controls first
        $this->render_filter_controls($filters, $current_url);

        // Get filtered tournament IDs for all queries
        $filtered_tournament_ids = $filters->get_filtered_tournament_ids();

        // Get counts
        $tournament_count = wp_count_posts('tournament');
        $total_tournaments = $tournament_count->publish;

        $player_count = wp_count_posts('player');
        $total_players = $player_count->publish;

        $season_count = wp_count_posts('tournament_season');
        $total_seasons = $season_count->publish;

        // Get recent tournaments (filtered if active)
        $tournament_args = array(
            'post_type' => 'tournament',
            'posts_per_page' => $recent_count,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        );

        // Apply season filter if active
        $active_filters = $filters->get_active_filters();
        if (!empty($active_filters['season'])) {
            $tournament_args['meta_query'] = array(
                array(
                    'key' => '_season_id',
                    'value' => $active_filters['season'],
                    'compare' => '='
                )
            );
        }

        $recent_tournaments = get_posts($tournament_args);

        // Get data mart health info
        $datamart_last_refresh = get_option('tdwp_statistics_last_refresh', null);

        // Render stats cards
        if ($show_stats) {
            $this->render_stats_cards($total_tournaments, $total_players, $total_seasons);
        }

        // Render recent tournaments
        if ($show_recent && !empty($recent_tournaments)) {
            $this->render_recent_tournaments($recent_tournaments);
        }

        // Render overall standings (always show if enabled)
        $this->render_overall_standings($filtered_tournament_ids);

        // Render data mart health
        if ($show_health) {
            $this->render_data_mart_health($datamart_last_refresh);
        }
    }

    /**
     * Render stats cards
     */
    private function render_stats_cards($total_tournaments, $total_players, $total_seasons)
    {
        ?>
        <div class="poker-dashboard-stats">
            <div class="poker-stat-card">
                <div class="poker-stat-icon dashicons-list-view">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div class="poker-stat-content">
                    <h3><?php esc_html_e('Tournaments', 'poker-tournament-import'); ?></h3>
                    <div class="poker-stat-value"><?php echo number_format($total_tournaments); ?></div>
                    <a href="<?php echo esc_url(get_post_type_archive_link('tournament')); ?>" class="poker-stat-link">
                        <?php esc_html_e('View All', 'poker-tournament-import'); ?>
                    </a>
                </div>
            </div>

            <div class="poker-stat-card">
                <div class="poker-stat-icon dashicons-groups">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="poker-stat-content">
                    <h3><?php esc_html_e('Players', 'poker-tournament-import'); ?></h3>
                    <div class="poker-stat-value"><?php echo number_format($total_players); ?></div>
                    <a href="<?php echo esc_url(get_post_type_archive_link('player')); ?>" class="poker-stat-link">
                        <?php esc_html_e('View All', 'poker-tournament-import'); ?>
                    </a>
                </div>
            </div>

            <div class="poker-stat-card">
                <div class="poker-stat-icon dashicons-calendar">
                    <span class="dashicons dashicons-calendar"></span>
                </div>
                <div class="poker-stat-content">
                    <h3><?php esc_html_e('Seasons', 'poker-tournament-import'); ?></h3>
                    <div class="poker-stat-value"><?php echo number_format($total_seasons); ?></div>
                    <a href="<?php echo esc_url(get_post_type_archive_link('tournament_season')); ?>" class="poker-stat-link">
                        <?php esc_html_e('View All', 'poker-tournament-import'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent tournaments table
     */
    private function render_recent_tournaments($recent_tournaments)
    {
        ?>
        <div class="poker-dashboard-recent">
            <h2><?php esc_html_e('Recent Tournaments', 'poker-tournament-import'); ?></h2>
            <table class="poker-dashboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Tournament', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Players', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tournaments as $tournament) :
                        $date = get_post_meta($tournament->ID, '_tournament_date', true);
                        $players = get_post_meta($tournament->ID, '_player_count', true);
                        ?>
                        <tr>
                            <td><?php echo $date ? esc_html(date('Y-m-d', strtotime($date))) : ''; ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_permalink($tournament->ID)); ?>">
                                    <?php echo esc_html($tournament->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo $players ? number_format($players) : '0'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render data mart health status
     */
    private function render_data_mart_health($last_refresh)
    {
        ?>
        <div class="poker-dashboard-health">
            <h2><?php esc_html_e('Statistics Status', 'poker-tournament-import'); ?></h2>
            <?php if ($last_refresh) : ?>
                <p class="poker-health-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php
                    printf(
                        /* translators: %s: last refresh date */
                        esc_html__('Statistics last updated: %s', 'poker-tournament-import'),
                        esc_html(date('Y-m-d H:i', strtotime($last_refresh)))
                    );
                    ?>
                </p>
            <?php else : ?>
                <p class="poker-health-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Statistics have not been refreshed yet.', 'poker-tournament-import'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render filter controls
     *
     * @param Poker_Dashboard_Filters $filters Filter system instance
     * @param string $current_url Base URL for form action
     */
    private function render_filter_controls($filters, $current_url)
    {
        echo $filters->render_filter_controls($current_url);
    }

    /**
     * Render overall standings table
     *
     * @param array $tournament_ids Tournament IDs to calculate standings for
     */
    private function render_overall_standings($tournament_ids = null)
    {
        // Require the standings calculator
        if (!class_exists('Poker_Series_Standings_Calculator')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-series-standings.php';
        }

        $calculator = new Poker_Series_Standings_Calculator();
        $standings = $calculator->calculate_overall_standings($tournament_ids);

        if (empty($standings)) {
            return;
        }
        ?>
        <div class="poker-dashboard-standings">
            <h2><?php esc_html_e('Overall Points Standings', 'poker-tournament-import'); ?></h2>
            <table class="poker-dashboard-table poker-standings-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Rank', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Player', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Points', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Played', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Best', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Avg Finish', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('1st', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Top 3', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Top 5', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $standing) :
                        $rank_display = $standing['rank'];
                        if ($standing['is_tied']) {
                            $rank_display .= 'T';
                        }

                        // Add medal indicators for top ranks
                        $rank_suffix = '';
                        $rank_class = '';
                        if ($standing['rank'] === 1) {
                            $rank_suffix = ' 🥇';
                            $rank_class = ' rank-first';
                        } elseif ($standing['rank'] === 2) {
                            $rank_suffix = ' 🥈';
                            $rank_class = ' rank-second';
                        } elseif ($standing['rank'] === 3) {
                            $rank_suffix = ' 🥉';
                            $rank_class = ' rank-third';
                        }
                        ?>
                        <tr<?php echo $rank_class ? ' class="' . esc_attr($rank_class) . '"' : ''; ?>>
                            <td class="rank-cell<?php echo esc_attr($rank_class); ?>">
                                <?php echo esc_html($rank_display . $rank_suffix); ?>
                            </td>
                            <td>
                                <?php if ($standing['player_url']) : ?>
                                    <a href="<?php echo esc_url($standing['player_url']); ?>">
                                        <?php echo esc_html($standing['player_name']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($standing['player_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="points-cell"><?php echo number_format($standing['overall_points'], 1); ?></td>
                            <td><?php echo number_format($standing['tournaments_played']); ?></td>
                            <td><?php echo esc_html($standing['best_finish']); ?></td>
                            <td><?php echo number_format($standing['avg_finish'], 1); ?></td>
                            <td><?php echo number_format($standing['tie_breakers']['first_places']); ?></td>
                            <td><?php echo number_format($standing['tie_breakers']['top3_finishes']); ?></td>
                            <td><?php echo number_format($standing['tie_breakers']['top5_finishes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize shortcode
new Poker_Dashboard_Shortcode();
