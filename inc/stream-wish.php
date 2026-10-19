<?php
/**
 * Wish Stream cards as torn catalog clippings (PKIW issue 238).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamWish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a stream-card post ID.
 *
 * @param mixed $instance Block instance.
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Read the first article's class tokens.
 *
 * @param string $html Rendered card HTML.
 */
function root_classes( string $html ): array {
	if ( ! preg_match( '/^\\s*<article\\b[^>]*\\bclass="([^"]*)"[^>]*>/i', $html, $match ) ) {
		return array();
	}
	$classes = preg_split( '/\\s+/', trim( $match[1] ) );
	return is_array( $classes ) ? array_values( array_filter( $classes ) ) : array();
}

/**
 * Add clipping classes and optional priority to the root article.
 *
 * @param string $html     Rendered card HTML.
 * @param int    $post_id  Post ID.
 * @param string $priority Wish priority.
 */
function dress_article( string $html, int $post_id, string $priority ): string {
	$rotation = $post_id % 4;
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		$rotation = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 3, 4 );
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	foreach ( array( 'cr-wish-clipping', 'cr-paper-sheet', 'cr-paper-sheet--torn', 'cr-paper--printer-ivory', 'cr-wish-clipping--tilt-' . $rotation ) as $class_name ) {
		$tags->add_class( $class_name );
	}
	if ( '' !== $priority && null === $tags->get_attribute( 'data-priority' ) ) {
		$tags->set_attribute( 'data-priority', $priority );
	}
	return $tags->get_updated_html();
}

/**
 * Add semantic modifier classes to the compact wish chips.
 *
 * @param string $html Rendered card HTML.
 */
function mark_chips( string $html ): string {
	$classes = array( 'pk-wish-chip--type', 'pk-wish-chip--priority', 'pk-wish-chip--price' );
	$at      = 0;
	$tags    = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( 'SPAN' === $tags->get_tag() && $tags->has_class( 'pk-wish-chip' ) && isset( $classes[ $at ] ) ) {
			$tags->add_class( $classes[ $at ] );
			++$at;
		}
	}
	return $tags->get_updated_html();
}

/**
 * Mark media for the shared failed-image fallback.
 *
 * @param string $html Rendered card HTML.
 */
function mark_media( string $html ): string {
	$tags  = new \WP_HTML_Tag_Processor( $html );
	$found = false;
	while ( $tags->next_tag() ) {
		if ( 'DIV' === $tags->get_tag() && $tags->has_class( 'pk-media' ) ) {
			$tags->set_attribute( 'data-cr-cover-fallback', 'true' );
			$found = true;
			continue;
		}
		if ( $found && 'IMG' === $tags->get_tag() ) {
			$tags->set_attribute( 'alt', '' );
			break;
		}
	}
	return $tags->get_updated_html();
}

/**
 * Insert the clipping's decorative tape after the root opens.
 *
 * @param string $html Rendered card HTML.
 */
function insert_tape( string $html ): string {
	if ( ! preg_match( '/^\\s*<article\\b[^>]*>/i', $html, $opening, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$at = (int) $opening[0][1] + strlen( $opening[0][0] );
	return substr( $html, 0, $at ) . '<span class="cr-wish-clipping__tape" aria-hidden="true"></span>' . substr( $html, $at );
}

/**
 * Move the injected Stream date after the chips paragraph.
 *
 * @param string $html Rendered card HTML.
 */
function move_date_after_chips( string $html ): string {
	if ( ! preg_match( '/<p\\b[^>]*\\bclass="[^"]*(?<![\\w-])pk-sub(?![\\w-])[^"]*"[^>]*>[^<]*(?:<span\\b[^>]*\\bclass="[^"]*(?<![\\w-])pk-wish-chip(?![\\w-])[^"]*"[^>]*>.*?<\\/span>\\s*)+<\\/p>/s', $html ) ) {
		return $html;
	}
	$original = $html;
	$date     = \Courtneyr\Child\StreamRead\take_element( $html, 'p', 'pk-stream-date' );
	if ( '' === $date ) {
		return $original;
	}
	$out = preg_replace_callback(
		'/(<p\\b[^>]*\\bclass="[^"]*(?<![\\w-])pk-sub(?![\\w-])[^"]*"[^>]*>.*?pk-wish-chip.*?<\\/p>)/s',
		static fn( array $matches ): string => $matches[1] . $date,
		$html,
		1,
		$count
	);
	return null !== $out && $count > 0 ? $out : $original;
}

/**
 * Dress a wish Stream card as a one-link clipping.
 *
 * @param string $html     Rendered card HTML.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function clipping( string $html, array $block, $instance ): string {
	if ( '' === $html
		|| ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
		|| ! function_exists( '\\Courtneyr\\Child\\MediaShelf\\cut_elements' )
		|| ! function_exists( '\\Courtneyr\\Child\\StreamRead\\take_element' )
	) {
		return $html;
	}
	if ( function_exists( '\\Courtneyr\\Child\\Wish\\is_archive_item' ) ) {
		if ( \Courtneyr\Child\Wish\is_archive_item( $block ) ) {
			return $html;
		}
	} elseif ( false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-cr-wish-listing' ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	$classes = root_classes( $html );
	if ( ! $post instanceof \WP_Post
		|| ! has_term( 'wish', 'kind', $post )
		|| post_password_required( $post )
		|| ! in_array( 'k-wish', $classes, true )
		|| in_array( 'pk-card--protected', $classes, true )
	) {
		return $html;
	}

	$card_only = false !== strpos( $html, 'pk-wish-chip' );
	$generic   = in_array( 'pk-card--stream', $classes, true );
	if ( ! $card_only && ! $generic ) {
		return $html;
	}

	$facts    = function_exists( '\\PKIW\\kind_facts' ) ? \PKIW\kind_facts( $post_id ) : array();
	$priority = is_array( $facts ) ? (string) ( $facts['priority'] ?? '' ) : '';
	$edited   = \Courtneyr\Child\MediaShelf\cut_elements( $html, 'div', 'pk-badge' );
	if ( $generic ) {
		$edited = \Courtneyr\Child\MediaShelf\cut_elements( $edited, 'div', 'pk-meta' );
		$edited = \Courtneyr\Child\MediaShelf\cut_elements( $edited, 'p', 'pk-excerpt' );
	}
	if ( $card_only ) {
		$edited = mark_chips( $edited );
		$edited = move_date_after_chips( $edited );
	}
	$edited = mark_media( $edited );
	$edited = dress_article( $edited, $post_id, $priority );
	return insert_tape( $edited );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\clipping', 10, 3 );
