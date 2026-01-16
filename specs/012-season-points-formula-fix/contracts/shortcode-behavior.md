# Contract: Season Leaderboard Shortcode Behavior

**Feature**: 012-season-points-formula-fix
**Component**: Season Leaderboard Shortcode
**Version**: Post-fix implementation

## Shortcode Interface

### Shortcode Tag

```
[season_leaderboard season_id="5" formula="best_10" show_details="true"]
```

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `season_id` | int | Yes | - | WordPress term ID for season |
| `formula` | string | No | Active formula | Formula key to use (e.g., "best_10", "season_total") |
| `show_details` | bool | No | false | Show detailed statistics columns |
| `limit` | int | No | 20 | Limit number of players (when show_details=false) |

## Output Contract

### HTML Table Structure

```html
<table class="season-leaderboard">
  <thead>
    <tr>
      <th>Rank</th>
      <th>Player</th>
      <th>Points</th>          <!-- Simple sum of ALL tournaments -->
      <th>Played</th>
      <th>Best</th>
      <th>Avg</th>
      <th>1st</th>
      <th>Top 3</th>
      <th>Top 5</th>
      <th>Bubble</th>
      <th>Last</th>
      <th>Hits</th>
      <th>Season Points</th>  <!-- Formula-calculated (FIXED) -->
    </tr>
  </thead>
  <tbody>
    <!-- One row per player -->
    <tr>
      <td>1</td>
      <td>Fredrik Y</td>
      <td>2,500.0</td>        <!-- Points: sum of all 18 tournaments -->
      <td>18</td>
      <td>1</td>
      <td>5.3</td>
      <td>1</td>
      <td>7</td>
      <td>11</td>
      <td>0</td>
      <td>0</td>
      <td>36</td>
      <td>2,043.0</td>      <!-- Season Points: sum of best 10 (FIXED) -->
    </tr>
  </tbody>
</table>
```

### Column Specifications

#### Points Column

**Purpose**: Baseline metric showing total performance across ALL tournaments
**Calculation**: Simple sum of all tournament points
**Formula**:
```
Points = Σ(tournament_points[i]) for i = 1 to N
```
**Behavior**:
- Unaffected by active season formula
- Always sums all tournaments regardless of formula
- Used for comparison with formula-based Season Points

**Example**:
```
Player: Fredrik Y
Tournaments: 18
Points: 2,500.0 (sum of all 18 tournament results)
```

#### Season Points Column (FIXED)

**Purpose**: Formula-calculated season ranking based on active formula
**Calculation**: Apply active season formula to complete tournament_points array
**Formula**:
```
IF active_formula = "best_10":
    sorted_points = sort(tournament_points, descending)
    Season_Points = sum(sorted_points[0:10])  // Top 10 only
ELSE IF active_formula = "season_total":
    Season_Points = sum(tournament_points)  // All tournaments
ELSE IF active_formula = "direct_sum":
    Season_Points = sum(tournament_points)  // All tournaments
```
**Behavior**:
- Respects configured active season formula
- Can be less than Points (when formula filters results)
- Can equal Points (when formula sums all results)

**Example**:
```
Player: Fredrik Y
Tournaments: 18
Active Formula: best_10
Points: 2,500.0 (all 18)
Season Points: 2,043.0 (best 10 only) ← FIXED: Now different from Points
```

## Formula Resolution Logic

### Formula Priority Order

1. **Explicit shortcode parameter**: `[season_leaderboard formula="best_10"]`
2. **Active formula option**: `get_option('tdwp_active_season_formula', 'best_10')`
3. **Default fallback**: `'best_10'`

### Code Flow

```php
// 1. Get formula key from shortcode or active option
$formula_key = !empty($atts['formula'])
    ? sanitize_text_field($atts['formula'])
    : get_option('tdwp_active_season_formula', 'best_10');

// 2. Validate formula exists
if (!formula_exists($formula_key)) {
    $formula_key = 'direct_sum';  // Fallback to simple sum
}

// 3. Calculate season points using formula
$season_points = calculate_season_points(
    $player_id,
    $season_id,
    $formula_key
);

// 4. Display in table
echo number_format($season_points, 1);
```

