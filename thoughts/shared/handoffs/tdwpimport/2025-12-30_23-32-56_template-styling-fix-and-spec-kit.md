---
date: 2025-12-30T23:32:56-08:00
session_name: tdwpimport
researcher: hkhard
git_commit: c2949596e1cfc644aa2827fc42df0d9f1755dfd3
branch: main
repository: hkhard/tdwpimport
topic: "Template Styling Fix and Spec-Kit Integration"
tags: [wordpress, templates, block-themes, deprecation-warnings, spec-kit, mcp]
status: complete
last_updated: 2025-12-30
last_updated_by: hkhard
type: implementation_strategy
root_span_id:
turn_span_id:
---

# Handoff: Beta8 Template Styling Fix & Spec-Kit MCP Integration

## Task(s)

### Task 1: Restore Template Styling (COMPLETED)
**Problem**: Beta7 fixed deprecation warnings but broke template styling by loading unwanted block theme content.

**Status**: Complete - Committed and pushed as c294959

**Plan Reference**: `/Users/hkh/.claude/plans/sprightly-nibbling-puzzle.md`

**What was done**:
- Restored complete HTML document structure (DOCTYPE, html, head, body tags) to all 5 custom templates
- Modified header/footer logic to skip block themes entirely
- Updated version to 3.5.0-beta8
- Created distribution zip (2.1MB)
- Committed and pushed to GitHub

### Task 2: Spec-Kit MCP Integration (COMPLETED)
**Problem**: Spec-kit from github/spec-kit not working with Claude Code.

**Status**: Complete - Requires Claude Code restart to take effect

**What was done**:
- Identified missing spec-kit-mcp MCP server
- Installed @lsendel/spec-kit-mcp globally via npm
- Added spec-kit to ~/.claude/mcp_config.json
- User needs to restart Claude Code for MCP server to load

## Critical References

- **Plan file**: `/Users/hkh/.claude/plans/sprightly-nibbling-puzzle.md` - Template styling fix plan with root cause analysis
- **Constitution**: `wordpress-plugin/.specify/memory/constitution.md` - Project constitution (currently has template placeholders)
- **MCP Config**: `~/.claude/mcp_config.json` - Global Claude MCP server configuration

## Recent Changes

### Beta8 Template Fixes
- `wordpress-plugin/poker-tournament-import/templates/single-tournament.php:140-156` - Restored HTML structure and minimal header integration
- `wordpress-plugin/poker-tournament-import/templates/single-player.php:15-32` - Restored HTML structure and minimal header integration
- `wordpress-plugin/poker-tournament-import/templates/single-tournament_season.php:15-32` - Restored HTML structure and minimal header integration
- `wordpress-plugin/poker-tournament-import/templates/taxonomy-tournament_series.php:15-32` - Restored HTML structure and minimal header integration
- `wordpress-plugin/poker-tournament-import/templates/taxonomy-tournament_season.php:15-32` - Restored HTML structure and minimal header integration
- `wordpress-plugin/poker-tournament-import/poker-tournament-import.php:6,23` - Version bumped to 3.5.0-beta8

### Spec-Kit MCP Integration
- `~/.claude/mcp_config.json:75-80` - Added spec-kit-mcp server configuration
- Installed npm package: @lsendel/spec-kit-mcp (global)

## Learnings

### Block Theme Header Integration
**Key Pattern**: When using block themes (Twenty Twenty-Four/Five), `block_template_part('header')` loads the ENTIRE theme header including:
- Navigation menus
- Site logo/title
- Mobile menu toggles (X and = icons)
- All block editor content

**Solution Pattern**: Skip block template parts entirely for block themes. Use only:
```php
if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
    get_header();
}
```

### WordPress Template Standalone Structure
**Key Insight**: WordPress custom post type templates can be standalone HTML documents while maintaining WordPress integration:
- Use complete HTML structure (DOCTYPE, html, head, body)
- Use `wp_head()` and `wp_footer()` for WordPress hooks
- Skip theme headers for block themes to avoid unwanted content
- Use `get_header()`/`get_footer()` only for classic themes

