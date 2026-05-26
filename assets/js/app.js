/* MBD CRM front-end shell behaviour. */
( function () {
	'use strict';

	var app = document.querySelector( '[data-mbd-app]' );
	if ( ! app ) {
		return;
	}

	var toggle = app.querySelector( '[data-mbd-toggle]' );
	var OPEN_CLASS = 'is-nav-open';

	function setOpen( open ) {
		app.classList.toggle( OPEN_CLASS, open );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			setOpen( ! app.classList.contains( OPEN_CLASS ) );
		} );
	}

	// Close when tapping the backdrop or following a nav link on mobile.
	app.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-mbd-close]' ) || event.target.closest( '[data-mbd-nav-link]' ) ) {
			setOpen( false );
		}
	} );

	// Close on Escape.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			setOpen( false );
		}
	} );
} )();
