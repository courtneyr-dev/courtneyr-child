<?php
/**
 * `php tests/php/photo-archive-test.php`: the photo archive frame adapter
 * (issue 241) without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/photo-bootstrap.php';

use function Courtneyr\Child\Photo\frame_card;

if ( ! empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
	cr_photo_group(
		'no plugin: kind_facts is absent and the frame adapter returns its input',
		static function (): void {
			cr_photo_assert( ! function_exists( 'PKIW\\kind_facts' ), 'the stand-in reader is not defined' );
			$html = '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Title</a></h3></article>';
			$GLOBALS['cr_photo_request']['kind_archive'] = 'photo';
			cr_photo_same( $html, \Courtneyr\Child\Photo\frame_card( $html, array( 'attrs' => array( 'headingLevel' => 3 ) ), new WP_Block( array( 'postId' => 42 ) ) ), 'frame_card' );
		}
	);
	cr_photo_finish();
}

/** The plugin's photo Stream card, as render_photo_stream_card() prints it for a titled post. */
function cr_photo_plugin_card( string $title = 'Blue Hour at Example Lake' ): string {
	return '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><div class="pk-body"><span class="pk-kindlabel">Photo</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">' . $title . '</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-08T14:00:00+00:00">September 8, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="https://example.test/m.jpg" alt="" loading="lazy" /><p class="pk-media__caption">The dock after sunset.</p></div></div></article>';
}

/** Run the adapter on the archive for post $id with the plugin's card. */
function cr_photo_frame( int $id = 42, array $attrs = array( 'headingLevel' => 3 ), ?string $html = null ): string {
	$GLOBALS['cr_photo_request']['kind_archive'] = 'photo';
	$GLOBALS['cr_photo_request']['the_id']       = $id;
	return frame_card( $html ?? cr_photo_plugin_card(), array( 'attrs' => $attrs ), new WP_Block( array( 'postId' => $id ) ) );
}

