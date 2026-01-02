# Feature Specification: Fix Tournament Formula Manager Page Interactions

**Feature Branch**: `005-formula-manager`
**Created**: 2025-01-02
**Status**: Draft
**Input**: User description: "the Tournament Formula Manager page is not working properly. We can't press edit, switch tabs for instructions etc. use Playwright to investigate at here: http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager#"

## User Scenarios & Testing

### User Story 1 - Edit Existing Formula (Priority: P1)

Administrator needs to edit an existing Tournament Director formula to update its display name, description, or calculation logic.

**Why this priority**: This is the core functionality of the Formula Manager. If administrators cannot edit formulas, they cannot modify scoring calculations which is the primary purpose of the page.

**Independent Test**: Navigate to Formula Manager page, click "Edit" button on any formula row, verify modal opens with formula details pre-populated, make changes, and save successfully.

**Acceptance Scenarios**:

1. **Given** user is on Formula Manager page with existing formulas, **When** clicking "Edit" button on any formula row, **Then** formula editor modal opens displaying current formula details (name, display name, description, category, dependencies, expression)
2. **Given** formula editor modal is open, **When** user modifies formula display name and clicks "Save Formula", **Then** changes are saved, modal closes, and success message appears
3. **Given** formula editor modal is open, **When** user clicks "Cancel" button, **Then** modal closes without saving changes
4. **Given** formula editor modal is open, **When** user clicks "Test Formula" button, **Then** formula is validated against test data and results display in modal

### User Story 2 - View Variable Reference Documentation (Priority: P2)

Administrator needs to access reference documentation for Tournament Director variables and functions while creating or editing formulas.

**Why this priority**: Important for usability and reducing errors, but administrators can work without it if they have external documentation or prior knowledge.

**Independent Test**: Click "Show Variable Reference" button in formula editor modal, verify variable reference modal opens, navigate between tabs (Tournament Variables, Player Variables, Variable Mapping, Functions), and verify all tabs display correctly.

**Acceptance Scenarios**:

1. **Given** formula editor modal is open, **When** clicking "Show Variable Reference" button, **Then** variable reference modal opens showing Tournament Variables tab by default
2. **Given** variable reference modal is open, **When** clicking on "Player Variables" tab, **Then** tab content switches to display player-specific variables (prizeWinnings, numberOfRebuys, numberOfAddOns, chipStack, inTheMoney)
3. **Given** variable reference modal is open, **When** clicking on "Variable Mapping" tab, **Then** tab content displays data key to formula variable mapping table
4. **Given** variable reference modal is open, **When** clicking on "Functions" tab, **Then** tab content displays available mathematical functions (abs, sqrt, pow, log, exp, round, floor, ceil, max, min, if, assign)
5. **Given** variable reference modal is open, **When** clicking "Close" button, **Then** modal closes and returns to formula editor

### User Story 3 - Create New Formula (Priority: P3)

Administrator needs to create a new custom formula for tournament or season scoring calculations.

**Why this priority**: Lower priority because default formulas exist and work. Custom formulas are advanced usage.

**Independent Test**: Click "Add New Formula" button, verify formula editor modal opens with empty fields, fill in required fields, and save successfully.

**Acceptance Scenarios**:

1. **Given** user is on Formula Manager page, **When** clicking "Add New Formula" button, **Then** formula editor modal opens with all fields empty except "Formula Name" field which is disabled
2. **Given** new formula modal is open, **When** user fills in display name, description, category, and expression, **Then** "Save Formula" button becomes enabled
3. **Given** new formula modal is open with required fields filled, **When** clicking "Save Formula", **Then** new formula is saved, modal closes, and formula appears in the formulas table

### User Story 4 - Delete Custom Formula (Priority: P3)

Administrator needs to delete a custom formula that is no longer needed (default formulas cannot be deleted).

**Why this priority**: Lower priority because deletion is infrequent and default formulas are protected.

**Independent Test**: Click "Delete" button on a custom (non-default) formula, confirm deletion, verify formula is removed from table.

**Acceptance Scenarios**:

1. **Given** custom formula exists in formulas table, **When** clicking "Delete" button, **Then** confirmation dialog appears
2. **Given** delete confirmation dialog is shown, **When** confirming deletion, **Then** formula is removed from table and success message appears
3. **Given** default formula exists in formulas table, **When** viewing formula row, **Then** no "Delete" button is displayed (default formulas are protected)

### Edge Cases

- What happens when user clicks "Edit" but formula data fails to load from server?
- What happens when user tries to save formula with invalid expression syntax?
- What happens when user tries to create formula with display name that already exists?
- What happens when required fields are empty and user clicks "Save Formula"?
- What happens when network error occurs during save operation?
- What happens when formula expression contains undefined variables?
- What happens when JavaScript errors prevent modal from opening?
- What happens when user clicks "Show Variable Reference" before opening formula editor?
- What happens when multiple users edit the same formula simultaneously?
- What happens when formula dependencies reference non-existent formulas?

## Requirements

### Functional Requirements

