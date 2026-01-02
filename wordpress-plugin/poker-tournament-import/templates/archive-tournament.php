<?php
/**
 * Tournament Archive Template
 *
 * This template displays a list of tournaments with filtering,
 * search, and grid/list view options.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="poker-tournament-archive-wrapper">
    <main id="primary" class="site-main">
        <header class="archive-header">
            <div class="archive-title-section">
                <?php the_archive_title('<h1 class="archive-title">', '</h1>'); ?>
                <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
            </div>

            <!-- View Toggle and Filters -->
            <div class="archive-controls">
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid">
                        <i class="icon-grid"></i> <?php esc_html_e('Grid', 'poker-tournament-import'); ?>
                    </button>
                    <button class="view-btn" data-view="list">
                        <i class="icon-list"></i> <?php esc_html_e('List', 'poker-tournament-import'); ?>
                    </button>
                </div>

                <div class="archive-filters">
                    <?php
                    // Series filter
                    $series_options = get_posts(array(
                        'post_type' => 'tournament_series',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));

                    if ($series_options) {
                        echo '<select id="series-filter" class="filter-select">';
                        echo '<option value="">' . esc_html__('All Series', 'poker-tournament-import') . '</option>';
                        foreach ($series_options as $series) {
                            echo '<option value="' . esc_attr($series->ID) . '">' . esc_html($series->post_title) . '</option>';
                        }
                        echo '</select>';
                    }
                    ?>

                    <select id="date-filter" class="filter-select">
                        <option value=""><?php esc_html_e('All Dates', 'poker-tournament-import'); ?></option>
                        <option value="30"><?php esc_html_e('Last 30 Days', 'poker-tournament-import'); ?></option>
                        <option value="90"><?php esc_html_e('Last 90 Days', 'poker-tournament-import'); ?></option>
                        <option value="365"><?php esc_html_e('Last Year', 'poker-tournament-import'); ?></option>
                    </select>

                    <select id="sort-filter" class="filter-select">
                        <option value="date_desc"><?php esc_html_e('Latest First', 'poker-tournament-import'); ?></option>
                        <option value="date_asc"><?php esc_html_e('Oldest First', 'poker-tournament-import'); ?></option>
                        <option value="title_asc"><?php esc_html_e('Title (A-Z)', 'poker-tournament-import'); ?></option>
                        <option value="title_desc"><?php esc_html_e('Title (Z-A)', 'poker-tournament-import'); ?></option>
                        <option value="players_desc"><?php esc_html_e('Most Players', 'poker-tournament-import'); ?></option>
                        <option value="prize_desc"><?php esc_html_e('Largest Prize Pool', 'poker-tournament-import'); ?></option>
                    </select>
                </div>

                <div class="archive-search">
                    <input type="search" id="tournament-search" placeholder="<?php esc_html_e('Search tournaments...', 'poker-tournament-import'); ?>">
                    <button type="button" id="search-btn">
                        <i class="icon-search"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Statistics Overview -->
        <section class="archive-stats-overview">
            <div class="stats-grid">
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'poker_tournament_players';
                $total_tournaments = wp_count_posts('tournament')->publish;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $total_players = $wpdb->get_var("SELECT COUNT(DISTINCT player_id) FROM $table_name");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                $total_prize_pool = $wpdb->get_var("SELECT SUM(winnings) FROM $table_name WHERE winnings > 0");
                ?>

                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($total_tournaments); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Tournaments', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($total_players ?: 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Unique Players', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo esc_html(number_format($total_prize_pool ?: 0, 0)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Prize Money', 'poker-tournament-import'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($total_tournaments > 0 ? number_format($total_prize_pool / $total_tournaments, 0) : 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Average Prize Pool', 'poker-tournament-import'); ?></div>
                </div>
            </div>
        </section>

        <!-- Tournaments Grid/List -->
        <section class="tournaments-archive-content">
            <?php if (have_posts()) : ?>

                <div class="tournaments-container grid-view">
                    <?php while (have_posts()) : the_post(); ?>

                        <?php
                        // Get tournament data
                        $tournament_uuid = get_post_meta(get_the_ID(), 'tournament_uuid', true) ?:
                                          get_post_meta(get_the_ID(), '_tournament_uuid', true);
                        $players_count = get_post_meta(get_the_ID(), '_players_count', true);
                        $prize_pool = get_post_meta(get_the_ID(), '_prize_pool', true);
                        $buy_in = get_post_meta(get_the_ID(), '_buy_in', true);
                        $tournament_date = get_post_meta(get_the_ID(), '_tournament_date', true);
                        $currency = get_post_meta(get_the_ID(), '_currency', true) ?: '$';
                        $series_id = get_post_meta(get_the_ID(), '_series_id', true);
                        $series_name = $series_id ? get_the_title($series_id) : '';

                        // Get winner info
                        $winner_name = '';
                        $winner_avatar = '';
                        if ($tournament_uuid) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
                            $winner = $wpdb->get_row($wpdb->prepare(
                                "SELECT tp.player_id, p.post_title as player_name
                                 FROM $table_name tp
                                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                 WHERE tp.tournament_id = %s AND tp.finish_position = 1
                                 LIMIT 1",
                                $tournament_uuid
                            ));

                            if ($winner) {
                                $winner_name = $winner->player_name;
                                $winner_avatar = substr($winner_name, 0, 2);
                            }
                        }
                        ?>

                        <?php
                        // Quick View: Get top 5 players and stats for this tournament
                        $qv_top_players = array();
                        $qv_stats = (object) array('paid' => 0, 'avg_cash' => 0, 'first_prize' => 0);
                        if ($tournament_uuid) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $qv_top_players = $wpdb->get_results($wpdb->prepare(
                                "SELECT tp.finish_position, tp.winnings, tp.player_id, p.post_title as player_name
                                 FROM $table_name tp
                                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                 WHERE tp.tournament_id = %s AND tp.winnings > 0
                                 ORDER BY tp.finish_position ASC
                                 LIMIT 5",
                                $tournament_uuid
                            ));
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $qv_stats = $wpdb->get_row($wpdb->prepare(
                                "SELECT COUNT(*) as paid, AVG(winnings) as avg_cash, MAX(winnings) as first_prize
                                 FROM $table_name
                                 WHERE tournament_id = %s AND winnings > 0",
                                $tournament_uuid
                            ));
                        }
                        ?>

                        <!-- Grid View Card -->
                        <div class="tournament-card grid-view-item" data-series-id="<?php echo esc_attr($series_id); ?>" data-date="<?php echo esc_attr($tournament_date); ?>" data-players="<?php echo esc_attr($players_count); ?>" data-prize="<?php echo esc_attr($prize_pool); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>">
                            <div class="tournament-card-header">
                                <h3 class="tournament-card-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>

                                <div class="tournament-card-meta">
                                    <div class="meta-item">
                                        <span class="meta-icon">📅</span>
                                        <span class="meta-value"><?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date()); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon">👥</span>
                                        <span class="meta-value"><?php echo esc_html($players_count ?: '--'); ?> <?php esc_html_e('players', 'poker-tournament-import'); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon">💰</span>
                                        <span class="meta-value"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon">🎫</span>
                                        <span class="meta-value"><?php echo esc_html($currency . $buy_in); ?></span>
                                    </div>
                                </div>

                                <?php if (get_the_excerpt()) : ?>
                                    <div class="tournament-card-excerpt">
                                        <?php the_excerpt(); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tournament-card-footer">
                                <a href="<?php the_permalink(); ?>" class="btn btn-primary">
                                    <?php esc_html_e('View Results', 'poker-tournament-import'); ?>
                                </a>
                                <label for="quick-view-toggle" class="btn btn-secondary quick-view-trigger" data-tournament-id="<?php the_ID(); ?>">
                                    <?php esc_html_e('Quick View', 'poker-tournament-import'); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Hidden Quick View Data -->
                        <div class="quick-view-data" id="qv-data-<?php the_ID(); ?>" style="display: none;">
                            <div class="qv-title"><?php the_title(); ?></div>
                            <div class="qv-date"><?php echo esc_html($tournament_date ? date_i18n('M j, Y', strtotime($tournament_date)) : '--'); ?></div>
                            <div class="qv-buyin"><?php echo esc_html($currency . ($buy_in ?? 0)); ?></div>
                            <div class="qv-players"><?php echo esc_html($players_count ?? '--'); ?></div>
                            <div class="qv-prize"><?php echo esc_html($currency . number_format($prize_pool ?? 0, 0)); ?></div>
                            <div class="qv-winner"><?php echo esc_html($winner_name ?: '--'); ?></div>
                            <div class="qv-paid"><?php echo esc_html($qv_stats->paid ?? 0); ?></div>
                            <div class="qv-cash-rate"><?php echo esc_html($players_count > 0 ? round(($qv_stats->paid ?? 0) / $players_count * 100, 1) : 0); ?>%</div>
                            <div class="qv-first"><?php echo esc_html($currency . number_format($qv_stats->first_prize ?? 0, 0)); ?></div>
                            <div class="qv-avg"><?php echo esc_html($currency . number_format($qv_stats->avg_cash ?? 0, 0)); ?></div>
                            <div class="qv-link"><?php echo esc_url(get_permalink()); ?></div>
                            <div class="qv-players-table">
                                <?php if ($qv_top_players) : ?>
                                    <table>
                                        <?php foreach ($qv_top_players as $p) : ?>
                                            <tr data-pos="<?php echo esc_attr($p->finish_position); ?>"
                                                data-name="<?php echo esc_attr($p->player_name ?: $p->player_id); ?>"
                                                data-winnings="<?php echo esc_attr($currency . number_format($p->winnings, 0)); ?>"></tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- List View Item -->
                        <div class="tournament-list-item list-view-item" style="display: none;" data-series-id="<?php echo esc_attr($series_id); ?>" data-date="<?php echo esc_attr($tournament_date); ?>" data-players="<?php echo esc_attr($players_count); ?>" data-prize="<?php echo esc_attr($prize_pool); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>">
                            <div class="list-item-content">
                                <div class="list-item-main">
                                    <h3 class="list-item-title">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <div class="list-item-meta">
                                        <span class="list-meta-item">
                                            <i class="icon-calendar"></i>
                                            <?php echo $tournament_date ? esc_html(date_i18n('M j, Y', strtotime($tournament_date))) : esc_html(get_the_date()); ?>
                                        </span>
                                        <?php if ($series_name) : ?>
                                            <span class="list-meta-item">
                                                <i class="icon-tag"></i>
                                                <a href="<?php echo esc_url(get_permalink($series_id)); ?>"><?php echo esc_html($series_name); ?></a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($winner_name) : ?>
                                            <span class="list-meta-item">
                                                <i class="icon-trophy"></i>
                                                <?php echo esc_html($winner_name); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="list-item-stats">
                                    <div class="stat-badge">
                                        <span class="stat-value"><?php echo esc_html($players_count ?: '--'); ?></span>
                                        <span class="stat-label"><?php esc_html_e('Players', 'poker-tournament-import'); ?></span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="stat-value"><?php echo esc_html($currency . number_format($prize_pool ?: 0, 0)); ?></span>
                                        <span class="stat-label"><?php esc_html_e('Prize Pool', 'poker-tournament-import'); ?></span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="stat-value"><?php echo esc_html($currency . $buy_in); ?></span>
                                        <span class="stat-label"><?php esc_html_e('Buy-in', 'poker-tournament-import'); ?></span>
                                    </div>
                                </div>

                                <div class="list-item-actions">
                                    <a href="<?php the_permalink(); ?>" class="btn btn-sm btn-primary">
                                        <?php esc_html_e('View Results', 'poker-tournament-import'); ?>
                                    </a>
                                    <label for="quick-view-toggle" class="btn btn-sm btn-secondary quick-view-trigger" data-tournament-id="<?php the_ID(); ?>">
                                        <?php esc_html_e('Quick View', 'poker-tournament-import'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <div class="archive-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size'  => 2,
                        'prev_text' => __('&laquo; Previous', 'poker-tournament-import'),
                        'next_text' => __('Next &raquo;', 'poker-tournament-import'),
                    ));
                    ?>
                </div>

            <?php else : ?>

                <div class="no-tournaments-found">
                    <div class="no-results-icon">🎰</div>
                    <h2><?php esc_html_e('No Tournaments Found', 'poker-tournament-import'); ?></h2>
                    <p><?php esc_html_e('There are no tournaments to display at this time.', 'poker-tournament-import'); ?></p>
                    <?php if (current_user_can('manage_options')) : ?>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=poker-tournament-import')); ?>" class="btn btn-primary">
                                <?php esc_html_e('Import Your First Tournament', 'poker-tournament-import'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </section>

        <!-- Quick View Modal -->
        <input type="checkbox" id="quick-view-toggle" class="quick-view-checkbox">
        <div id="quick-view-modal" class="quick-view-modal">
            <label for="quick-view-toggle" class="quick-view-overlay"></label>
            <div class="quick-view-content">
                <div class="quick-view-header">
                    <h3 id="qv-modal-title"><?php esc_html_e('Tournament Quick View', 'poker-tournament-import'); ?></h3>
                    <label for="quick-view-toggle" class="quick-view-close">&times;</label>
                </div>
                <div class="quick-view-body" id="qv-modal-body">
                    <!-- Content injected via minimal JS -->
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Additional Styles for Archive -->
<style>
.tournaments-archive-content {
    margin: 40px 0;
}

.tournaments-container {
    display: grid;
    gap: 24px;
}

.grid-view {
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
}

.list-view {
    grid-template-columns: 1fr;
}

.tournament-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.tournament-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.tournament-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.tournament-winner {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #fff9c4, #fff3cd);
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #f0c674;
}

.winner-avatar {
    width: 32px;
    height: 32px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

.winner-name {
    font-weight: 600;
    color: #333;
}

.winner-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    display: block;
}

.tournament-series-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.tournament-series-badge a {
    color: inherit;
    text-decoration: none;
}

.tournament-card-title {
    margin: 0 0 16px 0;
    font-size: 20px;
    line-height: 1.3;
}

.tournament-card-title a {
    color: #1a1a1a;
    text-decoration: none;
}

.tournament-card-title a:hover {
    color: #4CAF50;
}

.tournament-card-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #666;
}

.meta-icon {
    font-size: 16px;
}

.tournament-card-excerpt {
    font-size: 14px;
    color: #666;
    line-height: 1.5;
    margin-bottom: 20px;
}

.tournament-card-footer {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.tournament-list-item {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    margin-bottom: 16px;
}

.list-item-content {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 20px;
    align-items: center;
}

.list-item-title {
    margin: 0 0 8px 0;
    font-size: 18px;
}

.list-item-title a {
    color: #1a1a1a;
    text-decoration: none;
}

.list-item-title a:hover {
    color: #4CAF50;
}

.list-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
    color: #666;
}

.list-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.list-meta-item a {
    color: #4CAF50;
    text-decoration: none;
}

.list-item-stats {
    display: flex;
    gap: 16px;
}

.stat-badge {
    text-align: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    min-width: 60px;
}

.stat-value {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-top: 2px;
}

.archive-stats-overview {
    margin: 40px 0;
}

.archive-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin: 24px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.view-toggle {
    display: flex;
    gap: 4px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.view-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    transition: all 0.2s ease;
}

.view-btn.active,
.view-btn:hover {
    background: #4CAF50;
    color: white;
}

.archive-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: white;
    font-size: 14px;
}

.archive-search {
    display: flex;
    gap: 8px;
}

.archive-search input {
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    min-width: 200px;
}

.archive-search button {
    padding: 8px 12px;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.no-tournaments-found {
    text-align: center;
    padding: 80px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #e0e0e0;
}

.no-results-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

/* CSS-Only Modal Toggle */
.quick-view-checkbox {
    display: none;
}

