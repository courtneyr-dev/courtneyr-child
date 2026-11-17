<?php
/**
 * Public follow single card.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleFollow;

use function Courtneyr\Child\Follow\block_post_id;
use function Courtneyr\Child\Follow\facts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a post is the queried follow single.
 *
 * @param int $post_id Post ID.
 */
function is_follow_single( int $post_id ): bool {
	return $post_id > 0
		&& is_singular( 'post' )
		&& get_queried_object_id() === $post_id
		&& has_term( 'follow', 'kind', $post_id );
}

/**
 * Whether rendered content contains visible text or media.
 *
 * @param string $html Rendered post content.
 */
function has_visible_note( string $html ): bool {
	if ( '' === trim( $html ) ) {
		return false;
	}
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return '' !== trim( wp_strip_all_tags( $html ) );
	}

	$processor = new \WP_HTML_Tag_Processor( $html );
	// Each entry is array( tag name, hidden ). Closers pop back to their own tag.
	$open    = array();
	$void    = array( 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR' );
	$atomic  = array( 'IFRAME', 'NOEMBED', 'NOFRAMES', 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP' );
	$foreign = array( 'MATH', 'SVG' );
	$media   = array( 'AUDIO', 'CANVAS', 'EMBED', 'IFRAME', 'IMG', 'OBJECT', 'SVG', 'VIDEO' );
	while ( $processor->next_token() ) {
		$type = $processor->get_token_type();
		if ( '#text' === $type ) {
			$text = str_replace( "\u{00A0}", ' ', html_entity_decode( $processor->get_modifiable_text(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( ! in_array( true, array_column( $open, 1 ), true ) && '' !== trim( $text ) ) {
				return true;
			}
			continue;
		}
		if ( '#tag' !== $type ) {
			continue;
		}
		$tag = (string) $processor->get_tag();
		if ( $processor->is_tag_closer() ) {
			for ( $i = count( $open ) - 1; $i >= 0; $i-- ) {
				if ( $open[ $i ][0] === $tag ) {
					array_splice( $open, $i );
					break;
				}
			}
			continue;
		}
		$is_hidden = in_array( true, array_column( $open, 1 ), true ) || null !== $processor->get_attribute( 'hidden' );
		if ( ! $is_hidden && in_array( $tag, $media, true ) ) {
			return true;
		}
		$in_foreign   = in_array( $tag, $foreign, true ) || array() !== array_intersect( $foreign, array_column( $open, 0 ) );
		$self_closing = $in_foreign && $processor->has_self_closing_flag();
		if ( ! $self_closing && ! in_array( $tag, $void, true ) && ! in_array( $tag, $atomic, true ) ) {
			$open[] = array( $tag, $is_hidden );
		}
	}
	return false;
}

/**
 * Build the visible card from public facts and rendered content.
 *
 * @param array<string, mixed> $facts Follow facts.
 * @param string               $html  Rendered post content.
 */
function card_html( array $facts, string $html ): string {
	$name     = (string) ( $facts['name'] ?? '' );
	$photo_id = (int) ( $facts['photo_id'] ?? 0 );
	$out      = '<div class="cr-follow-single"><div class="cr-follow-card">';
	if ( $photo_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
		$image = (string) wp_get_attachment_image( $photo_id, 'large', false, array( 'alt' => (string) ( $facts['photo_alt'] ?? '' ) ) );
		if ( '' !== $image ) {
			$out .= '<figure class="cr-follow-card__photo" data-cr-cover-fallback>' . $image . '</figure>';
		}
	}
	$out .= '<span class="cr-follow-card__initial" aria-hidden="true">' . esc_html( (string) ( $facts['initial'] ?? '' ) ) . '</span>';
	$out .= ! empty( $facts['title_empty'] )
		? '<h1 class="cr-follow-card__name">' . esc_html( $name ) . '</h1>'
		: '<p class="cr-follow-card__name">' . esc_html( $name ) . '</p>';

	$url = (string) ( $facts['url'] ?? '' );
	if ( '' !== $url ) {
		$out .= '<a class="cr-follow-card__site" href="' . esc_url( $url ) . '" rel="noopener noreferrer">' . esc_html( (string) ( $facts['host'] ?? '' ) ) . '</a>';
	}
	$out .= '<p class="cr-follow-card__followed"><span class="cr-follow-card__label">' . esc_html__( 'Followed', 'courtneyr-child' ) . '</span> <time datetime="' . esc_attr( (string) ( $facts['day'] ?? '' ) ) . '">' . esc_html( (string) ( $facts['day_display'] ?? '' ) ) . '</time></p>';
	$out .= '</div><div class="cr-follow-card__note">';
	if ( has_visible_note( $html ) ) {
		$out .= '<p class="cr-follow-card__label">' . esc_html__( 'Note', 'courtneyr-child' ) . '</p>';
	}
	return $out . $html . '</div></div>';
}

/**
 * Replace a follow single's post-content block with the card.
 *
 * @param string               $html     Rendered post content.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	$post_id = block_post_id( $instance );
	if ( ! is_follow_single( $post_id ) ) {
		return $html;
	}
	$facts = facts( $post_id );
	return array() === $facts ? $html : card_html( $facts, $html );
}
add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\card', 20, 3 );

/**
 * Drop the generated follow paragraph on its own public follow single.
 *
 * @param string               $html  Rendered paragraph.
 * @param array<string, mixed> $block Parsed paragraph.
 */
function drop_generated( string $html, array $block ): string {
	$post_id = (int) get_queried_object_id();
	if ( ! is_follow_single( $post_id ) || array() === facts( $post_id ) ) {
		return $html;
	}
	if ( ! class_exists( '\\PKIW\\Follow_Archive' ) || ! method_exists( '\\PKIW\\Follow_Archive', 'is_generated_paragraph' ) ) {
		return $html;
	}
	return \PKIW\Follow_Archive::is_generated_paragraph( $block ) ? '' : $html;
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\drop_generated', 10, 2 );

/**
 * Let a title-less public follow card own the page H1.
 *
 * @param bool     $holds Existing decision.
 * @param \WP_Post $post  Queried post.
 */
function holds_h1( bool $holds, \WP_Post $post ): bool {
	if ( ! is_follow_single( (int) $post->ID ) ) {
		return $holds;
	}
	$facts = facts( (int) $post->ID );
	return array() !== $facts && ! empty( $facts['title_empty'] ) ? true : $holds;
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Add follow to the kinds that can repeat a featured image.
 *
 * @param mixed $kinds Existing kind list.
 * @return string[]
 */
function repeat_kinds( $kinds ): array {
	$kinds   = is_array( $kinds ) ? $kinds : array();
	$kinds[] = 'follow';
	return array_values( array_unique( $kinds ) );
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );

/**
 * The card repeats the featured image only when public facts expose one.
 *
 * @param bool     $repeats Existing decision.
 * @param \WP_Post $post    Post being rendered.
 */
function featured_repeats( bool $repeats, \WP_Post $post ): bool {
	if ( ! has_term( 'follow', 'kind', $post ) ) {
		return $repeats;
	}
	$facts = facts( (int) $post->ID );
	return array() !== $facts && (int) ( $facts['photo_id'] ?? 0 ) > 0 ? true : $repeats;
}
add_filter( 'courtneyr_child_featured_repeats', __NAMESPACE__ . '\\featured_repeats', 10, 2 );
