# Data Model: Season Leaderboard Formula & Stats Enhancement

**Feature**: 007-season-leaderboard-fix
**Date**: 2025-01-02
**Status**: No Schema Changes

## Overview

This feature requires **no new database schema or migrations**. All data is already available in existing tables. The changes are purely computational (adding calculated fields to existing data structures) and display-related.

## Existing Data Sources

### Primary Table: wp_poker_tournament_players

| Column | Type | Description | Usage in Feature |
|--------|------|-------------|------------------|
| tournament_id | varchar(36) | Tournament UUID (foreign key to tournament posts) | Query player results |
| player_id | varchar(36) | Player UUID (foreign key to player posts) | Group by player |
| finish_position | int | Final finishing position (1 = first) | Calculate bubble/last |
| points | decimal | Tournament points awarded | Already used for total_points |
| winnings | decimal | Prize money won | Existing tie-breaker |
| hits | int | Player hits (Tournament Director stat) | Display in leaderboard |

**Indexes**:
- PRIMARY KEY (tournament_id, player_id)
- INDEX finish_position (used for max position queries)

### WordPress Post Meta: Tournament Metadata

| Meta Key | Type | Description | Usage in Feature |
|----------|------|-------------|------------------|
| tournament_uuid | varchar(36) | Maps post to database UUID | Query correlation |
| paid_positions | int | Number of paid places | Calculate bubble position |

### WordPress Options: Season Configuration

| Option Name | Type | Description | Usage in Feature |
|-------------|------|-------------|------------------|
| tdwp_active_season_formula | varchar(50) | Formula key for season points | Default formula |

## Data Structures

### Input: Tournament Results Query

**Method**: `Poker_Series_Standings_Calculator::calculate_player_series_data()`

**Query** (simplified):
```sql
SELECT
    tournament_id,
    finish_position,
    winnings,
    points,
    hits
FROM wp_poker_tournament_players
WHERE player_id = %s
  AND tournament_id IN (/* season tournament UUIDs */)
ORDER BY tournament_id
```

**Returns**: Array of result objects for a single player

### Processing: Player Statistics Calculation

**Input**: Array of tournament results for one player

**Calculated Fields**:

| Field | Type | Calculation | Example |
|-------|------|-------------|---------|
| tournaments_played | int | count(results) | 10 |
| total_points | float | sum(points) | 850.0 |
| total_hits | int | sum(hits) | 45 |
| best_finish | int | min(finish_position) | 1 |
| worst_finish | int | max(finish_position) | 28 |
| avg_finish | float | avg(finish_position) | 12.5 |
| bubble_count | int | **NEW** count(finish_position == paid_positions + 1) | 2 |
| last_place_count | int | **NEW** count(finish_position == max_tournament_position) | 1 |

**Algorithm** (pseudocode):
```php
foreach ($results as $result) {
    // Existing calculations
    $total_points += $result->points;
    $total_hits += $result->hits;
    // ...

    // NEW: Bubble detection
    $paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);
    if ($paid_positions && $result->finish_position == $paid_positions + 1) {
        $bubble_count++;
    }

    // NEW: Last place detection
    if ($result->finish_position == $max_positions[$result->tournament_id]) {
        $last_place_count++;
    }
}
```

### Output: Season Standings Array

**Structure**: Array of player standings, sorted by series_points

```php
[
    [
        'player_id' => string,              // UUID
        'player_name' => string,            // Display name
        'player_url' => string,             // Permalink
        'tournaments_played' => int,
        'total_points' => float,            // Sum of all tournament points
        'series_points' => float,           // Formula-calculated points (DISPLAY THIS)
        'total_hits' => int,                // Sum of hits (already calculated, now displayed)
        'bubble_count' => int,              // NEW: Number of bubble finishes
        'last_place_count' => int,          // NEW: Number of last place finishes
        'best_finish' => int,
        'worst_finish' => int,
        'avg_finish' => float,
        'tie_breakers' => [
            'first_places' => int,
            'top3_finishes' => int,
            'top5_finishes' => int,
            'best_points' => float,
            'total_winnings' => float,
            'avg_finish' => float,
            'tournaments_played' => int
        ],
        'formula_used' => string            // Formula key applied
    ],
    // ... more players
]
```

**Sorting**:
1. Primary: series_points (descending)
2. Tie-breaker 1: first_places (descending)
3. Tie-breaker 2: top3_finishes (descending)
4. Tie-breaker 3: top5_finishes (descending)
5. Tie-breaker 4: best_points (descending)
6. Tie-breaker 5: total_winnings (descending)
7. Tie-breaker 6: avg_finish (ascending)

## Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Season Query                                            │
│    Get all tournaments in season (WordPress posts)          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Player Results Query                                     │
│    SELECT * FROM wp_poker_tournament_players               │
│    WHERE tournament_id IN (season UUIDs)                    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Per-Player Statistics Calculation                        │
│    For each player:                                         │
│    - Sum total_points, total_hits                           │
│    - Calculate bubble_count (NEW)                           │
│    - Calculate last_place_count (NEW)                       │
│    - Apply series formula to get series_points              │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Sorting & Ranking                                        │
│    Sort by series_points, apply tie-breakers                │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Caching                                                  │
│    Store in transient: poker_season_standings_{season}_v2   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. Display                                                  │
│    Render HTML table with series_points + new columns       │
└─────────────────────────────────────────────────────────────┘
```

## Validation Rules

### Bubble Position Calculation
- **Rule**: Bubble position = paid_positions + 1
- **Validation**: Bubble count ≥ 0, ≤ tournaments_played
- **Edge Case**: If paid_positions not set, bubble_count = 0

### Last Place Calculation
- **Rule**: Last place = MAX(finish_position) for tournament
- **Validation**: Last count ≥ 0, ≤ tournaments_played
- **Edge Case**: If max_position cannot be determined, last_place_count = 0

### Hits Display
- **Rule**: Display total_hits (already calculated)
- **Validation**: Hits ≥ 0
- **Edge Case**: If not in array (old cache), display 0

## No Migrations Required

**Reason**: All data exists in current schema. Only:
1. New calculated fields in PHP array
2. New display columns in HTML output
3. Cache key version bump

## Backward Compatibility

**Cache Strategy**:
- Old cache key: `poker_season_standings_{season}_{formula}`
- New cache key: `poker_season_standings_{season}_{formula}_v2`
- Display code: `$player['bubble_count'] ?? 0` (handles old cache)

**Graceful Degradation**:
- If new fields missing (old cache), display 0
- No PHP errors or notices
- Users see correct data after cache expires (1 hour)
