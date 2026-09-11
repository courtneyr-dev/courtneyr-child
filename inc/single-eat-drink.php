<?php
/**
 * Single Eat and Drink posts: a diner placemat.
 *
 * The plugin's eat-card / drink-card prints one article (label, title,
 * sub, a location block, stars, a photo, a note, a timestamp) and no map.
 * This file turns that article into a placemat somebody recorded a meal
 * onto: the label becomes the order-box header (What I ate / What I
 * drank), the plugin's location block moves into a WHERE column with a
 * real OpenStreetMap embed built from the stored coordinates (the same
 * embed the check-in card uses), the photo gets a handwritten caption, a
 * long note becomes "Notes from the table", the timestamp becomes the
 * receipt line, a dated seal and hand-drawn doodles finish the sheet,
 * and three handwritten margin notes sit beside it in the journal grid.
 *
 * Everything the card printed still prints inside the same article, so
 * the h-food, p-location h-card, h-geo, p-rating, u-photo and
 * dt-published stay where microformat parsers expect them. No fact is
 * invented: doodles are decorative and hidden from assistive technology,
 * handwriting is theme copy chosen by the post's seed, and every module
 * (photo, WHERE, map, rating, note) renders only when its data exists.
 * cr-post-kinds.css paints it under body.single-post.kind-eat / .kind-drink.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SinglePlacemat;

use function Courtneyr\Child\Journal\aside;
use function Courtneyr\Child\Journal\card_attrs;
use function Courtneyr\Child\Journal\margin_lines;
use function Courtneyr\Child\Journal\skip_lazy_iframes;
use function Courtneyr\Child\Journal\wrap;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\render;
use function Courtneyr\Child\Stamps\seed;
use function Courtneyr\Child\StreamMedia\find_block;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A note longer than this leaves the order box for "Notes from the table".
 */
const NOTE_INLINE_LIMIT = 140;

/**
 * Which placemat kind the queried post is.
 *
 * @return string 'eat', 'drink', or '' when this is not such a single.
 */
function kind(): string {
	if ( ! \is_singular( 'post' ) ) {
		return '';
	}
	$id = \get_queried_object_id();
	if ( \has_term( 'eat', 'kind', $id ) ) {
		return 'eat';
	}
	if ( \has_term( 'drink', 'kind', $id ) ) {
		return 'drink';
	}
	return '';
}

/**
 * The card's attributes, with the meta fallback (inc/journal.php).
 *
 * @param \WP_Post $post Post.
 * @param string   $kind 'eat' or 'drink'.
 * @return array<string, mixed>|null Null when the post holds no card.
 */
function attrs( \WP_Post $post, string $kind ): ?array {
	$card = find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' );
	if ( null === $card ) {
		return null;
	}
	return card_attrs( $post, 'post-kinds-indieweb/' . $kind . '-card', (array) ( $card['attrs'] ?? array() ) );
}

/**
 * When meta filled attributes the block lacked, let the plugin render the
 * card again from the full set, and swap that article into the HTML, so
 * the venue, photo and rating print through the plugin's own markup.
 *
 * @param string               $html  HTML holding the card's article.
 * @param \WP_Post             $post  Post.
 * @param string               $kind  'eat' or 'drink'.
 * @param array<string, mixed> $attrs Attributes after the fallback.
 * @return string
 */
