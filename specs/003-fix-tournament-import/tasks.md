# Task Breakdown: Fix Tournament Import Button and Public Import Function

**Branch**: `003-fix-tournament-import` | **Generated**: 2025-12-29
**Input**: [spec.md](spec.md) + [plan.md](plan.md)

## Overview

Fix two critical WordPress plugin regressions:
1. **Restore Admin Import Button** (User Story 1, P1)
2. **Fix Public Import Function** (User Story 2, P1)

This breakdown organizes work into sequential phases, parallel-safe tasks, and cross-cutting concerns.

---

## Phase 1: Setup & Environment

- [ ] [T1.1] [P0] [Setup] Enable WordPress debug mode in wp-config.php at `/Users/hkh/Library/Application Support/Local/ssh-entry/hNPsf2SE_.sh`
  - Add `define('WP_DEBUG', true);`
  - Add `define('WP_DEBUG_LOG', true);`
  - Add `define('WP_DEBUG_DISPLAY', false);`
  - Verify debug.log file creation

- [ ] [T1.2] [P0] [Setup] Create backup of current plugin state
  - Copy `wordpress-plugin/poker-tournament-import/` to backup location
  - Export current WordPress database state
  - Document backup locations for rollback

---

## Phase 2: Investigation & Root Cause Analysis

- [ ] [T2.1] [P1] [Story1] Examine admin menu registration in `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Search for `add_menu_page()` or `add_submenu_page()` calls
  - Verify `admin_menu` hook registration in `__construct()`
  - Check capability checks (`manage_options` or similar)
  - Document findings in research notes

- [ ] [T2.2] [P1] [Story2] Examine public shortcode handler in `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Search for `add_shortcode('poker_tournament_import', ...)`
  - Locate AJAX handler registration (`wp_ajax_nopriv_poker_import_public_tournament`)
  - Verify nonce verification code
  - Document error sources

- [ ] [T2.3] [P1] [Story1] Check git history for recent changes to `admin/class-admin.php`
  - Run `git log --oneline --all -- wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Identify commits from merged PRs (#1-#6) that modified menu registration
  - Diff working vs. broken versions
  - Identify specific code removals or changes

- [ ] [T2.4] [P1] [Story2] Check git history for recent changes to `includes/class-shortcodes.php`
  - Run `git log --oneline --all -- wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Identify commits that modified shortcode or AJAX handlers
  - Diff working vs. broken versions
  - Identify specific code removals or changes

- [ ] [T2.5] [P1] [Story1] Verify import page template exists at `wordpress-plugin/poker-tournament-import/admin/bulk-import-page.php`
  - Check if file exists and is readable
  - Verify template rendering function is called
  - Check for PHP syntax errors: `php -l bulk-import-page.php`

- [ ] [T2.6] [P1] [Story2] Check for missing hook registrations in main plugin file
  - Examine `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
  - Verify all required hooks are registered:
    - `admin_menu` for admin import
    - `wp_ajax_*` and `wp_ajax_nopriv_*` for AJAX handlers
    - `init` for shortcode registration
  - Document missing hooks

**Parallel Execution**: Tasks T2.1-T2.4 can be executed in parallel (2 pairs: [T2.1, T2.2] and [T2.3, T2.4])

---

## Phase 3: User Story 1 - Restore Admin Import Button (P1)

- [ ] [T3.1] [P1] [Story1] Restore missing `add_submenu_page()` call in `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Locate `add_plugin_menu()` method
  - Add or restore submenu page registration:
    ```php
    add_submenu_page(
        'edit.php?post_type=tournament',
        'Import Tournament',
        'Import',
        'manage_options',
        'poker-import-tournament',
        array($this, 'import_page_callback')
    );
    ```
  - Verify capability check is `manage_options`
  - Run PHP syntax check: `php -l class-admin.php`

- [ ] [T3.2] [P1] [Story1] Verify import page callback method exists in `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Check for `import_page_callback()` or similar method
  - If missing, create method to render import form
  - Include nonce field: `wp_nonce_field('poker_import_nonce')`
  - Run PHP syntax check: `php -l class-admin.php`

- [ ] [T3.3] [P1] [Story1] Verify admin menu hook is properly registered in `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Check `__construct()` for `add_action('admin_menu', array($this, 'add_plugin_menu'))`
  - If missing, add hook registration
  - Run PHP syntax check: `php -l class-admin.php`

- [ ] [T3.4] [P1] [Story1] Test admin import button visibility in WordPress dashboard
  - Log into WordPress admin at `/wp-admin`
  - Navigate to "Poker Tournaments" menu in sidebar
  - Verify "Import" submenu item is visible
  - Click on Import menu item
  - Verify import page loads without PHP errors
  - Check `wp-content/debug.log` for any errors

