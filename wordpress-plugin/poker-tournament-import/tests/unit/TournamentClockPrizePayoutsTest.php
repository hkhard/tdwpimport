<?php
/**
 * Unit tests for TDWP_Tournament_Clock_Shortcode::get_prize_payouts() (tdwp-wp7).
 *
 * Exercises the helper via reflection, without constructing the full shortcode
 * class (which wires WordPress hooks/AJAX in its constructor). Pure-logic
 * around the config snapshot — no DB access beyond get_post_meta(), which is
 * backed by the in-memory $GLOBALS['tdwp_test_meta'] fixture from wp-stubs.php.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

$plugin_dir = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR;

require_once $plugin_dir . 'includes/tournament-manager/class-tournament-snapshot.php';
require_once $plugin_dir . 'includes/class-tournament-clock-shortcode.php';

/**
 * @covers TDWP_Tournament_Clock_Shortcode
 */
class TournamentClockPrizePayoutsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['tdwp_test_meta'] = array();
	}

	/**
	 * Call the private get_prize_payouts() method via reflection.
	 */
	private function call_get_prize_payouts( $tournament_id ) {
		$obj = ( new ReflectionClass( 'TDWP_Tournament_Clock_Shortcode' ) )->newInstanceWithoutConstructor();
		$ref = new ReflectionMethod( $obj, 'get_prize_payouts' );
		return $ref->invoke( $obj, $tournament_id );
	}

	public function test_returns_empty_array_when_no_snapshot() {
		$this->assertSame( array(), $this->call_get_prize_payouts( 123 ) );
	}

	public function test_returns_normalized_payouts_from_snapshot() {
		$tournament_id = 42;

		$snapshot = TDWP_Tournament_Snapshot::build(
			array(),
			array(),
			array(
				array(
					'structure_json' => wp_json_encode(
						array(
							array(
								'place'      => 1,
								'amount'     => 500.0,
								'percentage' => null,
								'display'    => true,
							),
							array(
								'place'      => 2,
								'amount'     => null,
								'percentage' => 25.5,
								'display'    => true,
							),
							array(
								'place'      => 3,
								'amount'     => null,
								'percentage' => 10,
								'display'    => false,
							),
						)
					),
				),
			)
		);

		$GLOBALS['tdwp_test_meta'][ $tournament_id ]['_tournament_config_snapshot'] = $snapshot;

		$payouts = $this->call_get_prize_payouts( $tournament_id );

		$this->assertCount( 2, $payouts );
		$this->assertSame( 1, $payouts[0]['place'] );
		$this->assertSame( 500.0, $payouts[0]['amount'] );
		$this->assertNull( $payouts[0]['percentage'] );
		$this->assertSame( 2, $payouts[1]['place'] );
		$this->assertNull( $payouts[1]['amount'] );
		$this->assertSame( 25.5, $payouts[1]['percentage'] );
	}
}
