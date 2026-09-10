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
 * they respect the block's locationPrivacy the same way the plugin does:
 * a private check-in stamps only the date and entry number. Nothing here
 * runs outside the stream page or touches a non-check-in card.
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
 * The facts a stamp is allowed to print for this check-in.
 *
 * Mirrors the plugin's privacy gates: public and approximate check-ins show
 * locality, region and country as text, so a stamp may repeat them; the
 * postal code is public-only; a private check-in shows no place at all.
 * Coordinates, street address and venue name never reach a stamp.
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @param \WP_Post             $post  Post being rendered.
 * @return array{place: string, country: string, postal: string, date: string, iso: string, entry: string, seed: int}
 */
function stamp_facts( array $attrs, \WP_Post $post ): array {
	$privacy = (string) ( $attrs['locationPrivacy'] ?? 'approximate' );
	$private = 'private' === $privacy;

	$locality = $private ? '' : trim( (string) ( $attrs['locality'] ?? '' ) );
	$region   = $private ? '' : trim( (string) ( $attrs['region'] ?? '' ) );
	$country  = $private ? '' : trim( (string) ( $attrs['country'] ?? '' ) );
	$postal   = 'public' === $privacy ? trim( (string) ( $attrs['postalCode'] ?? '' ) ) : '';

	$place = implode( ', ', array_filter( array( $locality, $region ) ) );

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
		'seed'    => seed( (string) $post->ID, (string) ( $attrs['osmId'] ?? '' ), (string) ( $attrs['foursquareId'] ?? '' ), (string) ( $attrs['venueName'] ?? '' ) ),
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
 * @return string Footer markup.
 */
function render_stamps( array $attrs, \WP_Post $post ): string {
	$f    = stamp_facts( $attrs, $post );
	$seed = (int) $f['seed'];

	$families  = array( 'checked-in', 'arrived', 'postmark', 'passport-entry' );
	$lead      = $families[ pick( $seed, 0, 4 ) ];
	$ink_a     = INKS[ pick( $seed, 3, 3 ) ];
	$ink_b     = INKS[ ( pick( $seed, 3, 3 ) + 1 ) % 3 ];
	$has_place = '' !== $f['place'] || '' !== $f['country'];
	$second    = $has_place && 0 !== pick( $seed, 12, 3 );
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
 * Render a check-in on /stream/ through the plugin's checkin-card block,
 * then add the post title, the date, and the stamp footer.
 *
 * @param string    $html     Rendered stream card HTML.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function passport_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) ) {
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
			$headline   = '<p class="cr-passport__title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></p>';
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
	$card = substr( $card, 0, $close ) . render_stamps( (array) ( $checkin['attrs'] ?? array() ), $post ) . substr( $card, $close );

	$tags = new \WP_HTML_Tag_Processor( $card );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'cr-passport' );
		$card = $tags->get_updated_html();
	}

	return $card;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\passport_card', 10, 3 );
