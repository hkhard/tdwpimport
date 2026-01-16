# Tasks: Poker Dashboard Filter Persistence

**Input**: Design documents from `/specs/008-improve-poker-dashboard-filters/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, quickstart.md ✅

**Tests**: Manual testing only - no automated test tasks included per spec.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **Web application**: `wordpress-plugin/poker-tournament-import/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Ensure development environment is ready

*Note: Most setup already complete. This feature modifies existing WordPress plugin code.*

- [x] T001 Verify feature branch `008-improve-poker-dashboard-filters` is checked out
- [x] T002 [P] Verify CSS fix has been applied to `wordpress-plugin/poker-tournament-import/assets/css-dashboard/filters.css` (CSS variables defined at top of file)
- [ ] T003 [P] Restart WordPress site via Local GUI to clear nginx cache
- [ ] T004 [P] Create backup of current plugin state before changes

---

## Phase 2: Foundational (Verification & Testing Setup)

**Purpose**: Verify current implementation and prepare testing environment

**⚠️ CRITICAL**: No deployment until verification phase is complete

- [ ] T005 [P] Load dashboard page http://tdwp.local/hello-world/ and verify page loads without errors
- [ ] T006 [P] Open browser DevTools and verify filters.css is loading (check Network tab for CSS file)
- [ ] T007 [P] Verify computed styles of "Apply Filters" button shows `background-color` with visible color (not `rgba(0,0,0,0)`)
- [ ] T008 [P] Check WordPress user meta table for existing filter preferences: `wp user meta get 1 poker_dashboard_filters --allow-root`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Always-Visible Apply Filter Button (Priority: P1) 🎯 MVP

**Goal**: Make the "Apply Filters" button immediately visible with proper background color on page load

**Independent Test**: Load poker dashboard page and verify button is visible with blue background without any mouse interaction

### Implementation for User Story 1

- [x] T009 [US1] Add CSS custom property definitions to wordpress-plugin/poker-tournament-import/assets/css-dashboard/filters.css (define :root variables at top of file: --dashboard-primary, --dashboard-bg-surface, etc.)
- [ ] T010 [US1] Test button visibility on desktop: Load page, verify button has blue background without hovering
- [ ] T011 [US1] Test button visibility on mobile: Use responsive design mode or touch device, verify button is visible without hover capability
- [ ] T012 [US1] Test button stays visible: Move mouse away from filter area, verify button remains visible
- [ ] T013 [US1] Verify accessibility compliance: Check button has `opacity: 1` and `visibility: visible` in computed styles

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Persistent Filter Selection (Priority: P1)

**Goal**: Ensure filter selection persists across page refreshes and navigation

**Independent Test**: Select a season filter, refresh the page, and verify the selected season remains active in dropdown and URL

### Verification for User Story 2

- [ ] T014 [P] [US2] Test filter persistence via URL: Select "Season 2025" → Apply → Verify URL changes to `?filter_season=2025`
- [ ] T015 [P] [US2] Test persistence across page refresh: Select season → Apply → Refresh page (F5) → Verify dropdown still shows selected season
- [ ] T016 [P] [US2] Test persistence across navigation: Select season → Apply → Navigate away → Return → Verify selection persists
- [ ] T017 [P] [US2] Test filter change: Select "All Seasons" → Apply → Verify new selection persists
- [ ] T018 [US2] Test URL parameter priority: Save preference for Season 2025 → Manually set URL to `?filter_season=2024` → Verify URL takes precedence
- [ ] T019 [P] [US2] Verify user meta storage: Run `wp user meta get 1 poker_dashboard_filters --allow-root` and confirm saved value matches applied filter
- [ ] T020 [US2] Test deleted season fallback: Save preference for Season 2024 → Delete season 2024 → Refresh → Verify fallback to most recent season

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Final validation and documentation

