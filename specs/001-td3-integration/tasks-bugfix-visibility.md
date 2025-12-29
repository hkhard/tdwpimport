# Implementation Tasks: Fix Bustout Template Visibility Bug

**Feature**: Tournament Manager Phase 2 - Bustout Inline Expansion
**Issue**: Template row constantly visible
**Target Version**: 3.2.0-beta3.2

## Phase 1: Setup

- [ ] T001: Update todo list to track bugfix progress
- [ ] T002: Verify git repository status

## Phase 2: CSS Fix

- [ ] T003: Add !important flag to .tdwp-bustout-inline-template CSS rule
  - File: `wordpress-plugin/poker-tournament-import/assets/css/tournament-control.css`
  - Line: 139-141
  - Change: Add `!important` to `display: none;`

## Phase 3: HTML Semantic Backup

- [ ] T004: Add hidden attribute to template row
  - File: `wordpress-plugin/poker-tournament-import/admin/tabs/players-tab.php`
  - Line: 167
  - Change: Add `hidden` attribute to `<tr>` element

## Phase 4: Validation

- [ ] T005: Validate PHP syntax on modified files
- [ ] T006: Verify CSS syntax

## Phase 5: Version & Release

- [ ] T007: Update version to 3.2.0-beta3.2 in poker-tournament-import.php (header)
- [ ] T008: Update POKER_TOURNAMENT_IMPORT_VERSION constant
- [ ] T009: Create distribution ZIP: poker-tournament-import-v3.2.0-beta3.2.zip
- [ ] T010: Update todo list to mark bugfix complete

## Execution Rules

- All tasks sequential (no parallel execution needed)
- Halt on any validation failure
- Each phase must complete before next phase starts
