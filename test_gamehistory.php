<?php
/**
 * Test script for GameHistoryItem processing
 */

// Include the parser class
require_once 'poker-tournament-import/includes/class-parser.php';

// Test file with known GameHistoryItems
$test_file = 'tdtfiles/ORF_Poker_20250227.tdt';

echo "=== GameHistoryItem Processing Test ===\n";

if (!file_exists($test_file)) {
    echo "ERROR: Test file not found: $test_file\n";
    exit(1);
}

try {
    $parser = new Poker_Tournament_Parser();
    $result = $parser->parse_file($test_file);

    echo "✅ File parsed successfully\n";

    // Check if game history was extracted
    if (isset($result['game_history']) && !empty($result['game_history'])) {
        echo "✅ Game history extracted: " . count($result['game_history']) . " items\n";

        // Look for winner declaration
        $found_winner = false;
        $found_elimination = false;

        foreach ($result['game_history'] as $item) {
            echo "  - [{$item['type']}] {$item['text']}\n";

            if ($item['type'] === 'winner_declaration') {
                $found_winner = true;
                echo "🏆 WINNER DECLARATION FOUND: {$item['text']}\n";
            }

            if ($item['type'] === 'elimination') {
                $found_elimination = true;
                echo "⚔️  ELIMINATION FOUND: {$item['text']}\n";
            }
        }

        if ($found_winner) {
            echo "✅ Winner declaration detected in GameHistory\n";
        } else {
            echo "❌ No winner declaration found\n";
        }

        if ($found_elimination) {
            echo "✅ Elimination details found in GameHistory\n";
        }

    } else {
        echo "❌ No game history extracted\n";
    }

    // Check players for enhanced data
    if (isset($result['players']) && !empty($result['players'])) {
        echo "\n=== Player Analysis ===\n";
        echo "Total players: " . count($result['players']) . "\n";

        foreach ($result['players'] as $uuid => $player) {
            echo "  - {$player['nickname']} (Position: {$player['finish_position']})";

            if (isset($player['winner_source'])) {
                echo " [Winner: {$player['winner_source']}]";
            }

            if (isset($player['elimination_details'])) {
                echo " [Eliminated by: {$player['elimination_details']['eliminated_by_name']}]";
            }

            if (isset($player['elimination_count'])) {
                echo " [Eliminations: {$player['elimination_count']}]";
            }

            echo "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";