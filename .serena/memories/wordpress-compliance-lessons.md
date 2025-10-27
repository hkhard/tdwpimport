# WordPress.org Plugin Compliance - Security Lessons Learned

**Source:** Versions 2.9.15 - 2.9.22 WordPress.org submission process
**Date Compiled:** October 27, 2025

---

## Critical Security Rules (NEVER VIOLATE)

### 1. Data Sanitization on Input

**Golden Rule:** Sanitize IMMEDIATELY when receiving user data

```php
// CORRECT - Sanitize on input
$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
update_post_meta( $post_id, '_tournament_title', $title );

// WRONG - No sanitization
update_post_meta( $post_id, '_tournament_title', $_POST['title'] );
```

**Required Functions:**
- `wp_unslash()` - ALWAYS use FIRST on $_POST, $_GET, $_REQUEST
- `sanitize_text_field()` - Single-line text input
- `sanitize_textarea_field()` - Multi-line text
- `sanitize_email()` - Email addresses
- `sanitize_file_name()` - File names
- `sanitize_key()` - Keys/slugs (alphanumeric, dashes, underscores)
- `sanitize_title()` - Titles for URLs
- `absint()` - Positive integers
- `floatval()` - Float numbers
- `wp_kses_post()` - Rich text (allows safe HTML)
- `map_deep($array, 'sanitize_text_field')` - Recursive array sanitization

**For Arrays:**
```php
// Recursive sanitization
function tdwp_sanitize_array_recursive( $array ) {
    return map_deep( $array, 'sanitize_text_field' );
}

$clean_data = tdwp_sanitize_array_recursive( $_POST['tournament_data'] );
```

---

### 2. Output Escaping

**Golden Rule:** Escape LATE (right before output)

```php
// CORRECT - Escape on output
echo '<h1>' . esc_html( $tournament_name ) . '</h1>';
echo '<a href="' . esc_url( $tournament_link ) . '">';
echo '<div class="' . esc_attr( $class_name ) . '">';

// WRONG - No escaping
echo '<h1>' . $tournament_name . '</h1>';
```

**Required Functions:**
- `esc_html()` - HTML content
- `esc_attr()` - HTML attributes
- `esc_url()` - URLs (validates and escapes)
- `esc_url_raw()` - Database/redirect URLs (validates only)
- `esc_js()` - Inline JavaScript
- `esc_textarea()` - Textarea values
- `wp_kses_post()` - Safe HTML (for content from trusted users)

**Translation + Escaping:**
```php
// CORRECT - Combined functions
echo esc_html__( 'Tournament Name', 'poker-tournament-import' );
echo esc_attr__( 'Player Count', 'poker-tournament-import' );

// Use esc_html__(), esc_attr__(), esc_html_e(), esc_attr_e()
```

---

### 3. SQL Prepared Statements

**Golden Rule:** NEVER concatenate user input into SQL

```php
// CORRECT - Prepared statement
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}tdwp_tournaments WHERE id = %d AND status = %s",
    $tournament_id,
    $status
) );

// WRONG - SQL injection vulnerability
$results = $wpdb->get_results(
    "SELECT * FROM wp_tdwp_tournaments WHERE id = $tournament_id"
);
```

**Placeholders:**
- `%d` - Integer
- `%f` - Float
- `%s` - String

**Important Notes:**
- `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()` handle escaping automatically
- STILL sanitize data before passing to these functions
- Table/column names CANNOT be parameterized (use whitelist validation)

---

### 4. JSON Data Sanitization

**Problem:** `json_decode()` returns unsanitized data from database

```php
// CORRECT - Sanitize after json_decode
$tournament_data = json_decode( get_post_meta( $post_id, '_tournament_data', true ), true );
if ( is_array( $tournament_data ) ) {
    $tournament_data = map_deep( $tournament_data, 'sanitize_text_field' );
}

// WRONG - Trust JSON data
$tournament_data = json_decode( get_post_meta( $post_id, '_tournament_data', true ), true );
echo $tournament_data['player_name']; // XSS vulnerability
```

---

### 5. AJAX Security

**ALWAYS Required:**
1. Nonce verification
2. Capability check
3. Input sanitization
4. Output escaping

```php
// Creating nonce (in PHP for JS)
wp_localize_script( 'tdwp-admin-script', 'tdwpAjax', array(
    'ajaxurl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'tdwp_save_tournament' )
) );

// AJAX Handler
public function tdwp_ajax_save_tournament() {
    // 1. Verify nonce
    check_ajax_referer( 'tdwp_save_tournament', 'nonce' );
    
    // 2. Check capability
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }
    
    // 3. Sanitize input
    $tournament_id = absint( $_POST['tournament_id'] );
    $tournament_name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
    
    // 4. Process...
    
    // 5. Escape output
    wp_send_json_success( array(
        'message' => esc_html__( 'Tournament saved', 'poker-tournament-import' ),
        'name' => esc_html( $tournament_name )
    ) );
}

// Register AJAX action
add_action( 'wp_ajax_tdwp_save_tournament', array( $this, 'tdwp_ajax_save_tournament' ) );
```