function rerender_with_meta( string $html, \WP_Post $post, string $kind, array $attrs ): string {
	$card = find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' );
	if ( null === $card || $attrs == (array) ( $card['attrs'] ?? array() ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- filled values are compared by value.
		return $html;
	}
	$pos = strpos( $html, 'pk-card k-' . $kind );
	$open = false !== $pos ? strrpos( substr( $html, 0, $pos ), '<article' ) : false;
	$end  = false !== $open ? strpos( $html, '</article>', $open ) : false;
	if ( false === $end ) {
		return $html;
	}
	$fresh = render_block( array_merge( $card, array( 'attrs' => $attrs ) ) );
	if ( '' === $fresh || false === strpos( $fresh, 'pk-card k-' . $kind ) ) {
		return $html;
	}
	return substr( $html, 0, $open ) . $fresh . substr( $html, $end + 10 );
}

/**
 * The map: the same OpenStreetMap embed the plugin's check-in card
 * builds (public precision, a marker, a larger-map link), from the
 * card's stored coordinates.
 *
 * @param float  $lat  Latitude.
 * @param float  $lon  Longitude.
 * @param string $name Venue name for the frame's title.
 * @return string
 */
function map_html( float $lat, float $lon, string $name ): string {
	$d     = 0.01;
	$src   = sprintf( 'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F', $lon - $d, $lat - $d, $lon + $d, $lat + $d, $lat, $lon );
	$large = sprintf( 'https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=16/%F/%F', $lat, $lon, $lat, $lon );
	$title = '' !== $name
		? sprintf( /* translators: %s: venue name */ __( 'Map of %s', 'courtneyr-child' ), $name )
		: __( 'Map of this location', 'courtneyr-child' );
	return '<div class="pk-embed pk-embed--map">'
		. '<iframe title="' . esc_attr( $title ) . '" width="100%" height="200" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="' . esc_url( $src ) . '" loading="lazy"></iframe>'
		. '<a href="' . esc_url( $large ) . '" class="pk-map-link" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on OpenStreetMap', 'courtneyr-child' ) . ' <span class="pk-map-link__arrow" aria-hidden="true">↗</span></a>'
		. '</div>';
}

/**
 * Hand-drawn diner doodles: line art in the placemat's ink, four per
 * sheet, chosen by kind (and, for drinks, by the stored drink type).
 * Decorative only.
 *
 * @param string $kind 'eat' or 'drink'.
 * @param string $type The drink card's drinkType.
 * @return string
 */
