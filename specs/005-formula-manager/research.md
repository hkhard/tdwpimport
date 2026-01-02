# Research: Fix Tournament Formula Manager Page Interactions

**Feature**: Formula Manager JavaScript Bug Fix
**Date**: 2025-01-02
**Status**: Complete

## Overview

This document captures technical research and design decisions for restoring JavaScript functionality to the Tournament Formula Manager admin page.

## Research Questions & Decisions

### 1. External JS vs Inline Script

**Question**: Should modal functions be added to external `formula-manager.js` or as inline script via `wp_add_inline_script()`?

**Investigation**:
- Current page loads `formula-manager.js` via `wp_enqueue_script()` (formula-manager-page.php:25)
- WordPress best practices favor external JS files for better caching
- Inline scripts via `wp_add_inline_script()` are best for small dynamic data (e.g., localized strings)

**Decision**: Add modal functions to external `formula-manager.js`

**Rationale**:
- Better browser caching (external file cached across page loads)
- Separation of concerns (behavior in JS, structure in PHP)
- Easier to maintain and debug
- Follows WordPress coding standards

### 2. jQuery Modal Pattern

**Question**: Which jQuery pattern for show/hide modals?

**Options Considered**:
1. `.show()/.hide()` - Instant visibility toggle
2. `.fadeIn()/.fadeOut()` - Fade animation (300ms default)
3. `.slideDown()/.slideUp()` - Slide animation (300ms default)
4. `.toggleClass()` - CSS class-based visibility

**Decision**: Use `.show()/.hide()`

**Rationale**:
- Performance goal SC-003 requires <500ms modal open
- Instant show/hide is fastest (~1-5ms)
- No animation needed for admin interface
- Simpler code, fewer edge cases

**Implementation Pattern**:
```javascript
// Show modal
$('#formula-editor-modal').show();

// Hide modal
$('#formula-editor-modal').hide();
```

### 3. Tab Switching Pattern

**Question**: How to handle WordPress nav-tab clicks?

**Options Considered**:
1. Event delegation on `.nav-tab` class (single handler)
2. Individual click handlers per tab (4 handlers)
3. WordPress core `nav-tab.js` (if available)

**Investigation**:
- Page uses WordPress `nav-tab-wrapper` and `nav-tab` CSS classes
- Tab links have `data-target` attributes (e.g., `data-target="var-tab-tournament"`)
- WordPress doesn't provide core nav-tab.js for this use case

**Decision**: Event delegation on `.nav-tab` class

**Rationale**:
- Single handler for all tabs
- Dynamic tab support (add/remove tabs without new handlers)
- WordPress standard pattern for admin tabs
- Meets performance goal SC-004 (<200ms tab switch)

**Implementation Pattern**:
```javascript
$('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
    e.preventDefault();
    var target = $(this).data('target');

    // Hide all tabs
    $('.tab-content').hide();

    // Show target tab
    $('#' + target).show();

    // Update active class
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
});
```

### 4. Data Loading for Edit Modal

**Question**: How to populate modal with formula data when editing?

**Options Considered**:
1. AJAX fetch from server (`wp_ajax_get_formula`)
2. Data attributes on Edit button (`data-*` attributes)
3. Global JavaScript object with all formulas
4. Hidden form fields in page

**Investigation**:
- PHP template renders formula list in table (formula-manager-page.php:417-455)
- Edit button already has `data-key` attribute
- Formula data already loaded in page context (available via PHP loop)
- AJAX would add unnecessary server round-trip

**Decision**: Data attributes on Edit button (extend existing `data-key`)

**Rationale**:
- Formula data already in page memory (PHP loop during render)
- No additional AJAX call needed (faster, meets SC-003)
- Simpler implementation
- Works offline after page load

**Implementation Pattern**:
```php
// In PHP template (extend existing Edit button)
<button type="button" class="button edit-formula"
        data-key="<?php echo esc_attr($key); ?>"
        data-display-name="<?php echo esc_attr($formula['name']); ?>"
        data-description="<?php echo esc_attr($formula['description']); ?>"
        data-category="<?php echo esc_attr($formula['category']); ?>"
        data-dependencies="<?php echo esc_attr(json_encode($formula['dependencies'])); ?>"
        data-expression="<?php echo esc_attr($formula['expression']); ?>">
    Edit
</button>
```

```javascript
// In JavaScript
function openFormulaModal(key) {
    var $button = $('button[data-key="' + key + '"]');
    var data = $button.data();

    $('#formula-display-name').val(data.displayName);
    $('#formula-description').val(data.description);
    $('#formula-category').val(data.category);
    $('#formula-expression').val(data.expression);

    $('#formula-editor-modal').show();
}
```

### 5. AJAX Endpoints for Save/Delete

**Question**: What endpoints exist for formula save/delete operations?

**Investigation Needed**: MUST VERIFY during implementation

**Current Status**:
- PHP template has form with `#formula-editor-form`
- No visible AJAX handlers in `class-admin.php` (needs grep search)
- May use WordPress `admin-post.php` pattern instead of AJAX

**Fallback Plan** (if no AJAX endpoints):
1. Use standard form submission with POST
2. Add WordPress nonce verification
3. Redirect back to Formula Manager with success/error notice
4. Pattern: `<form action="<?php echo admin_url('admin-post.php'); ?>" method="post">`

**Verification Steps** (during implementation):
1. Grep `wp_ajax_` in `class-admin.php`
2. Check for `admin_post_` hooks
3. If neither exists, implement `admin-post.php` pattern

## Technical Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| JS file location | External `formula-manager.js` | Caching, separation of concerns |
| Modal show/hide | `.show()/.hide()` | Fastest, meets SC-003 (<500ms) |
| Tab switching | Event delegation on `.nav-tab` | Single handler, WordPress standard |
| Edit data loading | Data attributes on button | No AJAX needed, data in page |
| Save/delete endpoints | **NEEDS CLARIFICATION** | Will verify AJAX or admin-post |

## Additional Considerations

### Performance Requirements

From Success Criteria:
- **SC-003**: Modal open <500ms → Use `.show()` (instant)
- **SC-004**: Tab switch <200ms → Use event delegation (minimal DOM traversal)

### WordPress Coding Standards

- Use `jQuery` instead of `$` in WordPress context (or wrap in IIFE)
- Sanitize all data from PHP with `esc_js()`, `esc_attr()`
- Use WordPress admin notice CSS for success/error messages
- Nonce verification for any form submission (AJAX or POST)

### Browser Compatibility

- Target: WordPress 6.0+ admin dashboard
- ES5+ JavaScript (no arrow functions, const/let if supporting older browsers)
- jQuery 1.12.4+ (WordPress bundled version)

## Open Questions

1. **AJAX endpoints exist?** - Must verify `wp_ajax_save_formula` / `wp_ajax_delete_formula` hooks
2. **Formula validation?** - Does `Poker_Tournament_Formula_Validator` have AJAX test endpoint?
3. **Default formula protection?** - How to prevent deletion of default formulas (client-side or server-side)?

## Next Steps

1. **Phase 1 Design**: Create `data-model.md` with formula entity structure
2. **Phase 1 Design**: Create `contracts/` with verified AJAX endpoints
3. **Phase 1 Design**: Create `quickstart.md` with implementation guide
4. **Phase 2 Tasks**: Generate task list via `/speckit.tasks`
5. **Implementation**: Add JavaScript functions to `formula-manager.js`
