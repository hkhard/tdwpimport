<?php
/**
 * Unit tests for tdwp-cma.5: late-registration window configuration.
 *
 * The `late_reg_until_level` column already exists in the templates schema and is
 * persisted per-tournament by the wizard. These tests cover surfacing it in the
 * template CRUD layer (TDWP_Tournament_Template): the value is sanitized and
 * persisted on create.
 *
 * Offline — uses the fake $wpdb (generic insert records the row payload).
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'TDWP_Tournament_Template' ) ) {
	require POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-tournament-template.php';
}

final class PlayerLateRegConfigTest extends TestCase {

	protected function setUp(): void {
		tdwp_test_reset();
	}

	/** Build a minimally-valid template payload. */
	private function baseData( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'           => 'Friday Deepstack',
				'buy_in'         => 100,
				'starting_chips' => 20000,
				'rake_percentage' => 0,
			),
			$overrides
		);
	}

	public function test_late_reg_until_level_is_persisted_on_create(): void {
		$template = new TDWP_Tournament_Template();

		$result = $template->create( $this->baseData( array( 'late_reg_until_level' => 6 ) ) );

		$this->assertIsInt( $result );
		$insert = $GLOBALS['wpdb']->get_last_insert();
		$this->assertArrayHasKey( 'late_reg_until_level', $insert );
		$this->assertSame( 6, $insert['late_reg_until_level'] );
	}

	public function test_late_reg_until_level_is_sanitized_to_int(): void {
		$template = new TDWP_Tournament_Template();

		$template->create( $this->baseData( array( 'late_reg_until_level' => '8abc' ) ) );

		$insert = $GLOBALS['wpdb']->get_last_insert();
		$this->assertSame( 8, $insert['late_reg_until_level'] );
	}

	public function test_late_reg_until_level_defaults_to_zero_when_absent(): void {
		$template = new TDWP_Tournament_Template();

		$template->create( $this->baseData() );

		$insert = $GLOBALS['wpdb']->get_last_insert();
		$this->assertSame( 0, $insert['late_reg_until_level'] );
	}

	public function test_negative_late_reg_until_level_is_clamped_to_absolute(): void {
		$template = new TDWP_Tournament_Template();

		$template->create( $this->baseData( array( 'late_reg_until_level' => -4 ) ) );

		$insert = $GLOBALS['wpdb']->get_last_insert();
		$this->assertSame( 4, $insert['late_reg_until_level'] );
	}
}
