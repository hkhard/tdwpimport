# Data Model: Active Formula Selection

**Feature**: 009-active-formula-selection
**Date**: 2025-01-02

## Overview

This document describes the data model changes required to support active formula selection for tournament and season points calculations.

## WordPress Options

### New Options

#### `poker_active_tournament_formula`

Stores the formula key of the currently active tournament points formula.

```php
// Option Structure
string formula_key  // e.g., "default_td_formula", "custom_points_v1"

// Default Value
null  // No active formula until explicitly set

// Example Usage
$active_formula = get_option('poker_active_tournament_formula', null);
if ($active_formula) {
    $formula = get_formula_by_key($active_formula);
    // Use formula for tournament points calculation
}
```

**Validation Rules**:
- Must match an existing formula_key in `tdwp_tournament_formulas`
- Formula must have category 'points'
- If referenced formula is deleted, option should be cleared or reset

**Lifecycle**:
- Created: User selects active formula in formula manager
- Updated: User changes active formula selection
- Deleted: When all formulas deleted or plugin uninstalled

---

#### `poker_active_season_formula`

Stores the formula key of the currently active season standings formula.

```php
// Option Structure
string formula_key  // e.g., "season_points_v1", "performance_score"

// Default Value
null  // Falls back to direct sum of tournament points

// Example Usage
$active_formula = get_option('poker_active_season_formula', null);
if ($active_formula) {
    $season_points = calculate_season_points($player_results, $active_formula);
} else {
    $season_points = array_sum($player_results['tournament_points']);
}
```

**Validation Rules**:
- Must match an existing formula_key in `tdwp_tournament_formulas`
- Formula should have category 'season' (but can use any category for flexibility)
- If referenced formula is deleted, option should be cleared or reset

**Lifecycle**:
- Created: User selects active formula in formula manager
- Updated: User changes active formula selection
- Deleted: When all formulas deleted or plugin uninstalled

---

## Formula Object Extension

### Existing Schema

Located in `tdwp_tournament_formulas` option:

```php
[
    'formula_key' => string,        // Auto-generated slug
    'display_name' => string,        // Human-readable name
    'description' => string,         // Formula description
    'category' => string,            // 'points', 'season', 'custom'
    'dependencies' => array,         // TD variable assignments
    'formula' => string,             // Main TD formula expression
    'is_default' => bool             // Default formula flag
]
```

### Proposed Addition

**No changes required to formula schema itself.**

Active status is stored separately in WordPress options (see above) to:
- Keep formula definitions pure
- Allow easy switching without modifying formula objects
- Support multiple "active" formulas for different purposes

---

## Tournament Post Meta

### Existing Fields

#### `tournament_uuid`

- **Type**: string (UUID)
- **Purpose**: Unique identifier from Tournament Director
- **Source**: Parsed from .tdt file
- **Status**: ✅ Already implemented

#### `paid_positions`

- **Type**: integer
- **Purpose**: Number of players who receive prize money
- **Source**: Parsed from .tdt file (prize structure section)
- **Status**: ⚠️ MAY NOT BE SET - Root cause of bubble=0 issue

### Verification Required

```php
// Check if paid_positions is being set
$paid_positions = get_post_meta($tournament_id, 'paid_positions', true);

// Expected: integer > 0
// Actual: May be empty string or 0 (needs investigation)
```

**Action Item**: Verify parser populates this field during .tdt import

---

## Season Leaderboard Data Structure

### Current Implementation

Season standings calculated per-player:

```php
$series_data = [
    'player_id' => string,
    'player_name' => string,
    'player_url' => string,
    'tournaments_played' => int,
    'total_points' => float,           // Sum of tournament points
    'total_winnings' => float,
    'total_hits' => int,
    'bubble_count' => int,             // ISSUE: Currently always 0
    'last_place_count' => int,
    'best_finish' => int,
    'worst_finish' => int,
    'avg_finish' => float,
    'tournament_points' => array,      // Individual tournament points
    'finishes' => array,               // Individual finish positions
    'results_detail' => array          // Full tournament results
];
```

### Enhancement for Active Season Formula

**New Field**: `season_points` (calculated using active formula)

```php
$series_data['season_points'] = calculate_season_points_with_formula(
    $series_data,
    $active_formula_key
);
```

**Calculation Flow**:

```
For each player:
    For each tournament result:
        Gather tournament variables (n, r, hits, monies, avgBC, T33, T80)
        Evaluate season formula with these variables
        Add to season_points total
```

