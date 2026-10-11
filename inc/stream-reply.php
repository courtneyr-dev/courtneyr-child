<?php
/**
 * Reply Stream envelopes.
 *
 * The Stream card becomes one envelope with a single permalink. Reply
 * target data stays hidden for microformats and the external target never
 * becomes a visible link on the Stream surface.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamReply;

use function Courtneyr\Child\Letters\excerpt_text;
use function Courtneyr\Child\Letters\facts_for;
use function Courtneyr\Child\Letters\heading_level;
use function Courtneyr\Child\Letters\named;
use function Courtneyr\Child\Letters\target_data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable tilt index for the envelope insert.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function tilt_for( int $post_id ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		return \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 6, 5 );
	}
	return abs( crc32( (string) $post_id ) ) % 5;
}

/**
 * Plugin kind label with a local fallback.
 *
 * @return string
 */
function label(): string {
	$fallback = __( 'Reply', 'courtneyr-child' );
	// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- plugin fallback string.
	return function_exists( '\\PKIW\\get_kind_label' ) ? \PKIW\get_kind_label( __( 'Reply', 'post-kinds-for-indieweb-in-block-themes' ), 'reply', 'stream-card' ) : $fallback;
}

/**
 * Render a reply Stream card as an envelope.
 *
 * @param string $html     Rendered stream card.
 * @param array  $block    Parsed stream-card block.
 * @param mixed  $instance Block instance with post context.
 * @return string
 */
function envelope_card( string $html, array $block, $instance ): string {
	if ( ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}
	if ( str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-cr-letter' ) ) {
		return $html;
	}
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
	$post    = $post_id && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! function_exists( 'has_term' ) || ! has_term( 'reply', 'kind', $post ) ) {
		return $html;
	}
	if ( function_exists( 'post_password_required' ) && post_password_required( $post ) ) {
		return $html;
	}
	$facts      = facts_for( $post );
	$uid        = wp_unique_id( '' );
	$level      = heading_level( $block['attrs']['headingLevel'] ?? 2, 2 );
	$name       = '' !== $facts['name'] ? $facts['name'] : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $post ) : (string) $post->post_title );
	$tilt       = tilt_for( (int) $post->ID );
	$insert_cls = 'cr-envelope__insert' . ( $tilt > 0 ? ' cr-envelope__insert--tilt-' . $tilt : '' );
	$title_cls  = 'cr-envelope__title' . ( named( $post ) ? ' p-name' : '' );
	$out        = '<article class="cr-envelope k-reply">';
	$out       .= '<div class="' . esc_attr( $insert_cls ) . '">';
	$out       .= '<p class="cr-envelope__meta"><span class="cr-envelope__label">' . esc_html( label() ) . '</span><time class="dt-published cr-envelope__date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_the_date( 'M j', $post ) ) . '</time></p>';
	$out       .= '<h' . $level . ' class="' . esc_attr( $title_cls ) . '"><a id="cr-envelope-t-' . esc_attr( $uid ) . '" class="u-url" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $name ) . '</a></h' . $level . '>';
	$excerpt    = excerpt_text( $post, 20 );
	if ( '' !== $excerpt ) {
		$out .= '<p class="cr-envelope__excerpt p-summary">' . esc_html( $excerpt ) . '</p>';
	}
	$out .= '</div>';
	if ( '' !== trim( $facts['host'] ) ) {
		$out .= '<p class="cr-envelope__address">' . esc_html( $facts['host'] ) . '</p>';
	}
	$out .= target_data( $facts['url'] );
	return $out . '</article>';
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\envelope_card', 10, 3 );
}
