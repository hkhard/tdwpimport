# Implementation Plan: Fix Invalid HTML Structure - Bustout Template

**Branch**: `feature/tournament-manager-phase2` | **Date**: 2025-10-30
**Spec**: `/specs/001-td3-integration/spec.md`
**Issue**: Bustout inline template renders visibly at top of page

## Summary

The bustout inline expansion template is incorrectly structured as a standalone `<tr>` element outside any `<table>`, violating HTML5 specification. This causes browsers to auto-correct the malformed DOM, rendering the template visibly at the top of the page near the "Add Player" and "Process Buy-ins" buttons instead of remaining hidden. The fix wraps the template in a valid `<table><tbody>` structure while maintaining JavaScript compatibility. Additionally fixes a colspan bug that prevents the inline expansion from spanning all table columns.

## Technical Context

**Language/Version**: PHP 8.0+, HTML5, CSS3, JavaScript (jQuery)
**Primary Dependencies**: WordPress 6.0+, jQuery (WordPress bundled)
**Storage**: N/A (DOM structure fix only)
**Testing**: PHP syntax validation (`php -l`), manual browser testing, HTML validation
**Target Platform**: WordPress admin interface (all modern browsers)
**Project Type**: WordPress plugin (server-rendered PHP with JavaScript enhancement)
**Performance Goals**: Zero performance impact (structure-only change)
**Constraints**: Must maintain backward compatibility with existing JavaScript clone logic
**Scale/Scope**: Single template element, 3 files modified, no database changes

## Constitution Check

*GATE: Must pass before implementation. No re-check needed after (HTML structure fix only).*

Reference: `.specify/memory/constitution.md`

### I. Security Compliance ✅
- [x] No data sanitization needed (pure HTML structure change)
- [x] No AJAX handlers modified
- [x] No capability checks needed
- [x] No database operations
- [x] No file uploads involved

**Status**: COMPLIANT - No security implications

### II. Code Quality Standards ✅
- [x] WordPress Coding Standards followed
- [x] Valid HTML5 structure created
- [x] Internationalization unchanged (no text changes)
- [x] PHPDoc unchanged (no function changes)
- [x] PHP 8.0+ compatible

**Status**: COMPLIANT - Improves HTML validity

### III. Testing Requirements ✅
- [x] PHP syntax validation planned (players-tab.php)
- [x] CSS syntax validation planned (tournament-control.css)
- [x] Manual browser testing planned (Chrome, Firefox, Safari)
- [x] HTML validation planned (W3C validator)
- [x] No memory efficiency concerns (no processing changes)

**Status**: COMPLIANT - Testing plan complete

### IV. User Experience ✅
- [x] WordPress admin UI patterns maintained
- [x] No user-facing changes (fix makes feature work correctly)
- [x] Frontend template compatibility N/A (admin only)
- [x] Accessibility unchanged (DOM structure fix)

**Status**: COMPLIANT - Improves UX by fixing broken feature

### V. Performance ✅
- [x] No caching changes needed
- [x] No database queries involved
- [x] No file handling
- [x] No async processing needed
- [x] Zero performance impact

**Status**: COMPLIANT - Structure-only change

## Project Structure

### Documentation (this bugfix)

```text
specs/001-td3-integration/
├── bugfix-invalid-html-plan.md           # Detailed technical plan
├── plan-bugfix-invalid-html.md           # This file (implementation plan)
├── tasks-bugfix-invalid-html.md          # Task breakdown
└── spec.md                                # Original feature spec
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── admin/
│   └── tabs/
│       └── players-tab.php              # MODIFIED: Wrap template in table
├── assets/
│   └── css/
│       └── tournament-control.css        # MODIFIED: Add wrapper CSS rule
├── poker-tournament-import.php           # MODIFIED: Version bump
└── assets/
    └── js/
        └── tournament-control.js         # NO CHANGES (JavaScript compatible)
```

**Structure Decision**: Minimal changes to existing WordPress plugin structure. Only modify template HTML, add one CSS rule, and bump version. JavaScript unchanged due to selector compatibility.

## Implementation Summary

### Root Cause
Standalone `<tr>` element outside table structure (players-tab.php:167) violates HTML5 spec, causing browser DOM auto-correction and visible rendering.

### Solution
Wrap template in valid `<table><tbody><tr>` structure, fix colspan bug, add defensive CSS.

### Changes
1. **players-tab.php**: Wrap template in table, fix colspan from 11/10 to 12/11
2. **tournament-control.css**: Add `.tdwp-bustout-template-table { display: none !important; }`
3. **poker-tournament-import.php**: Version 3.2.0-beta3.2 → 3.2.0-beta3.3

### Compatibility
✅ JavaScript clone logic unchanged (selector still finds template)
✅ CSS selectors still work
✅ Event handlers unaffected
✅ Backward compatible

## Complexity Tracking

No constitution violations - all checks passed.

## Success Criteria

- [ ] Template completely hidden on page load
- [ ] No visible element near "Add Player" button
- [ ] Inline expansion functions correctly on bust-out click
- [ ] Colspan spans all columns (11 or 12 depending on bounty)
- [ ] HTML passes W3C validation
- [ ] Cross-browser compatibility (Chrome, Firefox, Safari)
- [ ] Version bumped to 3.2.0-beta3.3
- [ ] ZIP distribution created

## Related Documentation

- **Detailed Technical Plan**: `bugfix-invalid-html-plan.md`
- **Task Breakdown**: `tasks-bugfix-invalid-html.md`
- **Original Feature Spec**: `spec.md` (User Story 5 - Live Player Operations)
- **Constitution**: `.specify/memory/constitution.md`

---

**Next Step**: Execute `/speckit.implement` to apply changes from tasks file.
