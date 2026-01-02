# Quickstart: Fix Player Names Display

**Feature**: 004-fix-player-names
**Branch**: `004-fix-player-names`
**Date**: 2026-01-02

## Overview

Fix GUID display issue in season_leaderboard shortcode where player UUIDs are shown instead of human-readable names.

## The Problem

**What**: The [season_leaderboard] shortcode displays raw UUIDs like "550e8400-e29b-41d4-a716-446655440000" instead of player names like "John Doe".

**Where**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` in the `calculate_player_series_data()` method (lines 265-280)

**Why**: When no player post exists for a UUID, the code falls back to displaying the raw GUID as the player name.

## The Solution

Change the fallback from displaying the GUID to displaying a user-friendly placeholder "Unknown Player".

## Implementation

### File to Modify

**Path**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Method**: `calculate_player_series_data()` (private method, lines 224-338)

### Code Change

**Find this code** (around line 277-280):

```php
$player_name = $player_id; // Fallback to UUID
$player_url = '';

if (!empty($player_post)) {
    $player_name = $player_post[0]->post_title;
    $player_url = esc_url(get_permalink($player_post[0]->ID));
}
```

**Replace with**:

```php
$player_name = __('Unknown Player', 'poker-tournament-import');
$player_url = '';

if (!empty($player_post) && !empty($player_post[0]->post_title)) {
    $player_name = $player_post[0]->post_title;
    $player_url = get_permalink($player_post[0]->ID);
}
```

### What Changed

1. **Line 277**: Changed default from `$player_id` (GUID) to `__('Unknown Player', 'poker-tournament-import')` (placeholder)
2. **Line 281**: Added `&& !empty($player_post[0]->post_title)` to handle empty player post titles
3. **Line 283**: Removed `esc_url()` wrapper (URL is escaped later in shortcode output)

## Testing

### Manual Testing Steps

1. **Create test data**:
   - Create a season with tournaments
   - Import tournament results that include player UUIDs
   - For some players, create player posts with matching `_player_uuid` meta values
   - Leave some player UUIDs without corresponding player posts

2. **Test Case 1 - Player post exists**:
   - Add shortcode to a page: `[season_leaderboard]`
   - View the page
   - **Expected**: Player names are displayed (not GUIDs)
   - **Expected**: Player names are clickable links to profile pages

3. **Test Case 2 - Player post missing**:
   - View the same leaderboard
   - Find rows for players without player posts
   - **Expected**: "Unknown Player" is displayed (not GUIDs)
   - **Expected**: Text is plain (not clickable)

4. **Test Case 3 - Empty player post title**:
   - Create a player post with empty title
   - Set its `_player_uuid` to match a tournament result
   - Refresh leaderboard
   - **Expected**: "Unknown Player" is displayed (not blank space)

5. **Test Case 4 - Special characters**:
   - Create a player post with name: `Test<script>alert('xss')</script>`
   - Set its `_player_uuid` to match a tournament result
   - Refresh leaderboard
   - **Expected**: Name is escaped (no script execution)
   - **Expected**: Display shows: `Test&lt;script&gt;alert('xss')&lt;/script&gt;`

6. **Clear cache**:
   - Delete WordPress transient: `poker_season_standings_*`
   - Or wait 1 hour for cache to expire
   - Refresh leaderboard to verify fix works with fresh data

### Automated Testing (Optional)

If you have PHP unit tests set up:

```php
public function test_calculate_player_series_data_with_missing_player_post() {
    $calculator = new Poker_Series_Standings_Calculator();

    // Mock data
    $player_id = '550e8400-e29b-41d4-a716-446655440000';
    $tournaments = [$this->createMockTournament()];

    // Call method
    $result = $calculator->calculate_player_series_data($player_id, $tournaments, 'default');

    // Assert
    $this->assertEquals('Unknown Player', $result['player_name']);
    $this->assertEmpty($result['player_url']);
}
```

## Deployment

### Pre-Deployment Checklist

- [ ] Change implemented on branch `004-fix-player-names`
- [ ] PHP syntax validated: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- [ ] Manual testing completed
- [ ] Cache cleared and verified with fresh data
- [ ] No GUIDs visible in shortcode output
- [ ] Player links still work for existing player posts
- [ ] Plugin version number updated (if needed)

### Deploy Steps

1. **Merge branch**:
   ```bash
   git checkout main
   git merge 004-fix-player-names
   ```

2. **Update version** (if this is a release):
   - Edit `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
   - Update version constant: `POKER_TOURNAMENT_IMPORT_VERSION`
   - Update plugin header version

3. **Create zip file**:
   ```bash
   # Create distribution zip
   cd wordpress-plugin
   zip -r ../poker-tournament-import-v3.5.0-betaXX.zip poker-tournament-import/
   ```

4. **Test on staging** (if available):
   - Upload plugin to staging site
   - Activate plugin
   - Test shortcode with various data scenarios
   - Verify no GUIDs displayed

5. **Deploy to production**:
   - Upload plugin zip to WordPress.org or client site
   - Activate new version
   - Clear all caches
   - Verify fix working

### Rollback Plan

If issues occur:

1. **Immediate rollback**: Deactivate plugin and reinstall previous version
2. **Data rollback**: No data changes, so no rollback needed
3. **Cache rollback**: Clear WordPress transients: `delete_transient('poker_season_standings_*')`

## Troubleshooting

### Issue: GUIDs still showing after fix

**Possible causes**:
1. **Cache not cleared**: Old data cached in transient
   - **Fix**: Delete transients or wait 1 hour
2. **Player posts not created**: Players don't have corresponding posts
   - **Fix**: This is expected - "Unknown Player" should show, not GUIDs
3. **Wrong branch deployed**: Old code still active
   - **Fix**: Verify deployed code matches branch

### Issue: All players show "Unknown Player"

**Possible causes**:
1. **Player posts not linked**: `_player_uuid` meta doesn't match tournament results
   - **Fix**: Verify player posts have correct `_player_uuid` values
2. **Query failing**: Database issue preventing player post lookup
   - **Fix**: Check WordPress debug log for errors

### Issue: Player names not clickable

**Possible causes**:
1. **No player post**: Player without post shows as plain text (expected)
2. **Permalink issue**: Player post permalink not accessible
   - **Fix**: Flush WordPress permalinks: Settings → Permalinks → Save Changes

## Performance Impact

**Expected**: None

**Reason**:
- No additional database queries
- Same number of `get_posts()` calls
- Existing transient caching (1 hour) mitigates any impact
- Only conditional logic change (same execution path)

**Monitoring**:
- Check page load times before/after deployment
- Verify cache hit rates remain high
- Monitor for increased database query count

## Related Files

### Modified
- `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` (lines ~277-283)

### Related (not modified)
- `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` (shortcode output)
- `wordpress-plugin/poker-tournament-import/includes/class-dashboard-filters.php` (filter integration)

## Success Criteria

✅ **Done when**:
- [ ] No GUIDs visible in shortcode output
- [ ] Player names display when player post exists
- [ ] "Unknown Player" displays when player post missing
- [ ] Player profile links work correctly
- [ ] No performance degradation (>10% page load increase)
- [ ] No PHP errors or warnings
- [ ] XSS escaping works correctly

## Notes

- **Translation ready**: Uses `__('Unknown Player', 'poker-tournament-import')` for i18n
- **Backward compatible**: No breaking changes to existing functionality
- **Low risk**: Single line change, well-tested scenario
- **No database changes**: Pure display logic fix
