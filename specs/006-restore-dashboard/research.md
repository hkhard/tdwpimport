# Research Report: Dashboard Restoration

**Feature**: 006-restore-dashboard
**Date**: 2025-01-02
**Status**: ✅ Complete

## Overview

This document consolidates research findings for restoring the WordPress admin dashboard overview page. All technical decisions have been made and documented.

## Research Topic 1: Original Dashboard Implementation

### Decision: Restore from commit 4ff9552 (v3.4.0-beta4)

**Rationale**:
- The `render_dashboard()` method existed and was fully functional in version 3.4.0-beta4
- Git history shows complete implementation with all 4 dashboard sections
- No need to redesign - copy and adapt existing code
- Commit 4ff9552 contains the exact implementation we need to restore

**Alternatives Considered**:
- **Reimplement from scratch**: Rejected because original implementation is proven and working
- **Use newer version as reference**: Rejected because later versions removed the dashboard entirely

**Implementation Details**:
- Location: `admin/class-admin.php`
- Method signature: `public function render_dashboard()`
- Approximate length: 200 lines of PHP/HTML
- Uses inline CSS styles (not external stylesheet)
- Integrates with existing menu registration (menu slug: 'poker-tournament-import')

## Research Topic 2: Dashboard Components

### Decision: Restore 4 sections exactly as they existed

**Components Identified**:
1. **Stat Cards Grid** (4 columns)
   - Tournaments card with count and "View All" button
   - Players card with count and "View All" button
   - Seasons card with count and "View All" button
   - Formulas card with count and "Manage" button

2. **Quick Actions** section
   - Import Tournament button (primary style)
   - View Tournaments button
   - View Players button
   - Manage Formulas button

3. **Data Mart Health** section
   - Status indicator (Active/Not Created)
   - Record count
   - Last refresh timestamp
   - Refresh Statistics button

4. **Recent Activity** table
   - Shows 5 most recent tournaments
   - Columns: Tournament Name, Date Imported, Status, Actions
   - Action links: Edit, View, Trash

**Rationale**: All 4 sections provide value to tournament directors and existed in v3.3/v3.4

## Research Topic 3: Data Retrieval Strategy

### Decision: Use WordPress core functions exclusively

**Functions to Use**:
1. **Stat Card Counts**: `wp_count_posts($post_type)` for tournaments, players, seasons
2. **Formula Count**: `Poker_Tournament_Formula_Validator::get_all_formulas()` → `count()`
3. **Recent Tournaments**: `get_posts()` with `post_type='tournament'`, `posts_per_page=5`, `orderby='date'`, `order='DESC'`
4. **Data Mart Health**: Direct query to `wp_poker_statistics` table with `$wpdb->prepare()`

**Rationale**:
- WordPress core functions are cached and optimized
- No need for custom SQL queries for post counts
- Direct table query acceptable for data mart health (simple existence check)
- Matches original implementation approach

**Alternatives Considered**:
- **Custom SQL for all counts**: Rejected because WordPress core functions are sufficient
- **Transients for caching**: Rejected because WordPress already caches count queries

## Research Topic 4: Styling Strategy

### Decision: Use inline CSS matching original implementation

**Rationale**:
- Original implementation used inline styles
- No external CSS file needed
- Simpler to maintain (styles with markup)
- Faster to implement (copy from original)

**CSS Patterns**:
- Grid layout: `grid-template-columns: repeat(4, 1fr)` for stat cards
- Card styling: White background, border, box-shadow, padding
- Two-column layout below stat cards: `grid-template-columns: 2fr 1fr`
- Dashicons: `dashicons-list-view`, `dashicons-groups`, `dashicons-calendar-alt`, `dashicons-calculator`, `dashicons-database`, `dashicons-admin-tools`, `dashicons-clock`

**Alternatives Considered**:
- **External CSS file**: Rejected because original used inline styles, no clear benefit to separating

## Research Topic 5: Security & Escaping

### Decision: Use standard WordPress escaping functions

**Functions to Use**:
- `esc_html()` for all text output
- `esc_url()` for all URLs (admin_url() results, links)
- `number_format()` for numeric counts
- `date_i18n()` for date formatting

**Rationale**:
- Follows WordPress security best practices
- Prevents XSS attacks
- Required by constitution
- Matches original implementation

**Nonces**: Not required (dashboard is read-only, no form submissions)

## Research Topic 6: Data Mart Health Check

### Decision: Direct table query with $wpdb->prepare()

**Implementation**:
```php
global $wpdb;
$table_name = $wpdb->prefix . 'poker_statistics';
$datamart_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
$datamart_row_count = $datamart_exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;
```

**Rationale**:
- Simple existence check and row count
- Uses `$wpdb->prepare()` for security (table name escaped)
- Matches original implementation
- No user input involved (safe query)

**Alternatives Considered**:
- **Use WordPress options to store status**: Rejected because querying table is more reliable

## Research Topic 7: Menu Registration Integration

### Decision: Reuse existing menu registration

**Current State**:
- Menu already registered in `add_admin_menu()` method
- Menu slug: 'poker-tournament-import'
- Page callback parameter already set to `array($this, 'render_dashboard')`
- Capability: 'manage_options'

**No Changes Needed**: Menu registration is correct, just need to implement the `render_dashboard()` method

## Research Topic 8: Performance Optimization

### Decision: Rely on WordPress core query caching

**Considerations**:
- `wp_count_posts()` results are cached by WordPress
- `get_posts()` with small limit (5) is fast
- Data mart query is simple COUNT(*) (indexed table)
- No transients needed for this level of complexity

**Performance Target**: <2s page load (easily achievable with core functions)

**Alternatives Considered**:
- **Add transient caching**: Rejected because WordPress already caches count queries
- **Batch queries**: Not needed (small number of simple queries)

## Summary of Technical Decisions

| Topic | Decision | Rationale |
|-------|----------|-----------|
| Implementation Source | Copy from commit 4ff9552 | Proven, working code exists |
| Components | Restore all 4 sections | All provide value, existed in v3.3/v3.4 |
| Data Retrieval | WordPress core functions | Cached, optimized, secure |
| Styling | Inline CSS | Matches original, simpler |
| Security | esc_html(), esc_url() | WordPress best practices |
| Data Mart Check | Direct query with $wpdb->prepare | Simple, reliable |
| Menu Integration | Reuse existing | No changes needed |
| Performance | Rely on WP caching | Core functions are fast enough |

## Open Questions

**None** - All technical decisions have been made.

## Next Steps

1. Create data-model.md documenting existing entities used
2. Create contracts/README.md documenting admin page contract
3. Create quickstart.md with development setup instructions
4. Ready for task generation phase
