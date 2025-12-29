# Research: Blind Level Scheme Management Screen

**Feature**: 002-blind-level-crud
**Created**: 2025-12-28
**Status**: Complete

## Overview

This research document validates technical decisions for implementing a blind level scheme CRUD management screen in the mobile app. All unknowns from Technical Context have been resolved through analysis of existing codebase and best practices evaluation.

## Research Topics

### 1. Settings Screen Navigation Pattern

**Question**: How should blind level management integrate into existing Settings navigation?

**Decision**: Add Settings as a top-level tab with sub-navigation for management screens

**Rationale**:
- Existing SettingsScreen.tsx is a simple flat list (API config, preferences)
- React Navigation v6 Stack Navigator already in use for screen hierarchy
- Constitution requires clear hierarchy: Settings > Blind Level Management > [Specific Scheme]
- User story FR-027/FR-028 specify sub-screens for specific settings categories

**Implementation Pattern**:
```typescript
// Stack Navigator within Settings tab
<Stack.Navigator>
  <Stack.Screen name="SettingsMain" component={SettingsScreen} />
  <Stack.Screen name="BlindSchemeList" component={BlindSchemeListScreen} />
  <Stack.Screen name="BlindSchemeEditor" component={BlindSchemeEditorScreen} />
</Stack.Navigator>
```

**Alternatives Considered**:
- Modal presentation from Settings - Rejected: Breaks navigation hierarchy, harder to maintain
- Separate tab for blind management - Rejected: Overkill for single management feature

---

### 2. Offline-First CRUD Strategy

**Question**: How to handle CRUD operations when device is offline?

**Decision**: AsyncStorage cache with write-ahead queue and sync on reconnect

**Rationale**:
- Constitution III (CMS Integration) requires API-only communication
- Feature spec FR-025: "System MUST cache schemes locally for offline access"
- Feature spec FR-026: "System MUST handle merge conflicts when same scheme edited on multiple devices"
- Existing mobile app uses AsyncStorage for API URL persistence
- Zustand store can persist to AsyncStorage automatically

**Implementation Pattern**:
```typescript
// Optimistic updates with sync queue
const updateScheme = async (id: string, updates: Partial<BlindScheme>) => {
  // 1. Update local state immediately (optimistic)
  setSchemes(prev => prev.map(s => s.id === id ? { ...s, ...updates } : s));

  // 2. Add to sync queue
  const syncQueue = await AsyncStorage.getItem('sync_queue');
  const queue = JSON.parse(syncQueue || '[]');
  queue.push({ type: 'UPDATE_SCHEME', id, updates, timestamp: Date.now() });
  await AsyncStorage.setItem('sync_queue', JSON.stringify(queue));

  // 3. Try API call (background)
  if (isOnline) {
    await flushSyncQueue();
  }
};

// On reconnect, flush queue
NetInfo.addEventListener(state => {
  if (state.isConnected) flushSyncQueue();
});
```

**Merge Conflict Resolution**: Last-write-wins with user notification (per SC-015)

**Alternatives Considered**:
- Full offline-first with local database - Rejected: Over-complexity for read-mostly data
- Block edits when offline - Rejected: Poor UX, violates FR-006 requirement

---

### 3. List Performance for 100+ Schemes

**Question**: How to ensure smooth scrolling with 100+ blind schemes?

**Decision**: FlatList with windowing and pagination

**Rationale**:
- Feature spec SC-008: "Scheme list supports 100+ schemes without performance degradation"
- React Native FlatList has built-in windowing (renders only visible items)
- Existing BlindLevelsList.tsx already implements pagination pattern (PAGE_SIZE = 10)
- 100 schemes × ~500 bytes each = 50KB total data (trivial for memory)

**Implementation Pattern**:
```typescript
<FlatList
  data={schemes}
  renderItem={BlindSchemeListItem}
  keyExtractor={(item) => item.blindScheduleId}
  windowSize={5}  // Render 5 screens worth of items
  initialNumToRender={10}
  maxToRenderPerBatch={10}
  removeClippedSubviews={true}
/>
```

**Alternatives Considered**:
- Infinite scroll with manual pagination - Rejected: FlatList handles this automatically
- VirtualizedList - Rejected: FlatList is sufficient and simpler

---

### 4. Inline Editing UX Pattern

**Question**: What UX pattern for editing blind levels within a scheme?

**Decision**: Inline table with tap-to-edit and swipe-to-delete

**Rationale**:
- Feature spec FR-017: "System MUST provide inline editing of blind levels"
- Mobile pattern reference: iOS Reminders, Google Sheets mobile
- Existing BlindScheduleEditorScreen.tsx can provide patterns
- Reorder support (FR-016) requires visible drag handles

