# Data Model: Blind Level Scheme Management

**Feature**: 002-blind-level-crud
**Created**: 2025-12-28
**Status**: Complete

## Overview

This document defines the data entities, relationships, and validation rules for blind level scheme management. The model reuses existing database schema from feature 001-blind-level-management and extends it for management-specific functionality.

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                      BlindScheme                             │
├─────────────────────────────────────────────────────────────┤
│ PK: blindScheduleId: string (UUID)                          │
│     name: string (1-100 chars)                              │
│     description: string (0-500 chars)                       │
│     startingStack: number (positive integer)                │
│     breakInterval: number (non-negative integer)             │
│     breakDuration: number (non-negative integer)             │
│     isDefault: boolean                                      │
│     createdAt: datetime                                     │
│     updatedAt: datetime                                     │
│     createdBy: string (userId)                              │
└─────────────────────────────────────────────────────────────┘
                           │ 1
                           │ has many
                           │ N
┌─────────────────────────────────────────────────────────────┐
│                       BlindLevel                             │
├─────────────────────────────────────────────────────────────┤
│ PK: blindLevelId: string (UUID)                             │
│ FK: blindScheduleId: string (references BlindScheme)        │
│     level: number (positive integer, sequential)            │
│     smallBlind: number (non-negative integer)               │
│     bigBlind: number (>= smallBlind)                        │
│     ante: number (optional, non-negative integer)            │
│     duration: number (positive integer, minutes)             │
│     isBreak: boolean                                        │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                       Tournament                             │
├─────────────────────────────────────────────────────────────┤
│ PK: tournamentId: string (UUID)                             │
│ FK: blindScheduleId: string (references BlindScheme)        │
│     ... existing tournament fields ...                      │
└─────────────────────────────────────────────────────────────┘
```

## Database Schema

### Existing Tables (Reused from Feature 001)

#### blind_schedules

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| blind_schedule_id | TEXT | PRIMARY KEY, UUID | Auto-generated on create |
| name | TEXT | NOT NULL, UNIQUE (case-insensitive) | Scheme name |
| description | TEXT | NULL | Optional description |
| starting_stack | INTEGER | NOT NULL, > 0 | Starting chip stack |
| break_interval | INTEGER | NOT NULL, >= 0 | Levels between breaks |
| break_duration | INTEGER | NOT NULL, >= 0 | Break length in minutes |
| isDefault | INTEGER | NOT NULL, 0 or 1 | 1 = default scheme (protected) |
| created_at | TEXT | NOT NULL | ISO 8601 datetime |
| updated_at | TEXT | NOT NULL | ISO 8601 datetime |
| created_by | TEXT | NULL | User ID who created scheme |

**Indexes**:
- `idx_blind_schedules_name` on `name COLLATE NOCASE` (for case-insensitive search)
- `idx_blind_schedules_isDefault` on `isDefault` (for filtering defaults)

**Foreign Keys**: None (levels reference this table)

#### blind_levels

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| blind_level_id | TEXT | PRIMARY KEY, UUID | Auto-generated on create |
| blind_schedule_id | TEXT | NOT NULL, FOREIGN KEY | References blind_schedules |
| level | INTEGER | NOT NULL, > 0 | Sequential level number |
| small_blind | INTEGER | NOT NULL, >= 0 | Small blind amount |
| big_blind | INTEGER | NOT NULL, >= small_blind | Big blind amount |
| ante | INTEGER | NULL, >= 0 | Optional ante amount |
| duration | INTEGER | NOT NULL, > 0 | Duration in minutes |
| is_break | INTEGER | NOT NULL, 0 or 1 | 1 = break level |

**Indexes**:
- `idx_blind_levels_schedule` on `blind_schedule_id` (for fetching levels)
- `idx_blind_levels_level` on `(blind_schedule_id, level)` (for ordering)

**Foreign Keys**:
- `fk_blind_levels_schedule` → `blind_schedules(blind_schedule_id)` ON DELETE CASCADE

**Constraints**:
- `ck_blind_levels_blind_ratio`: `big_blind >= small_blind`
- `ck_blind_levels_break_zero_blinds`: `(is_break = 1) IMPLIES (small_blind = 0 AND big_blind = 0)`
- `ck_blind_levels_duration_positive`: `duration > 0`

### Existing Tables (Referenced)

#### tournaments

| Column | Type | Notes |
|--------|------|-------|
| tournament_id | TEXT | PK |
| blind_schedule_id | TEXT | FK to blind_schedules |

**Usage**: Referenced to check if scheme is in use before deletion

## TypeScript Types

### Shared Types (shared/src/types/timer.ts)

```typescript
/**
 * Blind Scheme - Complete blind structure
 * Extends existing BlindSchedule from feature 001
 */
