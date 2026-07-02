<?php
/**
 * Data Consolidation admin page (tdwp-eil Phases D/E — no-CLI production cutover UI).
 *
 * Turns the operator steps that were run via wp-cli/mysql on the dev stage into UI-triggered,
 * nonce- and capability-guarded, batched, idempotent, reversible actions so the live/legacy
 * player-stats consolidation can be performed on a production site with no DB CLI:
 *   1. Backfill imports into the canonical source (batched, idempotent).
 *   2. Reconcile review (read-only dry run) — the human gate before cutover.
 *   3. Enable / disable cutover + rollback (remove import rows).
 *
 * @package Poker_Tournament_Import
 * @since 3.6.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDWP_Data_Consolidation_Admin {

	const PAGE_SLUG    = 'poker-data-consolidation';
	const BATCH_SIZE   = 25; // tournaments per AJAX request
	const REVIEW_FLAG  = 'tdwp_eil_reconcile_clean'; // set when a full reconcile found 0 mismatches

	/**
	 * Register menu, AJAX, and admin-post handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'wp_ajax_tdwp_eil_backfill_batch', array( __CLASS__, 'ajax_backfill_batch' ) );
		add_action( 'wp_ajax_tdwp_eil_reconcile_batch', array( __CLASS__, 'ajax_reconcile_batch' ) );
		add_action( 'admin_post_tdwp_eil_enable', array( __CLASS__, 'handle_enable' ) );
		add_action( 'admin_post_tdwp_eil_disable', array( __CLASS__, 'handle_disable' ) );
		add_action( 'admin_post_tdwp_eil_rollback', array( __CLASS__, 'handle_rollback' ) );
	}

	/**
	 * Add the submenu page under the plugin menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'poker-tournament-import',
			__( 'Data Consolidation', 'poker-tournament-import' ),
			__( 'Data Consolidation', 'poker-tournament-import' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Whether the canonical schema columns exist yet (v3.6.3/v3.6.4 migrations applied).
	 *
	 * @return bool
	 */
	private static function schema_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'tdwp_tournament_players';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection.
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		return in_array( 'tournament_uuid', (array) $cols, true )
			&& in_array( 'source', (array) $cols, true )
			&& in_array( 'import_buyins', (array) $cols, true );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'poker-tournament-import' ) );
		}

		global $wpdb;
		$schema_ready = self::schema_ready();
		$enabled      = class_exists( 'TDWP_Stats_Rollup' ) && TDWP_Stats_Rollup::is_enabled();
		$reviewed     = (bool) get_option( self::REVIEW_FLAG, false );
		$src          = $wpdb->prefix . 'tdwp_tournament_players';
		$live_ct      = 0;
		$import_ct    = 0;
		if ( $schema_ready ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Status counts.
			$live_ct = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$src} WHERE source = 'live'" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Status counts.
			$import_ct = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$src} WHERE source = 'import'" );
		}
		$import_tournaments = class_exists( 'TDWP_Stats_Rollup' ) ? TDWP_Stats_Rollup::count_import_tournaments() : 0;
		$ajax_nonce = wp_create_nonce( 'tdwp_eil_ajax' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Live / Legacy Data Consolidation', 'poker-tournament-import' ); ?></h1>
			<?php
			$notice = get_transient( 'tdwp_eil_notice' );
			if ( is_array( $notice ) ) {
				delete_transient( 'tdwp_eil_notice' );
				printf(
					'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ),
					esc_html( $notice['message'] )
				);
			}
			?>
			<p class="description">
				<?php esc_html_e( 'Consolidates live and imported tournament participation onto one canonical store so the stats bridge is no longer needed. Additive and reversible: enabling is inert until a live tournament finishes, and the backfill never writes the stats mart. Take a backup or export first anyway.', 'poker-tournament-import' ); ?>
			</p>

			<?php if ( ! $schema_ready ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'Canonical schema columns are not present yet. Load any admin page once after updating the plugin so the schema migration (v3.6.4) runs, then return here.', 'poker-tournament-import' ); ?>
				</p></div>
			<?php endif; ?>

			<div class="card" style="max-width:820px;margin-top:20px;">
				<h2><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></h2>
				<ul style="list-style:disc;margin-left:20px;">
					<li><?php echo esc_html( sprintf( __( 'Schema ready: %s', 'poker-tournament-import' ), $schema_ready ? __( 'yes', 'poker-tournament-import' ) : __( 'no', 'poker-tournament-import' ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Canonical source rows — live: %1$d, imported: %2$d', 'poker-tournament-import' ), $live_ct, $import_ct ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Imported tournaments in mart: %d', 'poker-tournament-import' ), $import_tournaments ) ); ?></li>
					<li><strong><?php echo esc_html( sprintf( __( 'Rollup cutover: %s', 'poker-tournament-import' ), $enabled ? __( 'ENABLED', 'poker-tournament-import' ) : __( 'disabled', 'poker-tournament-import' ) ) ); ?></strong></li>
					<li><?php echo esc_html( sprintf( __( 'Last reconcile clean: %s', 'poker-tournament-import' ), $reviewed ? __( 'yes', 'poker-tournament-import' ) : __( 'not yet', 'poker-tournament-import' ) ) ); ?></li>
				</ul>
			</div>

			<div class="card" style="max-width:820px;margin-top:20px;">
				<h2><?php esc_html_e( 'Step 1 — Backfill imported tournaments into the canonical source', 'poker-tournament-import' ); ?></h2>
				<p><?php esc_html_e( 'Represents each imported tournament as synthetic per-entry rows in the canonical store. Idempotent and batched — safe to re-run.', 'poker-tournament-import' ); ?></p>
				<button type="button" class="button button-primary" id="tdwp-eil-backfill" <?php disabled( ! $schema_ready ); ?>><?php esc_html_e( 'Run Backfill', 'poker-tournament-import' ); ?></button>
				<div id="tdwp-eil-backfill-progress" style="margin-top:10px;"></div>
			</div>

			<div class="card" style="max-width:820px;margin-top:20px;">
				<h2><?php esc_html_e( 'Step 2 — Reconcile review (read-only)', 'poker-tournament-import' ); ?></h2>
				<p><?php esc_html_e( 'Compares what the rollup would produce against the current stats mart. Review this before enabling the cutover. Zero mismatches means the rollup faithfully reproduces the mart.', 'poker-tournament-import' ); ?></p>
				<button type="button" class="button" id="tdwp-eil-reconcile" <?php disabled( ! $schema_ready ); ?>><?php esc_html_e( 'Run Reconcile', 'poker-tournament-import' ); ?></button>
				<div id="tdwp-eil-reconcile-progress" style="margin-top:10px;"></div>
				<div id="tdwp-eil-reconcile-result" style="margin-top:10px;"></div>
			</div>

			<div class="card" style="max-width:820px;margin-top:20px;">
				<h2><?php esc_html_e( 'Step 3 — Cutover', 'poker-tournament-import' ); ?></h2>
				<p><?php esc_html_e( 'Enable the rollup as the single mart writer for live tournaments; the stats bridge stands down. Only available after a clean reconcile.', 'poker-tournament-import' ); ?></p>
				<?php if ( ! $enabled ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Enable the rollup cutover now?', 'poker-tournament-import' ) ); ?>');">
						<input type="hidden" name="action" value="tdwp_eil_enable" />
						<?php wp_nonce_field( 'tdwp_eil_enable' ); ?>
						<button type="submit" class="button button-primary" <?php disabled( ! $reviewed ); ?>><?php esc_html_e( 'Enable Cutover', 'poker-tournament-import' ); ?></button>
						<?php if ( ! $reviewed ) : ?><span class="description"><?php esc_html_e( '(run a clean reconcile first)', 'poker-tournament-import' ); ?></span><?php endif; ?>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Disable the cutover? The stats bridge resumes.', 'poker-tournament-import' ) ); ?>');">
						<input type="hidden" name="action" value="tdwp_eil_disable" />
						<?php wp_nonce_field( 'tdwp_eil_disable' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Disable Cutover', 'poker-tournament-import' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="card" style="max-width:820px;margin-top:20px;border-left:4px solid #d63638;">
				<h2><?php esc_html_e( 'Rollback', 'poker-tournament-import' ); ?></h2>
				<p><?php esc_html_e( 'Disable the cutover and remove all imported synthetic rows from the canonical source (live rows are never touched). The stats mart is unaffected.', 'poker-tournament-import' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Roll back: disable cutover and delete imported canonical rows?', 'poker-tournament-import' ) ); ?>');">
					<input type="hidden" name="action" value="tdwp_eil_rollback" />
					<?php wp_nonce_field( 'tdwp_eil_rollback' ); ?>
					<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Roll Back Consolidation', 'poker-tournament-import' ); ?></button>
				</form>
			</div>
		</div>

		<script>
		( function() {
			var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $ajax_nonce ); ?>;

			function runBatches( action, progressEl, done ) {
				var offset = 0, totals = { processed: 0, total: 0, extra: {} };
				progressEl.textContent = '<?php echo esc_js( __( 'Starting…', 'poker-tournament-import' ) ); ?>';
				function step() {
					var body = new URLSearchParams();
					body.set( 'action', action );
					body.set( 'nonce', nonce );
					body.set( 'offset', offset );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function( r ) { return r.json(); } )
						.then( function( res ) {
							if ( ! res || ! res.success ) {
								progressEl.textContent = '<?php echo esc_js( __( 'Error: ', 'poker-tournament-import' ) ); ?>' + ( res && res.data ? res.data : 'unknown' );
								return;
							}
							var d = res.data;
							totals.total = d.total;
							totals.processed = d.next_offset;
							if ( d.acc ) { totals.extra = d.acc; }
							progressEl.textContent = totals.processed + ' / ' + d.total;
							if ( d.done ) { done( d ); } else { offset = d.next_offset; step(); }
						} )
						.catch( function( e ) { progressEl.textContent = 'Error: ' + e; } );
				}
				step();
			}

			var bf = document.getElementById( 'tdwp-eil-backfill' );
			if ( bf ) {
				bf.addEventListener( 'click', function() {
					bf.disabled = true;
					runBatches( 'tdwp_eil_backfill_batch', document.getElementById( 'tdwp-eil-backfill-progress' ), function( d ) {
						document.getElementById( 'tdwp-eil-backfill-progress' ).textContent =
							'<?php echo esc_js( __( 'Backfill complete. Inserted rows: ', 'poker-tournament-import' ) ); ?>' + ( d.acc && d.acc.inserted || 0 ) +
							' · <?php echo esc_js( __( 'flagged (no buy-in): ', 'poker-tournament-import' ) ); ?>' + ( d.acc && d.acc.flagged || 0 );
						bf.disabled = false;
					} );
				} );
			}

			var rc = document.getElementById( 'tdwp-eil-reconcile' );
			if ( rc ) {
				rc.addEventListener( 'click', function() {
					rc.disabled = true;
					runBatches( 'tdwp_eil_reconcile_batch', document.getElementById( 'tdwp-eil-reconcile-progress' ), function( d ) {
						var mm = d.acc && d.acc.mismatches || 0;
						var el = document.getElementById( 'tdwp-eil-reconcile-result' );
						el.innerHTML = '<strong>' + ( mm === 0
							? '<?php echo esc_js( __( '✓ Clean — 0 mismatches. You can enable the cutover.', 'poker-tournament-import' ) ); ?>'
							: ( mm + ' <?php echo esc_js( __( 'mismatches found — review before enabling.', 'poker-tournament-import' ) ); ?>' ) ) + '</strong>';
						if ( d.acc && d.acc.details && d.acc.details.length ) {
							el.innerHTML += '<pre style="white-space:pre-wrap;max-height:300px;overflow:auto;">' +
								d.acc.details.map( function( x ) { return x.replace( /</g, '&lt;' ); } ).join( '\n' ) + '</pre>';
						}
						rc.disabled = false;
						location.reload();
					} );
				} );
			}
		} )();
		</script>
		<?php
	}

	/* ------------------------------------------------------------------ AJAX */

	/**
	 * Verify AJAX nonce + capability, or die with a JSON error.
	 *
	 * @return void
	 */
	private static function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		if ( ! check_ajax_referer( 'tdwp_eil_ajax', 'nonce', false ) ) {
			wp_send_json_error( 'bad_nonce', 400 );
		}
	}

	/**
	 * Backfill one batch of imported tournaments into the real canonical source.
	 *
	 * @return void
	 */
	public static function ajax_backfill_batch() {
		self::verify_ajax();
		global $wpdb;

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$total  = TDWP_Stats_Rollup::count_import_tournaments();
		$src    = $wpdb->prefix . 'tdwp_tournament_players';

		$acc = get_transient( 'tdwp_eil_backfill_acc' );
		if ( 0 === $offset || ! is_array( $acc ) ) {
			$acc = array( 'inserted' => 0, 'flagged' => 0 );
		}

		$res = TDWP_Stats_Rollup::backfill_imports( $src, self::BATCH_SIZE, $offset );
		$acc['inserted'] += (int) $res['inserted'];
		$acc['flagged']  += count( $res['flagged_no_buyin'] );

		$next = $offset + self::BATCH_SIZE;
		$done = $next >= $total;
		set_transient( 'tdwp_eil_backfill_acc', $acc, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'total'       => $total,
				'next_offset' => $next,
				'done'        => $done,
				'acc'         => $acc,
			)
		);
	}

	/**
	 * Reconcile one batch of tournaments (read-only) and accumulate mismatch details.
	 *
	 * @return void
	 */
	public static function ajax_reconcile_batch() {
		self::verify_ajax();

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$total  = TDWP_Stats_Rollup::count_source_tournaments();

		$acc = get_transient( 'tdwp_eil_reconcile_acc' );
		if ( 0 === $offset || ! is_array( $acc ) ) {
			$acc = array( 'mismatches' => 0, 'details' => array() );
		}

		$report = TDWP_Stats_Rollup::reconcile_report( null, null, self::BATCH_SIZE, $offset );
		foreach ( $report as $r ) {
			$n = count( $r['mismatches'] );
			if ( $n > 0 ) {
				$acc['mismatches'] += $n;
				if ( count( $acc['details'] ) < 100 ) {
					$acc['details'][] = $r['tournament_uuid'] . ': ' . $n . ' mismatch(es); rollup=' . $r['rollup_rows'] . ' mart=' . $r['mart_rows'];
				}
			}
		}

		$next = $offset + self::BATCH_SIZE;
		$done = $next >= $total;
		set_transient( 'tdwp_eil_reconcile_acc', $acc, HOUR_IN_SECONDS );

		if ( $done ) {
			// Record whether the whole run was clean — gates the Enable button.
			update_option( self::REVIEW_FLAG, 0 === (int) $acc['mismatches'] ? 1 : 0 );
		}

		wp_send_json_success(
			array(
				'total'       => $total,
				'next_offset' => $next,
				'done'        => $done,
				'acc'         => $acc,
			)
		);
	}

	/* ------------------------------------------------------------ admin-post */

	/**
	 * Enable the rollup cutover.
	 *
	 * @return void
	 */
	public static function handle_enable() {
		self::verify_post( 'tdwp_eil_enable' );
		if ( ! get_option( self::REVIEW_FLAG, false ) ) {
			self::redirect_notice( 'error', __( 'Run a clean reconcile before enabling the cutover.', 'poker-tournament-import' ) );
		}
		update_option( 'tdwp_eil_rollup_enabled', 1 );
		self::redirect_notice( 'success', __( 'Cutover enabled — the rollup now owns the live projection.', 'poker-tournament-import' ) );
	}

	/**
	 * Disable the rollup cutover (bridge resumes).
	 *
	 * @return void
	 */
	public static function handle_disable() {
		self::verify_post( 'tdwp_eil_disable' );
		update_option( 'tdwp_eil_rollup_enabled', 0 );
		self::redirect_notice( 'success', __( 'Cutover disabled — the stats bridge has resumed.', 'poker-tournament-import' ) );
	}

	/**
	 * Roll back: disable + delete imported canonical rows (live rows untouched).
	 *
	 * @return void
	 */
	public static function handle_rollback() {
		self::verify_post( 'tdwp_eil_rollback' );
		global $wpdb;
		update_option( 'tdwp_eil_rollup_enabled', 0 );
		$src = $wpdb->prefix . 'tdwp_tournament_players';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollback removes only import rows.
		$removed = $wpdb->query( "DELETE FROM {$src} WHERE source = 'import'" );
		delete_option( self::REVIEW_FLAG );
		self::redirect_notice( 'success', sprintf( __( 'Rolled back: cutover disabled, %d imported rows removed.', 'poker-tournament-import' ), (int) $removed ) );
	}

	/**
	 * Verify an admin-post nonce + capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function verify_post( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'poker-tournament-import' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to the page with a transient admin notice.
	 *
	 * @param string $type    'success'|'error'.
	 * @param string $message Message.
	 * @return void
	 */
	private static function redirect_notice( $type, $message ) {
		set_transient( 'tdwp_eil_notice', array( 'type' => $type, 'message' => $message ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
