<?php
/**
 * Stream: check-ins as passport pages.
 *
 * A check-in posted through Outpost carries a `p-trip` paragraph ahead of
 * its checkin-card block, so the Post Kinds stream card no longer sees a
 * card-only body and falls back to its generic card: featured photo, a
 * flattened excerpt, no map, no h-card. On /stream/ this file finds the
 * checkin-card block in the body, renders it through the plugin's own
 * render callback (map, h-card, h-adr, h-geo, privacy gates and all), then
 * adds the post title, the post date, and a footer of passport stamps drawn
 * as inline SVG from the same block attributes. cr-post-kinds.css paints
 * the result as a travel-document page.
 *
 * The stamps are decoration. Every fact they show — place, date, entry
 * number — is also visible as text in the card, they are aria-hidden, and
 * they print no place the plugin hides: place_facts() asks the plugin's
 * visibility result first, then the block's locationPrivacy. A private
 * check-in stamps only the date and entry number. Nothing here runs outside
 * the stream page or touches a non-check-in card.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamCheckin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\render;
use function Courtneyr\Child\Stamps\seed;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

/**
 * The checkin-card block from a post body, or null when there is none.
 *
 * Walks the parsed block tree so a card wrapped in a group is still found.
 *
 * @param \WP_Post $post Post being rendered.
 * @return array<string, mixed>|null Parsed block.
 */
function find_checkin_block( \WP_Post $post ): ?array {
	$found = null;

	$walk = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( 'post-kinds-indieweb/checkin-card' === ( $block['blockName'] ?? '' ) ) {
				$found = $block;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );

	return $found;
}

/**
 * The place text this theme may print for a check-in.
 *
 * The plugin decides what a visitor may see from the post's
 * `_pkiw_geo_privacy` meta. The block's saved locationPrivacy attribute can
 * lag behind that meta after a privacy change made outside the block, so the
 * stricter of the two wins: a field the plugin hides is never printed, and
 * the attribute's own tier still applies on top. Public and approximate
 * check-ins may show locality, region and country; the postal code is
 * public-only; a private check-in shows no place at all.
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @param \WP_Post             $post  Post being rendered.
 * @return array{locality: string, region: string, country: string, postal: string}
 */
function place_facts( array $attrs, \WP_Post $post ): array {
	$visible = function_exists( 'pkiw_get_visible_location_fields' ) ? (array) pkiw_get_visible_location_fields( $post->ID ) : array();
	$privacy = (string) ( $attrs['locationPrivacy'] ?? 'approximate' );
	$private = 'private' === $privacy;

	$fact = static function ( string $attr, string $field ) use ( $attrs, $visible, $private ): string {
		return ( $private || empty( $visible[ $field ] ) ) ? '' : trim( (string) ( $attrs[ $attr ] ?? '' ) );
	};

	return array(
		'locality' => $fact( 'locality', 'locality' ),
		'region'   => $fact( 'region', 'region' ),
		'country'  => $fact( 'country', 'country' ),
		'postal'   => 'public' === $privacy ? $fact( 'postalCode', 'postal_code' ) : '',
	);
}

/**
 * The facts a stamp is allowed to print for this check-in.
 *
 * The place comes from place_facts(). Coordinates, street address and venue
 * name never reach a stamp.
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @param \WP_Post             $post  Post being rendered.
 * @return array{place: string, country: string, postal: string, date: string, iso: string, entry: string, seed: int}
 */
