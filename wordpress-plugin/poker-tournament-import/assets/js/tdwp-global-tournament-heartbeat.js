/**
 * Global Tournament Heartbeat
 *
 * Keeps tournament clock ticking across all admin pages using WordPress Heartbeat API.
 * Uses local 1-second countdown with Page Visibility API for seamless transitions.
 * Only runs when user has an active tournament.
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function ($) {
	'use strict';

	// Only run if user has active tournament
	if (!tdwpGlobalHeartbeat || !tdwpGlobalHeartbeat.activeTournamentId) {
		return;
	}

	// Local state for smooth countdown
	var currentState = {
		status: 'setup',
		current_level: 1,
		time_remaining: 0
	};
	var intervalId = null;

	/**
	 * Start local countdown (1-second updates)
	 *
	 * @since 3.1.0
	 */
	function startLocalClock() {
		if (intervalId) {
			clearInterval(intervalId);
		}

		intervalId = setInterval(function() {
			if (currentState.status === 'running' && currentState.time_remaining > 0) {
				currentState.time_remaining--;
				updateAdminBar(currentState);

				if (currentState.time_remaining === 0) {
					// Level complete - will be handled by next heartbeat
					stopLocalClock();
				}
			}
		}, 1000);
	}

	/**
	 * Stop local countdown
	 *
	 * @since 3.1.0
	 */
	function stopLocalClock() {
		if (intervalId) {
			clearInterval(intervalId);
			intervalId = null;
		}
	}

	/**
	 * Page Visibility API - sync on tab focus
	 *
	 * When user returns to admin page, force immediate heartbeat sync
	 * to eliminate time jumps when switching views.
	 *
	 * @since 3.1.0
	 */
	if (typeof document.hidden !== 'undefined') {
		document.addEventListener('visibilitychange', function() {
			if (!document.hidden && typeof wp !== 'undefined' && wp.heartbeat) {
				// User returned to page - force immediate sync
				wp.heartbeat.connectNow();
			}
		});
	}

	/**
	 * Send tournament ID with every heartbeat
	 *
	 * WordPress Heartbeat runs every 60 seconds on admin pages.
	 * This ensures tournament clock ticks even when user is on other pages.
	 *
	 * @since 3.1.0
	 */
	$(document).on('heartbeat-send', function (e, data) {
		data.tdwp_tournament_id = tdwpGlobalHeartbeat.activeTournamentId;
	});

	/**
	 * Update admin bar when heartbeat returns
	 *
	 * Server returns updated tournament state (status, level, time, etc.).
	 * Update local state and restart countdown if running.
	 *
	 * @since 3.1.0
	 */
	$(document).on('heartbeat-tick', function (e, data) {
		if (data.tdwp_live_state) {
			// Update local state
			currentState = data.tdwp_live_state;

			// Update display
			updateAdminBar(currentState);

			// Start/stop local countdown based on status
			if (currentState.status === 'running') {
				startLocalClock();
			} else {
				stopLocalClock();
			}
		}
	});

	/**
	 * Update admin bar widget with current state
	 *
	 * @since 3.1.0
	 * @param {Object} state Tournament state from server.
	 */
	function updateAdminBar(state) {
		var $statusEl = $('#wp-admin-bar-tdwp-active-tournament .tdwp-status');

		if (!$statusEl.length) {
			return;
		}

		var statusText = getStatusText(state.status, state.current_level);
		$statusEl.text(statusText);
	}

	/**
	 * Get formatted status text
	 *
	 * @since 3.1.0
	 * @param {string} status Tournament status (running, paused, break, setup).
	 * @param {number} level Current level number.
	 * @return {string} Formatted status text.
	 */
	function getStatusText(status, level) {
		switch (status) {
			case 'running':
				return tdwpGlobalHeartbeat.i18n.level.replace('%d', level);

			case 'paused':
				return tdwpGlobalHeartbeat.i18n.paused.replace('%d', level);

			case 'break':
				return tdwpGlobalHeartbeat.i18n.break;

			case 'setup':
				return tdwpGlobalHeartbeat.i18n.setup;

			default:
				return status.charAt(0).toUpperCase() + status.slice(1);
		}
	}

})(jQuery);
