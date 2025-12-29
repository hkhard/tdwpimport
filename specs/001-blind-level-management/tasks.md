# Tasks: Blind Level Management

**Input**: Design documents from `/specs/001-blind-level-management/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.yaml

**Tests**: Tests are NOT included in this initial implementation. The feature specification did not explicitly require TDD, and tests can be added in a follow-up phase.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Mobile App**: `mobile-app/src/` at repository root
- **Controller**: `controller/src/` at repository root
- **Shared**: `shared/src/` at repository root
- Paths shown follow the mobile+api structure from plan.md

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [X] T001 Create isDefault column migration in controller/src/db/migrations/002_add_blind_schedule_defaults.sql
- [X] T002 [P] Create TypeScript types extension in mobile-app/src/types/blindSchedule.ts
- [X] T003 [P] Create mobile blind schedule API client in mobile-app/src/services/api/blindScheduleApi.ts
- [X] T004 [P] Create Zustand store for blind schedules in mobile-app/src/stores/blindScheduleStore.ts

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Database Layer (Controller)

- [X] T005 Create BlindScheduleRepository in controller/src/db/repositories/BlindScheduleRepository.ts (CRUD operations)
- [X] T006 [P] Create BlindLevelRepository in controller/src/db/repositories/BlindLevelRepository.ts (levels CRUD)

### Service Layer (Controller)

- [X] T007 Create BlindScheduleService in controller/src/services/blindSchedule/BlindScheduleService.ts (business logic)
- [X] T008 [P] Create DefaultSchedulesLoader in controller/src/services/blindSchedule/DefaultSchedulesLoader.ts (seed Turbo, Standard, Deep Stack)
- [X] T009 Create migration to seed default schedules in controller/src/db/migrations/003_seed_default_schedules.sql

### API Routes (Controller)

- [X] T010 Create blind schedules REST API in controller/src/api/routes/blindSchedules.ts (GET /blind-schedules, POST, PUT, DELETE)
- [X] T011 [P] Create blind levels REST API in controller/src/api/routes/blindSchedules.ts (GET /blind-schedules/{id}/levels, POST, PUT, DELETE)
- [X] T012 [P] Create tournament blind level API in controller/src/api/routes/blindSchedules.ts (GET /tournaments/{id}/blind-level, PUT for manual changes)

### Mobile Infrastructure

- [X] T013 Configure AsyncStorage blind schedule cache in mobile-app/src/services/cache/BlindScheduleCache.ts
- [X] T014 [P] Create blind schedule sync service in mobile-app/src/services/sync/BlindScheduleSync.ts (API + AsyncStorage)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Select Blind Schedule During Setup (Priority: P1) 🎯 MVP

**Goal**: Enable tournament directors to select a blind schedule from a library when creating a tournament

**Independent Test**: A user can create a new tournament and select a blind schedule from the available options. The tournament will then be associated with that blind schedule and can be started.

### Implementation for User Story 1

- [X] T015 [P] [US1] Create BlindScheduleSelector component in mobile-app/src/components/BlindScheduleSelector.tsx (dropdown with schedule list)
- [X] T016 [P] [US1] Update TournamentSetup screen in mobile-app/src/screens/CreateTournamentScreen.tsx (add blind schedule selector, pass to API)
- [X] T017 [US1] Update tournament creation API in controller/src/api/routes/tournaments.ts (accept blindScheduleId parameter)
- [X] T018 [US1] Update tournament repository in controller/src/db/repositories/TournamentRepository.ts (save blindScheduleId, currentBlindLevel)
- [X] T019 [US1] Test blind schedule selection in mobile app (create tournament, select schedule, verify association)

**Checkpoint**: At this point, User Story 1 (Select Blind Schedule) should be fully functional and independently testable

---

## Phase 4: User Story 2 - View Current Blind Level During Tournament (Priority: P1) 🎯 MVP

**Goal**: Display current blind level prominently on tournament detail screen, updating automatically as timer advances

**Independent Test**: A tournament director opens any active tournament and can immediately see the current blind amounts displayed on the timer section.

### Implementation for User Story 2

- [X] T020 [P] [US2] Create BlindLevelDisplay component in mobile-app/src/components/BlindLevelDisplay.tsx (large font current blinds, break indicator)
- [X] T021 [P] [US2] Create WebSocket blind level listener in mobile-app/src/services/websocket/blindLevelWebSocket.ts (subscribe to level changes)
- [X] T022 [US2] Update TournamentDetail screen in mobile-app/src/screens/TournamentDetail.tsx (add BlindLevelDisplay, connect to WebSocket)
- [X] T023 [US2] Update timer broadcaster in controller/src/websocket/broadcaster.ts (broadcast level:change events)
- [X] T024 [US2] Test blind level display (view tournament, verify blinds shown, verify auto-update on level change)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently. Core MVP is complete - directors can select schedules and see current blinds.

---

## Phase 5: User Story 5 - Manual Level Control During Tournament (Priority: P1) 🎯 MVP

**Goal**: Enable manual level up/down controls for adjusting blind levels during tournament play

**Independent Test**: A tournament director can use level up/down buttons to change the current blind level, and the timer and blind display update accordingly.

### Implementation for User Story 5

- [X] T025 [P] [US5] Create level control buttons in mobile-app/src/components/LevelControls.tsx (+ Level, - Level buttons with disable logic)
- [X] T026 [US5] Update TournamentDetail screen in mobile-app/src/screens/TournamentDetail.tsx (add LevelControls component)
- [X] T027 [US5] Implement manual level change API call in mobile-app/src/services/api/blindScheduleApi.ts (PUT /tournaments/{id}/blind-level)
- [X] T028 [US5] Implement manual level change handler in controller/src/api/routes/blindSchedules.ts (accept action: next/previous/set, recalculate timer)
- [X] T029 [US5] Test manual level controls (tap + Level, verify blind update, tap - Level, verify rewind, verify disable at level 1)

**Checkpoint**: At this point, all P1 user stories are complete (Select Schedule, View Blinds, Manual Control). Tournament directors can manage blind levels end-to-end.

---

## Phase 6: User Story 3 - View Upcoming Blind Levels (Priority: P2)

**Goal**: Display list of upcoming blind levels in tournament detail screen, with current level highlighted

**Independent Test**: A tournament director can view the list of upcoming levels and see the blind structure for the next several levels.

### Implementation for User Story 3

- [X] T030 [P] [US3] Create BlindLevelsList component in mobile-app/src/components/BlindLevelsList.tsx (scrollable list, current level highlight)
- [X] T031 [P] [US3] Implement level pagination in mobile-app/src/components/BlindLevelsList.tsx (show next 10, load more on scroll)
- [X] T032 [US3] Update TournamentDetail screen in mobile-app/src/screens/TournamentDetail.tsx (add BlindLevelsList component)
- [X] T033 [US3] Test upcoming levels list (view levels, verify current highlighted, verify auto-update on level change)

**Checkpoint**: At this point, User Stories 1, 2, 5, AND 3 should all work independently. Directors can see upcoming levels and prepare players.

---

## Phase 7: User Story 4 - Create and Edit Blind Schedules (Priority: P2)

**Goal**: Enable tournament directors to create custom blind schedules and save them to their library

**Independent Test**: A user can navigate to a "Blind Schedules" management area, create a new schedule with levels, and then select that schedule when creating a tournament.

### Implementation for User Story 4

- [X] T034 [P] [US4] Create BlindScheduleList screen in mobile-app/src/screens/BlindScheduleList.tsx (list all schedules with edit/delete actions)
- [X] T035 [P] [US4] Create BlindScheduleEditor screen in mobile-app/src/screens/BlindScheduleEditor.tsx (create/edit form with level management)
- [X] T036 [P] [US4] Create level editor component in mobile-app/src/components/LevelEditor.tsx (add/edit/remove blind levels)
- [X] T037 [US4] Implement blind schedule creation API in mobile-app/src/services/api/blindScheduleApi.ts (POST /blind-schedules with levels)
- [X] T038 [US4] Implement blind schedule update API in mobile-app/src/services/api/blindScheduleApi.ts (PUT /blind-schedules/{id})
- [X] T039 [US4] Implement blind schedule deletion with validation in controller/src/services/blindSchedule/BlindScheduleService.ts (check if in use, prevent delete)
- [X] T040 [US4] Test blind schedule CRUD (create schedule, add levels, save, verify appears in list, edit, delete)

**Checkpoint**: At this point, User Stories 1, 2, 3, 4, AND 5 should all work independently. Directors can manage their blind schedule library.

---

## Phase 8: User Story 6 - Pre-Loaded Default Blind Schedules (Priority: P2)

**Goal**: Provide new users with pre-loaded default blind schedules (Turbo, Standard, Deep Stack)

**Independent Test**: A new user installs the app and when creating their first tournament, they see 3-5 pre-loaded blind schedules to choose from.

### Implementation for User Story 6

- [X] T041 [P] [US6] Create default schedule data in controller/src/data/defaultBlindSchedules.ts (Turbo: 10min levels, Standard: 20min, Deep Stack: 30min)
- [X] T042 [US6] Implement default schedule seeding in controller/src/services/blindSchedule/DefaultSchedulesLoader.ts (insert on first run, mark isDefault=true)
- [X] T043 [US6] Implement default schedule protection in controller/src/services/blindSchedule/BlindScheduleService.ts (prevent deletion, create copy on edit)
- [X] T044 [US6] Test default schedules (fresh install, verify 3 schedules exist, try delete default, verify creates copy on edit)

**Checkpoint**: At this point, ALL user stories are complete. New users have immediate access to blind schedules.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T045 [P] Add comprehensive logging to controller blind schedule service in controller/src/services/blindSchedule/BlindScheduleService.ts (structured logging, cache hits/misses)
- [ ] T046 [P] Implement performance monitoring in mobile-app/src/services/analytics/blindScheduleAnalytics.ts (track schedule selection, creation, edit actions)
- [ ] T047 [P] Add loading states to blind schedule UI in mobile-app/src/components/LoadingBlindSchedules.tsx (skeleton screens while fetching)
- [ ] T048 [P] Create empty state components in mobile-app/src/components/EmptyBlindScheduleList.tsx (no schedules message, create CTA)
- [ ] T049 [P] Add accessibility features in mobile-app/src/components/BlindLevelDisplay.tsx (screen reader support for blind amounts)
- [ ] T050 [P] Implement error boundaries in mobile-app/src/screens/BlindScheduleEditor.tsx (graceful error handling)
- [ ] T051 [P] Add internationalization support in mobile-app/assets/i18n/blindSchedules/en.json (English blind schedule labels)
- [ ] T052 Validate blind schedule API contracts in controller/tests/contract/test-blind-schedules-api.ts (OpenAPI compliance)
- [ ] T053 Run quickstart.md validation in scripts/test/quickstart-validation.sh (verify all test scenarios pass)
- [ ] T054 Update README.md in repository root with blind level management documentation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-8)**:
  - US1 (Select Schedule), US2 (View Blinds), US5 (Manual Control) are all P1 and should be implemented first
  - US1 and US2 are tightly coupled (need schedule to view blinds)
  - US3 (Upcoming Levels) can proceed after US2 (needs blind display)
  - US4 (Create/Edit Schedules) is independent, can be done anytime after Foundational
  - US6 (Default Schedules) is independent, can be done anytime after Foundational
- **Polish (Phase 9)**: Depends on all desired user stories being complete

### User Story Dependencies

```
┌─────────────────┐
│  Phase 1: Setup │
└────────┬─────────┘
         │
         v
