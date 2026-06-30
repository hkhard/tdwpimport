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

/**
 * Reset all in-memory test state. Call from setUp().
 */
function tdwp_test_reset() {
	$GLOBALS['tdwp_test_meta']       = array();
	$GLOBALS['tdwp_test_options']    = array();
	$GLOBALS['tdwp_test_actions']    = array();
	$GLOBALS['tdwp_test_cron']       = array();
	$GLOBALS['tdwp_test_transients'] = array();
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

	/** @var string */
	public $last_error = '';

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

	public function reset() {
		$this->live_rows    = array();
		$this->legacy_rows  = array();
		$this->roi_rows     = array();
		$this->auto_id      = 0;
		$this->auto_id_roi  = 0;
		$this->buyin          = null;
		$this->last_error     = '';
		$this->tables_exist   = true;
		$this->missing_tables = array();
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
		return null;
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
			$data['id'] = $this->auto_id_roi;
			$this->roi_rows[ $this->auto_id_roi ] = $data;
			return 1;
		}
		if ( stripos( $table, 'poker_tournament_players' ) !== false ) {
			$this->auto_id++;
			$data['id'] = $this->auto_id;
			$this->legacy_rows[ $this->auto_id ] = $data;
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
