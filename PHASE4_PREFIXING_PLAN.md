# Phase 4: Prefixing Audit - Strategic Plan

## Overview
WordPress.org requires 4+ character unique prefixes for all global scope items to avoid naming collisions.

**Suggested Prefix:** `poker_` or `POKER_` (6 characters, unique to plugin)

---

## Audit Categories

### Category 1: Classes (Currently Compliant)
**Status:** ✅ Already properly prefixed
**Pattern:** All classes use `Poker_Tournament_*` prefix
**Examples:**
- `Poker_Tournament_Import`
- `Poker_Tournament_Admin`
- `Poker_Tournament_Parser`
- `Poker_Tournament_Shortcodes`

**Action Required:** NONE - Keep as is

---

### Category 2: Custom Post Types (Needs Review)
**Current Names:**
- `tournament`
- `player`
- `tournament_series`

**Analysis:**
- "tournament" and "player" are generic terms - potential collision risk
- Already in production use - changing requires migration

**Options:**
1. **Keep as is** - Risk of collision but distinctive in context
2. **Migrate to prefixed** - `poker_tournament`, `poker_player`, `poker_series`

**Recommendation:** Check with WordPress.org if current names acceptable. If not:
```php
// Migration script needed
function poker_migrate_post_types() {
    global $wpdb;

    // Only run once
    if (get_option('poker_post_type_migration_done')) return;

    // Update post types
    $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'poker_tournament' WHERE post_type = 'tournament'");
    $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'poker_player' WHERE post_type = 'player'");
    $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'poker_series' WHERE post_type = 'tournament_series'");

    // Update post meta
    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = 'poker_tournament' WHERE meta_key = 'related_post_type' AND meta_value = 'tournament'");

    update_option('poker_post_type_migration_done', true);
}
```

**Risk:** HIGH - Breaking change for existing sites
**Decision:** Defer to review feedback - ask if current names acceptable

---

### Category 3: Taxonomies (Needs Review)
**Current Names:**
- `tournament_season`
- `tournament_series` (as taxonomy)

**Analysis:** These are reasonably distinctive

**Recommendation:** Check with reviewer if acceptable. If not:
```php
// Migration for taxonomies
register_taxonomy('poker_tournament_season', ...);
// Update term taxonomy table
```

---

### Category 4: Functions (Needs Audit)
**Search Required:** Global functions without prefix

**Audit Command:**
```bash
grep -rn "^function " wordpress-plugin/poker-tournament-import/ --include="*.php" | grep -v "class\|public\|private\|protected"
```

**Expected Patterns:**
- ✅ Class methods: Don't need prefix (inside namespace)
- ⚠️ Global functions: MUST have `poker_` prefix

**Action Items:**
1. Search for global functions
2. Verify all have `poker_` prefix
3. If any missing, add prefix and update all calls

---

### Category 5: Global Variables (Needs Audit)
**Search Required:** Check for unprefixed globals

**Audit Command:**
```bash
grep -rn "global \$" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
global $plugin_settings;

// Good
global $poker_plugin_settings;
```

**Action Items:**
1. Find all global variable declarations
2. Verify `poker_` or `$poker_` prefix
3. Update if needed

---

### Category 6: Options & Transients (Needs Audit)
**Search Required:** Check option names

**Audit Commands:**
```bash
# Options
grep -rn "update_option\|get_option\|add_option\|delete_option" wordpress-plugin/poker-tournament-import/ --include="*.php"

# Transients
grep -rn "set_transient\|get_transient\|delete_transient" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
update_option('plugin_settings', $data);

// Good
update_option('poker_plugin_settings', $data);
```

**Known Options to Check:**
- `poker_import_debug_mode` ✅
- `poker_plugin_version` ✅
- `poker_formulas_*` ✅
- `poker_statistics_*` ✅

**Action Items:**
1. List all option names
2. Verify all start with `poker_`
3. Create migration for any that don't

---

### Category 7: Post Meta Keys (Needs Audit)
**Search Required:** Check meta key names

**Audit Command:**
```bash
grep -rn "update_post_meta\|add_post_meta\|get_post_meta" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
update_post_meta($post_id, 'tournament_date', $date);

// Good
update_post_meta($post_id, 'poker_tournament_date', $date);
// OR
update_post_meta($post_id, '_poker_tournament_date', $date); // Leading underscore = hidden
```

**Action Items:**
1. Extract all meta key strings
2. Verify all have `poker_` or `_poker_` prefix
3. Migration script if needed

---

### Category 8: AJAX Actions (Needs Audit)
**Search Required:** Check AJAX action names

**Audit Command:**
```bash
grep -rn "add_action.*wp_ajax" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
add_action('wp_ajax_save_tournament', 'handler');

// Good
add_action('wp_ajax_poker_save_tournament', 'handler');
```

**Action Items:**
1. List all AJAX actions
2. Verify all start with `poker_`
3. Update frontend JS to match

---

### Category 9: Shortcodes (Needs Audit)
**Search Required:** Check shortcode names

**Audit Command:**
```bash
grep -rn "add_shortcode" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Current Known Shortcodes:**
- `[tournament_list]` ⚠️ No prefix
- `[tournament_results]` ⚠️ No prefix
- `[player_stats]` ⚠️ No prefix

**Pattern Required:**
```php
// Bad
add_shortcode('tournament_list', 'callback');

