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

### 1.x Blind Schedule builder + suggestions

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Blind schedule builder, level editing, smart suggestions | ⬜ pending audit | `tdwp-bo5` |

Likely files: `class-blind-schedule.php`, `class-blind-level.php`, `admin/tournament-manager/blind-builder-page.php`.

### 1.x Prize calculator + suggestions + chop/ICM

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Prize structure calc, payout suggestions, chop/ICM | ⬜ pending audit | `tdwp-5vt` |

Likely files: `class-prize-calculator.php`, `class-prize-structure.php`, `admin/tournament-manager/prize-calculator-page.php`.

### 1.x Player DB + registration + import/export

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Player database, registration, import/export | ⬜ pending audit | `tdwp-paa` |

Likely files: `class-player-manager.php`, `class-player-registration.php`, `admin/tournament-manager/class-player-importer.php`, `player-management-page.php`.

---

## Phase 2 — Live Operations (epic `tdwp-871`)

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Table mgmt + auto-seat + balance + break | ⬜ pending audit | `tdwp-194` |
| Live player ops (bustout / rebuy / add-on / late-reg / chip) | ⬜ pending audit | `tdwp-tnq` |
| Stats + export (.TDT/CSV/PDF/JSON) + email results | ⬜ pending audit | `tdwp-sjy` |

Likely files: `class-table-manager.php`, `class-table-balancer.php`, `class-seat-manager.php`, `class-player-operations.php`, `class-export-manager.php`, `class-csv-exporter.php`, `class-tdt-exporter.php`.

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
