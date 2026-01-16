# Tasks: Season Leaderboard Formula & Stats Enhancement

**Input**: Design documents from `/specs/007-season-leaderboard-fix/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/shortcode-api.md

**Tests**: Manual testing only - no automated test tasks (spec confirms manual testing sufficient)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/`
- **Includes**: `wordpress-plugin/poker-tournament-import/includes/`
- **Assets**: `wordpress-plugin/poker-tournament-import/assets/`

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify development environment and understand current codebase

- [x] T001 Verify branch 007-season-leaderboard-fix is checked out
- [x] T002 Verify PHP 8.0+ development environment is available
- [x] T003 Review existing season_leaderboard_shortcode in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2425-2595
- [x] T004 Review calculate_player_series_data in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:224-338
- [x] T005 [P] Review CSS dashboard bubble/last calculation logic in wordpress-plugin/poker-tournament-import/includes/class-statistics-engine.php:1124-1143

**Checkpoint**: Environment verified and codebase understood ✓

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before user stories

**⚠️ CRITICAL**: This phase is MINIMAL - no foundational blockers for this bug fix

- [x] T006 Identify helper method need for tournament UUID to post ID mapping

**Checkpoint**: Foundation ready - user story implementation can begin ✓

---

## Phase 3: User Story 1 - Correct Season Points Calculation (Priority: P1) 🎯 MVP

**Goal**: Fix the critical bug where leaderboard displays total_points instead of formula-calculated series_points

**Independent Test**: View `[season_leaderboard]` shortcode output and verify the "Points" column shows formula-calculated values (e.g., if formula is "best 8 of 10", points should reflect best 8 tournaments, not sum of all 10)

### Implementation for User Story 1

- [x] T007 [US1] Fix points display in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2540 - change $player['total_points'] to $player['series_points']
- [x] T008 [US1] Update cache key version in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:95 - add '_v2' suffix to $cache_key
- [x] T009 [US1] Add null coalescing operators in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php for backward compatibility with old cache
- [x] T010 [US1] Validate PHP syntax for modified files: `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
- [x] T011 [US1] Validate PHP syntax for modified files: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- [x] T012 [US1] Clear transients to force recalculation: `wp transient delete poker_season_standings_* --allow-root`
- [ ] T013 [US1] Manual test: View season leaderboard and verify points match formula calculation (not total points)
- [ ] T014 [US1] Manual test: Test with formula override parameter `[season_leaderboard formula="season_total"]`
- [ ] T015 [US1] Manual test: Verify old cache doesn't cause PHP errors (null coalescing works)

**Checkpoint**: User Story 1 implementation complete - points display bug is FIXED ✓
**Note**: Manual tests T013-T015 to be completed in Phase 6 (Polish)

---

## Phase 4: User Story 2 - Enhanced Statistics Display (Priority: P2)

**Goal**: Add Bubble, Last, and Hits statistics columns when show_details="true"

**Independent Test**: Add `show_details="true"` to shortcode and verify three new columns appear with accurate counts for each player

### Implementation for User Story 2

- [x] T016 [P] [US2] Add helper method get_tournament_post_id() to wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (maps UUID to post ID)
- [x] T017 [P] [US2] Add bubble_count initialization in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:248 (after $finishes array)
- [x] T018 [P] [US2] Add last_place_count initialization in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:248
- [x] T019 [P] [US2] Add max_positions pre-calculation query in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:248 (before results foreach loop)
- [x] T020 [US2] Add bubble detection logic inside results foreach loop in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:266 (after $total_hits calculation)
- [x] T021 [US2] Add last place detection logic inside results foreach loop in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:266
- [x] T022 [US2] Add bubble_count to $series_data array in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:294
- [x] T023 [US2] Add last_place_count to $series_data array in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php:294
- [x] T024 [US2] Add Bubble table header in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2531 (after Top 5 column)
- [x] T025 [US2] Add Last table header in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2531 (after Bubble column)
- [x] T026 [US2] Add Hits table header in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2531 (after Last column)
- [x] T027 [US2] Add Bubble data cell in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2559 (after Top 5 data, using null coalescing)
- [x] T028 [US2] Add Last data cell in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2559 (after Bubble data, using null coalescing)
- [x] T029 [US2] Add Hits data cell in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2559 (after Last data)
- [x] T030 [US2] Validate PHP syntax: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- [x] T031 [US2] Validate PHP syntax: `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
- [x] T032 [US2] Clear transients to force recalculation with new fields: `wp transient delete poker_season_standings_* --allow-root`
- [ ] T033 [US2] Manual test: Add show_details="true" and verify Bubble/Last/Hits columns appear
- [ ] T034 [US2] Manual test: Verify bubble count is accurate for player with known bubble finishes
- [ ] T035 [US2] Manual test: Verify last place count is accurate for player with known last place finishes
- [ ] T036 [US2] Manual test: Verify hits count matches player's total hits across season
- [ ] T037 [US2] Manual test: Verify show_details="false" hides new columns (default behavior)
- [ ] T038 [US2] Manual test: Verify old cache doesn't cause PHP notices (null coalescing ?? 0)

**Checkpoint**: User Stories 1 AND 2 both work - points fixed + new stats displayed

---

## Phase 5: User Story 3 - Column Header Consistency (Priority: P3)

**Goal**: Ensure column headers are clear and descriptive

**Independent Test**: View rendered table and verify column headers accurately describe the data shown

### Implementation for User Story 3

