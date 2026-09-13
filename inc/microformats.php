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

/**
 * R-02 (one microformats root per page): IndieBlocks' "Enable microformats"
 * option adds `h-entry` to <body> on every singular view, which wraps the
 * Stream page's cards and the single post's own entry root in a second,
 * property-less entry. The card/entry roots are owned by Post Kinds and the
 * theme; keep IndieBlocks' `h-feed` on archives and drop the body-level
 * `h-entry`/`h-recipe`/`h-review`/`h-event` on singular views.
 *
 * @param string $class Class IndieBlocks wants to add.
 * @return string
 */
function indieblocks_body_class( string $class ): string {
	if ( is_singular() ) {
		return '';
	}
	return $class;
}
add_filter( 'indieblocks_body_class', __NAMESPACE__ . '\\indieblocks_body_class' );

/**
 * R-02 on archives: IndieBlocks (and any other emitter) adds `h-entry` to the
 * Query Loop item through post_class. When the Post Kinds stream card inside
 * that item already carries the entry root, the item must not: parsers would
 * see two entries per card. Runs after every other post_class callback.
 *
 * @param string[] $classes Post classes.
 * @param string[] $class   Extra classes (unused).
 * @param int      $post_id Post ID.
 * @return string[]
 */
function single_entry_root_per_item( array $classes, array $class, int $post_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! empty( $GLOBALS['pkiw_stream_card_root_seen'][ $post_id ] ) ) {
		$classes = array_values( array_diff( $classes, array( 'h-entry', 'hentry' ) ) );
	}
	return $classes;
}
add_filter( 'post_class', __NAMESPACE__ . '\\single_entry_root_per_item', 100, 3 );
