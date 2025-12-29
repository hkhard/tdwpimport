/**
 * TDWP Display Shortcode JavaScript
 * Handles real-time updates for tournament display shortcodes
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

(function($) {
    'use strict';

    // Main shortcode object
    window.TDWP_Shortcode = {

        // Configuration
        config: {
            ajaxUrl: tdwp_shortcode.ajax_url,
            nonce: tdwp_shortcode.nonce,
            autoRefresh: tdwp_shortcode.auto_refresh,
            refreshInterval: tdwp_shortcode.refresh_interval,
        },

        // State
        state: {
            isRefreshing: false,
            refreshTimer: null,
            lastUpdate: null,
        },

        /**
         * Initialize shortcode functionality
         */
        init: function() {
            this.setupAutoRefresh();
            this.bindEvents();
            this.initializeDisplayElements();

            // Set up connection status monitoring
            this.setupConnectionMonitoring();

            console.log('TDWP Shortcode: Initialized');
        },

        /**
         * Setup auto-refresh functionality
         */
        setupAutoRefresh: function() {
            if (this.config.autoRefresh) {
                this.state.refreshTimer = setInterval(function() {
                    this.refreshAllDisplays();
                }.bind(this), this.config.refreshInterval);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    this.pauseAutoRefresh();
                } else {
                    this.resumeAutoRefresh();
                }
            }.bind(this));

            // Network status
            window.addEventListener('online', function() {
                this.showConnectionStatus(true);
                this.refreshAllDisplays();
            }.bind(this));

            window.addEventListener('offline', function() {
                this.showConnectionStatus(false);
            }.bind(this));

            // Click events for manual refresh
            $(document).on('click', '.tdwp-refresh-button', function(e) {
                e.preventDefault();
                this.refreshDisplay($(e.currentTarget).closest('[data-tournament-id]'));
            }.bind(this));

            // Form submissions for tournament selection
            $(document).on('change', '.tdwp-tournament-selector', function(e) {
                var tournamentId = $(e.currentTarget).val();
                if (tournamentId) {
                    this.refreshDisplayForTournament(tournamentId);
                }
            }.bind(this));
        },

        /**
         * Initialize display elements
         */
        initializeDisplayElements: function() {
            // Find all display elements
            $('.tdwp-tournament-display').each(function() {
                var $display = $(this);
                var tournamentId = $display.data('tournament-id');

                if (tournamentId) {
                    $display.attr('data-last-refresh', Date.now());
                    this.addRefreshButton($display);
                    this.addLoadingIndicator($display);
                }
            }.bind(this));

            // Initialize individual shortcode elements
            $('.tdwp-live-clock, .tdwp-leaderboard, .tdwp-prize-pool, .tdwp-player-count, .tdwp-current-blinds').each(function() {
                var $element = $(this);
                var tournamentId = $element.data('tournament-id');

                if (tournamentId) {
                    $element.attr('data-last-refresh', Date.now());
                    this.addLoadingIndicator($element);
                }
            }.bind(this));
        },

        /**
         * Add refresh button to display
         */
        addRefreshButton: function($display) {
            if (!$display.find('.tdwp-refresh-button').length) {
                var $button = $('<button class="tdwp-refresh-button" title="Refresh display">↻</button>');
                $display.find('.tdwp-display-header').append($button);
            }
        },

        /**
         * Add loading indicator
         */
        addLoadingIndicator: function($element) {
            if (!$element.find('.tdwp-loading-indicator').length) {
                var $indicator = $('<div class="tdwp-loading-indicator" style="display: none;">Loading...</div>');
                $element.append($indicator);
            }
        },

        /**
         * Setup connection monitoring
         */
        setupConnectionMonitoring: function() {
            // Add connection status indicator if not present
            if (!$('.tdwp-connection-status').length) {
                var $status = $('<div class="tdwp-connection-status connected">Connected</div>');
                $('body').append($status);
            }
        },

        /**
         * Refresh all displays
         */
        refreshAllDisplays: function() {
            if (this.state.isRefreshing) {
                return;
            }

            this.state.isRefreshing = true;

            // Get unique tournament IDs
            var tournamentIds = [];
            $('.tdwp-tournament-display[data-tournament-id], .tdwp-live-clock[data-tournament-id], .tdwp-leaderboard[data-tournament-id], .tdwp-prize-pool[data-tournament-id], .tdwp-player-count[data-tournament-id], .tdwp-current-blinds[data-tournament-id]').each(function() {
                var id = $(this).data('tournament-id');
                if (id && tournamentIds.indexOf(id) === -1) {
                    tournamentIds.push(id);
                }
            });

            if (tournamentIds.length > 0) {
                this.fetchTournamentData(tournamentIds);
            }

            this.state.isRefreshing = false;
        },

        /**
         * Refresh specific display
         */
        refreshDisplay: function($display) {
            var tournamentId = $display.data('tournament-id');
            if (!tournamentId) {
                return;
            }

            this.showLoading($display, true);
            this.fetchTournamentData([tournamentId]);
        },

        /**
         * Refresh display for specific tournament
         */
        refreshDisplayForTournament: function(tournamentId) {
            var $displays = $('[data-tournament-id="' + tournamentId + '"]');
            if ($displays.length) {
                this.showLoading($displays, true);
                this.fetchTournamentData([tournamentId]);
            }
        },

        /**
         * Fetch tournament data from server
         */
        fetchTournamentData: function(tournamentIds) {
            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'tdwp_get_tournament_data',
                    tournament_ids: tournamentIds,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        this.updateDisplays(response.data);
                        this.state.lastUpdate = new Date();
                    } else {
                        console.error('TDWP Shortcode: Failed to fetch tournament data', response);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('TDWP Shortcode: AJAX error', error);
                    this.showConnectionStatus(false);
                }.bind(this),
                complete: function() {
                    $('.tdwp-loading-indicator').hide();
                }
            });
        },

        /**
         * Update displays with new data
         */
        updateDisplays: function(tournamentData) {
            Object.keys(tournamentData).forEach(function(tournamentId) {
                var data = tournamentData[tournamentId];
                var $displays = $('[data-tournament-id="' + tournamentId + '"]');

                $displays.each(function() {
                    var $display = $(this);
                    $display.attr('data-last-refresh', Date.now());
                    $display.addClass('updated');
                    setTimeout(function() {
                        $display.removeClass('updated');
                    }, 1000);
                });

                // Update clock displays
                this.updateClockDisplays(tournamentId, data);

                // Update blinds displays
                this.updateBlindsDisplays(tournamentId, data);

                // Update player count displays
                this.updatePlayerCountDisplays(tournamentId, data);

                // Update prize pool displays
                this.updatePrizePoolDisplays(tournamentId, data);

                // Update leaderboards
                this.updateLeaderboards(tournamentId, data);

                // Trigger custom event
                $(document).trigger('tdwp_shortcode_updated', [tournamentId, data]);
            }.bind(this));
        },

        /**
         * Update clock displays
         */
        updateClockDisplays: function(tournamentId, data) {
            var $clocks = $('.tdwp-live-clock[data-tournament-id="' + tournamentId + '"], .tdwp-clock-display[data-tournament-id="' + tournamentId + '"]');

            var tournamentStatus = data.tournament_status || 'pending';
            var isRunning = (tournamentStatus === 'running' && data.clock_running);

            // Update status classes
            $clocks.removeClass('status-pending status-running status-paused status-break status-completed running');
            $clocks.addClass('status-' + tournamentStatus);
            if (isRunning) {
                $clocks.addClass('running');
            }

            // Update clock display based on status
            var timeDisplay = data.time_remaining || '00:00';
            var levelDisplay = 'Level ' + (data.current_level || 1);

            if (tournamentStatus === 'pending') {
                timeDisplay = 'Not Started';
                levelDisplay = 'Scheduled';
            } else if (tournamentStatus === 'completed') {
                timeDisplay = 'Finished';
                levelDisplay = 'Completed';
            } else if (tournamentStatus === 'paused') {
                levelDisplay = 'Level ' + (data.current_level || 1) + ' (Paused)';
            } else if (tournamentStatus === 'break') {
                timeDisplay = 'On Break';
                levelDisplay = 'On Break';
            }

            $clocks.find('.clock-display, .clock-time').text(timeDisplay);
            $clocks.find('.clock-level, .level-display').text(levelDisplay);

            // Only show next blinds for running tournaments
            if (isRunning && data.next_blinds) {
                $clocks.find('.next-blinds').text('Next: ' + data.next_blinds).show();
            } else {
                $clocks.find('.next-blinds').hide();
            }
        },

        /**
         * Update blinds displays
         */
        updateBlindsDisplays: function(tournamentId, data) {
            var $blinds = $('.tdwp-current-blinds[data-tournament-id="' + tournamentId + '"], .tdwp-blinds-display[data-tournament-id="' + tournamentId + '"]');

            $blinds.find('.current-blinds').text(data.current_blinds || '10/20');
            $blinds.find('.next-blinds').text('Next: ' + (data.next_blinds || '15/30'));
        },

        /**
         * Update player count displays
         */
        updatePlayerCountDisplays: function(tournamentId, data) {
            var $playerCounts = $('.tdwp-player-count[data-tournament-id="' + tournamentId + '"], .tdwp-player-count-display[data-tournament-id="' + tournamentId + '"]');

            $playerCounts.find('.players-remaining, .players-number').text(data.players_remaining || 0);
        },

        /**
         * Update prize pool displays
         */
        updatePrizePoolDisplays: function(tournamentId, data) {
            var $prizePools = $('.tdwp-prize-pool[data-tournament-id="' + tournamentId + '"], .tdwp-prize-pool-display[data-tournament-id="' + tournamentId + '"]');

            $prizePools.find('.prize-amount').text(data.prize_pool || '$0');

            // Update prize breakdown if available
            if (data.prize_breakdown) {
                var $breakdown = $prizePools.find('.prize-breakdown');
                if ($breakdown.length) {
                    var html = '<ul>';
                    Object.keys(data.prize_breakdown).forEach(function(position) {
                        html += '<li><strong>' + position + ':</strong> ' + data.prize_breakdown[position] + '</li>';
                    });
                    html += '</ul>';
                    $breakdown.html(html);
                }
            }
        },

        /**
         * Update leaderboards
         */
        updateLeaderboards: function(tournamentId, data) {
            var $leaderboards = $('.tdwp-leaderboard[data-tournament-id="' + tournamentId + '"]');

            if (data.leaderboard && data.leaderboard.length > 0) {
                var $table = $leaderboards.find('.tdwp-leaderboard-table');
                if ($table.length) {
                    var tbody = '';
                    data.leaderboard.forEach(function(player, index) {
                        tbody += '<tr>';
                        tbody += '<td class="rank">' + (index + 1) + '</td>';
                        tbody += '<td class="player-name">' + player.name + '</td>';
                        tbody += '<td class="chips">' + parseInt(player.chips || 0).toLocaleString() + '</td>';
                        tbody += '</tr>';
                    });

                    $table.find('tbody').html(tbody);
                }
            }
        },

        /**
         * Show loading indicator
         */
        showLoading: function($elements, show) {
            if (typeof $elements === 'undefined') {
                $elements = $('.tdwp-loading-indicator').parent();
            }

            $elements.each(function() {
                var $loading = $(this).find('.tdwp-loading-indicator');
                if (show) {
                    $loading.show();
                } else {
                    $loading.hide();
                }
            });
        },

        /**
         * Show connection status
         */
        showConnectionStatus: function(isConnected) {
            var $status = $('.tdwp-connection-status');
            if ($status.length) {
                $status.toggleClass('connected', isConnected);
                $status.toggleClass('disconnected', !isConnected);
                $status.text(isConnected ? 'Connected' : 'Disconnected');
            }

            // Update body class
            $('body').toggleClass('tdwp-connected', isConnected);
            $('body').toggleClass('tdwp-disconnected', !isConnected);
        },

        /**
         * Pause auto-refresh
         */
        pauseAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
            }
        },

        /**
         * Resume auto-refresh
         */
        resumeAutoRefresh: function() {
            if (!this.state.refreshTimer && this.config.autoRefresh) {
                this.state.refreshTimer = setInterval(function() {
                    this.refreshAllDisplays();
                }.bind(this), this.config.refreshInterval);
            }
        },

        /**
         * Destroy shortcode functionality
         */
        destroy: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
            }
            $('.tdwp-connection-status').remove();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof tdwp_shortcode !== 'undefined') {
            TDWP_Shortcode.init();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        TDWP_Shortcode.destroy();
    });

})(jQuery);