<?php
/**
 * Single tag post: a name-tag sticker beside an upright clipping.
 *
 * The markup is built from `\PKIW\kind_facts()` only, never from the card's
 * block attributes, so a tag post that isn't public keeps the plugin card and
 * its privacy rules unchanged.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleTag;

use function Courtneyr\Child\Tag\band_html;
use function Courtneyr\Child\Tag\band_style;
use function Courtneyr\Child\Tag\block_post_id;
use function Courtneyr\Child\Tag\facts;
use function Courtneyr\Child\Tag\is_display_label;
use function Courtneyr\Child\Tag\tilt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The aria-hidden external-link icon. */
function external_icon(): string {
	return '<svg class="cr-name-tag-clipping__external" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>';
}

/**
 * The accessible name for a link that opens a new tab.
 *
 * @param string $text Visible link text.
 */
function new_tab_label( string $text ): string {
	if ( function_exists( 'pkiw_new_tab_label_attr' ) ) {
		return (string) \pkiw_new_tab_label_attr( $text );
	}
	return ' aria-label="' . esc_attr( $text . __( ' (opens in a new tab)', 'courtneyr-child' ) ) . '"';
}

/**
 * The sticker: band, hidden label prefix and one p-category per label.
 *
 * @param array<string, mixed> $facts   Tag facts.
 * @param int                  $post_id Post ID.
 */
function sticker( array $facts, int $post_id ): string {
	$labels = array_values( array_filter( array_map( 'strval', (array) ( $facts['labels'] ?? array() ) ), static fn( string $label ): bool => '' !== trim( $label ) ) );
	if ( array() === $labels ) {
		return '';
	}
	$prefix = _n( 'Label:', 'Labels:', count( $labels ), 'courtneyr-child' );
	$out    = '<div class="cr-name-tag-single__mount"><div class="cr-name-tag-single__sticker cr-name-tag cr-name-tag-tilt-' . tilt( $post_id ) . '" style="' . esc_attr( band_style( $post_id ) ) . '">';
	$out   .= '<span class="cr-name-tag__tape" aria-hidden="true"></span>';
	$out   .= band_html();
	$out   .= '<p class="cr-name-tag__write-in"><span class="screen-reader-text">' . esc_html( $prefix ) . ' </span>';
	foreach ( $labels as $label ) {
		$out .= '<span class="p-category' . ( is_display_label( $label ) ? '' : ' cr-name-tag__label--long' ) . '">' . esc_html( $label ) . '</span>';
	}
	return $out . '</p></div></div>';
}

/**
 * The clipping for a content target: one external link, host, description, image.
 *
 * @param array<string, mixed> $facts Tag facts.
 */
function content_clipping( array $facts ): string {
	$url   = (string) ( $facts['url'] ?? '' );
	$host  = (string) ( $facts['url_host'] ?? '' );
	$title = trim( (string) ( $facts['title'] ?? '' ) );
	$text  = '' !== $title ? $title : $host;
	$out   = '';
	if ( '' !== $text ) {
		$out .= '<h2 class="cr-name-tag-clipping__title">';
		$out .= '' !== $url
			? '<a class="u-tag-of" href="' . esc_url( $url ) . '" rel="noopener noreferrer" target="_blank"' . new_tab_label( $text ) . '>' . esc_html( $text ) . external_icon() . '</a>'
			: esc_html( $text );
		$out .= '</h2>';
	} elseif ( '' !== $url ) {
		$out .= '<data class="u-tag-of" value="' . esc_url( $url ) . '"></data>';
	}
	if ( '' !== $host ) {
		$out .= '<p class="cr-name-tag-clipping__host">' . esc_html( $host ) . '</p>';
	}
	$description = trim( (string) ( $facts['description'] ?? '' ) );
	if ( '' !== $description ) {
		$out .= '<p class="cr-name-tag-clipping__description">' . esc_html( $description ) . '</p>';
	}
	$image_id = (int) ( $facts['image_id'] ?? 0 );
	if ( $image_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
		$image = (string) wp_get_attachment_image( $image_id, 'medium', false, array( 'alt' => (string) ( $facts['image_alt'] ?? '' ) ) );
		if ( '' !== $image ) {
			$out .= '<figure class="cr-name-tag-clipping__image">' . $image . '</figure>';
		}
	}
	return $out;
}

