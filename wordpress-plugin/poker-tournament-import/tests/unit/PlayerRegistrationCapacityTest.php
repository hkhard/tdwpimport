<?php
/**
 * Unit tests for player registration capacity, waitlist, and confirmation email.
 *
 * Covers beads tdwp-cma.2 (registrant confirmation email), tdwp-cma.3 (waitlist
 * for overflow registrations), and tdwp-cma.4 (available-seats computation).
 *
 * Exercises only the new helper methods added to TDWP_Tournament_Player_Manager
 * and TDWP_Player_Registration so no AJAX plumbing, $wpdb live queries, or
 * WordPress install is required.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class PlayerRegistrationCapacityTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	/* -----------------------------------------------------------------------
	 * TDWP_Tournament_Player_Manager::get_confirmed_count() (cma.3 / cma.4)
	 * --------------------------------------------------------------------- */

	public function test_get_confirmed_count_returns_zero_when_no_rows(): void {
		// Arrange: fake wpdb returns 0 confirmed for tournament 1.
		$GLOBALS['wpdb']->set_confirmed_count( 1, 0 );

		// Act.
		$count = TDWP_Tournament_Player_Manager::get_confirmed_count( 1 );

		// Assert.
		$this->assertSame( 0, $count );
	}

	public function test_get_confirmed_count_returns_preset_value(): void {
		$GLOBALS['wpdb']->set_confirmed_count( 7, 5 );

		$this->assertSame( 5, TDWP_Tournament_Player_Manager::get_confirmed_count( 7 ) );
	}

	public function test_get_confirmed_count_is_per_tournament(): void {
		$GLOBALS['wpdb']->set_confirmed_count( 1, 3 );
		$GLOBALS['wpdb']->set_confirmed_count( 2, 8 );

		$this->assertSame( 3, TDWP_Tournament_Player_Manager::get_confirmed_count( 1 ) );
		$this->assertSame( 8, TDWP_Tournament_Player_Manager::get_confirmed_count( 2 ) );
	}

	/* -----------------------------------------------------------------------
	 * TDWP_Tournament_Player_Manager::get_next_waitlist_position() (cma.3)
	 * --------------------------------------------------------------------- */

	public function test_get_next_waitlist_position_returns_one_when_no_waitlisted(): void {
		// Arrange: fake MAX returns 0 (COALESCE result when no rows).
		$GLOBALS['wpdb']->set_max_waitlist_position( 1, 0 );

		$this->assertSame( 1, TDWP_Tournament_Player_Manager::get_next_waitlist_position( 1 ) );
	}

	public function test_get_next_waitlist_position_increments_from_current_max(): void {
		$GLOBALS['wpdb']->set_max_waitlist_position( 1, 3 );

		$this->assertSame( 4, TDWP_Tournament_Player_Manager::get_next_waitlist_position( 1 ) );
	}

	/* -----------------------------------------------------------------------
	 * TDWP_Player_Registration::get_capacity_info() (cma.4)
	 * --------------------------------------------------------------------- */

	/**
	 * Helper: build a registration instance without triggering the constructor's
	 * require_once / add_action calls. We use ReflectionClass to access the
	 * method without instantiation.
	 */
	private function makeRegistration(): object {
		// We test get_capacity_info() and send_registrant_confirmation() as
		// instance methods, so we need an object. Bypass __construct() via
		// ReflectionClass so we don't need the full WP environment.
		$ref = new ReflectionClass( TDWP_Player_Registration::class );
		return $ref->newInstanceWithoutConstructor();
	}

	public function test_capacity_info_unlimited_when_no_tournament(): void {
		$reg  = $this->makeRegistration();
		$info = $reg->get_capacity_info( 0 );

		$this->assertTrue( $info['is_unlimited'] );
		$this->assertFalse( $info['is_full'] );
	}

	public function test_capacity_info_unlimited_when_max_players_not_set(): void {
		// get_post_meta returns '' (falsy) by default → max = 0 → unlimited.
		$reg  = $this->makeRegistration();
		$info = $reg->get_capacity_info( 42 );

		$this->assertTrue( $info['is_unlimited'] );
		$this->assertFalse( $info['is_full'] );
	}

	public function test_capacity_info_not_full_when_under_capacity(): void {
		// max_players = 10, confirmed = 6 → remaining 4, not full.
		update_post_meta( 10, '_max_players', 10 );
		$GLOBALS['wpdb']->set_confirmed_count( 10, 6 );

		$reg  = $this->makeRegistration();
		$info = $reg->get_capacity_info( 10 );

		$this->assertFalse( $info['is_unlimited'] );
		$this->assertFalse( $info['is_full'] );
		$this->assertSame( 4, $info['remaining'] );
		$this->assertSame( 6, $info['confirmed'] );
	}

	public function test_capacity_info_is_full_when_at_capacity(): void {
		// max_players = 8, confirmed = 8 → full.
		update_post_meta( 20, '_max_players', 8 );
		$GLOBALS['wpdb']->set_confirmed_count( 20, 8 );

		$reg  = $this->makeRegistration();
		$info = $reg->get_capacity_info( 20 );

		$this->assertTrue( $info['is_full'] );
		$this->assertSame( 0, $info['remaining'] );
	}

	public function test_capacity_info_remaining_floors_at_zero_when_over_capacity(): void {
		// Defensive: confirmed may exceed max if max was lowered after registrations.
		update_post_meta( 30, '_max_players', 5 );
		$GLOBALS['wpdb']->set_confirmed_count( 30, 7 );

		$reg  = $this->makeRegistration();
		$info = $reg->get_capacity_info( 30 );

		$this->assertTrue( $info['is_full'] );
		$this->assertSame( 0, $info['remaining'] );
	}

	/* -----------------------------------------------------------------------
	 * TDWP_Player_Registration::send_registrant_confirmation() (cma.2)
	 * --------------------------------------------------------------------- */

	public function test_confirmation_email_sent_to_registrant_address(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Jane Doe', 'email' => 'jane@example.com' ),
			'confirmed',
			'Friday Night Poker'
		);

		$mail = $GLOBALS['tdwp_test_mail'];
		$this->assertCount( 1, $mail, 'Exactly one email should be sent to the registrant.' );
		$this->assertSame( 'jane@example.com', $mail[0]['to'] );
	}

	public function test_confirmation_email_subject_reflects_confirmed_status(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Jane Doe', 'email' => 'jane@example.com' ),
			'confirmed',
			'Friday Night Poker'
		);

		$this->assertStringContainsString( 'confirmed', strtolower( $GLOBALS['tdwp_test_mail'][0]['subject'] ) );
	}

	public function test_confirmation_email_subject_reflects_waitlisted_status(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Jane Doe', 'email' => 'jane@example.com' ),
			'waitlisted',
			'Friday Night Poker',
			2
		);

		$this->assertStringContainsString( 'waiting list', strtolower( $GLOBALS['tdwp_test_mail'][0]['subject'] ) );
	}

	public function test_waitlisted_email_body_contains_position(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Bob', 'email' => 'bob@example.com' ),
			'waitlisted',
			'Saturday Poker',
			3
		);

		$body = $GLOBALS['tdwp_test_mail'][0]['message'];
		$this->assertStringContainsString( '#3', $body, 'Body must include the waitlist position.' );
	}

	public function test_confirmed_email_body_contains_tournament_name(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Alice', 'email' => 'alice@example.com' ),
			'confirmed',
			'Sunday Deepstack'
		);

		$body = $GLOBALS['tdwp_test_mail'][0]['message'];
		$this->assertStringContainsString( 'Sunday Deepstack', $body );
	}

	public function test_no_email_sent_when_email_address_is_empty(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'No Email', 'email' => '' ),
			'confirmed'
		);

		$this->assertEmpty( $GLOBALS['tdwp_test_mail'], 'No mail should be sent when no email address is supplied.' );
	}

	public function test_confirmed_email_works_without_tournament_name(): void {
		$reg = $this->makeRegistration();
		$reg->send_registrant_confirmation(
			array( 'name' => 'Chris', 'email' => 'chris@example.com' ),
			'confirmed'
		);

		$mail = $GLOBALS['tdwp_test_mail'];
		$this->assertCount( 1, $mail );
		$this->assertStringContainsString( 'chris@example.com', $mail[0]['to'] );
	}
}
