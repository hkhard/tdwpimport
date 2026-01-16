# Implementation Plan: Active Formula Selection and Season Points Display

**Branch**: `009-active-formula-selection` | **Date**: 2025-01-02 | **Spec**: [spec.md](./spec.md)

## Summary

Add active formula selection to the formula manager for both tournament points and season standings calculations. Fix bubble calculation by ensuring `paid_positions` meta is populated during import. Enable season formulas to access full tournament-level variables with graceful undefined handling. Implement lazy recalculation for season standings when active formula changes.

**Technical Approach**:
- Store active formula keys in WordPress options (`poker_active_tournament_formula`, `poker_active_season_formula`)
- Add checkbox controls to formula manager UI with mutual exclusion
- Extend season formula evaluation to support per-tournament variables
- Fix parser to set `paid_positions` post meta during tournament import
- Implement lazy recalculation with transient caching

## Technical Context

**Language/Version**: PHP 8.0+
**Primary Dependencies**: WordPress 6.0+, jQuery (already enqueued)
**Storage**: WordPress options table (active formulas), WordPress post meta (tournament data), WordPress transients (season standings cache)
**Testing**: WordPress unit tests (PHPUnit), manual browser testing
**Target Platform**: WordPress admin panel (formula manager), public-facing (season leaderboard)
**Project Type**: WordPress plugin (single project with admin + frontend)
**Performance Goals**: <200ms AJAX response, <5s season leaderboard load on first view after formula change
**Constraints**: Must work with existing Tournament Director formula syntax, backward compatible with existing formulas
**Scale/Scope**: 10-100 formulas, 10-1000 tournaments per season, 10-500 players

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: ✅ PASS - No constitution violations identified

This feature extends existing WordPress plugin functionality without introducing new architectural patterns or dependencies. All implementation follows established WordPress patterns and plugin coding standards.

## Project Structure

### Documentation (this feature)

```text
specs/009-active-formula-selection/
├── plan.md              # This file
├── research.md          # Phase 0 output (complete)
├── data-model.md        # Phase 1 output (complete)
├── quickstart.md        # Phase 1 output (complete)
├── contracts/           # Phase 1 output (complete)
│   └── ajax-api.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created yet)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── admin/
│   ├── class-admin.php                          # Modify: Add cache clearing hooks
│   └── formula-manager-page.php                 # Modify: Add active formula checkboxes
├── includes/
│   ├── class-active-formula-manager.php        # NEW: Manage active formula state
│   ├── class-formula-validator.php              # Modify: Add active formula validation
│   ├── class-parser.php                         # Modify: Fix paid_positions meta
│   └── class-series-standings.php               # Modify: Use active season formula
└── assets/
    ├── js/
    │   ├── formula-manager.js                   # Modify: Add checkbox handlers
    │   └── active-formula-handler.js            # NEW: Active formula AJAX client
    └── css/
        └── formula-manager.css                  # Modify: Add active state styles
```

**Structure Decision**: Single WordPress plugin with admin (formula manager) and frontend (season leaderboard) components. Follows existing plugin structure with no new architectural patterns introduced.

## Complexity Tracking

> No complexity violations. This feature extends existing functionality without introducing new patterns.

## Phase 0: Research & Technical Analysis

**Status**: ✅ COMPLETE

### Research Tasks Completed

1. **Formula Storage System**: Located in `tdwp_tournament_formulas` option, managed by `Poker_Tournament_Formula_Validator`
2. **Bubble Calculation**: Found in `class-series-standings.php:304`, logic is correct but `paid_positions` meta not populated
3. **Season Standings**: Uses pre-aggregated data, needs extension for per-tournament variable access
4. **Formula Manager UI**: Modal-based editor in `formula-manager-page.php`, uses AJAX for save/delete

### Key Findings

- **Root Cause of Bubble=0**: Parser doesn't set `paid_positions` post meta during import
- **Formula Storage**: Array structure keyed by formula_key, category stored in each formula
- **Season Formula Limitation**: Currently uses pre-aggregates only, needs per-tournament evaluation
- **UI Components**: Formula cards rendered in PHP, JavaScript handles edit/delete interactions

### Decisions Made

1. **Active Formula Storage**: Separate WordPress options (not in formula array)
2. **UI Controls**: Checkboxes with mutual exclusion (radio-like behavior)
3. **Bubble Fix**: Fix parser to set `paid_positions`, not logic change
4. **Season Variables**: Per-tournament evaluation with graceful undefined handling
5. **Recalculation**: Lazy calculation on leaderboard view with cache clearing

**Output**: See [research.md](./research.md) for complete findings.

## Phase 1: Design & Artifacts

