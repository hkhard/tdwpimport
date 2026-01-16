# AJAX API Contracts

**Feature**: 009-active-formula-selection
**Version**: 1.0
**Date**: 2025-01-02

## Overview

This document defines the AJAX API contracts for the active formula selection feature. All endpoints follow WordPress AJAX conventions with nonce verification and JSON responses.

---

## Endpoints

### 1. Set Active Formula

Sets the active formula for a specific category (tournament or season).

**Endpoint**: `wp_ajax_set_active_formula`
**Method**: `POST`
**Required Capability**: `manage_options`

#### Request

```javascript
POST /wp-admin/admin-ajax.php

Parameters:
- action: "set_active_formula"
- security: <nonce_value> (poker_formula_manager_nonce)
- formula_key: string - The formula key to set as active
- category: string - Either "tournament" or "season"

Body (JSON):
{
    "formula_key": "custom_points_v1",
    "category": "tournament"
}
```

#### Response (Success)

```json
{
    "success": true,
    "data": {
        "message": "Active formula updated successfully",
        "active_formula": "custom_points_v1",
        "display_name": "Custom Points V1",
        "category": "tournament"
    }
}
```

#### Response (Error)

```json
{
    "success": false,
    "data": {
        "message": "Error message describing what went wrong",
        "code": "error_code"
    }
}
```

**Error Codes**:
- `invalid_nonce`: Nonce verification failed
- `invalid_formula`: Formula key does not exist
- `wrong_category`: Formula category doesn't match request
- `no_capability`: User lacks required permissions

#### PHP Implementation Stub

```php
add_action('wp_ajax_set_active_formula', 'handle_set_active_formula');

function handle_set_active_formula() {
    // Verify nonce
    if (!check_ajax_referer('poker_formula_manager_nonce', 'security', false)) {
        wp_send_json_error([
            'message' => 'Invalid security token',
            'code' => 'invalid_nonce'
        ]);
    }

    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => 'Insufficient permissions',
            'code' => 'no_capability'
        ]);
    }

    // Get parameters
    $formula_key = sanitize_text_field($_POST['formula_key'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');

    // Validate category
    if (!in_array($category, ['tournament', 'season'])) {
        wp_send_json_error([
            'message' => 'Invalid category',
            'code' => 'invalid_category'
        ]);
    }

    // Validate formula exists and matches category
    $formulas = get_option('tdwp_tournament_formulas', []);
    if (!isset($formulas[$formula_key])) {
        wp_send_json_error([
            'message' => 'Formula not found',
            'code' => 'invalid_formula'
        ]);
    }

    // Store active formula
    $option_name = 'poker_active_' . $category . '_formula';
    update_option($option_name, $formula_key);

    // Clear season standings cache if season formula changed
    if ($category === 'season') {
        delete_transient('poker_season_standings_*');
    }

    // Return success
    wp_send_json_success([
        'message' => 'Active formula updated successfully',
        'active_formula' => $formula_key,
        'display_name' => $formulas[$formula_key]['display_name'],
        'category' => $category
    ]);
}
```

---

### 2. Get Active Formula

Retrieves the currently active formula for a category.

**Endpoint**: `wp_ajax_get_active_formula`
**Method**: `POST`
**Required Capability**: None (read-only)

#### Request

```javascript
POST /wp-admin/admin-ajax.php

Parameters:
- action: "get_active_formula"
- security: <nonce_value> (poker_formula_manager_nonce)
- category: string - Either "tournament" or "season"

Body (JSON):
{
    "category": "tournament"
}
```

#### Response (Success - Active Formula Set)

```json
{
    "success": true,
    "data": {
        "active_formula": "custom_points_v1",
        "display_name": "Custom Points V1",
        "category": "tournament",
        "description": "Custom tournament points formula"
    }
}
```

#### Response (Success - No Active Formula)

```json
{
    "success": true,
    "data": {
        "active_formula": null,
        "message": "No active formula set"
    }
}
```

#### Response (Error)

```json
{
    "success": false,
    "data": {
        "message": "Invalid category",
        "code": "invalid_category"
    }
}
```

#### PHP Implementation Stub

