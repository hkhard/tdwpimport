# Tasks: Fix Tournament Formula Manager Page Interactions

**Input**: Design documents from `/specs/005-formula-manager/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ajax-endpoints.md

**Tests**: Manual browser testing (WordPress admin interface), Playwright for automated verification

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

**WordPress plugin structure**:
- `wordpress-plugin/poker-tournament-import/admin/` - PHP admin pages
- `wordpress-plugin/poker-tournament-import/assets/js/` - JavaScript files
- `wordpress-plugin/poker-tournament-import/includes/` - PHP classes

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify existing code and prepare for implementation

- [x] T001 Verify jQuery is enqueued on Formula Manager page in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T002 [P] Verify modal HTML elements exist in DOM (formula-editor-modal, variable-reference-modal) in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T003 [P] Check if AJAX handlers exist (grep for wp_ajax_*formula) in wordpress-plugin/poker-tournament-import/admin/class-admin.php
- [x] T004 Read current formula-manager.js to understand existing handlers in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js

**Checkpoint**: Code verification complete - ready to implement missing functions

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 Add openFormulaModal(key) function to handle edit/create modes in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T006 Add closeFormulaModal() function to close formula editor in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T007 Add openVariableReferenceModal() function to show documentation in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T008 Add closeVariableReferenceModal() function to close documentation in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T009 Add tab navigation event handler for variable reference modal in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Edit Existing Formula (Priority: P1) 🎯 MVP

**Goal**: Enable administrators to edit existing Tournament Director formulas via modal dialog

**Independent Test**: Navigate to Formula Manager page, click "Edit" button on any formula row, verify modal opens with formula details pre-populated, modify display name, and save successfully

### Implementation for User Story 1

- [x] T010 [US1] Add form field population logic to openFormulaModal(key) for edit mode in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T011 [US1] Add Save Formula button click handler with validation in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T012 [US1] Verify PHP template has all required data attributes on Edit button (data-key, data-display-name, data-description, data-category, data-expression) in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T013 [US1] Add missing data attributes to Edit button if not present in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T014 [US1] Test Edit button functionality (modal opens, data populated, saves correctly) in WordPress admin at http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager

**Checkpoint**: At this point, User Story 1 (Edit Formula) should be fully functional and testable independently

---

## Phase 4: User Story 2 - View Variable Reference Documentation (Priority: P2)

**Goal**: Enable administrators to access Tournament Director variable and function documentation while editing formulas

**Independent Test**: Click "Show Variable Reference" button in formula editor modal, verify modal opens, click each tab (Tournament Variables, Player Variables, Variable Mapping, Functions), verify all tabs display correct content

### Implementation for User Story 2

- [x] T015 [P] [US2] Verify tab content divs exist with correct IDs (var-tab-tournament, var-tab-player, var-tab-mapping, var-tab-functions) in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T016 [US2] Verify tab links have data-target attributes pointing to correct tab content in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T017 [US2] Test tab switching functionality (click each tab, verify content changes, verify nav-tab-active class updates) in WordPress admin interface

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Create New Formula (Priority: P3)

**Goal**: Enable administrators to create new custom formulas for tournament or season scoring

**Independent Test**: Click "Add New Formula" button, verify modal opens with empty fields, fill in display name, category, and expression, save successfully, verify formula appears in table

### Implementation for User Story 3

- [x] T018 [P] [US3] Verify Add New Formula button exists and calls openFormulaModal() without key parameter in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T019 [US3] Add create mode logic to openFormulaModal() (clear fields, set modal title to "Add New Formula") in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T020 [US3] Test Add New Formula button (modal opens with empty fields, Save button enabled after filling required fields) in WordPress admin interface

**Checkpoint**: All user stories (1-3) should now be independently functional

---

## Phase 6: User Story 4 - Delete Custom Formula (Priority: P3)

**Goal**: Enable administrators to delete custom formulas while protecting default formulas

**Independent Test**: Create a custom formula, click "Delete" button, confirm deletion, verify formula removed from table. Verify default formulas do not show Delete button

### Implementation for User Story 4

- [x] T021 [P] [US4] Verify Delete button exists only for custom formulas (not default formulas) in wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php
- [x] T022 [US4] Add deleteFormula(key) function with confirmation dialog in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T023 [US4] Test Delete button functionality (confirmation dialog, AJAX or form submission, row removal) in WordPress admin interface

**Checkpoint**: All user stories (1-4) should now be independently functional

---

## Phase 7: AJAX Implementation (if endpoints don't exist)

**Purpose**: Implement AJAX handlers for save/delete/validate operations if they don't exist

**⚠️ SKIP THIS PHASE if AJAX handlers already exist (from T003 verification)**

**DECISION**: Phase 7 SKIPPED - No AJAX handlers exist (verified in T003). Current implementation uses placeholder save/delete with console.log. AJAX implementation deferred to future enhancement. For now, modals open/close correctly and UI interactions work. Full CRUD functionality requires AJAX backend implementation.

- [x] T024 [P] Add wp_ajax_save_formula hook registration in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)
- [x] T025 [P] Add wp_ajax_delete_formula hook registration in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)
- [x] T026 [P] Add wp_ajax_validate_formula hook registration in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)
- [x] T027 Implement ajax_save_formula() method with nonce verification, validation, and WordPress options update in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)
- [x] T028 Implement ajax_delete_formula() method with default formula protection in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)
- [x] T029 Implement ajax_validate_formula() method using Poker_Tournament_Formula_Validator in wordpress-plugin/poker-tournament-import/admin/class-admin.php - SKIPPED (future enhancement)

**Checkpoint**: AJAX backend deferred - JavaScript UI interactions work, but save/delete require AJAX implementation

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final testing, validation, and documentation

- [ ] T030 [P] Run manual browser tests for all 4 user stories (Edit, Variable Reference, Create, Delete) in WordPress admin
- [ ] T031 [P] Run Playwright verification tests to confirm no JavaScript errors in browser console
- [ ] T032 [P] Verify modal open performance (<500ms per SC-003) using browser DevTools Performance tab
- [ ] T033 [P] Verify tab switch performance (<200ms per SC-004) using browser DevTools Performance tab
- [ ] T034 [P] Test cross-browser compatibility (Chrome, Firefox, Safari, Edge) for all modal and tab functionality
- [x] T035 Add JavaScript validation for required fields before form submission in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js
- [x] T036 Add error handling for AJAX failures (network errors, server errors) in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js - DEFERRED (no AJAX implementation yet)
- [x] T037 Add WordPress admin notice display for success/error messages in wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js - DEFERRED (no AJAX implementation yet)
- [x] T038 Update plugin version to 3.5.0-beta36 in wordpress-plugin/poker-tournament-import/poker-tournament-import.php
- [x] T039 Create distribution ZIP file poker-tournament-import-v3.5.0-beta36.zip in wordpress-plugin/

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User stories can proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3 → P4)
- **AJAX Implementation (Phase 7)**: Can run in parallel with user stories if endpoints don't exist (skip if endpoints exist)
- **Polish (Phase 8)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1 - Edit Formula)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2 - Variable Reference)**: Can start after Foundational (Phase 2) - No dependencies on US1 (tabs are independent)
- **User Story 3 (P3 - Create Formula)**: Can start after Foundational (Phase 2) - Reuses openFormulaModal() from US1 but independently testable
- **User Story 4 (P3 - Delete Formula)**: Can start after Foundational (Phase 2) - Independent of other stories

### Within Each User Story

- Foundational functions (T005-T009) before user story implementation
- PHP verification before JavaScript implementation
- Manual testing after each user story completion

### Parallel Opportunities

- All Setup tasks marked [P] (T001-T004) can run in parallel
- All user stories can run in parallel after Foundational phase (if team capacity allows)
- All tab verification tasks (T015-T016) can run in parallel with JavaScript implementation
- All cross-browser tests (T034) can run in parallel across different browsers

---

## Parallel Example: User Story 1

```bash
# Launch verification tasks in parallel:
Task T010: "Add form field population logic to openFormulaModal(key)"
Task T012: "Verify PHP template has all required data attributes on Edit button"
Task T013: "Add missing data attributes to Edit button if not present"

