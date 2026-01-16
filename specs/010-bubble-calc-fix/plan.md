# Implementation Plan: Bubble Calculation Fix for Season Leaderboard

**Branch**: `010-bubble-calc-fix` | **Date**: 2025-01-04 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-bubble-calc-fix/spec.md`

## Summary

Fix the bubble_count calculation in the season_leaderboard shortcode to use the `paid_positions` meta field from tournament posts, matching the correct implementation in the series standings class. Remove the broken SQL-based subquery that doesn't reference `paid_positions`.

## Technical Context

**Language/Version**: PHP 8.0+ (WordPress 6.0+ environment)
**Primary Dependencies**: WordPress API, MySQL 5.7+, jQuery (already enqueued)
**Storage**: WordPress MySQL database (wp_ prefix)
**Testing**: Manual testing via WordPress admin and frontend shortcodes
**Target Platform**: WordPress plugin (admin dashboard + frontend shortcodes)
**Project Type**: Single (WordPress plugin)
**Performance Goals**: Minimize database queries, use transient caching
**Constraints**: WordPress coding standards, nonce verification for AJAX, prepared statements
**Scale/Scope**: 10s of tournaments, 100s of players per season

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: No project constitution exists (template only). Following WordPress coding standards and CLAUDE.md guidelines.

## Project Structure

### Documentation (this feature)

```text
specs/010-bubble-calc-fix/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (not needed - no new data model)
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (not needed - no new contracts)
└── tasks.md             # Phase 2 output (NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── includes/
│   ├── class-shortcodes.php       # season_leaderboard shortcode (MODIFY)
│   └── class-series-standings.php # Reference implementation (READ ONLY)
├── poker-tournament-import.php    # Main plugin file (version update)
└── assets/                        # No changes needed
```

**Structure Decision**: Single WordPress plugin with includes/ directory for class files. Target file is `class-shortcodes.php` which contains the season_leaderboard shortcode handler.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

N/A - No constitution violations. Feature is a straightforward bug fix with clear requirements.

## Phase 0: Research (COMPLETE)

### Research Summary

The specification identifies two different bubble calculation implementations:

1. **Series standings class** (CORRECT):
   - File: `includes/class-series-standings.php` lines 310-322
   - Uses `get_post_meta($tournament_post_id, 'paid_positions', true)`
   - Logs warning when `paid_positions` is missing
   - Formula: Bubble if `finish_position == paid_positions + 1`

2. **Season leaderboard shortcode** (BROKEN):
   - File: `includes/class-shortcodes.php` lines 4718-4725
   - Uses SQL subquery: `SELECT COUNT(*) + 1 FROM ... WHERE tp2.winnings > 0`
   - Problem: Doesn't reference `paid_positions` meta field
   - Assumes all players with winnings > 0 are in paid positions (incorrect)

**Root Cause**: SQL-based calculation doesn't account for tournaments where `paid_positions` differs from actual paid players (e.g., tournaments with unpaid cashes, overlay, or structured prize pools).

**Solution**: Replace SQL subquery with post meta lookup matching series standings implementation.

## Phase 1: Design

### Data Model Changes

**No new data model required.** The `paid_positions` meta field already exists on tournament posts (populated during tournament import from previous feature work).

### API/Interface Changes

**No new APIs or interfaces.** This is an internal calculation fix within the existing shortcode handler.

### Component Design

#### Target: `includes/class-shortcodes.php`

**Function**: `render_season_leaderboard()` (around line 4500)

**Current Implementation** (lines 4718-4725):
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

**New Implementation**:
```sql
SUM(CASE
    WHEN tp.finish_position = (
        SELECT CAST(pm.meta_value AS UNSIGNED)
        FROM wp_postmeta pm
        INNER JOIN wp_poker_tournament_players tp3 ON tp3.tournament_id = tp.tournament_id
        WHERE pm.post_id = tp3.tournament_post_id
        AND pm.meta_key = 'paid_positions'
        LIMIT 1
    ) + 1
    THEN 1 ELSE 0
END) as bubble_count,
```

**Alternative Approach** (simpler, more maintainable):
Use PHP post-processing instead of SQL subquery:
1. Query tournament results with `tournament_post_id`
2. For each row: look up `paid_positions` via `get_post_meta()`
3. Compare `finish_position` to `paid_positions + 1`

**Recommendation**: PHP post-processing for better maintainability and consistency with series standings class.

### Security Considerations

- No new security vulnerabilities introduced
- Uses existing WordPress API functions (`get_post_meta`)
- Prepared statements already in use for SQL queries
- No user input handling changes

### Performance Considerations

**Current**: SQL subquery executes once per row in result set
**Proposed**: One `get_post_meta()` call per tournament (cached by WordPress)

**Impact**: Minimal - WordPress object cache for post_meta is very efficient. For typical season (10-20 tournaments), difference is negligible.

### Testing Strategy

1. **Manual Test Cases**:
   - Tournament with 5 paid positions: player finishes 6th → bubble_count = 1
   - Tournament with 3 paid positions: player finishes 4th → bubble_count = 1
   - Tournament with 10 paid positions: player finishes 1st → bubble_count = 0
   - Tournament without `paid_positions` set: verify error_log warning

2. **Verification**:
   - Compare bubble counts in season_leaderboard vs series standings
   - Verify counts match 100% when `paid_positions` is set
   - Check error_log for warnings about missing `paid_positions`

### Dependencies

- `paid_positions` meta field must be populated during tournament import (already implemented in feature 009)
- Series standings class implementation (reference for correct logic)

## Implementation Tasks

### Task 1: Remove SQL-based bubble calculation

**File**: `includes/class-shortcodes.php`
**Location**: `render_season_leaderboard()` function, around line 4718
**Action**: Remove the SQL CASE statement that calculates bubble_count

### Task 2: Add PHP-based bubble calculation

**File**: `includes/class-shortcodes.php`
**Location**: After SQL query execution in `render_season_leaderboard()`
**Action**:
1. Loop through result set
2. For each row, get `tournament_post_id`
3. Look up `paid_positions` via `get_post_meta($tournament_post_id, 'paid_positions', true)`
4. If `paid_positions` is set and `finish_position == paid_positions + 1`, increment bubble_count
5. Log warning if `paid_positions` is missing

### Task 3: Update plugin version

**File**: `poker-tournament-import.php`
**Location**: Line 5 (plugin header), Line 22 (version constant)
**Action**: Bump version to 3.5.0-beta52

### Task 4: Manual testing

**Steps**:
1. Deploy updated plugin to test site
2. View season leaderboard shortcode on frontend
3. Verify bubble counts match expected values
4. Compare with series standings data
5. Check error_log for warnings

### Task 5: Create distribution package

**File**: Create `poker-tournament-import-v3.5.0-beta52.zip`
**Location**: Repository root
**Action**: Package updated plugin files

## Quickstart

### For Developers

To implement this fix:

1. **Read reference implementation**:
   ```bash
   # View correct bubble calculation
   grep -A 15 "Detect bubble finish" wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
   ```

2. **Modify shortcode handler**:
   ```bash
   # Edit the season_leaderboard function
   wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
   ```

3. **Test changes**:
   ```bash
   # Verify PHP syntax
   php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
   ```

4. **Deploy and verify**:
   - Upload to WordPress test site
   - View season leaderboard page
   - Check bubble_count column for accuracy

### Key Files

- **Reference**: `includes/class-series-standings.php:310-322`
- **Target**: `includes/class-shortcodes.php:4500+` (render_season_leaderboard function)
- **Version**: `poker-tournament-import.php:5, 22`

## Success Criteria

From spec.md:
- **SC-001**: Bubble counts in season_leaderboard match series standings 100% of the time
- **SC-002**: Calculation uses `paid_positions` meta field (not SQL subquery)
- **SC-003**: Error logging when `paid_positions` is missing
- **SC-004**: Accurate across varying prize structures (3, 5, 10 paid positions)

Definition of Done:
- [ ] Season leaderboard uses `paid_positions` meta field
- [ ] Old SQL-based calculation removed
- [ ] Bubble calculation matches series standings
- [ ] Error logging added for missing data
- [ ] Manual testing confirms accuracy
- [ ] No PHP errors or warnings
