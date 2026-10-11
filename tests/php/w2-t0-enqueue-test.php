<?php
/**
 * `php tests/php/w2-t0-enqueue-test.php`: checks W2-T0 shared paper enqueue
 * registration and Remove Unused CSS exclusions without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']      = array();
	$GLOBALS['cr_styles']     = array();
	$GLOBALS['cr_registered'] = array();
	$GLOBALS['cr_request']    = array();

	/** Register a hook callback. */
	function add_action( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Register a filter callback. */
	function add_filter( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Record an enqueued stylesheet. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
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
	require CR_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\W2T0EnqueueTests {

	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
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
			foreach ( $GLOBALS['cr_hooks'][ $hook ] ?? array() as $callback ) {
				$callback();
			}
		}
		return array(
			'registered' => $GLOBALS['cr_registered'],
			'enqueued'   => $GLOBALS['cr_styles'],
		);
	}

	$rucss = perfmatters_rucss_exclusions( array() );
	foreach ( array( 'cr-review.css', 'cr-letters.css', 'cr-like.css', 'cr-bookmark.css', 'cr-paper.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS keeps $file whole" );
	}
	foreach ( array( 'cr-play.css', 'cr-play-board.css', 'cr-play-video.css', 'cr-read-shelf.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS still keeps W1 $file whole" );
	}

	$front = assets_for( array() );
	check( isset( $front['registered']['courtneyr-paper'] ), 'front end registers courtneyr-paper' );
	$paper = $front['registered']['courtneyr-paper'] ?? array();
	check( isset( $paper['src'] ) && str_ends_with( $paper['src'], '/assets/css/cr-paper.css' ), 'front-end courtneyr-paper src points to cr-paper.css' );
	check( ( $paper['version'] ?? null ) === COURTNEYR_CHILD_VERSION, 'front-end courtneyr-paper uses theme version' );
	check( array() === ( $paper['deps'] ?? null ), 'front-end courtneyr-paper has no dependencies' );
	$front_paper_enqueues = array_filter(
		$front['enqueued'],
		static function ( array $style ): bool {
			return str_ends_with( $style['src'], '/assets/css/cr-paper.css' );
		}
	);
	check( array() === $front_paper_enqueues, 'front end does not enqueue cr-paper.css directly' );

	$editor = assets_for( array( 'admin' => true ) );
	check( isset( $editor['enqueued']['courtneyr-paper'] ), 'editor enqueues courtneyr-paper' );

	foreach ( array( '/kind/review/' => array( 'kind' => 'review' ), 'single' => array( 'single' => true ), 'blog home' => array( 'home' => true ) ) as $label => $request ) {
		$assets  = assets_for( $request );
		$matches = array_filter(
			$assets['enqueued'],
			static function ( array $style ): bool {
				return str_ends_with( $style['src'], '/assets/css/cr-paper.css' );
			}
		);
		check( array() === $matches, "$label enqueues no cr-paper.css stylesheet" );
	}

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
