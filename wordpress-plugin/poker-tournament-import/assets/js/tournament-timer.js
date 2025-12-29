/**
 * Tournament Timer JS
 * Clock countdown, controls, Heartbeat sync
 */

(function($) {
    'use strict';

    const TournamentTimer = {
        tournamentId: 0,
        currentLevel: 1,
        timeRemaining: 900, // seconds
        status: 'setup',
        intervalId: null,
        blindSchedule: [], // Will be populated from server

        init: function() {
            if (typeof tdwpTournamentControl !== 'undefined') {
                this.tournamentId = tdwpTournamentControl.tournament_id;
            }

            this.bindEvents();
            this.initHeartbeat();
            this.loadState();
        },

        bindEvents: function() {
            $('#btn-start').on('click', this.startTournament.bind(this));
            $('#btn-pause').on('click', this.pauseTournament.bind(this));
            $('#btn-resume').on('click', this.resumeTournament.bind(this));
            $('#btn-finish').on('click', this.finishTournament.bind(this));
            $('#btn-trash').on('click', this.trashTournament.bind(this));
            $('#btn-skip-level').on('click', this.skipLevel.bind(this));
            $('#btn-add-time').on('click', this.addTime.bind(this));
            $('#btn-start-break').on('click', this.startBreak.bind(this));
            $('#btn-end-break').on('click', this.endBreak.bind(this));
        },

        initHeartbeat: function() {
            const self = this;

            // Use WordPress Heartbeat for polling
            $(document).on('heartbeat-send', function(e, data) {
                if (self.tournamentId) {
                    data.tdwp_tm_poll = {
                        tournament_id: self.tournamentId,
                        action: 'get_state'
                    };
                }
            });

            $(document).on('heartbeat-tick', function(e, data) {
                if (data.tdwp_tm_state) {
                    self.handleStateUpdate(data.tdwp_tm_state);
                }
            });

            // Set Heartbeat to 5-second interval for timer
            wp.heartbeat.interval(5);
        },

        startLocalClock: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
            }

            this.intervalId = setInterval(() => {
                if (this.status === 'running' && this.timeRemaining > 0) {
                    this.timeRemaining--;
                    this.updateDisplay();

                    if (this.timeRemaining === 0) {
                        // Level complete - will be handled by next Heartbeat
                        clearInterval(this.intervalId);
                    }
                }
            }, 1000);
        },

        stopLocalClock: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },

        updateDisplay: function() {
            const minutes = Math.floor(this.timeRemaining / 60);
            const seconds = this.timeRemaining % 60;

            $('#clock-minutes').text(minutes.toString().padStart(2, '0'));
            $('#clock-seconds').text(seconds.toString().padStart(2, '0'));

            const timeStr = minutes + ':' + seconds.toString().padStart(2, '0');

            // Update minimized clock
            if (window.TournamentControl) {
                const blinds = $('#current-small-blind').text() + '/' + $('#current-big-blind').text();
                window.TournamentControl.updateMinimizedClock(this.currentLevel, timeStr, blinds);
            }
        },

        startTournament: function() {
            this.ajaxCall('tdwp_tm_start', {
                level_duration: 900 // 15 minutes default
            }, (response) => {
                this.status = 'running';
                this.timeRemaining = 900;
                this.startLocalClock();
                this.updateControls();
                $('#clock-status').text('Running');

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Tournament started!');
                }
            });
        },

        pauseTournament: function() {
            this.ajaxCall('tdwp_tm_pause', {
                time_remaining: this.timeRemaining
            }, (response) => {
                this.status = 'paused';
                this.stopLocalClock();
                this.updateControls();
                $('#clock-status').text('Paused');

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Tournament paused');
                }
            });
        },

        resumeTournament: function() {
            this.ajaxCall('tdwp_tm_resume', {}, (response) => {
                this.status = 'running';
                this.startLocalClock();
                this.updateControls();
                $('#clock-status').text('Running');

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Tournament resumed');
                }
            });
        },

        finishTournament: function() {
            if (confirm('Stop this tournament? This cannot be undone.')) {
                this.ajaxCall('tdwp_tm_finish', {}, (response) => {
                    this.status = 'finished';
                    this.stopLocalClock();
                    $('#clock-status').text('Finished');
                    this.updateControls();

                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('Tournament finished');
                    }
                });
            }
        },

        trashTournament: function() {
            if (confirm('Trash this tournament? It will be moved to the trash.')) {
                this.ajaxCall('tdwp_tm_trash', {}, (response) => {
                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('Tournament trashed');
                    }

                    // Redirect to live control selector
                    if (response.redirect_url) {
                        setTimeout(() => {
                            window.location.href = response.redirect_url;
                        }, 1000);
                    }
                });
            }
        },

        skipLevel: function() {
            if (confirm('Advance to next level?')) {
                this.ajaxCall('tdwp_tm_advance_level', {
                    next_level_duration: 900
                }, (response) => {
                    this.currentLevel++;
                    this.timeRemaining = 900;
                    this.updateLevelDisplay();

                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('Advanced to level ' + this.currentLevel);
                    }
                });
            }
        },

        addTime: function() {
            this.ajaxCall('tdwp_tm_add_time', {
                seconds: 300 // 5 minutes
            }, (response) => {
                this.timeRemaining += 300;
                this.updateDisplay();

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Added 5 minutes');
                }
            });
        },

        startBreak: function() {
            const duration = parseInt($('#break-duration').val());

            this.ajaxCall('tdwp_tm_start_break', {
                break_duration: duration
            }, (response) => {
                this.status = 'break';
                this.stopLocalClock();
                this.updateControls();
                $('#clock-status').text('On Break - ' + duration + ' minutes');

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Break started (' + duration + ' min)');
                }
            });
        },

        endBreak: function() {
            this.ajaxCall('tdwp_tm_end_break', {
                next_level_duration: 900
            }, (response) => {
                this.status = 'running';
                this.currentLevel++;
                this.timeRemaining = 900;
                this.startLocalClock();
                this.updateControls();
                this.updateLevelDisplay();
                $('#clock-status').text('Running');

                if (window.TournamentControl) {
                    window.TournamentControl.showNotice('Break ended - Level ' + this.currentLevel);
                }
            });
        },

        updateControls: function() {
            $('#btn-start').toggle(this.status === 'setup');
            $('#btn-pause').toggle(this.status === 'running');
            $('#btn-resume').toggle(this.status === 'paused');
            $('#btn-finish').toggle(this.status !== 'finished').prop('disabled', this.status === 'finished');
            $('#btn-trash').toggle(this.status === 'finished');
            $('#btn-end-break').toggle(this.status === 'break');
            $('#btn-start-break').toggle(this.status !== 'break' && this.status !== 'finished');
        },

        updateLevelDisplay: function() {
            $('#current-level-num').text(this.currentLevel);
            $('#next-level-num').text(this.currentLevel + 1);
            // TODO: Update blinds from blind schedule
        },

        loadState: function() {
            this.ajaxCall('tdwp_tm_get_state', {}, (response) => {
                this.handleStateUpdate(response);
            });
        },

        handleStateUpdate: function(data) {
            if (data.state) {
                this.status = data.state.status;
                this.currentLevel = parseInt(data.state.current_level);
                this.timeRemaining = parseInt(data.state.time_remaining);

                this.updateDisplay();
                this.updateControls();
                this.updateLevelDisplay();

                // Update clock status text
                if (this.status === 'finished') {
                    $('#clock-status').text('Finished');
                    this.stopLocalClock();
                } else if (this.status === 'running' && !this.intervalId) {
                    this.startLocalClock();
                }
            }
        },

        ajaxCall: function(action, data, successCallback) {
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
                    }
                })
                .fail(function() {
                    if (window.TournamentControl) {
                        window.TournamentControl.showNotice('AJAX error', 'error');
                    }
                });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TournamentTimer.init();
    });

    // Export to global scope
    window.TournamentTimer = TournamentTimer;

})(jQuery);
