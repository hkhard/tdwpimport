# Tasks: Blind Level Scheme Management Screen

**Input**: Design documents from `/specs/002-blind-level-crud/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Tests are NOT included in this feature - focus on implementation per spec.md requirements.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Mobile + API (Expo)**: `mobile-app/src/`, `controller/src/`, `shared/src/`
- Paths below reflect actual project structure from plan.md

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and dependencies

- [X] T001 Install Zod validation library in controller/package.json and mobile-app/package.json
- [X] T002 [P] Add TypeScript types for blind scheme management to shared/src/types/timer.ts (BlindSchemeListItem, CreateBlindSchemeInput, UpdateBlindSchemeInput, BlindSchemeValidationError)
- [X] T003 [P] Create validation utility schemas in controller/src/services/blindSchedule/validation.ts (Zod schemas for BlindLevel and BlindScheme)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Extend blind schedule API service in mobile-app/src/services/api/blindScheduleApi.ts (add createScheme, updateScheme, deleteScheme, duplicateScheme methods)
- [X] T005 Create blind scheme Zustand store in mobile-app/src/stores/blindSchemeStore.ts (state for schemes list, current scheme, CRUD operations, offline sync queue)
- [X] T006 [P] Implement validation service in controller/src/services/blindSchedule/BlindSchemeValidationService.ts (validateScheme, validateLevel, checkDuplicates)
- [X] T007 [P] Extend blind schedule routes in controller/src/api/routes/blindSchedules.ts (add DELETE /:id, POST /:id/duplicate, enhance PUT with validation)
- [X] T008 [P] Add offline sync utilities to mobile-app/src/utils/syncQueue.ts (addToQueue, flushQueue, SyncQueueItem type)
- [X] T009 Configure NetInfo listener in mobile-app/src/App.tsx for offline detection and sync queue flushing

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - View and Navigate Blind Level Schemes (Priority: P1) 🎯 MVP

**Goal**: Display list of all blind schemes with metadata, allow tapping to view details

**Independent Test**: Open Settings > Blind Level Management, verify list shows 3+ schemes with name/level count/duration, tap scheme to view levels

### Implementation for User Story 1

- [X] T010 [P] [US1] Create BlindSchemeListItem component in mobile-app/src/components/BlindSchemeListItem.tsx (displays scheme name, level count, duration, default indicator)
- [X] T011 [P] [US1] Create empty state component in mobile-app/src/components/BlindSchemeEmptyState.tsx (shows prompt to create first scheme)
- [X] T012 [US1] Create BlindSchemeListScreen in mobile-app/src/screens/BlindSchemeListScreen.tsx (FlatList of schemes, pull-to-refresh, tap to view details)
- [X] T013 [US1] Create BlindSchemeDetailScreen in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (read-only view of scheme with all levels using BlindLevelsList component)
- [X] T014 [US1] Integrate blind scheme list into Settings navigation in mobile-app/src/screens/SettingsScreen.tsx (add "Blind Level Management" row that navigates to BlindSchemeListScreen)
- [X] T015 [US1] Add Settings Stack Navigator in mobile-app/src/navigation/AppNavigator.tsx (Settings > BlindSchemeList > BlindSchemeDetail navigation hierarchy)
- [X] T016 [US1] Implement GET /blind-schedules list endpoint in controller/src/api/routes/blindSchedules.ts (support includeDefault query parameter, return BlindSchemeListItem array)
- [X] T017 [US1] Connect blind scheme list to Zustand store in mobile-app/src/screens/BlindSchemeListScreen.tsx (load schemes on mount, handle loading/error states)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Create New Blind Level Scheme (Priority: P1)

**Goal**: Allow users to create custom blind schemes with inline level editing

**Independent Test**: Tap "Create New Scheme", enter name, add 3-5 levels with blinds/duration, save, verify appears in list

### Implementation for User Story 2

- [X] T018 [P] [US2] Create blind level row editor component in mobile-app/src/components/BlindLevelRowEditor.tsx (inline editable fields for smallBlind, bigBlind, ante, duration, isBreak)
- [X] T019 [P] [US2] Create scheme form component in mobile-app/src/components/BlindSchemeForm.tsx (inputs for name, description, startingStack, breakInterval, breakDuration)
- [X] T020 [US2] Create BlindSchemeEditorScreen in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (create mode: scheme form + level rows + add level button + live preview + save)
- [X] T021 [US2] Implement POST /blind-schemes endpoint in controller/src/api/routes/blindSchedules.ts (accept CreateBlindSchemeInput, validate with Zod, insert via repository, return created BlindScheme)
- [X] T022 [US2] Implement createScheme method in controller/src/services/blindSchedule/BlindScheduleService.ts (validate input, check duplicate name, insertWithLevels, return created scheme)
- [X] T023 [US2] Connect create scheme to Zustand store in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (optimistic update, add to sync queue, navigate back to list on success)
- [X] T024 [US2] Add validation error display in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (show Zod validation errors inline for name, levels, blind ratios)
- [X] T025 [US2] Implement level renumbering logic in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (auto-assign sequential level numbers when adding levels)
- [X] T026 [US2] Add "Create New Scheme" button to BlindSchemeListScreen in mobile-app/src/screens/BlindSchemeListScreen.tsx (FAB or header button that navigates to editor in create mode)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Edit Existing Blind Level Scheme (Priority: P1)

**Goal**: Allow users to modify existing schemes with default scheme copy protection

**Independent Test**: Tap existing scheme, tap "Edit", modify level amounts, save, verify changes persist (or copy created for default)

### Implementation for User Story 3

- [X] T027 [P] [US3] Extend BlindSchemeEditorScreen for edit mode in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (load existing scheme, populate form/levels, enable delete level buttons, React Navigation integration)
- [X] T028 [P] [US3] Add unsaved changes detection in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (track dirty state, show confirm dialog on Cancel with hasUnsavedChanges function)
- [X] T029 [US3] Implement PUT /blind-schedules/:id endpoint in controller/src/api/routes/blindSchedules.ts (accept UpdateBlindSchemeInput with levels, validate, check isDefault conflict, update or return 403)
- [X] T030 [US3] Implement updateScheme method in controller/src/services/blindSchedule/BlindScheduleService.ts (validate input, check isDefault flag, updateSchedule with levels support, update/delete levels with auto-numbering)
- [X] T031 [US3] Add default scheme copy logic to controller in controller/src/services/blindSchedule/BlindScheduleService.ts (if isDefault and edited, throw error with message - duplicate endpoint handles copy)
- [X] T032 [US3] Implement POST /blind-schemes/:id/duplicate endpoint in controller/src/api/routes/blindSchedules.ts (create copy with new name, preserve levels, set isDefault=false, return new scheme - already implemented)
- [X] T033 [US3] Connect edit scheme to Zustand store in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (use updateScheme from store, handle 403/copy response, show "Copy created" alert for defaults)
- [X] T034 [US3] Add "Edit Scheme" button to BlindSchemeDetailScreen in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (React Navigation integration, navigate to editor in edit mode, "Create Copy to Edit" for defaults)

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: User Story 4 - Delete Blind Level Scheme (Priority: P2)

**Goal**: Allow users to delete custom schemes with protection for defaults and in-use schemes

**Independent Test**: Tap custom scheme, delete and confirm, verify removed from list; verify default schemes have no delete button

### Implementation for User Story 4

- [X] T035 [P] [US4] Implement DELETE /blind-schedules/:id endpoint in controller/src/api/routes/blindSchedules.ts (check isDefault flag, check isInUse, return 403 for protected, deleteSchedule for custom)
- [X] T036 [P] [US4] Implement deleteScheme method in controller/src/services/blindSchedule/BlindScheduleService.ts (validate isDefault=false, validate isInUse=false, call deleteSchedule, cascade to levels)
- [X] T037 [US4] Add delete button to BlindSchemeDetailScreen in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (show for custom schemes only, hidden for isDefault=true)
- [X] T038 [US4] Implement delete confirmation dialog in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (Alert.alert with confirm/cancel, show warning if scheme was used historically)
- [X] T039 [US4] Connect delete scheme to Zustand store in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (optimistic removal, add to sync queue, navigate back to list on success)
- [X] T040 [US4] Add "isInUse" check to BlindSchemeDetailScreen in mobile-app/src/screens/BlindSchemeDetailScreen.tsx (disable delete button with tooltip if scheme referenced by active tournament)

**Checkpoint**: User Stories 1-4 should all work independently

---

## Phase 7: User Story 5 - Reorder Blind Levels Within Scheme (Priority: P3)

**Goal**: Allow users to reorder blind levels with automatic renumbering

**Independent Test**: Edit scheme with 5+ levels, use move controls to reorder level 3 to position 1, save, verify sequential renumbering

### Implementation for User Story 5

- [X] T041 [P] [US5] Add move controls to BlindLevelRowEditor in mobile-app/src/components/BlindLevelRowEditor.tsx (move up/move down buttons visible only in edit mode)
- [X] T042 [P] [US5] Add "Insert Level at Position" to BlindSchemeEditorScreen in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (add level button inserts at specific position, shifts subsequent levels down)
- [X] T043 [US5] Implement level reordering logic in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (moveLevel function renumbers all levels sequentially, updates level field in BlindLevel array)
- [X] T044 [US5] Validate sequential numbering before save in mobile-app/src/screens/BlindSchemeEditorScreen.tsx (ensure level numbers are 1, 2, 3... without gaps, show error if validation fails)

**Checkpoint**: All user stories should now be independently functional

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T045 [P] Add loading indicators to blind scheme API calls in mobile-app/src/services/api/blindScheduleApi.ts (show spinner during CRUD operations)
- [X] T046 [P] Add error boundaries for blind scheme screens in mobile-app/src/screens/ (wrap BlindSchemeListScreen, BlindSchemeEditorScreen, BlindSchemeDetailScreen)
- [X] T047 [P] Implement AsyncStorage cache invalidation in mobile-app/src/stores/blindSchemeStore.ts (invalidate cache on create/update/delete, refetch from API)
- [X] T048 Add accessibility labels to blind scheme components in mobile-app/src/components/ (accessibilityLabel, accessibilityHint for screen readers)
- [X] T049 [P] Add unit tests for validation schemas in controller/src/services/blindSchedule/validation.test.ts (test Zod schemas for valid/invalid inputs)
- [X] T050 [P] Add integration tests for blind scheme API in controller/src/api/routes/blindSchedules.test.ts (test CRUD endpoints with validation)
- [X] T051 Run quickstart.md validation (test all workflows, verify setup instructions work)
- [X] T052 Code cleanup and refactoring (remove unused imports, consolidate duplicate logic, improve type safety)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-7)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P1 → P2 → P3)
- **Polish (Phase 8)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1) - View**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1) - Create**: Can start after Foundational (Phase 2) - Uses US1 list screen but independently testable
- **User Story 3 (P1) - Edit**: Can start after Foundational (Phase 2) - Uses US1 detail screen but independently testable
- **User Story 4 (P2) - Delete**: Can start after Foundational (Phase 2) - Uses US1/US3 screens but independently testable
- **User Story 5 (P3) - Reorder**: Can start after Foundational (Phase 2) - Extends US2/US3 editor but independently testable

### Within Each User Story

- Parallel tasks [P] can run simultaneously within a story
- Component creation before screen assembly
- API endpoints before mobile app integration
- Core implementation before polish

### Parallel Opportunities

- **Setup Phase**: T002, T003 can run in parallel (different files)
- **Foundational Phase**: T006, T007, T008 can run in parallel
- **Once Foundational phase completes**, all user stories can start in parallel (if team capacity allows)
- **Within US1**: T010, T011 can run in parallel
- **Within US2**: T018, T019 can run in parallel
- **Within US3**: T027, T028 can run in parallel
- **Within US4**: T035, T036 can run in parallel
- **Within US5**: T041, T042 can run in parallel

---

## Parallel Example: User Story 2

```bash
# Launch component creation together:
Task: "Create blind level row editor component in mobile-app/src/components/BlindLevelRowEditor.tsx"
Task: "Create scheme form component in mobile-app/src/components/BlindSchemeForm.tsx"

