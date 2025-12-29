# Phase 2 Completion Tasks - TD3 Integration

**Feature**: Tournament Director 3 Integration - Phase 2 Completion
**Branch**: 001-td3-integration
**Spec**: specs/001-td3-integration/spec.md
**Plan**: specs/001-td3-integration/plan.md
**Tasks**: specs/001-td3-integration/tasks.md

**Total Tasks**: 97 tasks across 5 phases
**Estimated Time**: 25 hours (sequential) | 15-18 hours (2 developers parallel)
**Target Version**: v3.2.0-beta1

## Scope

**Completing**: User Story 5 (Player Operations) + User Story 6 (Statistics & Export)
**Already Implemented**: US1-4 (templates, registration, clock, table management) in v3.1.0-beta19

## Phase Structure

### Phase 1: Setup & Dependencies (3 tasks)
- T001: Install TCPDF library via composer
- T002: Verify PHP 8.0+ syntax compatibility
- T003: Verify database schema v3.1.2

### Phase 2: Foundational - BLOCKS ALL USER STORIES (6 tasks)
- T004-T006: Create wp_poker_transactions table with indexes
- T007-T008: Extend wp_poker_tournament_players and wp_poker_tournament_live_state tables
- T009: Update database version to v3.2.0

### Phase 3: User Story 5 - Player Operations (18 tasks)

**New Classes**:
- `class-transaction-logger.php` - Immutable transaction log (T010, T012-T013)
- `class-player-operations.php` - Bust-out/rebuy/add-on handlers (T011, T014-T017)

**Key Tasks**:
- T010-T011: Create class files (parallel)
- T012-T017: Implement transaction logging and player operations methods
- T018-T021: Add AJAX handlers (tdwp_process_bustout, rebuy, addon, chip_adjustment)
- T022: Add nonce verification and capability checks
- T023-T027: Build transaction log viewer UI and JavaScript handlers

### Phase 4: User Story 6 - Statistics & Export (41 tasks)

**New Classes** (can run in parallel):
- `class-statistics-engine.php` - Live stats with 15s cache (T028-T031)
- `class-export-manager.php` - Export coordination (T036, T040)
- `class-pdf-exporter.php` - PDF generation via TCPDF (T037, T041-T042)
- `class-csv-exporter.php` - CSV export (T038, T043)
- `class-tdt-exporter.php` - TD3 format export (T039, T044)
- `class-email-queue.php` - Async email batching (T050-T054)
- `class-tournament-archiver.php` - Archive/unarchive logic (T059-T062)

**Key Task Groups**:

*Statistics* (T028-T035):
- Calculate chip leader, average stack, bubble position
- 15s transient cache (key: tdwp_live_stats_{tournament_id})
- Auto-refresh dashboard UI

*Exports* (T036-T049):
- PDF with HTML template support and {{tokens}}
- CSV with streaming for 1000+ players
- TDT format for TD3 compatibility
- Temp file management with 1-hour cleanup

*Email Queue* (T050-T058):
- wp_schedule_single_event batch processing (50 emails/batch)
- Retry logic (max 3 retries, 5-minute delay)
- Status tracking (pending/sending/sent/failed)

*Archival* (T059-T068):
- Post meta '_tournament_archived' flag
- pre_get_posts filter to exclude from lists
- Read-only enforcement on archived tournaments

### Phase 5: Polish & Constitution Compliance (29 tasks)

**Constitution Compliance** (T069-T076):
- T069: PHP syntax validation (`php -l`)
- T070-T072: Security validation (nonces, capabilities, prepared statements)
- T073-T074: Testing with representative data (100-500 players)
- T075-T076: Performance validation (caching, async processing)

**Code Quality** (T077-T084):
- T077-T080: PHPDoc, i18n, code cleanup
- T081-T084: Generate documentation (research.md, data-model.md, quickstart.md, API contracts)

**Performance Testing** (T085-T092):
- T085: Player operations <1s response time
- T086: Statistics calculation <2s for 500 players
- T087: PDF export <10s for 200 players
- T088-T092: Streaming, email queue, cache invalidation, archive enforcement

**Release** (T093-T097):
- T093-T094: Update version to v3.2.0-beta1, changelog
- T095-T097: Create ZIP, test installation

## Critical Dependencies

1. **Phase 2 blocks Phase 3-4**: Database schema must be complete before any user story work
2. **US6 Statistics depends on US5**: Transaction Logger must exist for statistics to query transaction data
3. **Composer required**: TCPDF library must be installed before PDF export (T001 → T037)

## Parallel Execution Opportunities

**Can run in parallel** (marked [P] in tasks.md):
- Phase 1: T001-T003 (3 tasks)
- US5: T010 and T011 (class creation)
- US6: T028, T036-T039, T050, T059 (9 files)
- Documentation: T081-T084 (4 files)

## New Database Entities

**wp_poker_transactions** (T004-T006):
- Append-only audit log
- Fields: tournament_id, player_id, transaction_type ENUM, amount, chips, reason, actor_user_id, created_at
- Indexes: tournament_id, player_id, transaction_type, created_at

**Extended wp_poker_tournament_players** (T007):
- status ENUM, current_chips, finish_position, prize_amount, rebuy_count, addon_count

**Extended wp_poker_tournament_live_state** (T008):
- current_chips_total, prize_pool_current, rebuys_count, addons_count, busted_players_count

## Implementation Strategy

**Recommended Sequential Path**:
1. Phase 1: Setup (~30 min)
2. Phase 2: Foundational (~1 hour) → **VALIDATE before continuing**
3. Phase 3: US5 Player Operations (~8 hours) → **VALIDATE transactions working**
4. Phase 4: US6 Statistics & Export (~12 hours) → **VALIDATE all exports**
5. Phase 5: Polish (~4 hours) → **VALIDATE constitution compliance**

**Checkpoints**:
- After Phase 2: Database ready, proceed to user stories
- After US5: Player operations functional, transaction log verified
- After US6: Statistics, exports, emails, archival all working
- After Phase 5: Ready for v3.2.0-beta1 release

## Key Technical Decisions (from plan.md)

1. **Transaction Log**: Simple append-only DB table (no triggers, WordPress-compatible)
2. **PDF Export**: TCPDF library (memory efficient, GPL compatible)
3. **Statistics Cache**: 15s transient (balance freshness/performance)
4. **Email Queue**: wp_schedule_single_event batching (no dependencies)
5. **Security**: All AJAX handlers require nonces + capability checks
6. **Performance**: <1s operations, <10s exports, <512MB memory for 500 players

## Next Phase Preview

After Phase 2 completion (v3.2.0), Phase 3 features planned:
- Custom display builder (React drag-and-drop)
- Event trigger system (WordPress action hooks)
- League management
- Token rendering system ({{tournament_name}}, etc.)
- Receipt generation and badge printing

**Created**: 2025-10-30
**Last Updated**: 2025-10-30
