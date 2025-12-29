# Tasks: Cross-Platform Tournament Director Platform

**Input**: Design documents from `/specs/002-expo-rewrite/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

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

- [X] T001 Create monorepo root structure with mobile-app/, controller/, shared/, scripts/ directories
- [X] T002 Initialize shared TypeScript package in shared/ with package.json, tsconfig.json (strict mode)
- [X] T003 [P] Initialize mobile-app Expo project with TypeScript strict mode in mobile-app/
- [X] T004 [P] Initialize controller Node.js project in controller/ with package.json, tsconfig.json (strict mode)
- [X] T005 [P] Configure ESLint and Prettier for TypeScript with shared config in scripts/eslint/
- [X] T006 [P] Configure Git repository with .gitignore excluding node_modules/, .env, data/, build/
- [X] T007 Create environment configuration templates in controller/.env.example and mobile-app/.env.example
- [X] T008 Configure TypeScript path aliases for shared code in mobile-app/tsconfig.json and controller/tsconfig.json
- [X] T009 Create shared type definition templates in shared/src/types/ directory

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Shared Type System

- [X] T010 Define shared TypeScript types in shared/src/types/tournament.ts (Tournament, TournamentStatus, TimerState)
- [X] T011 [P] Define shared TypeScript types in shared/src/types/player.ts (Player, TournamentPlayer, PlayerStats)
- [X] T012 [P] Define shared TypeScript types in shared/src/types/timer.ts (TimerEvent, BlindLevel, TimerEventType)
- [X] T013 [P] Define shared TypeScript types in shared/src/types/sync.ts (SyncRecord, Change, Conflict, SyncStatus)
- [X] T014 [P] Define shared TypeScript types in shared/src/types/api.ts (ApiResponse, ApiError, PaginatedResponse)
- [X] T014a [P] Create terminology glossary in specs/002-expo-rewrite/glossary.md (standardize deviceId, elapsedTime, timestamp naming conventions)
- [X] T015 Define shared validation schemas using Zod in shared/src/schemas/ (tournamentSchema, playerSchema, syncSchema)

### Database Layer (Controller)

- [X] T016 Setup SQLite database connection in controller/src/db/connection.ts with better-sqlite3, WAL mode enabled
- [X] T017 Create database migration system in controller/src/db/migrations/ with migration runner
- [X] T018 Create initial schema migration in controller/src/db/migrations/001_initial_schema.sql (users, tournaments, players, timer_events, sync_records, blind_schedules, blind_levels, tournament_players)
- [X] T019 Create repository base class in controller/src/db/repositories/BaseRepository.ts
- [X] T020 [P] Implement TournamentRepository in controller/src/db/repositories/TournamentRepository.ts
- [X] T021 [P] Implement PlayerRepository in controller/src/db/repositories/PlayerRepository.ts
- [X] T022 [P] Implement TournamentPlayerRepository in controller/src/db/repositories/TournamentPlayerRepository.ts
- [X] T023 [P] Implement TimerEventRepository in controller/src/db/repositories/TimerEventRepository.ts
- [X] T024 [P] Implement SyncRecordRepository in controller/src/db/repositories/SyncRecordRepository.ts
- [X] T025 Implement replication support in controller/src/db/replication/primary.ts (WAL file management for standby sync)
- [X] T026 Implement standby controller logic in controller/src/db/replication/standby.ts (WAL polling, failover promotion)

### Mobile Database Layer

- [X] T027 Setup Expo SQLite database in mobile-app/src/db/connection.ts with async API
- [X] T028 Create mobile database schema matching controller in mobile-app/src/db/schema.ts
- [X] T029 Create mobile migrations system in mobile-app/src/db/migrations.ts
- [X] T030 Implement mobile repository pattern in mobile-app/src/db/repositories/ (TournamentRepository, PlayerRepository)

### API Framework (Controller)

- [X] T031 Setup Fastify server in controller/src/server.ts with CORS, JSON parsing, error handling
- [X] T032 [P] Implement authentication middleware in controller/src/middleware/auth.ts (JWT validation)
- [X] T033 [P] Implement request validation middleware in controller/src/middleware/validation.ts (Zod schemas)
- [X] T034 [P] Implement error handler in controller/src/middleware/errorHandler.ts (standardized error responses)
- [X] T035 Create health check endpoint in controller/src/routes/health.ts for monitoring

### WebSocket Infrastructure (Controller)

- [X] T036 Setup WebSocket server using @fastify/websocket in controller/src/websocket/server.ts
- [X] T037 Implement client connection management in controller/src/websocket/server.ts (track connections per tournament)
- [X] T038 Implement event broadcasting in controller/src/websocket/server.ts (timer updates, player changes)
- [X] T039 Implement heartbeat/ping mechanism in controller/src/websocket/server.ts (30s interval, detect stale connections)

### Authentication & Security

- [X] T040 Implement JWT token generation/validation in controller/src/services/authService.ts
- [X] T041 Implement password hashing (bcrypt) in controller/src/services/authService.ts
- [X] T042 [P] Implement SecureStore wrapper in mobile-app/src/services/secureStorage.ts (token storage)
- [X] T043 [P] Implement authentication API routes in controller/src/routes/auth.ts (register, login)
- [X] T044 [P] Create auth context/hooks in mobile-app/src/hooks/useAuth.ts (authentication state)

### State Management (Mobile)

- [X] T045 Setup Zustand store in mobile-app with store structure (stores/timerStore.ts, stores/tournamentStore.ts, stores/syncStore.ts, stores/userStore.ts)
- [X] T046 [P] Implement timer store in mobile-app/src/stores/timerStore.ts (current level, elapsed time, status)
- [X] T047 [P] Implement tournament store in mobile-app/src/stores/tournamentStore.ts (active tournament, list)
- [X] T048 [P] Implement sync store in mobile-app/src/stores/syncStore.ts (sync status, conflicts, offline queue)

### Navigation (Mobile)

- [X] T049 Setup React Navigation in mobile-app/src/navigation/ with AppNavigator and TabNavigator
- [X] T050 [P] Create screen components structure in mobile-app/src/screens/ (TournamentList.tsx, TimerScreen.tsx, PlayerManager.tsx, SettingsScreen.tsx)

### Core Services (Shared)

- [X] T051 Implement sync orchestration base class in shared/src/services/sync/SyncOrchestrator.ts
- [X] T052 Implement conflict resolution logic in shared/src/services/sync/ConflictResolver.ts (last-write-wins algorithm)
- [X] T053 Implement time synchronization utilities in shared/src/utils/timeSync.ts (drift correction, NTP sync)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Precision Tournament Timer (Priority: P1) 🎯 MVP

**Goal**: Implement highly accurate, reliable timer that maintains 1/10th second precision through app backgrounding, device restarts, and network disconnections.

**Independent Test**: Run timer for 8+ hours across app backgrounding, device restarts, and network disconnection; verify elapsed time matches wall-clock time within 1 second.

### Implementation for User Story 1

- [X] T054 [P] [US1] Create TimerState type in shared/src/types/timer.ts with elapsedTime, level, remainingTime, tenths
- [X] T055 [P] [US1] Create TimerService interface in shared/src/services/TimerService.ts (start, pause, resume, get state)
- [X] T056 [US1] Implement local timer engine in mobile-app/src/services/timer/LocalTimerEngine.ts (100ms precision updates, requestAnimationFrame-based)
- [X] T057 [US1] Implement timer state persistence in mobile-app/src/services/timer/TimerPersistence.ts (AsyncStorage for survival across restarts)
- [X] T058 [US1] Implement timer backgrounding handler in mobile-app/src/services/timer/TimerBackgroundHandler.ts (AppState listener, background time tracking)
- [X] T059 [US1] Implement server time synchronization in mobile-app/src/services/timer/TimeSyncService.ts (periodic server timestamp fetch, drift correction)
- [X] T060 [US1] Implement hybrid timer (local + server sync) in mobile-app/src/hooks/useTimer.ts (combines local precision with server authority)
- [X] T061 [US1] Create TimerDisplay component in mobile-app/src/components/TimerDisplay.tsx (60 FPS updates, tenths display)
- [X] T062 [US1] Implement BlindLevelDisplay component in mobile-app/src/components/BlindLevelDisplay.tsx (current blinds, ante display)
- [X] T063 [US1] Implement timer controls UI in mobile-app/src/screens/TimerScreen.tsx (start, pause, resume buttons, level display)
- [X] T064 [US1] Implement server-side timer service in controller/src/services/timerService.ts (authoritative timer state, event logging)
- [X] T065 [US1] Implement timer API endpoints in controller/src/api/routes/timer.ts (POST /tournaments/:id/start, /pause, /resume)
- [X] T066 [US1] Implement timer event logging in controller/src/services/timerEventLogger.ts (all state changes recorded with timestamps)
- [X] T067 [US1] Implement WebSocket timer updates in controller/src/websocket/broadcaster.ts (broadcast timer state on changes)
- [X] T068 [US1] Implement mobile WebSocket timer listener in mobile-app/src/services/websocket/timerWebSocket.ts (subscribe to timer updates, sync local state)
- [X] T069 [US1] Implement automatic blind level progression in mobile-app/src/services/timer/LevelProgressionService.ts (trigger next level when timer expires)
- [X] T070 [US1] Create timer precision stress test in scripts/test/timer-precision-test.ts (8+ hour run, validate <1s drift)
- [X] T070a [US1] Create 100ms precision validation test in mobile-app/__tests__/e2e/timer-precision.test.ts (verify tenths-of-second display accuracy, validate <100ms drift per constitution requirement)

**Checkpoint**: At this point, User Story 1 (Precision Timer) should be fully functional and independently testable

---

## Phase 4: User Story 2 - Offline Tournament Management (Priority: P1) 🎯 MVP

**Goal**: Enable tournament directors to manage tournaments (register players, record bustouts, adjust settings) without network connectivity, with automatic data syncing when connection is restored.

**Independent Test**: Put device in airplane mode, register 50 players, record 30 bustouts, adjust blind levels; verify all data syncs correctly to central controller when WiFi reconnected.

### Implementation for User Story 2

- [x] T071 [P] [US2] Create offline queue manager in mobile-app/src/services/sync/OfflineQueue.ts (persist changes to AsyncStorage)
- [x] T072 [P] [US2] Implement sync status detection in mobile-app/src/services/sync/NetworkMonitor.ts (detect connectivity changes)
- [x] T073 [US2] Implement sync orchestrator in mobile-app/src/services/sync/SyncService.ts (upload changes, pull changes, conflict resolution)
- [x] T074 [US2] Implement sync API endpoint in controller/src/api/routes/sync.ts (POST /sync for upload, GET /sync for pull)
- [x] T075 [US2] Implement change tracking in controller/src/services/sync/ChangeTracker.ts (track entity changes with timestamps)
- [x] T076 [US2] Implement conflict detection in controller/src/services/sync/ConflictDetectionService.ts (compare timestamps, detect overwrites)
- [x] T077 [US2] Implement last-write-wins resolution in controller/src/services/sync/ConflictResolver.ts (server timestamp wins, notify client)
- [x] T078 [US2] Create sync status indicator UI in mobile-app/src/components/SyncStatusIndicator.tsx (connected/syncing/offline display)
- [x] T079 [US2] Implement offline data persistence in mobile-app/src/db/repositories/ (all repositories write locally first)
- [x] T080 [US2] Create player registration form in mobile-app/src/screens/PlayerManager.tsx (works offline, queues to sync)
- [x] T081 [US2] Implement bustout recording in mobile-app/src/components/PlayerListItem.tsx (record finish position, update payouts)
- [x] T082 [US2] Implement automatic payout calculation in mobile-app/src/services/payout/PayoutCalculator.ts (based on finish position and prize pool)
- [x] T083 [US2] Create sync conflict resolution UI in mobile-app/src/screens/SyncConflictScreen.tsx (show diff, prompt user for resolution)
- [x] T084 [US2] Implement exponential backoff reconnection in mobile-app/src/services/sync/ReconnectionService.ts (1s → 2s → 4s → 8s → 15s max)
- [x] T085 [US2] Create offline mode test in scripts/test/offline-sync-test.ts (simulate airplane mode, verify sync)
- [x] T085a [US2] Create offline core functionality stress test in mobile-app/__tests__/e2e/offline-core-functions.test.ts (24hr offline period, validate player registration, bustout recording, settings adjustments work without network, verify data integrity on reconnection per constitution requirement)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently. Core MVP is complete - directors can run tournaments with precise timing and offline capability.

---

## Phase 5: User Story 5 - Central Controller Timekeeping (Priority: P1) 🎯 MVP

**Goal**: Implement web-based central controller serving as authoritative time source, managing multiple simultaneous tournaments with automatic failover and data replication.

**Independent Test**: Run 10 simultaneous tournaments, disconnect central controller for 5 minutes, reconnect and verify all timers sync correctly with proper state reconciliation.

**Note**: This story implements the server-side timer infrastructure that complements the mobile app timer from US1.

### Implementation for User Story 5

- [x] T086 [P] [US5] Implement multi-tournament timer management in controller/src/services/timer/MultiTimerManager.ts (manage 100+ concurrent timers)
- [x] T087 [US5] Create tournament allocation service in controller/src/services/tournament/TournamentAllocator.ts (assign unique IDs, manage active list)
- [x] T088 [US5] Implement timer state recovery after restart in controller/src/services/timer/TimerRecoveryService.ts (restore from TimerEvent log within 1s)
- [x] T089 [US5] Implement automatic failover detection in controller/src/services/health/FailoverDetector.ts (heartbeat monitoring, standby promotion)
- [x] T090 [US5] Implement standby controller in controller/src/db/replication/standby.ts (WAL polling, take over within 5s)
- [x] T091 [US5] Create health monitoring dashboard in controller/src/api/routes/health.ts (system metrics, active tournaments)
- [x] T092 [US5] Implement tournament list API in controller/src/api/routes/tournaments.ts (GET /tournaments with filtering)
- [x] T093 [US5] Implement tournament CRUD API in controller/src/api/routes/tournaments.ts (POST, PUT, DELETE)
- [x] T094 [US5] Create tournament service in controller/src/services/tournament/TournamentService.ts (business logic for tournament management)
- [x] T095 [US5] Implement device-to-tournament binding in controller/src/services/tournament/DeviceBindingService.ts (track which device controls timer)
- [x] T096 [US5] Implement load shedding in controller/src/services/tournament/LoadShedding.ts (handle 100 sync requests/sec)
- [x] T097 [US5] Create failover test script in scripts/test/failover-test.ts (simulate primary failure, verify standby takeover)

**Checkpoint**: At this point, all P1 user stories are complete (Timer, Offline, Central Controller). The core tournament director functionality is fully operational.

---

## Phase 6: User Story 3 - Remote Viewing for Players (Priority: P2)

**Goal**: Provide real-time access to tournament status (clock, blind levels, remaining players, payouts) via any web browser without requiring app installation.

**Independent Test**: Open web browser on different device, navigate to tournament URL, observe real-time updates as director makes changes in mobile app.

### Implementation for User Story 3

- [X] T098 [P] [US3] Create public tournament view endpoint in controller/src/api/routes/tournaments.ts (GET /tournaments/:id/public, no auth required)
- [X] T099 [P] [US3] Implement server-sent events endpoint in controller/src/api/routes/stream.ts (GET /tournaments/:id/stream for real-time updates)
- [X] T100 [US3] Create web-based remote view UI in controller/public/remote-view.html (HTML/CSS/JS for timer display)
- [X] T101 [US3] Implement WebSocket subscription for tournament updates in controller/src/websocket/server.ts (public rooms for read-only access)
- [X] T102 [US3] Create timer display component for web in controller/public/remote-view.html (shows tenths of seconds, blinds, player count)
- [X] T103 [US3] Implement leaderboard display in controller/public/remote-view.html (remaining players, payouts)
- [X] T104 [US3] Implement graceful degradation in controller/src/websocket/broadcaster.ts (throttle updates on poor network, bulk sends every 1s)
- [X] T105 [US3] Create responsive design for remote view in controller/public/css/remote-view.css (mobile browser support)
- [X] T106 [US3] Implement viewer count tracking in controller/src/services/tournament/ViewerTracker.ts (100 concurrent viewers per tournament)
- [X] T107 [US3] Create remote view test in scripts/test/remote-viewing-test.ts (100 concurrent viewers, <2s updates)

**Checkpoint**: Remote viewing adds player-facing features without requiring app installation. Independent from mobile app functionality.

---

## Phase 7: User Story 4 - Cross-Platform Mobile Apps (Priority: P2)

**Goal**: Ensure native mobile apps on both iOS and Android provide full functionality with consistent user experience across platforms.

**Independent Test**: Install app on iPhone and Android device, run complete tournament workflow on each, verify identical functionality and behavior.

### Implementation for User Story 4

- [X] T108 [P] [US4] Verify Expo managed workflow compatibility in mobile-app/app.json (no custom native code required)
- [X] T109 [P] [US4] Create responsive layout components in mobile-app/src/components/Layout/ (adapt to phone vs tablet)
- [X] T110 [US4] Implement platform-specific UI patterns in mobile-app/src/components/ (Platform.OS === 'ios' vs 'android')
- [X] T111 [US4] Configure push notifications with Expo Notifications in mobile-app/app.json and mobile-app/src/services/notifications/
- [X] T112 [US4] Implement notification scheduler in mobile-app/src/services/notifications/NotificationScheduler.ts (break end alerts, level changes)
- [X] T113 [US4] Create EAS Build configuration in mobile-app/eas.json (iOS and Android build profiles)
- [X] T114 [US4] Implement screen orientation handling in mobile-app/app.json (portrait/landscape as needed)
- [X] T115 [US4] Create platform-specific styling in mobile-app/src/styles/ (Material Design for Android, iOS design for iOS)
- [X] T116 [US4] Verify feature parity on both platforms in mobile-app/ (test all screens, verify identical behavior)
- [X] T117 [US4] Create cross-platform E2E test in mobile-app/__tests__/e2e/cross-platform.test.ts (Detox test for both iOS and Android)

**Checkpoint**: Mobile apps provide identical functionality on iOS and Android with platform-appropriate UI patterns.

---

## Phase 8: User Story 6 - Data Export & Migration (Priority: P3)

**Goal**: Enable exporting tournament data in standard formats for external systems, with migration path from old WordPress plugin.

**Independent Test**: Run complete tournament, export data, import into fresh instance; verify all data transfers correctly.

### Implementation for User Story 6

- [X] T118 [P] [US6] Implement JSON export service in controller/src/services/export/JsonExportService.ts (include all tournament data, timer events)
- [X] T119 [P] [US6] Implement JSON import service in controller/src/services/import/JsonImportService.ts (validate, load data, report errors)
- [X] T120 [US6] Create export API endpoint in controller/src/api/routes/export.ts (GET /tournaments/:id/export)
- [X] T121 [US6] Create import API endpoint in controller/src/api/routes/import.ts (POST /import)
- [X] T122 [US6] Implement WordPress plugin migration tool in scripts/migrate/migrate-wordpress.ts (read WordPress DB, convert to new format)
- [X] T123 [US6] Create export UI in mobile-app/src/screens/ExportScreen.tsx (select tournament, export to file/share)
- [X] T124 [US6] Implement data validation in controller/src/services/import/JsonImportService.ts (check integrity, report warnings)
- [X] T125 [US6] Create migration progress indicator in scripts/migrate/migrate-wordpress.ts (show progress for large datasets)
- [X] T126 [US6] Implement snapshot export (in-progress tournament) in controller/src/services/export/JsonExportService.ts (include current timer state)
- [X] T127 [US6] Create migration test in scripts/test/migration-test.ts (verify zero data loss, all records transferred)

**Checkpoint**: Data portability and migration tools complete. WordPress plugin users can migrate to new system.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T128 [P] Add comprehensive logging to controller in controller/src/utils/logger.ts (structured logging, log levels)
- [X] T129 [P] Implement performance monitoring in controller/src/utils/metrics.ts (API response times, timer precision metrics)
- [X] T130 [P] Add analytics for sync operations in mobile-app/src/services/sync/analytics.ts (track sync success rates, conflict counts)
- [X] T131 [P] Create API documentation in controller/docs/api.md (endpoint documentation, examples)
- [X] T132 [P] Implement error boundaries in mobile-app/src/components/ErrorBoundary.tsx (graceful error handling)
- [X] T133 [P] Add loading states to all async operations in mobile-app/src/components/LoadingIndicator.tsx
- [X] T134 [P] Create empty state components in mobile-app/src/components/EmptyState.tsx (no tournaments, no players, etc.)
- [X] T135 [P] Implement accessibility features in mobile-app/src/ (screen reader support, minimum touch targets 44px)
- [X] T136 [P] Add internationalization support in mobile-app/assets/i18n/ (English initial, extensible)
- [X] T137 Run full timer precision stress test in scripts/test/timer-stress-test.ts (8+ hours, verify <1s drift)
- [X] T138 Run offline mode validation test in scripts/test/offline-validation.ts (4+ hours offline, verify sync)
- [X] T139 Validate all constitution compliance in scripts/test/constitution-check.ts (TypeScript strict, Expo compliance, precision tests)
- [X] T140 Update README.md in repository root with new architecture overview and quick start instructions

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-8)**:
  - US1 (Timer), US2 (Offline), US5 (Controller) are all P1 and should be implemented first
  - US1 and US5 are tightly coupled (mobile timer + controller timer)
  - US2 depends on US1 (timer functionality needed for tournaments)
  - US3 (Remote Viewing) can proceed in parallel after US5 (controller infrastructure)
  - US4 (Cross-Platform) is validation/testing phase, depends on US1-US3
  - US6 (Migration) is independent, can be done anytime after Foundational
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
    ┌─────┴─────┬────────┐      │ US5: Controller     │
    │           │        │      │ (P1 - Server Timer) │
    v           v        v      └─────────────────────┘
┌──────┐   ┌──────┐  ┌─────┐              │
│ US1  │   │ US2  │  │ US6 │              │
│ Timer│   │Offline│ │Migr.│              │
│ (P1) │   │ (P1)  │ │ (P3)│              │
└──┬───┘   └───┬──┘  └───┬─┘              │
   │           │        │                  │
   └─────┬─────┴────────┘                  │
         │                                 │
         v                                 │
    ┌─────────┐                            │
    │   US3   │                            │
    │ Remote │                            │
    │ (P2)   │                            │
    └────┬────┘                            │
         │                                 │
         v                                 │
    ┌─────────┐                            │
    │   US4   │                            │
    │  Cross │                            │
    │Platform│                            │
    │ (P2)   │                            │
    └────┬────┘                            │
         │                                 │
         v                                 v
    ┌────────────────────────────────────────┐
    │      Phase 9: Polish                  │
    └────────────────────────────────────────┘
```

