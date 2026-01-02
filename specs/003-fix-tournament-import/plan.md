# Implementation Plan: Fix Tournament Import Button and Public Import Function

**Branch**: `003-fix-tournament-import` | **Date**: 2025-12-29 | **Status**: ✅ **COMPLETE**
**Spec**: [spec.md](spec.md)

## Completion Notice

**Date Completed**: 2025-12-30
**Implementation Status**: ✅ All fixes implemented and tested
**Distribution**: Multiple beta versions created

### What Was Accomplished

1. **Admin Import Button**: Restored missing tournament import functionality in WordPress admin dashboard
2. **Public Import Function**: Fixed public-facing statistics page import that was failing on upload
3. **Root Cause Fixed**: Corrected AJAX handler registration and form submission issues
4. **Security Maintained**: All fixes include proper nonce verification and capability checks

### Files Modified

- `wordpress-plugin/poker-tournament-import/admin/class-admin.php` (AJAX handlers)
- `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` (public import)
- Template files for import pages

### Testing

Both admin and public import flows tested and verified working.

---

## Summary (Original)

Fix two critical regressions in the TDWP WordPress plugin:
1. Restore missing tournament import button in WordPress admin dashboard
2. Fix public-facing statistics page import function that fails on upload

Technical approach: Investigate recent changes to admin menu registration and shortcode handlers, restore missing functionality while maintaining WordPress security standards (nonce verification, capability checks).

## Technical Context

**Language/Version**: PHP 8.0+ (8.2+ compatible)
**Primary Dependencies**: WordPress 6.0+, WordPress Coding Standards
**Storage**: WordPress MySQL database (wp_ prefix)
**Testing**: Manual WordPress admin testing, PHP syntax validation (`php -l`)
**Target Platform**: WordPress plugin (admin dashboard + public frontend)
**Project Type**: web (WordPress plugin)
**Performance Goals**: <500ms p95 for import processing
**Constraints**: Must follow WordPress security best practices (nonce verification, prepared statements, capability checks)
**Scale/Scope**: Single-site WordPress installation, supporting up to 10,000+ player tournaments

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Code Quality & Standards Compliance
- [x] PHP 8.0+ with 8.2+ compatibility - will use declare dynamic properties if needed
- [x] WordPress Coding Standards - follow WP naming conventions
- [x] Proper internationalization - use `__()`, `_e()` with text domain
- [x] No underscore prefixes for custom variables
- [x] Class-based architecture

### Security First (NON-NEGOTIABLE)
- [x] Input sanitization: `sanitize_text_field()`, `wp_kses_post()`
- [x] Nonce verification for ALL AJAX handlers
- [x] Capability checks: `current_user_can('manage_options')` for admin operations
- [x] File upload validation with .tdt format verification
- [x] Prepared statements for all database operations
- [x] Output escaping: `esc_html()`, `esc_attr()`, `wp_kses_post()`

### Performance & Scalability
- [x] Transient caching where appropriate
- [x] Proper database indexing
- [x] Stream large .tdt file parsing
- [x] <500ms p95 target for import processing

### Testing Discipline
- [x] PHP syntax check: `php -l` on all changed files
- [x] Manual testing in WordPress admin
- [x] Test with valid and invalid .tdt files
- [x] Verify security measures (nonce, capabilities)

### Multi-Platform Coordination
- [x] WordPress plugin: Data persistence, admin interface, public display
- [x] No controller/mobile changes needed for this fix

**Status**: ✅ PASSED - No constitution violations expected

## Project Structure

### Documentation (this feature)

```text
specs/003-fix-tournament-import/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── poker-tournament-import.php  # Main plugin file
├── admin/
│   ├── class-admin.php           # Admin menu registration (PRIORITY 1)
│   └── bulk-import-page.php      # Import page template
└── includes/
    ├── class-shortcodes.php      # Public-facing shortcodes (PRIORITY 2)
    ├── class-parser.php          # .tdt file parser
    └── class-post-types.php      # Tournament custom post type
```

**Structure Decision**: WordPress plugin structure - admin classes handle dashboard functionality, includes classes handle public-facing features and core logic.

## Phase 0: Research & Investigation

### Research Tasks

