<!--
Sync Impact Report
==================
Version change: [INITIAL] → 1.0.0
Modified principles: N/A (initial version)
Added sections:
  - Core Principles (5 principles)
  - Security Requirements
  - Development Standards
  - Multi-Platform Architecture
  - Governance
Removed sections: N/A (initial version)
Templates requiring updates:
  - .specify/templates/plan-template.md (needs Constitution Check alignment)
  - .specify/templates/spec-template.md (needs security/performance constraint references)
  - .specify/templates/tasks-template.md (needs testing/security task categories)
  - CLAUDE.md (already references constitution)
Follow-up TODOs: None
-->

# TDWP Import Constitution

## Core Principles

### I. Code Quality & Standards Compliance

**WordPress Plugin (PHP):**
- PHP 8.0+ with 8.2+ compatibility (declare dynamic properties)
- WordPress Coding Standards strictly enforced
- Proper internationalization: `__()`, `_e()` with text domain `poker-tournament-import`
- **Never use underscore prefixes for our own variables** (WordPress reserves `_` for internal use)
- Class-based architecture with clear separation of concerns

**TypeScript Projects (Controller/Mobile):**
- TypeScript 5.0+ in strict mode (no `any`, explicit return types)
- Controller: Node.js 20+ with Fastify framework
- Mobile: Expo SDK 54 with React Navigation v6, Zustand for state
- Async/await over callbacks for all async operations

**Rationale:** Multi-platform consistency reduces cognitive load. Strict typing catches bugs at compile time. WordPress standards ensure plugin approval and ecosystem compatibility.

### II. Security First (NON-NEGOTIABLE)

**Data Validation:**
- All user input MUST be sanitized: `sanitize_text_field()`, `wp_kses_post()`
- Nonce verification required for ALL AJAX handlers
- Role-based access checks: `current_user_can('manage_options')` for admin operations
- File upload validation with .tdt format verification

**Database Safety:**
- Prepared statements for ALL database operations (WordPress `$wpdb->prepare()`)
- SQL injection prevention: never concatenate user input into queries
- Database prefix: `tdwp_` for all custom tables (controller) - `wp_` for WordPress tables

**Output Escaping:**
- Escape all dynamic output with `esc_html()`, `esc_attr()`, or `wp_kses_post()`
- Use WordPress functions for URLs: `esc_url()`, `admin_url()`

**Rationale:** Poker tournaments involve financial data. Security breaches could impact real-money operations. WordPress security best practices prevent plugin vulnerabilities.

### III. Performance & Scalability

**Caching Strategy:**
- Transient caching for computed statistics with appropriate expiration
- WordPress object cache (`wp_cache_get()`, `wp_cache_set()`) where applicable
- AsyncStorage for mobile offline caching

**Database Optimization:**
- Proper indexes on frequently queried columns
- Avoid N+1 queries: use joins or eager loading
- Data mart tables (`poker_statistics`) for pre-aggregated analytics
- Asynchronous statistics refresh: `wp_schedule_single_event()`

