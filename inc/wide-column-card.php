<?php
/**
 * The card a wide page's record replaces, found by what it is, not by what it says.
 *
 * The decision in inc/single-wide-column.php is made before the post
 * content renders: it says a kind's first card is a top-level block. The
 * kind's content filter then cuts the rendered HTML at that card. Searching the HTML for the card's class
 * name finds text too: a code sample, a comment or an attribute that quotes
 * the name. So the kind's own card renderer puts a token on the card element
 * it printed (the token is random per request, so no author can write it),
 * and top_level_card() looks for that attribute on a tag that is a direct
 * child of the slice, with every tag before and inside it balanced. Anything
 * else answers null, and the kind filter leaves the HTML as it was. No mark
 * outlives the post content: release() strips them all.
 *
 * Marks go on only while the main post content of a wide page renders, so a
 * page without the column is byte for byte what it was.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWideColumn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The attribute that carries the token. */
const CARD_MARK_ATTRIBUTE = 'data-cr-wide-card';

/** HTML elements with no closing tag. */
const VOID_ELEMENTS = array( 'AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR' );

/**
 * This request's token.
 *
 * @return string
 */
function card_mark(): string {
	static $mark = '';
	if ( '' === $mark ) {
		$mark = bin2hex( random_bytes( 8 ) );
	}
	return $mark;
}

/**
 * Whether the main post content of a wide page is rendering now.
 *
 * @param bool|null $set True when it starts, false when it ends; null to read.
 * @return bool
 */
function marking( ?bool $set = null ): bool {
	static $on = false;
	if ( null !== $set ) {
		$on = $set;
	}
	return $on;
}

/**
 * Put this request's token on a rendered card's own element.
 *
 * Only while marking() is on, and only when the HTML's first tag is the
 * card's article with the given class.
 *
 * @param string $html       Rendered card.
 * @param string $class_name The card's class.
 * @return string
 */
function mark_card( string $html, string $class_name ): string {
	if ( ! marking() ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag() && 'ARTICLE' === $tags->get_tag() && $tags->has_class( $class_name ) ) {
		$tags->set_attribute( CARD_MARK_ATTRIBUTE, card_mark() );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Remove every mark this request put in the HTML.
 *
 * @param string $html HTML.
 * @return string
 */
function unmark( string $html ): string {
	return str_replace( ' ' . CARD_MARK_ATTRIBUTE . '="' . card_mark() . '"', '', $html );
}

/**
 * Where the first marked card sits in a run of blocks, when it is one of them.
 *
 * @param string $html The blocks of the entry, from its first child to its last.
 * @return array{0: int, 1: int}|null Byte offsets of the card element's start and end; null when no card is
 *                                    marked, when the first marked card is inside another element, or when
 *                                    the tags do not balance (the structure is not what it seems).
 */
function top_level_card( string $html ): ?array {
	// A Tag Processor that also reports where the current token sits in the HTML.
	$tags = new class( $html ) extends \WP_HTML_Tag_Processor {
		/**
		 * Byte offset where the current token starts.
		 *
		 * @return int
		 */
		public function token_start(): int {
			$this->set_bookmark( 'here' );
			return $this->bookmarks['here']->start;
		}

		/**
		 * Byte offset just past the current token.
		 *
		 * @return int
		 */
		public function token_end(): int {
			$this->set_bookmark( 'here' );
			return $this->bookmarks['here']->start + $this->bookmarks['here']->length;
		}
	};
	$open = array();
	$from = null;
	$to   = null;
	while ( $tags->next_token() ) {
		if ( '#tag' !== $tags->get_token_type() ) {
			continue;
		}
		$tag = (string) $tags->get_tag();
		if ( $tags->is_tag_closer() ) {
			if ( in_array( $tag, VOID_ELEMENTS, true ) ) {
				continue;
			}
			if ( array() === $open || end( $open ) !== $tag ) {
				return null;
			}
			array_pop( $open );
			if ( null !== $from && null === $to && array() === $open ) {
				$to = $tags->token_end();
			}
			continue;
		}
		if ( null === $from && card_mark() === $tags->get_attribute( CARD_MARK_ATTRIBUTE ) ) {
			if ( array() !== $open ) {
				return null;
			}
			$from = $tags->token_start();
		}
		$foreign = in_array( 'SVG', $open, true ) || in_array( 'MATH', $open, true );
		// Void elements, self-closed foreign elements, and script, style, textarea and title (one token each) open nothing.
		if ( in_array( $tag, VOID_ELEMENTS, true ) || ( $foreign && $tags->has_self_closing_flag() ) || in_array( $tag, array( 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'NOEMBED', 'NOFRAMES', 'XMP' ), true ) ) {
			continue;
		}
		$open[] = $tag;
	}
	if ( null === $from || null === $to || array() !== $open || $tags->paused_at_incomplete_token() ) {
		return null;
	}
	return array( $from, $to );
}

/**
 * Turn marking on when the main post content of a wide page starts to render.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return array<string, mixed>
 */
function start_marking( array $block ): array {
	if ( is_content_block( $block ) && applies() ) {
		marking( true );
	}
	return $block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\start_marking' );

/**
 * When the main post content has rendered: strip any mark left, and stop marking.
 *
 * Runs after the kind filters (priority 20) cut the HTML at the card.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function release( string $html, array $block ): string {
	if ( ! is_content_block( $block ) ) {
		return $html;
	}
	marking( false );
	return unmark( $html );
}
add_filter( 'render_block', __NAMESPACE__ . '\\release', 30, 2 );

/**
 * The single template's post content block.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return bool
 */
function is_content_block( array $block ): bool {
	return 'core/post-content' === ( $block['blockName'] ?? '' ) && in_array( 'single-post__content', explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) ), true );
}
