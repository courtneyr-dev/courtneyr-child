<?php
/**
 * Output-level accessibility fixes for markup the theme does not author.
 *
 * Each filter closes one class of Accessibility Checker finding at the point
 * the markup is emitted, so it holds for every post and comment instead of
 * being dismissed one instance at a time. Sources of the original markup:
 * core's comment blocks, comment text (webmention backfeed), Yoast Comment
 * Hacks, and the block editor's empty paragraphs.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\A11yOutput;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment avatars are decorative: the comment-author-name block sits beside
 * them, so alt="<name>" repeats the name and, for repeat commenters, fails
 * the duplicate-alt check. Linked avatars keep their alt (it names the link).
 *
 * Context (the comment id) travels on the WP_Block instance, not on the
 * parsed block array.
 *
 * @param string    $content  Rendered block.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance carrying context.
 * @return string
 */
function decorative_comment_avatar( string $content, array $block, \WP_Block $instance ): string {
	if ( empty( $instance->context['commentId'] ) || ! empty( $block['attrs']['isLink'] ) ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( $content );
	if ( $tags->next_tag( 'img' ) ) {
		$tags->set_attribute( 'alt', '' );
		$tags->set_attribute( 'aria-hidden', 'true' );
		$tags->set_attribute( 'role', 'presentation' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/avatar', __NAMESPACE__ . '\\decorative_comment_avatar', 10, 3 );

/**
 * Links in comment text whose visible text is the bare URL (make_clickable,
 * webmention "Continue reading: https://…") read as a URL. Show host + path
 * instead; the href is unchanged.
 *
 * @param string $text Comment text.
 * @return string
 */
function readable_url_links( string $text ): string {
	if ( false === stripos( $text, '<a ' ) ) {
		return $text;
	}
	$normalize = static function ( string $url ): string {
		return rtrim( (string) preg_replace( '#^(https?://)?(www\.)?#i', '', strtolower( $url ) ), '/' );
	};
	return (string) preg_replace_callback(
		'#<a\b([^>]*\bhref="([^"]+)"[^>]*)>\s*((?:https?://|www\.)[^<\s]+)\s*</a>#i',
		static function ( array $m ) use ( $normalize ): string {
			if ( $normalize( $m[2] ) !== $normalize( $m[3] ) ) {
				return $m[0];
			}
			$parts = wp_parse_url( $m[2] );
			$host  = (string) ( $parts['host'] ?? '' );
			$path  = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
			if ( mb_strlen( $path ) > 40 ) {
				$path = mb_substr( $path, 0, 37 ) . '…';
			}
			if ( '' === $host ) {
				return $m[0];
			}
			return '<a' . $m[1] . '>' . esc_html( $host . $path ) . '</a>';
		},
		$text
	);
}
add_filter( 'comment_text', __NAMESPACE__ . '\\readable_url_links', 20 );

/**
 * Yoast Comment Hacks appends a moderator-only "Remove URL" control inside
 * <small style="font-size:70% !important"> which computes under 10px. Keep
 * the control, drop the shrink. Runs after the plugin's own render_block
 * filter (priority 90).
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function comment_hacks_small( string $content, array $block ): string {
	if ( 'core/comment-edit-link' !== ( $block['blockName'] ?? '' ) || false === strpos( $content, 'comment-remove-url' ) ) {
		return $content;
	}
	return str_replace( '<small style="font-size:70% !important;">', '<small>', $content );
}
add_filter( 'render_block', __NAMESPACE__ . '\\comment_hacks_small', 91, 2 );

/**
 * An empty paragraph (a stray Enter in the editor, or Post Formats' status
 * placeholder left blank) renders as <p></p> and is announced as a blank
 * line. Drop it on the front end; the editor still shows the block.
 *
 * @param string $content Rendered block.
 * @return string
 */
function drop_empty_paragraph( string $content ): string {
	if ( is_admin() ) {
		return $content;
	}
	return preg_match( '#^\s*<p\b[^>]*>(?:\s|&nbsp;|\xC2\xA0)*</p>\s*$#u', $content ) ? '' : $content;
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\drop_empty_paragraph' );
