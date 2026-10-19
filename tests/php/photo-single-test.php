<?php
/**
 * `php tests/php/photo-single-test.php`: the photo single hooks (issue 241)
 * and the Simple Location map alt, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/photo-bootstrap.php';

use function Courtneyr\Child\SinglePhoto\projector_labelledby;
use function Courtneyr\Child\SinglePhoto\scrapbook;
use function Courtneyr\Child\SinglePhoto\framed_print;
use const Courtneyr\Child\SinglePhoto\FRAMES;

if ( ! function_exists( 'wp_dequeue_script' ) ) {
	function wp_dequeue_script( $handle ): void {
		$GLOBALS['cr_photo_dequeued'][] = 'script:' . $handle;
	}
	function wp_dequeue_style( $handle ): void {
		$GLOBALS['cr_photo_dequeued'][] = 'style:' . $handle;
	}
}
if ( ! function_exists( 'wp_enqueue_block_style' ) ) {
	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_photo_block_styles'][] = array( $block, $args );
	}
}

use function Courtneyr\Child\SinglePhoto\title_id;

/** Core render_block stand-in for image blocks: records attrs, prints a figure with its caption. */
if ( ! function_exists( 'render_block' ) ) {
	function render_block( array $block ): string {
		$GLOBALS['cr_photo_rendered'][] = $block;
		$id      = (int) ( $block['attrs']['id'] ?? 0 );
		$caption = preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/s', (string) ( $block['innerHTML'] ?? '' ), $m ) ? '<figcaption class="wp-element-caption">' . $m[1] . '</figcaption>' : '';
		$lightbox = ! empty( $block['attrs']['lightbox']['enabled'] ) ? ' data-wp-interactive="core/image"' : '';
		// The Image block is static: core prints the saved markup, so a className set on attrs at render time never reaches it.
		$extra = preg_match( '/<figure class="[^"]*?( is-style-[^" ]+)/', (string) ( $block['innerHTML'] ?? '' ), $saved ) ? $saved[1] : '';
		return '<figure class="wp-block-image size-large' . $extra . '"' . $lightbox . '><img src="https://example.test/uploads/' . $id . '.jpg" alt="Photo ' . $id . '" class="wp-image-' . $id . '" sizes="(max-width: 1024px) 100vw, 1024px" />' . $caption . '</figure>';
	}
}

/** A parsed core/gallery holding $count image blocks (ids 701 on), every other one captioned. */
function cr_photo_gallery_block( int $count = 3 ): array {
	$inner = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$id      = 701 + $i;
		$caption = 0 === $i % 2 ? '<figcaption class="wp-element-caption">Frame ' . $id . '</figcaption>' : '';
		$inner[] = array( 'blockName' => 'core/image', 'attrs' => array( 'id' => $id, 'sizeSlug' => 'large' ), 'innerBlocks' => array(), 'innerHTML' => '<figure class="wp-block-image size-large"><img src="x" alt="" class="wp-image-' . $id . '"/>' . $caption . '</figure>' );
	}
	return array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => $inner, 'innerHTML' => '' );
}

/** What the plugin's projector hands the theme for that gallery. */
const CR_PHOTO_PROJECTOR = '<div class="wp-block-gallery pkiw-projector" role="region" aria-labelledby="cr-photo-title"><div class="pkiw-projector__mount">slides</div><button class="pkiw-projector__button">Next image</button><p class="pkiw-projector__note wp-element-caption">A day at the <em>lake</em>.</p></div>';

/** The plugin's mount for one loose image: core's figure with the mount class added. */
function cr_photo_loose_mount( string $classes = 'wp-block-image size-large' ): string {
	return '<figure class="' . $classes . ' pkiw-projector__mount"><img src="https://example.test/uploads/901.jpg" alt="A dock" class="wp-image-901 u-photo" loading="eager" /><figcaption class="wp-element-caption">The dock.</figcaption></figure>';
}

/** A photo single request for $id with the template's H1 block. */
function cr_photo_single_request( int $id = 42, string $title = 'Blue Hour at Example Lake' ): WP_Post {
	$post                                  = cr_photo_reset( $id, $title );
	$GLOBALS['cr_photo_request']['single'] = true;
	return $post;
}

/** The core post-title H1 as the single template prints it. */
function cr_photo_h1( string $text = 'Blue Hour at Example Lake' ): string {
	return '<h1 class="wp-block-post-title cr-flank cr-flank--blue single-post__title">' . $text . '</h1>';
}

if ( ! empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
	cr_photo_group(
		'no plugin: the map alt is generic, never the address',
		static function (): void {
			cr_photo_assert( ! function_exists( 'pkiw_get_visible_location_fields' ), 'the stand-in rule is not defined' );
			cr_photo_single_request();
			$GLOBALS['cr_photo_meta'][42]['geo_address'] = '100 Example Avenue, Sampletown';
			$html = \Courtneyr\Child\SlocMapAlt\add_map_alt( '<img class="sloc-map" src="https://example.test/m.png" />' );
			cr_photo_contains( 'alt="Map showing this post\'s location"', html_entity_decode( $html, ENT_QUOTES ), 'generic alt' );
			cr_photo_not_contains( 'Example Avenue', $html, 'no address' );
		}
	);
	cr_photo_finish();
}

cr_photo_group(
	'the hooks are registered',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_core/post-title', 'Courtneyr\\Child\\SinglePhoto\\title_id', 10, 2 ), 'title_id on the post title' );
		cr_photo_assert( cr_photo_hooked( 'pkiw_photo_projector_labelledby', 'Courtneyr\\Child\\SinglePhoto\\projector_labelledby', 10, 2 ), 'the plugin labelledby filter' );
		cr_photo_assert( cr_photo_hooked( 'body_class', 'Courtneyr\\Child\\SinglePhoto\\body_class' ), 'body class' );
		cr_photo_assert( cr_photo_hooked( 'courtneyr_child_editor_parity_kinds', 'Courtneyr\\Child\\SinglePhoto\\editor_parity_kinds' ), 'editor parity registration' );
		cr_photo_assert( ! cr_photo_hooked( 'courtneyr_child_single_object_holds_h1', 'Courtneyr\\Child\\SinglePhoto\\holds_h1' ), 'the template keeps its H1 and header (no object H1)' );
	}
);

cr_photo_group(
	'a titled photo single gives its H1 the id the projector is labelled by',
	static function (): void {
		cr_photo_single_request();
		$html = title_id( cr_photo_h1(), array( 'blockName' => 'core/post-title', 'attrs' => array( 'level' => 1 ) ) );
		cr_photo_assert( 1 === preg_match( '/^<h1\b[^>]*\bid="cr-photo-title"[^>]*>Blue Hour at Example Lake<\/h1>$/', $html ), 'id added, text untouched' );
		cr_photo_contains( 'class="wp-block-post-title cr-flank cr-flank--blue single-post__title"', $html, 'classes untouched' );
		cr_photo_same( 'cr-photo-title', projector_labelledby( '', 42 ), 'labelledby names the H1' );
	}
);

cr_photo_group(
	'the id and labelledby stay out of the way in every other case',
	static function (): void {
		$block = array( 'blockName' => 'core/post-title', 'attrs' => array( 'level' => 1 ) );
		cr_photo_single_request( 42, '' );
		cr_photo_same( cr_photo_h1( 'Photo, September 8, 2026' ), title_id( cr_photo_h1( 'Photo, September 8, 2026' ), $block ), 'untitled: no id' );
		cr_photo_same( '', projector_labelledby( '', 42 ), 'untitled: the projector falls back to aria-label Photo' );

		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
		cr_photo_same( cr_photo_h1(), title_id( cr_photo_h1(), $block ), 'not a photo: no id' );
		cr_photo_same( '', projector_labelledby( '', 42 ), 'not a photo: no labelledby' );

		cr_photo_single_request();
		cr_photo_same( cr_photo_h1(), title_id( cr_photo_h1(), array( 'blockName' => 'core/post-title', 'attrs' => array( 'level' => 2 ) ) ), 'only the H1 block' );
		cr_photo_same( '<h2>Related</h2>', title_id( '<h2>Related</h2>', $block ), 'no h1 in the html' );

		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['queried_id'] = 77;
		cr_photo_same( cr_photo_h1(), title_id( cr_photo_h1(), $block ), 'a post that is not the queried one' );
		cr_photo_same( '', projector_labelledby( '', 42 ), 'a post that is not the queried one: no labelledby' );

		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['single'] = false;
		cr_photo_same( cr_photo_h1(), title_id( cr_photo_h1(), $block ), 'not a singular request' );

		cr_photo_single_request();
		$already = '<h1 id="mine">T</h1>';
		cr_photo_same( $already, title_id( $already, $block ), 'an existing id stays' );
		cr_photo_same( 'earlier', projector_labelledby( 'earlier', 77 ), 'an earlier filter value is passed through for other posts' );
	}
);

