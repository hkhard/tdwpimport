<?php
/**
 * History Tab - Tournament Event Log
 *
 * Renders the chronological audit log for a single tournament.
 * $tournament_id is inherited from tournament-manager-control.php.
 *
 * @package    Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since      3.7.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this page.', 'poker-tournament-import' ) . '</p></div>';
	return;
}

$events_manager = new TDWP_Tournament_Events();
$events         = $events_manager->get_events(
	$tournament_id,
	array(
		'order' => 'ASC',
		'limit' => 200,
	)
);
?>

<div class="tdwp-history-panel">
	<h2><?php esc_html_e( 'Tournament History', 'poker-tournament-import' ); ?></h2>

	<?php if ( empty( $events ) ) : ?>
		<p class="tdwp-history-empty">
			<?php esc_html_e( 'No events have been recorded for this tournament yet.', 'poker-tournament-import' ); ?>
		</p>
	<?php else : ?>
		<table class="widefat striped tdwp-history-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'poker-tournament-import' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Event', 'poker-tournament-import' ); ?></th>
					<th scope="col"><?php esc_html_e( 'User', 'poker-tournament-import' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Details', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $event ) : ?>
					<?php
					$user_display = '';
					if ( ! empty( $event->user_id ) ) {
						$user_data = get_userdata( (int) $event->user_id );
						if ( $user_data ) {
							$user_display = $user_data->display_name;
						}
					}

					$details_parts = array();
					if ( ! empty( $event->event_data ) && is_array( $event->event_data ) ) {
						foreach ( $event->event_data as $key => $value ) {
							if ( is_scalar( $value ) ) {
								$details_parts[] = esc_html( ucwords( str_replace( '_', ' ', $key ) ) )
									. ': ' . esc_html( (string) $value );
							}
						}
					}
					?>
					<tr>
						<td class="tdwp-history-time">
							<?php
							echo esc_html(
								mysql2date(
									get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
									$event->created_at
								)
							);
							?>
						</td>
						<td class="tdwp-history-event">
							<span class="tdwp-event-badge tdwp-event-<?php echo esc_attr( $event->event_type ); ?>">
								<?php echo esc_html( TDWP_Tournament_Events::get_event_label( $event->event_type ) ); ?>
							</span>
						</td>
						<td class="tdwp-history-user">
							<?php echo esc_html( $user_display ); ?>
						</td>
						<td class="tdwp-history-details">
							<?php echo esc_html( implode( ', ', $details_parts ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.tdwp-history-panel {
	margin-top: 1em;
}
.tdwp-history-table {
	margin-top: .5em;
}
.tdwp-history-table th,
.tdwp-history-table td {
	padding: 8px 12px;
	vertical-align: top;
}
.tdwp-history-time {
	white-space: nowrap;
	color: #555;
	font-size: .9em;
}
.tdwp-event-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: .85em;
	font-weight: 600;
	background: #e0e0e0;
	color: #333;
}
.tdwp-event-tournament_created   { background: #d4edda; color: #155724; }
.tdwp-event-tournament_started   { background: #cce5ff; color: #004085; }
.tdwp-event-tournament_completed { background: #fff3cd; color: #856404; }
.tdwp-event-tournament_modified  { background: #f0f0f0; color: #333; }
.tdwp-history-details {
	color: #555;
	font-size: .9em;
}
.tdwp-history-empty {
	color: #777;
	font-style: italic;
}
</style>
