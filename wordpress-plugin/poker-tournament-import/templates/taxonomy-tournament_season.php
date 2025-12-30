<?php
/**
 * Tournament Season Taxonomy Template
 *
 * This template displays a single tournament season with
 * tabbed interface for overview, results, statistics, and players.
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

<!-- Minimal WordPress integration - skip block themes to avoid unwanted navigation -->
<?php
if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
    get_header();
}
?>

<div class="poker-season-wrapper">
    <main id="primary" class="site-main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('season-single'); ?>

                <!-- Season Header -->
                <header class="season-single-header">
                    <div class="season-title-section">
                        <h1 class="season-title"><?php the_title(); ?></h1>
                        <div class="season-subtitle">
                            <?php esc_html_e('Tournament Season', 'poker-tournament-import'); ?>
                        </div>
                        <?php if (get_the_content()) : ?>
                            <div class="season-description-brief">
                                <?php echo esc_html(wp_trim_words(get_the_content(), 30)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="season-actions">
                        <button class="print-season" onclick="window.print()">
                            <i class="icon-print"></i> <?php esc_html_e('Print Season', 'poker-tournament-import'); ?>
                        </button>
                        <button class="export-season" data-season-id="<?php the_ID(); ?>" data-format="csv">
                            <i class="icon-download"></i> <?php esc_html_e('Export All', 'poker-tournament-import'); ?>
                        </button>
                    </div>
                </header>

                <!-- Season Content -->
                <div class="season-content-area">
                    <?php if (get_the_content()) : ?>
                        <div class="season-description">
                            <?php the_content(); ?>
                        </div>
                    <?php endif; ?>
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
// Minimal WordPress integration - skip block themes to avoid unwanted footer content
if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
    get_footer();
}
?>
</body>
</html>
