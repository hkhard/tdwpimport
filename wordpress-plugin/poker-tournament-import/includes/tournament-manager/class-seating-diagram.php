<?php
/**
 * Seating Diagram (tdwp-871.13)
 *
 * Produces a normalized seating layout for a tournament: each table with its
 * seats arranged around a circle (angle + unit-circle x/y for rendering),
 * empty seats marked. The layout builder is pure so it can be unit-tested; a
 * front-end/admin renderer consumes the JSON.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds tournament seating-diagram data.
 */
class TDWP_Seating_Diagram {

	/**
	 * Default seats per table when a table omits max_seats.
	 */
	const DEFAULT_MAX_SEATS = 9;

	/**
	 * Build the seating layout (pure; no DB).
	 *
	 * Every table is expanded to exactly max_seats slots (1..max_seats). A slot
	 * is `occupied` when a seat row exists for it with a player and a non-empty
	 * status. Each slot gets an angle and unit-circle position (seat 1 at top,
	 * clockwise) so a renderer can place chairs around the table.
	 *
	 * @param array $tables Table rows (table_number, max_seats, status, id).
	 * @param array $seats  Seat rows (table_id, seat_number, player_id, status).
	 * @return array Normalized layout: list of tables each with a `seats` list.
	 */
	public static function build_layout( $tables, $seats ) {
		// Index seats by table_id => seat_number => row.
		$by_table = array();
		foreach ( (array) $seats as $seat ) {
			$table_id    = isset( $seat['table_id'] ) ? (int) $seat['table_id'] : 0;
			$seat_number = isset( $seat['seat_number'] ) ? (int) $seat['seat_number'] : 0;
			if ( $table_id && $seat_number ) {
				$by_table[ $table_id ][ $seat_number ] = $seat;
			}
		}

		$layout = array();
		foreach ( (array) $tables as $table ) {
			$table_id    = isset( $table['id'] ) ? (int) $table['id'] : 0;
			$max_seats   = isset( $table['max_seats'] ) ? (int) $table['max_seats'] : self::DEFAULT_MAX_SEATS;
			$max_seats   = $max_seats > 0 ? $max_seats : self::DEFAULT_MAX_SEATS;
			$table_seats = isset( $by_table[ $table_id ] ) ? $by_table[ $table_id ] : array();

			$slots    = array();
			$occupied = 0;
			for ( $number = 1; $number <= $max_seats; $number++ ) {
				$seat_row    = isset( $table_seats[ $number ] ) ? $table_seats[ $number ] : null;
				$player_id   = ( $seat_row && ! empty( $seat_row['player_id'] ) ) ? (int) $seat_row['player_id'] : null;
				$status      = ( $seat_row && ! empty( $seat_row['status'] ) ) ? (string) $seat_row['status'] : 'empty';
				$is_occupied = ( null !== $player_id && 'empty' !== $status );
				if ( $is_occupied ) {
					++$occupied;
				}

				$position = self::seat_position( $number, $max_seats );
				$slots[]  = array(
					'seat_number' => $number,
					'occupied'    => $is_occupied,
					'player_id'   => $player_id,
					'status'      => $status,
					'angle'       => $position['angle'],
					'x'           => $position['x'],
					'y'           => $position['y'],
				);
			}

			$layout[] = array(
				'table_id'       => $table_id,
				'table_number'   => isset( $table['table_number'] ) ? (int) $table['table_number'] : 0,
				'status'         => isset( $table['status'] ) ? (string) $table['status'] : 'active',
				'max_seats'      => $max_seats,
				'occupied_seats' => $occupied,
				'empty_seats'    => $max_seats - $occupied,
				'seats'          => $slots,
			);
		}

		return $layout;
	}

	/**
	 * Compute the angle and unit-circle position for a seat (pure).
	 *
	 * Seat 1 is at the top (12 o'clock); seats increase clockwise.
	 *
	 * @param int $seat_number Seat number (1-based).
	 * @param int $max_seats   Seats at the table.
	 * @return array { angle (degrees, 0=top), x, y } with x/y in [-1,1].
	 */
	public static function seat_position( $seat_number, $max_seats ) {
		$max_seats = max( 1, (int) $max_seats );
		$index     = ( (int) $seat_number - 1 ) % $max_seats;
		$angle_deg = ( 360.0 / $max_seats ) * $index;

		// 0 degrees at the top, clockwise. Screen y grows downward.
		$angle_rad = deg2rad( $angle_deg ) - ( M_PI / 2 );

		return array(
			'angle' => round( $angle_deg, 2 ),
			'x'     => round( cos( $angle_rad ), 4 ),
			'y'     => round( sin( $angle_rad ), 4 ),
		);
	}

	/**
	 * Gather the seating diagram for a tournament from the live tables.
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return array Layout (see build_layout).
	 */
	public static function get_diagram( $tournament_id ) {
		global $wpdb;

		$tournament_id = absint( $tournament_id );
		$tables        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, table_number, max_seats, status FROM {$wpdb->prefix}tdwp_tournament_tables
				 WHERE tournament_id = %d ORDER BY table_number ASC",
				$tournament_id
			),
			ARRAY_A
		);

		if ( empty( $tables ) ) {
			return array();
		}

		$table_ids    = array_map( 'intval', wp_list_pluck( $tables, 'id' ) );
		$placeholders = implode( ', ', array_fill( 0, count( $table_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders from a counted array; values bound below.
		$seats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT table_id, seat_number, player_id, status FROM {$wpdb->prefix}tdwp_tournament_seats
				 WHERE table_id IN ( {$placeholders} )",
				$table_ids
			),
			ARRAY_A
		);

		return self::build_layout( $tables, (array) $seats );
	}
}
