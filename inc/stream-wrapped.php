<?php
/**
 * Wrapped posts as one-link Stream collage cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamWrapped;

use function Courtneyr\Child\Wrapped\facts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the Stream card post ID.
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
 * Read the incoming Stream title's heading level.
 *
 * @param string $html Rendered Stream card.
 */
function heading_level( string $html ): int {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return 2;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tag = (string) $tags->get_tag();
		if ( in_array( $tag, array( 'H2', 'H3', 'H4' ), true ) && $tags->has_class( 'pk-title' ) ) {
			return max( 2, min( 4, (int) substr( $tag, 1 ) ) );
		}
	}
	return 2;
}

/**
 * Build one Wrapped Stream card from plugin-owned facts.
 *
 * @param array<string, mixed> $wrapped Display-ready facts.
 * @param \WP_Post             $post    Post.
 * @param int                  $level   Title heading level.
 */
function card_html( array $wrapped, \WP_Post $post, int $level ): string {
	$level   = max( 2, min( 4, $level ) );
	$title   = trim( (string) ( $wrapped['title'] ?? '' ) );
	$kind    = sanitize_key( (string) ( $wrapped['kind'] ?? '' ) );
	$metrics = array_slice( array_values( (array) ( $wrapped['card_metrics'] ?? array() ) ), 0, 3 );
	if ( '' === $title ) {
		$title = (string) get_the_title( $post );
	}

	$out = '<article class="pk-card pk-card--stream cr-wrapped-card h-entry"><span class="cr-wrapped-card__paper" aria-hidden="true"></span>';
	if ( '' !== $kind && function_exists( '\\PKIW\\get_kind_icon_svg' ) ) {
		$out .= '<span class="cr-wrapped-card__glyph" aria-hidden="true">' . \PKIW\get_kind_icon_svg( $kind ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin glyph; the wrapper and SVG are aria-hidden.
	}
	$out .= '<h' . $level . ' class="cr-wrapped-card__title"><a class="u-url p-name" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></h' . $level . '>';
	if ( array() !== $metrics ) {
		$out .= '<ul class="cr-wrapped-card__metrics">';
		foreach ( $metrics as $metric ) {
			if ( ! is_array( $metric ) ) {
				continue;
			}
			$out .= '<li><span class="cr-wrapped-card__value">' . esc_html( (string) ( $metric['display'] ?? '' ) ) . '</span> <span class="cr-wrapped-card__label">' . esc_html( (string) ( $metric['label'] ?? '' ) ) . '</span></li>';
		}
		$out .= '</ul>';
	}
	$out   .= '<time class="dt-published cr-wrapped-card__date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>';
	$author = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	if ( '' !== $author ) {
		$out .= '<span class="cr-wrapped-card__props" hidden>' . $author . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by PKIW\entry_author_html().
	}
	return $out . '</article>';
}

/**
 * Replace a public Wrapped post's Stream card.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function wrapped_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-wrapped-card' ) || ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$wrapped = facts( $post_id );
	if ( array() === $wrapped ) {
		return $html;
	}
	wp_enqueue_style(
		'courtneyr-wrapped',
		COURTNEYR_CHILD_URI . '/assets/css/cr-wrapped.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
	return card_html( $wrapped, $post, heading_level( $html ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\wrapped_card', 10, 3 );
