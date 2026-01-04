# Shortcode Contract: season_leaderboard

**Version**: 3.6.1 (post-enhancement)
**Date**: January 4, 2026
**Location**: `class-shortcodes.php:2426-2607`

## Shortcode Signature

```
[season_leaderboard season_id="123" formula_key="default" show_details="true" limit="20" echo="true"]
```

## Input Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `season_id` | int | Required (0 = error) | WordPress term ID of the season to display |
| `formula_key` | string | "default" | Scoring formula key (must exist in formulas) |
| `show_details` | string ("true"/"false") | "false" | Show detailed tie-breaker columns |
| `limit` | int | 20 | Maximum players to display (see behavior notes) |
| `echo` | string ("true"/"false") | "true" | Output HTML directly (true) or return as string (false) |

## Behavior Contract

### Standard View (`show_details="false"`)

**Output**: Basic standings table with columns:
- Rank
- Player
- Points

**Limit Behavior**: Respects `limit` parameter
- Default: Shows top 20 players
- Custom limit: Shows top N players where N = `limit` value

**Example**:
```php
[season_leaderboard season_id="5" limit="10"]
// Shows top 10 players without details
```

### Detailed View (`show_details="true"`) ⭐ **ENHANCED**

**Output**: Enhanced standings table with columns:
- Rank
- Player
- Points
- Played (tournaments played)
- Best (best finish)
- Avg (average finish)
- 1st (first places)
- Top 3
- Top 5
- Bubble (bubble finishes)
- Last (last place finishes)
- Hits (total hits)

**Limit Behavior**: **IGNORES limit parameter, always shows all players**
- Always: Shows ALL registered players in the season
- `limit` parameter has no effect when `show_details="true"`

**Example**:
```php
[season_leaderboard season_id="5" show_details="true" limit="10"]
// Shows ALL players in season 5 with detailed statistics
// The limit="10" is ignored
```

## Output Format

### Return Value (when `echo="false"`)
```php
string // HTML table representation of standings
```

### Direct Output (when `echo="true"`)
Outputs HTML directly to page buffer (no return value)

### HTML Structure
```html
<table class="poker-season-leaderboard">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Player</th>
            <th>Points</th>
            <!-- Additional columns when show_details="true" -->
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>Player Name</td>
            <td>150.5</td>
            <!-- Additional cells when show_details="true" -->
        </tr>
        <!-- More player rows -->
    </tbody>
</table>
```

## Error Handling

### Invalid season_id
**Behavior**: Returns error message
```html
<div class="poker-error">Invalid season specified.</div>
```

### No players in season
**Behavior**: Returns informational message
```html
<div class="poker-info">No players found for this season.</div>
```

### Invalid formula_key
**Behavior**: Falls back to default formula, logs warning

## Performance Characteristics

### Database Queries
1. Season meta query: 1 query
2. Tournament posts query: 1 query (with meta_query)
3. Player participation query: 1 query (custom table)
4. Player meta queries: N queries (batched via `get_users()`)
5. Formula calculations: In-memory computation

**Total**: ~5 queries regardless of player count

### Caching
- **Transient key**: `poker_season_standings_{$season_id}_{$formula_key}`
- **TTL**: 24 hours
- **Invalidation**: On tournament save/delete/update

### Memory Usage
- **Per player**: ~500 bytes
- **Standard view (20 players)**: ~10 KB
- **Detailed view (all players)**: ~50-100 KB depending on season size

### Execution Time
- **Cached**: <50ms (deserialization + rendering)
- **Uncached**: 500ms-2s depending on season size and player count
- **Rendering**: <100ms for 200 rows (HTML generation)

## Version History

| Version | Date | Change |
|---------|------|--------|
| 3.6.0 | 2025-12-30 | Initial bubble calculation fix |
| 3.6.1 | 2026-01-04 | **ENHANCEMENT**: Detailed view now always shows all players, ignores limit parameter |

## Breaking Changes from 3.6.0

### Changed Behavior
**Before (3.6.0)**:
```php
[season_leaderboard show_details="true" limit="10"]
// Shows top 10 players with details
```

**After (3.6.1)**:
```php
[season_leaderboard show_details="true" limit="10"]
// Shows ALL players with details (limit is ignored)
```

**Migration**: None required - this is intentional behavior change per user requirement. Users who want limited detailed views should use `show_details="false"`.

## Test Cases

### TC-001: Standard view with default limit
```php
[season_leaderboard season_id="5"]
```
**Expected**: Top 20 players, no detail columns

### TC-002: Standard view with custom limit
```php
[season_leaderboard season_id="5" limit="10"]
```
**Expected**: Top 10 players, no detail columns

### TC-003: Detailed view with default limit
```php
[season_leaderboard season_id="5" show_details="true"]
```
**Expected**: ALL players, with detail columns

### TC-004: Detailed view with explicit limit ⭐ **NEW BEHAVIOR**
```php
[season_leaderboard season_id="5" show_details="true" limit="10"]
```
**Expected**: ALL players (50+), with detail columns, limit is IGNORED

### TC-005: Detailed view on small season
```php
[season_leaderboard season_id="3" show_details="true"]
```
**Expected**: All 5 players in season, with detail columns

### TC-006: Empty season
```php
[season_leaderboard season_id="999" show_details="true"]
```
**Expected**: "No players found for this season" message
