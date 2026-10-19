<?php
/**
 * Static contract checks for jam CSS, template, pattern and SVG assets.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$root     = dirname( __DIR__, 2 );
$css      = (string) file_get_contents( $root . '/assets/css/cr-jam.css' );
$template = (string) file_get_contents( $root . '/templates/taxonomy-kind-jam.html' );
$pattern  = (string) file_get_contents( $root . '/patterns/cr-jukebox-loop.php' );
$failures = array();

/** Record a static assertion. */
function cr_jam_css_assert( bool $ok, string $message ): void {
	global $failures;
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

/** Print one static-check result. */
function cr_jam_css_group( string $name, callable $checks ): void {
	global $failures;
	$before = count( $failures );
	try {
		$checks();
	} catch ( Throwable $error ) {
		$failures[] = $name . ': ' . $error->getMessage();
	}
	echo ( $before === count( $failures ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}

cr_jam_css_group(
	'CSS uses the locked palette, type floor and physical effects',
	static function () use ( $css ): void {
		$without_comments = (string) preg_replace( '~/\*.*?\*/~s', '', $css );
		cr_jam_css_assert( 0 === preg_match_all( '/#[0-9a-fA-F]{3,8}\b/', $without_comments ), 'no CSS hex colors' );
		cr_jam_css_assert( 0 === preg_match( '/\bfilter\s*:/i', $css ), 'no filter declaration' );
		cr_jam_css_assert( ! str_contains( strtolower( $css ), 'blur(' ), 'no blur' );
		cr_jam_css_assert( ! str_contains( strtolower( $css ), 'gradient(' ), 'no gradient' );
		cr_jam_css_assert( 0 === preg_match( '/font-size\s*:\s*\d+(?:\.\d+)?px/i', $css ), 'no px font sizes' );
		preg_match_all( '/font-size\s*:\s*(0?\.\d+|\d+(?:\.\d+)?)rem/i', $css, $rem_sizes );
		foreach ( $rem_sizes[1] ?? array() as $size ) {
			cr_jam_css_assert( (float) $size >= 0.75, 'font rem floor: ' . $size );
		}
		preg_match_all( '/transform\s*:\s*rotate\(([^)]+)\)/i', $css, $rotations );
		foreach ( $rotations[1] ?? array() as $rotation ) {
			cr_jam_css_assert( str_starts_with( trim( $rotation ), 'var(--cr-rotate-' ), 'rotation uses a token: ' . $rotation );
		}
		cr_jam_css_assert( str_contains( $css, 'box-shadow: var(--cr-shadow-hard)' ), 'hard shadow token' );
		cr_jam_css_assert( str_contains( $css, 'box-shadow: var(--cr-shadow-hard-2)' ), 'lead hard shadow token' );
	}
);

cr_jam_css_group(
	'accessibility modes and focus/motion contracts are present',
	static function () use ( $css ): void {
		cr_jam_css_assert( str_contains( $css, ':root[data-theme="dark"]' ), 'manual dark mechanism' );
		cr_jam_css_assert( 1 === preg_match( '/@media \(prefers-color-scheme: dark\)\s*\{.*:root:not\(\[data-theme="light"\]\)/s', $css ), 'system dark mechanism' );
		cr_jam_css_assert( str_contains( $css, '@media (forced-colors: active)' ), 'forced-colors block' );
		cr_jam_css_assert( str_contains( $css, '@media (prefers-reduced-motion: no-preference)' ), 'motion opt-in block' );
		cr_jam_css_assert( str_contains( $css, '@media (prefers-reduced-motion: reduce)' ), 'reduced-motion block' );
		cr_jam_css_assert( 1 === preg_match( '/@media \(prefers-reduced-motion: no-preference\)\s*\{.*transition:.*transform: translateY\(-1px\);/s', $css ), 'listing transition and lift stay in no-preference' );
		cr_jam_css_assert( str_contains( $css, 'outline: 3px solid var(--cr-focus-ring)' ), '3px focus ring' );
		cr_jam_css_assert( str_contains( $css, '0 0 0 7px var(--cr-focus-halo)' ), '7px focus halo' );
		cr_jam_css_assert( 1 === preg_match( '/@media print\s*\{[^}]*\.cr-archive--jam::before/s', $css ), 'print hides doodles' );
		cr_jam_css_assert( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{.*\.cr-archive--jam::before.*display:\s*none;/s', $css ), 'forced colors hide doodles' );
	}
);

cr_jam_css_group(
	'archive counters, breakpoints and single layout are encoded',
	static function () use ( $css ): void {
		foreach ( array( 'counter-reset: cr-jam-sec', 'counter-increment: cr-jam-sec', 'counter-reset: cr-jam-item', 'counter-increment: cr-jam-item' ) as $rule ) {
			cr_jam_css_assert( str_contains( $css, $rule ), $rule );
		}
		cr_jam_css_assert( str_contains( $css, 'content: counter(cr-jam-sec, upper-alpha) counter(cr-jam-item) / "";' ), 'selection code has empty alt' );
		cr_jam_css_assert( str_contains( $css, '@media (min-width: 40rem)' ), '40rem breakpoint' );
		cr_jam_css_assert( str_contains( $css, '@media (min-width: 64rem)' ), '64rem breakpoint' );
		cr_jam_css_assert( str_contains( $css, 'repeat(3, minmax(0, 1fr))' ), 'three-column archive' );
		cr_jam_css_assert( str_contains( $css, 'repeat(2, minmax(0, 1fr))' ), 'two-column archive' );
		cr_jam_css_assert( str_contains( $css, 'url(../img/cr-jam-note.svg)' ), 'note doodle' );
		cr_jam_css_assert( str_contains( $css, 'url(../img/cr-jam-stars.svg)' ), 'star doodle' );
		cr_jam_css_assert( str_contains( $css, 'url(../img/cr-jam-headphones.svg)' ), 'headphone doodle' );
		cr_jam_css_assert( str_contains( $css, 'url(../img/cr-jam-cassette.svg)' ), 'cassette doodle' );
		cr_jam_css_assert( ! str_contains( $css, '--cr-font-display' ) && ! str_contains( $css, 'Rock Salt' ), 'no display font in jam CSS' );
		cr_jam_css_assert( 1 === preg_match( '/\.cr-jcard__note\s*\{[^{}]*background-image:\s*url\("data:image\/svg\+xml/s', $css ), 'note rules use an SVG data URL' );
	}
);

cr_jam_css_group(
	'template and pattern preserve the Query Loop contract',
	static function () use ( $template, $pattern ): void {
		foreach ( array( 'cr-archive--jam', 'courtneyr/archive-identity', 'courtneyr-child/cr-jukebox-loop', 'courtneyr-child/cr-browse-all', 'Jam archive' ) as $required ) {
			cr_jam_css_assert( str_contains( $template, $required ), 'template: ' . $required );
		}
		foreach ( array( 'leadCount', 'leadLabel', 'linesPerPage', "'Previous'", "'Next'", 'query-pagination', 'cr-jukebox__list' ) as $required ) {
			cr_jam_css_assert( str_contains( $pattern, $required ), 'pattern: ' . $required );
		}
		cr_jam_css_assert( 1 === preg_match( '/"perPage":12/', $pattern ), 'perPage 12' );
		cr_jam_css_assert( 1 === preg_match( '/"inherit":true/', $pattern ), 'inheriting query' );
		cr_jam_css_assert( strpos( $pattern, 'wp:query-pagination' ) > strpos( $pattern, '<!-- /wp:post-template -->' ), 'pager follows Post Template' );
		cr_jam_css_assert( 1 === preg_match( '/query-pagination-previous <\?php echo wp_json_encode\( array\( \'label\' => __\( \'Previous\'/s', $pattern ), 'Previous label uses translation' );
		cr_jam_css_assert( 1 === preg_match( '/query-pagination-next <\?php echo wp_json_encode\( array\( \'label\' => __\( \'Next\'/s', $pattern ), 'Next label uses translation' );
	}
);

cr_jam_css_group(
	'new files contain no retired jam colors',
	static function () use ( $root ): void {
		$files = array(
			'inc/jam.php',
			'inc/single-jam.php',
			'assets/css/cr-jam.css',
			'templates/taxonomy-kind-jam.html',
			'patterns/cr-jukebox-loop.php',
			'assets/img/cr-jam-note.svg',
			'assets/img/cr-jam-stars.svg',
			'assets/img/cr-jam-headphones.svg',
			'assets/img/cr-jam-cassette.svg',
		);
		foreach ( $files as $file ) {
			$contents = strtolower( (string) file_get_contents( $root . '/' . $file ) );
			foreach ( array( '3f2f7a', 'b0a0f0', 'cbc3ec' ) as $retired ) {
				cr_jam_css_assert( ! str_contains( $contents, $retired ), $file . ' excludes ' . $retired );
			}
		}
	}
);

cr_jam_css_group(
	'SVG doodles parse, stay small and use only approved colors',
	static function () use ( $root ): void {
		$allowed = array( '241c4a', '023047', '126782', '219ebc', '8ecae6', 'bcb5e3', 'fb8500', 'ffb703', 'fbfaf5', 'fee2c3' );
		foreach ( array( 'note', 'stars', 'headphones', 'cassette' ) as $name ) {
			$path = $root . '/assets/img/cr-jam-' . $name . '.svg';
			cr_jam_css_assert( file_exists( $path ), $name . ' exists' );
			cr_jam_css_assert( filesize( $path ) < 4096, $name . ' under 4 KB' );
			cr_jam_css_assert( false !== simplexml_load_file( $path ), $name . ' parses as XML' );
			$svg = (string) file_get_contents( $path );
			cr_jam_css_assert( 0 === preg_match( '/<(?:text|filter|image)\b|Gradient/i', $svg ), $name . ' has no forbidden element' );
			preg_match_all( '/#([0-9a-fA-F]{6})\b/', $svg, $colors );
			foreach ( $colors[1] ?? array() as $color ) {
				cr_jam_css_assert( in_array( strtolower( $color ), $allowed, true ), $name . ' approved color ' . $color );
			}
		}
	}
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '  ' . $failure . "\n" );
	}
	echo count( $failures ) . " failed\n";
	exit( 1 );
}

echo "all passed\n";