**Status**: ✅ COMPLETE

### Data Model

**Created**: [data-model.md](./data-model.md)

**New Storage**:
- `poker_active_tournament_formula` - Active tournament formula key
- `poker_active_season_formula` - Active season formula key

**Schema Extensions**:
- No changes to formula schema (active status separate)
- Enhanced season standings calculation context
- Verified `paid_positions` post meta requirement

**Cache Keys**:
- `poker_season_standings_{series_id}_{formula_key}` - Per-series/per-formula cache

### API Contracts

**Created**: [contracts/ajax-api.md](./contracts/ajax-api.md)

**New Endpoints**:
1. `wp_ajax_set_active_formula` - Set active formula for category
2. `wp_ajax_get_active_formula` - Get current active formula
3. `wp_ajax_clear_formula_cache` - Clear season standings cache

**UI Components**:
- Formula card checkbox controls
- Active formula visual indicator (badge/highlight)
- JavaScript client helper class

### Quickstart Guide

**Created**: [quickstart.md](./quickstart.md)

**Includes**:
- Implementation checklist (Phase 1-4)
- Code snippets for common tasks
- Testing procedures
- Debugging guide
- Common issues & solutions

## Phase 2: Task Breakdown

**Status**: ⏳ NOT STARTED (use `/speckit.tasks` to generate)

**Planned Tasks** (high-level):

### Database & Options
1. Create active formula option keys on plugin activation
2. Migration script to verify `paid_positions` meta

### Backend Implementation
3. Create `class-active-formula-manager.php`
4. Implement AJAX handlers (set/get/clear)
5. Update parser to set `paid_positions` meta
6. Update tournament import to use active formula
7. Update season standings to use active season formula
8. Add cache clearing hooks

### Frontend Implementation
9. Add checkbox controls to formula cards
10. Implement mutual exclusion JavaScript
11. Add AJAX handlers for checkbox changes
12. Add visual indicators (CSS)
13. Add error handling and user feedback

### Testing
14. Unit tests for AJAX handlers
15. Integration tests for formula switching
16. Manual testing with sample data
17. Verify bubble counts display correctly
18. Performance testing (AJAX response time, cache)

### Documentation
19. Update admin documentation
20. Add inline code comments
21. Update user guide

## Phase 3: Implementation

**Status**: ⏳ NOT STARTED

**Order of Implementation**:
1. Database & Options (Phase 2, tasks 1-2)
2. Backend Core (Phase 2, tasks 3-8)
3. Frontend UI (Phase 2, tasks 9-13)
4. Testing (Phase 2, tasks 14-18)
5. Documentation (Phase 2, tasks 19-21)

**Dependencies**:
- Backend must be complete before frontend AJAX integration
- Testing depends on both backend and frontend completion
- Documentation can happen in parallel with implementation

## Phase 4: Verification

**Status**: ⏳ NOT STARTED

**Success Criteria** (from spec):
- [ ] SC-001: Admin can change active formula in under 10 seconds
- [ ] SC-002: Season leaderboard displays season points for 100% of players
- [ ] SC-003: Switching active formula updates season points within 5 seconds
- [ ] SC-004: Bubble count accurately reflects statistics
- [ ] SC-005: Formula manager clearly indicates active formulas
- [ ] SC-006: No PHP errors when season formula references undefined variables
- [ ] SC-007: Season points produces valid numeric output for all players

**Acceptance Scenarios**: See [spec.md](./spec.md) User Stories 1-4

## Risk Assessment

### Technical Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Parser changes break existing import | HIGH | Thorough testing, backup existing parser |
| Season formula performance degradation | MEDIUM | Caching, lazy recalculation |
| AJAX nonce conflicts with existing handlers | LOW | Use unique nonce name |
| Database migration issues | LOW | Idempotent migration script |

### User Experience Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Confusing checkbox behavior (mutual exclusion) | MEDIUM | Clear UI labels, tooltips |
| Slow season leaderboard after formula change | MEDIUM | Show loading indicator, cache results |
| Lost formula selections (plugin update) | LOW | Migration script preserves options |

## Next Steps

1. **Generate Tasks**: Run `/speckit.tasks` to create detailed task breakdown
2. **Start Implementation**: Begin with Phase 2 database tasks
3. **Reference Documents**:
   - [quickstart.md](./quickstart.md) - Implementation guide
   - [data-model.md](./data-model.md) - Data structures
   - [contracts/ajax-api.md](./contracts/ajax-api.md) - API documentation
   - [research.md](./research.md) - Technical context

---

**Plan Version**: 1.0
**Last Updated**: 2025-01-02
**Status**: Ready for task generation
