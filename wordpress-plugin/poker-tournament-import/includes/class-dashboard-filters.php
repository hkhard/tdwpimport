<?php
/**
 * Dashboard Filter System
 *
 * Handles server-side filtering for CSS dashboard
 * No JavaScript - uses URL parameters and form submission
 *
 * @package Poker_Tournament_Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Dashboard_Filters {

    /**
     * Current user ID
     * @var int
     */
    private $user_id;

    /**
     * Filter configuration
     * @var array
     */
    private $filter_config;

    /**
     * Constructor
     *
     * @param int|null $user_id Optional user ID, defaults to current user
     */
    public function __construct($user_id = null) {
        $this->user_id = $user_id ?: get_current_user_id();
        $this->filter_config = $this->get_filter_config();

        // Auto-save preferences from URL params
        $this->maybe_save_preferences();
    }

    /**
     * Define available filters
     * Extensible - add new filters here
     *
     * @return array Filter configuration
     */
    private function get_filter_config() {
        // Get available seasons
        $seasons = get_posts(array(
            'post_type' => 'tournament_season',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'DESC',
            'post_status' => 'publish'
        ));

        // Build season options
        $season_options = array(
            array(
                'value' => 'all',
                'label' => __('All Seasons', 'poker-tournament-import')
            )
        );

        foreach ($seasons as $season) {
            $season_options[] = array(
                'value' => $season->ID,
                'label' => $season->post_title
            );
        }

        return array(
            'season' => array(
                'type' => 'select',
                'label' => __('Season', 'poker-tournament-import'),
                'options' => $season_options,
                'default' => 'all'
            )
            // Future filters: 'series', 'min_tournaments', 'date_range', etc.
        );
    }

    /**
     * Get active filter values
     * Priority: URL params > saved preferences > defaults
     *
     * @return array Active filter values
     */
    public function get_active_filters() {
        // Get saved user preferences
        $saved = get_user_meta($this->user_id, 'poker_dashboard_filters', true);
        if (!is_array($saved)) {
            $saved = array();
        }

        $active = array();

        foreach ($this->filter_config as $filter_key => $config) {
            // Check URL params first
            $url_param = isset($_GET['filter_' . $filter_key])
                ? sanitize_text_field($_GET['filter_' . $filter_key])
                : null;

            // Fall back to saved preference
            $saved_value = isset($saved[$filter_key]) ? $saved[$filter_key] : null;

            // Fall back to default
            $default = isset($config['default']) ? $config['default'] : null;

            $active[$filter_key] = $url_param ?: ($saved_value ?: $default);
        }

        return $active;
    }

    /**
     * Save filter preferences to user meta
     *
     * @param array $filters Filter values to save
     * @return bool Success
     */
    public function save_user_preferences($filters) {
        return update_user_meta($this->user_id, 'poker_dashboard_filters', $filters);
    }

    /**
     * Save filter preferences if URL params present
     * Called automatically in constructor
     *
     * @return void
     */
    private function maybe_save_preferences() {
        // Only save on non-AJAX, GET requests
        if (!isset($_GET) || wp_doing_ajax()) {
            return;
        }

        $has_filters = false;
        $filters_to_save = array();

        foreach ($this->filter_config as $filter_key => $config) {
            $param_name = 'filter_' . $filter_key;
            if (isset($_GET[$param_name])) {
                $has_filters = true;
                $filters_to_save[$filter_key] = sanitize_text_field($_GET[$param_name]);
            }
        }

        if ($has_filters) {
            $this->save_user_preferences($filters_to_save);
        }
    }

    /**
     * Render filter control form (CSS-only, no JS)
     *
     * @param string $current_url Current page URL
     * @return string Filter form HTML
     */
    public function render_filter_controls($current_url = '') {
        $active = $this->get_active_filters();
        $filters = $this->filter_config;

        // Remove existing filter params from URL
        $current_url = remove_query_arg(array_keys($this->get_filter_param_names()), $current_url);

        ob_start();
        ?>
        <form method="GET" action="<?php echo esc_url($current_url); ?>" class="dashboard-filters">
            <?php foreach ($filters as $filter_key => $config): ?>
                <div class="filter-control filter-control--<?php echo esc_attr($config['type']); ?>">
                    <label for="filter_<?php echo esc_attr($filter_key); ?>">
                        <?php echo esc_html($config['label']); ?>
                    </label>

                    <?php if ($config['type'] === 'select'): ?>
                        <select
                            name="filter_<?php echo esc_attr($filter_key); ?>"
                            id="filter_<?php echo esc_attr($filter_key); ?>"
                        >
                            <?php
                            $current_value = isset($active[$filter_key]) ? $active[$filter_key] : '';
                            foreach ($config['options'] as $option):
                                $selected = $option['value'] === $current_value ? 'selected' : '';
                            ?>
                                <option
                                    value="<?php echo esc_attr($option['value']); ?>"
                                    <?php echo $selected; ?>
                                >
                                    <?php echo esc_html($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <?php
                    /**
                     * Future filter types:
                     * - date: Date range picker
                     * - number: Min/max inputs
                     * - checkbox: Boolean toggle
                     * - multiselect: Checkboxes for multiple values
                     */
                    ?>
                </div>
            <?php endforeach; ?>

            <div class="filter-actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Apply Filters', 'poker-tournament-import'); ?>
                </button>

                <?php
                // Show reset button if any active filters
                $has_active = false;
                foreach ($active as $value) {
                    if ($value && $value !== 'all') {
                        $has_active = true;
                        break;
                    }
                }
                ?>

                <?php if ($has_active): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array_keys($this->get_filter_param_names()), $current_url)); ?>" class="button">
                        <?php esc_html_e('Reset', 'poker-tournament-import'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }

    /**
     * Get URL parameter names for all filters
     *
     * @return array Parameter names
     */
    private function get_filter_param_names() {
        return array_map(function($key) {
            return 'filter_' . $key;
        }, array_keys($this->filter_config));
    }

    /**
     * Apply filters to get tournament IDs
     * Returns filtered tournament IDs based on active filters
     *
     * @return array Filtered tournament IDs
     */
    public function get_filtered_tournament_ids() {
        $active = $this->get_active_filters();

        $args = array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids' // Only get IDs for performance
        );

        // Apply season filter
        if (isset($active['season']) && $active['season'] !== 'all') {
            $args['meta_query'] = array(
                array(
                    'key' => '_tournament_season_id',
                    'value' => intval($active['season']),
                    'compare' => '='
                )
            );
        }

        // Future: apply series, date_range, etc.

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Get the currently active season ID from filter state
     *
     * Priority order:
     * 1. URL parameter ?filter_season=XXX (highest priority - user just changed filter)
     * 2. User meta preference 'poker_dashboard_filters'
     * 3. Default option 'tdwp_default_season'
     * 4. Null (no season selected)
     *
     * @return int|null Season ID or null if 'all' or none selected
     */
    public function get_active_season() {
        $active = $this->get_active_filters();

        // Check if season filter exists
        if (isset($active['season'])) {
            $season_value = $active['season'];

            // Return null if 'all' is selected
            if ($season_value === 'all') {
                return null;
            }

            // Return season ID if numeric
            $season_id = intval($season_value);
            if ($season_id > 0) {
                return $season_id;
            }
        }

        return null;
    }
}
