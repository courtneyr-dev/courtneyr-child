/**
 * Interactivity API store for collapsible callouts.
 *
 * Pattern source: patterns/cr-callout-note.php, cr-callout-warn.php
 * Markup is rewritten server-side by inc/callout-interactivity.php which
 * injects the data-wp-* directives onto the rendered block HTML so the
 * patterns themselves stay simple wp:group structures editable in the
 * block editor without IA syntax leaking into the editor canvas.
 *
 * Local context per callout: { open: true } — clicking the label-button
 * flips it. The .cr-callout__body element gets `is-collapsed` via
 * wp-class binding, which CSS uses to hide-or-reveal the content.
 */

import { store, getContext } from '@wordpress/interactivity';

store( 'courtneyr/callout', {
	state: {
		// Computed: drives the wp-class--is-collapsed binding on the
		// outer wrapper. Negation done here in JS rather than inline
		// because IA directive values are callable references, not
		// arbitrary JS expressions.
		get isCollapsed() {
			return ! getContext().open;
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			ctx.open = ! ctx.open;
		},
	},
} );
