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
	if ( ! function_exists( 'esc_attr__' ) ) {
		function esc_attr__( $text, $domain = null ): string {
			return esc_attr( $text );
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
	use function Courtneyr\Child\GalleryZine\capture;
	use function Courtneyr\Child\GalleryZine\compose;
	use function Courtneyr\Child\GalleryZine\enable_lightbox;
	use function Courtneyr\Child\GalleryZine\holds_h1;
	use function Courtneyr\Child\GalleryZine\start_pass;
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
	function cr_zine_request( int $count, int $id = 42, string $title = 'Blue Hour at Example Lake', bool $linked_third = false, string $kind = 'note' ): array {
		cr_photo_reset( $id, $title );
		$GLOBALS['cr_photo_request']['post_kinds'][ $id ] = array( $kind );
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

	/** Core's render of a gallery block: its image figures (lightbox where the attrs ask) and an optional gallery caption. */
	function cr_zine_core_html( array $gallery, string $caption = '' ): string {
		$figures = '';
		foreach ( $gallery['innerBlocks'] as $image ) {
			$figures .= render_block( $image );
		}
		return '<figure class="wp-block-gallery has-nested-images" data-wp-interactive="core/gallery" data-wp-context="{&quot;galleryId&quot;:&quot;g1&quot;}">' . $figures
			. ( '' !== $caption ? '<figcaption class="blocks-gallery-caption wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';
	}

	/** One gallery through the render pipeline: render_block_data, core's render, capture (9), the projector's output if any (10), the zine (11). */
	function cr_zine_render( array $gallery, string $caption = '', int $id = 42, ?string $projector = null ): string {
		$instance = new WP_Block( array( 'postId' => $id ) );
		$parsed   = enable_lightbox( $gallery );
		$html     = capture( cr_zine_core_html( $parsed, $caption ), $parsed, $instance );
		return zine( $projector ?? $html, $parsed, $instance );
	}

	cr_photo_group(
		'hooks: the zine replaces the first gallery after the plugin projector, holds the H1, prints the masthead, loads its sheet',
		static function (): void {
			cr_photo_assert( cr_photo_hooked( 'render_block_core/gallery', 'Courtneyr\\Child\\GalleryZine\\zine', 11, 3 ), 'zine at 11' );
			cr_photo_assert( cr_photo_hooked( 'render_block_core/gallery', 'Courtneyr\\Child\\GalleryZine\\capture', 9, 3 ), 'core render kept before the projector (10)' );
			cr_photo_assert( cr_photo_hooked( 'render_block_data', 'Courtneyr\\Child\\GalleryZine\\enable_lightbox', 20 ), 'lightbox on before core renders, after core adds data-id' );
			cr_photo_assert( cr_photo_hooked( 'the_content', 'Courtneyr\\Child\\GalleryZine\\start_pass', 1 ), 'each content pass starts fresh' );
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
			// Core's gallery adds data-id to each image's attrs before the render filter runs.
			foreach ( $gallery['innerBlocks'] as $k => $inner ) {
				$gallery['innerBlocks'][ $k ]['attrs']['data-id'] = (string) $inner['attrs']['id'];
			}
			$html = cr_zine_render( $gallery, 'A short gallery caption.' );
			cr_photo_assert( 1 === preg_match( '/^<section class="cr-zine alignwide" aria-label="Photos" data-wp-interactive="core\/gallery" data-wp-context="\{&quot;galleryId&quot;:&quot;g1&quot;\}">/', $html ), 'a region named Photos that carries core\'s gallery lightbox wiring' );
			cr_photo_assert( 1 === preg_match( '/<ol class="cr-zine__frames" role="list">/', $html ), 'an ordered list of panels' );
			cr_photo_same( 6, preg_match_all( '/<li class="cr-zine__frame cr-zine__frame--[a-z]+[^"]*" style="--cr-col:[^"]+">/', $html ), 'six panels, each placed' );
			foreach ( array( 'landscape' => 2, 'portrait' => 2, 'square' => 1, 'panorama' => 1 ) as $shape => $n ) {
				cr_photo_same( $n, preg_match_all( '/class="cr-zine__frame cr-zine__frame--' . $shape . '[ "]/', $html ), $shape . ' from the attachment size' );
			}
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true">01<span class="cr-zine__of">/06</span></span>', $html, 'numbered 01/06' );
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true">06<span class="cr-zine__of">/06</span></span>', $html, 'numbered 06/06' );
			cr_photo_same( 6, substr_count( $html, 'srcset="https://example.test/' ), 'srcset kept' );
			cr_photo_same( 6, preg_match_all( '/alt="Alt \d"/', $html ), 'alt kept' );
			cr_photo_same( 3, substr_count( $html, '<figcaption class="wp-element-caption">Caption ' ), 'captions kept, none invented' );
			cr_photo_same( 6, substr_count( $html, 'data-wp-interactive="core/image"' ), 'core lightbox on unlinked images' );
			cr_photo_same( 6, preg_match_all( '/<img\b[^>]*\bclass="[^"]*\bu-photo\b/', $html ), 'u-photo on every image' );
			cr_photo_same( 6, preg_match_all( '/sizes="\(max-width: 37\.49rem\) (100|50)vw, \(max-width: 64rem\) \d+vw, \d+(\.\d+)?rem"/', $html ), 'every image is sized for its spans on phone, tablet and desktop' );
			cr_photo_contains( '<p class="cr-zine__note cr-zine__note--hand">A short gallery caption.</p>', $html, 'a short gallery caption is the handwritten note' );
			cr_photo_not_contains( 'wp-block-gallery', $html, 'the gallery wrapper is replaced' );
		}
	);

	cr_photo_group(
		'a long gallery caption stays typeset; a linked image keeps its link and gets no lightbox',
		static function (): void {
			$gallery = cr_zine_request( 4, 42, 'Blue Hour at Example Lake', true );
			$long    = 'A longer gallery caption that runs past the handwriting limit so it stays typeset in the body face, the way notes read.';
			$html    = cr_zine_render( $gallery, $long );
			cr_photo_contains( '<p class="cr-zine__note">' . $long . '</p>', $html, 'typeset note' );
			cr_photo_contains( '<a href="https://example.test/703.jpg">', $html, 'the media link stays' );
			cr_photo_same( 3, substr_count( $html, 'data-wp-interactive="core/image"' ), 'no lightbox on the linked image' );
		}
	);

	cr_photo_group(
		'the plugin projector output is replaced too, with its caption kept and its assets dequeued',
		static function (): void {
			$gallery   = cr_zine_request( 5, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$projector = '<div class="wp-block-gallery pkiw-projector" role="region"><div class="pkiw-projector__mount">slides</div><p class="pkiw-projector__note wp-element-caption">From the projector.</p></div>';
			$html      = cr_zine_render( $gallery, '', 42, $projector );
			cr_photo_same( 5, substr_count( $html, 'data-wp-interactive="core/image"' ), 'panels are core\'s figures from before the projector' );
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
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery ), 'a second gallery stays core' );
			start_pass( '' );
			cr_photo_assert( str_starts_with( cr_zine_render( $gallery ), '<section class="cr-zine' ), 'a fresh content pass prints the zine again' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_zine_formats'] = array( 42 => 'image' );
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery ), 'another post format' );

			$gallery = cr_zine_request( 3, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$GLOBALS['cr_zine_formats'] = array();
			cr_photo_assert( str_starts_with( cr_zine_render( $gallery ), '<section class="cr-zine' ), 'a photo post the projector would take gets the zine in any format' );

			$gallery = cr_zine_request( 1, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$GLOBALS['cr_zine_formats'] = array();
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery ), 'a one-image gallery on a photo post outside the gallery format stays core, as the projector leaves it' );

			$gallery = cr_zine_request( 1, 42, 'Blue Hour at Example Lake', false, 'photo' );
			cr_photo_assert( str_starts_with( cr_zine_render( $gallery ), '<section class="cr-zine' ), 'a gallery-format photo post with only a one-image gallery still gets the zine' );

			// A gallery-format photo post: one image, then three. The projector takes the second, so the zine does too.
			$one   = cr_zine_request( 1, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$three = cr_zine_request( 3, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$GLOBALS['cr_zine_blocks'] = array( $one, $three );
			cr_photo_same( cr_zine_core_html( $one ), cr_zine_render( $one ), 'the one-image gallery stays core' );
			cr_photo_same( 3, substr_count( cr_zine_render( $three ), '<li class="cr-zine__frame' ), 'the projector\'s gallery is the zine' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_photo_request']['protected'][42] = true;
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery ), 'password protected' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_photo_request']['the_id'] = 77;
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery, '', 77 ), 'not the queried post' );

			$gallery = cr_zine_request( 3 );
			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( cr_zine_core_html( $gallery ), cr_zine_render( $gallery ), 'not a single' );

			$gallery = cr_zine_request( 3 );
			$short   = '<figure class="wp-block-gallery has-nested-images"><figure class="wp-block-image"><img src="x" alt=""/></figure></figure>';
			cr_photo_same( $short, zine( $short, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'a render that lost figures is left alone' );

			cr_zine_request( 0 );
			$empty = array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '' );
			cr_photo_same( cr_zine_core_html( $empty ), cr_zine_render( $empty ), 'no images' );
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
		'another kind lane that prints its own H1 (chicken board, favorite sheet, video label) keeps it: no masthead, one H1',
		static function (): void {
			cr_zine_request( 6 );
			$block = array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content' ) );
			cr_photo_assert( str_contains( masthead( '<div class="cr-coop">board</div>', $block ), 'cr-zine__masthead' ), 'alone, the zine prints its masthead' );
			$lane = static fn( $holds ): bool => true;
			add_filter( 'courtneyr_child_single_object_holds_h1', $lane, 10, 2 );
			cr_photo_same( '<div class="cr-coop">board</div>', masthead( '<div class="cr-coop">board</div>', $block ), 'the lane keeps the page H1' );
			cr_photo_same( true, holds_h1( false, $GLOBALS['cr_photo_posts'][42] ), 'the template header still steps aside' );
			array_pop( $GLOBALS['cr_photo_hooks'] );
		}
	);

	/** Shapes from letters: L landscape, P portrait, S square, A panorama. */
	function cr_zine_shapes( string $letters ): array {
		$map = array( 'L' => 'landscape', 'P' => 'portrait', 'S' => 'square', 'A' => 'panorama' );
		return array_map( static fn( string $c ): string => $map[ $c ], str_split( $letters ) );
	}

	/** The pattern of each group, in order: "hero,triptych". */
	function cr_zine_signature( array $plan ): string {
		$out  = array();
		$last = -1;
		foreach ( $plan as $item ) {
			if ( $item['group'] !== $last ) {
				$out[] = $item['pattern'];
				$last  = $item['group'];
			}
		}
		return implode( ',', $out );
	}

	/** Rows as CSS auto-placement forms them: a panel whose start is at or before the last panel's end opens a row. */
	function cr_zine_rows( array $plan, string $bp, int $cols ): array {
		$rows = array();
		$end  = PHP_INT_MAX;
		foreach ( $plan as $n => $item ) {
			$cell = $item[ $bp ];
			if ( $cell['start'] <= $end ) {
				$rows[] = array();
			}
			$rows[ count( $rows ) - 1 ][] = $n;
			$end                          = $cell['start'] + $cell['span'] - 1;
		}
		return $rows;
	}

	cr_photo_group(
		'vocabulary: the collection, not the count, picks the pattern, in source order',
		static function (): void {
			$expect = array(
				'L'      => 'solo',
				'P'      => 'solo',
				'A'      => 'banner',
				'LP'     => 'diptych',
				'PP'     => 'diptych',
				'LL'     => 'essay',
				'AL'     => 'stack',
				'LSL'    => 'hero',
				'PPP'    => 'triptych',
				'LPS'    => 'triptych',
				'LLLLLL' => 'hero,triptych',
				'PPPPPP' => 'triptych,triptych',
				'SSSSSS' => 'hero,triptych',
				'LPSALP' => 'triptych,banner,diptych',
				'PPLSLL' => 'triptych,hero',
				'LSSPLL' => 'hero,triptych',
			);
			foreach ( $expect as $letters => $signature ) {
				cr_photo_same( $signature, cr_zine_signature( compose( cr_zine_shapes( $letters ), array_fill( 0, strlen( $letters ), '' ) ) ), $letters );
			}
			cr_photo_same( 'journal', cr_zine_signature( compose( cr_zine_shapes( 'L' ), array( 'A dock at dusk.' ) ) ), 'a captioned single is a field-journal page' );
			$twenty = compose( cr_zine_shapes( 'LPSALPSALPSALPSALPSA' ), array_fill( 0, 20, '' ) );
			cr_photo_assert( str_ends_with( cr_zine_signature( $twenty ), ',contact' ), 'a long gallery closes on a contact sheet' );
			cr_photo_same( 'contact', $twenty[19]['pattern'], 'the tail is the contact sheet' );
			// Uniform landscapes and uniform squares share a skeleton by design (heights follow each picture's ratio); every other family differs.
			$six = array( 'LLLLLL', 'PPPPPP', 'LPSALP', 'PPLSLL', 'LSSPLL' );
			$signatures = array_map( static fn( string $l ): string => md5( serialize( array_map( static fn( array $i ): array => array( $i['pattern'], $i['d'], $i['t'] ), compose( cr_zine_shapes( $l ), array_fill( 0, 6, '' ) ) ) ) ), $six );
			cr_photo_same( 5, count( array_unique( $signatures ) ), 'five six-image shape families, five different compositions' );
			cr_photo_same( compose( cr_zine_shapes( 'LPSALP' ), array_fill( 0, 6, '' ) ), compose( cr_zine_shapes( 'LPSALP' ), array_fill( 0, 6, '' ) ), 'deterministic' );
		}
	);

	cr_photo_group(
		'placement: every panel in order, inside its grid, never overlapping, rows full or balanced, on desktop, tablet and phone',
		static function (): void {
			$mixes = array( 'L', 'P', 'S', 'A', 'LPSA', 'PPLS', 'SSL', 'LLA', 'APPL', 'LSSPLL', 'PSLPSA' );
			foreach ( $mixes as $mix ) {
				foreach ( array( 1, 2, 3, 4, 5, 6, 7, 10, 13, 20, 23 ) as $count ) {
					$letters = substr( str_repeat( $mix, 30 ), 0, $count );
					$plan    = compose( cr_zine_shapes( $letters ), array_fill( 0, $count, '' ) );
					cr_photo_same( range( 0, $count - 1 ), array_keys( $plan ), $letters . ': every panel, in source order' );
					foreach ( array( 'd' => 24, 't' => 12, 'p' => 2 ) as $bp => $cols ) {
						foreach ( $plan as $n => $item ) {
							$c = $item[ $bp ];
							cr_photo_assert( $c['start'] >= 1 && $c['span'] >= 1 && $c['start'] + $c['span'] - 1 <= $cols, "$letters $bp #$n inside the grid" );
						}
						foreach ( cr_zine_rows( $plan, $bp, $cols ) as $r => $row ) {
							$first = $plan[ $row[0] ][ $bp ];
							$last  = $plan[ end( $row ) ][ $bp ];
							if ( 1 === count( $row ) && 'p' !== $bp && $first['span'] < $cols && 1 === ( $first['rows'] ?? 1 ) ) {
								// A panel alone on its row is centred, or is the field-journal page whose note fills the rest.
								$left  = $first['start'] - 1;
								$right = $cols - ( $first['start'] + $first['span'] - 1 );
								$solo  = in_array( $plan[ $row[0] ]['pattern'], array( 'solo', 'journal', 'stack', 'essay' ), true ) || in_array( 'hero-sat', $plan[ $row[0] ]['flags'], true ) || 'contact' === $plan[ $row[0] ]['pattern'] || 'triptych' === $plan[ $row[0] ]['pattern'];
								cr_photo_assert( $solo || abs( $left - $right ) <= 1, "$letters $bp row $r: a lone panel is centred or part of a composed group" );
							}
						}
					}
				}
			}
			$hero = compose( cr_zine_shapes( 'LSL' ), array( '', '', '' ) );
			cr_photo_same( 2, $hero[0]['d']['rows'], 'the hero spans two rows on desktop' );
			cr_photo_assert( $hero[0]['d']['span'] >= 12 && $hero[0]['d']['span'] <= 18, 'the hero takes half to three quarters of the row' );
			cr_photo_same( $hero[0]['d']['span'] + 1, $hero[1]['d']['start'], 'satellites stack beside the hero' );
			cr_photo_same( $hero[0]['d']['span'] + 1, $hero[2]['d']['start'], 'the second satellite under the first' );
			$sq = compose( cr_zine_shapes( 'SSS' ), array( '', '', '' ) );
			$pl = compose( cr_zine_shapes( 'PLL' ), array( '', '', '' ) );
			cr_photo_assert( $sq[0]['d']['span'] > $pl[0]['d']['span'], 'a portrait hero takes fewer columns than a square one beside the same stack height' );
			$even = array_slice( compose( cr_zine_shapes( 'LSLLPS' ), array_fill( 0, 6, '' ) ), 3 );
			cr_photo_assert( '' !== $even[0]['h']['d'] && $even[0]['h']['d'] === $even[1]['h']['d'] && $even[1]['h']['d'] === $even[2]['h']['d'], 'a justified row shares one picture height' );
			cr_photo_assert( 1 === preg_match( '/^min\((calc\([\d.]+ \* \(100cqi \+ 2rem\) - [\d.]+rem\)(, )?){3}\)$/', $even[0]['h']['d'] ), 'the height follows the grid width: ' . $even[0]['h']['d'] );
			cr_photo_same( '', $hero[0]['h']['d'], 'a hero sizes by its columns, not a shared height' );
			$wide = compose( array( 'landscape', 'landscape' ), array( '', '' ), array( 1.5, 1.2 ) );
			cr_photo_assert( $wide[0]['d']['span'] > $wide[1]['d']['span'] || 'essay' === $wide[0]['pattern'], 'measured ratios steer the spans' );
			$pl = compose( cr_zine_shapes( 'PL' ), array( '', '' ) );
			cr_photo_same( 'anchor', $pl[1]['treat'], 'the anchor is the opening group\'s largest picture, not always the first' );
			$six = compose( cr_zine_shapes( 'PPPPPP' ), array_fill( 0, 6, '' ) );
			cr_photo_assert( $six[0]['mt']['d'] !== $six[3]['mt']['d'] - 3 || $six[1]['mt']['d'] !== $six[4]['mt']['d'] - 3, 'a second portrait row answers the first instead of repeating it' );
			$pp = compose( cr_zine_shapes( 'PP' ), array( '', '' ) );
			cr_photo_same( array( 1, 2 ), array( $pp[0]['p']['start'], $pp[1]['p']['start'] ), 'two portraits sit side by side on a phone' );
			$end = compose( cr_zine_shapes( 'LSLPP' ), array_fill( 0, 5, '' ) );
			$rows = cr_zine_rows( $end, 'd', 12 );
			cr_photo_assert( count( end( $rows ) ) > 1, 'a five-image gallery does not end on a lone panel' );
		}
	);

	cr_photo_group(
		'per breakpoint: phones tell a single-column story, tablets open trios on a hero, desktops alternate tight and loose spreads',
		static function (): void {
			// Phones pair only pictures that stay comfortably viewable side by side: two portraits, or contact frames.
			foreach ( array( 'LPSALP', 'PPLSLL', 'SSSSSS', 'PPPPPP', 'LSSPLL', 'LPSALPSALPSALPSALPSA' ) as $letters ) {
				$shapes = cr_zine_shapes( $letters );
				$plan   = compose( $shapes, array_fill( 0, strlen( $letters ), '' ) );
				foreach ( cr_zine_rows( $plan, 'p', 2 ) as $row ) {
					if ( 2 === count( $row ) ) {
						$ok = ( 'portrait' === $shapes[ $row[0] ] && 'portrait' === $shapes[ $row[1] ] ) || 'contact' === $plan[ $row[0] ]['pattern'];
						cr_photo_assert( $ok, "$letters: a phone pair is two portraits or contact frames" );
					}
				}
			}
			// Tablets: a mixed trio opens on a full-width picture, the other two share a row and a height.
			$trio = compose( cr_zine_shapes( 'LPS' ), array( '', '', '' ) );
			cr_photo_same( 12, $trio[0]['t']['span'], 'the trio\'s first picture spans the tablet row' );
			cr_photo_same( 12, $trio[1]['t']['span'] + $trio[2]['t']['span'], 'the other two share the next tablet row' );
			cr_photo_assert( '' === $trio[0]['h']['t'] && '' !== $trio[1]['h']['t'] && $trio[1]['h']['t'] === $trio[2]['h']['t'], 'the tablet pair shares a height; the hero does not' );
			cr_photo_assert( in_array( 'spread', $trio[0]['flags'], true ) && $trio[0]['d']['span'] >= 9, 'an opening mixed trio is a spread led by its first picture' );
			cr_photo_same( 24, $trio[0]['d']['span'] + $trio[1]['d']['span'] + $trio[2]['d']['span'], 'the other two share the rest of the row' );
			cr_photo_same( 'anchor', $trio[0]['treat'], 'the spread anchors on its first picture' );
			$tall = compose( cr_zine_shapes( 'PPL' ), array( '', '', '' ) );
			cr_photo_assert( $tall[0]['d']['span'] < $trio[0]['d']['span'], 'a portrait hero, held to the height cap, takes fewer columns than a landscape one' );
			cr_photo_assert( '' === $trio[0]['h']['d'] && '' !== $trio[1]['h']['d'] && $trio[1]['h']['d'] === $trio[2]['h']['d'], 'and one height' );
			$later = compose( cr_zine_shapes( 'LSLLPS' ), array_fill( 0, 6, '' ) );
			cr_photo_assert( ! in_array( 'spread', $later[3]['flags'], true ), 'only the opening trio is a spread' );
			cr_photo_assert( str_starts_with( $trio[1]['h']['t'], 'min(' ), 'a shared height takes the smallest any picture allows at the real width' );
			$ppp = compose( cr_zine_shapes( 'PPP' ), array( '', '', '' ) );
			cr_photo_same( array( 6, 6 ), array( $ppp[0]['t']['span'], $ppp[1]['t']['span'] ), 'three portraits on a tablet: a pair' );
			cr_photo_assert( $ppp[2]['t']['span'] < 12 && $ppp[2]['t']['span'] >= 6, 'then an inset portrait' );
			// Desktop: the second trio in a gallery is the loose spread, breaking the row line.
			$six = compose( cr_zine_shapes( 'PPPPPP' ), array_fill( 0, 6, '' ) );
			cr_photo_assert( ! in_array( 'loose', $six[0]['flags'], true ) && in_array( 'loose', $six[3]['flags'], true ), 'trios alternate tight and loose' );
			cr_photo_assert( $six[4]['mt']['d'] > $six[3]['mt']['d'] - 3, 'the loose spread\'s second picture drops below the row line' );
			cr_photo_assert( $six[5]['d']['start'] <= $six[4]['d']['start'] + $six[4]['d']['span'] - 1, 'and its third opens a new row' );
			cr_photo_same( '', $six[3]['h']['d'], 'a loose spread does not force a shared height' );
			$lps = compose( cr_zine_shapes( 'LPSLPS' ), array_fill( 0, 6, '' ) );
			cr_photo_same( 'triptych,triptych', cr_zine_signature( $lps ), 'two trios' );
			cr_photo_assert( in_array( 'loose', $lps[3]['flags'], true ), 'the second mixed trio is loose too' );
		}
	);

	cr_photo_group(
		'presentation: one anchor, prints and contact frames by role, at most one taped print, handwriting only for short real captions, row spacing by group',
		static function (): void {
			foreach ( array( 'LPSALP', 'PPLSLL', 'LLLLLL', 'SSSSSS', 'LPSALPSALPSALPSALPSA' ) as $letters ) {
				$count = strlen( $letters );
				$caps  = array();
				for ( $n = 0; $n < $count; $n++ ) {
					$caps[] = 0 === $n % 2 ? 'Caption ' . ( $n + 1 ) : '';
				}
				$plan  = compose( cr_zine_shapes( $letters ), $caps );
				$treat = array_count_values( array_column( $plan, 'treat' ) );
				cr_photo_same( 1, $treat['anchor'], $letters . ': one anchor' );
				cr_photo_assert( ( $treat['taped'] ?? 0 ) <= 1, $letters . ': at most one taped print' );
				foreach ( $plan as $n => $item ) {
					cr_photo_assert( ( 'contact' === $item['pattern'] ) === ( 'contact' === $item['treat'] ), "$letters #$n: contact frames only on the contact sheet" );
					if ( in_array( 'hand', $item['flags'], true ) ) {
						cr_photo_assert( '' !== $caps[ $n ] && in_array( $item['treat'], array( 'print', 'taped', 'bare', 'anchor' ), true ), "$letters #$n: handwriting only on a real caption" );
					}
					cr_photo_assert( 'taped' !== $item['treat'] || 'panorama' !== cr_zine_shapes( $letters )[ $n ], "$letters #$n: a panorama is never tilted" );
				}
				cr_photo_same( 0, $plan[0]['mt']['d'], $letters . ': no space above the first row' );
			}
			$two = compose( cr_zine_shapes( 'LSLPP' ), array_fill( 0, 5, '' ) );
			cr_photo_assert( $two[3]['mt']['d'] > $two[2]['mt']['d'], 'a new group opens with more space than a row inside a group' );
			$long = compose( cr_zine_shapes( 'LS' ), array( '', str_repeat( 'x', 41 ) ) );
			cr_photo_assert( ! in_array( 'hand', $long[1]['flags'], true ), 'a caption over 40 characters stays typeset' );
			$html = cr_zine_render( cr_zine_request( 6 ) );
			cr_photo_same( 1, substr_count( $html, 'class="cr-zine__imprint"' ), 'one date imprint, on the anchor' );
			cr_photo_assert( 1 === preg_match( '/<span class="cr-zine__imprint" aria-hidden="true">[^<]*<\/span><\/figure>/', $html ), 'the imprint sits inside the anchor\'s figure' );
			$gone = cr_zine_request( 3 );
			unset( $GLOBALS['cr_zine_meta'][702] );
			$html2 = cr_zine_render( $gone );
			cr_photo_same( 1, substr_count( $html2, 'cr-zine__frame--missing' ), 'an image whose attachment is gone is marked as a missing frame' );
			cr_photo_contains( 'alt="Alt 2"', $html2, 'its alt text stays' );
			cr_photo_same( 6, preg_match_all( '/style="--cr-col:[^;"]+;--cr-col-t:[^;"]+;--cr-col-p:[^;"]+;--cr-mt:\d+;--cr-mt-t:\d+;--cr-mt-p:\d+(;[^"]*)?"/', $html ), 'each panel carries its place on three grids and its row spacing' );
		}
	);

	cr_photo_group(
		'the zine sheet: tokens, hard shadows, a 12-column desktop grid, 6 on tablets, 2 on phones, focus ring, reduced motion, forced colors',
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
			cr_photo_contains( 'grid-template-columns: repeat(24, minmax(0, 1fr))', $css, 'desktop 24 fine columns' );
			cr_photo_contains( 'grid-template-columns: repeat(12, minmax(0, 1fr))', $css, 'tablet 12 fine columns' );
			cr_photo_assert( 1 === preg_match( '/@media \(max-width: 37\.49rem\)\s*\{[^@]*\.cr-zine__frames\s*\{[^{}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/s', $css ), 'phones two columns' );
			cr_photo_contains( 'object-fit: contain', $css, 'no cropping' );
			cr_photo_contains( 'var(--cr-focus-ring)', $css, 'visible focus' );
			cr_photo_contains( '@media (prefers-reduced-motion: reduce)', $css, 'reduced motion' );
			cr_photo_contains( '@media (forced-colors: active)', $css, 'forced colors' );
			cr_photo_assert( 0 === preg_match( '/\.cr-zine__frame--(landscape|portrait|square|panorama)[^{]*:nth-child\(\d+\)\s*\{/', $css ), 'no layout keyed to a fixed image position' );
		}
	);

	cr_photo_finish();
}
