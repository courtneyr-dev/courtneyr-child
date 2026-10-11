<?php
/**
 * `php tests/php/like-css-test.php`: checks the like stylesheet contract.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$css_path   = $theme_dir . '/assets/css/cr-like.css';
$failures   = array();

/** Print one named check. */
function check_case( string $name, callable $test ): void {
	global $failures;
	$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
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

/** Read the stylesheet when it exists. */
function like_css(): string {
	global $css_path;
	return is_file( $css_path ) ? (string) file_get_contents( $css_path ) : '';
}

/** Extract one declaration value from a rule that contains a selector. */
function declaration_value( string $css, string $selector_part, string $property ): string {
	$pattern = '/' . preg_quote( $selector_part, '/' ) . '[^{]*\{([^}]*)\}/s';
	if ( ! preg_match( $pattern, $css, $rule ) ) {
		return '';
	}
	if ( ! preg_match( '/' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+);/i', $rule[1], $decl ) ) {
		return '';
	}
	return trim( $decl[1] );
}

/** Whether a font-size declaration stays at or above the floor. */
function font_size_allowed( string $value ): bool {
	$value = trim( $value );
	if ( in_array( $value, array( 'inherit', 'initial', 'unset', 'var(--cr-text-floor)' ), true ) ) {
		return true;
	}
	if ( preg_match( '/^(?:1|[2-9]|0\.(?:7[5-9]|[89]\d))rem$/', $value ) ) {
		return true;
	}
	if ( preg_match( '/^max\(\s*(0\.(?:7[5-9]|[89]\d)|1(?:\.\d+)?|[2-9](?:\.\d+)?)rem\s*,/i', $value ) ) {
		return true;
	}
	if ( preg_match( '/^max\(\s*var\(--cr-text-[^)]+\)\s*,\s*var\(--cr-text-floor\)\s*\)$/i', $value ) ) {
		return true;
	}
	return (bool) preg_match( '/^[1-9](?:\.\d+)?em$/', $value );
}

check_case(
	'stylesheet file exists',
	static function (): bool {
		return '' !== like_css();
	}
);

check_case(
	'forbidden paint and rendering features are absent',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		$bad = array(
			'/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'/\brgba?\s*\(/i',
			'/\bhsla?\s*\(/i',
			'/\b(?:linear|radial|conic|repeating-linear|repeating-radial|repeating-conic)-gradient\s*\(/i',
			'/\bclip-path\s*:/i',
			'/(?:^|[;{])\s*filter\s*:/im',
			'/\bbackdrop-filter\s*:/i',
			'/text-overflow\s*:\s*ellipsis/i',
			'/--cr-black\b/',
			'/--cr-white\b(?![^;{}]*cr-like-sticker--stream|[^;{}]*border)/',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'box shadows, rotations and font sizes use the allowed tokens',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		preg_match_all( '/box-shadow\s*:\s*([^;]+);/i', $css, $shadows );
		foreach ( $shadows[1] as $shadow ) {
			$shadow = trim( $shadow );
			if ( 'none' === $shadow ) {
				continue;
			}
			if ( ! preg_match( '/^var\(--cr-shadow-hard(?:-sm|-2)?\)(?:,\s*0\s+0\s+0\s+\d+(?:px|rem)\s+var\(--cr-focus-halo\))?$/', $shadow ) ) {
				return false;
			}
		}
		preg_match_all( '/transform\s*:\s*([^;]+);/i', $css, $transforms );
		foreach ( $transforms[1] as $transform ) {
			if ( false !== strpos( $transform, 'rotate(' ) && ! preg_match( '/rotate\(\s*var\(--cr-rotate-(?:1|2|neg|neg2)\)\s*\)/', $transform ) ) {
				return false;
			}
		}
		preg_match_all( '/font-size\s*:\s*([^;]+);/i', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			if ( ! font_size_allowed( $size ) ) {
				return false;
			}
		}
		return ! empty( $shadows[1] ) && ! empty( $sizes[1] );
	}
);

check_case(
	'archive sticker book rules cover sections, grids, tilt, focus and image states',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		$heading = declaration_value( $css, '.pkiw-group__heading', 'color' );
		$tape    = false !== strpos( $css, '.pkiw-group__heading::before' )
			&& false !== strpos( $css, 'background: var(--cr-tape)' )
			&& preg_match( '/\.pkiw-group__heading::before[^{]*\{[^}]*rotate\(\s*var\(--cr-rotate-(?:1|2|neg|neg2)\)/s', $css );
		return false !== strpos( $css, '.cr-like .cr-sticker-book' )
			&& false !== strpos( $css, 'border: var(--cr-space-md) solid var(--cr-russian-violet)' )
			&& false !== strpos( $heading, 'var(--cr-tape-ink)' )
			&& (bool) $tape
			&& false !== strpos( $css, 'grid-template-columns: repeat(3, minmax(0, 1fr))' )
			&& false !== strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' )
			&& false !== strpos( $css, 'grid-template-columns: minmax(0, 1fr)' )
			&& false !== strpos( $css, 'li:nth-child(4n+1)' )
			&& false !== strpos( $css, 'var(--cr-rotate-1)' )
			&& false !== strpos( $css, 'li:nth-child(4n+2)' )
			&& false !== strpos( $css, 'var(--cr-rotate-neg)' )
			&& false !== strpos( $css, 'li:nth-child(4n+3)' )
			&& false !== strpos( $css, 'var(--cr-rotate-2)' )
			&& false !== strpos( $css, 'li:nth-child(4n+4)' )
			&& false !== strpos( $css, 'var(--cr-rotate-neg2)' )
			&& preg_match( '/\.pk-title\s+a::after[^{]*\{[^}]*inset\s*:\s*0/s', $css )
			&& false !== strpos( $css, 'var(--cr-focus-ring)' )
			&& false !== strpos( $css, 'var(--cr-focus-halo)' )
			&& preg_match( '/\.cr-cover--failed[^{]*\{[^}]*display\s*:\s*none/s', $css )
			&& false !== strpos( $css, 'overflow-wrap: anywhere' );
	}
);

check_case(
	'stream and single rules use the frozen selectors and controls',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		$stream_label = declaration_value( $css, '.cr-like-sticker--stream .pk-kindlabel', 'font-family' );
		$single_label = declaration_value( $css, 'body.single-post.kind-like .pk-kindlabel', 'font-family' );
		$original_min = declaration_value( $css, '.pk-original a', 'min-height' );
		return false !== strpos( $css, '.cr-like-sticker--stream' )
			&& false !== strpos( $css, 'body.single-post.kind-like' )
			&& false !== strpos( $stream_label, 'var(--cr-font-display)' )
			&& false !== strpos( $single_label, 'var(--cr-font-display)' )
			&& preg_match( '/\.cr-like-sticker--stream\s+\.pk-kindlabel[^{]*\{[^}]*font-size\s*:\s*(?:1rem|max\(\s*1rem\s*,)/s', $css )
			&& preg_match( '/body\.single-post\.kind-like\s+[^{}]*\.pk-kindlabel[^{]*\{[^}]*font-size\s*:\s*(?:1rem|max\(\s*1rem\s*,)/s', $css )
			&& in_array( $original_min, array( '44px', '2.75rem' ), true );
	}
);

check_case(
	'accessibility, forced-colors, reduced-motion and dark-mode branches exist',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		return false !== strpos( $css, '@media (forced-colors: active)' )
			&& false !== strpos( $css, 'CanvasText' )
			&& false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' )
			&& false !== strpos( $css, '@media (prefers-color-scheme: dark)' )
			&& false !== strpos( $css, ':root:not([data-theme="light"])' )
			&& false !== strpos( $css, ':root[data-theme="dark"]' );
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
