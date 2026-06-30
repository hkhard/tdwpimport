<?php
/**
 * Chipset Manager (tdwp-ee1.9)
 *
 * CRUD for reusable chipsets and their chip denominations. The denomination
 * data this manages is the foundation for chip-up / race-off automation
 * (tdwp-ee1.13).
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages chipsets and their chip denominations.
 */
class TDWP_Chipset_Manager {

	/**
	 * Chipsets table name.
	 *
	 * @return string
	 */
	private static function chipsets_table() {
		global $wpdb;
		return $wpdb->prefix . 'tdwp_chipsets';
	}

	/**
	 * Denominations table name.
	 *
	 * @return string
	 */
	private static function denominations_table() {
		global $wpdb;
		return $wpdb->prefix . 'tdwp_chip_denominations';
	}

	/**
	 * Validate a set of denomination rows (pure; no DB).
	 *
	 * Each row must have a positive integer `value`; values must be unique
	 * within the chipset.
	 *
	 * @param array $denominations List of rows with at least a `value` key.
	 * @return true|WP_Error True if valid, WP_Error describing the first problem.
	 */
	public static function validate_denominations( $denominations ) {
		if ( empty( $denominations ) || ! is_array( $denominations ) ) {
			return new WP_Error( 'no_denominations', __( 'A chipset needs at least one denomination', 'poker-tournament-import' ) );
		}

		$seen = array();
		foreach ( $denominations as $denomination ) {
			$value = isset( $denomination['value'] ) ? (int) $denomination['value'] : 0;
			if ( $value <= 0 ) {
				return new WP_Error( 'invalid_value', __( 'Chip denomination values must be positive', 'poker-tournament-import' ) );
			}
			if ( isset( $seen[ $value ] ) ) {
				return new WP_Error( 'duplicate_value', __( 'Chip denomination values must be unique', 'poker-tournament-import' ) );
			}
			$seen[ $value ] = true;
		}

		return true;
	}

	/**
	 * Get the smallest denomination value from a set of rows (pure; no DB).
	 *
	 * Used by chip-up automation to find the lowest chip still in play.
	 *
	 * @param array $denominations List of rows with a `value` key.
	 * @return int Smallest positive value, or 0 if none.
	 */
	public static function get_smallest_denomination( $denominations ) {
		$smallest = 0;
		foreach ( (array) $denominations as $denomination ) {
			$value = isset( $denomination['value'] ) ? (int) $denomination['value'] : 0;
			if ( $value > 0 && ( 0 === $smallest || $value < $smallest ) ) {
				$smallest = $value;
			}
		}
		return $smallest;
	}

	/**
	 * Create a chipset.
	 *
	 * @param string $name        Chipset name.
	 * @param string $description  Optional description.
	 * @return int|WP_Error New chipset ID, or WP_Error.
	 */
	public static function create_chipset( $name, $description = '' ) {
		global $wpdb;

		$name = sanitize_text_field( $name );
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'invalid_name', __( 'Chipset name is required', 'poker-tournament-import' ) );
		}

		$inserted = $wpdb->insert(
			self::chipsets_table(),
			array(
				'name'        => $name,
				'description' => sanitize_text_field( $description ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? new WP_Error( 'insert_failed', __( 'Failed to create chipset', 'poker-tournament-import' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Update a chipset's name/description.
	 *
	 * @param int    $chipset_id  Chipset ID.
	 * @param string $name        New name.
	 * @param string $description New description.
	 * @return true|WP_Error
	 */
	public static function update_chipset( $chipset_id, $name, $description = '' ) {
		global $wpdb;

		$name = sanitize_text_field( $name );
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'invalid_name', __( 'Chipset name is required', 'poker-tournament-import' ) );
		}

		$wpdb->update(
			self::chipsets_table(),
			array(
				'name'        => $name,
				'description' => sanitize_text_field( $description ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $chipset_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Delete a chipset and its denominations.
	 *
	 * @param int $chipset_id Chipset ID.
	 * @return true
	 */
	public static function delete_chipset( $chipset_id ) {
		global $wpdb;

		$chipset_id = absint( $chipset_id );
		$wpdb->delete( self::denominations_table(), array( 'chipset_id' => $chipset_id ), array( '%d' ) );
		$wpdb->delete( self::chipsets_table(), array( 'id' => $chipset_id ), array( '%d' ) );

		return true;
	}

	/**
	 * Get a chipset with its denominations.
	 *
	 * @param int $chipset_id Chipset ID.
	 * @return array|null Chipset array with `denominations`, or null if missing.
	 */
	public static function get_chipset( $chipset_id ) {
		global $wpdb;

		$chipset_id = absint( $chipset_id );
		$chipset    = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::chipsets_table() . ' WHERE id = %d', $chipset_id ),
			ARRAY_A
		);

		if ( ! $chipset ) {
			return null;
		}

		$chipset['denominations'] = self::get_denominations( $chipset_id );
		return $chipset;
	}

	/**
	 * List all chipsets (without denominations).
	 *
	 * @return array[]
	 */
	public static function get_chipsets() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::chipsets_table() . ' ORDER BY name ASC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get a chipset's denominations, ordered by value ascending.
	 *
	 * @param int $chipset_id Chipset ID.
	 * @return array[]
	 */
	public static function get_denominations( $chipset_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::denominations_table() . ' WHERE chipset_id = %d ORDER BY value ASC',
				absint( $chipset_id )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Replace a chipset's denominations (validated, transactional-ish).
	 *
	 * @param int   $chipset_id    Chipset ID.
	 * @param array $denominations List of rows with value/color/quantity.
	 * @return true|WP_Error
	 */
	public static function set_denominations( $chipset_id, $denominations ) {
		global $wpdb;

		$chipset_id = absint( $chipset_id );
		$valid      = self::validate_denominations( $denominations );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// Replace atomically so a failed insert can't leave the chipset with
		// zero denominations (no-op on non-transactional engines).
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( self::denominations_table(), array( 'chipset_id' => $chipset_id ), array( '%d' ) );

		$order = 0;
		foreach ( $denominations as $denomination ) {
			$inserted = $wpdb->insert(
				self::denominations_table(),
				array(
					'chipset_id' => $chipset_id,
					'value'      => (int) $denomination['value'],
					'color'      => sanitize_text_field( isset( $denomination['color'] ) ? $denomination['color'] : '' ),
					'quantity'   => isset( $denomination['quantity'] ) ? absint( $denomination['quantity'] ) : 0,
					'sort_order' => $order,
				),
				array( '%d', '%d', '%s', '%d', '%d' )
			);
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'denomination_insert_failed', __( 'Failed to save chip denominations', 'poker-tournament-import' ) );
			}
			++$order;
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}
}
