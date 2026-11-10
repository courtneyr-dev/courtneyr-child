<?php
/**
 * Stream trips as luggage tags and itineraries as ticket stubs.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamTravel;

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
 * Read visitor-safe facts supplied by the plugin.
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
 * Preserve the incoming Stream title's heading level.
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
 * Use the plugin's filterable Stream label when available.
 *
 * @param string $kind Travel kind.
 */
function kind_label( string $kind ): string {
	$term     = get_term_by( 'slug', $kind, 'kind' );
	$fallback = $term instanceof \WP_Term ? $term->name : ( 'itinerary' === $kind ? __( 'Itinerary', 'courtneyr-child' ) : __( 'Trip', 'courtneyr-child' ) );
	return function_exists( '\\PKIW\\get_kind_label' ) ? (string) \PKIW\get_kind_label( $fallback, $kind, 'stream-card' ) : $fallback;
}

/**
 * Replace travel Stream cards with privacy-safe physical objects.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function travel_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-travel-card--stream' ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || post_password_required( $post ) ) {
		return $html;
	}
	$kind = has_term( 'trip', 'kind', $post ) ? 'trip' : ( has_term( 'itinerary', 'kind', $post ) ? 'itinerary' : '' );
	if ( '' === $kind ) {
		return $html;
	}
	$travel = facts( $post_id );
	if ( array() === $travel ) {
		return $html;
	}

	$title = trim( (string) get_the_title( $post ) );
	if ( '' === $title && function_exists( '\\PKIW\\untitled_name' ) ) {
		$title = (string) \PKIW\untitled_name( $post, false );
	}
	if ( '' === $title ) {
		$title = 'itinerary' === $kind ? __( 'Untitled itinerary', 'courtneyr-child' ) : __( 'Untitled trip', 'courtneyr-child' );
	}

	$origin      = trim( (string) ( $travel['origin'] ?? '' ) );
	$destination = trim( (string) ( $travel['destination'] ?? '' ) );
	$route       = class_exists( '\\PKIW\\Travel' ) ? (string) \PKIW\Travel::route_html( $origin, $destination ) : '';
	$range       = is_array( $travel['range'] ?? null ) ? trim( (string) ( $travel['range']['display'] ?? '' ) ) : '';
	$count       = max( 0, (int) ( $travel['count'] ?? 0 ) );
	$level       = heading_level( $html );
	$object      = 'trip' === $kind ? 'cr-travel-tag cr-travel-tag--trip' : 'cr-travel-ticket cr-travel-ticket--itinerary';

	$out  = '<article class="pk-card pk-card--stream cr-travel-card cr-travel-card--stream ' . esc_attr( $object ) . ' h-entry">';
	$out .= '<span class="cr-travel-card__fastener" aria-hidden="true" role="presentation"></span>';
	$out .= '<p class="pk-kindlabel cr-travel-card__kind">' . esc_html( kind_label( $kind ) ) . '</p>';
	$out .= '<h' . $level . ' class="cr-travel-card__title"><a class="u-url p-name" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></h' . $level . '>';
	if ( '' !== $route ) {
		$out .= '<p class="cr-travel-card__route">' . $route . '</p>';
	}
	if ( '' !== $range ) {
		$out .= '<p class="cr-travel-card__range">' . esc_html( $range ) . '</p>';
	}
	if ( 'itinerary' === $kind && $count > 0 ) {
		/* translators: %s: itinerary leg count. */
		$out .= '<p class="cr-travel-card__count">' . esc_html( sprintf( _n( '%s leg', '%s legs', $count, 'courtneyr-child' ), number_format_i18n( $count ) ) ) . '</p>';
	}
	$out   .= '<time class="dt-published" datetime="' . esc_attr( (string) get_the_date( 'c', $post ) ) . '" hidden></time>';
	$author = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	if ( '' !== $author ) {
		$out .= '<span hidden>' . $author . '</span>';
	}
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\travel_card', 10, 3 );
