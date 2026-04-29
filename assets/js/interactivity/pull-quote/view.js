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

import { store, getContext, withSyncEvent } from '@wordpress/interactivity';

store( 'courtneyr/pull-quote', {
	state: {
		// v0.5.32 — string the visually-hidden status span renders. AT
		// announces the value when it transitions from "" to non-empty
		// because the span carries aria-live="polite". Empty default
		// keeps the page silent until the user actually copies.
		get copyStatus() {
			return getContext().copied ? 'Quote copied to clipboard' : '';
		},
	},
	actions: {
		// v0.5.39 — WP 7.0 changed `data-wp-on--click` to fire actions
		// asynchronously by default (was sync in 6.6). The Clipboard
		// API requires synchronous user activation: by the time an
		// async handler runs, browsers have lost the "user gesture"
		// flag and `navigator.clipboard.writeText()` rejects silently.
		// withSyncEvent() forces this action to run synchronously
		// inside the click event so clipboard write succeeds.
		copyQuote: withSyncEvent( ( event ) => {
			const btn = event.currentTarget;
			// v0.5.37 — was searching for `.wp-block-quote` as an ancestor
			// of the button, which never matched: in the IA-injected
			// markup, the button is a SIBLING of the blockquote (both
			// nested inside `.cr-pull-quote-wrap`). closest() walks up,
			// so it never reached the sibling. Walk to the IA wrapper
			// instead and query down for the blockquote's first <p>.
			const wrapper = btn.closest( '.cr-pull-quote-wrap, .cr-pull-quote, .wp-block-quote' );
			if ( ! wrapper ) return;
			const para = wrapper.querySelector( 'blockquote p, .wp-block-quote p, p' );
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
		} ),
	},
} );
