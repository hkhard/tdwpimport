# Research: Improve Poker Dashboard Filters

**Feature**: 008-improve-poker-dashboard-filters
**Date**: 2025-01-02
**Phase**: Phase 0 - Technical Research

## Research Questions

1. **Button Visibility**: Why is the Apply Filters button only visible on hover? Is this CSS or JavaScript controlled?
2. **Filter Persistence**: Does the current implementation already support session-based persistence via user meta?
3. **Best Practices**: What are WordPress/UX best practices for always-visible action buttons in filter interfaces?
4. **Edge Cases**: How should the system handle deleted seasons, changing defaults, and browser cache clearing?

## Current Implementation Analysis

### Apply Filter Button (class-dashboard-filters.php:234-256)

```php
<div class="filter-actions">
    <button type="submit" class="button button-primary">
        <?php esc_html_e('Apply Filters', 'poker-tournament-import'); ?>
    </button>
    <?php if ($has_active): ?>
        <a href="<?php echo esc_url(remove_query_arg(array_keys($this->get_filter_param_names()), $current_url)); ?>" class="button">
            <?php esc_html_e('Reset', 'poker-tournament-import'); ?>
        </a>
    <?php endif; ?>
</div>
```

**Finding**: Button HTML uses standard WordPress admin button classes (`button button-primary`). No special visibility attributes in PHP.

### CSS Styling (filters.css:75-85)

```css
.filter-actions .button-primary {
    background: var(--dashboard-primary);
    color: white;
    border: none;
}

.filter-actions .button-primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
```

**Finding**: CSS only defines hover *effects* (color change, transform), not hover *visibility*. The button should be visible by default.

**Possible Issue**: There may be additional CSS hiding the button, or JavaScript controlling visibility. Need to search for:
- `opacity: 0` on button
- `visibility: hidden` on button
- `display: none` on button
- JavaScript hiding/showing button

### Filter State Management (class-dashboard-filters.php:94-140)

**Current Implementation**:

```php
public function get_active_filters() {
    // Get saved user preferences
    $saved = get_user_meta($this->user_id, 'poker_dashboard_filters', true);
    if (!is_array($saved)) {
        $saved = array();
    }

    $active = array();

    foreach ($this->filter_config as $filter_key => $config) {
        // Priority: URL params > saved preferences > defaults
        $url_param = isset($_GET['filter_' . $filter_key])
            ? sanitize_text_field($_GET['filter_' . $filter_key])
            : null;

        $saved_value = isset($saved[$filter_key]) ? $saved[$filter_key] : null;
        $default = isset($config['default']) ? $config['default'] : null;

        $active[$filter_key] = $url_param ?: ($saved_value ?: $default);
    }

    return $active;
}
```

**Finding**: The system already has a sophisticated persistence mechanism:
1. Reads from WordPress user meta (`poker_dashboard_filters`)
2. Falls back through: URL params → saved preferences → defaults
3. This should already handle persistence across page refreshes

**Potential Issues**:
- Are preferences being SAVED after application?
- Is the filter CONFIG correctly setting the default to "most recent"?
- Does the dropdown visually reflect the saved value?

## Best Practices Research

### Always-Visible Action Buttons

**WordPress Admin Pattern**: WordPress admin interfaces use button-primary for primary actions and keep them always visible. Hover-only visibility is considered an anti-pattern.

**Accessibility (WCAG AA)**: Buttons must not rely on hover for visibility, as this discriminates against:
- Keyboard-only users
- Touch device users
- Screen reader users

**Recommended Approach**:
- Remove any CSS/JS hiding the button
- Ensure button has full opacity at all times
- Consider visual prominence (bold color, clear label)

### Filter Persistence Patterns

**WordPress User Meta**: The current approach using `get_user_meta()` and `update_user_meta()` is correct for per-user preferences.

**Session vs Permanent**: User meta persists across sessions indefinitely. For true session-only persistence, would use:
- WordPress transients (with user-specific keys)
- Browser localStorage/ sessionStorage

**Current Implementation Assessment**: The user meta approach is appropriate for this use case. Users likely want their filter preference to persist across sessions, not just during a single session.

## Technical Unknowns Requiring Investigation

1. **Button Visibility Control**: Need to grep the codebase for any CSS/JS hiding the Apply button:
   ```bash
   grep -r "opacity.*0" assets/css-dashboard/
   grep -r "visibility.*hidden" assets/css-dashboard/
   grep -r "filter-actions.*button" assets/js/
   ```

2. **Filter Save Logic**: Need to verify that `save_user_preferences()` is called when filters are applied:
   ```bash
   grep -A 20 "save_user_preferences" includes/class-dashboard-filters.php
   ```

3. **Dropdown Selected State**: Need to ensure the dropdown visually shows the active filter:
   ```bash
   grep -B 5 -A 5 "selected.*filter" includes/class-dashboard-filters.php
   ```

## Edge Case Analysis

### Deleted Season Scenario

**Current Behavior**: If user has "Season 2024" saved, but Season 2024 is deleted:
- `get_active_filters()` will return the deleted season ID
- Query will return no results (empty leaderboard)
- User sees empty table with no explanation

**Recommended Handling**:
1. In `get_active_filters()`, validate that saved season still exists
2. If not, fall back to default (most recent season)
3. Optionally show admin notice: "Your previously selected season is no longer available"

### Browser Cache Clearing

**Current Behavior**: If user clears browser cache:
- URL parameters are lost
- User meta preferences remain (stored server-side)
- System falls back to saved preferences or defaults

**Assessment**: This is the correct behavior. No changes needed.

### Changing Defaults

**Scenario**: If new season becomes "most recent", should existing users see the new season or their saved selection?

**Recommended Behavior**: Saved preferences should take priority over defaults. Only fall back to default if:
- No saved preference exists (first visit)
- Saved preference is invalid (season deleted)

## Implementation Strategy

### Phase 1: Button Visibility Fix

**Approach**: Search and destroy any CSS/JS hiding the button
1. Grep for opacity/visibility/display rules on `.filter-actions .button`
2. Remove or modify hiding rules
3. Test on desktop and mobile

**Expected Complexity**: LOW - likely 1-2 CSS rules to remove

### Phase 2: Filter Persistence Verification

**Approach**: Verify current implementation works correctly
1. Check if `save_user_preferences()` is called on form submit
2. Check if dropdown `selected` attribute reflects active filter
3. Test: select season → apply → refresh → verify selection persists

**Expected Complexity**: LOW to MEDIUM - may need minor tweaks

### Phase 3: Edge Case Handling (Optional)

**Approach**: Add validation for deleted seasons
1. Add season existence check in `get_active_filters()`
2. Fall back to default if saved season is invalid
3. Add admin notice for deleted season scenario

**Expected Complexity**: MEDIUM - requires database validation logic

## Recommendations

1. **Immediate Action**: Fix button visibility (P1 UX issue, accessibility violation)
2. **Verify Persistence**: Test current implementation to confirm it works
3. **Optional Enhancement**: Add edge case handling for better UX

## Next Steps

1. Run codebase search for button visibility rules
2. Inspect `save_user_preferences()` method implementation
3. Verify dropdown selected state logic
4. Create data-model.md and contracts based on findings