- **FR-001**: System MUST allow administrators to click "Edit" button on any formula row and open formula editor modal
- **FR-002**: System MUST pre-populate formula editor modal with current formula details when editing existing formulas
- **FR-003**: System MUST allow administrators to modify formula display name, description, category, dependencies, and expression in editor modal
- **FR-004**: System MUST validate formula expression syntax before saving
- **FR-005**: System MUST save formula changes when user clicks "Save Formula" button
- **FR-006**: System MUST close formula editor modal when user clicks "Cancel" button without saving changes
- **FR-007**: System MUST provide "Show Variable Reference" button to open variable reference modal from formula editor
- **FR-008**: System MUST allow navigation between tabs in variable reference modal (Tournament Variables, Player Variables, Variable Mapping, Functions)
- **FR-009**: System MUST display correct content for each tab in variable reference modal
- **FR-010**: System MUST close variable reference modal when user clicks "Close" button
- **FR-011**: System MUST allow administrators to create new custom formulas via "Add New Formula" button
- **FR-012**: System MUST validate that required fields are filled before allowing formula save
- **FR-013**: System MUST prevent deletion of default formulas (only custom formulas can be deleted)
- **FR-014**: System MUST require confirmation before deleting custom formulas
- **FR-015**: System MUST provide visual feedback when formula is successfully saved (success message)
- **FR-016**: System MUST display error messages when save operation fails
- **FR-017**: System MUST allow testing formulas with sample data via "Test Formula" button
- **FR-018**: System MUST ensure JavaScript functions `openFormulaModal()`, `closeFormulaModal()`, `openVariableReferenceModal()`, `closeVariableReferenceModal()` are defined and functional
- **FR-019**: System MUST ensure modal HTML elements exist in DOM with correct IDs (`formula-editor-modal`, `variable-reference-modal`)
- **FR-020**: System MUST ensure jQuery event handlers are properly attached to Edit and Delete buttons

### Key Entities

- **Formula**: Represents a Tournament Director calculation formula with attributes: name (internal key), display name, description, category (points/season/custom), dependencies (list of prerequisite formulas), expression (calculation logic)
- **Formula Category**: Classification of formulas as "points" (for tournament scoring), "season" (for season standings), or "custom" (user-defined)
- **Variable**: Tournament Director data variables available for use in formulas (e.g., buyins, rank, numberOfHits, prizeWinnings)
- **Function**: Mathematical functions available in formula expressions (e.g., abs, sqrt, pow, log, round, floor, ceil, max, min, if, assign)

## Success Criteria

### Measurable Outcomes

- **SC-001**: Administrators can successfully edit existing formulas on first attempt in 95% of cases
- **SC-002**: All modal functions (`openFormulaModal`, `closeFormulaModal`, `openVariableReferenceModal`, `closeVariableReferenceModal`) execute without JavaScript errors
- **SC-003**: Formula editor modal opens within 500ms after clicking "Edit" button
- **SC-004**: Variable reference modal tabs switch within 200ms after clicking
- **SC-005**: Formula save operations complete successfully in 98% of attempts
- **SC-006**: Zero JavaScript errors appear in browser console when performing any Formula Manager action
- **SC-007**: All buttons (Edit, Delete, Add New Formula, Show Variable Reference, Save, Cancel, Close) are clickable and responsive
- **SC-008**: Page functions correctly across all major browsers (Chrome, Firefox, Safari, Edge)

## Assumptions

1. User has WordPress administrator privileges with access to Formula Manager page
2. jQuery is loaded and available on the page (already enqueued in line 25 of formula-manager-page.php)
3. Formula data is stored in WordPress options and retrieved via `Poker_Tournament_Formula_Validator` class
4. Modal HTML is rendered server-side in the page template (lines 42-107 and 110-394 of formula-manager-page.php)
5. Missing JavaScript functions should be added to the external `formula-manager.js` file rather than inline script
6. Modal state management should use jQuery for show/hide/toggle operations
7. AJAX endpoints will be used for saving/deleting formulas (to be verified during implementation)
8. Default formulas (from `get_default_formulas()`) cannot be deleted and should not display Delete button
9. Tab navigation in variable reference modal uses WordPress nav-tab CSS classes
10. Error messages should use WordPress admin notice styling

## Root Cause Analysis (Preliminary)

Based on code inspection, the issue appears to be:

1. **Missing JavaScript Functions**: The PHP template (formula-manager-page.php) references JavaScript functions `openFormulaModal()`, `closeFormulaModal()`, `openVariableReferenceModal()`, `closeVariableReferenceModal()`, and `deleteFormula()` (lines 96, 100, 390, 412, 448, 452) but these functions are not defined in either:
   - The inline script in `wp_add_inline_script()` (line 27)
   - The external `formula-manager.js` file

2. **Modal HTML Present**: The modal HTML elements exist in the PHP template (`#formula-editor-modal` and `#variable-reference-modal`) but cannot be opened without the JavaScript functions to manipulate them.

3. **Incomplete External JS File**: The `formula-manager.js` file only contains basic event handlers for dependency fields and edit toggles, but lacks the core modal functions needed for the Formula Manager to work.

4. **Tab Switching Not Implemented**: The variable reference modal has tabs (lines 114-127) but no JavaScript to handle tab switching between different content panes.

This is a **bug fix** feature to restore functionality that was partially implemented but not completed.
