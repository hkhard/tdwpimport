# Overall Points Standings & Custom Filtering Implementation Plan

## Overview

Add an overall points standings component to the poker dashboard with customizable, extensible filtering. All implemented using CSS-only techniques (no JavaScript/AJAX) following existing dashboard patterns. Filters persist per-user and support season-based filtering with extensibility for future filter types.

## Current State Analysis

### Existing Dashboard Architecture

**CSS Dashboard System** (`class-css-dashboard-config.php`, `class-dashboard-renderer.php`):
- Configuration-based rendering: Define sections/components in PHP config
- Server-side rendering: All HTML generated server-side
- CSS-only interactivity: `:target` for modals, URL params for pagination, radio inputs for sorting
- No JavaScript: All state via CSS and page navigation
- Responsive CSS Grid with mobile breakpoints

**Data Sources**:
- `poker_tournament_players` - Individual tournament results
- `poker_player_roi` - Player ROI metrics
- `poker_statistics` - Pre-calculated stats data mart
- `poker_financial_summary` - Financial analytics

**Existing Standings System**:
- `Poker_Series_Standings_Calculator` - Series-specific standings with tie-breakers
- Formula system via `Poker_Tournament_Formula_Validator` - Custom point calculations
- Transient caching (1 hour)
- Comprehensive tie-breaker logic (first places, top 3/5, best finish, winnings, etc.)

**Current CSS Patterns**:
```php
// Pagination via URL params
$page = isset($_GET['table_page_' . $component['id']])
    ? intval($_GET['table_page_' . $component['id']]) : 1;

// Sorting via radio inputs + :checked CSS
<input type="radio" name="sort-{component_id}" value="{column_index}">

// Modals via :target CSS
<a href="#drill-down-{component_id}">...</a>
<div id="drill-down-{component_id}" class="drill-down-overlay">...</div>
```

### Key Constraints & Patterns to Follow

1. **CSS-Only**: No JavaScript, no AJAX - all interactivity via CSS and page navigation
2. **Server-Side Rendering**: All filtering/sorting happens in PHP before rendering
3. **URL Parameter State**: Use `$_GET` for filter state, enable bookmarking/sharing
4. **Form-Based Filters**: Use `<form method="GET">` for filter controls
5. **Per-User Persistence**: Use `wp_user_meta` for saved filter preferences
6. **Extensibility**: Build filter system to support additional filters (series, date ranges, etc.)

## Desired End State

### Component: Overall Points Standings Table

A new dashboard section showing player rankings with:
- **Rank display** with ties (1, 2T, 2T, 4, etc.)
- **Player name** linking to player profile
- **Season points** calculated via configured formula
- **Tournaments played** count
- **Best finish** position
- **Average finish** with decimal precision
- **Tie-breaker indicators** (first places, top 3, top 5)

### Filter System: Customizable Per-User Filters

**Primary Filter: Season Selection**
- Dropdown select: "All Seasons", or specific season
- Per-user persistence: Last selected season saved
- Extensible design: Add series, date range, player search later

**Filter UI Pattern**:
```html
<form method="GET" class="dashboard-filters">
  <select name="filter_season">
    <option value="all">All Seasons</option>
    <option value="123" selected>2024-2025 Season</option>
  </select>
  <button type="submit">Apply Filters</button>
  <button type="button" name="reset_filters">Reset</button>
</form>
```

**URL State**: `?filter_season=123&table_page_standings=1`

**Per-User Storage**: `update_user_meta($user_id, 'poker_dashboard_filters', ['season' => 123])`

### Key Discoveries

1. **Reuse Existing Calculator**: `Poker_Series_Standings_Calculator` already handles formula-based calculations with tie-breakers - extend it for overall standings
2. **No AJAX Needed**: CSS dashboard pattern uses server-side rendering + URL params - no JavaScript required
3. **Formula System Exists**: Season formulas already supported - just need to apply to overall/season-filtered data
4. **Pagination Built-in**: `class-dashboard-renderer.php:253-309` handles table pagination via URL params
5. **Transient Caching**: Standings calculator uses `set_transient()` - leverage for performance

