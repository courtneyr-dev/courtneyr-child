<?php
/**
 * Enqueue strategy — per-block CSS only.
 *
 * The design system's components.css is broken up by block, then attached
 * via wp_enqueue_block_style(). WordPress will only inject the CSS when
 * the matching block actually renders on the page. This is the same
 * performance discipline Perfmatters Script Manager applies to JS, but
 * native to the block system and not dependent on any plugin.
 *
 * Adding a new block style:
 *   1. Save the CSS file in assets/css/blocks/{block-namespace}-{block-name}.css
 *      e.g. assets/css/blocks/core-quote.css
 *   2. Add an entry to BLOCK_STYLE_MAP below.
 *
 * The entire components.css from the design system is also enqueued as a
 * baseline for the front end so global tokens, typography, and utility
 * classes are available even on pages that have no blocks at all (e.g.
 * legacy posts from the 2007 archive that still use raw HTML).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Enqueue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map of WordPress block names to per-block CSS files.
 *
 * Key:   the canonical block name ("namespace/block").
 * Value: the filename inside assets/css/blocks/ (without extension).
 */
const BLOCK_STYLE_MAP = array(
	'core/button'    => 'core-button',
	'core/buttons'   => 'core-buttons',
	'core/quote'     => 'core-quote',
	'core/pullquote' => 'core-pullquote',
	'core/code'      => 'core-code',
	'core/group'     => 'core-group',
	'core/columns'   => 'core-columns',
	'core/image'     => 'core-image',
	'core/gallery'   => 'core-gallery',
	'core/separator' => 'core-separator',
	'core/heading'   => 'core-heading',
	'core/list'      => 'core-list',
	'core/table'     => 'core-table',
	'core/embed'     => 'core-embed',
);

/**
 * Enqueue the global tokens + components baseline on the front end.
 *
 * Tokens.css comes first (defines --cr-*); components.css depends on it.
 * Both are versioned with the theme version so cache-busting on deploy
 * works the same way it does for the design system kit.
 */
