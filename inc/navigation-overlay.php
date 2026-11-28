<?php
/**
 * A Navigation block with no menu shows the menu the header picked.
 *
 * `parts/navigation-overlay.html` holds a Navigation block with no `ref`.
 * Core resolves one to the most recently published `wp_navigation` post: on
 * the front end through `block_core_navigation_get_fallback_blocks()`, and in
 * the Site Editor through `/wp-block-editor/v1/navigation-fallback`, whose id
 * the editor keeps as `ref` when the part is saved. Once a site picks an older
 * menu in the header, a newer menu would fill the overlay instead. Both paths
 * here answer with the header's menu, so the editor, the front end and a saved
 * overlay part agree. A header with no published menu changes nothing.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\NavigationOverlay;

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
 * The published menu the header's Navigation picked, if any.
 *
 * @return \WP_Post|null
 */
function header_menu(): ?\WP_Post {
	$header = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
	$id     = $header ? overlay_opener_menu( parse_blocks( (string) $header->content ) ) : null;
	$menu   = null === $id ? null : get_post( $id );
	return $menu instanceof \WP_Post && 'wp_navigation' === $menu->post_type && 'publish' === $menu->post_status ? $menu : null;
}

/**
 * Front end: a Navigation with no menu renders the header's menu.
 *
 * @param array[] $blocks Core's fallback blocks.
 * @return array[]
 */
function render_fallback( $blocks ) {
	$menu = header_menu();
	if ( ! $menu ) {
		return $blocks;
	}
	$menu_blocks = block_core_navigation_filter_out_empty_blocks( parse_blocks( $menu->post_content ) );
	return $menu_blocks ? parse_blocks( apply_block_hooks_to_content_from_post_object( serialize_blocks( $menu_blocks ), $menu ) ) : $blocks;
}
add_filter( 'block_core_navigation_render_fallback', __NAMESPACE__ . '\\render_fallback' );

/**
 * Site Editor: the fallback menu endpoint answers with the header's menu.
 *
 * Runs after core's permission check and callback, so a refused request stays refused.
 *
 * @param \WP_REST_Response|\WP_Error|mixed $response Result of core's callback.
 * @param array                             $handler  Route handler.
 * @param \WP_REST_Request                  $request  Request.
 * @return \WP_REST_Response|\WP_Error|mixed
 */
function editor_fallback( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || '/wp-block-editor/v1/navigation-fallback' !== $request->get_route() ) {
		return $response;
	}
	$menu = header_menu();
	return $menu ? ( new \WP_REST_Navigation_Fallback_Controller() )->prepare_item_for_response( $menu, $request ) : $response;
}
add_filter( 'rest_request_after_callbacks', __NAMESPACE__ . '\\editor_fallback', 10, 3 );