- [ ] T021 [P] Cross-browser testing: Test button visibility and filter persistence in Chrome, Firefox, Safari
- [ ] T022 [P] Mobile testing: Test on iOS Safari and Android Chrome browsers
- [ ] T023 [P] Update feature documentation in specs/008-improve-poker-dashboard-filters/ with implementation results
- [ ] T024 [P] Create distribution package: Update version number in poker-tournament-import.php if needed
- [ ] T025 [P] Run full quickstart.md validation checklist from specs/008-improve-poker-dashboard-filters/quickstart.md
- [ ] T026 Code cleanup: Remove any debug code or temporary files
- [ ] T027 Final smoke test: Load dashboard, apply filter, refresh, verify all functionality works

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-4)**: All depend on Foundational phase completion
  - User Story 1 (US1) and User Story 2 (US2) are independent and can proceed in parallel
- **Polish (Phase 5)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on US2
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - No dependencies on US1
- Both stories are **INDEPENDENT** and can be tested separately

### Within Each User Story

- US1: CSS fix must be complete before visibility tests
- US2: All verification tasks marked [P] can run in parallel
- Both stories can proceed independently after Foundational phase

### Parallel Opportunities

- Setup tasks T002-T004 can run in parallel
- Foundational tasks T005-T008 can run in parallel (within Phase 2)
- US1 task T010-T013 are sequential (test after each fix)
- US2 tasks T014-T020 marked [P] can all run in parallel
- Polish tasks T021-T027 can run in parallel after stories complete

---

## Parallel Example: User Story 2 Verification

```bash
# Launch all US2 verification tasks together:
echo "Testing filter persistence across scenarios..."
# Test 1: URL parameter persistence
# Test 2: Page refresh persistence
# Test 3: Navigation persistence
# Test 4: Filter change persistence
# Test 5: URL parameter priority
# Test 6: User meta storage verification
# Test 7: Deleted season fallback
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup ✅ (already done)
2. Complete Phase 2: Foundational - verify environment and clear caches
3. Complete Phase 3: User Story 1 - CSS fix and button visibility tests
4. **STOP and VALIDATE**: Test button visibility independently
5. Deploy/demo if ready

**MVP Success Criteria**: Button is visible with proper background color

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!) ✅ CSS fix already done
3. Add User Story 2 → Test independently → Deploy/Demo (already works, just needs verification)
4. Each story adds value without breaking previous stories

**Current Status**:
- Phase 1 (Setup): ✅ Complete (branch checked out, CSS fix applied)
- Phase 2 (Foundational): ⚠️ Ready to start (needs nginx restart)
- Phase 3 (US1): ✅ Complete (CSS fix done)
- Phase 4 (US2): ⚠️ Ready for verification (persistence already implemented)
- Phase 5 (Polish): ⚠️ Ready to start after US1+US2 validation

### Parallel Team Strategy

With multiple developers (not needed for this small feature):

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 verification (trivial - CSS fix done)
   - Developer B: User Story 2 verification (persistence already works)
3. Both stories complete and validate independently

**Note**: This is a small feature with minimal parallelization opportunities. Most work is CSS fix (done) and verification.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- CSS fix for US1 is already implemented - just needs testing after nginx restart
- Filter persistence for US2 is already implemented - just needs verification
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence

## Implementation Status Summary

**Complete**:
- ✅ T001: Branch checked out
- ✅ T002: CSS variables added to filters.css
- ✅ T009: CSS fix implementation complete

**Code Implementation Complete**:
- ✅ User Story 1 (Button Visibility): CSS variables defined in filters.css:10-22
- ✅ User Story 2 (Filter Persistence): get_active_filters() and maybe_save_preferences() implemented

**Requires Manual Testing** (user action needed):
- ⏸️ T003: Restart WordPress site via Local GUI to clear nginx cache
- ⏳ T004-T027: All verification tasks pending nginx restart

**Next Immediate Action**: User must restart site via Local GUI, then run `.specify/scripts/bash/verify-008-fixes.sh`
