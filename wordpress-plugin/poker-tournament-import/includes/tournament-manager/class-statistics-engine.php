<?php
/**
 * Statistics Engine for Tournament Manager
 *
 * Calculates live tournament statistics with transient caching:
 * - Chip leader
 * - Average stack
 * - Biggest/shortest stacks
 * - Bubble position
 * - Prize pool
 * - Time elapsed
 *
 * Uses 15-second transient cache for performance
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.2.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statistics Engine class
 *
 * @since 3.2.0
 */
class TDWP_Statistics_Engine {

	/**
	 * Cache TTL in seconds (15 seconds)
	 *
	 * @var int
	 */
	const CACHE_TTL = 15;

	/**
	 * Calculate live tournament statistics
	 *
	 * Returns comprehensive statistics with 15-second cache
	 *
	 * @since 3.2.0
	 * @param int $tournament_id Tournament ID.
	 * @return array|false Statistics array or false on error
	 */
	public static function calculate_live_stats( $tournament_id ) {
		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return false;
		}

		// Try to get from cache first
		$cache_key = self::get_cache_key( $tournament_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		// Calculate fresh statistics
		global $wpdb;

		// Get tournament live state
		$live_state_table = $wpdb->prefix . 'tdwp_tournament_live_state';
		$live_state       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$live_state_table} WHERE tournament_id = %d", $tournament_id )
		);

		if ( ! $live_state ) {
			return false;
		}

		// Get player statistics
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';
		$player_stats  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_players,
					SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_players,
					SUM(CASE WHEN status = 'busted' THEN 1 ELSE 0 END) as busted_players,
					MAX(chip_count) as biggest_stack,
					MIN(CASE WHEN status = 'active' THEN chip_count END) as shortest_stack,
					AVG(CASE WHEN status = 'active' THEN chip_count END) as average_stack,
					SUM(CASE WHEN status = 'active' THEN chip_count ELSE 0 END) as total_chips
				FROM {$players_table}
				WHERE tournament_id = %d",
				$tournament_id
			)
		);

		// Get chip leader
		$chip_leader = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.id, p.player_id, p.chip_count, pm.display_name
				FROM {$players_table} p
				LEFT JOIN {$wpdb->prefix}tdwp_players pm ON p.player_id = pm.id
				WHERE p.tournament_id = %d
				  AND p.status = 'active'
				ORDER BY p.chip_count DESC
				LIMIT 1",
				$tournament_id
			)
		);

		// Calculate the big-blind equivalent for the chip leader using the real
		// big blind for the current level from the tournament's blind schedule.
		$bb_equivalent = 0;
		if ( $chip_leader && $live_state->current_level > 0 ) {
			$current_bb    = self::get_current_big_blind(
				isset( $live_state->template_id ) ? (int) $live_state->template_id : 0,
				(int) $live_state->current_level
			);
			$bb_equivalent = self::bb_equivalent( (int) $chip_leader->chip_count, $current_bb );
		}

		// Calculate bubble position (next payout position - 1)
		$bubble_position = null;
		if ( $live_state->next_payout_position && $live_state->next_payout_position > 0 ) {
			$bubble_position = $live_state->next_payout_position - 1;
		}

		// Calculate time elapsed
		$time_elapsed = '';
		if ( $live_state->started_at ) {
			$start_time   = strtotime( $live_state->started_at );
			$current_time = current_time( 'timestamp' );
			$elapsed_secs = $current_time - $start_time;
			$hours        = floor( $elapsed_secs / 3600 );
			$minutes      = floor( ( $elapsed_secs % 3600 ) / 60 );
			$seconds      = $elapsed_secs % 60;
			$time_elapsed = sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
		}

		// Build statistics array
		$stats = array(
			'chip_leader'      => $chip_leader ? array(
				'name'          => $chip_leader->display_name,
				'chips'         => intval( $chip_leader->chip_count ),
				'bb_equivalent' => $bb_equivalent,
			) : null,
			'average_stack'    => $player_stats->average_stack ? round( floatval( $player_stats->average_stack ) ) : 0,
			'biggest_stack'    => intval( $player_stats->biggest_stack ),
			'shortest_stack'   => intval( $player_stats->shortest_stack ),
			'bubble_position'  => $bubble_position,
			'total_prize_pool' => floatval( $live_state->prize_pool ),
			'time_elapsed'     => $time_elapsed,
			'remaining_players' => intval( $live_state->remaining_players ),
			'total_players'    => intval( $player_stats->total_players ),
			'busted_players'   => intval( $player_stats->busted_players ),
			'total_chips'      => intval( $player_stats->total_chips ),
			'cache_age'        => 0,
			'from_cache'       => false,
		);

		// Cache for 15 seconds
		set_transient( $cache_key, $stats, self::CACHE_TTL );

		return $stats;
	}

	/**
	 * Invalidate statistics cache
	 *
	 * Called after player operations to force fresh calculation
	 *
	 * @since 3.2.0
	 * @param int $tournament_id Tournament ID.
	 * @return bool True on success
	 */
	public static function invalidate_stats_cache( $tournament_id ) {
		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return false;
		}

		$cache_key = self::get_cache_key( $tournament_id );
		return delete_transient( $cache_key );
	}

	/**
	 * Get cache key for tournament
	 *
	 * @since 3.2.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Cache key
	 */
	private static function get_cache_key( $tournament_id ) {
		return sprintf( 'tdwp_live_stats_%d', absint( $tournament_id ) );
	}

	/**
	 * Big-blind equivalent of a stack (pure).
	 *
	 * @param int $chips      Chip count.
	 * @param int $big_blind  Current big blind (0 if unknown).
	 * @return float Stack in big blinds, or 0 when the big blind is unknown.
	 */
	public static function bb_equivalent( $chips, $big_blind ) {
		$big_blind = (int) $big_blind;
		return $big_blind > 0 ? round( (int) $chips / $big_blind, 1 ) : 0.0;
	}

	/**
	 * Resolve the big blind for a tournament's current level.
	 *
	 * Follows live_state.template_id -> template.blind_schedule_id ->
	 * tdwp_blind_levels(level_order).
	 *
	 * @param int $template_id  Template ID from the live state.
	 * @param int $current_level Current level number.
	 * @return int Big blind, or 0 if it cannot be resolved.
	 */
	private static function get_current_big_blind( $template_id, $current_level ) {
		global $wpdb;

		$template_id   = absint( $template_id );
		$current_level = absint( $current_level );
		if ( ! $template_id || ! $current_level ) {
			return 0;
		}

		$big_blind = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT bl.big_blind
				 FROM {$wpdb->prefix}tdwp_tournament_templates t
				 INNER JOIN {$wpdb->prefix}tdwp_blind_levels bl ON bl.schedule_id = t.blind_schedule_id
				 WHERE t.id = %d AND bl.level_order = %d
				 LIMIT 1",
				$template_id,
				$current_level
			)
		);

		return null === $big_blind ? 0 : (int) $big_blind;
	}
}
