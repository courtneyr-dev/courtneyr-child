<?php
/**
 * `php tests/php/note-board-enqueue-test.php`: checks that the W4 Note board
 * paint and its source contracts hold without WordPress.
 *
 * Loads inc/enqueue.php, inc/stamps.php and inc/sticky-note.php with stubbed
 * WordPress calls, fires the front-end and editor asset hooks, and reads the
 * enqueued theme stylesheets and board source files from disk. Exits 1 if any
 * check fails.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']   = array();
	$GLOBALS['cr_styles']  = array();
	$GLOBALS['cr_request'] = array();

	function add_action( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	function add_filter( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array() ) {
		$GLOBALS['cr_styles'][ $handle ] = array(
			'src'  => (string) $src,
			'deps' => (array) $deps,
		);
	}

	function is_tax( $taxonomy = '', $term = '' ) {
		$current = $GLOBALS['cr_request']['kind'] ?? '';
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	function is_archive() {
		return '' !== ( $GLOBALS['cr_request']['kind'] ?? '' );
	}

	function is_singular() {
		return ! empty( $GLOBALS['cr_request']['single'] );
	}

	function is_home() {
		return ! empty( $GLOBALS['cr_request']['home'] );
	}

	function is_404() {
		return false;
	}
	function is_page() {
		return false;
	}
	function is_search() {
		return false;
	}
	function is_feed() {
		return false;
	}
	function is_robots() {
		return false;
	}
	function is_user_logged_in() {
		return false;
	}
	function is_admin_bar_showing() {
		return false;
	}
	function has_term() {
		return false;
	}
	function get_the_terms() {
		return false;
	}
	function is_wp_error() {
		return false;
	}
	function get_post() {
		return null;
	}
	function get_the_ID() {
		return 0;
	}
	function esc_url( $url ) {
		return $url;
	}
	function strip_shortcodes( $text ) {
		return $text;
	}
	function wp_strip_all_tags( $text ) {
		return $text;
	}
	function __( $text ) {
		return $text;
	}
	function register_block_style() {}
	function wp_enqueue_script() {}
	function wp_register_script_module() {}
	function wp_enqueue_script_module() {}
	function wp_add_inline_style() {}
	function wp_enqueue_block_style() {}
	function add_editor_style() {}
	function wp_register_style() {}

	function get_queried_object_id() {
		return 0;
	}

	function get_stylesheet_directory_uri() {
		return COURTNEYR_CHILD_URI;
	}
}

namespace Courtneyr\Child\AsideScrap {
	function is_aside_single(): bool {
		return false;
	}

	function applies(): bool {
		return false;
	}
}

namespace {
	require CR_THEME_DIR . '/inc/enqueue.php';
	require CR_THEME_DIR . '/inc/stamps.php';
	require CR_THEME_DIR . '/inc/sticky-note.php';
}

namespace Courtneyr\Child\NoteBoard\Tests {

	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;

	$failures = array();

	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/**
	 * Fire the hooks WordPress fires for one request.
	 *
	 * @param array<string, mixed> $request Scenario: kind, admin, single, home.
	 * @return array<string, array{src: string, deps: string[]}>
	 */
	function styles_for( array $request ): array {
		$GLOBALS['cr_request'] = $request;
		$GLOBALS['cr_styles']  = array();
		$hooks                 = empty( $request['admin'] )
			? array( 'wp_enqueue_scripts', 'enqueue_block_assets' )
			: array( 'enqueue_block_assets', 'enqueue_block_editor_assets', 'admin_enqueue_scripts' );
		foreach ( $hooks as $hook ) {
			foreach ( $GLOBALS['cr_hooks'][ $hook ] ?? array() as $callback ) {
				$callback();
			}
		}
		return $GLOBALS['cr_styles'];
	}

	function source_path( array $styles, string $handle ): string {
		$src = $styles[ $handle ]['src'] ?? '';
		return 0 === strpos( $src, COURTNEYR_CHILD_URI . '/' )
			? substr( $src, strlen( COURTNEYR_CHILD_URI ) + 1 )
			: '';
	}

	function carries_board_rules( array $styles ): array {
		$out = array();
		foreach ( $styles as $style ) {
			$src = (string) ( $style['src'] ?? '' );
			if ( 0 !== strpos( $src, COURTNEYR_CHILD_URI . '/' ) ) {
				continue;
			}
			$file = substr( $src, strlen( COURTNEYR_CHILD_URI ) + 1 );
			if ( is_file( CR_THEME_DIR . '/' . $file ) && false !== strpos( (string) file_get_contents( CR_THEME_DIR . '/' . $file ), '.cr-note-board' ) ) {
				$out[] = $file;
			}
		}
		return $out;
	}

	$homes = array();
	$walk  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( CR_THEME_DIR . '/assets/css', \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walk as $file ) {
		if ( 'css' === $file->getExtension() && false !== strpos( (string) file_get_contents( $file->getPathname() ), '.cr-note-board' ) ) {
			$homes[] = substr( $file->getPathname(), strlen( CR_THEME_DIR ) + 1 );
		}
	}
	check( array( 'assets/css/cr-note-board.css' ) === $homes, 'exactly cr-note-board.css carries the .cr-note-board rules: ' . implode( ', ', $homes ) );
	check( is_file( CR_THEME_DIR . '/assets/css/cr-note-board.css' ), 'cr-note-board.css exists on disk' );

	foreach ( array( '/kind/note/' => array( 'kind' => 'note' ), 'editor (admin)' => array( 'admin' => true ) ) as $label => $request ) {
		$styles = styles_for( $request );
		check( 'assets/css/cr-note-board.css' === source_path( $styles, 'courtneyr-note-board' ), "$label enqueues cr-note-board.css" );
		check( 'assets/css/cr-post-kinds.css' === source_path( $styles, 'courtneyr-post-kinds' ), "$label enqueues cr-post-kinds.css" );
		check( array( 'courtneyr-post-kinds' ) === ( $styles['courtneyr-note-board']['deps'] ?? null ), "$label declares courtneyr-post-kinds as the note board dependency" );
		check( array( 'assets/css/cr-note-board.css' ) === carries_board_rules( $styles ), "$label loads one stylesheet carrying .cr-note-board" );
	}

	foreach ( array(
		'/kind/read/'   => array( 'kind' => 'read' ),
		'/kind/play/'   => array( 'kind' => 'play' ),
		'/kind/listen/' => array( 'kind' => 'listen' ),
		'blog home'     => array( 'home' => true ),
		'a single post' => array( 'single' => true ),
	) as $label => $request ) {
		$styles = styles_for( $request );
		check( ! isset( $styles['courtneyr-note-board'] ) && ! isset( $styles['courtneyr-post-kinds'] ), "$label loads neither note board stylesheet" );
	}

	$note_styles = styles_for( array( 'kind' => 'note' ) );
	check( ! isset( $note_styles['courtneyr-shelf-boards'] ), '/kind/note/ does not load cr-shelf-boards.css' );

	$rucss = perfmatters_rucss_exclusions( array() );
	check( in_array( 'cr-note-board.css', $rucss, true ), 'Remove Unused CSS keeps cr-note-board.css whole' );
	check( ! in_array( 'note-entry.css', $rucss, true ), 'Remove Unused CSS does not exclude note-entry.css' );

	$template = (string) file_get_contents( CR_THEME_DIR . '/templates/taxonomy-kind-note.html' );
	$pattern  = (string) file_get_contents( CR_THEME_DIR . '/patterns/cr-note-board-loop.php' );
	$months  = '/\\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\\b/i';
	check( 0 === preg_match( $months, $template ), 'the Note template has no hand-typed month name' );
	check( 0 === preg_match( $months, $pattern ), 'the Note board pattern has no hand-typed month name' );
	check( false !== strpos( $pattern, '"linesPerPage":12' ), 'the pattern keeps linesPerPage at 12' );
	check( false !== strpos( $pattern, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' ), 'the pattern keeps the approved empty-state string' );
	$board  = strpos( $template, 'courtneyr-child/cr-note-board-loop' );
	$browse = strpos( $template, 'courtneyr-child/cr-browse-all' );
	check( false !== $board && false !== $browse && $board < $browse, 'the Note template names the board loop before browse-all' );

	$supports = (string) file_get_contents( CR_THEME_DIR . '/inc/theme-supports.php' );
	$start    = strpos( $supports, 'function kind_archive_page_sizes()' );
	$end      = false === $start ? false : strpos( $supports, "\n}\n", $start );
	$list     = false === $start || false === $end ? '' : substr( $supports, $start, $end - $start );
	check( '' !== $list && false !== strpos( $list, 'courtneyr_child_kind_archive_page_sizes' ), 'theme-supports.php exposes the kind archive page-size list' );
	check( '' !== $list && 0 === preg_match( "/['\"]note['\"]\\s*=>/", $list ), 'the kind archive page-size list does not name note' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
