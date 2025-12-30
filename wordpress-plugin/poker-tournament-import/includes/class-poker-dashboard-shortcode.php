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

        // Get counts
        $tournament_count = wp_count_posts('tournament');
        $total_tournaments = $tournament_count->publish;

        $player_count = wp_count_posts('player');
        $total_players = $player_count->publish;

        $season_count = wp_count_posts('tournament_season');
        $total_seasons = $season_count->publish;

        // Get recent tournaments
        $recent_tournaments = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => $recent_count,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        ));

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
}

// Initialize shortcode
new Poker_Dashboard_Shortcode();
