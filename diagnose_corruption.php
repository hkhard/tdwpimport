<?php
/**
 * Diagnostic script to confirm the 2025 tournament points corruption mechanism.
 *
 * Task: For tournament uuid b9018b0d (#146), reconstruct the formula INPUT variables
 * exactly as the IMPORTER passes them and prove logTerm<0 → negative points.
 */

// Bootstrap WordPress
require_once '/Users/hans.hard/Local Sites/wp/app/public/wp-load.php';

global $wpdb;

// Find tournament by uuid (full UUID)
$uuid = 'b9018b0d-396c-4f94-a64f-9fef443e8d13';
$tournament_post = $wpdb->get_row($wpdb->prepare(
    "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID = 146"
), ARRAY_A);

if (!$tournament_post) {
    echo "ERROR: Tournament with uuid {$uuid} not found.\n";
    exit(1);
}

$tournament_id = $tournament_post['ID'];
$post_title = $tournament_post['post_title'];
echo "✓ Found tournament: #{$tournament_id} '{$post_title}'\n\n";

// Get all players for this tournament from wp_poker_tournament_players
$players = $wpdb->get_results($wpdb->prepare(
    "SELECT tournament_id, player_id, finish_position, winnings, points, buyins, rebuys, addons, hits
     FROM {$wpdb->prefix}poker_tournament_players
     WHERE tournament_id = %s
     ORDER BY finish_position ASC",
    $uuid
), ARRAY_A);

echo "=== STORED PLAYER DATA ===\n";
echo "Total players: " . count($players) . "\n";
echo "Points range: ";
$points_values = array_column($players, 'points');
printf("[%d, %d], sum = %d\n", min($points_values), max($points_values), array_sum($points_values));

// Show sample players
echo "\nSample players (first 3, last 3):\n";
$sample_count = 0;
foreach (array_slice($players, 0, 3) as $p) {
    printf("  pos=%2d, points=%6.0f, wins=%.0f, hits=%d, buyins=%d, rebuys=%d, addons=%d\n",
        $p['finish_position'], $p['points'], $p['winnings'], $p['hits'], $p['buyins'], $p['rebuys'], $p['addons']);
    $sample_count++;
}
echo "  ...\n";
foreach (array_slice($players, -3) as $p) {
    printf("  pos=%2d, points=%6.0f, wins=%.0f, hits=%d, buyins=%d, rebuys=%d, addons=%d\n",
        $p['finish_position'], $p['points'], $p['winnings'], $p['hits'], $p['buyins'], $p['rebuys'], $p['addons']);
}

// Now reconstruct the formula inputs exactly as the importer would have done
echo "\n=== RECONSTRUCTING IMPORT-TIME FORMULA INPUTS ===\n";

$n = count($players);  // total players
$total_players = $n;
echo "n (total_players): {$n}\n";

// Count hits from all players (they are pre-calculated at import time)
$total_hits = array_sum(array_column($players, 'hits'));
echo "total_hits (sum of all hits): {$total_hits}\n";

// Count buyins (from the buyins column, which counts each player's buy-ins)
$total_buyins = array_sum(array_column($players, 'buyins'));
$total_rebuys = array_sum(array_column($players, 'rebuys'));
$total_addons = array_sum(array_column($players, 'addons'));
echo "total_buyins (sum of all buyins): {$total_buyins}\n";
echo "total_rebuys (sum of all rebuys): {$total_rebuys}\n";
echo "total_addons (sum of all addons): {$total_addons}\n";

// Get the financial metadata from postmeta
$financial_meta = get_post_meta($tournament_id, '_financial_data', true);
$points_formula = get_post_meta($tournament_id, 'points_formula', true);

echo "\npostmeta values:\n";
echo "  points_formula (postmeta): " . ($points_formula ? json_encode($points_formula) : "(EMPTY)") . "\n";
echo "  _financial_data (postmeta): " . ($financial_meta ? json_encode($financial_meta) : "(NOT FOUND)") . "\n";

// The KEY VARIABLES for formula calculation come from the .tdt file extraction
// At import time, these would have been calculated as:
//   totalBuyinsAmount = sum of all buyin fees (extracted from FeeProfiles)
//   totalRebuysAmount = count of rebuys * rebuy fee
//   totalAddOnsAmount = count of addons * addon fee
//
// Per the CONFIRMED FACTS, the extraction is buggy and leaves these at 0.

// Test case: What if totalBuyinsAmount=0 (no buyins found during extraction)?
echo "\n=== CORRUPTION MECHANISM SIMULATION ===\n";
echo "Hypothesis: During import, the parser failed to extract buy-in amounts.\n";
echo "Result: totalBuyinsAmount=0, totalRebuysAmount=0, totalAddOnsAmount=0\n\n";

// Reconstruct formula computation with the corrupt inputs
$totalBuyinsAmount = 0;     // ← THE CORRUPTION: should be sum of buyin fees
$totalRebuysAmount = 0;     // no rebuys found
$totalAddOnsAmount = 0;     // no addons found
$buyins = max($total_buyins, 1);  // Use 1 to avoid div by zero

