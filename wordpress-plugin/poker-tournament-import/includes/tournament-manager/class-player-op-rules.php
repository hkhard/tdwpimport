<?php
/**
 * Player-operation rule guards (tdwp-3lg.9 / .10 / .11)
 *
 * Pure eligibility checks for rebuys, add-ons, and late registration. These
 * live in one place so the live AJAX path (TDWP_Player_Operations) enforces the
 * same rules the tournament config declares — previously it enforced none.
 *
 * All methods are pure (no DB); callers pass the already-fetched state and
 * config so the logic is fully unit-testable.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eligibility guards for rebuys / add-ons / late registration.
 */
class TDWP_Player_Op_Rules {

	/**
	 * Whether a player may rebuy (pure).
	 *
	 * @param int $rebuys_count   Player's current rebuy count.
	 * @param int $chip_count     Player's current chip count.
	 * @param int $until_level    Last level rebuys are allowed (0 = not allowed).
	 * @param int $limit          Max rebuys per player (0 = unlimited).
	 * @param int $chip_threshold Rebuy only allowed at/below this stack (0 = no limit).
	 * @param int $current_level  Current tournament level.
	 * @return true|WP_Error True if eligible, WP_Error otherwise.
	 */
	public static function can_rebuy( $rebuys_count, $chip_count, $until_level, $limit, $chip_threshold, $current_level ) {
		if ( (int) $until_level <= 0 ) {
			return new WP_Error( 'rebuy_not_allowed', __( 'Rebuys are not allowed for this tournament', 'poker-tournament-import' ) );
		}
		if ( (int) $current_level > (int) $until_level ) {
			return new WP_Error( 'rebuy_period_ended', __( 'Rebuy period has ended', 'poker-tournament-import' ) );
		}
		if ( (int) $limit > 0 && (int) $rebuys_count >= (int) $limit ) {
			return new WP_Error( 'rebuy_limit_reached', __( 'Rebuy limit reached', 'poker-tournament-import' ) );
		}
		if ( (int) $chip_threshold > 0 && (int) $chip_count > (int) $chip_threshold ) {
			return new WP_Error( 'rebuy_stack_too_large', __( 'Stack is above the rebuy threshold', 'poker-tournament-import' ) );
		}
		return true;
	}

	/**
	 * Whether a player may take an add-on (pure).
	 *
	 * @param int $addons_count  Player's current add-on count.
	 * @param int $at_level      First level add-ons are available (0 = not allowed).
	 * @param int $until_level   Last level add-ons are allowed (0 = only at_level onward).
	 * @param int $max_per_player Max add-ons per player (default 1).
	 * @param int $current_level Current tournament level.
	 * @return true|WP_Error
	 */
	public static function can_add_on( $addons_count, $at_level, $until_level, $max_per_player, $current_level ) {
		$max_per_player = (int) $max_per_player > 0 ? (int) $max_per_player : 1;

		if ( (int) $at_level <= 0 ) {
			return new WP_Error( 'addon_not_allowed', __( 'Add-ons are not allowed for this tournament', 'poker-tournament-import' ) );
		}
		if ( (int) $current_level < (int) $at_level ) {
			return new WP_Error( 'addon_not_yet', __( 'Add-on not yet available', 'poker-tournament-import' ) );
		}
		if ( (int) $until_level > 0 && (int) $current_level > (int) $until_level ) {
			return new WP_Error( 'addon_period_ended', __( 'Add-on period has ended', 'poker-tournament-import' ) );
		}
		if ( (int) $addons_count >= $max_per_player ) {
			return new WP_Error( 'addon_limit_reached', __( 'Add-on limit reached', 'poker-tournament-import' ) );
		}
		return true;
	}

	/**
	 * Whether a player may still register (pure).
	 *
	 * Registration is open before the tournament starts (no live state) and
	 * while running up to the late-registration deadline. It is closed once the
	 * tournament is in a terminal state or the deadline level has passed.
	 *
	 * @param int         $late_reg_until_level Last level registration is open (0 = no explicit deadline).
	 * @param int         $current_level        Current level (0 if not started).
	 * @param string|null $status               Live-state status (null if not started).
	 * @return true|WP_Error
	 */
	public static function can_register_late( $late_reg_until_level, $current_level, $status ) {
		$closed_states = array( 'completed', 'finished', 'complete', 'cancelled', 'canceled', 'ended' );
		if ( null !== $status && in_array( strtolower( (string) $status ), $closed_states, true ) ) {
			return new WP_Error( 'registration_closed', __( 'Registration is closed; the tournament has ended', 'poker-tournament-import' ) );
		}
		if ( (int) $late_reg_until_level > 0 && (int) $current_level > (int) $late_reg_until_level ) {
			return new WP_Error( 'late_reg_ended', __( 'Late registration has closed', 'poker-tournament-import' ) );
		}
		return true;
	}
}