cr_photo_group(
	'body class and editor parity',
	static function (): void {
		cr_photo_single_request();
		cr_photo_assert( in_array( 'cr-photo-single', \Courtneyr\Child\SinglePhoto\body_class( array( 'single' ) ), true ), 'photo single class' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
		cr_photo_same( array( 'single' ), \Courtneyr\Child\SinglePhoto\body_class( array( 'single' ) ), 'no class on a note single' );
		$kinds = \Courtneyr\Child\SinglePhoto\editor_parity_kinds(
			array(
				'photo' => array( 'classes' => array( 'single-post', 'kind-photo' ) ),
				'note'  => array( 'classes' => array( 'single-post', 'kind-note' ) ),
			)
		);
		cr_photo_same( array( 'single-post', 'kind-photo', 'cr-photo-single' ), $kinds['photo']['classes'], 'photo classes gain the single paint class' );
		cr_photo_same( array( 'single-post', 'kind-note' ), $kinds['note']['classes'], 'other kinds untouched' );
		cr_photo_same( array( 'note' ), array_keys( \Courtneyr\Child\SinglePhoto\editor_parity_kinds( array( 'note' => array( 'classes' => array() ) ) ) ), 'no photo entry is invented' );
		$again = \Courtneyr\Child\SinglePhoto\editor_parity_kinds( $kinds );
		cr_photo_same( $kinds['photo']['classes'], $again['photo']['classes'], 'idempotent' );
	}
);

cr_photo_group(
	'five photo frames are block styles on the core Image block, painted from their own sheet wherever an image renders',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'init', 'Courtneyr\\Child\\SinglePhoto\\register_frame_styles' ), 'registered on init' );
		$GLOBALS['cr_photo_styles']            = array();
		$GLOBALS['cr_photo_block_styles']      = array();
		\Courtneyr\Child\SinglePhoto\register_frame_styles();
		$names = array_map( static fn( array $s ): string => $s[0] . ' ' . $s[1]['name'], $GLOBALS['cr_photo_styles'] );
		cr_photo_same( array( 'polaroid', 'taped', 'corners', 'postcard', 'film' ), array_keys( FRAMES ), 'the five frames' );
		cr_photo_same( array_map( static fn( string $k ): string => 'core/image cr-photo-' . $k, array_keys( FRAMES ) ), $names, 'one block style each' );
		cr_photo_same( 'core/image', $GLOBALS['cr_photo_block_styles'][0][0] ?? '', 'the frame sheet loads with the Image block' );
		cr_photo_contains( 'assets/css/cr-photo-frames.css', (string) ( $GLOBALS['cr_photo_block_styles'][0][1]['src'] ?? '' ), 'from cr-photo-frames.css' );
	}
);

cr_photo_group(
	'a single-image photo post prints its picture in a frame: the picked style, or one seeded by the post',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_core/image', 'Courtneyr\\Child\\SinglePhoto\\framed_print', 11, 3 ), 'after the plugin mount (10)' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_dequeued'] = array();
		$html = framed_print( cr_photo_loose_mount(), array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 901 ) ), new WP_Block( array( 'postId' => 42 ) ) );
		cr_photo_not_contains( 'pkiw-projector__mount', $html, 'no mount' );
		cr_photo_assert( 1 === preg_match( '/^<figure class="wp-block-image size-large is-style-cr-photo-(polaroid|taped|corners|postcard|film)"/', $html ), 'one frame style on the figure' );
		cr_photo_contains( 'u-photo', $html, 'u-photo kept' );
		cr_photo_contains( 'The dock.', $html, 'caption kept' );
		cr_photo_same( $html, framed_print( cr_photo_loose_mount(), array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 901 ) ), new WP_Block( array( 'postId' => 42 ) ) ), 'stable per post' );
		cr_photo_assert( in_array( 'style:pkiw-photo-projector', $GLOBALS['cr_photo_dequeued'], true ), 'the mount styles are dequeued' );

		$picked = framed_print( cr_photo_loose_mount( 'wp-block-image size-large is-style-cr-photo-film' ), array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 901, 'className' => 'is-style-cr-photo-film' ) ), new WP_Block( array( 'postId' => 42 ) ) );
		cr_photo_same( 1, substr_count( $picked, 'is-style-cr-photo-' ), 'a picked frame is kept, not doubled' );
		cr_photo_contains( 'is-style-cr-photo-film', $picked, 'the picked frame wins' );

		$seen = array();
		for ( $id = 300; $id < 340; $id++ ) {
			cr_photo_single_request( $id );
			preg_match( '/is-style-cr-photo-([a-z]+)/', framed_print( cr_photo_loose_mount(), array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 901 ) ), new WP_Block( array( 'postId' => $id ) ) ), $m );
			$seen[ $m[1] ?? '' ] = true;
		}
		cr_photo_assert( count( $seen ) >= 4 && ! isset( $seen[''] ), 'the seed spreads over the frames' );
	}
);

