<?php
/**
 * `php tests/php/w2-t233-editor-parity-test.php`: checks the editor-parity
 * registry and its editor script registration without WordPress (PKIW issue 233).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );

	$GLOBALS['t233'] = array(
		'terms'      => array(),
		'kind_terms' => array(),
		'parts'      => array(),
		'hooks'      => array(),
		'screen'     => null,
		'scripts'    => array(),
		'enqueued'   => array(),
		'inline'     => array(),
	);

	class WP_Error {}

	/** WordPress sanitize_key(). */
	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	/** WordPress sanitize_html_class(). */
	function sanitize_html_class( $class, $fallback = '' ): string {
		$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
		return '' !== $sanitized ? $sanitized : (string) $fallback;
	}

	/** Record a hook callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['t233']['hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Run the callbacks recorded for a filter. */
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['t233']['hooks'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}

	/** Terms for a post (unused here, kept for the router). */
	function get_the_terms( $post_id, $taxonomy ) {
		return $GLOBALS['t233']['terms'][ $post_id ] ?? false;
	}

	/** All kind term slugs, set per test. */
	function get_terms( $args ) {
		return $GLOBALS['t233']['kind_terms'];
	}

	/** WordPress is_wp_error(). */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}

	/** Active stylesheet. */
	function get_stylesheet(): string {
		return 'courtneyr-child';
	}

	/** A block template exists only for slugs listed in the test's parts. */
	function get_block_template( $id, $template_type = 'wp_template' ) {
		$slug = substr( (string) $id, strlen( 'courtneyr-child//' ) );
		return ( 'wp_template_part' === $template_type && in_array( $slug, $GLOBALS['t233']['parts'], true ) ) ? (object) array( 'slug' => $slug ) : null;
	}

	/** Singular check. */
	function is_singular( $post_types = '' ): bool {
		return false;
	}

	/** Queried post. */
	function get_queried_object_id(): int {
		return 0;
	}

	/** Render a block. */
	function render_block( array $block ): string {
		return '';
	}

	/** JSON encoding. */
	function wp_json_encode( $value, int $flags = 0, int $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}

	/** The current admin screen, set per test. */
	function get_current_screen() {
		return $GLOBALS['t233']['screen'];
	}

	/** Record a registered script. */
	function wp_register_script( $handle, $src = '', $deps = array(), $version = false, $args = array() ): bool {
		$GLOBALS['t233']['scripts'][ $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
			'args'    => $args,
		);
		return true;
	}

	/** Record an enqueued script. */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $args = array() ): void {
		$GLOBALS['t233']['enqueued'][] = $handle;
	}

	/** Record inline script data. */
	function wp_add_inline_script( $handle, $data, $position = 'after' ): bool {
		$GLOBALS['t233']['inline'][] = array( $handle, $data, $position );
		return true;
	}

	$GLOBALS['t233_failures'] = 0;

	/** Report one check. */
	function t233_check( string $label, bool $ok ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$GLOBALS['t233_failures'];
		}
	}

	/** Reset per-test state, keeping the hooks the files registered at load. */
	function t233_reset(): void {
		$GLOBALS['t233']['terms']      = array();
		$GLOBALS['t233']['kind_terms'] = array();
		$GLOBALS['t233']['parts']      = array();
		$GLOBALS['t233']['screen']     = null;
		$GLOBALS['t233']['scripts']    = array();
		$GLOBALS['t233']['enqueued']   = array();
		$GLOBALS['t233']['inline']     = array();
		unset( $GLOBALS['t233']['hooks']['courtneyr_child_editor_parity_kinds'] );
	}

	require dirname( __DIR__, 2 ) . '/inc/kind-parts.php';
	require dirname( __DIR__, 2 ) . '/inc/editor-parity.php';
}

namespace {
	use function Courtneyr\Child\EditorParity\canvas_classes;
	use function Courtneyr\Child\EditorParity\editor_data;
	use function Courtneyr\Child\EditorParity\enqueue;
	use function Courtneyr\Child\EditorParity\inline_script;
	use function Courtneyr\Child\EditorParity\kinds;

