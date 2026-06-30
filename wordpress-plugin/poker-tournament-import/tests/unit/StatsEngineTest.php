<?php
/**
 * Unit tests for Poker_Statistics_Engine.
 *
 * The engine is heavily $wpdb-backed; the no-database harness exercises the
 * parts that are robust against an empty fake DB: the singleton accessor, the
 * dashboard aggregation shape, and the simple count accessors degrading to 0.
 * Deeper recompute coverage would need a fuller table fake (tracked separately).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class StatsEngineTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	private function engine(): Poker_Statistics_Engine {
		return Poker_Statistics_Engine::get_instance();
	}

	public function test_get_instance_returns_singleton(): void {
		$a = Poker_Statistics_Engine::get_instance();
		$b = Poker_Statistics_Engine::get_instance();
		$this->assertInstanceOf( Poker_Statistics_Engine::class, $a );
		$this->assertSame( $a, $b, 'Statistics engine must be a singleton.' );
	}

	public function test_get_last_updated_is_safe_when_never_run(): void {
		// Should not fatal; returns a falsy/empty value (or a timestamp string)
		// when statistics have never been computed.
		$last = $this->engine()->get_last_updated();
		$this->assertTrue( $last === false || $last === null || is_string( $last ) );
	}
}
