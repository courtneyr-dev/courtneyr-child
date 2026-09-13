<?php
/**
 * Microformat properties on Core post blocks.
 *
 * Core's Post Title renders the permalink anchor without `u-url`, and Post
 * Date renders `<time datetime>` without `dt-published`. Blocks that carry the
 * marker classes below get the property added to the right element with the
 * HTML API, so the saved content stays plain Core blocks and the editor
 * canvas is unchanged.
 *
 *   core/post-title  className "cr-u-url"        -> <a class="u-url">
 *   core/post-date   className "cr-dt-published" -> <time class="dt-published">
 *
 * @package CourtneyrChild
 * @since 0.7.46
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Microformats;

/**
 * Add a class to the first tag of a kind inside the rendered block.
 */
function add_class_to_tag( string $html, string $tag, string $class ): string {
	$p = new \WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag( $tag ) ) {
		$p->add_class( $class );
		return $p->get_updated_html();
	}
	return $html;
}

function post_title_u_url( string $content, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! str_contains( $class, 'cr-u-url' ) || ! str_contains( $content, '<a' ) ) {
		return $content;
	}
	return add_class_to_tag( $content, 'a', 'u-url' );
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\post_title_u_url', 10, 2 );

function post_date_dt_published( string $content, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! str_contains( $class, 'cr-dt-published' ) || ! str_contains( $content, '<time' ) ) {
		return $content;
	}
	return add_class_to_tag( $content, 'time', 'dt-published' );
}
add_filter( 'render_block_core/post-date', __NAMESPACE__ . '\\post_date_dt_published', 10, 2 );
