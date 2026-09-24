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
 * state.resultsLabel backs a visually-hidden status paragraph so AT
 * users get an equivalent of the CSS-only visible/hidden filtering
 * (the count of currently-shown items), each filter instance's own
 * context still keeping multiple stream loops independent.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

// A default/standard post's post_class carries "format-standard", not
// "format-blog" — the same mapping components.css's filter-visibility
// rule (~L6560) uses to spare both classes from the "Blog" chip.
const FORMAT_CLASSES = {
	blog: [ 'format-blog', 'format-standard' ],
	quote: [ 'format-quote' ],
	link: [ 'format-link' ],
	gallery: [ 'format-gallery' ],
	video: [ 'format-video' ],
};

store( 'courtneyr/stream-filter', {
	state: {
		// Visible-result count for the screen-reader-only status
		// paragraph. The filter itself stays CSS-only (no per-item
		// directives); this reads the same DOM the CSS hides from,
		// counting the currently-visible items so AT users get an
		// equivalent of what the CSS attribute-selector filter shows.
		get resultsLabel() {
			const { format } = getContext();
			const { ref } = getElement();
			const root = ref.closest( '.cr-stream-filter' );
			if ( ! root ) {
				return '';
			}
			const items   = root.querySelectorAll( '.wp-block-post-template > li' );
			const classes = FORMAT_CLASSES[ format ] || null;
			let count = 0;
			items.forEach( ( li ) => {
				if ( ! classes || classes.some( ( c ) => li.classList.contains( c ) ) ) {
					count++;
				}
			} );
			return count + ( 1 === count ? ' post shown' : ' posts shown' );
		},
	},
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