## What We're NOT Doing

- No JavaScript/AJAX - pure CSS/PHP solution
- No new REST API endpoints - server-side rendering only
- No client-side filtering - all filtering in PHP
- No advanced filters yet (date ranges, series, player search) - building extensibility foundation
- No user settings UI page - filters persist automatically
- No real-time updates - page refresh for filter changes

## Implementation Approach

### Architecture

**Server-Side Filtering Pipeline**:
```
User Preferences (wp_user_meta)
    ↓
URL Parameters ($_GET)
    ↓
Filter Processor (PHP)
    ↓
Data Query (with filters)
    ↓
Standings Calculator (formula application)
    ↓
Table Renderer (HTML output)
```

**Filter System Design**:
```
Poker_Dashboard_Filters (new class)
    - get_active_filters(): Read from user_meta + $_GET
    - apply_to_query($query): Modify WP_Query with filters
    - save_user_preferences(): Persist to wp_user_meta
    - render_filter_controls(): Output form HTML
```

**Standings Component**:
```
Poker_Dashboard_Config (extend existing)
    + get_overall_standings_section(): New section
        - Reads filters
        - Calls standings calculator with filters
        - Returns table component config
```

### Design Decisions

**1. Form-Based Filters (CSS-Only)**
- `<form method="GET">` with select dropdowns
- Submit button updates URL params
- PHP reads `$_GET` for filter values
- User preferences loaded on page load, merged with URL params

**2. Per-User Persistence**
- `get_user_meta($user_id, 'poker_dashboard_filters', true)` on page load
- Update preferences when form submitted
- URL params override saved preferences (enable sharing)

**3. Extensibility Pattern**
```php
$filters = array(
    'season' => array(
        'type' => 'select',
        'label' => 'Season',
        'options' => $seasons_list,
        'default' => 'all'
    ),
    // Future: 'series' => array(...),
    // Future: 'date_range' => array(...),
);
```

**4. Formula Integration**
- Use existing `tdwp_active_season_formula` option
- Pass formula to `Poker_Series_Standings_Calculator::calculate_series_standings()`
- For "All Seasons", aggregate across all tournaments then apply formula

**5. Caching Strategy**
- Standings transient key includes filters: `poker_overall_standings_{season}_{formula}`
- Separate cache per filter combination
- Invalidate on tournament save/delete

## Phase 1: Dashboard Filter System Foundation

### Overview
Create extensible filter system with per-user persistence and season filter.

### Changes Required:

#### 1. `includes/class-dashboard-filters.php` (NEW FILE)
**Purpose**: Filter configuration, processing, and persistence

```php
<?php
/**
 * Dashboard Filter System
 *
 * Handles server-side filtering for CSS dashboard
 * No JavaScript - uses URL parameters and form submission
 */

class Poker_Dashboard_Filters {

    private $user_id;
    private $filter_config;

    public function __construct($user_id = null) {
        $this->user_id = $user_id ?: get_current_user_id();
        $this->filter_config = $this->get_filter_config();
    }

    /**
     * Define available filters
     * Extensible - add new filters here
     */
    private function get_filter_config() {
        $seasons = get_posts(array(
            'post_type' => 'tournament_season',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'DESC',
        ));

        $season_options = array(array('value' => 'all', 'label' => 'All Seasons'));
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
            // Future filters: 'series', 'min_tournaments', etc.
        );
    }

    /**
     * Get active filter values
     * Priority: URL params > saved preferences > defaults
     */
    public function get_active_filters() {
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
     */
    public function save_user_preferences($filters) {
        update_user_meta($this->user_id, 'poker_dashboard_filters', $filters);
    }

    /**
     * Render filter control form (CSS-only, no JS)
     */
    public function render_filter_controls($current_url = '') {
        $active = $this->get_active_filters();
        $filters = $this->filter_config;

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
                            foreach ($config['option s'] as $option):
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

                    <?php /* Future: date, range, checkbox types */ ?>
                </div>
            <?php endforeach; ?>

            <div class="filter-actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Apply Filters', 'poker-tournament-import'); ?>
                </button>

                <?php
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
     */
    private function get_filter_param_names() {
        return array_map(function($key) {
            return 'filter_' . $key;
        }, array_keys($this->filter_config));
    }

    /**
     * Apply filters to a tournament query
     * Returns filtered tournament IDs
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
}
```

