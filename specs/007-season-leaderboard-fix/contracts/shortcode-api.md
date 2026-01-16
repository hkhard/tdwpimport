# Shortcode API Contract: season_leaderboard

**Feature**: 007-season-leaderboard-fix
**Type**: WordPress Shortcode (Server-Side Rendered)
**Version**: 2.0 (with formula fix + stats enhancement)

## Shortcode Signature

```php
[season_leaderboard formula="string" show_details="bool" show_export="bool" limit="int"]
[tdwp_season_leaderboard formula="string" show_details="bool" show_export="bool" limit="int"]
```

## Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `formula` | string | `tdwp_active_season_formula` option | Formula key to use for points calculation (e.g., "season_total", "best_8", "drop_2") |
| `show_details` | boolean | `false` | Show detailed statistics columns (Bubble, Last, Hits, tie-breakers) |
| `show_export` | boolean | `true` | Show Export CSV and Print buttons |
| `limit` | int | `20` | Maximum number of players to display |

## Output Structure

### HTML Table Structure

```html
<div class="poker-season-leaderboard" data-season-id="{season_id}">
    <div class="leaderboard-header">
        <h2>{Season Title} - Leaderboard</h2>
        <div class="leaderboard-actions">
            <button class="button poker-export-csv">Export CSV</button>
            <button class="button poker-print-standings">Print</button>
        </div>
    </div>

    <table class="widefat poker-standings-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Player</th>
                <th>Points</th>  <!-- Formula-calculated (FIXED in v2.0) -->
                <!-- Conditional columns when show_details="true": -->
                <th>Played</th>
                <th>Best</th>
                <th>Avg</th>
                <th>1st</th>
                <th>Top 3</th>
                <th>Top 5</th>
                <th>Bubble</th>  <!-- NEW in v2.0 -->
                <th>Last</th>    <!-- NEW in v2.0 -->
                <th>Hits</th>    <!-- NEW in v2.0 -->
            </tr>
        </thead>
        <tbody>
            <!-- One row per player -->
        </tbody>
    </table>
</div>
```

### Data Contract: Player Row

Each table row displays data from the player standings array:

| Column | Data Source | Type | Format | Notes |
|--------|-------------|------|--------|-------|
| Rank | Calculated | string | "{rank}{medal}" | Ties shown as "T{rank}", medals 🥇🥈🥉 for top 3 |
| Player | `player_name`, `player_url` | HTML | `<a href="...">Name</a>` or text | Linked if player URL exists |
| Points | `series_points` | float | "1.0" decimal places | **FIXED**: Now shows formula-calculated value |
| Played | `tournaments_played` | int | Whole number | Shown when show_details="true" |
| Best | `best_finish` | int | Whole number | Lowest finish position |
| Avg | `avg_finish` | float | "1.0" decimal places | Average finishing position |
| 1st | `tie_breakers.first_places` | int | Whole number | Count of 1st place finishes |
| Top 3 | `tie_breakers.top3_finishes` | int | Whole number | Count of top 3 finishes |
| Top 5 | `tie_breakers.top5_finishes` | int | Whole number | Count of top 5 finishes |
| Bubble | `bubble_count` | int | Whole number | **NEW**: Count of bubble finishes |
| Last | `last_place_count` | int | Whole number | **NEW**: Count of last place finishes |
| Hits | `total_hits` | int | Whole number | **NEW**: Total hits across season |

### Edge Cases

#### No Season Selected

```html
<div class="poker-leaderboard-no-season">
    <p>No season selected. Please select a season from the filter controls above...</p>
</div>
```

#### No Tournaments in Season

```html
<div class="poker-leaderboard-no-data">
    <p>No tournaments or standings data found for season: {Season Name}</p>
</div>
```

## Version Changes

### v2.0 (Current - Feature 007)

**Bug Fixes**:
- Points column now displays `series_points` instead of `total_points`
- Correctly applies season formula to points display

**New Features**:
- Added `bubble_count` column when `show_details="true"`
- Added `last_place_count` column when `show_details="true"`
- Added `total_hits` column when `show_details="true"`

