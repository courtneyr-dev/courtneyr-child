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
	if ( ! function_exists( 'wp_parse_list' ) ) {
		function wp_parse_list( $input_list ): array {
			return array_values( array_filter( explode( ' ', str_replace( ',', ' ', (string) $input_list ) ), 'strlen' ) );
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
	use function Courtneyr\Child\GalleryZine\interactive_styles;
	use function Courtneyr\Child\GalleryZine\start_pass;
	use function Courtneyr\Child\GalleryZine\masthead;
	use function Courtneyr\Child\GalleryZine\reset_state;
	use function Courtneyr\Child\GalleryZine\zine;
	use function Courtneyr\Child\GalleryZine\count_reactions;
	use function Courtneyr\Child\GalleryZine\ephemera;
	use function Courtneyr\Child\GalleryZine\flavor;
	use function Courtneyr\Child\GalleryZine\held;
	use function Courtneyr\Child\GalleryZine\hold_reactions;
	use function Courtneyr\Child\GalleryZine\label_counts;
	use function Courtneyr\Child\GalleryZine\sendoff;

	const CR_ZINE_SHAPES = array(
		701 => array( 1600, 1200 ),
		702 => array( 1200, 1600 ),
		703 => array( 1600, 1600 ),
		704 => array( 1600, 640 ),
	);

	const CR_ZINE_INTERACTIVE_STYLES = array(
		'lightbox-slideshow',
		'before-after-pairs',
		'filter-tags',
		'lookbook-hotspots',
		'card-deck-swipe',
		'photo-essay-scroll',
		'comparison-pairs',
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
		$class_name = $gallery['attrs']['className'] ?? '';
		$classes    = 'wp-block-gallery has-nested-images' . ( is_string( $class_name ) && '' !== $class_name ? ' ' . esc_attr( $class_name ) : '' );
		return '<figure class="' . $classes . '" data-wp-interactive="core/gallery" data-wp-context="{&quot;galleryId&quot;:&quot;g1&quot;}">' . $figures
			. ( '' !== $caption ? '<figcaption class="blocks-gallery-caption wp-element-caption">' . $caption . '</figcaption>' : '' ) . '</figure>';
	}

	/** Set the gallery's block style class. */
	function cr_zine_styled( array $gallery, string $class ): array {
		$gallery['attrs']['className'] = $class;
		return $gallery;
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
		'interactive gallery styles mirror PFBT\'s className gate and keep the constant\'s order',
		static function (): void {
			foreach ( CR_ZINE_INTERACTIVE_STYLES as $slug ) {
				$block = array( 'attrs' => array( 'className' => 'foo is-style-' . $slug . ' bar' ) );
				cr_photo_same( array( $slug ), interactive_styles( $block ), $slug );
			}
			cr_photo_same( array(), interactive_styles( array( 'attrs' => array() ) ), 'no className' );
			cr_photo_same( array(), interactive_styles( array( 'attrs' => array( 'className' => 'is-style-masonry-cascade' ) ) ), 'a non-interactive PFBT style' );
			cr_photo_same( array(), interactive_styles( array( 'attrs' => array( 'className' => 'is-style-lightbox-slideshow-x my-is-style-filter-tags' ) ) ), 'substrings are not classes' );
			cr_photo_same(
				array( 'lightbox-slideshow', 'filter-tags' ),
				interactive_styles( array( 'attrs' => array( 'className' => 'is-style-filter-tags is-style-lightbox-slideshow' ) ) ),
				'two classes return in constant order'
			);
		}
	);

	cr_photo_group(
		'each interactive gallery style stays on a wrapper around the zine panels only',
		static function (): void {
			foreach ( CR_ZINE_INTERACTIVE_STYLES as $slug ) {
				$gallery = cr_zine_styled( cr_zine_request( 4 ), 'is-style-' . $slug );
				$html    = cr_zine_render( $gallery, 'A short gallery caption.' );
				$opening = '<div class="wp-block-gallery cr-zine__gallery is-style-' . $slug . '"><ol class="cr-zine__frames" role="list">';
				cr_photo_contains( $opening, $html, $slug . ' wrapper' );
				cr_photo_assert( str_starts_with( $html, '<section class="cr-zine alignwide" aria-label="Photos"' ), $slug . ' section' );
				$open_at    = strpos( $html, $opening );
				$close_at   = strpos( $html, '</ol></div>' );
				$last_panel = false !== $close_at ? strrpos( substr( $html, 0, $close_at ), '</li>' ) : false;
				cr_photo_assert( false !== $open_at && false !== $close_at && false !== $last_panel && $last_panel < $close_at, $slug . ' wrapper closes after the panels' );
				$wrapped = substr( $html, (int) $open_at, (int) $close_at - (int) $open_at );
				cr_photo_same( 4, substr_count( $wrapped, '<figure class="wp-block-image' ), $slug . ' has four figures inside the wrapper' );
				cr_photo_assert( strpos( $html, '<p class="cr-zine__note' ) > $close_at, $slug . ' note follows the wrapper' );
				cr_photo_assert( strpos( $html, '<div class="cr-zine__keepsakes"' ) > $close_at, $slug . ' keepsakes follow the wrapper' );
			}
		}
	);

	cr_photo_group(
		'the lightbox-slideshow module owns styled images while author and unstyled lightbox choices stay intact',
		static function (): void {
			$gallery = cr_zine_styled( cr_zine_request( 4 ), 'is-style-lightbox-slideshow' );
			$html    = cr_zine_render( $gallery );
			cr_photo_not_contains( 'lightbox-trigger', $html, 'no core lightbox trigger' );
			cr_photo_not_contains( 'data-wp-interactive="core/image"', $html, 'no core image interactivity' );
			cr_photo_not_contains( 'pfbt-lightbox', $html, 'no browser-built PFBT markup from PHP' );
			foreach ( range( 1, 4 ) as $number ) {
				cr_photo_assert( 1 === preg_match( '/<img\b[^>]*alt="Alt ' . $number . '"[^>]*class="[^"]*\bu-photo\b/', $html ), 'image ' . $number . ' keeps its alt and u-photo class' );
			}

			$explicit                                      = cr_zine_styled( cr_zine_request( 1 ), 'is-style-lightbox-slideshow' );
			$explicit['innerBlocks'][0]['attrs']['lightbox'] = array( 'enabled' => false );
			$parsed                                        = enable_lightbox( $explicit );
			cr_photo_same( array( 'enabled' => false ), $parsed['innerBlocks'][0]['attrs']['lightbox'], 'the author\'s explicit lightbox attr is preserved' );

			foreach ( CR_ZINE_INTERACTIVE_STYLES as $slug ) {
				$styled = cr_zine_styled( cr_zine_request( 1 ), 'is-style-' . $slug );
				$parsed = enable_lightbox( $styled );
				cr_photo_assert( ! isset( $parsed['innerBlocks'][0]['attrs']['lightbox'] ), $slug . ' does not get core lightbox attrs' );
			}

			$plain  = cr_zine_request( 1 );
			$parsed = enable_lightbox( $plain );
			cr_photo_same( array( 'enabled' => true ), $parsed['innerBlocks'][0]['attrs']['lightbox'], 'an unstyled unlinked image still gets core lightbox' );
		}
	);

	cr_photo_group(
		'two interactive classes stay together on the wrapper in constant order',
		static function (): void {
			$gallery = cr_zine_styled( cr_zine_request( 4 ), 'is-style-filter-tags is-style-lightbox-slideshow' );
			$html    = cr_zine_render( $gallery );
			cr_photo_contains( '<div class="wp-block-gallery cr-zine__gallery is-style-lightbox-slideshow is-style-filter-tags"><ol', $html, 'both wrapper classes in constant order' );
		}
	);

	cr_photo_group(
		'unstyled zine markup stays byte-identical',
		static function (): void {
			// These hashes pin the markup and must be regenerated when the zine's markup changes on purpose.
			$plain = cr_zine_request( 6 );
			foreach ( $plain['innerBlocks'] as $k => $inner ) {
				$plain['innerBlocks'][ $k ]['attrs']['data-id'] = (string) $inner['attrs']['id'];
			}
			$plain_html = cr_zine_render( $plain, 'A short gallery caption.' );
			cr_photo_same( '37a1a29f34e20c36b6fef4ef85f5697a5fe00c9f61dc747aaf028bcf8d44c4f2', hash( 'sha256', $plain_html ), 'plain6 hash' );

			$linked      = cr_zine_request( 4, 42, 'Blue Hour at Example Lake', true );
			$linked_html = cr_zine_render( $linked, 'A longer gallery caption that runs past the handwriting limit so it stays typeset in the body face, the way notes read.' );
			cr_photo_same( 'f86e567ef0466810fbeb51302a52676080e78fec992a1ac115b55fd950804b13', hash( 'sha256', $linked_html ), 'linked4 hash' );

			$photo      = cr_zine_request( 5, 42, 'Blue Hour at Example Lake', false, 'photo' );
			$photo_html = cr_zine_render( $photo );
			cr_photo_same( '080323d583f4f93ef6ebd75540ad11597a472096549c85ebd24b285fa4457071', hash( 'sha256', $photo_html ), 'photo5 hash' );

			$masonry = cr_zine_styled( cr_zine_request( 6 ), 'is-style-masonry-cascade' );
			foreach ( $masonry['innerBlocks'] as $k => $inner ) {
				$masonry['innerBlocks'][ $k ]['attrs']['data-id'] = (string) $inner['attrs']['id'];
			}
			$masonry_html = cr_zine_render( $masonry, 'A short gallery caption.' );
			cr_photo_same( '37a1a29f34e20c36b6fef4ef85f5697a5fe00c9f61dc747aaf028bcf8d44c4f2', hash( 'sha256', $masonry_html ), 'a non-interactive style keeps the plain6 hash' );
			foreach ( array( $plain_html, $linked_html, $photo_html ) as $html ) {
				cr_photo_not_contains( 'cr-zine__gallery', $html, 'no wrapper on an unstyled scenario' );
			}
		}
	);

	cr_photo_group(
		'the interactive wrapper is layout-neutral and gives the PFBT trigger the zine focus colour',
		static function (): void {
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( CR_PHOTO_THEME_DIR . '/assets/css/cr-gallery-zine.css' ) );
			cr_photo_assert( 1 === preg_match( '/\.cr-zine-single \.cr-zine \.cr-zine__gallery\s*\{[^{}]*display:\s*block;[^{}]*margin:\s*0;[^{}]*padding:\s*0;/s', $css ), 'the wrapper is a zero-spacing block' );
			cr_photo_assert( 1 === preg_match( '/\.cr-zine__gallery\.is-style-lightbox-slideshow\s*\{[^{}]*--pfbt-lightbox-focus:\s*var\(--cr-focus-halo\);/s', $css ), 'the PFBT trigger uses the zine focus halo' );
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
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true" role="presentation">01<span class="cr-zine__of">/06</span></span>', $html, 'numbered 01/06' );
			cr_photo_contains( '<span class="cr-zine__num" aria-hidden="true" role="presentation">06<span class="cr-zine__of">/06</span></span>', $html, 'numbered 06/06' );
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
			cr_photo_assert( 1 === preg_match( '/<span class="cr-zine__postmark" aria-hidden="true" role="presentation">.*Oct 10 2026.*<\/span>/s', $html ), 'decorative postmark repeats only the date' );
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
			cr_photo_assert( 1 === preg_match( '/<span class="cr-zine__imprint" aria-hidden="true" role="presentation">[^<]*<\/span><\/figure>/', $html ), 'the imprint sits inside the anchor\'s figure' );
			$gone = cr_zine_request( 3 );
			unset( $GLOBALS['cr_zine_meta'][702] );
			$html2 = cr_zine_render( $gone );
			cr_photo_same( 1, substr_count( $html2, 'cr-zine__frame--missing' ), 'an image whose attachment is gone is marked as a missing frame' );
			cr_photo_contains( 'alt="Alt 2"', $html2, 'its alt text stays' );
			cr_photo_same( 6, preg_match_all( '/style="--cr-col:[^;"]+;--cr-col-t:[^;"]+;--cr-col-p:[^;"]+;--cr-mt:\d+;--cr-mt-t:\d+;--cr-mt-p:\d+(;[^"]*)?"/', $html ), 'each panel carries its place on three grids and its row spacing' );
		}
	);

	cr_photo_group(
		'keepsakes: handwritten flavor beside the sheet, drawn from attributes so no text joins the post, its microformats or the accessibility tree',
		static function (): void {
			$a = flavor( 41 );
			$b = flavor( 42 );
			cr_photo_same( $a, flavor( 41 ), 'a post keeps its flavor' );
			cr_photo_assert( $a !== $b, 'neighbouring posts differ' );
			cr_photo_assert( '' !== $a['note'] && '' !== $a['sticker'] && count( $a['words'] ) >= 4, 'a note, a sticker and a word list' );
			$doodles = array_unique( array_map( static fn( int $id ): string => flavor( $id )['doodle'], range( 1, 12 ) ) );
			cr_photo_same( array(), array_diff( $doodles, array( 'star', 'circle', 'film', 'arrow' ) ), 'doodles come from a pool with no subject in it' );
			cr_photo_assert( count( $doodles ) >= 3, 'posts get different doodles' );
			$html = cr_zine_render( cr_zine_request( 6 ) );
			cr_photo_assert( 1 === preg_match( '/<div class="cr-zine__keepsakes" aria-hidden="true" role="presentation">(.*?)<\/div><\/section>$/s', $html, $m ), 'one aria-hidden keepsakes block, role=presentation like every zine ornament, closes the section' );
			$block = $m[1] ?? '';
			cr_photo_same( '', trim( strip_tags( $block ) ), 'no text nodes: the flavor lives in data-text only' );
			foreach ( array( 'hand', 'sticker', 'list' ) as $piece ) {
				cr_photo_contains( 'class="cr-zine__' . $piece . '"', $block, $piece );
			}
			cr_photo_contains( 'class="cr-zine__doodle cr-zine__doodle--' . flavor( 6 )['doodle'] . '"', $block, 'the post\'s doodle' );
			cr_photo_assert( ! str_contains( $block, 'cr-zine__trees' ), 'no pines on every gallery' );
			cr_photo_assert( 1 === preg_match( '/class="cr-zine__list" data-text="[^"&]+ · [^"&]+"/', $block ), 'the word list is one attribute, words on one line' );
			$layer = ephemera( '<div class="entry-content">body</div>', array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content' ) ) );
			foreach ( array( 'map', 'torn', 'band' ) as $piece ) {
				cr_photo_contains( '<span class="cr-zine-ephemera__' . $piece . '"></span>', $layer, 'the page layer has the ' . $piece );
			}
			cr_photo_assert( ! str_contains( $layer, 'cr-zine-ephemera__postmark' ), 'the sticker is the page\'s second stamp, so the margin postmark goes' );
			$css = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/assets/css/cr-gallery-zine.css' );
			cr_photo_assert( substr_count( $css, 'content: attr(data-text)' ) >= 3, 'CSS draws the flavor from its attribute' );
			cr_photo_assert( 1 === preg_match( '/@media \(max-width: 37\.49rem\)\s*\{[^@]*\.cr-zine__keepsakes[^{]*:is\([^)]*cr-zine__doodle[^)]*\)[^{]*\{[^{}]*display:\s*none;/s', $css ), 'phones drop the doodle' );
			cr_photo_assert( 1 === preg_match( '/@media \(min-width: 64rem\)\s*\{[^@]*\.cr-zine__list\s*\{[^{}]*display:\s*block;/s', $css ), 'the word list shows on every desktop, in the band above the sheet' );
			cr_photo_assert( ! str_contains( $css, '92rem' ) && ! str_contains( $css, 'cr-zine-trees' ), 'no wide-only list, no pine mask' );
			cr_photo_assert( 1 === preg_match( '/@media \(max-width: 74\.99rem\)\s*\{[^@]*\.cr-zine__masthead::after\s*\{[^{}]*content:\s*"";/s', $css ), 'below the wide margins the map peeks out under the masthead' );
			cr_photo_assert( 1 === preg_match( '/\.cr-zine-ephemera__torn\s*\{[^{}]*height:\s*clamp\([^{}]*clip-path:\s*polygon\(/s', $css ), 'the start-edge strip is a short scrap with a torn top' );
			cr_photo_assert( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{[^@]*\.cr-zine__keepsakes[^{]*\{[^{}]*display:\s*none;/s', $css ), 'forced colors drop the keepsakes' );
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

	cr_photo_group(
		'page ephemera: one decorative layer per zine single, varied by post, out of the accessibility tree',
		static function (): void {
			cr_photo_assert( cr_photo_hooked( 'render_block_core/post-content', 'Courtneyr\\Child\\GalleryZine\\ephemera', 21, 2 ), 'ephemera after the masthead on the post content' );
			$block = array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content wp-block-post-content' ) );

			cr_zine_request( 6, 42 );
			$html = ephemera( '<div class="entry-content">body</div>', $block );
			cr_photo_assert( 1 === preg_match( '/^<div class="entry-content">body<div class="cr-zine-ephemera cr-zine-ephemera--[abc]" aria-hidden="true" role="presentation">(<span class="cr-zine-ephemera__[a-z]+"><\/span>)+<\/div><\/div>$/', $html ), 'one aria-hidden layer of empty spans at the end of the content' );
			cr_photo_assert( substr_count( $html, '<span class="cr-zine-ephemera__' ) >= 6, 'at least six artifacts' );
			cr_photo_not_contains( 'tabindex', $html, 'nothing focusable' );

			$variants = array();
			foreach ( array( 42, 43, 44 ) as $id ) {
				cr_zine_request( 6, $id );
				preg_match( '/cr-zine-ephemera--([abc])/', ephemera( '<div>body</div>', $block ), $m );
				$variants[] = $m[1] ?? '';
			}
			cr_photo_same( 3, count( array_unique( $variants ) ), 'three neighbouring posts get three different arrangements' );
			cr_zine_request( 6, 42 );
			cr_photo_same( $html, ephemera( '<div class="entry-content">body</div>', $block ), 'the same post always gets the same arrangement' );

			cr_photo_same( '<div>x</div>', ephemera( '<div>x</div>', array( 'blockName' => 'core/post-content', 'attrs' => array() ) ), 'only the single template content' );

			cr_zine_request( 6 );
			$GLOBALS['cr_zine_formats']                   = array( 42 => 'image' );
			$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
			cr_photo_same( '<div>body</div>', ephemera( '<div>body</div>', $block ), 'no zine, no ephemera' );

			cr_zine_request( 6 );
			$lane = static fn( $holds ): bool => true;
			add_filter( 'courtneyr_child_single_object_holds_h1', $lane, 10, 2 );
			cr_photo_same( '<div>body</div>', ephemera( '<div>body</div>', $block ), 'another kind lane keeps its own page' );
			array_pop( $GLOBALS['cr_photo_hooks'] );
		}
	);

	/** IndieBlocks' facepile as the single template saves it: a heading and one avatar pile. */
	function cr_zine_facepile(): array {
		return array(
			'blockName'    => 'indieblocks/facepile',
			'attrs'        => array( 'className' => 'single-post__facepile' ),
			'innerBlocks'  => array(
				array( 'blockName' => 'core/heading', 'attrs' => array( 'level' => 2 ), 'innerBlocks' => array(), 'innerHTML' => '<h2 class="wp-block-heading">Likes, Bookmarks, and Reposts</h2>', 'innerContent' => array( '<h2 class="wp-block-heading">Likes, Bookmarks, and Reposts</h2>' ) ),
				array( 'blockName' => 'indieblocks/facepile-content', 'attrs' => array( 'avatarSize' => 3 ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
			),
			'innerHTML'    => "\n<div class=\"wp-block-indieblocks-facepile single-post__facepile\">\n\t\n\t\n</div>\n",
			'innerContent' => array( "\n<div class=\"wp-block-indieblocks-facepile single-post__facepile\">\n\t", null, "\n\t", null, "\n</div>\n" ),
		);
	}

	cr_photo_group(
		'reactions on a zine single are a count row: one count-only IndieBlocks block per kind, each labelled for screen readers',
		static function (): void {
			cr_photo_assert( cr_photo_hooked( 'render_block_data', 'Courtneyr\\Child\\GalleryZine\\count_reactions' ), 'count row before IndieBlocks renders' );
			cr_photo_assert( cr_photo_hooked( 'render_block_indieblocks/facepile-content', 'Courtneyr\\Child\\GalleryZine\\label_counts' ), 'count labels' );

			cr_zine_request( 6 );
			$parsed = count_reactions( cr_zine_facepile() );
			$inner  = $parsed['innerBlocks'];
			cr_photo_same( 'core/heading', $inner[0]['blockName'], 'the heading stays first' );
			cr_photo_same( array( 'like', 'bookmark', 'repost' ), array_map( static fn( array $b ): string => $b['attrs']['type'][0] ?? '', array_slice( $inner, 1, 3 ) ), 'like, bookmark and repost, in that order' );
			cr_photo_same( cr_zine_facepile()['innerBlocks'][1], $inner[4], 'the avatar pile follows the counts unchanged, so each response keeps its h-cite link' );
			cr_photo_same( 5, count( $inner ), 'heading, three counts, avatar pile' );
			foreach ( array_slice( $inner, 1, 3 ) as $count ) {
				cr_photo_same( 'indieblocks/facepile-content', $count['blockName'], 'each is IndieBlocks\' own block' );
				cr_photo_same( true, $count['attrs']['countOnly'] && $count['attrs']['icons'], 'count only, with its icon' );
				cr_photo_same( 1, count( $count['attrs']['type'] ), 'one kind per count' );
			}
			cr_photo_same( count( $inner ), count( array_filter( $parsed['innerContent'], 'is_null' ) ), 'one placeholder per inner block' );
			cr_photo_same( "\n<div class=\"wp-block-indieblocks-facepile single-post__facepile\">\n\t", $parsed['innerContent'][0], 'the wrapper markup is kept' );
			cr_photo_same( "\n</div>\n", end( $parsed['innerContent'] ), 'and closed' );
			cr_photo_same( $parsed, count_reactions( $parsed ), 'a count row is left as it is' );

			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( cr_zine_facepile(), count_reactions( cr_zine_facepile() ), 'other pages keep the avatar pile' );
			cr_zine_request( 6 );
			$GLOBALS['cr_zine_formats']                   = array( 42 => 'image' );
			$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
			cr_photo_same( cr_zine_facepile(), count_reactions( cr_zine_facepile() ), 'other singles keep the avatar pile' );
			$heading = cr_zine_facepile()['innerBlocks'][0];
			cr_zine_request( 6 );
			cr_photo_same( $heading, count_reactions( $heading ), 'other blocks untouched' );

			$svg = '<svg class="icon indieblocks-icon-%1$s" aria-hidden="true" role="img"><use href="#indieblocks-icon-%1$s" xlink:href="#indieblocks-icon-%1$s"></use></svg>';
			$out = label_counts( '<div class="wp-block-indieblocks-facepile-content"><div class="indieblocks-count">' . sprintf( $svg, 'like' ) . ' 24</div></div>' );
			cr_photo_contains( sprintf( $svg, 'like' ) . ' 24<span class="screen-reader-text"> likes</span></div>', $out, 'the number says what it counts' );
			cr_photo_contains( '<span class="screen-reader-text"> bookmark</span>', label_counts( '<div class="indieblocks-count">' . sprintf( $svg, 'bookmark' ) . ' 1</div>' ), 'singular' );
			cr_photo_contains( '<span class="screen-reader-text"> reposts</span>', label_counts( '<div class="indieblocks-count">' . sprintf( $svg, 'repost' ) . ' 3</div>' ), 'reposts' );
			$avatars = '<ul class="indieblocks-avatar-size-3"><li class="h-cite p-like"></li></ul>';
			cr_photo_same( $avatars, label_counts( $avatars ), 'the avatar pile is untouched' );
		}
	);

	cr_photo_group(
		'the send-off: reactions sit beside the newsletter band on a zine single, only when there are reactions',
		static function (): void {
			cr_photo_assert( cr_photo_hooked( 'render_block_core/group', 'Courtneyr\\Child\\GalleryZine\\hold_reactions', 20, 2 ), 'reactions held after the empty-group drop (10)' );
			cr_photo_assert( cr_photo_hooked( 'render_block_core/template-part', 'Courtneyr\\Child\\GalleryZine\\sendoff', 10, 2 ), 'send-off on the newsletter part' );
			$group      = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__syndication' ) );
			$part       = array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'newsletter-cta' ) );
			$reactions  = '<div class="wp-block-group single-post__syndication"><div class="wp-block-indieblocks-facepile single-post__facepile"><h2>Likes</h2><div class="wp-block-indieblocks-facepile-content"><div class="indieblocks-count">24</div></div></div></div>';
			$newsletter = '<div class="wp-block-template-part"><div class="cr-newsletter-banner">Field notes</div></div>';

			cr_zine_request( 6 );
			held( true );
			cr_photo_same( '', hold_reactions( $reactions, $group ), 'held back from its own spot' );
			cr_photo_same( '<div class="cr-zine-sendoff alignwide">' . $reactions . $newsletter . '</div>', sendoff( $newsletter, $part ), 'printed beside the newsletter, reactions first' );
			cr_photo_same( $newsletter, sendoff( $newsletter, $part ), 'printed once' );

			held( true );
			$avatars = str_replace( '<div class="indieblocks-count">24</div>', '<ul><li class="h-cite p-like"></li></ul>', $reactions );
			cr_photo_same( '', hold_reactions( $avatars, $group ), 'an avatar pile counts as reactions too' );
			sendoff( $newsletter, $part );

			held( true );
			$empty = '<div class="wp-block-group single-post__syndication"><div class="wp-block-indieblocks-facepile single-post__facepile"><h2>Likes</h2></div></div>';
			cr_photo_same( $empty, hold_reactions( $empty, $group ), 'no reactions: left to the template' );
			cr_photo_same( '', hold_reactions( '', $group ), 'nothing to hold' );
			cr_photo_same( $newsletter, sendoff( $newsletter, $part ), 'and the newsletter stands alone' );

			held( true );
			cr_photo_same( $reactions, hold_reactions( $reactions, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__meta' ) ) ), 'other groups untouched' );
			hold_reactions( $reactions, $group );
			cr_photo_same( '<div>footer</div>', sendoff( '<div>footer</div>', array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) ), 'other parts untouched' );
			cr_photo_contains( 'cr-zine-sendoff', sendoff( $newsletter, $part ), 'still waiting for the newsletter' );

			held( true );
			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( $reactions, hold_reactions( $reactions, $group ), 'other pages untouched' );
			cr_photo_same( $newsletter, sendoff( $newsletter, $part ), 'their newsletter too' );

			$template = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/templates/single.html' );
			cr_photo_assert( 1 === preg_match( '/<!-- wp:group \{[^\n]*"className":"single-post__syndication".*?<!-- \/wp:indieblocks\/facepile -->\s*<\/div>\s*<!-- \/wp:group -->\s*<!-- wp:template-part \{"slug":"newsletter-cta"\} \/-->/s', $template ), 'the single template prints the newsletter part right after the reactions group, so nothing held is lost' );
		}
	);

	cr_photo_group(
		'page ephemera CSS: margins only on wide screens, fewer on phones, hidden in forced colors, never in the way',
		static function (): void {
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( CR_PHOTO_THEME_DIR . '/assets/css/cr-gallery-zine.css' ) );
			cr_photo_assert( 1 === preg_match( '/\.cr-zine-ephemera\s*\{[^{}]*pointer-events:\s*none;/', $css ), 'the layer never takes a click' );
			cr_photo_assert( 1 === preg_match( '/\.cr-zine-ephemera\s*\{[^{}]*z-index:\s*-1;/', $css ), 'the layer sits behind the content' );
			cr_photo_assert( 1 === preg_match( '/@media \(min-width: 75rem\)\s*\{[^@]*\.cr-zine-ephemera__scrap/s', $css ), 'margin artifacts wait for real margins' );
			cr_photo_assert( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{[^@]*\.cr-zine-ephemera[^{]*\{[^{}]*display:\s*none;/s', $css ), 'forced colors drop the ephemera' );
			cr_photo_assert( 1 === preg_match( '/@media \(min-width: 48rem\)\s*\{[^@]*\.cr-zine-sendoff\s*\{[^{}]*grid-template-columns:/s', $css ), 'the send-off sits side by side from tablets up' );
			cr_photo_assert( 0 === preg_match( '/url\([^)]*%23[0-9a-fA-F]{3,8}/', $css ), 'no colour hidden in an SVG: masks take token colours' );
		}
	);

	cr_photo_finish();
}