#### 2. `assets/css-dashboard/filters.css` (NEW FILE)
**Purpose**: Styling for filter controls

```css
/* Dashboard Filters Form */
.dashboard-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
    padding: 1rem 1.5rem;
    background: var(--dashboard-bg-surface);
    border-radius: var(--dashboard-radius);
    border: 1px solid var(--dashboard-border);
    margin-bottom: 1.5rem;
    box-shadow: var(--dashboard-shadow-sm);
}

.filter-control {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.filter-control label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    color: var(--dashboard-text-muted);
}

.filter-control select {
    padding: 0.5rem 0.75rem;
    min-width: 200px;
    border: 1px solid var(--dashboard-border);
    border-radius: 6px;
    background: var(--dashboard-bg-body);
    color: var(--dashboard-text-main);
    font-size: 0.875rem;
    cursor: pointer;
    transition: border-color 0.2s;
}

.filter-control select:hover,
.filter-control select:focus {
    border-color: var(--dashboard-primary);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-left: auto;
}

.filter-actions .button {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    text-decoration: none;
}

@media (max-width: 768px) {
    .dashboard-filters {
        flex-direction: column;
    }

    .filter-control select {
        width: 100%;
    }

    .filter-actions {
        width: 100%;
        justify-content: stretch;
    }

    .filter-actions .button {
        flex: 1;
        text-align: center;
    }
}
```

#### 3. `includes/class-css-dashboard-config.php` (MODIFY)
**Line 42-64**: Add filter system integration

**Changes**:
```php
public function get_dashboard_config($config) {
    global $wpdb;

    // Initialize filter system
    $filters = new Poker_Dashboard_Filters();

    $config['title'] = 'Poker Tournament Dashboard';
    $config['sections'] = array();

    // Add filter controls section (first, before data)
    $config['sections'][] = $this->get_filters_section($filters);

    // Overview Statistics Section
    $config['sections'][] = $this->get_overview_section();

    // ... existing sections ...

    // NEW: Overall Points Standings Section
    $config['sections'][] = $this->get_overall_standings_section($filters);

    return $config;
}

/**
 * NEW: Filter controls section
 */
private function get_filters_section($filters) {
    $current_url = remove_query_arg(array_keys($_GET)); // Clear all params

    return array(
        'id' => 'dashboard-filters',
        'title' => '', // No title for filter bar
        'columns' => 1,
        'components' => array(
            array(
                'id' => 'filter-controls',
                'parent_section_id' => 'dashboard-filters',
                'type' => 'custom', // Custom HTML type
                'html' => $filters->render_filter_controls($current_url)
            )
        )
    );
}
```

#### 4. `includes/class-dashboard-renderer.php` (MODIFY)
**Line 192-217**: Add support for custom HTML components

**Changes**:
```php
private function render_component($component) {
    $output = '';

    // ... existing iframe check ...

    switch ($component['type']) {
        case 'stat':
            // ... existing stat code ...
            break;

        case 'table':
            $output = $this->render_table_component($component);
            break;

        // NEW: Custom HTML component
        case 'custom':
            if (isset($component['html'])) {
                $output = $component['html'];
            }
            break;
    }

    return $output;
}
```

#### 5. `poker-tournament-import.php` (MODIFY)
**Line ~569-570**: Register new filter class

**Changes**:
```php
// Dashboard filter system
require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-dashboard-filters.php';
```

### Success Criteria:

