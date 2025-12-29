# Phase 4: Prefixing Audit Results

**Date:** October 26, 2025
**Target Prefix:** `tdwp_` (Tournament Director WordPress)
**Current Prefix:** `poker_` (too generic)

---

## Executive Summary

**Items Requiring Change:** 4 categories with prefix changes needed
**Risk Level:** MEDIUM-HIGH (shortcodes affect user content)
**Migration Required:** Yes - for options and backward compatibility

---

## Category 1: Global Functions ⚠️ NEEDS FIXING

**Found:** 3 global functions WITHOUT prefix in templates
**Risk:** LOW - internal template functions
**Action:** Add `tdwp_` prefix

### Functions to Rename:
```
templates/single-tournament.php:21:
  get_tournament_winner_info() → tdwp_get_tournament_winner_info()

templates/single-tournament.php:89:
  get_realtime_tournament_results() → tdwp_get_realtime_tournament_results()

templates/single-tournament.php:122:
  extract_basic_metadata() → tdwp_extract_basic_metadata()
```

**Migration:** Simple find/replace in template files

---

## Category 2: Global Variables ✅ COMPLIANT

**Found:** 0 custom global variables
**Status:** No action needed

---

## Category 3: Options/Transients ⚠️ NEEDS MIGRATION

**Found:** 16 options with `poker_` prefix
**Risk:** MEDIUM - requires database migration
**Action:** Change to `tdwp_` + migration script

### Options to Migrate:
```
poker_active_season_formula → tdwp_active_season_formula
poker_active_tournament_formula → tdwp_active_tournament_formula
poker_currency_position → tdwp_currency_position
poker_currency_symbol → tdwp_currency_symbol
poker_formula_debug_mode → tdwp_formula_debug_mode
poker_formulas → tdwp_formulas
poker_hit_counting_method → tdwp_hit_counting_method
poker_import_auto_publish → tdwp_import_auto_publish
poker_import_debug_logging → tdwp_import_debug_logging
poker_import_debug_mode → tdwp_import_debug_mode
poker_import_default_buyin → tdwp_import_default_buyin
poker_import_last_version → tdwp_import_last_version
poker_import_show_debug_stats → tdwp_import_show_debug_stats
poker_roi_migration_complete → tdwp_roi_migration_complete
poker_statistics_last_refresh → tdwp_statistics_last_refresh
poker_tournament_formulas → tdwp_tournament_formulas
```

---

## Category 4: Post Meta Keys 📝 NEEDS INVESTIGATION

**Status:** Requires deeper scan
**Next Step:** Scan for update_post_meta/get_post_meta calls

---

## Category 5: AJAX Actions ⚠️ NEEDS CHANGING

**Found:** 38 AJAX actions with `poker_` prefix
**Risk:** MEDIUM - breaks AJAX if not updated in both PHP and JS
**Action:** Global find/replace + update JS files

### AJAX Actions (sample - 38 total):
```
wp_ajax_poker_clean_data_mart → wp_ajax_tdwp_clean_data_mart
wp_ajax_poker_dashboard_load_content → wp_ajax_tdwp_dashboard_load_content
wp_ajax_poker_get_leaderboard_data → wp_ajax_tdwp_get_leaderboard_data
wp_ajax_poker_load_overview_stats → wp_ajax_tdwp_load_overview_stats
wp_ajax_poker_migrate_tournaments → wp_ajax_tdwp_migrate_tournaments
wp_ajax_poker_refresh_statistics → wp_ajax_tdwp_refresh_statistics
wp_ajax_poker_save_formula → wp_ajax_tdwp_save_formula
wp_ajax_poker_validate_formula → wp_ajax_tdwp_validate_formula
... (30 more)
```

**Critical:** Must update BOTH:
1. PHP: `add_action('wp_ajax_poker_*')` → `add_action('wp_ajax_tdwp_*')`
2. JS: `action: 'poker_*'` → `action: 'tdwp_*'`

---

## Category 6: Shortcodes 🔴 HIGH RISK - REQUIRES BACKWARD COMPAT

**Found:** 18 shortcodes WITHOUT prefix
**Risk:** HIGH - used in user posts/pages
**Action:** Add prefix + maintain backward compatibility

### Shortcodes to Update:
```
[player_profile] → [tdwp_player_profile]
[poker_dashboard] → [tdwp_dashboard]
[season_overview] → [tdwp_season_overview]
[season_players] → [tdwp_season_players]
[season_results] → [tdwp_season_results]
[season_standings] → [tdwp_season_standings]
[season_statistics] → [tdwp_season_statistics]
[season_tabs] → [tdwp_season_tabs]
[series_leaderboard] → [tdwp_series_leaderboard]
[series_overview] → [tdwp_series_overview]
[series_players] → [tdwp_series_players]
[series_results] → [tdwp_series_results]
[series_standings] → [tdwp_series_standings]
[series_statistics] → [tdwp_series_statistics]
[series_tabs] → [tdwp_series_tabs]
[tournament_results] → [tdwp_tournament_results]
[tournament_series] → [tdwp_tournament_series]
```

