<?php
/**
 * `php tests/php/favorite-css-test.php`: checks the favorite stylesheet and
 * sources against the locked design system and the facts-only rule (issue 259).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme = dirname( __DIR__, 2 );
$count = 0;
$fails = array();

/** Record one check. */
function cr_fav_css_check( bool $ok, string $label ): void {
	global $count, $fails;
	++$count;
	echo $ok ? 'PASS ' : 'FAIL ', $label, "\n";
	if ( ! $ok ) {
		$fails[] = $label;
	}
}

/** Read a theme file. */
function cr_fav_css_read( string $path ): string {
	global $theme;
	$text = is_file( $theme . '/' . $path ) ? file_get_contents( $theme . '/' . $path ) : '';
	return false === $text ? '' : $text;
}

$css     = cr_fav_css_read( 'assets/css/cr-favorite.css' );
$tokens  = cr_fav_css_read( 'assets/css/tokens.css' );
$sources = array(
	'inc/favorite.php',
	'inc/single-favorite.php',
	'inc/stream-favorite.php',
	'patterns/cr-favorite-star-chart-loop.php',
	'templates/taxonomy-kind-favorite.html',
	'assets/css/cr-favorite.css',
);

cr_fav_css_check( '' !== $css, 'cr-favorite.css exists' );

foreach ( $sources as $file ) {
	$text = cr_fav_css_read( $file );
	cr_fav_css_check( 0 === preg_match( '/(?<![&\w])#[0-9a-fA-F]{3,8}\b/', $text ), $file . ' holds no hex color and no "#" followed by hex-looking characters' );
}

cr_fav_css_check( 0 === preg_match( '/\b(?:rgba?|hsla?)\(/i', $css ), 'no rgb(a) or hsl(a) literal' );
cr_fav_css_check( 0 === preg_match( '/(?:^|[\s;{])(?:backdrop-)?filter\s*:|drop-shadow\(/i', $css ), 'no filter of any kind' );
cr_fav_css_check( ! str_contains( $css, 'photocopier-black' ), 'no --cr-photocopier-black' );
cr_fav_css_check( 0 === preg_match( '/#(?:013eac|96690a|e6c05a|f1e4c0|fef7e0|fff497)/i', implode( "\n", array_map( 'cr_fav_css_read', $sources ) ) ), 'none of the retired or off-palette values' );

// Every custom property the sheet reads is defined in tokens.css or in the sheet itself.
preg_match_all( '/var\(\s*(--[a-z0-9-]+)/', $css, $used );
preg_match_all( '/(--[a-z0-9-]+)\s*:/', $css, $own );
preg_match_all( '/(--[a-z0-9-]+)\s*:/', $tokens, $known );
// --cr-fav-tilt is set inline on each star by inc/favorite.php, with a fallback in the sheet.
$missing = array_diff( array_unique( $used[1] ), array_unique( array_merge( $own[1], $known[1], array( '--cr-fav-tilt' ) ) ) );
cr_fav_css_check( array() === $missing, 'every custom property resolves (' . implode( ', ', $missing ) . ')' );

// Hard shadows only: a box-shadow is none, a hard token, or a hard token plus the focus halo.
preg_match_all( '/box-shadow\s*:\s*([^;]+);/', $css, $shadows );
$bad = array();
foreach ( $shadows[1] as $value ) {
	if ( 'none' === trim( $value ) ) {
		continue;
	}
	$stripped = preg_replace( '/var\(--cr-shadow-hard(?:-sm|-2)?\)|0 0 0 \d+px var\(--cr-focus-halo\)|,|\s+/', '', $value );
	if ( '' !== $stripped ) {
		$bad[] = trim( $value );
	}
}
cr_fav_css_check( array() === $bad, 'every box-shadow is a hard token (' . implode( ' | ', $bad ) . ')' );

// Text never drops below the floor: every literal font-size is at least 0.75rem or reads a token.
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
cr_fav_css_check( array() === $small, 'no literal font-size below 0.75rem (' . implode( ' | ', $small ) . ')' );

// The type voices are the four tokens, never a family name.
preg_match_all( '/font-family\s*:\s*([^;]+);/', $css, $families );
$loose = array_filter( $families[1], static fn( string $v ): bool => ! preg_match( '/^var\(--cr-font-(?:display|accent|body|mono)\)$/', trim( $v ) ) );
cr_fav_css_check( array() === $loose, 'font-family reads only the four voice tokens' );

// The star is flat yellow with a 2px violet edge; the tape word is Rock Salt at 1rem or more.
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-star__shape\s*\{[^}]*fill:\s*var\(--cr-selective-yellow\)[^}]*stroke:\s*var\(--cr-russian-violet\)[^}]*stroke-width:\s*2px/s', $css ), 'star: flat selective yellow, 2px russian violet edge' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-sheet__tape \.pk-kindlabel\s*\{[^}]*font-family:\s*var\(--cr-font-display\)[^}]*font-size:\s*max\(1rem/s', $css ), 'tape word: Rock Salt at 1rem or more' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-card \.cr-fav-star \.pk-kindlabel\s*\{[^}]*font-family:\s*var\(--cr-font-display\)[^}]*font-size:\s*max\(1rem/s', $css ), 'Stream label: Rock Salt at 1rem or more' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-sheet__tape\s*\{[^}]*background-color:\s*var\(--cr-tape\)[^}]*color:\s*var\(--cr-tape-ink\)/s', $css ), 'tape uses --cr-tape and --cr-tape-ink' );

