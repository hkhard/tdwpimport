# Quickstart: Blind Level Scheme Management

**Feature**: 002-blind-level-crud
**Created**: 2025-12-28

## Overview

This guide helps you get started with the blind level scheme management feature. It provides setup instructions, common workflows, and troubleshooting tips.

## Prerequisites

### Required Software

- **Node.js**: v20+ (for controller and mobile app)
- **Expo CLI**: `npm install -g expo-cli`
- **iOS Simulator** (Mac only) or **Android Emulator**
- **Git**: For version control

### Existing Features

This feature requires **feature 001-blind-level-management** to be complete:
- Blind schedule database schema
- Blind schedule CRUD API
- Mobile app blind schedule components

## Setup

### 1. Install Dependencies

```bash
# Controller dependencies
cd controller
npm install

# Mobile app dependencies
cd ../mobile-app
npm install

# Shared types
cd ../shared
npm install
```

### 2. Start Controller

```bash
cd controller
npm run dev
# Controller runs on http://localhost:3000
```

Verify health:
```bash
curl http://localhost:3000/api/health
# Expected: {"status":"ok"}
```

### 3. Start Mobile App

```bash
cd mobile-app
npm start
# Press 'i' for iOS simulator or 'a' for Android emulator
```

### 4. Verify API Connection

In mobile app, navigate to **Settings** and verify:
- API URL shows: `http://localhost:3000/api`
- Test connection (manual API call)

## Common Workflows

### Workflow 1: Create a New Blind Scheme

**User Story**: As a tournament director, I want to create a custom blind structure for my weekly game.

**Steps**:
1. Open mobile app
2. Navigate to **Settings** > **Blind Level Management**
3. Tap **Create New Scheme**
4. Enter scheme details:
   - Name: "My Weekly Game"
   - Starting Stack: 10000
   - Break Interval: 5 levels
   - Break Duration: 10 minutes
5. Add blind levels:
   - Level 1: 100/200, no ante, 20 minutes
   - Level 2: 150/300, no ante, 20 minutes
   - Level 3: 200/400, no ante, 20 minutes
   - Continue adding levels...
6. Tap **Save Scheme**
7. Verify scheme appears in list with correct level count and duration

**Success Criteria**:
- Scheme saves without errors
- Scheme appears in list immediately
- All levels show correctly in preview

### Workflow 2: Edit Existing Scheme

**User Story**: As a tournament director, I want to modify an existing scheme's ante amounts.

**Steps**:
1. Navigate to **Settings** > **Blind Level Management**
2. Tap on desired scheme
3. Tap **Edit Scheme**
4. Modify level(s):
   - Add ante to Level 5: 25
   - Add ante to Level 6: 50
5. Observe live preview updates
6. Tap **Save Scheme**
7. Verify changes persist

**Success Criteria**:
- Changes save immediately
- Preview reflects edits in real-time
- Modified scheme shows correct ante amounts

### Workflow 3: Duplicate Default Scheme

**User Story**: As a tournament director, I want to create a custom version of the Turbo structure.

**Steps**:
1. Navigate to **Settings** > **Blind Level Management**
2. Tap on **Turbo** scheme
3. Tap **Edit Scheme**
4. Make a change (e.g., adjust duration)
5. Tap **Save**
6. Observe alert: "Creating copy of default scheme..."
7. Verify new scheme appears: "Turbo (Copy)"

**Success Criteria**:
- Original Turbo scheme unchanged
- New copy created with modifications
- Both schemes appear in list

### Workflow 4: Delete Custom Scheme

**User Story**: As a tournament director, I want to remove a scheme I no longer use.

**Steps**:
1. Navigate to **Settings** > **Blind Level Management**
2. Tap on custom scheme
3. Tap **Delete Scheme**
4. Confirm deletion
5. Verify scheme removed from list

**Success Criteria**:
- Scheme permanently deleted
- List no longer shows deleted scheme
- No error messages

### Workflow 5: Offline CRUD

**User Story**: As a tournament director, I want to manage schemes while offline (no internet connection).

**Steps**:
1. Disable device internet connection
2. Navigate to **Settings** > **Blind Level Management**
3. Observe cached schemes load
4. Create new scheme (follow Workflow 1)
5. Verify scheme saves locally
6. Re-enable internet connection
7. Observe sync notification: "Changes synced"
8. Verify scheme appears on other devices

**Success Criteria**:
- Can view cached schemes offline
- Can create/edit schemes offline
- Changes sync automatically when online
- No data loss

## API Testing

### Get All Schemes

```bash
curl http://localhost:3000/api/blind-schedules
```

