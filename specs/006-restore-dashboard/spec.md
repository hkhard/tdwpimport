# Feature Specification: Restore Dashboard Overview and Action Buttons

**Feature Branch**: `006-restore-dashboard`
**Created**: 2025-01-02
**Status**: Draft
**Input**: User description: "top level poker import had a dashboard overview and action buttons, we want to restore it back. it is visible in version 3.3 and 3.4 of the plugin"

## User Scenarios & Testing

### User Story 1 - View Statistics Overview (Priority: P1)

As a tournament director, I want to see a dashboard overview when I log into the WordPress admin, so I can quickly understand the current state of my poker database without navigating to multiple pages.

**Why this priority**: This is the primary value proposition - a single-page overview showing tournament count, player count, season count, and formula count at a glance. This was the main dashboard feature in v3.3/v3.4.

**Independent Test**: Can be tested by navigating to "Poker Import" admin menu and verifying 4 stat cards display with correct counts and "View All" links work.

**Acceptance Scenarios**:

1. **Given** I am logged into WordPress admin, **When** I click "Poker Import" in the admin menu, **Then** I see a dashboard with 4 stat cards showing: Tournaments count, Players count, Seasons count, and Formulas count
2. **Given** I am viewing the dashboard, **When** I look at the Tournaments card, **Then** I see the total number of tournaments (published + draft + private) and a "View All" button that links to the tournaments list
3. **Given** I am viewing the dashboard, **When** I look at the Players card, **Then** I see the total number of players and a "View All" button that links to the players list
4. **Given** I am viewing the dashboard, **When** I look at the Seasons card, **Then** I see the total number of seasons and a "View All" button that links to the seasons list
5. **Given** I am viewing the dashboard, **When** I look at the Formulas card, **Then** I see the total number of formulas and a "Manage" button that links to the formula manager

---

### User Story 2 - Access Quick Actions (Priority: P2)

As a tournament director, I want to see quick action buttons on the dashboard, so I can perform common tasks like importing tournaments or managing data without navigating through menus.

**Why this priority**: Quick actions provide direct access to the most frequently used features, improving workflow efficiency. This is secondary to seeing statistics but still important for usability.

**Independent Test**: Can be tested by verifying the Quick Actions section displays 4 buttons with correct links and icons.

**Acceptance Scenarios**:

1. **Given** I am viewing the dashboard, **When** I look at the Quick Actions section, **Then** I see 4 buttons: "Import Tournament", "View Tournaments", "View Players", "Manage Formulas"
2. **Given** I am viewing the dashboard, **When** I click "Import Tournament", **Then** I am taken to the tournament import page
3. **Given** I am viewing the dashboard, **When** I click "View Tournaments", **Then** I am taken to the tournaments list page
4. **Given** I am viewing the dashboard, **When** I click "View Players", **Then** I am taken to the players list page
5. **Given** I am viewing the dashboard, **When** I click "Manage Formulas", **Then** I am taken to the formula manager page

---

### User Story 3 - Monitor Data Mart Health (Priority: P3)

As a tournament director, I want to see the health status of the statistics data mart on the dashboard, so I can know if statistics are up-to-date and if the data mart needs refreshing.

**Why this priority**: Data mart health is important for analytics but is an advanced feature. Less critical than basic navigation and quick actions.

**Independent Test**: Can be tested by verifying the Data Mart Health section shows status, record count, and last refresh time.

**Acceptance Scenarios**:

1. **Given** I am viewing the dashboard, **When** I look at the Data Mart Health section, **Then** I see the current status (Active/Not Created), number of records, and last refresh timestamp
2. **Given** the data mart table exists, **When** I view the dashboard, **Then** I see a green "Active" status indicator
3. **Given** the data mart table does not exist, **When** I view the dashboard, **Then** I see a red "Not Created" status indicator
4. **Given** the data mart has been refreshed, **When** I view the dashboard, **Then** I see the last refresh time in "M j, Y g:i A" format (e.g., "Jan 2, 2025 3:45 PM")
5. **Given** the data mart has never been refreshed, **When** I view the dashboard, **Then** I see "Never" for the last refresh time
6. **Given** I am viewing the dashboard, **When** I click "Refresh Statistics" button, **Then** I am taken to the Settings page where I can trigger a refresh

---

### User Story 4 - View Recent Activity (Priority: P4)

As a tournament director, I want to see a list of recently imported tournaments on the dashboard, so I can quickly verify recent imports and access them for editing.

**Why this priority**: Recent activity is helpful for verification but is lower priority than the core statistics and navigation features.

**Independent Test**: Can be tested by importing a tournament and verifying it appears in the Recent Activity table.

**Acceptance Scenarios**:

1. **Given** I am viewing the dashboard and tournaments exist, **When** I look at the Recent Activity section, **Then** I see a table showing the 5 most recently imported tournaments
2. **Given** I am viewing the Recent Activity table, **When** I look at a tournament row, **Then** I see the tournament title, import date, status (Published/Draft/Private), and action links (Edit/View/Trash)
3. **Given** no tournaments exist, **When** I view the dashboard, **Then** the Recent Activity section is not displayed
4. **Given** I recently imported a tournament, **When** I view the dashboard, **Then** that tournament appears at the top of the Recent Activity table
5. **Given** I am viewing the Recent Activity table, **When** I hover over a tournament title, **Then** it becomes a clickable link to view that tournament