```php
add_action('wp_ajax_get_active_formula', 'handle_get_active_formula');
add_action('wp_ajax_nopriv_get_active_formula', 'handle_get_active_formula');

function handle_get_active_formula() {
    // Verify nonce
    if (!check_ajax_referer('poker_formula_manager_nonce', 'security', false)) {
        wp_send_json_error([
            'message' => 'Invalid security token',
            'code' => 'invalid_nonce'
        ]);
    }

    // Get parameters
    $category = sanitize_text_field($_POST['category'] ?? '');

    // Validate category
    if (!in_array($category, ['tournament', 'season'])) {
        wp_send_json_error([
            'message' => 'Invalid category',
            'code' => 'invalid_category'
        ]);
    }

    // Get active formula
    $option_name = 'poker_active_' . $category . '_formula';
    $active_formula_key = get_option($option_name, null);

    // No active formula set
    if (!$active_formula_key) {
        wp_send_json_success([
            'active_formula' => null,
            'message' => 'No active formula set'
        ]);
    }

    // Get formula details
    $formulas = get_option('tdwp_tournament_formulas', []);
    if (!isset($formulas[$active_formula_key])) {
        // Active formula references non-existent formula
        wp_send_json_success([
            'active_formula' => null,
            'message' => 'Previously active formula no longer exists'
        ]);
    }

    $formula = $formulas[$active_formula_key];

    // Return formula details
    wp_send_json_success([
        'active_formula' => $active_formula_key,
        'display_name' => $formula['display_name'],
        'category' => $category,
        'description' => $formula['description'] ?? ''
    ]);
}
```

---

### 3. Clear Formula Cache

Clears cached season standings when formula changes (utility endpoint).

**Endpoint**: `wp_ajax_clear_formula_cache`
**Method**: `POST`
**Required Capability**: `manage_options`

#### Request

```javascript
POST /wp-admin/admin-ajax.php

Parameters:
- action: "clear_formula_cache"
- security: <nonce_value> (poker_formula_manager_nonce)
- cache_type: string - "season_standings" or "all"

Body (JSON):
{
    "cache_type": "season_standings"
}
```

#### Response (Success)

```json
{
    "success": true,
    "data": {
        "message": "Cache cleared successfully",
        "cache_type": "season_standings",
        "cleared_count": 5
    }
}
```

#### PHP Implementation Stub

```php
add_action('wp_ajax_clear_formula_cache', 'handle_clear_formula_cache');

function handle_clear_formula_cache() {
    // Verify nonce and capability...

    $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');

    global $wpdb;

    if ($cache_type === 'all' || $cache_type === 'season_standings') {
        // Delete all season standings transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_poker_season_standings_%'"
        );
    }

    wp_send_json_success([
        'message' => 'Cache cleared successfully',
        'cache_type' => $cache_type
    ]);
}
```

---

## JavaScript Client API

### FormulaManagerClient

Helper class for AJAX calls to active formula endpoints.

```javascript
class FormulaManagerClient {
    constructor() {
        this.ajaxUrl = pokerFormulaManager.ajaxUrl;
        this.nonce = pokerFormulaManager.nonce;
    }

    /**
     * Set active formula for a category
     * @param {string} formulaKey - Formula key to set as active
     * @param {string} category - 'tournament' or 'season'
     * @returns {Promise<object>} Response data
     */
    async setActiveFormula(formulaKey, category) {
        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'set_active_formula',
                security: this.nonce,
                formula_key: formulaKey,
                category: category
            })
        });

        return await response.json();
    }

    /**
     * Get active formula for a category
     * @param {string} category - 'tournament' or 'season'
     * @returns {Promise<object>} Active formula data
     */
    async getActiveFormula(category) {
        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_active_formula',
                security: this.nonce,
                category: category
            })
        });

        return await response.json();
    }

    /**
     * Clear formula cache
     * @param {string} cacheType - 'season_standings' or 'all'
     * @returns {Promise<object>} Response data
     */
    async clearCache(cacheType = 'all') {
        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'clear_formula_cache',
                security: this.nonce,
                cache_type: cacheType
            })
        });

        return await response.json();
    }
}

// Initialize global instance
window.pokerFormulaClient = new FormulaManagerClient();
```

---

## UI Component Contracts

### Formula Card Active Checkbox

Each formula card displays an active checkbox/radio button.

