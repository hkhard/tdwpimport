# Data Model

**Feature**: Cross-Platform Tournament Director Platform (002-expo-rewrite)
**Date**: 2025-12-26
**Database**: SQLite (controller) + Expo SQLite (mobile)

This document defines the data model for the tournament management system, including entities, relationships, validation rules, and state transitions.

---

## Entity Relationship Diagram

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Tournament │────<│TournamentPlayer│>────│    Player    │
└──────┬───────┘     └──────────────┘     └──────────────┘
       │
       │ has many
       v
┌──────────────┐
│  TimerEvent  │
└──────────────┘

┌──────────────┐     ┌──────────────┐
│  BlindLevel  │<────│BlindSchedule │
└──────────────┘     └──────────────┘

┌──────────────┐
│   SyncRecord │
└──────────────┘

┌──────────────┐
│     User     │
└──────────────┘
```

---

## Core Entities

### Tournament

Represents a poker tournament event with timer, blind structure, registered players, and results.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique tournament identifier |
| `name` | VARCHAR(255) | NOT NULL, MIN=1, MAX=255 | Tournament name |
| `description` | TEXT | NULLABLE | Optional description |
| `blindScheduleId` | UUID | FOREIGN KEY → BlindSchedule.id | Blind structure template |
| `status` | ENUM | NOT NULL, DEFAULT='upcoming' | upcoming, active, paused, completed |
| `startTime` | TIMESTAMP | NULLABLE | When tournament started |
| `endTime` | TIMESTAMP | NULLABLE | When tournament ended |
| `currentLevel` | INTEGER | NOT NULL, DEFAULT=1, MIN=1 | Current blind level number |
| `elapsedTime` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Elapsed time in milliseconds |
| `remainingTime` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Remaining time in current level (ms) |
| `prizePool` | DECIMAL(10,2) | NOT NULL, DEFAULT=0, MIN=0 | Total prize pool amount |
| `payoutStructure` | JSON | NOT NULL, DEFAULT={} | Payout distribution by finish position |
| `createdAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Creation timestamp |
| `updatedAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Last update timestamp |
| `createdBy` | UUID | FOREIGN KEY → User.id | User who created tournament |
| `deviceId` | UUID | NULLABLE | Device that currently controls timer |

**Indexes**:
- `idx_tournament_status`: (status) for filtering active tournaments
- `idx_tournament_createdBy`: (createdBy) for user's tournaments
- `idx_tournament_deviceId`: (deviceId) for device-active tournament lookup

**Validation Rules**:
- `endTime` must be > `startTime` if both set
- `currentLevel` must be ≤ number of levels in BlindSchedule
- Cannot transition from 'completed' back to other statuses

**State Transitions**:
```
upcoming → active → paused → active → completed
     ↓
   completed (only from active or paused)
