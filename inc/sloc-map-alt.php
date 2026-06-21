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
 * 12 and injects a descriptive alt (built from the post's geo_address) into any
 * sloc-map image that doesn't already have one. Runs before Perfmatters' lazy
 * pass, so the alt survives onto the final markup.
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

	$place = get_post_meta( (int) get_the_ID(), 'geo_address', true );
	$label = is_string( $place ) && '' !== trim( $place )
		? sprintf( 'Map showing %s', wp_strip_all_tags( $place ) )
		: "Map showing this post's location";
	$alt = esc_attr( $label );

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
