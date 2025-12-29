<?php
/**
 * TD3 Integration Database Schema
 *
 * Database schema for Tournament Director 3 integration features:
 * - Display System: Templates, layouts, screen configurations
 * - Event Notifications: Event queue, notification preferences, sound library
 * - Player Engagement: Player photos, achievements, leagues/seasons
 * - QR Codes: QR code generation and tracking
 * - Display Screens: Multi-screen endpoint management
 *
 * @package Poker_Tournament_Import
 * @subpackage TD3_Integration
 * @since 3.4.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TD3 Database Schema Management Class
 *
 * @since 3.4.0
 */
class TDWP_TD3_Database_Schema {

	/**
	 * TD3 Database version for schema migrations
	 *
	 * v3.4.0: Initial TD3 integration - display, notifications, engagement, QR codes, screens
	 *
	 * @var string
	 */
	const TD3_DB_VERSION = '3.4.0';

	/**
	 * Option name for storing TD3 database version
	 *
	 * @var string
	 */
	const TD3_DB_VERSION_OPTION = 'tdwp_td3_db_version';

	/**
	 * Create or update TD3 database tables
	 *
	 * Called on plugin activation and updates
	 *
	 * @since 3.4.0
	 * @return bool True on success, false on failure
	 */
	public static function create_td3_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$current_version = get_option( self::TD3_DB_VERSION_OPTION, '0.0.0' );

		// Only run if schema needs update
		if ( version_compare( $current_version, self::TD3_DB_VERSION, '>=' ) ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables_created = array();

		// Display System Tables
		$tables_created[] = self::create_display_templates_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_display_layouts_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_display_screens_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_screen_configurations_table( $wpdb, $charset_collate );

		// Event Notification Tables
		$tables_created[] = self::create_event_queue_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_notification_preferences_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_sound_library_table( $wpdb, $charset_collate );

		// Player Engagement Tables
		$tables_created[] = self::create_player_photos_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_achievements_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_player_achievements_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_leagues_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_seasons_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_league_memberships_table( $wpdb, $charset_collate );

		// QR Code Tables
		$tables_created[] = self::create_qr_codes_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_qr_tracking_table( $wpdb, $charset_collate );

		// Multi-Screen Endpoint Tables
		$tables_created[] = self::create_display_endpoints_table( $wpdb, $charset_collate );
		$tables_created[] = self::create_endpoint_status_table( $wpdb, $charset_collate );

		// Check if all tables created successfully
		$success = ! in_array( false, $tables_created, true );

		if ( $success ) {
			update_option( self::TD3_DB_VERSION_OPTION, self::TD3_DB_VERSION );
			do_action( 'tdwp_td3_database_schema_updated', self::TD3_DB_VERSION );
		}

		return $success;
	}

