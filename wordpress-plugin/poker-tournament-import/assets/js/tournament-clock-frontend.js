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

			// Start local countdown
			this.startCountdown();

			// Start lightweight status polling (every 3s)
			this.checkStatus();

			// Initialize Heartbeat for real-time updates
			this.initHeartbeat();
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
				// Only countdown if status is running
				var status = self.$widget.attr('class').match(/tdwp-clock-status-(\w+)/);
				if (status && status[1] === 'running' && self.localTimeRemaining > 0) {
					self.localTimeRemaining--;
					self.updateTimeDisplay(self.localTimeRemaining);
				}
			}, 1000);
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
						tournament_id: self.tournamentId || 0
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
				}
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
		},

		/**
		 * Update status display and widget class
		 *
		 * @since 3.1.0
		 *
		 * @param {string} status Status (running, paused, break, completed).
		 */
		updateStatus: function (status) {
			// Update widget class
			this.$widget.removeClass('tdwp-clock-status-running tdwp-clock-status-paused tdwp-clock-status-break tdwp-clock-status-completed');
			this.$widget.addClass('tdwp-clock-status-' + status);

			// Update status text
			var $statusElement = this.$widget.find('.tdwp-clock-status');
			if ($statusElement.length) {
				var statusText = status.charAt(0).toUpperCase() + status.slice(1);
				// Keep the indicator span, just update text node
				$statusElement.contents().filter(function() {
					return this.nodeType === 3; // Text node
				}).remove();
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
					currency: 'USD'
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
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		TDWPClockFrontend.init();
	});

})(jQuery);
