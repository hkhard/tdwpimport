# Implementation Plan: Cross-Platform Tournament Director Platform

**Branch**: `002-expo-rewrite` | **Date**: 2025-12-26 | **Status**: 📋 **PLANNING COMPLETE**
**Spec**: [spec.md](./spec.md)

## Planning Status

**Phase 0 Research**: ✅ COMPLETE
**Phase 1 Design**: ✅ COMPLETE
**Phase 2 Tasks**: NOT STARTED
**Implementation**: NOT STARTED

### Planning Artifacts Completed

1. **Research Report** (`research.md`) - Technology decisions for WebSocket, database, state management
2. **Data Model** (`data-model.md`) - Complete data structures for mobile + controller
3. **API Contracts** (`contracts/`) - OpenAPI specifications for all endpoints
4. **Quick Start Guide** (`quickstart.md`) - Development setup and workflows
5. **Glossary** (`glossary.md`) - Terminology conventions
6. **Agent Context Update** - AI agent context updated with Expo/TypeScript stack

### Ready for Implementation

All technical decisions made, architecture designed, contracts specified. Ready for `/speckit.tasks` to generate implementation tasks.

**Note**: This is a major architectural initiative (complete platform rewrite). Current status is planning complete, awaiting decision to proceed with implementation.

---

## Summary (Original)

## Summary

Complete rewrite of the WordPress-based poker tournament management system into a modern cross-platform architecture. The new system comprises three components: (1) Expo-based mobile apps for iOS/Android providing tournament director functionality, (2) a web-based central controller for authoritative timekeeping and data coordination, and (3) a future API integration layer for external systems.

Key requirements include 1/10th second timer precision, offline-first architecture for core functions, real-time remote viewing for players, and automatic failover for high reliability. The system removes WordPress dependencies from the core, positioning CMS integration as a future concern.

## Technical Context

**Language/Version**: TypeScript 5.0+ (strict mode), Node.js 20+ for controller
**Primary Dependencies**: Expo SDK 50+, React Navigation v6+, WebSocket library (NEEDS CLARIFICATION: ws vs Socket.io vs SSE)
**Storage**: Lightweight embedded database with replication (NEEDS CLARIFICATION: SQLite vs RxDB vs Dexie.js vs PouchDB), AsyncStorage for offline cache
**Testing**: Jest + React Native Testing Library, Detox for E2E, timer precision stress tests
**Target Platform**: iOS 13+, Android 8+, modern web browsers (Chrome, Firefox, Safari, Edge)
**Project Type**: mobile+api (Expo mobile apps + Node.js central controller)
**Performance Goals**: <3s cold launch, 60 FPS timer, 1/10s precision, <100ms API p95, 50 concurrent tournaments
**Constraints**: <50MB mobile bundle, offline-capable core functions, <200ms sync latency, 99.5% uptime
**Scale/Scope**: 100 concurrent tournaments, 1000 connected devices, 100 concurrent remote viewers per tournament

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Mobile App Features

- [x] **Mobile-First**: UI designed for smallest screen, responsive scaling validated
- [x] **Precision Timing**: Timer implementations include 1/10s precision tests
- [x] **Offline Capability**: Core timer/control functions work without network
- [x] **TypeScript Strict**: `strict: true`, no `any` types, interfaces defined
- [x] **Expo Compliance**: Managed workflow preferred; custom native code justified if used
- [x] **Real-Time Sync**: WebSocket/streaming for remote viewing with reconnection logic

### WordPress Plugin Features

*NOT APPLICABLE* - This rewrite removes WordPress dependencies from core functionality. WordPress integration will be added in future phase via API integration layer.

### Quality Gates

- [x] **Pre-Commit**: TypeScript compiles, ESLint passes, Prettier applied
- [x] **Pre-Merge**: Tests for new functionality, physical device test required
- [x] **Pre-Release**: Full test suite, 8hr timer stress test, offline mode validated

### Post-Design Validation (Phase 1 Complete)

All constitution requirements satisfied by technology decisions:

1. **Mobile-First**: Expo SDK 50+ with React Navigation, responsive layout design
2. **Precision Timing**: Hybrid local + server time approach ensures 100ms precision (FR-001)
3. **Offline Capability**: SQLite local database with async sync queue (FR-009, FR-010)
4. **TypeScript Strict**: Strict mode enforced across mobile, controller, shared code
5. **Expo Compliance**: All Expo-managed packages, no custom native code required
6. **Real-Time Sync**: Native WebSocket with exponential backoff reconnection logic

**Status**: ✅ ALL GATES PASSED - Proceed to implementation

## Project Structure

### Documentation (this feature)

