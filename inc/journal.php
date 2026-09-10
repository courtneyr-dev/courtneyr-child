<?php
/**
 * Journal page primitives shared by the kind singles.
 *
 * The check-in passport page and the watch VHS page are both "opened
 * journal" compositions: a card, handwritten margin notes in Rock Salt,
 * an optional notes section, and a quiet meta row. The markup for those
 * pieces lives here so each single only decides what goes in them.
 * cr-post-kinds.css paints `.cr-journal`, `.cr-hand`, `.cr-journal__notes`
 * and `.cr-journal__meta` for every kind that opts in.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Journal;

use function Courtneyr\Child\Stamps\pick;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handwritten margin lines, in sets of three so a page never repeats a
 * line. Short, place-neutral, time-neutral: nothing here claims a fact.
 *
 * @param string $flavour 'travel', 'film' or 'read'.
 * @return array<int, array<int, string>>
 */
function margin_copy( string $flavour ): array {
	if ( 'film' === $flavour ) {
		return array(
			array(
				__( 'Same stories. A more curious life.', 'courtneyr-child' ),
				__( 'Good films. Brighter days.', 'courtneyr-child' ),
				__( 'People. Places. Stories.', 'courtneyr-child' ),
			),
			array(
				__( 'A brilliant little film.', 'courtneyr-child' ),
				__( 'Worth the rewind.', 'courtneyr-child' ),
				__( 'Be kind, rewind.', 'courtneyr-child' ),
			),
			array(
				__( 'Watched. Noted. Kept.', 'courtneyr-child' ),
				__( 'Small screen, big feelings.', 'courtneyr-child' ),
				__( 'Still thinking about it.', 'courtneyr-child' ),
			),
		);
	}
	if ( 'read' === $flavour ) {
		return array(
			array(
				__( 'Good books. Brighter days.', 'courtneyr-child' ),
				__( 'Keep reading. Keep exploring.', 'courtneyr-child' ),
				__( 'Notes in the margins.', 'courtneyr-child' ),
			),
			array(
				__( 'Books make a quieter, kinder world.', 'courtneyr-child' ),
				__( 'Different places. Same pull.', 'courtneyr-child' ),
				__( 'Read. Noted. Kept.', 'courtneyr-child' ),
			),
			array(
				__( 'Same shelf. New doors.', 'courtneyr-child' ),
				__( 'Pages over pixels.', 'courtneyr-child' ),
				__( 'Come back to this one.', 'courtneyr-child' ),
			),
		);
	}
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
 * Pick one set of margin lines for a seed.
 *
 * @param int    $seed    Stable seed.
 * @param string $flavour 'travel', 'film' or 'read'.
 * @return string[] Three lines.
 */
function margin_lines( int $seed, string $flavour ): array {
	$sets = margin_copy( $flavour );
	return $sets[ pick( $seed, 2, count( $sets ) ) ];
}

/**
 * A handwritten margin note.
 *
 * @param string $text     The line.
 * @param int    $position 1, 2 or 3 — which margin slot it takes.
 * @param string $variant  Extra .cr-hand modifier(s), e.g. 'cr-hand--underline'.
 * @return string
 */
function aside( string $text, int $position, string $variant = 'cr-hand--underline' ): string {
	return '<aside class="cr-journal__margin cr-journal__margin--' . (int) $position . '"><p class="cr-hand ' . esc_attr( $variant ) . '">' . esc_html( $text ) . '</p></aside>';
}

/**
 * The notes section wrapper.
 *
 * @param string $title Section heading.
 * @param string $html  Already-escaped body HTML.
 * @return string
 */
function notes_section( string $title, string $html ): string {
	return '<section class="cr-journal__notes"><h2 class="cr-journal__notes-title">' . esc_html( $title ) . '</h2>' . $html . '</section>';
}

/**
 * One item of the quiet meta row.
 *
 * @param string $kind  'time' or 'place' or 'film' (picks the glyph).
 * @param string $html  Already-escaped inner HTML.
 * @param string $label Optional small label under the text.
 * @return string
 */
function meta_item( string $kind, string $html, string $label = '' ): string {
	$out  = '<p class="cr-journal__meta-item cr-journal__meta-item--' . esc_attr( $kind ) . '"><span class="cr-journal__meta-icon" aria-hidden="true"></span><span class="cr-journal__meta-text">' . $html;
	if ( '' !== $label ) {
		$out .= '<span class="cr-journal__meta-label">' . esc_html( $label ) . '</span>';
	}
	return $out . '</span></p>';
}

/**
 * The meta row.
 *
 * @param string[] $items Rendered meta_item() strings.
 * @return string
 */
function meta_row( array $items ): string {
	return '<footer class="cr-journal__meta">' . implode( '', $items ) . '</footer>';
}

/**
 * A card block's attributes with the plugin's post-meta fallback.
 *
 * The plugin mirrors card attributes into `_pkiw_*` meta (Card_Meta_Sync)
 * and fills empty attributes from that meta when it renders, so a post
 * whose block stores only some fields still shows the rest. Read them
 * the same way. Numeric attributes come back as integers.
 *
 * @param \WP_Post             $post       Post.
 * @param string               $block_name e.g. 'post-kinds-indieweb/read-card'.
 * @param array<string, mixed> $attrs      Parsed block attributes.
 * @return array<string, mixed>
 */
function card_attrs( \WP_Post $post, string $block_name, array $attrs ): array {
	$map = array();
	if ( class_exists( '\\PKIW\\Card_Meta_Sync' ) && defined( '\\PKIW\\Card_Meta_Sync::ATTR_META_MAP' ) ) {
		$map = (array) ( \PKIW\Card_Meta_Sync::ATTR_META_MAP[ $block_name ] ?? array() );
	}
	$numeric = array( 'pageCount', 'currentPage', 'rating', 'seasonNumber', 'episodeNumber', 'releaseYear' );
	foreach ( $map as $attr => $suffix ) {
		if ( isset( $attrs[ $attr ] ) && '' !== $attrs[ $attr ] && 0 !== $attrs[ $attr ] ) {
			continue;
		}
		$value = get_post_meta( $post->ID, '_pkiw_' . $suffix, true );
		if ( is_string( $value ) && '' !== $value ) {
			$attrs[ $attr ] = in_array( $attr, $numeric, true ) && is_numeric( $value ) ? (int) $value : $value;
		}
	}
	return $attrs;
}

/**
 * Mark a card's iframes so Perfmatters' lazy load leaves them alone.
 *
 * Complianz gates an embed with `src="about:blank"` and the real URL in
 * `data-src-cmplz`; Perfmatters' iframe lazy load then keeps that blank
 * source and writes it back on scroll, undoing the consent activation.
 * Perfmatters honours the `skip-lazy` class and `data-skip-lazy`.
 *
 * @param string $html Card HTML.
 * @return string
 */
function skip_lazy_iframes( string $html ): string {
	if ( false === strpos( $html, '<iframe' ) ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'iframe' ) ) {
		$tags->add_class( 'skip-lazy' );
		$tags->set_attribute( 'data-skip-lazy', '1' );
		$tags->set_attribute( 'data-no-lazy', '1' );
	}
	return $tags->get_updated_html();
}

/**
 * Wrap a card and what follows it in the journal grid.
 *
 * @param string $html  Content HTML containing one `<article … </article>`.
 * @param string $after Markup to place after the article, inside the grid.
 * @return string Unchanged HTML when no article is found.
 */
function wrap( string $html, string $after ): string {
	$open  = strpos( $html, '<article' );
	$close = strpos( $html, '</article>' );
	if ( false === $open || false === $close || $open > $close ) {
		return $html;
	}
	$close += 10;
	return substr( $html, 0, $open ) . '<div class="cr-journal">' . substr( $html, $open, $close - $open ) . $after . '</div>' . substr( $html, $close );
}
