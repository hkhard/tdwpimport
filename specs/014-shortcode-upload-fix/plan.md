# Implementation Plan: Frontend Tournament Import Shortcode Fix

**Branch**: `014-shortcode-upload-fix` | **Date**: 2025-01-06 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/014-shortcode-upload-fix/spec.md`

## Summary

The `tdwp_tournament_import` shortcode file upload functionality is broken because the form uses jQuery (`$.ajax` at line 5457) but the shortcode never enqueues jQuery on frontend pages. This causes `$ is not defined` JavaScript errors, preventing AJAX file uploads from working.

**Root Cause**: Missing `wp_enqueue_script('jquery')` call in the shortcode handler

**Fix**: Add jQuery dependency loading to the shortcode (similar to how `poker_dashboard_shortcode` already does this at line 2629)

## Technical Context

**Language/Version**: PHP 8.0+ (WordPress 6.0+ environment)
**Primary Dependencies**: jQuery (WordPress core dependency), WordPress AJAX API
**Storage**: WordPress MySQL database (wp_ prefix) - no schema changes required
**Testing**: Manual browser testing (JavaScript console, file upload)
**Target Platform**: WordPress frontend pages (any theme)
**Project Type**: WordPress plugin (single codebase)
**Performance Goals**: Page load impact <100ms, AJAX upload <30s for 5MB files
**Constraints**: WordPress Coding Standards, backward compatibility, theme independence
**Scale/Scope**: Single shortcode function modification, ~5 lines of code

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: ✅ PASSED - No constitution violations

This is a simple bug fix with:
- Minimal scope (single function modification)
- No new features or complexity
- Follows existing patterns in the codebase (see `poker_dashboard_shortcode` at line 2629)
- No new dependencies or architecture changes

## Project Structure

### Documentation (this feature)

```text
specs/014-shortcode-upload-fix/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (N/A - no data model changes)
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (N/A - no new contracts)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── includes/
│   └── class-shortcodes.php    # MODIFIED: Add jQuery enqueue to tournament_import_shortcode()
└── poker-tournament-import.php # Version bump (if needed)
```

**Structure Decision**: Single WordPress plugin structure. The fix is isolated to one method in one file (`class-shortcodes.php`).

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| N/A | N/A | No violations - simple bug fix |

---

## Phase 0: Research & Technical Decisions

### Research Tasks

1. **WordPress jQuery Enqueuing Best Practices**
   - Question: Should we use `wp_enqueue_script()` in the shortcode or hook into `wp_enqueue_scripts`?
   - Decision: Use inline `wp_enqueue_script()` within shortcode (follows existing pattern at line 2629)
   - Rationale: Keeps code co-located, only loads when shortcode is actually used, follows existing pattern in codebase

2. **jQuery Dependency in WordPress**
   - Question: Is jQuery always available in WordPress?
   - Decision: Yes, jQuery is a WordPress core dependency, but must be explicitly enqueued for frontend
   - Rationale: WordPress includes jQuery but doesn't load it on frontend by default (admin area auto-loads it)

3. **Backward Compatibility**
   - Question: Will adding `wp_enqueue_script('jquery')` break anything?
   - Decision: No, jQuery is already used throughout the plugin (admin interfaces, other shortcodes)
   - Rationale: jQuery is a WordPress core dependency, already loaded in admin, we're just ensuring it loads on frontend

### Research Output

See [research.md](./research.md) for detailed findings.

---

## Phase 1: Design & Implementation

### Data Model Changes

**N/A** - This fix does not involve any data model changes. No database schema, no new post types, no new options.

### API/Interface Contracts

**N/A** - This fix does not introduce new APIs or change existing contracts. The AJAX handler `ajax_frontend_import_tournament` already exists and is registered correctly.

### Implementation Approach

#### File: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`

**Location**: `tournament_import_shortcode()` method (starting at line 5346)

**Change Required**:

