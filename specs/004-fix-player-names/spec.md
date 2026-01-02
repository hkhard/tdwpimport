# Feature Specification: Fix Player Names Display in Season Leaderboard

**Feature Branch**: `004-fix-player-names`
**Created**: 2026-01-02
**Status**: Draft
**Input**: User description: "in shortcode season_leaderboard we have guids instead of player names. we must remove guids and add playe names instead"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Display Player Names Instead of GUIDs (Priority: P1)

Website visitors viewing the season leaderboard shortcode expect to see human-readable player names, not technical GUIDs (UUIDs). When player posts exist in the system, those player names should be displayed. If player posts do not exist for a GUID, a generic placeholder should be shown instead of the raw UUID.

**Why this priority**: This is critical data display issue. Users cannot identify players by UUID, making the leaderboard unusable. This is the core problem described by the user and must be fixed first.

**Independent Test**: Can be fully tested by viewing the [season_leaderboard] shortcode output on any page and verifying all player entries show either actual names or placeholders, not GUIDs.

**Acceptance Scenarios**:

1. **Given** a season with tournaments and players who have player posts created, **When** the season_leaderboard shortcode is rendered, **Then** all player rows display the player's name from their player post
2. **Given** a season with tournament results for a GUID that has no corresponding player post, **When** the season_leaderboard shortcode is rendered, **Then** the player row displays a generic placeholder like "Unknown Player" instead of the GUID
3. **Given** a season with no tournament data, **When** the season_leaderboard shortcode is rendered, **Then** the existing "no data" message is displayed (no change to current behavior)

---

### User Story 2 - Link to Player Profiles When Available (Priority: P2)

When a player post exists for a leaderboard entry, the player name should be a clickable link to that player's profile page, maintaining existing functionality.

**Why this priority**: This is a lower priority because the critical issue (showing GUIDs) is addressed in P1. This story preserves existing linking behavior once names are properly displayed.

**Independent Test**: Can be tested by clicking player names in the rendered leaderboard and verifying they navigate to the correct player profile pages.

**Acceptance Scenarios**:

1. **Given** a player entry with an existing player post, **When** the leaderboard is rendered, **Then** the player name is a clickable link to their player profile page
2. **Given** a player entry without an existing player post, **When** the leaderboard is rendered, **Then** the player placeholder text is displayed as plain text (not linked)

---

### Edge Cases

- What happens when the player post query returns multiple results for the same UUID? (Should use first result)
- What happens when a player post exists but has an empty post_title? (Should show placeholder)
- What happens with special characters in player names? (Should be properly escaped)
- How does system handle players who participated but have no tournament results? (Should not appear in standings)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display human-readable player names in the season_leaderboard shortcode output instead of GUID/UUID strings
- **FR-002**: System MUST query player posts by UUID (_player_uuid meta key) to retrieve player names for each leaderboard entry
- **FR-003**: System MUST display a generic placeholder ("Unknown Player" or similar) when no player post exists for a GUID
- **FR-004**: System MUST escape player names for safe HTML output to prevent XSS issues
- **FR-005**: System MUST link player names to their player profile pages when player posts exist
- **FR-006**: System MUST display placeholder text as plain text (not linked) when no player post exists
- **FR-007**: System MUST handle empty player post titles by treating them as missing (use placeholder)
- **FR-008**: System MUST use the first player post if multiple posts exist for the same UUID

### Key Entities

- **Season Leaderboard**: Aggregated standings display showing player rankings, points, and tie-breaker statistics for a tournament season
- **Player Entry**: Individual row in the leaderboard containing player identification, rank, points, and statistics
- **Player Post**: WordPress custom post type (player) containing player profile information, identified by _player_uuid meta key
- **GUID/UUID**: Technical identifier used internally to link tournament results to players (not user-facing)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of player entries in season_leaderboard shortcode display human-readable text (no GUIDs visible to end users)
- **SC-002**: Zero GUID/UUID strings appear in the rendered HTML output of the season_leaderboard shortcode
- **SC-003**: All existing player profile links remain functional after the fix
- **SC-004**: Page load time for season_leaderboard shortcode does not increase by more than 10% compared to current implementation
- **SC-005**: No PHP warnings or errors are generated when rendering leaderboards with missing player posts

## Assumptions

1. Player posts are identified in the system by the _player_uuid meta key
2. Tournament results store player references as GUIDs in the custom poker_tournament_players table
3. The calculate_player_series_data() method in class-series-standings.php is where the GUID-to-name lookup happens
4. Existing player post queries use get_posts() with meta_query on _player_uuid
5. The fallback to GUID occurs when get_posts() returns empty results
6. Generic placeholder text "Unknown Player" is acceptable for missing player posts
7. Performance impact of additional player post queries is acceptable (current caching should mitigate)

## Dependencies

- WordPress custom post type: player
- WordPress meta key: _player_uuid
- Existing class: Poker_Series_Standings_Calculator in includes/class-series-standings.php
- Existing shortcode handler: season_leaderboard_shortcode in includes/class-shortcodes.php

## Out of Scope

- Creating new player posts for missing GUIDs (this is a separate feature)
- Bulk import or sync of player data
- Changing how GUIDs are stored in the database
- Modifying other leaderboards or shortcodes (only season_leaderboard is in scope)
