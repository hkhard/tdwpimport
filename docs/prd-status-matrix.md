# PRD ↔ Implementation Status Matrix

**Purpose (tdwp-xsa):** the single source of truth mapping each PRD acceptance
criterion to concrete implementation evidence and a status verdict. All phase
gap-work beads draw their scope from this matrix.

**Status legend:** `done` (implemented & matches PRD) · `partial` (some
sub-behaviors present) · `missing` (not implemented) · `wrong` (implemented but
contradicts the PRD) · `⬜ pending audit` (not yet verified — see the named
audit bead).

**How this fills in:** each feature area is verified by its own audit bead.
As an audit closes, its rows here move from `⬜ pending audit` to a real verdict
with `file:line` evidence. Audited areas below carry their evidence inline.

---

## Phase 1 — Foundation (epic `tdwp-cma`)

### 1.x Tournament Setup + Templates — ✅ audited (`tdwp-96a`, 2026-06-30)

PRD ref: `docs/prd-phase-1-foundation.md` §1.1–1.3.

| Criterion | Status | Evidence | Gap bead |
|-----------|--------|----------|----------|
| Buy-in with separate fee structure (entry_fee / prize_pool_contribution) | partial | single `buy_in` field — `tournament-templates-page.php:463`, `class-tournament-template.php:79`; no split | `tdwp-vf9` |
| Rebuy rules with restrictions (timing / chip threshold / per-player limit) | partial | DB cols exist `class-database-schema.php:385,1094`; not in template form `tournament-templates-page.php:509` | `tdwp-vf9` |
| Add-on configuration saves/loads (incl. timing) | partial | cost+chips captured `tournament-templates-page.php:555`; timing field absent (form + DB) | `tdwp-vf9` |
| Rake — flat-fee alternative to percentage | partial | `rake_percentage` stored; no flat-fee mode | `tdwp-vf9` |
| Pot calculation updates automatically by registered players | missing | no estimator JS in template/wizard | `tdwp-vf9` |
| Financial summary panel (total pot / rake / net prize pool) | missing | no summary panel rendered | `tdwp-vf9` |
| Templates save all tournament settings | done | `class-tournament-template.php:57–109` (`create()`) | — |
| Loading a template populates all fields | partial | `get()`/`load_relations()` `:119–143,554–580`; but no "apply template" flow from creation wizard | `tdwp-l2l` |
| Editing a template doesn't affect existing tournaments | done | update path `class-tournament-template.php:153–212`, no cascade | — |
| 3+ built-in templates out of the box | partial | 3 seeded (Turbo/Standard/Deep Stack) `class-database-schema.php:809–930`; naming differs from PRD, exactly the minimum | — |
| Template library accessible from tournament-creation screen | missing | standalone admin submenu only; no link from wizard | `tdwp-l2l` |
| Auto-save within 30s | missing | no auto-save JS/AJAX in `live-tournament-wizard.php` | `tdwp-67j` |
| Visual "Saved/Saving…" indicator | missing | none in template/wizard UI | `tdwp-67j` |
| Draft tournaments in a "Drafts" section | missing | no draft status/storage/UI | `tdwp-67j` |
| Restore unsaved changes after crash | missing | no localStorage/server draft restore | `tdwp-67j` |
| Tournament history log on tournament details | missing | no created/modified/started/completed log | `tdwp-67j` |

**Files of record:** `admin/tournament-manager/tournament-templates-page.php`,
`includes/tournament-manager/class-tournament-template.php`,
`includes/tournament-manager/class-template-engine.php` (display-token renderer —
separate concern), `includes/tournament-manager/class-database-schema.php`
(seeds built-ins), `admin/tournament-manager/live-tournament-wizard.php`.

### 1.x Blind Schedule builder + suggestions — ✅ audited (`tdwp-bo5`, 2026-06-30)

PRD ref: `docs/prd-phase-1-foundation.md §2.1–2.3`. Adversarially verified. Tally: 7 done · 4 partial · 10 missing · 0 wrong. Gaps filed under epic `tdwp-cma`; 2 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** break_length vs break_duration_minutes field name mismatch causes blank break display; Per-level duration not editable; all new levels hardcoded to 15 minutes.

