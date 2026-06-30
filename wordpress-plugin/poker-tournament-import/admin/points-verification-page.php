<?php
/**
 * Points verification admin page template.
 *
 * Rendered by Poker_Tournament_Import_Admin::render_points_verification_page().
 * Expects $verifier (Poker_Tournament_Points_Verifier) and the query var
 * `tournament_id` or `season_id` to choose between tournament and season mode.
 *
 * @package Poker_Tournament_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'poker-tournament-import' ) );
}

$pv_tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pv_season_id     = isset( $_GET['season_id'] ) ? absint( $_GET['season_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/**
 * Render a coloured health badge.
 *
 * @param string $severity ok|warning|critical.
 * @return string HTML.
 */
function tdwp_pv_health_badge( $severity ) {
	$labels = array(
		'ok'       => __( 'OK', 'poker-tournament-import' ),
		'warning'  => __( 'Warning', 'poker-tournament-import' ),
		'critical' => __( 'Critical', 'poker-tournament-import' ),
	);
	$label = isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
	return '<span class="tdwp-pv-badge tdwp-pv-badge-' . esc_attr( $severity ) . '">' . esc_html( $label ) . '</span>';
}
?>
<div class="wrap tdwp-pv-wrap">
	<h1><?php esc_html_e( 'Points Verification', 'poker-tournament-import' ); ?></h1>

	<?php if ( ! $pv_tournament_id && ! $pv_season_id ) : ?>
		<p><?php esc_html_e( 'Open a tournament from the Tournaments list and click "Review Points", or pass a tournament_id / season_id.', 'poker-tournament-import' ); ?></p>
	<?php endif; ?>

	<?php
	// ---- SEASON MODE -----------------------------------------------------
	if ( $pv_season_id ) :
		$pv_season    = get_post( $pv_season_id );
		$pv_season_t  = $pv_season ? $pv_season->post_title : (string) $pv_season_id;
		$pv_rows      = $verifier->get_season_tournaments_with_health( $pv_season_id );
		?>
		<h2>
			<?php
			/* translators: %s: season title */
			printf( esc_html__( 'Season: %s', 'poker-tournament-import' ), esc_html( $pv_season_t ) );
			?>
		</h2>
		<table class="widefat striped tdwp-pv-season-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Date', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Players', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Monies', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Points Sum', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Anomalies', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Formula Used', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Verified', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Health', 'poker-tournament-import' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $pv_rows ) ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No tournaments in this season.', 'poker-tournament-import' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $pv_rows as $pv_row ) : ?>
						<tr>
							<td><?php echo esc_html( $pv_row['title'] ); ?></td>
							<td><?php echo esc_html( $pv_row['date'] ); ?></td>
							<td><?php echo esc_html( $pv_row['player_count'] ); ?></td>
							<td>
								<?php echo esc_html( number_format( $pv_row['monies'], 0 ) ); ?>
								<?php if ( $pv_row['estimated'] ) : ?><em>(<?php esc_html_e( 'est.', 'poker-tournament-import' ); ?>)</em><?php endif; ?>
							</td>
							<td><?php echo esc_html( $pv_row['points_sum'] ); ?></td>
							<td><?php echo esc_html( $pv_row['anomaly_count'] ); ?></td>
							<td><?php echo esc_html( $pv_row['formula_used'] ); ?></td>
							<td><?php echo $pv_row['verified'] ? '&#10003;' : '&mdash;'; ?></td>
							<td><?php echo tdwp_pv_health_badge( $pv_row['severity'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=poker-points-verification&tournament_id=' . $pv_row['post_id'] ) ); ?>">
									<?php esc_html_e( 'Review', 'poker-tournament-import' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	endif;

	// ---- TOURNAMENT MODE -------------------------------------------------
	if ( $pv_tournament_id ) :
		$pv_summary = $verifier->get_tournament_summary( $pv_tournament_id );
		if ( is_wp_error( $pv_summary ) ) :
			?>
			<div class="notice notice-error"><p><?php echo esc_html( $pv_summary->get_error_message() ); ?></p></div>
			<?php
		else :
			$pv_fin    = $pv_summary['financials'];
			$pv_health = $pv_summary['health'];
			?>
			<h2><?php echo esc_html( $pv_summary['title'] ); ?> <?php echo tdwp_pv_health_badge( $pv_health['severity'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>

			<?php if ( ! empty( $pv_health['messages'] ) ) : ?>
				<div class="notice notice-<?php echo 'critical' === $pv_health['severity'] ? 'error' : 'warning'; ?> tdwp-pv-anomaly-banner">
					<ul>
						<?php foreach ( $pv_health['messages'] as $pv_msg ) : ?>
							<li><?php echo esc_html( $pv_msg ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="tdwp-pv-financials">
				<h3><?php esc_html_e( 'Financial Summary', 'poker-tournament-import' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Players:', 'poker-tournament-import' ); ?> <strong><?php echo esc_html( $pv_summary['player_count'] ); ?></strong></li>
					<li><?php esc_html_e( 'Buy-in:', 'poker-tournament-import' ); ?> <strong><?php echo esc_html( number_format( $pv_fin['buy_in'], 0 ) ); ?></strong></li>
					<li>
						<?php esc_html_e( 'Monies:', 'poker-tournament-import' ); ?>
						<strong><?php echo esc_html( number_format( $pv_fin['monies'], 0 ) ); ?></strong>
						<?php if ( $pv_fin['estimated'] ) : ?><em>(<?php esc_html_e( 'estimated', 'poker-tournament-import' ); ?>)</em><?php endif; ?>
					</li>
					<li><?php esc_html_e( 'Total buy-ins:', 'poker-tournament-import' ); ?> <strong><?php echo esc_html( $pv_fin['total_buyins'] ); ?></strong></li>
					<li><?php esc_html_e( 'Points sum:', 'poker-tournament-import' ); ?> <strong><?php echo esc_html( $pv_health['sum'] ); ?></strong></li>
				</ul>
			</div>

			<details class="tdwp-pv-current" open>
				<summary><?php esc_html_e( 'Current stored points', 'poker-tournament-import' ); ?></summary>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Pos', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Hits', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Winnings', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Points', 'poker-tournament-import' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pv_summary['players'] as $pv_player ) : ?>
							<tr>
								<td><?php echo esc_html( $pv_player['finish_position'] ); ?></td>
								<td><?php echo esc_html( $pv_player['name'] ); ?></td>
								<td><?php echo esc_html( $pv_player['hits'] ); ?></td>
								<td><?php echo esc_html( number_format( $pv_player['winnings'], 0 ) ); ?></td>
								<td class="<?php echo $pv_player['points'] < 0 ? 'tdwp-pv-negative' : ''; ?>">
									<?php echo esc_html( $pv_player['points'] ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>

			<div class="tdwp-pv-selector" data-post-id="<?php echo esc_attr( $pv_tournament_id ); ?>">
				<h3><?php esc_html_e( 'Preview &amp; apply a formula', 'poker-tournament-import' ); ?></h3>
				<fieldset class="tdwp-pv-formula-list">
					<?php $pv_first = true; ?>
					<?php foreach ( $pv_summary['formulas'] as $pv_key => $pv_label ) : ?>
						<label>
							<input type="radio" name="tdwp_pv_formula" value="<?php echo esc_attr( $pv_key ); ?>" <?php checked( $pv_first ); ?> />
							<?php echo esc_html( $pv_label ); ?>
						</label>
						<?php $pv_first = false; ?>
					<?php endforeach; ?>
				</fieldset>
				<p>
					<button type="button" class="button button-secondary tdwp-pv-preview-btn"><?php esc_html_e( 'Preview', 'poker-tournament-import' ); ?></button>
					<button type="button" class="button button-primary tdwp-pv-apply-btn" disabled><?php esc_html_e( 'Apply selected formula', 'poker-tournament-import' ); ?></button>
					<span class="spinner tdwp-pv-spinner"></span>
				</p>
				<div class="tdwp-pv-preview-panel" hidden></div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
