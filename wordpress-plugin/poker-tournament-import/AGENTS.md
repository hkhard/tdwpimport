# PROJECT KNOWLEDGE BASE

**Generated:** 2026-01-16
**Plugin Version:** 3.6.5
**Location:** wordpress-plugin/poker-tournament-import/

## OVERVIEW
Core WordPress plugin for Tournament Director .tdt file import and live tournament management.

## STRUCTURE
```
poker-tournament-import.php        # Main entry (singleton)
includes/                          # Core (51 files)
  class-parser.php                 # .tdt AST parser (TD v3.7.2+)
  class-shortcodes.php             # 20+ shortcodes (5542 lines)
  class-statistics-engine.php       # Data mart, auto-refresh
  tournament-manager/             # Live tournament (34 classes)
    class-tournament-live.php       # State, clock
    class-display-manager.php      # TD3 display screens
    class-database-schema.php       # Tables, migrations
admin/                             # Admin interface
  class-admin.php                  # Pages (5232 lines)
  tournament-manager/              # Live tournament UI
templates/                          # CPT display templates
assets/                             # Frontend CSS/JS
```

## WHERE TO LOOK
| Task | Location | Notes |
|------|----------|-------|
| Main plugin | poker-tournament-import.php | Init, hooks, activation |
| .tdt parsing | includes/class-parser.php | AST parser, TD v3.7.2+ |
| Live state | tournament-manager/class-tournament-live.php | State persistence |
| Shortcodes | includes/class-shortcodes.php | 20+ display shortcodes |
| Statistics | includes/class-statistics-engine.php | Data mart, auto-refresh |
| Formula system | includes/class-formula-validator.php | AJAX validation |
| Admin UI | admin/class-admin.php | Bulk import, dashboard |
| TD3 displays | tournament-manager/class-display-manager.php | Screens, URL rewrite |
| DB tables | tournament-manager/class-database-schema.php | tdwp_ tables |

## CONVENTIONS

### PHP (WordPress Standard)
- No namespaces: Global namespace (WordPress standard)
- Class naming: `Class_Name` format
- Singleton pattern: Main class, display manager, statistics engine
- Hook prefixes: `tdwp_` for custom hooks
- AJAX handlers: `wp_ajax_tdwp_*` (logged-in), `wp_ajax_nopriv_tdwp_*` (public)
- Direct DB: `$wpdb->prefix`, no ORM
- No build: PHP interpreted directly

### Database Prefixing
- Standard WP: `wp_` prefix (posts, postmeta, etc.)
- Custom: `tdwp_` prefix (tdwp_tournament_templates, etc.)
- Legacy: `poker_` prefix (data mart, will migrate)
- Check prefix before query: Mix of prefixes

### Version Tracking & Release
- Header: Line 6 in poker-tournament-import.php
- Constant: `POKER_TOURNAMENT_IMPORT_VERSION` line ~23 - MUST MATCH header
- Current mismatch: Header 3.6.5, constant 3.6.3
- Release: Update header + constant → readme.txt changelog → test → zip → `php -l`

## ANTI-PATTERNS (THIS PLUGIN)

### CRITICAL DO NOT:
- DO NOT USE `class-parser-legacy-ranking.php` (DEPRECATED v2.8.0)
- NEVER use `_` prefix for variables (WordPress internal)
- NEVER commit without updating BOTH version numbers
- NEVER disable features without explicit instruction

### CRITICAL ALWAYS:
- ALWAYS log to error_log in class-parser.php line 1705
- ALWAYS verify .tdt format before parsing (TD v3.7.2+)
- ALWAYS update version in log output for patch level
- ALWAYS create zip for WordPress releases

### NEVER VIOLATE:
- Version mismatch: Header (3.6.5) vs constant (3.6.3)
- Database prefix: Mixed wp_ and tdwp_ - check before query
