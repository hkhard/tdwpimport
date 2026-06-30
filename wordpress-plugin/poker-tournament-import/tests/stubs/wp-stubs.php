<?php
/**
 * Minimal WordPress runtime stubs for no-database unit testing.
 *
 * This lets us exercise plugin classes (e.g. TDWP_Stats_Bridge) without a live
 * WordPress install, MySQL, or the WP test suite. Only the surface the classes
 * under test actually touch is implemented. Keep it small and honest — add a
 * stub only when a test needs it.
 *
 * @package Poker_Tournament_Import\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	// The classes under test bail unless ABSPATH is defined.
	define( 'ABSPATH', __DIR__ . '/' );
}

/* ---------------------------------------------------------------------------
 * In-memory post-meta store (resettable per test).
 * ------------------------------------------------------------------------- */

$GLOBALS['tdwp_test_meta']     = array();
$GLOBALS['tdwp_test_options']  = array();
$GLOBALS['tdwp_test_actions']  = array();
$GLOBALS['tdwp_test_cron']     = array();
$GLOBALS['tdwp_test_mail']     = array();
$GLOBALS['tdwp_test_posts']    = array();

/**
 * Reset all in-memory test state. Call from setUp().
 */
function tdwp_test_reset() {
	$GLOBALS['tdwp_test_meta']       = array();
	$GLOBALS['tdwp_test_options']    = array();
	$GLOBALS['tdwp_test_actions']    = array();
	$GLOBALS['tdwp_test_cron']       = array();
	$GLOBALS['tdwp_test_transients'] = array();
	$GLOBALS['tdwp_test_cache']      = array();
	$GLOBALS['tdwp_test_mail']       = array();
	$GLOBALS['tdwp_test_posts']      = array();
	if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof TDWP_Fake_WPDB ) {
		$GLOBALS['wpdb']->reset();
	}
}

/* ---------------------------------------------------------------------------
 * Core function stubs.
 * ------------------------------------------------------------------------- */

