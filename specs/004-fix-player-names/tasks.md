# Tasks: Fix Player Names Display in Season Leaderboard

**Input**: Design documents from `/specs/004-fix-player-names/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅

**Tests**: Manual testing only - no automated test tasks included (per quickstart.md)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **WordPress Plugin**: `wordpress-plugin/poker-tournament-import/` at repository root
- Modified file: `includes/class-series-standings.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify environment and prepare for implementation

- [x] T001 Verify current branch is `004-fix-player-names` with `git branch --show-current`
- [x] T002 Verify PHP syntax validation tool available with `php --version`
- [x] T003 Review quickstart.md implementation guide at /Users/hkh/dev/tdwpimport/specs/004-fix-player-names/quickstart.md

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Locate target method in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (calculate_player_series_data around line 224-338)
- [x] T005 Identify exact lines to modify (lines ~277-283 based on research.md)
- [x] T006 Review existing escaping implementation in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (season_leaderboard_shortcode around line 2426)
- [x] T007 Create test data plan: identify season with tournaments, player UUIDs, and existing player posts for manual testing

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Display Player Names Instead of GUIDs (Priority: P1) 🎯 MVP

**Goal**: Fix season_leaderboard shortcode to display human-readable player names or "Unknown Player" placeholder instead of raw GUIDs

**Independent Test**: View [season_leaderboard] shortcode output and verify all player entries show either actual names or "Unknown Player" placeholder - never GUIDs

### Implementation for User Story 1

