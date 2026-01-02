# Implementation Plan: Fix Tournament Formula Manager Page Interactions

**Branch**: `005-formula-manager` | **Date**: 2025-01-02 | **Status**: ✅ **COMPLETE**
**Spec**: [spec.md](spec.md)

## Completion Notice

**Date Completed**: 2025-01-02
**Implementation Status**: ✅ All core functionality restored and tested
**Distribution**: v3.5.0-beta36 created

### What Was Accomplished

1. **JavaScript Modal Functions**: Added all missing modal functions (`openFormulaModal`, `closeFormulaModal`, `openVariableReferenceModal`, `closeVariableReferenceModal`, `deleteFormula`)
2. **Formula Field Bug Fix**: Fixed critical bug where formula changes reverted after save (missing `wp_unslash()` in AJAX handler)
3. **Dependencies Field Implementation**: Completely broken dependencies field now functional (both save and load directions)
4. **Tab Navigation**: Implemented variable reference modal tab switching
5. **AJAX Backend**: Full AJAX save/delete handlers implemented with nonce verification and validation

### Files Modified

- `wordpress-plugin/poker-tournament-import/admin/class-admin.php` (3 locations: lines 4159, 4160, 4599)
- `wordpress-plugin/poker-tournament-import/assets/js/formula-manager.js` (added 230+ lines of functionality)
- `wordpress-plugin/poker-tournament-import/poker-tournament-import.php` (version bumped to 3.5.0-beta36)

### Testing

All 4 user stories tested and verified working:
- ✅ User Story 1: Edit Existing Formula
- ✅ User Story 2: View Variable Reference Documentation
- ✅ User Story 3: Create New Formula
- ✅ User Story 4: Delete Custom Formula

### Additional Fixes Beyond Original Spec

During implementation, discovered and fixed two critical bugs not in original spec:
1. **Formula field backslash bug**: WordPress magic quotes causing formulas to save with escaped quotes
2. **Dependencies field completely non-functional**: Hardcoded to empty array, never captured user input

Both bugs fixed and tested.

---

## Summary (Original)

Fix two critical issues with Tournament Formula Manager page:
1. Missing JavaScript modal functions preventing all interactions (Edit, Delete, Variable Reference)
2. Formula field changes reverting after save (data not persisting)

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: [e.g., Python 3.11, Swift 5.9, Rust 1.75 or NEEDS CLARIFICATION]  
**Primary Dependencies**: [e.g., FastAPI, UIKit, LLVM or NEEDS CLARIFICATION]  
**Storage**: [if applicable, e.g., PostgreSQL, CoreData, files or N/A]  
**Testing**: [e.g., pytest, XCTest, cargo test or NEEDS CLARIFICATION]  
**Target Platform**: [e.g., Linux server, iOS 15+, WASM or NEEDS CLARIFICATION]
**Project Type**: [single/web/mobile - determines source structure]  
**Performance Goals**: [domain-specific, e.g., 1000 req/s, 10k lines/sec, 60 fps or NEEDS CLARIFICATION]  
**Constraints**: [domain-specific, e.g., <200ms p95, <100MB memory, offline-capable or NEEDS CLARIFICATION]  
**Scale/Scope**: [domain-specific, e.g., 10k users, 1M LOC, 50 screens or NEEDS CLARIFICATION]

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

[Gates determined based on constitution file]

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
# [REMOVE IF UNUSED] Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# [REMOVE IF UNUSED] Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# [REMOVE IF UNUSED] Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure: feature modules, UI flows, platform tests]
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
