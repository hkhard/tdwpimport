<?php
/**
 * Custom Post Types Class
 *
 * Registers custom post types for tournaments, series, and seasons
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Import_Post_Types {

    /**
     * Register custom post types
     */
    public function register() {
        $this->register_tournament_post_type();
        $this->register_series_post_type();
        $this->register_season_post_type();
        $this->register_player_post_type();
        $this->register_live_tournament_post_type();
    }

    /**
     * Register Tournament post type
     */
    private function register_tournament_post_type() {
        $labels = array(
            'name' => _x('Tournaments', 'Post type general name', 'poker-tournament-import'),
            'singular_name' => _x('Tournament', 'Post type singular name', 'poker-tournament-import'),
            'menu_name' => _x('Tournaments', 'Admin Menu text', 'poker-tournament-import'),
            'name_admin_bar' => _x('Tournament', 'Add New on Toolbar', 'poker-tournament-import'),
            'add_new' => __('Add New', 'poker-tournament-import'),
            'add_new_item' => __('Add New Tournament', 'poker-tournament-import'),
            'new_item' => __('New Tournament', 'poker-tournament-import'),
            'edit_item' => __('Edit Tournament', 'poker-tournament-import'),
            'view_item' => __('View Tournament', 'poker-tournament-import'),
            'all_items' => __('All Tournaments', 'poker-tournament-import'),
            'search_items' => __('Search Tournaments', 'poker-tournament-import'),
            'parent_item_colon' => __('Parent Tournaments:', 'poker-tournament-import'),
            'not_found' => __('No tournaments found.', 'poker-tournament-import'),
            'not_found_in_trash' => __('No tournaments found in Trash.', 'poker-tournament-import'),
            'featured_image' => _x('Tournament Cover Image', 'Overrides the "Featured Image" phrase for this post type.', 'poker-tournament-import'),
            'set_featured_image' => _x('Set cover image', 'Overrides the "Set featured image" phrase for this post type.', 'poker-tournament-import'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase for this post type.', 'poker-tournament-import'),
            'use_featured_image' => _x('Use as cover image', 'Overrides the "Use as featured image" phrase for this post type.', 'poker-tournament-import'),
            'archives' => _x('Tournament archives', 'The post type archive label used in nav menus.', 'poker-tournament-import'),
            'insert_into_item' => _x('Insert into tournament', 'Overrides the "Insert into post"/"Insert into page" phrase (used when inserting media uploads).', 'poker-tournament-import'),
            'uploaded_to_this_item' => _x('Uploaded to this tournament', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase (used when viewing media uploaded to a post).', 'poker-tournament-import'),
            'filter_items_list' => _x('Filter tournaments list', 'Screen reader text for the filter links heading on the post type listing screen.', 'poker-tournament-import'),
            'items_list_navigation' => _x('Tournaments list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'poker-tournament-import'),
            'items_list' => _x('Tournaments list', 'Screen reader text for the items list heading on the post type listing screen.', 'poker-tournament-import'),
        );

        $args = array(
            'label' => __('Tournament', 'poker-tournament-import'),
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tournaments'),
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-trophy',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('tournament', $args);
    }

    /**
     * Register Tournament Series post type
     */
    private function register_series_post_type() {
        $labels = array(
            'name' => _x('Tournament Series', 'Post type general name', 'poker-tournament-import'),
            'singular_name' => _x('Tournament Series', 'Post type singular name', 'poker-tournament-import'),
            'menu_name' => _x('Series', 'Admin Menu text', 'poker-tournament-import'),
            'add_new' => __('Add New Series', 'poker-tournament-import'),
            'add_new_item' => __('Add New Series', 'poker-tournament-import'),
            'new_item' => __('New Series', 'poker-tournament-import'),
            'edit_item' => __('Edit Series', 'poker-tournament-import'),
            'view_item' => __('View Series', 'poker-tournament-import'),
            'all_items' => __('All Series', 'poker-tournament-import'),
            'search_items' => __('Search Series', 'poker-tournament-import'),
            'not_found' => __('No series found.', 'poker-tournament-import'),
            'not_found_in_trash' => __('No series found in Trash.', 'poker-tournament-import'),
        );

        $args = array(
            'label' => __('Tournament Series', 'poker-tournament-import'),
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=tournament',
            'query_var' => true,
            'rewrite' => array('slug' => 'tournament-series'),
            'capability_type' => 'post',
            'hierarchical' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('tournament_series', $args);
    }

    /**
     * Register Tournament Season post type
     */
    private function register_season_post_type() {
        $labels = array(
            'name' => _x('Tournament Seasons', 'Post type general name', 'poker-tournament-import'),
            'singular_name' => _x('Tournament Season', 'Post type singular name', 'poker-tournament-import'),
            'menu_name' => _x('Seasons', 'Admin Menu text', 'poker-tournament-import'),
            'add_new' => __('Add New Season', 'poker-tournament-import'),
            'add_new_item' => __('Add New Season', 'poker-tournament-import'),
            'new_item' => __('New Season', 'poker-tournament-import'),
            'edit_item' => __('Edit Season', 'poker-tournament-import'),
            'view_item' => __('View Season', 'poker-tournament-import'),
            'all_items' => __('All Seasons', 'poker-tournament-import'),
            'search_items' => __('Search Seasons', 'poker-tournament-import'),
            'not_found' => __('No seasons found.', 'poker-tournament-import'),
            'not_found_in_trash' => __('No seasons found in Trash.', 'poker-tournament-import'),
        );

        $args = array(
            'label' => __('Tournament Season', 'poker-tournament-import'),
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=tournament',
            'query_var' => true,
            'rewrite' => array('slug' => 'tournament-seasons'),
            'capability_type' => 'post',
            'hierarchical' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('tournament_season', $args);
    }

    /**
     * Register Player post type
     */
    private function register_player_post_type() {
        $labels = array(
            'name' => _x('Players', 'Post type general name', 'poker-tournament-import'),
            'singular_name' => _x('Player', 'Post type singular name', 'poker-tournament-import'),
            'menu_name' => _x('Players', 'Admin Menu text', 'poker-tournament-import'),
            'add_new' => __('Add New Player', 'poker-tournament-import'),
            'add_new_item' => __('Add New Player', 'poker-tournament-import'),
            'new_item' => __('New Player', 'poker-tournament-import'),
            'edit_item' => __('Edit Player', 'poker-tournament-import'),
            'view_item' => __('View Player', 'poker-tournament-import'),
            'all_items' => __('All Players', 'poker-tournament-import'),
            'search_items' => __('Search Players', 'poker-tournament-import'),
            'not_found' => __('No players found.', 'poker-tournament-import'),
            'not_found_in_trash' => __('No players found in Trash.', 'poker-tournament-import'),
        );

        $args = array(
            'label' => __('Player', 'poker-tournament-import'),
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=tournament',
            'query_var' => true,
            'rewrite' => array('slug' => 'players'),
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('player', $args);
    }

    /**
     * Register Live Tournament post type
     * For actively running tournaments with clock/table management
     */
    private function register_live_tournament_post_type() {
        $labels = array(
            'name' => _x('Live Tournaments', 'Post type general name', 'poker-tournament-import'),
            'singular_name' => _x('Live Tournament', 'Post type singular name', 'poker-tournament-import'),
            'menu_name' => _x('Live Tournaments', 'Admin Menu text', 'poker-tournament-import'),
            'add_new' => __('New Live Tournament', 'poker-tournament-import'),
            'add_new_item' => __('Create Live Tournament', 'poker-tournament-import'),
            'new_item' => __('New Live Tournament', 'poker-tournament-import'),
            'edit_item' => __('Manage Live Tournament', 'poker-tournament-import'),
            'view_item' => __('View Live Tournament', 'poker-tournament-import'),
            'all_items' => __('All Live Tournaments', 'poker-tournament-import'),
            'search_items' => __('Search Live Tournaments', 'poker-tournament-import'),
            'not_found' => __('No live tournaments found.', 'poker-tournament-import'),
            'not_found_in_trash' => __('No live tournaments found in Trash.', 'poker-tournament-import'),
        );

        $args = array(
            'label' => __('Live Tournament', 'poker-tournament-import'),
            'labels' => $labels,
            'public' => false,
            'has_archive' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'tdwp-tournament-manager',
            'query_var' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'hierarchical' => false,
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-games',
        );

        register_post_type('live_tournament', $args);
    }
}