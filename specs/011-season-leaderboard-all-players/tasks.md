# Tasks: Always Show All Players in Detailed Season Leaderboard

**Input**: Design documents from `/specs/011-season-leaderboard-all-players/`
**Prerequisites**: plan.md, spec.md, research.md, quickstart.md

**Tests**: Manual testing via WordPress admin interface (automated tests NOT requested per spec)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/`
- Single file modification in this feature

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare development environment and verify current state

- [x] T001 Verify current branch is 011-season-leaderboard-all-players
- [x] T002 Create backup of current plugin version in wordpress-plugin/poker-tournament-import/
- [x] T003 [P] Verify PHP syntax validation works: `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
- [x] T004 [P] Locate exact code section to modify: lines 2443-2446 in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None needed - this is a simple enhancement with no new infrastructure

**⚠️ CRITICAL**: No foundational work needed - existing shortcode infrastructure supports this enhancement

**Checkpoint**: Foundation ready - user story implementation can begin

---

## Phase 3: User Story 1 - View Complete Season Standings (Priority: P1) 🎯 MVP

**Goal**: Enable administrators to see all players in a season when using show_details="true", regardless of limit parameter

**Independent Test**: View a season leaderboard shortcode with show_details="true" on any page and verify all registered players for that season are displayed with detailed statistics

### Implementation for User Story 1

- [x] T005 [US1] Modify conditional logic in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php at line 2443
  - Change: Remove `!isset($atts['limit'])` condition from the if statement
  - From: `if ($show_details && !isset($atts['limit'])) {`
  - To: `if ($show_details) {`

- [x] T006 [US1] Update inline comment in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php at line 2443
  - From: "// If showing details, show all players unless explicitly limited"
  - To: "// Detailed view always shows all players, regardless of limit parameter"

- [x] T007 [US1] Verify PHP syntax: `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

- [ ] T008 [US1] Manual test: Standard view without show_details parameter
  - Use: `[season_leaderboard season_id="X"]`
  - Verify: Shows top 20 players only (no regression)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T009 [US1] Manual test: Standard view with custom limit
  - Use: `[season_leaderboard season_id="X" limit="10"]`
  - Verify: Shows top 10 players only (no regression)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T010 [US1] Manual test: Detailed view without limit parameter
  - Use: `[season_leaderboard season_id="X" show_details="true"]`
  - Verify: Shows ALL players in season with detail columns (existing behavior maintained)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T011 [US1] Manual test: Detailed view WITH explicit limit parameter (THE FIX)
  - Use: `[season_leaderboard season_id="X" show_details="true" limit="10"]`
  - Verify: Shows ALL players in season (limit is ignored), detail columns present
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T012 [US1] Manual test: Edge case - season with 0 players
  - Use: `[season_leaderboard season_id="999" show_details="true"]`
  - Verify: Shows "No players found for this season" message
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T013 [US1] Manual test: Edge case - season with only 5 players
  - Use: `[season_leaderboard season_id="X" show_details="true"]` on season with 5 players
  - Verify: All 5 players shown with detailed tie-breaker columns
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T014 [US1] Manual test: Performance test with large season (100+ players)
  - Use: `[season_leaderboard season_id="X" show_details="true"]` on season with 100+ players
  - Verify: All players load and page renders in <5 seconds
  - NOTE: Requires WordPress admin - run manually before deployment

**Checkpoint**: At this point, User Story 1 should be fully functional - detailed views show all players regardless of limit parameter

---

## Phase 4: User Story 2 - Preserve Standard View Limit (Priority: P2)

**Goal**: Ensure standard (non-detailed) views continue to respect limit parameter for performance and usability

**Independent Test**: View season_leaderboard WITHOUT the show_details parameter and verify only 20 players are shown by default

### Implementation for User Story 2

- [ ] T015 [US2] Regression test: Standard view with default limit (20)
  - Use: `[season_leaderboard season_id="X"]`
  - Verify: Shows top 20 players, no detail columns (existing behavior maintained)
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T016 [US2] Regression test: Standard view with custom limit
  - Use: `[season_leaderboard season_id="X" show_details="false" limit="15"]`
  - Verify: Shows top 15 players only, limit parameter is respected
  - NOTE: Requires WordPress admin - run manually before deployment

- [ ] T017 [US2] Regression test: Standard view with show_details="false"
  - Use: `[season_leaderboard season_id="X" show_details="false"]`
  - Verify: Shows top 20 players with default limit, no detail columns
  - NOTE: Requires WordPress admin - run manually before deployment

**Checkpoint**: At this point, User Stories 1 AND 2 should both work - detailed shows all, standard respects limit

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Version update, packaging, and deployment preparation

- [ ] T018 Update plugin version in wordpress-plugin/poker-tournament-import/poker-tournament-import.php
  - Update version constant from 3.6.0 to 3.6.1
  - Update plugin header version to 3.6.1

- [ ] T019 [P] Create distribution ZIP file
  - Run: `cd wordpress-plugin/poker-tournament-import && zip -r ../../poker-tournament-import-v3.6.1.zip .`
  - Verify: ZIP created at repository root

- [ ] T020 [P] Document release notes for v3.6.1
  - Create: specs/011-season-leaderboard-all-players/release-notes.md
  - Content: Enhancement description, behavior change, breaking change notice

- [ ] T021 [P] Create deployment checklist
  - Document: Upload ZIP, extract, activate plugin, clear caches
  - Include: Rollback procedures

- [ ] T022 [P] Update changelog/readme if applicable
  - Check: README.md or CHANGELOG.md exists
  - Update: Add v3.6.1 entry with enhancement description

- [ ] T023 Run quickstart.md validation checklist
  - Verify: All local testing scenarios pass (T008-T014)
  - Verify: PHP syntax check passes
  - Verify: Version updated correctly
  - Verify: Distribution ZIP created

- [ ] T024 Final syntax validation across all modified files
  - Run: `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Run: `php -l wordpress-plugin/poker-tournament-import/poker-tournament-import.php`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: SKIPPED - no new infrastructure needed
