# Quickstart: Fix Tournament Import Button and Public Import Function

**Feature**: 003-fix-tournament-import
**Branch**: `003-fix-tournament-import`
**Date**: 2025-12-29

## Overview

Fix two critical regressions blocking tournament import functionality in the TDWP WordPress plugin.

## What This Feature Does

1. **Restores Admin Import Button**: Makes the tournament import button visible again in the WordPress admin dashboard
2. **Fixes Public Import Function**: Enables tournament import from the public-facing statistics page

## What This Feature Does NOT Do

- Does NOT change the .tdt file parser
- Does NOT modify tournament data structure
- Does NOT change import business logic
- Does NOT affect mobile app or controller
- Does NOT create new features

## Prerequisites

- WordPress 6.0+ installed
- TDWP WordPress plugin activated
- Tournament Director (.tdt) files available for testing
- WordPress admin access for testing admin import
- Public statistics page accessible for testing public import

## Testing Checklist

### Admin Import Button

- [ ] Log into WordPress admin dashboard
- [ ] Navigate to "Poker Tournaments" menu in sidebar
- [ ] Look for "Import" submenu item
- [ ] Click on Import menu item
- [ ] Verify import page loads without errors
- [ ] Upload a test .tdt file
- [ ] Verify tournament is created and published
- [ ] Check WordPress error log for any PHP errors

### Public Import Function

- [ ] Visit public statistics page (shortcode page)
- [ ] Locate import form on page
- [ ] Verify file upload field is visible
- [ ] Upload a test .tdt file
- [ ] Verify success message appears
- [ ] Verify tournament is created and published
- [ ] Try uploading invalid file (should show error)
- [ ] Check WordPress error log for any PHP errors

## Quick Test Procedure

### Step 1: Test Admin Import (2 minutes)

1. Login to WordPress admin
2. Look for "Poker Tournaments" in left sidebar
3. Click "Import" (or similar)
4. Should see import form with file upload
5. Select a .tdt file and click "Import"
6. Should see success message
7. Check that tournament appears in tournament list

**Expected Result**: Admin can import tournaments successfully

### Step 2: Test Public Import (2 minutes)

1. Visit the public statistics page URL
2. Look for import form on page
3. Select a .tdt file and click "Import"
4. Should see success message
5. Check that tournament appears on site

**Expected Result**: Public users can import tournaments successfully

## Troubleshooting

### Admin Import Button Not Visible

**Check 1**: Verify admin menu registration
```php
// In admin/class-admin.php, look for:
add_action('admin_menu', array($this, 'add_plugin_menu'));
```

**Check 2**: Verify submenu page
```php
// In add_plugin_menu method, look for:
add_submenu_page(
    'edit.php?post_type=tournament',
    'Import Tournament',
    'Import',
    'manage_options',
    'poker-import-tournament',
    array($this, 'import_page_callback')
);
```

**Check 3**: Verify callback method exists
```php
// Should have:
public function import_page_callback() {
    // Render import page
}
```

**Check 4**: Check WordPress capabilities
- Ensure current user has 'manage_options' capability
- Try with Administrator role

### Public Import Not Working

**Check 1**: Verify shortcode registered
```php
// In includes/class-shortcodes.php, look for:
add_shortcode('poker_tournament_import', array($this, 'import_shortcode'));
```

**Check 2**: Verify shortcode on page
- View page source and search for `[poker_tournament_import]`
- Check shortcode is being processed

**Check 3**: Verify AJAX handler registered
```php
// Look for:
add_action('wp_ajax_nopriv_poker_import_public_tournament', ...);
add_action('wp_ajax_poker_import_public_tournament', ...);
```

**Check 4**: Check browser console for errors
- Open browser DevTools (F12)
- Check Console tab for JavaScript errors
- Check Network tab for failed AJAX requests

**Check 5**: Enable WordPress debug
```php
// In wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check `wp-content/debug.log` for errors.

## Common Error Messages

### "Security verification failed"
- Cause: Nonce verification failing
- Fix: Ensure nonce field included in form and verified in AJAX handler

### "Access Denied"
- Cause: Capability check failing
- Fix: Ensure user has correct capabilities (admin only)

### "Invalid file format"
- Cause: File not a valid .tdt file
- Fix: Upload valid Tournament Director export file

### "Failed to save tournament"
- Cause: Database error or parser error
- Fix: Check debug.log for specific error

## Files Modified

Expected changes to:
1. `wordpress-plugin/poker-tournament-import/admin/class-admin.php` - Menu registration
2. `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` - Shortcode handler
3. Possibly `wordpress-plugin/poker-tournament-import/poker-tournament-import.php` - Hook initialization

## Deployment

1. Backup WordPress site before updating
2. Deactivate plugin
3. Replace plugin files with updated version
4. Reactivate plugin
5. Test admin import functionality
6. Test public import functionality
7. Monitor for errors in debug log

## Rollback Plan

If issues occur after deployment:
1. Deactivate plugin
2. Restore previous version from backup
3. Reactivate plugin
4. Report issues with details from debug.log

## Success Criteria

- ✅ Admin import button visible and functional
- ✅ Public import form visible and functional
- ✅ Both import methods successfully create tournaments
- ✅ No PHP errors in debug.log
- ✅ Proper security (nonce verification, capability checks)
- ✅ User-friendly error messages for invalid uploads
