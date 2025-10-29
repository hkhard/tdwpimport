<?php
/**
 * Tables Tab Content
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );
$unseated_players = TDWP_Seat_Manager::get_unseated_players( $tournament_id );
$balance_status = TDWP_Table_Balancer::get_balance_status( $tournament_id );
?>

<div class="tables-tab-content">
	<!-- Top Toolbar -->
	<div class="tables-toolbar">
		<div class="toolbar-left">
			<button id="btn-add-table" class="button button-primary"><?php esc_html_e( 'Add Table', 'poker-tournament-import' ); ?></button>
			<button id="btn-auto-seat" class="button button-secondary"><?php esc_html_e( 'Auto-Seat Players', 'poker-tournament-import' ); ?></button>
			<button id="btn-auto-balance" class="button button-secondary"><?php esc_html_e( 'Auto-Balance', 'poker-tournament-import' ); ?></button>

			<select id="select-break-table">
				<option value=""><?php esc_html_e( 'Break Table...', 'poker-tournament-import' ); ?></option>
				<?php foreach ( $tables as $table ) : ?>
					<option value="<?php echo esc_attr( $table->id ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: table number, 2: player count */
								__( 'Table %1$d (%2$d players)', 'poker-tournament-import' ),
								$table->table_number,
								$table->player_count
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="toolbar-right status-summary">
			<span class="status-item"><?php echo esc_html( count( $tables ) ); ?> <?php esc_html_e( 'tables', 'poker-tournament-import' ); ?></span>
			<span class="status-item"><?php echo esc_html( TDWP_Table_Manager::get_seated_player_count( $tournament_id ) ); ?> <?php esc_html_e( 'seated', 'poker-tournament-import' ); ?></span>
			<span class="status-item balance-status-<?php echo esc_attr( $balance_status ); ?>">
				<?php
				if ( 'balanced' === $balance_status ) {
					esc_html_e( 'Balanced', 'poker-tournament-import' );
				} else {
					esc_html_e( 'Unbalanced', 'poker-tournament-import' );
				}
				?>
			</span>
		</div>
	</div>

	<!-- Main Content Area -->
	<div class="tables-main-content">
		<!-- Tables Grid -->
		<div class="tables-grid" id="tables-grid">
			<?php if ( empty( $tables ) ) : ?>
				<div class="no-tables-message">
					<p><?php esc_html_e( 'No tables yet. Click "Add Table" to create your first table.', 'poker-tournament-import' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $tables as $table ) : ?>
					<div class="table-card" data-table-id="<?php echo esc_attr( $table->id ); ?>" data-table-number="<?php echo esc_attr( $table->table_number ); ?>">
						<div class="table-header">
							<h3><?php echo esc_html( sprintf( __( 'Table %d', 'poker-tournament-import' ), $table->table_number ) ); ?></h3>
							<span class="player-count"><?php echo esc_html( $table->player_count ); ?> / <?php echo esc_html( $table->max_seats ); ?></span>
							<button class="button-link remove-table" data-table-id="<?php echo esc_attr( $table->id ); ?>">×</button>
						</div>

						<div class="seats-container" data-max-seats="<?php echo esc_attr( $table->max_seats ); ?>">
							<?php foreach ( $table->seats as $seat ) : ?>
								<div class="seat-item seat-position-<?php echo esc_attr( $seat->seat_number ); ?> <?php echo $seat->player_id ? 'occupied' : 'empty'; ?> droppable-seat"
									data-table-id="<?php echo esc_attr( $table->id ); ?>"
									data-seat-number="<?php echo esc_attr( $seat->seat_number ); ?>">

									<?php if ( $seat->player_id ) : ?>
										<div class="player-chip draggable-player"
											data-player-id="<?php echo esc_attr( $seat->player_id ); ?>"
											data-table-id="<?php echo esc_attr( $table->id ); ?>"
											data-seat-number="<?php echo esc_attr( $seat->seat_number ); ?>">

											<span class="seat-num"><?php echo esc_html( $seat->seat_number ); ?></span>
											<span class="player-name"><?php echo esc_html( $seat->player ? $seat->player->post_title : 'Unknown' ); ?></span>
											<button class="unseat-btn" data-player-id="<?php echo esc_attr( $seat->player_id ); ?>">×</button>
										</div>
									<?php else : ?>
										<span class="seat-num"><?php echo esc_html( $seat->seat_number ); ?></span>
										<span class="empty-label"><?php esc_html_e( 'Empty', 'poker-tournament-import' ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- Unseated Players Sidebar -->
		<div class="unseated-players-panel">
			<h3><?php esc_html_e( 'Unseated Players', 'poker-tournament-import' ); ?></h3>
			<div id="unseated-players-list">
				<?php if ( empty( $unseated_players ) ) : ?>
					<p class="no-players"><?php esc_html_e( 'No unseated players', 'poker-tournament-import' ); ?></p>
				<?php else : ?>
					<?php foreach ( $unseated_players as $player ) : ?>
						<div class="player-chip draggable-player unseated"
							data-player-id="<?php echo esc_attr( $player->ID ); ?>">
							<span class="player-name"><?php echo esc_html( $player->post_title ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<!-- Add Table Modal -->
<div id="add-table-modal" class="tdwp-modal" style="display:none;">
	<div class="modal-content">
		<span class="modal-close">×</span>
		<h2><?php esc_html_e( 'Add New Table', 'poker-tournament-import' ); ?></h2>
		<p>
			<label><?php esc_html_e( 'Max Seats:', 'poker-tournament-import' ); ?></label>
			<select id="new-table-max-seats">
				<option value="2">2-max (Heads-up)</option>
				<option value="3">3-max</option>
				<option value="4">4-max</option>
				<option value="5">5-max</option>
				<option value="6">6-max</option>
				<option value="7">7-max</option>
				<option value="8">8-max</option>
				<option value="9" selected>9-max</option>
				<option value="10">10-max</option>
			</select>
		</p>
		<button id="confirm-add-table" class="button button-primary"><?php esc_html_e( 'Add Table', 'poker-tournament-import' ); ?></button>
	</div>
</div>

<!-- Balance Preview Modal -->
<div id="balance-preview-modal" class="tdwp-modal" style="display:none;">
	<div class="modal-content">
		<span class="modal-close">×</span>
		<h2><?php esc_html_e( 'Balance Preview', 'poker-tournament-import' ); ?></h2>
		<div id="balance-moves-list"></div>
		<button id="execute-balance" class="button button-primary"><?php esc_html_e( 'Execute Balance', 'poker-tournament-import' ); ?></button>
		<button class="button modal-close"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
	</div>
</div>