	/**
	 * Create display templates table
	 *
	 * Stores reusable display template configurations
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_display_templates_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_display_templates';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			template_type varchar(50) NOT NULL DEFAULT 'tournament',
			css_variables longtext,
			html_template longtext,
			is_default tinyint(1) DEFAULT 0,
			is_active tinyint(1) DEFAULT 1,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY template_type (template_type),
			KEY is_default (is_default),
			KEY is_active (is_active),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create display layouts table
	 *
	 * Stores layout configurations for different screen types
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_display_layouts_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_display_layouts';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id bigint(20) UNSIGNED NOT NULL,
			name varchar(255) NOT NULL,
			screen_type varchar(50) NOT NULL DEFAULT 'main',
			layout_config longtext NOT NULL,
			responsive_settings longtext,
			is_default tinyint(1) DEFAULT 0,
			sort_order int DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY template_id (template_id),
			KEY screen_type (screen_type),
			KEY is_default (is_default),
			KEY sort_order (sort_order),
			KEY template_screen (template_id, screen_type)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create display screens table
	 *
	 * Stores display screen instances and assignments
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_display_screens_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_display_screens';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED,
			screen_name varchar(255) NOT NULL,
			screen_type varchar(50) NOT NULL DEFAULT 'main',
			template_id bigint(20) UNSIGNED,
			layout_id bigint(20) UNSIGNED,
			endpoint_id bigint(20) UNSIGNED,
			status varchar(20) NOT NULL DEFAULT 'active',
			refresh_interval int DEFAULT 30,
			last_updated datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tournament_id (tournament_id),
			KEY screen_type (screen_type),
			KEY template_id (template_id),
			KEY layout_id (layout_id),
			KEY endpoint_id (endpoint_id),
			KEY status (status),
			KEY tournament_status (tournament_id, status)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create screen configurations table
	 *
	 * Stores runtime configuration for active screens
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_screen_configurations_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_screen_configurations';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			screen_id bigint(20) UNSIGNED NOT NULL,
			config_key varchar(255) NOT NULL,
			config_value longtext,
			data_type varchar(20) DEFAULT 'string',
			is_system tinyint(1) DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY screen_config (screen_id, config_key),
			KEY screen_id (screen_id),
			KEY config_key (config_key(191)),
			KEY is_system (is_system)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create event queue table
	 *
	 * Stores event queue for tournament notifications
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_event_queue_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_event_queue';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) UNSIGNED,
			event_type varchar(50) NOT NULL,
			priority int DEFAULT 5,
			event_data longtext,
			notification_data longtext,
			scheduled_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int DEFAULT 0,
			max_attempts int DEFAULT 3,
			last_attempt datetime DEFAULT NULL,
			next_attempt datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			error_message text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tournament_id (tournament_id),
			KEY event_type (event_type),
			KEY priority (priority),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY next_attempt (next_attempt),
			KEY tournament_priority (tournament_id, priority),
			KEY status_priority (status, priority)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create notification preferences table
	 *
	 * Stores user notification preferences
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_notification_preferences_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_notification_preferences';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			tournament_id bigint(20) UNSIGNED,
			event_type varchar(50) NOT NULL,
			notification_method varchar(50) NOT NULL DEFAULT 'screen',
			is_enabled tinyint(1) DEFAULT 1,
			sound_enabled tinyint(1) DEFAULT 1,
			sound_file varchar(255) DEFAULT NULL,
			volume_level int DEFAULT 80,
			auto_popup tinyint(1) DEFAULT 0,
			duration_seconds int DEFAULT 5,
			priority_filter int DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_tournament_event (user_id, tournament_id, event_type, notification_method),
			KEY user_id (user_id),
			KEY tournament_id (tournament_id),
			KEY event_type (event_type),
			KEY notification_method (notification_method),
			KEY is_enabled (is_enabled)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create sound library table
	 *
	 * Stores sound files for event notifications
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collation.
	 * @return bool True on success
	 */
	private static function create_sound_library_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_sound_library';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			file_path varchar(500) NOT NULL,
			file_url varchar(500),
			file_size bigint(20) DEFAULT 0,
			duration_ms int DEFAULT 0,
			file_type varchar(50) DEFAULT 'audio',
			category varchar(100) DEFAULT 'general',
			is_default tinyint(1) DEFAULT 0,
			is_active tinyint(1) DEFAULT 1,
			uploaded_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY category (category),
			KEY is_default (is_default),
			KEY is_active (is_active),
			KEY uploaded_by (uploaded_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create player photos table
	 *
	 * Stores player photo references and metadata
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_player_photos_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_player_photos';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			player_id bigint(20) UNSIGNED NOT NULL,
			photo_url varchar(500) NOT NULL,
			thumbnail_url varchar(500),
			photo_path varchar(500),
			file_size bigint(20) DEFAULT 0,
			mime_type varchar(100) DEFAULT 'image/jpeg',
			upload_source varchar(50) DEFAULT 'upload',
			is_primary tinyint(1) DEFAULT 1,
			is_approved tinyint(1) DEFAULT 1,
			uploaded_by bigint(20) UNSIGNED,
			approved_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY player_id (player_id),
			KEY is_primary (is_primary),
			KEY is_approved (is_approved),
			KEY uploaded_by (uploaded_by),
			KEY approved_by (approved_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create achievements table
	 *
	 * Stores achievement definitions and criteria
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_achievements_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_achievements';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			icon_url varchar(500),
			badge_color varchar(20) DEFAULT '#gold',
			category varchar(100) DEFAULT 'general',
			achievement_type varchar(50) NOT NULL,
			criteria_config longtext NOT NULL,
			points_value int DEFAULT 10,
			is_active tinyint(1) DEFAULT 1,
			is_hidden tinyint(1) DEFAULT 0,
			sort_order int DEFAULT 0,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY category (category),
			KEY achievement_type (achievement_type),
			KEY is_active (is_active),
			KEY is_hidden (is_hidden),
			KEY sort_order (sort_order),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create player achievements table
	 *
	 * Stores earned achievements by players
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_player_achievements_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_player_achievements';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			player_id bigint(20) UNSIGNED NOT NULL,
			achievement_id bigint(20) UNSIGNED NOT NULL,
			tournament_id bigint(20) UNSIGNED,
			earned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			progress_data longtext,
			is_displayed tinyint(1) DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY player_achievement (player_id, achievement_id),
			KEY player_id (player_id),
			KEY achievement_id (achievement_id),
			KEY tournament_id (tournament_id),
			KEY earned_at (earned_at),
			KEY is_displayed (is_displayed)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create leagues table
	 *
	 * Stores league definitions and settings
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_leagues_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_leagues';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			league_type varchar(50) DEFAULT 'points',
			scoring_config longtext,
			ranking_config longtext,
			is_active tinyint(1) DEFAULT 1,
			is_private tinyint(1) DEFAULT 0,
			max_players int DEFAULT 0,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY league_type (league_type),
			KEY is_active (is_active),
			KEY is_private (is_private),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create seasons table
	 *
	 * Stores season definitions within leagues
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_seasons_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_seasons';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			league_id bigint(20) UNSIGNED NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			start_date date NOT NULL,
			end_date date,
			is_active tinyint(1) DEFAULT 1,
			sort_order int DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY league_id (league_id),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY is_active (is_active),
			KEY sort_order (sort_order),
			KEY league_order (league_id, sort_order)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create league memberships table
	 *
	 * Stores player memberships in leagues and seasons
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_league_memberships_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_league_memberships';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			league_id bigint(20) UNSIGNED NOT NULL,
			season_id bigint(20) UNSIGNED,
			player_id bigint(20) UNSIGNED NOT NULL,
			membership_status varchar(20) DEFAULT 'active',
			join_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			leave_date datetime DEFAULT NULL,
			total_points decimal(10,2) DEFAULT 0,
			wins int DEFAULT 0,
			final_tables int DEFAULT 0,
			cashes int DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY league_season_player (league_id, season_id, player_id),
			KEY league_id (league_id),
			KEY season_id (season_id),
			KEY player_id (player_id),
			KEY membership_status (membership_status),
			KEY total_points (total_points),
			KEY wins (wins)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create QR codes table
	 *
	 * Stores generated QR codes and their metadata
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_qr_codes_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_qr_codes';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			qr_type varchar(50) NOT NULL,
			target_id bigint(20) UNSIGNED,
			target_data longtext,
			qr_data varchar(2000) NOT NULL,
			qr_image_url varchar(500),
			qr_image_path varchar(500),
			title varchar(255),
			description text,
			is_active tinyint(1) DEFAULT 1,
			expiry_date datetime DEFAULT NULL,
			max_scans int DEFAULT 0,
			current_scans int DEFAULT 0,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY qr_type (qr_type),
			KEY target_id (target_id),
			KEY is_active (is_active),
			KEY expiry_date (expiry_date),
			KEY created_by (created_by),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create QR tracking table
	 *
	 * Stores QR code scan tracking data
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_qr_tracking_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_qr_tracking';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			qr_id bigint(20) UNSIGNED NOT NULL,
			scan_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address varchar(45),
			user_agent text,
			referrer_url varchar(500),
			geo_location varchar(255),
			device_type varchar(50),
			browser_type varchar(100),
			session_id varchar(255),
			user_id bigint(20) UNSIGNED,
			scan_context longtext,
			PRIMARY KEY  (id),
			KEY qr_id (qr_id),
			KEY scan_time (scan_time),
			KEY ip_address (ip_address),
			KEY user_id (user_id),
			KEY device_type (device_type),
			KEY session_id (session_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create display endpoints table
	 *
	 * Stores multi-screen endpoint configurations
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_display_endpoints_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_display_endpoints';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			endpoint_name varchar(255) NOT NULL,
			endpoint_type varchar(50) NOT NULL DEFAULT 'web',
			endpoint_url varchar(500),
			api_key varchar(255),
			api_secret varchar(255),
			screen_resolution varchar(50),
			orientation varchar(20) DEFAULT 'landscape',
			refresh_rate int DEFAULT 30,
			status varchar(20) NOT NULL DEFAULT 'offline',
			last_heartbeat datetime DEFAULT NULL,
			location_info longtext,
			config_settings longtext,
			is_active tinyint(1) DEFAULT 1,
			created_by bigint(20) UNSIGNED,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY endpoint_name (endpoint_name(191)),
			KEY endpoint_type (endpoint_type),
			KEY status (status),
			KEY is_active (is_active),
			KEY last_heartbeat (last_heartbeat),
			KEY created_by (created_by)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create endpoint status table
	 *
	 * Stores real-time status information for display endpoints
	 *
	 * @since 3.4.0
	 * @param wpdb   $wpdb             WordPress database object.
	 * @param string $charset_collate  Database charset collate.
	 * @return bool True on success
	 */
	private static function create_endpoint_status_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'tdwp_endpoint_status';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			endpoint_id bigint(20) UNSIGNED NOT NULL,
			screen_id bigint(20) UNSIGNED,
			status varchar(20) NOT NULL DEFAULT 'offline',
			cpu_usage decimal(5,2) DEFAULT 0,
			memory_usage decimal(5,2) DEFAULT 0,
			network_quality int DEFAULT 100,
			current_viewers int DEFAULT 0,
			buffer_health int DEFAULT 100,
			last_render_time int DEFAULT 0,
			error_count int DEFAULT 0,
			last_error text,
			status_data longtext,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_id (endpoint_id),
			KEY screen_id (screen_id),
			KEY status (status),
			KEY last_heartbeat (updated_at),
			KEY status_updated (status, updated_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Insert default TD3 data
	 *
	 * Creates default templates, sounds, and achievements
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	public static function insert_default_td3_data() {
		// Check if TD3 data already exists
		$existing_data = get_option( 'tdwp_td3_default_data_inserted', false );

		if ( $existing_data ) {
			return true;
		}

		global $wpdb;

		// Insert default display templates
		self::insert_default_display_templates( $wpdb );

		// Insert default notification sounds
		self::insert_default_notification_sounds( $wpdb );

		// Insert default achievements
		self::insert_default_achievements( $wpdb );

		// Mark as inserted
		update_option( 'tdwp_td3_default_data_inserted', true );

		return true;
	}

	/**
	 * Insert default display templates
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb WordPress database object.
	 */
	private static function insert_default_display_templates( $wpdb ) {
		$templates_table = $wpdb->prefix . 'tdwp_display_templates';

		// Main Tournament Display Template
		$wpdb->insert(
			$templates_table,
			array(
				'name' => __( 'Main Tournament Display', 'poker-tournament-import' ),
				'description' => __( 'Default tournament display with player info, blinds, and clock', 'poker-tournament-import' ),
				'template_type' => 'tournament',
				'css_variables' => wp_json_encode( array(
					'primary_color' => '#0073aa',
					'secondary_color' => '#23282d',
					'success_color' => '#46b450',
					'warning_color' => '#ffb900',
					'error_color' => '#dc3232',
					'font_family' => 'Arial, sans-serif',
					'background_color' => '#ffffff',
				) ),
				'html_template' => '<!-- Main Tournament Display Template -->',
				'is_default' => 1,
				'is_active' => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		// Player Rankings Display Template
		$wpdb->insert(
			$templates_table,
			array(
				'name' => __( 'Player Rankings Display', 'poker-tournament-import' ),
				'description' => __( 'Display focused on player rankings and chip counts', 'poker-tournament-import' ),
				'template_type' => 'rankings',
				'css_variables' => wp_json_encode( array(
					'primary_color' => '#0073aa',
					'secondary_color' => '#23282d',
					'font_family' => 'Arial, sans-serif',
					'background_color' => '#ffffff',
				) ),
				'html_template' => '<!-- Player Rankings Display Template -->',
				'is_default' => 1,
				'is_active' => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		// Countdown Timer Display Template
		$wpdb->insert(
			$templates_table,
			array(
				'name' => __( 'Countdown Timer Display', 'poker-tournament-import' ),
				'description' => __( 'Minimal display focused on level timer', 'poker-tournament-import' ),
				'template_type' => 'timer',
				'css_variables' => wp_json_encode( array(
					'timer_color' => '#46b450',
					'background_color' => '#000000',
					'text_color' => '#ffffff',
				) ),
				'html_template' => '<!-- Countdown Timer Display Template -->',
				'is_default' => 1,
				'is_active' => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
	}

	/**
	 * Insert default notification sounds
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb WordPress database object.
	 */
	private static function insert_default_notification_sounds( $wpdb ) {
		$sounds_table = $wpdb->prefix . 'tdwp_sound_library';

		$sounds = array(
			array(
				'name' => __( 'Level Change Bell', 'poker-tournament-import' ),
				'description' => __( 'Bell sound for level changes', 'poker-tournament-import' ),
				'file_path' => plugin_dir_path( dirname( __FILE__ ) ) . 'assets/sounds/bell.mp3',
				'file_url' => plugin_dir_url( dirname( __FILE__ ) ) . 'assets/sounds/bell.mp3',
				'category' => 'tournament',
				'is_default' => 1,
			),
			array(
				'name' => __( 'Player Bustout', 'poker-tournament-import' ),
				'description' => __( 'Sound for player eliminations', 'poker-tournament-import' ),
				'file_path' => plugin_dir_path( dirname( __FILE__ ) ) . 'assets/sounds/bustout.mp3',
				'file_url' => plugin_dir_url( dirname( __FILE__ ) ) . 'assets/sounds/bustout.mp3',
				'category' => 'elimination',
				'is_default' => 1,
			),
			array(
				'name' => __( 'Final Table', 'poker-tournament-import' ),
				'description' => __( 'Celebration sound for reaching final table', 'poker-tournament-import' ),
				'file_path' => plugin_dir_path( dirname( __FILE__ ) ) . 'assets/sounds/final-table.mp3',
				'file_url' => plugin_dir_url( dirname( __FILE__ ) ) . 'assets/sounds/final-table.mp3',
				'category' => 'milestone',
				'is_default' => 1,
			),
			array(
				'name' => __( 'Registration Open', 'poker-tournament-import' ),
				'description' => __( 'Sound for registration opening', 'poker-tournament-import' ),
				'file_path' => plugin_dir_path( dirname( __FILE__ ) ) . 'assets/sounds/registration.mp3',
				'file_url' => plugin_dir_url( dirname( __FILE__ ) ) . 'assets/sounds/registration.mp3',
				'category' => 'registration',
				'is_default' => 1,
			),
		);

		foreach ( $sounds as $sound ) {
			$wpdb->insert(
				$sounds_table,
				$sound,
				array( '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Insert default achievements
	 *
	 * @since 3.4.0
	 * @param wpdb $wpdb WordPress database object.
	 */
	private static function insert_default_achievements( $wpdb ) {
		$achievements_table = $wpdb->prefix . 'tdwp_achievements';

		$achievements = array(
			array(
				'name' => __( 'First Win', 'poker-tournament-import' ),
				'description' => __( 'Win your first tournament', 'poker-tournament-import' ),
				'achievement_type' => 'tournament_win',
				'criteria_config' => wp_json_encode( array(
					'type' => 'count',
					'field' => 'finish_position',
					'value' => 1,
					'operator' => '=',
					'required_count' => 1,
				) ),
				'points_value' => 50,
				'badge_color' => '#gold',
				'category' => 'tournament',
				'sort_order' => 1,
			),
			array(
				'name' => __( 'Final Table Regular', 'poker-tournament-import' ),
				'description' => __( 'Reach 10 final tables', 'poker-tournament-import' ),
				'achievement_type' => 'final_table',
				'criteria_config' => wp_json_encode( array(
					'type' => 'count',
					'field' => 'finish_position',
					'value' => '<= 9',
					'required_count' => 10,
				) ),
				'points_value' => 30,
				'badge_color' => '#silver',
				'category' => 'tournament',
				'sort_order' => 2,
			),
			array(
				'name' => __( 'Bounty Hunter', 'poker-tournament-import' ),
				'description' => __( 'Eliminate 50 players with bounties', 'poker-tournament-import' ),
				'achievement_type' => 'bounty_hunter',
				'criteria_config' => wp_json_encode( array(
					'type' => 'sum',
					'field' => 'bounties_from_players',
					'required_total' => 50,
				) ),
				'points_value' => 25,
				'badge_color' => '#bronze',
				'category' => 'bounty',
				'sort_order' => 3,
			),
			array(
				'name' => __( 'Deep Stack Master', 'poker-tournament-import' ),
				'description' => __( 'Win with starting chips doubled', 'poker-tournament-import' ),
				'achievement_type' => 'chip_leader',
				'criteria_config' => wp_json_encode( array(
					'type' => 'condition',
					'conditions' => array(
						array( 'field' => 'finish_position', 'operator' => '=', 'value' => 1 ),
						array( 'field' => 'chip_count', 'operator' => '>=', 'value' => 20000 ),
					),
				) ),
				'points_value' => 40,
				'badge_color' => '#purple',
				'category' => 'chips',
				'sort_order' => 4,
			),
			array(
				'name' => __( 'Serial Winner', 'poker-tournament-import' ),
				'description' => __( 'Win 5 tournaments in a season', 'poker-tournament-import' ),
				'achievement_type' => 'serial_winner',
				'criteria_config' => wp_json_encode( array(
					'type' => 'season_count',
					'field' => 'finish_position',
					'value' => 1,
					'required_count' => 5,
					'timeframe' => 'season',
				) ),
				'points_value' => 100,
				'badge_color' => '#gold',
				'category' => 'milestone',
				'sort_order' => 5,
			),
		);

		foreach ( $achievements as $achievement ) {
			$wpdb->insert(
				$achievements_table,
				$achievement,
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Drop all TD3 tables
	 *
	 * WARNING: This deletes all TD3 data
	 * Only used during development or uninstall
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	public static function drop_td3_tables() {
		global $wpdb;

		$tables = array(
			// Drop tables with foreign keys first
			$wpdb->prefix . 'tdwp_endpoint_status',
			$wpdb->prefix . 'tdwp_qr_tracking',
			$wpdb->prefix . 'tdwp_player_achievements',
			$wpdb->prefix . 'tdwp_league_memberships',
			$wpdb->prefix . 'tdwp_screen_configurations',
			$wpdb->prefix . 'tdwp_display_screens',
			$wpdb->prefix . 'tdwp_event_queue',

			// Drop main tables
			$wpdb->prefix . 'tdwp_display_endpoints',
			$wpdb->prefix . 'tdwp_qr_codes',
			$wpdb->prefix . 'tdwp_seasons',
			$wpdb->prefix . 'tdwp_leagues',
			$wpdb->prefix . 'tdwp_player_photos',
			$wpdb->prefix . 'tdwp_achievements',
			$wpdb->prefix . 'tdwp_sound_library',
			$wpdb->prefix . 'tdwp_notification_preferences',
			$wpdb->prefix . 'tdwp_display_layouts',
			$wpdb->prefix . 'tdwp_display_templates',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name from safe array
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::TD3_DB_VERSION_OPTION );
		delete_option( 'tdwp_td3_default_data_inserted' );

		return true;
	}
}