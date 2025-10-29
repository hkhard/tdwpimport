/**
 * Table Manager JS
 * Drag-drop, table operations, balance
 */

(function($) {
    'use strict';

    const TableManager = {
        tournamentId: 0,

        init: function() {
            if (typeof tdwpTournamentControl !== 'undefined') {
                this.tournamentId = tdwpTournamentControl.tournament_id;
            }

            this.bindEvents();
            this.initDragDrop();
        },

        bindEvents: function() {
            $('#btn-add-table').on('click', this.showAddTableModal.bind(this));
            $('#confirm-add-table').on('click', this.addTable.bind(this));

            $('#btn-auto-seat').on('click', this.autoSeatPlayers.bind(this));
            $('#btn-auto-balance').on('click', this.calculateBalance.bind(this));
            $('#execute-balance').on('click', this.executeBalance.bind(this));

            $('#select-break-table').on('change', this.handleBreakTableSelect.bind(this));

            // Delegated events for dynamic elements
            $(document).on('click', '.remove-table', this.removeTable.bind(this));
            $(document).on('click', '.unseat-btn', this.unseatPlayer.bind(this));
        },

        initDragDrop: function() {
            const self = this;

            // Make players draggable
            $('.draggable-player').draggable({
                revert: 'invalid',
                helper: 'clone',
                cursor: 'move',
                zIndex: 1000,
                start: function(event, ui) {
                    $(this).css('opacity', '0.5');
                },
                stop: function(event, ui) {
                    $(this).css('opacity', '1');
                }
            });

            // Make ONLY empty seats droppable (not occupied ones)
            $('.droppable-seat.empty').droppable({
                accept: '.draggable-player',
                hoverClass: 'ui-droppable-hover',
                drop: function(event, ui) {
                    const $dragged = ui.draggable;
                    const $dropzone = $(this);

                    // Get player and seat info
                    const playerId = $dragged.data('player-id');
                    const toTableId = $dropzone.data('table-id');
                    const toSeatNumber = $dropzone.data('seat-number');

                    // Move player to seat
                    self.movePlayer(playerId, toTableId, toSeatNumber);
                }
            });

            // Disable droppable on occupied seats (in case they were previously empty)
            $('.droppable-seat.occupied').droppable('destroy');
        },

        showAddTableModal: function() {
            if (window.TournamentControl) {
                window.TournamentControl.showModal('add-table-modal');
            }
        },

        addTable: function() {
            const maxSeats = parseInt($('#new-table-max-seats').val());

            this.ajaxCall('tdwp_tm_add_table', {
                max_seats: maxSeats
            }, (response) => {
                // Reload page to show new table
                location.reload();
            });
        },

        removeTable: function(e) {
            e.preventDefault();

            const tableId = $(e.currentTarget).data('table-id');

            if (confirm(tdwpTournamentControl.i18n.confirm_remove_table)) {
                this.ajaxCall('tdwp_tm_remove_table', {
                    table_id: tableId
                }, (response) => {
                    // Remove table card from DOM
                    $('[data-table-id="' + tableId + '"]').closest('.table-card').fadeOut(function() {
                        $(this).remove();
                    });

                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('Table removed');
                    }
                });
            }
        },

        movePlayer: function(playerId, toTableId, toSeatNumber) {
            this.ajaxCall('tdwp_tm_move_player', {
                player_id: playerId,
                to_table_id: toTableId,
                to_seat_number: toSeatNumber
            }, (response) => {
                // Reload page to update all seats
                location.reload();
            });
        },

        unseatPlayer: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const playerId = $(e.currentTarget).data('player-id');

            this.ajaxCall('tdwp_tm_unseat_player', {
                player_id: playerId
            }, (response) => {
                // Reload page to update
                location.reload();
            });
        },

        autoSeatPlayers: function() {
            const $button = $('#btn-auto-seat');
            const originalText = $button.text();

            $button.prop('disabled', true).text('Seating...');

            this.ajaxCall('tdwp_tm_auto_seat', {}, (response) => {
                if (window.TournamentControl) {
                    window.TournamentControl.showNotice(response.message, 'success');

                    if (response.errors && response.errors.length > 0) {
                        response.errors.forEach(err => {
                            window.TournamentControl.showNotice(err, 'warning');
                        });
                    }
                }

                // Reload page to show seated players
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }, () => {
                // Error callback
                $button.prop('disabled', false).text(originalText);
            });
        },

        calculateBalance: function() {
            this.ajaxCall('tdwp_tm_calculate_balance', {}, (response) => {
                if (response.balanced) {
                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice(response.message, 'info');
                    }
                } else {
                    this.showBalancePreview(response);
                }
            });
        },

        showBalancePreview: function(plan) {
            const $movesList = $('#balance-moves-list');
            $movesList.empty();

            if (plan.moves && plan.moves.length > 0) {
                plan.moves.forEach(function(move) {
                    const html = '<div class="balance-move-item">' +
                        '<div class="player-name">' + move.player_name + '</div>' +
                        '<div class="move-arrow">From: Table ' + move.from_table_num + ' Seat ' + move.from_seat + '</div>' +
                        '<div class="move-arrow">To: Table ' + move.to_table_num + ' Seat ' + move.to_seat + '</div>' +
                        '</div>';

                    $movesList.append(html);
                });

                // Store moves for execution
                this.balancePlan = plan.moves;

                if (window.TournamentControl) {
                    window.TournamentControl.showModal('balance-preview-modal');
                }
            }
        },

        executeBalance: function() {
            if (!this.balancePlan || this.balancePlan.length === 0) {
                return;
            }

            const movesJson = JSON.stringify(this.balancePlan);

            this.ajaxCall('tdwp_tm_execute_balance', {
                moves: movesJson
            }, (response) => {
                if (window.TournamentControl) {
                    window.TournamentControl.hideModal('balance-preview-modal');
                    window.TournamentControl.showNotice('Tables balanced!');
                }

                // Reload page to show new layout
                setTimeout(function() {
                    location.reload();
                }, 1000);
            });
        },

        handleBreakTableSelect: function(e) {
            const tableId = $(e.currentTarget).val();

            if (!tableId) {
                return;
            }

            if (confirm(tdwpTournamentControl.i18n.confirm_break_table)) {
                this.breakTable(tableId);
            }

            // Reset dropdown
            $(e.currentTarget).val('');
        },

        breakTable: function(tableId) {
            this.ajaxCall('tdwp_tm_break_table', {
                table_id: tableId
            }, (response) => {
                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Table broken');
                }

                // Reload page
                setTimeout(function() {
                    location.reload();
                }, 1000);
            });
        },

        ajaxCall: function(action, data, successCallback, errorCallback) {
            const ajaxData = {
                action: action,
                nonce: tdwpTournamentControl.nonce,
                tournament_id: this.tournamentId,
                ...data
            };

            $.post(tdwpTournamentControl.ajaxurl, ajaxData)
                .done(function(response) {
                    if (response.success && successCallback) {
                        successCallback(response.data);
                    } else if (!response.success) {
                        if (window.TournamentControl) {
                            window.TournamentControl.showNotice(response.data.message, 'error');
                        }
                        if (errorCallback) {
                            errorCallback(response.data);
                        }
                    }
                })
                .fail(function() {
                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('AJAX error', 'error');
                    }
                    if (errorCallback) {
                        errorCallback();
                    }
                });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TableManager.init();
    });

    // Reinitialize drag-drop when switching to Tables tab
    $(document).on('tdwp:tabChanged', function(e, tab) {
        if (tab === 'tables') {
            setTimeout(function() {
                TableManager.initDragDrop();
            }, 100);
        }
    });

    // Export to global scope
    window.TableManager = TableManager;

})(jQuery);
