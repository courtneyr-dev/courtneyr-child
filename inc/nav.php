<?php
/**
 * Shared navigation: archive pagination and single-post Previous/Next.
 *
 * Both controls are core blocks (core/query-pagination in the archive
 * patterns, core/post-navigation-link in templates/single.html) styled by one
 * stylesheet, assets/css/cr-nav.css. This file owns three small integration
 * points:
 *
 * 1. The stylesheet, attached to those two blocks with wp_enqueue_block_style()
 *    so it loads only where they render, front end and editor.
 * 2. Adjacency. The site splits posts into a Blog (main) and a Stream surface
 *    through the `_pkiw_surface` meta Post Kinds computes. Previous/Next stays
 *    on the current post's surface, so a Blog reader never steps into a
 *    stream-only micro post. On the Stream, a post with a kind also stays
 *    within that kind (core's own `taxonomy` attribute). Blog posts don't: 26%
 *    of the live site's posts carry no kind term and Blog notes and articles
 *    interleave on /blog/.
 * 3. Link names. The chips read "Previous" and "Next"; the adjacent post's
 *    title follows inside the link in a `screen-reader-text` span, so the
 *    accessible name says where the link goes and starts with the visible text.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Nav;

use Courtneyr\Child\HomeSections;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class the single template puts on its two post-navigation-link blocks.
 */
const LINK_CLASS = 'cr-post-nav__link';

/**
 * Attach cr-nav.css to the pagination and post-navigation blocks.
 *
 * @return void
 */
