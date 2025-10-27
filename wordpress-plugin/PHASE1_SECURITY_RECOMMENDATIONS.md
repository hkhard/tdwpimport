# Phase 1 Security Recommendations
## Quick Reference for Minor Improvements

**Status:** Optional Enhancements
**Priority:** Low to Medium
**Current Security Rating:** 9.5/10

---

## High Priority (Should Implement)

### 1. Add Capability Check to Search Handler

**File:** `admin/tournament-manager/player-management-page.php`
**Line:** 849
**Severity:** Medium

**Current Code:**
```php
public function ajax_search_players() {
    check_ajax_referer( 'tdwp_player_management', 'nonce' );

    $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    // ...
}
```

**Recommended Fix:**
```php
public function ajax_search_players() {
    check_ajax_referer( 'tdwp_player_management', 'nonce' );

    // Add capability check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'poker-tournament-import' ) ) );
    }

    $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    // ...
}
```

**Rationale:** While nonce provides CSRF protection, capability check ensures only authorized users can search players.

---

### 2. Add MIME Type Verification to File Upload

**File:** `admin/tournament-manager/class-player-importer.php`
**Line:** 50
**Severity:** Medium

**Current Code:**
```php
public function parse_file( $file_path, $file_name ) {
    // Validate file extension.
    $extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

    $allowed_extensions = array( 'csv', 'xls', 'xlsx' );
    if ( ! in_array( $extension, $allowed_extensions, true ) ) {
        return new WP_Error( 'invalid_file_type', '...' );
    }
    // ...
}
```

**Recommended Fix:**
```php
public function parse_file( $file_path, $file_name ) {
    // Validate file extension.
    $extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

    $allowed_extensions = array( 'csv', 'xls', 'xlsx' );
    if ( ! in_array( $extension, $allowed_extensions, true ) ) {
        return new WP_Error( 'invalid_file_type', '...' );
    }

    // Validate MIME type (NEW)
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime_type = finfo_file( $finfo, $file_path );
    finfo_close( $finfo );

    $allowed_mimes = array(
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
        return new WP_Error(
            'invalid_mime_type',
            sprintf(
                /* translators: %s: detected MIME type */
                __( 'Invalid file MIME type: %s', 'poker-tournament-import' ),
                $mime_type
            )
        );
    }

    // Continue with parsing...
}
```

**Rationale:** Prevents users from uploading malicious files disguised as CSV/Excel.

---

### 3. Add File Size Limit

**File:** `admin/tournament-manager/player-management-page.php`
**Line:** 890
**Severity:** Medium

**Current Code:**
```php
private function handle_import_preview() {
    if ( empty( $_FILES['import_file'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'poker-tournament-import' ) ) );
    }

    $file = $_FILES['import_file'];

    // Validate file upload.
    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        wp_send_json_error( array( 'message' => __( 'File upload error.', 'poker-tournament-import' ) ) );
    }
    // ...
}
```

**Recommended Fix:**
```php
private function handle_import_preview() {
    if ( empty( $_FILES['import_file'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'poker-tournament-import' ) ) );
    }

    $file = $_FILES['import_file'];

    // Validate file upload.
    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        wp_send_json_error( array( 'message' => __( 'File upload error.', 'poker-tournament-import' ) ) );
    }

    // Check file size (NEW) - 2MB limit
    $max_file_size = 2 * 1024 * 1024; // 2MB in bytes
    if ( $file['size'] > $max_file_size ) {
        wp_send_json_error(
            array(
                'message' => sprintf(
                    /* translators: %s: maximum file size */
                    __( 'File too large. Maximum size: %s', 'poker-tournament-import' ),
                    size_format( $max_file_size )
                ),
            )
        );
    }

    // Continue with parsing...
}
```

**Rationale:** Prevents DoS attacks via extremely large file uploads.

---

## Medium Priority (Nice to Have)

### 4. Implement Rate Limiting on Frontend Registration

**File:** `includes/class-player-registration.php`
**Line:** 207
**Severity:** Low

**Implementation:**
```php
public function ajax_register_player() {
    check_ajax_referer( 'tdwp_player_registration', 'nonce' );

    // Rate limiting (NEW)
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'tdwp_registration_rate_' . md5( $ip_address );
    $attempts = get_transient( $transient_key );

    if ( false !== $attempts && $attempts >= 3 ) {
        wp_send_json_error(
            array(
                'message' => __( 'Too many registration attempts. Please try again later.', 'poker-tournament-import' ),
            )
        );
    }

    // Increment attempts counter
    set_transient( $transient_key, ( $attempts ? $attempts + 1 : 1 ), HOUR_IN_SECONDS );

    // Honeypot check...
    // Continue with registration...
}
```

