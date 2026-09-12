<?php
/**
 * Aside format: a margin scrap, not a sticky note.
 *
 * Post Kind Note is a first-class short thought and stays the sticky note
 * (inc/sticky-note.php). Post Format Aside is a "by the way": a correction,
 * a small FYI, a follow-up, housekeeping. On this site the Post Formats
 * plugin maps the Note kind to the Aside format and back on save, so a
 * post carrying both is the ordinary case; the format is the more
 * specific signal and wins the presentation while the Note kind stays in
 * the data. Kinds with an object of their own (like, reply, repost,
 * favorite, rsvp, bookmark, check-in…) keep their own artifact even when
 * their format is aside; only a Note, an Article, or a kind-less post
 * becomes a scrap.
 *
 * /stream: the plugin's generic card becomes a narrow scrap of grid paper
 * with a side rule and a torn outer edge, typeset, with the format as its
 * visible label. Single: the post content becomes a larger margin note
 * with a handwritten label and the date on the paper. A title that is
 * empty or only repeats the body stays in the DOM for assistive
 * technology and never prints twice. cr-post-kinds.css paints it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\AsideScrap;

use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;
use function Courtneyr\Child\StickyNote\plain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paper stocks: pale periwinkle, pale sky, off-white; all ≥ 12:1 against
 * Russian violet ink.
 */
const PAPERS = array( '#efeaf9', '#e6f2f8', '#f3f1eb' );

/**
 * Tilts a scrap may take, chosen by seed. Smaller than the sticky's.
 */
const TILTS = array( '-0.6deg', '0.45deg', '-0.35deg' );

/**
 * Kinds whose posts become a scrap when the format is aside. Any other
 * kind carries an object of its own and keeps its artifact.
 */
const TEXT_KINDS = array( '', 'note', 'article' );

/**
 * Does the Aside presentation apply to this post?
 *
 * Aside format, and a text kind (note, article) or no kind at all.
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function applies( \WP_Post $post ): bool {
	if ( 'aside' !== get_post_format( $post ) ) {
		return false;
	}
	$terms = get_the_terms( $post, 'kind' );
	$kind  = ( $terms && ! is_wp_error( $terms ) ) ? (string) $terms[0]->slug : '';
	return in_array( $kind, TEXT_KINDS, true );
}

/**
 * Is this the single view of an Aside?
 *
 * @return bool
 */
function is_aside_single(): bool {
	if ( ! \is_singular( 'post' ) ) {
		return false;
	}
	$post = get_post( \get_queried_object_id() );
	return $post instanceof \WP_Post && applies( $post );
}

/**
 * The artifact's inline custom properties: paper stock and tilt, fixed per
 * post.
 *
 * @param int $seed Stable seed.
 * @return string A style attribute.
 */
function scrap_style( int $seed ): string {
	return 'style="--cr-scrap-paper:' . PAPERS[ pick( $seed, 4, count( PAPERS ) ) ] . ';--cr-scrap-tilt:' . TILTS[ pick( $seed, 6, count( TILTS ) ) ] . '"';
}

/**
 * What the stored title is worth: 'none' (empty), 'dup' (it only repeats
 * the opening of the body, or the body repeats it), or 'real'.
 *
 * Nothing is inferred from length or wording of the body; only whether
 * the title and the body say the same thing.
 *
 * @param \WP_Post $post Post.
 * @return string
 */
function title_state( \WP_Post $post ): string {
	$title = plain( (string) get_the_title( $post ) );
	if ( '' === $title ) {
		return 'none';
	}
	$body  = plain( strip_shortcodes( (string) $post->post_content ) );
	$title = rtrim( $title, " .\u{2026}" );
	if ( '' === $body || '' === $title ) {
		return 'real';
	}
	if ( str_starts_with( $body, $title ) || str_starts_with( $title, $body ) ) {
		return 'dup';
	}
	return 'real';
}

