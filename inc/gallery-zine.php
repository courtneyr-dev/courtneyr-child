<?php
/**
 * Gallery-format single as a zine photo essay (Courtney, 2026-10-19).
 *
 * The post's first gallery prints as one numbered sequence of panels: every
 * image block is rendered by core (its alt, caption, srcset, link and, when
 * unlinked, core's "expand on click" lightbox), sized by its real
 * orientation, and laid on the zine grid by assets/css/cr-gallery-zine.css.
 * The page H1 moves into a masthead at the head of the post content with the
 * post's own date, kind and categories and a postmark of the same date and
 * the frame count. Nothing is invented: no caption, place or credit the post
 * does not carry. Photo posts whose gallery the plugin turns into a projector
 * (priority 10) get the zine too, so the projector's assets are dequeued.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\GalleryZine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TITLE_ID   = 'cr-zine-title';
const BODY_CLASS = 'cr-zine-single';

/** A gallery caption up to this many characters is handwritten; longer ones stay typeset. */
const HAND_LIMIT = 60;

/**
 * Image sizes per panel: the featured first panel, then the rest, by shape.
 * Desktop widths follow the 12-column grid in a 1100px wide column.
 */
const SIZES = array(
	'featured' => array(
		'landscape' => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 52rem',
		'panorama'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 69rem',
		'square'    => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 40rem',
		'portrait'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 66vw, 34rem',
	),
	'panel'    => array(
		'landscape' => '(max-width: 40rem) 100vw, (max-width: 64rem) 66vw, 34rem',
		'panorama'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 46rem',
		'square'    => '(max-width: 40rem) 100vw, (max-width: 64rem) 50vw, 23rem',
		'portrait'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 50vw, 23rem',
	),
);

/**
 * Column spans per shape on the 12-column desktop grid and the 6-column
 * tablet grid: what a panel prefers, the least it takes when a row squeezes,
 * and what it takes alone on a row. The featured first panel prefers more
 * and gives up less (fmin).
 */
const SPANS = array(
	12 => array(
		'featured' => array(
			'panorama'  => 12,
			'landscape' => 9,
			'square'    => 7,
			'portrait'  => 6,
		),
		'pref'     => array(
			'panorama'  => 8,
			'landscape' => 6,
			'square'    => 4,
			'portrait'  => 4,
		),
		'min'      => array(
			'panorama'  => 8,
			'landscape' => 5,
			'square'    => 4,
			'portrait'  => 3,
		),
		'fmin'     => array(
			'panorama'  => 12,
			'landscape' => 8,
			'square'    => 6,
			'portrait'  => 5,
		),
		'lone'     => array(
			'panorama'  => 12,
			'landscape' => 8,
			'square'    => 6,
			'portrait'  => 5,
		),
	),
	6  => array(
		'featured' => array(
			'panorama'  => 6,
			'landscape' => 6,
			'square'    => 4,
			'portrait'  => 4,
		),
		'pref'     => array(
			'panorama'  => 6,
			'landscape' => 4,
			'square'    => 3,
			'portrait'  => 3,
		),
		'min'      => array(
			'panorama'  => 6,
			'landscape' => 3,
			'square'    => 3,
			'portrait'  => 2,
		),
		'fmin'     => array(
			'panorama'  => 6,
			'landscape' => 4,
			'square'    => 3,
			'portrait'  => 3,
		),
		'lone'     => array(
			'panorama'  => 6,
			'landscape' => 6,
			'square'    => 4,
			'portrait'  => 4,
		),
	),
);

/**
 * How each panel after the anchor is presented, in a fixed rhythm: mostly
 * bare photographs, with an occasional narrow-edged print, contact-sheet
 * panel or taped print, so no device repeats on every image.
 */
const TREATMENTS = array( 'bare', 'print', 'bare', 'contact', 'bare', 'print', 'taped' );

/** A caption up to this many characters is handwritten on a print's edge. */
const HAND_CAPTION_LIMIT = 40;