**Rationale:** Prevents spam bots from overwhelming the registration system.

---

### 5. Enhanced Email Validation

**File:** `includes/class-player-registration.php`
**Line:** 218
**Severity:** Low

**Current Code:**
```php
$player_data = array(
    'email' => isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '',
    // ...
);
```

**Recommended Enhancement:**
```php
$email = isset( $_POST['player_email'] ) ? sanitize_email( wp_unslash( $_POST['player_email'] ) ) : '';

// Additional email validation (NEW)
if ( ! empty( $email ) && ! is_email( $email ) ) {
    wp_send_json_error( array( 'message' => __( 'Invalid email format.', 'poker-tournament-import' ) ) );
}

// Optional: Check for disposable email domains
$disposable_domains = array( 'tempmail.com', 'guerrillamail.com', '10minutemail.com' );
$email_domain = substr( strrchr( $email, '@' ), 1 );
if ( in_array( $email_domain, $disposable_domains, true ) ) {
    wp_send_json_error( array( 'message' => __( 'Disposable email addresses not allowed.', 'poker-tournament-import' ) ) );
}

$player_data = array(
    'email' => $email,
    // ...
);
```

**Rationale:** Reduces fake registrations with temporary email addresses.

---

### 6. Add Security Headers

**File:** `poker-tournament-import.php`
**Line:** Add to init hook
**Severity:** Low

**Implementation:**
```php
/**
 * Initialize plugin
 */
public function init() {
    // Existing code...

    // Add security headers (NEW)
    add_action( 'send_headers', array( $this, 'add_security_headers' ) );
}

/**
 * Add security headers
 *
 * @since 3.0.0
 */
public function add_security_headers() {
    if ( is_admin() || wp_doing_ajax() ) {
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    }
}
```

**Rationale:** Adds defense-in-depth security layers at HTTP header level.

---

## Low Priority (Optional)

### 7. Add Audit Logging

**Implementation:** Create new class `TDWP_Audit_Logger`

**Example Usage:**
```php
// Log player creation
TDWP_Audit_Logger::log( 'player_created', array(
    'player_id' => $player_id,
    'user_id' => get_current_user_id(),
    'ip_address' => $_SERVER['REMOTE_ADDR'],
) );

// Log player deletion
TDWP_Audit_Logger::log( 'player_deleted', array(
    'player_id' => $player_id,
    'user_id' => get_current_user_id(),
) );
```

**Rationale:** Provides accountability and helps track suspicious activity.

---

### 8. Implement reCAPTCHA (Alternative to Honeypot)

**File:** `includes/class-player-registration.php`

**Implementation:**
```php
// Add Google reCAPTCHA v3 to frontend form
public function render_registration_form( $atts ) {
    // ... existing code ...

    // Add reCAPTCHA script
    wp_enqueue_script(
        'google-recaptcha',
        'https://www.google.com/recaptcha/api.js?render=' . RECAPTCHA_SITE_KEY,
        array(),
        null,
        true
    );

    // ... form HTML ...
}

// Verify reCAPTCHA on submission
public function ajax_register_player() {
    check_ajax_referer( 'tdwp_player_registration', 'nonce' );

    // Verify reCAPTCHA (NEW)
    $recaptcha_response = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( $_POST['recaptcha_token'] ) : '';

    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $response = wp_remote_post(
        $verify_url,
        array(
            'body' => array(
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $recaptcha_response,
            ),
        )
    );

    $response_body = wp_remote_retrieve_body( $response );
    $result = json_decode( $response_body );

    if ( ! $result->success || $result->score < 0.5 ) {
        wp_send_json_error( array( 'message' => __( 'reCAPTCHA verification failed.', 'poker-tournament-import' ) ) );
    }

    // Continue with registration...
}
```

**Rationale:** reCAPTCHA provides stronger bot protection than honeypot alone.

---

## Implementation Checklist

When implementing these recommendations:

- [ ] Review each recommendation
- [ ] Prioritize based on your use case
- [ ] Test thoroughly in staging environment
- [ ] Update security audit document
- [ ] Document changes in release notes

---

## Testing After Implementation

For each implemented recommendation:

1. **Positive Tests:** Verify functionality still works
2. **Negative Tests:** Verify security actually blocks threats
3. **Edge Cases:** Test boundary conditions
4. **Performance:** Ensure no significant slowdown

---

## Summary

**Current State:** Very secure (9.5/10)
**With All Recommendations:** Exceptionally secure (10/10)

**Minimum Recommended:**
- Implement #1-3 (High Priority)
- Total effort: ~2 hours

**Full Implementation:**
- All 8 recommendations
- Total effort: ~8 hours

---

**Last Updated:** 2025-01-27
**Next Review:** After implementation
