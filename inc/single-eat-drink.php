<?php
/**
 * Single Eat and Drink posts: an order ticket and a coaster (PKIW issue 230).
 *
 * The plugin's eat-card / drink-card prints one article: a label, the dish
 * or drink as a heading, a line with the restaurant and cuisine (or the
 * drink type and brand), the place, stars, a photo, a note and a time. It
 * applies the post's location privacy before it prints.
 *
 * This file reads the pieces that article printed and sets them out again
 * inside the same article, which becomes the placemat:
 *
 * 1. The order: an eat post is one ticket on a clipboard (photo, label,
 *    title, then Restaurant, Cuisine, Ate and Rated, then Notes); a drink
 *    post is a taped photo beside a coaster (label, title, Type, Brand,
 *    Drank, Rated, Notes).
 * 2. The check-in map slip (inc/checkin-map.php), under the order and on
 *    the same placemat, when the plugin printed a place.
 *
 * The post title is the page's one h1 and sits inside the order. The card's
 * own name carries the food's p-name there when it equals the title; when it
 * differs it prints once as a labelled fact. The template's post header and
 * featured image don't print on these pages, so nothing shows twice. Post
 * navigation stays where the template puts it, outside the placemat.
 *
 * Nothing here reads location meta or block attributes. Every place, every
 * coordinate and every photo comes from what the plugin rendered, so the
 * plugin's privacy rule is the only one. p-ate / p-drank, h-food, the
 * location h-card, p-rating, u-photo, p-content and dt-published stay
 * inside the article. Each module prints only when its data exists.
 * assets/css/cr-eat-drink.css draws it under body.cr-order-single.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleOrder;

use function Courtneyr\Child\CheckinMap\slip;
use function Courtneyr\Child\StreamMedia\find_block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which order the queried post is: one with the plugin's card for its kind.
 *
 * @return string 'eat', 'drink', or '' when this is not such a single.
 */
function kind(): string {
	if ( ! \is_singular( 'post' ) ) {
		return '';
	}
	$post = \get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return '';
	}
	foreach ( array( 'eat', 'drink' ) as $kind ) {
		if ( \has_term( $kind, 'kind', $post ) && null !== find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' ) ) {
			return $kind;
		}
	}
	return '';
}

/**
 * The pieces the plugin's card printed, read from its article.
 *
 * @param string $article The card's `<article>…</article>`.
 * @param string $kind    'eat' or 'drink'.
 * @return array<string, mixed>
 */
function parts( string $article, string $kind ): array {
	$dom      = new \DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $article . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$xpath = new \DOMXPath( $dom );
	$has   = static fn( string $name ): string => 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
	$one   = static function ( string $query, ?\DOMNode $within = null ) use ( $xpath ): ?\DOMElement {
		$found = $xpath->query( $query, $within );
		$node  = $found ? $found->item( 0 ) : null;
		return $node instanceof \DOMElement ? $node : null;
	};
	$text  = static fn( ?\DOMElement $node ): string => $node ? trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';

	// The line under the title: restaurant and cuisine, or drink type and brand.
	$sub   = $one( '//p[' . $has( 'pk-sub' ) . ' and not(' . $has( 'p-location' ) . ') and not(' . $has( 'pk-stream-date' ) . ')]' );
	$maker = $sub ? $one( './/span[' . $has( 'eat' === $kind ? 'p-location' : 'p-author' ) . ']', $sub ) : null;
	$sort  = $sub ? $one( 'eat' === $kind ? './/em' : './span[not(@class)]', $sub ) : null;

	// The place, as far as the plugin's privacy rule let it print.
	$where = $one( '//p[' . $has( 'pk-sub' ) . ' and ' . $has( 'p-location' ) . ']' );
	$place = null;
	if ( $where ) {
		$named = $one( './/*[' . $has( 'p-name' ) . ']', $where );
		$geo   = $one( './/data[' . $has( 'h-geo' ) . ']', $where );
		$point = $geo ? array_map( 'trim', explode( ',', (string) $geo->getAttribute( 'value' ) ) ) : array();
		$place = array(
			'name'     => $text( $named ),
			'url'      => $named && 'a' === strtolower( $named->nodeName ) ? (string) $named->getAttribute( 'href' ) : '',
			'street'   => $text( $one( './/*[' . $has( 'p-street-address' ) . ']', $where ) ),
			'locality' => $text( $one( './/*[' . $has( 'p-locality' ) . ']', $where ) ),
			'region'   => $text( $one( './/*[' . $has( 'p-region' ) . ']', $where ) ),
			'country'  => $text( $one( './/*[' . $has( 'p-country-name' ) . ']', $where ) ),
			'lat'      => 2 === count( $point ) && is_numeric( $point[0] ) && is_numeric( $point[1] ) ? (float) $point[0] : null,
			'lon'      => 2 === count( $point ) && is_numeric( $point[0] ) && is_numeric( $point[1] ) ? (float) $point[1] : null,
		);
	}

	$stars  = $one( '//*[' . $has( 'pk-stars' ) . ']' );
	$rating = $one( '//data[' . $has( 'p-rating' ) . ']' );
	$photo  = $one( '//*[' . $has( 'pk-embed--photo' ) . ']//img' );
	$time   = $one( '//*[' . $has( 'pk-meta' ) . ']//time' );
	$marker = $one( '//data[' . $has( 'u-ate' ) . ' or ' . $has( 'u-drank' ) . ']' );

	return array(
		'name'       => $text( $one( '//*[' . $has( 'pk-title' ) . ']' ) ),
		'maker'      => $text( $maker ),
		'maker_html' => $maker ? (string) $dom->saveHTML( $maker ) : '',
		'sort'       => $text( $sort ),
		'place'      => $place,
		'rated'      => $stars ? trim( (string) $stars->getAttribute( 'aria-label' ) ) : '',
		'rating'     => $rating ? trim( (string) $rating->getAttribute( 'value' ) ) : '',
		'photo'      => $photo ? array(
			'src' => (string) $photo->getAttribute( 'src' ),
			'alt' => (string) $photo->getAttribute( 'alt' ),
		) : null,
		'note'       => $text( $one( '//p[' . $has( 'pk-note' ) . ']' ) ),
		'when'       => $time ? (string) $time->getAttribute( 'datetime' ) : '',
		'marker'     => $marker ? (string) $dom->saveHTML( $marker ) : '',
	);
}

