<?php
/**
 * Unit tests for tdwp-67j: tournament history logging.
 *
 * Covers:
 *  - EVENT_TYPES includes 'tournament_created' and 'tournament_modified'
 *  - TDWP_Tournament_Events::log() constructs the correct insert payload shape
 *  - get_event_label() returns human-readable strings for the new types
 *  - ajax_create_tournament logs 'tournament_created' (source-level)
 *  - History is stored in the existing tdwp_tournament_events table (decision doc)
 *
 * Runs offline — no live DB.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Tournament_Events' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
		. 'includes/tournament-manager/class-tournament-events.php';
}

final class TournamentHistoryTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	// -------------------------------------------------------------------------
	// EVENT_TYPES includes the new lifecycle types
	// -------------------------------------------------------------------------

	public function test_event_types_includes_tournament_created(): void {
		$this->assertArrayHasKey(
			'tournament_created',
			TDWP_Tournament_Events::EVENT_TYPES,
			"EVENT_TYPES must include 'tournament_created' (tdwp-67j)."
		);
	}

	public function test_event_types_includes_tournament_modified(): void {
		$this->assertArrayHasKey(
			'tournament_modified',
			TDWP_Tournament_Events::EVENT_TYPES,
			"EVENT_TYPES must include 'tournament_modified' (tdwp-67j)."
		);
	}

	public function test_tournament_created_label_is_non_empty(): void {
		$label = TDWP_Tournament_Events::EVENT_TYPES['tournament_created'];
		$this->assertNotEmpty( $label, "'tournament_created' label must not be empty." );
	}

	public function test_tournament_modified_label_is_non_empty(): void {
		$label = TDWP_Tournament_Events::EVENT_TYPES['tournament_modified'];
		$this->assertNotEmpty( $label, "'tournament_modified' label must not be empty." );
	}

	// -------------------------------------------------------------------------
	// get_event_label() returns sensible strings for the new types
	// -------------------------------------------------------------------------

	public function test_get_event_label_tournament_created(): void {
		$label = TDWP_Tournament_Events::get_event_label( 'tournament_created' );
		$this->assertStringContainsString(
			'Tournament',
			$label,
			"Label for 'tournament_created' should reference 'Tournament'."
		);
	}

	public function test_get_event_label_tournament_modified(): void {
		$label = TDWP_Tournament_Events::get_event_label( 'tournament_modified' );
		$this->assertStringContainsString(
			'Tournament',
			$label,
			"Label for 'tournament_modified' should reference 'Tournament'."
		);
	}

	// -------------------------------------------------------------------------
	// TDWP_Tournament_Events::log() inserts correct payload shape
	// -------------------------------------------------------------------------

	public function test_log_returns_wp_error_for_invalid_event_type(): void {
		global $wpdb;
		$wpdb   = new TDWP_Fake_WPDB();
		$events = new TDWP_Tournament_Events();

		$result = $events->log( 1, 'not_a_real_event', array() );

		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'log() must return WP_Error for an unregistered event type.'
		);
	}

	public function test_log_accepts_tournament_created(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$result = $events->log(
			5,
			'tournament_created',
			array( 'name' => 'Friday Turbo', 'creation_method' => 'blank' ),
			false
		);

		// Result is the inserted row id (fake returns 1) — not a WP_Error.
		$this->assertNotInstanceOf(
			'WP_Error',
			$result,
			"log() must succeed for 'tournament_created'."
		);

		// Confirm the insert payload contained the expected event_type.
		$last = $db->get_last_insert();
		$this->assertIsArray( $last, 'TDWP_Fake_WPDB must record the insert call.' );
		$this->assertSame( 'tournament_created', $last['event_type'] );
		$this->assertSame( 5, (int) $last['tournament_id'] );
	}

	public function test_log_accepts_tournament_modified(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$events = new TDWP_Tournament_Events();
		$result = $events->log( 7, 'tournament_modified', array( 'field' => 'buy_in' ), false );

		$this->assertNotInstanceOf( 'WP_Error', $result );
		$last = $db->get_last_insert();
		$this->assertSame( 'tournament_modified', $last['event_type'] );
		$this->assertSame( 7, (int) $last['tournament_id'] );
	}

	public function test_log_encodes_event_data_as_json(): void {
		global $wpdb;
		$db   = new TDWP_Fake_WPDB();
		$wpdb = $db;

		$data   = array( 'name' => 'Test Tourney', 'creation_method' => 'template' );
		$events = new TDWP_Tournament_Events();
		$events->log( 3, 'tournament_created', $data, false );

		$last = $db->get_last_insert();
		$decoded = json_decode( $last['event_data'], true );
		$this->assertSame( 'Test Tourney', $decoded['name'] );
		$this->assertSame( 'template', $decoded['creation_method'] );
	}

	// -------------------------------------------------------------------------
	// Source-level: ajax_create_tournament logs tournament_created
	// -------------------------------------------------------------------------

	public function test_create_tournament_logs_tournament_created_event(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php'
		);

		$this->assertStringContainsString(
			"'tournament_created'",
			$src,
			"ajax_create_tournament must log the 'tournament_created' event (tdwp-67j)."
		);
	}

	public function test_create_tournament_instantiates_tournament_events(): void {
		$src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php'
		);

		$this->assertStringContainsString(
			'new TDWP_Tournament_Events()',
			$src,
			'ajax_create_tournament must instantiate TDWP_Tournament_Events to log history (tdwp-67j).'
		);
	}

	// -------------------------------------------------------------------------
	// Architecture decision: history uses existing events table (source-level)
	// -------------------------------------------------------------------------

	public function test_history_reuses_tdwp_tournament_events_table(): void {
		// No new table should be created for history; the existing
		// tdwp_tournament_events mechanism covers it.
		$events_class_src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'includes/tournament-manager/class-tournament-events.php'
		);

		$this->assertStringContainsString(
			'tdwp_tournament_events',
			$events_class_src,
			'TDWP_Tournament_Events must use the tdwp_tournament_events table (no new table needed).'
		);

		// Confirm the wizard file does NOT create a new history table.
		$wizard_src = (string) file_get_contents(
			POKER_TOURNAMENT_IMPORT_PLUGIN_DIR
			. 'admin/tournament-manager/live-tournament-wizard.php'
		);
		$this->assertStringNotContainsString(
			'tdwp_tournament_history',
			$wizard_src,
			'Wizard must not create a separate tdwp_tournament_history table — reuse events table.'
		);
	}
}
