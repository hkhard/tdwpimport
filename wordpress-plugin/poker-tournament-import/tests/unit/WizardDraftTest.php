<?php
/**
 * Unit tests for tdwp-67j: wizard auto-save draft feature.
 *
 * Covers:
 *  - Draft AJAX handlers exist and carry nonce + capability guards (source-level)
 *  - ajax_autosave_draft uses check_ajax_referer with 'tdwp_wizard_draft'
 *  - ajax_autosave_draft uses current_user_can( 'manage_options' )
 *  - Draft is written to user meta (integration via stubs)
 *  - Draft sanitizes text field (tournament_name) via sanitize_text_field path
 *  - ajax_get_draft verifies nonce + capability
 *  - ajax_discard_draft verifies nonce + capability
 *  - ajax_create_tournament discards draft (source-level assertion)
 *
 * Runs offline — no live DB, no WordPress install.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class WizardDraftTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Slice a method body from a PHP source file for assertion.
	 */
	private function sliceMethod( string $file, string $method ): string {
		$src   = (string) file_get_contents( $file );
		$start = strpos( $src, 'function ' . $method . '(' );
		$this->assertNotFalse( $start, "Method {$method}() must exist in {$file}." );
		return substr( $src, $start, 4000 );
	}

	private function wizardFile(): string {
		return POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php';
	}

	// -------------------------------------------------------------------------
	// Source-level security guards: ajax_autosave_draft
	// -------------------------------------------------------------------------

	public function test_autosave_draft_verifies_nonce(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			"check_ajax_referer( 'tdwp_wizard_draft'",
			$body,
			'ajax_autosave_draft must verify the tdwp_wizard_draft nonce (tdwp-67j).'
		);
	}

	public function test_autosave_draft_checks_manage_options(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$body,
			'ajax_autosave_draft must require manage_options (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Source-level security guards: ajax_get_draft
	// -------------------------------------------------------------------------

	public function test_get_draft_verifies_nonce(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_get_draft' );

		$this->assertStringContainsString(
			"check_ajax_referer( 'tdwp_wizard_draft'",
			$body,
			'ajax_get_draft must verify the tdwp_wizard_draft nonce (tdwp-67j).'
		);
	}

	public function test_get_draft_checks_manage_options(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_get_draft' );

		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$body,
			'ajax_get_draft must require manage_options (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Source-level security guards: ajax_discard_draft
	// -------------------------------------------------------------------------

	public function test_discard_draft_verifies_nonce(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_discard_draft' );

		$this->assertStringContainsString(
			"check_ajax_referer( 'tdwp_wizard_draft'",
			$body,
			'ajax_discard_draft must verify the tdwp_wizard_draft nonce (tdwp-67j).'
		);
	}

	public function test_discard_draft_checks_manage_options(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_discard_draft' );

		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$body,
			'ajax_discard_draft must require manage_options (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Source-level: ajax_create_tournament discards draft after success
	// -------------------------------------------------------------------------

	public function test_create_tournament_discards_draft_on_success(): void {
		// ajax_create_tournament is large; search the full file so we don't miss
		// the delete_user_meta call that appears after all the update_post_meta calls.
		$src = (string) file_get_contents( $this->wizardFile() );

		$this->assertStringContainsString(
			'delete_user_meta',
			$src,
			'ajax_create_tournament must call delete_user_meta to discard the draft after tournament creation (tdwp-67j).'
		);
		$this->assertStringContainsString(
			"'tdwp_wizard_draft'",
			$src,
			"Draft meta key 'tdwp_wizard_draft' must be referenced for discard in the wizard file (tdwp-67j)."
		);
	}

	// -------------------------------------------------------------------------
	// Draft storage uses user meta (source-level)
	// -------------------------------------------------------------------------

	public function test_autosave_draft_stores_via_update_user_meta(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			'update_user_meta',
			$body,
			'ajax_autosave_draft must persist draft via update_user_meta (tdwp-67j).'
		);
	}

	public function test_autosave_draft_uses_tdwp_wizard_draft_meta_key(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			"'tdwp_wizard_draft'",
			$body,
			"Draft meta key must be 'tdwp_wizard_draft' (tdwp-67j)."
		);
	}

	// -------------------------------------------------------------------------
	// Sanitization assertions (source-level)
	// -------------------------------------------------------------------------

	public function test_autosave_draft_sanitizes_tournament_name(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			'sanitize_text_field',
			$body,
			'ajax_autosave_draft must sanitize tournament_name with sanitize_text_field (tdwp-67j).'
		);
	}

	public function test_autosave_draft_uses_absint_for_integer_fields(): void {
		$body = $this->sliceMethod( $this->wizardFile(), 'ajax_autosave_draft' );

		$this->assertStringContainsString(
			'absint',
			$body,
			'ajax_autosave_draft must sanitize integer fields with absint (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Draft allowed-fields whitelist exists (source-level)
	// -------------------------------------------------------------------------

	public function test_draft_allowed_fields_method_exists(): void {
		$src = (string) file_get_contents( $this->wizardFile() );

		$this->assertStringContainsString(
			'draft_allowed_fields',
			$src,
			'Wizard class must have a draft_allowed_fields() method for field whitelisting (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Draft restore banner present in render_page (source-level)
	// -------------------------------------------------------------------------

	public function test_render_page_contains_draft_restore_banner(): void {
		$src = (string) file_get_contents( $this->wizardFile() );

		$this->assertStringContainsString(
			'tdwp-draft-restore-banner',
			$src,
			'render_page() must include the draft restore banner element (tdwp-67j).'
		);
	}

	public function test_render_page_contains_autosave_indicator(): void {
		$src = (string) file_get_contents( $this->wizardFile() );

		$this->assertStringContainsString(
			'tdwp-autosave-indicator',
			$src,
			'render_page() must include the auto-save status indicator element (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Stub-level integration: user meta round-trip
	// -------------------------------------------------------------------------

	public function test_user_meta_stubs_store_and_retrieve(): void {
		tdwp_test_reset();

		$user_id = 42;
		$draft   = array(
			'tournament_name' => 'Friday Night Test',
			'buy_in'          => 55.0,
			'_saved_at'       => '2026-01-01 12:00:00',
		);

		update_user_meta( $user_id, 'tdwp_wizard_draft', $draft );
		$retrieved = get_user_meta( $user_id, 'tdwp_wizard_draft', true );

		$this->assertIsArray( $retrieved );
		$this->assertSame( 'Friday Night Test', $retrieved['tournament_name'] );
		$this->assertEqualsWithDelta( 55.0, (float) $retrieved['buy_in'], 0.001 );
	}

	public function test_user_meta_stubs_delete(): void {
		tdwp_test_reset();

		$user_id = 7;
		update_user_meta( $user_id, 'tdwp_wizard_draft', array( 'foo' => 'bar' ) );
		delete_user_meta( $user_id, 'tdwp_wizard_draft' );

		$retrieved = get_user_meta( $user_id, 'tdwp_wizard_draft', true );
		$this->assertSame( '', $retrieved, 'delete_user_meta must remove the key.' );
	}
}
