<?php
/**
 * TD3 Integration Migration Handler
 *
 * Handles migration and integration of TD3 features with existing tournament system.
 * Provides safe upgrade path for existing installations.
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
 * TD3 Migration Management Class
 *
 * @since 3.4.0
 */
class TDWP_TD3_Migration {

	/**
	 * Current TD3 migration version
	 *
	 * @var string
	 */
	const TD3_MIGRATION_VERSION = '3.4.0';

	/**
	 * Migration history option name
	 *
	 * @var string
	 */
	const MIGRATION_HISTORY_OPTION = 'tdwp_td3_migration_history';

	/**
	 * Run all TD3 migrations
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	public static function run_migrations() {
		$current_version = get_option( 'tdwp_td3_migration_version', '0.0.0' );

		if ( version_compare( $current_version, self::TD3_MIGRATION_VERSION, '>=' ) ) {
			return true;
		}

		$migration_success = true;

		// Run migrations in order
		$migrations = array(
			'3.4.0' => 'migrate_340_initial_td3',
		);

		foreach ( $migrations as $version => $migration_method ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				$result = self::$migration_method();

				if ( $result ) {
					self::log_migration( $version, true );
					$current_version = $version;
					update_option( 'tdwp_td3_migration_version', $version );
				} else {
					$migration_success = false;
					self::log_migration( $version, false, 'Migration failed' );
					break;
				}
			}
		}

		return $migration_success;
	}

	/**
	 * Migration 3.4.0: Initial TD3 Integration
	 *
	 * Creates all TD3 tables and inserts default data
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	private static function migrate_340_initial_td3() {
		global $wpdb;

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create TD3 tables
			$create_result = TDWP_TD3_Database_Schema::create_td3_tables();

			if ( ! $create_result ) {
				throw new Exception( 'Failed to create TD3 tables' );
			}

			// Insert default TD3 data
			$insert_result = TDWP_TD3_Database_Schema::insert_default_td3_data();

			if ( ! $insert_result ) {
				throw new Exception( 'Failed to insert default TD3 data' );
			}

			// Run post-migration setup
			self::setup_td3_features();

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			// Fire migration complete action
			do_action( 'tdwp_td3_migration_340_complete' );

			return true;

		} catch ( Exception $e ) {
			// Rollback transaction
			$wpdb->query( 'ROLLBACK' );

			error_log( 'TD3 Migration 3.4.0 failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Setup TD3 features after migration
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	private static function setup_td3_features() {
		// Create upload directories for TD3 features
		self::create_td3_upload_directories();

		// Set default TD3 options
		self::set_default_td3_options();

		// Schedule TD3 cron jobs
		self::schedule_td3_cron_jobs();

		return true;
	}

	/**
	 * Create upload directories for TD3 features
	 *
	 * @since 3.4.0
	 */
	private static function create_td3_upload_directories() {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/poker-tournament';

		$directories = array(
			$base_dir,
			$base_dir . '/player-photos',
			$base_dir . '/qr-codes',
			$base_dir . '/display-templates',
			$base_dir . '/notification-sounds',
			$base_dir . '/achievement-icons',
		);

		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );

				// Create .htaccess to protect directories
				$htaccess_path = $dir . '/.htaccess';
				if ( ! file_exists( $htaccess_path ) ) {
					file_put_contents( $htaccess_path, "Order deny,allow\nDeny from all\n" );
				}

