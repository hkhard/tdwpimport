# Quickstart Guide: Active Formula Selection

**Feature**: 009-active-formula-selection
**Audience**: Developers implementing this feature
**Prerequisites**: WordPress plugin development experience, PHP 8.0+, jQuery

## Overview

This guide provides a quick reference for implementing the active formula selection feature. For complete details, see the full specification and research documents.

---

## Implementation Checklist

### Phase 1: Database & Storage ✅

- [ ] Add `poker_active_tournament_formula` option
- [ ] Add `poker_active_season_formula` option
- [ ] Verify `paid_positions` post meta is populated during import
- [ ] Create migration script if needed

### Phase 2: Backend (PHP)

- [ ] Implement AJAX handler: `set_active_formula`
- [ ] Implement AJAX handler: `get_active_formula`
- [ ] Implement AJAX handler: `clear_formula_cache`
- [ ] Update tournament import to use active formula
- [ ] Update season standings to use active season formula
- [ ] Add cache clearing on formula change

### Phase 3: Frontend (JavaScript)

- [ ] Add checkbox controls to formula cards
- [ ] Implement mutual exclusion logic (one active per category)
- [ ] Add AJAX handlers for checkbox changes
- [ ] Add visual indicator for active formulas
- [ ] Add error handling and user feedback

### Phase 4: Testing

- [ ] Unit tests for AJAX handlers
- [ ] Integration tests for formula switching
- [ ] Manual testing with sample data
- [ ] Verify bubble counts display correctly

---

## File Changes

### New Files

```
wordpress-plugin/poker-tournament-import/
├── includes/
│   └── class-active-formula-manager.php       # New: Manages active formula state
└── assets/
    └── js/
        └── active-formula-handler.js          # New: Frontend checkbox logic
```

### Modified Files

```
wordpress-plugin/poker-tournament-import/
├── admin/
│   └── formula-manager-page.php              # Add checkbox HTML
├── includes/
│   ├── class-parser.php                       # Fix paid_positions meta
│   └── class-series-standings.php             # Use active season formula
└── assets/
    ├── js/formula-manager.js                  # Add checkbox handlers
    └── css/formula-manager.css                # Add active state styles
```

---

## Code Snippets

### 1. PHP: Get Active Formula

```php
/**
 * Get active formula for a category
 *
 * @param string $category 'tournament' or 'season'
 * @return string|null Formula key or null if not set
 */
function get_active_formula($category) {
    $option_name = 'poker_active_' . $category . '_formula';
    $formula_key = get_option($option_name, null);

    if (!$formula_key) {
        return null;
    }

    // Verify formula still exists
    $formulas = get_option('tdwp_tournament_formulas', []);
    if (!isset($formulas[$formula_key])) {
        // Clear stale reference
        delete_option($option_name);
        return null;
    }

    return $formula_key;
}
```

### 2. PHP: Use Active Tournament Formula in Import

```php
// In tournament import logic
$active_formula = get_active_formula('tournament');

if ($active_formula) {
    $formula = get_formula_by_key($active_formula);
    $points = evaluate_tournament_formula($formula, $tournament_data);
} else {
    // Fallback to default or hardcoded calculation
    $points = calculate_default_points($tournament_data);
}

update_post_meta($tournament_id, 'tournament_points', $points);
```

### 3. PHP: Season Points with Active Formula

```php
// In class-series-standings.php
$active_season_formula = get_active_formula('season');

if ($active_season_formula) {
    // Calculate with active formula (per-tournament evaluation)
    $season_points = 0;
    foreach ($player_results as $result) {
        $variables = [
            'n' => $result->buyins,
            'r' => $result->finish_position,
            'hits' => $result->hits,
            'monies' => $result->winnings,
            // ... other variables
        ];

        $season_points += evaluate_formula(
            $active_season_formula,
            $variables,
            $result->tournament_id
        );
    }
} else {
    // Fallback to direct sum
    $season_points = array_sum($tournament_points_list);
}

$series_data['season_points'] = $season_points;
```

