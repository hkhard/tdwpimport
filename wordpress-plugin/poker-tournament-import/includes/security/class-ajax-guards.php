<?php
/**
 * Reusable security guards for AJAX handlers.
 *
 * Small, dependency-light helpers extracted so the security-critical logic is
 * unit-testable without bootstrapping the full plugin. The frontend AJAX
 * handlers in poker-tournament-import.php delegate to these. (beads tdwp-7k5,
 * tdwp-bxp, tdwp-cdq, tdwp-gwp)
 *
 * @package Poker_Tournament_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static security helpers shared by the plugin's AJAX handlers.
 */
class TDWP_Ajax_Guards {

	/**
	 * Build the screen-scoped nonce action for the unregister_screen endpoint.
	 *
	 * The endpoint is registered nopriv so kiosk displays (which have no WordPress
	 * login) can mark themselves offline on page unload. Scoping the nonce to the
	 * screen_id closes the IDOR: an anonymous visitor can only unregister the
	 * screen whose display page they were actually served, never arbitrary
	 * screen_ids by iteration. Mirrors WordPress core object-scoped nonces such as
	 * `delete-post_{id}`. (tdwp-bxp)
	 *
	 * @param int|string $screen_id Screen identifier.
	 * @return string Nonce action string.
	 */
	public static function unregister_screen_nonce_action( $screen_id ) {
		return 'tdwp_unregister_screen_' . absint( $screen_id );
	}

	/**
	 * Content sniff: does the file look like plain text (not binary)?
	 *
	 * Tournament Director .tdt exports are text. Binary uploads (images,
	 * executables, archives) renamed to .tdt contain NUL bytes; text files do
	 * not. This is robust for the non-standard .tdt extension, where WordPress
	 * mime/finfo checks would false-reject valid exports and break import. The
	 * parser performs the deeper structural validation afterwards. (tdwp-gwp)
	 *
	 * @param string $path Absolute path to a readable file.
	 * @return bool True if the file looks like text.
	 */
	public static function is_text_upload( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return false;
		}

		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		$chunk = fread( $handle, 8192 );
		fclose( $handle );

		if ( false === $chunk || '' === $chunk ) {
			return false;
		}

		return false === strpos( $chunk, "\0" );
	}

	/**
	 * Rate-limit an expensive operation via a transient lock.
	 *
	 * Returns true when the operation is currently throttled (the caller should
	 * bail). On the first call within a window it sets the lock and returns false,
	 * so the caller proceeds at most once per window. Prevents an authenticated
	 * user from hammering an expensive rebuild into a CPU/DB DoS. (tdwp-cdq)
	 *
	 * @param string $key Transient key.
	 * @param int    $ttl Lock lifetime in seconds.
	 * @return bool True if throttled (a lock is already held).
	 */
	public static function is_throttled( $key, $ttl ) {
		if ( get_transient( $key ) ) {
			return true;
		}

		// Clamp to a positive lifetime: a 0/negative TTL would make the transient
		// non-expiring in WordPress, permanently locking the operation.
		set_transient( $key, 1, max( 1, (int) $ttl ) );

		return false;
	}

	/**
	 * Resolve the client IP for rate-limiting.
	 *
	 * Uses REMOTE_ADDR only — proxy headers (X-Forwarded-For) are client-spoofable
	 * and must not be trusted for a security decision. Falls back to a constant
	 * bucket if REMOTE_ADDR is missing/invalid. (tdwp-hk3)
	 *
	 * @return string A validated IP, or '0.0.0.0' if none.
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Per-IP rate limit using a counting transient.
	 *
	 * Returns true once the calling IP has made >= $max attempts within $window
	 * seconds (caller should bail). Unlike is_throttled() (a single global lock),
	 * this keys on the client IP so it cannot block other users — suitable for
	 * public forms like player registration. (tdwp-hk3)
	 *
	 * @param string $key_prefix Transient key prefix (per logical action).
	 * @param int    $max        Max attempts allowed within the window.
	 * @param int    $window     Window length in seconds.
	 * @return bool True if the IP is over the limit.
	 */
	public static function is_rate_limited( $key_prefix, $max, $window ) {
		$key   = $key_prefix . '_' . md5( self::client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= (int) $max ) {
			return true;
		}

		set_transient( $key, $count + 1, max( 1, (int) $window ) );

		return false;
	}
}
