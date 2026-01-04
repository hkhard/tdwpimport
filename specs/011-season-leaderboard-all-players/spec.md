# Feature Specification: Always Show All Players in Detailed Season Leaderboard

**Feature Branch**: `011-season-leaderboard-all-players`
**Created**: January 4, 2026
**Status**: Draft
**Input**: User description: "in season_leaderboard detailed_info="true" we need to return every player registered in the selected season. currently we only see top 20."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Complete Season Standings (Priority: P1)

As a league administrator, I want to see complete standings for a season with detailed statistics so that I can review all players' performance, not just the top 20.

**Why this priority**: This is the primary use case - administrators need full visibility into season standings for reporting, awards, and analysis. The current 20-player limit prevents comprehensive review of season data.

**Independent Test**: Can be fully tested by viewing a season leaderboard shortcode with `show_details="true"` on any page and verifying all registered players for that season are displayed with their detailed statistics.

**Acceptance Scenarios**:

1. **Given** a season with 50+ registered players, **When** I display the season_leaderboard shortcode with `show_details="true"`, **Then** all 50+ players are shown with their complete statistics
2. **Given** a season leaderboard with `show_details="true" limit="10"`, **When** the page loads, **Then** all players in the season are displayed (the limit parameter is ignored)
3. **Given** a season with only 5 players, **When** I use `show_details="true"`, **Then** all 5 players are shown with their detailed tie-breaker columns

---

### User Story 2 - Preserve Standard View Limit (Priority: P2)

As a site visitor, I want to see a manageable top-20 leaderboard by default so that I can quickly view the leaders without overwhelming the page with data.

**Why this priority**: Maintains existing behavior for standard views and ensures good performance for typical use cases.

**Independent Test**: Can be tested by viewing season_leaderboard WITHOUT the `show_details` parameter and verifying only 20 players are shown by default.

**Acceptance Scenarios**:

1. **Given** a season with 50 players, **When** I display season_leaderboard without `show_details`, **Then** only the top 20 players are shown
2. **Given** a season leaderboard with `show_details="false" limit="15"`, **When** the page loads, **Then** only 15 players are shown (limit parameter is respected)

---

### Edge Cases

- What happens when a season has 0 players? → Display "No players found for this season" message
- What happens when a season has 200+ players? → Display all players with detailed stats; page may be long but complete
- What happens when limit is set to 0 or negative with show_details="false"? → Respect existing validation/defaults
- What happens when both show_details="true" and an explicit limit are provided? → Show all players (limit is ignored)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: When season_leaderboard shortcode includes `show_details="true"`, the system MUST display ALL players registered in the selected season, regardless of any limit parameter value
- **FR-002**: When season_leaderboard shortcode includes `show_details="true" limit="N"`, the system MUST ignore the limit parameter and display all players
- **FR-003**: When season_leaderboard shortcode excludes show_details or uses `show_details="false"`, the system MUST respect the limit parameter (default 20)
- **FR-004**: The system MUST calculate and display all tie-breaker statistics (Played, Best, Avg, 1st, Top3, Top5, Bubble, Last, Hits) for each player when show_details="true"
- **FR-005**: Player rankings MUST be correctly calculated across all players in the season when show_details="true"

### Key Entities

- **Season Standings**: Aggregated player statistics across all tournaments in a season, including points earned and tie-breaker metrics
- **Player**: Individual participant in one or more tournaments within a season
- **Season**: Collection of tournaments grouped together for standings calculation

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: When viewing any season with `show_details="true"`, 100% of registered players for that season are displayed on the page
- **SC-002**: When `show_details="true" limit="10"` is used on a season with 50+ players, all 50+ players are visible (limit is completely ignored)
- **SC-003**: When `show_details="false"` is used, only the specified number of players (or default 20) are displayed, preserving existing performance characteristics
- **SC-004**: Page load time for detailed view with 100+ players completes in under 5 seconds on standard hosting

## Assumptions

- The season_leaderboard shortcode is the correct method for displaying season standings (this is existing functionality being enhanced)
- "detailed_info" in the user request refers to the existing `show_details` parameter
- Users want to see all players with detailed stats when they opt into the detailed view
- Standard non-detailed view should retain the 20-player default for performance and usability
- WordPress hosting can handle rendering larger pages (200+ rows) without performance degradation
