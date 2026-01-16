# Research: Season Points Formula Honor Active Formula

**Feature**: 012-season-points-formula-fix
**Date**: 2026-01-04
**Focus**: Understand current bug and identify correct fix approach

## Problem Statement

Season Points column displays same value as Points column, ignoring configured active season formula (e.g., "best_10" should count only top 10 results but currently counts all tournaments).

## Code Analysis Findings

### 1. Current Implementation (INCORRECT)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`
**Method**: `calculate_player_series_data()` (lines 76-428)
**Problem Area**: Lines 287-329

```php
// US3: Evaluate season formula per tournament with tournament-specific variables
if ($formula_key && $formula_key !== 'direct_sum') {
    foreach ($results as $result) {
        // ... accumulate totals

        // US3: Evaluate season formula for this specific tournament
        $tournament_season_points = $this->evaluate_season_formula_per_tournament(
            $formula_key,
            $result,
            $tournaments
        );
        $season_points += $tournament_season_points;  // Line 309 - WRONG!
    }
}
```

**Issue**: Formula is evaluated per-tournament with single value, then results are summed. This breaks formulas like `best_10` that need to see ALL tournament points at once to sort and select top 10.

### 2. Formula Evaluation Function

**Location**: `class-series-standings.php:564-603`

```php
private function evaluate_season_formula_per_tournament($formula_key, $result, $tournaments) {
    // Get tournament-specific variables
    $variables = $this->get_tournament_variables($result, $tournaments);

    // Build complete formula with dependencies
    $complete_formula = '';
    if (!empty($formula_data['dependencies'])) {
        $complete_formula = implode(';', $formula_data['dependencies']) . ';';
    }
    $complete_formula .= $formula_data['formula'];

    // Calculate
    $result_calc = $formula_validator->calculate_formula(
        $complete_formula,
        $variables,  // Single tournament variables only - WRONG!
        'season'
    );

    return floatval($result_calc['result']);
}
```

**Problem**: `$variables` contains only data from ONE tournament, not the complete array.

### 3. Correct Implementation Pattern (REFERENCE)

**File**: `wordpress-plugin/poker-tournament-import/includes/class-statistics-engine.php`
**Method**: `get_player_season_points()` (lines 1220-1346)

```php
private function get_player_season_points($player_id, $season_id = null) {
    // Get configured season formula
    $formula_key = get_option('tdwp_active_season_formula', 'best_10');

    // Build query to get ALL tournament points for player
    $query = "SELECT tp.points, tp.winnings, tp.hits, tp.finish_position
             FROM {$this->players_table} tp
             WHERE tp.player_id = %s";
    // ... season filtering

    $results = $wpdb->get_results($wpdb->prepare($query, $params));

    // Collect data into ARRAY
    $tournament_points = array();
    foreach ($results as $result) {
        $tournament_points[] = floatval($result->points);  // ARRAY of all points
        // ... other aggregations
    }

    // Apply formula ONCE with ALL tournament data
    if ($formula_key && $formula_key !== 'direct_sum') {
        $formula_input = array(
            'tournament_points' => $tournament_points,  // ARRAY of all points
            'total_tournaments' => $tournaments_played,
            // ... other aggregated stats
        );

        // Execute formula with combined dependencies + main formula
        $combined_formula = implode("\n", $formula_data['dependencies']) . "\n" . $formula_data['formula'];
        $result = $formula_validator->calculate_formula($combined_formula, $formula_input, 'season');

        if ($result['success']) {
            return floatval($result['result']);
        }
    }

    // Fallback: sum all points
    return $total_points;
}
```

**Key Insight**: Formula receives ARRAY of all tournament points, can sort and select top N.

### 4. Existing Correct Method (NOT BEING USED)

**File**: `class-series-standings.php`
**Method**: `apply_series_formula()` (lines 433-500)

```php
private function apply_series_formula($series_data, $formula_key) {
    // This method already exists and has correct structure
    // It receives $series_data['tournament_points'] as an ARRAY
    // It passes this array to formula validator for proper evaluation

    $formula_validator = new Poker_Tournament_Formula_Validator();
    $formula_data = $formula_validator->get_formula($formula_key, 'season');

    // Build formula input with ARRAY
    $formula_input = array(
        'tournament_points' => $series_data['tournament_points'],  // ARRAY!
        'total_tournaments' => count($series_data['tournament_points']),
        // ... other stats
    );

    // Calculate formula ONCE with complete data
    $result = $formula_validator->calculate_formula(
        $complete_formula,
        $formula_input,  // Array of all points
        'season'
    );

    return floatval($result['result']);
}
```

**Status**: This method is called at line 416 but the per-tournament loop (lines 287-329) has already calculated `$season_points` incorrectly, so this correct calculation is overwritten.

## Decision

**Remove lines 287-329** (per-tournament evaluation loop) and rely on existing `apply_series_formula()` method.

**Justification**:
1. `apply_series_formula()` already exists and works correctly
2. It passes tournament_points array to formula validator
3. Formula validator supports array operations (sort, slice, sum)
4. Per-tournament loop breaks the calculation by summing individual formula results

**Alternative Considered and Rejected**:
- Fix per-tournament loop to accumulate without formula: Won't work because formula needs to see all points for sorting
- Create new method: Unnecessary since correct method already exists
- Modify formula validator: Already supports required operations

## Data Flow Comparison

### Current (BROKEN)
```
Tournament 1 (100 pts) → Formula → 100
Tournament 2 (85 pts)  → Formula → 85
Tournament 3 (92 pts)  → Formula → 92
...
Sum: 277 (WRONG - formula saw 1 value at a time)
```

### Fixed (CORRECT)
```
Collect: [100, 85, 92, 88, 95, ...]
Sort: [100, 95, 92, 88, 85, ...]
Slice top 10: [100, 95, 92, 88, 85, ...]
Sum: 550 (CORRECT - formula saw all values, sorted, selected top 10)
```

## Formula Variables Contract

**Input to Formula Validator**:
```php
$variables = [
    'tournament_points' => array[float],  // REQUIRED: All tournament points
    'total_tournaments' => int,            // REQUIRED: Count of tournaments
    'best_finish' => int,
    'worst_finish' => int,
    'hits' => int,
    'winnings' => float,
    // ... other aggregated stats
];
```

**Best 10 Formula Example**:
```
Dependencies:
  sort_points = sort(tournament_points, descending)

Formula:
  sum(slice(sort_points, 0, 10))
```

## Testing Strategy

**Manual Testing Required** (per spec):
1. Set active formula to "best_10"
2. Create season with players having 15+ tournaments
3. View season leaderboard shortcode
4. Verify Season Points < Points for players with 10+ tournaments
5. Manual calculation: Sum top 10 points, compare to Season Points column

**Edge Cases**:
- Player with 5 tournaments (fewer than best_10): Should use all 5
- Player with 10 tournaments: Should use all 10
- Player with 18 tournaments: Should use best 10
- No active formula set: Season Points = Points (simple sum)
- NULL formula: Default to simple sum