### 4. JavaScript: Checkbox Handler

```javascript
/**
 * Handle active formula checkbox changes
 */
$('.formula-active-checkbox').on('change', async function() {
    const $checkbox = $(this);
    const category = $checkbox.data('category');
    const formulaKey = $checkbox.data('formula-key');
    const isActive = $checkbox.is(':checked');

    if (isActive) {
        // Uncheck other formulas in same category
        $(`.formula-active-checkbox[data-category="${category}"]`)
            .not($checkbox)
            .prop('checked', false);

        // Update active formula
        try {
            const result = await $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'set_active_formula',
                    security: pokerFormulaManager.nonce,
                    formula_key: formulaKey,
                    category: category
                },
                dataType: 'json'
            });

            if (result.success) {
                showNotice('success', result.data.message);

                // Clear cache if season formula changed
                if (category === 'season') {
                    await clearSeasonStandingsCache();
                }
            } else {
                throw new Error(result.data.message);
            }
        } catch (error) {
            $checkbox.prop('checked', false);
            showNotice('error', error.message);
        }
    } else {
        // Clear active formula
        await clearActiveFormula(category);
    }
});
```

### 5. HTML: Formula Card Checkbox

```php
<!-- In formula-manager-page.php render_formulas_tab() -->
<div class="formula-card" data-key="<?php echo esc_attr($key); ?>"
     data-category="<?php echo esc_attr($formula['category']); ?>">

    <h3><?php echo esc_html($formula['display_name']); ?></h3>

    <div class="formula-active-control">
        <label>
            <input type="checkbox"
                   class="formula-active-checkbox"
                   data-category="tournament"
                   data-formula-key="<?php echo esc_attr($key); ?>"
                   <?php checked($is_active_tournament); ?>>
            Active Tournament Formula
        </label>

        <?php if ($formula['category'] === 'season'): ?>
        <label style="margin-left: 20px;">
            <input type="checkbox"
                   class="formula-active-checkbox"
                   data-category="season"
                   data-formula-key="<?php echo esc_attr($key); ?>"
                   <?php checked($is_active_season); ?>>
            Active Season Formula
        </label>
        <?php endif; ?>
    </div>

    <!-- ... rest of formula card ... -->
</div>
```

### 6. CSS: Active Formula Styling

```css
/* Highlight active formula cards */
.formula-card.is-active-tournament {
    border-left: 4px solid #0073aa;
    background: #f9f9f9;
}

.formula-card.is-active-season {
    border-right: 4px solid #00a32a;
    background: #f9f9f9;
}

/* Active badge */
.formula-active-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #0073aa;
    color: #fff;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
}
```

---

## Common Tasks

### Set Active Formula Programmatically

```php
// Set tournament formula
update_option('poker_active_tournament_formula', 'custom_points_v1');

// Set season formula
update_option('poker_active_season_formula', 'season_score_v2');
```

### Get Current Active Formula

```php
$tournament_formula = get_option('poker_active_tournament_formula');
$season_formula = get_option('poker_active_season_formula');
```

### Clear Active Formula

```php
delete_option('poker_active_tournament_formula');
delete_option('poker_active_season_formula');
```

### Check if Formula is Active

```php
function is_formula_active($formula_key, $category) {
    $active = get_option('poker_active_' . $category . '_formula');
    return $active === $formula_key;
}
```

### Clear Season Standings Cache

```php
global $wpdb;

// Delete all season standings transients
$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_poker_season_standings_%'
    OR option_name LIKE '_transient_timeout_poker_season_standings_%'"
);
```

---

## Debugging

### Check Active Formula State

```php
// In WP Admin or during debugging
error_log('Active Tournament Formula: ' . print_r(get_option('poker_active_tournament_formula'), true));
error_log('Active Season Formula: ' . print_r(get_option('poker_active_season_formula'), true));
```

### Verify paid_positions Meta

```php
// Check if paid_positions is set for a tournament
$paid = get_post_meta($tournament_id, 'paid_positions', true);
error_log("Tournament {$tournament_id} paid_positions: " . var_export($paid, true));
```

