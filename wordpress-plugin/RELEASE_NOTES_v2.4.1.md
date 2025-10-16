# Poker Tournament Import - Version 2.4.1 Release Notes

**Release Date:** October 15, 2025
**Release Type:** Critical Bug Fix Release
**Package:** poker-tournament-import-v2.4.1-bugfixes.zip

---

## 🐛 Critical Bug Fixes

This release addresses **5 critical issues** reported in production environments running PHP 8.1+ and 8.2+.

### 1. PHP 8.1+ Float-to-Int Deprecation Fix
**File:** `includes/class-parser.php:162`
**Issue:** Implicit conversion from float to int causing deprecation warnings
**Fix:** Added explicit `intval()` wrapper for timestamp conversion
**Impact:** Eliminates PHP 8.1+ deprecation warnings during tournament imports

```php
// BEFORE
$metadata['start_time'] = date('Y-m-d H:i:s', $matches[1] / 1000);

// AFTER
$metadata['start_time'] = date('Y-m-d H:i:s', intval($matches[1] / 1000));
```

### 2. PHP 8.2+ Dynamic Property Deprecation Fix
**File:** `admin/migration-tools.php:19`
**Issue:** Creation of dynamic properties deprecated in PHP 8.2+
**Fix:** Added explicit property declaration
**Impact:** Eliminates PHP 8.2+ deprecation warnings in admin interface

```php
// ADDED
class Poker_Migration_Admin_Page {
    /**
     * PHP 8.2+ compatibility - declare dynamic properties
     */
    private $migration_tools;

    public function __construct() {
        $this->migration_tools = new Poker_Tournament_Migration_Tools();
```

### 3. Formula Editor Save Functionality Fix
**File:** `admin/class-admin.php:4046`
**Issue:** Formula editor meta box not saving changes due to class name mismatch
**Fix:** Corrected class name from `Poker_Formula_Validator` to `Poker_Tournament_Formula_Validator`
**Impact:** Formula editor now properly saves user changes

```php
// BEFORE
$validator = new Poker_Formula_Validator();

// AFTER
$validator = new Poker_Tournament_Formula_Validator();
```

### 4. PokerStars Points Formula Implementation
**Files:**
- `includes/class-parser.php:985-1006` (Hardcoded fallback)
- `includes/class-formula-validator.php:375-393` (Default formula)

**Issue:** Points calculation using simplified formula instead of PokerStars specification
**Fix:** Implemented complete piecewise formula with decay, thresholds, and safety checks
**Impact:** Accurate point calculations matching PokerStars formula (1st=267pts, 2nd=189pts, 3rd=154pts)

**Formula Components:**
- **Safety checks**: `nSafe = max(n, 1)`, `buyinsSafe = max(buyins, 1)`
- **Thresholds**: `T33 = round(nSafe/3)`, `T80 = floor(nSafe*0.9)`
- **Decay factor**: `pow(0.66, (r - T33))`
- **Piecewise logic**: Top third, middle (with decay), bottom positions

### 5. Variable Extraction Verification
**Analysis:** Confirmed all player performance variables required by formulas are properly extracted
**Variables Extracted:** n, r, buyins, hits, totalBuyInsAmount, totalRebuysAmount, totalAddOnsAmount, winnings, and 14+ others
**Impact:** No additional extraction needed - current implementation is complete

---

## 📦 Package Contents

**File:** `poker-tournament-import-v2.4.1-bugfixes.zip`
**Size:** 217 KB
**Files:** 40 files total

### Modified Files (5):
1. `poker-tournament-import.php` - Version bump to 2.4.1
2. `readme.txt` - Updated changelog and upgrade notices
3. `includes/class-parser.php` - PHP 8.1+ fix + formula update
4. `admin/migration-tools.php` - PHP 8.2+ fix
5. `admin/class-admin.php` - Formula editor class name fix
6. `includes/class-formula-validator.php` - PokerStars formula update

---

## 🚀 Installation Instructions

### For New Installations:
1. Upload `poker-tournament-import-v2.4.1-bugfixes.zip` to WordPress
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Activate the plugin

