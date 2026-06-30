/**
 * Tournament event sound player (tdwp-ee1.1).
 *
 * Plays a sound when a tournament event occurs. The event→URL map is provided
 * via the localized `tdwpSounds` object. Other code triggers a sound either by
 * calling window.tdwpPlayEventSound('player_busted') or by dispatching a
 * `tdwp:event` CustomEvent with detail.type set to the event type.
 */
( function () {
	'use strict';

	var map = ( window.tdwpSounds && window.tdwpSounds.map ) || {};
	var enabled = ! ( window.tdwpSounds && window.tdwpSounds.muted );
	var cache = {};

	function getAudio( url ) {
		if ( ! cache[ url ] ) {
			cache[ url ] = new Audio( url );
			cache[ url ].preload = 'auto';
		}
		return cache[ url ];
	}

	function playEventSound( eventType ) {
		if ( ! enabled || ! eventType || ! map[ eventType ] ) {
			return false;
		}
		try {
			var audio = getAudio( map[ eventType ] );
			audio.currentTime = 0;
			// play() returns a promise in modern browsers; ignore autoplay
			// rejections (no user gesture yet) rather than throwing.
			var p = audio.play();
			if ( p && typeof p.catch === 'function' ) {
				p.catch( function () {} );
			}
			return true;
		} catch ( e ) {
			return false;
		}
	}

	// Public API.
	window.tdwpPlayEventSound = playEventSound;
	window.tdwpSetSoundEnabled = function ( on ) {
		enabled = !! on;
	};

	// Event-driven trigger: document.dispatchEvent(new CustomEvent('tdwp:event', { detail: { type: 'player_busted' } }))
	document.addEventListener( 'tdwp:event', function ( e ) {
		if ( e && e.detail && e.detail.type ) {
			playEventSound( e.detail.type );
		}
	} );
} )();
