# Quickstart: Improve Poker Dashboard Filters

**Feature**: 008-improve-poker-dashboard-filters
**Branch**: `008-improve-poker-dashboard-filters`
**Date**: 2025-01-02

## Overview

Fix two UX issues with the poker dashboard filter interface:
1. **Button Visibility**: "Apply Filters" button should be always visible (currently reported as hover-only)
2. **Filter Persistence**: Filter selection should persist across page refreshes during session

## Investigation Summary

### Button Visibility Issue

**Finding**: CSS inspection shows NO rules hiding the button. The button should be visible by default.

**Hypothesis**: User may be seeing cached CSS, or there's a JavaScript injection not found in codebase search.

**Action Plan**:
1. Inspect live page with browser DevTools to check computed styles
2. Search for any inline styles or JavaScript modifying button visibility
3. Verify CSS file is loading (check Network tab)

### Filter Persistence Implementation

**Finding**: Persistence ALREADY IMPLEMENTED via:
- `Poker_Dashboard_Filters::maybe_save_preferences()` - Saves on form submit
- `Poker_Dashboard_Filters::get_active_filters()` - Loads with priority chain
- WordPress user meta storage - Persists across sessions

**Action Plan**:
1. Test current implementation to verify it works
2. If broken, identify why (e.g., method not being called)
3. Fix if needed

## Implementation Steps

### Phase 1: Investigation (Do First)

```bash
# 1. Create feature branch (already done)
git checkout 008-improve-poker-dashboard-filters

# 2. Copy updated files to test site
cp wordpress-plugin/poker-tournament-import/assets/css-dashboard/filters.css \
   "/Users/hkh/Library/Application Support/Local/sites/poker-tournament-dev/local-site/app/public/wp-content/plugins/poker-tournament-import/assets/css-dashboard/"

# 3. Clear all caches
/Users/hkh/Library/Application\ Support/Local/ssh-entry/hNPsf2SE_.sh -c 'wp cache flush --allow-root && wp transient delete --allow-root'

# 4. Inspect live page with browser DevTools
# - Open poker dashboard page
# - Right-click "Apply Filters" button → Inspect
# - Check Computed styles for opacity, visibility, display
# - Check Network tab to verify filters.css is loading
```

### Phase 2: Button Visibility Fix (If Issue Found)

**If CSS rule hiding button found**:
```bash
# Edit filters.css to remove hiding rule
vi wordpress-plugin/poker-tournament-import/assets/css-dashboard/filters.css

# Remove lines like:
# .filter-actions .button { opacity: 0; }
# .filter-actions:hover .button { opacity: 1; }
```

**If JavaScript hiding button found**:
```bash
# Find and remove/hide script
grep -r "filter-actions" wordpress-plugin/poker-tournament-import/assets/js/
```

### Phase 3: Filter Persistence Verification

```bash
# Test current implementation
# 1. Select "Season 2024" from dropdown
# 2. Click "Apply Filters"
# 3. Refresh page (F5)
# 4. Verify dropdown still shows "Season 2024"

# If not persisting, check:
# - Is maybe_save_preferences() being called?
# - Is user meta being saved?
wp user meta get 1 poker_dashboard_filters --allow-root

# - Is get_active_filters() loading saved prefs?
```

### Phase 4: Edge Case Handling (Optional Enhancement)

```php
// In class-dashboard-filters.php, add validation:

/**
 * Validate saved season still exists
 */
private function validate_saved_season($season_id) {
    if (!term_exists($season_id, 'poker_season')) {
        // Fall back to most recent season
        return $this->get_most_recent_season_id();
    }
    return $season_id;
}
```

## Files Modified

### If Button Issue Found

```
wordpress-plugin/poker-tournament-import/assets/css-dashboard/filters.css
```

### If Persistence Enhancement Needed

```
wordpress-plugin/poker-tournament-import/includes/class-dashboard-filters.php
```

## Testing Checklist

### Button Visibility (P1)
- [ ] Load page and verify "Apply Filters" button is immediately visible
- [ ] Move mouse away from filter area - button remains visible
- [ ] Test on mobile/touch device - button visible without hover
- [ ] Check browser DevTools: button has `opacity: 1`, `visibility: visible`

### Filter Persistence (P1)
- [ ] Select season → Apply → Refresh → Verify selection persists
- [ ] Select season → Apply → Navigate away → Back → Verify selection persists
- [ ] Change to different season → Apply → Verify new selection persists
- [ ] Select "All Seasons" → Apply → Verify selection persists

### Edge Cases (Optional)
- [ ] Select season, delete season, refresh page → Falls back to most recent season
- [ ] Clear browser cache → Saved preferences still apply
- [ ] First visit (no saved prefs) → Shows most recent season by default

## Rollback Plan

If changes break functionality:

```bash
# Discard changes
git checkout -- .

# Switch back to main
git checkout main

# Delete feature branch
git branch -D 008-improve-poker-dashboard-filters
```

## Success Criteria

- ✅ "Apply Filters" button visible on page load with no user interaction
- ✅ Filter selection persists across page refresh with 100% reliability
- ✅ Zero user confusion about how to apply filters
- ✅ Mobile/touch devices work correctly
- ✅ No regression in existing filter functionality

## Notes

- Current CSS (filters.css:75-85) shows NO hiding rules for button
- Current PHP (class-dashboard-filters.php:157-177) already implements persistence
- Issue may be cache-related or in JavaScript not yet found
- Test on live site before making code changes
