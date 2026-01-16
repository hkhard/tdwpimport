# PROJECT KNOWLEDGE BASE

**Generated:** 2026-01-16
**Commit:** b3388f5
**Branch:** 014-shortcode-upload-fix

## OVERVIEW
Multi-tier poker tournament management platform: WordPress plugin (core PHP 8.0+ import/display system), mobile app (Expo React Native with offline sync), controller (Node.js/Fastify API with WebSocket), shared TypeScript library, Next.js websites. v3.6.5 WordPress plugin in production.

## STRUCTURE
```
tdwpimport/
├── wordpress-plugin/poker-tournament-import/    # Core .tdt import, tournament management (v3.6.5)
├── mobile-app/                                # Expo RN app with offline sync
├── controller/                                # Fastify API + WebSocket server
├── shared/                                     # TS types, schemas, services
├── tdwp-website/                              # Next.js marketing site
├── tdwp-website-cybrancee/                  # Next.js static export site
├── specs/                                      # Feature specs (001-014)
├── claude-continuity-kit/                    # Claude Code session management
└── thoughts/                                   # Handoffs, plans, ledgers
```

## WHERE TO LOOK
| Task | Location | Notes |
|------|----------|-------|
| WordPress plugin core | `wordpress-plugin/poker-tournament-import/` | Main: poker-tournament-import.php |
| .tdt file parser | `wordpress-plugin/poker-tournament-import/includes/class-parser.php` | Tournament Director v3.7.2+ |
| Live tournament management | `wordpress-plugin/poker-tournament-import/includes/tournament-manager/` | TD3 display, timer, blind schedules |
| Mobile app entry | `mobile-app/App.tsx` | Tab nav: Tournaments, Timer, Players, Settings |
| Controller API | `controller/src/api/routes/` | Fastify REST + WebSocket |
| Shared types | `shared/src/types/` | Tournament, Player, Timer, Sync, API types |
| Development guidance | `CLAUDE.md` | Constitution, standards, workflows |

## CONVENTIONS

### Database Prefixing
- **Controller/mobile**: `tdwp_` prefix always (SQLite)
- **WordPress**: Mixed - uses `wp_` for standard tables, `tdwp_` for custom (documented as "always tdwp_")
- **CRITICAL**: Version mismatch - plugin header says 3.6.5, constant defines 3.6.3

### PHP (WordPress Plugin)
- No namespaces (WordPress standard)
- Class naming: `Class_Name` format
- Singleton pattern for main plugin class
- Hook prefixes: `tdwp_` for custom hooks
- Never use `_` prefix for variables (WordPress internal use only)
- PHP 8.2+ compatibility: Explicit property declarations required

### TypeScript (Controller/Mobile/Shared)
- **All projects**: Strict mode enabled (`strict: true`)
- **Path aliases**: `@shared/*` (mobile, controller), `@/*` (websites)
- **Line width**: 100 characters (unusual but consistent)
- **Formatting**: Prettier with semicolons, single quotes, ES5 trailing commas
- **Linting**: ESLint with `@typescript-eslint/recommended-requiring-type-checking`

### Code Quality
- **Root config**: `.eslintrc.js`, `.prettierrc.js` applies to all TS projects
- **No monorepo manager**: Manual shared linking via tsconfig path aliases
- **Testing**: Minimal infrastructure - only continuity kit has proper pytest setup

### Release Process (WordPress Plugin)
1. Update version in poker-tournament-import.php header (line 6)
2. Update POKER_TOURNAMENT_IMPORT_VERSION constant (line ~23) - MUST MATCH
3. Test changes thoroughly
4. Create zip: `wordpress-plugin/poker-tournament-import-vX.X.X.zip`
5. PHP syntax verification: `php -l` on changed files

### Mobile App State Management
- **Zustand stores**: tournamentStore, timerStore, blindScheduleStore, playerStore, userStore, syncStore
- **Navigation**: Tab-based (Expo Router)
- **Offline first**: AsyncStorage cache, sync queue, conflict resolution

### Controller API Architecture
- **No ORM**: Direct Better SQLite3 usage with repository pattern
- **Routes**: tournaments, timer, players, blindSchedules, export, health, import, sync
- **Real-time**: WebSocket endpoint `/ws` with level change broadcasting (<1s requirement)
- **Auth**: JWT with bcrypt password hashing

## ANTI-PATTERNS (THIS PROJECT)