/**
 * The order's photo: the card's own picture, or else the featured image.
 * The picture keeps the alt text WordPress holds for it.
 *
 * @param \WP_Post                   $post  Post.
 * @param array<string, string>|null $photo The card's photo, when it printed one.
 * @param string                     $class Class for the figure.
 * @return string
 */
function photo( \WP_Post $post, ?array $photo, string $class ): string {
	if ( $photo && '' !== $photo['src'] ) {
		$img = '<img class="u-photo" src="' . esc_url( $photo['src'] ) . '" alt="' . esc_attr( $photo['alt'] ) . '" loading="lazy" decoding="async" />';
	} elseif ( \has_post_thumbnail( $post ) ) {
		$img = \get_the_post_thumbnail( $post, 'large', array( 'loading' => 'eager' ) );
	} else {
		return '';
	}
	return '<figure class="' . esc_attr( $class ) . '">' . $img . '</figure>';
}

/**
 * One labelled fact for the order's facts list.
 *
 * @param string $label Label.
 * @param string $html  Value, already escaped.
 * @return string Empty when the value is.
 */
function fact( string $label, string $html ): string {
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}
	return '<div class="cr-order__fact"><dt class="cr-order__label">' . esc_html( $label ) . '</dt><dd class="cr-order__value">' . $html . '</dd></div>';
}

/**
 * The order and its map slip, set out from the pieces the card printed.
 *
 * @param \WP_Post             $post  Post.
 * @param string               $kind  'eat' or 'drink'.
 * @param array<string, mixed> $parts See parts().
 * @return string The inside of the placemat article.
 */
function order( \WP_Post $post, string $kind, array $parts ): string {
	$is_eat = 'eat' === $kind;
	$title  = trim( get_the_title( $post ) );
	$name   = '' !== $parts['name'] ? $parts['name'] : $title;
	$same   = 0 === strcasecmp( $name, html_entity_decode( $title, ENT_QUOTES ) );
	$term   = get_term_by( 'slug', $kind, 'kind' );
	$label  = $term instanceof \WP_Term ? $term->name : ( $is_eat ? __( 'Eat', 'courtneyr-child' ) : __( 'Drink', 'courtneyr-child' ) );
	$link   = $term instanceof \WP_Term ? get_term_link( $term ) : '';

	$head  = '<p class="cr-order__kind">';
	$head .= is_string( $link ) && '' !== $link ? '<a class="cr-order__kind-link" href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
	$head .= '</p>';
	// One title: the post's, as the page's h1. It is also the food's name when the card says the same.
	$head .= '<h1 class="cr-order__title' . ( $same ? ' p-name' : '' ) . '">' . esc_html( html_entity_decode( $title, ENT_QUOTES ) ) . '</h1>';

	// The time: the card's, or the post's own when the card stores none.
	$stamp = '' !== $parts['when'] ? strtotime( $parts['when'] ) : false;
	if ( false !== $stamp ) {
		$when = '<time class="dt-published" datetime="' . esc_attr( $parts['when'] ) . '">' . esc_html( (string) wp_date( (string) get_option( 'date_format' ), $stamp ) ) . '</time>';
	} else {
		$when = '<time datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>';
	}
	$rated = '' !== $parts['rated'] ? esc_html( $parts['rated'] ) . ( '' !== $parts['rating'] ? '<data class="p-rating" value="' . esc_attr( $parts['rating'] ) . '" hidden></data>' : '' ) : '';

	$facts  = $same ? '' : fact( $is_eat ? __( 'Dish', 'courtneyr-child' ) : __( 'Drink', 'courtneyr-child' ), '<span class="p-name">' . esc_html( $name ) . '</span>' );
	$facts .= $is_eat
		? fact( __( 'Restaurant', 'courtneyr-child' ), $parts['maker_html'] ) . fact( __( 'Cuisine', 'courtneyr-child' ), esc_html( $parts['sort'] ) )
		: fact( __( 'Type', 'courtneyr-child' ), esc_html( $parts['sort'] ) ) . fact( __( 'Brand', 'courtneyr-child' ), $parts['maker_html'] );
	$facts .= fact( $is_eat ? __( 'Ate', 'courtneyr-child' ) : __( 'Drank', 'courtneyr-child' ), $when );
	$facts .= fact( __( 'Rated', 'courtneyr-child' ), $rated );

	$notes = '' !== $parts['note']
		? '<div class="cr-order__notes"><h2 class="cr-order__notes-title">' . esc_html__( 'Notes', 'courtneyr-child' ) . '</h2><p class="cr-order__note p-content">' . esc_html( $parts['note'] ) . '</p></div>'
		: '';

	$photo = photo( $post, $parts['photo'], 'cr-order__photo' );
	$sheet = '<div class="cr-order__sheet"><div class="cr-order__head">' . $head . '</div><dl class="cr-order__facts">' . $facts . '</dl>' . $notes . '</div>';
	$order = '<div class="cr-order cr-order--' . ( $is_eat ? 'ticket' : 'coaster' ) . ( '' !== $photo ? ' cr-order--has-photo' : '' ) . '">' . $photo . $sheet . '</div>';

	// The slip names the place only when the order above doesn't already (a restaurant, or a brand poured at its own bar).
	$named_above = is_array( $parts['place'] ) && '' !== $parts['maker'] && 0 === strcasecmp( $parts['maker'], $parts['place']['name'] );
	$map         = is_array( $parts['place'] ) ? slip( $parts['place'], $named_above ) : '';

	return $order . $map . $parts['marker'];
}

