<?php
/**
 * Unit tests for TDWP_Chipset_Manager pure helpers.
 *
 * Covers denomination validation and smallest-denomination resolution
 * (no DB), the logic chip-up automation will depend on.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-chipset-manager.php';

/**
 * @covers TDWP_Chipset_Manager
 */
class ChipsetManagerTest extends TestCase {

	private function denoms( array $values ) {
		return array_map(
			static function ( $v ) {
				return array( 'value' => $v );
			},
			$values
		);
	}

	public function test_valid_denominations_pass() {
		$this->assertTrue( TDWP_Chipset_Manager::validate_denominations( $this->denoms( array( 25, 100, 500, 1000 ) ) ) );
	}

	public function test_empty_denominations_fail() {
		$result = TDWP_Chipset_Manager::validate_denominations( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_denominations', $result->get_error_code() );
	}

	public function test_non_positive_value_fails() {
		$result = TDWP_Chipset_Manager::validate_denominations( $this->denoms( array( 25, 0, 100 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_value', $result->get_error_code() );
	}

	public function test_duplicate_value_fails() {
		$result = TDWP_Chipset_Manager::validate_denominations( $this->denoms( array( 25, 100, 100 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'duplicate_value', $result->get_error_code() );
	}

	public function test_smallest_denomination_found_regardless_of_order() {
		$this->assertSame( 25, TDWP_Chipset_Manager::get_smallest_denomination( $this->denoms( array( 1000, 25, 500, 100 ) ) ) );
	}

	public function test_smallest_denomination_ignores_non_positive() {
		$this->assertSame( 50, TDWP_Chipset_Manager::get_smallest_denomination( $this->denoms( array( 0, 50, 100 ) ) ) );
	}

	public function test_smallest_denomination_empty_is_zero() {
		$this->assertSame( 0, TDWP_Chipset_Manager::get_smallest_denomination( array() ) );
	}
}
