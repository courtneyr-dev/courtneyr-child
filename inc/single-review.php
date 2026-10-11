<?php
/**
 * Single review header adapter.
 *
 * The plugin owns the h-review wrapper and machine-readable review facts.
 * This file edits only the block-theme header around that content: the
 * stored picture, category chip, item byline, rating box, and duplicate
 * star-rating suppression.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleReview;

use Courtneyr\Child\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a block className contains one complete class token.
 *
 * @param array<string, mixed> $block Parsed block.
 * @param string               $token Class token.
 * @return bool
 */
function block_has_class( array $block, string $token ): bool {
	$classes = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
	return in_array( $token, is_array( $classes ) ? $classes : array(), true );
}

/**
 * Whether the block instance belongs to the queried post.
 *
 * @param mixed $instance Block instance.
 * @return bool
 */
function matches_queried_post( $instance ): bool {
	if ( $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ) {
		return (int) get_queried_object_id() === (int) $instance->context['postId'];
	}
	return true;
}

/**
 * The stored review picture for the single header.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function picture_html( int $post_id ): string {
	if ( ! function_exists( '\\PKIW\\kind_picture' ) ) {
		return '';
	}

	$picture = (array) \PKIW\kind_picture( $post_id );
	$url     = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}

	$alt           = (string) ( $picture['alt'] ?? '' );
	$attachment_id = (int) ( $picture['attachment_id'] ?? 0 );
	$image         = '';
	if ( $attachment_id > 0 ) {
		$image = wp_get_attachment_image(
			$attachment_id,
			'large',
			false,
			array(
				'class'         => 'cr-review__img',
				'alt'           => $alt,
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
	}
	if ( '' === $image ) {
		$image = '<img class="cr-review__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="eager" fetchpriority="high" decoding="async" />';
	}

	return '<figure class="cr-review__picture" data-cr-cover-fallback>' . $image . '</figure>';
}

/**
 * Review item byline.
 *
 * @param array<string, mixed> $facts Review facts.
 * @return string
 */
function byline_html( array $facts ): string {
	$name = trim( (string) ( $facts['item_name'] ?? '' ) );
	if ( '' === $name ) {
		return '';
	}

	$url   = (string) ( $facts['item_url'] ?? '' );
	$value = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
	return '<p class="cr-review__byline">' . $value . '</p>';
}

/**
 * Review rating box.
 *
 * @param array<string, mixed> $facts Review facts.
 * @return string
 */
function rating_box_html( array $facts ): string {
	$rating = Review\rating_html( $facts );
	return '' === $rating ? '' : '<div class="cr-review__rating">' . $rating . '</div>';
}

/**
 * Review category chip.
 *
 * @param array<string, mixed> $facts Review facts.
 * @return string
 */
function chip_html( array $facts ): string {
	$category = (array) ( $facts['category'] ?? array() );
	$name     = trim( (string) ( $category['name'] ?? '' ) );
	if ( '' === $name ) {
		return '';
	}

	$link  = (string) ( $category['link'] ?? '' );
	$value = '' !== $link ? '<a href="' . esc_url( $link ) . '" rel="tag">' . esc_html( $name ) . '</a>' : '<span>' . esc_html( $name ) . '</span>';
	return '<div class="cr-review__chip single-post__category">' . $value . '</div>';
}

/**
 * Replace the template term blocks with the review category chip.
 *
 * @param string               $html  Rendered post-terms block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function header_terms( string $html, array $block ): string {
	if ( ! Review\is_review_single() ) {
		return $html;
	}
	if ( block_has_class( $block, 'single-post__kind' ) ) {
		return '';
	}
	if ( block_has_class( $block, 'single-post__category' ) ) {
		return chip_html( Review\facts( get_queried_object_id() ) );
	}
	return $html;
}
add_filter( 'render_block_core/post-terms', __NAMESPACE__ . '\\header_terms', 10, 2 );

/**
 * Remove the template featured-image block on review singles.
 *
 * @param string               $html  Rendered featured image block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_featured_image( string $html, array $block ): string {
	return Review\is_review_single() && block_has_class( $block, 'single-post__featured' ) ? '' : $html;
}
add_filter( 'render_block_core/post-featured-image', __NAMESPACE__ . '\\drop_featured_image', 10, 2 );

/**
 * Append the review byline and rating box under the H1.
 *
 * @param string               $html     Rendered title block.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function title_extras( string $html, array $block, $instance = null ): string {
	if ( ! Review\is_review_single() || ! matches_queried_post( $instance ) || ! block_has_class( $block, 'single-post__title' ) ) {
		return $html;
	}

	$facts = Review\facts( get_queried_object_id() );
	return $html . byline_html( $facts ) . held_date() . rating_box_html( $facts );
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\title_extras', 30, 3 );

/**
 * The header date block, held back from the lede row until the title prints it.
 *
 * @param string|null $html Rendered date to hold; null takes the held date and clears it.
 * @return string The held date when taking, '' otherwise.
 */
function held_date( ?string $html = null ): string {
	static $held = '';
	if ( null !== $html ) {
		$held = $html;
		return '';
	}
	$out  = $held;
	$held = '';
	return $out;
}

/**
 * Move the review single's header date from the lede row to between the byline and the star box.
 *
 * The lede row keeps the category chip alone, so the header reads chip,
 * title, byline, date, rating. Runs after the microformats filter, so the
 * date moves with its hidden author card.
 *
 * @param string               $html     Rendered post-date block.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function hold_header_date( string $html, array $block, $instance = null ): string {
	if ( ! Review\is_review_single() || ! matches_queried_post( $instance ) || ! block_has_class( $block, 'single-post__date' ) ) {
		return $html;
	}
	return held_date( $html );
}
add_filter( 'render_block_core/post-date', __NAMESPACE__ . '\\hold_header_date', 20, 3 );

/**
 * Insert the stored picture as the first child of the single header group.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function header_picture( string $html, array $block ): string {
	if (
		! Review\is_review_single()
		|| 'core/group' !== ( $block['blockName'] ?? '' )
		|| ! block_has_class( $block, 'single-post__header' )
	) {
		return $html;
	}

	$picture = picture_html( get_queried_object_id() );
	if ( '' === $picture || ! preg_match( '/<[a-z][a-z0-9-]*\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$offset = (int) $match[0][1] + strlen( $match[0][0] );
	return substr( $html, 0, $offset ) . $picture . substr( $html, $offset );
}
add_filter( 'render_block', __NAMESPACE__ . '\\header_picture', 10, 2 );

/**
 * Suppress duplicate plugin star-rating blocks on review singles.
 *
 * @param string               $html     Rendered star-rating block.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function drop_star_rating( string $html, array $block, $instance = null ): string {
	unset( $block, $instance );

	return Review\is_review_single() ? '' : $html;
}
add_filter( 'render_block_post-kinds-indieweb/star-rating', __NAMESPACE__ . '\\drop_star_rating', 20, 3 );

/**
 * Remove the syndication area from a password-protected review single.
 *
 * The protected review shows only its title and the password form, so the
 * whole wrapper goes, heading included.
 *
 * @param string               $html  Rendered group block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_protected_syndication( string $html, array $block ): string {
	return Review\is_review_single() && post_password_required( get_queried_object_id() ) && block_has_class( $block, 'single-post__syndication' ) ? '' : $html;
}
add_filter( 'render_block_core/group', __NAMESPACE__ . '\\drop_protected_syndication', 10, 2 );
