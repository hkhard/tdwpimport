# Feature Specification: Blind Level Management

**Feature Branch**: `001-blind-level-management`
**Created**: 2025-12-27
**Status**: Draft
**Input**: User description: "we need tournament level management. we want it inside the tournament detail screen, but we also want a repository of blinds to choose from in the tournament setup. ultrathink"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Select Blind Schedule During Setup (Priority: P1)

As a tournament director, I want to select from a library of pre-configured blind schedules when creating a tournament, so that I don't have to manually enter blind levels for every tournament.

**Why this priority**: This is the foundation - without blind schedules, tournaments have no blind structure to use. It's the first thing users need when setting up a tournament.

**Independent Test**: A user can create a new tournament and select a blind schedule from the available options. The tournament will then be associated with that blind schedule and can be started.

**Acceptance Scenarios**:

1. **Given** I am on the Create Tournament screen, **When** I view the form, **Then** I see a "Blind Schedule" dropdown/selector
2. **Given** I tap the "Blind Schedule" selector, **When** the list loads, **Then** I see available blind schedules with their names and key details (e.g., starting stack, number of levels)
3. **Given** I select a blind schedule from the list, **When** I confirm the selection, **Then** the selector displays the chosen schedule name
4. **Given** I have selected a blind schedule, **When** I submit the form, **Then** the tournament is created with that blind schedule associated
5. **Given** no blind schedules exist in the library, **When** I create a tournament, **Then** I can still proceed (with a default/manual option) and I'm prompted to create blind schedules first

---

### User Story 2 - View Current Blind Level During Tournament (Priority: P1)

As a tournament director, I want to see the current blind level (small blind, big blind, ante) displayed prominently on the tournament detail screen, so that I know what the current blinds are without having to look elsewhere.

**Why this priority**: This is essential information for running a tournament - players constantly ask "what are the blinds?" and this needs to be visible at a glance.

**Independent Test**: A tournament director opens any active tournament and can immediately see the current blind amounts displayed on the timer section.

**Acceptance Scenarios**:

1. **Given** I am viewing a tournament in the Tournament Detail screen, **When** the screen loads, **Then** I see the current blind level displayed (e.g., "100/200, Ante: 25")
2. **Given** the timer advances to the next level, **When** the level changes, **Then** the blind display updates automatically to show the new blinds
3. **Given** I am viewing a paused tournament, **When** I look at the timer section, **Then** I can still see the current blind level
4. **Given** I am viewing a tournament with no blind schedule, **When** I look at the timer section, **Then** I see "No blinds set" or similar indicator
5. **Given** the current level is a break, **When** I view the timer, **Then** the display shows "Break" instead of blind amounts

---

### User Story 3 - View Upcoming Blind Levels (Priority: P2)

As a tournament director, I want to see upcoming blind levels in the tournament detail screen, so that I can prepare players for what's coming next.

**Why this priority**: This improves the tournament flow - directors can announce "next level is 200/400" and players can plan accordingly. It's nice-to-have but the tournament can run without it.

**Independent Test**: A tournament director can view the list of upcoming levels and see the blind structure for the next several levels.

**Acceptance Scenarios**:

1. **Given** I am viewing an active tournament, **When** I scroll to the blind levels section, **Then** I see a list of upcoming levels with their blind amounts and durations
2. **Given** I am viewing the blind levels list, **When** I look at the current level, **Then** it's highlighted or marked as "Current"
3. **Given** the timer advances to a new level, **When** I view the blind levels list, **Then** the highlight/marker moves to the new current level
4. **Given** there are more than 10 levels remaining, **When** I view the list, **Then** I see the next 10 levels with an option to load more

---

### User Story 4 - Create and Edit Blind Schedules (Priority: P2)

As a tournament director, I want to create custom blind schedules and save them to my library, so that I can reuse them for future tournaments and don't have to re-enter the same blinds repeatedly.

