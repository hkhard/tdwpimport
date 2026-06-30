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
