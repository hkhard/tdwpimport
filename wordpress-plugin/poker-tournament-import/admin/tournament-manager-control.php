<?php
/**
 * Tournament Manager Control Page
 *
 * Unified control interface with tabs for Timer, Tables, Players, Stats
 * Phase 2 Week 1-3 functionality
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get tournament ID from URL
$tournament_id = isset( $_GET['tournament_id'] ) ? intval( $_GET['tournament_id'] ) : 0;

if ( ! $tournament_id ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid tournament ID', 'poker-tournament-import' ) . '</p></div>';
	return;
}

// Initialize state if needed
TDWP_Live_State_Manager::initialize( $tournament_id );

// Get current state
$state = TDWP_Live_State_Manager::get_state( $tournament_id );

$tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );
$balance_status = TDWP_Table_Balancer::get_balance_status( $tournament_id );

// Enqueue assets
wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1' );
wp_enqueue_script( 'jquery-ui-draggable' );
wp_enqueue_script( 'jquery-ui-droppable' );

wp_enqueue_style( 'tdwp-tournament-control', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/tournament-control.css', array(), POKER_TOURNAMENT_IMPORT_VERSION );
wp_enqueue_style( 'tdwp-tournament-timer', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/tournament-timer.css', array(), POKER_TOURNAMENT_IMPORT_VERSION );
wp_enqueue_style( 'tdwp-table-manager', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/table-manager.css', array(), POKER_TOURNAMENT_IMPORT_VERSION );

wp_enqueue_script( 'tdwp-tournament-control', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-control.js', array( 'jquery', 'heartbeat' ), POKER_TOURNAMENT_IMPORT_VERSION, true );
wp_enqueue_script( 'tdwp-tournament-timer', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/tournament-timer.js', array( 'jquery', 'heartbeat' ), POKER_TOURNAMENT_IMPORT_VERSION, true );
wp_enqueue_script( 'tdwp-table-manager', POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/table-manager.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ), POKER_TOURNAMENT_IMPORT_VERSION, true );

// Localize script
wp_localize_script(
	'tdwp-tournament-control',
	'tdwpTournamentControl',
	array(
		'ajaxurl'       => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'tdwp_tournament_manager' ),
		'tournament_id' => $tournament_id,
		'i18n'          => array(
			'confirm_remove_table' => __( 'Are you sure you want to remove this table?', 'poker-tournament-import' ),
			'confirm_break_table'  => __( 'Break this table and move all players?', 'poker-tournament-import' ),
			'confirm_balance'      => __( 'Execute this balance plan?', 'poker-tournament-import' ),
		),
	)
);
?>

<div class="wrap tdwp-tournament-control">
	<h1><?php echo esc_html( get_the_title( $tournament_id ) ); ?> - <?php esc_html_e( 'Live Control', 'poker-tournament-import' ); ?></h1>

	<!-- Minimized Clock Indicator -->
	<div id="tdwp-minimized-clock" class="tdwp-minimized-clock" style="display:none;">
		<span class="clock-level"><?php esc_html_e( 'Level', 'poker-tournament-import' ); ?> <span id="mini-level">1</span></span>
		<span class="clock-divider">|</span>
		<span class="clock-time" id="mini-time">--:--</span>
		<span class="clock-divider">|</span>
		<span class="clock-blinds" id="mini-blinds">--/--</span>
	</div>

	<!-- Tab Navigation -->
	<div class="tdwp-tab-nav">
		<button class="tdwp-tab-button active" data-tab="timer"><?php esc_html_e( 'Timer', 'poker-tournament-import' ); ?></button>
		<button class="tdwp-tab-button" data-tab="tables"><?php esc_html_e( 'Tables', 'poker-tournament-import' ); ?></button>
		<button class="tdwp-tab-button" data-tab="players"><?php esc_html_e( 'Players', 'poker-tournament-import' ); ?></button>
		<button class="tdwp-tab-button" data-tab="transactions"><?php esc_html_e( 'Transactions', 'poker-tournament-import' ); ?></button>
		<button class="tdwp-tab-button" data-tab="stats"><?php esc_html_e( 'Stats', 'poker-tournament-import' ); ?></button>
	</div>

	<!-- Tab Panels -->
	<div class="tdwp-tab-content">

		<!-- Timer Tab -->
		<div id="tab-timer" class="tdwp-tab-panel active">
			<?php require_once __DIR__ . '/tabs/timer-tab.php'; ?>
		</div>

		<!-- Tables Tab -->
		<div id="tab-tables" class="tdwp-tab-panel">
			<?php require_once __DIR__ . '/tabs/tables-tab.php'; ?>
		</div>

		<!-- Players Tab - Tournament Roster -->
		<div id="tab-players" class="tdwp-tab-panel">
			<?php require_once __DIR__ . '/tabs/players-tab.php'; ?>
		</div>

		<!-- Transactions Tab - Transaction Log Viewer -->
		<div id="tab-transactions" class="tdwp-tab-panel">
			<?php require_once __DIR__ . '/tournament-manager/transaction-log-viewer.php'; ?>
		</div>

		<!-- Stats Tab (Stub) -->
		<div id="tab-stats" class="tdwp-tab-panel">
			<?php require_once __DIR__ . '/tabs/stats-tab.php'; ?>
		</div>

	</div>
</div>