```php
public function tournament_import_shortcode($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="notice notice-info"><p>' .
            esc_html__('Please log in to import tournaments.', 'poker-tournament-import') .
            '</p></div>';
    }

    // Check if user has permission to import
    if (!current_user_can('edit_posts')) {
        return '<div class="notice notice-warning"><p>' .
            esc_html__('You do not have permission to import tournaments.', 'poker-tournament-import') .
            '</p></div>';
    }

    // === ADD THIS SECTION ===
    // Enqueue jQuery for frontend AJAX functionality
    // Follows same pattern as poker_dashboard_shortcode (line 2629)
    wp_enqueue_script('jquery');
    // === END ADDITION ===

    // Prepare data for JavaScript (output inline to avoid script dependencies)
    $ajax_url = admin_url('admin-ajax.php');
    // ... rest of function unchanged
}
```

**Placement**: After permission checks, before preparing JavaScript data (around line 5360)

**Rationale**:
- Placed after early returns to avoid unnecessary script loading for unauthorized users
- Matches existing pattern in `poker_dashboard_shortcode` method (line 2629)
- Minimal code change (single function call)
- Zero risk of breaking existing functionality

### Testing Strategy

#### Manual Testing Checklist

1. **Permission Checks**:
   - [ ] Not logged in: See "Please log in" message
   - [ ] Logged in without `edit_posts`: See "permission" message
   - [ ] Logged in with `edit_posts`: Form displays correctly

2. **JavaScript Console**:
   - [ ] Page load: No `$ is not defined` errors
   - [ ] jQuery loaded: Type `$` in console → returns function, not undefined
   - [ ] No other JavaScript errors related to missing dependencies

3. **File Upload Flow**:
   - [ ] Select .tdt file: File input accepts file
   - [ ] Click Import: "Uploading..." status appears
   - [ ] AJAX request: Network tab shows POST to admin-ajax.php
   - [ ] Success: Tournament created, success message shows
   - [ ] Form reset: Input clears after success

4. **Error Handling**:
   - [ ] Non-.tdt file: Alert shows "Please select a valid .tdt file"
   - [ ] Corrupted .tdt: Error message displays (handled by parser)
   - [ ] Network error: AJAX error handler shows details

5. **Cross-Theme Testing**:
   - [ ] Test with WordPress default theme (Twenty Twenty-Four)
   - [ ] Test with another theme to verify no conflicts

#### Browser Testing
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (if available)

---

## Phase 2: Task Breakdown

**NOTE**: This section will be populated by the `/speckit.tasks` command. Do not manually edit.

**Expected Tasks** (to be generated by tasks command):
1. Add `wp_enqueue_script('jquery')` to `tournament_import_shortcode()` method
2. Manual testing on local WordPress installation
3. Update plugin version (if needed)
4. Create distribution ZIP file

---

## Quickstart

### For Developers

**Reproduce the Bug**:
1. Add `[tdwp_tournament_import]` to a WordPress page
2. View page in browser (logged in with `edit_posts` permission)
3. Open browser console (F12)
4. See error: `Uncaught ReferenceError: $ is not defined`
5. Try uploading .tdt file → nothing happens

**Verify the Fix**:
1. Apply code change (add `wp_enqueue_script('jquery')`)
2. Refresh page
3. Check console: No `$ is not defined` error
4. Type `$` in console → should return `function(selector, context)`
5. Upload .tdt file → should work correctly

**File to Modify**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
**Function**: `tournament_import_shortcode()` (line 5346)
**Insertion Point**: After permission checks (after line 5359)
**Code to Add**: `wp_enqueue_script('jquery');`

### For Testers

**Prerequisites**:
- WordPress installation with plugin activated
- User account with `edit_posts` capability
- Valid .tdt file for testing

**Test Steps**:
1. Log in to WordPress
2. Create a new page with `[tdwp_tournament_import]` shortcode
3. Publish page
4. View page
5. Open browser console (Cmd+Option+I / F12)
6. Verify no errors in console
7. Select a .tdt file
8. Click "Import Tournament"
9. Verify "Uploading..." message appears
10. Verify tournament is created

**Expected Results**:
- No JavaScript errors
- File uploads successfully
- Tournament appears in WordPress admin

---

## Implementation Checklist

- [x] Phase 0: Research complete
- [x] Phase 1: Design documented
- [x] Data model reviewed (no changes needed)
- [x] API contracts reviewed (no changes needed)
- [x] Implementation approach defined
- [ ] Phase 2: Tasks generated (run `/speckit.tasks`)
- [ ] Code changes implemented
- [ ] Testing complete
- [ ] Version bump (if needed)
- [ ] Distribution ZIP created