```

---

### Player

Represents an individual participant who can play in multiple tournaments.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique player identifier |
| `name` | VARCHAR(255) | NOT NULL, MIN=1, MAX=255 | Player display name |
| `email` | VARCHAR(255) | UNIQUE, NULLABLE, EMAIL format | Contact email |
| `phone` | VARCHAR(50) | NULLABLE | Contact phone |
| `stats` | JSON | NOT NULL, DEFAULT={} | Aggregate statistics (tournamentsPlayed, wins, totalWinnings) |
| `createdAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Creation timestamp |
| `updatedAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Last update timestamp |

**Indexes**:
- `idx_player_email`: (email) UNIQUE for login lookup
- `idx_player_name`: (name) for search autocomplete

**Validation Rules**:
- `name` must contain at least one non-whitespace character
- `email` must be valid format if provided
- `stats.tournamentsPlayed` must be ≥ 0
- `stats.totalWinnings` must be ≥ 0

---

### TournamentPlayer

Represents a player's participation in a specific tournament (many-to-many relationship with tournament-specific data).

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique participation record |
| `tournamentId` | UUID | FOREIGN KEY → Tournament.id, NOT NULL | Tournament reference |
| `playerId` | UUID | FOREIGN KEY → Player.id, NOT NULL | Player reference |
| `startingStack` | INTEGER | NOT NULL, MIN=0 | Starting chip count |
| `currentStack` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Current chip count (0 if busted) |
| `finishPosition` | INTEGER | NULLABLE, MIN=1 | Final standing (NULL if active) |
| `winnings` | DECIMAL(10,2) | NOT NULL, DEFAULT=0, MIN=0 | Payout amount |
| `bustoutTime` | TIMESTAMP | NULLABLE | When player busted out |
| `eliminations` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Number of players eliminated (bounties) |
| `registeredAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Registration timestamp |
| `updatedAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Last update timestamp |

**Composite Index**:
- `unique_tournament_player`: (tournamentId, playerId) UNIQUE

**Indexes**:
- `idx_tournamentplayer_tournament`: (tournamentId) for player list queries
- `idx_tournamentplayer_finishPosition`: (tournamentId, finishPosition) for leaderboard queries

**Validation Rules**:
- `finishPosition` must be unique per tournament (no ties for same position)
- `winnings` calculated automatically based on `finishPosition` and tournament `payoutStructure`
- `currentStack` = 0 if `finishPosition` is set (busted players have no chips)

---

### BlindLevel

Defines a blind level in a tournament's blind structure.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique blind level identifier |
| `blindScheduleId` | UUID | FOREIGN KEY → BlindSchedule.id, NOT NULL | Parent schedule |
| `levelNumber` | INTEGER | NOT NULL, MIN=1 | Sequential level number (1, 2, 3...) |
| `smallBlind` | DECIMAL(10,2) | NOT NULL, MIN=0 | Small blind amount |
| `bigBlind` | DECIMAL(10,2) | NOT NULL, MIN=0 | Big blind amount |
| `ante` | DECIMAL(10,2) | NULLABLE, MIN=0 | Ante amount (NULL if no ante) |
| `durationMinutes` | INTEGER | NOT NULL, MIN=1 | Level duration in minutes |
| `isBreak` | BOOLEAN | NOT NULL, DEFAULT=FALSE | Whether this is a break level |
| `order` | INTEGER | NOT NULL | Display order within schedule |

**Composite Index**:
- `unique_schedule_level`: (blindScheduleId, levelNumber) UNIQUE

**Validation Rules**:
- `bigBlind` must be ≥ `smallBlind`
- If `isBreak` is TRUE, `smallBlind` and `bigBlind` must be 0

---

### BlindSchedule

Template of blind levels for a tournament (reusable across tournaments).

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique schedule identifier |
| `name` | VARCHAR(255) | NOT NULL, MIN=1, MAX=255 | Schedule display name |
| `description` | TEXT | NULLABLE | Optional description |
| `startingStack` | INTEGER | NOT NULL, DEFAULT=10000, MIN=0 | Default starting chips |
| `breakInterval` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Break every N levels (0 = no breaks) |
| `breakDuration` | INTEGER | NOT NULL, DEFAULT=10, MIN=0 | Break duration in minutes |
| `createdAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Creation timestamp |
| `updatedAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Last update timestamp |
| `createdBy` | UUID | FOREIGN KEY → User.id | Creator user |

**Indexes**:
- `idx_blindschedule_createdBy`: (createdBy) for user's templates

---

### TimerEvent

Audit log of timer state changes for reconciliation and dispute resolution.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique event identifier |
| `tournamentId` | UUID | FOREIGN KEY → Tournament.id, NOT NULL | Tournament reference |
| `timestamp` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | When event occurred |
| `eventType` | ENUM | NOT NULL | level_start, level_end, break_start, break_end, tournament_start, tournament_end, pause, resume |
| `previousState` | JSON | NULLABLE | State before event |
| `newState` | JSON | NOT NULL | State after event |
| `deviceId` | UUID | NULLABLE | Device that triggered event |
| `serverTimestamp` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Server timestamp (authoritative) |

**Indexes**:
- `idx_timerevent_tournament`: (tournamentId, timestamp) for event replay
- `idx_timerevent_deviceId`: (deviceId) for device-specific events

**Validation Rules**:
- Event sequence must be valid (e.g., level_end requires prior level_start)
- `serverTimestamp` used for conflict resolution (FR-011)

---

### SyncRecord

Represents a data synchronization event between devices and central controller.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique sync identifier |
| `deviceId` | UUID | NOT NULL | Device that initiated sync |
| `timestamp` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Sync timestamp |
| `changes` | JSON | NOT NULL | List of changed entities |
| `conflictCount` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Number of conflicts detected |
| `status` | ENUM | NOT NULL, DEFAULT='pending' | pending, success, failed |
| `errorMessage` | TEXT | NULLABLE | Error details if failed |
| `completedAt` | TIMESTAMP | NULLABLE | When sync completed |

**Indexes**:
- `idx_syncrecord_device`: (deviceId, timestamp) for device sync history
- `idx_syncrecord_status`: (status) for pending sync queries

---

### User

Represents a system user with authentication and permissions.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique user identifier |
| `email` | VARCHAR(255) | UNIQUE, NOT NULL, EMAIL format | Login email |
| `passwordHash` | VARCHAR(255) | NOT NULL | Bcrypt hash of password |
| `role` | ENUM | NOT NULL, DEFAULT='director' | admin, director, scorekeeper, viewer |
| `name` | VARCHAR(255) | NULLABLE | Display name |
| `createdAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Creation timestamp |
| `lastLoginAt` | TIMESTAMP | NULLABLE | Last successful login |
| `preferences` | JSON | NOT NULL, DEFAULT={} | User settings (notifications, themes, etc.) |

