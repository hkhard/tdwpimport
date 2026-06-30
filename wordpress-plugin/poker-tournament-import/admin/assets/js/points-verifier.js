/**
 * Points verification page interactions.
 *
 * Previews and applies a selected points formula via AJAX. Expects the
 * localized `tdwpPV` object (ajaxUrl, nonce, strings).
 */
( function ( $ ) {
	'use strict';

	function selectedFormula( $root ) {
		return $root.find( 'input[name="tdwp_pv_formula"]:checked' ).val() || '';
	}

	function renderPreview( $panel, data ) {
		var rows = '';
		$.each( data.players, function ( _i, p ) {
			var preview = p.error ? tdwpPV.strings.error : p.preview;
			var delta = ( null === p.delta || p.error ) ? '&mdash;' : ( p.delta > 0 ? '+' + p.delta : p.delta );
			var deltaClass = '';
			if ( ! p.error && null !== p.delta ) {
				deltaClass = p.delta > 0 ? 'tdwp-pv-up' : ( p.delta < 0 ? 'tdwp-pv-down' : '' );
			}
			var previewClass = ( ! p.error && p.preview < 0 ) ? 'tdwp-pv-negative' : '';
			rows +=
				'<tr><td>' + p.finish_position + '</td><td>' + $( '<div>' ).text( p.name ).html() +
				'</td><td>' + p.current + '</td><td class="' + previewClass + '">' + preview +
				'</td><td class="' + deltaClass + '">' + delta + '</td></tr>';
		} );

		var sevClass = 'tdwp-pv-badge-' + data.health.severity;
		var banner = data.health.messages.length
			? '<div class="tdwp-pv-preview-health ' + sevClass + '"><ul><li>' +
				data.health.messages.map( function ( m ) {
					return $( '<div>' ).text( m ).html();
				} ).join( '</li><li>' ) + '</li></ul></div>'
			: '';
		var estimated = data.estimated
			? '<p class="tdwp-pv-estimated"><em>' + tdwpPV.strings.estimated + '</em></p>'
			: '';

		$panel.html(
			banner + estimated +
			'<table class="widefat striped"><thead><tr>' +
			'<th>' + tdwpPV.strings.pos + '</th>' +
			'<th>' + tdwpPV.strings.player + '</th>' +
			'<th>' + tdwpPV.strings.current + '</th>' +
			'<th>' + tdwpPV.strings.preview + '</th>' +
			'<th>' + tdwpPV.strings.delta + '</th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>' +
			'<p class="tdwp-pv-sum">' + tdwpPV.strings.previewSum + ' <strong>' + data.health.sum + '</strong></p>'
		).removeAttr( 'hidden' );
	}

	$( function () {
		var $root = $( '.tdwp-pv-selector' );
		if ( ! $root.length ) {
			return;
		}
		var postId = $root.data( 'post-id' );
		var $panel = $root.find( '.tdwp-pv-preview-panel' );
		var $spinner = $root.find( '.tdwp-pv-spinner' );
		var $applyBtn = $root.find( '.tdwp-pv-apply-btn' );

		// Re-selecting a formula invalidates the prior preview.
		$root.on( 'change', 'input[name="tdwp_pv_formula"]', function () {
			$applyBtn.prop( 'disabled', true );
			$panel.attr( 'hidden', true ).empty();
		} );

		$root.on( 'click', '.tdwp-pv-preview-btn', function () {
			var formula = selectedFormula( $root );
			if ( ! formula ) {
				return;
			}
			$spinner.addClass( 'is-active' );
			$.post( tdwpPV.ajaxUrl, {
				action: 'tdwp_pv_preview_formula',
				nonce: tdwpPV.nonce,
				post_id: postId,
				formula_key: formula
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					renderPreview( $panel, resp.data );
					$applyBtn.prop( 'disabled', false );
				} else {
					window.alert( ( resp && resp.data && resp.data.message ) || tdwpPV.strings.error );
				}
			} ).fail( function () {
				window.alert( tdwpPV.strings.error );
			} ).always( function () {
				$spinner.removeClass( 'is-active' );
			} );
		} );

		$root.on( 'click', '.tdwp-pv-apply-btn', function () {
			var formula = selectedFormula( $root );
			if ( ! formula || ! window.confirm( tdwpPV.strings.confirmApply ) ) {
				return;
			}
			$spinner.addClass( 'is-active' );
			$applyBtn.prop( 'disabled', true );
			$.post( tdwpPV.ajaxUrl, {
				action: 'tdwp_pv_apply_formula',
				nonce: tdwpPV.nonce,
				post_id: postId,
				formula_key: formula
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					window.alert( resp.data.message );
					window.location.reload();
				} else {
					window.alert( ( resp && resp.data && resp.data.message ) || tdwpPV.strings.error );
					$applyBtn.prop( 'disabled', false );
				}
			} ).fail( function () {
				window.alert( tdwpPV.strings.error );
				$applyBtn.prop( 'disabled', false );
			} ).always( function () {
				$spinner.removeClass( 'is-active' );
			} );
		} );
	} );
} )( jQuery );
