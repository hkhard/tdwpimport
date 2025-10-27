<?php
/**
 * Tournament Manager Database Schema
 *
 * Handles database table creation and management for Phase 1 features:
 * - Tournament templates
 * - Blind schedules and levels
 * - Prize structures
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema management class
 *
 * @since 3.0.0
 */
class TDWP_Database_Schema {

	/**
	 * Database version for schema migrations
	 *
	 * @var string
	 */
	const DB_VERSION = '3.0.0';

	/**
	 * Option name for storing database version
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'tdwp_db_version';

	/**
	 * Create or update database tables
	 *
	 * Called on plugin activation and updates
	 *
	 * @since 3.0.0
	 * @return bool True on success, false on failure
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		// Only run if schema needs update
		if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables_created = array();

		// Create Tournament Templates table
		$tables_created[] = self::create_tournament_templates_table( $wpdb, $charset_collate );

		// Create Blind Schedules table
		$tables_created[] = self::create_blind_schedules_table( $wpdb, $charset_collate );

		// Create Blind Levels table
		$tables_created[] = self::create_blind_levels_table( $wpdb, $charset_collate );

		// Create Prize Structures table
		$tables_created[] = self::create_prize_structures_table( $wpdb, $charset_collate );

		// Check if all tables created successfully
		$success = ! in_array( false, $tables_created, true );

		if ( $success ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
			do_action( 'tdwp_database_schema_updated', self::DB_VERSION );
		}

		return $success;
	}

	/**
	 * Create tournament templates table
	 *
	 * Stores reusable tournament configurations
	 *
	 * @since 3.0.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_tournament_templates_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_templates';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			buy_in decimal(10,2) DEFAULT 0,
			rebuy_cost decimal(10,2) DEFAULT 0,
			rebuy_chips int DEFAULT 0,
			addon_cost decimal(10,2) DEFAULT 0,
			addon_chips int DEFAULT 0,
			starting_chips int DEFAULT 10000,
			rake_percentage decimal(5,2) DEFAULT 0,
			blind_schedule_id bigint(20) UNSIGNED,
			prize_structure_id bigint(20) UNSIGNED,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY blind_schedule_id (blind_schedule_id),
			KEY prize_structure_id (prize_structure_id),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		// Verify table was created
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create blind schedules table
	 *
	 * Stores blind structure configurations
	 *
	 * @since 3.0.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_blind_schedules_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_blind_schedules';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			is_template tinyint(1) DEFAULT 0,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY is_template (is_template),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create blind levels table
	 *
	 * Stores individual blind levels for each schedule
	 *
	 * @since 3.0.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_blind_levels_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_blind_levels';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id bigint(20) UNSIGNED NOT NULL,
			level_number int NOT NULL,
			small_blind int NOT NULL,
			big_blind int NOT NULL,
			ante int DEFAULT 0,
			duration_minutes int DEFAULT 15,
			is_break tinyint(1) DEFAULT 0,
			break_duration_minutes int DEFAULT 0,
			PRIMARY KEY  (id),
			KEY schedule_id (schedule_id),
			KEY level_number (level_number),
			UNIQUE KEY schedule_level (schedule_id, level_number)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create prize structures table
	 *
	 * Stores prize distribution configurations
	 *
	 * @since 3.0.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_prize_structures_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_prize_structures';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			is_template tinyint(1) DEFAULT 0,
			min_players int DEFAULT 1,
			max_players int DEFAULT 999,
			structure_json longtext NOT NULL,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY is_template (is_template),
			KEY min_players (min_players),
			KEY max_players (max_players),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Insert default templates
	 *
	 * Creates default blind schedules and prize structures
	 *
	 * @since 3.0.0
	 * @return bool True on success
	 */
	public static function insert_default_templates() {
		// Check if templates already exist
		$existing_templates = get_option( 'tdwp_default_templates_inserted', false );

		if ( $existing_templates ) {
			return true;
		}

		global $wpdb;

		// Insert default blind schedule templates
		self::insert_default_blind_schedules( $wpdb );

		// Insert default prize structure templates
		self::insert_default_prize_structures( $wpdb );

		// Mark as inserted
		update_option( 'tdwp_default_templates_inserted', true );

		return true;
	}