function doodles( string $kind, string $type ): string {
	$art = array(
		'fish'     => '<path d="M6 25c6-8 13-12 21-12 5 0 9 2 13 6-4 4-8 6-13 6-8 0-15-3-21-11z"/><path d="M38 19l7-5v20l-7-5"/><circle cx="13" cy="23" r="1.4" fill="currentColor" stroke="none"/><path d="M21 17c2 3 2 7 0 11"/>',
		'fries'    => '<path d="M14 22h20l-2 21H16z"/><path d="M18 22l-2-11M24 22V9M30 22l2-11M21 22l-3-9M27 22l3-9"/>',
		'cutlery'  => '<path d="M13 5v11c0 3 2 5 4 5v22M17 21c2 0 4-2 4-5V5M17 5v11"/><path d="M33 5c-4 6-4 12 0 16v22M33 5c3 4 4 10 2 16"/>',
		'lemon'    => '<path d="M8 32a18 18 0 0 1 32-13L8 32z"/><path d="M12 30l24-10M15 29l12-9M18 31l16-4"/>',
		'plate'    => '<circle cx="24" cy="24" r="18"/><circle cx="24" cy="24" r="11"/>',
		'mug'      => '<path d="M11 18h22v14a8 8 0 0 1-8 8h-6a8 8 0 0 1-8-8z"/><path d="M33 22h4a4 4 0 0 1 0 8h-4"/><path d="M17 5c-2 3 2 5 0 8M24 5c-2 3 2 5 0 8"/>',
		'bean'     => '<ellipse cx="24" cy="24" rx="9" ry="13" transform="rotate(-30 24 24)"/><path d="M19 15c4 6 4 12 2 18"/>',
		'teacup'   => '<path d="M9 18h26v8a10 10 0 0 1-10 10h-6a10 10 0 0 1-10-10z"/><path d="M35 20h3a4 4 0 0 1 0 8h-3M7 41h30"/><path d="M20 6c-2 3 2 5 0 8"/>',
		'leaf'     => '<path d="M10 38c2-14 12-24 28-26-2 16-12 26-28 26z"/><path d="M12 36c8-8 14-14 22-20"/>',
		'pint'     => '<path d="M14 9h18l-2 33H16z"/><path d="M12 9c2-4 8-5 12-3 3-3 10-1 10 3"/><path d="M32 17h4a4 4 0 0 1 0 8h-5"/>',
		'hop'      => '<path d="M24 7c-6 6-8 12-4 20 2 4 6 6 8 6 2-6 4-12 2-18-1-4-4-7-6-8z"/><path d="M24 7v26M20 17l4 4 4-4M19 25l5 5 5-5"/>',
		'wine'     => '<path d="M14 6h20c0 12-4 18-10 18S14 18 14 6z"/><path d="M24 24v15M16 41h16"/>',
		'grapes'   => '<circle cx="18" cy="20" r="4"/><circle cx="26" cy="18" r="4"/><circle cx="22" cy="27" r="4"/><circle cx="30" cy="26" r="4"/><circle cx="26" cy="34" r="4"/><path d="M22 12c2-4 6-6 10-6"/>',
		'coupe'    => '<path d="M9 8h30c0 8-7 14-15 14S9 16 9 8z"/><path d="M24 22v15M16 39h16"/>',
		'citrus'   => '<circle cx="24" cy="24" r="12"/><path d="M24 12v24M12 24h24M15.5 15.5l17 17M32.5 15.5l-17 17"/>',
		'glass'    => '<path d="M14 8h20l-2 33H16z"/><path d="M16 23h16"/>',
		'straw'    => '<path d="M14 12h20l-2 29H16z"/><path d="M28 4l4 13"/><circle cx="20" cy="27" r="1.6"/><circle cx="25" cy="32" r="1.3"/><circle cx="22" cy="21" r="1.1"/>',
		'cup'      => '<path d="M14 15h20l-2 26H16z"/><path d="M12 15a12 8 0 0 1 24 0"/><path d="M26 4l4 12"/>',
		'droplet'  => '<path d="M24 6c-5 7-8 11-8 15a8 8 0 0 0 16 0c0-4-3-8-8-15z"/>',
	);
	$sets = array(
		'eat'      => array( 'fish', 'fries', 'cutlery', 'lemon' ),
		'coffee'   => array( 'mug', 'bean', 'cutlery', 'mug' ),
		'tea'      => array( 'teacup', 'leaf', 'leaf', 'teacup' ),
		'beer'     => array( 'pint', 'hop', 'hop', 'pint' ),
		'wine'     => array( 'wine', 'grapes', 'grapes', 'wine' ),
		'cocktail' => array( 'coupe', 'citrus', 'lemon', 'coupe' ),
		'juice'    => array( 'glass', 'citrus', 'lemon', 'glass' ),
		'soda'     => array( 'straw', 'citrus', 'droplet', 'straw' ),
		'smoothie' => array( 'cup', 'grapes', 'citrus', 'cup' ),
		'water'    => array( 'glass', 'droplet', 'droplet', 'glass' ),
		'other'    => array( 'glass', 'citrus', 'plate', 'glass' ),
	);
	$key   = 'eat' === $kind ? 'eat' : ( isset( $sets[ $type ] ) ? $type : 'other' );
	$spots = array( 'tl', 'tr', 'bl', 'br' );
	$out   = '<span class="cr-placemat__doodles" aria-hidden="true">';
	foreach ( $sets[ $key ] as $i => $name ) {
		$out .= '<svg class="cr-placemat__doodle cr-placemat__doodle--' . $spots[ $i ] . ' cr-placemat__doodle--' . esc_attr( $name ) . '" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">' . $art[ $name ] . '</svg>';
	}
	return $out . '</span>';
}

