<?php
/**
 * `php tests/php/photo-stream-test.php`: the photo Stream polaroid and the
 * booth strip for gallery-format photo posts, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/photo-bootstrap.php';

use function Courtneyr\Child\StreamPhoto\polaroid_card;
use function Courtneyr\Child\StreamGallery\booth_strip_card;

/* WordPress stand-ins the booth strip needs on its splice path. */
if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( string $content ): array {
		preg_match_all( '/<!-- wp:image \{"id":(\d+)\} -->/', $content, $found );
		return array_map( static fn( string $id ): array => array( 'blockName' => 'core/image', 'attrs' => array( 'id' => (int) $id ), 'innerBlocks' => array() ), $found[1] );
	}
	function get_attached_media( $type, $post = 0 ): array {
		return array();
	}
	function has_post_thumbnail( $post = null ): bool {
		return false;
	}
	function get_post_thumbnail_id( $post = null ): int {
		return 0;
	}
	function get_post_field( $field, $post = null, $context = 'display' ): string {
		return '';
	}
	function _n( $single, $plural, $number, $domain = null ): string {
		return 1 === (int) $number ? $single : $plural;
	}
	function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
		return 1788876000;
	}
	function wp_date( $format, $timestamp = null, $timezone = null ): string {
		return gmdate( $format, (int) $timestamp );
	}
	function get_the_category( $post_id = false ): array {
		return array();
	}
}

/** The plugin's titled photo Stream card, with room for a caption that carries a link. */
function cr_photo_stream_card( string $caption = 'The dock after sunset.', string $title = 'Blue Hour at Example Lake' ): string {
	return '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><div class="pk-body"><span class="pk-kindlabel">Photo</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">' . $title . '</a></h2><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="https://example.test/m.jpg" alt="" loading="lazy" />' . ( '' === $caption ? '' : '<p class="pk-media__caption">' . $caption . '</p>' ) . '</div></div></article>';
}

/** Put the harness on a Stream request and run the adapter. */
function cr_photo_mount( ?string $html = null, array $block = array(), int $id = 42 ): string {
	$GLOBALS['cr_photo_request']['stream'] = true;
	$GLOBALS['cr_photo_request']['the_id'] = $id;
	return polaroid_card( $html ?? cr_photo_stream_card(), $block, new WP_Block( array( 'postId' => $id ) ) );
}

if ( ! empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
	cr_photo_group(
		'no plugin: the polaroid still dresses the card and reads no facts',
		static function (): void {
			cr_photo_assert( ! function_exists( 'PKIW\\kind_facts' ), 'the stand-in reader is not defined' );
			cr_photo_reset();
			$html = cr_photo_mount();
			cr_photo_contains( 'cr-polaroid', $html, 'polaroid class' );
		}
	);
	cr_photo_finish();
}

cr_photo_group(
	'the polaroid adapter runs at 11, after the booth strip at 10',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamPhoto\\polaroid_card', 11, 3 ), 'polaroid_card at 11' );
		cr_photo_assert( cr_photo_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamGallery\\booth_strip_card', 10, 3 ), 'booth strip hook unchanged' );
	}
);

cr_photo_group(
	'a photo card on the Stream becomes a polaroid and keeps the collage rotation',
	static function (): void {
		cr_photo_reset();
		$input = cr_photo_stream_card();
		$html  = cr_photo_mount( $input );
		cr_photo_assert( 1 === preg_match( '/^<article\\b(?=[^>]*\\bclass="pk-card pk-card--stream pk-card--photo k-photo h-entry cr-polaroid")[^>]*>/', $html ), 'root keeps the plugin classes in order and gains cr-polaroid' );
		cr_photo_not_contains( 'data-cr-rotate', $html, 'no own rotation key: the collage rotates it' );
		cr_photo_not_contains( 'cr-slide-mount', $html, 'no slide mount' );
		cr_photo_assert( 1 === preg_match( '/<div\\b(?=[^>]*\\bclass="pk-media pk-media--stream")(?=[^>]*\\bdata-cr-cover-fallback)[^>]*>/', $html ), 'the media is marked for the failed-image fallback' );
		foreach ( array( '<h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Blue Hour at Example Lake</a></h2>', '<span class="pk-kindlabel">Photo</span>', '<time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time>', '<p class="pk-media__caption">The dock after sunset.</p>' ) as $kept ) {
			cr_photo_contains( $kept, $html, 'kept byte-for-byte' );
		}
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one link' );
		cr_photo_not_contains( 'cr-booth', $html, 'no booth strip on a single picture' );
	}
);

