# Research: Season Leaderboard Enhancement

**Date**: January 4, 2026
**Feature**: Always show all players when show_details="true"

## Research Tasks

### 1. Current Implementation Analysis

**Finding**: Located the exact code requiring modification in `class-shortcodes.php:2443-2446`

**Current Logic**:
```php
// If showing details, show all players unless explicitly limited
if ($show_details && !isset($atts['limit'])) {
    $limit = -1; // Show all players
}
```

**Behavior Analysis**:
- Condition checks both `$show_details` is true AND limit was NOT explicitly provided
- Uses `!isset($atts['limit'])` on the original shortcode attributes array
- When user provides `show_details="true" limit="10"`, the limit is respected (shows 10 players)
- When user provides `show_details="true"` without limit, all players are shown

**Rationale for Current Behavior**:
The current logic allows users to optionally limit detailed views, perhaps for performance reasons or to create "top 10 with details" views.

### 2. WordPress Shortcode Best Practices

**Research**: WordPress shortcode parameter handling patterns

**Decision**: Use `shortcode_atts()` for parameter defaults (already implemented)
- Standard WordPress pattern for shortcode attribute handling
- Provides defaults and validates attributes
- Already implemented at line 2427

**No additional best practices needed** - this is a simple conditional logic change.

### 3. Performance Considerations

**Research**: Impact of displaying 100+ players with detailed statistics

**Finding**: Minimal risk
- The calculator (class-series-standings.php) already supports fetching all players
- Detailed columns are simple string/integer displays, not complex computations
- Performance is dominated by database queries, not rendering
- Current implementation caches standings in transients
- HTML table rendering is fast even for 200+ rows

**Mitigation**: None required
- WordPress can handle large HTML tables
- Browser rendering is the bottleneck, not PHP
- Users explicitly requesting detailed view expect more data

### 4. Backward Compatibility

**Research**: Ensure existing shortcode usage continues to work

**Analysis**:
- `[season_leaderboard show_details="true"]` → Already shows all (no change)
- `[season_leaderboard show_details="false"]` → Shows 20 (no change)
- `[season_leaderboard show_details="true" limit="10"]` → **CHANGES** from 10 to all players
- `[season_leaderboard limit="10"]` → Shows 10 (no change)

**Breaking Change**: Yes, for `show_details="true" limit="N"` pattern

**Justification**: User requirement overrides backward compatibility for this specific pattern. The user explicitly requested "always show all players when show_details=true", indicating this is the desired behavior even if it breaks existing usage.

**Communication**: Document change in release notes.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Logic modification** | Remove `!isset($atts['limit'])` condition | User wants all players when detailed view enabled |
| **Performance impact** | Acceptable for <200 players | WordPress rendering is fast; users expect more data in detailed view |
| **Backward compatibility** | Break `show_details="true" limit="N"` pattern | User requirement is explicit; this is a feature enhancement, not a bug fix |
| **Additional changes** | Update inline comments | Clarify new behavior for future maintainers |

## Alternatives Considered

### Alternative 1: Add new parameter `show_all_players="true"`
**Rejected Because**:
- Adds complexity (new parameter to document and maintain)
- User already has `show_details` as the trigger
- Doesn't align with user mental model (details = all players)

### Alternative 2: Keep current behavior, add documentation
**Rejected Because**:
- Doesn't solve the user's problem
- User explicitly wants "all players" in detailed view
- Current behavior of respecting limit in detailed view is unintuitive

### Alternative 3: Make limit optional in detailed view (remove if set)
**Rejected Because**:
- More complex logic (unset variable conditionally)
- Harder to understand and maintain
- Simple conditional is clearer

## Implementation Strategy

**Chosen Approach**: Simple conditional modification

**Code Change**:
```php
// OLD (lines 2443-2446)
if ($show_details && !isset($atts['limit'])) {
    $limit = -1;
}

// NEW
if ($show_details) {
    $limit = -1;
}
```

**Files to Modify**:
1. `wordpress-plugin/poker-tournament-import/includes/class-shortcodes.php` (lines 2443-2446)
2. Update inline comment at line 2443 to reflect new behavior

**Testing Strategy**:
- Manual testing with various shortcode parameter combinations
- Verify detailed view shows all players
- Verify standard view still respects limit
- Test edge cases (0 players, 200+ players)

## Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking existing `show_details="true" limit="N"` usage | Medium | Document in release notes; communication to users |
| Performance degradation on large seasons (200+ players) | Low | Acceptable per user requirement; WordPress handles large tables well |
| User confusion about limit parameter behavior | Low | Update inline documentation; parameter still works for standard views |

## Open Questions

None - all technical unknowns resolved through code analysis.

## Next Steps

Phase 1: Create data-model.md, contracts/, and quickstart.md
