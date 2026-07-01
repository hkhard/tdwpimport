<?php
/**
 * Unit tests for TDWP_Statistics_Engine::bb_equivalent().
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-statistics-engine.php';

/**
 * @covers TDWP_Statistics_Engine::bb_equivalent
 */
class BbEquivalentTest extends TestCase {

	public function test_basic_ratio() {
		$this->assertSame( 100.0, TDWP_Statistics_Engine::bb_equivalent( 40000, 400 ) );
	}

	public function test_rounds_to_one_decimal() {
		// 25500 / 800 = 31.875 -> 31.9
		$this->assertSame( 31.9, TDWP_Statistics_Engine::bb_equivalent( 25500, 800 ) );
	}

	public function test_zero_big_blind_returns_zero_not_division_error() {
		$this->assertSame( 0.0, TDWP_Statistics_Engine::bb_equivalent( 40000, 0 ) );
	}

	public function test_negative_big_blind_is_treated_as_unknown() {
		$this->assertSame( 0.0, TDWP_Statistics_Engine::bb_equivalent( 40000, -5 ) );
	}

	public function test_not_hardcoded_to_bb_100() {
		// The bug: BB was hardcoded to 100. With a real BB=200, the same stack
		// must yield a different (halved) result.
		$this->assertSame( 400.0, TDWP_Statistics_Engine::bb_equivalent( 40000, 100 ) );
		$this->assertSame( 200.0, TDWP_Statistics_Engine::bb_equivalent( 40000, 200 ) );
	}
}