cr_photo_group(
	'the adapter and its style are hooked',
	static function (): void {
		cr_photo_assert( cr_photo_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Photo\\frame_card', 10, 3 ), 'frame_card on the stream card at 10' );
		cr_photo_assert( cr_photo_hooked( 'init', 'Courtneyr\\Child\\Photo\\register_frame_style' ), 'frame style registered on init' );
		cr_photo_assert( cr_photo_hooked( 'enqueue_block_assets', 'Courtneyr\\Child\\Photo\\enqueue_styles' ), 'sheet enqueued on enqueue_block_assets' );
		cr_photo_assert( cr_photo_hooked( 'body_class', 'Courtneyr\\Child\\Photo\\body_class' ), 'body class filter' );
		\Courtneyr\Child\Photo\register_frame_style();
		cr_photo_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_photo_styles'][0][0] ?? '', 'style is for the stream card' );
		cr_photo_same( 'cr-photo-frame', $GLOBALS['cr_photo_styles'][0][1]['name'] ?? '', 'style name' );
		cr_photo_assert( ! cr_photo_hooked( 'courtneyr_child_kind_archive_page_sizes', 'Courtneyr\\Child\\Photo\\page_size' ), 'no page-size filter: linesPerPage sets 12' );
	}
);

cr_photo_group(
	'a titled frame has one link, an h3 p-name, a decorative image and the counter span',
	static function (): void {
		cr_photo_reset();
		$html = cr_photo_frame();
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one rendered link' );
		cr_photo_same( 0, cr_photo_visible_negative_tabindex( $html ), 'no visible tabindex -1 link' );
		cr_photo_assert( 1 === preg_match( '/<h3\b[^>]*class="[^"]*\bp-name\b[^"]*"[^>]*><a class="u-url" href="https:\/\/example\.test\/photo\/42\/">Blue Hour at Example Lake<\/a><\/h3>/', $html ), 'h3.p-name > a.u-url with the title' );
		cr_photo_contains( 'alt=""', $html, 'titled image is decorative' );
		cr_photo_contains( 'class="u-photo"', $html, 'u-photo on the image' );
		cr_photo_contains( '<span class="cr-photo-frame__number" aria-hidden="true"></span>', $html, 'number span is aria-hidden and empty (CSS counter fills it)' );
		cr_photo_assert( 1 === preg_match( '/^<article class="pk-card [^"]*\bk-photo\b[^"]*\bh-entry\b[^"]*\bcr-photo-frame\b[^"]*">/', $html ), 'root keeps pk-card before h-entry and gains cr-photo-frame' );
		foreach ( array( 'pk-media__caption', 'pk-stream-date', 'dt-published', 'pk-kindlabel', 'Read more' ) as $cut ) {
			cr_photo_not_contains( $cut, $html, 'frame prints no ' . $cut );
		}
		cr_photo_not_contains( 'September', $html, 'no per-frame date' );
		cr_photo_not_contains( '→', $html, 'no arrow glyph' );
		cr_photo_not_contains( 'figcaption', $html, 'no caption element' );
		cr_photo_not_contains( 'cr-photo-frame__title--object', $html, 'a titled frame has no image link' );
	}
);

cr_photo_group(
	'the heading level follows the card block and defaults to 3',
	static function (): void {
		cr_photo_reset();
		foreach ( array( 2, 3, 4 ) as $level ) {
			cr_photo_contains( '<h' . $level . ' class="cr-photo-frame__title', cr_photo_frame( 42, array( 'headingLevel' => $level ) ), 'h' . $level );
		}
		cr_photo_contains( '<h3 class="cr-photo-frame__title', cr_photo_frame( 42, array() ), 'default h3' );
		cr_photo_contains( '<h4 class="cr-photo-frame__title', cr_photo_frame( 42, array( 'headingLevel' => 9 ) ), 'clamped to 4' );
	}
);

cr_photo_group(
	'the lead image is a medium sub-size with sizes, and never filtered',
	static function (): void {
		cr_photo_reset();
		$html = cr_photo_frame();
		cr_photo_contains( 'attachment-901-medium.jpg', $html, 'medium size requested' );
		cr_photo_contains( 'srcset=', $html, 'srcset kept' );
		cr_photo_contains( 'sizes="(min-width: 64rem) 22vw, (min-width: 40rem) 30vw, 46vw"', $html, 'sizes' );
		cr_photo_not_contains( 'style=', $html, 'no inline filter or style' );
		cr_photo_not_contains( 'full', $html, 'never the full size' );
		cr_photo_contains( 'data-cr-cover-fallback', $html, 'the media is marked for the failed-image fallback' );
		cr_photo_assert( 1 === preg_match( '/<span class="cr-photo-frame__media"[^>]*data-cr-cover-fallback[^>]*>\s*<img/', $html ), 'the fallback container directly holds the image' );
	}
);

cr_photo_group(
	'the first four frames of a page load eagerly and the first has high priority',
	static function (): void {
		cr_photo_reset();
		$loads = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$html    = cr_photo_frame();
			$loads[] = array(
				'eager'    => str_contains( $html, 'loading="eager"' ),
				'lazy'     => str_contains( $html, 'loading="lazy"' ),
				'high'     => str_contains( $html, 'fetchpriority="high"' ),
				'skipLazy' => str_contains( $html, 'u-photo skip-lazy' ),
			);
		}
		cr_photo_same( array( true, false, true, false ), array( $loads[0]['eager'], $loads[0]['lazy'], $loads[0]['high'], $loads[0]['skipLazy'] ), 'frame 1: eager, high priority, not skip-lazy class' );
		foreach ( array( 1, 2, 3 ) as $i ) {
			cr_photo_same( array( true, false, false, true ), array( $loads[ $i ]['eager'], $loads[ $i ]['lazy'], $loads[ $i ]['high'], $loads[ $i ]['skipLazy'] ), 'frame ' . ( $i + 1 ) . ': eager, skip-lazy class' );
		}
		foreach ( array( 4, 5 ) as $i ) {
			cr_photo_same( array( false, true, false, false ), array( $loads[ $i ]['eager'], $loads[ $i ]['lazy'], $loads[ $i ]['high'], $loads[ $i ]['skipLazy'] ), 'frame ' . ( $i + 1 ) . ': lazy' );
		}
		\Courtneyr\Child\Photo\reset_page_state();
		cr_photo_contains( 'fetchpriority="high"', cr_photo_frame(), 'a reset page starts at frame 1 again' );
	}
);

