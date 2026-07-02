# tdwp-eil — Migration Plan: Collapse the dual player-stats store onto one canonical schema

> **Status:** PLAN — awaiting user decisions (see [Open Questions](#open-questions)) before any destructive execution.
> **Issue:** tdwp-eil (Option C part (a), split from tdwp-ayg). Parent epic tdwp-3lg.
> **Author:** produced via multi-agent mapping workflow over all ~29 reader/writer sites.
>
> The issue mandates: a written migration plan (this doc) + a staging/real-data dry run + human sign-off
> **before** any destructive step. Nothing here is executed autonomously.

---

## TL;DR — the key reframe

**"One physical table" is the wrong target. "One canonical SOURCE + one derived READ MODEL" is right.**

The two tables are not two copies of the same data — they are two layers that were never named as such:

| Table | Grain | Key | Role |
|-------|-------|-----|------|
| `tdwp_tournament_players` | one row **per entry** (re-entries = many rows) | bigint post-id + `id` surrogate | **operational source-of-truth** for live play; `id` is `registration_id` FK in `tdwp_tournament_seats` |
| `poker_tournament_players` | one **aggregated** row per player/tournament | UUID varchar(100), joined via postmeta `tournament_uuid`/`player_uuid` | **derived read model / stats mart**; today written by 5 different writers, always rebuildable |
| `poker_player_roi` | one row per player/tournament | UUID varchar(100) | derived ROI mart; **has NO unique key** (latent dup bug) |

The `TDWP_Stats_Bridge` component disappears **not because a table is dropped**, but because there is **one write path (the per-entry source) and one rollup**, so there is no longer a live↔legacy store gap to bridge.

Collapsing both grains into one physical row-shape is **lossy in both directions**: it would either duplicate aggregates per entry, or destroy the re-entry / seat / chip / bounty detail and the `id` FK the seat table depends on.

---

## Grain decision

**Keep BOTH grains. Make per-entry canonical (source); make aggregated derived (read model). Never fuse into one physical grain.**

- **Per-entry (canonical source):** `tdwp_tournament_players`, `UNIQUE(tournament_id, player_id, entry_number)`. Required by the live subsystem and the `id`→seat `registration_id` FK. Losing this grain corrupts live play and orphans seats.
- **Aggregated (derived read model):** `poker_tournament_players`, `UNIQUE(tournament_uuid, player_uuid)`. One row per player/tournament, produced by a deterministic rollup:
  `SUM(entries)→buyins`, `SUM(prize_amount/winnings)→winnings`, `SUM(rebuys_count)→rebuys`, `SUM(addons_count)→addons`, `MIN(finish_position)→finish_position`, points recomputed. This exactly reproduces `class-stats-bridge.php::project_to_stats_mart` (L169-227), so behavior is preserved.
- **Imported tournaments** never had per-entry detail (`.tdt` gives aggregate counts). They are stored in the canonical source as **one synthetic entry per player** (`entry_number=1`, `source='import'`). For imports the rollup is a 1:1 pass-through — lossless.

Honest conclusion: **the aggregate is a cache of the source.** It is the only reconciliation that loses nothing, and it is only *provable* once a real-data dry run confirms the rollup reproduces today's mart row counts and sums.

Why not one table / one grain:
- **Per-entry only** → breaks ~40 readers that assume one row per player; double-counts `SUM(winnings)`/`COUNT(*)` for re-entered players (e.g. `single-tournament.php` paid-positions, `archive-tournament.php` totals — neither has GROUP BY defense).
- **Aggregated only** → destroys re-entry rows, seat/chip state, and the `id` FK → breaks live operation. Non-starter.

---

## Canonical source schema (evolve `tdwp_tournament_players`, do NOT rename yet)

```sql
-- Canonical per-entry participation source.
-- Additive: keep every existing tdwp_tournament_players column (live FKs depend on `id`).
CREATE TABLE {prefix}tdwp_tournament_players (
    id                bigint UNSIGNED NOT NULL AUTO_INCREMENT,   -- KEEP: seat registration_id FK
    tournament_id     bigint UNSIGNED NOT NULL,                  -- KEEP: wp_posts.ID
    player_id         bigint UNSIGNED NOT NULL,                  -- KEEP: wp_posts.ID
    entry_number      int NOT NULL DEFAULT 1,                    -- KEEP: per-entry grain
    -- ... ALL existing live columns retained verbatim (status, chip_count, paid_amount,
    --     rebuys_count, addons_count, seat_assignment, finish_position, prize_amount,
    --     is_reentry, original_entry_id, bounties_*, withdrawal_*, elimination_*, etc.) ...
    tournament_uuid   varchar(100) NOT NULL DEFAULT '',          -- [NEW] stored bridge key
    player_uuid       varchar(100) NOT NULL DEFAULT '',          -- [NEW] stored bridge key
    source            varchar(16)  NOT NULL DEFAULT 'live',      -- [NEW] 'live' | 'import'
    PRIMARY KEY (id),
    UNIQUE KEY tournament_player_entry (tournament_id, player_id, entry_number),
    KEY tournament_uuid (tournament_uuid),
    KEY player_uuid (player_uuid),
    KEY uuid_pair (tournament_uuid, player_uuid)
) {charset_collate};
```

**Key move:** UUID becomes a **stored column**, not a runtime `get_or_create_meta_uuid()` postmeta lookup. That is the concrete step that makes the bridge's mapping redundant.

**Read model** `poker_tournament_players` / `poker_player_roi` keep their DDL **byte-identical** (varchar(100) UUID keys). ~40 read sites depend on the exact shape + the postmeta bridge; they get **exactly one writer** — the rollup — instead of today's 5 divergent writers.

---

## Key strategy

**Dual-key the canonical source; keep the read model UUID-keyed. Do not converge to one key type.**

- ~40 read/write sites require **UUID varchar** keys joined to `wp_postmeta` (`tournament_uuid`/`player_uuid`) and `tdwp_points_adjustments`. UUID is deliberate — it survives re-import (which mints a new post/bigint id).
- The entire live subsystem requires **bigint post-id** keys and the `id` surrogate as seat FK.

Strategy:
1. Canonical source carries **both**: bigint keys (live FKs, unchanged) + new stored `tournament_uuid`/`player_uuid` populated at write time via the promoted `get_or_create_meta_uuid()` helper.
2. Read model stays UUID varchar(100). The rollup copies UUIDs straight from the source's stored columns — no lookup, no drift.

### Latent key-type bugs the strategy must FIX (broken today, independent of the merge)
1. `class-css-dashboard-config.php::get_players_section` (~L302): `INNER JOIN wp_posts p ON p.ID = tp.player_id` treats a UUID varchar as a bigint post-id → returns nothing. Repoint to the UUID/postmeta join used by `get_overview_section` in the same file.
2. `class-css-dashboard-config.php` (~L313-323): joins `poker_player_roi.player_id = wp_posts.ID` and selects non-existent `tournaments_played`/`avg_finish` columns → silent zeros. Reconcile to real UUID-keyed ROI shape.
3. `class-display-shortcode.php::get_leaderboard` (~L522): queries `poker_tournament_players` with bigint `tournament_id=%d` and columns `chips`/`eliminated`/`rank` that don't exist there — it means the **live** `tdwp_tournament_players`. Repoint to live source.
4. `poker-tournament-import.php::get_tournament_data` live count (~L3454): `WHERE tournament_id=%d AND eliminated=0` — repoint to live table (`status='active'`/`finish_position IS NULL`).

---

## ROI ownership + the no-unique-key dup bug

**(a) Dup bug (tdwp-ayg/iwc):** `poker_player_roi` has only `PRIMARY KEY(id)` — no UNIQUE on `(player_id, tournament_id)`. So `$wpdb->replace()` never replaces; every writer relies on manual delete-then-insert, and any path that skips the delete silently accumulates duplicate rows that inflate `SUM(net_profit)` in every leaderboard.
- **Fix (Step 1):** dedup keeping `MIN(id)`, then `ADD UNIQUE KEY player_tournament (player_id, tournament_id)`. Dup count is a **dry-run unknown** — must be measured on real data before deleting.

**(b) Fragmented ownership + lossy rebuild:** ROI is written by 3 owners with 2 buy-in strategies:
- `process_player_roi_data` — buy-in from fee_profiles (accurate)
- `migrate_populate_roi_table` — buy-in from `_buy_in` postmeta with a **$20 fallback** (lossy; this is why `calculate_all_statistics` L107-116 refuses to blanket-rebuild ROI)
- `class-stats-bridge.php::project_to_roi_mart` — `total_invested = buyin × entry_count`
- **Fix:** ROI becomes a **derived rollup owned solely by `TDWP_Stats_Rollup`**, computed from the source's stored financial columns: `total_invested = SUM(paid_amount)` (+ per-entry rebuys/addons the source now actually stores), `total_winnings = SUM(prize_amount)`, `net_profit = winnings − invested`. Because the source persists the **actual paid amount per entry**, the destructive `$20`-fallback rebuild is no longer lossy — this is exactly what makes `calculate_all_statistics` safe to rebuild ROI again, closing the tdwp-eil gap.

---

## Migration steps (all additive-first, reversible; no DROP until an optional post-soak Step 7)

Each DDL step is guarded by `SHOW COLUMNS`/`SHOW INDEX`/`SHOW TABLES` checks + a `get_option('tdwp_eil_step_N_done')` flag (matching the existing migration-flag pattern in `class-database-schema.php`).

- **Step 0 — Backup + baseline.** `wp db export` the six affected tables (`poker_tournament_players`, `poker_player_roi`, `tdwp_tournament_players`, `tdwp_tournament_seats`, `poker_tournament_costs`, `wp_postmeta`) outside web root. Record baseline metrics into a `tdwp_eil_baseline` option (COUNTs, SUM(winnings/buyins), per-tournament winner list, ROI sums).
- **Step 1 — Dedup + UNIQUE on `poker_player_roi`.** Dry-run count dups; delete keeping `MIN(id)`; add UNIQUE. Reconcile row counts. Rollback = drop index (dedup restorable from Step 0).
- **Step 2 — Add canonical columns** (`tournament_uuid`, `player_uuid`, `source` + 3 keys). Purely additive; live code ignores them. Rollback = drop columns.
- **Step 3 — Backfill UUID columns** for existing live rows from postmeta. Dry-run: count rows missing postmeta UUIDs. Reconcile: 0 empty UUIDs for finished tournaments.
- **Step 4 — Introduce single rollup writer behind an OFF feature flag.** `TDWP_Stats_Rollup::rebuild_tournament($uuid)` = generalized `project_to_stats_mart` + `project_to_roi_mart`. Existing writers still run; enables shadow compare.
- **Step 5 — Shadow dry-run + reconciliation (read-only, on staging clone).** Run rollup into `poker_tournament_players_shadow`; diff per-tournament row counts, `SUM(winnings)`, winner, ROI `net_profit`. **This is the gate that cannot be decided without real data.** Expect diffs from divergent buy-in strategies, re-entry aggregation, hardcoded rebuys/addons. User reviews before cutover.
- **Step 6 — Cut writers over (flag ON).** Repoint import + reimport + live to funnel through the canonical source; rollup becomes sole mart/ROI writer. Old writers stay flag-disabled for one release (instant rollback = flip flag).
- **Step 7 (OPTIONAL, deferred).** After a multi-week clean soak, delete `class-stats-bridge.php` projection + dead direct-mart inserts. **Do not DROP any table** — the `poker_*` marts remain as read model.
- **Standing reconciliation cron:** daily job re-runs the rollup for recently-finished tournaments and asserts mart == fresh aggregate of source; logs drift via `TDWP_Debug_Logger`. Replaces `dedup_participation_mart` self-heal.

---

## Reader/writer repoints

**Writers → funnel through canonical source + single rollup:**
- `admin/class-admin.php::insert_tournament_players` (~L3102), `admin/class-batch-processor.php::save_tournament_players` (~L638) / `update_existing_tournament` (~L484), `admin/class-migration-tools.php::sync_tournament_player_data` (~L153), `poker-tournament-import.php` reimport/reorder/delete paths (~L2745/2639/2470): write per-player rows into the canonical source (`source='import'`, `entry_number=1`, populate UUIDs), then call `TDWP_Stats_Rollup::rebuild_tournament($uuid)` instead of inserting into the mart directly. Add explicit column/format allowlists (current inserts check only `$result!==false`).
- `class-stats-bridge.php`: projection methods become `TDWP_Stats_Rollup`; `get_or_create_meta_uuid` moves to the shared canonical writer (called at source-write time).

**Read-model readers → NO change (schema preserved):** `class-statistics-engine.php` (~40 methods), `class-series-standings.php`, `class-shortcodes.php`, all three `templates/*.php`, `class-results-emailer.php`, and the `poker-tournament-import.php` winner/leaderboard readers.

**Latent-bug readers → must fix:** the four key-type bugs listed above.

**Player-merge writers → retarget to canonical:**
- `class-player-manager.php::merge_players` (~L343/354): after migration must also update `player_uuid` in the canonical source (+ re-run rollup), not just the two `poker_*` tables.
- `class-points-verifier.php::apply_formula` (~L449): writes `points` directly into the mart — see the ordering hazard in [Open Questions](#open-questions) #4.

**Admin registry / schema:** `class-data-mart-cleaner.php` `$data_mart_tables` registry keeps the mart tables (they survive); update hardcoded suffixes only if Step 7 renames anything. `class-database-schema.php` + `create_database_tables` gain the Step 1/2 DDL idempotently.

---

## Risks

- **CRITICAL — historical ROI accuracy is unknowable pre-dry-run.** Import-only tournaments may never have stored a real buy-in; rollup falls back to postmeta/$20 for those. Step 5 quantifies the diff. Cannot promise "identical ROI" without the real-data run.
- **CRITICAL — points overrides collide with a derived mart.** `apply_formula` writes `points` straight into `poker_tournament_players`. Once rollup-owned, the next rollup overwrites them unless overrides are sourced from `tdwp_points_adjustments` (which exists) and re-applied inside the rollup. If missed, manual adjustments silently vanish on next save.
- **HIGH — seat FK breakage.** `tdwp_tournament_players.id` is `registration_id` in `tdwp_tournament_seats`. Any step that renumbers `id` orphans seats. Mitigation: strictly additive ALTERs; never rebuild the canonical table.
- **HIGH — double-counting** if a tournament exists BOTH as imported `.tdt` and live under the same `tournament_uuid` (import synthetic entry + live per-entry rows). Must detect in dry-run.
- **HIGH — live-subsystem column drift the merge inherits:** three timestamp names (`bustout_timestamp` / `elimination_time` / `elimination_timestamp`), status vocab, exporter column names (`buyin_count`/`rebuy_count` vs `rebuys_count`/`addons_count`), two `player_id` join targets. The rollup must pick one canonical vocabulary.
- **MEDIUM** — inserts without explicit format/column allowlists silently fail if the source gains NOT NULL columns; non-prepared table-name-interpolated queries in `archive-tournament.php` (~L91/93) break on rename; migration-flag desync on rename; dbDelta can't add UNIQUE with existing dups (dedup must precede).

---

## Open questions (require user decision before execution)

1. **Physical endgame:** Confirm "one canonical source + derived read model" satisfies tdwp-eil (bridge *component* retired, an aggregation step remains) vs a literal single-table merge (strongly argued against by the reader map).
2. **Import per-entry policy:** Accept representing imported tournaments as one synthetic entry per player (`entry_number=1`)?
3. **Historical ROI buy-in:** For import-only tournaments with no stored paid amount, accept the postmeta/$20 fallback, or curate real buy-ins first? Bounds ROI accuracy.
4. **Points overrides:** Migrate manual points (`apply_formula`) to persist as `tdwp_points_adjustments` and re-apply via rollup? If not, the rollup must preserve existing `points`, which conflicts with a full recompute.
5. **Dual import/live tournaments:** Do any tournaments exist BOTH as imported `.tdt` and live under the same UUID? If so, define precedence (live wins? import wins?).
6. **Live vs mart consumers of the same name:** Confirm the `display-shortcode`/`get_tournament_data` bigint+`eliminated` readers should be repointed to the live source (i.e. they are broken today).
7. **Downtime tolerance:** Confirm a staging clone is available for the Step 5 shadow dry-run, and Step 6 cutover can run behind a feature flag with a maintenance window for the initial full backfill.

**Cannot be decided without a real-data dry run:** dup-row counts in `poker_player_roi` (Step 1) and residual mart dups; live rows missing postmeta UUIDs (Step 3); per-tournament sum/winner diffs (Step 5); count of import-only ROI rows on the fallback buy-in.

---

## Recommended phasing (incremental, feature-flagged, reversible; each phase shippable)

- **Phase A — Instrument & fix latent bugs (no schema change).** Fix the four key-type bugs (broken today regardless of the merge) + add baseline reconciliation logging. Low risk, immediate value, de-risks later diffing.
- **Phase B — ROI hardening (Steps 0-1).** Backup, dedup `poker_player_roi`, add UNIQUE. Independently valuable — fixes the dup bug even if the full merge is deferred.
- **Phase C — Canonical columns + backfill (Steps 2-3).** Additive; zero reader impact.
- **Phase D — Single rollup, shadow only (Steps 4-5).** Introduce `TDWP_Stats_Rollup` behind an OFF flag; diff against production on staging. **Gate:** user reviews the diff.
- **Phase E — Cutover write path (Step 6).** Flip flag; repoint writers. Old writers disabled → instant rollback. Run one release cycle with reconciliation cron.
- **Phase F — Retire bridge & dead writers (Step 7, optional).** Only after a multi-week clean soak. Keep all mart tables. **This is where tdwp-eil is formally satisfied.**

Stopping after any phase still delivers value (e.g. after B the ROI dup bug is fixed; after E the bridge is already redundant even before code deletion).
