<?php
/**
 * TDT File Parser Class with Formula Integration
 *
 * Parses Tournament Director (.tdt) files and extracts tournament data
 * Now integrated with customizable formula validation system
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Parser {

    /**
     * File path
     */
    private $file_path;

    /**
     * Raw file content
     */
    private $raw_content;

    /**
     * Parsed tournament data
     */
    private $tournament_data;

    /**
     * Formula override (user-selected during import)
     */
    private $formula_override = null;

    /**
     * Constructor
     */
    public function __construct($file_path = null, $formula_override = null) {
        if ($file_path) {
            $this->file_path = $file_path;
        }
        $this->formula_override = $formula_override;
    }

    /**
     * Parse TDT file
     */
    public function parse_file($file_path = null) {
        if ($file_path) {
            $this->file_path = $file_path;
        }

        if (!file_exists($this->file_path)) {
            throw new Exception("File not found: {$this->file_path}");
        }

        // Read file content
        $this->raw_content = file_get_contents($this->file_path);
        if ($this->raw_content === false) {
            throw new Exception("Failed to read file: {$this->file_path}");
        }

        // Parse the content
        $this->tournament_data = $this->extract_tournament_data($this->raw_content);

        return $this->tournament_data;
    }

    /**
     * Parse TDT content directly (for AJAX uploads)
     */
    public function parse_content($content) {
        if (empty($content)) {
            throw new Exception("Empty content provided for parsing");
        }

        // Store raw content
        $this->raw_content = $content;

        // Parse the content
        $this->tournament_data = $this->extract_tournament_data($this->raw_content);

        return $this->tournament_data;
    }

    /**
     * Extract tournament data from raw content using AST-based parser
     *
     * v2.4.9: Replaced regex-based extraction with proper lexer/parser/AST approach
     * to correctly handle deeply nested structures (4+ levels of nesting)
     *
     * @param string $content Raw .tdt file content
     * @return array Tournament data structure
     * @throws Exception If parsing fails
     */
    private function extract_tournament_data($content) {
        Poker_Tournament_Import_Debug::log_success("=== v2.4.9: Using AST-based TDT Parser (not regex) ===");

        try {
            // Parse .tdt file into Abstract Syntax Tree
            $parser = new TDT_Parser($content);
            $ast = $parser->parseDocument();

            Poker_Tournament_Import_Debug::log_success("Successfully parsed .tdt file into AST");

            // Convert AST to our domain data structure
            $mapper = new Poker_Tournament_Domain_Mapper();
            $data = $mapper->map_tournament_data($ast);

            Poker_Tournament_Import_Debug::log_success("Successfully mapped AST to tournament data");
            Poker_Tournament_Import_Debug::log("Extracted " . count($data['players']) . " players, " . count($data['game_history']) . " history items");

        } catch (Exception $e) {
            // Log the parsing error
            Poker_Tournament_Import_Debug::log_error("AST Parser failed: " . $e->getMessage());
            throw new Exception("Failed to parse .tdt file: " . $e->getMessage());
        }

        // POST-PROCESSING: Calculate per-player total_invested using financial data
        // The domain mapper extracts raw buyin data, but we need to calculate dollar amounts
        foreach ($data['players'] as $uuid => $player) {
            $total_invested = 0;

            if (!empty($player['buyins']) && is_array($player['buyins'])) {
                foreach ($player['buyins'] as $buyin) {
                    // Look up dollar amount from ProfileName
                    $profile_name = $buyin['profile'] ?? 'Standard';
                    if (isset($data['financial']['fee_profiles'][$profile_name])) {
                        $dollar_amount = $data['financial']['fee_profiles'][$profile_name]['fee'];
                    } else {
                        $dollar_amount = $data['financial']['buy_in'] ?? 0;
                    }
                    $total_invested += $dollar_amount;
                }
            }

            $data['players'][$uuid]['total_invested'] = $total_invested;
        }

        Poker_Tournament_Import_Debug::log_game_history("Extraction Complete", "Found " . count($data['game_history']) . " GameHistory items");

        // CRITICAL FIX: Enhance players with elimination data from GameHistory
        $data['players'] = $this->enhance_players_with_elimination_data($data['players'], $data['game_history']);

        // Calculate rankings and winnings
        $data['players'] = $this->calculate_player_rankings($data['players'], $data['game_history']);

        // NEW v2.4.25: Calculate hits from elimination data
        $data['players'] = $this->calculate_hits_from_eliminations($data['players']);

        // Calculate Tournament Director points using formula system
        $data['players'] = $this->calculate_tournament_points($data['players'], $data['financial']);

        // Calculate actual winnings
        $data['players'] = $this->calculate_winnings_by_rank($data['players'], $data['prizes']);

        return $data;
    }

    /**
     * Extract tournament metadata
     */
    private function extract_metadata($content) {
        $metadata = array();

        // Extract UUID
        if (preg_match('/UUID:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['uuid'] = $matches[1];
        }

        // Extract League info
        if (preg_match('/LeagueUUID:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['league_uuid'] = $matches[1];
        }

        if (preg_match('/LeagueName:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['league_name'] = $matches[1];
        }

        // Extract Season info
        if (preg_match('/SeasonUUID:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['season_uuid'] = $matches[1];
        }

        if (preg_match('/SeasonName:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['season_name'] = $matches[1];
        }

        // Extract Title
        if (preg_match('/Title:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['title'] = $matches[1];
        }

        // Extract Description
        if (preg_match('/Description:\s*"([^"]+)"/', $content, $matches)) {
            $metadata['description'] = $matches[1];
        }

        // Extract Start Time (convert from milliseconds)
        if (preg_match('/StartTime:\s*(\d+)/', $content, $matches)) {
            $metadata['start_time'] = date('Y-m-d H:i:s', intval($matches[1] / 1000));
        }

        // Extract PointsForPlaying formula from .tdt file
        $metadata['points_formula'] = $this->extract_points_formula($content);

        return $metadata;
    }

    /**
     * Extract PointsForPlaying formula from .tdt file
     *
     * Extracts the custom formula defined in the Tournament Director file
     * Pattern: PointsForPlaying: new UserFormula({Formula: "...", Dependencies: [...], Description: "..."})
     *
     * @param string $content Raw .tdt file content
     * @return array|null Formula data with 'formula', 'dependencies', 'description' or null if not found
     */
    private function extract_points_formula($content) {
        // Pattern to match: PointsForPlaying: new UserFormula({...})
        // This uses a complex regex to handle nested braces in the formula
        $pattern = '/PointsForPlaying:\s*new\s+UserFormula\s*\(\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\s*\)/';

        if (preg_match($pattern, $content, $matches)) {
            $formula_block = $matches[1];
            $formula_data = array();

            // Extract Formula field
            if (preg_match('/Formula:\s*"([^"]+)"/', $formula_block, $formula_match)) {
                $formula_data['formula'] = $formula_match[1];
            }

            // Extract Dependencies array
            // Pattern: Dependencies: [dependency1, dependency2, ...]
            if (preg_match('/Dependencies:\s*\[(.*?)\]/', $formula_block, $deps_match)) {
                $dependencies_string = $deps_match[1];
                $dependencies = array();

                // Extract individual dependency strings
                if (preg_match_all('/"([^"]+)"/', $dependencies_string, $dep_matches)) {
                    $dependencies = $dep_matches[1];
                }

                $formula_data['dependencies'] = $dependencies;
            }

            // Extract Description field
            if (preg_match('/Description:\s*"([^"]+)"/', $formula_block, $desc_match)) {
                $formula_data['description'] = $desc_match[1];
            }

            // Only return if we found at least a formula
            if (isset($formula_data['formula']) && !empty($formula_data['formula'])) {
                Poker_Tournament_Import_Debug::log_success("Extracted PointsForPlaying formula from .tdt file");
                Poker_Tournament_Import_Debug::log("Formula: " . $formula_data['formula']);
                if (!empty($formula_data['dependencies'])) {
                    Poker_Tournament_Import_Debug::log("Dependencies: " . count($formula_data['dependencies']) . " found");
                }
                return $formula_data;
            }
        }

        Poker_Tournament_Import_Debug::log("No PointsForPlaying formula found in .tdt file - will use default formula");
        return null;
    }

    /**
     * Extract players data
     *
     * @param string $content Raw .tdt file content
     * @param array $financial Financial data with fee profiles for dollar amount lookup
     */
    private function extract_players($content, $financial) {
        $players = array();

        // Find all player blocks
        preg_match_all('/new GamePlayer\(\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\)/', $content, $player_matches);

        foreach ($player_matches[1] as $player_data) {
            $player = array();

            // Extract UUID
            if (preg_match('/UUID:\s*"([^"]+)"/', $player_data, $matches)) {
                $player['uuid'] = $matches[1];
            }

            // Extract Nickname
            if (preg_match('/Nickname:\s*"([^"]+)"/', $player_data, $matches)) {
                $player['nickname'] = $matches[1];
            }

            // CRITICAL FIX v2.4.8: Extract buyins from WITHIN the GamePlayer object
            // Buyins are stored as Buyins: [...] INSIDE each player's data, not at tournament level
            $player['buyins'] = $this->extract_buyins($player_data);
            $player['buyins_count'] = count($player['buyins']);

            // Calculate total investment for ROI analytics
            $total_invested = 0;
            foreach ($player['buyins'] as $buyin) {
                // Look up dollar amount from ProfileName
                $profile_name = $buyin['profile'] ?? 'Standard';
                if (isset($financial['fee_profiles'][$profile_name])) {
                    $dollar_amount = $financial['fee_profiles'][$profile_name]['fee'];
                } else {
                    $dollar_amount = $financial['buy_in'] ?? 0;
                }
                $total_invested += $dollar_amount;
            }
            $player['total_invested'] = $total_invested;

            // Calculate finish position and winnings from bust-out data
            $player['finish_position'] = $this->calculate_finish_position($player_data);
            $player['winnings'] = $this->calculate_winnings($player_data);

            // Extract hit count
            if (preg_match('/HitsAdjustment:\s*(\d+)/', $player_data, $matches)) {
                $player['hits'] = intval($matches[1]);
            } else {
                $player['hits'] = 0;
            }

            if (!empty($player['uuid']) && !empty($player['nickname'])) {
                $players[$player['uuid']] = $player;
            }
        }

        return $players;
    }

    /**
     * Extract player buyins with bust-out information from GamePlayer data
     *
     * Buyins are stored WITHIN each GamePlayer object as: Buyins: [new GameBuyin({...}), ...]
     * This method correctly handles re-entries (multiple buyins per player)
     *
     * @param string $player_data Raw GamePlayer object content
     * @return array Array of buyins with amounts, chips, profile names, and bust-out data
     */
    private function extract_buyins($player_data) {
        $buyins = array();

        // Find all GameBuyin objects
        preg_match_all('/new GameBuyin\(\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\)/', $player_data, $buyin_matches);

        foreach ($buyin_matches[1] as $buyin_info) {
            $buyin = array();

            // Extract amount
            if (preg_match('/Amount:\s*(\d+)/', $buyin_info, $matches)) {
                $buyin['amount'] = intval($matches[1]);
            }

            // Extract chips
            if (preg_match('/Chips:\s*(\d+)/', $buyin_info, $matches)) {
                $buyin['chips'] = intval($matches[1]);
            }

            // Extract profile name
            if (preg_match('/ProfileName:\s*"([^"]+)"/', $buyin_info, $matches)) {
                $buyin['profile'] = $matches[1];
            }

            // Extract bust-out information if present
            if (preg_match('/BustOut:\s*new GameBustOut\(\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\)/', $buyin_info, $bustout_matches)) {
                $bustout_data = $bustout_matches[1];

                // Extract bust-out time (milliseconds)
                if (preg_match('/Time:\s*(\d+)/', $bustout_data, $matches)) {
                    $buyin['bust_out_time'] = intval($matches[1]);
                }

                // Extract bust-out round
                if (preg_match('/Round:\s*(\d+)/', $bustout_data, $matches)) {
                    $buyin['bust_out_round'] = intval($matches[1]);
                }

                // Extract hitman(s) who eliminated this player
                if (preg_match('/HitmanUUID:\s*\[(.*?)\]/', $bustout_data, $matches)) {
                    $hitman_ids = trim($matches[1], '" ');
                    $buyin['eliminated_by'] = !empty($hitman_ids) ? explode('", "', $hitman_ids) : array();
                }
            }

            $buyins[] = $buyin;
        }

        return $buyins;
    }

    /**
     * Calculate player finish position from bust-out data
     */
    private function calculate_finish_position($player_data) {
        // Extract bust-out time from GameBustOut object
        if (preg_match('/new GameBustOut\(\{[^}]*Time:\s*(\d+)/', $player_data, $matches)) {
            $bust_out_time = intval($matches[1]);
            // Later we'll need to rank all players by bust-out time
            // For now, return the bust-out round
            if (preg_match('/new GameBustOut\(\{[^}]*Round:\s*(\d+)/', $player_data, $matches)) {
                return intval($matches[1]);
            }
        }
        return 1; // Winner (no bust-out)
    }

    /**
     * Calculate player winnings
     */
    private function calculate_winnings($player_data) {
        // Extract prize amount from awarded prize data
        // This requires looking at the Prizes section and matching by finish position
        // For now, return 0 as placeholder - will be calculated in calculate_all_prizes
        return 0;
    }

    /**
     * Extract financial configuration
     */
    private function extract_financial_data($content) {
        $financial = array();

        // Extract buy-in amounts from FeeProfiles
        preg_match_all('/new FeeProfile\(\{[^}]*Name:\s*"([^"]+)"[^}]*Fee:\s*(\d+)/', $content, $matches);

        if (!empty($matches[1]) && !empty($matches[2])) {
            foreach ($matches[1] as $i => $name) {
                $fee_amount = intval($matches[2][$i]);
                $financial['fee_profiles'][$name] = array(
                    'name' => $name,
                    'fee' => $fee_amount
                );

                // CRITICAL FIX: Extract default buy-in amount from first FeeProfile
                // FeeProfile.Fee contains the dollar amount (e.g., $200), not chip count
                // This is the actual tournament buy-in that should be used for points calculation
                if (!isset($financial['buy_in']) || $name === 'Standard') {
                    $financial['buy_in'] = $fee_amount;
                    Poker_Tournament_Import_Debug::log("Extracted buy-in amount from FeeProfile '{$name}': \${$fee_amount}");
                }
            }
        }

        return $financial;
    }


    /**
     * Extract tournament structure (blinds, levels, etc.)
     */
    private function extract_structure($content) {
        $structure = array();

        // Extract blind levels
        preg_match_all('/new GameRound\(\{[^}]*SmallBlind:\s*(\d+)[^}]*BigBlind:\s*(\d+)[^}]*Minutes:\s*(\d+)/', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $i => $small_blind) {
                $structure['levels'][] = array(
                    'small_blind' => intval($small_blind),
                    'big_blind' => intval($matches[2][$i]),
                    'minutes' => intval($matches[3][$i])
                );
            }
        }

        return $structure;
    }

    /**
     * Get tournament data
     */
    public function get_tournament_data() {
        return $this->tournament_data;
    }

    /**
     * Get raw TDT file content for real-time processing
     */
    public function get_raw_content() {
        return $this->raw_content;
    }

    /**
     * Get chronological players data directly from parser (optimization)
     * This avoids unnecessary reprocessing
     */
    public function get_chronological_players() {
        if (isset($this->tournament_data['players'])) {
            // Check if players are already in chronological order
            $players = $this->tournament_data['players'];
            $first_player = reset($players);

            // If first player has finish_position = 1, data is chronological
            if ($first_player && isset($first_player['finish_position']) && $first_player['finish_position'] === 1) {
                Poker_Tournament_Import_Debug::log_success('OPTIMIZATION: Using existing chronological player data from parser');
                return $players;
            }
        }

        return null; // No chronological data available
    }

    /**
     * Extract prize distribution from Prizes section
     */
    private function extract_prize_distribution($content) {
        $prizes = array();

        // Find all GamePrize objects
        preg_match_all('/new GamePrize\(\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\)/', $content, $prize_matches);

        foreach ($prize_matches[1] as $prize_data) {
            $prize = array();

            // Extract description
            if (preg_match('/Description:\s*"([^"]+)"/', $prize_data, $matches)) {
                $prize['description'] = $matches[1];
            }

            // Extract recipient (finish position)
            if (preg_match('/Recipient:\s*(\d+)/', $prize_data, $matches)) {
                $prize['position'] = intval($matches[1]);
            }

            // Extract amount type and amount
            if (preg_match('/AmountType:\s*(\d+)/', $prize_data, $matches)) {
                $prize['amount_type'] = intval($matches[1]);
            }

            if (preg_match('/Amount:\s*(\d+)/', $prize_data, $matches)) {
                $prize['amount_percent'] = intval($matches[1]);
            }

            // Extract calculated amount
            if (preg_match('/CalculatedAmount:\s*(\d+)/', $prize_data, $matches)) {
                $prize['calculated_amount'] = intval($matches[1]);
            }

            // Extract awarded players
            if (preg_match('/AwardedToPlayers:\s*\[(.*?)\]/', $prize_data, $matches)) {
                $player_ids = trim($matches[1], '" ');
                $prize['awarded_to'] = !empty($player_ids) ? explode('", "', $player_ids) : array();
            }

            $prizes[] = $prize;
        }

        return $prizes;
    }

    /**
     * Extract game history from GameHistoryItem objects
     */
    private function extract_game_history($content) {
        $game_history = array();

        // Find all GameHistoryItem objects
        preg_match_all('/new GameHistoryItem\(\{([^}]+(?:\{[^}]*\}[^}]*)*)\}\)/', $content, $history_matches);

        foreach ($history_matches[1] as $history_data) {
            $history_item = array();

            // Extract timestamp
            if (preg_match('/Time:\s*(\d+)/', $history_data, $matches)) {
                $history_item['timestamp'] = intval($matches[1]);
            }

            // Extract text description
            if (preg_match('/Text:\s*"([^"]+)"/', $history_data, $matches)) {
                $history_item['text'] = $matches[1];
            }

            // Extract source
            if (preg_match('/Source:\s*(\d+)/', $history_data, $matches)) {
                $history_item['source'] = intval($matches[1]);
            }

            // Categorize history item for easier processing
            if (!empty($history_item['text'])) {
                $history_item['type'] = $this->categorize_history_item($history_item['text']);
                $game_history[] = $history_item;
            }
        }

        // Sort by timestamp for chronological processing
        usort($game_history, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        return $game_history;
    }

    /**
     * Categorize GameHistoryItem by type for processing
     */
    private function categorize_history_item($text) {
        $text_lower = strtolower($text);

        if (strpos($text_lower, 'won the tournament') !== false) {
            return 'winner_declaration';
        } elseif (strpos($text_lower, 'busted out') !== false && strpos($text_lower, 'by') !== false) {
            return 'elimination';
        } elseif (strpos($text_lower, 'busted out') !== false) {
            return 'bust_out';
        } elseif (strpos($text_lower, 'rebuys') !== false) {
            return 'rebuy';
        } elseif (strpos($text_lower, 'addon') !== false) {
            return 'addon';
        } else {
            return 'general';
        }
    }

    /**
     * Extract explicit winner from game history
     */
    private function extract_explicit_winner($game_history) {
        foreach ($game_history as $item) {
            if ($item['type'] === 'winner_declaration') {
                // Extract winner name from "X won the tournament" text
                if (preg_match('/^(.+?)\s+won the tournament$/i', $item['text'], $matches)) {
                    $winner_name = trim($matches[1]);
                    Poker_Tournament_Import_Debug::log_winner_detection($winner_name, 'GameHistoryItem', $item['timestamp']);
                    return array(
                        'name' => $winner_name,
                        'timestamp' => $item['timestamp'],
                        'source' => 'game_history'
                    );
                }
            }
        }
        Poker_Tournament_Import_Debug::log_game_history("No explicit winner found in GameHistory");
        return null;
    }

    /**
     * Map player name to UUID using existing player data
     */
    private function map_player_name_to_uuid($winner_name, $players) {
        $winner_name = strtolower(trim($winner_name));

        foreach ($players as $uuid => $player) {
            if (isset($player['nickname']) && strtolower(trim($player['nickname'])) === $winner_name) {
                return $uuid;
            }
        }

        return null;
    }

    /**
     * Extract detailed elimination information from GameHistoryItems
     */
    private function extract_elimination_details($game_history, $players) {
        $eliminations = array();

        foreach ($game_history as $item) {
            if ($item['type'] === 'elimination') {
                // Parse "X busted out ... by Y" pattern
                if (preg_match('/^(.+?)\s+busted out.*?\s+by\s+(.+?)(?:\s*\(|$)/i', $item['text'], $matches)) {
                    $eliminated_name = trim($matches[1]);
                    $eliminator_name = trim($matches[2]);

                    // Map names to UUIDs
                    $eliminated_uuid = $this->map_player_name_to_uuid($eliminated_name, $players);
                    $eliminator_uuid = $this->map_player_name_to_uuid($eliminator_name, $players);

                    if ($eliminated_uuid && $eliminator_uuid) {
                        $eliminations[] = array(
                            'eliminated_uuid' => $eliminated_uuid,
                            'eliminated_name' => $eliminated_name,
                            'eliminator_uuid' => $eliminator_uuid,
                            'eliminator_name' => $eliminator_name,
                            'timestamp' => $item['timestamp'],
                            'text' => $item['text'],
                            'source' => 'game_history'
                        );

                        Poker_Tournament_Import_Debug::log_elimination($eliminated_name, $eliminator_name, 'GameHistoryItem');
                    } else {
                        Poker_Tournament_Import_Debug::log_warning("Could not map elimination names: {$eliminated_name} by {$eliminator_name}");
                    }
                }
            }
        }

        return $eliminations;
    }

    /**
     * Enhance player data with elimination information from GameHistory
     */
    private function enhance_players_with_elimination_data($players, $game_history) {
        $eliminations = $this->extract_elimination_details($game_history, $players);

        foreach ($eliminations as $elimination) {
            $eliminated_uuid = $elimination['eliminated_uuid'];
            $eliminator_uuid = $elimination['eliminator_uuid'];

            // Add elimination details to eliminated player
            if (isset($players[$eliminated_uuid])) {
                $players[$eliminated_uuid]['elimination_details'] = array(
                    'eliminated_by_uuid' => $eliminator_uuid,
                    'eliminated_by_name' => $elimination['eliminator_name'],
                    'timestamp' => $elimination['timestamp'],
                    'source' => 'game_history',
                    'text' => $elimination['text']
                );
            }

            // Add elimination count to eliminator
            if (isset($players[$eliminator_uuid])) {
                if (!isset($players[$eliminator_uuid]['eliminations'])) {
                    $players[$eliminator_uuid]['eliminations'] = array();
                }
                $players[$eliminator_uuid]['eliminations'][] = array(
                    'eliminated_uuid' => $eliminated_uuid,
                    'eliminated_name' => $elimination['eliminated_name'],
                    'timestamp' => $elimination['timestamp']
                );
                $players[$eliminator_uuid]['elimination_count'] = count($players[$eliminator_uuid]['eliminations']);
            }
        }

        return $players;
    }

    /**
     * Process GameHistory chronologically to determine tournament progression and rankings
     * This is the NEW CORRECT approach that replaces bust-out timestamp comparison
     */
    private function process_game_history_chronologically($game_history, $players) {
        Poker_Tournament_Import_Debug::log_function("process_game_history_chronologically", [
            'game_history_count' => count($game_history),
            'players_count' => count($players)
        ]);

        if (empty($game_history)) {
            Poker_Tournament_Import_Debug::log_warning("No GameHistory data provided for chronological processing");
            return $this->fallback_to_bustout_processing($players);
        }

        // Find tournament end event to know when to stop processing
        $tournament_end_time = $this->find_tournament_end_event($game_history);
        if (!$tournament_end_time) {
            Poker_Tournament_Import_Debug::log_warning("No 'Tournament ended' event found in GameHistory");
            return $this->fallback_to_bustout_processing($players);
        }

        Poker_Tournament_Import_Debug::log_success("Found tournament end at: " . date('Y-m-d H:i:s', intval($tournament_end_time/1000)));

        // Initialize tournament state
        $tournament_state = array(
            'remaining_players' => array_keys($players), // All players start
            'eliminated_players' => array(),
            'elimination_order' => array(),
            'winner' => null,
            'winner_declaration' => null
        );

        // Process GameHistory events chronologically until tournament end
        $tournament_state = $this->track_elimination_through_history($game_history, $players, $tournament_state, $tournament_end_time);

        // Generate final rankings from tournament state
        return $this->generate_rankings_from_tournament_state($tournament_state, $players);
    }

    /**
     * Find the "Tournament ended" event in GameHistory
     */
    private function find_tournament_end_event($game_history) {
        foreach ($game_history as $item) {
            if (strpos(strtolower($item['text']), 'tournament ended') !== false) {
                Poker_Tournament_Import_Debug::log_success("Found tournament end event: " . $item['text']);
                return $item['timestamp'];
            }
        }
        return null;
    }

    /**
     * Track eliminations through chronological GameHistory processing
     */
    private function track_elimination_through_history($game_history, $players, $tournament_state, $tournament_end_time) {
        Poker_Tournament_Import_Debug::log("Processing GameHistory events chronologically until tournament end");

        foreach ($game_history as $item) {
            // Stop processing when we reach tournament end
            if ($item['timestamp'] > $tournament_end_time) {
                Poker_Tournament_Import_Debug::log("Reached tournament end time, stopping processing");
                break;
            }

            // Process winner declaration
            if ($item['type'] === 'winner_declaration') {
                $winner_name = $this->extract_winner_from_declaration($item['text']);
                $winner_uuid = $this->map_player_name_to_uuid($winner_name, $players);

                if ($winner_uuid && isset($players[$winner_uuid])) {
                    $tournament_state['winner'] = $winner_uuid;
                    $tournament_state['winner_declaration'] = $item['text'];
                    Poker_Tournament_Import_Debug::log_success("Winner declared: {$players[$winner_uuid]['nickname']} (UUID: {$winner_uuid})");
                }
                continue;
            }

            // Process elimination events
            if ($item['type'] === 'elimination') {
                $elimination_data = $this->parse_elimination_event($item['text'], $players);

                if ($elimination_data) {
                    $eliminated_uuid = $elimination_data['eliminated_uuid'];
                    $eliminator_uuid = $elimination_data['eliminator_uuid'];

                    // Only process if player hasn't been eliminated yet
                    if (in_array($eliminated_uuid, $tournament_state['remaining_players'])) {
                        // Remove from remaining players
                        $remaining_key = array_search($eliminated_uuid, $tournament_state['remaining_players']);
                        unset($tournament_state['remaining_players'][$remaining_key]);
                        $tournament_state['remaining_players'] = array_values($tournament_state['remaining_players']);

                        // Add to eliminated players with order
                        $elimination_position = count($tournament_state['eliminated_players']) + 1;
                        $tournament_state['eliminated_players'][] = $eliminated_uuid;
                        $tournament_state['elimination_order'][] = array(
                            'uuid' => $eliminated_uuid,
                            'position' => $elimination_position,
                            'eliminated_by' => $eliminator_uuid,
                            'timestamp' => $item['timestamp'],
                            'text' => $item['text']
                        );

                        Poker_Tournament_Import_Debug::log_elimination(
                            $players[$eliminated_uuid]['nickname'],
                            $players[$eliminator_uuid]['nickname'],
                            'Chronological GameHistory'
                        );
                    }
                }
            }
        }

        // If no explicit winner found, assume last remaining player is winner
        if (!$tournament_state['winner'] && count($tournament_state['remaining_players']) === 1) {
            $winner_uuid = $tournament_state['remaining_players'][0];
            $tournament_state['winner'] = $winner_uuid;
            $tournament_state['winner_declaration'] = 'Inferred from chronological processing';
            Poker_Tournament_Import_Debug::log_success("Winner inferred: {$players[$winner_uuid]['nickname']} (last remaining player)");
        }

        return $tournament_state;
    }

    /**
     * Generate final rankings from tournament state
     */
    private function generate_rankings_from_tournament_state($tournament_state, $players) {
        Poker_Tournament_Import_Debug::log("Generating final rankings from tournament state");

        $ranked_players = $players;

        // Assign position 1 to winner
        if ($tournament_state['winner'] && isset($ranked_players[$tournament_state['winner']])) {
            $ranked_players[$tournament_state['winner']]['finish_position'] = 1;
            $ranked_players[$tournament_state['winner']]['winner_source'] = 'chronological_game_history';
            $ranked_players[$tournament_state['winner']]['winner_declaration'] = $tournament_state['winner_declaration'];
            Poker_Tournament_Import_Debug::log_success("Position 1: {$ranked_players[$tournament_state['winner']]['nickname']} (chronological winner)");
        }

        // Assign positions to eliminated players in reverse elimination order
        $total_players = count($players);
        foreach ($tournament_state['elimination_order'] as $elimination) {
            $uuid = $elimination['uuid'];
            $elimination_position = $elimination['position'];

            // CRITICAL FIX: Skip if this player is the tournament winner
            // The winner's position was already correctly set to 1 above
            if ($uuid === $tournament_state['winner']) {
                Poker_Tournament_Import_Debug::log("Skipping position assignment for winner: {$ranked_players[$uuid]['nickname']}");
                continue;
            }

            // Calculate finish position: eliminated first gets last place, last eliminated gets 2nd place
            $finish_position = $total_players - $elimination_position + 1;

            if (isset($ranked_players[$uuid])) {
                $ranked_players[$uuid]['finish_position'] = $finish_position;
                $ranked_players[$uuid]['winner_source'] = 'chronological_elimination_order';
                $ranked_players[$uuid]['elimination_details'] = array(
                    'eliminated_by_uuid' => $elimination['eliminated_by'],
                    'eliminated_by_name' => $ranked_players[$elimination['eliminated_by']]['nickname'] ?? 'Unknown',
                    'timestamp' => $elimination['timestamp'],
                    'text' => $elimination['text'],
                    'source' => 'chronological_game_history'
                );

                Poker_Tournament_Import_Debug::log("Position {$finish_position}: {$ranked_players[$uuid]['nickname']} (eliminated by {$ranked_players[$elimination['eliminated_by']]['nickname']})");
            }
        }

        // Log final rankings
        Poker_Tournament_Import_Debug::log("Final chronological rankings:");
        foreach ($ranked_players as $uuid => $player) {
            if (isset($player['finish_position'])) {
                Poker_Tournament_Import_Debug::log("Position {$player['finish_position']}: {$player['nickname']} (Source: {$player['winner_source']})");
            }
        }

        return $ranked_players;
    }

    /**
     * Extract winner name from winner declaration text
     */
    private function extract_winner_from_declaration($text) {
        if (preg_match('/^(.+?)\s+won the tournament$/i', trim($text), $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Parse elimination event text to extract eliminated and eliminator
     */
    private function parse_elimination_event($text, $players) {
        // Pattern: "X busted out ... by Y"
        if (preg_match('/^(.+?)\s+busted out.*?\s+by\s+(.+?)(?:\s*\(|$)/i', $text, $matches)) {
            $eliminated_name = trim($matches[1]);
            $eliminator_name = trim($matches[2]);

            $eliminated_uuid = $this->map_player_name_to_uuid($eliminated_name, $players);
            $eliminator_uuid = $this->map_player_name_to_uuid($eliminator_name, $players);

            if ($eliminated_uuid && $eliminator_uuid) {
                return array(
                    'eliminated_uuid' => $eliminated_uuid,
                    'eliminator_uuid' => $eliminator_uuid
                );
            }
        }
        return null;
    }

    /**
     * Fallback to old bustout processing method for backward compatibility
     */
    private function fallback_to_bustout_processing($players) {
        Poker_Tournament_Import_Debug::log_warning("Falling back to bust-out timestamp processing");

        // Sort players by bust-out time
        uasort($players, function($a, $b) {
            $a_time = $this->extract_bust_out_time($a);
            $b_time = $this->extract_bust_out_time($b);

            if ($a_time === null && $b_time === null) return 0;
            if ($a_time === null) return -1;
            if ($b_time === null) return 1;

            return $a_time - $b_time;
        });

        // Assign positions
        $position = 1;
        foreach ($players as $uuid => &$player) {
            $player['finish_position'] = $position;
            $player['winner_source'] = 'fallback_bustout_timestamp';
            $position++;
        }

        return $players;
    }

    /**
     * Calculate player rankings using NEW chronological GameHistory processing
     * This replaces the old bust-out timestamp comparison approach
     */
    private function calculate_player_rankings($players, $game_history = array()) {
        Poker_Tournament_Import_Debug::log_function("calculate_player_rankings", [
            'players_count' => count($players),
            'game_history_count' => count($game_history)
        ]);

        Poker_Tournament_Import_Debug::log_success("Using NEW chronological GameHistory processing (not bust-out timestamps)");

        // Use the new chronological processing approach
        $players = $this->process_game_history_chronologically($game_history, $players);

        return $players;
    }

  
    /**
     * Calculate player hits by counting eliminations from buyin data
     *
     * v2.4.25: Hits are calculated from actual elimination data in buyins,
     * not from the HitsAdjustment field which may be missing or incorrect
     *
     * @param array $players Players array
     * @return array Players with updated hit counts
     */
    private function calculate_hits_from_eliminations($players) {
        // Initialize hit counts to zero
        $hit_counts = array();
        foreach ($players as $uuid => $player) {
            $hit_counts[$uuid] = 0;
        }

        // Count eliminations: loop through all players' buyins and count
        // how many times each player's UUID appears as an eliminator
        foreach ($players as $uuid => $player) {
            if (isset($player['buyins']) && is_array($player['buyins'])) {
                foreach ($player['buyins'] as $buyin) {
                    if (isset($buyin['eliminated_by']) && is_array($buyin['eliminated_by'])) {
                        foreach ($buyin['eliminated_by'] as $eliminator_uuid) {
                            if (isset($hit_counts[$eliminator_uuid])) {
                                $hit_counts[$eliminator_uuid]++;
                            }
                        }
                    }
                }
            }
        }

        // Update player hit counts
        foreach ($players as $uuid => $player) {
            $players[$uuid]['hits'] = $hit_counts[$uuid];
        }

        // Debug logging
        Poker_Tournament_Import_Debug::log_success("v2.4.25: Calculated hits from elimination data");
        foreach ($players as $uuid => $player) {
            if ($player['hits'] > 0) {
                Poker_Tournament_Import_Debug::log("  {$player['nickname']}: {$player['hits']} hits");
            }
        }

        return $players;
    }

    /**
     * Extract bust-out time from player data
     */
    private function extract_bust_out_time($player) {
        foreach ($player['buyins'] as $buyin) {
            // Find the last (most recent) buyin with bust-out data
            if (isset($buyin['bust_out_time'])) {
                return $buyin['bust_out_time'];
            }
        }
        return null; // No bust-out = still in tournament
    }

    /**
     * Calculate Tournament Director points for all players using formula system
     * Now supports per-tournament formulas extracted from .tdt files
     */
    private function calculate_tournament_points($players, $financial) {
        $total_players = count($players);

        // CRITICAL FIX: Calculate total money using actual buy-in amounts, not chip counts
        // GameBuyin.Amount contains starting chip count (e.g., 5000 chips), NOT dollar amount
        // We need to use FeeProfile.Fee which contains the actual buy-in dollar amount (e.g., $200)

        $buy_in_amount = $financial['buy_in'] ?? 0;
        $total_money = 0;
        $total_buyins = 0;
        $total_rebuys = 0;
        $total_addons = 0;

        // CRITICAL FIX v2.4.8: Calculate total money using ACTUAL dollar amounts from each buyin
        // Each buyin may have different ProfileName (e.g., "Standard", "Double") with different fees
        // We must look up the dollar amount for each buyin individually, not use a flat buy_in_amount
        Poker_Tournament_Import_Debug::log("=== v2.4.8 BUYIN CALCULATION DEBUG ===");

        foreach ($players as $player) {
            $buyin_count = isset($player['buyins']) ? count($player['buyins']) : 0;
            $buyins_exist = isset($player['buyins']);
            $buyins_is_array = is_array($player['buyins'] ?? null);

            Poker_Tournament_Import_Debug::log("Player {$player['nickname']}: buyins_exist=" . ($buyins_exist ? 'YES' : 'NO') . ", is_array=" . ($buyins_is_array ? 'YES' : 'NO') . ", count={$buyin_count}");

            if (!empty($player['buyins']) && is_array($player['buyins'])) {
                $player_buyin_count = count($player['buyins']);
                $total_buyins += $player_buyin_count;

                // Calculate total money by summing ACTUAL dollar amounts from each buyin
                foreach ($player['buyins'] as $buyin) {
                    $profile_name = $buyin['profile'] ?? 'Standard';
                    if (isset($financial['fee_profiles'][$profile_name])) {
                        $dollar_amount = $financial['fee_profiles'][$profile_name]['fee'];
                    } else {
                        $dollar_amount = $buy_in_amount; // Fallback to default
                    }
                    $total_money += $dollar_amount;
                }

                Poker_Tournament_Import_Debug::log("  → {$player['nickname']}: {$player_buyin_count} buyins, total invested: \${$player['total_invested']}");
            } else {
                Poker_Tournament_Import_Debug::log("  → Player {$player['nickname']} skipped - empty or non-array buyins");
            }
        }

        Poker_Tournament_Import_Debug::log("Financial Calculation Summary:");
        Poker_Tournament_Import_Debug::log("  Default buy-in amount: \${$buy_in_amount}");
        Poker_Tournament_Import_Debug::log("  Total buyins (including re-entries): {$total_buyins}");
        Poker_Tournament_Import_Debug::log("  Total money (sum of all buyin dollar amounts): \${$total_money}");
        Poker_Tournament_Import_Debug::log("=================================");

        // Initialize formula validator
        $formula_validator = new Poker_Tournament_Formula_Validator();

        // FORMULA PRIORITY HIERARCHY:
        // 0. User override from import form (NEW - HIGHEST PRIORITY)
        // 1. Per-tournament formula from .tdt file
        // 2. Active global formula from settings
        // 3. Default tournament_points formula
        // 4. Hardcoded fallback formula

        $formula_data = null;
        $formula_source = null;

        // Priority 0: User override from import (NEW)
        if ($this->formula_override) {
            $formula_data = $formula_validator->get_formula($this->formula_override);
            if ($formula_data) {
                $formula_source = 'user_override_' . $this->formula_override;
                Poker_Tournament_Import_Debug::log_success("Using USER-SELECTED override formula: {$this->formula_override}");
            } else {
                Poker_Tournament_Import_Debug::log_warning("User selected formula '{$this->formula_override}' not found, falling back");
            }
        }

        // Priority 1: Check for per-tournament formula from .tdt file
        if (!$formula_data && isset($this->tournament_data['metadata']['points_formula']) &&
            !empty($this->tournament_data['metadata']['points_formula'])) {
            $formula_data = $this->tournament_data['metadata']['points_formula'];
            $formula_source = 'tdt_file';
            Poker_Tournament_Import_Debug::log_success("Using PointsForPlaying formula from .tdt file");
        }

        // Priority 2: Get active tournament points formula from settings
        if (!$formula_data) {
            $active_formula = get_option('poker_active_tournament_formula', 'tournament_points');
            $formula_data = $formula_validator->get_formula($active_formula);
            if ($formula_data) {
                $formula_source = 'settings_' . $active_formula;
                Poker_Tournament_Import_Debug::log("Using active formula from settings: {$active_formula}");
            }
        }

        // Priority 3: Fallback to default if formula not found
        if (!$formula_data) {
            $formula_data = $formula_validator->get_formula('tournament_points');
            if ($formula_data) {
                $formula_source = 'default_tournament_points';
                Poker_Tournament_Import_Debug::log("Using default tournament_points formula");
            }
        }

        // Priority 4: Hardcoded fallback formula if all else fails (PokerStars specification)
        if (!$formula_data) {
            $formula_data = array(
                'formula' => 'assign("points", if((T80 > r) and (T33 < r), round(baseFromT33 * decay) + hits, if(T33 >= r, baseAtRank + hits, 1 + hits)))',
                'dependencies' => array(
                    'assign("nSafe", max(n, 1))',
                    'assign("buyinsSafe", max(buyins, 1))',
                    'assign("T33", round(nSafe / 3))',
                    'assign("T80", floor(nSafe * 0.9))',
                    'assign("monies", totalBuyinsAmount + totalRebuysAmount + totalAddOnsAmount)',
                    'assign("avgBC", monies / buyinsSafe)',
                    'assign("scale", 10 * sqrt(nSafe))',
                    'assign("logTerm", 1 + log(avgBC + 0.25))',
                    'assign("hits", numberofHits * 10)',
                    'assign("baseAtRank", round((scale / sqrt(r)) * logTerm))',
                    'assign("baseFromT33", round((scale / sqrt(T33 + 1)) * logTerm))',
                    'assign("decay", pow(0.66, (r - T33)))'
                )
            );
            $formula_source = 'hardcoded_fallback';
            Poker_Tournament_Import_Debug::log_warning("Using hardcoded fallback PokerStars formula");
        }

        // Calculate points for each player
        foreach ($players as $uuid => $player) {
            // Prepare tournament data for formula calculation
            $tournament_data = array(
                'total_players' => $total_players,
                'finish_position' => $player['finish_position'],
                'hits' => $player['hits'],
                'total_money' => $total_money,
                'total_buyins' => $total_buyins,
                'total_rebuys' => $total_rebuys,
                'total_addons' => $total_addons,
                'total_buyins_amount' => $total_money,
                'total_rebuys_amount' => $total_rebuys * ($financial['rebuy_amount'] ?? 0),
                'total_addons_amount' => $total_addons * ($financial['addon_amount'] ?? 0),
                'buyin_amount' => $buy_in_amount,  // CRITICAL FIX: Use extracted buy-in amount from FeeProfile
                'fee_amount' => $financial['fee_amount'] ?? 0,
                'prize_pool' => $total_money,
                'winnings' => $player['winnings'] ?? 0
            );

            // Build complete formula with dependencies
            $complete_formula = '';
            if (!empty($formula_data['dependencies'])) {
                $complete_formula = implode(';', $formula_data['dependencies']) . ';';
            }
            $complete_formula .= $formula_data['formula'];

            // Calculate points using formula system
            $result = $formula_validator->calculate_formula($complete_formula, $tournament_data, 'tournament');

            if ($result['success']) {
                $players[$uuid]['points'] = $result['result'];
                $players[$uuid]['points_calculation'] = array(
                    'formula_used' => $formula_source,
                    'formula_description' => $formula_data['description'] ?? 'Tournament points calculation',
                    'variables' => $result['variables'],
                    'final_points' => $result['result'],
                    'warnings' => $result['warnings'] ?? array()
                );
            } else {
                // FIX #4: Enhanced debug logging for formula failures
                // Fallback to basic calculation if formula fails
                $players[$uuid]['points'] = max(1, $total_players - $player['finish_position'] + 1);
                $players[$uuid]['points_calculation'] = array(
                    'formula_used' => 'error_fallback',
                    'formula_source' => $formula_source,
                    'error' => $result['error'],
                    'final_points' => $players[$uuid]['points']
                );

                // FIX #4: Comprehensive error logging for formula debugging
                Poker_Tournament_Import_Debug::log_warning("=== FORMULA CALCULATION FAILURE ===");
                Poker_Tournament_Import_Debug::log_warning("Player: {$player['nickname']} (Position: {$player['finish_position']})");
                Poker_Tournament_Import_Debug::log_warning("Formula Source: {$formula_source}");
                Poker_Tournament_Import_Debug::log_warning("Error: {$result['error']}");
                Poker_Tournament_Import_Debug::log_warning("Complete Formula Attempted:");
                Poker_Tournament_Import_Debug::log_warning($complete_formula);
                Poker_Tournament_Import_Debug::log_warning("Tournament Data Provided:");
                Poker_Tournament_Import_Debug::log_warning(print_r($tournament_data, true));
                Poker_Tournament_Import_Debug::log_warning("Fallback Points: {$players[$uuid]['points']} (using n-r+1 formula)");
                Poker_Tournament_Import_Debug::log_warning("===================================");
            }
        }

        return $players;
    }

    /**
     * Calculate winnings by rank and prize distribution
     */
    private function calculate_winnings_by_rank($players, $prizes) {
        // ALWAYS log to error_log (even when debug mode is off)
        error_log("Poker Import - === WINNINGS CALCULATION START ===");
        error_log("Poker Import - Total prizes to distribute: " . count($prizes));
        error_log("Poker Import - Total players to check: " . count($players));

        Poker_Tournament_Import_Debug::log("=== WINNINGS CALCULATION DEBUG ===");
        Poker_Tournament_Import_Debug::log("Total prizes to distribute: " . count($prizes));
        Poker_Tournament_Import_Debug::log("Total players to check: " . count($players));

        // Log all prizes
        foreach ($prizes as $i => $prize) {
            $pos = $prize['position'] ?? 'NULL';
            $amt = $prize['calculated_amount'] ?? 0;
            error_log("Poker Import - Prize " . ($i+1) . ": Position={$pos} (type: " . gettype($pos) . "), Amount=\${$amt}");
            Poker_Tournament_Import_Debug::log("  Prize " . ($i+1) . ": Position={$pos} (type: " . gettype($pos) . "), Amount=\${$amt}");
        }

        // Log all player positions
        Poker_Tournament_Import_Debug::log("Player finish positions:");
        foreach ($players as $uuid => $player) {
            $pos = $player['finish_position'] ?? 'NULL';
            $nick = $player['nickname'] ?? 'Unknown';
            Poker_Tournament_Import_Debug::log("  {$nick}: Position={$pos} (type: " . gettype($pos) . ")");
        }

        $matches_found = 0;
        foreach ($prizes as $prize) {
            if (isset($prize['position']) && $prize['calculated_amount'] > 0) {
                $position = $prize['position'];

                // Find player at this position
                $match_found = false;
                foreach ($players as $uuid => $player) {
                    // CRITICAL FIX v2.4.38: Use intval() for type-safe comparison
                    // Prize position may be string "1" while player finish_position is integer 1
                    if (intval($player['finish_position']) === intval($position)) {
                        $players[$uuid]['winnings'] = $prize['calculated_amount'];
                        $match_found = true;
                        $matches_found++;
                        Poker_Tournament_Import_Debug::log_success("MATCH: {$player['nickname']} at position {$position} wins \${$prize['calculated_amount']}");
                        // Always log to error_log for debugging (even when debug mode is off)
                        error_log("Poker Import - WINNINGS MATCH: {$player['nickname']} at position {$position} wins \${$prize['calculated_amount']}");
                        break;
                    }
                }

                if (!$match_found) {
                    error_log("Poker Import - WARNING: NO MATCH for prize at position {$position} (\${$prize['calculated_amount']})");
                    Poker_Tournament_Import_Debug::log_warning("NO MATCH for prize at position {$position} (\${$prize['calculated_amount']})");
                }
            } else {
                $pos_status = isset($prize['position']) ? "position={$prize['position']}" : "NO POSITION";
                $amt_status = isset($prize['calculated_amount']) ? "amount={$prize['calculated_amount']}" : "NO AMOUNT";
                error_log("Poker Import - WARNING: Prize skipped: {$pos_status}, {$amt_status}");
                Poker_Tournament_Import_Debug::log_warning("Prize skipped: {$pos_status}, {$amt_status}");
            }
        }

        error_log("Poker Import - Winnings calculation complete: {$matches_found} matches found");
        error_log("Poker Import - === WINNINGS CALCULATION END ===");
        Poker_Tournament_Import_Debug::log_success("Winnings calculation complete: {$matches_found} matches found");
        Poker_Tournament_Import_Debug::log("===================================");

        return $players;
    }

    /**
     * Validate parsed data with GameHistory cross-validation
     */
    public function validate_data() {
        $errors = array();
        $warnings = array();

        if (empty($this->tournament_data['metadata']['uuid'])) {
            $errors[] = 'Tournament UUID is missing';
        }

        if (empty($this->tournament_data['metadata']['title'])) {
            $errors[] = 'Tournament title is missing';
        }

        if (empty($this->tournament_data['players'])) {
            $errors[] = 'No player data found';
        }

        // CRITICAL FIX: Validate GameHistory processing
        if (isset($this->tournament_data['game_history'])) {
            $validation_result = $this->validate_game_history_integration();
            $errors = array_merge($errors, $validation_result['errors']);
            $warnings = array_merge($warnings, $validation_result['warnings']);
        }

        // Additional validation for points calculation
        $players_with_points = array_filter($this->tournament_data['players'], function($player) {
            return isset($player['points']) && $player['points'] > 0;
        });

        if (empty($players_with_points)) {
            $errors[] = 'No player points calculated';
        }

        return array(
            'errors' => $errors,
            'warnings' => $warnings
        );
    }

    /**
     * Validate GameHistory integration and detect data conflicts
     */
    private function validate_game_history_integration() {
        $errors = array();
        $warnings = array();

        $game_history = $this->tournament_data['game_history'] ?? array();
        $players = $this->tournament_data['players'] ?? array();

        // Check for explicit winner in GameHistory
        $explicit_winner = $this->extract_explicit_winner($game_history);
        if ($explicit_winner) {
            $winner_uuid = $this->map_player_name_to_uuid($explicit_winner['name'], $players);

            if (!$winner_uuid) {
                $errors[] = "GameHistory declares winner '{$explicit_winner['name']}' but this player is not found in tournament data";
            } else {
                // Check if winner matches position 1 in calculated rankings
                if (!isset($players[$winner_uuid]) || $players[$winner_uuid]['finish_position'] !== 1) {
                    $actual_winner = $this->find_player_by_position($players, 1);
                    $winner_name = $actual_winner['nickname'] ?? 'Unknown';
                    $warnings[] = "GameHistory winner ({$explicit_winner['name']}) doesn't match calculated winner ({$winner_name}) - GameHistory data should take precedence";
                }
            }
        }

        // Validate elimination data consistency
        $gh_eliminations = $this->extract_elimination_details($game_history, $players);
        $bustout_eliminations = $this->extract_bustout_eliminations($players);

        $mismatch_count = $this->compare_elimination_sources($gh_eliminations, $bustout_eliminations);
        if ($mismatch_count > 0) {
            $warnings[] = "Found {$mismatch_count} elimination discrepancies between GameHistory and bustout data - GameHistory should be authoritative";
        }

        // Check for players mentioned in GameHistory but not in player list
        $mentioned_players = $this->extract_all_mentioned_players($game_history);
        $missing_players = array_diff($mentioned_players, array_keys($players));
        if (!empty($missing_players)) {
            $warnings[] = "GameHistory mentions players not found in tournament data: " . implode(', ', $missing_players);
        }

        return array(
            'errors' => $errors,
            'warnings' => $warnings
        );
    }

    /**
     * Find player by finish position
     */
    private function find_player_by_position($players, $position) {
        foreach ($players as $player) {
            if (isset($player['finish_position']) && $player['finish_position'] === $position) {
                return $player;
            }
        }
        return null;
    }

    /**
     * Extract elimination data from bustout records
     */
    private function extract_bustout_eliminations($players) {
        $eliminations = array();

        foreach ($players as $uuid => $player) {
            if (isset($player['buyins'])) {
                foreach ($player['buyins'] as $buyin) {
                    if (isset($buyin['eliminated_by']) && !empty($buyin['eliminated_by'])) {
                        foreach ($buyin['eliminated_by'] as $eliminator_uuid) {
                            $eliminations[] = array(
                                'eliminated_uuid' => $uuid,
                                'eliminator_uuid' => $eliminator_uuid,
                                'timestamp' => $buyin['bust_out_time'] ?? null,
                                'source' => 'bustout'
                            );
                        }
                    }
                }
            }
        }

        return $eliminations;
    }

    /**
     * Compare elimination data from different sources
     */
    private function compare_elimination_sources($gh_eliminations, $bustout_eliminations) {
        $mismatch_count = 0;
        $gh_pairs = array();

        // Create comparison pairs for GameHistory
        foreach ($gh_eliminations as $elim) {
            $pair = $elim['eliminated_uuid'] . '->' . $elim['eliminator_uuid'];
            $gh_pairs[$pair] = $elim;
        }

        // Compare with bustout data
        foreach ($bustout_eliminations as $elim) {
            $pair = $elim['eliminated_uuid'] . '->' . $elim['eliminator_uuid'];
            if (!isset($gh_pairs[$pair])) {
                $mismatch_count++;
            }
        }

        return $mismatch_count;
    }

    /**
     * Extract all player names mentioned in GameHistory
     */
    private function extract_all_mentioned_players($game_history) {
        $mentioned = array();

        // Comprehensive exclusion list for non-player terms
        $exclude_words = array(
            // Tournament terms
            'Table', 'Seat', 'Tournament', 'Double', 'End', 'Start', 'User', 'Undo',
            'Standard', 'Profile', 'Planting', 'Fee', 'Rake', 'Chips', 'Points',
            // Common terms
            'The', 'And', 'By', 'At', 'In', 'Of', 'To', 'With', 'For', 'Is', 'Was',
            // Actions
            'Won', 'Busted', 'Out', 'Rebuys', 'Addon', 'Add', 'On', 'Rebuy',
            // Positions
            'First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth', 'Seventh', 'Eighth', 'Ninth', 'Tenth',
            // Poker terms
            'Blind', 'Small', 'Big', 'Ante', 'Pot', 'Cards', 'Deal', 'Flop', 'Turn', 'River',
            // Numbers and patterns
            'st', 'nd', 'rd', 'th', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th',
            // File/technical terms
            'Source', 'Time', 'ID', 'UUID', 'Data', 'Game', 'Player', 'History', 'Item'
        );

        foreach ($game_history as $item) {
            // Only extract from elimination and winner declarations
            if ($item['type'] === 'elimination' || $item['type'] === 'winner_declaration') {
                // Enhanced regex to avoid over-extraction
                if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2})\b/', $item['text'], $matches)) {
                    foreach ($matches[1] as $name) {
                        // Clean and validate name
                        $name = trim($name);

                        // Multiple validation filters
                        if ($this->is_valid_player_name($name, $exclude_words)) {
                            $mentioned[] = $name;
                        }
                    }
                }
            }
        }

        return array_unique($mentioned);
    }

    /**
     * Validate if extracted name is actually a player name
     */
    private function is_valid_player_name($name, $exclude_words) {
        // Basic length check
        if (strlen($name) < 2 || strlen($name) > 30) {
            return false;
        }

        // Check exclusion list
        if (in_array($name, $exclude_words)) {
            return false;
        }

        // Check for common non-player patterns
        if (preg_match('/\d/', $name)) { // Contains numbers
            return false;
        }

        if (preg_match('/^(st|nd|rd|th)$/i', $name)) { // Ordinal suffixes
            return false;
        }

        // Must start with uppercase and contain only letters and spaces
        if (!preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*$/', $name)) {
            return false;
        }

        // Single word names are more likely to be valid
        if (strpos($name, ' ') === false) {
            return true;
        }

        // For multi-word names, check if they're common patterns
        $common_patterns = array('Table', 'Seat', 'Round', 'Level', 'Break', 'Time');
        foreach ($common_patterns as $pattern) {
            if (stripos($name, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }
}