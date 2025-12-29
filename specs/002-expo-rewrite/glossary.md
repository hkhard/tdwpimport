# Terminology Glossary

**Purpose**: Standardize naming conventions across codebase, API contracts, and documentation.

## Device Identifier

| Context | Term | Format | Example |
|---------|------|--------|---------|
| Code (TypeScript) | `deviceId` | camelCase | `deviceId: string` |
| API Contracts (OpenAPI) | `device_id` | snake_case | `"device_id": "uuid-123"` |
| Database Schema | `deviceId` | camelCase | `deviceId TEXT` |
| Documentation | "device ID" | sentence case | "The device ID uniquely identifies..." |

**Rationale**:
- TypeScript code uses camelCase per constitution IV (TypeScript Discipline)
- API contracts use snake_case per OpenAPI conventions
- Database uses camelCase for consistency with repository layer
- Automatic conversion occurs at API boundaries via middleware

## Time Measurements

| Context | Term | Format | Example |
|---------|------|--------|---------|
| Code | `elapsedTime` | camelCase | `elapsedTime: number` (milliseconds) |
| API | `elapsed_time` | snake_case | `"elapsed_time": 450000` |
| Database | `elapsedTime` | camelCase | `elapsedTime INTEGER` |

**Rationale**: Same pattern as deviceId - camelCase in code, snake_case in API.

## Sync Timestamps

| Context | Term | Format | Example |
|---------|------|--------|---------|
| Code | `serverTimestamp` | camelCase | `serverTimestamp: Date` |
| API | `server_timestamp` | snake_case | `"server_timestamp": "2025-12-26T15:30:00Z"` |
| Database | `serverTimestamp` | camelCase | `serverTimestamp TEXT` |

## Tournament Identifiers

| Context | Term | Format | Example |
|---------|------|--------|---------|
| Code | `tournamentId` | camelCase | `tournamentId: string` |
| API | `tournament_id` | snake_case | `"tournament_id": "abc-123"` |
| Database | `tournamentId` | camelCase | `tournamentId TEXT PRIMARY KEY` |

## Player Identifiers

| Context | Term | Format | Example |
|---------|------|--------|---------|
| Code | `playerId` | camelCase | `playerId: string` |
| API | `player_id` | snake_case | `"player_id": "player-456"` |
| Database | `playerId` | camelCase | `playerId TEXT` |

## Conversion Layer

API boundary middleware handles automatic conversion:

```typescript
// Request: snake_case → camelCase
function convertRequest<T>(input: T): CamelCase<T> {
  return snakeCaseToCamelCase(input);
}

// Response: camelCase → snake_case
function convertResponse<T>(input: T): SnakeCase<T> {
  return camelCaseToSnakeCase(input);
}
```

## Naming Rules

1. **All TypeScript code**: camelCase (variables, properties, methods)
2. **All API contracts**: snake_case (request/response bodies)
3. **All database columns**: camelCase (matches repository layer)
4. **All constants**: UPPER_SNAKE_CASE (e.g., `MAX_TOURNAMENTS`)
5. **All TypeScript types/interfaces**: PascalCase (e.g., `Tournament`, `TimerState`)
6. **All React components**: PascalCase (e.g., `TimerDisplay`, `PlayerList`)