cr_photo_group(
	'an untitled frame is one link around the image, named by the stored alt',
	static function (): void {
		cr_photo_reset( 43, '' );
		$GLOBALS['cr_photo_facts'][43]['lead']['alt'] = 'A red kite over a field';
		$html = cr_photo_frame( 43, array( 'headingLevel' => 3 ), str_replace( 'Blue Hour at Example Lake', 'Photo, September 8, 2026', cr_photo_plugin_card() ) );
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one rendered link' );
		cr_photo_assert( 1 === preg_match( '/<h3 class="cr-photo-frame__title cr-photo-frame__title--object pk-title"><a class="u-url" href="[^"]+"><span class="cr-photo-frame__media/', $html ), 'the link opens the heading and wraps the media' );
		cr_photo_not_contains( 'p-name', $html, 'no p-name on an untitled frame' );
		cr_photo_contains( 'alt="A red kite over a field"', $html, 'stored alt names the link' );
		cr_photo_not_contains( 'cr-photo-frame__sr', $html, 'no screen-reader text when the alt names the link' );
		cr_photo_assert( 1 === preg_match( '/<span class="cr-photo-frame__sleeve[^"]*"><span class="cr-photo-frame__sleeve-text">Photo, September 8, 2026<\/span><\/span>/', $html ), 'the sleeve is exposed (not aria-hidden) so a failed image still leaves a name' );
		cr_photo_assert( 0 === preg_match( '/<h3[^>]*>.*?<\/h3>\s*<div class="cr-photo-frame__label">[^<]*<span[^>]*><\/span>[^<]+</s', $html ), 'the label shows the number only' );

		$GLOBALS['cr_photo_facts'][43]['lead']['alt'] = '';
		$html = cr_photo_frame( 43, array( 'headingLevel' => 3 ), str_replace( 'Blue Hour at Example Lake', 'Photo, September 8, 2026', cr_photo_plugin_card() ) );
		cr_photo_contains( '<span class="cr-photo-frame__sr">Photo, September 8, 2026</span>', $html, 'empty alt: visually hidden Photo, <date> names the link' );
		cr_photo_contains( 'aria-hidden="true"><span class="cr-photo-frame__sleeve-text">Photo, September 8, 2026', $html, 'empty alt: the sleeve is aria-hidden so the name is not doubled' );
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'still one rendered link' );
	}
);

cr_photo_group(
	'a post with no lead image prints the typographic frame',
	static function (): void {
		cr_photo_reset();
		$GLOBALS['cr_photo_facts'][42]['lead'] = array( 'attachment_id' => 0, 'alt' => '', 'caption' => '', 'source' => '' );
		$html = cr_photo_frame();
		cr_photo_not_contains( '<img', $html, 'no image' );
		cr_photo_contains( 'cr-photo-frame__media--paper', $html, 'paper modifier' );
		cr_photo_contains( 'Blue Hour at Example Lake</span>', $html, 'the title sits on the sleeve as text' );
		cr_photo_assert( 1 === preg_match( '/cr-paper--(sky-blue|periwinkle|light-orange)/', $html ), 'one palette paper class' );
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one rendered link' );
		// A lead whose file is gone (the attachment row exists, the image does not render).
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['missing_attachments'][901] = true;
		cr_photo_contains( 'cr-photo-frame__media--paper', cr_photo_frame(), 'an attachment that prints nothing falls back too' );
	}
);

cr_photo_group(
	'the paper is stable per post and drawn from three palette tokens',
	static function (): void {
		$seen = array();
		for ( $id = 100; $id < 160; $id++ ) {
			$paper         = \Courtneyr\Child\Photo\paper_class( $id );
			$seen[ $paper ] = true;
			cr_photo_same( $paper, \Courtneyr\Child\Photo\paper_class( $id ), 'stable for ' . $id );
		}
		cr_photo_same( array( 'cr-paper--light-orange', 'cr-paper--periwinkle', 'cr-paper--sky-blue' ), ( static function ( array $keys ): array { sort( $keys ); return $keys; } )( array_keys( $seen ) ), 'three papers in use' );
	}
);

