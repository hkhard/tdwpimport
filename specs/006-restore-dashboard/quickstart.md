# Quick Start Guide: Dashboard Restoration

**Feature**: 006-restore-dashboard
**Branch**: `006-restore-dashboard`
**Date**: 2025-01-02

## Overview

This guide helps you set up a development environment and implement the dashboard restoration feature. The dashboard is a WordPress admin page showing tournament statistics, quick actions, data mart health, and recent activity.

---

## Prerequisites

### Required Software
- **PHP**: 8.0+ (8.2+ compatible)
- **WordPress**: 6.0+
- **MySQL/MariaDB**: 5.7+ / 10.2+
- **Git**: For version control

### WordPress Installation
You need a working WordPress installation with the Poker Tournament Import plugin activated.

**Local Development Options**:
- Local by Flywheel (recommended)
- WP-CLI
- XAMPP/MAMP
- Dev environment of your choice

---

## Development Setup

### 1. Clone and Switch Branch

```bash
cd /path/to/tdwpimport
git checkout 006-restore-dashboard
```

### 2. Verify Plugin Structure

```bash
ls wordpress-plugin/poker-tournament-import/
# Expected: poker-tournament-import.php, admin/, includes/, assets/
```

### 3. Create Backup (Optional but Recommended)

```bash
# Backup current admin class
cp wordpress-plugin/poker-tournament-import/admin/class-admin.php \
   wordpress-plugin/poker-tournament-import/admin/class-admin.php.backup
```

---

## Implementation Workflow

### Step 1: Locate Implementation Point

**File**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`

**Find the menu registration**:
```bash
grep -n "add_admin_menu" wordpress-plugin/poker-tournament-import/admin/class-admin.php
```

You should see something like:
```php
add_menu_page(
    __('Poker Tournament Import', 'poker-tournament-import'),
    __('Poker Import', 'poker-tournament-import'),
    'manage_options',
    'poker-tournament-import',
    array($this, 'render_dashboard'),  // <-- Page callback
    $spade_icon,
    25
);
```

**Note**: The menu already points to `render_dashboard()` method - we just need to implement it.

---

### Step 2: Get Original Implementation

**Option A: View from Git History** (Recommended)

```bash
git show 4ff9552:wordpress-plugin/poker-tournament-import/admin/class-admin.php \
  | grep -A 300 "function render_dashboard"
```

**Option B: Copy from Research Document**

The research document (`research.md`) contains the full implementation details.

---

### Step 3: Add render_dashboard() Method

**Location**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`

**Add after** the `render_settings_page()` method (or similar admin page methods).

**Method Signature**:
```php
/**
 * Render the dashboard overview page
 *
 * @since 3.5.0
 */
public function render_dashboard() {
    // Implementation goes here
}
```

---

### Step 4: Implementation Checklist

Copy the implementation from the original version and ensure:

- [ ] Retrieve tournament count using `wp_count_posts('tournament')`
- [ ] Retrieve player count using `wp_count_posts('player')`
- [ ] Retrieve season count using `wp_count_posts('tournament_season')`
- [ ] Retrieve formula count using `Poker_Tournament_Formula_Validator::get_all_formulas()`
- [ ] Query data mart health (table existence, row count)
- [ ] Get last refresh time from `tdwp_statistics_last_refresh` option
- [ ] Retrieve 5 recent tournaments using `get_posts()`
- [ ] Render 4 stat cards with inline CSS
- [ ] Render Data Mart Health section
- [ ] Render Quick Actions section
- [ ] Render Recent Activity table (if tournaments exist)
- [ ] All output escaped with `esc_html()` and `esc_url()`
- [ ] All text translated with `__()` or `_e()`

---

### Step 5: PHP Syntax Check

```bash
php -l wordpress-plugin/poker-tournament-import/admin/class-admin.php
```

**Expected Output**: `No syntax errors detected in...`

---

### Step 6: Activate and Test

#### 6.1. Clear WordPress Cache

```bash
# If using WP-CLI
wp cache flush

# Or manually in WordPress admin:
# Dashboard > Updates > Clear Cache
```

#### 6.2. Navigate to Dashboard

1. Log into WordPress admin
2. Click "Poker Import" in left sidebar
3. You should see the dashboard with 4 stat cards

#### 6.3. Verify Each Section

**Stat Cards**:
- [ ] Tournaments count displayed
- [ ] Players count displayed
- [ ] Seasons count displayed
- [ ] Formulas count displayed
- [ ] "View All" / "Manage" buttons work

**Data Mart Health**:
- [ ] Status shows (Active/Not Created)
- [ ] Record count accurate
- [ ] Last refresh time displayed
- [ ] "Refresh Statistics" button works

**Quick Actions**:
- [ ] All 4 buttons displayed
- [ ] Each button navigates to correct page

**Recent Activity**:
- [ ] Shows 5 most recent tournaments (if any exist)
- [ ] Tournament names are links
- [ ] Action links (Edit/View/Trash) work

---

## Common Workflows

### Workflow 1: Test with Empty Database

```bash
# WordPress fresh install or empty plugin data
# Expected: All counts show "0", Recent Activity hidden
```

### Workflow 2: Test with Sample Data

```bash
# Import at least 5 tournaments
# Expected: Counts accurate, Recent Activity shows 5 tournaments
```

### Workflow 3: Test Data Mart Health

