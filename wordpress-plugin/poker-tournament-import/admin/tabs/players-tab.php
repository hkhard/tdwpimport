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

// Get financial policy for conditional UI.
$allow_reentry       = (int) get_post_meta( $tournament_id, '_allow_reentry', true );
$reentry_until_level = (int) get_post_meta( $tournament_id, '_reentry_until_level', true );
$bounty_type         = get_post_meta( $tournament_id, '_bounty_type', true );
$show_bounty_column  = ( 'none' !== $bounty_type && ! empty( $bounty_type ) );

// Get rebuy/add-on policy for conditional UI.
$rebuy_cost           = (float) get_post_meta( $tournament_id, '_rebuy_cost', true );
$rebuy_chips          = (int) get_post_meta( $tournament_id, '_rebuy_chips', true );
$rebuy_until_level    = (int) get_post_meta( $tournament_id, '_rebuy_until_level', true );
$rebuy_limit_per_player = (int) get_post_meta( $tournament_id, '_rebuy_limit_per_player', true );
$addon_cost           = (float) get_post_meta( $tournament_id, '_addon_cost', true );
$addon_chips          = (int) get_post_meta( $tournament_id, '_addon_chips', true );
$addon_at_level       = (int) get_post_meta( $tournament_id, '_addon_at_level', true );
$addon_until_level    = (int) get_post_meta( $tournament_id, '_addon_until_level', true );

// Get current tournament state for level-based checks.
$state         = TDWP_Live_State_Manager::get_state( $tournament_id );
$current_level = $state ? $state->current_level : 1;

/**
 * Helper function to check if player can rebuy
 *
 * @param object $entry Player entry object
 * @return bool True if player can rebuy
 */
function tdwp_can_player_rebuy( $entry ) {
	global $rebuy_cost, $rebuy_chips, $rebuy_until_level, $rebuy_limit_per_player, $current_level;

	// Check if rebuys allowed
	if ( $rebuy_cost <= 0 || $rebuy_chips <= 0 || $rebuy_until_level <= 0 ) {
		return false;
	}

	// Check if player is active
	if ( ! in_array( $entry->status, array( 'active', 'paid', 'checked_in' ), true ) ) {
		return false;
	}

	// Check level restrictions
	if ( $current_level > $rebuy_until_level ) {
		return false;
	}

	// Check rebuy limit
	if ( $rebuy_limit_per_player > 0 && $entry->rebuys_count >= $rebuy_limit_per_player ) {
		return false;
	}

	return true;
}

/**
 * Helper function to check if player can add-on
 *
 * @param object $entry Player entry object
 * @return bool True if player can add-on
 */
