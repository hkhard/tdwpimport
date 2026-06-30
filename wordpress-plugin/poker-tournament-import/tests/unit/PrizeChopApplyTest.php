<?php
/**
 * Unit tests for chop persistence (tdwp-cma.22) and tournament-integrated chop
 * entry point (tdwp-cma.23).
 *
 * Covers:
 *  - The ≥2-players gate: apply_chop_to_tournament() rejects a single-player request.
 *  - Event payload shape: a logged prize_chop event contains chop_type, amounts, and
 *    chopped=true.
 *  - Post-meta persistence: the _tdwp_chop_applied meta is written with chopped=true.
 *
 * Runs fully offline under the no-database harness (no MySQL, no WP install).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

// ── Minimal stubs for symbols not yet in wp-stubs.php ─────────────────────────

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

// ── Load classes under test ────────────────────────────────────────────────────

$_plugin_dir = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR;

// class-prize-structure.php defines TDWP_Prize_Structure; load it once here so
// PrizeStructureSuggestTest.php can also require_once it without a redeclaration
// error (require_once is idempotent by path).
require_once $_plugin_dir . 'includes/tournament-manager/class-prize-structure.php';
require_once $_plugin_dir . 'includes/tournament-manager/class-prize-calculator.php';
require_once $_plugin_dir . 'includes/tournament-manager/class-tournament-events.php';
require_once $_plugin_dir . 'admin/tournament-manager/prize-calculator-page.php';

// ── Test suite ─────────────────────────────────────────────────────────────────

final class PrizeChopApplyTest extends TestCase {

	private TDWP_Prize_Calculator_Page $page;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->page = new TDWP_Prize_Calculator_Page();
	}

	// ── ≥2 players gate ────────────────────────────────────────────────────────

	public function test_rejects_zero_players(): void {
		$result = $this->page->apply_chop_to_tournament( 42, 'chip', 1000.0, array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'insufficient_players', $result->get_error_code() );
	}

	public function test_rejects_single_player(): void {
		$result = $this->page->apply_chop_to_tournament(
			42,
			'chip',
			1000.0,
			array( array( 'name' => 'Alice', 'chips' => 10000 ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'insufficient_players', $result->get_error_code() );
	}

	// ── Event log payload shape (cma.22) ───────────────────────────────────────

	public function test_apply_chip_chop_logs_event_with_correct_shape(): void {
		$players = array(
			array( 'name' => 'Alice', 'chips' => 60000 ),
			array( 'name' => 'Bob', 'chips' => 40000 ),
		);

		$result = $this->page->apply_chop_to_tournament( 42, 'chip', 1000.0, $players );

		// Return value must be an array, not WP_Error.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'chop_type', $result );
		$this->assertArrayHasKey( 'amounts', $result );
		$this->assertArrayHasKey( 'chopped', $result );
		$this->assertTrue( $result['chopped'] );
		$this->assertSame( 'chip', $result['chop_type'] );

		// Exactly one event must have been inserted.
		$event_rows = $GLOBALS['wpdb']->get_event_rows();
		$this->assertCount( 1, $event_rows );

		$event      = $event_rows[0];
		$event_data = json_decode( $event['event_data'], true );

		$this->assertSame( 42, (int) $event['tournament_id'] );
		$this->assertSame( 'prize_chop', $event['event_type'] );
		$this->assertSame( 'chip', $event_data['chop_type'] );
		$this->assertArrayHasKey( 'amounts', $event_data );
		$this->assertTrue( $event_data['chopped'] );
		$this->assertSame( 1000.0, (float) $event_data['total'] );
	}

	public function test_apply_even_chop_logs_event(): void {
		$players = array(
			array( 'name' => 'Alice', 'chips' => 50000 ),
			array( 'name' => 'Bob', 'chips' => 30000 ),
			array( 'name' => 'Carol', 'chips' => 20000 ),
		);

		$this->page->apply_chop_to_tournament( 7, 'even', 3000.0, $players );

		$event_rows = $GLOBALS['wpdb']->get_event_rows();
		$this->assertCount( 1, $event_rows );

		$event_data = json_decode( $event_rows[0]['event_data'], true );
		$this->assertSame( 'even', $event_data['chop_type'] );
		$this->assertArrayHasKey( 'Alice', $event_data['amounts'] );
		$this->assertArrayHasKey( 'Bob', $event_data['amounts'] );
		$this->assertArrayHasKey( 'Carol', $event_data['amounts'] );
	}

	// ── Post-meta 'chopped' flag (cma.22) ─────────────────────────────────────

	public function test_sets_chopped_flag_in_post_meta(): void {
		$players = array(
			array( 'name' => 'Alice', 'chips' => 60000 ),
			array( 'name' => 'Bob', 'chips' => 40000 ),
		);

		$this->page->apply_chop_to_tournament( 99, 'chip', 2000.0, $players );

		$meta = get_post_meta( 99, '_tdwp_chop_applied', true );

		$this->assertIsArray( $meta );
		$this->assertTrue( $meta['chopped'] );
		$this->assertSame( 'chip', $meta['chop_type'] );
		$this->assertSame( 2000.0, (float) $meta['total'] );
		$this->assertArrayHasKey( 'amounts', $meta );
	}

	public function test_meta_amounts_sum_to_pool(): void {
		$players = array(
			array( 'name' => 'Alice', 'chips' => 60000 ),
			array( 'name' => 'Bob', 'chips' => 40000 ),
		);

		$this->page->apply_chop_to_tournament( 12, 'chip', 1000.0, $players );

		$meta  = get_post_meta( 12, '_tdwp_chop_applied', true );
		$total = array_sum( $meta['amounts'] );

		$this->assertEqualsWithDelta( 1000.0, $total, 0.01 );
	}

	// ── Two separate tournaments get separate meta entries ─────────────────────

	public function test_each_tournament_stores_independent_meta(): void {
		$players = array(
			array( 'name' => 'Alice', 'chips' => 50000 ),
			array( 'name' => 'Bob', 'chips' => 50000 ),
		);

		$this->page->apply_chop_to_tournament( 11, 'even', 500.0, $players );
		$this->page->apply_chop_to_tournament( 22, 'chip', 800.0, $players );

		$meta_11 = get_post_meta( 11, '_tdwp_chop_applied', true );
		$meta_22 = get_post_meta( 22, '_tdwp_chop_applied', true );

		$this->assertSame( 500.0, (float) $meta_11['total'] );
		$this->assertSame( 800.0, (float) $meta_22['total'] );

		// Two events in the log — one per tournament.
		$this->assertCount( 2, $GLOBALS['wpdb']->get_event_rows() );
	}
}
