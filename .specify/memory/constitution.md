<!--
Sync Impact Report:
Version change: Initial → 1.0.0
Modified principles: N/A (initial creation)
Added sections:
  - Core Principles (5 principles)
  - Security Requirements
  - Performance Standards
  - Governance
Templates requiring updates:
  ✅ plan-template.md - Constitution Check section aligned
  ✅ spec-template.md - Requirements sections aligned
  ✅ tasks-template.md - Task categorization aligned
Follow-up TODOs: None
-->

# Poker Tournament Import Plugin Constitution

## Core Principles

### I. WordPress Security-First

**MUST comply with WordPress.com security guidelines**: All features MUST implement proper data sanitization (`sanitize_text_field()`, `wp_kses_post()`), nonce verification for AJAX/form submissions, capability checks (`current_user_can('manage_options')`), and prepared statements for database operations. File uploads MUST validate file type and content. NO external security plugins needed - follow platform standards.

**Rationale**: WordPress plugins handle sensitive user data. Security breaches can expose personal information, cause data loss, and damage reputation. Following WordPress security standards ensures protection against XSS, CSRF, SQL injection, and unauthorized access without adding unnecessary dependencies.

### II. Code Quality & Standards

**MUST follow WordPress Coding Standards**: All code MUST adhere to WordPress PHP coding standards. Classes use `PascalCase_With_Underscores` (e.g., `Poker_Tournament_Parser`), methods/variables use `snake_case`, files use `hyphen-separation` (e.g., `class-parser.php`). PHP 8.0+ required with 8.2+ compatibility (declare dynamic properties). **NEVER use underscore prefixes for custom variables** - reserved for WordPress internals.

**MUST internationalize all user-facing text**: Use text domain `'poker-tournament-import'` with `__()`, `_e()`, `esc_html__()` functions. Enables translation and broader adoption.

**MUST document with PHPDoc**: All classes/methods require PHPDoc blocks with `@since`, `@param`, `@return` tags. Complex logic requires inline comments explaining "why" not just "what".

**Rationale**: Consistent code standards enable maintainability, reduce bugs, facilitate team collaboration, and ensure WordPress.org plugin directory approval. Proper documentation reduces onboarding time and enables long-term maintenance.

### III. Testing Discipline

**MUST validate PHP syntax on all changes**: Run `php -l` on modified PHP files before committing. Catches syntax errors early.

**MUST test with representative data**: Use sample .tdt files from `tdtfiles/` directory covering various tournament structures, player counts, and edge cases. Test import, display, statistics calculation, and performance with realistic data.

**MUST verify memory efficiency**: Large tournament imports (500+ players) MUST complete without exceeding 512MB memory. Use streaming parsers, chunk processing, and transient caching.

**SHOULD write automated tests for critical paths**: Parser logic, points formula calculation, database operations, and security functions benefit from PHPUnit tests in `tests/` directory.

**Rationale**: WordPress environment inconsistencies demand thorough testing. Representative data catches edge cases. Memory limits are common hosting constraints. Automated tests prevent regressions in business-critical calculations.

### IV. User Experience Consistency

**MUST follow WordPress admin UI patterns**: Use WordPress admin components (metaboxes, notices, settings API). Match WordPress core styling. Ensure mobile-responsive admin interfaces.

**MUST provide clear feedback**: Loading states for imports, success/error messages for all operations, progress indicators for long-running tasks, validation messages for form inputs.

**MUST ensure frontend template compatibility**: Templates MUST work with common WordPress themes without style conflicts. Use namespaced CSS classes (e.g., `.poker-tournament-`), enqueue styles properly, test with popular themes.

**MUST optimize for accessibility**: Follow WCAG 2.1 AA standards - keyboard navigation, screen reader support, proper semantic HTML, sufficient color contrast.

**Rationale**: Users expect WordPress plugins to feel native. Consistent UX reduces learning curve, increases adoption, and minimizes support requests. Accessibility is both ethical requirement and legal compliance in many jurisdictions.

### V. Performance Requirements

**MUST implement transient caching**: Computed statistics, leaderboards, and aggregations MUST cache results using WordPress transients. Cache invalidation on data changes.

**MUST optimize database queries**: Use proper indexing on foreign keys and frequently queried columns. Avoid N+1 queries. Use `$wpdb->prepare()` for parameterized queries.

**MUST handle large files efficiently**: Parsing .tdt files >5MB MUST use streaming/chunking. Avoid loading entire file into memory.

**MUST implement async processing**: Statistics refresh on tournament save/delete MUST use `wp_schedule_single_event()` for background processing. Don't block user interactions.

**Performance targets**: Import 200-player tournament <30 seconds, display tournament results <500ms, admin dashboard loads <2 seconds.

