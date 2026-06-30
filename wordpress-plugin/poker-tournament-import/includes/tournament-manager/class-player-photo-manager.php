<?php
/**
 * Player Photo Manager (tdwp-ee1.15)
 *
 * Validates and stores player profile photos. Uploads go through
 * wp_handle_upload (which sniffs real file type — the browser-supplied MIME is
 * never trusted) and are recorded in tdwp_player_photos. The validation
 * helpers are pure so they can be unit-tested.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages player profile photo uploads.
 */
class TDWP_Player_Photo_Manager {

	/**
	 * Maximum upload size in bytes (5 MB).
	 */
	const MAX_SIZE = 5242880;

	/**
	 * Allowed MIME types.
	 *
	 * @var string[]
	 */
	const ALLOWED_MIME = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Allowed file extensions.
	 *
	 * @var string[]
	 */
	const ALLOWED_EXT = array( 'jpg', 'jpeg', 'png', 'webp' );

	/**
	 * Lower-case file extension of a name (pure).
	 *
	 * @param string $filename File name.
	 * @return string Extension without the dot, or ''.
	 */
	public static function get_extension( $filename ) {
		$ext = pathinfo( (string) $filename, PATHINFO_EXTENSION );
		return strtolower( (string) $ext );
	}

	/**
	 * Whether a (mime, filename) pair is an allowed image (pure).
	 *
	 * Both the MIME and the extension must be allowed — but note the browser
	 * MIME is advisory; handle_upload() re-checks the real bytes.
	 *
	 * @param string $mime     MIME type.
	 * @param string $filename File name.
	 * @return bool
	 */
	public static function is_allowed_image_type( $mime, $filename ) {
		$mime = strtolower( trim( (string) $mime ) );
		return in_array( $mime, self::ALLOWED_MIME, true )
			&& in_array( self::get_extension( $filename ), self::ALLOWED_EXT, true );
	}

	/**
	 * Whether a size is within the limit (pure).
	 *
	 * @param int $size     Size in bytes.
	 * @param int $max_size Max size (defaults to MAX_SIZE).
	 * @return bool
	 */
	public static function is_within_size_limit( $size, $max_size = self::MAX_SIZE ) {
		$size = (int) $size;
		return $size > 0 && $size <= (int) $max_size;
	}

	/**
	 * Validate an uploaded-file array (pure; no IO).
	 *
	 * @param array $file     A single $_FILES entry (name, type, size, error).
	 * @param int   $max_size Max size.
	 * @return true|WP_Error
	 */
	public static function validate_upload( $file, $max_size = self::MAX_SIZE ) {
		if ( empty( $file ) || ! is_array( $file ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded', 'poker-tournament-import' ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'upload_error', __( 'File upload failed', 'poker-tournament-import' ) );
		}

		if ( ! self::is_within_size_limit( isset( $file['size'] ) ? $file['size'] : 0, $max_size ) ) {
			return new WP_Error( 'too_large', __( 'Image is too large (max 5 MB)', 'poker-tournament-import' ) );
		}

		$name = isset( $file['name'] ) ? $file['name'] : '';
		$mime = isset( $file['type'] ) ? $file['type'] : '';
		if ( ! self::is_allowed_image_type( $mime, $name ) ) {
			return new WP_Error( 'invalid_type', __( 'Only JPG, PNG, or WebP images are allowed', 'poker-tournament-import' ) );
		}

		return true;
	}

	/**
	 * Table name for player photos.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tdwp_player_photos';
	}

	/**
	 * Validate, store, and link a player photo upload.
	 *
	 * @param int   $player_id Player post ID.
	 * @param array $file      A single $_FILES entry.
	 * @return array|WP_Error Result (photo_id, attachment_id, url) or error.
	 */
	public static function handle_upload( $player_id, $file ) {
		global $wpdb;

		$player_id = absint( $player_id );
		if ( ! $player_id ) {
			return new WP_Error( 'invalid_player', __( 'Invalid player', 'poker-tournament-import' ) );
		}

		$valid = self::validate_upload( $file );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// wp_handle_upload sniffs the real file type and enforces an extension
		// allowlist — the browser-supplied MIME is never trusted here.
		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			),
		);
		$uploaded = wp_handle_upload( $file, $overrides );
		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'upload_failed', $uploaded['error'] );
		}

		$attachment = array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'attachment_failed', __( 'Could not create attachment', 'poker-tournament-import' ) );
		}
		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$thumb = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		// Insert the new photo row first; only demote the others once it is
		// safely stored. If the insert fails, roll back the orphaned attachment.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'player_id'     => $player_id,
				'photo_url'     => esc_url_raw( $uploaded['url'] ),
				'thumbnail_url' => $thumb ? esc_url_raw( $thumb ) : '',
				'photo_path'    => $uploaded['file'],
				'file_size'     => isset( $file['size'] ) ? absint( $file['size'] ) : 0,
				'mime_type'     => $uploaded['type'],
				'upload_source' => 'upload',
				'is_primary'    => 1,
				'is_approved'   => 1,
				'uploaded_by'   => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		$photo_id = (int) $wpdb->insert_id;

		if ( false === $inserted || ! $photo_id ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'db_failed', __( 'Failed to save the photo record', 'poker-tournament-import' ) );
		}

		// Demote any previously-primary photo for this player (not the new row).
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET is_primary = 0 WHERE player_id = %d AND id != %d',
				$player_id,
				$photo_id
			)
		);

		update_post_meta( $player_id, 'player_photo_id', $attachment_id );

		return array(
			'success'       => true,
			'photo_id'      => $photo_id,
			'attachment_id' => (int) $attachment_id,
			'url'           => $uploaded['url'],
			'thumbnail_url' => $thumb ? $thumb : $uploaded['url'],
		);
	}

	/**
	 * Get a player's current primary photo row.
	 *
	 * @param int $player_id Player post ID.
	 * @return array|null
	 */
	public static function get_player_photo( $player_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE player_id = %d AND is_primary = 1 ORDER BY id DESC LIMIT 1',
				absint( $player_id )
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Delete a player photo record.
	 *
	 * @param int $photo_id Photo row ID.
	 * @return true
	 */
	public static function delete_photo( $photo_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => absint( $photo_id ) ), array( '%d' ) );
		return true;
	}
}
