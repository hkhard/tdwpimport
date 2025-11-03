<?php
/**
 * Prize Calculator Engine Class
 *
 * Handles prize pool and payout calculations for tournaments.
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Prize Calculator Class
 *
 * Provides prize pool calculation, payout distribution,
 * and chop calculation functionality.
 *
 * @since 3.0.0
 */
class TDWP_Prize_Calculator {

	/**
	 * Calculate net prize pool after rake
	 *
	 * @since 3.0.0
	 *
	 * @param float $buy_in         Buy-in amount per player.
	 * @param int   $entries        Number of entries.
	 * @param int   $rebuys         Number of rebuys.
	 * @param int   $addons         Number of add-ons.
	 * @param float $rebuy_cost     Cost per rebuy (default: same as buy-in).
	 * @param float $addon_cost     Cost per add-on (default: same as buy-in).
	 * @param float $rake_percentage Rake percentage (0-100).
	 * @return array Breakdown of prize pool calculation.
	 */
	public static function calculate_prize_pool( $buy_in, $entries, $rebuys = 0, $addons = 0, $rebuy_cost = 0, $addon_cost = 0, $rake_percentage = 0 ) {
		// Sanitize inputs.
		$buy_in         = floatval( $buy_in );
		$entries        = absint( $entries );
		$rebuys         = absint( $rebuys );
		$addons         = absint( $addons );
		$rebuy_cost     = $rebuy_cost > 0 ? floatval( $rebuy_cost ) : $buy_in;
		$addon_cost     = $addon_cost > 0 ? floatval( $addon_cost ) : $buy_in;
		$rake_percentage = max( 0, min( 100, floatval( $rake_percentage ) ) );

		// Calculate components.
		$entry_pool = $buy_in * $entries;
		$rebuy_pool = $rebuy_cost * $rebuys;
		$addon_pool = $addon_cost * $addons;

		// Calculate totals.
		$gross_pool = $entry_pool + $rebuy_pool + $addon_pool;
		$rake_amount = $gross_pool * ( $rake_percentage / 100 );
		$net_pool = $gross_pool - $rake_amount;

		return array(
			'buy_in'          => $buy_in,
			'entries'         => $entries,
			'rebuys'          => $rebuys,
			'addons'          => $addons,
			'rebuy_cost'      => $rebuy_cost,
			'addon_cost'      => $addon_cost,
			'entry_pool'      => round( $entry_pool, 2 ),
			'rebuy_pool'      => round( $rebuy_pool, 2 ),
			'addon_pool'      => round( $addon_pool, 2 ),
			'gross_pool'      => round( $gross_pool, 2 ),
			'rake_percentage' => $rake_percentage,
			'rake_amount'     => round( $rake_amount, 2 ),
			'net_pool'        => round( $net_pool, 2 ),
		);
	}

	/**
	 * Calculate payouts from structure ID
	 *
	 * @since 3.0.0
	 *
	 * @param float $prize_pool   Net prize pool.
	 * @param int   $structure_id Prize structure ID.
	 * @return array|WP_Error Array of payouts or error.
	 */
	public static function calculate_payouts( $prize_pool, $structure_id ) {
		$prize_pool   = floatval( $prize_pool );
		$structure_id = absint( $structure_id );

		if ( $prize_pool <= 0 ) {
			return new WP_Error(
				'invalid_pool',
				__( 'Prize pool must be greater than zero.', 'poker-tournament-import' )
			);
		}

		if ( 0 === $structure_id ) {
			return new WP_Error(
				'invalid_structure',
				__( 'Invalid structure ID.', 'poker-tournament-import' )
			);
		}

		// Load structure.
		$structure_manager = new TDWP_Prize_Structure();
		$structure = $structure_manager->get( $structure_id );

		if ( null === $structure ) {
			return new WP_Error(
				'structure_not_found',
				__( 'Prize structure not found.', 'poker-tournament-import' )
			);
		}

		// Calculate from structure.
		return self::calculate_payouts_from_array( $prize_pool, $structure->places );
	}

