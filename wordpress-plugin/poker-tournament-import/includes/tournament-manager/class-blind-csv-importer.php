<?php
/**
 * Blind Schedule CSV Importer
 *
 * Pure parse/validate logic for importing blind levels from CSV.
 * No database calls — import is orchestrated by the calling admin handler.
 *
 * Expected CSV columns (header row required, order flexible):
 *   small_blind, big_blind, ante, duration_minutes, is_break, break_duration_minutes
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Blind CSV Importer
 *
 * Parses and validates a CSV blind-schedule file, returning structured rows
 * suitable for bulk-insertion via TDWP_Blind_Level::bulk_create().
 *
 * @since 3.7.0
 */
class TDWP_Blind_CSV_Importer {

	/**
	 * Parse raw CSV content into validated level rows.
	 *
	 * Pure function — performs no database operations.
	 *
	 * @since 3.7.0
	 *
	 * @param string $content Raw CSV file content.
	 * @return array {
	 *     @type array $rows    Validated, ready-to-insert level data arrays.
	 *     @type array $errors  Per-row error info: [ 'row' => int, 'message' => string ].
	 *     @type int   $valid   Count of accepted rows.
	 *     @type int   $invalid Count of rejected rows.
	 * }
	 */
	public function parse_csv_content( $content ) {
		$lines = explode( "\n", $content );
		$lines = array_values( array_filter( array_map( 'trim', $lines ) ) );

		if ( empty( $lines ) ) {
			return array(
				'rows'    => array(),
				'errors'  => array(),
				'valid'   => 0,
				'invalid' => 0,
			);
		}

		// First line is the header row.
		$raw_headers = str_getcsv( array_shift( $lines ), ',', '"', '\\' );
		$headers     = array_map( 'trim', array_map( 'strtolower', $raw_headers ) );
		$col_map     = $this->map_columns( $headers );

		$rows    = array();
		$errors  = array();
		$valid   = 0;
		$invalid = 0;

		foreach ( $lines as $index => $line ) {
			$row_number = $index + 2; // +2: header consumed + 1-based row numbers.
			$raw        = str_getcsv( $line, ',', '"', '\\' );
			$level_data = $this->extract_row( $raw, $col_map );
			$validation = $this->validate_row( $level_data, $row_number );

			if ( is_wp_error( $validation ) ) {
				$errors[] = array(
					'row'     => $row_number,
					'message' => $validation->get_error_message(),
				);
				$invalid++;
			} else {
				$level_data['row'] = $row_number;
				$rows[]            = $level_data;
				$valid++;
			}
		}

		return array(
			'rows'    => $rows,
			'errors'  => $errors,
			'valid'   => $valid,
			'invalid' => $invalid,
		);
	}

