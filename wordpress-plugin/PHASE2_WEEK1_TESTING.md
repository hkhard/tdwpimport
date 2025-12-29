# Phase 2 Week 1 Testing Checklist

**Version:** 3.1.0-beta6
**Date:** 2025-01-27
**Features:** Real-time Tournament Clock & Live Control

---

## Pre-Testing Setup

- [ ] Install `poker-tournament-import-v3.1.0-beta6.zip`
- [ ] Verify plugin activated successfully
- [ ] Check error logs for PHP warnings/errors
- [ ] Confirm Tournament Manager menu appears
- [ ] Verify at least 1 tournament template exists (from Phase 1)

---

## 1. Database Schema Testing

### 1.1 Table Creation
- [ ] **Test:** Navigate to any WordPress admin page
- [ ] **Expected:** Tables auto-create on first load
- [ ] **Verify in DB:**
  ```sql
  SHOW TABLES LIKE 'wp_tdwp_tournament_%';
  -- Should show: wp_tdwp_tournament_live_state
  -- Should show: wp_tdwp_tournament_events
  ```

### 1.2 Schema Verification
- [ ] **Test:** Check live_state table structure
  ```sql
  DESCRIBE wp_tdwp_tournament_live_state;
  ```
- [ ] **Expected:** 18 columns (id, tournament_id, template_id, status, current_level, time_remaining, started_at, paused_at, break_until, completed_at, total_players, remaining_players, total_rebuys, total_addons, prize_pool, next_payout_position, created_at, updated_at)

- [ ] **Test:** Check events table structure
  ```sql
  DESCRIBE wp_tdwp_tournament_events;
  ```
- [ ] **Expected:** 7 columns (id, tournament_id, event_type, event_data, user_id, is_automated, created_at)

---

## 2. Admin Live Control - Start Tournament

### 2.1 Initial State
- [ ] **Navigate to:** Tournament Manager → Live Control
- [ ] **Expected:** See "Start New Tournament" form
- [ ] **Expected:** Template dropdown populated with templates
- [ ] **Expected:** Total players field present

### 2.2 Start Tournament
- [ ] **Test:** Select template, enter 20 players, click "Start Tournament"
- [ ] **Expected:** Page reloads
- [ ] **Expected:** Clock display appears with gradient background
- [ ] **Expected:** Time shows (e.g., "20:00" for 20-minute level)
- [ ] **Expected:** Shows "Level 1"
- [ ] **Expected:** Status badge shows "Running" (green)
- [ ] **Expected:** Control buttons visible: Pause, Advance Level, Complete
- [ ] **Expected:** Statistics show: Total Players (20), Remaining (20), Rebuys (0), Addons (0), Prize Pool ($0.00)
- [ ] **Expected:** Event log shows "Tournament Started" entry

### 2.3 Database Verification
- [ ] **Test:** Check database after start
  ```sql
  SELECT * FROM wp_tdwp_tournament_live_state;
  ```
- [ ] **Expected:** One row with status='running'
- [ ] **Expected:** started_at timestamp populated
- [ ] **Expected:** tournament_id references a tournament post

---

## 3. Admin Live Control - Real-Time Updates

### 3.1 Heartbeat Auto-Update
- [ ] **Test:** Keep Live Control page open for 30 seconds
- [ ] **Expected:** Clock counts down automatically (updates every 1 second locally)
- [ ] **Expected:** Time syncs with server every 15 seconds (via Heartbeat)
- [ ] **Browser Console:** No JavaScript errors
- [ ] **Network Tab:** See admin-ajax.php requests every 15 seconds

### 3.2 Time Accuracy
- [ ] **Test:** Watch clock for 1 minute
- [ ] **Expected:** Time decreases consistently (no jumps or freezes)
- [ ] **Expected:** After 15s Heartbeat, time aligns with server state

---

## 4. Admin Live Control - Control Actions

### 4.1 Pause Tournament
- [ ] **Test:** Click "Pause Tournament" button
- [ ] **Confirm:** Click OK on confirmation dialog
- [ ] **Expected:** Status changes to "Paused" (yellow badge)
- [ ] **Expected:** Clock stops counting down
- [ ] **Expected:** "Pause" button replaced with "Resume" button
- [ ] **Expected:** Event log shows "Tournament Paused"
- [ ] **Database:** paused_at timestamp populated

### 4.2 Resume Tournament
- [ ] **Test:** Click "Resume Tournament" button
- [ ] **Confirm:** Click OK on confirmation dialog
- [ ] **Expected:** Status changes back to "Running" (green badge)
- [ ] **Expected:** Clock resumes countdown from where it paused
- [ ] **Expected:** "Resume" button replaced with "Pause" button
- [ ] **Expected:** Event log shows "Tournament Resumed"
- [ ] **Database:** paused_at = NULL

### 4.3 Advance Level
- [ ] **Test:** Click "Advance Level" button
- [ ] **Confirm:** Click OK on confirmation dialog
- [ ] **Expected:** Level increments (Level 1 → Level 2)
- [ ] **Expected:** Clock resets to level duration from template
- [ ] **Expected:** Event log shows "Level Advanced to 2"
- [ ] **Database:** current_level = 2