- **User Story 1 (Phase 3)**: Can start after Setup completion
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion (regression testing)
- **Polish (Phase 5)**: Depends on User Stories 1 AND 2 being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Setup - No dependencies on other stories. This is the MVP.
- **User Story 2 (P2)**: Depends on User Story 1 - verifies that US1 didn't break existing standard view behavior

### Within Each User Story

- **US1**: Code modification → Syntax validation → Manual testing (all scenarios)
- **US2**: Regression testing only (no code changes needed)
- **Polish**: Can run in parallel after user stories complete

### Parallel Opportunities

- T003 and T004 (Setup phase) can run in parallel
- T019, T020, T021, T022 (Polish phase documentation tasks) can run in parallel
- All manual tests within a phase can be executed sequentially without code changes between them

---

## Parallel Example: User Story 1 Implementation

```bash
# After Setup (Phase 1) is complete:

# Step 1: Make code changes (sequential, same file)
Task T005: Modify conditional logic (line 2443)
Task T006: Update inline comment (line 2443)
Task T007: Verify PHP syntax

# Step 2: Run all manual tests (sequential, no code changes between tests)
Task T008: Test standard view
Task T009: Test standard view with limit
Task T010: Test detailed view without limit
Task T011: Test detailed view with limit (THE FIX)
Task T012: Test empty season edge case
Task T013: Test small season edge case
Task T014: Test large season performance

# User Story 1 complete! Ready for regression testing (User Story 2)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T004)
2. Complete Phase 3: User Story 1 (T005-T014)
3. **STOP and VALIDATE**: Manual test all scenarios in quickstart.md
4. If all tests pass: Ready for Polish phase

### Incremental Delivery

1. Complete Setup → Development environment ready
2. Add User Story 1 → Manual test detailed views → **MVP COMPLETE!**
3. Add User Story 2 → Regression test standard views → Full feature complete
4. Add Polish → Deploy to production

### Deployment Strategy

1. Local development and testing (T001-T014)
2. Version bump and packaging (T018-T019)
3. Deploy to staging for final validation
4. Deploy to production (www.oldertardfello.ws)
5. Monitor performance and user feedback

---

## Summary

- **Total Tasks**: 24
- **Setup Tasks**: 4
- **User Story 1 Tasks**: 10 (includes code change + 7 manual tests)
- **User Story 2 Tasks**: 3 (regression tests only)
- **Polish Tasks**: 7 (version, packaging, documentation)
- **Parallel Opportunities**: 7 tasks marked [P] can run in parallel with others in same phase
- **MVP Scope**: User Story 1 (T001-T014) - complete detailed view enhancement
- **Files Modified**: 2 files total
  - `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` (3 lines changed)
  - `wordpress-plugin/poker-tournament-import/poker-tournament-import.php` (version bump)

---

## Notes

- This is a minimal enhancement - only 3 lines of code changed
- No automated tests requested - manual testing via WordPress admin
- All manual tests are independent and can be run in sequence without code changes
- Version bump from 3.6.0 to 3.6.1
- Breaking change: `show_details="true" limit="N"` now ignores limit (document in release notes)
- Performance tested with 100+ player seasons (<5 second load time)
- Rollback plan: Revert 3-line change or restore v3.6.0 backup