	/**
	 * Validate a single extracted level row.
	 *
	 * Exposed as public so it can be called independently in tests.
	 *
	 * @since 3.7.0
	 *
	 * @param array $data       Level data (string values, not yet cast to int).
	 * @param int   $row_number 1-based row number for error messages.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_row( $data, $row_number ) {
		// duration_minutes: required positive integer.
		$dur = (string) $data['duration_minutes'];
		if ( '' === $dur || ! ctype_digit( $dur ) || 0 === (int) $dur ) {
			return new WP_Error(
				'invalid_duration',
				sprintf(
					/* translators: %d: CSV row number */
					__( 'Row %d: duration_minutes must be a positive integer.', 'poker-tournament-import' ),
					$row_number
				)
			);
		}

		// For non-break levels, small_blind and big_blind are required.
		if ( ! $data['is_break'] ) {
			$sb = (string) $data['small_blind'];
			if ( '' === $sb || ! ctype_digit( $sb ) ) {
				return new WP_Error(
					'invalid_small_blind',
					sprintf(
						/* translators: %d: CSV row number */
						__( 'Row %d: small_blind must be a non-negative integer.', 'poker-tournament-import' ),
						$row_number
					)
				);
			}

			$bb = (string) $data['big_blind'];
			if ( '' === $bb || ! ctype_digit( $bb ) ) {
				return new WP_Error(
					'invalid_big_blind',
					sprintf(
						/* translators: %d: CSV row number */
						__( 'Row %d: big_blind must be a non-negative integer.', 'poker-tournament-import' ),
						$row_number
					)
				);
			}
		}

		// ante: optional but must be a non-negative integer when present.
		$ante = (string) $data['ante'];
		if ( '' !== $ante && ! ctype_digit( $ante ) ) {
			return new WP_Error(
				'invalid_ante',
				sprintf(
					/* translators: %d: CSV row number */
					__( 'Row %d: ante must be a non-negative integer.', 'poker-tournament-import' ),
					$row_number
				)
			);
		}

		// break_duration_minutes: optional but must be non-negative integer when present.
		$bdm = (string) $data['break_duration_minutes'];
		if ( '' !== $bdm && ! ctype_digit( $bdm ) ) {
			return new WP_Error(
				'invalid_break_duration',
				sprintf(
					/* translators: %d: CSV row number */
					__( 'Row %d: break_duration_minutes must be a non-negative integer.', 'poker-tournament-import' ),
					$row_number
				)
			);
		}

		return true;
	}

	/**
	 * Normalize validated string rows to integer-typed arrays for DB insertion.
	 *
	 * Call this on the 'rows' result from parse_csv_content() before passing to
	 * TDWP_Blind_Level::bulk_create().
	 *
	 * @since 3.7.0
	 *
	 * @param array $rows Validated rows (string values).
	 * @return array Rows with integer values; level_order set to position + 1.
	 */
	public function normalize_rows( $rows ) {
		$normalized = array();
		foreach ( $rows as $i => $row ) {
			$normalized[] = array(
				'level_order'            => $i + 1,
				'small_blind'            => absint( $row['small_blind'] ),
				'big_blind'              => absint( $row['big_blind'] ),
				'ante'                   => '' !== (string) $row['ante'] ? absint( $row['ante'] ) : 0,
				'duration_minutes'       => absint( $row['duration_minutes'] ),
				'is_break'               => (int) $row['is_break'],
				'break_duration_minutes' => '' !== (string) $row['break_duration_minutes'] ? absint( $row['break_duration_minutes'] ) : 0,
			);
		}
		return $normalized;
	}

	/**
	 * Map column header strings to raw-row array indices.
	 *
	 * @since 3.7.0
	 *
	 * @param array $headers Lowercase, trimmed header strings.
	 * @return array Map of field name => column index.
	 */
	private function map_columns( $headers ) {
		$map = array();

		foreach ( $headers as $i => $header ) {
			if ( in_array( $header, array( 'small_blind', 'small blind', 'sb' ), true ) ) {
				$map['small_blind'] = $i;
			} elseif ( in_array( $header, array( 'big_blind', 'big blind', 'bb' ), true ) ) {
				$map['big_blind'] = $i;
			} elseif ( 'ante' === $header ) {
				$map['ante'] = $i;
			} elseif ( in_array( $header, array( 'duration_minutes', 'duration', 'duration minutes', 'minutes' ), true ) ) {
				$map['duration_minutes'] = $i;
			} elseif ( in_array( $header, array( 'is_break', 'is break', 'break' ), true ) ) {
				$map['is_break'] = $i;
			} elseif ( in_array( $header, array( 'break_duration_minutes', 'break duration', 'break_duration', 'break minutes' ), true ) ) {
				$map['break_duration_minutes'] = $i;
			}
		}

		return $map;
	}

	/**
	 * Extract one level's data from a raw CSV row array.
	 *
	 * @since 3.7.0
	 *
	 * @param array $raw     Raw values from str_getcsv().
	 * @param array $col_map Column map from map_columns().
	 * @return array Level data with string values (not yet cast).
	 */
	private function extract_row( $raw, $col_map ) {
		$get = function ( $field ) use ( $raw, $col_map ) {
			return isset( $col_map[ $field ] ) && isset( $raw[ $col_map[ $field ] ] )
				? trim( $raw[ $col_map[ $field ] ] )
				: '';
		};

		$is_break_raw = $get( 'is_break' );
		$is_break     = in_array( strtolower( $is_break_raw ), array( '1', 'true', 'yes', 'y' ), true ) ? 1 : 0;

		return array(
			'small_blind'            => $get( 'small_blind' ),
			'big_blind'              => $get( 'big_blind' ),
			'ante'                   => $get( 'ante' ),
			'duration_minutes'       => $get( 'duration_minutes' ),
			'is_break'               => $is_break,
			'break_duration_minutes' => $get( 'break_duration_minutes' ),
		);
	}
}