# Launch backend tasks together:
Task: "Implement POST /blind-schemes endpoint in controller/src/api/routes/blindSchedules.ts"
Task: "Implement createScheme method in controller/src/services/blindSchedule/BlindScheduleService.ts"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T003)
2. Complete Phase 2: Foundational (T004-T009) - CRITICAL
3. Complete Phase 3: User Story 1 (T010-T017)
4. **STOP and VALIDATE**: Test User Story 1 independently (view list, tap to view details)
5. Deploy/demo if ready (MVP delivers value: users can see and navigate schemes)

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo (Users can create schemes)
4. Add User Story 3 → Test independently → Deploy/Demo (Users can edit schemes)
5. Add User Story 4 → Test independently → Deploy/Demo (Users can delete schemes)
6. Add User Story 5 → Test independently → Deploy/Demo (Users can reorder levels)
7. Polish → Final release

Each story adds value without breaking previous stories.

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T001-T009)
2. Once Foundational is done:
   - Developer A: User Story 1 (T010-T017)
   - Developer B: User Story 2 (T018-T026)
   - Developer C: User Story 3 (T027-T034)
3. Stories complete and integrate independently

---

## Task Summary

| Phase | Tasks | Priority |
|-------|-------|----------|
| Phase 1: Setup | 3 tasks | Foundation |
| Phase 2: Foundational | 6 tasks | BLOCKING |
| Phase 3: US1 - View | 8 tasks | P1 (MVP) |
| Phase 4: US2 - Create | 9 tasks | P1 |
| Phase 5: US3 - Edit | 8 tasks | P1 |
| Phase 6: US4 - Delete | 6 tasks | P2 |
| Phase 7: US5 - Reorder | 4 tasks | P3 |
| Phase 8: Polish | 8 tasks | Final |
| **Total** | **52 tasks** | |

**Parallel Opportunities Identified**: 15 tasks marked [P] can run in parallel with others

**Suggested MVP Scope**: Phase 1 + Phase 2 + Phase 3 (T001-T017 = 17 tasks for view-only functionality)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Focus on implementation (tests not requested in spec.md)