cr_photo_group(
	'caption links are stripped so the card keeps one tab stop',
	static function (): void {
		cr_photo_reset();
		$html = cr_photo_mount( cr_photo_stream_card( 'Photo by <a href="https://example.com/credit" rel="nofollow">Ana Example</a>, <em>edited</em>.' ) );
		cr_photo_contains( '<p class="pk-media__caption">Photo by Ana Example, <em>edited</em>.</p>', $html, 'link text stays, emphasis stays' );
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one rendered link' );
		cr_photo_same( 0, cr_photo_visible_negative_tabindex( $html ), 'no visible tabindex -1 link' );
		cr_photo_not_contains( 'example.com/credit', $html, 'external href gone' );
		cr_photo_same( 1, substr_count( cr_photo_mount( cr_photo_stream_card( 'A <a href="#a">one</a> and <a href="#b">two</a>.' ) ), '<a ' ), 'two caption links, still one anchor' );
		$no_caption = cr_photo_mount( cr_photo_stream_card( '' ) );
		cr_photo_not_contains( 'pk-media__caption', $no_caption, 'no caption element when there is none' );
	}
);

cr_photo_group(
	'an untitled card keeps its Photo, <date> link and gains no p-name',
	static function (): void {
		cr_photo_reset( 43, '' );
		$input = '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><div class="pk-body"><div class="pk-caption"><h2 class="pk-title"><a class="u-url" href="https://example.test/photo/43/">Photo, <time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time></a></h2></div></div></article>';
		$html  = cr_photo_mount( $input, array(), 43 );
		cr_photo_contains( 'cr-polaroid', $html, 'polaroid class' );
		cr_photo_not_contains( 'p-name', $html, 'no p-name' );
		cr_photo_same( 1, substr_count( $html, 'dt-published' ), 'one dt-published' );
		cr_photo_not_contains( 'data-cr-cover-fallback', $html, 'no media, no fallback mark' );
	}
);

cr_photo_group(
	'unchanged cases return the input byte for byte',
	static function (): void {
		cr_photo_reset();
		$input = cr_photo_stream_card();
		$GLOBALS['cr_photo_request']['stream'] = false;
		cr_photo_same( $input, polaroid_card( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'not a Stream surface' );

		cr_photo_reset();
		$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
		cr_photo_same( $input, cr_photo_mount(), 'not a photo post' );

		cr_photo_reset();
		$GLOBALS['cr_photo_request']['protected'][42] = true;
		cr_photo_same( $input, cr_photo_mount(), 'password protected' );

		cr_photo_reset();
		$protected = '<article class="pk-card pk-card--stream pk-card--protected k-photo h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Protected: Blue Hour</a></h2></article>';
		cr_photo_same( $protected, cr_photo_mount( $protected ), 'the protected card' );

		cr_photo_reset();
		cr_photo_same( $input, cr_photo_mount( $input, array( 'attrs' => array( 'className' => 'is-style-cr-photo-frame' ) ) ), 'archive frames are not Stream polaroids' );

		cr_photo_reset();
		$not_photo_card = str_replace( 'k-photo', 'k-note', $input );
		cr_photo_same( $not_photo_card, cr_photo_mount( $not_photo_card ), 'a card whose root is not k-photo' );

		cr_photo_reset();
		cr_photo_same( '', cr_photo_mount( '' ), 'empty html' );
		cr_photo_same( '<div class="pk-card k-photo"></div>', cr_photo_mount( '<div class="pk-card k-photo"></div>' ), 'no article root' );
		cr_photo_same( $input, cr_photo_mount( $input, array(), 999 ), 'unknown post' );
	}
);

/** A gallery-format post whose body lists $count images (attachments 701 on). */
function cr_photo_gallery_post( int $id, int $count, array $kinds = array( 'photo' ) ): void {
	cr_photo_reset( $id );
	$content = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$content .= '<!-- wp:image {"id":' . ( 701 + $i ) . '} --><figure class="wp-block-image"><img src="x" alt="" /></figure><!-- /wp:image -->';
	}
	$GLOBALS['cr_photo_posts'][ $id ]->post_content     = $content;
	$GLOBALS['cr_photo_request']['stream']              = true;
	$GLOBALS['cr_photo_request']['format'][ $id ]       = 'gallery';
	$GLOBALS['cr_photo_request']['post_kinds'][ $id ]   = $kinds;
}