**Memory Management:**
- Stream large .tdt file parsing (don't load entire file into memory)
- Limit result sets with pagination
- Clean up transients on plugin deactivation

**Target Performance:**
- Statistics queries: <500ms p95
- .tdt file parsing: handle 10,000+ player tournaments
- Mobile app: 60fps UI, <100ms API response perception

**Rationale:** Poker tournaments can have hundreds of players. Poor performance blocks tournament directors during live events. Caching prevents expensive recalculations.

### IV. Testing Discipline

**Required Test Coverage:**
- Database migrations: MUST be tested on clean database
- API endpoints: validate request/response schemas
- .tdt parser: test with malformed files, edge cases
- Statistics calculations: verify accuracy against manual calculations

**Test-First Workflow for Critical Paths:**
1. Write failing test for business logic
2. Implement minimum code to pass
3. Refactor for clarity
4. Document edge cases

**Quality Gates:**
- PHP syntax check: `php -l` on all changed files
- TypeScript compilation: `tsc --noEmit` must pass
- Database migrations: MUST be reversible with `down()` script
- No uncommitted database schema changes

**Rationale:** Financial and statistical data errors have real consequences. Tests catch regressions before they reach production.

### V. Multi-Platform Coordination

**Architecture Principles:**
- WordPress plugin: Data persistence, admin interface, public display
- Controller: Real-time API, business logic, mobile backend
- Mobile app: Tournament director tools, offline-first

**Data Consistency:**
- Single source of truth: WordPress MySQL database
- Controller: SQLite cache with sync to WordPress
- Mobile: AsyncStorage + SQLite with pull-to-refresh

**API Design:**
- RESTful endpoints in controller (`/api/tournaments`, `/api/blinds`)
- Versioned APIs: `/api/v1/` for backward compatibility
- WebSocket support for real-time updates (controller)

**Cross-Cutting Concerns:**
- Error handling: consistent format across platforms
- Logging: structured logs with correlation IDs
- Time zones: always store UTC, display local time

**Rationale:** Separation of concerns allows independent deployment. Mobile app works offline during live tournaments where connectivity is unreliable.

## Security Requirements

### File Handling
- .tdt file uploads: validate magic bytes, file extension, size limits
- Never execute uploaded files
- Store uploads outside webroot when possible

### Authentication & Authorization
- WordPress admin capability checks for plugin admin pages
- Controller: JWT tokens for mobile API authentication
- Session management: secure token storage (Keychain on iOS)

### Data Protection
- Sensitive data: never log passwords, tokens, financial details
- PII handling: comply with data protection regulations
- Database credentials: environment variables only

## Development Standards

### Code Review Checklist
- [ ] Security: input sanitization, output escaping, nonce verification
- [ ] Performance: queries indexed, caching where appropriate
- [ ] i18n: all strings wrapped in translation functions
- [ ] Documentation: inline comments for complex logic
- [ ] Testing: tests pass, edge cases covered

### Release Process
1. Update version in `POKER_TOURNAMENT_IMPORT_VERSION` constant
2. Update plugin header version
3. Test changes thoroughly (manual + automated)
4. Create distribution zip: `poker-tournament-import-vX.X.X.zip`
5. Tag release in git

### Version Format
- WordPress plugin: `MAJOR.MINOR.PATCH` (SemVer)
- Database migrations: numbered sequentially `001_description.sql`
- Controller/mobile: independent versioning

## Multi-Platform Architecture

### Technology Stack Summary

| Platform | Tech | Purpose |
|----------|------|---------|
| WordPress Plugin | PHP 8.0+, MySQL 5.7+ | Admin UI, data persistence, public display |
| Controller | TypeScript, Fastify, SQLite | Real-time API, mobile backend |
| Mobile App | Expo 54, React Native, SQLite | Tournament director tools, offline-first |

### Database Schemas

**WordPress (MySQL, wp_ prefix):**
- Custom post types: `tournament`, `tournament_series`, `player`
- Custom tables: `poker_tournament_players`, `poker_statistics`, `poker_tournament_costs`, `poker_financial_summary`, `poker_player_roi`, `poker_revenue_analytics`

**Controller (SQLite, tdwp_ prefix):**
- Cache tables for performance
- Real-time data synchronization

**Mobile (SQLite, tdwp_ prefix):**
- Offline storage with sync on reconnect

## Governance

### Amendment Process
1. Propose change with rationale
2. Document impact on existing code
3. Update constitution version (semantic versioning)
4. Update dependent templates/specs
5. Communicate to team

### Version Policy
- **MAJOR**: Backward-incompatible changes (e.g., removing a principle, changing tech stack)
- **MINOR**: New principle added or existing principle materially expanded
- **PATCH**: Clarifications, wording improvements, non-semantic changes

### Compliance Review
- All PRs MUST verify compliance with constitution principles
- Complexity MUST be justified (YAGNI principles apply)
- Use `CLAUDE.md` for runtime development guidance
- Constitution supersedes all other practices

### Enforcement
- Automated checks: PHP linting, TypeScript compilation, syntax validation
- Manual review: Security review for auth/data changes, performance review for queries
- Violation: Block merge if constitutional principles are violated

---

**Version**: 1.0.0 | **Ratified**: 2025-12-29 | **Last Amended**: 2025-12-29
