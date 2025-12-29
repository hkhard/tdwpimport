<?php
/**
 * Single Player Template
 *
 * This template displays a single player profile with statistics,
 * tournament history, and career achievements.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?> <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Theme Header Template Part (v2.8.10: Block theme support) -->
<?php block_template_part('header'); ?>

<!-- Breadcrumb Navigation -->
<?php
$home_url = home_url('/');
$post_type = get_post_type();
$post_type_obj = get_post_type_object($post_type);
$archive_url = get_post_type_archive_link($post_type);
?>
<nav class="poker-breadcrumbs" style="padding: 10px 20px; background: #f5f5f5; margin-bottom: 20px; font-size: 14px; max-width: 1200px; margin-left: auto; margin-right: auto;">
    <a href="<?php echo esc_url($home_url); ?>" style="color: #2271b1; text-decoration: none;">Home</a>
    <span style="margin: 0 8px; color: #666;">›</span>
    <?php if ($archive_url): ?>
        <a href="<?php echo esc_url($archive_url); ?>" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($post_type_obj->labels->name); ?></a>
        <span style="margin: 0 8px; color: #666;">›</span>
    <?php endif; ?>
    <span style="color: #666;"><?php the_title(); ?></span>
</nav>

<div class="poker-player-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('player-single'); ?>>

                <!-- Player Header -->
                <header class="player-single-header">
                    <div class="player-profile-section">
                        <div class="player-avatar-large">
                            <?php echo esc_html(substr(get_the_title(), 0, 2)); ?>
                        </div>
                        <div class="player-title-section">
                            <h1 class="player-title"><?php the_title(); ?></h1>
                            <div class="player-subtitle">
                                <?php esc_html_e('Professional Poker Player', 'poker-tournament-import'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="player-actions">
                        <button class="print-profile" onclick="window.print()">
                            <i class="icon-print"></i> <?php esc_html_e('Print Profile', 'poker-tournament-import'); ?>
                        </button>
                        <button class="export-profile" data-player-id="<?php the_ID(); ?>" data-format="csv">
                            <i class="icon-download"></i> <?php esc_html_e('Export Stats', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                </header>

                <!-- Player Career Stats -->
                <section class="player-career-stats">
                    <h2><?php esc_html_e('Career Statistics', 'poker-tournament-import'); ?></h2>
                    <?php
                    // Use shortcode to display player stats
                    echo do_shortcode('[player_profile id="' . get_the_ID() . '" show_stats="true"]');
                    ?>
                </section>

                <!-- Performance Chart -->
                <section class="player-performance-chart">
                    <h2><?php esc_html_e('Performance Overview', 'poker-tournament-import'); ?></h2>
                    <?php
                    $player_uuid = get_post_meta(get_the_ID(), 'player_uuid', true);
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'poker_tournament_players';

                    if ($player_uuid) {
                        // Get tournament history for chart
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                        $tournaments = $wpdb->get_results($wpdb->prepare(
                            "SELECT tp.finish_position, tp.winnings, tp.points,
                                    p.post_title as tournament_name,
                                    pm.meta_value as tournament_date
                             FROM $table_name tp
                             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = (
                                 SELECT post_id FROM {$wpdb->postmeta}
                                 WHERE meta_key = 'tournament_uuid' AND meta_value = tp.tournament_id
                                 LIMIT 1
                             ) AND pm.meta_key = 'tournament_date'
                             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                             WHERE tp.player_id = %s
                             ORDER BY pm.meta_value DESC
                             LIMIT 20",
                            $player_uuid
                        ));

                        if ($tournaments) {
                            echo '<div class="performance-grid">';

                            // Finishing positions distribution
                            $position_counts = array();
                            $total_winnings = 0;
                            $best_finish = 999;
                            $cashes = 0;

                            foreach ($tournaments as $tournament) {
                                $position = intval($tournament->finish_position);
                                $position_counts[$position] = ($position_counts[$position] ?? 0) + 1;
                                $total_winnings += floatval($tournament->winnings);
                                if ($position < $best_finish) $best_finish = $position;
                                if ($tournament->winnings > 0) $cashes++;
                            }

                            // Create performance cards
                            echo '<div class="performance-cards">';
                            echo '<div class="perf-card achievement">';
                            echo '<div class="perf-number">' . esc_html($best_finish) . esc_html(get_ordinal_suffix($best_finish)) . '</div>';
                            echo '<div class="perf-label">' . esc_html__('Best Finish', 'poker-tournament-import') . '</div>';
                            echo '</div>';
                            echo '<div class="perf-card success">';
                            echo '<div class="perf-number">' . esc_html($cashes) . '</div>';
                            echo '<div class="perf-label">' . esc_html__('Total Cashes', 'poker-tournament-import') . '</div>';
                            echo '</div>';
                            echo '<div class="perf-card money">';
                            echo '<div class="perf-number">$' . esc_html(number_format($total_winnings, 0)) . '</div>';
                            echo '<div class="perf-label">' . esc_html__('Total Winnings', 'poker-tournament-import') . '</div>';
                            echo '</div>';
                            echo '<div class="perf-card consistency">';
                            $cash_rate = count($tournaments) > 0 ? ($cashes / count($tournaments)) * 100 : 0;
                            echo '<div class="perf-number">' . esc_html(round($cash_rate, 1)) . '%</div>';
                            echo '<div class="perf-label">' . esc_html__('Cash Rate', 'poker-tournament-import') . '</div>';
                            echo '</div>';
                            echo '</div>';

                            // Position distribution chart
                            if (!empty($position_counts)) {
                                echo '<div class="position-distribution">';
                                echo '<h3>' . esc_html__('Finishing Position Distribution', 'poker-tournament-import') . '</h3>';
                                echo '<div class="position-bars">';

                                ksort($position_counts);
                                $max_count = max($position_counts);

                                foreach ($position_counts as $position => $count) {
                                    $percentage = ($count / $max_count) * 100;
                                    $color_class = '';
                                    if ($position === 1) $color_class = 'gold';
                                    elseif ($position === 2) $color_class = 'silver';
                                    elseif ($position === 3) $color_class = 'bronze';
                                    elseif ($position <= 9) $color_class = 'final-table';

                                    echo '<div class="position-bar">';
                                    echo '<div class="position-label">' . esc_html($position) . esc_html(get_ordinal_suffix($position)) . '</div>';
                                    echo '<div class="position-bar-container">';
                                    echo '<div class="position-bar-fill ' . esc_attr($color_class) . '" style="width: ' . esc_attr($percentage) . '%"></div>';
                                    echo '</div>';
                                    echo '<div class="position-count">' . esc_html($count) . ' ' . esc_html(_n('time', 'times', $count, 'poker-tournament-import')) . '</div>';
                                    echo '</div>';
                                }

                                echo '</div>';
                                echo '</div>';
                            }

                            echo '</div>';
                        }
                    }
                    ?>
                </section>

                <!-- Player Content -->
                <section class="player-content-section">
                    <div class="player-content-grid">

                        <!-- Main Content -->
                        <div class="player-main-content">
                            <?php if (get_the_content()) : ?>
                            <div class="player-biography">
                                <h2><?php esc_html_e('Biography', 'poker-tournament-import'); ?></h2>
                                <div class="player-biography-content">
                                    <?php the_content(); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Tournament History -->
                            <div class="player-tournament-history-section">
                                <h2><?php esc_html_e('Tournament History', 'poker-tournament-import'); ?></h2>
                                <?php
                                if ($player_uuid) {
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                                    $history_tournaments = $wpdb->get_results($wpdb->prepare(
                                        "SELECT tp.*, p.post_title as tournament_name, p.post_date,
                                                pm.meta_value as tournament_date,
                                                ps.post_title as series_name
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = (
                                             SELECT post_id FROM {$wpdb->postmeta}
                                             WHERE meta_key = 'tournament_uuid' AND meta_value = tp.tournament_id
                                             LIMIT 1
                                         ) AND pm.meta_key = 'tournament_date'
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         LEFT JOIN {$wpdb->postmeta} psm ON p.ID = psm.post_id AND psm.meta_key = 'series_id'
                                         LEFT JOIN {$wpdb->posts} ps ON psm.meta_value = ps.ID
                                         WHERE tp.player_id = %s
                                         ORDER BY pm.meta_value DESC, tp.finish_position ASC
                                         LIMIT 50",
                                        $player_uuid
                                    ));

                                    if ($history_tournaments) {
                                        echo '<div class="tournament-history-table-wrapper">';
                                        echo '<table class="tournament-history-table">';
                                        echo '<thead>';
                                        echo '<tr>';
                                        echo '<th>' . esc_html__('Date', 'poker-tournament-import') . '</th>';
                                        echo '<th>' . esc_html__('Tournament', 'poker-tournament-import') . '</th>';
                                        echo '<th>' . esc_html__('Series', 'poker-tournament-import') . '</th>';
                                        echo '<th>' . esc_html__('Position', 'poker-tournament-import') . '</th>';
                                        echo '<th>' . esc_html__('Winnings', 'poker-tournament-import') . '</th>';
                                        echo '<th>' . esc_html__('Points', 'poker-tournament-import') . '</th>';
                                        echo '</tr>';
                                        echo '</thead>';
                                        echo '<tbody>';

                                        foreach ($history_tournaments as $tournament) {
                                            $date_source = $tournament->tournament_date ?? $tournament->post_date ?? 'now';
                                            $display_date = date_i18n(get_option('date_format'), strtotime($date_source));

                                            $position_class = '';
                                            if ($tournament->finish_position == 1) $position_class = 'gold';
                                            elseif ($tournament->finish_position == 2) $position_class = 'silver';
                                            elseif ($tournament->finish_position == 3) $position_class = 'bronze';
                                            elseif ($tournament->finish_position <= 9) $position_class = 'final-table';

                                            echo '<tr class="' . esc_attr($position_class) . '">';
                                            echo '<td>' . esc_html($display_date) . '</td>';
                                            echo '<td><a href="' . esc_url(get_permalink($tournament->tournament_id)) . '">' . esc_html($tournament->tournament_name) . '</a></td>';
                                            echo '<td>' . ($tournament->series_name ? '<a href="' . esc_url(get_permalink($tournament->series_id)) . '">' . esc_html($tournament->series_name) . '</a>' : '-') . '</td>';
                                            echo '<td class="position">' . esc_html($tournament->finish_position) . esc_html(get_ordinal_suffix($tournament->finish_position)) . '</td>';
                                            echo '<td class="winnings">$' . esc_html(number_format($tournament->winnings, 2)) . '</td>';
                                            echo '<td class="points">' . esc_html(number_format($tournament->points, 1)) . '</td>';
                                            echo '</tr>';
                                        }

                                        echo '</tbody>';
                                        echo '</table>';
                                        echo '</div>';
                                    } else {
                                        echo '<p class="no-tournaments">' . esc_html__('No tournament history available for this player.', 'poker-tournament-import') . '</p>';
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <aside class="player-sidebar">
                            <!-- Achievement Badges -->
                            <div class="sidebar-widget player-achievements">
                                <h3><?php esc_html_e('Achievements', 'poker-tournament-import'); ?></h3>
                                <?php
                                if ($player_uuid) {
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                                    $wins = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM $table_name WHERE player_id = %s AND finish_position = 1",
                                        $player_uuid
                                    ));
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                                    $final_tables = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM $table_name WHERE player_id = %s AND finish_position <= 9",
                                        $player_uuid
                                    ));

                                    echo '<div class="achievement-badges">';
                                    if ($wins > 0) {
                                        /* translators: %d: number of tournament victories */
                                        echo '<div class="achievement-badge gold" title="' . esc_attr(sprintf(_n('%d tournament victory', '%d tournament victories', $wins, 'poker-tournament-import'), $wins)) . '">';
                                        echo '<div class="badge-icon">🏆</div>';
                                        echo '<div class="badge-count">' . esc_html($wins) . '</div>';
                                        echo '<div class="badge-label">' . esc_html__('Wins', 'poker-tournament-import') . '</div>';
                                        echo '</div>';
                                    }

                                    if ($final_tables > 0) {
                                        /* translators: %d: number of final table appearances */
                                        echo '<div class="achievement-badge final-table" title="' . esc_attr(sprintf(_n('%d final table appearance', '%d final table appearances', $final_tables, 'poker-tournament-import'), $final_tables)) . '">';
                                        echo '<div class="badge-icon">🎯</div>';
                                        echo '<div class="badge-count">' . esc_html($final_tables) . '</div>';
                                        echo '<div class="badge-label">' . esc_html__('Final Tables', 'poker-tournament-import') . '</div>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>

                            <!-- Recent Performance -->
                            <div class="sidebar-widget recent-performance">
                                <h3><?php esc_html_e('Recent Performance', 'poker-tournament-import'); ?></h3>
                                <?php
                                if ($player_uuid) {
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                                    $recent_tournaments = $wpdb->get_results($wpdb->prepare(
                                        "SELECT tp.finish_position, tp.winnings, p.post_title as tournament_name,
                                                pm.meta_value as tournament_date
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = (
                                             SELECT post_id FROM {$wpdb->postmeta}
                                             WHERE meta_key = 'tournament_uuid' AND meta_value = tp.tournament_id
                                             LIMIT 1
                                         ) AND pm.meta_key = 'tournament_date'
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         WHERE tp.player_id = %s
                                         ORDER BY pm.meta_value DESC
                                         LIMIT 10",
                                        $player_uuid
                                    ));

                                    if ($recent_tournaments) {
                                        echo '<ul class="recent-performance-list">';
                                        foreach ($recent_tournaments as $tournament) {
                                            $position_class = '';
                                            $position_icon = '';
                                            if ($tournament->finish_position == 1) {
                                                $position_class = 'win';
                                                $position_icon = '🏆';
                                            } elseif ($tournament->finish_position <= 3) {
                                                $position_class = 'podium';
                                                $position_icon = '🥈';
                                            } elseif ($tournament->finish_position <= 9) {
                                                $position_class = 'final-table';
                                                $position_icon = '🎯';
                                            } else {
                                                $position_icon = $tournament->finish_position;
                                            }

                                            echo '<li class="' . esc_attr($position_class) . '">';
                                            echo '<span class="recent-position">' . esc_html($position_icon) . '</span>';
                                            echo '<div class="recent-details">';
                                            echo '<span class="recent-tournament">' . esc_html($tournament->tournament_name) . '</span>';
                                            echo '<span class="recent-date">' . esc_html(date_i18n('M j, Y', strtotime($tournament->tournament_date))) . '</span>';
                                            echo '</div>';
                                            if ($tournament->winnings > 0) {
                                                echo '<span class="recent-winnings">$' . esc_html(number_format($tournament->winnings, 0)) . '</span>';
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                }
                                ?>
                            </div>

                            <!-- Top Series -->
                            <div class="sidebar-widget top-series">
                                <h3><?php esc_html_e('Top Series', 'poker-tournament-import'); ?></h3>
                                <?php
                                if ($player_uuid) {
                                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                                    $top_series = $wpdb->get_results($wpdb->prepare(
                                        "SELECT ps.post_title as series_name, ps.ID as series_id,
                                                COUNT(*) as tournaments_played,
                                                SUM(tp.winnings) as total_winnings,
                                                MIN(tp.finish_position) as best_finish
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} tpm ON tpm.meta_value = tp.tournament_id AND tpm.meta_key = 'tournament_uuid'
                                         LEFT JOIN {$wpdb->posts} tp_post ON tpm.post_id = tp_post.ID
                                         LEFT JOIN {$wpdb->postmeta} spm ON tp_post.ID = spm.post_id AND spm.meta_key = 'series_id'
                                         LEFT JOIN {$wpdb->posts} ps ON spm.meta_value = ps.ID
                                         WHERE tp.player_id = %s AND ps.ID IS NOT NULL
                                         GROUP BY ps.ID
                                         ORDER BY total_winnings DESC
                                         LIMIT 5",
                                        $player_uuid
                                    ));

                                    if ($top_series) {
                                        echo '<ul class="top-series-list">';
                                        foreach ($top_series as $series) {
                                            echo '<li>';
                                            echo '<a href="' . esc_url(get_permalink($series->series_id)) . '">';
                                            echo '<div class="series-info">';
                                            echo '<span class="series-name">' . esc_html($series->series_name) . '</span>';
                                            echo '<span class="series-stats">';
                                            echo esc_html($series->tournaments_played) . ' ' . esc_html(_n('tournament', 'tournaments', $series->tournaments_played, 'poker-tournament-import'));
                                            echo ' • Best: ' . esc_html($series->best_finish) . esc_html(get_ordinal_suffix($series->best_finish));
                                            echo '</span>';
                                            echo '</div>';
                                            echo '<div class="series-winnings">$' . esc_html(number_format($series->total_winnings, 0)) . '</div>';
                                            echo '</a>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                }
                                ?>
                            </div>
                        </aside>
                    </div>
                </section>

                <!-- Related Players -->
                <section class="related-players-section">
                    <h2><?php esc_html_e('Related Players', 'poker-tournament-import'); ?></h2>
                    <?php
                    // Find players who competed in the same tournaments
                    if ($player_uuid) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                        $related_players = $wpdb->get_results($wpdb->prepare(
                            "SELECT DISTINCT tp2.player_id, p.post_title as player_name, p.ID as player_post_id,
                                    COUNT(*) as shared_tournaments
                             FROM $table_name tp1
                             JOIN $table_name tp2 ON tp1.tournament_id = tp2.tournament_id AND tp1.player_id != tp2.player_id
                             LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp2.player_id AND pm.meta_key = 'player_uuid'
                             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                             WHERE tp1.player_id = %s AND p.ID IS NOT NULL
                             GROUP BY tp2.player_id
                             ORDER BY shared_tournaments DESC, MIN(tp2.finish_position) ASC
                             LIMIT 8",
                            $player_uuid
                        ));

                        if ($related_players) {
                            echo '<div class="related-players-grid">';
                            foreach ($related_players as $player) {
                                echo '<div class="related-player-card">';
                                echo '<a href="' . esc_url(get_permalink($player->player_post_id)) . '">';
                                echo '<div class="related-player-avatar">' . esc_html(substr($player->player_name, 0, 2)) . '</div>';
                                echo '<div class="related-player-info">';
                                echo '<div class="related-player-name">' . esc_html($player->player_name) . '</div>';
                                echo '<div class="shared-tournaments">' . esc_html($player->shared_tournaments) . ' ' . esc_html(_n('shared tournament', 'shared tournaments', $player->shared_tournaments, 'poker-tournament-import')) . '</div>';
                                echo '</div>';
                                echo '</a>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    }
                    ?>
                </section>

            </article>

        <?php endwhile; ?>
    </main>
</div>

<!-- Theme Footer Template Part (v2.8.10: Block theme support) -->
<?php block_template_part('footer'); ?>

<?php wp_footer(); ?>
</body>
</html>