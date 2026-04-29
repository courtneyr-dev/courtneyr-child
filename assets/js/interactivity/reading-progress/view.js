/**
 * Interactivity API store for the single-post reading-progress bar.
 *
 * Markup: a thin fixed-position div at the top of single posts (see
 * templates/single.html). Listens to window scroll, computes how
 * much of the page has been scrolled past, and updates context.progress
 * (0–100). The wp-style--width binding paints the bar's width as a
 * percentage so the user gets a visual cue for how far through the
 * post they are.
 *
 * No store state needed — context per element + a derived state
 * getter that formats the number as a percentage string.
 */

import { store, getContext } from '@wordpress/interactivity';

store( 'courtneyr/reading-progress', {
	state: {
		// Computed: drives wp-style--width. The directive expects a
		// CSS value, so we format the 0–100 number here rather than
		// inlining the math in the directive expression.
		get progressWidth() {
			return getContext().progress + '%';
		},
	},
	actions: {
		updateProgress() {
			const ctx     = getContext();
			const scrolled = window.scrollY;
			const max      = document.documentElement.scrollHeight - window.innerHeight;
			ctx.progress   = max > 0 ? Math.min( 100, ( scrolled / max ) * 100 ) : 0;
		},
	},
	callbacks: {
		// Run once on init so the bar reflects the current scroll
		// position on page load (e.g. when a user lands on a
		// fragment URL that scrolls to mid-page).
		init() {
			const ctx     = getContext();
			const scrolled = window.scrollY;
			const max      = document.documentElement.scrollHeight - window.innerHeight;
			ctx.progress   = max > 0 ? Math.min( 100, ( scrolled / max ) * 100 ) : 0;
		},
	},
} );
