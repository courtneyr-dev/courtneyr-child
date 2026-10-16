<?php
/**
 * `php tests/php/w4-t0-css-test.php`: checks W4-T0 shared paint contracts
 * without WordPress: the kinds cut from cr-post-kinds.css, the sticky
 * primitive left in place, cr-calendar.css, the craft print block and
 * Stamps\BIB_PAPERS.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$failures  = array();

/** Print one named check. */
function check_case( string $name, callable $test ): void {
	global $failures;
	try {
		$ok = (bool) $test();
	} catch ( Throwable $error ) {
		$ok   = false;
		$name = $name . ' (' . $error->getMessage() . ')';
	}
	echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
	if ( ! $ok ) {
		$failures[] = $name;
	}
}

/** Read a file when it exists. */
function read_file( string $path ): string {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

$post_kinds = read_file( $theme_dir . '/assets/css/cr-post-kinds.css' );
$calendar   = read_file( $theme_dir . '/assets/css/cr-calendar.css' );
$print      = read_file( $theme_dir . '/assets/css/cr-print.css' );
$tokens     = read_file( $theme_dir . '/assets/css/tokens.css' );
$stamps     = read_file( $theme_dir . '/inc/stamps.php' );

check_case(
	'cr-post-kinds.css has no weather, sleep, exercise or craft kind selectors',
	static function () use ( $post_kinds ): bool {
		return '' !== $post_kinds && 0 === preg_match( '/k-weather|kind-weather|k-sleep|kind-sleep|k-exercise|k-craft/', $post_kinds );
	}
);

check_case(
	'the sticky primitive stays unscoped in cr-post-kinds.css',
	static function () use ( $post_kinds ): bool {
		foreach ( array(
			"\n.cr-sticky-artifact {",
			"\n.cr-sticky__curl {",
			"\n.cr-sticky,\n.cr-stream-page .cr-sticky-artifact--stream .pk-card.k-note.cr-sticky,",
			"\n:root[data-theme=\"dark\"] .cr-sticky-artifact { filter: drop-shadow(4px 4px 0 var(--cr-selective-yellow, #ffb703)); }",
			"\n\t:root:not([data-theme=\"light\"]) .cr-sticky-artifact { filter: drop-shadow(4px 4px 0 var(--cr-selective-yellow, #ffb703)); }",
		) as $needle ) {
			if ( false === strpos( $post_kinds, $needle ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'cr-calendar.css holds the shared cell, year, month and week rules',
	static function () use ( $calendar ): bool {
		foreach ( array( '.pkiw-cal {', '.pkiw-cal__day {', '.pkiw-cal__link {', ':where(.pkiw-cal--year) .pkiw-cal__day', ':where(.pkiw-cal--month)', '.pkiw-cal--week', '--cr-almanac-offset', '@media (min-width: 48rem)', '@media (forced-colors: active)', '2.75rem' ) as $needle ) {
			if ( false === strpos( $calendar, $needle ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'cr-calendar.css uses tokens only: no hex, rgb, hsl, gradient, filter, blur or color-mix',
	static function () use ( $calendar ): bool {
		$code = preg_replace( '#/\*.*?\*/#s', '', $calendar );
		return '' !== $code && 0 === preg_match( '/#[0-9a-f]{3,8}\b|\brgba?\s*\(|\bhsla?\s*\(|gradient|filter\s*:|blur\(|color-mix\(|font-size/i', (string) $code );
	}
);

check_case(
	'cr-calendar.css sets the month offset from --cr-almanac-offset with an auto fallback',
	static function () use ( $calendar ): bool {
		return false !== strpos( $calendar, 'grid-column-start: var(--cr-almanac-offset, auto);' )
			&& false !== strpos( $calendar, 'repeat(7, minmax(0, 1fr))' );
	}
);

check_case(
	'cr-print.css carries the craft block',
	static function () use ( $print ): bool {
		return 1 === preg_match( '/\.cr-pegboard__board\s*\{\s*background:\s*none\s*!important;/', $print )
			&& 1 === preg_match( '/\.cr-pegboard::before,\s*\.cr-pegboard::after,\s*\.cr-peg-card::before\s*\{\s*display:\s*none\s*!important;/', $print )
			&& 1 === preg_match( '/\.cr-peg-card,\s*\.pkiw-craft-step\s*\{\s*break-inside:\s*avoid;/', $print );
	}
);

check_case(
	'Stamps\BIB_PAPERS lists the five bib papers, each a token in tokens.css',
	static function () use ( $stamps, $tokens ): bool {
		if ( 1 !== preg_match( '/const BIB_PAPERS = array\(([^)]*)\);/', $stamps, $match ) ) {
			return false;
		}
		preg_match_all( "/'([a-z-]+)'/", $match[1], $names );
		if ( array( 'sky-blue', 'periwinkle', 'light-orange', 'light-gray', 'printer-ivory' ) !== $names[1] ) {
			return false;
		}
		foreach ( $names[1] as $name ) {
			if ( false === strpos( $tokens, '--cr-' . $name . ':' ) ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
exit( count( $failures ) ? 1 : 0 );
