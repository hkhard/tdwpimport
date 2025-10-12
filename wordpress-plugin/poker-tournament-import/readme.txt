=== Poker Tournament Import ===
Contributors: yourname
Tags: poker, tournament, import, results
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.6.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and display poker tournament results from Tournament Director (.tdt) files.

== Description ==

Poker Tournament Import is a WordPress plugin that allows you to import poker tournament results from Tournament Director software (.tdt files) and display them on your website.

Features:
* Import .tdt files from Tournament Director software
* Automatic player creation and management
* Tournament series and season tracking with **tabbed interface**
* **NEW: Professional tabbed interface** for series and season pages
* **NEW: Interactive statistics dashboard** with visual cards
* **NEW: Comprehensive leaderboards** with sortable rankings
* **NEW: Real-time player search** and filtering
* **NEW: AJAX-powered content loading** with smooth transitions
* Responsive tournament results display
* Player statistics and profiles
* Shortcode support for easy integration

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/poker-tournament-import` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Poker Import menu to import your first tournament

== Frequently Asked Questions ==

= What file formats are supported? =

The plugin currently supports Tournament Director (.tdt) files from version 3.7.2 and later.

= Can I import multiple tournaments at once? =

Currently, the plugin supports single file imports. Batch import functionality is planned for future versions.

= How do I display tournament results on my pages? =

Use the following shortcodes:

**Tournament Display:**
* `[tournament_results id="123"]` - Display specific tournament results
* `[tournament_series id="456"]` - Show series overview
* `[player_profile name="John Doe"]` - Display player profile

**NEW: Tabbed Interface (v1.6.0+):**
* `[series_tabs id="123"]` - Complete tabbed interface for series
* `[series_overview id="123"]` - Series overview with statistics
* `[series_results id="123"]` - Series tournament results
* `[series_statistics id="123"]` - Series leaderboard and statistics
* `[series_players id="123"]` - Series player directory with search
* `[season_tabs id="456"]` - Complete tabbed interface for seasons
* `[season_overview id="456"]` - Season overview with statistics
* `[season_results id="456"]` - Season tournament results
* `[season_statistics id="456"]` - Season leaderboard and statistics
* `[season_players id="456"]` - Season player directory with search

== Screenshots ==

1. Import interface for .tdt files
2. Tournament results display
3. Player statistics page
4. **NEW: Tabbed interface for series pages**
5. **NEW: Statistics dashboard with visual cards**
6. **NEW: Interactive leaderboard with sorting**

== Changelog ==

= 1.6.0 - October 12, 2025 =
🎯 **MAJOR RELEASE: Complete Tabbed Interface System**
✅ **NEW: Professional tabbed interface** for series and season pages
✅ **NEW: Interactive statistics dashboard** with visual cards
✅ **NEW: Comprehensive leaderboards** with sortable rankings and medals
✅ **NEW: Real-time player search** with visual feedback
✅ **NEW: AJAX-powered content loading** with smooth transitions
✅ **NEW: Mobile responsive design** optimized for all devices
✅ **11 new shortcodes** for complete customization
✅ **Enhanced security** with nonce verification for all AJAX requests
✅ **SOLVED: Fixed empty series/season pages** - now display rich data
✅ **700+ lines of professional CSS** styling
✅ **Complete backward compatibility** - seamless upgrade from v1.5.0

= 1.0.0 =
* Initial release
* Basic .tdt file parsing
* Custom post types for tournaments, series, seasons, and players
* Admin interface for importing tournaments
* Shortcode support for displaying results

== Upgrade Notice ==

= 1.6.0 =
**Major feature release with complete tabbed interface.** Fully backward compatible - seamless upgrade recommended for all users.

= 1.0.0 =
Initial release - no upgrade required.