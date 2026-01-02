# AJAX Endpoints Contract: Formula Manager

**Feature**: Formula Manager JavaScript Bug Fix
**Date**: 2025-01-02
**Status**: NEEDS VERIFICATION

## Overview

This document defines AJAX endpoints for formula save, delete, and validation operations. **These endpoints must be verified during implementation** - they may not exist yet.

## Endpoint: Save Formula

### Request

**URL**: `/wp-admin/admin-ajax.php`

**Method**: `POST`

**Action**: `save_formula`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `"save_formula"` |
| `nonce` | string | Yes | WordPress nonce for verification |
| `key` | string | No | Formula key (omitted for new formulas) |
| `display_name` | string | Yes | Human-readable name |
| `description` | string | No | Formula description |
| `category` | string | Yes | `"points"`, `"season"`, or `"custom"` |
| `dependencies` | array | No | List of prerequisite formula keys |
| `expression` | string | Yes | Formula expression |

**Example Request**:

```javascript
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'save_formula',
        nonce: '<?php echo wp_create_nonce('save_formula'); ?>',
        key: 'points_total',
        display_name: 'Total Points',
        description: 'Sum of all tournament points',
        category: 'points',
        dependencies: ['points_played'],
        expression: 'n * 10 + r'
    },
    success: function(response) {
        if (response.success) {
            console.log('Formula saved');
        } else {
            console.error('Save failed:', response.data.message);
        }
    }
});
```

### Response

**Success** (HTTP 200):

```json
{
    "success": true,
    "data": {
        "message": "Formula saved successfully",
        "formula": {
            "key": "points_total",
            "display_name": "Total Points",
            "description": "Sum of all tournament points",
            "category": "points",
            "dependencies": ["points_played"],
            "expression": "n * 10 + r"
        }
    }
}
```

**Error** (HTTP 200 with `success: false`):

```json
{
    "success": false,
    "data": {
        "message": "Invalid formula expression",
        "errors": {
            "expression": "Undefined variable: xyz"
        }
    }
}
```

**Error Cases**:
- Invalid nonce → 403 Forbidden
- Missing capability → 403 Forbidden
- Invalid expression syntax → 200 with success: false
- Duplicate display name → 200 with success: false
- Circular dependency → 200 with success: false

### Server-Side Handler (if not exists)

**File**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`

**Hook Registration**:

```php
add_action('wp_ajax_save_formula', array($this, 'ajax_save_formula'));
add_action('wp_ajax_nopriv_save_formula', array($this, 'ajax_save_formula'));
```

**Handler Method**:

```php
/**
 * AJAX handler for saving formulas
 */
public function ajax_save_formula() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_formula')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Get formula data
    $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : null;
    $display_name = sanitize_text_field($_POST['display_name']);
    $description = sanitize_textarea_field($_POST['description']);
    $category = sanitize_text_field($_POST['category']);
    $dependencies = isset($_POST['dependencies']) ? array_map('sanitize_text_field', $_POST['dependencies']) : array();
    $expression = sanitize_textarea_field($_POST['expression']);

    // Validate required fields
    if (empty($display_name)) {
        wp_send_json_error(array('message' => 'Display name is required'));
    }

    if (empty($category)) {
        wp_send_json_error(array('message' => 'Category is required'));
    }

    if (empty($expression)) {
        wp_send_json_error(array('message' => 'Expression is required'));
    }

    // Validate expression syntax
    if (!class_exists('Poker_Tournament_Formula_Validator')) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-formula-validator.php';
    }

    $validator = new Poker_Tournament_Formula_Validator();

    if (!$validator->validate_expression($expression)) {
        wp_send_json_error(array(
            'message' => 'Invalid formula expression',
            'errors' => array('expression' => $validator->get_last_error())
        ));
    }

    // Load existing formulas
    $formulas = get_option('poker_formulas', array());

    // Generate key for new formulas
    if (empty($key)) {
        $key = sanitize_key($display_name);
    }

    // Save formula
    $formulas[$key] = array(
        'name' => $display_name,
        'description' => $description,
        'category' => $category,
        'dependencies' => $dependencies,
        'expression' => $expression
    );

    update_option('poker_formulas', $formulas);

    // Send success response
    wp_send_json_success(array(
        'message' => 'Formula saved successfully',
        'formula' => $formulas[$key]
    ));
}
```

## Endpoint: Delete Formula

### Request

**URL**: `/wp-admin/admin-ajax.php`

**Method**: `POST`

**Action**: `delete_formula`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `"delete_formula"` |
| `nonce` | string | Yes | WordPress nonce for verification |
| `key` | string | Yes | Formula key to delete |

**Example Request**:

```javascript
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'delete_formula',
        nonce: '<?php echo wp_create_nonce('delete_formula'); ?>',
        key: 'custom_formula_1'
    },
    success: function(response) {
        if (response.success) {
            console.log('Formula deleted');
        } else {
            console.error('Delete failed:', response.data.message);
        }
    }
});
```

### Response

**Success**:

```json
{
    "success": true,
    "data": {
        "message": "Formula deleted successfully",
        "key": "custom_formula_1"
    }
}
```

**Error**:

```json
{
    "success": false,
    "data": {
        "message": "Cannot delete default formula"
    }
}
```

**Error Cases**:
- Default formula (protected) → 200 with success: false
- Formula not found → 200 with success: false
- Invalid nonce → 403 Forbidden
- Missing capability → 403 Forbidden

### Server-Side Handler (if not exists)

**Hook Registration**:

```php
add_action('wp_ajax_delete_formula', array($this, 'ajax_delete_formula'));
add_action('wp_ajax_nopriv_delete_formula', array($this, 'ajax_delete_formula'));
```

**Handler Method**:

```php
/**
 * AJAX handler for deleting formulas
 */