export interface BlindScheme {
  blindScheduleId: string;
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  isDefault: boolean;
  createdAt: Date;
  updatedAt: Date;
  createdBy?: string;
  levels: BlindLevel[];
}

/**
 * Blind Level - Individual level within a scheme
 * Reused from feature 001 without changes
 */
export interface BlindLevel {
  blindLevelId: string;
  blindScheduleId: string;
  level: number;
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  duration: number;
  isBreak: boolean;
}

/**
 * Blind Scheme List Item (metadata only, no levels)
 * Used for list view performance
 */
export interface BlindSchemeListItem {
  id: string;
  name: string;
  description: string | null;
  startingStack: number;
  levelCount: number;
  totalDurationMinutes: number;
  isDefault: boolean;
}

/**
 * Blind Scheme Validation Errors
 */
export interface BlindSchemeValidationError {
  field: string;
  message: string;
  level?: number; // For level-specific errors
}

/**
 * Blind Scheme Create Input
 */
export interface CreateBlindSchemeInput {
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  levels: CreateBlindLevelInput[];
}

/**
 * Blind Level Create Input
 */
export interface CreateBlindLevelInput {
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  duration: number;
  isBreak: boolean;
}

/**
 * Blind Scheme Update Input
 * All fields optional for partial updates
 */
export interface UpdateBlindSchemeInput {
  name?: string;
  description?: string;
  startingStack?: number;
  breakInterval?: number;
  breakDuration?: number;
  levels?: CreateBlindLevelInput[];
}

/**
 * Blind Level Update Input
 */
export interface UpdateBlindLevelInput {
  smallBlind?: number;
  bigBlind?: number;
  ante?: number;
  duration?: number;
  isBreak?: boolean;
}

/**
 * Sync Queue Item (for offline CRUD)
 */
export interface SyncQueueItem {
  type: 'CREATE_SCHEME' | 'UPDATE_SCHEME' | 'DELETE_SCHEME';
  schemeId: string;
  data?: CreateBlindSchemeInput | UpdateBlindSchemeInput;
  timestamp: number;
}
```

## Validation Rules

### Scheme-Level Validation

| Rule | Field | Constraint | Error Message |
|------|-------|------------|---------------|
| SCHEME-001 | name | Required, 1-100 chars | "Scheme name is required (max 100 characters)" |
| SCHEME-002 | name | Case-insensitive unique | "A scheme with this name already exists" |
| SCHEME-003 | description | Max 500 chars | "Description too long (max 500 characters)" |
| SCHEME-004 | startingStack | > 0 | "Starting stack must be greater than 0" |
| SCHEME-005 | breakInterval | >= 0 | "Break interval must be non-negative" |
| SCHEME-006 | breakDuration | >= 0 | "Break duration must be non-negative" |
| SCHEME-007 | levels | At least 1 level | "Scheme must have at least one level" |
| SCHEME-008 | levels | Sequential numbering from 1 | "Levels must be numbered sequentially from 1" |

### Level-Level Validation

| Rule | Field | Constraint | Error Message |
|------|-------|------------|---------------|
| LEVEL-001 | level | > 0, sequential | "Level number must be positive and sequential" |
| LEVEL-002 | smallBlind | >= 0 | "Small blind must be non-negative" |
| LEVEL-003 | bigBlind | >= smallBlind | "Big blind must be >= small blind" |
| LEVEL-004 | ante | >= 0 | "Ante must be non-negative" |
| LEVEL-005 | duration | > 0 | "Duration must be greater than 0 (minutes)" |
| LEVEL-006 | isBreak + blinds | If break, blinds must be 0 | "Break levels must have zero blinds" |

### Business Logic Validation

| Rule | Context | Constraint | Error Message |
|------|---------|------------|---------------|
| BUSINESS-001 | Delete | Cannot delete default schemes | "Cannot delete default blind schemes" |
| BUSINESS-002 | Delete | Cannot delete if in use by active tournament | "Scheme is in use by active tournament" |
| BUSINESS-003 | Edit | Editing default creates copy | "Modified default scheme saved as new copy" |

## State Transitions

### Blind Scheme Lifecycle

```
[New Draft] ──save──> [Saved]
                      │
                      ├─edit──> [Editing]
                      │         │
                      │         ├─save──> [Saved]
                      │         └─cancel──> [Saved]
                      │
                      └─delete──> [Deleted]
