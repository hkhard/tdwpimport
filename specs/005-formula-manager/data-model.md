# Data Model: Tournament Formula Manager

**Feature**: Formula Manager JavaScript Bug Fix
**Date**: 2025-01-02
**Status**: Complete

## Overview

This document defines the data structures for the Tournament Formula Manager, focusing on the JavaScript-visible representation used by modal dialogs and tab navigation.

## Formula Entity

### Purpose

Represents a Tournament Director calculation formula used for tournament or season scoring.

### Structure

```javascript
{
    // Internal identifier (unique key)
    key: string,

    // Human-readable display name
    display_name: string,

    // Detailed description of formula purpose
    description: string,

    // Formula category
    category: "points" | "season" | "custom",

    // List of prerequisite formula keys
    dependencies: string[],

    // Calculation expression (Tournament Director formula language)
    expression: string,

    // Protection flag (default formulas cannot be deleted)
    is_default: boolean
}
```

### Field Definitions

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `key` | string | Yes | Internal unique identifier | `"points_total"` |
| `display_name` | string | Yes | Human-readable name for UI | `"Total Points"` |
| `description` | string | No | Explanation of formula logic | `"Sum of all tournament points"` |
| `category` | enum | Yes | Formula classification | `"points"` |
| `dependencies` | string[] | No | Prerequisite formula keys | `["points_played"]` |
| `expression` | string | Yes | Formula expression | `"n * 10 + r"` |
| `is_default` | boolean | Yes | Protection flag | `true` |

### Category Values

| Value | Description | Deletable |
|-------|-------------|-----------|
| `"points"` | Tournament scoring formula | No (if default) |
| `"season"` | Season standings formula | No (if default) |
| `"custom"` | User-defined formula | Yes |

## Modal DOM Structure

### Formula Editor Modal

**ID**: `#formula-editor-modal`

**Purpose**: Edit or create formulas

**Form Fields**:

| Field ID | Type | Purpose | Readonly |
|----------|------|---------|----------|
| `#formula-name` | input | Internal key (auto-generated for new) | Yes |
| `#formula-display-name` | input | Display name | No |
| `#formula-description` | textarea | Description | No |
| `#formula-category` | select | Category dropdown | No |
| `#dependencies-container` | div | Dynamic dependency inputs | No |
| `#formula-expression` | textarea | Formula expression | No |

**Buttons**:
- `#save-formula-btn` - Save changes
- `#test-formula-btn` - Validate expression
- `[onclick="openVariableReferenceModal()"]` - Show documentation
- `[onclick="closeFormulaModal()"]` - Cancel

**Data Flow** (Edit mode):
```
Edit button click
    → openFormulaModal(key)
    → Populate fields from data attributes
    → Show modal
    → User modifies fields
    → Save button click
    → Submit form / AJAX
    → Close modal
    → Refresh page / show success
```

**Data Flow** (Create mode):
```
Add New Formula button click
    → openFormulaModal() (no key)
    → Clear all fields
    → Enable Save button
    → User fills fields
    → Save button click
    → Submit form / AJAX
    → Close modal
    → Add to table
```

### Variable Reference Modal

**ID**: `#variable-reference-modal`

**Purpose**: Display Tournament Director variable and function documentation

**Tab Navigation**:

| Tab | Target ID | Content |
|-----|-----------|---------|
| Tournament Variables | `#var-tab-tournament` | Global variables: n, r, hits, monies, avgBC, T33, T80 |
| Player Variables | `#var-tab-player` | Per-player: prizeWinnings, numberOfRebuys, numberOfAddOns, chipStack, inTheMoney |
| Variable Mapping | `#var-tab-mapping` | Data key to formula variable mapping table |
| Functions | `#var-tab-functions` | Math functions: abs, sqrt, pow, log, exp, round, floor, ceil, max, min, if, assign |

**Tab Switching Pattern**:
```javascript
$('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
    e.preventDefault();
    var target = $(this).data('target');

    // Hide all tab content
    $('.tab-content').hide();

    // Show target tab
    $('#' + target).show();

    // Update active class
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
});
```

**Button**:
- `[onclick="closeVariableReferenceModal()"]` - Close

## JavaScript API

### Functions to Implement

#### `openFormulaModal(key)`

**Purpose**: Open formula editor modal (edit or create mode)

**Parameters**:
- `key` (string | null): Formula key for edit, null for create

**Behavior**:
1. If `key` provided (edit mode):
   - Find button with `data-key="${key}"`
   - Extract formula data from data attributes
   - Populate form fields
   - Set modal title to "Edit Formula"
   - Enable Test Formula button
2. If `key` null (create mode):
   - Clear all form fields
   - Set modal title to "Add New Formula"
   - Disable Test Formula button
