<?php
/**
 * Single check-in: an opened travel-journal page.
 *
 * A check-in's single view came from the generic single template: a wide
 * featured image (the same photo the checkin-card block already shows),
 * then the post body with the plugin's card, then Simple Location's raster
 * map and address, then the tag row. This file reorders that into a journal
 * page for posts with the `checkin` kind:
 *
 *   - the featured image is dropped when the block carries the same photo;
 *   - the H1 gets a lede with the date and the privacy-safe place;
 *   - the plugin's card becomes the passport artifact: its photo is lifted
 *     out, a long note moves to a "Notes from this check-in" section, the
 *     shared stamps (inc/stream-checkin.php on inc/stamps.php) close it;
 *   - the photo returns after the notes as a pasted snapshot with a
 *     handwritten caption;
 *   - three short handwritten margin notes are placed in the page grid;
 *   - a quiet time / place row closes the entry.
 *
 * All handwriting is theme copy chosen by the post's seed, so a page never
 * changes between loads and nothing is invented about the place. Everything
 * the card printed still prints; the plugin's markup, microformats and
 * privacy gates are untouched. cr-post-kinds.css paints the page under
 * body.single-post.kind-checkin.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleCheckin;

use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;
use function Courtneyr\Child\StreamCheckin\find_checkin_block;
use function Courtneyr\Child\StreamCheckin\render_stamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A note longer than this leaves the passport card for the notes section.
 */
const NOTE_INLINE_LIMIT = 140;

/**
 * Handwritten margin notes, in sets so a page never repeats a line.
 * Short, place-neutral, time-neutral: nothing here claims a fact.
 *
 * @return array<int, array<int, string>>
 */
function margin_copy(): array {
	return array(
		array(
			__( 'Same places. A more curious life.', 'courtneyr-child' ),
			__( 'Good people. Better ideas.', 'courtneyr-child' ),
			__( 'Collecting moments, not things.', 'courtneyr-child' ),
		),
		array(
			__( 'People. Places. Ideas.', 'courtneyr-child' ),
			__( 'Go, look, listen.', 'courtneyr-child' ),
			__( 'Worth the trip.', 'courtneyr-child' ),
		),
		array(
			__( 'Notes from the road.', 'courtneyr-child' ),
			__( 'Same city, different conversations.', 'courtneyr-child' ),
			__( 'Always something new.', 'courtneyr-child' ),
		),
	);
}

/**
 * Handwritten photo captions.
 *
 * @return string[]
 */
function caption_copy(): array {
	return array(
		__( 'A little photo from a good day.', 'courtneyr-child' ),
		__( 'Proof I was here.', 'courtneyr-child' ),
		__( 'The kind of day I like.', 'courtneyr-child' ),
		__( 'One for the journal.', 'courtneyr-child' ),
	);
}

/**
 * Is this the single view of a check-in?
 *
 * @return bool
 */
function is_checkin_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'checkin', 'kind', \get_queried_object_id() );
}

/**
 * The place a page may print, by the block's privacy setting.
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @return string "Locality, Region, Country" as far as privacy allows, or ''.
 */
function safe_place( array $attrs ): string {
	if ( 'private' === ( $attrs['locationPrivacy'] ?? 'approximate' ) ) {
		return '';
	}
	return implode(
		', ',
		array_filter(
			array(
				trim( (string) ( $attrs['locality'] ?? '' ) ),
				trim( (string) ( $attrs['region'] ?? '' ) ),
				trim( (string) ( $attrs['country'] ?? '' ) ),
			)
		)
	);
}

