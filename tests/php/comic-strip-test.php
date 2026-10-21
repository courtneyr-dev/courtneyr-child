<?php
/**
 * `php tests/php/comic-strip-test.php`: drawn comic theme contracts without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/photo-bootstrap.php';

	if ( ! defined( 'COURTNEYR_CHILD_DIR' ) ) {
		define( 'COURTNEYR_CHILD_DIR', CR_PHOTO_THEME_DIR );
	}

	$GLOBALS['cr_comic_unique']     = 0;
	$GLOBALS['cr_comic_registered'] = array();
	$GLOBALS['cr_comic_blocks']     = array();
	$GLOBALS['cr_comic_series_url'] = 'https://example.test/kind/comics/?pkiw_comic_view=drawn&pkiw_comic_series=Field%20Notes';

	if ( ! function_exists( 'get_query_var' ) ) {
		function get_query_var( $name, $default = '' ) {
			return $GLOBALS['cr_photo_request']['query_vars'][ (string) $name ] ?? $default;
		}
	}
	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ): string {
			$found = get_post( $post );
			return $found instanceof WP_Post ? $found->post_title : '';
		}
	}
	if ( ! function_exists( 'get_the_date' ) ) {
		function get_the_date( $format = '', $post = null ): string {
			return 'c' === $format ? '2026-09-24T12:00:00-04:00' : 'September 24, 2026';
		}
	}
	if ( ! function_exists( 'wp_unique_id' ) ) {
		function wp_unique_id( $prefix = '' ): string {
			return (string) $prefix . ++$GLOBALS['cr_comic_unique'];
		}
	}
	if ( ! function_exists( 'get_term_by' ) ) {
		function get_term_by( $field, $value, $taxonomy ) {
			return (object) array( 'name' => 'Comics', 'slug' => 'comics', 'taxonomy' => 'kind' );
		}
	}
	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $post, $taxonomy ) {
			return array( (object) array( 'name' => 'Comics', 'slug' => 'comics', 'taxonomy' => 'kind' ) );
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ): bool {
			return false;
		}
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $value, $flags = 0, $depth = 512 ): string {
			return (string) json_encode( $value, (int) $flags, (int) $depth );
		}
	}
	if ( ! function_exists( 'esc_html_e' ) ) {
		function esc_html_e( $text, $domain = null ): void {
			echo esc_html( $text );
		}
	}
	if ( ! function_exists( 'is_paged' ) ) {
		function is_paged(): bool {
			return ! empty( $GLOBALS['cr_photo_request']['paged'] );
		}
	}
	if ( ! function_exists( 'in_the_loop' ) ) {
		function in_the_loop(): bool {
			return ! isset( $GLOBALS['cr_photo_request']['in_loop'] ) || (bool) $GLOBALS['cr_photo_request']['in_loop'];
		}
	}
	if ( ! function_exists( 'wp_style_is' ) ) {
		function wp_style_is( $handle, $status = 'enqueued' ): bool {
			return isset( $GLOBALS['cr_comic_registered'][ (string) $handle ] );
		}
	}
	if ( ! function_exists( 'wp_register_style' ) ) {
		function wp_register_style( $handle, $src, $deps = array(), $version = false ): void {
			$GLOBALS['cr_comic_registered'][ (string) $handle ] = array( $src, $deps, $version );
		}
	}
	if ( ! function_exists( 'wp_style_add_data' ) ) {
		function wp_style_add_data( $handle, $key, $value ): void {
			$GLOBALS['cr_comic_registered'][ (string) $handle ][ (string) $key ] = $value;
		}
	}
	if ( ! function_exists( 'has_post_format' ) ) {
		function has_post_format( $format = array(), $post = null ): bool {
			return ( $GLOBALS['cr_photo_request']['format'][ cr_photo_post_id( $post ) ] ?? '' ) === $format;
		}
	}
	if ( ! function_exists( 'parse_blocks' ) ) {
		function parse_blocks( string $content ): array {
			return $GLOBALS['cr_comic_blocks'][ $content ] ?? array();
		}
	}
}

namespace PKIW {
	/** Comic series URL stand-in. */
	class Comic_Strip {
		public static function series_url( string $series = '' ): string {
			return (string) $GLOBALS['cr_comic_series_url'];
		}
	}

	/** Stream label source stand-in. */
	function stream_card_kind_label( \WP_Post $post ): string {
		unset( $post );
		return 'Comics';
	}

	/** Stream label filter stand-in. */
	function get_kind_label( string $label, string $kind, string $context ): string {
		unset( $kind, $context );
		return $label . ' from plugin';
	}
}