**Why this priority**: This enables efficiency and customization. Directors can build a library of their favorite structures (turbo, deep stack, rebuy, etc.) and reuse them. It's important for productivity but tournaments can initially run with hardcoded schedules.

**Independent Test**: A user can navigate to a "Blind Schedules" management area, create a new schedule with levels, and then select that schedule when creating a tournament.

**Acceptance Scenarios**:

1. **Given** I navigate to the Blind Schedules management screen, **When** the screen loads, **Then** I see a list of my saved blind schedules
2. **Given** I tap "Create New Schedule", **When** the creation form opens, **Then** I can enter a schedule name, starting stack, and add blind levels
3. **Given** I am adding blind levels, **When** I enter level details (small blind, big blind, ante, duration), **Then** the level is added to the schedule
4. **Given** I have added multiple levels, **When** I view the schedule, **Then** I see all levels in order with their blind amounts
5. **Given** I tap "Edit" on an existing schedule, **When** the edit form opens, **Then** I can modify the schedule name, starting stack, and add/remove/edit levels
6. **Given** I modify a blind schedule, **When** tournaments are using that schedule, **Then** they are not affected (changes apply to new tournaments only)
7. **Given** I try to delete a blind schedule, **When** it's in use by an active tournament, **Then** I see a warning and cannot delete it

---

### User Story 5 - Manual Level Control During Tournament (Priority: P1)

As a tournament director, I want to manually advance or rewind blind levels during a tournament, so that I can adjust for late starts, extended breaks, or other timing issues.

**Why this priority**: This is critical for tournament management - real-world disruptions happen and directors need control. The automatic timer is the primary mechanism, but manual overrides are essential.

**Independent Test**: A tournament director can use level up/down buttons to change the current blind level, and the timer and blind display update accordingly.

**Acceptance Scenarios**:

1. **Given** I am viewing an active tournament, **When** I tap the "+ Level" button, **Then** the timer advances to the next blind level and the display updates
2. **Given** I am viewing an active tournament, **When** I tap the "- Level" button, **Then** the timer rewinds to the previous blind level
3. **Given** the timer is on level 1, **When** I view the "- Level" button, **Then** it's disabled (can't go below level 1)
4. **Given** I manually change the level, **When** the level changes, **Then** the blind display updates to show the new blinds
5. **Given** I manually advance past the last defined level, **When** the level changes, **Then** the timer continues running but shows "Final Level" or similar
6. **Given** I manually change the level during a break, **When** the level changes, **Then** the break status updates accordingly

---

### User Story 6 - Pre-Loaded Default Blind Schedules (Priority: P2)

As a new user, I want to start with a selection of common blind schedules already available, so that I don't have to create schedules from scratch before running my first tournament.

**Why this priority**: This improves onboarding and reduces friction. New users can immediately create a tournament with reasonable blinds. It's a nice-to-have that can be added after the core functionality works.

**Independent Test**: A new user installs the app and when creating their first tournament, they see 3-5 pre-loaded blind schedules to choose from (e.g., "Turbo", "Standard", "Deep Stack").

**Acceptance Scenarios**:

1. **Given** I am a new user, **When** I create my first tournament and tap "Blind Schedule", **Then** I see at least 3 default schedules available
2. **Given** I select a default schedule, **When** I view the tournament, **Then** it uses the blind levels from that schedule
3. **Given** I edit a default schedule, **When** I save my changes, **Then** it creates a copy rather than modifying the default (defaults remain unchanged)
4. **Given** I delete all my custom schedules, **When** I view the blind schedule list, **Then** the default schedules are still available

---

### Edge Cases

