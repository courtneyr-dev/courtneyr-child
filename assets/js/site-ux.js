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
 * its media box so the case shows the title sleeve. Listen shelf covers
 * (PKIW #226) do the same, so the cassette prints its typographic label.
 *
 * Comic covers (0.7.77): a cover that fails to load leaves its bag, and
 * the card is marked so the stylesheet prints the title on the board.
 */
( function () {
	'use strict';

	// Watch and listen shelf covers (PKIW #227, #226): a cover that fails to
	// load leaves the case, so the stylesheet's no-media rules show the
	// title sleeve or the cassette's typographic label instead of an empty
	// recess. `error` doesn't bubble, so listen in capture.
	function dropFailedCover( img ) {
		const media = img.closest( '.cr-vhs-shelf .pk-media, .pk-card.cr-cassette.cr-media--shelf .pk-media' );
		if ( media ) {
			media.remove();
			return;
		}
		// Comics (PKIW #228): the bag stays. cr-comic.css turns the board
		// into a typographic cover and, on the rack, shows the title link's
		// text there, so a comic never stands as a blank board.
		const comic = img.closest( '.pk-card.cr-comic' );
		if ( comic && img.closest( '.pk-media' ) ) {
			comic.classList.add( 'cr-comic--no-art' );
			img.remove();
			return;
		}
		// A strip on the comic rack has no bag: its picture leaves and its
		// title tag stands on the shelf.
		const strip = img.closest( '.pk-card.cr-comic-strip .pk-media' );
		if ( strip ) strip.remove();
	}
	document.addEventListener( 'error', function ( e ) {
		if ( e.target && e.target.tagName === 'IMG' ) dropFailedCover( e.target );
	}, true );
	document.querySelectorAll( '.cr-vhs-shelf .pk-media img, .pk-card.cr-cassette.cr-media--shelf .pk-media img, .pk-card.cr-comic .pk-media img, .pk-card.cr-comic-strip .pk-media img' ).forEach( function ( img ) {
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