/**
 * Drop the template's featured image when the card already shows that
 * photo, so the placemat's snapshot is the only copy (a photo hero above
 * the placemat is not part of this page).
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function suppress_duplicate_featured_image( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	$kind = kind();
	$post = \get_post();
	if ( '' === $kind || ! $post instanceof \WP_Post ) {
		return $html;
	}
	$a     = attrs( $post, $kind );
	$photo = trim( (string) ( $a['photo'] ?? '' ) );
	if ( '' === $photo ) {
		return $html;
	}
	$thumb_id = (int) \get_post_thumbnail_id( $post );
	if ( $thumb_id > 0 ) {
		$norm = static fn( string $f ): string => (string) preg_replace( '/(-scaled|-\d+x\d+)(?=\.[a-z0-9]+$)/i', '', strtolower( $f ) );
		if ( $norm( (string) \wp_basename( (string) \get_attached_file( $thumb_id ) ) ) !== $norm( (string) \wp_basename( \wp_parse_url( $photo, PHP_URL_PATH ) ?? '' ) ) ) {
			return $html;
		}
	}
	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\suppress_duplicate_featured_image', 10, 2 );

/**
 * The date lede under the H1 (the template's own date hides).
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || '' === kind() ) {
		return $html;
	}
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__title' ) ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	return $html . '<p class="cr-journal__lede"><time class="cr-journal__lede-date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>';
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );

/**
 * Insert markup before the first occurrence of an anchor string.
 *
 * @param string $html   Haystack.
 * @param string $anchor Needle.
 * @param string $insert Markup.
 * @return string Unchanged when the anchor is absent.
 */
function before( string $html, string $anchor, string $insert ): string {
	$pos = strpos( $html, $anchor );
	return false === $pos ? $html : substr( $html, 0, $pos ) . $insert . substr( $html, $pos );
}