- [ ] [T3.5] [P1] [Story1] Verify admin AJAX handler is registered in `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
  - Check for `add_action('wp_ajax_poker_import_tournament', ...)` hook
  - If missing, add AJAX handler registration
  - Verify callback function exists and handles file upload
  - Run PHP syntax check: `php -l poker-tournament-import.php`

- [ ] [T3.6] [P1] [Story1] Test admin import functionality with valid .tdt file
  - Prepare test .tdt file
  - Upload file through admin import form
  - Verify AJAX request succeeds (check browser DevTools Network tab)
  - Verify tournament post is created in WordPress database
  - Verify tournament is published
  - Check `wp-content/debug.log` for errors

**Dependencies**: T3.1-T3.3 must complete sequentially before T3.4-T3.5 (which can be parallel). T3.6 depends on T3.4 and T3.5.

---

## Phase 4: User Story 2 - Fix Public Import Function (P1)

- [ ] [T4.1] [P1] [Story2] Restore missing shortcode registration in `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Search for `add_shortcode('poker_tournament_import', ...)`
  - If missing, add shortcode registration in `__construct()` or init hook
  - Verify callback method exists: `import_shortcode()` or similar
  - Run PHP syntax check: `php -l class-shortcodes.php`

- [ ] [T4.2] [P1] [Story2] Verify shortcode callback method renders import form in `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Check callback method outputs:
    - File upload form HTML (`<input type="file">`)
    - Nonce field (`wp_nonce_field('poker_import_public_nonce')`)
    - Submit button
    - Success/error message containers
  - If missing, create form HTML following WordPress standards
  - Run PHP syntax check: `php -l class-shortcodes.php`

- [ ] [T4.3] [P1] [Story2] Restore AJAX handler for public import in `wordpress-plugin/poker-tournament-import/poker-tournament-import.php` or `includes/class-shortcodes.php`
  - Check for `add_action('wp_ajax_nopriv_poker_import_public_tournament', ...)`
  - Check for `add_action('wp_ajax_poker_import_public_tournament', ...)` (for logged-in users)
  - If missing, add AJAX handler registration
  - Verify callback function exists
  - Run PHP syntax check on modified file

- [ ] [T4.4] [P1] [Story2] Fix nonce verification in public import AJAX handler
  - Verify `check_ajax_referer('poker_import_public_nonce', 'nonce')` is called
  - If failing, fix nonce name or verification method
  - Ensure error response is user-friendly on nonce failure
  - Run PHP syntax check on modified file

- [ ] [T4.5] [P1] [Story2] Verify file upload handling in public import AJAX handler
  - Check `$_FILES` array is properly accessed
  - Verify file validation: .tdt extension, file size limits
  - Verify parser is called: `Poker_Tournament_Parser::parse_file()`
  - Verify tournament creation: `wp_insert_post()` with post_type='tournament'
  - Verify publish status: `wp_publish_post()` or set post_status='publish'
  - Run PHP syntax check on modified file

- [ ] [T4.6] [P1] [Story2] Add proper error handling to public import AJAX handler
  - Wrap file processing in try-catch block
  - Return JSON error response on failure:
    ```php
    wp_send_json_error(array(
        'error' => 'User-friendly error message',
        'code' => 'ERROR_CODE'
    ));
    ```
  - Log errors to WordPress debug log
  - Run PHP syntax check on modified file

- [ ] [T4.7] [P1] [Story2] Test public import form visibility on statistics page
  - Visit public statistics page URL
  - Verify shortcode `[poker_tournament_import]` is rendered as form
  - Verify file upload field is visible
  - Verify submit button is visible
  - Check browser console for JavaScript errors

- [ ] [T4.8] [P1] [Story2] Test public import functionality with valid .tdt file
  - Select valid .tdt file in public import form
  - Submit form
  - Verify AJAX request succeeds (check browser DevTools Network tab)
  - Verify success message appears on page
  - Verify tournament post is created in WordPress database
  - Verify tournament is published
  - Check `wp-content/debug.log` for errors

- [ ] [T4.9] [P1] [Story2] Test public import error handling with invalid file
  - Select invalid file (e.g., .txt, .jpg) in public import form
  - Submit form
  - Verify error message appears on page
  - Verify error message is user-friendly
  - Verify no tournament post is created
  - Check `wp-content/debug.log` for errors

**Dependencies**: T4.1-T4.2 must complete sequentially. T4.3-T4.6 can be parallel (within same file, may need sequential depending on code structure). T4.7-T4.9 must be sequential.

---

## Phase 5: Quality Assurance & Security Validation

- [ ] [T5.1] [P0] [Security] Verify nonce verification on all AJAX handlers
  - Check admin import: `check_ajax_referer('poker_import_nonce', 'nonce')`
  - Check public import: `check_ajax_referer('poker_import_public_nonce', 'nonce')`
  - Test with invalid nonce to verify rejection
  - Document nonce names for future reference

- [ ] [T5.2] [P0] [Security] Verify capability checks on admin import
  - Verify `current_user_can('manage_options')` is called
  - Test with non-admin user to verify access denial
  - Verify error message is appropriate
  - Document required capabilities

- [ ] [T5.3] [P0] [Security] Verify input sanitization on all user inputs
  - Check `sanitize_text_field()` usage on form inputs
  - Check `wp_kses_post()` usage on HTML content
  - Verify file upload validation (.tdt extension only)
  - Test with malicious input (script tags, SQL injection attempts)

- [ ] [T5.4] [P0] [Security] Verify output escaping on all displayed data
  - Check `esc_html()` usage on escaped output
  - Check `esc_attr()` usage on HTML attributes
  - Check `wp_kses_post()` usage on allowed HTML
  - Test with data containing HTML/JavaScript

- [ ] [T5.5] [P0] [Quality] Run PHP syntax check on all modified files
  - Run `php -l wordpress-plugin/poker-tournament-import/admin/class-admin.php`
  - Run `php -l wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
  - Run `php -l wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
  - Fix any syntax errors
  - Verify all files return "No syntax errors detected"

- [ ] [T5.6] [P0] [Quality] Check WordPress debug log for errors and warnings
  - Open `wp-content/debug.log`
  - Search for PHP errors, warnings, or notices
  - Fix any issues found
  - Clear log and retest to verify no new errors

- [ ] [T5.7] [P0] [Quality] Verify internationalization (i18n) on all user-facing strings
  - Check for hardcoded English strings
  - Replace with `__('String', 'poker-tournament-import')`
  - Replace `_e('String', 'poker-tournament-import')` for echoed strings
  - Verify text domain is correct

**Parallel Execution**: All T5.1-T5.7 can be executed in parallel after Phase 3 and Phase 4 complete.

---

## Phase 6: Edge Cases & Error Handling

- [ ] [T6.1] [P2] [Story1] Test admin import with oversized file (exceeds PHP upload_max_filesize)
  - Attempt to upload file larger than PHP limit
  - Verify user-friendly error message
  - Verify no security exposure in error message

- [ ] [T6.2] [P2] [Story2] Test public import with oversized file
  - Attempt to upload file larger than PHP limit
  - Verify user-friendly error message
  - Verify no security exposure in error message

- [ ] [T6.3] [P2] [Story1] Test admin import with malformed .tdt file
  - Create or obtain corrupted .tdt file
  - Upload through admin import
  - Verify parser catches error gracefully
  - Verify error message is helpful

- [ ] [T6.4] [P2] [Story2] Test public import with malformed .tdt file
  - Create or obtain corrupted .tdt file
  - Upload through public import
  - Verify parser catches error gracefully
  - Verify error message is helpful

- [ ] [T6.5] [P2] [Story1] Test admin import with non-Latin filename
  - Create .tdt file with Unicode characters in filename
  - Upload through admin import
  - Verify file is processed correctly
  - Verify filename is handled properly

- [ ] [T6.6] [P2] [Story2] Test public import with non-Latin filename
  - Create .tdt file with Unicode characters in filename
  - Upload through public import
  - Verify file is processed correctly
  - Verify filename is handled properly

- [ ] [T6.7] [P2] [Story1] Test concurrent admin import attempts
  - Open multiple browser tabs to admin import page
  - Simultaneously upload different .tdt files
  - Verify both tournaments are created correctly
  - Verify no data corruption

- [ ] [T6.8] [P2] [Story2] Test concurrent public import attempts
  - Open multiple browser tabs to public statistics page
  - Simultaneously upload different .tdt files
  - Verify both tournaments are created correctly
  - Verify no data corruption

**Parallel Execution**: T6.1-T6.2 can be parallel, T6.3-T6.4 can be parallel, T6.5-T6.6 can be parallel. T6.7-T6.8 should be sequential (require coordinated testing).

---

## Phase 7: Documentation & Deployment

- [ ] [T7.1] [P1] [Documentation] Update plugin version number in `wordpress-plugin/poker-tournament-import/poker-tournament-import.php`
  - Increment version constant `POKER_TOURNAMENT_IMPORT_VERSION`
  - Update plugin header comment version
  - Update version in readme.txt if present
  - Document version change (e.g., "2.4.3 - Fixed admin and public import functionality")

- [ ] [T7.2] [P1] [Deployment] Create updated plugin ZIP file
  - Navigate to `wordpress-plugin/` directory
  - Create ZIP: `zip -r poker-tournament-import-v2.4.3.zip poker-tournament-import/ -x "*.git*" "*node_modules*"`
  - Verify ZIP file structure
  - Test ZIP by extracting and verifying files

- [ ] [T7.3] [P1] [Documentation] Document bug fixes in changelog
  - Update `changelog.txt` or `readme.txt` with:
    - "Fixed: Admin import button now visible in WordPress dashboard"
    - "Fixed: Public import function on statistics page now works correctly"
  - Include version number and date

- [ ] [T7.4] [P1] [Quality] Final integration testing
  - Deactivate old plugin version
  - Upload and activate new ZIP file
  - Retest admin import functionality
  - Retest public import functionality
  - Verify no errors in debug log
  - Document test results

**Dependencies**: T7.1-T7.3 can be parallel. T7.4 must be sequential after T7.2.

---

## Phase 8: Final Polish & Cross-Cutting Concerns

- [ ] [T8.1] [P2] [Polish] Verify CSS styling on admin import page
  - Check for broken styles or layout issues
  - Verify responsive design on mobile devices
  - Ensure consistency with WordPress admin UI

- [ ] [T8.2] [P2] [Polish] Verify CSS styling on public import form
  - Check for broken styles or layout issues
  - Verify responsive design on mobile devices
  - Ensure consistency with site theme

- [ ] [T8.3] [P2] [Polish] Add loading indicators during file upload
  - Add spinner or progress bar to admin import form
  - Add spinner or progress bar to public import form
  - Improve UX during file processing

- [ ] [T8.4] [P2] [Accessibility] Verify ARIA labels and keyboard navigation
  - Add `aria-label` attributes to file inputs
  - Verify form is navigable via keyboard
  - Verify screen reader compatibility

- [ ] [T8.5] [P2] [Performance] Measure import processing time
  - Time import of 500-player .tdt file
  - Verify processing completes within 30 seconds (success criterion SC-004)
  - Optimize if needed

- [ ] [T8.6] [P2] [Cleanup] Remove debug mode settings from wp-config.php
  - Comment out or remove `WP_DEBUG` settings after testing
  - Or leave enabled if monitoring production
  - Document final debug configuration

**Parallel Execution**: All T8.1-T8.6 can be executed in parallel after Phase 7.

---

## Task Summary

**Total Tasks**: 54
- Phase 1 (Setup): 2 tasks
- Phase 2 (Investigation): 6 tasks
- Phase 3 (Story 1 - Admin Import): 6 tasks
- Phase 4 (Story 2 - Public Import): 9 tasks
- Phase 5 (Quality & Security): 7 tasks
- Phase 6 (Edge Cases): 8 tasks
- Phase 7 (Documentation & Deployment): 4 tasks
- Phase 8 (Final Polish): 6 tasks

**Priority Distribution**:
- P0 (Critical): 15 tasks (setup, security, quality gates)
- P1 (High): 27 tasks (core functionality for both stories)
- P2 (Medium): 12 tasks (edge cases, polish, accessibility)

**Critical Path** (must be sequential):
T1.1 → T2.1 → T3.1 → T3.2 → T3.3 → T3.4 → T3.5 → T3.6 → T5.5 → T7.1 → T7.2 → T7.4

**Parallel Opportunities**:
- Investigation phase (T2.1-T2.4): 2 pairs can run in parallel
- Quality gates (T5.1-T5.7): All can run in parallel after fixes
- Edge cases (T6.1-T6.8): Multiple pairs can run in parallel
- Polish phase (T8.1-T8.6): All can run in parallel

---

## Execution Notes

**Pre-requisites**:
- WordPress 6.0+ installation with TDWP plugin activated
- Access to WordPress admin dashboard
- Access to wp-config.php for debug mode
- Test .tdt files (valid and invalid)
- Command-line access for PHP syntax checks

**Estimated Effort**:
- Phase 1-2 (Investigation): 2-3 hours
- Phase 3 (Admin Import): 3-4 hours
- Phase 4 (Public Import): 4-5 hours
- Phase 5-6 (Quality & Edge Cases): 3-4 hours
- Phase 7-8 (Deployment & Polish): 2-3 hours

**Total Estimate**: 14-19 hours

**Risk Mitigation**:
- Always backup before making changes (T1.2)
- Test in staging environment first
- Keep debug log enabled throughout process
- Rollback plan: Restore backup from T1.2 if critical failure occurs