### 4.4 Complete Tournament
- [ ] **Test:** Click "Complete Tournament" button
- [ ] **Confirm:** Click OK on confirmation dialog
- [ ] **Expected:** Status changes to "Completed" (red badge)
- [ ] **Expected:** Clock stops
- [ ] **Expected:** All control buttons disabled or hidden
- [ ] **Expected:** Event log shows "Tournament Completed"
- [ ] **Expected:** Page may reload to show "Start New Tournament" form
- [ ] **Database:** completed_at timestamp populated, status='completed'

---

## 5. Frontend Shortcode - Basic Display

### 5.1 Setup
- [ ] **Create:** New page "Live Clock Test"
- [ ] **Add:** `[tournament_clock]` shortcode to content
- [ ] **Publish:** Save and publish page

### 5.2 Active Tournament Display
- [ ] **Prerequisite:** Start a tournament in admin
- [ ] **Navigate to:** Frontend page with shortcode
- [ ] **Expected:** Clock widget displays
- [ ] **Expected:** Large time display (96px font)
- [ ] **Expected:** Current level shown
- [ ] **Expected:** Status badge with animated indicator dot
- [ ] **Expected:** Statistics grid: Players, Prize Pool, Rebuys/Addons (if applicable)
- [ ] **Expected:** "Live Tournament Clock" footer text

### 5.3 No Active Tournament
- [ ] **Prerequisite:** Complete or have no active tournament
- [ ] **Navigate to:** Frontend page with shortcode
- [ ] **Expected:** Shows "No active tournament at this time" message
- [ ] **Expected:** Gray background, centered text

---

## 6. Frontend Shortcode - Real-Time Updates

### 6.1 Public Heartbeat
- [ ] **Test:** Keep frontend page open for 30 seconds
- [ ] **Expected:** Clock counts down every second
- [ ] **Expected:** Updates sync from server every 15 seconds
- [ ] **Browser Console:** No JavaScript errors
- [ ] **Network Tab:** admin-ajax.php requests every 15 seconds

### 6.2 Multi-User Sync
- [ ] **Test:** Open admin Live Control in one browser
- [ ] **Test:** Open frontend shortcode page in another browser/device
- [ ] **Action:** Pause tournament in admin
- [ ] **Expected:** Within 15 seconds, frontend updates to "Paused" status
- [ ] **Action:** Resume tournament in admin
- [ ] **Expected:** Within 15 seconds, frontend updates to "Running" status

### 6.3 Non-Logged-In Users
- [ ] **Test:** Open frontend page in incognito/private window
- [ ] **Expected:** Clock displays normally
- [ ] **Expected:** Real-time updates work via nopriv Heartbeat

---

## 7. Frontend Shortcode - Attributes

