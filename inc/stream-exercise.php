<?php
/**
 * Exercise Stream cards: compact race-bib corners (issue 245).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamExercise;

use Courtneyr\Child\Exercise;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a Stream block's post ID.
 *
 * @param mixed $instance Block instance.
 * @return int
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Clamp the Stream card's requested heading level to 2-4.
 *
 * @param array<string, mixed> $block Parsed Stream block.
 * @return int
 */
function heading_level( array $block ): int {
	return max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 2 ) ) );
}

/** The plugin-filtered Exercise label, with a theme fallback. */
function kind_label(): string {
	$label = __( 'Exercise', 'courtneyr-child' );
	if ( function_exists( '\\PKIW\\get_kind_label' ) ) {
		$label = (string) \PKIW\get_kind_label( $label, Exercise\KIND, 'stream-card' );
	}
	return $label;
}

/**
 * Replace an exercise Stream card with its facts-only bib corner.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed Stream block.
 * @param mixed                $instance Block instance with the Query Loop post ID.
 * @return string
 */
function bib_corner( string $html, array $block = array(), $instance = null ): string {
	if ( '' === $html || ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( Exercise\KIND, 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return $html;
	}

	$facts = \PKIW\kind_facts( $post_id );
	if ( ! is_array( $facts ) || array() === $facts ) {
		return $html;
	}

	$is_titled   = ! empty( $facts['name_is_title'] );
	$title_class = 'cr-bib-corner__title pk-title' . ( $is_titled ? ' p-name' : '' );
	$name        = trim( (string) ( $facts['name'] ?? '' ) );
	if ( $is_titled ) {
		$link_text = esc_html( $name );
	} else {
		$name_kind = trim( (string) ( $facts['name_kind'] ?? '' ) );
		$name_date = trim( (string) ( $facts['name_date'] ?? '' ) );
		$link_text = '<span class="screen-reader-text">' . esc_html( $name_kind ) . ', </span>' . esc_html( $name_date );
	}

	// No h-entry here: the plugin's priority-99 filter then treats the card as unrooted, so the Query Loop li carries the entry root.
	$level = heading_level( $block );
	$out   = '<article class="pk-card pk-card--stream k-exercise cr-bib-corner">';
	$out  .= '<span class="cr-bib-corner__kind pk-kindlabel">' . esc_html( kind_label() ) . '</span>';
	$out  .= '<h' . $level . ' class="' . esc_attr( $title_class ) . '"><a class="u-url cr-bib-corner__link" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . $link_text . '</a></h' . $level . '>';

	$activity = trim( (string) ( $facts['activity'] ?? '' ) );
	// The card prints no body, so it carries the entry's one p-exercise even when the post body holds its own.
	if ( '' !== $activity ) {
		$out .= '<p class="cr-bib-corner__activity p-exercise">' . esc_html( $activity ) . '</p>';
	}
	$distance = trim( (string) ( $facts['distance_label'] ?? '' ) );
	if ( '' !== $distance ) {
		$out .= '<p class="cr-bib-corner__distance">' . esc_html( $distance ) . '</p>';
	}

	$out .= '<span class="cr-bib-corner__pin" aria-hidden="true"></span>';
	$out .= '<data class="dt-published" value="' . esc_attr( (string) ( $facts['date_iso'] ?? '' ) ) . '" hidden></data>';

	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\bib_corner', 10, 3 );