**Breaking Changes**:
- None - backward compatible with existing shortcodes

**Cache Changes**:
- Cache key versioned to `_v2` to prevent old cache issues
- Old cache expires naturally (1 hour TTL)

### v1.0 (Previous)

**Features**:
- Display season standings with ranking
- Support for formula override
- Optional detailed statistics (tie-breakers)
- Export to CSV functionality
- Print functionality

## CSS Classes

### Container Classes

- `.poker-season-leaderboard` - Main container
- `.leaderboard-header` - Header section
- `.leaderboard-actions` - Action buttons area
- `.poker-standings-table` - Table element
- `.poker-leaderboard-no-season` - No season message
- `.poker-leaderboard-no-data` - No data message

### Button Classes

- `.poker-export-csv` - Export CSV button
- `.poker-print-standings` - Print button

### Interactive Classes

- `.button` - WordPress button base class

## JavaScript Hooks

### Data Attributes

- `data-season-id="{id}"` - Season post ID on container
- `data-formula="{key}"` - Formula key on export button

### Event Handlers

**Export CSV**:
```javascript
document.querySelector('.poker-export-csv').addEventListener('click', function() {
    const seasonId = this.dataset.seasonId;
    const formula = this.dataset.formula;
    // Trigger AJAX export
});
```

**Print**:
```javascript
document.querySelector('.poker-print-standings').addEventListener('click', function() {
    window.print();
});
```

## Response Format

### Success

Returns HTML string (rendered shortcode output)

### Error States

- No season selected: HTML warning message
- Invalid season: HTML error message
- No data: HTML info message

## Dependencies

**Required**:
- `Poker_Dashboard_Filters` class (for active season)
- `Poker_Series_Standings_Calculator` class (for standings calculation)
- WordPress options: `tdwp_active_season_formula`

**Optional**:
- None (all parameters have defaults)

## Security

**Input Sanitization**:
- `formula`: `sanitize_text_field()`
- `show_details`: `filter_var($val, FILTER_VALIDATE_BOOLEAN)`
- `show_export`: `filter_var($val, FILTER_VALIDATE_BOOLEAN)`
- `limit`: `intval()`

**Output Escaping**:
- All text: `esc_html()`
- URLs: `esc_url()`
- Attributes: `esc_attr()`

**Nonce Verification**: N/A (shortcode is public display, not form processing)

## Performance

**Caching**:
- Results cached in WordPress transients (1 hour TTL)
- Cache key: `poker_season_standings_{season_id}_{formula_key}_v2`

**Expected Performance**:
- Cache hit: <100ms render time
- Cache miss: <2s for 50 players × 20 tournaments
- Database queries: 1 initial + N tournaments for max positions (N = tournaments in season)

## Examples

### Basic Usage

```php
// Use active season formula, default settings
[season_leaderboard]
```

### Show Details

```php
// Show detailed statistics including bubble/last/hits
[season_leaderboard show_details="true"]
```

### Formula Override

```php
// Use specific formula instead of active season default
[season_leaderboard formula="season_total"]
```

### Custom Limit

```php
// Show top 10 players only
[season_leaderboard limit="10"]
```

### Combined Parameters

```php
// Full customization
[season_leaderboard formula="best_8" show_details="true" limit="15"]
```

## Testing

### Manual Testing

1. **Display Test**: Render shortcode on a page
2. **Formula Test**: Change active formula, verify points update
3. **Details Test**: Toggle show_details, verify columns appear/hide
4. **Export Test**: Click Export CSV, verify file includes new columns
5. **Print Test**: Click Print, verify formatting

### Automated Testing

No automated tests currently implemented for shortcodes.

## Migration Guide

### From v1.0 to v2.0

**No changes required** - existing shortcodes continue to work.

**Optional enhancements**:
- Add `show_details="true"` to see new statistics columns
- Verify points display matches expected formula calculation

**Cache clearing**:
- Old cache will expire naturally (1 hour)
- Manual clear: `wp transient delete poker_season_standings_* --allow-root`
