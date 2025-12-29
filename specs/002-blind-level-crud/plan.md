# Implementation Plan: Blind Level Scheme Management Screen

**Branch**: `002-blind-level-crud` | **Date**: 2025-12-28 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-blind-level-crud/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Build a mobile settings screen for tournament directors to manage blind level schemes with full CRUD operations. The screen integrates into existing Settings navigation, reuses blind schedule API from feature 001, and provides inline editing with validation. Technical approach extends existing mobile app with React Native screens and adds management endpoints to controller API.

## Technical Context

**Language/Version**: TypeScript 5.0+ (strict mode)
**Primary Dependencies**: Expo SDK 54, React Navigation v6, Zustand 4.4, Fastify (controller)
**Storage**: AsyncStorage (offline cache), SQLite (controller database with tdwp_ prefix)
**Testing**: Jest + React Native Testing Library
**Target Platform**: iOS 13+, Android 8+ (Expo managed workflow)
**Project Type**: mobile+api (Expo app + Fastify controller)
**Performance Goals**: <500ms API p95 for CRUD operations, <1s list load time, 60 FPS animations
**Constraints**: <50MB mobile bundle, offline-capable CRUD with sync on reconnect
**Scale/Scope**: Support 100+ blind schemes, 10-20 levels per scheme, 3-10 schemes per user

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Mobile App Features

- [x] **Mobile-First**: UI designed for smallest screen (Settings integration), responsive scaling for tablets
- [ ] **Precision Timing**: N/A - no timer implementation in this feature
- [x] **Offline Capability**: CRUD operations cached in AsyncStorage, sync when connection restored
- [x] **TypeScript Strict**: Existing mobile app uses strict mode, all new code will follow suit
- [x] **Expo Compliance**: Using existing Expo SDK 54 managed workflow, no custom native code needed
- [x] **Real-Time Sync**: N/A - management screens don't require WebSocket (no live tournament updates)

### WordPress Plugin Features

- [x] **CMS Integration**: N/A - controller API is standalone (not WordPress plugin)
- [x] **Security**: API authentication via existing auth middleware, input validation on all endpoints
- [x] **Performance**: API p95 <500ms target (within constitution requirements)
- [x] **Database**: Reuses existing tdwp_ prefix database schema from feature 001

### Quality Gates

- [x] **Pre-Commit**: TypeScript strict compilation enforced, ESLint/Prettier configured
- [x] **Pre-Merge**: Unit tests for validation logic, integration tests for API endpoints
- [x] **Pre-Release**: Manual testing on physical device, offline mode validation

**Status**: PASS - All constitution requirements met or N/A for this feature

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
mobile-app/                    # Existing Expo React Native app
├── src/
│   ├── components/           # Reusable UI components
│   │   ├── BlindLevelDisplay.tsx
│   │   ├── BlindLevelsList.tsx
│   │   └── [NEW] BlindSchemeListItem.tsx
│   ├── screens/              # Screen-level components
│   │   ├── SettingsScreen.tsx          # Existing - will be enhanced
│   │   ├── [NEW] BlindSchemeListScreen.tsx
│   │   ├── [NEW] BlindSchemeEditorScreen.tsx
│   │   └── BlindScheduleEditorScreen.tsx  # Existing reference
│   ├── navigation/           # React Navigation config
│   │   └── AppNavigator.tsx   # Will add Settings sub-navigation
│   ├── services/             # API clients, networking
│   │   └── api/
│   │       └── blindScheduleApi.ts  # Existing - will extend
│   ├── stores/               # State management (Zustand)
│   │   └── [NEW] blindSchemeStore.ts
│   ├── types/                # TypeScript type definitions
│   │   └── timer.ts          # Existing BlindLevel types
│   └── utils/                # Helper functions
│       └── [NEW] validation.ts
├── assets/
├── __tests__/                # Jest tests
├── app.json                  # Expo config
├── tsconfig.json             # TypeScript strict mode config
└── package.json

controller/                    # Existing Fastify controller
├── src/
│   ├── api/
│   │   └── routes/
│   │       └── blindSchedules.ts  # Existing - will extend with management endpoints
│   ├── services/
│   │   └── blindSchedule/
│   │       ├── BlindScheduleService.ts  # Existing - will add management methods
│   │       └── [NEW] BlindSchemeValidationService.ts
│   └── db/
│       └── repositories/
│           ├── BlindScheduleRepository.ts  # Existing
│           └── BlindLevelRepository.ts      # Existing
└── [existing structure]

shared/                       # Shared TypeScript types
└── src/
    └── types/
        └── timer.ts          # Existing - will extend with management types
```

**Structure Decision**: Mobile + API (Option 3)
- Reuses existing mobile-app Expo structure
- Reuses existing controller Fastify structure
- New screens in mobile-app/src/screens/ for blind scheme management UI
- Extended routes in controller/src/api/routes/blindSchedules.ts for management API
- Extended existing services/repositories instead of creating new ones

## Complexity Tracking

> **No constitution violations - this section not applicable**

All requirements align with existing principles. Feature reuses established patterns from feature 001-blind-level-management.

## Phase 0: Research (COMPLETE)

**Status**: ✅ Complete
**Output**: `research.md`

**Research Completed**:
1. ✅ Settings Screen Navigation Pattern - Stack Navigator with Settings tab
2. ✅ Offline-First CRUD Strategy - AsyncStorage cache with sync queue
3. ✅ List Performance for 100+ Schemes - FlatList with windowing
4. ✅ Inline Editing UX Pattern - Tap-to-edit rows
5. ✅ Validation Strategy - Zod schema validation
6. ✅ Default Scheme Protection - DB flag + API enforcement + UI disabled
7. ✅ Real-Time Preview During Editing - Computed from state

**Technology Decisions**:
- Added **Zod** for runtime validation with TypeScript inference
- Reused existing Expo SDK 54, React Navigation v6, Zustand 4.4
- Extended existing Fastify controller routes
- No new native modules required (Expo managed workflow)

## Phase 1: Design (COMPLETE)

**Status**: ✅ Complete
**Output**: `data-model.md`, `contracts/`, `quickstart.md`

**Data Model** (`data-model.md`):
- Reused existing database schema from feature 001
- Defined TypeScript interfaces for all entities
- Documented validation rules (8 scheme-level, 6 level-level)
- Specified state transitions and data flow
- Calculated storage requirements (~170 KB total)

**API Contracts** (`contracts/blind-schemes-api.yaml`):
- OpenAPI 3.0.3 specification
- 5 endpoints: GET (list/details), POST (create), PUT (update), DELETE, POST (duplicate)
- Comprehensive request/response schemas
- Error handling documentation
- Authentication via bearer JWT

**Quickstart** (`quickstart.md`):
- Setup instructions for controller and mobile app
- 5 common workflows with step-by-step instructions
- API testing examples (curl commands)
- Troubleshooting guide for common issues
- Performance tips and development notes

**Agent Context Update**:
- ✅ CLAUDE.md updated with TypeScript 5.0+, Expo SDK 54, React Navigation v6, Zustand 4.4, Fastify

## Phase 2: Tasks (NOT STARTED)

**Next Step**: Run `/speckit.tasks` to generate implementation task list

**Expected Output**: `specs/002-blind-level-crud/tasks.md`

The tasks document will break down implementation into:
- Backend API endpoints (controller)
- Mobile app screens and components
- State management (Zustand store)
- Validation logic (Zod schemas)
- Offline sync (AsyncStorage queue)
- Testing (unit, integration, E2E)
