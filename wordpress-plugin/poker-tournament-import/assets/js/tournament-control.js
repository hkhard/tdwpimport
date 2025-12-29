/**
 * Tournament Control JS
 * Tab switching, minimized indicator
 */

(function($) {
    'use strict';

    const TournamentControl = {
        currentTab: 'timer',

        init: function() {
            this.bindEvents();
            this.bindPlayerEvents();
            this.initMinimizedClock();
            this.restoreActiveTab();
        },

        bindEvents: function() {
            // Tab switching
            $('.tdwp-tab-button').on('click', this.switchTab.bind(this));

            // Modal close buttons
            $('.modal-close').on('click', function() {
                $(this).closest('.tdwp-modal').hide();
            });

            // Click outside modal to close
            $('.tdwp-modal').on('click', function(e) {
                if ($(e.target).hasClass('tdwp-modal')) {
                    $(this).hide();
                }
            });
        },

        switchTab: function(e) {
            const $button = $(e.currentTarget);
            const tab = $button.data('tab');

            if (tab === this.currentTab) {
                return;
            }

            // Update buttons
            $('.tdwp-tab-button').removeClass('active');
            $button.addClass('active');

            // Update panels
            $('.tdwp-tab-panel').removeClass('active');
            $('#tab-' + tab).addClass('active');

            // Update minimized clock visibility
            if (tab === 'timer') {
                $('#tdwp-minimized-clock').hide();
            } else {
                $('#tdwp-minimized-clock').show();
            }

            this.currentTab = tab;

            // Update URL hash for persistence
            window.location.hash = 'tab-' + tab;

            // Trigger event for other scripts
            $(document).trigger('tdwp:tabChanged', [tab]);
        },

        restoreActiveTab: function() {
            // Check URL hash for saved tab
            const hash = window.location.hash.substring(1); // Remove #
            if (hash && hash.startsWith('tab-')) {
                const tab = hash.replace('tab-', '');
                const $button = $('.tdwp-tab-button[data-tab="' + tab + '"]');

                if ($button.length) {
                    // Deactivate default tab
                    $('.tdwp-tab-button').removeClass('active');
                    $('.tdwp-tab-panel').removeClass('active');

                    // Activate saved tab
                    $button.addClass('active');
                    $('#tab-' + tab).addClass('active');
                    this.currentTab = tab;

                    // Update minimized clock
                    if (tab === 'timer') {
                        $('#tdwp-minimized-clock').hide();
                    } else {
                        $('#tdwp-minimized-clock').show();
                    }
                }
            }
        },

        initMinimizedClock: function() {
            // Initially hidden (timer tab is active by default)
            $('#tdwp-minimized-clock').hide();
        },

        updateMinimizedClock: function(level, time, blinds) {
            $('#mini-level').text(level);
            $('#mini-time').text(time);
            $('#mini-blinds').text(blinds);
        },

        showNotice: function(message, type) {
            type = type || 'success';

            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.tdwp-tournament-control h1').after($notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showModal: function(modalId) {
            $('#' + modalId).show();
        },

        hideModal: function(modalId) {
            $('#' + modalId).hide();
        },

        // Player Management Methods
        bindPlayerEvents: function() {
            // Add player button
            $('#btn-add-player').on('click', this.openAddPlayerModal.bind(this));

            // Modal close buttons
            $('#add-player-modal .tdwp-modal-close, #btn-cancel-add').on('click', this.closeAddPlayerModal.bind(this));

            // Confirm add player
            $('#btn-confirm-add').on('click', this.addPlayer.bind(this));

            // Remove player buttons
            $(document).on('click', '.btn-remove-player', this.handleRemovePlayer.bind(this));

            // Player status change
            $(document).on('change', '.player-status-select', this.handleStatusChange.bind(this));

            // Buy-in wizard
            $('#btn-process-buyins').on('click', this.openBuyinWizard.bind(this));
            $('#buyin-wizard-modal .tdwp-modal-close, #btn-cancel-buyin').on('click', this.closeBuyinWizard.bind(this));
            $('#btn-confirm-buyin').on('click', this.processBuyins.bind(this));

            // Buy-in wizard inputs - calculate totals on change
            $(document).on('change', '.buyin-checkbox, .rebuys-input, .addons-input', this.updateBuyinTotal);

            // Beta 22: Live player operations
            $(document).on('click', '.btn-bust-player', this.toggleBustoutInline.bind(this));
            $(document).on('click', '.btn-reentry-player', this.handleReentry.bind(this));
            $(document).on('click', '.btn-update-chips', this.openUpdateChipsModal.bind(this));

            // Phase 2: Rebuy and Add-on operations
            $(document).on('click', '.btn-rebuy-player', this.openRebuyModal.bind(this));
            $(document).on('click', '.btn-addon-player', this.openAddonModal.bind(this));

            // Rebuy modal
            $('#rebuy-modal .tdwp-modal-close, #btn-cancel-rebuy').on('click', this.closeRebuyModal.bind(this));
            $('#btn-confirm-rebuy').on('click', this.processRebuy.bind(this));

            // Add-on modal
            $('#addon-modal .tdwp-modal-close, #btn-cancel-addon').on('click', this.closeAddonModal.bind(this));
            $('#btn-confirm-addon').on('click', this.processAddon.bind(this));

            // ESC key to close inline expansion
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeBustoutInline();
                }
            });

            // Update chips modal
            $('#update-chips-modal .tdwp-modal-close, #btn-cancel-chips').on('click', this.closeUpdateChipsModal.bind(this));
            $('#btn-confirm-chips').on('click', this.confirmUpdateChips.bind(this));
        },

        openAddPlayerModal: function() {
            // Reset form
            $('#player-select').val('');
            $('#paid-in-full').prop('checked', false);
            this.showModal('add-player-modal');
        },

        closeAddPlayerModal: function() {
            this.hideModal('add-player-modal');
        },

        addPlayer: function() {
            const playerId = $('#player-select').val();
            const paidInFull = $('#paid-in-full').is(':checked');
            const buyIn = parseFloat($('#tournament-buy-in').val()) || 0;
            const paidAmount = paidInFull ? buyIn : 0;
            const status = paidInFull ? 'paid' : 'registered';
            const tournamentId = tdwpTournamentControl.tournament_id;

            if (!playerId) {
                this.showNotice('Please select a player', 'error');
                return;
            }

            const $button = $('#btn-confirm-add');
            const originalText = $button.text();
            $button.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_add_player',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: playerId,
                    status: status,
                    paid_amount: paidAmount
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.closeAddPlayerModal();
                        // Reload page to show updated roster
                        location.reload();
                    } else {
                        this.showNotice(response.data.message || 'Failed to add player', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while adding the player', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        handleRemovePlayer: function(e) {
            const $button = $(e.currentTarget);
            const playerId = $button.data('player-id');
            const playerName = $button.closest('tr').find('td:first strong').text();

            if (!confirm('Remove ' + playerName + ' from this tournament?')) {
                return;
            }

            this.removePlayer(playerId, $button);
        },

        removePlayer: function(playerId, $button) {
            const tournamentId = tdwpTournamentControl.tournament_id;
            const originalText = $button.text();

            $button.prop('disabled', true).text('Removing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_remove_player',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: playerId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        // Reload page to show updated roster
                        location.reload();
                    } else {
                        this.showNotice(response.data.message || 'Failed to remove player', 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while removing the player', 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        handleStatusChange: function(e) {
            const $select = $(e.currentTarget);
            const playerId = $select.data('player-id');
            const newStatus = $select.val();
            const oldStatus = $select.data('old-status') || $select.val();

            // Store old status in case we need to revert
            $select.data('old-status', oldStatus);

            this.updatePlayerStatus(playerId, newStatus, $select);
        },

        updatePlayerStatus: function(playerId, status, $select) {
            const tournamentId = tdwpTournamentControl.tournament_id;

            $select.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_update_player_status',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: playerId,
                    status: status
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        // Update stored old status
                        $select.data('old-status', status);
                    } else {
                        this.showNotice(response.data.message || 'Failed to update status', 'error');
                        // Revert to old status
                        $select.val($select.data('old-status'));
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while updating status', 'error');
                    // Revert to old status
                    $select.val($select.data('old-status'));
                },
                complete: () => {
                    $select.prop('disabled', false);
                }
            });
        },

        // Buy-in Wizard Methods
        openBuyinWizard: function() {
            // Reset all inputs and recalculate totals
            $('.buyin-checkbox').prop('checked', true);
            $('.rebuys-input, .addons-input').val(0);
            $('.buyin-player-row').each(function() {
                TournamentControl.updateBuyinTotal.call(this);
            });
            this.showModal('buyin-wizard-modal');
        },

        closeBuyinWizard: function() {
            this.hideModal('buyin-wizard-modal');
        },

        updateBuyinTotal: function() {
            const $row = $(this).closest('.buyin-player-row');
            const buyInChecked = $row.find('.buyin-checkbox').is(':checked');
            const rebuys = parseInt($row.find('.rebuys-input').val()) || 0;
            const addons = parseInt($row.find('.addons-input').val()) || 0;

            const buyIn = parseFloat($('#buyin-buy-in').val()) || 0;
            const rebuyCost = parseFloat($('#buyin-rebuy-cost').val()) || 0;
            const addonCost = parseFloat($('#buyin-addon-cost').val()) || 0;

            let total = 0;
            if (buyInChecked) {
                total += buyIn;
            }
            total += (rebuys * rebuyCost);
            total += (addons * addonCost);

            $row.find('.total-amount').text('$' + total.toFixed(2));
        },

        processBuyins: function() {
            const tournamentId = tdwpTournamentControl.tournament_id;
            const playersData = [];

            $('.buyin-player-row').each(function() {
                const $row = $(this);
                const playerId = $row.data('player-id');
                const boughtIn = $row.find('.buyin-checkbox').is(':checked');
                const rebuys = parseInt($row.find('.rebuys-input').val()) || 0;
                const addons = parseInt($row.find('.addons-input').val()) || 0;
                const totalText = $row.find('.total-amount').text();
                const total = parseFloat(totalText.replace('$', '')) || 0;

                playersData.push({
                    player_id: playerId,
                    bought_in: boughtIn,
                    rebuys: rebuys,
                    addons: addons,
                    total: total
                });
            });

            const $button = $('#btn-confirm-buyin');
            const originalText = $button.text();
            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_process_buyins',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    players: playersData
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.closeBuyinWizard();
                        // Reload page to show updated roster
                        location.reload();
                    } else {
                        this.showNotice(response.data.message || 'Failed to process buy-ins', 'error');
                        if (response.data.errors && response.data.errors.length > 0) {
                            response.data.errors.forEach(err => this.showNotice(err, 'error'));
                        }
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while processing buy-ins', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // ===== BETA 22: Live Player Operations =====

        // Bust-out Inline Expansion Methods
        toggleBustoutInline: function(e) {
            const $button = $(e.currentTarget);
            const $clickedRow = $button.closest('tr');

            // Close any existing inline expansions
            $('.tdwp-bustout-inline-row').remove();

            // Get player data
            const registrationId = $button.data('registration-id');
            const playerId = $button.data('player-id');
            const entryNumber = $button.data('entry-number');
            const playerName = $clickedRow.find('td:first strong').text();
            const bountyAmount = parseFloat($clickedRow.data('bounty')) || 0;

            // Clone the template
            const $template = $('#bustout-inline-template');
            const $inlineRow = $template.clone()
                .attr('id', '')
                .removeClass('tdwp-bustout-inline-template')
                .addClass('tdwp-bustout-inline-row')
                .show();

            // Populate player info
            $inlineRow.find('.bustout-player-name-display').text(playerName);
            $inlineRow.find('.bustout-confirm-name-display').text(playerName);
            $inlineRow.find('.bustout-registration-id').val(registrationId);
            $inlineRow.find('.bustout-player-id').val(playerId);
            $inlineRow.find('.bustout-entry-number').val(entryNumber);
            $inlineRow.find('.bustout-player-bounty').val(bountyAmount);

            // Clear confirmation checkbox
            $inlineRow.find('.confirm-bustout-checkbox').prop('checked', false);

            // Populate eliminator checkboxes
            const $container = $inlineRow.find('.eliminator-checkboxes-container');
            $container.empty();

            // Get all active players except the one being eliminated
            $('.players-table tbody tr').each(function() {
                const $thisRow = $(this);
                const thisPlayerId = $thisRow.data('player-id');
                const thisEntryNumber = $thisRow.data('entry-number');
                const thisStatus = $thisRow.data('status');
                const thisPlayerName = $thisRow.find('td:first strong').text();

                // Include active players, exclude the player being eliminated
                if (['active', 'paid', 'checked_in'].includes(thisStatus) &&
                    !(thisPlayerId == playerId && thisEntryNumber == entryNumber)) {
                    const displayName = thisEntryNumber > 1 ?
                        thisPlayerName + ' (Entry #' + thisEntryNumber + ')' :
                        thisPlayerName;

                    const checkbox = $('<label class="eliminator-checkbox">' +
                        '<input type="checkbox" class="eliminator-select" ' +
                        'value="' + thisPlayerId + '" ' +
                        'data-entry="' + thisEntryNumber + '" ' +
                        'data-bounty="' + ($thisRow.data('bounty') || 0) + '">' +
                        displayName +
                        '</label>');

                    $container.append(checkbox);
                }
            });

            // Insert the inline row after the clicked row
            $clickedRow.after($inlineRow);

            // Attach event handlers for this specific inline form
            $inlineRow.find('.tdwp-bustout-inline-close, .btn-cancel-bustout').on('click', () => {
                $inlineRow.remove();
            });

            $inlineRow.find('.btn-confirm-bustout').on('click', () => {
                this.confirmBustout($inlineRow);
            });

            $inlineRow.find('.eliminator-select').on('change', () => {
                this.updateBountyPreview($inlineRow);
            });

            // Focus on confirmation checkbox
            $inlineRow.find('.confirm-bustout-checkbox').focus();
        },

        closeBustoutInline: function() {
            $('.tdwp-bustout-inline-row').remove();
        },

        updateBountyPreview: function($inlineRow) {
            // Count selected eliminators in this inline form
            const selectedEliminators = $inlineRow.find('.eliminator-select:checked');
            const numEliminators = selectedEliminators.length;

            const $preview = $inlineRow.find('.bounty-preview');
            const $previewText = $inlineRow.find('.bounty-earned-preview');

            if (numEliminators === 0) {
                $preview.hide();
                return;
            }

            // Get total bounty amount from hidden field
            const totalBounty = parseFloat($inlineRow.find('.bustout-player-bounty').val()) || 0;
            if (totalBounty <= 0) {
                $preview.hide();
                return;
            }

            // Calculate equal split
            const splitAmount = totalBounty / numEliminators;

            // Update preview text
            $previewText.text(
                '$' + splitAmount.toFixed(2) + ' each (' + numEliminators + ' player' + (numEliminators > 1 ? 's' : '') + ')'
            );
            $preview.show();
        },

        confirmBustout: function($inlineRow) {
            // REQUIRED: Check confirmation checkbox
            if (!$inlineRow.find('.confirm-bustout-checkbox').is(':checked')) {
                this.showNotice('Please confirm the bustout by checking the confirmation box', 'error');
                return;
            }

            const registrationId = $inlineRow.find('.bustout-registration-id').val();
            const playerId = $inlineRow.find('.bustout-player-id').val();
            const entryNumber = $inlineRow.find('.bustout-entry-number').val();
            const tournamentId = tdwpTournamentControl.tournament_id;

            if (!registrationId) {
                this.showNotice('Invalid player selection', 'error');
                return;
            }

            // Collect selected eliminator IDs (can be empty array for natural bustout)
            const eliminatorIds = $inlineRow.find('.eliminator-select:checked').map(function() {
                return parseInt($(this).val());
            }).get();

            const $button = $inlineRow.find('.btn-confirm-bustout');
            const originalText = $button.text();
            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_bust_player',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: registrationId,
                    entry_number: entryNumber,
                    eliminated_by: JSON.stringify(eliminatorIds)
                },
                success: (response) => {
                    if (response.success) {
                        let message = response.data.message;

                        if (response.data.bounty_earned > 0) {
                            message += ' (Bounty: $' + response.data.bounty_earned.toFixed(2) + ')';
                        }

                        if (response.data.can_reentry) {
                            message += ' - Player can re-enter';
                        }

                        if (response.data.tournament_completed) {
                            message += ' - TOURNAMENT COMPLETED!';
                        }

                        this.showNotice(message, 'success');
                        this.closeBustoutInline();

                        // Reload page to show updated roster
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showNotice(response.data.message || 'Failed to eliminate player', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while processing elimination', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Re-entry Methods
        handleReentry: function(e) {
            const $button = $(e.currentTarget);
            const playerId = $button.data('player-id');
            const $row = $button.closest('tr');
            const playerName = $row.find('td:first strong').text();

            if (!confirm('Re-enter ' + playerName + ' into the tournament?')) {
                return;
            }

            this.processReentry(playerId, $button);
        },

        processReentry: function(playerId, $button) {
            const tournamentId = tdwpTournamentControl.tournament_id;
            const originalText = $button.text();

            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_reentry_player',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: playerId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        // Reload page to show new entry
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showNotice(response.data.message || 'Failed to process re-entry', 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while processing re-entry', 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Update Chips Modal Methods
        openUpdateChipsModal: function(e) {
            const $button = $(e.currentTarget);
            const registrationId = $button.data('registration-id');
            const playerId = $button.data('player-id');
            const entryNumber = $button.data('entry-number');
            const currentChips = $button.data('current-chips') || 0;
            const $row = $button.closest('tr');
            const playerName = $row.find('td:first strong').text();

            $('#chip-player-name').text(playerName + ' (Entry #' + entryNumber + ')');
            $('#chip-registration-id').val(registrationId);
            $('#chip-player-id').val(playerId);
            $('#chip-entry-number').val(entryNumber);
            $('#new-chip-count').val(currentChips);

            this.showModal('update-chips-modal');
        },

        closeUpdateChipsModal: function() {
            this.hideModal('update-chips-modal');
        },

        confirmUpdateChips: function() {
            const registrationId = $('#chip-registration-id').val();
            const playerId = $('#chip-player-id').val();
            const entryNumber = $('#chip-entry-number').val();
            const newChips = parseInt($('#new-chip-count').val()) || 0;
            const tournamentId = tdwpTournamentControl.tournament_id;

            if (!registrationId || newChips < 0) {
                this.showNotice('Invalid chip count', 'error');
                return;
            }

            const $button = $('#btn-confirm-chips');
            const originalText = $button.text();
            $button.prop('disabled', true).text('Updating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_update_chip_count',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    player_id: registrationId,
                    entry_number: entryNumber,
                    chip_count: newChips
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.closeUpdateChipsModal();

                        // Update the chip count in the table without reloading
                        const $row = $('.players-table tbody tr[data-player-id="' + playerId + '"][data-entry-number="' + entryNumber + '"]');
                        $row.find('td:nth-child(4)').text(newChips.toLocaleString());
                        $row.data('chips', newChips);
                        $row.find('.btn-update-chips').data('current-chips', newChips);
                    } else {
                        this.showNotice(response.data.message || 'Failed to update chip count', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while updating chip count', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Phase 2: Rebuy and Add-on operations
         */

        // Rebuy modal handlers
        openRebuyModal: function(e) {
            const $button = $(e.currentTarget);
            const registrationId = $button.data('registration-id');
            const playerId = $button.data('player-id');
            const entryNumber = $button.data('entry-number');
            const currentRebuys = $button.data('rebuy-count');
            const currentChips = $button.data('current-chips');
            const playerName = $button.data('player-name');

            // Populate modal with player data
            $('#rebuy-player-name').text(playerName);
            $('#rebuy-current-count').text(currentRebuys);
            $('#rebuy-registration-id').val(registrationId);
            $('#rebuy-player-id').val(playerId);
            $('#rebuy-entry-number').val(entryNumber);
            $('#rebuy-current-chips').val(currentChips);

            // Reset confirmation checkboxes
            $('#confirm-rebuy-limit, #confirm-rebuy-payment').prop('checked', false);

            // Show modal
            $('#rebuy-modal').show();
        },

        closeRebuyModal: function() {
            $('#rebuy-modal').hide();
        },

        processRebuy: function() {
            const $button = $('#btn-confirm-rebuy');
            const originalText = $button.text();

            // Validate confirmations
            const confirmLimit = $('#confirm-rebuy-limit').length ? $('#confirm-rebuy-limit').prop('checked') : true;
            const confirmPayment = $('#confirm-rebuy-payment').prop('checked');

            if (!confirmLimit) {
                this.showNotice('Please confirm player has not reached rebuy limit', 'error');
                return;
            }

            if (!confirmPayment) {
                this.showNotice('Please confirm player has paid for the rebuy', 'error');
                return;
            }

            // Get data
            const registrationId = $('#rebuy-registration-id').val();
            const playerId = $('#rebuy-player-id').val();
            const entryNumber = $('#rebuy-entry-number').val();
            const currentChips = $('#rebuy-current-chips').val();
            const tournamentId = $('#tournament-id').val();

            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_process_rebuy',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    registration_id: registrationId,
                    player_id: playerId,
                    entry_number: entryNumber,
                    current_chips: currentChips
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.closeRebuyModal();

                        // Update the table without reloading
                        this.updatePlayerRowAfterRebuy(playerId, entryNumber, response.data.new_chips, response.data.new_rebuy_count);
                    } else {
                        this.showNotice(response.data.message || 'Failed to process rebuy', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while processing rebuy', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Add-on modal handlers
        openAddonModal: function(e) {
            const $button = $(e.currentTarget);
            const registrationId = $button.data('registration-id');
            const playerId = $button.data('player-id');
            const entryNumber = $button.data('entry-number');
            const currentAddons = $button.data('addon-count');
            const currentChips = $button.data('current-chips');
            const playerName = $button.data('player-name');

            // Populate modal with player data
            $('#addon-player-name').text(playerName);
            $('#addon-current-count').text(currentAddons);
            $('#addon-registration-id').val(registrationId);
            $('#addon-player-id').val(playerId);
            $('#addon-entry-number').val(entryNumber);
            $('#addon-current-chips').val(currentChips);

            // Reset confirmation checkbox
            $('#confirm-addon-payment').prop('checked', false);

            // Show modal
            $('#addon-modal').show();
        },

        closeAddonModal: function() {
            $('#addon-modal').hide();
        },

        processAddon: function() {
            const $button = $('#btn-confirm-addon');
            const originalText = $button.text();

            // Validate confirmation
            const confirmPayment = $('#confirm-addon-payment').prop('checked');

            if (!confirmPayment) {
                this.showNotice('Please confirm player has paid for the add-on', 'error');
                return;
            }

            // Get data
            const registrationId = $('#addon-registration-id').val();
            const playerId = $('#addon-player-id').val();
            const entryNumber = $('#addon-entry-number').val();
            const currentChips = $('#addon-current-chips').val();
            const tournamentId = $('#tournament-id').val();

            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_tm_process_addon',
                    nonce: tdwpTournamentControl.nonce,
                    tournament_id: tournamentId,
                    registration_id: registrationId,
                    player_id: playerId,
                    entry_number: entryNumber,
                    current_chips: currentChips
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.closeAddonModal();

                        // Update the table without reloading
                        this.updatePlayerRowAfterAddon(playerId, entryNumber, response.data.new_chips, response.data.new_addon_count);
                    } else {
                        this.showNotice(response.data.message || 'Failed to process add-on', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while processing add-on', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Helper methods to update table rows
        updatePlayerRowAfterRebuy: function(playerId, entryNumber, newChips, newRebuyCount) {
            const $row = $('.players-table tbody tr[data-player-id="' + playerId + '"][data-entry-number="' + entryNumber + '"]');

            // Update chip count
            $row.find('td:nth-child(4)').text(newChips.toLocaleString());
            $row.data('chips', newChips);

            // Update rebuy count in consolidated row
            const $rebuyCell = $row.find('td:nth-child(9)');
            const currentText = $rebuyCell.text();
            const newTotalRebuys = parseInt(currentText) + 1;
            $rebuyCell.text(newTotalRebuys);

            // Update button data
            $row.find('.btn-rebuy-player').data('current-chips', newChips);
            $row.find('.btn-rebuy-player').data('rebuy-count', newRebuyCount);
            $row.find('.btn-update-chips').data('current-chips', newChips);
        },

        updatePlayerRowAfterAddon: function(playerId, entryNumber, newChips, newAddonCount) {
            const $row = $('.players-table tbody tr[data-player-id="' + playerId + '"][data-entry-number="' + entryNumber + '"]');

            // Update chip count
            $row.find('td:nth-child(4)').text(newChips.toLocaleString());
            $row.data('chips', newChips);

            // Update addon count in consolidated row
            const $addonCell = $row.find('td:nth-child(10)');
            const currentText = $addonCell.text();
            const newTotalAddons = parseInt(currentText) + 1;
            $addonCell.text(newTotalAddons);

            // Update button data
            $row.find('.btn-addon-player').data('current-chips', newChips);
            $row.find('.btn-addon-player').data('addon-count', newAddonCount);
            $row.find('.btn-update-chips').data('current-chips', newChips);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TournamentControl.init();
    });

    // Export to global scope for other scripts
    window.TournamentControl = TournamentControl;

})(jQuery);
