<?php
/**
 * Regression tests for TD-sourced points: PointsAdjustment, entry counting, and
 * the surrender pot contribution.
 *
 * Backstory: the winner of ORF_Poker_20260827 has `PointsAdjustment: 50` in the
 * .tdt, and Tournament Director 3.7.2 reports 399 points, but the plugin imported
 * 299. Investigating that single 100-point gap surfaced three independent defects
 * in how we feed the TD points formula:
 *
 *   1. `PointsAdjustment` was never parsed at all. TD applies it *after* the
 *      formula (the tournament's own PointsForPlaying script never references it),
 *      so it is a post-calculation addend, not a formula input.        [+50]
 *
 *   2. The formula variable `n` is the number of ENTRIES, not the number of
 *      distinct players. With re-entries these differ (20 vs 14 here). Note that
 *      `buyins` remains the PLAYER count — they are genuinely different
 *      variables, despite our validator historically aliasing them.    [+49]
 *
 *   3. A surrender (a GameBustOut carrying no HitmanUUID — the player busted
 *      themselves rather than being eliminated) contributes an extra 100 to the
 *      pot that the buy-in Amounts do not account for.                 [+1]
 *
 * These expectations are not derived from our own implementation: each figure was
 * confirmed against Tournament Director 3.7.2 by the tournament director. That is
 * what makes this file an oracle rather than a snapshot of current behaviour.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class TdtPointsAdjustmentTest extends TestCase {

	/**
	 * Winner expectations, verified against Tournament Director 3.7.2.
	 *
	 * fixture => [winner nickname, expected total points, hits, adjustment]
	 *
	 * @return array<string,array{string,string,int,int,int}>
	 */
	public static function winners(): array {
		return array(
			// The reported case: 5 hits (50) + 50 adjustment on a base of 299.
			'20260827 (has a +50 adjustment and a surrender)' =>
				array( 'ORF_Poker_20260827.tdt', 'Gabriel C', 399, 5, 50 ),
			// Also has a surrender, but no points adjustment. Discriminates the
			// surrender rule from the adjustment rule.
			'20260521 (surrender, no adjustment)' =>
				array( 'ORF_Poker_20260521.tdt', 'Hans KH', 368, 6, 0 ),
			// Clean controls: re-entries but no surrender and no adjustment, so
			// they isolate the n=entries change on its own.
			'20260604 (re-entries only)' =>
				array( 'ORF_Poker_20260604.tdt', 'Mikael C', 290, 3, 0 ),
			'20260617 (re-entries only)' =>
				array( 'ORF_Poker_20260617.tdt', 'John', 321, 5, 0 ),
			'20260704 (re-entries only)' =>
				array( 'ORF_Poker_20260704.tdt', 'Bosse', 298, 2, 0 ),
		);
	}

	private function parseFixture( string $file ): array {
		$path = POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'tests/fixtures/turrar/' . $file;
		$this->assertFileExists( $path, 'Missing tournament fixture.' );

		$parser = new Poker_Tournament_Parser( $path );

		ob_start();
		try {
			$parser->parse_file();
		} finally {
			ob_end_clean();
		}

		$data = $parser->get_tournament_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data['players'] ?? array(), 'Parser returned no players.' );

		return $data;
	}

	/**
	 * Find one player's record by nickname.
	 */
	private function player( array $data, string $nickname ): array {
		foreach ( $data['players'] as $player ) {
			if ( ( $player['nickname'] ?? null ) === $nickname ) {
				return $player;
			}
		}

		$this->fail( sprintf( 'Player "%s" not found in the parsed fixture.', $nickname ) );
	}

	/* ---------------------------------------------------------------------
	 * The headline expectation: our total must equal Tournament Director's.
	 * ------------------------------------------------------------------ */

	#[PHPUnit\Framework\Attributes\DataProvider( 'winners' )]
	public function test_winner_points_match_tournament_director(
		string $file,
		string $nickname,
		int $expected,
		int $hits,
		int $adjustment
	): void {
		$data   = $this->parseFixture( $file );
		$player = $this->player( $data, $nickname );

		$this->assertSame(
			1,
			(int) $player['finish_position'],
			sprintf( '%s should be the winner of %s.', $nickname, $file )
		);

		$this->assertSame(
			$hits,
			(int) $player['hits'],
			sprintf( 'Hit count for %s drives the points total and must be exact.', $nickname )
		);

		$this->assertSame(
			$expected,
			(int) round( (float) $player['points'] ),
			sprintf(
				"%s in %s should score %d points (verified in Tournament Director 3.7.2).\n"
				. 'Adjustment on this player: %d.',
				$nickname,
				$file,
				$expected,
				$adjustment
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * The individual mechanisms, so a failure localises to one defect.
	 * ------------------------------------------------------------------ */

	/**
	 * The adjustment must be captured from the file and kept as its own field,
	 * so the audit trail can explain why a total differs from the raw formula.
	 */
	public function test_points_adjustment_is_parsed_and_isolated(): void {
		$data = $this->parseFixture( 'ORF_Poker_20260827.tdt' );

		$winner = $this->player( $data, 'Gabriel C' );
		$this->assertSame(
			50.0,
			(float) ( $winner['points_adjustment'] ?? 0 ),
			'Gabriel C carries PointsAdjustment: 50 in the .tdt.'
		);

		$adjusted = 0;
		foreach ( $data['players'] as $player ) {
			if ( 0.0 !== (float) ( $player['points_adjustment'] ?? 0 ) ) {
				$adjusted++;
			}
		}

		$this->assertSame( 1, $adjusted, 'Exactly one player in this tournament has an adjustment.' );
	}

	/**
	 * Every other fixture has no adjustments at all. This guards against a
	 * mis-parse that sprays a default onto everyone.
	 */
	public function test_other_fixtures_have_no_adjustments(): void {
		foreach ( array( 'ORF_Poker_20260521.tdt', 'ORF_Poker_20260604.tdt', 'ORF_Poker_20260617.tdt', 'ORF_Poker_20260704.tdt' ) as $file ) {
			$data = $this->parseFixture( $file );

			foreach ( $data['players'] as $player ) {
				$this->assertSame(
					0.0,
					(float) ( $player['points_adjustment'] ?? 0 ),
					sprintf( '%s should contain no points adjustments.', $file )
				);
			}
		}
	}

	/**
	 * `n` is the entry count and `buyins` the player count. With re-entries these
	 * differ, and conflating them was worth 49 points to the winner here.
	 */
	public function test_entry_count_and_player_count_are_distinct(): void {
		$data = $this->parseFixture( 'ORF_Poker_20260827.tdt' );

		$this->assertSame( 14, count( $data['players'] ), 'Fixture has 14 distinct players.' );

		$entries = 0;
		foreach ( $data['players'] as $player ) {
			$entries += max( 1, count( $player['buyins'] ?? array() ) );
		}

		$this->assertSame( 20, $entries, 'Fixture has 20 entries (6 re-entries).' );
	}

	/**
	 * A tournament with no re-entries, no adjustments and no surrenders must be
	 * completely unaffected by any of these three changes.
	 *
	 * ORF_Poker_20250605 is the only file in the repo's tdtfiles/ where entries
	 * equal players (10 = 10), which makes it the sole true control: for every
	 * other historical tournament `n` legitimately changes, because they all
	 * contain re-entries.
	 */
	public function test_unaffected_tournament_is_unchanged(): void {
		$path = dirname( POKER_TOURNAMENT_IMPORT_PLUGIN_DIR, 2 ) . '/tdtfiles/ORF_Poker_20250605.tdt';

		if ( ! is_file( $path ) ) {
			$this->markTestSkipped( 'Repo tdtfiles/ fixture not present.' );
		}

		$parser = new Poker_Tournament_Parser( $path );
		ob_start();
		try {
			$parser->parse_file();
		} finally {
			ob_end_clean();
		}

		$data = $parser->get_tournament_data();

		$entries = 0;
		foreach ( $data['players'] as $player ) {
			$entries += max( 1, count( $player['buyins'] ?? array() ) );
		}
		$this->assertSame(
			count( $data['players'] ),
			$entries,
			'Precondition: this control fixture must have no re-entries.'
		);

		$sum = 0;
		foreach ( $data['players'] as $player ) {
			$sum += (int) round( (float) ( $player['points'] ?? 0 ) );
		}

		$this->assertSame(
			718,
			$sum,
			'A tournament with no re-entries, adjustments or surrenders must keep its existing point total.'
		);
	}

	/**
	 * The surrender contribution is a house rule, so it must be filterable.
	 *
	 * 100 per surrender is what reconciles against TD 3.7.2, but a league using
	 * a different stake has to be able to change it without editing the plugin.
	 * This drives the documented hook and asserts the points actually move,
	 * which a filter that was declared but never dispatched would fail.
	 */
	public function test_surrender_contribution_is_filterable(): void {
		remove_all_filters( 'poker_tournament_surrender_contribution' );

		// Baseline: the default 100 per surrender gives TD's figure.
		$baseline = $this->parseFixture( 'ORF_Poker_20260827.tdt' );
		$this->assertSame( 399, $this->winnerPoints( $baseline ), 'Baseline must match TD.' );

		// Setting the contribution to zero removes it from the pot, which must
		// lower the winner's points. This is the pre-3.9.10 behaviour.
		add_filter( 'poker_tournament_surrender_contribution', static fn() => 0.0 );
		$without = $this->parseFixture( 'ORF_Poker_20260827.tdt' );
		remove_all_filters( 'poker_tournament_surrender_contribution' );

		$this->assertLessThan(
			399,
			$this->winnerPoints( $without ),
			'Zeroing the surrender contribution must reduce the winner\'s points.'
		);

		// A larger stake must push the points the other way.
		add_filter( 'poker_tournament_surrender_contribution', static fn() => 500.0 );
		$larger = $this->parseFixture( 'ORF_Poker_20260827.tdt' );
		remove_all_filters( 'poker_tournament_surrender_contribution' );

		$this->assertGreaterThan(
			399,
			$this->winnerPoints( $larger ),
			'A larger surrender contribution must increase the winner\'s points.'
		);
	}

	/**
	 * Winner's points from a parsed fixture.
	 *
	 * @param array $data Parsed tournament data.
	 * @return int
	 */
	private function winnerPoints( array $data ): int {
		foreach ( $data['players'] as $player ) {
			if ( 1 === (int) ( $player['finish_position'] ?? 0 ) ) {
				return (int) round( (float) ( $player['points'] ?? 0 ) );
			}
		}
		$this->fail( 'No winner found in parsed data.' );
	}
}
