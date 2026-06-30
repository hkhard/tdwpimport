<?php
/**
 * Unit tests for TDWP_Player_Manager::merge_players() (tdwp-cma.1).
 *
 * Verifies that merging re-points the UUID-keyed data-mart rows from the source
 * player to the target player, removes the source record, and rejects invalid
 * input (same id / missing player).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerMergeTest extends TestCase {

	/** @var TDWP_Fake_WPDB */
	private $wpdb;

	/** @var TDWP_Player_Manager */
	private $manager;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->wpdb    = $GLOBALS['wpdb'];
		$this->manager = new TDWP_Player_Manager();
	}

	public function test_merge_repoints_participation_and_roi_rows(): void {
		tdwp_test_register_player( 10, 'uuid-source', 'Dupe Bob' );
		tdwp_test_register_player( 20, 'uuid-target', 'Bob' );

		// Source has two participation rows and one ROI row; target has one of each.
		$this->wpdb->seed_legacy_row( array( 'player_id' => 'uuid-source', 'tournament_id' => 'T1' ) );
		$this->wpdb->seed_legacy_row( array( 'player_id' => 'uuid-source', 'tournament_id' => 'T2' ) );
		$this->wpdb->seed_legacy_row( array( 'player_id' => 'uuid-target', 'tournament_id' => 'T3' ) );
		$this->wpdb->seed_roi_row( array( 'player_id' => 'uuid-source', 'tournament_id' => 'T1' ) );

		$result = $this->manager->merge_players( 10, 20 );

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['repointed']['poker_tournament_players'] );
		$this->assertSame( 1, $result['repointed']['poker_player_roi'] );

		// No participation row should still reference the source uuid.
		foreach ( $this->wpdb->get_legacy_rows() as $row ) {
			$this->assertNotSame( 'uuid-source', $row['player_id'], 'Source uuid must be fully re-pointed.' );
		}
		// All three participation rows now belong to the target.
		$target_rows = array_filter(
			$this->wpdb->get_legacy_rows(),
			static function ( $r ) {
				return 'uuid-target' === $r['player_id'];
			}
		);
		$this->assertCount( 3, $target_rows );

		// ROI row re-pointed too.
		foreach ( $this->wpdb->get_roi_rows() as $row ) {
			$this->assertSame( 'uuid-target', $row['player_id'] );
		}
	}

	public function test_merge_deletes_source_player(): void {
		tdwp_test_register_player( 10, 'uuid-source' );
		tdwp_test_register_player( 20, 'uuid-target' );

		$this->manager->merge_players( 10, 20 );

		$this->assertNull( get_post( 10 ), 'Source player must be removed after merge.' );
		$this->assertNotNull( get_post( 20 ), 'Target player must remain after merge.' );
	}

	public function test_merge_rejects_same_id(): void {
		tdwp_test_register_player( 10, 'uuid-source' );

		$result = $this->manager->merge_players( 10, 10 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'same_player', $result->get_error_code() );
	}

	public function test_merge_rejects_missing_source(): void {
		tdwp_test_register_player( 20, 'uuid-target' );

		$result = $this->manager->merge_players( 999, 20 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_source', $result->get_error_code() );
	}

	public function test_merge_rejects_missing_uuid(): void {
		tdwp_test_register_player( 10, '' ); // No uuid.
		tdwp_test_register_player( 20, 'uuid-target' );

		$result = $this->manager->merge_players( 10, 20 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_uuid', $result->get_error_code() );
	}
}