function stamp_facts( array $attrs, \WP_Post $post ): array {
	$facts   = place_facts( $attrs, $post );
	$country = $facts['country'];
	$postal  = $facts['postal'];

	$place = implode( ', ', array_filter( array( $facts['locality'], $facts['region'] ) ) );

	// A check-in whose place is hidden draws the stamp a check-in with no
	// location draws: the venue and its ids stay out of the seed, so the
	// stamp's shape, ink and tilt say nothing about a stored place.
	$has_place = '' !== $place || '' !== $country;

	$ts = 0;
	if ( ! empty( $attrs['checkinAt'] ) ) {
		$ts = (int) strtotime( (string) $attrs['checkinAt'] );
	}
	if ( $ts <= 0 ) {
		$ts = (int) get_post_time( 'U', true, $post );
	}

	return array(
		'place'   => $place,
		'country' => $country,
		'postal'  => $postal,
		'date'    => strtoupper( (string) wp_date( 'd M Y', $ts ) ),
		'iso'     => (string) wp_date( 'c', $ts ),
		'entry'   => sprintf( '№ %d', $post->ID ),
		'seed'    => $has_place ? seed( (string) $post->ID, (string) ( $attrs['osmId'] ?? '' ), (string) ( $attrs['foursquareId'] ?? '' ), (string) ( $attrs['venueName'] ?? '' ) ) : seed( (string) $post->ID ),
	);
}

/**
 * The passport vocabulary: four stamp families built on the shared
 * primitive. Each family is a spec; the facts fill the text slots.
 *
 * @param string                    $family One of checked-in, arrived, passport-entry, postmark.
 * @param array<string, string|int> $f      Stamp facts from stamp_facts().
 * @param string                    $ink    Ink colour.
 * @param int                       $tilt   Tilt index.
 * @param string                    $uid    Unique id fragment for seals.
 * @return string Stamp markup.
 */
function checkin_stamp( string $family, array $f, string $ink, int $tilt, string $uid ): string {
	$place = '' !== $f['place'] ? (string) $f['place'] : (string) $f['country'];
	$foot  = ( '' !== $f['country'] && '' !== $f['place'] ) ? (string) $f['country'] : (string) $f['entry'];
	$date  = (string) $f['date'];

	switch ( $family ) {
		case 'arrived':
			$spec = array(
				'shape' => 'seal',
				'ring'  => '' !== $place ? $place : 'CHECK-IN',
				'glyph' => 'pin',
				'big'   => 'ARRIVED',
				'small' => $date,
				'tiny'  => $foot,
			);
			break;
		case 'postmark':
			$spec = array(
				'shape'    => 'seal',
				'postmark' => true,
				'ring'     => '' !== $place ? $place : 'CHECK-IN',
				'glyph'    => 'pin',
				'big'      => 'ARRIVED',
				'small'    => $date,
				'tiny'     => $foot,
			);
			break;
		case 'passport-entry':
			$spec = array(
				'shape' => 'octagon',
				'glyph' => 'pin',
				'big'   => 'ARRIVED',
				'mid'   => '' !== $place ? $place : (string) $f['entry'],
				'small' => $date,
			);
			break;
		default:
			$spec = array(
				'shape' => 'rect',
				'big'   => 'CHECKED IN',
				'mid'   => '' !== $place ? $place : (string) $f['entry'],
				'small' => $date . ( '' !== $f['postal'] ? ' · ' . $f['postal'] : '' ),
			);
	}

	$spec['family'] = 'checkin-' . $family;
	$spec['ink']    = $ink;
	$spec['tilt']   = $tilt;
	$spec['uid']    = $uid;

	return render( $spec );
}

/**
 * The stamp footer: one lead stamp, an optional second one, and the entry
 * line as text so the entry number exists outside the drawing.
 *
 * Everything varies from the seed and nothing else: which family leads,
 * whether a second stamp appears, each stamp's ink and tilt.
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @param \WP_Post             $post  Post being rendered.
 * @param int                  $max   Most stamps to draw: 2 on the single, 1 on a Stream card.
 * @return string Footer markup.
 */