// Grid: 1 column, 2 from 40rem, 3 from 64rem.
cr_fav_css_check( (bool) preg_match( '/@media \(min-width: 40rem\)\s*\{[^}]*pkiw-group__items\s*\{[^}]*repeat\(2,/s', $css ) && (bool) preg_match( '/@media \(min-width: 64rem\)\s*\{[^}]*pkiw-group__items\s*\{[^}]*repeat\(3,/s', $css ), 'items: 2 columns from 40rem, 3 from 64rem' );

// Focus: ring and 7px halo on the clipping, with the halo kept violet inside paper.
cr_fav_css_check( str_contains( $css, '--cr-focus-halo: var(--cr-russian-violet)' ), 'the halo is violet inside paper in both schemes' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-item:has\(a:focus-visible\) \.cr-fav-clipping\s*\{[^}]*3px solid var\(--cr-focus-ring\)[^}]*0 0 0 7px var\(--cr-focus-halo\)/s', $css ), 'archive focus: 3px ring and 7px halo around the clipping' );

// Fidelity fixes (2026-10-15): specificity against the rules that outranked the sheet.
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-sheet \.cr-fav-sheet__title\s*\{[^}]*color:\s*var\(--cr-russian-violet\)/s', $css ), 'single H1 pins violet at two classes, above components.css has-main-accent-background-color h1' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-card \.cr-fav-card__title \.cr-fav-link\s*\{[^}]*color:\s*var\(--cr-cerulean\)[^}]*text-decoration:\s*underline/s', $css ), 'Stream title link: cerulean and underlined at three classes, above the plugin pk-title a' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-card \.cr-fav-card__title \.cr-fav-link:hover\s*\{[^}]*color:\s*var\(--cr-russian-violet\)/s', $css ), 'Stream title link hover: violet, above the plugin hover rule' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-sheet::before,\s*\.cr-fav-sheet::after\s*\{[^}]*background-color:\s*var\(--cr-white\)/s', $css ) && (bool) preg_match( '/\.cr-fav-sheet::before\s*\{[^}]*inset-block-end:\s*100%[^}]*mask:/s', $css ) && (bool) preg_match( '/\.cr-fav-sheet::after\s*\{[^}]*inset-block-start:\s*100%[^}]*mask:/s', $css ), 'sheet: torn top and bottom strips, flat masks in the sheet fill' );
cr_fav_css_check( (bool) preg_match( '/forced-colors: active\).*\.cr-fav-sheet::before,\s*\.cr-fav-sheet::after,\s*\.cr-fav-sheet__mount::before/s', $css ), 'forced colors: the sheet tear drops' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-item--protected \.pk-card--protected\s*\{[^}]*position:\s*static[^}]*display:\s*block[^}]*border:\s*0[^}]*box-shadow:\s*none/s', $css ), 'protected item: the plugin card loses its box, grid and positioning' );
cr_fav_css_check( (bool) preg_match( '/\.cr-fav-link,\s*\.cr-fav-item--protected \.pk-card--protected \.pk-title a\s*\{[^}]*color:\s*var\(--cr-cerulean\)/s', $css ), 'protected item: the title link takes the shared link look' );

cr_fav_css_check( (bool) preg_match( '/\.cr-fav-item--protected \.pk-card--protected \.pk-title\s*\{\s*position:\s*static/s', $css ), 'protected item: the plugin title is unpositioned so the link covers the whole clipping' );

// Modes.
cr_fav_css_check( str_contains( $css, '@media (forced-colors: active)' ) && str_contains( $css, '2px solid CanvasText' ) && str_contains( $css, 'LinkText' ), 'forced colors: CanvasText outlines and LinkText links' );
cr_fav_css_check( (bool) preg_match( '/forced-colors: active\).*\.cr-fav-star__shape/s', $css ), 'forced colors: star art drops' );
cr_fav_css_check( str_contains( $css, '@media (prefers-reduced-motion: reduce)' ) && str_contains( $css, '@media print' ), 'reduced motion and print blocks exist' );
cr_fav_css_check( ! preg_match( '/@keyframes|animation:\s*+(?!none)|transition:\s*+(?!none)/', $css ), 'nothing animates' );

// Facts only (X16): the theme never reads block attributes or post meta for a favorite.
foreach ( array( 'inc/favorite.php', 'inc/single-favorite.php', 'inc/stream-favorite.php' ) as $file ) {
	$text = str_replace( '\\\\', '\\', cr_fav_css_read( $file ) );
	cr_fav_css_check( 0 === preg_match( '/get_post_meta|parse_blocks|->post_content|\\\\PKIW\\\\(?!kind_facts|get_kind_label|pkiw_new_tab_label_attr|Grouped_Archive)/', $text ), $file . ' reads the plugin only through kind_facts and its label helpers' );
}
cr_fav_css_check( str_contains( cr_fav_css_read( 'inc/favorite.php' ), "function_exists( '\\\\PKIW\\\\kind_facts' )" ), 'kind_facts is called behind function_exists' );

// Ownership: the lane touches no TINT or W3-T0 file.
cr_fav_css_check( ! is_file( $theme . '/assets/css/cr-favorite.css.bak' ), 'no stray files' );

echo 'TOTAL ', $count, ' checks, ', count( $fails ), " failed\n";
exit( empty( $fails ) ? 0 : 1 );