- What happens when a tournament's blind schedule is deleted while the tournament is active? → Tournament continues with its current level, but blind schedule reference is cleared
- What happens when a level is manually changed beyond the last defined level? → Timer continues, blind display shows "Level N" with no blind amounts (or last known blinds)
- What happens when importing a tournament from Tournament Director without blind schedule data? → Tournament is created with no blind schedule, director can manually set levels
- What happens when the duration of the current level is changed in the blind schedule? → Change applies only to new tournaments, existing tournaments continue with original duration
- What happens when two devices try to control the same tournament's level simultaneously? → Last operation wins (handled by existing timer conflict resolution)
- What happens when a break level is encountered during automatic progression? → Timer shows break status, "Break" indicator is displayed, pause/resume behavior may vary
- What happens when the blind schedule has gaps in level numbers (e.g., levels 1, 2, 5, 6)? → Timer follows the sequence as defined, gap levels are skipped
- What happens when viewing blind levels on a small screen device? → List is scrollable, key information (blinds, duration) remains visible

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow users to select a blind schedule when creating a tournament
- **FR-002**: System MUST display the current blind level (small blind, big blind, ante) on the tournament detail screen
- **FR-003**: System MUST update the blind display automatically when the timer advances levels
- **FR-004**: System MUST provide level up/down controls for manual level changes
- **FR-005**: System MUST enable users to create custom blind schedules with a name and starting stack
- **FR-006**: System MUST allow users to add blind levels with small blind, big blind, ante (optional), and duration
- **FR-007**: System MUST allow users to edit existing blind schedules
- **FR-008**: System MUST prevent deletion of blind schedules that are in use by active tournaments
- **FR-009**: System MUST display a list of upcoming blind levels in the tournament detail screen
- **FR-010**: System MUST highlight or mark the current level in the blind levels list
- **FR-011**: System MUST provide at least 3 default blind schedules for new users
- **FR-012**: System MUST handle break levels specially (display "Break" instead of blind amounts)
- **FR-013**: System MUST persist blind schedules in the database for reuse across tournaments
- **FR-014**: System MUST support blind schedules with up to 50 levels
- **FR-015**: Mobile app MUST fetch blind schedules from the server API
- **FR-016**: Mobile app MUST cache blind schedules locally for offline viewing
- **FR-017**: System MUST associate a blind schedule with a tournament upon tournament creation

### Key Entities

- **BlindSchedule**: A reusable blind structure template containing a name, starting stack, and list of blind levels. Can be selected when creating a tournament.
- **BlindLevel**: Individual level within a blind schedule, containing level number, small blind amount, big blind amount, optional ante, duration in minutes, and break flag.
- **Tournament**: Links to a BlindSchedule via blindScheduleId, tracks currentBlindLevel during play.
- **BlindScheduleLibrary**: Collection of all available BlindSchedules, includes default (pre-loaded) schedules and user-created schedules.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can create a tournament with a blind schedule selected in under 30 seconds
- **SC-002**: Tournament directors can see current blind level at a glance without additional taps
- **SC-003**: Blind display updates within 1 second of level change (automatic or manual)
- **SC-004**: 90% of users can successfully create a custom blind schedule on first attempt without documentation
- **SC-005**: Mobile app displays blind information within 2 seconds on average network connection
- **SC-006**: Users can manually advance/rewind levels with a single tap each
- **SC-007**: Blind schedule management (create/edit) saves data within 3 seconds on average network

## Assumptions

1. Blind schedules are independent of tournaments - editing a schedule doesn't affect existing tournaments
2. Default blind schedules will be common structures: Turbo (10 min levels), Standard (20 min levels), Deep Stack (30 min levels)
3. Break levels are defined by setting isBreak=true and having blind amounts of 0/0
4. Duration is stored in minutes but may be displayed as minutes in UI
5. Ante is optional - not all levels or schedules have antes
6. Mobile app is primarily for tournament management, not blind schedule creation (that can be web/controller initially)
7. Blind schedule library is global - shared across all tournaments, not per-tournament
8. When manually changing levels, the timer's elapsed time and remaining time are recalculated by the server

## Dependencies

1. Existing blind_schedules and blind_levels database tables
2. Existing BlindLevel and BlindSchedule type definitions in shared/src/types/timer.ts
3. Existing tournament timer service for level progression
4. Existing tournament detail screen layout
5. Server API endpoints for blind schedule CRUD (to be implemented)