				// Create index.php to prevent directory listing
				$index_path = $dir . '/index.php';
				if ( ! file_exists( $index_path ) ) {
					file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
				}
			}
		}
	}

	/**
	 * Set default TD3 options
	 *
	 * @since 3.4.0
	 */
	private static function set_default_td3_options() {
		$default_options = array(
			'tdwp_td3_display_enabled' => true,
			'tdwp_td3_notifications_enabled' => true,
			'tdwp_td3_player_photos_enabled' => true,
			'tdwp_td3_achievements_enabled' => true,
			'tdwp_td3_qr_codes_enabled' => true,
			'tdwp_td3_multi_screen_enabled' => false, // Disabled by default

			// Display settings
			'tdwp_td3_default_refresh_rate' => 30,
			'tdwp_td3_max_concurrent_screens' => 10,
			'tdwp_td3_display_cache_duration' => 300,

			// Notification settings
			'tdwp_td3_event_queue_retention_days' => 30,
			'tdwp_td3_max_notifications_per_minute' => 10,
			'tdwp_td3_default_sound_volume' => 80,

			// Photo settings
			'tdwp_td3_max_photo_size_mb' => 5,
			'tdwp_td3_photo_thumbnail_size' => array( 150, 150 ),
			'tdwp_td3_photo_display_size' => array( 400, 400 ),

			// QR code settings
			'tdwp_td3_qr_code_size' => 300,
			'tdwp_td3_qr_retention_days' => 90,
			'tdwp_td3_qr_default_expiry_days' => 30,

			// Achievement settings
			'tdwp_td3_achievement_badge_size' => 64,
			'tdwp_td3_achievement_check_frequency' => 'hourly',
		);

		foreach ( $default_options as $option => $value ) {
			if ( get_option( $option ) === false ) {
				add_option( $option, $value );
			}
		}
	}

	/**
	 * Schedule TD3 cron jobs
	 *
	 * @since 3.4.0
	 */
	private static function schedule_td3_cron_jobs() {
		// Event queue processor
		if ( ! wp_next_scheduled( 'tdwp_td3_process_event_queue' ) ) {
			wp_schedule_event( time(), 'tdwp_td3_frequent', 'tdwp_td3_process_event_queue' );
		}

		// Achievement checker
		if ( ! wp_next_scheduled( 'tdwp_td3_check_achievements' ) ) {
			wp_schedule_event( time(), get_option( 'tdwp_td3_achievement_check_frequency', 'hourly' ), 'tdwp_td3_check_achievements' );
		}

		// Endpoint health checker
		if ( ! wp_next_scheduled( 'tdwp_td3_check_endpoint_health' ) ) {
			wp_schedule_event( time(), 'tdwp_td3_every_minute', 'tdwp_td3_check_endpoint_health' );
		}

		// Cleanup old data
		if ( ! wp_next_scheduled( 'tdwp_td3_cleanup_old_data' ) ) {
			wp_schedule_event( time(), 'daily', 'tdwp_td3_cleanup_old_data' );
		}
	}

	/**
	 * Add custom cron intervals for TD3
	 *
	 * @since 3.4.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules
	 */
	public static function add_custom_cron_intervals( $schedules ) {
		$schedules['tdwp_td3_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'poker-tournament-import' ),
		);

		$schedules['tdwp_td3_frequent'] = array(
			'interval' => 30,
			'display'  => __( 'Every 30 Seconds', 'poker-tournament-import' ),
		);

		return $schedules;
	}

	/**
	 * Log migration activity
	 *
	 * @since 3.4.0
	 * @param string $version Migration version.
	 * @param bool $success Whether migration succeeded.
	 * @param string $message Optional error message.
	 */
	private static function log_migration( $version, $success, $message = '' ) {
		$history = get_option( self::MIGRATION_HISTORY_OPTION, array() );

		$history[] = array(
			'version' => $version,
			'timestamp' => current_time( 'mysql' ),
			'success' => $success,
			'message' => $message,
		);

		// Keep only last 50 migration entries
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, -50 );
		}

		update_option( self::MIGRATION_HISTORY_OPTION, $history );

		// Log to error log for debugging
		$log_message = sprintf(
			'TD3 Migration %s: %s%s',
			$version,
			$success ? 'SUCCESS' : 'FAILED',
			$message ? ' - ' . $message : ''
		);

		if ( $success ) {
			error_log( $log_message );
		} else {
			error_log( $log_message );
		}
	}

	/**
	 * Get migration history
	 *
	 * @since 3.4.0
	 * @return array Migration history
	 */
	public static function get_migration_history() {
		return get_option( self::MIGRATION_HISTORY_OPTION, array() );
	}

	/**
	 * Check if TD3 migration is needed
	 *
	 * @since 3.4.0
	 * @return bool True if migration is needed
	 */
	public static function needs_migration() {
		$current_version = get_option( 'tdwp_td3_migration_version', '0.0.0' );
		return version_compare( $current_version, self::TD3_MIGRATION_VERSION, '<' );
	}

	/**
	 * Get current TD3 migration version
	 *
	 * @since 3.4.0
	 * @return string Current version
	 */
	public static function get_current_version() {
		return get_option( 'tdwp_td3_migration_version', '0.0.0' );
	}

	/**
	 * Uninstall TD3 features
	 *
	 * Removes all TD3 data and tables. Use with caution!
	 *
	 * @since 3.4.0
	 * @return bool True on success
	 */
	public static function uninstall_td3() {
		global $wpdb;

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Remove TD3 tables
			$drop_result = TDWP_TD3_Database_Schema::drop_td3_tables();

			if ( ! $drop_result ) {
				throw new Exception( 'Failed to drop TD3 tables' );
			}

			// Remove TD3 options
			self::remove_td3_options();

			// Remove TD3 upload directories
			self::remove_td3_upload_directories();

			// Unschedule TD3 cron jobs
			self::unschedule_td3_cron_jobs();

			// Remove migration history
			delete_option( self::MIGRATION_HISTORY_OPTION );
			delete_option( 'tdwp_td3_migration_version' );

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			return true;

		} catch ( Exception $e ) {
			// Rollback transaction
			$wpdb->query( 'ROLLBACK' );

			error_log( 'TD3 Uninstall failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Remove TD3 options
	 *
	 * @since 3.4.0
	 */
	private static function remove_td3_options() {
		$td3_options = array(
			'tdwp_td3_display_enabled',
			'tdwp_td3_notifications_enabled',
			'tdwp_td3_player_photos_enabled',
			'tdwp_td3_achievements_enabled',
			'tdwp_td3_qr_codes_enabled',
			'tdwp_td3_multi_screen_enabled',
			'tdwp_td3_default_refresh_rate',
			'tdwp_td3_max_concurrent_screens',
			'tdwp_td3_display_cache_duration',
			'tdwp_td3_event_queue_retention_days',
			'tdwp_td3_max_notifications_per_minute',
			'tdwp_td3_default_sound_volume',
			'tdwp_td3_max_photo_size_mb',
			'tdwp_td3_photo_thumbnail_size',
			'tdwp_td3_photo_display_size',
			'tdwp_td3_qr_code_size',
			'tdwp_td3_qr_retention_days',
			'tdwp_td3_qr_default_expiry_days',
			'tdwp_td3_achievement_badge_size',
			'tdwp_td3_achievement_check_frequency',
			'tdwp_td3_default_data_inserted',
		);

		foreach ( $td3_options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Remove TD3 upload directories
	 *
	 * @since 3.4.0
	 */
	private static function remove_td3_upload_directories() {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/poker-tournament';

		if ( file_exists( $base_dir ) ) {
			self::delete_directory( $base_dir );
		}
	}

	/**
	 * Recursively delete directory
	 *
	 * @since 3.4.0
	 * @param string $dir Directory path.
	 */
	private static function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::delete_directory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Unschedule TD3 cron jobs
	 *
	 * @since 3.4.0
	 */
	private static function unschedule_td3_cron_jobs() {
		$cron_jobs = array(
			'tdwp_td3_process_event_queue',
			'tdwp_td3_check_achievements',
			'tdwp_td3_check_endpoint_health',
			'tdwp_td3_cleanup_old_data',
		);

		foreach ( $cron_jobs as $cron_job ) {
			wp_clear_scheduled_hook( $cron_job );
		}
	}

	/**
	 * Validate TD3 installation
	 *
	 * @since 3.4.0
	 * @return array Validation results
	 */
	public static function validate_td3_installation() {
		global $wpdb;

		$validation = array(
			'tables_exist' => true,
			'options_set' => true,
			'directories_created' => true,
			'cron_jobs_scheduled' => true,
			'errors' => array(),
		);

		// Check if all TD3 tables exist
		$td3_tables = array(
			$wpdb->prefix . 'tdwp_display_templates',
			$wpdb->prefix . 'tdwp_display_layouts',
			$wpdb->prefix . 'tdwp_display_screens',
			$wpdb->prefix . 'tdwp_screen_configurations',
			$wpdb->prefix . 'tdwp_event_queue',
			$wpdb->prefix . 'tdwp_notification_preferences',
			$wpdb->prefix . 'tdwp_sound_library',
			$wpdb->prefix . 'tdwp_player_photos',
			$wpdb->prefix . 'tdwp_achievements',
			$wpdb->prefix . 'tdwp_player_achievements',
			$wpdb->prefix . 'tdwp_leagues',
			$wpdb->prefix . 'tdwp_seasons',
			$wpdb->prefix . 'tdwp_league_memberships',
			$wpdb->prefix . 'tdwp_qr_codes',
			$wpdb->prefix . 'tdwp_qr_tracking',
			$wpdb->prefix . 'tdwp_display_endpoints',
			$wpdb->prefix . 'tdwp_endpoint_status',
		);

		foreach ( $td3_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			if ( ! $table_exists ) {
				$validation['tables_exist'] = false;
				$validation['errors'][] = "Missing table: {$table}";
			}
		}

		// Check critical options
		$critical_options = array(
			'tdwp_td3_migration_version',
			'tdwp_td3_display_enabled',
			'tdwp_td3_default_data_inserted',
		);

		foreach ( $critical_options as $option ) {
			if ( get_option( $option ) === false ) {
				$validation['options_set'] = false;
				$validation['errors'][] = "Missing option: {$option}";
			}
		}

		// Check upload directories
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/poker-tournament';
		if ( ! file_exists( $base_dir ) ) {
			$validation['directories_created'] = false;
			$validation['errors'][] = "Upload directory not created: {$base_dir}";
		}

		// Check cron jobs
		$cron_jobs = array(
			'tdwp_td3_process_event_queue',
			'tdwp_td3_check_achievements',
			'tdwp_td3_check_endpoint_health',
			'tdwp_td3_cleanup_old_data',
		);

		foreach ( $cron_jobs as $cron_job ) {
			if ( ! wp_next_scheduled( $cron_job ) ) {
				$validation['cron_jobs_scheduled'] = false;
				$validation['errors'][] = "Cron job not scheduled: {$cron_job}";
			}
		}

		return $validation;
	}
}