---
date: 2025-12-30T00:54:34Z
session_name: tdwpimport
researcher: Claude
git_commit: 64ffdc0
branch: main
repository: tdwpimport
topic: "WordPress Dashboard Tab Loading Bug Investigation (Beta28-33)"
tags: [bugfix, wordpress, ajax, dashboard, php-fatal-error]
status: complete
last_updated: 2025-12-30
last_updated_by: Claude
type: investigation_handoff
root_span_id:
turn_span_id:
---

# Handoff: WordPress Dashboard Tab Loading Bug Investigation (Beta28-33)

## Task(s)

**Completed: Beta33 - PHP Fatal Error Fix**
- Removed duplicate `ajax_load_tournaments_data()` function definition causing "Cannot redeclare" fatal error
- Deployment: `poker-tournament-import-v3.4.0-beta33.zip`
- Status: **AWAITING USER TEST RESULTS**

**In Progress: 400/0 Status Pattern Investigation**
- User reports: First tab load works (400 → 0 with data), return visit fails (empty 400)
- Pattern affects Tournaments tab specifically
- May be separate issue from PHP fatal error or may be resolved by beta33

## Critical References
- `/Users/hkh/.claude/plans/wild-gathering-breeze.md` - Original PHP fatal error fix plan
- `/Users/hkh/dev/tdwpimport/wordpress-plugin/poker-tournament-import/admin/class-admin.php:3949-4037` - Tournaments AJAX handler (correct version)
- `/Users/hkh/dev/tdwpimport/wordpress-plugin/poker-tournament-import/assets/js/admin.js` - Dashboard tab loading logic

## Recent Changes

**Beta33 (64ffdc0) - PHP Fatal Error Fix:**
- `poker-tournament-import.php:251-252` - Removed duplicate AJAX action registration
- `poker-tournament-import.php:2707-2781` - Removed duplicate function definition
- Replaced with comments pointing to `admin/class-admin.php`

**Beta32 - Event Handler Delegation:**
- `admin.js:584-608` - Converted to event delegation on `$(document)`
- `admin.js:330-331` - Added tab cleanup on switch

**Beta31 - Loading Condition Fix:**
- `admin.js:324` - Changed loading condition from `!loaded && spinner > 0` to `!loaded || content === 0`
- `admin.js:474-476` - Added error state cleanup

**Beta30 - Cache Cleanup:**
- `admin.js:351-352` - Added `removeClass('loading')` and `loadingQueue.delete(tabName)`

**Beta29 - Action Name Fixes:**
- `admin.js:397,400,403` - Fixed action names from `poker_load_*` to `tdwp_load_*`

## Learnings

**Root Cause Discovery Process:**
1. **Initial misdiagnosis (Beta28-32):** Thought issue was JavaScript (action names, caching, event handlers)
2. **User diagnostic revealed truth:** 500 errors indicated server-side PHP fatal errors
3. **Actual cause:** Duplicate function definitions in PHP causing "Cannot redeclare" error

**Pattern for Future Debugging:**
- JavaScript errors + 500 status = Check PHP fatal errors first
- 400 status after 500 = PHP already crashed, subsequent requests fail
- Always check Network tab status codes before JavaScript debugging

**Key Files:**
- `poker-tournament-import.php:251` - Duplicate AJAX registration removed
- `poker-tournament-import.php:2707-2781` - Duplicate function removed
- `admin/class-admin.php:32,3949-4037` - Correct handler locations

**Tournaments Handler Difference:**
- Uses `is_user_logged_in()` instead of `current_user_can('manage_options')`
- Intentional design from v2.5.9 for non-admin dashboard access
- May contribute to different behavior vs other tabs

## Post-Mortem

### What Worked
- **User-provided diagnostics were critical:** Network tab status codes (500 → 400) revealed server-side issue
- **Systematic search for duplicates:** Found duplicate registrations at line 251 and function at lines 2707-2781
- **Plan file tracking:** Documented root cause clearly for implementation

### What Failed
- **Beta28 (Action names):** Fixed series/seasons/analytics, but issue persisted → Wrong diagnosis
- **Beta30 (Cache cleanup):** Added loading state cleanup → Wrong diagnosis
- **Beta31 (Loading condition):** Changed condition logic → Wrong diagnosis
- **Beta32 (Event delegation):** Converted to delegated events → Wrong diagnosis
- **Pattern:** 4 consecutive incorrect JavaScript-focused diagnoses before user provided 500 error diagnostic

### Key Decisions
- **Decision:** Remove duplicates from `poker-tournament-import.php`, keep in `admin/class-admin.php`
  - Alternatives considered: Keep duplicates with conditional includes, rename one function
  - Reason: `admin/class-admin.php` is proper location, main plugin file should only register hooks

## Artifacts
- `wordpress-plugin/poker-tournament-import-v3.4.0-beta33.zip` - Deployment package with duplicate removal
- `wordpress-plugin/poker-tournament-import-v3.4.0-beta32.zip` - Event delegation fix (superseded)
- `wordpress-plugin/poker-tournament-import-v3.4.0-beta31.zip` - Loading condition fix (superseded)
- `wordpress-plugin/poker-tournament-import-v3.4.0-beta30.zip` - Cache cleanup fix (superseded)
- `wordpress-plugin/poker-tournament-import-v3.4.0-beta29.zip` - Action name fixes (partial)

## Action Items & Next Steps

**IMMEDIATE - Awaiting User:**
1. Deploy and test beta33 - Does duplicate removal fix the tab loading issue?

**IF ISSUE PERSISTS - 400/0 Pattern Investigation:**
1. Investigate nonce verification in `admin/class-admin.php:3955`
2. Check if cache stores error state that blocks retry on return visits
3. Compare tournaments handler permissions vs other tabs
4. Add debug logging to trace request/response cycle

**DEPLOYMENT:**
1. If beta33 works: Create production release
2. If issue persists: Continue investigation with beta34

## Other Notes

**WordPress AJAX Status Code Reference:**
- 200: Success
- 400: Bad request (nonce failed, missing params)
- 500: Server error (PHP fatal, uncaught exception)
- 0: Connection error (CORS, timeout, network issue)

**User Diagnostic Quote:**
> "when it works, 1st time, I see a network 400 error then a 0 with all data. then i go to seasons an get 400 then 0 with data, then i go to tournaments and get an empty 400 only"

**Interpretation:**
- "400 then 0 with data" = Request succeeds but gets wrong status code (nonce check fails but continues?)
- "empty 400 only" = Complete failure, no data returned
- First load succeeds despite status codes, return visit fails completely

**Debug Commands:**
```bash
# Check PHP error logs
tail -f /path/to/wp-content/debug.log

# Test AJAX endpoint directly
curl -X POST https://example.com/wp-admin/admin-ajax.php \
  -d "action=tdwp_load_tournaments_data&nonce=<value>"

# Verify function is not declared twice
grep -n "function ajax_load_tournaments_data" wordpress-plugin/poker-tournament-import/*.php
```
