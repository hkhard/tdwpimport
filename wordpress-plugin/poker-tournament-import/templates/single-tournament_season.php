<?php
/**
 * Single Tournament Season Template
 *
 * This template displays a single tournament season post with
 * tabbed interface for overview, results, statistics, and players.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="poker-season-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('season-single'); ?>>

                <!-- Season Header -->
                <header class="season-single-header">
                    <div class="season-title-section">
                        <h1 class="season-title"><?php the_title(); ?></h1>
                        <div class="season-subtitle">
                            <?php _e('Tournament Season', 'poker-tournament-import'); ?>
                        </div>
                        <?php if (get_the_content()) : ?>
                            <div class="season-description-brief">
                                <?php echo wp_trim_words(get_the_content(), 30); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="season-actions">
                        <button class="print-season" onclick="window.print()">
                            <i class="icon-print"></i> <?php _e('Print Season', 'poker-tournament-import'); ?>
                        </button>
                        <button class="export-season" data-season-id="<?php the_ID(); ?>" data-format="csv">
                            <i class="icon-download"></i> <?php _e('Export All', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                </header>

                <!-- Tabbed Interface -->
                <div class="season-content-area">
                    <?php echo do_shortcode('[season_tabs season_id="' . get_the_ID() . '"]'); ?>
                </div>

            </article>

        <?php endwhile; ?>
    </main>
</div>

<!-- Season Header Styles -->
<style>
.poker-season-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.season-single-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    padding: 40px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border-radius: 12px;
}

.season-title {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.season-subtitle {
    font-size: 18px;
    opacity: 0.9;
    margin-bottom: 16px;
}

.season-description-brief {
    font-size: 16px;
    line-height: 1.5;
    opacity: 0.8;
}

.season-actions {
    display: flex;
    gap: 12px;
}

.print-season,
.export-season {
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

.print-season:hover,
.export-season:hover {
    background: rgba(255, 255, 255, 0.3);
}

.season-content-area {
    margin-top: 20px;
}

@media (max-width: 768px) {
    .season-single-header {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }

    .season-actions {
        justify-content: center;
    }
}
</style>

<?php
get_footer();