| Criterion | Status | Evidence |
|-----------|--------|----------|
| §2.1 Level editor: Small blind, big blind, ante, duration, break flag fields | partial | blind-builder-page.php:802-810 — SB, BB, Ante, Break flag all present in UI. Per-level duration field is absent from both the PHP level-row template (blind-builder-page.php:834-... |
| §2.1 Add new level at any position | partial | blind-builder-page.php:789-797 — 'Add Blind Level' and 'Add Break' buttons append to end; no insert-at-position UI. Drag-drop reorder exists so workaround is possible, but the P... |
| §2.1 Edit existing levels | done | class-blind-level.php:163-218 implements update(); ajax_save_levels() (blind-builder-page.php:331-381) deletes-and-recreates all levels on save — effective edit path. |
| §2.1 Delete levels | done | class-blind-level.php:229-275 implements delete(); UI has per-row Delete button (blind-builder-page.php:843, 884, 898). |
| §2.1 Reorder levels via drag-drop | done | blind-builder-page.php:93 enqueues jquery-ui-sortable; drag handle column present (line 804); TDWP_Blind_Level::reorder() at class-blind-level.php:286-330 handles server-side pe... |
| §2.1 Visual timeline preview showing full schedule | partial | blind-builder-page.php:827-830 renders #schedule-preview div; blind-builder-admin.js:315-349 populates it with level blinds and break minutes. However there is no horizontal/gra... |
| §2.1 Total tournament duration calculation | missing | Searched blind-builder-admin.js (374 lines) for totalTime, totalDuration, duration.*sum — none found. The preview renders level blinds but never sums or displays total minutes. |
| §2.1 Average stack calculation by level | missing | Searched blind-builder-admin.js and blind-builder-page.php for avgStack, average.*stack, avg.*chip — not found anywhere in the blind builder files. |
| §2.1 AC: Break levels clearly marked visually | done | blind-builder-page.php:875 adds class 'level-break'; level-row template (line 848) uses 'level-break' class; 'BREAK' label shown in bold (line 853/879). |
| §2.1 AC: Ante field optional (defaults to 0) | done | class-blind-level.php:422 defaults ante to 0; blind-level-template in blind-builder-page.php:840 sets value=0; validation at class-blind-level.php:464 only checks ante >= 0. |
| §2.2 Template library: 4+ built-in schedule templates (Turbo, Standard, Deep Stack, Hyp... | partial | class-database-schema.php:818-900+ seeds exactly 3 templates: Turbo (10-min), Standard (15-min), Deep Stack (20-min). Hyper Turbo (5-min) template required by PRD is not seeded.... |
| §2.2 Load template into tournament | missing | No REST endpoint or admin UI found for loading a blind schedule into a specific tournament record. class-blind-schedule.php has get/list operations but no apply-to-tournament me... |
| §2.2 Save current schedule as template | done | All schedules in tdwp_blind_schedules are treated as reusable templates. TDWP_Blind_Schedule::create() at class-blind-schedule.php:72 and the admin form allow saving any schedul... |
| §2.2 Edit templates without affecting existing tournaments | done | Schedules are stored independently; editing a schedule via TDWP_Blind_Schedule::update() does not cascade to tournament records — no foreign-key cascade found in class-database-... |
| §2.2 Export schedule to PDF/printable format | missing | Searched all PHP files under tournament-manager/ for pdf, export — not found in blind-builder-page.php or class-blind-schedule.php. |
| §2.2 Import schedule from CSV with validation | missing | Searched for csv, import in blind-builder-page.php and class-blind-schedule.php — not found. class-player-importer.php exists but is player-specific. |
| §2.3 Suggestion algorithm (starting chips, desired duration, player count, style) | missing | Searched all PHP and JS files for suggest, schedule.*suggest, generate.*schedule, starting_chip, desired_duration, num_player — no suggestion algorithm found anywhere. |
| §2.3 REST endpoint POST .../blinds/suggest | missing | PRD specifies POST /wp-json/poker-tournament/v1/tournaments/:id/blinds/suggest. Grep for 'blinds/suggest' and 'suggest_schedule' in all plugin PHP returned nothing. |
| §2.3 Blinds double approximately every 4-6 levels in suggestion | missing | No suggestion algorithm exists; criterion is vacuously unimplemented. |
| §2.3 Breaks suggested every 60-90 minutes | missing | No suggestion algorithm exists. |
| §2.3 User can modify suggestion before accepting | missing | No suggestion UI or workflow found in blind-builder-page.php or any admin tournament-manager file. |

### 1.x Prize calculator + suggestions + chop/ICM — ✅ audited (`tdwp-5vt`, 2026-06-30)

PRD ref: `docs/prd-phase-1-foundation.md §4.1–4.4`. Adversarially verified. Tally: 4 done · 8 partial · 11 missing · 0 wrong. Gaps filed under epic `tdwp-cma`; 2 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** Template update overwrites shared record — no snapshot isolation; ICM calculation uses incorrect simplified model, not true ICM.

| Criterion | Status | Evidence |
|-----------|--------|----------|
| 4.1 – Prizes can be added with $ or % values | partial | class-prize-structure.php:556–563 — structure_json stores only {place, percentage}. The PRD DB schema specifies separate `amount DECIMAL(10,2)` and `percentage DECIMAL(5,2)` col... |
| 4.1 – Locked prizes don't recalculate | missing | Searched class-prize-structure.php, class-prize-calculator.php, prize-calculator-page.php for 'lock', 'locked'. Not found. No lock field in structure_json (only place/percentage... |
| 4.1 – Rounding applies consistently (nearest $1/$5/$10) | missing | Searched all prize files for 'round_to', 'rounding'. Not found. calculate_payouts_from_array (class-prize-calculator.php:144) hard-codes round(…,2) — two decimal places only, no... |
| 4.1 – Prize total matches available pot (or shows warning) | partial | validate_structure() (class-prize-calculator.php:456–523) checks percentages sum to 100 and shows a warning. However there is no live comparison against the actual calculated ne... |
| 4.1 – Can hide specific prizes from public display | missing | Searched prize files for 'display', 'hide'. The PRD DB schema has a `display BOOLEAN` column; actual table has no such column. No display-flag field in structure_json or UI. |
| 4.1 – Recipient override per place | missing | Searched for 'recipient', 'recipient_player'. Not found in prize-structure or prize-calculator files. PRD schema requires `recipient_player_id BIGINT`; not in DB or JSON. |
| 4.1 – Add/edit/delete prizes; sort; clear all | partial | Full CRUD exists (class-prize-structure.php create/update/delete). Sort is only by name/created_at in get_all() (line 372), not by place or amount. No 'clear all' action found i... |
| 4.2 – 3+ prize suggestion algorithms (Standard, Top-heavy, Flat, Custom) | partial | suggest_common_structures() (class-prize-calculator.php:205–252) returns 5 presets (winner, top-3/4/5/6). The named PRD algorithms — Top-heavy 65/25/10 and Flat (more even distr... |
| 4.2 – Suggested place count scales with players (1-20→3, 21-50→5, 51-100→9, 100+→15%) | missing | suggest_for_players() (class-prize-structure.php:511–537) queries DB for structures with matching min/max_players range — requires pre-seeded records to return anything. No algo... |
| 4.2 – Suggestions calculate to exact pot amount | done | calculate_payouts_from_array() (class-prize-calculator.php:150–156) adjusts rounding difference into 1st place to ensure total matches net_pool. |
| 4.2 – Can modify suggestion before applying | partial | prize-calculator-page.php:843–847 — preset buttons load percentages into the editable places-list form via JS. User can modify before saving. However this applies only to the st... |
| 4.2 – Minimum payout configurable (e.g., $50) | missing | Searched prize files for 'minimum', 'min_payout', 'floor'. Not found. No minimum payout setting exists. |
| 4.3 – Templates save percentage-based structures | done | class-prize-structure.php:615 — is_template flag stored; structure_json holds percentage array. CRUD with is_template=1 fully functional. |
| 4.3 – Loading template recalculates for current pot | partial | prize-calculator-page.php:982–991 — structure select in pool calculator tab triggers payout display via AJAX. However this is a manual step in a separate tab, not an automatic r... |
| 4.3 – 4+ built-in templates provided out-of-box | missing | class-database-schema.php:493–519 creates tdwp_prize_structures table with no INSERT of default rows. No seeder found. The 5 JS presets in suggest_common_structures() are transi... |
| 4.3 – Custom templates can be created/edited | done | Full create/update with is_template flag via prize-calculator-page.php form and AJAX handlers. |
| 4.3 – Template changes don't affect existing tournaments | partial | delete() (class-prize-structure.php:241–258) blocks deletion if referenced by tournament_templates. However updating a template overwrites the shared record (update() line 184),... |
| 4.4 – Chop interface accessible from prizes tab | missing | Chop calculator is a standalone tab at ?action=chop on the prize-calculator admin page (prize-calculator-page.php:523,600). It is not contextually linked to an active tournament... |
| 4.4 – Even split divides prizes correctly | done | calculate_even_chop() (class-prize-calculator.php:415–446) uses floor-then-distribute-remainder approach; validated by reading code. |
| 4.4 – ICM calculation based on chip stacks | partial | calculate_icm_chop() exists (class-prize-calculator.php:312–368) but uses a simplified linear probability model (place_factor = 1 - (place-1)*0.1, lines 393–401) rather than tru... |
| 4.4 – Custom chop amounts per player validated to equal pot | missing | ajax_calculate_chop() (prize-calculator-page.php:440–488) supports chop_type of 'chip', 'icm', 'even' only. No 'custom' type. No UI for per-player custom amounts. Searched for '... |
| 4.4 – Chopped prizes marked distinctly | missing | No 'chopped' flag, status field, or display marker in structure_json, DB schema, or any template. Searched 'chopped', 'chop_status' — not found. |
| 4.4 – Chop recorded in tournament log | missing | class-tournament-events.php exists (includes/tournament-manager/) but has no chop event type. AJAX chop handler returns JSON to browser only; no write to events table, post meta... |

### 1.x Player DB + registration + import/export — ✅ audited (`tdwp-paa`, 2026-06-30)

PRD ref: `docs/prd-phase-1-foundation.md §3.1–3.3`. Adversarially verified. Tally: 9 done · 5 partial · 7 missing · 0 wrong. Gaps filed under epic `tdwp-cma`; 2 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** Registration capacity limit not enforced in frontend AJAX; Excel (.xlsx) import silently accepted then fails.

| Criterion | Status | Evidence |
|-----------|--------|----------|
| 3.1 — Players can be added to database from any screen | done | admin/tournament-manager/player-management-page.php:139 handle_create_player(); includes/class-player-registration.php:241 ajax_register_player() both call TDWP_Player_Manager::... |
| 3.1 — Player records include Name, Email, Phone, Notes, Photo/avatar fields | done | includes/tournament-manager/class-player-manager.php:388-421 sanitize_player_data() handles name, email, phone, bio (notes), avatar_url; all persisted via save_player_meta() |
| 3.1 — Search finds players by partial name match | done | class-player-manager.php:347-365 search() uses LIKE %term% on post_title and player_email; exposed via AJAX at player-management-page.php:849 ajax_search_players() |
| 3.1 — Duplicate detection suggests merges | partial | admin/tournament-manager/class-player-importer.php:298-324 check_duplicate() detects duplicates by UUID/email during import and flags them in the result set. No merge UI or merg... |
| 3.1 — Player autocomplete works in registration forms | done | player-management-page.php:55 registers wp_ajax_tdwp_search_players; assets/js/player-management-admin.js is enqueued for the admin page; class-player-registration.php enqueues ... |
| 3.1 — Player history shows all past tournaments | partial | class-player-manager.php:509-548 get_player_stats() queries poker_tournament_players by player UUID returning tournament count, wins, final tables, winnings, avg finish; display... |
| 3.2 — Admin can add players from existing database (autocomplete) | done | includes/tournament-manager/class-tournament-player-manager.php:33 add_player() registers player to tdwp_tournament_players; admin search AJAX at player-management-page.php:849 ... |
| 3.2 — Admin can create new players during registration | done | player-management-page.php:156 handle_create_player() creates player post via TDWP_Player_Manager::create(); ajax_quick_edit_player() also available |
| 3.2 — Frontend shortcode displays registration form | done | includes/class-player-registration.php:94 render_registration_form() renders HTML form with name/email/phone/bio; registered as [player_registration] shortcode |
| 3.2 — Registration limits enforced | partial | Database schema (class-database-schema.php:502) has max_players column on tdwp_live_tournaments. However class-player-registration.php ajax_register_player() does NOT check max_... |
| 3.2 — Confirmation email sent on registration | partial | class-player-registration.php:266 send_admin_notification() sends email only to get_option('admin_email'). No email is sent to the registrant. PRD requires confirmation email to... |
| 3.2 — Waiting list manages overflow registrations | missing | Grepped 'waiting', 'waitlist' across all plugin PHP files — zero matches in live code. No waiting-list status, queue table, or overflow logic exists. |
| 3.2 — Displays available seats on frontend form | missing | render_registration_form() in class-player-registration.php renders a static form with no seat-count display. No query to max_players or current registrant count is made. |
| 3.2 — Late registration period configuration | missing | Grepped 'late_registration', 'late registration' across plugin — no matches. No schema column or UI for late-reg window configuration found. |
| 3.3 — CSV import handles standard format | done | admin/tournament-manager/class-player-importer.php:92-118 parse_csv() reads file, splits lines, maps columns; prepare_import_data() validates each row and reports errors with ro... |
| 3.3 — Excel import works with .xlsx files | missing | class-player-importer.php:129-137 parse_excel() explicitly returns WP_Error('excel_not_supported', ...) with comment 'not yet supported. Please convert to CSV'. File extension i... |
| 3.3 — Import errors reported with line numbers | done | class-player-importer.php:197-206 validate_import_row() returns WP_Error with row number in message; errors array includes row field; prepare_import_data() accumulates all errors |
| 3.3 — Export includes all player data (CSV, Excel, PDF) | partial | includes/tournament-manager/class-export-manager.php:18 SUPPORTED_FORMATS = ['csv','tdt']; class-csv-exporter.php:64-74 exports tournament-results columns (position, name, statu... |
| 3.3 — Can copy player list from previous tournament | missing | Grepped 'copy.*tournament', 'previous.*tournament', 'bulk.*import.*previous' across plugin PHP — no matches. No bulk-copy-from-prior-tournament feature exists. |
| 3.3 — PDF export formatted for printing | missing | class-export-manager.php:18 SUPPORTED_FORMATS does not include 'pdf'. No PDF library or PDF generation code found anywhere in the plugin. |
| 3.3 — CSV format includes Buy-in Status and Seat Number columns | missing | class-player-importer.php:231-249 map_columns() maps name/email/phone/uuid only. 'Buy-in Status' and 'Seat Number' columns from the PRD CSV spec are not mapped and not imported. |

---

## Phase 2 — Live Operations (epic `tdwp-871`)

All four Phase 2 feature areas are now audited (subsections below): table
management (`tdwp-194`), live player ops (`tdwp-tnq`), stats + export
(`tdwp-sjy`), and clock + heartbeat (`tdwp-bo7`).

### 2.x Table mgmt + auto-seat + balance + break — ✅ audited (`tdwp-194`, 2026-06-30)

PRD ref: `docs/prd-phase-2-live-operations.md §2`. Adversarially verified. Tally: 5 done · 11 partial · 19 missing · 0 wrong. Gaps filed under epic `tdwp-871`; 2 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** Minimum 4 players per table not enforced during balancing; Seat unavailability (broken chairs) not implemented.

| Criterion | Status | Evidence |
|-----------|--------|----------|
| 2.1 Req: Table properties — number, seat count (6/8/9/10), status (active/breaking/brok... | partial | class-table-manager.php:28-30 has STATUS_ACTIVE/BREAKING/BROKEN constants; class-database-schema.php:616-628 shows tdwp_tournament_tables schema has id, tournament_id, table_num... |
| 2.1 Req: Quick table creation — 'Add 5 tables' bulk button, default 9 seats, sequential... | partial | class-table-manager.php:62 add_table() handles single-table creation with default max_seats=9 and sequential numbering via get_next_table_number(). No bulk_create_tables() metho... |
| 2.1 Req: Table editing — change seat count (warn if players seated), rename table, chan... | partial | class-table-manager.php:311 update_status() exists. No update_table(), rename_table(), or set_seat_count() method found. No renaming functionality and no seat-count editing with... |
| 2.1 Req: Table deletion — prevented if players seated; option to unseat all first | partial | class-table-manager.php:123-168 remove_table() enforces is_table_empty() check before deletion. However there is no 'unseat all first and then delete' helper method; unseat_play... |
| 2.1 Req: Table capacity calculation based on registrations | missing | Searched for 'capacity.*registrations', 'registration.*capacity' in includes/tournament-manager/ — nothing found. get_table_count() and get_seated_player_count() exist but no fu... |
| 2.1 AC: Tables created with specified seat counts | done | class-table-manager.php:71 clamps max_seats via max(2, min(12, intval($max_seats))); seats are created in loop lines 98-109. |
| 2.1 AC: Bulk create generates correct number of tables | missing | No bulk create method exists. Searched for 'bulk.*table', 'add.*tables' in all .php files — found nothing. |
| 2.1 AC: Editing validates against current seating | missing | No seat-count or rename editing method exists at all. Searched 'update_table', 'rename.*table', 'change.*seat.*count' — not found. |
| 2.1 AC: Deletion prevented when players seated | done | class-table-manager.php:127 calls is_table_empty() and returns false if players are present. |
| 2.1 AC: Total capacity matches table configuration | partial | get_table_count() and get_seated_player_count() exist (lines 373, 408) but no function explicitly computes total tournament seat capacity as sum of all tables' max_seats, nor ti... |
| 2.2 Req: Initial seating — random + balanced distribution (±1), dealer button randomly ... | partial | class-seat-manager.php:251-276 find_optimal_seat() uses ORDER BY table_player_count ASC, RAND() for balance. auto_seat_players() (line 288) loops registrations. No dealer_button... |
| 2.2 Req: Automatic balancing triggers — on bust, manual button, auto-balance option | partial | class-table-balancer.php:437 trigger_rebalance() exists (since 3.2.0). Manual trigger is wired. No evidence of per-tournament 'auto-balance on every bust' toggle or that bust-ou... |
| 2.2 Req: Balancing algorithm — minimum moves, never leave table with <4 players, mainta... | partial | class-table-balancer.php:109-183 generate_move_plan() implements minimum-move logic. No check for min 4 players per table (searched 'min.*4', '4.*player', 'minimum.*player' in b... |
| 2.2 Req: Table breaking — auto break smallest, distribute players, mark broken, option ... | partial | class-table-balancer.php:231 suggest_table_break() finds smallest table; execute_table_break() (line 337) marks table breaking→broken and moves players. No 'lock table from brea... |
| 2.2 AC: Initial seating distributes evenly | done | class-seat-manager.php:259-275 find_optimal_seat() seats to lowest-count active table. |
| 2.2 AC: Balancing maintains ±1 player variance | done | class-table-balancer.php:67-75 checks >target+1 and <target-1 thresholds. |
| 2.2 AC: Minimum players per table enforced (never <4) during balancing | missing | class-table-balancer.php generate_move_plan() (lines 109-183) has no minimum-player guard. Searched 'min.*4', '4.*player' in table-balancer — not found. |
| 2.2 AC: Dealer buttons remain valid after moves | missing | No dealer_button column in tdwp_tournament_seats schema (class-database-schema.php:648-667). Searched 'dealer_button', 'dealer button' across all .php files — not found. |
| 2.2 AC: Table breaking distributes optimally | done | class-table-balancer.php:270-310 suggest_table_break() distributes smallest-table players to tables with capacity. |
| 2.2 AC: Movement log tracks all changes | partial | No dedicated player_movements table (PRD schema names wp_poker_player_movements). Movement stored only as moved_from_table_id/moved_from_seat_number columns on current seat row ... |
| 2.3 Req: Drag-and-drop player movement UI | missing | class-layout-builder.php has drag-drop for display panel widgets only. No drag-drop seating UI found in any admin PHP/JS file under tournament-manager/. Searched 'drag.*seat', '... |
| 2.3 Req: Seat locking (prevents auto-move) | missing | No 'locked' column in tdwp_tournament_seats schema. Searched 'locked', 'seat_lock', 'is_locked' across all tournament-manager PHP files — nothing found. |
| 2.3 Req: Make seat unavailable (broken chairs etc.) | missing | No seat_available/is_available/unavailable column in tdwp_tournament_seats schema. Searched 'unavailable', 'seat_available' — not found. |
| 2.3 Req: Swap two players | missing | No swap_players() method found. Searched 'swap' in all tournament-manager PHP files — not found. |
| 2.3 Req: Move entire table to different number | missing | No method to renumber a table. Searched 'move.*table', 'table.*number.*change', 'renumber' — not found. |
| 2.3 Req: Undo last move operation | missing | No undo mechanism. Searched 'undo', 'revert.*move' across all files — not found. |
| 2.3 Req: Seat assignment overrides (VIP, request-based, separation requirements) | missing | No VIP flag, separation rule, or preference fields in any schema or class. move_player() allows targeted seating but no override metadata is stored. |
| 2.3 Req: Visual table diagram showing players, dealer button, locked players, empty seats | missing | No table diagram template or component found. class-display-manager.php renders public tournament displays. No admin-side seating diagram for TD use was found. |
| 2.3 AC: Players can be moved via drag-drop | missing | Backend move_player() (class-seat-manager.php:35) works programmatically but no drag-drop UI layer exists. |
| 2.3 AC: Locked players stay put during auto-balance | missing | Lock feature not implemented in schema or balancer. |
| 2.3 AC: Unavailable seats excluded from capacity | missing | No seat availability column exists; find_optimal_seat() only checks player_id IS NULL. |
| 2.3 AC: Swap operation validates seat availability | missing | No swap method exists at all. |
| 2.3 AC: Undo restores previous state | missing | No undo mechanism exists. |
| 2.3 AC: Visual diagram updates in real-time | missing | No seating diagram component found anywhere. |
| 2.3 AC: Manual moves logged in history | partial | class-seat-manager.php:69-88 saves moved_from_table_id/moved_from_seat_number on the seat row before overwriting. This is a single-step history only (last move) — no append-only... |

### 2.x Live player ops (bustout/rebuy/addon/late-reg/chip) — ✅ audited (`tdwp-tnq`, 2026-06-30)

PRD ref: `docs/prd-phase-2-live-operations.md §3`. Adversarially verified. Tally: 8 done · 4 partial · 9 missing · 3 wrong. Gaps filed under epic `tdwp-871`; 3 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** Rebuy rules bypassed on live AJAX path — no period, limit, or chip-threshold enforcement; Add-on one-per-player limit not enforced on live AJAX path; Late registration period not enforced — add_player() has no level/status gate.

| Criterion | Status | Evidence |
|-----------|--------|----------|
| 3.1 Bust-outs assign correct sequential rank | done | class-player-operations.php:104-108 — calculate_finish_position() counts already-eliminated players + still-active players to assign rank; updated on player record at line 125-128. |
| 3.1 Simultaneous busts handled correctly (bulk bust-out, tie resolution, split prize) | missing | Searched all PHP files for 'simultaneous', 'bulk.*bust', 'tie.*place', 'split.*prize', 'same.*place' — no matching code found. process_bustout() takes a single player_id; there ... |
| 3.1 Bubble player identified automatically | missing | Searched all PHP files for 'bubble' — no logic found in class-player-operations.php or class-tournament-manager-ajax.php. No bubble detection or special handling exists. |
| 3.1 Undo bust-out restores state accurately, adjusts rankings | missing | Searched for 'undo_bustout', 'revert_bustout', 'cancel_bustout', 'wp_ajax.*undo' — nothing found. process_bustout() has rollback logic only for transaction-log failures, not a u... |
| 3.1 Prize amounts display for ITM finishes (auto-filled) | partial | prize_amount field exists in tdwp_tournament_players schema and is returned for the winner in get_tournament_winner() (class-player-operations.php:901). No code found that auto-... |
| 3.1 Bust-out logged with timestamp | done | class-player-operations.php:111 captures $bustout_timestamp; logged via TDWP_Transaction_Logger::log_transaction() at line 172 with bustout_timestamp in $transaction_data. |
| 3.2 Rebuy rules enforced automatically (period open, max limit, chip threshold) | wrong | Two competing implementations exist. TDWP_Tournament_Player_Manager::process_rebuy() (class-tournament-player-manager.php:1035) correctly enforces period (level check line 1054)... |
| 3.2 Ineligible players cannot rebuy | wrong | Same as above — the active AJAX path (TDWP_Player_Operations::process_rebuy) does not enforce ineligibility. Only TDWP_Tournament_Player_Manager::process_rebuy() does, but it is... |
| 3.2 Add-ons limited to one per player | wrong | TDWP_Tournament_Player_Manager::process_addon() (class-tournament-player-manager.php:1160) checks addons_count > 0 and returns an error. But the AJAX handler (class-tournament-m... |
| 3.2 Pot updates with each rebuy/addon transaction | done | class-player-operations.php:366-378 (rebuy) and 481-493 (addon) update prize_pool in tdwp_tournament_live_state. |
| 3.2 Bulk add-on processes entire table | missing | Searched for 'bulk.*addon', 'bulk.*add.on', 'table.*addon' across all PHP files — nothing found. |
| 3.2 Transactions logged with details | done | class-player-operations.php:381-388 (rebuy) and 496-503 (addon) call TDWP_Transaction_Logger::log_transaction() with type, amount, chips, and reason string. |
| 3.3 Late registration open until configured time/level | partial | Schema has late_reg_until_level column (class-database-schema.php:392) and wizard saves it (live-tournament-wizard.php:611). However TDWP_Tournament_Player_Manager::add_player()... |
| 3.3 Late entries receive correct chips (starting or current average) | partial | add_player() assigns _starting_chips (class-tournament-player-manager.php:52-71); PRD also says 'or current average'. No average chip calculation found in add_player(). Starting... |
| 3.3 Seating happens automatically on late entry | missing | add_player() (class-tournament-player-manager.php:33-113) inserts the player record but does not call TDWP_Seat_Manager or any auto-seating logic. |
| 3.3 Tables rebalance with new late-registration player | missing | add_player() does not call TDWP_Table_Balancer::trigger_rebalance(). No rebalance trigger found connected to player addition. |
| 3.3 Late registration deadline displays on public screens | missing | Searched for 'deadline', 'countdown.*reg', 'late.*reg.*display' in display manager and shortcodes — nothing found. live-tournament-wizard.php only stores the config value. |
| 3.3 Registration cannot reopen after manual close (TD override) | missing | Searched for 'manual.*close', 'reopen', 'td.*override', 'close.*late.*reg' — nothing found. No mechanism to manually close or prevent reopening of late registration exists. |
| 3.4 Chip counts editable per player | done | TDWP_Player_Operations::process_chip_adjustment() (class-player-operations.php:543) accepts player_id, adjustment, reason and updates chip_count. Exposed via AJAX at class-tourn... |
| 3.4 Reason required for all adjustments | done | class-player-operations.php:560-562 returns WP_Error('missing_reason') when $reason is empty. |
| 3.4 Negative counts prevented | done | class-player-operations.php:578-586 calculates new_chip_count and returns WP_Error('negative_chips') if result < 0. |
| 3.4 Large adjustments require confirmation | missing | Searched for 'large.*adjust', 'warn.*large', 'confirm.*adjust', 'threshold.*chip' in player-operations.php and ajax handler — no server-side threshold or confirmation check found. |
| 3.4 Complete audit trail maintained | done | class-player-operations.php:606-613 calls TDWP_Transaction_Logger::log_transaction() with type 'chip_adjustment', amount 0, chips=adjustment, and the required reason string. |
| 3.4 History exportable (filterable by player/type, CSV export) | partial | admin/tournament-manager/transaction-log-viewer.php:460-462 has a CSV export button calling tdwp_tm_export_transactions_csv AJAX action; class-tournament-manager-ajax.php:1063 s... |

### 2.x Stats + export (.TDT/CSV/PDF/JSON) + email results — ✅ audited (`tdwp-sjy`, 2026-06-30)

PRD ref: `docs/prd-phase-2-live-operations.md §4`. Adversarially verified. Tally: 0 done · 7 partial · 9 missing · 0 wrong. Gaps filed under epic `tdwp-871`; 1 P0 defect(s) under `tdwp-3lg`.

**P0 defects:** BB-equivalent chip leader calculation uses hardcoded placeholder (TODO).

| Criterion | Status | Evidence |
|-----------|--------|----------|
| 4.1 Statistics dashboard: players remaining, average chip stack, total chips in play, l... | partial | class-statistics-engine.php:136-154 covers remaining_players, average_stack, biggest_stack, shortest_stack, total_chips, total_prize_pool. Rebuys/add-ons count and revenue are N... |
| 4.1 Statistics dashboard: players eliminated per level | missing | Searched class-statistics-engine.php for 'per_level', 'eliminated_per_level', 'level_busts' — none found. The stats array has no breakdown by level. |
| 4.1 Statistics dashboard: rebuys/add-ons count and revenue | missing | class-statistics-engine.php does not query or return rebuy_count, addon_count, or related revenue fields. Searched for 'rebuy', 'addon', 'revenue' in that file — none found. |
| 4.1 Statistics dashboard: tournament duration (elapsed) and estimated time to completion | partial | class-statistics-engine.php:123-147 calculates and returns time_elapsed. Estimated time to completion is absent — no 'estimated' or 'completion' field in stats array. |
| 4.1 Per-player statistics: current chips, starting chips, peak chips, rebuys, add-ons, ... | missing | class-statistics-engine.php returns only aggregate stats and chip_leader. No per-player breakdown object returned. Searched for 'starting_chips', 'peak_chips', 'per_player' — no... |
| 4.1 Table statistics: players per table, average chips per table, short stack at table,... | missing | class-statistics-engine.php contains no table-level aggregation. Searched for 'players_per_table', 'avg_chip', 'short_stack_table', 'big_stack_table' — none found. |
| 4.1 Real-time statistics updates (no page refresh needed) | partial | AJAX action wp_ajax_tdwp_tm_get_state exists (class-tournament-manager-ajax.php:41). Stats are cached with 15-second transients (class-statistics-engine.php:37,157). No Heartbea... |
| 4.2 Tournament summary includes all required data (tournament details, player list, pri... | partial | class-tdt-exporter.php:72-97 includes name, date, status, buyin, prizePool, totalPlayers, finishPosition, prizeAmount, rebuyCount, addonCount. Missing: blind structure used, rak... |
| 4.2 PDF export | missing | class-export-manager.php:18 — SUPPORTED_FORMATS = ['csv', 'tdt']. No PDF exporter class found. grep for 'pdf' in includes/tournament-manager/ returns only docblock comment refer... |
| 4.2 Excel (.xlsx) export | missing | class-export-manager.php:18 — SUPPORTED_FORMATS = ['csv', 'tdt']. No xlsx exporter. grep for 'xlsx' in tournament-manager/ returns only player-importer.php (import side) and adm... |
| 4.2 JSON export | missing | class-export-manager.php:18 — SUPPORTED_FORMATS = ['csv', 'tdt']. No json case in switch. grep for 'json.*export' in tournament-manager/ returns only layout export (class-layout... |
| 4.2 CSV export | partial | class-csv-exporter.php fully implemented: player rows with position, name, status, chips, prize, buyin/rebuy/addon counts (lines 64-91). Missing tournament-level header row (nam... |
| 4.2 .TDT export compatible with TD3 | partial | class-tdt-exporter.php generates SimpleXML with info and players nodes. Blind structure is not included in the export (no blinds node). TD3 compatibility cannot be confirmed wit... |
| 4.2 Email results to all players / specific addresses / receipts for ITM | missing | Searched entire plugin for 'wp_mail' calls — only class-player-registration.php (registration confirmation to admin). No tournament-results email sender exists. No AJAX action f... |
| 4.2 Tournament archive: mark complete, archived list, read-only mode, restore | partial | class-tournament-player-manager.php implements update_tournament_status() setting live_state.status = 'completed'. No 'archived' status, no read-only enforcement in export/edit ... |
| 4.2 Email delivery tracked | missing | No email sending feature exists; therefore no delivery tracking. Searched for 'email_log', 'email_sent', 'delivery' in tournament-manager/ — none found. |

### 2.x Clock + real-time heartbeat sync — ✅ audited (`tdwp-bo7`, 2026-06-30)

PRD ref: `docs/prd-phase-2-live-operations.md` §1.1–1.3. Verified by adversarial
re-check (18/20 done|partial claims held; end-tournament downgraded done→partial).

| Criterion | Status | Evidence | Gap bead |
|-----------|--------|----------|----------|
| §1.1 Clock display — current level SB/BB/Ante | partial | only level number on public clock `class-tournament-clock-shortcode.php:160-169`; no blind values in payload (admin-only in `timer-tab.php`) | `tdwp-7u1` |
| §1.1 Clock display — time remaining | done | `class-tournament-clock-shortcode.php:155-156`; local countdown `tournament-clock-frontend.js:130-137` | — |
| §1.1 Clock display — next level preview | missing | no next-level blind data fetched/rendered | `tdwp-7u1` |
| §1.1 Clock display — players remaining | done | `class-tournament-clock-shortcode.php:181-184`; live `tournament-clock-frontend.js:278-281` | — |
| §1.1 Clock display — avg chip stack | missing | absent from state payload `ajax_get_clock_state:254-266` | `tdwp-ekz` |
| §1.1 Clock display — current pot | missing | only `prize_pool` shown (≠ current pot) | `tdwp-ekz` |
| §1.1 Controls — start / pause / resume | done | `class-tournament-clock.php:63,155,235`; binds `live-control-admin.js:51-58` | — |
| §1.1 Controls — skip next level | done | `advance_level()` `class-tournament-clock.php:307`; `timer-tab.php:111` | — |
| §1.1 Controls — skip to specific level | missing | only advance-by-one implemented | `tdwp-zh6` |
| §1.1 Controls — add time | done | `class-live-state-manager.php:340`; `timer-tab.php:112` | — |
| §1.1 Controls — end tournament | partial | `complete()` `class-tournament-clock.php:369` but JS binds `.tdwp-complete-btn` while template renders `#btn-finish` (`timer-tab.php:109`) — selector mismatch | `tdwp-h0r` |
| §1.1 Break — auto-start at configured levels | partial | `start_break()` exists `class-live-state-manager.php:267`; `advance_level()` does not detect break levels | `tdwp-nja` |
| §1.1 Break — countdown + "back at HH:MM" | missing | `break_until` stored `:278` but excluded from heartbeat/AJAX payloads `:326-337` | `tdwp-nja` |
| §1.1 Sound notifications | missing | no Web Audio / assets / triggers in any clock JS | `tdwp-blc` |
| §1.1 Clock survives page refresh | done | server state `tdwp_tournament_live_state` `class-live-state-manager.php:63-76`; refetched on load `:128-139` | — |
| §1.2 WP Heartbeat integration, 15s sync | done | `tournament-clock-frontend.js:95-111`; filter `class-tournament-clock-shortcode.php:57-58` | — |
| §1.2 Optimistic UI | done | 1s local countdown `tournament-clock-frontend.js:121-138` | — |
| §1.2 Server-side time authority | done | server overwrites local on tick `tournament-clock-frontend.js:197-199` | — |
| §1.2 Multi-tab sync ±1s | partial | per-tab independent 15s sync (±15s); no BroadcastChannel/SSE | `tdwp-32g` |
| §1.2 Connection-lost warning overlay | missing | no disconnect detection/overlay in clock JS | `tdwp-o12` |
| §1.2 Auto reconnection w/ state recovery | partial | 3s status-poll fallback `:147-180`; no lost-conn state machine / feedback | `tdwp-o12` |
| §1.2 No drift over 4+ hours | partial | `tick()` `class-tournament-clock.php:428` uses client/default elapsed, not wall-clock | `tdwp-rhp` |
| §1.3 Full-screen mode (F11/button) | missing | no Fullscreen API anywhere | `tdwp-558` |
| §1.3 Clean public interface | partial | shortcode renders no admin controls `:149-208`; no mode hiding WP chrome | `tdwp-558` |
| §1.3 Customizable elements (blinds/prizes/rankings/logo) | partial | only show_stats/show_level/theme/size `:113-124` | `tdwp-wp7` |
| §1.3 Auto screen wake (prevent sleep) | missing | no Wake Lock / NoSleep | `tdwp-558` |
| §1.3 URL param `?screen=clock` | missing | no such routing | `tdwp-558` |
| §1.3 Responsive design | partial | size CSS classes `:145`; media queries unverified (CSS not inspected) | `tdwp-wp7` |
| §1.3 Optional dark mode | partial | `theme=dark` class `:119,144`; CSS defs unverified | `tdwp-wp7` |

**Files of record:** `includes/tournament-manager/class-tournament-clock.php`,
`includes/class-tournament-clock-shortcode.php`,
`includes/tournament-manager/class-live-state-manager.php`,
`assets/js/tournament-clock-frontend.js`, `assets/js/tdwp-global-tournament-heartbeat.js`,
`admin/tournament-manager/tabs/timer-tab.php`, `assets/js/live-control-admin.js`.

---

## Phase 3 — Professional Features (epic `tdwp-ee1`)

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Events & notifications (sounds, email/SMS, triggers) | ⬜ pending audit | `tdwp-38u` |
| Rules display (templates, multi-language) | ⬜ pending audit | `tdwp-efh` |
| Chip management (chipset, denominations, capacity) | ⬜ pending audit | `tdwp-g28` |
| League mgmt + player photos/badges | ⬜ pending audit | `tdwp-hgp` |

Likely files: `class-tournament-events.php` and others per feature area.

### 3.x Token display + layout builder — ✅ audited (`tdwp-7qe`, 2026-06-30)

PRD ref: `docs/prd-phase-3-professional-features.md` §1 Display & Layout System.
Verified by adversarial re-check (all 12 done|partial claims held).

| Criterion | Status | Evidence | Gap bead |
|-----------|--------|----------|----------|
| Token `{{tournament_name}}` | done | `class-template-engine.php:128-133`, resolver `:559` | — |
| Token `{{current_level}}` | done | `class-template-engine.php:164-170`, resolver `:649` | — |
| Token `{{small_blind}}` | partial | registered as `small_blind_amount` `:205-210` — PRD name unresolved | `tdwp-9qm` |
| Token `{{big_blind}}` | partial | registered as `big_blind_amount` `:200-205` | `tdwp-9qm` |
| Token `{{ante}}` | partial | registered as `ante_amount` `:211-217` | `tdwp-9qm` |
| Token `{{time_remaining}}` | done | `class-template-engine.php:148-153`, resolver `:603` | — |
| Token `{{players_remaining}}` | done | `class-template-engine.php:155-160`, resolver `:619` | — |
| Token `{{total_pot}}` | missing | only `prize_pool` exists | `tdwp-5fo` |
| Token `{{next_sb}}` | missing | only generic `next_blind` `:140` | `tdwp-5fo` |
| Token `{{next_bb}}` | missing | only generic `next_blind` | `tdwp-5fo` |
| Token `{{avg_stack}}` | partial | registered as `average_stack` `:190-196` | `tdwp-9qm` |
| Token `{{venue_name}}` | missing | no registry entry / resolver | `tdwp-5fo` |
| Token `{{venue_logo}}` | missing | no registry entry / resolver | `tdwp-5fo` |
| Layout builder drag-drop interface | done | jQuery UI draggable/droppable `admin/assets/js/layout-builder.js:86-105`; `handleComponentDrop():316` | — |
| Multiple screen templates (Clock/Rankings/Blinds/Prizes/Tables) | partial | ENUM `class-database-schema.php:1370` = clock/rankings/prizes/seating/rules/custom; no `blinds`, `tables`→`seating` | `tdwp-6wq` |
| HTML/CSS customization | done | `html_template`/`css_styles` LONGTEXT cols; saved via `class-display-manager.php:780` | — |
| Banner image support | missing | no banner field/upload/renderer in display system | `tdwp-3lv` |
| Multiple screen sets | partial | individual screens only `poker_display_screens`; no set grouping | `tdwp-323` |
| Screen transition effects | missing | no transition field/logic in renderer | `tdwp-6tg` |
| Conditional display rules | missing | no condition field/eval (formula validator not wired) | `tdwp-5fs` |
| Layout JSON schema (`screen_sets[].screens[].cells[]`, `{x,y,w,h}`) | wrong | stored `component_positions` uses `{column_start,row_start,width,height}`, no `screen_sets`/`cells` nesting | `tdwp-71e` |
| Layout builder undo/redo | — (defect) | Ctrl+Z/Y bound to undefined `undo()`/`redo()` `admin/assets/js/layout-builder.js:136-140` | `tdwp-ebc` |

**Files of record:** `includes/tournament-manager/class-template-engine.php`,
`includes/tournament-manager/class-layout-builder.php`,
`includes/tournament-manager/class-display-manager.php`,
`includes/tournament-manager/class-database-schema.php`,
`admin/assets/js/layout-builder.js`.

---

## Phase 4 — Premium + REST API + Mobile (epic `tdwp-1u4`)

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Expo controller + mobile app + REST API | ⬜ pending audit | `tdwp-2mn` |
| Advanced rake & financial reporting | ⬜ pending audit | `tdwp-3jh` |
| Multi-monitor / advanced display | ⬜ pending audit | `tdwp-bro` |
| Hand timer & analytics | ⬜ pending audit | `tdwp-ebd` |

> REST API interface decision: see ADR `docs/adr/0001-api-interface-ajax-vs-rest.md` (deferred).

---

_Maintained under tdwp-xsa. Phase 1 Setup+Templates rows are verified
(tdwp-96a). All `⬜ pending audit` rows are filled in by their named audit bead._
