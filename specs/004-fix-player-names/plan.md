# Implementation Plan: Fix Player Name Display in Season Leaderboard

**Branch**: `004-fix-player-names` | **Date**: 2026-01-02 | **Status**: ✅ **COMPLETE**
**Spec**: [spec.md](./spec.md)

## Completion Notice

**Date Completed**: 2026-01-02
**Implementation Status**: ✅ Implementation complete, testing pending
**Distribution**: v3.5.0-beta35 created

### What Was Accomplished

1. **GUID Display Fix**: Replaced raw GUID fallback with "Unknown Player" placeholder in season leaderboard
2. **Single-Line Change**: Modified `class-series-standings.php` to display user-friendly placeholder instead of cryptic GUIDs
3. **Backward Compatibility**: No breaking changes, only display improvement

### Files Modified

- `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` (lines 255-259)

### Testing

Implementation tasks complete (T001-T015). Manual testing pending (T016-T027).

---

## Summary (Original)

Fix critical data display bug where the [season_leaderboard] shortcode shows raw GUIDs (UUIDs) instead of human-readable player names. When player posts don't exist for tournament results, the system currently falls back to displaying the technical identifier, making the leaderboard unusable for end users.

## Summary

Fix critical data display bug where the [season_leaderboard] shortcode shows raw GUIDs (UUIDs) instead of human-readable player names. When player posts don't exist for tournament results, the system currently falls back to displaying the technical identifier, making the leaderboard unusable for end users.

**Technical Approach**: Modify the `calculate_player_series_data()` method in `Poker_Series_Standings_Calculator` to use a localized "Unknown Player" placeholder instead of the raw GUID when player posts are missing or have empty titles.

## Technical Context

**Language/Version**: PHP 8.0+ (8.2+ compatible)
**Primary Dependencies**: WordPress 6.0+, WordPress Coding Standards
**Storage**: MySQL 5.7+ / MariaDB 10.2+ (custom wp_poker_tournament_players table)
**Testing**: Manual testing via WordPress admin, optional PHPUnit tests
**Target Platform**: WordPress plugin (Linux/Unix servers)
**Project Type**: Single project (WordPress plugin)
**Performance Goals**: <500ms page load for shortcode, <10% performance impact from change
**Constraints**: Must maintain backward compatibility, no breaking changes, no database schema changes
**Scale/Scope**: Affects 1 method, 1 file, ~5 lines of code changed, 0 new dependencies

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: ⚠️ CONSTITUTION NOT FOUND

The project constitution file (`.specify/memory/constitution.md`) is still in template form and has not been customized with project-specific principles.

**Default Principles Applied**:
1. **Code Quality**: Follow WordPress Coding Standards
2. **Testing**: Manual testing required (no automated test suite available)
3. **Backward Compatibility**: Must maintain existing functionality
4. **Security**: Proper escaping for all output (XSS prevention)
5. **Performance**: No degradation in page load times

**No Violations**: This is a minimal bug fix with:
- Single method modified (private, internal implementation)
- No new dependencies or complexity
- No breaking changes
- Follows existing patterns

## Project Structure

### Documentation (this feature)

```text
specs/004-fix-player-names/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output - Technical research and decisions
├── data-model.md        # Phase 1 output - Data structures (no changes)
├── quickstart.md        # Phase 1 output - Implementation guide
├── contracts/           # Phase 1 output - API contracts (no new contracts)
│   └── README.md        # Contract documentation
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created yet)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── includes/
│   ├── class-series-standings.php    # PRIMARY FILE TO MODIFY
│   │   └── Poker_Series_Standings_Calculator
│   │       └── calculate_player_series_data()  # METHOD TO MODIFY (lines ~277-283)
│   ├── class-shortcodes.php          # Uses the calculator (no changes needed)
│   └── class-dashboard-filters.php   # Season filter integration (no changes needed)
└── assets/
    └── No CSS/JS changes required (display only)
```

**Structure Decision**: This is a WordPress plugin (Option 1: Single project structure). The fix is localized to a single method in the `Poker_Series_Standings_Calculator` class with no changes to data structures, APIs, or UI components.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

**No violations to justify.** This is a straightforward bug fix:
- Single line change to default value assignment
- One additional condition in if-statement
- No new abstractions or patterns introduced
- Follows existing code style and patterns

## Phase 0: Research & Decisions ✅ COMPLETE

**Output**: [research.md](./research.md)

### Research Summary

**Key Findings**:
1. **Root Cause Identified**: Line 277 in `class-series-standings.php` falls back to `$player_id` (GUID) when player post not found
2. **Existing Caching**: Transient cache (1 hour) means performance impact is negligible
3. **No Database Changes Required**: Pure display logic fix
4. **Security**: Existing `esc_html()` in shortcode handles XSS prevention

**Technical Decisions**:
1. **Placeholder Text**: Use "Unknown Player" (translated) for missing players
2. **Query Strategy**: Keep existing `get_posts()` approach (performant, cached)
3. **Empty Title Handling**: Treat empty `post_title` as missing player
4. **Multiple Posts**: Use first result (existing behavior, acceptable)
5. **Security**: Rely on existing escaping in shortcode (no changes needed)

**Alternatives Considered and Rejected**:
- Skip players without posts → Would break rankings/totals
- Create player posts automatically → Out of scope, data integrity risk
- Batch query all players → Unnecessary complexity for minimal performance gain

## Phase 1: Design ✅ COMPLETE

**Output**: [data-model.md](./data-model.md), [contracts/README.md](./contracts/README.md), [quickstart.md](./quickstart.md)

### Data Model

**No Changes**: Existing data structures remain unchanged
- Player posts (custom post type)
- Tournament results (custom table)
- Season leaderboard (computed/cached)

**Flow Change**:
```
BEFORE: No player post → Display GUID
AFTER:  No player post → Display "Unknown Player"
```

### Contracts

**No New APIs**: Internal implementation detail only
- Modified method: `calculate_player_series_data()` (private)
- No public API changes
- No shortcode parameter changes
- Fully backward compatible

### Implementation Details

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
**Method**: `calculate_player_series_data()` (lines ~277-283)
**Changes**: 2 lines modified, 1 condition added

**Before**:
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

**Manual Testing** (Required):
1. Test with player post exists → Shows name
2. Test with player post missing → Shows "Unknown Player"
3. Test with empty post title → Shows "Unknown Player"
4. Test XSS with special characters → Properly escaped
5. Clear cache and verify → Fix persists after cache refresh

**Success Criteria**:
- ✅ Zero GUIDs visible in shortcode output
- ✅ Player names display when posts exist
- ✅ "Unknown Player" placeholder when posts missing
- ✅ Player profile links functional
- ✅ No performance degradation
- ✅ No PHP errors/warnings

## Phase 2: Implementation Tasks

**Output**: `tasks.md` (generated by `/speckit.tasks` command - NOT YET CREATED)

**Next Step**: Run `/speckit.tasks` to generate actionable implementation tasks

---

## Ready for Implementation

**Status**: ✅ Planning complete, ready for task generation

**Completed Artifacts**:
- ✅ Feature specification ([spec.md](./spec.md))
- ✅ Technical research ([research.md](./research.md))
- ✅ Data model documentation ([data-model.md](./data-model.md))
- ✅ Contract documentation ([contracts/README.md](./contracts/README.md))
- ✅ Quickstart guide ([quickstart.md](./quickstart.md))

**Next Command**: `/speckit.tasks` to generate implementation tasks
