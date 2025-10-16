# Poker Tournament Import Plugin - Version 2.4.37 Release Notes

**Release Date:** October 16, 2025
**Criticality:** CRITICAL BUGFIX - Immediate upgrade required for all v2.4.36 users

---

## 🚨 CRITICAL ISSUE RESOLVED

### Database Schema Bug: Buyins Column Storing Chip Amounts Instead of Entry Counts

**Version 2.4.36 introduced a critical data corruption bug** that caused ROI calculations to show values in the millions instead of hundreds. This version fixes the root cause of the bug for future imports.

#### The Problem

The `poker_tournament_players` table's `buyins` column was storing the **SUM of chip amounts** instead of the **COUNT of entries**.

**Example of corrupted data:**
- Player makes 2 entries with 20,000 starting chips each
- **Incorrect (v2.4.36):** `buyins = 40000` (20000 + 20000 = sum of chips)
- **Correct (v2.4.37):** `buyins = 2` (count of entries)

#### The Impact

When the ROI migration multiplied buy-in fees by the corrupt `buyins` values:
- **Buy-in fee:** $200
- **Corrupt calculation:** $200 × 40000 chips = **$8,000,000** (completely wrong)
- **Correct calculation:** $200 × 2 entries = **$400** (correct)

This resulted in:
- Top players panel showing values like **-$80,000** and **-$120,000**
- Should have shown realistic values like **-$200** to **-$600**

---

## ✅ WHAT'S FIXED IN v2.4.37

### Code Fix

**File:** `admin/class-admin.php`
**Method:** `insert_tournament_players()`
**Lines:** 2507-2524

**Before (WRONG):**
```php
$total_buyins = 0;
if (!empty($player_data['buyins'])) {
    foreach ($player_data['buyins'] as $buyin) {
        $total_buyins += $buyin['amount'] ?? 0;  // Summed chip amounts!
    }
}
```

**After (CORRECT):**
```php
$total_buyins = 0;
if (!empty($player_data['buyins'])) {
    $total_buyins = count($player_data['buyins']);  // Count entries!
}
```

### What This Fixes

- **Future imports** will now store correct entry counts in the `buyins` column
- ROI calculations for **new tournaments** will be accurate
- Migration will calculate correct `total_invested` values: `buy_in_fee × entry_count`

---

## ⚠️ EXISTING DATA REQUIRES CLEANUP

**IMPORTANT:** This fix only affects **future imports**. Your existing database already has corrupt data.

### Your Current Situation

1. ✅ **Fix is in place:** Future imports will work correctly
2. ❌ **Corrupt data remains:** Existing `poker_tournament_players` table has chip amounts in `buyins` column
3. ❌ **Corrupt ROI table:** Your `poker_player_roi` table was populated using corrupt data
4. ❌ **Dashboard shows wrong values:** Top players panel shows incorrect money calculations

### Required Actions

You have **two options** to clean up the corrupt data:

---

## 🔧 DATA CLEANUP OPTIONS

### Option 1: Re-Import Tournaments (RECOMMENDED)

This is the cleanest approach and guarantees 100% accurate data.

**Steps:**

1. **Backup your database first** (always!)

2. **Clear the corrupt tables:**
   - Go to WordPress Admin → Poker Import → Migration Tools
   - Click "Clean Player Data" button
   - Click "Clean Financial Data" button
   - This removes corrupt data from `poker_tournament_players` and `poker_player_roi` tables

3. **Re-import your tournaments:**
   - Go to Poker Import → Import Tournament
   - Re-upload your 3 .tdt files
   - The new import will store correct entry counts (2, 3, etc.) instead of chip amounts (40000, 80000)

4. **Verify the fix:**
   - Check the top players panel
   - Values should now show realistic amounts like -$200, -$400, -$600
   - No more -$80,000 or -$120,000 values

**Time Required:** 5-10 minutes for 3 tournaments

---

### Option 2: SQL Data Cleanup Script (ADVANCED)

If you have many tournaments and don't want to re-import everything, use this SQL script.

**⚠️ WARNING:** This is an estimate-based approach and may not be 100% accurate for all players.

**How It Works:**
- Divides chip amounts by a typical starting chip count (20,000) to estimate entry count
- Works for players with standard chip amounts (20000, 40000, 60000, etc.)
- May be slightly inaccurate for players with non-standard chip amounts

**SQL Script:**

```sql
-- Step 1: Backup the table first!
CREATE TABLE poker_tournament_players_backup_20251016 AS SELECT * FROM wp_poker_tournament_players;

-- Step 2: Fix the buyins column by converting chip amounts to entry counts
-- Assumes 20,000 chips per entry (adjust if your tournament uses different amount)
UPDATE wp_poker_tournament_players
SET buyins = GREATEST(1, ROUND(buyins / 20000));

-- Step 3: Verify the changes (should show realistic numbers like 1, 2, 3)
SELECT player_id, buyins, winnings FROM wp_poker_tournament_players ORDER BY buyins DESC LIMIT 20;

-- Step 4: Clear and rebuild ROI table
TRUNCATE TABLE wp_poker_player_roi;

-- Step 5: Trigger the migration again via WordPress admin or run this:
-- Go to WordPress Admin → Poker Import → Migration Tools → "Refresh Statistics"
```

