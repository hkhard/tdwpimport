=== Poker Tournament Import ===
Contributors: yourname
Tags: poker, tournament, import, results
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and display poker tournament results from Tournament Director (.tdt) files.

== Description ==

Poker Tournament Import is a WordPress plugin that allows you to import poker tournament results from Tournament Director software (.tdt files) and display them on your website.

Features:
* Import .tdt files from Tournament Director software
* Automatic player creation and management
* Tournament series and season tracking
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
* `[tournament_results id="123"]` - Display specific tournament results
* `[tournament_series id="456"]` - Show series overview
* `[player_profile name="John Doe"]` - Display player profile

== Screenshots ==

1. Import interface for .tdt files
2. Tournament results display
3. Player statistics page

== Changelog ==

= 1.0.0 =
* Initial release
* Basic .tdt file parsing
* Custom post types for tournaments, series, seasons, and players
* Admin interface for importing tournaments
* Shortcode support for displaying results

== Upgrade Notice ==

= 1.0.0 =
Initial release - no upgrade required.