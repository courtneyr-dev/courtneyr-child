<?php
/**
 * `php tests/php/w5-t0-enqueue-test.php`: checks W5 stylesheet routing
 * without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_W5_T0_THEME_DIR', dirname( __DIR__, 2 ) );
	$GLOBALS['cr_theme_dir'] = CR_W5_T0_THEME_DIR;

	$GLOBALS['cr_hooks']   = array();
	$GLOBALS['cr_request'] = array();
	$GLOBALS['cr_styles']  = array();
	$GLOBALS['cr_facts']   = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $deps, $version );
	}

	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	function is_tax( $taxonomy = '', $term = '' ) {
		$current = $GLOBALS['cr_request']['kind'] ?? '';
		return 'kind' === $taxonomy && '' !== $current && ( '' === $term || in_array( $current, (array) $term, true ) );
	}

	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function get_theme_file_path( $file = '' ) {
		return $GLOBALS['cr_theme_dir'] . '/' . ltrim( (string) $file, '/' );
	}

	function get_query_var( $name ) {
		return $GLOBALS['cr_request'][ $name ] ?? '';
	}

	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['post_id'] ?? 0 );
	}

	require CR_W5_T0_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\W5T0EnqueueTests {

	use function Courtneyr\Child\Enqueue\enqueue_podcast_styles;
	use function Courtneyr\Child\Enqueue\enqueue_shelf_boards;
	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;

	$GLOBALS['cr_failures'] = array();

	function check( bool $ok, string $message ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_failures'][] = $message;
		}
	}

	function styles_for( array $request, callable $callback ): array {
		$GLOBALS['cr_request'] = $request;
		$GLOBALS['cr_styles']  = array();
		$callback();
		return $GLOBALS['cr_styles'];
	}

	$required = array( 'cr-video.css', 'cr-audio.css', 'cr-jam.css', 'cr-podcast.css', 'cr-wish.css', 'cr-question.css', 'podcast-lineup.css', 'question-answers.css' );
	$rucss    = perfmatters_rucss_exclusions( array( 'caller.css' ) );
	foreach ( $required as $file ) {
		check( in_array( $file, $rucss, true ), "RUCSS keeps $file" );
	}
	check( in_array( 'cr-shelf-boards.css', $rucss, true ) && in_array( 'cr-note-board.css', $rucss, true ), 'RUCSS keeps existing W1 and W4 entries' );
	check( in_array( 'caller.css', $rucss, true ), "RUCSS keeps the caller's entry" );
	check( count( $rucss ) === count( array_unique( $rucss ) ), 'RUCSS exclusions contain no duplicates' );

	foreach ( array( 'video', 'play', 'read' ) as $kind ) {
		$styles = styles_for( array( 'kind' => $kind ), 'Courtneyr\\Child\\Enqueue\\enqueue_shelf_boards' );
		check( isset( $styles['courtneyr-shelf-boards'] ), "$kind archive loads shelf-board paint" );
	}
	foreach ( array( 'listen', 'watch', 'note' ) as $kind ) {
		$styles = styles_for( array( 'kind' => $kind ), 'Courtneyr\\Child\\Enqueue\\enqueue_shelf_boards' );
		check( ! isset( $styles['courtneyr-shelf-boards'] ), "$kind archive skips shelf-board paint" );
	}
	check( array() === styles_for( array( 'home' => true ), 'Courtneyr\\Child\\Enqueue\\enqueue_shelf_boards' ), 'blog home skips shelf-board paint' );
	check( array() === styles_for( array( 'single' => true ), 'Courtneyr\\Child\\Enqueue\\enqueue_shelf_boards' ), 'single post skips shelf-board paint' );

	$hooked = false;
	foreach ( $GLOBALS['cr_hooks']['enqueue_block_assets'] ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $registered ) {
			if ( 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' === $registered[0] ) {
				$hooked = true;
			}
		}
	}
	check( $hooked, 'podcast styles hook enqueue_block_assets' );

	$no_plugin_single = styles_for( array( 'single' => true, 'post_id' => 1 ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	check( ! isset( $no_plugin_single['courtneyr-podcast'] ), 'single skips podcast CSS when the plugin function is undefined' );
}

namespace PKIW {
	if ( ! function_exists( __NAMESPACE__ . '\\kind_facts' ) ) {
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_facts'][ $post_id ] ?? array();
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	if ( ! function_exists( __NAMESPACE__ . '\\is_stream_surface' ) ) {
		function is_stream_surface(): bool {
			return ! empty( $GLOBALS['cr_request']['stream'] );
		}
	}
}

namespace Courtneyr\Child\W5T0EnqueueTests {

	$theme_dir = sys_get_temp_dir() . '/w5-t0-theme-' . getmypid();
	mkdir( $theme_dir . '/assets/css', 0777, true );
	touch( $theme_dir . '/assets/css/cr-podcast.css' );
	$GLOBALS['cr_theme_dir'] = $theme_dir;

	$skip = array(
		'plain listen archive' => array( 'kind' => 'listen' ),
		'watch archive'        => array( 'kind' => 'watch' ),
		'video archive'        => array( 'kind' => 'video' ),
		'blog home'            => array( 'home' => true ),
		'editor'               => array( 'admin' => true, 'kind' => 'listen', 'pkiw_listen_type' => 'podcast' ),
	);
	foreach ( $skip as $label => $request ) {
		$styles = styles_for( $request, 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
		check( ! isset( $styles['courtneyr-podcast'] ), "$label skips podcast CSS" );
	}

	foreach ( array( array(), array( 'name' => 'Listen' ), array( 'is_podcast' => false ) ) as $facts ) {
		$GLOBALS['cr_facts'] = array( 7 => $facts );
		$styles              = styles_for( array( 'single' => true, 'post_id' => 7 ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
		check( ! isset( $styles['courtneyr-podcast'] ), 'non-podcast single facts skip podcast CSS' );
	}

	$view_styles = styles_for( array( 'kind' => 'listen', 'pkiw_listen_type' => 'podcast' ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	check( isset( $view_styles['courtneyr-podcast'] ), 'podcast listen view loads podcast CSS' );
	$GLOBALS['cr_facts'] = array( 8 => array( 'is_podcast' => true ) );
	$single_styles       = styles_for( array( 'single' => true, 'post_id' => 8 ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	check( isset( $single_styles['courtneyr-podcast'] ), 'podcast single loads podcast CSS' );
	$stream_styles = styles_for( array( 'stream' => true ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	check( isset( $stream_styles['courtneyr-podcast'] ), 'Stream surface loads podcast CSS' );

	foreach ( array( $view_styles, $single_styles, $stream_styles ) as $styles ) {
		check(
			array( COURTNEYR_CHILD_URI . '/assets/css/cr-podcast.css', array(), COURTNEYR_CHILD_VERSION ) === $styles['courtneyr-podcast'],
			'podcast CSS uses the required source, version and empty dependencies'
		);
	}

	$GLOBALS['cr_facts'] = array( 9 => array( 'name' => 'Old plugin facts' ) );
	$old_view            = styles_for( array( 'kind' => 'listen' ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	$old_single          = styles_for( array( 'single' => true, 'post_id' => 9 ), 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
	check( ! isset( $old_view['courtneyr-podcast'] ) && ! isset( $old_single['courtneyr-podcast'] ), 'old plugin without query var or fact loads no podcast CSS' );

	// The sheet belongs to the podcast lane: while the file is missing, nothing is enqueued on any of the three surfaces.
	unlink( $theme_dir . '/assets/css/cr-podcast.css' );
	$GLOBALS['cr_facts'] = array( 8 => array( 'is_podcast' => true ) );
	foreach ( array( array( 'kind' => 'listen', 'pkiw_listen_type' => 'podcast' ), array( 'single' => true, 'post_id' => 8 ), array( 'stream' => true ) ) as $request ) {
		$styles = styles_for( $request, 'Courtneyr\\Child\\Enqueue\\enqueue_podcast_styles' );
		check( ! isset( $styles['courtneyr-podcast'] ), 'missing cr-podcast.css enqueues nothing' );
	}
	rmdir( $theme_dir . '/assets/css' );
	rmdir( $theme_dir . '/assets' );
	rmdir( $theme_dir );

	echo "\n", count( $GLOBALS['cr_failures'] ) ? count( $GLOBALS['cr_failures'] ) . ' failed' : 'all passed', "\n";
	exit( count( $GLOBALS['cr_failures'] ) ? 1 : 0 );
}
