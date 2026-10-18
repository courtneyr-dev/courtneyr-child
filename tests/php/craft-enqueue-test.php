<?php
/**
 * `php tests/php/craft-enqueue-test.php`: checks craft stylesheet gates and
 * the small craft integration helpers without loading WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'CR_CRAFT_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']      = array();
	$GLOBALS['cr_styles']     = array();
	$GLOBALS['cr_registered'] = array();
	$GLOBALS['cr_request']    = array();

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Record an enqueued stylesheet. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false, $media = 'all' ) {
		if ( '' === $src && isset( $GLOBALS['cr_registered'][ $handle ] ) ) {
			$GLOBALS['cr_styles'][ $handle ] = $GLOBALS['cr_registered'][ $handle ];
			return;
		}
		$GLOBALS['cr_styles'][ $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
			'media'   => $media,
		);
	}

	/** Record a registered stylesheet. */
	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_registered'][ $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
			'media'   => 'all',
		);
	}

	/** Return the current taxonomy request. */
	function is_tax( $taxonomy = '', $term = '' ) {
		$current = $GLOBALS['cr_request']['kind'] ?? '';
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Return whether this is an admin request. */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/** Return whether this is any archive request. */
	function is_archive() {
		return '' !== ( $GLOBALS['cr_request']['kind'] ?? '' );
	}

	/** Return whether this is a single-post request. */
	function is_singular( $post_type = '' ) {
		return '' !== ( $GLOBALS['cr_request']['single_kind'] ?? '' ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Return whether the current single has the requested kind. */
	function has_term( $term = '', $taxonomy = '', $post = null ) {
		return 'kind' === $taxonomy && in_array( $GLOBALS['cr_request']['single_kind'] ?? '', (array) $term, true );
	}

	/** Return whether this is the posts page. */
	function is_home() {
		return ! empty( $GLOBALS['cr_request']['home'] );
	}

	/** Return whether this is a search request. */
	function is_search() {
		return ! empty( $GLOBALS['cr_request']['search'] );
	}

	/** Stub: never a not-found request. */
	function is_404() { return false; }

	/** Stub: never a page request. */
	function is_page() { return false; }

	/** Stub: never a feed request. */
	function is_feed() { return false; }

	/** Stub: never a robots request. */
	function is_robots() { return false; }

	/** Stub: no logged-in front-end user. */
	function is_user_logged_in() { return false; }

	/** Stub: no front-end admin bar. */
	function is_admin_bar_showing() { return false; }

	/** Stub: no term objects are needed. */
	function get_the_terms() { return false; }

	/** Stub: test values aren't errors. */
	function is_wp_error() { return false; }

	/** Stub: no post object is needed. */
	function get_post() { return null; }

	/** Stub: return a URL unchanged. */
	function esc_url( $url ) { return $url; }

	/** Stub: return text without shortcode work. */
	function strip_shortcodes( $text ) { return $text; }

	/** Stub: return text without tag work. */
	function wp_strip_all_tags( $text ) { return $text; }

	/** Stub: scripts aren't inspected by this test. */
	function wp_enqueue_script() {}

	/** Record an inline style by handle. */
	function wp_add_inline_style( $handle = '', $css = '' ) {
		$GLOBALS['cr_inline'][ $handle ][] = (string) $css;
		return true;
	}

	/** Return the scenario's featured attachment ID. */
	function get_post_thumbnail_id( $post = null ) {
		return (int) ( $GLOBALS['cr_request']['thumbnail'] ?? 0 );
	}

	/** Return the scenario's featured image source, as url, width and height. */
	function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
		return $GLOBALS['cr_request']['thumbnail_src'] ?? false;
	}

	/** Stub: per-block styles aren't inspected by this test. */
	function wp_enqueue_block_style() {}

	/** Stub: editor stylesheet registration isn't inspected. */
	function add_editor_style() {}

	/** Stub: block-style registration isn't inspected. */
	function register_block_style() {}

	/** Stub: return translated text unchanged. */
	function __( $text ) { return $text; }

	/** Stub: return one queried object ID. */
	function get_queried_object_id() { return 1; }

	/** Stub: return the test theme URI. */
	function get_stylesheet_directory_uri() { return COURTNEYR_CHILD_URI; }
}

namespace Courtneyr\Child\AsideScrap {
	/** Stub: never an aside single in these scenarios. */
	function is_aside_single(): bool {
		return false;
	}

	/** Stub: aside paint never applies in these scenarios. */
	function applies(): bool {
		return false;
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Return the scenario's Stream-surface flag. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_request']['stream_surface'] );
	}
}