```bash
# Check if wp_poker_statistics table exists
wp db query "SHOW TABLES LIKE 'wp_poker_statistics'"

# If exists, check row count
wp db query "SELECT COUNT(*) FROM wp_poker_statistics"
```

### Workflow 4: Verify Links

```bash
# Test each link manually:
# - View All Tournaments: edit.php?post_type=tournament
# - View All Players: edit.php?post_type=player
# - View All Seasons: edit.php?post_type=tournament_season
# - Manage Formulas: admin.php?page=poker-formula-manager
# - Import Tournament: admin.php?page=poker-tournament-import-import
# - Refresh Statistics: admin.php?page=poker-tournament-import-settings
```

---

## Troubleshooting

### Issue: Dashboard shows blank white page

**Possible Causes**:
1. PHP syntax error
2. Fatal error in `render_dashboard()` method
3. Missing class dependency

**Solutions**:
```bash
# Check PHP error log
tail -f wp-content/debug.log

# Check syntax
php -l wordpress-plugin/poker-tournament-import/admin/class-admin.php

# Enable WordPress debugging
wp config set WP_DEBUG true --raw
```

---

### Issue: Stat counts show "0"

**Possible Causes**:
1. No posts of that type exist
2. `wp_count_posts()` returning unexpected results
3. Count calculation bug

**Solutions**:
```bash
# Verify posts exist in database
wp post list --post_type=tournament --format=count
wp post list --post_type=player --format=count
wp post list --post_type=tournament_season --format=count

# Check formulas
wp option get poker_formulas_category
```

---

### Issue: Data Mart Health shows "Not Created"

**Possible Causes**:
1. `wp_poker_statistics` table doesn't exist
2. Table name has different prefix

**Solutions**:
```bash
# Check if table exists
wp db query "SHOW TABLES LIKE '%poker_statistics'"

# Create table if missing
# Navigate to Settings > Poker Tournament Import > Refresh Statistics
```

---

### Issue: Recent Activity not showing

**Possible Causes**:
1. No tournaments exist
2. `get_posts()` query returning empty
3. Recent Activity section hidden by logic

**Solutions**:
```bash
# Create test tournaments
# Import .tdt file or create via admin

# Check recent tournaments
wp post list --post_type=tournament --orderby=date --order=DESC --fields=ID,post_title,post_date --per_page=5
```

---

## Testing Checklist

### Visual Testing
- [ ] Dashboard loads without errors
- [ ] Stat cards display in 4-column grid
- [ ] Data Mart Health on left, Quick Actions on right
- [ ] Recent Activity table at bottom
- [ ] All icons (dashicons) display correctly
- [ ] Colors and spacing match WordPress admin style

### Functional Testing
- [ ] All counts are accurate
- [ ] All links navigate correctly
- [ ] Data Mart status is correct
- [ ] Recent Activity shows 5 tournaments
- [ ] All action links work

### Security Testing
- [ ] All output escaped (no XSS)
- [ ] Unauthorized users cannot access (capability check)
- [ ] SQL injection protection (no user input in queries)

### Performance Testing
- [ ] Dashboard loads in <2 seconds
- [ ] No excessive database queries
- [ ] No PHP warnings or errors

---

## Code Reference

### Original Implementation

**Git Commit**: 4ff9552 (Version 3.4.0-beta4)
**File**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
**Method**: `render_dashboard()`

**View Original**:
```bash
git show 4ff9552:wordpress-plugin/poker-tournament-import/admin/class-admin.php \
  | sed -n '/public function render_dashboard/,/^        }$/p' | head -200
```

---

## Git Workflow

### Commit Implementation

```bash
# Check changes
git status
git diff wordpress-plugin/poker-tournament-import/admin/class-admin.php

# Stage changes
git add wordpress-plugin/poker-tournament-import/admin/class-admin.php

# Commit
git commit -m "Restore dashboard overview page

- Add render_dashboard() method to Poker_Tournament_Admin class
- Display 4 stat cards (Tournaments, Players, Seasons, Formulas)
- Show Data Mart Health section with status and record count
- Add Quick Actions section with 4 primary action buttons
- Display Recent Activity table with 5 most recent tournaments
- Use inline CSS matching WordPress admin aesthetic
- All output properly escaped with esc_html() and esc_url()
- Restores functionality from version 3.4.0-beta4 (commit 4ff9552)"
```

---

## Next Steps

After implementation:

1. **Create Distribution ZIP**:
   ```bash
   # Update version in poker-tournament-import.php
   # Create new zip file
   zip -r poker-tournament-import-v3.5.0-beta37.zip \
     wordpress-plugin/poker-tournament-import/
   ```

2. **Run Full Test Suite**:
   - Test all dashboard sections
   - Test with empty database
   - Test with sample data
   - Verify all links work

3. **Create Pull Request**:
   ```bash
   git push origin 006-restore-dashboard
   gh pr create --title "Restore Dashboard Overview" --body "..."
   ```

---

## Support

**Issues**: Report problems in GitHub issues or project management system

**Questions**: Refer to specification documents:
- `spec.md` - User stories and requirements
- `research.md` - Technical decisions
- `data-model.md` - Data structures used
- `contracts/README.md` - Admin page contract

---

**Last Updated**: 2025-01-02
**Feature Version**: 3.5.0-beta37 (planned)
