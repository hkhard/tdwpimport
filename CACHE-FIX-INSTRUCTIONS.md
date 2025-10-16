# Cache Fix Instructions - Version 2.3.16

## Problem
The browser is loading OLD cached JavaScript files instead of the new admin.js with proper nonce handling. This is preventing the dashboard from working correctly.

## Version 2.3.16 Changes

### 1. Version Marker Added to admin.js
The admin.js file now starts with a clear version identifier that logs to console:
```javascript
/**
 * Admin JavaScript for Poker Tournament Import
 * VERSION: 2.3.16
 */

// Version verification log - will appear first in console
console.log('========================================');
console.log('ADMIN.JS VERSION 2.3.16 LOADED');
console.log('Expected pokerImport structure: {dashboardNonce, refreshNonce, ajaxUrl, adminUrl, messages}');
console.log('Actual pokerImport:', typeof pokerImport !== 'undefined' ? pokerImport : 'UNDEFINED');
console.log('========================================');
```

### 2. Plugin Version Updated
- Main plugin header: Version 2.3.16
- Plugin constant: POKER_TOURNAMENT_IMPORT_VERSION = '2.3.16'

## Installation Steps (CRITICAL - MUST FOLLOW EXACTLY)

### Step 1: Complete Plugin Removal
1. Go to WordPress Admin → Plugins
2. **Deactivate** the Poker Tournament Import plugin
3. **Delete** the plugin completely
4. Using FTP or file manager, navigate to `/wp-content/plugins/`
5. **Delete ALL folders** that start with `poker-tournament-import`:
   - poker-tournament-import
   - poker-tournament-import-v2.3.12
   - poker-tournament-import-v2.3.13
   - poker-tournament-import-v2.3.14
   - poker-tournament-import-v2.3.15
   - ANY other version folders

### Step 2: Clear All Caches
1. **WordPress Object Cache**:
   - If using a caching plugin (W3 Total Cache, WP Super Cache, etc.), clear ALL caches
   - Go to each caching plugin and use "Clear All Caches" or "Purge All"

2. **Browser Cache**:
   - Chrome/Edge: Ctrl+Shift+Delete (Windows) or Cmd+Shift+Delete (Mac)
   - Select "Cached images and files"
   - Clear from "All time"

3. **Hard Refresh**:
   - Windows: Ctrl+Shift+F5 or Ctrl+F5
   - Mac: Cmd+Shift+R

### Step 3: Install Version 2.3.16
1. Upload `poker-tournament-import-v2.3.16-CACHE-FIX.zip`
2. Install through WordPress Admin → Plugins → Add New → Upload Plugin
3. **Activate** the plugin

### Step 4: Verify Installation
1. Go to WordPress Admin → Poker Tournament → Dashboard
2. Open browser Developer Tools (F12)
3. Go to the **Console** tab
4. Look for this message at the top:
   ```
   ========================================
   ADMIN.JS VERSION 2.3.16 LOADED
   Expected pokerImport structure: {dashboardNonce, refreshNonce, ajaxUrl, adminUrl, messages}
   Actual pokerImport: {dashboardNonce: "...", refreshNonce: "...", ...}
   ========================================
   ```

### Step 5: Verification Checklist
✅ Console shows "ADMIN.JS VERSION 2.3.16 LOADED"
✅ pokerImport object shows: `{dashboardNonce: "...", refreshNonce: "...", ajaxUrl: "...", adminUrl: "...", messages: {...}}`
✅ NO errors like "400 Bad Request" or "403 Forbidden"
✅ Dashboard tabs load correctly (Overview, Tournaments, Players, Series, Analytics)

## What to Do If It Still Shows Old Version

If the console STILL shows the old pokerImport structure with only `{nonce: "...", loadMoreNonce: "..."}`:

### 1. Check Network Tab
1. Open DevTools → Network tab
2. Filter by "admin.js"
3. Reload the page
4. Click on the admin.js request
5. Check the "Response" tab - it should show version 2.3.16 at the top

### 2. Check File Modification Time
1. Look at the admin.js URL in Network tab
2. It should have a version string like: `admin.js?ver=2.3.16-1729018000`
3. If it shows an old version number, the browser is still cached

### 3. Nuclear Option - Clear Everything
If still not working:
1. Delete ALL browser data for your site (cookies, cache, local storage)
2. Close ALL browser windows
3. Restart browser
4. Clear WordPress transients using WP-CLI:
   ```bash
   wp transient delete --all
   ```
5. If using Redis/Memcached, flush those caches too

## Technical Details

### What Was Fixed
1. **Duplicate wp_localize_script removed**: The main plugin file was calling `wp_localize_script` twice, causing the frontend localization to overwrite the admin localization
2. **Correct nonce property names**: JavaScript now uses `pokerImport.dashboardNonce` and `pokerImport.refreshNonce` as expected by the server
3. **Cache-busting version marker**: Added console.log version check to definitively confirm which version is loading

### The Code Fix
The correct localization (in admin/class-admin.php) provides:
```php
wp_localize_script(
    'poker-tournament-import-admin',
    'pokerImport',
    array(
        'dashboardNonce' => wp_create_nonce('poker_dashboard_nonce'),
        'refreshNonce' => wp_create_nonce('poker_refresh_statistics'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url(),
        'messages' => array(...)
    )
);
```

The JavaScript correctly uses:
```javascript
data: {
    action: 'poker_load_players_data',
    nonce: pokerImport.dashboardNonce || ''
}
```

## Contact
If problems persist after following ALL steps above, provide:
1. Screenshot of browser console showing version message
2. Screenshot of Network tab showing admin.js request/response
3. Error messages from console
4. Confirmation that ALL old plugin folders were deleted
