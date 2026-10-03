/**
 * Site UX — small interactions that don't warrant the full Interactivity API.
 *
 * Complianz close button (B-7 / WCAG 4.1.2): the plugin renders close as
 * <div tabindex="0" role="button"> instead of <button>. Native <button> handles
 * Space-key activation; <div role="button"> needs explicit JS. Trigger click()
 * on Space to match expected button semantics.
 *
 * The v0.5.49 header search disclosure handler is gone: parts/header.html
 * renders the search as a plain <form>, so `details.site-header__search`
 * never matched.
 *
 * Watch shelf covers (0.7.75): a face-out cover that fails to load drops
 * its media box so the case shows the title sleeve.
 */
( function () {
	'use strict';

	// Watch shelf covers (PKIW #227): a cover that fails to load leaves the
	// case, so the stylesheet's no-media rules show the title sleeve instead
	// of an empty recess. `error` doesn't bubble, so listen in capture.
	function dropFailedCover( img ) {
		const media = img.closest( '.cr-vhs-shelf .pk-media' );
		if ( media ) media.remove();
	}
	document.addEventListener( 'error', function ( e ) {
		if ( e.target && e.target.tagName === 'IMG' ) dropFailedCover( e.target );
	}, true );
	document.querySelectorAll( '.cr-vhs-shelf .pk-media img' ).forEach( function ( img ) {
		if ( img.complete && img.naturalWidth === 0 && ! img.classList.contains( 'perfmatters-lazy' ) ) dropFailedCover( img );
	} );

	// Complianz close button — Space key activation
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== ' ' && e.key !== 'Spacebar' ) return;
		const target = e.target;
		if ( ! target || ! target.classList ) return;
		if ( target.classList.contains( 'cmplz-close' ) && target.getAttribute( 'role' ) === 'button' ) {
			e.preventDefault();
			target.click();
		}
	} );
} )();