/**
 * Drop the featured image when the check-in block already shows that photo.
 *
 * The block's `photo` attribute holds an upload URL; the featured image is
 * an attachment. When they resolve to the same file the page would show the
 * selfie twice, so the template's wide hero goes and the block's copy is
 * placed as the supporting snapshot instead. A featured image the block
 * does not carry stays, and cr-post-kinds.css shrinks it to a snapshot.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function suppress_duplicate_featured_image( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) || ! is_checkin_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$checkin = find_checkin_block( $post );
	$photo   = trim( (string) ( $checkin['attrs']['photo'] ?? '' ) );
	if ( '' === $photo ) {
		return $html;
	}
	$thumb_id = (int) \get_post_thumbnail_id( $post );
	if ( $thumb_id > 0 ) {
		$thumb_file = (string) \wp_basename( (string) \get_attached_file( $thumb_id ) );
		$photo_file = (string) \wp_basename( \wp_parse_url( $photo, PHP_URL_PATH ) ?? '' );
		// "IMG_7127-scaled.jpeg" vs "IMG_7127.jpeg": same upload once sizes strip.
		$norm = static fn( string $f ): string => (string) preg_replace( '/(-scaled|-\d+x\d+)(?=\.[a-z0-9]+$)/i', '', strtolower( $f ) );
		if ( $norm( $thumb_file ) !== $norm( $photo_file ) ) {
			return $html;
		}
	}
	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\suppress_duplicate_featured_image', 10, 2 );

/**
 * Add the date-and-place lede under the H1.
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_checkin_single() ) {
		return $html;
	}
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__title' ) ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$checkin = find_checkin_block( $post );
	$attrs   = (array) ( $checkin['attrs'] ?? array() );
	$place   = safe_place( $attrs );

	$lede  = '<p class="cr-journal__lede">';
	$lede .= '<time class="cr-journal__lede-date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
	if ( '' !== $place ) {
		$lede .= '<span class="cr-journal__lede-sep" aria-hidden="true"> · </span><span class="cr-journal__lede-place">' . esc_html( $place ) . '</span>';
	}
	$lede .= '</p>';

	return $html . $lede;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );

/**
 * Rebuild the post body into the journal page.
 *
 * Works on the rendered post-content block, after the plugin has wrapped
 * it, and splices at markup the plugin always emits in a fixed order:
 * `<div class="pk-embed pk-embed--photo">`, `<div class="pk-note p-content">`,
 * `</article>`. Any anchor missing leaves that part alone.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function journal_page( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_checkin_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$checkin = find_checkin_block( $post );
	if ( null === $checkin || false === strpos( $html, 'k-checkin' ) ) {
		return $html;
	}
	$attrs = (array) ( $checkin['attrs'] ?? array() );
	$s     = seed( (string) $post->ID, (string) ( $attrs['osmId'] ?? '' ), (string) ( $attrs['foursquareId'] ?? '' ), (string) ( $attrs['venueName'] ?? '' ) );

	// 1. Lift the photo out of the card.
	$photo_html = '';
	$p_open     = '<div class="pk-embed pk-embed--photo">';
	$p_start    = strpos( $html, $p_open );
	if ( false !== $p_start ) {
		$p_end = strpos( $html, '</div>', $p_start );
		if ( false !== $p_end ) {
			$p_end     += 6;
			$photo_html = substr( $html, $p_start + strlen( $p_open ), $p_end - $p_start - strlen( $p_open ) - 6 );
			$html       = substr( $html, 0, $p_start ) . substr( $html, $p_end );
		}
	}

	// 2. A long note leaves the card for the notes section.
	$note_html = '';
	$note      = trim( (string) ( $attrs['note'] ?? '' ) );
	if ( mb_strlen( wp_strip_all_tags( $note ) ) > NOTE_INLINE_LIMIT ) {
		$n_open  = '<div class="pk-note p-content">';
		$n_start = strpos( $html, $n_open );
		if ( false !== $n_start ) {
			$n_end = strpos( $html, '</div>', $n_start );
			if ( false !== $n_end ) {
				$n_end    += 6;
				$note_html = substr( $html, $n_start, $n_end - $n_start );
				$html      = substr( $html, 0, $n_start ) . substr( $html, $n_end );
			}
		}
	}

	// 3. Stamps close the card; the label reads as the card's eyebrow.
	$close = strpos( $html, '</article>' );
	if ( false === $close ) {
		return $html;
	}
	$html  = substr( $html, 0, $close ) . render_stamps( $attrs, $post ) . substr( $html, $close );
	$label = '<p class="pk-kindlabel">';
	$lpos  = strpos( $html, $label );
	if ( false !== $lpos ) {
		$lend = strpos( $html, '</p>', $lpos );
		if ( false !== $lend ) {
			$html = substr( $html, 0, $lpos ) . $label . esc_html__( 'Checked in at', 'courtneyr-child' ) . substr( $html, $lend );
		}
	}

	// 4. What follows the card: margin notes, notes section, snapshot,
	//    time and place row. Margin notes are asides in the page grid.
	$copy   = margin_copy()[ pick( $s, 2, count( margin_copy() ) ) ];
	$after  = '';
	$after .= '<aside class="cr-journal__margin cr-journal__margin--1"><p class="cr-hand cr-hand--underline">' . esc_html( $copy[0] ) . '</p></aside>';

	if ( '' !== $note_html ) {
		$after .= '<section class="cr-journal__notes"><h2 class="cr-journal__notes-title">' . esc_html__( 'Notes from this check-in', 'courtneyr-child' ) . '</h2>' . $note_html . '</section>';
		$after .= '<aside class="cr-journal__margin cr-journal__margin--2"><p class="cr-hand cr-hand--orange cr-hand--burst">' . esc_html( $copy[1] ) . '</p></aside>';
	}

	if ( '' !== $photo_html ) {
		$caption = caption_copy()[ pick( $s, 5, count( caption_copy() ) ) ];
		$after  .= '<figure class="cr-journal__photo">' . $photo_html . '<figcaption class="cr-hand cr-hand--caption">' . esc_html( $caption ) . ' <span class="cr-hand__heart" aria-hidden="true">♡</span></figcaption></figure>';
		$after  .= '<aside class="cr-journal__margin cr-journal__margin--3"><p class="cr-hand cr-hand--underline">' . esc_html( $copy[ '' !== $note_html ? 2 : 1 ] ) . '</p></aside>';
	}

	$ts    = ! empty( $attrs['checkinAt'] ) ? (int) strtotime( (string) $attrs['checkinAt'] ) : 0;
	$ts    = $ts > 0 ? $ts : (int) get_post_time( 'U', true, $post );
	$place = safe_place( $attrs );

	$after .= '<footer class="cr-journal__meta">';
	$after .= '<p class="cr-journal__meta-item cr-journal__meta-item--time"><span class="cr-journal__meta-icon" aria-hidden="true"></span><span class="cr-journal__meta-text"><time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '<br>' . esc_html( (string) wp_date( get_option( 'time_format' ) . ' (T)', $ts ) ) . '</time></span></p>';
	if ( '' !== $place ) {
		$after .= '<p class="cr-journal__meta-item cr-journal__meta-item--place"><span class="cr-journal__meta-icon" aria-hidden="true"></span><span class="cr-journal__meta-text">' . implode( '<br>', array_map( 'esc_html', explode( ', ', $place ) ) ) . '</span></p>';
	}
	$after .= '</footer>';

	// 5. The card and everything after it sit in the journal grid. Outpost
	//    wraps the card in a group; the editor may not. Wrapping here works
	//    for both.
	$open  = strpos( $html, '<article' );
	$close = strpos( $html, '</article>' ) + 10;
	if ( false === $open || $open > $close ) {
		return $html;
	}
	$html = substr( $html, 0, $open ) . '<div class="cr-journal">' . substr( $html, $open, $close - $open ) . $after . '</div>' . substr( $html, $close );

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'k-checkin' ) ) ) {
		$tags->add_class( 'cr-passport' );
		$tags->add_class( 'cr-passport--single' );
		$html = $tags->get_updated_html();
	}

	// The map frame is consent-gated; Perfmatters' lazy loader must not
	// re-blank it after Complianz sets the real source (see journal.php).
	return \Courtneyr\Child\Journal\skip_lazy_iframes( $html );
}
add_filter( 'render_block', __NAMESPACE__ . '\\journal_page', 20, 2 );