// Good
add_shortcode('poker_tournament_list', 'callback');
```

**Risk:** MEDIUM - Changes user's shortcodes in posts/pages
**Solution:** Support both old and new during transition
```php
// Register both for backward compatibility
add_shortcode('poker_tournament_list', 'poker_tournament_list_handler');
add_shortcode('tournament_list', 'poker_tournament_list_handler'); // Deprecated but supported
```

---

### Category 10: Database Tables (Currently Prefixed)
**Status:** ✅ Already properly prefixed
**Pattern:** All use `wp_poker_*` or `{$wpdb->prefix}poker_*`

**Examples:**
- `wp_poker_tournament_players` ✅
- `wp_poker_statistics` ✅
- `wp_poker_financial_summary` ✅

**Action Required:** NONE

---

### Category 11: Registered Scripts/Styles (Needs Audit)
**Search Required:** Check handle names

**Audit Commands:**
```bash
grep -rn "wp_register_script\|wp_enqueue_script" wordpress-plugin/poker-tournament-import/ --include="*.php"
grep -rn "wp_register_style\|wp_enqueue_style" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
wp_enqueue_script('admin-script', ...);

// Good
wp_enqueue_script('poker-admin-script', ...);
```

**Action Items:**
1. List all script/style handles
2. Verify all start with `poker-`
3. Update registrations

---

### Category 12: wp_localize_script Variables (Needs Audit)
**Search Required:** Check localized object names

**Audit Command:**
```bash
grep -rn "wp_localize_script" wordpress-plugin/poker-tournament-import/ --include="*.php"
```

**Pattern Required:**
```php
// Bad
wp_localize_script('handle', 'ajaxData', $data);

// Good
wp_localize_script('handle', 'pokerAjaxData', $data);
```

---

## Execution Plan

### Step 1: Automated Audit (30 min)
Run all audit commands and collect results:
```bash
# Create audit report
bash wordpress-plugin/poker-tournament-import/audit-prefixes.sh > PREFIX_AUDIT_REPORT.txt
```

### Step 2: Categorize Issues (30 min)
Review audit report and classify:
- ✅ Already compliant
- ⚠️ Needs prefix but low risk
- 🔴 Needs prefix and requires migration

### Step 3: Low-Risk Fixes (1-2 hours)
- Script/style handles
- wp_localize_script variables
- New AJAX actions
- Internal functions

### Step 4: Medium-Risk Fixes (2-3 hours)
- Shortcodes (with backward compatibility)
- Post meta keys (with migration)
- Option names (with migration)

### Step 5: High-Risk Decision (Review Dependent)
- Post types: Get reviewer guidance
- Taxonomies: Get reviewer guidance
- May not need changes if distinctive enough

### Step 6: Testing (1 hour)
- Verify all functionality
- Test migrations
- Check backward compatibility

---

## Migration Script Template

```php
/**
 * Migrate unprefixed data to prefixed versions
 * Run once on plugin update
 */
function poker_prefix_migration() {
    $version = get_option('poker_prefix_migration_version', '0');

    if (version_compare($version, '2.9.15', '<')) {
        global $wpdb;

        // Migrate options
        $old_options = array(
            'old_name' => 'poker_new_name',
        );
        foreach ($old_options as $old => $new) {
            $value = get_option($old);
            if ($value !== false) {
                update_option($new, $value);
                delete_option($old);
            }
        }

        // Migrate post meta
        $wpdb->query("
            UPDATE {$wpdb->postmeta}
            SET meta_key = CONCAT('poker_', meta_key)
            WHERE meta_key NOT LIKE 'poker_%'
            AND meta_key IN ('tournament_date', 'player_stats')
        ");

        update_option('poker_prefix_migration_version', '2.9.15');
    }
}
add_action('admin_init', 'poker_prefix_migration');
```

---

## Risk Matrix

| Category | Risk | Impact | Mitigation |
|----------|------|--------|------------|
| Classes | None | - | Already compliant |
| Post Types | High | Breaking | Ask reviewer first |
| Taxonomies | High | Breaking | Ask reviewer first |
| Functions | Low | Internal | Simple rename |
| Options | Medium | Data | Migration script |
| Post Meta | Medium | Data | Migration script |
| AJAX | Medium | Frontend | Update JS + PHP |
| Shortcodes | Medium | User content | Backward compat |
| Scripts/Styles | Low | Display | Simple rename |
| DB Tables | None | - | Already compliant |

---

## Reviewer Questions to Ask

Before implementing high-risk changes:

**Q1:** "Our custom post types are 'tournament', 'player', and 'tournament_series'. While these are generic terms, they're distinctive in the context of a poker tournament plugin. Are these acceptable, or do they need prefixing (which would require a breaking change migration for existing sites)?"

**Q2:** "Our taxonomies 'tournament_season' and 'tournament_series' - acceptable or need prefixing?"

**Q3:** "We have existing shortcodes like [tournament_list] used in customer sites. Can we maintain backward compatibility by supporting both [tournament_list] and [poker_tournament_list], or must we force the change?"

---

## Estimated Time

- Audit: 1 hour
- Low-risk fixes: 1-2 hours
- Medium-risk fixes: 2-3 hours
- High-risk (if needed): 3-4 hours + extensive testing
- Testing: 1-2 hours

**Total: 8-12 hours** (depends on reviewer guidance on post types/taxonomies)

---

## Success Criteria

✅ All global functions have `poker_` prefix
✅ All global variables have `poker_` prefix
✅ All options have `poker_` prefix
✅ All post meta keys have `poker_` prefix
✅ All AJAX actions have `poker_` prefix
✅ All script/style handles have `poker-` prefix
✅ Shortcodes have `poker_` prefix (with backward compat)
✅ Post types/taxonomies resolution with reviewer
✅ Migration scripts tested
✅ No broken functionality
✅ Plugin Check passes prefix requirements
