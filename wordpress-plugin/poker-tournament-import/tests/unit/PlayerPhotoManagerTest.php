<?php
/**
 * Unit tests for TDWP_Player_Photo_Manager pure validators.
 *
 * Covers extension parsing, image-type allow-listing, size limits, and
 * uploaded-file-array validation (no IO).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-photo-manager.php';

/**
 * @covers TDWP_Player_Photo_Manager
 */
class PlayerPhotoManagerTest extends TestCase {

	public function test_get_extension_is_lowercased() {
		$this->assertSame( 'jpg', TDWP_Player_Photo_Manager::get_extension( 'Photo.JPG' ) );
		$this->assertSame( 'png', TDWP_Player_Photo_Manager::get_extension( 'a/b/c.png' ) );
		$this->assertSame( '', TDWP_Player_Photo_Manager::get_extension( 'noext' ) );
	}

	public function test_allowed_image_types() {
		$this->assertTrue( TDWP_Player_Photo_Manager::is_allowed_image_type( 'image/jpeg', 'p.jpg' ) );
		$this->assertTrue( TDWP_Player_Photo_Manager::is_allowed_image_type( 'image/png', 'p.png' ) );
		$this->assertTrue( TDWP_Player_Photo_Manager::is_allowed_image_type( 'image/webp', 'p.webp' ) );
	}

	public function test_rejects_disallowed_mime() {
		$this->assertFalse( TDWP_Player_Photo_Manager::is_allowed_image_type( 'image/gif', 'p.gif' ) );
		$this->assertFalse( TDWP_Player_Photo_Manager::is_allowed_image_type( 'application/pdf', 'p.pdf' ) );
	}

	public function test_rejects_mime_extension_mismatch() {
		// A PHP file masquerading with an image MIME must still be rejected on extension.
		$this->assertFalse( TDWP_Player_Photo_Manager::is_allowed_image_type( 'image/jpeg', 'evil.php' ) );
	}

	public function test_size_limit() {
		$this->assertTrue( TDWP_Player_Photo_Manager::is_within_size_limit( 1024 ) );
		$this->assertTrue( TDWP_Player_Photo_Manager::is_within_size_limit( TDWP_Player_Photo_Manager::MAX_SIZE ) );
		$this->assertFalse( TDWP_Player_Photo_Manager::is_within_size_limit( TDWP_Player_Photo_Manager::MAX_SIZE + 1 ) );
		$this->assertFalse( TDWP_Player_Photo_Manager::is_within_size_limit( 0 ) );
	}

	public function test_validate_upload_accepts_good_file() {
		$file = array(
			'name'  => 'avatar.png',
			'type'  => 'image/png',
			'size'  => 2048,
			'error' => UPLOAD_ERR_OK,
		);
		$this->assertTrue( TDWP_Player_Photo_Manager::validate_upload( $file ) );
	}

	public function test_validate_upload_rejects_php_disguised_as_image() {
		$file = array(
			'name'  => 'shell.php',
			'type'  => 'image/png',
			'size'  => 2048,
			'error' => UPLOAD_ERR_OK,
		);
		$result = TDWP_Player_Photo_Manager::validate_upload( $file );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_type', $result->get_error_code() );
	}

	public function test_validate_upload_rejects_oversize() {
		$file = array(
			'name'  => 'big.jpg',
			'type'  => 'image/jpeg',
			'size'  => TDWP_Player_Photo_Manager::MAX_SIZE + 1,
			'error' => UPLOAD_ERR_OK,
		);
		$result = TDWP_Player_Photo_Manager::validate_upload( $file );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'too_large', $result->get_error_code() );
	}

	public function test_validate_upload_rejects_upload_error() {
		$file = array(
			'name'  => 'x.jpg',
			'type'  => 'image/jpeg',
			'size'  => 100,
			'error' => UPLOAD_ERR_PARTIAL,
		);
		$result = TDWP_Player_Photo_Manager::validate_upload( $file );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'upload_error', $result->get_error_code() );
	}

	public function test_validate_upload_rejects_empty() {
		$result = TDWP_Player_Photo_Manager::validate_upload( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_file', $result->get_error_code() );
	}
}
