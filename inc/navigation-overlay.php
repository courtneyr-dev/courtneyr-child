<?php
/**
 * The overlay part's Navigation shows the menu the header picked.
 *
 * `parts/navigation-overlay.html` holds a Navigation with the
 * `cr-nav-overlay__nav` class, which an editor save keeps. Only that block is
 * touched, and only while this theme is the active stylesheet, which
 * `get_option()` reads per site, so `switch_to_blog()` to another theme
 * changes nothing. Core's own fallback, on the front end and at
 * `/wp-block-editor/v1/navigation-fallback`, is left alone.
 *
 * - Front end: the marked block renders with the header's `ref`, whatever a
 *   saved part says, so it shows exactly what core shows for the header,
 *   an empty menu included.
 * - Site Editor: `assets/js/navigation-overlay-editor.js` shows the header's
 *   menu in the marked block without writing `ref`.
 * - Saving a template part drops `ref` from the marked block, so no menu id
 *   goes stale when the header later picks another menu.
 *
 * A header Navigation with no menu changes nothing.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\NavigationOverlay;

const MARKER = 'cr-nav-overlay__nav';
const THEME  = 'courtneyr-child';

/**
 * Whether this theme is the current site's stylesheet.
 *
 * @return bool
 */
function is_active(): bool {
	return THEME === get_option( 'stylesheet' );
}

/**
 * Whether a parsed block is the overlay part's Navigation.
 *
 * @param array $block Parsed block.
 * @return bool
 */
function is_marked( array $block ): bool {
	return 'core/navigation' === ( $block['blockName'] ?? '' )
		&& in_array( MARKER, explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) ), true );
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
 * The menu of the first Navigation that opens an overlay part.
 *
 * @param array $blocks Parsed blocks.
 * @return int|null
 */
function overlay_opener_menu( array $blocks ): ?int {
	foreach ( $blocks as $block ) {
		if ( 'core/navigation' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['overlay'] ) ) {
			return menu_of( $block['attrs'] );
		}
		$menu = overlay_opener_menu( $block['innerBlocks'] ?? array() );
		if ( null !== $menu ) {
			return $menu;
		}
	}
	return null;
}

/**
 * The menu id the header part's overlay-opening Navigation names, if any.
 *
 * @return int|null
 */
function header_menu_id(): ?int {
	$header = get_block_template( THEME . '//header', 'wp_template_part' );
	return $header ? overlay_opener_menu( parse_blocks( (string) $header->content ) ) : null;
}

/**
 * Front end: the overlay part's Navigation renders the header's menu.
 *
 * @param array $block Parsed block.
 * @return array
 */
function mirror_header_menu( $block ) {
	if ( ! is_array( $block ) || ! is_marked( $block ) || ! is_active() ) {
		return $block;
	}
	$menu = header_menu_id();
	if ( null !== $menu ) {
		unset( $block['attrs']['navigationMenuId'] );
		$block['attrs']['ref'] = $menu;
	}
	return $block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\mirror_header_menu' );

/**
 * Parsed blocks with no menu id on the overlay part's Navigation.
 *
 * @param array $blocks Parsed blocks.
 * @return array
 */
function strip_overlay_refs( array $blocks ): array {
	foreach ( $blocks as $i => $block ) {
		if ( is_marked( $block ) ) {
			unset( $blocks[ $i ]['attrs']['ref'], $blocks[ $i ]['attrs']['navigationMenuId'] );
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = strip_overlay_refs( $block['innerBlocks'] );
		}
	}
	return $blocks;
}

/**
 * Saving a template part drops the overlay Navigation's menu id.
 *
 * @param array $data Slashed post data.
 * @return array
 */
function strip_on_save( $data ) {
	if ( ! is_array( $data ) || 'wp_template_part' !== ( $data['post_type'] ?? '' ) || ! is_active() ) {
		return $data;
	}
	$content = wp_unslash( (string) ( $data['post_content'] ?? '' ) );
	if ( ! str_contains( $content, MARKER ) ) {
		return $data;
	}
	$blocks   = parse_blocks( $content );
	$stripped = strip_overlay_refs( $blocks );
	if ( $stripped !== $blocks ) {
		$data['post_content'] = wp_slash( serialize_blocks( $stripped ) );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\strip_on_save' );

/**
 * Site Editor: hand the header's menu id to the overlay editor filter.
 */
function enqueue_editor(): void {
	if ( ! is_active() ) {
		return;
	}
	wp_enqueue_script(
		'courtneyr-navigation-overlay-editor',
		COURTNEYR_CHILD_URI . '/assets/js/navigation-overlay-editor.js',
		array( 'wp-hooks', 'wp-element', 'wp-compose', 'wp-data', 'wp-core-data', 'wp-blocks' ),
		COURTNEYR_CHILD_VERSION,
		true
	);
	$data = array(
		'marker'       => MARKER,
		'headerPart'   => THEME . '//header',
		'headerMenuId' => header_menu_id(),
	);
	wp_add_inline_script( 'courtneyr-navigation-overlay-editor', 'window.courtneyrNavigationOverlay = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor' );
