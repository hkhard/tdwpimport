# Code Style & Conventions

## Critical Rules (from CLAUDE.md)
**NEVER use underscore prefixes for our own variables** - WordPress reserves _ prefix for internal use

## PHP Standards
- **PHP Version**: 8.0+ (declare dynamic properties for 8.2+ compatibility)
- **Coding Standard**: WordPress Coding Standards
- **Naming Conventions**:
  - Classes: PascalCase with underscores (e.g., `Poker_Tournament_Parser`)
  - Methods: snake_case (e.g., `parse_file`)
  - Variables: snake_case (e.g., `tournament_data`)
  - Constants: UPPER_CASE (e.g., `POKER_TOURNAMENT_IMPORT_VERSION`)
  - Files: hyphen-separated (e.g., `class-parser.php`)

## Security Requirements
- Secure file upload with .tdt validation
- Data sanitization: `sanitize_text_field()`, `wp_kses_post()`
- Nonce verification for AJAX handlers
- Role checks: `current_user_can('manage_options')`
- Prepared statements for DB operations

## Performance Practices
- Transient caching for computed statistics
- Proper database indexing
- Memory-efficient parsing for large files
- Async statistics refresh via `wp_schedule_single_event()`

## Internationalization
- Text domain: 'poker-tournament-import'
- Functions: `__()`, `_e()`, `esc_html__()`

## Documentation
- PHPDoc blocks for all classes and methods
- Include @since, @param, @return tags
- Clear inline comments for complex logic
