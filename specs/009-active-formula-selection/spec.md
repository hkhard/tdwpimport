# Feature Specification: Active Formula Selection and Season Points Display

**Feature Branch**: `009-active-formula-selection`
**Created**: 2025-01-02
**Status**: Draft
**Input**: User description: "in formula manager, we need a check box for which formula is the active one, both for tournament points and for season points. The season points formula output, regardelss of configured variables. this season points needs to surface in the season_leaaderboard Season points column. The bubble calculation returns 0 for all, the bubble is the last player who don't get money."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Active Tournament Formula Selection (Priority: P1)

As a league administrator, I need to designate which formula should be used for calculating tournament points so that the system automatically applies the correct scoring formula when tournament results are imported.

**Why this priority**: Critical for scoring consistency - without a designated active formula, the system cannot calculate tournament points reliably.

**Independent Test**: Can be fully tested by creating multiple formulas, checking one as active, importing a tournament result, and verifying the tournament points column uses the selected formula.

**Acceptance Scenarios**:

1. **Given** multiple tournament formulas exist in the formula manager, **When** administrator checks the "Active" checkbox on one formula, **Then** that formula is visually marked as active and all other tournament formulas have their active checkbox unchecked
2. **Given** an active tournament formula is selected, **When** a tournament .tdt file is imported, **Then** the tournament points are calculated using the active formula (not a default formula)
3. **Given** a formula is marked as active, **When** the formula is deleted, **Then** the system prompts to select a new active formula or defaults to the first available formula

---

### User Story 2 - Active Season Formula Selection (Priority: P1)

As a league administrator, I need to designate which formula should be used for calculating season standings points so that the season leaderboard reflects the correct ranking methodology.

**Why this priority**: Critical for season-long rankings - season standings are a key feature for player engagement and tracking performance over time.

**Independent Test**: Can be fully tested by creating multiple season formulas, checking one as active, viewing the season leaderboard, and verifying the Season Points column uses the selected formula.

**Acceptance Scenarios**:

1. **Given** multiple season formulas exist in the formula manager, **When** administrator checks the "Active" checkbox on one season formula, **Then** that formula is visually marked as active and all other season formulas have their active checkbox unchecked
2. **Given** an active season formula is selected, **When** season leaderboard is displayed, **Then** the Season Points column shows values calculated using the active season formula
3. **Given** no season formula is marked as active, **When** season leaderboard is displayed, **Then** the system defaults to using a direct sum of tournament points

---

### User Story 3 - Season Points Display Regardless of Variables (Priority: P1)

As a player viewing the season leaderboard, I need to see my season points calculated based on the active season formula regardless of which variables are configured in that formula so that I can understand my true standing in the season.

**Why this priority**: Core value proposition - players need to see their season performance. The current system may not display season points if certain variables aren't configured, which breaks the leaderboard.

**Independent Test**: Can be fully tested by configuring a season formula with various dependencies, viewing the season leaderboard, and confirming season points display even when some variables have no values.

**Acceptance Scenarios**:

1. **Given** a season formula is marked as active and contains variables (e.g., avgBC, T33, T80), **When** the season leaderboard is rendered, **Then** the Season Points column displays calculated values using available data and treats missing variables as zero or null based on formula configuration
2. **Given** a season formula references a variable with no data (e.g., no tournaments have avgBC calculated), **When** season points are calculated, **Then** the calculation completes without error and produces a result (possibly zero or partial)
3. **Given** the active season formula is changed, **When** the season leaderboard is refreshed, **Then** all season points are recalculated using the new active formula

---

### User Story 4 - Bubble Calculation Fix (Priority: P2)

As a league administrator, I need to see accurate bubble counts in the season leaderboard so that players can track how often they finished just outside the money.

**Why this priority**: Important for player statistics but not blocking - bubble is a nice-to-have stat that builds player engagement but doesn't affect core scoring.

**Independent Test**: Can be fully tested by importing tournament results with known paid positions, viewing player profiles, and verifying bubble_count reflects the number of times each player finished in the last unpaid position.

**Acceptance Scenarios**:

1. **Given** a tournament with 5 paid positions, **When** a player finishes in 6th place, **Then** that player's bubble_count increases by 1
2. **Given** a tournament with variable paid positions (e.g., top 10% or top 3 tables), **When** results are imported, **Then** bubble_count is calculated based on the actual paid positions for that tournament
3. **Given** the current bubble calculation returns 0 for all players, **When** the fix is deployed, **Then** bubble_count shows accurate non-zero values for players who finished outside paid positions

---

### Edge Cases

