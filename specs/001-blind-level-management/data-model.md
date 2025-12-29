# Data Model: Blind Level Management

**Feature**: 001-blind-level-management
**Date**: 2025-12-28
**Database**: SQLite (controller) + Expo SQLite (mobile)

This document defines the data model for blind schedule management, including entities, relationships, validation rules, and state transitions.

---

## Entity Relationship Diagram

```
┌──────────────┐     ┌──────────────┐
│    User      │────<│BlindSchedule │
└──────────────┘     └──────┬───────┘
                            │ has many
                            v
                     ┌──────────────┐
                     │  BlindLevel  │
                     └──────────────┘

┌──────────────┐
│  Tournament  │────> BlindSchedule (foreign key)
└──────────────┘
```

---

## Core Entities

### BlindSchedule

Represents a reusable blind structure template containing a name, starting stack, and list of blind levels. Can be selected when creating a tournament.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique schedule identifier |
| `name` | VARCHAR(255) | NOT NULL, MIN=1, MAX=255 | Schedule display name (e.g., "Turbo", "Standard") |
| `description` | TEXT | NULLABLE | Optional description |
| `startingStack` | INTEGER | NOT NULL, DEFAULT=10000, MIN=0 | Default starting chip count |
| `breakInterval` | INTEGER | NOT NULL, DEFAULT=0, MIN=0 | Break every N levels (0 = no breaks) |
| `breakDuration` | INTEGER | NOT NULL, DEFAULT=10, MIN=0 | Break duration in minutes |
| `isDefault` | BOOLEAN | NOT NULL, DEFAULT=FALSE | Whether this is a pre-loaded default schedule |
| `createdAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Creation timestamp |
| `updatedAt` | TIMESTAMP | NOT NULL, DEFAULT=NOW() | Last update timestamp |
| `createdBy` | UUID | FOREIGN KEY → User.id | Creator user |

**Indexes**:
- `idx_blindschedule_createdBy`: (createdBy) for user's schedules
- `idx_blindschedule_isDefault`: (isDefault) for filtering default schedules

**Validation Rules**:
- `name` must contain at least one non-whitespace character
- `startingStack` must be ≥ 0
- `breakInterval` must be ≥ 0
- `breakDuration` must be ≥ 0
- Default schedules (`isDefault=true`) cannot be deleted
- When user edits a default schedule, a copy is created instead

**State Transitions**:
```
draft → active → archived
```
- `draft`: Schedule being created, not yet usable
- `active`: Schedule available for tournament selection
- `archived`: Schedule hidden from list but preserved for historical tournaments

---

### BlindLevel

Individual level within a blind schedule, containing level number, small blind amount, big blind amount, optional ante, duration in minutes, and break flag.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `id` | UUID | PRIMARY KEY, NOT NULL | Unique level identifier |
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
- `durationMinutes` must be ≥ 1 for normal levels
- `durationMinutes` for break levels comes from parent schedule's `breakDuration`
- `ante` is NULL if no ante for this level

---

### Tournament (Extended)

Existing Tournament entity with blind schedule reference added.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| `blindScheduleId` | UUID | FOREIGN KEY → BlindSchedule.id, NULLABLE | Selected blind schedule |
| `currentBlindLevel` | INTEGER | NOT NULL, DEFAULT=1, MIN=1 | Current blind level number |

**Relationships**:
- Tournament links to BlindSchedule via `blindScheduleId`
- When tournament is created, blind schedule association is stored
- Tournament progresses through levels via `currentBlindLevel`

**Validation Rules**:
- If `blindScheduleId` is set, `currentBlindLevel` must be ≤ number of levels in schedule
- If schedule is deleted, `blindScheduleId` is set to NULL (cascade not used - preserve history)

---

## Database Schema (SQLite)

### Tables Creation Order

```sql
-- 1. Blind Schedules (extends existing schema)
CREATE TABLE IF NOT EXISTS blind_schedules (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT,
  startingStack INTEGER NOT NULL DEFAULT 10000,
  breakInterval INTEGER NOT NULL DEFAULT 0,
  breakDuration INTEGER NOT NULL DEFAULT 10,
  isDefault INTEGER NOT NULL DEFAULT 0,
  createdAt TEXT NOT NULL DEFAULT (datetime('now')),
  updatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  createdBy TEXT REFERENCES users(id)
);

CREATE INDEX idx_blindschedule_createdBy ON blind_schedules(createdBy);
CREATE INDEX idx_blindschedule_isDefault ON blind_schedules(isDefault);

