<?php
/**
 * Stream presentation cards as conference badges.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamPresentation;

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
 * Read visitor-safe presentation facts.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
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

/** Return the visitor-facing presentation kind label, from the plugin's card-label source when present. */
function kind_label(): string {
	$term     = get_term_by( 'slug', 'presentation', 'kind' );
	$fallback = $term instanceof \WP_Term ? $term->name : __( 'Presentation', 'courtneyr-child' );
	// The plugin's .pk-kindlabel text is filterable per site; the badge card shows the same words.
	return function_exists( '\\PKIW\\get_kind_label' ) ? (string) \PKIW\get_kind_label( $fallback, 'presentation', 'stream-card' ) : $fallback;
}

/**
 * Replace a presentation on the Stream with its one-link badge.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function badge_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-badge--stream' ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'presentation', 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}
	$presentation = facts( $post_id );
	if ( array() === $presentation ) {
		return $html;
	}

	$title = trim( (string) ( $presentation['title'] ?? '' ) );
	if ( '' === $title ) {
		$title = trim( (string) get_the_title( $post ) );
	}
	if ( '' === $title && function_exists( '\\PKIW\\untitled_name' ) ) {
		$title = (string) \PKIW\untitled_name( $post, false );
	}
	if ( '' === $title ) {
		$title = __( 'Untitled presentation', 'courtneyr-child' );
	}

	$date_iso     = trim( (string) ( $presentation['date_iso'] ?? '' ) );
	$date_display = trim( (string) ( $presentation['date_display'] ?? '' ) );
	if ( '' === $date_iso ) {
		$date_iso = (string) get_the_date( 'c', $post );
	}
	if ( '' === $date_display ) {
		$date_display = (string) get_the_date( '', $post );
	}

	$level = heading_level( $html );
	$type  = trim( (string) ( $presentation['event_type'] ?? '' ) );
	$out   = '<article class="pk-card pk-card--stream cr-badge cr-badge--stream h-entry" data-pkiw-event-type="' . esc_attr( $type ) . '">';
	$out  .= '<span class="cr-badge__strap" aria-hidden="true" role="presentation"></span>';
	$out  .= '<p class="pk-kindlabel">' . esc_html( kind_label() ) . '</p>';
	$out  .= '<h' . $level . ' class="cr-badge__title"><a class="u-url p-name" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></h' . $level . '>';
	if ( '' !== trim( (string) ( $presentation['event_name'] ?? '' ) ) ) {
		$out .= '<p class="cr-badge__event">' . esc_html( (string) $presentation['event_name'] ) . '</p>';
	}
	$out .= '<time class="dt-published cr-badge__date" datetime="' . esc_attr( $date_iso ) . '">' . esc_html( $date_display ) . '</time>';
	if ( '' !== trim( (string) ( $presentation['event_type_label'] ?? '' ) ) ) {
		$out .= '<p class="cr-badge__type">' . esc_html( (string) $presentation['event_type_label'] ) . '</p>';
	}
	$author = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	if ( '' !== $author ) {
		$out .= '<span hidden>' . $author . '</span>';
	}
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\badge_card', 10, 3 );
