<?php
/**
 * Unit tests for the AJAX security hardening cluster.
 *
 * Behavioural coverage of TDWP_Ajax_Guards (the security primitives the frontend
 * AJAX handlers delegate to) plus source-level regression guards that the
 * hardened handlers keep their nonce/capability/throttle/content checks and that
 * the debug test handler stays removed. (beads tdwp-7k5, tdwp-uee, tdwp-kws,
 * tdwp-bxp, tdwp-cdq, tdwp-gwp)
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class AjaxSecurityTest extends TestCase {

	/** @var string[] Temp files to clean up. */
	private $tmp_files = array();

	protected function setUp(): void {
		tdwp_test_reset();
	}

	protected function tearDown(): void {
		foreach ( $this->tmp_files as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f );
			}
		}
		$this->tmp_files = array();
	}

	/** Helper: write a temp file with the given bytes, return its path. */
	private function tmpFile( string $bytes ): string {
		$path = tempnam( sys_get_temp_dir(), 'tdwp_test_' );
		file_put_contents( $path, $bytes );
		$this->tmp_files[] = $path;
		return $path;
	}

	private function pluginSource(): string {
		return (string) file_get_contents( POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'poker-tournament-import.php' );
	}

	/* -----------------------------------------------------------------------
	 * unregister_screen_nonce_action() — IDOR fix (tdwp-bxp)
	 * --------------------------------------------------------------------- */

	public function test_nonce_action_is_scoped_to_the_screen_id(): void {
		$a = TDWP_Ajax_Guards::unregister_screen_nonce_action( 5 );
		$b = TDWP_Ajax_Guards::unregister_screen_nonce_action( 6 );

		$this->assertNotSame( $a, $b, 'Each screen must get a distinct nonce action so a kiosk cannot forge another screen.' );
		$this->assertSame( 'tdwp_unregister_screen_5', $a );
	}

	public function test_nonce_action_is_stable_for_the_same_screen(): void {
		$this->assertSame(
			TDWP_Ajax_Guards::unregister_screen_nonce_action( 42 ),
			TDWP_Ajax_Guards::unregister_screen_nonce_action( 42 )
		);
	}

	public function test_nonce_action_coerces_input_with_absint(): void {
		// String numerics normalise; non-numeric / negative collapse to 0 (never
		// interpolated raw), so a crafted screen_id cannot smuggle a foreign action.
		$this->assertSame( 'tdwp_unregister_screen_7', TDWP_Ajax_Guards::unregister_screen_nonce_action( '7' ) );
		$this->assertSame( 'tdwp_unregister_screen_0', TDWP_Ajax_Guards::unregister_screen_nonce_action( 'abc' ) );
		$this->assertSame( 'tdwp_unregister_screen_3', TDWP_Ajax_Guards::unregister_screen_nonce_action( -3 ) );
	}

	/* -----------------------------------------------------------------------
	 * is_text_upload() — content sniff (tdwp-gwp)
	 * --------------------------------------------------------------------- */

	public function test_text_upload_accepts_plain_text(): void {
		$path = $this->tmpFile( "TournamentName: Friday Game\nPlayers: 12\n" );
		$this->assertTrue( TDWP_Ajax_Guards::is_text_upload( $path ) );
	}

	public function test_text_upload_rejects_binary_with_nul_bytes(): void {
		// A PNG header contains NUL bytes — the classic "binary renamed to .tdt".
		$path = $this->tmpFile( "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR" );
		$this->assertFalse( TDWP_Ajax_Guards::is_text_upload( $path ) );
	}

	public function test_text_upload_rejects_empty_file(): void {
		$path = $this->tmpFile( '' );
		$this->assertFalse( TDWP_Ajax_Guards::is_text_upload( $path ) );
	}

	public function test_text_upload_rejects_missing_or_invalid_path(): void {
		$this->assertFalse( TDWP_Ajax_Guards::is_text_upload( '/no/such/file/here.tdt' ) );
		$this->assertFalse( TDWP_Ajax_Guards::is_text_upload( '' ) );
	}

	/* -----------------------------------------------------------------------
	 * is_throttled() — DoS throttle (tdwp-cdq)
	 * --------------------------------------------------------------------- */

	public function test_throttle_allows_first_call_then_blocks_within_window(): void {
		$key = 'tdwp_frontend_stats_refresh_lock';

		$this->assertFalse( TDWP_Ajax_Guards::is_throttled( $key, 30 ), 'First call should proceed.' );
		$this->assertTrue( TDWP_Ajax_Guards::is_throttled( $key, 30 ), 'Second call within the window should be blocked.' );
		$this->assertTrue( TDWP_Ajax_Guards::is_throttled( $key, 30 ) );
	}

	public function test_throttle_allows_again_after_window_elapses(): void {
		$key = 'tdwp_frontend_stats_refresh_lock';

		$this->assertFalse( TDWP_Ajax_Guards::is_throttled( $key, 30 ) );
		$this->assertTrue( TDWP_Ajax_Guards::is_throttled( $key, 30 ) );

		// Simulate the throttle window elapsing.
		delete_transient( $key );

		$this->assertFalse( TDWP_Ajax_Guards::is_throttled( $key, 30 ), 'After the window the call should proceed again.' );
	}

	public function test_throttle_keys_are_independent(): void {
		$this->assertFalse( TDWP_Ajax_Guards::is_throttled( 'lock_a', 30 ) );
		$this->assertFalse( TDWP_Ajax_Guards::is_throttled( 'lock_b', 30 ), 'A distinct key must not be blocked by another lock.' );
		$this->assertTrue( TDWP_Ajax_Guards::is_throttled( 'lock_a', 30 ) );
	}

	/* -----------------------------------------------------------------------
	 * Source-level regression guards on the hardened handlers.
	 * --------------------------------------------------------------------- */

	public function test_debug_test_handler_is_removed(): void {
		$src = $this->pluginSource();
		$this->assertStringNotContainsString( 'tdwp_ajax_test', $src, 'The debug AJAX test handler/registration must stay removed (tdwp-uee).' );
		$this->assertStringNotContainsString( 'tdwp_ajax_test_handler', $src );
	}

	public function test_no_request_or_files_debug_dumps_remain(): void {
		$src = $this->pluginSource();
		$this->assertStringNotContainsString( 'print_r($_REQUEST', $src, 'Request dumps must be removed (tdwp-kws).' );
		$this->assertStringNotContainsString( 'print_r($_FILES', $src );
		$this->assertStringNotContainsString( '[TDWP Beta', $src, 'Beta debug markers must be removed (tdwp-kws).' );
	}

	public function test_import_handler_requires_capability_and_validates_upload(): void {
		$src = $this->pluginSource();
		$import = $this->sliceFunction( $src, 'ajax_frontend_import_tournament' );

		$this->assertStringContainsString( "check_ajax_referer('tdwp_frontend_import_tournament'", $import );
		$this->assertStringContainsString( "current_user_can('edit_posts')", $import, 'Import must require a real capability, not just login (tdwp-gwp).' );
		$this->assertStringContainsString( 'TDWP_Ajax_Guards::is_text_upload', $import, 'Import must content-sniff the upload (tdwp-gwp).' );
		$this->assertStringContainsString( 'tdwp_max_tdt_upload_bytes', $import, 'Import must enforce a size cap (tdwp-gwp).' );
	}

	public function test_refresh_handler_requires_manage_options_and_throttles(): void {
		$src = $this->pluginSource();
		$refresh = $this->sliceFunction( $src, 'ajax_frontend_refresh_statistics' );

		$this->assertStringContainsString( "check_ajax_referer('poker_frontend_stats'", $refresh );
		$this->assertStringContainsString( "current_user_can('manage_options')", $refresh, 'Refresh must require manage_options (tdwp-cdq).' );
		$this->assertStringContainsString( 'TDWP_Ajax_Guards::is_throttled', $refresh, 'Refresh must be throttled (tdwp-cdq).' );
	}

	public function test_unregister_handler_uses_per_screen_nonce(): void {
		$src = $this->pluginSource();
		$unreg = $this->sliceFunction( $src, 'ajax_unregister_screen' );

		$this->assertStringContainsString( 'TDWP_Ajax_Guards::unregister_screen_nonce_action', $unreg, 'Unregister must use the screen-scoped nonce (tdwp-bxp).' );
		// The old global display nonce must no longer guard this state change.
		$this->assertStringNotContainsString( "check_ajax_referer('tdwp_display_nonce'", $unreg );
	}

	/** Helper: return the source of a method body (best-effort brace match). */
	private function sliceFunction( string $src, string $name ): string {
		$start = strpos( $src, 'function ' . $name . '(' );
		$this->assertNotFalse( $start, "Handler {$name}() must exist in the plugin source." );
		// Grab a generous window; the handlers are well under this size.
		return substr( $src, $start, 2600 );
	}
}
