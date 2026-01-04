# Data Model: Season Leaderboard Enhancement

**Date**: January 4, 2026
**Feature**: Always show all players when show_details="true"

## Overview

This enhancement modifies the **behavior** of existing data structures but does not introduce new entities or schema changes. The season_leaderboard shortcode already has access to all player data; the change only affects how many players are displayed to the user.

## Existing Entities (No Changes)

### Season Standings
**Source**: `class-series-standings.php`

**Structure** (array of player standing objects):
```php
[
    [
        'player_id' => int,           // WordPress user ID
        'player_name' => string,      // Display name
        'series_points' => float,     // Total points for season
        'rank' => int,                // Ranking position (1-based)
        'tournaments_played' => int,  // Number of tournaments
        'best_finish' => int,         // Best placing (1 = 1st place)
        'average_finish' => float,    // Average placing
        'first_places' => int,        // Number of 1st place finishes
        'top3_finishes' => int,       // Number of top 3 finishes
        'top5_finishes' => int,       // Number of top 5 finishes
        'bubble_finishes' => int,     // Number of bubble finishes
        'last_place_finishes' => int, // Number of last place finishes
        'total_hits' => int,          // Total hit count (formula-specific)
        // ... additional formula-specific fields
    ],
    // ... more players
]
```

**Retrieval**: `Poker_Tournament_Series_Standings::calculate_season_standings($season_id, $formula_key, $limit)`

**Behavior Change**: The `$limit` parameter will be forced to `-1` (all players) when `$show_details` is true, regardless of user input.

### Shortcode Parameters
**Source**: `class-shortcodes.php` (season_leaderboard_shortcode method)

**Structure**:
```php
$atts = shortcode_atts([
    'season_id' => 0,              // Season to display
    'formula_key' => 'default',    // Scoring formula
    'show_details' => 'false',     // Show tie-breaker columns
    'limit' => '20',               // Max players to show (default)
    'echo' => 'true',              // Output or return
], $atts, 'season_leaderboard');
```

**Behavior Change**: When `show_details='true'`, the `limit` parameter is overridden to `-1`.

## Data Flow

```
1. User requests page with [season_leaderboard] shortcode
                      ↓
2. WordPress invokes season_leaderboard_shortcode($atts)
                      ↓
3. Parse shortcode attributes (show_details, limit, etc.)
                      ↓
4. NEW: If show_details=true, force limit=-1
                      ↓
5. Call calculate_season_standings($season_id, $formula_key, $limit)
                      ↓
6. Query wp_poker_tournament_players table
                      ↓
7. Calculate statistics for ALL players in season
                      ↓
8. Apply limit in calculator (array_slice)
                      ↓
9. Return standings array
                      ↓
10. Render HTML table with player rows
                      ↓
11. Output to page
```

## State Transitions

No state changes - this is a read-only operation (displaying data).

## Validation Rules

### Input Validation (Existing)
- `season_id`: Must be valid term ID (WordPress validates)
- `formula_key`: Must exist in formulas (calculator handles invalid keys)
- `show_details`: Must be 'true' or 'false' (string comparison)
- `limit`: Must be positive integer or -1 (PHP intval)

### Behavior Validation (New)
- When `show_details='true'`: Ignore any `limit` value, use -1
- When `show_details='false'`: Respect `limit` parameter (default 20)

## Relationships

```
Season (taxonomy term)
  ├─ has_many → Tournaments (custom post type)
  │              └─ has_many → TournamentPlayers (custom table)
  │                             └─ belongs_to → Player (WordPress user)
  └─ computed → SeasonStandings (transient cache)
                  └─ aggregates → TournamentPlayers data
```

**No relationship changes** - only the quantity of SeasonStandings records displayed changes.

## Storage

### Database Tables (No Changes)
- `wp_terms` / `wp_term_taxonomy`: Season definitions
- `wp_posts`: Tournament records
- `wp_poker_tournament_players`: Player participation records
- `wp_postmeta`: Tournament metadata
- `wp_usermeta`: Player metadata

### Transient Cache (No Schema Changes)
- Key: `poker_season_standings_{$season_id}_{$formula_key}`
- Value: Serialized standings array
- Expiration: 24 hours (existing)
- **Behavior**: When `show_details=true`, fetch all standings (limit=-1), cache may be larger

## Performance Considerations

### Database Queries
**No change in query structure** - the calculator already fetches all players before applying limit.

### Memory Usage
**Increase**: Detailed views will load more player data into memory
- **Before**: ~20 players × ~500 bytes = ~10 KB
- **After**: ~200 players × ~500 bytes = ~100 KB (worst case)
- **Impact**: Negligible for modern servers

### Cache Behavior
**Change**: Transient cache will store full standings array when detailed view is first requested
- **Benefit**: Subsequent detailed views load from cache (fast)
- **Cost**: Larger transient values in wp_options table
- **Mitigation**: WordPress transients have built-in size limits; large seasons will still work

## Migration

**No database migration required** - this is a behavioral change only.

**Deployment steps**:
1. Deploy updated class-shortcodes.php
2. Clear WordPress cache (transients auto-regenerate)
3. Test on production with sample seasons