#### Automated Verification:
- [ ] File `class-dashboard-filters.php` exists in includes/
- [ ] PHP syntax valid: `php -l wordpress-plugin/poker-tournament-import/includes/class-dashboard-filters.php`
- [ ] Filter form renders on dashboard page
- [ ] Season dropdown populates with seasons from database
- [ ] URL parameter `?filter_season=123` updates filter selection
- [ ] User meta saves on form submission: check database `wp_usermeta` table

#### Manual Verification:
- [ ] Filter controls appear at top of dashboard
- [ ] Season dropdown shows available seasons
- [ ] Selecting season and clicking "Apply" updates URL params
- [ ] Filter selection persists across page loads (per-user)
- [ ] "Reset" button clears all filters
- [ ] Layout responsive on mobile (stacks vertically)
- [ ] No JavaScript errors in console (should be none)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Overall Standings Calculator Extension

### Overview
Extend `Poker_Series_Standings_Calculator` to support overall/all-time standings with formula integration.

### Changes Required:

#### 1. `includes/class-series-standings.php` (MODIFY)
**Line 14-578**: Add overall standings method

**Add new public method after line 391**:

```php
/**
 * Calculate overall standings across all tournaments
 * Supports season filtering and formula application
 *
 * @param array $tournament_ids Optional tournament IDs to filter by
 * @param string $formula_key Optional formula key for calculation
 * @return array Overall standings with tie-breakers
 */
public function calculate_overall_standings($tournament_ids = null, $formula_key = null) {
    global $wpdb;

    if (!$formula_key) {
        $formula_key = get_option('tdwp_active_season_formula', 'season_total');
    }

    // Generate cache key including tournament IDs
    $cache_key = 'poker_overall_standings_' . md5(serialize($tournament_ids) . $formula_key);
    $cached_standings = get_transient($cache_key);

    if ($cached_standings !== false) {
        return $cached_standings;
    }

    // Get tournament IDs if not provided
    if ($tournament_ids === null) {
        $tournament_ids = get_posts(array(
            'post_type' => 'tournament',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
    }

    if (empty($tournament_ids)) {
        return array();
    }

    // Get all players who participated
    $table_name = $wpdb->prefix . 'poker_tournament_players';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $unique_players = $wpdb->get_col($wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
        "SELECT DISTINCT player_id FROM $table_name
         WHERE tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")",
        $tournament_ids
    ));

    if (empty($unique_players)) {
        return array();
    }

    // Calculate standings for each player
    $standings = array();
    foreach ($unique_players as $player_id) {
        $player_data = $this->calculate_overall_player_data($player_id, $tournament_ids, $formula_key);
        if ($player_data) {
            $standings[] = $player_data;
        }
    }

    // Sort with tie-breakers
    $standings = $this->sort_standings_with_tiebreakers($standings);

    // Assign rankings
    $standings = $this->assign_final_rankings($standings);

    // Cache for 1 hour
    set_transient($cache_key, $standings, HOUR_IN_SECONDS);

    return $standings;
}

/**
 * Calculate overall data for a single player
 * Mirrors calculate_player_series_data but for overall/all-time
 */
private function calculate_overall_player_data($player_id, $tournament_ids, $formula_key) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'poker_tournament_players';

    // Get all tournament results for this player
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT tournament_id, finish_position, winnings, points, hits
         FROM $table_name
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, uses $wpdb->prefix
         WHERE player_id = %s AND tournament_id IN (" . implode(',', array_fill(0, count($tournament_ids), '%s')) . ")
         ORDER BY tournament_id",
        array_merge(array($player_id), $tournament_ids)
    ));

    if (empty($results)) {
        return null;
    }

    // Calculate cumulative statistics (same logic as series)
    $total_points = 0;
    $total_winnings = 0;
    $total_hits = 0;
    $best_finish = PHP_INT_MAX;
    $worst_finish = 0;
    $tournaments_played = count($results);
    $tournament_points_list = array();
    $finishes = array();

    foreach ($results as $result) {
        $total_points += floatval($result->points);
        $total_winnings += floatval($result->winnings);
        $total_hits += intval($result->hits);

        if ($result->finish_position < $best_finish) {
            $best_finish = $result->finish_position;
        }
        if ($result->finish_position > $worst_finish) {
            $worst_finish = $result->finish_position;
        }

        $tournament_points_list[] = floatval($result->points);
        $finishes[] = intval($result->finish_position);
    }

    $avg_finish = array_sum($finishes) / count($finishes);

    // Get player information
    $player_post = get_posts(array(
        'post_type' => 'player',
        'meta_query' => array(
            array(
                'key' => '_player_uuid',
                'value' => $player_id,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1
    ));

    $player_name = $player_id;
    $player_url = '';

    if (!empty($player_post)) {
        $player_name = $player_post[0]->post_title;
        $player_url = esc_url(get_permalink($player_post[0]->ID));
    }

    // Build overall data array
    $overall_data = array(
        'player_id' => $player_id,
        'player_name' => $player_name,
        'player_url' => $player_url,
        'tournaments_played' => $tournaments_played,
        'total_points' => $total_points,
        'total_winnings' => $total_winnings,
        'total_hits' => $total_hits,
        'best_finish' => $best_finish === PHP_INT_MAX ? 0 : $best_finish,
        'worst_finish' => $worst_finish,
        'avg_finish' => $avg_finish,
        'tournament_points' => $tournament_points_list,
        'finishes' => $finishes,
        'results_detail' => $results
    );

    // Apply formula if specified
    if ($formula_key && $formula_key !== 'direct_sum') {
        $overall_points = $this->apply_series_formula($overall_data, $formula_key);
        $overall_data['overall_points'] = $overall_points;
        $overall_data['formula_used'] = $formula_key;
    } else {
        $overall_data['overall_points'] = $total_points;
        $overall_data['formula_used'] = 'direct_sum';
    }

    // Calculate tie-breakers
    $overall_data['tie_breakers'] = $this->calculate_tie_breakers($overall_data);

    return $overall_data;
}

/**
 * Clear overall standings cache
 * Call this when tournaments are saved/deleted
 */
public function clear_overall_standings_cache() {
    global $wpdb;

    // Clear all overall standings transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_poker_overall_standings_%'"
    );
}
```