	/**
	 * Calculate payouts from structure array
	 *
	 * @since 3.0.0
	 *
	 * @param float $prize_pool Net prize pool.
	 * @param array $structure  Structure array with place/percentage.
	 * @return array Payouts array indexed by place.
	 */
	public static function calculate_payouts_from_array( $prize_pool, $structure ) {
		$prize_pool = floatval( $prize_pool );

		if ( $prize_pool <= 0 || ! is_array( $structure ) || empty( $structure ) ) {
			return array();
		}

		$payouts = array();
		$total_allocated = 0;

		// Calculate each payout.
		foreach ( $structure as $place_data ) {
			$place      = absint( $place_data['place'] );
			$percentage = floatval( $place_data['percentage'] );

			$amount = round( $prize_pool * ( $percentage / 100 ), 2 );

			$payouts[ $place ] = $amount;
			$total_allocated += $amount;
		}

		// Handle rounding differences - add/subtract from first place.
		$difference = round( $prize_pool - $total_allocated, 2 );

		if ( abs( $difference ) > 0 && isset( $payouts[1] ) ) {
			$payouts[1] += $difference;
		}

		return $payouts;
	}

	/**
	 * Format payout table as HTML
	 *
	 * @since 3.0.0
	 *
	 * @param array  $payouts      Payouts array indexed by place.
	 * @param string $currency_symbol Currency symbol.
	 * @return string HTML table.
	 */
	public static function format_payout_table( $payouts, $currency_symbol = '$' ) {
		if ( empty( $payouts ) ) {
			return '<p>' . esc_html__( 'No payouts calculated.', 'poker-tournament-import' ) . '</p>';
		}

		$currency_symbol = sanitize_text_field( wp_unslash( $currency_symbol ) );

		$html = '<table class="tdwp-payout-table">';
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= '<th>' . esc_html__( 'Place', 'poker-tournament-import' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Payout', 'poker-tournament-import' ) . '</th>';
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';

		foreach ( $payouts as $place => $amount ) {
			$html .= '<tr>';
			$html .= '<td>' . absint( $place ) . '</td>';
			$html .= '<td>' . esc_html( $currency_symbol ) . number_format( $amount, 2 ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Get common structure suggestions
	 *
	 * @since 3.0.0
	 *
	 * @return array Common prize structures.
	 */
	public static function suggest_common_structures() {
		return array(
			'winner_takes_all' => array(
				'name'   => __( 'Winner Takes All', 'poker-tournament-import' ),
				'places' => array(
					array( 'place' => 1, 'percentage' => 100 ),
				),
			),
			'top_3_50_30_20' => array(
				'name'   => __( 'Top 3: 50/30/20', 'poker-tournament-import' ),
				'places' => array(
					array( 'place' => 1, 'percentage' => 50 ),
					array( 'place' => 2, 'percentage' => 30 ),
					array( 'place' => 3, 'percentage' => 20 ),
				),
			),
			'top_4_40_30_20_10' => array(
				'name'   => __( 'Top 4: 40/30/20/10', 'poker-tournament-import' ),
				'places' => array(
					array( 'place' => 1, 'percentage' => 40 ),
					array( 'place' => 2, 'percentage' => 30 ),
					array( 'place' => 3, 'percentage' => 20 ),
					array( 'place' => 4, 'percentage' => 10 ),
				),
			),
			'top_5_35_25_20_12_8' => array(
				'name'   => __( 'Top 5: 35/25/20/12/8', 'poker-tournament-import' ),
				'places' => array(
					array( 'place' => 1, 'percentage' => 35 ),
					array( 'place' => 2, 'percentage' => 25 ),
					array( 'place' => 3, 'percentage' => 20 ),
					array( 'place' => 4, 'percentage' => 12 ),
					array( 'place' => 5, 'percentage' => 8 ),
				),
			),
			'top_6_30_22_18_14_10_6' => array(
				'name'   => __( 'Top 6: 30/22/18/14/10/6', 'poker-tournament-import' ),
				'places' => array(
					array( 'place' => 1, 'percentage' => 30 ),
					array( 'place' => 2, 'percentage' => 22 ),
					array( 'place' => 3, 'percentage' => 18 ),
					array( 'place' => 4, 'percentage' => 14 ),
					array( 'place' => 5, 'percentage' => 10 ),
					array( 'place' => 6, 'percentage' => 6 ),
				),
			),
		);
	}

	/**
	 * Calculate chip chop (proportional to chip counts)
	 *
	 * @since 3.0.0
	 *
	 * @param float $remaining_pool   Remaining prize pool.
	 * @param array $chip_counts      Array of chip counts indexed by player ID/name.
	 * @return array Chop amounts indexed by player.
	 */
	public static function calculate_chip_chop( $remaining_pool, $chip_counts ) {
		$remaining_pool = floatval( $remaining_pool );

		if ( $remaining_pool <= 0 || ! is_array( $chip_counts ) || empty( $chip_counts ) ) {
			return array();
		}

		// Calculate total chips.
		$total_chips = array_sum( array_map( 'absint', $chip_counts ) );

		if ( 0 === $total_chips ) {
			return array();
		}

		$chop_amounts = array();
		$total_allocated = 0;

		// Calculate proportional amounts.
		foreach ( $chip_counts as $player => $chips ) {
			$chips = absint( $chips );
			$percentage = ( $chips / $total_chips ) * 100;
			$amount = round( $remaining_pool * ( $percentage / 100 ), 2 );

			$chop_amounts[ $player ] = $amount;
			$total_allocated += $amount;
		}

		// Handle rounding - add/subtract from biggest stack.
		$difference = round( $remaining_pool - $total_allocated, 2 );

		if ( abs( $difference ) > 0 ) {
			$biggest_stack = array_keys( $chip_counts, max( $chip_counts ) )[0];
			$chop_amounts[ $biggest_stack ] += $difference;
		}

		return $chop_amounts;
	}

	/**
	 * Calculate ICM chop (Independent Chip Model)
	 *
	 * Simplified ICM calculation for remaining players
	 *
	 * @since 3.0.0
	 *
	 * @param array $remaining_prizes Array of remaining prize amounts.
	 * @param array $chip_counts      Array of chip counts indexed by player.
	 * @return array ICM values indexed by player.
	 */
	public static function calculate_icm_chop( $remaining_prizes, $chip_counts ) {
		if ( ! is_array( $remaining_prizes ) || ! is_array( $chip_counts ) ) {
			return array();
		}

		if ( empty( $remaining_prizes ) || empty( $chip_counts ) ) {
			return array();
		}

		// Sort prizes descending.
		rsort( $remaining_prizes );

		// Calculate total chips.
		$total_chips = array_sum( array_map( 'absint', $chip_counts ) );

		if ( 0 === $total_chips ) {
			return array();
		}

		$player_count = count( $chip_counts );
		$prize_count  = count( $remaining_prizes );

		// Simplified ICM: Calculate equity for each player.
		$icm_values = array();

		foreach ( $chip_counts as $player => $chips ) {
			$chips = absint( $chips );
			$equity = 0;

			// Calculate probability-weighted equity.
			for ( $prize_idx = 0; $prize_idx < min( $prize_count, $player_count ); $prize_idx++ ) {
				$probability = self::calculate_finish_probability(
					$chips,
					$total_chips,
					$player_count,
					$prize_idx + 1
				);

				$equity += $probability * $remaining_prizes[ $prize_idx ];
			}

			$icm_values[ $player ] = round( $equity, 2 );
		}

		// Normalize to match total prize pool exactly.
		$total_allocated = array_sum( $icm_values );
		$total_prizes = array_sum( $remaining_prizes );
		$difference = round( $total_prizes - $total_allocated, 2 );

		if ( abs( $difference ) > 0 ) {
			// Add difference to biggest stack.
			$biggest_stack = array_keys( $chip_counts, max( $chip_counts ) )[0];
			$icm_values[ $biggest_stack ] += $difference;
		}

		return $icm_values;
	}

	/**
	 * Calculate probability of finishing in specific place
	 *
	 * Simplified calculation for ICM
	 *
	 * @since 3.0.0
	 *
	 * @param int $player_chips Player's chip count.
	 * @param int $total_chips  Total chips in play.
	 * @param int $players_left Number of players remaining.
	 * @param int $place        Place to calculate probability for.
	 * @return float Probability (0-1).
	 */
	private static function calculate_finish_probability( $player_chips, $total_chips, $players_left, $place ) {
		if ( $total_chips <= 0 || $players_left <= 0 ) {
			return 0;
		}

		// Base probability from chip percentage.
		$chip_percentage = $player_chips / $total_chips;

		// Adjust for place (simplified model).
		// Higher places have slightly reduced probability for smaller stacks.
		$place_factor = 1 - ( ( $place - 1 ) * 0.1 );
		$place_factor = max( 0.1, $place_factor );

		// Adjust for number of players.
		$competition_factor = 1 / $players_left;

		$probability = $chip_percentage * $place_factor * ( $players_left - $place + 1 );

		return max( 0, min( 1, $probability ) );
	}

	/**
	 * Calculate even chop
	 *
	 * Split remaining prize pool evenly among players
	 *
	 * @since 3.0.0
	 *
	 * @param float $remaining_pool Remaining prize pool.
	 * @param array $players        Array of player identifiers.
	 * @return array Even chop amounts.
	 */
	public static function calculate_even_chop( $remaining_pool, $players ) {
		$remaining_pool = floatval( $remaining_pool );

		if ( $remaining_pool <= 0 || ! is_array( $players ) || empty( $players ) ) {
			return array();
		}

		$player_count = count( $players );
		$base_amount = floor( ( $remaining_pool * 100 ) / $player_count ) / 100;
		$chop_amounts = array();

		foreach ( $players as $player ) {
			$chop_amounts[ $player ] = $base_amount;
		}

		// Distribute remaining cents to first players.
		$total_allocated = $base_amount * $player_count;
		$difference = round( $remaining_pool - $total_allocated, 2 );

		if ( $difference > 0 ) {
			$cents_to_distribute = round( $difference * 100 );
			$player_idx = 0;

			while ( $cents_to_distribute > 0 && $player_idx < $player_count ) {
				$chop_amounts[ $players[ $player_idx ] ] += 0.01;
				$cents_to_distribute--;
				$player_idx++;
			}
		}

		return $chop_amounts;
	}

	/**
	 * Validate prize structure percentages
	 *
	 * @since 3.0.0
	 *
	 * @param array $structure Structure array with place/percentage.
	 * @return array Validation result with 'valid' and 'message' keys.
	 */
	public static function validate_structure( $structure ) {
		if ( ! is_array( $structure ) || empty( $structure ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Structure must be a non-empty array.', 'poker-tournament-import' ),
			);
		}

		$total = 0;
		$places = array();

		foreach ( $structure as $place_data ) {
			if ( ! isset( $place_data['place'] ) || ! isset( $place_data['percentage'] ) ) {
				return array(
					'valid'   => false,
					'message' => __( 'Each place must have place and percentage.', 'poker-tournament-import' ),
				);
			}

			$place      = absint( $place_data['place'] );
			$percentage = floatval( $place_data['percentage'] );

			if ( $percentage < 0 || $percentage > 100 ) {
				return array(
					'valid'   => false,
					'message' => __( 'Percentages must be between 0 and 100.', 'poker-tournament-import' ),
				);
			}

			if ( in_array( $place, $places, true ) ) {
				return array(
					'valid'   => false,
					'message' => __( 'Duplicate place numbers found.', 'poker-tournament-import' ),
				);
			}

			$places[] = $place;
			$total += $percentage;
		}

		// Check sequential places.
		sort( $places );
		for ( $i = 0; $i < count( $places ); $i++ ) {
			if ( $places[ $i ] !== $i + 1 ) {
				return array(
					'valid'   => false,
					'message' => __( 'Places must be sequential starting from 1.', 'poker-tournament-import' ),
				);
			}
		}

		// Check total (allow 0.01% rounding error).
		if ( abs( $total - 100 ) > 0.01 ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: actual total percentage */
					__( 'Percentages must sum to 100%%. Current total: %s%%', 'poker-tournament-import' ),
					number_format( $total, 2 )
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Structure is valid.', 'poker-tournament-import' ),
		);
	}
}
