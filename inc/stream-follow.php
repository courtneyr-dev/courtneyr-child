<?php
/**
 * Stream follow cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamFollow;

use function Courtneyr\Child\Follow\block_post_id;
use function Courtneyr\Child\Follow\facts;
use function Courtneyr\Child\Follow\is_stream_surface;
use function Courtneyr\Child\Follow\paper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROTATIONS = array( 'rotate-1', 'rotate-2', 'rotate-neg', 'rotate-neg2' );

/**
 * Read the incoming card's heading level.
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

/** The public kind label. */
function kind_label(): string {
	$term = get_term_by( 'slug', 'follow', 'kind' );
	return $term instanceof \WP_Term ? (string) $term->name : __( 'Follow', 'courtneyr-child' );
}

/**
 * Hidden author and followed-site h-card.
 *
 * @param array<string, mixed> $facts Follow facts.
 * @param \WP_Post             $post  Follow post.
 */
function hidden_properties( array $facts, \WP_Post $post ): string {
	$out = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	$url = (string) ( $facts['url'] ?? '' );
	if ( '' === $url ) {
		return $out;
	}
	$out .= '<span class="u-follow-of h-card"><data class="p-name" value="' . esc_attr( (string) ( $facts['name'] ?? '' ) ) . '"></data><data class="u-url" value="' . esc_url( $url ) . '"></data>';

	$photo_id = (int) ( $facts['photo_id'] ?? 0 );
	if ( $photo_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
		$photo_url = (string) wp_get_attachment_image_url( $photo_id, 'medium' );
		if ( '' !== $photo_url ) {
			$out .= '<data class="u-photo" value="' . esc_url( $photo_url ) . '"></data>';
		}
	}
	return $out . '</span>';
}

/**
 * Build a one-link Stream card from public facts.
 *
 * @param array<string, mixed> $facts Follow facts.
 * @param \WP_Post             $post  Follow post.
 * @param int                  $level Heading level.
 */
function card_html( array $facts, \WP_Post $post, int $level ): string {
	$post_id  = (int) $post->ID;
	$level    = max( 2, min( 4, $level ) );
	$rotation = ROTATIONS[ abs( $post_id ) % count( ROTATIONS ) ];
	$style    = '--cr-follow-paper:var(--cr-' . paper( $post_id ) . ');--cr-follow-rotate:var(--cr-' . $rotation . ')';
	$out      = '<article class="pk-card pk-card--stream cr-follow-stream h-entry" style="' . esc_attr( $style ) . '">';
	$out     .= '<span class="cr-follow-stream__wheel" aria-hidden="true"></span>';
	$out     .= '<p class="cr-follow-stream__kind">' . esc_html( kind_label() ) . '</p>';
	$photo_id = (int) ( $facts['photo_id'] ?? 0 );
	if ( $photo_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
		$image = (string) wp_get_attachment_image( $photo_id, 'medium', false, array( 'alt' => '' ) );
		if ( '' !== $image ) {
			$out .= '<figure class="cr-follow-stream__photo" data-cr-cover-fallback aria-hidden="true">' . $image . '</figure>';
		}
	}
	$out .= '<span class="cr-follow-stream__initial" aria-hidden="true">' . esc_html( (string) ( $facts['initial'] ?? '' ) ) . '</span>';

	$host     = (string) ( $facts['host'] ?? '' );
	$has_host = '' !== $host && 'host' !== (string) ( $facts['name_source'] ?? '' );
	$name_id  = 'cr-follow-' . $post_id . '-name';
	$host_id  = 'cr-follow-' . $post_id . '-host';

	// Name plus host names the link, so two follows with the same name stay distinguishable (WCAG 2.4.4).
	$labelled = $has_host ? ' aria-labelledby="' . esc_attr( $name_id . ' ' . $host_id ) . '"' : '';

	$out .= '<h' . $level . ' class="cr-follow-stream__name"><a class="u-url" id="' . esc_attr( $name_id ) . '" href="' . esc_url( (string) get_permalink( $post ) ) . '"' . $labelled . '>' . esc_html( (string) ( $facts['name'] ?? '' ) ) . '</a></h' . $level . '>';
	if ( $has_host ) {
		$out .= '<p class="cr-follow-stream__host" id="' . esc_attr( $host_id ) . '">' . esc_html( $host ) . '</p>';
	}
	$out .= '<time class="cr-follow-stream__day dt-published" datetime="' . esc_attr( (string) ( $facts['date_iso'] ?? '' ) ) . '">' . esc_html( (string) ( $facts['day_display'] ?? '' ) ) . '</time>';
	$out .= '<span hidden>' . hidden_properties( $facts, $post ) . '</span>';
	return $out . '</article>';
}

/**
 * Replace a public follow's Stream card.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || ! is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-follow-stream' ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'follow', 'kind', $post ) ) {
		return $html;
	}
	$facts = facts( $post_id );
	return array() === $facts ? $html : card_html( $facts, $post, heading_level( $html ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\card', 10, 3 );
