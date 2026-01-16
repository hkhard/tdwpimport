# Research: Season Leaderboard Formula & Stats Enhancement

**Feature**: 007-season-leaderboard-fix
**Date**: 2025-01-02
**Status**: Complete

## Overview

This document captures research findings and technical decisions for fixing the season leaderboard shortcode to display formula-calculated points and adding bubble/last/hits statistics.

## Research Task 1: Bubble Position Calculation Method

### Question
How to determine bubble position for each tournament when calculating season standings?

### Investigation

Reviewed existing implementation in `class-statistics-engine.php:1129-1135`:

```php
SUM(CASE WHEN tp.finish_position = (
    SELECT MAX(prize_rank)
    FROM wp_poker_tournament_import_prizes
    WHERE tournament_id = tp.tournament_id
) + 1 THEN 1 ELSE 0 END) as bubble_count
```

**Key Finding**: Bubble position is calculated as:
1. Find the maximum `prize_rank` from the prizes table for each tournament
2. Add 1 to get the first non-paid position
3. Compare each player's `finish_position` to this value

**Alternative Approach**: Use tournament postmeta `paid_positions` field:
- If `paid_positions = 10`, then bubble position = 11
- Simpler query, no JOIN required

### Decision

**Approach**: Use tournament postmeta `paid_positions` field

**Rationale**:
1. Simpler SQL - no subquery required
2. Postmeta is already loaded when querying tournaments
3. Consistent with how tournaments are displayed elsewhere
4. Fallback to calculating from prizes if postmeta not set

**Implementation**:
```php
foreach ($results as $result) {
    $tournament_post_id = $this->get_tournament_post_id($result->tournament_id);
    $paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);

    if ($paid_positions && $result->finish_position == $paid_positions + 1) {
        $bubble_count++;
    }
}
```

## Research Task 2: Last Place Detection Method

### Question
How to identify if a player finished last in a tournament?

### Investigation

**Definition**: Last place = maximum `finish_position` value for a tournament

**Challenge**: Need to know the maximum finish position for each tournament to determine if a player's result is last.

**Options Evaluated**:

**Option A**: Query max position per tournament during calculation
- **Pros**: Accurate, simple logic
- **Cons**: N+1 query problem (one query per tournament per player)
- **Performance**: 20 tournaments × 50 players = 1000 queries

**Option B**: Pre-calculate max positions in single query
- **Pros**: Single efficient query
- **Cons**: More complex SQL
- **Performance**: 1 query for all tournaments

**Option C**: Use existing `worst_finish` field
- **Pros**: Already calculated per player
- **Cons**: Field name ambiguous - is it player's worst finish or tournament's last place?
- **Finding**: `worst_finish` = highest finish_position for that player, not tournament max

### Decision

**Approach**: Pre-calculate tournament max positions in single query

**Rationale**:
1. Performance - single query instead of N queries
2. Can cache results per season
3. Accurate - actual tournament max position

**Implementation**:
```php
// Before player loop, get max positions for all tournaments
$max_positions = array();
foreach ($tournaments as $tournament) {
    $uuid = get_post_meta($tournament->ID, 'tournament_uuid', true);
    // Query max finish_position for this tournament
    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(finish_position) FROM {$table_name} WHERE tournament_id = %s",
        $uuid
    ));
    $max_positions[$uuid] = $max;
}

// During player loop
foreach ($results as $result) {
    if ($result->finish_position == $max_positions[$result->tournament_id]) {
        $last_place_count++;
    }
}
```

## Research Task 3: Cache Invalidation Strategy

### Question
How to handle existing transient cache when adding new fields (bubble_count, last_place_count)?

### Investigation

**Current Cache Key**:
```php
$cache_key = 'poker_season_standings_' . $season_id . '_' . $formula_key;
```

**Problem**: Existing cached standings won't have new fields, causing PHP notices when accessing `$player['bubble_count']`

**Options Evaluated**:

**Option A**: Version the cache key
- **Pros**: Clean break between old and new data
- **Cons**: Old cache sits unused until expiry (1 hour)
- **Implementation**: `'_v2'` suffix

**Option B**: Delete all season standings transients on plugin activation
- **Pros**: Immediate cleanup
- **Cons**: Requires activation hook, only runs on activation
- **Implementation**: `delete_transient('poker_season_standings_*')`

**Option C**: Use null coalescing in display code
- **Pros**: Backward compatible, no cache invalidation
- **Cons**: Old cache returns 0 for new fields (misleading)
- **Implementation**: `$player['bubble_count'] ?? 0`

### Decision

**Approach**: Version the cache key with backward compatibility fallback

**Rationale**:
1. Clean separation between old/new data structures
2. Old cache expires naturally (1 hour TTL)
3. Display code handles both versions safely
4. No activation hook required

**Implementation**:
```php
// In calculate_season_standings()
$cache_key = 'poker_season_standings_' . $season_id . '_' . $formula_key . '_v2';

// In shortcode display
$bubble_count = $player['bubble_count'] ?? 0;
$last_place_count = $player['last_place_count'] ?? 0;
```

## Research Task 4: Hits Display Confirmation

### Question
Is `total_hits` already calculated and available in the standings array?

### Investigation

Reviewed `calculate_player_series_data()` in `class-series-standings.php:224-338`:

```php
$total_hits = 0;
foreach ($results as $result) {
    $total_hits += intval($result->hits);
    // ...
}

$series_data = array(
    // ...
    'total_hits' => $total_hits,
    // ...
);
```

**Finding**: `total_hits` is already calculated and returned in standings array. Just need to display it.

### Decision

**Approach**: No calculation changes needed for hits - only display logic

**Implementation**:
```php
// In shortcode, when show_details="true"
<td><?php echo intval($player['total_hits']); ?></td>
```

## Summary of Decisions

| Decision | Approach | Rationale |
|----------|----------|-----------|
| Bubble calculation | Use postmeta `paid_positions` | Simpler than querying prizes table |
| Last place detection | Pre-calculate max positions | Single efficient query vs N queries |
| Cache invalidation | Version cache key + fallback | Clean break, backward compatible |
| Hits display | Use existing `total_hits` field | Already calculated, just display |

## Performance Considerations

### Current Performance
- 50 players × 20 tournaments = 1000 player-tournament combinations
- Current caching reduces this to 1 calculation per hour
- Query: Get all results for all players in all season tournaments

### New Performance Impact

**Bubble Calculation**:
- Additional: 50 `get_post_meta()` calls (negligible)
- Total: No significant impact

**Last Place Calculation**:
- Additional: 20 queries for max position (one per tournament)
- Total: +20 queries per cache miss
- Mitigation: Cache max positions array

**Overall**:
- Cache miss: +20 queries (acceptable for infrequent calculation)
- Cache hit: No performance impact
- Expected: <2 second render time maintained (SC-003)

## Alternatives Considered

### Alternative 1: Calculate Everything in SQL
**Rejected Because**:
- Would require complex CASE WHEN subqueries for each stat
- Harder to maintain
- Existing PHP structure works well

### Alternative 2: Store bubble/last in Database
**Rejected Because**:
- Adds database schema changes
- Values are derivable from existing data
- Adds complexity to import/export

### Alternative 3: Use JavaScript for Display
**Rejected Because**:
- Shortcode is server-side rendered
- Would require API endpoint
- Breaks export/print functionality

## Open Questions Resolved

All research tasks complete. No remaining questions.