cr_photo_group(
	'the framed print leaves everything else alone',
	static function (): void {
		$block = array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 901 ) );
		cr_photo_single_request();
		$plain = '<figure class="wp-block-image"><img src="x" alt="" /></figure>';
		cr_photo_same( $plain, framed_print( $plain, $block, new WP_Block( array( 'postId' => 42 ) ) ), 'an image the plugin did not mount' );
		cr_photo_reset();
		cr_photo_same( cr_photo_loose_mount(), framed_print( cr_photo_loose_mount(), $block, new WP_Block( array( 'postId' => 42 ) ) ), 'not a single' );
		cr_photo_single_request();
		cr_photo_same( cr_photo_loose_mount(), framed_print( cr_photo_loose_mount(), $block, new WP_Block( array( 'postId' => 77 ) ) ), 'not the queried post' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['protected'][42] = true;
		cr_photo_same( cr_photo_loose_mount(), framed_print( cr_photo_loose_mount(), $block, new WP_Block( array( 'postId' => 42 ) ) ), 'password protected' );
	}
);

cr_photo_group(
	'a gallery photo single is a scrapbook page: mixed frames, the gallery caption as a note, right-sized images, core lightbox',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_core/gallery', 'Courtneyr\\Child\\SinglePhoto\\scrapbook', 11, 3 ), 'scrapbook runs after the projector (10)' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_rendered'] = array();
		$GLOBALS['cr_photo_dequeued'] = array();
		$gallery = cr_photo_gallery_block( 5 );
		$gallery['innerBlocks'][1]['attrs']['className'] = 'is-style-cr-photo-postcard';
		$gallery['innerBlocks'][1]['innerHTML']          = str_replace( 'wp-block-image size-large', 'wp-block-image size-large is-style-cr-photo-postcard', $gallery['innerBlocks'][1]['innerHTML'] );
		$html = scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 42 ) ) );
		cr_photo_assert( 1 === preg_match( '/^<div class="cr-scrapbook"><ul class="cr-scrapbook__photos" role="list" aria-labelledby="cr-photo-title">/', $html ), 'a page holding one list, labelled by the H1' );
		cr_photo_same( 5, substr_count( $html, '<li class="cr-scrapbook__print">' ), 'every picture is on the page' );
		cr_photo_same( 5, preg_match_all( '/<figure class="wp-block-image size-large is-style-cr-photo-(polaroid|taped|corners|postcard|film)"/', $html ), 'every picture in a frame' );
		cr_photo_assert( preg_match_all( '/is-style-cr-photo-([a-z]+)/', $html, $m ) && count( array_unique( $m[1] ) ) >= 3, 'frames are mixed on one page' );
		cr_photo_assert( 1 === preg_match( '/<li class="cr-scrapbook__print"><figure class="wp-block-image size-large is-style-cr-photo-[a-z]+"[^>]*>.*?<\/li><li class="cr-scrapbook__print"><figure class="wp-block-image size-large is-style-cr-photo-postcard"/s', $html ), 'the second print keeps its picked postcard frame, alone' );
		cr_photo_same( 5, preg_match_all( '/<img\b[^>]*\bclass="[^"]*\bu-photo\b/', $html ), 'every picture keeps u-photo' );
		cr_photo_same( 5, substr_count( $html, 'data-wp-interactive="core/image"' ), 'every print opens in the core lightbox' );
		cr_photo_same( 5, substr_count( $html, 'sizes="(max-width: 40rem) 90vw, 32rem"' ), 'images sized for the print, not 1024px' );
		cr_photo_contains( '<p class="cr-scrapbook__note">A day at the <em>lake</em>.</p>', $html, 'the gallery caption is a note on the page' );
		cr_photo_not_contains( 'pkiw-projector', $html, 'no projector, no buttons' );
		cr_photo_assert( in_array( 'script:pkiw-photo-projector', $GLOBALS['cr_photo_dequeued'], true ) && in_array( 'style:pkiw-photo-projector', $GLOBALS['cr_photo_dequeued'], true ), 'projector script and style dequeued' );
		cr_photo_same( $html, scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'stable per post' );

		cr_photo_single_request( 43, '' );
		$untitled = scrapbook( CR_PHOTO_PROJECTOR, cr_photo_gallery_block( 2 ), new WP_Block( array( 'postId' => 43 ) ) );
		cr_photo_assert( 1 === preg_match( '/<ul class="cr-scrapbook__photos" role="list" aria-label="Photos">/', $untitled ), 'an untitled post names the list' );
	}
);

