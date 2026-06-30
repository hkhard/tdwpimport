<?php
/**
 * Unit tests for TDWP_League_Manager pure validators.
 *
 * Covers league validation, league-type normalisation, and membership-status
 * validation (no DB).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-league-manager.php';

/**
 * @covers TDWP_League_Manager
 */
class LeagueManagerTest extends TestCase {

	public function test_valid_league_passes() {
		$this->assertTrue( TDWP_League_Manager::validate_league( array( 'name' => 'ORF League' ) ) );
	}

	public function test_missing_name_fails() {
		$result = TDWP_League_Manager::validate_league( array( 'name' => '   ' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_name', $result->get_error_code() );
	}

	public function test_negative_max_players_fails() {
		$result = TDWP_League_Manager::validate_league(
			array(
				'name'        => 'L',
				'max_players' => -5,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_max_players', $result->get_error_code() );
	}

	public function test_zero_max_players_is_allowed() {
		$this->assertTrue(
			TDWP_League_Manager::validate_league(
				array(
					'name'        => 'L',
					'max_players' => 0,
				)
			)
		);
	}

	public function test_sanitize_league_type_keeps_known_types() {
		$this->assertSame( 'knockout', TDWP_League_Manager::sanitize_league_type( 'knockout' ) );
		$this->assertSame( 'hybrid', TDWP_League_Manager::sanitize_league_type( 'hybrid' ) );
	}

	public function test_sanitize_league_type_falls_back_to_points() {
		$this->assertSame( 'points', TDWP_League_Manager::sanitize_league_type( 'nonsense' ) );
		$this->assertSame( 'points', TDWP_League_Manager::sanitize_league_type( '' ) );
	}

	public function test_membership_status_validation() {
		$this->assertTrue( TDWP_League_Manager::is_valid_membership_status( 'active' ) );
		$this->assertTrue( TDWP_League_Manager::is_valid_membership_status( 'suspended' ) );
		$this->assertFalse( TDWP_League_Manager::is_valid_membership_status( 'banned' ) );
		$this->assertFalse( TDWP_League_Manager::is_valid_membership_status( '' ) );
	}
}
