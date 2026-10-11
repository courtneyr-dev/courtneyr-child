<?php
/**
 * `php tests/php/w3-t0-enqueue-test.php`: checks W3-T0 shared ticket/sticker
 * registration and Remove Unused CSS exclusions without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'CR_W3_T0_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']      = array();
	$GLOBALS['cr_styles']     = array();
	$GLOBALS['cr_registered'] = array();
	$GLOBALS['cr_request']    = array();

	/** Register a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Register a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Check whether a filter has callbacks. */
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['cr_hooks'][ $hook ] ) ) {
			return false;
		}
		foreach ( $GLOBALS['cr_hooks'][ $hook ] as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( false === $callback || $registered[0] === $callback ) {
					return (int) $priority;
				}
			}
		}
		return false;
	}

	/** Return a registered callback's accepted arg count. */
	function accepted_args_for( string $hook, string $callback, int $priority = 10 ): int {
		foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] ?? array() as $registered ) {
			if ( $registered[0] === $callback ) {
				return (int) $registered[1];
			}
		}
		return 0;
	}

	/** Record an enqueued stylesheet. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		if ( '' === $src && isset( $GLOBALS['cr_registered'][ $handle ] ) ) {
			$GLOBALS['cr_styles'][ $handle ] = $GLOBALS['cr_registered'][ $handle ];
			return;
		}
		$GLOBALS['cr_styles'][ $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
		);
	}

	/** Record a registered stylesheet. */
	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_registered'][ $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
		);
	}

	/** Current taxonomy request. */
	function is_tax( $taxonomy = '', $term = '' ) {
		$current = $GLOBALS['cr_request']['kind'] ?? '';
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Admin flag. */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/** Archive flag. */
	function is_archive() {
		return '' !== ( $GLOBALS['cr_request']['kind'] ?? '' );
	}

	/** Singular flag. */
	function is_singular() {
		return ! empty( $GLOBALS['cr_request']['single'] );
	}

	/** Blog home flag. */
	function is_home() {
		return ! empty( $GLOBALS['cr_request']['home'] );
	}

	function is_404() { return false; }
	function is_page() { return false; }
	function is_search() { return false; }
	function is_feed() { return false; }
	function is_robots() { return false; }
	function is_user_logged_in() { return false; }
	function is_admin_bar_showing() { return false; }
	function has_term() { return false; }
	function get_the_terms() { return false; }
	function is_wp_error() { return false; }
	function get_post() { return null; }
	function esc_url( $url ) { return $url; }
	function strip_shortcodes( $text ) { return $text; }
	function wp_strip_all_tags( $text ) { return $text; }
	function wp_enqueue_script() {}
	function wp_add_inline_style() {}
	function wp_enqueue_block_style() {}
	function add_editor_style() {}
	function get_queried_object_id() { return 0; }
	function get_stylesheet_directory_uri() { return COURTNEYR_CHILD_URI; }
}

namespace Courtneyr\Child\AsideScrap {
	/** Stub: never an aside single here. */
	function is_aside_single(): bool {
		return false;
	}

	/** Stub: aside scrap never applies here. */
	function applies(): bool {
		return false;
	}
}

