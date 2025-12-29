<?php
/**
 * Debug Log Viewer Page
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle log actions.
if ( isset( $_POST['tdwp_clear_log'] ) && check_admin_referer( 'tdwp_debug_log_action' ) ) {
	TDWP_Debug_Logger::clear_log();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Debug log cleared.', 'poker-tournament-import' ) . '</p></div>';
}

if ( isset( $_POST['tdwp_enable_logging'] ) && check_admin_referer( 'tdwp_debug_log_action' ) ) {
	TDWP_Debug_Logger::enable();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Debug logging enabled.', 'poker-tournament-import' ) . '</p></div>';
}

if ( isset( $_POST['tdwp_disable_logging'] ) && check_admin_referer( 'tdwp_debug_log_action' ) ) {
	TDWP_Debug_Logger::disable();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Debug logging disabled.', 'poker-tournament-import' ) . '</p></div>';
}

$is_enabled  = TDWP_Debug_Logger::is_enabled();
$log_file    = TDWP_Debug_Logger::get_log_file();
$log_content = TDWP_Debug_Logger::get_log_contents();
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Tournament Debug Log', 'poker-tournament-import' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Debug log for troubleshooting tournament clock issues (Beta12).', 'poker-tournament-import' ); ?>
		<br>
		<?php
		printf(
			/* translators: %s: log file path */
			esc_html__( 'Log file: %s', 'poker-tournament-import' ),
			'<code>' . esc_html( $log_file ) . '</code>'
		);
		?>
	</p>

	<div style="margin: 20px 0;">
		<form method="post" style="display: inline;">
			<?php wp_nonce_field( 'tdwp_debug_log_action' ); ?>
			<?php if ( $is_enabled ) : ?>
				<button type="submit" name="tdwp_disable_logging" class="button">
					<?php esc_html_e( 'Disable Logging', 'poker-tournament-import' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" name="tdwp_enable_logging" class="button">
					<?php esc_html_e( 'Enable Logging', 'poker-tournament-import' ); ?>
				</button>
			<?php endif; ?>
		</form>

		<form method="post" style="display: inline;">
			<?php wp_nonce_field( 'tdwp_debug_log_action' ); ?>
			<button type="submit" name="tdwp_clear_log" class="button" onclick="return confirm('<?php echo esc_js( __( 'Clear debug log?', 'poker-tournament-import' ) ); ?>');">
				<?php esc_html_e( 'Clear Log', 'poker-tournament-import' ); ?>
			</button>
		</form>

		<button type="button" class="button" onclick="location.reload();">
			<?php esc_html_e( 'Refresh', 'poker-tournament-import' ); ?>
		</button>

		<span style="margin-left: 20px;">
			<strong><?php esc_html_e( 'Status:', 'poker-tournament-import' ); ?></strong>
			<?php if ( $is_enabled ) : ?>
				<span style="color: green;"><?php esc_html_e( 'Enabled', 'poker-tournament-import' ); ?></span>
			<?php else : ?>
				<span style="color: red;"><?php esc_html_e( 'Disabled', 'poker-tournament-import' ); ?></span>
			<?php endif; ?>
		</span>
	</div>

	<?php if ( empty( $log_content ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No log entries yet.', 'poker-tournament-import' ); ?></p>
		</div>
	<?php else : ?>
		<div style="background: #f5f5f5; border: 1px solid #ccc; padding: 15px; overflow-x: auto;">
			<pre style="margin: 0; font-family: monospace; font-size: 12px; line-height: 1.5;"><?php echo esc_html( $log_content ); ?></pre>
		</div>
	<?php endif; ?>
</div>
