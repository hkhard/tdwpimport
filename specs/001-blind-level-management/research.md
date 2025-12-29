# Research: Blind Level Management

**Feature**: 001-blind-level-management
**Date**: 2025-12-28
**Status**: Complete

This document documents research findings and technology decisions for blind level management.

---

## Research Topic 1: Blind Schedule Data Structure

### Decision: Use existing shared types with minor extensions

**Rationale**:
- `shared/src/types/timer.ts` already defines `BlindLevel` and `BlindSchedule` types
- Existing structure supports all required fields: levelNumber, smallBlind, bigBlind, ante, durationMinutes, isBreak
- Database schema already includes `blind_schedules` and `blind_levels` tables with proper relationships
- No changes needed to data model - feature leverages existing infrastructure

**Existing Type Definitions**:
```typescript
// shared/src/types/timer.ts (ALREADY EXISTS)
interface BlindLevel {
  id: string;
  blindScheduleId: string;
  levelNumber: number;
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  durationMinutes: number;
  isBreak: boolean;
  order: number;
}

interface BlindSchedule {
  id: string;
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  createdAt: string;
  updatedAt: string;
  createdBy: string;
}
```

**Database Schema** (already exists from 002-expo-rewrite):
```sql
-- Controller: blind_schedules table
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

-- Controller: blind_levels table
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
```

**Alternatives Considered**:
- **JSON blob storage**: Rejected because less queryable, harder to validate
- **Separate mobile schema**: Rejected to maintain consistency with controller

---

## Research Topic 2: Offline Caching Strategy

### Decision: AsyncStorage for blind schedules + SQLite for mobile tournament data

**Rationale**:
- **AsyncStorage**: Simple key-value store for caching blind schedules library
  - Key: `@blind_schedules` stores array of all schedules
  - Fast read/write, sufficient for small dataset (100 schedules × 50 levels)
  - Survives app restarts
- **Expo SQLite**: For tournament-blind associations during active play
  - Tournament already stored in mobile SQLite
  - Blind schedule reference stored as foreign key
  - Enables offline tournament management

**Cache Strategy**:
```typescript
// Cache blind schedules on app launch or after CRUD
await AsyncStorage.setItem('@blind_schedules', JSON.stringify(schedules));

// Load from cache when offline
const cached = await AsyncStorage.getItem('@blind_schedules');
const schedules = cached ? JSON.parse(cached) : await fetchFromAPI();
```

**Sync Logic**:
1. On app start: Load from AsyncStorage
2. When online: Fetch latest from API, update AsyncStorage
3. On CRUD: Optimistic update to AsyncStorage, then sync to API
4. Conflict resolution: Server wins, client refreshes

**Alternatives Considered**:
- **SQLite-only**: Rejected because overkill for simple list cache
- **Memory cache only**: Rejected because doesn't survive app restart
- **Realm Database**: Rejected because adds dependency, AsyncStorage sufficient

---

## Research Topic 3: UI Patterns for Blind Level Display

### Decision: Large prominent display for current level, scrollable list for upcoming

**Rationale**:
- Tournament directors need to see current blinds at a glance (players ask constantly)
- Upcoming levels needed for announcement ("next level is 200/400")
- Break levels need special visual treatment (different color/icon)

**UI Components**:

**1. BlindLevelDisplay (Prominent)**
- Large font for current blinds: "100 / 200"
- Smaller font for ante: "Ante: 25"
- Break indicator: "BREAK - 5:00 remaining" (when isBreak=true)
- Color coding: Green for normal, yellow for break, red for final level
- Position: Top of timer section in TournamentDetail screen