/**
 * Whether an order page is being served.
 *
 * @return bool
 */
function is_order(): bool {
	return '' !== kind();
}

/**
 * The template's post header and featured image don't print on an order
 * page: the order carries the title, the label, the date and the photo.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_template_header( string $html, array $block ): string {
	$name  = (string) ( $block['blockName'] ?? '' );
	$class = (string) ( $block['attrs']['className'] ?? '' );
	$is    = ( 'core/group' === $name && str_contains( $class, 'single-post__header' ) ) || ( 'core/post-featured-image' === $name && str_contains( $class, 'single-post__featured' ) );
	return $is && is_order() ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\drop_template_header', 10, 2 );

/**
 * Rebuild the plugin's card into the placemat: the order, then the map slip.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function placemat( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	$kind = kind();
	$post = \get_post();
	if ( '' === $kind || ! $post instanceof \WP_Post ) {
		return $html;
	}
	$at    = strpos( $html, 'pk-card k-' . $kind );
	$open  = false !== $at ? strrpos( substr( $html, 0, $at ), '<article' ) : false;
	$close = false !== $open ? strpos( $html, '</article>', $open ) : false;
	if ( false === $close ) {
		return $html;
	}
	$article = substr( $html, $open, $close + 10 - $open );
	$parts   = parts( $article, $kind );

	// The article stays the h-food item; it is the placemat now, not a card.
	$tags = new \WP_HTML_Tag_Processor( $article );
	$root = '';
	if ( $tags->next_tag( 'article' ) ) {
		$tags->remove_class( 'pk-card' );
		$tags->add_class( 'cr-placemat' );
		$tags->add_class( 'cr-placemat--' . $kind );
		$root = substr( $tags->get_updated_html(), 0, (int) strpos( $tags->get_updated_html(), '>' ) + 1 );
	}
	if ( '' === $root ) {
		return $html;
	}
	$after = substr( $html, $close + 10 );

	// Body copy after the card that only says the note again doesn't print twice.
	if ( '' !== $parts['note'] && preg_match( '/^\s*((?:<p class="wp-block-paragraph"[^>]*>.*?<\/p>\s*)+)/s', $after, $body ) ) {
		$flat = static fn( string $text ): string => strtolower( trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES ) ) ) );
		if ( $flat( $body[1] ) === $flat( $parts['note'] ) ) {
			$after = substr( $after, strlen( $body[0] ) );
		}
	}

	return substr( $html, 0, $open ) . $root . order( $post, $kind, $parts ) . '</article>' . $after;
}
add_filter( 'render_block', __NAMESPACE__ . '\\placemat', 20, 2 );

/**
 * Load the order paint where an order can appear: its single, and the
 * Stream surfaces that show the compact cards.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_order() && ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-eat-drink',
		COURTNEYR_CHILD_URI . '/assets/css/cr-eat-drink.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Mark the page for the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	$kind = kind();
	if ( '' !== $kind ) {
		$classes[] = 'cr-order-single';
		$classes[] = 'cr-order-single--' . $kind;
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );
