# Release Notes: Poker Tournament Import v3.6.1

**Release Date**: January 4, 2026
**Version**: 3.6.1

## Enhancement: Season Leaderboard Detailed View

### What's New

The `season_leaderboard` shortcode with `show_details="true"` now displays **ALL** registered players in a season, regardless of the `limit` parameter value.

### Behavior Change

**Before (v3.6.0)**:
```php
[season_leaderboard season_id="5" show_details="true" limit="10"]
// Displayed: Top 10 players with detailed statistics
```

**After (v3.6.1)**:
```php
[season_leaderboard season_id="5" show_details="true" limit="10"]
// Displays: ALL players in season with detailed statistics (limit parameter is ignored)
```

### Use Case

League administrators can now see complete season standings with all tie-breaker statistics for:
- End-of-season awards and recognition
- Comprehensive performance analysis
- Full player roster review
- Reporting and documentation

### Details

When `show_details="true"` is enabled, the following columns are displayed for all players:
- Rank
- Player Name
- Points
- Played (tournaments participated)
- Best (best finishing position)
- Average (average finishing position)
- 1st (number of 1st place finishes)
- Top 3 (number of top 3 finishes)
- Top 5 (number of top 5 finishes)
- Bubble (number of bubble finishes)
- Last (number of last place finishes)
- Hits (total hits per formula)

### Standard View Unchanged

The standard view (without `show_details`) continues to respect the `limit` parameter:
- Default: Shows top 20 players
- Custom limit: Shows top N players where N is the limit value

### Breaking Change

⚠️ **Breaking Change**: Shortcode usage of `show_details="true" limit="N"` will now display ALL players instead of just N players.

**Migration**: If you need limited detailed views, use `show_details="false"` with a limit parameter.

### Performance

- Page load time for detailed views with 100+ players: Under 5 seconds
- Transient caching ensures subsequent loads are fast
- HTML rendering handles 200+ row tables efficiently

### Upgrade Instructions

1. Download `poker-tournament-import-v3.6.1.zip`
2. Upload to WordPress plugins directory
3. Deactivate old version
4. Extract and activate v3.6.1
5. Clear WordPress caches (transients, page cache)
6. Test on your season pages

### Rollback

If needed, deactivate v3.6.1 and reactivate v3.6.0.

---

**Full Changelog**: See CHANGELOG.md for complete version history
