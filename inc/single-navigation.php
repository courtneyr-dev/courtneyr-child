<?php
/**
 * Navigation for publicly readable single entries.
 *
 * Breadcrumbs. templates/single.html holds core's Breadcrumbs block
 * (WordPress 7.0+). During the pilot it draws the trail for Stream posts
 * only: a `post` that Post Kinds routed to the Stream (`_pkiw_surface`), that
 * has a kind, while the /stream/ page is published, reads
 * Home / Stream / <kind> / <title>. The theme builds that middle itself
 * (stream_trail()), so no parent page, ancestor or Posts page core would add
 * reaches it. Every other entry Yoast's trail already covers keeps Yoast's
 * trail, printed before the title as before, and the block draws nothing for
 * it. Who draws the trail is decided from the entry and the template alone,
 * never from which block happens to render first, so a page gets one trail.
 *
 * The page trail comes from the template: a Breadcrumbs block inside post
 * content or a Query Loop draws nothing.
 *
 * Every callback first checks that this theme is the current site's
 * stylesheet, which follows switch_to_blog() on a multisite.
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

const THEME = 'courtneyr-child';

/**
 * Post types whose single view gets no Yoast trail.
 */
const NO_TRAIL_TYPES = array( 'page', 'web-story', 'attachment' );

/**
 * Blocks whose inner blocks are post content or Query Loop rows, never the page's own trail.
 */
const NESTING_BLOCKS = array( 'core/post-content', 'core/query', 'core/post-template' );

/**
 * Whether this theme is the current site's stylesheet.
 *
 * @return bool
 */
function is_active(): bool {
	return THEME === get_option( 'stylesheet' );
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
 * The published /stream/ page.
 *
 * @return \WP_Post|null
 */
function stream_page(): ?\WP_Post {
	$page = get_page_by_path( 'stream' );
	return $page instanceof \WP_Post && 'publish' === $page->post_status ? $page : null;
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
 * Whether the theme draws a post's trail: a Stream post while /stream/ is published.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function draws_stream_trail( int $post_id ): bool {
	return null !== stream_kind( $post_id ) && null !== stream_page();
}

/**
 * A URL a trail item can link to, or null when esc_url() would drop it.
 *
 * @param mixed $url Candidate URL.
 * @return string|null
 */
function link_url( $url ): ?string {
	return is_string( $url ) && '' !== esc_url( $url ) ? $url : null;
}

/**
 * What the template being rendered holds outside post content and Query Loops.
 *
 * Read from the template's markup, so the answer doesn't depend on the order
 * its blocks render in. Template parts and patterns aren't expanded.
 *
 * @return array{breadcrumbs: bool, title: bool} A Breadcrumbs block; a level-1, unlinked Post Title.
 */
function template_holds(): array {
	static $cache = array();
	$content      = $GLOBALS['_wp_current_template_content'] ?? '';
	if ( ! is_string( $content ) || '' === $content ) {
		return array(
			'breadcrumbs' => false,
			'title'       => false,
		);
	}

	$key = md5( $content );
	if ( ! isset( $cache[ $key ] ) ) {
		$found = array(
			'breadcrumbs' => false,
			'title'       => false,
		);
		$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
			foreach ( $blocks as $block ) {
				$name = (string) ( $block['blockName'] ?? '' );
				if ( in_array( $name, NESTING_BLOCKS, true ) ) {
					continue;
				}
				if ( 'core/breadcrumbs' === $name ) {
					$found['breadcrumbs'] = true;
				}
				if ( 'core/post-title' === $name && 1 === (int) ( $block['attrs']['level'] ?? 2 ) && empty( $block['attrs']['isLink'] ) ) {
					$found['title'] = true;
				}
				$walk( (array) ( $block['innerBlocks'] ?? array() ) );
			}
		};
		$walk( parse_blocks( $content ) );
		$cache[ $key ] = $found;
	}
	return $cache[ $key ];
}

/**
 * Whether the theme's native trail replaces Yoast's on this entry's page.
 *
 * @param \WP_Post $entry Entry being read.
 * @return bool
 */
function native_replaces_yoast( \WP_Post $entry ): bool {
	return draws_stream_trail( $entry->ID ) && template_holds()['breadcrumbs'];
}

/**
 * Whether breadcrumbs() prints Yoast's trail on this entry's page.
 *
 * @param \WP_Post $entry Entry being read.
 * @return bool
 */
