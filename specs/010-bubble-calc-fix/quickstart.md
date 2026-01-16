# Quickstart: Bubble Calculation Fix for Season Leaderboard

**Branch**: `010-bubble-calc-fix`
**Status**: Ready for Implementation
**Prerequisites**: Feature 009 (paid_positions meta field during import)

## Overview

Fix the bubble_count column in the `[season_leaderboard]` shortcode to accurately track how many times each player finished in the last unpaid position (the "bubble").

**Current State**: Broken - uses SQL subquery that counts players with winnings > 0
**Desired State**: Fixed - uses `paid_positions` meta field from tournament posts

## Implementation Time

Estimated 1-2 hours for experienced WordPress developer.

## Step-by-Step Guide

### 1. Review Reference Implementation

First, understand the correct bubble calculation logic:

```bash
grep -A 15 "Detect bubble finish" wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
```

Key points:
- Uses `get_post_meta($tournament_post_id, 'paid_positions', true)`
- Logs warning when `paid_positions` is missing
- Formula: Bubble if `finish_position == paid_positions + 1`

### 2. Locate Target Function

Find the `render_season_leaderboard()` function in class-shortcodes.php:

```bash
grep -n "function render_season_leaderboard" wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
```

This function contains the broken SQL-based bubble calculation around line 4718.

### 3. Modify the Shortcode Handler

**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Step 3.1**: Remove the SQL CASE statement (around line 4718-4725):
```sql
-- REMOVE THIS:
SUM(CASE
    WHEN tp.finish_position = (
        SELECT COUNT(*) + 1
        FROM wp_poker_tournament_players tp2
        WHERE tp2.tournament_id = tp.tournament_id AND tp2.winnings > 0
    )
    THEN 1 ELSE 0
END) as bubble_count,
```

**Step 3.2**: Initialize bubble_count to 0 in SELECT:
```sql
0 as bubble_count,
```

**Step 3.3**: Add PHP post-processing after query execution (around line 4750):

```php
// Calculate bubble counts using paid_positions meta field
$tournament_paid_positions = array();
foreach ($results as $row) {
    if (!isset($tournament_paid_positions[$row->tournament_id])) {
        $tournament_post_id = $row->tournament_post_id;
        $paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);

        if (!$paid_positions) {
            error_log("US4 Warning: paid_positions not set for tournament post ID $tournament_post_id (UUID: {$row->tournament_id}) - bubble calculation may be inaccurate");
        }

        $tournament_paid_positions[$row->tournament_id] = $paid_positions;
    }
}

// Recalculate bubble_count for each result
foreach ($results as &$row) {
    $paid_positions = $tournament_paid_positions[$row->tournament_id];

    if ($paid_positions && $row->finish_position == $paid_positions + 1) {
        $row->bubble_count = 1;
    } else {
        $row->bubble_count = 0;
    }
}
unset($row); // Break reference
```

### 4. Update Plugin Version

**File**: `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`

**Line 5** (plugin header):
```php
 * Version: 3.5.0-beta52
```

**Line 22** (version constant):
```php
define('POKER_TOURNAMENT_IMPORT_VERSION', '3.5.0-beta52');
```

### 5. Verify Changes

Check PHP syntax:
```bash
php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
php -l wordpress-plugin/poker-tournament-import/poker-tournament-import.php
```

Both should return "No syntax errors detected".

### 6. Test Locally

1. **Backup current plugin**:
   ```bash
   cp -r wordpress-plugin/poker-tournament-import wordpress-plugin/poker-tournament-import.backup
   ```

2. **Deploy to WordPress**:
   - Via Local: Copy files to `wp-content/plugins/poker-tournament-import/`
   - Via Admin: Create new zip file and upload in WordPress admin

3. **Clear caches**:
   ```bash
   # Via WP-CLI
   wp transient delete --all
   wp cache flush
   ```

4. **View season leaderboard** on frontend page containing `[season_leaderboard]` shortcode

5. **Verify bubble counts**:
   - Compare with series standings data
   - Check error_log for warnings about missing paid_positions

### 7. Create Distribution Package

```bash
cd wordpress-plugin
zip -r ../poker-tournament-import-v3.5.0-beta52.zip poker-tournament-import/
cd ..
```

## Testing Checklist

- [ ] Tournament with 5 paid positions: player finishes 6th → bubble_count = 1
- [ ] Tournament with 3 paid positions: player finishes 4th → bubble_count = 1
- [ ] Tournament with 10 paid positions: player finishes 1st → bubble_count = 0
- [ ] Multiple tournaments in season: bubble_count aggregates correctly
- [ ] Tournament without paid_positions: error_log warning appears
- [ ] Bubble counts match series standings data 100%
- [ ] No PHP errors or warnings in browser console
- [ ] No PHP errors or warnings in debug.log

## Common Issues

### Issue: "Undefined property: stdClass::$tournament_post_id"

**Cause**: SQL query doesn't select `tournament_post_id` column

**Fix**: Add `tp.tournament_post_id` to SELECT statement

### Issue: Bubble counts all showing 0

**Cause**: `paid_positions` meta field not populated during import

**Fix**: Verify feature 009 (paid_positions population) is working correctly

### Issue: Performance degradation on large leaderboards

**Cause**: Calling `get_post_meta()` in a loop without caching

**Fix**: Use the pattern shown above that caches `paid_positions` per tournament

## Next Steps

After implementation:

1. Run manual testing checklist
2. Compare bubble counts with series standings
3. Check error_log for warnings
4. Create distribution package
5. Commit changes with message: "Fix bubble calculation in season_leaderboard shortcode"
6. Create pull request to main branch

## Related Files

- **Specification**: [spec.md](./spec.md)
- **Implementation Plan**: [plan.md](./plan.md)
- **Reference Implementation**: `includes/class-series-standings.php:310-322`
- **Target File**: `includes/class-shortcodes.php` (render_season_leaderboard function)
