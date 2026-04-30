/**
 * Site UX — small interactions that don't warrant the full Interactivity API.
 *
 * v0.5.49: two a11y polish behaviors:
 *
 * 1. Header search disclosure (B-5 / WCAG 2.1.1): native <details> closes only
 *    when its <summary> is clicked. Add Escape-to-close + click-outside-to-close
 *    so keyboard and AT users can dismiss the open search without re-clicking
 *    the toggle.
 *
 * 2. Complianz close button (B-7 / WCAG 4.1.2): the plugin renders close as
 *    <div tabindex="0" role="button"> instead of <button>. Native <button> handles
 *    Space-key activation; <div role="button"> needs explicit JS. Trigger click()
 *    on Space to match expected button semantics.
 */
( function () {
	'use strict';

	// 1. Header search disclosure UX
	const search = document.querySelector( 'details.site-header__search' );
	if ( search ) {
		// Escape closes when open
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && search.open ) {
				search.open = false;
				const toggle = search.querySelector( 'summary' );
				if ( toggle ) {
					toggle.focus();
				}
			}
		} );

		// Click outside closes
		document.addEventListener( 'click', function ( e ) {
			if ( ! search.open ) return;
			if ( ! search.contains( e.target ) ) {
				search.open = false;
			}
		} );
	}

	// 2. Complianz close button — Space key activation
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