**Variable Resolution**:
- Undefined variables → 0 (graceful degradation)
- Missing tournaments → Skip (don't penalize)
- Formula syntax errors → Return 0 and log warning

---

## Cache Keys

### Transient Keys

#### `poker_season_standings_{series_id}_{formula_key}`

Stores calculated season standings for a series/formula combination.

```php
$cache_key = 'poker_season_standings_' . $series_id . '_' . $active_formula_key;
$cached = get_transient($cache_key);

if (false === $cached) {
    $standings = calculate_season_standings($series_id, $active_formula_key);
    set_transient($cache_key, $standings, HOUR_IN_SECONDS);
}
```

**Cache Invalidation**:
- When active season formula changes: `delete_transient('poker_season_standings_' . $series_id . '_*')`
- When tournament is added/updated: Delete affected series cache
- Manual "Refresh Statistics" button: Clear all season standings transients

---

## AJAX Request/Response Models

### Set Active Formula

**Request**:

```javascript
POST /wp-admin/admin-ajax.php
action: set_active_formula
nonce: poker_formula_manager_nonce
security: <nonce_value>

{
    "formula_key": "custom_points_v1",
    "category": "tournament"  // or "season"
}
```

**Response** (Success):

```json
{
    "success": true,
    "data": {
        "message": "Active formula updated",
        "active_formula": "custom_points_v1",
        "category": "tournament"
    }
}
```

**Response** (Error):

```json
{
    "success": false,
    "data": {
        "message": "Formula not found or invalid category"
    }
}
```

---

### Get Active Formula

**Request**:

```javascript
POST /wp-admin/admin-ajax.php
action: get_active_formula
nonce: poker_formula_manager_nonce
security: <nonce_value>

{
    "category": "tournament"  // or "season"
}
```

**Response**:

```json
{
    "success": true,
    "data": {
        "active_formula": "custom_points_v1",
        "display_name": "Custom Points V1",
        "category": "tournament"
    }
}
```

---

## State Transition Diagrams

### Active Formula Selection

```
                    ┌─────────────────┐
                    │  No Formula     │
                    │  (null)         │
                    └────────┬────────┘
                             │
                   User selects formula
                             ↓
                    ┌─────────────────┐
                    │  Formula Active │
                    │  (formula_key)  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
    User changes    Formula deleted    Plugin reset
    to formula A   (active one)       (uninstall)
              │              │              │
              └──────────────┴──────────────┘
                             ↓
                    ┌─────────────────┐
                    │  Update State   │
                    │  - New formula  │
                    │  - Clear cache  │
                    │  - Or set null  │
                    └─────────────────┘
```

### Season Points Calculation

```
                    ┌─────────────────┐
                    │ Leaderboard     │
                    │ View Requested   │
                    └────────┬────────┘
                             │
                             ↓
                    ┌─────────────────┐
                    │ Check Cache     │
                    │ (transient)     │
                    └────────┬────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
           Cache Hit                 Cache Miss
                │                         │
                ↓                         ↓
        ┌───────────────┐       ┌─────────────────┐
        │ Return Cached │       │ Get Active      │
        │ Standings     │       │ Season Formula  │
        └───────────────┘       └────────┬────────┘
                                        │
                              ┌─────────┴─────────┐
                              │                   │
                         Has Formula         No Formula
                              │                   │
                              ↓                   ↓
                    ┌──────────────┐    ┌──────────────┐
                    │ Calculate    │    │ Direct Sum   │
                    │ With Formula │    │ (fallback)   │
                    └──────┬───────┘    └──────┬───────┘
                           │                   │
                           └─────────┬─────────┘
                                     ↓
                            ┌─────────────────┐
                            │ Store in Cache  │
                            │ Return Results  │
                            └─────────────────┘
```

---

## Data Validation Rules

### Formula Key Validation

```php
function validate_formula_key($formula_key, $category = null) {
    $formulas = get_option('tdwp_tournament_formulas', array());

    // Check formula exists
    if (!isset($formulas[$formula_key])) {
        return new WP_Error('invalid_formula', 'Formula does not exist');
    }

    // Check category if specified
    if ($category && $formulas[$formula_key]['category'] !== $category) {
        return new WP_Error('wrong_category', 'Formula is not a ' . $category . ' formula');
    }

    return true;
}
```

### Paid Positions Validation

```php
function validate_paid_positions($paid_positions) {
    // Must be positive integer
    if (!is_numeric($paid_positions) || $paid_positions <= 0) {
        return 0;  // Default to 0 if invalid
    }

    return intval($paid_positions);
}
```

---

## Migration Considerations

### Initial State

- No active formulas selected
- System uses default behavior:
  - Tournament points: First available formula or hardcoded calculation
  - Season points: Direct sum of tournament points

### Migration Path

1. **Detect existing formulas** on first load
2. **Suggest active formula** based on usage or `is_default` flag
3. **Prompt user** to confirm selections
4. **Store selections** in new options

### Rollback Plan

If active formula causes issues:
1. Clear option (`delete_option('poker_active_X_formula')`)
2. System falls back to default behavior
3. No data loss (formulas preserved)

---

## Performance Implications

### Read Operations

- `get_option('poker_active_X_formula')`: ~1ms (WordPress option cache)
- Formula retrieval: ~2ms (option cache hit)

### Write Operations

- Set active formula: ~5ms (option update + cache clear)
- Calculate season points: Varies by tournament count (cached after first calc)

### Cache Hit Rate

Expected: >95% (leaderboard viewed multiple times between formula changes)

---

## Security Considerations

### Capability Requirements

- **View**: Read access to formulas (any logged-in user)
- **Set Active**: `manage_options` capability required
- **Delete**: Cannot delete active formula (must select replacement first)

### Nonce Verification

All AJAX actions must verify nonces:
```php
check_ajax_referer('poker_formula_manager_nonce', 'security');
```

### SQL Injection Protection

Use WordPress prepared statements for all queries:
```php
$formulas = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}poker_statistics WHERE player_id = %s",
    $player_id
));
```

---

## Testing Data

### Sample Formulas

```php
$sample_formulas = [
    'default_tournament' => [
        'formula_key' => 'default_td',
        'display_name' => 'Default Tournament Points',
        'category' => 'points',
        'formula' => 'round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (hits * 10)'
    ],
    'simple_season' => [
        'formula_key' => 'simple_season',
        'display_name' => 'Simple Season Score',
        'category' => 'season',
        'formula' => 'total_points + (best_finish * 5) - (worst_finish * 2)'
    ]
];
```

### Test Cases

1. **No active formula set** → Fallback to direct sum
2. **Active formula deleted** → Clear option, fallback
3. **Formula with undefined variables** → Graceful degradation
4. **Multiple formulas, one active** → Only one checkbox checked
5. **Season formula change** → Cache cleared, recalc on view
