<?php
/**
 * Points adjustments admin page (tdwp-31i).
 *
 * Shows the manual-override audit log and an add-override form. Rendered by
 * Poker_Tournament_Import_Admin::render_points_adjustments_page().
 *
 * @package Poker_Tournament_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'poker-tournament-import' ) );
}

$pa_filter_uuid = isset( $_GET['tournament_uuid'] ) ? sanitize_text_field( wp_unslash( $_GET['tournament_uuid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pa_paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pa_per_page    = 50;
$pa_offset      = ( $pa_paged - 1 ) * $pa_per_page;

$pa_manager = new Poker_Points_Adjustment_Manager();
$pa_rows    = $pa_manager->get_audit_log(
	$pa_filter_uuid ? array( 'tournament_uuid' => $pa_filter_uuid ) : array(),
	$pa_per_page,
	$pa_offset
);

/* -------------------------------------------------------------------------
 * Recalculate imported points (3.9.10).
 *
 * Handled inline rather than via admin-post so the preview renders on this same
 * screen. Both actions are nonce-protected and capability-checked above.
 * ---------------------------------------------------------------------- */
$pa_recalc_preview = null;
$pa_recalc_applied = null;

if ( isset( $_POST['tdwp_recalc_action'] ) && check_admin_referer( 'tdwp_recalc_points', 'tdwp_recalc_nonce' ) ) {
	$pa_recalc_action = sanitize_key( wp_unslash( $_POST['tdwp_recalc_action'] ) );

	if ( ! class_exists( 'Poker_Points_Recalculator' ) ) {
		require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'includes/class-points-recalculator.php';
	}
	$pa_recalculator = new Poker_Points_Recalculator();

	if ( 'preview' === $pa_recalc_action ) {
		$pa_recalc_preview = $pa_recalculator->recalculate_all( true );
	} elseif ( 'apply' === $pa_recalc_action ) {
		$pa_recalc_applied = $pa_recalculator->recalculate_all( false );
	}
}

