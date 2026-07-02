<?php
/**
 * TDWP Stats Rollup — single derived-mart writer (tdwp-eil Phase D).
 *
 * The long-term successor to TDWP_Stats_Bridge's projection. Where the bridge maps
 * live post IDs to UUIDs at projection time, the rollup reads the canonical per-entry
 * source (tdwp_tournament_players) using its STORED tournament_uuid/player_uuid columns
 * (added in Phase C) and aggregates one derived row per (tournament_uuid, player_uuid)
 * into the legacy marts (poker_tournament_players + poker_player_roi).
 *
 * Grain reconciliation: re-entries collapse to one aggregated row (buyins = entry count,
 * winnings = SUM(prize_amount), best finish = MIN(non-zero finish_position)). Imported
 * tournaments are represented as one synthetic entry per player (entry_number=1,
 * source='import'), so the rollup is a lossless 1:1 pass-through for them.
 *
 * ROI invested uses the stored per-entry paid_amount (decision #3 — no $20 fallback).
 * Manual points overrides in tdwp_points_adjustments are re-applied so a rebuild never
 * silently discards them (decision #4).
 *
 * Gated OFF by default via the tdwp_eil_rollup_enabled option: rebuild_tournament() is a
 * no-op until cutover. reconcile_report() is always safe (read-only) and drives the
 * Phase-D shadow diff.
 *
 * @package Poker_Tournament_Import
 * @since 3.6.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDWP_Stats_Rollup {

	/**
	 * Option gating whether rebuild_tournament() actually writes. OFF until cutover.
	 */
	const ENABLED_OPTION = 'tdwp_eil_rollup_enabled';

	/**
	 * Whether the rollup is the active mart writer.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::ENABLED_OPTION, false );
	}

	/**
	 * Aggregate the canonical per-entry source into derived per-player rows (READ-ONLY).
	 *
	 * @param string $tournament_uuid Tournament UUID join key.
	 * @return array{rows: array<string,array>, total_money: float} Per-player-uuid aggregates.
	 */
	public static function aggregate_from_source( $tournament_uuid ) {
		global $wpdb;

		$out = array(
			'rows'        => array(),
			'total_money' => 0.0,
		);

		$tournament_uuid = (string) $tournament_uuid;
		if ( '' === $tournament_uuid ) {
			return $out;
		}

		$source = $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $source ) ) {
			return $out;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_uuid, finish_position, prize_amount, paid_amount
				 FROM {$source}
				 WHERE tournament_uuid = %s AND player_uuid <> ''",
				$tournament_uuid
			)
		);

		foreach ( (array) $entries as $e ) {
			$puuid = (string) $e->player_uuid;
			if ( '' === $puuid ) {
				continue;
			}

			$finish   = is_null( $e->finish_position ) ? 0 : (int) $e->finish_position;
			$winnings = (float) $e->prize_amount;
			$invested = (float) $e->paid_amount;
			$out['total_money'] += $winnings;

			if ( ! isset( $out['rows'][ $puuid ] ) ) {
				$out['rows'][ $puuid ] = array(
					'finish'         => $finish,
					'winnings'       => $winnings,
					'buyins'         => 1,
					'total_invested' => $invested,
				);
				continue;
			}

			// Best (lowest, non-zero) finish wins; sum winnings + invested; count entries as buy-ins.
			if ( $finish > 0 && ( 0 === $out['rows'][ $puuid ]['finish'] || $finish < $out['rows'][ $puuid ]['finish'] ) ) {
				$out['rows'][ $puuid ]['finish'] = $finish;
			}
			$out['rows'][ $puuid ]['winnings']       += $winnings;
			$out['rows'][ $puuid ]['buyins']         += 1;
			$out['rows'][ $puuid ]['total_invested'] += $invested;
		}

		return $out;
	}

	/**
	 * Compute the full derived mart + ROI rows for a tournament, points included (READ-ONLY).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return array{mart: array<int,array>, roi: array<int,array>} Ready-to-write rows.
	 */
	public static function compute_rows( $tournament_uuid ) {
		$agg = self::aggregate_from_source( $tournament_uuid );
		$result = array(
			'mart' => array(),
			'roi'  => array(),
		);
		if ( empty( $agg['rows'] ) ) {
			return $result;
		}

		$entrant_count  = count( $agg['rows'] );
		$total_money    = (float) $agg['total_money'];
		$points_formula = self::get_points_formula();
		$overrides      = self::get_override_map( $tournament_uuid );
		$tournament_date = self::get_tournament_date( $tournament_uuid );

		foreach ( $agg['rows'] as $player_uuid => $row ) {
			$finish   = (int) $row['finish'];
			$winnings = (float) $row['winnings'];
			$buyins   = (int) $row['buyins'];
			$invested = (float) $row['total_invested'];

			// Points: active formula, then apply a manual override if one exists (decision #4).
			$points = self::compute_points(
				$points_formula,
				array(
					'finish_position' => $finish,
					'total_players'   => $entrant_count,
					'total_money'     => $total_money,
					'winnings'        => $winnings,
					'buyin_amount'    => $entrant_count > 0 ? ( $invested / max( 1, $buyins ) ) : 0,
				)
			);
			$override_key = $tournament_uuid . '|' . $player_uuid;
			if ( isset( $overrides[ $override_key ] ) ) {
				$points = (float) $overrides[ $override_key ];
			}

			$result['mart'][] = array(
				'tournament_id'   => $tournament_uuid,
				'player_id'       => $player_uuid,
				'finish_position' => $finish,
				'winnings'        => $winnings,
				'buyins'          => $buyins,
				'rebuys'          => 0,
				'addons'          => 0,
				'hits'            => 0,
				'points'          => $points,
			);

			$net_profit = $winnings - $invested;
			$roi_pct    = $invested > 0 ? ( $net_profit / $invested ) * 100 : 0.0;

			$result['roi'][] = array(
				'player_id'       => $player_uuid,
				'tournament_id'   => $tournament_uuid,
				'total_invested'  => $invested,
				'total_winnings'  => $winnings,
				'net_profit'      => $net_profit,
				'roi_percentage'  => $roi_pct,
				'finish_position' => $finish,
				'tournament_date' => $tournament_date,
			);
		}

		return $result;
	}

	/**
	 * Rebuild the derived marts for one tournament from the canonical source.
	 *
	 * No-op unless the rollup is enabled (OFF until cutover). Idempotent: delete-then-insert
	 * the mart on tournament_uuid, and $wpdb->replace the ROI rows (safe now that Phase B added
	 * UNIQUE(player_id, tournament_id)).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return int Number of mart rows written (0 if disabled / nothing to write).
	 */
	public static function rebuild_tournament( $tournament_uuid ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return 0;
		}

		$tournament_uuid = (string) $tournament_uuid;
		if ( '' === $tournament_uuid ) {
			return 0;
		}

		$mart_table = $wpdb->prefix . 'poker_tournament_players';
		$roi_table  = $wpdb->prefix . 'poker_player_roi';
		if ( ! self::table_exists( $mart_table ) ) {
			return 0;
		}

		$rows = self::compute_rows( $tournament_uuid );
		if ( empty( $rows['mart'] ) ) {
			return 0;
		}

		// Idempotent re-projection: every mart row under this UUID is rollup-owned.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent rebuild.
		$wpdb->delete( $mart_table, array( 'tournament_id' => $tournament_uuid ), array( '%s' ) );

		$written = 0;
		foreach ( $rows['mart'] as $m ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$res = $wpdb->insert(
				$mart_table,
				$m,
				array( '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%f' )
			);
			if ( false !== $res ) {
				$written++;
			}
		}

		if ( self::table_exists( $roi_table ) ) {
			foreach ( $rows['roi'] as $r ) {
				// UNIQUE(player_id, tournament_id) (Phase B) makes replace() idempotent.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
				$wpdb->replace(
					$roi_table,
					$r,
					array( '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s' )
				);
			}
		}

		if ( class_exists( 'TDWP_Debug_Logger' ) ) {
			TDWP_Debug_Logger::log(
				'StatsRollup',
				'Rebuilt derived marts from canonical source',
				array(
					'tournament_uuid' => $tournament_uuid,
					'mart_rows'       => $written,
					'roi_rows'        => count( $rows['roi'] ),
				)
			);
		}

		return $written;
	}

	/**
	 * Read-only shadow reconciliation: diff what the rollup WOULD write against the current mart.
	 *
	 * Drives the Phase-D dry run. Never writes. Compares per (tournament_uuid, player_uuid):
	 * winnings, buy-ins, finish position; and per tournament: row counts.
	 *
	 * @param string|null $tournament_uuid Limit to one tournament, or null for every UUID in the source.
	 * @return array<int,array> One entry per tournament with mismatches[] and summary counts.
	 */
	public static function reconcile_report( $tournament_uuid = null ) {
		global $wpdb;

		$source     = $wpdb->prefix . 'tdwp_tournament_players';
		$mart_table = $wpdb->prefix . 'poker_tournament_players';
		if ( ! self::table_exists( $source ) || ! self::table_exists( $mart_table ) ) {
			return array();
		}

		if ( null !== $tournament_uuid ) {
			$uuids = array( (string) $tournament_uuid );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reconcile.
			$uuids = $wpdb->get_col(
				"SELECT DISTINCT tournament_uuid FROM {$source} WHERE tournament_uuid <> ''"
			);
		}

		$report = array();
		foreach ( (array) $uuids as $uuid ) {
			$computed = self::compute_rows( $uuid );
			$expected = array();
			foreach ( $computed['mart'] as $m ) {
				$expected[ $m['player_id'] ] = $m;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reconcile.
			$actual_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT player_id, winnings, buyins, finish_position
					 FROM {$mart_table} WHERE tournament_id = %s",
					$uuid
				)
			);
			$actual = array();
			foreach ( (array) $actual_rows as $a ) {
				$actual[ $a->player_id ] = $a;
			}

			$mismatches = array();
			foreach ( $expected as $puuid => $m ) {
				if ( ! isset( $actual[ $puuid ] ) ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'missing_in_mart' );
					continue;
				}
				$a = $actual[ $puuid ];
				if ( abs( (float) $m['winnings'] - (float) $a->winnings ) > 0.001 ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'winnings', 'rollup' => $m['winnings'], 'mart' => $a->winnings );
				}
				if ( (int) $m['buyins'] !== (int) $a->buyins ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'buyins', 'rollup' => $m['buyins'], 'mart' => $a->buyins );
				}
				if ( (int) $m['finish_position'] !== (int) $a->finish_position ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'finish', 'rollup' => $m['finish_position'], 'mart' => $a->finish_position );
				}
			}
			foreach ( $actual as $puuid => $a ) {
				if ( ! isset( $expected[ $puuid ] ) ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'extra_in_mart' );
				}
			}

			$report[] = array(
				'tournament_uuid' => $uuid,
				'rollup_rows'     => count( $expected ),
				'mart_rows'       => count( $actual ),
				'mismatches'      => $mismatches,
			);
		}

		return $report;
	}

	/* ---- helpers (mirrors of TDWP_Stats_Bridge, sourced from stored columns) ---- */

	/**
	 * Latest manual points overrides for a tournament, keyed "tuuid|puuid".
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return array<string,float>
	 */
	private static function get_override_map( $tournament_uuid ) {
		if ( ! class_exists( 'Poker_Points_Adjustment_Manager' ) ) {
			return array();
		}
		$manager = new Poker_Points_Adjustment_Manager();
		return $manager->get_adjustment_map( array( $tournament_uuid ) );
	}

	/**
	 * Tournament date for ROI rows: the tournament post's _tournament_date, else today.
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return string Y-m-d.
	 */
	private static function get_tournament_date( $tournament_uuid ) {
		global $wpdb;

		// Resolve the tournament post from the UUID postmeta, then read _tournament_date.
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'tournament_uuid' AND meta_value = %s LIMIT 1",
				$tournament_uuid
			)
		);
		if ( $post_id ) {
			$date = get_post_meta( (int) $post_id, '_tournament_date', true );
			if ( ! empty( $date ) ) {
				return (string) $date;
			}
		}
		return current_time( 'Y-m-d' );
	}

	/**
	 * Active tournament points formula string, or '' if none.
	 *
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
	 * Compute points via the active formula. Fully guarded — never fatals (falls back to 0).
	 *
	 * @param string $formula Formula expression.
	 * @param array  $data    Formula variables.
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
			if ( class_exists( 'TDWP_Debug_Logger' ) ) {
				TDWP_Debug_Logger::log( 'StatsRollup', 'Points formula failed; defaulting to 0', array( 'error' => $e->getMessage() ) );
			}
		}
		return 0.0;
	}

	/**
	 * Whether a database table exists.
	 *
	 * @param string $table Fully-qualified table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema guard.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
