/**
 * Points adjustments page interactions (tdwp-31i).
 *
 * Loads a tournament's players into the override form and saves an override
 * via AJAX. Expects the localized `tdwpPA` object.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $tournament = $( '#tdwp-pa-tournament' );
		var $player = $( '#tdwp-pa-player' );
		var $points = $( '#tdwp-pa-points' );
		var $reason = $( '#tdwp-pa-reason' );
		var $save = $( '#tdwp-pa-save' );
		var $spinner = $( '#tdwp-pa-spinner' );

		if ( ! $tournament.length ) {
			return;
		}

		$tournament.on( 'change', function () {
			var postId = $tournament.val();
			$player.prop( 'disabled', true ).html( '<option value="">…</option>' );
			if ( ! postId ) {
				return;
			}
			$.post( tdwpPA.ajaxUrl, {
				action: 'tdwp_get_tournament_players_for_adjustment',
				nonce: tdwpPA.nonce,
				post_id: postId
			} ).done( function ( resp ) {
				if ( ! resp || ! resp.success ) {
					window.alert( ( resp && resp.data && resp.data.message ) || tdwpPA.strings.error );
					return;
				}
				var opts = '<option value="">' + tdwpPA.strings.selectPlayer + '</option>';
				$.each( resp.data.players, function ( _i, p ) {
					var label = p.finish_position + '. ' + p.name + ' (' + tdwpPA.strings.current + ': ' + p.current_points;
					if ( null !== p.override ) {
						label += ', ' + tdwpPA.strings.override + ': ' + p.override;
					}
					label += ')';
					opts += '<option value="' + $( '<div>' ).text( p.player_uuid ).html() + '" data-current="' + p.current_points + '">' +
						$( '<div>' ).text( label ).html() + '</option>';
				} );
				$player.html( opts ).prop( 'disabled', false );
			} ).fail( function () {
				window.alert( tdwpPA.strings.error );
			} );
		} );

		$player.on( 'change', function () {
			var current = $player.find( ':selected' ).data( 'current' );
			if ( undefined !== current && '' === $points.val() ) {
				$points.val( current );
			}
		} );

		$save.on( 'click', function () {
			var postId = $tournament.val();
			var playerUuid = $player.val();
			var reason = $.trim( $reason.val() );
			if ( ! postId || ! playerUuid ) {
				window.alert( tdwpPA.strings.selectPlayer );
				return;
			}
			if ( ! reason ) {
				window.alert( tdwpPA.strings.needReason );
				return;
			}
			if ( ! window.confirm( tdwpPA.strings.confirm ) ) {
				return;
			}
			$spinner.addClass( 'is-active' );
			$save.prop( 'disabled', true );
			$.post( tdwpPA.ajaxUrl, {
				action: 'tdwp_save_points_adjustment',
				nonce: tdwpPA.nonce,
				post_id: postId,
				player_uuid: playerUuid,
				new_points: $points.val(),
				reason: reason
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					window.alert( $( '<div>' ).html( resp.data.message ).text() );
					window.location.reload();
				} else {
					window.alert( ( resp && resp.data && resp.data.message ) || tdwpPA.strings.error );
					$save.prop( 'disabled', false );
				}
			} ).fail( function () {
				window.alert( tdwpPA.strings.error );
				$save.prop( 'disabled', false );
			} ).always( function () {
				$spinner.removeClass( 'is-active' );
			} );
		} );
	} );
} )( jQuery );
