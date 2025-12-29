# Phase 3: Script/Style Enqueueing - Strategic Conversion Plan

## Overview
WordPress.org compliance requires all inline `<script>` and `<style>` tags to use proper wp_enqueue functions.

**Total Identified:** 32 instances across 16 files
- Scripts: 12 instances
- Styles: 20 instances

---

## Batch 1: Admin Pages (Priority: HIGH)
**Files:** 5 admin files | **Total:** 13 instances

### 1.1 class-admin.php (2 instances)
**Line 676:** `<script>` - Formula editor initialization
**Status:** Dynamic JS with PHP variables
**Solution:**
```php
add_action('admin_enqueue_scripts', function($hook) {
    if ('post.php' !== $hook && 'post-new.php' !== $hook) return;
    wp_add_inline_script('tdwp-admin-script', '
        // Formula editor init code
    ');
});
```

### 1.2 class-data-mart-cleaner.php (2 instances)
**Line 987:** `<script>` - AJAX progress handler
**Solution:** Use wp_add_inline_script attached to admin script

### 1.3 shortcode-helper.php (4 instances)
**Lines 166, others:** `<style>` - Admin UI styling
**Solution:** Extract to admin CSS file or use wp_add_inline_style

### 1.4 shortcode-help.php (2 instances)
**Line 68:** `<style>` - Help page styling
**Solution:** wp_add_inline_style with admin hook

### 1.5 migration-tools.php (2 instances)
**Line 425:** `<script>` - Migration progress
**Solution:** wp_add_inline_script for AJAX handlers

### 1.6 formula-manager-page.php (3 instances)
**Multiple locations:** Formula editor scripts/styles
**Solution:** Dedicated formula-editor.js/css already exists, attach inline code to those

---

## Batch 2: Formula Validator (Priority: HIGH)
**Files:** 1 file | **Total:** 3 instances

### 2.1 class-formula-validator.php (3 instances)
**Lines 1985, 2084:** `<script>` - Real-time validation
**Status:** Critical for formula editing functionality
**Solution:**
```php
public function enqueue_validator_scripts() {
    if (is_admin()) {
        wp_enqueue_script('tdwp-formula-validator',
            plugins_url('assets/js/formula-validator.js', dirname(__FILE__)),
            array('jquery'),
            POKER_TOURNAMENT_IMPORT_VERSION,
            true
        );
        wp_add_inline_script('tdwp-formula-validator', $this->get_validator_inline_code());
    }
}
```

---

## Batch 3: Frontend Templates (Priority: MEDIUM)
**Files:** 6 template files | **Total:** 8 instances

### 3.1 archive-tournament.php (2 instances)
**Line 315:** `<style>` - Tournament grid styling
**Solution:** Extract to frontend.css or use wp_add_inline_style

### 3.2 single-tournament.php (1 instance)
**Style tag:** Tournament detail page styling
**Solution:** Move to frontend.css

### 3.3 single-tournament_season.php (1 instance)
**Style tag:** Season detail styling
**Solution:** Move to frontend.css

### 3.4 taxonomy-tournament_series.php (1 instance)
**Style tag:** Series archive styling
**Solution:** Move to frontend.css

### 3.5 taxonomy-tournament_season.php (1 instance)
**Style tag:** Season archive styling
**Solution:** Move to frontend.css

### 3.6 Template Strategy:
All template inline styles should use:
```php
add_action('wp_enqueue_scripts', function() {
    if (is_singular('tournament') || is_post_type_archive('tournament')) {
        wp_add_inline_style('tdwp-frontend-style', '
            /* Tournament-specific styles */
        ');
    }
});
```

---

## Batch 4: Shortcodes & Components (Priority: MEDIUM)
**Files:** 3 files | **Total:** 7 instances

### 4.1 class-shortcodes.php (5 instances)
**Multiple locations:** Shortcode output styling
**Solution:** Each shortcode should enqueue its styles via wp_add_inline_style

### 4.2 class-series-standings.php (1 instance)
**Style tag:** Standings table styling
**Solution:** Extract to component CSS or inline with wp_add_inline_style

### 4.3 class-debug.php (1 instance)
**Script tag:** Debug panel functionality
**Solution:** Conditional enqueue only when debug mode enabled

---

## Batch 5: Bulk Import (Priority: LOW - New Feature)
**Files:** 1 file | **Total:** 1 instance

### 5.1 bulk-import-page.php (1 instance)
**Script tag:** Bulk upload handler
**Solution:** Create dedicated bulk-import.js file

---

## Implementation Strategy

### Step 1: Create Asset Files (if needed)
```bash
wordpress-plugin/poker-tournament-import/assets/js/
├── admin.js (existing)
├── frontend.js (existing)
├── formula-validator.js (existing)
├── formula-editor.js (existing)
└── bulk-import.js (NEW)

wordpress-plugin/poker-tournament-import/assets/css/
├── admin.css (existing)
├── frontend.css (existing)
└── formula-editor.css (existing)
```

