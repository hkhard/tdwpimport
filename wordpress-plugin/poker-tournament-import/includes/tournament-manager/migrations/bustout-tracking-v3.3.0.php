<?php
/**
 * Migration: Enhanced Bustout Tracking v3.3.0
 *
 * This migration adds enhanced bustout tracking and player withdrawal functionality:
 * - Precise bustout timing for accurate finish order
 * - Player withdrawal status tracking
 * - Elimination reason categorization
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.3.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the enhanced bustout tracking migration
 *
 * This function can be called manually if needed
 *
 * @since 3.3.0
 * @return bool True on success
 */
function tdwp_run_bustout_tracking_migration_v330() {
	return TDWP_Database_Schema::migrate_enhanced_bustout_tracking();
}

/**
 * Rollback the enhanced bustout tracking migration
 *
 * WARNING: This will remove all bustout tracking data!
 * Only use for development/testing purposes.
 *
 * @since 3.3.0
 * @return bool True on success
 */
function tdwp_rollback_bustout_tracking_migration_v330() {
	global $wpdb;

	$players_table = $wpdb->prefix . 'tdwp_tournament_players';

	// Remove columns (reverse order)
	$columns_to_remove = array(
		'elimination_reason',
		'withdrawal_timestamp',
		'withdrawal_status',
		'bustout_timestamp',
	);

	foreach ( $columns_to_remove as $column ) {
		// Check if column exists before removing
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $players_table . '` LIKE %s', $column ) );

		if ( ! empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` DROP COLUMN {$column}" );
			error_log( "Bustout Tracking Rollback: Removed column {$column} from {$players_table}" );
		}
	}

	// Remove indexes
	$indexes_to_remove = array(
		'elimination_reason',
		'withdrawal_status',
		'bustout_timestamp',
	);

	foreach ( $indexes_to_remove as $index_name ) {
		if ( TDWP_Database_Schema::index_exists( $players_table, $index_name ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` DROP INDEX {$index_name}" );
			error_log( "Bustout Tracking Rollback: Removed index {$index_name} from {$players_table}" );
		}
	}

	// Remove migration flag
	delete_option( 'tdwp_enhanced_bustout_tracking_migration' );

	error_log( 'Bustout Tracking Migration: Rollback completed successfully' );

	return true;
}

/**
 * Verify migration status
 *
 * @since 3.3.0
 * @return array Status information
 */
function tdwp_verify_bustout_tracking_migration_v330() {
	global $wpdb;

	$players_table = $wpdb->prefix . 'tdwp_tournament_players';
	$migration_done = get_option( 'tdwp_enhanced_bustout_tracking_migration', false );

	$required_columns = array(
		'bustout_timestamp',
		'withdrawal_status',
		'withdrawal_timestamp',
		'elimination_reason',
	);

	$required_indexes = array(
		'bustout_timestamp',
		'withdrawal_status',
		'elimination_reason',
	);

	$status = array(
		'migration_flag' => $migration_done,
		'missing_columns' => array(),
		'missing_indexes' => array(),
		'success' => false,
	);

	// Check columns
	foreach ( $required_columns as $column ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $players_table . '` LIKE %s', $column ) );

		if ( empty( $column_exists ) ) {
			$status['missing_columns'][] = $column;
		}
	}

	// Check indexes
	foreach ( $required_indexes as $index_name ) {
		if ( ! TDWP_Database_Schema::index_exists( $players_table, $index_name ) ) {
			$status['missing_indexes'][] = $index_name;
		}
	}

	$status['success'] = $migration_done &&
						   empty( $status['missing_columns'] ) &&
						   empty( $status['missing_indexes'] );

	return $status;
}