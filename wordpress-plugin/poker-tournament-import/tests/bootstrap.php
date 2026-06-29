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

require __DIR__ . '/stubs/wp-stubs.php';

// Install the fake database.
$GLOBALS['wpdb'] = new TDWP_Fake_WPDB();

/*
 * Classes under test. These are plain class files guarded by `if ( ! defined(
 * 'ABSPATH' ) ) exit;` — safe to require directly once ABSPATH is defined.
 */
require $plugin_dir . 'includes/tournament-manager/class-stats-bridge.php';
