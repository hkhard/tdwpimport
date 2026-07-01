/**
 * Tournament Clock Frontend JavaScript
 *
 * Real-time clock display with WordPress Heartbeat integration
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function ($) {
  'use strict';

  /**
   * Tournament Clock Frontend Manager
   *
   * @since 3.1.0
   */
  var TDWPClockFrontend = {
    /**
     * Clock widget element
     */
    $widget: null,

    /**
     * Current tournament ID
     */
    tournamentId: null,

    /**
     * Current state data
     */
    currentState: null,

    /**
     * Countdown interval
     */
    countdownInterval: null,

    /**
     * Status check interval
     */
    statusCheckInterval: null,

    /**
     * Local time remaining (for countdown between heartbeats)
     */
    localTimeRemaining: 0,

    /**
     * Last update timestamp
     */
    lastUpdateTime: null,

    /**
     * Whether this instance is running in fullscreen "screen" mode
     */
    screenMode: false,

    /**
     * Previously seen level, used to detect level changes
     */
    previousLevel: null,

    /**
     * Sounds already fired for the current level, keyed by threshold name
     */
    soundsFiredForLevel: {},

    /**
     * Wake lock sentinel (Screen Wake Lock API)
     */
    wakeLockSentinel: null,

    /**
     * Initialize
     *
     * @since 3.1.0
     */
    init: function () {
      this.$widget = $('.tdwp-clock-widget');

      if (!this.$widget.length) {
        return;
      }

      // Get tournament ID from widget
      this.tournamentId = this.$widget.data('tournament-id') || 0;

      // Get initial time from data attribute
      var $timeElement = this.$widget.find('.tdwp-clock-time');
      if ($timeElement.length) {
        this.localTimeRemaining = parseInt($timeElement.data('seconds'), 10) || 0;
      }

      this.screenMode = (typeof tdwpClock !== 'undefined' && tdwpClock.screenMode) || false;

      if (window.TDWPSoundManager && typeof tdwpClock !== 'undefined' && tdwpClock.sounds) {
        window.TDWPSoundManager.preload(tdwpClock.sounds);
      }

      var self = this;
      this.$widget.find('.tdwp-clock-fullscreen-toggle').on('click', function () {
        self.toggleFullscreen();
      });

      if (this.screenMode) {
        this.enterFullscreen();
        this.requestWakeLock();
      }

      $(document).on('visibilitychange', function () {
        if (!document.hidden && self.screenMode) {
          self.requestWakeLock();
        }
      });

      // Start local countdown
      this.startCountdown();

      // Start lightweight status polling (every 3s)
      this.checkStatus();

      // Initialize Heartbeat for real-time updates
      this.initHeartbeat();
    },

    /**
     * Enter fullscreen mode, if supported.
     *
     * @since 3.9.2
     */
    enterFullscreen: function () {
      try {
        var el = document.documentElement;
        if (el && typeof el.requestFullscreen === 'function') {
          el.requestFullscreen().catch(function () {});
        }
      } catch (e) {
        // Ignore - fullscreen may be blocked or unsupported.
      }
    },

    /**
     * Toggle fullscreen mode.
     *
     * @since 3.9.2
     */
    toggleFullscreen: function () {
      try {
        if (!document.fullscreenElement) {
          this.enterFullscreen();
        } else if (typeof document.exitFullscreen === 'function') {
          document.exitFullscreen().catch(function () {});
        }
      } catch (e) {
        // Ignore - fullscreen may be blocked or unsupported.
      }
    },

    /**
     * Request a screen wake lock, if supported.
     *
     * @since 3.9.2
     */
    requestWakeLock: function () {
      var self = this;

      if (!('wakeLock' in navigator)) {
        return;
      }

      try {
        navigator.wakeLock
          .request('screen')
          .then(function (sentinel) {
            self.wakeLockSentinel = sentinel;
          })
          .catch(function () {});
      } catch (e) {
        // Ignore - wake lock unsupported or blocked.
      }
    },

    /**
     * Release the screen wake lock, if held.
     *
     * @since 3.9.2
     */
    releaseWakeLock: function () {
      if (this.wakeLockSentinel) {
        try {
          this.wakeLockSentinel.release();
        } catch (e) {
          // Ignore.
        }
        this.wakeLockSentinel = null;
      }
    },

    /**
     * Initialize WordPress Heartbeat API
     *
     * @since 3.1.0
     */
    initHeartbeat: function () {
      var self = this;

      // Send tournament ID with every heartbeat
      $(document).on('heartbeat-send', function (event, data) {
        if (self.tournamentId) {
          data.tdwp_clock_id = self.tournamentId;
        }
      });

      // Handle heartbeat response
      $(document).on('heartbeat-tick', function (event, data) {
        if (data.tdwp_clock_state) {
          self.updateState(data.tdwp_clock_state);
        }
      });

      // Set heartbeat interval to 15 seconds (matches admin)
      if (typeof wp !== 'undefined' && wp.heartbeat) {
        wp.heartbeat.interval(15);
      }
    },

    /**
     * Start local countdown timer
     *
     * Runs every second to update display between heartbeat updates
     *
     * @since 3.1.0
     */
    startCountdown: function () {
      var self = this;

      // Clear any existing interval
      if (this.countdownInterval) {
        clearInterval(this.countdownInterval);
      }

      // Update every second
      this.countdownInterval = setInterval(function () {
        var status = self.$widget.attr('class').match(/tdwp-clock-status-(\w+)/);
        var statusName = status ? status[1] : '';

        if (statusName === 'running' && self.localTimeRemaining > 0) {
          self.localTimeRemaining--;
          self.updateTimeDisplay(self.localTimeRemaining);
          self.maybePlayThresholdSound(self.localTimeRemaining);
        } else if (statusName === 'break' && self.currentState && self.currentState.break_until) {
          // Break countdown is derived from break_until on each tick so it
          // keeps moving even though localTimeRemaining is frozen.
          self.updateBreakInfo(self.currentState);
        }
      }, 1000);
    },

    /**
     * Play a warning sound when localTimeRemaining crosses a known threshold.
     *
     * @since 3.9.2
     *
     * @param {number} remaining Seconds remaining.
     */
    maybePlayThresholdSound: function (remaining) {
      if (!window.TDWPSoundManager) {
        return;
      }

      var level = this.currentState ? this.currentState.current_level : null;
      var fired = this.soundsFiredForLevel[level] || {};

      if (remaining === 300 && !fired.warn5min) {
        window.TDWPSoundManager.play('warn5min');
        fired.warn5min = true;
      } else if (remaining === 60 && !fired.warn1min) {
        window.TDWPSoundManager.play('warn1min');
        fired.warn1min = true;
      } else if (remaining === 0 && !fired.levelEnd) {
        fired.levelEnd = true;
      }

      this.soundsFiredForLevel[level] = fired;
    },

    /**
     * Check status via lightweight polling
     *
     * Polls every 3 seconds to detect status changes quickly
     *
     * @since 3.1.0
     */
    checkStatus: function () {
      var self = this;

      // Clear any existing interval
      if (this.statusCheckInterval) {
        clearInterval(this.statusCheckInterval);
      }

      // Poll every 3 seconds
      this.statusCheckInterval = setInterval(function () {
        $.ajax({
          url: tdwpClock.ajaxurl,
          type: 'POST',
          data: {
            action: 'tdwp_check_clock_status',
            nonce: tdwpClock.nonce,
            tournament_id: self.tournamentId || 0,
          },
          success: function (response) {
            if (response.success && response.data.status) {
              // Check if status changed
              var currentStatus = self.$widget.attr('class').match(/tdwp-clock-status-(\w+)/);
              var newStatus = response.data.status;

              if (!currentStatus || currentStatus[1] !== newStatus) {
                // Status changed - trigger immediate full state update
                if (typeof wp !== 'undefined' && wp.heartbeat) {
                  wp.heartbeat.connectNow();
                }
              }
            }
          },
        });
      }, 3000); // Every 3 seconds
    },

    /**
     * Update state from heartbeat response
     *
     * @since 3.1.0
     *
     * @param {Object} state State data from server.
     */
    updateState: function (state) {
      if (!state) {
        return;
      }

      this.currentState = state;

      // Update local time remaining from server (source of truth)
      this.localTimeRemaining = parseInt(state.time_remaining, 10) || 0;
      this.lastUpdateTime = Date.now();

      // Update all UI elements
      this.updateTimeDisplay(this.localTimeRemaining);
      this.updateLevel(state.current_level);
      this.updateStatus(state.status);
      this.updateStats(state);
      this.updateBlinds(state);
      this.updateBreakInfo(state);
      this.updateNextLevel(state);
    },

    /**
     * Update time display
     *
     * @since 3.1.0
     *
     * @param {number} seconds Seconds remaining.
     */
    updateTimeDisplay: function (seconds) {
      var $timeElement = this.$widget.find('.tdwp-clock-time');
      if ($timeElement.length) {
        $timeElement.text(this.formatTime(seconds));
        $timeElement.attr('data-seconds', seconds);
      }
    },

    /**
     * Update level display
     *
     * @since 3.1.0
     *
     * @param {number} level Current level.
     */
    updateLevel: function (level) {
      var $levelElement = this.$widget.find('.tdwp-clock-level');
      if ($levelElement.length) {
        // Extract the text pattern (e.g., "Level %d")
        var currentText = $levelElement.text();
        var pattern = currentText.replace(/\d+/, '%d');
        var newText = pattern.replace('%d', level);
        $levelElement.text(newText);
      }

      var numericLevel = parseInt(level, 10);
      if (
        this.previousLevel !== null &&
        !isNaN(numericLevel) &&
        numericLevel > this.previousLevel &&
        window.TDWPSoundManager
      ) {
        window.TDWPSoundManager.play('levelChange');
      }

      if (this.previousLevel !== numericLevel) {
        this.soundsFiredForLevel[numericLevel] = {};
      }

      this.previousLevel = numericLevel;
    },

    /**
     * Update status display and widget class
     *
     * @since 3.1.0
     *
     * @param {string} status Status (running, paused, break, completed).
     */
    updateStatus: function (status) {
      var currentStatus = this.$widget.attr('class').match(/tdwp-clock-status-(\w+)/);
      var previousStatus = currentStatus ? currentStatus[1] : '';

      // Update widget class
      this.$widget.removeClass(
        'tdwp-clock-status-running tdwp-clock-status-paused tdwp-clock-status-break tdwp-clock-status-completed'
      );
      this.$widget.addClass('tdwp-clock-status-' + status);

      if (status === 'break' && previousStatus !== 'break' && window.TDWPSoundManager) {
        window.TDWPSoundManager.play('breakStart');
      }

      // Update status text
      var $statusElement = this.$widget.find('.tdwp-clock-status');
      if ($statusElement.length) {
        var statusText = status.charAt(0).toUpperCase() + status.slice(1);
        // Keep the indicator span, just update text node
        $statusElement
          .contents()
          .filter(function () {
            return this.nodeType === 3; // Text node
          })
          .remove();
        $statusElement.append(statusText);
      }
    },

    /**
     * Update statistics
     *
     * @since 3.1.0
     *
     * @param {Object} state State data.
     */
    updateStats: function (state) {
      var $stats = this.$widget.find('.tdwp-clock-stats');
      if (!$stats.length) {
        return;
      }

      // Update player count
      var $playerValue = $stats.find('.tdwp-stat-value').eq(0);
      if ($playerValue.length) {
        $playerValue.html(state.remaining_players + '/' + state.total_players);
      }

      // Update prize pool (if exists)
      if (state.prize_pool > 0) {
        var $prizeValue = $stats.find('.tdwp-stat-value').eq(1);
        if ($prizeValue.length) {
          $prizeValue.text(this.formatCurrency(state.prize_pool));
        }
      }

      // Update rebuys/addons (if exists)
      if (state.total_rebuys > 0 || state.total_addons > 0) {
        var $rebuyValue = $stats.find('.tdwp-stat-value').eq(2);
        if ($rebuyValue.length) {
          $rebuyValue.html(state.total_rebuys + '/' + state.total_addons);
        }
      }

      var $avgStack = this.$widget.find('.tdwp-clock-avg-stack');
      if ($avgStack.length) {
        if (state.avg_stack) {
          $avgStack.text('Avg Stack: ' + state.avg_stack);
        } else {
          $avgStack.text('');
        }
      }

      var $pot = this.$widget.find('.tdwp-clock-pot');
      if ($pot.length) {
        if (state.current_pot) {
          $pot.text('Pot: ' + this.formatCurrency(state.current_pot));
        } else {
          $pot.text('');
        }
      }
    },

    /**
     * Update blinds display.
     *
     * @since 3.9.2
     *
     * @param {Object} state State data.
     */
    updateBlinds: function (state) {
      var $sb = this.$widget.find('.tdwp-clock-sb');
      var $bb = this.$widget.find('.tdwp-clock-bb');
      var $ante = this.$widget.find('.tdwp-clock-ante');

      if ($sb.length) {
        $sb.text(
          state.small_blind !== null && state.small_blind !== undefined ? state.small_blind : ''
        );
      }
      if ($bb.length) {
        $bb.text(state.big_blind !== null && state.big_blind !== undefined ? state.big_blind : '');
      }
      if ($ante.length) {
        $ante.text(state.ante ? '(' + state.ante + ')' : '');
      }
    },

    /**
     * Update the "next level" preview.
     *
     * @since 3.9.2
     *
     * @param {Object} state State data.
     */
    updateNextLevel: function (state) {
      var $next = this.$widget.find('.tdwp-clock-next-level');
      if (!$next.length) {
        return;
      }

      if (!state.next_level) {
        $next.text('');
        return;
      }

      if (state.next_level.is_break) {
        $next.text('Next: Break');
        return;
      }

      var ante = state.next_level.ante ? ' (' + state.next_level.ante + ')' : '';
      $next.text('Next: ' + state.next_level.small_blind + '/' + state.next_level.big_blind + ante);
    },

    /**
     * Update the break countdown block.
     *
     * @since 3.9.2
     *
     * @param {Object} state State data.
     */
    updateBreakInfo: function (state) {
      var $breakInfo = this.$widget.find('.tdwp-clock-break-info');
      var $backAt = this.$widget.find('.tdwp-clock-back-at');

      if (!$breakInfo.length) {
        return;
      }

      if (state.status === 'break' && state.break_until) {
        $breakInfo.show();
        if ($backAt.length) {
          $backAt.text('Back at ' + this.formatBackAt(state.break_until));
        }
      } else {
        $breakInfo.hide();
        if ($backAt.length) {
          $backAt.text('');
        }
      }
    },

    /**
     * Format a "YYYY-MM-DD HH:MM:SS" datetime string as HH:MM.
     *
     * @since 3.9.2
     *
     * @param {string} datetimeStr Datetime string.
     * @return {string} Formatted HH:MM, or empty string if invalid.
     */
    formatBackAt: function (datetimeStr) {
      if (!datetimeStr) {
        return '';
      }

      var date = new Date(String(datetimeStr).replace(' ', 'T'));
      if (isNaN(date.getTime())) {
        return '';
      }

      return this.pad(date.getHours()) + ':' + this.pad(date.getMinutes());
    },

    /**
     * Format time in MM:SS
     *
     * @since 3.1.0
     *
     * @param {number} seconds Seconds.
     * @return {string} Formatted time.
     */
    formatTime: function (seconds) {
      seconds = parseInt(seconds, 10);
      if (isNaN(seconds)) {
        seconds = 0;
      }

      var minutes = Math.floor(seconds / 60);
      var secs = seconds % 60;

      return this.pad(minutes) + ':' + this.pad(secs);
    },

    /**
     * Format currency
     *
     * @since 3.1.0
     *
     * @param {number} amount Amount.
     * @return {string} Formatted currency.
     */
    formatCurrency: function (amount) {
      amount = parseFloat(amount);
      if (isNaN(amount)) {
        amount = 0;
      }

      // Use localized formatting if available
      if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        return new Intl.NumberFormat(undefined, {
          style: 'currency',
          currency: 'USD',
        }).format(amount);
      }

      // Fallback to simple formatting
      return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    },

    /**
     * Pad number with leading zero
     *
     * @since 3.1.0
     *
     * @param {number} num Number.
     * @return {string} Padded number.
     */
    pad: function (num) {
      return (num < 10 ? '0' : '') + num;
    },
  };

  /**
   * Initialize when DOM is ready
   */
  $(document).ready(function () {
    TDWPClockFrontend.init();
  });
})(jQuery);