Expected response:
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid-1",
      "name": "Turbo",
      "description": null,
      "startingStack": 10000,
      "levelCount": 15,
      "totalDurationMinutes": 180,
      "isDefault": true
    }
  ]
}
```

### Get Single Scheme

```bash
curl http://localhost:3000/api/blind-schemes/{schemeId}
```

### Create Scheme

```bash
curl -X POST http://localhost:3000/api/blind-schemes \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Scheme",
    "startingStack": 10000,
    "breakInterval": 5,
    "breakDuration": 10,
    "levels": [
      {
        "smallBlind": 100,
        "bigBlind": 200,
        "ante": 0,
        "duration": 20,
        "isBreak": false
      }
    ]
  }'
```

### Update Scheme

```bash
curl -X PUT http://localhost:3000/api/blind-schemes/{schemeId} \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name"
  }'
```

### Delete Scheme

```bash
curl -X DELETE http://localhost:3000/api/blind-schemes/{schemeId}
```

## Troubleshooting

### Issue: "Cannot connect to API"

**Symptoms**: Mobile app shows "Failed to load blind schemes"

**Solutions**:
1. Verify controller is running: `curl http://localhost:3000/api/health`
2. Check mobile app Settings > API URL matches controller address
3. For physical device testing, use local network IP (not localhost):
   - Mac: Find IP in System Settings > Network
   - Update mobile app API URL to: `http://192.168.1.X:3000/api`

### Issue: "Validation failed"

**Symptoms**: Save fails with validation errors

**Common causes**:
- **Level numbers not sequential**: Ensure levels are numbered 1, 2, 3...
- **Big blind < small blind**: e.g., smallBlind: 200, bigBlind: 100 (invalid)
- **Break level has blinds**: Break levels must have smallBlind=0, bigBlind=0
- **Duplicate scheme name**: Scheme names are case-insensitive unique

**Solution**: Review error messages and fix validation issues

### Issue: "Cannot delete default scheme"

**Symptoms**: Delete button disabled or error when deleting Turbo/Standard/Deep Stack

**Solution**: This is expected behavior (FR-006). Default schemes are protected. To customize, duplicate the scheme first.

### Issue: "Scheme in use by active tournament"

**Symptoms**: Cannot delete scheme that's referenced by a tournament

**Solution**: Delete/archive the tournament first, or use a different blind scheme for the tournament.

### Issue: "Changes not syncing"

**Symptoms**: Offline changes don't appear on other devices

**Solutions**:
1. Verify internet connection is active
2. Pull to refresh in mobile app
3. Check sync queue: `AsyncStorage.getItem('sync_queue')`
4. Restart mobile app

## Performance Tips

### List Performance

- **100+ schemes**: Enable pagination (PAGE_SIZE = 20)
- **Large level counts**: Use FlatList windowing
- **Slow queries**: Check database has proper indexes:
  - `idx_blind_schedules_name`
  - `idx_blind_levels_schedule_level`

### Offline Sync

- **Clear sync queue**: If queue gets too large, manually clear and re-fetch
- **Batch operations**: Group multiple edits into single API call
- **Debounce validation**: Don't validate on every keystroke

## Development Notes

### File Locations

**Mobile App**:
- Screens: `mobile-app/src/screens/BlindScheme*Screen.tsx`
- API: `mobile-app/src/services/api/blindScheduleApi.ts`
- Store: `mobile-app/src/stores/blindSchemeStore.ts`

**Controller**:
- Routes: `controller/src/api/routes/blindSchedules.ts`
- Service: `controller/src/services/blindSchedule/BlindScheduleService.ts`

**Shared Types**:
- Types: `shared/src/types/timer.ts`

### Testing

```bash
# Run controller tests
cd controller
npm test

# Run mobile app tests
cd mobile-app
npm test
```

### Database Inspection

```bash
# View blind_schedules table
cd controller
sqlite3 data/tournaments.db "SELECT * FROM blind_schedules;"

# View blind_levels for a scheme
sqlite3 data/tournaments.db "SELECT * FROM blind_levels WHERE blind_schedule_id = 'uuid';"
```

## Next Steps

After completing this quickstart:

1. **Review full specification**: See `spec.md` for all user stories
2. **Review API contracts**: See `contracts/blind-schemes-api.yaml` for all endpoints
3. **Review data model**: See `data-model.md` for entity relationships
4. **Start implementation**: Run `/speckit.tasks` to generate task list

## Support

- **Documentation**: See `CLAUDE.md` for project guidance
- **Constitution**: See `.specify/memory/constitution.md` for core principles
- **Issues**: Report bugs or questions via GitHub issues
