<?php
/**
 * `php tests/php/photo-stream-test.php`: the photo Stream slide mount
 * (issue 241) and the booth strip's photo exemption, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/photo-bootstrap.php';

use function Courtneyr\Child\StreamPhoto\slide_mount_card;

/** The plugin's titled photo Stream card, with room for a caption that carries a link. */
function cr_photo_stream_card( string $caption = 'The dock after sunset.', string $title = 'Blue Hour at Example Lake' ): string {
	return '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><div class="pk-body"><span class="pk-kindlabel">Photo</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">' . $title . '</a></h2><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="https://example.test/m.jpg" alt="" loading="lazy" />' . ( '' === $caption ? '' : '<p class="pk-media__caption">' . $caption . '</p>' ) . '</div></div></article>';
}

/** Put the harness on a Stream request and run the adapter. */
function cr_photo_mount( ?string $html = null, array $block = array(), int $id = 42 ): string {
	$GLOBALS['cr_photo_request']['stream'] = true;
	$GLOBALS['cr_photo_request']['the_id'] = $id;
	return slide_mount_card( $html ?? cr_photo_stream_card(), $block, new WP_Block( array( 'postId' => $id ) ) );
}

if ( ! empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
	cr_photo_group(
		'no plugin: the slide mount still dresses the card and reads no facts',
		static function (): void {
			cr_photo_assert( ! function_exists( 'PKIW\\kind_facts' ), 'the stand-in reader is not defined' );
			cr_photo_reset();
			$html = cr_photo_mount();
			cr_photo_contains( 'cr-slide-mount', $html, 'mount class' );
		}
	);
	cr_photo_finish();
}

cr_photo_group(
	'the adapter is hooked at 10 on the stream card and the booth strip is still at 10',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamPhoto\\slide_mount_card', 10, 3 ), 'slide_mount_card at 10' );
		cr_photo_assert( cr_photo_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamGallery\\booth_strip_card', 10, 3 ), 'booth strip hook unchanged' );
	}
);

cr_photo_group(
	'a photo card on the Stream becomes a slide mount with its own rotation key',
	static function (): void {
		cr_photo_reset();
		$input = cr_photo_stream_card();
		$html  = cr_photo_mount( $input );
		cr_photo_assert( 1 === preg_match( '/^<article\b(?=[^>]*\bclass="pk-card pk-card--stream pk-card--photo k-photo h-entry cr-slide-mount")(?=[^>]*\bdata-cr-rotate="[0-3]")[^>]*>/', $html ), 'root keeps the plugin classes in order, gains cr-slide-mount and one rotate key' );
		cr_photo_assert( 1 === preg_match( '/<div\b(?=[^>]*\bclass="pk-media pk-media--stream")(?=[^>]*\bdata-cr-cover-fallback)[^>]*>/', $html ), 'the media is marked for the failed-image fallback' );
		foreach ( array( '<h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Blue Hour at Example Lake</a></h2>', '<span class="pk-kindlabel">Photo</span>', '<time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time>', '<p class="pk-media__caption">The dock after sunset.</p>' ) as $kept ) {
			cr_photo_contains( $kept, $html, 'kept byte-for-byte' );
		}
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one link' );
		cr_photo_not_contains( 'pk-excerpt', $html, 'no excerpt' );
		cr_photo_not_contains( 'cr-booth', $html, 'no booth strip' );
		cr_photo_not_contains( 'Read more', $html, 'no read more' );
	}
);

cr_photo_group(
	'the rotation key is stable per post and spreads over the four tokens',
	static function (): void {
		$keys = array();
		for ( $id = 200; $id < 260; $id++ ) {
			cr_photo_reset( $id );
			$first  = cr_photo_mount( null, array(), $id );
			$second = cr_photo_mount( null, array(), $id );
			cr_photo_assert( 1 === preg_match( '/data-cr-rotate="([0-3])"/', $first, $m ), 'key for ' . $id );
			cr_photo_same( $first, $second, 'stable for ' . $id );
			$keys[ $m[1] ] = true;
		}
		cr_photo_same( array( '0', '1', '2', '3' ), ( static function ( array $k ): array { $k = array_map( 'strval', array_keys( $k ) ); sort( $k ); return $k; } )( $keys ), 'all four rotation keys occur' );
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
		cr_photo_contains( 'cr-slide-mount', $html, 'mount class' );
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
		cr_photo_same( $input, slide_mount_card( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'not a Stream surface' );

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
		cr_photo_same( $input, cr_photo_mount( $input, array( 'attrs' => array( 'className' => 'is-style-cr-photo-frame' ) ) ), 'archive frames are not Stream mounts' );

		cr_photo_reset();
		$not_photo_card = str_replace( 'k-photo', 'k-note', $input );
		cr_photo_same( $not_photo_card, cr_photo_mount( $not_photo_card ), 'a card whose root is not k-photo' );

		cr_photo_reset();
		cr_photo_same( '', cr_photo_mount( '' ), 'empty html' );
		cr_photo_same( '<div class="pk-card k-photo"></div>', cr_photo_mount( '<div class="pk-card k-photo"></div>' ), 'no article root' );
		cr_photo_same( $input, cr_photo_mount( $input, array(), 999 ), 'unknown post' );
	}
);

cr_photo_group(
	'the booth strip skips photo posts of any format and keeps its other posts',
	static function (): void {
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['stream']        = true;
		$GLOBALS['cr_photo_request']['format'][42]    = 'gallery';
		$card = '<article class="pk-card pk-card--stream"><div class="pk-media pk-media--stream"><img src="x" alt="" /></div><div class="pk-meta"><a href="y">More</a></div></article>';
		cr_photo_same( $card, \Courtneyr\Child\StreamGallery\booth_strip_card( $card, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'photo + gallery format: unchanged' );
		$source = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/inc/stream-gallery.php' );
		$format = strpos( $source, "'gallery' !== get_post_format( \$post )" );
		$kind   = strpos( $source, "has_term( 'photo', 'kind', \$post )" );
		$anchor = strpos( $source, '$media_open = ' );
		cr_photo_assert( false !== $format && false !== $kind && false !== $anchor && $format < $kind && $kind < $anchor, 'the photo check sits after the format check and before the splice' );
	}
);

cr_photo_group(
	'without the plugin the mount adapter reads nothing from it',
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
