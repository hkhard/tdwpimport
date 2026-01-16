# Tasks: Season Points Formula Honor Active Formula

**Input**: Design documents from `/specs/012-season-points-formula-fix/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/shortcode-behavior.md

**Tests**: Manual testing via WordPress admin interface (automated tests NOT requested per spec)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/`
- Single file modification in this feature

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare development environment and verify current state

- [x] T001 Verify current branch is 012-season-points-formula-fix
- [x] T002 Create backup of class-series-standings.php in wordpress-plugin/poker-tournament-import/includes/
- [x] T003 [P] Verify PHP syntax validation works: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
- [x] T004 [P] Locate exact code section to remove: lines 287-329 in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None needed - this is a simple bug fix with no new infrastructure

**⚠️ CRITICAL**: No foundational work needed - existing infrastructure supports this fix

**Checkpoint**: Foundation ready - user story implementation can begin

---

## Phase 3: User Story 1 - Apply Active Formula to Season Points (Priority: P1) 🎯 MVP

**Goal**: Fix Season Points calculation to use active season formula (e.g., best_10) by removing incorrect per-tournament evaluation loop

**Independent Test**: Set active formula to "best_10", view season leaderboard with player having 18 tournaments, verify Season Points < Points column

### Implementation for User Story 1

- [x] T005 [US1] Remove per-tournament formula evaluation loop in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php at lines 287-329
  - Delete entire foreach loop that evaluates formula per tournament
  - Remove lines 287-329 completely
  - Verify apply_series_formula() call at line 416 still exists

- [x] T006 [US1] Verify PHP syntax after removing lines: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

- [ ] T007 [US1] Manual test: Season Points with best_10 formula (18 tournaments)
  - Use: Set active formula to "best_10" in WordPress admin
  - Verify: Season Points < Points for players with 10+ tournaments
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T008 [US1] Manual test: Season Points with best_10 formula (5 tournaments)
  - Use: Player with only 5 tournaments in season
  - Verify: Season Points = Points (formula uses all available tournaments)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T009 [US1] Manual test: Season Points with season_total formula
  - Use: Set active formula to "season_total"
  - Verify: Season Points = Points (both sum all tournaments)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T010 [US1] Manual test: Edge case - player with 0 tournaments
  - Use: Season leaderboard with player who has 0 tournaments
  - Verify: Season Points shows 0
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T011 [US1] Manual test: Edge case - no active formula set
  - Use: Clear tdwp_active_season_formula option
  - Verify: Season Points = Points (defaults to simple sum)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T012 [US1] Manual test: Performance test with 100+ player season
  - Use: Season leaderboard with 100+ players
  - Verify: Page renders in < 5 seconds after cache clear
  - NOTE: Requires WordPress admin - run manually before deployment

**Checkpoint**: At this point, User Story 1 should be fully functional - Season Points honors active formula

---

## Phase 4: User Story 2 - Maintain Points Column as Simple Sum (Priority: P2)

**Goal**: Verify Points column continues showing simple sum of all tournaments regardless of active formula

**Independent Test**: View season leaderboard and verify Points column equals manual sum of all tournament points

### Implementation for User Story 2

- [ ] T013 [US2] Regression test: Points column with best_10 formula
  - Use: Active formula set to "best_10"
  - Verify: Points column shows sum of ALL tournaments (not filtered)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T014 [US2] Regression test: Points column with season_total formula
  - Use: Active formula set to "season_total"
  - Verify: Points = Season Points (both show sum of all)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T015 [US2] Regression test: Points >= Season Points relationship
  - Use: Any active formula configuration
  - Verify: Points column value >= Season Points column value
  - NOTE: Requires WordPress admin - run manually before deployment

**Checkpoint**: At this point, User Stories 1 AND 2 should both work - formula applied correctly, Points unchanged

---

## Phase 5: User Story 3 - Support All Season Formula Types (Priority: P2)

**Goal**: Verify fix works with all formula types (best_5, best_10, season_total, custom formulas)

**Independent Test**: Configure different active formulas and verify Season Points calculates correctly for each type

### Implementation for User Story 3

- [ ] T016 [US3] Manual test: best_5 formula with 15 tournaments
  - Use: Set active formula to "best_5"
  - Verify: Season Points = sum of top 5 results only
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T017 [US3] Manual test: direct_sum formula
  - Use: Set active formula to "direct_sum"
  - Verify: Season Points = Points (simple sum behavior)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T018 [US3] Manual test: Custom formula with dependencies
  - Use: Set active formula to custom formula (e.g., weighted average)
  - Verify: Season Points reflects custom calculation correctly
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T019 [US3] Manual test: Formula change updates display
  - Use: Change active formula from best_10 to best_5, refresh page
  - Verify: Season Points immediately update to reflect new formula
  - NOTE: Requires WordPress admin - run manually before deployment

