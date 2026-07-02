# Poker Tournament Import Changelog

## Version 3.9.3 - (July 2, 2026)

### 🐛 Data integrity — duplicate tournaments & robust statistics (epic tdwp-3lg)

**Duplicate tournaments on player profiles (tdwp-48e)**
- Root cause fixed: `poker_tournament_players` was append-only on import with no cleanup on delete, so repeatedly importing and deleting the same `.tdt` left multiple copies of every participation row — showing the same tournament twice on a player profile.
- Imports are now idempotent: participation rows are deleted-then-reinserted per tournament UUID, mirroring the ROI mart.
- Tournament posts are found-and-updated by UUID instead of blindly recreated on every re-import.
- Permanently deleting a tournament now purges its rows from `poker_tournament_players`, `poker_player_roi`, and `poker_tournament_costs`.
- Player profile listings collapse duplicates (`GROUP BY tournament_id`) and count wins/final tables with `COUNT(DISTINCT tournament_id)`.

**Robust statistics recalculation (tdwp-46s)**
- New `UNIQUE(tournament_id, player_id)` index on `poker_tournament_players` makes duplicate rows structurally impossible.
- One-time upgrade migration reconciles orphaned rows, de-duplicates, enforces the index, and recalculates — automatically cleaning historical damage.
- `calculate_all_statistics()` self-heals the participation mart before aggregating, so recalculation is idempotent.

**Negative tournament points (tdwp-brj)**
- Rebuy and add-on fees are now extracted from their `.tdt` `BuyConfig` blocks (previously never set, so rebuy/add-on money was always 0). Combines with the existing buy-in inference fallback and negative-points clamp to protect the prize-pool-driven points formula.

**Data Operations menu (tdwp-7br)**
- New **Data Operations** submenu consolidates Refresh Statistics, a new **Repair Participation Mart** tool, and links to the Data Mart Cleaner and Migration Tools.
- Data Mart Cleaner moved from the Tournaments menu into the Poker Import menu.

## Version 3.9.2 - (July 1, 2026)

### 📖 Admin help overhaul

- Relocated the shortcode help to a prominent **Shortcodes & Help** entry at the top of the main Poker Import menu (previously buried under the Tournaments menu).
- Documented every shortcode (29 across Tournament, Series, Season, Player, Live Clock, Display Screen, and Dashboard/Registration categories) with accurate attributes, defaults, a Quick Reference index, and click-to-copy code blocks.
- Added **Tutorials**: step-by-step walkthroughs for importing & managing a `.tdt` file and for running a live tournament end-to-end via the Tournament Manager.
- Rebuilt the help stylesheet into one consistent, readable design system (and actually enqueue it).

### 🕐 Phase 2 Live Operations — tournament clock cluster

Public `[tournament_clock]` and live-clock gap closure (epic tdwp-871).

**Public clock display**
- Public display mode via `?screen=clock`: fullscreen + native Wake Lock (feature-detected, graceful fallback), dark full-viewport theme, and a fullscreen toggle button (tdwp-558).
- Current SB/BB/Ante and next-level preview (tdwp-7u1); average chip stack and current pot — total collected, distinct from prize pool (tdwp-ekz).
- Sound notifications: 5-minute / 1-minute / level-change / break-start cues via Web Audio, with per-level fire guards (tdwp-blc).
- Configurable elements: per-tournament logo (featured image or `logo_url`), prize payout table, and live chip-leader rankings — all opt-in, default off (tdwp-wp7).

**Break handling**
- Advancing (or skipping) onto a break level auto-starts the break with `break_until` (tdwp-nja).
- Live break countdown driven from the absolute `break_until` (no longer freezes) with a timezone-correct "Back at HH:MM" rendered in the viewer's local time.

**Reliability & control**
- Server-side wall-clock tick: elapsed is computed from `updated_at`, removing the 30-second cap that accumulated clock drift over long events (tdwp-rhp).
- Skip-to-specific-level operator control with a nonce/capability-checked AJAX endpoint (tdwp-zh6).
- Connection-lost overlay when heartbeats stop for 3× the interval (tdwp-o12); cross-tab state sync within ~1s via BroadcastChannel (tdwp-32g).

**Fixes**
- Robust fresh-activation template seeding: empty template tables now self-heal (tdwp-luk).
- Removed stray `hook-debug.txt` writes on every admin page load (tdwp-he6).

## Version 3.8.0 - (June 30, 2026)

### 🏁 Phase 1 Foundation gap closure complete (epic tdwp-cma)

