<?php
/**
 * Timer Tab Content
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="timer-tab-content">
	<!-- Large Clock Display -->
	<div class="tournament-clock-display">
		<div class="clock-main" id="clock-main">
			<span id="clock-minutes">15</span>:<span id="clock-seconds">00</span>
		</div>
		<div class="clock-status" id="clock-status"><?php esc_html_e( 'Ready to Start', 'poker-tournament-import' ); ?></div>
		<?php if ( isset( $state ) && $state && isset( $state->is_practice ) && 1 === (int) $state->is_practice ) : ?>
			<div class="practice-mode-indicator">
				<span class="practice-badge"><?php esc_html_e( 'PRACTICE MODE', 'poker-tournament-import' ); ?></span>
				<span class="practice-description"><?php esc_html_e( 'Excluded from statistics', 'poker-tournament-import' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<!-- Current Level Info -->
	<div class="level-info-cards">
		<div class="level-card current-level">
			<h3><?php esc_html_e( 'Current Level', 'poker-tournament-import' ); ?></h3>
			<div class="level-number" id="current-level-num">1</div>
			<div class="blinds-display">
				<span id="current-small-blind">25</span> / <span id="current-big-blind">50</span>
				<div class="ante-display"><?php esc_html_e( 'Ante:', 'poker-tournament-import' ); ?> <span id="current-ante">0</span></div>
			</div>
			<div class="level-duration"><?php esc_html_e( 'Duration:', 'poker-tournament-import' ); ?> <span id="current-duration">15</span> <?php esc_html_e( 'min', 'poker-tournament-import' ); ?></div>
		</div>

		<div class="level-card next-level">
			<h3><?php esc_html_e( 'Next Level', 'poker-tournament-import' ); ?></h3>
			<div class="level-number" id="next-level-num">2</div>
			<div class="blinds-display">
				<span id="next-small-blind">50</span> / <span id="next-big-blind">100</span>
				<div class="ante-display"><?php esc_html_e( 'Ante:', 'poker-tournament-import' ); ?> <span id="next-ante">0</span></div>
			</div>
		</div>
	</div>

	<!-- Tournament Info -->
	<?php
	$buy_in          = get_post_meta( $tournament_id, '_buy_in', true );
	$starting_chips  = get_post_meta( $tournament_id, '_starting_chips', true );
	$rebuy_cost      = get_post_meta( $tournament_id, '_rebuy_cost', true );
	$rebuy_chips     = get_post_meta( $tournament_id, '_rebuy_chips', true );
	$addon_cost      = get_post_meta( $tournament_id, '_addon_cost', true );
	$addon_chips     = get_post_meta( $tournament_id, '_addon_chips', true );
	$rake_percentage = get_post_meta( $tournament_id, '_rake_percentage', true );

	if ( $buy_in || $rebuy_cost || $addon_cost || $rake_percentage ) :
		?>
		<details class="tournament-info-section" open>
			<summary><?php esc_html_e( 'Tournament Information', 'poker-tournament-import' ); ?></summary>
			<div class="tournament-info-grid">
				<?php if ( $buy_in ) : ?>
					<div class="info-item">
						<span class="info-label"><?php esc_html_e( 'Buy-in:', 'poker-tournament-import' ); ?></span>
						<span class="info-value">$<?php echo esc_html( number_format( (float) $buy_in, 2 ) ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $starting_chips ) : ?>
					<div class="info-item">
						<span class="info-label"><?php esc_html_e( 'Starting Chips:', 'poker-tournament-import' ); ?></span>
						<span class="info-value"><?php echo esc_html( number_format( (int) $starting_chips ) ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $rebuy_cost > 0 ) : ?>
					<div class="info-item">
						<span class="info-label"><?php esc_html_e( 'Rebuy:', 'poker-tournament-import' ); ?></span>
						<span class="info-value">$<?php echo esc_html( number_format( (float) $rebuy_cost, 2 ) ); ?> (<?php echo esc_html( number_format( (int) $rebuy_chips ) ); ?> chips)</span>
					</div>
				<?php endif; ?>

				<?php if ( $addon_cost > 0 ) : ?>
					<div class="info-item">
						<span class="info-label"><?php esc_html_e( 'Add-on:', 'poker-tournament-import' ); ?></span>
						<span class="info-value">$<?php echo esc_html( number_format( (float) $addon_cost, 2 ) ); ?> (<?php echo esc_html( number_format( (int) $addon_chips ) ); ?> chips)</span>
					</div>
				<?php endif; ?>

				<?php if ( $rake_percentage > 0 ) : ?>
					<div class="info-item">
						<span class="info-label"><?php esc_html_e( 'Rake:', 'poker-tournament-import' ); ?></span>
						<span class="info-value"><?php echo esc_html( number_format( (float) $rake_percentage, 2 ) ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
		</details>
	<?php endif; ?>

	<!-- Control Buttons -->
	<div class="clock-controls">
		<button id="btn-start" class="button button-primary button-hero" style="display:none;"><?php esc_html_e( 'Start Tournament', 'poker-tournament-import' ); ?></button>
		<button id="btn-pause" class="button button-secondary" style="display:none;"><?php esc_html_e( 'Pause', 'poker-tournament-import' ); ?></button>
		<button id="btn-resume" class="button button-primary" style="display:none;"><?php esc_html_e( 'Resume', 'poker-tournament-import' ); ?></button>
		<button id="btn-finish" class="button button-link-delete"><?php esc_html_e( 'Stop Tournament', 'poker-tournament-import' ); ?></button>
		<button id="btn-trash" class="button button-link-delete" style="display:none;"><?php esc_html_e( 'Trash Tournament', 'poker-tournament-import' ); ?></button>
		<button id="btn-skip-level" class="button"><?php esc_html_e( 'Skip Level', 'poker-tournament-import' ); ?></button>
		<button id="btn-add-time" class="button"><?php esc_html_e( 'Add 5 Minutes', 'poker-tournament-import' ); ?></button>

		<div class="break-controls">
			<label><?php esc_html_e( 'Start Break:', 'poker-tournament-import' ); ?></label>
			<select id="break-duration">
				<option value="5">5 <?php esc_html_e( 'min', 'poker-tournament-import' ); ?></option>
				<option value="10" selected>10 <?php esc_html_e( 'min', 'poker-tournament-import' ); ?></option>
				<option value="15">15 <?php esc_html_e( 'min', 'poker-tournament-import' ); ?></option>
				<option value="20">20 <?php esc_html_e( 'min', 'poker-tournament-import' ); ?></option>
			</select>
			<button id="btn-start-break" class="button"><?php esc_html_e( 'Start Break', 'poker-tournament-import' ); ?></button>
			<button id="btn-end-break" class="button button-primary" style="display:none;"><?php esc_html_e( 'End Break', 'poker-tournament-import' ); ?></button>
		</div>
	</div>

	<!-- Level History -->
	<details class="level-history">
		<summary><?php esc_html_e( 'Level History', 'poker-tournament-import' ); ?></summary>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Level', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Blinds', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Ante', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'poker-tournament-import' ); ?></th>
					<th><?php esc_html_e( 'Started', 'poker-tournament-import' ); ?></th>
				</tr>
			</thead>
			<tbody id="level-history-body">
				<tr>
					<td colspan="5"><?php esc_html_e( 'No levels completed yet', 'poker-tournament-import' ); ?></td>
				</tr>
			</tbody>
		</table>
	</details>
</div>
