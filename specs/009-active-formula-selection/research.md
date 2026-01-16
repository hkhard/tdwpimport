# Research: Active Formula Selection Implementation

**Feature**: 009-active-formula-selection
**Date**: 2025-01-02
**Status**: Complete

## Overview

This research document captures findings about the existing formula management system, bubble calculation, and season leaderboard implementation to inform the technical approach for adding active formula selection and fixing season points display.

## Formula Storage System

### Current Implementation

**Storage Location**: WordPress options table
- **Option Key**: `tdwp_tournament_formulas`
- **Data Structure**: Array of formula objects keyed by formula_key
- **Validator Class**: `Poker_Tournament_Formula_Validator` in `includes/class-formula-validator.php`

### Formula Schema

Each formula object contains:
```php
[
    'formula_key' => string,        // Auto-generated slug from display name
    'display_name' => string,        // Human-readable name
    'description' => string,         // Formula description
    'category' => string,            // 'points', 'season', or 'custom'
    'dependencies' => array,         // List of TD variable assignments
    'formula' => string,             // Main TD formula expression
    'is_default' => bool             // Whether this is a default formula
]
```

### Key Methods

- `get_formulas()`: Retrieve all formulas from options
- `save_formula($formula_key, $formula_data)`: Save/update a formula
- `delete_formula($formula_key)`: Remove a formula
- `validate_formula($formula, $dependencies)`: Validate syntax

## Formula Manager UI

### Frontend Components

**PHP Class**: `Poker_Formula_Manager_Page` in `admin/formula-manager-page.php`
**JavaScript**: `assets/js/formula-manager.js`

### Current UI Structure

1. **Formula Cards**: Display existing formulas with edit/delete buttons
2. **Formula Editor Modal**: Create/edit formulas with fields:
   - Display Name
   - Description
   - Category dropdown (points/season/custom)
   - Dependencies (textarea)
   - Formula expression (textarea)

3. **Action Buttons**: Test Formula, Save Formula, Cancel

### AJAX Endpoints

- Formula save/update via AJAX with nonce verification
- Formula preview testing
- Formula deletion

## Season Standings System

### Core Class

**File**: `includes/class-series-standings.php`
**Class**: `Poker_Series_Standings`

### Bubble Calculation (Current Implementation)

**Location**: Lines 300-306 in `class-series-standings.php`

```php
$tournament_post_id = $this->get_tournament_post_id($result->tournament_id);
$paid_positions = get_post_meta($tournament_post_id, 'paid_positions', true);

if ($paid_positions && $result->finish_position == $paid_positions + 1) {
    $bubble_count++;
}
```

**Issue Identified**: Bubble calculation returns 0 for all players because:
1. `paid_positions` post meta may not be populated during tournament import
2. Need to verify parser sets this meta field correctly

### Season Points Calculation

**Current Approach**:
- Aggregates tournament results for each player
- Applies series formula if specified
- Uses pre-calculated aggregates (total_points, avg_finish, best_finish, etc.)

**Data Flow**:
```
Individual Tournament Results
    → Aggregate by player
    → Apply series formula (if any)
    → Display in season leaderboard
```

### Variable Access

**Available Variables for Season Formulas**:
- Tournament-level: `n`, `r`, `hits`, `monies`, `avgBC`, `T33`, `T80`
- Aggregates: `total_points`, `total_winnings`, `total_hits`, `best_finish`, `worst_finish`, `avg_finish`, `tournaments_played`

**Gap Identified**: Season formulas currently use pre-aggregated data only. Need to enable per-tournament formula evaluation with full TD variable access.

## Tournament Import Process

### Parser Class

**File**: `includes/class-parser.php`
**Purpose**: Parse .tdt files and extract tournament data

### Post Meta Storage

Tournament data stored as post meta:
- `tournament_uuid`: Unique identifier from .tdt
- `paid_positions`: Number of paid spots (NEEDS VERIFICATION)
- Other tournament-specific fields

## Technical Decisions

### Active Formula Storage

**Decision**: Store active formula keys in separate WordPress options
- `poker_active_tournament_formula`: Formula key for tournament points
- `poker_active_season_formula`: Formula key for season standings