#### 2. `includes/class-css-dashboard-config.php` (MODIFY)
**After line 311**: Add overall standings section method

**Add new method**:

```php
/**
 * NEW: Overall Points Standings Section
 * Shows player rankings across all tournaments (filtered by season)
 */
private function get_overall_standings_section($filters) {
    // Get filtered tournament IDs
    $tournament_ids = $filters->get_filtered_tournament_ids();

    // Get active filters for display
    $active_filters = $filters->get_active_filters();
    $season_filter = isset($active_filters['season']) ? $active_filters['season'] : 'all';

    // Calculate standings
    $calculator = new Poker_Series_Standings_Calculator();
    $standings = $calculator->calculate_overall_standings($tournament_ids);

    if (empty($standings)) {
        // Return empty section with message
        return array(
            'id' => 'overall-standings',
            'title' => __('Overall Points Standings', 'poker-tournament-import'),
            'columns' => 1,
            'components' => array(
                array(
                    'id' => 'standings-empty',
                    'parent_section_id' => 'overall-standings',
                    'type' => 'custom',
                    'html' => '<p>' . esc_html__('No standings data available.', 'poker-tournament-import') . '</p>'
                )
            )
        );
    }

    // Build table rows
    $rows = array();
    foreach ($standings as $standing) {
        $rank_display = $standing['rank'];
        if ($standing['is_tied']) {
            $rank_display .= 'T';
        }

        // Add position indicators for top ranks
        $rank_suffix = '';
        $rank_class = '';
        if ($standing['rank'] === 1) {
            $rank_suffix = ' 🥇';
            $rank_class = ' rank-first';
        } elseif ($standing['rank'] === 2) {
            $rank_suffix = ' 🥈';
            $rank_class = ' rank-second';
        } elseif ($standing['rank'] === 3) {
            $rank_suffix = ' 🥉';
            $rank_class = ' rank-third';
        }

        // Player cell with link
        $player_cell = $standing['player_url']
            ? sprintf('<a href="%s">%s</a>', $standing['player_url'], esc_html($standing['player_name']))
            : esc_html($standing['player_name']);

        $rows[] = array(
            'cells' => array(
                '<span class="rank-cell' . esc_attr($rank_class) . '">' . esc_html($rank_display) . esc_html($rank_suffix) . '</span>',
                $player_cell,
                '<span class="points-cell">' . number_format($standing['overall_points'], 1) . '</span>',
                number_format($standing['tournaments_played']),
                $standing['best_finish'],
                number_format($standing['avg_finish'], 1),
                number_format($standing['tie_breakers']['first_places']),
                number_format($standing['tie_breakers']['top3_finishes']),
                number_format($standing['tie_breakers']['top5_finishes']),
            )
        );
    }

    return array(
        'id' => 'overall-standings',
        'title' => __('Overall Points Standings', 'poker-tournament-import'),
        'columns' => 1,
        'components' => array(
            array(
                'id' => 'overall-standings-table',
                'parent_section_id' => 'overall-standings',
                'type' => 'table',
                'sortable' => false, // Server-side sorted, no client sorting needed
                'headers' => array(
                    __('Rank', 'poker-tournament-import'),
                    __('Player', 'poker-tournament-import'),
                    __('Points', 'poker-tournament-import'),
                    __('Played', 'poker-tournament-import'),
                    __('Best', 'poker-tournament-import'),
                    __('Avg Finish', 'poker-tournament-import'),
                    __('1st', 'poker-tournament-import'),
                    __('Top 3', 'poker-tournament-import'),
                    __('Top 5', 'poker-tournament-import'),
                ),
                'rows' => $rows,
                'per_page' => 25
            )
        )
    );
}
```

