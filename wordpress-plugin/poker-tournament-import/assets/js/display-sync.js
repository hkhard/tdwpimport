/**
 * TDWP Display Synchronization
 * Handles real-time synchronization between displays and server
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

(function($) {
    'use strict';

    // Main synchronization object
    window.TDWP_DisplaySync = {

        // Configuration
        config: {
            heartbeatInterval: 'fast',
            reconnectDelay: 5000,
            maxReconnectAttempts: 10,
            autoRefreshInterval: 30000, // 30 seconds
        },

        // State
        state: {
            isConnected: false,
            reconnectAttempts: 0,
            lastUpdate: null,
            currentData: {},
            autoRefreshTimer: null,
        },

        /**
         * Initialize display synchronization
         */
        init: function() {
            if (typeof tdwp_display_sync === 'undefined') {
                console.error('TDWP Display Sync: Configuration not available');
                return;
            }

            this.config.screenId = tdwp_display_sync.screen_id;
            this.config.ajaxUrl = tdwp_display_sync.ajax_url;
            this.config.nonce = tdwp_display_sync.nonce;
            this.config.heartbeatInterval = tdwp_display_sync.heartbeat_interval || 'fast';

            this.setupHeartbeat();
            this.setupAutoRefresh();
            this.bindEvents();

            console.log('TDWP Display Sync: Initialized for screen', this.config.screenId);
        },

        /**
         * Setup WordPress Heartbeat integration
         */
        setupHeartbeat: function() {
            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.enqueue('tdwp_display_sync', {
                    tdwp_screen_id: this.config.screenId,
                    tdwp_display_id: this.config.screenId,
                    tdwp_check_updates: true
                }, {
                    interval: this.config.heartbeatInterval,
                    screenId: 'tdwp_display_sync'
                });

                $(document).on('heartbeat-send.tdwp_display', this.onHeartbeatSend.bind(this));
                $(document).on('heartbeat-tick.tdwp_display', this.onHeartbeatTick.bind(this));
                $(document).on('heartbeat-error.tdwp_display', this.onHeartbeatError.bind(this));
            }
        },

        /**
         * Setup auto-refresh fallback
         */
        setupAutoRefresh: function() {
            this.state.autoRefreshTimer = setInterval(function() {
                if (!this.state.isConnected) {
                    this.refreshContent();
                }
            }.bind(this), this.config.autoRefreshInterval);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Visibility change handling
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    this.pauseSync();
                } else {
                    this.resumeSync();
                }
            }.bind(this));

            // Page unload handling
            $(window).on('beforeunload', function() {
                this.unregisterScreen();
            }.bind(this));

            // Network status handling
            window.addEventListener('online', function() {
                this.reconnect();
            }.bind(this));

            window.addEventListener('offline', function() {
                this.state.isConnected = false;
                this.showConnectionStatus(false);
            }.bind(this));
        },

        /**
         * Heartbeat send handler
         */
        onHeartbeatSend: function(e, data) {
            // Add tournament ID if available
            var tournamentId = this.getTournamentId();
            if (tournamentId) {
                data.tdwp_tournament_id = tournamentId;
            }

            // Add client timestamp
            data.tdwp_client_time = new Date().toISOString();
        },

        /**
         * Heartbeat tick handler
         */
        onHeartbeatTick: function(e, data) {
            this.state.isConnected = true;
            this.state.reconnectAttempts = 0;
            this.state.lastUpdate = new Date();

            if (data.tdwp_sync_status) {
                this.updateSyncStatus(data.tdwp_sync_status);
            }

            if (data.tdwp_tournament_data) {
                this.updateTournamentData(data.tdwp_tournament_data);
            }

            if (data.tdwp_updates) {
                this.handleUpdates(data.tdwp_updates);
            }

            if (data.tdwp_screen_health) {
                this.updateScreenHealth(data.tdwp_screen_health);
            }

            this.showConnectionStatus(true);
        },

        /**
         * Heartbeat error handler
         */
        onHeartbeatError: function(e, jqXHR, textStatus, error) {
            this.state.isConnected = false;
            console.error('TDWP Display Sync: Heartbeat error', error);

            this.showConnectionStatus(false);
            this.attemptReconnect();
        },

        /**
         * Handle updates from server
         */
        handleUpdates: function(updates) {
            var needsRefresh = false;

            if (updates.layout_changed) {
                console.log('TDWP Display Sync: Layout changed, refresh needed');
                needsRefresh = true;
            }

            if (updates.template_changed) {
                console.log('TDWP Display Sync: Template changed, refresh needed');
                needsRefresh = true;
            }

            if (updates.tournament_data_changed) {
                console.log('TDWP Display Sync: Tournament data changed');
                this.updateTournamentData(updates.tournament_data);
            }

            if (needsRefresh) {
                this.refreshContent();
            }
        },

        /**
         * Update tournament data in the DOM
         */
        updateTournamentData: function(data) {
            this.state.currentData = data;

            // Update clock display
            if (data.clock_display) {
                this.updateElement('.clock-display', data.clock_display);
            }

            // Update blinds
            if (data.current_blinds) {
                this.updateElement('.current-blinds', data.current_blinds);
            }

            if (data.next_blinds) {
                this.updateElement('.next-blinds', data.next_blinds);
            }

            // Update player count
            if (data.players_remaining) {
                this.updateElement('.players-remaining', data.players_remaining);
            }

            // Update prize pool
            if (data.prize_pool) {
                this.updateElement('.prize-amount', data.prize_pool);
            }

            // Trigger custom event for other components
            $(document).trigger('tdwp_tournament_updated', [data]);
        },

        /**
         * Update DOM element if content changed
         */
        updateElement: function(selector, content) {
            var $element = $(selector);
            if ($element.length && $element.text() !== content.toString()) {
                $element.text(content).addClass('updated');
                setTimeout(function() {
                    $element.removeClass('updated');
                }, 1000);
            }
        },

        /**
         * Update sync status indicator
         */
        updateSyncStatus: function(status) {
            var $indicator = $('.tdwp-sync-indicator');
            if ($indicator.length) {
                $indicator.attr('data-status', status.status);
                $indicator.attr('title', 'Last sync: ' + status.last_sync);
            }
        },

        /**
         * Update screen health indicator
         */
        updateScreenHealth: function(health) {
            var $healthIndicator = $('.tdwp-health-indicator');
            if ($healthIndicator.length) {
                $healthIndicator.attr('data-health', health.status);
                $healthIndicator.attr('title', health.message);
            }

            // Update connection quality indicator
            var $qualityIndicator = $('.tdwp-quality-indicator');
            if ($qualityIndicator.length) {
                $qualityIndicator.attr('data-quality', health.connection_quality);
            }
        },

        /**
         * Show connection status
         */
        showConnectionStatus: function(isConnected) {
            var $status = $('.tdwp-connection-status');
            if ($status.length) {
                $status.toggleClass('connected', isConnected);
                $status.toggleClass('disconnected', !isConnected);

                var statusText = isConnected ? 'Connected' : 'Reconnecting...';
                $status.text(statusText);
            }

            // Update body class for styling
            $('body').toggleClass('tdwp-connected', isConnected);
            $('body').toggleClass('tdwp-disconnected', !isConnected);
        },

        /**
         * Refresh content from server
         */
        refreshContent: function() {
            $.ajax({
                url: window.location.href,
                method: 'GET',
                dataType: 'html',
                success: function(html) {
                    // Only update if we got a proper response
                    if (html && html.indexOf('<!DOCTYPE') !== -1) {
                        // Parse response and update relevant parts
                        this.updateContentFromResponse(html);
                    }
                }.bind(this),
                error: function() {
                    console.warn('TDWP Display Sync: Failed to refresh content');
                }
            });
        },

        /**
         * Update content from AJAX response
         */
        updateContentFromResponse: function(html) {
            var $response = $(html);

            // Update tournament data elements
            $response.find('.clock-display').each(function() {
                var $display = $(this);
                $('.clock-display').text($display.text());
            });

            $response.find('.current-blinds').each(function() {
                var $blinds = $(this);
                $('.current-blinds').text($blinds.text());
            });

            $response.find('.players-remaining').each(function() {
                var $players = $(this);
                $('.players-remaining').text($players.text());
            });

            // Trigger update event
            $(document).trigger('tdwp_content_refreshed');
        },

        /**
         * Get tournament ID from page
         */
        getTournamentId: function() {
            // Try to get tournament ID from various sources
            return $('body').data('tournament-id') ||
                   $('#tdwp-tournament-id').val() ||
                   this.extractTournamentIdFromUrl();
        },

        /**
         * Extract tournament ID from URL
         */
        extractTournamentIdFromUrl: function() {
            var match = window.location.href.match(/tournament\/(\d+)/);
            return match ? parseInt(match[1]) : null;
        },

        /**
         * Attempt to reconnect
         */
        attemptReconnect: function() {
            if (this.state.reconnectAttempts >= this.config.maxReconnectAttempts) {
                console.error('TDWP Display Sync: Max reconnect attempts reached');
                return;
            }

            this.state.reconnectAttempts++;

            setTimeout(function() {
                console.log('TDWP Display Sync: Attempting to reconnect (' + this.state.reconnectAttempts + '/' + this.config.maxReconnectAttempts + ')');
                this.reconnect();
            }.bind(this), this.config.reconnectDelay);
        },

        /**
         * Reconnect to server
         */
        reconnect: function() {
            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.connect();
            }
        },

        /**
         * Pause synchronization
         */
        pauseSync: function() {
            if (this.state.autoRefreshTimer) {
                clearInterval(this.state.autoRefreshTimer);
                this.state.autoRefreshTimer = null;
            }

            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.pause();
            }
        },

        /**
         * Resume synchronization
         */
        resumeSync: function() {
            this.setupAutoRefresh();

            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.resume();
            }
        },

        /**
         * Unregister screen on page unload
         */
        unregisterScreen: function() {
            // Send synchronous request to unregister screen
            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                async: false,
                data: {
                    action: 'tdwp_unregister_screen',
                    screen_id: this.config.screenId,
                    nonce: this.config.nonce
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (window.location.search.indexOf('tdwp_display') !== -1 ||
            window.location.pathname.indexOf('/tdwp-display/') !== -1 ||
            window.location.pathname.indexOf('/tdwp-preview/') !== -1) {
            TDWP_DisplaySync.init();
        }
    });

})(jQuery);