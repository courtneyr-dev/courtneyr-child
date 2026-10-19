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

/** Shapes wide enough to stack as a hero's satellites. */
const WIDE = array( 'landscape', 'square' );

/** Width over height, by shape, for rows whose pictures share a height. */
const RATIO = array(
	'landscape' => 1.33,
	'square'    => 1.0,
	'portrait'  => 0.75,
	'panorama'  => 2.5,
);

/** A panel alone on its row, by shape, on the 12- and 6-column grids. */
const SOLO_SPAN = array(
	12 => array(
		'panorama'  => 12,
		'landscape' => 9,
		'square'    => 7,
		'portrait'  => 5,
	),
	6  => array(
		'panorama'  => 6,
		'landscape' => 6,
		'square'    => 5,
		'portrait'  => 4,
	),
);

/** The hero's columns beside its two satellites. */
const HERO_SPAN = array(
	'landscape' => 8,
	'square'    => 7,
	'portrait'  => 6,
);

/** A caption up to this many characters is handwritten on a print's edge. */
const HAND_CAPTION_LIMIT = 40;

/** Row spacing, in steps of the sheet's rhythm: a new group, a row inside a group, a dropped panel. */
const LEAD = 3;
const STEP = 1;
const DROP = 2;

/**
 * The gallery as composed groups, in source order.
 *
 * Patterns: solo (one picture), journal (one captioned picture with its
 * caption as a margin note), banner (a panorama), stack (a panorama and its
 * neighbour), diptych (two), essay (two landscapes stepping down the page),
 * triptych (three), hero (one picture with two wide satellites) and contact
 * (the tail of a long gallery as a contact sheet). The walk reads each
 * picture's shape and what remains, never reorders, and never leaves a lone
 * picture at the end unless it is the only one or a full-width banner.
 *
 * @param string[] $shapes   Panel shapes.
 * @param string[] $captions Panel caption text ('' for none).
 * @return array<int, array{0: string, 1: int[]}> Pattern and the panels it holds.
 */
function groups( array $shapes, array $captions ): array {
	$n    = count( $shapes );
	$out  = array();
	$i    = 0;
	$wide = static fn( int $k ): bool => in_array( $shapes[ $k ] ?? '', WIDE, true );
	$pano = static fn( int $k ): bool => 'panorama' === ( $shapes[ $k ] ?? '' );
	while ( $i < $n ) {
		$left = $n - $i;
		$prev = $out ? $out[ count( $out ) - 1 ][0] : '';
		if ( $n >= 12 && $i >= 8 && $left >= 4 ) {
			$out[] = array( 'contact', range( $i, $n - 1 ) );
			break;
		}
		if ( 1 === $left ) {
			$single = '' !== ( $captions[ $i ] ?? '' ) ? 'journal' : 'solo';
			$out[]  = array( $pano( $i ) ? 'banner' : $single, array( $i ) );
			break;
		}
		if ( $pano( $i ) || $pano( $i + 1 ) ) {
			if ( $pano( $i ) && 2 !== $left ) {
				$out[] = array( 'banner', array( $i ) );
				++$i;
				continue;
			}
			$out[] = array( 'stack', array( $i, $i + 1 ) );
			$i    += 2;
			continue;
		}
		if ( $left >= 3 && 4 !== $left && ! $pano( $i + 2 ) ) {
			$out[] = array( 'hero' !== $prev && $wide( $i + 1 ) && $wide( $i + 2 ) ? 'hero' : 'triptych', array( $i, $i + 1, $i + 2 ) );
			$i    += 3;
			continue;
		}
		$out[] = array( 'landscape' === $shapes[ $i ] && 'landscape' === $shapes[ $i + 1 ] ? 'essay' : 'diptych', array( $i, $i + 1 ) );
		$i    += 2;
	}
	return $out;
}

/**
 * Spans for pictures that share a row and a height: each takes columns in
 * proportion to its width over height, at least $min, summing to $cols.
 *
 * @param string[] $shapes Shapes in the row.
 * @param int      $cols   Columns to fill.
 * @param int      $min    Fewest columns per picture.
 * @return int[]
 */
