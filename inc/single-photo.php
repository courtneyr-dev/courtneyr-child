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
 * Tilt keys a scrapbook print may take; cr-photo.css maps 0 to 3 to the
 * four rotation tokens.
 */
const PRINT_TILTS = 4;

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
 * A gallery photo single as a scrapbook spread (Courtney, 2026-10-19).
 *
 * The plugin turns the first gallery of a photo post into a projector (one
 * slide at a time, buttons and a rail). On the single the theme lays every
 * picture out at once instead: each of the gallery's own image blocks is
 * rendered by core with its "expand on click" lightbox (the Interactivity
 * API, no theme script), keeps its caption and its u-photo class, and
 * becomes a loose print with a seeded tilt; cr-photo.css adds the paper and
 * tape. Runs after the projector (priority 10) and only where it ran.
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
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	if ( ! is_singular( 'post' ) || $post_id <= 0 || get_queried_object_id() !== $post_id || ! has_term( KIND, 'kind', $post_id ) || post_password_required( $post_id ) ) {
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
		$tags                       = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->add_class( 'u-photo' );
			$figure = $tags->get_updated_html();
		}
		$tilt    = pick( seed( 'scrapbook', (string) $post_id, (string) $count ), 3, PRINT_TILTS );
		$prints .= '<li class="cr-scrapbook__print cr-scrapbook__print--tilt-' . $tilt . '">' . $figure . '</li>';
		++$count;
	}
	if ( 0 === $count ) {
		return $html;
	}

	// The projector's own script and styles have nothing left to drive.
	if ( function_exists( 'wp_dequeue_script' ) ) {
		wp_dequeue_script( 'pkiw-photo-projector' );
		wp_dequeue_style( 'pkiw-photo-projector' );
	}

	$label = is_titled_photo_single( $post_id )
		? ' aria-labelledby="' . TITLE_ID . '"'
		: ' aria-label="' . esc_attr( __( 'Photos', 'courtneyr-child' ) ) . '"';
	return '<ul class="cr-scrapbook" role="list"' . $label . '>' . $prints . '</ul>';
}
add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\scrapbook', 11, 3 );