/**
 * /stream: the plugin's generic card becomes the scrap.
 *
 * The visible kind label reads "Aside" (the format; the kind stays in the
 * data), the plugin's synthetic title for an untitled post says the same,
 * and the card is wrapped in the artifact that carries the stock and the
 * tilt. cr-post-kinds.css hides an empty or repeated title from view.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed block (unused).
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function stream_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) || ! preg_match( '/pk-card--stream k-(?:note|article)\b/', $html ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! applies( $post ) ) {
		return $html;
	}
	$state = title_state( $post );
	$label = esc_html__( 'Aside', 'courtneyr-child' );
	$html  = (string) preg_replace( '/(<p class="pk-kindlabel">)[^<]*(<\/p>)/', '${1}' . $label . '${2}', $html, 1 );
	if ( 'none' === $state ) {
		$html = (string) preg_replace( '/(<h2 class="pk-title"><a href="[^"]*">)[^<]*(<\/a><\/h2>)/', '${1}' . $label . '${2}', $html, 1 );
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'cr-scrap' );
		$tags->add_class( 'cr-scrap--stream' );
		$tags->add_class( 'cr-scrap--' . $state );
		$html = $tags->get_updated_html();
	}
	return '<div class="cr-scrap-artifact cr-scrap-artifact--stream" ' . scrap_style( seed( (string) $post->ID, $post->post_title ) ) . '>' . $html . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card', 10, 3 );

/**
 * Single: a real title moves from the header onto the scrap.
 *
 * The theme hides the header's H1 on titleless formats; an Aside with a
 * title of its own shows it on the paper instead. An empty or repeated
 * title stays where it is, visually hidden, so the page keeps its
 * heading for assistive technology.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function hold_title( string $html, array $block ): string {
	global $courtneyr_aside_title;
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_aside_single() ) {
		return $html;
	}
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__title' ) || '' !== (string) $courtneyr_aside_title ) {
		return $html;
	}
	$post = get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post || 'real' !== title_state( $post ) ) {
		return $html;
	}
	$courtneyr_aside_title = $html;
	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\hold_title', 20, 2 );

/**
 * Single: the post content becomes the margin note.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function single_scrap( string $html, array $block ): string {
	global $courtneyr_aside_title;
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_aside_single() ) {
		return $html;
	}
	$post  = \get_post();
	$open  = strpos( $html, '>' );
	$close = strrpos( $html, '</div>' );
	if ( ! $post instanceof \WP_Post || false === $open || false === $close || $close <= $open ) {
		if ( '' !== (string) $courtneyr_aside_title ) {
			$html                  = $courtneyr_aside_title . $html;
			$courtneyr_aside_title = '';
		}
		return $html;
	}
	$ts    = (int) get_post_time( 'U', true, $post );
	$inner = substr( $html, $open + 1, $close - $open - 1 );
	// The theme places the format avatar first in the content of a titleless
	// post and positions it beside the column; it stays a direct child of the
	// block, outside the paper, so that rule keeps applying.
	$avatar = '';
	if ( preg_match( '/<span class="[^"]*single-post__format-avatar[^"]*"[^>]*>.*?<\/span>/s', $inner, $am ) ) {
		$avatar = $am[0];
		$inner  = str_replace( $am[0], '', $inner );
	}
	$paper = $avatar . '<div class="cr-scrap-artifact cr-scrap-artifact--single" ' . scrap_style( seed( (string) $post->ID, $post->post_title ) ) . '>'
		. '<div class="cr-scrap cr-scrap--single">'
		. '<p class="cr-scrap__label">' . esc_html__( 'Aside', 'courtneyr-child' ) . '</p>'
		. (string) $courtneyr_aside_title
		. '<div class="cr-scrap__body">' . $inner . '</div>'
		. '<p class="cr-scrap__date"><time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '</time></p>'
		. '</div></div>';
	$courtneyr_aside_title = '';
	return substr( $html, 0, $open + 1 ) . $paper . substr( $html, $close );
}
add_filter( 'render_block', __NAMESPACE__ . '\\single_scrap', 20, 2 );