// Tournaments for the select control (newest first).
$pa_tournaments = get_posts(
	array(
		'post_type'      => 'tournament',
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

/**
 * Resolve a player UUID to a display name (cached per request).
 *
 * @param string $uuid Player UUID.
 * @return string Name or the UUID.
 */
function tdwp_pa_player_name( $uuid ) {
	static $cache = array();
	if ( isset( $cache[ $uuid ] ) ) {
		return $cache[ $uuid ];
	}
	$posts = get_posts(
		array(
			'post_type'      => 'player',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => 'player_uuid',
					'value'   => $uuid,
					'compare' => '=',
				),
			),
		)
	);
	$name           = ( ! empty( $posts ) && ! empty( $posts[0]->post_title ) ) ? $posts[0]->post_title : $uuid;
	$cache[ $uuid ] = $name;
	return $name;
}
?>
<div class="wrap tdwp-pv-wrap">
	<h1><?php esc_html_e( 'Points Adjustments', 'poker-tournament-import' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Manually override a player\'s points for a tournament. Overrides are an insert-only audit log, survive re-imports, and are applied to season standings automatically.', 'poker-tournament-import' ); ?></p>

	<?php
	/* Recalculate imported points — preview then apply. */
	$pa_report = $pa_recalc_applied ? $pa_recalc_applied : $pa_recalc_preview;
	$pa_is_applied = (bool) $pa_recalc_applied;
	?>
	<div class="tdwp-pv-selector tdwp-pa-form" style="border-left:4px solid #72aee6;">
		<h2><?php esc_html_e( 'Recalculate imported points', 'poker-tournament-import' ); ?></h2>

		<p>
			<?php esc_html_e( 'Version 3.9.10 corrected three errors in how points were calculated when importing a Tournament Director file:', 'poker-tournament-import' ); ?>
		</p>
		<ul style="list-style:disc;margin-left:22px;">
			<li><?php esc_html_e( 'A player\'s manual points adjustment, set by the tournament director in TD, was ignored entirely.', 'poker-tournament-import' ); ?></li>
			<li><?php esc_html_e( 'Tournaments with re-entries counted players instead of entries, which lowered everyone\'s score.', 'poker-tournament-import' ); ?></li>
			<li><?php esc_html_e( 'A surrendered stake was left out of the prize pool.', 'poker-tournament-import' ); ?></li>
		</ul>

		<p>
			<strong><?php esc_html_e( 'What this button does:', 'poker-tournament-import' ); ?></strong>
			<?php esc_html_e( 'it re-reads the original .tdt content stored with each tournament, recalculates every player\'s points with the corrected rules, and updates the statistics tables. Season and series standings are refreshed and caches cleared afterwards.', 'poker-tournament-import' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'What it does not do:', 'poker-tournament-import' ); ?></strong>
			<?php esc_html_e( 'it never touches your manual overrides. Any player with an override on this page keeps that value, and nothing on the audit log below is altered or deleted. Tournament posts, players, prizes and finishing positions are all left as they are — only the points figure changes.', 'poker-tournament-import' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Always press Preview first. It shows exactly which players would change and by how much, without writing anything. Points will go up, not down, for tournaments that had re-entries. A tournament imported by a much older version may not have its original file stored — those are listed as skipped and need re-importing to correct.', 'poker-tournament-import' ); ?>
		</p>

		<form method="post" style="margin-top:14px;">
			<?php wp_nonce_field( 'tdwp_recalc_points', 'tdwp_recalc_nonce' ); ?>
			<button type="submit" name="tdwp_recalc_action" value="preview" class="button">
				<?php esc_html_e( 'Preview changes', 'poker-tournament-import' ); ?>
			</button>
			<?php if ( $pa_recalc_preview && ! empty( $pa_recalc_preview['tournaments'] ) ) : ?>
				<button type="submit" name="tdwp_recalc_action" value="apply" class="button button-primary"
					onclick="return confirm('<?php echo esc_js( __( 'Apply the recalculated points to your statistics? Manual overrides are preserved. This cannot be undone automatically, though re-importing a .tdt always restores that tournament.', 'poker-tournament-import' ) ); ?>');">
					<?php esc_html_e( 'Apply these changes', 'poker-tournament-import' ); ?>
				</button>
			<?php endif; ?>
		</form>

		<?php if ( $pa_report ) : ?>
			<?php if ( $pa_is_applied ) : ?>
				<div class="notice notice-success inline" style="margin-top:14px;">
					<p>
						<?php
						printf(
							/* translators: 1: tournament count, 2: player count */
							esc_html__( 'Applied. Updated %1$d tournament(s) and %2$d player result(s). Statistics have been rebuilt.', 'poker-tournament-import' ),
							(int) $pa_report['totals']['tournaments'],
							(int) $pa_report['totals']['players_changed']
						);
						?>
					</p>
				</div>
			<?php elseif ( empty( $pa_report['tournaments'] ) ) : ?>
				<div class="notice notice-success inline" style="margin-top:14px;">
					<p><?php esc_html_e( 'Nothing to change — every imported tournament already has correct points.', 'poker-tournament-import' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-warning inline" style="margin-top:14px;">
					<p>
						<?php
						printf(
							/* translators: 1: tournament count, 2: player count, 3: skipped count */
							esc_html__( 'Preview only — nothing has been saved. %1$d tournament(s) and %2$d player result(s) would change. %3$d tournament(s) cannot be recalculated.', 'poker-tournament-import' ),
							(int) $pa_report['totals']['tournaments'],
							(int) $pa_report['totals']['players_changed'],
							(int) $pa_report['totals']['skipped']
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $pa_report['tournaments'] ) ) : ?>
				<table class="widefat striped" style="margin-top:10px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Points now', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Points after', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Change', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Note', 'poker-tournament-import' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $pa_report['tournaments'] as $pa_tr ) : ?>
						<?php if ( 'skipped' === $pa_tr['status'] || 'error' === $pa_tr['status'] ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $pa_tr['title'] ); ?></strong></td>
								<td colspan="5"><em><?php echo esc_html( $pa_tr['message'] ); ?></em></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $pa_tr['changes'] as $pa_i => $pa_ch ) : ?>
								<tr>
									<td><?php echo 0 === $pa_i ? '<strong>' . esc_html( $pa_tr['title'] ) . '</strong>' : ''; ?></td>
									<td><?php echo esc_html( $pa_ch['player'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $pa_ch['from'], 0 ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $pa_ch['to'], 0 ) ); ?></td>
									<td>
										<?php
										$pa_delta = $pa_ch['to'] - $pa_ch['from'];
										if ( abs( $pa_delta ) >= 0.005 ) {
											printf(
												'<span style="color:%s;">%s%s</span>',
												$pa_delta > 0 ? '#007017' : '#b32d2e',
												$pa_delta > 0 ? '+' : '',
												esc_html( number_format_i18n( $pa_delta, 0 ) )
											);
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<?php
										if ( '' !== $pa_ch['note'] ) {
											echo esc_html( $pa_ch['note'] );
										} elseif ( abs( (float) $pa_ch['adjustment'] ) > 0 ) {
											printf(
												/* translators: %s: signed adjustment value */
												esc_html__( 'includes a %s adjustment from the .tdt', 'poker-tournament-import' ),
												esc_html( ( $pa_ch['adjustment'] > 0 ? '+' : '' ) . number_format_i18n( $pa_ch['adjustment'], 0 ) )
											);
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="tdwp-pv-selector tdwp-pa-form">
		<h2><?php esc_html_e( 'Add an override', 'poker-tournament-import' ); ?></h2>
		<p>
			<label for="tdwp-pa-tournament"><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></label><br />
			<select id="tdwp-pa-tournament">
				<option value=""><?php esc_html_e( '— Select —', 'poker-tournament-import' ); ?></option>
				<?php foreach ( $pa_tournaments as $pa_t ) : ?>
					<option value="<?php echo esc_attr( $pa_t->ID ); ?>"><?php echo esc_html( $pa_t->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="tdwp-pa-player"><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></label><br />
			<select id="tdwp-pa-player" disabled><option value=""><?php esc_html_e( '— Select a tournament first —', 'poker-tournament-import' ); ?></option></select>
		</p>
		<p>
			<label for="tdwp-pa-points"><?php esc_html_e( 'New points', 'poker-tournament-import' ); ?></label><br />
			<input type="number" step="0.0001" id="tdwp-pa-points" />
		</p>
		<p>
			<label for="tdwp-pa-reason"><?php esc_html_e( 'Reason (required)', 'poker-tournament-import' ); ?></label><br />
			<input type="text" id="tdwp-pa-reason" maxlength="500" class="regular-text" />
		</p>
		<p>
			<button type="button" class="button button-primary" id="tdwp-pa-save"><?php esc_html_e( 'Save override', 'poker-tournament-import' ); ?></button>
			<span class="spinner tdwp-pv-spinner" id="tdwp-pa-spinner"></span>
		</p>
	</div>

	<h2><?php esc_html_e( 'Audit log', 'poker-tournament-import' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Old', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'New', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Delta', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'By', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'When', 'poker-tournament-import' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $pa_rows ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No adjustments recorded yet.', 'poker-tournament-import' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $pa_rows as $pa_row ) : ?>
					<?php
					$pa_delta = floatval( $pa_row->adjusted_points ) - floatval( $pa_row->original_points );
					$pa_user  = $pa_row->actor_user_id ? get_userdata( $pa_row->actor_user_id ) : false;
					?>
					<tr>
						<td><code><?php echo esc_html( substr( $pa_row->tournament_uuid, 0, 8 ) ); ?></code></td>
						<td><?php echo esc_html( tdwp_pa_player_name( $pa_row->player_uuid ) ); ?></td>
						<td><?php echo esc_html( rtrim( rtrim( $pa_row->original_points, '0' ), '.' ) ); ?></td>
						<td><?php echo esc_html( rtrim( rtrim( $pa_row->adjusted_points, '0' ), '.' ) ); ?></td>
						<td class="<?php echo $pa_delta > 0 ? 'tdwp-pv-up' : ( $pa_delta < 0 ? 'tdwp-pv-down' : '' ); ?>">
							<?php echo esc_html( ( $pa_delta > 0 ? '+' : '' ) . rtrim( rtrim( number_format( $pa_delta, 4 ), '0' ), '.' ) ); ?>
						</td>
						<td><?php echo esc_html( $pa_row->reason ); ?></td>
						<td><?php echo esc_html( $pa_user ? $pa_user->display_name : '—' ); ?></td>
						<td><?php echo esc_html( $pa_row->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pa_paged > 1 || count( $pa_rows ) === $pa_per_page ) : ?>
		<p class="tdwp-pa-pagination">
			<?php if ( $pa_paged > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $pa_paged - 1 ) ); ?>"><?php esc_html_e( '&laquo; Previous', 'poker-tournament-import' ); ?></a>
			<?php endif; ?>
			<?php if ( count( $pa_rows ) === $pa_per_page ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $pa_paged + 1 ) ); ?>"><?php esc_html_e( 'Next &raquo;', 'poker-tournament-import' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