- [x] T008 [US1] Locate the GUID fallback code in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (line ~277: `$player_name = $player_id;`)
- [x] T009 [US1] Replace GUID fallback with translated placeholder in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (change `$player_name = $player_id;` to `$player_name = __('Unknown Player', 'poker-tournament-import');`)
- [x] T010 [US1] Add empty title check to player post condition in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (modify `if (!empty($player_post))` to `if (!empty($player_post) && !empty($player_post[0]->post_title))`)
- [x] T011 [US1] Remove redundant esc_url() wrapper in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (change `$player_url = esc_url(get_permalink($player_post[0]->ID));` to `$player_url = get_permalink($player_post[0]->ID);` - URL is escaped later in shortcode)
- [x] T012 [US1] Validate PHP syntax with `php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Link to Player Profiles When Available (Priority: P2)

**Goal**: Ensure player names are clickable links to profile pages when player posts exist (preserving existing functionality)

**Independent Test**: Click player names in rendered leaderboard and verify they navigate to correct player profile pages, or display as plain text when no player post exists

### Implementation for User Story 2

- [x] T013 [US2] Verify player_url is populated correctly in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (ensure `$player_url = get_permalink($player_post[0]->ID);` executes when player post exists)
- [x] T014 [US2] Verify player_url is empty when no player post in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php (ensure `$player_url = '';` is set in placeholder branch)
- [x] T015 [US2] Verify shortcode uses player_url for linking in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php (check season_leaderboard_shortcode around line 2520-2530 for link rendering) - FIXED: Changed from $player->player_id to $player->player_url

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: Manual Testing & Validation

**Purpose**: Comprehensive manual testing per quickstart.md test scenarios

- [ ] T016 Test Case 1 - Player post exists: Create page with [season_leaderboard] shortcode, verify player names displayed and clickable
- [ ] T017 Test Case 2 - Player post missing: View leaderboard for players without posts, verify "Unknown Player" displayed (not GUIDs), verify text is plain (not linked)
- [ ] T018 Test Case 3 - Empty player post title: Create player post with empty title, set _player_uuid, verify "Unknown Player" displayed (not blank)
- [ ] T019 Test Case 4 - Special characters/XSS: Create player post with name `Test<script>alert('xss')</script>`, verify name is escaped as `Test&lt;script&gt;alert('xss')&lt;/script&gt;`
- [ ] T020 Clear WordPress transient cache: Delete `poker_season_standings_*` transients or use wp transient delete command
- [ ] T021 Verify fix persists after cache clear: Refresh leaderboard and confirm "Unknown Player" still displays (not GUIDs)
- [ ] T022 Performance check: Measure page load time before and after change, verify <10% increase per plan.md requirements
- [ ] T023 Check for PHP errors: Review WordPress debug log for any warnings or errors related to the changes

---

## Phase 6: Polish & Deployment Preparation

**Purpose**: Final validation and deployment readiness

- [ ] T024 Verify zero GUIDs visible in shortcode HTML output (view page source and search for UUID patterns)
- [ ] T025 Validate all player profile links functional (click each linked player name, verify 404s don't occur)
- [ ] T026 Test with multiple seasons to ensure fix works across different season selections
- [ ] T027 Update plugin version if needed (edit wordpress-plugin/poker-tournament-import/poker-tournament-import.php header)
- [x] T028 Create distribution zip: `cd wordpress-plugin && zip -r ../poker-tournament-import-v3.5.0-betaXX.zip poker-tournament-import/` (Created: poker-tournament-import-v3.5.0-beta33.zip - 749KB)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational phase completion - No dependencies on other stories
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion - Verifies linking behavior works correctly
- **Testing (Phase 5)**: Depends on User Stories 1 AND 2 being complete
- **Polish (Phase 6)**: Depends on Testing phase completion

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories - This is the MVP
- **User Story 2 (P2)**: Depends on User Story 1 completion - Validates that linking behavior is preserved after the GUID fix

### Within Each User Story

- User Story 1: Tasks T008 → T009 → T010 → T011 → T012 (sequential, all in same file)
- User Story 2: Tasks T013 → T014 → T015 can run in parallel [P] (verification tasks in different files)

### Parallel Opportunities

- User Story 2 verification tasks (T013, T014, T015) can all run in parallel since they check different files
- Testing tasks (T016-T021) can run in any order
- Polish tasks (T024-T026) can run in parallel

---

## Parallel Example: User Story 2 Verification

```bash
# Launch all verification tasks for User Story 2 together:
Task: "Verify player_url is populated correctly in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php"
Task: "Verify player_url is empty when no player post in wordpress-plugin/poker-tournament-import/includes/class-series-standings.php"
Task: "Verify shortcode uses player_url for linking in wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (Tasks T008-T012)
4. **STOP and VALIDATE**: Manual testing with Test Cases 1-4 from quickstart.md
5. Deploy if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy (MVP! Fixes GUID display issue)
3. Add User Story 2 → Test independently → Deploy (Validates linking preserved)
4. Complete Testing & Polish → Final deployment

### Minimal Change Approach

This feature is a **single file, ~3 line change**:

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
**Method**: `calculate_player_series_data()`
**Lines**: ~277-283

**Changes**:
1. Line 277: `$player_id` → `__('Unknown Player', 'poker-tournament-import')`
2. Line 281: Add `&& !empty($player_post[0]->post_title)` condition
3. Line 283: Remove `esc_url()` wrapper

**Total Scope**: 1 method, 1 file, ~3 lines changed

---

## Summary

**Total Tasks**: 28
**Setup Tasks**: 3
**Foundational Tasks**: 4
**User Story 1 Tasks**: 5 (MVP - fixes GUID display bug)
**User Story 2 Tasks**: 3 (validates linking preserved)
**Testing Tasks**: 8 (comprehensive manual testing)
**Polish Tasks**: 5 (deployment readiness)

**MVP Scope**: Tasks T001-T012 (Setup + Foundational + User Story 1)
**Parallel Opportunities**: 6 tasks marked [P] across User Story 2 and Polish phases

**Independent Test Criteria**:
- **User Story 1**: View [season_leaderboard] shortcode → Zero GUIDs visible, player names or "Unknown Player" displayed
- **User Story 2**: Click player names → Links work for players with posts, plain text for players without posts

**Format Validation**: ✅ All tasks follow checkbox format with ID, story labels, and file paths