function render_stamps( array $attrs, \WP_Post $post, int $max = 2 ): string {
	$f    = stamp_facts( $attrs, $post );
	$seed = (int) $f['seed'];

	$families  = array( 'checked-in', 'arrived', 'postmark', 'passport-entry' );
	$lead      = $families[ pick( $seed, 0, 4 ) ];
	$ink_a     = INKS[ pick( $seed, 3, 3 ) ];
	$ink_b     = INKS[ ( pick( $seed, 3, 3 ) + 1 ) % 3 ];
	$has_place = '' !== $f['place'] || '' !== $f['country'];
	$second    = $max > 1 && $has_place && 0 !== pick( $seed, 12, 3 );
	$uid       = 'cr-seal-' . $post->ID;

	// A second stamp always changes shape: a rectangle beside a round or
	// octagonal seal, never two of the same silhouette.
	$out  = '<footer class="cr-passport__stamps cr-stamps">';
	$out .= checkin_stamp( $lead, $f, $ink_a, pick( $seed, 6, TILTS ), $uid );
	if ( $second ) {
		$out .= checkin_stamp( 'checked-in' === $lead ? 'arrived' : 'checked-in', $f, $ink_b, pick( $seed, 9, TILTS ), $uid . '-b' );
	}
	$out .= '<p class="cr-passport__entry cr-stamps__line">'
		. '<span class="cr-passport__entry-label">' . esc_html__( 'Entry', 'courtneyr-child' ) . '</span> '
		. esc_html( (string) $f['entry'] )
		. '</p>';
	$out .= '</footer>';

	return $out;
}

/**
 * A still map of one point: four map tiles behind a dot, with the tile
 * provider's attribution. No script and no iframe.
 *
 * Call it only for coordinates the plugin already printed for this visitor.
 *
 * @param array<string, mixed> $attrs Check-in card attributes.
 * @return string Empty without coordinates.
 */