**HTML Structure**:

```html
<div class="formula-card" data-key="custom_points_v1" data-category="points">
    <h3>Custom Points V1</h3>

    <!-- Active Formula Control -->
    <div class="formula-active-control">
        <label>
            <input type="checkbox"
                   class="formula-active-checkbox"
                   data-category="tournament"
                   data-formula-key="custom_points_v1"
                   <?php checked($is_active); ?>>
            Active Tournament Formula
        </label>
    </div>

    <!-- ... rest of formula card ... -->
</div>
```

**Behavior**:
- Only one checkbox can be checked per category
- Unchecking all is allowed (no active formula)
- Checking one unchecks others in same category

**JavaScript Handler**:

```javascript
$('.formula-active-checkbox').on('change', async function() {
    const $checkbox = $(this);
    const category = $checkbox.data('category');
    const formulaKey = $checkbox.data('formula-key');
    const isActive = $checkbox.is(':checked');

    if (isActive) {
        // Uncheck other formulas in same category
        $(`.formula-active-checkbox[data-category="${category}"]`)
            .not($checkbox)
            .prop('checked', false);

        // Set active formula via AJAX
        const result = await window.pokerFormulaClient.setActiveFormula(
            formulaKey,
            category
        );

        if (result.success) {
            // Show success message
            showNotice('success', result.data.message);

            // Clear cache if season formula changed
            if (category === 'season') {
                await window.pokerFormulaClient.clearCache('season_standings');
            }
        } else {
            // Revert checkbox on error
            $checkbox.prop('checked', false);
            showNotice('error', result.data.message);
        }
    } else {
        // Clear active formula
        await window.pokerFormulaClient.setActiveFormula('', category);
    }
});
```

---

## Error Handling Contract

### Error Display

All errors should be displayed using WordPress admin notices.

```javascript
function showNotice(type, message) {
    const className = type === 'error' ? 'notice-error' : 'notice-success';
    const html = `
        <div class="notice ${className} is-dismissible">
            <p>${message}</p>
        </div>
    `;

    $('.wrap.poker-formula-manager').prepend(html);
    setTimeout(() => {
        $('.notice').fadeOut();
    }, 5000);
}
```

### Retry Logic

For failed AJAX requests:

```javascript
async function setActiveFormulaWithRetry(formulaKey, category, maxRetries = 2) {
    for (let i = 0; i <= maxRetries; i++) {
        try {
            const result = await window.pokerFormulaClient.setActiveFormula(
                formulaKey,
                category
            );

            if (result.success) {
                return result;
            }

            // Server returned error, don't retry
            showNotice('error', result.data.message);
            return result;

        } catch (error) {
            if (i === maxRetries) {
                showNotice('error', 'Network error. Please try again.');
                return { success: false };
            }

            // Wait before retry
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
    }
}
```

---

## Testing Contract

### Unit Tests

```php
class Test_Active_Formula_API extends WP_UnitTestCase {
    function test_set_active_formula() {
        $this->_setRole('administrator');

        // Create nonce
        $nonce = wp_create_nonce('poker_formula_manager_nonce');

        // Make request
        $_POST = [
            'action' => 'set_active_formula',
            'security' => $nonce,
            'formula_key' => 'test_formula',
            'category' => 'tournament'
        ];

        // ... assertions
    }
}
```

### Integration Tests

```javascript
describe('Active Formula API', () => {
    it('should set active formula', async () => {
        const result = await pokerFormulaClient.setActiveFormula(
            'test_formula',
            'tournament'
        );

        expect(result.success).toBe(true);
        expect(result.data.active_formula).toBe('test_formula');
    });
});
```

---

## Security Checklist

- [x] Nonce verification on all endpoints
- [x] Capability checks for write operations
- [x] Input sanitization (sanitize_text_field)
- [x] Output escaping (wp_send_json_success handles this)
- [x] SQL injection prevention (use $wpdb->prepare)
- [x] XSS prevention (WordPress esc_* functions)

---

## Performance Requirements

- **Response Time**: < 200ms for all endpoints
- **Concurrent Users**: Support 10+ admins configuring formulas simultaneously
- **Cache Invalidation**: < 1s to clear all season standings transients
- **Database Load**: Minimal (option reads are cached)