1. **Investigate Admin Menu Registration**
   - Task: Examine `wordpress-plugin/poker-tournament-import/admin/class-admin.php` to understand how the import menu is registered
   - Deliverable: Document the menu registration code and identify what might be missing
   - Key questions: Is `add_menu_page` or `add_submenu_page` called? Is there a capability check issue?

2. **Investigate Public Import Shortcode**
   - Task: Examine `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` to find the public statistics import function
   - Deliverable: Document the shortcode handler and identify the error source
   - Key questions: Is the shortcode registered? Does the AJAX handler exist? Is nonce verification failing?

3. **Review Recent Changes**
   - Task: Check git history for recent modifications to admin and shortcode files
   - Deliverable: Identify commits that may have introduced the regressions
   - Key questions: What changed between working and broken state?

4. **Check for Missing Dependencies**
   - Task: Verify all required WordPress hooks and filters are properly registered
   - Deliverable: Document any missing hook registrations
   - Key questions: Are actions/fires hooks properly set up?

**Output**: `research.md` with findings, root cause analysis, and recommended fixes

## Phase 1: Design & Contracts

### Data Model

The existing WordPress data model is used:
- **Tournament Post**: Custom post type `tournament` in WordPress database
- **Tournament Import Request**: Transient state during upload process
- **Import Error Log**: WordPress error log or custom database table

### API Contracts

#### Admin Import AJAX Endpoint
```php
// AJAX action: wp_ajax_poker_import_tournament / wp_ajax_nopriv_poker_import_public_tournament
Request:
  - file: .tdt file upload
  - nonce: WordPress nonce for security
  - action: 'poker_import_tournament' or 'poker_import_public_tournament'

Response (Success):
  - success: true
  - tournament_id: WordPress post ID
  - message: "Tournament imported successfully"

Response (Error):
  - success: false
  - error: Error message (user-friendly)
  - code: Error code for troubleshooting
```

#### Public Shortcode Contract
```php
// Shortcode: [poker_tournament_import]
Attributes:
  - None required

Output:
  - File upload form HTML
  - Nonce field for security
  - AJAX handler binding
  - Success/error message containers
```

### Security Requirements

1. **Admin Import Button**:
   - Capability check: `current_user_can('manage_options')` or custom capability
   - Nonce verification: `check_ajax_referer()` for AJAX actions
   - Input sanitization: `sanitize_text_field()` for all user inputs
   - File validation: Check .tdt file extension and magic bytes

2. **Public Import Function**:
   - Nonce verification: `check_ajax_referer()` for AJAX actions
   - Rate limiting: Consider limiting public import attempts
   - File validation: Strict .tdt format validation
   - Output escaping: `esc_html()` for all displayed data

## Phase 2: Implementation Strategy

### Fix Priority 1: Restore Admin Import Button

**Investigation Steps**:
1. Check `class-admin.php` for menu registration code
2. Verify admin menu hook is properly registered
3. Check if capability checks are hiding the menu
4. Verify the import page template exists

**Likely Fixes**:
- Restore missing `add_menu_page()` or `add_submenu_page()` call
- Fix capability check if too restrictive
- Restore missing import page template
- Fix hook registration timing

### Fix Priority 2: Fix Public Import Function

**Investigation Steps**:
1. Locate the public statistics shortcode handler
2. Check if shortcode is registered in `class-shortcodes.php`
3. Verify AJAX handler exists and is properly hooked
4. Test nonce verification flow
5. Check file upload handling code

**Likely Fixes**:
- Restore missing shortcode registration
- Fix AJAX handler hook
- Fix nonce verification if causing errors
- Restore file upload processing code
- Fix error handling and response format

### Testing Checklist

- [ ] Admin import button visible in WordPress dashboard
- [ ] Admin can access import page without errors
- [ ] Admin can successfully upload .tdt file
- [ ] Tournament is created and published after admin import
- [ ] Public statistics page displays import form
- [ ] Public users can upload .tdt file
- [ ] Tournament is created and published after public import
- [ ] Invalid file uploads show appropriate error messages
- [ ] Nonce verification passes for both admin and public imports
- [ ] PHP syntax check passes on all modified files
- [ ] No WordPress PHP errors or warnings in debug log
