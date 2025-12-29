<?php
/**
 * Tournament Manager Debug Logger
 *
 * Comprehensive logging for troubleshooting tournament clock issues.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Debug Logger Class
 *
 * @since 3.1.0
 */
class TDWP_Debug_Logger {

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private static $log_file = null;

	/**
	 * Enable/disable logging
	 *
	 * @var bool
	 */
	private static $enabled = true;

	/**
	 * Initialize logger
	 *
	 * @since 3.1.0
	 */
	public static function init() {
		$upload_dir      = wp_upload_dir();
		self::$log_file = $upload_dir['basedir'] . '/tdwp-tournament-debug.log';

		// Check if logging is enabled via option
		self::$enabled = get_option( 'tdwp_debug_logging_enabled', true );
	}

	/**
	 * Log a message
	 *
	 * @since 3.1.0
	 * @param string $context Context (e.g., 'CLOCK', 'DB', 'HEARTBEAT').
	 * @param string $message Log message.
	 * @param array  $data    Optional data to include.
	 */
	public static function log( $context, $message, $data = array() ) {
		if ( ! self::$enabled ) {
			return;
		}

		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$caller    = isset( $backtrace[1]['function'] ) ? $backtrace[1]['function'] : 'unknown';

		$log_entry = sprintf(
			"[%s] [%s] [%s] %s\n",
			$timestamp,
			$context,
			$caller,
			$message
		);

		if ( ! empty( $data ) ) {
			$log_entry .= '  Data: ' . print_r( $data, true ) . "\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		file_put_contents( self::$log_file, $log_entry, FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Log database state change
	 *
	 * @since 3.1.0
	 * @param string $operation Operation name.
	 * @param object $before    State before change (null if new).
	 * @param object $after     State after change.
	 */
	public static function log_state_change( $operation, $before, $after ) {
		$changes = array();

		if ( $before ) {
			$changes['status']         = ( $before->status !== $after->status ) ? $before->status . ' → ' . $after->status : $after->status;
			$changes['time_remaining'] = ( $before->time_remaining !== $after->time_remaining ) ? $before->time_remaining . ' → ' . $after->time_remaining : $after->time_remaining;
			$changes['current_level']  = ( $before->current_level !== $after->current_level ) ? $before->current_level . ' → ' . $after->current_level : $after->current_level;
			$changes['updated_at']     = $before->updated_at . ' → ' . $after->updated_at;
		} else {
			$changes['status']         = 'NEW → ' . $after->status;
			$changes['time_remaining'] = 'NEW → ' . $after->time_remaining;
			$changes['current_level']  = 'NEW → ' . $after->current_level;
			$changes['updated_at']     = 'NEW → ' . $after->updated_at;
		}

		self::log( 'STATE_CHANGE', $operation . ' - Tournament #' . $after->tournament_id, $changes );
	}

	/**
	 * Log tick operation
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament ID.
	 * @param int $elapsed       Seconds elapsed.
	 * @param int $old_time      Time before tick.
	 * @param int $new_time      Time after tick.
	 */
	public static function log_tick( $tournament_id, $elapsed, $old_time, $new_time ) {
		self::log(
			'TICK',
			'Tournament #' . $tournament_id,
			array(
				'elapsed'    => $elapsed . 's',
				'old_time'   => $old_time . 's (' . self::format_time( $old_time ) . ')',
				'new_time'   => $new_time . 's (' . self::format_time( $new_time ) . ')',
				'difference' => ( $old_time - $new_time ) . 's',
			)
		);
	}

	/**
	 * Format seconds as MM:SS
	 *
	 * @since 3.1.0
	 * @param int $seconds Seconds.
	 * @return string Formatted time.
	 */
	private static function format_time( $seconds ) {
		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;
		return sprintf( '%02d:%02d', $minutes, $secs );
	}

	/**
	 * Clear log file
	 *
	 * @since 3.1.0
	 * @return bool Success.
	 */
	public static function clear_log() {
		if ( ! self::$log_file ) {
			self::init();
		}

		if ( file_exists( self::$log_file ) ) {
			return unlink( self::$log_file );
		}

		return true;
	}

	/**
	 * Get log file path
	 *
	 * @since 3.1.0
	 * @return string Log file path.
	 */
	public static function get_log_file() {
		if ( ! self::$log_file ) {
			self::init();
		}

		return self::$log_file;
	}

	/**
	 * Get log file contents
	 *
	 * @since 3.1.0
	 * @param int $lines Number of lines to return (0 = all).
	 * @return string Log contents.
	 */
	public static function get_log_contents( $lines = 0 ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		if ( ! file_exists( self::$log_file ) ) {
			return '';
		}

		if ( $lines > 0 ) {
			// Get last N lines
			$output = array();
			exec( 'tail -n ' . intval( $lines ) . ' ' . escapeshellarg( self::$log_file ), $output ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			return implode( "\n", $output );
		}

		return file_get_contents( self::$log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Enable logging
	 *
	 * @since 3.1.0
	 */
	public static function enable() {
		self::$enabled = true;
		update_option( 'tdwp_debug_logging_enabled', true );
	}

	/**
	 * Disable logging
	 *
	 * @since 3.1.0
	 */
	public static function disable() {
		self::$enabled = false;
		update_option( 'tdwp_debug_logging_enabled', false );
	}

	/**
	 * Check if logging is enabled
	 *
	 * @since 3.1.0
	 * @return bool Enabled status.
	 */
	public static function is_enabled() {
		if ( ! isset( self::$enabled ) ) {
			self::init();
		}

		return self::$enabled;
	}
}