**CRITICAL:** Never rely on nonces for authentication - always check `current_user_can()`

---

### 6. Script/Style Enqueueing

**Golden Rule:** NEVER use inline `<script>` or `<style>` tags

```php
// CORRECT - Enqueue properly
public function tdwp_enqueue_admin_scripts( $hook ) {
    if ( 'toplevel_page_tournament-manager' !== $hook ) {
        return;
    }
    
    // Register and enqueue script
    wp_enqueue_script(
        'tdwp-tournament-manager',
        POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-manager.js',
        array( 'jquery' ),
        POKER_TOURNAMENT_IMPORT_VERSION,
        true
    );
    
    // Enqueue style
    wp_enqueue_style(
        'tdwp-tournament-manager',
        POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/tournament-manager.css',
        array(),
        POKER_TOURNAMENT_IMPORT_VERSION
    );
    
    // Add inline script if needed
    wp_add_inline_script(
        'tdwp-tournament-manager',
        'const tdwpConfig = ' . wp_json_encode( array(
            'maxPlayers' => 500,
            'currency' => get_option( 'tdwp_currency', 'USD' )
        ) ) . ';',
        'before'
    );
}

add_action( 'admin_enqueue_scripts', array( $this, 'tdwp_enqueue_admin_scripts' ) );

// WRONG - Inline script
echo '<script>var maxPlayers = 500;</script>';
```

---

### 7. Global Scope Prefixing

**Critical:** All global scope items MUST have unique prefixes

**Classes:** `TDWP_*` or `Poker_Tournament_*`
```php
class TDWP_Tournament_Manager {}
class Poker_Tournament_Template {}
```

**Functions:** `tdwp_*`
```php
function tdwp_get_tournament( $id ) {}
function tdwp_format_currency( $amount ) {}
```

**Global Variables:** `$tdwp_*`
```php
global $tdwp_tournament_manager;
```

**Options:** `tdwp_*`
```php
add_option( 'tdwp_default_buyin', 100 );
get_option( 'tdwp_currency', 'USD' );
```

**Post Meta:** `_tdwp_*` (leading underscore = hidden from Custom Fields UI)
```php
update_post_meta( $post_id, '_tdwp_tournament_template', $template_id );
```

**Transients:** `tdwp_*`
```php
set_transient( 'tdwp_tournament_stats_' . $id, $stats, HOUR_IN_SECONDS );
```

**AJAX Actions:** `tdwp_*`
```php
add_action( 'wp_ajax_tdwp_save_tournament', 'callback' );
```

**Script/Style Handles:** `tdwp-*`
```php
wp_enqueue_script( 'tdwp-tournament-manager', ... );
wp_enqueue_style( 'tdwp-admin-styles', ... );
```

**Shortcodes:** `[tdwp_*]` (keep old for backward compatibility)
```php
add_shortcode( 'tdwp_tournament_list', 'callback' );
// Also support legacy: add_shortcode( 'tournament_list', 'callback' );
```

**CSS Classes:** `tdwp-*`
```html
<div class="tdwp-tournament-card">
```

---

### 8. $_SERVER Variables

**Problem:** $_SERVER variables need sanitization

```php
// CORRECT
$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) 
    ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
    : 'Unknown';

// WRONG
$server_software = $_SERVER['SERVER_SOFTWARE'];
```

---

### 9. Options API

**ALWAYS use sanitize callback with `register_setting()`**

```php
// CORRECT
register_setting(
    'tdwp_tournament_settings',
    'tdwp_default_buyin',
    array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 100
    )
);

register_setting(
    'tdwp_tournament_settings',
    'tdwp_tournament_name_format',
    array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '{date} - {buyin}'
    )
);

// For arrays
register_setting(
    'tdwp_tournament_settings',
    'tdwp_blind_templates',
    array(
        'type' => 'array',
        'sanitize_callback' => 'tdwp_sanitize_blind_templates',
    )
);

function tdwp_sanitize_blind_templates( $value ) {
    if ( ! is_array( $value ) ) {
        return array();
    }
    return map_deep( $value, 'sanitize_text_field' );
}
```

---

### 10. Post Meta Sanitization

**ALWAYS sanitize before `update_post_meta()`**

```php
// CORRECT - Sanitize before save
public function tdwp_save_tournament_meta( $post_id ) {
    // Nonce verification
    if ( ! isset( $_POST['tdwp_tournament_nonce'] ) 
        || ! wp_verify_nonce( $_POST['tdwp_tournament_nonce'], 'tdwp_save_tournament_' . $post_id ) ) {
        return;
    }
    
    // Capability check
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // Sanitize and save
    $buy_in = isset( $_POST['tournament_buyin'] ) 
        ? floatval( wp_unslash( $_POST['tournament_buyin'] ) )
        : 0;
    
    update_post_meta( $post_id, '_tdwp_tournament_buyin', $buy_in );
    
    $player_count = isset( $_POST['tournament_players'] )
        ? absint( $_POST['tournament_players'] )
        : 0;
    
    update_post_meta( $post_id, '_tdwp_tournament_players', $player_count );
}
```

