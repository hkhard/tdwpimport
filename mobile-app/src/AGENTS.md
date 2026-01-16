# MOBILE APP (mobile-app/src/)

**Generated:** 2026-01-16
**Commit:** b3388f5
**Branch:** 014-shortcode-upload-fix

## OVERVIEW
Expo React Native app with offline-first tournament management using Zustand state management.

## STRUCTURE
```
src/
├── stores/         # Zustand stores (tournament, timer, blindSchedule, player, user, sync)
├── services/
│   ├── timer/      # LocalTimerEngine (100ms precision), TimeSyncService
│   ├── sync/       # OfflineQueue, SyncService, ReconnectionService, NetworkMonitor
│   └── api/        # REST client (tournamentApi, playerApi, blindScheduleApi)
├── screens/        # Tab screens (TournamentList, Timer, PlayerManager, CreateTournament)
├── components/     # PlatformCard, PlatformButton, TimerDisplay, BlindSchedule*
├── db/             # SQLite schema/migrations (tdwp_ prefix)
└── hooks/          # useTimer, useAuth, useDeviceContext
```

## WHERE TO LOOK
| Task | Location | Notes |
|------|----------|-------|
| State management | `src/stores/` | 6 Zustand stores |
| Local timer engine | `src/services/timer/LocalTimerEngine.ts` | 100ms precision |
| Offline sync | `src/services/sync/OfflineQueue.ts` | AsyncStorage, retry |
| Database | `src/db/schema.ts` | SQLite, tdwp_ prefix |
| Timer WebSocket | `src/services/websocket/timerWebSocket.ts` | Public route (no auth) |

## CONVENTIONS

### Zustand Stores
- Pattern: Interface `XxxStoreState` + actions, export `useXxxStore`
- Mutations: `set((state) => ({ ... }))` for array updates

### Offline Sync
- Queue key: `@tdp:offline_queue` in AsyncStorage
- Entity types: `tournament`, `player`, `tournament_player`, `timer_event`
- Max queue: 1000 items, removes oldest failed first
- Singleton: `getOfflineQueue()`

### Timer Engine
- Precision: 100ms intervals for tenths display
- Timing: `performance.now()` for monotonic timestamps
- Level progression: Automatic on `remainingTime <= 0`

### Database
- Prefix: `tdwp_` for all tables
- Tables: `tournaments`, `players`, `tournament_players`, `sync_queue`, `synced_data`
- Constraints: `ON DELETE CASCADE` on tournament_players

### WebSocket
- Timer updates: Public endpoint (no auth), per-tournament connection
- Fallback: Polling at 1s intervals

### Navigation
- Primary: Tab-based (in `App.tsx`, NOT `AppNavigator.tsx`)
- Screens: 10 total (TournamentList, Timer, PlayerManager, CreateTournament, etc.)

## ANTI-PATTERNS

### CRITICAL DO NOT:
- **DO NOT use** `setInterval` for timer precision - use `LocalTimerEngine` with `performance.now()`
- **NEVER modify** store state directly - use exported action functions
- **DO NOT skip** queue persistence - always `await persist()` after OfflineQueue mutations
- **NEVER use** `Date.now()` for monotonic timing - use `performance.now()`
- **DO NOT create** multiple `OfflineQueue` instances - use `getOfflineQueue()` singleton
- **ALWAYS subscribe** to WebSocket before calling `connect()` - TimerScreen.tsx:87-92
- **ALWAYS clean up** WebSocket connections in useEffect cleanup - TimerScreen.tsx:99-102
- **ALWAYS use** `@shared/types/*` for shared types, never duplicate definitions
- **Stack navigation**: `AppNavigator.tsx` exists but unused, app uses tab-based in `App.tsx`
- **Polling-only timer**: WebSocket primary, polling fallback only
- **NEVER VIOLATE**: 100ms timer intervals (Constitution II), offline-first design, Zustand functional updates
