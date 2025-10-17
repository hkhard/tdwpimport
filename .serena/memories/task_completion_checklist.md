# Task Completion Checklist

## After Code Changes

### 1. Version Update (for releases)
- Update version in `poker-tournament-import.php` header (line 6)
- Update POKER_TOURNAMENT_IMPORT_VERSION constant (line 23)

### 2. Create Distribution Zip (REQUIRED for releases)
```bash
cd wordpress-plugin
zip -r poker-tournament-import-vX.X.X.zip poker-tournament-import/ \
  -x "*.git*" "*.DS_Store" "*node_modules*" "*.backup"
```
**CRITICAL**: Always create updated installer zip with new versions

### 3. Code Quality (if available)
```bash
# Check WordPress coding standards (if configured)
composer run phpcs

# Run static analysis (if configured)
composer run phpstan
```

### 4. Testing
- Test changes in WordPress admin
- Test .tdt file import if parser modified
- Test shortcodes if frontend modified
- Check statistics refresh if engine modified
- Verify AJAX endpoints if admin modified

### 5. Security Checks
- Verify nonce checks for AJAX handlers
- Confirm data sanitization
- Check capability/permission checks
- Review prepared statements for DB queries

### 6. Documentation
- Update CLAUDE.md if architecture changes
- Update README.md if features added
- Update inline PHPDoc comments
- Create release notes for significant changes

### 7. Git Workflow
```bash
git status                    # Review changes
git add .                     # Stage changes
git commit -m "description"   # Commit with message
# DO NOT push unless explicitly requested
```

## For Formula Changes
- Test validation via admin formula interface
- Verify calculations with test data
- Check backward compatibility with existing formulas

## For Statistics Engine Changes
- Run manual statistics refresh
- Check data mart tables for consistency
- Verify async refresh on tournament save/delete

## For Shortcode Changes
- Test all attribute combinations
- Verify template rendering
- Check caching behavior
