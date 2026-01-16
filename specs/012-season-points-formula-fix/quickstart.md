# Quickstart: Season Points Formula Fix

**Feature**: 012-season-points-formula-fix
**Branch**: `012-season-points-formula-fix`
**Purpose**: Fix Season Points calculation to honor active season formula

## Overview

**Bug**: Season Points column displays same value as Points column, ignoring configured active season formula (e.g., "best_10" should count only top 10 results but currently counts all).

**Fix**: Remove per-tournament formula evaluation loop and use existing `apply_series_formula()` method which correctly passes tournament_points array to formula validator.

**Files Modified**:
- `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php` (lines 287-329 removed)

## Development Setup

### 1. Checkout Branch

```bash
cd /Users/hkh/dev/tdwpimport
git checkout 012-season-points-formula-fix
```

### 2. Locate Fix Location

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Problem Code** (lines 287-329):
```php
// US3: Evaluate season formula per tournament with tournament-specific variables
if ($formula_key && $formula_key !== 'direct_sum') {
    foreach ($results as $result) {
        // ... accumulate totals

        // US3: Evaluate season formula for this specific tournament
        $tournament_season_points = $this->evaluate_season_formula_per_tournament(
            $formula_key,
            $result,
            $tournaments
        );
        $season_points += $tournament_season_points;  // Line 309 - WRONG!
    }
}
```

**Action**: Remove lines 287-329 (entire per-tournament evaluation loop)

### 3. Verify Correct Method Exists

**File**: Same file
**Lines**: 415-422

```php
if ($formula_key && $formula_key !== 'direct_sum') {
    $series_points = $this->apply_series_formula($series_data, $formula_key);
    $series_data['series_points'] = $series_points;
} else {
    $series_data['series_points'] = $total_points;
}
```

**Verification**: This code should still exist and will now execute correctly (not overwritten by per-tournament loop).

## Testing

### Manual Testing Setup

#### Prerequisites
1. WordPress site with plugin installed
2. Season with players having 15+ tournaments
3. Active formula set to "best_10" (or similar filtering formula)

#### Test Steps via SSH

```bash
# 1. Connect to WordPress site
/Users/hkh/Library/Application\ Support/Local/ssh-entry/hNPsf2SE_.sh

# 2. Set active formula
wp option update tdwp_active_season_formula "best_10" --allow-root

# 3. Clear transients (force recalculation)
wp transient delete poker_season_standings_* --allow-root

# 4. Deactivate/reactivate plugin
wp plugin deactivate poker-tournament-import --allow-root
wp plugin activate poker-tournament-import --allow-root
```

#### Test via WordPress Admin

1. **Set Active Formula**:
   - Log in to WordPress admin
   - Navigate to Poker → Settings → Formula Manager
   - Select "best_10" as Active Season Formula
   - Save changes

2. **View Season Leaderboard**:
   - Navigate to page with `[season_leaderboard season_id="X"]` shortcode
   - Check Season Points column

3. **Verify Fix**:
   - Find player with 10+ tournaments
   - Compare Points vs Season Points columns
   - Season Points should be LESS than Points

#### Expected Results

**Before Fix**:
```
Player: Fredrik Y
Tournaments: 18
Points: 2,500.0
Season Points: 2,500.0  ← WRONG (same as Points)
```

**After Fix**:
```
Player: Fredrik Y
Tournaments: 18
Points: 2,500.0
Season Points: 2,043.0  ← CORRECT (sum of best 10 only)
```

### Test Scenarios

#### Scenario 1: Player with 18 tournaments, best_10 formula

**Setup**:
- Player: Fredrik Y
- Tournaments: 18
- Active Formula: best_10
- Tournament Points: [100, 85, 92, 88, 95, 78, 90, 87, 93, 82, 89, 86, 91, 84, 80, 88, 85, 90]

**Expected**:
- Points: 1,611.0 (sum of all 18)
- Season Points: ~915.0 (sum of best 10)

**Verification**: Season Points < Points

#### Scenario 2: Player with 5 tournaments, best_10 formula

**Setup**:
- Player: Joakim H
- Tournaments: 5
- Active Formula: best_10
- Tournament Points: [100, 85, 92, 88, 95]

**Expected**:
- Points: 460.0 (sum of all 5)
- Season Points: 460.0 (sum of all 5 - fewer than 10)

**Verification**: Season Points = Points

#### Scenario 3: Player with 15 tournaments, season_total formula

**Setup**:
- Player: Any
- Tournaments: 15
- Active Formula: season_total
- Tournament Points: [various]

**Expected**:
- Points: [sum of all 15]
- Season Points: [sum of all 15]

**Verification**: Season Points = Points

### Edge Case Testing

#### Edge Case 1: Player with 0 tournaments
**Expected**: Season Points = 0

#### Edge Case 2: No active formula set
**Expected**: Season Points = Points (default to direct_sum)

