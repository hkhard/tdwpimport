# Quickstart: Frontend Tournament Import Shortcode Fix

**Feature**: Fix broken file upload in `tdwp_tournament_import` shortcode
**Branch**: `014-shortcode-upload-fix`
**Complexity**: Low (1 line of code)

---

## The Problem

The `[tdwp_tournament_import]` shortcode displays an upload form, but clicking "Import Tournament" does nothing. Opening browser console shows:

```
Uncaught ReferenceError: $ is not defined
```

**Root Cause**: The form uses jQuery (`$.ajax`) but jQuery is never loaded on frontend pages.

---

## The Solution

Add ONE line of code to enqueue jQuery:

```php
wp_enqueue_script('jquery');
```

**Location**: `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
**Function**: `tournament_import_shortcode()` (line 5346)
**Insert After**: Permission checks (after line 5359)

---

## For Developers

### Reproduce the Bug (Before Fix)

1. Add `[tdwp_tournament_import]` to a WordPress page
2. View page while logged in with `edit_posts` permission
3. Open browser DevTools Console (F12)
4. See error: `Uncaught ReferenceError: $ is not defined`
5. Try uploading a .tdt file → nothing happens

### Apply the Fix

1. Open `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
2. Go to line 5346 (`tournament_import_shortcode` method)
3. Find the permission check block (ends around line 5359)
4. Add this line immediately after:

```php
// Enqueue jQuery for frontend AJAX functionality
wp_enqueue_script('jquery');
```

5. Save file

### Verify the Fix (After Fix)

1. Refresh the page with the shortcode
2. Open browser DevTools Console (F12)
3. **Should see**: No `$ is not defined` error
4. Type `$` in console → should return `function(selector, context)` (not undefined)
5. Select a .tdt file
6. Click "Import Tournament"
7. **Should see**: "Uploading..." status message
8. **Should see**: Tournament imported successfully

---

## For Testers

### Test Setup

**Requirements**:
- WordPress installation with plugin activated
- User account with `edit_posts` capability (Editor role or higher)
- Valid .tdt file for testing

### Test Procedure

#### Test 1: Permission Checks (Basic Functionality)

1. **Not Logged In**:
   - Log out of WordPress
   - Visit page with `[tdwp_tournament_import]` shortcode
   - **Expected**: See "Please log in to import tournaments" message
   - **Pass**: ✅ Message displayed

2. **Insufficient Permissions**:
   - Log in as Subscriber (no `edit_posts` capability)
   - Visit page with shortcode
   - **Expected**: See "You do not have permission to import tournaments" message
   - **Pass**: ✅ Message displayed

3. **Authorized User**:
   - Log in as Editor or Administrator
   - Visit page with shortcode
   - **Expected**: Upload form displays with file input and checkbox
   - **Pass**: ✅ Form displays correctly

#### Test 2: JavaScript Console (No Errors)

1. Open browser DevTools Console (F12 or Cmd+Option+I)
2. Refresh the page
3. **Expected**: No red errors, especially no `$ is not defined`
4. Type `$` in console and press Enter
5. **Expected**: Returns `function(selector, context)` not `undefined`
6. **Pass**: ✅ No errors, jQuery is loaded

#### Test 3: File Upload Flow

1. Select a valid .tdt file using the file input
2. Leave "Publish tournament immediately" checked (default)
3. Click "Import Tournament" button
4. **Expected**: Status message changes to "Uploading..."
5. **Expected**: Network tab shows POST request to `admin-ajax.php`
6. **Expected**: After upload, message changes to success or error
7. **Pass**: ✅ Upload process completes

#### Test 4: Successful Import

1. Upload a valid .tdt file (use test file if available)
2. Wait for upload to complete
3. **Expected**: Success message appears
4. **Expected**: Form resets (file input cleared)
5. Go to WordPress Admin → Tournaments
6. **Expected**: New tournament appears in list
7. **Pass**: ✅ Tournament created successfully

#### Test 5: Error Handling

