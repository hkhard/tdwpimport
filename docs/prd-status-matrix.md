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
| Clock + real-time heartbeat sync | ⬜ pending audit | `tdwp-bo7` |
| Live player ops (bustout / rebuy / add-on / late-reg / chip) | ⬜ pending audit | `tdwp-tnq` |
| Stats + export (.TDT/CSV/PDF/JSON) + email results | ⬜ pending audit | `tdwp-sjy` |

Likely files: `class-table-manager.php`, `class-table-balancer.php`, `class-seat-manager.php`, `class-tournament-clock.php`, `class-live-state-manager.php`, `class-player-operations.php`, `class-export-manager.php`, `class-csv-exporter.php`, `class-tdt-exporter.php`.

---

## Phase 3 — Professional Features (epic `tdwp-ee1`)

| Feature area | Status | Audit bead |
|--------------|--------|-----------|
| Token display + layout builder | ⬜ pending audit | `tdwp-7qe` |
| Events & notifications (sounds, email/SMS, triggers) | ⬜ pending audit | `tdwp-38u` |
| Rules display (templates, multi-language) | ⬜ pending audit | `tdwp-efh` |
| Chip management (chipset, denominations, capacity) | ⬜ pending audit | `tdwp-g28` |
| League mgmt + player photos/badges | ⬜ pending audit | `tdwp-hgp` |

Likely files: `class-display-manager.php`, `class-layout-builder.php`, `class-template-engine.php`, `class-tournament-events.php`, `admin/tournament-manager/layout-builder-page.php`, `screen-management-page.php`.

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