namespace {
	require CR_W3_T0_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\W3T0EnqueueTests {

	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'PASS ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Fire the asset hooks for one request. */
	function assets_for( array $request ): array {
		$GLOBALS['cr_request']    = $request;
		$GLOBALS['cr_styles']     = array();
		$GLOBALS['cr_registered'] = array();
		$hooks                    = empty( $request['admin'] )
			? array( 'wp_enqueue_scripts', 'enqueue_block_assets' )
			: array( 'enqueue_block_assets', 'enqueue_block_editor_assets', 'admin_enqueue_scripts' );
		foreach ( $hooks as $hook ) {
			$priorities = array_keys( $GLOBALS['cr_hooks'][ $hook ] ?? array() );
			sort( $priorities );
			foreach ( $priorities as $priority ) {
				foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] as $registered ) {
					$registered[0]();
				}
			}
		}
		return array(
			'registered' => $GLOBALS['cr_registered'],
			'enqueued'   => $GLOBALS['cr_styles'],
		);
	}

	/** Read a source file. */
	function source( string $relative ): string {
		$path = CR_W3_T0_THEME_DIR . '/' . $relative;
		return is_file( $path ) ? (string) file_get_contents( $path ) : '';
	}

	$rucss = perfmatters_rucss_exclusions( array() );
	foreach ( array( 'cr-review.css', 'cr-letters.css', 'cr-like.css', 'cr-bookmark.css', 'cr-paper.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS keeps existing W2 $file whole" );
	}
	foreach ( array( 'cr-play.css', 'cr-play-board.css', 'cr-play-video.css', 'cr-read-shelf.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS keeps existing W1 $file whole" );
	}
	foreach ( array( 'cr-chicken.css', 'chicken-entry.css', 'cr-photo.css', 'photo-projector.css', 'cr-favorite.css', 'cr-sticker.css', 'cr-acquisition.css', 'cr-ticket.css', 'cr-repost.css', 'article-stream-card.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS keeps W3 $file whole" );
	}

	$shared_callback = 'Courtneyr\\Child\\Enqueue\\register_shared_kind_styles';
	check( function_exists( $shared_callback ), 'register_shared_kind_styles exists' );
	check( 10 === \has_filter( 'enqueue_block_assets', $shared_callback ), 'register_shared_kind_styles is hooked on enqueue_block_assets at priority 10' );
	check( 1 === \accepted_args_for( 'enqueue_block_assets', $shared_callback ), 'register_shared_kind_styles accepts the default 1 arg' );

	$front = assets_for( array() );
	foreach ( array( 'courtneyr-ticket' => 'cr-ticket.css', 'courtneyr-sticker' => 'cr-sticker.css' ) as $handle => $file ) {
		$style = $front['registered'][ $handle ] ?? array();
		check( isset( $front['registered'][ $handle ] ), "front end registers $handle" );
		check( isset( $style['src'] ) && str_ends_with( $style['src'], '/assets/css/' . $file ), "front-end $handle src points to $file" );
		check( ( $style['version'] ?? null ) === COURTNEYR_CHILD_VERSION, "front-end $handle uses theme version" );
		check( array() === ( $style['deps'] ?? null ), "front-end $handle has no dependencies" );
		check( ! isset( $front['enqueued'][ $handle ] ), "front end does not enqueue $handle directly" );
	}

	$editor = assets_for( array( 'admin' => true ) );
	foreach ( array( 'courtneyr-ticket', 'courtneyr-sticker' ) as $handle ) {
		check( isset( $editor['enqueued'][ $handle ] ), "editor enqueues $handle" );
	}

	$eat_drink = source( 'inc/single-eat-drink.php' );
	check(
		(bool) preg_match( "/wp_enqueue_style\\s*\\(\\s*'courtneyr-eat-drink'\\s*,\\s*COURTNEYR_CHILD_URI\\s*\\.\\s*'\\/assets\\/css\\/cr-eat-drink\\.css'\\s*,\\s*array\\(\\s*'courtneyr-ticket'\\s*\\)/s", $eat_drink ),
		'single eat/drink stylesheet depends on courtneyr-ticket'
	);

	$like = source( 'inc/like.php' );
	check(
		(bool) preg_match( "/wp_enqueue_style\\s*\\(\\s*STYLE_HANDLE\\s*,\\s*COURTNEYR_CHILD_URI\\s*\\.\\s*'\\/assets\\/css\\/cr-like\\.css'\\s*,\\s*array\\(\\s*'courtneyr-paper'\\s*,\\s*'courtneyr-sticker'\\s*\\)/s", $like ),
		'like stylesheet depends on courtneyr-paper and courtneyr-sticker'
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
