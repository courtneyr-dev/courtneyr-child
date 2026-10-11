<?php
/**
 * Static checks for the review stylesheet contract.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$css_path   = $theme_dir . '/assets/css/cr-review.css';
$css        = file_exists( $css_path ) ? (string) file_get_contents( $css_path ) : null;
$failures   = array();

/** Print one named check. */
function cr_review_css_check( string $name, callable $test ): void {
	global $css, $failures;
	try {
		$ok = null !== $css && (bool) $test( $css );
	} catch ( Throwable $error ) {
		$ok   = false;
		$name = $name . ' (' . $error->getMessage() . ')';
	}
	if ( null === $css ) {
		$name .= ' (missing)';
	}
	echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
	if ( ! $ok ) {
		$failures[] = $name;
	}
}

/** Strip CSS comments for value checks. */
function cr_review_css_without_comments( string $css ): string {
	return (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
}

/** Return CSS declarations for one property. */
function cr_review_css_values( string $css, string $property ): array {
	preg_match_all( '/(?<![-\w])' . preg_quote( $property, '/' ) . '\s*:\s*([^;{}]+);/i', cr_review_css_without_comments( $css ), $matches );
	return array_map( 'trim', $matches[1] );
}

/** Split selector preludes on commas. */
function cr_review_css_selectors( string $css ): array {
	$clean = cr_review_css_without_comments( $css );
	preg_match_all( '/([^{}]+)\{/', $clean, $matches );
	$selectors = array();
	foreach ( $matches[1] as $prelude ) {
		$prelude = trim( $prelude );
		if ( '' === $prelude || str_starts_with( $prelude, '@' ) || preg_match( '/^(from|to|\d+%)/', $prelude ) ) {
			continue;
		}
		foreach ( explode( ',', $prelude ) as $selector ) {
			$selectors[] = trim( $selector );
		}
	}
	return array_filter( $selectors );
}

/** Whether a selector starts in an allowed review scope. */
function cr_review_css_allowed_selector( string $selector ): bool {
	$scope = '(?:\.cr-review|\.cr-clipping|body\.cr-review-single)';
	if ( preg_match( '/^' . $scope . '(?:[\s\.#:\[>+~]|$)/', $selector ) ) {
		return true;
	}
	foreach ( array( ':root[data-theme="dark"]', ':root:not([data-theme="light"])' ) as $prefix ) {
		if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '\s+' . $scope . '(?:[\s\.#:\[>+~]|$)/', $selector ) ) {
			return true;
		}
	}
	return false;
}

cr_review_css_check(
	'paint uses tokens and no forbidden color functions',
	static function ( string $css ): bool {
		return ! preg_match( '/#[A-Fa-f0-9]{3}(?:[A-Fa-f0-9]{3})?\b|\brgba?\s*\(|\bhsla?\s*\(|\bcolor-mix\s*\(/', cr_review_css_without_comments( $css ) )
			&& ! str_contains( $css, '--cr-black' )
			&& ! str_contains( $css, '--cr-white' );
	}
);

cr_review_css_check(
	'forbidden visual effects are absent',
	static function ( string $css ): bool {
		return ! preg_match( '/\b(?:radial-gradient|linear-gradient|conic-gradient|repeating-linear-gradient|repeating-radial-gradient|blur|backdrop-filter|filter|clip-path|mask|text-overflow)\b/i', cr_review_css_without_comments( $css ) );
	}
);

cr_review_css_check(
	'font sizes stay on approved tokens or accessible units',
	static function ( string $css ): bool {
		foreach ( cr_review_css_values( $css, 'font-size' ) as $value ) {
			if ( preg_match( '/^var\(--cr-text-[^)]+\)$/', $value ) || 'inherit' === $value || preg_match( '/^\d*\.?\d+em$/', $value ) ) {
				continue;
			}
			if ( preg_match( '/^(\d*\.?\d+)rem$/', $value, $match ) && (float) $match[1] >= 0.75 ) {
				continue;
			}
			return false;
		}
		return true;
	}
);

cr_review_css_check(
	'font families stay on approved voices and display appears only on Four Stars heading',
	static function ( string $css ): bool {
		foreach ( cr_review_css_values( $css, 'font-family' ) as $value ) {
			if ( ! preg_match( '/^(?:inherit|var\(--cr-font-(?:body|accent|display|mono)\))$/', $value ) ) {
				return false;
			}
		}
		foreach ( cr_review_css_selectors( $css ) as $selector ) {
			if ( str_contains( $selector, '.pkiw-four-stars__heading' ) ) {
				continue;
			}
			$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{[^{}]*font-family\s*:\s*var\(--cr-font-display\)/s';
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		return true;
	}
);

cr_review_css_check(
	'box shadows use only hard tokens, marker token, focus halo or none',
	static function ( string $css ): bool {
		foreach ( cr_review_css_values( $css, 'box-shadow' ) as $value ) {
			if ( 'none' === $value || '0 0 0 7px var(--cr-focus-halo)' === $value ) {
				continue;
			}
			if ( preg_match( '/^var\(--cr-shadow-(?:hard|hard-sm|hard-2|marker)\)(?:,\s*0 0 0 7px var\(--cr-focus-halo\))?$/', $value ) ) {
				continue;
			}
			return false;
		}
		return true;
	}
);

cr_review_css_check(
	'rotations and border radius follow the contract',
	static function ( string $css ): bool {
		if ( preg_match( '/rotate\(\s*(?!var\(--cr-rotate-(?:1|2|neg|neg2)\))/', cr_review_css_without_comments( $css ) ) ) {
			return false;
		}
		foreach ( cr_review_css_values( $css, 'border-radius' ) as $value ) {
			if ( ! preg_match( '/^(?:0|0rem|0px)$/', $value ) ) {
				return false;
			}
		}
		return true;
	}
);

cr_review_css_check(
	'dark, forced-colors and motion media blocks are present',
	static function ( string $css ): bool {
		return substr_count( $css, ':root[data-theme="dark"]' ) > 0
			&& substr_count( $css, ':root[data-theme="dark"]' ) === substr_count( $css, ':root:not([data-theme="light"])' )
			&& str_contains( $css, '@media (prefers-color-scheme: dark)' )
			&& str_contains( $css, '@media (forced-colors: active)' )
			&& str_contains( $css, '@media (prefers-reduced-motion' );
	}
);

cr_review_css_check(
	'archive, single and clipping contract hooks are present',
	static function ( string $css ): bool {
		foreach ( array( 'data-pkiw-sections', '40rem', '64rem', 'column-count', ':has(', 'break-inside: avoid', '--cr-glaucous-dark', '--cr-tape', '--cr-link', '--cr-night-elevated', '--cr-shadow-marker', 'cr-cover--failed', '-webkit-line-clamp' ) as $needle ) {
			if ( ! str_contains( $css, $needle ) ) {
				return false;
			}
		}
		return true;
	}
);

cr_review_css_check(
	'all selectors are scoped to review surfaces',
	static function ( string $css ): bool {
		foreach ( cr_review_css_selectors( $css ) as $selector ) {
			if ( ! cr_review_css_allowed_selector( $selector ) ) {
				return false;
			}
		}
		return true;
	}
);

cr_review_css_check(
	'no sample copy or issue-style hex-looking comment tokens',
	static function ( string $css ): bool {
		return ! preg_match( '/sample content/i', $css )
			&& ! preg_match( '/(?<![A-Fa-f0-9])#[A-Fa-f0-9]{3}(?:[A-Fa-f0-9]{3})?(?![A-Fa-f0-9])/', $css );
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