### Spec-Kit vs Spec-Kit-MCP
**Important Distinction**:
- **github/spec-kit**: The spec-driven development toolkit with templates and scripts (installed in `.specify/`)
- **lsendel/spec-kit-mcp**: The MCP server that enables Claude Code to interact with spec-kit
- Both are required for spec-kit to work with Claude Code

## Post-Mortem

### What Worked
- **Minimal theme integration approach**: Skipping block theme headers entirely successfully prevented unwanted navigation/icons from loading
- **HTML structure restoration**: Bringing back DOCTYPE, html, head, body tags fixed browser rendering and styling issues
- **wp_head()/wp_footer() hooks**: Maintained WordPress plugin functionality while avoiding theme content
- **MCP server installation**: npm global install of spec-kit-mcp was straightforward

### What Failed
- **Beta7 approach**: Using `block_template_part('header')` for block themes caused the unwanted icons/navigation
  - **Root cause**: `block_template_part()` loads full block theme header, not just header hooks
  - **Fix**: Skip block template parts entirely, use only get_header() for classic themes

### Key Decisions
- **Decision**: Skip block theme headers entirely instead of trying to work with them
  - **Alternatives considered**: Use block_template_part(), suppress via CSS, create custom header
  - **Reason**: Cleanest solution - avoids unwanted content while maintaining WordPress hooks

- **Decision**: Use complete HTML document structure in templates
  - **Alternatives considered**: Fragment templates, rely on theme structure
  - **Reason**: Ensures proper browser rendering, independent of theme variations

- **Decision**: Install spec-kit-mcp via npm globally
  - **Alternatives considered**: Cargo install, npx on-demand
  - **Reason**: Easiest integration with existing npm-based MCP servers

## Artifacts

- **Plan file**: `/Users/hkh/.claude/plans/sprightly-nibbling-puzzle.md` - Complete plan with root cause analysis
- **Distribution zip**: `/Users/hkh/dev/tdwpimport/poker-tournament-import-v3.5.0-beta8.zip` - Beta8 release (2.1MB)
- **MCP configuration**: `/Users/hkh/.claude/mcp_config.json` - Updated with spec-kit-mcp entry
- **Spec-kit templates**: `/Users/hkh/dev/tdwpimport/.specify/` - Constitution, templates, scripts (unfilled)
- **Commit**: c294959 - "Restore template HTML structure and skip block theme headers"

## Action Items & Next Steps

### Immediate
1. **Restart Claude Code** - Required for spec-kit-mcp MCP server to load
2. **Test Beta8** - Verify template styling is clean with no deprecation warnings:
   - Hard refresh browser (Cmd+Shift+R)
   - Click through dashboard to tournament/season/series/player pages
   - Confirm no unwanted navigation/icons
   - Confirm no deprecation warnings

### Future Work
1. **Fill out spec-kit constitution** - `.specify/memory/constitution.md` currently has template placeholders
2. **Consider spec-kit usage** - Now that spec-kit-mcp is installed, consider using spec-driven development for new features

## Other Notes

### Template File Pattern
All 5 custom templates now follow this pattern:
```php
<?php
// Security check
if (!defined('ABSPATH')) { exit; }
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?> <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Minimal WordPress integration - skip block themes -->
<?php
if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
    get_header();
}
?>

<!-- Template content -->

<?php
if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
    get_footer();
}
?>
</body>
</html>
```

### Local WordPress Environment
- Running site path: `/Users/hkh/Local Sites/poker-tournament-devlocal/app/public/wp-content/plugins/poker-tournament-import/`
- SSH entry point: `/Users/hkh/Library/Application Support/Local/ssh-entry/hNPsf2SE_.sh`
- Always sync template changes to running site for testing

### Related Resources
- [spec-kit-mcp GitHub](https://github.com/lsendel/spec-kit-mcp)
- [github/spec-kit](https://github.com/github/spec-kit)
- [Best Claude Code Extensions 2025](https://claudefa.st/blog/tools/mcp-extensions/best-addons)