public function ajax_delete_formula() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delete_formula')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Get formula key
    $key = sanitize_text_field($_POST['key']);

    if (empty($key)) {
        wp_send_json_error(array('message' => 'Formula key is required'));
    }

    // Load existing formulas
    $formulas = get_option('poker_formulas', array());

    // Check if formula exists
    if (!isset($formulas[$key])) {
        wp_send_json_error(array('message' => 'Formula not found'));
    }

    // Check if default formula (protected)
    if (!class_exists('Poker_Tournament_Formula_Validator')) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-formula-validator.php';
    }

    $validator = new Poker_Tournament_Formula_Validator();
    $default_formulas = $validator->get_default_formulas();

    if (isset($default_formulas[$key])) {
        wp_send_json_error(array('message' => 'Cannot delete default formula'));
    }

    // Delete formula
    unset($formulas[$key]);
    update_option('poker_formulas', $formulas);

    // Send success response
    wp_send_json_success(array(
        'message' => 'Formula deleted successfully',
        'key' => $key
    ));
}
```

## Endpoint: Validate Formula Expression

### Request

**URL**: `/wp-admin/admin-ajax.php`

**Method**: `POST`

**Action**: `validate_formula`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `"validate_formula"` |
| `nonce` | string | Yes | WordPress nonce for verification |
| `expression` | string | Yes | Formula expression to validate |

**Example Request**:

```javascript
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'validate_formula',
        nonce: '<?php echo wp_create_nonce('validate_formula'); ?>',
        expression: 'n * 10 + r'
    },
    success: function(response) {
        if (response.success) {
            console.log('Valid expression:', response.data.variables);
        } else {
            console.error('Invalid expression:', response.data.errors);
        }
    }
});
```

### Response

**Success**:

```json
{
    "success": true,
    "data": {
        "valid": true,
        "variables": ["n", "r"],
        "functions": [],
        "message": "Expression is valid"
    }
}
```

**Error**:

```json
{
    "success": false,
    "data": {
        "valid": false,
        "errors": [
            "Undefined variable: xyz",
            "Invalid function: foo()"
        ]
    }
}
```

### Server-Side Handler (if not exists)

**Hook Registration**:

```php
add_action('wp_ajax_validate_formula', array($this, 'ajax_validate_formula'));
add_action('wp_ajax_nopriv_validate_formula', array($this, 'ajax_validate_formula'));
```

**Handler Method**:

```php
/**
 * AJAX handler for validating formula expressions
 */
