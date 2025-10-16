<?php
/**
 * TDT Domain Mapper
 *
 * Converts Abstract Syntax Tree from TDT_Parser into the data structure
 * expected by the Poker Tournament Import plugin. This bridges the gap between
 * the generic AST parser and our domain-specific needs.
 *
 * @package Poker_Tournament_Import
 * @subpackage Parsers
 * @since 2.4.9
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Domain mapper for poker tournament data
 *
 * Navigates the AST and extracts tournament data in the format expected
 * by existing plugin code. Replaces fragile regex extraction with structured
 * AST navigation.
 *
 * @since 2.4.9
 */
class Poker_Tournament_Domain_Mapper {

    /**
     * Map root AST node to tournament data structure
     *
     * @param array $root_ast Root AST node from TDT_Parser
     * @return array Tournament data in plugin's expected format
     * @throws Exception If required data is missing or malformed
     */
    public function map_tournament_data($root_ast) {
        $this->assert_type($root_ast, 'Object');
        $entries = $root_ast['entries'];

        // Extract version if present
        $version = $this->get_scalar($entries, 'V');

        // Find Tournament node
        if (!isset($entries['T'])) {
            throw new Exception('No Tournament node found in .tdt file');
        }

        $tournament_node = $entries['T'];
        if (!$this->is_new_with_ctor($tournament_node, 'Tournament')) {
            throw new Exception('T node is not a Tournament constructor');
        }

        // Extract all tournament data
        $tournament_obj = $this->expect_object($tournament_node['arg']);
        $t_entries = $tournament_obj['entries'];

        return array(
            'metadata' => $this->extract_metadata($t_entries),
            'financial' => $this->extract_financial($t_entries),
            'players' => $this->extract_players($t_entries),
            'game_history' => $this->extract_game_history($t_entries),
            'structure' => $this->extract_structure($t_entries),
            'prizes' => $this->extract_prizes($t_entries)
        );
    }

    /**
     * Extract metadata from tournament entries
     *
     * @param array $entries Tournament object entries
     * @return array Metadata array
     */
    private function extract_metadata($entries) {
        $metadata = array();

        // Extract UUID
        $metadata['uuid'] = $this->get_scalar($entries, 'UUID');

        // Extract League info
        $metadata['league_uuid'] = $this->get_scalar($entries, 'LeagueUUID');
        $metadata['league_name'] = $this->get_scalar($entries, 'LeagueName');

        // Extract Season info
        $metadata['season_uuid'] = $this->get_scalar($entries, 'SeasonUUID');
        $metadata['season_name'] = $this->get_scalar($entries, 'SeasonName');

        // Extract Title and Description
        $metadata['title'] = $this->get_scalar($entries, 'Title');
        $metadata['description'] = $this->get_scalar($entries, 'Description');

        // Extract StartTime (convert from milliseconds to date string)
        $start_time_ms = $this->get_scalar($entries, 'StartTime');
        if ($start_time_ms !== null) {
            $metadata['start_time'] = date('Y-m-d H:i:s', intval($start_time_ms / 1000));
        }

        // Extract PointsForPlaying formula
        $metadata['points_formula'] = $this->extract_points_formula($entries);

        return $metadata;
    }

    /**
     * Extract PointsForPlaying formula from tournament entries
     *
     * @param array $entries Tournament object entries
     * @return array|null Formula data or null if not found
     */
    private function extract_points_formula($entries) {
        if (!isset($entries['PointsForPlaying'])) {
            return null;
        }

        $node = $entries['PointsForPlaying'];
        if (!$this->is_new_with_ctor($node, 'UserFormula')) {
            return null;
        }

        $formula_obj = $this->expect_object($node['arg']);
        $f_entries = $formula_obj['entries'];

        $formula_data = array();

        // Extract Formula field
        $formula_data['formula'] = $this->get_scalar($f_entries, 'Formula');

        // Extract Dependencies array
        if (isset($f_entries['Dependencies'])) {
            $deps_node = $f_entries['Dependencies'];
            if ($this->is_type($deps_node, 'Array')) {
                $dependencies = array();
                foreach ($deps_node['items'] as $item) {
                    if ($this->is_type($item, 'String')) {
                        $dependencies[] = $item['value'];
                    }
                }
                $formula_data['dependencies'] = $dependencies;
            }
        }

        // Extract Description
        $formula_data['description'] = $this->get_scalar($f_entries, 'Description');

        if (!empty($formula_data['formula'])) {
            Poker_Tournament_Import_Debug::log_success("Extracted PointsForPlaying formula from .tdt file (AST parser)");
            return $formula_data;
        }

        return null;
    }

