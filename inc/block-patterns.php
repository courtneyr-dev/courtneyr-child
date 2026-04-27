<?php
/**
 * Block patterns and pattern categories.
 *
 * Patterns are pre-built compositions users insert via the block inserter.
 * Two roles in this theme:
 *
 * 1. Post-format patterns — one per IndieWeb / WordPress post type, used
 *    as quick-start scaffolding when authoring. Each pattern uses the
 *    appropriate chip + body structure from the design system.
 *
 * 2. Layout patterns — zine-aesthetic compositions (collage layouts,
 *    masking-tape callouts, torn-paper pulls) ready to drop into pages.
 *
 * Patterns live as PHP files in /patterns/ at the theme root. WordPress
 * auto-discovers them; this file only handles category registration so
 * they group correctly in the inserter.
 *
 * Pattern overrides (WP 6.6+) are supported in any pattern by adding
 * "metadata":{"name":"slug","bindings":{...}} to a block. We will adopt
 * those as the post-format patterns mature.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\BlockPatterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom pattern categories surfaced in the inserter.
 */
const PATTERN_CATEGORIES = array(
	'cr-post-format' => 'Post Format (Courtneyr)',
	'cr-zine'        => 'Zine Layouts (Courtneyr)',
	'cr-indieweb'    => 'IndieWeb (Courtneyr)',
);

/**
 * Register the custom pattern categories.
 */
function register_categories(): void {
	foreach ( PATTERN_CATEGORIES as $slug => $label ) {
		register_block_pattern_category(
			$slug,
			array( 'label' => $label )
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_categories' );