function justify( array $shapes, int $cols, int $min ): array {
	$ratios = array_map( static fn( string $s ): float => RATIO[ $s ], $shapes );
	$total  = array_sum( $ratios );
	$spans  = array_map( static fn( float $r ): int => max( $min, (int) floor( $cols * $r / $total ) ), $ratios );
	$order  = array_keys( $ratios );
	// Widest first; equal ratios keep source order.
	usort( $order, static fn( int $a, int $b ): int => array( $ratios[ $b ], $a ) <=> array( $ratios[ $a ], $b ) );
	$size  = count( $order );
	$spare = $cols - array_sum( $spans );
	for ( $k = 0; $k < $spare; ++$k ) {
		++$spans[ $order[ $k % $size ] ];
	}
	// Over: take columns back from the narrowest that can give them.
	$over = array_sum( $spans ) - $cols;
	for ( $k = $size - 1, $guard = 0; $over > 0 && $guard < $cols * $size; $k = ( $k + $size - 1 ) % $size, ++$guard ) {
		if ( $spans[ $order[ $k ] ] > $min ) {
			--$spans[ $order[ $k ] ];
			--$over;
		}
	}
	return $spans;
}

/**
 * A cell: start column, span, rows.
 *
 * @param int $start Start column.
 * @param int $span  Columns.
 * @param int $rows  Rows.
 * @return array{start: int, span: int, rows: int}
 */
function cell( int $start, int $span, int $rows = 1 ): array {
	return array(
		'start' => $start,
		'span'  => $span,
		'rows'  => $rows,
	);
}

/**
 * Cells for a run of pictures that share a row: consecutive starts from 1.
 *
 * @param int[] $spans Spans.
 * @return array<int, array{start: int, span: int, rows: int}>
 */
function row_cells( array $spans ): array {
	$out   = array();
	$start = 1;
	foreach ( $spans as $span ) {
		$out[]  = cell( $start, $span );
		$start += $span;
	}
	return $out;
}

/**
 * Where each panel sits on the 12-column desktop, 6-column tablet and
 * 2-column phone grids, how it is presented, and the space above its row.
 *
 * @param string[] $shapes   Panel shapes, in source order.
 * @param string[] $captions Panel caption text ('' for none).
 * @return array<int, array{pattern: string, group: int, treat: string, flags: string[], d: array, t: array, p: array, mt: array{d: int, t: int, p: int}}>
 */