namespace {
	require CR_PHOTO_THEME_DIR . '/inc/comic-strip.php';

	use function Courtneyr\Child\ComicStrip\body_class;
	use function Courtneyr\Child\ComicStrip\facts;
	use function Courtneyr\Child\ComicStrip\holds_h1;
	use function Courtneyr\Child\ComicStrip\register_styles;
	use function Courtneyr\Child\ComicStrip\reset_facts_cache;
	use function Courtneyr\Child\ComicStrip\series_tag;
	use function Courtneyr\Child\ComicStrip\sketchbook_item;
	use function Courtneyr\Child\ComicStrip\stream_strip;
	use function Courtneyr\Child\ComicStrip\strip_page;
	use function Courtneyr\Child\ComicStrip\strip_styles;
	use function Courtneyr\Child\ComicStrip\tilt;

	/** One Image block in the shape returned by parse_blocks(). */
	function cr_comic_image_block( int $id, string $src = '' ): array {
		$src = '' !== $src ? $src : 'https://example.test/uploads/panel-' . $id . '.png';
		return array(
			'blockName'   => 'core/image',
			'attrs'       => $id > 0 ? array( 'id' => $id ) : array(),
			'innerBlocks' => array(),
			'innerHTML'   => '<figure class="wp-block-image"><img src="' . esc_url( $src ) . '" alt="saved"></figure>',
		);
	}

	/** Gallery block with the supplied ordered image blocks. */
	function cr_comic_gallery_block( array $images ): array {
		return array(
			'blockName'   => 'core/gallery',
			'attrs'       => array( 'className' => 'is-style-cr-comic-panels' ),
			'innerBlocks' => $images,
			'innerHTML'   => '',
		);
	}

	/** Public panel facts. */
	function cr_comic_panels(): array {
		return array(
			array( 'position' => 1, 'attachment_id' => 801, 'src' => '', 'alt' => 'Panel one stored alt', 'transcript' => 'A person sketches at a desk.' ),
			array( 'position' => 2, 'attachment_id' => 802, 'src' => '', 'alt' => '', 'transcript' => '' ),
			array( 'position' => 3, 'attachment_id' => 0, 'src' => 'https://example.test/uploads/remote-panel.png', 'alt' => 'Panel three stored alt', 'transcript' => 'A note lands beside the pencil.' ),
		);
	}

	/** Reset one drawn-comic request and its facts. */
	function cr_comic_request( int $id = 266, string $title = 'The missing alt text', ?array $comic_facts = null ): WP_Post {
		$post = cr_photo_reset( $id, $title );
		$GLOBALS['cr_photo_request']['post_kinds'][ $id ] = array( 'comics' );
		$GLOBALS['cr_photo_request']['single']           = true;
		$GLOBALS['cr_photo_request']['query_vars']       = array();
		$GLOBALS['cr_photo_request']['in_loop']          = true;
		$GLOBALS['cr_photo_request']['paged']            = false;
		$GLOBALS['cr_photo_facts'][ $id ]                = $comic_facts ?? array(
			'drawn'  => true,
			'series' => 'Field Notes',
			'issue'  => '12',
			'panels' => cr_comic_panels(),
		);
		$GLOBALS['wp_query'] = (object) array( 'current_post' => 0 );
		reset_facts_cache();
		return $post;
	}

	/** Count visible anchors in one fragment. */
	function cr_comic_anchor_count( string $html ): int {
		return substr_count( $html, '<a ' );
	}