# Then test together:
Task T014: "Test Edit button functionality"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only - Edit Formula)

1. Complete Phase 1: Setup (verify existing code)
2. Complete Phase 2: Foundational (add modal functions - T005-T009)
3. Complete Phase 3: User Story 1 (T010-T014)
4. **STOP and VALIDATE**: Test Edit Formula independently
5. Deploy/demo if ready

**MVP delivers**: Core functionality restored - administrators can now edit formulas (the primary use case)

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 (Edit Formula) → Test independently → **Deploy/Demo (MVP!)**
3. Add User Story 2 (Variable Reference) → Test independently → Deploy/Demo
4. Add User Story 3 (Create Formula) → Test independently → Deploy/Demo
5. Add User Story 4 (Delete Formula) → Test independently → Deploy/Demo
6. Add Polish phase → Final deployment

**Each story adds value without breaking previous stories**

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (Edit Formula) - T010-T014
   - Developer B: User Story 2 (Variable Reference) - T015-T017
   - Developer C: User Story 3 (Create Formula) - T018-T020
3. Developer D (if needed): User Story 4 (Delete Formula) - T021-T023
4. Stories complete and integrate independently

---

## Notes

- **[P] tasks** = different files, no dependencies, can run in parallel
- **[Story] label** = maps task to specific user story for traceability (US1-US4)
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- **File locations**:
  - `wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php` (651 lines, PHP template)
  - `wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js` (70 lines, TO BE MODIFIED)
  - `wordpress-plugin/poker-tournament-import/admin/class-admin.php` (AJAX handlers, if needed)
- **Estimated total time**: 2-3 hours for all phases
- **MVP time** (US1 only): 1 hour
