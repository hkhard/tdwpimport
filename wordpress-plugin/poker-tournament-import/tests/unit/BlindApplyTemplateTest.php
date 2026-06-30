<?php
/**
 * Unit tests for the "apply blind template to tournament" logic (tdwp-cma.12).
 *
 * The AJAX handler in TDWP_Blind_Builder_Page delegates level-copying to
 * TDWP_Blind_Schedule::get() and TDWP_Blind_Level::create(). Since those
 * classes require a real wpdb, we test the pure shape/logic guarantees that
 * can be verified offline:
 *
 * 1. The AJAX handler is registered in the PHP source.
 * 2. The nonce and capability checks appear in the handler.
 * 3. The level data shape written to the DB uses canonical column names
 *    (break_duration_minutes, duration_minutes — NOT break_length).
 * 4. The UI panel for "Apply to Tournament" appears in the page source.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * BlindApplyTemplateTest
 *
 * @covers TDWP_Blind_Builder_Page::ajax_apply_template_to_tournament
 */
class BlindApplyTemplateTest extends TestCase {

	/** @var string Full source of blind-builder-page.php */
	private string $page_src;

	protected function setUp(): void {
		$this->page_src = file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/blind-builder-page.php'
		);
	}

	// ── AJAX handler registration ─────────────────────────────────────────────

	public function test_ajax_action_is_registered(): void {
		$this->assertStringContainsString(
			'tdwp_apply_blind_template_to_tournament',
			$this->page_src,
			'AJAX action tdwp_apply_blind_template_to_tournament must be registered'
		);
	}

	public function test_ajax_handler_method_exists_in_source(): void {
		$this->assertStringContainsString(
			'ajax_apply_template_to_tournament',
			$this->page_src,
			'Handler method ajax_apply_template_to_tournament must be defined'
		);
	}

	// ── security: nonce and capability ───────────────────────────────────────

	public function test_handler_calls_check_ajax_referer(): void {
		$this->assertStringContainsString(
			'check_ajax_referer',
			$this->page_src,
			'ajax_apply_template_to_tournament must call check_ajax_referer()'
		);
	}

	public function test_handler_checks_manage_options_capability(): void {
		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$this->page_src,
			'ajax_apply_template_to_tournament must check manage_options capability'
		);
	}

	// ── canonical column names (no break_length propagation) ─────────────────

	public function test_handler_uses_canonical_break_duration_minutes(): void {
		// Find the method *definition* (not the add_action registration).
		$method_start = strpos( $this->page_src, 'public function ajax_apply_template_to_tournament' );
		$this->assertNotFalse( $method_start, 'Method definition must exist' );

		$method_body = substr( $this->page_src, $method_start, 4000 );

		$this->assertStringContainsString(
			'break_duration_minutes',
			$method_body,
			'Handler must use canonical break_duration_minutes, not break_length'
		);

		$this->assertStringNotContainsString(
			"'break_length'",
			$method_body,
			'Handler must not propagate the break_length bug'
		);
	}

	public function test_handler_uses_canonical_duration_minutes(): void {
		$method_start = strpos( $this->page_src, 'public function ajax_apply_template_to_tournament' );
		$method_body  = substr( $this->page_src, $method_start, 4000 );

		$this->assertStringContainsString(
			'duration_minutes',
			$method_body,
			'Handler must use duration_minutes for play-level duration'
		);
	}

	// ── post meta storage ────────────────────────────────────────────────────

	public function test_handler_stores_schedule_id_in_post_meta(): void {
		$method_start = strpos( $this->page_src, 'public function ajax_apply_template_to_tournament' );
		$method_body  = substr( $this->page_src, $method_start, 4000 );

		$this->assertStringContainsString(
			'_tdwp_blind_schedule_id',
			$method_body,
			'Handler must store the schedule ID in _tdwp_blind_schedule_id post meta'
		);
	}

	// ── UI panel ─────────────────────────────────────────────────────────────

	public function test_apply_to_tournament_panel_exists_in_page(): void {
		$this->assertStringContainsString(
			'apply-template-to-tournament',
			$this->page_src,
			'Blind builder page must render the Apply to Tournament UI panel'
		);
	}

	public function test_apply_tournament_id_input_exists(): void {
		$this->assertStringContainsString(
			'apply-tournament-id',
			$this->page_src,
			'Apply panel must include a tournament ID input'
		);
	}

	// ── render_level_row bug fix (break_length → break_duration_minutes) ─────

	public function test_render_level_row_uses_canonical_break_duration_minutes(): void {
		$this->assertStringNotContainsString(
			'$level->break_length',
			$this->page_src,
			'render_level_row must not read $level->break_length (use break_duration_minutes)'
		);

		$this->assertStringContainsString(
			'$level->break_duration_minutes',
			$this->page_src,
			'render_level_row must read $level->break_duration_minutes'
		);
	}
}
