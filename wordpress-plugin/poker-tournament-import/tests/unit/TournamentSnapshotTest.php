<?php
/**
 * Unit tests for TDWP_Tournament_Snapshot pure builder/accessors.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-snapshot.php';

/**
 * @covers TDWP_Tournament_Snapshot
 */
class TournamentSnapshotTest extends TestCase {

	private function sample() {
		return TDWP_Tournament_Snapshot::build(
			array(
				'buy_in'            => 200,
				'starting_chips'    => 20000,
				'rebuy_until_level' => 6,
			),
			array(
				array(
					'level_order'      => 1,
					'big_blind'        => 100,
					'duration_minutes' => 20,
				),
				array(
					'level_order'      => 2,
					'big_blind'        => 200,
					'duration_minutes' => 20,
				),
			),
			array( array( 'position' => 1, 'percentage' => 50 ) )
		);
	}

	public function test_build_structure() {
		$snap = $this->sample();
		$this->assertSame( TDWP_Tournament_Snapshot::VERSION, $snap['version'] );
		$this->assertCount( 2, $snap['blind_levels'] );
		$this->assertCount( 1, $snap['prizes'] );
		$this->assertSame( 200, $snap['template']['buy_in'] );
	}

	public function test_config_reads_template_field() {
		$snap = $this->sample();
		$this->assertSame( 20000, TDWP_Tournament_Snapshot::config( $snap, 'starting_chips' ) );
		$this->assertSame( 6, TDWP_Tournament_Snapshot::config( $snap, 'rebuy_until_level' ) );
	}

	public function test_config_returns_default_for_missing_key() {
		$snap = $this->sample();
		$this->assertSame( 'x', TDWP_Tournament_Snapshot::config( $snap, 'nope', 'x' ) );
		$this->assertNull( TDWP_Tournament_Snapshot::config( array(), 'buy_in' ) );
	}

	public function test_blind_level_lookup() {
		$snap  = $this->sample();
		$level = TDWP_Tournament_Snapshot::blind_level_for( $snap, 2 );
		$this->assertSame( 200, $level['big_blind'] );
		$this->assertSame( 20, $level['duration_minutes'] );
	}

	public function test_blind_level_lookup_miss_returns_null() {
		$this->assertNull( TDWP_Tournament_Snapshot::blind_level_for( $this->sample(), 99 ) );
	}

	public function test_build_handles_empty_inputs() {
		$snap = TDWP_Tournament_Snapshot::build( array(), array(), array() );
		$this->assertSame( array(), $snap['template'] );
		$this->assertSame( array(), $snap['blind_levels'] );
	}
}
