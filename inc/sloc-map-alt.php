<?php
/**
 * Alt text for Simple Location maps.
 *
 * The Simple Location plugin renders its static map as
 *   <img class="sloc-map" src="…" loading="lazy" />
 * with no alt attribute (see class-map-provider.php). An <img> without alt
 * fails WCAG 1.1.1, and when the map sits inside a link it leaves that link
 * with no accessible name — which is what the Accessibility Checker flags on
 * every location-tagged post.
 *
 * Simple Location appends the map to the_content at priority 11. This runs at
 * 12 and injects a descriptive alt into any sloc-map image that doesn't
 * already have one. Runs before Perfmatters' lazy pass, so the alt survives
 * onto the final markup.
 *
 * Simple Location's own `geo_public` gates whether it renders the map image
 * at all, but not Post Kinds for IndieWeb's separate, potentially stricter
 * `_pkiw_geo_privacy`. This file only ever decides how much of the address
 * to name in the alt text, via `pkiw_get_visible_location_fields()`; it never
 * decides whether the map image itself shows — that stays Simple Location's
 * call. Post Kinds absent means a fully generic alt with no location text.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SlocMapAlt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', __NAMESPACE__ . '\\add_map_alt', 12 );

/**
 * Inject alt text into Simple Location map images that lack it.
 *
 * @param string $content Post content (map already appended by Simple Location).
 * @return string
 */
function add_map_alt( string $content ): string {
	if ( false === strpos( $content, 'sloc-map' ) ) {
		return $content;
	}

	$alt = esc_attr( map_alt_label() );

	return (string) preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( array $m ) use ( $alt ): string {
			$tag = $m[0];
			if ( false === strpos( $tag, 'sloc-map' ) || preg_match( '/\balt\s*=/i', $tag ) ) {
				return $tag; // not a map, or already has alt.
			}
			return preg_replace( '/<img\b/i', '<img alt="' . $alt . '"', $tag, 1 );
		},
		$content
	);
}

/**
 * The alt text's label, gated by `pkiw_get_visible_location_fields()`: the
 * full street address when `street` is visible, else the coarsest place
 * text its locality/region/country tiers allow, else a generic label that
 * names no location. Post Kinds absent (or the post carries no ID) also
 * gets the generic label — "nothing location-related" per its safe fallback.
 *
 * @return string
 */
function map_alt_label(): string {
	$post_id = (int) get_the_ID();
	if ( $post_id <= 0 || ! function_exists( 'pkiw_get_visible_location_fields' ) ) {
		return 'Map';
	}

	$visible = pkiw_get_visible_location_fields( $post_id );

	$place = '';
	if ( ! empty( $visible['street'] ) ) {
		$place = trim( (string) get_post_meta( $post_id, 'geo_address', true ) );
	}
	if ( '' === $place ) {
		$place = implode(
			', ',
			array_filter(
				array(
					! empty( $visible['locality'] ) ? trim( (string) get_post_meta( $post_id, 'geo_locality', true ) ) : '',
					! empty( $visible['region'] ) ? trim( (string) get_post_meta( $post_id, 'geo_region', true ) ) : '',
					! empty( $visible['country'] ) ? trim( (string) get_post_meta( $post_id, 'geo_country_name', true ) ) : '',
				)
			)
		);
	}

	return '' !== $place
		? sprintf( 'Map showing %s', wp_strip_all_tags( $place ) )
		: "Map showing this post's location";
}
