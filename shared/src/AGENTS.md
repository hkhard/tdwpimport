# PROJECT KNOWLEDGE BASE

**Generated:** 2026-01-16
**Path:** shared/src/
**Scope:** Shared TypeScript library for mobile-app and controller

## OVERVIEW
Shared TypeScript types, Zod validation schemas, and service interfaces for mobile-app and controller.

## STRUCTURE
```
shared/src/
├── types/          # Core type definitions (tournament, player, timer, sync, api)
├── schemas/        # Zod 3.25.76 runtime validation schemas
├── services/       # Service interfaces and abstract classes (Timer, Sync, Conflict, Time)
└── utils/          # Utility classes (TimeSync)
```

## WHERE TO LOOK
| Component | Location | Purpose |
|-----------|----------|---------|
| Type exports | `types/index.ts` | Re-exports all types for @shared/* imports |
| Zod schemas | `schemas/index.ts` | Runtime validation (tournamentSchema, playerSchema, timerEventSchema, syncUploadSchema) |
| Timer service | `services/TimerService.ts` | Interface for timer operations (start, pause, nextLevel, subscribe) |
| Sync orchestrator | `services/sync/SyncOrchestrator.ts` | Abstract base class for bidirectional sync with offline queue |
| Conflict resolver | `services/sync/ConflictResolver.ts` | Conflict detection/resolution (last-write-wins, client-wins, server-wins, merge) |
| Time sync | `utils/timeSync.ts` | NTP-style server time sync with drift correction |

## CONVENTIONS

### Type Definitions
- **No namespaces**: Direct type exports from `types/index.ts`
- **Cross-platform**: Used by mobile-app (Expo RN) and controller (Node.js/Fastify)
- **Path alias**: `@shared/*` imports from both projects point to `shared/src/*`

### Zod Schemas
- **Runtime validation**: All API payloads use Zod schemas
- **Coercion**: `z.coerce.date()` for date fields, `z.enum()` for status/event types
- **Version locked**: Zod 3.25.76 across all projects

### Service Patterns
- **Interfaces over implementations**: `TimerService` as interface for contract
- **Abstract base classes**: `SyncOrchestrator` extends with protected abstract methods
- **Platform-specific implementation**: Mobile/controller extend abstract classes

### Build Process
- **Output**: `dist/` directory with declaration files (`.d.ts`)
- **Compilation**: `tsc` generates `dist/index.js` and `dist/index.d.ts`
- **No bundling**: Individual TS files compiled, not bundled

## ANTI-PATTERNS

### CRITICAL DO NOT:
- **NEVER use** `.d.ts` files directly - duplicate `player.ts`/`player.d.ts`, `timer.ts`/`timer.d.ts`, `sync.ts`/`sync.d.ts` exist but USE `.ts` ONLY
- **NEVER bypass** Zod validation for API payloads - all data must pass schema validation
- **NEVER use** `any` types in type definitions - strict typing required
- **NEVER modify** schemas without updating corresponding TypeScript types

### CRITICAL ALWAYS:
- **ALWAYS use** `@shared/*` path alias from mobile-app and controller (not relative paths)
- **ALWAYS validate** runtime data with Zod schemas before processing
- **ALWAYS extend** `SyncOrchestrator` abstract class for sync implementations
- **ALWAYS implement** `TimerService` interface for platform-specific timer logic
- **ALWAYS run** `npm run build` after type changes to regenerate declarations

### DEPRECATED:
- **Duplicate .d.ts files**: `player.d.ts`, `timer.d.ts`, `sync.d.ts` alongside `.ts` - use `.ts` source files only

### NEVER VIOLATE:
- **Type synchronization**: Zod schemas must match TypeScript types (compile-time + runtime consistency)
- **Cross-platform compatibility**: Types work in both browser (React Native) and Node.js environments
- **Path alias resolution**: `@shared/types/tournament` not `../../shared/src/types/tournament`
