<?php
/**
 * `php tests/php/gallery-zine-test.php`: the gallery-format single as a zine
 * photo essay, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/photo-bootstrap.php';

	$GLOBALS['cr_zine_formats']  = array();
	$GLOBALS['cr_zine_meta']     = array();
	$GLOBALS['cr_zine_dequeued'] = array();

	if ( ! function_exists( 'has_post_format' ) ) {
		function has_post_format( $format = array(), $post = null ): bool {
			return ( $GLOBALS['cr_zine_formats'][ cr_photo_post_id( $post ) ] ?? '' ) === $format;
		}
	}
	if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
		function wp_get_attachment_metadata( $id ) {
			return $GLOBALS['cr_zine_meta'][ (int) $id ] ?? false;
		}
	}
	if ( ! function_exists( 'get_the_date' ) ) {
		function get_the_date( $format = '', $post = null ): string {
			return 'c' === $format ? '2026-10-10T12:00:00+00:00' : ( 'M j Y' === $format ? 'Oct 10 2026' : 'October 10, 2026' );
		}
	}
	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $post, $taxonomy ) {
			$names = 'kind' === $taxonomy ? array( 'photo' => 'Photo' ) : ( 'category' === $taxonomy ? array( 'travel' => 'Travel' ) : array() );
			$terms = array();
			foreach ( $names as $slug => $name ) {
				$terms[] = (object) array( 'slug' => $slug, 'name' => $name, 'taxonomy' => $taxonomy );
			}
			return $terms ?: false;
		}
	}
	if ( ! function_exists( 'get_term_link' ) ) {
		function get_term_link( $term, $taxonomy = '' ): string {
			return 'https://example.test/' . $term->taxonomy . '/' . $term->slug . '/';
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ): bool {
			return false;
		}
	}
	if ( ! function_exists( 'wp_dequeue_script' ) ) {
		function wp_dequeue_script( $handle ): void {
			$GLOBALS['cr_zine_dequeued'][] = 'script:' . $handle;
		}
		function wp_dequeue_style( $handle ): void {
			$GLOBALS['cr_zine_dequeued'][] = 'style:' . $handle;
		}
	}
	if ( ! function_exists( '_n' ) ) {
		function _n( $single, $plural, $number, $domain = null ): string {
			return 1 === (int) $number ? (string) $single : (string) $plural;
		}
	}
	if ( ! function_exists( 'parse_blocks' ) ) {
		function parse_blocks( string $content ): array {
			return $GLOBALS['cr_zine_blocks'] ?? array();
		}
	}
	if ( ! function_exists( 'render_block' ) ) {
		/** Core prints an Image block's saved markup; the lightbox attr shows as core's directive. */
		function render_block( array $block ): string {
			$GLOBALS['cr_zine_rendered'][] = $block;
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( ! empty( $block['attrs']['lightbox']['enabled'] ) ) {
				$html = (string) preg_replace( '/^<figure /', '<figure data-wp-interactive="core/image" ', $html );
			}
			return $html;
		}
	}

	require CR_PHOTO_THEME_DIR . '/inc/gallery-zine.php';
}

namespace {
	use function Courtneyr\Child\GalleryZine\holds_h1;
	use function Courtneyr\Child\GalleryZine\layout;
	use function Courtneyr\Child\GalleryZine\masthead;
	use function Courtneyr\Child\GalleryZine\reset_state;
	use function Courtneyr\Child\GalleryZine\zine;

	const CR_ZINE_SHAPES = array(
		701 => array( 1600, 1200 ),
		702 => array( 1200, 1600 ),
		703 => array( 1600, 1600 ),
		704 => array( 1600, 640 ),
	);