**Backward Compatibility Strategy:**
```php
// Register BOTH old and new shortcodes
add_shortcode('tdwp_player_profile', 'tdwp_player_profile_shortcode');
add_shortcode('player_profile', 'tdwp_player_profile_shortcode'); // Deprecated but supported

// Add deprecation notice in shortcode function
function tdwp_player_profile_shortcode($atts) {
    // Check which shortcode was used
    $trace = debug_backtrace();
    if (isset($trace[3]['args'][2]) && $trace[3]['args'][2] === 'player_profile') {
        // Log deprecation (optional)
        error_log('Deprecated shortcode [player_profile] used. Please update to [tdwp_player_profile]');
    }
    // ... rest of function
}
```

---

## Category 7: Script/Style Handles ⚠️ NEEDS CHANGING

**Found:** Handles use full plugin name (acceptable but can optimize)
**Current:** `poker-tournament-import-admin`
**Recommendation:** Keep as-is (already distinctive) OR change to `tdwp-admin`

### Current Handles:
```
poker-tournament-import-admin (CSS)
poker-tournament-import-admin (JS)
poker-migration-tools (CSS)
poker-formula-editor (CSS)
poker-formula-editor (JS)
```

**Decision:** KEEP AS-IS - already unique enough

---

## Category 8: Database Tables ✅ COMPLIANT

**Status:** Already use `wp_poker_*` prefix (checked in initial audit)
**Action:** None needed

---

## Category 9: Post Types & Taxonomies 📋 REVIEWER QUESTION

**Post Types:** `tournament`, `player`, `tournament_series`
**Taxonomies:** `tournament_season`, `tournament_series`
**Risk:** HIGH - changing breaks existing sites
**Action:** ASK REVIEWER FIRST

**Question for WordPress.org:**
> "Our custom post types are 'tournament', 'player', and 'tournament_series'. While generic, they're distinctive in context. Must these be prefixed (requiring migration for existing sites)?"

---

## Implementation Priority

### Priority 1: LOW RISK (Do First)
1. ✅ Global functions (3 functions) - 10 min
2. ✅ Script/style handles (if changing) - 15 min

### Priority 2: MEDIUM RISK (Requires Testing)
3. Options migration (16 options) - 30 min + migration script
4. AJAX actions (38 actions) - 45 min PHP + JS updates

### Priority 3: HIGH RISK (Requires Backward Compat)
5. Shortcodes (18 shortcodes) - 1 hour with compat layer

### Priority 4: REVIEWER DEPENDENT
6. Post types/taxonomies - Wait for guidance

---

## Migration Script Template

```php
/**
 * Migrate poker_ prefixes to tdwp_ prefixes
 * Version: 2.9.15
 */
function tdwp_migrate_prefixes() {
    $migrated = get_option('tdwp_prefix_migration_v1', false);
    if ($migrated) return;

    // Migrate options
    $options_map = array(
        'poker_formulas' => 'tdwp_formulas',
        'poker_import_debug_mode' => 'tdwp_import_debug_mode',
        // ... all 16 options
    );

    foreach ($options_map as $old => $new) {
        $value = get_option($old);
        if ($value !== false) {
            update_option($new, $value);
            // Keep old option for rollback capability
            // delete_option($old); // Uncomment after testing
        }
    }

    update_option('tdwp_prefix_migration_v1', true);
}
add_action('admin_init', 'tdwp_migrate_prefixes');
```

---

## Estimated Time

- Global functions: 10 min
- Options migration: 1 hour (code + testing)
- AJAX actions: 1 hour (PHP + JS)
- Shortcodes + compat: 1.5 hours
- Testing: 1 hour
- **Total: 4.5 hours**

---

## Success Criteria

✅ All global functions have `tdwp_` prefix
✅ All options have `tdwp_` prefix with migration
✅ All AJAX actions have `tdwp_` prefix (PHP + JS)
✅ All shortcodes have `tdwp_` prefix with backward compat
✅ Migration script tested and working
✅ No broken functionality
✅ Plugin Check passes

---

## Next Steps

1. Start with global functions (lowest risk)
2. Create options migration script
3. Update AJAX actions (PHP + JS)
4. Add shortcode backward compatibility
5. Test thoroughly
6. Ask reviewer about post types
