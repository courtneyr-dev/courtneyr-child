<?php
/**
 * Navigation for publicly readable single entries.
 *
 * Breadcrumbs. A page gets one breadcrumb trail. Two places can print it:
 * the Breadcrumbs block marked with the `cr-page-trail` class
 * (templates/single.html holds one) and the level-1 Post Title, which puts
 * Yoast's trail before itself. Whichever renders first with a trail to show
 * prints it, and the other then adds nothing. Neither prints a trail inside a
 * Query Loop row (in_loop_row() counts Post Template renders) or for any post
 * other than the queried one, and neither prints one for the post types in
 * NO_TRAIL_TYPES, as before the marked block existed.
 *
 * The marked block shows, in this order of preference: a Stream post's own
 * trail, Home / Stream / <kind> / <title> (stream_trail()); Yoast's trail;
 * core's trail. Every other Breadcrumbs block is core's, untouched, in the
 * editor and on the front end.
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
 * Class that marks the Breadcrumbs block this theme draws the page trail with.
 */
const MARKER = 'cr-page-trail';

/**
 * Post types whose single view gets no page trail.
 */
const NO_TRAIL_TYPES = array( 'page', 'web-story', 'attachment' );

/**
 * Unicode space separators (Zs, Zl, Zp) that trim() leaves in place.
 */
const UNICODE_SPACES = array( "\u{00A0}", "\u{1680}", "\u{2000}", "\u{2001}", "\u{2002}", "\u{2003}", "\u{2004}", "\u{2005}", "\u{2006}", "\u{2007}", "\u{2008}", "\u{2009}", "\u{200A}", "\u{2028}", "\u{2029}", "\u{202F}", "\u{205F}", "\u{3000}" );

/**
 * Whether this theme is the current site's stylesheet.
 *
 * @return bool
 */
function is_active(): bool {
	return THEME === get_option( 'stylesheet' );
}

/**
 * Whether this request's page trail is printed; pass true once it is.
 *
 * @param bool|null $printed True when a trail was just printed, false to reset (tests), null to read.
 * @return bool
 */
function trail_printed( ?bool $printed = null ): bool {
	static $done = false;
	if ( null !== $printed ) {
		$done = $printed;
	}
	return $done;
}

/**
 * Whether a Query Loop row is rendering; pass 1 or -1 as a Post Template render starts or ends.
 *
 * @param int $step 1 entering a Post Template, -1 leaving it, 0 to read.
 * @return bool
 */
