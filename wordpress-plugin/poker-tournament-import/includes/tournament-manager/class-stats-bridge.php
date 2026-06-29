<?php
/**
 * Stats Mart Bridge
 *
 * Projects a finished LIVE tournament (TD3 tournament manager, stored in
 * tdwp_tournament_players, keyed by WordPress post IDs) into the legacy
 * statistics data-mart (poker_tournament_players, keyed by UUID strings) so the
 * existing statistics / leaderboard engine includes live-run tournaments.
 *
 * This is the "Option A" bridge (see beads tdwp-iwc). Design goals:
 *  - ADDITIVE: a pure listener on the existing `tdwp_tournament_finished` action;
 *    it does NOT modify the import path, the live-state flow, or the stats engine.
 *  - SAFE: every external table / class is guarded so it can never fatal a finish.
 *  - IDEMPOTENT: re-finishing a tournament replaces its projection, never duplicates.
 *  - FAITHFUL: one legacy row per player (re-entries aggregated) to match the
 *    legacy import semantics the stats engine expects.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.6.6
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges live tournament results into the legacy stats data-mart.
 *
 * @since 3.6.6
 */
class TDWP_Stats_Bridge {

	/**
	 * Action fired by TDWP_Live_State_Manager::finish() (manual "End tournament").
	 *
	 * @var string
	 */
	const FINISH_HOOK = 'tdwp_tournament_finished';

	/**
	 * Action fired by TDWP_Player_Operations when the last player is eliminated
	 * (the natural end of a tournament). This path does NOT call finish(), so the
	 * bridge must listen here too or auto-completed tournaments would be missed.
	 *
	 * @var string
	 */
	const COMPLETE_HOOK = 'tdwp_tournament_completed';

	/**
	 * Cron hook used to refresh the legacy stats mart after projection.
	 *
	 * @var string
	 */
	const REFRESH_HOOK = 'tdwp_stats_bridge_refresh';

	/**
	 * Register hooks.
	 *
	 * @since 3.6.6
	 */
	public static function init() {
		// Priority 20 so any other finish listeners (e.g. events) run first.
		// Both completion paths are covered; the projection is idempotent, so if
		// both fire for one tournament there is no duplication.
		add_action( self::FINISH_HOOK, array( __CLASS__, 'project_to_stats_mart' ), 20, 1 );
		add_action( self::COMPLETE_HOOK, array( __CLASS__, 'project_to_stats_mart' ), 20, 1 );
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh_stats_mart' ) );
	}

	/**
	 * Project a finished live tournament into poker_tournament_players.
	 *
	 * @since 3.6.6
	 * @param int $tournament_id Tournament post ID.
	 * @return void
	 */
	public static function project_to_stats_mart( $tournament_id ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return;
		}

		$live_table   = $wpdb->prefix . 'tdwp_tournament_players';
		$legacy_table = $wpdb->prefix . 'poker_tournament_players';