**Rationale**: Performance directly impacts user satisfaction and hosting costs. Shared hosting environments have strict resource limits. Background processing prevents timeouts and improves perceived responsiveness.

## Security Requirements

### WordPress Security Compliance

**File Upload Security**:
- MUST validate .tdt file extensions and MIME types
- MUST sanitize all parsed content before database storage
- MUST limit file size (default: 10MB max)
- MUST store uploads in non-web-accessible directory when possible

**Input Validation & Sanitization**:
- Form inputs: `sanitize_text_field()`, `sanitize_email()`, `absint()` for IDs
- Rich text: `wp_kses_post()` with allowed HTML tags
- URLs: `esc_url()`, `esc_url_raw()`
- Database output: `esc_html()`, `esc_attr()`, `wp_kses_post()`

**Authentication & Authorization**:
- Admin actions: `current_user_can('manage_options')` check
- AJAX handlers: Nonce verification via `check_ajax_referer()`
- Form submissions: Nonce verification via `wp_verify_nonce()`
- Role-based access: Use WordPress capability system

**Database Security**:
- MUST use `$wpdb->prepare()` for all parameterized queries
- NEVER concatenate user input into SQL
- Use `%s`, `%d`, `%f` placeholders appropriately
- Validate/sanitize before prepare statements

### Data Privacy (GDPR Compliance)

- Player data: Support data export/deletion requests
- Minimal data collection: Only store tournament-relevant data
- Clear privacy policy integration
- User consent for data display (configurable visibility)

## Performance Standards

### Database Optimization

**Indexing Requirements**:
- Primary keys on all custom tables
- Foreign key indexes for relationships
- Composite indexes on frequently queried column combinations
- Unique indexes on UUID/identifier columns

**Query Optimization**:
- Use `SELECT` specific columns, avoid `SELECT *`
- Implement pagination for large result sets
- Cache query results with transients (5-60 minute TTL)
- Monitor slow query log (>500ms queries need optimization)

### Caching Strategy

**Transient Usage**:
- Statistics dashboard: 15-minute cache
- Leaderboards: 5-minute cache
- Player profiles: 30-minute cache
- Series standings: 10-minute cache
- Invalidate on relevant data changes

**Object Caching Support**:
- Compatible with Redis/Memcached when available
- Graceful degradation to transients
- Cache keys namespaced with plugin prefix

### Resource Management

**Memory Limits**:
- Target: 256MB minimum, 512MB recommended
- Large imports: Chunk processing in 100-record batches
- Cleanup: `unset()` large variables after processing

**Execution Time**:
- Imports >30 seconds: Use background processing
- Admin pages: <3 seconds total load time
- AJAX requests: <2 seconds response time
- Frontend: <500ms first contentful paint

## Governance

### Amendment Process

Constitution amendments require:
1. Clear justification documenting why change needed
2. Version increment following semantic versioning (MAJOR.MINOR.PATCH)
3. Update all dependent templates in `.specify/templates/`
4. Sync report documenting changes and impact
5. Validation that no unexplained placeholder tokens remain

### Versioning Policy

- **MAJOR**: Backward-incompatible principle removals/redefinitions
- **MINOR**: New principles added or material guidance expansions
- **PATCH**: Clarifications, wording fixes, non-semantic refinements

### Compliance Review

**Pre-Development**:
- All feature specs MUST reference constitution principles
- Implementation plans MUST include "Constitution Check" section
- Complexity violations MUST be justified in plan

**Pre-Commit**:
- Run `php -l` on all modified PHP files
- Verify nonce implementation on new AJAX handlers
- Check capability checks on new admin features
- Update version numbers in plugin header and constant

**Pre-Release**:
- Test with sample .tdt files from `tdtfiles/` directory
- Verify all security requirements implemented
- Check performance targets met
- Create updated installer ZIP: `poker-tournament-import-vX.X.X.zip`
- Update version in log file output

### Development Workflow

**Release Process**:
1. Update `POKER_TOURNAMENT_IMPORT_VERSION` constant
2. Update plugin header version in `poker-tournament-import.php:6`
3. Test changes thoroughly with representative data
4. Run syntax validation on all modified files
5. Create distribution ZIP with version number
6. Update changelog in README.md

**Local Development**:
- WordPress shell: `/Users/hkh/Library/Application\ Support/Local/ssh-entry/hNPsf2SE_.sh`
- Test environment: Local WP with sample tournament data
- Database access: Via WP shell or phpMyAdmin

**Guidance Files**:
- Runtime development: `/Users/hkh/dev/tdwpimport/CLAUDE.md`
- User instructions: `/Users/hkh/.claude/CLAUDE.md`
- Memory files: `.specify/memory/*.md`

**Version**: 1.0.0 | **Ratified**: 2025-10-30 | **Last Amended**: 2025-10-30
