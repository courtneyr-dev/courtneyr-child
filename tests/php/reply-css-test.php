<?php
/**
 * Static checks for reply letter CSS.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

$root       = dirname( __DIR__, 2 );
$css        = (string) file_get_contents( $root . '/assets/css/cr-letters.css' );
$tokens     = (string) file_get_contents( $root . '/assets/css/tokens.css' );
$post_kinds = (string) file_get_contents( $root . '/assets/css/cr-post-kinds.css' );
$nav        = (string) file_get_contents( $root . '/assets/css/cr-nav.css' );

function cr_reply_token_hex( string $tokens, string $name ): string {
	if ( preg_match( '/--' . preg_quote( $name, '/' ) . ':\s*(#[0-9a-fA-F]{6})/', $tokens, $m ) ) {
		return $m[1];
	}
	return '';
}

function cr_reply_luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	$rgb = array_map( 'hexdec', str_split( $hex, 2 ) );
	$lin = array_map(
		static function ( int $channel ): float {
			$value = $channel / 255;
			return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		},
		$rgb
	);
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

function cr_reply_contrast( string $a, string $b ): float {
	$la = cr_reply_luminance( $a );
	$lb = cr_reply_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

function cr_reply_font_below_floor( string $css ): array {
	preg_match_all( '/font-size:\s*([0-9]*\.?[0-9]+)(rem|em|px)\b/', $css, $matches, PREG_SET_ORDER );

	$offending = array();
	foreach ( $matches as $match ) {
		$value = (float) $match[1];
		$px    = 'px' === $match[2] ? $value : $value * 16;
		if ( $px < 12 ) {
			$offending[] = $match[0];
		}
	}

	return $offending;
}

cr_reply_group(
	'css avoids forbidden primitives',
	static function () use ( $css ): void {
		cr_reply_assert( preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $css ) === 0, 'no raw hex' );
		foreach ( array( 'rgb(', 'rgba(', 'filter', 'grayscale', 'blur(' ) as $gone ) {
			cr_reply_not_contains( $gone, $css, $gone . ' absent' );
		}
		foreach ( array( '.wp-block-query-pagination', '.page-numbers', '.cr-post-nav' ) as $gone ) {
			cr_reply_not_contains( $gone, $css, $gone . ' selector absent' );
		}
		$rotate_count = substr_count( $css, 'rotate(' );
		cr_reply_assert( $rotate_count > 0, 'rotate tokens present' );
		cr_reply_same( $rotate_count, preg_match_all( '/rotate\(\s*var\(--cr-rotate-(?:1|2|neg|neg2)\)\s*\)/', $css ), 'every rotate uses an allowed token' );
		cr_reply_assert( preg_match( '/\.cr-letters \.pkiw-group__heading\s*{[^}]*(?:^|\s)(?:transform|rotate):/s', $css ) === 0, 'host heading stays upright' );
		foreach ( array( 'font-size: 0.7rem', 'font-size: 0.74rem', 'font-size: 0.65rem', 'font-size: 11px', 'font-size: 0.7em' ) as $too_small ) {
			cr_reply_assert( count( cr_reply_font_below_floor( $too_small ) ) > 0, 'no font below text floor proof catches ' . $too_small );
		}
		foreach ( array( 'font-size: 0.75rem', 'font-size: 12px', 'font-size: 0.875rem', 'font-size: 1rem' ) as $allowed ) {
			cr_reply_assert( count( cr_reply_font_below_floor( $allowed ) ) === 0, 'no font below text floor proof allows ' . $allowed );
		}
		cr_reply_assert( count( cr_reply_font_below_floor( $css ) ) === 0, 'no font below text floor' );
		cr_reply_assert( preg_match( '/\.cr-letters li\.wp-block-post\.cr-paper-sheet > \*[^{}]*{[^}]*(?:^|\s)position:/s', $css ) === 0, 'letter children are not positioned' );
		foreach ( array( 'transform', 'position', 'z-index' ) as $property ) {
			cr_reply_assert( preg_match( '/\.cr-envelope__insert(?:--tilt-[1-4])?\s*{[^}]*' . preg_quote( $property, '/' ) . '\s*:/s', $css ) === 0, 'insert subject has no ' . $property );
		}
	}
);

cr_reply_group(
	'css includes required reply surfaces',
	static function () use ( $css, $nav ): void {
		foreach ( array( 'forced-colors: active', 'prefers-reduced-motion', ':root[data-theme="dark"]', ':root:not( [data-theme="light"] )', 'overflow-wrap: anywhere', 'repeat( 3, minmax( 0, 1fr ) )', 'repeat( 2, minmax( 0, 1fr ) )', '40rem', '64rem', '--cr-focus-ring', '::after', '--cr-font-display', '--cr-type-reply', 'cr-envelope__insert--tilt-1', 'cr-letter-cite__host' ) as $needle ) {
			cr_reply_contains( $needle, $css, $needle );
		}
		cr_reply_contains( '.cr-envelope::before', $css, 'envelope before fold' );
		cr_reply_contains( '.cr-envelope::after', $css, 'envelope after fold' );
		foreach ( array( 'min-height: 2.75rem', 'var(--cr-shadow-hard-sm)', 'forced-colors: active', 'dark' ) as $needle ) {
			cr_reply_contains( $needle, $nav, 'nav keeps ' . $needle );
		}
		cr_reply_assert( preg_match( '/\.cr-letters \.pkiw-group__items,[^{]*{[^}]*max-inline-size:\s*none/s', $css ) === 1, 'letter grid lifts the global list measure (tokens.css caps ul at --cr-measure)' );
		cr_reply_assert( preg_match( '/cr-letter__signature[^}]*font-family:\s*var\(--cr-font-display\)[^}]*font-size:\s*(?:1rem|var\(--cr-text-base\))/s', $css ) === 1, 'signature font and size' );
	}
);

cr_reply_group(
	'palette contrast and old reply paint stay clean',
	static function () use ( $tokens, $post_kinds ): void {
		$violet = cr_reply_token_hex( $tokens, 'cr-russian-violet' );
		foreach ( array( 'cr-sky-blue', 'cr-periwinkle', 'cr-light-orange', 'cr-selective-yellow', 'cr-printer-ivory' ) as $paper ) {
			cr_reply_assert( cr_reply_contrast( cr_reply_token_hex( $tokens, $paper ), $violet ) >= 4.5, $paper . ' contrasts with violet ink' );
		}
		$ivory = cr_reply_token_hex( $tokens, 'cr-printer-ivory' );
		foreach ( array( 'cr-russian-violet', 'cr-cerulean', 'cr-ut-orange-dark' ) as $ink ) {
			cr_reply_assert( cr_reply_contrast( cr_reply_token_hex( $tokens, $ink ), $ivory ) >= 3, $ink . ' h2 border contrast' );
		}
		$dark = cr_reply_token_hex( $tokens, 'cr-photocopier-blk' );
		foreach ( array( 'cr-periwinkle', 'cr-selective-yellow' ) as $ink ) {
			cr_reply_assert( cr_reply_contrast( cr_reply_token_hex( $tokens, $ink ), $dark ) >= 3, $ink . ' dark h2 border contrast' );
		}
		foreach ( array( '4a6a9d', 'd7e0ef', 'eaf4fa', 'content: "💬' ) as $gone ) {
			cr_reply_not_contains( $gone, $post_kinds, $gone . ' old reply paint absent' );
		}
		cr_reply_assert( preg_match( '/k-reply[^{}]*\.pk-title[^{}]*{[^}]*font-size:\s*0/', $post_kinds ) === 0, 'no reply title zero font size' );
	}
);

cr_reply_finish();
