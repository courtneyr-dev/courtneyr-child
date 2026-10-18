<?php
/**
 * `php tests/php/w5-t0-autoplay-test.php`: checks W5-T0 media autoplay
 * stripping and the audio Able Player helper without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'REST_REQUEST', true );
	define( 'CR_W5_T0_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']            = array();
	$GLOBALS['cr_current_post']     = 0;
	$GLOBALS['cr_queried_post']     = 0;
	$GLOBALS['cr_terms']            = array();
	$GLOBALS['cr_admin']            = false;
	$GLOBALS['cr_shortcode_exists'] = true;
	$GLOBALS['cr_attachment_audio'] = true;
	$GLOBALS['cr_shortcode_calls']  = array();
	$GLOBALS['cr_order']            = array();

	/** Register a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Run registered filters in priority and registration order. */
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = array_keys( $GLOBALS['cr_hooks'][ $hook ] ?? array() );
		sort( $priorities );
		foreach ( $priorities as $priority ) {
			foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] as $registered ) {
				$all   = array_merge( array( $value ), $args );
				$value = call_user_func_array( $registered[0], array_slice( $all, 0, $registered[1] ) );
			}
		}
		return $value;
	}

	function is_admin() {
		return $GLOBALS['cr_admin'];
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function get_the_ID() {
		return $GLOBALS['cr_current_post'];
	}

	function get_queried_object_id() {
		return $GLOBALS['cr_queried_post'];
	}

	function has_term( $terms, $taxonomy, $post ) {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$actual = $GLOBALS['cr_terms'][ (int) $post ] ?? '';
		return in_array( $actual, (array) $terms, true );
	}

	function shortcode_exists( $tag ) {
		return 'ableplayer' === $tag && $GLOBALS['cr_shortcode_exists'];
	}

	function do_shortcode( $shortcode ) {
		$GLOBALS['cr_shortcode_calls'][] = $shortcode;
		$GLOBALS['cr_order'][]           = 'shortcode';
		return '<div class="ableplayer">' . $shortcode . '</div>';
	}

	function wp_attachment_is( $type, $attachment_id ) {
		return 'audio' === $type && $attachment_id > 0 && $GLOBALS['cr_attachment_audio'];
	}

	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function get_posts() {
		return array();
	}

	function is_singular() {
		return false;
	}

	function get_post() {
		return null;
	}

	function wp_has_noncharacters( $text ) {
		return false;
	}

	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}

	$core  = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api   = $core . '/wp-includes/html-api/';
	$files = array(
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-token.php',
		'class-wp-html-tag-processor.php',
	);
	foreach ( $files as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}
	require_once $core . '/wp-includes/class-wp-token-map.php';
	require_once $api . 'html5-named-character-references.php';

	require CR_W5_T0_THEME_DIR . '/inc/post-kinds.php';
}

namespace Courtneyr\Child\W5T0AutoplayTests {

	use function Courtneyr\Child\PostKinds\ableplayer_audio;

	$failures = array();

	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	function render( string $html, string $block_name, int $post_id, string $kind, int $queried_id = 0 ): string {
		$GLOBALS['cr_current_post'] = $post_id;
		$GLOBALS['cr_queried_post'] = $queried_id;
		$GLOBALS['cr_terms']         = array( ( 0 === $post_id ? $queried_id : $post_id ) => $kind );
		return apply_filters(
			'render_block',
			$html,
			array(
				'blockName' => $block_name,
				'attrs'     => array(),
			)
		);
	}

	$video = '<figure class="wp-block-video"><video autoplay muted loop controls playsinline poster="p.jpg" src="v.mp4"><track kind="captions" src="en.vtt" srclang="en"><track kind="descriptions" src="d.vtt" srclang="en"></video></figure>';
	$out   = render( $video, 'core/video', 12, 'video' );
	check( false === strpos( $out, 'autoplay' ), 'video autoplay is removed' );
	check( false !== strpos( $out, 'controls' ) && false !== strpos( $out, 'preload="metadata"' ), 'video controls and metadata preload are present' );
	foreach ( array( 'muted', 'loop', 'playsinline', 'poster="p.jpg"' ) as $kept ) {
		check( false !== strpos( $out, $kept ), "video keeps $kept" );
	}
	check( 2 === substr_count( $out, '<track' ), 'video keeps both track elements' );

	$audio = '<audio autoplay src="a.mp3"></audio>';
	$out   = render( $audio, 'core/audio', 4, 'audio' );
	check( false === strpos( $out, 'autoplay' ), 'audio autoplay is removed' );
	check( false !== strpos( $out, 'controls' ) && false !== strpos( $out, 'preload="metadata"' ), 'audio controls and metadata preload are present' );

