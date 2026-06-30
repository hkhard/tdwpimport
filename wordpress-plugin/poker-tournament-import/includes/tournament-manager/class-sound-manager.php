<?php
/**
 * Sound Manager (tdwp-ee1.1)
 *
 * Maps tournament events to sounds from the tdwp_sound_library and exposes the
 * event→sound URL map the front-end player uses to play a sound when an event
 * occurs. The event→category mapping is pure so it can be unit-tested.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves sounds for tournament events.
 */
class TDWP_Sound_Manager {

	/**
	 * Event-type => sound category mapping.
	 *
	 * @var array<string,string>
	 */
	private static $event_categories = array(
		'level_changed'       => 'tournament',
		'level_advanced'      => 'tournament',
		'break_started'       => 'tournament',
		'break_ended'         => 'tournament',
		'player_busted'       => 'elimination',
		'bustout'             => 'elimination',
		'final_table'         => 'milestone',
		'final_table_reached' => 'milestone',
		'tournament_ended'    => 'milestone',
		'registration_open'   => 'registration',
		'registration'        => 'registration',
	);

	/**
	 * Resolve the sound category for an event type (pure).
	 *
	 * @param string $event_type Event type slug.
	 * @return string|null Category, or null if the event has no sound.
	 */
	public static function get_category_for_event( $event_type ) {
		$event_type = (string) $event_type;
		return isset( self::$event_categories[ $event_type ] ) ? self::$event_categories[ $event_type ] : null;
	}

	/**
	 * The event types that have a sound mapping (pure).
	 *
	 * @return string[]
	 */
	public static function get_mapped_event_types() {
		return array_keys( self::$event_categories );
	}

	/**
	 * Get the active default sound URL for a category.
	 *
	 * @param string $category Sound category.
	 * @return string Sound file URL, or '' if none.
	 */
	public static function get_default_sound_url( $category ) {
		global $wpdb;

		$url = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_url FROM {$wpdb->prefix}tdwp_sound_library
				 WHERE category = %s AND is_active = 1
				 ORDER BY is_default DESC, id ASC LIMIT 1",
				$category
			)
		);

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Build the event-type => sound-URL map for the front-end player.
	 *
	 * @return array<string,string> Only events that resolve to a real sound.
	 */
	public static function get_event_sound_map() {
		$map   = array();
		$cache = array();

		foreach ( self::$event_categories as $event_type => $category ) {
			if ( ! array_key_exists( $category, $cache ) ) {
				$cache[ $category ] = self::get_default_sound_url( $category );
			}
			if ( '' !== $cache[ $category ] ) {
				$map[ $event_type ] = $cache[ $category ];
			}
		}

		return $map;
	}

	/**
	 * Register the front-end sound player enqueue.
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_player' ) );
	}

	/**
	 * Enqueue the sound player and localise the event→URL map.
	 */
	public static function enqueue_player() {
		$map = self::get_event_sound_map();
		if ( empty( $map ) ) {
			return;
		}

		wp_enqueue_script(
			'tdwp-sound-player',
			POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tdwp-sound-player.js',
			array(),
			POKER_TOURNAMENT_IMPORT_VERSION,
			true
		);
		wp_localize_script(
			'tdwp-sound-player',
			'tdwpSounds',
			array(
				'map'   => $map,
				'muted' => false,
			)
		);
	}
}
