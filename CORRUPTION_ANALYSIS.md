# 2025 Tournament Points Corruption - Root Cause Analysis

## Executive Summary

Tournament **b9018b0d-396c-4f94-a64f-9fef443e8d13** (#146 ORF Poker 20251023) has **CORRUPT points values** ranging from **-10 to 1** (sum = -38).

**Root Cause:** During import, the formula receives `totalBuyinsAmount=0`, causing:
- `monies = 0 + 0 + 0 = 0`
- `avgBC = 0 / buyins = 0`
- `logTerm = 1 + log(0.25) = -0.386` (NEGATIVE)
- `baseAtRank = round(scale / sqrt(r) * logTerm)` = NEGATIVE
- Final points = NEGATIVE

---

## Confirmed Corruption Mechanism

### Test Input (Reconstructed from Database)

```
Tournament: b9018b0d-396c-4f94-a64f-9fef443e8d13 (#146)
Total players (n): 14
Hits (eliminated players tracked): 0
Buyins total: 0
Rebuys total: 0
Addons total: 0
```

### Formula Dependency Chain Execution

```
Step 1:  nSafe = max(n, 1) = max(14, 1) = 14
Step 2:  buyinsSafe = max(buyins, 1) = max(1, 1) = 1
Step 3:  T33 = round(nSafe / 3) = round(14 / 3) = 5
Step 4:  T80 = floor(nSafe * 0.9) = floor(14 * 0.9) = 12

Step 5:  monies = totalBuyinsAmount + totalRebuysAmount + totalAddOnsAmount
         = 0 + 0 + 0 = 0  ← *** CORRUPTION POINT ***

Step 6:  avgBC = monies / buyinsSafe = 0 / 1 = 0
Step 7:  logTerm = 1 + log(avgBC + 0.25) = 1 + log(0.25)
         = 1 + (-1.3862943611199) = -0.38629436111989  ← NEGATIVE!

Step 8:  baseAtRank = round((scale / sqrt(r)) * logTerm) = NEGATIVE
Step 9:  baseFromT33 = round((scale / sqrt(T33+1)) * logTerm) = NEGATIVE
```

**Result for Rank 2 player:** -10 points (instead of positive value)

---

## Where Points Are Imported

### File: `includes/class-parser.php`

#### 1. Entry Point: `public function parse_content($content)` (line 73)
Calls the main parsing logic. Flow:
```
parse_content()
  → parse_players() [line ~120]
  → calculate_tournament_points() [line 174]
```

#### 2. Points Calculation: `private function calculate_tournament_points($players, $financial)` (line 1441)

**Lines 1448-1452:** Initialize financial totals (BUGGY)
```php
1448  $buy_in_amount = $financial['buy_in'] ?? 0;
1449  $total_money = 0;
1450  $total_buyins = 0;
1451  $total_rebuys = 0;
1452  $total_addons = 0;
```

**Lines 1459-1485:** Loop through players, sum buyins (INCOMPLETE)
```php
1466  if (!empty($player['buyins']) && is_array($player['buyins'])) {
1467      $player_buyin_count = count($player['buyins']);
1468      $total_buyins += $player_buyin_count;
1469      
1470      foreach ($player['buyins'] as $buyin) {
1471          $profile_name = $buyin['profile'] ?? 'Standard';
1472          if (isset($financial['fee_profiles'][$profile_name])) {
1473              $dollar_amount = $financial['fee_profiles'][$profile_name]['fee'];
1474          } else {
1475              $dollar_amount = $buy_in_amount;
1476          }
1477          $total_money += $dollar_amount;  ← accumulates buyin fees
```

**Lines 1585-1600:** Prepare formula inputs (CORRUPT)
```php
1585  $tournament_data = array(
1586      'total_players' => $total_players,
1587      'finish_position' => $player['finish_position'],
1588      'hits' => $player['hits'],
1589      'total_money' => $total_money,
1590      'total_buyins' => $total_buyins,
1591      'total_rebuys' => $total_rebuys,
1592      'total_addons' => $total_addons,
1593      'total_buyins_amount' => $total_money,           ← CORRECT when money is extracted
1594      'total_rebuys_amount' => $total_rebuys * ($financial['rebuy_amount'] ?? 0),
1595      'total_addons_amount' => $total_addons * ($financial['addon_amount'] ?? 0),
```

**Line 1610:** Calculate formula with corrupt inputs
```php
1610  $result = $formula_validator->calculate_formula($complete_formula, $tournament_data, 'tournament');
```

**Lines 1612-1620:** Store corrupt points to database
```php
1612  if ($result['success']) {
1613      $players[$uuid]['points'] = $result['result'];  ← STORES NEGATIVE VALUE
1614      $players[$uuid]['points_calculation'] = array(...)
```

---

## Why totalBuyinsAmount = 0 (The Root Cause)

### Financial Data Extraction: `private function extract_financial_data($content)` (line 441)

**Lines 441-466:** Only extracts buy-in amounts from FeeProfiles
```php
441   private function extract_financial_data($content) {
442       $financial = array();
443       
444       // Extract buy-in amounts from FeeProfiles
445       preg_match_all('/new FeeProfile\(\{[^}]*Name:\s*"([^"]+)"[^}]*Fee:\s*(\d+)/', $content, $matches);
446       
447       if (!empty($matches[1]) && !empty($matches[2])) {
448           foreach ($matches[1] as $i => $name) {
449               $fee_amount = intval($matches[2][$i]);
450               $financial['fee_profiles'][$name] = array(
451                   'name' => $name,
452                   'fee' => $fee_amount
453               );
454               if (!isset($financial['buy_in']) || $name === 'Standard') {
455                   $financial['buy_in'] = $fee_amount;
456               }
457           }
458       }
459       
460       return $financial;  ← Returns empty if no FeeProfiles found!
461   }
```

**Problem:** If no FeeProfiles are extracted, `$financial['fee_profiles']` is empty, and line 1473 in calculate_tournament_points() falls back to `$buy_in_amount` which is 0.

---

## Database Impact: Points Stored at Import

### Table: `wp_poker_tournament_players`

**Columns:**
- `tournament_id` (varchar): UUID like b9018b0d-396c-4f94-a64f-9fef443e8d13
- `player_id` (varchar): Player UUID
- `finish_position` (int): 1 = 1st, 2 = 2nd, etc.
- `points` (decimal): **STORES CORRUPT VALUE HERE**
- `buyins` (int): Count of buy-ins per player
- `rebuys` (int): Count of rebuys per player
- `addons` (int): Count of add-ons per player
- `hits` (int): Players eliminated by this player

**Sample Row (2025 tournament):**
```
tournament_id = b9018b0d-396c-4f94-a64f-9fef443e8d13
player_id = f482b4f0-1bc9-11e3-0aa0-ed615033decd
finish_position = 2
points = -10.00  ← NEGATIVE (CORRUPT)
buyins = 0
rebuys = 0
addons = 0
hits = 0
```

---

## Where Formula Variables Come From

### At Import Time:

1. **`n` (total players)**
   - Source: COUNT of players in .tdt file
   - Passed as: `'total_players' => $total_players` (line 1586)

2. **`buyins` (count of buy-ins)**
   - Source: Sum of `count($player['buyins'])` across all players (line 1468)
   - Storage: Each player's buyin count → `wp_poker_tournament_players.buyins`
   - Passed as: Part of `totalBuyinsAmount` calculation (line 1593)

3. **`hits` (eliminations per player)**
   - Source: Extracted from .tdt player data (eliminated player count)
   - Storage: `wp_poker_tournament_players.hits`
   - Passed as: `'hits' => $player['hits']` (line 1588)

4. **`totalBuyinsAmount` (money collected from buy-ins)**
   - **Source:** `$total_money` from loop at lines 1470-1479
   - **Formula:** Sum of `$financial['fee_profiles'][$profile_name]['fee']` for each buyin
   - **Problem:** If `$financial['fee_profiles']` is empty (FeeProfile extraction failed), defaults to 0
   - **Stored as:** `wp_poker_tournament_players` does NOT store this value; it's only used at import time
   - **Passed as:** `'total_buyins_amount' => $total_money` (line 1593)

5. **`totalRebuysAmount` (money from rebuys)**
   - Source: `$total_rebuys * ($financial['rebuy_amount'] ?? 0)` (line 1594)
   - **Problem:** `$financial['rebuy_amount']` is NEVER set (extract_financial_data doesn't extract it)
   - **Result:** Always 0 or not set

6. **`totalAddOnsAmount` (money from add-ons)**
   - Source: `$total_addons * ($financial['addon_amount'] ?? 0)` (line 1595)
   - **Problem:** `$financial['addon_amount']` is NEVER set
   - **Result:** Always 0 or not set

---

## Why Is buyins=0 AND total_buyins=0?

Looking at the 2025 tournament data:
- All players have `buyins=0` (column in database)
- No FeeProfiles were extracted from the .tdt file
- Parser never found `$player['buyins']` array (line 1466 condition failed)

**This suggests:**
1. Either the .tdt file had no FeeProfile definitions
2. Or the extraction regex at line 445 failed to match the FeeProfile format
3. Or the buyins were not properly parsed into the player data structure

---

## Verification: Import-Time Log Files

To confirm this corruption for the 2025 tournament, check:
- WordPress debug log (if WP_DEBUG is enabled)
- Plugin debug log: Check for "=== v2.4.8 BUYIN CALCULATION DEBUG ===" marker
- Look for: "total_buyins (including re-entries): 0"
- Look for: "totalBuyinsAmount = 0"

---

## Summary: Exact Locations

| Item | File | Line | Details |
|------|------|------|---------|
| Points storage | includes/class-parser.php | 1613 | `$players[$uuid]['points'] = $result['result']` |
| Formula inputs prepared | includes/class-parser.php | 1585-1600 | `$tournament_data` array |
| Total money calculation | includes/class-parser.php | 1478 | `$total_money += $dollar_amount` |
| Financial extraction | includes/class-parser.php | 441-466 | `extract_financial_data()` method |
| FeeProfile regex | includes/class-parser.php | 445 | Regex pattern for FeeProfile matching |
| DB insert into poker_tournament_players | admin/class-admin.php | ~(needs search) | Points inserted during import save |

---

## Next Steps

1. **Verify .tdt file format** for 2025 tournament - check if FeeProfile definitions exist
2. **Fix extraction** - ensure FeeProfile regex matches the actual format in 2025 .tdt file
3. **Fix rebuys/addons extraction** - add extraction for rebuy and addon amounts
4. **Provide correction view** - allow user to see import-time points and re-apply after correction
5. **Support manual adjustment** - allow post-import points adjustment

