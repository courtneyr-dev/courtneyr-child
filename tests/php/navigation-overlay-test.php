<?php
/**
 * `php tests/php/navigation-overlay-test.php`: checks the header Navigation's
 * overlay wiring without WordPress. The header Navigation names the
 * `navigation-overlay` part, theme.json registers that part in the
 * `navigation-overlay` area, and the part holds one close control, one search
 * field and one Navigation that resolves to the header's menu.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$failures = array();
$root     = dirname( __DIR__, 2 );

/** Print one result and remember failures. */
function check( bool $ok, string $message ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

/**
 * Block comments in a part: list of [ name, attributes ].
 *
 * @param string $html Part markup.
 * @return array<int, array{0: string, 1: array}>
 */
function block_comments( string $html ): array {
	preg_match_all( '/<!-- wp:([a-z0-9\/-]+)(?: (\{.*?\}))? \/?-->/', $html, $m, PREG_SET_ORDER );
	return array_map(
		static fn( $x ) => array( str_contains( $x[1], '/' ) ? $x[1] : 'core/' . $x[1], isset( $x[2] ) ? json_decode( $x[2], true ) : array() ),
		$m
	);
}

/**
 * Blocks of one type.
 *
 * @param array  $blocks block_comments() output.
 * @param string $name   Block name.
 * @return array<int, array>
 */
function of_type( array $blocks, string $name ): array {
	return array_values( array_map( static fn( $b ) => $b[1], array_filter( $blocks, static fn( $b ) => $name === $b[0] ) ) );
}

$header  = block_comments( (string) file_get_contents( $root . '/parts/header.html' ) );
$overlay = block_comments( (string) file_get_contents( $root . '/parts/navigation-overlay.html' ) );
$theme   = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );

$header_navs = of_type( $header, 'core/navigation' );
check( 1 === count( $header_navs ), 'the header has one Navigation block' );
$nav = $header_navs[0] ?? array();
check( 'navigation-overlay' === ( $nav['overlay'] ?? null ), 'the header Navigation names the navigation-overlay part' );
check( 'never' !== ( $nav['overlayMenu'] ?? 'mobile' ), 'the header Navigation still collapses into an overlay' );
// The editor migrates a block that carries openSubmenusOnClick through a deprecation
// whose schema has no `overlay` or `ariaLabel`, so saving the header would drop both.
check( ! array_key_exists( 'openSubmenusOnClick', $nav ), 'the header Navigation has no deprecated openSubmenusOnClick' );

$parts = array_column( $theme['templateParts'] ?? array(), null, 'name' );
check( array( 'header', 'footer', 'sidebar' ) === array_slice( array_column( $theme['templateParts'] ?? array(), 'name' ), 0, 3 ), 'theme.json keeps Ollie\'s header, footer, sidebar order first' );
check( 'navigation-overlay' === ( $parts['navigation-overlay']['area'] ?? null ), 'theme.json registers the part in the navigation-overlay area' );

check( 1 === count( of_type( $overlay, 'core/navigation-overlay-close' ) ), 'the overlay has one close control' );
check( 1 === count( of_type( $overlay, 'core/search' ) ), 'the overlay has one search field' );
$overlay_navs = of_type( $overlay, 'core/navigation' );
check( 1 === count( $overlay_navs ), 'the overlay has one Navigation block' );
check( ( $overlay_navs[0]['ref'] ?? null ) === ( $nav['ref'] ?? null ), 'the overlay Navigation resolves to the same menu as the header' );

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