-- 2. Blind Levels (extends existing schema)
CREATE TABLE IF NOT EXISTS blind_levels (
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

-- 3. Tournaments (add blind schedule reference)
-- Note: This assumes tournaments table already exists from 002-expo-rewrite
ALTER TABLE tournaments ADD COLUMN blindScheduleId TEXT REFERENCES blind_schedules(id);
ALTER TABLE tournaments ADD COLUMN currentBlindLevel INTEGER NOT NULL DEFAULT 1;

CREATE INDEX idx_tournament_blindSchedule ON tournaments(blindScheduleId);
```

---

## Validation Rules Summary

| Entity | Rule | Enforced By |
|--------|------|-------------|
| BlindSchedule | name non-empty | Zod schema validation |
| BlindSchedule | isDefault schedules cannot be deleted | Application logic |
| BlindLevel | bigBlind ≥ smallBlind | Application logic |
| BlindLevel | Break levels have 0/0 blinds | Application logic |
| Tournament | currentLevel ≤ schedule levels | Application logic |
| Tournament | Blind schedule preserved on deletion | Database (no CASCADE) |

---

## JSON Schema Examples

### BlindSchedule Creation Request

```json
{
  "name": "My Custom Schedule",
  "description": "For Friday night games",
  "startingStack": 15000,
  "breakInterval": 5,
  "breakDuration": 10,
  "levels": [
    {
      "levelNumber": 1,
      "smallBlind": 25,
      "bigBlind": 50,
      "ante": null,
      "durationMinutes": 20,
      "isBreak": false
    },
    {
      "levelNumber": 2,
      "smallBlind": 50,
      "bigBlind": 100,
      "ante": null,
      "durationMinutes": 20,
      "isBreak": false
    },
    {
      "levelNumber": 3,
      "smallBlind": 0,
      "bigBlind": 0,
      "ante": null,
      "durationMinutes": 10,
      "isBreak": true
    }
  ]
}
```

### BlindSchedule Response

```json
{
  "id": "uuid-123",
  "name": "My Custom Schedule",
  "description": "For Friday night games",
  "startingStack": 15000,
  "breakInterval": 5,
  "breakDuration": 10,
  "isDefault": false,
  "levelCount": 15,
  "totalDurationMinutes": 300,
  "createdAt": "2025-12-28T00:00:00Z",
  "updatedAt": "2025-12-28T00:00:00Z",
  "createdBy": "user-uuid",
  "levels": [
    {
      "id": "level-uuid-1",
      "levelNumber": 1,
      "smallBlind": 25,
      "bigBlind": 50,
      "ante": null,
      "durationMinutes": 20,
      "isBreak": false,
      "order": 1
    }
  ]
}
```

### Tournament with Blind Schedule

```json
{
  "id": "tournament-uuid",
  "name": "Friday Night Game",
  "blindScheduleId": "uuid-123",
  "currentBlindLevel": 5,
  "blindSchedule": {
    "id": "uuid-123",
    "name": "My Custom Schedule",
    "levels": [...]
  },
  "currentLevel": {
    "levelNumber": 5,
    "smallBlind": 200,
    "bigBlind": 400,
    "ante": 50,
    "durationMinutes": 20,
    "isBreak": false
  }
}
```

---

## Migration Strategy

Database schema changes will be handled via migration files:

```
controller/src/db/migrations/
├── 001_initial_schema.sql  # Already exists from 002-expo-rewrite
├── 002_add_blind_schedule_defaults.sql  # NEW: Add isDefault column
└── 003_seed_default_schedules.sql  # NEW: Insert default schedules
```

Each migration:
1. Is numbered sequentially
2. Contains both UP and DOWN SQL
3. Is tested on both fresh installs and upgrades
4. Is committed to version control before deployment

### Mobile Database

Mobile app uses Expo SQLite with matching schema:
- Tables created via `mobile-app/src/db/schema.ts`
- Migrations run via `mobile-app/src/db/migrations.ts`
- Offline sync via `mobile-app/src/services/sync/`

---

## Relationships Summary

| Relationship | Type | Cascade | Notes |
|--------------|------|---------|-------|
| User → BlindSchedule | One-to-Many | No | User owns schedules |
| BlindSchedule → BlindLevel | One-to-Many | Yes | Levels deleted with schedule |
| Tournament → BlindSchedule | Many-to-One | No | Schedule preserved if deleted |
| Tournament → currentBlindLevel | Reference | N/A | Integer pointer to level number |
