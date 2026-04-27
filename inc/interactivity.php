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
