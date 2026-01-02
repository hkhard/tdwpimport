# Quickstart: Fix Tournament Formula Manager Page Interactions

**Feature Branch**: `005-formula-manager`
**Estimated Time**: 2-3 hours
**Difficulty**: Beginner (JavaScript bug fix)

## Prerequisites

1. **Branch checked out**: `git checkout 005-formula-manager`
2. **WordPress environment running**: http://poker-tournament-devlocal.local
3. **Admin access**: Log in to WordPress admin
4. **Formula Manager page**: http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager

## Step-by-Step Implementation

### Phase 1: Add Modal Functions to formula-manager.js

**File**: `wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js`

**Current state**: 70 lines, contains only basic handlers (dependencies, edit toggles, delete confirmation)

**Add after line 70** (before closing `});`):

```javascript
/**
 * Open formula editor modal
 *
 * @param {string} key Formula key for edit mode, null for create mode
 */
function openFormulaModal(key) {
    var $modal = $('#formula-editor-modal');
    var $form = $('#formula-editor-form');

    if (key) {
        // Edit mode: populate from data attributes
        var $button = $('button[data-key="' + key + '"]');

        // Extract data from button data attributes
        var displayName = $button.data('display-name') || '';
        var description = $button.data('description') || '';
        var category = $button.data('category') || 'points';
        var dependencies = $button.data('dependencies') || [];
        var expression = $button.data('expression') || '';

        // Populate form fields
        $('#formula-name').val(key);
        $('#formula-display-name').val(displayName);
        $('#formula-description').val(description);
        $('#formula-category').val(category);
        $('#formula-expression').val(expression);

        // Set modal title
        $('#modal-title').text('<?php esc_html_e('Edit Formula', 'poker-tournament-import'); ?>');

        // TODO: Populate dependencies container
        // populateDependencies(dependencies);

    } else {
        // Create mode: clear all fields
        $form[0].reset();
        $('#formula-name').val('');
        $('#modal-title').text('<?php esc_html_e('Add New Formula', 'poker-tournament-import'); ?>');
    }

    // Show modal
    $modal.show();
}

/**
 * Close formula editor modal
 */
function closeFormulaModal() {
    $('#formula-editor-modal').hide();
    $('#formula-editor-form')[0].reset();
}

/**
 * Open variable reference modal
 */
function openVariableReferenceModal() {
    // Show first tab by default
    $('.tab-content').hide();
    $('#var-tab-tournament').show();

    // Set first tab as active
    $('.nav-tab').removeClass('nav-tab-active');
    $('.nav-tab[data-target="var-tab-tournament"]').addClass('nav-tab-active');

    // Show modal
    $('#variable-reference-modal').show();
}

/**
 * Close variable reference modal
 */
function closeVariableReferenceModal() {
    $('#variable-reference-modal').hide();
}
```

**Add event handlers** (inside `jQuery(document).ready()`):

```javascript
/**
 * Tab navigation handler
 */
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

/**
 * Save formula button handler
 */
$('#save-formula-btn').click(function(e) {
    e.preventDefault();

    // Validate required fields
    var displayName = $('#formula-display-name').val().trim();
    var category = $('#formula-category').val();
    var expression = $('#formula-expression').val().trim();

    if (!displayName) {
        alert('<?php esc_html_e('Display name is required', 'poker-tournament-import'); ?>');
        return;
    }

    if (!category) {
        alert('<?php esc_html_e('Category is required', 'poker-tournament-import'); ?>');
        return;
    }

    if (!expression) {
        alert('<?php esc_html_e('Expression is required', 'poker-tournament-import'); ?>');
        return;
    }

    // Collect form data
    var formData = {
        key: $('#formula-name').val(),
        display_name: displayName,
        description: $('#formula-description').val(),
        category: category,
        dependencies: [],
        expression: expression
    };

    // Submit via AJAX (to be implemented)
    // $.ajax({
    //     url: ajaxurl,
    //     type: 'POST',
    //     data: {
    //         action: 'save_formula',
    //         nonce: '<?php echo wp_create_nonce('save_formula'); ?>',
    //         formula: formData
    //     },
    //     success: function(response) {
    //         if (response.success) {
    //             closeFormulaModal();
    //             location.reload();
    //         } else {
    //             alert(response.data.message || 'Save failed');
    //         }
    //     },
    //     error: function() {
    //         alert('<?php esc_html_e('Failed to save formula', 'poker-tournament-import'); ?>');
    //     }
    // });

    // For now, just close modal
    console.log('Save formula:', formData);
    closeFormulaModal();
});

/**
 * Test formula button handler (placeholder)
 */
$('#test-formula-btn').click(function(e) {
    e.preventDefault();

    var expression = $('#formula-expression').val().trim();

    if (!expression) {
        alert('<?php esc_html_e('Expression is required', 'poker-tournament-import'); ?>');
        return;
    }

    // Validate via AJAX (to be implemented)
    alert('<?php esc_html_e('Formula validation not yet implemented', 'poker-tournament-import'); ?>');
});
```

### Phase 2: Update PHP Template (if needed)

