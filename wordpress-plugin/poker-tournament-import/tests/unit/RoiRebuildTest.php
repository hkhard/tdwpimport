<?php
/**
 * Unit tests for the poker_player_roi duplication fix (tdwp-ayg part b).
 *
 * poker_player_roi has no unique key, so $wpdb->replace() never dedupes — every
 * (re)write used to append duplicate rows. process_player_roi_data() now clears
 * a tournament's ROI rows before (re)inserting, so re-processing the same
 * tournament is idempotent while distinct tournaments still accumulate.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class RoiRebuildTest extends TestCase {

	/** @var TDWP_Fake_WPDB */
	private $wpdb;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->wpdb = $GLOBALS['wpdb'];
	}

	private function engine(): Poker_Statistics_Engine {
		return Poker_Statistics_Engine::get_instance();
	}

	/** Build tournament_data with N players, each with a single Standard buy-in. */
	private function tournamentData( int $players, float $buyIn = 100.0 ): array {
		$rows = array();
		for ( $i = 1; $i <= $players; $i++ ) {
			$rows[] = array(
				'uuid'            => 'player-' . $i,
				'winnings'        => $i === 1 ? 500.0 : 0.0,
				'finish_position' => $i,
				'buyins'          => array( array( 'profile' => 'Standard' ) ),
			);
		}
		return array(
			'buy_in'  => $buyIn,
			'players' => $rows,
		);
	}

	public function test_reprocessing_same_tournament_is_idempotent(): void {
		$engine = $this->engine();
		$data   = $this->tournamentData( 3 );

		$engine->process_player_roi_data( 'tourney-A', $data );
		$this->assertCount( 3, $this->wpdb->get_roi_rows(), 'First write: one ROI row per player.' );

		// Re-processing must NOT duplicate — delete-then-insert keeps it at 3.
		$engine->process_player_roi_data( 'tourney-A', $data );
		$this->assertCount( 3, $this->wpdb->get_roi_rows(), 'Re-processing the same tournament must not duplicate rows.' );
	}

	public function test_distinct_tournaments_accumulate(): void {
		$engine = $this->engine();

		$engine->process_player_roi_data( 'tourney-A', $this->tournamentData( 2 ) );
		$engine->process_player_roi_data( 'tourney-B', $this->tournamentData( 3 ) );

		$this->assertCount( 5, $this->wpdb->get_roi_rows(), 'Distinct tournaments accumulate (2 + 3).' );
	}

	public function test_roi_rows_are_scoped_per_tournament_uuid(): void {
		$engine = $this->engine();
		$engine->process_player_roi_data( 'tourney-A', $this->tournamentData( 2 ) );
		$engine->process_player_roi_data( 'tourney-B', $this->tournamentData( 2 ) );

		// Re-processing only A must leave B's rows intact.
		$engine->process_player_roi_data( 'tourney-A', $this->tournamentData( 2 ) );

		$rows = $this->wpdb->get_roi_rows();
		$this->assertCount( 4, $rows );
		$byTourney = array();
		foreach ( $rows as $r ) {
			$byTourney[ $r['tournament_id'] ] = ( $byTourney[ $r['tournament_id'] ] ?? 0 ) + 1;
		}
		$this->assertSame( 2, $byTourney['tourney-A'] );
		$this->assertSame( 2, $byTourney['tourney-B'] );
	}
}
