# Quickstart: Season Leaderboard Formula & Stats Enhancement

**Feature**: 007-season-leaderboard-fix
**Branch**: `007-season-leaderboard-fix`
**Date**: 2025-01-02

## Overview

Fix the season leaderboard shortcode to display formula-calculated points and add bubble/last/hits statistics.

**Time Estimate**: 2-3 hours
**Complexity**: Low (bug fix + display enhancement)

## Prerequisites

1. **WordPress Environment**: Local WordPress instance with plugin installed
2. **Test Data**: Season with tournaments and player results
3. **PHP 8.0+**: Development environment supports PHP 8.0+ syntax
4. **Access**: SSH access to WordPress at `/Users/hkh/Library/Application Support/Local/ssh-entry/hNPsf2SE_.sh`

## Quick Start

### 1. Verify the Bug

**Location**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2540`

Current (buggy) code:
```php
<td><?php echo number_format($player['total_points'], 1); ?></td>
```

View any season leaderboard page - the "Points" column shows total points (sum of all tournaments) instead of formula-calculated points.

### 2. Implementation Steps

#### Step 1: Fix Points Display (5 minutes)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Line 2540**: Change `total_points` to `series_points`

```php
// Before:
<td><?php echo number_format($player['total_points'], 1); ?></td>

// After:
<td><?php echo number_format($player['series_points'], 1); ?></td>
```

**Test**: Refresh leaderboard - points should now match formula calculation

#### Step 2: Add Statistics Calculation (45 minutes)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Location**: `calculate_player_series_data()` method (lines 224-338)

**Changes**:

1. **After line 246** (after `$finishes = array();`), add initialization:
```php
$bubble_count = 0;
$last_place_count = 0;
```

2. **Before the foreach loop** (around line 248), pre-calculate max positions:
```php
// Get max finish position for each tournament in the season
$max_positions = array();
$table_name = $wpdb->prefix . 'poker_tournament_players';

foreach ($tournaments as $tournament) {
    $uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
    if ($uuid) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $max_pos = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(finish_position) FROM $table_name WHERE tournament_id = %s",
            $uuid
        ));
        $max_positions[$uuid] = $max_pos ? intval($max_pos) : 0;
    }
}
```

3. **Inside the foreach loop** (after line 266, after `$total_hits += ...`), add bubble/last detection:
```php
// Detect bubble finish (one position outside paid spots)
$tournament_post_id = $this->get_tournament_post_id($result->tournament_id);
$paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);

if ($paid_positions && $result->finish_position == $paid_positions + 1) {
    $bubble_count++;
}

// Detect last place finish
if (!empty($max_positions[$result->tournament_id]) &&
    $result->finish_position == $max_positions[$result->tournament_id]) {
    $last_place_count++;
}
```

4. **In the $series_data array** (around line 294), add new fields:
```php
'series_data' => array(
    // ... existing fields ...
    'total_hits' => $total_hits,
    'bubble_count' => $bubble_count,       // NEW
    'last_place_count' => $last_place_count, // NEW
    // ... rest of fields
)
```

5. **Helper method** (add at end of class):
```php
/**
 * Get tournament post ID from tournament UUID
 */
