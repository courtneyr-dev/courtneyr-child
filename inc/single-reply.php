<?php
/**
 * Single reply letter sheet.
 *
 * The plugin's reply-card owns the cited source microformats. This file
 * removes Stream-only chrome from that card, adds the cited host when the
 * card title does not already show it, appends a plain author signature to
 * the reply body, and wraps the post header (date, kind chip, H1) and that
 * body in one torn letter sheet. Comments, the newsletter and the post
 * navigation stay outside the sheet.
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
	// With a cited author, the host follows the author on the same supporting line; the dot is decorative.
	$sub = '/(<p\b[^>]*\bclass="[^"]*(?<![\\w-])pk-sub(?![\\w-])[^"]*"[^>]*>.*?)(\s*<\/p>)/is';
	if ( preg_match( $sub, $html ) ) {
		return (string) preg_replace( $sub, '$1<span class="cr-letter-cite__sep" aria-hidden="true">·</span><span class="cr-letter-cite__host">' . esc_html( $host ) . '</span>$2', $html, 1 );
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
 * Whether a block's className holds one class.
 *
 * @param array  $block      Parsed block.
 * @param string $class_name Class name.
 * @return bool
 */
function block_has_class( array $block, string $class_name ): bool {
	return 1 === preg_match( '/(?:^|\\s)' . preg_quote( $class_name, '/' ) . '(?:\\s|$)/', (string) ( $block['attrs']['className'] ?? '' ) );
}

/**
 * Drop the category chip from the reply lede, so the kind chip is its only label.
 *
 * @param string $html  Rendered post-terms block.
 * @param array  $block Parsed post-terms block.
 * @return string
 */
function lede_category( string $html, array $block ): string {
	if ( ! block_has_class( $block, 'single-post__category' ) || ! is_reply_single() ) {
		return $html;
	}
	return '';
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/post-terms', __NAMESPACE__ . '\\lede_category', 20, 2 );
}

/**
 * Print the stored day on a protected reply, which core's post-data source leaves empty.
 *
 * The date is already public in the permalink; the citation and signature stay hidden.
 *
 * @param string $html     Rendered post-date block.
 * @param array  $block    Parsed post-date block.
 * @param mixed  $instance Block instance.
 * @return string
 */
function protected_date( string $html, array $block, $instance = null ): string {
	if ( '' !== trim( $html ) || ! block_has_class( $block, 'single-post__date' ) || ! is_reply_single() ) {
		return $html;
	}
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_queried_object_id();
	$post    = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || (int) get_queried_object_id() !== (int) $post->ID || ! post_password_required( $post ) ) {
		return $html;
	}
	$format = (string) ( $block['attrs']['format'] ?? '' );
	$class  = trim( (string) ( $block['attrs']['className'] ?? '' ) . ' wp-block-post-date' );
	return '<div class="' . esc_attr( $class ) . '"><time datetime="' . esc_attr( (string) get_the_date( 'c', $post ) ) . '">' . esc_html( (string) get_the_date( $format, $post ) ) . '</time></div>';
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/post-date', __NAMESPACE__ . '\\protected_date', 5, 3 );
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

/** Marker the header filter leaves where the letter sheet opens. */
const LETTER_OPEN = '<!-- cr-letter:open -->';

/** Marker the content filter leaves where the letter sheet closes. */
const LETTER_CLOSE = '<!-- cr-letter:close -->';

/** Whether this request is a reply single that prints its letter. */
function is_open_letter(): bool {
	return is_reply_single() && ! ( function_exists( 'post_password_required' ) && post_password_required( get_queried_object_id() ) );
}

/**
 * Append the signature to the reply body and mark where the letter closes.
 *
 * @param string $html  Rendered post-content block.
 * @param array  $block Parsed post-content block.
 * @return string
 */
function sheet( string $html, array $block ): string {
	unset( $block );

	if ( ! is_open_letter() ) {
		return $html;
	}
	$post = function_exists( 'get_post' ) ? get_post( get_queried_object_id() ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	return '<div class="cr-letter-sheet">' . $html . signature_html( $post ) . '</div>' . LETTER_CLOSE;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\sheet', 25, 2 );
}

/**
 * Mark where the letter opens: before the post header, so the date, kind chip and H1 sit on the sheet.
 *
 * @param string $html  Rendered group block.
 * @param array  $block Parsed group block.
 * @return string
 */
function letter_open( string $html, array $block ): string {
	if ( ! block_has_class( $block, 'single-post__header' ) || ! is_open_letter() ) {
		return $html;
	}
	return LETTER_OPEN . $html;
}

/**
 * Turn the two markers into one letter sheet inside the single's main group.
 *
 * Both markers or neither: a missing half leaves the page unwrapped rather than unbalanced.
 *
 * @param string $html  Rendered group block.
 * @param array  $block Parsed group block.
 * @return string
 */
function letter( string $html, array $block ): string {
	if ( ! block_has_class( $block, 'single-post' ) || ! str_contains( $html, '<!-- cr-letter:' ) ) {
		return $html;
	}
	$open  = strpos( $html, LETTER_OPEN );
	$close = strpos( $html, LETTER_CLOSE );
	if ( false === $open || false === $close || $close < $open ) {
		return str_replace( array( LETTER_OPEN, LETTER_CLOSE ), '', $html );
	}
	return str_replace(
		array( LETTER_OPEN, LETTER_CLOSE ),
		array( '<div class="cr-letter alignwide cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory">', '</div>' ),
		$html
	);
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/group', __NAMESPACE__ . '\\letter_open', 25, 2 );
	add_filter( 'render_block_core/group', __NAMESPACE__ . '\\letter', 26, 2 );
}