### Success Criteria:

#### Automated Verification:
- [ ] Method `calculate_overall_standings()` exists in `class-series-standings.php`
- [ ] PHP syntax valid: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- [ ] Transient caching works: Check `wp_options` table for `_transient_poker_overall_standings_%` entries
- [ ] Standings section appears in dashboard config output

#### Manual Verification:
- [ ] Overall standings table appears on dashboard
- [ ] Table shows rank, player name, points, tournaments played, best finish, avg finish
- [ ] Top 3 players have medal indicators (🥇🥈🥉)
- [ ] Tied players show "T" suffix (e.g., "2T")
- [ ] Tie-breaker columns (1st, Top 3, Top 5) display correctly
- [ ] Player names link to player profiles
- [ ] Pagination works for > 25 players
- [ ] Season filter filters standings correctly
- [ ] Formula points calculation accurate (compare with known data)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Filter Persistence & User Preferences

### Overview
Implement automatic saving of user filter preferences and hook into WordPress user system.

### Changes Required:

#### 1. `includes/class-dashboard-filters.php` (MODIFY)
**Add preference saving logic to constructor**:

```php
public function __construct($user_id = null) {
    $this->user_id = $user_id ?: get_current_user_id();
    $this->filter_config = $this->get_filter_config();

    // Auto-save preferences from URL params
    $this->maybe_save_preferences();
}

/**
 * Save filter preferences if URL params present
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
```

#### 2. `poker-tournament-import.php` (MODIFY)
**Add cache invalidation hook**:

```php
// Clear overall standings cache on tournament changes
add_action('save_post_tournament', array(new Poker_Series_Standings_Calculator(), 'clear_overall_standings_cache'));
add_action('before_delete_post', array(new Poker_Series_Standings_Calculator(), 'clear_overall_standings_cache'));
```

### Success Criteria:

#### Automated Verification:
- [ ] Filter preferences saved to `wp_usermeta` table
- [ ] Preferences load correctly on subsequent page visits
- [ ] Cache clears when tournament saved/deleted

#### Manual Verification:
- [ ] Select season filter, reload page - selection persists
- [ ] Change filter, reload - new value saved
- [ ] Logout/login - filters persist per user
- [ ] Different users have different filter settings
- [ ] Create/edit tournament - standings cache invalidates
- [ ] Delete tournament - standings update reflects change

