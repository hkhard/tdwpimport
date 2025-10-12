<?php
/**
 * Single Tournament Template
 *
 * This template displays a single tournament with all details,
 * results, and statistics in a professional layout.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="poker-tournament-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('tournament-single'); ?>>

                <!-- Tournament Header -->
                <header class="tournament-single-header">
                    <div class="tournament-title-section">
                        <h1 class="tournament-title"><?php the_title(); ?></h1>
                        <div class="tournament-meta-breadcrumb">
                            <?php
                            $series_id = get_post_meta(get_the_ID(), '_series_id', true);
                            $season_id = get_post_meta(get_the_ID(), '_season_id', true);

                            if ($series_id) {
                                echo '<span class="breadcrumb-item"><a href="' . get_permalink($series_id) . '">' . esc_html(get_the_title($series_id)) . '</a></span>';
                            }

                            if ($season_id) {
                                echo '<span class="breadcrumb-separator"> / </span>';
                                echo '<span class="breadcrumb-item"><a href="' . get_permalink($season_id) . '">' . esc_html(get_the_title($season_id)) . '</a></span>';
                            }

                            echo '<span class="breadcrumb-separator"> / </span>';
                            echo '<span class="breadcrumb-item current">' . esc_html(get_the_title()) . '</span>';
                            ?>
                        </div>
                    </div>

                    <div class="tournament-actions">
                        <button class="print-tournament" onclick="window.print()">
                            <i class="icon-print"></i> <?php _e('Print', 'poker-tournament-import'); ?>
                        </button>
                        <button class="export-tournament" data-tournament-id="<?php the_ID(); ?>" data-format="csv">
                            <i class="icon-download"></i> <?php _e('Export CSV', 'poker-tournament-import'); ?>
                        </button>
                        <button class="share-tournament" onclick="navigator.share ? navigator.share({title: '<?php the_title(); ?>', url: window.location.href}) : navigator.clipboard.writeText(window.location.href)">
                            <i class="icon-share"></i> <?php _e('Share', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                </header>

                <!-- Tournament Quick Stats -->
                <section class="tournament-quick-stats">
                    <div class="stats-grid">
                        <?php
                        $tournament_uuid = get_post_meta(get_the_ID(), '_tournament_uuid', true);
                        $players_count = get_post_meta(get_the_ID(), '_players_count', true);
                        $prize_pool = get_post_meta(get_the_ID(), '_prize_pool', true);
                        $buy_in = get_post_meta(get_the_ID(), '_buy_in', true);
                        $tournament_date = get_post_meta(get_the_ID(), '_tournament_date', true);
                        $currency = get_post_meta(get_the_ID(), '_currency', true) ?: '$';
                        ?>

                        <div class="stat-card primary">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number"><?php echo esc_html($players_count ?: '0'); ?></div>
                            <div class="stat-label"><?php _e('Players', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon">💰</div>
                            <div class="stat-number"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></div>
                            <div class="stat-label"><?php _e('Prize Pool', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon">🎫</div>
                            <div class="stat-number"><?php echo esc_html($currency . $buy_in); ?></div>
                            <div class="stat-label"><?php _e('Buy-in', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon">📅</div>
                            <div class="stat-number"><?php echo esc_html($tournament_date ? date_i18n('M j', strtotime($tournament_date)) : '--'); ?></div>
                            <div class="stat-label"><?php _e('Date', 'poker-tournament-import'); ?></div>
                        </div>
                    </div>
                </section>

                <!-- Tournament Content -->
                <section class="tournament-content-section">
                    <div class="tournament-content-grid">

                        <!-- Main Content -->
                        <div class="tournament-main-content">
                            <?php if (get_the_content()) : ?>
                            <div class="tournament-description">
                                <h2><?php _e('Tournament Details', 'poker-tournament-import'); ?></h2>
                                <div class="tournament-description-content">
                                    <?php the_content(); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Results Table -->
                            <div class="tournament-results-section">
                                <h2><?php _e('Official Results', 'poker-tournament-import'); ?></h2>
                                <?php
                                // Use the shortcode to display results
                                echo do_shortcode('[tournament_results id="' . get_the_ID() . '" show_players="true" show_structure="true"]');
                                ?>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <aside class="tournament-sidebar">
                            <!-- Tournament Statistics -->
                            <?php if ($tournament_uuid) : ?>
                            <div class="sidebar-widget tournament-statistics-widget">
                                <h3><?php _e('Tournament Statistics', 'poker-tournament-import'); ?></h3>
                                <?php
                                // Get tournament statistics
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'poker_tournament_players';
                                $stats = $wpdb->get_row($wpdb->prepare(
                                    "SELECT
                                        COUNT(*) as paid_positions,
                                        SUM(winnings) as total_paid,
                                        AVG(winnings) as avg_winnings,
                                        MAX(winnings) as first_place,
                                        MIN(finish_position) as best_finish
                                    FROM $table_name
                                    WHERE tournament_id = %s AND winnings > 0",
                                    $tournament_uuid
                                ));

                                if ($stats && $stats->paid_positions > 0) :
                                ?>
                                    <div class="mini-stats-grid">
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($stats->paid_positions); ?></span>
                                            <span class="mini-stat-label"><?php _e('Paid', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html(round(($stats->paid_positions / $players_count) * 100, 1)); ?>%</span>
                                            <span class="mini-stat-label"><?php _e('Cash Rate', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($stats->first_place, 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('1st Prize', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($stats->avg_winnings, 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('Avg Cash', 'poker-tournament-import'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Prize Distribution -->
                            <div class="sidebar-widget prize-distribution-widget">
                                <h3><?php _e('Prize Distribution', 'poker-tournament-import'); ?></h3>
                                <?php
                                if ($tournament_uuid) {
                                    $paid_players = $wpdb->get_results($wpdb->prepare(
                                        "SELECT finish_position, winnings, player_id
                                         FROM $table_name
                                         WHERE tournament_id = %s AND winnings > 0
                                         ORDER BY finish_position ASC
                                         LIMIT 10",
                                        $tournament_uuid
                                    ));

                                    if ($paid_players) {
                                        echo '<div class="prize-distribution-chart">';
                                        foreach ($paid_players as $player) {
                                            $percentage = $prize_pool > 0 ? ($player->winnings / $prize_pool) * 100 : 0;
                                            echo '<div class="prize-bar">';
                                            echo '<div class="prize-position">' . esc_html($player->finish_position) . get_ordinal_suffix($player->finish_position) . '</div>';
                                            echo '<div class="prize-bar-container">';
                                            echo '<div class="prize-bar-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
                                            echo '</div>';
                                            echo '<div class="prize-amount">' . esc_html($currency . number_format($player->winnings, 0)) . '</div>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>

                            <!-- Recent Tournaments -->
                            <div class="sidebar-widget recent-tournaments-widget">
                                <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                                <?php
                                $recent_tournaments = get_posts(array(
                                    'post_type' => 'tournament',
                                    'posts_per_page' => 5,
                                    'post__not_in' => array(get_the_ID()),
                                    'meta_key' => '_tournament_date',
                                    'orderby' => 'meta_value',
                                    'order' => 'DESC'
                                ));

                                if ($recent_tournaments) {
                                    echo '<ul class="recent-tournaments-list">';
                                    foreach ($recent_tournaments as $tournament) {
                                        $date = get_post_meta($tournament->ID, '_tournament_date', true);
                                        echo '<li>';
                                        echo '<a href="' . get_permalink($tournament->ID) . '">' . esc_html($tournament->post_title) . '</a>';
                                        if ($date) {
                                            echo '<span class="tournament-date">' . esc_html(date_i18n('M j, Y', strtotime($date))) . '</span>';
                                        }
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                }
                                ?>
                            </div>
                        </aside>
                    </div>
                </section>

                <!-- Related Content -->
                <section class="tournament-related-content">
                    <h2><?php _e('Related Content', 'poker-tournament-import'); ?></h2>

                    <div class="related-content-grid">
                        <!-- Players from this tournament -->
                        <?php
                        if ($tournament_uuid) {
                            $players = $wpdb->get_results($wpdb->prepare(
                                "SELECT DISTINCT tp.player_id, p.post_title as player_name, p.ID as player_post_id
                                 FROM $table_name tp
                                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
                                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                 WHERE tp.tournament_id = %s
                                 ORDER BY tp.finish_position ASC
                                 LIMIT 8",
                                $tournament_uuid
                            ));

                            if ($players) {
                                echo '<div class="related-section">';
                                echo '<h3>' . __('Players in This Tournament', 'poker-tournament-import') . '</h3>';
                                echo '<div class="players-grid">';
                                foreach ($players as $player) {
                                    if ($player->player_post_id) {
                                        echo '<div class="player-card">';
                                        echo '<a href="' . get_permalink($player->player_post_id) . '">';
                                        echo '<div class="player-avatar">' . substr($player->player_name, 0, 2) . '</div>';
                                        echo '<div class="player-name">' . esc_html($player->player_name) . '</div>';
                                        echo '</a>';
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>

                        <!-- Related Tournaments -->
                        <?php
                        if ($series_id) {
                            $related_tournaments = get_posts(array(
                                'post_type' => 'tournament',
                                'posts_per_page' => 3,
                                'post__not_in' => array(get_the_ID()),
                                'meta_key' => '_series_id',
                                'meta_value' => $series_id,
                                'orderby' => 'date',
                                'order' => 'DESC'
                            ));

                            if ($related_tournaments) {
                                echo '<div class="related-section">';
                                echo '<h3>' . __('Other ' . esc_html(get_the_title($series_id)) . ' Tournaments', 'poker-tournament-import') . '</h3>';
                                echo '<div class="related-tournaments">';
                                foreach ($related_tournaments as $tournament) {
                                    echo '<div class="related-tournament-card">';
                                    echo '<a href="' . get_permalink($tournament->ID) . '">';
                                    echo '<h4>' . esc_html($tournament->post_title) . '</h4>';
                                    $date = get_post_meta($tournament->ID, '_tournament_date', true);
                                    if ($date) {
                                        echo '<span class="tournament-meta-date">' . esc_html(date_i18n('F j, Y', strtotime($date))) . '</span>';
                                    }
                                    echo '</a>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </section>

            </article>

        <?php endwhile; ?>
    </main>
</div>

<?php
get_footer();