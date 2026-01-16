#!/bin/bash
# Verification script for 008-improve-poker-dashboard-filters
# Run this AFTER restarting WordPress site via Local GUI

SHORT_NAME="poker-filter-persistence"
SITE_URL="http://tdwp.local"
TEST_PAGE="$SITE_URL/hello-world/"

echo "======================================"
echo "Feature 008 Verification"
echo "======================================"
echo ""

echo "Test 1: Check CSS file loads"
CSS_SIZE=$(curl -s "$SITE_URL/wp-content/plugins/poker-tournament-import/assets/css-dashboard/filters.css" | wc -c)
echo "CSS file size: $CSS_SIZE bytes"
if [ "$CSS_SIZE" -gt 3600 ]; then
    echo "✅ PASS: CSS file has updated content (should be ~3649 bytes)"
else
    echo "❌ FAIL: CSS file still old version (got $CSS_SIZE bytes, expected >3600)"
    echo "   Try: Restart site via Local GUI to clear nginx cache"
fi
echo ""

echo "Test 2: Check for CSS variables in served file"
CSS_HAS_VARS=$(curl -s "$SITE_URL/wp-content/plugins/poker-tournament-import/assets/css-dashboard/filters.css" | grep -c "dashboard-primary:")
if [ "$CSS_HAS_VARS" -gt 0 ]; then
    echo "✅ PASS: CSS variables defined (--dashboard-primary found)"
else
    echo "❌ FAIL: CSS variables not found in served file"
fi
echo ""

echo "Test 3: Check page loads without errors"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TEST_PAGE")
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ PASS: Page loads successfully (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: Page load error (HTTP $HTTP_CODE)"
fi
echo ""

echo "======================================"
echo "Manual Testing Required"
echo "======================================"
echo ""
echo "1. Button Visibility (US1):"
echo "   - Open $TEST_PAGE in browser"
echo "   - Verify 'Apply Filters' button has BLUE background"
echo "   - Move mouse away - button should stay visible"
echo ""
echo "2. Filter Persistence (US2):"
echo "   - Select season filter → Apply"
echo "   - Refresh page (F5)"
echo "   - Verify dropdown still shows selected season"
echo "   - Check URL contains ?filter_season=X"
echo ""
echo "3. User Meta Verification:"
echo "   WP_CLI='/Users/hkh/Library/Application Support/Local/ssh-entry/hNPsf2SE_.sh'"
echo "   $WP_CLI -c 'wp user meta get 1 poker_dashboard_filters --allow-root'"
echo ""