function compose( array $shapes, array $captions ): array {
	$plan  = array();
	$taped = false;
	foreach ( groups( $shapes, $captions ) as $g => list( $pattern, $members ) ) {
		$s     = array_map( static fn( int $k ): string => $shapes[ $k ], $members );
		$cells = array();
		$flags = array_fill( 0, count( $members ), array() );
		$off   = array_fill( 0, count( $members ), 0 );
		switch ( $pattern ) {
			case 'solo':
			case 'banner':
			case 'stack':
				foreach ( $s as $k => $shape ) {
					$d           = SOLO_SPAN[12][ $shape ];
					$t           = SOLO_SPAN[6][ $shape ];
					$cells[ $k ] = array( cell( intdiv( 12 - $d, 2 ) + 1, $d ), cell( intdiv( 6 - $t, 2 ) + 1, $t ), cell( 1, 2 ) );
					$flags[ $k ] = array( 'center' );
				}
				break;
			case 'journal':
				$d        = 'portrait' === $s[0] ? 8 : ( 'square' === $s[0] ? 9 : 10 );
				$cells[0] = array( cell( intdiv( 12 - $d, 2 ) + 1, $d ), cell( 1, 6 ), cell( 1, 2 ) );
				break;
			case 'essay':
				$cells    = array(
					array( cell( 1, 8 ), cell( 1, 5 ), cell( 1, 2 ) ),
					array( cell( 5, 8 ), cell( 2, 5 ), cell( 1, 2 ) ),
				);
				$flags[1] = array( 'step' );
				break;
			case 'diptych':
				if ( $s[0] === $s[1] ) {
					$cells  = array(
						array( cell( 2, 5 ), cell( 1, 3 ), cell( 1, 'portrait' === $s[0] ? 1 : 2 ) ),
						array( cell( 7, 5 ), cell( 4, 3 ), cell( 'portrait' === $s[0] ? 2 : 1, 'portrait' === $s[0] ? 1 : 2 ) ),
					);
					$off[1] = 'square' === $s[0] ? DROP : 0;
				} else {
					$d     = row_cells( justify( $s, 12, 4 ) );
					$cells = array(
						array( $d[0], cell( 1, 3 ), cell( 1, 2 ) ),
						array( $d[1], cell( 4, 3 ), cell( 1, 2 ) ),
					);
					$flags = array( array( 'baseline' ), array( 'baseline' ) );
				}
				$flags[0][] = 'toward-end';
				$flags[1][] = 'toward-start';
				break;
			case 'triptych':
				if ( array( 'portrait', 'portrait', 'portrait' ) === $s ) {
					$d     = row_cells( array( 4, 4, 4 ) );
					$t     = row_cells( array( 2, 2, 2 ) );
					$cells = array(
						array( $d[0], $t[0], cell( 1, 1 ) ),
						array( $d[1], $t[1], cell( 2, 1 ) ),
						array( $d[2], $t[2], cell( 1, 2 ) ),
					);
					$off   = array( DROP, 0, DROP );
				} else {
					$d = row_cells( justify( $s, 12, 3 ) );
					$t = row_cells( justify( $s, 6, 2 ) );
					foreach ( $s as $k => $shape ) {
						$cells[ $k ] = array( $d[ $k ], $t[ $k ], cell( 1, 2 ) );
						$flags[ $k ] = array( 'baseline' );
					}
				}
				break;
			case 'hero':
				$h        = HERO_SPAN[ $s[0] ];
				$sat_p    = 'square' === $s[1] && 'square' === $s[2];
				$cells    = array(
					array( cell( 1, $h, 2 ), cell( 1, 6 ), cell( 1, 2 ) ),
					array( cell( $h + 1, 12 - $h ), cell( 1, 3 ), $sat_p ? cell( 1, 1 ) : cell( 1, 2 ) ),
					array( cell( $h + 1, 12 - $h ), cell( 4, 3 ), $sat_p ? cell( 2, 1 ) : cell( 1, 2 ) ),
				);
				$flags[1] = array( 'hero-sat' );
				$flags[2] = array( 'hero-sat', 'baseline' );
				break;
			case 'contact':
				foreach ( $members as $k => $unused ) {
					$cells[ $k ] = array( cell( 1 + ( $k % 4 ) * 3, 3 ), cell( 1 + ( $k % 3 ) * 2, 2 ), cell( 1 + $k % 2, 1 ) );
				}
				break;
		}

		foreach ( $members as $k => $n ) {
			$treat = 'bare';
			if ( 'contact' === $pattern ) {
				$treat = 'contact';
			} elseif ( 0 === $n ) {
				$treat = 'anchor';
			} elseif ( ( 'hero' === $pattern && $k > 0 ) || ( 'triptych' === $pattern && 1 === $k ) ) {
				$treat = 'print';
			}
			if ( 'print' === $treat && ! $taped && $n >= 3 && 'panorama' !== $shapes[ $n ] ) {
				$treat = 'taped';
				$taped = true;
			}
			$item_flags = $flags[ $k ];
			if ( 'anchor' === $treat && 1 === $cells[ $k ][0]['start'] && $cells[ $k ][0]['span'] < 12 ) {
				$item_flags[] = 'edge';
			}
			if ( 'anchor' === $treat && 2 === $cells[ $k ][2]['span'] ) {
				$item_flags[] = 'bleed';
			}
			if ( ( 'anchor' === $treat && 'journal' !== $pattern ) || ( 0 === $k && 2 === $g % 3 && 'bare' === $treat ) ) {
				$item_flags[] = 'plate';
			}
			$caption = (string) ( $captions[ $n ] ?? '' );
			$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $caption ) : strlen( $caption );
			if ( '' !== $caption && ( ( in_array( $treat, array( 'print', 'taped' ), true ) && $length <= HAND_CAPTION_LIMIT ) || ( 'journal' === $pattern && $length <= HAND_LIMIT ) ) ) {
				$item_flags[] = 'hand';
			}
			$plan[ $n ] = array(
				'pattern' => $pattern,
				'group'   => $g,
				'treat'   => $treat,
				'flags'   => $item_flags,
				'd'       => $cells[ $k ][0],
				't'       => $cells[ $k ][1],
				'p'       => $cells[ $k ][2],
				'off'     => $off[ $k ],
			);
		}
	}
	ksort( $plan );

	// Space above each row, per grid: none above the first, a lead above a new group, a step inside one.
	foreach ( array( 'd', 't', 'p' ) as $bp ) {
		$end   = PHP_INT_MAX;
		$group = -1;
		$space = 0;
		foreach ( $plan as $n => $item ) {
			if ( $item[ $bp ]['start'] <= $end ) {
				if ( 0 === $n ) {
					$space = 0;
				} elseif ( $item['group'] !== $group ) {
					$space = LEAD;
				} else {
					$space = 'contact' === $item['pattern'] ? 0 : STEP;
				}
			}
			$group                   = $item['group'];
			$end                     = $item[ $bp ]['start'] + $item[ $bp ]['span'] - 1;
			$drop                    = 'd' === $bp ? $item['off'] : ( 't' === $bp ? intdiv( $item['off'], 2 ) : 0 );
			$plan[ $n ]['mt'][ $bp ] = $space + $drop;
		}
	}
	foreach ( $plan as $n => $item ) {
		unset( $plan[ $n ]['off'] );
	}
	return $plan;
}

