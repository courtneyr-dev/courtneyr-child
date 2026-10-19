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
use function Courtneyr\Child\SinglePhoto\title_id;

/** Core render_block stand-in for image blocks: records attrs, prints a figure with its caption. */
if ( ! function_exists( 'render_block' ) ) {
	function render_block( array $block ): string {
		$GLOBALS['cr_photo_rendered'][] = $block;
		$id      = (int) ( $block['attrs']['id'] ?? 0 );
		$caption = preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/s', (string) ( $block['innerHTML'] ?? '' ), $m ) ? '<figcaption class="wp-element-caption">' . $m[1] . '</figcaption>' : '';
		$lightbox = ! empty( $block['attrs']['lightbox']['enabled'] ) ? ' data-wp-interactive="core/image"' : '';
		return '<figure class="wp-block-image size-large"' . $lightbox . '><img src="https://example.test/uploads/' . $id . '.jpg" alt="Photo ' . $id . '" class="wp-image-' . $id . '" />' . $caption . '</figure>';
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
const CR_PHOTO_PROJECTOR = '<div class="wp-block-gallery pkiw-projector" role="region" aria-labelledby="cr-photo-title"><div class="pkiw-projector__mount">slides</div><button class="pkiw-projector__button">Next image</button></div>';

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
	'a gallery photo single is a scrapbook spread of every picture, each with core lightbox, u-photo and its caption',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_core/gallery', 'Courtneyr\\Child\\SinglePhoto\\scrapbook', 11, 3 ), 'scrapbook runs after the projector (10)' );
		cr_photo_single_request();
		$GLOBALS['cr_photo_rendered'] = array();
		$html = scrapbook( CR_PHOTO_PROJECTOR, cr_photo_gallery_block( 3 ), new WP_Block( array( 'postId' => 42 ) ) );
		cr_photo_assert( 1 === preg_match( '/^<ul class="cr-scrapbook" role="list" aria-labelledby="cr-photo-title">/', $html ), 'one list, labelled by the H1' );
		cr_photo_same( 3, substr_count( $html, '<li class="cr-scrapbook__print cr-scrapbook__print--tilt-' ), 'every picture is a print' );
		cr_photo_same( 3, preg_match_all( '/<img\b[^>]*\bclass="[^"]*\bu-photo\b/', $html ), 'every picture keeps u-photo' );
		cr_photo_same( 3, substr_count( $html, 'data-wp-interactive="core/image"' ), 'every print opens in the core lightbox' );
		foreach ( $GLOBALS['cr_photo_rendered'] as $block ) {
			cr_photo_assert( true === ( $block['attrs']['lightbox']['enabled'] ?? null ), 'lightbox asked of core for image ' . ( $block['attrs']['id'] ?? '?' ) );
		}
		cr_photo_contains( 'Frame 701', $html, 'captions kept' );
		cr_photo_contains( 'Frame 703', $html, 'captions kept' );
		cr_photo_not_contains( 'pkiw-projector', $html, 'no projector, no buttons' );
		cr_photo_not_contains( '<button', $html, 'no carousel controls' );
		cr_photo_same( $html, scrapbook( CR_PHOTO_PROJECTOR, cr_photo_gallery_block( 3 ), new WP_Block( array( 'postId' => 42 ) ) ), 'tilts are stable per post' );

		cr_photo_single_request( 43, '' );
		$untitled = scrapbook( CR_PHOTO_PROJECTOR, cr_photo_gallery_block( 2 ), new WP_Block( array( 'postId' => 43 ) ) );
		cr_photo_assert( 1 === preg_match( '/^<ul class="cr-scrapbook" role="list" aria-label="Photos">/', $untitled ), 'an untitled post names the list' );
	}
);

cr_photo_group(
	'the scrapbook leaves everything else alone',
	static function (): void {
		$gallery = cr_photo_gallery_block( 3 );
		cr_photo_single_request();
		$plain = '<figure class="wp-block-gallery">plain</figure>';
		cr_photo_same( $plain, scrapbook( $plain, $gallery, new WP_Block( array( 'postId' => 42 ) ) ), 'a gallery the projector did not take' );
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
