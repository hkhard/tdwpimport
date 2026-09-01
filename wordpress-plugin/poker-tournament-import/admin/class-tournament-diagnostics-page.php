<?php
/**
 * Tournament Diagnostics — read-only health report for the statistics data marts.
 *
 * Why this exists
 * ---------------
 * A tournament can render perfectly on its own page while contributing nothing
 * to season standings, player cards, or the Points Adjustments list. The two
 * surfaces read different sources:
 *
 *   - The tournament page re-parses `_tournament_raw_content` in real time and
 *     falls back to the mart only if that fails, so it keeps working even when
 *     the mart is empty.
 *   - Season standings, player profiles and the points adjuster read ONLY
 *     `poker_tournament_players`, joined on tournament_uuid.
 *
 * So a tournament can disappear from every aggregate with nothing, anywhere,
 * reporting a problem. This page makes that state visible.
 *
 * Strictly read-only: it runs SELECTs and renders. It never writes.
 *
 * @package Poker_Tournament_Import
 * @since 3.9.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'poker-tournament-import' ) );
}

global $wpdb;

$tdwp_diag_mart  = $wpdb->prefix . 'poker_tournament_players';
$tdwp_diag_roi   = $wpdb->prefix . 'poker_player_roi';
$tdwp_diag_adj   = $wpdb->prefix . 'tdwp_points_adjustments';

/**
 * Does a table exist? Missing tables are a finding in themselves.
 *
 * @param string $table Fully-prefixed table name.
 * @return bool
 */
$tdwp_diag_table_exists = static function ( $table ) use ( $wpdb ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
};

$tdwp_diag_has_mart = $tdwp_diag_table_exists( $tdwp_diag_mart );
$tdwp_diag_has_roi  = $tdwp_diag_table_exists( $tdwp_diag_roi );

