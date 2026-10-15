<?php
/**
 * `php tests/php/acquisition-assets-test.php`: checks the acquisition template, pattern and stylesheet.
 *
 * Reads files only. Covers PKIW issue 239 lines that don't need a running
 * site: the template's order, the pattern's query and marker, and the
 * stylesheet's token, shadow, rotation, text-floor, forced-colors and
 * reduced-motion rules.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$root     = dirname( __DIR__, 2 );
$failures = array();

/** Record one assertion. */
function cr_acq_assets_check( bool $ok, string $message ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $message . "\n";
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

/** Read a repo file. */
function cr_acq_assets_read( string $path ): string {
	global $root;
	$source = file_get_contents( $root . '/' . $path );
	return false === $source ? '' : $source;
}

$template = cr_acq_assets_read( 'templates/taxonomy-kind-acquisition.html' );
$pattern  = cr_acq_assets_read( 'patterns/cr-acquisition-bags-loop.php' );
$css      = cr_acq_assets_read( 'assets/css/cr-acquisition.css' );
$months   = '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/';

// Template: header, identity, title, description, divider, bags pattern, then browse-all.
$order = array( 'wp:template-part {"slug":"header"', 'courtneyr/archive-identity', 'wp:query-title', 'wp:term-description', 'is-style-cr-marker-bar-short', 'courtneyr-child/cr-acquisition-bags-loop', 'courtneyr-child/cr-browse-all', 'wp:template-part {"slug":"footer"' );
$last  = -1;
$ok    = true;
foreach ( $order as $needle ) {
	$at = strpos( $template, $needle );
	$ok = $ok && false !== $at && $at > $last;
	$last = false === $at ? $last : $at;
}
cr_acq_assets_check( $ok, 'template order: header, identity, title, description, divider, bags, browse-all, footer' );
cr_acq_assets_check( str_contains( $template, '"className":"cr-flank cr-flank--blue cr-archive__title"' ), 'template: the h1 carries the blue burst' );
cr_acq_assets_check( str_contains( $template, '"showPrefix":false' ), 'template: no archive prefix' );
cr_acq_assets_check( str_contains( $template, 'cr-archive--acquisition' ), 'template: archive modifier class' );
cr_acq_assets_check( ! str_contains( $template, 'wp:query ' ), 'template: no query of its own, the pattern holds the one inherited query' );
cr_acq_assets_check( 0 === preg_match( '/SAMPLE CONTENT/i', $template . $pattern ), 'no sample-content label ships' );
cr_acq_assets_check( 0 === preg_match( $months, $template . $pattern . $css . cr_acq_assets_read( 'inc/acquisition.php' ) . cr_acq_assets_read( 'inc/single-acquisition.php' ) . cr_acq_assets_read( 'inc/stream-acquisition.php' ) ), 'no month name is hardcoded in templates, patterns, inc files or CSS' );

// Pattern: one inherited query, the sections marker, the receipt style, core pagination.
cr_acq_assets_check( str_contains( $pattern, 'Slug: courtneyr-child/cr-acquisition-bags-loop' ) && str_contains( $pattern, 'Inserter: false' ), 'pattern header' );
cr_acq_assets_check( 1 === substr_count( $pattern, '<!-- wp:query {' ) && str_contains( $pattern, '"queryId":239' ) && str_contains( $pattern, '"inherit":true' ), 'pattern: one inherited Query Loop, queryId 239' );
cr_acq_assets_check( str_contains( $pattern, 'wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2}' ), 'pattern: the marker sets 12 lines a page and h2 labels' );
cr_acq_assets_check( str_contains( $pattern, 'wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-receipt"}' ), 'pattern: stream cards take the receipt style at h3' );
cr_acq_assets_check( ! str_contains( $pattern, 'cr-stream-page' ), 'pattern: no cr-stream-page class, so the collage tilt cannot reach receipts' );
cr_acq_assets_check( 2 === substr_count( $pattern, 'is_registered(' ), 'pattern: each plugin block sits behind an is_registered guard' );
cr_acq_assets_check( str_contains( $pattern, 'wp:query-no-results' ) && str_contains( $pattern, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' ), 'pattern: shipped empty-state copy' );
cr_acq_assets_check( str_contains( $pattern, 'wp:query-pagination-previous {"label":"Previous"}' ) && str_contains( $pattern, 'wp:query-pagination-next {"label":"Next"}' ), 'pattern: core pagination with Previous and Next labels' );
cr_acq_assets_check( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $pattern ), 'pattern: no color-like literal' );

// Stylesheet.
cr_acq_assets_check( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $css ) && 0 === preg_match( '/\b(rgb|rgba|hsl|hsla)\(/i', $css ), 'css: no color literal, tokens only' );
preg_match_all( '/(?<![-\w])filter:\s*([^;]+);/', $css, $filters );
cr_acq_assets_check( array() === array_diff( array_unique( $filters[1] ), array( 'none' ) ), 'css: filter only ever resets to none (the ticket sheet draws the torn-edge shadow)' );
cr_acq_assets_check( 0 === preg_match( '/blur\(|backdrop-filter/', $css ), 'css: no blur' );
preg_match_all( '/box-shadow:\s*([^;]+);/', $css, $shadows );
$shadow_ok = true;
foreach ( $shadows[1] as $shadow ) {
	$shadow_ok = $shadow_ok && 1 === preg_match( '/^(none|var\(--cr-shadow-hard(-sm|-2)?\)|0 0 0 7px var\(--cr-(focus-halo|russian-violet)\))$/', trim( $shadow ) );
}
cr_acq_assets_check( $shadow_ok, 'css: shadows are the hard-shadow tokens or the 7px focus halo' );
preg_match_all( '/rotate\(([^)]*\)?)\)/', $css, $rotations );
$rotation_ok = true;
foreach ( $rotations[1] as $rotation ) {
	$rotation_ok = $rotation_ok && 1 === preg_match( '/^var\(--cr-rotate-(1|2|neg|neg2)\)$/', trim( $rotation ) );
}
cr_acq_assets_check( $rotation_ok && count( $rotations[1] ) > 0, 'css: rotations come only from the --cr-rotate-* tokens' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-acq-stub\s*\{[^}]*transform:\s*rotate\(var\(--cr-rotate-1\)\)/s', $css ), 'css: the stub has one rotation token' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-receipt\s*\{[^}]*transform:\s*none/s', $css ), 'css: receipts are upright' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-acq \.pkiw-group__heading\s*\{[^}]*transform:\s*none/s', $css ), 'css: the month label text is upright' );
preg_match_all( '/font-size:\s*([^;]+);/', $css, $sizes );
$size_ok = true;
foreach ( $sizes[1] as $size ) {
	$size_ok = $size_ok && ( 1 === preg_match( '/^max\(var\(--cr-text-[a-z0-9]+\), var\(--cr-text-floor\)\)$/', trim( $size ) ) || 1 === preg_match( '/^var\(--cr-text-(md|lg|xl|2xl|3xl)\)$/', trim( $size ) ) );
}
cr_acq_assets_check( $size_ok && count( $sizes[1] ) > 0, 'css: every font size is a token at or above --cr-text-floor' );
cr_acq_assets_check( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{.*CanvasText.*LinkText/s', $css ), 'css: forced-colors block with CanvasText outlines and LinkText links' );
cr_acq_assets_check( 1 === preg_match( '/@media \(prefers-reduced-motion: reduce\)\s*\{[^}]*\.cr-receipt[^}]*\.cr-acq-stub[^}]*transform:\s*none/s', $css ), 'css: reduced motion clears receipt and stub transforms' );
cr_acq_assets_check( 1 === preg_match( '/@container \(min-width: 17\.75rem\)/', $css ), 'css: two receipts across at an 18rem bag (284px inside its 2px edges)' );
cr_acq_assets_check( 1 === preg_match( '/@media \(min-width: 48rem\)[^{]*\{\s*\.cr-acq \.pkiw-grouped\s*\{[^}]*repeat\(2,/s', $css ) && 1 === preg_match( '/@media \(min-width: 64rem\)[^{]*\{\s*\.cr-acq \.pkiw-grouped\s*\{[^}]*repeat\(3,/s', $css ), 'css: 1 bag a row below 48rem, 2 from 48rem, 3 from 64rem' );
cr_acq_assets_check( 4 === preg_match_all( '/--cr-acq-bag:\s*var\(--cr-(periwinkle|sky-blue|blue-green|cerulean)\)/', $css ), 'css: bag fills cycle periwinkle, sky blue, blue-green, cerulean' );
cr_acq_assets_check( str_contains( $css, '.cr-receipt:has(a:focus-visible)' ) && str_contains( $css, '.cr-acq-stub:has(a:focus-visible)' ) && str_contains( $css, 'var(--cr-focus-ring)' ), 'css: receipts and the stub draw the 3px focus ring' );
cr_acq_assets_check( str_contains( $css, '.cr-fieldnotes .cr-home-stream .cr-acq-stub' ) && str_contains( $css, 'max-width: 15.625rem' ), 'css: the front-page lane gives the stub its 15.625rem measure' );
cr_acq_assets_check( 1 === preg_match( '/body\.cr-acq-single-page\.single \.single-post__content \.pk-card\.k-acquisition\s*\{/', $css ), 'css: later cards print as a compact receipt' );
cr_acq_assets_check( 1 === preg_match( '/body\.cr-acq-single-page\.single \.single-post__content \.pk-card\.k-acquisition \.pk-kindlabel\s*\{[^}]*color:\s*var\(--cr-russian-violet\)/', $css ), 'css: the later card label pins violet ink, so the dark pk-muted token never lands on the ivory card' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-acq-single__receipt \.cr-acq-single__title\s*\{[^}]*color:\s*var\(--cr-russian-violet\)/', $css ), 'css: the single title pins violet ink above the accent-background h1 override' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-acq-single__notes \.cr-acq-single__notes-title\s*\{[^}]*color:\s*var\(--cr-tape-ink\)/', $css ), 'css: the notes title follows the tape ink token' );
cr_acq_assets_check( 1 === preg_match( '/\.cr-acq-stub \.pk-kindlabel\s*\{[^}]*display:\s*block/', $css ), 'css: the stub label is a block so its margin applies to the span' );
cr_acq_assets_check( str_contains( cr_acq_assets_read( 'inc/enqueue.php' ), "'cr-acquisition.css'" ) && str_contains( cr_acq_assets_read( 'inc/enqueue.php' ), "'cr-ticket.css'" ), 'RUCSS list already names cr-acquisition.css and cr-ticket.css' );

if ( $failures ) {
	echo count( $failures ) . " failed\n";
	exit( 1 );
}
echo "all passed\n";