**File**: `wordpress-plugin/poker-tournament-import/admin/formula-manager-page.php`

**Check if Edit button has all required data attributes** (around line 448):

```php
<button type="button" class="button"
        data-key="<?php echo esc_attr($key); ?>"
        data-display-name="<?php echo esc_attr($formula['name']); ?>"
        data-description="<?php echo esc_attr($formula['description']); ?>"
        data-category="<?php echo esc_attr($formula['category']); ?>"
        data-dependencies="<?php echo esc_attr(json_encode($formula['dependencies'])); ?>"
        data-expression="<?php echo esc_attr($formula['expression']); ?>"
        onclick="openFormulaModal('<?php echo esc_js($key); ?>')">
    <?php esc_html_e('Edit', 'poker-tournament-import'); ?>
</button>
```

**If data attributes are missing**, add them to the Edit button.

### Phase 3: Test Manualy in WordPress Admin

1. **Navigate to Formula Manager page**:
   - URL: http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager

2. **Test Edit button**:
   - Click "Edit" button on any formula row
   - Verify modal opens with formula data pre-populated
   - Check browser console for errors (should be none)

3. **Test modal close**:
   - Click "Cancel" button
   - Verify modal closes
   - Check browser console for errors

4. **Test Variable Reference button**:
   - Click "Edit" on any formula
   - Click "Show Variable Reference" button
   - Verify variable reference modal opens
   - Verify "Tournament Variables" tab is active

5. **Test tab navigation**:
   - Click "Player Variables" tab
   - Verify content switches to player variables
   - Click "Variable Mapping" tab
   - Verify content switches to mapping table
   - Click "Functions" tab
   - Verify content switches to functions list

6. **Test close Variable Reference**:
   - Click "Close" button
   - Verify modal closes and returns to formula editor

### Phase 4: Verify with Playwright (Optional)

**Run Playwright test to verify fix**:

```bash
cd /Users/hkh/dev/tdwpimport
npx playwright test formula-manager.spec.ts
```

**Or manually navigate and take screenshot**:

```bash
npx playwright codegen http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager
```

## Expected Results

### Before Fix

- Clicking Edit button produces error: `ReferenceError: openFormulaModal is not defined`
- Modal never opens
- Tab navigation doesn't work

### After Fix

- Edit button opens modal with formula data (<500ms)
- Cancel button closes modal
- Variable Reference button opens documentation modal
- Tab navigation switches content (<200ms)
- No JavaScript errors in browser console

## Troubleshooting

### Issue: Modal still doesn't open

**Check**:
1. Browser console for errors (F12 → Console)
2. `formula-manager.js` is loaded (Network tab)
3. jQuery is loaded (should be enqueued by WordPress)
4. Modal HTML exists in DOM (inspect with F12 → Elements)

### Issue: Tab switching doesn't work

**Check**:
1. Tab content has correct IDs: `var-tab-tournament`, `var-tab-player`, etc.
2. Tab links have `data-target` attributes
3. jQuery event handler is attached
4. No CSS conflicts hiding tabs

### Issue: Form data doesn't populate

**Check**:
1. PHP template renders `data-*` attributes on Edit button
2. Data attributes are properly escaped with `esc_attr()`
3. JavaScript correctly extracts data with `.data()`
4. Form field IDs match: `#formula-display-name`, `#formula-expression`, etc.

## Next Steps (After This Fix)

1. **Implement AJAX save/delete** (if endpoints exist or create them)
2. **Add formula validation** (integrate with `Poker_Tournament_Formula_Validator`)
3. **Add Test Formula functionality** (AJAX validation endpoint)
4. **Create distribution ZIP** with updated version number

## Additional Resources

- **Spec**: `specs/005-formula-manager/spec.md` - User stories and requirements
- **Research**: `specs/005-formula-manager/research.md` - Technical decisions
- **Data Model**: `specs/005-formula-manager/data-model.md` - Entity structures
- **Requirements Checklist**: `specs/005-formula-manager/checklists/requirements.md`

## Commit Message Template

```
Fix Formula Manager JavaScript modal and tab functionality

Add missing JavaScript functions to formula-manager.js for:
- openFormulaModal() - Edit/create formula modal
- closeFormulaModal() - Close formula editor
- openVariableReferenceModal() - Show documentation
- closeVariableReferenceModal() - Close documentation
- Tab navigation handler - Switch between reference tabs

Fixes: ReferenceError: openFormulaModal is not defined

Files modified:
- assets/js/formula-manager.js (add ~150 lines)
- admin/formula-manager-page.php (verify data attributes)

Testing:
- Manual test in WordPress admin (all buttons working)
- Playwright verification (no JS errors)
```

## Version Bump

After committing changes, update plugin version:

```php
// In poker-tournament-import.php header
define('POKER_TOURNAMENT_IMPORT_VERSION', '3.5.0-beta36');

// In plugin header
 * Version: 3.5.0-beta36
```

Then create distribution ZIP:

```bash
cd wordpress-plugin/poker-tournament-import
zip -r ../poker-tournament-import-v3.5.0-beta36.zip .
```
