<?php
/**
 * `php tests/php/w4-t0-enqueue-test.php`: checks W4-T0 calendar block styles
 * and Remove Unused CSS exclusions without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'CR_W4_T0_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']      = array();
	$GLOBALS['cr_styles']     = array();
	$GLOBALS['cr_registered'] = array();
	$GLOBALS['cr_request']    = array();
	$GLOBALS['cr_block_styles'] = array();

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
	/** Record a block style call. */
	function wp_enqueue_block_style( $block_name, $args ) {
		$GLOBALS['cr_block_styles'][] = array( $block_name, $args );
	}
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
	require CR_W4_T0_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\W4T0EnqueueTests {

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

	$rucss = perfmatters_rucss_exclusions( array() );
	foreach ( array( 'cr-calendar.css', 'cr-mood-year.css', 'mood-year-grid.css', 'cr-weather.css', 'cr-exercise.css', 'exercise-entry.css', 'medal-row.css', 'cr-sleep.css', 'sleep-night.css', 'cr-craft.css', 'cr-note-board.css' ) as $file ) {
		check( in_array( $file, $rucss, true ), "Remove Unused CSS keeps W4 $file whole" );
	}
	check( ! in_array( 'note-entry.css', $rucss, true ), 'note-entry.css stays off the Remove Unused CSS list' );
	check( in_array( 'cr-photo.css', $rucss, true ) && in_array( 'cr-print.css', $rucss, true ), 'earlier entries are still listed' );
	check( count( $rucss ) === count( array_unique( $rucss ) ), 'the list has no duplicates' );

	$callback = 'Courtneyr\\Child\\Enqueue\\register_calendar_styles';
	check( function_exists( $callback ), 'register_calendar_styles exists' );
	check( 9 === \has_filter( 'init', $callback ), 'register_calendar_styles runs on init at priority 9, ahead of the kind files at 10 (print order comes from dependencies, not call order)' );
	$callback();

	$blocks = array();
	foreach ( $GLOBALS['cr_block_styles'] as $call ) {
		$blocks[ $call[0] ] = $call[1];
	}
	check( array( 'post-kinds-indieweb/mood-year-grid', 'post-kinds-indieweb/almanac-month', 'post-kinds-indieweb/exercise-entry' ) === array_keys( $blocks ), 'the calendar sheet attaches to mood-year-grid, almanac-month and exercise-entry' );
	check( ! isset( $blocks['post-kinds-indieweb/sleep-night'] ), 'sleep-night does not load the calendar sheet' );
	foreach ( $blocks as $name => $args ) {
		check( 'courtneyr-calendar' === ( $args['handle'] ?? '' ), "$name uses the courtneyr-calendar handle" );
		check( COURTNEYR_CHILD_URI . '/assets/css/cr-calendar.css' === ( $args['src'] ?? '' ), "$name src is cr-calendar.css" );
		check( COURTNEYR_CHILD_DIR . '/assets/css/cr-calendar.css' === ( $args['path'] ?? '' ), "$name path is cr-calendar.css" );
		check( COURTNEYR_CHILD_VERSION === ( $args['ver'] ?? '' ), "$name uses the theme version" );
		check( ! isset( $args['deps'] ), "$name sheet has no dependency of its own" );
	}

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
