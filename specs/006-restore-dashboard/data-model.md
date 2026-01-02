# Data Model: Dashboard Restoration

**Feature**: 006-restore-dashboard
**Date**: 2025-01-02
**Status**: ✅ No Changes Required

## Overview

This feature restores the WordPress admin dashboard overview page. **No new data structures are created** - all data comes from existing WordPress post types, options, and database tables.

## Existing Data Entities Used

### 1. Tournament Posts

**Type**: WordPress Custom Post Type
**Post Type Slug**: `tournament`
**Storage**: WordPress `wp_posts` table

**Purpose**: Display tournament count in stat card and show recent activity

**Query Method**:
```php
$tournament_count = wp_count_posts('tournament');
$total_tournaments = $tournament_count->publish + $tournament_count->draft + $tournament_count->private;
```

**Fields Used**:
- `post_title` - Tournament name (displayed in recent activity)
- `post_date` - Import date (displayed in recent activity)
- `post_status` - Published/Draft/Private (displayed in recent activity)

**Recent Activity Query**:
```php
$recent_tournaments = get_posts(array(
    'post_type' => 'tournament',
    'posts_per_page' => 5,
    'orderby' => 'date',
    'order' => 'DESC',
    'post_status' => array('publish', 'draft', 'private')
));
```

---

### 2. Player Posts

**Type**: WordPress Custom Post Type
**Post Type Slug**: `player`
**Storage**: WordPress `wp_posts` table

**Purpose**: Display player count in stat card

**Query Method**:
```php
$player_count = wp_count_posts('player');
$total_players = $player_count->publish + $player_count->draft + $player_count->private;
```

**Fields Used**: Count only (no individual player data displayed on dashboard)

---

### 3. Season Posts

**Type**: WordPress Custom Post Type
**Post Type Slug**: `tournament_season`
**Storage**: WordPress `wp_posts` table

**Purpose**: Display season count in stat card

**Query Method**:
```php
$season_count = wp_count_posts('tournament_season');
$total_seasons = $season_count->publish + $season_count->draft + $season_count->private;
```

**Fields Used**: Count only (no individual season data displayed on dashboard)

---

### 4. Formulas

**Type**: WordPress Options
**Storage**: WordPress `wp_options` table

**Purpose**: Display formula count in stat card

**Query Method**:
```php
$validator = new Poker_Tournament_Formula_Validator();
$formulas = $validator->get_all_formulas();
$total_formulas = count($formulas);
```

**Fields Used**: Count only (formula details managed on separate Formula Manager page)

**Integration**: Uses existing `Poker_Tournament_Formula_Validator` class

---

### 5. Data Mart Health

**Type**: WordPress Database Table
**Table Name**: `wp_poker_statistics`
**Storage**: Custom MySQL table

**Purpose**: Display data mart status, record count, and last refresh time

**Query Method**:
```php
global $wpdb;
$table_name = $wpdb->prefix . 'poker_statistics';

// Check if table exists
$datamart_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);

// Get row count
if ($datamart_exists) {
    $datamart_row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
} else {
    $datamart_row_count = 0;
}

// Get last refresh time
$datamart_last_refresh = get_option('tdwp_statistics_last_refresh', null);
```

**Fields Displayed**:
- Status: "Active" (green) or "Not Created" (red)
- Record count: Number of rows in `wp_poker_statistics`
- Last refresh: Timestamp from `tdwp_statistics_last_refresh` option

---

## Data Flow

```
Dashboard Page Load
    ↓
┌─────────────────────────────────────────────────────────────┐
│  Data Retrieval (All queries happen server-side)            │
├─────────────────────────────────────────────────────────────┤
│  1. wp_count_posts('tournament') → Total tournaments       │
│  2. wp_count_posts('player') → Total players               │
│  3. wp_count_posts('tournament_season') → Total seasons    │
│  4. Poker_Tournament_Formula_Validator::get_all_formulas() │
│     → Total formulas                                        │
│  5. $wpdb query → Data mart existence & row count          │
│  6. get_option() → Data mart last refresh time             │
│  7. get_posts() → 5 most recent tournaments               │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│  Render Dashboard Sections                                  │
├─────────────────────────────────────────────────────────────┤
│  • Stat Cards Grid (4 cards)                               │
│  • Quick Actions (4 buttons)                               │
│  • Data Mart Health (status table)                         │
│  • Recent Activity (5 tournaments table)                   │
└─────────────────────────────────────────────────────────────┘
```

## Relationships

**No New Relationships**: All entities are independent and already exist in the system.

**Existing Relationships** (not modified by this feature):
- Tournaments may belong to a Season (via `_season_id` meta key)
- Tournaments have Players (via `wp_poker_tournament_players` table)
- Formulas calculate Tournament statistics

These relationships are **displayed elsewhere** in the plugin, not on the dashboard.

## Validation Rules

**No Validation Required**: Dashboard is read-only. No user input is accepted.

**Display Formatting**:
- Counts: `number_format($count)` - adds thousand separators
- Dates: `date_i18n('M j, Y g:i A', $timestamp)` - localized date/time
- Currency: Not displayed on dashboard (shown elsewhere)
- Status indicators: Colored dots (●) with descriptive text

## State Transitions

**Not Applicable**: Dashboard does not manage state. It only displays current state of other entities.

---

## Summary

**Data Model Impact**: None. This feature uses existing data structures and does not create any new entities, relationships, or validation rules.

**Read-Only Access**: Dashboard is purely informational. All data retrieval uses WordPress core functions or existing plugin classes.
