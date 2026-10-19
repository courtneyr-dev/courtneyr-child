<?php
/**
 * Static design-system checks for the video archive, single and Stream paint.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme    = dirname( __DIR__, 2 );
$failures = array();
$checks   = 0;

/** Record one check. */
function cr_video_css_check( bool $condition, string $label ): void {
	global $failures, $checks;
	++$checks;
	echo $condition ? 'PASS ' : 'FAIL ', $label, "\n";
	if ( ! $condition ) {
		$failures[] = $label;
	}
}

/** Read one theme file. */
function cr_video_css_read( string $path ): string {
	global $theme;
	$text = is_file( $theme . '/' . $path ) ? file_get_contents( $theme . '/' . $path ) : '';
	return false === $text ? '' : $text;
}

$css     = cr_video_css_read( 'assets/css/cr-video.css' );
$tokens  = cr_video_css_read( 'assets/css/tokens.css' );
$sources = array(
	'inc/video.php',
	'patterns/cr-video-shelf-loop.php',
	'templates/taxonomy-kind-video.html',
	'assets/css/cr-video.css',
);

cr_video_css_check( '' !== $css, 'cr-video.css exists' );

foreach ( $sources as $file ) {
	$text = cr_video_css_read( $file );
	cr_video_css_check( 0 === preg_match( '/(?<![&\w])#[0-9a-fA-F]{3,8}\b/', $text ), $file . ' has no hex color or hex-looking issue label' );
}

cr_video_css_check( 0 === preg_match( '/\b(?:rgba?|hsla?)\(/i', $css ), 'stylesheet has no rgb or hsl literal' );
cr_video_css_check( 0 === preg_match( '/(?:^|[\s;{])(?:backdrop-)?filter\s*:|drop-shadow\(|blur\(|mix-blend-mode\s*:/im', $css ), 'stylesheet has no soft visual effects' );
cr_video_css_check( ! preg_match( '/noise|halftone/i', $css ), 'stylesheet has no noise or halftone treatment' );

preg_match_all( '/var\(\s*(--[a-z0-9-]+)/', $css, $used );
preg_match_all( '/(--[a-z0-9-]+)\s*:/', $css, $own );
preg_match_all( '/(--[a-z0-9-]+)\s*:/', $tokens, $known );
$missing = array_diff( array_unique( $used[1] ), array_unique( array_merge( $own[1], $known[1] ) ) );
cr_video_css_check( array() === $missing, 'every custom property resolves (' . implode( ', ', $missing ) . ')' );

preg_match_all( '/box-shadow\s*:\s*([^;]+);/', $css, $shadows );
$bad_shadows = array();
foreach ( $shadows[1] as $value ) {
	if ( 'none' === trim( $value ) ) {
		continue;
	}
	$stripped = preg_replace( '/var\(--cr-shadow-hard(?:-sm|-2)?\)|0 0 0 \d+px var\(--cr-focus-halo\)|,|\s+/', '', $value );
	if ( '' !== $stripped ) {
		$bad_shadows[] = trim( $value );
	}
}
cr_video_css_check( array() === $bad_shadows, 'every box shadow uses an approved hard token (' . implode( ' | ', $bad_shadows ) . ')' );

preg_match_all( '/font-size\s*:\s*([^;]+);/', $css, $sizes );
$small = array();
foreach ( $sizes[1] as $value ) {
	if ( preg_match_all( '/(\d*\.?\d+)(rem|px)/', $value, $parts, PREG_SET_ORDER ) ) {
		foreach ( $parts as $part ) {
			$rem = 'px' === $part[2] ? (float) $part[1] / 16 : (float) $part[1];
			if ( $rem < 0.75 ) {
				$small[] = trim( $value );
			}
		}
	}
}
cr_video_css_check( array() === $small, 'no literal font size falls below the floor (' . implode( ' | ', $small ) . ')' );

preg_match_all( '/font-family\s*:\s*([^;]+);/', $css, $families );
$bad_families = array_filter( $families[1], static fn( string $value ): bool => ! preg_match( '/^var\(--cr-font-(?:display|accent|body|mono)\)$/', trim( $value ) ) );
cr_video_css_check( array() === $bad_families, 'font families use only the four voice tokens' );

