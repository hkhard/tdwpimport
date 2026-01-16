# Feature Specification: Season Points Formula Honor Active Formula

**Feature Branch**: `012-season-points-formula-fix`
**Created**: 2026-01-04
**Status**: Draft
**Input**: User description: "fix Season Points to honor the active season formula. Rank Player Points Played Best Avg 1st Top 3 Top 5 Bubble Last Hits Season Points 1 🥇 Fredrik Y 2,043.0 18 1 5.3 1 7 11 0 0 36 2,043.0 2 🥈 Joakim H 1,770.0 19 1 7.1 1 7 8 0 4 33 1,770.0 this is an example where the Points are equal to Season Points, but the active formula for season points is: season_total which only counts top 10 results. This is not reflected in the season 2025 standings and needs fixing."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Apply Active Formula to Season Points Calculation (Priority: P1)

League administrators configure active season formulas (e.g., "best_10" which counts only top 10 tournament results) and expect the Season Points column to reflect this formula when viewing season leaderboards. Currently, the Season Points column shows the same value as the Points column (simple sum of all tournaments), ignoring the configured formula.

**Why this priority**: This is a data accuracy bug that renders the active season formula feature non-functional. Administrators rely on accurate season points for fair rankings and prize distribution.

**Independent Test**: Can be tested by setting an active season formula (e.g., "best_10"), viewing a season leaderboard where players have more than 10 tournaments, and verifying the Season Points column only counts the top 10 results (not all tournaments).

**Acceptance Scenarios**:

1. **Given** an active season formula configured as "best_10" (count only top 10 results), **when** a player has 18 tournaments with varying points, **then** the Season Points column should show the sum of their best 10 tournament results only
2. **Given** an active season formula configured as "best_10", **when** a player has only 5 tournaments in the season, **then** the Season Points column should show the sum of all 5 tournaments (formula applies to available results)
3. **Given** an active season formula configured as "season_total" (sum of all tournaments), **when** viewing any season leaderboard, **then** the Season Points column should equal the Points column (both show sum of all results)
4. **Given** a player with 18 tournaments where the top 10 sum to 2,043 points but all 18 sum to 2,500 points, **when** the active formula is "best_10", **then** Season Points should show 2,043.0 (not 2,500.0)
5. **Given** the active formula is changed from "best_10" to "best_5", **when** refreshing the season leaderboard, **then** Season Points should immediately reflect only the top 5 tournament results

### User Story 2 - Maintain Points Column as Simple Sum (Priority: P2)

The Points column should continue to show the simple sum of all tournament points regardless of the active formula, providing administrators with a quick reference for total performance across all tournaments.

**Why this priority**: Preserves existing functionality while fixing the Season Points bug. Administrators use both metrics for different purposes (total performance vs. formula-based ranking).

**Independent Test**: Can be tested by viewing any season leaderboard and verifying the Points column matches the manual sum of all tournament points for each player, regardless of active formula setting.

**Acceptance Scenarios**:

1. **Given** an active season formula is set to "best_10", **when** viewing the season leaderboard, **then** the Points column should show the sum of ALL tournament results (not just top 10)
2. **Given** a player with 18 tournaments totaling 2,500 points, **when** any active formula is configured, **then** the Points column should display 2,500.0 (simple sum)
3. **Given** the Points column shows 2,500.0 and active formula is "best_10" with top 10 summing to 2,043.0, **when** comparing the two columns, **then** Points should be greater than or equal to Season Points (Points ≥ Season Points)

### User Story 3 - Support All Season Formula Types (Priority: P2)

The season leaderboard should correctly apply all configured season formula types, including formulas that filter by count (best_10, best_5), sum all results (season_total, direct_sum), or use custom weighting schemes.

**Why this priority**: Ensures the fix works comprehensively across all formula types, not just the specific "best_10" example reported.

**Independent Test**: Can be tested by configuring different active formulas (best_5, best_10, season_total, custom formulas) and verifying Season Points calculates correctly for each formula type.

**Acceptance Scenarios**:

1. **Given** active formula is "best_5", **when** a player has 15 tournaments, **then** Season Points should sum only the top 5 results
2. **Given** active formula is "season_total" or "direct_sum", **when** viewing any season, **then** Season Points should equal Points (sum of all results)
3. **Given** a custom formula with complex dependencies (e.g., weighted average with bonus for final table appearances), **when** this formula is set as active, **then** Season Points should reflect the custom calculation
4. **Given** multiple players in a season with varying tournament counts, **when** applying "best_10" formula, **then** Season Points rankings should accurately reflect top 10 performance (not total volume)

---

### Edge Cases