/**
 * A panel's grid-column (and grid-row) value.
 *
 * @param array{start: int, span: int, rows: int} $cell Cell.
 * @return string
 */
function grid_area( array $cell ): string {
	return $cell['start'] . ' / span ' . $cell['span'];
}

/**
 * The image sizes a panel's spans need on phone, tablet and desktop.
 *
 * @param array $item Panel from compose().
 * @return string
 */
function sizes_for( array $item ): string {
	$phone  = 1 === $item['p']['span'] ? '50vw' : '100vw';
	$tablet = (int) round( $item['t']['span'] / 6 * 100 ) . 'vw';
	$desk   = rtrim( rtrim( number_format( $item['d']['span'] * 5.25, 2, '.', '' ), '0' ), '.' ) . 'rem';
	return '(max-width: 40rem) ' . $phone . ', (max-width: 64rem) ' . $tablet . ', ' . $desk;
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
		$figures[]  = $figure;
		$shapes[]   = shape_of( $images[ $n ], $figure );
		$captions[] = preg_match( '/<figcaption\b[^>]*>(.*?)<\/figcaption>/s', $figure, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES ) ) : '';
	}

	$plan   = compose( $shapes, $captions );
	$of     = str_pad( (string) $total, $width, '0', STR_PAD_LEFT );
	$panels = '';
	foreach ( $figures as $n => $figure ) {
		$item = $plan[ $n ];
		$tags = new \WP_HTML_Tag_Processor( $figure );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->add_class( 'u-photo' );
			$tags->set_attribute( 'sizes', sizes_for( $item ) );
			$figure = $tags->get_updated_html();
		}
		$num   = str_pad( (string) ( $n + 1 ), $width, '0', STR_PAD_LEFT );
		$style = '--cr-col:' . grid_area( $item['d'] ) . ';--cr-col-t:' . grid_area( $item['t'] ) . ';--cr-col-p:' . grid_area( $item['p'] )
			. ';--cr-mt:' . $item['mt']['d'] . ';--cr-mt-t:' . $item['mt']['t'] . ';--cr-mt-p:' . $item['mt']['p'];
		if ( $item['d']['rows'] > 1 ) {
			$style .= ';--cr-rows:span ' . $item['d']['rows'];
		}
		$classes = 'cr-zine__frame cr-zine__frame--' . $shapes[ $n ] . ' cr-zine__frame--' . $item['pattern'] . ' cr-zine__frame--' . $item['treat'];
		foreach ( $item['flags'] as $flag ) {
			$classes .= ' cr-zine__frame--' . $flag;
		}
		// The anchor carries a camera-style date imprint: the post's own date, nothing more.
		if ( 'anchor' === $item['treat'] ) {
			// Inside the figure, so the imprint sits on the photograph however the cell is sized.
			$imprint = '<span class="cr-zine__imprint" aria-hidden="true">' . esc_html( get_the_date( "'y m d", $post ) ) . '</span>';
			$at      = strrpos( $figure, '</figure>' );
			$figure  = false === $at ? $figure . $imprint : substr( $figure, 0, $at ) . $imprint . substr( $figure, $at );
		}
		$panels .= '<li class="' . $classes . '" style="' . esc_attr( $style ) . '">'
			. '<span class="cr-zine__num" aria-hidden="true">' . $num . '<span class="cr-zine__of">/' . $of . '</span></span>'
			. $figure . '</li>';
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
