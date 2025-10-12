<?php
/**
 * Tournament Series Taxonomy Template
 *
 * This template displays a single tournament series with
 * overview, statistics, and list of tournaments.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="poker-series-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('series-single'); ?>>

                <!-- Series Header -->
                <header class="series-single-header">
                    <div class="series-title-section">
                        <h1 class="series-title"><?php the_title(); ?></h1>
                        <div class="series-subtitle">
                            <?php _e('Tournament Series', 'poker-tournament-import'); ?>
                        </div>
                        <?php if (get_the_content()) : ?>
                            <div class="series-description-brief">
                                <?php echo wp_trim_words(get_the_content(), 30); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="series-actions">
                        <button class="print-series" onclick="window.print()">
                            <i class="icon-print"></i> <?php _e('Print Series', 'poker-tournament-import'); ?>
                        </button>
                        <button class="export-series" data-series-id="<?php the_ID(); ?>" data-format="csv">
                            <i class="icon-download"></i> <?php _e('Export All', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                </header>

                <!-- Series Statistics Overview -->
                <section class="series-stats-overview">
                    <h2><?php _e('Series Overview', 'poker-tournament-import'); ?></h2>
                    <?php
                    $series_uuid = get_post_meta(get_the_ID(), '_series_uuid', true);
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'poker_tournament_players';

                    // Get series statistics
                    $series_tournaments = get_posts(array(
                        'post_type' => 'tournament',
                        'meta_key' => '_series_id',
                        'meta_value' => get_the_ID(),
                        'posts_per_page' => -1,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ));

                    $total_tournaments = count($series_tournaments);
                    $total_players = 0;
                    $total_prize_pool = 0;
                    $unique_players = array();
                    $tournament_ids = array();

                    foreach ($series_tournaments as $tournament) {
                        $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
                        $players_count = get_post_meta($tournament->ID, '_players_count', true);
                        $prize_pool = get_post_meta($tournament->ID, '_prize_pool', true);

                        $total_players += intval($players_count);
                        $total_prize_pool += floatval($prize_pool);
                        $tournament_ids[] = $tournament_uuid;

                        // Get unique players
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
                    $avg_players = $total_tournaments > 0 ? $total_players / $total_tournaments : 0;

                    // Get series leaderboard
                    $leaderboard = array();
                    if (!empty($tournament_ids)) {
                        $placeholders = implode(',', array_fill(0, count($tournament_ids), '%s'));
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
                             LIMIT 10",
                            ...$tournament_ids
                        ));
                    }
                    ?>

                    <div class="series-stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon">🏆</div>
                            <div class="stat-number"><?php echo esc_html($total_tournaments); ?></div>
                            <div class="stat-label"><?php _e('Tournaments', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number"><?php echo esc_html($unique_players_count); ?></div>
                            <div class="stat-label"><?php _e('Unique Players', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon">💰</div>
                            <div class="stat-number">$<?php echo esc_html(number_format($total_prize_pool, 0)); ?></div>
                            <div class="stat-label"><?php _e('Total Prize Pool', 'poker-tournament-import'); ?></div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon">📊</div>
                            <div class="stat-number">$<?php echo esc_html(number_format($avg_prize_pool, 0)); ?></div>
                            <div class="stat-label"><?php _e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                        </div>
                    </div>
                </section>

                <div class="series-content-grid">
                    <!-- Main Content -->
                    <div class="series-main-content">

                        <!-- Series Description -->
                        <?php if (get_the_content()) : ?>
                        <section class="series-description-section">
                            <h2><?php _e('About This Series', 'poker-tournament-import'); ?></h2>
                            <div class="series-description-content">
                                <?php the_content(); ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Series Leaderboard -->
                        <?php if (!empty($leaderboard)) : ?>
                        <section class="series-leaderboard-section">
                            <h2><?php _e('Series Leaderboard', 'poker-tournament-import'); ?></h2>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaderboard as $index => $player) : ?>
                                            <?php
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
                                                    <?php if ($index < 3) : ?>
                                                        <span class="rank-medal"><?php echo ($index === 0) ? '🥇' : (($index === 1) ? '🥈' : '🥉'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="player">
                                                    <?php if ($player_post) : ?>
                                                        <a href="<?php echo get_permalink($player_post->ID); ?>" class="player-link">
                                                            <span class="player-avatar"><?php echo substr($player_post->post_title, 0, 2); ?></span>
                                                            <?php echo esc_html($player_post->post_title); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <?php echo esc_html($player->player_id); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="tournaments"><?php echo esc_html($player->tournaments_played); ?></td>
                                                <td class="winnings">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></td>
                                                <td class="points"><?php echo esc_html(number_format($player->total_points, 1)); ?></td>
                                                <td class="best-finish"><?php echo esc_html($player->best_finish); ?><?php echo get_ordinal_suffix($player->best_finish); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Tournaments in Series -->
                        <section class="series-tournaments-section">
                            <h2><?php _e('Tournaments in This Series', 'poker-tournament-import'); ?></h2>
                            <?php if (!empty($series_tournaments)) : ?>
                                <div class="series-tournaments-grid">
                                    <?php foreach ($series_tournaments as $tournament) : ?>
                                        <?php
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

                                        <div class="series-tournament-card">
                                            <div class="tournament-card-header">
                                                <h3 class="tournament-card-title">
                                                    <a href="<?php echo get_permalink($tournament->ID); ?>"><?php echo esc_html($tournament->post_title); ?></a>
                                                </h3>
                                                <div class="tournament-date">
                                                    <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date('M j, Y', $tournament->ID)); ?>
                                                </div>
                                            </div>

                                            <div class="tournament-card-stats">
                                                <div class="stat-group">
                                                    <div class="stat-item">
                                                        <span class="stat-label"><?php _e('Players', 'poker-tournament-import'); ?></span>
                                                        <span class="stat-value"><?php echo esc_html($players_count ?: '--'); ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-label"><?php _e('Prize Pool', 'poker-tournament-import'); ?></span>
                                                        <span class="stat-value"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></span>
                                                    </div>
                                                </div>

                                                <?php if ($winner_name) : ?>
                                                    <div class="tournament-winner">
                                                        <span class="winner-label"><?php _e('Winner:', 'poker-tournament-import'); ?></span>
                                                        <span class="winner-name"><?php echo esc_html($winner_name); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="tournament-card-footer">
                                                <a href="<?php echo get_permalink($tournament->ID); ?>" class="btn btn-primary">
                                                    <?php _e('View Results', 'poker-tournament-import'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="no-tournaments">
                                    <p><?php _e('No tournaments have been added to this series yet.', 'poker-tournament-import'); ?></p>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <!-- Sidebar -->
                    <aside class="series-sidebar">
                        <!-- Top Performers -->
                        <?php if (!empty($leaderboard)) : ?>
                        <div class="sidebar-widget top-performers-widget">
                            <h3><?php _e('Top Performers', 'poker-tournament-import'); ?></h3>
                            <div class="top-performers-list">
                                <?php
                                $top_performers = array_slice($leaderboard, 0, 5);
                                foreach ($top_performers as $index => $player) :
                                    $player_post = $wpdb->get_row($wpdb->prepare(
                                        "SELECT p.ID, p.post_title
                                         FROM {$wpdb->postmeta} pm
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         WHERE pm.meta_key = '_player_uuid' AND pm.meta_value = %s
                                         LIMIT 1",
                                        $player->player_id
                                    ));
                                ?>
                                    <div class="performer-item">
                                        <div class="performer-rank">
                                            <?php if ($index < 3) : ?>
                                                <span class="rank-medal"><?php echo ($index === 0) ? '🥇' : (($index === 1) ? '🥈' : '🥉'); ?></span>
                                            <?php else : ?>
                                                <span class="rank-number"><?php echo esc_html($index + 1); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="performer-info">
                                            <?php if ($player_post) : ?>
                                                <a href="<?php echo get_permalink($player_post->ID); ?>" class="performer-name">
                                                    <?php echo esc_html($player_post->post_title); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="performer-name"><?php echo esc_html($player->player_id); ?></span>
                                            <?php endif; ?>
                                            <div class="performer-stats">
                                                <span class="performer-winnings">$<?php echo esc_html(number_format($player->total_winnings, 0)); ?></span>
                                                <span class="performer-points"><?php echo esc_html(number_format($player->total_points, 0)); ?> pts</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Series Statistics -->
                        <div class="sidebar-widget series-stats-widget">
                            <h3><?php _e('Series Statistics', 'poker-tournament-import'); ?></h3>
                            <div class="mini-stats-list">
                                <div class="mini-stat-item">
                                    <span class="mini-stat-label"><?php _e('Avg Players/Tournament', 'poker-tournament-import'); ?></span>
                                    <span class="mini-stat-value"><?php echo esc_html(round($avg_players, 1)); ?></span>
                                </div>
                                <div class="mini-stat-item">
                                    <span class="mini-stat-label"><?php _e('Avg Prize Pool', 'poker-tournament-import'); ?></span>
                                    <span class="mini-stat-value">$<?php echo esc_html(number_format($avg_prize_pool, 0)); ?></span>
                                </div>
                                <div class="mini-stat-item">
                                    <span class="mini-stat-label"><?php _e('Total Payouts', 'poker-tournament-import'); ?></span>
                                    <span class="mini-stat-value">$<?php echo esc_html(number_format($total_prize_pool, 0)); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Champions -->
                        <div class="sidebar-widget recent-champions-widget">
                            <h3><?php _e('Recent Champions', 'poker-tournament-import'); ?></h3>
                            <?php
                            $recent_champions = array();
                            foreach (array_slice($series_tournaments, 0, 5) as $tournament) {
                                $tournament_uuid = get_post_meta($tournament->ID, '_tournament_uuid', true);
                                if ($tournament_uuid) {
                                    $champion = $wpdb->get_row($wpdb->prepare(
                                        "SELECT p.ID as player_id, p.post_title as player_name,
                                                pm.meta_value as tournament_date
                                         FROM $table_name tp
                                         LEFT JOIN {$wpdb->postmeta} pm_t ON pm_t.meta_value = tp.tournament_id AND pm_t.meta_key = '_tournament_uuid'
                                         LEFT JOIN {$wpdb->posts} t ON pm_t.post_id = t.ID
                                         LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = '_player_uuid'
                                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                         WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                         LIMIT 1",
                                        $tournament_uuid
                                    ));
                                    if ($champion) {
                                        $champion->tournament_name = $tournament->post_title;
                                        $champion->tournament_link = get_permalink($tournament->ID);
                                        $recent_champions[] = $champion;
                                    }
                                }
                            }

                            if (!empty($recent_champions)) :
                            ?>
                                <ul class="champions-list">
                                    <?php foreach ($recent_champions as $champion) : ?>
                                        <li class="champion-item">
                                            <div class="champion-avatar"><?php echo substr($champion->player_name, 0, 2); ?></div>
                                            <div class="champion-info">
                                                <a href="<?php echo get_permalink($champion->player_id); ?>" class="champion-name">
                                                    <?php echo esc_html($champion->player_name); ?>
                                                </a>
                                                <div class="champion-tournament">
                                                    <a href="<?php echo $champion->tournament_link; ?>"><?php echo esc_html($champion->tournament_name); ?></a>
                                                </div>
                                            </div>
                                            <div class="champion-trophy">🏆</div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><?php _e('No champions recorded yet.', 'poker-tournament-import'); ?></p>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>

            </article>

        <?php endwhile; ?>
    </main>
</div>

<!-- Series Page Styles -->
<style>
.poker-series-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.series-single-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    padding: 40px;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border-radius: 12px;
}

.series-title {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.series-subtitle {
    font-size: 18px;
    opacity: 0.9;
    margin-bottom: 16px;
}

.series-description-brief {
    font-size: 16px;
    line-height: 1.5;
    opacity: 0.8;
}

.series-actions {
    display: flex;
    gap: 12px;
}

.print-series,
.export-series {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.print-series:hover,
.export-series:hover {
    background: rgba(255, 255, 255, 0.3);
}

.series-stats-overview {
    margin-bottom: 40px;
}

.series-stats-overview h2 {
    font-size: 24px;
    margin-bottom: 24px;
    color: #333;
}

.series-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.series-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.series-main-content section {
    margin-bottom: 40px;
}

.series-main-content h2 {
    font-size: 24px;
    margin-bottom: 20px;
    color: #333;
}

.series-description-content {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 8px;
    line-height: 1.6;
    border-left: 4px solid #4CAF50;
}

.leaderboard-table-wrapper {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table th {
    background: linear-gradient(135deg, #34495e, #2c3e50);
    color: white;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.leaderboard-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
}

.leaderboard-table tbody tr:hover {
    background: #f8f9fa;
}

.leaderboard-table .gold {
    background: linear-gradient(135deg, #fff9c4, #ffeb3b);
}

.leaderboard-table .silver {
    background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
}

.leaderboard-table .bronze {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
}

.rank {
    text-align: center;
    font-weight: 700;
}

.rank-number {
    font-size: 18px;
}

.rank-medal {
    font-size: 24px;
    display: block;
    margin-bottom: 4px;
}

.player-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #333;
    font-weight: 500;
}

.player-link:hover {
    color: #4CAF50;
}

.player-avatar {
    width: 28px;
    height: 28px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.winnings {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #27ae60;
}

.points {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #3498db;
}

.best-finish {
    font-weight: 600;
    color: #e74c3c;
}

.series-tournaments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.series-tournament-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    transition: all 0.2s ease;
}

.series-tournament-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.tournament-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.tournament-card-title {
    margin: 0;
    font-size: 18px;
    line-height: 1.3;
}

.tournament-card-title a {
    color: #333;
    text-decoration: none;
}

.tournament-card-title a:hover {
    color: #4CAF50;
}

.tournament-date {
    color: #666;
    font-size: 14px;
    white-space: nowrap;
}

.tournament-card-stats {
    margin-bottom: 16px;
}

.stat-group {
    display: flex;
    gap: 20px;
    margin-bottom: 12px;
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.tournament-winner {
    background: #e8f5e8;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
}

.winner-label {
    color: #666;
    margin-right: 4px;
}

.winner-name {
    font-weight: 600;
    color: #333;
}

.tournament-card-footer {
    text-align: center;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: background 0.2s ease;
}

.btn:hover {
    background: #45a049;
}

.sidebar-widget {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.sidebar-widget h3 {
    font-size: 18px;
    margin-top: 0;
    margin-bottom: 16px;
    color: #333;
}

.top-performers-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.performer-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: white;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.performer-item:hover {
    transform: translateX(4px);
}

.performer-rank {
    font-weight: 700;
    font-size: 16px;
    width: 32px;
    text-align: center;
}

.performer-info {
    flex: 1;
}

.performer-name {
    display: block;
    font-weight: 500;
    color: #333;
    text-decoration: none;
    margin-bottom: 4px;
}

.performer-name:hover {
    color: #4CAF50;
}

.performer-stats {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #666;
}

.performer-winnings {
    color: #27ae60;
    font-weight: 600;
}

.performer-points {
    color: #3498db;
}

.mini-stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mini-stat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.mini-stat-label {
    color: #666;
    font-size: 14px;
}

.mini-stat-value {
    font-weight: 600;
    color: #333;
}

.champions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.champion-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
}

.champion-item:last-child {
    border-bottom: none;
}

.champion-avatar {
    width: 32px;
    height: 32px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.champion-info {
    flex: 1;
}

.champion-name {
    display: block;
    font-weight: 500;
    color: #333;
    text-decoration: none;
    margin-bottom: 4px;
}

.champion-name:hover {
    color: #4CAF50;
}

.champion-tournament {
    font-size: 12px;
    color: #666;
}

.champion-tournament a {
    color: #666;
    text-decoration: none;
}

.champion-tournament a:hover {
    color: #4CAF50;
}

.champion-trophy {
    font-size: 20px;
}

.no-tournaments {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #e0e0e0;
}

@media (max-width: 768px) {
    .series-content-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .series-single-header {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }

    .series-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .series-tournaments-grid {
        grid-template-columns: 1fr;
    }

    .tournament-card-header {
        flex-direction: column;
        gap: 8px;
    }

    .stat-group {
        flex-direction: column;
        gap: 12px;
    }
}
</style>

<?php
get_footer();