.quick-view-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.quick-view-checkbox:checked ~ .quick-view-modal {
    display: block;
}

.quick-view-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.quick-view-content {
    position: relative;
    max-width: 600px;
    margin: 50px auto;
    background: white;
    border-radius: 8px;
    max-height: 80vh;
    overflow-y: auto;
}

.quick-view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.quick-view-close {
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
}

.quick-view-body {
    padding: 20px;
}

.qv-summary h4,
.qv-stats h4,
.qv-players h4 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 6px;
}

.qv-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.qv-meta-grid > div {
    display: flex;
    justify-content: space-between;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.qv-meta-grid > div.winner {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #fff9c4, #fff3cd);
}

.qv-meta-grid .label {
    font-weight: 500;
    color: #666;
}

.mini-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.mini-stats-grid > div {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
}

.mini-stats-grid > div span:first-child {
    font-weight: 700;
    font-size: 18px;
}

.mini-stats-grid > div span:last-child {
    font-size: 12px;
    color: #666;
}

.qv-table {
    width: 100%;
    border-collapse: collapse;
}

.qv-table th,
.qv-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.qv-table th {
    background: #f8f9fa;
}

.qv-footer {
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

@media (max-width: 600px) {
    .quick-view-content {
        margin: 20px;
        max-height: 85vh;
    }
    .qv-meta-grid {
        grid-template-columns: 1fr;
    }
    .mini-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .archive-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .archive-filters {
        justify-content: center;
    }

    .archive-search input {
        min-width: auto;
        flex: 1;
    }

    .grid-view {
        grid-template-columns: 1fr;
    }

    .list-item-content {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .list-item-stats {
        order: -1;
        justify-content: space-around;
    }

    .tournament-card-footer {
        flex-direction: column;
    }

    .btn {
        text-align: center;
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View toggle functionality
    $('.view-btn').on('click', function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');

        const view = $(this).data('view');
        const container = $('.tournaments-container');

        if (view === 'list') {
            container.removeClass('grid-view').addClass('list-view');
            $('.grid-view-item').hide();
            $('.list-view-item').show();
        } else {
            container.removeClass('list-view').addClass('grid-view');
            $('.list-view-item').hide();
            $('.grid-view-item').show();
        }
    });

    // Filter functionality
    function filterTournaments() {
        const seriesFilter = $('#series-filter').val();
        const dateFilter = $('#date-filter').val();
        const searchTerm = $('#tournament-search').val().toLowerCase();

        $('.tournament-card, .tournament-list-item').each(function() {
            const item = $(this);
            let show = true;

            // Series filter
            if (seriesFilter && item.data('series-id') !== seriesFilter) {
                show = false;
            }

            // Date filter
            if (dateFilter && item.data('date')) {
                const itemDate = new Date(item.data('date'));
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - parseInt(dateFilter));
                if (itemDate < cutoffDate) {
                    show = false;
                }
            }

            // Search filter
            if (searchTerm) {
                const title = item.data('title').toLowerCase();
                if (!title.includes(searchTerm)) {
                    show = false;
                }
            }

            item.toggle(show);
        });
    }

    $('#series-filter, #date-filter, #tournament-search').on('change keyup', filterTournaments);
    $('#search-btn').on('click', filterTournaments);

    // Sort functionality
    $('#sort-filter').on('change', function() {
        const sort = $(this).val();
        const container = $('.tournaments-container');
        const items = container.children('.grid-view-item, .list-view-item');

        items.sort(function(a, b) {
            const $a = $(a);
            const $b = $(b);

            switch (sort) {
                case 'date_asc':
                    return new Date($a.data('date') || 0) - new Date($b.data('date') || 0);
                case 'date_desc':
                    return new Date($b.data('date') || 0) - new Date($a.data('date') || 0);
                case 'title_asc':
                    return $a.data('title').localeCompare($b.data('title'));
                case 'title_desc':
                    return $b.data('title').localeCompare($a.data('title'));
                case 'players_desc':
                    return parseInt($b.data('players') || 0) - parseInt($a.data('players') || 0);
                case 'prize_desc':
                    return parseFloat($b.data('prize') || 0) - parseFloat($a.data('prize') || 0);
                default:
                    return 0;
            }
        });

        container.empty().append(items);
    });

    // Quick view - CSS checkbox hack + data copy
    $('.quick-view-trigger').on('click', function() {
        const tournamentId = $(this).data('tournament-id');
        const source = $('#qv-data-' + tournamentId);
        const modalTitle = $('#qv-modal-title');
        const modalBody = $('#qv-modal-body');

        // Debug logging
        console.log('[Quick View] Tournament ID:', tournamentId);
        console.log('[Quick View] Source element found:', source.length);

        // Check if source exists
        if (source.length === 0) {
            console.error('[Quick View] Source element not found for ID:', tournamentId);
            modalBody.html('<div class="qv-error"><p>Error: Tournament data not found.</p></div>');
            return;
        }

        // Check if source has content
        const titleText = source.find('.qv-title').text();
        console.log('[Quick View] Title:', titleText);
        if (!titleText) {
            console.error('[Quick View] Source element has no title content');
            modalBody.html('<div class="qv-error"><p>Error: Tournament data is empty.</p></div>');
            return;
        }

        // Copy data from hidden div to modal
        modalTitle.text(titleText);

        const playersTable = source.find('.qv-players-table table');
        console.log('[Quick View] Players table found:', playersTable.length);
        let tableHtml = '';
        if (playersTable && playersTable.length > 0) {
            playersTable.find('tr').each(function() {
                const row = $(this);
                tableHtml += '<tr><td>' + row.data('pos') + '</td><td>' + row.data('name') +
                            '</td><td>' + row.data('winnings') + '</td></tr>';
            });
        }

        // Build modal content
        const bodyContent = `
            <div class="qv-summary">
                <h4><?php esc_html_e('Tournament Summary', 'poker-tournament-import'); ?></h4>
                <div class="qv-meta-grid">
                    <div><span class="label"><?php esc_html_e('Date'); ?></span><span>${source.find('.qv-date').text()}</span></div>
                    <div><span class="label"><?php esc_html_e('Buy-in'); ?></span><span>${source.find('.qv-buyin').text()}</span></div>
                    <div><span class="label"><?php esc_html_e('Players'); ?></span><span>${source.find('.qv-players').text()}</span></div>
                    <div><span class="label"><?php esc_html_e('Prize Pool'); ?></span><span>${source.find('.qv-prize').text()}</span></div>
                    <div class="winner"><span class="label"><?php esc_html_e('Winner'); ?></span><span>${source.find('.qv-winner').text()}</span></div>
                </div>
            </div>
            <div class="qv-stats">
                <h4><?php esc_html_e('Statistics', 'poker-tournament-import'); ?></h4>
                <div class="mini-stats-grid">
                    <div><span>${source.find('.qv-paid').text()}</span><span><?php esc_html_e('Paid'); ?></span></div>
                    <div><span>${source.find('.qv-cash-rate').text()}</span><span><?php esc_html_e('Cash Rate'); ?></span></div>
                    <div><span>${source.find('.qv-first').text()}</span><span><?php esc_html_e('1st Prize'); ?></span></div>
                    <div><span>${source.find('.qv-avg').text()}</span><span><?php esc_html_e('Avg Cash'); ?></span></div>
                </div>
            </div>
            ${tableHtml ? `
            <div class="qv-players">
                <h4><?php esc_html_e('Top Players', 'poker-tournament-import'); ?></h4>
                <table class="qv-table">
                    <thead><tr><th><?php esc_html_e('Pos'); ?></th><th><?php esc_html_e('Player'); ?></th><th><?php esc_html_e('Winnings'); ?></th></tr></thead>
                    <tbody>${tableHtml}</tbody>
                </table>
            </div>` : ''}
            <div class="qv-footer">
                <a href="${source.find('.qv-link').text()}" class="btn btn-primary">
                    <?php esc_html_e('View Full Results', 'poker-tournament-import'); ?>
                </a>
            </div>
        `;

        console.log('[Quick View] Body content length:', bodyContent.length);
        modalBody.html(bodyContent);
        console.log('[Quick View] Modal body HTML set, length:', modalBody.html().length);
    });
});
</script>

<?php
get_footer();