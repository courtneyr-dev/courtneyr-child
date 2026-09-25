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
		if ( '' === trim( (string) $tags->get_attribute( 'src' ) ) ) {
			return ''; // No avatar resolved for this commenter: an empty <img src=""> is a broken image, not a decoration.
		}
		$tags->set_attribute( 'alt', '' );
		$tags->set_attribute( 'aria-hidden', 'true' );
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
 * A block-bindings paragraph (e.g. cr-listen-sources__value, bound to
 * post-kinds/kind-meta) legitimately renders empty when the bound meta is
 * unset, and CSS such as cr-post-kinds.css's
 * .cr-listen-sources__rating:has(> .cr-listen-sources__value:empty) depends
 * on that empty element staying in the DOM to hide its wrapper. Skip any
 * paragraph carrying bindings metadata, or the cr-listen-sources__value
 * class specifically, so that rule keeps working.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block, including attrs.
 * @return string
 */
function drop_empty_paragraph( string $content, array $block ): string {
	if ( is_admin() ) {
		return $content;
	}
	if ( ! empty( $block['attrs']['metadata']['bindings'] ) ) {
		return $content;
	}
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( false !== strpos( $class_name, 'cr-listen-sources__value' ) ) {
		return $content;
	}
	return preg_match( '#^\s*<p\b[^>]*>(?:\s|&nbsp;|\xC2\xA0)*</p>\s*$#u', $content ) ? '' : $content;
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\drop_empty_paragraph', 10, 2 );

/**
 * A featured image whose alt repeats the post title, or whose attachment also
 * appears inside the content (the first content image reused as the thumbnail),
 * carries nothing the page does not already say. Mark it decorative so the
 * duplicate-alt check and screen readers both skip it; the content copy keeps
 * its alt.
 *
 * @param string    $content  Rendered block.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance carrying postId context.
 * @return string
 */
function decorative_duplicate_featured_image( string $content, array $block, \WP_Block $instance ): string {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );
	if ( ! $post_id || ! empty( $block['attrs']['isLink'] ) ) {
		return $content;
	}
	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $thumb_id ) {
		return $content;
	}
	$alt       = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
	$title     = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
	$body      = (string) get_post_field( 'post_content', $post_id );
	$duplicate = ( '' !== $alt && 0 === strcasecmp( $alt, $title ) )
		|| false !== strpos( $body, 'wp-image-' . $thumb_id . '"' )
		|| false !== strpos( $body, 'wp-image-' . $thumb_id . ' ' )
		|| false !== strpos( $body, '"id":' . $thumb_id . ',' );
	if ( ! $duplicate ) {
		// Legacy posts (pre-block editor) embed the same file by URL with no
		// wp-image-N class; match the filename, any size suffix, any format.
		$file = pathinfo( wp_basename( (string) get_attached_file( $thumb_id ) ), PATHINFO_FILENAME );
		$file = (string) preg_replace( '/-scaled$/', '', $file );
		if ( '' !== $file && preg_match( '#src="[^"]*/' . preg_quote( $file, '#' ) . '(?:-\\d+x\\d+|-scaled)?\\.[a-z0-9]{3,4}"#i', $body ) ) {
			$duplicate = true;
		}
	}
	if ( ! $duplicate ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( $content );
	if ( $tags->next_tag( 'img' ) ) {
		$tags->set_attribute( 'alt', '' );
		$tags->set_attribute( 'aria-hidden', 'true' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-featured-image', __NAMESPACE__ . '\\decorative_duplicate_featured_image', 10, 3 );

/**
 * Post Kinds prints its kind label as <p class="pk-kindlabel">. It is a label,
 * not prose, and on single pages the theme sets it large enough that checkers
 * read a short, large <p> as a heading. Swap the tag; the class-based CSS
 * (inline-block, explicit margin) renders identically.
 *
 * The theme's own relabels (stream-checkin, single-checkin, single-watch,
 * single-eat-drink, stream-media, aside-scrap) match the <p> literal, so this
 * must run after all of them: on the OUTER blocks, via the block-specific
 * hooks (which WordPress fires after the generic render_block filters) at a
 * late priority. Inner card blocks are left alone.
 *
 * @param string $content Rendered block.
 * @return string
 */
function kind_label_is_not_a_paragraph( string $content ): string {
	if ( false === strpos( $content, '<p class="pk-kindlabel"' ) ) {
		return $content;
	}
	return (string) preg_replace( '#<p class="pk-kindlabel"([^>]*)>(.*?)</p>#s', '<span class="pk-kindlabel"$1>$2</span>', $content );
}
add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\kind_label_is_not_a_paragraph', 999 );
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\kind_label_is_not_a_paragraph', 999 );
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\kind_label_is_not_a_paragraph', 999 );

/**
 * Embed fallbacks (oEmbed, Instagram in particular) ship an <img> whose alt is the
 * entire caption, hundreds of characters long. Trim any embedded image alt
 * over 300 characters to its first sentence.
 *
 * @param string|false $html Embed markup.
 * @return string|false
 */
function trim_long_embed_alt( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, 'alt="' ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'/alt="([^"]{301,})"/',
		static function ( array $m ): string {
			$text  = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5 );
			$first = preg_split( '/(?<=[.!?])\s+/', $text, 2 )[0] ?? $text;
			if ( mb_strlen( $first ) > 200 ) {
				$first = mb_substr( $first, 0, 197 ) . '…';
			}
			return 'alt="' . esc_attr( $first ) . '"';
		},
		$html
	);
}
add_filter( 'embed_oembed_html', __NAMESPACE__ . '\\trim_long_embed_alt', 20 );
add_filter( 'embed_maybe_make_link', __NAMESPACE__ . '\\trim_long_embed_alt', 100 ); // OpenGraph Fallback Embed cards return here (priority 99).

/**
 * The YouTube video id behind an Able Player `youtube-id` value. Authors write
 * it as a bare id or paste any youtube.com / youtu.be URL, and the attribute
 * reaches the filters entity-encoded.
 *
 * @param string $value Attribute value as it sits in the markup.
 * @return string Eleven-character id, or '' when the value holds none.
 */
function youtube_id( string $value ): string {
	$value = trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5 ) );
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $value ) ) {
		return $value;
	}
	if ( preg_match( '#(?:[?&]v=|youtu\.be/|/embed/|/shorts/|/live/|/v/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#', $value, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Able Player converts YouTube embed blocks into <video data-able-player>
 * players. Where the block carries no caption track, add a link to the video
 * on YouTube, where its captions and the transcript panel live, right after
 * the player so the two sit together for readers and for checkers.
 *
 * @param string $content Rendered block.
 * @return string
 */
function youtube_transcript_link( string $content ): string {
	if ( false === strpos( $content, 'data-youtube-id' ) || false !== strpos( $content, '<track' ) || false !== stripos( $content, 'transcript' ) ) {
		return $content;
	}
	$id = preg_match( '/data-youtube-id="([^"]+)"/', $content, $m ) ? youtube_id( $m[1] ) : '';
	if ( '' === $id ) {
		return $content;
	}
	$link = sprintf(
		'<p class="cr-media__transcript"><a href="%s">%s</a></p>',
		esc_url( 'https://www.youtube.com/watch?v=' . $id ),
		esc_html__( 'Captions and transcript on YouTube', 'courtneyr-child' )
	);
	// Inside the figure when there is one, so it is the player's next sibling.
	$pos = strripos( $content, '</figure>' );
	return false === $pos ? $content . $link : substr( $content, 0, $pos ) . $link . substr( $content, $pos );
}
add_filter( 'render_block_core/embed', __NAMESPACE__ . '\\youtube_transcript_link', 200 );

/**
 * The same for Able Player shortcodes: `[ableplayer youtube-id="…"]` renders
 * outside any block, so the core/embed filter never sees it and the Checker
 * reports the player as a video with no transcript.
 *
 * With a captions track, Able Player itself offers an interactive transcript
 * (its Transcript control), but that control is an icon button with an
 * aria-label, which the Checker's transcript check, reading visible text
 * only, cannot see. The caption tells every reader where the transcript is,
 * and the check reads a figcaption before it caps the surrounding text.
 * Without a track, the caption links to the captions and transcript on YouTube.
 *
 * @param string $output Shortcode output.
 * @param string $tag    Shortcode name.
 * @return string
 */
function ableplayer_transcript_link( $output, $tag ) {
	if ( 'ableplayer' !== $tag || ! is_string( $output ) || false !== strpos( $output, 'cr-media__transcript' ) ) {
		return $output;
	}
	$id = preg_match( '/data-youtube-id="([^"]+)"/', $output, $m ) ? youtube_id( $m[1] ) : '';
	if ( '' === $id ) {
		return $output;
	}
	$caption = false !== stripos( $output, '<track' )
		? esc_html__( 'Captions and an interactive transcript are in the player controls (Transcript button).', 'courtneyr-child' )
		: sprintf(
			'<a href="%s">%s</a>',
			esc_url( 'https://www.youtube.com/watch?v=' . $id ),
			esc_html__( 'Captions and transcript on YouTube', 'courtneyr-child' )
		);
	return sprintf(
		'<figure class="cr-media cr-media--ableplayer">%s<figcaption class="cr-media__transcript">%s</figcaption></figure>',
		$output,
		$caption
	);
}
add_filter( 'do_shortcode_tag', __NAMESPACE__ . '\\ableplayer_transcript_link', 20, 2 );

/**
 * Backfed comments (Bridgy through the Webmention plugin) arrive with the
 * author's fediverse display name verbatim, custom-emoji shortcodes included:
 * `James Huff :prami_pride:`. The shortcode is noise wherever the name is read
 * out, and on the ATmosphere reactions block it becomes the avatar's alt, where
 * the Checker reads its underscore as a filename. Drop it before the comment
 * is stored or updated.
 *
 * @param string $name Author name as submitted.
 * @return string
 */
function author_name_without_emoji_shortcodes( string $name ): string {
	$name = preg_replace( '/\s*:[A-Za-z0-9_]*[A-Za-z_][A-Za-z0-9_]*:\s*/', ' ', $name );
	return trim( preg_replace( '/\s{2,}/', ' ', (string) $name ) );
}

/**
 * Apply the strip to the comment author about to be inserted or updated.
 *
 * @param array $commentdata Comment data (preprocess_comment or wp_update_comment_data).
 * @return array
 */
function strip_emoji_shortcodes_from_author( array $commentdata ): array {
	if ( ! empty( $commentdata['comment_author'] ) && is_string( $commentdata['comment_author'] ) ) {
		$commentdata['comment_author'] = author_name_without_emoji_shortcodes( $commentdata['comment_author'] );
	}
	return $commentdata;
}
add_filter( 'preprocess_comment', __NAMESPACE__ . '\strip_emoji_shortcodes_from_author' );
add_filter( 'wp_update_comment_data', __NAMESPACE__ . '\strip_emoji_shortcodes_from_author' );

/**
 * The Newsletter plugin's `[newsletter_form]` shortcode carries its field
 * shortcodes as multi-line inner content. wpautop runs before do_shortcode and
 * wraps those lines, so the rendered form holds an orphan `</p>` after the
 * hidden language input and an unclosed `<p>` before the submit button; the
 * browser turns both into empty paragraphs (Checker: empty_paragraph_tag).
 * The form is built from divs and never needs paragraph tags, so drop them.
 *
 * @param string $output Shortcode output.
 * @param string $tag    Shortcode name.
 * @return string
 */
function newsletter_form_without_autop( $output, $tag ) {
	if ( 'newsletter_form' !== $tag || ! is_string( $output ) || false === strpos( $output, 'tnp-subscription' ) ) {
		return $output;
	}
	return (string) preg_replace( '#\s*</?p>\s*#', ' ', $output );
}
add_filter( 'do_shortcode_tag', __NAMESPACE__ . '\newsletter_form_without_autop', 20, 2 );

/**
 * The archive and Stream loops print a core Post Excerpt block for every
 * post. A note, photo or check-in without excerpt text still renders
 * `<p class="wp-block-post-excerpt__excerpt"> </p>`, which the Checker
 * reports as an empty paragraph. Drop the block when there is nothing to say.
 *
 * @param string $content Rendered block.
 * @return string
 */
function drop_empty_post_excerpt( string $content ): string {
	if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block_core/post-excerpt', __NAMESPACE__ . '\drop_empty_post_excerpt', 10 );

/**
 * Empty paragraphs that appear only at render time: wpautop leaves `<p></p>`
 * around the Able Player shortcode in classic-era posts, and backfed comments
 * end in one when the source text closed with a blank line. Nothing is lost by
 * removing an element with no content, and the Checker reports each one.
 * Excerpt-block emptiness is handled by drop_empty_post_excerpt().
 *
 * @param string $html Rendered content or comment text.
 * @return string
 */
function drop_empty_paragraphs_in_output( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, '<p' ) ) {
		return $html;
	}
	// wpautop wraps a block-level element (the Able Player figure, an embed) in a paragraph;
	// the browser closes that paragraph when the block starts and turns the leftover `</p>`
	// into an empty one. Backfed comments arrive with a doubled `</p></p>`. Remove the orphans
	// first, then any paragraph whose only content is whitespace.
	$html = (string) preg_replace( '#</(figure|div|ul|ol|blockquote|table|pre|h[1-6])>\s*</p>#i', '</$1>', $html );
	$html = (string) preg_replace( '#<p(?:\s[^>]*)?>\s*(?=<(?:figure|div|ul|ol|blockquote|table|pre|h[1-6])\b)#i', '', $html );
	$html = (string) preg_replace( '#</p>(\s*</p>)+#i', '</p>', $html );
	return (string) preg_replace( '#<p(?:\s[^>]*)?>(?:\s|&nbsp;|\x{00a0})*</p>#u', '', $html );
}
add_filter( 'the_content', __NAMESPACE__ . '\drop_empty_paragraphs_in_output', 999 );
add_filter( 'comment_text', __NAMESPACE__ . '\drop_empty_paragraphs_in_output', 999 );

/**
 * The header search block renders <form role="search"> with no name, and the
 * search and 404 templates add a second one, so the two landmarks collide.
 * Name the header form after its (visually hidden) label.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function name_header_search_landmark( string $content, array $block ): string {
	$classes = (string) ( $block['attrs']['className'] ?? '' );
	if ( false === strpos( $classes, 'site-header__search' ) ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( $content );
	if ( $tags->next_tag( 'form' ) && null === $tags->get_attribute( 'aria-label' ) ) {
		$tags->set_attribute( 'aria-label', (string) ( $block['attrs']['label'] ?? __( 'Search the site', 'courtneyr-child' ) ) );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/search', __NAMESPACE__ . '\\name_header_search_landmark', 10, 2 );

/**
 * The comment platform badge (assets/css/components.css, "Comment platform
 * badges") paints a small circular icon in the corner of a comment bubble,
 * detecting the network purely via CSS :has()/[href*="..."] matching on the
 * comment author link — Mastodon, WordPress, X, GitHub, Bluesky, or a
 * generic IndieWeb globe. It has no text alternative, so a badged comment
 * announces nothing about its source to assistive tech.
 *
 * Mirror the same URL-substring cascade here (checks applied in the same
 * order the CSS rules are written, so a later match — e.g. Bluesky —
 * overrides an earlier one exactly like the CSS "last rule wins" cascade)
 * and append a visually-hidden "via {Network}" span. Purely additive: the
 * visible badge is untouched.
 *
 * @param string    $content  Rendered block.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance, carries the commentId context.
 * @return string
 */
function comment_network_label( string $content, array $block, \WP_Block $instance ): string {
	unset( $block );
	$comment_id = (int) ( $instance->context['commentId'] ?? 0 );
	if ( 0 === $comment_id ) {
		return $content;
	}
	$url = get_comment_author_url( $comment_id );
	if ( '' === $url ) {
		return $content;
	}

	$network = __( 'IndieWeb', 'courtneyr-child' );
	if ( false !== strpos( $url, '/@' ) ) {
		$network = __( 'Mastodon', 'courtneyr-child' );
	}
	if ( false !== strpos( $url, 'wordpress.org' ) || false !== strpos( $url, '.wordpress.com' ) ) {
		$network = __( 'WordPress', 'courtneyr-child' );
	}
	if ( false !== strpos( $url, 'x.com/' ) || false !== strpos( $url, 'twitter.com/' ) ) {
		$network = __( 'X', 'courtneyr-child' );
	}
	if ( false !== strpos( $url, 'github.com' ) ) {
		$network = __( 'GitHub', 'courtneyr-child' );
	}
	if ( false !== strpos( $url, 'bsky.app' ) ) {
		$network = __( 'Bluesky', 'courtneyr-child' );
	}

	$label = '<span class="screen-reader-text"> ' . sprintf(
		/* translators: %s: social network or platform name, e.g. Mastodon. */
		esc_html__( 'via %s', 'courtneyr-child' ),
		esc_html( $network )
	) . '</span>';

	return $content . $label;
}
add_filter( 'render_block_core/comment-author-name', __NAMESPACE__ . '\\comment_network_label', 10, 3 );

/**
 * Saved copies of theme patterns keep the markup they were inserted with. The
 * home page's newsletter-reasons section still carries the "01"–"04" number
 * paragraphs (now painted by a CSS counter) and the "Every Saturday" edition
 * line as a <p>; both read as headings to checkers. Handle them at render so
 * the content needs no surgery: drop the number paragraphs, and render the
 * edition line as a <div>.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function reasons_paragraphs( string $content, array $block ): string {
	$classes = ' ' . (string) ( $block['attrs']['className'] ?? '' ) . ' ';
	if ( false !== strpos( $classes, ' cr-reasons__number ' ) ) {
		return '';
	}
	if ( false !== strpos( $classes, ' cr-reasons__edition ' ) ) {
		$content = (string) preg_replace( '/^(\s*)<p\b/', '$1<div', $content, 1 );
		return (string) preg_replace( '/<\/p>(\s*)$/', '</div>$1', $content, 1 );
	}
	return $content;
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\reasons_paragraphs', 20, 2 );

/**
 * The Stream page paginates its Query Loop with ?query-1-page=N (a non-inheriting
 * loop), so the conventional /stream/page/N/ URL renders page 1 under a page-N
 * address. Send it to the address the block actually reads.
 */
function stream_page_redirect(): void {
	if ( ! is_page( 'stream' ) ) {
		return;
	}
	$paged = (int) get_query_var( 'page' );
	if ( $paged < 2 ) {
		return;
	}
	wp_safe_redirect( add_query_arg( 'query-1-page', $paged, get_permalink() ), 301 );
	exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\\stream_page_redirect' );

/**
 * Post-format list items say what they are. The browse-all pattern renders
 * the post_format taxonomy through core/categories, so a term named "Link"
 * becomes a link whose whole text is "Link" (WCAG 2.4.4). Every item gets the
 * same visually hidden suffix so the list reads consistently.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function post_format_list_link_names( string $content, array $block ): string {
	if ( 'post_format' !== ( $block['attrs']['taxonomy'] ?? 'category' ) || false === strpos( $content, 'cat-item' ) ) {
		return $content;
	}
	return (string) preg_replace(
		'#(<li class="cat-item[^"]*"[^>]*>\\s*<a [^>]*>)([^<]+)(</a>)#',
		'$1$2<span class="screen-reader-text"> ' . esc_html__( 'posts', 'courtneyr-child' ) . '</span>$3',
		$content
	);
}
add_filter( 'render_block_core/categories', __NAMESPACE__ . '\\post_format_list_link_names', 10, 2 );
