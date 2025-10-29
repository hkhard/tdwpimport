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
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TournamentControl.init();
    });

    // Export to global scope for other scripts
    window.TournamentControl = TournamentControl;

})(jQuery);
