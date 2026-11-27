<?php
/**
 * `php tests/php/navigation-overlay-menu-test.php`: the Navigation inside the
 * overlay part shows the menu the header picked, not core's fallback.
 *
 * Two published menus: A (older, picked in the header) and B (newer). Core
 * resolves a Navigation with no `ref` to the newest menu, B. The test runs the
 * theme's hooks in the order core fires them while the header renders.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_nom_filters'] = array();
	$failures                  = array();

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

	const MENU_A = 101;
	const MENU_B = 202;

	/** The menu core renders for a Navigation: its own, else the newest published. */
	function shown_menu( array $parsed_block ): int {
		$ref = $parsed_block['attrs']['ref'] ?? $parsed_block['attrs']['navigationMenuId'] ?? null;
		return null === $ref ? MENU_B : (int) $ref;
	}

	/** A Navigation as core hands it to render_block_data. */
	function nav( array $attrs ): array {
		return array( 'blockName' => 'core/navigation', 'attrs' => $attrs, 'innerBlocks' => array() );
	}

	/** The overlay part's Navigation, as core marks it. */
	function overlay_nav( array $attrs = array() ): array {
		return nav( $attrs + array( 'overlayMenu' => 'never', '_isWithinOverlayTemplatePart' => true ) );
	}

	/**
	 * Render a header Navigation that opens the overlay part.
	 *
	 * @param array $header_attrs Header Navigation attributes.
	 * @param array $inner        Overlay Navigation blocks.
	 * @return int[] Menus the overlay Navigation blocks show.
	 */
	function render_header( array $header_attrs, array $inner ): array {
		$header = cr_nom_apply( 'render_block_data', nav( $header_attrs ) );
		$shown  = array();
		foreach ( $inner as $block ) {
			$block   = cr_nom_apply( 'render_block_data', $block );
			$shown[] = shown_menu( $block );
			cr_nom_apply( 'render_block_core/navigation', '', $block );
		}
		cr_nom_apply( 'render_block_core/navigation', '', $header );
		return $shown;
	}

	$root = dirname( __DIR__, 2 );
	check( str_contains( (string) file_get_contents( $root . '/functions.php' ), "require_once __DIR__ . '/inc/navigation-overlay.php';" ), 'functions.php loads inc/navigation-overlay.php' );
	$file = $root . '/inc/navigation-overlay.php';
	check( is_file( $file ), 'inc/navigation-overlay.php exists' );
	if ( is_file( $file ) ) {
		require $file;
	}

	$open = array( 'overlay' => 'navigation-overlay' );

	check( array( MENU_A ) === render_header( $open + array( 'ref' => MENU_A ), array( overlay_nav() ) ), 'header picks the older menu A: the overlay shows A, not the newer B' );
	check( array( MENU_A ) === render_header( $open + array( 'navigationMenuId' => MENU_A ), array( overlay_nav() ) ), 'a header saved with the legacy navigationMenuId passes it too' );
	check( array( MENU_B ) === render_header( $open, array( overlay_nav() ) ), 'a header with no menu passes nothing; both fall back to the same menu' );
	check( array( MENU_B ) === render_header( $open + array( 'ref' => MENU_A ), array( overlay_nav( array( 'ref' => MENU_B ) ) ) ), 'an overlay Navigation that names its own menu keeps it' );

	render_header( $open + array( 'ref' => MENU_A ), array() );
	check( MENU_B === shown_menu( cr_nom_apply( 'render_block_data', overlay_nav() ) ), 'the header menu is forgotten once the header has rendered' );
	cr_nom_apply( 'render_block_data', nav( array( 'ref' => MENU_A ) ) );
	check( MENU_B === shown_menu( cr_nom_apply( 'render_block_data', overlay_nav() ) ), 'a Navigation with no overlay part passes nothing' );
	check( array( 'ref' => MENU_A ) === cr_nom_apply( 'render_block_data', nav( array( 'ref' => MENU_A ) ) )['attrs'], 'the header Navigation itself is left alone' );

	if ( $failures ) {
		echo count( $failures ), " failed\n";
		exit( 1 );
	}
	echo "all passed\n";
}
