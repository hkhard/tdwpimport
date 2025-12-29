# PDF Export Removal Testing Checklist

**Date**: 2025-11-03
**Purpose**: Verify plugin works correctly after complete PDF export and TCPDF removal

## Pre-Removal Checklist

### Environment Setup
- [ ] Create full backup of current plugin files
- [ ] Backup WordPress database (especially plugin-related tables and options)
- [ ] Document current plugin functionality for comparison
- [ ] Note any existing PDF exports that users might need

### Current Functionality Verification
- [ ] Plugin activates without errors
- [ ] Admin interface loads correctly
- [ ] Tournament data displays properly
- [ ] Export options show PDF, CSV, TDT formats
- [ ] Dependency Manager page is accessible

## Phase 1: Core Removal Testing

### File Removal Verification
- [ ] Remove `class-pdf-exporter.php` - check no fatal errors
- [ ] Remove `class-dependency-manager.php` - check no fatal errors
- [ ] Remove `class-dependency-manager-page.php` - check admin interface loads
- [ ] Remove `composer.json` and `/vendor/` directory - check plugin still works

### Plugin Load Testing
- [ ] Plugin activates without PHP errors
- [ ] Admin menu appears correctly
- [ ] Tournament list page loads
- [ ] Tournament detail pages load
- [ ] Player statistics pages load

## Phase 2: Modified File Testing

### Export Manager Testing
- [ ] `class-export-manager.php` loads without errors
- [ ] Supported formats array excludes PDF (should be `['csv', 'tdt']`)
- [ ] CSV export functionality works correctly
- [ ] TDT export functionality works correctly

### Main Plugin File Testing
- [ ] No TCPDF-related PHP errors on plugin load
- [ ] No missing function errors for removed dependency manager
- [ ] AJAX handlers work for remaining functionality
- [ ] Cron jobs don't reference removed TCPDF functions

## Phase 3: Template and Interface Testing

### Template File Testing
For each template file:
- [ ] `single-tournament.php` - loads without errors, no PDF export button
- [ ] `single-player.php` - loads without errors, no PDF export button
- [ ] `single-tournament_series.php` - loads without errors
- [ ] `single-tournament_season.php` - loads without errors
- [ ] `taxonomy-tournament_series.php` - loads without errors
- [ ] `taxonomy-tournament_season.php` - loads without errors

### Frontend Functionality
- [ ] Tournament detail pages display correctly
- [ ] Player profile pages display correctly
- [ ] Tournament series pages display correctly
- [ ] Export buttons show only CSV/TDT options
- [ ] No broken links or missing buttons visible

### Admin Interface Testing
- [ ] Admin dashboard loads without errors
- [ ] Poker Tournament Import menu works
- [ ] Tournament management pages load
- [ ] Statistics tab loads without errors
- [ ] Export options show only CSV/TDT
- [ ] No Dependency Manager menu item

## Phase 4: Database Cleanup Testing

### WordPress Options
- [ ] All PDF-related options removed from database
- [ ] No orphaned options causing warnings
- [ ] Plugin settings page works correctly

### Database Operations
- [ ] Tournament creation and editing works
- [ ] Player data management works
- [ ] Statistics calculations work
- [ ] Data import/export works for remaining formats

### Cron Jobs
- [ ] No TCPDF-related cron jobs scheduled
- [ ] Remaining cron jobs (if any) work correctly
- [ ] No scheduled task errors in WordPress logs

## Phase 5: Export Functionality Testing

### CSV Export Testing
- [ ] CSV export from tournament detail pages works
- [ ] CSV export from player pages works
- [ ] CSV export from series pages works
- [ ] Generated CSV files are valid and contain correct data
- [ ] CSV download works correctly

### TDT Export Testing
- [ ] TDT export from tournament pages works
- [ ] TDT export from series pages works
- [ ] Generated TDT files are valid for Tournament Director
- [ ] TDT download works correctly

### Export Interface Testing
- [ ] Export buttons are styled correctly
- [ ] Export AJAX calls work without errors
- [ ] Export progress indicators work (if any)
- [ ] Export error handling works for remaining formats

## Phase 6: Performance and Error Testing

### Performance Testing
- [ ] Plugin load time is faster than before removal
- [ ] Memory usage is reduced
- [ ] No external HTTP requests made by plugin
- [ ] Database queries execute efficiently

### Error Log Testing
- [ ] No PHP errors in WordPress debug log
- [ ] No WordPress admin notices about missing dependencies
- [ ] No JavaScript errors in browser console
- [ ] No AJAX errors in browser network tab

### Edge Case Testing
- [ ] Plugin works on fresh WordPress installation
- [ ] Plugin works after deactivation/reactivation
- [ ] Plugin works after WordPress updates
- [ ] Plugin works with different PHP versions (8.0+)

## Phase 7: User Experience Testing

### Admin Experience
- [ ] Admin interface is intuitive without PDF options
- [ ] Export functionality is clearly labeled for remaining formats
- [ ] No confusing references to PDF export remain
- [ ] Help text is updated to reflect current capabilities

### Frontend Experience
- [ ] Website visitors see clean tournament pages
- [ ] Export options are clear and functional
- [ ] No broken PDF download links
- [ ] Page loading is smooth and error-free

## Phase 8: Security Testing

### File Access
- [ ] No unauthorized file access through export functions
- [ ] All remaining AJAX handlers properly validate nonces
- [ ] User capability checks work correctly
- [ ] File downloads are properly secured

### Input Validation
- [ ] Export parameters are properly sanitized
- [ ] No SQL injection vulnerabilities in remaining code
- [ ] No XSS vulnerabilities in export outputs
- [ ] File path traversal is prevented

## Rollback Testing (Optional)

If rollback capability is needed:
- [ ] Can restore removed files from git
- [ ] Can revert database changes
- [ ] Plugin works again after rollback
- [ ] PDF export functionality restored correctly

## Final Verification

### Success Criteria
- [ ] Plugin loads without any errors
- [ ] All core functionality works correctly
- [ ] Remaining export formats (CSV, TDT) function properly
- [ ] No references to PDF export remain in interface
- [ ] Performance is improved over previous version
- [ ] Security is maintained or improved

### Documentation Updates
- [ ] Plugin documentation updated to reflect removed features
- [ ] User guide updated with current export options
- [ ] Help text updated in admin interface
- [ ] changelog notes PDF export removal

## Troubleshooting Checklist

### Common Issues to Check
- [ ] White screen of death - check PHP errors
- [ ] Missing menu items - check plugin file modifications
- [ ] Broken export buttons - check template updates
- [ ] Database errors - check option cleanup
- [ ] AJAX failures - check handler removal

### Debug Information to Collect
- [ ] WordPress debug log contents
- [ ] PHP error logs
- [ ] Browser console errors
- [ ] Network request failures
- [ ] Database query logs (if available)

## Sign-off

### Tester Verification
- [ ] All tests completed successfully
- [ ] No critical issues found
- [ ] Plugin ready for production deployment
- [ ] Documentation updated

### Final Approval
- [ ] Plugin owner review completed
- [ ] Stakeholder approval received
- [ ] Change log updated
- [ ] Release prepared