/**
 * The Hack Repair Guy's Plugin Detective: Tools page helpers.
 *
 * @author Jim Walker, The Hack Repair Guy
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '[data-hrpd-copy]' );
		if ( ! btn ) {
			return;
		}
		const field = document.getElementById( btn.getAttribute( 'data-hrpd-copy' ) );
		if ( ! field ) {
			return;
		}
		const done = function () {
			const before = btn.textContent;
			btn.textContent = ( window.hrpdAdmin && window.hrpdAdmin.copied ) || 'Copied';
			setTimeout( function () {
				btn.textContent = before;
			}, 2000 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( field.value ).then( done );
			return;
		}
		field.select();
		try {
			document.execCommand( 'copy' );
		} catch ( err ) {
			// The report stays selected so it can be copied by hand.
		}
		done();
	} );
}() );