3. Show `#formula-editor-modal`

**Returns**: undefined

#### `closeFormulaModal()`

**Purpose**: Close formula editor modal

**Parameters**: none

**Behavior**:
1. Clear all form fields
2. Hide `#formula-editor-modal`

**Returns**: undefined

#### `openVariableReferenceModal()`

**Purpose**: Open variable reference documentation modal

**Parameters**: none

**Behavior**:
1. Show first tab (Tournament Variables)
2. Set `#var-tab-tournament` as active
3. Show `#variable-reference-modal`

**Returns**: undefined

#### `closeVariableReferenceModal()`

**Purpose**: Close variable reference modal

**Parameters**: none

**Behavior**:
1. Hide `#variable-reference-modal`

**Returns**: undefined

### Event Handlers

#### Tab Navigation

**Selector**: `.nav-tab` (within `.nav-tab-wrapper`)

**Event**: click

**Delegation**: Yes (use `.on()` for dynamic tabs)

**Behavior**:
1. Prevent default link behavior
2. Extract `data-target` attribute
3. Hide all `.tab-content` elements
4. Show target `#` + data-target
5. Remove `nav-tab-active` from all tabs
6. Add `nav-tab-active` to clicked tab

#### Save Formula Button

**Selector**: `#save-formula-btn`

**Event**: click

**Behavior**:
1. Validate required fields
2. Collect form data
3. Submit via AJAX or form POST
4. On success: close modal, refresh page / update table
5. On error: display error message

#### Delete Formula Button

**Selector**: `.delete-formula` (on formula table rows)

**Event**: click

**Behavior**:
1. Check if `is_default === true`
2. If default: prevent deletion (no button shown)
3. If custom: show confirmation dialog
4. On confirm: submit delete request
5. On success: remove row from table

## Data Validation

### Client-Side Rules

1. **Required Fields** (before save):
   - `display_name` must not be empty
   - `category` must be selected
   - `expression` must not be empty

2. **Expression Syntax**:
   - Must validate via `Poker_Tournament_Formula_Validator`
   - AJAX call to test endpoint (if exists)
   - Display validation errors inline

3. **Display Name Uniqueness**:
   - Check against existing formulas
   - Server-side validation required

### Server-Side Rules

(Handled by PHP backend, not JavaScript)

- Nonce verification
- Capability check (`manage_options`)
- Formula expression syntax validation
- Dependency cycle detection
- Default formula protection

## State Management

### Modal State

**Formula Editor Modal**:
- **Visible**: `$('#formula-editor-modal').is(':visible')`
- **Mode**: Edit (has key) vs Create (no key)
- **Dirty**: Form fields modified vs original values

**Variable Reference Modal**:
- **Visible**: `$('#variable-reference-modal').is(':visible')`
- **Active Tab**: Current `data-target` value

### Tab State

**Active Tab**:
- Selector: `.nav-tab-active`
- Target: `$(this).data('target')`

**Tab Content**:
- Hidden tabs: `.tab-content:not(:visible)`
- Visible tab: `.tab-content:visible`

## Error Handling

### JavaScript Errors

| Scenario | Error Message | Action |
|----------|---------------|--------|
| Formula not found | "Formula not found: {key}" | Alert user, close modal |
| Required field empty | "Display name is required" | Show inline error |
| Expression invalid | "Invalid formula syntax" | Show validation error |
| Save failed | "Failed to save formula" | Show admin notice |

### AJAX Failures

| Scenario | Fallback | User Message |
|----------|----------|--------------|
| Network error | Retry with exponential backoff | "Network error, retrying..." |
| Server error 500 | Show error details | "Server error, please try again" |
| Timeout (30s) | Cancel request | "Request timed out" |

## Performance Considerations

### DOM Queries

- **Cache selectors**: Store `$('#formula-editor-modal')` in variable
- **Event delegation**: Use `.on()` for dynamic elements
- **Minimize reflows**: Batch DOM updates

### Data Loading

- **Avoid AJAX**: Use data attributes (already in page)
- **Lazy load tabs**: Only render tab content when first opened
- **Debounce validation**: Wait 300ms after keystroke before validating

## Browser Compatibility

### Target Browsers

- Chrome 90+ (WordPress admin primary)
- Firefox 88+ (secondary)
- Safari 14+ (macOS)
- Edge 90+ (Chromium)

### jQuery Compatibility

- jQuery 1.12.4+ (WordPress bundled)
- No ES6+ syntax (if supporting older browsers)
- Use `jQuery` instead of `$` (wrap in IIFE)

### Testing

- Test in WordPress admin context
- Verify modal positioning in all browsers
- Check tab switching responsiveness
- Validate form submission behavior
