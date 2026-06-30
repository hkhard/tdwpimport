<?php
/**
 * PHPUnit bootstrap for the no-database unit harness.
 *
 * Loads the WordPress runtime stubs, installs the fake $wpdb global, then pulls
 * in the plugin classes under test. No MySQL, svn, or WP install required, so it
 * runs offline on a developer machine and on a self-hosted runner alike.
 *
 * @package Poker_Tournament_Import\Tests
 */

define( 'POKER_TOURNAMENT_IMPORT_TESTING', true );

$plugin_dir = dirname( __DIR__ ) . '/';
define( 'POKER_TOURNAMENT_IMPORT_PLUGIN_DIR', $plugin_dir );
define( 'POKER_TOURNAMENT_IMPORT_PLUGIN_URL', 'https://example.com/wp-content/plugins/poker-tournament-import/' );
define( 'POKER_TOURNAMENT_IMPORT_VERSION', '3.5.0-test' );

// Route explicit error_log() calls to a file instead of the SAPI logger so the
// (pre-existing) diagnostic error_log() spam in the parser/formula code is not
// captured as "unexpected output" by PHPUnit's strict-output checks. Cleaning up
// that spam in production code is tracked separately. Genuine errors remain
// inspectable in the temp log.
ini_set( 'error_log', sys_get_temp_dir() . '/tdwp-phpunit-error.log' );

require __DIR__ . '/stubs/wp-stubs.php';

// Install the fake database.
$GLOBALS['wpdb'] = new TDWP_Fake_WPDB();

/*
 * Classes under test. These are plain class files guarded by `if ( ! defined(
 * 'ABSPATH' ) ) exit;` — safe to require directly once ABSPATH is defined.
 */
require $plugin_dir . 'includes/tournament-manager/class-stats-bridge.php';
require $plugin_dir . 'includes/security/class-ajax-guards.php';

// Formula validator (standalone), and the .tdt parser chain (debug -> formula ->
// lexer -> ast -> domain-mapper -> parser), and the statistics engine. Load order
// matters: the parser depends on the debug class and the formula validator.
require $plugin_dir . 'includes/class-debug.php';
require $plugin_dir . 'includes/class-formula-validator.php';
require $plugin_dir . 'includes/class-tdt-lexer.php';
require $plugin_dir . 'includes/class-tdt-ast-parser.php';
require $plugin_dir . 'includes/class-tdt-domain-mapper.php';
require $plugin_dir . 'includes/class-parser.php';
require $plugin_dir . 'includes/class-statistics-engine.php';
require $plugin_dir . 'includes/tournament-manager/class-blind-schedule.php';
require $plugin_dir . 'includes/tournament-manager/class-tournament-player-manager.php';
require $plugin_dir . 'includes/class-player-registration.php';

// Player CRUD, CSV importer, and player-database exporter (cma.1 / .6 / .7).
require $plugin_dir . 'includes/tournament-manager/class-player-manager.php';
require $plugin_dir . 'admin/tournament-manager/class-player-importer.php';
require $plugin_dir . 'includes/tournament-manager/class-player-db-exporter.php';
