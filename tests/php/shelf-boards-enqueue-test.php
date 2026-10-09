<?php
/**
 * `php tests/php/shelf-boards-enqueue-test.php`: checks that the W1 shelf-board
 * paint (.cr-shelf-boards, X13) reaches the play and read archives and the
 * editor, without WordPress.
 *
 * Loads inc/enqueue.php with stubbed WordPress calls, fires the asset hooks
 * WordPress fires for each request type (wp_enqueue_scripts, then
 * enqueue_block_assets on the front end; enqueue_block_assets,
 * enqueue_block_editor_assets and admin_enqueue_scripts in the editor), and
 * reads every enqueued theme stylesheet from disk. Exits 1 if any check fails.
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

	/**
	 * Stub: record a hook callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function add_action( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/**
	 * Stub: record a filter callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function add_filter( $hook, $callback ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/**
	 * Stub: record an enqueued stylesheet by handle.
	 *
	 * @param string $handle Handle.
	 * @param string $src    URL.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '' ) {
		$GLOBALS['cr_styles'][ $handle ] = (string) $src;
	}

	/**
	 * Stub: the request's taxonomy archive.
	 *
	 * @param string          $taxonomy Taxonomy.
	 * @param string|string[] $term     Term slug or slugs.
	 * @return bool
	 */
	function is_tax( $taxonomy = '', $term = '' ) {
		$current = $GLOBALS['cr_request']['kind'] ?? '';
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/**
	 * Stub: true in the editor scenario.
	 *
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/**
	 * Stub: true on a kind archive.
	 *
	 * @return bool
	 */
	function is_archive() {
		return '' !== ( $GLOBALS['cr_request']['kind'] ?? '' );
	}

	/**
	 * Stub: true on the single-post scenario.
	 *
	 * @return bool
	 */
	function is_singular() {
		return ! empty( $GLOBALS['cr_request']['single'] );
	}

	/**
	 * Stub: true on the blog home scenario.
	 *
	 * @return bool
	 */
	function is_home() {
		return ! empty( $GLOBALS['cr_request']['home'] );
	}

	// The rest answer "no", pass their input through or do nothing: none of
	// them decides whether the board paint loads.
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
	function esc_url( $url ) {
		return $url;
	}
	function strip_shortcodes( $text ) {
		return $text;
	}
	function wp_strip_all_tags( $text ) {
		return $text;
	}
	function wp_enqueue_script() {}
	function wp_add_inline_style() {}
	function wp_enqueue_block_style() {}
	function add_editor_style() {}
	function wp_register_style() {}

	/**
	 * Stub: no queried object.
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		return 0;
	}

	/**
	 * Stub: the theme URI.
	 *
	 * @return string
	 */
	function get_stylesheet_directory_uri() {
		return COURTNEYR_CHILD_URI;
	}
}

namespace Courtneyr\Child\AsideScrap {
	/**
	 * Stub: never an aside single here.
	 *
	 * @return bool
	 */
	function is_aside_single(): bool {
		return false;
	}

	/**
	 * Stub: the aside scrap never applies here.
	 *
	 * @return bool
	 */
	function applies(): bool {
		return false;
	}
}

namespace {
	require CR_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\ShelfBoards\Tests {

	use function Courtneyr\Child\Enqueue\perfmatters_rucss_exclusions;

	$failures = array();

	/**
	 * Print one result and remember a failure.
	 *
	 * @param bool   $ok      Passed.
	 * @param string $message What was checked.
	 * @return void
	 */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/**
	 * Fire the asset hooks for one request and return the enqueued theme
	 * stylesheets as handle => path relative to the theme.
	 *
	 * @param array<string, mixed> $request Scenario: kind, admin, single, home.
	 * @return array<string, string>
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
		$out = array();
		foreach ( $GLOBALS['cr_styles'] as $handle => $src ) {
			if ( 0 === strpos( $src, COURTNEYR_CHILD_URI . '/' ) ) {
				$out[ $handle ] = substr( $src, strlen( COURTNEYR_CHILD_URI ) + 1 );
			}
		}
		return $out;
	}

	/**
	 * The enqueued stylesheets that carry the board rules.
	 *
	 * @param array<string, string> $styles Handle => relative path.
	 * @return string[] Relative paths.
	 */
	function board_sheets( array $styles ): array {
		return array_values(
			array_filter(
				$styles,
				static function ( string $file ): bool {
					$css = (string) file_get_contents( CR_THEME_DIR . '/' . $file );
					return false !== strpos( $css, '.cr-shelf-boards' );
				}
			)
		);
	}

	// One home for the .cr-shelf-boards rules, loaded on its own.
	$homes = array();
	$walk  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( CR_THEME_DIR . '/assets/css', \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walk as $file ) {
		if ( 'css' === $file->getExtension() && false !== strpos( (string) file_get_contents( $file->getPathname() ), '.cr-shelf-boards' ) ) {
			$homes[] = substr( $file->getPathname(), strlen( CR_THEME_DIR ) + 1 );
		}
	}
	check( 1 === count( $homes ), 'exactly one stylesheet carries the .cr-shelf-boards rules: ' . implode( ', ', $homes ) );
	$home = $homes[0] ?? '';

	// The listen and watch shelf rules stay out of it.
	$home_css = '' === $home ? '' : (string) file_get_contents( CR_THEME_DIR . '/' . $home );
	foreach ( array( '.cr-media-shelf', '.cr-vhs', '.cr-archive--watch', '.cr-archive--listen' ) as $selector ) {
		check( '' !== $home_css && false === strpos( $home_css, $selector ), "the board sheet ($home) carries no $selector rules" );
	}

	// Loaded where the play and read templates and the Site Editor need it.
	$want = array(
		'/kind/play/'    => array( 'kind' => 'play' ),
		'/kind/read/'    => array( 'kind' => 'read' ),
		'editor (admin)' => array( 'admin' => true ),
	);
	foreach ( $want as $label => $request ) {
		$sheets = board_sheets( styles_for( $request ) );
		check( array( $home ) === $sheets, "$label loads the board paint: " . ( $sheets ? implode( ', ', $sheets ) : 'no enqueued sheet carries .cr-shelf-boards' ) );
	}

	// And nowhere else.
	$skip = array(
		'/kind/listen/'  => array( 'kind' => 'listen' ),
		'/kind/watch/'   => array( 'kind' => 'watch' ),
		'/kind/note/'    => array( 'kind' => 'note' ),
		'blog home'      => array( 'home' => true ),
		'a single post'  => array( 'single' => true ),
	);
	foreach ( $skip as $label => $request ) {
		$sheets = board_sheets( styles_for( $request ) );
		check( array() === $sheets, "$label doesn't load the board paint" . ( $sheets ? ': ' . implode( ', ', $sheets ) : '' ) );
	}

	// Every /kind/* archive shares one used-CSS file under Remove Unused CSS,
	// so the board sheet has to stay whole like the other archive sheets.
	$rucss = perfmatters_rucss_exclusions( array() );
	check( '' !== $home && in_array( basename( $home ), $rucss, true ), 'Remove Unused CSS keeps ' . ( '' === $home ? 'the board sheet' : basename( $home ) ) . ' whole' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
