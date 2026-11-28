<?php
/**
 * `php tests/php/navigation-overlay-menu-test.php`: the overlay part's
 * Navigation shows the menu the header picked, in the Site Editor and on the
 * front end, before and after an editor save.
 *
 * Two published menus: A (older, picked in the saved header) and B (newer).
 * The overlay part's Navigation has no `ref`. The Site Editor asks
 * `/wp-block-editor/v1/navigation-fallback` for a menu, shows it and keeps its
 * id as `ref` when the part is saved; the front end renders core's fallback
 * blocks. Core answers both with the newest menu, B.
 *
 * Needs core's block parser: CR_WP_CORE, or ~/.wp-tests/wordpress. Exits 2
 * when it can't load it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_nom_filters'] = array();
	$GLOBALS['cr_nom_header']  = null;
	$failures                  = array();

	const MENU_A  = 101;
	const MENU_B  = 202;
	const DRAFT   = 303;
	const NOT_NAV = 404;

	/** Minimal post. */
	class WP_Post {
		/** Constructor. */
		public function __construct( public int $ID, public string $post_type, public string $post_status, public string $post_content ) {}
	}

	/** Minimal REST request. */
	class WP_REST_Request {
		/** Constructor. */
		public function __construct( private string $route ) {}
		/** The route. */
		public function get_route(): string {
			return $this->route;
		}
	}

	/** Minimal REST response. */
	class WP_REST_Response {
		/** Constructor. */
		public function __construct( public array $data ) {}
	}

	/** Minimal error. */
	class WP_Error {}

	/** Core's fallback controller, reduced to the id it reports. */
	class WP_REST_Navigation_Fallback_Controller {
		/** Prepare a menu post. */
		public function prepare_item_for_response( $post, $request ): WP_REST_Response {
			return new WP_REST_Response( array( 'id' => $post->ID ) );
		}
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_nom_filters'][ $hook ][] = array( $callback, $accepted_args );
	}

	/** Run recorded callbacks for a hook. */
	function cr_nom_apply( string $hook, $value, ...$args ) {
		foreach ( $GLOBALS['cr_nom_filters'][ $hook ] ?? array() as [ $callback, $accepted ] ) {
			$value = $callback( $value, ...array_slice( $args, 0, $accepted - 1 ) );
		}
		return $value;
	}

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Parse block markup with core's parser. */
	function parse_blocks( string $content ): array {
		return ( new WP_Block_Parser() )->parse( $content );
	}

	/** Drop whitespace-only blocks, as core does. */
	function block_core_navigation_filter_out_empty_blocks( array $blocks ): array {
		return array_values( array_filter( $blocks, static fn( $b ) => null !== $b['blockName'] ) );
	}

	/** Block markup for parsed blocks (labels only; enough to reparse). */
	function serialize_blocks( array $blocks ): string {
		return implode( '', array_map( static fn( $b ) => '<!-- wp:' . substr( $b['blockName'], 5 ) . ' ' . wp_json_encode( $b['attrs'] ) . ' /-->', $blocks ) );
	}

	/** JSON for block attributes. */
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES );
	}

	/** No hooked blocks in this fixture. */
	function apply_block_hooks_to_content_from_post_object( string $content, $post ): string {
		return $content;
	}

	/** Error check. */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}

	/** The active theme. */
	function get_stylesheet(): string {
		return 'courtneyr-child';
	}

	/** The saved header part, or null for none. */
	function get_block_template( string $id, string $type ) {
		return 'courtneyr-child//header' === $id && 'wp_template_part' === $type && null !== $GLOBALS['cr_nom_header']
			? (object) array( 'content' => $GLOBALS['cr_nom_header'] )
			: null;
	}

	/** The fixture menus. */
	function get_post( $id ) {
		$link = static fn( $label ) => '<!-- wp:navigation-link {"label":"' . $label . '","url":"/' . $label . '/"} /-->';
		$all  = array(
			MENU_A  => new WP_Post( MENU_A, 'wp_navigation', 'publish', $link( 'alpha-one' ) . "\n\n" . $link( 'alpha-two' ) ),
			MENU_B  => new WP_Post( MENU_B, 'wp_navigation', 'publish', $link( 'bravo-one' ) . $link( 'bravo-two' ) . $link( 'bravo-three' ) ),
			DRAFT   => new WP_Post( DRAFT, 'wp_navigation', 'draft', $link( 'draft-one' ) ),
			NOT_NAV => new WP_Post( NOT_NAV, 'page', 'publish', $link( 'page-one' ) ),
		);
		return $all[ (int) $id ] ?? null;
	}

	/** Core's fallback blocks: the newest published menu, B. */
	function core_fallback_blocks(): array {
		return block_core_navigation_filter_out_empty_blocks( parse_blocks( get_post( MENU_B )->post_content ) );
	}

	/** Link labels in parsed blocks. */
	function labels( array $blocks ): array {
		return array_map( static fn( $b ) => $b['attrs']['label'] ?? '?', $blocks );
	}

	/** The menu id the Site Editor gets for a Navigation with no `ref`. */
	function editor_fallback_id(): ?int {
		$request  = new WP_REST_Request( '/wp-block-editor/v1/navigation-fallback' );
		$response = cr_nom_apply( 'rest_request_after_callbacks', new WP_REST_Response( array( 'id' => MENU_B ) ), array(), $request );
		return $response instanceof WP_REST_Response ? $response->data['id'] : null;
	}

	/** The labels the front end shows for an overlay Navigation with these attributes. */
	function front_end_labels( array $attrs ): array {
		$ref = $attrs['ref'] ?? null;
		return labels( null === $ref ? cr_nom_apply( 'block_core_navigation_render_fallback', core_fallback_blocks() ) : block_core_navigation_filter_out_empty_blocks( parse_blocks( get_post( $ref )->post_content ) ) );
	}

	/**
	 * The saved header: the theme's header part with a menu picked.
	 *
	 * @param string $attr `"ref":N`, `"navigationMenuId":N`, or '' for none.
	 */
	function saved_header( string $attr ): string {
		$header = (string) file_get_contents( dirname( __DIR__, 2 ) . '/parts/header.html' );
		return '' === $attr ? $header : (string) preg_replace( '/<!-- wp:navigation \{/', '<!-- wp:navigation {' . $attr . ',', $header, 1 );
	}

	$core   = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$parser = $core . '/wp-includes/class-wp-block-parser.php';
	if ( ! is_file( $parser ) ) {
		echo "FAIL core's block parser could not load: set CR_WP_CORE to a WordPress checkout\n";
		exit( 2 );
	}
	foreach ( array( 'html-api/class-wp-html-span.php', 'class-wp-block-processor.php', 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php' ) as $file ) {
		if ( is_file( $core . '/wp-includes/' . $file ) ) {
			require_once $core . '/wp-includes/' . $file;
		}
	}

	$root = dirname( __DIR__, 2 );
	check( str_contains( (string) file_get_contents( $root . '/functions.php' ), "require_once __DIR__ . '/inc/navigation-overlay.php';" ), 'functions.php loads inc/navigation-overlay.php' );
	require $root . '/inc/navigation-overlay.php';

	$alpha = array( 'alpha-one', 'alpha-two' );
	$bravo = array( 'bravo-one', 'bravo-two', 'bravo-three' );

	// Finding 1: the header picks the older menu A; B is newer.
	$GLOBALS['cr_nom_header'] = saved_header( '"ref":' . MENU_A );
	check( MENU_A === editor_fallback_id(), 'Site Editor: the overlay part shows the header menu A, not the newer B' );
	check( $alpha === front_end_labels( array() ), 'front end: the overlay part shows A' );

	// Finding 2: an editor save keeps the id the editor showed as `ref`.
	check( $alpha === front_end_labels( array( 'ref' => editor_fallback_id() ) ), 'after an editor save of the overlay part, the front end still shows A' );
	check( $bravo === front_end_labels( array( 'ref' => MENU_B ) ), 'an overlay Navigation that names its own menu keeps it' );

	$GLOBALS['cr_nom_header'] = saved_header( '"navigationMenuId":' . MENU_A );
	check( MENU_A === editor_fallback_id() && $alpha === front_end_labels( array() ), 'a header saved with the legacy navigationMenuId passes it too' );

	foreach ( array(
		'a header with no menu'           => saved_header( '' ),
		'no saved header part'            => null,
		'a header naming a draft menu'    => saved_header( '"ref":' . DRAFT ),
		'a header naming a non-menu post' => saved_header( '"ref":' . NOT_NAV ),
	) as $case => $header ) {
		$GLOBALS['cr_nom_header'] = $header;
		check( MENU_B === editor_fallback_id() && $bravo === front_end_labels( array() ), $case . ': editor and front end both keep core\'s fallback, B' );
	}

	$GLOBALS['cr_nom_header'] = saved_header( '"ref":' . MENU_A );
	$other                    = cr_nom_apply( 'rest_request_after_callbacks', new WP_REST_Response( array( 'id' => 7 ) ), array(), new WP_REST_Request( '/wp/v2/navigation' ) );
	check( 7 === $other->data['id'], 'other REST routes are left alone' );
	$denied = new WP_Error();
	check( $denied === cr_nom_apply( 'rest_request_after_callbacks', $denied, array(), new WP_REST_Request( '/wp-block-editor/v1/navigation-fallback' ) ), 'a refused fallback request stays refused' );

	if ( $failures ) {
		echo count( $failures ), " failed\n";
		exit( 1 );
	}
	echo "all passed\n";
}
