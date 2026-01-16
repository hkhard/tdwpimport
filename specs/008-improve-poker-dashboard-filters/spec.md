# Feature Specification: Poker Dashboard Filter Persistence

**Feature Branch**: `008-improve-poker-dashboard-filters`
**Created**: 2025-01-02
**Updated**: 2025-01-02
**Status**: Implementation In Progress
**Input**: User description: "The filter drop down in poker_dashboard short code needs to be persistent to the selected filter per user session."

## User Scenarios & Testing

### User Story 1 - Always-Visible Apply Filter Button (Priority: P1)

As a poker league administrator viewing the season leaderboard dashboard, I want the "Apply Filters" button to be always visible (not just on hover) so that I can immediately understand how to apply my filter selection without having to discover the button through mouse movement.

**Why this priority**: This is a critical UX issue - users may not realize the button exists if it only appears on hover, leading to confusion about how to apply filters. The button's visibility is essential for the core filtering functionality.

**Independent Test**: Can be fully tested by loading the poker dashboard page and verifying the "Apply Filters" button is visible without hovering over the filter area.

**Acceptance Scenarios**:

1. **Given** a user visits the poker dashboard page, **When** the page loads, **Then** the "Apply Filters" button should be immediately visible with no user interaction required
2. **Given** a user has selected a filter option, **When** they move their mouse away from the filter area, **Then** the "Apply Filters" button should remain visible
3. **Given** a user is on mobile/touch device, **When** they view the filter section, **Then** the "Apply Filters" button should be visible without any hover interaction
4. **Given** a user views the dashboard, **When** they inspect the button, **Then** it should have a visible background color (not transparent)

### User Story 2 - Persistent Filter Selection Per User Session (Priority: P1)

As a poker league administrator, I want my season filter selection to persist across page views during my session so that I don't have to re-select the same season every time I navigate the dashboard or refresh the page.

**Why this priority**: This improves workflow efficiency and reduces user frustration. Administrators frequently work within a specific season context and shouldn't need to repeatedly select it.

**Independent Test**: Can be fully tested by selecting a season filter, refreshing the page or navigating away and back, and verifying the selected season remains active.

**Acceptance Scenarios**:

1. **Given** a user selects "Season 2025" from the dropdown, **When** they click "Apply Filters", **Then** the dashboard should show filtered results for Season 2025
2. **Given** a user has selected "Season 2025" and applied filters, **When** they refresh the page (F5), **Then** the dropdown should still show "Season 2025" as selected and the URL should contain `?filter_season=2025`
3. **Given** a user has selected "Season 2025" and applied filters, **When** they navigate away from the dashboard and return, **Then** the dashboard should still be filtered to Season 2025
4. **Given** a user wants to change seasons, **When** they select "All Seasons" and apply, **Then** the new selection should persist for subsequent page views
5. **Given** a user has a saved filter preference, **When** they manually edit the URL to use a different season, **Then** the URL parameter should take precedence over the saved preference

### Edge Cases

- What happens when a user's selected season no longer exists (e.g., season deleted)?
- What happens when multiple seasons are available and the default "most recent" changes?
- What happens when a user clears their browser cache/cookies - should preferences persist?
- What happens on first visit when no preference is saved - what should be default?
- What happens when URL parameters conflict with saved preferences?
- What happens when a user session expires - should filter preferences be restored?

## Requirements

### Functional Requirements

- **FR-001**: System MUST display the "Apply Filters" button with full opacity/visibility at all times (no hover-dependent visibility)
- **FR-002**: System MUST maintain the selected filter value across page refreshes during a user session via URL parameters
- **FR-003**: System MUST maintain the selected filter value when user navigates away and returns to the dashboard
- **FR-004**: System MUST save filter preferences to WordPress user meta for persistence across sessions
- **FR-005**: System MUST support changing filter selections with the new selection persisting after application
- **FR-006**: System MUST handle the case when a saved season no longer exists (fallback to most recent season or "all")
- **FR-007**: Filter dropdown MUST visually indicate which option is currently selected/active
- **FR-008**: System MUST prioritize URL parameters over saved preferences to allow manual override via URL
- **FR-009**: System MUST define CSS variables for dashboard theme colors to ensure button visibility
- **FR-010**: System MUST validate that saved filter selections still exist before applying them

### Key Entities

- **Dashboard Filter Configuration**: Defines available filters (season, series, etc.), their types, options, and defaults
- **User Filter Preferences**: Per-user saved filter selections stored in WordPress user meta (key: `poker_dashboard_filters`)
- **Active Filters**: Current filter state determined by priority chain: URL parameters → saved preferences → config defaults
- **CSS Custom Properties**: Theme variables for dashboard styling (--dashboard-primary, --dashboard-bg-surface, etc.)

## Success Criteria

### Measurable Outcomes

- **SC-001**: "Apply Filters" button has 100% visibility (opacity: 1, visible without hover, with visible background color) on page load
- **SC-002**: Filter selection persists across page refreshes with 100% reliability
- **SC-003**: Filter selection persists when navigating away and back to dashboard with 100% reliability
- **SC-004**: Zero user confusion about how to apply filters (button always visible with proper styling)
- **SC-005**: Users can work within a specific season context without re-selecting filters across session
- **SC-006**: First-time visitors see the most recent season selected by default

### Non-Functional Requirements

- **Performance**: Filter persistence should not add noticeable latency to page load (<100ms additional processing)
- **Compatibility**: Must work on mobile/touch devices without hover capability
- **Accessibility**: Button visibility must comply with WCAG AA standards for users who may not use pointer devices
- **Backward Compatibility**: Existing filter functionality must continue to work for users with saved preferences
- **Browser Independence**: Filter persistence must work regardless of browser cache settings (server-side storage required)

## Assumptions

1. **WordPress User Meta Storage**: The system will use WordPress's built-in user meta table for storing filter preferences, which persists across browser sessions and is server-side (independent of browser cache)
2. **URL Parameter Priority**: URL parameters will take precedence over saved preferences to allow manual override and bookmarkability
3. **Default Behavior**: When no filter is selected, the system defaults to the most recent season available in the database
4. **Filter Scope**: This specification focuses on the season filter; additional filters (series, status) follow the same pattern
5. **User Session**: For the purposes of this feature, "session" refers to the user's WordPress login session (not limited to a single browser session)
6. **CSS Variables**: The button visibility issue is caused by undefined CSS custom properties that default to transparent, requiring explicit variable definitions

## Implementation Notes

**Current State** (based on investigation):
- Button visibility issue: CSS file uses undefined custom properties (--dashboard-primary, etc.) which default to transparent, causing white text on transparent background
- Filter persistence: Already partially implemented via URL parameters and WordPress user meta storage
- Priority chain already implemented: URL params → saved preferences → defaults

**Required Changes**:
1. Add CSS custom property definitions to filters.css
2. Verify user meta save/load logic is functioning correctly
3. Add validation for deleted seasons (edge case handling)
