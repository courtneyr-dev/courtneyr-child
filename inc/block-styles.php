<?php
/**
 * Block style variations.
 *
 * register_block_style() adds a named variation that appears in the block
 * inspector "Styles" panel. Users select it like any other style; the
 * resulting class (e.g. is-style-cr-tape) is applied to the rendered
 * block. The actual CSS lives in components.css and the per-block files
 * loaded by inc/enqueue.php.
 *
 * Pattern: a block style for every zine vocabulary element that has a
 * meaningful editor presence. Things that are pure layout primitives
 * (the rotation system, hard shadows) are exposed via theme.json
 * shadow presets instead — register_block_style is for named
 * variations users intentionally pick.
 *
 * Adding a new variation:
 *   1. Add the .is-style-cr-{slug} rule to assets/css/components.css or
 *      the appropriate per-block CSS file.
 *   2. Append an entry below using the matching block name + slug.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\BlockStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block style registrations.
 *
 * Each entry: [ block_name, slug, label, optional inline_style_callback ]
 * Inline style callback is reserved for future use; for now all visuals
 * come from components.css so this is null on every entry.
 */
const STYLE_VARIATIONS = array(
	// Tape — masking-tape eyebrow / callout label, applies to group, paragraph, heading.
	array( 'core/group',     'cr-tape',      'Masking Tape' ),
	array( 'core/paragraph', 'cr-tape',      'Masking Tape' ),
	array( 'core/heading',   'cr-tape',      'Masking Tape' ),

	// Highlight — selective-yellow marker swipe under inline text.
	array( 'core/heading',   'cr-highlight', 'Marker Highlight' ),
	array( 'core/paragraph', 'cr-highlight', 'Marker Highlight' ),

	// Eyebrow — small all-caps tracked label above headings.
	array( 'core/paragraph', 'cr-eyebrow',   'Eyebrow Label' ),

	// Hard-shadow group — zine card with offset shadow + 2px ink border.
	array( 'core/group',     'cr-card',      'Zine Card' ),

	// Rotation styles — four locked rotation values from the design system.
	// Apply to images, groups, and quotes for collage-style layouts.
	array( 'core/image',     'cr-rotate-1',    'Rotate -1.2°' ),
	array( 'core/image',     'cr-rotate-2',    'Rotate +1.5°' ),
	array( 'core/image',     'cr-rotate-neg',  'Rotate -2.5°' ),
	array( 'core/image',     'cr-rotate-neg2', 'Rotate +2.8°' ),
	array( 'core/group',     'cr-rotate-1',    'Rotate -1.2°' ),
	array( 'core/group',     'cr-rotate-2',    'Rotate +1.5°' ),
	array( 'core/quote',     'cr-rotate-1',    'Rotate -1.2°' ),
	array( 'core/quote',     'cr-rotate-2',    'Rotate +1.5°' ),

	// Quote pulled from the page like a torn-paper note.
	array( 'core/quote',     'cr-torn-paper', 'Torn Paper' ),

	// Buttons — outline variant for secondary CTAs.
	array( 'core/button',    'cr-outline',   'Outline' ),
);

/**
 * Run register_block_style() for every entry in STYLE_VARIATIONS.
 */
function register_all(): void {
	foreach ( STYLE_VARIATIONS as $variation ) {
		list( $block_name, $slug, $label ) = $variation;

		register_block_style(
			$block_name,
			array(
				'name'  => $slug,
				'label' => $label,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_all' );
