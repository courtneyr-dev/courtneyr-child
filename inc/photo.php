<?php
/**
 * Photo contact sheets (PKIW issue 241): the archive's month strips.
 *
 * The plugin owns every photo fact: the lead image, the title it is safe to
 * print, the date and the grouping into month sheets. This file reads them
 * through \PKIW\kind_facts() only and decides what a frame shows:
 *
 *   Archive  one frame per post on a photocopier-black film strip: the lead
 *            image at 3:2, then its number and title. The number comes from
 *            a CSS counter that restarts on every sheet. No date, caption,
 *            place, arrow or kind label. A post with no image, or one whose
 *            image fails to load, stands as a typographic frame on a palette
 *            paper.
 *
 * Frames are built from the facts and keep the plugin's article root (the
 * pk-card and h-entry classes in their order), so the plugin's own filter
 * adds the hidden published date, permalink and author. assets/css/cr-photo.css
 * paints the strips, the frames, the single and the Stream slide mount.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Photo;

use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'photo';
const STYLE_HANDLE = 'courtneyr-photo';
const FRAME_STYLE  = 'is-style-cr-photo-frame';

/**
 * Frames at the top of a page that load eagerly; the first also gets
 * fetchpriority high.
 */
const EAGER_FRAMES = 4;

/**
 * Palette papers for typographic frames, picked once per post.
 */
const PAPERS = array( 'sky-blue', 'periwinkle', 'light-orange' );

/**
 * Rendered widths: 4 frames across from 64rem, 3 from 40rem, 2 below.
 */
const SIZES = '(min-width: 64rem) 22vw, (min-width: 40rem) 30vw, 46vw';

/** Whether this request is the photo kind archive. */
function is_photo_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single photo post. */
function is_photo_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Photo facts from the plugin's render-time reader.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	return function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post_id ) : array();
}

/**
 * Name the frame style, so the editor lists it for the stream card.
 *
 * The style travels with the block, so the Site Editor dresses a frame the
 * way the archive does.
 *
 * @return void
 */
