<?php
/**
 * Tournament Events Manager
 *
 * Handles event logging for tournament actions (audit trail)
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TDWP Tournament Events Class
 *
 * Manages tournament event logging and audit trail
 *
 * @since 3.1.0
 */
class TDWP_Tournament_Events {

	/**
	 * Database instance
	 *
	 * @since 3.1.0
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table name for events
	 *
	 * @since 3.1.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Event types
	 *
	 * @since 3.1.0
	 * @var array
	 */
	const EVENT_TYPES = array(
		'tournament_started'   => 'Tournament Started',
		'tournament_paused'    => 'Tournament Paused',
		'tournament_resumed'   => 'Tournament Resumed',
		'tournament_completed' => 'Tournament Completed',
		'level_advanced'       => 'Level Advanced',
		'player_bust'          => 'Player Bust Out',
		'player_rebuy'         => 'Player Rebuy',
		'player_addon'         => 'Player Add-on',
		'player_late_reg'      => 'Player Late Registration',
		'table_assigned'       => 'Table Assignment',
		'table_balanced'       => 'Table Balanced',
		'table_broken'         => 'Table Broken',
		'chip_adjustment'      => 'Chip Adjustment',
		'prize_awarded'        => 'Prize Awarded',
	);

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'tdwp_tournament_events';
	}

	/**
	 * Log tournament event
	 *
	 * @since 3.1.0
	 *
	 * @param int    $tournament_id Tournament ID.
	 * @param string $event_type    Event type.
	 * @param array  $event_data    Event data (will be JSON encoded).
	 * @param bool   $is_automated  Whether event was automated.
	 * @return int|WP_Error Event ID on success, WP_Error on failure.
	 */
	public function log( $tournament_id, $event_type, $event_data = array(), $is_automated = false ) {
		$tournament_id = absint( $tournament_id );
		$event_type    = sanitize_text_field( $event_type );
		$is_automated  = (bool) $is_automated;

		// Validate event type.
		if ( ! array_key_exists( $event_type, self::EVENT_TYPES ) ) {
			return new WP_Error(
				'invalid_event_type',
				__( 'Invalid event type.', 'poker-tournament-import' )
			);
		}

		// Get current user ID.
		$user_id = get_current_user_id();

		// JSON encode event data.
		$event_data_json = wp_json_encode( $event_data );

		// Insert event.
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'tournament_id' => $tournament_id,
				'event_type'    => $event_type,
				'user_id'       => $user_id,
				'event_data'    => $event_data_json,
				'is_automated'  => $is_automated ? 1 : 0,
			),
			array( '%d', '%s', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to log tournament event.', 'poker-tournament-import' )
			);
		}

		$event_id = $this->wpdb->insert_id;

		/**
		 * Fires after tournament event is logged
		 *
		 * @since 3.1.0
		 *
		 * @param int    $event_id      Event ID.
		 * @param int    $tournament_id Tournament ID.
		 * @param string $event_type    Event type.
		 * @param array  $event_data    Event data.
		 */
		do_action( 'tdwp_tournament_event_logged', $event_id, $tournament_id, $event_type, $event_data );

		return $event_id;
	}

	/**
	 * Get events for tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int   $tournament_id Tournament ID.
	 * @param array $args          Query arguments.
	 * @return array Array of event objects.
	 */
	public function get_events( $tournament_id, $args = array() ) {
		$tournament_id = absint( $tournament_id );

		$defaults = array(
			'event_type' => '',
			'limit'      => 50,
			'offset'     => 0,
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query.
		$query = "SELECT * FROM {$this->table_name} WHERE tournament_id = %d";
		$params = array( $tournament_id );

		// Filter by event type.
		if ( ! empty( $args['event_type'] ) ) {
			$query   .= ' AND event_type = %s';
			$params[] = sanitize_text_field( $args['event_type'] );
		}

		// Order.
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$query .= " ORDER BY created_at $order, id $order";

		// Limit and offset.
		$limit  = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		if ( $limit > 0 ) {
			$query   .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = $offset;
		}

		// Execute query.
		$events = $this->wpdb->get_results(
			$this->wpdb->prepare( $query, ...$params )
		);

		// Decode event_data JSON.
		foreach ( $events as &$event ) {
			$event->event_data = json_decode( $event->event_data, true );
		}

		return $events;
	}

	/**
	 * Get recent events across all tournaments
	 *
	 * @since 3.1.0
	 *
	 * @param int $limit Limit.
	 * @return array Array of event objects.
	 */
	public function get_recent( $limit = 20 ) {
		$limit = absint( $limit );

		$events = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY created_at DESC, id DESC LIMIT %d",
				$limit
			)
		);

		// Decode event_data JSON.
		foreach ( $events as &$event ) {
			$event->event_data = json_decode( $event->event_data, true );
		}

		return $events;
	}

	/**
	 * Get event count for tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int    $tournament_id Tournament ID.
	 * @param string $event_type    Optional event type filter.
	 * @return int Event count.
	 */
	public function get_count( $tournament_id, $event_type = '' ) {
		$tournament_id = absint( $tournament_id );

		$query  = "SELECT COUNT(*) FROM {$this->table_name} WHERE tournament_id = %d";
		$params = array( $tournament_id );

		if ( ! empty( $event_type ) ) {
			$query   .= ' AND event_type = %s';
			$params[] = sanitize_text_field( $event_type );
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare( $query, ...$params )
		);
	}

	/**
	 * Delete events for tournament
	 *
	 * @since 3.1.0
	 *
	 * @param int $tournament_id Tournament ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_tournament_events( $tournament_id ) {
		$tournament_id = absint( $tournament_id );

		if ( 0 === $tournament_id ) {
			return new WP_Error(
				'invalid_tournament_id',
				__( 'Invalid tournament ID.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires before tournament events are deleted
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 */
		do_action( 'tdwp_before_tournament_events_deleted', $tournament_id );

		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'tournament_id' => $tournament_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete tournament events.', 'poker-tournament-import' )
			);
		}

		/**
		 * Fires after tournament events are deleted
		 *
		 * @since 3.1.0
		 *
		 * @param int $tournament_id Tournament ID.
		 */
		do_action( 'tdwp_tournament_events_deleted', $tournament_id );

		return true;
	}

	/**
	 * Get event label
	 *
	 * @since 3.1.0
	 *
	 * @param string $event_type Event type.
	 * @return string Event label.
	 */
	public static function get_event_label( $event_type ) {
		if ( array_key_exists( $event_type, self::EVENT_TYPES ) ) {
			return self::EVENT_TYPES[ $event_type ];
		}

		return ucwords( str_replace( '_', ' ', $event_type ) );
	}

	/**
	 * Format event for display
	 *
	 * @since 3.1.0
	 *
	 * @param object $event Event object.
	 * @return string Formatted event string.
	 */
	public static function format_event( $event ) {
		$label = self::get_event_label( $event->event_type );
		$time  = mysql2date( get_option( 'time_format' ), $event->created_at );

		$message = sprintf(
			'[%s] %s',
			$time,
			$label
		);

		// Add event-specific details.
		if ( ! empty( $event->event_data ) ) {
			$data_parts = array();

			foreach ( $event->event_data as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$data_parts[] = ucwords( str_replace( '_', ' ', $key ) ) . ': ' . $value;
				}
			}

			if ( ! empty( $data_parts ) ) {
				$message .= ' (' . implode( ', ', $data_parts ) . ')';
			}
		}

		return $message;
	}
}
