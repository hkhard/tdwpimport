# Implementation Plan: Season Leaderboard Formula & Stats Enhancement

**Branch**: `007-season-leaderboard-fix` | **Date**: 2025-01-02 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-season-leaderboard-fix/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Fix the `[season_leaderboard]` shortcode to display formula-calculated season points (currently displays total points in error) and add bubble/last/hits statistics when `show_details="true"`.

**Primary Changes**:
1. Display `series_points` instead of `total_points` in main Points column
2. Calculate bubble_count and last_place_count per player
3. Add Bubble, Last, and Hits columns to detailed leaderboard view
4. Update transient cache to include new statistics

## Technical Context

**Language/Version**: PHP 8.0+ (8.2+ compatible)
**Primary Dependencies**: WordPress 6.0+, WordPress Coding Standards
**Storage**: MySQL 5.7+ / MariaDB 10.2+ (WordPress database)
- `wp_poker_tournament_players` table: tournament results with finish_position, hits, winnings, points
- WordPress postmeta: tournament metadata including paid_positions
- WordPress options: `tdwp_active_season_formula` for season formula configuration
**Testing**: Manual testing via WordPress admin, PHP syntax validation
**Target Platform**: WordPress plugin (server-side rendering)
**Project Type**: web (WordPress plugin)
**Performance Goals**:
- Leaderboard rendering <2 seconds for 50 players × 20 tournaments (SC-003)
- Transient cache reuse (existing 1-hour cache)
**Constraints**:
- Must maintain backward compatibility with existing shortcode parameters
- Must not break existing export CSV functionality
- Mobile-responsive layout for additional columns
**Scale/Scope**:
- Typically 20-50 players per season
- 10-20 tournaments per season
- Single shortcode modification, no new endpoints

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: No constitution file exists - skipping constitution check

## Project Structure

### Documentation (this feature)

```text
specs/007-season-leaderboard-fix/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── includes/
│   ├── class-shortcodes.php       # MODIFIED: season_leaderboard_shortcode()
│   └── class-series-standings.php  # MODIFIED: calculate_player_series_data()
└── assets/
    └── css/
        └── frontend.css            # MAY MODIFY: responsive layout for new columns
```

**Structure Decision**: Single WordPress plugin structure. This is a bug fix + feature enhancement to existing shortcode functionality. No new modules, endpoints, or architectural changes required.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| N/A | No violations | This is a straightforward bug fix with data already available |

## Phase 0: Research & Decisions

### Research Tasks

**Task 1: Bubble Position Calculation Method**
- **Question**: How to determine bubble position for each tournament?
- **Context**: Bubble = position just outside paid spots (e.g., 11th if 10 places paid)
- **Approach**: Review existing CSS dashboard implementation in `class-statistics-engine.php:1129-1135`

**Task 2: Last Place Detection Method**
- **Question**: How to identify if a player finished last in a tournament?
- **Context**: Last place = maximum finish_position for that tournament
- **Approach**: Determine if we query per-tournament max position or use existing data

**Task 3: Cache Invalidation Strategy**
- **Question**: How to handle existing transient cache when adding new fields?
- **Context**: Cached standings won't have bubble_count, last_place_count
- **Approach**: Use cache versioning or clear existing transients

### Decisions (see research.md for details)

<!-- After research completion, document decisions here with rationale -->

## Phase 1: Design

### Data Model (see data-model.md)

**No new database schema required** - using existing data:
- `finish_position` from wp_poker_tournament_players
- `hits` from wp_poker_tournament_players (already retrieved)
- `paid_positions` from tournament postmeta

### API Contracts (see contracts/ directory)

**No new API endpoints** - shortcode output only

### Modified Data Structures

**Player Standings Array** (returned by `calculate_season_standings()`):
```php
[
    'player_id' => string,
    'player_name' => string,
    'player_url' => string,
    'tournaments_played' => int,
    'total_points' => float,           // SUM of all tournament points
    'series_points' => float,          // Formula-calculated points (display this)
    'total_hits' => int,               // Already calculated, will display
    'bubble_count' => int,             // NEW: bubble finishes
    'last_place_count' => int,         // NEW: last place finishes
    'best_finish' => int,
    'worst_finish' => int,
    'avg_finish' => float,
    'tie_breakers' => [
        'first_places' => int,
        'top3_finishes' => int,
        'top5_finishes' => int,
        // ... other tie-breakers
    ]
]
```

### Implementation Phases

#### Phase 1A: Fix Points Display (CRITICAL BUG)
**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2540`

Change:
```php
<td><?php echo number_format($player['total_points'], 1); ?></td>
```

To:
```php
<td><?php echo number_format($player['series_points'], 1); ?></td>
```

**Impact**: Immediately fixes incorrect point display across all leaderboards

#### Phase 1B: Add Statistics Calculation
**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:224-338`

Modify `calculate_player_series_data()` method:
1. Query tournament metadata (paid_positions) for bubble calculation
2. Track bubble_count during results iteration
3. Track last_place_count during results iteration
4. Add new fields to $series_data array

**Challenge**: Per-tournament max finish_position query for last place detection

#### Phase 1C: Display New Columns
**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2522-2559`

Add table headers and data cells for:
- Bubble (column after "Top 5")
- Last (column after "Bubble")
- Hits (column after "Last")

Only render when `show_details="true"`

#### Phase 1D: Cache Update
**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:95`

Update cache key to version 2:
```php
$cache_key = 'poker_season_standings_' . $season_id . '_' . $formula_key . '_v2';
```

Or clear existing transients on deployment

## Quickstart (see quickstart.md)

Development and testing instructions for implementing this feature.

## Testing Strategy

### Unit Testing (PHP)
- N/A - manual testing sufficient for shortcode changes

### Integration Testing
1. Create test season with 10 tournaments
2. Configure "best 8" formula
3. Verify points display formula-calculated values
4. Add show_details="true" and verify new columns
5. Test edge cases (no tournaments, varying paid positions)

### Manual Testing Checklist
- [ ] Default formula shows formula-calculated points
- [ ] formula="season_total" shows sum of all points
- [ ] show_details="true" shows Bubble/Last/Hits columns
- [ ] show_details="false" hides new columns
- [ ] Export CSV includes new columns
- [ ] Mobile layout handles 3 additional columns
- [ ] Transient cache works correctly

## Success Criteria Alignment

| Spec Criterion | Implementation Verification |
|----------------|------------------------------|
| SC-001: Points match formula | Display series_points not total_points |
| SC-002: Accurate stats | Calculate bubble/last correctly per tournament |
| SC-003: <2 second render | Existing caching + efficient queries |
| SC-004: Clear headers | Column labels: Bubble, Last, Hits |
| SC-005: No regression | Export/print/limit still work |
| SC-006: Mobile responsive | CSS media queries for wider table |

## Dependencies

**Internal**:
- `Poker_Series_Standings_Calculator` class must be functional (already exists)
- `Poker_Dashboard_Filters` class for active season retrieval (already exists)
- Tournament postmeta for `paid_positions` (already stored)

**External**:
- WordPress transient API for caching (already used)
- WordPress postmeta functions (get_post_meta)

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Bubble calculation varies from CSS dashboard | Low | Medium | Reuse existing dashboard logic |
| Last place detection expensive | Medium | Low | Query max position once per tournament |
| Cache invalidation causes performance hit | Low | Low | Version cache key, let old cache expire |
| Mobile layout breaks with more columns | Low | Medium | Test on real devices, use CSS overflow-x |

## Open Questions

None - all technical decisions documented in research.md
