<?php
/**
 * Chip-Up / Colour-Up automation (tdwp-ee1.13)
 *
 * Races off chip denominations that have become obsolete (smaller than the
 * current small blind) using ROUND-DOWN + RACE-REMAINDER: each stack is
 * rounded down to a multiple of the lowest still-useful denomination, and the
 * removed remainders form a pot of full chips redistributed by the
 * largest-remainder method (deterministic, fair, unit-testable).
 *
 * The live-stack mutation is opt-in: the automatic level-advance trigger only
 * fires when the `tdwp_auto_chipup` option is enabled. Operators can always
 * preview, and apply manually.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes and applies chip-up / race-off operations.
 */
class TDWP_Chip_Up {

	/**
	 * Register hooks. The auto-trigger is gated behind an opt-in option so it
	 * never mutates live stacks by default.
	 */
	public static function register() {
		if ( get_option( 'tdwp_auto_chipup', 0 ) ) {
			add_action( 'tdwp_tournament_level_advanced', array( __CLASS__, 'on_level_advanced' ), 10, 2 );
		}
	}

	/**
	 * Choose the denomination to round to when colouring up (pure; no DB).
	 *
	 * Returns the smallest denomination that is still useful (>= the small
	 * blind). Returns 0 when no race is needed — either there are no smaller
	 * denominations to remove, or the data is unusable.
	 *
	 * @param array $denominations Rows with a `value` key.
	 * @param int   $small_blind   Current small blind.
	 * @return int Target denomination, or 0 for "no race".
	 */
	public static function select_raceoff_denomination( $denominations, $small_blind ) {
		$small_blind = (int) $small_blind;

		$values = array();
		foreach ( (array) $denominations as $denomination ) {
			$value = isset( $denomination['value'] ) ? (int) $denomination['value'] : 0;
			if ( $value > 0 ) {
				$values[] = $value;
			}
		}

		if ( empty( $values ) ) {
			return 0;
		}

		sort( $values );

		// Nothing is obsolete yet — the smallest chip is still >= the blind.
		if ( $values[0] >= $small_blind ) {
			return 0;
		}

		// Smallest denomination that is still useful.
		foreach ( $values as $value ) {
			if ( $value >= $small_blind ) {
				return $value;
			}
		}

		// Every denomination is below the small blind: don't auto-race.
		return 0;
	}

	/**
	 * Compute a race-off (pure; no DB).
	 *
	 * Rounds each stack down to a multiple of $denom; the removed remainders
	 * become a pot of full $denom chips, redistributed one-at-a-time by the
	 * largest-remainder method (ties broken by player key ascending). Any value
	 * below one full chip is reported as `dropped` (standard chip-race loss).
	 *
	 * @param array $stacks Map of player key => chip count.
	 * @param int   $denom  Denomination to round to.
	 * @return array {
	 *     @type int   $denom      The denomination used.
	 *     @type array $new_stacks Map of player key => new chip count.
	 *     @type array $awards     Map of player key => chips awarded.
	 *     @type int   $pot_chips  Number of $denom chips redistributed.
	 *     @type int   $dropped    Chip value lost to rounding (< $denom).
	 * }
	 */
	public static function compute_race_off( $stacks, $denom ) {
		$denom  = (int) $denom;
		$result = array(
			'denom'      => $denom,
			'new_stacks' => array(),
			'awards'     => array(),
			'pot_chips'  => 0,
			'dropped'    => 0,
		);

		if ( $denom <= 0 ) {
			foreach ( $stacks as $key => $count ) {
				$result['new_stacks'][ $key ] = (int) $count;
			}
			return $result;
		}

		$remainders      = array();
		$total_remainder = 0;
		foreach ( $stacks as $key => $count ) {
			$count                        = (int) $count;
			$floored                      = intdiv( $count, $denom ) * $denom;
			$remainders[ $key ]           = $count - $floored;
			$total_remainder             += $remainders[ $key ];
			$result['new_stacks'][ $key ] = $floored;
		}

		$pot_chips           = intdiv( $total_remainder, $denom );
		$result['pot_chips'] = $pot_chips;
		$result['dropped']   = $total_remainder - ( $pot_chips * $denom );

		// Order players by remainder descending, then key ascending (stable).
		$order = array_keys( $remainders );
		usort(
			$order,
			static function ( $a, $b ) use ( $remainders ) {
				if ( $remainders[ $a ] === $remainders[ $b ] ) {
					return ( (string) $a ) <=> ( (string) $b );
				}
				return $remainders[ $b ] <=> $remainders[ $a ];
			}
		);

		$count_players = count( $order );
		$i             = 0;
		while ( $pot_chips > 0 && $count_players > 0 ) {
			$key                           = $order[ $i % $count_players ];
			$result['awards'][ $key ]      = isset( $result['awards'][ $key ] ) ? $result['awards'][ $key ] + 1 : 1;
			$result['new_stacks'][ $key ] += $denom;
			--$pot_chips;
			++$i;
		}

		return $result;
	}

