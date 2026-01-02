# API Contracts: Dashboard Restoration

**Feature**: 006-restore-dashboard
**Date**: 2025-01-02
**Status**: ✅ No New APIs

## Overview

This feature restores the WordPress admin dashboard overview page. **No new REST or GraphQL APIs are created** - the dashboard is a server-rendered WordPress admin page.

---

## WordPress Admin Page Contract

### Page: Poker Dashboard

**Type**: WordPress Admin Page (server-rendered)
**Menu Slug**: `poker-tournament-import`
**Capability**: `manage_options`
**Page Callback**: `Poker_Tournament_Admin::render_dashboard()`

---

### Input/Output Contract

#### Inputs

**HTTP GET Request**:
```
URL: /wp-admin/admin.php?page=poker-tournament-import
Method: GET
Parameters: None (page accessed via WordPress admin menu)
```

**Preconditions**:
- User must be logged into WordPress
- User must have `manage_options` capability
- Plugin must be activated
- `Poker_Tournament_Admin` class must be instantiated

---

#### Outputs

**Response**: HTML Page (server-rendered WordPress admin page)

**Content Sections**:

##### 1. Stat Cards Grid (4 cards)

```html
<div class="poker-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
  <div class="poker-stat-card">
    <!-- Tournaments Card -->
    <h3>Tournaments</h3>
    <div>{count}</div>
    <a href="edit.php?post_type=tournament">View All</a>
  </div>
  <!-- Players, Seasons, Formulas cards similar -->
</div>
```

**Data**:
- `Tournaments count`: Integer (sum of publish + draft + private)
- `Players count`: Integer
- `Seasons count`: Integer
- `Formulas count`: Integer

---

##### 2. Data Mart Health Section

```html
<div class="poker-datamart-health">
  <h2>Data Mart Health</h2>
  <table>
    <tr>
      <td>Status</td>
      <td>{Active|Not Created}</td>
    </tr>
    <tr>
      <td>Records</td>
      <td>{count}</td>
    </tr>
    <tr>
      <td>Last Refresh</td>
      <td>{timestamp|"Never"}</td>
    </tr>
  </table>
  <a href="admin.php?page=poker-tournament-import-settings">Refresh Statistics</a>
</div>
```

**Data**:
- `Status`: String ("Active" or "Not Created")
- `Records`: Integer (row count from `wp_poker_statistics`)
- `Last Refresh`: String (formatted date/time or "Never")

---

##### 3. Quick Actions Section

```html
<div class="poker-quick-actions">
  <h2>Quick Actions</h2>
  <a href="admin.php?page=poker-tournament-import-import" class="button button-primary">
    Import Tournament
  </a>
  <a href="edit.php?post_type=tournament" class="button">
    View Tournaments
  </a>
  <a href="edit.php?post_type=player" class="button">
    View Players
  </a>
  <a href="admin.php?page=poker-formula-manager" class="button">
    Manage Formulas
  </a>
</div>
```

**Data**: Static links (no dynamic data)

---

##### 4. Recent Activity Table

```html
<div class="poker-recent-activity">
  <h2>Recent Activity</h2>
  <table class="widefat striped">
    <thead>
      <tr>
        <th>Tournament</th>
        <th>Date Imported</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {foreach tournament in recent_tournaments}
      <tr>
        <td><a href="post.php?post={id}&action=edit">{title}</a></td>
        <td>{date_imported}</td>
        <td>{status}</td>
        <td>
          <a href="post.php?post={id}&action=edit">Edit</a> |
          <a href="{permalink}">View</a> |
          <a href="{trash_url}">Trash</a>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>
</div>
```

**Data** (array of 5 tournament objects):
- `title`: String (post_title)
- `date_imported`: String (formatted post_date)
- `status`: String (Published/Draft/Private)
- `id`: Integer (post ID for links)
- `permalink`: String (post permalink)
- `trash_url`: String (trash action URL)

---

### Security Contract

**Access Control**:
- Capability Check: `current_user_can('manage_options')` (handled by WordPress menu registration)
- Authentication: WordPress session (user must be logged in)
- Nonces: Not required (read-only page, no form submissions)

**Output Escaping**:
- All text output: `esc_html()` or `esc_html__()`
- All URLs: `esc_url()`
- All attributes: `esc_attr()`
- Numbers: `number_format()` then `esc_html()`

**Database Queries**:
- Use WordPress core functions: `wp_count_posts()`, `get_posts()`
- Direct query for data mart: Use `$wpdb->prepare()` or properly escaped table name
- No user input in SQL queries (safe)

---

### Performance Contract

**Page Load Target**: <2 seconds on typical WordPress installations

**Query Optimization**:
- `wp_count_posts()` - cached by WordPress
- `get_posts()` with `posts_per_page=5` - small result set
- Data mart COUNT(*) query - simple indexed query
- Total queries: ~8 (4 counts + 1 formulas + 1 data mart + 1 recent + 1 options)

**Caching Strategy**:
- Rely on WordPress object cache for `wp_count_posts()`
- No transients needed (queries are fast and WordPress caches them)
- Page is not heavily trafficked (admin dashboard only)

---

### Error Handling

**No Explicit Error Handling Required**:
- WordPress handles unauthorized access (redirects to login)
- WordPress handles missing capabilities (shows "Sorry, you are not allowed...")
- Missing data: Show "0" for counts, hide Recent Activity if no tournaments

**Edge Cases**:
- No tournaments: Show "0" in Tournaments card, hide Recent Activity section
- No players: Show "0" in Players card
- No seasons: Show "0" in Seasons card
- No formulas: Show "0" in Formulas card
- Data mart table missing: Show "Not Created" status, "0" records
- Data mart empty: Show "Active" status, "0" records

---

## Summary

**Contract Type**: WordPress Admin Page (server-rendered)
**Authentication**: WordPress session + capability check
**Input**: None (GET request to page URL)
**Output**: HTML page with 4 sections
**Security**: Escaping required, nonces not required
**Performance**: <2s target, rely on WordPress caching
**Errors**: Handled by WordPress core, graceful degradation

---

## Notes

- This is **not** a REST API endpoint
- This is **not** a GraphQL query
- This is a standard WordPress admin page callback
- All data retrieval happens server-side during page render
- No AJAX calls are made
- No client-side JavaScript required (pure PHP rendering)
