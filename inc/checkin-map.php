<?php
/**
 * The check-in map slip (PKIW issue 230).
 *
 * One component for the eat and drink singles: a folded slip of paper that
 * sits under the order ticket or the coaster, on the same placemat. It holds
 * an OpenStreetMap embed, the place under a "Where" heading, a sentence
 * saying the post's privacy setting decides what shows, and one link to
 * OpenStreetMap.
 *
 * The slip prints only what the plugin's card printed. The plugin applies
 * the post's location privacy before it renders, so a place the visitor may
 * not see never reaches this file, and neither do its coordinates:
 *
 * - Coordinates printed: the map, the place, the sentence and the link.
 * - A place printed with no coordinates: the text slip, with no frame.
 * - Nothing printed: no slip, no link and no request to OpenStreetMap.
 *
 * The map appears on no other surface. The menus and the Stream cards name
 * a venue in text and nothing more.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\CheckinMap;

use function Courtneyr\Child\Journal\skip_lazy_iframes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The slip for a place the plugin's card printed.
 *
 * @param array{name:string,url:string,street:string,locality:string,region:string,country:string,lat:?float,lon:?float} $place       The printed place; lat and lon are null when no coordinates printed.
 * @param bool                                                                                                             $named_above Whether the card above already shows the place's name as a fact.
 * @return string Empty when the slip would show nothing the card above doesn't.
 */
function slip( array $place, bool $named_above ): string {
	$name    = trim( $place['name'] );
	$has_map = null !== $place['lat'] && null !== $place['lon'];
	$address = array_filter(
		array(
			'p-street-address' => trim( $place['street'] ),
			'p-locality'       => trim( $place['locality'] ),
			'p-region'         => trim( $place['region'] ),
			'p-country-name'   => trim( $place['country'] ),
		)
	);

	// Each fact once: a name the card shows as a fact isn't printed again.
	$show_name = '' !== $name && ! $named_above;
	if ( ! $has_map && ! $show_name && empty( $address ) ) {
		return '';
	}

	// The place: the plugin's h-card, name first, then the address it allowed.
	$where = '';
	if ( $show_name ) {
		$where .= '<span class="cr-map-slip__name p-name">' . esc_html( $name ) . '</span>';
	} elseif ( '' !== $name ) {
		$where .= '<data class="p-name" value="' . esc_attr( $name ) . '" hidden></data>';
	}
	if ( '' !== $place['url'] ) {
		$where .= '<data class="u-url" value="' . esc_url( $place['url'] ) . '" hidden></data>';
	}
	$parts = array();
	foreach ( $address as $class => $text ) {
		$parts[] = '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}
	if ( $parts ) {
		$where .= '<span class="cr-map-slip__address">' . implode( ', ', $parts ) . '</span>';
	}
	if ( $has_map ) {
		$where .= sprintf(
			'<data class="p-geo h-geo" value="%1$s,%2$s" hidden><span class="p-latitude">%1$s</span><span class="p-longitude">%2$s</span></data>',
			esc_attr( (string) $place['lat'] ),
			esc_attr( (string) $place['lon'] )
		);
	}

	$caption  = '<h2 class="cr-map-slip__title">' . esc_html__( 'Where', 'courtneyr-child' ) . '</h2>';
	$caption .= '<p class="cr-map-slip__place p-location h-card">' . $where . '</p>';
	$caption .= '<p class="cr-map-slip__privacy">' . esc_html__( 'Location shown according to this post’s privacy setting.', 'courtneyr-child' ) . '</p>';

	$map = '';
	if ( $has_map ) {
		$lat   = (float) $place['lat'];
		$lon   = (float) $place['lon'];
		$span  = 0.01;
		$src   = sprintf( 'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F', $lon - $span, $lat - $span, $lon + $span, $lat + $span, $lat, $lon );
		$large = sprintf( 'https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=16/%F/%F', $lat, $lon, $lat, $lon );
		$title = '' !== $name
			/* translators: %s: place name. */
			? sprintf( __( 'Map showing %s.', 'courtneyr-child' ), $name )
			: __( 'Map showing this location.', 'courtneyr-child' );
		$link  = '' !== $name
			/* translators: %s: place name. */
			? sprintf( __( 'View %s on OpenStreetMap', 'courtneyr-child' ), $name )
			: __( 'View this location on OpenStreetMap', 'courtneyr-child' );

		$map      = '<div class="cr-map-slip__map"><iframe class="cr-map-slip__frame" title="' . esc_attr( $title ) . '" src="' . esc_url( $src ) . '" width="640" height="360" loading="lazy"></iframe></div>';
		$caption .= '<p class="cr-map-slip__more"><a class="cr-map-slip__link" href="' . esc_url( $large ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span></a></p>';
	}

	return skip_lazy_iframes(
		'<section class="cr-map-slip' . ( $has_map ? ' cr-map-slip--map' : ' cr-map-slip--text' ) . '">'
		. $map
		. '<div class="cr-map-slip__caption">' . $caption . '</div>'
		. '</section>'
	);
}
