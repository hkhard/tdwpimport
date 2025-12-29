# Implementation Plan: Blind Level Management

**Branch**: `001-blind-level-management` | **Date**: 2025-12-28 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-blind-level-management/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Implement blind level management functionality for the mobile tournament director app, including: (1) a library of reusable blind schedules that can be selected during tournament setup, (2) prominent display of current blind levels on the tournament detail screen, (3) manual level controls for advancing/rewinding levels, (4) CRUD operations for creating and managing custom blind schedules, and (5) pre-loaded default blind schedules for new users.

## Technical Context

**Language/Version**: TypeScript 5.0+ (strict mode)
**Primary Dependencies**: Expo SDK 50+, React Navigation v6+, Zustand (state), React Native (UI)
**Storage**: SQLite (controller with tdwp_ prefix), Expo SQLite (mobile), AsyncStorage (offline cache)
**Testing**: Jest + React Native Testing Library
**Target Platform**: iOS 13+, Android 8+
**Project Type**: mobile+api (Expo mobile app + Node.js controller)
**Performance Goals**: <2s blind info load, <1s level change display, offline-capable viewing
**Constraints**: <50MB mobile bundle, core functions work offline, max 50 levels per schedule
**Scale/Scope**: 100 blind schedules per user, 50 levels per schedule, default 3 pre-loaded schedules

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Mobile App Features

- [x] **Mobile-First**: UI designed for smallest screen, responsive scaling validated
- [ ] **Precision Timing**: Timer implementations include 1/10s precision tests (N/A for this feature - uses existing timer)
- [x] **Offline Capability**: Core timer/control functions work without network (blind schedule caching)
- [x] **TypeScript Strict**: `strict: true`, no `any` types, interfaces defined
- [x] **Expo Compliance**: Managed workflow preferred; custom native code justified if used
- [ ] **Real-Time Sync**: WebSocket/streaming for remote viewing with reconnection logic (N/A - uses existing WebSocket)

### WordPress Plugin Features

*NOT APPLICABLE* - This feature extends the mobile app and controller. WordPress integration is via existing API layer.

### Quality Gates

- [x] **Pre-Commit**: TypeScript compiles, ESLint passes, Prettier applied
- [x] **Pre-Merge**: Tests for new functionality, physical device test required
- [x] **Pre-Release**: Full test suite, 8hr timer stress test, offline mode validated

**Status**: ✅ ALL GATES PASSED - Proceed to Phase 0 research

## Project Structure

### Documentation (this feature)

```text
specs/001-blind-level-management/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
mobile-app/                    # Expo React Native app (iOS/Android)
├── src/
│   ├── screens/
│   │   ├── TournamentDetail.tsx       # Updated with blind display + level controls
│   │   ├── BlindScheduleList.tsx     # NEW: Library of blind schedules
│   │   ├── BlindScheduleEditor.tsx   # NEW: Create/edit blind schedules
│   │   └── TournamentSetup.tsx       # Updated with blind schedule selector
│   ├── components/
│   │   ├── BlindLevelDisplay.tsx     # NEW: Current level prominent display
│   │   ├── BlindLevelsList.tsx       # NEW: Upcoming levels list
│   │   └── BlindScheduleSelector.tsx # NEW: Dropdown for schedule selection
│   ├── services/
│   │   └── api/
│   │       └── blindScheduleApi.ts   # NEW: API client for blind schedules
│   ├── stores/
│   │   └── blindScheduleStore.ts     # NEW: Zustand store for blind schedules
│   └── types/
│       └── blindSchedule.ts          # NEW: Type definitions (extends shared)

controller/                    # Central web controller (Node.js/TypeScript)
├── src/
│   ├── db/
│   │   └── repositories/
│   │       └── BlindScheduleRepository.ts  # NEW: Blind schedule CRUD
│   ├── services/
│   │   └── blindSchedule/
│   │       ├── BlindScheduleService.ts    # NEW: Business logic
│   │       └── DefaultSchedulesLoader.ts  # NEW: Seed default schedules
│   └── api/
│       └── routes/
│           └── blindSchedules.ts          # NEW: REST API endpoints

shared/                        # Shared TypeScript code
├── src/
│   └── types/
│       └── timer.ts          # EXISTING: BlindLevel, BlindSchedule types
```

**Structure Decision**: This is a **mobile+api** feature that extends the existing cross-platform architecture. The mobile app gets new screens/components for blind management, the controller gets API endpoints and business logic, and shared types already exist from previous work.

## Complexity Tracking

> No constitution violations. This feature aligns with all core principles:
> - **Mobile-First**: New screens designed for smallest screen first
> - **TypeScript Discipline**: All new code in strict mode
> - **Expo Compliance**: Uses existing Expo managed workflow
> - **Offline Capability**: Blind schedules cached locally for offline viewing

---

# Phase 0: Research & Technology Decisions

This section documents research findings and technology decisions.

**Research Topics**:
1. Blind schedule data structure (how to model levels, breaks, durations)
2. Offline caching strategy for blind schedules
3. UI patterns for blind level display on mobile
4. Default blind schedule templates (Turbo, Standard, Deep Stack)

**Detailed Research**: See [research.md](./research.md) for comprehensive analysis and decisions.

---

# Phase 1: Design Artifacts

## Data Model

**Detailed Data Model**: See [data-model.md](./data-model.md) for entity definitions, relationships, and validation rules.

## API Contracts

**API Contracts**: See [contracts/](./contracts/) directory for OpenAPI schemas.

## Quick Start Guide

**Quick Start**: See [quickstart.md](./quickstart.md) for development setup and local testing instructions.