function enqueue_baseline(): void {
	wp_enqueue_style(
		'courtneyr-tokens',
		COURTNEYR_CHILD_URI . '/assets/css/tokens.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);

	wp_enqueue_style(
		'courtneyr-components',
		COURTNEYR_CHILD_URI . '/assets/css/components.css',
		array( 'courtneyr-tokens' ),
		COURTNEYR_CHILD_VERSION
	);

	// 404 comic layer — only loaded on the Not Found template.
	if ( is_404() ) {
		wp_enqueue_style(
			'courtneyr-404',
			COURTNEYR_CHILD_URI . '/assets/css/cr-404.css',
			array( 'courtneyr-components' ),
			COURTNEYR_CHILD_VERSION
		);
	}

	/*
	 * V0.5.76 — emit the per-format tint rules as INLINE CSS attached
	 * to the components handle. Perfmatters' Used CSS optimizer
	 * silently prunes the body.single-format-{slug} main.single-post
	 * selectors out of the optimized inline stylesheet (its analyzer
	 * doesn't seem to recognize compound body-class + element-class
	 * chains as matched). Outputting these rules as inline CSS via
	 * wp_add_inline_style sidesteps the Used CSS pipeline — inline
	 * <style> tags aren't touched.
	 *
	 * Same rules already exist in components.css for the case when
	 * Used CSS is disabled or the rule survives pruning; this is a
	 * redundant inline copy that always wins.
	 */
	wp_add_inline_style(
		'courtneyr-components',
		'body.single-format-aside main.single-post   { background-color: var(--cr-type-aside-bg); }
		body.single-format-audio main.single-post   { background-color: var(--cr-type-audio-bg); }
		body.single-format-chat main.single-post    { background-color: var(--cr-type-chat-bg); }
		body.single-format-gallery main.single-post { background-color: var(--cr-type-gallery-bg); }
		body.single-format-image main.single-post   { background-color: var(--cr-type-image-bg); }
		body.single-format-link main.single-post    { background-color: var(--cr-type-link-bg); }
		body.single-format-quote main.single-post   { background-color: var(--cr-type-quote-bg); }
		body.single-format-status main.single-post  { background-color: var(--cr-type-status-bg); }
		body.single-format-video main.single-post   { background-color: var(--cr-type-video-bg); }
		/* v0.5.77+v0.5.79 — responsive single-post body width. Below 1024px
		   (tablet portrait + phone) the theme.json defaults apply unchanged.
		   At ≥1024px, widen the single-post surface to 80vw.

		   v0.5.79 sledgehammer fix: the v0.5.77 var override
		   (--wp--style--global--content-size: 80vw at body.single) was on
		   the page but didnt visibly widen anything (image #186). The theme
		   uses its own --cr-measure (65ch) on several selectors AND WPs
		   constrained-layout selectors emit hardcoded values in some paths.
		   Targeting the post-content + its children directly with !important
		   guarantees the widening regardless of which underlying variable
		   path WP chose for this page.

		   v0.5.79 — also bump body paragraph font-size from 1rem to 1.15rem
		   (~18px) on single-post prose. Original 16px read as tiny on
		   wide-screen prose at desktop tiers. */
		/* v0.5.89 (revised v0.5.81): width strategy is now content-shape
		   aware, not site-wide. User direction (image #202 vs #203):
		   "I want some things to be closer to the older screenshot of
		   the homepage, while posts that have more content should have
		   a wider content area."

		   Translation: theme.json defaults (contentSize 720px / wideSize
		   1100px) are the right shape for the homepage and other
		   component-heavy templates — narrower, design-paced. Long-form
		   single posts read better at the wider 80vw stage where there
		   is enough room for prose plus pullquotes and image alignwide.

		   Scope: body.single ONLY. Static pages (body.page), home
		   (body.home), archives (body.archive), search, 404, etc. all
		   fall back to the theme.json 720/1100 defaults — restoring the
		   image-202 layout. Inline so Perfmatters Used CSS does not
		   prune the body.single scope. */
		@media (min-width: 1024px) {
			body.single {
				--wp--style--global--content-size: 80vw;
				--wp--style--global--wide-size: 86vw;
				--cr-measure: 80vw;
				--cr-measure-wide: 86vw;
			}
			body.single .wp-block-post-content {
				max-width: 80vw !important;
				margin-inline: auto !important;
			}
			body.single .wp-block-post-content > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
				max-width: 100% !important;
			}
			body.single .wp-block-post-content pre,
			body.single .wp-block-post-content .wp-block-code,
			body.single .wp-block-post-content .wp-block-code pre,
			body.single .wp-block-post-content .wp-block-preformatted {
				max-width: min(72ch, 100%) !important;
				margin-inline: auto !important;
			}
		}
		/* v0.5.81: hide the thin separator HRs on the homepage that
		   create faint horizontal lines (image #182, #185, #191). The
		   <hr is-style-separator-thin> elements are baked into a DB
		   template customization (wp_template post 37371). CSS-hide is
		   the non-destructive fix — when the DB customization is later
		   removed, this rule is harmless because the file template
		   has no separators. */
		body.home hr.wp-block-separator.is-style-separator-thin {
			display: none !important;
		}
		/* v0.5.82 — restore index-card sizing for groups containing
		   multiple .is-style-cr-card.is-nowrap siblings (the audience
		   cards on the homepage and similar patterns). v0.5.81 site-wide
		   width override ballooned each card to fill 80vw, stacking
		   them as horizontal bars (image #193). Put the parent in
		   flex-row+wrap and cap each card at ~25% of a 1100px container,
		   so 4 fit per row at index-card proportions. Below 1024px the
		   cards stack naturally. */
		@media (min-width: 1024px) {
			.wp-block-group:has(> .is-style-cr-card.is-nowrap) {
				display: flex !important;
				flex-direction: row !important;
				flex-wrap: wrap !important;
				gap: var(--wp--preset--spacing--medium, 1rem);
				justify-content: center;
				max-width: 1100px !important;
				margin-inline: auto !important;
			}
			.wp-block-group:has(> .is-style-cr-card.is-nowrap) > .is-style-cr-card {
				flex: 0 1 calc(25% - 0.75rem);
				max-width: 17rem;
				min-width: 14rem;
				margin: 0 !important;
			}
		}
		/* v0.5.86 — v0.5.84 prose cap rule reverted. The clamp(45ch, 90ch)
		   cap on .wp-block-post-content > * cascaded into Query Loop
		   archives (image #197 — empty boxes per post) and centered
		   prose narrowly within the 80vw wrapper, leaving dead space
		   on About and other pages (image #196). User feedback (image
		   #198): "I actually loved the width of things on home" —
		   restore the wider prose stage; revisit WCAG 1.4.8 line-length
		   compliance later via theme.json contentSize per template part
		   instead of bolt-on override CSS. */

		/* v0.5.88 — v0.5.87 layout/spacing refinements reverted per user
		   request (image #201): "make the homepage look like this again"
		   — image #201 matches image #198 (the loved state). Reverted:
		   hero column gap reduction, audience-cards 2x2 grid, Field
		   Notes row centering, closer body margin-bottom override.
		   KEPT (per "keep the larger font though"): the medium-size
		   font bumps on the four homepage prose paragraphs that read
		   too small at the v0.5.86 site-wide width. */

		/* Hero: bump "Join my newsletter…" intro paragraph. */
		.cr-home-hero .has-border-dark-color {
			font-size: var(--wp--preset--font-size--medium, 1.25rem);
			line-height: 1.5;
		}

		/* Newsletter Features left column: bump "Every Saturday…" intro
		   paragraph + "Looking for personalized guidance?" text-box copy. */
		.cr-home-features .wp-block-column:first-child p {
			font-size: var(--wp--preset--font-size--medium, 1.25rem);
			line-height: 1.55;
		}

		/* Closer ("Start your journey today" CTA): bump "Every Saturday…"
		   body paragraph. */
		.cr-home-closer p.has-secondary-color,
		.cr-home-closer .wp-block-column:first-child > p {
			font-size: var(--wp--preset--font-size--medium, 1.25rem);
			line-height: 1.55;
		}'
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_baseline' );

/**
 * Register a per-block stylesheet for every block in BLOCK_STYLE_MAP.
 *
 * Wp_enqueue_block_style() only loads the CSS when WordPress renders the
 * named block on the current page. For posts that contain only a heading
 * and a paragraph, only core-heading.css is loaded. For pages with the
 * full chip taxonomy, the relevant block CSS is loaded on demand.
 */
function register_per_block_styles(): void {
	foreach ( BLOCK_STYLE_MAP as $block_name => $file_basename ) {
		$file_path = COURTNEYR_CHILD_DIR . "/assets/css/blocks/{$file_basename}.css";

		// Skip blocks whose CSS file has not been written yet.
		// Allows incremental rollout without breaking the site.
		if ( ! is_readable( $file_path ) ) {
			continue;
		}

		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => "courtneyr-block-{$file_basename}",
				'src'    => COURTNEYR_CHILD_URI . "/assets/css/blocks/{$file_basename}.css",
				'path'   => $file_path,
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_per_block_styles' );

/**
 * Enqueue editor-specific styles so the block editor preview matches
 * the front end. Same per-block files are reused; this hook adds them
 * to the editor iframe.
 */
function add_editor_assets(): void {
	add_editor_style(
		array(
			'assets/css/tokens.css',
			'assets/css/components.css',
		)
	);
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\add_editor_assets' );


/**
 * Print the no-flash theme initializer inline in <head> at priority 1
 * — BEFORE any stylesheet links so the data-theme attribute is set on
 * <html> before the first paint. This prevents the dark-flash that
 * happens when a user has chosen "dark" but the page renders light first.
 *
 * The script is ~550 bytes; small enough to inline. Reads the same
 * localStorage key (courtneyr-theme) that view.js writes to.
 */
function print_no_flash_theme_script(): void {
	$path = COURTNEYR_CHILD_DIR . '/assets/js/theme-toggle/no-flash.js';
	if ( ! is_readable( $path ) ) {
		return;
	}
	$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents ) {
		return;
	}
	echo '<script id="courtneyr-theme-no-flash">' . $contents . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', __NAMESPACE__ . '\\print_no_flash_theme_script', 1 );

/**
 * Preload the above-the-fold self-hosted fonts. Paired with font-display:optional
 * (theme.json), this lets the real font arrive before first paint on normal
 * connections — so users still see the brand type — while `optional` guarantees
 * no font-swap layout shift under lab throttling (the mobile CLS culprit). Fonts
 * are CORS-fetched, so the preload needs crossorigin even though same-origin.
 */
function preload_critical_fonts(): void {
	$fonts = array(
		'assets/fonts/barlow-400.woff2',
		'assets/fonts/barlow-700.woff2',
		'assets/fonts/roboto-slab-variable.woff2',
	);
	foreach ( $fonts as $rel ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( COURTNEYR_CHILD_URI . '/' . $rel )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\\preload_critical_fonts', 1 );

/**
 * Enqueue the theme-toggle click handler. Loaded in the footer with
 * defer so it does not block the first paint. Pairs with the inline
 * no-flash script above.
 */
function enqueue_theme_toggle(): void {
	wp_enqueue_script(
		'courtneyr-theme-toggle',
		COURTNEYR_CHILD_URI . '/assets/js/theme-toggle/view.js',
		array(),
		COURTNEYR_CHILD_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_theme_toggle' );

/**
 * Enqueue the icon-injector script that converts <use href> references
 * into inline SVGs from the post-type icon sprite. Required for the
 * cr-icon-avatar pattern to render its icon glyphs.
 *
 * Only enqueued when the icon sprite is present on disk.
 */
function enqueue_icons_inject(): void {
	$path = COURTNEYR_CHILD_DIR . '/assets/svg/icons.svg';
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'courtneyr-icons-inject',
		COURTNEYR_CHILD_URI . '/assets/js/icons-inject.js',
		array(),
		COURTNEYR_CHILD_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	// Pass the sprite URL as a JS global so icons-inject knows where to fetch.
	wp_add_inline_script(
		'courtneyr-icons-inject',
		'window.COURTNEYR_ICONS_SPRITE_URL = ' . wp_json_encode(
			COURTNEYR_CHILD_URI . '/assets/svg/icons.svg?ver=' . COURTNEYR_CHILD_VERSION
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_icons_inject' );

/**
 * Small site-UX a11y polish module (v0.5.49): header search disclosure
 * (Escape + click-outside to close) and Complianz close-button Space-key
 * activation. See assets/js/site-ux.js for full rationale.
 *
 * Footer-deferred — none of this is render-blocking and all behaviors
 * degrade gracefully (Escape/click-outside missing means user re-clicks
 * the toggle; Space on Complianz close still works via Enter fallback).
 */
function enqueue_site_ux(): void {
	wp_enqueue_script(
		'courtneyr-site-ux',
		COURTNEYR_CHILD_URI . '/assets/js/site-ux.js',
		array(),
		COURTNEYR_CHILD_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_site_ux' );


/**
 * Re-skin the wp-admin chrome in the courtneyr.dev palette (v0.5.21).
 *
 * The admin runs on its own CSS and doesn't see tokens.css or
 * components.css. assets/css/admin.css inlines the design tokens as
 * fallbacks and overrides the stable wp-admin selectors (top bar,
 * sidebar, buttons, links, list tables, notices, postboxes).
 *
 * Loaded on every admin page via admin_enqueue_scripts. The block
 * editor canvas is intentionally NOT restyled here — the theme's
 * own CSS handles editor canvas; this stylesheet only touches the
 * admin chrome around it.
 */
function enqueue_admin_styles(): void {
	wp_enqueue_style(
		'courtneyr-admin',
		COURTNEYR_CHILD_URI . '/assets/css/admin.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_styles' );

/**
 * Also load admin.css on the FRONT-END when the admin bar (v0.5.26)
 * is showing (logged-in user viewing the public site). Without this,
 * the WP admin bar at the top of front-end pages renders in default
 * gray/blue and clashes with the courtneyr.dev brand palette. Same
 * stylesheet drives both contexts.
 */
function enqueue_admin_bar_styles_frontend(): void {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-admin-bar',
		COURTNEYR_CHILD_URI . '/assets/css/admin.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_bar_styles_frontend' );