// Every tournament, including drafts: a draft that should be published is itself
// a finding, and "configured correctly but invisible" is the case under study.
$tdwp_diag_tournaments = get_posts(
	array(
		'post_type'      => 'tournament',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

// Distinct tournament UUIDs actually present in the mart. Used to spot rows that
// belong to no post (the mirror image of a post with no rows).
$tdwp_diag_mart_uuids = array();
if ( $tdwp_diag_has_mart ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic read.
	$tdwp_diag_mart_uuids = (array) $wpdb->get_col( "SELECT DISTINCT tournament_id FROM {$tdwp_diag_mart}" );
}

$tdwp_diag_rows        = array();
$tdwp_diag_post_uuids  = array();
$tdwp_diag_problem_ct  = 0;

foreach ( $tdwp_diag_tournaments as $tdwp_diag_t ) {
	$tdwp_diag_id   = (int) $tdwp_diag_t->ID;
	$tdwp_diag_uuid = (string) get_post_meta( $tdwp_diag_id, 'tournament_uuid', true );
	$tdwp_diag_alt  = (string) get_post_meta( $tdwp_diag_id, '_tournament_uuid', true );

	$tdwp_diag_effective = $tdwp_diag_uuid ? $tdwp_diag_uuid : $tdwp_diag_alt;
	if ( $tdwp_diag_effective ) {
		$tdwp_diag_post_uuids[ $tdwp_diag_effective ] = true;
	}

	$tdwp_diag_mart_rows = 0;
	$tdwp_diag_roi_rows  = 0;
	$tdwp_diag_points    = null;

	if ( $tdwp_diag_has_mart && $tdwp_diag_effective ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic read.
		$tdwp_diag_mart_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tdwp_diag_mart} WHERE tournament_id = %s", $tdwp_diag_effective )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic read.
		$tdwp_diag_points = $wpdb->get_var(
			$wpdb->prepare( "SELECT ROUND(SUM(points)) FROM {$tdwp_diag_mart} WHERE tournament_id = %s", $tdwp_diag_effective )
		);
	}

	if ( $tdwp_diag_has_roi && $tdwp_diag_effective ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic read.
		$tdwp_diag_roi_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tdwp_diag_roi} WHERE tournament_id = %s", $tdwp_diag_effective )
		);
	}

	$tdwp_diag_season = get_post_meta( $tdwp_diag_id, '_season_id', true );
	$tdwp_diag_series = get_post_meta( $tdwp_diag_id, '_series_id', true );
	$tdwp_diag_raw    = (string) get_post_meta( $tdwp_diag_id, '_tournament_raw_content', true );

	/*
	 * Can "Repair Player Data" actually rebuild this tournament?
	 *
	 * Two conditions, and both matter:
	 *
	 *  1. It reads the `tournament_data` post meta and, failing that, tries to
	 *     reconstruct from `tournament_players` / `player_results`. With none
	 *     of those it inserts nothing and reports nothing.
	 *  2. It only scans posts with status `publish`. A draft is skipped in
	 *     silence, so promising a repair for one would send the operator to a
	 *     button that cannot touch it.
	 *
	 * Both were confirmed by running the tool, not by reading it.
	 */
	$tdwp_diag_td   = get_post_meta( $tdwp_diag_id, 'tournament_data', true );
	$tdwp_diag_tp   = get_post_meta( $tdwp_diag_id, 'tournament_players', true );
	$tdwp_diag_pr   = get_post_meta( $tdwp_diag_id, 'player_results', true );
	$tdwp_diag_has_data   = ( ! empty( $tdwp_diag_td['players'] ) || ! empty( $tdwp_diag_tp ) || ! empty( $tdwp_diag_pr ) );
	$tdwp_diag_published  = ( 'publish' === $tdwp_diag_t->post_status );
	$tdwp_diag_repairable = ( $tdwp_diag_has_data && $tdwp_diag_published );

	// Can the stored .tdt still be parsed? Only meaningful as a yes/no here; the
	// recalculator reports the detail. Parsing every tournament would be slow, so
	// this is a cheap structural check rather than a full parse.
	$tdwp_diag_raw_state = 'missing';
	if ( '' !== $tdwp_diag_raw ) {
		// A genuine .tdt escapes quotes inside its UserFormula text. Their absence
		// means the copy was stored through an unslashing write (pre-3.9.10).
		$tdwp_diag_raw_state = ( false === strpos( $tdwp_diag_raw, '\\"' ) && false !== strpos( $tdwp_diag_raw, 'UserFormula' ) )
			? 'damaged'
			: 'ok';
	}

	// Findings, most severe first.
	$tdwp_diag_problems = array();

	if ( '' === $tdwp_diag_effective ) {
		$tdwp_diag_problems[] = __( 'No tournament UUID — cannot be joined to any statistics.', 'poker-tournament-import' );
	} else {
		if ( '' === $tdwp_diag_uuid && '' !== $tdwp_diag_alt ) {
			$tdwp_diag_problems[] = __( 'UUID is stored only as _tournament_uuid; standings read tournament_uuid.', 'poker-tournament-import' );
		}
		if ( $tdwp_diag_has_mart && 0 === $tdwp_diag_mart_rows ) {
			// Name the remedy that will actually work for this tournament.
			if ( $tdwp_diag_repairable ) {
				$tdwp_diag_problems[] = __( 'No rows in the participation mart — invisible to season standings, player cards and the points adjuster. Fix: Settings → Repair Player Data.', 'poker-tournament-import' );
			} elseif ( $tdwp_diag_has_data && ! $tdwp_diag_published ) {
				$tdwp_diag_problems[] = __( 'No rows in the participation mart. The stored player data could rebuild it, but Repair Player Data only processes published tournaments and skips this one silently. Publish it first, then run the repair.', 'poker-tournament-import' );
			} else {
				$tdwp_diag_problems[] = __( 'No rows in the participation mart — invisible to season standings, player cards and the points adjuster. Repair Player Data cannot rebuild this one (no stored player data), so the .tdt must be re-imported.', 'poker-tournament-import' );
			}
		}
		if ( $tdwp_diag_has_roi && 0 === $tdwp_diag_roi_rows && $tdwp_diag_mart_rows > 0 ) {
			$tdwp_diag_problems[] = __( 'No ROI rows, though participation rows exist.', 'poker-tournament-import' );
		}
	}

	if ( empty( $tdwp_diag_season ) ) {
		$tdwp_diag_problems[] = __( 'No _season_id — excluded from season standings even if the page shows a season name.', 'poker-tournament-import' );
	}
	if ( empty( $tdwp_diag_series ) ) {
		$tdwp_diag_problems[] = __( 'No _series_id — excluded from series standings.', 'poker-tournament-import' );
	}
	if ( 'missing' === $tdwp_diag_raw_state ) {
		$tdwp_diag_problems[] = __( 'Original .tdt not stored — cannot be recalculated without re-importing.', 'poker-tournament-import' );
	} elseif ( 'damaged' === $tdwp_diag_raw_state ) {
		$tdwp_diag_problems[] = __( 'Stored .tdt was damaged by a pre-3.9.10 write — re-import to repair.', 'poker-tournament-import' );
	}

	if ( ! empty( $tdwp_diag_problems ) ) {
		$tdwp_diag_problem_ct++;
	}

	$tdwp_diag_rows[] = array(
		'id'        => $tdwp_diag_id,
		'title'     => $tdwp_diag_t->post_title,
		'status'    => $tdwp_diag_t->post_status,
		'date'      => get_the_date( 'Y-m-d', $tdwp_diag_t ),
		'uuid'      => $tdwp_diag_uuid,
		'alt_uuid'  => $tdwp_diag_alt,
		'season'    => $tdwp_diag_season,
		'series'    => $tdwp_diag_series,
		'mart'      => $tdwp_diag_mart_rows,
		'roi'       => $tdwp_diag_roi_rows,
		'points'    => $tdwp_diag_points,
		'raw'       => $tdwp_diag_raw_state,
		'repairable'=> $tdwp_diag_repairable,
		'has_data'  => $tdwp_diag_has_data,
		'problems'  => $tdwp_diag_problems,
	);
}