	$on_enqueue = $GLOBALS['t233']['hooks']['enqueue_block_editor_assets'] ?? array();
	t233_check( 'the editor script hooks enqueue_block_editor_assets', array( 'Courtneyr\\Child\\EditorParity\\enqueue' ) === $on_enqueue );

	// The registry.
	t233_reset();
	$GLOBALS['t233']['parts']      = array( 'kind-listen' );
	$GLOBALS['t233']['kind_terms'] = array( 'listen', 'watch' );
	t233_check(
		'kinds lists every kind term with the front-end body classes and its part',
		array(
			'listen' => array(
				'classes' => array( 'single-post', 'kind-listen' ),
				'part'    => 'kind-listen',
			),
			'watch'  => array(
				'classes' => array( 'single-post', 'kind-watch' ),
				'part'    => '',
			),
		) === kinds()
	);

	t233_reset();
	t233_check( 'kinds is empty with no kind terms and no filter', array() === kinds() );

	// A kind lane adds its own kind through the filter.
	t233_reset();
	$GLOBALS['t233']['parts']      = array( 'kind-listen' );
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	add_filter(
		'courtneyr_child_editor_parity_kinds',
		static function ( array $kinds ): array {
			$kinds['chicken']             = array( 'classes' => array( 'single-post', 'kind-chicken', 'cr-chicken-board' ) );
			$kinds['listen']['classes'][] = 'cr-listen-extra';
			return $kinds;
		}
	);
	$registered = kinds();
	t233_check(
		'a filter registers a kind that has no term yet, with an empty part',
		array(
			'classes' => array( 'single-post', 'kind-chicken', 'cr-chicken-board' ),
			'part'    => '',
		) === ( $registered['chicken'] ?? null )
	);
	t233_check(
		'a filter adds a class to a listed kind and the part stays',
		array(
			'classes' => array( 'single-post', 'kind-listen', 'cr-listen-extra' ),
			'part'    => 'kind-listen',
		) === ( $registered['listen'] ?? null )
	);

	// Sanitizing.
	t233_reset();
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	add_filter(
		'courtneyr_child_editor_parity_kinds',
		static function ( array $kinds ): array {
			$kinds['Bad Key!']          = array( 'classes' => array( 'bad class<b>', 'kind-x', 'kind-x', '', '!!' ) );
			$kinds['junk']              = 'not an array';
			$kinds['noclasses']         = array();
			$kinds['listen']['classes'] = array( 'single-post', 'kind-listen', 7, null );
			return $kinds;
		}
	);
	$registered = kinds();
	t233_check( 'filter keys are sanitized', isset( $registered['badkey'] ) && ! isset( $registered['Bad Key!'] ) );
	t233_check(
		'classes are sanitized, unique and never empty',
		array( 'badclassb', 'kind-x' ) === ( $registered['badkey']['classes'] ?? null )
	);
	t233_check( 'an entry that is not an array is dropped', ! isset( $registered['junk'] ) );
	t233_check( 'an entry with no classes keeps no classes and a part key', array(
		'classes' => array(),
		'part'    => '',
	) === ( $registered['noclasses'] ?? null ) );
	t233_check( 'non-string classes are dropped', array( 'single-post', 'kind-listen' ) === ( $registered['listen']['classes'] ?? null ) );

	// A filter that returns something else falls back to the base list.
	t233_reset();
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	add_filter(
		'courtneyr_child_editor_parity_kinds',
		static function ( $kinds ) {
			return null;
		}
	);
	t233_check(
		'a filter that returns a non-array falls back to the base list',
		array(
			'listen' => array(
				'classes' => array( 'single-post', 'kind-listen' ),
				'part'    => '',
			),
		) === kinds()
	);