cr_photo_group(
	'a protected post keeps the plugin card, gains the paper frame and no image',
	static function (): void {
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['protected'][42] = true;
		$GLOBALS['cr_photo_facts'][42]                = array();
		$card = '<article class="pk-card pk-card--stream pk-card--protected k-photo h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Protected: Blue Hour</a></h3></article>';
		$html = cr_photo_frame( 42, array( 'headingLevel' => 3 ), $card );
		cr_photo_contains( '<h3 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Protected: Blue Hour</a></h3>', $html, 'plugin heading byte-identical' );
		cr_photo_contains( 'cr-photo-frame', $html, 'frame class' );
		cr_photo_contains( 'cr-photo-frame--protected', $html, 'protected modifier' );
		cr_photo_not_contains( '<img', $html, 'no image' );
		cr_photo_same( 1, cr_photo_rendered_anchors( $html ), 'one link' );
		cr_photo_contains( '<span class="cr-photo-frame__number" aria-hidden="true"></span>', $html, 'counter span' );
	}
);

cr_photo_group(
	'unchanged cases return the input byte for byte',
	static function (): void {
		cr_photo_reset();
		$input = cr_photo_plugin_card();
		// Not a photo post.
		$GLOBALS['cr_photo_request']['post_kinds'][42] = array( 'note' );
		cr_photo_same( $input, cr_photo_frame(), 'not a photo post' );
		// Neither the archive nor the frame style.
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['kind_archive'] = '';
		cr_photo_same( $input, frame_card( $input, array( 'attrs' => array() ), new WP_Block( array( 'postId' => 42 ) ) ), 'outside the archive and without the style' );
		// The style alone is enough (Site Editor preview, home lanes that opt in).
		cr_photo_assert( str_contains( frame_card( $input, array( 'attrs' => array( 'className' => 'is-style-cr-photo-frame' ) ), new WP_Block( array( 'postId' => 42 ) ) ), 'cr-photo-frame' ), 'the block style alone dresses the frame' );
		// Empty facts on a visible card.
		cr_photo_reset();
		$GLOBALS['cr_photo_facts'][42] = array();
		cr_photo_same( $input, cr_photo_frame(), 'empty facts' );
		// Empty and rootless HTML.
		cr_photo_reset();
		cr_photo_same( '', cr_photo_frame( 42, array(), '' ), 'empty html' );
		cr_photo_same( '<div class="pk-card"></div>', cr_photo_frame( 42, array(), '<div class="pk-card"></div>' ), 'no article root' );
		// No post.
		cr_photo_same( $input, cr_photo_frame( 999 ), 'unknown post' );
	}
);

cr_photo_group(
	'without the plugin the adapter changes nothing',
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

cr_photo_group(
	'sheet loads on the photo archive, photo singles, the Stream and the editor only',
	static function (): void {
		cr_photo_reset();
		\Courtneyr\Child\Photo\enqueue_styles();
		cr_photo_same( 0, count( $GLOBALS['cr_photo_enqueued'] ), 'nothing on an unrelated page' );
		foreach ( array( 'kind_archive' => 'photo', 'single' => true, 'stream' => true, 'admin' => true ) as $key => $value ) {
			cr_photo_reset();
			$GLOBALS['cr_photo_request'][ $key ] = $value;
			\Courtneyr\Child\Photo\enqueue_styles();
			cr_photo_same( 'courtneyr-photo', $GLOBALS['cr_photo_enqueued'][0][0] ?? '', 'enqueued for ' . $key );
			cr_photo_same( COURTNEYR_CHILD_URI . '/assets/css/cr-photo.css', $GLOBALS['cr_photo_enqueued'][0][1] ?? '', 'sheet path for ' . $key );
			cr_photo_same( array( 'courtneyr-paper' ), $GLOBALS['cr_photo_enqueued'][0][2] ?? array(), 'depends on the shared paper sheet for ' . $key );
		}
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['single']                 = true;
		$GLOBALS['cr_photo_request']['post_kinds'][42]         = array( 'note' );
		\Courtneyr\Child\Photo\enqueue_styles();
		cr_photo_same( 0, count( $GLOBALS['cr_photo_enqueued'] ), 'not on a note single' );
	}
);

cr_photo_group(
	'archive and single body classes',
	static function (): void {
		cr_photo_reset();
		$GLOBALS['cr_photo_request']['kind_archive'] = 'photo';
		cr_photo_assert( in_array( 'cr-photo-archive', \Courtneyr\Child\Photo\body_class( array( 'archive' ) ), true ), 'archive class' );
		cr_photo_reset();
		cr_photo_same( array( 'home' ), \Courtneyr\Child\Photo\body_class( array( 'home' ) ), 'no class elsewhere' );
	}
);

cr_photo_finish();