function yoast_prints( \WP_Post $entry ): bool {
	return function_exists( 'yoast_breadcrumb' )
		&& ! in_array( $entry->post_type, NO_TRAIL_TYPES, true )
		&& ! native_replaces_yoast( $entry )
		&& template_holds()['title'];
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
	$kind = is_active() ? stream_kind( (int) $post_id ) : null;
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
 * Draw a Stream post's trail as Home / Stream / <kind> / <title>.
 *
 * Core runs this filter inside the block's render, with the described post
 * set up as the global post (template and the editor's block renderer
 * alike). Core's Home item and its title (and page number) items stay; what
 * core put between them (a Posts page, a parent page and its ancestors, the
 * term) gives way to the Stream page and the kind. A Stream page with no
 * title reads "Stream"; an item whose URL esc_url() would drop is left out.
 * Labels are plain text: core escapes a label that doesn't set `allow_html`.
 *
 * @param array<int, array<string, mixed>> $items Trail items.
 * @return array<int, array<string, mixed>>
 */
function stream_trail( $items ): array {
	$items   = array_values( (array) $items );
	$post_id = (int) get_the_ID();
	if ( ! is_active() || ! $post_id || is_home() || is_front_page() || is_search() || is_404() || is_archive() || ! draws_stream_trail( $post_id ) ) {
		return $items;
	}

	$kind   = stream_kind( $post_id );
	$stream = stream_page();

	$head = array();
	if ( $items && is_array( $items[0] ) && home_url( '/' ) === ( $items[0]['url'] ?? null ) ) {
		$head[] = array_shift( $items );
	}

	$at = null;
	foreach ( $items as $i => $item ) {
		if ( is_array( $item ) && get_permalink( $post_id ) === ( $item['url'] ?? null ) ) {
			$at = $i;
			break;
		}
	}
	if ( null === $at ) {
		$at = $items && empty( end( $items )['url'] ) ? count( $items ) - 1 : count( $items );
	}
	$tail = array_slice( $items, $at );

	$middle     = array();
	$stream_url = link_url( get_permalink( $stream ) );
	if ( $stream_url ) {
		$middle[] = array(
			'label' => '' === trim( $stream->post_title ) ? __( 'Stream', 'courtneyr-child' ) : $stream->post_title,
			'url'   => $stream_url,
		);
	}
	$kind_url = link_url( get_term_link( $kind ) );
	if ( $kind_url ) {
		$middle[] = array(
			'label' => $kind->name,
			'url'   => $kind_url,
		);
	}

	return array_merge( $head, $middle, $tail );
}
add_filter( 'block_core_breadcrumbs_items', __NAMESPACE__ . '\\stream_trail' );

/**
 * Depth of post content and Query Loops being rendered.
 *
 * @param int $change +1 entering one, -1 leaving one, 0 to only read.
 * @return int
 */
function nesting( int $change = 0 ): int {
	static $depth = 0;
	$depth        = max( 0, $depth + $change );
	return $depth;
}

/**
 * Track entry into post content and Query Loops; a Breadcrumbs block inside one draws nothing.
 *
 * Runs last, so a block another filter already rendered isn't counted.
 *
 * @param string|null $pre   Content another filter rendered, or null.
 * @param array       $block Parsed block.
 * @return string|null
 */
function enter_block( $pre, $block ) {
	if ( null !== $pre || ! is_active() ) {
		return $pre;
	}
	$name = (string) ( $block['blockName'] ?? '' );
	if ( 'core/breadcrumbs' === $name && nesting() > 0 ) {
		return '';
	}
	if ( in_array( $name, NESTING_BLOCKS, true ) ) {
		nesting( 1 );
	}
	return $pre;
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\enter_block', PHP_INT_MAX, 2 );

/**
 * Leave post content or a Query Loop.
 *
 * @param string $html Rendered block.
 * @return string
 */
function leave_block( $html ) {
	if ( is_active() ) {
		nesting( -1 );
	}
	return $html;
}
array_map( static fn( $name ) => add_filter( "render_block_{$name}", __NAMESPACE__ . '\\leave_block', PHP_INT_MAX ), NESTING_BLOCKS );

/**
 * Leave a post Yoast's trail covers to Yoast.
 *
 * Only on a single-post trail: core builds archive, search and home trails
 * from the query, whatever post the block's context holds. On the entry's
 * page the block steps aside exactly when breadcrumbs() prints Yoast's
 * trail. In the editor's block renderer, with no page to compare, it steps
 * aside for a `post` Yoast would cover, as the front end of single.html does.
 *
 * @param string    $html     Rendered trail.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function native_trail( string $html, array $block, \WP_Block $instance ): string {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( ! is_active() || '' === trim( $html ) || ! $post_id || is_home() || is_front_page() || is_search() || is_404() || is_archive() || draws_stream_trail( $post_id ) ) {
		return $html;
	}

	$entry = public_entry();
	if ( $entry && $entry->ID === $post_id ) {
		return yoast_prints( $entry ) ? '' : $html;
	}
	return function_exists( 'yoast_breadcrumb' ) && 'post' === get_post_type( $post_id ) ? '' : $html;
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
	if ( ! is_active() || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || ! empty( $block['attrs']['isLink'] ) || ! function_exists( 'yoast_breadcrumb' ) ) {
		return $html;
	}

	$post = public_entry();
	if ( ! $post || in_array( $post->post_type, NO_TRAIL_TYPES, true ) || (int) ( $instance->context['postId'] ?? 0 ) !== $post->ID || native_replaces_yoast( $post ) ) {
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
