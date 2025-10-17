<?php
/**
 * Taxonomies Class
 *
 * Handles tournament taxonomies for categorization
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Import_Taxonomies {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
        add_action('admin_init', array($this, 'create_default_terms'));
    }

    /**
     * Register tournament taxonomies
     */
    public function register_taxonomies() {

        // Tournament Type Taxonomy
        register_taxonomy('tournament_type', array('tournament'), array(
            'labels' => array(
                'name' => __('Tournament Types', 'poker-tournament-import'),
                'singular_name' => __('Tournament Type', 'poker-tournament-import'),
                'search_items' => __('Search Tournament Types', 'poker-tournament-import'),
                'all_items' => __('All Tournament Types', 'poker-tournament-import'),
                'parent_item' => __('Parent Tournament Type', 'poker-tournament-import'),
                'parent_item_colon' => __('Parent Tournament Type:', 'poker-tournament-import'),
                'edit_item' => __('Edit Tournament Type', 'poker-tournament-import'),
                'update_item' => __('Update Tournament Type', 'poker-tournament-import'),
                'add_new_item' => __('Add New Tournament Type', 'poker-tournament-import'),
                'new_item_name' => __('New Tournament Type Name', 'poker-tournament-import'),
                'menu_name' => __('Tournament Types', 'poker-tournament-import'),
                'choose_from_most_used' => __('Choose from most used tournament types', 'poker-tournament-import'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tournament-type'),
            'show_in_rest' => true,
            'public' => true,
            'has_archive' => true,
        ));

        // Tournament Format Taxonomy
        register_taxonomy('tournament_format', array('tournament'), array(
            'labels' => array(
                'name' => __('Tournament Formats', 'poker-tournament-import'),
                'singular_name' => __('Tournament Format', 'poker-tournament-import'),
                'search_items' => __('Search Tournament Formats', 'poker-tournament-import'),
                'all_items' => __('All Tournament Formats', 'poker-tournament-import'),
                'parent_item' => __('Parent Tournament Format', 'poker-tournament-import'),
                'parent_item_colon' => __('Parent Tournament Format:', 'poker-tournament-import'),
                'edit_item' => __('Edit Tournament Format', 'poker-tournament-import'),
                'update_item' => __('Update Tournament Format', 'poker-tournament-import'),
                'add_new_item' => __('Add New Tournament Format', 'poker-tournament-import'),
                'new_item_name' => __('New Tournament Format Name', 'poker-tournament-import'),
                'menu_name' => __('Tournament Formats', 'poker-tournament-import'),
                'choose_from_most_used' => __('Choose from most used tournament formats', 'poker-tournament-import'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tournament-format'),
            'show_in_rest' => true,
            'public' => true,
            'has_archive' => true,
        ));

        // Tournament Category Taxonomy
        register_taxonomy('tournament_category', array('tournament'), array(
            'labels' => array(
                'name' => __('Tournament Categories', 'poker-tournament-import'),
                'singular_name' => __('Tournament Category', 'poker-tournament-import'),
                'search_items' => __('Search Tournament Categories', 'poker-tournament-import'),
                'all_items' => __('All Tournament Categories', 'poker-tournament-import'),
                'parent_item' => __('Parent Tournament Category', 'poker-tournament-import'),
                'parent_item_colon' => __('Parent Tournament Category:', 'poker-tournament-import'),
                'edit_item' => __('Edit Tournament Category', 'poker-tournament-import'),
                'update_item' => __('Update Tournament Category', 'poker-tournament-import'),
                'add_new_item' => __('Add New Tournament Category', 'poker-tournament-import'),
                'new_item_name' => __('New Tournament Category Name', 'poker-tournament-import'),
                'menu_name' => __('Tournament Categories', 'poker-tournament-import'),
                'choose_from_most_used' => __('Choose from most used tournament categories', 'poker-tournament-import'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tournament-category'),
            'show_in_rest' => true,
            'public' => true,
            'has_archive' => true,
        ));
    }

    /**
     * Create default taxonomy terms
     */
    public function create_default_terms() {

        // Create Tournament Type terms
        $tournament_types = array(
            'Texas Hold\'em' => array(
                'description' => __('Texas Hold\'em poker tournaments', 'poker-tournament-import'),
                'slug' => 'texas-holdem'
            ),
            'Omaha' => array(
                'description' => __('Omaha poker tournaments', 'poker-tournament-import'),
                'slug' => 'omaha'
            ),
            'Seven Card Stud' => array(
                'description' => __('Seven Card Stud tournaments', 'poker-tournament-import'),
                'slug' => 'seven-card-stud'
            ),
            'Mixed Games' => array(
                'description' => __('Mixed game tournaments', 'poker-tournament-import'),
                'slug' => 'mixed-games'
            ),
            'Other' => array(
                'description' => __('Other poker variants', 'poker-tournament-import'),
                'slug' => 'other'
            )
        );

        foreach ($tournament_types as $name => $args) {
            if (!term_exists($name, 'tournament_type')) {
                wp_insert_term($name, 'tournament_type', $args);
            }
        }

        // Create Tournament Format terms
        $tournament_formats = array(
            'No Limit' => array(
                'description' => __('No Limit betting structure', 'poker-tournament-import'),
                'slug' => 'no-limit'
            ),
            'Pot Limit' => array(
                'description' => __('Pot Limit betting structure', 'poker-tournament-import'),
                'slug' => 'pot-limit'
            ),
            'Fixed Limit' => array(
                'description' => __('Fixed Limit betting structure', 'poker-tournament-import'),
                'slug' => 'fixed-limit'
            ),
            'Spread Limit' => array(
                'description' => __('Spread Limit betting structure', 'poker-tournament-import'),
                'slug' => 'spread-limit'
            )
        );

        foreach ($tournament_formats as $name => $args) {
            if (!term_exists($name, 'tournament_format')) {
                wp_insert_term($name, 'tournament_format', $args);
            }
        }

        // Create Tournament Category terms
        $tournament_categories = array(
            'Live Tournament' => array(
                'description' => __('In-person live tournaments', 'poker-tournament-import'),
                'slug' => 'live-tournament'
            ),
            'Online Tournament' => array(
                'description' => __('Online poker tournaments', 'poker-tournament-import'),
                'slug' => 'online-tournament'
            ),
            'Home Game' => array(
                'description' => __('Private home game tournaments', 'poker-tournament-import'),
                'slug' => 'home-game'
            ),
            'Charity Event' => array(
                'description' => __('Charity fundraising tournaments', 'poker-tournament-import'),
                'slug' => 'charity-event'
            ),
            'Casino Tournament' => array(
                'description' => __('Casino-hosted tournaments', 'poker-tournament-import'),
                'slug' => 'casino-tournament'
            ),
            'Club Tournament' => array(
                'description' => __('Poker club tournaments', 'poker-tournament-import'),
                'slug' => 'club-tournament'
            ),
            'Satellite' => array(
                'description' => __('Satellite tournaments', 'poker-tournament-import'),
                'slug' => 'satellite'
            ),
            'Championship' => array(
                'description' => __('Championship events', 'poker-tournament-import'),
                'slug' => 'championship'
            )
        );

        foreach ($tournament_categories as $name => $args) {
            if (!term_exists($name, 'tournament_category')) {
                wp_insert_term($name, 'tournament_category', $args);
            }
        }
    }

    /**
     * Auto-categorize tournament based on .tdt data
     */
    public function auto_categorize_tournament($tournament_id, $tournament_data) {

        if (!$tournament_id || empty($tournament_data)) {
            return;
        }

        // Extract tournament type from game name
        $game_name = strtolower($tournament_data['gameName'] ?? '');
        $tournament_type_term = $this->determine_tournament_type($game_name);

        // Extract format from game type
        $game_type = intval($tournament_data['gameType'] ?? 0);
        $tournament_format_term = $this->determine_tournament_format($game_type);

        // Determine category based on available data
        $tournament_category_term = $this->determine_tournament_category($tournament_data);

        // Assign terms to tournament
        $terms_to_assign = array();

        if ($tournament_type_term) {
            $terms_to_assign[] = $tournament_type_term;
        }

        if ($tournament_format_term) {
            $terms_to_assign[] = $tournament_format_term;
        }

        if ($tournament_category_term) {
            $terms_to_assign[] = $tournament_category_term;
        }

        if (!empty($terms_to_assign)) {
            wp_set_object_terms($tournament_id, $terms_to_assign, 'tournament_type', false);
            wp_set_object_terms($tournament_id, $terms_to_assign, 'tournament_format', false);
            wp_set_object_terms($tournament_id, $terms_to_assign, 'tournament_category', false);
        }
    }

    /**
     * Determine tournament type from game name
     */
    private function determine_tournament_type($game_name) {

        if (strpos($game_name, 'hold\'em') !== false || strpos($game_name, 'holdem') !== false) {
            return 'texas-holdem';
        } elseif (strpos($game_name, 'omaha') !== false) {
            return 'omaha';
        } elseif (strpos($game_name, 'stud') !== false) {
            return 'seven-card-stud';
        } elseif (strpos($game_name, 'mixed') !== false || strpos($game_name, 'horse') !== false) {
            return 'mixed-games';
        }

        return 'other';
    }

    /**
     * Determine tournament format from game type
     */
    private function determine_tournament_format($game_type) {

        switch ($game_type) {
            case 0:
                return 'fixed-limit';
            case 1:
                return 'pot-limit';
            case 2:
                return 'no-limit';
            default:
                return 'no-limit';
        }
    }

    /**
     * Determine tournament category from tournament data
     */
    private function determine_tournament_category($tournament_data) {

        // Default to live tournament unless specified otherwise
        $category = 'live-tournament';

        // You could add logic here to detect online tournaments
        // based on specific data patterns or metadata

        return $category;
    }

    /**
     * Get taxonomy terms for display
     */
    public function get_tournament_taxonomy_terms($tournament_id) {

        $taxonomies = array('tournament_type', 'tournament_format', 'tournament_category');
        $terms = array();

        foreach ($taxonomies as $taxonomy) {
            $taxonomy_terms = wp_get_object_terms($tournament_id, $taxonomy);
            if (!empty($taxonomy_terms) && !is_wp_error($taxonomy_terms)) {
                $terms[$taxonomy] = $taxonomy_terms;
            }
        }

        return $terms;
    }

    /**
     * Display taxonomy terms with links
     */
    public function display_taxonomy_terms($tournament_id, $separator = ' | ') {

        $all_terms = $this->get_tournament_taxonomy_terms($tournament_id);
        $output = array();

        foreach ($all_terms as $taxonomy => $terms) {
            foreach ($terms as $term) {
                $output[] = '<a href="' . esc_url(get_term_link($term)) . '" class="taxonomy-term ' . esc_attr($taxonomy) . '-term">' . esc_html($term->name) . '</a>';
            }
        }

        return !empty($output) ? implode($separator, $output) : '';
    }
}