```text
specs/002-expo-rewrite/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
├── glossary.md          # Phase 2 output (/speckit.tasks command - terminology conventions)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
mobile-app/                    # Expo React Native app (iOS/Android)
├── src/
│   ├── components/           # Reusable UI components
│   │   ├── TimerDisplay.tsx  # 1/10s precision clock
│   │   ├── PlayerList.tsx    # Player management
│   │   ├── BlindLevel.tsx    # Current blinds display
│   │   └── ...
│   ├── screens/              # Screen-level components
│   │   ├── TournamentList.tsx
│   │   ├── TimerScreen.tsx   # Main timer control
│   │   ├── PlayerManager.tsx
│   │   ├── SettingsScreen.tsx
│   │   └── ...
│   ├── navigation/           # React Navigation config
│   │   ├── AppNavigator.tsx
│   │   └── TabNavigator.tsx
│   ├── services/             # API clients, networking
│   │   ├── apiClient.ts      # HTTP/WebSocket client
│   │   ├── syncService.ts    # Offline sync orchestration
│   │   └── timerService.ts   # Local timer logic
│   ├── hooks/                # Custom React hooks
│   │   ├── useTimer.ts       # Timer state management
│   │   ├── useOfflineSync.ts # Offline sync hook
│   │   └── useTournament.ts  # Tournament data hook
│   ├── store/                # State management (Zustand/Redux)
│   │   ├── tournamentStore.ts
│   │   ├── timerStore.ts
│   │   └── syncStore.ts
│   ├── db/                   # Local database (SQLite/RxDB)
│   │   ├── schema.ts         # Database schema
│   │   ├── migrations.ts     # Database migrations
│   │   └── repositories.ts    # Data access layer
│   ├── types/                # TypeScript type definitions
│   │   ├── tournament.ts
│   │   ├── player.ts
│   │   └── timer.ts
│   ├── utils/                # Helper functions
│   │   ├── timeSync.ts       # Clock synchronization
│   │   └── conflictResolver.ts
│   └── constants/            # App constants
│       ├── config.ts         # App configuration
│       └── syncConfig.ts     # Sync settings
├── assets/
│   ├── images/
│   ├── fonts/
│   └── i18n/                 # Internationalization
├── __tests__/                # Jest tests
│   ├── unit/
│   │   ├── timer.test.ts
│   │   ├── sync.test.ts
│   │   └── ...
│   ├── integration/
│   │   └── ...
│   └── e2e/                  # Detox E2E tests
│       └── ...
├── app.json                  # Expo config
├── tsconfig.json             # TypeScript strict mode config
├── package.json
├── eas.json                  # EAS Build configuration
└── App.tsx                   # Expo entry point

controller/                    # Central web controller (Node.js/TypeScript)
├── src/
│   ├── server.ts             # Express/Fastify server
│   ├── api/                  # REST/GraphQL API
│   │   ├── routes/
│   │   │   ├── tournaments.ts
│   │   │   ├── players.ts
│   │   │   └── sync.ts
│   │   └── middleware/
│   │       ├── auth.ts       # JWT/OAuth middleware
│   │       └── validation.ts
│   ├── services/             # Business logic
│   │   ├── timerService.ts   # Authoritative timer
│   │   ├── syncService.ts    # Sync orchestration
│   │   └── tournamentService.ts
│   ├── websocket/            # Real-time communication
│   │   ├── handler.ts        # WebSocket server
│   │   └── broadcaster.ts    # Event broadcasting
│   ├── db/                   # Embedded database
│   │   ├── connection.ts     # Database connection
│   │   ├── models/           # Data models
│   │   ├── repositories/     # Data access
│   │   └── replication/      # Replication logic
│   │       ├── primary.ts
│   │       └── standby.ts
│   ├── types/                # TypeScript types
│   └── utils/
│       └── health.ts         # Health monitoring
├── tests/
│   ├── unit/
│   ├── integration/
│   └── load/                 # Load testing
├── package.json
├── tsconfig.json
└── Dockerfile                # Container deployment

shared/                        # Shared TypeScript code (mobile + controller)
├── src/
│   ├── types/                # Shared type definitions
│   │   ├── tournament.ts
│   │   ├── player.ts
│   │   ├── sync.ts
│   │   └── api.ts
│   ├── schemas/              # Validation schemas
│   │   └── ...
│   └── constants/            # Shared constants
│       └── ...
└── package.json

scripts/                       # Build and deployment scripts
├── setup.sh                  # Initial project setup
├── build-mobile.sh           # Mobile app build
├── build-controller.sh       # Controller build
└── deploy.sh                 # Deployment automation
```

**Structure Decision**: This is a **mobile+api** project with three main components:
1. **mobile-app/**: Expo React Native app for iOS/Android tournament directors
2. **controller/**: Node.js/TypeScript central controller for timekeeping and data coordination
3. **shared/**: Common TypeScript types and constants shared between mobile and controller

The WordPress plugin remains in `wordpress-plugin/` but is **not modified** in this phase - it will be integrated later via the API layer.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No constitution violations. This rewrite aligns with all core principles:
- **Mobile-First**: Expo-managed workflow with offline-capable core
- **Precision Timing**: 1/10s precision with automated validation tests
- **TypeScript Discipline**: Strict mode across all codebases
- **Expo Compliance**: Managed workflow preferred; custom native code avoided
- **Real-Time Sync**: WebSocket-based remote viewing with reconnection logic

WordPress plugin integration is **explicitly out of scope** for this phase, per the "Out of Scope" section in the specification.

---

# Phase 0: Research & Technology Decisions

This section documents research findings and technology decisions that resolve all "NEEDS CLARIFICATION" items from Technical Context.

**Research Topics**:
1. WebSocket library selection (ws vs Socket.io vs SSE)
2. Lightweight embedded database with replication (SQLite vs RxDB vs Dexie.js vs PouchDB)
3. State management (Zustand vs Redux Toolkit)
4. Real-time sync strategy for offline-first architecture

**Detailed Research**: See [research.md](./research.md) for comprehensive analysis and decisions.

---

# Phase 1: Design Artifacts

## Data Model

**Detailed Data Model**: See [data-model.md](./data-model.md) for entity definitions, relationships, and validation rules.

## API Contracts

**API Contracts**: See [contracts/](./contracts/) directory for OpenAPI/GraphQL schemas.

## Quick Start Guide

**Quick Start**: See [quickstart.md](./quickstart.md) for development setup and local testing instructions.