// Mart rows whose tournament UUID matches no post at all.
$tdwp_diag_orphans = array();
foreach ( $tdwp_diag_mart_uuids as $tdwp_diag_u ) {
	if ( '' !== $tdwp_diag_u && ! isset( $tdwp_diag_post_uuids[ $tdwp_diag_u ] ) ) {
		$tdwp_diag_orphans[] = $tdwp_diag_u;
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Tournament Diagnostics', 'poker-tournament-import' ); ?></h1>

	<p class="description" style="max-width:900px;">
		<?php esc_html_e( 'A tournament can look perfectly healthy on its own page while contributing nothing to season standings, player cards or the points adjuster. The tournament page re-reads the original .tdt file, but every statistic reads the participation mart instead. When a tournament is missing from that mart, nothing anywhere reports it. This page is read-only and changes nothing.', 'poker-tournament-import' ); ?>
	</p>

	<?php if ( ! $tdwp_diag_has_mart ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'The participation mart table does not exist. No statistics can be produced at all.', 'poker-tournament-import' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 0 === $tdwp_diag_problem_ct ) : ?>
		<div class="notice notice-success">
			<p>
				<?php
				printf(
					/* translators: %d: tournament count */
					esc_html__( 'All %d tournaments are correctly linked. Nothing to report.', 'poker-tournament-import' ),
					count( $tdwp_diag_rows )
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: 1: problem count, 2: total count */
					esc_html__( '%1$d of %2$d tournaments have a problem. Affected rows are highlighted below, with the specific finding in the last column.', 'poker-tournament-import' ),
					(int) $tdwp_diag_problem_ct,
					count( $tdwp_diag_rows )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $tdwp_diag_orphans ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Orphaned statistics rows:', 'poker-tournament-import' ); ?></strong>
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( '%d tournament UUID(s) exist in the mart with no matching tournament post. These inflate aggregates while being unreachable from the admin.', 'poker-tournament-import' ),
					count( $tdwp_diag_orphans )
				);
				?>
			</p>
			<p><code><?php echo esc_html( implode( ', ', array_slice( $tdwp_diag_orphans, 0, 10 ) ) ); ?></code></p>
		</div>
	<?php endif; ?>

	<table class="widefat striped" style="margin-top:14px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tournament', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Date', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'UUID', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Season', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Series', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Stat rows', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'ROI rows', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Points', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( '.tdt', 'poker-tournament-import' ); ?></th>
				<th title="<?php esc_attr_e( 'Whether Repair Player Data has stored data to rebuild from', 'poker-tournament-import' ); ?>"><?php esc_html_e( 'Repairable', 'poker-tournament-import' ); ?></th>
				<th><?php esc_html_e( 'Finding', 'poker-tournament-import' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $tdwp_diag_rows as $tdwp_diag_r ) : ?>
			<tr<?php echo empty( $tdwp_diag_r['problems'] ) ? '' : ' style="background:#fcf3f2;"'; ?>>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $tdwp_diag_r['id'] ) ); ?>"><?php echo esc_html( $tdwp_diag_r['title'] ); ?></a>
					<br /><span class="description">#<?php echo esc_html( $tdwp_diag_r['id'] ); ?></span>
				</td>
				<td><?php echo esc_html( $tdwp_diag_r['date'] ); ?></td>
				<td><?php echo esc_html( $tdwp_diag_r['status'] ); ?></td>
				<td>
					<?php if ( '' !== $tdwp_diag_r['uuid'] ) : ?>
						<code style="font-size:11px;"><?php echo esc_html( substr( $tdwp_diag_r['uuid'], 0, 8 ) ); ?></code>
					<?php elseif ( '' !== $tdwp_diag_r['alt_uuid'] ) : ?>
						<code style="font-size:11px;color:#b32d2e;">_alt <?php echo esc_html( substr( $tdwp_diag_r['alt_uuid'], 0, 8 ) ); ?></code>
					<?php else : ?>
						<strong style="color:#b32d2e;"><?php esc_html_e( 'none', 'poker-tournament-import' ); ?></strong>
					<?php endif; ?>
				</td>
				<td><?php echo $tdwp_diag_r['season'] ? esc_html( $tdwp_diag_r['season'] ) : '<strong style="color:#b32d2e;">&mdash;</strong>'; ?></td>
				<td><?php echo $tdwp_diag_r['series'] ? esc_html( $tdwp_diag_r['series'] ) : '<strong style="color:#b32d2e;">&mdash;</strong>'; ?></td>
				<td<?php echo 0 === $tdwp_diag_r['mart'] ? ' style="color:#b32d2e;font-weight:600;"' : ''; ?>><?php echo esc_html( $tdwp_diag_r['mart'] ); ?></td>
				<td<?php echo 0 === $tdwp_diag_r['roi'] ? ' style="color:#b32d2e;"' : ''; ?>><?php echo esc_html( $tdwp_diag_r['roi'] ); ?></td>
				<td><?php echo null === $tdwp_diag_r['points'] ? '&mdash;' : esc_html( number_format_i18n( (float) $tdwp_diag_r['points'], 0 ) ); ?></td>
				<td>
					<?php
					if ( 'ok' === $tdwp_diag_r['raw'] ) {
						echo '<span style="color:#007017;">' . esc_html__( 'ok', 'poker-tournament-import' ) . '</span>';
					} elseif ( 'damaged' === $tdwp_diag_r['raw'] ) {
						echo '<span style="color:#b32d2e;">' . esc_html__( 'damaged', 'poker-tournament-import' ) . '</span>';
					} else {
						echo '<span style="color:#996800;">' . esc_html__( 'missing', 'poker-tournament-import' ) . '</span>';
					}
					?>
				</td>
				<td>
					<?php
					// Only meaningful when rows are actually missing.
					if ( $tdwp_diag_r['mart'] > 0 ) {
						echo '<span class="description">&mdash;</span>';
					} elseif ( $tdwp_diag_r['repairable'] ) {
						echo '<span style="color:#007017;">' . esc_html__( 'yes', 'poker-tournament-import' ) . '</span>';
					} elseif ( $tdwp_diag_r['has_data'] ) {
						// Rebuildable, but the repair tool ignores non-published posts.
						echo '<span style="color:#996800;">' . esc_html__( 'publish first', 'poker-tournament-import' ) . '</span>';
					} else {
						echo '<span style="color:#b32d2e;">' . esc_html__( 're-import', 'poker-tournament-import' ) . '</span>';
					}
					?>
				</td>
				<td style="max-width:420px;">
					<?php if ( empty( $tdwp_diag_r['problems'] ) ) : ?>
						<span style="color:#007017;"><?php esc_html_e( 'OK', 'poker-tournament-import' ); ?></span>
					<?php else : ?>
						<ul style="margin:0;list-style:disc;padding-left:18px;">
							<?php foreach ( $tdwp_diag_r['problems'] as $tdwp_diag_p ) : ?>
								<li><?php echo esc_html( $tdwp_diag_p ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2 style="margin-top:26px;"><?php esc_html_e( 'How to read this', 'poker-tournament-import' ); ?></h2>
	<ul style="list-style:disc;margin-left:22px;max-width:900px;">
		<li><strong><?php esc_html_e( 'Stat rows = 0', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'the usual cause of a tournament that displays correctly but is missing from season totals, player cards and the points adjuster. Check the Repairable column for the remedy.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( 'Repairable = yes', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'the player data is still stored on the post, so Settings → Repair Player Data will rebuild the missing rows. It only processes published tournaments; a draft is skipped silently.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( 'Repairable = publish first', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'the data to rebuild from is there, but Repair Player Data only processes published tournaments and skips drafts silently. Publish it, then run the repair.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( 'Repairable = re-import', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'nothing remains to rebuild from, so Repair Player Data would run and change nothing. Re-import that tournament\'s .tdt file instead.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( 'UUID shown as _alt', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'the identifier is stored under the wrong key, so every statistics join misses. Re-importing rewrites it correctly.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( 'Season or Series blank', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'the tournament is not linked to a season or series post, so standings never consider it, even though its own page may display the season name from the imported file.', 'poker-tournament-import' ); ?></li>
		<li><strong><?php esc_html_e( '.tdt damaged or missing', 'poker-tournament-import' ); ?></strong> — <?php esc_html_e( 'points cannot be recalculated in place; re-import the file.', 'poker-tournament-import' ); ?></li>
	</ul>
</div>
