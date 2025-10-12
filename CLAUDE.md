# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **WordPress plugin project** for importing and displaying poker tournament results from Tournament Director (.tdt) files. The project is currently in the **planning/documentation phase** with a comprehensive Product Requirements Document (PRD) completed.

**Project Goal**: Create a WordPress plugin that automates tournament results publishing, reducing manual data entry time by 90% while maintaining 100% data accuracy.

## Current Development Status

**Stage**: Pre-Development / Requirements Complete
- No source code implemented yet
- Comprehensive PRD finalized (Poker_Tournament_Results_Import_Plugin_PRD.md)
- Ready for development implementation following 5-phase plan

## Technology Stack

- **Platform**: WordPress Plugin
- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript (WordPress compatible)
- **File Format**: Tournament Director (.tdt) file parsing
- **Target**: WordPress 6.0+ compatibility

## Development Environment Setup

### WordPress Development Environment
```bash
# Local WordPress development (choose one approach)
1. Use Local by Flywheel (recommended for WordPress development)
2. Docker WordPress setup
3. Vagrant WordPress environment
4. XAMPP/MAMP with WordPress installation

# Plugin development location
wp-content/plugins/poker-tournament-import/
```

### Essential WordPress Development Tools
```bash
# WordPress CLI (essential for plugin development)
wp plugin activate poker-tournament-import
wp post-type list  # Verify custom post types
wp taxonomy list   # Verify custom taxonomies
```

## Core Architecture (From PRD)

### Custom Post Types
- `tournament`: Individual tournament results
- `tournament_series`: Series of related tournaments
- `tournament_season`: Season/year grouping
- `player`: Player profiles and statistics

### Taxonomies
- `tournament_type`: Hold'em, Omaha, Stud, etc.
- `tournament_format`: No Limit, Pot Limit, Fixed Limit
- `tournament_category`: Live, Online, Charity, etc.

### Key Functional Components
1. **.tdt File Parser**: Tournament Director file format parsing
2. **Data Processing Layer**: Tournament metadata and player results extraction
3. **WordPress Integration**: Custom post types and database operations
4. **Display System**: Responsive templates and shortcodes
5. **Admin Interface**: Import wizard and series management

## Development Phases

### Phase 1: Core Parsing Engine (4 weeks)
- .tdt file format analysis and parser development
- Data validation and error handling
- Basic tournament data extraction

### Phase 2: WordPress Integration (3 weeks)
- Custom post types and taxonomies
- Database schema implementation
- WordPress hooks and filters

### Phase 3: Admin Interface (3 weeks)
- Import wizard with drag-and-drop
- Series/season management dashboard
- Settings and configuration

### Phase 4: Display Templates (2 weeks)
- Responsive tournament results pages
- Shortcode system implementation
- Series overview pages

### Phase 5: Testing & Polish (2 weeks)
- Comprehensive testing suite
- Documentation completion
- Performance optimization

## Critical Development Requirements

### Security Requirements
- Secure file upload handling with validation
- Data sanitization using WordPress functions (`wp_kses_post()`, `sanitize_text_field()`)
- Role-based access control using WordPress capabilities
- GDPR compliance for personal data handling
- Prepared statements for all database operations

### Performance Targets
- Support 10,000+ tournaments per site
- Memory-efficient file parsing for large .tdt files
- Database query optimization with proper indexing
- Caching for frequently accessed tournament data

### WordPress Standards Compliance
- WordPress Coding Standards (PHPCS)
- Proper internationalization (`wp_i18n`)
- WordPress plugin header and metadata
- Activation/deactivation hooks
- Uninstall cleanup procedures

## File Structure (To Be Created)