---

### 11. Validation vs Sanitization

**Validation:** Reject invalid data (stricter)
```php
// For known values, VALIDATE
$tournament_type = sanitize_text_field( wp_unslash( $_POST['type'] ) );

if ( ! in_array( $tournament_type, array( 'freezeout', 'rebuy', 'bounty' ), true ) ) {
    wp_die( esc_html__( 'Invalid tournament type', 'poker-tournament-import' ) );
}

update_post_meta( $post_id, '_tdwp_tournament_type', $tournament_type );
```

**Sanitization:** Clean data (more forgiving)
```php
// For open-ended values, SANITIZE
$tournament_name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
update_post_meta( $post_id, '_tdwp_tournament_name', $tournament_name );
```

**Rule:** Validation > Sanitization. Use validation when possible.

---

### 12. File Upload Security

```php
// CORRECT - Validate file upload
public function tdwp_handle_file_upload() {
    // Nonce + capability check
    check_ajax_referer( 'tdwp_upload_file', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }
    
    // Validate file exists
    if ( empty( $_FILES['tdt_file'] ) ) {
        wp_send_json_error( 'No file uploaded' );
    }
    
    $file = $_FILES['tdt_file'];
    
    // Validate file extension
    $allowed_extensions = array( 'tdt', 'txt' );
    $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    
    if ( ! in_array( $file_ext, $allowed_extensions, true ) ) {
        wp_send_json_error( 'Invalid file type. Only .tdt files allowed.' );
    }
    
    // Validate MIME type
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime_type = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );
    
    if ( ! in_array( $mime_type, array( 'text/plain', 'application/octet-stream' ), true ) ) {
        wp_send_json_error( 'Invalid MIME type' );
    }
    
    // Sanitize filename
    $filename = sanitize_file_name( $file['name'] );
    
    // Process file...
}
```

---

### 13. Translator Comments

**REQUIRED for dynamic translations**

```php
// CORRECT - Add translator comment
/* translators: %d: number of tournaments */
$message = sprintf(
    esc_html__( 'Found %d tournaments', 'poker-tournament-import' ),
    $count
);

/* translators: 1: player name, 2: tournament name */
$message = sprintf(
    esc_html__( 'Player %1$s won %2$s', 'poker-tournament-import' ),
    $player_name,
    $tournament_name
);

// WRONG - Missing translator comment
$message = sprintf(
    esc_html__( 'Found %d tournaments', 'poker-tournament-import' ),
    $count
);
```

---

## Common Mistakes from v2.9.x Fixes

### 1. Forgot wp_unslash() before sanitization
```php
// WRONG
$value = sanitize_text_field( $_POST['value'] );

// CORRECT
$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
```

### 2. Trusted JSON data from database
```php
// WRONG
$data = json_decode( $meta_value, true );
echo $data['name']; // XSS

// CORRECT
$data = json_decode( $meta_value, true );
$data = map_deep( $data, 'sanitize_text_field' );
echo esc_html( $data['name'] );
```

### 3. Inline scripts/styles
```php
// WRONG
echo '<script>var config = {};</script>';

// CORRECT
wp_add_inline_script( 'my-script', 'var config = {};', 'before' );
```

### 4. Missing nonce verification sanitization
```php
// WRONG
check_ajax_referer( 'my_action', 'nonce' );
$nonce = $_POST['nonce']; // Used later

// CORRECT
check_ajax_referer( 'my_action', 'nonce' );
$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
```

### 5. Missing translator comments
```php
// WRONG - phpcs error
sprintf( __( '%d items', 'domain' ), $count );

// CORRECT
/* translators: %d: number of items */
sprintf( __( '%d items', 'domain' ), $count );
```

---

## Testing Checklist

Before committing ANY code:

- [ ] All $_POST/$_GET data uses wp_unslash() + sanitize_*()
- [ ] All output uses esc_*() functions
- [ ] All SQL uses $wpdb->prepare()
- [ ] All AJAX endpoints verify nonces
- [ ] All AJAX endpoints check capabilities
- [ ] All scripts/styles properly enqueued
- [ ] All global functions/variables prefixed
- [ ] All options have sanitize callback
- [ ] All file uploads validated
- [ ] All translator comments added
- [ ] No phpcs:ignore without good reason
- [ ] Test XSS attempt (should fail)
- [ ] Test SQL injection (should fail)
- [ ] Test CSRF (should fail)

---

**Last Updated:** October 27, 2025
**Next Review:** Before Phase 1 merge to main
