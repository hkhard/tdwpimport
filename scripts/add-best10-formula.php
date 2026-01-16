<?php
/**
 * Add custom "Best 10" season formula
 * Run this script to add the formula to WordPress
 */

// Load WordPress
require_once dirname(__FILE__) . '/../wordpress-plugin/poker-tournament-import/poker-tournament-import.php';

echo "=== Adding Best 10 Season Formula ===\n\n";

// Get existing formulas
$formulas = get_option('tdwp_tournament_formulas', array());

// Define the best_10 formula
$best10_formula = array(
    'name' => 'Best 10 of Season',
    'description' => 'Sum of best 10 tournament points in season (top 10 results)',
    'formula' => 'assign("lp", listpoints) assign("numberOfTourneysToCount", 10) assign("countedResults", top(numberOfTourneysToCount, lp)) assign("rankingPoints", round(sum(countedResults)))',
    'dependencies' => array(),
    'category' => 'season'
);

// Add to formulas
$formulas['best_10'] = $best10_formula;

// Save to database
update_option('tdwp_tournament_formulas', $formulas);

echo "✓ Added 'best_10' formula to tdwp_tournament_formulas\n";

// Update active season formula to use best_10
update_option('tdwp_active_season_formula', 'best_10');

echo "✓ Set 'best_10' as active season formula\n";

// Clear transients to force refresh
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_poker_season_standings_%'
    OR option_name LIKE '_transient_timeout_poker_season_standings_%'"
);

echo "✓ Cleared season standings transients\n\n";

echo "=== Formula Added Successfully ===\n";
echo "\nFormula details:\n";
echo "  Name: " . $best10_formula['name'] . "\n";
echo "  Description: " . $best10_formula['description'] . "\n";
echo "  Formula: " . $best10_formula['formula'] . "\n";
echo "\nThe season leaderboard will now show:\n";
echo "  - Points column: Sum of ALL tournaments (total_points)\n";
echo "  - Season Points column: Sum of BEST 10 tournaments (series_points via best_10 formula)\n";
echo "\nNext: Reload the season leaderboard page to see the difference.\n";