#### Edge Case 3: Invalid formula key
**Expected**: Season Points = Points (fallback to direct_sum)

## Deployment

### 1. Code Changes

**File**: `wordpress-plugin/poker-tournament-import/includes/class-series-standings.php`

**Action**: Remove lines 287-329

**Before**:
```php
// US3: Evaluate season formula per tournament with tournament-specific variables
if ($formula_key && $formula_key !== 'direct_sum') {
    foreach ($results as $result) {
        $season_points += $this->evaluate_season_formula_per_tournament(...);
    }
}
```

**After**: (lines removed, code deleted)

### 2. Syntax Validation

```bash
php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
```

**Expected Output**: `No syntax errors detected in...`

### 3. Deployment Steps

**Option A: Manual Deployment**
```bash
# 1. Backup current file
cp wordpress-plugin/poker-tournament-import/includes/class-series-standings.php \
   wordpress-plugin/poker-tournament-import/includes/class-series-standings.php.backup

# 2. Upload modified file
# (Use FTP, SFTP, or file manager)

# 3. Clear caches
wp transient delete poker_season_standings_* --allow-root
wp cache flush --allow-root

# 4. Reactivate plugin
wp plugin deactivate poker-tournament-import --allow-root
wp plugin activate poker-tournament-import --allow-root
```

**Option B: Git Deployment**
```bash
# 1. Commit changes
git add wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
git commit -m "Fix season points formula calculation"

# 2. Push to remote
git push origin 012-season-points-formula-fix

# 3. Pull on server
cd /path/to/wordpress/wp-content/plugins/poker-tournament-import
git pull origin 012-season-points-formula-fix

# 4. Clear caches
wp transient delete poker_season_standings_* --allow-root
wp plugin deactivate poker-tournament-import --allow-root
wp plugin activate poker-tournament-import --allow-root
```

### 4. Post-Deployment Verification

1. **View season leaderboard page**
2. **Check Season Points column shows different values**
3. **Manual calculate for 1-2 players to verify**
4. **Check browser console for JavaScript errors**
5. **Check PHP error logs**

## Rollback Plan

If issues occur after deployment:

**Option 1: Restore Backup**
```bash
cd /path/to/wordpress/wp-content/plugins/poker-tournament-import
cp includes/class-series-standings.php.backup includes/class-series-standings.php
wp transient delete poker_season_standings_* --allow-root
wp plugin deactivate poker-tournament-import --allow-root
wp plugin activate poker-tournament-import --allow-root
```

**Option 2: Revert Commit**
```bash
git revert HEAD
git push origin 012-season-points-formula-fix
# Then pull on server
```

**Option 3: Switch Branches**
```bash
git checkout main
# Then redeploy main branch
```

## Troubleshooting

### Issue: Season Points still equals Points

**Possible Causes**:
1. Code changes not deployed
2. Transient cache not cleared
3. Active formula not set correctly
4. Different formula than expected

**Debug Steps**:
```bash
# Check active formula
wp option get tdwp_active_season_formula --allow-root

# Clear transients
wp transient delete poker_season_standings_* --allow-root

# Check PHP version
php -v

# Check file modifications
git diff wordpress-plugin/poker-tournament-import/includes/class-series-standings.php
```

### Issue: PHP errors after deployment

**Possible Causes**:
1. Syntax error in code
2. Incomplete file upload
3. Version incompatibility

**Debug Steps**:
```bash
# Check syntax
php -l wordpress-plugin/poker-tournament-import/includes/class-series-standings.php

# Check error logs
tail -f /path/to/wordpress/wp-content/debug.log
```

### Issue: Formula not working

**Possible Causes**:
1. Formula doesn't exist in definitions
2. Formula syntax error
3. Missing variables

**Debug Steps**:
```bash
# List available formulas
wp option get poker_formulas_season --format=json --allow-root | python3 -m json.tool

# Test formula directly
# (Use WordPress admin formula manager interface)
```

## Performance Validation

**Target**: Season leaderboard renders in < 5 seconds for 100+ players

**Test**:
1. Load season leaderboard with 100+ players
2. Use browser dev tools to measure load time
3. Verify < 5 seconds after cache clear
4. Verify < 2 seconds on subsequent loads (cached)

**If slow**:
- Check database queries
- Check formula evaluation time
- Consider increasing transient cache duration

## Completion Checklist

- [ ] Code changes applied (lines 287-329 removed)
- [ ] PHP syntax validation passed
- [ ] Active formula set to "best_10"
- [ ] Transients cleared
- [ ] Plugin reactivated
- [ ] Manual test: Season Points < Points for players with 10+ tournaments
- [ ] Manual test: Season Points = Points for players with < 10 tournaments
- [ ] Edge cases tested (0 tournaments, no formula, invalid formula)
- [ ] Performance verified (< 5 seconds)
- [ ] No PHP errors or warnings
- [ ] Browser console clean (no JS errors)