	check( $video === render( $video, 'core/video', 5, 'note' ), 'a note video stays byte-identical by default' );
	check( false === strpos( render( $video, 'core/video', 12, 'video' ), 'autoplay' ), 'empty block context still strips through render_block' );
	check( false === strpos( render( $video, 'core/video', 0, 'video', 12 ), 'autoplay' ), 'queried object fallback strips when get_the_ID is zero' );

	$vimeo = '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><iframe src="https://player.vimeo.com/video/1?autoplay=1&amp;title=0" allow="autoplay; fullscreen"></iframe></div></figure>';
	$out   = render( $vimeo, 'core/embed', 12, 'video' );
	check( false === strpos( $out, 'autoplay=1' ) && false !== strpos( $out, 'title=0' ), 'Vimeo loses only the autoplay query argument' );
	check( false !== strpos( $out, 'allow="autoplay; fullscreen"' ), 'Vimeo keeps the allow attribute' );
	$plain_vimeo = '<iframe src="https://player.vimeo.com/video/1?title=0"></iframe>';
	check( $plain_vimeo === render( $plain_vimeo, 'core/embed', 12, 'video' ), 'an iframe without autoplay stays byte-identical' );
	$protocol_relative = '<iframe src="//player.vimeo.com/video/1?autoplay=1"></iframe>';
	check( '<iframe src="//player.vimeo.com/video/1"></iframe>' === render( $protocol_relative, 'core/embed', 12, 'video' ), 'protocol-relative URL keeps its form' );

	foreach ( array( 'jam', 'listen' ) as $kind ) {
		check( $vimeo === render( $vimeo, 'core/embed', 20, $kind ), "$kind Vimeo stays byte-identical" );
	}
	$spotify = '<iframe src="https://open.spotify.com/embed/track/1?autoplay=1"></iframe>';
	check( $spotify === render( $spotify, 'core/embed', 20, 'jam' ), 'jam Spotify stays byte-identical' );

	add_filter(
		'courtneyr_child_strip_autoplay_kinds',
		static function ( array $kinds ): array {
			$kinds[] = 'note';
			return $kinds;
		}
	);
	check( false === strpos( render( $video, 'core/video', 5, 'note' ), 'autoplay' ), 'kind filter can add note' );

	$GLOBALS['cr_admin'] = true;
	check( $video === render( $video, 'core/video', 12, 'video' ), 'admin render stays byte-identical' );
	$GLOBALS['cr_admin'] = false;
	$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/core/video';
	check( $video === render( $video, 'core/video', 12, 'video' ), 'REST block-renderer preview stays byte-identical' );
	unset( $_SERVER['REQUEST_URI'] );

	$registered = array();
	foreach ( $GLOBALS['cr_hooks']['render_block'] as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$registered[ $callback[0] ] = array( (int) $priority, $callback[1] );
		}
	}
	check( array( 9, 2 ) === $registered['Courtneyr\\Child\\PostKinds\\strip_media_autoplay'], 'strip hook is priority 9 with two arguments' );
	check( array( 10, 2 ) === $registered['Courtneyr\\Child\\PostKinds\\embed_block'], 'embed hook remains priority 10 with two arguments' );
	add_filter(
		'render_block',
		static function ( string $html ): string {
			$GLOBALS['cr_order'][] = false === strpos( $html, 'autoplay=1' ) ? 'stripped' : 'not-stripped';
			return $html;
		},
		9,
		1
	);
	$GLOBALS['cr_order'] = array();
	$GLOBALS['cr_current_post'] = 12;
	$GLOBALS['cr_terms']        = array( 12 => 'video' );
	apply_filters(
		'render_block',
		'<iframe src="https://www.youtube.com/embed/abcdefghijk?autoplay=1"></iframe>',
		array(
			'blockName' => 'core/embed',
			'attrs'     => array( 'url' => 'https://www.youtube.com/watch?v=abcdefghijk' ),
		)
	);
	check( array( 'stripped', 'shortcode' ) === $GLOBALS['cr_order'], 'autoplay stripping runs before the Able Player embed callback' );

	$GLOBALS['cr_shortcode_exists'] = false;
	check( '' === ableplayer_audio( 7 ), 'audio helper is empty without the shortcode' );
	$GLOBALS['cr_shortcode_exists'] = true;
	$GLOBALS['cr_attachment_audio'] = false;
	check( '' === ableplayer_audio( 7 ), 'audio helper rejects a non-audio attachment' );
	$GLOBALS['cr_attachment_audio'] = true;
	check( '' === ableplayer_audio( 0 ), 'audio helper rejects attachment zero' );
	$GLOBALS['cr_shortcode_calls'] = array();
	ableplayer_audio( 7 );
	check( array( '[ableplayer media-id=7 preload=metadata autoplay=false]' ) === $GLOBALS['cr_shortcode_calls'], 'audio helper sends the exact Able Player shortcode' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
