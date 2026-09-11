<?php
/**
 * Quote kind: a note pinned into the zine page.
 *
 * The Quote kind has no plugin card. A single is the plugin's h-entry
 * wrapper around a core quote block (paragraphs and a <cite>) followed
 * by the syndication links; /stream shows the plugin's generic card
 * (title link, date, a summary, "Read more") with no citation at all.
 *
 * This file gives both surfaces one pinned-note primitive: a push pin
 * (inline SVG, a real thumbtack: domed cap, flared base, neck, steel
 * shaft, cast shadow) in a palette colour chosen by the post's seed, an
 * oversized opening quote mark in the top-left corner, ruled paper with
 * a slight seeded tilt, the citation attached to the quote, and a small
 * mono date. On /stream the card keeps the plugin's excerpt and gains the
 * quote's <cite>; the single moves the H1 onto the sheet, keeps the
 * blockquote and its cite as they are, and adds two handwritten margin
 * notes in the journal grid. Nothing decorative reaches assistive
 * technology; the plugin's e-content, u-url, dt-published and
 * syndication markup are untouched. cr-post-kinds.css paints it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\QuoteNote;

use function Courtneyr\Child\Journal\aside;
use function Courtneyr\Child\Journal\margin_lines;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Push-pin colours: the site palette only, each with a highlight and a
 * shadow shade for the cap's shading (base, highlight, shadow).
 */
const PALETTE = array(
	'russian-violet'   => array( '#241c4a', '#6a5fa8', '#14102b' ),
	'periwinkle'       => array( '#bcb5e3', '#efeaf9', '#8f86c4' ),
	'glaucous'         => array( '#647baf', '#a8b6d8', '#3f5285' ),
	'sky-blue'         => array( '#8ecae6', '#d2ecf6', '#4e9dc2' ),
	'blue-green'       => array( '#219ebc', '#7dd0e3', '#14657a' ),
	'cerulean'         => array( '#126782', '#7cc1d4', '#0b3f50' ),
	'prussian-blue'    => array( '#023047', '#3a6a86', '#011a27' ),
	'selective-yellow' => array( '#ffb703', '#ffe08a', '#b57f00' ),
	'ut-orange'        => array( '#fb8500', '#ffb066', '#b25c00' ),
	'light-orange'     => array( '#fee2c3', '#fff1df', '#d9a86e' ),
);

/**
 * Tilts a note may take: subtle, four values, chosen by seed.
 */
const TILTS = array( '-0.8deg', '-0.35deg', '0.35deg', '0.8deg' );

/**
 * Is this the single view of a Quote?
 *
 * @return bool
 */
function is_quote_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'quote', 'kind', \get_queried_object_id() );
}

/**
 * The artifact's inline custom properties: pin colours and tilt, fixed per
 * post; the paper inherits them.
 *
 * @param int $seed Stable seed.
 * @return string A style attribute.
 */
function note_style( int $seed ): string {
	$keys  = array_keys( PALETTE );
	$key   = $keys[ pick( $seed, 8, count( $keys ) ) ];
	$shade = PALETTE[ $key ];
	return 'style="--cr-pin:' . $shade[0] . ';--cr-pin-hi:' . $shade[1] . ';--cr-pin-lo:' . $shade[2] . ';--cr-note-tilt:' . TILTS[ pick( $seed, 9, count( TILTS ) ) ] . '"';
}

/**
 * The push pin: a thumbtack seen from slightly above, coloured by the
 * note's custom properties (the steel shaft and the cast shadow are fixed).
 *
 * @return string
 */
function pin_svg(): string {
	return '<svg class="cr-note__pin" viewBox="0 0 44 60" aria-hidden="true" focusable="false">'
		. '<ellipse cx="24" cy="55.5" rx="10" ry="3" fill="#241c4a" opacity="0.22"/>'
		. '<path d="M21.3 37.5h1.9l-.55 17.5h-.8z" fill="#8f96a3"/>'
		. '<path d="M21.6 37.5h.6l-.4 17h-.35z" fill="#e3e7ec"/>'
		. '<ellipse cx="22" cy="35.5" rx="9.5" ry="3.6" fill="var(--cr-pin-lo, #0b3f50)"/>'
		. '<path d="M17.2 26.5h9.6l1.6 9.2H15.6z" fill="var(--cr-pin-lo, #0b3f50)"/>'
		. '<path d="M18.4 26.5h2.4l-1 8.6h-2.5z" fill="var(--cr-pin, #126782)" opacity="0.85"/>'
		. '<path d="M8 12c0-6.6 6.3-11 14-11s14 4.4 14 11v9.5c0 4-6.3 6.8-14 6.8S8 25.5 8 21.5z" fill="var(--cr-pin, #126782)"/>'
		. '<path d="M8 21.5c0 4 6.3 6.8 14 6.8s14-2.8 14-6.8v-3.4c0 4-6.3 6.5-14 6.5S8 22.1 8 18.1z" fill="var(--cr-pin-lo, #0b3f50)"/>'
		. '<ellipse cx="22" cy="12" rx="14" ry="6.6" fill="var(--cr-pin-hi, #7cc1d4)"/>'
		. '<ellipse cx="22" cy="12.6" rx="10.6" ry="4.3" fill="var(--cr-pin, #126782)" opacity="0.55"/>'
		. '<ellipse cx="16.4" cy="9.6" rx="4.3" ry="1.7" fill="#ffffff" opacity="0.6" transform="rotate(-12 16.4 9.6)"/>'
		. '</svg>';
}

/**
 * The decorative opening mark.
 *
 * @return string
 */
function mark(): string {
	return '<span class="cr-note__mark" aria-hidden="true">&#8220;</span>';
}