public function ajax_validate_formula() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'validate_formula')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Get expression
    $expression = sanitize_textarea_field($_POST['expression']);

    if (empty($expression)) {
        wp_send_json_error(array('message' => 'Expression is required'));
    }

    // Validate expression
    if (!class_exists('Poker_Tournament_Formula_Validator')) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-formula-validator.php';
    }

    $validator = new Poker_Tournament_Formula_Validator();
    $result = $validator->validate_expression($expression);

    if ($result['valid']) {
        wp_send_json_success(array(
            'valid' => true,
            'variables' => $result['variables'],
            'functions' => $result['functions'],
            'message' => 'Expression is valid'
        ));
    } else {
        wp_send_json_error(array(
            'valid' => false,
            'errors' => $result['errors']
        ));
    }
}
```

## Verification Steps

**Before implementing JavaScript AJAX calls**:

1. **Search for existing handlers**:
   ```bash
   grep -r "wp_ajax.*formula" wordpress-plugin/poker-tournament-import/
   ```

2. **Check if handlers exist**:
   - `wp_ajax_save_formula`
   - `wp_ajax_delete_formula`
   - `wp_ajax_validate_formula`

3. **If handlers don't exist**:
   - Implement handlers in `class-admin.php` (see code above)
   - Register hooks in `__construct()` method
   - Test handlers with WordPress REST API plugin or Postman

4. **If handlers exist**:
   - Document actual request/response format
   - Update this contract file
   - Adjust JavaScript to match actual format

## Fallback: Form Submission

**If AJAX endpoints are not available**, use WordPress `admin-post.php` pattern:

```php
// In PHP template (formula-manager-page.php)
<form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
    <input type="hidden" name="action" value="save_formula">
    <?php wp_nonce_field('save_formula', 'formula_nonce'); ?>
    <!-- form fields -->
    <button type="submit">Save Formula</button>
</form>
```

```php
// In class-admin.php
add_action('admin_post_save_formula', array($this, 'handle_save_formula'));
add_action('admin_post_nopriv_save_formula', array($this, 'handle_save_formula'));

public function handle_save_formula() {
    // Verify nonce
    if (!isset($_POST['formula_nonce']) || !wp_verify_nonce($_POST['formula_nonce'], 'save_formula')) {
        wp_die('Invalid nonce');
    }

    // Save logic (same as AJAX handler)

    // Redirect back to Formula Manager with notice
    $redirect = admin_url('admin.php?page=poker-formula-manager&message=saved');
    wp_redirect($redirect);
    exit;
}
```

## Security Considerations

### Nonce Verification

All AJAX requests must include a WordPress nonce:

```php
// Generate nonce in PHP
$nonce = wp_create_nonce('save_formula');

// Verify in handler
if (!wp_verify_nonce($_POST['nonce'], 'save_formula')) {
    wp_send_json_error(array('message' => 'Invalid nonce'));
}
```

### Capability Checks

All handlers must verify user capabilities:

```php
if (!current_user_can('manage_options')) {
    wp_send_json_error(array('message' => 'Insufficient permissions'));
}
```

### Input Sanitization

All user input must be sanitized:

```php
$key = sanitize_text_field($_POST['key']);
$display_name = sanitize_text_field($_POST['display_name']);
$description = sanitize_textarea_field($_POST['description']);
$expression = sanitize_textarea_field($_POST['expression']);
```

### Output Escaping

When rendering user data in JavaScript:

```php
data-display-name="<?php echo esc_attr($formula['name']); ?>"
onclick="openFormulaModal('<?php echo esc_js($key); ?>')"
```

## Testing

### Manual Testing (WordPress Admin)

1. **Test Save Formula**:
   - Edit existing formula
   - Click "Save Formula" button
   - Verify success message
   - Reload page and confirm changes persist

2. **Test Delete Formula**:
   - Create custom formula
   - Click "Delete" button
   - Confirm deletion
   - Verify formula removed from table

3. **Test Validate Expression**:
   - Enter invalid expression (e.g., `xyz + abc`)
   - Click "Test Formula" button
   - Verify error message displays

### Automated Testing (Playwright)

```javascript
// Test save formula via AJAX
test('save formula', async ({ page }) => {
    await page.goto('http://poker-tournament-devlocal.local/wp-admin/admin.php?page=poker-formula-manager');

    // Intercept AJAX request
    page.on('response', async (response) => {
        if (response.url().includes('admin-ajax.php')) {
            const data = await response.json();
            expect(data.success).toBe(true);
        }
    });

    // Click Edit button
    await page.click('button[data-key="points_total"]');

    // Modify display name
    await page.fill('#formula-display-name', 'Updated Total Points');

    // Click Save
    await page.click('#save-formula-btn');

    // Wait for success
    await page.waitForSelector('.notice-success');
});
```

## Status Legend

- **IMPLEMENTED**: Endpoint exists and tested
- **NEEDS IMPLEMENTATION**: Endpoint code provided above, must be added
- **NEEDS VERIFICATION**: Unknown if endpoint exists, must search codebase

**Current Status**: All endpoints are **NEEDS VERIFICATION**
