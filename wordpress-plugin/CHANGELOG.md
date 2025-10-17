# Poker Tournament Import Changelog

## Version 2.6.2 - (October 17, 2025)

### 🌍 WordPress.org Plugin Directory Preparation
- **FIXED**: All 52 WordPress Plugin Check i18n errors for plugin directory submission
  - Added translator comments for all strings with placeholders (%s, %d)
  - Fixed 3 unordered placeholders (changed %s, %d to %1$s, %2$d)
  - Fixed 1 non-singular string literal (refactored concatenation to use sprintf)
  - Errors across 8 files: class-shortcodes.php (3), class-data-mart-cleaner.php (5), class-admin.php (32), shortcode-helper.php (4), migration-tools.php (7), single-tournament.php (1), single-player.php (2), poker-tournament-import.php (1)

### 📦 Version Updates
- Plugin version: 2.6.1 → 2.6.2
- Updated in poker-tournament-import.php header and POKER_TOURNAMENT_IMPORT_VERSION constant
- Updated readme.txt Stable tag to 2.6.2
- Distribution: poker-tournament-import-v2.6.2.zip

### ✅ Result
- **Ready for WordPress.org submission**: All i18n errors resolved, plugin passes WordPress Plugin Check tool

## Version 2.6.1 - (October 17, 2025)

### 🔧 Critical Menu Race Condition Fix
- **FIXED**: 404 errors on both main "Poker Import" menu and "Formulas" submenu
  - Issue: Formula validator tried to add submenu before parent menu existed
  - Root cause: Both menus hooking to 'admin_menu' at same priority (10) created race condition
  - Impact: Menu registration order was random, causing submenu to fail when running first
  - Solution: Set formula validator hook priority to 11, ensuring parent menu creates first

### 🎨 UI/UX Improvements
- **REMOVED**: Tabbed interface from series and season templates for cleaner display
  - Removed `[series_tabs]` shortcode from taxonomy-tournament_series.php
  - Removed `[season_tabs]` shortcode from taxonomy-tournament_season.php
  - Direct content display instead of tabs for simplified user experience