/**
 * The first core quote block's citation, as safe inline HTML.
 *
 * @param \WP_Post $post Post.
 * @return string '' when the post has no cited quote.
 */
function citation( \WP_Post $post ): string {
	$found = '';
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( '' !== $found ) {
				return;
			}
			if ( 'core/quote' === ( $block['blockName'] ?? '' ) || 'core/pullquote' === ( $block['blockName'] ?? '' ) ) {
				$html = (string) ( $block['innerHTML'] ?? '' );
				if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
					$html = implode( '', array_filter( (array) ( $block['innerContent'] ?? array() ), 'is_string' ) );
				}
				if ( preg_match( '/<cite[^>]*>(.*?)<\/cite>/is', $html, $m ) ) {
					$found = trim( $m[1] );
				}
				if ( '' === $found && ! empty( $block['attrs']['citation'] ) ) {
					$found = (string) $block['attrs']['citation'];
				}
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return '' === $found ? '' : wp_kses(
		$found,
		array(
			'a'      => array( 'href' => true, 'rel' => true, 'target' => true, 'class' => true ),
			'em'     => array(),
			'strong' => array(),
			'span'   => array( 'class' => true ),
		)
	);
}

/**
 * /stream: the plugin's generic card becomes the pinned note.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed block (unused).
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function stream_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) || false === strpos( $html, 'k-quote' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'quote', 'kind', $post ) ) {
		return $html;
	}
	$s = seed( (string) $post->ID, $post->post_title );

	// The mark, first in the article (the paper); the pin comes later, on
	// the artifact wrapper outside the paper's clipped box.
	$open = strpos( $html, '<article' );
	$gt   = false !== $open ? strpos( $html, '>', $open ) : false;
	if ( false === $gt ) {
		return $html;
	}
	$html = substr( $html, 0, $gt + 1 ) . mark() . substr( $html, $gt + 1 );

	// The date leaves the caption; the citation and the date go before the footer.
	$date = '';
	if ( preg_match( '/<p class="pk-sub pk-stream-date">.*?<\/p>/s', $html, $dm, PREG_OFFSET_CAPTURE ) ) {
		$date = $dm[0][0];
		$html = substr( $html, 0, (int) $dm[0][1] ) . substr( $html, (int) $dm[0][1] + strlen( $date ) );
	}
	$cite = citation( $post );
	$tail = ( '' !== $cite ? '<p class="cr-note__cite"><span class="cr-note__dash" aria-hidden="true">&mdash; </span>' . $cite . '</p>' : '' ) . $date;
	$meta = strpos( $html, '<div class="pk-meta">' );
	$html = false !== $meta ? substr( $html, 0, $meta ) . $tail . substr( $html, $meta ) : $html . $tail;

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'cr-note' );
		$tags->add_class( 'cr-note--stream' );
		if ( '' !== $cite ) {
			$tags->add_class( 'cr-note--cited' );
		}
		$html = $tags->get_updated_html();
	}
	// The artifact: pin colours and tilt on the wrapper (custom properties
	// inherit into the paper), the pin above the paper, overflow visible.
	return '<div class="cr-note-artifact cr-note-artifact--stream" ' . note_style( $s ) . '>' . pin_svg() . $html . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card', 10, 3 );

/**
 * The template's title, held back so the sheet can carry it.
 *
 * @var string
 */
$courtneyr_quote_title = '';

/**
 * Single: the H1 moves onto the sheet (captured here, printed by note_page()).
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function hold_title( string $html, array $block ): string {
	global $courtneyr_quote_title;
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_quote_single() ) {
		return $html;
	}
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__title' ) || '' !== $courtneyr_quote_title ) {
		return $html;
	}
	// Post Formats for Block Themes hides titles on quote-format posts unless
	// the title block carries its own opt-out class; on the sheet the title shows.
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'h1' ) ) {
		$tags->add_class( 'is-style-show-format-title' );
		$html = $tags->get_updated_html();
	}
	$courtneyr_quote_title = $html;
	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\hold_title', 20, 2 );

/**
 * Single: the quote block becomes a larger pinned notebook sheet.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function note_page( string $html, array $block ): string {
	global $courtneyr_quote_title;
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_quote_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$q_start = strpos( $html, '<blockquote' );
	$q_end   = false !== $q_start ? strpos( $html, '</blockquote>', $q_start ) : false;
	if ( false === $q_end ) {
		// No quote block: the title goes back where it was.
		if ( '' !== $courtneyr_quote_title ) {
			$html                   = $courtneyr_quote_title . $html;
			$courtneyr_quote_title = '';
		}
		return $html;
	}
	$q_end += 13;
	$s      = seed( (string) $post->ID, $post->post_title );
	$ts     = (int) get_post_time( 'U', true, $post );

	$sheet  = '<div class="cr-note-artifact cr-note-artifact--single" ' . note_style( $s ) . '>' . pin_svg();
	$sheet .= '<div class="cr-note cr-note--single">' . mark();
	$sheet .= '<p class="cr-note__label cr-sr-only">' . esc_html__( 'Quote', 'courtneyr-child' ) . '</p>';
	$sheet .= $courtneyr_quote_title;
	$sheet .= substr( $html, $q_start, $q_end - $q_start );
	$sheet .= '<p class="cr-note__date"><time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '</time></p>';
	$sheet .= '</div></div>';
	$courtneyr_quote_title = '';

	$lines = margin_lines( $s, 'quote' );
	$page  = '<div class="cr-journal">' . $sheet
		. aside( $lines[0], 1, 'cr-hand--arrow' )
		. aside( $lines[1], 2, 'cr-hand--underline' )
		. '</div>';

	return substr( $html, 0, $q_start ) . $page . substr( $html, $q_end );
}
add_filter( 'render_block', __NAMESPACE__ . '\\note_page', 20, 2 );