    /**
     * Extract financial data (fee profiles)
     *
     * @param array $entries Tournament object entries
     * @return array Financial data
     */
    private function extract_financial($entries) {
        $financial = array(
            'fee_profiles' => array(),
            'buy_in' => 0
        );

        if (!isset($entries['Financials'])) {
            return $financial;
        }

        $financials_node = $entries['Financials'];
        if (!$this->is_new_with_ctor($financials_node, 'FinancialConfig')) {
            return $financial;
        }

        $fin_obj = $this->expect_object($financials_node['arg']);
        $fin_entries = $fin_obj['entries'];

        // Extract Buyins config
        if (isset($fin_entries['Buyins'])) {
            $buyins_node = $fin_entries['Buyins'];
            if ($this->is_new_with_ctor($buyins_node, 'BuyConfig')) {
                $buy_obj = $this->expect_object($buyins_node['arg']);
                $buy_entries = $buy_obj['entries'];

                // Extract FeeProfile array
                if (isset($buy_entries['Profiles'])) {
                    $profiles_node = $buy_entries['Profiles'];
                    if ($this->is_type($profiles_node, 'Array')) {
                        foreach ($profiles_node['items'] as $profile_item) {
                            if ($this->is_new_with_ctor($profile_item, 'FeeProfile')) {
                                $profile = $this->extract_fee_profile($profile_item);
                                if ($profile) {
                                    $financial['fee_profiles'][$profile['name']] = $profile;

                                    // Set default buy_in from first profile (or "Standard" if found)
                                    if (!isset($financial['buy_in']) || $profile['name'] === 'Standard') {
                                        $financial['buy_in'] = $profile['fee'];
                                        Poker_Tournament_Import_Debug::log("Extracted buy-in amount from FeeProfile '{$profile['name']}': \${$profile['fee']} (AST parser)");
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $financial;
    }

    /**
     * Extract FeeProfile data
     *
     * @param array $profile_node FeeProfile New node
     * @return array|null Profile data or null
     */
    private function extract_fee_profile($profile_node) {
        $profile_obj = $this->expect_object($profile_node['arg']);
        $p_entries = $profile_obj['entries'];

        $name = $this->get_scalar($p_entries, 'Name');
        $fee = $this->get_scalar($p_entries, 'Fee');

        if ($name === null || $fee === null) {
            return null;
        }

        return array(
            'name' => $name,
            'fee' => intval($fee)
        );
    }

    /**
     * Extract players data with buyins
     *
     * @param array $entries Tournament object entries
     * @return array Players array keyed by UUID
     */
    private function extract_players($entries) {
        $players = array();

        if (!isset($entries['Players'])) {
            return $players;
        }

        $players_wrapper = $entries['Players'];

        // v2.4.14: Check if wrapped in GamePlayers constructor
        if ($this->is_new_with_ctor($players_wrapper, 'GamePlayers')) {
            // Extract the inner GamePlayers object
            $game_players_obj = $this->expect_object($players_wrapper['arg']);
            $gp_entries = $game_players_obj['entries'];

            // Get the inner Players field
            if (!isset($gp_entries['Players'])) {
                return $players;
            }

            $players_node = $gp_entries['Players'];
        } else {
            // Direct array format (older files)
            $players_node = $players_wrapper;
        }

        // v2.4.11: Unwrap Call expressions like Map.from([...])
        $players_node = $this->unwrap_call($players_node);

        if (!$this->is_type($players_node, 'Array')) {
            return $players;
        }

        // Process the array - could be direct GamePlayer items or Map.from pairs
        foreach ($players_node['items'] as $i => $player_item) {
            // v2.4.14: Handle Map.from() format: [[key, GamePlayer], [key, GamePlayer], ...]
            if ($this->is_type($player_item, 'Array') && count($player_item['items'] ?? array()) >= 2) {
                // Map.from() creates arrays like [[key, value], [key, value]]
                $player_constructor = $player_item['items'][1];  // Second element is the GamePlayer
                if ($this->is_new_with_ctor($player_constructor, 'GamePlayer')) {
                    $player = $this->extract_game_player($player_constructor);
                    if ($player && !empty($player['uuid'])) {
                        $players[$player['uuid']] = $player;
                    }
                }
            } elseif ($this->is_new_with_ctor($player_item, 'GamePlayer')) {
                // Direct GamePlayer format (older files)
                $player = $this->extract_game_player($player_item);
                if ($player && !empty($player['uuid'])) {
                    $players[$player['uuid']] = $player;
                }
            }
        }

        Poker_Tournament_Import_Debug::log_success("Extracted " . count($players) . " players using AST parser");
        return $players;
    }

    /**
     * Extract GamePlayer data
     *
     * @param array $player_node GamePlayer New node
     * @return array|null Player data or null
     */
    private function extract_game_player($player_node) {
        $player_obj = $this->expect_object($player_node['arg']);
        $p_entries = $player_obj['entries'];

        $uuid = $this->get_scalar($p_entries, 'UUID');

        // v2.4.17: Handle nested PlayerName constructor
        // Modern .tdt files have: Name: new PlayerName({Nickname: "..."})
        // Older files might have: Name: "..." or Nickname: "..."
        $name_node = $p_entries['Name'] ?? null;
        $nickname = null;

        if ($name_node && $this->is_new_with_ctor($name_node, 'PlayerName')) {
            // Modern format: Name is wrapped in PlayerName constructor
            $player_name_obj = $this->expect_object($name_node['arg']);
            $pn_entries = $player_name_obj['entries'];
            $nickname = $this->get_scalar($pn_entries, 'Nickname');
        } else if ($name_node) {
            // Legacy format: Name is a direct string
            $nickname = $this->get_scalar($p_entries, 'Name');
        }

        // Fallback: Try old Nickname field for very old files
        if (empty($nickname)) {
            $nickname = $this->get_scalar($p_entries, 'Nickname');
        }

        $player = array(
            'uuid' => $uuid,
            'nickname' => $nickname,
            'buyins' => array(),
            'buyins_count' => 0,
            'total_invested' => 0,
            'hits' => intval($this->get_scalar($p_entries, 'HitsAdjustment') ?? 0)
        );

        if (empty($player['uuid']) || empty($player['nickname'])) {
            return null;
        }

        // CRITICAL FIX v2.4.9: Extract Buyins array from WITHIN GamePlayer object
        if (isset($p_entries['Buyins'])) {
            $buyins_node = $p_entries['Buyins'];
            if ($this->is_type($buyins_node, 'Array')) {
                foreach ($buyins_node['items'] as $buyin_item) {
                    if ($this->is_new_with_ctor($buyin_item, 'GameBuyin')) {
                        $buyin = $this->extract_game_buyin($buyin_item);
                        if ($buyin) {
                            $player['buyins'][] = $buyin;
                        }
                    }
                }
            }
        }

        $player['buyins_count'] = count($player['buyins']);

        // Note: total_invested will be calculated after we have financial data
        // This is done in the main parser class

        return $player;
    }

    /**
     * Extract GameBuyin data
     *
     * @param array $buyin_node GameBuyin New node
     * @return array|null Buyin data or null
     */
    private function extract_game_buyin($buyin_node) {
        $buyin_obj = $this->expect_object($buyin_node['arg']);
        $b_entries = $buyin_obj['entries'];

        $buyin = array(
            'amount' => intval($this->get_scalar($b_entries, 'Amount') ?? 0),
            'chips' => intval($this->get_scalar($b_entries, 'Chips') ?? 0),
            'profile' => $this->get_scalar($b_entries, 'ProfileName') ?? 'Standard'
        );

        // Extract BustOut data if present
        if (isset($b_entries['BustOut'])) {
            $bustout_node = $b_entries['BustOut'];
            if ($this->is_new_with_ctor($bustout_node, 'GameBustOut')) {
                $bustout_obj = $this->expect_object($bustout_node['arg']);
                $bo_entries = $bustout_obj['entries'];

                $buyin['bust_out_time'] = intval($this->get_scalar($bo_entries, 'Time') ?? 0);
                $buyin['bust_out_round'] = intval($this->get_scalar($bo_entries, 'Round') ?? 0);

                // Extract HitmanUUID array
                if (isset($bo_entries['HitmanUUID'])) {
                    $hitman_node = $bo_entries['HitmanUUID'];
                    if ($this->is_type($hitman_node, 'Array')) {
                        $hitman_uuids = array();
                        foreach ($hitman_node['items'] as $uuid_item) {
                            if ($this->is_type($uuid_item, 'String')) {
                                $hitman_uuids[] = $uuid_item['value'];
                            }
                        }
                        $buyin['eliminated_by'] = $hitman_uuids;
                    }
                }
            }
        }

        return $buyin;
    }

    /**
     * Extract game history
     *
     * @param array $entries Tournament object entries
     * @return array Game history items
     */
    private function extract_game_history($entries) {
        $history = array();

        if (!isset($entries['History'])) {
            return $history;
        }

        $history_node = $entries['History'];
        if (!$this->is_type($history_node, 'Array')) {
            return $history;
        }

        foreach ($history_node['items'] as $history_item) {
            if ($this->is_new_with_ctor($history_item, 'GameHistoryItem')) {
                $item = $this->extract_game_history_item($history_item);
                if ($item) {
                    $history[] = $item;
                }
            }
        }

        return $history;
    }

    /**
     * Extract GameHistoryItem data
     *
     * @param array $item_node GameHistoryItem New node
     * @return array|null History item or null
     */
    private function extract_game_history_item($item_node) {
        $item_obj = $this->expect_object($item_node['arg']);
        $i_entries = $item_obj['entries'];

        $item = array(
            'timestamp' => intval($this->get_scalar($i_entries, 'Time') ?? 0),
            'text' => $this->get_scalar($i_entries, 'Text') ?? '',
            'source' => intval($this->get_scalar($i_entries, 'Source') ?? 0)
        );

        if (empty($item['text'])) {
            return null;
        }

        // Categorize history item
        $item['type'] = $this->categorize_history_item($item['text']);

        return $item;
    }

    /**
     * Categorize history item by text content
     *
     * @param string $text History item text
     * @return string Category type
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
     * Extract tournament structure (levels/rounds)
     *
     * @param array $entries Tournament object entries
     * @return array Structure data
     */
    private function extract_structure($entries) {
        $structure = array('levels' => array());

        if (!isset($entries['Levels'])) {
            return $structure;
        }

        $levels_node = $entries['Levels'];
        if (!$this->is_type($levels_node, 'Array')) {
            return $structure;
        }

        foreach ($levels_node['items'] as $level_item) {
            if ($this->is_new_with_ctor($level_item, 'GameRound')) {
                $round = $this->extract_game_round($level_item);
                if ($round) {
                    $structure['levels'][] = $round;
                }
            }
        }

        return $structure;
    }

    /**
     * Extract GameRound data
     *
     * @param array $round_node GameRound New node
     * @return array|null Round data or null
     */
    private function extract_game_round($round_node) {
        $round_obj = $this->expect_object($round_node['arg']);
        $r_entries = $round_obj['entries'];

        return array(
            'small_blind' => intval($this->get_scalar($r_entries, 'SmallBlind') ?? 0),
            'big_blind' => intval($this->get_scalar($r_entries, 'BigBlind') ?? 0),
            'minutes' => intval($this->get_scalar($r_entries, 'Minutes') ?? 0)
        );
    }

    /**
     * Extract prize distribution
     *
     * @param array $entries Tournament object entries
     * @return array Prizes array
     */
    private function extract_prizes($entries) {
        $prizes = array();

        if (!isset($entries['Prizes'])) {
            return $prizes;
        }

        $prizes_node = $entries['Prizes'];
        if (!$this->is_type($prizes_node, 'Array')) {
            return $prizes;
        }

        foreach ($prizes_node['items'] as $prize_item) {
            if ($this->is_new_with_ctor($prize_item, 'GamePrize')) {
                $prize = $this->extract_game_prize($prize_item);
                if ($prize) {
                    $prizes[] = $prize;
                }
            }
        }

        return $prizes;
    }

    /**
     * Extract GamePrize data
     *
     * @param array $prize_node GamePrize New node
     * @return array|null Prize data or null
     */
    private function extract_game_prize($prize_node) {
        $prize_obj = $this->expect_object($prize_node['arg']);
        $p_entries = $prize_obj['entries'];

        $prize = array(
            'description' => $this->get_scalar($p_entries, 'Description'),
            'position' => intval($this->get_scalar($p_entries, 'Recipient') ?? 0),
            'amount_type' => intval($this->get_scalar($p_entries, 'AmountType') ?? 0),
            'amount_percent' => intval($this->get_scalar($p_entries, 'Amount') ?? 0),
            'calculated_amount' => intval($this->get_scalar($p_entries, 'CalculatedAmount') ?? 0),
            'awarded_to' => array()
        );

        // Extract AwardedToPlayers array
        if (isset($p_entries['AwardedToPlayers'])) {
            $awarded_node = $p_entries['AwardedToPlayers'];
            if ($this->is_type($awarded_node, 'Array')) {
                foreach ($awarded_node['items'] as $uuid_item) {
                    if ($this->is_type($uuid_item, 'String')) {
                        $prize['awarded_to'][] = $uuid_item['value'];
                    }
                }
            }
        }

        return $prize;
    }

    // ====== Helper Methods ======

    /**
     * Check if node is New constructor with specific constructor name
     *
     * @param array $node AST node
     * @param string $ctor Constructor name to check
     * @return bool True if matches
     */
    private function is_new_with_ctor($node, $ctor) {
        return is_array($node) &&
               isset($node['_type']) &&
               $node['_type'] === 'New' &&
               isset($node['ctor']) &&
               $node['ctor'] === $ctor;
    }

    /**
     * Check if node is of specific type
     *
     * @param array $node AST node
     * @param string $type Type to check
     * @return bool True if matches
     */
    private function is_type($node, $type) {
        return is_array($node) && isset($node['_type']) && $node['_type'] === $type;
    }

    /**
     * Expect node to be Object type
     *
     * @param array $node AST node
     * @return array The object node
     * @throws Exception If not an Object
     */
    private function expect_object($node) {
        $this->assert_type($node, 'Object');
        return $node;
    }

    /**
     * Assert node is of specific type
     *
     * @param array $node AST node
     * @param string $type Expected type
     * @throws Exception If type doesn't match
     */
    private function assert_type($node, $type) {
        if (!is_array($node) || !isset($node['_type']) || $node['_type'] !== $type) {
            throw new Exception("Expected {$type} node");
        }
    }

    /**
     * Get scalar value from entries
     *
     * @param array $entries Object entries
     * @param string $key Key to lookup
     * @return mixed|null Scalar value or null
     */
    private function get_scalar($entries, $key) {
        if (!isset($entries[$key])) {
            return null;
        }

        $node = $entries[$key];
        $type = $node['_type'] ?? '';

        if ($type === 'String' || $type === 'Number' || $type === 'Boolean') {
            return $node['value'];
        }

        return null;
    }

    /**
     * Unwrap Call expressions to get the underlying value
     *
     * For example, Map.from(array) returns the array
     *
     * @param array $node AST node (might be Call expression)
     * @return array The unwrapped node
     */
    private function unwrap_call($node) {
        // If it's a Call expression, extract the argument
        if (is_array($node) && isset($node['_type']) && $node['_type'] === 'Call') {
            // For Map.from(array), return the array
            if ($node['object'] === 'Map' && $node['method'] === 'from') {
                return $node['arg'];
            }
            // For other method calls, return the argument (generic fallback)
            return $node['arg'];
        }

        // Not a Call expression, return as-is
        return $node;
    }
}