/**
 * Each panel's presentation classes from its place in the packed rows.
 *
 * The first panel is the anchor (bare, on a spot-colour plate). The second
 * panel of the anchor's row, and of every third shared row after it, laps
 * over its neighbour's edge; one panel in each other shared row drops below
 * the row line, at alternate ends. A short real caption on a print or taped
 * print is handwritten. Every eighth panel from the fourth gets a plate. A
 * panorama is never the tilted, taped print: it lies flat as a print.
 *
 * @param array<int, array{span: int, start: int, row: int}> $cells    Desktop cells from layout().
 * @param string[]                                           $captions Each panel's caption text ('' for none).
 * @param string[]                                           $shapes   Each panel's shape.
 * @return array<int, string[]> Class suffixes per panel.
 */
function dressing( array $cells, array $captions, array $shapes = array() ): array {
	$rows = array();
	foreach ( $cells as $n => $cell ) {
		$rows[ $cell['row'] ][] = $n;
	}
	$out = array();
	foreach ( $cells as $n => $cell ) {
		$classes = array( 0 === $n ? 'anchor' : TREATMENTS[ ( $n - 1 ) % count( TREATMENTS ) ] );
		if ( 'taped' === $classes[0] && 'panorama' === ( $shapes[ $n ] ?? '' ) ) {
			$classes[0] = 'print';
		}
		$row = $rows[ $cell['row'] ];
		$pos = (int) array_search( $n, $row, true );
		if ( count( $row ) > 1 && 0 !== $n ) {
			if ( 1 === $pos && 0 === $cell['row'] % 3 ) {
				$classes[] = 'lap';
			} elseif ( ( 0 === $cell['row'] % 2 ? count( $row ) - 1 : 0 ) === $pos ) {
				$classes[] = 'drop';
			}
		}
		$caption = (string) ( $captions[ $n ] ?? '' );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $caption ) : strlen( $caption );
		if ( in_array( $classes[0], array( 'print', 'taped' ), true ) && '' !== $caption && $length <= HAND_CAPTION_LIMIT ) {
			$classes[] = 'hand';
		}
		if ( 0 === $n || 3 === $n % 8 ) {
			$classes[] = 'plate';
		}
		$out[ $n ] = $classes;
	}
	return $out;
}

/** Which panels take a row's spare columns first. */
const GROW_ORDER = array( 'landscape', 'panorama', 'square', 'portrait' );

/**
 * Pack panels into full rows, deterministically, for any count.
 *
 * Panels fill a row at their preferred spans; when the next one does not
 * fit, the row squeezes to its least spans if that makes room, else the row
 * closes. A full row's spare columns go to its landscapes first, so no row
 * leaves a hole. A panel alone on a row keeps its lone span and sits centred
 * when it is the only row, else at alternate ends of the page.
 *
 * @param string[] $shapes Panel shapes in order.
 * @param int      $cols   12 (desktop) or 6 (tablet).
 * @return array<int, array{span: int, start: int, row: int}> Start 0 means "after the previous panel".
 */
