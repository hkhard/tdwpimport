<?php
/**
 * Unit tests for TDWP_Player_DB_Exporter (tdwp-cma.6).
 *
 * Verifies the player-DATABASE CSV export contains the player-profile columns
 * (name, email, phone, notes, avatar, uuid) plus aggregate statistics, distinct
 * from the tournament-results export.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerDbExportTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	private function samplePlayer(): array {
		return array(
			'id'    => 5,
			'name'  => 'Jane Doe',
			'bio'   => '<strong>VIP</strong> regular',
			'meta'  => array(
				'email'      => 'jane@example.com',
				'phone'      => '555-1234',
				'avatar_url' => 'https://example.com/jane.png',
				'uuid'       => 'uuid-jane',
			),
			'stats' => array(
				'tournaments'    => 12,
				'wins'           => 3,
				'final_tables'   => 7,
				'total_winnings' => 1500.5,
				'average_finish' => 4.25,
			),
		);
	}

	public function test_header_row_lists_profile_and_stat_columns(): void {
		$columns = TDWP_Player_DB_Exporter::get_columns();

		foreach ( array( 'Name', 'Email', 'Phone', 'Notes', 'Avatar URL', 'UUID', 'Tournaments', 'Total Winnings' ) as $expected ) {
			$this->assertContains( $expected, $columns );
		}
	}

	public function test_build_csv_contains_player_profile_values(): void {
		$csv = TDWP_Player_DB_Exporter::build_csv( array( $this->samplePlayer() ) );

		$this->assertStringContainsString( 'Jane Doe', $csv );
		$this->assertStringContainsString( 'jane@example.com', $csv );
		$this->assertStringContainsString( '555-1234', $csv );
		$this->assertStringContainsString( 'https://example.com/jane.png', $csv );
		$this->assertStringContainsString( 'uuid-jane', $csv );
		// Notes/bio is included with HTML stripped.
		$this->assertStringContainsString( 'VIP regular', $csv );
		$this->assertStringNotContainsString( '<strong>', $csv );
		// Aggregate stats present.
		$this->assertStringContainsString( '1500.50', $csv );
	}

	public function test_build_csv_has_header_plus_one_row_per_player(): void {
		$csv   = TDWP_Player_DB_Exporter::build_csv( array( $this->samplePlayer(), $this->samplePlayer() ) );
		$lines = array_values( array_filter( explode( "\n", trim( $csv ) ) ) );

		// 1 header + 2 data rows.
		$this->assertCount( 3, $lines );
	}
}
