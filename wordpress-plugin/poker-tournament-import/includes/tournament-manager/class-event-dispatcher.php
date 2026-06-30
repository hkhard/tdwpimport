<?php
/**
 * Event Dispatcher
 *
 * Bridges the tournament event audit log to the asynchronous event queue and
 * drains that queue on the scheduled cron tick. Closes two gaps:
 *
 *  - tdwp-ee1.4: nothing hooked `tdwp_tournament_event_logged`, so logged
 *    events never reached the priority queue.
 *  - tdwp-3lg.13: the `tdwp_td3_process_event_queue` cron event was scheduled
 *    but had no handler, so queued rows accumulated and were never processed.
 *
 * Downstream consumers (sound playback, notifications, etc.) hook the
 * `tdwp_td3_dispatch_event` action fired for each processed row.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the event audit log to the event queue and processes the queue.
 */
class TDWP_Event_Dispatcher {

	/**
	 * Default queue priority when an event type is not mapped.
	 *
	 * Lower number = higher priority (processed first).
	 */
	const DEFAULT_PRIORITY = 5;

	/**
	 * Per-event-type queue priorities (lower = sooner).
	 *
	 * @var array<string,int>
	 */
	private static $priority_map = array(
		'tournament_ended'  => 1,
		'tournament_paused' => 1,
		'player_busted'     => 2,
		'level_changed'     => 3,
		'break_started'     => 3,
		'break_ended'       => 3,
		'table_broken'      => 4,
		'table_balanced'    => 4,
		'table_assigned'    => 5,
	);

	/**
	 * Register the dispatcher's hooks. Safe to call once on plugin load.
	 */
	public static function register() {
		add_action( 'tdwp_tournament_event_logged', array( __CLASS__, 'enqueue' ), 10, 4 );
		add_action( 'tdwp_td3_process_event_queue', array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * Resolve the queue priority for an event type.
	 *
	 * @param string $event_type Event type slug.
	 * @return int Priority (lower = sooner).
	 */
	public static function get_priority_for_event( $event_type ) {
		return isset( self::$priority_map[ $event_type ] )
			? (int) self::$priority_map[ $event_type ]
			: self::DEFAULT_PRIORITY;
	}

	/**
	 * Build the queue row for a logged event (pure; no DB access).
	 *
	 * @param int    $event_id      Audit-log row ID.
	 * @param int    $tournament_id Tournament ID (0/empty for none).
	 * @param string $event_type    Event type slug.
	 * @param mixed  $event_data    Arbitrary event payload.
	 * @return array Column => value map for tdwp_event_queue.
	 */
	public static function build_queue_row( $event_id, $tournament_id, $event_type, $event_data = array() ) {
		return array(
			'tournament_id' => $tournament_id ? absint( $tournament_id ) : null,
			'event_type'    => sanitize_text_field( $event_type ),
			'priority'      => self::get_priority_for_event( $event_type ),
			'event_data'    => wp_json_encode(
				array(
					'event_id' => absint( $event_id ),
					'data'     => $event_data,
				)
			),
			'status'        => 'pending',
		);
	}

	/**
	 * Enqueue a logged event onto the priority queue.
	 *
	 * Hooked to `tdwp_tournament_event_logged`.
	 *
	 * @param int    $event_id      Audit-log row ID.
	 * @param int    $tournament_id Tournament ID.
	 * @param string $event_type    Event type slug.
	 * @param mixed  $event_data    Event payload.
	 * @return int|false Inserted queue row ID, or false on failure.
	 */
	public static function enqueue( $event_id, $tournament_id, $event_type, $event_data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_event_queue';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		$row               = self::build_queue_row( $event_id, $tournament_id, $event_type, $event_data );
		$row['created_at'] = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			$row,
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Process pending queue rows, firing `tdwp_td3_dispatch_event` for each.
	 *
	 * Hooked to the `tdwp_td3_process_event_queue` cron event. Retries failures
	 * up to each row's `max_attempts`, then marks them `failed`.
	 *
	 * @param int $batch_size Maximum rows to process per run.
	 * @return int Number of rows successfully dispatched.
	 */
	public static function process_queue( $batch_size = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'tdwp_event_queue';
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		$now  = current_time( 'mysql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status = 'pending' AND attempts < max_attempts
				   AND ( scheduled_at IS NULL OR scheduled_at <= %s )
				 ORDER BY priority ASC, id ASC
				 LIMIT %d",
				$now,
				absint( $batch_size )
			)
		);

		$processed = 0;
		foreach ( (array) $rows as $row ) {
			$attempts = (int) $row->attempts + 1;
			$error    = '';

			try {
				/**
				 * Dispatch a queued tournament event to downstream consumers
				 * (sound playback, notifications, webhooks, ...).
				 *
				 * @param object $row The tdwp_event_queue row being processed.
				 */
				do_action( 'tdwp_td3_dispatch_event', $row );
				$dispatched = true;
			} catch ( \Throwable $e ) {
				$dispatched = false;
				$error      = $e->getMessage();
			}

			if ( $dispatched ) {
				$wpdb->update(
					$table,
					array(
						'status'       => 'completed',
						'attempts'     => $attempts,
						'last_attempt' => $now,
						'completed_at' => $now,
					),
					array( 'id' => $row->id ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
				++$processed;
			} else {
				$wpdb->update(
					$table,
					array(
						'status'        => $attempts >= (int) $row->max_attempts ? 'failed' : 'pending',
						'attempts'      => $attempts,
						'last_attempt'  => $now,
						'error_message' => $error,
					),
					array( 'id' => $row->id ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		return $processed;
	}

	/**
	 * Whether a table exists (queue is only present after TD3 migration).
	 *
	 * @param string $table Fully-qualified table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		// enqueue() runs on every logged event, so cache the lookup per request.
		static $cache = array();
		if ( ! isset( $cache[ $table ] ) ) {
			$cache[ $table ] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
		}
		return $cache[ $table ];
	}
}
