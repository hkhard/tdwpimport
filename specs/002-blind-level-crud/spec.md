# Feature Specification: Blind Level Scheme Management Screen

**Feature Branch**: `002-blind-level-crud`
**Created**: 2025-12-28
**Status**: Draft
**Input**: User description: "for blind level management, we need a screen to manage and edit (CRUD) blind level schemes. Thinking we make settings screen into manegement with sub screens for specifics like Settings"

## User Scenarios & Testing

### User Story 1 - View and Navigate Blind Level Schemes (Priority: P1)

A tournament director opens the app settings and accesses the Blind Level Management section. They see a list of all available blind level schemes with their key details (name, number of levels, total duration). The director can tap any scheme to view its full blind structure.

**Why this priority**: This is the foundation for all blind level management. Users must be able to see what schemes exist before they can edit or create them.

**Independent Test**: Can be tested by opening settings, navigating to Blind Level Management, and viewing the list of schemes. No edit functionality required.

**Acceptance Scenarios**:

1. **Given** the app has 3 or more blind level schemes configured, **When** the user opens Settings > Blind Level Management, **Then** they see a list of all schemes with name, level count, and total duration
2. **Given** a blind level scheme list is displayed, **When** the user taps on a scheme, **Then** the scheme details screen opens showing all blind levels
3. **Given** no blind level schemes exist, **When** the user opens Blind Level Management, **Then** they see an empty state with a prompt to create their first scheme

---

### User Story 2 - Create New Blind Level Scheme (Priority: P1)

A tournament director wants to create a custom blind structure for their weekly game. They access the Blind Level Management screen, tap "Create New Scheme", and enter the scheme name, starting stack, and break settings. They then add blind levels one by one, specifying small blind, big blind, ante, and duration for each level.

**Why this priority**: Creating custom schemes is essential for tournament directors who have specific blind structures they prefer over the defaults.

**Independent Test**: Can be tested by creating a new scheme with 3-5 levels, saving it, and verifying it appears in the scheme list.

**Acceptance Scenarios**:

1. **Given** the user is on the Blind Level Management screen, **When** they tap "Create New Scheme", **Then** they see a form to enter scheme name and settings
2. **Given** the user has entered a valid scheme name, **When** they tap "Add Level", **Then** a new blind level row appears with editable fields
3. **Given** the user has added at least one blind level, **When** they tap "Save Scheme", **Then** the scheme is saved and they return to the scheme list
4. **Given** the user tries to save without a scheme name, **When** they tap "Save Scheme", **Then** they see a validation error prompting for a name
5. **Given** the user tries to save without any blind levels, **When** they tap "Save Scheme", **Then** they see a validation error that at least one level is required

---

### User Story 3 - Edit Existing Blind Level Scheme (Priority: P1)

A tournament director wants to modify an existing blind scheme (e.g., change the ante amounts or adjust level durations). They open the scheme from the list, make their changes, and save. The system validates that the changes don't break any active tournaments using this scheme.

**Why this priority**: Editing capability allows directors to refine their blind structures based on experience without recreating from scratch.

**Independent Test**: Can be tested by editing an existing scheme, modifying a few levels, saving, and verifying the changes persist.

**Acceptance Scenarios**:

1. **Given** the user is viewing a blind level scheme, **When** they tap "Edit Scheme", **Then** they enter edit mode with all fields modifiable
2. **Given** the user is editing a scheme, **When** they modify a blind level amount or duration, **Then** the changes are reflected in the preview
3. **Given** the user edits a default scheme (Turbo, Standard, Deep Stack), **When** they tap "Save", **Then** the system creates a copy instead of modifying the default
4. **Given** the user has made unsaved changes, **When** they tap "Cancel", **Then** they're prompted to confirm discarding changes

---

### User Story 4 - Delete Blind Level Scheme (Priority: P2)

A tournament director wants to remove an unused blind scheme they created earlier. They navigate to the scheme, tap delete, and confirm the action. The system prevents deletion if the scheme is currently in use by any active or scheduled tournament.