**Checkpoint**: All user stories should now be complete - all formula types work correctly

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Documentation and deployment preparation

- [ ] T020 [P] Update CHANGELOG.md with bug fix details
  - Add version entry describing Season Points formula fix
  - Document breaking changes (none - this is bug fix only)
  - File: wordpress-plugin/poker-tournament-import/CHANGELOG.md

- [ ] T021 [P] Create deployment documentation
  - Document manual testing scenarios
  - Include rollback procedures
  - Create: specs/012-season-points-formula-fix/deployment-guide.md

- [ ] T022 Run quickstart.md validation checklist
  - Verify: All manual testing scenarios pass (T007-T019)
  - Verify: PHP syntax check passes
  - Verify: Code changes applied correctly (lines 287-329 removed)
  - Verify: Performance target met (< 5 seconds for 100+ players)

- [ ] T023 Final syntax validation across modified files
  - Run: `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
  - Verify: No syntax errors

- [ ] T024 [P] Update project documentation if needed
  - Check: CLAUDE.md needs updates for this fix
  - Update: If necessary, add notes about formula calculation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: SKIPPED - no new infrastructure needed
- **User Story 1 (Phase 3)**: Can start after Setup completion
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion (regression testing)
- **User Story 3 (Phase 5)**: Depends on User Story 1 completion (tests different formula types)
- **Polish (Phase 6)**: Depends on User Story 1 completion (documentation)

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Setup - No dependencies on other stories. This is the MVP.
- **User Story 2 (P2)**: Depends on User Story 1 - verifies that US1 didn't break Points column
- **User Story 3 (P3)**: Depends on User Story 1 - verifies formula works across all formula types

### Within Each User Story

- **US1**: Code removal (T005) → Syntax validation (T006) → Manual testing (all scenarios)
- **US2**: Regression testing only (no code changes needed)
- **US3**: Manual testing only (no code changes needed)
- **Polish**: Can run in parallel after user stories complete

### Parallel Opportunities

- T002 and T003 and T004 (Setup phase) can run in parallel
- T020, T021, T024 (Polish phase documentation tasks) can run in parallel
- All manual tests within a phase can be executed sequentially without code changes between them

---

## Parallel Example: User Story 1 Implementation

```bash
# After Setup (Phase 1) is complete:

# Step 1: Make code change (sequential, single task)
Task T005: Remove per-tournament evaluation loop (lines 287-329)
Task T006: Verify PHP syntax

# Step 2: Run all manual tests (sequential, no code changes between tests)
Task T007: Test best_10 formula (18 tournaments)
Task T008: Test best_10 formula (5 tournaments)
Task T009: Test season_total formula
Task T010: Test edge case (0 tournaments)
Task T011: Test edge case (no formula set)
Task T012: Test performance (100+ players)

# User Story 1 complete! Ready for regression testing (User Story 2)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T004)
2. Complete Phase 3: User Story 1 (T005-T012)
3. **STOP and VALIDATE**: Manual test all scenarios in quickstart.md
4. If all tests pass: Ready for Polish phase

### Incremental Delivery

1. Complete Setup → Development environment ready
2. Add User Story 1 → Manual test Season Points formula → **MVP COMPLETE!**
3. Add User Story 2 → Regression test Points column → Full feature complete
4. Add User Story 3 → Test all formula types → Comprehensive validation
5. Add Polish → Deploy to production

### Deployment Strategy

1. Local development and testing (T001-T012)
2. Manual testing verification (T007-T012)
3. Deploy to staging for final validation
4. Deploy to production (www.oldertardfello.ws)
5. Monitor performance and user feedback

---

## Summary

- **Total Tasks**: 24
- **Setup Tasks**: 4
- **User Story 1 Tasks**: 8 (includes code change + 7 manual tests)
- **User Story 2 Tasks**: 3 (regression tests only)
- **User Story 3 Tasks**: 4 (manual tests for different formula types)
- **Polish Tasks**: 5 (documentation, validation)
- **Parallel Opportunities**: 3 tasks marked [P] can run in parallel with others in same phase
- **MVP Scope**: User Story 1 (T001-T012) - complete Season Points formula fix
- **Files Modified**: 1 file total
  - `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` (lines 287-329 removed)

---

## Notes

- This is a minimal bug fix - only 1 file modified (43 lines removed)
- No automated tests requested - manual testing via WordPress admin
- All manual tests are independent and can be run in sequence without code changes
- Breaking changes: None (this is a bug fix restoring correct behavior)
- Performance tested: Season leaderboard must render in < 5 seconds for 100+ players
- Rollback plan: Restore class-series-standings.php from backup if issues occur
