<?php
/**
 * Navigation for publicly readable single entries.
 *
 * Breadcrumbs. templates/single.html holds core's Breadcrumbs block
 * (WordPress 7.0+). During the pilot it draws the trail for Stream posts
 * only: a `post` that Post Kinds routed to the Stream (`_pkiw_surface`) and
 * that has a kind reads Home / Stream / <kind> / <title>. Core's two filters
 * do the work: `block_core_breadcrumbs_post_type_settings` picks the kind
 * taxonomy over Category, and `block_core_breadcrumbs_items` adds the /stream/
 * page before the kind. Every other entry Yoast's trail already covers keeps
 * Yoast's trail, printed before the title as before, and the block draws
 * nothing for it, in the editor as on the front end. A page gets one trail.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleNavigation;

use Courtneyr\Child\HomeSections;
use Courtneyr\Child\Nav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types whose single view gets no Yoast trail.
 */
const NO_TRAIL_TYPES = array( 'page', 'web-story', 'attachment' );

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
 * The kind of a Stream post: a `post` on the Stream surface with a kind term.
 *
 * Post Kinds owns both signals. With several kinds, the first get_the_terms()
 * lists, the one core's own term trail would pick.
 *
 * @param int $post_id Post ID.
 * @return \WP_Term|null
 */
function stream_kind( int $post_id ): ?\WP_Term {
	if ( 'post' !== get_post_type( $post_id ) || HomeSections\SURFACE_STREAM !== Nav\surface_of( $post_id ) ) {
		return null;
	}
	$kinds = get_the_terms( $post_id, 'kind' );
	return is_array( $kinds ) && $kinds && $kinds[0] instanceof \WP_Term ? $kinds[0] : null;
}

/**
 * Trail through the kind taxonomy, not Category, for a Stream post.
 *
 * @param array<string, string> $settings  Core's taxonomy and term choice.
 * @param string                $post_type Post type.
 * @param int                   $post_id   Post the block describes.
 * @return array<string, string>
 */
function kind_settings( $settings, $post_type, $post_id ): array {
	$kind = stream_kind( (int) $post_id );
	if ( ! $kind ) {
		return (array) $settings;
	}
	return array(
		'taxonomy' => 'kind',
		'term'     => $kind->slug,
	);
}
add_filter( 'block_core_breadcrumbs_post_type_settings', __NAMESPACE__ . '\\kind_settings', 10, 3 );

/**
 * Put the /stream/ page before the kind in a Stream post's trail.
 *
 * Core runs this filter inside the block's render, with the described post
 * set up as the global post (template, Query Loop and the editor's block
 * renderer alike). The page title is plain text: core escapes a label that
 * doesn't set `allow_html`.
 *
 * @param array<int, array<string, mixed>> $items Trail items.
 * @return array<int, array<string, mixed>>
 */
function stream_trail( $items ): array {
	$items  = (array) $items;
	$kind   = stream_kind( (int) get_the_ID() );
	$stream = $kind ? get_page_by_path( 'stream' ) : null;
	if ( ! $stream instanceof \WP_Post || 'publish' !== $stream->post_status ) {
		return $items;
	}

	$urls       = array_map( static fn( $item ) => is_array( $item ) ? ( $item['url'] ?? null ) : null, $items );
	$stream_url = get_permalink( $stream );
	$at         = array_search( get_term_link( $kind ), $urls, true );
	if ( false === $at || in_array( $stream_url, $urls, true ) ) {
		return $items;
	}

	array_splice(
		$items,
		(int) $at,
		0,
		array(
			array(
				'label' => $stream->post_title,
				'url'   => $stream_url,
			),
		)
	);
	return $items;
}
add_filter( 'block_core_breadcrumbs_items', __NAMESPACE__ . '\\stream_trail' );

/**
 * Entry IDs whose native trail rendered, so Yoast's stays out.
 *
 * @param int $mark Entry to record, or 0 to only read.
 * @return array<int, true>
 */
function native_rendered( int $mark = 0 ): array {
	static $rendered = array();
	if ( $mark ) {
		$rendered[ $mark ] = true;
	}
	return $rendered;
}

/**
 * Leave a post Yoast covers to Yoast; note when the entry's native trail prints.
 *
 * Only on a single-post trail: core builds archive, search and home trails
 * from the query, whatever post the block's context holds.
 *
 * @param string    $html     Rendered trail.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function native_trail( string $html, array $block, \WP_Block $instance ): string {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( '' === trim( $html ) || ! $post_id || is_home() || is_front_page() || is_search() || is_404() || is_archive() ) {
		return $html;
	}

	if ( ! stream_kind( $post_id ) ) {
		$yoast_covers = function_exists( 'yoast_breadcrumb' ) && ! in_array( get_post_type( $post_id ), NO_TRAIL_TYPES, true );
		return $yoast_covers ? '' : $html;
	}

	$entry = public_entry();
	if ( $entry && $entry->ID === $post_id ) {
		native_rendered( $post_id );
	}
	return $html;
}
add_filter( 'render_block_core/breadcrumbs', __NAMESPACE__ . '\\native_trail', 10, 3 );

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
	if ( ! $post || in_array( $post->post_type, NO_TRAIL_TYPES, true ) || (int) ( $instance->context['postId'] ?? 0 ) !== $post->ID || isset( native_rendered()[ $post->ID ] ) ) {
		return $html;
	}

	static $printed = array();
	if ( isset( $printed[ $post->ID ] ) ) {
		return $html;
	}

	$trail = yoast_breadcrumb( '', '', false );
	if ( ! is_string( $trail ) || '' === trim( $trail ) ) {
		return $html;
	}
	$printed[ $post->ID ] = true;

	return '<nav class="cr-single-breadcrumbs has-small-font-size" aria-label="' . esc_attr__( 'Breadcrumb', 'courtneyr-child' ) . '">' . $trail . '</nav>' . $html;
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\breadcrumbs', 50, 3 );