function register_style(): void {
	$file = COURTNEYR_CHILD_DIR . '/assets/css/cr-nav.css';
	if ( ! is_readable( $file ) ) {
		return;
	}
	foreach ( array( 'core/query-pagination', 'core/post-navigation-link' ) as $block_name ) {
		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => 'courtneyr-nav',
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-nav.css',
				'path'   => $file,
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_style' );

/**
 * The surface a post belongs to. Posts saved before Post Kinds stored the meta
 * count as main, the same rule the Blog query uses.
 *
 * @param int $post_id Post ID.
 * @return string HomeSections\SURFACE_STREAM or HomeSections\SURFACE_MAIN.
 */
function surface_of( int $post_id ): string {
	return HomeSections\SURFACE_STREAM === get_post_meta( $post_id, '_pkiw_surface', true )
		? HomeSections\SURFACE_STREAM
		: HomeSections\SURFACE_MAIN;
}

/**
 * On a Stream post with a kind, scope core/post-navigation-link to that kind.
 *
 * A `taxonomy` the template sets explicitly wins.
 *
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return array<string, mixed>
 */
function scope_to_kind( array $parsed_block ): array {
	if ( 'core/post-navigation-link' !== ( $parsed_block['blockName'] ?? '' ) || ! empty( $parsed_block['attrs']['taxonomy'] ) ) {
		return $parsed_block;
	}
	$post = get_post();
	if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
		return $parsed_block;
	}
	if ( HomeSections\SURFACE_STREAM === surface_of( $post->ID ) && has_term( '', 'kind', $post ) && has_kind_neighbour( $post ) ) {
		$parsed_block['attrs']['taxonomy'] = 'kind';
	}
	return $parsed_block;
}

/**
 * Does the post's kind hold another post on the same surface?
 *
 * A kind's only post has no Previous or Next inside the kind, and scoping
 * it there would print an empty Post navigation landmark. Such a post
 * keeps the surface's own order instead (the next and previous Stream
 * posts of any kind).
 *
 * @param \WP_Post $post Current post.
 * @return bool
 */
function has_kind_neighbour( \WP_Post $post ): bool {
	static $seen = array();
	if ( ! isset( $seen[ $post->ID ] ) ) {
		$seen[ $post->ID ] = (bool) get_adjacent_post( true, '', true, 'kind' ) || (bool) get_adjacent_post( true, '', false, 'kind' );
	}
	return $seen[ $post->ID ];
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\scope_to_kind' );

/**
 * Meta SQL that keeps an adjacent-post query on the current post's surface.
 *
 * The adjacent-post query aliases the posts table as `p`. The clauses come from
 * HomeSections\surface_meta_clause(), which defers to cr-content-surfaces when
 * that plugin is active, so Previous/Next match /blog/ and /stream/.
 *
 * @param \WP_Post|null $post Current post.
 * @return array{join: string, where: string}|null Null when the post isn't a `post`.
 */
function surface_sql( $post ): ?array {
	if ( is_admin() || ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
		return null;
	}
	$sql = get_meta_sql( array( HomeSections\surface_meta_clause( surface_of( $post->ID ) ) ), 'post', 'p', 'ID' );
	return is_array( $sql ) ? $sql : null;
}

/**
 * Add the surface join to get_previous_post / get_next_post.
 *
 * @param string        $join           JOIN clause.
 * @param bool          $in_same_term   Unused.
 * @param int[]|string  $excluded_terms Unused.
 * @param string        $taxonomy       Unused.
 * @param \WP_Post|null $post           Current post.
 * @return string
 */
function surface_join( $join, $in_same_term, $excluded_terms, $taxonomy, $post ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$sql = surface_sql( $post );
	return (string) $join . ( $sql ? $sql['join'] : '' );
}

/**
 * Add the surface condition to get_previous_post / get_next_post.
 *
 * @param string        $where          WHERE clause.
 * @param bool          $in_same_term   Unused.
 * @param int[]|string  $excluded_terms Unused.
 * @param string        $taxonomy       Unused.
 * @param \WP_Post|null $post           Current post.
 * @return string
 */
function surface_where( $where, $in_same_term, $excluded_terms, $taxonomy, $post ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$sql = surface_sql( $post );
	return (string) $where . ( $sql ? $sql['where'] : '' );
}

add_filter( 'get_previous_post_join', __NAMESPACE__ . '\\surface_join', 10, 5 );
add_filter( 'get_next_post_join', __NAMESPACE__ . '\\surface_join', 10, 5 );
add_filter( 'get_previous_post_where', __NAMESPACE__ . '\\surface_where', 10, 5 );
add_filter( 'get_next_post_where', __NAMESPACE__ . '\\surface_where', 10, 5 );

/**
 * Hide the adjacent post's title visually on the theme's Previous/Next links.
 *
 * Core renders `showTitle` + `linkLabel` as
 * `<a><span class="post-navigation-link__label">Previous</span> <span class="post-navigation-link__title">Title</span></a>`.
 * Adding the core `screen-reader-text` utility to the title span keeps it in
 * the link's accessible name.
 *
 * @param string               $block_content Rendered block.
 * @param array<string, mixed> $block         Parsed block.
 * @return string
 */
function hide_title_visually( string $block_content, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $block_content || ! in_array( LINK_CLASS, explode( ' ', $class ), true ) ) {
		return $block_content;
	}
	$tags  = new \WP_HTML_Tag_Processor( $block_content );
	$query = array(
		'tag_name'   => 'span',
		'class_name' => 'post-navigation-link__title',
	);
	while ( $tags->next_tag( $query ) ) {
		$tags->add_class( 'screen-reader-text' );
	}
	return name_untitled( $tags->get_updated_html(), $block );
}

/**
 * Give an untitled adjacent post (a mood, a note) a descriptive name.
 *
 * Core substitutes "Previous Post" / "Next Post" for an empty title, which
 * makes the link read "Previous Previous Post". Use the adjacent post's kind
 * and date instead: "Mood, September 11, 2026 at 4:05 pm".
 *
 * @param string               $html  Rendered block with the hidden title span.
 * @param array<string, mixed> $block Parsed block, after scope_to_kind().
 * @return string
 */
function name_untitled( string $html, array $block ): string {
	$previous = 'previous' === ( $block['attrs']['type'] ?? 'next' );
	$taxonomy = (string) ( $block['attrs']['taxonomy'] ?? '' );
	$adjacent = get_adjacent_post( '' !== $taxonomy, '', $previous, '' !== $taxonomy ? $taxonomy : 'category' );
	if ( ! $adjacent instanceof \WP_Post || '' !== trim( get_the_title( $adjacent ) ) ) {
		return $html;
	}
	$terms = get_the_terms( $adjacent, 'kind' );
	$kind  = ( is_array( $terms ) && $terms ) ? $terms[0]->name : __( 'Post', 'courtneyr-child' );
	/* translators: 1: post kind name, 2: post date, 3: post time. */
	$name     = sprintf( __( '%1$s, %2$s at %3$s', 'courtneyr-child' ), $kind, get_the_date( '', $adjacent ), get_the_time( '', $adjacent ) );
	$fallback = $previous ? __( 'Previous Post' ) : __( 'Next Post' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own fallback string.
	return str_replace( '>' . esc_html( $fallback ) . '</span>', '>' . esc_html( $name ) . '</span>', $html );
}
add_filter( 'render_block_core/post-navigation-link', __NAMESPACE__ . '\\hide_title_visually', 10, 2 );