cr_photo_group(
	'the scrapbook leaves everything else alone',
	static function (): void {
		$gallery = cr_photo_gallery_block( 3 );
		cr_photo_single_request();
		$plain = '<figure class="wp-block-gallery">plain</figure>';
		$GLOBALS['cr_photo_dequeued'] = array();
		cr_photo_same( $plain, scrapbook( $plain, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'a gallery the projector did not take' );
		cr_photo_same( CR_PHOTO_PROJECTOR, scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 77 ) ) ), 'a gallery of a post that is not the queried one' );
		cr_photo_same( array(), $GLOBALS['cr_photo_dequeued'], 'nothing dequeued when nothing was replaced' );
		cr_photo_reset();
		cr_photo_same( CR_PHOTO_PROJECTOR, scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'not a single' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
		cr_photo_same( CR_PHOTO_PROJECTOR, scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'not a photo post' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_request']['protected'][42] = true;
		cr_photo_same( CR_PHOTO_PROJECTOR, scrapbook( CR_PHOTO_PROJECTOR, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'password protected' );
		cr_photo_single_request();
		cr_photo_same( CR_PHOTO_PROJECTOR, scrapbook( CR_PHOTO_PROJECTOR, array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '' ), new WP_Block( array( 'postId' => 42 ) ) ), 'no image blocks to print' );
	}
);

cr_photo_group(
	'the Simple Location map alt names the address only when the street is visible',
	static function (): void {
		cr_photo_single_request();
		$map = '<a target="_blank" href=""><img class="sloc-map" src="https://example.test/m.png" /></a>';
		$GLOBALS['cr_photo_meta'][42]['geo_address'] = '100 Example Avenue, Sampletown';

		$GLOBALS['cr_photo_visible'][42] = array( 'street' => true );
		cr_photo_contains( 'alt="Map showing 100 Example Avenue, Sampletown"', \Courtneyr\Child\SlocMapAlt\add_map_alt( $map ), 'street visible: the address' );

		$GLOBALS['cr_photo_visible'][42] = array( 'street' => false );
		$html = \Courtneyr\Child\SlocMapAlt\add_map_alt( $map );
		cr_photo_contains( 'alt="Map showing this post\'s location"', html_entity_decode( $html, ENT_QUOTES ), 'street hidden: generic alt' );
		cr_photo_not_contains( 'Example Avenue', $html, 'street hidden: no address' );
		cr_photo_not_contains( '<a ', $html, 'the empty-href wrapper still goes' );

		$GLOBALS['cr_photo_meta'][42]['geo_address'] = '';
		$GLOBALS['cr_photo_visible'][42]             = array( 'street' => true );
		cr_photo_contains( 'alt="Map showing this post\'s location"', html_entity_decode( \Courtneyr\Child\SlocMapAlt\add_map_alt( $map ), ENT_QUOTES ), 'no address stored: generic alt' );

		$existing = '<img class="sloc-map" alt="Mine" src="x" />';
		cr_photo_same( $existing, \Courtneyr\Child\SlocMapAlt\add_map_alt( $existing ), 'an existing alt stays' );
		cr_photo_same( '<p>no map</p>', \Courtneyr\Child\SlocMapAlt\add_map_alt( '<p>no map</p>' ), 'content without a map is untouched' );
	}
);

cr_photo_group(
	'without the plugin the map alt never names the address',
	static function (): void {
		$child = proc_open(
			array( PHP_BINARY, __FILE__, '--no-plugin' ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		cr_photo_assert( is_resource( $child ), 'could launch the no-plugin child' );
		$out  = stream_get_contents( $pipes[1] );
		$err  = stream_get_contents( $pipes[2] );
		$code = proc_close( $child );
		cr_photo_same( 0, $code, 'no-plugin child exit code (' . trim( $out . $err ) . ')' );
	}
);

cr_photo_finish();