---

### Edge Cases

- What happens when the data mart table exists but is empty? → Show "0" records and status as "Active" (table exists but no data)
- What happens when a tournament post has no title? → Display "Untitled Tournament" or fallback to post ID
- What happens when there are no formulas configured? → Display "0" in the Formulas stat card
- What happens when there are no players? → Display "0" in the Players stat card
- What happens when there are no seasons? → Display "0" in the Seasons stat card
- What happens when the WordPress database connection fails? → Display error message in each stat card instead of counts
- What happens when permissions prevent accessing certain post types? → Display "0" or error message as appropriate
- What happens when the dashboard is accessed by a user without 'manage_options' capability? → WordPress should handle this via capability check on menu registration

## Requirements

### Functional Requirements

- **FR-001**: System MUST display a dashboard page at the "Poker Import" admin menu location
- **FR-002**: Dashboard MUST display 4 stat cards in a grid layout: Tournaments, Players, Seasons, Formulas
- **FR-003**: Each stat card MUST display the count of items (sum of publish + draft + private statuses)
- **FR-004**: Each stat card MUST include a "View All" or "Manage" button linking to the appropriate list page
- **FR-005**: Dashboard MUST display a "Quick Actions" section with 4 action buttons: Import Tournament, View Tournaments, View Players, Manage Formulas
- **FR-006**: Dashboard MUST display a "Data Mart Health" section showing: status (Active/Not Created), record count, last refresh time
- **FR-007**: Dashboard MUST display a "Recent Activity" table showing the 5 most recently imported tournaments
- **FR-008**: Recent Activity table MUST display columns: Tournament Name, Date Imported, Status, Actions (Edit/View/Trash links)
- **FR-009**: Dashboard MUST use WordPress dashicons for visual indicators in stat cards and section headers
- **FR-010**: Dashboard MUST use inline CSS styles matching the WordPress admin aesthetic (cards, borders, shadows)
- **FR-011**: Stat card counts MUST be calculated from actual WordPress post counts using `wp_count_posts()`
- **FR-012**: Formula count MUST be calculated using `Poker_Tournament_Formula_Validator::get_all_formulas()`
- **FR-013**: Data Mart health MUST query the wp_poker_statistics table to check existence and record count
- **FR-014**: Last refresh time MUST be retrieved from the 'tdwp_statistics_last_refresh' WordPress option
- **FR-015**: Recent tournaments MUST be queried using `get_posts()` with post_type 'tournament', ordered by date DESC, posts_per_page 5

### Key Entities

- **Dashboard Page**: WordPress admin page rendered by `render_dashboard()` method in `Poker_Tournament_Admin` class
- **Stat Card**: Visual component displaying a count, icon, label, and action button for a specific entity type
- **Quick Actions**: Collection of 4 primary action buttons for common workflows
- **Data Mart Health**: Status display showing WordPress statistics table state and metadata
- **Recent Activity Table**: List of 5 most recent tournament post objects with metadata

## Success Criteria

### Measurable Outcomes

- **SC-001**: Dashboard loads in under 2 seconds on typical WordPress installations
- **SC-002**: All stat counts display accurate numbers matching actual database counts (verified by manual count)
- **SC-003**: All "View All" and action buttons navigate to correct admin pages without errors
- **SC-004**: Dashboard displays correctly on screens with minimum width 1024px (standard admin breakpoint)
- **SC-005**: Data Mart Health section accurately reflects table existence and record count
- **SC-006**: Recent Activity shows exactly 5 most recent tournaments when 5+ exist
- **SC-007**: Dashboard renders without PHP errors or warnings in debug.log
- **SC-008**: All links are secure (use `esc_url()`) and all output is escaped (use `esc_html()`)
- **SC-009**: Dashboard functionality matches v3.3/v3.4 behavior as verified by git history comparison
- **SC-010**: No new dependencies are introduced - uses existing WordPress core functions and existing classes

## Out of Scope

- Tabbed interface for switching between Overview, Tournaments, Players, Series, Seasons, Analytics (this was removed in later versions, not being restored)
- AJAX loading of tabbed data content (not needed for restoration)
- Detailed view modal for tournaments (not needed for restoration)
- Report generation functionality (not needed for restoration)
- Interactive charts or graphs (not needed for restoration)
- Leaderboard data display (not needed for restoration)
- Dashboard filtering or search functionality (not needed for restoration)

## Technical Notes

- The `render_dashboard()` method existed in version 3.4.0-beta4 (commit 4ff9552) and was subsequently removed
- The dashboard HTML used inline CSS styles (not external stylesheet) for immediate visual rendering
- The dashboard used a 2-column layout below the stat cards: Data Mart Health (left, 2fr width) and Quick Actions (right, 1fr width)
- Stat cards used a 4-column grid layout: `grid-template-columns: repeat(4, 1fr)`
- All icons were WordPress dashicons: `dashicons-list-view`, `dashicons-groups`, `dashicons-calendar-alt`, `dashicons-calculator`, `dashicons-database`, `dashicons-admin-tools`, `dashicons-clock`
- The dashboard integrated with existing classes: `Poker_Tournament_Formula_Validator` for formula count
- Data mart table name: `wp_poker_statistics` (with WordPress prefix)
