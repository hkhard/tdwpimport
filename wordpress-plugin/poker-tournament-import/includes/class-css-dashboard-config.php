<?php
/**
 * CSS Dashboard Configuration for Poker Tournament Import
 *
 * Maps existing poker tournament data to CSS dashboard components
 */

class Poker_CSS_Dashboard_Config extends CSS_Dashboard_Base
{

    public function __construct()
    {
        parent::__construct();
        add_filter('css_dashboard_config', array($this, 'get_dashboard_config'));
    }

    protected function get_menu_slug()
    {
        return 'poker-css-dashboard';
    }

    protected function get_page_title()
    {
        return 'Poker Dashboard';
    }

    protected function get_menu_title()
    {
        return 'Poker Dashboard';
    }

    protected function get_menu_icon()
    {
        return 'dashicons-chart-pie';
    }

    protected function get_menu_position()
    {
        return 3;
    }

    /**
     * Filter controls section
     *
     * @param Poker_Dashboard_Filters $filters Filter system instance
     * @return array Section configuration
     */
    private function get_filters_section($filters) {
        $current_url = remove_query_arg(array_keys($_GET)); // Clear all params

        return array(
            'id' => 'dashboard-filters',
            'title' => '', // No title for filter bar
            'columns' => 1,
            'components' => array(
                array(
                    'id' => 'filter-controls',
                    'parent_section_id' => 'dashboard-filters',
                    'type' => 'custom', // Custom HTML type
                    'html' => $filters->render_filter_controls($current_url)
                )
            )
        );
    }

    public function get_dashboard_config($config)
    {
        global $wpdb;

        // Initialize filter system
        $filters = new Poker_Dashboard_Filters();

        $config['title'] = 'Poker Tournament Dashboard';
        $config['sections'] = array();

        // Add filter controls section (first, before data)
        $config['sections'][] = $this->get_filters_section($filters);

        // Overview Statistics Section
        $config['sections'][] = $this->get_overview_section();

        // Tournaments Table Section
        $config['sections'][] = $this->get_tournaments_section();

        // Players Table Section
        $config['sections'][] = $this->get_players_section();

        // Series Table Section
        $config['sections'][] = $this->get_series_section();

        // Seasons Table Section
        $config['sections'][] = $this->get_seasons_section();

        return $config;
    }

    private function get_overview_section()
    {
        $tournament_count = wp_count_posts('tournament')->publish;
        $player_count = wp_count_posts('player')->publish;
        $series_count = wp_count_posts('tournament_series')->publish;
        $season_count = wp_count_posts('tournament_season')->publish;

        // Get total prize pool from custom table
        global $wpdb;
        $total_prize = $wpdb->get_var("SELECT SUM(total_prize_pool) FROM {$wpdb->prefix}poker_financial_summary");

        return array(
            'id' => 'overview-stats',
            'title' => 'Overview Statistics',
            'columns' => 4,
            'components' => array(
                array(
                    'id' => 'total-tournaments',
                    'parent_section_id' => 'overview-stats',
                    'type' => 'stat',
                    'title' => 'Total Tournaments',
                    'value' => number_format($tournament_count),
                    'icon' => 'dashicons-calendar',
                    'color' => 'primary',
                ),
                array(
                    'id' => 'total-players',
                    'parent_section_id' => 'overview-stats',
                    'type' => 'stat',
                    'title' => 'Total Players',
                    'value' => number_format($player_count),
                    'icon' => 'dashicons-groups',
                    'color' => 'success',
                ),
                array(
                    'id' => 'total-prize',
                    'parent_section_id' => 'overview-stats',
                    'type' => 'stat',
                    'title' => 'Total Prize Pool',
                    'value' => '$' . number_format($total_prize ?: 0),
                    'icon' => 'dashicons-money-alt',
                    'color' => 'primary',
                ),
                array(
                    'id' => 'series-count',
                    'parent_section_id' => 'overview-stats',
                    'type' => 'stat',
                    'title' => 'Series / Seasons',
                    'value' => number_format($series_count) . ' / ' . number_format($season_count),
                    'icon' => 'dashicons-list-view',
                    'color' => 'success',
                ),
            )
        );
    }

