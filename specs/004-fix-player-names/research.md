# Research: Fix Player Names Display in Season Leaderboard

**Feature**: 004-fix-player-names
**Date**: 2026-01-02
**Status**: Complete

## Overview

Research into fixing the issue where GUIDs (UUIDs) are displayed instead of player names in the season_leaderboard shortcode output.

## Current Implementation Analysis

### Problem Location

The issue is in `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` in the `calculate_player_series_data()` method (lines 224-338).

**Current Code (lines 265-278)**:
```php
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

$player_name = $player_id; // Fallback to UUID
$player_url = '';

if (!empty($player_post)) {
    $player_name = $player_post[0]->post_title;
    $player_url = esc_url(get_permalink($player_post[0]->ID));
}
```

**Problem**: When `get_posts()` returns empty (no player post found), the code falls back to using the raw `$player_id` (which is a GUID) as the player name.

### Data Flow

1. Tournament results stored in `wp_poker_tournament_players` table with `player_id` as GUID
2. `calculate_season_standings()` calls `calculate_player_series_data()` for each player
3. `calculate_player_series_data()` attempts to find player post by `_player_uuid` meta key
4. If no player post exists, GUID is used as display name

## Technical Decisions

### Decision 1: Placeholder Text for Missing Players

**Chosen**: Use "Unknown Player" as placeholder text

**Rationale**:
- Clear, user-friendly placeholder that indicates missing data
- Consistent with WordPress convention of showing "Anonymous" or similar for missing user data
- More informative than showing a blank or technical UUID
- Allows leaderboard to remain functional even with incomplete data

**Alternatives Considered**:
1. **"Player Not Found"**: More verbose, but less friendly
2. **Blank/Empty String**: Would create visual gaps in the table, making it hard to read
3. **"N/A"**: Too technical, less clear
4. **Skip the player entirely**: Would break rankings and point totals

### Decision 2: Player Post Query Strategy

**Chosen**: Keep existing `get_posts()` with meta_query approach

**Rationale**:
- Already implemented and working for finding player posts
- WordPress standard method for querying posts by meta key
- Properly handles caching and performance
- No performance concerns given the existing transient caching in `calculate_season_standings()`

**Alternatives Considered**:
1. **Direct database query**: Would bypass WordPress cache layers, less efficient
2. **WP_Query object**: More overhead than `get_posts()` for simple queries
3. **Batch query all players at once**: Would require significant refactoring, minimal performance gain due to caching

### Decision 3: Handling Empty Post Titles

**Chosen**: Treat empty `post_title` as missing player (use placeholder)

**Rationale**:
- An empty title is effectively the same as a missing player post from a user perspective
- Prevents displaying blank or whitespace-only names in the leaderboard
- Maintains data integrity - player posts should always have titles

**Implementation**:
```php
if (!empty($player_post) && !empty($player_post[0]->post_title)) {
    $player_name = $player_post[0]->post_title;
    $player_url = esc_url(get_permalink($player_post[0]->ID));
} else {
    $player_name = __('Unknown Player', 'poker-tournament-import');
    $player_url = '';
}
```

### Decision 4: Multiple Player Posts for Same UUID

**Chosen**: Use first result (existing behavior with `'posts_per_page' => 1`)

**Rationale**:
- Existing query already limits to 1 result
- First result is arbitrary but deterministic
- Multiple player posts for same UUID indicates data integrity issue that should be fixed at source
- No performance impact from maintaining existing behavior

**Note**: This could be logged as a warning for data cleanup, but is out of scope for this fix.

### Decision 5: Security - XSS Prevention

**Chosen**: Use WordPress escaping functions consistently

**Rationale**:
- `esc_html()` for player names when outputting as HTML
- `esc_url()` for player profile links
- `esc_attr()` for attribute values
- Follows WordPress Coding Standards for security
- Prevents XSS attacks from malicious player names

**Implementation**: The shortcode already uses `esc_html()` in its output, so no additional escaping needed in the data retrieval layer.

## Implementation Strategy

### File Changes

**Primary File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Method to Modify**: `calculate_player_series_data()` (lines 224-338)

**Changes Required**:
1. Replace GUID fallback with "Unknown Player" placeholder
2. Add empty title check
3. Use WordPress translation function for placeholder text

### Code Changes

**Before** (lines 277-280):
```php
$player_name = $player_id; // Fallback to UUID
$player_url = '';

if (!empty($player_post)) {
    $player_name = $player_post[0]->post_title;
    $player_url = esc_url(get_permalink($player_post[0]->ID));
}
```

**After**:
```php
$player_name = __('Unknown Player', 'poker-tournament-import');
$player_url = '';

if (!empty($player_post) && !empty($player_post[0]->post_title)) {
    $player_name = $player_post[0]->post_title;
    $player_url = get_permalink($player_post[0]->ID);
}
```

### Testing Strategy

1. **Unit Testing**: Test `calculate_player_series_data()` with:
   - Valid player post with title
   - Missing player post (GUID only)
   - Player post with empty title
   - Multiple player posts for same UUID

2. **Integration Testing**: Test full shortcode output with:
   - Season with complete player data
   - Season with missing player posts
   - Season with mixed data (some players with posts, some without)
   - Verify HTML escaping works correctly

3. **Performance Testing**:
   - Measure page load time before and after change
   - Verify transient caching still works
   - Confirm no significant performance degradation

## Edge Cases Handled

1. **No player post found**: Show "Unknown Player" placeholder
2. **Empty player post title**: Show "Unknown Player" placeholder
3. **Multiple player posts for UUID**: Use first result
4. **Special characters in names**: Existing `esc_html()` in shortcode handles this
5. **Very long player names**: Existing CSS handles text wrapping
6. **Players with no tournament results**: Already filtered out in `get_series_players()`

## Performance Impact

**Expected**: Minimal to none

**Reasoning**:
- No additional database queries introduced
- Existing transient caching (1 hour) in `calculate_season_standings()` mitigates repeated lookups
- Player post query already uses WordPress object cache
- Change is purely conditional logic (same number of queries)

## Compatibility

- **PHP Version**: Compatible with PHP 8.0+ (no new features used)
- **WordPress Version**: Compatible with WordPress 6.0+ (using standard WP functions)
- **Backward Compatibility**: Fully backward compatible - only changes display fallback behavior
- **Existing Data**: No database migrations required

## Security Considerations

- **XSS Prevention**: Player names are escaped in shortcode output using `esc_html()`
- **SQL Injection**: Using prepared statements via `$wpdb->prepare()`
- **Authorization**: Shortcode respects WordPress user capabilities (no change)
- **Nonce Verification**: Not applicable for display-only change

## Internationalization

Placeholder text uses WordPress translation function:
```php
__('Unknown Player', 'poker-tournament-import')
```

This allows for future translations if needed.

## Conclusion

The fix is straightforward and low-risk:
1. Single line change to replace GUID fallback with placeholder
2. Add empty title check for robustness
3. No performance impact due to existing caching
4. Fully backward compatible
5. Follows WordPress best practices

**Risk Level**: Low
**Test Coverage Required**: Medium (integration testing recommended)
**Deployment Strategy**: Standard deployment (no special migration needed)
