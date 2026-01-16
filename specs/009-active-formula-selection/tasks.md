# Tasks: Active Formula Selection and Season Points Display

**Input**: Design documents from `/specs/009-active-formula-selection/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ajax-api.md

**Tests**: Tests are NOT included in this feature (manual testing per spec requirements)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/`
- Paths are relative to repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create new files and prepare plugin for active formula feature

- [x] T001 [P] Create class-active-formula-manager.php in wordpress-plugin/poker-tournament-import/includes/
- [x] T002 [P] Create active-formula-handler.js in wordpress-plugin/poker-tournament-import/assets/js/
- [x] T003 Add nonce for active formula AJAX actions in wordpress-plugin/poker-tournament-import/poker-tournament-import.php

**Checkpoint**: New files created, ready for implementation ✅

---

## Phase 2: Foundational (Active Formula Storage)

**Purpose**: Core infrastructure for active formula state management

**⚠️ CRITICAL**: Active formula storage must be in place before any user story implementation

- [x] T004 Implement get_active_formula($category) function in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T005 Implement set_active_formula($formula_key, $category) function in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T006 Implement clear_active_formula($category) function in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T007 Register wp_ajax_set_active_formula action in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T008 Register wp_ajax_get_active_formula action in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T009 Register wp_ajax_clear_formula_cache action in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php

**Checkpoint**: Active formula backend API ready, UI components can now be added ✅

---

## Phase 3: User Story 1 - Active Tournament Formula Selection (Priority: P1) 🎯 MVP

**Goal**: Allow administrators to select which formula calculates tournament points during import

**Independent Test**: Create multiple tournament formulas, check one as active, import tournament, verify points use selected formula

### Implementation for User Story 1

- [x] T010 [P] [US1] Add active tournament formula checkbox HTML in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php render_formulas_tab()
- [x] T011 [US1] Enqueue active-formula-handler.js in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php enqueue_formula_manager_assets()
- [x] T012 [US1] Localize pokerFormulaManager with activeFormulaNonce in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T013 [P] [US1] Implement checkbox mutual exclusion logic in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T014 [US1] Implement setActiveFormula() AJAX call in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T015 [US1] Implement getActiveFormula() AJAX call in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T016 [US1] Add success/error notices for checkbox changes in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T017 [P] [US1] Add active-tournament class styling in wordpress-plugin/poker-tournament-import/assets/css/formula-manager.css
- [x] T018 [US1] Add active formula badge styling in wordpress-plugin/poker-tournament-import/assets/css/formula-manager.css
- [x] T019 [US1] Update tournament import to use get_active_formula('tournament') in wordpress-plugin/poker-tournament-import/includes/class-parser.php
- [x] T020 [US1] Add fallback to default formula when no active tournament formula set in wordpress-plugin/poker-tournament-import/includes/class-parser.php

**Checkpoint**: Tournament active formula selection complete - admins can choose formula for tournament points ✅

---

## Phase 4: User Story 2 - Active Season Formula Selection (Priority: P1)

**Goal**: Allow administrators to select which formula calculates season standings points

**Independent Test**: Create multiple season formulas, check one as active, view season leaderboard, verify Season Points column uses selected formula

### Implementation for User Story 2

- [x] T021 [P] [US2] Add active season formula checkbox HTML in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php render_formulas_tab()
- [x] T022 [P] [US2] Add active season formula checkbox category filter (only show for season category formulas) in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T023 [US2] Extend checkbox mutual exclusion to support season category in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T024 [US2] Add cache clearing call when season formula changes in wordpress-plugin/poker-tournament-import/assets/js/active-formula-handler.js
- [x] T025 [P] [US2] Add active-season class styling in wordpress-plugin/poker-tournament-import/assets/css/formula-manager.css
- [x] T026 [US2] Implement clear_season_standings_cache() function in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T027 [US2] Call clear_season_standings_cache() when active season formula updated in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php

**Checkpoint**: Season active formula selection complete - admins can choose formula for season standings ✅

---

## Phase 5: User Story 3 - Season Points with Full Variable Access (Priority: P1)

**Goal**: Enable season formulas to access tournament-level variables (n, r, hits, monies, avgBC, T33, T80) with graceful undefined handling

**Independent Test**: Configure season formula with variables (avgBC, T33), view season leaderboard, verify season points display even when some variables undefined

### Implementation for User Story 3

- [x] T028 [P] [US3] Extend season formula evaluation to support per-tournament variables in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php calculate_player_series_data()
- [x] T029 [US3] Implement get_tournament_variables($tournament_id, $player_result) helper in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
- [x] T030 [US3] Implement evaluate_season_formula_per_tournament($formula_key, $variables, $tournament_id) in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
- [x] T031 [US3] Add undefined variable handling (treat as 0) in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php evaluate_season_formula_per_tournament()
- [x] T032 [US3] Aggregate per-tournament season points in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php calculate_player_series_data()
- [x] T033 [US3] Add season_points field to series_data array in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
- [x] T034 [US3] Add fallback to direct sum when no active season formula in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php calculate_player_series_data()
- [x] T035 [US3] Add transient caching for season points calculation in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
- [x] T036 [US3] Implement lazy recalculation on season leaderboard view in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php

**Checkpoint**: Season points display complete - formulas use full variable access with graceful degradation ✅

