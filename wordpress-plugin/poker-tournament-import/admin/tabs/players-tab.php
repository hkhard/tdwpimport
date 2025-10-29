<?php
/**
 * Players Tab Content - Tournament Roster
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get registered players.
$players = TDWP_Tournament_Player_Manager::get_tournament_players( $tournament_id );
$player_count = count( $players );

// Get tournament financial config.
$buy_in      = get_post_meta( $tournament_id, '_buy_in', true );
$rebuy_cost  = get_post_meta( $tournament_id, '_rebuy_cost', true );
$addon_cost  = get_post_meta( $tournament_id, '_addon_cost', true );

// Get all available players for autocomplete.
$all_players = get_posts(
	array(
		'post_type'      => 'player',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>

<div class="players-tab-content">
	<div class="players-header">
		<h2><?php esc_html_e( 'Tournament Roster', 'poker-tournament-import' ); ?></h2>
		<div class="players-actions">
			<button type="button" id="btn-add-player" class="button button-primary">
				<?php esc_html_e( 'Add Player', 'poker-tournament-import' ); ?>
			</button>
			<button type="button" id="btn-process-buyins" class="button button-secondary">
				<?php esc_html_e( 'Process Buy-ins', 'poker-tournament-import' ); ?>
			</button>
			<span class="player-count"><?php echo esc_html( sprintf( __( 'Total Players: %d', 'poker-tournament-import' ), $player_count ) ); ?></span>
		</div>
	</div>

	<!-- Add Player Modal -->
	<div id="add-player-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Add Player to Tournament', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<div class="form-row">
					<label for="player-select"><?php esc_html_e( 'Select Player', 'poker-tournament-import' ); ?></label>
					<select id="player-select" class="regular-text">
						<option value=""><?php esc_html_e( 'Choose a player...', 'poker-tournament-import' ); ?></option>
						<?php foreach ( $all_players as $player ) : ?>
							<option value="<?php echo esc_attr( $player->ID ); ?>">
								<?php echo esc_html( $player->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-row">
					<label>
						<input type="checkbox" id="paid-in-full">
						<?php
						/* translators: %s: buy-in amount */
						echo esc_html( sprintf( __( 'Paid in full (%s)', 'poker-tournament-import' ), $buy_in ? '$' . number_format( (float) $buy_in, 2 ) : '$0.00' ) );
						?>
					</label>
					<p class="description"><?php esc_html_e( 'Check if player has paid the buy-in amount', 'poker-tournament-import' ); ?></p>
					<input type="hidden" id="tournament-buy-in" value="<?php echo esc_attr( $buy_in ); ?>">
				</div>
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-add" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-add" class="button button-primary"><?php esc_html_e( 'Add Player', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Buy-in Wizard Modal -->
	<div id="buyin-wizard-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content" style="max-width: 800px;">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Process Buy-ins', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="description"><?php esc_html_e( 'Mark players as bought in and record rebuys/add-ons. Only registered players are shown.', 'poker-tournament-import' ); ?></p>

				<table class="wp-list-table widefat striped buyin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Buy-in', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></th>
							<th><?php esc_html_e( 'Total', 'poker-tournament-import' ); ?></th>
						</tr>
					</thead>
					<tbody id="buyin-players-list">
						<?php
						$registered_players = TDWP_Tournament_Player_Manager::get_tournament_players( $tournament_id, 'registered' );
						if ( empty( $registered_players ) ) :
							?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'No registered players to process', 'poker-tournament-import' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $registered_players as $player_reg ) : ?>
								<?php
								$player_post = get_post( $player_reg->player_id );
								if ( ! $player_post ) {
									continue;
								}
								?>
								<tr class="buyin-player-row" data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>">
									<td><strong><?php echo esc_html( $player_post->post_title ); ?></strong></td>
									<td>
										<label>
											<input type="checkbox" class="buyin-checkbox" checked>
											$<?php echo esc_html( number_format( (float) $buy_in, 2 ) ); ?>
										</label>
									</td>
									<td>
										<input type="number" class="rebuys-input small-text" min="0" value="0" step="1">
										× $<?php echo esc_html( number_format( (float) $rebuy_cost, 2 ) ); ?>
									</td>
									<td>
										<input type="number" class="addons-input small-text" min="0" value="0" step="1">
										× $<?php echo esc_html( number_format( (float) $addon_cost, 2 ) ); ?>
									</td>
									<td class="total-amount">$0.00</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<input type="hidden" id="buyin-buy-in" value="<?php echo esc_attr( $buy_in ); ?>">
				<input type="hidden" id="buyin-rebuy-cost" value="<?php echo esc_attr( $rebuy_cost ); ?>">
				<input type="hidden" id="buyin-addon-cost" value="<?php echo esc_attr( $addon_cost ); ?>">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-buyin" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-buyin" class="button button-primary"><?php esc_html_e( 'Process Buy-ins', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Players Table -->
	<?php if ( $player_count > 0 ) : ?>
		<table class="wp-list-table widefat fixed striped players-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Paid', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Seat', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $players as $player_reg ) : ?>
					<?php
					$player_post = get_post( $player_reg->player_id );
					if ( ! $player_post ) {
						continue;
					}
					?>
					<tr data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>">
						<td><strong><?php echo esc_html( $player_post->post_title ); ?></strong></td>
						<td>
							<select class="player-status-select" data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>">
								<option value="registered" <?php selected( $player_reg->status, 'registered' ); ?>><?php esc_html_e( 'Registered', 'poker-tournament-import' ); ?></option>
								<option value="paid" <?php selected( $player_reg->status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'poker-tournament-import' ); ?></option>
								<option value="checked_in" <?php selected( $player_reg->status, 'checked_in' ); ?>><?php esc_html_e( 'Checked In', 'poker-tournament-import' ); ?></option>
								<option value="active" <?php selected( $player_reg->status, 'active' ); ?>><?php esc_html_e( 'Active', 'poker-tournament-import' ); ?></option>
								<option value="eliminated" <?php selected( $player_reg->status, 'eliminated' ); ?>><?php esc_html_e( 'Eliminated', 'poker-tournament-import' ); ?></option>
							</select>
						</td>
						<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $player_reg->registration_date ) ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $player_reg->paid_amount, 2 ) ); ?></td>
						<td><?php echo esc_html( $player_reg->rebuys_count ); ?></td>
						<td><?php echo esc_html( $player_reg->addons_count ); ?></td>
						<td><?php echo $player_reg->seat_assignment ? esc_html( $player_reg->seat_assignment ) : '—'; ?></td>
						<td>
							<button type="button" class="button button-small btn-remove-player" data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>">
								<?php esc_html_e( 'Remove', 'poker-tournament-import' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="no-players-message">
			<p><?php esc_html_e( 'No players registered yet. Click "Add Player" to start.', 'poker-tournament-import' ); ?></p>
		</div>
	<?php endif; ?>
</div>
