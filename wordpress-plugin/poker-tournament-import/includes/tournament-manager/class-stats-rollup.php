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

	/** Live-tournament completion hooks (shared with TDWP_Stats_Bridge). */
	const FINISH_HOOK   = 'tdwp_tournament_finished';
	const COMPLETE_HOOK = 'tdwp_tournament_completed';
	const REFRESH_HOOK  = 'tdwp_stats_rollup_refresh';

	/**
	 * Whether the rollup is the active mart writer.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::ENABLED_OPTION, false );
	}

	/**
	 * Register finish-hook handlers — ONLY when enabled. Until cutover this is a no-op, so
	 * TDWP_Stats_Bridge remains the sole projector; after cutover the rollup takes over and the
	 * bridge stands down (see TDWP_Stats_Bridge::init).
	 *
	 * @return void
	 */
	public static function init() {
		// tdwp-4o2: keep the canonical source in sync with imports regardless of cutover state, so
		// canonical stays the complete record and a later cutover is ready. Additive listeners on the
		// batch importer's completion hooks; never touch the stats mart. (The single-import path in
		// class-admin calls sync_import_by_uuid() directly since it fires no hook.)
		add_action( 'poker_tournament_imported', array( __CLASS__, 'sync_import_by_post' ), 20, 1 );
		add_action( 'poker_tournament_updated', array( __CLASS__, 'sync_import_by_post' ), 20, 1 );

		if ( ! self::is_enabled() ) {
			return;
		}
		// Priority 20 so other finish listeners run first; rebuild is idempotent on double-fire.
		add_action( self::FINISH_HOOK, array( __CLASS__, 'on_tournament_finished' ), 20, 1 );
		add_action( self::COMPLETE_HOOK, array( __CLASS__, 'on_tournament_finished' ), 20, 1 );
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh_stats_mart' ) );
	}

	/**
	 * Sync one imported tournament into the canonical source, resolved from a tournament post ID.
	 * Additive: writes only source='import' canonical rows (idempotent); never touches the mart.
	 *
	 * @param int $post_id Tournament post ID.
	 * @return void
	 */
	public static function sync_import_by_post( $post_id ) {
		$uuid = get_post_meta( absint( $post_id ), 'tournament_uuid', true );
		if ( ! empty( $uuid ) ) {
			self::sync_import_by_uuid( (string) $uuid );
		}
	}

	/**
	 * Sync one imported tournament (by UUID) into the canonical source. No-op until the canonical
	 * columns exist. Idempotent (delete-then-insert of that tournament's source='import' rows).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return void
	 */
	public static function sync_import_by_uuid( $tournament_uuid ) {
		global $wpdb;
		$tournament_uuid = (string) $tournament_uuid;
		if ( '' === $tournament_uuid ) {
			return;
		}
		$src = $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $src ) ) {
			return;
		}
		// Guard: canonical columns must exist (v3.6.4 migration applied).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema guard.
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$src}" );
		if ( ! in_array( 'source', (array) $cols, true ) || ! in_array( 'import_buyins', (array) $cols, true ) ) {
			return;
		}
		self::backfill_imports( $src, 0, 0, $tournament_uuid );
	}

	/**
	 * Finish-hook handler: ensure live rows carry stored UUIDs, then rebuild the marts.
	 *
	 * Replaces TDWP_Stats_Bridge::project_to_stats_mart after cutover. Where the bridge mapped
	 * post IDs to UUIDs at projection time, this stamps the stored tournament_uuid/player_uuid
	 * columns on the live source rows first, then rolls up from those stored keys.
	 *
	 * @param int $tournament_id Tournament post ID.
	 * @return void
	 */
	public static function on_tournament_finished( $tournament_id ) {
		$tournament_id = absint( $tournament_id );
		if ( ! $tournament_id ) {
			return;
		}

		$tournament_uuid = self::ensure_live_uuids( $tournament_id );
		if ( '' === $tournament_uuid ) {
			return;
		}

		self::rebuild_tournament( $tournament_uuid );

		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::REFRESH_HOOK );
		}
	}

	/**
	 * Recompute the legacy statistics data-mart (async).
	 *
	 * @return void
	 */
	public static function refresh_stats_mart() {
		if ( class_exists( 'Poker_Statistics_Engine' ) ) {
			Poker_Statistics_Engine::get_instance()->calculate_all_statistics();
		}
	}

	/**
	 * Stamp stored tournament_uuid/player_uuid (+ source='live') on the live source rows for a
	 * finished tournament, minting UUIDs into postmeta where missing (reuses existing ones so live
	 * and imported records for the same post unify). Returns the tournament UUID.
	 *
	 * @param int $tournament_id Tournament post ID.
	 * @return string Tournament UUID, or '' on failure.
	 */
	private static function ensure_live_uuids( $tournament_id ) {
		global $wpdb;

		$source = $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $source ) ) {
			return '';
		}

		$tournament_uuid = self::get_or_create_meta_uuid( $tournament_id, 'tournament_uuid' );
		if ( '' === $tournament_uuid ) {
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration read.
		$player_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT player_id FROM {$source} WHERE tournament_id = %d", $tournament_id )
		);

		foreach ( (array) $player_ids as $pid ) {
			$player_uuid = self::get_or_create_meta_uuid( (int) $pid, 'player_uuid' );
			if ( '' === $player_uuid ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stamp stored keys.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$source} SET tournament_uuid = %s, player_uuid = %s, source = 'live'
					 WHERE tournament_id = %d AND player_id = %d",
					$tournament_uuid,
					$player_uuid,
					$tournament_id,
					(int) $pid
				)
			);
		}

		return $tournament_uuid;
	}

	/**
	 * Return an existing post-meta UUID, or generate, store, and return a new one.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key 'tournament_uuid' or 'player_uuid'.
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
	 * Aggregate the canonical per-entry source into derived per-player rows (READ-ONLY).
	 *
	 * @param string $tournament_uuid Tournament UUID join key.
	 * @return array{rows: array<string,array>, total_money: float} Per-player-uuid aggregates.
	 */
	public static function aggregate_from_source( $tournament_uuid, $source_table = null ) {
		global $wpdb;

		$out = array(
			'rows'        => array(),
			'total_money' => 0.0,
		);

		$tournament_uuid = (string) $tournament_uuid;
		if ( '' === $tournament_uuid ) {
			return $out;
		}

		$source = $source_table ? $source_table : $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $source ) ) {
			return $out;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_uuid, finish_position, prize_amount, paid_amount, source, import_buyins, import_hits
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
			$is_import = ( 'import' === $e->source );

			// Buy-ins: imports carry the original count on a single synthetic row (decision:
			// store-aggregate-on-1-row); live tournaments count one per entry row. Hits: imports
			// carry the parsed knockout count; live rows contribute 0 (unchanged live behavior).
			// Store-aggregate-on-1-row: the synthetic import row always exists, so preserve the
			// true buy-in count (including 0) rather than clamping — faithful to the source mart.
			$row_buyins = $is_import ? (int) $e->import_buyins : 1;
			$row_hits   = $is_import ? (int) $e->import_hits : 0;
			$out['total_money'] += $winnings;

			if ( ! isset( $out['rows'][ $puuid ] ) ) {
				$out['rows'][ $puuid ] = array(
					'finish'         => $finish,
					'winnings'       => $winnings,
					'buyins'         => $row_buyins,
					'hits'           => $row_hits,
					'total_invested' => $invested,
				);
				continue;
			}

			// Best (lowest, non-zero) finish wins; sum winnings/invested/hits; accumulate buy-ins.
			if ( $finish > 0 && ( 0 === $out['rows'][ $puuid ]['finish'] || $finish < $out['rows'][ $puuid ]['finish'] ) ) {
				$out['rows'][ $puuid ]['finish'] = $finish;
			}
			$out['rows'][ $puuid ]['winnings']       += $winnings;
			$out['rows'][ $puuid ]['buyins']         += $row_buyins;
			$out['rows'][ $puuid ]['hits']           += $row_hits;
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
	public static function compute_rows( $tournament_uuid, $source_table = null ) {
		$agg = self::aggregate_from_source( $tournament_uuid, $source_table );
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
			$hits     = (int) $row['hits'];
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
				'hits'            => $hits,
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
	public static function reconcile_report( $tournament_uuid = null, $source_table = null, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$source     = $source_table ? $source_table : $wpdb->prefix . 'tdwp_tournament_players';
		$mart_table = $wpdb->prefix . 'poker_tournament_players';
		if ( ! self::table_exists( $source ) || ! self::table_exists( $mart_table ) ) {
			return array();
		}

		if ( null !== $tournament_uuid ) {
			$uuids = array( (string) $tournament_uuid );
		} else {
			// Batching (tdwp-77n): a deterministic ORDER BY + LIMIT/OFFSET cursor lets the admin UI
			// reconcile large datasets in bounded chunks without a PHP timeout.
			$limit_sql = '';
			if ( $limit > 0 ) {
				$limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $limit, (int) $offset );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only reconcile; LIMIT clause prepared above.
			$uuids = $wpdb->get_col(
				"SELECT DISTINCT tournament_uuid FROM {$source} WHERE tournament_uuid <> '' ORDER BY tournament_uuid" . $limit_sql
			);
		}

		$report = array();
		foreach ( (array) $uuids as $uuid ) {
			// Use the cheap aggregate (no points formula / override / date lookups): reconcile only
			// needs winnings/buyins/hits/finish, so this avoids the per-tournament formula cost.
			$agg      = self::aggregate_from_source( $uuid, $source );
			$expected = $agg['rows'];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reconcile.
			$actual_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT player_id, winnings, buyins, finish_position, hits
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
				if ( (int) $m['finish'] !== (int) $a->finish_position ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'finish', 'rollup' => $m['finish'], 'mart' => $a->finish_position );
				}
				if ( (int) $m['hits'] !== (int) $a->hits ) {
					$mismatches[] = array( 'player' => $puuid, 'issue' => 'hits', 'rollup' => $m['hits'], 'mart' => $a->hits );
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

	/**
	 * Count distinct tournaments in the canonical source (for the batched-reconcile cursor).
	 *
	 * @param string|null $source_table Optional source override.
	 * @return int
	 */
	public static function count_source_tournaments( $source_table = null ) {
		global $wpdb;
		$source = $source_table ? $source_table : $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $source ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count for batching cursor.
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT tournament_uuid) FROM {$source} WHERE tournament_uuid <> ''" );
	}

	/**
	 * Count distinct tournaments in the legacy mart (for the batched-backfill cursor).
	 *
	 * @return int
	 */
	public static function count_import_tournaments() {
		global $wpdb;
		$mart = $wpdb->prefix . 'poker_tournament_players';
		if ( ! self::table_exists( $mart ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count for batching cursor.
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT tournament_id) FROM {$mart}" );
	}

	/**
	 * Backfill imported tournaments into the canonical per-entry source as synthetic entries.
	 *
	 * Each imported mart row becomes ONE synthetic entry (source='import', entry_number=1) carrying
	 * the aggregate buy-in count (import_buyins) and knockouts (import_hits), keyed by the resolved
	 * bigint post IDs plus the stored UUIDs. ROI invested (paid_amount) is derived from the
	 * tournament's prize pool divided by its total entries (decision #3: derive-from-prize-pool);
	 * tournaments without a _prize_pool are flagged and get paid_amount=0 for later curation.
	 *
	 * Only tournaments NOT already present as LIVE rows are backfilled (live is canonical; decision #5).
	 * Writes into $target_table (the real source, or a *_shadow clone for a dry run).
	 *
	 * @param string $target_table Fully-qualified destination table.
	 * @return array Summary: inserted, tournaments, flagged_no_buyin[].
	 */
	public static function backfill_imports( $target_table, $limit = 0, $offset = 0, $only_uuid = '' ) {
		global $wpdb;

		$mart     = $wpdb->prefix . 'poker_tournament_players';
		$live_src = $wpdb->prefix . 'tdwp_tournament_players';
		$summary  = array(
			'inserted'         => 0,
			'tournaments'      => 0,
			'flagged_no_buyin' => array(),
			'ambiguous'        => array(), // uuid -> post mapping was not 1:1 (merged/duplicate); skipped for review
			'skipped_missing'  => array(), // uuid has no matching post (trashed/deleted); skipped
		);

		if ( ! self::table_exists( $mart ) || ! self::table_exists( $target_table ) ) {
			return $summary;
		}

		// Live tournament UUIDs are canonical — never overwrite them with an import projection.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration read.
		$live_uuids = $wpdb->get_col( "SELECT DISTINCT tournament_uuid FROM {$live_src} WHERE tournament_uuid <> '' AND source = 'live'" );
		$live_set   = array_fill_keys( (array) $live_uuids, true );

		if ( '' !== $only_uuid ) {
			// tdwp-4o2: single-tournament sync (called after an import to keep canonical complete).
			$tournament_uuids = array( (string) $only_uuid );
		} else {
			// Batching (tdwp-77n): deterministic ORDER BY + LIMIT/OFFSET so the admin UI can backfill
			// large datasets one bounded chunk per request.
			$limit_sql = '';
			if ( $limit > 0 ) {
				$limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $limit, (int) $offset );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration read; LIMIT prepared above.
			$tournament_uuids = $wpdb->get_col( "SELECT DISTINCT tournament_id FROM {$mart} ORDER BY tournament_id" . $limit_sql );
		}

		foreach ( (array) $tournament_uuids as $tuuid ) {
			if ( isset( $live_set[ $tuuid ] ) ) {
				continue; // Live tournament — skip.
			}

			// tdwp-5xm: resolve to exactly one post. Ambiguous (merged/duplicate UUID) or missing
			// (trashed post) mappings are flagged and skipped rather than silently mis-resolved.
			$t_ids = $wpdb->get_col(
				$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='tournament_uuid' AND meta_value=%s", $tuuid )
			);
			$t_ids = array_values( array_unique( array_map( 'intval', (array) $t_ids ) ) );
			if ( count( $t_ids ) > 1 ) {
				$summary['ambiguous'][] = 'tournament:' . $tuuid;
				continue;
			}
			$tournament_post_id = isset( $t_ids[0] ) ? (int) $t_ids[0] : 0;
			if ( ! $tournament_post_id || 'trash' === get_post_status( $tournament_post_id ) ) {
				$summary['skipped_missing'][] = 'tournament:' . $tuuid;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration read.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT player_id, finish_position, winnings, buyins, hits FROM {$mart} WHERE tournament_id = %s",
					$tuuid
				)
			);
			if ( empty( $rows ) ) {
				continue;
			}

			// Derive per-entry buy-in: prize pool / total entries. Flag tournaments with no prize pool.
			$prize_pool    = (float) get_post_meta( $tournament_post_id, '_prize_pool', true );
			$total_entries = 0;
			foreach ( $rows as $r ) {
				$total_entries += (int) $r->buyins;
			}
			$per_entry_buyin = ( $prize_pool > 0 && $total_entries > 0 ) ? ( $prize_pool / $total_entries ) : 0.0;
			if ( $per_entry_buyin <= 0 ) {
				$summary['flagged_no_buyin'][] = $tuuid;
			}

			$summary['tournaments']++;

			// Idempotency: clear this tournament's prior import rows so a re-run/batch retry never
			// duplicates. Only source='import' rows are removed — live rows are never touched.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent backfill.
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$target_table} WHERE tournament_uuid = %s AND source = 'import'", $tuuid )
			);

			foreach ( $rows as $r ) {
				// tdwp-5xm: same unique-resolution guard for the player post.
				$p_ids = $wpdb->get_col(
					$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='player_uuid' AND meta_value=%s", $r->player_id )
				);
				$p_ids = array_values( array_unique( array_map( 'intval', (array) $p_ids ) ) );
				if ( count( $p_ids ) > 1 ) {
					$summary['ambiguous'][] = 'player:' . $r->player_id;
					continue;
				}
				$player_post_id = isset( $p_ids[0] ) ? (int) $p_ids[0] : 0;
				if ( ! $player_post_id || 'trash' === get_post_status( $player_post_id ) ) {
					$summary['skipped_missing'][] = 'player:' . $r->player_id;
					continue;
				}

				$buyins = (int) $r->buyins;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration write.
				$res = $wpdb->insert(
					$target_table,
					array(
						'tournament_id'   => $tournament_post_id,
						'player_id'       => $player_post_id,
						'entry_number'    => 1,
						'status'          => 'busted',
						'finish_position' => (int) $r->finish_position,
						'prize_amount'    => (float) $r->winnings,
						'paid_amount'     => (float) $per_entry_buyin * $buyins,
						'tournament_uuid' => $tuuid,
						'player_uuid'     => $r->player_id,
						'source'          => 'import',
						'import_buyins'   => $buyins,
						'import_hits'     => (int) $r->hits,
					),
					array( '%d', '%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%d' )
				);
				if ( false !== $res ) {
					$summary['inserted']++;
				}
			}
		}

		return $summary;
	}

	/**
	 * List imported tournaments in the canonical source whose per-entry buy-in is still 0
	 * (no prize pool was available at backfill) — the curation worklist (tdwp-npe).
	 *
	 * @return array<int,array> {tournament_uuid, name, entries, players}
	 */
	public static function list_uncurated_imports() {
		global $wpdb;
		$src = $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $src ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Curation worklist.
		$rows = $wpdb->get_results(
			"SELECT tournament_uuid, COUNT(*) AS players, SUM(import_buyins) AS entries, SUM(paid_amount) AS invested
			 FROM {$src}
			 WHERE source = 'import' AND tournament_uuid <> ''
			 GROUP BY tournament_uuid
			 HAVING SUM(paid_amount) = 0
			 ORDER BY tournament_uuid"
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$post_id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='tournament_uuid' AND meta_value=%s LIMIT 1", $r->tournament_uuid )
			);
			$out[] = array(
				'tournament_uuid' => $r->tournament_uuid,
				'name'            => $post_id ? get_the_title( $post_id ) : $r->tournament_uuid,
				'entries'         => (int) $r->entries,
				'players'         => (int) $r->players,
			);
		}
		return $out;
	}

	/**
	 * Apply a curated per-entry buy-in to an imported tournament: sets paid_amount on its canonical
	 * import rows (= buy-in × that entry's import_buyins) and rebuilds the ROI mart for it from the
	 * corrected figures. ROI-only — participation counts/winnings are unchanged (tdwp-npe).
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @param float  $per_entry_buyin Curated buy-in per entry.
	 * @return int Number of canonical rows updated.
	 */
	public static function apply_import_buyin( $tournament_uuid, $per_entry_buyin ) {
		global $wpdb;
		$tournament_uuid = (string) $tournament_uuid;
		$per_entry_buyin = (float) $per_entry_buyin;
		if ( '' === $tournament_uuid || $per_entry_buyin < 0 ) {
			return 0;
		}
		$src = $wpdb->prefix . 'tdwp_tournament_players';
		if ( ! self::table_exists( $src ) ) {
			return 0;
		}

		// paid_amount per row = buy-in × the entry's carried buy-in count (min 1 so 0-buyin rows still cost the buy-in once? no — keep faithful: use import_buyins).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Curation write.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$src} SET paid_amount = %f * GREATEST(import_buyins, 1)
				 WHERE tournament_uuid = %s AND source = 'import'",
				$per_entry_buyin,
				$tournament_uuid
			)
		);

		// Rebuild the ROI mart for this tournament from the corrected canonical figures.
		$roi_table = $wpdb->prefix . 'poker_player_roi';
		if ( self::table_exists( $roi_table ) ) {
			$rows = self::compute_rows( $tournament_uuid );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent ROI rebuild.
			$wpdb->delete( $roi_table, array( 'tournament_id' => $tournament_uuid ), array( '%s' ) );
			foreach ( $rows['roi'] as $r ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ROI write.
				$wpdb->replace( $roi_table, $r, array( '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s' ) );
			}
		}

		return $updated ? (int) $updated : 0;
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