┌─────────────────┐
│ Phase 2: Found.  │ ────── BLOCKS ─────┐
└────────┬─────────┘                     │
         │                                v
         v                    ┌─────────────────────┐
    ┌─────┴─────┬────────┐      │ US1: Select Schedule │
    │           │        │      │    (P1 - Setup)      │
    v           v        v      └─────────────────────┘
┌──────┐   ┌──────┐  ┌─────┐              │
│ US2  │   │ US5  │  │ US6 │              │
│ View │   │Manual│  │Def. │              │
│Blinds│   │Ctrl. │  │Sched│              │
│ (P1) │   │ (P1) │  │ (P2)│              │
└──┬───┘   └───┬──┘  └──┬─┘              │
   │           │        │                  │
   └─────┬─────┴────────┘                  │
         │                                 │
         v                                 │
    ┌─────────┐                            │
    │   US3   │                            │
    │ Upcoming│                            │
    │ (P2)   │                            │
    └────┬────┘                            │
         │                                 │
         v                                 │
    ┌─────────┐                            │
    │   US4   │                            │
    │ Create │                            │
    │ (P2)   │                            │
    └────┬────┘                            │
         │                                 │
         v                                 v
    ┌────────────────────────────────────────┐
    │      Phase 9: Polish                  │
    └────────────────────────────────────────┘
