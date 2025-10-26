<?php
/**
 * Shortcode Helper Meta Box
 *
 * Provides shortcode examples for tournaments, series, and seasons
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Shortcode_Helper {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_shortcode_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_shortcode_helper_styles'));
    }

    /**
     * Enqueue shortcode helper styles for admin screens
     */
    public function enqueue_shortcode_helper_styles($hook) {
        // Only load on post edit screens
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        // Check if we're editing one of the relevant post types
        global $post;
        if (!$post || !in_array($post->post_type, array('tournament', 'tournament_series', 'tournament_season', 'player'))) {
            return;
        }

        // Add inline styles for shortcode helper meta box
        $styles = '
        .poker-shortcode-helper-content {
            padding: 10px;
        }
        .shortcode-example {
            margin-bottom: 10px;
        }
        .shortcode-example label {
            display: block;
            margin-bottom: 3px;
            font-weight: 600;
            font-size: 12px;
        }
        .shortcode-example input {
            font-family: monospace;
            font-size: 11px;
        }
        .shortcode-info {
            margin: 10px 0;
            padding: 8px;
            background: #f9f9f9;
            border-left: 3px solid #0073aa;
            border-radius: 3px;
        }
        .shortcode-links {
            margin-top: 10px;
        }
        ';

        wp_add_inline_style('wp-admin', $styles);
    }

    /**
     * Add shortcode meta boxes
     */
    public function add_shortcode_meta_boxes() {
        add_meta_box(
            'poker_shortcode_helper',
            __('Shortcode Helper', 'poker-tournament-import'),
            array($this, 'render_shortcode_helper'),
            'tournament',
            'side',
            'high'
        );

        add_meta_box(
            'poker_shortcode_helper_series',
            __('Shortcode Helper', 'poker-tournament-import'),
            array($this, 'render_series_shortcode_helper'),
            'tournament_series',
            'side',
            'high'
        );

        add_meta_box(
            'poker_shortcode_helper_season',
            __('Shortcode Helper', 'poker-tournament-import'),
            array($this, 'render_season_shortcode_helper'),
            'tournament_season',
            'side',
            'high'
        );

        add_meta_box(
            'poker_shortcode_helper_player',
            __('Shortcode Helper', 'poker-tournament-import'),
            array($this, 'render_player_shortcode_helper'),
            'player',
            'side',
            'high'
        );
    }

    /**
     * Render tournament shortcode helper
     */
    public function render_shortcode_helper($post) {
        $tournament_id = $post->ID;
        $title = get_the_title($post);

        echo '<div class="poker-shortcode-helper-content">';
        echo '<h4>' . esc_html__('Quick Shortcodes', 'poker-tournament-import') . '</h4>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Copy this shortcode:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[tournament_results id=&quot;' . esc_attr($tournament_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Advanced:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[tournament_results id=&quot;' . esc_attr($tournament_id) . '&quot; show_players=&quot;true&quot; show_structure=&quot;true&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-info">';
        /* translators: %s: tournament title */
        echo '<p><small>' . esc_html(sprintf(__('Use this shortcode to display "%s" on any page or post.', 'poker-tournament-import'), esc_html($title))) . '</small></p>';
        echo '</div>';

        echo '<div class="shortcode-links">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=tournament&page=poker-shortcode-help')) . '" class="button button-small" target="_blank">';
        echo esc_html__('View All Shortcodes', 'poker-tournament-import');
        echo '</a>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render series shortcode helper
     */
    public function render_series_shortcode_helper($post) {
        $series_id = $post->ID;
        $title = get_the_title($post);

        echo '<div class="poker-shortcode-helper-content">';
        echo '<h4>' . esc_html__('Series Shortcodes', 'poker-tournament-import') . '</h4>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Tabbed Interface:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[series_tabs series_id=&quot;' . esc_attr($series_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Series Standings:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[series_standings id=&quot;' . esc_attr($series_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Individual Tabs:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[series_overview id=&quot;' . esc_attr($series_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-info">';
        /* translators: %s: series title */
        echo '<p><small>' . esc_html(sprintf(__('Use these shortcodes to display "%s" information on any page.', 'poker-tournament-import'), esc_html($title))) . '</small></p>';
        echo '</div>';

        echo '<div class="shortcode-links">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=tournament&page=poker-shortcode-help')) . '" class="button button-small" target="_blank">';
        echo esc_html__('Shortcode Guide', 'poker-tournament-import');
        echo '</a>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render season shortcode helper
     */
    public function render_season_shortcode_helper($post) {
        $season_id = $post->ID;
        $title = get_the_title($post);

        echo '<div class="poker-shortcode-helper-content">';
        echo '<h4>' . esc_html__('Season Shortcodes', 'poker-tournament-import') . '</h4>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Tabbed Interface:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[season_tabs season_id=&quot;' . esc_attr($season_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('Season Standings:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[season_standings id=&quot;' . esc_attr($season_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-info">';
        /* translators: %s: season title */
        echo '<p><small>' . esc_html(sprintf(__('Use these shortcodes to display "%s" information on any page.', 'poker-tournament-import'), esc_html($title))) . '</small></p>';
        echo '</div>';

        echo '<div class="shortcode-links">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=tournament&page=poker-shortcode-help')) . '" class="button button-small" target="_blank">';
        echo esc_html__('Shortcode Guide', 'poker-tournament-import');
        echo '</a>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render player shortcode helper
     */
    public function render_player_shortcode_helper($post) {
        $player_id = $post->ID;
        $title = get_the_title($post);

        echo '<div class="poker-shortcode-helper-content">';
        echo '<h4>' . esc_html__('Player Shortcode', 'poker-tournament-import') . '</h4>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('By Name:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[player_profile name=&quot;' . esc_attr($title) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-example">';
        echo '<label>' . esc_html__('By ID:', 'poker-tournament-import') . '</label>';
        echo '<input type="text" readonly value="[player_profile id=&quot;' . esc_attr($player_id) . '&quot;]" onclick="this.select();" class="widefat">';
        echo '</div>';

        echo '<div class="shortcode-info">';
        /* translators: %s: player name */
        echo '<p><small>' . esc_html(sprintf(__('Use this shortcode to display "%s" profile on any page.', 'poker-tournament-import'), esc_html($title))) . '</small></p>';
        echo '</div>';

        echo '<div class="shortcode-links">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=tournament&page=poker-shortcode-help')) . '" class="button button-small" target="_blank">';
        echo esc_html__('Shortcode Guide', 'poker-tournament-import');
        echo '</a>';
        echo '</div>';

        echo '</div>';
    }
}