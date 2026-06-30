<?php
/**
 * Unit tests for TDWP_Chip_Up pure logic.
 *
 * Covers denomination selection and the round-down + race-remainder
 * computation (no DB), including chip conservation.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-chip-up.php';

/**
 * @covers TDWP_Chip_Up
 */
class ChipUpTest extends TestCase {

	private function denoms( array $values ) {
		return array_map(
			static function ( $v ) {
				return array( 'value' => $v );
			},
			$values
		);
	}

	// ---- select_raceoff_denomination -----------------------------------

	public function test_selects_smallest_useful_denomination() {
		// Blinds passed 25; the 25 chip becomes the lowest useful chip.
		$this->assertSame( 25, TDWP_Chip_Up::select_raceoff_denomination( $this->denoms( array( 5, 25, 100, 500 ) ), 25 ) );
	}

	public function test_no_race_when_smallest_chip_still_useful() {
		// Smallest chip (25) is still >= the small blind (10): nothing obsolete.
		$this->assertSame( 0, TDWP_Chip_Up::select_raceoff_denomination( $this->denoms( array( 25, 100 ) ), 10 ) );
	}

	public function test_no_race_when_no_denominations() {
		$this->assertSame( 0, TDWP_Chip_Up::select_raceoff_denomination( array(), 100 ) );
	}

	public function test_no_auto_race_when_all_denoms_below_blind() {
		$this->assertSame( 0, TDWP_Chip_Up::select_raceoff_denomination( $this->denoms( array( 5, 25 ) ), 100 ) );
	}

	// ---- compute_race_off ----------------------------------------------

	public function test_round_down_and_redistribute_largest_remainder() {
		$stacks = array(
			1 => 10325,
			2 => 10100,
			3 => 9990,
		);
		$out = TDWP_Chip_Up::compute_race_off( $stacks, 100 );

		// Floored: 10300, 10100, 9900. Remainders: 25, 0, 90. Pot = floor(115/100)=1.
		$this->assertSame( 1, $out['pot_chips'] );
		$this->assertSame( 15, $out['dropped'] );
		// The single pot chip goes to the largest remainder (player 3, rem 90).
		$this->assertSame( 10300, $out['new_stacks'][1] );
		$this->assertSame( 10100, $out['new_stacks'][2] );
		$this->assertSame( 10000, $out['new_stacks'][3] );
		$this->assertSame( array( 3 => 1 ), $out['awards'] );
	}

	public function test_chips_are_conserved_except_dropped_remainder() {
		$stacks = array(
			1 => 13333,
			2 => 8888,
			3 => 4321,
			4 => 999,
		);
		$out      = TDWP_Chip_Up::compute_race_off( $stacks, 500 );
		$orig_sum = array_sum( $stacks );
		$new_sum  = array_sum( $out['new_stacks'] );
		// All chip value is conserved except the sub-chip remainder dropped.
		$this->assertSame( $orig_sum, $new_sum + $out['dropped'] );
		$this->assertLessThan( 500, $out['dropped'] );
		// Every new stack is a multiple of the denomination.
		foreach ( $out['new_stacks'] as $count ) {
			$this->assertSame( 0, $count % 500 );
		}
	}

	public function test_zero_denom_leaves_stacks_unchanged() {
		$stacks = array(
			1 => 500,
			2 => 750,
		);
		$out = TDWP_Chip_Up::compute_race_off( $stacks, 0 );
		$this->assertSame( $stacks, $out['new_stacks'] );
		$this->assertSame( 0, $out['pot_chips'] );
	}

	public function test_ties_broken_by_player_key_ascending() {
		// Equal remainders (50 each); a single pot chip goes to the lowest key.
		$stacks = array(
			7 => 150,
			3 => 250,
		);
		$out = TDWP_Chip_Up::compute_race_off( $stacks, 100 );
		// Floored 100/200, remainders 50/50, pot=1 -> key 3 wins the tie.
		$this->assertSame( 1, $out['pot_chips'] );
		$this->assertSame( array( 3 => 1 ), $out['awards'] );
	}
}
