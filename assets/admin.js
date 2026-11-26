/* AWD Client Mailer admin scripts. */
jQuery( function ( $ ) {
	'use strict';

	// Confirm destructive actions.
	$( document ).on( 'click', '[data-awdcm-confirm]', function ( e ) {
		if ( ! window.confirm( $( this ).data( 'awdcm-confirm' ) ) ) {
			e.preventDefault();
		}
	} );

	// Colour picker.
	if ( $.fn.wpColorPicker ) {
		$( '.awdcm-color' ).wpColorPicker();
	}

	// Logo picker.
	var frame;
	$( '#awdcm-logo-select' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( frame ) {
			frame.open();
			return;
		}
		frame = wp.media( { title: 'Choose a logo', library: { type: 'image' }, multiple: false, button: { text: 'Use this logo' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
			$( '#awdcm-logo-id' ).val( a.id );
			$( '#awdcm-logo-preview' ).empty().append( $( '<img>' ).attr( { src: url, alt: '' } ) );
		} );
		frame.open();
	} );
	$( '#awdcm-logo-remove' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#awdcm-logo-id' ).val( '0' );
		$( '#awdcm-logo-preview' ).empty();
	} );

	// Recipients: show tag list only when sending to tags.
	function toggleTags() {
		var mode = $( 'input[name="recipients_mode"]:checked' ).val();
		$( '#awdcm-tag-list' ).toggle( mode === 'tags' );
	}
	$( 'input[name="recipients_mode"]' ).on( 'change', toggleTags );
	toggleTags();

	// Sending progress.
	var $p = $( '#awdcm-progress' );
	if ( $p.length && $p.data( 'status' ) === 'sending' ) {
		var poll = function () {
			$.post( AWDCM.ajax, { action: 'awdcm_process_queue', nonce: AWDCM.nonce, campaign: $p.data( 'campaign' ) } )
				.done( function ( r ) {
					if ( ! r || ! r.success ) {
						$( '#awdcm-progress-text' ).text( ( r && r.data ) || 'Something went wrong. Refresh the page.' );
						return;
					}
					var d = r.data;
					var done = d.sent + d.failed + d.skipped;
					var pct = d.total ? Math.floor( ( 100 * done ) / d.total ) : 100;
					$p.find( '.awdcm-bar span' ).css( 'width', pct + '%' );
					$( '#awdcm-n-sent' ).text( d.sent );
					$( '#awdcm-n-failed' ).text( d.failed );
					$( '#awdcm-n-skipped' ).text( d.skipped );
					$( '#awdcm-progress-text' ).text( 'Sending: ' + done + ' of ' + d.total + ' done.' );
					if ( d.status === 'sending' ) {
						setTimeout( poll, 3000 );
					} else {
						window.location.reload();
					}
				} )
				.fail( function () {
					setTimeout( poll, 10000 );
				} );
		};
		setTimeout( poll, 1000 );
	}
} );