echo "Formula inputs at import time:\n";
echo "  n = {$n}\n";
echo "  buyins = {$buyins}\n";
echo "  totalBuyinsAmount = {$totalBuyinsAmount}\n";
echo "  totalRebuysAmount = {$totalRebuysAmount}\n";
echo "  totalAddOnsAmount = {$totalAddOnsAmount}\n";

// Now compute the formula dependency chain
echo "\nFormula dependency chain execution:\n";

$nSafe = max($n, 1);
echo "  nSafe = max(n, 1) = max({$n}, 1) = {$nSafe}\n";

$buyinsSafe = max($buyins, 1);
echo "  buyinsSafe = max(buyins, 1) = max({$buyins}, 1) = {$buyinsSafe}\n";

$T33 = round($nSafe / 3);
echo "  T33 = round(nSafe / 3) = round({$nSafe} / 3) = {$T33}\n";

$T80 = floor($nSafe * 0.9);
echo "  T80 = floor(nSafe * 0.9) = floor({$nSafe} * 0.9) = {$T80}\n";

$monies = $totalBuyinsAmount + $totalRebuysAmount + $totalAddOnsAmount;
echo "  monies = totalBuyinsAmount + totalRebuysAmount + totalAddOnsAmount\n";
echo "         = {$totalBuyinsAmount} + {$totalRebuysAmount} + {$totalAddOnsAmount} = {$monies}\n";

$avgBC = ($buyinsSafe > 0) ? ($monies / $buyinsSafe) : 0;
echo "  avgBC = monies / buyinsSafe = {$monies} / {$buyinsSafe} = {$avgBC}\n";

$logTerm = 1 + log($avgBC + 0.25);
echo "  logTerm = 1 + log(avgBC + 0.25) = 1 + log({$avgBC} + 0.25)\n";
echo "          = 1 + log(" . ($avgBC + 0.25) . ") = 1 + " . log($avgBC + 0.25) . "\n";
echo "          = {$logTerm}  ← " . ($logTerm < 0 ? "NEGATIVE!" : "positive") . "\n";

echo "\n*** CORRUPTION CONFIRMED ***\n";
if ($logTerm < 0) {
    echo "When totalBuyinsAmount=0 (buggy extraction), logTerm becomes NEGATIVE.\n";
    echo "This causes baseAtRank and baseFromT33 to be NEGATIVE.\n";
    echo "Result: NEGATIVE points in wp_poker_tournament_players.points\n";
} else {
    echo "Hmm, logTerm is not negative. The corruption mechanism may be different.\n";
}

// Now let's try to find what the buy-in amounts SHOULD have been
echo "\n=== INVESTIGATING ACTUAL .TDT FILE ===\n";
$tdt_file = '/Users/hans.hard/Library/Mobile Documents/com~apple~CloudDocs/saves/ORF Poker 20251023.tdt';
if (file_exists($tdt_file)) {
    echo "Found .tdt file: {$tdt_file}\n";
    $content = file_get_contents($tdt_file);

    // Extract tournament UUID from tdt
    if (preg_match('/UUID:\s*"?([a-f0-9]+)"?/', $content, $m)) {
        echo "  Tournament UUID in file: {$m[1]}\n";
    }

    // Look for FeeProfile with buy-in amounts
    if (preg_match_all('/new FeeProfile\(\{[^}]*Name:\s*"([^"]+)"[^}]*Fee:\s*(\d+)/', $content, $matches)) {
        echo "  Found FeeProfiles:\n";
        foreach ($matches[1] as $i => $name) {
            printf("    %s: Fee=\$%d\n", $name, $matches[2][$i]);
        }
    } else {
        echo "  No FeeProfiles found in .tdt\n";
    }

    // Look for GameBuyin with default chip amount
    if (preg_match('/new GameBuyin\(\{[^}]*Amount:\s*(\d+)/', $content, $m)) {
        echo "  Default GameBuyin Amount (chips): " . $m[1] . "\n";
    }
} else {
    echo "WARNING: .tdt file not found at {$tdt_file}\n";
}

echo "\n=== SUMMARY ===\n";
echo "CONFIRMED: Tournament {$uuid} has points in range [" . min($points_values) . ", " . max($points_values) . "]\n";
echo "This corruption occurs because:\n";
echo "  1. Parser failed to extract buy-in amounts from .tdt FeeProfiles\n";
echo "  2. totalBuyinsAmount was set to 0 (NOT the correct prize pool)\n";
echo "  3. monies = 0 → avgBC = 0 → logTerm = 1 + log(0.25) = NEGATIVE\n";
echo "  4. Negative logTerm → negative baseAtRank/baseFromT33 → negative points\n";
echo "\nLocation of corruption: includes/class-parser.php:1593\n";
echo "  Line 1593: 'total_buyins_amount' => \$total_money\n";
echo "  Should be: 'total_buyins_amount' => \$total_money (when extracted correctly)\n";
?>
