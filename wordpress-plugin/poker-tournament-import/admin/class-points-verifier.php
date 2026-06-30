<?php
/**
 * Post-import points verification / selection.
 *
 * Lets an operator inspect the per-tournament points that were computed at
 * import time, preview what a different formula would produce, and apply a
 * chosen formula before the points pollute the season standings.
 *
 * @package Poker_Tournament_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes, previews and applies tournament points for verification.
 */
class Poker_Tournament_Points_Verifier {

	/**
	 * Sentinel formula key meaning "the formula embedded in the .tdt file".
	 */
	const EMBEDDED_KEY = '__embedded__';

	/**
	 * Transient name fragments that cache standings derived from points.
	 *
	 * @var string[]
	 */
	private $cache_patterns = array(
		'poker_season_standings_',
		'poker_series_standings_',
		'poker_overall_standings_',
		'poker_statistics_',
		'poker_leaderboard_',
		'poker_player_roi_',
	);

	/**
	 * Formula validator instance.
	 *
	 * @var Poker_Tournament_Formula_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new Poker_Tournament_Formula_Validator();
	}

	/**
	 * Build the list of selectable formulas for a tournament.
	 *
	 * @param int $post_id Tournament post ID.
	 * @return array<string,string> Map of formula_key => human label.
	 */
	public function get_selectable_formulas( $post_id ) {
		$choices = array();

		$embedded = get_post_meta( $post_id, '_tournament_points_formula', true );
		if ( ! empty( $embedded ) ) {
			$choices[ self::EMBEDDED_KEY ] = __( 'Embedded .tdt formula', 'poker-tournament-import' );
		}

		$formulas = $this->validator->get_all_formulas();
		foreach ( $formulas as $key => $data ) {
			$label = isset( $data['name'] ) && ! empty( $data['name'] ) ? $data['name'] : $key;
			$choices[ $key ] = $label;
		}

		return $choices;
	}

	/**
	 * Resolve a formula key to a formula_data array (formula + dependencies).
	 *
	 * @param int    $post_id     Tournament post ID.
	 * @param string $formula_key Formula key or EMBEDDED_KEY.
	 * @return array|null Normalised formula data, or null if unavailable.
	 */
	private function resolve_formula( $post_id, $formula_key ) {
		if ( self::EMBEDDED_KEY === $formula_key ) {
			$embedded = get_post_meta( $post_id, '_tournament_points_formula', true );
			if ( empty( $embedded ) ) {
				return null;
			}
			// Stored embedded formula may be a string or a formula_data array.
			if ( is_array( $embedded ) ) {
				$formula = isset( $embedded['formula'] ) ? $embedded['formula'] : '';
				$deps    = isset( $embedded['dependencies'] ) ? $embedded['dependencies'] : array();
			} else {
				$formula = (string) $embedded;
				$deps    = array();
			}
			if ( ! is_array( $deps ) ) {
				$deps = preg_split( '/[\r\n;]+/', (string) $deps, -1, PREG_SPLIT_NO_EMPTY );
				$deps = array_map( 'trim', (array) $deps );
			}
			return array(
				'formula'      => $formula,
				'dependencies' => $deps,
				'name'         => __( 'Embedded .tdt formula', 'poker-tournament-import' ),
			);
		}

		return $this->validator->get_formula( $formula_key );
	}

	/**
	 * Read the stored financial aggregates for a tournament.
	 *
	 * Mirrors the figures the importer feeds into the points formula. When the
	 * stored prize pool is zero but a per-player buy-in exists, monies are
	 * inferred (buy_in * buyins) and flagged as estimated — matching the
	 * importer's financial fallback.
	 *
	 * @param int $post_id      Tournament post ID.
	 * @param int $player_count Number of player rows (fallback for buyins).
	 * @return array Financial aggregates plus an `estimated` flag.
	 */
	private function get_financials( $post_id, $player_count ) {
		$stats = get_post_meta( $post_id, '_tournament_stats', true );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		$buy_in       = floatval( get_post_meta( $post_id, '_buy_in', true ) );
		$total_buyins = isset( $stats['total_buyins'] ) ? intval( $stats['total_buyins'] ) : 0;
		$total_rebuys = isset( $stats['total_rebuys'] ) ? intval( $stats['total_rebuys'] ) : 0;
		$total_addons = isset( $stats['total_addons'] ) ? intval( $stats['total_addons'] ) : 0;
		$monies       = isset( $stats['gross_prize_pool'] ) ? floatval( $stats['gross_prize_pool'] ) : 0.0;

		if ( $total_buyins <= 0 ) {
			$total_buyins = $player_count;
		}

		$estimated = false;
		if ( $monies <= 0 && $buy_in > 0 && $total_buyins > 0 ) {
			$monies    = $buy_in * $total_buyins;
			$estimated = true;
		}

		return array(
			'buy_in'       => $buy_in,
			'total_buyins' => $total_buyins,
			'total_rebuys' => $total_rebuys,
			'total_addons' => $total_addons,
			'monies'       => $monies,
			'estimated'    => $estimated,
		);
	}

