<?php
/**
 * Stream: gallery posts as photo-booth strips.
 *
 * On /stream/ the Post Kinds stream card paints every `photo` kind the same
 * way — a featured image with a row of thumbnail buttons under it — whether
 * the post holds one picture or seven. WordPress already tells the two apart:
 * a multi-image post carries the Gallery post format, a single picture the
 * Image format. This file reads that distinction and, for Gallery posts only,
 * swaps the card's media block for a vertical strip of frames in the order
 * the gallery block lists them, capped at four with a real "+N more frames"
 * link for the rest. cr-post-kinds.css paints the strip as a booth print.
 *
 * The plugin exposes no filter around its media markup, so the card HTML is
 * spliced at two fixed anchors it always emits in this order —
 * `<div class="pk-media pk-media--stream">` and `<div class="pk-meta">`.
 * When either anchor is missing the card is returned untouched and keeps
 * the polaroid treatment, so a plugin markup change degrades to the old
 * look rather than a broken card. Nothing here runs outside the stream
 * page, and single-image photo posts never enter this code path.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamGallery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frames shown on the stream before the rest collapse into the more-link.
 * Four is the classic booth strip; the single post shows the whole gallery.
 */
const FRAME_LIMIT = 4;

/**
 * Attachment IDs of every image in the post body, in source order.
 *
 * Walks the parsed block tree and collects `core/image` blocks wherever they
 * sit (inside a `core/gallery`, a group, or loose). Falls back to the post's
 * attached images when the body carries no image blocks, and to the featured
 * image when it carries nothing at all, so a gallery-format post always
 * yields at least the picture the card would have shown anyway.
 *
 * @param \WP_Post $post Post being rendered.
 * @return int[] Attachment IDs, deduplicated, in the order they appear.
 */
function gallery_image_ids( \WP_Post $post ): array {
	$ids = array();

	$walk = static function ( array $blocks ) use ( &$walk, &$ids ): void {
		foreach ( $blocks as $block ) {
			if ( 'core/image' === ( $block['blockName'] ?? '' ) ) {
				$id = (int) ( $block['attrs']['id'] ?? 0 );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );

	if ( empty( $ids ) ) {
		foreach ( get_attached_media( 'image', $post ) as $image ) {
			$ids[] = (int) $image->ID;
		}
	}
	if ( empty( $ids ) && has_post_thumbnail( $post ) ) {
		$ids[] = (int) get_post_thumbnail_id( $post );
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * The strip: an ordered list of frames, each a responsive core-generated
 * image, followed by the booth footer (count line, first caption, more-link).
 *
 * `wp_get_attachment_image()` supplies srcset, sizes, width, height, and the
 * attachment's alt text, so the frames keep the same responsive behaviour as
 * any image WordPress prints. Only the first frame loads eagerly; the rest
 * are lazy like the thumbnails they replace.
 *
 * @param \WP_Post $post Post being rendered.
 * @param int[]    $ids  Attachment IDs in source order.
 * @return string Strip markup, or '' when no image resolves to markup.
 */
function render_strip( \WP_Post $post, array $ids ): string {
	$total   = count( $ids );
	$visible = array_slice( $ids, 0, FRAME_LIMIT );
	$frames  = '';
	$shown   = 0;

	foreach ( $visible as $i => $id ) {
		$img = wp_get_attachment_image(
			$id,
			'medium_large',
			false,
			array(
				'class'   => 'cr-booth__img u-photo',
				'sizes'   => '(max-width: 640px) 88vw, 15rem',
				'loading' => 0 === $i ? 'eager' : 'lazy',
			)
		);
		if ( '' === $img ) {
			continue;
		}
		++$shown;
		$frames .= '<li class="cr-booth__frame">' . $img . '</li>';
	}

	if ( 0 === $shown ) {
		return '';
	}

	$remaining = $total - count( $visible );
	$permalink = (string) get_permalink( $post );

	// The first frame's caption is the strip's hand-written note. WordPress
	// keeps a caption in the attachment excerpt; Outpost writes it there too.
	$note = trim( (string) get_post_field( 'post_excerpt', $ids[0] ) );

	$out  = '<div class="pk-media pk-media--stream cr-booth">';
	$out .= '<ol class="cr-booth__frames">' . $frames . '</ol>';
	$out .= '</div>';

	$out .= '<div class="cr-booth__footer">';
	if ( '' !== $note ) {
		$out .= '<p class="cr-booth__note">' . esc_html( $note ) . '</p>';
	}

	if ( $remaining > 0 ) {
		$count = sprintf(
			/* translators: 1: frames shown on the stream, 2: frames in the post. */
			__( '1–%1$d of %2$d', 'courtneyr-child' ),
			$shown,
			$total
		);
	} else {
		$count = sprintf(
			/* translators: %d: number of frames in the strip. */
			_n( '%d frame', '%d frames', $shown, 'courtneyr-child' ),
			$shown
		);
	}
	$out .= '<p class="cr-booth__count">'
		. '<span class="cr-booth__serial">' . esc_html( sprintf( '№ %d', $post->ID ) ) . '</span>'
		. ' · '
		. '<span class="cr-booth__frames-count">' . esc_html( $count ) . '</span>'
		. '</p>';

	if ( $remaining > 0 ) {
		$out .= '<p class="cr-booth__more"><a class="cr-booth__more-link" href="' . esc_url( $permalink ) . '">'
			. esc_html(
				sprintf(
					/* translators: %d: number of frames not shown on the stream. */
					_n( '+ %d more frame', '+ %d more frames', $remaining, 'courtneyr-child' ),
					$remaining
				)
			)
			. '<span class="pk-sr-only">' . esc_html__( ' on the full post', 'courtneyr-child' ) . '</span>'
			. '<span class="cr-booth__arrow" aria-hidden="true"> →</span>'
			. '</a></p>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * Replace the stream card's media block with the booth strip for gallery
 * posts on /stream/.
 *
 * @param string    $html     Rendered card HTML.
 * @param array     $block    Parsed block (unused; the instance carries context).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function booth_strip_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post instanceof \WP_Post || 'gallery' !== get_post_format( $post ) ) {
		return $html;
	}

	$media_open = '<div class="pk-media pk-media--stream">';
	$meta_open  = '<div class="pk-meta">';

	$start = strpos( $html, $media_open );
	$end   = false !== $start ? strpos( $html, $meta_open, $start ) : false;
	if ( false === $start || false === $end ) {
		return $html;
	}

	$strip = render_strip( $post, gallery_image_ids( $post ) );
	if ( '' === $strip ) {
		return $html;
	}

	$html = substr( $html, 0, $start ) . $strip . substr( $html, $end );

	// Name the object so the stylesheet can paint it without guessing from
	// image counts: pk-card--gallery beside the plugin's own pk-card--stream.
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--gallery' );
		$html = $tags->get_updated_html();
	}

	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\booth_strip_card', 10, 3 );
