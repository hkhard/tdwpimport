# Deployment Checklist: v3.6.1

**Release**: Poker Tournament Import v3.6.1
**Feature**: Season Leaderboard - Always Show All Players in Detailed View
**Date**: January 4, 2026

## Pre-Deployment Checklist

### Development Environment
- [x] Code changes complete (3-line modification in class-shortcodes.php)
- [x] Version updated to 3.6.1
- [x] PHP syntax validation passed
- [x] Distribution ZIP created

### Testing Checklist (Manual Testing Required)
- [ ] Standard view: `[season_leaderboard season_id="X"]` → Shows top 20
- [ ] Standard view with limit: `[season_leaderboard season_id="X" limit="10"]` → Shows top 10
- [ ] Detailed view: `[season_leaderboard season_id="X" show_details="true"]` → Shows ALL players
- [ ] Detailed view with limit (THE FIX): `[season_leaderboard season_id="X" show_details="true" limit="10"]` → Shows ALL players (limit ignored)
- [ ] Empty season: `[season_leaderboard season_id="999" show_details="true"]` → "No players found"
- [ ] Performance test with 100+ player season → Loads in <5 seconds

## Deployment Steps

### 1. Backup Current Installation
```bash
# Via SSH or file manager
cp -r wp-content/plugins/poker-tournament-import wp-content/plugins/poker-tournament-import-backup
```

### 2. Upload New Version
- [ ] Upload `poker-tournament-import-v3.6.1.zip` to server
- [ ] Extract to `wp-content/plugins/poker-tournament-import/`
- [ ] Verify files extracted correctly

### 3. Activate Plugin
- [ ] Log in to WordPress admin
- [ ] Go to Plugins → Installed Plugins
- [ ] Deactivate "Poker Tournament Import" (if currently active)
- [ ] Activate "Poker Tournament Import v3.6.1"

### 4. Clear Caches
```bash
# Via SSH or WP-CLI
wp cache flush --allow-root
wp transient delete --allow-root
```

Or via WordPress admin:
- [ ] Clear page cache (if using WP Super Cache, W3 Total Cache, etc.)
- [ ] Clear object cache
- [ ] Clear browser cache (Ctrl+Shift+R / Cmd+Shift+R)

### 5. Verify Deployment
- [ ] Load a season page with `[season_leaderboard show_details="true"]`
- [ ] Verify ALL players in season are displayed
- [ ] Verify detail columns are present (Played, Best, Avg, etc.)
- [ ] Check browser console for JavaScript errors
- [ ] Test on multiple seasons if available

### 6. Regression Testing
- [ ] Standard view still shows top 20
- [ ] Custom limits still work on standard view
- [ ] Other shortcodes still work (tournament_list, player_profiles, etc.)
- [ ] Tournament import still works
- [ ] Tournament creation still works

## Rollback Plan

### If Issues Occur

**Option 1: Quick Rollback via WordPress Admin**
1. Go to Plugins → Installed Plugins
2. Deactivate "Poker Tournament Import v3.6.1"
3. Reactivate previous version (v3.6.0 or earlier)
4. Clear caches

**Option 2: Manual Rollback via SSH**
```bash
# Restore backup
cd wp-content/plugins
rm -rf poker-tournament-import
mv poker-tournament-import-backup poker-tournament-import

# Or re-extract old version
unzip poker-tournament-import-v3.6.0.zip -d poker-tournament-import
```

**Option 3: Code-Only Rollback**
If only the 3-line change needs reverting:
1. Edit `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php`
2. Find line 2443
3. Change from: `if ($show_details) {`
4. Change back to: `if ($show_details && !isset($atts['limit'])) {`
5. Update comment back to: "// If showing details, show all players unless explicitly limited"
6. Save and clear caches

## Post-Deployment Monitoring

### Performance Monitoring
- [ ] Monitor page load times for detailed views
- [ ] Check server resource usage during peak traffic
- [ ] Verify transient cache is working properly

### User Feedback
- [ ] Monitor user reports/complaints
- [ ] Check if detailed views meet user needs
- [ ] Gather feedback on performance with large seasons

## Known Issues & Limitations

### Behavior Changes
- `show_details="true" limit="N"` now ignores the limit parameter
- This is intentional per user requirement
- Documented in release notes

### Performance
- Large seasons (200+ players) may take 3-5 seconds to load
- Subsequent loads use transient cache (much faster)
- Recommend using standard view (show_details="false") for faster page loads

## Support Contacts

**Documentation**: See README.md and plugin help pages
**Issues**: Report via WordPress plugin support forum or GitHub issues

## Sign-Off

**Deployed by**: _________________
**Date**: _________________
**Tested on**: Production/Staging URL: _________________
**Notes**: _________________