	/**
	 * Fetch player result rows for a tournament from the data mart.
	 *
	 * @param string $tournament_uuid Tournament UUID.
	 * @return array[] Rows keyed numerically with player_id, finish_position, etc.
	 */
	private function get_player_rows( $tournament_uuid ) {
		global $wpdb;

		if ( empty( $tournament_uuid ) ) {
			return array();
		}

		$table = $wpdb->prefix . 'poker_tournament_players';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_id, finish_position, winnings, buyins, rebuys, addons, hits, points
				 FROM {$table} WHERE tournament_id = %s ORDER BY finish_position ASC",
				$tournament_uuid
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Resolve a player UUID to a display name.
	 *
	 * @param string $player_uuid Player UUID.
	 * @return string Display name (or the UUID if no post matches).
	 */
	private function get_player_name( $player_uuid ) {
		$posts = get_posts(
			array(
				'post_type'      => 'player',
				'posts_per_page' => 1,
				'fields'         => 'all',
				'meta_query'     => array(
					array(
						'key'     => 'player_uuid',
						'value'   => $player_uuid,
						'compare' => '=',
					),
				),
			)
		);

		if ( ! empty( $posts ) && ! empty( $posts[0]->post_title ) ) {
			return $posts[0]->post_title;
		}

		return $player_uuid;
	}

	/**
	 * Compute points for one player row under a resolved formula.
	 *
	 * Assembles the complete formula exactly as the importer does (dependencies
	 * joined by ';' then the formula expression) so previews match imports.
	 *
	 * @param array $formula_data Resolved formula data.
	 * @param array $row          Player result row.
	 * @param array $fin          Financial aggregates from get_financials().
	 * @param int   $player_count Total players in the tournament.
	 * @return float|null Computed points, or null on formula error.
	 */
	private function compute_points( $formula_data, $row, $fin, $player_count ) {
		$tournament_data = array(
			'total_players'        => $player_count,
			'finish_position'      => intval( $row['finish_position'] ),
			'hits'                 => intval( $row['hits'] ),
			'total_money'          => $fin['monies'],
			'total_buyins'         => $fin['total_buyins'],
			'total_rebuys'         => $fin['total_rebuys'],
			'total_addons'         => $fin['total_addons'],
			'total_buyins_amount'  => $fin['monies'],
			'total_rebuys_amount'  => 0,
			'total_addons_amount'  => 0,
			'buyin_amount'         => $fin['buy_in'],
			'fee_amount'           => 0,
			'prize_pool'           => $fin['monies'],
			'winnings'             => floatval( $row['winnings'] ),
		);

		$complete_formula = '';
		if ( ! empty( $formula_data['dependencies'] ) ) {
			$complete_formula = implode( ';', $formula_data['dependencies'] ) . ';';
		}
		$complete_formula .= $formula_data['formula'];

		$result = $this->validator->calculate_formula( $complete_formula, $tournament_data, 'tournament' );
		if ( empty( $result['success'] ) ) {
			return null;
		}

		return is_numeric( $result['result'] ) ? floatval( $result['result'] ) : null;
	}

	/**
	 * Classify the health of a set of points values.
	 *
	 * @param float[] $points       Points values.
	 * @param bool    $monies_zero  Whether the tournament's monies are zero.
	 * @return array Health descriptor: severity, has_negatives, anomaly_count, sum, messages.
	 */
	public function get_health_flags( $points, $monies_zero = false ) {
		$has_negatives = false;
		$zero_count    = 0;
		$sum           = 0.0;

		foreach ( $points as $value ) {
			$value = floatval( $value );
			$sum  += $value;
			if ( $value < 0 ) {
				$has_negatives = true;
			} elseif ( 0.0 === $value ) {
				++$zero_count;
			}
		}

		$messages = array();
		$severity = 'ok';

		if ( $has_negatives ) {
			$severity   = 'critical';
			$messages[] = __( 'One or more players have NEGATIVE points — these will corrupt the season standings.', 'poker-tournament-import' );
		}

		if ( $monies_zero ) {
			if ( 'ok' === $severity ) {
				$severity = 'warning';
			}
			$messages[] = __( 'Tournament monies are zero — points may be unreliable.', 'poker-tournament-import' );
		}

		if ( $zero_count > 0 ) {
			if ( 'ok' === $severity ) {
				$severity = 'warning';
			}
			$messages[] = sprintf(
				/* translators: %d: number of players with zero points */
				__( '%d player(s) have zero points.', 'poker-tournament-import' ),
				$zero_count
			);
		}

		return array(
			'severity'      => $severity,
			'has_negatives' => $has_negatives,
			'zero_count'    => $zero_count,
			'anomaly_count' => ( $has_negatives ? 1 : 0 ) + $zero_count,
			'sum'           => round( $sum, 2 ),
			'messages'      => $messages,
		);
	}

	/**
	 * Build a full verification summary for one tournament.
	 *
	 * @param int $post_id Tournament post ID.
	 * @return array|WP_Error Summary, or WP_Error if the post is invalid.
	 */
	public function get_tournament_summary( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'tournament' !== $post->post_type ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found.', 'poker-tournament-import' ) );
		}

		$uuid    = get_post_meta( $post_id, 'tournament_uuid', true );
		$rows    = $this->get_player_rows( $uuid );
		$count   = count( $rows );
		$fin     = $this->get_financials( $post_id, $count );

		$players = array();
		$points  = array();
		foreach ( $rows as $row ) {
			$value     = floatval( $row['points'] );
			$points[]  = $value;
			$players[] = array(
				'player_id'       => $row['player_id'],
				'name'            => $this->get_player_name( $row['player_id'] ),
				'finish_position' => intval( $row['finish_position'] ),
				'hits'            => intval( $row['hits'] ),
				'winnings'        => floatval( $row['winnings'] ),
				'points'          => $value,
			);
		}

		$health = $this->get_health_flags( $points, ( $fin['monies'] <= 0 ) );

		return array(
			'post_id'       => $post_id,
			'title'         => $post->post_title,
			'uuid'          => $uuid,
			'season_id'     => intval( get_post_meta( $post_id, '_season_id', true ) ),
			'player_count'  => $count,
			'financials'    => $fin,
			'formula_used'  => get_post_meta( $post_id, '_formula_used', true ),
			'verified'      => (bool) get_post_meta( $post_id, '_points_verified', true ),
			'verified_at'   => get_post_meta( $post_id, '_points_verified_at', true ),
			'formulas'      => $this->get_selectable_formulas( $post_id ),
			'players'       => $players,
			'health'        => $health,
		);
	}