- What happens when all formulas are deleted and no active formula exists?
- What happens when the active formula is invalid (syntax errors, division by zero)?
- How does the system handle switching between active formulas mid-season (historical data recalculation)?
- What happens when a tournament has no paid positions defined (everyone gets paid or nobody gets paid)?
- How does bubble calculation work for tournaments with 0 paid positions?
- What happens when season formula references variables that don't exist in any tournament?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a checkbox control in the formula manager for marking a single formula as "Active" for tournament points calculations
- **FR-002**: System MUST enforce that only one tournament formula can be marked as active at any time (automatically unchecking others when one is selected)
- **FR-003**: System MUST persist the active tournament formula key in WordPress options (e.g., `poker_active_tournament_formula`)
- **FR-004**: System MUST provide a checkbox control in the formula manager for marking a single formula as "Active" for season points calculations
- **FR-005**: System MUST enforce that only one season formula can be marked as active at any time (automatically unchecking others when one is selected)
- **FR-006**: System MUST persist the active season formula key in WordPress options (e.g., `poker_active_season_formula`)
- **FR-007**: System MUST use the active tournament formula when calculating points for newly imported tournaments
- **FR-008**: System MUST use the active season formula when rendering the Season Points column in the season leaderboard
- **FR-009**: System MUST calculate and display season points even when some formula variables have no values (treating missing variables as zero or using graceful degradation)
- **FR-010**: System MUST visually indicate which formulas are currently active (e.g., badge, highlight, checkbox state)
- **FR-011**: System MUST default to the first available formula if no active formula is selected and formula is required
- **FR-012**: System MUST recalculate season points for all players when the active season formula changes
- **FR-013**: System MUST fix the bubble calculation logic to accurately count players who finished in the last unpaid position (bubble position = paid_positions + 1)

### Key Entities

- **Formula**: A reusable calculation expression with metadata (name, description, category, expression, dependencies, active status)
  - Categories: "points" (tournament), "season" (standings), "custom"
  - Active status: boolean flag for tournament and season independently
  - Expression: Tournament Director formula syntax
  - Dependencies: Intermediate variables calculated before the main formula

- **Active Formula Selection**: Persistent configuration indicating which formula to use for specific calculations
  - Tournament active formula: Used when importing tournaments
  - Season active formula: Used when calculating season standings
  - Storage: WordPress options table

- **Season Points**: Calculated aggregate score for a player across a tournament series
  - Source: Active season formula applied to tournament results
  - Variables: May reference tournament-level aggregates (total_points, avg_finish, best_finish, etc.)
  - Display: Column in season leaderboard

- **Bubble Count**: Number of times a player finished in the last position outside paid spots
  - Calculation: finish_position == paid_positions + 1
  - Per-player aggregate across tournaments in a series
  - Display: Column in season leaderboard

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can change the active formula in under 10 seconds (navigate to formula manager, check checkbox, save)
- **SC-002**: Season leaderboard displays season points for 100% of players who participated in at least one tournament
- **SC-003**: Switching active season formula updates all season points within 5 seconds on page refresh
- **SC-004**: Bubble count accurately reflects player statistics (verified by manual calculation on sample data)
- **SC-005**: Formula manager clearly indicates active formulas with visual distinction (color, badge, or icon)
- **SC-006**: No PHP errors or warnings occur when season formula references undefined variables
- **SC-007**: Season points calculation produces valid numeric output (not null, not NaN) for all players with tournament results

## Assumptions

- Formulas are categorized as "points" (tournament) or "season" (standings) based on a dropdown selection in the formula manager
- Active formula state is stored separately from the formula definition itself (in WordPress options, not in the formula array)
- Season leaderboard already exists and has a "Season Points" column that needs to be populated
- Bubble calculation logic exists in `class-series-standings.php` around line 304 but may not be working correctly due to paid_positions not being set
- Formula manager UI uses checkboxes or radio buttons for formula selection (existing implementation may need modification)
- Tournament import process already hooks into formula calculation (just needs to use the active formula)

## Key Decisions

### Bubble Position Definition
**Decision**: Use traditional poker bubble definition - single position immediately outside the money (finish_position == paid_positions + 1)

**Rationale**: This is the standard poker terminology and aligns with existing code logic. The reported issue (bubble returns 0 for all) likely indicates that paid_positions is not being set correctly during tournament import, not a logic error in the bubble calculation itself.

**Implication**: Debug focus should be on ensuring paid_positions meta field is populated during tournament import from .tdt files.

### Season Formula Variable Scope
**Decision**: Season formulas have full access to tournament-level variables (n, r, hits, monies, avgBC, T33, T80, etc.) applied on a per-tournament basis

**Rationale**: Maximum flexibility for season scoring formulas. League administrators may want to create sophisticated season calculations that consider per-tournament performance metrics.

**Implication**: Season formula evaluator must:
- Run the formula for each tournament result individually
- Handle cases where variables are undefined (treat as zero or null based on formula context)
- Aggregate results across all tournaments for the final season score

### Historical Recalculation Strategy
**Decision**: Lazy calculation - recalculate season statistics only when the season leaderboard is viewed

**Rationale**: Balances admin panel responsiveness with data freshness. Changing active formulas is infrequent, and season leaderboard views are more common than formula changes. First view after formula change may be slower but acceptable.

**Implication**: When active season formula changes:
- Update the WordPress option storing the active formula key
- Clear any cached season statistics transients
- Next season leaderboard view triggers fresh calculation using new formula
- No background processing or admin blocking operations needed