function in_loop_row( int $step = 0 ): bool {
	static $depth = 0;

	$depth = max( 0, $depth + $step );
	return $depth > 0;
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
 * Yoast's trail for the page, in the theme's landmark, or '' when Yoast prints none.
 *
 * @return string
 */
function yoast_trail(): string {
	$trail = function_exists( 'yoast_breadcrumb' ) ? yoast_breadcrumb( '', '', false ) : '';
	if ( ! is_string( $trail ) || '' === trim( $trail ) ) {
		return '';
	}
	return '<nav class="cr-single-breadcrumbs has-small-font-size" aria-label="' . esc_attr__( 'Breadcrumb', 'courtneyr-child' ) . '">' . $trail . '</nav>';
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
 * A URL a trail item can link to, or null when esc_url() would drop it.
 *
 * @param mixed $url Candidate URL.
 * @return string|null
 */
function link_url( $url ): ?string {
	return is_string( $url ) && '' !== esc_url( $url ) ? $url : null;
}

/**
 * A Stream post's trail: core's Home item, the Stream page, the kind, the title.
 *
 * Built here, so no parent page, ancestor, Posts page or page number core
 * would add reaches it; on page 2 or later the title stays the unlinked
 * current item. A Stream page whose title is blank once entities are decoded
 * reads "Stream"; an item whose URL esc_url() would drop is left out.
 * Every label, the title too, is plain text: core escapes a label that
 * doesn't set `allow_html`, and the title's tags are stripped first.
 *
 * @param array<int, mixed> $items   Core's trail items.
 * @param int               $post_id Stream post.
 * @param bool              $current Whether the block shows the current item.
 * @return array<int, array<string, mixed>>
 */
function stream_trail( array $items, int $post_id, bool $current ): array {
	$items = array_values( $items );
	$trail = array();
	if ( $items && is_array( $items[0] ) && home_url( '/' ) === ( $items[0]['url'] ?? null ) ) {
		$trail[] = $items[0];
	}

	$stream     = stream_page();
	$stream_url = $stream ? link_url( get_permalink( $stream ) ) : null;
	if ( $stream && $stream_url ) {
		$blank   = '' === trim( str_replace( UNICODE_SPACES, '', html_entity_decode( $stream->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$trail[] = array(
			'label' => $blank ? __( 'Stream', 'courtneyr-child' ) : $stream->post_title,
			'url'   => $stream_url,
		);
	}
	$kind     = stream_kind( $post_id );
	$kind_url = $kind ? link_url( get_term_link( $kind ) ) : null;
	if ( $kind && $kind_url ) {
		$trail[] = array(
			'label' => $kind->name,
			'url'   => $kind_url,
		);
	}

	if ( $current ) {
		$trail[] = array(
			'label' => wp_strip_all_tags( block_core_breadcrumbs_get_post_title( $post_id ) ),
		);
	}
	return $trail;
}

/**
 * Render a Breadcrumbs block: core's untouched, unless it carries the marker.
 *
 * The marked block prints the page trail once per request and never for a
 * post other than the singular page's own (a Query Loop row). It prints a
 * Stream post's trail, else Yoast's trail for the public entry being read,
 * else core's. An empty trail prints nothing and leaves the page trail to the
 * title.
 *
 * @param callable  $core       Core's render callback.
 * @param array     $attributes Block attributes.
 * @param string    $content    Block content.
 * @param \WP_Block $block      Block instance.
 * @return string
 */
function render_breadcrumbs( callable $core, array $attributes, string $content, \WP_Block $block ): string {
	if ( ! is_active() || ! in_array( MARKER, explode( ' ', (string) ( $attributes['className'] ?? '' ) ), true ) ) {
		return (string) $core( $attributes, $content, $block );
	}

	$post_id = (int) ( $block->context['postId'] ?? 0 );
	if ( trail_printed() || in_loop_row() || ( is_singular() && get_queried_object_id() !== $post_id ) || ( $post_id && in_array( get_post_type( $post_id ), NO_TRAIL_TYPES, true ) ) ) {
		return '';
	}

	$lists = is_home() || is_front_page() || is_search() || is_404() || is_archive();
	if ( $post_id && ! $lists && stream_kind( $post_id ) && stream_page() ) {
		$stream = static fn( $items ) => stream_trail( (array) $items, $post_id, ! empty( $attributes['showCurrentItem'] ) );
		add_filter( 'block_core_breadcrumbs_items', $stream );
		$trail  = (string) $core( $attributes, $content, $block );
		remove_filter( 'block_core_breadcrumbs_items', $stream );
	} else {
		$entry = public_entry();
		$trail = $entry && $entry->ID === $post_id ? yoast_trail() : '';
		$trail = '' !== $trail ? $trail : (string) $core( $attributes, $content, $block );
	}

	if ( '' === trim( $trail ) ) {
		return '';
	}
	trail_printed( true );
	return $trail;
}

/**
 * Route core's Breadcrumbs render callback through render_breadcrumbs(), and
 * count Post Template renders for in_loop_row().
 *
 * @param array  $args Block type arguments.
 * @param string $name Block type name.
 * @return array
 */
function wrap_breadcrumbs( $args, $name ): array {
	$args = (array) $args;
	if ( ! isset( $args['render_callback'] ) || ! is_callable( $args['render_callback'] ) ) {
		return $args;
	}
	$core = $args['render_callback'];
	if ( 'core/breadcrumbs' === $name ) {
		$args['render_callback'] = static fn( $attributes, $content, $block ) => render_breadcrumbs( $core, (array) $attributes, (string) $content, $block );
	} elseif ( 'core/post-template' === $name ) {
		$args['render_callback'] = static function ( $attributes, $content, $block ) use ( $core ) {
			in_loop_row( 1 );
			try {
				return $core( $attributes, $content, $block );
			} finally {
				in_loop_row( -1 );
			}
		};
	}
	return $args;
}
add_filter( 'register_block_type_args', __NAMESPACE__ . '\\wrap_breadcrumbs', 10, 2 );

/**
 * Place Yoast's trail before the singular template's main title, unless the page trail is printed.
 *
 * @param string    $html     Rendered title.
 * @param array     $block    Parsed title block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function breadcrumbs( string $html, array $block, \WP_Block $instance ): string {
	if ( ! is_active() || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || ! empty( $block['attrs']['isLink'] ) || trail_printed() || in_loop_row() || isset( $instance->context['queryId'] ) ) {
		return $html;
	}

	$post = public_entry();
	if ( ! $post || in_array( $post->post_type, NO_TRAIL_TYPES, true ) || (int) ( $instance->context['postId'] ?? 0 ) !== $post->ID ) {
		return $html;
	}

	$trail = yoast_trail();
	if ( '' === $trail ) {
		return $html;
	}
	trail_printed( true );
	return $trail . $html;
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\breadcrumbs', 50, 3 );