	/**
	 * Get the denominations of the chipset linked to a tournament.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array Denomination rows (possibly empty).
	 */
	public static function get_tournament_denominations( $tournament_id ) {
		$chipset_id = (int) get_post_meta( $tournament_id, '_tdwp_chipset_id', true );
		if ( ! $chipset_id || ! class_exists( 'TDWP_Chipset_Manager' ) ) {
			return array();
		}
		return TDWP_Chipset_Manager::get_denominations( $chipset_id );
	}

	/**
	 * Resolve the current small blind for a live tournament.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return int Small blind, or 0 if unavailable.
	 */
	public static function get_current_small_blind( $tournament_id ) {
		global $wpdb;

		$state = class_exists( 'TDWP_Live_State_Manager' ) ? TDWP_Live_State_Manager::get_state( $tournament_id ) : null;
		if ( ! $state ) {
			return 0;
		}
		$schedule_id   = isset( $state->schedule_id ) ? (int) $state->schedule_id : 0;
		$current_level = isset( $state->current_level ) ? (int) $state->current_level : 0;
		if ( ! $schedule_id || ! $current_level ) {
			return 0;
		}

		$small_blind = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT small_blind FROM {$wpdb->prefix}tdwp_blind_levels WHERE schedule_id = %d AND level_order = %d",
				$schedule_id,
				$current_level
			)
		);

		return null === $small_blind ? 0 : (int) $small_blind;
	}

	/**
	 * Read active player stacks for a tournament.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array Map of tournament-player row ID => chip count.
	 */
	private static function get_active_stacks( $tournament_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, chip_count FROM {$wpdb->prefix}tdwp_tournament_players
				 WHERE tournament_id = %d AND status = 'active'",
				$tournament_id
			)
		);
		$stacks = array();
		foreach ( (array) $rows as $row ) {
			$stacks[ (int) $row->id ] = (int) $row->chip_count;
		}
		return $stacks;
	}

	/**
	 * Preview the chip-up that would occur for a tournament right now.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array|WP_Error Preview (denom, new_stacks, awards, pot, dropped) or error.
	 */
	public static function preview( $tournament_id ) {
		$tournament_id = absint( $tournament_id );
		$denoms        = self::get_tournament_denominations( $tournament_id );
		if ( empty( $denoms ) ) {
			return new WP_Error( 'no_chipset', __( 'No chipset is linked to this tournament', 'poker-tournament-import' ) );
		}

		$small_blind = self::get_current_small_blind( $tournament_id );
		$target      = self::select_raceoff_denomination( $denoms, $small_blind );
		if ( ! $target ) {
			return new WP_Error( 'no_raceoff', __( 'No denomination needs racing off at the current level', 'poker-tournament-import' ) );
		}

		$stacks  = self::get_active_stacks( $tournament_id );
		$compute = self::compute_race_off( $stacks, $target );

		$compute['small_blind'] = $small_blind;
		return $compute;
	}

	/**
	 * Apply a chip-up to the live stacks (guarded, audited).
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array|WP_Error Result summary or error.
	 */
	public static function apply( $tournament_id ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$preview       = self::preview( $tournament_id );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$player_table = $wpdb->prefix . 'tdwp_tournament_players';
		$updated      = 0;
		foreach ( $preview['new_stacks'] as $row_id => $new_count ) {
			$wpdb->update(
				$player_table,
				array(
					'chip_count' => (int) $new_count,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			++$updated;
		}

		if ( class_exists( 'TDWP_Tournament_Events' ) ) {
			$events = new TDWP_Tournament_Events();
			$events->log(
				$tournament_id,
				'chip_up',
				array(
					'denom'     => $preview['denom'],
					'pot_chips' => $preview['pot_chips'],
					'dropped'   => $preview['dropped'],
				),
				true
			);
		}

		/**
		 * Fires after a chip-up is applied to a tournament.
		 *
		 * @param int   $tournament_id Tournament ID.
		 * @param array $preview       The applied race-off computation.
		 */
		do_action( 'tdwp_chip_up_applied', $tournament_id, $preview );

		return array(
			'success'   => true,
			'denom'     => $preview['denom'],
			'updated'   => $updated,
			'pot_chips' => $preview['pot_chips'],
			'dropped'   => $preview['dropped'],
			'message'   => sprintf(
				/* translators: %d: denomination raced to */
				__( 'Chipped up to %d; stacks rounded and remainders raced off', 'poker-tournament-import' ),
				$preview['denom']
			),
		);
	}

	/**
	 * Auto-trigger on level advance (only registered when opted in).
	 *
	 * @param int $tournament_id Tournament ID.
	 * @param int $new_level     The level just advanced to.
	 */
	public static function on_level_advanced( $tournament_id, $new_level ) {
		$result = self::apply( $tournament_id );
		// A "no race needed" outcome is normal at most levels; ignore errors.
		unset( $result );
	}
}