- What happens when a player has fewer tournaments than the formula requires (e.g., 5 tournaments when formula is "best_10")?
- What happens when the active formula is NULL or not set?
- What happens when a player has zero tournaments in the season?
- What happens when tournament points are NULL or missing for some tournaments?
- What happens when the active formula references variables that don't exist in the formula input?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST calculate Season Points by applying the active season formula to each player's complete tournament results array (not per-tournament evaluation)
- **FR-002**: System MUST retrieve the active season formula from the configured WordPress option (tdwp_active_season_formula or poker_active_season_formula)
- **FR-003**: System MUST pass the complete array of tournament points to the formula validator in a single calculation (not accumulated per tournament)
- **FR-004**: System MUST display Season Points as a separate column from Points, with formula-based calculation applied
- **FR-005**: System MUST continue calculating Points as a simple sum of all tournament results (no formula applied)
- **FR-006**: System MUST support formulas that filter results (e.g., best_10, best_5) by sorting tournament points and selecting top N
- **FR-007**: System MUST handle edge cases where player has fewer tournaments than formula requires (use available tournaments)
- **FR-008**: System MUST default to simple sum (Points = Season Points) when no active formula is configured
- **FR-009**: System MUST remove or bypass the per-tournament formula evaluation loop that currently accumulates season_points incorrectly
- **FR-010**: System MUST use the existing apply_series_formula() method which correctly handles formula evaluation with tournament points array

### Key Entities

- **Season Formula**: A configured calculation rule (e.g., "best_10", "season_total") that determines how tournament results are combined for season standings
- **Tournament Points Array**: Complete list of point values for all tournaments a player participated in during a season
- **Season Points**: Formula-calculated total points based on active season formula (e.g., sum of top 10 results)
- **Points**: Simple sum of all tournament points regardless of formula (baseline metric)
- **Formula Validator**: Component that evaluates formulas with dependencies and variables to produce calculated results

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Season Points column displays different values than Points column when active formula filters results (e.g., best_10 with 18+ tournaments)
- **SC-002**: Season Points calculation produces correct results when tested against manual calculation for best_10 formula
- **SC-003**: Formula filtering logic (top N selection) works correctly for players with varying tournament counts
- **SC-004**: Season leaderboard rendering time does not increase significantly (maintain under 5 seconds for 100+ player seasons)
- **SC-005**: Changing active formula immediately updates Season Points display (after cache refresh)

### Definition of Done

- [ ] Season Points calculation uses active season formula via apply_series_formula() method
- [ ] Per-tournament formula evaluation loop removed or bypassed
- [ ] Season Points column displays formula-based results distinct from Points column
- [ ] Points column continues showing simple sum of all tournaments
- [ ] Manual testing confirms best_10 formula produces correct top-10 sums
- [ ] Edge cases handled (fewer tournaments than formula requires, NULL formula, zero tournaments)
- [ ] No PHP errors or warnings related to formula calculation
- [ ] Cache invalidation works when active formula changes

## Assumptions

1. The active season formula is stored in WordPress option: tdwp_active_season_formula or poker_active_season_formula
2. The apply_series_formula() method in class-series-standings.php already has correct structure for formula evaluation
3. Formula variables include tournament_points array which can be sorted and filtered
4. Current bug is caused by per-tournament evaluation loop (lines 287-329 in class-series-standings.php)
5. Statistics Engine class (class-statistics-engine.php) has reference implementation showing correct pattern
6. Formulas like "best_10" sort the tournament_points array and sum the first 10 elements
7. Users expect Season Points to reflect the configured formula for fair ranking

## Scope

### In Scope

- Fixing Season Points calculation to honor active season formula in season_leaderboard shortcode
- Removing or bypassing incorrect per-tournament formula evaluation loop
- Using existing apply_series_formula() method for correct calculation
- Ensuring Season Points column displays formula-based results
- Maintaining Points column as simple sum (no regression)
- Manual testing with best_10 formula and other formula types
- Handling edge cases (fewer tournaments, NULL formula, empty results)

### Out of Scope

- Modifying formula validator logic or formula definitions
- Adding new formula types (use existing formulas only)
- UI changes to season leaderboard display format
- Performance optimization beyond ensuring fix doesn't degrade performance
- Formula management interface improvements (admin pages for formula configuration)
- Changes to tournament import or data storage
- Modifying Points column calculation (keep as simple sum)

## Dependencies

- Active season formula must be properly configured in WordPress options
- apply_series_formula() method must correctly handle tournament points array
- Formula validator must support sorting and array operations (top N selection)
- Tournament points data must be correctly populated for all tournaments
- WordPress transients cache for season standings must invalidate when formula changes
