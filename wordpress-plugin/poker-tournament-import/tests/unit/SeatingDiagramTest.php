<?php
/**
 * Unit tests for TDWP_Seating_Diagram pure layout logic.
 *
 * Covers seat-slot expansion, occupancy mapping, and circular positioning
 * (no DB).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-seating-diagram.php';

/**
 * @covers TDWP_Seating_Diagram
 */
class SeatingDiagramTest extends TestCase {

	public function test_table_expands_to_max_seats() {
		$tables = array(
			array(
				'id'           => 5,
				'table_number' => 1,
				'max_seats'    => 9,
				'status'       => 'active',
			),
		);
		$layout = TDWP_Seating_Diagram::build_layout( $tables, array() );
		$this->assertCount( 1, $layout );
		$this->assertCount( 9, $layout[0]['seats'] );
		$this->assertSame( 9, $layout[0]['empty_seats'] );
		$this->assertSame( 0, $layout[0]['occupied_seats'] );
	}

	public function test_occupied_and_empty_seats_are_marked() {
		$tables = array(
			array(
				'id'           => 5,
				'table_number' => 1,
				'max_seats'    => 6,
				'status'       => 'active',
			),
		);
		$seats  = array(
			array(
				'table_id'    => 5,
				'seat_number' => 2,
				'player_id'   => 101,
				'status'      => 'occupied',
			),
			array(
				'table_id'    => 5,
				'seat_number' => 4,
				'player_id'   => null,
				'status'      => 'empty',
			),
		);
		$layout = TDWP_Seating_Diagram::build_layout( $tables, $seats );

		$this->assertSame( 1, $layout[0]['occupied_seats'] );
		$this->assertSame( 5, $layout[0]['empty_seats'] );

		$seat2 = $layout[0]['seats'][1];
		$this->assertTrue( $seat2['occupied'] );
		$this->assertSame( 101, $seat2['player_id'] );

		$seat4 = $layout[0]['seats'][3];
		$this->assertFalse( $seat4['occupied'] );
		$this->assertNull( $seat4['player_id'] );
	}

	public function test_seats_for_wrong_table_are_ignored() {
		$tables = array(
			array(
				'id'        => 5,
				'max_seats' => 4,
			),
		);
		$seats  = array(
			array(
				'table_id'    => 99,
				'seat_number' => 1,
				'player_id'   => 7,
				'status'      => 'occupied',
			),
		);
		$layout = TDWP_Seating_Diagram::build_layout( $tables, $seats );
		$this->assertSame( 0, $layout[0]['occupied_seats'] );
	}

	public function test_missing_max_seats_defaults_to_nine() {
		$layout = TDWP_Seating_Diagram::build_layout( array( array( 'id' => 1 ) ), array() );
		$this->assertSame( 9, $layout[0]['max_seats'] );
		$this->assertCount( 9, $layout[0]['seats'] );
	}

	public function test_seat_one_is_at_top() {
		$pos = TDWP_Seating_Diagram::seat_position( 1, 8 );
		$this->assertSame( 0.0, $pos['angle'] );
		// Top of the circle: x≈0, y≈-1.
		$this->assertEqualsWithDelta( 0.0, $pos['x'], 0.0001 );
		$this->assertEqualsWithDelta( -1.0, $pos['y'], 0.0001 );
	}

	public function test_seat_angles_are_evenly_distributed() {
		$this->assertSame( 90.0, TDWP_Seating_Diagram::seat_position( 3, 8 )['angle'] );
		$this->assertSame( 180.0, TDWP_Seating_Diagram::seat_position( 5, 8 )['angle'] );
	}

	public function test_positions_are_on_the_unit_circle() {
		for ( $n = 1; $n <= 9; $n++ ) {
			$pos = TDWP_Seating_Diagram::seat_position( $n, 9 );
			$radius = sqrt( ( $pos['x'] * $pos['x'] ) + ( $pos['y'] * $pos['y'] ) );
			$this->assertEqualsWithDelta( 1.0, $radius, 0.001 );
		}
	}
}
