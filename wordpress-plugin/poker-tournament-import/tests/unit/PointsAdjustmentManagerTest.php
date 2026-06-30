<?php
/**
 * Unit tests for Poker_Points_Adjustment_Manager pure guards.
 *
 * The map/audit queries require a real database (verified live on LocalWP);
 * here we cover the input guards that need no DB.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-points-adjustment-manager.php';

/**
 * @covers Poker_Points_Adjustment_Manager
 */
class PointsAdjustmentManagerTest extends TestCase {

	public function test_empty_uuid_list_returns_empty_map() {
		$manager = new Poker_Points_Adjustment_Manager();
		$this->assertSame( array(), $manager->get_adjustment_map( array() ) );
	}

	public function test_filtered_falsey_uuids_return_empty_map() {
		$manager = new Poker_Points_Adjustment_Manager();
		$this->assertSame( array(), $manager->get_adjustment_map( array( '', null, false ) ) );
	}
}