private function get_tournament_post_id($tournament_uuid) {
    $posts = get_posts(array(
        'post_type' => 'tournament',
        'meta_query' => array(
            array(
                'key' => 'tournament_uuid',
                'value' => $tournament_uuid,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));

    return !empty($posts) ? $posts[0] : 0;
}
```

#### Step 3: Update Cache Key (5 minutes)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Line 95** (in `calculate_season_standings()`):

```php
// Before:
$cache_key = 'poker_season_standings_' . $season_id . '_' . $formula_key;

// After:
$cache_key = 'poker_season_standings_' . $season_id . '_' . $formula_key . '_v2';
```

#### Step 4: Display New Columns (30 minutes)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Add table headers** (after line 2531, after "Top 5" header):

```php
<th><?php esc_html_e('Top 5', 'poker-tournament-import'); ?></th>
<th><?php esc_html_e('Bubble', 'poker-tournament-import'); ?></th>
<th><?php esc_html_e('Last', 'poker-tournament-import'); ?></th>
<th><?php esc_html_e('Hits', 'poker-tournament-import'); ?></th>
<?php endif; ?>
```

**Add data cells** (after line 2559, after "Top 5" cell):

```php
<td><?php echo intval($player['tie_breakers']['top5_finishes']); ?></td>
<td><?php echo intval($player['bubble_count'] ?? 0); ?></td>
<td><?php echo intval($player['last_place_count'] ?? 0); ?></td>
<td><?php echo intval($player['total_hits']); ?></td>
<?php endif; ?>
```

### 3. Testing

#### Manual Testing Checklist

1. **Verify Formula Points**:
   - View season leaderboard
   - Points column should show formula-calculated values (not total)
   - Test with different formulas (best 8, season_total, etc.)

2. **Verify New Columns**:
   - Add `show_details="true"` to shortcode
   - Verify Bubble, Last, Hits columns appear
   - Verify counts are accurate for known players

3. **Verify Edge Cases**:
   - Season with no tournaments (should show message)
   - Player with zero bubble/last finishes (should show 0)
   - Tournaments with different paid positions (bubble calculation correct)

4. **Verify Backward Compatibility**:
   - `show_details="false"` hides new columns
   - Old cache doesn't cause PHP errors

5. **Verify Export CSV**:
   - Export includes new columns
   - Headers are correct

6. **Verify Mobile**:
   - Table scrolls horizontally on small screens
   - No layout breakage

#### PHP Syntax Validation

```bash
# Validate all modified files
for f in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php wordpress-plugin/poker-tournament-import/includes/class-series-standings.php; do
    php -l /Users/hkh/dev/tdwpimport/$f
done
```

#### Clear Transients

```bash
# Clear cache to force recalculation
/Users/hkh/Library/Application\ Support/Local/ssh-entry/hNPsf2SE_.sh -c 'wp transient delete poker_season_standings_* --allow-root'
```

### 4. Deployment

1. **Commit changes**:
   ```bash
   git add wordpress-plugin/poker-tournament-import/includes/
   git commit -m "Fix season leaderboard points display and add bubble/last/hits stats"
   ```

2. **Update version**:
   - Update version in `poker-tournament-import.php` header
   - Create new zip file for distribution

3. **Deploy**:
   - Copy files to production
   - Clear transients on production

## Troubleshooting

### Issue: Points still showing total instead of formula

**Cause**: Cache not cleared
**Fix**: Run `wp transient delete poker_season_standings_* --allow-root`

### Issue: New columns showing 0 for all players

**Cause**: Old cache doesn't have new fields
**Fix**: Wait 1 hour for cache expiry or clear transients manually

### Issue: Bubble count incorrect

**Cause**: `paid_positions` postmeta not set
**Fix**: Verify tournament post has `paid_positions` value in postmeta

### Issue: Last place count incorrect

**Cause**: Max position query failed
**Fix**: Check tournament UUIDs are correct, verify query results

### Issue: PHP warnings about undefined indexes

**Cause**: New fields not in cached data
**Fix**: Ensure null coalescing operators (`?? 0`) are in place

## Code Review Checklist

- [ ] Points column displays `series_points` not `total_points`
- [ ] Bubble, Last, Hits columns only show when `show_details="true"`
- [ ] Null coalescing operators prevent PHP notices
- [ ] Cache key includes `_v2` suffix
- [ ] Helper method `get_tournament_post_id()` added
- [ ] Max positions pre-calculated before player loop
- [ ] All PHP files pass syntax validation
- [ ] No database queries inside loops (except single max position query per tournament)
- [ ] WordPress coding standards followed
- [ ] Existing functionality not broken (export, print, limit)

## Related Files

- **Spec**: `specs/007-season-leaderboard-fix/spec.md`
- **Research**: `specs/007-season-leaderboard-fix/research.md`
- **Data Model**: `specs/007-season-leaderboard-fix/data-model.md`
- **Plan**: `specs/007-season-leaderboard-fix/plan.md`

## Next Steps

After implementation:
1. Run full testing checklist
2. Create pull request
3. Update documentation
4. Create distribution zip
5. Tag release
