# Implementation Plan: Restore Dashboard Overview and Action Buttons

**Branch**: `006-restore-dashboard` | **Date**: 2025-01-02 | **Status**: 📋 **PLANNING**
**Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/006-restore-dashboard/spec.md`

## Summary

Restore the WordPress admin dashboard overview page that existed in plugin versions 3.3 and 3.4. The dashboard provides tournament directors with a single-page overview showing tournament count, player count, season count, formula count, quick action buttons, data mart health status, and recent activity table. Technical approach: Re-implement the `render_dashboard()` method from version 3.4.0-beta4 (commit 4ff9552), restoring all 4 dashboard sections with original inline CSS styling and WordPress admin integration.

## Technical Context

**Language/Version**: PHP 8.0+ (8.2+ compatible)
**Primary Dependencies**: WordPress 6.0+, WordPress Coding Standards
**Storage**: WordPress MySQL database (wp_ prefix)
**Testing**: Manual WordPress admin testing, PHP syntax validation (`php -l`)
**Target Platform**: WordPress plugin (admin dashboard)
**Project Type**: single (WordPress plugin)
**Performance Goals**: <2s page load for dashboard, accurate count queries using WordPress core functions
**Constraints**: Must follow WordPress security best practices (escaping, nonces, capability checks), must match v3.3/v3.4 visual design exactly
**Scale/Scope**: Single admin page, 4 dashboard sections, ~200 lines of PHP code, 0 new dependencies

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### WordPress Plugin Standards

**Code Quality & Standards Compliance**:
- [x] PHP 8.0+ with 8.2+ compatibility - no declare dynamic properties needed (no new class properties)
- [x] WordPress Coding Standards - follow WP naming conventions (snake_case for functions)
- [x] Proper internationalization - use `__()`, `_e()` with text domain 'poker-tournament-import'
- [x] No underscore prefixes for custom variables
- [x] Class-based architecture - extending existing `Poker_Tournament_Admin` class

**Security First (NON-NEGOTIABLE)**:
- [x] Output escaping: `esc_html()` for all output, `esc_url()` for URLs
- [x] Capability checks: `current_user_can('manage_options')` inherited from menu registration
- [x] Prepared statements for database queries - using WordPress core functions (`wp_count_posts`, `get_posts`)
- [x] No direct database queries except for data mart health check (will use `$wpdb->prepare`)

**Performance & Scalability**:
- [x] Use WordPress core functions for counts (cached by WP)
- [x] Limit queries to 5 recent tournaments
- [x] No expensive joins or complex aggregations
- [x] <2s page load target achievable with core functions

**Testing Discipline**:
- [x] PHP syntax check: `php -l` on modified files
- [x] Manual testing in WordPress admin
- [x] Visual verification against v3.3/v3.4 screenshots

**Multi-Platform Coordination**:
- [x] WordPress plugin: Admin dashboard only, no mobile/controller changes needed

**Status**: ✅ PASSED - No constitution violations expected

This is a restoration feature - all code patterns exist in git history and will be copied/adapted. No new architecture or complexity introduced.

## Project Structure

### Documentation (this feature)

```text
specs/006-restore-dashboard/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── poker-tournament-import.php  # Main plugin file (version bump)
├── admin/
│   ├── class-admin.php           # PRIMARY FILE: Add render_dashboard() method
│   │   ├── add_admin_menu()      # Already registers 'poker-tournament-import' menu
│   │   └── render_dashboard()    # TO BE RESTORED: Dashboard page rendering
│   └── assets/
│       └── css/
│           └── admin.css         # No CSS changes needed (uses inline styles)
└── includes/
    ├── class-post-types.php      # Tournament, player, season post types (no changes)
    └── class-formula-validator.php  # Formula Validator class (existing, used for formula count)
