# CONTROLLER KNOWLEDGE BASE

**Generated:** 2026-01-16
**Component:** @tdwp/tournament-controller (Node.js 20+)

## OVERVIEW
Node.js/Fastify backend API server with WebSocket support for real-time tournament updates

## STRUCTURE
```
controller/src/
├── api/routes/           # tournaments, timer, players, blindSchedules, export, health, import, sync
├── db/
│   ├── repositories/     # 8 repos: Base + Tournament, Player, TimerEvent, etc.
│   ├── connection.ts     # SQLite WAL, migration runner
│   ├── migrations/       # 001-007 SQL
│   └── replication/      # primary/standby
├── services/             # TournamentService, TournamentAllocator, BlindScheduleService, TimerService, SyncService, AuthService
├── middleware/           # auth, validation, errorHandler
├── routes/               # health, auth
└── websocket/            # server, types, broadcaster
```

## WHERE TO LOOK
| REST API | `api/routes/tournaments.ts` | CRUD + public/stream |
| WebSocket | `websocket/server.ts` | /ws (auth) + /ws/public |
| Repositories | `db/repositories/BaseRepository.ts` | Base pattern |
| Database | `db/connection.ts` | WAL, migrations |
| Tournament | `services/tournament/TournamentService.ts` | Core logic, allocator |
| Timer | `services/timerService.ts` | Timer, recovery |

## CONVENTIONS

### Fastify Server
- `server.decorate('serviceName', service)` pattern
- `onRequest` validation, custom error handler
- Route prefix `/api/*`

### Database (No ORM)
- Direct better-sqlite3, BaseRepository<T> (CRUD)
- `tdwp_` prefix, WAL mode, FK ON, 5000ms timeout
- SQL migrations 001-007, UP/DOWN format

### WebSocket
- `/ws` (JWT), `/ws/public? tournamentId` (no auth)
- Map<tournamentId, Set<Client>> grouping
- `broadcastToTournament()` to auth + public
- 30s heartbeat, 60s timeout
- **Level changes broadcast immediately (<1s)**

### SSE
- `/tournaments/:id/stream` - public, no auth
- Polls 1s, sends on change, 15s heartbeat

### Auth
- JWT (header or query param), bcrypt
- Demo mode bypasses auth
- Dates: strings → Date → ISO strings

## ANTI-PATTERNS

### DO NOT:
- Introduce ORM (repo pattern intentional)
- Throttle level changes (must send immediately)
- Broadcast to all (use `broadcastToTournament()`)

### ALWAYS:
- Use BaseRepository prepared statements
- Wrap mutations in transactions
- Send WebSocket broadcasts before HTTP response returns

### NEVER VIOLATE:
- WAL connection handling rules
- WebSocket client cleanup on close/error
- Standby mode read-only constraint