- **UNIFIED**: Series template gradient styling to match season template
  - Changed series gradient from green (#4CAF50) to blue (#3498db, #2980b9)
  - Consistent visual identity across all tournament series and season pages

### 🏗️ Admin Menu Reorganization
- **MOVED**: Formula Manager from WordPress Settings to Poker Import menu
  - Changed parent from 'options-general.php' to 'poker-tournament-import'
  - Better organization with all poker features under one menu
  - Updated dashboard links from options-general.php to admin.php

- **SIMPLIFIED**: Formula Manager interface
  - Removed 4-tab interface (Formulas/Validator/Settings/Variables)
  - Single-page view showing only formula management
  - Cleaner, more focused editing experience

### 🐛 Debug System Enhancements
- **NEW**: Debug mode toggle in admin settings
  - Checkbox: "Show Statistics Debug Info" in Poker Import Settings
  - Hides technical debug information (database tables, field analysis) by default
  - Option stored as `poker_import_show_debug_stats` in WordPress options

- **ENHANCED**: Conditional PHP error_log statements
  - All dashboard AJAX debug logs wrapped with `get_option('poker_import_debug_logging', 0)` check
  - 15+ error_log statements now conditional based on debug logging setting
  - Cleaner production logs, verbose diagnostics when needed

- **ADDED**: JavaScript debug wrapper in admin.js
  - Global `POKER_DEBUG` flag with `debugLog()` wrapper function
  - 51 console.log/warn/error calls replaced with conditional debugLog()
  - Version verification logging for troubleshooting

### 🔒 Technical Details
- **Formula Validator**: Added admin_menu hook priority 11 (includes/class-formula-validator.php:473)
  - Ensures parent menu exists before submenu registration
  - Eliminates race condition between Admin and Formula Validator constructors

- **Settings Registration**: Added `poker_import_show_debug_stats` boolean option
  - Registered in admin/class-admin.php:137-146
  - UI checkbox in Settings page (admin/class-admin.php:1615-1624)
  - Conditional debug section display (admin/class-admin.php:1716, 1845)

- **Debug Logging**: All dashboard AJAX error_log statements now conditional
  - ajax_load_overview_stats, ajax_load_tournaments_data, ajax_load_players_data
  - ajax_load_series_data, ajax_load_seasons_data, ajax_load_analytics_data
  - Only logs when `poker_import_debug_logging` option enabled

### 📦 Version Updates
- Plugin version: 2.6.0 → 2.6.1
- Updated in poker-tournament-import.php header and POKER_TOURNAMENT_IMPORT_VERSION constant
- Distribution: poker-tournament-import-v2.6.1.zip

### ✅ Result
- **Menu Navigation**: Both main menu and formula submenu work correctly without 404 errors
- **Clean Interface**: Simplified templates and formula manager for better user experience
- **Debug Control**: Technical information hidden by default, available when needed
- **Unified Design**: Consistent blue gradient across all series and season displays

## Version 1.6.2 - (October 12, 2025)

### 📚 Comprehensive Documentation System
- **NEW**: Complete shortcode integration documentation page with step-by-step guides
- **NEW**: Shortcode helper meta boxes for tournaments, series, seasons, and players
- **NEW**: Block Editor, Classic Editor, and HTML Editor integration guides
- **NEW**: Troubleshooting section and advanced usage examples
- **NEW**: Professional responsive styling with navigation and search
- **NEW**: Copy-to-clipboard functionality for shortcode examples
- **ENHANCEMENT**: Complete help system accessible from admin menu

## Version 1.6.1 - (October 12, 2025)

### 🔧 Critical Fixes & Major Features
- **FIXED**: Critical parameter mismatch in series/season taxonomy templates (id → series_id/season_id)
- **NEW**: Complete taxonomy system with tournament_type, tournament_format, tournament_category
- **NEW**: Tournament rankings system with achievement badges (🏆🥈🥉🎯💭⚔️)
- **NEW**: Formula Validation System supporting Tournament Director syntax
- **NEW**: Series Standings Calculator with advanced 7-level tie-breaker logic
- **NEW**: Admin interface for customizable formula management
- **NEW**: CSV export functionality for series standings
- **ENHANCEMENT**: Professional tournament results display with position indicators
- **DATABASE**: Added `hits` column for elimination tracking

## Version 1.6.0 - (October 12, 2025)

### 🎯 MAJOR NEW FEATURES

#### ✅ Complete Tabbed Interface System
- **Tabbed Navigation**: Professional tabbed interface for series and season pages
- **Four Main Tabs**: Overview, Results, Statistics, and Players for comprehensive data display
- **AJAX-Powered**: Dynamic content loading with smooth transitions
- **Mobile Responsive**: Optimized for all screen sizes with touch-friendly navigation

#### 📊 Overview Tab Dashboard
- **Statistics Cards**: Visual display of tournaments, unique players, total/average prize pools
- **Best Player Showcase**: Top performer with avatar, achievements, and detailed stats
- **Recent Tournaments**: Quick view of latest events with winner information
- **Professional Layout**: Grid-based design with hover effects and animations

#### 🏆 Results Tab Implementation
- **Comprehensive Tournament Tables**: Sortable listings with all tournament details
- **Pagination System**: AJAX "Load More" functionality for large datasets
- **Winner Information**: Direct links to player profiles with tournament details
- **Action Buttons**: Quick access to individual tournament pages

#### 📈 Statistics Tab Features
- **Interactive Leaderboards**: Sortable rankings with gold/silver/bronze medals
- **Player Profiles**: Avatar integration with links to detailed player pages
- **Performance Metrics**: Tournaments played, winnings, points, best/average finishes
- **Advanced Sorting**: Click-to-sort functionality on all table columns

#### 👥 Players Tab System
- **Search Functionality**: Real-time player search with visual feedback
- **Player Cards**: Professional grid layout with player statistics
- **Result Counting**: Dynamic search result counters
- **Profile Integration**: Direct links to individual player profile pages

### 🔧 TECHNICAL IMPROVEMENTS

#### Shortcode System
- **Series Shortcodes**: `[series_tabs]`, `[series_overview]`, `[series_results]`, `[series_statistics]`, `[series_players]`
- **Season Shortcodes**: `[season_tabs]`, `[season_overview]`, `[season_results]`, `[season_statistics]`, `[season_players]`
- **Modular Design**: Each tab content generated by dedicated shortcode handlers
- **Parameter Support**: Configurable options for limits, filters, and display preferences

#### Template Integration
- **Updated Series Template**: `taxonomy-tournament_series.php` with tabbed interface
- **New Season Template**: `taxonomy-tournament_season.php` with tabbed interface
- **Clean Header Design**: Professional gradient backgrounds with action buttons
- **Responsive Layout**: Mobile-first design with proper breakpoints

#### JavaScript Enhancements
- **Tab Navigation**: Complete AJAX system with loading states and error handling
- **URL Hash Support**: Direct tab linking and browser history navigation
- **Dynamic Initialization**: Content re-initialization for AJAX-loaded content
- **Search Functionality**: Real-time filtering with visual feedback
- **Table Sorting**: Interactive column sorting for all data tables

#### CSS Styling
- **700+ Lines of Professional CSS**: Comprehensive styling system
- **Mobile Responsive**: Optimized layouts for phones, tablets, and desktops
- **Interactive Elements**: Hover effects, transitions, and micro-animations
- **Accessibility**: Proper contrast ratios and keyboard navigation support
- **Modern Design**: Clean, professional appearance consistent with modern web standards

### 🔒 SECURITY & PERFORMANCE

#### Security Features
- **AJAX Nonce Verification**: All AJAX requests properly secured with WordPress nonces
- **Input Sanitization**: Comprehensive data validation and sanitization
- **Output Escaping**: All user-facing data properly escaped for security
- **Permission Checks**: Proper capability verification for sensitive operations

#### Performance Optimizations
- **Database Efficiency**: Optimized queries with proper indexing
- **Lazy Loading**: Progressive content loading for large datasets
- **Pagination**: AJAX-based pagination to reduce initial load times
- **Caching Ready**: Structure prepared for future caching implementations

### 🐛 BUG FIXES

#### Core Functionality
- **Fixed Data Display Issues**: Resolved problems with series/season pages not showing data
- **Improved Database Queries**: Enhanced query performance and accuracy
- **Template Consistency**: Unified styling and functionality across all templates
- **JavaScript Compatibility**: Resolved conflicts with other WordPress plugins

### 📱 ENHANCED USER EXPERIENCE

#### Navigation Improvements
- **Smooth Transitions**: Professional animations between tab switches
- **Loading Indicators**: Visual feedback during AJAX content loading
- **Error Handling**: Graceful error messages with retry options
- **Breadcrumb Support**: Improved navigation context for users

#### Visual Enhancements
- **Professional Typography**: Consistent font hierarchy and spacing
- **Color System**: Unified color palette with proper contrast ratios
- **Icon Integration**: Meaningful icons for better visual communication
- **Hover States**: Interactive feedback for all clickable elements

### 🏗️ DEVELOPER EXPERIENCE

#### Code Quality
- **Modular Architecture**: Clean separation of concerns across all components
- **Well-Documented**: Comprehensive inline documentation and comments
- **WordPress Standards**: Full compliance with WordPress coding standards
- **Extensible Design**: Easy to extend with additional features and integrations

#### Template System
- **Template Hierarchy**: Proper WordPress template structure
- **Reusable Components**: Modular shortcode system for easy customization
- **CSS Organization**: Well-structured CSS with logical groupings
- **JavaScript Modularity**: Clean, organized JavaScript with clear functions

---

## Version 1.5.0 - Previous Release

### Features
- Basic tournament import functionality
- Tournament Director (.tdt) file support
- Basic tournament display templates
- Player profile system
- Initial database structure

---

## Installation Instructions

1. Download the latest version from [WordPress.org](https://wordpress.org/plugins/poker-tournament-import/)
2. Upload the plugin to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under 'Settings' > 'Poker Tournament Import'

## Upgrade Instructions

1. Backup your WordPress site and database
2. Deactivate the previous version of the plugin
3. Delete the old plugin folder
4. Install the new version following the installation instructions
5. Reactivate the plugin and verify functionality

## Support

- **Documentation**: Visit the [plugin documentation](https://nikielhard.se/tdwpimport)
- **Support Forum**: Post questions on the [WordPress support forum](https://wordpress.org/support/plugin/poker-tournament-import/)
- **Issues**: Report bugs or request features through the [GitHub repository](https://github.com/nikielhard/poker-tournament-import)

---

*Last updated: October 12, 2025*