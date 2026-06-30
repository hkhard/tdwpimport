<?php
/**
 * Unit tests for TDWP_Event_Dispatcher pure logic.
 *
 * Covers priority resolution and queue-row construction (no DB).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-event-dispatcher.php';

/**
 * @covers TDWP_Event_Dispatcher
 */
class EventDispatcherTest extends TestCase {

	public function test_mapped_event_types_get_their_priority() {
		$this->assertSame( 1, TDWP_Event_Dispatcher::get_priority_for_event( 'tournament_ended' ) );
		$this->assertSame( 2, TDWP_Event_Dispatcher::get_priority_for_event( 'player_busted' ) );
		$this->assertSame( 3, TDWP_Event_Dispatcher::get_priority_for_event( 'level_changed' ) );
	}

	public function test_unmapped_event_type_gets_default_priority() {
		$this->assertSame(
			TDWP_Event_Dispatcher::DEFAULT_PRIORITY,
			TDWP_Event_Dispatcher::get_priority_for_event( 'something_unknown' )
		);
	}

	public function test_build_queue_row_shape_and_status() {
		$row = TDWP_Event_Dispatcher::build_queue_row( 42, 7, 'player_busted', array( 'seat' => 3 ) );

		$this->assertSame( 7, $row['tournament_id'] );
		$this->assertSame( 'player_busted', $row['event_type'] );
		$this->assertSame( 2, $row['priority'] );
		$this->assertSame( 'pending', $row['status'] );

		$decoded = json_decode( $row['event_data'], true );
		$this->assertSame( 42, $decoded['event_id'] );
		$this->assertSame( array( 'seat' => 3 ), $decoded['data'] );
	}

	public function test_build_queue_row_null_tournament_when_empty() {
		$row = TDWP_Event_Dispatcher::build_queue_row( 1, 0, 'level_changed' );
		$this->assertNull( $row['tournament_id'] );
		$this->assertSame( 3, $row['priority'] );
	}

	public function test_event_id_is_coerced_to_absint() {
		$row     = TDWP_Event_Dispatcher::build_queue_row( '99abc', 5, 'table_broken' );
		$decoded = json_decode( $row['event_data'], true );
		$this->assertSame( 99, $decoded['event_id'] );
		$this->assertSame( 4, $row['priority'] );
	}
}
