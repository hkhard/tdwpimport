<?php
// Quick test for TDT parser with 'undefined' keyword

require_once '/Users/hkh/dev/tdwpimport/wordpress-plugin/poker-tournament-import/includes/class-tdt-lexer.php';
require_once '/Users/hkh/dev/tdwpimport/wordpress-plugin/poker-tournament-import/includes/class-tdt-ast-parser.php';

$tdtFile = '/Users/hkh/Desktop/saves/ORF_Poker_20240118.tdt';

echo "Testing TDT parser with 'undefined' keyword fix...\n";
echo "File: $tdtFile\n\n";

try {
    $content = file_get_contents($tdtFile);
    echo "File size: " . strlen($content) . " bytes\n\n";

    $parser = new TDT_Parser($content);
    $ast = $parser->parse();

    echo "✅ SUCCESS! File parsed without errors.\n";
    echo "AST type: " . gettype($ast) . "\n";

    if (isset($ast['V'])) {
        echo "Tournament Director Version: " . $ast['V'] . "\n";
    }

    echo "\nParser handled 'undefined' keyword correctly!\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    echo "Error at position mentioned in error message\n";
    exit(1);
}
