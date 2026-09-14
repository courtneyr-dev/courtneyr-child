<?php
/**
 * Kind parts: the single template's `kind` template part renders the part for
 * the post's kind.
 *
 * The single template (templates/single.html) places one `core/template-part`
 * with slug `kind`. On a singular post whose Post Kind has a theme part
 * (`parts/kind-listen.html`, and the kinds R-14 adds later), that block renders
 * the `kind-{slug}` part; any other request renders nothing there. The parts hold patterns whose values
 * come from Post Kinds block bindings, so the theme no longer rewrites the
 * plugin's card markup to show them.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\KindParts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROUTER_SLUG = 'kind';

/**
 * The theme part slug for a post's kind, when the theme ships one.
 *
 * @param int $post_id Post ID.
 * @return string `kind-{slug}`, or '' when the post has no kind or no part exists.
 */
function part_slug_for( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'kind' );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}
	$slug = 'kind-' . sanitize_key( $terms[0]->slug );
	return get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ? $slug : '';
}

/**
 * Route the `kind` template part to the post's kind part, or render nothing.
 *
 * @param string|null          $pre          Short-circuit output from earlier filters.
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return string|null
 */
function route( ?string $pre, array $parsed_block ): ?string {
	if ( null !== $pre || 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) || ROUTER_SLUG !== ( $parsed_block['attrs']['slug'] ?? '' ) ) {
		return $pre;
	}
	$slug = is_singular( 'post' ) ? part_slug_for( (int) get_queried_object_id() ) : '';
	if ( '' === $slug ) {
		return '';
	}
	$parsed_block['attrs']['slug'] = $slug;
	return render_block( $parsed_block );
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\route', 10, 2 );