### CRITICAL DO NOT:
- **DO NOT USE** legacy ranking methods in `class-parser-legacy-ranking.php` (marked DEPRECATED v2.8.0)
- **NEVER use** `as any`, `@ts-ignore`, `@ts-expect-error` - fix types properly
- **NEVER commit** changes without explicit user request
- **NEVER disable** features without explicit instruction

### CRITICAL ALWAYS:
- **ALWAYS log to error_log** in class-parser.php line 1705 - critical debugging even when debug mode off
- **ALWAYS send level changes immediately** via WebSocket - bypass throttling for US3-A2 <1s latency
- **ALWAYS create updated installer zip** for WordPress version releases

### DEPRECATED:
- **Legacy ranking methods** - `class-parser-legacy-ranking.php`, will be removed in v2.9.0
- **Duplicate type files** - `shared/src/types/player.d.ts` alongside `player.ts` (use `.ts` only)

### NEVER VIOLATE:
- **Database prefix inconsistency** - Document says "tdwp_ always", but WordPress uses both wp_ and tdwp_
- **Version mismatch** - Header (3.6.5) vs constant (3.6.3) causes deployment issues

## UNIQUE STYLES

### WordPress Plugin
- **No build process** - PHP interpreted directly
- **AJAX handlers** prefixed: `wp_ajax_tdwp_*`, `wp_ajax_nopriv_tdwp_*`
- **Formula system** - Stored in WordPress options, real-time validation via AJAX
- **Statistics engine** - Auto-refreshes on plugin update, data mart tables for performance
- **Shortcodes** - 20+ display shortcodes for frontend integration

### Monorepo Without Manager
- **Manual linking**: `shared/` compiled to dist/, imported via `@shared/*` path alias
- **No workspaces**: No npm/yarn workspaces, Lerna, or Nx
- **Version drift**: Different React versions across projects (mobile 19.1.0, websites 18.3.0)

### Spec-Driven Development
- **14 feature specs**: Each in `specs/00X-feature-name/` with research.md, plan.md, tasks.md, checklists/
- **Specify system**: `.specify/` with bash scripts for planning workflows

## COMMANDS
```bash
# WordPress plugin
php -l <file>                  # Verify PHP syntax
zip -r poker-tournament-import-vX.X.X.zip wordpress-plugin/poker-tournament-import/

# Controller
npm run dev                         # Watch mode (tsx)
npm run build                       # TypeScript + tsc-alias + copy migrations
npm start                            # Production server
npm test                             # Jest (no tests configured yet)

# Mobile app
npm start                            # Expo dev server
npm run android/ios/web             # Platform builds

# Shared library
npm run build                       # TypeScript compilation
npm run watch                       # Watch mode

# Websites
npm run dev                          # Next.js dev server
npm run build                        # Production build
```

## NOTES

### WP Shell Access
WordPress local environment: `/Users/hkh/Library/Application Support/Local/ssh-entry/hNPsf2SE_.sh`

### Version Tracking
ALWAYS update version number in log output so we know which patch level we're logging.

### Testing Gaps
- **WordPress**: No PHPUnit configured, manual test scripts only
- **Mobile**: No Jest config, empty `__tests__/e2e/` directory
- **Controller**: Jest in package.json but no test files
- **Websites**: No testing infrastructure
- **Continuity kit**: Excellent pytest setup (other components should follow this pattern)

### Architecture Concerns
- **Multiple databases**: WordPress (MySQL), controller (SQLite), mobile (SQLite) - sync complexity
- **No CI/CD**: All builds/deployments manual
- **Duplicate websites**: `tdwp-website` and `tdwp-website-cybrancee` (same codebase)
- **Monorepo without manager**: Manual shared library linking, no dependency synchronization
- **Large PHP files**: class-shortcodes.php (257KB), class-admin.php (258KB) - high coupling

### Active Technologies
- PHP 8.0+ (8.2+ compatible) + WordPress 6.0+, MySQL 5.7+
- TypeScript 5.0+ (strict mode) + Node.js 20+ (controller)
- Expo SDK 54 + React Native 0.81.5 + React 19.1.0 (mobile)
- Fastify 5.6.2 + better-sqlite3 + WebSocket (controller)
- Zod 3.25.76 (runtime validation, all TS projects)
- Zustand 4.4 (mobile state management)
- Next.js 14.2 + React 18.3 + Tailwind CSS 3.4 (websites)