1. Try uploading a non-.tdt file (e.g., .txt, .pdf)
2. **Expected**: Alert shows "Please select a valid .tdt file"
3. **Pass**: ✅ Validation works

#### Test 6: Cross-Theme Testing

1. Activate WordPress default theme (Twenty Twenty-Four)
2. Repeat Tests 2-4
3. Activate a different theme
4. Repeat Tests 2-4
5. **Pass**: ✅ Works consistently across themes

---

## Expected Test Results

| Test | Expected Result | Pass Criteria |
|------|----------------|---------------|
| Permission Check 1 (Not logged in) | "Please log in" message | ✅ Message shows |
| Permission Check 2 (No permission) | "Permission denied" message | ✅ Message shows |
| Permission Check 3 (Authorized) | Upload form displays | ✅ Form visible |
| JavaScript Console | No `$ is not defined` error | ✅ Clean console |
| jQuery Loaded | `$` returns function in console | ✅ jQuery available |
| File Upload | "Uploading..." status shows | ✅ Status updates |
| AJAX Request | POST to admin-ajax.php | ✅ Request sent |
| Success Message | "Tournament imported" shows | ✅ Success displayed |
| Form Reset | Input clears after success | ✅ Form resets |
| Tournament Created | Tournament appears in admin | ✅ Post created |
| File Validation | Alert on non-.tdt file | ✅ Validation works |

---

## Troubleshooting

### Issue: Still seeing `$ is not defined` error

**Possible Causes**:
1. Code change not applied - check file was saved
2. Browser cache - hard refresh (Ctrl+Shift+R / Cmd+Shift+R)
3. Wrong file edited - verify you modified `includes/class-shortcodes.php`
4. Plugin not reactivated - deactivate and reactivate plugin

**Debug Steps**:
```php
// Add this temporary debug line after wp_enqueue_script('jquery'):
error_log('[TDWP DEBUG] jQuery enqueued in shortcode');
```
Check WordPress debug log for the message.

### Issue: File uploads but tournament not created

**Possible Causes**:
1. Invalid .tdt file - verify file format
2. Parser error - check PHP error logs
3. Permission issue - verify user has `edit_posts` capability
4. AJAX handler not registered - check plugin is activated

**Debug Steps**:
1. Open browser Network tab
2. Upload file
3. Click the admin-ajax.php request
4. Check Response tab for error details
5. Check browser console for AJAX error logs

### Issue: Form doesn't display at all

**Possible Causes**:
1. Not logged in - log in with appropriate permissions
2. Syntax error in PHP file - check file was edited correctly
3. Plugin deactivated - verify plugin is active

**Debug Steps**:
```php
// Add at start of tournament_import_shortcode():
error_log('[TDWP DEBUG] Shortcode executed, user logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
```

---

## Code Review Checklist

Before committing, verify:

- [ ] Change is exactly 1 line added (`wp_enqueue_script('jquery');`)
- [ ] Placed after permission checks (line ~5360)
- [ ] No other code modifications
- [ ] PHP syntax is valid (no parse errors)
- [ ] Follows existing pattern (matches `poker_dashboard_shortcode` at line 2629)
- [ ] File saved and committed to git
- [ ] Tested on local WordPress installation
- [ ] Browser console shows no errors
- [ ] File upload works end-to-end

---

## Next Steps After Fix

1. **Manual Testing**: Complete all test procedures above
2. **Cross-Browser**: Test in Chrome, Firefox, Safari (if available)
3. **Cross-Theme**: Test with 2+ different WordPress themes
4. **Version Bump**: Update plugin version if needed
5. **Create ZIP**: Build distribution package
6. **Deploy**: Upload to WordPress site
7. **Verify**: Test on production/staging environment

---

## Summary

- **Complexity**: Low (1 line of code)
- **Risk**: Minimal (follows existing pattern)
- **Testing**: Manual browser testing required
- **Time Estimate**: 15-30 minutes (implementation + testing)
- **Rollback**: Simple git revert if needed

The fix is straightforward and follows an established pattern already used in the codebase.
