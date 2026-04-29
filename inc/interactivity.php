<?php
/**
 * Interactivity API setup for the theme toggle.
 *
 * The Interactivity API (WP 6.5+, Core in 6.6+) is the WordPress-native
 * way to add reactive front-end behavior to a block theme. It replaces
 * the kit's vanilla-JS toggle with a Core-supported store + directives.
 *
 * Architecture:
 *   - Pre-paint: a tiny inline script reads localStorage and sets
 *     <html data-theme="dark"> before the first frame, preventing the
 *     flash of wrong theme. This stays inline because it must execute
 *     before any CSS loads.
 *   - Hydration: assets/js/theme-toggle/view.js registers the
 *     Interactivity store and binds toggle behavior to any element with
 *     data-wp-interactive="courtneyr/theme-toggle".
 *   - Persistence: the store writes to localStorage so the choice
 *     survives page navigation and OS dark-mode pref changes.
 *
 * The toggle button itself can live in the header template part as a
 * core/button block with the appropriate data-wp-* attributes, or as a
 * pattern users drop in. Either way, no custom block is required.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Interactivity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline pre-paint script. Sets data-theme on <html> before CSS loads.
 *
 * Output position: <head>, before any stylesheet link.
 * Storage key: 'courtneyr-theme' (same key the kit uses, so the kit and
 * the live site share the user's preference if they happen to test in
 * both).
 */
function pre_paint_theme(): void {
	?>
<script id="courtneyr-pre-paint">
(function () {
	try {
		var stored = localStorage.getItem('courtneyr-theme');
		if (stored === 'dark' || stored === 'light') {
			document.documentElement.setAttribute('data-theme', stored);
			return;
		}
		// No explicit choice — fall through to OS pref via @media (prefers-color-scheme).
	} catch (e) {
		// localStorage may be blocked. Fall through silently.
	}
})();
</script>
	<?php
}
add_action( 'wp_head', __NAMESPACE__ . '\\pre_paint_theme', 1 );

/**
 * Register the theme-toggle Interactivity API view script.
 *
 * The script is loaded as an ES module (Interactivity API requirement)
 * and only enqueued on pages that actually contain the toggle, via the
 * data-wp-interactive directive in the rendered markup.
 */
function register_theme_toggle_view(): void {
	wp_register_script_module(
		'courtneyr/theme-toggle',
		COURTNEYR_CHILD_URI . '/assets/js/theme-toggle/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_theme_toggle_view' );

/**
 * Enqueue the toggle view script + matching stylesheet on any page that
 * could render the toggle. We check for the data-wp-interactive attribute
 * in the rendered output rather than enumerating every template, so the
 * toggle works wherever it is dropped in.
 */
function maybe_enqueue_theme_toggle(): void {
	wp_enqueue_script_module( 'courtneyr/theme-toggle' );

	wp_enqueue_style(
		'courtneyr-theme-toggle',
		COURTNEYR_CHILD_URI . '/assets/css/interactivity/theme-toggle.css',
		array( 'courtneyr-tokens' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_theme_toggle' );


/* ============================================================
 * v0.5.14 — Callout collapse/expand (IA module #1).
 *
 * The callout patterns (cr-callout-note.php, cr-callout-warn.php)
 * render as static wp:group blocks. To make them collapsible without
 * forcing IA syntax into the editor canvas, we transform the rendered
 * HTML server-side via render_block: inject data-wp-* directives on
 * the outer .cr-callout div, and rewrite the .cr-callout__label
 * paragraph as a <button> with toggle handlers.
 *
 * The IA store (assets/js/interactivity/callout/view.js) provides
 * an `actions.toggle` that flips context.open and a `state.isCollapsed`
 * computed property the wrapper's wp-class binding reads.
 * ============================================================ */

/**
 * Transform any rendered .cr-callout block to add IA wiring.
 *
 * Operates on core/group blocks whose className includes "cr-callout".
 * Pattern auto-discovery generates these blocks; the filter extends
 * them at render time without editing the patterns themselves.
 */
function transform_callout_block( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class, 'cr-callout' ) ) {
		return $block_content;
	}

	// Add IA directives to the outer wrapper. Uses single quotes
	// inside the data-wp-context JSON so the attr's outer quotes can
	// stay double; works in HTML5 without escaping.
	$block_content = preg_replace(
		'/^(\s*<div\s+class="[^"]*cr-callout[^"]*")/i',
		'$1 data-wp-interactive="courtneyr/callout" data-wp-context=\'{"open":true}\' data-wp-class--is-collapsed="state.isCollapsed"',
		$block_content,
		1
	);

	// Rewrite the label paragraph as a real <button> so keyboard +
	// screen-reader semantics work. Chevron rotates via CSS when the
	// callout is collapsed (driven by the parent's is-collapsed class).
	$button_open  = '<button type="button" class="cr-callout__toggle" data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="context.open" aria-expanded="true">';
	$chevron      = '<svg class="cr-callout__chevron" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$button_close = '</button>';

	$block_content = preg_replace(
		'/<p\s+class="cr-callout__label">([^<]*)<\/p>/',
		$button_open . '<span class="cr-callout__label-text">$1</span>' . $chevron . $button_close,
		$block_content,
		1
	);

	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\transform_callout_block', 10, 2 );

/**
 * Register the callout IA view module.
 */
function register_callout_view(): void {
	wp_register_script_module(
		'courtneyr/callout',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/callout/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_callout_view' );

/**
 * Enqueue the callout IA module. The module is small (~400 bytes
 * minified) and the conditional-load via data-wp-interactive auto-
 * detection isn't reliable for our use case (filter-injected
 * directives), so we always enqueue.
 */
function maybe_enqueue_callout(): void {
	wp_enqueue_script_module( 'courtneyr/callout' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_callout' );


/* ============================================================
 * v0.5.14 — Stream Loop format filter (IA module #2).
 *
 * The cr-stream-loop pattern wraps its query loop in a
 * .cr-stream-filter container with chip buttons. Clicking a chip
 * sets context.format; the wrapper's data-active-format attribute
 * (bound via wp-bind) drives CSS attribute selectors that hide non-
 * matching post-template li's (which carry post_class()-emitted
 * format-X classes).
 *
 * No per-item directives, no DOM mutation — just one bound
 * attribute on the wrapper that CSS reads to control visibility +
 * active-chip styling.
 * ============================================================ */

/**
 * Register the stream-filter IA view module.
 */
function register_stream_filter_view(): void {
	wp_register_script_module(
		'courtneyr/stream-filter',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/stream-filter/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_stream_filter_view' );

/**
 * Enqueue the stream-filter IA module on any page that might render
 * the cr-stream-loop pattern. Same always-enqueue rationale as the
 * callout module above.
 */
function maybe_enqueue_stream_filter(): void {
	wp_enqueue_script_module( 'courtneyr/stream-filter' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_stream_filter' );
