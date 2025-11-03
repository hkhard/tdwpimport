<?php
/**
 * Tournament Manager Database Schema
 *
 * Handles database table creation and management for Phase 1 & 2 features:
 * - Tournament templates (Phase 1)
 * - Blind schedules and levels (Phase 1)
 * - Prize structures (Phase 1)
 * - Live tournament state (Phase 2)
 * - Tournament events (Phase 2)
 *
 * TD3 Integration (v3.4.0+):
 * - Display system and templates
 * - Event notifications and sound library
 * - Player engagement features
 * - QR code generation and tracking
 * - Multi-screen endpoint management
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
	 * v3.3.0: Enhanced bustout tracking and player withdrawal system
	 * v3.2.1: Multi-hitman bustout support (eliminated_by_player_ids)
	 * v3.2.0: Phase 2 Completion - transactions table and extends existing tables
	 * v3.4.0: TD3 Integration - display, notifications, engagement, QR codes, screens
	 * v3.4.1: Display System Enhancement - live tournament integration and screen management fixes
	 *
	 * @var string
	 */
	const DB_VERSION = '3.4.1';

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

		// Phase 2 Tables
		// Create Tournament Live State table
		$tables_created[] = self::create_tournament_live_state_table( $wpdb, $charset_collate );

		// Create Tournament Events table
		$tables_created[] = self::create_tournament_events_table( $wpdb, $charset_collate );

		// Phase 2 Week 2-3: Table Management
		// Create Tournament Tables table
		$tables_created[] = self::create_tournament_tables_table( $wpdb, $charset_collate );

		// Create Tournament Seats table
		$tables_created[] = self::create_tournament_seats_table( $wpdb, $charset_collate );

		// Phase 1 Beta16: Player Registration
		// Create Tournament Players table
		$tables_created[] = self::create_tournament_players_table( $wpdb, $charset_collate );

		// Phase 2 Completion: Transactions and Financial Tracking
		// Create Transactions table (immutable audit log)
		$tables_created[] = self::create_transactions_table( $wpdb, $charset_collate );

		// TD3 Integration: Display System Tables
		// Create Display Templates table
		$tables_created[] = self::create_display_templates_table( $wpdb, $charset_collate );

		// Create Display Layouts table
		$tables_created[] = self::create_display_layouts_table( $wpdb, $charset_collate );

		// Create Display Screens table
		$tables_created[] = self::create_display_screens_table( $wpdb, $charset_collate );

		// Create Display Tokens table
		$tables_created[] = self::create_display_tokens_table( $wpdb, $charset_collate );

		// Check if all tables created successfully
		$success = ! in_array( false, $tables_created, true );

		if ( $success ) {
			// Run migrations for all versions up to current
			self::run_migrations( $current_version );

			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
			do_action( 'tdwp_database_schema_updated', self::DB_VERSION );
		}

		return $success;
	}

	/**
	 * Reset database version to force re-creation of all tables
	 *
	 * @since 3.4.1
	 */
	public static function reset_database_version() {
		delete_option( self::DB_VERSION_OPTION );
		error_log( 'TDWP Display System: Database version reset - will re-create all tables on next init' );
	}

	/**
	 * Force creation of display system tables with debugging
	 *
	 * @since 3.4.1
	 * @return bool True on success, false on failure
	 */
	public static function force_create_display_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables_created = array();

		error_log('TDWP Display System: Forcing table creation...');

		// Create Display Templates table
		$tables_created[] = self::create_display_templates_table( $wpdb, $charset_collate );
		error_log('TDWP Display System: Templates table creation result: ' . ($tables_created[0] ? 'SUCCESS' : 'FAILED'));

		// Create Display Layouts table
		$tables_created[] = self::create_display_layouts_table( $wpdb, $charset_collate );
		error_log('TDWP Display System: Layouts table creation result: ' . ($tables_created[1] ? 'SUCCESS' : 'FAILED'));

		// Create Display Screens table
		$tables_created[] = self::create_display_screens_table( $wpdb, $charset_collate );
		error_log('TDWP Display System: Screens table creation result: ' . ($tables_created[2] ? 'SUCCESS' : 'FAILED'));

		// Create Display Tokens table
		$tables_created[] = self::create_display_tokens_table( $wpdb, $charset_collate );
		error_log('TDWP Display System: Tokens table creation result: ' . ($tables_created[3] ? 'SUCCESS' : 'FAILED'));

		$success = ! in_array( false, $tables_created, true );

		if ( $success ) {
			error_log('TDWP Display System: All display tables created successfully');
		} else {
			error_log('TDWP Display System: Some display tables failed to create');
		}

		return $success;
	}

	/**
	 * Run migrations for specific versions
	 *
	 * @since 3.4.0
	 * @param string $from_version Starting version.
	 */
	private static function run_migrations( $from_version ) {
		// Run v3.3.0 migrations for enhanced bustout tracking
		if ( version_compare( $from_version, '3.3.0', '<' ) ) {
			self::migrate_enhanced_bustout_tracking();
		}

		// Run v3.4.0 TD3 integration migrations
		if ( version_compare( $from_version, '3.4.0', '<' ) ) {
			// Include TD3 migration class
			require_once __DIR__ . '/class-td3-migration.php';

			// Run TD3 migrations
			$td3_migration_success = TDWP_TD3_Migration::run_migrations();

			if ( ! $td3_migration_success ) {
				error_log( 'TD3 migration failed during database schema update' );
			}
		}

		// Run v3.4.1 Display System Enhancement migrations
		if ( version_compare( $from_version, '3.4.1', '<' ) ) {
			self::migrate_display_screens_v341();
		}
	}

	/**
	 * Migrate display screens table to v3.4.1
	 *
	 * Adds missing fields for live tournament integration
	 *
	 * @since 3.4.1
	 * @return bool True on success
	 */
	private static function migrate_display_screens_v341() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'poker_display_screens';

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		if ( ! $table_exists ) {
			return true; // Table doesn't exist, will be created with new schema
		}

		// Add missing columns if they don't exist
		$columns_to_add = array(
			'tournament_id'      => "ADD COLUMN tournament_id BIGINT(20) UNSIGNED NULL AFTER screen_name",
			'refresh_rate'       => "ADD COLUMN refresh_rate INT NOT NULL DEFAULT 30 AFTER location",
			'screen_description' => "ADD COLUMN screen_description TEXT NULL AFTER refresh_rate",
			'is_active'         => "ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER screen_description"
		);

		$success = true;

		foreach ( $columns_to_add as $column_name => $alter_sql ) {
			// Check if column already exists
			$column_exists = $wpdb->get_var(
				"SHOW COLUMNS FROM {$table_name} LIKE '{$column_name}'"
			);

			if ( ! $column_exists ) {
				$result = $wpdb->query( "ALTER TABLE {$table_name} {$alter_sql}" );
				if ( $result === false ) {
					error_log( "Failed to add column {$column_name} to display_screens table" );
					$success = false;
				}
			}
		}

		// Add new enum value for 'tournament' screen_type if it doesn't exist
		$enum_check = $wpdb->get_var(
			"SHOW COLUMNS FROM {$table_name} WHERE Field = 'screen_type' AND Type LIKE '%tournament%'"
		);

		if ( ! $enum_check ) {
			// Modify ENUM to include 'tournament'
			$result = $wpdb->query(
				"ALTER TABLE {$table_name} MODIFY COLUMN screen_type ENUM('clock','rankings','prizes','seating','custom','tournament') NOT NULL DEFAULT 'custom'"
			);
			if ( $result === false ) {
				error_log( "Failed to update screen_type enum to include 'tournament'" );
				$success = false;
			}
		}

		// Add indexes if they don't exist
		$indexes_to_add = array(
			'idx_tournament' => "ADD INDEX idx_tournament (tournament_id)",
			'idx_active'    => "ADD INDEX idx_active (is_active)"
		);

		foreach ( $indexes_to_add as $index_name => $index_sql ) {
			$index_exists = $wpdb->get_var( "SHOW INDEX FROM {$table_name} WHERE Key_name = '{$index_name}'" );
			if ( ! $index_exists ) {
				$result = $wpdb->query( "ALTER TABLE {$table_name} {$index_sql}" );
				if ( $result === false ) {
					error_log( "Failed to add index {$index_name} to display_screens table" );
					$success = false;
				}
			}
		}

		// Add foreign key for tournament_id if it doesn't exist
		$fk_exists = $wpdb->get_var(
			"SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '{$table_name}'
			 AND COLUMN_NAME = 'tournament_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
		);

		if ( ! $fk_exists ) {
			$result = $wpdb->query(
				"ALTER TABLE {$table_name} ADD CONSTRAINT fk_display_screens_tournament
					FOREIGN KEY (tournament_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL"
			);
			if ( $result === false ) {
				error_log( "Failed to add foreign key constraint for tournament_id" );
				// Don't mark as failure as FK constraint may fail on some systems
			}
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
			allow_reentry tinyint(1) DEFAULT 0,
			reentry_cost decimal(10,2) DEFAULT 0,
			reentry_chips int DEFAULT 0,
			reentry_limit int DEFAULT 0,
			reentry_until_level int DEFAULT 0,
			rebuy_until_level int DEFAULT 0,
			rebuy_chip_threshold int DEFAULT 0,
			rebuy_limit_per_player int DEFAULT 0,
			addon_at_level int DEFAULT 0,
			addon_until_level int DEFAULT 0,
			bounty_type varchar(20) DEFAULT 'none',
			bounty_amount decimal(10,2) DEFAULT 0,
			bounty_percentage decimal(5,2) DEFAULT 50,
			late_reg_until_level int DEFAULT 0,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY blind_schedule_id (blind_schedule_id),
			KEY prize_structure_id (prize_structure_id),
			KEY created_by (created_by),
			KEY bounty_type (bounty_type),
			KEY allow_reentry (allow_reentry)
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
			level_duration int DEFAULT 15,
			break_frequency int DEFAULT 0,
			break_duration int DEFAULT 10,
			is_default_turbo tinyint(1) DEFAULT 0,
			is_default_regular tinyint(1) DEFAULT 0,
			is_default_deep tinyint(1) DEFAULT 0,
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
			level_order int NOT NULL,
			small_blind int NOT NULL,
			big_blind int NOT NULL,
			ante int DEFAULT 0,
			duration_minutes int DEFAULT 15,
			is_break tinyint(1) DEFAULT 0,
			break_duration_minutes int DEFAULT 0,
			PRIMARY KEY  (id),
			KEY schedule_id (schedule_id),
			KEY level_order (level_order),
			UNIQUE KEY schedule_level (schedule_id, level_order)
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
	 * Create tournament live state table (Phase 2)
	 *
	 * Stores real-time tournament state for active tournaments
	 *
	 * @since 3.1.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_tournament_live_state_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_live_state';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED NOT NULL,
			template_id bigint(20) UNSIGNED,
			status varchar(20) NOT NULL DEFAULT 'pending',
			current_level int DEFAULT 1,
			time_remaining int DEFAULT 0,
			started_at datetime,
			paused_at datetime,
			break_until datetime,
			completed_at datetime,
			total_players int DEFAULT 0,
			remaining_players int DEFAULT 0,
			busted_players_count int DEFAULT 0,
			total_rebuys int DEFAULT 0,
			total_addons int DEFAULT 0,
			current_chips_total bigint DEFAULT 0,
			prize_pool decimal(10,2) DEFAULT 0,
			next_payout_position int,
			is_practice tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_id (tournament_id),
			KEY template_id (template_id),
			KEY status (status),
			KEY started_at (started_at),
			KEY is_practice (is_practice)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create tournament events table (Phase 2)
	 *
	 * Stores event log for tournament actions and audit trail
	 *
	 * @since 3.1.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_tournament_events_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_events';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(50) NOT NULL,
			user_id bigint(20) UNSIGNED,
			event_data longtext,
			is_automated tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tournament_id (tournament_id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY is_automated (is_automated)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create tournament tables table (Phase 2 Week 2-3)
	 *
	 * Stores poker tables for live tournament management
	 *
	 * @since 3.1.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_tournament_tables_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_tables';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED NOT NULL,
			table_number int NOT NULL,
			max_seats int NOT NULL DEFAULT 9,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tournament_id (tournament_id),
			KEY status (status),
			KEY tournament_status (tournament_id, status)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create tournament seats table (Phase 2 Week 2-3)
	 *
	 * Stores seat assignments for poker tables
	 *
	 * @since 3.1.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_tournament_seats_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_seats';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			table_id bigint(20) UNSIGNED NOT NULL,
			seat_number int NOT NULL,
			player_id bigint(20) UNSIGNED DEFAULT NULL,
			registration_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'empty',
			assigned_at datetime DEFAULT NULL,
			moved_from_table_id bigint(20) UNSIGNED DEFAULT NULL,
			moved_from_seat_number int DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY table_seat (table_id, seat_number),
			KEY player_id (player_id),
			KEY registration_id (registration_id),
			KEY table_id (table_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create tournament players table (Phase 1 Beta16)
	 *
	 * Stores player registrations for tournaments
	 *
	 * @since 3.1.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_tournament_players_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_tournament_players';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED NOT NULL,
			player_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'registered',
			registration_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			paid_amount decimal(10,2) DEFAULT 0,
			rebuys_count int DEFAULT 0,
			addons_count int DEFAULT 0,
			seat_assignment varchar(50) DEFAULT NULL,
			finish_position int DEFAULT NULL,
			prize_amount decimal(10,2) DEFAULT 0,
			entry_number int DEFAULT 1,
			is_reentry tinyint(1) DEFAULT 0,
			original_entry_id bigint(20) UNSIGNED DEFAULT NULL,
			reentry_count int DEFAULT 0,
			eliminations_count int DEFAULT 0,
			elimination_time datetime DEFAULT NULL,
			eliminated_by_player_id bigint(20) UNSIGNED DEFAULT NULL,
			eliminated_by_player_ids TEXT DEFAULT NULL,
			bounty_amount decimal(10,2) DEFAULT 0,
			bounties_earned decimal(10,2) DEFAULT 0,
			bounties_from_players int DEFAULT 0,
			chip_count int DEFAULT 0,
			notes text,
			bustout_timestamp datetime DEFAULT NULL,
			withdrawal_status varchar(20) DEFAULT 'active',
			withdrawal_timestamp datetime DEFAULT NULL,
			elimination_reason varchar(20) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_player (tournament_id, player_id, entry_number),
			KEY tournament_id (tournament_id),
			KEY player_id (player_id),
			KEY status (status),
			KEY finish_position (finish_position),
			KEY original_entry_id (original_entry_id),
			KEY eliminated_by_player_id (eliminated_by_player_id),
			KEY is_reentry (is_reentry),
			KEY bustout_timestamp (bustout_timestamp),
			KEY withdrawal_status (withdrawal_status),
			KEY elimination_reason (elimination_reason)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create transactions table (Phase 2 Completion)
	 *
	 * Immutable audit log for all tournament financial operations:
	 * - Player bust-outs with finish position
	 * - Rebuys and add-ons
	 * - Chip count adjustments
	 * - Late registrations
	 *
	 * This table is append-only (no UPDATE or DELETE operations allowed)
	 * to maintain complete audit trail for compliance.
	 *
	 * @since 3.2.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_transactions_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_transactions';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED NOT NULL,
			player_id bigint(20) UNSIGNED NOT NULL,
			transaction_type varchar(50) NOT NULL,
			amount decimal(10,2) DEFAULT 0,
			chips int DEFAULT 0,
			reason varchar(500) DEFAULT NULL,
			actor_user_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tournament_id (tournament_id),
			KEY player_id (player_id),
			KEY transaction_type (transaction_type),
			KEY created_at (created_at),
			KEY tournament_type (tournament_id, transaction_type)
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
				'name'              => __( 'Turbo', 'poker-tournament-import' ),
				'description'       => __( 'Fast-paced structure with 10-minute levels', 'poker-tournament-import' ),
				'level_duration'    => 10,
				'break_frequency'   => 4,
				'break_duration'    => 5,
				'is_default_turbo'  => 1,
				'is_template'       => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		$turbo_id = $wpdb->insert_id;

		$turbo_levels = array(
			array( 'level_order' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_order' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_order' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 10 ),
			array( 'level_order' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 25, 'duration_minutes' => 10 ),
			array( 'level_order' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 5, 'is_break' => 1, 'break_duration_minutes' => 5 ),
			array( 'level_order' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 50, 'duration_minutes' => 10 ),
			array( 'level_order' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 50, 'duration_minutes' => 10 ),
			array( 'level_order' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 75, 'duration_minutes' => 10 ),
			array( 'level_order' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 100, 'duration_minutes' => 10 ),
			array( 'level_order' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 10 ),
		);

		foreach ( $turbo_levels as $level ) {
			$level['schedule_id'] = $turbo_id;
			$wpdb->insert( $levels_table, $level, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ) );
		}

		// Standard Structure (15 min levels)
		$wpdb->insert(
			$schedules_table,
			array(
				'name'                => __( 'Standard', 'poker-tournament-import' ),
				'description'         => __( 'Balanced structure with 15-minute levels', 'poker-tournament-import' ),
				'level_duration'      => 15,
				'break_frequency'     => 4,
				'break_duration'      => 10,
				'is_default_regular'  => 1,
				'is_template'         => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		$standard_id = $wpdb->insert_id;

		$standard_levels = array(
			array( 'level_order' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_order' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_order' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_order' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 0, 'duration_minutes' => 15 ),
			array( 'level_order' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 10, 'is_break' => 1, 'break_duration_minutes' => 10 ),
			array( 'level_order' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 25, 'duration_minutes' => 15 ),
			array( 'level_order' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 50, 'duration_minutes' => 15 ),
			array( 'level_order' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 75, 'duration_minutes' => 15 ),
			array( 'level_order' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 100, 'duration_minutes' => 15 ),
			array( 'level_order' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 15 ),
		);

		foreach ( $standard_levels as $level ) {
			$level['schedule_id'] = $standard_id;
			$wpdb->insert( $levels_table, $level, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ) );
		}

		// Deep Stack Structure (20 min levels)
		$wpdb->insert(
			$schedules_table,
			array(
				'name'             => __( 'Deep Stack', 'poker-tournament-import' ),
				'description'      => __( 'Deep structure with 20-minute levels for longer play', 'poker-tournament-import' ),
				'level_duration'   => 20,
				'break_frequency'  => 4,
				'break_duration'   => 15,
				'is_default_deep'  => 1,
				'is_template'      => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		$deep_id = $wpdb->insert_id;

		$deep_levels = array(
			array( 'level_order' => 1, 'small_blind' => 25, 'big_blind' => 50, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_order' => 2, 'small_blind' => 50, 'big_blind' => 100, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_order' => 3, 'small_blind' => 75, 'big_blind' => 150, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_order' => 4, 'small_blind' => 100, 'big_blind' => 200, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_order' => 5, 'small_blind' => 0, 'big_blind' => 0, 'ante' => 0, 'duration_minutes' => 15, 'is_break' => 1, 'break_duration_minutes' => 15 ),
			array( 'level_order' => 6, 'small_blind' => 150, 'big_blind' => 300, 'ante' => 0, 'duration_minutes' => 20 ),
			array( 'level_order' => 7, 'small_blind' => 200, 'big_blind' => 400, 'ante' => 25, 'duration_minutes' => 20 ),
			array( 'level_order' => 8, 'small_blind' => 300, 'big_blind' => 600, 'ante' => 50, 'duration_minutes' => 20 ),
			array( 'level_order' => 9, 'small_blind' => 400, 'big_blind' => 800, 'ante' => 75, 'duration_minutes' => 20 ),
			array( 'level_order' => 10, 'small_blind' => 500, 'big_blind' => 1000, 'ante' => 100, 'duration_minutes' => 20 ),
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
	 * Migrate existing Phase 1 tables to match schema updates
	 *
	 * Adds missing columns to existing installations and renames columns.
	 * Safe to run multiple times - checks if columns exist before altering.
	 *
	 * @since 3.0.0
	 * @return bool True on success
	 */
	public static function migrate_schema() {
		global $wpdb;

		$schedules_table = $wpdb->prefix . 'tdwp_blind_schedules';
		$levels_table    = $wpdb->prefix . 'tdwp_blind_levels';

		// Check if migration already done
		$migration_done = get_option( 'tdwp_phase1_schema_migration_v1', false );
		if ( $migration_done ) {
			return true;
		}

		// Migrate blind_schedules table - add missing columns
		$columns_to_add = array(
			'level_duration'     => 'ADD COLUMN level_duration int DEFAULT 15 AFTER description',
			'break_frequency'    => 'ADD COLUMN break_frequency int DEFAULT 0 AFTER level_duration',
			'break_duration'     => 'ADD COLUMN break_duration int DEFAULT 10 AFTER break_frequency',
			'is_default_turbo'   => 'ADD COLUMN is_default_turbo tinyint(1) DEFAULT 0 AFTER break_duration',
			'is_default_regular' => 'ADD COLUMN is_default_regular tinyint(1) DEFAULT 0 AFTER is_default_turbo',
			'is_default_deep'    => 'ADD COLUMN is_default_deep tinyint(1) DEFAULT 0 AFTER is_default_regular',
		);

		foreach ( $columns_to_add as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column existence check
			$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $schedules_table . '` LIKE %s', $column ) );

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
				$wpdb->query( "ALTER TABLE `{$schedules_table}` {$sql}" );
				error_log( "Phase 1 Migration: Added column {$column} to {$schedules_table}" );
			}
		}

		// Migrate blind_levels table - rename level_number to level_order
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column existence check
		$has_level_number = $wpdb->get_results( "SHOW COLUMNS FROM `{$levels_table}` LIKE 'level_number'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column existence check
		$has_level_order = $wpdb->get_results( "SHOW COLUMNS FROM `{$levels_table}` LIKE 'level_order'" );

		if ( ! empty( $has_level_number ) && empty( $has_level_order ) ) {
			// Rename column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
			$wpdb->query( "ALTER TABLE `{$levels_table}` CHANGE `level_number` `level_order` int NOT NULL" );

			// Update index names
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
			$wpdb->query( "ALTER TABLE `{$levels_table}` DROP INDEX level_number" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
			$wpdb->query( "ALTER TABLE `{$levels_table}` ADD INDEX level_order (level_order)" );

			// Update unique key
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
			$wpdb->query( "ALTER TABLE `{$levels_table}` DROP INDEX schedule_level" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
			$wpdb->query( "ALTER TABLE `{$levels_table}` ADD UNIQUE KEY schedule_level (schedule_id, level_order)" );

			error_log( "Phase 1 Migration: Renamed level_number to level_order in {$levels_table}" );
		}

		// Mark migration as complete
		update_option( 'tdwp_phase1_schema_migration_v1', true );
		error_log( 'Phase 1 Migration: Schema migration completed successfully' );

		return true;
	}

	/**
	 * Migrate Phase 2 Beta20 - Financial Policy Schema
	 *
	 * Adds re-entry, rebuy policies, bounty tracking to existing installations
	 *
	 * @since 3.1.0
	 * @return bool True on success
	 */
	public static function migrate_beta20_financial_policy() {
		global $wpdb;

		// Check if migration already done
		$migration_done = get_option( 'tdwp_beta20_financial_policy_migration', false );
		if ( $migration_done ) {
			return true;
		}

		$templates_table = $wpdb->prefix . 'tdwp_tournament_templates';
		$players_table   = $wpdb->prefix . 'tdwp_tournament_players';

		// Add columns to tournament_templates
		$template_columns = array(
			'allow_reentry'          => 'ADD COLUMN allow_reentry tinyint(1) DEFAULT 0 AFTER prize_structure_id',
			'reentry_cost'           => 'ADD COLUMN reentry_cost decimal(10,2) DEFAULT 0 AFTER allow_reentry',
			'reentry_chips'          => 'ADD COLUMN reentry_chips int DEFAULT 0 AFTER reentry_cost',
			'reentry_limit'          => 'ADD COLUMN reentry_limit int DEFAULT 0 AFTER reentry_chips',
			'reentry_until_level'    => 'ADD COLUMN reentry_until_level int DEFAULT 0 AFTER reentry_limit',
			'rebuy_until_level'      => 'ADD COLUMN rebuy_until_level int DEFAULT 0 AFTER reentry_until_level',
			'rebuy_chip_threshold'   => 'ADD COLUMN rebuy_chip_threshold int DEFAULT 0 AFTER rebuy_until_level',
			'rebuy_limit_per_player' => 'ADD COLUMN rebuy_limit_per_player int DEFAULT 0 AFTER rebuy_chip_threshold',
			'addon_at_level'         => 'ADD COLUMN addon_at_level int DEFAULT 0 AFTER rebuy_limit_per_player',
			'addon_until_level'      => 'ADD COLUMN addon_until_level int DEFAULT 0 AFTER addon_at_level',
			'bounty_type'            => 'ADD COLUMN bounty_type varchar(20) DEFAULT \'none\' AFTER addon_until_level',
			'bounty_amount'          => 'ADD COLUMN bounty_amount decimal(10,2) DEFAULT 0 AFTER bounty_type',
			'bounty_percentage'      => 'ADD COLUMN bounty_percentage decimal(5,2) DEFAULT 50 AFTER bounty_amount',
			'late_reg_until_level'   => 'ADD COLUMN late_reg_until_level int DEFAULT 0 AFTER bounty_percentage',
		);

		foreach ( $template_columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $templates_table . '` LIKE %s', $column ) );

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$templates_table}` {$sql}" );
				error_log( "Beta20 Migration: Added column {$column} to {$templates_table}" );
			}
		}

		// Add indexes to tournament_templates
		if ( ! self::index_exists( $templates_table, 'bounty_type' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$templates_table}` ADD INDEX bounty_type (bounty_type)" );
			error_log( "Beta20 Migration: Added index bounty_type to {$templates_table}" );
		}

		if ( ! self::index_exists( $templates_table, 'allow_reentry' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$templates_table}` ADD INDEX allow_reentry (allow_reentry)" );
			error_log( "Beta20 Migration: Added index allow_reentry to {$templates_table}" );
		}

		// Add columns to tournament_players
		$player_columns = array(
			'entry_number'            => 'ADD COLUMN entry_number int DEFAULT 1 AFTER prize_amount',
			'is_reentry'              => 'ADD COLUMN is_reentry tinyint(1) DEFAULT 0 AFTER entry_number',
			'original_entry_id'       => 'ADD COLUMN original_entry_id bigint(20) UNSIGNED DEFAULT NULL AFTER is_reentry',
			'reentry_count'           => 'ADD COLUMN reentry_count int DEFAULT 0 AFTER original_entry_id',
			'eliminations_count'      => 'ADD COLUMN eliminations_count int DEFAULT 0 AFTER reentry_count',
			'elimination_time'        => 'ADD COLUMN elimination_time datetime DEFAULT NULL AFTER eliminations_count',
			'eliminated_by_player_id' => 'ADD COLUMN eliminated_by_player_id bigint(20) UNSIGNED DEFAULT NULL AFTER elimination_time',
			'eliminated_by_player_ids' => 'ADD COLUMN eliminated_by_player_ids TEXT DEFAULT NULL AFTER eliminated_by_player_id',
			'bounty_amount'           => 'ADD COLUMN bounty_amount decimal(10,2) DEFAULT 0 AFTER eliminated_by_player_ids',
			'bounties_earned'         => 'ADD COLUMN bounties_earned decimal(10,2) DEFAULT 0 AFTER bounty_amount',
			'bounties_from_players'   => 'ADD COLUMN bounties_from_players int DEFAULT 0 AFTER bounties_earned',
			'chip_count'              => 'ADD COLUMN chip_count int DEFAULT 0 AFTER bounties_from_players',
		);

		foreach ( $player_columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $players_table . '` LIKE %s', $column ) );

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$players_table}` {$sql}" );
				error_log( "Beta20 Migration: Added column {$column} to {$players_table}" );
			}
		}

		// Update unique key on tournament_players to include entry_number
		// Check if old unique key exists and has only 2 columns (needs upgrade)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old_key_info = $wpdb->get_results( "SHOW INDEX FROM `{$players_table}` WHERE Key_name = 'tournament_player'" );

		// If old key exists and doesn't include entry_number, drop and recreate
		$needs_key_update = false;
		if ( ! empty( $old_key_info ) ) {
			$has_entry_number = false;
			foreach ( $old_key_info as $key_column ) {
				if ( 'entry_number' === $key_column->Column_name ) {
					$has_entry_number = true;
					break;
				}
			}

			if ( ! $has_entry_number ) {
				// Drop old key (it doesn't have entry_number)
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$players_table}` DROP INDEX tournament_player" );
				error_log( "Beta20 Migration: Dropped old tournament_player unique key from {$players_table}" );
				$needs_key_update = true;
			}
		} else {
			// Key doesn't exist at all
			$needs_key_update = true;
		}

		// Add new unique key if needed
		if ( $needs_key_update ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` ADD UNIQUE KEY tournament_player (tournament_id, player_id, entry_number)" );
			error_log( "Beta20 Migration: Added new tournament_player unique key with entry_number to {$players_table}" );
		}

		// Add new indexes
		if ( ! self::index_exists( $players_table, 'original_entry_id' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` ADD INDEX original_entry_id (original_entry_id)" );
			error_log( "Beta20 Migration: Added index original_entry_id to {$players_table}" );
		}

		if ( ! self::index_exists( $players_table, 'eliminated_by_player_id' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` ADD INDEX eliminated_by_player_id (eliminated_by_player_id)" );
			error_log( "Beta20 Migration: Added index eliminated_by_player_id to {$players_table}" );
		}

		if ( ! self::index_exists( $players_table, 'is_reentry' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$players_table}` ADD INDEX is_reentry (is_reentry)" );
			error_log( "Beta20 Migration: Added index is_reentry to {$players_table}" );
		}

		// Mark migration as complete
		update_option( 'tdwp_beta20_financial_policy_migration', true );
		error_log( 'Beta20 Migration: Financial policy schema migration completed successfully' );

		return true;
	}

	/**
	 * Migrate Beta Seating - Registration ID Support
	 *
	 * Adds registration_id column to seats table to properly track which
	 * specific tournament entry is seated (fixes re-entry seating bug)
	 *
	 * @since 3.2.0
	 * @return bool True on success
	 */
	public static function migrate_beta_seating_registration_id() {
		global $wpdb;

		// Check if migration already done
		$migration_done = get_option( 'tdwp_beta_seating_registration_id', false );
		if ( $migration_done ) {
			return true;
		}

		$seats_table   = $wpdb->prefix . 'tdwp_tournament_seats';
		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		// Add registration_id column
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $seats_table . '` LIKE %s', 'registration_id' ) );

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE `{$seats_table}`
				ADD COLUMN registration_id bigint(20) UNSIGNED DEFAULT NULL AFTER player_id,
				ADD INDEX registration_id (registration_id)"
			);
			error_log( 'Seating Migration: Added registration_id column to ' . $seats_table );
		}

		// Migrate existing data: Find registration_id for each seated player
		// For players with only 1 entry, this is straightforward
		// For players with re-entries, assume entry_number=1 was seated (best guess)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$seats_table} seats
			INNER JOIN {$players_table} players
				ON seats.player_id = players.player_id
				AND players.entry_number = 1
			SET seats.registration_id = players.id
			WHERE seats.registration_id IS NULL
			AND seats.player_id IS NOT NULL"
		);

		update_option( 'tdwp_beta_seating_registration_id', true );
		error_log( 'Seating Migration: Added registration_id column and migrated existing seats' );

		return true;
	}

	/**
	 * Migrate Enhanced Bustout Tracking (v3.3.0)
	 *
	 * Adds bustout tracking and withdrawal system columns to existing installations:
	 * - bustout_timestamp: Precise timing of bustout for accurate order tracking
	 * - withdrawal_status: Track player withdrawal status (active, withdrawn, declined_reentry)
	 * - withdrawal_timestamp: When player chose to withdraw
	 * - elimination_reason: Reason for elimination (bustout, withdrawn, disqualified)
	 *
	 * @since 3.3.0
	 * @return bool True on success
	 */
	public static function migrate_enhanced_bustout_tracking() {
		global $wpdb;

		// Check if migration already done
		$migration_done = get_option( 'tdwp_enhanced_bustout_tracking_migration', false );
		if ( $migration_done ) {
			return true;
		}

		$players_table = $wpdb->prefix . 'tdwp_tournament_players';

		// Add columns for enhanced bustout tracking
		$columns_to_add = array(
			'bustout_timestamp'    => 'ADD COLUMN bustout_timestamp datetime DEFAULT NULL AFTER notes',
			'withdrawal_status'    => "ADD COLUMN withdrawal_status varchar(20) DEFAULT 'active' AFTER bustout_timestamp",
			'withdrawal_timestamp' => 'ADD COLUMN withdrawal_timestamp datetime DEFAULT NULL AFTER withdrawal_status',
			'elimination_reason'   => 'ADD COLUMN elimination_reason varchar(20) DEFAULT NULL AFTER withdrawal_timestamp',
		);

		foreach ( $columns_to_add as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column_exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $players_table . '` LIKE %s', $column ) );

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$players_table}` {$sql}" );
				error_log( "Enhanced Bustout Tracking Migration: Added column {$column} to {$players_table}" );
			}
		}

		// Add indexes for new columns
		$indexes_to_add = array(
			'bustout_timestamp' => 'bustout_timestamp',
			'withdrawal_status' => 'withdrawal_status',
			'elimination_reason' => 'elimination_reason',
		);

		foreach ( $indexes_to_add as $column => $index_name ) {
			if ( ! self::index_exists( $players_table, $index_name ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$players_table}` ADD INDEX {$index_name} ({$column})" );
				error_log( "Enhanced Bustout Tracking Migration: Added index {$index_name} to {$players_table}" );
			}
		}

		// Mark migration as complete
		update_option( 'tdwp_enhanced_bustout_tracking_migration', true );
		error_log( 'Enhanced Bustout Tracking Migration: Schema migration completed successfully' );

		return true;
	}

	/**
	 * Check if an index exists on a table
	 *
	 * @since 3.1.0
	 * @param string $table Table name (with prefix).
	 * @param string $index_name Index name.
	 * @return bool True if index exists, false otherwise.
	 */
	private static function index_exists( $table, $index_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'" );

		return ! empty( $indexes );
	}

	/**
	 * Create Display Templates table
	 *
	 * TD3 Integration: Display System (v3.4.0+)
	 * Template definitions with token support for tournament displays
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb Database instance.
	 * @param string $charset_collate Charset collation.
	 * @return bool True on success, false on failure
	 */
	private static function create_display_templates_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'poker_display_templates';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			template_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id BIGINT(20) UNSIGNED NULL,
			template_name VARCHAR(255) NOT NULL,
			template_type ENUM('clock','rankings','prizes','seating','rules','custom') NOT NULL DEFAULT 'custom',
			html_template LONGTEXT NOT NULL,
			css_styles LONGTEXT NULL,
			tokens_used LONGTEXT NULL,
			is_default BOOLEAN NOT NULL DEFAULT FALSE,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (template_id),
			KEY idx_tournament_type (tournament_id, template_type),
			KEY idx_type_default (template_type, is_default),
			FOREIGN KEY (tournament_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Check if table exists before creation
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		if ( $table_exists ) {
			error_log( "TDWP Display System: Table {$table_name} already exists" );
			return true;
		}

		error_log( "TDWP Display System: Creating table {$table_name}" );

		$result = dbDelta( $sql );
		error_log( "TDWP Display System: dbDelta result for templates: " . print_r( $result, true ) );

		// Verify table was created
		$table_created = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		$success = ! empty( $table_created );

		error_log( "TDWP Display System: Table {$table_name} creation " . ( $success ? 'SUCCESS' : 'FAILED' ) );

		return $success;
	}

	/**
	 * Create Display Layouts table
	 *
	 * TD3 Integration: Display System (v3.4.0+)
	 * Drag-and-drop layout configurations for tournament displays
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb Database instance.
	 * @param string $charset_collate Charset collation.
	 * @return bool True on success, false on failure
	 */
	private static function create_display_layouts_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'poker_display_layouts';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			layout_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id BIGINT(20) UNSIGNED NOT NULL,
			layout_name VARCHAR(255) NOT NULL,
			screen_size VARCHAR(50) NULL,
			grid_config LONGTEXT NULL,
			component_positions LONGTEXT NULL,
			breakpoints LONGTEXT NULL,
			is_active BOOLEAN NOT NULL DEFAULT TRUE,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (layout_id),
			KEY idx_tournament_active (tournament_id, is_active),
			FOREIGN KEY (tournament_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Create Display Screens table
	 *
	 * TD3 Integration: Display System (v3.4.0+)
	 * Physical display endpoint management for multi-screen support
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb Database instance.
	 * @param string $charset_collate Charset collation.
	 * @return bool True on success, false on failure
	 */
	private static function create_display_screens_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'poker_display_screens';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			screen_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			screen_name VARCHAR(255) NOT NULL,
			tournament_id BIGINT(20) UNSIGNED NULL,
			endpoint_url VARCHAR(500) NOT NULL,
			layout_id BIGINT(20) UNSIGNED NULL,
			template_id BIGINT(20) UNSIGNED NULL,
			screen_type ENUM('clock','rankings','prizes','seating','custom','tournament') NOT NULL DEFAULT 'custom',
			location VARCHAR(255) NULL,
			refresh_rate INT NOT NULL DEFAULT 30,
			screen_description TEXT NULL,
			is_active BOOLEAN NOT NULL DEFAULT TRUE,
			last_ping DATETIME NULL,
			is_online BOOLEAN NOT NULL DEFAULT FALSE,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (screen_id),
			UNIQUE KEY idx_endpoint_url (endpoint_url),
			KEY idx_tournament (tournament_id),
			KEY idx_online (is_online, last_ping),
			KEY idx_active (is_active),
			FOREIGN KEY (tournament_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL,
			FOREIGN KEY (layout_id) REFERENCES {$wpdb->prefix}poker_display_layouts(layout_id) ON DELETE SET NULL,
			FOREIGN KEY (template_id) REFERENCES {$wpdb->prefix}poker_display_templates(template_id) ON DELETE SET NULL
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Create Display Tokens table
	 *
	 * TD3 Integration: Display System (v3.4.0+)
	 * Token registry for dynamic content in display templates
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb Database instance.
	 * @param string $charset_collate Charset collation.
	 * @return bool True on success, false on failure
	 */
	private static function create_display_tokens_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'poker_display_tokens';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			token_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token_name VARCHAR(100) NOT NULL,
			token_description TEXT NULL,
			token_type ENUM('tournament','player','blind','prize','time','custom') NOT NULL DEFAULT 'custom',
			data_source VARCHAR(255) NULL,
			default_format VARCHAR(255) NULL,
			is_active BOOLEAN NOT NULL DEFAULT TRUE,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (token_id),
			UNIQUE KEY idx_token_name (token_name),
			KEY idx_type_active (token_type, is_active)
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
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
			// Phase 2 tables (drop first due to foreign keys)
			$wpdb->prefix . 'tdwp_tournament_seats',
			$wpdb->prefix . 'tdwp_tournament_tables',
			$wpdb->prefix . 'tdwp_tournament_events',
			$wpdb->prefix . 'tdwp_tournament_live_state',
			// Phase 1 tables
			$wpdb->prefix . 'tdwp_tournament_players',
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