	// canvas_classes.
	t233_reset();
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	t233_check( 'canvas_classes returns the classes of a registered kind', array( 'single-post', 'kind-listen' ) === canvas_classes( 'listen' ) );
	t233_check( 'canvas_classes is empty for an unknown kind', array() === canvas_classes( 'nope' ) );
	t233_check( 'canvas_classes is empty for an empty kind', array() === canvas_classes( '' ) );

	// editor_data and the inline script.
	t233_reset();
	$GLOBALS['t233']['parts']      = array( 'kind-listen' );
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	t233_check(
		'editor_data carries the registry and the router slug',
		array(
			'kinds'      => kinds(),
			'routerSlug' => 'kind',
		) === editor_data()
	);
	$script = inline_script();
	t233_check( 'inline_script assigns window.courtneyrEditorParity', str_starts_with( $script, 'window.courtneyrEditorParity = ' ) && str_ends_with( $script, ';' ) );
	$decoded = json_decode( substr( $script, strlen( 'window.courtneyrEditorParity = ' ), -1 ), true );
	t233_check( 'inline_script holds the editor data as JSON', is_array( $decoded ) && 'kind' === ( $decoded['routerSlug'] ?? null ) && 'kind-listen' === ( $decoded['kinds']['listen']['part'] ?? null ) );
	t233_reset();
	t233_check( 'inline_script encodes an empty registry as an object', str_contains( inline_script(), '"kinds":{}' ) );

	// enqueue.
	t233_reset();
	$GLOBALS['t233']['kind_terms'] = array( 'listen' );
	$GLOBALS['t233']['screen']     = (object) array(
		'base'      => 'post',
		'post_type' => 'post',
	);
	enqueue();
	$script = $GLOBALS['t233']['scripts']['courtneyr-editor-parity'] ?? null;
	t233_check( 'the post editor registers the script', null !== $script );
	t233_check( 'the script is the theme file at the theme version', null !== $script && 'https://example.test/theme/assets/js/editor-parity.js' === $script['src'] && 'test-version' === $script['version'] );
	t233_check(
		'the script depends on the data, hooks, element and core-data packages',
		null !== $script && array() === array_diff( array( 'wp-data', 'wp-hooks', 'wp-element', 'wp-core-data' ), $script['deps'] )
	);
	t233_check( 'the post editor enqueues the script', array( 'courtneyr-editor-parity' ) === $GLOBALS['t233']['enqueued'] );
	t233_check(
		'the inline data loads before the script',
		1 === count( $GLOBALS['t233']['inline'] ) && 'courtneyr-editor-parity' === $GLOBALS['t233']['inline'][0][0] && 'before' === $GLOBALS['t233']['inline'][0][2] && inline_script() === $GLOBALS['t233']['inline'][0][1]
	);

	t233_reset();
	$GLOBALS['t233']['screen'] = (object) array(
		'base'      => 'site-editor',
		'post_type' => '',
	);
	enqueue();
	t233_check( 'the Site Editor enqueues the script', array( 'courtneyr-editor-parity' ) === $GLOBALS['t233']['enqueued'] );

	t233_reset();
	$GLOBALS['t233']['screen'] = (object) array(
		'base'      => 'post',
		'post_type' => 'page',
	);
	enqueue();
	t233_check( 'a page editor does not enqueue the script', array() === $GLOBALS['t233']['enqueued'] );

	t233_reset();
	$GLOBALS['t233']['screen'] = (object) array(
		'base'      => 'edit',
		'post_type' => 'post',
	);
	enqueue();
	t233_check( 'the posts list does not enqueue the script', array() === $GLOBALS['t233']['enqueued'] );

	t233_reset();
	enqueue();
	t233_check( 'no screen enqueues nothing', array() === $GLOBALS['t233']['enqueued'] && array() === $GLOBALS['t233']['inline'] );

	echo $GLOBALS['t233_failures'] ? "\n" . $GLOBALS['t233_failures'] . " failed\n" : "\nall passed\n";
	exit( $GLOBALS['t233_failures'] ? 1 : 0 );
}
