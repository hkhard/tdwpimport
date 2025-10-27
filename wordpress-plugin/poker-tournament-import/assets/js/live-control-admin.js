/**
 * Live Tournament Control Admin JavaScript
 *
 * Integrates with WordPress Heartbeat API for real-time updates
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function ($) {
	'use strict';

	/**
	 * Live Control Manager
	 *
	 * @since 3.1.0
	 */
	var TDWPLiveControl = {

		/**
		 * Current tournament ID
		 */
		tournamentId: null,

		/**
		 * Heartbeat interval ID
		 */
		heartbeatInterval: null,

		/**
		 * Initialize
		 *
		 * @since 3.1.0
		 */
		init: function () {
			this.tournamentId = $('#tdwp-current-tournament-id').val() || null;
			this.bindEvents();

			if (this.tournamentId) {
				this.initHeartbeat();
			this.startLocalCountdown();
			}
		},

		/**
		 * Bind event handlers
		 *
		 * @since 3.1.0
		 */
		bindEvents: function () {
			// Start tournament form
			$('#tdwp-start-form').on('submit', this.handleStart.bind(this));

			// Control buttons
			$(document).on('click', '.tdwp-pause-btn', this.handlePause.bind(this));
			$(document).on('click', '.tdwp-resume-btn', this.handleResume.bind(this));
			$(document).on('click', '.tdwp-advance-btn', this.handleAdvance.bind(this));
			$(document).on('click', '.tdwp-complete-btn', this.handleComplete.bind(this));
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
					data.tdwp_tournament_id = self.tournamentId;
				}
			});

			// Handle heartbeat response
			$(document).on('heartbeat-tick', function (event, data) {
				if (data.tdwp_live_state) {
					self.updateUI(data.tdwp_live_state);
				}

				if (data.tdwp_events) {
					self.updateEventLog(data.tdwp_events);
				}
			});

			// Set heartbeat interval to 15 seconds
			wp.heartbeat.interval(15);
		},

		/**
		 * Handle start tournament
		 *
		 * @since 3.1.0
		 */
		handleStart: function (e) {
			e.preventDefault();

			var $form = $(e.target);
			var templateId = $form.find('#template_id').val();
			var totalPlayers = $form.find('#total_players').val();

			if (!templateId) {
				alert('Please select a template');
				return;
			}

			if (!confirm(tdwpLiveControl.i18n.confirmStart)) {
				return;
			}

			var $button = $form.find('button[type="submit"]');
			$button.prop('disabled', true).addClass('is-busy');

			$.ajax({
				url: tdwpLiveControl.ajaxurl,
				type: 'POST',
				data: {
					action: 'tdwp_start_tournament',
					nonce: tdwpLiveControl.nonce,
					template_id: templateId,
					total_players: totalPlayers
				},
				success: function (response) {
					if (response.success) {
						// Reload page to show active tournament
						window.location.reload();
					} else {
						alert(response.data.message || tdwpLiveControl.i18n.error);
						$button.prop('disabled', false).removeClass('is-busy');
					}
				},
				error: function () {
					alert(tdwpLiveControl.i18n.error);
					$button.prop('disabled', false).removeClass('is-busy');
				}
			});
		},

		/**
		 * Handle pause
		 *
		 * @since 3.1.0
		 */
		handlePause: function (e) {
			e.preventDefault();

			if (!confirm(tdwpLiveControl.i18n.confirmPause)) {
				return;
			}

			this.sendControlAction('tdwp_pause_tournament', $(e.target), null, 'paused');
		},

		/**
		 * Handle resume
		 *
		 * @since 3.1.0
		 */
		handleResume: function (e) {
			e.preventDefault();

			if (!confirm(tdwpLiveControl.i18n.confirmResume)) {
				return;
			}

			this.sendControlAction('tdwp_resume_tournament', $(e.target), null, 'running');
		},

		/**
		 * Handle advance level
		 *
		 * @since 3.1.0
		 */
		handleAdvance: function (e) {
			e.preventDefault();

			if (!confirm(tdwpLiveControl.i18n.confirmAdvance)) {
				return;
			}

			this.sendControlAction('tdwp_advance_level', $(e.target));
		},

		/**
		 * Handle complete
		 *
		 * @since 3.1.0
		 */
		handleComplete: function (e) {
			e.preventDefault();

			if (!confirm(tdwpLiveControl.i18n.confirmComplete)) {
				return;
			}

			this.sendControlAction('tdwp_complete_tournament', $(e.target), function () {
				// Reload page after completion
				window.location.reload();
			});
		},

		/**
		 * Send control action via AJAX
		 *
		 * @since 3.1.0
		 */
		sendControlAction: function (action, $button, callback, newStatus) {
			var self = this;

			$button.prop('disabled', true).addClass('is-busy');

			$.ajax({
				url: tdwpLiveControl.ajaxurl,
				type: 'POST',
				data: {
					action: action,
					nonce: tdwpLiveControl.nonce,
					tournament_id: self.tournamentId
				},
				success: function (response) {
					$button.prop('disabled', false).removeClass('is-busy');

					if (response.success) {
						// Update buttons immediately if we know the new status
						if (newStatus) {
							self.updateButtons(newStatus);
						}

						if (callback) {
							callback();
						}
						// Trigger immediate heartbeat update for full state sync
						wp.heartbeat.connectNow();
					} else {
						alert(response.data.message || tdwpLiveControl.i18n.error);
					}
				},
				error: function () {
					$button.prop('disabled', false).removeClass('is-busy');
					alert(tdwpLiveControl.i18n.error);
				}
			});
		},

		/**
		 * Update UI with live state
		 *
		 * @since 3.1.0
		 */
		updateUI: function (state) {
			var $active = $('.tdwp-tournament-active');

			// Update time
			var $timeValue = $active.find('.tdwp-time-value');
			$timeValue.text(this.formatTime(state.time_remaining));
			$timeValue.attr('data-seconds', state.time_remaining);

			// Update level
			$active.find('.tdwp-clock-level').text('Level ' + state.current_level);

			// Update status
			var $status = $active.find('.tdwp-clock-status');
			$status.removeClass('status-running status-paused status-break status-completed');
			$status.addClass('status-' + state.status);
			$status.text(state.status.charAt(0).toUpperCase() + state.status.slice(1));

			// Update statistics
			$active.find('.tdwp-stat-value').eq(0).text(state.total_players);
			$active.find('.tdwp-stat-value').eq(1).text(state.remaining_players);
			$active.find('.tdwp-stat-value').eq(2).text(state.total_rebuys);
			$active.find('.tdwp-stat-value').eq(3).text(state.total_addons);
			$active.find('.tdwp-stat-value').eq(4).text('$' + parseFloat(state.prize_pool).toFixed(2));

			// Update buttons based on status
			this.updateButtons(state.status);
		},

		/**
		 * Update control buttons based on status
		 *
		 * @since 3.1.0
		 */
		updateButtons: function (status) {
			var $buttons = $('.tdwp-control-buttons');

			// Hide all first
			$buttons.find('.tdwp-pause-btn, .tdwp-resume-btn').hide();

			// Show appropriate button
			if (status === 'running') {
				$buttons.find('.tdwp-pause-btn').show();
			} else if (status === 'paused') {
				$buttons.find('.tdwp-resume-btn').show();
			}
		},

	/**
	 * Start local countdown timer
	 *
	 * Updates display every second between Heartbeat syncs
	 *
	 * @since 3.1.0
	 */
	startLocalCountdown: function () {
		var self = this;

		setInterval(function () {
			var $timeValue = $('.tdwp-time-value');
			var currentSeconds = parseInt($timeValue.attr('data-seconds'), 10);

			// Only countdown if running status
			var $status = $('.tdwp-clock-status');
			if ($status.hasClass('status-running') && currentSeconds > 0) {
				currentSeconds--;
				$timeValue.attr('data-seconds', currentSeconds);
				$timeValue.text(self.formatTime(currentSeconds));
			}
		}, 1000); // Every 1 second
	},

		/**
		 * Update event log
		 *
		 * @since 3.1.0
		 */
		updateEventLog: function (events) {
			var $container = $('.tdwp-events-container');
			var $list = $container.find('.tdwp-events-list');

			if (!$list.length) {
				$container.find('.tdwp-no-events').remove();
				$list = $('<ul class="tdwp-events-list"></ul>');
				$container.append($list);
			}

			// Clear current events
			$list.empty();

			// Add new events
			$.each(events, function (index, event) {
				var eventType = '';
				if (event.includes('Started')) {
					eventType = 'tournament_started';
				} else if (event.includes('Paused')) {
					eventType = 'tournament_paused';
				} else if (event.includes('Completed')) {
					eventType = 'tournament_completed';
				} else if (event.includes('Advanced')) {
					eventType = 'level_advanced';
				}

				$list.append(
					'<li class="tdwp-event-item event-type-' + eventType + '">' + event + '</li>'
				);
			});
		},

		/**
		 * Format time in MM:SS
		 *
		 * @since 3.1.0
		 */
		formatTime: function (seconds) {
			seconds = parseInt(seconds, 10);
			var minutes = Math.floor(seconds / 60);
			var secs = seconds % 60;

			return this.pad(minutes) + ':' + this.pad(secs);
		},

		/**
		 * Pad number with leading zero
		 *
		 * @since 3.1.0
		 */
		pad: function (num) {
			return (num < 10 ? '0' : '') + num;
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		TDWPLiveControl.init();
	});

})(jQuery);
