<?php
/**
 * TDT File Parser Class
 *
 * Parses Tournament Director (.tdt) files and extracts tournament data
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
     * Constructor
     */
    public function __construct($file_path = null) {
        if ($file_path) {
            $this->file_path = $file_path;
        }
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
     * Extract tournament data from raw content
     */
    private function extract_tournament_data($content) {
        $data = array();

        // Extract tournament metadata
        $data['metadata'] = $this->extract_metadata($content);

        // Extract players data
        $data['players'] = $this->extract_players($content);

        // Extract financial data
        $data['financial'] = $this->extract_financial_data($content);

        // Extract tournament structure
        $data['structure'] = $this->extract_structure($content);

        // Extract prize distribution
        $data['prizes'] = $this->extract_prize_distribution($content);

        // Calculate rankings and winnings
        $data['players'] = $this->calculate_player_rankings($data['players']);

        // Calculate Tournament Director points
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
            $metadata['start_time'] = date('Y-m-d H:i:s', $matches[1] / 1000);
        }

        return $metadata;
    }

    /**
     * Extract players data
     */
    private function extract_players($content) {
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

            // Extract Buyins
            $player['buyins'] = $this->extract_buyins($player_data);

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
     * Extract player buyins with bust-out information
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

        // Extract buy-in amounts
        preg_match_all('/new FeeProfile\(\{[^}]*Name:\s*"([^"]+)"[^}]*Fee:\s*(\d+)/', $content, $matches);

        if (!empty($matches[1]) && !empty($matches[2])) {
            foreach ($matches[1] as $i => $name) {
                $financial['fee_profiles'][$name] = array(
                    'name' => $name,
                    'fee' => intval($matches[2][$i])
                );
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
     * Calculate player rankings based on bust-out data
     */
    private function calculate_player_rankings($players) {
        // Sort players by bust-out time and round
        uasort($players, function($a, $b) {
            // Extract bust-out times
            $a_time = $this->extract_bust_out_time($a);
            $b_time = $this->extract_bust_out_time($b);

            if ($a_time === null && $b_time === null) return 0;
            if ($a_time === null) return -1; // No bust-out = winner
            if ($b_time === null) return 1;

            return $a_time - $b_time;
        });

        // Assign finish positions
        $position = 1;
        foreach ($players as $uuid => $player) {
            $players[$uuid]['finish_position'] = $position++;
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
     * Calculate Tournament Director points for all players
     */
    private function calculate_tournament_points($players, $financial) {
        $total_players = count($players);

        // Calculate total money in tournament
        $total_money = 0;
        $total_buyins = 0;

        foreach ($players as $player) {
            $player_total = 0;
            foreach ($player['buyins'] as $buyin) {
                $player_total += $buyin['amount'];
            }
            $total_money += $player_total;
            $total_buyins += count($player['buyins']);
        }

        $avg_buyin = $total_buyins > 0 ? $total_money / $total_buyins : 0;

        foreach ($players as $uuid => $player) {
            $r = $player['finish_position'];
            $n = $total_players;
            $numberofHits = $player['hits'];

            // Tournament Director points formula
            // assign("points", 1)
            // assign("T33", Round(n/3))
            // assign("T80", Floor(n*0.9))
            // assign("monies", totalBuyInsAmount + totalRebuysAmount + totalAddOnsAmount)
            // assign("avgBC", monies/buyins)
            // assign("temp", 10*(sqrt(n)/sqrt( (T33 + 1) ))*(1+log(avgBC+0.25))+ (numberofHits * 10))
            // IF (T80 > r and T33 < r, assign("points", round(temp * pow(0.66, (r-T33))) +(numberofHits * 10) ), (IF (T33 >= r, assign (\"points\", (round(10*(sqrt(n)/sqrt(r))*(1+log(avgBC+0.25)))+(numberofHits * 10))))))

            $T33 = round($n / 3);
            $T80 = floor($n * 0.9);

            $points = 1; // Base points

            if ($T80 > $r && $T33 < $r) {
                // Middle positions with decay
                $temp = 10 * (sqrt($n) / sqrt($T33 + 1)) * (1 + log($avg_buyin + 0.25)) + ($numberofHits * 10);
                $points = round($temp * pow(0.66, ($r - $T33))) + ($numberofHits * 10);
            } elseif ($T33 >= $r) {
                // Top positions
                $points = round(10 * (sqrt($n) / sqrt($r)) * (1 + log($avg_buyin + 0.25))) + ($numberofHits * 10);
            }

            $players[$uuid]['points'] = $points;
            $players[$uuid]['points_calculation'] = array(
                'n' => $n,
                'r' => $r,
                'T33' => $T33,
                'T80' => $T80,
                'avg_buyin' => $avg_buyin,
                'hits' => $numberofHits,
                'final_points' => $points
            );
        }

        return $players;
    }

    /**
     * Calculate winnings by rank and prize distribution
     */
    private function calculate_winnings_by_rank($players, $prizes) {
        foreach ($prizes as $prize) {
            if (isset($prize['position']) && $prize['calculated_amount'] > 0) {
                $position = $prize['position'];

                // Find player at this position
                foreach ($players as $uuid => $player) {
                    if ($player['finish_position'] === $position) {
                        $players[$uuid]['winnings'] = $prize['calculated_amount'];
                        break;
                    }
                }
            }
        }

        return $players;
    }

    /**
     * Validate parsed data
     */
    public function validate_data() {
        $errors = array();

        if (empty($this->tournament_data['metadata']['uuid'])) {
            $errors[] = 'Tournament UUID is missing';
        }

        if (empty($this->tournament_data['metadata']['title'])) {
            $errors[] = 'Tournament title is missing';
        }

        if (empty($this->tournament_data['players'])) {
            $errors[] = 'No player data found';
        }

        // Additional validation for points calculation
        $players_with_points = array_filter($this->tournament_data['players'], function($player) {
            return isset($player['points']) && $player['points'] > 0;
        });

        if (empty($players_with_points)) {
            $errors[] = 'No player points calculated';
        }

        return $errors;
    }
}