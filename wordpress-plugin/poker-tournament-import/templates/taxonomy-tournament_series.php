<?php
/**
 * Tournament Series Taxonomy Template
 *
 * This template displays a single tournament series with
 * tabbed interface for overview, results, statistics, and players.
 *
 * @package Poker Tournament Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="poker-series-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('series-single'); ?>

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

                <!-- Series Content -->
                <div class="series-content-area">
                    <?php if (get_the_content()) : ?>
                        <div class="series-description">
                            <?php the_content(); ?>
                        </div>
                    <?php endif; ?>
                </div>

            </article>

        <?php endwhile; ?>
    </main>
</div>

<!-- Series Header Styles -->
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
    background: linear-gradient(135deg, #3498db, #2980b9);
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

.series-content-area {
    margin-top: 20px;
}

@media (max-width: 768px) {
    .series-single-header {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }

    .series-actions {
        justify-content: center;
    }
}
</style>