function layout( array $shapes, int $cols ): array {
	$table = SPANS[ $cols ];
	$rows  = array();
	$row   = array();
	foreach ( array_values( $shapes ) as $i => $shape ) {
		$item = array(
			'i'     => $i,
			'shape' => $shape,
			'pref'  => $table[ 0 === $i ? 'featured' : 'pref' ][ $shape ],
			'min'   => $table[ 0 === $i ? 'fmin' : 'min' ][ $shape ],
		);
		if ( $row && array_sum( array_column( $row, 'pref' ) ) + $item['pref'] > $cols && array_sum( array_column( $row, 'min' ) ) + $item['min'] > $cols ) {
			$rows[] = $row;
			$row    = array();
		}
		$row[] = $item;
	}
	if ( $row ) {
		$rows[] = $row;
	}

	$out = array();
	foreach ( $rows as $r => $row ) {
		if ( 1 === count( $row ) ) {
			$span = $table['lone'][ $row[0]['shape'] ];
			if ( 1 === count( $rows ) ) {
				$start = intdiv( $cols - $span, 2 ) + 1;
			} else {
				$start = 1 === $r % 2 ? $cols - $span + 1 : 1;
			}
			$out[ $row[0]['i'] ] = array(
				'span'  => $span,
				'start' => $span >= $cols ? 0 : $start,
				'row'   => $r,
			);
			continue;
		}
		$spans = array_column( $row, 'pref' );
		// Squeeze: take a column from whichever panel sits furthest above its least span.
		while ( array_sum( $spans ) > $cols ) {
			$best = 0;
			foreach ( $spans as $k => $span ) {
				if ( $span - $row[ $k ]['min'] > $spans[ $best ] - $row[ $best ]['min'] ) {
					$best = $k;
				}
			}
			--$spans[ $best ];
		}
		// Grow: hand spare columns round the panels, landscapes first.
		$order = array_keys( $row );
		$rank  = static fn( int $k ): int => (int) array_search( $row[ $k ]['shape'], GROW_ORDER, true ) * 100 + $k;
		usort( $order, static fn( int $a, int $b ): int => $rank( $a ) <=> $rank( $b ) );
		$spare = $cols - array_sum( $spans );
		for ( $k = 0; $k < $spare; ++$k ) {
			++$spans[ $order[ $k % count( $order ) ] ];
		}
		foreach ( $row as $k => $item ) {
			$out[ $item['i'] ] = array(
				'span'  => $spans[ $k ],
				'start' => 0,
				'row'   => $r,
			);
		}
	}
	ksort( $out );
	return $out;
}

/**
 * A panel's grid-column value.
 *
 * @param array{span: int, start: int, row: int} $cell Cell from layout().
 * @return string
 */
function grid_column( array $cell ): string {
	return ( $cell['start'] > 0 ? $cell['start'] . ' / ' : '' ) . 'span ' . $cell['span'];
}

/**
 * Per-request state: whether the zine printed in this content pass, core's
 * render of the zine gallery, whether the H1 filter is being asked on the
 * masthead's behalf, and each post's gallery.
 *
 * @param bool $reset Clear the state.
 * @return array{done: bool, core: string, asking: bool, galleries: array<int, array|null>}
 */
function &state( bool $reset = false ): array {
	static $state = array();
	if ( $reset || array() === $state ) {
		$state = array(
			'done'      => false,
			'core'      => '',
			'asking'    => false,
			'galleries' => array(),
		);
	}
	return $state;
}

/** Clear the per-request state (tests). */
function reset_state(): void {
	state( true );
}

/**
 * The image blocks directly inside a gallery block.
 *
 * @param array<string, mixed> $gallery Parsed core/gallery block.
 * @return array<int, array<string, mixed>>
 */
function images_of( array $gallery ): array {
	return array_values(
		array_filter(
			(array) ( $gallery['innerBlocks'] ?? array() ),
			static fn( $inner ): bool => is_array( $inner ) && 'core/image' === ( $inner['blockName'] ?? '' )
		)
	);
}

/**
 * The first gallery, at any depth, with at least $min images.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @param int                              $min    Fewest images.
 * @return array<string, mixed>|null
 */