- [x] T039 [US3] Review all column headers in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php:2522-2531
- [x] T040 [US3] Verify "Points" header is descriptive (consider "Season Points" or formula-specific label)
- [x] T041 [US3] Verify new column headers follow naming convention: Bubble, Last, Hits
- [ ] T042 [US3] Manual test: View leaderboard with show_details="true" and verify all headers are clear
- [ ] T043 [US3] Manual test: Verify header text is translatable with esc_html_e()

**Checkpoint**: All user stories complete - headers are clear and consistent

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T044 [P] Test Export CSV functionality includes new Bubble/Last/Hits columns
- [ ] T045 [P] Test Print functionality displays new columns correctly
- [ ] T046 [P] Test limit parameter works with new columns
- [ ] T047 Test mobile responsive layout handles additional columns (no horizontal scroll on standard mobile)
- [ ] T048 [P] Verify backward compatibility: shortcodes without new parameters still work
- [ ] T049 Run full quickstart.md testing checklist from specs/007-season-leaderboard-fix/quickstart.md
- [ ] T050 [P] Update plugin version number in wordpress-plugin/poker-tournament-import/poker-tournament-import.php header
- [ ] T051 [P] Create distribution zip file: wordpress-plugin/poker-tournament-import-v3.5.0-beta18.zip
- [ ] T052 [P] Code review: verify no PHP 8.2 incompatibilities introduced
- [ ] T053 [P] Code review: verify WordPress Coding Standards compliance
- [ ] T054 [P] Code review: verify all user input is sanitized
- [ ] T055 [P] Code review: verify all output is properly escaped

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Minimal - no blocking infrastructure for this bug fix
- **User Story 1 (Phase 3)**: Can start after Setup - CRITICAL BUG FIX, highest priority
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion (shares same files)
- **User Story 3 (Phase 5)**: Depends on User Story 2 completion (headers added in US2)
- **Polish (Phase 6)**: Depends on User Stories 1, 2, 3 being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start immediately - No dependencies (CRITICAL BUG)
- **User Story 2 (P2)**: Must complete after US1 - modifies same files
- **User Story 3 (P3)**: Must complete after US2 - reviews headers added in US2

### Within Each User Story

**User Story 1**:
- T007 must complete before T008 (fix display before versioning cache)
- T008, T009, T010, T011 can run in parallel
- T012 must complete before T013 (clear cache before testing)

**User Story 2**:
- T016-T019 (setup) must complete before T020-T023 (loop logic)
- T020-T023 (calculation) must complete before T024-T029 (display)
- T030, T031 (validation) can run in parallel
- T032 must complete before T033-T038 (clear cache before testing)

**User Story 3**:
- All tasks can run in parallel (review only)

### Parallel Opportunities

- T005 (CSS dashboard review) can run with T001-T004
- T016-T019 (US2 setup) can all run in parallel
- T024-T026 (US2 headers) can all run in parallel
- T030-T031 (US2 validation) can run in parallel
- T039-T043 (US3 review) can all run in parallel
- T044-T046, T048, T050-T055 (Polish) can all run in parallel

---

## Parallel Example: User Story 2 Setup

```bash
# Launch all setup tasks for User Story 2 together:
Task: "Add helper method get_tournament_post_id() to class-series-standings.php"
Task: "Add bubble_count initialization in class-series-standings.php:248"
Task: "Add last_place_count initialization in class-series-standings.php:248"
Task: "Add max_positions pre-calculation query in class-series-standings.php:248"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only) - CRITICAL BUG FIX

1. Complete Phase 1: Setup (understand codebase)
2. Complete Phase 3: User Story 1 (fix points display bug)
3. **STOP and VALIDATE**: Test User Story 1 independently
4. Deploy bug fix immediately (this is a data accuracy issue)

### Incremental Delivery

1. **MVP (US1)**: Fix points display → Deploy immediately (bug fix!)
2. **Enhancement (US2)**: Add bubble/last/hits stats → Test independently → Deploy
3. **Polish (US3)**: Review headers → Final deployment

### Sequential Strategy (Recommended for Single Developer)

This feature has sequential dependencies due to shared files:

1. Complete User Story 1 (bug fix) - **HIGH PRIORITY**
2. Complete User Story 2 (new stats) - **MEDIUM PRIORITY**
3. Complete User Story 3 (header review) - **LOW PRIORITY**
4. Complete Polish phase

---

## Task Summary

**Total Tasks**: 55
**By User Story**:
- Setup: 5 tasks
- Foundational: 1 task
- User Story 1 (P1 - Critical Bug): 9 tasks
- User Story 2 (P2 - New Stats): 23 tasks
- User Story 3 (P3 - Headers): 5 tasks
- Polish: 12 tasks

**Parallel Opportunities**: 15 tasks marked [P]

**MVP Scope**: User Story 1 only (9 tasks) - fixes critical data accuracy bug

**Independent Test Criteria**:
- US1: Points column shows formula-calculated values, not total points
- US2: Bubble/Last/Hits columns appear with show_details="true" and show accurate counts
- US3: Column headers are clear and descriptive

---

## Notes

- [P] tasks = different files or no dependencies, can run in parallel
- [Story] label maps task to specific user story for traceability
- User Stories 1, 2, 3 are sequential (same files) but independently testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- **Critical**: US1 is a bug fix - prioritize completion and deployment
- Avoid: breaking existing functionality, introducing PHP errors, cache invalidation issues