### Parallel Opportunities

**Setup Phase (Phase 1)**:
- T002, T003, T004 can run in parallel (separate package.json initializations)
- T005, T006 can run in parallel (independent tooling setup)
- T007, T008 can run in parallel (env config, path aliases)

**Foundational Phase (Phase 2)**:
- All type definitions (T010-T014) can run in parallel (separate files)
- All repositories (T020-T024) can run in parallel after base classes
- All middleware (T032-T034) can run in parallel
- All mobile stores (T046-T048) can run in parallel

**User Story Phases**:
- Within each user story, tasks marked [P] can run in parallel
- Example US1: T054, T055, T060 preparation tasks can run in parallel
- Example US2: T071, T072, T089 detection services can run in parallel

**Cross-Story Parallelization** (after Foundational complete):
- US3 (Remote Viewing) can be developed parallel to US4 (Cross-Platform validation)
- US6 (Migration) can be developed anytime independently
- US1/US2/US5 (P1 stories) should be completed first for MVP, but could have team members working on different stories in parallel if staffed

---

## Implementation Strategy

### MVP First (User Stories 1, 2, 5 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (Precision Timer)
4. Complete Phase 4: User Story 2 (Offline Management)
5. Complete Phase 5: User Story 5 (Central Controller)
6. **STOP and VALIDATE**: Test core tournament director functionality (timer + offline + controller) in real-world scenario
7. Deploy/demo MVP with P1 features only