**2. BlindLevelsList (Scrollable)**
- List of upcoming levels (next 10, load more on scroll)
- Current level highlighted with checkmark or "Current" badge
- Each row shows: Level number, blinds, duration
- Break levels shown with different icon
- Scrollable to view future levels
- Tap level to preview (not change - that's separate controls)

**3. Level Controls (Manual Override)**
- "+ Level" button: Advances to next level
- "- Level" button: Rewinds to previous level (disabled at level 1)
- Positioned below timer display
- Confirmation dialog for manual changes (prevents accidental taps)

**Responsive Design**:
- Minimum touch targets: 44px (constitution requirement)
- Small screen (iPhone SE): Stack vertically, compact fonts
- Tablet: Side-by-side layout, larger fonts

**Alternatives Considered**:
- **Modal for blind levels**: Rejected because less accessible, adds friction
- **Inline editing**: Rejected because error-prone, separate editor screen safer
- **Horizontal scrolling cards**: Rejected because less space-efficient than list

---

## Research Topic 4: Default Blind Schedule Templates

### Decision: Three pre-loaded schedules covering common tournament types

**Rationale**:
- New users need immediate functionality without creating schedules first
- Three templates cover 80% of home tournament scenarios
- Schedules are read-only (user creates copy to edit)

**Default Schedules**:

**1. Turbo (Quick tournaments)**
- Starting stack: 5,000
- Level duration: 10 minutes
- Break interval: Every 5 levels
- Break duration: 5 minutes
- Levels: 1-15 (blinds double every 2 levels)
  - L1: 25/50, L2: 50/100, L3: 75/150, L4: 100/200
  - L5: 150/300, L6: 200/400, L7: 300/600, L8: 400/800
  - L9: 600/1200, L10: 800/1600, L11: 1000/2000, L12: 1500/3000
  - L13: 2000/4000, L14: 3000/6000, L15: 5000/10000

**2. Standard (Home tournament)**
- Starting stack: 10,000
- Level duration: 20 minutes
- Break interval: Every 4 levels
- Break duration: 10 minutes
- Levels: 1-20 (gradual progression)
  - L1: 25/50, L2: 50/100, L3: 75/150, L4: 100/200
  - L5-Break, L6: 100/200, L7: 150/300, L8: 200/400
  - L9-Break, L10: 200/400, L11: 300/600, L12: 400/800
  - L13-Break, L14: 600/1200, L15: 800/1600, L16: 1000/2000
  - L17-Break, L18: 1500/3000, L19: 2000/4000, L20: 3000/6000

**3. Deep Stack (Long tournaments)**
- Starting stack: 20,000
- Level duration: 30 minutes
- Break interval: Every 4 levels
- Break duration: 15 minutes
- Levels: 1-25 (slow progression)
  - L1: 50/100, L2: 75/150, L3: 100/200, L4: 150/300
  - L5-Break, L6: 200/400, L7: 300/600, L8: 400/800
  - L9-Break, L10: 500/1000, L11: 750/1500, L12: 1000/2000
  - L13-Break, L14: 1500/3000, L15: 2000/4000, L16: 3000/6000
  - L17-Break, L18: 4000/8000, L19: 6000/12000, L20: 8000/16000
  - L21-Break, L22: 10000/20000, L23: 15000/30000, L24: 20000/40000
  - L25-Break

**Implementation**:
- Stored in `controller/src/data/defaultBlindSchedules.ts` as constant array
- Loaded via migration when database is initialized
- Marked with `isDefault: true` flag (prevents deletion)
- When user edits default schedule, system creates copy with `name: "Turbo (Copy)"`

**Alternatives Considered**:
- **User-generated templates**: Rejected because new users have no templates yet
- **Single default schedule**: Rejected because too limiting for different tournament types
- **Downloadable templates**: Rejected because adds complexity, local storage simpler

---

## Summary of Decisions

| Topic | Decision | Key Benefit |
|-------|----------|-------------|
| Data Structure | Use existing shared types | No migration needed, consistency |
| Offline Cache | AsyncStorage + SQLite | Simple, fast, survives restart |
| UI Pattern | Prominent current + scrollable upcoming | At-a-glance info, detailed access |
| Default Schedules | Turbo, Standard, Deep Stack | Covers 80% of use cases |

**Next Steps**: Proceed to Phase 1 (Design Artifacts) - create data-model.md, API contracts, and quickstart.md.
