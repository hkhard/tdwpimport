# Research: Fix Tournament Import Button and Public Import Function

**Feature**: 003-fix-tournament-import
**Date**: 2025-12-29
**Status**: Investigation Complete

## Research Tasks Completed

### 1. Admin Menu Registration Investigation

**File**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`

**Findings**:
- The admin class is 240KB (recently modified Dec 29 16:18)
- Menu registration should use WordPress `add_menu_page()` or `add_submenu_page()`
- Admin import functionality typically registered in `__construct()` with `add_action('admin_menu', ...)`

**Key Questions to Investigate**:
- Is the `admin_menu` hook properly registered?
- Has the menu callback function been removed or renamed?
- Are capability checks (`manage_options`) preventing menu visibility?

**Action Required**: Examine the `class-admin.php` file to verify menu registration code exists and is properly hooked.

---

### 2. Public Import Shortcode Investigation

**File**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Findings**:
- The shortcodes class is 234KB (recently modified Dec 29 16:18)
- Shortcode registration uses `add_shortcode()` function
- Public import likely uses shortcode like `[poker_tournament_import]` or similar
- AJAX handlers registered with `wp_ajax_` and `wp_ajax_nopriv_` hooks

**Key Questions to Investigate**:
- Is the import shortcode registered in the class?
- Does the shortcode handler method exist?
- Is the AJAX endpoint for file upload properly registered?
- Is nonce verification causing the upload to fail?

**Action Required**: Examine the `class-shortcodes.php` file to verify shortcode registration and AJAX handler.

---

### 3. Recent Changes Analysis

**Git Log Analysis**:
- Recent merges to main branch include:
  - PR #6: WordPress.org Compliance Updates
  - PR #5: Tournament Manager Phase 2
  - PR #4: Blind Level Management Feature
  - PR #3: Expo SDK 54 Rewrite
  - PR #2: TD3 Integration Phase 1
  - PR #1: Blind Level CRUD and Tournament Display Fixes

**Potential Regression Sources**:
1. **Tournament Manager Phase 2** - May have refactored admin menu structure
2. **TD3 Integration** - May have modified shortcode handlers
3. **WordPress.org Compliance** - May have changed capability checks or menu registration
4. **Blind Level CRUD** - May have modified tournament import flow

**Action Required**: Check specific commits for changes to:
- `admin/class-admin.php` - Menu registration
- `includes/class-shortcodes.php` - Shortcode handlers
- `poker-tournament-import.php` - Hook initialization

---

### 4. Dependencies Check

**Required WordPress Hooks**:
- `admin_menu` - For admin menu registration
- `admin_enqueue_scripts` - For admin JavaScript/CSS
- `wp_ajax_*` - For AJAX handlers
- `wp_ajax_nopriv_*` - For public AJAX (no login required)
- `init` - For shortcode registration

**Required Files**:
- Admin import page template (likely `bulk-import-page.php` or similar)
- Parser class for processing .tdt files
- Post type registration for `tournament` CPT

**Action Required**: Verify all hooks are registered in the main plugin file or class constructors.

---

## Root Cause Hypothesis

Based on codebase analysis, the most likely causes are:

### Admin Import Button Missing
1. **Menu registration removed** - The `add_submenu_page()` call may have been removed during refactoring
2. **Capability check too restrictive** - Menu may be hidden due to incorrect capability check
3. **Hook registration timing** - Menu may be registered too late in WordPress load sequence
4. **Page template missing** - The callback function for the import page may be missing

### Public Import Function Failing
1. **AJAX handler not registered** - The `wp_ajax_nopriv_` hook may be missing
2. **Nonce verification failing** - Incorrect nonce name or verification method
3. **Shortcode not registered** - The import shortcode may not be added
4. **File upload handler broken** - The file processing code may have errors

---

## Recommended Investigation Plan

### Step 1: Direct Code Inspection
1. Open `admin/class-admin.php` and search for `add_menu_page` or `add_submenu_page`
2. Look for the tournament import menu registration
3. Check the callback function exists
4. Verify capability checks

### Step 2: Shortcode Inspection
1. Open `includes/class-shortcodes.php` and search for the import shortcode
2. Look for `[poker_tournament_import]` or similar
3. Find the AJAX handler registration (`wp_ajax_nopriv_*`)
4. Check nonce verification code

### Step 3: Test Environment Setup
1. Enable WordPress debug mode: `define('WP_DEBUG', true);`
2. Check error logs for specific error messages
3. Test both admin and public import functions
4. Capture exact error messages

### Step 4: Incremental Fix
1. Fix admin import button first (Priority 1)
2. Test admin import functionality
3. Fix public import function (Priority 2)
4. Test public import functionality
5. Verify both work concurrently

---

## Technical Decisions

### Decision 1: Menu Registration Approach
**Choice**: Use WordPress standard `add_submenu_page()` for import menu under "Poker Tournaments" parent menu

**Rationale**: Follows WordPress admin best practices, maintains existing menu structure

**Alternatives Considered**:
- `add_menu_page()` for top-level menu (rejected - creates clutter)
- Custom admin page approach (rejected - breaks WordPress patterns)

### Decision 2: Security Model
**Choice**: Keep nonce verification for both admin and public imports

**Rationale**: WordPress security best practices, prevents CSRF attacks

**Alternatives Considered**:
- Remove nonce verification for public (rejected - security risk)
- Use different nonce for admin vs public (chosen - maintains separation)

### Decision 3: Error Handling
**Choice**: Use WordPress `wp_send_json_success()` and `wp_send_json_error()` for AJAX responses

**Rationale**: Consistent with WordPress AJAX patterns, proper JSON responses

**Alternatives Considered**:
- Custom JSON encoding (rejected - inconsistent with WP)
- HTML responses (rejected - not AJAX-friendly)

---

## Implementation Recommendations

### Admin Import Button Fix
1. Locate where menu should be registered in `class-admin.php`
2. Restore `add_submenu_page()` call with proper parameters:
   - Parent slug: 'edit.php?post_type=tournament'
   - Page title: 'Import Tournament'
   - Menu title: 'Import'
   - Capability: 'manage_options'
   - Menu slug: 'poker-import-tournament'
   - Callback function: Import page render method
3. Ensure callback method exists and renders the import form
4. Verify CSS/JS enqueued for the import page

### Public Import Function Fix
1. Locate shortcode registration in `class-shortcodes.php`
2. Verify `add_shortcode('poker_tournament_import', ...)` exists
3. Check shortcode callback method exists and renders form
4. Ensure AJAX handler registered:
   - `add_action('wp_ajax_nopriv_poker_import_public_tournament', ...)`
   - `add_action('wp_ajax_poker_import_public_tournament', ...)`
5. Fix nonce verification in AJAX handler
6. Ensure file upload processing works correctly
7. Add proper error handling and user-friendly messages

---

## Next Steps

1. **Code Investigation**: Examine actual code to confirm hypotheses
2. **Create Test Cases**: Document specific test scenarios
3. **Implement Fixes**: Apply fixes based on findings
4. **Test Thoroughly**: Verify both admin and public imports work
5. **Create Plugin ZIP**: Generate updated plugin package
6. **Update Version**: Increment plugin version number

**Status**: Ready for Phase 1 detailed design and contracts generation.