	/**
	 * Insert default blind schedule templates
	 *
	 * Creates Turbo, Standard, and Deep Stack templates
	 *
	 * @since 3.0.0
	 * @param wpdb $wpdb WordPress database object.
	 */
	private static function insert_default_blind_schedules( $wpdb ) {
		$schedules_table = $wpdb->prefix . 'tdwp_blind_schedules';
		$levels_table    = $wpdb->prefix . 'tdwp_blind_levels';

		// Turbo Structure (10 min levels)
		$wpdb->insert(
			$schedules_table,
			array(
				'name'        => __( 'Turbo', 'poker-tournament-import' ),
				'description' => __( 'Fast-paced structure with 10-minute levels', 'poker-tournament-import' ),
				'is_template' => 1,
			),
			array( '%s', '%s', '%d' )
		);

		$turbo_id = $wpdb->insert_id;

		$turbo_levels = array(
			array( 'level_number' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_number' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_number' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_number' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 25, 'duration_minutes' => 10 ),
			array( 'level_number' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 5, 'is_break' => 1, 'break_duration_minutes' => 5 ),
			array( 'level_number' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 50, 'duration_minutes' => 10 ),
			array( 'level_number' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 50, 'duration_minutes' => 10 ),
			array( 'level_number' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 75, 'duration_minutes' => 10 ),
			array( 'level_number' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 100, 'duration_minutes' => 10 ),
			array( 'level_number' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 10 ),
		);

		foreach ( $turbo_levels as $level ) {
			$level['schedule_id'] = $turbo_id;
			$wpdb->insert( $levels_table, $level, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ) );
		}

		// Standard Structure (15 min levels)
		$wpdb->insert(
			$schedules_table,
			array(
				'name'        => __( 'Standard', 'poker-tournament-import' ),
				'description' => __( 'Balanced structure with 15-minute levels', 'poker-tournament-import' ),
				'is_template' => 1,
			),
			array( '%s', '%s', '%d' )
		);

		$standard_id = $wpdb->insert_id;

		$standard_levels = array(
			array( 'level_number' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_number' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_number' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_number' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_number' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 10, 'is_break' => 1, 'break_duration_minutes' => 10 ),
			array( 'level_number' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 25, 'duration_minutes' => 15 ),
			array( 'level_number' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 50, 'duration_minutes' => 15 ),
			array( 'level_number' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 75, 'duration_minutes' => 15 ),
			array( 'level_number' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 100, 'duration_minutes' => 15 ),
			array( 'level_number' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 15 ),
		);

		foreach ( $standard_levels as $level ) {
			$level['schedule_id'] = $standard_id;
			$wpdb->insert( $levels_table, $level, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ) );
		}

		// Deep Stack Structure (20 min levels)
		$wpdb->insert(
			$schedules_table,
			array(
				'name'        => __( 'Deep Stack', 'poker-tournament-import' ),
				'description' => __( 'Deep structure with 20-minute levels for longer play', 'poker-tournament-import' ),
				'is_template' => 1,
			),
			array( '%s', '%s', '%d' )
		);

		$deep_id = $wpdb->insert_id;

		$deep_levels = array(
			array( 'level_number' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_number' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_number' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_number' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_number' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 15, 'is_break' => 1, 'break_duration_minutes' => 15 ),
			array( 'level_number' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_number' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 25, 'duration_minutes' => 20 ),
			array( 'level_number' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 50, 'duration_minutes' => 20 ),
			array( 'level_number' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 75, 'duration_minutes' => 20 ),
			array( 'level_number' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 20 ),
		);

		foreach ( $deep_levels as $level ) {
			$level['schedule_id'] = $deep_id;
			$wpdb->insert( $levels_table, $level, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ) );
		}
	}

	/**
	 * Insert default prize structure templates
	 *
	 * Creates common prize distribution templates
	 *
	 * @since 3.0.0
	 * @param wpdb $wpdb WordPress database object.
	 */
	private static function insert_default_prize_structures( $wpdb ) {
		$table_name = $wpdb->prefix . 'tdwp_prize_structures';

		// Winner Takes All
		$wpdb->insert(
			$table_name,
			array(
				'name'           => __( 'Winner Takes All', 'poker-tournament-import' ),
				'description'    => __( '100% to 1st place', 'poker-tournament-import' ),
				'is_template'    => 1,
				'min_players'    => 1,
				'max_players'    => 999,
				'structure_json' => wp_json_encode( array( array( 'place' => 1, 'percentage' => 100 ) ) ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		// 50/30/20
		$wpdb->insert(
			$table_name,
			array(
				'name'           => __( '50/30/20 (Top 3)', 'poker-tournament-import' ),
				'description'    => __( '50% / 30% / 20% distribution', 'poker-tournament-import' ),
				'is_template'    => 1,
				'min_players'    => 3,
				'max_players'    => 999,
				'structure_json' => wp_json_encode(
					array(
						array( 'place' => 1, 'percentage' => 50 ),
						array( 'place' => 2, 'percentage' => 30 ),
						array( 'place' => 3, 'percentage' => 20 ),
					)
				),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		// 40/30/20/10
		$wpdb->insert(
			$table_name,
			array(
				'name'           => __( '40/30/20/10 (Top 4)', 'poker-tournament-import' ),
				'description'    => __( '40% / 30% / 20% / 10% distribution', 'poker-tournament-import' ),
				'is_template'    => 1,
				'min_players'    => 4,
				'max_players'    => 999,
				'structure_json' => wp_json_encode(
					array(
						array( 'place' => 1, 'percentage' => 40 ),
						array( 'place' => 2, 'percentage' => 30 ),
						array( 'place' => 3, 'percentage' => 20 ),
						array( 'place' => 4, 'percentage' => 10 ),
					)
				),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Drop all tournament manager tables
	 *
	 * WARNING: This deletes all tournament manager data
	 * Only used during development or uninstall
	 *
	 * @since 3.0.0
	 * @return bool True on success
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'tdwp_blind_levels',
			$wpdb->prefix . 'tdwp_blind_schedules',
			$wpdb->prefix . 'tdwp_prize_structures',
			$wpdb->prefix . 'tdwp_tournament_templates',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name from safe array
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::DB_VERSION_OPTION );
		delete_option( 'tdwp_default_templates_inserted' );

		return true;
	}
}