```
poker-tournament-import/
├── poker-tournament-import.php          # Main plugin file
├── includes/
│   ├── class-parser.php                 # .tdt file parser
│   ├── class-post-types.php             # Custom post types
│   ├── class-taxonomies.php             # Custom taxonomies
│   ├── class-admin.php                  # Admin interface
│   ├── class-shortcodes.php             # Shortcode system
│   └── class-templates.php              # Display templates
├── admin/
│   ├── import-wizard.php                # File import interface
│   ├── series-management.php            # Series dashboard
│   └── settings.php                     # Plugin settings
├── templates/
│   ├── tournament-results.php           # Results display
│   ├── series-overview.php              # Series pages
│   └── player-profile.php               # Player profiles
├── assets/
│   ├── css/
│   │   └── admin.css                    # Admin styling
│   └── js/
│       ├── admin.js                     # Admin functionality
│       └── import.js                    # File upload handling
├── languages/
│   └── poker-tournament-import.pot      # Translation template
└── tests/
    ├── test-parser.php                  # Parser unit tests
    ├── test-post-types.php              # Post type tests
    └── test-import.php                  # Import integration tests
```

## Key Development Patterns

### .tdt File Parsing Strategy
```php
// Memory-efficient parsing for large files
class TDT_Parser {
    public function parse_file($file_path) {
        // Use streaming JSON/parser for large files
        // Implement error recovery for malformed data
        // Validate data structure before database insertion
    }
}
```

### WordPress Integration Pattern
```php
// Custom post type registration
register_post_type('tournament', [
    'public' => true,
    'has_archive' => true,
    'supports' => ['title', 'custom-fields'],
    'rewrite' => ['slug' => 'tournaments'],
]);
```

### Security Pattern
```php
// Secure file upload handling
function handle_tdt_upload($file) {
    $file_type = wp_check_filetype($file['name']);
    if ($file_type['ext'] !== 'tdt') {
        wp_die('Invalid file type');
    }
    // Additional validation and processing
}
```

## Testing Requirements

### Essential Tests
- **Parser Tests**: Test with various .tdt file formats and edge cases
- **Database Tests**: Verify data integrity and relationships
- **Security Tests**: File upload validation and data sanitization
- **Performance Tests**: Large dataset handling and memory usage
- **WordPress Integration**: Hooks, filters, and admin interface

### WordPress Testing Framework
```bash
# WordPress PHPUnit testing
wp scaffold plugin-tests poker-tournament-import
composer install
phpunit
```

## Development Workflow

### Local Development Commands
```bash
# Start development
cd wp-content/plugins/poker-tournament-import

# Watch for changes (if using build tools)
npm run watch

# Run tests
npm test

# Check WordPress coding standards
composer run phpcs

# Build for production
npm run build
```

### WordPress Plugin Development Best Practices
- Use WordPress settings API for plugin options
- Implement proper capability checks (`current_user_can()`)
- Use WordPress nonce verification for security
- Implement plugin activation/deactivation hooks
- Follow WordPress database naming conventions
- Use transients for caching where appropriate

## Common Development Tasks

### Adding New Tournament Import Features
1. Extend TDT_Parser class with new data extraction methods
2. Add corresponding database fields to custom post type meta
3. Update admin interface to handle new data fields
4. Modify display templates to show new information
5. Add tests for new parsing functionality

### Creating New Display Templates
1. Create template file in `/templates/` directory
2. Add shortcode in `class-shortcodes.php`
3. Implement template rendering in `class-templates.php`
4. Add CSS styling in `/assets/css/`
5. Test with various WordPress themes

### Adding Database Schema Changes
1. Use WordPress dbDelta() function in activation hook
2. Create migration class for version updates
3. Update model classes for new data structure
4. Add corresponding tests
5. Update documentation

## Plugin Deployment

### WordPress.org Repository Requirements
- Plugin header with proper metadata
- Readme.txt file following WordPress.org standards
- Asset directory for screenshots and banners
- Internationalization support
- Security review compliance

### Version Management
- Semantic versioning (1.0.0, 1.1.0, etc.)
- Proper changelog maintenance
- Database migration handling for updates
- Backward compatibility considerations

## Key Files to Monitor

- **`poker-tournament-import.php`**: Main plugin functionality and hooks
- **`includes/class-parser.php`**: Core .tdt parsing logic
- **`includes/class-admin.php`**: Admin interface and import workflow
- **`templates/tournament-results.php`**: Public-facing display logic
- **`tests/`**: All test files for functionality validation

## Performance Considerations

- Use WordPress WP_Query with proper caching
- Implement database indexes for tournament queries
- Use transients for computed statistics
- Optimize image handling for tournament displays
- Consider background processing for large imports