---

## Phase 4: CSS Styling & Polish

### Overview
Add final CSS styling for standings table and ensure responsive design.

### Changes Required:

#### 1. `assets/css-dashboard/components.css` (MODIFY)
**Add at end of file**:

```css
/* Overall Standings Table Styling */
.rank-cell {
    font-weight: 700;
    min-width: 60px;
}

.rank-first {
    color: #d97706; /* Gold/amber */
}

.rank-second {
    color: #64748b; /* Silver */
}

.rank-third {
    color: #b45309; /* Bronze */
}

.points-cell {
    font-weight: 700;
    color: var(--dashboard-primary);
}

/* Tie indicator */
.table-cell a {
    color: inherit;
    text-decoration: none;
}

.table-cell a:hover {
    color: var(--dashboard-primary);
    text-decoration: underline;
}

/* Responsive standings table */
@media (max-width: 768px) {
    .data-table-wrapper .table-row {
        grid-template-columns: auto 1fr;
        gap: 0.75rem;
    }

    .table-cell:nth-child(n+3) {
        font-size: 0.75rem;
    }
}
```

### Success Criteria:

#### Automated Verification:
- [ ] CSS file exists and is valid
- [ ] No console errors when viewing dashboard

#### Manual Verification:
- [ ] Standings table visually consistent with other dashboard tables
- [ ] Top 3 ranks have appropriate colors (gold, silver, bronze)
- [ ] Table responsive on mobile (columns collapse gracefully)
- [ ] Hover effects work on player name links
- [ ] Overall polish matches existing dashboard aesthetic

---

## Testing Strategy

### Unit Tests (Future)
- Filter config generation
- Active filter resolution (URL > saved > default)
- Standings calculation with mock data
- Tie-breaker logic verification

### Integration Tests
- Filter controls render correctly
- Standings calculation with actual tournament data
- Formula application produces expected results
- Cache invalidation on tournament changes

### Manual Testing Steps
1. **Filter System**:
   - Create 3+ seasons with tournaments
   - Select each season filter, verify standings update
   - Select "All Seasons", verify all tournaments included
   - Reset filters, verify defaults restored
   - Check browser back/forward navigation (URL state)

2. **Standings Display**:
   - Import tournament results with known point values
   - Verify overall standings match manual calculation
   - Test tie-breaker scenarios (players with equal points)
   - Verify medal indicators for top 3
   - Test pagination with 30+ players

3. **User Preferences**:
   - Login as User A, set filter to Season 1
   - Login as User B, set filter to Season 2
   - Logout/login as User A - verify Season 1 persists
   - Logout/login as User B - verify Season 2 persists

4. **Performance**:
   - Test with 1000+ tournaments
   - Verify caching reduces query load
   - Check page load time < 2 seconds with cache warm

## Performance Considerations

- **Caching**: Standings cached per filter combination (1 hour TTL)
- **Database**: Use `fields => 'ids'` for tournament queries to reduce memory
- **Indexes**: Ensure `player_id` and `tournament_id` indexed on `poker_tournament_players`
- **Pagination**: Server-side pagination (not client-side) for large datasets
- **Transient Invalidation**: Only clear relevant cache keys, not all transients

## Migration Notes

No database migrations required. Uses existing:
- `poker_tournament_players` table
- `wp_posts` (tournament_season)
- `wp_usermeta` (filter preferences)

## References

- Original specification: User request for overall standings + filters
- Existing standings: `includes/class-series-standings.php:14-578`
- Dashboard config: `includes/class-css-dashboard-config.php:8-315`
- Dashboard renderer: `includes/class-dashboard-renderer.php:1-315`
- CSS dashboard styles: `assets/css-dashboard/dashboard-core.css`, `assets/css-dashboard/components.css`
- Formula system: `includes/class-formula-validator.php`
- User meta persistence: WordPress `get_user_meta()`, `update_user_meta()`