**After running the script:**
1. Go to WordPress Admin → Poker Import → Migration Tools
2. Click "Refresh Statistics" button to rebuild ROI table with corrected data
3. Check the top players panel to verify values are now realistic

**Time Required:** 2-3 minutes (if comfortable with SQL)

---

## 📊 HOW TO VERIFY THE FIX WORKED

After completing either cleanup option, verify the fix:

1. **Go to your WordPress admin dashboard**
2. **Check the Top Players panel**
3. **Look for realistic values:**
   - ✅ Good: -$200, -$400, -$600 (typical losses for 1-3 entries at $200 buy-in)
   - ❌ Bad: -$80,000, -$120,000 (these indicate corrupt data still present)

4. **Check the debug log** (if enabled):
   ```
   Player X: total_invested = 400 (should be realistic, not 40000)
   Player Y: net_profit = -200 (should be realistic, not -80000)
   ```

---

## 🔍 TECHNICAL DETAILS

### Root Cause Analysis

**What Happened:**

The `insert_tournament_players()` method in `admin/class-admin.php` at line 2512 was:
```php
$total_buyins += $buyin['amount'] ?? 0;
```

**The Problem:**

Each `$buyin['amount']` contains the starting **chip count** (e.g., 5000, 20000, 40000 chips), NOT the entry count.

When a player had 2 entries:
- Buyin 1: `amount = 20000` (chips)
- Buyin 2: `amount = 20000` (chips)
- **Stored in DB:** `buyins = 40000` (sum of chips)
- **Should have stored:** `buyins = 2` (count of entries)

**The Impact:**

Migration code at `class-statistics-engine.php` line 1630:
```php
$total_invested = $buy_in_amount * ($buyins + $rebuys + $addons);
```

With corrupt data:
- `$buy_in_amount = 200` (dollars)
- `$buyins = 40000` (chip amount, not entry count!)
- `$total_invested = 200 × 40000 = 8,000,000` ❌

With correct data:
- `$buy_in_amount = 200` (dollars)
- `$buyins = 2` (entry count!)
- `$total_invested = 200 × 2 = 400` ✅

### The Fix

Changed line 2511 to count entries instead of summing chip amounts:
```php
$total_buyins = count($player_data['buyins']);
```

Now the `buyins` column stores:
- `1` = player made 1 entry
- `2` = player made 2 entries (1 initial + 1 re-entry)
- `3` = player made 3 entries (1 initial + 2 re-entries)

Migration then correctly calculates:
- 1 entry: `$200 × 1 = $200`
- 2 entries: `$200 × 2 = $400`
- 3 entries: `$200 × 3 = $600`

---

## 📝 UPGRADE INSTRUCTIONS

### Step 1: Download v2.4.37

Download: `poker-tournament-import-v2.4.37.zip`

### Step 2: Backup Your Data

**Critical:** Always backup before upgrading!

**Option A - Database Backup (Recommended):**
```bash
# Via phpMyAdmin: Export → Custom → Select all poker_* tables → Go
# Or via command line:
mysqldump -u username -p database_name wp_poker_tournament_players wp_poker_player_roi > backup.sql
```

**Option B - Full Site Backup:**
Use your backup plugin (UpdraftPlus, BackWPup, etc.)

### Step 3: Install the Update

**Method 1 - WordPress Admin (Easiest):**
1. Go to WordPress Admin → Plugins → Add New
2. Click "Upload Plugin"
3. Choose `poker-tournament-import-v2.4.37.zip`
4. Click "Install Now"
5. Click "Replace current with uploaded" when prompted
6. Activate the plugin

**Method 2 - FTP/SFTP:**
1. Deactivate the plugin in WordPress Admin
2. Delete the `wp-content/plugins/poker-tournament-import/` directory
3. Upload the new `poker-tournament-import/` directory
4. Activate the plugin in WordPress Admin

**Method 3 - SSH/Command Line:**
```bash
cd /path/to/wordpress/wp-content/plugins/
rm -rf poker-tournament-import/
unzip poker-tournament-import-v2.4.37.zip
chown -R www-data:www-data poker-tournament-import/
```

### Step 4: Data Cleanup

**Follow Option 1 or Option 2** from the "DATA CLEANUP OPTIONS" section above.

### Step 5: Verify

Check the top players panel to confirm values are now realistic (-$200 to -$600 range, not -$80,000).

---

## 🆘 TROUBLESHOOTING

### Issue: Still seeing -$80,000 values after upgrade