function register_frame_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-photo-frame',
				'label' => __( 'Photo frame', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_frame_style' );

/** Enqueue the photo sheet where photo markup can appear. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_photo_archive() && ! is_photo_single() && ! is_stream_surface() ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-photo.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Add the archive body class.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_photo_archive() && ! in_array( 'cr-photo-archive', $classes, true ) ) {
		$classes[] = 'cr-photo-archive';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Per-request count of frames already printed.
 *
 * @return int
 */
function &frame_count(): int {
	static $count = 0;
	return $count;
}

/** Reset the frame count between renders and tests. */
function reset_page_state(): void {
	$count = &frame_count();
	$count = 0;
}

/**
 * The palette paper class for a post, the same on every surface.
 *
 * @param int $post_id Post ID.
 * @return string A cr-paper class, or '' when stamps are unavailable.
 */
function paper_class( int $post_id ): string {
	if ( ! function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		return '';
	}
	$paper = \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
	return '' !== $paper ? 'cr-paper--' . $paper : '';
}

/**
 * Add classes to the first tag of a fragment and return the whole fragment.
 *
 * @param string   $html    Fragment that opens with the tag.
 * @param string[] $classes Classes to add.
 * @return string
 */
function add_root_classes( string $html, array $classes ): string {
	if ( ! preg_match( '/^\s*<article\b[^>]*>/i', $html, $open ) ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $open[0] );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	foreach ( $classes as $class_name ) {
		$tags->add_class( $class_name );
	}
	return $tags->get_updated_html() . substr( $html, strlen( $open[0] ) );
}

/**
 * Whether HTML carries a complete class token.
 *
 * @param string $html       Fragment.
 * @param string $class_name Class token.
 * @return bool
 */
function has_class_token( string $html, string $class_name ): bool {
	return (bool) preg_match( '/\bclass="[^"]*(?<![\w-])' . preg_quote( $class_name, '/' ) . '(?![\w-])[^"]*"/', $html );
}

/**
 * The frame's image: a medium sub-size with srcset and sizes, never full.
 *
 * The first four frames of a page load eagerly. The first is fetched at high
 * priority, and the next three carry skip-lazy, because Perfmatters leaves
 * an image whose tag names either alone.
 *
 * @param array<string, mixed> $lead     Lead image facts.
 * @param bool                 $titled   Whether a title names the link.
 * @param int                  $position Frame position on the page, from 1.
 * @return string Image markup, or '' when no image prints.
 */
function frame_image( array $lead, bool $titled, int $position ): string {
	$id = (int) ( $lead['attachment_id'] ?? 0 );
	if ( $id <= 0 || '' === (string) ( $lead['source'] ?? '' ) ) {
		return '';
	}

	$attrs = array(
		'class'    => 'u-photo',
		'alt'      => $titled ? '' : (string) ( $lead['alt'] ?? '' ),
		'sizes'    => SIZES,
		'decoding' => 'async',
		'loading'  => $position <= EAGER_FRAMES ? 'eager' : 'lazy',
	);
	if ( 1 === $position ) {
		$attrs['fetchpriority'] = 'high';
	} elseif ( $position <= EAGER_FRAMES ) {
		$attrs['class'] .= ' skip-lazy';
	}

	return (string) wp_get_attachment_image( $id, 'medium', false, $attrs );
}

/**
 * Dress a protected photo card as a typographic frame.
 *
 * The plugin's protected card is a title link and nothing else; it keeps
 * its heading byte for byte, gains the frame classes and its number.
 *
 * @param string $html    Plugin card.
 * @param int    $post_id Post ID.
 * @return string
 */
function dress_protected( string $html, int $post_id ): string {
	$html = add_root_classes( $html, array( 'cr-photo-frame', 'cr-photo-frame--protected', 'cr-photo-frame--paper', paper_class( $post_id ) ) );
	return (string) preg_replace( '/^(\s*<article\b[^>]*>)/i', '$1<span class="cr-photo-frame__number" aria-hidden="true"></span>', $html, 1 );
}

/**
 * Dress a photo post's stream card as a contact-sheet frame.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance with the Query Loop's postId.
 * @return string
 */
function frame_card( string $html, array $block, $instance = null ): string {
	if ( '' === $html || ! preg_match( '/^\s*<article\b[^>]*>/i', $html, $open ) ) {
		return $html;
	}

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! is_photo_archive() && false === strpos( $class_name, FRAME_STYLE ) ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) ) {
		return $html;
	}

	if ( post_password_required( $post ) || has_class_token( $html, 'pk-card--protected' ) ) {
		return dress_protected( $html, $post->ID );
	}

	$facts = facts( $post->ID );
	if ( array() === $facts ) {
		return $html;
	}

	$count = &frame_count();
	++$count;

	$level     = max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 3 ) ) );
	$permalink = esc_url( (string) get_permalink( $post ) );
	$titled    = ! empty( $facts['has_title'] ) && '' !== trim( (string) ( $facts['title'] ?? '' ) );
	$name      = trim( (string) ( $facts['name'] ?? '' ) );
	$name      = '' !== $name ? $name : __( 'Photo', 'courtneyr-child' );
	$lead      = is_array( $facts['lead'] ?? null ) ? $facts['lead'] : array();
	$image     = frame_image( $lead, $titled, $count );
	$paper     = paper_class( $post->ID );

	// Titled: a title names the link, so the media is decoration. Untitled:
	// the image names the link; the sleeve only speaks when it replaces a
	// missing or failed image, or when an empty alt leaves the link unnamed.
	$alt_names_link = ! $titled && '' !== $image && '' !== trim( (string) ( $lead['alt'] ?? '' ) );
	$sleeve_hidden  = $titled || ( ! $titled && '' !== $image && ! $alt_names_link );
	$sleeve         = '<span class="cr-photo-frame__sleeve' . ( '' !== $paper ? ' ' . esc_attr( $paper ) : '' ) . '"' . ( $sleeve_hidden ? ' aria-hidden="true"' : '' ) . '><span class="cr-photo-frame__sleeve-text">' . esc_html( $name ) . '</span></span>';
	$media          = '<span class="cr-photo-frame__media' . ( '' === $image ? ' cr-photo-frame__media--paper' : '' ) . '"' . ( $titled ? ' aria-hidden="true"' : '' ) . ' data-cr-cover-fallback>' . $image . $sleeve . '</span>';
	$number         = '<span class="cr-photo-frame__number" aria-hidden="true"></span>';

	if ( $titled ) {
		$inner = $media
			. '<div class="cr-photo-frame__label">' . $number
			. '<h' . $level . ' class="cr-photo-frame__title pk-title p-name"><a class="u-url" href="' . $permalink . '">' . esc_html( (string) $facts['title'] ) . '</a></h' . $level . '>'
			. '</div>';
	} else {
		$screen = ( '' !== $image && ! $alt_names_link ) ? '<span class="cr-photo-frame__sr">' . esc_html( $name ) . '</span>' : '';
		$inner  = '<h' . $level . ' class="cr-photo-frame__title cr-photo-frame__title--object pk-title"><a class="u-url" href="' . $permalink . '">' . $media . $screen . '</a></h' . $level . '>'
			. '<div class="cr-photo-frame__label">' . $number . '</div>';
	}

	$root = add_root_classes( $open[0] . '</article>', array( 'cr-photo-frame', $titled ? 'cr-photo-frame--titled' : 'cr-photo-frame--untitled' ) );
	return substr( $root, 0, -strlen( '</article>' ) ) . $inner . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\frame_card', 10, 3 );
