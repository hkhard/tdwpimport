<?php
/**
 * Unit tests for Poker_Tournament_Formula_Validator.
 *
 * Characterization + behavioural tests for the Tournament Director formula
 * engine: arithmetic, operator precedence, the supported functions, the
 * canonical scoring variables (n, r, hits, T33, T80), and validate_formula.
 *
 * Variable injection note (discovered from the engine's variable map): the
 * calculate_formula() $data array is keyed by SOURCE names, not the short
 * formula names — total_players -> n, finish_position -> r, hits -> hits.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

final class FormulaValidatorTest extends TestCase {

	/** @var Poker_Tournament_Formula_Validator */
	private $v;

	protected function setUp(): void {
		tdwp_test_reset();
		$this->v = new Poker_Tournament_Formula_Validator();
	}

	/** Helper: calculate a formula and assert it succeeded, returning the numeric result. */
	private function calc( string $formula, array $data = array() ) {
		$r = $this->v->calculate_formula( $formula, $data );
		$this->assertIsArray( $r );
		$this->assertTrue( $r['success'], 'Formula should evaluate: ' . $formula . ' — ' . ( $r['error'] ?? '' ) );
		return $r['result'];
	}

	/* ----------------------------- arithmetic ----------------------------- */

	public function test_basic_arithmetic(): void {
		$this->assertEquals( 5, $this->calc( '2 + 3' ) );
		$this->assertEquals( 6, $this->calc( '10 - 4' ) );
		$this->assertEquals( 12, $this->calc( '3 * 4' ) );
		$this->assertEquals( 4, $this->calc( '20 / 5' ) );
	}

	public function test_operator_precedence(): void {
		$this->assertEquals( 14, $this->calc( '2 + 3 * 4' ) );
	}

	public function test_parentheses_override_precedence(): void {
		$this->assertEquals( 20, $this->calc( '(2 + 3) * 4' ) );
	}

	/* ----------------------------- variables ------------------------------ */

	public function test_variable_n_from_total_players(): void {
		$this->assertEquals( 21, $this->calc( 'n', array( 'total_players' => 21 ) ) );
		$this->assertEquals( 42, $this->calc( 'n * 2', array( 'total_players' => 21 ) ) );
	}

	public function test_variable_r_from_finish_position(): void {
		$this->assertEquals( 5, $this->calc( 'r', array( 'finish_position' => 5 ) ) );
	}

	public function test_variable_hits(): void {
		$this->assertEquals( 3, $this->calc( 'hits', array( 'hits' => 3 ) ) );
	}

	public function test_T33_and_T80_are_assign_targets_not_runtime_globals(): void {
		// T33/T80 are documented names the formula author assigns (e.g.
		// assign("T33", round(n/3))); they are not pre-populated runtime globals,
		// so a bare reference does not evaluate. Characterize that contract.
		$bare = $this->v->calculate_formula( 'T33', array( 'total_players' => 9 ) );
		$this->assertFalse( $bare['success'], 'Bare T33 is not an engine-provided variable.' );

		// But an assign() call referencing n is itself a valid, evaluable formula.
		$assigned = $this->v->calculate_formula( 'assign("T33", round(n / 3))', array( 'total_players' => 9 ) );
		$this->assertTrue( $assigned['success'] );
	}

	/* ----------------------------- functions ------------------------------ */

	public function test_if_function_true_branch(): void {
		$this->assertEquals( 100, $this->calc( 'if(r < 3, 100, 10)', array( 'finish_position' => 1 ) ) );
	}

	public function test_if_function_false_branch(): void {
		$this->assertEquals( 10, $this->calc( 'if(r < 3, 100, 10)', array( 'finish_position' => 5 ) ) );
	}

	public function test_round_function(): void {
		$this->assertEquals( 3, $this->calc( 'round(n / 3)', array( 'total_players' => 10 ) ) );
	}

	public function test_floor_function(): void {
		$this->assertEquals( 3, $this->calc( 'floor(n / 3)', array( 'total_players' => 10 ) ) );
	}

	public function test_max_and_min_functions(): void {
		$this->assertEquals( 5, $this->calc( 'max(5, 3)' ) );
		$this->assertEquals( 3, $this->calc( 'min(5, 3)' ) );
	}

	public function test_sqrt_and_pow_functions(): void {
		$this->assertEquals( 4, $this->calc( 'sqrt(16)' ) );
		$this->assertEquals( 8, $this->calc( 'pow(2, 3)' ) );
	}

	public function test_switch_function(): void {
		// switch(value, cmp1, res1, cmp2, res2, ...) — matches 3 -> 30.
		$this->assertEquals( 30, $this->calc( 'switch(3, 1, 10, 2, 20, 3, 30)' ) );
	}

	/* --------------------------- validate_formula ------------------------- */

	public function test_validate_accepts_well_formed_formula(): void {
		$r = $this->v->validate_formula( 'n + r' );
		$this->assertTrue( $r['valid'] );
	}

	public function test_validate_rejects_unclosed_call(): void {
		$r = $this->v->validate_formula( 'round(' );
		$this->assertFalse( $r['valid'] );
	}

	public function test_validate_rejects_unknown_function(): void {
		$r = $this->v->validate_formula( 'undefined_fn(3)' );
		$this->assertFalse( $r['valid'] );
	}
}