**Indexes**:
- `idx_user_email`: (email) UNIQUE for authentication

**Role Permissions**:
| Role | Create Tournament | Edit Tournament | Delete Tournament | Manage Players | View Only |
|------|-----------------|----------------|------------------|----------------|-----------|
| admin | ✓ | ✓ | ✓ | ✓ | ✓ |
| director | ✓ | ✓ | ✓ | ✓ | ✓ |
| scorekeeper | ✗ | limited | ✗ | ✓ | ✓ |
| viewer | ✗ | ✗ | ✗ | ✗ | ✓ |

---

## Database Schema (SQLite)

### Tables Creation Order

```sql
-- 1. Users
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  passwordHash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'director',
  name TEXT,
  createdAt TEXT NOT NULL DEFAULT (datetime('now')),
  lastLoginAt TEXT,
  preferences TEXT NOT NULL DEFAULT '{}'
);

CREATE INDEX idx_user_email ON users(email);

-- 2. Blind Schedules
CREATE TABLE blind_schedules (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT,
  startingStack INTEGER NOT NULL DEFAULT 10000,
  breakInterval INTEGER NOT NULL DEFAULT 0,
  breakDuration INTEGER NOT NULL DEFAULT 10,
  createdAt TEXT NOT NULL DEFAULT (datetime('now')),
  updatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  createdBy TEXT REFERENCES users(id)
);

CREATE INDEX idx_blindschedule_createdBy ON blind_schedules(createdBy);

-- 3. Blind Levels
CREATE TABLE blind_levels (
  id TEXT PRIMARY KEY,
  blindScheduleId TEXT NOT NULL REFERENCES blind_schedules(id) ON DELETE CASCADE,
  levelNumber INTEGER NOT NULL,
  smallBlind REAL NOT NULL,
  bigBlind REAL NOT NULL,
  ante REAL,
  durationMinutes INTEGER NOT NULL,
  isBreak INTEGER NOT NULL DEFAULT 0,
  orderIndex INTEGER NOT NULL,
  UNIQUE(blindScheduleId, levelNumber)
);

-- 4. Players
CREATE TABLE players (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT UNIQUE,
  phone TEXT,
  stats TEXT NOT NULL DEFAULT '{}',
  createdAt TEXT NOT NULL DEFAULT (datetime('now')),
  updatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_player_email ON players(email);
CREATE INDEX idx_player_name ON players(name);

-- 5. Tournaments
CREATE TABLE tournaments (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT,
  blindScheduleId TEXT REFERENCES blind_schedules(id),
  status TEXT NOT NULL DEFAULT 'upcoming',
  startTime TEXT,
  endTime TEXT,
  currentLevel INTEGER NOT NULL DEFAULT 1,
  elapsedTime INTEGER NOT NULL DEFAULT 0,
  remainingTime INTEGER NOT NULL DEFAULT 0,
  prizePool REAL NOT NULL DEFAULT 0,
  payoutStructure TEXT NOT NULL DEFAULT '{}',
  createdAt TEXT NOT NULL DEFAULT (datetime('now')),
  updatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  createdBy TEXT REFERENCES users(id),
  deviceId TEXT,
  CHECK(endTime IS NULL OR startTime IS NULL OR endTime > startTime)
);

CREATE INDEX idx_tournament_status ON tournaments(status);
CREATE INDEX idx_tournament_createdBy ON tournaments(createdBy);
CREATE INDEX idx_tournament_deviceId ON tournaments(deviceId);

-- 6. Tournament Players
CREATE TABLE tournament_players (
  id TEXT PRIMARY KEY,
  tournamentId TEXT NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
  playerId TEXT NOT NULL REFERENCES players(id),
  startingStack INTEGER NOT NULL,
  currentStack INTEGER NOT NULL DEFAULT 0,
  finishPosition INTEGER,
  winnings REAL NOT NULL DEFAULT 0,
  bustoutTime TEXT,
  eliminations INTEGER NOT NULL DEFAULT 0,
  registeredAt TEXT NOT NULL DEFAULT (datetime('now')),
  updatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(tournamentId, playerId),
  CHECK(currentStack = 0 OR finishPosition IS NULL)
);

CREATE INDEX idx_tournamentplayer_tournament ON tournament_players(tournamentId);
CREATE INDEX idx_tournamentplayer_finishPosition ON tournament_players(tournamentId, finishPosition);

-- 7. Timer Events
CREATE TABLE timer_events (
  id TEXT PRIMARY KEY,
  tournamentId TEXT NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
  timestamp TEXT NOT NULL DEFAULT (datetime('now')),
  eventType TEXT NOT NULL,
  previousState TEXT,
  newState TEXT NOT NULL,
  deviceId TEXT,
  serverTimestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_timerevent_tournament ON timer_events(tournamentId, timestamp);
CREATE INDEX idx_timerevent_deviceId ON timer_events(deviceId);

-- 8. Sync Records
CREATE TABLE sync_records (
  id TEXT PRIMARY KEY,
  deviceId TEXT NOT NULL,
  timestamp TEXT NOT NULL DEFAULT (datetime('now')),
  changes TEXT NOT NULL,
  conflictCount INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'pending',
  errorMessage TEXT,
  completedAt TEXT
);

CREATE INDEX idx_syncrecord_device ON sync_records(deviceId, timestamp);
CREATE INDEX idx_syncrecord_status ON sync_records(status);
```