**Implementation Pattern**:
```typescript
// Each level is editable row
<BlindLevelRow
  level={level}
  isEditing={editingLevelId === level.blindLevelId}
  onEdit={() => setEditingLevelId(level.blindLevelId)}
  onSave={(updates) => updateLevel(level.blindLevelId, updates)}
  onDelete={() => deleteLevel(level.blindLevelId)}
  onMoveUp={() => moveLevel(level.level, -1)}
  onMoveDown={() => moveLevel(level.level, 1)}
/>
```

**Alternatives Considered**:
- Separate edit screen for each level - Rejected: Too many taps for bulk editing
- Modal editor - Rejected: Loses context of full scheme structure

---

### 5. Validation Strategy

**Question**: How to validate blind scheme data (FR-014, FR-015, FR-009)?

**Decision**: Zod schema validation shared between mobile and controller

**Rationale**:
- Constitution IV (TypeScript Discipline): Strict typing required
- Feature spec FR-014: "big blind >= small blind"
- Feature spec FR-015: "break levels have zero blinds"
- Feature spec FR-009: "sequential level numbering starting from 1"
- Zod provides runtime validation with TypeScript inference
- Can share validation logic via shared/ package

**Implementation Pattern**:
```typescript
// shared/src/types/timer.ts
import { z } from 'zod';

export const BlindLevelSchema = z.object({
  blindLevelId: z.string().uuid(),
  level: z.number().int().positive(),
  smallBlind: z.number().int().nonnegative(),
  bigBlind: z.number().int().nonnegative(),
  ante: z.number().int().nonnegative().optional(),
  duration: z.number().int().positive(),
  isBreak: z.boolean(),
}).refine(
  (data) => !data.isBreak || (data.smallBlind === 0 && data.bigBlind === 0),
  { message: "Break levels must have zero blinds" }
).refine(
  (data) => data.bigBlind >= data.smallBlind,
  { message: "Big blind must be >= small blind" }
);

export const BlindSchemeSchema = z.object({
  blindScheduleId: z.string().uuid(),
  name: z.string().min(1).max(100),
  description: z.string().max(500).optional(),
  startingStack: z.number().int().positive(),
  breakInterval: z.number().int().nonnegative(),
  breakDuration: z.number().int().nonnegative(),
  levels: z.array(BlindLevelSchema).min(1),
}).refine(
  (data) => {
    // Check sequential numbering
    for (let i = 0; i < data.levels.length; i++) {
      if (data.levels[i].level !== i + 1) return false;
    }
    return true;
  },
  { message: "Levels must be numbered sequentially from 1" }
);
```

**Alternatives Considered**:
- Manual validation functions - Rejected: More error-prone, no type inference
- Yup or Joi - Rejected: Zod has better TypeScript support

---

### 6. Default Scheme Protection

**Question**: How to prevent editing/deleting default schemes (FR-006)?

**Decision**: Database flag + API enforcement + UI disabled state

**Rationale**:
- Feature spec FR-006: "System MUST prevent deletion of default blind level schemes"
- Feature spec US3-A3: "editing default scheme creates copy instead"
- Existing BlindScheduleRepository.isDefaultSchedule() method available
- Database already has isDefault column (from feature 001)

**Implementation Pattern**:
```typescript
// API layer (controller)
fastify.delete('/blind-schedules/:id', async (request, reply) => {
  const { id } = request.params;

  // Check if default
  const isDefault = await blindScheduleRepo.isDefaultSchedule(id);
  if (isDefault) {
    return reply.code(403).send({
      success: false,
      error: 'Cannot delete default blind schemes',
    });
  }

  // Check if in use
  const isInUse = await blindScheduleRepo.isInUse(id);
  if (isInUse) {
    return reply.code(409).send({
      success: false,
      error: 'Cannot delete scheme used by active tournament',
    });
  }

  await blindScheduleRepo.deleteSchedule(id);
  return reply.send({ success: true });
});

// Mobile UI layer
{!scheme.isDefault && (
  <TouchableOpacity onPress={() => deleteScheme(scheme.id)}>
    <Text>Delete</Text>
  </TouchableOpacity>
)}
```

**Alternatives Considered**:
- Client-side only enforcement - Rejected: Users can bypass with API calls
- Soft delete - Rejected: Unnecessary complexity for rare action

---

### 7. Real-Time Preview During Editing

**Question**: How to show live preview of blind structure (FR-018)?

**Decision**: Computed preview from edited state with debounced validation

**Rationale**:
- Feature spec FR-018: "System MUST display a preview of the blind structure as users edit"
- Feature spec FR-019: "System MUST show clear visual distinction between play levels and break levels"
- React state updates trigger re-render automatically
- Can reuse BlindLevelsList.tsx component in preview mode