preg_match_all( '/(?:rotate|transform)\s*:\s*([^;]+);/', $css, $rotations );
$bad_rotations = array_filter( $rotations[1], static fn( string $value ): bool => ! preg_match( '/^(?:none|rotate\(var\(--cr-rotate-(?:1|2|neg|neg2)\)\)|var\(--cr-rotate-(?:1|2|neg|neg2)\))$/', trim( $value ) ) );
cr_video_css_check( array() === $bad_rotations, 'rotations use only the four rotation tokens' );

preg_match_all( '/(?:linear|radial)-gradient\(([^;\n]+)\)/', $css, $gradients );
$bad_gradients = array_filter( $gradients[1], static fn( string $value ): bool => ! preg_match( '/(\d+%)\s*,\s*var\([^)]*\)\s*\1(?:\s|,|$)/', $value ) );
cr_video_css_check( array() === $bad_gradients, 'every gradient has a hard stop pair' );
cr_video_css_check( ! preg_match( '/@keyframes|\banimation\s*:/', $css ), 'stylesheet defines no animation' );

preg_match_all( '/transition\s*:\s*([^;]+);/', $css, $transitions );
$bad_transitions = array_filter( $transitions[1], static fn( string $value ): bool => ! preg_match( '/^(?:none|(?:translate|transform)\b)/', trim( $value ) ) );
cr_video_css_check( array() === $bad_transitions, 'transitions affect only translate or transform' );

cr_video_css_check( str_contains( $css, '@media (min-width: 40rem)' ) && str_contains( $css, 'repeat(2, minmax(0, 1fr))' ), 'tablet grid uses two columns from 40rem' );
cr_video_css_check( str_contains( $css, '@media (min-width: 64rem)' ) && str_contains( $css, 'repeat(3, minmax(0, 1fr))' ), 'desktop grid uses three columns from 64rem' );
cr_video_css_check( str_contains( $css, '@media (forced-colors: active)' ) && str_contains( $css, '2px solid CanvasText' ), 'forced colors use CanvasText outlines' );
cr_video_css_check( str_contains( $css, '@media (prefers-reduced-motion: reduce)' ), 'reduced-motion block exists' );
cr_video_css_check( str_contains( $css, ':root[data-theme="dark"]' ), 'stored dark theme path exists' );
cr_video_css_check( str_contains( $css, '@media (prefers-color-scheme: dark)' ) && str_contains( $css, ':root:not([data-theme="light"])' ), 'operating-system dark path exists' );
cr_video_css_check( str_contains( $css, '.cr-shelf-tape' ), 'archive tape section exists' );
cr_video_css_check( str_contains( $css, '.cr-tape-deck' ), 'single deck section exists' );
cr_video_css_check( str_contains( $css, '.cr-tape-spine' ), 'Stream spine section exists' );
cr_video_css_check( str_contains( $css, '--able-base-control-size: 2.75rem;' ), 'Able Player controls use the 44px size token' );
cr_video_css_check( str_contains( $css, '--able-focus-outline: var(--cr-focus-ring);' ), 'Able Player focus reads the theme ring token' );

$git = trim( (string) shell_exec( 'command -v git 2>/dev/null' ) );
if ( '' === $git ) {
	echo "PASS protected shared-file comparisons skipped because git is unavailable\n";
} else {
	foreach ( array( 'assets/css/cr-shelf-boards.css', 'assets/css/cr-nav.css', 'assets/css/tokens.css' ) as $file ) {
		$head = shell_exec( escapeshellarg( $git ) . ' -C ' . escapeshellarg( $theme ) . ' show HEAD:' . escapeshellarg( $file ) . ' 2>/dev/null' );
		cr_video_css_check( is_string( $head ) && $head === cr_video_css_read( $file ), $file . ' matches HEAD' );
	}
}

echo 'TOTAL ', $checks, ' checks, ', count( $failures ), " failed\n";
exit( empty( $failures ) ? 0 : 1 );
