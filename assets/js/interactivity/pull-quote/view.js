/**
 * Interactivity API store for the pull-quote copy-to-clipboard button.
 *
 * Markup: a "Copy quote" button injected by inc/interactivity.php
 * after each blockquote inside .is-style-cr-pull-quote (or
 * .cr-pull-quote). Click → reads the quote text from the closest
 * blockquote in the DOM → writes to clipboard → flips context.copied
 * for 2 seconds so the button label swaps "Copy quote" → "Copied".
 *
 * Reading the quote text from the DOM at click time avoids
 * serializing the full quote text into context (which would balloon
 * page HTML for long quotes), and stays accurate if anyone edits
 * the quote inline.
 */

import { store, getContext } from '@wordpress/interactivity';

store( 'courtneyr/pull-quote', {
	actions: {
		copyQuote( event ) {
			const btn = event.currentTarget;
			// .closest searches up to find the wrapper that contains
			// the blockquote. Then we pull the text from the first
			// <p> inside (the quote body) — citation handled
			// separately if present.
			const wrapper = btn.closest( '.cr-pull-quote, .wp-block-quote' );
			if ( ! wrapper ) return;
			const para = wrapper.querySelector( 'p' );
			if ( ! para ) return;

			const text = para.textContent.trim();
			if ( ! text || ! navigator.clipboard ) return;

			navigator.clipboard.writeText( text ).then( () => {
				const ctx = getContext();
				ctx.copied = true;
				setTimeout( () => {
					ctx.copied = false;
				}, 2000 );
			} ).catch( () => {
				// Clipboard API failed (likely insecure context or
				// no permission). Stay silent — no fallback needed
				// for a nice-to-have feature.
			} );
		},
	},
} );
