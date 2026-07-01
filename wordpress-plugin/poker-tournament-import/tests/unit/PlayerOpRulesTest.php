<?php
/**
 * Unit tests for TDWP_Player_Op_Rules (rebuy / add-on / late-reg guards).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-player-op-rules.php';

/**
 * @covers TDWP_Player_Op_Rules
 */
class PlayerOpRulesTest extends TestCase {

	// ---- rebuy ---------------------------------------------------------

	public function test_rebuy_allowed_within_window_and_limit() {
		// until level 6, limit 2, no threshold, at level 3, 1 rebuy so far.
		$this->assertTrue( TDWP_Player_Op_Rules::can_rebuy( 1, 5000, 6, 2, 0, 3 ) );
	}

	public function test_rebuy_blocked_when_not_allowed() {
		$r = TDWP_Player_Op_Rules::can_rebuy( 0, 5000, 0, 0, 0, 1 );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'rebuy_not_allowed', $r->get_error_code() );
	}

	public function test_rebuy_blocked_after_period() {
		$r = TDWP_Player_Op_Rules::can_rebuy( 0, 5000, 6, 0, 0, 7 );
		$this->assertSame( 'rebuy_period_ended', $r->get_error_code() );
	}

	public function test_rebuy_blocked_at_limit() {
		$r = TDWP_Player_Op_Rules::can_rebuy( 2, 5000, 6, 2, 0, 3 );
		$this->assertSame( 'rebuy_limit_reached', $r->get_error_code() );
	}

	public function test_rebuy_blocked_above_chip_threshold() {
		// threshold 3000, player has 5000 -> too many chips to rebuy.
		$r = TDWP_Player_Op_Rules::can_rebuy( 0, 5000, 6, 0, 3000, 3 );
		$this->assertSame( 'rebuy_stack_too_large', $r->get_error_code() );
	}

	public function test_rebuy_allowed_at_or_below_threshold() {
		$this->assertTrue( TDWP_Player_Op_Rules::can_rebuy( 0, 3000, 6, 0, 3000, 3 ) );
	}

	// ---- add-on --------------------------------------------------------

	public function test_addon_allowed_at_break_first_time() {
		// available from level 6, until 8, max 1, at level 6, 0 taken.
		$this->assertTrue( TDWP_Player_Op_Rules::can_add_on( 0, 6, 8, 1, 6 ) );
	}

	public function test_addon_blocked_when_not_allowed() {
		$r = TDWP_Player_Op_Rules::can_add_on( 0, 0, 0, 1, 6 );
		$this->assertSame( 'addon_not_allowed', $r->get_error_code() );
	}

	public function test_addon_blocked_before_available() {
		$r = TDWP_Player_Op_Rules::can_add_on( 0, 6, 8, 1, 4 );
		$this->assertSame( 'addon_not_yet', $r->get_error_code() );
	}

	public function test_addon_blocked_after_period() {
		$r = TDWP_Player_Op_Rules::can_add_on( 0, 6, 8, 1, 9 );
		$this->assertSame( 'addon_period_ended', $r->get_error_code() );
	}

	public function test_addon_blocked_when_already_taken_one_per_player() {
		$r = TDWP_Player_Op_Rules::can_add_on( 1, 6, 8, 1, 6 );
		$this->assertSame( 'addon_limit_reached', $r->get_error_code() );
	}

	public function test_addon_default_max_is_one_when_unset() {
		// max_per_player 0 -> defaults to 1; second add-on blocked.
		$this->assertTrue( TDWP_Player_Op_Rules::can_add_on( 0, 6, 8, 0, 6 ) );
		$this->assertInstanceOf( WP_Error::class, TDWP_Player_Op_Rules::can_add_on( 1, 6, 8, 0, 6 ) );
	}

	// ---- late registration --------------------------------------------

	public function test_registration_open_before_start() {
		$this->assertTrue( TDWP_Player_Op_Rules::can_register_late( 4, 0, null ) );
	}

	public function test_registration_open_within_deadline() {
		$this->assertTrue( TDWP_Player_Op_Rules::can_register_late( 4, 4, 'running' ) );
	}

	public function test_registration_blocked_after_deadline() {
		$r = TDWP_Player_Op_Rules::can_register_late( 4, 5, 'running' );
		$this->assertSame( 'late_reg_ended', $r->get_error_code() );
	}

	public function test_registration_blocked_when_tournament_completed() {
		$r = TDWP_Player_Op_Rules::can_register_late( 0, 12, 'completed' );
		$this->assertSame( 'registration_closed', $r->get_error_code() );
	}

	public function test_registration_open_when_no_deadline_and_running() {
		$this->assertTrue( TDWP_Player_Op_Rules::can_register_late( 0, 3, 'running' ) );
	}
}
