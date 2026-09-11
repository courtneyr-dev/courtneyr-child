<?php
/**
 * /stream eat and drink cards: compact diner placemats.
 *
 * A tighter cousin of the single placemat (inc/single-eat-drink.php).
 * The plugin's eat-card / drink-card is the card (a post whose body holds
 * more than the card gets the plugin's generic stream card; the theme
 * renders the kind card itself then, with the plugin's own title link and
 * date helpers, as the read cards do). On top of it: the rating as a
 * number, a compact WHERE line (venue and town; the plugin's street and
 * country stay in the DOM for the h-card but do not print), a "View on
 * map" link to OpenStreetMap when coordinates exist instead of an
 * embedded map, the timestamp trimmed to its date, one doodle, one small
 * generic stamp and one short handwritten line in an art corner. The
 * full interactive map stays on the single. Nothing is invented and
 * every module renders only when its data exists.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamPlacemat;

use function Courtneyr\Child\Journal\card_attrs;
use function Courtneyr\Child\SinglePlacemat\doodles;
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
 * Short generic handwriting for the art corner: nothing about the dish.
 *
 * @param string $kind 'eat' or 'drink'.
 * @return string[]
 */
function hand_lines( string $kind ): array {
	return 'eat' === $kind
		? array( __( 'Good food, brighter days.', 'courtneyr-child' ), __( 'Worth the trip.', 'courtneyr-child' ), __( 'Eat well, wander often.', 'courtneyr-child' ) )
		: array( __( 'Small sips, big stories.', 'courtneyr-child' ), __( 'Sip slowly.', 'courtneyr-child' ), __( 'Raise a glass.', 'courtneyr-child' ) );
}

/**
 * The stamp's big line: a generic diner phrase by kind and drink type.
 *
 * @param string $kind 'eat' or 'drink'.
 * @param string $type The drink card's drinkType.
 * @return string
 */
function stamp_line( string $kind, string $type ): string {
	if ( 'eat' === $kind ) {
		return __( 'Good food', 'courtneyr-child' );
	}
	$lines = array(
		'coffee'   => __( 'Good coffee', 'courtneyr-child' ),
		'tea'      => __( 'Good tea', 'courtneyr-child' ),
		'beer'     => __( 'Local brews', 'courtneyr-child' ),
		'wine'     => __( 'Good wine', 'courtneyr-child' ),
		'cocktail' => __( 'Cheers', 'courtneyr-child' ),
	);
	return $lines[ $type ] ?? __( 'Good drinks', 'courtneyr-child' );
}

