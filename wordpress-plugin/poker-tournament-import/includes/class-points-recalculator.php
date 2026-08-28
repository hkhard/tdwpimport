<?php
/**
 * Recalculate imported tournament points from their stored .tdt content.
 *
 * Three defects in how the plugin fed Tournament Director's points formula were
 * fixed in 3.9.10 (see TdtPointsAdjustmentTest for the evidence):
 *
 *   - the director's `PointsAdjustment` was never read from the file,
 *   - the formula variable `n` counted players instead of entries, and
 *   - a surrender's contribution to the pot was missing.
 *
 * Tournaments imported before that release therefore carry points that are too
 * low. Re-importing every .tdt by hand would work but is slow and error-prone,
 * so this recalculates in place from `_tournament_raw_content` — the original
 * file content stored on each tournament post at import time.
 *
 * Design notes:
 *  - Dry run first, always. The admin screen previews every change before
 *    anything is written.
 *  - Manual overrides in tdwp_points_adjustments are never touched, and are
 *    re-applied after recalculation so a WP-side edit always wins.
 *  - Tournaments without stored raw content cannot be recalculated and are
 *    reported as skipped rather than silently left alone.
 *
 * @package Poker_Tournament_Import
 * @since 3.9.10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poker_Points_Recalculator {

	/**
	 * Recalculate one tournament from its stored .tdt content.
	 *
	 * @param int  $tournament_id Tournament post ID.
	 * @param bool $dry_run       When true, compute and report without writing.
	 * @return array{
	 *     status:string, tournament_id:int, title:string, uuid:string,
	 *     changes:array, unchanged:int, message:string
	 * }
	 */
	public function recalculate_tournament( $tournament_id, $dry_run = true ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$result        = array(
			'status'        => 'skipped',
			'tournament_id' => $tournament_id,
			'title'         => get_the_title( $tournament_id ),
			'uuid'          => '',
			'changes'       => array(),
			'unchanged'     => 0,
			'message'       => '',
		);

		$uuid = (string) get_post_meta( $tournament_id, 'tournament_uuid', true );
		if ( '' === $uuid ) {
			$result['message'] = __( 'No tournament UUID; cannot match statistics rows.', 'poker-tournament-import' );
			return $result;
		}
		$result['uuid'] = $uuid;

		$raw = (string) get_post_meta( $tournament_id, '_tournament_raw_content', true );
		if ( '' === $raw ) {
			$result['message'] = __( 'Original .tdt content was not stored for this tournament, so it cannot be recalculated. Re-import the file to correct it.', 'poker-tournament-import' );
			return $result;
		}

		// Re-parse with the corrected logic.
		$parser = new Poker_Tournament_Parser();

		ob_start();
		try {
			$parsed = $parser->parse_content( $raw );
		} catch ( Throwable $e ) {
			ob_end_clean();
			$result['status'] = 'error';

			/*
			 * Plugin versions before 3.9.10 passed the file straight to
			 * update_post_meta(), which unslashes. That stripped the backslashes
			 * from the .tdt's own \" \n and \\ escapes, so the stored copy no
			 * longer parses. The damage is lossy and cannot be repaired here
			 * (a stored "n" may have been either "\n" or a literal "n"), so the
			 * only honest remedy is to re-import the file.
			 */
			if ( $this->looks_slash_corrupted( $raw ) ) {
				$result['message'] = __( 'The stored copy of this .tdt was damaged by a bug in an earlier version (quote and newline escapes were stripped when it was saved). It cannot be repaired automatically — re-import the tournament\'s .tdt file and its points will be correct.', 'poker-tournament-import' );
				return $result;
			}

			$result['message'] = sprintf(
				/* translators: %s: error message */
				__( 'Could not parse the stored .tdt content: %s', 'poker-tournament-import' ),
				$e->getMessage()
			);
			return $result;
		}
		ob_end_clean();

		if ( empty( $parsed ) || empty( $parser->get_tournament_data()['players'] ) ) {
			$result['status']  = 'error';
			$result['message'] = __( 'The stored .tdt content produced no players.', 'poker-tournament-import' );
			return $result;
		}

		$data  = $parser->get_tournament_data();

		/*
		 * Guard against a mismatched raw copy. If the stored content belongs to a
		 * different tournament, recalculating would write one event's points onto
		 * another's results. Refuse rather than corrupt the marts.
		 */
		$parsed_uuid = (string) ( $data['metadata']['uuid'] ?? '' );
		if ( '' !== $parsed_uuid && 0 !== strcasecmp( $parsed_uuid, $uuid ) ) {
			$result['status']  = 'error';
			$result['message'] = __( 'The stored .tdt content belongs to a different tournament, so it was skipped. Re-import this tournament\'s own .tdt file to correct it.', 'poker-tournament-import' );
			return $result;
		}

		$table = $wpdb->prefix . 'poker_tournament_players';

		// Manual overrides win outright and must not be disturbed.
		$overrides = $this->get_override_map( $uuid );

		foreach ( $data['players'] as $player_uuid => $player ) {
			$new_points = (float) ( $player['points'] ?? 0 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted mart read.
			$current = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT points FROM {$table} WHERE tournament_id = %s AND player_id = %s",
					$uuid,
					$player_uuid
				)
			);

			if ( null === $current ) {
				// No mart row for this player; nothing to correct.
				continue;
			}

			$current = (float) $current;

			if ( isset( $overrides[ $player_uuid ] ) ) {
				$result['changes'][] = array(
					'player'     => $player['nickname'] ?? $player_uuid,
					'from'       => $current,
					'to'         => $current,
					'adjustment' => (float) ( $player['points_adjustment'] ?? 0 ),
					'note'       => __( 'left unchanged — a manual override is in force', 'poker-tournament-import' ),
				);
				continue;
			}

			// Compare on the stored scale to avoid float-noise "changes".
			if ( abs( $new_points - $current ) < 0.005 ) {
				$result['unchanged']++;
				continue;
			}

			$result['changes'][] = array(
				'player'     => $player['nickname'] ?? $player_uuid,
				'from'       => $current,
				'to'         => $new_points,
				'adjustment' => (float) ( $player['points_adjustment'] ?? 0 ),
				'note'       => '',
			);

			if ( ! $dry_run ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted mart write.
				$wpdb->update(
					$table,
					array( 'points' => $new_points ),
					array(
						'tournament_id' => $uuid,
						'player_id'     => $player_uuid,
					),
					array( '%f' ),
					array( '%s', '%s' )
				);

				// Keep the canonical source in step so a later rollup rebuild does
				// not revert this (see import_points, DB 3.9.10).
				$this->sync_canonical_points( $uuid, $player_uuid, $new_points );
			}
		}

		$result['status'] = $dry_run ? 'preview' : 'updated';

		return $result;
	}

	/**
	 * Recalculate every imported tournament.
	 *
	 * @param bool $dry_run When true, nothing is written.
	 * @return array{tournaments:array,totals:array}
	 */
	public function recalculate_all( $dry_run = true ) {
		$tournaments = get_posts(
			array(
				'post_type'      => 'tournament',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$out = array(
			'tournaments' => array(),
			'totals'      => array(
				'tournaments'     => 0,
				'players_changed' => 0,
				'skipped'         => 0,
				'errors'          => 0,
			),
		);

		foreach ( $tournaments as $tournament_id ) {
			$row = $this->recalculate_tournament( $tournament_id, $dry_run );

			$changed = 0;
			foreach ( $row['changes'] as $change ) {
				if ( '' === $change['note'] ) {
					$changed++;
				}
			}

			if ( 'skipped' === $row['status'] ) {
				$out['totals']['skipped']++;
			} elseif ( 'error' === $row['status'] ) {
				$out['totals']['errors']++;
			} elseif ( $changed > 0 || ! empty( $row['changes'] ) ) {
				$out['totals']['tournaments']++;
				$out['totals']['players_changed'] += $changed;
			}

			// Only report tournaments that have something to say.
			if ( ! empty( $row['changes'] ) || 'skipped' === $row['status'] || 'error' === $row['status'] ) {
				$out['tournaments'][] = $row;
			}
		}

		if ( ! $dry_run ) {
			// Derived statistics are now stale.
			if ( class_exists( 'Poker_Statistics_Engine' ) ) {
				Poker_Statistics_Engine::get_instance()->calculate_all_statistics();
			}
			if ( class_exists( 'Poker_Cache_Purge' ) ) {
				Poker_Cache_Purge::purge_public();
			}
		}

		return $out;
	}

	/**
	 * Detect a stored .tdt copy damaged by the pre-3.9.10 unslash bug.
	 *
	 * A genuine .tdt escapes the quotes inside its UserFormula Text values, so an
	 * intact copy always contains `\"` sequences (88 of them in a typical file).
	 * A copy that went through update_post_meta() without wp_slash() has had one
	 * level of escaping removed, leaving zero `\"` sequences. Checking for plain
	 * backslashes is not enough, because `\\` survives unslashing as `\`.
	 *
	 * Only used to improve the message after a parse failure, so a false negative
	 * costs nothing beyond a less specific explanation.
	 *
	 * @since 3.9.10
	 * @param string $raw Stored raw content.
	 * @return bool True when the content looks slash-stripped.
	 */
	private function looks_slash_corrupted( $raw ) {
		// Escaped quotes survived, so the copy was stored correctly.
		if ( false !== strpos( $raw, '\\"' ) ) {
			return false;
		}

		// A formula-bearing file must have had escaped quotes; they are gone.
		return ( false !== strpos( $raw, 'UserFormula' ) );
	}

	/**
	 * Latest manual override per player for a tournament.
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return array<string,float> player_uuid => adjusted points
	 */
	private function get_override_map( $tournament_uuid ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_points_adjustments';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema guard.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Override read.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_uuid, adjusted_points
				 FROM {$table}
				 WHERE tournament_uuid = %s
				 ORDER BY id ASC",
				$tournament_uuid
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			// Later rows supersede earlier ones.
			$map[ (string) $row->player_uuid ] = (float) $row->adjusted_points;
		}

		return $map;
	}

	/**
	 * Mirror the recalculated points onto the canonical per-entry source.
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @param string $player_uuid     Player UUID.
	 * @param float  $points          Recalculated points.
	 * @return void
	 */
	private function sync_canonical_points( $tournament_uuid, $player_uuid, $points ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_tournament_players';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema guard.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
		$cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		if ( ! in_array( 'import_points', $cols, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted write.
		$wpdb->update(
			$table,
			array( 'import_points' => $points ),
			array(
				'tournament_uuid' => $tournament_uuid,
				'player_uuid'     => $player_uuid,
				'source'          => 'import',
			),
			array( '%f' ),
			array( '%s', '%s', '%s' )
		);
	}
}
