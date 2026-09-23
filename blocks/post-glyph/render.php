<?php
/**
 * courtneyr/post-glyph — server render.
 *
 * Same markup the blog grid's render filter produces, built from the loop post
 * in context (falls back to the global post). The block replaces the static
 * `core/html` placeholder the 0.7.44 patterns saved, so no HTML lives in the
 * page and the editor preview shows the real format.
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance with Query Loop context.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cr_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $cr_post_id ) {
	return;
}
echo \Courtneyr\Child\Interactivity\build_post_glyph( $cr_post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