namespace Courtneyr\Child\MediaShelf {
	/** Stub: tool_tag() isn't rendered by this enqueue test. */
	function cut_elements( string $html ): string {
		return $html;
	}
}

namespace {
	require CR_CRAFT_THEME_DIR . '/inc/craft.php';
	require CR_CRAFT_THEME_DIR . '/inc/single-craft.php';
	require CR_CRAFT_THEME_DIR . '/inc/stream-craft.php';
	require CR_CRAFT_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\CraftEnqueueTests {

	use function Courtneyr\Child\Craft\body_class as archive_body_class;
	use function Courtneyr\Child\Craft\is_peg_card;
	use function Courtneyr\Child\Craft\register_editor_parity;
	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;
	use function Courtneyr\Child\SingleCraft\body_class as single_body_class;
	use function Courtneyr\Child\SingleCraft\repeat_kinds;

	$failures = array();

	/** Print one result and remember a failure. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Fire one hook in WordPress priority order. */
	function fire( string $hook ): void {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities, SORT_NUMERIC );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$registered[0]();
			}
		}
	}

	/** Return enqueued theme styles as handle to relative file path. */
	function styles_for( array $request ): array {
		$GLOBALS['cr_request'] = $request;
		$GLOBALS['cr_styles']  = array();
		$hooks                 = empty( $request['admin'] )
			? array( 'wp_enqueue_scripts', 'enqueue_block_assets' )
			: array( 'enqueue_block_assets', 'enqueue_block_editor_assets', 'admin_enqueue_scripts' );
		foreach ( $hooks as $hook ) {
			fire( $hook );
		}
		$styles = array();
		foreach ( $GLOBALS['cr_styles'] as $handle => $details ) {
			$src = (string) ( $details['src'] ?? '' );
			if ( str_starts_with( $src, COURTNEYR_CHILD_URI . '/' ) ) {
				$styles[ $handle ] = substr( $src, strlen( COURTNEYR_CHILD_URI ) + 1 );
			}
		}
		return $styles;
	}

	/** Return enqueued stylesheets containing the craft Stream-tag rules. */
	function craft_sheets( array $styles ): array {
		return array_values(
			array_filter(
				$styles,
				static function ( string $file ): bool {
					return false !== strpos( (string) file_get_contents( CR_CRAFT_THEME_DIR . '/' . $file ), '.cr-craft-tag' );
				}
			)
		);
	}

	$homes = array();
	$walk  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( CR_CRAFT_THEME_DIR . '/assets/css', \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walk as $file ) {
		if ( 'css' === $file->getExtension() && false !== strpos( (string) file_get_contents( $file->getPathname() ), '.cr-craft-tag' ) ) {
			$homes[] = substr( $file->getPathname(), strlen( CR_CRAFT_THEME_DIR ) + 1 );
		}
	}
	check( array( 'assets/css/cr-craft.css' ) === $homes, 'exactly one theme stylesheet carries .cr-craft-tag rules' );

	$wanted = array(
		'/kind/craft/'         => array( 'kind' => 'craft' ),
		'a craft single'       => array( 'single_kind' => 'craft' ),
		'/stream/'             => array( 'stream_surface' => true ),
		'front-page Stream'    => array( 'stream_surface' => true, 'front_page' => true ),
		'the block editor'     => array( 'admin' => true ),
	);
	foreach ( $wanted as $label => $request ) {
		$styles = styles_for( $request );
		$sheets = craft_sheets( $styles );
		check( array( 'assets/css/cr-craft.css' ) === $sheets, "$label enqueues exactly one craft stylesheet" );
		check( 'assets/css/cr-craft.css' === ( $styles['courtneyr-craft'] ?? '' ), "$label uses handle courtneyr-craft for cr-craft.css" );
		$file = CR_CRAFT_THEME_DIR . '/' . ( $styles['courtneyr-craft'] ?? '' );
		$css  = is_file( $file ) ? (string) file_get_contents( $file ) : '';
		check( is_file( $file ), "$label points at an existing cr-craft.css file" );
		check( false !== strpos( $css, '.cr-peg-card' ) && false !== strpos( $css, '.cr-craft-tag' ) && false !== strpos( $css, '.pkiw-craft-section' ), "$label sheet carries archive, Stream and single craft rules" );
	}

	$unwanted = array(
		'/kind/note/'       => array( 'kind' => 'note' ),
		'/kind/play/'       => array( 'kind' => 'play' ),
		'blog home'         => array( 'home' => true ),
		'a non-craft single'=> array( 'single_kind' => 'note' ),
		'search results'    => array( 'search' => true ),
	);
	foreach ( $unwanted as $label => $request ) {
		check( array() === craft_sheets( styles_for( $request ) ), "$label enqueues no stylesheet carrying .cr-craft-tag" );
	}

	check( in_array( 'cr-craft.css', perfmatters_rucss_exclusions( array() ), true ), 'Remove Unused CSS keeps cr-craft.css whole' );

	$ratio_cases = array(
		'a craft single with a 640x480 cover' => array( array( 'single_kind' => 'craft', 'thumbnail' => 7, 'thumbnail_src' => array( 'cover.png', 640, 480 ) ), array( 'body.cr-craft-single{--craft-cover-ratio:0.7500;}' ) ),
		'a craft single with no cover'        => array( array( 'single_kind' => 'craft' ), array() ),
		'a non-craft single with a cover'     => array( array( 'single_kind' => 'note', 'thumbnail' => 7, 'thumbnail_src' => array( 'cover.png', 640, 480 ) ), array() ),
	);
	foreach ( $ratio_cases as $label => list( $request, $expected ) ) {
		$GLOBALS['cr_inline'] = array();
		styles_for( $request );
		check( $expected === ( $GLOBALS['cr_inline']['courtneyr-craft'] ?? array() ), "$label prints the expected cover ratio" );
	}

	$GLOBALS['cr_request'] = array( 'kind' => 'craft' );
	check( is_peg_card( array() ), 'is_peg_card is true on the craft archive' );
	$GLOBALS['cr_request'] = array( 'kind' => 'note' );
	check( is_peg_card( array( 'attrs' => array( 'className' => 'other is-style-cr-peg-card class' ) ) ), 'is_peg_card is true for the peg-card block style' );
	check( ! is_peg_card( array() ), 'is_peg_card is false on another kind archive without the class' );

	$parity = register_editor_parity(
		array(
			'note'  => array( 'classes' => array( 'keep-me' ) ),
			'craft' => array( 'classes' => array( 'single-post', 'existing-craft' ) ),
		)
	);
	check( in_array( 'cr-craft-single', $parity['craft']['classes'], true ), 'register_editor_parity adds cr-craft-single to craft' );
	check( array( 'keep-me' ) === $parity['note']['classes'] && in_array( 'existing-craft', $parity['craft']['classes'], true ), 'register_editor_parity keeps existing registry classes' );

	$repeated = repeat_kinds( repeat_kinds( array( 'photo' ) ) );
	check( array( 'photo', 'craft' ) === $repeated, 'repeat_kinds keeps photo and adds craft once across repeated calls' );

	$GLOBALS['cr_request'] = array( 'kind' => 'craft' );
	check( in_array( 'cr-craft-archive', archive_body_class( array() ), true ), 'body_class adds cr-craft-archive on the archive' );
	check( ! in_array( 'cr-craft-single', single_body_class( array() ), true ), 'body_class does not add cr-craft-single on the archive' );
	$GLOBALS['cr_request'] = array( 'single_kind' => 'craft' );
	check( in_array( 'cr-craft-single', single_body_class( array() ), true ), 'body_class adds cr-craft-single on a craft single' );
	check( ! in_array( 'cr-craft-archive', archive_body_class( array() ), true ), 'body_class does not add cr-craft-archive on a craft single' );
	$GLOBALS['cr_request'] = array( 'single_kind' => 'note' );
	check( ! in_array( 'cr-craft-single', single_body_class( array() ), true ) && ! in_array( 'cr-craft-archive', archive_body_class( array() ), true ), 'body_class adds neither craft class elsewhere' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
