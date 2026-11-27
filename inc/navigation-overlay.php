<?php
/**
 * The header Navigation's overlay part shows the header's menu.
 *
 * `parts/navigation-overlay.html` holds a Navigation block with no `ref`, so
 * core resolves it on its own: the most recently published `wp_navigation`
 * post. Once a site picks a menu in the header, a newer menu would fill the
 * overlay instead. Core 7.1.5 and 7.2-RC3 pass no menu from the parent to the
 * overlay, so this hands it over: while a Navigation that opens an overlay part
 * renders, a Navigation inside that part (core marks it
 * `_isWithinOverlayTemplatePart`) with no menu of its own takes the parent's.
 * A parent with no menu passes nothing, and both fall back to the same menu.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\NavigationOverlay;

/**
 * Menus of the overlay-opening Navigation blocks rendering now, innermost last.
 *
 * @param array<int, int|null>|null $set Replacement stack, or null to read.
 * @return array<int, int|null>
 */
function parent_refs( ?array $set = null ): array {
	static $refs = array();
	if ( null !== $set ) {
		$refs = $set;
	}
	return $refs;
}

/**
 * The menu a Navigation block names, if any.
 *
 * @param array $attrs Block attributes.
 * @return int|null
 */
function menu_of( array $attrs ): ?int {
	$ref = $attrs['ref'] ?? $attrs['navigationMenuId'] ?? null;
	return is_numeric( $ref ) ? (int) $ref : null;
}

/**
 * Remember an overlay-opening Navigation's menu; give it to the overlay's Navigation.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function share_menu( array $parsed_block ): array {
	if ( 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}
	$attrs = $parsed_block['attrs'] ?? array();
	if ( empty( $attrs['_isWithinOverlayTemplatePart'] ) ) {
		if ( ! empty( $attrs['overlay'] ) ) {
			parent_refs( array_merge( parent_refs(), array( menu_of( $attrs ) ) ) );
		}
		return $parsed_block;
	}
	$refs   = parent_refs();
	$parent = $refs ? end( $refs ) : null;
	if ( null !== $parent && null === menu_of( $attrs ) ) {
		$parsed_block['attrs']['ref'] = $parent;
	}
	return $parsed_block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\share_menu' );

/**
 * Forget an overlay-opening Navigation's menu once it has rendered.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function release_menu( string $block_content, array $block ): string {
	$attrs = $block['attrs'] ?? array();
	if ( ! empty( $attrs['overlay'] ) && empty( $attrs['_isWithinOverlayTemplatePart'] ) ) {
		parent_refs( array_slice( parent_refs(), 0, -1 ) );
	}
	return $block_content;
}
add_filter( 'render_block_core/navigation', __NAMESPACE__ . '\\release_menu', 10, 2 );
