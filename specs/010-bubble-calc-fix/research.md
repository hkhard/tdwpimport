# Research: Bubble Calculation Fix for Season Leaderboard

**Feature**: 010-bubble-calc-fix
**Date**: 2025-01-04
**Status**: COMPLETE

## Research Question

Why is the bubble_count calculation in the season_leaderboard shortcode not working correctly?

## Investigation

### 1. Examined Series Standings Implementation (CORRECT)

**File**: `includes/class-series-standings.php`
**Lines**: 310-322

```php
// Detect bubble finish (one position outside paid spots)
$tournament_post_id = $this->get_tournament_post_id($result->tournament_id);
$paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);

// US4: Log warning if paid_positions is missing for a tournament
if (!$paid_positions) {
    error_log("US4 Warning: paid_positions not set for tournament post ID $tournament_post_id (UUID: {$result->tournament_id}) - bubble calculation may be inaccurate");
}

if ($paid_positions && $result->finish_position == $paid_positions + 1) {
    $bubble_count++;
}
```

**Key Points**:
- Uses WordPress `get_post_meta()` to retrieve `paid_positions` from tournament post
- Logs warning when `paid_positions` is missing
- Clear formula: `finish_position == paid_positions + 1`

### 2. Examined Season Leaderboard Implementation (BROKEN)

**File**: `includes/class-shortcodes.php`
**Lines**: 4718-4725

```sql
SUM(CASE
    WHEN tp.finish_position = (
        SELECT COUNT(*) + 1
        FROM wp_poker_tournament_players tp2
        WHERE tp2.tournament_id = tp.tournament_id AND tp2.winnings > 0
    )
    THEN 1 ELSE 0
END) as bubble_count,
```

**Key Problems**:
- Uses SQL subquery that counts players with `winnings > 0`
- Doesn't reference the `paid_positions` meta field
- Incorrect assumption: all players with winnings > 0 are in paid positions
- Fails for tournaments with:
  - Unpaid cashes (e.g., bounties, add-on payouts)
  - Overlay (more paid positions than actual cashes)
  - Structured prize pools (fixed number of paid positions)

## Root Cause Analysis

The SQL-based calculation assumes the number of paid positions equals the number of players with positive winnings. This is incorrect because:

1. **Bounty tournaments**: Players can win money without finishing in paid positions
2. **Overlay tournaments**: Tournament guarantees more paid positions than players who cashed
3. **Structured prizes**: Number of paid positions is predetermined, not based on actual payouts

The `paid_positions` meta field was introduced in feature 009 to store the correct number of paid positions from the Tournament Director file, but the season_leaderboard shortcode never got updated to use it.

## Solution Approach

Replace the SQL-based bubble calculation with PHP post-processing that:

1. Queries tournament results with `tournament_post_id` column
2. For each unique tournament, looks up `paid_positions` via `get_post_meta()`
3. Compares each player's `finish_position` to `paid_positions + 1`
4. Logs warning when `paid_positions` is missing

This approach:
- Matches the series standings implementation exactly
- Uses the correct `paid_positions` data source
- Provides better error logging
- Is more maintainable than complex SQL subqueries

## Dependencies

- Feature 009: Tournament import must populate `paid_positions` meta field
- WordPress post meta caching ensures performance is acceptable
- Series standings class serves as reference implementation

## Recommendations

1. **Use PHP post-processing** instead of SQL for bubble calculation
2. **Reuse series standings logic** for consistency
3. **Add error logging** for missing `paid_positions` data
4. **Cache `paid_positions` lookup** per tournament to avoid redundant queries

## Test Data Required

- Tournament with 5 paid positions, player finishes 6th
- Tournament with 3 paid positions, player finishes 4th
- Tournament with 10 paid positions, player finishes 1st (not bubble)
- Tournament without `paid_positions` set
- Multiple tournaments with varying prize structures

## References

- User Story 1: Fix Bubble Count Display (Priority: P1)
- User Story 2: Use Consistent Calculation Method (Priority: P1)
- Functional Requirements FR-001 through FR-007
- Series standings implementation: `class-series-standings.php:310-322`
