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
		'landscape' => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 46rem',
		'panorama'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 69rem',
		'square'    => '(max-width: 40rem) 100vw, (max-width: 64rem) 100vw, 40rem',
		'portrait'  => '(max-width: 40rem) 100vw, (max-width: 64rem) 66vw, 29rem',
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
 * and what it takes alone on a row. The featured first panel prefers more.
 */
const SPANS = array(
	12 => array(
		'featured' => array(
			'panorama'  => 12,
			'landscape' => 8,
			'square'    => 6,
			'portrait'  => 5,
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
		'lone'     => array(
			'panorama'  => 6,
			'landscape' => 6,
			'square'    => 4,
			'portrait'  => 4,
		),
	),
);

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
			'min'   => min( $table['min'][ $shape ], $table[ 0 === $i ? 'featured' : 'pref' ][ $shape ] ),
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
 * Per-request state: whether the zine printed, and each post's gallery.
 *
 * @param bool $reset Clear the state.
 * @return array{done: bool, galleries: array<int, array|null>}
 */
function &state( bool $reset = false ): array {
	static $state = array(
		'done'      => false,
		'galleries' => array(),
	);
	if ( $reset ) {
		$state = array(
			'done'      => false,
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
 * A gallery-format post: its first gallery with an image. A photo post: the
 * first gallery the plugin's projector takes (two or more images).
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function gallery_for( \WP_Post $post ): ?array {
	$state = &state();
	if ( ! array_key_exists( $post->ID, $state['galleries'] ) ) {
		$min                             = has_post_format( 'gallery', $post ) ? 1 : ( has_term( 'photo', 'kind', $post ) ? 2 : 0 );
		$state['galleries'][ $post->ID ] = 0 === $min ? null : first_gallery( parse_blocks( (string) $post->post_content ), $min );
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
	$sign = static fn( array $g ): string => md5( serialize( array_map( static fn( $i ) => array( $i['attrs'] ?? array(), $i['innerHTML'] ?? '' ), images_of( $g ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- A hash input, never stored.
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
 * The post's first gallery as a zine of numbered panels.
 *
 * @param string    $html     Rendered gallery (core's, or the plugin's projector).
 * @param array     $block    Parsed core/gallery block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function zine( string $html, array $block, $instance = null ): string {
	$state = &state();
	if ( $state['done'] ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$post    = zine_post( $post_id );
	if ( null === $post ) {
		return $html;
	}
	$gallery = gallery_for( $post );
	if ( null === $gallery || ! same_gallery( $block, $gallery ) ) {
		return $html;
	}
	$images = images_of( $block );
	$total  = count( $images );
	if ( 0 === $total ) {
		return $html;
	}

	$width   = max( 2, strlen( (string) $total ) );
	$figures = array();
	$shapes  = array();
	foreach ( $images as $n => $image ) {
		$image['attrs'] = (array) ( $image['attrs'] ?? array() );
		// Core's lightbox for every panel the author did not link or opt out of.
		if ( ! isset( $image['attrs']['lightbox'] ) && in_array( (string) ( $image['attrs']['linkDestination'] ?? 'none' ), array( '', 'none' ), true ) ) {
			$image['attrs']['lightbox'] = array( 'enabled' => true );
		}
		$figure = render_block( $image );
		$shape  = shape_of( $image, $figure );
		$tags   = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->add_class( 'u-photo' );
			$tags->set_attribute( 'sizes', SIZES[ 0 === $n ? 'featured' : 'panel' ][ $shape ] );
			$figure = $tags->get_updated_html();
		}
		$figures[] = $figure;
		$shapes[]  = $shape;
	}
	$state['done'] = true;

	$desktop = layout( $shapes, 12 );
	$tablet  = layout( $shapes, 6 );
	$of      = str_pad( (string) $total, $width, '0', STR_PAD_LEFT );
	$panels  = '';
	foreach ( $figures as $n => $figure ) {
		$num     = str_pad( (string) ( $n + 1 ), $width, '0', STR_PAD_LEFT );
		$style   = '--cr-col:' . grid_column( $desktop[ $n ] ) . ';--cr-col-t:' . grid_column( $tablet[ $n ] );
		$panels .= '<li class="cr-zine__frame cr-zine__frame--' . $shapes[ $n ] . '" style="' . esc_attr( $style ) . '">'
			. '<span class="cr-zine__num" aria-hidden="true">' . $num . '<span class="cr-zine__of">/' . $of . '</span></span>'
			. $figure . '</li>';
	}

	$caption = gallery_caption( $html );
	$note    = '';
	if ( '' !== $caption ) {
		$hand = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $caption ) ) : strlen( wp_strip_all_tags( $caption ) );
		$note = '<p class="cr-zine__note' . ( $hand <= HAND_LIMIT ? ' cr-zine__note--hand' : '' ) . '">' . $caption . '</p>';
	}

	if ( false !== strpos( $html, 'pkiw-projector' ) && function_exists( 'wp_dequeue_script' ) ) {
		wp_dequeue_script( 'pkiw-photo-projector' );
		wp_dequeue_style( 'pkiw-photo-projector' );
	}

	return '<section class="cr-zine alignwide" aria-labelledby="' . TITLE_ID . '">'
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
	return true === $holds || ( $post instanceof \WP_Post && null !== zine_post( $post->ID ) );
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
	if ( null === $post ) {
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
