<?php
/**
 * Simple test file for the TDT parser
 *
 * This file can be used to test the parser functionality outside of WordPress
 * Run with: php test-parser.php
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    // We're outside WordPress, so we need to define some WordPress functions for testing
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags($string) {
            return strip_tags($string);
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($string) {
            return htmlspecialchars($string);
        }
    }
}

// Include the parser class
require_once 'includes/class-parser.php';

echo "=== Poker Tournament TDT Parser Test ===\n\n";

// Test with one of our sample files
$test_file = __DIR__ . '/../../tdtfiles/Koffsta_20250718.tdt';

if (!file_exists($test_file)) {
    echo "ERROR: Test file not found: $test_file\n";
    echo "Please make sure the tdtfiles directory exists with sample files.\n";
    exit(1);
}

echo "Testing file: " . basename($test_file) . "\n";
echo "File size: " . filesize($test_file) . " bytes\n\n";

try {
    // Create parser instance
    $parser = new Poker_Tournament_Parser();

    // Parse the file
    echo "Parsing TDT file...\n";
    $start_time = microtime(true);
    $tournament_data = $parser->parse_file($test_file);
    $parse_time = microtime(true) - $start_time;

    echo "Parse completed in " . number_format($parse_time, 3) . " seconds\n\n";

    // Display parsed data
    echo "=== PARSED DATA ===\n";

    if (!empty($tournament_data['metadata'])) {
        echo "Tournament Metadata:\n";
        foreach ($tournament_data['metadata'] as $key => $value) {
            echo "  $key: " . (is_string($value) ? $value : print_r($value, true)) . "\n";
        }
        echo "\n";
    }

    if (!empty($tournament_data['players'])) {
        echo "Players found: " . count($tournament_data['players']) . "\n";

        // Show first 3 players as sample
        $player_count = 0;
        foreach ($tournament_data['players'] as $player_uuid => $player) {
            if ($player_count >= 3) break;

            echo "  Player " . ($player_count + 1) . ":\n";
            echo "    UUID: " . $player_uuid . "\n";
            echo "    Name: " . $player['nickname'] . "\n";
            echo "    Buy-ins: " . count($player['buyins']) . "\n";
            echo "    Hits: " . $player['hits'] . "\n";
            echo "\n";

            $player_count++;
        }

        if (count($tournament_data['players']) > 3) {
            echo "  ... and " . (count($tournament_data['players']) - 3) . " more players\n\n";
        }
    }

    if (!empty($tournament_data['financial'])) {
        echo "Financial Configuration:\n";
        if (!empty($tournament_data['financial']['fee_profiles'])) {
            echo "  Fee Profiles:\n";
            foreach ($tournament_data['financial']['fee_profiles'] as $name => $profile) {
                echo "    $name: " . $profile['fee'] . "\n";
            }
        }
        echo "\n";
    }

    if (!empty($tournament_data['structure']['levels'])) {
        echo "Blind Structure: " . count($tournament_data['structure']['levels']) . " levels\n";
        echo "  First few levels:\n";
        for ($i = 0; $i < min(3, count($tournament_data['structure']['levels'])); $i++) {
            $level = $tournament_data['structure']['levels'][$i];
            echo "    Level " . ($i + 1) . ": " . $level['small_blind'] . "/" . $level['big_blind'] . " (" . $level['minutes'] . " min)\n";
        }
        echo "\n";
    }

    if (!empty($tournament_data['prizes'])) {
        echo "Prize Distribution: " . count($tournament_data['prizes']) . " prizes\n";
        foreach ($tournament_data['prizes'] as $prize) {
            echo "  " . $prize['description'] . ": Position " . $prize['position'] . " - " . $prize['calculated_amount'] . "\n";
        }
        echo "\n";
    }

    if (!empty($tournament_data['players'])) {
        echo "Player Rankings & Points:\n";
        echo "  Top 3 Players:\n";

        // Sort players by finish position
        uasort($tournament_data['players'], function($a, $b) {
            return $a['finish_position'] - $b['finish_position'];
        });

        $count = 0;
        foreach ($tournament_data['players'] as $player_uuid => $player) {
            if ($count >= 3) break;

            echo "    Position " . $player['finish_position'] . ": " . $player['nickname'] . "\n";
            echo "      Points: " . $player['points'] . " | Winnings: " . $player['winnings'] . "\n";
            echo "      Buy-ins: " . count($player['buyins']) . " | Hits: " . $player['hits'] . "\n";

            if (isset($player['points_calculation'])) {
                $calc = $player['points_calculation'];
                echo "      Points Formula: T33=" . $calc['T33'] . ", T80=" . $calc['T80'] . ", Avg Buyin=" . number_format($calc['avg_buyin'], 2) . "\n";
            }
            echo "\n";
            $count++;
        }
    }

    // Validate the data
    echo "=== VALIDATION ===\n";
    $errors = $parser->validate_data();
    if (empty($errors)) {
        echo "✓ Data validation passed\n";
    } else {
        echo "✗ Validation errors found:\n";
        foreach ($errors as $error) {
            echo "  - " . $error . "\n";
        }
    }

    echo "\n=== TEST COMPLETED ===\n";
    echo "Parser appears to be working correctly!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}