/** Booth strip, then the polaroid adapter, as the filter priorities run them. */
function cr_photo_stream_pipeline( string $html, int $id ): string {
	$instance = new WP_Block( array( 'postId' => $id ) );
	return polaroid_card( booth_strip_card( $html, array(), $instance ), array(), $instance );
}

cr_photo_group(
	'a gallery-format photo post becomes a photo booth print: frames only, the title as its caption, one link',
	static function (): void {
		cr_photo_gallery_post( 42, 5 );
		$html = cr_photo_stream_pipeline( cr_photo_stream_card(), 42 );
		cr_photo_contains( '<ol class="cr-booth__frames">', $html, 'the strip replaced the lead image' );
		cr_photo_same( 4, substr_count( $html, '<li class="cr-booth__frame">' ), 'four frames' );
		cr_photo_contains( 'pk-card--gallery', $html, 'the card is named a gallery' );
		cr_photo_not_contains( 'cr-polaroid', $html, 'the strip is not also a polaroid' );
		cr_photo_not_contains( 'https://example.test/m.jpg', $html, 'the lead image is gone' );
		cr_photo_contains( '<h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Blue Hour at Example Lake</a></h2>', $html, 'title link kept' );
		foreach ( array( 'cr-booth__footer', 'cr-booth__stamps', 'cr-booth__count', 'cr-booth__more', 'cr-booth__note', 'more frame' ) as $gone ) {
			cr_photo_not_contains( $gone, $html, 'a print carries no ' . $gone );
		}
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one link per Stream item (W3-G2)' );
		cr_photo_same( 1, substr_count( $html, 'dt-published' ), 'one dt-published' );
		cr_photo_assert( 1 === preg_match( '/<div class="pk-caption">.*<\/div><div class="pk-media pk-media--stream cr-booth"><ol class="cr-booth__frames">/s', $html ), 'the caption (title and date) stays in the card beside the frames' );
	}
);

cr_photo_group(
	'an image-format photo post is never a strip, and a protected one is untouched',
	static function (): void {
		cr_photo_gallery_post( 42, 5 );
		$GLOBALS['cr_photo_request']['format'][42] = 'image';
		$html = cr_photo_stream_pipeline( cr_photo_stream_card(), 42 );
		cr_photo_not_contains( 'cr-booth', $html, 'image format: no strip' );
		cr_photo_contains( 'cr-polaroid', $html, 'image format: polaroid' );

		cr_photo_gallery_post( 42, 5 );
		$GLOBALS['cr_photo_request']['protected'][42] = true;
		$protected = '<article class="pk-card pk-card--stream pk-card--protected k-photo h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Protected: Blue Hour</a></h2></article>';
		cr_photo_same( $protected, cr_photo_stream_pipeline( $protected, 42 ), 'protected card' );
		cr_photo_same( cr_photo_stream_card(), booth_strip_card( cr_photo_stream_card(), array(), new WP_Block( array( 'postId' => 42 ) ) ), 'protected post, plugin card: no strip' );
	}
);

cr_photo_group(
	'a gallery post of another kind keeps its strip and its more-link',
	static function (): void {
		cr_photo_gallery_post( 42, 6, array( 'note' ) );
		$card = '<article class="pk-card pk-card--stream"><div class="pk-body"><h2 class="pk-title"><a href="https://example.test/photo/42/">Roll</a></h2></div><div class="pk-media pk-media--stream"><img src="x" alt="" /></div><div class="pk-meta"><a href="y">More</a></div></article>';
		$html = booth_strip_card( $card, array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_photo_contains( '<ol class="cr-booth__frames">', $html, 'strip' );
		cr_photo_contains( 'cr-booth__more-link" href="https://example.test/photo/42/"', $html, 'more-link stays a link for other kinds' );
	}
);

cr_photo_group(
	'without the plugin the polaroid adapter reads nothing from it',
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