**Cause:** You upgraded the plugin but didn't run the data cleanup.

**Solution:** Run Option 1 (re-import) or Option 2 (SQL script) from the "DATA CLEANUP OPTIONS" section.

---

### Issue: "Table not found" error after SQL script

**Cause:** Your WordPress table prefix is not `wp_`.

**Solution:** Find your prefix in `wp-config.php`:
```php
$table_prefix = 'wp_';  // This might be different (e.g., 'wpdb_', 'xyz_')
```

Update the SQL script to use your prefix:
```sql
-- Change all instances of wp_poker_ to yourprefix_poker_
UPDATE yourprefix_poker_tournament_players SET...
```

---

### Issue: ROI table still empty after migration

**Cause:** Migration didn't run or failed silently.

**Solution:**

1. Check error log for migration errors:
   ```bash
   tail -f /path/to/wordpress/wp-content/debug.log | grep "ROI Migration"
   ```

2. Manually trigger migration:
   - Go to WordPress Admin → Poker Import → Migration Tools
   - Click "Refresh Statistics"
   - Check if ROI table populates

3. Check if tournaments have valid buy-in amounts:
   ```sql
   SELECT post_id, meta_key, meta_value
   FROM wp_postmeta
   WHERE meta_key LIKE '%buy%'
   ORDER BY post_id;
   ```

---

### Issue: After cleanup, some players show $0 invested

**Cause:** Player has no buyin records or buyin data is missing.

**Solution:**

1. Check if player has buyin records:
   ```sql
   SELECT * FROM wp_poker_tournament_players WHERE player_id = 'player-uuid';
   ```

2. If `buyins = 0`, this is a data integrity issue. Re-import the tournament that includes this player.

---

## 📋 VERSION HISTORY CONTEXT

This bug was introduced in v2.4.36 which attempted to fix ROI calculations. Here's the version timeline:

- **v2.4.34:** Created ROI migration, but used simplified calculation (buy-in × count)
- **v2.4.35:** Attempted to fix cents/dollars issue (incorrectly divided by 100)
- **v2.4.36:** Reverted v2.4.35 division, attempted per-buyin fee lookup (but introduced chip amount bug)
- **v2.4.37:** Fixed the root cause - buyins column now stores entry counts, not chip amounts

---

## 🎯 EXPECTED RESULTS AFTER FIX

After completing the upgrade and data cleanup:

### Top Players Panel Should Show:
```
Player A: -$200 (1 entry, no winnings)
Player B: -$400 (2 entries, no winnings)
Player C: +$150 (2 entries, $550 winnings → profit: $550 - $400 = $150)
Player D: -$600 (3 entries, no winnings)
```

### Database Values Should Be:
```sql
SELECT player_id, buyins, rebuys, addons, winnings
FROM wp_poker_tournament_players;

-- Should show realistic values:
player_uuid_1 | 1 | 0 | 0 | 0.00
player_uuid_2 | 2 | 0 | 0 | 0.00
player_uuid_3 | 2 | 0 | 0 | 550.00
player_uuid_4 | 3 | 0 | 0 | 0.00
```

### ROI Table Should Show:
```sql
SELECT player_id, total_invested, total_winnings, net_profit
FROM wp_poker_player_roi
ORDER BY net_profit DESC
LIMIT 5;

-- Should show realistic values:
player_uuid_3 | 400.00  | 550.00  | 150.00
player_uuid_1 | 200.00  | 0.00    | -200.00
player_uuid_2 | 400.00  | 0.00    | -400.00
player_uuid_4 | 600.00  | 0.00    | -600.00
```

---

## 💬 SUPPORT

If you encounter any issues during the upgrade or data cleanup:

1. **Check debug log:** Enable WordPress debug mode and check for errors
2. **Report the issue:** Include debug log output and screenshots
3. **Rollback if needed:** Restore from your backup and wait for further guidance

---

## 🙏 APOLOGY & ACKNOWLEDGMENT

We apologize for the inconvenience caused by v2.4.36. This bug resulted from a fundamental misunderstanding of the data structure - we were summing chip amounts when we should have been counting entries.

**Thank you** for reporting the issue promptly with detailed logs. This helped us identify and fix the root cause quickly.

---

## ✅ FINAL CHECKLIST

Before considering the upgrade complete:

- [ ] Downloaded `poker-tournament-import-v2.4.37.zip`
- [ ] Created full database backup
- [ ] Installed v2.4.37 plugin
- [ ] Ran data cleanup (Option 1 or Option 2)
- [ ] Verified top players panel shows realistic values (-$200 to -$600 range)
- [ ] Checked ROI table has proper data (query provided above)
- [ ] Tested re-import of a new tournament (should work correctly)

---

**Version:** 2.4.37
**Release Date:** October 16, 2025
**Upgrade Priority:** CRITICAL - Immediate upgrade required
**Data Cleanup Required:** YES - Follow Option 1 or Option 2 above
