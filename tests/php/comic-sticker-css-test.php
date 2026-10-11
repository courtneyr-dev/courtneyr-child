<?php
/**
 * Static checks for comic sticker and recipe print CSS.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$root    = dirname( __DIR__, 2 );
$comic   = (string) file_get_contents( $root . '/assets/css/cr-comic.css' );
$recipe  = (string) file_get_contents( $root . '/assets/css/cr-recipe.css' );
$failures = array();

function cr_comic_css_assert( bool $ok, string $message ): void {
	global $failures;
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

function cr_comic_css_group( string $name, callable $checks ): void {
	global $failures;
	$before = count( $failures );
	$checks();
	$ok = $before === count( $failures );
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}

function cr_comic_css_rule( string $css, string $selector ): string {
	if ( preg_match( '/' . preg_quote( $selector, '/' ) . '\s*{(?P<body>[^}]*)}/s', $css, $m ) ) {
		return $m['body'];
	}
	return '';
}

function cr_comic_css_block( string $css, string $needle ): string {
	$start = strpos( $css, $needle );
	if ( false === $start ) {
		return '';
	}
	$open = strpos( $css, '{', $start );
	if ( false === $open ) {
		return '';
	}
	$depth = 0;
	$len   = strlen( $css );
	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $css[ $i ] ) {
			++$depth;
		} elseif ( '}' === $css[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return substr( $css, $open + 1, $i - $open - 1 );
			}
		}
	}
	return '';
}

cr_comic_css_group(
	'base comic sticker remains a text-floor circle',
	static function () use ( $comic ): void {
		$rule = cr_comic_css_rule( $comic, '.cr-comic-rack .cr-comic__sticker' );
		cr_comic_css_assert( '' !== $rule, 'base sticker rule exists' );
		foreach ( array( 'aspect-ratio: 1', 'border-radius: var(--cr-radius-full)', 'font-size: var(--cr-text-floor)' ) as $needle ) {
			cr_comic_css_assert( str_contains( $rule, $needle ), 'base sticker has ' . $needle );
		}
	}
);

cr_comic_css_group(
	'phone comic sticker becomes a pill without shrinking text',
	static function () use ( $comic ): void {
		$media = cr_comic_css_block( $comic, '@media (max-width: 40rem)' );
		$rule  = cr_comic_css_rule( $media, '.cr-comic-rack .cr-comic__sticker' );
		cr_comic_css_assert( '' !== $media, 'phone media block exists' );
		cr_comic_css_assert( '' !== $rule, 'phone sticker rule exists' );
		foreach ( array( 'width: fit-content', 'max-width: calc(var(--cr-space-4xl) - var(--cr-space-2xs))', 'aspect-ratio: auto' ) as $needle ) {
			cr_comic_css_assert( str_contains( $rule, $needle ), 'phone sticker has ' . $needle );
		}
		cr_comic_css_assert( ! preg_match( '/font-size\s*:/', $rule ), 'phone sticker sets no font-size' );
	}
);

cr_comic_css_group(
	'recipe print button keeps sharp corners',
	static function () use ( $recipe ): void {
		$rule = cr_comic_css_rule( $recipe, 'body.cr-recipe-single .cr-recipe__actions .wprm-recipe-print' );
		cr_comic_css_assert( '' !== $rule, 'recipe print rule exists' );
		cr_comic_css_assert( str_contains( $rule, 'border-radius: 0' ), 'recipe print rule has border-radius: 0' );
	}
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '  ' . $failure . "\n" );
	}
	exit( 1 );
}

echo "all passed\n";
