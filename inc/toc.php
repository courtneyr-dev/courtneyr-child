<?php
/**
 * Yoast table of contents: zine presentation, and no ✦ list marker when
 * every entry already opens with an emoji.
 *
 * Courtney asked on 2026-11-04 for the table of contents to drop its
 * visible bullets when the headings carry emoji ("it looks like double
 * emoji") and to look like the rest of the zine. Merge Conflicts posts put
 * one emoji at the start of every H2 and H3, so Yoast's list repeats a ✦
 * marker in front of an emoji on every line.
 *
 * Architecture: the block is Yoast's, so there's no Ollie primitive to
 * reuse. A block style was rejected because a style can't tell whether the
 * headings start with emoji. Selected: small integration PHP that adds two
 * classes through the HTML API and loads one sheet (cr-toc.css) only on
 * pages that render the block.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Toc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HANDLE = 'courtneyr-toc';

/**
 * Code point ranges that open an emoji in heading text: the pictograph and
 * symbol planes, dingbats, miscellaneous symbols and technical, arrows, and
 * the single symbols that take an emoji presentation (©, ®, ‼, ⁉, ™, ℹ, Ⓜ,
 * 〰, 〽, ㊗, ㊙). A plain letter, digit or punctuation mark never matches.
 */
const EMOJI_RANGES = array(
	array( 0x1F000, 0x1FAFF ),
	array( 0x2600, 0x27BF ),
	array( 0x2300, 0x23FF ),
	array( 0x2B00, 0x2BFF ),
	array( 0x2190, 0x21FF ),
	array( 0x25A0, 0x25FF ),
	array( 0x00A9, 0x00A9 ),
	array( 0x00AE, 0x00AE ),
	array( 0x203C, 0x203C ),
	array( 0x2049, 0x2049 ),
	array( 0x2122, 0x2122 ),
	array( 0x2139, 0x2139 ),
	array( 0x24C2, 0x24C2 ),
	array( 0x3030, 0x3030 ),
	array( 0x303D, 0x303D ),
	array( 0x3297, 0x3297 ),
	array( 0x3299, 0x3299 ),
);

/**
 * Register the table of contents sheet.
 */
function register_style(): void {
	wp_register_style( HANDLE, COURTNEYR_CHILD_URI . '/assets/css/cr-toc.css', array(), COURTNEYR_CHILD_VERSION );
}
add_action( 'init', __NAMESPACE__ . '\\register_style' );

/**
 * Whether text opens with an emoji.
 *
 * @param string $text Entry text, entities allowed.
 * @return bool
 */
function starts_with_emoji( string $text ): bool {
	$text = trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $text ) {
		return false;
	}
	$code_point = mb_ord( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' );
	if ( false === $code_point ) {
		return false;
	}
	foreach ( EMOJI_RANGES as $range ) {
		if ( $code_point >= $range[0] && $code_point <= $range[1] ) {
			return true;
		}
	}
	return false;
}

/**
 * The text of each link in the rendered block, in order.
 *
 * @param string $html Rendered block HTML.
 * @return string[]
 */
function entry_texts( string $html ): array {
	$texts   = array();
	$current = null;
	$tokens  = new \WP_HTML_Tag_Processor( $html );
	while ( $tokens->next_token() ) {
		$type = $tokens->get_token_type();
		if ( '#tag' === $type && 'A' === $tokens->get_tag() ) {
			if ( $tokens->is_tag_closer() ) {
				if ( null !== $current ) {
					$texts[] = $current;
				}
				$current = null;
			} else {
				$current = '';
			}
		} elseif ( '#text' === $type && null !== $current ) {
			$current .= $tokens->get_modifiable_text();
		}
	}
	return $texts;
}

/**
 * Whether every entry opens with an emoji.
 *
 * @param string[] $texts Entry texts.
 * @return bool
 */
function all_start_with_emoji( array $texts ): bool {
	if ( array() === $texts ) {
		return false;
	}
	foreach ( $texts as $text ) {
		if ( ! starts_with_emoji( $text ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Add the zine classes to the block wrapper and load its sheet.
 *
 * @param string $block_content Rendered block HTML.
 * @return string
 */
function decorate( string $block_content ): string {
	if ( '' === trim( $block_content ) ) {
		return $block_content;
	}

	wp_enqueue_style( HANDLE );

	$emoji = all_start_with_emoji( entry_texts( $block_content ) );
	$tags  = new \WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag() ) {
		$tags->add_class( 'cr-toc' );
		if ( $emoji ) {
			$tags->add_class( 'cr-toc--emoji' );
		}
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_yoast-seo/table-of-contents', __NAMESPACE__ . '\\decorate' );
