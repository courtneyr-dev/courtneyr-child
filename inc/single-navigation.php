<?php
/**
 * Navigation for publicly readable single entries.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleNavigation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the public entry being read.
 *
 * @return \WP_Post|null The singular entry when accessible without a password.
 */
function public_entry(): ?\WP_Post {
	if ( ! is_singular() || is_preview() || is_embed() || is_feed() || is_admin() ) {
		return null;
	}

	$post = get_queried_object();
	if ( ! $post instanceof \WP_Post || '' !== $post->post_password || ! is_post_status_viewable( $post->post_status ) || ! is_post_publicly_viewable( $post ) ) {
		return null;
	}
	return $post;
}

/**
 * Place Yoast's trail before the singular template's main title.
 *
 * @param string    $html   Rendered title.
 * @param array     $block  Parsed title block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function breadcrumbs( string $html, array $block, \WP_Block $instance ): string {
	if ( 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || ! empty( $block['attrs']['isLink'] ) || ! function_exists( 'yoast_breadcrumb' ) ) {
		return $html;
	}

	$post = public_entry();
	if ( ! $post || in_array( $post->post_type, array( 'page', 'web-story', 'attachment' ), true ) || (int) ( $instance->context['postId'] ?? 0 ) !== $post->ID ) {
		return $html;
	}

	static $printed = false;
	if ( $printed ) {
		return $html;
	}

	$trail = yoast_breadcrumb( '', '', false );
	if ( ! is_string( $trail ) || '' === trim( $trail ) ) {
		return $html;
	}
	$printed = true;

	return '<nav class="cr-single-breadcrumbs has-small-font-size" aria-label="' . esc_attr__( 'Breadcrumb', 'courtneyr-child' ) . '">' . $trail . '</nav>' . $html;
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\breadcrumbs', 50, 3 );
