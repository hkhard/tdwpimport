# Data Model: Season Points Formula Honor Active Formula

**Feature**: 012-season-points-formula-fix
**Date**: 2026-01-04
**Note**: No database schema changes - this is a bug fix to existing calculation logic

## Entity Relationships

### No New Entities

This feature fixes incorrect calculation logic for existing entities. No new entities, tables, or data structures are created.

### Modified Behavior

**Player Season Standings** (existing entity)

**Current (Incorrect) Behavior**:
```
PlayerSeasonData {
    player_id: int
    season_id: int
    total_points: float        // Simple sum of all tournaments (UNCHANGED)
    season_points: float       // Currently equals total_points (BUG)
    tournaments_played: int
    // ... other stats
}
```

**Fixed Behavior**:
```
PlayerSeasonData {
    player_id: int
    season_id: int
    total_points: float        // Simple sum of all tournaments (UNCHANGED)
    season_points: float       // Formula-calculated (FIXED - now different from total_points)
    tournaments_played: int
    // ... other stats
}
```

## Data Flow

### Input Data Sources

1. **WordPress Options** (active formula configuration)
   ```
   Option: tdwp_active_season_formula (or poker_active_season_formula)
   Type: string
   Default: "best_10"
   Examples: "best_10", "best_5", "season_total", "direct_sum"
   ```

2. **Tournament Results** (from database)
   ```
   Table: wp_poker_tournament_players
   Fields: player_id, points, finish_position, winnings, hits
   Filter: season_id
   ```

### Calculation Flow

#### Current (Broken) Flow

```
For each tournament result:
    1. Extract single tournament points value
    2. Evaluate season formula with single value
    3. Accumulate: season_points += formula_result

Final: season_points = sum(formula(tournament_1), formula(tournament_2), ...)
```

**Problem**: Formula receives one value at a time, cannot sort/select top N

#### Fixed Flow

```
1. Collect ALL tournament results for player in season
2. Extract tournament points into ARRAY: [100.5, 85.0, 92.3, ...]
3. Build formula input variables:
    {
        tournament_points: [100.5, 85.0, 92.3, ...],  // ARRAY
        total_tournaments: 18,
        best_finish: 1,
        worst_finish: 15,
        hits: 36,
        winnings: 1500.00,
        ...
    }
4. Evaluate formula ONCE with complete array
5. Formula operations:
    - Sort array descending: [100.5, 95.0, 92.3, 88.0, ...]
    - Slice top 10: [100.5, 95.0, ...] (first 10 elements)
    - Sum: 850.5
6. Return season_points = 850.5

Final: season_points = formula_result
```

## Formula Variables Schema

### Input Variables Structure

```php
$formula_input = [
    // REQUIRED VARIABLES
    'tournament_points' => array<float>,  // All tournament point values
    'total_tournaments' => int,            // Count of tournaments in season

    // OPTIONAL VARIABLES (for formula dependencies)
    'best_finish' => int,                  // Best finishing position (1 = 1st place)
    'worst_finish' => int,                 // Worst finishing position
    'hits' => int,                         // Total hits (per formula)
    'winnings' => float,                   // Total winnings
    'avg_finish' => float,                 // Average finishing position
    'first_place_count' => int,            // Number of 1st place finishes
    'top_3_count' => int,                  // Number of top 3 finishes
    'top_5_count' => int,                  // Number of top 5 finishes
    'bubble_count' => int,                 // Number of bubble finishes
    'last_place_count' => int,             // Number of last place finishes
];
```

### Formula Output

```php
$formula_result = [
    'success' => bool,
    'result' => float,    // Calculated season points
    'error' => string|null // Error message if failed
];
```

## Formula Examples

### Best 10 Formula

**Formula Key**: `best_10`

**Dependencies**:
```
sort_points = sort(tournament_points, descending)
```

**Formula**:
```
sum(slice(sort_points, 0, 10))
```

**Behavior**:
1. Sort tournament_points array descending
2. Take first 10 elements (top 10)
3. Sum them

**Edge Case**: If player has only 5 tournaments, formula uses all 5 (slice handles array bounds)

### Season Total Formula

**Formula Key**: `season_total` or `direct_sum`

**Dependencies**: None

**Formula**:
```
sum(tournament_points)
```

**Behavior**: Sum all tournament points (same as Points column)

## State Transitions

### No State Changes

This feature fixes calculation logic only. No state transitions or entity lifecycle changes.

### Cache Invalidation

**WordPress Transient Cache**:
```
Key: poker_season_standings_{season_id}
Value: Serialized season standings data
Invalidation: When active formula changes OR after tournament import/update
```

## Validation Rules

### Input Validation

1. **Active Formula**:
   - Must exist in formula definitions
   - If NULL or invalid: Default to "direct_sum" (simple sum)
   - If option not set: Use default "best_10"

2. **Tournament Points Array**:
   - Must not be empty (player with 0 tournaments)
   - Each element must be numeric (float)
   - Filter out NULL values before passing to formula

3. **Formula Variables**:
   - All required variables must be present
   - Arrays must have at least 1 element
   - Numeric values must be >= 0

### Output Validation

1. **Season Points**:
   - Must be numeric (float)
   - Must be >= 0
   - Must be <= total_points (when formula filters results)
   - Can equal total_points (when formula sums all results)

2. **Points vs Season Points Relationship**:
   - Points >= Season Points (when formula filters/subsets)
   - Points = Season Points (when formula sums all)
   - Points can be < Season Points only if formula has multiplier/bonus (not in current formulas)

## Error Handling

### Edge Cases

1. **Player with fewer tournaments than formula requires**:
   - Example: 5 tournaments when formula is "best_10"
   - Handling: Formula uses available tournaments (array slice handles bounds)
   - Result: Season Points = sum of all 5 tournaments

2. **No active formula set**:
   - Option is NULL or empty string
   - Handling: Default to "direct_sum" (simple sum)
   - Result: Season Points = total_points

3. **Invalid formula key**:
   - Formula doesn't exist in definitions
   - Handling: Default to "direct_sum"
   - Result: Season Points = total_points

4. **Formula evaluation fails**:
   - Formula syntax error or missing variables
   - Handling: Log error, default to "direct_sum"
   - Result: Season Points = total_points

5. **Player with no tournaments**:
   - tournament_points array is empty
   - Handling: Return 0 for season_points
   - Result: Season Points = 0

## Data Integrity

### Constraints

1. **Formula Calculation Idempotency**:
   - Same inputs → same outputs
   - Formula calculation must be deterministic
   - No side effects during calculation

2. **Performance Constraints**:
   - Season leaderboard must render in < 5 seconds for 100+ players
   - Formula evaluation must be efficient
   - Cache results in transients

3. **Accuracy Constraints**:
   - Manual calculation must match formula result
   - Formula result must be repeatable
   - No floating point precision issues (use round() if needed)
