<?php
/**
 * Unit tests for tdwp-l2l: "Use Template" apply flow in the live tournament wizard.
 *
 * Covers:
 *  - ajax_get_template_data source has nonce + capability guards (security regression)
 *  - Payload shape returned by the handler contains all required financial / timing / relation keys
 *  - Invalid (zero) template_id is rejected before any DB access
 *  - TDWP_Tournament_Template::get() returns all financial fields for a known template
 *  - apply mapping carries entry_fee, prize_pool_contribution, rake_mode, rake_flat_amount,
 *    rebuy timing, and relation ids
 *
 * Pure-logic / source-level — no live DB — runs offline.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Tournament_Template' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-template.php';
}

final class WizardApplyTemplateTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	// -------------------------------------------------------------------------
	// Source-level security guards on ajax_get_template_data (regression).
	// -------------------------------------------------------------------------

	/**
	 * Slice the body of a method from a source file for assertion.
	 */
	private function sliceMethod( string $file, string $method ): string {
		$src   = (string) file_get_contents( $file );
		$start = strpos( $src, 'function ' . $method . '(' );
		$this->assertNotFalse( $start, "Method {$method}() must exist in {$file}." );
		return substr( $src, $start, 3000 );
	}

	public function test_get_template_data_verifies_nonce(): void {
		$wizard_file = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php';
		$body = $this->sliceMethod( $wizard_file, 'ajax_get_template_data' );

		$this->assertStringContainsString(
			"check_ajax_referer( 'tdwp_live_tournament_wizard'",
			$body,
			'ajax_get_template_data must verify the wizard nonce (tdwp-l2l).'
		);
	}

	public function test_get_template_data_checks_manage_options(): void {
		$wizard_file = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php';
		$body = $this->sliceMethod( $wizard_file, 'ajax_get_template_data' );

		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$body,
			'ajax_get_template_data must require manage_options (tdwp-l2l).'
		);
	}

	public function test_get_template_data_uses_absint_for_template_id(): void {
		$wizard_file = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php';
		$body = $this->sliceMethod( $wizard_file, 'ajax_get_template_data' );

		$this->assertStringContainsString(
			'absint',
			$body,
			'template_id must be sanitized with absint() (tdwp-l2l).'
		);
	}

	// -------------------------------------------------------------------------
	// TDWP_Tournament_Template::get() payload shape.
	// -------------------------------------------------------------------------

	/**
	 * Build a fake template row with all financial + relation fields.
	 */
	private function make_template_row( array $overrides = array() ): object {
		$defaults = array(
			'id'                      => 1,
			'name'                    => 'Friday Turbo',
			'description'             => '',
			'buy_in'                  => 55.00,
			'entry_fee'               => 5.00,
			'prize_pool_contribution' => 50.00,
			'rebuy_cost'              => 20.00,
			'rebuy_chips'             => 5000,
			'addon_cost'              => 30.00,
			'addon_chips'             => 8000,
			'starting_chips'          => 10000,
			'rake_percentage'         => 0.0,
			'rake_mode'               => 'flat',
			'rake_flat_amount'        => 5.00,
			'rebuy_until_level'       => 4,
			'rebuy_chip_threshold'    => 3000,
			'rebuy_limit_per_player'  => 2,
			'addon_at_level'          => 5,
			'addon_until_level'       => 5,
			'blind_schedule_id'       => 7,
			'prize_structure_id'      => 3,
			'created_by'              => 1,
			'created_at'              => '2026-01-01 00:00:00',
			'updated_at'              => '2026-01-01 00:00:00',
		);
		return (object) array_merge( $defaults, $overrides );
	}

	public function test_get_returns_null_for_zero_id(): void {
		global $wpdb;
		$wpdb = new TDWP_Fake_WPDB();

		$loader = new TDWP_Tournament_Template();
		$result = $loader->get( 0 );

		$this->assertNull( $result, 'get(0) must return null without a DB round-trip.' );
	}

	public function test_get_returns_all_financial_fields(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		// Wire the fake DB to return our template row.
		$row = $this->make_template_row();
		$db->set_template_row( $row );

		$loader   = new TDWP_Tournament_Template();
		$template = $loader->get( 1, false );

		$this->assertNotNull( $template, 'A valid template_id must return an object.' );

		// Financial fields.
		$this->assertEqualsWithDelta( 55.00, (float) $template->buy_in, 0.001 );
		$this->assertEqualsWithDelta( 5.00,  (float) $template->entry_fee, 0.001 );
		$this->assertEqualsWithDelta( 50.00, (float) $template->prize_pool_contribution, 0.001 );
		$this->assertSame( 'flat',  $template->rake_mode );
		$this->assertEqualsWithDelta( 5.00, (float) $template->rake_flat_amount, 0.001 );

		// Rebuy / add-on timing.
		$this->assertSame( 4, (int) $template->rebuy_until_level );
		$this->assertSame( 3000, (int) $template->rebuy_chip_threshold );
		$this->assertSame( 2, (int) $template->rebuy_limit_per_player );
		$this->assertSame( 5, (int) $template->addon_at_level );
		$this->assertSame( 5, (int) $template->addon_until_level );

		// Relation ids.
		$this->assertSame( 7, (int) $template->blind_schedule_id );
		$this->assertSame( 3, (int) $template->prize_structure_id );
	}

	public function test_get_with_relations_attaches_blind_schedule(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$row = $this->make_template_row();
		$db->set_template_row( $row );
		$db->set_blind_schedule_row( (object) array( 'id' => 7, 'name' => 'Turbo 15-min' ) );
		$db->set_prize_structure_row( (object) array( 'id' => 3, 'name' => 'Top-3 flat' ) );

		$loader   = new TDWP_Tournament_Template();
		$template = $loader->get( 1, true );

		$this->assertNotNull( $template->blind_schedule );
		$this->assertSame( 'Turbo 15-min', $template->blind_schedule->name );
		$this->assertNotNull( $template->prize_structure );
		$this->assertSame( 'Top-3 flat', $template->prize_structure->name );
	}

	public function test_get_with_relations_sets_null_when_no_blind_schedule(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$row = $this->make_template_row( array( 'blind_schedule_id' => 0, 'prize_structure_id' => 0 ) );
		$db->set_template_row( $row );

		$loader   = new TDWP_Tournament_Template();
		$template = $loader->get( 1, true );

		$this->assertNull( $template->blind_schedule,  'No blind_schedule_id → blind_schedule must be null.' );
		$this->assertNull( $template->prize_structure, 'No prize_structure_id → prize_structure must be null.' );
	}

	// -------------------------------------------------------------------------
	// apply-mapping: hidden fields present in wizard form source (regression).
	// -------------------------------------------------------------------------

	public function test_wizard_form_has_hidden_entry_fee_input(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString(
			'name="entry_fee"',
			$src,
			'Wizard form must carry entry_fee as a hidden input so the create handler saves it (tdwp-l2l).'
		);
	}

	public function test_wizard_form_has_hidden_prize_pool_contribution_input(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString(
			'name="prize_pool_contribution"',
			$src
		);
	}

	public function test_wizard_form_has_hidden_rake_mode_input(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString( 'name="rake_mode"', $src );
	}

	public function test_wizard_form_has_hidden_rake_flat_amount_input(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString( 'name="rake_flat_amount"', $src );
	}

	// -------------------------------------------------------------------------
	// ajax_create_tournament saves fee-split / rake-mode meta (regression).
	// -------------------------------------------------------------------------

	public function test_create_tournament_handler_saves_entry_fee_meta(): void {
		// ajax_create_tournament is large; search the full file source for the meta keys
		// rather than a fixed-width method slice.
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString( "'_entry_fee'", $src, 'create handler must save _entry_fee (tdwp-l2l).' );
		$this->assertStringContainsString( "'_prize_pool_contribution'", $src );
		$this->assertStringContainsString( "'_rake_mode'", $src );
		$this->assertStringContainsString( "'_rake_flat_amount'", $src );
	}
}