**Rationale**:
- Clean separation of formula definitions vs. active selection
- Easy to update without touching formula array
- Atomic operations for switching active formulas

### UI Implementation

**Approach**: Add checkbox controls to formula manager
- Radio buttons for mutually exclusive selection (one per category)
- Visual indicator (badge/icon) for active formula
- JavaScript enforcement of single-selection constraint

**Components Needed**:
1. Checkbox/radio in formula card
2. "Set as Active" AJAX endpoint
3. Visual styling for active state (CSS badge)
4. JavaScript handler for mutual exclusion

### Bubble Fix Strategy

**Root Cause**: `paid_positions` meta not set during import
**Solution**: Verify and fix parser to populate `paid_positions` post meta
**Fallback**: If `paid_positions` is not available, calculate from tournament prize structure

### Season Points Calculation Enhancement

**Approach**: Extend season formula evaluation to support per-tournament variables

**Implementation**:
1. Season formula receives tournament result context
2. Evaluate formula for each tournament with that tournament's variables
3. Aggregate results (sum, average, or custom aggregation)
4. Handle undefined variables gracefully (treat as 0)

**Cache Strategy**: Lazy recalculation
- Clear transients when active formula changes
- Recalculate on leaderboard view
- Store in transient for performance

## WordPress Integration Points

### Options to Add

1. `poker_active_tournament_formula` - Active tournament formula key
2. `poker_active_season_formula` - Active season formula key

### Post Meta to Verify

1. `paid_positions` - Must be populated by parser
2. `tournament_uuid` - Already working

### AJAX Endpoints to Add

1. `wp_ajax_set_active_formula` - Set active formula for category
2. `wp_ajax_get_active_formula` - Get current active formula

### Hooks to Modify

1. Tournament import: Use active tournament formula for points calculation
2. Season leaderboard: Use active season formula for standings
3. Formula save/delete: Update active formula if needed

## Dependencies and Constraints

### Existing Dependencies

- WordPress 6.0+
- PHP 8.0+
- jQuery (already enqueued in admin)

### Formula System Constraints

- Formulas use Tournament Director expression syntax
- Variables must be predefined or calculated
- Formula validation happens before save
- No circular dependencies allowed

### Performance Considerations

- Season points calculation is expensive (iterates all tournaments)
- Lazy recalculation chosen to balance responsiveness
- Transient caching essential for leaderboard performance
- Formula validation should be fast (AJAX responsiveness)

## Security Considerations

### Nonce Verification

All AJAX endpoints must verify nonces:
- Formula save/delete already use nonces
- New active formula endpoints must add nonce verification

### Capability Checks

- `manage_options` capability required for formula management
- Season leaderboard view available to appropriate user roles

### Input Sanitization

- Formula keys: slugified (alphanumeric + underscore)
- Formula expressions: Validated by formula validator
- Category values: Whitelist (points/season/custom)

## Testing Strategy

### Unit Tests Needed

1. Active formula storage/retrieval
2. Mutual exclusion enforcement
3. Bubble calculation with various paid_positions values
4. Season formula evaluation with undefined variables

### Integration Tests Needed

1. Formula manager UI interactions
2. Tournament import with active formula
3. Season leaderboard with active season formula
4. Active formula switching and cache clearing

### Manual Testing Scenarios

1. Create formulas, mark one active, verify enforcement
2. Import tournament, verify points use active formula
3. View season leaderboard, verify season points calculated
4. Switch active formula, verify recalculation
5. Check bubble count displays correctly

## Open Questions Resolved

1. **Bubble Definition**: Traditional poker bubble (position = paid_positions + 1)
   - Issue is `paid_positions` not being set, not logic error

2. **Season Formula Variables**: Full tournament-level variable access
   - Per-tournament evaluation with graceful undefined handling

3. **Historical Recalculation**: Lazy calculation on leaderboard view
   - Clear transients on formula change, recalc on view

## Next Steps

1. Implement active formula UI controls
2. Add AJAX endpoints for active formula management
3. Fix parser to set `paid_positions` meta
4. Extend season formula evaluation for per-tournament variables
5. Implement lazy recalculation with cache clearing
6. Test all scenarios and verify bubble counts