		// Guard: both tables must exist (partial installs / activation order).
		if ( ! self::table_exists( $live_table ) || ! self::table_exists( $legacy_table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_id, finish_position, prize_amount, entry_number
				 FROM {$live_table}
				 WHERE tournament_id = %d",
				$tournament_id
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		// Aggregate per player so re-entries collapse to a single legacy row,
		// matching the legacy import's one-row-per-player model.
		$players     = array();
		$total_money = 0.0;
		foreach ( $rows as $row ) {
			$pid = (int) $row->player_id;
			if ( ! $pid ) {
				continue;
			}

			$finish   = is_null( $row->finish_position ) ? 0 : (int) $row->finish_position;
			$winnings = (float) $row->prize_amount;
			$total_money += $winnings;

			if ( ! isset( $players[ $pid ] ) ) {
				$players[ $pid ] = array(
					'finish'   => $finish,
					'winnings' => $winnings,
					'buyins'   => 1,
				);
			} else {
				// Best (lowest, non-zero) finish wins; sum winnings; count entries as buy-ins.
				if ( $finish > 0 && ( 0 === $players[ $pid ]['finish'] || $finish < $players[ $pid ]['finish'] ) ) {
					$players[ $pid ]['finish'] = $finish;
				}
				$players[ $pid ]['winnings'] += $winnings;
				$players[ $pid ]['buyins']   += 1;
			}
		}

		if ( empty( $players ) ) {
			return;
		}

		$entrant_count  = count( $players );
		$buyin          = (float) get_post_meta( $tournament_id, '_buy_in', true );
		$points_formula = self::get_points_formula();

		// Legacy join key: the stats engine joins tournament_id -> postmeta 'tournament_uuid'.
		$tournament_uuid = self::get_or_create_meta_uuid( $tournament_id, 'tournament_uuid' );

		// Idempotency: drop any prior projection for this tournament's UUID before
		// re-inserting. The UUID is unique to this live tournament, so every row
		// under it is bridge-created — safe to delete.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent re-projection.
		$wpdb->delete( $legacy_table, array( 'tournament_id' => $tournament_uuid ), array( '%s' ) );

		$inserted = 0;
		foreach ( $players as $player_post_id => $agg ) {
			// Legacy join key: player_id -> postmeta 'player_uuid'. Reusing an
			// existing player_uuid is what unifies imported + live stats for the
			// same player post.
			$player_uuid = self::get_or_create_meta_uuid( $player_post_id, 'player_uuid' );
			if ( '' === $player_uuid ) {
				continue;
			}

			$points = self::compute_points(
				$points_formula,
				array(
					'finish_position' => $agg['finish'],
					'total_players'   => $entrant_count,
					'total_money'     => $total_money,
					'winnings'        => $agg['winnings'],
					'buyin_amount'    => $buyin,
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$result = $wpdb->insert(
				$legacy_table,
				array(
					'tournament_id'   => $tournament_uuid,
					'player_id'       => $player_uuid,
					'finish_position' => (int) $agg['finish'],
					'winnings'        => (float) $agg['winnings'],
					'buyins'          => (int) $agg['buyins'],
					'rebuys'          => 0,
					'addons'          => 0,
					'hits'            => 0,
					'points'          => (float) $points,
				),
				array( '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%f' )
			);

			if ( false !== $result ) {
				$inserted++;
			}
		}

		if ( class_exists( 'TDWP_Debug_Logger' ) ) {
			TDWP_Debug_Logger::log(
				'StatsBridge',
				'Projected live tournament into stats mart',
				array(
					'tournament_id'   => $tournament_id,
					'tournament_uuid' => $tournament_uuid,
					'players'         => $entrant_count,
					'inserted'        => $inserted,
				)
			);
		}

		// Refresh the legacy data-mart asynchronously (matches the plugin's async
		// stats pattern; avoids blocking the finish request).
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::REFRESH_HOOK );
		}
	}

	/**
	 * Recompute the legacy statistics data-mart.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public static function refresh_stats_mart() {
		if ( class_exists( 'Poker_Statistics_Engine' ) ) {
			Poker_Statistics_Engine::get_instance()->calculate_all_statistics();
		}
	}

	/**
	 * Get the active tournament points formula string, or '' if none.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	private static function get_points_formula() {
		if ( ! class_exists( 'Poker_Active_Formula_Manager' ) ) {
			return '';
		}

		$manager     = new Poker_Active_Formula_Manager();
		$formula_key = $manager->get_active_formula( 'tournament' );
		if ( empty( $formula_key ) ) {
			return '';
		}

		$formulas = get_option( 'tdwp_tournament_formulas', array() );
		if ( isset( $formulas[ $formula_key ]['formula'] ) ) {
			return (string) $formulas[ $formula_key ]['formula'];
		}

		return '';
	}

	/**
	 * Compute points via the active formula. Best-effort and fully guarded —
	 * never lets a formula error break the projection (falls back to 0).
	 *
	 * @since 3.6.6
	 * @param string $formula Formula expression.
	 * @param array  $data    Formula input variables.
	 * @return float
	 */
	private static function compute_points( $formula, $data ) {
		if ( '' === $formula || ! class_exists( 'Poker_Tournament_Formula_Validator' ) ) {
			return 0.0;
		}

		try {
			$validator = new Poker_Tournament_Formula_Validator();
			$res       = $validator->calculate_formula( $formula, $data, 'tournament' );
			if ( is_array( $res ) && ! empty( $res['success'] ) && isset( $res['result'] ) && is_numeric( $res['result'] ) ) {
				return (float) $res['result'];
			}
		} catch ( \Throwable $e ) {
			// Degrade safely; points default to 0 for this projection.
			if ( class_exists( 'TDWP_Debug_Logger' ) ) {
				TDWP_Debug_Logger::log( 'StatsBridge', 'Points formula failed; defaulting to 0', array( 'error' => $e->getMessage() ) );
			}
		}

		return 0.0;
	}

	/**
	 * Return an existing post-meta UUID, or generate, store, and return a new one.
	 *
	 * Reusing an existing UUID is intentional: it keeps live and imported records
	 * for the same post unified in the stats engine.
	 *
	 * @since 3.6.6
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key ('tournament_uuid' or 'player_uuid').
	 * @return string UUID, or '' if post_id is invalid.
	 */
	private static function get_or_create_meta_uuid( $post_id, $meta_key ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return '';
		}

		$uuid = get_post_meta( $post_id, $meta_key, true );
		if ( ! empty( $uuid ) ) {
			return (string) $uuid;
		}

		$uuid = 'live-' . wp_generate_uuid4();
		update_post_meta( $post_id, $meta_key, $uuid );

		return $uuid;
	}

	/**
	 * Whether a database table exists.
	 *
	 * @since 3.6.6
	 * @param string $table Fully-qualified table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema guard.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