	/**
	 * Preview points under a candidate formula WITHOUT writing anything.
	 *
	 * @param int    $post_id     Tournament post ID.
	 * @param string $formula_key Formula key or EMBEDDED_KEY.
	 * @return array|WP_Error Per-player current/preview/delta plus health.
	 */
	public function preview_formula( $post_id, $formula_key ) {
		$post = get_post( $post_id );
		if ( ! $post || 'tournament' !== $post->post_type ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found.', 'poker-tournament-import' ) );
		}

		$formula_data = $this->resolve_formula( $post_id, $formula_key );
		if ( ! $formula_data || empty( $formula_data['formula'] ) ) {
			return new WP_Error( 'invalid_formula', __( 'Selected formula is not available.', 'poker-tournament-import' ) );
		}

		$uuid  = get_post_meta( $post_id, 'tournament_uuid', true );
		$rows  = $this->get_player_rows( $uuid );
		$count = count( $rows );
		$fin   = $this->get_financials( $post_id, $count );

		$players       = array();
		$preview_points = array();
		foreach ( $rows as $row ) {
			$current = floatval( $row['points'] );
			$preview = $this->compute_points( $formula_data, $row, $fin, $count );

			$preview_points[] = ( null === $preview ) ? 0.0 : $preview;
			$players[]        = array(
				'player_id'       => $row['player_id'],
				'name'            => $this->get_player_name( $row['player_id'] ),
				'finish_position' => intval( $row['finish_position'] ),
				'current'         => $current,
				'preview'         => $preview,
				'delta'           => ( null === $preview ) ? null : round( $preview - $current, 2 ),
				'error'           => ( null === $preview ),
			);
		}

		return array(
			'formula_key' => $formula_key,
			'players'     => $players,
			'health'      => $this->get_health_flags( $preview_points, ( $fin['monies'] <= 0 ) ),
			'estimated'   => $fin['estimated'],
		);
	}