**MVP Scope**: Tournament directors can run tournaments with precise timing, offline capability, and reliable central controller. This delivers the core value proposition.

### Incremental Delivery (Add P2 Features)

1. After MVP is stable, add Phase 6: User Story 3 (Remote Viewing) → Demo
2. Then add Phase 7: User Story 4 (Cross-Platform validation) → Deploy P2 release
3. Finally add Phase 8: User Story 6 (Migration tools) → Deploy full feature set

### Parallel Team Strategy

With 3 developers after Foundational phase:

1. **Developer A**: US1 (Timer) + US5 (Controller) - tightly coupled, best done together
2. **Developer B**: US2 (Offline) - depends on timer, but largely independent
3. **Developer C**: US3 (Remote Viewing) - can start in parallel after US5 begins

With 5 developers after Foundational phase:
1. **Dev A**: US1 (Timer) - mobile app focus
2. **Dev B**: US5 (Controller) - server-side timer
3. **Dev C**: US2 (Offline) - sync infrastructure
4. **Dev D**: US3 (Remote Viewing) - web UI + WebSocket
5. **Dev E**: US4 (Cross-Platform) - platform-specific testing + US6 (Migration)

---

## Task Summary

**Total Tasks**: 143 tasks
**Tasks by Phase**:
- Phase 1 (Setup): 10 tasks
- Phase 2 (Foundational): 47 tasks
- Phase 3 (US1 - Timer): 18 tasks
- Phase 4 (US2 - Offline): 16 tasks
- Phase 5 (US5 - Controller): 12 tasks
- Phase 6 (US3 - Remote Viewing): 10 tasks
- Phase 7 (US4 - Cross-Platform): 10 tasks
- Phase 8 (US6 - Migration): 10 tasks
- Phase 9 (Polish): 13 tasks

**Tasks by User Story**:
- US1 (Precision Timer): 18 tasks
- US2 (Offline Management): 16 tasks
- US3 (Remote Viewing): 10 tasks
- US4 (Cross-Platform): 10 tasks
- US5 (Central Controller): 12 tasks
- US6 (Migration): 10 tasks

**Parallel Opportunities**: 60+ tasks marked [P] can run in parallel within their phases, enabling efficient team utilization

**Independent Tests**: Each user story has clear independent test criteria, enabling incremental delivery and validation

**MVP Path**: Phases 1-5 deliver complete tournament director functionality (timer + offline + controller). Phases 6-8 add player-facing features and platform validation. Phase 9 polishes the complete system.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Stop at any checkpoint to validate story independently
- Commit after each task or logical group
- Tasks follow strict checklist format: `- [ ] [ID] [P?] [Story?] Description with file path`
