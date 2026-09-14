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
 */
( function () {
	'use strict';

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