### Step 2: Enqueue Pattern for Admin
```php
add_action('admin_enqueue_scripts', 'poker_admin_scripts');
function poker_admin_scripts($hook) {
    // Base admin scripts
    wp_enqueue_script('tdwp-admin-script',
        plugins_url('assets/js/admin.js', __FILE__),
        array('jquery'),
        POKER_TOURNAMENT_IMPORT_VERSION,
        true
    );

    // Page-specific inline code
    if ('toplevel_page_poker-tournament-import' === $hook) {
        wp_add_inline_script('tdwp-admin-script', '
            // Page-specific JS
        ');
    }
}
```

### Step 3: Enqueue Pattern for Frontend
```php
add_action('wp_enqueue_scripts', 'poker_frontend_scripts');
function poker_frontend_scripts() {
    if (is_singular('tournament') || is_post_type_archive('tournament')) {
        wp_enqueue_style('tdwp-frontend-style',
            plugins_url('assets/css/frontend.css', __FILE__),
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        wp_add_inline_style('tdwp-frontend-style', '
            /* Template-specific styles */
        ');
    }
}
```

### Step 4: Convert Inline to Functions
For each file:
1. Extract inline script/style content
2. Move static content to .js/.css files
3. Keep dynamic content in wp_add_inline_script/style
4. Add proper conditionals (page detection)
5. Test functionality

---

## Execution Order

### Session 1 (Current): Planning + Critical Fixes
- ✅ Phase 1: Ownership verification
- ✅ Phase 2: Input sanitization (COMPLETE)
- ⏸️ Phase 3: Create this plan

### Session 2: Admin Pages (Batch 1)
- Convert class-admin.php
- Convert class-data-mart-cleaner.php
- Convert shortcode-helper.php
- Convert shortcode-help.php
- Convert migration-tools.php
- Convert formula-manager-page.php
- Test admin functionality

### Session 3: Formula Validator (Batch 2)
- Convert class-formula-validator.php
- Create formula-validator-inline.js if needed
- Test formula editing

### Session 4: Frontend Templates (Batch 3)
- Convert all 6 template files
- Move styles to frontend.css where appropriate
- Test frontend display

### Session 5: Shortcodes (Batch 4)
- Convert class-shortcodes.php
- Convert class-series-standings.php
- Convert class-debug.php
- Test all shortcode outputs

### Session 6: Final Batch (Batch 5) + Testing
- Convert bulk-import-page.php
- Run comprehensive tests
- Plugin Check validation

---

## Testing Checklist (After Each Batch)

### Admin Testing
- [ ] Plugin activates without errors
- [ ] Admin pages load correctly
- [ ] AJAX functions work
- [ ] Formula editor functional
- [ ] No JS console errors
- [ ] No missing styles

### Frontend Testing
- [ ] Tournament archives display
- [ ] Single tournament pages work
- [ ] Player pages work
- [ ] Series/season pages work
- [ ] Shortcodes render correctly
- [ ] No JS console errors
- [ ] No missing styles

---

## Risk Assessment

### Low Risk (Easy conversions)
- Static styles → Extract to CSS files
- Simple scripts → Move to JS files

### Medium Risk (Requires testing)
- Dynamic styles with PHP variables → wp_add_inline_style with PHP
- AJAX handlers → Need to verify data passing

### High Risk (Critical functionality)
- Formula validator (lines 1985, 2084) → Test extensively
- Tournament import flow → Verify end-to-end

---

## Rollback Strategy

Each batch committed separately:
```bash
git commit -m "Phase 3 Batch 1: Admin pages enqueue conversion"
git commit -m "Phase 3 Batch 2: Formula validator enqueue conversion"
# etc.
```

If issues found:
```bash
git revert <commit-hash>
# Fix issues
# Re-commit
```

---

## Notes

- Keep PHP variables in wp_localize_script for clean separation
- Use heredoc for multi-line inline scripts to maintain readability
- Maintain proper dependency chains (jQuery, etc.)
- Add version numbers for cache busting
- Use 'true' for footer placement on scripts
- Test on WP 6.0+ and PHP 8.0+

---

## Estimated Time per Batch

- Batch 1 (Admin): 2-3 hours
- Batch 2 (Validator): 1 hour
- Batch 3 (Templates): 1-2 hours
- Batch 4 (Shortcodes): 1-2 hours
- Batch 5 (Bulk Import): 30 minutes
- Testing per batch: 30 minutes

**Total: 6-9 hours across multiple sessions**

---

## Success Criteria

✅ All `<script>` tags converted to wp_enqueue_script or wp_add_inline_script
✅ All `<style>` tags converted to wp_enqueue_style or wp_add_inline_style
✅ Plugin Check shows 0 inline script/style issues
✅ All functionality tested and working
✅ No JavaScript console errors
✅ No missing styles in admin or frontend
✅ AJAX endpoints still functional
✅ Formula editor still works
✅ Tournament import still works