```

### Within Each User Story

- Components marked [P] can be created in parallel
- API routes depend on service layer
- Service layer depends on repositories
- Mobile screens depend on components and API clients
- Test after implementation (not TDD approach for this feature)

### Parallel Opportunities

**Setup Phase (Phase 1)**:
- T002, T003, T004 can run in parallel (types, API client, store are independent)

**Foundational Phase (Phase 2)**:
- T006 (BlindLevelRepository) can run parallel to other foundational tasks
- T011, T012 (API routes) can run in parallel
- T013, T014 (mobile cache/sync) can run in parallel

**User Story Phases**:
- Within each user story, tasks marked [P] can run in parallel
- Example US1: T015, T016 can run in parallel (component and screen updates)
- Example US2: T020, T021 can run in parallel (component and WebSocket client)

**Cross-Story Parallelization** (after Foundational complete):
- US3 (Upcoming Levels) can be developed parallel to US4 (Create/Edit Schedules)
- US6 (Default Schedules) can be developed anytime independently
- US1/US2/US5 (P1 stories) should be completed first for MVP, but could have team members working on different stories in parallel if staffed

---

## Parallel Example: User Story 1

```bash
# Launch all components for User Story 1 together:
Task: "T015 [P] [US1] Create BlindScheduleSelector component"
Task: "T016 [P] [US1] Update TournamentSetup screen"
```

---

## Implementation Strategy

### MVP First (User Stories 1, 2, 5 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (Select Blind Schedule)
4. Complete Phase 4: User Story 2 (View Current Blinds)
5. Complete Phase 5: User Story 5 (Manual Level Control)
6. **STOP and VALIDATE**: Test core blind management functionality (select schedule, view blinds, manual control) in real-world scenario
7. Deploy/demo MVP with P1 features only

**MVP Scope**: Tournament directors can select blind schedules, view current blinds, and manually adjust levels. This delivers the core blind management value proposition.

### Incremental Delivery (Add P2 Features)

1. After MVP is stable, add Phase 6: User Story 3 (Upcoming Levels) → Demo
2. Then add Phase 7: User Story 4 (Create/Edit Schedules) → Deploy P2 release
3. Finally add Phase 8: User Story 6 (Default Schedules) → Deploy full feature set

### Parallel Team Strategy

With 2 developers after Foundational phase:

1. **Developer A**: US1 (Select Schedule) + US2 (View Blinds) - tightly coupled, best done together
2. **Developer B**: US5 (Manual Control) + US3 (Upcoming Levels) - can proceed after US2

With 3 developers after Foundational phase:

1. **Dev A**: US1 (Select Schedule) - setup screen and API
2. **Dev B**: US2 (View Blinds) - display component and WebSocket
3. **Dev C**: US5 (Manual Control) - controls and level change API

---

## Task Summary

**Total Tasks**: 54 tasks
**Tasks by Phase**:
- Phase 1 (Setup): 4 tasks
- Phase 2 (Foundational): 10 tasks
- Phase 3 (US1 - Select Schedule): 5 tasks
- Phase 4 (US2 - View Blinds): 5 tasks
- Phase 5 (US5 - Manual Control): 5 tasks
- Phase 6 (US3 - Upcoming Levels): 4 tasks
- Phase 7 (US4 - Create/Edit): 7 tasks
- Phase 8 (US6 - Default Schedules): 4 tasks
- Phase 9 (Polish): 10 tasks

**Tasks by User Story**:
- US1 (Select Blind Schedule): 5 tasks
- US2 (View Current Blinds): 5 tasks
- US3 (Upcoming Levels): 4 tasks
- US4 (Create/Edit Schedules): 7 tasks
- US5 (Manual Level Control): 5 tasks
- US6 (Default Schedules): 4 tasks

**Parallel Opportunities**: 25+ tasks marked [P] can run in parallel within their phases, enabling efficient team utilization

**Independent Tests**: Each user story has clear independent test criteria, enabling incremental delivery and validation

**MVP Path**: Phases 1-5 deliver complete blind level management (select, view, control). Phases 6-8 add schedule library and defaults. Phase 9 polishes the complete system.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Tasks follow strict checklist format: `- [ ] [ID] [P?] [Story?] Description with file path`
