# Project Overview - Poker Tournament Import Plugin

## Purpose
WordPress plugin for importing/displaying poker tournament results from Tournament Director (.tdt) files. Automates tournament data entry reducing manual work by 90% while maintaining 100% accuracy.

## Tech Stack
- **Platform**: WordPress 6.0+ Plugin
- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript (vanilla)
- **No build tools**: Direct PHP/JS/CSS development

## Key Features
- Import .tdt files from Tournament Director v3.7.2+
- Custom post types: tournament, tournament_series, player
- Statistics calculation engine with formula validator
- Dashboard with analytics
- Shortcode system for frontend display
- Series/season tracking and leaderboards

## Version
Current: 2.5.8 (defined in POKER_TOURNAMENT_IMPORT_VERSION constant)

## Main Entry Point
`wordpress-plugin/poker-tournament-import/poker-tournament-import.php` - Main plugin file using singleton pattern
