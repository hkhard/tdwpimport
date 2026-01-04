# Implementation Plan: Always Show All Players in Detailed Season Leaderboard

**Branch**: `011-season-leaderboard-all-players` | **Date**: January 4, 2026 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/011-season-leaderboard-all-players/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Modify the season_leaderboard shortcode to always display all registered players when `show_details="true"` is used, regardless of whether a limit parameter is explicitly provided. Currently, the limit is only ignored when show_details=true AND no explicit limit is set. The fix is a simple 3-line code change removing the `!isset($atts['limit'])` condition.

## Technical Context

**Language/Version**: PHP 8.0+ (8.2+ compatible)
**Primary Dependencies**: WordPress 6.0+, WordPress Shortcode API
**Storage**: WordPress MySQL database (wp_ prefix, custom wp_poker_tournament_players table)
**Testing**: Manual testing via WordPress admin interface
**Target Platform**: WordPress production server (www.oldertardfello.ws)
**Project Type**: WordPress plugin (single monolithic plugin)
**Performance Goals**: <5 second page load for 100+ player detailed views
**Constraints**: Must maintain backward compatibility with existing shortcode usage
**Scale/Scope**: Small enhancement - single conditional logic change in existing shortcode handler

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Status**: ⚠️ CONSTITUTION NOT YET RATIFIED

The project constitution (.specify/memory/constitution.md) is currently a template and has not been customized for this codebase. No gates to evaluate at this time.

**Post-Design Check**: Will verify simplicity principle - this is a minimal change (3 lines) that solves the user's problem without introducing complexity.

## Project Structure

### Documentation (this feature)

```text
specs/011-season-leaderboard-all-players/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
wordpress-plugin/poker-tournament-import/
├── includes/
│   ├── class-shortcodes.php       # PRIMARY FILE - lines 2443-2446 to modify
│   ├── class-series-standings.php # Season standings calculator (no changes needed)
│   └── class-parser.php           # Tournament file parser (no changes needed)
└── assets/
    ├── css/
    │   └── frontend.css           # Styling for shortcode output (no changes needed)
    └── js/
        └── frontend.js            # Frontend JavaScript (no changes needed)
```

**Structure Decision**: Single WordPress plugin with existing shortcode infrastructure. Only class-shortcodes.php requires modification.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| N/A | No violations | This is a minimal 3-line change with no complexity added |