**Why this priority**: Deletion is important for keeping the scheme list clean, but less critical than create/edit. Lower priority because it only affects organization, not functionality.

**Independent Test**: Can be tested by creating a temporary scheme, then deleting it and verifying it's removed from the list.

**Acceptance Scenarios**:

1. **Given** the user is viewing a custom scheme not used by any tournament, **When** they tap "Delete Scheme" and confirm, **Then** the scheme is permanently deleted
2. **Given** the user is viewing a default scheme (Turbo, Standard, Deep Stack), **When** they look for delete option, **Then** no delete button is shown (defaults cannot be deleted)
3. **Given** the user tries to delete a scheme used by an active tournament, **When** they tap delete, **Then** they see an error message explaining the scheme is in use
4. **Given** the user is viewing a scheme used only by past completed tournaments, **When** they tap delete and confirm, **Then** the scheme is deleted with a warning that it was used historically

---

### User Story 5 - Reorder Blind Levels Within Scheme (Priority: P3)

A tournament director wants to change the order of blind levels in their scheme (e.g., insert a new level at position 5, shifting later levels down). They access the edit screen and use drag handles or move up/down buttons to rearrange levels. The system automatically renumbers all levels to maintain sequential order.

**Why this priority**: Reordering is a nice-to-have feature for scheme refinement, but schemes can be created in order initially. Lower priority than basic CRUD.

**Independent Test**: Can be tested by creating a scheme with 5 levels, moving level 3 to position 1, and verifying all levels are renumbered correctly.

**Acceptance Scenarios**:

1. **Given** the user is editing a scheme with 5+ levels, **When** they use move controls to reorder a level, **Then** all levels are automatically renumbered sequentially
2. **Given** the user moves a level to position 1, **When** the scheme is saved, **Then** the level order persists correctly
3. **Given** the user is editing, **When** they tap "Add Level" at a specific position, **Then** the new level is inserted and subsequent levels shift down

---

### Edge Cases

- What happens when a user tries to edit a scheme that's currently being used in an active tournament?
- How does the system handle duplicate scheme names?
- What happens if the user creates a scheme with 100+ levels (performance implications)?
- How does the system handle concurrent edits if multiple devices are connected to the same tournament?
- What happens when a blind level has invalid data (e.g., big blind smaller than small blind)?
- How does the system behave when network is unavailable during scheme creation/editing?
- What happens if a user sets a break level with non-zero blind amounts?
- How does the system handle importing/exporting blind schemes for backup or sharing?

## Requirements

### Functional Requirements

#### Scheme Management

- **FR-001**: System MUST provide a dedicated screen accessible from Settings for managing blind level schemes
- **FR-002**: System MUST display a list of all blind level schemes showing name, level count, and total duration
- **FR-003**: System MUST allow users to create new blind level schemes with custom name, description, starting stack, break interval, and break duration
- **FR-004**: System MUST allow users to edit existing blind level schemes
- **FR-005**: System MUST allow users to delete custom blind level schemes
- **FR-006**: System MUST prevent deletion of default blind level schemes (Turbo, Standard, Deep Stack)
- **FR-007**: System MUST prevent deletion of schemes currently in use by active tournaments
- **FR-008**: System MUST validate that scheme names are unique (case-insensitive)
- **FR-009**: System MUST validate that blind levels have sequential numbering starting from 1

#### Blind Level Editing

- **FR-010**: System MUST allow users to add blind levels to a scheme with small blind, big blind, ante (optional), and duration
- **FR-011**: System MUST allow users to edit individual blind levels within a scheme
- **FR-012**: System MUST allow users to remove blind levels from a scheme
- **FR-013**: System MUST require at least one blind level in a scheme
- **FR-014**: System MUST validate that big blind is greater than or equal to small blind
- **FR-015**: System MUST validate that break levels have zero blind amounts (small blind = 0, big blind = 0)
- **FR-016**: System MUST support reordering blind levels within a scheme with automatic renumbering