function first_gallery( array $blocks, int $min ): ?array {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( 'core/gallery' === ( $block['blockName'] ?? '' ) && count( images_of( $block ) ) >= $min ) {
			return $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = first_gallery( (array) $block['innerBlocks'], $min );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

/**
 * The gallery this post prints as a zine, or null.
 *
 * A photo post (any format): the first gallery the plugin's projector takes
 * (two or more images). Otherwise, on a gallery-format post: its first
 * gallery with an image.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function gallery_for( \WP_Post $post ): ?array {
	$state = &state();
	if ( ! array_key_exists( $post->ID, $state['galleries'] ) ) {
		$blocks  = parse_blocks( (string) $post->post_content );
		$gallery = null;
		// A photo post's zine is the gallery the plugin's projector takes (two or more images), when it has one.
		if ( has_term( 'photo', 'kind', $post ) ) {
			$gallery = first_gallery( $blocks, 2 );
		}
		if ( null === $gallery && has_post_format( 'gallery', $post ) ) {
			$gallery = first_gallery( $blocks, 1 );
		}
		$state['galleries'][ $post->ID ] = $gallery;
	}
	return $state['galleries'][ $post->ID ];
}

/**
 * The queried, unprotected single post that prints a zine, or null.
 *
 * @param int $post_id Post being rendered, 0 for the queried post.
 * @return \WP_Post|null
 */
function zine_post( int $post_id = 0 ): ?\WP_Post {
	$queried = (int) get_queried_object_id();
	if ( 0 === $post_id ) {
		$post_id = $queried;
	}
	if ( ! is_singular( 'post' ) || $post_id <= 0 || $queried !== $post_id || post_password_required( $post_id ) ) {
		return null;
	}
	$post = get_post( $post_id );
	return $post instanceof \WP_Post && null !== gallery_for( $post ) ? $post : null;
}

/**
 * Whether a parsed gallery is the post's zine gallery.
 *
 * @param array<string, mixed> $block   Gallery being rendered.
 * @param array<string, mixed> $gallery The post's zine gallery.
 * @return bool
 */
function same_gallery( array $block, array $gallery ): bool {
	// Core's gallery adds a data-id attr to each image before render, so sign on the attachment and saved markup only.
	$sign = static fn( array $g ): string => md5( serialize( array_map( static fn( $i ) => array( (int) ( $i['attrs']['id'] ?? 0 ), $i['innerHTML'] ?? '' ), images_of( $g ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- A hash input, never stored.
	return $sign( $block ) === $sign( $gallery );
}

/**
 * An image's shape from its attachment size, else its width and height.
 *
 * @param array<string, mixed> $image  Parsed core/image block.
 * @param string               $figure Rendered image.
 * @return string landscape, portrait, square or panorama.
 */
function shape_of( array $image, string $figure ): string {
	$width  = 0;
	$height = 0;
	$id     = (int) ( $image['attrs']['id'] ?? 0 );
	$meta   = $id > 0 ? wp_get_attachment_metadata( $id ) : false;
	if ( is_array( $meta ) ) {
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );
	}
	if ( $width <= 0 || $height <= 0 ) {
		$tags = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'img' ) ) {
			$width  = (int) $tags->get_attribute( 'width' );
			$height = (int) $tags->get_attribute( 'height' );
		}
	}
	if ( $width <= 0 || $height <= 0 ) {
		return 'landscape';
	}
	$ratio = $width / $height;
	if ( $ratio >= 2.0 ) {
		return 'panorama';
	}
	if ( $ratio > 1.15 ) {
		return 'landscape';
	}
	return $ratio < 0.87 ? 'portrait' : 'square';
}

/**
 * The gallery caption, from core's figcaption or the projector's note.
 *
 * @param string $html Rendered gallery.
 * @return string Caption HTML, or ''.
 */
function gallery_caption( string $html ): string {
	if ( preg_match( '/<p class="pkiw-projector__note[^"]*">(.*?)<\/p>/s', $html, $m ) ) {
		return trim( $m[1] );
	}
	if ( preg_match( '/<figcaption class="[^"]*\bblocks-gallery-caption\b[^"]*">(.*?)<\/figcaption>\s*<\/figure>\s*$/s', $html, $m ) ) {
		return trim( $m[1] );
	}
	return '';
}

/**
 * The zine gallery being rendered in this content pass, or null.
 *
 * @param array<string, mixed> $block Parsed core/gallery block.
 * @param mixed                $instance Block instance.
 * @return \WP_Post|null
 */
function zine_gallery_post( array $block, $instance = null ): ?\WP_Post {
	if ( state()['done'] || 'core/gallery' !== ( $block['blockName'] ?? '' ) ) {
		return null;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$post    = zine_post( $post_id );
	$gallery = null === $post ? null : gallery_for( $post );
	return null !== $gallery && same_gallery( $block, $gallery ) ? $post : null;
}

/**
 * A fresh content pass may print the zine again (the plugin's projector
 * resets the same way).
 *
 * @param mixed $content Post content.
 * @return mixed
 */
function start_pass( $content ) {
	$state         = &state();
	$state['done'] = false;
	$state['core'] = '';
	return $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\\start_pass', 1 );

/**
 * Before core renders the zine gallery, turn on core's lightbox for every
 * image the author did not link or opt out of, so core's own gallery render
 * wires the lightbox's previous and next across the panels.
 *
 * @param array<string, mixed> $parsed Parsed block.
 * @return array<string, mixed>
 */
function enable_lightbox( array $parsed ): array {
	if ( null === zine_gallery_post( $parsed ) ) {
		return $parsed;
	}
	foreach ( (array) ( $parsed['innerBlocks'] ?? array() ) as $k => $image ) {
		if ( ! is_array( $image ) || 'core/image' !== ( $image['blockName'] ?? '' ) ) {
			continue;
		}
		$attrs = (array) ( $image['attrs'] ?? array() );
		if ( ! isset( $attrs['lightbox'] ) && in_array( (string) ( $attrs['linkDestination'] ?? 'none' ), array( '', 'none' ), true ) ) {
			$attrs['lightbox']                    = array( 'enabled' => true );
			$parsed['innerBlocks'][ $k ]['attrs'] = $attrs;
		}
	}
	return $parsed;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\enable_lightbox', 20 );

/**
 * Keep core's render of the zine gallery before the plugin's projector
 * (priority 10) replaces it.
 *
 * @param string    $html     Core's rendered gallery.
 * @param array     $block    Parsed core/gallery block.
 * @param \WP_Block $instance Block instance.
 * @return string Unchanged.
 */
function capture( string $html, array $block, $instance = null ): string {
	if ( null !== zine_gallery_post( $block, $instance ) ) {
		$state         = &state();
		$state['core'] = $html;
	}
	return $html;
}
add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\capture', 9, 3 );

/**
 * The post's first gallery as a zine of numbered panels: core's own image
 * figures (alt, caption, srcset, link, lightbox) re-laid in order.
 *
 * @param string    $html     Rendered gallery (core's, or the plugin's projector).
 * @param array     $block    Parsed core/gallery block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function zine( string $html, array $block, $instance = null ): string {
	$post = zine_gallery_post( $block, $instance );
	if ( null === $post ) {
		return $html;
	}
	$state  = &state();
	$core   = '' !== $state['core'] ? $state['core'] : $html;
	$images = images_of( $block );
	$total  = count( $images );
	// Core prints one figure per image block; anything else is not a gallery this can re-lay.
	$count = preg_match_all( '/<figure\b(?=[^>]*\bwp-block-image\b)[^>]*>.*?<\/figure>/s', $core, $found );
	if ( 0 === $total || $count !== $total ) {
		return $html;
	}
	$state['done'] = true;

	$width    = max( 2, strlen( (string) $total ) );
	$figures  = array();
	$shapes   = array();
	$captions = array();
	foreach ( $found[0] as $n => $figure ) {
		$shape = shape_of( $images[ $n ], $figure );
		$tags  = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->add_class( 'u-photo' );
			$tags->set_attribute( 'sizes', SIZES[ 0 === $n ? 'featured' : 'panel' ][ $shape ] );
			$figure = $tags->get_updated_html();
		}
		$figures[]  = $figure;
		$shapes[]   = $shape;
		$captions[] = preg_match( '/<figcaption\b[^>]*>(.*?)<\/figcaption>/s', $figure, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES ) ) : '';
	}

	$desktop = layout( $shapes, 12 );
	$tablet  = layout( $shapes, 6 );
	$dress   = dressing( $desktop, $captions, $shapes );
	$of      = str_pad( (string) $total, $width, '0', STR_PAD_LEFT );
	$panels  = '';
	foreach ( $figures as $n => $figure ) {
		$num     = str_pad( (string) ( $n + 1 ), $width, '0', STR_PAD_LEFT );
		$style   = '--cr-col:' . grid_column( $desktop[ $n ] ) . ';--cr-col-t:' . grid_column( $tablet[ $n ] );
		$classes = 'cr-zine__frame cr-zine__frame--' . $shapes[ $n ];
		foreach ( $dress[ $n ] as $suffix ) {
			$classes .= ' cr-zine__frame--' . $suffix;
		}
		// The anchor carries a camera-style date imprint: the post's own date, nothing more.
		$imprint = 0 === $n ? '<span class="cr-zine__imprint" aria-hidden="true">' . esc_html( get_the_date( "'y m d", $post ) ) . '</span>' : '';
		$panels .= '<li class="' . $classes . '" style="' . esc_attr( $style ) . '">'
			. '<span class="cr-zine__num" aria-hidden="true">' . $num . '<span class="cr-zine__of">/' . $of . '</span></span>'
			. $figure . $imprint . '</li>';
	}

	$caption = gallery_caption( $core );
	$caption = '' !== $caption ? $caption : gallery_caption( $html );
	$note    = '';
	if ( '' !== $caption ) {
		$hand = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $caption ) ) : strlen( wp_strip_all_tags( $caption ) );
		$note = '<p class="cr-zine__note' . ( $hand <= HAND_LIMIT ? ' cr-zine__note--hand' : '' ) . '">' . $caption . '</p>';
	}

	if ( false !== strpos( $html, 'pkiw-projector' ) && function_exists( 'wp_dequeue_script' ) ) {
		wp_dequeue_script( 'pkiw-photo-projector' );
		wp_dequeue_style( 'pkiw-photo-projector' );
	}

	// The section takes over the gallery wrapper's lightbox wiring (core/gallery namespace and its galleryId).
	$wiring  = '';
	$wrapper = new \WP_HTML_Tag_Processor( $core );
	if ( $wrapper->next_tag() && 'core/gallery' === $wrapper->get_attribute( 'data-wp-interactive' ) ) {
		$wiring = ' data-wp-interactive="core/gallery" data-wp-context="' . esc_attr( (string) $wrapper->get_attribute( 'data-wp-context' ) ) . '"';
	}

	return '<section class="cr-zine alignwide" aria-label="' . esc_attr__( 'Photos', 'courtneyr-child' ) . '"' . $wiring . '>'
		. '<ol class="cr-zine__frames" role="list">' . $panels . '</ol>' . $note . '</section>';
}
add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\zine', 11, 3 );

/**
 * The zine's masthead owns the page H1, so the template header steps aside.
 *
 * @param mixed    $holds Whether another lane already owns the H1.
 * @param \WP_Post $post  Post being rendered.
 * @return bool
 */
function holds_h1( $holds, $post = null ): bool {
	if ( state()['asking'] ) {
		return true === $holds;
	}
	return true === $holds || ( $post instanceof \WP_Post && null !== zine_post( $post->ID ) );
}

/**
 * Whether another kind lane (favorite sheet, chicken board, video label and
 * the rest) already prints this page's H1; the masthead then steps aside.
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function other_lane_holds( \WP_Post $post ): bool {
	$state           = &state();
	$state['asking'] = true;
	$holds           = true === apply_filters( 'courtneyr_child_single_object_holds_h1', false, $post );
	$state['asking'] = false;
	return $holds;
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Linked term labels for one taxonomy.
 *
 * @param \WP_Post $post     Post.
 * @param string   $taxonomy Taxonomy.
 * @param string   $extra    Extra class.
 * @return string
 */
function term_labels( \WP_Post $post, string $taxonomy, string $extra ): string {
	$terms = get_the_terms( $post, $taxonomy );
	$out   = '';
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$out .= '<a class="' . esc_attr( trim( 'cr-zine__label ' . $extra ) ) . '" href="' . esc_url( (string) $link ) . '">' . esc_html( $term->name ) . '</a>';
	}
	return $out;
}

/**
 * The masthead at the head of the post content: kind and category labels,
 * the H1, the date and the frame count, and a postmark of the same date.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function masthead( string $html, array $block ): string {
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__content' ) ) {
		return $html;
	}
	$post = zine_post();
	if ( null === $post || other_lane_holds( $post ) ) {
		return $html;
	}
	$count = count( images_of( (array) gallery_for( $post ) ) );

	$titled = '' !== trim( $post->post_title );
	if ( $titled ) {
		$name = esc_html( $post->post_title );
		$name = function_exists( 'wptexturize' ) ? wptexturize( $name ) : $name;
	} elseif ( function_exists( '\\Courtneyr\\Child\\Nav\\kind_date_name' ) ) {
		$name = esc_html( \Courtneyr\Child\Nav\kind_date_name( $post ) );
	} else {
		$terms = get_the_terms( $post, 'kind' );
		/* translators: 1: post kind name, 2: post date. */
		$name = esc_html( sprintf( __( '%1$s, %2$s', 'courtneyr-child' ), is_array( $terms ) && $terms ? $terms[0]->name : __( 'Gallery', 'courtneyr-child' ), get_the_date( '', $post ) ) );
	}

	/* translators: %s: number of photos in the gallery. */
	$photos = sprintf( _n( '%s photo', '%s photos', $count, 'courtneyr-child' ), number_format_i18n_safe( $count ) );
	/* translators: %s: number of frames, zero-padded. */
	$frames = sprintf( _n( '%s frame', '%s frames', $count, 'courtneyr-child' ), str_pad( (string) $count, 2, '0', STR_PAD_LEFT ) );

	$labels = term_labels( $post, 'kind', 'cr-zine__label--kind' ) . term_labels( $post, 'category', '' );
	$header = '<header class="cr-zine__masthead alignwide">'
		. ( '' !== $labels ? '<p class="cr-zine__labels">' . $labels . '</p>' : '' )
		. '<h1 id="' . TITLE_ID . '" class="cr-zine__title' . ( $titled ? ' p-name' : '' ) . '"><span class="cr-zine__title-paper">' . $name . '</span></h1>'
		. '<p class="cr-zine__facts"><time class="dt-published" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>'
		. ' <span class="cr-zine__count">' . esc_html( $photos ) . '</span></p>'
		. '<span class="cr-zine__postmark" aria-hidden="true"><span class="cr-zine__postmark-date">' . esc_html( get_the_date( 'M j Y', $post ) ) . '</span>'
		. '<span class="cr-zine__postmark-count">' . esc_html( $frames ) . '</span></span>'
		. '</header>';

	$open = strpos( $html, '>' );
	return false === $open ? $header . $html : substr( $html, 0, $open + 1 ) . $header . substr( $html, $open + 1 );
}
add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\masthead', 20, 2 );

/**
 * A number for display.
 *
 * @param int $number Number.
 * @return string
 */
function number_format_i18n_safe( int $number ): string {
	return function_exists( 'number_format_i18n' ) ? (string) number_format_i18n( $number ) : (string) $number;
}

/**
 * Add the zine single's body class.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( null !== zine_post() && ! in_array( BODY_CLASS, $classes, true ) ) {
		$classes[] = BODY_CLASS;
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/** Load the zine sheet on a zine single only. */
function enqueue_styles(): void {
	if ( null === zine_post() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-gallery-zine',
		COURTNEYR_CHILD_URI . '/assets/css/cr-gallery-zine.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles' );
