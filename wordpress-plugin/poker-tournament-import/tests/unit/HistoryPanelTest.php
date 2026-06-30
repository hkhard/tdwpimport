<?php
/**
 * Unit tests for tdwp-cma.28: history panel + event wiring.
 *
 * Covers:
 *  - get_events() returns events for a tournament in chronological order
 *  - tournament_started event is written with correct type + payload shape
 *  - tournament_completed event is written with correct type + payload shape
 *  - tournament_modified event is registered in EVENT_TYPES
 *  - get_event_label() returns sensible strings for started/completed/modified
 *  - Source-level: history tab file exists and references TDWP_Tournament_Events
 *  - Source-level: wizard registers save_post_live_tournament hook for modified
 *  - Source-level: AJAX class logs tournament_started on start_tournament success
 *  - Source-level: AJAX class logs tournament_completed on finish_tournament success
 *
 * Runs offline — no live DB required.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Tournament_Events' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
		. 'includes/tournament-manager/class-tournament-events.php';
}

final class HistoryPanelTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	// -------------------------------------------------------------------------
	// EVENT_TYPES completeness
	// -------------------------------------------------------------------------

	public function test_event_types_includes_tournament_started(): void {
		$this->assertArrayHasKey(
			'tournament_started',
			TDWP_Tournament_Events::EVENT_TYPES,
			"EVENT_TYPES must include 'tournament_started'."
		);
	}

	public function test_event_types_includes_tournament_completed(): void {
		$this->assertArrayHasKey(
			'tournament_completed',
			TDWP_Tournament_Events::EVENT_TYPES,
			"EVENT_TYPES must include 'tournament_completed'."
		);
	}

	public function test_event_types_includes_tournament_modified(): void {
		$this->assertArrayHasKey(
			'tournament_modified',
			TDWP_Tournament_Events::EVENT_TYPES,
			"EVENT_TYPES must include 'tournament_modified'."
		);
	}

	// -------------------------------------------------------------------------
	// get_event_label() human-readable labels
	// -------------------------------------------------------------------------

	public function test_label_tournament_started_contains_tournament(): void {
		$label = TDWP_Tournament_Events::get_event_label( 'tournament_started' );
		$this->assertStringContainsString( 'Tournament', $label );
	}

	public function test_label_tournament_completed_contains_tournament(): void {
		$label = TDWP_Tournament_Events::get_event_label( 'tournament_completed' );
		$this->assertStringContainsString( 'Tournament', $label );
	}

	public function test_label_tournament_modified_contains_tournament(): void {
		$label = TDWP_Tournament_Events::get_event_label( 'tournament_modified' );
		$this->assertStringContainsString( 'Tournament', $label );
	}

	// -------------------------------------------------------------------------
	// log() + get_events() round-trip via fake wpdb
	// -------------------------------------------------------------------------

	public function test_log_tournament_started_writes_correct_event_type(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$result = $events->log( 10, 'tournament_started', array( 'level_duration' => 900 ) );

		$this->assertNotInstanceOf( 'WP_Error', $result, 'log() must succeed for tournament_started.' );

		$last = $db->get_last_insert();
		$this->assertIsArray( $last );
		$this->assertSame( 'tournament_started', $last['event_type'] );
		$this->assertSame( 10, (int) $last['tournament_id'] );
	}

	public function test_log_tournament_started_encodes_payload(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$events->log( 10, 'tournament_started', array( 'level_duration' => 900 ) );

		$last    = $db->get_last_insert();
		$decoded = json_decode( $last['event_data'], true );
		$this->assertSame( 900, $decoded['level_duration'] );
	}

	public function test_log_tournament_completed_writes_correct_event_type(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$result = $events->log( 11, 'tournament_completed', array( 'status' => 'finished' ) );

		$this->assertNotInstanceOf( 'WP_Error', $result, 'log() must succeed for tournament_completed.' );

		$last = $db->get_last_insert();
		$this->assertSame( 'tournament_completed', $last['event_type'] );
		$this->assertSame( 11, (int) $last['tournament_id'] );
	}

	public function test_log_tournament_modified_writes_correct_event_type(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$result = $events->log( 12, 'tournament_modified', array( 'post_title' => 'Friday Turbo' ) );

		$this->assertNotInstanceOf( 'WP_Error', $result, 'log() must succeed for tournament_modified.' );

		$last = $db->get_last_insert();
		$this->assertSame( 'tournament_modified', $last['event_type'] );
		$this->assertSame( 12, (int) $last['tournament_id'] );
	}

	// -------------------------------------------------------------------------
	// get_events() SQL shape — source-level checks
	// -------------------------------------------------------------------------

	public function test_get_events_sql_includes_order_by_created_at(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'includes/tournament-manager/class-tournament-events.php'
		);
		$this->assertStringContainsString(
			'ORDER BY created_at',
			$src,
			'get_events() must order results by created_at for chronological display.'
		);
	}

	public function test_get_events_sql_filters_by_tournament_id(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'includes/tournament-manager/class-tournament-events.php'
		);
		$this->assertStringContainsString(
			'tournament_id = %d',
			$src,
			'get_events() must filter by tournament_id using a prepared placeholder.'
		);
	}

	// -------------------------------------------------------------------------
	// Source-level: history tab file exists and is wired correctly
	// -------------------------------------------------------------------------

	public function test_history_tab_file_exists(): void {
		$path = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tabs/history-tab.php';
		$this->assertFileExists( $path, 'admin/tabs/history-tab.php must exist (tdwp-cma.28).' );
	}

	public function test_history_tab_references_tournament_events_class(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tabs/history-tab.php'
		);
		$this->assertStringContainsString(
			'TDWP_Tournament_Events',
			$src,
			'history-tab.php must use TDWP_Tournament_Events to fetch events.'
		);
	}

	public function test_history_tab_escapes_output(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tabs/history-tab.php'
		);
		$this->assertStringContainsString(
			'esc_html(',
			$src,
			'history-tab.php must use esc_html() for all dynamic output.'
		);
	}

	public function test_history_tab_included_in_control_page(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/tournament-manager-control.php'
		);
		$this->assertStringContainsString(
			'history-tab.php',
			$src,
			'tournament-manager-control.php must include history-tab.php.'
		);
	}

	// -------------------------------------------------------------------------
	// Source-level: wizard wires save_post_live_tournament for tournament_modified
	// -------------------------------------------------------------------------

	public function test_wizard_registers_save_post_hook_for_modified(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString(
			'save_post_live_tournament',
			$src,
			'Wizard must register save_post_live_tournament hook for tournament_modified (tdwp-cma.28).'
		);
	}

	public function test_wizard_logs_tournament_modified_in_hook_handler(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringContainsString(
			"'tournament_modified'",
			$src,
			"Wizard save-post handler must log 'tournament_modified' event (tdwp-cma.28)."
		);
	}

	// -------------------------------------------------------------------------
	// Source-level: AJAX class logs started/completed events
	// -------------------------------------------------------------------------

	public function test_ajax_class_logs_tournament_started(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-tournament-manager-ajax.php'
		);
		$this->assertStringContainsString(
			"'tournament_started'",
			$src,
			"class-tournament-manager-ajax.php must log 'tournament_started' (tdwp-cma.28)."
		);
	}

	public function test_ajax_class_logs_tournament_completed(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-tournament-manager-ajax.php'
		);
		$this->assertStringContainsString(
			"'tournament_completed'",
			$src,
			"class-tournament-manager-ajax.php must log 'tournament_completed' (tdwp-cma.28)."
		);
	}
}