    private function get_tournaments_section()
    {
        $args = array(
            'post_type' => 'tournament',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        );

        $tournaments = get_posts($args);
        $rows = array();

        foreach ($tournaments as $index => $tournament) {
            $date = get_post_meta($tournament->ID, '_tournament_date', true);
            $players = get_post_meta($tournament->ID, '_player_count', true);
            $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);

            $rows[] = array(
                'cells' => array(
                    esc_html($tournament->post_title),
                    $date ? date('Y-m-d', strtotime($date)) : 'N/A',
                    $players ? number_format($players) : '0',
                    $prize_pool ? '$' . number_format($prize_pool) : '$0',
                ),
            );
        }

        return array(
            'id' => 'tournaments-table',
            'title' => 'Recent Tournaments',
            'components' => array(
                array(
                    'id' => 'tournaments-data',
                    'parent_section_id' => 'tournaments-table',
                    'type' => 'table',
                    'headers' => array('Tournament', 'Date', 'Players', 'Prize Pool'),
                    'rows' => $rows,
                    'per_page' => 25,
                )
            )
        );
    }

    private function get_players_section()
    {
        global $wpdb;

        // Get player statistics from custom table
        $players = $wpdb->get_results("
            SELECT p.ID, p.post_title,
                   COALESCE(stats.total_winnings, 0) as winnings,
                   COALESCE(stats.tournaments_played, 0) as played,
                   COALESCE(stats.avg_finish, 0) as avg_finish
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}poker_player_roi stats ON p.ID = stats.player_id
            WHERE p.post_type = 'player' AND p.post_status = 'publish'
            ORDER BY winnings DESC
            LIMIT 100
        ");

        $rows = array();

        foreach ($players as $player) {
            $rows[] = array(
                'cells' => array(
                    esc_html($player->post_title),
                    number_format($player->winnings),
                    number_format($player->played),
                    number_format($player->avg_finish, 1),
                ),
            );
        }

        return array(
            'id' => 'players-table',
            'title' => 'Player Rankings',
            'components' => array(
                array(
                    'id' => 'players-data',
                    'parent_section_id' => 'players-table',
                    'type' => 'table',
                    'headers' => array('Player', 'Total Winnings', 'Tournaments', 'Avg Finish'),
                    'rows' => $rows,
                    'per_page' => 25,
                )
            )
        );
    }

    private function get_series_section()
    {
        global $wpdb;

        $series_list = get_posts(array(
            'post_type' => 'tournament_series',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $rows = array();

        foreach ($series_list as $series) {
            $tournament_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tournament'
                AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_tournament_series_id'
                    AND meta_value = %d
                )",
                $series->ID
            ));

            $rows[] = array(
                'cells' => array(
                    esc_html($series->post_title),
                    $tournament_count ? number_format($tournament_count) : '0',
                ),
            );
        }

        return array(
            'id' => 'series-table',
            'title' => 'Tournament Series',
            'components' => array(
                array(
                    'id' => 'series-data',
                    'parent_section_id' => 'series-table',
                    'type' => 'table',
                    'headers' => array('Series', 'Tournaments'),
                    'rows' => $rows,
                    'per_page' => 25,
                )
            )
        );
    }

    private function get_seasons_section()
    {
        global $wpdb;

        $seasons = get_posts(array(
            'post_type' => 'tournament_season',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'DESC',
        ));

        $rows = array();

        foreach ($seasons as $season) {
            $tournament_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tournament'
                AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_tournament_season_id'
                    AND meta_value = %d
                )",
                $season->ID
            ));

            $rows[] = array(
                'cells' => array(
                    esc_html($season->post_title),
                    $tournament_count ? number_format($tournament_count) : '0',
                ),
            );
        }

        return array(
            'id' => 'seasons-table',
            'title' => 'Tournament Seasons',
            'components' => array(
                array(
                    'id' => 'seasons-data',
                    'parent_section_id' => 'seasons-table',
                    'type' => 'table',
                    'headers' => array('Season', 'Tournaments'),
                    'rows' => $rows,
                    'per_page' => 25,
                )
            )
        );
    }
}

new Poker_CSS_Dashboard_Config();