if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return '2026-01-01 00:00:00';
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	// Deterministic-ish unique value; good enough for tests asserting uniqueness/prefix.
	function wp_generate_uuid4() {
		static $seq = 0;
		$seq++;
		return sprintf( '00000000-0000-4000-8000-%012d', $seq );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		$val = $GLOBALS['tdwp_test_meta'][ $post_id ][ $key ] ?? '';
		return $val;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['tdwp_test_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return $GLOBALS['tdwp_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $post_id, $force_delete = false ) {
		if ( ! isset( $GLOBALS['tdwp_test_posts'][ $post_id ] ) ) {
			return false;
		}
		$post = $GLOBALS['tdwp_test_posts'][ $post_id ];
		unset( $GLOBALS['tdwp_test_posts'][ $post_id ] );
		return $post;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	// No fixtures wired for meta_query lookups in the harness; report "none found".
	function get_posts( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		return trim( strip_tags( (string) $string ) );
	}
}

/** Test helper: register a fake 'player' post the manager can resolve. */
function tdwp_test_register_player( $post_id, $uuid = '', $name = 'Player', $type = 'player' ) {
	$GLOBALS['tdwp_test_posts'][ $post_id ] = (object) array(
		'ID'        => $post_id,
		'post_type' => $type,
		'post_title' => $name,
	);
	if ( '' !== $uuid ) {
		update_post_meta( $post_id, 'player_uuid', $uuid );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['tdwp_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['tdwp_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['tdwp_test_options'][ $name ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['tdwp_test_actions'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$params ) {
		if ( empty( $GLOBALS['tdwp_test_actions'][ $hook ] ) ) {
			return;
		}
		foreach ( $GLOBALS['tdwp_test_actions'][ $hook ] as $entry ) {
			$slice = array_slice( $params, 0, (int) $entry['args'] );
			call_user_func_array( $entry['callback'], $slice );
		}
	}
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	// No posts in the harness; report zero of every status.
	function wp_count_posts( $type = 'post', $perm = '' ) {
		return (object) array(
			'publish' => 0,
			'draft'   => 0,
			'trash'   => 0,
		);
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['tdwp_test_cron'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook ) {
		$GLOBALS['tdwp_test_cron'][ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	// No registered filters in the harness; return the value unchanged.
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Test Site';
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = (string) $email;
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * Captures outbound mail for assertions.
	 * Read via $GLOBALS['tdwp_test_mail'] after each send.
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$GLOBALS['tdwp_test_mail'][] = compact( 'to', 'subject', 'message' );
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_code() {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	// Configurable via $GLOBALS['tdwp_test_current_user_can']; defaults to true.
	function current_user_can( $capability, ...$args ) {
		return $GLOBALS['tdwp_test_current_user_can'] ?? true;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return is_string( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $s ) ) ) : $s;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['tdwp_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['tdwp_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['tdwp_test_transients'][ $key ] );
		return true;
	}
}

/* ---------------------------------------------------------------------------
 * Object cache (wp_cache_*) — backed by an in-memory, group-keyed store.
 * The statistics engine caches per-stat reads and invalidates them in
 * clear_all_statistics(); these stubs let those paths be asserted.
 * ------------------------------------------------------------------------- */

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		if ( isset( $GLOBALS['tdwp_test_cache'][ $group ][ $key ] ) ) {
			$found = true;
			return $GLOBALS['tdwp_test_cache'][ $group ][ $key ];
		}
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
		$GLOBALS['tdwp_test_cache'][ $group ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		unset( $GLOBALS['tdwp_test_cache'][ $group ][ $key ] );
		return true;
	}
}

/* ---------------------------------------------------------------------------
 * In-memory $wpdb fake.
 *
 * Models exactly the tables the stats bridge touches:
 *   - {prefix}tdwp_tournament_players  (read source; preset via set_live_rows)
 *   - {prefix}poker_tournament_players (write target; insert/delete tracked)
 *   - {prefix}poker_player_roi         (ROI write target; insert/delete tracked)
 *   - the live buy-in lookup (live_state INNER JOIN templates) via set_buyin()
 * ------------------------------------------------------------------------- */

class TDWP_Fake_WPDB {

	/** @var string */
	public $prefix = 'wp_';

	/** @var string Core table names (real $wpdb exposes these as properties). */
	public $postmeta = 'wp_postmeta';

	/** @var string */
	public $posts = 'wp_posts';

	/** @var string Core taxonomy table names (real $wpdb exposes these as properties). */
	public $terms = 'wp_terms';

	/** @var string */
	public $term_taxonomy = 'wp_term_taxonomy';

	/** @var string */
	public $term_relationships = 'wp_term_relationships';

	/** @var string */
	public $last_error = '';

	/** @var int ID of the most recently inserted row (mirrors real $wpdb->insert_id). */
	public $insert_id = 0;

	/** @var bool Whether SHOW TABLES LIKE should report tables present. */
	public $tables_exist = true;

	/** @var array Fully-qualified table names to report as ABSENT (overrides $tables_exist). */
	public $missing_tables = array();

	/** @var array Rows returned for SELECT against the live players table. */
	private $live_rows = array();

	/** @var array Rows currently stored in the legacy players table. */
	private $legacy_rows = array();

	/** @var array Rows currently stored in the ROI table. */
	private $roi_rows = array();

	/** @var int Auto-increment id for legacy inserts. */
	private $auto_id = 0;

	/** @var int Auto-increment id for ROI inserts. */
	private $auto_id_roi = 0;

	/** @var mixed Buy-in returned by the live-state/template lookup; null => unknown. */
	private $buyin = null;

	/** @var array Stored statistics data mart: stat_name => stat_value. */
	private $stats = array();

	/** @var array Participation rows backing poker_tournament_players aggregates. */
	private $player_rows = array();

	/** @var array Rows inserted into tdwp_tournament_events. */
	private $event_rows = array();

	/** @var int Auto-increment id for event inserts. */
	private $auto_id_events = 0;

	/** @var array Rows inserted into tdwp_tournament_players (live table). */
	private $tdwp_player_inserts = array();

	/** @var int Auto-increment id for tdwp_tournament_players inserts. */
	private $auto_id_tdwp_players = 0;

	/**
	 * Test helper: set confirmed player count for a tournament.
	 * Used to make get_confirmed_count() return a predictable value.
	 *
	 * @var array tournament_id => count
	 */
	private $confirmed_counts = array();

	/**
	 * Test helper: set max waitlist position for a tournament.
	 *
	 * @var array tournament_id => max_position
	 */
	private $max_waitlist_positions = array();

	public function reset() {
		$this->live_rows              = array();
		$this->legacy_rows            = array();
		$this->roi_rows               = array();
		$this->auto_id                = 0;
		$this->auto_id_roi            = 0;
		$this->buyin                  = null;
		$this->last_error             = '';
		$this->tables_exist           = true;
		$this->missing_tables         = array();
		$this->stats                  = array();
		$this->player_rows            = array();
		$this->event_rows             = array();
		$this->auto_id_events         = 0;
		$this->insert_id              = 0;
		$this->tdwp_player_inserts    = array();
		$this->auto_id_tdwp_players   = 0;
		$this->confirmed_counts       = array();
		$this->max_waitlist_positions = array();
		$GLOBALS['tdwp_test_mail']    = array();
	}

	/** Test helper: preset the confirmed player count for a tournament. */
	public function set_confirmed_count( $tournament_id, $count ) {
		$this->confirmed_counts[ (int) $tournament_id ] = (int) $count;
	}

	/** Test helper: preset the max waitlist position for a tournament. */
	public function set_max_waitlist_position( $tournament_id, $position ) {
		$this->max_waitlist_positions[ (int) $tournament_id ] = (int) $position;
	}

	/** Test helper: return rows inserted into tdwp_tournament_players. */
	public function get_tdwp_player_inserts() {
		return array_values( $this->tdwp_player_inserts );
	}

	/** Test helper: define the poker_tournament_players rows the stats engine aggregates over. */
	public function set_player_rows( array $rows ) {
		$this->player_rows = array_map(
			static function ( $r ) {
				return (array) $r;
			},
			$rows
		);
	}

	/** Test helper: read the current statistics data-mart contents (stat_name => value). */
	public function get_stats() {
		return $this->stats;
	}

	/** Test helper: define the live buy-in returned by the template lookup. */
	public function set_buyin( $amount ) {
		$this->buyin = $amount;
	}

	/** Test helper: read what is currently in the ROI table. */
	public function get_roi_rows() {
		return array_values( $this->roi_rows );
	}

	/** Test helper: define the live participation rows for the next projection. */
	public function set_live_rows( array $rows ) {
		$this->live_rows = array_map(
			static function ( $r ) {
				return (object) $r;
			},
			$rows
		);
	}

	/** Test helper: read what is currently in the legacy table. */
	public function get_legacy_rows() {
		return array_values( $this->legacy_rows );
	}

	/** Test helper: read rows inserted into the tournament events table. */
	public function get_event_rows() {
		return array_values( $this->event_rows );
	}

	/**
	 * Emulate $wpdb->prepare. We keep the placeholders and append args so the
	 * fake query handlers can branch on the SQL text and read the bound args.
	 */
	public function prepare( $query, ...$args ) {
		// Flatten a single array arg, matching WP behaviour.
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		return array(
			'sql'  => $query,
			'args' => $args,
		);
	}

	public function get_var( $query ) {
		$sql  = is_array( $query ) ? $query['sql'] : $query;
		$args = is_array( $query ) ? $query['args'] : array();

		// get_confirmed_count(): COUNT with status IN (...) on tdwp_tournament_players.
		if (
			stripos( $sql, 'tdwp_tournament_players' ) !== false &&
			stripos( $sql, "status IN ('registered','paid','active','checked_in')" ) !== false
		) {
			$tid = isset( $args[0] ) ? (int) $args[0] : 0;
			return $this->confirmed_counts[ $tid ] ?? 0;
		}

		// get_next_waitlist_position(): MAX(waitlist_position) on tdwp_tournament_players.
		if (
			stripos( $sql, 'tdwp_tournament_players' ) !== false &&
			stripos( $sql, 'MAX(waitlist_position)' ) !== false
		) {
			$tid = isset( $args[0] ) ? (int) $args[0] : 0;
			return $this->max_waitlist_positions[ $tid ] ?? 0;
		}

		if ( stripos( $sql, 'SHOW TABLES LIKE' ) !== false ) {
			$name = $args[0] ?? '';
			if ( in_array( $name, $this->missing_tables, true ) ) {
				return null;
			}
			return $this->tables_exist ? $name : null;
		}
		// Live buy-in lookup: SELECT t.buy_in FROM ...live_state... JOIN ...templates.
		if ( stripos( $sql, 'buy_in' ) !== false && stripos( $sql, 'tdwp_tournament_templates' ) !== false ) {
			return $this->buyin;
		}

		// Statistics data mart (poker_statistics) reads.
		if ( stripos( $sql, 'poker_statistics' ) !== false ) {
			if ( stripos( $sql, 'stat_value' ) !== false && stripos( $sql, 'stat_name' ) !== false ) {
				$name = $args[0] ?? '';
				return $this->stats[ $name ] ?? null;
			}
			if ( stripos( $sql, 'MAX(last_updated' ) !== false ) {
				return empty( $this->stats ) ? null : '2026-01-01 00:00:00';
			}
			if ( stripos( $sql, 'COUNT(' ) !== false ) {
				return count( $this->stats );
			}
			return null;
		}

		// Aggregates over the poker_tournament_players read-model.
		if ( stripos( $sql, 'poker_tournament_players' ) !== false ) {
			if ( stripos( $sql, 'COUNT(DISTINCT player_id' ) !== false ) {
				$ids = array();
				foreach ( $this->player_rows as $r ) {
					if ( isset( $r['player_id'] ) ) {
						$ids[ (string) $r['player_id'] ] = true;
					}
				}
				return count( $ids );
			}
			if ( stripos( $sql, 'SUM(buyins' ) !== false ) {
				return array_sum( array_column( $this->player_rows, 'buyins' ) );
			}
			if ( stripos( $sql, 'SUM(winnings' ) !== false ) {
				return array_sum( array_column( $this->player_rows, 'winnings' ) );
			}
			if ( stripos( $sql, 'MAX(winnings' ) !== false ) {
				$w = array_column( $this->player_rows, 'winnings' );
				return empty( $w ) ? null : max( $w );
			}
			if ( stripos( $sql, 'COUNT(*' ) !== false && stripos( $sql, 'winnings > 0' ) !== false ) {
				$n = 0;
				foreach ( $this->player_rows as $r ) {
					if ( isset( $r['winnings'] ) && $r['winnings'] > 0 ) {
						$n++;
					}
				}
				return $n;
			}
			if ( stripos( $sql, 'AVG(finish_position' ) !== false ) {
				$vals = array();
				foreach ( $this->player_rows as $r ) {
					if ( isset( $r['finish_position'] ) && $r['finish_position'] > 0 ) {
						$vals[] = $r['finish_position'];
					}
				}
				return empty( $vals ) ? null : array_sum( $vals ) / count( $vals );
			}
		}
		return null;
	}

	/** Emulate $wpdb->query: only TRUNCATE of the stats data mart is modelled. */
	public function query( $query ) {
		$sql = is_array( $query ) ? $query['sql'] : $query;
		if ( stripos( $sql, 'TRUNCATE' ) !== false && stripos( $sql, 'poker_statistics' ) !== false ) {
			$this->stats = array();
			return true;
		}
		return 0;
	}

	public function get_results( $query ) {
		$sql = is_array( $query ) ? $query['sql'] : $query;

		if ( stripos( $sql, 'tdwp_tournament_players' ) !== false ) {
			return $this->live_rows;
		}
		return array();
	}

	public function insert( $table, $data, $format = null ) {
		if ( stripos( $table, 'poker_player_roi' ) !== false ) {
			$this->auto_id_roi++;
			$data['id']      = $this->auto_id_roi;
			$this->insert_id = $this->auto_id_roi;
			$this->roi_rows[ $this->auto_id_roi ] = $data;
			return 1;
		}
		if ( stripos( $table, 'poker_tournament_players' ) !== false ) {
			$this->auto_id++;
			$data['id']      = $this->auto_id;
			$this->insert_id = $this->auto_id;
			$this->legacy_rows[ $this->auto_id ] = $data;
			return 1;
		}
		if ( stripos( $table, 'tdwp_tournament_players' ) !== false ) {
			$this->auto_id_tdwp_players++;
			$data['id']      = $this->auto_id_tdwp_players;
			$this->insert_id = $this->auto_id_tdwp_players;
			$this->tdwp_player_inserts[ $this->auto_id_tdwp_players ] = $data;
			return 1;
		}
		if ( stripos( $table, 'tdwp_tournament_events' ) !== false ) {
			$this->auto_id_events++;
			$data['id']      = $this->auto_id_events;
			$this->insert_id = $this->auto_id_events;
			$this->event_rows[ $this->auto_id_events ] = $data;
			return 1;
		}
		return false;
	}

	public function delete( $table, $where, $where_format = null ) {
		if ( stripos( $table, 'poker_player_roi' ) !== false ) {
			return $this->delete_from( $this->roi_rows, $where );
		}
		if ( stripos( $table, 'poker_tournament_players' ) !== false ) {
			return $this->delete_from( $this->legacy_rows, $where );
		}
		return false;
	}

	/**
	 * Emulate $wpdb->update for the data-mart tables the merge re-points.
	 * Returns the number of rows whose columns were changed.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( stripos( $table, 'poker_player_roi' ) !== false ) {
			return $this->update_in( $this->roi_rows, $data, $where );
		}
		// poker_tournament_players (must be checked after the more specific ROI table).
		if ( stripos( $table, 'poker_tournament_players' ) !== false ) {
			return $this->update_in( $this->legacy_rows, $data, $where );
		}
		return false;
	}

	/** Shared update-by-where for the in-memory row stores (mutates by reference). */
	private function update_in( array &$store, array $data, array $where ) {
		$changed = 0;
		foreach ( $store as $id => $row ) {
			$match = true;
			foreach ( $where as $col => $val ) {
				if ( ! isset( $row[ $col ] ) || (string) $row[ $col ] !== (string) $val ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				$store[ $id ] = array_merge( $row, $data );
				$changed++;
			}
		}
		return $changed;
	}

	/** Test helper: seed a row in the legacy participation table. */
	public function seed_legacy_row( array $row ) {
		$this->auto_id++;
		$row['id']                           = $this->auto_id;
		$this->legacy_rows[ $this->auto_id ] = $row;
	}

	/** Test helper: seed a row in the ROI table. */
	public function seed_roi_row( array $row ) {
		$this->auto_id_roi++;
		$row['id']                            = $this->auto_id_roi;
		$this->roi_rows[ $this->auto_id_roi ] = $row;
	}

	/**
	 * MySQL REPLACE: delete-by-unique-key then insert. The real poker_player_roi
	 * has no unique key, so the production code does its own delete-then-insert;
	 * here we mirror insert() (callers clear first) so row counts reflect reality.
	 */
	public function replace( $table, $data, $format = null ) {
		// Statistics data mart: REPLACE upserts by stat_name (its unique key).
		if ( stripos( $table, 'poker_statistics' ) !== false ) {
			if ( isset( $data['stat_name'] ) ) {
				$this->stats[ $data['stat_name'] ] = $data['stat_value'] ?? null;
				return 1;
			}
			return false;
		}
		return $this->insert( $table, $data, $format );
	}

	/** Minimal get_row: the lookups under test (e.g. postmeta -> post_id) have no
	 *  fixture here, so return null (callers treat that as "not found"). */
	public function get_row( $query ) {
		return null;
	}

	/** Shared delete-by-where for the in-memory row stores (mutates by reference). */
	private function delete_from( array &$store, array $where ) {
		$removed = 0;
		foreach ( $store as $id => $row ) {
			$match = true;
			foreach ( $where as $col => $val ) {
				if ( ! isset( $row[ $col ] ) || (string) $row[ $col ] !== (string) $val ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				unset( $store[ $id ] );
				$removed++;
			}
		}
		return $removed;
	}
}
