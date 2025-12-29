<?php
/**
 * Player Importer Class
 *
 * Handles importing players from CSV/Excel files.
 * Parses file formats and bulk creates player posts.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Player Importer class
 *
 * @since 3.0.0
 */
class TDWP_Player_Importer {

	/**
	 * Player Manager instance
	 *
	 * @var TDWP_Player_Manager
	 */
	private $player_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->player_manager = new TDWP_Player_Manager();
	}

	/**
	 * Parse CSV/Excel file and return player data
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path File path.
	 * @param string $file_name Original file name.
	 * @return array|WP_Error Parsed data on success, WP_Error on failure.
	 */
	public function parse_file( $file_path, $file_name ) {
		// Validate file extension.
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		$allowed_extensions = array( 'csv', 'xls', 'xlsx' );
		if ( ! in_array( $extension, $allowed_extensions, true ) ) {
			return new WP_Error(
				'invalid_file_type',
				sprintf(
					/* translators: %s: allowed file extensions */
					__( 'Invalid file type. Allowed: %s', 'poker-tournament-import' ),
					implode( ', ', $allowed_extensions )
				)
			);
		}

		// Parse based on extension.
		if ( 'csv' === $extension ) {
			$data = $this->parse_csv( $file_path );
		} else {
			// For Excel files, convert to CSV first (simplified approach).
			$data = $this->parse_excel( $file_path );
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Validate and prepare data.
		$result = $this->prepare_import_data( $data );

		return $result;
	}

	/**
	 * Parse CSV file
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path File path.
	 * @return array|WP_Error Parsed data on success, WP_Error on failure.
	 */
	private function parse_csv( $file_path ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );
		// phpcs:enable

		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Failed to read file.', 'poker-tournament-import' ) );
		}

		$lines = explode( "\n", $content );
		$data  = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$row = str_getcsv( $line );
			$data[] = $row;
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'empty_file', __( 'File is empty or unreadable.', 'poker-tournament-import' ) );
		}

		return $data;
	}

	/**
	 * Parse Excel file (simplified - converts to CSV)
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path File path.
	 * @return array|WP_Error Parsed data on success, WP_Error on failure.
	 */
	private function parse_excel( $file_path ) {
		// Note: This is a simplified implementation.
		// For production, consider using PHPSpreadsheet library.
		// For now, return error suggesting CSV format.
		return new WP_Error(
			'excel_not_supported',
			__( 'Excel format not yet supported. Please convert to CSV format.', 'poker-tournament-import' )
		);
	}

	/**
	 * Prepare import data for preview
	 *
	 * @since 3.0.0
	 *
	 * @param array $raw_data Raw parsed data.
	 * @return array Prepared data with validation.
	 */
	private function prepare_import_data( $raw_data ) {
		if ( empty( $raw_data ) ) {
			return array(
				'total'    => 0,
				'valid'    => 0,
				'invalid'  => 0,
				'players'  => array(),
				'errors'   => array(),
			);
		}

		// First row is headers.
		$headers = array_shift( $raw_data );
		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );

		// Map column indices.
		$column_map = $this->map_columns( $headers );

		$players = array();
		$errors  = array();
		$valid   = 0;
		$invalid = 0;

		foreach ( $raw_data as $index => $row ) {
			$row_number = $index + 2; // +2 because we removed headers and rows are 1-indexed.

			// Extract data.
			$player_data = array();

			// Name (required).
			if ( isset( $column_map['name'] ) && isset( $row[ $column_map['name'] ] ) ) {
				$player_data['name'] = trim( $row[ $column_map['name'] ] );
			}

			// Email (optional).
			if ( isset( $column_map['email'] ) && isset( $row[ $column_map['email'] ] ) ) {
				$player_data['email'] = trim( $row[ $column_map['email'] ] );
			}

			// Phone (optional).
			if ( isset( $column_map['phone'] ) && isset( $row[ $column_map['phone'] ] ) ) {
				$player_data['phone'] = trim( $row[ $column_map['phone'] ] );
			}

			// UUID (optional).
			if ( isset( $column_map['uuid'] ) && isset( $row[ $column_map['uuid'] ] ) ) {
				$player_data['uuid'] = trim( $row[ $column_map['uuid'] ] );
			}

			// Validate row.
			$validation = $this->validate_import_row( $player_data, $row_number );

			if ( is_wp_error( $validation ) ) {
				$errors[] = array(
					'row'     => $row_number,
					'message' => $validation->get_error_message(),
					'data'    => $player_data,
				);
				$invalid++;
			} else {
				$player_data['row']       = $row_number;
				$player_data['duplicate'] = $this->check_duplicate( $player_data );
				$players[] = $player_data;
				$valid++;
			}
		}

		return array(
			'total'    => count( $raw_data ),
			'valid'    => $valid,
			'invalid'  => $invalid,
			'players'  => $players,
			'errors'   => $errors,
		);
	}

	/**
	 * Map CSV columns to player fields
	 *
	 * @since 3.0.0
	 *
	 * @param array $headers Column headers.
	 * @return array Column index map.
	 */
	private function map_columns( $headers ) {
		$map = array();

		foreach ( $headers as $index => $header ) {
			$normalized = strtolower( trim( $header ) );

			// Match common variations.
			if ( in_array( $normalized, array( 'name', 'player_name', 'player name', 'full name', 'fullname' ), true ) ) {
				$map['name'] = $index;
			} elseif ( in_array( $normalized, array( 'email', 'e-mail', 'email address', 'player_email' ), true ) ) {
				$map['email'] = $index;
			} elseif ( in_array( $normalized, array( 'phone', 'telephone', 'phone number', 'player_phone' ), true ) ) {
				$map['phone'] = $index;
			} elseif ( in_array( $normalized, array( 'uuid', 'id', 'player_uuid', 'player_id' ), true ) ) {
				$map['uuid'] = $index;
			}
		}

		return $map;
	}

	/**
	 * Validate import row
	 *
	 * @since 3.0.0
	 *
	 * @param array $data       Row data.
	 * @param int   $row_number Row number for error reporting.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_import_row( $data, $row_number ) {
		// Name is required.
		if ( empty( $data['name'] ) ) {
			return new WP_Error(
				'missing_name',
				sprintf(
					/* translators: %d: row number */
					__( 'Row %d: Player name is required.', 'poker-tournament-import' ),
					$row_number
				)
			);
		}

		// Validate email format if provided.
		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			return new WP_Error(
				'invalid_email',
				sprintf(
					/* translators: 1: row number, 2: email address */
					__( 'Row %1$d: Invalid email address: %2$s', 'poker-tournament-import' ),
					$row_number,
					$data['email']
				)
			);
		}

		return true;
	}

	/**
	 * Check for duplicate player
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Player data.
	 * @return array|false Duplicate info if found, false otherwise.
	 */
	private function check_duplicate( $data ) {
		// Check by UUID first.
		if ( ! empty( $data['uuid'] ) ) {
			$existing_id = $this->player_manager->find_by_uuid( $data['uuid'] );
			if ( $existing_id ) {
				return array(
					'type'      => 'uuid',
					'player_id' => $existing_id,
					'message'   => __( 'Player with this UUID already exists', 'poker-tournament-import' ),
				);
			}
		}

		// Check by email.
		if ( ! empty( $data['email'] ) ) {
			$existing_id = $this->player_manager->find_by_email( $data['email'] );
			if ( $existing_id ) {
				return array(
					'type'      => 'email',
					'player_id' => $existing_id,
					'message'   => __( 'Player with this email already exists', 'poker-tournament-import' ),
				);
			}
		}

		return false;
	}

	/**
	 * Import players from prepared data
	 *
	 * @since 3.0.0
	 *
	 * @param array  $players            Player data array.
	 * @param string $duplicate_handling How to handle duplicates (skip|update).
	 * @param string $status             Post status for new players.
	 * @return array|WP_Error Import results on success, WP_Error on failure.
	 */
	public function import_players( $players, $duplicate_handling = 'skip', $status = 'publish' ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $players as $player_data ) {
			$duplicate = isset( $player_data['duplicate'] ) ? $player_data['duplicate'] : false;

			// Set status.
			$player_data['status'] = $status;

			// Handle duplicates.
			if ( $duplicate ) {
				if ( 'skip' === $duplicate_handling ) {
					$results['skipped']++;
					continue;
				} elseif ( 'update' === $duplicate_handling ) {
					// Update existing player.
					$result = $this->player_manager->update( $duplicate['player_id'], $player_data );

					if ( is_wp_error( $result ) ) {
						$results['failed']++;
						$results['errors'][] = array(
							'row'     => isset( $player_data['row'] ) ? $player_data['row'] : 0,
							'message' => $result->get_error_message(),
						);
					} else {
						$results['updated']++;
					}
					continue;
				}
			}

			// Create new player.
			$result = $this->player_manager->create( $player_data );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'row'     => isset( $player_data['row'] ) ? $player_data['row'] : 0,
					'message' => $result->get_error_message(),
				);
			} else {
				$results['created']++;
			}
		}

		return $results;
	}
}
