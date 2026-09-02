<?php
/**
 * tdwp-dup: on upgrade, the participation mart must be deduped BEFORE statistics
 * are recalculated from it.
 *
 * Sites upgraded from before 3.9.11 ran no migrations, so poker_tournament_players
 * can lack UNIQUE(tournament_id, player_id) and hold one duplicate row per player
 * per tournament. That doubles season points. The repair existed but ran *after*
 * calculate_all_statistics(), so stats were aggregated from the duplicated rows and
 * remained inflated. This asserts the source order in check_plugin_upgmdate().
 */

use PHPUnit\Framework\TestCase;

class UpgradeRepairOrderTest extends TestCase {

	/** Source of the check_plugin_upgmdate() method body only. */
	private function upgrade_method_source(): string {
		$src = file_get_contents( dirname( __DIR__, 2 ) . '/poker-tournament-import.php' );
		$this->assertNotFalse( $src, 'plugin bootstrap should be readable' );

		$start = strpos( $src, 'private function check_plugin_upgmdate()' );
		$this->assertNotFalse( $start, 'check_plugin_upgmdate() should exist' );

		// Walk braces to find the end of the method.
		$open  = strpos( $src, '{', $start );
		$depth = 0;
		for ( $i = $open; $i < strlen( $src ); $i++ ) {
			if ( '{' === $src[ $i ] ) {
				$depth++;
			} elseif ( '}' === $src[ $i ] ) {
				$depth--;
				if ( 0 === $depth ) {
					return substr( $src, $start, $i - $start + 1 );
				}
			}
		}

		$this->fail( 'could not delimit check_plugin_upgmdate()' );
	}

	public function test_mart_is_deduped_before_statistics_are_recalculated(): void {
		$body = $this->upgrade_method_source();

		$dedup = strpos( $body, 'ensure_participation_unique_index()' );
		$stats = strpos( $body, 'calculate_all_statistics()' );

		$this->assertNotFalse( $dedup, 'upgrade must enforce the participation UNIQUE index' );
		$this->assertNotFalse( $stats, 'upgrade must refresh statistics' );

		$this->assertLessThan(
			$stats,
			$dedup,
			'ensure_participation_unique_index() must run BEFORE calculate_all_statistics(), '
			. 'otherwise statistics are rebuilt from duplicated participation rows and season '
			. 'points stay doubled.'
		);
	}

	public function test_orphan_reconcile_also_precedes_statistics(): void {
		$body = $this->upgrade_method_source();

		$orphans = strpos( $body, 'reconcile_orphan_participation_rows()' );
		$stats   = strpos( $body, 'calculate_all_statistics()' );

		$this->assertNotFalse( $orphans, 'upgrade must reconcile orphaned participation rows' );
		$this->assertLessThan(
			$stats,
			$orphans,
			'orphaned rows must be removed before statistics aggregate over them.'
		);
	}
}
