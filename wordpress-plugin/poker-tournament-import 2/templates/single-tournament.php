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

/**
 * CRITICAL FIX: Get tournament winner information using chronological processing
 * This function tries to get real-time winner data using the new GameHistory processing
 * before falling back to stored data (which may be incorrect)
 */
function get_tournament_winner_info($tournament_id) {
    // Try to get real-time tournament results first
    $realtime_results = get_realtime_tournament_results($tournament_id);
    if ($realtime_results && !empty($realtime_results['players'])) {
        // Find the winner (position 1) from real-time data
        foreach ($realtime_results['players'] as $uuid => $player) {
            if (isset($player['finish_position']) && $player['finish_position'] === 1) {
                return array(
                    'name' => $player['nickname'] ?? 'Unknown',
                    'uuid' => $uuid,
                    'finish_position' => 1,
                    'winnings' => $player['winnings'] ?? 0,
                    'processing_type' => 'chronological',
                    'source' => $player['winner_source'] ?? 'chronological_game_history',
                    'points' => $player['points'] ?? 0
                );
            }
        }
    }

    // Fallback to stored database data
    global $wpdb;
    $tournament_uuid = get_post_meta($tournament_id, 'tournament_uuid', true) ?:
                      get_post_meta($tournament_id, '_tournament_uuid', true);

    if ($tournament_uuid) {
        $table_name = $wpdb->prefix . 'poker_tournament_players';
        $winner = $wpdb->get_row($wpdb->prepare(
            "SELECT player_id, winnings, points FROM $table_name
             WHERE tournament_id = %s AND finish_position = 1
             LIMIT 1",
            $tournament_uuid
        ));

        if ($winner) {
            // Try to get player name
            $player_name = 'Unknown Player';
            $player_posts = get_posts(array(
                'post_type' => 'player',
                'meta_key' => 'player_uuid',
                'meta_value' => $winner->player_id,
                'numberposts' => 1
            ));

            if (!empty($player_posts)) {
                $player_name = $player_posts[0]->post_title;
            }

            return array(
                'name' => $player_name,
                'uuid' => $winner->player_id,
                'finish_position' => 1,
                'winnings' => $winner->winnings ?? 0,
                'processing_type' => 'stored',
                'source' => 'stored_database',
                'points' => $winner->points ?? 0
            );
        }
    }

    return null;
}

/**
 * Get real-time tournament results using chronological processing
 * This function replicates the logic from the shortcode class
 */
function get_realtime_tournament_results($tournament_id) {
    // CRITICAL FIX: Get raw TDT content for real-time chronological processing
    $raw_content = get_post_meta($tournament_id, '_tournament_raw_content', true);

    if (!$raw_content) {
        return null;
    }

    try {
        // Initialize parser and use modern AST-based parsing
        $parser = new Poker_Tournament_Parser();

        // Use public parse_content() method - handles everything internally including financial data
        $parsed_data = $parser->parse_content($raw_content);

        if ($parsed_data && !empty($parsed_data['players'])) {
            return array(
                'players' => $parsed_data['players'],
                'game_history' => $parsed_data['game_history'] ?? null,
                'metadata' => $parsed_data['metadata'] ?? extract_basic_metadata($raw_content)
            );
        }
    } catch (Exception $e) {
        // Log error but don't break the template
        error_log('Real-time tournament processing failed: ' . $e->getMessage());
    }

    return null;
}

/**
 * Extract basic metadata from tournament data
 */
