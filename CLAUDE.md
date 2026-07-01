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

### Database Tables (import / statistics subsystem — `poker_*`)
> Note: the plugin uses **two** prefixes — `poker_*` (below) and `tdwp_*` (live
> tournament manager). See "Database Table Prefixes (canonical)" below for the
> full rule. These are prefixed onto WordPress's `$wpdb->prefix` (usually `wp_`).
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
- JavaScript (ES5+ for WordPress compatibility), PHP 8.0+ (WordPress standard) + jQuery (already enqueued), WordPress admin APIs (005-formula-manager)
- WordPress options table (via `Poker_Tournament_Formula_Validator` class) (005-formula-manager)

## Recent Changes
- 001-td3-integration: Added PHP 8.0+ (8.2+ compatible) + WordPress 6.0+, MySQL 5.7+, jQuery, modern JavaScript (ES6+)

## Database Table Prefixes (canonical)

The plugin uses **two** custom-table prefixes (in addition to WordPress's own
`$wpdb->prefix`, usually `wp_`). This is by history, not by mistake — do not
assume a single prefix:

- **`poker_*`** — the original **import / statistics** subsystem: dashboard and
  leaderboard data marts read by the stats engine and shortcodes. Examples:
  `poker_tournament_players`, `poker_player_roi`, `poker_statistics`,
  `poker_financial_summary`, `poker_revenue_analytics`, `poker_tournament_costs`,
  and the display tables `poker_display_screens` / `poker_display_templates` /
  `poker_display_layouts`.
- **`tdwp_*`** — the **TD3 live tournament-manager** subsystem: live state and
  configuration. Examples: `tdwp_tournament_players`, `tdwp_tournament_templates`,
  `tdwp_tournament_live_state`, and related live tables.

When writing a new query, check which subsystem the data belongs to and use the
matching prefix via `$wpdb->prefix`. The live → legacy bridge that lets live
tournaments surface in the `poker_*` marts is `TDWP_Stats_Bridge`
(see closed bead `tdwp-iwc`). Consolidating onto a single canonical store is
tracked as a future task (`tdwp-ayg`, "Option C"); until then, **both prefixes
are correct** for their respective subsystems.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **tdwpimport** (12481 symbols, 24461 relationships, 300 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/tdwpimport/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/tdwpimport/context` | Codebase overview, check index freshness |
| `gitnexus://repo/tdwpimport/clusters` | All functional areas |
| `gitnexus://repo/tdwpimport/processes` | All execution flows |
| `gitnexus://repo/tdwpimport/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->


<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd dolt push
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
<!-- END BEADS INTEGRATION -->
