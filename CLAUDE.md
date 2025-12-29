# CLAUDE.md

Claude Code guidance for the Poker Tournament Import WordPress plugin.

**Constitution**: See `.specify/memory/constitution.md` for core principles on code quality, testing, UX consistency, performance, and security requirements that govern all development.

## Project Status

**Current Version**: 2.4.2 (Production)
**Stage**: Active Development & Maintenance
**Goal**: WordPress plugin for importing/displaying poker tournament results from Tournament Director (.tdt) files

## Technology Stack

- **Platform**: WordPress 6.0+ Plugin
- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript

## Core Architecture

### Custom Post Types
- `tournament`: Individual tournament results
- `tournament_series`: Series of related tournaments
- `player`: Player profiles and statistics

### Key Classes (wordpress-plugin/poker-tournament-import/)
- **poker-tournament-import.php**: Main plugin entry point
- **includes/class-parser.php**: .tdt file parser
- **includes/class-post-types.php**: Custom post types registration
- **includes/class-taxonomies.php**: Custom taxonomies
- **includes/class-shortcodes.php**: Frontend shortcodes
- **includes/class-statistics-engine.php**: Statistics calculations
- **includes/class-formula-validator.php**: Formula validation engine
- **admin/class-admin.php**: Admin interface
- **admin/class-data-mart-cleaner.php**: Data maintenance tools

### Database Tables (wp_ prefix)
- `poker_tournament_players`: Tournament participation records
- `poker_statistics`: Dashboard statistics (data mart)
- `poker_tournament_costs`: Tournament cost tracking
- `poker_financial_summary`: Financial analytics
- `poker_player_roi`: Player ROI metrics
- `poker_revenue_analytics`: Monthly revenue aggregation

## Development Standards

### Security Requirements
- Secure file upload handling with .tdt validation
- Data sanitization: `sanitize_text_field()`, `wp_kses_post()`
- Nonce verification for AJAX handlers
- Role-based access: `current_user_can('manage_options')`
- Prepared statements for database operations

### Code Conventions
- **Never use underscore prefixes for our own variables** (WordPress uses _ for internal)
- PHP 8.2+ compatibility (declare dynamic properties)
- WordPress Coding Standards
- Proper internationalization (`__()`, `_e()`, text domain: 'poker-tournament-import')

### Performance Practices
- Transient caching for computed statistics
- Proper database indexing
- Memory-efficient parsing for large .tdt files
- Asynchronous statistics refresh using `wp_schedule_single_event()`

## Common Development Tasks

### Adding New Features
1. Update version in `poker-tournament-import.php` header
2. Implement feature in appropriate class
3. Add AJAX handlers if needed (register in main init())
4. Update admin interface if required
5. **Create new .zip file**: `wordpress-plugin/poker-tournament-import-vX.X.X.zip`

### Formula System
- Formulas stored in WordPress options (`poker_formulas_*`)
- Validator class: `Poker_Tournament_Formula_Validator`
- Supports variables: n, r, hits, monies, avgBC, T33, T80
- Real-time validation via AJAX

### Statistics Engine
- Auto-refreshes on plugin update (checks version change)
- Manual refresh: Admin dashboard "Refresh Statistics" button
- Async refresh on tournament save/delete/restore
- Data mart tables for performance

### Release Process
1. Update version constant: `POKER_TOURNAMENT_IMPORT_VERSION`
2. Update plugin header version
3. Test changes thoroughly
4. Create distribution zip file
5. Version format: `poker-tournament-import-vX.X.X.zip`

## Key File Locations

### Core Files
- Main: `wordpress-plugin/poker-tournament-import/poker-tournament-import.php:6`
- Parser: `wordpress-plugin/poker-tournament-import/includes/class-parser.php`
- Admin: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`

### Templates
- Tournament: `wordpress-plugin/poker-tournament-import/templates/single-tournament.php`
- Player: `wordpress-plugin/poker-tournament-import/templates/single-player.php`

### Assets
- Frontend CSS: `wordpress-plugin/poker-tournament-import/assets/css/frontend.css`
- Admin CSS: `wordpress-plugin/poker-tournament-import/assets/css/admin.css`
- Frontend JS: `wordpress-plugin/poker-tournament-import/assets/js/frontend.js`

## Plan Mode Guidance
- Use pseudocode for implementation planning
- Break complex tasks into clear steps
- Focus on WordPress best practices
- always create updated installer zip file with new versions
- the wp shell is  at /Users/hkh/Library/Application\ Support/Local/ssh-entry/hNPsf2SE_.sh
- remember to update version number in the log file output so we know we are logging for the right patch level
- use php to verify syntax on all changes

## Active Technologies
- PHP 8.0+ (8.2+ compatible) + WordPress 6.0+, MySQL 5.7+, jQuery, modern JavaScript (ES6+) (001-td3-integration)
- MySQL with WordPress custom tables (12 existing tables), WordPress media library, transient caching (001-td3-integration)
- TypeScript 5.0+ (strict mode), Node.js 20+ for controller (002-expo-rewrite)
- TypeScript 5.0+ (strict mode) + Expo SDK 50+, React Navigation v6+, Zustand (state), React Native (UI) (001-blind-level-management)
- SQLite (controller with tdwp_ prefix), Expo SQLite (mobile), AsyncStorage (offline cache) (001-blind-level-management)
- TypeScript 5.0+ (strict mode) + Expo SDK 54, React Navigation v6, Zustand 4.4, Fastify (controller) (002-blind-level-crud)
- AsyncStorage (offline cache), SQLite (controller database with tdwp_ prefix) (002-blind-level-crud)
- PHP 8.0+ (8.2+ compatible) + WordPress 6.0+, WordPress Coding Standards (003-fix-tournament-import)
- WordPress MySQL database (wp_ prefix) (003-fix-tournament-import)

## Recent Changes
- 001-td3-integration: Added PHP 8.0+ (8.2+ compatible) + WordPress 6.0+, MySQL 5.7+, jQuery, modern JavaScript (ES6+)
- our database prefix is tdwp_ always, nothing else
