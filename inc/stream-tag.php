<?php
/**
 * Stream tag cards as one-link name-tag stickers.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamTag;

use function Courtneyr\Child\Tag\band_html;
use function Courtneyr\Child\Tag\band_style;
use function Courtneyr\Child\Tag\block_post_id;
use function Courtneyr\Child\Tag\facts;
use function Courtneyr\Child\Tag\is_display_label;
use function Courtneyr\Child\Tag\tilt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

/** The visitor-facing kind label, from the kind term. */
function kind_label(): string {
	$term = get_term_by( 'slug', 'tag', 'kind' );
	return $term instanceof \WP_Term ? (string) $term->name : __( 'Tag', 'courtneyr-child' );
}

/**
 * Hidden mf2 properties: author, tag-of and every label after the first.
 *
 * @param array<string, mixed> $facts Tag facts.
 * @param \WP_Post             $post  Post.
 */
function hidden_props( array $facts, \WP_Post $post ): string {
	$out = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	$url = (string) ( $facts['url'] ?? '' );
	if ( 'person' === ( $facts['target_type'] ?? '' ) ) {
		$name = (string) ( $facts['title'] ?? '' );
		$out .= '<span class="u-tag-of h-card">';
		$out .= '' !== $name ? '<data class="p-name" value="' . esc_attr( $name ) . '"></data>' : '';
		$out .= '' !== $url ? '<data class="u-url" value="' . esc_url( $url ) . '"></data>' : '';
		$out .= '</span>';
	} elseif ( '' !== $url ) {
		$out .= '<data class="u-tag-of" value="' . esc_url( $url ) . '"></data>';
	}
	foreach ( array_slice( array_values( (array) ( $facts['labels'] ?? array() ) ), 1 ) as $label ) {
		$out .= '<data class="p-category" value="' . esc_attr( (string) $label ) . '"></data>';
	}
	return $out;
}

/**
 * Build the one-link Stream name tag from public facts.
 *
 * @param array<string, mixed> $facts Tag facts.
 * @param \WP_Post             $post  Post.
 * @param int                  $level Title heading level.
 */
function card_html( array $facts, \WP_Post $post, int $level ): string {
	$level = max( 2, min( 4, $level ) );
	$label = trim( (string) ( $facts['label'] ?? '' ) );
	$title = (string) ( $facts['display_title'] ?? '' );
	$host  = (string) ( $facts['url_host'] ?? '' );

	$out  = '<article class="pk-card pk-card--stream cr-name-tag-card cr-name-tag-tilt-' . tilt( (int) $post->ID ) . ' h-entry" style="' . esc_attr( band_style( (int) $post->ID ) ) . '">';
	$out .= '<p class="cr-name-tag-card__kind">' . esc_html( kind_label() ) . '</p>';
	if ( '' !== $label ) {
		$out .= '<p class="cr-name-tag-card__sticker cr-name-tag">' . band_html() . '<span class="cr-name-tag__write-in p-category' . ( is_display_label( $label ) ? '' : ' cr-name-tag__label--long' ) . '">' . esc_html( $label ) . '</span></p>';
	}
	// Title plus host names the link, so two cards with the same title stay distinguishable (WCAG 2.4.4).
	$title_id = 'cr-tag-' . (int) $post->ID . '-title';
	$host_id  = 'cr-tag-' . (int) $post->ID . '-host';
	$labelled = '' !== $host ? ' aria-labelledby="' . esc_attr( $title_id . ' ' . $host_id ) . '"' : '';
	$out     .= '<h' . $level . ' class="cr-name-tag-card__title"><a class="u-url' . ( empty( $facts['title_is_target'] ) ? ' p-name' : '' ) . '" id="' . esc_attr( $title_id ) . '" href="' . esc_url( (string) get_permalink( $post ) ) . '"' . $labelled . '>' . esc_html( $title ) . '</a></h' . $level . '>';
	if ( '' !== $host ) {
		$out .= '<p class="cr-name-tag-card__host" id="' . esc_attr( $host_id ) . '">' . esc_html( $host ) . '</p>';
	}
	$out .= '<time class="dt-published cr-name-tag-card__date" datetime="' . esc_attr( (string) ( $facts['date_iso'] ?? '' ) ) . '">' . esc_html( (string) ( $facts['tagged_display'] ?? '' ) ) . '</time>';
	$out .= '<span hidden>' . hidden_props( $facts, $post ) . '</span>';
	return $out . '</article>';
}

/**
 * Replace a public tag post's Stream card with the name-tag card.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function name_tag_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-name-tag-card' ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'tag', 'kind', $post ) ) {
		return $html;
	}
	$facts = facts( $post_id );
	if ( array() === $facts ) {
		return $html;
	}
	return card_html( $facts, $post, heading_level( $html ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\name_tag_card', 10, 3 );