```

### Level Reordering (FR-016)

```
[Level at position N] ──move──> [Level at position M]
                                │
                                └─renumber all levels sequentially
```

## Data Flow

### Create Flow

```
[User Input] ──validate──> [Valid Input] ──API──> [Database]
                                    │
                                    └─cache──> [AsyncStorage]
                                             └─sync queue
```

### Update Flow

```
[User Input] ──validate──> [Valid Input] ──optimistic update──> [Local State]
                                                      │
                                                      ├─cache──> [AsyncStorage]
                                                      │         └─sync queue
                                                      │
                                                      └─API──> [Database]
```

### Delete Flow

```
[Delete Request] ──check usage──> [In Use?] ──yes──> [Error]
                                     │ no
                                     └─check default──> [Is Default?] ──yes──> [Error]
                                                          │ no
                                                          └─delete──> [Database]
                                                                      └─invalidate cache
```

## Indexes for Performance

### blind_schedules

- `idx_blind_schedules_name`: Case-insensitive name search
- `idx_blind_schedules_isDefault`: Filter default schemes
- `idx_blind_schedules_created_by`: User's schemes list

### blind_levels

- `idx_blind_levels_schedule_level`: Composite (schedule_id, level) for ordering
- `idx_blind_levels_schedule`: Fetch all levels for a scheme

## Data Migration

### No Migration Required

This feature reuses existing schema from feature 001-blind-level-management. No database migrations needed.

### Default Data Seeding

Default schemes already seeded by feature 001:
- Turbo (name: "Turbo")
- Standard (name: "Standard")
- Deep Stack (name: "Deep Stack")

## Storage Requirements

### Per-Scheme Storage

- Scheme metadata: ~200 bytes
- Per level: ~80 bytes
- Typical scheme (15 levels): ~1.4 KB
- 100 schemes: ~140 KB

### Sync Queue Storage

- Per operation: ~200 bytes
- 100 pending operations: ~20 KB

### AsyncStorage Total

- Schemes cache: ~150 KB
- Sync queue: ~20 KB
- API config: ~100 bytes
- **Total**: ~170 KB (well within AsyncStorage limits)

## Concurrency Control

### Optimistic Locking

Schemes use `updated_at` timestamp for conflict detection:

```
Client A: GET scheme (updated_at = 1000)
Client B: GET scheme (updated_at = 1000)

Client A: PUT scheme with updated_at = 1000 ──success──> updated_at = 1001
Client B: PUT scheme with updated_at = 1000 ──fail──> 409 Conflict
```

### Conflict Resolution

Per SC-015: Last-write-wins with user notification

```typescript
if (conflict) {
  Alert.alert(
    'Scheme Modified',
    'This scheme was changed by another device. Your changes will overwrite theirs.',
    [{ text: 'Cancel' }, { text: 'Overwrite', onPress: forceUpdate }]
  );
}
```

## Caching Strategy

### Read Cache

- Cache all schemes in AsyncStorage after successful API fetch
- Invalidate cache on create/update/delete
- Serve from cache while offline

### Write Cache (Sync Queue)

- Queue operations when offline
- Flush queue on reconnect
- Remove from queue on success
- Retry failed operations with exponential backoff

## Data Relationships Summary

```
BlindScheme 1──N BlindLevel
Tournament N──1 BlindScheme
User 1──N BlindScheme (created_by)
```
