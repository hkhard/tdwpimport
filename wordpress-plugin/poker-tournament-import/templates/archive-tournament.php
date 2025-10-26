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
                        $tournament_uuid = get_post_meta(get_the_ID(), 'tournament_uuid', true);
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

                        <!-- Grid View Card -->
                        <div class="tournament-card grid-view-item" data-series-id="<?php echo esc_attr($series_id); ?>" data-date="<?php echo esc_attr($tournament_date); ?>" data-players="<?php echo esc_attr($players_count); ?>" data-prize="<?php echo esc_attr($prize_pool); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>">
                            <div class="tournament-card-header">
                                <?php if ($winner_name) : ?>
                                    <div class="tournament-winner">
                                        <div class="winner-avatar"><?php echo esc_html($winner_avatar); ?></div>
                                        <div class="winner-info">
                                            <span class="winner-label"><?php esc_html_e('Winner:', 'poker-tournament-import'); ?></span>
                                            <span class="winner-name"><?php echo esc_html($winner_name); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($series_name) : ?>
                                    <div class="tournament-series-badge">
                                        <a href="<?php echo esc_url(get_permalink($series_id)); ?>"><?php echo esc_html($series_name); ?></a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tournament-card-content">
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
                                <button class="btn btn-secondary quick-view" data-tournament-id="<?php the_ID(); ?>">
                                    <?php esc_html_e('Quick View', 'poker-tournament-import'); ?>
                                </button>
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
        <div id="quick-view-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php esc_html_e('Tournament Quick View', 'poker-tournament-import'); ?></h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Content will be loaded dynamically -->
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

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background: white;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 600px;
    border-radius: 12px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 20px;
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
    $('#search-btn').on('click', filterTournament);

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

    // Quick view modal
    $('.quick-view').on('click', function() {
        const tournamentId = $(this).data('tournament-id');
        const modal = $('#quick-view-modal');
        const modalBody = modal.find('.modal-body');

        // Load tournament content via AJAX
        modalBody.html('<div class="loading">Loading...</div>');
        modal.show();

        $.ajax({
            url: ajaxurl,
            data: {
                action: 'poker_tournament_quick_view',
                tournament_id: tournamentId
            },
            success: function(response) {
                modalBody.html(response);
            }
        });
    });

    $('.modal-close').on('click', function() {
        $('#quick-view-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('.modal')) {
            $('.modal').hide();
        }
    });
});
</script>

<?php
get_footer();