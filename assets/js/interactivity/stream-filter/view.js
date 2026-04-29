/**
 * Interactivity API store for the cr-stream-loop format filter.
 *
 * Pattern source: patterns/cr-stream-loop.php
 * The pattern wraps its query loop in a .cr-stream-filter container
 * with chip buttons above. Clicking a chip sets context.format; the
 * wrapper's data-active-format attribute (bound via wp-bind) drives
 * CSS attribute selectors that hide non-matching post-template li's
 * — no per-item directives, no DOM mutation, just one attribute on
 * the wrapper that CSS reads.
 *
 * No store-level state needed: each filter instance has its own
 * context (so multiple stream loops on one page would each filter
 * independently).
 */

import { store, getContext } from '@wordpress/interactivity';

store( 'courtneyr/stream-filter', {
	actions: {
		setFormat( event ) {
			const ctx = getContext();
			// Use currentTarget so clicks landing on inner elements
			// (the chip text span, etc.) still read the button's
			// data-format attribute.
			ctx.format = event.currentTarget.dataset.format || '';

			// v0.5.32 — toggle aria-pressed across the chip group so
			// screen readers announce the active filter. CSS-only
			// active state (via [data-active-format] attribute on the
			// wrapper) was invisible to AT — this exposes it.
			const group = event.currentTarget.closest( '.cr-stream-filter__chips' );
			if ( group ) {
				group.querySelectorAll( '.cr-stream-filter__chip' ).forEach( ( btn ) => {
					btn.setAttribute(
						'aria-pressed',
						btn === event.currentTarget ? 'true' : 'false'
					);
				} );
			}
		},
	},
} );
