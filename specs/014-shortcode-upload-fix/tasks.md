# Tasks: Frontend Tournament Import Shortcode Fix

**Input**: Design documents from `/specs/014-shortcode-upload-fix/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Manual browser testing only - no automated tests in this simple bug fix

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/`
- All paths relative to repository root: `/Users/hkh/dev/tdwpimport/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Initial preparation for the bug fix

- [x] T001 Verify current branch is 014-shortcode-upload-fix
- [x] T002 Verify WordPress local environment is running for testing
- [x] T003 [P] Backup current version of class-shortcodes.php to class-shortcodes.php.backup

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Understand the current state before making changes

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Read tournament_import_shortcode() method in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (lines 5346-5491)
- [x] T005 Locate the existing jQuery enqueue pattern in poker_dashboard_shortcode() at line 2629
- [x] T006 [P] Verify existing AJAX handler ajax_frontend_import_tournament is registered in poker-tournament-import.php
- [x] T007 [P] Verify WordPress jQuery dependency is available (check wp-includes/script-loader.php)

**Checkpoint**: Foundation ready - code change can now be implemented

---

## Phase 3: User Story 1 & 2 - Frontend Tournament File Upload + jQuery Dependency (Priority: P1) 🎯 MVP

**Goal**: Fix broken file upload by adding jQuery dependency to enable AJAX functionality

**Independent Test**:
1. Add `[tdwp_tournament_import]` shortcode to a WordPress page
2. View page in browser (logged in with `edit_posts` permission)
3. Verify no `$ is not defined` error in browser console
4. Upload a .tdt file and verify tournament is created successfully

**Note**: User Stories 1 and 2 are combined because they represent the same fix - US1 is the functional requirement, US2 is the dependency fix that enables it.

### Implementation for User Stories 1 & 2 (Combined)

- [x] T008 [US1][US2] Add wp_enqueue_script('jquery') after permission checks in tournament_import_shortcode() at line ~5360 in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php

**Checkpoint**: At this point, the jQuery dependency fix should be complete and functional

---

## Phase 4: User Story 3 - User Feedback During Upload (Priority: P2)

**Goal**: Verify existing user feedback mechanisms work correctly after jQuery fix

**Independent Test**: Upload files and observe status messages, error handling, and form reset behavior

**Note**: User Story 3 functionality already exists in the code (lines 5440-5485). We only need to verify it works after the jQuery fix.

### Verification for User Story 3

- [ ] T009 [US3] Test "Uploading..." status message displays correctly in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (line 5440-5442)
- [ ] T010 [US3] Test success message displays and form resets after upload in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (lines 5464-5467)
- [ ] T011 [US3] Test error message displays for non-.tdt files in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (lines 5432-5437)
- [ ] T012 [US3] Test AJAX error handler displays error details in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (lines 5473-5484)

**Checkpoint**: At this point, all user story functionality should work correctly

---

## Phase 5: Testing & Validation

**Purpose**: Comprehensive manual testing of the fix

### Browser Console Testing

- [ ] T013 [P] Test in Chrome/Edge: Verify no `$ is not defined` error on page load
- [ ] T014 [P] Test in Firefox: Verify no `$ is not defined` error on page load
- [ ] T015 [P] Test in Safari (if available): Verify no `$ is not defined` error on page load

### Permission Testing

- [ ] T016 Test not logged in: Verify "Please log in to import tournaments" message displays
- [ ] T017 Test logged in without `edit_posts`: Verify "permission denied" message displays
- [ ] T018 Test logged in with `edit_posts`: Verify upload form displays correctly

### File Upload Testing

- [ ] T019 [P] Test upload with valid .tdt file: Verify tournament imports successfully
- [ ] T020 [P] Test upload with non-.tdt file: Verify validation alert shows
- [ ] T021 [P] Test form reset after successful upload: Verify input clears
- [ ] T022 [P] Test AJAX request in Network tab: Verify POST to admin-ajax.php

### Cross-Theme Testing

- [ ] T023 [P] Test with WordPress default theme (Twenty Twenty-Four): Verify no conflicts
- [ ] T024 [P] Test with another active theme: Verify functionality works consistently

---

## Phase 6: Polish & Deployment

**Purpose**: Finalize and prepare for release

- [ ] T025 [P] Verify PHP syntax is valid using `php -l` on wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
- [ ] T026 [P] Review code change follows WordPress coding standards
- [ ] T027 [P] Update plugin version in poker-tournament-import.php header if needed
- [ ] T028 [P] Update CHANGELOG.md with bug fix details
- [ ] T029 [P] Create distribution ZIP file: poker-tournament-import-vX.X.X.zip
- [ ] T030 [P] Commit changes to git with descriptive commit message
- [ ] T031 Run quickstart.md validation checklist

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: No dependencies on Setup - can start immediately
- **User Stories (Phase 3-4)**:
  - Phase 3 (US1+US2 combined) must complete before Phase 4 (US3 verification)
  - Reason: Need jQuery fix (US2) before upload works (US1), then can verify feedback (US3)
- **Testing (Phase 5)**: Depends on Phase 3 & 4 completion
- **Polish (Phase 6)**: Depends on all testing passing

### User Story Dependencies

- **User Story 1 (P1) + User Story 2 (P1)**: Combined - same fix enables both
  - US1: File upload functionality
  - US2: jQuery dependency loading
  - Both satisfied by single code change (T008)
- **User Story 3 (P2)**: Depends on US1+US2 completion
  - US3: User feedback verification
  - Can only test after jQuery fix enables upload functionality
  - Existing code should work once jQuery is loaded

### Parallel Opportunities

- **Setup Phase**: T002 and T003 can run in parallel
- **Foundational Phase**: T006 and T007 can run in parallel
- **Testing Phase**: All browser tests (T013-T015) can run in parallel
- **Testing Phase**: All upload tests (T019-T022) can run in parallel
- **Testing Phase**: All theme tests (T023-T024) can run in parallel
- **Polish Phase**: T025-T028 can run in parallel

---

## Parallel Example: Cross-Browser Testing

```bash
# Launch all browser console tests together:
Test: "Test in Chrome/Edge: Verify no `$ is not defined` error on page load"
Test: "Test in Firefox: Verify no `$ is not defined` error on page load"
Test: "Test in Safari: Verify no `$ is not defined` error on page load"
```

---

## Implementation Strategy

### MVP First (User Stories 1 & 2 Combined)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (understand current code)
3. Complete Phase 3: Add jQuery enqueue (single line of code)
4. **STOP and VALIDATE**: Test file upload independently
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Code understanding complete
2. Add User Stories 1 & 2 (jQuery fix) → Test independently → Deploy/Demo (MVP!)
3. Verify User Story 3 (existing feedback mechanisms work) → Validate
4. Complete Testing → Comprehensive validation
5. Polish & Deploy → Release

### Minimal Scope

This is the simplest possible fix:
- **Lines Changed**: 1 (add `wp_enqueue_script('jquery');`)
- **Files Modified**: 1 (`class-shortcodes.php`)
- **Functions Modified**: 1 (`tournament_import_shortcode`)
- **Risk Level**: Minimal (follows existing pattern)
- **Testing**: Manual browser testing
- **Time Estimate**: 30-60 minutes (implementation + testing)

---

## Notes

- This is a bug fix, not new feature development
- User Stories 1 and 2 are satisfied by a single code change
- User Story 3 is verification only (functionality already exists)
- No test automation required - manual testing sufficient
- No data model changes, no API changes, no migrations
- Simple, low-risk fix following existing codebase patterns
- Each task should be quick (5-15 minutes) except testing phase
- Commit after each task or logical group
- Stop at any checkpoint to validate progress
