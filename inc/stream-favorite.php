<?php
/**
 * Favorite Stream card: a paper clipping with a yellow star.
 *
 * The card is rebuilt from `\PKIW\kind_facts()` and prints the star sticker
 * with the live kind label, the title, the cited author when stored and the
 * post date. It has exactly one link, to the favorite's permalink. The cited
 * URL rides as hidden data, so the Stream never offers a second way out. A
 * protected favorite keeps the plugin's own protected card.
 *
 * Shared helpers live in inc/favorite.php, which functions.php loads with
 * this file.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamFavorite;

use function Courtneyr\Child\Favorite\block_post_id;
use function Courtneyr\Child\Favorite\facts;
use function Courtneyr\Child\Favorite\heading_level;
use function Courtneyr\Child\Favorite\is_clipping;
use function Courtneyr\Child\Favorite\is_favorite;
use function Courtneyr\Child\Favorite\kind_label;
use function Courtneyr\Child\Favorite\star_svg;
use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The favorite-of property as hidden data on the entry.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @return string Hidden h-cite, or '' when no URL is stored.
 */
function cite_html( array $facts ): string {
	$url = esc_url( (string) ( $facts['url'] ?? '' ) );
	if ( '' === $url ) {
		return '';
	}
	$inner = '';
	$title = trim( (string) ( $facts['cited_title'] ?? '' ) );
	if ( '' !== $title ) {
		$inner .= '<data class="p-name" value="' . esc_attr( $title ) . '"></data>';
	}
	$author = trim( (string) ( $facts['author'] ?? '' ) );
	if ( '' !== $author ) {
		$inner .= '<span class="p-author h-card"><data class="p-name" value="' . esc_attr( $author ) . '"></data></span>';
	}
	$inner .= '<data class="u-url" value="' . $url . '"></data>';
	return '<span class="pk-kind-cite h-cite u-favorite-of" hidden>' . $inner . '</span>';
}

/**
 * Build the favorite Stream card.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Favorite facts.
 * @param int                  $level Heading level.
 * @return string
 */
function card_html( \WP_Post $post, array $facts, int $level ): string {
	$classes = 'pk-title cr-fav-card__title' . ( ! empty( $facts['name_is_title'] ) ? ' p-name' : '' );
	$author  = trim( (string) ( $facts['author'] ?? '' ) );

	$out  = '<article class="cr-fav-card k-favorite">';
	$out .= '<span class="cr-fav-star">' . star_svg() . '<span class="pk-kindlabel">' . esc_html( kind_label( 'stream-card' ) ) . '</span></span>';
	$out .= '<div class="cr-fav-card__clipping">';
	$out .= '<h' . $level . ' class="' . esc_attr( $classes ) . '"><a class="u-url cr-fav-link" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( (string) ( $facts['name'] ?? '' ) ) . '</a></h' . $level . '>';
	if ( '' !== $author ) {
		$out .= '<p class="pk-sub cr-fav-card__author">' . esc_html( $author ) . '</p>';
	}
	$out .= '<p class="cr-fav-card__date"><time class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time></p>';
	$out .= '</div>';
	return $out . cite_html( $facts ) . '</article>';
}

/**
 * Replace a favorite's Stream card with the clipping.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's post ID.
 * @return string
 */
function card( string $html, array $block, $instance ): string {
	if ( '' === $html || is_clipping( $block ) || ! is_stream_surface() ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! is_favorite( $post ) || post_password_required( $post ) ) {
		return $html;
	}

	$facts = facts( $post_id );
	return array() === $facts ? $html : card_html( $post, $facts, heading_level( $block, 2 ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\card', 10, 3 );