### For Existing Installations (Upgrade):
1. **Backup your database** before upgrading
2. Deactivate the current version
3. Delete the old plugin folder
4. Upload and activate v2.4.1
5. Plugin will auto-refresh statistics on first load

---

## ✅ Testing Verification

### Test 1: PHP Deprecation Warnings
**Expected:** No deprecation warnings during tournament import
**Test:** Import a .tdt file on PHP 8.1+ and 8.2+
**Result:** ✅ Clean import with no warnings

### Test 2: Formula Editor Functionality
**Expected:** Formula changes save and persist
**Test:** Edit tournament formula in meta box, save, reload page
**Result:** ✅ Formula changes persisted correctly

### Test 3: Points Calculation Accuracy
**Expected:** Points match PokerStars specification
**Test Tournament:** 20 players, finish positions 1-3
**Expected Results:**
- 1st place: 267 points
- 2nd place: 189 points
- 3rd place: 154 points

**Formula Verification:**
- Top third (r ≤ 7): `baseAtRank + hits`
- Middle (7 < r ≤ 18): `round(baseFromT33 * decay) + hits`
- Bottom (r > 18): `1 + hits`

---

## 🔧 Technical Details

### PHP Compatibility
- **Minimum:** PHP 8.0
- **Tested:** PHP 8.1, 8.2, 8.3
- **Deprecated Code:** All eliminated

### WordPress Compatibility
- **Minimum:** WordPress 6.0
- **Tested:** WordPress 6.4
- **Database:** No schema changes required

### Formula Priority Hierarchy
1. Per-tournament formula (from .tdt file) - **Highest Priority**
2. Active global formula (from settings)
3. Default formula (PokerStars specification)
4. Hardcoded fallback (PokerStars specification) - **Lowest Priority**

---

## 📊 Performance Impact

- **Import Speed:** No change (fixes are optimization-neutral)
- **Memory Usage:** No change
- **Database Queries:** No change
- **Formula Calculation:** ~5% faster due to AST optimization (from v2.4.0)

---

## 🔐 Security

All fixes maintain security standards from v2.4.0:
- ✅ AST-based formula evaluation (no eval())
- ✅ Input sanitization maintained
- ✅ Nonce verification unchanged
- ✅ Capability checks preserved

---

## 📝 Changelog Summary

```
= 2.4.1 - October 15, 2025 =
🐛 CRITICAL BUG FIXES: PHP 8.1+/8.2+ Compatibility & Formula Editor
✅ FIXED: PHP 8.1+ deprecation - Float-to-int conversion warning
✅ FIXED: PHP 8.2+ deprecation - Dynamic property declaration
✅ FIXED: Formula editor not saving - Class name mismatch corrected
✅ FIXED: Points calculation - PokerStars formula implemented
✅ ENHANCED: PokerStars formula - Decay factor and thresholds
✅ VERIFIED: Variable extraction - All player data properly extracted
```

---

## 🆘 Support

If you encounter issues after upgrading:

1. **Check PHP Version:** Must be 8.0+
2. **Clear WordPress Cache:** Delete all transients
3. **Refresh Statistics:** Use admin dashboard refresh button
4. **Review Logs:** Check WordPress debug.log for errors
5. **Test Import:** Try importing a sample .tdt file

For bug reports: https://github.com/your-repo/issues

---

## 📅 Upgrade Path

- **From v2.4.0:** Direct upgrade, no migration needed
- **From v2.3.x:** Direct upgrade, statistics will auto-refresh
- **From v2.2.x or earlier:** Review v2.4.0 release notes first

---

## ⚠️ Important Notes

1. **Backup Required:** Always backup database before upgrading
2. **PHP 8.0+ Required:** Plugin will not work on PHP 7.x
3. **Formula Changes:** Existing tournaments will recalculate points on next import
4. **Statistics Refresh:** May take 1-2 minutes for large datasets

---

**Developed by:** Hans Kästel Hård
**License:** GPL v2 or later
**Support:** https://nikielhard.se/tdwpimport
