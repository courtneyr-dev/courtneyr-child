<?php
/**
 * Review archive and shared review helpers.
 *
 * The plugin owns review facts and rendered review markup. This file only
 * gates the review stylesheet, marks review archive images for fallback
 * behavior, and exposes small helpers used by the single and Stream adapters.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'review';
const STYLE_HANDLE = 'courtneyr-review';
const EAGER_COVERS = 3;

/** Whether this request is the review kind archive. */
function is_review_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single review post. */
function is_review_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Review facts from the plugin's render-time reader.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	return function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post_id ) : array();
}

/**
 * Rating markup from the plugin, with a label-only fallback for tests.
 *
 * @param array<string, mixed> $facts Review facts.
 * @return string
 */
function rating_html( array $facts ): string {
	if ( (float) ( $facts['rating'] ?? 0 ) <= 0 ) {
		return '';
	}

	if (
		class_exists( '\\PKIW\\Review_Archive' )
		&& is_callable( array( '\\PKIW\\Review_Archive', 'review_rating_html' ) )
	) {
		return (string) \PKIW\Review_Archive::review_rating_html( $facts );
	}

	$label = (string) ( $facts['rating_label'] ?? '' );
	if ( '' === $label ) {
		return '';
	}
	return '<p class="pkiw-review-rating"><span class="pkiw-review-rating__label">' . esc_html( $label ) . '</span></p>';
}

/** Enqueue review paint where review markup can appear. */
function enqueue_styles(): void {
	if (
		! is_admin()
		&& ! is_review_archive()
		&& ! is_review_single()
		&& ! \Courtneyr\Child\HomeSections\is_stream_surface()
	) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-review.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Add review body classes for the archive and single surfaces.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_review_archive() && ! in_array( 'cr-review-archive', $classes, true ) ) {
		$classes[] = 'cr-review-archive';
	}
	if ( is_review_single() && ! in_array( 'cr-review-single', $classes, true ) ) {
		$classes[] = 'cr-review-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/** Per-request count of archive covers already prepared. */
function &cover_count(): int {
	static $count = 0;
	return $count;
}

/** Reset archive-page adapter state between renders and tests. */
function reset_page_state(): void {
	$count = &cover_count();
	$count = 0;
}

/**
 * Set or replace one attribute on an opening tag.
 *
 * @param string $tag   Opening tag HTML.
 * @param string $name  Attribute name.
 * @param string $value Attribute value.
 * @return string
 */
function set_opening_attribute( string $tag, string $name, string $value ): string {
	$pattern = '/\s' . preg_quote( $name, '/' ) . '=(["\']).*?\1/i';
	if ( preg_match( $pattern, $tag ) ) {
		return (string) preg_replace( $pattern, ' ' . $name . '="' . esc_attr( $value ) . '"', $tag, 1 );
	}
	return (string) preg_replace( '/\s*\/?>$/', ' ' . $name . '="' . esc_attr( $value ) . '"$0', $tag, 1 );
}

/**
 * Remove one attribute from an opening tag.
 *
 * @param string $tag  Opening tag HTML.
 * @param string $name Attribute name.
 * @return string
 */
function remove_opening_attribute( string $tag, string $name ): string {
	return (string) preg_replace( '/\s' . preg_quote( $name, '/' ) . '=(["\']).*?\1/i', '', $tag, 1 );
}

/**
 * Mark review archive covers and prioritize the first few images.
 *
 * @param string               $html     Rendered review-entry block.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function prepare_entry( string $html, array $block, $instance = null ): string {
	unset( $block, $instance );

	if ( ! preg_match( '/<figure\b[^>]*\bclass="[^"]*(?<![\w-])pkiw-review-entry__figure(?![\w-])[^"]*"[^>]*>/i', $html, $figure, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$figure_start = (int) $figure[0][1];
	$figure_tag   = set_opening_attribute( $figure[0][0], 'data-cr-cover-fallback', 'true' );
	$html         = substr( $html, 0, $figure_start ) . $figure_tag . substr( $html, $figure_start + strlen( $figure[0][0] ) );
	$search_from  = $figure_start + strlen( $figure_tag );
	if ( ! preg_match( '/<img\b[^>]*\bclass="[^"]*(?<![\w-])pkiw-review-entry__cover(?![\w-])[^"]*"[^>]*>/i', $html, $image, PREG_OFFSET_CAPTURE, $search_from ) ) {
		return $html;
	}

	$count = &cover_count();
	++$count;
	$image_tag = $image[0][0];
	if ( $count <= EAGER_COVERS ) {
		$image_tag = set_opening_attribute( $image_tag, 'loading', 'eager' );
		$image_tag = set_opening_attribute( $image_tag, 'fetchpriority', 'high' );
	} else {
		$image_tag = set_opening_attribute( $image_tag, 'loading', 'lazy' );
		$image_tag = remove_opening_attribute( $image_tag, 'fetchpriority' );
	}

	$image_start = (int) $image[0][1];
	return substr( $html, 0, $image_start ) . $image_tag . substr( $html, $image_start + strlen( $image[0][0] ) );
}
add_filter( 'render_block_post-kinds-indieweb/review-entry', __NAMESPACE__ . '\\prepare_entry', 10, 3 );