function extract_basic_metadata($tournament_data) {
    $metadata = array();

    if (preg_match('/UUID:\s*"([^"]+)"/', $tournament_data, $matches)) {
        $metadata['uuid'] = $matches[1];
    }

    if (preg_match('/Title:\s*"([^"]+)"/', $tournament_data, $matches)) {
        $metadata['title'] = $matches[1];
    }

    if (preg_match('/StartTime:\s*(\d+)/', $tournament_data, $matches)) {
        $metadata['start_time'] = date('Y-m-d H:i:s', intval($matches[1] / 1000));
    }

    return $metadata;
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
                        // **CRITICAL FIX**: Enhanced data retrieval with fallbacks
                        $tournament_uuid = get_post_meta(get_the_ID(), 'tournament_uuid', true) ?:
                                        get_post_meta(get_the_ID(), '_tournament_uuid', true);
                        $players_count = get_post_meta(get_the_ID(), '_players_count', true) ?: 0;
                        $prize_pool = get_post_meta(get_the_ID(), '_prize_pool', true) ?: 0;
                        $buy_in = get_post_meta(get_the_ID(), '_buy_in', true) ?:
                                 get_post_meta(get_the_ID(), 'tournament_data', true)['metadata']['buy_in'] ?? 200;
                        $tournament_date = get_post_meta(get_the_ID(), '_tournament_date', true) ?:
                                          get_post_meta(get_the_ID(), 'tournament_date', true);
                        $currency = get_post_meta(get_the_ID(), '_currency', true) ?: '$';

                        // **CRITICAL FIX**: Additional fallback data
                        $game_type = get_post_meta(get_the_ID(), '_game_type', true) ?: 'Texas Hold\'em';
                        $tournament_structure = get_post_meta(get_the_ID(), '_tournament_structure', true) ?: 'No Limit';
                        $points_summary = get_post_meta(get_the_ID(), '_points_summary', true);
                        $tournament_stats = get_post_meta(get_the_ID(), '_tournament_stats', true);
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

                <!-- CRITICAL FIX: Winner Information Section -->
                <section class="tournament-winner-section">
                    <?php
                    // Try to get real-time winner information
                    $winner_info = get_tournament_winner_info(get_the_ID());
                    if ($winner_info):
                    ?>
                        <div class="winner-highlight <?php echo esc_attr($winner_info['processing_type']); ?>">
                            <div class="winner-trophy">🏆</div>
                            <div class="winner-details">
                                <h2><?php _e('Tournament Champion', 'poker-tournament-import'); ?></h2>
                                <div class="winner-name"><?php echo esc_html($winner_info['name']); ?></div>
                                <?php if (isset($winner_info['winnings']) && $winner_info['winnings'] > 0): ?>
                                    <div class="winner-prize"><?php echo esc_html($currency . number_format($winner_info['winnings'], 0)); ?></div>
                                <?php endif; ?>
                                <div class="winner-source">
                                    <?php if ($winner_info['processing_type'] === 'chronological'): ?>
                                        <span class="chronological-badge">⚡ <?php _e('Verified by GameHistory Chronological Processing', 'poker-tournament-import'); ?></span>
                                    <?php else: ?>
                                        <span class="legacy-badge">📊 <?php _e('Based on Stored Tournament Data', 'poker-tournament-import'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <style>
                /* CRITICAL FIX: Tournament Winner Section Styling */
                .tournament-winner-section {
                    margin: 30px 0;
                }

                .winner-highlight {
                    background: linear-gradient(135deg, #fff9e6 0%, #ffedcc 100%);
                    border: 2px solid #d4a574;
                    border-radius: 12px;
                    padding: 30px;
                    text-align: center;
                    box-shadow: 0 4px 20px rgba(212, 165, 116, 0.3);
                    position: relative;
                    overflow: hidden;
                }

                .winner-highlight.chronological {
                    background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
                    border-color: #2271b1;
                    box-shadow: 0 4px 20px rgba(34, 113, 177, 0.3);
                }

                .winner-highlight.stored {
                    background: linear-gradient(135deg, #fef7f7 0%, #fce8e8 100%);
                    border-color: #d63638;
                    box-shadow: 0 4px 20px rgba(214, 54, 56, 0.3);
                }

                .winner-trophy {
                    font-size: 48px;
                    margin-bottom: 15px;
                    animation: pulse 2s infinite;
                }

                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }

                .winner-details h2 {
                    margin: 0 0 10px 0;
                    color: #2c3e50;
                    font-size: 28px;
                    font-weight: 700;
                }

                .winner-name {
                    font-size: 32px;
                    font-weight: 800;
                    color: #1a1a1a;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                .winner-prize {
                    font-size: 24px;
                    font-weight: 600;
                    color: #27ae60;
                    margin-bottom: 15px;
                }

                .winner-source {
                    margin-top: 10px;
                }

                .chronological-badge {
                    background: #2271b1;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 500;
                    display: inline-block;
                }

                .legacy-badge {
                    background: #d63638;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 500;
                    display: inline-block;
                }

                .processing-notice {
                    background: #e7f3ff;
                    border: 1px solid #2271b1;
                    border-radius: 4px;
                    padding: 10px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }

                .processing-notice strong {
                    color: #2271b1;
                }

                /* Mobile Responsive */
                @media (max-width: 768px) {
                    .winner-highlight {
                        padding: 20px;
                    }

                    .winner-trophy {
                        font-size: 36px;
                    }

                    .winner-details h2 {
                        font-size: 24px;
                    }

                    .winner-name {
                        font-size: 24px;
                    }

                    .winner-prize {
                        font-size: 20px;
                    }

                    .chronological-badge,
                    .legacy-badge {
                        font-size: 12px;
                        padding: 6px 12px;
                    }
                }
                </style>

                <!-- Tournament Content -->
                <section class="tournament-content-section">
                    <div class="tournament-content-grid">

                        <!-- Main Content -->
                        <div class="tournament-main-content">
                            <div class="tournament-description">
                                <h2><?php _e('Tournament Details', 'poker-tournament-import'); ?></h2>
                                <div class="tournament-description-content">
                                    <?php if (get_the_content()) : ?>
                                        <?php the_content(); ?>
                                    <?php else: ?>
                                        <!-- **CRITICAL FIX**: Fallback content for tournaments without detailed description -->
                                        <div class="tournament-fallback-content">
                                            <div class="tournament-basic-info">
                                                <p><strong><?php _e('Game Type:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($game_type); ?></p>
                                                <p><strong><?php _e('Structure:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($tournament_structure); ?></p>
                                                <p><strong><?php _e('Date:', 'poker-tournament-import'); ?></strong> <?php echo $tournament_date ? esc_html(date_i18n(get_option('date_format'), strtotime($tournament_date))) : __('Date not specified', 'poker-tournament-import'); ?></p>
                                                <p><strong><?php _e('Players:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($players_count); ?></p>
                                                <p><strong><?php _e('Buy-in:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($currency . number_format($buy_in, 0)); ?></p>
                                                <p><strong><?php _e('Prize Pool:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($currency . number_format($prize_pool, 0)); ?></p>
                                            </div>

                                            <?php if ($points_summary): ?>
                                                <div class="tournament-points-summary">
                                                    <h4><?php _e('Points Summary', 'poker-tournament-import'); ?></h4>
                                                    <p><strong><?php _e('Total Points Awarded:', 'poker-tournament-import'); ?></strong> <?php echo esc_html(number_format($points_summary['total_points_awarded'], 1)); ?></p>
                                                    <p><strong><?php _e('Highest Points:', 'poker-tournament-import'); ?></strong> <?php echo esc_html(number_format($points_summary['max_points'], 1)); ?>
                                                    <?php if ($points_summary['top_point_scorer']): ?>
                                                        (<?php echo esc_html($points_summary['top_point_scorer']['name']); ?> - <?php echo esc_html($points_summary['top_point_scorer']['finish_position']); ?><?php echo get_ordinal_suffix($points_summary['top_point_scorer']['finish_position']); ?>)
                                                    <?php endif; ?></p>
                                                    <p><strong><?php _e('Players with Points:', 'poker-tournament-import'); ?></strong> <?php echo esc_html($points_summary['players_with_points']); ?> / <?php echo esc_html($points_summary['total_players']); ?></p>
                                                    <p><em><?php _e('Points calculated using Tournament Director formula with T33/T80 thresholds and knockout bonuses.', 'poker-tournament-import'); ?></em></p>
                                                </div>
                                            <?php endif; ?>

                                            <div class="tournament-status-info">
                                                <p><em><?php _e('This tournament was automatically imported from Tournament Director data. Complete results and statistics are shown below.', 'poker-tournament-import'); ?></em></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

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
                            <div class="sidebar-widget tournament-statistics-widget">
                                <h3><?php _e('Tournament Statistics', 'poker-tournament-import'); ?></h3>
                                <?php
                                // **CRITICAL FIX**: Enhanced statistics with multiple fallback options
                                $stats_available = false;

                                // Option 1: Try database query if tournament_uuid is available
                                if ($tournament_uuid) {
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

                                    if ($stats && $stats->paid_positions > 0) {
                                        $stats_available = true;
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
                                        <?php
                                    }
                                }

                                // Option 2: Fallback to stored tournament statistics if available
                                if (!$stats_available && $tournament_stats) {
                                    $stats_available = true;
                                    ?>
                                    <div class="mini-stats-grid">
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($tournament_stats['paid_positions']); ?></span>
                                            <span class="mini-stat-label"><?php _e('Paid', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($tournament_stats['cash_rate']); ?>%</span>
                                            <span class="mini-stat-label"><?php _e('Cash Rate', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($tournament_stats['first_place_prize'], 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('1st Prize', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($tournament_stats['average_cash'], 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('Avg Cash', 'poker-tournament-import'); ?></span>
                                        </div>
                                    </div>
                                    <?php
                                }

                                // Option 3: Show basic calculated stats as last resort
                                if (!$stats_available) {
                                    $paid_positions = max(1, round($players_count * 0.1)); // Assume 10% paid
                                    $first_place_estimated = round($prize_pool * 0.5); // Assume 50% for 1st
                                    ?>
                                    <div class="mini-stats-grid">
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($paid_positions); ?></span>
                                            <span class="mini-stat-label"><?php _e('Est. Paid', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html(round(($paid_positions / $players_count) * 100, 1)); ?>%</span>
                                            <span class="mini-stat-label"><?php _e('Est. Cash Rate', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($first_place_estimated, 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('Est. 1st Prize', 'poker-tournament-import'); ?></span>
                                        </div>
                                        <div class="mini-stat">
                                            <span class="mini-stat-value"><?php echo esc_html($currency . number_format($prize_pool / $paid_positions, 0)); ?></span>
                                            <span class="mini-stat-label"><?php _e('Est. Avg Cash', 'poker-tournament-import'); ?></span>
                                        </div>
                                    </div>
                                    <p><em><?php _e('Statistics are estimated. Detailed player data processing may be required.', 'poker-tournament-import'); ?></em></p>
                                    <?php
                                }
                                ?>
                            </div>

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
                                 LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_value = tp.player_id AND pm.meta_key = 'player_uuid'
                                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                 WHERE tp.tournament_id = %s
                                 ORDER BY tp.finish_position ASC",
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

<?php wp_footer(); ?>
</body>
</html>