**Implementation Pattern**:
```typescript
// In editor screen
const [editedScheme, setEditedScheme] = useState<BlindScheme>(initialScheme);
const [isValid, setIsValid] = useState(true);

// Live validation
useEffect(() => {
  const result = BlindSchemeSchema.safeParse(editedScheme);
  setIsValid(result.success);
}, [editedScheme]);

// Preview section
<View style={styles.preview}>
  <Text style={styles.previewTitle}>Preview</Text>
  <BlindLevelsList
    levels={editedScheme.levels}
    currentLevelNumber={0}  // Not applicable in preview
    onLevelPress={() => {}}
  />
</View>

// Save button disabled if invalid
<TouchableOpacity disabled={!isValid} onPress={handleSave}>
  <Text style={{ opacity: isValid ? 1 : 0.5 }}>Save Scheme</Text>
</TouchableOpacity>
```

**Alternatives Considered**:
- Separate preview screen - Rejected: Breaks editing flow
- Preview only on save - Rejected: Doesn't meet "as users edit" requirement

---

## Technology Stack Validation

### Mobile App

| Technology | Version | Justification |
|------------|---------|---------------|
| Expo SDK | 54.0 | Already in use, managed workflow sufficient |
| React Navigation | v6 | Already in use, Stack Navigator for Settings hierarchy |
| Zustand | 4.4 | Already in use for state management, simpler than Redux |
| TypeScript | 5.9 | Constitution requirement, strict mode enabled |
| Zod | NEW | Will add for runtime validation with TS inference |

### Controller API

| Technology | Version | Justification |
|------------|---------|---------------|
| Fastify | Existing | Already in use, extends blindSchedules routes |
| SQLite | Existing | Database already has blind_schedules schema from 001 |
| TypeScript | 5.0+ | Constitution requirement |

## Dependencies

### External Dependencies (None Required)

All functionality can be built with existing dependencies. Only addition is **Zod** for validation.

### Internal Dependencies

1. **Blind schedule CRUD API** (feature 001-blind-level-management)
   - Status: ✅ Complete and available
   - Repository: BlindScheduleRepository, BlindLevelRepository
   - Routes: /blind-schedules CRUD endpoints
   - Will extend with management-specific endpoints

2. **Mobile app infrastructure**
   - Status: ✅ Existing
   - Settings screen: SettingsScreen.tsx
   - Navigation: React Navigation configured
   - State management: Zustand stores pattern established

3. **Tournament management service** (for deletion validation)
   - Status: ✅ Existing
   - Repository: TournamentRepository
   - Method needed: Check if schedule referenced by active tournament

## Performance Considerations

### Mobile App

- **List rendering**: FlatList with windowing handles 100+ items easily
- **Memory footprint**: ~50KB for 100 schemes (negligible)
- **API latency**: Target <500ms p95 (achievable with proper indexing)

### Controller

- **Database queries**: All queries use indexed columns (blind_schedule_id, level)
- **Pagination support**: Repository already has getLevelsPaginated()
- **Validation overhead**: Zod validation is fast (<1ms per scheme)

## Security Considerations

- **Authentication**: All management endpoints require valid auth token (existing middleware)
- **Authorization**: Tournament director role check (existing implementation)
- **Input validation**: Zod schema enforces constraints (no SQL injection possible)
- **Rate limiting**: Apply existing rate limiter to management endpoints

## Testing Strategy

### Unit Tests

- Validation logic (Zod schemas)
- Repository methods (already tested from 001)
- Sync queue operations

### Integration Tests

- API endpoints (create, update, delete schemes)
- Offline CRUD with sync
- Default scheme protection

### E2E Tests

- Full user flow: view list → create scheme → edit → delete
- Offline scenario: create while offline → verify sync on reconnect

## Open Questions Resolved

All questions from Technical Context have been answered. No NEEDS CLARIFICATION items remain.

## Recommendations

1. **Proceed with Phase 1**: Data model and API contracts can be designed with confidence
2. **Add Zod dependency**: Install in both mobile-app and controller
3. **Reuse existing patterns**: Follow BlindScheduleEditorScreen.tsx patterns for editor UX
4. **Implement offline sync early**: Sync queue affects data model design

## Alternatives Summary

| Decision | Alternative Chosen | Alternative Rejected |
|----------|-------------------|---------------------|
| Settings navigation | Stack Navigator with Settings tab | Modal, separate tab |
| Offline CRUD | AsyncStorage with sync queue | Block offline, local DB |
| List performance | FlatList with windowing | Manual pagination |
| Inline editing | Tap-to-edit rows | Modal, separate screen |
| Validation | Zod schemas | Manual functions |
| Default protection | DB flag + API + UI | Client-side only, soft delete |
| Live preview | Computed from state | Separate screen, on-save only |