/**
 * The clipping for a person target: h-card with a local photo or an initial tile.
 *
 * @param array<string, mixed> $facts Tag facts.
 */
function person_clipping( array $facts ): string {
	$url  = (string) ( $facts['url'] ?? '' );
	$host = (string) ( $facts['url_host'] ?? '' );
	$name = trim( (string) ( $facts['title'] ?? '' ) );
	if ( '' === $name && '' === $url ) {
		return '';
	}
	$shown    = '' !== $name ? $name : $host;
	$image_id = (int) ( $facts['image_id'] ?? 0 );
	$photo    = '';
	if ( $image_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
		$photo = (string) wp_get_attachment_image(
			$image_id,
			'thumbnail',
			false,
			array(
				'class' => 'u-photo cr-name-tag-clipping__photo',
				'alt'   => '',
			)
		);
	}
	if ( '' === $photo ) {
		$initial = (string) ( $facts['initial'] ?? '' );
		if ( '' === $initial ) {
			$initial = mb_strtoupper( mb_substr( $shown, 0, 1 ) );
		}
		$photo = '<span class="cr-name-tag-clipping__initial" aria-hidden="true">' . esc_html( $initial ) . '</span>';
	}
	$out  = '<p class="cr-name-tag-clipping__person"><span class="u-tag-of h-card">' . $photo;
	$out .= '' !== $url
		? '<a class="p-name u-url" href="' . esc_url( $url ) . '" rel="noopener noreferrer">' . esc_html( $shown ) . '</a>'
		: '<span class="p-name">' . esc_html( $shown ) . '</span>';
	$out .= '</span></p>';
	if ( '' !== $host ) {
		$out .= '<p class="cr-name-tag-clipping__host">' . esc_html( $host ) . '</p>';
	}
	return $out;
}

/**
 * Build the sticker and clipping from facts.
 *
 * @param array<string, mixed> $facts   Tag facts (public post only).
 * @param int                  $post_id Post ID.
 */
function clipping_html( array $facts, int $post_id ): string {
	$type     = 'person' === ( $facts['target_type'] ?? '' ) ? 'person' : 'content';
	$clipping = 'person' === $type ? person_clipping( $facts ) : content_clipping( $facts );
	$sticker  = sticker( $facts, $post_id );
	if ( '' === $clipping && '' === $sticker ) {
		return '';
	}
	$out  = '<div class="cr-name-tag-single cr-name-tag-single--' . $type . ( '' === $sticker ? ' cr-name-tag-single--no-sticker' : '' ) . '">';
	$out .= $sticker;
	if ( '' !== $clipping ) {
		$out .= '<div class="cr-name-tag-clipping">' . $clipping . '</div>';
	}
	return $out . '</div>';
}

/**
 * Replace the first tag card on a public tag single with the sticker and clipping.
 *
 * @param string               $html     Rendered plugin tag card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function clipping( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	static $done = array();

	if ( '' === $html || ! is_singular( 'post' ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	if ( $post_id < 1 || get_queried_object_id() !== $post_id || isset( $done[ $post_id ] ) || ! has_term( 'tag', 'kind', $post_id ) ) {
		return $html;
	}
	$facts = facts( $post_id );
	if ( array() === $facts ) {
		return $html;
	}
	$done[ $post_id ] = true;
	$out              = clipping_html( $facts, $post_id );
	return '' === $out ? $html : $out;
}
add_filter( 'render_block_post-kinds-indieweb/tag-card', __NAMESPACE__ . '\\clipping', 20, 3 );
