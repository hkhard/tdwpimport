<?php
/**
 * DEPRECATED Legacy Ranking Methods
 *
 * v2.8.0: These methods represent the old chronological state tracking approach
 * that didn't properly handle rebuys and assumed all players appear in GameHistory.
 *
 * @deprecated Will be removed in v2.9.0 after v2.8.0 is verified in production
 * @package Poker_Tournament_Import
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Legacy ranking methods preserved for reference
 * DO NOT USE - kept only for rollback capability
 *
 * @deprecated v2.8.0
 */
trait Poker_Tournament_Parser_Legacy_Ranking {

    /**
     * Process GameHistory chronologically to determine tournament progression and rankings
     *
     * @deprecated v2.8.0 Use calculate_player_rankings() instead
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

        Poker_Tournament_Import_Debug::log_success("Found tournament end at: " . gmdate('Y-m-d H:i:s', intval($tournament_end_time/1000)));

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
     *
     * @deprecated v2.8.0
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
     *
     * @deprecated v2.8.0
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
     *
     * @deprecated v2.8.0
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
     *
     * @deprecated v2.8.0
     */
    private function extract_winner_from_declaration($text) {
        if (preg_match('/^(.+?)\s+won the tournament$/i', trim($text), $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Parse elimination event text to extract eliminated and eliminator
     *
     * @deprecated v2.8.0
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
     *
     * @deprecated v2.8.0
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
}
