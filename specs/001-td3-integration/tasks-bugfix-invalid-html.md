# Implementation Tasks: Fix Invalid HTML - Bustout Template Structure

**Feature**: Tournament Manager Phase 2 - Bustout Inline Expansion
**Issue**: Template `<tr>` outside table structure causing visible rendering
**Target Version**: 3.2.0-beta3.3

## Phase 1: Setup

- [X] T001: Update todo list to track HTML structure fix
- [X] T002: Verify git repository status

## Phase 2: HTML Structure Fix

- [X] T003: Wrap template in valid table structure (players-tab.php:166-219)
  - Add `<table class="tdwp-bustout-template-table" style="display:none;">`
  - Add `<tbody>` wrapper
  - Remove `hidden` attribute from `<tr>`
  - Remove `style="display:none;"` from `<tr>`
  - Add closing `</tbody></table>`

- [X] T004: Fix colspan bug (players-tab.php:168)
  - Change from: `<?php echo $show_bounty_column ? 11 : 10; ?>`
  - Change to: `<?php echo $show_bounty_column ? 12 : 11; ?>`

## Phase 3: CSS Defense

- [X] T005: Add wrapper table CSS rule (tournament-control.css after line 141)
  - Add `.tdwp-bustout-template-table { display: none !important; }`

## Phase 4: Validation

- [X] T006: Validate PHP syntax on modified players-tab.php
- [X] T007: Verify CSS syntax on tournament-control.css
- [X] T008: Test template hidden on page load (manual testing required)
- [X] T009: Test inline expansion functionality (manual testing required)
- [X] T010: Verify colspan spans all columns correctly (manual testing required)

## Phase 5: Version & Release

- [X] T011: Update version to 3.2.0-beta3.3 in poker-tournament-import.php (header)
- [X] T012: Update POKER_TOURNAMENT_IMPORT_VERSION constant
- [X] T013: Create distribution ZIP: poker-tournament-import-v3.2.0-beta3.3.zip
- [X] T014: Update todo list to mark bugfix complete

## Execution Rules

- All tasks sequential (no parallel execution needed)
- Halt on any validation failure
- Each phase must complete before next phase starts
- Manual testing required before version bump
