# Quickstart: Season Leaderboard Enhancement

**Feature**: Always show all players when `show_details="true"`
**Branch**: `011-season-leaderboard-all-players`
**Files**: 1 file modified, 3 lines changed

## The Change

**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
**Lines**: 2443-2446

### Before
```php
// If showing details, show all players unless explicitly limited
if ($show_details && !isset($atts['limit'])) {
    $limit = -1; // Show all players
}
```

### After
```php
// Detailed view always shows all players, regardless of limit parameter
if ($show_details) {
    $limit = -1; // Show all players
}
```

**What changed**: Removed the `!isset($atts['limit'])` condition so detailed views ALWAYS show all players.

## Testing Checklist

### Local Testing (Before Production)

1. **Standard view still works**
   - Use `[season_leaderboard season_id="X"]`
   - Verify: Shows top 20 players only

2. **Standard view with custom limit**
   - Use `[season_leaderboard season_id="X" limit="10"]`
   - Verify: Shows top 10 players only

3. **Detailed view without limit**
   - Use `[season_leaderboard season_id="X" show_details="true"]`
   - Verify: Shows ALL players with detail columns

4. **Detailed view WITH limit (THE FIX)**
   - Use `[season_leaderboard season_id="X" show_details="true" limit="10"]`
   - Verify: Shows ALL players (limit ignored), detail columns present

5. **Edge case: Empty season**
   - Use `[season_leaderboard season_id="999" show_details="true"]`
   - Verify: Shows "No players found" message

6. **Edge case: Large season (100+ players)**
   - Use `[season_leaderboard season_id="X" show_details="true"]` on season with 100+ players
   - Verify: All players load, page renders in <5 seconds

### Production Testing

After deploying to www.oldertardfello.ws:

1. Test TC-004 on production season
2. Verify performance with 100+ player season
3. Check browser console for errors
4. Confirm page load time <5 seconds

## Deployment Steps

### 1. Code Changes
```bash
# Edit the file
wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
# Lines 2443-2446: Remove !isset($atts['limit']) condition
```

### 2. Syntax Check
```bash
php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
```
Expected: `No syntax errors detected`

### 3. Update Version
```bash
# Edit poker-tournament-import.php
# Update version from 3.6.0 to 3.6.1
```

### 4. Create Distribution ZIP
```bash
cd wordpress-plugin/poker-tournament-import
zip -r ../../poker-tournament-import-v3.6.1.zip .
```

### 5. Deploy to Production
- Upload `poker-tournament-import-v3.6.1.zip` to production
- Extract to `wp-content/plugins/`
- Activate plugin in WordPress admin

### 6. Clear Caches
```bash
# Via SSH or WP-CLI
wp cache flush --allow-root
wp transient delete --allow-root
```

### 7. Verify
- Load a season page with `[season_leaderboard show_details="true"]`
- Confirm all players are displayed

## Rollback Plan

If issues occur:

1. **Immediate rollback**: Deactivate v3.6.1, reactivate v3.6.0
2. **Alternative**: Revert the 3-line change in class-shortcodes.php

## Expected Behavior Changes

### For Site Visitors
**No change**: Standard leaderboard view still shows top 20 by default

### For Administrators
**Improved**: Detailed view now shows complete season standings
- Before: `show_details="true" limit="10"` showed 10 players
- After: `show_details="true" limit="10"` shows ALL players (limit ignored)

### Performance
- **Small impact**: Larger pages render more HTML
- **Acceptable**: WordPress handles 200+ row tables easily
- **Cached views**: Subsequent loads use transient cache (fast)

## Communication

### Release Notes (v3.6.1)
```
ENHANCEMENT: Season Leaderboard Detailed View

The season_leaderboard shortcode with show_details="true" now
displays ALL registered players in a season, regardless of the
limit parameter.

Before: [season_leaderboard show_details="true" limit="10"]
        → Showed 10 players with details

After:  [season_leaderboard show_details="true" limit="10"]
        → Shows ALL players with details (limit ignored)

Use case: Administrators can now see complete season standings
with detailed statistics for awards and reporting.

Standard view (show_details="false") behavior unchanged.
```

## Troubleshooting

### Issue: Page load slow with 100+ players
**Cause**: Large HTML table rendering
**Solution**: Expected behavior. Use standard view for faster loads.

### Issue: Still showing limited players in detailed view
**Cause**: Browser cache or WordPress transient cache
**Solution**:
1. Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
2. Clear WordPress transients
3. Verify code change deployed

### Issue: Syntax error after change
**Cause**: Typo in code edit
**Solution**: Restore from backup, reapply 3-line change carefully

## Next Steps

After deployment:
1. Monitor site performance
2. Gather user feedback
3. Consider adding pagination for very large seasons (if requested)
