<?php
/**
 * Photo single (PKIW issue 241): what the theme adds around the plugin's
 * projector.
 *
 * The plugin turns the first gallery of a photo post into a projector, or one
 * loose image into a mount, and labels the projector by the post's own
 * heading when this file names it. templates/single.html keeps its header,
 * H1 and post navigation as shipped; assets/css/cr-photo.css paints the mount,
 * the controls and the rail, and inc/photo.php loads it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SinglePhoto;

use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND       = 'photo';
const TITLE_ID   = 'cr-photo-title';
const BODY_CLASS = 'cr-photo-single';

/**
 * Photo frames: block styles on the core Image block (slug => label),
 * painted by assets/css/cr-photo-frames.css wherever an image renders.
 */
const FRAMES = array(
	'polaroid' => 'Polaroid',
	'taped'    => 'Taped print',
	'corners'  => 'Photo corners',
	'postcard' => 'Postcard',
	'film'     => 'Film frame',
);

/**
 * Whether this is the queried photo single and it has a title of its own.
 *
 * @param int $post_id Post being rendered.
 * @return bool
 */
function is_titled_photo_single( int $post_id ): bool {
	if ( ! is_singular( 'post' ) || $post_id <= 0 || get_queried_object_id() !== $post_id || ! has_term( KIND, 'kind', $post_id ) ) {
		return false;
	}
	$post = get_post( $post_id );
	return $post instanceof \WP_Post && '' !== trim( (string) $post->post_title );
}

/**
 * Give a titled photo single's H1 the id its projector is labelled by.
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function title_id( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) ) {
		return $html;
	}
	if ( ! is_titled_photo_single( (int) get_the_ID() ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'h1' ) || '' !== trim( (string) $tags->get_attribute( 'id' ) ) ) {
		return $html;
	}
	$tags->set_attribute( 'id', TITLE_ID );
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\title_id', 10, 2 );

/**
 * Label the projector by the H1 when the post has one.
 *
 * An untitled post keeps the plugin's own label, the kind name.
 *
 * @param string $id      Id the projector is labelled by so far.
 * @param int    $post_id Post ID.
 * @return string
 */
function projector_labelledby( $id, $post_id = 0 ): string {
	$id = is_string( $id ) ? $id : '';
	return is_titled_photo_single( (int) $post_id ) ? TITLE_ID : $id;
}
add_filter( 'pkiw_photo_projector_labelledby', __NAMESPACE__ . '\\projector_labelledby', 10, 2 );

/**
 * Add the single's body class.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() ) && ! in_array( BODY_CLASS, $classes, true ) ) {
		$classes[] = BODY_CLASS;
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Give the block editor's photo canvas the single's paint class.
 *
 * The kind-part registry (inc/editor-parity.php) lists every kind with its
 * canvas classes; photo adds the class cr-photo.css keys the mount on.
 *
 * @param mixed $kinds Registry keyed by kind slug.
 * @return mixed
 */
function editor_parity_kinds( $kinds ) {
	if ( ! is_array( $kinds ) || ! isset( $kinds[ KIND ] ) || ! is_array( $kinds[ KIND ] ) ) {
		return $kinds;
	}
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] ) ? $kinds[ KIND ]['classes'] : array();
	if ( ! in_array( BODY_CLASS, $classes, true ) ) {
		$classes[] = BODY_CLASS;
	}
	$kinds[ KIND ]['classes'] = $classes;
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\editor_parity_kinds' );

/**
 * The photo frames as block styles on the core Image block, with their sheet
 * loaded wherever an Image block renders.
 */