#### User Interface

- **FR-017**: System MUST provide inline editing of blind levels within the scheme editor
- **FR-018**: System MUST display a preview of the blind structure as users edit
- **FR-019**: System MUST show clear visual distinction between play levels and break levels
- **FR-020**: System MUST provide empty state messaging when no schemes exist
- **FR-021**: System MUST provide loading indicators during data operations
- **FR-022**: System MUST provide confirmation dialogs for destructive actions (delete, discard changes)

#### Data Persistence

- **FR-023**: System MUST save scheme changes immediately upon user confirmation
- **FR-024**: System MUST sync scheme data across all connected devices for the same user account
- **FR-025**: System MUST cache schemes locally for offline access
- **FR-026**: System MUST handle merge conflicts when the same scheme is edited on multiple devices

#### Navigation & Settings Integration

- **FR-027**: System MUST integrate blind level management into the main Settings screen
- **FR-028**: System MUST provide sub-screens or navigation for specific settings categories
- **FR-029**: System MUST maintain a clear hierarchy: Settings > Blind Level Management > [Specific Scheme]

### Key Entities

- **Blind Scheme**: Represents a complete blind structure with name, description, starting stack, break interval, break duration, and collection of blind levels
- **Blind Level**: Individual level within a scheme with level number, small blind, big blind, ante, duration, and break flag
- **Scheme Metadata**: Level count, total play duration (excluding breaks), default flag, creation date, last modified date
- **Tournament Reference**: Active/past tournaments using a specific scheme (for deletion validation)

## Success Criteria

### Measurable Outcomes

- **SC-001**: Users can create a new 10-level blind scheme in under 3 minutes
- **SC-002**: Users can edit an existing scheme (modify 3-5 levels) in under 2 minutes
- **SC-003**: Scheme list loads and displays within 1 second on standard mobile connection
- **SC-004**: 95% of users successfully complete scheme creation on first attempt without errors
- **SC-005**: System validates and prevents invalid blind configurations with clear error messages
- **SC-006**: Users can manage (view, create, edit) blind schemes while offline, with sync when connection restored
- **SC-007**: Default schemes (Turbo, Standard, Deep Stack) remain unmodified; edits create copies automatically
- **SC-008**: Scheme list supports 100+ schemes without performance degradation

### User Experience

- **SC-009**: Users can navigate to blind level management from settings in 3 or fewer taps
- **SC-010**: Visual hierarchy makes it immediately clear which scheme is currently selected for a tournament
- **SC-011**: Edit mode provides clear indication of unsaved changes
- **SC-012**: Error messages provide actionable guidance for fixing validation issues

### Data Integrity

- **SC-013**: Blind level numbers are always sequential without gaps
- **SC-014**: Scheme deletion cascade is prevented when tournaments reference the scheme
- **SC-015**: Concurrent edits are resolved with last-write-wins and user notification

## Dependencies

### External Dependencies

- Mobile app navigation system (for settings screen integration)
- Blind schedule storage service (already implemented in 001-blind-level-management)
- Tournament management service (for checking scheme usage)

### Technical Prerequisites

- Settings screen infrastructure must exist
- Blind schedule CRUD API must be available (from 001-blind-level-management)
- Mobile app state management (Zustand store) for blind schedules
- Offline data synchronization service

### Blocked By

- None - this feature enhances existing 001-blind-level-management functionality

### Blocks

- None - this is an independent enhancement

## Assumptions

1. User has the mobile app installed with tournament director permissions
2. Blind schedule CRUD API from 001-blind-level-management is available for reuse
3. Settings screen follows standard mobile app patterns (tab-based or list-based navigation)
4. Users are familiar with blind level terminology from playing poker tournaments
5. Default schemes (Turbo, Standard, Deep Stack) should always remain available and unmodified
6. Offline-first architecture is acceptable for this feature (sync when connection available)
7. A typical user manages 3-10 blind schemes total
8. Most schemes have 10-20 blind levels
