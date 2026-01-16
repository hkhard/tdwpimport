# Feature Specification: Bubble Calculation Fix for Season Leaderboard

**Feature Branch**: `010-bubble-calc-fix`
**Created**: 2025-01-04
**Status**: Ready for Planning
**Input**: User description: "the bubble calculation for season_leaderboard short code is not working. this need to be fixed."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Fix Bubble Count Display (Priority: P1)

Administrators view season leaderboards to track player performance across tournaments. The bubble count column should accurately show how many times each player finished in the last unpaid position (the "bubble" position).

**Why this priority**: Incorrect bubble counts misrepresent player performance statistics and can affect player rankings and bragging rights.

**Independent Test**: Can be tested by importing tournaments with known paid positions, viewing the season leaderboard shortcode, and verifying bubble counts match the actual tournament results.

**Acceptance Scenarios**:

1. **Given** a tournament with 5 paid positions, **when** a player finishes in 6th place, **then** their bubble_count should increment by 1
2. **Given** a tournament with 3 paid positions, **when** a player finishes in 4th place, **then** their bubble_count should increment by 1
3. **Given** a tournament with 10 paid positions, **when** a player finishes in 1st place (paid), **then** their bubble_count should NOT increment
4. **Given** multiple tournaments in a season, **when** viewing the season leaderboard, **then** bubble_count should show the total across all tournaments
5. **Given** tournaments where paid_positions meta field is not set, **when** calculating bubble counts, **then** the system should log a warning and skip bubble calculation for those tournaments

### User Story 2 - Use Consistent Calculation Method (Priority: P1)

The season leaderboard shortcode should use the same bubble calculation logic as the series standings class to ensure consistency across the plugin.

**Why this priority**: Having two different calculation methods leads to inconsistent data and user confusion.

**Independent Test**: Can be tested by comparing bubble counts in the season leaderboard shortcode output with the series standings data for the same season.

**Acceptance Scenarios**:

1. **Given** both the season_leaderboard shortcode and series standings calculate bubble_count, **when** comparing the results, **then** both should show identical bubble counts
2. **Given** the paid_positions meta field is properly set during tournament import, **when** either shortcode or series standings runs, **then** both should use this meta field for calculation

---

### Edge Cases

- What happens when paid_positions meta field is 0 (no paid positions)?
- What happens when paid_positions meta field is NULL or not set?
- What happens when a tournament has all players in paid positions (no bubble possible)?
- What happens when finish_position data is missing or NULL?
- What happens in tournaments with non-standard prize structures (e.g., winner-take-all)?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST calculate bubble_count as the number of times a player finishes in position (paid_positions + 1)
- **FR-002**: System MUST retrieve paid_positions from tournament post meta field for bubble calculation
- **FR-003**: System MUST log a warning when paid_positions meta field is missing or NULL
- **FR-004**: System MUST skip bubble calculation for tournaments where paid_positions is not available
- **FR-005**: System MUST use the same bubble calculation logic in both season_leaderboard shortcode and series standings class
- **FR-006**: System MUST aggregate bubble counts across all tournaments in a season for the total
- **FR-007**: Season leaderboard shortcode MUST remove the old SQL-based bubble calculation subquery

### Key Entities

- **Tournament**: Represents an individual poker tournament with paid_positions metadata
- **Player Result**: Represents a player's finish in a specific tournament with finish_position
- **Season Leaderboard**: Aggregates player results across multiple tournaments
- **Bubble Count**: Metric tracking how many times a player finished immediately outside paid positions

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Bubble counts displayed in season_leaderboard shortcode match series standings data 100% of the time when paid_positions is set
- **SC-002**: Bubble calculation uses paid_positions meta field (not SQL subquery) in both shortcode and series standings
- **SC-003**: Error logging occurs when paid_positions meta field is missing, with tournament UUID included
- **SC-004**: Bubble counts are accurate across tournaments with varying prize structures (3 paid, 5 paid, 10 paid, etc.)

### Definition of Done

- [ ] Season leaderboard shortcode bubble calculation uses paid_positions meta field
- [ ] Old SQL-based bubble count subquery removed from shortcode
- [ ] Bubble calculation logic matches series standings implementation
- [ ] Error logging added for missing paid_positions
- [ ] Manual testing confirms bubble counts are accurate
- [ ] No PHP errors or warnings related to bubble calculation

## Assumptions

1. The `paid_positions` meta field is populated during tournament import (from previous feature implementation)
2. The series standings class (class-series-standings.php) has the correct bubble calculation implementation
3. The season_leaderboard shortcode currently uses an outdated SQL-based calculation
4. Users want bubble counts to accurately reflect "one position outside the money" finishes
5. Standard poker bubble definition: player who finishes in the last position before paid places (e.g., 6th place when 5 places paid)

## Scope

### In Scope

- Fixing bubble_count calculation in season_leaderboard shortcode
- Removing old SQL-based bubble calculation subquery
- Reusing bubble calculation logic from series standings class
- Adding error logging for missing data
- Manual testing and verification

### Out of Scope

- Modifying tournament import logic (assumed to work from previous feature)
- Changes to series standings class (already correct)
- UI changes to leaderboard display format
- Adding new metrics or statistics
- Performance optimization (unless current implementation is critically slow)

## Dependencies

- Tournament import must populate paid_positions meta field (from User Story 4 of feature 009)
- Series standings class bubble calculation must be working correctly
- WordPress wp_cache_get/wp_cache_set for caching query results
