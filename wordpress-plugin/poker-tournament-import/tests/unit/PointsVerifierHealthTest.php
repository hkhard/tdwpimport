<?php
/**
 * Unit tests for Poker_Tournament_Points_Verifier::get_health_flags().
 *
 * Pure severity-classification logic — no database or WP runtime required
 * beyond the shared stubs.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-points-verifier.php';

/**
 * @covers Poker_Tournament_Points_Verifier
 */
class PointsVerifierHealthTest extends TestCase {

	/**
	 * Verifier under test.
	 *
	 * @var Poker_Tournament_Points_Verifier
	 */
	private $verifier;

	protected function setUp(): void {
		parent::setUp();
		$this->verifier = new Poker_Tournament_Points_Verifier();
	}

	public function test_all_positive_points_are_ok() {
		$health = $this->verifier->get_health_flags( array( 10, 20, 5.5 ) );
		$this->assertSame( 'ok', $health['severity'] );
		$this->assertFalse( $health['has_negatives'] );
		$this->assertSame( 0, $health['anomaly_count'] );
		$this->assertSame( 35.5, $health['sum'] );
	}

	public function test_negative_points_are_critical() {
		$health = $this->verifier->get_health_flags( array( 10, -3, 5 ) );
		$this->assertSame( 'critical', $health['severity'] );
		$this->assertTrue( $health['has_negatives'] );
		$this->assertNotEmpty( $health['messages'] );
	}

	public function test_zero_points_are_warning() {
		$health = $this->verifier->get_health_flags( array( 10, 0, 0, 5 ) );
		$this->assertSame( 'warning', $health['severity'] );
		$this->assertSame( 2, $health['zero_count'] );
	}

	public function test_zero_monies_flag_raises_warning() {
		$health = $this->verifier->get_health_flags( array( 10, 20 ), true );
		$this->assertSame( 'warning', $health['severity'] );
	}

	public function test_negative_outranks_zero_monies() {
		$health = $this->verifier->get_health_flags( array( -1, 0 ), true );
		$this->assertSame( 'critical', $health['severity'] );
	}
}