function tdwp_can_player_addon( $entry ) {
	global $addon_cost, $addon_chips, $addon_at_level, $addon_until_level, $current_level;

	// Check if addons allowed
	if ( $addon_cost <= 0 || $addon_chips <= 0 || $addon_at_level <= 0 ) {
		return false;
	}

	// Check if player is active
	if ( ! in_array( $entry->status, array( 'active', 'paid', 'checked_in' ), true ) ) {
		return false;
	}

	// Check level restrictions
	if ( $current_level < $addon_at_level ) {
		return false;
	}

	if ( $addon_until_level > 0 && $current_level > $addon_until_level ) {
		return false;
	}

	// Check if player has already taken add-on
	if ( $entry->addons_count >= 1 ) {
		return false;
	}

	return true;
}

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

	<!-- Bust-out Inline Expansion Template (hidden, cloned by JS) -->
	<table class="tdwp-bustout-template-table" style="display:none;">
		<tbody>
			<tr id="bustout-inline-template" class="tdwp-bustout-inline-template">
				<td colspan="<?php echo $show_bounty_column ? 12 : 11; ?>" class="tdwp-bustout-inline-cell">
			<div class="tdwp-bustout-inline-container">
				<div class="tdwp-bustout-inline-header">
					<h4><?php esc_html_e( 'Bust Out Player', 'poker-tournament-import' ); ?></h4>
					<button type="button" class="tdwp-bustout-inline-close" title="<?php esc_attr_e( 'Close', 'poker-tournament-import' ); ?>">&times;</button>
				</div>

				<div class="tdwp-bustout-inline-body">
					<p class="bustout-player-name">
						<strong><?php esc_html_e( 'Eliminating:', 'poker-tournament-import' ); ?></strong> <span class="bustout-player-name-display"></span>
					</p>

					<!-- Confirmation Checkbox (Required) -->
					<div class="bustout-confirmation">
						<label>
							<input type="checkbox" class="confirm-bustout-checkbox">
							<strong><?php esc_html_e( 'Confirm: Bust out ', 'poker-tournament-import' ); ?><span class="bustout-confirm-name-display"></span></strong>
						</label>
					</div>

					<!-- Eliminator Checkboxes -->
					<div class="eliminator-section">
						<label class="eliminator-label"><?php esc_html_e( 'Select Eliminator(s):', 'poker-tournament-import' ); ?></label>
						<p class="description"><?php esc_html_e( 'Select all players who contributed to this elimination. Leave unchecked for natural bust-outs.', 'poker-tournament-import' ); ?></p>
						<div class="eliminator-checkboxes-container">
							<!-- Dynamically populated via JavaScript -->
						</div>
					</div>

					<?php if ( $show_bounty_column ) : ?>
						<div class="bounty-preview" style="display:none;">
							<p>
								<strong><?php esc_html_e( 'Bounty Split:', 'poker-tournament-import' ); ?></strong>
								<span class="bounty-earned-preview">$0.00 each (0 players)</span>
							</p>
						</div>
					<?php endif; ?>

					<!-- Hidden Fields -->
					<input type="hidden" class="bustout-registration-id">
					<input type="hidden" class="bustout-player-id">
					<input type="hidden" class="bustout-entry-number">
					<input type="hidden" class="bustout-player-bounty">
				</div>

				<div class="tdwp-bustout-inline-footer">
					<button type="button" class="btn-cancel-bustout button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
					<button type="button" class="btn-confirm-bustout button button-primary"><?php esc_html_e( 'Confirm Bust Out', 'poker-tournament-import' ); ?></button>
				</div>
			</div>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Update Chips Modal -->
	<div id="update-chips-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Update Chip Count', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="chip-player-name" style="font-size: 16px; margin-bottom: 15px;">
					<strong><?php esc_html_e( 'Player:', 'poker-tournament-import' ); ?></strong> <span id="chip-player-name"></span>
				</p>
				<div class="form-row">
					<label for="new-chip-count"><?php esc_html_e( 'New Chip Count', 'poker-tournament-import' ); ?></label>
					<input type="number" id="new-chip-count" class="regular-text" min="0" step="100" value="0">
					<p class="description"><?php esc_html_e( 'Enter the updated chip count for this player.', 'poker-tournament-import' ); ?></p>
				</div>
				<input type="hidden" id="chip-registration-id">
				<input type="hidden" id="chip-player-id">
				<input type="hidden" id="chip-entry-number">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-chips" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-chips" class="button button-primary"><?php esc_html_e( 'Update Chips', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Rebuy Modal -->
	<div id="rebuy-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Process Rebuy', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="rebuy-player-name" style="font-size: 16px; margin-bottom: 15px;">
					<strong><?php esc_html_e( 'Player:', 'poker-tournament-import' ); ?></strong> <span id="rebuy-player-name"></span>
				</p>

				<div class="rebuy-details">
					<div class="form-row">
						<strong><?php esc_html_e( 'Rebuy Cost:', 'poker-tournament-import' ); ?></strong>
						<span id="rebuy-cost-display">$<?php echo esc_html( number_format( $rebuy_cost, 2 ) ); ?></span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Chips Received:', 'poker-tournament-import' ); ?></strong>
						<span id="rebuy-chips-display"><?php echo esc_html( number_format( $rebuy_chips ) ); ?></span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Rebuys:', 'poker-tournament-import' ); ?></strong>
						<span id="rebuy-current-count">0</span>
						<?php if ( $rebuy_limit_per_player > 0 ) : ?>
							/<?php echo esc_html( $rebuy_limit_per_player ); ?>
						<?php endif; ?>
					</div>
					<?php if ( $rebuy_limit_per_player > 0 ) : ?>
						<div class="form-row">
							<strong><?php esc_html_e( 'Rebuy Limit:', 'poker-tournament-import' ); ?></strong>
							<?php echo esc_html( $rebuy_limit_per_player ); ?> <?php esc_html_e( 'per player', 'poker-tournament-import' ); ?>
						</div>
					<?php endif; ?>
					<div class="form-row">
						<strong><?php esc_html_e( 'Rebuy Period:', 'poker-tournament-import' ); ?></strong>
						<?php
						if ( $rebuy_until_level <= 0 ) {
							esc_html_e( 'Not allowed', 'poker-tournament-import' );
						} elseif ( $rebuy_until_level == 999 ) {
							esc_html_e( 'Until end of tournament', 'poker-tournament-import' );
						} else {
							/* translators: %d: level number */
							printf( esc_html__( 'Until level %d', 'poker-tournament-import' ), esc_html( $rebuy_until_level ) );
						}
						?>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Level:', 'poker-tournament-import' ); ?></strong>
						<?php echo esc_html( $current_level ); ?>
					</div>
				</div>

				<?php if ( $rebuy_limit_per_player > 0 ) : ?>
					<div class="form-row">
						<label>
							<input type="checkbox" id="confirm-rebuy-limit">
							<?php
							/* translators: %s: rebuy limit */
							printf( esc_html__( 'Confirm player has not reached rebuy limit (%s)', 'poker-tournament-import' ), esc_html( $rebuy_limit_per_player ) );
							?>
						</label>
					</div>
				<?php endif; ?>

				<div class="form-row">
					<label>
						<input type="checkbox" id="confirm-rebuy-payment">
						<?php
						/* translators: %s: rebuy cost */
						printf( esc_html__( 'Confirm player has paid %s for this rebuy', 'poker-tournament-import' ), '$' . esc_html( number_format( $rebuy_cost, 2 ) ) );
						?>
					</label>
				</div>

				<input type="hidden" id="rebuy-registration-id">
				<input type="hidden" id="rebuy-player-id">
				<input type="hidden" id="rebuy-entry-number">
				<input type="hidden" id="rebuy-current-chips">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-rebuy" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-rebuy" class="button button-primary"><?php esc_html_e( 'Process Rebuy', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Add-on Modal -->
	<div id="addon-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Process Add-on', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="addon-player-name" style="font-size: 16px; margin-bottom: 15px;">
					<strong><?php esc_html_e( 'Player:', 'poker-tournament-import' ); ?></strong> <span id="addon-player-name"></span>
				</p>

				<div class="addon-details">
					<div class="form-row">
						<strong><?php esc_html_e( 'Add-on Cost:', 'poker-tournament-import' ); ?></strong>
						<span id="addon-cost-display">$<?php echo esc_html( number_format( $addon_cost, 2 ) ); ?></span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Chips Received:', 'poker-tournament-import' ); ?></strong>
						<span id="addon-chips-display"><?php echo esc_html( number_format( $addon_chips ) ); ?></span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Add-ons:', 'poker-tournament-import' ); ?></strong>
						<span id="addon-current-count">0</span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Add-on Available:', 'poker-tournament-import' ); ?></strong>
						<?php
						if ( $addon_at_level <= 0 ) {
							esc_html_e( 'Not available', 'poker-tournament-import' );
						} else {
							if ( $addon_until_level > 0 ) {
								/* translators: 1: level number, 2: level number */
								printf( esc_html__( 'Levels %d to %d', 'poker-tournament-import' ), esc_html( $addon_at_level ), esc_html( $addon_until_level ) );
							} else {
								/* translators: %d: level number */
								printf( esc_html__( 'Level %d and after', 'poker-tournament-import' ), esc_html( $addon_at_level ) );
							}
						}
						?>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Level:', 'poker-tournament-import' ); ?></strong>
						<?php echo esc_html( $current_level ); ?>
					</div>
				</div>

				<div class="form-row">
					<label>
						<input type="checkbox" id="confirm-addon-payment">
						<?php
						/* translators: %s: addon cost */
						printf( esc_html__( 'Confirm player has paid %s for this add-on', 'poker-tournament-import' ), '$' . esc_html( number_format( $addon_cost, 2 ) ) );
						?>
					</label>
				</div>

				<input type="hidden" id="addon-registration-id">
				<input type="hidden" id="addon-player-id">
				<input type="hidden" id="addon-entry-number">
				<input type="hidden" id="addon-current-chips">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-addon" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-addon" class="button button-primary"><?php esc_html_e( 'Process Add-on', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Withdrawal Modal (Phase 3) -->
	<div id="withdrawal-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Player Withdrawal', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="withdrawal-player-name" style="font-size: 16px; margin-bottom: 15px;">
					<strong><?php esc_html_e( 'Player:', 'poker-tournament-import' ); ?></strong> <span id="withdrawal-player-name"></span>
				</p>

				<div class="withdrawal-details">
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Chips:', 'poker-tournament-import' ); ?></strong>
						<span id="withdrawal-current-chips">0</span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Entry Number:', 'poker-tournament-import' ); ?></strong>
						<span id="withdrawal-entry-number">1</span>
					</div>
				</div>

				<div class="form-row">
					<label for="withdrawal-reason"><?php esc_html_e( 'Withdrawal Reason (Optional):', 'poker-tournament-import' ); ?></label>
					<textarea id="withdrawal-reason" class="large-text" rows="3" placeholder="<?php esc_html_e( 'Enter reason for withdrawal (optional)...', 'poker-tournament-import' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Optional: Explain why this player is withdrawing from the tournament.', 'poker-tournament-import' ); ?></p>
				</div>

				<div class="form-row">
					<label>
						<input type="checkbox" id="confirm-withdrawal">
						<?php esc_html_e( 'Confirm player withdrawal from tournament', 'poker-tournament-import' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'This action will remove the player from the tournament and mark them as withdrawn.', 'poker-tournament-import' ); ?></p>
				</div>

				<!-- Hidden Fields -->
				<input type="hidden" id="withdrawal-registration-id">
				<input type="hidden" id="withdrawal-player-id">
				<input type="hidden" id="withdrawal-tournament-id" value="<?php echo esc_attr( $tournament_id ); ?>">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-withdrawal" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-withdrawal" class="button button-primary"><?php esc_html_e( 'Withdraw Player', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Re-entry Modal (Phase 3) -->
	<div id="reentry-modal" class="tdwp-modal" style="display:none;">
		<div class="tdwp-modal-content">
			<div class="tdwp-modal-header">
				<h3><?php esc_html_e( 'Player Re-entry Options', 'poker-tournament-import' ); ?></h3>
				<span class="tdwp-modal-close">&times;</span>
			</div>
			<div class="tdwp-modal-body">
				<p class="reentry-player-name" style="font-size: 16px; margin-bottom: 15px;">
					<strong><?php esc_html_e( 'Player:', 'poker-tournament-import' ); ?></strong> <span id="reentry-modal-player-name"></span>
				</p>

				<div class="reentry-details">
					<div class="form-row">
						<strong><?php esc_html_e( 'Re-entry Cost:', 'poker-tournament-import' ); ?></strong>
						<span id="reentry-modal-cost">$0.00</span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Starting Chips:', 'poker-tournament-import' ); ?></strong>
						<span id="reentry-modal-chips">0</span>
					</div>
					<div class="form-row">
						<strong><?php esc_html_e( 'Current Level:', 'poker-tournament-import' ); ?></strong>
						<?php echo esc_html( $current_level ); ?>
					</div>
					<?php if ( $reentry_until_level > 0 ) : ?>
					<div class="form-row">
						<strong><?php esc_html_e( 'Re-entry Available Until Level:', 'poker-tournament-import' ); ?></strong>
						<?php echo esc_html( $reentry_until_level ); ?>
					</div>
					<?php endif; ?>
				</div>

				<div class="reentry-options">
					<h4><?php esc_html_e( 'Choose an option:', 'poker-tournament-import' ); ?></h4>

					<div class="form-row">
						<label>
							<input type="radio" name="reentry_option" value="reentry" checked>
							<strong><?php esc_html_e( 'Re-enter Tournament', 'poker-tournament-import' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Player will pay the re-entry fee and receive a new stack of chips to continue playing.', 'poker-tournament-import' ); ?>
						</p>
					</div>

					<div class="form-row">
						<label>
							<input type="radio" name="reentry_option" value="decline_withdraw">
							<strong><?php esc_html_e( 'Decline Re-entry and Withdraw', 'poker-tournament-import' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Player will decline re-entry and withdraw from the tournament. This action cannot be undone.', 'poker-tournament-import' ); ?>
						</p>
					</div>
				</div>

				<div class="form-row" id="withdrawal-reason-field" style="display:none;">
					<label for="decline-withdrawal-reason"><?php esc_html_e( 'Withdrawal Reason (Optional):', 'poker-tournament-import' ); ?></label>
					<textarea id="decline-withdrawal-reason" class="large-text" rows="3" placeholder="<?php esc_html_e( 'Enter reason for declining re-entry (optional)...', 'poker-tournament-import' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Optional: Explain why the player is declining re-entry.', 'poker-tournament-import' ); ?></p>
				</div>

				<div class="form-row">
					<label>
						<input type="checkbox" id="confirm-reentry-action">
						<?php esc_html_e( 'Confirm selected action', 'poker-tournament-import' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Please confirm your choice above before proceeding.', 'poker-tournament-import' ); ?></p>
				</div>

				<!-- Hidden Fields -->
				<input type="hidden" id="reentry-modal-registration-id">
				<input type="hidden" id="reentry-modal-player-id">
				<input type="hidden" id="reentry-modal-tournament-id" value="<?php echo esc_attr( $tournament_id ); ?>">
				<input type="hidden" id="reentry-modal-cost-amount" value="<?php echo esc_attr( get_post_meta( $tournament_id, '_reentry_cost', true ) ); ?>">
				<input type="hidden" id="reentry-modal-chip-amount" value="<?php echo esc_attr( get_post_meta( $tournament_id, '_reentry_chips', true ) ); ?>">
			</div>
			<div class="tdwp-modal-footer">
				<button type="button" id="btn-cancel-reentry-modal" class="button"><?php esc_html_e( 'Cancel', 'poker-tournament-import' ); ?></button>
				<button type="button" id="btn-confirm-reentry-action" class="button button-primary"><?php esc_html_e( 'Confirm Action', 'poker-tournament-import' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Players Table -->
	<?php if ( $player_count > 0 ) : ?>
		<table class="wp-list-table widefat fixed striped players-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Player', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Entry #', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Status', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Withdrawal', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Chip Count', 'poker-tournament-import' ); ?></th>
					<?php if ( $show_bounty_column ) : ?>
						<th><?php esc_html_e( 'Bounty', 'poker-tournament-import' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Eliminations', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Paid', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Rebuys', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Add-ons', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Seat', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				// Group players by player_id to consolidate multi-entries (Beta 22.3)
				$grouped_players = array();
				foreach ( $players as $entry ) {
					$pid = $entry->player_id;

					if ( ! isset( $grouped_players[ $pid ] ) ) {
						$grouped_players[ $pid ] = array(
							'player_post'        => get_post( $pid ),
							'entries'            => array(),
							'total_eliminations' => 0,
							'total_paid'         => 0,
							'total_rebuys'       => 0,
							'total_addons'       => 0,
						);
					}

					$grouped_players[ $pid ]['entries'][]             = $entry;
					$grouped_players[ $pid ]['total_eliminations']   += (int) $entry->eliminations_count;
					$grouped_players[ $pid ]['total_paid']           += (float) $entry->paid_amount;
					$grouped_players[ $pid ]['total_rebuys']         += (int) $entry->rebuys_count;
					$grouped_players[ $pid ]['total_addons']         += (int) $entry->addons_count;
				}

				// Display consolidated rows
				foreach ( $grouped_players as $player_id => $group ) :
					$player_post = $group['player_post'];
					if ( ! $player_post ) {
						continue;
					}

					// Find active entry (or latest if all eliminated)
					$active_entry = null;
					foreach ( $group['entries'] as $e ) {
						if ( in_array( $e->status, array( 'active', 'paid', 'checked_in' ), true ) ) {
							$active_entry = $e;
							break;
						}
					}
					if ( ! $active_entry ) {
						// All eliminated, use latest entry
						$active_entry = end( $group['entries'] );
						reset( $group['entries'] ); // Reset array pointer
					}

					$entry_count = count( $group['entries'] );
					$player_reg  = $active_entry; // Alias for backward compatibility

					// Determine re-entry eligibility
					$can_reentry = false;
					if ( 'eliminated' === $active_entry->status && $allow_reentry && null === $active_entry->finish_position ) {
						if ( 0 === $reentry_until_level || $current_level <= $reentry_until_level ) {
							$can_reentry = true;
						}
					}

					// Determine if player is "active" (can be busted out)
					$is_active = in_array( $active_entry->status, array( 'active', 'paid', 'checked_in' ), true );

					// Determine rebuy/add-on eligibility
					$can_rebuy = tdwp_can_player_rebuy( $active_entry );
					$can_addon = tdwp_can_player_addon( $active_entry );
					?>
					<tr data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
						data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>"
						data-chips="<?php echo esc_attr( $player_reg->chip_count ); ?>"
						data-bounty="<?php echo esc_attr( $player_reg->bounty_amount ); ?>"
						data-status="<?php echo esc_attr( $player_reg->status ); ?>">
						<td>
							<strong><?php echo esc_html( $player_post->post_title ); ?></strong>
							<?php if ( $entry_count > 1 ) : ?>
								<span class="entry-badge" style="color: #999; font-size: 0.9em;">(<?php echo esc_html( $entry_count ); ?>×)</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $player_reg->entry_number ); ?></td>
						<td>
							<select class="player-status-select" data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>" data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>">
								<option value="registered" <?php selected( $player_reg->status, 'registered' ); ?>><?php esc_html_e( 'Registered', 'poker-tournament-import' ); ?></option>
								<option value="paid" <?php selected( $player_reg->status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'poker-tournament-import' ); ?></option>
								<option value="checked_in" <?php selected( $player_reg->status, 'checked_in' ); ?>><?php esc_html_e( 'Checked In', 'poker-tournament-import' ); ?></option>
								<option value="active" <?php selected( $player_reg->status, 'active' ); ?>><?php esc_html_e( 'Active', 'poker-tournament-import' ); ?></option>
								<option value="eliminated" <?php selected( $player_reg->status, 'eliminated' ); ?>><?php esc_html_e( 'Eliminated', 'poker-tournament-import' ); ?></option>
							</select>
						</td>
						<td>
							<?php
							// Display withdrawal status with visual indicators (Phase 3)
							$withdrawal_status_class = '';
							$withdrawal_status_text = '';
							$withdrawal_indicator = '';

							if ( ! empty( $player_reg->withdrawal_status ) && $player_reg->withdrawal_status !== 'active' ) {
								switch ( $player_reg->withdrawal_status ) {
									case 'withdrawn':
										$withdrawal_status_class = 'withdrawn-status';
										$withdrawal_status_text = __( 'Withdrawn', 'poker-tournament-import' );
										$withdrawal_indicator = '⚠️';
										break;
									case 'declined_reentry':
										$withdrawal_status_class = 'declined-reentry-status';
										$withdrawal_status_text = __( 'Declined Re-entry', 'poker-tournament-import' );
										$withdrawal_indicator = '❌';
										break;
								}

								if ( ! empty( $player_reg->withdrawal_timestamp ) ) {
									$withdrawal_date = date( 'M j, H:i', strtotime( $player_reg->withdrawal_timestamp ) );
									$withdrawal_status_text .= ' (' . esc_html( $withdrawal_date ) . ')';
								}
							} else {
								$withdrawal_status_text = '—';
							}

							if ( ! empty( $withdrawal_status_class ) ) :
							?>
								<span class="<?php echo esc_attr( $withdrawal_status_class ); ?>" style="
									display: inline-block;
									padding: 2px 6px;
									border-radius: 3px;
									font-size: 0.85em;
									font-weight: 600;
								">
									<?php echo esc_html( $withdrawal_indicator . ' ' . $withdrawal_status_text ); ?>
								</span>
							<?php else : ?>
								<?php echo esc_html( $withdrawal_status_text ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( number_format( (int) $player_reg->chip_count ) ); ?>
						</td>
						<?php if ( $show_bounty_column ) : ?>
							<td>$<?php echo esc_html( number_format( (float) $player_reg->bounty_amount, 2 ) ); ?></td>
						<?php endif; ?>
						<td><?php echo esc_html( $group['total_eliminations'] ); ?></td>
						<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $group['entries'][0]->registration_date ) ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $group['total_paid'], 2 ) ); ?></td>
						<td><?php echo esc_html( $group['total_rebuys'] ); ?></td>
						<td><?php echo esc_html( $group['total_addons'] ); ?></td>
						<td><?php echo $player_reg->seat_assignment ? esc_html( $player_reg->seat_assignment ) : '—'; ?></td>
						<td class="actions-cell">
							<?php if ( $is_active ) : ?>
								<button type="button" class="button button-small btn-bust-player"
									data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
									data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
									data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>">
									<?php esc_html_e( 'Bust Out', 'poker-tournament-import' ); ?>
								</button>
								<button type="button" class="button button-small btn-update-chips"
									data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
									data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
									data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>"
									data-current-chips="<?php echo esc_attr( $player_reg->chip_count ); ?>">
									<?php esc_html_e( 'Chips', 'poker-tournament-import' ); ?>
								</button>
								<?php if ( $can_rebuy ) : ?>
									<button type="button" class="button button-small btn-rebuy-player"
										data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
										data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
										data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>"
										data-rebuy-count="<?php echo esc_attr( $player_reg->rebuys_count ); ?>"
										data-current-chips="<?php echo esc_attr( $player_reg->chip_count ); ?>"
										data-player-name="<?php echo esc_attr( $player_post->post_title ); ?>">
										<?php esc_html_e( 'Rebuy', 'poker-tournament-import' ); ?>
									</button>
								<?php endif; ?>
								<?php if ( $can_addon ) : ?>
									<button type="button" class="button button-small btn-addon-player"
										data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
										data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
										data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>"
										data-addon-count="<?php echo esc_attr( $player_reg->addons_count ); ?>"
										data-current-chips="<?php echo esc_attr( $player_reg->chip_count ); ?>"
										data-player-name="<?php echo esc_attr( $player_post->post_title ); ?>">
										<?php esc_html_e( 'Add-on', 'poker-tournament-import' ); ?>
									</button>
								<?php endif; ?>

								<!-- Withdrawal Button (Phase 3) -->
								<button type="button" class="button button-small btn-withdraw-player"
									data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
									data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
									data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>"
									data-player-name="<?php echo esc_attr( $player_post->post_title ); ?>"
									data-current-chips="<?php echo esc_attr( $player_reg->chip_count ); ?>">
									<?php esc_html_e( 'Withdraw', 'poker-tournament-import' ); ?>
								</button>
							<?php elseif ( $can_reentry ) : ?>
								<button type="button" class="button button-small button-primary btn-reentry-modal"
									data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
									data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
									data-player-name="<?php echo esc_attr( $player_post->post_title ); ?>"
									data-reentry-cost="<?php echo esc_attr( get_post_meta( $tournament_id, '_reentry_cost', true ) ); ?>"
									data-reentry-chips="<?php echo esc_attr( get_post_meta( $tournament_id, '_reentry_chips', true ) ); ?>">
									<?php esc_html_e( 'Re-enter', 'poker-tournament-import' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( in_array( $player_reg->status, array( 'registered', 'paid' ), true ) ) : ?>
								<button type="button" class="button button-small btn-remove-player"
									data-registration-id="<?php echo esc_attr( $player_reg->id ); ?>"
									data-player-id="<?php echo esc_attr( $player_reg->player_id ); ?>"
									data-entry-number="<?php echo esc_attr( $player_reg->entry_number ); ?>">
									<?php esc_html_e( 'Remove', 'poker-tournament-import' ); ?>
								</button>
							<?php endif; ?>
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
