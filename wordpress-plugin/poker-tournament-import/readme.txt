=== Poker Tournament Import ===
Contributors: yourname
Tags: poker, tournament, import, results
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 2.3.25
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

= 2.3.25 - October 15, 2025 =
🎨 **UI/UX Improvements - Tournament Template Legibility Fixes**
✅ **FIXED: Elimination info visibility** - Added proper styling for elimination details (now displays in readable gray color)
✅ **FIXED: Tournament Details heading** - Changed from invisible dark gray to high-contrast dashboard dark (#23282d)
✅ **FIXED: Official Results heading** - Updated to use dashboard dark color with blue accent border
✅ **FIXED: Tournament title visibility** - Changed from light gray to dashboard dark for proper contrast on white background
✅ **ENHANCED: Consistent styling** - All text elements now use unified dashboard color palette for professional appearance

= 2.3.24 - October 15, 2025 =
🎨 **UI/UX Improvements - Color Unification**
✅ **UNIFIED: Dashboard color scheme** - All tournament displays now use consistent dashboard colors
✅ **IMPROVED: Prize money display** - Changed to dashboard green (#28a745) for consistency
✅ **IMPROVED: Points display** - Changed to dashboard blue (#0073aa) matching admin interface
✅ **IMPROVED: Position numbers** - Updated to dashboard blue for better visual hierarchy
✅ **ENHANCED: Player statistics** - Unified color palette across all player data displays
✅ **RESULT: Professional appearance** - Consistent styling between admin dashboard and frontend displays

= 2.3.23 - October 15, 2025 =
🎨 **UI/UX Improvements - Tournament Display**
✅ **REMOVED: Processing notice** - Removed unnecessary "Using Enhanced Chronological Processing" notice for cleaner display
✅ **IMPROVED: Table header visibility** - Changed from dark gradient to light background with better contrast
✅ **ENHANCED: Print styles** - Added comprehensive print media queries for better PDF/print output quality
✅ **OPTIMIZED: Table cell visibility** - All table text now uses high-contrast colors for maximum readability

= 2.3.22 - October 15, 2025 =
🔧 **Print/PDF Output Improvements & Bug Fixes**
✅ **FIXED: Player display limit** - Now shows all tournament players (removed artificial 8-player limit)
✅ **FIXED: Print readability** - Improved color contrast for player avatars and names
✅ **NEW: Print-optimized CSS** - Added comprehensive print media queries for better PDF output
✅ **FIXED: PHP 8.1+ compatibility** - Resolved additional float-to-int deprecation warning
✅ **Enhanced visual contrast** - Darker colors for better on-screen and print readability

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

= 2.3.25 =
**Legibility fixes for tournament templates.** Resolves multiple text visibility issues including elimination info, headings, and tournament title display. Recommended for all users.

= 2.3.24 =
**UI consistency update.** Unifies tournament display colors with dashboard design system for professional, consistent appearance. Recommended for all users.

= 2.3.22 =
**Important bug fix release.** Resolves player display limitations, improves print/PDF output quality, and ensures PHP 8.1+ compatibility. Recommended for all users.

= 1.6.0 =
**Major feature release with complete tabbed interface.** Fully backward compatible - seamless upgrade recommended for all users.

= 1.0.0 =
Initial release - no upgrade required.