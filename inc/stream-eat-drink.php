<?php
/**
 * /stream eat and drink cards: a compact order ticket and a coaster
 * (PKIW issue 230).
 *
 * The plugin's eat-card / drink-card is the source (a post whose body holds
 * more than the card gets the plugin's generic stream card; the theme
 * renders the kind card itself then, as the read cards do). This file reads
 * what that card printed and sets out a small card: the kind, the dish or
 * drink as the card's one link, the cuisine and restaurant (or the drink
 * type and brand), the rating as text, the date, the note and a small photo.
 *
 * The whole card is one link to the single: the title link's box covers the
 * card, so there is one keyboard stop and one link name. There is no map
 * link, no address and no coordinate here, printed or hidden; those stay on
 * the single, inside its map slip. The restaurant shows only when the
 * plugin's privacy rule printed it.
 *
 * assets/css/cr-eat-drink.css draws both cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamOrder;

use function Courtneyr\Child\SingleOrder\parts;
use function Courtneyr\Child\StreamMedia\find_block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set an eat or drink stream card out as a compact ticket or coaster.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$kind = has_term( 'eat', 'kind', $post ) ? 'eat' : ( has_term( 'drink', 'kind', $post ) ? 'drink' : '' );
	$card = '' !== $kind ? find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' ) : null;
	if ( null === $card ) {
		return $html;
	}

	// The kind card: the one the stream card holds, or the plugin's own render of it.
	$source = $html;
	if ( false === strpos( $source, 'pk-card k-' . $kind ) ) {
		$source = (string) render_block( $card );
	}
	$at    = strpos( $source, 'pk-card k-' . $kind );
	$open  = false !== $at ? strrpos( substr( $source, 0, $at ), '<article' ) : false;
	$close = false !== $open ? strpos( $source, '</article>', $open ) : false;
	if ( false === $close ) {
		return $html;
	}
	$parts  = parts( substr( $source, $open, $close + 10 - $open ), $kind );
	$is_eat = 'eat' === $kind;
	$name   = '' !== $parts['name'] ? $parts['name'] : html_entity_decode( get_the_title( $post ), ENT_QUOTES );
	$term   = get_term_by( 'slug', $kind, 'kind' );

	// Cuisine then restaurant, or type then brand: only what the card printed.
	$sub = array_filter( array( esc_html( $parts['sort'] ), $parts['maker_html'] ) );

	$stamp = '' !== $parts['when'] ? strtotime( $parts['when'] ) : false;
	$iso   = false !== $stamp ? $parts['when'] : (string) get_post_time( 'c', true, $post );
	$day   = false !== $stamp ? (string) wp_date( (string) get_option( 'date_format' ), $stamp ) : (string) get_the_date( '', $post );

	// The small photo: the card's own, or else the featured image, with the alt text WordPress holds.
	$photo = '';
	if ( is_array( $parts['photo'] ) && '' !== $parts['photo']['src'] ) {
		$photo = '<img class="u-photo" src="' . esc_url( $parts['photo']['src'] ) . '" alt="' . esc_attr( $parts['photo']['alt'] ) . '" loading="lazy" decoding="async" />';
	} elseif ( has_post_thumbnail( $post ) ) {
		$photo = get_the_post_thumbnail( $post, 'medium' );
	}

	$out  = '<article class="pk-card pk-card--stream k-' . $kind . ' p-' . ( $is_eat ? 'ate' : 'drank' ) . ' h-food cr-chit cr-chit--' . $kind . ( '' !== $photo ? ' cr-chit--has-photo' : '' ) . '">';
	$out .= '<div class="cr-chit__body">';
	$out .= '<p class="cr-chit__kind">' . esc_html( $term instanceof \WP_Term ? $term->name : ( $is_eat ? __( 'Eat', 'courtneyr-child' ) : __( 'Drink', 'courtneyr-child' ) ) ) . '</p>';
	$out .= '<h2 class="cr-chit__title p-name"><a class="cr-chit__link u-url" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $name ) . '</a></h2>';
	if ( $sub ) {
		$out .= '<p class="cr-chit__sub">' . implode( '<span class="cr-chit__dot" aria-hidden="true"> · </span>', $sub ) . '</p>';
	}
	if ( '' !== $parts['rated'] ) {
		$out .= '<p class="cr-chit__rating">' . esc_html( $parts['rated'] ) . ( '' !== $parts['rating'] ? '<data class="p-rating" value="' . esc_attr( $parts['rating'] ) . '" hidden></data>' : '' ) . '</p>';
	}
	$out .= '<p class="cr-chit__date"><time class="dt-published" datetime="' . esc_attr( $iso ) . '">' . esc_html( $day ) . '</time></p>';
	if ( '' !== $parts['note'] ) {
		$out .= '<p class="cr-chit__note p-content">' . esc_html( $parts['note'] ) . '</p>';
	}
	$out .= '</div>';
	if ( '' !== $photo ) {
		$out .= '<figure class="cr-chit__photo">' . $photo . '</figure>';
	}
	$out .= $parts['marker'] . '</article>';

	// Swap the card in where the stream card held one; otherwise it is the card.
	if ( $source === $html ) {
		return substr( $html, 0, $open ) . $out . substr( $html, $close + 10 );
	}
	return $out;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\card', 10, 3 );
