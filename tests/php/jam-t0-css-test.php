<?php
/**
 * `php tests/php/jam-t0-css-test.php`: grep gates for W5-T0's jam commit in
 * assets/css/cr-post-kinds.css (PKIW issue 235, W5-G25).
 *
 * The jam commit takes .k-jam and li.kind-jam out of every shared selector
 * list and deletes the solo rules, so cr-jam.css (W5-T235) is the one place
 * jam paint lives. The tests that belong to cr-jam.css are W5-T235's
 * tests/php/jam-css-test.php.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$css       = (string) file_get_contents( $theme_dir . '/assets/css/cr-post-kinds.css' );
$spec      = (string) file_get_contents( $theme_dir . '/tests/playwright/w2-t0-shared-paint.spec.mjs' );
$failures  = array();

/**
 * Print one named check.
 *
 * @param bool   $ok   Whether it passed.
 * @param string $name What was checked.
 * @return void
 */
function check( bool $ok, string $name ): void {
	global $failures;
	echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
	if ( ! $ok ) {
		$failures[] = $name;
	}
}

/**
 * Lines of the stylesheet that match a pattern, grep -n style.
 *
 * @param string $css     Stylesheet text.
 * @param string $pattern PCRE pattern.
 * @return int[] 1-based line numbers.
 */
function matching_lines( string $css, string $pattern ): array {
	$hits = array();
	foreach ( preg_split( '/\R/', $css ) as $index => $line ) {
		if ( preg_match( $pattern, $line ) ) {
			$hits[] = $index + 1;
		}
	}
	return $hits;
}

$jam_class = '/\.(?:k|kind)-jam(?![A-Za-z0-9_-])/';
$jam_hits  = matching_lines( $css, $jam_class );
check( array() === $jam_hits, 'git grep -n -e k-jam -e kind-jam prints nothing for cr-post-kinds.css' . ( $jam_hits ? ' (lines ' . implode( ', ', $jam_hits ) . ')' : '' ) );

// The legacy jam accent used three colors outside the locked palette (issue 235 correction 18).
$legacy = matching_lines( $css, '/3f2f7a|b0a0f0|cbc3ec/i' );
check( array() === $legacy, 'the off-palette jam accent hexes are gone from cr-post-kinds.css' . ( $legacy ? ' (lines ' . implode( ', ', $legacy ) . ')' : '' ) );

// The shared single-page groups lose the jam token only: the sibling kinds stay in them.
check( false !== strpos( $css, '.pk-card:is(.k-watch, .k-listen, .k-read, .k-play:not(:where(.cr-spine, .cr-cabinet, .cr-cartridge, .cr-scorepad, .pk-card--tabletop))' ), 'the single-page card group keeps watch, listen, read and play' );
check( false !== strpos( $css, 'body:is(.kind-watch, .kind-listen, .kind-read, .kind-play' ), 'the single-page body group keeps watch, listen, read and play' );
check( false !== strpos( $css, '.cr-stream-page .k-listen .pk-media img { aspect-ratio: 1 / 1; }' ), 'the Stream listen cover keeps its square crop' );

// The W2 shared-paint spec compares control kinds with the base ref; jam now changes by design.
$control = array();
if ( preg_match( '/const CONTROL_KINDS = \[(.*?)\];/s', $spec, $match ) ) {
	preg_match_all( "/'([a-z-]+)'/", $match[1], $kinds );
	$control = $kinds[1];
}
check( array() !== $control, 'w2-t0-shared-paint.spec.mjs still declares CONTROL_KINDS' );
check( ! in_array( 'jam', $control, true ), 'CONTROL_KINDS no longer lists jam' );
check( in_array( 'listen', $control, true ) && in_array( 'watch', $control, true ), 'CONTROL_KINDS keeps listen and watch as controls' );

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
exit( count( $failures ) ? 1 : 0 );