Closes the remaining P2/P3 gaps from the PRD reconciliation (PRs #36–#46), building on the P1 work shipped in 3.7.0.

**Prizes**
- Rounding denominations ($1/$5/$10) on payouts; top-heavy (65/25/10) and flat suggestion styles; configurable minimum payout floor (deterministic bottom-up trim); custom per-player chop with sum validation (tdwp-cma.16/.18/.19/.21).
- Over-allocation guard (locked+fixed never produce negative payouts) + live remaining-pool display; "Chopped" badge on public prize display (tdwp-cma.24/.25).

**Blinds**
- Schedule preview total duration + average-stack-at-start; Hyper Turbo built-in template; apply-template-to-tournament; blind-schedule CSV import; print-friendly schedule view (tdwp-cma.10/.11/.12/.14).
- Blind-schedule linkage written to both meta and the canonical template column (tdwp-cma.26). Fixed break-duration render field mismatch (tdwp-3lg.3).

**Players**
- Registrant confirmation email, capacity waiting list (`waitlist_position`) with admin promote, available-seats display (tdwp-cma.2/.3/.4).
- Duplicate-player merge (UUID-keyed re-pointing) + admin UI, player-database CSV export, buy-in/seat CSV import columns (tdwp-cma.1/.6/.7).
- Late-registration config, copy roster from a previous tournament, print-friendly roster (tdwp-cma.5/.8/.9).

**Wizard / Templates**
- Fee split (entry fee / prize-pool contribution), flat-rake mode, rebuy/add-on timing fields, live pot estimator + financial summary panel (tdwp-vf9; DB_VERSION 3.6.0).
- Use-Template flow into the creation wizard (tdwp-l2l).
- 30s auto-save with drafts + crash recovery; tournament history log (reusing `tdwp_tournament_events`) with a details-view history panel and created/modified/started/completed events (tdwp-67j, tdwp-cma.28).

**Quality**: full offline PHPUnit suite green at 322 tests / 1072 assertions; every PR CI-verified before merge.

## Version 3.7.0 - (June 30, 2026)

### 🏗️ Phase 1 gap closure (epic tdwp-cma) — Prizes & Blinds

- **Prizes — built-in templates (tdwp-cma.20)**: four PRD §4.3 prize-structure templates (Standard 3-Way, Standard 5-Way, Flat 9-Way, Top Heavy) are now seeded on activation, with a back-fill guard for existing installs.
- **Prizes — place-count suggestion (tdwp-cma.17)**: `recommend_place_count()` / `generate_suggested_structure()` compute paid places from player count per PRD tiers (1-20→3, 21-50→5, 51-100→9, 100+→~15%).
- **Prizes — richer per-place model (tdwp-cma.15)**: each place gains a fixed dollar amount, a lock flag (frozen against recalculation), a recipient override, and a public-display flag; the calculator pays locked/fixed places first then distributes the remainder across unlocked percentage places (backward-compatible with old `{place,percentage}` structures).
- **Prizes — chop integration (tdwp-cma.22, tdwp-cma.23)**: applied chops are recorded to the tournament event log (`prize_chop`) and `_tdwp_chop_applied` post meta; a nonce/capability-checked AJAX path applies a chop to a specific live tournament, gated on ≥2 active players.
- **Blinds — schedule suggestion (tdwp-cma.13)**: a pure-logic `suggest_schedule()` generates a draft blind ladder (blinds ~double every 4–6 levels, breaks every 60–90 min, total within ±15 min of the target) from starting chips / players / desired duration / style, exposed via a secured AJAX endpoint and a builder UI panel.
- **Follow-ups filed**: locked-place UX polish (tdwp-cma.24), chopped-prize display badge (tdwp-cma.25). Two pre-existing blind-builder defects this work surfaced are tracked as P0 (tdwp-3lg.3 break-field mismatch, tdwp-3lg.4 hardcoded level duration).
- **Tests**: full offline PHPUnit suite green at 126 tests / 479 assertions.

## Version 3.6.10 - (June 30, 2026)

### 🛠️ Data integrity: ROI mart dedup + ownership; live buy-in column (tdwp-ayg b+c)

- **Fixed `poker_player_roi` duplication (part b)**: the table has no unique key, so `$wpdb->replace()` never deduped — every re-import or migration appended duplicate ROI rows (inflating leaderboards/top-players). `process_player_roi_data()` now **delete-then-inserts** per tournament (idempotent), and `migrate_populate_roi_table()` **clears then rebuilds** (no dups by construction).
- **Fixed undefined `get_the_gmdate()` in the ROI rebuild**: `migrate_populate_roi_table()` called a non-existent function on the no-`_tournament_date` path (a latent fatal); now uses `get_post_time()` with a safe fallback.
- **Added missing `tdwp_tournament_live_state.buyin_amount` column (part c)**: the TDT exporter read this column although it never existed (latent bug). Added via `dbDelta` (new + existing installs) and made the exporter read null-safe.
- **Deferred (tdwp-eil)**: having `calculate_all_statistics()` own a *blanket* ROI rebuild is intentionally not done yet — the rebuild resolves buy-in from post-meta the live/bridge path never writes, so it would be lossy for live tournaments. It pairs with the template-aware buy-in model. ROI correctness today is maintained incrementally (delete-then-insert per tournament on both import and live paths).
- **Scope note**: the larger Option C schema consolidation (collapsing the dual `tdwp_*`/`poker_*` player stores) is split to its own bead for a dedicated, validated migration.

## Version 3.6.9 - (June 30, 2026)

### 🧪 Quality: docs fix, CI lint/PHPCS, parser/formula/stats tests

- **Docs (tdwp-x13)**: corrected the CLAUDE.md table-prefix contradiction. Documented the canonical **two-prefix** reality — `poker_*` (import/statistics subsystem) and `tdwp_*` (TD3 live tournament manager) — replacing the inaccurate "prefix is tdwp_ always" note.
- **CI (tdwp-cs7)**: broadened the mandatory `php -l` gate to all plugin PHP (incl. `templates/`), and added a non-blocking PHPCS (WordPress Coding Standards) step with a curated `phpcs.xml.dist` ruleset and a self-bootstrapping `run-phpcs.sh`.
- **Tests (tdwp-5n7)**: added offline PHPUnit coverage for the formula validator (arithmetic, precedence, functions, the n/r/hits variables), the `.tdt` parser (valid structure, malformed/empty/truncated/non-tdt error handling, real-export parse), and the statistics engine (singleton, safe accessors). Suite now 67 tests / 175 assertions.
- **Cleanup**: removed unconditional debug `error_log()` spam from the winnings calculation (`class-parser.php`) and the `round()` formula function (`class-formula-validator.php`) — these logged on every parse/calc regardless of debug mode. Remaining diagnostic spam tracked as a follow-up.

## Version 3.6.8 - (June 29, 2026)

### 🔒 Security: rate-limiting + authenticated-handler audit

- **Per-IP rate-limiting on public player registration (tdwp-hk3)**: `ajax_register_player` (nopriv) now limits registrations per client IP (default 5/hour, filterable) via `TDWP_Ajax_Guards::is_rate_limited()`, keyed on a validated `REMOTE_ADDR` (proxy headers are not trusted). Blunts spam without blocking other users.
- **Authenticated-handler audit (tdwp-0rr)**: scanned the ~100 authenticated `wp_ajax` handlers. The live-control surface is `verify_request()`-guarded (31 calls); destructive data-mart cleaners all verify nonce + `manage_options`; no user input flows into raw SQL (all interpolation is internal table names). **Fixed one gap**: `ajax_reconstruct_chronology` verified a (public, frontend-shared) nonce but no capability — now requires `manage_options` before rewriting tournament rows.

### 📐 Decision
- **ADR 0001 (tdwp-3mm, Proposed)**: recommend ratifying admin-ajax as the plugin's interface and deferring the REST API to tdwp-9pi (built with the mobile controller). See `docs/adr/0001-api-interface-ajax-vs-rest.md`.

## Version 3.6.7 - (June 29, 2026)

### 🔒 Security: nopriv AJAX surface reduction + audit

- **Dropped pointless `nopriv` registrations (tdwp-zsn)**: `tdwp_frontend_import_tournament` and `tdwp_frontend_refresh_statistics` now require `edit_posts` / `manage_options` respectively, so their unauthenticated (`nopriv`) registrations only routed anonymous traffic to a handler that rejects it. Removing them keeps unauthenticated requests from reaching these handlers at all. The genuinely public reads (series/tournament/clock/player display, screen unregister) keep `nopriv`.
- **nopriv handler security audit (tdwp-vyz)**: audited all 11 unauthenticated handlers across `nonce / capability / input sanitization / output escaping / $wpdb->prepare`. Result: every nopriv handler verifies a nonce, parameterizes all SQL via `$wpdb->prepare`, escapes output, and validates input. No CRITICAL/HIGH gaps. One LOW (`ajax_register_player` lacks per-IP rate-limiting) tracked as follow-up.

## Version 3.6.6 - (June 29, 2026)

### 🔒 Security: AJAX / Userland Hardening

Hardened the frontend admin-ajax surface. No change to legitimate user flows.

#### Fixed
- **IDOR in `tdwp_unregister_screen` (tdwp-bxp)**: an unauthenticated visitor could mark *any* display screen offline by iterating `screen_id` with the shared display nonce. The nonce is now **scoped to the specific screen** (`tdwp_unregister_screen_{id}`), so a kiosk can only unregister the screen it is actually displaying. The display page resolves its endpoint slug to the real `screen_id` so the per-screen nonce and the unregister-on-unload flow target the served screen.
- **DoS in `tdwp_frontend_refresh_statistics` (tdwp-cdq)**: the endpoint ran a full synchronous data-mart rebuild gated only on being logged in. It now requires `manage_options` and is rate-limited by a throttle transient (nonce → capability → throttle → rebuild).
- **Import authorization & validation in `tdwp_frontend_import_tournament` (tdwp-gwp)**: now requires the `edit_posts` capability (matching the import UI), enforces a 5 MB size cap, and content-sniffs the upload (rejects binary payloads renamed to `.tdt`) before parsing.
- **Removed debug code (tdwp-uee, tdwp-kws)**: deleted the `tdwp_ajax_test` debug handler and its registrations, the `admin_init` request-logging listener, and the `error_log(print_r($_REQUEST/$_FILES))` / `[TDWP Beta]` debug dumps that leaked request data into logs.

#### Internal
- New `TDWP_Ajax_Guards` (`includes/security/class-ajax-guards.php`) centralizes the security primitives (screen-scoped nonce action, upload content sniff, throttle) so they are unit-testable.
- Offline PHPUnit suite extended with 15 AJAX-security tests (37 tests / 81 assertions total, no database required).

## Version 3.6.5 - (January 6, 2026)

### 🐛 Bug Fix: Frontend Tournament Import jQuery Loading

#### ✅ Fixed Script Loading Timing Issue
- **[tdwp_tournament_import] Shortcode Fix**: File upload now works correctly with proper jQuery dependency management
- **Root Cause**: Inline `<script>` executed before jQuery loaded (timing issue)
- **Solution**: Use `wp_add_inline_script()` with proper jQuery dependency declaration
- **Pattern**: Follows same approach as `poker_dashboard_shortcode()` (line 2629)

#### 🎯 What Was Fixed
- **Before**: `Uncaught TypeError: Cannot read properties of undefined (reading 'ajax')`
- **After**: AJAX file upload works, tournaments import successfully from frontend
- **Technical Fix**:
  - Replaced direct `<script>` output with `wp_add_inline_script()`
  - Added `wp_enqueue_script()` with jQuery dependency array
  - Used `wp_localize_script()` to pass data from PHP to JavaScript
  - Wrapped JavaScript in `jQuery(document).ready()` for safety

#### 📝 Technical Details
- **File Modified**: `includes/class-shortcodes.php` (tournament_import_shortcode method)
- **Lines Changed**: ~5361-5459 (removed broken enqueue, added proper script handling)
- **Changes**:
  - Removed: `wp_enqueue_script('jquery')` (line 5363)
  - Removed: Direct `<script>` tag output (lines 5418-5492)
  - Added: Proper script enqueueing with jQuery dependency
  - Added: `wp_localize_script()` for data passing
  - Added: `wp_add_inline_script()` for JavaScript code
- **Impact**: Low risk, follows WordPress best practices

#### 🧪 Testing
- Verified PHP syntax validation
- Manual browser testing required (see testing checklist below)
- Cross-browser compatibility: Chrome, Firefox, Safari
- Cross-theme testing: Twenty Twenty-Four and custom themes

---

## Version 3.6.4 - (January 6, 2026)

### 🐛 Bug Fix: Frontend Tournament Import Shortcode

#### ✅ Fixed jQuery Dependency
- **[tdwp_tournament_import] Shortcode Fix**: File upload functionality now works correctly on frontend pages
- **Root Cause**: jQuery not enqueued on frontend, causing `$ is not defined` JavaScript error
- **Solution**: Added `wp_enqueue_script('jquery')` in `tournament_import_shortcode()` after permission checks
- **Pattern**: Follows same approach as `poker_dashboard_shortcode()` (line 2629)

#### 🎯 What Was Fixed
- **Before**: Upload button did nothing, browser console showed `$ is not defined` error
- **After**: AJAX file upload works, tournaments import successfully from frontend
- **Security**: jQuery only loaded for authorized users with `edit_posts` capability

#### 📝 Technical Details
- **File Modified**: `includes/class-shortcodes.php` (line 5363)
- **Change**: Single function call to enqueue jQuery
- **Impact**: Minimal risk, follows existing WordPress best practices
- **Compatibility**: Works with all WordPress themes that don't deregister jQuery

#### 🧪 Testing
- Verified PHP syntax validation
- Manual browser testing required (see tasks.md T009-T031)
- Cross-browser compatibility: Chrome, Firefox, Safari
- Cross-theme testing: Twenty Twenty-Four and custom themes

---

## Version 3.6.1 - (January 4, 2026)

### 🎯 Enhancement: Season Leaderboard Detailed View

#### ✅ Always Show All Players
- **[season_leaderboard] Shortcode Enhancement**: When `show_details="true"` is used, display ALL players in a season regardless of the `limit` parameter
- **Complete Season Visibility**: Administrators can now see full season standings with all tie-breaker statistics
- **Simplified Behavior**: Removed conditional check that respected limit parameter in detailed views

#### 📝 Behavior Changes
- **Before**: `[season_leaderboard show_details="true" limit="10"]` showed only 10 players
- **After**: `[season_leaderboard show_details="true" limit="10"]` shows ALL players (limit is ignored)
- **Standard View**: Unchanged - continues to respect limit parameter for top N players

#### 🎨 Detailed Statistics
When `show_details="true"`, displays for ALL players:
- Rank, Player Name, Points
- Played, Best, Average finishes
- 1st, Top 3, Top 5 finishes
- Bubble and Last place finishes
- Total Hits (per formula)

#### ⚡ Performance
- Handles seasons with 200+ players efficiently
- Page load time under 5 seconds for large seasons
- Transient caching ensures fast subsequent loads

#### ⚠️ Breaking Change
- `show_details="true" limit="N"` now ignores the limit parameter
- Use `show_details="false"` for limited views

---

## Version 1.8.0 - (October 12, 2025)

### 🚀 MAJOR NEW FEATURE: MODERN DASHBOARD SYSTEM

#### ✅ Complete Dashboard Implementation
- **[poker_dashboard] Shortcode**: Modern, responsive dashboard with comprehensive tournament analytics
- **Tabbed Navigation System**: Overview, Tournaments, Players, Series, and Analytics tabs
- **Real-Time Data Aggregation**: Live statistics with efficient database queries
- **Professional Design**: Clean, modern interface with card-based layout and smooth animations

#### 📊 Dashboard Overview Features
- **Key Statistics Cards**: Total tournaments, unique players, prize pools, active series with trend indicators
- **Recent Tournaments**: Last 10 tournaments with winner information and quick navigation
- **Top Players Leaderboard**: Best performers with achievement badges and performance metrics
- **Active Series Progress**: Current tournament series with visual progress indicators
- **Quick Actions Panel**: Import tournaments, create series, view calendar, generate reports

#### 🔍 Advanced Drill-Through Navigation
- **Clickable Elements**: Every stat card, tournament, player, and series is clickable for detailed views
- **AJAX-Powered Navigation**: Smooth content loading without page refreshes
- **Dynamic Content Loading**: Tab-specific content loads on-demand with loading indicators
- **Detailed Views**: Comprehensive tournament, player, and series analytics
- **Breadcrumb Trail**: Clear navigation path for user orientation

#### 📈 Analytics & Reporting
- **Tournament Analytics**: Prize pool distribution charts and participation trends
- **Player Performance**: Complete leaderboard with filtering and sorting
- **Series Management**: Overview of all tournament series with statistics
- **CSV Report Generation**: One-click export of comprehensive tournament data
- **Visual Data Presentation**: Charts, progress bars, and achievement badges

#### 🎨 Professional User Interface
- **Responsive Design**: Optimized for desktop, tablet, and mobile devices
- **Modern Styling**: Clean, professional appearance with consistent color scheme
- **Interactive Elements**: Hover effects, transitions, and micro-animations
- **Loading States**: Professional loading indicators and error handling
- **Accessibility**: Proper contrast ratios and keyboard navigation support

#### 🛠️ Technical Architecture
- **Efficient Database Queries**: Optimized aggregation queries with proper indexing
- **AJAX Framework**: Comprehensive AJAX system with nonce security
- **Modular Shortcode System**: Clean separation of dashboard components
- **Error Handling**: Robust error states and fallback content
- **Performance Optimized**: Lazy loading and efficient data retrieval

#### 🎯 Dashboard Components
- **Statistics Grid**: Four primary metrics cards with drill-through capability
- **Content Grid**: Flexible grid layout for tournaments, players, and series
- **Tab System**: Five-tab navigation with dynamic content loading
- **Quick Actions**: Centralized action buttons for common tasks
- **Export System**: CSV report generation with automatic file downloads

#### 📱 User Experience Enhancements
- **Intuitive Navigation**: Tab-based interface with clear visual hierarchy
- **One-Click Access**: Direct navigation to detailed views from any dashboard element
- **Real-Time Updates**: Auto-refresh functionality for live data monitoring
- **Visual Feedback**: Loading states, hover effects, and success/error indicators
- **Mobile Optimized**: Touch-friendly interface optimized for all screen sizes

#### 🔧 AJAX Implementation
- **Dynamic Content Loading**: All tabs load content via AJAX for smooth navigation
- **Detailed Views**: Specialized AJAX handlers for drill-through navigation
- **Report Generation**: AJAX-powered CSV export with download functionality
- **Error Handling**: Comprehensive error states with retry mechanisms
- **Security**: Full nonce verification and permission checking

#### 📊 Data Visualization
- **Progress Indicators**: Visual progress bars for series completion
- **Achievement Badges**: Gold, silver, bronze medals for top performers
- **Chart Elements**: Bar charts for prize pool distribution
- **Status Cards**: Color-coded status indicators with trend information
- **Performance Metrics**: Visual representations of player and tournament statistics

#### 🎛️ Dashboard Usage
- **Simple Implementation**: Just add `[poker_dashboard]` to any page or post
- **Customizable Options**: Parameters for view type, limits, and feature toggles
- **Auto-Configuration**: Dashboard automatically detects and displays available data
- **Integration Ready**: Seamlessly integrates with existing tournament data
- **Shortcode Compatibility**: Works alongside all existing shortcodes

#### 🚀 Performance Features
- **Efficient Queries**: Optimized database queries for fast dashboard loading
- **Caching Ready**: Structure prepared for future caching implementations
- **Lazy Loading**: Progressive content loading for large datasets
- **Memory Efficient**: Optimized data retrieval to minimize server load
- **Scalable Design**: Ready for large-scale tournament deployments

---

## Version 1.7.6 - (October 12, 2025)

### 🎯 CRITICAL DATA RETRIEVAL FIX - COMPLETE

#### ✅ Field Name Mismatch Resolution
- **Root Cause Fixed**: Systematically corrected `_tournament_uuid` → `tournament_uuid` across all display functions
- **Comprehensive Fix**: Updated shortcodes, templates, and all display components
- **Data Access Restored**: All functions now correctly read tournament UUIDs
- **Complete Coverage**: Fixed every occurrence across the entire codebase

#### 🔍 Files Updated
- **class-shortcodes.php**: All tournament UUID references corrected
- **archive-tournament.php**: Tournament listing template fixed
- **single-tournament.php**: Individual tournament page template fixed
- **single-player.php**: Player profile template fixed
- **Comprehensive Audit**: Every display function now uses correct field names

#### 🛠️ Technical Changes
- **Search and Replace**: Systematic field name correction across all display functions
- **Template Updates**: All template files updated for consistent UUID access
- **Function Integrity**: Database queries now match actual database schema
- **No Data Loss**: All existing tournament data remains intact

#### 🎉 Expected Results
- **All Shortcodes Working**: Tournament results, player profiles, series/season displays
- **Tournament Pages Active**: Individual tournament pages should show complete data
- **Statistics Calculating**: Player statistics and rankings should display
- **Tab Interface Functional**: All tabs should load and display content correctly
- **Complete Recovery**: All tournament-related functionality restored

#### 🚀 Foundation for Dashboard
- **Data Layer Fixed**: All data retrieval functions now working correctly
- **Ready for Phase 2**: Solid foundation established for dashboard implementation
- **Backward Compatible**: All existing functionality preserved
- **Performance Ready**: Optimized queries ready for dashboard enhancements

---

## Version 1.7.5 - (October 12, 2025)

### 🎯 CRITICAL SYNC FIX RESOLVED

#### ✅ UUID Field Name Issue Fixed
- **Root Cause Identified**: Sync function looked for `_tournament_uuid` but data stored as `tournament_uuid`
- **Field Mapping Corrected**: Fixed UUID field name mismatch in sync function
- **Data Access Restored**: Sync function now correctly reads tournament UUIDs
- **Player Data Sync**: Tournament player data should now sync successfully to database

#### 🔍 Diagnostic Success
- **Issue Pinpointed**: Diagnostic system successfully identified the exact problem
- **Field Mapping Discovered**: Revealed tournaments have correct data but wrong field names in sync logic
- **Targeted Fix Applied**: Precise fix for the UUID field name mismatch
- **Immediate Resolution**: This single change should resolve all sync issues

#### 🛠️ Technical Fix Applied
- **Sync Function Updated**: Changed `get_post_meta($tid, '_tournament_uuid', true)` to `get_post_meta($tid, 'tournament_uuid', true)`
- **Data Structure Alignment**: Sync function now matches actual database schema
- **No Data Loss**: All existing tournament data remains intact
- **Backward Compatible**: Fix doesn't affect other functionality

#### 🎉 Expected Results
- **Sync Success**: Both tournaments (67, 69) should now sync successfully
- **Player Statistics**: Player stats and tournament displays should start working
- **Tab Functionality**: Tab interface should display content properly
- **Complete Recovery**: All tournament-related functionality should be restored

---

## Version 1.7.4 - (October 12, 2025)

### 🔍 ENHANCED DIAGNOSTICS & TROUBLESHOOTING

#### 🩺 Comprehensive Diagnostic System
- **Advanced Sync Analysis**: Added detailed diagnostics for tournament sync operations
- **Meta Field Inspection**: Complete visibility into tournament meta data structure
- **Data Location Detection**: Automatically searches for player data in alternative meta fields
- **Visual Status Indicators**: Clear visual feedback on data availability and sync status

#### 📊 Diagnostic Dashboard
- **Tournament Status Table**: Visual table showing data status for all tournaments
- **Meta Key Display**: Shows all available meta keys for each tournament
- **Data Presence Indicators**: ✓/✗ indicators for tournament data and UUID presence
- **Player Count Display**: Shows number of players detected in each tournament

#### 🔧 Troubleshooting Enhancements
- **Alternative Data Detection**: Searches for player data in non-standard meta fields
- **Comprehensive Logging**: Detailed debug information for sync operations
- **Error Context**: Enhanced error messages with specific tournament details
- **Data Structure Analysis**: Reveals how tournament data is actually stored

#### 🎯 Diagnostic Features
- **Tournament ID Mapping**: Clear mapping between tournament IDs and titles
- **Data Validation**: Validates presence of required fields for sync operations
- **Meta Field Inventory**: Complete list of all meta fields stored for each tournament
- **Sync Status Tracking**: Detailed status of each tournament during sync process

#### 🛠️ Technical Improvements
- **Enhanced Debug Logging**: Comprehensive logging throughout sync process
- **Alternative Field Search**: Searches for player-related meta fields automatically
- **Visual Feedback Table**: Color-coded status indicators in diagnostic results
- **Error Recovery**: Better handling of missing or incomplete tournament data

#### 📈 User Experience
- **Clear Status Display**: Easy-to-understand visual indicators
- **Detailed Information**: Comprehensive diagnostic data for troubleshooting
- **Actionable Insights**: Clear indication of what data is missing or needs attention
- **Professional Interface**: Clean, organized diagnostic presentation

---

## Version 1.7.3 - (October 12, 2025)

### 🐛 CRITICAL BUG FIXES

#### 🚨 Division by Zero Error Fix
- **Parser Error Fixed**: Resolved critical division by zero error during tournament import
- **Edge Case Handling**: Added protection for tournaments with zero buyins
- **Import Recovery**: Tournament imports now complete successfully without fatal errors
- **Data Integrity**: Ensured all calculations handle edge cases properly

#### 🔄 Data Sync Field Mismatch Resolution
- **Field Mapping Fixed**: Corrected field name mismatches between parser and sync functions
- **Data Structure**: Properly handled `finish_position` vs `finishPosition` field naming
- **Buyins Calculation**: Fixed buyins, rebuys, and addons calculation from buyin arrays
- **Debug Logging**: Added comprehensive logging for sync troubleshooting

#### 🔧 Technical Improvements
- **Safe Division**: Added conditional checks before division operations
- **Parser Robustness**: Enhanced error handling for malformed tournament data
- **Sync Intelligence**: Improved field detection and mapping logic
- **Debug Support**: Added detailed logging for sync operation diagnostics

#### 🎯 Resolved Issues
- **Import Fatal Errors**: Tournament imports no longer crash with division by zero errors
- **Sync Functionality**: Player data synchronization now works correctly with proper field mapping
- **Data Consistency**: Ensured consistent field naming across all components
- **Error Prevention**: Prevented calculation errors when tournaments have no buyins

#### 🏗️ Enhanced Data Processing
- **Smart Field Detection**: Automatically detects and maps field variations
- **Profile Analysis**: Intelligent detection of rebuy and addon types from buyin profiles
- **Error Resilience**: Graceful handling of incomplete or malformed data
- **Validation**: Enhanced data validation during sync operations

---

## Version 1.7.2 - (October 12, 2025)

### 🐛 MAJOR CRITICAL FIXES

#### 🚨 Data Storage Disconnect Resolution
- **CRITICAL FIX**: Resolved fundamental data architecture issue preventing tournament displays
- **Database Sync**: Created comprehensive player data synchronization system
- **Data Bridge**: Built bridge between tournament_data meta fields and poker_tournament_players table
- **Display Recovery**: Fixed empty tournament pages and tab interface functionality
- **Root Cause**: Addressed core issue where parser stored data differently than display functions expected

#### 🛠️ Enhanced Migration Tools
- **Player Data Sync**: Added "Sync All Player Data" functionality to migration tools
- **Bulk Repair**: One-click synchronization of all tournament player data
- **Error Handling**: Comprehensive error reporting and progress tracking
- **Completion Flow**: Fixed migration tool completion with proper redirects and feedback
- **Smart Detection**: Automatic detection of tournaments needing data synchronization

#### 🔧 Technical Improvements
- **Data Integrity**: Robust validation and error handling during sync operations
- **Performance**: Efficient database operations with proper cleanup and indexing
- **User Experience**: Clear feedback and progress indicators for sync operations
- **Debug Support**: Enhanced logging for troubleshooting sync issues
- **Fallback Logic**: Graceful handling of missing or corrupted tournament data

#### 🎯 Fixed Issues
- **Empty Tournament Pages**: Individual tournaments now display complete player data
- **Tab Interface**: Series/Season tabs now load and display content correctly
- **Player Statistics**: Player rankings and statistics now calculate properly
- **Migration Completion**: Migration tools now complete successfully with proper feedback
- **Data Consistency**: Ensured data consistency across all display functions

#### 🏗️ Architecture Enhancement
- **Unified Data**: Single source of truth for tournament player data
- **Scalable Design**: Prepared for future enhancements and additional data types
- **Maintainable**: Clean separation between data import and display logic
- **Extensible**: Easy to add new synchronization features in the future

---

## Version 1.7.1 - (October 12, 2025)

### 🐛 BUG FIXES

#### Critical Shortcode Error Fix
- **Fixed Fatal Error**: Resolved `number_format()` TypeError in shortcode displays
- **Type Safety**: Added proper float conversion for all numeric display functions
- **Error Prevention**: Added null checks and default values for tournament data
- **Display Stability**: Ensured all prize pool and player count displays work correctly

#### Technical Details
- **Shortcode Safety**: Fixed `number_format(floatval($prize_pool ?: 0), 0)` in all display contexts
- **Data Validation**: Added type conversion for all tournament numeric fields
- **Backward Compatibility**: Maintained existing functionality while adding safety
- **Comprehensive Fix**: Applied to all shortcode displays including recent tournaments, series, and season views

#### Affected Components
- **Season Overview Shortcode**: Fixed prize pool display in recent tournaments
- **Series Overview Shortcode**: Fixed numeric value formatting throughout
- **Tournament Results Display**: Ensured proper number formatting in all contexts
- **AJAX Load More**: Fixed dynamic content loading with proper type handling

---

## Version 1.7.0 - (October 12, 2025)

### 🔧 CRITICAL ISSUE RESOLVED

#### ✅ Tournament-Series-Season Relationships Fix
- **COMPLETE FIX**: Fully resolved tournament-series-season relationships issue
- **Taxonomy Integration**: Fixed auto-categorization during tournament import process
- **Migration Tools**: Created comprehensive migration system for existing tournaments
- **Data Integrity**: Added verification tools for relationship integrity

#### 🛠️ Migration Tools Implementation
- **Bulk Migration**: One-click migration of tournaments missing series/season relationships
- **Admin Interface**: New "Migration Tools" admin page with status dashboard
- **Verification System**: Tools to check data integrity and find orphaned relationships
- **Error Handling**: Comprehensive error reporting and logging for migration operations
- **Responsive Design**: Mobile-friendly admin interface with visual status indicators

#### 📊 Migration Dashboard Features
- **Migration Status**: Real-time counts of tournaments needing migration
- **Visual Indicators**: Color-coded status cards (attention needed vs. good status)
- **Bulk Actions**: Single-click migration with confirmation dialogs
- **Verification Results**: Detailed reports on relationship integrity
- **Orphaned Data Detection**: Identification of broken relationships

#### 🏗️ Technical Improvements
- **Parser Integration**: Tournament Director data extraction working correctly
- **Meta Field Storage**: Proper `_series_id` and `_season_id` meta field assignment
- **Taxonomy System**: Complete tournament type/format/category taxonomy implementation
- **Shortcode Compatibility**: All existing shortcodes now use proper relationship queries
- **Database Optimization**: Efficient queries for tournament-series-season relationships

#### 🔍 Data Verification Tools
- **Relationship Verification**: Check integrity of all tournament relationships
- **Orphaned Detection**: Find tournaments with broken series/season links
- **Status Reporting**: Detailed statistics on relationship completeness
- **Bulk Repair**: Automated fixing of relationship issues
- **Audit Trail**: Complete logging of migration operations

### 🚀 NEW IMPORT PROCESS

#### Enhanced Tournament Import
- **Automatic Relationships**: New tournaments automatically get proper series/season links
- **Taxonomy Assignment**: Automatic tournament type/format/category categorization
- **Error Prevention**: Prevents future relationship issues during import
- **Data Validation**: Enhanced validation for tournament data integrity
- **Debug Support**: Comprehensive logging for troubleshooting import issues

#### Admin Menu Enhancement
- **Migration Tools**: New admin submenu for data migration and verification
- **Status Dashboard**: Overview of plugin data health and migration needs
- **Action Interface**: Intuitive interface for bulk operations and verification
- **Progress Tracking**: Visual feedback during migration operations
- **Result Reporting**: Detailed success/failure reporting with error details

---

## Version 1.6.0 - (October 12, 2025)

### 🎯 MAJOR NEW FEATURES

#### ✅ Complete Tabbed Interface System
- **Tabbed Navigation**: Professional tabbed interface for series and season pages
- **Four Main Tabs**: Overview, Results, Statistics, and Players for comprehensive data display
- **AJAX-Powered**: Dynamic content loading with smooth transitions
- **Mobile Responsive**: Optimized for all screen sizes with touch-friendly navigation

#### 📊 Overview Tab Dashboard
- **Statistics Cards**: Visual display of tournaments, unique players, total/average prize pools
- **Best Player Showcase**: Top performer with avatar, achievements, and detailed stats
- **Recent Tournaments**: Quick view of latest events with winner information
- **Professional Layout**: Grid-based design with hover effects and animations

#### 🏆 Results Tab Implementation
- **Comprehensive Tournament Tables**: Sortable listings with all tournament details
- **Pagination System**: AJAX "Load More" functionality for large datasets
- **Winner Information**: Direct links to player profiles with tournament details
- **Action Buttons**: Quick access to individual tournament pages

#### 📈 Statistics Tab Features
- **Interactive Leaderboards**: Sortable rankings with gold/silver/bronze medals
- **Player Profiles**: Avatar integration with links to detailed player pages
- **Performance Metrics**: Tournaments played, winnings, points, best/average finishes
- **Advanced Sorting**: Click-to-sort functionality on all table columns

#### 👥 Players Tab System
- **Search Functionality**: Real-time player search with visual feedback
- **Player Cards**: Professional grid layout with player statistics
- **Result Counting**: Dynamic search result counters
- **Profile Integration**: Direct links to individual player profile pages

### 🔧 TECHNICAL IMPROVEMENTS

#### Shortcode System
- **Series Shortcodes**: `[series_tabs]`, `[series_overview]`, `[series_results]`, `[series_statistics]`, `[series_players]`
- **Season Shortcodes**: `[season_tabs]`, `[season_overview]`, `[season_results]`, `[season_statistics]`, `[season_players]`
- **Modular Design**: Each tab content generated by dedicated shortcode handlers
- **Parameter Support**: Configurable options for limits, filters, and display preferences

#### Template Integration
- **Updated Series Template**: `taxonomy-tournament_series.php` with tabbed interface
- **New Season Template**: `taxonomy-tournament_season.php` with tabbed interface
- **Clean Header Design**: Professional gradient backgrounds with action buttons
- **Responsive Layout**: Mobile-first design with proper breakpoints

#### JavaScript Enhancements
- **Tab Navigation**: Complete AJAX system with loading states and error handling
- **URL Hash Support**: Direct tab linking and browser history navigation
- **Dynamic Initialization**: Content re-initialization for AJAX-loaded content
- **Search Functionality**: Real-time filtering with visual feedback
- **Table Sorting**: Interactive column sorting for all data tables

#### CSS Styling
- **700+ Lines of Professional CSS**: Comprehensive styling system
- **Mobile Responsive**: Optimized layouts for phones, tablets, and desktops
- **Interactive Elements**: Hover effects, transitions, and micro-animations
- **Accessibility**: Proper contrast ratios and keyboard navigation support
- **Modern Design**: Clean, professional appearance consistent with modern web standards

### 🔒 SECURITY & PERFORMANCE

#### Security Features
- **AJAX Nonce Verification**: All AJAX requests properly secured with WordPress nonces
- **Input Sanitization**: Comprehensive data validation and sanitization
- **Output Escaping**: All user-facing data properly escaped for security
- **Permission Checks**: Proper capability verification for sensitive operations

#### Performance Optimizations
- **Database Efficiency**: Optimized queries with proper indexing
- **Lazy Loading**: Progressive content loading for large datasets
- **Pagination**: AJAX-based pagination to reduce initial load times
- **Caching Ready**: Structure prepared for future caching implementations

### 🐛 BUG FIXES

#### Core Functionality
- **Fixed Data Display Issues**: Resolved problems with series/season pages not showing data
- **Improved Database Queries**: Enhanced query performance and accuracy
- **Template Consistency**: Unified styling and functionality across all templates
- **JavaScript Compatibility**: Resolved conflicts with other WordPress plugins

### 📱 ENHANCED USER EXPERIENCE

#### Navigation Improvements
- **Smooth Transitions**: Professional animations between tab switches
- **Loading Indicators**: Visual feedback during AJAX content loading
- **Error Handling**: Graceful error messages with retry options
- **Breadcrumb Support**: Improved navigation context for users

#### Visual Enhancements
- **Professional Typography**: Consistent font hierarchy and spacing
- **Color System**: Unified color palette with proper contrast ratios
- **Icon Integration**: Meaningful icons for better visual communication
- **Hover States**: Interactive feedback for all clickable elements

### 🏗️ DEVELOPER EXPERIENCE

#### Code Quality
- **Modular Architecture**: Clean separation of concerns across all components
- **Well-Documented**: Comprehensive inline documentation and comments
- **WordPress Standards**: Full compliance with WordPress coding standards
- **Extensible Design**: Easy to extend with additional features and integrations

#### Template System
- **Template Hierarchy**: Proper WordPress template structure
- **Reusable Components**: Modular shortcode system for easy customization
- **CSS Organization**: Well-structured CSS with logical groupings
- **JavaScript Modularity**: Clean, organized JavaScript with clear functions

---

## Version 1.5.0 - Previous Release

### Features
- Basic tournament import functionality
- Tournament Director (.tdt) file support
- Basic tournament display templates
- Player profile system
- Initial database structure

---

## Installation Instructions

1. Download the latest version from [WordPress.org](https://wordpress.org/plugins/poker-tournament-import/)
2. Upload the plugin to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under 'Settings' > 'Poker Tournament Import'

## Upgrade Instructions

1. Backup your WordPress site and database
2. Deactivate the previous version of the plugin
3. Delete the old plugin folder
4. Install the new version following the installation instructions
5. Reactivate the plugin and verify functionality

## Support

- **Documentation**: Visit the [plugin documentation](https://nikielhard.se/tdwpimport)
- **Support Forum**: Post questions on the [WordPress support forum](https://wordpress.org/support/plugin/poker-tournament-import/)
- **Issues**: Report bugs or request features through the [GitHub repository](https://github.com/nikielhard/poker-tournament-import)

---

*Last updated: October 12, 2025*