### 7.1 Theme Variants
- [ ] **Test:** `[tournament_clock theme="dark"]`
- [ ] **Expected:** Dark background (#1d2327), white text, text shadow

- [ ] **Test:** `[tournament_clock theme="light"]`
- [ ] **Expected:** White background, black text, border outline

- [ ] **Test:** `[tournament_clock theme="default"]` or no theme attribute
- [ ] **Expected:** White background, standard styling

### 7.2 Size Variants
- [ ] **Test:** `[tournament_clock size="small"]`
- [ ] **Expected:** Smaller clock (48px font), max-width 400px

- [ ] **Test:** `[tournament_clock size="medium"]`
- [ ] **Expected:** Medium clock (72px font), max-width 600px

- [ ] **Test:** `[tournament_clock size="large"]` or no size attribute
- [ ] **Expected:** Large clock (96px font), max-width 800px

### 7.3 Hide Stats
- [ ] **Test:** `[tournament_clock show_stats="no"]`
- [ ] **Expected:** Statistics grid hidden, only clock/level/status visible

### 7.4 Hide Level
- [ ] **Test:** `[tournament_clock show_level="no"]`
- [ ] **Expected:** Level text hidden, only clock/status visible

### 7.5 Specific Tournament
- [ ] **Test:** `[tournament_clock tournament_id="123"]` (use actual ID)
- [ ] **Expected:** Shows specific tournament even if not "active"

---

## 8. Responsive Design

### 8.1 Mobile (< 480px)
- [ ] **Test:** Resize browser to mobile width
- [ ] **Expected:** Clock font: 48px
- [ ] **Expected:** Level font: 18px
- [ ] **Expected:** Status badge smaller (12px font)
- [ ] **Expected:** Layout stacks vertically

### 8.2 Tablet (768px - 1200px)
- [ ] **Test:** Resize browser to tablet width
- [ ] **Expected:** Clock font: 64px
- [ ] **Expected:** Level font: 20px
- [ ] **Expected:** Stats grid: single column
- [ ] **Expected:** Control buttons stack if on admin

### 8.3 Desktop (> 1200px)
- [ ] **Test:** View on desktop resolution
- [ ] **Expected:** Full size clock (96px or per size attribute)
- [ ] **Expected:** Stats grid: multi-column layout
- [ ] **Expected:** All elements properly spaced

---

## 9. Error Handling & Edge Cases

### 9.1 Missing Template
- [ ] **Test:** Try to start tournament, but template is deleted/missing
- [ ] **Expected:** Error message displayed
- [ ] **Expected:** Tournament does not start
- [ ] **Expected:** No database record created

### 9.2 Zero Players
- [ ] **Test:** Start tournament with 0 or empty players field
- [ ] **Expected:** Either validation error OR starts with 0 players
- [ ] **Verify:** No PHP errors

### 9.3 Database Tables Missing
- [ ] **Test:** Drop Phase 2 tables manually
  ```sql
  DROP TABLE wp_tdwp_tournament_live_state;
  DROP TABLE wp_tdwp_tournament_events;
  ```
- [ ] **Test:** Reload any WordPress admin page
- [ ] **Expected:** Tables recreate automatically
- [ ] **Expected:** Live Control page works again

### 9.4 Heartbeat Disabled
- [ ] **Test:** If Heartbeat can be disabled, test behavior
- [ ] **Expected:** Graceful degradation (no auto-updates, but manual refresh works)
- [ ] **Expected:** No JavaScript errors

### 9.5 Concurrent Tournaments
- [ ] **Test:** Try to start a second tournament while one is active
- [ ] **Expected:** Either prevented OR both tracked separately
- [ ] **Verify:** Database handles correctly (unique constraints?)

### 9.6 Template with No Levels
- [ ] **Test:** Start tournament with template that has 0 blind levels
- [ ] **Expected:** Error handling OR defaults to safe value
- [ ] **Expected:** No PHP fatal errors

---

## 10. Performance & Browser Compatibility

### 10.1 Page Load Time
- [ ] **Test:** Measure admin Live Control page load time
- [ ] **Expected:** < 2 seconds on local dev
- [ ] **Test:** Measure frontend shortcode page load time
- [ ] **Expected:** < 1 second on local dev

### 10.2 Heartbeat Performance
- [ ] **Test:** Monitor CPU/memory with Heartbeat active for 5 minutes
- [ ] **Expected:** No memory leaks
- [ ] **Expected:** Low CPU usage (< 5%)

### 10.3 Browser Compatibility
- [ ] **Chrome:** All features work
- [ ] **Firefox:** All features work
- [ ] **Safari:** All features work
- [ ] **Edge:** All features work
- [ ] **Mobile Safari (iOS):** Frontend shortcode works
- [ ] **Chrome Mobile (Android):** Frontend shortcode works

---

## 11. Asset Loading

### 11.1 Admin Assets
- [ ] **Navigate to:** Live Control page
- [ ] **Browser DevTools → Network:**
  - [ ] live-control-admin.css loads
  - [ ] live-control-admin.js loads
  - [ ] heartbeat.js loads (WordPress core)
- [ ] **Expected:** No 404 errors for assets

### 11.2 Frontend Assets
- [ ] **Navigate to:** Page with [tournament_clock] shortcode
- [ ] **Browser DevTools → Network:**
  - [ ] tournament-clock-frontend.css loads
  - [ ] tournament-clock-frontend.js loads
  - [ ] heartbeat.js loads
- [ ] **Expected:** Assets only load when shortcode present
- [ ] **Test:** Visit page WITHOUT shortcode
- [ ] **Expected:** Frontend clock assets do NOT load

---

## 12. WordPress Integration

### 12.1 Plugin Activation
- [ ] **Test:** Deactivate plugin
- [ ] **Expected:** No errors
- [ ] **Test:** Reactivate plugin
- [ ] **Expected:** Activation hook runs
- [ ] **Expected:** Database tables created/verified

### 12.2 Plugin Update
- [ ] **Test:** Simulate update by changing version number
- [ ] **Expected:** Database migration runs automatically
- [ ] **Expected:** No data loss from existing tournaments

### 12.3 Multisite Compatibility (if applicable)
- [ ] **Test:** Activate on WordPress multisite
- [ ] **Expected:** Works on each site independently
- [ ] **Expected:** Correct table prefixes per site

---

## Testing Summary

**Total Tests:** ~100+
**Critical Tests:** 30
**Nice-to-Have Tests:** 70

---

## Bug Tracking Template

When you find a bug, document it here:

### Bug #1: [Short Description]
- **Severity:** Critical / High / Medium / Low
- **Steps to Reproduce:**
  1. Step one
  2. Step two
- **Expected Behavior:** What should happen
- **Actual Behavior:** What actually happens
- **Error Messages:** Any PHP/JS errors
- **Browser/Environment:** Chrome 120, macOS, Local dev
- **Screenshots:** (if applicable)

---

## Post-Testing Actions

After completing testing:
- [ ] Review all bugs found
- [ ] Prioritize critical bugs for immediate fix
- [ ] Create GitHub issues (if using issue tracking)
- [ ] Fix critical bugs
- [ ] Re-test fixed items
- [ ] Update version number if fixes made
- [ ] Create new package if needed
- [ ] Document known issues/limitations

---

**Testing Status:** 🔴 Not Started | 🟡 In Progress | 🟢 Complete

**Last Updated:** 2025-01-27
