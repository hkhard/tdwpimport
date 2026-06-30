<?php
/**
 * Unit tests for TDWP_Results_Emailer pure logic.
 *
 * Covers subject/body construction, ITM filtering, address parsing, and
 * recipient resolution (no DB, no mail).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-results-emailer.php';

/**
 * @covers TDWP_Results_Emailer
 */
class ResultsEmailerTest extends TestCase {

	private function rows() {
		return array(
			array(
				'position' => 3,
				'name'     => 'Carol',
				'prize'    => 0,
				'email'    => 'carol@example.com',
			),
			array(
				'position' => 1,
				'name'     => 'Alice',
				'prize'    => 500,
				'email'    => 'alice@example.com',
			),
			array(
				'position' => 2,
				'name'     => 'Bob',
				'prize'    => 250,
				'email'    => '',
			),
		);
	}

	public function test_subject_uses_tournament_name() {
		$this->assertStringContainsString( 'Friday Deepstack', TDWP_Results_Emailer::build_subject( 'Friday Deepstack' ) );
	}

	public function test_subject_falls_back_when_empty() {
		$this->assertNotSame( '', trim( TDWP_Results_Emailer::build_subject( '' ) ) );
	}

	public function test_filter_itm_keeps_only_prized() {
		$itm = TDWP_Results_Emailer::filter_itm( $this->rows() );
		$this->assertCount( 2, $itm );
		$names = array_column( $itm, 'name' );
		$this->assertContains( 'Alice', $names );
		$this->assertContains( 'Bob', $names );
		$this->assertNotContains( 'Carol', $names );
	}

	public function test_body_is_ordered_by_finish_position() {
		$body  = TDWP_Results_Emailer::build_body( 'My Event', $this->rows() );
		$posA  = strpos( $body, 'Alice' );
		$posB  = strpos( $body, 'Bob' );
		$posC  = strpos( $body, 'Carol' );
		$this->assertNotFalse( $posA );
		$this->assertLessThan( $posB, $posA );
		$this->assertLessThan( $posC, $posB );
	}

	public function test_parse_address_list_dedupes_and_validates() {
		$addresses = TDWP_Results_Emailer::parse_address_list( "a@x.com, b@x.com\nA@X.com; not-an-email  c@x.com" );
		sort( $addresses );
		$this->assertSame( array( 'a@x.com', 'b@x.com', 'c@x.com' ), $addresses );
	}

	public function test_resolve_recipients_all_uses_player_emails() {
		$emails = TDWP_Results_Emailer::resolve_recipients( $this->rows(), 'all' );
		// Bob has no email; Alice + Carol do.
		$this->assertContains( 'alice@example.com', $emails );
		$this->assertContains( 'carol@example.com', $emails );
		$this->assertCount( 2, $emails );
	}

	public function test_resolve_recipients_itm_only_prized_with_email() {
		$emails = TDWP_Results_Emailer::resolve_recipients( $this->rows(), 'itm' );
		// Alice is ITM + has email; Bob ITM but no email; Carol has email but not ITM.
		$this->assertSame( array( 'alice@example.com' ), $emails );
	}

	public function test_resolve_recipients_specific_uses_explicit_list() {
		$emails = TDWP_Results_Emailer::resolve_recipients( $this->rows(), 'specific', 'x@y.com, z@y.com' );
		sort( $emails );
		$this->assertSame( array( 'x@y.com', 'z@y.com' ), $emails );
	}
}
