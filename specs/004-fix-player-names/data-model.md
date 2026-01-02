# Data Model: Fix Player Names Display

**Feature**: 004-fix-player-names
**Date**: 2026-01-02

## Overview

This feature does not introduce new data structures or modify existing database schema. It only changes the display logic for player names in the season leaderboard. The data model remains unchanged.

## Existing Data Structures

### Player Post (WordPress Custom Post Type)

**Type**: `player` (Custom Post Type)
**Storage**: WordPress `wp_posts` table + `wp_postmeta`

**Attributes**:
- `ID` (int): WordPress post ID
- `post_title` (string): Player name - human-readable display name
- `post_status` (string): Post status (publish, draft, etc.)
- `post_type` (string): Always 'player'

**Meta Data** (wp_postmeta):
- `_player_uuid` (string): Unique identifier matching player_id in tournament results
  - Used to link tournament results to player posts
  - Format: UUID string (e.g., "550e8400-e29b-41d4-a716-446655440000")

**Relationships**:
- Has many: Tournament results (via _player_uuid)

**Validation Rules**:
- `post_title` must not be empty (enforced at display time)
- `_player_uuid` must be unique (ideally, but not enforced)

### Tournament Player Result

**Table**: `wp_poker_tournament_players`
**Storage**: Custom MySQL table

**Attributes**:
- `tournament_id` (string): Tournament UUID - references tournament_uuid in postmeta
- `player_id` (string): Player UUID - foreign key to _player_uuid
- `finish_position` (int): Final placing in tournament
- `winnings` (decimal): Prize money won
- `points` (decimal): Points awarded
- `hits` (int): Statistical metric

**Relationships**:
- Belongs to: Tournament (via tournament_id)
- Belongs to: Player (via player_id → _player_uuid)

**Indexes**:
- Primary key: (tournament_id, player_id)
- Index on: player_id (for lookup queries)

### Season Leaderboard Entry

**Type**: Computed/Transient
**Storage**: PHP object in memory + WordPress transient cache

**Attributes**:
```php
{
    player_id: string,        // Player UUID (GUID)
    player_name: string,      // Display name (CHANGED: now "Unknown Player" fallback)
    player_url: string,       // Profile URL (empty string if no player post)
    tournaments_played: int,
    total_points: float,
    total_winnings: float,
    total_hits: int,
    best_finish: int,
    worst_finish: int,
    avg_finish: float,
    tournament_points: array,
    finishes: array,
    results_detail: array,
    series_points: float,
    formula_used: string,
    tie_breakers: array
}
```

**Relationships**:
- Computed from: Tournament Player Results
- Optional link to: Player Post (via player_id → _player_uuid)

**Cache Key**: `poker_season_standings_{season_id}_{formula_key}`
**Cache Duration**: 1 hour (HOUR_IN_SECONDS)

## Data Flow

### Current (Buggy) Flow

```
1. Tournament Results (player_id = "550e8400...")
   ↓
2. calculate_player_series_data($player_id)
   ↓
3. get_posts(['meta_key' => '_player_uuid', 'meta_value' => $player_id])
   ↓
4a. IF found: $player_name = $player_post[0]->post_title
4b. IF NOT found: $player_name = $player_id ← BUG: Shows GUID
   ↓
5. Leaderboard displays: "550e8400-e29b-41d4-a716-446655440000"
```

### Fixed Flow

```
1. Tournament Results (player_id = "550e8400...")
   ↓
2. calculate_player_series_data($player_id)
   ↓
3. get_posts(['meta_key' => '_player_uuid', 'meta_value' => $player_id])
   ↓
4a. IF found AND post_title not empty:
        $player_name = $player_post[0]->post_title
        $player_url = get_permalink($player_post[0]->ID)
4b. IF NOT found OR post_title empty:
        $player_name = __('Unknown Player', 'poker-tournament-import')
        $player_url = ''
   ↓
5. Leaderboard displays: "Unknown Player" (or actual name)
```

## State Transitions

### Player Name Display State

```
Player Data State          → Display Output
--------------------------------------------------------------------
Player post exists + title → Player post title (linked)
Player post missing        → "Unknown Player" (plain text)
Player post exists + no title → "Unknown Player" (plain text)
Multiple posts for UUID    → First post's title (linked) or "Unknown Player"
```

## Validation Rules

### Input Validation (No Changes)

- Player UUID format: Valid UUID string (enforced by existing code)
- Season ID: Must be valid WordPress post ID
- Formula key: Must be valid formula key or 'default'

### Display Validation (NEW)

```php
// Rule: Never display raw GUID/UUID to end users
if (empty($player_post) || empty($player_post[0]->post_title)) {
    $player_name = __('Unknown Player', 'poker-tournament-import');
    $player_url = '';  // No link without valid player post
} else {
    $player_name = $player_post[0]->post_title;
    $player_url = get_permalink($player_post[0]->ID);
}
```

### Output Sanitization (Existing, No Changes)

- All player names escaped via `esc_html()` in shortcode output
- All URLs escaped via `esc_url()` in shortcode output
- Prevents XSS attacks from malicious player names

## Data Integrity Considerations

### Current Issues (Out of Scope to Fix)

1. **Multiple Player Posts for Same UUID**: Data integrity issue where duplicate player posts exist
   - Impact: System uses first result (arbitrary but deterministic)
   - Proper Fix: Add unique constraint on _player_uuid
   - This Feature: Use first result (existing behavior)

2. **Orphaned Tournament Results**: Results with player_ids that have no corresponding player post
   - Impact: Shows "Unknown Player" placeholder (fixed by this feature)
   - Proper Fix: Data cleanup or auto-create player posts
   - This Feature: Show placeholder (acceptable UX)

3. **Empty Player Post Titles**: Player posts with blank post_title field
   - Impact: Shows "Unknown Player" placeholder (fixed by this feature)
   - Proper Fix: Validation on player post save to require title
   - This Feature: Treat as missing (acceptable fallback)

## Database Queries

### Player Post Lookup (No Changes)

```sql
-- WordPress generates this query internally from get_posts():
SELECT wp_posts.*
FROM wp_posts
INNER JOIN wp_postmeta ON (wp_posts.ID = wp_postmeta.post_id)
WHERE 1=1
  AND wp_posts.post_type = 'player'
  AND (wp_postmeta.meta_key = '_player_uuid'
       AND wp_postmeta.meta_value = '550e8400-e29b-41d4-a716-446655440000')
ORDER BY wp_posts.post_date DESC
LIMIT 1
```

**Performance**: Acceptable
- Uses indexed meta_key and meta_value lookups
- Results cached by WordPress object cache
- Called once per player, cached via transient for 1 hour

### Season Standings Cache (No Changes)

```sql
-- Not a direct SQL query, but uses WordPress transient:
get_transient('poker_season_standings_{season_id}_{formula_key}')
```

**Performance**: Excellent
- Full leaderboard computed once per hour
- Subsequent page loads serve from cache
- Invalidation: Tournament save/delete/refresh triggers

## No Schema Changes

✓ No database migrations required
✓ No new tables
✓ No modified tables
✓ No new indexes
✓ No data transformation scripts needed

## Summary

This feature is purely a **display logic change** with no data model modifications:

- **BEFORE**: GUID fallback → UUID string displayed
- **AFTER**: Placeholder fallback → "Unknown Player" displayed

All data structures remain identical. Only the conditional logic for choosing the display value changes.
