<?php
/**
 * Single reply letter sheet.
 *
 * The plugin's reply-card owns the cited source microformats. This file
 * removes Stream-only chrome from that card, adds the cited host when the
 * card title does not already show it, and wraps the reply body plus a
 * plain author signature in one torn sheet.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleReply;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether this request is the single view of a reply. */
function is_reply_single(): bool {
	return function_exists( 'is_singular' ) && function_exists( 'has_term' ) && is_singular( 'post' ) && has_term( 'reply', 'kind', get_queried_object_id() );
}

/**
 * Remove one element with a class.
 *
 * @param string $html       Fragment.
 * @param string $tag        Tag name.
 * @param string $class_name Class name.
 * @return string
 */
function cut_element( string $html, string $tag, string $class_name ): string {
	return (string) preg_replace( '/<' . preg_quote( $tag, '/' ) . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>.*?<\/' . preg_quote( $tag, '/' ) . '>/is', '', $html, 1 );
}

/**
 * Add a class to the first article.
 *
 * @param string $html Fragment.
 * @return string
 */
function add_cite_class( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->add_class( 'cr-letter-cite' );
	return $tags->get_updated_html();
}

/**
 * Visible text of the first cited title link.
 *
 * @param string $html Fragment.
 * @return string
 */
function cite_link_text( string $html ): string {
	if ( preg_match( '/<h2\b[^>]*\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"[^>]*>.*?<a\b[^>]*>(.*?)<\/a>.*?<\/h2>/is', $html, $m ) ) {
		return trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
	}
	return '';
}

/**
 * Insert the host after the citation title.
 *
 * @param string $html Citation card.
 * @param string $host Host text.
 * @return string
 */
function insert_host( string $html, string $host ): string {
	if ( '' === $host || strtolower( $host ) === strtolower( cite_link_text( $html ) ) ) {
		return $html;
	}
	return (string) preg_replace( '/(<h2\b[^>]*\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"[^>]*>.*?<\/h2>)/is', '$1<p class="cr-letter-cite__host">' . esc_html( $host ) . '</p>', $html, 1 );
}

/**
 * Dress the reply-card citation for the opened letter.
 *
 * @param string $html     Rendered reply-card block.
 * @param array  $block    Parsed reply-card block.
 * @param mixed  $instance Block instance.
 * @return string
 */
function cite_card( string $html, array $block, $instance = null ): string {
	if ( ! is_reply_single() || ! function_exists( 'post_password_required' ) || post_password_required( get_queried_object_id() ) ) {
		return $html;
	}
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	if ( (int) get_queried_object_id() !== $post_id ) {
		return $html;
	}
	if ( ! str_contains( $html, '<article' ) ) {
		return $html;
	}
	$facts = function_exists( '\\PKIW\\kind_facts' ) ? \PKIW\kind_facts( $post_id ) : array();
	$host  = is_array( $facts ) ? trim( (string) ( $facts['host'] ?? '' ) ) : '';
	$out   = cut_element( $html, 'span', 'pk-kindlabel' );
	$out   = cut_element( $out, 'div', 'pk-badge' );
	$out   = add_cite_class( $out );
	return insert_host( $out, $host );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_post-kinds-indieweb/reply-card', __NAMESPACE__ . '\\cite_card', 20, 3 );
}

/**
 * Plain signature line.
 *
 * @param \WP_Post $post Post.
 * @return string
 */
function signature_html( \WP_Post $post ): string {
	$name = function_exists( 'get_the_author_meta' ) ? trim( (string) get_the_author_meta( 'display_name', $post->post_author ) ) : '';
	if ( '' === $name ) {
		return '';
	}
	return '<p class="cr-letter__signature">' . esc_html( $name ) . '</p>';
}

/**
 * Wrap the single reply content and signature in one sheet.
 *
 * @param string $html  Rendered post-content block.
 * @param array  $block Parsed post-content block.
 * @return string
 */
function sheet( string $html, array $block ): string {
	unset( $block );

	if ( ! is_reply_single() || ( function_exists( 'post_password_required' ) && post_password_required( get_queried_object_id() ) ) ) {
		return $html;
	}
	$post = function_exists( 'get_post' ) ? get_post( get_queried_object_id() ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	return '<div class="cr-letter-sheet cr-paper-sheet cr-paper-sheet--torn">' . $html . signature_html( $post ) . '</div>';
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\sheet', 25, 2 );
}
