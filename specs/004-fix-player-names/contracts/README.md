# Contracts: Fix Player Names Display

**Feature**: 004-fix-player-names
**Date**: 2026-01-02

## Overview

This feature is a bug fix with no new API contracts or interfaces. The changes are internal to the `Poker_Series_Standings_Calculator` class and do not expose any new endpoints, methods, or data structures.

## No API Contracts

This feature does not introduce:
- ❌ New REST API endpoints
- ❌ New GraphQL queries/mutations
- ❌ New WordPress shortcodes
- ❌ New AJAX handlers
- ❌ New admin pages or UI controls
- ❌ New webhook endpoints
- ❌ New public methods or functions

## Modified Internal Contract

### Method: `Poker_Series_Standings_Calculator::calculate_player_series_data()`

**Location**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:224-338`

**Visibility**: `private` (internal implementation detail)

**Contract Change**: Return value modification for edge cases

#### Before (Buggy Behavior)

```php
/**
 * Calculate series data for a single player
 *
 * @param string $player_id Player UUID
 * @param array $tournaments Tournament post objects
 * @param string $formula_key Formula key for calculations
 * @return array|null Player data or null if no results found
 */
private function calculate_player_series_data($player_id, $tournaments, $formula_key) {
    // ... calculation logic ...

    // BUG: Falls back to GUID if no player post found
    $player_name = $player_id; // e.g., "550e8400-e29b-41d4-a716-446655440000"
    $player_url = '';

    if (!empty($player_post)) {
        $player_name = $player_post[0]->post_title;
        $player_url = esc_url(get_permalink($player_post[0]->ID));
    }

    // ... rest of method ...
}
```

**Return Value** (player_name field):
- Type: `string`
- Possible values:
  - Player post title (if player post exists)
  - **Player UUID/GUID** ← BUG (if player post missing)

#### After (Fixed Behavior)

```php
/**
 * Calculate series data for a single player
 *
 * @param string $player_id Player UUID
 * @param array $tournaments Tournament post objects
 * @param string $formula_key Formula key for calculations
 * @return array|null Player data or null if no results found
 */
private function calculate_player_series_data($player_id, $tournaments, $formula_key) {
    // ... calculation logic ...

    // FIX: Use placeholder if no player post found or title empty
    $player_name = __('Unknown Player', 'poker-tournament-import');
    $player_url = '';

    if (!empty($player_post) && !empty($player_post[0]->post_title)) {
        $player_name = $player_post[0]->post_title;
        $player_url = get_permalink($player_post[0]->ID);
    }

    // ... rest of method ...
}
```

**Return Value** (player_name field):
- Type: `string`
- Possible values:
  - Player post title (if player post exists and has title)
  - **"Unknown Player"** (translated) ← FIX (if player post missing or empty)

### Breaking Changes

**None**: This is an internal private method with no public API contract.

**Backward Compatibility**: Fully maintained
- Return value structure unchanged
- Only the default value for `player_name` field changes
- All calling code remains compatible

## WordPress Shortcode Behavior

### Shortcode: `[season_leaderboard]`

**Location**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2426-2599`

**Change**: Visual output only (no shortcode API changes)

#### Parameters (No Changes)

```php
[
    'formula'      => '',           // Use default formula if empty
    'show_details' => 'false',      // Hide tie-breaker columns by default
    'show_export'  => 'true',       // Show export/print buttons
    'limit'        => '20',         // Show top 20 players
]
```

#### Output Changes

**Before**:
```html
<td>
    <a href="/player/123/">550e8400-e29b-41d4-a716-446655440000</a>
</td>
```

**After**:
```html
<td>
    <a href="/player/123/">John Doe</a>
</td>
<!-- OR for missing player post: -->
<td>
    Unknown Player
</td>
```

## Testing Contracts

### Expected Behavior Test Cases

#### Test Case 1: Player Post Exists

**Given**:
- Season with tournament results
- Player with UUID "abc-123" has results
- Player post exists with _player_uuid = "abc-123"
- Player post has title "Jane Smith"

**When**:
- Render [season_leaderboard] shortcode

**Then**:
- Player row displays "Jane Smith"
- Player name is linked to player profile page
- No GUID visible in HTML output

#### Test Case 2: Player Post Missing

**Given**:
- Season with tournament results
- Player with UUID "def-456" has results
- No player post exists with _player_uuid = "def-456"

**When**:
- Render [season_leaderboard] shortcode

**Then**:
- Player row displays "Unknown Player"
- Player name is plain text (not linked)
- No GUID visible in HTML output

#### Test Case 3: Player Post Has Empty Title

**Given**:
- Season with tournament results
- Player with UUID "ghi-789" has results
- Player post exists with _player_uuid = "ghi-789"
- Player post has empty post_title

**When**:
- Render [season_leaderboard] shortcode

**Then**:
- Player row displays "Unknown Player"
- Player name is plain text (not linked)
- No GUID visible in HTML output

#### Test Case 4: Multiple Player Posts for Same UUID

**Given**:
- Season with tournament results
- Player with UUID "jkl-012" has results
- Multiple player posts exist with _player_uuid = "jkl-012"
- First post has title "Player One"
- Second post has title "Player Two"

**When**:
- Render [season_leaderboard] shortcode

**Then**:
- Player row displays "Player One" (first result)
- Player name is linked to first player post
- No GUID visible in HTML output

#### Test Case 5: Special Characters in Name

**Given**:
- Player post exists with title "José María <script>alert('xss')</script>"

**When**:
- Render [season_leaderboard] shortcode

**Then**:
- Player name is escaped: "José María &lt;script&gt;alert('xss')&lt;/script&gt;"
- No XSS execution possible
- Safe HTML output

## Performance Contracts

### Expected Performance (No Changes)

**Operation**: Render season_leaderboard shortcode

**Metrics**:
- First load (cold cache): 200-500ms (depends on player count)
- Subsequent loads (warm cache): 10-50ms (transient cache hit)
- Database queries: 0 (cache hit) or N+1 (cache miss, where N = player count)
- Memory usage: <5MB for typical season (20-50 players)

**Acceptable Degradation**: +10% page load time maximum
**Expected Impact**: 0% (same number of queries, only conditional logic change)

## Security Contracts

### XSS Prevention (No Changes)

**All player name output MUST be escaped**:
```php
echo esc_html($player->player_name);
```

**All player URLs MUST be escaped**:
```php
echo esc_url($player->player_url);
```

**No unescaped output allowed**:
- ❌ `echo $player->player_name;` (VULNERABLE)
- ✅ `echo esc_html($player->player_name);` (SAFE)

### SQL Injection Prevention (No Changes)

**All database queries MUST use prepared statements**:
```php
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE player_id = %s",
    $player_id
));
```

## Summary

**Contract Impact**: Minimal to None
- No new public APIs
- No modified public interfaces
- Internal implementation detail only
- Fully backward compatible
- No breaking changes

**Risk Level**: Low
- Single line code change
- No new dependencies
- No new failure modes
- Easy to test and verify