/**
 * Rebuild the plugin's card into the placemat.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function journal_page( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	$kind = kind();
	$post = \get_post();
	if ( '' === $kind || ! $post instanceof \WP_Post || false === strpos( $html, 'pk-card k-' . $kind ) ) {
		return $html;
	}
	$a = attrs( $post, $kind );
	if ( null === $a ) {
		return $html;
	}
	$html   = rerender_with_meta( $html, $post, $kind, $a );
	$is_eat = 'eat' === $kind;
	$name   = trim( (string) ( $a['name'] ?? '' ) );
	if ( '' === $name ) {
		$name = get_the_title( $post );
	}
	$s = seed( (string) $post->ID, $name );

	// 1. The kind label is the order-box header.
	$label = '<p class="pk-kindlabel">';
	$lpos  = strpos( $html, $label );
	$lend  = false !== $lpos ? strpos( $html, '</p>', $lpos ) : false;
	if ( false !== $lend ) {
		$html = substr( $html, 0, $lpos ) . $label . esc_html( $is_eat ? __( 'What I ate', 'courtneyr-child' ) : __( 'What I drank', 'courtneyr-child' ) ) . substr( $html, $lend );
	}

	// 2. The rating as a number beside the plugin's stars.
	if ( preg_match( '/<div class="pk-stars[^"]*" aria-label="[^"]*?(\d)[^"]*"/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$s_end = strpos( $html, '</div>', (int) $m[0][1] );
		if ( false !== $s_end ) {
			$html = substr( $html, 0, $s_end ) . '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', (int) $m[1][0] ) ) . '</span>' . substr( $html, $s_end );
		}
	}

	// 3. The plugin's location block (venue, address, hidden geo) leaves the
	//    caption for the WHERE column; it holds no nested paragraph.
	$where_html = '';
	$w_open     = '<p class="pk-sub p-location h-card">';
	$w_start    = strpos( $html, $w_open );
	$w_end      = false !== $w_start ? strpos( $html, '</p>', $w_start ) : false;
	if ( false !== $w_end ) {
		$w_end     += 4;
		$where_html = substr( $html, $w_start, $w_end - $w_start );
		$html       = substr( $html, 0, $w_start ) . substr( $html, $w_end );
	}

	// 4. The map, from the stored coordinates.
	$lat = (float) ( $a['geoLatitude'] ?? 0 );
	$lon = (float) ( $a['geoLongitude'] ?? 0 );
	$map = ( 0.0 !== $lat || 0.0 !== $lon ) ? map_html( $lat, $lon, trim( (string) ( $a['locationName'] ?? '' ) ) ) : '';

	// 5. WHERE, between the note and the receipt, only when it has content.
	if ( '' !== $where_html || '' !== $map ) {
		$html = before( $html, '<div class="pk-meta">', '<div class="cr-placemat__where"><p class="cr-placemat__label">' . esc_html__( 'Where', 'courtneyr-child' ) . '</p>' . $where_html . $map . '</div>' );
	}

	// 6. The photo's handwritten caption: the dish or drink itself.
	$p_open  = '<div class="pk-embed pk-embed--photo">';
	$p_start = strpos( $html, $p_open );
	$p_end   = false !== $p_start ? strpos( $html, '</div>', $p_start ) : false;
	if ( false !== $p_end ) {
		$html = substr( $html, 0, $p_end ) . '<span class="cr-placemat__caption cr-hand" aria-hidden="true">' . esc_html( $name ) . '</span>' . substr( $html, $p_end );
	}

	// 7. A long note gets its own labelled area (the label is CSS-drawn from
	//    the attribute so the p-content text stays clean).
	$note = trim( (string) ( $a['notes'] ?? '' ) );
	$long = '' !== $note && mb_strlen( wp_strip_all_tags( $note ) ) > NOTE_INLINE_LIMIT;
	if ( $long ) {
		$html = str_replace( '<p class="pk-note p-content">', '<p class="pk-note p-content" data-label="' . esc_attr__( 'Notes from the table', 'courtneyr-child' ) . '">', $html );
	}

	// 8. The receipt line: the kind word before the plugin's timestamp.
	$html = before( $html, '<time class="dt-published"', '<span class="cr-placemat__receipt-label">' . esc_html( $is_eat ? __( 'Ate', 'courtneyr-child' ) : __( 'Drank', 'courtneyr-child' ) ) . '</span>' );

	// 9. A dated seal, then the doodles, both inside the article.
	$when = (string) ( $a[ $is_eat ? 'ateAt' : 'drankAt' ] ?? '' );
	$ts   = '' !== $when ? (int) strtotime( $when ) : 0;
	$ts   = $ts > 0 ? $ts : (int) get_post_time( 'U', true, $post );
	$seal = render(
		array(
			'shape'  => 'seal',
			'big'    => $is_eat ? __( 'Ate', 'courtneyr-child' ) : __( 'Drank', 'courtneyr-child' ),
			'mid'    => (string) wp_date( 'd M Y', $ts ),
			'ring'   => __( 'Table record · Table record', 'courtneyr-child' ),
			'ink'    => INKS[ pick( $s, 3, count( INKS ) ) ],
			'tilt'   => pick( $s, 6, TILTS ),
			'uid'    => 'placemat-' . $post->ID,
			'family' => 'placemat',
		)
	);
	$html = before( $html, '</article>', '<div class="cr-placemat__stamp">' . $seal . '</div>' );
	$open = strpos( $html, '<article' );
	$gt   = false !== $open ? strpos( $html, '>', $open ) : false;
	if ( false !== $gt ) {
		$html = substr( $html, 0, $gt + 1 ) . doodles( $kind, (string) ( $a['drinkType'] ?? 'other' ) ) . substr( $html, $gt + 1 );
	}

	// 10. Margin notes in the journal grid.
	$lines = margin_lines( $s, $is_eat ? 'food' : 'drink' );
	$html  = wrap( $html, aside( $lines[0], 1 ) . aside( $lines[1], 2, 'cr-hand--orange' ) . aside( $lines[2], 3 ) );

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'k-' . $kind ) ) ) {
		$tags->add_class( 'cr-placemat' );
		$tags->add_class( 'cr-placemat--' . $kind );
		if ( ! $is_eat ) {
			$tags->add_class( 'cr-placemat--' . sanitize_html_class( (string) ( $a['drinkType'] ?? 'other' ), 'other' ) );
		}
		if ( false !== $p_end ) {
			$tags->add_class( 'cr-placemat--has-photo' );
		}
		if ( '' !== $where_html || '' !== $map ) {
			$tags->add_class( 'cr-placemat--has-where' );
		}
		if ( '' !== $map ) {
			$tags->add_class( 'cr-placemat--has-map' );
		}
		if ( $long ) {
			$tags->add_class( 'cr-placemat--long-note' );
		}
		$html = $tags->get_updated_html();
	}
	return skip_lazy_iframes( $html );
}
add_filter( 'render_block', __NAMESPACE__ . '\\journal_page', 20, 2 );