---

## JSON Schema Examples

### Payout Structure

```json
{
  "payouts": [
    { "position": 1, "percentage": 0.50 },
    { "position": 2, "percentage": 0.30 },
    { "position": 3, "percentage": 0.20 }
  ]
}
```

### Player Statistics

```json
{
  "tournamentsPlayed": 42,
  "wins": 7,
  "totalWinnings": 5250.00,
  "bestFinish": 1,
  "averageFinish": 12.3
}
```

### Timer Event State

```json
{
  "level": 5,
  "elapsedTime": 450000,
  "remainingTime": 120000,
  "status": "running"
}
```

### Sync Changes

```json
{
  "changes": [
    {
      "entityType": "player",
      "operation": "create",
      "entityId": "uuid-123",
      "data": { "name": "John Doe", "email": "john@example.com" },
      "localTimestamp": 1703577600000
    }
  ]
}
```

---

## Validation Rules Summary

| Entity | Rule | Enforced By |
|--------|------|-------------|
| Tournament | endTime > startTime | Database CHECK constraint |
| Tournament | currentLevel ≤ schedule levels | Application logic |
| Player | Email format if provided | Zod schema validation |
| TournamentPlayer | Unique finishPosition per tournament | Application logic |
| TournamentPlayer | Winnings auto-calculated | Application logic |
| BlindLevel | bigBlind ≥ smallBlind | Application logic |
| User | Email required, unique | Database UNIQUE constraint |
| TimerEvent | Valid event sequence | Application logic |

---

## Migration Strategy

Database schema changes will be handled via migration files:

```
controller/src/db/migrations/
├── 001_initial_schema.sql
├── 002_add_user_preferences.sql
├── 003_add_break_fields.sql
└── ...
```

Each migration:
1. Is numbered sequentially
2. Contains both UP and DOWN SQL
3. Is tested on both fresh installs and upgrades
4. Is committed to version control before deployment
