<?php
/**
 * Stream review cards as torn newspaper clippings.
 *
 * The plugin prints the generic stream card. This adapter keeps the article
 * root, title link, kind label, date, and hidden machine-readable data while
 * cutting the media, excerpt, badge, and call to action.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamReview;

use Courtneyr\Child\Review;
use function Courtneyr\Child\MediaShelf\cut_elements;
use function Courtneyr\Child\StreamRead\take_element;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether HTML carries a complete class token.
 *
 * @param string $html       Fragment.
 * @param string $class_name Class token.
 * @return bool
 */
function has_class_token( string $html, string $class_name ): bool {
	return (bool) preg_match( '/\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"/', $html );
}

/**
 * Whether the fragment has the required review card structure.
 *
 * @param string $html Stream card HTML.
 * @return bool
 */
function has_clipping_shape( string $html ): bool {
	if ( ! preg_match( '/^\s*<article\b[^>]*\bclass="[^"]*(?<![\w-])pk-card(?![\w-])[^"]*"[^>]*>/i', $html ) ) {
		return false;
	}
	if ( ! preg_match( '/<span\b[^>]*\bclass="[^"]*(?<![\w-])pk-kindlabel(?![\w-])[^"]*"[^>]*>/i', $html ) ) {
		return false;
	}
	if ( ! preg_match( '/<h[2-4]\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>/i', $html ) ) {
		return false;
	}
	return (bool) preg_match( '/<p\b[^>]*\bclass="[^"]*(?<![\w-])pk-stream-date(?![\w-])[^"]*"[^>]*>/i', $html );
}

/**
 * Add clipping classes to the article root.
 *
 * @param string $html Stream card HTML.
 * @return string
 */
function add_article_classes( string $html ): string {
	return (string) preg_replace_callback(
		'/^(\s*<article\b[^>]*\bclass=")([^"]*)("[^>]*>)/i',
		static function ( array $found ): string {
			$classes = preg_split( '/\s+/', trim( $found[2] ) );
			$classes = is_array( $classes ) ? array_filter( $classes ) : array();
			foreach ( array( 'cr-clipping', 'cr-paper-sheet', 'cr-paper-sheet--torn' ) as $class_name ) {
				if ( ! in_array( $class_name, $classes, true ) ) {
					$classes[] = $class_name;
				}
			}
			return $found[1] . implode( ' ', $classes ) . $found[3];
		},
		$html,
		1
	);
}

/**
 * Insert clipping facts inside the plugin caption.
 *
 * @param string               $html  Stream card HTML after cuts.
 * @param array<string, mixed> $facts Review facts.
 * @param string               $date  The plugin date element.
 * @return string
 */
function insert_caption_facts( string $html, array $facts, string $date ): string {
	$item   = trim( (string) ( $facts['item_name'] ?? '' ) );
	$insert = '';
	if ( '' !== $item ) {
		$insert .= '<p class="pk-sub cr-clipping__item">' . esc_html( $item ) . '</p>';
	}
	$insert .= Review\rating_html( $facts ) . $date;

	return (string) preg_replace_callback(
		'/(<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>\s*)(<h([2-4])\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>.*?<\/h\3>)(.*?)(<\/div>)/is',
		static fn( array $found ): string => $found[1] . $found[2] . $insert . $found[4] . $found[5],
		$html,
		1
	);
}

/**
 * Add the optional pull quote after the caption inside the card body.
 *
 * @param string               $html  Stream card HTML.
 * @param array<string, mixed> $facts Review facts.
 * @return string
 */
function insert_quote( string $html, array $facts ): string {
	$quote = trim( (string) ( $facts['pullquote'] ?? '' ) );
	if ( '' === $quote ) {
		return $html;
	}

	return (string) preg_replace_callback(
		'/(<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>.*?<\/div>)/is',
		static fn( array $found ): string => $found[1] . '<p class="cr-clipping__quote">' . esc_html( $quote ) . '</p>',
		$html,
		1
	);
}

/**
 * Dress a generic review Stream card as a torn clipping.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function clipping( string $html, array $block, $instance = null ): string {
	if ( ! \Courtneyr\Child\HomeSections\is_stream_surface() || '' === $html ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if (
		! $post instanceof \WP_Post
		|| ! has_term( Review\KIND, 'kind', $post )
		|| post_password_required( $post )
		|| has_class_token( $html, 'pk-card--protected' )
		|| ! has_clipping_shape( $html )
	) {
		return $html;
	}

	$facts = Review\facts( $post->ID );
	if ( array() === $facts ) {
		return $html;
	}

	$edited = $html;
	$date   = take_element( $edited, 'p', 'pk-stream-date' );
	if ( '' === $date ) {
		return $html;
	}

	$edited = cut_elements( $edited, 'div', 'pk-badge' );
	$edited = cut_elements( $edited, 'div', 'pk-media' );
	$edited = cut_elements( $edited, 'p', 'pk-excerpt' );
	$edited = cut_elements( $edited, 'div', 'pk-meta' );
	$edited = insert_caption_facts( $edited, $facts, $date );
	$edited = insert_quote( $edited, $facts );

	// The date carries the card's dt-published; a card whose caption didn't take it back stays as the plugin printed it.
	if ( ! str_contains( $edited, $date ) ) {
		return $html;
	}

	return add_article_classes( $edited );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\clipping', 10, 3 );