function map_thumbnail( array $attrs ): string {
	if ( ! isset( $attrs['latitude'], $attrs['longitude'] ) || ! is_numeric( $attrs['latitude'] ) || ! is_numeric( $attrs['longitude'] ) ) {
		return '';
	}

	$lat  = max( -85.0, min( 85.0, (float) $attrs['latitude'] ) );
	$lon  = (float) $attrs['longitude'];
	$zoom = 15;
	$n    = 2 ** $zoom;

	// Slippy-map tile coordinates of the point, as fractions of a tile.
	$x = ( $lon + 180 ) / 360 * $n;
	$y = ( 1 - asinh( tan( deg2rad( $lat ) ) ) / M_PI ) / 2 * $n;

	// The 2 by 2 block of tiles whose middle is nearest the point.
	$x0 = (int) floor( $x - 0.5 );
	$y0 = (int) floor( $y - 0.5 );

	$tiles = class_exists( '\\PKIW\\Checkin_Map' )
		? \PKIW\Checkin_Map::tile_layer()
		: array(
			'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
			'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		);

	$venue = trim( (string) ( $attrs['venueName'] ?? '' ) );
	$label = '' !== $venue
		/* translators: %s: venue name */
		? sprintf( __( 'Map showing %s', 'courtneyr-child' ), $venue )
		: __( 'Map showing this check-in', 'courtneyr-child' );

	$out  = '<div class="cr-passport__thumb">';
	$out .= '<div class="cr-passport__thumb-map" role="img" aria-label="' . esc_attr( $label ) . '">';
	$out .= sprintf(
		'<div class="cr-passport__thumb-tiles" style="--cr-thumb-x:%dpx;--cr-thumb-y:%dpx">',
		(int) round( ( $x - $x0 ) * 256 ),
		(int) round( ( $y - $y0 ) * 256 )
	);
	foreach ( array( 0, 1 ) as $dy ) {
		foreach ( array( 0, 1 ) as $dx ) {
			$src  = str_replace( array( '{z}', '{x}', '{y}', '{s}' ), array( (string) $zoom, (string) ( ( $x0 + $dx + $n ) % $n ), (string) ( $y0 + $dy ), 'a' ), (string) $tiles['url'] );
			$out .= '<img src="' . esc_url( $src ) . '" alt="" width="256" height="256" loading="lazy" decoding="async">';
		}
	}
	$out .= '</div><span class="cr-passport__thumb-dot" aria-hidden="true"></span></div>';
	$out .= '<p class="cr-passport__thumb-credit">' . wp_kses(
		(string) $tiles['attribution'],
		array(
			'a' => array(
				'href' => true,
				'rel'  => true,
			),
		)
	) . '</p>';
	$out .= '</div>';

	return $out;
}

/**
 * Render a check-in on /stream/ through the plugin's checkin-card block,
 * then add the post title, the date, and the stamp footer.
 *
 * @param string    $html     Rendered stream card HTML.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function passport_card( string $html, array $block, $instance ): string {
	if ( ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post instanceof \WP_Post || ! has_term( 'checkin', 'kind', $post ) ) {
		return $html;
	}

	$checkin = find_checkin_block( $post );
	if ( null === $checkin ) {
		return $html;
	}

	$card = render_block( $checkin );
	if ( '' === $card || false === strpos( $card, 'k-checkin' ) ) {
		return $html;
	}

	// The plugin's own stream helpers: title anchor → permalink, and the
	// post date under the title when the card carries no date of its own.
	if ( function_exists( '\\PKIW\\link_title_to_post' ) ) {
		$card = \PKIW\link_title_to_post( $card, $post );
	}
	if ( false === strpos( $card, 'dt-published' ) && function_exists( '\\PKIW\\inject_post_date_into_card' ) ) {
		$card = \PKIW\inject_post_date_into_card( $card, $post );
	}

	// The post title is the entry's headline; the h2 stays the venue so the
	// plugin's p-name / h-card structure is untouched.
	$title = trim( get_the_title( $post ) );
	$venue = trim( (string) ( $checkin['attrs']['venueName'] ?? '' ) );
	if ( '' !== $title && 0 !== strcasecmp( $title, $venue ) ) {
		$label_end = strpos( $card, '</p>', (int) strpos( $card, 'pk-kindlabel' ) );
		if ( false !== $label_end ) {
			$label_end += 4;
			$headline   = '<div class="cr-passport__title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></div>';
			$card       = substr( $card, 0, $label_end ) . $headline . substr( $card, $label_end );
		}
	}

	// A private check-in has no title for the plugin helper to hang a date
	// on, so the date goes under the eyebrow instead.
	if ( false === strpos( $card, 'dt-published' ) ) {
		$label_end = strpos( $card, '</p>', (int) strpos( $card, 'pk-kindlabel' ) );
		if ( false !== $label_end ) {
			$label_end += 4;
			$date_html  = '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>';
			$card       = substr( $card, 0, $label_end ) . $date_html . substr( $card, $label_end );
		}
	}

	$close = strrpos( $card, '</article>' );
	if ( false === $close ) {
		return $html;
	}
	$card = substr( $card, 0, $close ) . render_stamps( (array) ( $checkin['attrs'] ?? array() ), $post, 1 ) . substr( $card, $close );

	// 0.7.46 (R-43): the homepage preview and the editor's card preview keep the
	// venue's map *link* but drop the map embed, so no map provider is contacted
	// before the reader acts. /stream/ keeps its embed (that decision is separate).
	$no_embed = is_front_page() || \Courtneyr\Child\HomeSections\is_stream_card_preview_request();
	$has_map  = false !== strpos( $card, '<iframe' );
	if ( $no_embed && $has_map ) {
		$card = (string) preg_replace( '#<iframe\b[^>]*>.*?</iframe>#is', '', $card );
	}

	// 0.7.97 (PKIW issue 224): on /stream/ the embed becomes a map thumbnail
	// above the entry, so a public card stands about 1.5 times an approximate
	// one, not twice. The plugin printed the embed, which is how this code
	// knows the visitor may see the coordinates.
	if ( ! $no_embed && $has_map ) {
		$thumb = map_thumbnail( (array) ( $checkin['attrs'] ?? array() ) );
		if ( '' !== $thumb ) {
			$card      = (string) preg_replace( '#<div class="pk-embed pk-embed--map">.*?</div>#is', '', $card, 1 );
			$label_end = strpos( $card, '</span>', (int) strpos( $card, 'pk-kindlabel' ) );
			if ( false !== $label_end ) {
				$label_end += 7;
				$card       = substr( $card, 0, $label_end ) . $thumb . substr( $card, $label_end );
			}
		}
	}

	$tags = new \WP_HTML_Tag_Processor( $card );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'cr-passport' );
		if ( $no_embed ) {
			$tags->add_class( 'cr-passport--no-embed' );
		}
		$card = $tags->get_updated_html();
	}

	return \Courtneyr\Child\Journal\skip_lazy_iframes( $card );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\passport_card', 10, 3 );