## Calculation Contract

### Input Requirements

**Season ID**:
- Type: int
- Must be valid WordPress term ID
- Must have tournaments associated

**Player Data**:
- Minimum 1 tournament result required
- Each result must have `points` field (numeric)
- NULL points filtered out before calculation

**Formula**:
- Must be registered in formula definitions
- Must have valid syntax
- Must support `tournament_points` array variable

### Output Guarantees

**Return Value**:
- Type: float
- Range: >= 0
- Precision: 1 decimal place

**Error Handling**:
- Formula evaluation failure → Return simple sum
- Empty tournament array → Return 0
- Invalid formula key → Use "direct_sum" fallback

### Performance Contract

**Calculation Time**:
- Target: < 100ms per player
- With 100 players: < 10 seconds total
- With caching: < 5 seconds (subsequent loads)

**Caching Strategy**:
```
Key: poker_season_standings_{season_id}_{formula_key}
Duration: 12 hours (or until formula changes)
Invalidation: Tournament import/update/delete
```

## Test Scenarios

### Scenario 1: Best 10 Formula with 18 Tournaments

**Input**:
```
Player: Fredrik Y
Season: 2025
Tournaments: 18
Active Formula: best_10
Tournament Points: [100, 85, 92, 88, 95, 78, 90, 87, 93, 82, 89, 86, 91, 84, 80, 88, 85, 90]
```

**Expected Output**:
```
Points: 1,611.0 (sum of all 18)
Season Points: 915.0 (sum of best 10: 100+95+93+92+91+90+90+88+88+87)
```

**Assertion**: Season Points < Points

### Scenario 2: Best 10 Formula with 5 Tournaments

**Input**:
```
Player: Joakim H
Season: 2025
Tournaments: 5
Active Formula: best_10
Tournament Points: [100, 85, 92, 88, 95]
```

**Expected Output**:
```
Points: 460.0 (sum of all 5)
Season Points: 460.0 (sum of all 5 - fewer than 10, so use all)
```

**Assertion**: Season Points = Points

### Scenario 3: Season Total Formula

**Input**:
```
Player: Any
Season: 2025
Tournaments: 15
Active Formula: season_total
Tournament Points: [100, 85, 92, ...]
```

**Expected Output**:
```
Points: 1,200.0 (sum of all 15)
Season Points: 1,200.0 (sum of all 15 - season_total sums all)
```

**Assertion**: Season Points = Points

## Integration Contract

### Dependencies

**Formula Manager**: `Poker_Tournament_Active_Formula_Manager`
- Method: `get_active_formula($category)`
- Returns: Formula key string
- Fallback: Default formula if not configured

**Formula Validator**: `Poker_Tournament_Formula_Validator`
- Method: `calculate_formula($formula, $variables, $category)`
- Input: Formula string, variables array, category string
- Output: Array with 'success', 'result', 'error' keys

**Series Standings**: `Poker_Series_Standings`
- Method: `calculate_player_series_data($player_id, $season_id, $formula_key)`
- Input: Player ID, season ID, formula key
- Output: Array with player stats including 'series_points' (Season Points)

### Data Flow Contract

```
1. Shortcode handler receives request
   ↓
2. Get active formula from Formula Manager
   ↓
3. Call Series_Standings::calculate_player_series_data()
   ↓
4. Series_Standings collects tournament results
   ↓
5. Series_Standings builds tournament_points array
   ↓
6. Series_Standings calls apply_series_formula() with array
   ↓
7. apply_series_formula() calls Formula_Validator::calculate_formula()
   ↓
8. Formula_Validator evaluates formula with array
   ↓
9. Formula_Validator returns calculated result
   ↓
10. Series_Standings returns player data with series_points
   ↓
11. Shortcode renders table with Season Points column
```

## Version History

**v1.0 (Pre-fix)**: Season Points equals Points (bug)
**v1.1 (Post-fix)**: Season Points uses formula calculation (fixed)

**Breaking Changes**: None - this is a bug fix, not a feature change