	/** Read HTML into an XPath rooted at body. */
	function cr_comic_xpath( string $html ): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		return new DOMXPath( $dom );
	}

	/** Check WordPress block comments with a small stack. */
	function cr_comic_balanced_blocks( string $html ): bool {
		preg_match_all( '/<!--\s*(\/?)wp:([a-z0-9-]+\/[a-z0-9-]+|[a-z0-9-]+)(.*?)-->/s', $html, $matches, PREG_SET_ORDER );
		$stack = array();
		foreach ( $matches as $match ) {
			if ( '/' === $match[1] ) {
				if ( array_pop( $stack ) !== $match[2] ) {
					return false;
				}
			} elseif ( ! str_ends_with( trim( $match[3] ), '/' ) ) {
				$stack[] = $match[2];
			}
		}
		return array() === $stack;
	}

	if ( in_array( '--facts-only', $argv, true ) ) {
		$post = cr_comic_request();
		echo array() === facts( $post ) ? "NO_PLUGIN_PASS\n" : "NO_PLUGIN_FAIL\n";
		exit( array() === facts( $post ) ? 0 : 1 );
	}

	require CR_PHOTO_THEME_DIR . '/inc/comic.php';
	require CR_PHOTO_THEME_DIR . '/inc/gallery-zine.php';

	cr_photo_group(
		'block styles and plugin style list',
		static function (): void {
			$GLOBALS['cr_photo_styles'] = array();
			register_styles();
			cr_photo_same( 2, count( $GLOBALS['cr_photo_styles'] ), 'two styles registered' );
			cr_photo_same( 'core/gallery', $GLOBALS['cr_photo_styles'][0][0], 'gallery block' );
			cr_photo_same( 'cr-comic-panels', $GLOBALS['cr_photo_styles'][0][1]['name'], 'gallery style slug' );
			cr_photo_same( 'Comic panels', $GLOBALS['cr_photo_styles'][0][1]['label'], 'gallery label' );
			cr_photo_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_photo_styles'][1][0], 'stream-card block' );
			cr_photo_same( 'cr-sketchbook-strip', $GLOBALS['cr_photo_styles'][1][1]['name'], 'page style slug' );
			cr_photo_same( 'Sketchbook strip', $GLOBALS['cr_photo_styles'][1][1]['label'], 'page style label' );
			$inline = (string) $GLOBALS['cr_photo_styles'][0][1]['inline_style'];
			cr_photo_assert( '' !== $inline, 'gallery preview CSS exists' );
			cr_photo_assert( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $inline ), 'preview has no raw color' );
			cr_photo_not_contains( 'filter', $inline, 'preview has no image filter' );
			cr_photo_not_contains( 'gradient', $inline, 'preview has no gradient' );
			cr_photo_same( array( 'other', 'cr-comic-panels' ), strip_styles( array( 'other' ) ), 'style appended' );
			cr_photo_same( array( 'cr-comic-panels' ), strip_styles( strip_styles( null ) ), 'style added once and non-array tolerated' );
		}
	);

	cr_photo_group(
		'drawn archive is not the existing rack',
		static function (): void {
			cr_comic_request();
			$GLOBALS['cr_photo_request']['single']       = false;
			$GLOBALS['cr_photo_request']['kind_archive'] = 'comics';
			$GLOBALS['cr_photo_request']['query_vars']   = array( 'pkiw_comic_view' => 'drawn' );
			cr_photo_same( false, \Courtneyr\Child\Comic\is_rack(), 'drawn view' );
			$GLOBALS['cr_photo_request']['query_vars'] = array();
			cr_photo_same( true, \Courtneyr\Child\Comic\is_rack(), 'unfiltered rack' );
		}
	);

	cr_photo_group(
		'facts are guarded and cached per post',
		static function (): void {
			$post = cr_comic_request();
			$first = facts( $post );
			$GLOBALS['cr_photo_facts'][ $post->ID ] = array();
			cr_photo_same( $first, facts( $post ), 'second read comes from cache' );

			$post = cr_comic_request( 267 );
			$GLOBALS['cr_photo_request']['post_kinds'][267] = array( 'note' );
			cr_photo_same( array(), facts( $post ), 'non-comics post' );

			$post = cr_comic_request( 268, 'A comic read', array( 'series' => 'Saga', 'panels' => array() ) );
			cr_photo_same( array(), facts( $post ), 'facts without drawn flag' );

			$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin --facts-only';
			exec( $command, $output, $status );
			cr_photo_same( 0, $status, 'older-plugin subprocess exits cleanly' );
			cr_photo_contains( 'NO_PLUGIN_PASS', implode( "\n", $output ), 'older-plugin facts are empty' );
		}
	);

	cr_photo_group(
		'strip page replaces only one matching block',
		static function (): void {
			$post    = cr_comic_request();
			$gallery = cr_comic_gallery_block( array( cr_comic_image_block( 801 ), cr_comic_image_block( 802 ), cr_comic_image_block( 0, 'https://example.test/uploads/remote-panel.png' ) ) );
			$other   = cr_comic_gallery_block( array( cr_comic_image_block( 999 ) ) );
			cr_photo_same( '<p>other</p>', strip_page( '<p>other</p>', $other ), 'unrelated gallery passes' );
			$html = strip_page( '<p>core gallery</p>', $gallery );
			cr_photo_contains( 'cr-strip--single', $html, 'spread prints' );
			cr_photo_same( '<p>duplicate</p>', strip_page( '<p>duplicate</p>', $gallery ), 'same block prints once' );
			cr_photo_same( 1, substr_count( $html, '<h1 class="cr-strip__title p-name">' ), 'one named H1' );
			cr_photo_contains( '<dt>Date</dt>', $html, 'Date row' );
			cr_photo_contains( '<dt>Series</dt>', $html, 'Series row' );
			cr_photo_contains( '<a href="https://example.test/kind/comics/', $html, 'series link' );
			cr_photo_same( 3, substr_count( $html, '<li class="cr-strip__panel">' ), 'three ordered panel items' );
			cr_photo_same( 3, substr_count( $html, '<figure class="cr-strip__figure"><img' ), 'tab sits outside each figure' );
			cr_photo_contains( 'alt="Panel one stored alt"', $html, 'stored alt' );
			cr_photo_contains( 'alt=""', $html, 'stored empty alt' );
			cr_photo_contains( 'src="https://example.test/uploads/remote-panel.png" alt="Panel three stored alt"', $html, 'source-only panel' );
			cr_photo_not_contains( 'figcaption', $html, 'no duplicate captions' );
			cr_photo_contains( '<h2 class="cr-strip__transcript-title">Transcript</h2>', $html, 'transcript heading' );
			cr_photo_same( 2, substr_count( $html, '<div class="cr-strip__entry">' ), 'only captioned panels have transcript rows' );
			cr_photo_contains( '<dt>Panel 1</dt>', $html, 'first transcript label' );
			cr_photo_contains( '<dt>Panel 3</dt>', $html, 'third transcript label' );
			foreach ( array( 'Entry', 'Read the full transcript', 'Panel order follows', 'SAMPLE CONTENT' ) as $banned ) {
				cr_photo_not_contains( $banned, $html, 'banned copy' );
			}

			$GLOBALS['cr_comic_series_url'] = '';
			$post = cr_comic_request( 269 );
			$html = strip_page( 'core', $gallery );
			cr_photo_contains( '<dd>Field Notes #12</dd>', $html, 'series falls back to plain text' );
			$GLOBALS['cr_comic_series_url'] = 'https://example.test/kind/comics/?pkiw_comic_view=drawn';

			$facts = array( 'drawn' => true, 'series' => '', 'issue' => '', 'panels' => array( array( 'position' => 1, 'attachment_id' => 801, 'src' => '', 'alt' => '', 'transcript' => '' ) ) );
			$post  = cr_comic_request( 270, 'No transcript', $facts );
			$html  = strip_page( 'core', cr_comic_image_block( 801 ) );
			cr_photo_not_contains( '<dt>Series</dt>', $html, 'series row absent' );
			cr_photo_not_contains( 'cr-strip__transcript', $html, 'transcript absent' );

			$post = cr_comic_request( 271 );
			$GLOBALS['cr_photo_request']['protected'][271] = true;
			cr_photo_same( 'protected core', strip_page( 'protected core', $gallery ), 'protected post passes' );
			$post = cr_comic_request( 272 );
			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( 'archive core', strip_page( 'archive core', $gallery ), 'non-single passes' );
		}
	);

	cr_photo_group(
		'H1 ownership, body class, tilt and series labels',
		static function (): void {
			$post = cr_comic_request();
			cr_photo_same( true, holds_h1( false, $post ), 'strip owns H1' );
			cr_photo_same( true, holds_h1( true, new WP_Post( 900 ) ), 'incoming true preserved' );
			cr_photo_same( false, holds_h1( false, new WP_Post( 900 ) ), 'other post does not own H1' );
			cr_photo_same( array( 'base', 'cr-comic-strip-single' ), body_class( array( 'base' ) ), 'single body class' );
			$GLOBALS['cr_photo_request']['single'] = false;
			cr_photo_same( array( 'base' ), body_class( array( 'base' ) ), 'other body class unchanged' );

			cr_photo_same( tilt( 42 ), tilt( 42 ), 'tilt stable' );
			$tilts = array();
			for ( $id = 1; $id <= 200; $id++ ) {
				$tilts[] = tilt( $id );
			}
			cr_photo_assert( count( array_unique( $tilts ) ) > 1, 'post IDs use more than one tilt' );
			cr_photo_assert( array() === array_diff( array_unique( $tilts ), \Courtneyr\Child\ComicStrip\TILTS ), 'tilts stay inside the token list' );
			cr_photo_same( 'Field Notes #12', series_tag( array( 'series' => ' Field Notes ', 'issue' => ' 12 ' ) ), 'series and issue' );
			cr_photo_same( 'Field Notes', series_tag( array( 'series' => 'Field Notes' ) ), 'series only' );
			cr_photo_same( '#12', series_tag( array( 'issue' => '12' ) ), 'issue only' );
			cr_photo_same( '', series_tag( array() ), 'empty tag' );
		}
	);

	cr_photo_group(
		'sketchbook item is one-link archive markup',
		static function (): void {
			$post  = cr_comic_request();
			$block = array( 'attrs' => array( 'headingLevel' => 3, 'className' => 'extra is-style-cr-sketchbook-strip' ) );
			$html  = sketchbook_item( 'plugin card', $block, new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_photo_contains( '<article class="pk-card pk-card--stream k-comics h-entry cr-sketch-item"', $html, 'exact root classes' );
			cr_photo_same( 2, substr_count( $html, '<img' ), 'first two panels' );
			cr_photo_contains( 'alt="Panel one stored alt"', $html, 'archive stored alt' );
			cr_photo_contains( 'alt=""', $html, 'archive empty alt' );
			cr_photo_same( 1, cr_comic_anchor_count( $html ), 'one anchor' );
			cr_photo_contains( 'aria-labelledby="cr-sk-title-', $html, 'tag contributes to accessible name' );
			$xpath = cr_comic_xpath( $html );
			$label = $xpath->query( '//a/@aria-labelledby' )->item( 0 )->nodeValue;
			foreach ( explode( ' ', $label ) as $id ) {
				cr_photo_same( 1, $xpath->query( '//*[@id="' . $id . '"]' )->length, 'label id exists' );
			}
			cr_photo_contains( 'loading="eager"', $html, 'first row eager' );
			cr_photo_contains( 'fetchpriority="high"', $html, 'first row priority' );
			foreach ( array( '<time', 'Transcript available', 'pk-kindlabel', 'pk-meta', 'pk-excerpt' ) as $banned ) {
				cr_photo_not_contains( $banned, $html, 'archive omission' );
			}

			$post = cr_comic_request( 280, 'Blank paper', array( 'drawn' => true, 'series' => '', 'issue' => '', 'panels' => array() ) );
			$html = sketchbook_item( 'plugin card', $block, new WP_Block( array( 'postId' => 280 ) ) );
			cr_photo_not_contains( 'cr-sketch-item__panels', $html, 'zero-panel item has no frame' );
			cr_photo_not_contains( 'aria-labelledby', $html, 'no tag means no composite label' );
			cr_photo_same( 1, cr_comic_anchor_count( $html ), 'zero-panel item still links once' );

			$post = cr_comic_request( 281 );
			$GLOBALS['wp_query']->current_post = 3;
			$html = sketchbook_item( 'plugin card', $block, new WP_Block( array( 'postId' => 281 ) ) );
			cr_photo_not_contains( 'fetchpriority="high"', $html, 'later row keeps default loading' );
			cr_photo_same( 'plugin card', sketchbook_item( 'plugin card', array( 'attrs' => array() ), new WP_Block( array( 'postId' => 281 ) ) ), 'unstyled card passes' );

			$post = cr_comic_request( 282, 'Protected', array() );
			cr_photo_same( 'protected card', sketchbook_item( 'protected card', $block, new WP_Block( array( 'postId' => 282 ) ) ), 'protected facts pass' );
		}
	);

	cr_photo_group(
		'Stream strip keeps stored alt and one link',
		static function (): void {
			$post = cr_comic_request();
			$GLOBALS['cr_photo_request']['stream'] = true;
			$block = array( 'attrs' => array( 'headingLevel' => 4 ) );
			$html  = stream_strip( 'plugin card', $block, new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_photo_contains( '<article class="pk-card pk-card--stream k-comics h-entry cr-strip-card"', $html, 'exact Stream root classes' );
			cr_photo_contains( '<h4 class="pk-title p-name">', $html, 'heading level' );
			cr_photo_same( 1, cr_comic_anchor_count( $html ), 'one Stream anchor' );
			cr_photo_contains( 'alt="Panel one stored alt"', $html, 'Stream stored alt' );
			cr_photo_contains( '<span class="cr-strip__tab" aria-hidden="true">1</span>', $html, 'tab hidden from accessibility tree' );
			cr_photo_contains( '<span class="pk-kindlabel">Comics from plugin</span>', $html, 'plugin kind label' );
			cr_photo_contains( '<details class="cr-strip__transcript-mini">', $html, 'panel-one transcript disclosure' );
			foreach ( array( 'Read comic', 'Transcript available' ) as $banned ) {
				cr_photo_not_contains( $banned, $html, 'Stream omission' );
			}

			$post = cr_comic_request( 290, 'No transcript', array( 'drawn' => true, 'series' => '', 'issue' => '', 'panels' => array( array( 'position' => 1, 'attachment_id' => 801, 'src' => '', 'alt' => '', 'transcript' => '' ) ) ) );
			$GLOBALS['cr_photo_request']['stream'] = true;
			$html = stream_strip( 'plugin card', $block, new WP_Block( array( 'postId' => 290 ) ) );
			cr_photo_not_contains( '<details', $html, 'empty transcript has no disclosure' );
			cr_photo_same( 'plugin card', stream_strip( 'plugin card', array( 'attrs' => array( 'className' => 'is-style-cr-sketchbook-strip' ) ), new WP_Block( array( 'postId' => 290 ) ) ), 'sketchbook style passes Stream adapter' );
			$GLOBALS['cr_photo_request']['stream'] = false;
			cr_photo_same( 'plugin card', stream_strip( 'plugin card', $block, new WP_Block( array( 'postId' => 290 ) ) ), 'non-Stream request passes' );
		}
	);

	cr_photo_group(
		'drawn Gallery-format comic bypasses the zine',
		static function (): void {
			$gallery = cr_comic_gallery_block( array( cr_comic_image_block( 801 ) ) );
			$post    = cr_comic_request( 300 );
			$post->post_content = 'drawn-gallery';
			$GLOBALS['cr_comic_blocks']['drawn-gallery'] = array( $gallery );
			$GLOBALS['cr_photo_request']['format'][300] = 'gallery';
			\Courtneyr\Child\GalleryZine\reset_state();
			cr_photo_same( null, \Courtneyr\Child\GalleryZine\gallery_for( $post ), 'drawn gallery has no zine' );

			$post = cr_comic_request( 301, 'Comic read', array() );
			$post->post_content = 'read-gallery';
			$GLOBALS['cr_comic_blocks']['read-gallery'] = array( $gallery );
			$GLOBALS['cr_photo_request']['format'][301] = 'gallery';
			\Courtneyr\Child\GalleryZine\reset_state();
			cr_photo_same( $gallery, \Courtneyr\Child\GalleryZine\gallery_for( $post ), 'empty facts preserve gallery zine' );

			$post = cr_comic_request( 302, 'Note gallery', array() );
			$GLOBALS['cr_photo_request']['post_kinds'][302] = array( 'note' );
			$post->post_content = 'note-gallery';
			$GLOBALS['cr_comic_blocks']['note-gallery'] = array( $gallery );
			$GLOBALS['cr_photo_request']['format'][302] = 'gallery';
			\Courtneyr\Child\GalleryZine\reset_state();
			cr_photo_same( $gallery, \Courtneyr\Child\GalleryZine\gallery_for( $post ), 'non-drawn gallery unchanged' );
		}
	);

	cr_photo_group(
		'owned files and rack regression guards',
		static function (): void {
			$owned = array(
				'inc/comic-strip.php',
				'templates/taxonomy-kind-comics-drawn.html',
				'patterns/cr-sketchbook-loop.php',
				'assets/css/cr-comic-strip.css',
			);
			foreach ( $owned as $relative ) {
				$source = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/' . $relative );
				cr_photo_assert( 0 === substr_count( $source, 'preg_' ), $relative . ' has no regular-expression call' );
				cr_photo_assert( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $source ), $relative . ' has no raw color' );
			}
			$css = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/assets/css/cr-comic-strip.css' );
			cr_photo_assert( 0 === preg_match( '/\bfilter\s*:/i', $css ), 'stylesheet has no filter property' );
			cr_photo_assert( 0 === preg_match( '/gradient\s*\(/i', $css ), 'stylesheet has no gradient' );
			cr_photo_assert( 0 === preg_match( '/font-size\s*:\s*(?:0\.[0-7]|10px|11px)/i', $css ), 'stylesheet has no sub-floor text' );

			$template = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/templates/taxonomy-kind-comics-drawn.html' );
			cr_photo_contains( 'post-kinds-indieweb/comic-series', $template, 'series block' );
			cr_photo_contains( 'courtneyr-child/cr-sketchbook-loop', $template, 'sketchbook pattern' );
			cr_photo_contains( 'courtneyr-child/cr-browse-all', $template, 'browse pattern' );
			cr_photo_assert( cr_comic_balanced_blocks( $template ), 'template block comments balance' );

			$pattern = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/patterns/cr-sketchbook-loop.php' );
			foreach ( array( '"perPage":12', '"inherit":true', 'post-kinds-indieweb/archive-sections', 'is-style-cr-sketchbook-strip', '"headingLevel":3' ) as $needle ) {
				cr_photo_contains( $needle, $pattern, 'pattern contract' );
			}
			cr_photo_assert( strpos( $pattern, 'query-pagination' ) > strpos( $pattern, '<!-- /wp:post-template -->' ), 'pager follows post template' );

			$hashes = array(
				'templates/taxonomy-kind-comics.html' => '7e5ef6335229f0e51c707c0494e407eb0c9a2be8',
				'patterns/cr-comic-rack-loop.php'     => '36c5da7706c35672b6c7c285e193d0aa35ae756d',
				'assets/css/cr-comic.css'             => '0dda7f76b8fb51abbc23d827055a495c1958c25d',
			);
			foreach ( $hashes as $relative => $expected ) {
				cr_photo_same( $expected, sha1_file( CR_PHOTO_THEME_DIR . '/' . $relative ), $relative . ' base hash' );
			}

			$comic = (string) file_get_contents( CR_PHOTO_THEME_DIR . '/inc/comic.php' );
			$start = strpos( $comic, 'function bag_card(' );
			$end   = strpos( $comic, 'add_filter(', $start );
			cr_photo_same( '79c6de5e3ff6bd36b11d033d74d8e0ebbe47358d', sha1( substr( $comic, $start, $end - $start ) ), 'bag_card hash' );
			$new = " * Is this the comics archive rack? The drawn view is not the rack.\n *\n * @return bool\n */\nfunction is_rack(): bool {\n\treturn \\is_tax( 'kind', 'comics' ) && 'drawn' !== \\get_query_var( 'pkiw_comic_view' );\n}";
			$old = " * Is this the comics archive?\n *\n * @return bool\n */\nfunction is_rack(): bool {\n\treturn \\is_tax( 'kind', 'comics' );\n}";
		cr_photo_contains( $new, $comic, 'only allowed rack hunk is present' );
			cr_photo_same( '9631ed8cef965162ac10dbdea2e013ece1b588f1', sha1( str_replace( $new, $old, $comic ) ), 'comic file normalizes to base' );
		}
	);

	cr_photo_finish();
}