### Test Formula Evaluation

```php
// Test season formula with sample data
$test_data = [
    'n' => 20,
    'r' => 5,
    'hits' => 2,
    'monies' => 150.00,
    'avgBC' => 75.00
];

$result = evaluate_formula($formula_key, $test_data);
error_log("Formula result: " . var_export($result, true));
```

---

## Testing Checklist

### Manual Testing Steps

1. **Create Test Formulas**
   - Navigate to Formula Manager
   - Create 2 tournament formulas
   - Create 2 season formulas

2. **Set Active Tournament Formula**
   - Check "Active Tournament Formula" on one formula
   - Verify other tournament formulas unchecked
   - Reload page and verify selection persisted

3. **Import Tournament with Active Formula**
   - Import a .tdt file
   - Check tournament points use active formula
   - Verify points calculation is correct

4. **Set Active Season Formula**
   - Check "Active Season Formula" on one formula
   - View season leaderboard
   - Verify Season Points column shows calculated values

5. **Switch Active Formula**
   - Change active season formula
   - Refresh season leaderboard
   - Verify season points recalculated

6. **Test Bubble Count**
   - Import tournament with known paid positions
   - View player statistics
   - Verify bubble_count > 0 for players who finished outside money

### Automated Tests

```php
// PHPUnit test example
function test_active_formula_storage() {
    $formula_key = 'test_formula';

    // Set active formula
    update_option('poker_active_tournament_formula', $formula_key);

    // Verify retrieval
    $active = get_option('poker_active_tournament_formula');
    $this->assertEquals($formula_key, $active);
}

function test_season_formula_evaluation() {
    // Set up test data
    $player_results = $this->get_test_player_data();
    $formula_key = 'test_season_formula';

    // Calculate season points
    $season_points = calculate_season_points_with_formula(
        $player_results,
        $formula_key
    );

    // Verify result
    $this->assertIsNumeric($season_points);
    $this->assertGreaterThanOrEqual(0, $season_points);
}
```

---

## Common Issues & Solutions

### Issue: Bubble Count Always 0

**Symptoms**: All players show `bubble_count: 0`

**Diagnosis**:
```php
// Check if paid_positions is set
$paid = get_post_meta($tournament_id, 'paid_positions', true);
var_dump($paid);  // Should be int > 0, likely empty string or 0
```

**Solution**: Fix parser to populate `paid_positions` during import

### Issue: Season Points Not Calculating

**Symptoms**: Season Points column empty or shows 0

**Diagnosis**:
```php
// Check if active season formula is set
$active = get_option('poker_active_season_formula');
var_dump($active);  // Should be formula_key string

// Check if formula exists
$formulas = get_option('tdwp_tournament_formulas');
var_dump(isset($formulas[$active]));  // Should be true
```

**Solution**: Set active season formula in Formula Manager

### Issue: Formula Changes Not Reflected

**Symptoms**: Changed active formula but leaderboard shows old values

**Diagnosis**:
```php
// Check cache
$cache_key = 'poker_season_standings_' . $series_id . '_' . $formula_key;
$cached = get_transient($cache_key);
var_dump($cached);  // Should be false if cache cleared
```

**Solution**: Clear transients after formula change

---

## Performance Tips

1. **Cache Active Formula**: WordPress options are cached by default, no extra work needed
2. **Batch Cache Clearing**: Use SQL `DELETE LIKE` instead of loop for transients
3. **Lazy Recalculation**: Only recalculate on leaderboard view, not on formula change
4. **Formula Validation**: Validate formulas on save, not on every evaluation

---

## Next Steps

1. Review full specification: `spec.md`
2. Review research findings: `research.md`
3. Review data model: `data-model.md`
4. Review API contracts: `contracts/ajax-api.md`
5. Start implementation following Phase 1-4 checklist

---

## Support

For questions or issues during implementation:
1. Check this quickstart first
2. Review research.md for technical context
3. Refer to data-model.md for data structures
4. See contracts/ajax-api.md for API details