```

**Structure Decision**: This is a WordPress plugin (Option 1: Single project structure). The dashboard is a single admin page rendered by a method in the existing `Poker_Tournament_Admin` class. No new files needed - all changes are in the existing `class-admin.php` file.

## Complexity Tracking

> **No constitution violations - this section not applicable**

This is a straightforward restoration feature:
- Single method addition (~200 lines of PHP/HTML)
- No new abstractions or patterns
- Follows existing WordPress admin page patterns
- No breaking changes
- No new dependencies

All dashboard components (stat cards, quick actions, data mart health, recent activity) are simple HTML rendering with WordPress core functions for data retrieval.

## Phase 0: Research & Decisions ✅ COMPLETE

**Output**: [research.md](./research.md)

### Research Summary

**Key Findings**:
1. **Original Implementation Located**: `render_dashboard()` method existed in version 3.4.0-beta4 (commit 4ff9552)
2. **4 Dashboard Sections Identified**: Stat Cards Grid, Quick Actions, Data Mart Health, Recent Activity
3. **All Dependencies Available**: Uses existing WordPress core functions and existing `Poker_Tournament_Formula_Validator` class
4. **No New CSS Needed**: Uses inline styles matching WordPress admin aesthetic
5. **Data Mart Integration**: Queries `wp_poker_statistics` table for health check

**Technical Decisions**:
1. **Restoration Approach**: Copy original implementation from commit 4ff9552 and adapt to current codebase
2. **Styling Strategy**: Use inline CSS (original approach) for immediate visual rendering
3. **Data Retrieval**: Use `wp_count_posts()` for stat cards, `get_posts()` for recent tournaments
4. **Data Mart Check**: Direct table query with `$wpdb->prepare()` for security
5. **Icon Strategy**: Use WordPress dashicons (no custom icons)
6. **Link Strategy**: Use `admin_url()` for all admin navigation links
7. **Security**: Rely on existing menu capability check, use `esc_html()` and `esc_url()` throughout

**Alternatives Considered and Rejected**:
- Create new Dashboard class → Rejected because original used method in admin class, no need for new abstraction
- Use external CSS file → Rejected because original used inline styles, simpler to keep same approach
- AJAX data loading → Rejected because original loaded data synchronously, no performance issue for simple counts

## Phase 1: Design ✅ COMPLETE

**Output**: [data-model.md](./data-model.md), [contracts/README.md](./contracts/README.md), [quickstart.md](./quickstart.md)

### Data Model

**No New Data Structures**: All data comes from existing WordPress post types and options

**Existing Entities Used**:
- **Tournament Posts**: Custom post type `tournament` - count via `wp_count_posts('tournament')`
- **Player Posts**: Custom post type `player` - count via `wp_count_posts('player')`
- **Season Posts**: Custom post type `tournament_season` - count via `wp_count_posts('tournament_season')`
- **Formulas**: Stored in WordPress options, retrieved via `Poker_Tournament_Formula_Validator::get_all_formulas()`
- **Data Mart**: Table `wp_poker_statistics` - queried directly for existence and row count
- **Recent Activity**: Tournament post objects - queried via `get_posts()` with date ordering

### Contracts

**No New APIs**: Dashboard is a server-rendered admin page, no REST/GraphQL endpoints

**Admin Page Contract**:
```php
// WordPress admin page: render_dashboard()
Displays:
  - 4 stat cards (Tournaments, Players, Seasons, Formulas)
  - Quick Actions section (4 action buttons)
  - Data Mart Health section (status, record count, last refresh)
  - Recent Activity table (5 most recent tournaments)

Access Control:
  - Capability: 'manage_options' (inherited from menu registration)
  - Menu slug: 'poker-tournament-import'
  - Page callback: render_dashboard() method in Poker_Tournament_Admin class

Security:
  - Output escaping: esc_html() for text, esc_url() for URLs
  - Nonces: Not required (read-only dashboard, no form submissions)
  - Capability checks: Inherited from menu registration
```

### Implementation Details

**File**: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
**Method**: `render_dashboard()` - NEW METHOD (approximately 200 lines)

**Implementation Strategy**:
1. Retrieve counts using WordPress core functions
2. Query data mart health with prepared statement
3. Render 4 stat cards with inline CSS
4. Render Data Mart Health section
5. Render Quick Actions section
6. Render Recent Activity table (if tournaments exist)
7. All output properly escaped with `esc_html()` and `esc_url()`

### Testing Strategy

**Manual Testing** (Required):
1. Test dashboard displays with 0 tournaments/players/seasons
2. Test dashboard displays with multiple tournaments/players/seasons
3. Test all "View All" and action buttons navigate correctly
4. Test data mart health shows correct status
5. Test recent activity shows most recent 5 tournaments
6. Test visual appearance matches v3.3/v3.4
7. Test PHP syntax: `php -l class-admin.php`
8. Test no WordPress PHP errors or warnings

**Success Criteria**:
- ✅ Dashboard loads without errors
- ✅ All stat counts are accurate
- ✅ All navigation links work correctly
- ✅ Data Mart Health section displays correctly
- ✅ Recent Activity shows exactly 5 tournaments
- ✅ Visual design matches v3.3/v3.4
- ✅ All output properly escaped

## Phase 2: Implementation Tasks

**Output**: `tasks.md` (generated by `/speckit.tasks` command - NOT YET CREATED)

**Next Step**: Run `/speckit.tasks` to generate implementation tasks

---

## Ready for Implementation

**Status**: ✅ Planning complete, ready for task generation

**Completed Artifacts**:
- ✅ Feature specification ([spec.md](./spec.md))
- ✅ Technical research ([research.md](./research.md))
- ✅ Data model documentation ([data-model.md](./data-model.md))
- ✅ Contract documentation ([contracts/README.md](./contracts/README.md))
- ✅ Quickstart guide ([quickstart.md](./quickstart.md))

**Next Command**: `/speckit.tasks` to generate implementation tasks
