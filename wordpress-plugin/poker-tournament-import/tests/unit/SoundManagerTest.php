<?php
/**
 * Unit tests for TDWP_Sound_Manager pure event→category mapping.
 *
 * @package Poker_Tournament_Import\Tests
 */

use PHPUnit\Framework\TestCase;

require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/tournament-manager/class-sound-manager.php';

/**
 * @covers TDWP_Sound_Manager
 */
class SoundManagerTest extends TestCase {

	public function test_known_events_map_to_categories() {
		$this->assertSame( 'tournament', TDWP_Sound_Manager::get_category_for_event( 'level_advanced' ) );
		$this->assertSame( 'elimination', TDWP_Sound_Manager::get_category_for_event( 'player_busted' ) );
		$this->assertSame( 'milestone', TDWP_Sound_Manager::get_category_for_event( 'final_table_reached' ) );
		$this->assertSame( 'registration', TDWP_Sound_Manager::get_category_for_event( 'registration_open' ) );
	}

	public function test_unknown_event_has_no_category() {
		$this->assertNull( TDWP_Sound_Manager::get_category_for_event( 'something_else' ) );
		$this->assertNull( TDWP_Sound_Manager::get_category_for_event( '' ) );
	}

	public function test_mapped_event_types_are_non_empty_and_consistent() {
		$types = TDWP_Sound_Manager::get_mapped_event_types();
		$this->assertNotEmpty( $types );
		foreach ( $types as $type ) {
			$this->assertNotNull( TDWP_Sound_Manager::get_category_for_event( $type ) );
		}
	}
}
