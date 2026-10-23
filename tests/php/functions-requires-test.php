<?php
/**
 * Every file in inc/ is loaded by functions.php.
 *
 * W6 lane PRs merged inc/issue.php, inc/single-issue.php, inc/stream-issue.php
 * and inc/comic-strip.php without their require_once lines (2026-10-22), so
 * their hooks never ran. This runner fails when an inc/ file isn't required.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$root      = dirname( __DIR__, 2 );
$functions = (string) file_get_contents( $root . '/functions.php' );
$failed    = 0;

foreach ( glob( $root . '/inc/*.php' ) as $file ) {
	$name = basename( $file );
	$ok   = str_contains( $functions, "require_once __DIR__ . '/inc/{$name}';" );
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . "functions.php requires inc/{$name}\n";
	$failed += $ok ? 0 : 1;
}

echo $failed ? "{$failed} failed\n" : "all passed\n";
exit( $failed ? 1 : 0 );