	/**
	 * Apply a formula's points to the data mart and recompute statistics.
	 *
	 * @param int    $post_id     Tournament post ID.
	 * @param string $formula_key Formula key or EMBEDDED_KEY.
	 * @return array|WP_Error Result summary, or WP_Error on failure.
	 */
	public function apply_formula( $post_id, $formula_key ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post || 'tournament' !== $post->post_type ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found.', 'poker-tournament-import' ) );
		}

		$formula_data = $this->resolve_formula( $post_id, $formula_key );
		if ( ! $formula_data || empty( $formula_data['formula'] ) ) {
			return new WP_Error( 'invalid_formula', __( 'Selected formula is not available.', 'poker-tournament-import' ) );
		}

		$uuid  = get_post_meta( $post_id, 'tournament_uuid', true );
		$rows  = $this->get_player_rows( $uuid );
		$count = count( $rows );
		$fin   = $this->get_financials( $post_id, $count );
		$table = $wpdb->prefix . 'poker_tournament_players';

		$updated   = 0;
		$negatives = 0;
		foreach ( $rows as $row ) {
			$value = $this->compute_points( $formula_data, $row, $fin, $count );
			if ( null === $value ) {
				continue;
			}
			if ( $value < 0 ) {
				$value = 0;
				++$negatives;
			}
			$wpdb->update(
				$table,
				array( 'points' => $value ),
				array(
					'tournament_id' => $uuid,
					'player_id'     => $row['player_id'],
				),
				array( '%f' ),
				array( '%s', '%s' )
			);
			++$updated;
		}

		$label = isset( $formula_data['name'] ) ? $formula_data['name'] : $formula_key;
		update_post_meta( $post_id, '_formula_used', 'verified_' . $formula_key );
		update_post_meta( $post_id, '_points_verified', 1 );
		update_post_meta( $post_id, '_points_verified_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_points_verified_by', get_current_user_id() );

		$this->bust_caches();

		/**
		 * Fires after a tournament's points have been re-applied via verification.
		 *
		 * @param int    $post_id     Tournament post ID.
		 * @param string $formula_key Applied formula key.
		 */
		do_action( 'tdwp_tournament_points_updated', $post_id, $formula_key );

		if ( class_exists( 'Poker_Statistics_Engine' ) ) {
			$engine = new Poker_Statistics_Engine();
			$engine->calculate_all_statistics();
		}

		return array(
			'updated'   => $updated,
			'negatives' => $negatives,
			'label'     => $label,
		);
	}

	/**
	 * Delete all standings/statistics transients derived from points.
	 */
	private function bust_caches() {
		global $wpdb;

		foreach ( $this->cache_patterns as $pattern ) {
			$like    = $wpdb->esc_like( '_transient_' . $pattern ) . '%';
			$options = $wpdb->get_col(
				$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
			);
			foreach ( (array) $options as $option ) {
				delete_transient( str_replace( '_transient_', '', $option ) );
			}
		}
	}

	/**
	 * List a season's tournaments with health flags for the season overview.
	 *
	 * @param int $season_id Season post ID.
	 * @return array[] One descriptor per tournament.
	 */
	public function get_season_tournaments_with_health( $season_id ) {
		$tournaments = get_posts(
			array(
				'post_type'      => 'tournament',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_season_id',
						'value'   => $season_id,
						'compare' => '=',
					),
				),
			)
		);

		$out = array();
		foreach ( $tournaments as $tournament ) {
			$summary = $this->get_tournament_summary( $tournament->ID );
			if ( is_wp_error( $summary ) ) {
				continue;
			}
			$out[] = array(
				'post_id'       => $tournament->ID,
				'title'         => $summary['title'],
				'date'          => get_the_date( 'Y-m-d', $tournament ),
				'player_count'  => $summary['player_count'],
				'monies'        => $summary['financials']['monies'],
				'estimated'     => $summary['financials']['estimated'],
				'points_sum'    => $summary['health']['sum'],
				'anomaly_count' => $summary['health']['anomaly_count'],
				'severity'      => $summary['health']['severity'],
				'formula_used'  => $summary['formula_used'],
				'verified'      => $summary['verified'],
			);
		}

		return $out;
	}
}