function register_frame_styles(): void {
	if ( function_exists( 'register_block_style' ) ) {
		foreach ( FRAMES as $slug => $label ) {
			register_block_style(
				'core/image',
				array(
					'name'  => 'cr-photo-' . $slug,
					'label' => __( $label, 'courtneyr-child' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Labels come from the FRAMES table.
				)
			);
		}
	}
	if ( function_exists( 'wp_enqueue_block_style' ) ) {
		wp_enqueue_block_style(
			'core/image',
			array(
				'handle' => 'courtneyr-photo-frames',
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-photo-frames.css',
				'path'   => dirname( __DIR__ ) . '/assets/css/cr-photo-frames.css',
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_frame_styles' );

/**
 * The frame picked as an image's block style, or ''.
 *
 * @param string $classes Class list.
 * @return string Frame slug, or ''.
 */
function picked_frame( string $classes ): string {
	return preg_match( '/(?<![\w-])is-style-cr-photo-([a-z]+)(?![\w-])/', $classes, $m ) && isset( FRAMES[ $m[1] ] ) ? $m[1] : '';
}

/**
 * A frame seeded by the post and the picture's place on the page.
 *
 * @param int $post_id  Post ID.
 * @param int $position Picture's place, 0 for a single image.
 * @return string Frame slug.
 */
function seeded_frame( int $post_id, int $position = 0 ): string {
	$keys = array_keys( FRAMES );
	return $keys[ ( pick( seed( 'photo-frame', (string) $post_id ), 3, count( $keys ) ) + $position ) % count( $keys ) ];
}

/**
 * The queried, unprotected photo single this block belongs to, or 0.
 *
 * @param mixed $instance Block instance.
 * @return int Post ID, or 0.
 */
function photo_single_id( $instance ): int {
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	if ( ! is_singular( 'post' ) || $post_id <= 0 || get_queried_object_id() !== $post_id || ! has_term( KIND, 'kind', $post_id ) || post_password_required( $post_id ) ) {
		return 0;
	}
	return $post_id;
}

/**
 * A single-image photo post prints its picture in a frame (Courtney,
 * 2026-10-19): the plugin's mount (priority 10) becomes the picked frame,
 * or one seeded by the post.
 *
 * @param string    $html     Rendered image (the plugin's mount).
 * @param array     $block    Parsed core/image block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function framed_print( string $html, array $block, $instance = null ): string {
	if ( false === strpos( $html, 'pkiw-projector__mount' ) ) {
		return $html;
	}
	$post_id = photo_single_id( $instance );
	if ( 0 === $post_id ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'figure' ) ) {
		return $html;
	}
	$tags->remove_class( 'pkiw-projector__mount' );
	if ( '' === picked_frame( (string) $tags->get_attribute( 'class' ) ) ) {
		$tags->add_class( 'is-style-cr-photo-' . seeded_frame( $post_id ) );
	}
	if ( function_exists( 'wp_dequeue_style' ) ) {
		wp_dequeue_style( 'pkiw-photo-projector' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/image', __NAMESPACE__ . '\\framed_print', 11, 3 );

/**
 * A gallery photo single as a scrapbook page (Courtney, 2026-10-19).
 *
 * The plugin turns the first gallery of a photo post into a projector (one
 * slide at a time, buttons and a rail). On the single the theme lays every
 * picture out on a scrapbook page instead: each of the gallery's own image
 * blocks is rendered by core in a photo frame (its picked frame, or a mix
 * seeded by the post) with core's "expand on click" lightbox (the
 * Interactivity API, no theme script), keeps its caption and its u-photo
 * class, and is sized for the print; the gallery caption becomes a note on
 * the page. cr-photo.css lays out the page. Runs after the projector
 * (priority 10) and only where it ran.
 *
 * @param string    $html     Rendered gallery (the projector).
 * @param array     $block    Parsed core/gallery block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function scrapbook( string $html, array $block, $instance = null ): string {
	if ( false === strpos( $html, 'pkiw-projector' ) ) {
		return $html;
	}
	$post_id = photo_single_id( $instance );
	if ( 0 === $post_id ) {
		return $html;
	}

	$prints = '';
	$count  = 0;
	foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $image ) {
		if ( 'core/image' !== ( $image['blockName'] ?? '' ) ) {
			continue;
		}
		$image['attrs']             = (array) ( $image['attrs'] ?? array() );
		$image['attrs']['lightbox'] = array( 'enabled' => true );
		$figure                     = render_block( $image );
		// The Image block is static (core prints its saved markup), so the frame goes on the rendered figure.
		$tags = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'figure' ) && '' === picked_frame( (string) $tags->get_attribute( 'class' ) ) ) {
			$tags->add_class( 'is-style-cr-photo-' . seeded_frame( $post_id, $count ) );
		}
		if ( $tags->next_tag( 'img' ) ) {
			$tags->add_class( 'u-photo' );
			$tags->set_attribute( 'sizes', '(max-width: 40rem) 90vw, 32rem' );
			$figure = $tags->get_updated_html();
		}
		$prints .= '<li class="cr-scrapbook__print">' . $figure . '</li>';
		++$count;
	}
	if ( 0 === $count ) {
		return $html;
	}

	// The gallery caption the projector carried over becomes a note on the page.
	$note = preg_match( '/<p class="pkiw-projector__note[^"]*">(.*?)<\/p>/s', $html, $m ) ? '<p class="cr-scrapbook__note">' . $m[1] . '</p>' : '';

	// The projector's own script and styles have nothing left to drive.
	if ( function_exists( 'wp_dequeue_script' ) ) {
		wp_dequeue_script( 'pkiw-photo-projector' );
		wp_dequeue_style( 'pkiw-photo-projector' );
	}

	$label = is_titled_photo_single( $post_id )
		? ' aria-labelledby="' . TITLE_ID . '"'
		: ' aria-label="' . esc_attr( __( 'Photos', 'courtneyr-child' ) ) . '"';
	return '<div class="cr-scrapbook"><ul class="cr-scrapbook__photos" role="list"' . $label . '>' . $prints . '</ul>' . $note . '</div>';
}
add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\scrapbook', 11, 3 );
