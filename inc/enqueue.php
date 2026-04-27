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
 * tokens.css comes first (defines --cr-*); components.css depends on it.
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
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_baseline' );

/**
 * Register a per-block stylesheet for every block in BLOCK_STYLE_MAP.
 *
 * wp_enqueue_block_style() only loads the CSS when WordPress renders the
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
