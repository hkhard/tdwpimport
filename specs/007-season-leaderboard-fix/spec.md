# Feature Specification: Season Leaderboard Formula & Stats Enhancement

**Feature Branch**: `007-season-leaderboard-fix`
**Created**: 2025-01-02
**Status**: Draft
**Input**: User description: "polish for season_leaderboard shortcode: season points are now reflecting total points, they should follow active season formula. in show_details="true" we add also stats for bubble, last, and hits."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Correct Season Points Calculation (Priority: P1)

As a league administrator, I need the season leaderboard to display points calculated according to the active season formula (not total points) so that players see their actual standing for the season.

**Why this priority**: This is a data accuracy bug - players are seeing incorrect point totals which affects rankings and league integrity.

**Independent Test**: Can be fully tested by viewing the `[season_leaderboard]` shortcode output and verifying that the "Points" column matches the configured season formula (e.g., if formula counts best 8 of 10 tournaments, points should reflect that calculation, not the sum of all tournaments).

**Acceptance Scenarios**:

1. **Given** a season with 10 tournaments per player and a "best 8" formula configured, **When** viewing the season leaderboard, **Then** the Points column displays each player's best 8 tournament results (not the sum of all 10)
2. **Given** a season with the "season_total" formula (sum all tournaments), **When** viewing the season leaderboard, **Then** the Points column shows the sum of all tournament points for each player
3. **Given** a formula override in the shortcode (e.g., `[season_leaderboard formula="custom"]`), **When** viewing the leaderboard, **Then** the Points column uses the specified formula instead of the default

---

### User Story 2 - Enhanced Statistics Display (Priority: P2)

As a player, I want to see detailed performance statistics including bubble finishes, last-place finishes, and total hits when I view the detailed leaderboard so I can better understand my tournament performance patterns.

**Why this priority**: This adds valuable statistics that players already track manually. The show_details parameter already exists, so this extends existing functionality rather than adding new UI complexity.

**Independent Test**: Can be fully tested by adding `show_details="true"` to the shortcode and verifying that three new columns appear with accurate counts for each player.

**Acceptance Scenarios**:

1. **Given** the shortcode `[season_leaderboard show_details="true"]`, **When** viewing the leaderboard, **Then** the table includes columns for "Bubble", "Last", and "Hits" after the existing detail columns
2. **Given** a player who finished in the bubble position (one spot outside the money) in 2 tournaments, **When** viewing the detailed leaderboard, **Then** that player's "Bubble" column shows "2"
3. **Given** a player who finished last in 1 tournament, **When** viewing the detailed leaderboard, **Then** that player's "Last" column shows "1"
4. **Given** a player who achieved 15 total hits across all season tournaments, **When** viewing the detailed leaderboard, **Then** that player's "Hits" column shows "15"
5. **Given** the shortcode `[season_leaderboard show_details="false"]` (default), **When** viewing the leaderboard, **Then** the Bubble, Last, and Hits columns are hidden

---

### User Story 3 - Column Header Consistency (Priority: P3)

As a user viewing the leaderboard, I need clear column headers that accurately describe the data shown so I can quickly understand what each column represents.

**Why this priority**: This is a minor UX improvement that enhances usability but doesn't block the core functionality.

**Independent Test**: Can be fully tested by viewing the rendered table and verifying column headers are descriptive and accurate.

**Acceptance Scenarios**:

1. **Given** the season leaderboard with formula-based points, **When** viewing the column headers, **Then** the main points column is labeled based on the formula used (e.g., "Points (Best 8)" or "Season Points")
2. **Given** the detailed leaderboard, **When** viewing all column headers, **Then** they follow a consistent naming pattern (Player, Points, Played, Best, Avg, 1st, Top 3, Top 5, Bubble, Last, Hits)

---

### Edge Cases

- What happens when a season has no tournaments configured? Display appropriate "no data" message
- What happens when the active season formula is not set? Default to "season_total" (sum all tournaments)
- What happens when a player has zero bubble finishes, last-place finishes, or hits? Display "0" in the respective columns
- What happens when tournament data is missing bubble/last position information (older imports)? Calculate based on available finish_position data
- What happens when viewing a season with tournaments that have different player counts? Bubble and last place calculations are per-tournament based on that tournament's finish positions

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST calculate season points using the active season formula (stored in `tdwp_active_season_formula` option) instead of displaying total points
- **FR-002**: System MUST support formula override via shortcode `formula` parameter to display standings using different calculation methods
- **FR-003**: System MUST display formula-calculated points in the main "Points" column of the season leaderboard table
- **FR-004**: System MUST calculate and display bubble finish count when `show_details="true"` is set (number of times player finished one position outside paid spots)
- **FR-005**: System MUST calculate and display last-place finish count when `show_details="true"` is set (number of times player finished in the final position)
- **FR-006**: System MUST display total hits count when `show_details="true"` is set (sum of all hits across season tournaments)
- **FR-007**: System MUST hide bubble, last, and hits columns when `show_details="false"` or not specified (default behavior)
- **FR-008**: System MUST handle edge cases where tournaments have varying player counts and bubble positions
- **FR-009**: System MUST maintain existing tie-breaker behavior (first places, top 3, top 5 finishes) alongside new statistics

### Key Entities

- **Season Standings**: Aggregated tournament results for all players in a season, including calculated points and performance statistics
- **Season Formula**: Mathematical formula used to calculate season points from individual tournament results (e.g., "best 8 of 10", "sum all", "drop lowest 2")
- **Bubble Finish**: A tournament result where the player finished one position outside the paid places (e.g., 11th in a 10-person payout)
- **Last Place Finish**: A tournament result where the player finished in the final position for that tournament
- **Hits**: Statistical measure from Tournament Director data representing player achievements (already stored in database)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Season points displayed in leaderboard match the configured season formula calculation 100% of the time
- **SC-002**: Detailed statistics (bubble, last, hits) display accurately when `show_details="true"` with zero calculation errors
- **SC-003**: Leaderboard rendering completes within 2 seconds for seasons with up to 50 players and 20 tournaments
- **SC-004**: Users can successfully distinguish between formula-calculated points and total tournament points at a glance (clear column headers)
- **SC-005**: Existing shortcode functionality (limit, export, print) continues to work without regression
- **SC-006**: Mobile-responsive layout accommodates 3 additional columns without horizontal scrolling issues on standard mobile devices

## Assumptions

1. The season formula system is already implemented and functional (confirmed from existing codebase)
2. Bubble position can be calculated from tournament data by finding the position one spot outside the highest paid position
3. Last place is the maximum `finish_position` value for each tournament
4. Hits are already stored in the `wp_poker_tournament_players` table and available in the standings calculator
5. The `calculate_player_series_data` method already retrieves hits from the database
6. Tournament data includes accurate `finish_position` values for bubble/last calculations
7. Season formula is stored in WordPress options as `tdwp_active_season_formula`
8. Transient caching for season standings should continue to work with the corrected calculations
