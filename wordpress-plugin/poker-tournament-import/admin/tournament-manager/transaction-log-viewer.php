<?php
/**
 * Transaction Log Viewer Component
 *
 * Displays tournament transaction log with filtering, search, and export capabilities
 *
 * @package Poker_Tournament_Import
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get tournament ID from URL parameter or function argument
$tournament_id = isset( $_GET['tournament_id'] ) ? intval( $_GET['tournament_id'] ) : 0;

if ( ! $tournament_id ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Tournament ID not specified', 'poker-tournament-import' ) . '</p></div>';
	return;
}

// Get tournament title
$tournament = get_post( $tournament_id );
if ( ! $tournament ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Tournament not found', 'poker-tournament-import' ) . '</p></div>';
	return;
}

// Get transaction log (using the existing backend method)
$transactions = TDWP_Transaction_Logger::get_tournament_transactions( $tournament_id, array() );
$transaction_count = count( $transactions );
?>

<div class="transaction-log-viewer">
	<div class="log-viewer-header">
		<h2><?php esc_html_e( 'Transaction Log', 'poker-tournament-import' ); ?></h2>
		<div class="log-viewer-info">
			<span class="tournament-title"><?php echo esc_html( $tournament->post_title ); ?></span>
			<span class="transaction-count"><?php echo esc_html( sprintf( __( '%d transactions', 'poker-tournament-import' ), $transaction_count ) ); ?></span>
		</div>
	</div>

	<div class="log-viewer-controls">
		<div class="control-group">
			<label for="transaction-filter"><?php esc_html_e( 'Filter by Type:', 'poker-tournament-import' ); ?></label>
			<select id="transaction-filter" class="regular-text">
				<option value=""><?php esc_html_e( 'All Transactions', 'poker-tournament-import' ); ?></option>
				<option value="bustout"><?php esc_html_e( 'Bust-outs', 'poker-tournament-import' ); ?></option>
				<option value="rebuy"><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></option>
				<option value="addon"><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></option>
				<option value="chip_adjustment"><?php esc_html_e( 'Chip Adjustments', 'poker-tournament-import' ); ?></option>
			</select>
		</div>

		<div class="control-group">
			<label for="transaction-search"><?php esc_html_e( 'Search:', 'poker-tournament-import' ); ?></label>
			<input type="text" id="transaction-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search by player name...', 'poker-tournament-import' ); ?>">
		</div>

		<div class="control-group">
			<button type="button" id="btn-refresh-transaction-log" class="button">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'poker-tournament-import' ); ?>
			</button>
			<button type="button" id="btn-export-transactions" class="button button-secondary">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'poker-tournament-import' ); ?>
			</button>
		</div>
	</div>

	<div class="log-viewer-content">
		<?php if ( $transaction_count > 0 ) : ?>
			<div class="transaction-summary">
				<div class="summary-item">
					<span class="summary-label"><?php esc_html_e( 'Total Buy-ins:', 'poker-tournament-import' ); ?></span>
					<span class="summary-value" id="summary-buyins">$0.00</span>
				</div>
				<div class="summary-item">
					<span class="summary-label"><?php esc_html_e( 'Total Rebuys:', 'poker-tournament-import' ); ?></span>
					<span class="summary-value" id="summary-rebuys">$0.00</span>
				</div>
				<div class="summary-item">
					<span class="summary-label"><?php esc_html_e( 'Total Add-ons:', 'poker-tournament-import' ); ?></span>
					<span class="summary-value" id="summary-addons">$0.00</span>
				</div>
				<div class="summary-item">
					<span class="summary-label"><?php esc_html_e( 'Prize Pool:', 'poker-tournament-import' ); ?></span>
					<span class="summary-value" id="summary-prize-pool">$0.00</span>
				</div>
			</div>

			<div class="transaction-table-wrapper">
				<table class="wp-list-table widefat fixed striped transaction-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Type', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Chips', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Actor', 'poker-tournament-import' ); ?></th>
						</tr>
					</thead>
					<tbody id="transaction-list">
						<?php
						foreach ( $transactions as $transaction ) :
							$player_post = get_post( $transaction->player_id );
							$actor_user = get_user_by( 'id', $transaction->actor_user_id );
							$player_name = $player_post ? $player_post->post_title : __( 'Unknown Player', 'poker-tournament-import' );
							$actor_name = $actor_user ? $actor_user->display_name : __( 'System', 'poker-tournament-import' );

							// Format transaction type for display
							$type_display = '';
							switch ( $transaction->transaction_type ) {
								case 'bustout':
									$type_display = __( 'Bust-out', 'poker-tournament-import' );
									break;
								case 'rebuy':
									$type_display = __( 'Rebuy', 'poker-tournament-import' );
									break;
								case 'addon':
									$type_display = __( 'Add-on', 'poker-tournament-import' );
									break;
								case 'chip_adjustment':
									$type_display = __( 'Chip Adjustment', 'poker-tournament-import' );
									break;
								case 'buyin':
									$type_display = __( 'Buy-in', 'poker-tournament-import' );
									break;
								default:
									$type_display = ucfirst( $transaction->transaction_type );
							}
							?>
							<tr class="transaction-row" data-type="<?php echo esc_attr( $transaction->transaction_type ); ?>" data-player="<?php echo esc_attr( $player_name ); ?>">
								<td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $transaction->created_at ) ) ); ?></td>
								<td>
									<span class="transaction-type-badge type-<?php echo esc_attr( $transaction->transaction_type ); ?>">
										<?php echo esc_html( $type_display ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $player_name ); ?></td>
								<td>
									<?php if ( $transaction->amount > 0 ) : ?>
										$<?php echo esc_html( number_format( $transaction->amount, 2 ) ); ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $transaction->chips > 0 ) : ?>
										<?php echo esc_html( number_format( $transaction->chips ) ); ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $transaction->reason ) ) : ?>
										<span class="transaction-reason" title="<?php echo esc_attr( $transaction->reason ); ?>">
											<?php echo esc_html( $transaction->reason ); ?>
										</span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $actor_name ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="no-transactions">
				<div class="no-transactions-icon">
					<span class="dashicons dashicons-media-text" style="font-size: 48px; color: #ccc;"></span>
				</div>
				<h3><?php esc_html_e( 'No transactions recorded yet', 'poker-tournament-import' ); ?></h3>
				<p><?php esc_html_e( 'Transactions will appear here as players buy in, rebuy, add-on, or get eliminated.', 'poker-tournament-import' ); ?></p>
				<button type="button" id="btn-refresh-no-transactions" class="button">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Refresh', 'poker-tournament-import' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.transaction-log-viewer {
	margin: 20px 0;
}

.log-viewer-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	padding-bottom: 15px;
	border-bottom: 1px solid #ddd;
}

.log-viewer-info {
	display: flex;
	gap: 15px;
	align-items: center;
}

.tournament-title {
	font-weight: 600;
	color: #333;
}

.transaction-count {
	background: #2271b1;
	color: white;
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 12px;
}

.log-viewer-controls {
	display: flex;
	gap: 20px;
	margin-bottom: 20px;
	padding: 15px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.control-group {
	display: flex;
	flex-direction: column;
	gap: 5px;
}

.control-group label {
	font-weight: 600;
	font-size: 13px;
}

.transaction-summary {
	display: flex;
	gap: 30px;
	margin-bottom: 20px;
	padding: 15px;
	background: #f0f6fc;
	border: 1px solid #c3d4ec;
	border-radius: 4px;
}

.summary-item {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.summary-label {
	font-size: 12px;
	color: #666;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.summary-value {
	font-size: 18px;
	font-weight: 600;
	color: #2271b1;
}

.transaction-table-wrapper {
	overflow-x: auto;
}

.transaction-type-badge {
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.type-bustout, .type-bust_out {
	background: #d63638;
	color: white;
}

.type-rebuy {
	background: #00a32a;
	color: white;
}

.type-addon {
	background: #3858e9;
	color: white;
}

.type-chip_adjustment {
	background: #f0b849;
	color: #333;
}

.type-buyin {
	background: #2271b1;
	color: white;
}

.transaction-reason {
	max-width: 150px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	display: block;
}

.no-transactions {
	text-align: center;
	padding: 60px 20px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.no-transactions-icon {
	margin-bottom: 20px;
}

.no-transactions h3 {
	margin: 0 0 10px 0;
	color: #333;
}

.no-transactions p {
	margin: 0 0 20px 0;
	color: #666;
}

@media (max-width: 768px) {
	.log-viewer-header {
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
	}

	.log-viewer-controls {
		flex-direction: column;
		gap: 15px;
	}

	.transaction-summary {
		flex-direction: column;
		gap: 15px;
		align-items: flex-start;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Auto-refresh transaction log every 30 seconds
	let refreshInterval;

	function refreshTransactionLog() {
		const tournamentId = <?php echo $tournament_id; ?>;
		const filter = $('#transaction-filter').val();
		const search = $('#transaction-search').val();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'tdwp_tm_get_transaction_log',
				nonce: tdwpTournamentControl.nonce,
				tournament_id: tournamentId,
				filter: filter,
				search: search
			},
			success: function(response) {
				if (response.success) {
					updateTransactionTable(response.data.transactions);
					updateTransactionSummary(response.data.summary);
				}
			}
		});
	}

	function updateTransactionTable(transactions) {
		const $tbody = $('#transaction-list');
		$tbody.empty();

		if (transactions.length === 0) {
			$tbody.append('<tr><td colspan="7" class="no-results">' +
				'<?php esc_html_e( 'No transactions match current filters', 'poker-tournament-import' ); ?>' +
				'</td></tr>');
			return;
		}

		transactions.forEach(function(transaction) {
			const row = createTransactionRow(transaction);
			$tbody.append(row);
		});
	}

	function createTransactionRow(transaction) {
		const typeDisplay = getTransactionTypeDisplay(transaction.transaction_type);
		const amount = transaction.amount > 0 ? '$' + parseFloat(transaction.amount).toFixed(2) : '—';
		const chips = transaction.chips > 0 ? parseInt(transaction.chips).toLocaleString() : '—';
		const reason = transaction.reason || '—';

		return '<tr class="transaction-row" data-type="' + transaction.transaction_type + '" data-player="' + transaction.player_name + '">' +
			'<td>' + transaction.created_at + '</td>' +
			'<td><span class="transaction-type-badge type-' + transaction.transaction_type + '">' + typeDisplay + '</span></td>' +
			'<td>' + transaction.player_name + '</td>' +
			'<td>' + amount + '</td>' +
			'<td>' + chips + '</td>' +
			'<td><span class="transaction-reason" title="' + reason + '">' + reason + '</span></td>' +
			'<td>' + transaction.actor_name + '</td>' +
			'</tr>';
	}

	function getTransactionTypeDisplay(type) {
		const types = {
			'bust_out': '<?php esc_html_e( 'Bust-out', 'poker-tournament-import' ); ?>',
			'bustout': '<?php esc_html_e( 'Bust-out', 'poker-tournament-import' ); ?>',
			'rebuy': '<?php esc_html_e( 'Rebuy', 'poker-tournament-import' ); ?>',
			'addon': '<?php esc_html_e( 'Add-on', 'poker-tournament-import' ); ?>',
			'chip_adjustment': '<?php esc_html_e( 'Chip Adjustment', 'poker-tournament-import' ); ?>',
			'buyin': '<?php esc_html_e( 'Buy-in', 'poker-tournament-import' ); ?>'
		};
		return types[type] || type.charAt(0).toUpperCase() + type.slice(1);
	}

	function updateTransactionSummary(summary) {
		$('#summary-buyins').text('$' + parseFloat(summary.buyins || 0).toFixed(2));
		$('#summary-rebuys').text('$' + parseFloat(summary.rebuys || 0).toFixed(2));
		$('#summary-addons').text('$' + parseFloat(summary.addons || 0).toFixed(2));
		$('#summary-prize-pool').text('$' + parseFloat(summary.prize_pool || 0).toFixed(2));
	}

	function filterTransactions() {
		const filter = $('#transaction-filter').val();
		const search = $('#transaction-search').val().toLowerCase();

		$('.transaction-row').each(function() {
			const $row = $(this);
			const matchesFilter = !filter || $row.data('type') === filter;
			const matchesSearch = !search || $row.data('player').toLowerCase().includes(search);

			$row.toggle(matchesFilter && matchesSearch);
		});
	}

	// Event handlers
	$('#transaction-filter, #transaction-search').on('input change', filterTransactions);

	$('#btn-refresh-transaction-log, #btn-refresh-no-transactions').on('click', refreshTransactionLog);

	$('#btn-export-transactions').on('click', function() {
		const tournamentId = <?php echo $tournament_id; ?>;
		window.location.href = ajaxurl + '?action=tdwp_tm_export_transactions_csv&nonce=' +
			tdwpTournamentControl.nonce + '&tournament_id=' + tournamentId;
	});

	// Start auto-refresh
	refreshTransactionLog();
	refreshInterval = setInterval(refreshTransactionLog, 30000);

	// Cleanup on page unload
	$(window).on('beforeunload', function() {
		if (refreshInterval) {
			clearInterval(refreshInterval);
		}
	});
});
</script>