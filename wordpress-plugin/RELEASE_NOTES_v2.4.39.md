# Poker Tournament Import Plugin - Version 2.4.39 Release Notes

**Release Date:** October 16, 2025
**Criticality:** CRITICAL BUGFIX - Immediate upgrade required for all v2.4.38 users
**Type:** Prize Extraction Bug Fix

---

## 🚨 CRITICAL ISSUE RESOLVED

### Prize Extraction Failure: GamePrizes Wrapper Not Handled

**Version 2.4.38 attempted to fix winnings display but failed** because prizes were not being extracted from the .tdt file at all. This version fixes the root cause of the prize extraction failure.

#### The Problem

Modern .tdt files wrap prize data in a **GamePrizes constructor** that was not being unwrapped:

```
Prizes: new GamePrizes({Prizes: [new GamePrize({...}), ...]})
```

The domain mapper's `extract_prizes()` method expected:

```
Prizes: [new GamePrize({...}), ...]
```

#### The Flow of the Bug

1. **User's .tdt file contains:** `Prizes: new GamePrizes({Prizes: [new GamePrize({Description: "1st Place", CalculatedAmount: 2200, Recipient: 1}), ...]})`
2. **Domain mapper extracts:** `$prizes_node = $entries['Prizes']` → Gets the GamePrizes constructor
3. **Type check fails:** `if (!$this->is_type($prizes_node, 'Array'))` → TRUE (it's a GamePrizes constructor, not an Array)
4. **Debug log shows:** `"Poker Import - WARNING: Prizes node is not an Array type"`
5. **Method returns:** Empty prizes array `return $prizes;`
6. **Parser receives:** 0 prizes to distribute
7. **Debug log shows:** `"Poker Import - Total prizes to distribute: 0"`
8. **Winnings calculation:** No prizes to match with players → all winnings = $0.00
9. **ROI table shows:** total_invested = $200, total_winnings = $0.00, net_profit = -$200

#### The Impact

- **All prize winners** showed $0.00 winnings
- **ROI table** showed only negative values (investments) with no prize winnings
- **Top players panel** showed incorrect net profit (missing prize winnings)
- **Dashboard statistics** showed no prize distribution data

---

## ✅ WHAT'S FIXED IN v2.4.39

### Code Fix

**File:** `includes/class-tdt-domain-mapper.php`
**Method:** `extract_prizes()`
**Lines:** 544-579

**Before (WRONG):**
```php
private function extract_prizes($entries) {
    $prizes = array();

    if (!isset($entries['Prizes'])) {
        return $prizes;
    }

    $prizes_node = $entries['Prizes'];

    // THIS CHECK FAILS - prizes_node is GamePrizes constructor, not Array!
    if (!$this->is_type($prizes_node, 'Array')) {
        error_log("Poker Import - WARNING: Prizes node is not an Array type");
        return $prizes; // EXITS HERE WITH EMPTY ARRAY
    }

    // ... rest never executes ...
}
```

**After (CORRECT):**
```php
private function extract_prizes($entries) {
    $prizes = array();

    if (!isset($entries['Prizes'])) {
        error_log("Poker Import - WARNING: No Prizes field found");
        return $prizes;
    }

    $prizes_wrapper = $entries['Prizes'];

    // v2.4.39: Check if wrapped in GamePrizes constructor (modern format)
    if ($this->is_new_with_ctor($prizes_wrapper, 'GamePrizes')) {
        error_log("Poker Import - Found GamePrizes wrapper, unwrapping...");
        // Extract the inner GamePrizes object
        $game_prizes_obj = $this->expect_object($prizes_wrapper['arg']);
        $gp_entries = $game_prizes_obj['entries'];

        // Get the inner Prizes field
        if (!isset($gp_entries['Prizes'])) {
            error_log("Poker Import - WARNING: No Prizes field inside GamePrizes wrapper");
            return $prizes;
        }

        $prizes_node = $gp_entries['Prizes'];
    } else {
        // Direct array format (older files)
        $prizes_node = $prizes_wrapper;
    }

    // Now check if the unwrapped node is an Array
    if (!$this->is_type($prizes_node, 'Array')) {
        error_log("Poker Import - WARNING: Prizes node is not an Array type");
        return $prizes;
    }

    // ... rest of extraction continues ...
}
```

### What This Fixes

- **Modern .tdt files** now extract prizes correctly
- **Prize winners** show actual prize amounts instead of $0.00
- **ROI calculations** include prize winnings in total_winnings
- **Top players panel** displays accurate net profit values
- **Backward compatible** with older files that use direct array format

---

## 🔧 TECHNICAL DETAILS

### The Pattern Used

This fix applies the **same unwrapping pattern** that was proven successful for GamePlayers in version 2.4.14:

**GamePlayers unwrapping (v2.4.14, lines 248-263):**
```php
if ($this->is_new_with_ctor($players_wrapper, 'GamePlayers')) {
    $game_players_obj = $this->expect_object($players_wrapper['arg']);
    $gp_entries = $game_players_obj['entries'];
    if (!isset($gp_entries['Players'])) {
        return $players;
    }
    $players_node = $gp_entries['Players'];
}
```

**GamePrizes unwrapping (v2.4.39, lines 555-572):**
```php
if ($this->is_new_with_ctor($prizes_wrapper, 'GamePrizes')) {
    $game_prizes_obj = $this->expect_object($prizes_wrapper['arg']);
    $gp_entries = $game_prizes_obj['entries'];
    if (!isset($gp_entries['Prizes'])) {
        return $prizes;
    }
    $prizes_node = $gp_entries['Prizes'];
}
```

### Supported Formats

**Modern Format (Tournament Director 3.7+):**
```
Prizes: new GamePrizes({
    Prizes: [
        new GamePrize({Description: "1st Place", CalculatedAmount: 2200, Recipient: 1}),
        new GamePrize({Description: "2nd Place", CalculatedAmount: 1300, Recipient: 2}),
        new GamePrize({Description: "3rd Place", CalculatedAmount: 800, Recipient: 3})
    ]
})
```

**Legacy Format (Older Tournament Director versions):**
```
Prizes: [
    new GamePrize({Description: "1st Place", CalculatedAmount: 2200, Recipient: 1}),
    new GamePrize({Description: "2nd Place", CalculatedAmount: 1300, Recipient: 2}),
    new GamePrize({Description: "3rd Place", CalculatedAmount: 800, Recipient: 3})
]
```

Both formats are now supported!

---

## 📋 UPGRADE INSTRUCTIONS

### Step 1: Download v2.4.39

Download: `poker-tournament-import-v2.4.39.zip`

### Step 2: Backup Your Data (Always!)

```bash
# Via phpMyAdmin: Export → Custom → Select all poker_* tables → Go
# Or via command line:
mysqldump -u username -p database_name wp_poker_tournament_players wp_poker_player_roi > backup_before_2.4.39.sql
```

### Step 3: Install the Update

**Method 1 - WordPress Admin (Easiest):**
1. Go to WordPress Admin → Plugins → Add New
2. Click "Upload Plugin"
3. Choose `poker-tournament-import-v2.4.39.zip`
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
unzip poker-tournament-import-v2.4.39.zip
chown -R www-data:www-data poker-tournament-import/
```

### Step 4: Re-Import Affected Tournaments

Since the bug prevented prizes from being extracted, you need to re-import your tournaments:

1. **Clear existing tournament data:**
   - Go to WordPress Admin → Poker Import → Migration Tools
   - Click "Clean Player Data" button
   - Click "Clean Financial Data" button

2. **Re-import your .tdt files:**
   - Go to Poker Import → Import Tournament
   - Upload your .tdt files again
   - The new import will extract prizes correctly

### Step 5: Verify the Fix

Check the dashboard to confirm prizes are now showing:

1. Go to your WordPress admin dashboard
2. Check the **Top Players panel**
3. Look for realistic net profit values:
   - ✅ **Good:** Players with winnings show positive values (e.g., +$500, +$1,200)
   - ✅ **Good:** Players without winnings show realistic losses (e.g., -$200, -$400)
   - ❌ **Bad:** All players show negative values (indicates prizes still not extracting)

---

## 🐛 TROUBLESHOOTING

### Issue: Still seeing $0.00 winnings after upgrade

**Cause:** You upgraded the plugin but didn't re-import the tournaments.

**Solution:** Follow Step 4 above to re-import your .tdt files.

---

### Issue: Debug log still shows "Prizes node is not an Array type"

**Cause:** Your .tdt file might have a different prize structure.

**Solution:**
1. Check your error log for the new debug message: `"Poker Import - Found GamePrizes wrapper, unwrapping..."`
2. If you don't see this message, share your .tdt file structure for further analysis

---

### Issue: Some prizes extract but not all

**Cause:** Possible data integrity issue in the .tdt file.

**Solution:**
1. Check error log for individual prize extraction messages
2. Verify each GamePrize has required fields: `Recipient`, `CalculatedAmount`
3. Share specific error messages for troubleshooting

---

## 🎯 EXPECTED RESULTS AFTER FIX

### Top Players Panel Should Show:

```
Player A: +$500 (1st place in Tournament A)
Player B: +$150 (3rd place in Tournament A)
Player C: -$200 (10th place, no winnings)
Player D: +$1,200 (1st place in Tournament A, 2nd place in Tournament B)
```

### ROI Table Should Show:

```sql
SELECT player_id, total_invested, total_winnings, net_profit
FROM wp_poker_player_roi
ORDER BY net_profit DESC
LIMIT 5;

-- Should show realistic values:
player_uuid_A | 200.00  | 700.00  | 500.00
player_uuid_D | 400.00  | 1600.00 | 1200.00
player_uuid_B | 200.00  | 350.00  | 150.00
player_uuid_C | 200.00  | 0.00    | -200.00
```

### Debug Log Should Show:

```
[16-Oct-2025 22:15:30 UTC] Poker Import - Found GamePrizes wrapper, unwrapping...
[16-Oct-2025 22:15:30 UTC] Poker Import - Found Prizes array with 8 items
[16-Oct-2025 22:15:30 UTC] Poker Import - Prize #1: Position 1, Amount $2200, Description: 1st Place
[16-Oct-2025 22:15:30 UTC] Poker Import - Prize #2: Position 2, Amount $1300, Description: 2nd Place
[16-Oct-2025 22:15:30 UTC] Poker Import - Prize #3: Position 3, Amount $800, Description: 3rd Place
...
[16-Oct-2025 22:15:30 UTC] Poker Import - Extracted 8 prizes from AST
[16-Oct-2025 22:15:30 UTC] Poker Import - === WINNINGS CALCULATION START ===
[16-Oct-2025 22:15:30 UTC] Poker Import - Total prizes to distribute: 8
[16-Oct-2025 22:15:30 UTC] Poker Import - WINNINGS MATCH: Joakim H at position 1 wins $2200
```

---

## 📊 VERSION HISTORY CONTEXT

This bug fix chain:

- **v2.4.37:** Fixed buyins column storing chip amounts instead of entry counts
- **v2.4.38:** Added type-safe comparison for prize position matching (INCOMPLETE - prizes not extracting)
- **v2.4.39:** **Fixed prize extraction** by unwrapping GamePrizes constructor (COMPLETE FIX)

The progression shows:
1. v2.4.37 fixed the database schema issue (buyins column)
2. v2.4.38 attempted to fix winnings calculation logic (type comparison)
3. v2.4.39 fixed the actual root cause (prize extraction failure)

---

## 💬 SUPPORT

If you encounter any issues during the upgrade:

1. **Check debug log:** Look for `"Poker Import - Found GamePrizes wrapper, unwrapping..."` message
2. **Verify prizes exist in .tdt file:** Search for "GamePrizes" or "GamePrize" in your file
3. **Report the issue:** Include debug log output and .tdt file structure (redact sensitive data)

---

## 🙏 ACKNOWLEDGMENT

Thank you for your patience through this bug fix series. The issue was more complex than initially diagnosed:

1. Database schema issue (v2.4.37)
2. Type comparison issue (v2.4.38)
3. **Prize extraction issue (v2.4.39)** ← The actual root cause

Your detailed logs and .tdt file structure examples were essential in identifying the real problem.

---

## ✅ FINAL CHECKLIST

Before considering the upgrade complete:

- [ ] Downloaded `poker-tournament-import-v2.4.39.zip`
- [ ] Created full database backup
- [ ] Installed v2.4.39 plugin
- [ ] Cleaned player and financial data
- [ ] Re-imported .tdt files
- [ ] Verified top players panel shows realistic values
- [ ] Checked debug log for "Found GamePrizes wrapper" message
- [ ] Confirmed ROI table has proper data with prize winnings
- [ ] Tested prize winners show positive net profit

---

**Version:** 2.4.39
**Release Date:** October 16, 2025
**Upgrade Priority:** CRITICAL - Immediate upgrade required for v2.4.38 users
**Data Cleanup Required:** YES - Re-import tournaments after upgrade