/**
 * Dress an eat or drink stream card as a compact placemat.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function placemat_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) ) {
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
	if ( '' === $kind ) {
		return $html;
	}
	$card = find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' );
	if ( null === $card ) {
		return $html;
	}

	// The generic stream card (a post with more than the card in its
	// body): render the kind card itself, with the plugin's title link and date.
	if ( false === strpos( $html, 'pk-card k-' . $kind ) ) {
		$rendered = render_block( $card );
		if ( '' === $rendered || false === strpos( $rendered, 'pk-card k-' . $kind ) ) {
			return $html;
		}
		if ( function_exists( '\\PKIW\\link_title_to_post' ) ) {
			$rendered = \PKIW\link_title_to_post( $rendered, $post );
		}
		if ( false === strpos( $rendered, 'dt-published' ) && function_exists( '\\PKIW\\inject_post_date_into_card' ) ) {
			$rendered = \PKIW\inject_post_date_into_card( $rendered, $post );
		}
		$html = $rendered;
	}

	$a = card_attrs( $post, 'post-kinds-indieweb/' . $kind . '-card', (array) ( $card['attrs'] ?? array() ) );
	// Meta the block lacked: the plugin renders the card again from the full set.
	$html   = \Courtneyr\Child\SinglePlacemat\rerender_with_meta( $html, $post, $kind, $a );
	if ( function_exists( '\\PKIW\\link_title_to_post' ) && false === strpos( $html, 'class="pk-title p-name"><a' ) ) {
		$html = \PKIW\link_title_to_post( $html, $post );
	}
	if ( false === strpos( $html, 'dt-published' ) && function_exists( '\\PKIW\\inject_post_date_into_card' ) ) {
		$html = \PKIW\inject_post_date_into_card( $html, $post );
	}
	$is_eat = 'eat' === $kind;
	$name   = trim( (string) ( $a['name'] ?? '' ) );
	$type   = (string) ( $a['drinkType'] ?? 'other' );
	$s      = seed( (string) $post->ID, '' !== $name ? $name : $post->post_title );

	// 1. The rating as a number.
	if ( preg_match( '/<div class="pk-stars[^"]*" aria-label="[^"]*?(\d)[^"]*"/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$s_end = strpos( $html, '</div>', (int) $m[0][1] );
		if ( false !== $s_end ) {
			$html = substr( $html, 0, $s_end ) . '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', (int) $m[1][0] ) ) . '</span>' . substr( $html, $s_end );
		}
	}

	// 2. WHERE: the plugin's location block stays where it is (venue, town);
	//    a restaurant that only repeats the venue leaves the sub line.
	$has_where  = false !== strpos( $html, 'pk-sub p-location h-card' );
	$restaurant = trim( (string) ( $a['restaurant'] ?? '' ) );
	if ( $has_where && '' !== $restaurant && $restaurant === trim( (string) ( $a['locationName'] ?? '' ) ) ) {
		$html = (string) preg_replace( '/<span class="p-location h-card"><span class="p-name">' . preg_quote( esc_html( $restaurant ), '/' ) . '<\/span><\/span>\s*(?:&mdash;\s*)?/', '', $html, 1 );
	}

	// 3. A map link instead of a map, when coordinates exist. A card with no
	//    location borrows Simple Location's public place: its address as the
	//    WHERE line (after the sub line) and its point for the link.
	$lat = (float) ( $a['geoLatitude'] ?? 0 );
	$lon = (float) ( $a['geoLongitude'] ?? 0 );
	if ( ! $has_where ) {
		$sl = \Courtneyr\Child\SinglePlacemat\sloc_place( $post );
		$c_open = '<div class="pk-caption">';
		$c_pos  = strpos( $html, $c_open );
		$c_end  = false !== $c_pos ? strpos( $html, '</div>', $c_pos ) : false;
		if ( '' !== $sl['address'] && false !== $c_end ) {
			$html      = substr( $html, 0, $c_end ) . '<p class="pk-sub p-location cr-mat__place">' . esc_html( $sl['address'] ) . '</p>' . substr( $html, $c_end );
			$has_where = true;
		}
		if ( 0.0 === $lat && 0.0 === $lon ) {
			$lat = $sl['lat'];
			$lon = $sl['lon'];
		}
	}
	$map = '';
	if ( 0.0 !== $lat || 0.0 !== $lon ) {
		$map = '<a class="cr-mat__map" href="' . esc_url( sprintf( 'https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=16/%F/%F', $lat, $lon, $lat, $lon ) ) . '" target="_blank" rel="noopener noreferrer">'
			. '<svg class="cr-mat__pin" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.5a4.5 4.5 0 0 0-4.5 4.5c0 3.4 4.5 8.5 4.5 8.5s4.5-5.1 4.5-8.5A4.5 4.5 0 0 0 8 1.5zm0 6.3a1.8 1.8 0 1 1 0-3.6 1.8 1.8 0 0 1 0 3.6z" fill="currentColor"/></svg>'
			. esc_html__( 'View on map', 'courtneyr-child' ) . ' <span aria-hidden="true">↗</span></a>';
	}

	// 4. The art corner: one doodle, one small generic stamp, one short line.
	$art  = '<div class="cr-mat__art" aria-hidden="true">';
	$art .= doodles( $kind, $type );
	$art .= '<div class="cr-mat__stamp">' . render(
		array(
			'shape'  => 'rect',
			'big'    => stamp_line( $kind, $type ),
			'mid'    => $is_eat ? __( 'Eaten well', 'courtneyr-child' ) : __( 'Sipped slowly', 'courtneyr-child' ),
			'ink'    => INKS[ pick( $s, 3, count( INKS ) ) ],
			'tilt'   => pick( $s, 6, TILTS ),
			'family' => 'placemat-mini',
		)
	) . '</div>';
	$lines = hand_lines( $kind );
	$art  .= '<p class="cr-hand cr-mat__hand">' . esc_html( $lines[ pick( $s, 4, count( $lines ) ) ] ) . '</p>';
	$art  .= '</div>';

	// 5. One date, top right: the card's own ateAt / drankAt when it has one,
	//    else the plugin's stream date (the post date), trimmed to the date;
	//    the time of day stays on the single. The datetime attribute is kept.
	$stream_date = '';
	if ( preg_match( '/<p class="pk-sub pk-stream-date">(.*?)<\/p>/s', $html, $sd, PREG_OFFSET_CAPTURE ) ) {
		$stream_date = $sd[1][0];
		$html        = substr( $html, 0, (int) $sd[0][1] ) . substr( $html, (int) $sd[0][1] + strlen( $sd[0][0] ) );
	}
	$m_open = '<div class="pk-meta">';
	$m_pos  = strpos( $html, $m_open );
	$m_end  = false !== $m_pos ? strpos( $html, '</div>', $m_pos ) : false;
	if ( false !== $m_end ) {
		$inner = substr( $html, $m_pos + strlen( $m_open ), $m_end - $m_pos - strlen( $m_open ) );
		if ( false === strpos( $inner, '<time' ) && '' !== $stream_date ) {
			$inner = $stream_date;
		}
		$inner = (string) preg_replace_callback(
			'/(<time class="dt-published" datetime="([^"]*)">)([^<]*)(<\/time>)/',
			static function ( array $mm ): string {
				$ts = (int) strtotime( $mm[2] );
				return $mm[1] . ( $ts > 0 ? esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) : $mm[3] ) . $mm[4];
			},
			$inner,
			1
		);
		$html = substr( $html, 0, $m_pos + strlen( $m_open ) ) . $inner . substr( $html, $m_end );
	}

	$meta = strpos( $html, '<div class="pk-meta">' );
	if ( false !== $meta ) {
		$html = substr( $html, 0, $meta ) . $map . $art . substr( $html, $meta );
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'cr-mat' );
		$tags->add_class( 'cr-mat--' . $kind );
		if ( ! $is_eat ) {
			$tags->add_class( 'cr-mat--' . sanitize_html_class( $type, 'other' ) );
		}
		if ( false !== strpos( $html, 'pk-embed--photo' ) ) {
			$tags->add_class( 'cr-mat--has-photo' );
		}
		if ( $has_where ) {
			$tags->add_class( 'cr-mat--has-where' );
		}
		if ( '' !== $map ) {
			$tags->add_class( 'cr-mat--has-map' );
		}
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\placemat_card', 10, 3 );