---

## Phase 6: User Story 4 - Bubble Calculation Fix (Priority: P2)

**Goal**: Fix bubble count to accurately track players finishing in last unpaid position

**Independent Test**: Import tournament with 5 paid positions, view player who finished 6th, verify bubble_count = 1

### Implementation for User Story 4

- [x] T037 [P] [US4] Investigate parser paid_positions extraction in wordpress-plugin/poker-tournament-import/includes/class-parser.php (locate prize structure parsing)
- [x] T038 [US4] Add paid_positions meta field population during import in wordpress-plugin/poker-tournament-import/includes/class-parser.php
- [x] T039 [US4] Add validation for paid_positions value (must be positive integer) in wordpress-plugin/poker-tournament-import/includes/class-parser.php
- [x] T040 [US4] Test bubble calculation with sample tournament data in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
- [x] T041 [US4] Add error logging for missing paid_positions in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php

**Checkpoint**: Bubble calculation fixed - bubble_count accurately reflects finishes outside paid positions ✅

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T042 [P] Add inline documentation to class-active-formula-manager.php per WordPress coding standards
- [x] T043 [P] Add inline documentation to active-formula-handler.js per JSDoc standards
- [x] T044 [P] Update formula manager help text to explain active formula selection in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T045 [P] Add accessibility attributes (aria-labels) to checkbox controls in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T046 Verify AJAX nonce security on all active formula endpoints in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [x] T047 Add capability checks (manage_options) to all AJAX handlers in wordpress-plugin/poker-tournament-import/includes/class-active-formula-manager.php
- [ ] T048 Test all acceptance scenarios from spec.md User Stories 1-4 (manual - requires WordPress admin)
- [ ] T049 Run quickstart.md validation checklist (manual - requires WordPress admin)
- [x] T050 Update plugin version in wordpress-plugin/poker-tournament-import/poker-tournament-import.php

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User Stories 1-3 (P1) can proceed in parallel after Foundational
  - User Story 4 (P2) can proceed in parallel after Foundational
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - Extends US1 checkbox logic but independently testable
- **User Story 3 (P1)**: Can start after Foundational (Phase 2) - Uses US2's active formula but independently testable
- **User Story 4 (P2)**: Can start after Foundational (Phase 2) - Independent parser fix

### Within Each User Story

- Backend functions before frontend integration
- AJAX handlers before JavaScript calls
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks (T001-T003) can run in parallel
- All Foundational backend tasks (T004-T009) can run in parallel
- US1 checkbox HTML and CSS (T010, T017-T018) can run in parallel
- US1 JavaScript handlers (T013-T016) can run in parallel after PHP complete
- US2 checkbox HTML and CSS (T021-T022, T025) can run in parallel
- US3 variable helper functions (T029-T031) can run in parallel
- US4 parser investigation (T037) can run in parallel with US3 implementation
- Polish tasks (T042-T045, T047) can run in parallel

---

## Parallel Example: User Story 1

```bash
# Launch all Setup tasks together:
Task T001: Create class-active-formula-manager.php
Task T002: Create active-formula-handler.js
Task T003: Add nonce for AJAX actions

# After Foundational phase, launch US1 frontend in parallel:
Task T010: Add checkbox HTML
Task T017: Add active-tournament class styling
Task T018: Add active formula badge styling

# Then launch US1 JavaScript handlers:
Task T014: Implement checkbox mutual exclusion
Task T015: Implement setActiveFormula AJAX
Task T016: Implement getActiveFormula AJAX
```

---

## Implementation Strategy

### MVP First (User Stories 1-3, P1 Priorities)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (Tournament Active Formula)
4. Complete Phase 4: User Story 2 (Season Active Formula)
5. Complete Phase 5: User Story 3 (Season Points with Variables)
6. **STOP and VALIDATE**: Test all P1 stories independently
7. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → MVP complete (tournament formula selection)
3. Add User Story 2 → Test independently → Season formula selection added
4. Add User Story 3 → Test independently → Full season points with variables
5. Add User Story 4 → Test independently → Bubble calculation fixed
6. Complete Polish → Production ready

### Parallel Team Strategy

With 2 developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (Tournament Active Formula)
   - Developer B: User Story 4 (Bubble Calculation Fix - independent)
3. After US1 complete:
   - Developer A: User Story 2 (Season Active Formula)
   - Developer B: User Story 3 (Season Points Variables - can proceed after US2 partial)
4. Stories complete and integrate

---

## Notes

- [P] tasks = different files, no dependencies on incomplete tasks
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Testing is manual per spec (no automated test tasks)
- WordPress coding standards must be followed
- All AJAX endpoints require nonce verification
- All write operations require manage_options capability
- Backward compatibility maintained - existing formulas work unchanged

---

**Task Summary**:
- Total Tasks: 50
- Setup: 3 tasks
- Foundational: 6 tasks
- User Story 1 (P1): 11 tasks
- User Story 2 (P1): 7 tasks
- User Story 3 (P1): 9 tasks
- User Story 4 (P2): 5 tasks
- Polish: 9 tasks

**Parallel Opportunities**: 22 tasks marked [P] can run in parallel with no conflicts

**Suggested MVP**: Phase 1 + 2 + 3 (User Story 1) - Tournament active formula selection
