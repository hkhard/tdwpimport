/**
 * Tournament Clock Sound Manager
 *
 * Small helper that preloads and plays notification sounds for the
 * live tournament clock. No third-party dependencies.
 *
 * @package Poker_Tournament_Import
 * @since 3.9.2
 */

(function (window) {
	'use strict';

	if (typeof window.Audio === 'undefined') {
		// No Audio support in this environment; provide a harmless no-op API.
		window.TDWPSoundManager = {
			preload: function () {},
			play: function () {}
		};
		return;
	}

	var sounds = {};

	/**
	 * Preload a set of named sound URLs.
	 *
	 * @param {Object} urls Map of name => URL.
	 */
	function preload(urls) {
		if (!urls) {
			return;
		}

		var name;
		for (name in urls) {
			if (Object.prototype.hasOwnProperty.call(urls, name)) {
				try {
					var audio = new window.Audio(urls[name]);
					audio.preload = 'auto';
					sounds[name] = audio;
				} catch (e) {
					// Ignore - environment may not support Audio construction.
				}
			}
		}
	}

	/**
	 * Play a preloaded sound by name.
	 *
	 * @param {string} name Sound name.
	 */
	function play(name) {
		if (!name || !sounds[name]) {
			return;
		}

		var audio = sounds[name];

		try {
			audio.currentTime = 0;
			var playPromise = audio.play();
			if (playPromise && typeof playPromise.catch === 'function') {
				playPromise.catch(function () {});
			}
		} catch (e) {
			// Swallow - autoplay may be blocked or audio unavailable.
		}
	}

	window.TDWPSoundManager = {
		preload: preload,
		play: play
	};

})(window);
