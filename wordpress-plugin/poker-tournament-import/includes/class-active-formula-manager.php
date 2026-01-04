<?php
/**
 * Active Formula Manager
 *
 * Handles global active formula selection and persistence.
 * Manages which formula should be used for tournament and season statistics.
 *
 * @package Poker_Tournament_Import
 * @since 3.5.0-beta42
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Active_Formula_Manager {

    /**
     * Constructor
     *
     * Registers AJAX handlers for active formula management
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_tdwp_set_active_formula', array($this, 'handle_set_active_formula'));
        add_action('wp_ajax_tdwp_get_active_formula', array($this, 'handle_get_active_formula'));
        add_action('wp_ajax_tdwp_clear_formula_cache', array($this, 'handle_clear_formula_cache'));
    }

    /**
     * Get the active formula for a category
     *
     * @param string $category Formula category ('tournament' or 'season')
     * @return string|null Formula key or null if none set
     */
    public function get_active_formula($category) {
        $option_name = 'poker_active_' . $category . '_formula';
        $formula_key = get_option($option_name, null);

        if (!$formula_key) {
            return null;
        }

        // Verify formula still exists
        $formulas = get_option('tdwp_tournament_formulas', array());
        if (!isset($formulas[$formula_key])) {
            // Stale reference - clear the option
            delete_option($option_name);
            return null;
        }

        return $formula_key;
    }

    /**
     * Set the active formula for a category
     *
     * @param string $formula_key The formula key to set as active
     * @param string $category Formula category ('tournament' or 'season')
     * @return bool|WP_Error True on success, WP_Error on validation failure
     */
    public function set_active_formula($formula_key, $category) {
        // Validate formula exists
        $formulas = get_option('tdwp_tournament_formulas', array());
        if (!isset($formulas[$formula_key])) {
            return new WP_Error('formula_not_found', 'Formula does not exist');
        }

        // Validate category matches formula's category (for season formulas)
        if ($category === 'season') {
            // Season formulas should have 'season' category
            // But we allow flexibility - any formula can be used
            // Just verify it exists (already done above)
        }

        $option_name = 'poker_active_' . $category . '_formula';
        $updated = update_option($option_name, $formula_key);

        // Clear season standings cache if season formula changed
        if ($updated && $category === 'season') {
            $this->clear_season_standings_cache();
        }

        return $updated;
    }

    /**
     * Clear the active formula for a category
     *
     * @param string $category Formula category ('tournament' or 'season')
     * @return bool True on success
     */
    public function clear_active_formula($category) {
        $option_name = 'poker_active_' . $category . '_formula';
        return delete_option($option_name);
    }

    /**
     * Clear all season standings transients
     *
     * @return int Number of transients deleted
     */
    public function clear_season_standings_cache() {
        global $wpdb;

        // Get all transient keys that match our pattern
        $pattern = 'poker_season_standings_';
        $options = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $pattern) . '%'
        ));

        $count = 0;
        foreach ($options as $option) {
            $transient_name = str_replace('_transient_', '', $option);
            delete_transient($transient_name);
            $count++;
        }

        return $count;
    }

    /**
     * Check if a specific formula is active for a category
     *
     * @param string $formula_key The formula key to check
     * @param string $category Formula category ('tournament' or 'season')
     * @return bool True if this formula is currently active
     */
    public function is_formula_active($formula_key, $category) {
        return $this->get_active_formula($category) === $formula_key;
    }

    /**
     * Get all available formulas that can be set as active
     *
     * @return array List of available formulas
     */
    public function get_available_formulas() {
        $formulas = get_option('tdwp_tournament_formulas', array());

        // Filter out formulas without proper structure
        $available = array();
        foreach ($formulas as $key => $formula) {
            if (isset($formula['display_name']) && isset($formula['formula'])) {
                $available[$key] = $formula;
            }
        }

        return $available;
    }

    /**
     * Validate if a formula key exists
     *
     * @param string $formula_key The formula key to validate
     * @return bool True if the formula exists
     */
    public function formula_exists($formula_key) {
        $formulas = $this->get_available_formulas();
        return isset($formulas[$formula_key]);
    }

    /**
     * Get the default formula (first available or system default)
     *
     * @param string $category Formula category ('tournament' or 'season')
     * @return string|null Formula key or null if no formulas available
     */
    public function get_default_formula($category = 'tournament') {
        $formulas = $this->get_available_formulas();

        if (empty($formulas)) {
            return null;
        }

        // Try to find formula marked as default
        foreach ($formulas as $key => $formula) {
            if (isset($formula['is_default']) && $formula['is_default']) {
                // For season category, prefer season formulas
                if ($category === 'season') {
                    if (isset($formula['category']) && $formula['category'] === 'season') {
                        return $key;
                    }
                } else {
                    return $key;
                }
            }
        }

        // Return first available formula key
        $keys = array_keys($formulas);
        return $keys[0];
    }

    /**
     * Ensure category has an active formula set (set to default if not)
     *
     * @param string $category Formula category ('tournament' or 'season')
     * @return string The active formula key
     */
    public function ensure_active_formula($category) {
        $active = $this->get_active_formula($category);

        if (empty($active) || !$this->formula_exists($active)) {
            $default = $this->get_default_formula($category);
            if ($default) {
                $this->set_active_formula($default, $category);
                return $default;
            }
        }

        return $active;
    }

    /**
     * AJAX handler for setting active formula
     *
     * Expects POST parameters:
     * - security: nonce verification
     * - formula_key: formula to set as active
     * - category: 'tournament' or 'season'
     */
    public function handle_set_active_formula() {
        // Verify nonce
        if (!check_ajax_referer('poker_active_formula_nonce', 'security', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Get parameters
        $formula_key = isset($_POST['formula_key']) ? sanitize_text_field($_POST['formula_key']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

        // Validate category
        if (!in_array($category, array('tournament', 'season'))) {
            wp_send_json_error(array('message' => 'Invalid category'));
        }

        // Set active formula
        $result = $this->set_active_formula($formula_key, $category);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Active formula updated',
            'active_formula' => $formula_key,
            'category' => $category
        ));
    }

    /**
     * AJAX handler for getting active formula
     *
     * Expects POST parameters:
     * - security: nonce verification
     * - category: 'tournament' or 'season'
     */
    public function handle_get_active_formula() {
        // Verify nonce
        if (!check_ajax_referer('poker_active_formula_nonce', 'security', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Get parameters
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

        // Validate category
        if (!in_array($category, array('tournament', 'season'))) {
            wp_send_json_error(array('message' => 'Invalid category'));
        }

        // Get active formula
        $formula_key = $this->get_active_formula($category);

        if (!$formula_key) {
            wp_send_json_success(array(
                'active_formula' => null,
                'category' => $category
            ));
        }

        // Get formula details
        $formulas = $this->get_available_formulas();
        $formula = isset($formulas[$formula_key]) ? $formulas[$formula_key] : null;

        wp_send_json_success(array(
            'active_formula' => $formula_key,
            'display_name' => $formula ? $formula['display_name'] : '',
            'category' => $category
        ));
    }

    /**
     * AJAX handler for clearing formula cache
     *
     * Expects POST parameters:
     * - security: nonce verification
     */
    public function handle_clear_formula_cache() {
        // Verify nonce
        if (!check_ajax_referer('poker_active_formula_nonce', 'security', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Clear season standings cache
        $count = $this->clear_season_standings_cache();

        wp_send_json_success(array(
            'message' => 'Formula cache cleared',
            'cleared' => $count
        ));
    }
}