	/** A gallery-format single request for post $id with $count images (701-704 cycling). */
	function cr_zine_request( int $count, int $id = 42, string $title = 'Blue Hour at Example Lake', bool $linked_third = false ): array {
		cr_photo_reset( $id, $title );
		$GLOBALS['cr_photo_request']['single'] = true;
		$GLOBALS['cr_zine_formats']            = array( $id => 'gallery' );
		$GLOBALS['cr_zine_dequeued']           = array();
		$GLOBALS['cr_zine_rendered']           = array();
		$GLOBALS['cr_zine_meta']               = array();
		foreach ( CR_ZINE_SHAPES as $att => $wh ) {
			$GLOBALS['cr_zine_meta'][ $att ] = array( 'width' => $wh[0], 'height' => $wh[1] );
		}
		$inner = array();
		for ( $n = 1; $n <= $count; $n++ ) {
			$att     = 701 + ( ( $n - 1 ) % 4 );
			$caption = 1 === $n % 2 ? '<figcaption class="wp-element-caption">Caption ' . $n . '</figcaption>' : '';
			$img     = '<img src="https://example.test/' . $att . '.jpg" alt="Alt ' . $n . '" class="wp-image-' . $att . '" srcset="https://example.test/' . $att . '-300.jpg 300w" sizes="(max-width: 1024px) 100vw, 1024px" width="1024" height="768"/>';
			$linked  = $linked_third && 3 === $n;
			$inner[] = array(
				'blockName'   => 'core/image',
				'attrs'       => array( 'id' => $att, 'sizeSlug' => 'large', 'linkDestination' => $linked ? 'media' : 'none' ),
				'innerBlocks' => array(),
				'innerHTML'   => '<figure class="wp-block-image size-large">' . ( $linked ? '<a href="https://example.test/' . $att . '.jpg">' . $img . '</a>' : $img ) . $caption . '</figure>',
			);
		}
		$GLOBALS['cr_zine_blocks'] = array( array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => $inner, 'innerHTML' => '' ) );
		reset_state();
		return $GLOBALS['cr_zine_blocks'][0];
	}

	/** Core's rendered gallery, with an optional gallery caption. */
	function cr_zine_core_html( string $caption = '' ): string {
		return '<figure class="wp-block-gallery has-nested-images">images' . ( '' !== $caption ? '<figcaption class="blocks-gallery-caption wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';
	}

	function cr_zine_render( array $gallery, string $html = '', int $id = 42 ): string {
		return zine( '' !== $html ? $html : cr_zine_core_html(), $gallery, new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_photo_group(
		'hooks: the zine replaces the first gallery after the plugin projector, holds the H1, prints the masthead, loads its sheet',
		static function (): void {
			cr_photo_assert( cr_photo_hooked( 'render_block_core/gallery', 'Courtneyr\\Child\\GalleryZine\\zine', 11, 3 ), 'zine at 11' );
			cr_photo_assert( cr_photo_hooked( 'courtneyr_child_single_object_holds_h1', 'Courtneyr\\Child\\GalleryZine\\holds_h1', 10, 2 ), 'holds the H1' );
			cr_photo_assert( cr_photo_hooked( 'render_block_core/post-content', 'Courtneyr\\Child\\GalleryZine\\masthead', 20, 2 ), 'masthead on the post content' );
			cr_photo_assert( cr_photo_hooked( 'wp_enqueue_scripts', 'Courtneyr\\Child\\GalleryZine\\enqueue_styles' ), 'sheet' );
			cr_photo_assert( cr_photo_hooked( 'body_class', 'Courtneyr\\Child\\GalleryZine\\body_class' ), 'body class' );
		}
	);

	cr_photo_group(
		'every image becomes a numbered panel that keeps core markup, alt, srcset, caption and lightbox, sized by its real orientation',
		static function (): void {
			$gallery = cr_zine_request( 6 );
			$html    = cr_zine_render( $gallery, cr_zine_core_html( 'A short gallery caption.' ) );
			cr_photo_assert( 1 === preg_match( '/^<section class="cr-zine alignwide" aria-labelledby="cr-zine-title">/', $html ), 'a section labelled by the masthead H1' );
			cr_photo_assert( 1 === preg_match( '/<ol class="cr-zine__frames" role="list">/', $html ), 'an ordered list of panels' );
			cr_photo_same( 6, preg_match_all( '/<li class="cr-zine__frame cr-zine__frame--[a-z]+" style="--cr-col:[^"]+;--cr-col-t:[^"]+">/', $html ), 'six panels, each placed on both grids' );
			foreach ( array( 'landscape' => 2, 'portrait' => 2, 'square' => 1, 'panorama' => 1 ) as $shape => $n ) {
				cr_photo_same( $n, substr_count( $html, 'cr-zine__frame--' . $shape . '" style' ), $shape . ' from the attachment size' );
			}
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true">01<span class="cr-zine__of">/06</span></span>', $html, 'numbered 01/06' );
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true">06<span class="cr-zine__of">/06</span></span>', $html, 'numbered 06/06' );
			cr_photo_same( 6, substr_count( $html, 'srcset="https://example.test/' ), 'srcset kept' );
			cr_photo_same( 6, preg_match_all( '/alt="Alt \d"/', $html ), 'alt kept' );
			cr_photo_same( 3, substr_count( $html, '<figcaption class="wp-element-caption">Caption ' ), 'captions kept, none invented' );
			cr_photo_same( 6, substr_count( $html, 'data-wp-interactive="core/image"' ), 'core lightbox on unlinked images' );
			cr_photo_same( 6, preg_match_all( '/<img\b[^>]*\bclass="[^"]*\bu-photo\b/', $html ), 'u-photo on every image' );
			cr_photo_assert( 1 === preg_match( '/<li class="cr-zine__frame cr-zine__frame--landscape"[^>]*>.*?sizes="\(max-width: 40rem\) 100vw, \(max-width: 64rem\) 100vw, 46rem"/s', $html ), 'the featured panel is sized for its span' );
			cr_photo_contains( '<p class="cr-zine__note cr-zine__note--hand">A short gallery caption.</p>', $html, 'a short gallery caption is the handwritten note' );
			cr_photo_not_contains( 'wp-block-gallery', $html, 'the gallery wrapper is replaced' );
		}
	);

	cr_photo_group(
		'a long gallery caption stays typeset; a linked image keeps its link and gets no lightbox',
		static function (): void {
			$gallery = cr_zine_request( 4, 42, 'Blue Hour at Example Lake', true );
			$long    = 'A longer gallery caption that runs past the handwriting limit so it stays typeset in the body face, the way notes read.';
			$html    = cr_zine_render( $gallery, cr_zine_core_html( $long ) );
			cr_photo_contains( '<p class="cr-zine__note">' . $long . '</p>', $html, 'typeset note' );
			cr_photo_contains( '<a href="https://example.test/703.jpg">', $html, 'the media link stays' );
			cr_photo_same( 3, substr_count( $html, 'data-wp-interactive="core/image"' ), 'no lightbox on the linked image' );
		}
	);

	cr_photo_group(
		'the plugin projector output is replaced too, with its caption kept and its assets dequeued',
		static function (): void {
			$gallery   = cr_zine_request( 5 );
			$projector = '<div class="wp-block-gallery pkiw-projector" role="region"><div class="pkiw-projector__mount">slides</div><p class="pkiw-projector__note wp-element-caption">From the projector.</p></div>';
			$html      = cr_zine_render( $gallery, $projector );
			cr_photo_same( 5, substr_count( $html, '<li class="cr-zine__frame' ), 'five panels' );
			cr_photo_contains( 'From the projector.', $html, 'caption kept' );
			cr_photo_not_contains( 'pkiw-projector', $html, 'no projector' );
			cr_photo_assert( in_array( 'script:pkiw-photo-projector', $GLOBALS['cr_zine_dequeued'], true ) && in_array( 'style:pkiw-photo-projector', $GLOBALS['cr_zine_dequeued'], true ), 'projector assets dequeued' );
		}
	);

	cr_photo_group(
		'counts from 1 to 20 all render, in order, numbered against the real total',
		static function (): void {
			foreach ( array( 1, 2, 3, 4, 5, 6, 10, 20 ) as $count ) {
				$html = cr_zine_render( cr_zine_request( $count ) );
				cr_photo_same( $count, substr_count( $html, '<li class="cr-zine__frame' ), $count . ' panels' );
				$total = str_pad( (string) $count, 2, '0', STR_PAD_LEFT );
				cr_photo_contains( '>01<span class="cr-zine__of">/' . $total . '</span>', $html, 'first of ' . $count );
				cr_photo_contains( '>' . $total . '<span class="cr-zine__of">/' . $total . '</span>', $html, 'last of ' . $count );
			}
		}
	);

	cr_photo_group(
		'only the first gallery of the queried, unprotected gallery-format single changes',
		static function (): void {
			$gallery = cr_zine_request( 3 );
			cr_zine_render( $gallery );
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery ), 'a second gallery stays core' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_zine_formats']                   = array( 42 => 'image' );
			$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery ), 'another post format' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_zine_formats'] = array();
			cr_photo_assert( str_starts_with( cr_zine_render( $gallery ), '<section class="cr-zine' ), 'a photo post the projector would take gets the zine in any format' );

			$gallery = cr_zine_request( 1 );
			$GLOBALS['cr_zine_formats'] = array();
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery ), 'a one-image photo gallery outside the gallery format stays core' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_photo_request']['protected'][42] = true;
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery ), 'password protected' );

			$gallery = cr_zine_request( 3 );
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery, '', 77 ), 'not the queried post' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( cr_zine_core_html(), cr_zine_render( $gallery ), 'not a single' );

			cr_zine_request( 0 );
			cr_photo_same( cr_zine_core_html(), cr_zine_render( array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '' ) ), 'no images' );
		}
	);

	cr_photo_group(
		'the masthead carries the H1, linked kind and category labels and a postmark of real data only',
		static function (): void {
			cr_zine_request( 6 );
			$post = $GLOBALS['cr_photo_posts'][42];
			cr_photo_same( true, holds_h1( false, $post ), 'holds the H1 for a gallery-format single with a gallery' );
			$html = masthead( '<div class="entry-content">body</div>', array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content wp-block-post-content' ) ) );
			cr_photo_assert( 1 === preg_match( '/^<div class="entry-content"><header class="cr-zine__masthead alignwide">/', $html ), 'masthead first inside the content' );
			cr_photo_contains( '<h1 id="cr-zine-title" class="cr-zine__title p-name"><span class="cr-zine__title-paper">Blue Hour at Example Lake</span></h1>', $html, 'the titled H1 is the p-name' );
			cr_photo_contains( '<a class="cr-zine__label cr-zine__label--kind" href="https://example.test/kind/photo/">Photo</a>', $html, 'kind label links its archive' );
			cr_photo_contains( '<a class="cr-zine__label" href="https://example.test/category/travel/">Travel</a>', $html, 'category label links its archive' );
			cr_photo_contains( '<time class="dt-published" datetime="2026-10-10T12:00:00+00:00">October 10, 2026</time>', $html, 'the dt-published date' );
			cr_photo_contains( '6 photos', $html, 'the real count' );
			cr_photo_assert( 1 === preg_match( '/<span class="cr-zine__postmark" aria-hidden="true">.*Oct 10 2026.*<\/span>/s', $html ), 'decorative postmark repeats only the date' );
			cr_photo_assert( str_ends_with( $html, '</header>body</div>' ), 'the content follows' );

			cr_zine_request( 6, 43, '' );
			$untitled = masthead( '<div>body</div>', array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content' ) ) );
			cr_photo_assert( 1 === preg_match( '/<h1 id="cr-zine-title" class="cr-zine__title"><span class="cr-zine__title-paper">[^<]+<\/span><\/h1>/', $untitled ), 'an untitled post still gets a named H1, no p-name' );

			cr_zine_request( 6 );
			$GLOBALS['cr_zine_formats']                   = array( 42 => 'image' );
			$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
			cr_photo_same( false, holds_h1( false, $GLOBALS['cr_photo_posts'][42] ), 'other formats keep the template H1' );
			cr_photo_same( '<div>body</div>', masthead( '<div>body</div>', array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content' ) ) ), 'and no masthead' );

			cr_zine_request( 6 );
			$GLOBALS['cr_zine_blocks'] = array();
			cr_photo_same( false, holds_h1( false, $GLOBALS['cr_photo_posts'][42] ), 'a gallery-format post with no gallery keeps the template H1' );
		}
	);

	cr_photo_group(
		'packing: every row of the 12- and 6-column grids is full or holds one placed panel, for every count and shape mix',
		static function (): void {
			$mixes = array(
				array( 'landscape', 'portrait', 'square', 'panorama' ),
				array( 'portrait' ),
				array( 'landscape' ),
				array( 'panorama', 'landscape' ),
				array( 'square', 'square', 'portrait' ),
				array( 'portrait', 'landscape', 'landscape', 'panorama', 'square' ),
			);
			foreach ( $mixes as $mix ) {
				foreach ( array( 1, 2, 3, 4, 5, 6, 10, 20, 23 ) as $count ) {
					$shapes = array();
					for ( $n = 0; $n < $count; $n++ ) {
						$shapes[] = $mix[ $n % count( $mix ) ];
					}
					foreach ( array( 12, 6 ) as $cols ) {
						$cells = layout( $shapes, $cols );
						$tag   = $cols . ' cols, ' . $count . ' of ' . implode( '/', $mix );
						cr_photo_same( $count, count( $cells ), $tag . ': every panel placed' );
						$rows = array();
						foreach ( $cells as $cell ) {
							cr_photo_assert( $cell['span'] >= 2 && $cell['span'] <= $cols, $tag . ': span in range' );
							cr_photo_assert( 0 === $cell['start'] || ( $cell['start'] >= 1 && $cell['start'] + $cell['span'] - 1 <= $cols ), $tag . ': start in range' );
							$rows[ $cell['row'] ][] = $cell;
						}
						foreach ( $rows as $row ) {
							$sum = array_sum( array_column( $row, 'span' ) );
							cr_photo_assert( 1 === count( $row ) ? $sum <= $cols : $sum === $cols, $tag . ': a shared row is full (' . $sum . ')' );
						}
					}
				}
			}
			$one = layout( array( 'landscape' ), 12 );
			cr_photo_same( array( 'span' => 8, 'start' => 3, 'row' => 0 ), $one[0], 'one landscape sits centred' );
			$two = layout( array( 'landscape', 'portrait' ), 12 );
			cr_photo_same( 12, $two[0]['span'] + $two[1]['span'], 'a featured landscape and a portrait share the top row' );
			cr_photo_assert( $two[0]['span'] > $two[1]['span'], 'the featured panel stays the larger' );
		}
	);

	cr_photo_group(
		'the zine sheet: tokens, hard shadows, a 12-column desktop grid, 6 on tablets, 1 on phones, focus ring, reduced motion, forced colors',
		static function (): void {
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( CR_PHOTO_THEME_DIR . '/assets/css/cr-gallery-zine.css' ) );
			cr_photo_assert( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b|\b(rgb|rgba|hsl|hsla)\(|filter\s*:|blur\(/i', $css ), 'no raw colour, filter or blur' );
			preg_match_all( '/(?<![\w-])box-shadow\s*:\s*([^;{}]+);/', $css, $shadows );
			foreach ( $shadows[1] as $value ) {
				cr_photo_assert( 'none' === trim( $value ) || 1 === preg_match( '/^(var\(--cr-shadow-hard(-sm|-2)?\)|0 0 0 [67]px var\(--cr-focus-halo\))$/', trim( $value ) ), 'hard shadow or focus halo: ' . $value );
			}
			preg_match_all( '/(?<![\w-])transform\s*:\s*([^;{}]+);/', $css, $turns );
			foreach ( $turns[1] as $value ) {
				cr_photo_assert( 'none' === trim( $value ) || 1 === preg_match( '/^rotate\(var\(--cr-rotate-(1|2|neg|neg2)\)\)$/', trim( $value ) ), 'rotation token: ' . $value );
			}
			preg_match_all( '/(?<![\w-])font-size\s*:\s*([^;{}]+);/', $css, $sizes );
			foreach ( $sizes[1] as $value ) {
				$value = trim( $value );
				$ok    = 1 === preg_match( '/^var\(--cr-text-[a-z0-9]+\)$/', $value ) || ( 1 === preg_match( '/^(\d*\.?\d+)rem$/', $value, $m ) && (float) $m[1] >= 0.75 ) || str_starts_with( $value, 'clamp(' );
				cr_photo_assert( $ok, 'type at or above the floor: ' . $value );
			}
			cr_photo_contains( 'grid-template-columns: repeat(12, minmax(0, 1fr))', $css, 'desktop 12 columns' );
			cr_photo_contains( 'grid-template-columns: repeat(6, minmax(0, 1fr))', $css, 'tablet 6 columns' );
			cr_photo_assert( 1 === preg_match( '/@media \(max-width: 39\.99rem\)\s*\{[^@]*\.cr-zine__frames\s*\{[^{}]*grid-template-columns:\s*1fr;/s', $css ), 'phones one column' );
			cr_photo_contains( 'object-fit: contain', $css, 'no cropping' );
			cr_photo_contains( 'var(--cr-focus-ring)', $css, 'visible focus' );
			cr_photo_contains( '@media (prefers-reduced-motion: reduce)', $css, 'reduced motion' );
			cr_photo_contains( '@media (forced-colors: active)', $css, 'forced colors' );
			cr_photo_assert( 0 === preg_match( '/\.cr-zine__frame--(landscape|portrait|square|panorama)[^{]*:nth-child\(\d+\)\s*\{/', $css ), 'no layout keyed to a fixed image position' );
		}
	);

	cr_photo_finish();
}
