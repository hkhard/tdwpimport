# Data Model: Improve Poker Dashboard Filters

**Feature**: 008-improve-poker-dashboard-filters
**Date**: 2025-01-02
**Phase**: Phase 1 - Data Model & Contracts

## Entity Model

### Dashboard Filter Configuration

```php
array(
    'filter_key' => array(
        'type' => 'select',           // Filter input type
        'label' => string,            // Display label
        'options' => array,           // Available options
        'default' => mixed,           // Default value (null, 'all', or specific ID)
        'query_arg' => string,        // URL parameter name
    )
)
```

**Example - Season Filter**:
```php
'season' => array(
    'type' => 'select',
    'label' => 'Season',
    'options' => get_season_options(),  // Array of season_id => season_title
    'default' => null,                    // Falls back to most recent season
    'query_arg' => 'filter_season',
)
```

### User Filter Preferences

**Storage**: WordPress user meta table
**Meta Key**: `poker_dashboard_filters`
**Structure**:
```php
array(
    'season' => '123',           // Season ID from taxonomy
    'series' => '456',           // Series ID (optional)
    'status' => 'published'      // Post status (optional)
)
```

**Persistence Scope**: Per-user, persists indefinitely across sessions until changed.

### Active Filters (Runtime)

**Computed from**:
1. URL parameters (highest priority)
2. Saved user preferences (fallback)
3. Filter config defaults (final fallback)

```php
public function get_active_filters(): array {
    $saved = get_user_meta($user_id, 'poker_dashboard_filters', true) ?: array();

    $active = array();
    foreach ($filter_config as $key => $config) {
        $url_param = $_GET['filter_' . $key] ?? null;
        $saved_value = $saved[$key] ?? null;
        $default = $config['default'] ?? null;

        // Priority chain: URL > Saved > Default
        $active[$key] = $url_param ?: ($saved_value ?: $default);
    }

    return $active;
}
```

## State Transitions

```
┌─────────────┐
│ Initial Load│
│  (No State) │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│ Check Priority Chain:      │
│ 1. URL params present?     │ → Yes: Use URL params
│ 2. Saved prefs exist?      │ → No URL, Yes saved: Use saved
│ 3. Config defaults?        │ → Neither: Use defaults
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────┐
│ Apply Filters   │
│ (User submits)  │
└──────┬──────────┘
       │
       ▼
┌─────────────────────────────┐
│ Save to User Meta          │
│ maybe_save_preferences()   │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────┐
│ Redirect with   │
│ URL params      │
└─────────────────┘
```

## Edge Cases

### 1. Deleted Season

**Scenario**: User has saved preference for Season 2024, but season is deleted.

**Handling**:
```php
// In get_active_filters(), validate saved season exists
if ($saved_season && !term_exists($saved_season, 'poker_season')) {
    // Fall back to default
    $active['season'] = $default;
    // Optionally: Clear invalid saved value
    // delete_user_meta($user_id, 'poker_dashboard_filters');
}
```

**Current Implementation**: No validation. May show empty results.

**Recommendation**: Add validation with fallback to most recent season.

### 2. Browser Cache Clearing

**Scenario**: User clears browser cache/cookies.

**Behavior**: URL parameters lost, but user meta preferences remain (server-side).

**Assessment**: ✅ Correct behavior. No changes needed.

### 3. URL vs Saved Conflict

**Scenario**: User has saved "Season 2024", but URL contains `?filter_season=2025`.

**Behavior**: URL parameter takes priority (by design).

**Assessment**: ✅ Correct behavior for manual override capability.

### 4. First Visit (No Saved Prefs)

**Scenario**: New user or cleared user meta.

**Behavior**: Falls back to config default (most recent season).

**Assessment**: ✅ Correct behavior.

## Database Schema

**No new tables required.**

Uses existing WordPress user meta table:
- `wp_usermeta`
- `meta_key` = 'poker_dashboard_filters'
- `meta_value` = serialized array of filter selections

## API Contracts

### Public Methods (Poker_Dashboard_Filters class)

```php
// Get current active filters (computed from URL/saved/defaults)
public function get_active_filters(): array

// Get filter configuration for rendering
public function get_filter_config(): array

// Get available filter options (e.g., seasons list)
public function get_filter_options(string $filter_key): array

// Save user preferences (internal, called on form submit)
private function save_user_preferences(array $filters): bool

// Auto-save preferences from URL params (internal)
private function maybe_save_preferences(): void
```

### Filter Configuration

```php
public function get_filter_config(): array {
    return array(
        'season' => array(
            'type' => 'select',
            'label' => __('Season', 'poker-tournament-import'),
            'options' => $this->get_season_options(),
            'default' => $this->get_most_recent_season_id(),
        ),
        // Additional filters can be added here
    );
}
```

## Non-Functional Requirements

### Performance

- User meta query: <10ms (WordPress object cache)
- Filter priority chain: <5ms (simple array operations)
- Total additional page load time: <15ms

### Scalability

- Per-user storage: ~100 bytes per user
- No database writes on GET requests (only on filter apply)
- Reads benefit from WordPress object cache

### Security

- All filter values sanitized via `sanitize_text_field()`
- No SQL injection risk (prepared statements or term lookup)
- No XSS risk (escaped via `esc_attr()`, `esc_html()`)

### Compatibility

- WordPress 6.0+
- PHP 8.0+
- No JavaScript required (CSS-only solution)
- Mobile/touch compatible (no hover dependency)
