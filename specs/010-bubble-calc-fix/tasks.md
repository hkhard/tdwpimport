# Tasks: Bubble Calculation Fix for Season Leaderboard

**Input**: Design documents from `/specs/010-bubble-calc-fix/`
**Prerequisites**: plan.md, spec.md, research.md, quickstart.md

**Tests**: Manual testing only - no automated tests requested for this bug fix.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2)
- Include exact file paths in descriptions

## Path Conventions

WordPress plugin structure:
- Plugin root: `wordpress-plugin/poker-tournament-import/`
- Includes: `wordpress-plugin/poker-tournament-import/includes/`
- Main file: `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`

---

## Phase 1: Setup

**Purpose**: Verify environment and understand codebase

- [x] T001 Verify PHP 8.0+ and WordPress 6.0+ environment
- [x] T002 Read reference implementation in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php lines 310-322
- [x] T003 Read current implementation in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php around line 4718

**Checkpoint**: Environment verified and both implementations understood

---

## Phase 2: Foundational

**Purpose**: No foundational tasks needed - this is a bug fix in existing code

**Note**: This feature modifies existing code without requiring new infrastructure. Skip to Phase 3.

---

## Phase 3: User Story 1 - Fix Bubble Count Display (Priority: P1) 🎯 MVP

**Goal**: Fix bubble_count calculation in season_leaderboard shortcode to use paid_positions meta field

**Independent Test**: View season leaderboard shortcode output and verify bubble counts match series standings data

### Implementation for User Story 1

- [x] T004 [US1] Remove SQL-based bubble_count calculation from wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php around line 4718-4725
- [x] T005 [US1] Add bubble_count initialization (0) to SELECT statement in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
- [x] T006 [US1] Add tournament_post_id column to SELECT query in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php if not present
- [x] T007 [US1] Implement PHP post-processing loop for bubble calculation in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php after query execution
- [x] T008 [US1] Add error_log warning for missing paid_positions in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
- [x] T009 [US1] Verify PHP syntax with `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Checkpoint**: Bubble calculation now uses paid_positions meta field

---

## Phase 4: User Story 2 - Use Consistent Calculation Method (Priority: P1)

**Goal**: Ensure season_leaderboard and series standings use identical bubble calculation logic

**Independent Test**: Compare bubble counts between season_leaderboard shortcode and series standings for same season

### Implementation for User Story 2

- [x] T010 [P] [US2] Review series standings implementation in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php lines 310-322
- [x] T011 [US2] Verify season_leaderboard implementation matches series standings logic in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php
- [ ] T012 [US2] Test both outputs with sample tournament data and verify identical bubble counts

**Checkpoint**: Both implementations use consistent calculation method

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Finalize and release the bug fix

- [x] T013 [P] Update plugin version to 3.5.0-beta52 in wordpress-plugin/poker-tournament-import/poker-tournament-import.php line 5 (plugin header)
- [x] T014 [P] Update version constant to 3.5.0-beta52 in wordpress-plugin/poker-tournament-import/poker-tournament-import.php line 22
- [x] T015 Verify PHP syntax with `php -l wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
- [x] T016 Create distribution package poker-tournament-import-v3.5.0-beta52.zip in repository root
- [x] T017 Deploy to WordPress test site and clear caches
- [ ] T018 Manual test: Tournament with 5 paid positions, player finishes 6th → verify bubble_count = 1
- [ ] T019 Manual test: Tournament with 3 paid positions, player finishes 4th → verify bubble_count = 1
- [ ] T020 Manual test: Tournament with 10 paid positions, player finishes 1st → verify bubble_count = 0
- [ ] T021 Manual test: Compare season_leaderboard vs series standings bubble counts → verify 100% match
- [ ] T022 Manual test: Check error_log for warnings about missing paid_positions
- [ ] T023 Verify no PHP errors or warnings in browser console or debug.log

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: SKIPPED - no new infrastructure needed
- **User Story 1 (Phase 3)**: Depends on Setup - implements the core bug fix
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion - verifies consistency
- **Polish (Phase 5)**: Depends on both user stories - finalizes release

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Setup - Core bug fix implementation
- **User Story 2 (P2)**: Depends on User Story 1 - Verification and consistency check

### Within Each User Story

**User Story 1**:
- T004 must complete before T005 (remove before add)
- T005, T006, T007 must complete in sequence
- T008 must complete after T007 (add logging after logic)
- T009 must complete after T008 (verify syntax)

**User Story 2**:
- T010 and T011 can run in parallel [P]
- T012 must complete after T010 and T011

**Polish Phase**:
- T013 and T014 can run in parallel [P]
- T015 must complete after T013, T014
- T016 must complete after T015
- T017 must complete after T016
- T018-T023 are manual tests that can run in parallel after T017

### Parallel Opportunities

- T013, T014: Version updates can run in parallel (different locations in same file)
- T010, T011: Code review tasks can run in parallel
- T018-T023: Manual tests can run in parallel once deployed

---

## Parallel Example: User Story 1

```bash
# Sequential execution (most tasks in US1 have dependencies):
Task: "Remove SQL-based bubble_count calculation"
Task: "Add bubble_count initialization"
Task: "Add tournament_post_id column"
Task: "Implement PHP post-processing loop"
Task: "Add error_log warning"
Task: "Verify PHP syntax"
```

---

## Parallel Example: Polish Phase

```bash
# Version updates can run in parallel:
Task: "Update plugin version in header (line 5)"
Task: "Update version constant (line 22)"

# Once both complete:
Task: "Verify PHP syntax"
Task: "Create distribution package"

# After deployment, run all manual tests in parallel:
Task: "Test: 5 paid positions"
Task: "Test: 3 paid positions"
Task: "Test: 10 paid positions"
Task: "Test: Compare outputs"
Task: "Test: Check error_log"
Task: "Test: Verify no errors"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (understand both implementations)
2. Complete Phase 3: User Story 1 (core bug fix)
3. **STOP and VALIDATE**: Test bubble calculation with sample data
4. Deploy to test site and verify manually

### Incremental Delivery

1. Complete Setup → Understand codebase
2. Add User Story 1 → Fix bubble calculation → Test manually → **MVP COMPLETE**
3. Add User Story 2 → Verify consistency → **DELIVERABLE READY**
4. Complete Polish → Version bump, package, deploy → **RELEASE**

### Sequential Execution (Single Developer)

1. **Setup Phase** (T001-T003): Read and understand codebase
2. **User Story 1** (T004-T009): Implement core bug fix
3. **User Story 2** (T010-T012): Verify consistency
4. **Polish Phase** (T013-T023): Finalize and test

---

## Notes

- This is a bug fix, not new feature development
- No new data models, APIs, or infrastructure needed
- Manual testing required - no automated tests in spec
- Target file: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
- Reference implementation: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- Key change: Replace SQL subquery with PHP post-processing using `get_post_meta()`
- WordPress object cache makes `get_post_meta()` very efficient
- Error logging for missing data helps identify data quality issues
- Version bump to 3.5.0-beta52 for release
