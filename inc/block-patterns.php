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
	'cr-sections'    => 'Courtneyr · Sections',
	'cr-marks'       => 'Courtneyr · Cards and marks',
	'cr-loops'       => 'Courtneyr · Loops and directories',
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

/**
 * Ollie's unregister_ollie_woocommerce_patterns() calls get_all_registered(),
 * which includes every registered pattern file to read its content: 0.7–0.9 s
 * on each uncached request (Pantheon dev profile, 2026-11-27). Swap it for a
 * name lookup that removes the same ollie/woo-* patterns.
 */
function replace_ollie_woo_pattern_cleanup(): void {
	if ( remove_action( 'init', 'Ollie\\unregister_ollie_woocommerce_patterns', 999 ) ) {
		add_action( 'init', __NAMESPACE__ . '\\unregister_ollie_woo_patterns', 999 );
	}
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\replace_ollie_woo_pattern_cleanup' );

/**
 * Unregister Ollie's WooCommerce patterns by slug, without loading any pattern content.
 */
function unregister_ollie_woo_patterns(): void {
	$registry = \WP_Block_Patterns_Registry::get_instance();
	$files    = glob( get_template_directory() . '/patterns/woo-*.php' );
	foreach ( false === $files ? array() : $files as $file ) {
		$name = 'ollie/' . basename( $file, '.php' );
		if ( $registry->is_registered( $name ) ) {
			unregister_block_pattern( $name );
		}
	}
}
