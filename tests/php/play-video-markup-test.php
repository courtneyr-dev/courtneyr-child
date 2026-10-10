<?php
/**
 * `php tests/php/play-video-markup-test.php`: static checks for video play objects.
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

/** Normalize CSS declaration values. */
function css_norm( string $value ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
}

/** Parse simple CSS rules. */
function css_rules( string $css ): array {
	$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER );
	$rules = array();
	foreach ( $matches as $match ) {
		$selector = trim( $match[1] );
		if ( '' === $selector || str_starts_with( $selector, '@' ) ) {
			continue;
		}
		$decls = array();
		foreach ( explode( ';', trim( $match[2] ) ) as $decl ) {
			if ( ! str_contains( $decl, ':' ) ) {
				continue;
			}
			list( $property, $value ) = array_map( 'trim', explode( ':', $decl, 2 ) );
			$decls[ strtolower( $property ) ] = css_norm( $value );
		}
		$selectors = array_map( 'css_norm', explode( ',', $selector ) );
		$rules[]   = array( $selectors, $decls );
	}
	return $rules;
}

/** Find a rule containing an exact selector. */
function rule_for( array $rules, string $selector ): ?array {
	$selector = css_norm( $selector );
	foreach ( $rules as $rule ) {
		if ( in_array( $selector, $rule[0], true ) ) {
			return $rule[1];
		}
	}
	return null;
}

/** Assert declarations for one selector. */
function has_decls( array $rules, string $selector, array $expected ): bool {
	$decls = rule_for( $rules, $selector );
	if ( null === $decls ) {
		return false;
	}
	foreach ( $expected as $property => $value ) {
		if ( ! isset( $decls[ $property ] ) || css_norm( $value ) !== $decls[ $property ] ) {
			return false;
		}
	}
	return true;
}

/** Extract CSS variable definitions from text. */
function css_var_defs( string $css ): array {
	preg_match_all( '/(--cr-[a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER );
	$defs = array();
	foreach ( $matches as $match ) {
		$defs[ $match[1] ] = css_norm( $match[2] );
	}
	return $defs;
}

/** Resolve a token to a hex value. */
function resolve_hex( string $token, array $defs, array $seen = array() ): string {
	if ( isset( $seen[ $token ] ) || ! isset( $defs[ $token ] ) ) {
		return '';
	}
	$value = trim( $defs[ $token ] );
	if ( preg_match( '/#[0-9a-fA-F]{6}\b/', $value, $match ) ) {
		return strtolower( $match[0] );
	}
	if ( preg_match( '/var\(\s*(--cr-[a-z0-9-]+)\s*\)/i', $value, $match ) ) {
		$seen[ $token ] = true;
		return resolve_hex( $match[1], $defs, $seen );
	}
	return '';
}

/** Contrast ratio for two hex colors. */
function contrast_ratio( string $a, string $b ): float {
	$lum = static function ( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$rgb = array_map(
			static function ( string $part ): float {
				$v = hexdec( $part ) / 255;
				return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			},
			array( substr( $hex, 0, 2 ), substr( $hex, 2, 2 ), substr( $hex, 4, 2 ) )
		);
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	};
	$l1 = $lum( $a );
	$l2 = $lum( $b );
	if ( $l2 > $l1 ) {
		list( $l1, $l2 ) = array( $l2, $l1 );
	}
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}

$css       = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-video.css' );
$php       = (string) file_get_contents( $theme_dir . '/inc/play-video.php' );
$tokens    = (string) file_get_contents( $theme_dir . '/assets/css/tokens.css' );
$rules     = css_rules( $css );
$new_files = array(
	'inc/play-video.php',
	'assets/css/cr-play-video.css',
	'tests/php/play-video-test.php',
	'tests/php/play-video-markup-test.php',
	'tests/php/play-video-mf2-test.php',
);

check_case(
	'php -l passes for inc/play-video.php',
	static function () use ( $theme_dir ): bool {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $theme_dir . '/inc/play-video.php' ) . ' 2>&1', $output, $status );
		return 0 === $status;
	}
);

check_case(
	'new files contain no forbidden hex markers or legacy values',
	static function () use ( $theme_dir, $new_files ): bool {
		foreach ( $new_files as $file ) {
			$content = (string) file_get_contents( $theme_dir . '/' . $file );
			$hash_pattern = '/' . preg_quote( chr( 35 ), '/' ) . '[0-9a-fA-F]{3}([0-9a-fA-F]{3})?\b/';
			$legacy_pattern = '/(?:' . implode( '|', array( '5d' . '72a3', '9f' . 'b0d8', 'dd' . 'e3f0' ) ) . ')/i';
			if ( preg_match( $hash_pattern, $content ) || preg_match( $legacy_pattern, $content ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'stylesheet obeys paint prohibitions',
	static function () use ( $css ): bool {
		$bad = array(
			'/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'/\brgba?\s*\(/i',
			'/\bhsl\s*\(/i',
			'/gradient\s*\(/i',
			'/blur\s*\(/i',
			'/(?:^|[;{])\s*(?:-webkit-)?filter\s*:/im',
			'/backdrop-filter\s*:/i',
			'/@keyframes/i',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		if ( false === strpos( $css, '@media (prefers-reduced-motion: reduce)' ) || false === strpos( $css, '@media (forced-colors: active)' ) || false === strpos( $css, ':root:not([data-theme])' ) || false === strpos( $css, ':root[data-theme="dark"]' ) ) {
			return false;
		}
		preg_match_all( '/font-size\s*:\s*([^;]+);/i', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			if ( false === strpos( $size, '--cr-text-floor' ) ) {
				return false;
			}
		}
		preg_match_all( '/letter-spacing\s*:\s*([^;]+);/i', $css, $tracking );
		foreach ( $tracking[1] as $value ) {
			if ( ! preg_match( '/^var\(\s*--cr-tracking-[a-z-]+\s*\)$/', trim( $value ) ) ) {
				return false;
			}
		}
		preg_match_all( '/(?:^|[;{])\s*transform\s*:\s*([^;]+);/i', $css, $transforms );
		foreach ( $transforms[1] as $value ) {
			if ( ! in_array( trim( $value ), array( 'translateY(-1px)', 'none', 'rotate(var(--cr-rotate-neg))' ), true ) ) {
				return false;
			}
		}
		preg_match_all( '/box-shadow\s*:\s*([^;]+);/i', $css, $shadows );
		foreach ( $shadows[1] as $value ) {
			$value = css_norm( $value );
			if ( ! preg_match( '/^(var\(--cr-(?:shadow-hard|cabinet-shadow|cartridge-shadow)\)|none|var\(--cr-(?:cabinet-shadow|cartridge-shadow)\), 0 0 0 [0-9.]+px var\(--cr-ink\))$/', $value ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'every CSS token is defined',
	static function () use ( $css, $tokens ): bool {
		preg_match_all( '/var\(\s*(--cr-[a-z0-9-]+)/i', $css, $used );
		$known = css_var_defs( $tokens ) + css_var_defs( $css );
		foreach ( array_unique( $used[1] ) as $token ) {
			if ( ! isset( $known[ $token ] ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'paint table selectors and declarations exist',
	static function () use ( $rules ): bool {
		$checks = array(
			array( '.cr-play .pk-card.cr-cabinet', array( '--cr-cabinet-edge' => 'var(--cr-russian-violet)', '--cr-cabinet-shadow' => 'var(--cr-shadow-hard)', 'position' => 'relative', 'box-sizing' => 'border-box', 'width' => '100%', 'max-width' => '100%', 'margin' => '0', 'padding' => '0', 'border' => '2px solid var(--cr-cabinet-edge)', 'background' => 'var(--cr-prussian-blue)', 'box-shadow' => 'var(--cr-cabinet-shadow)', 'color' => 'var(--cr-printer-ivory)' ) ),
			array( '.cr-play .pk-card.cr-cabinet .pk-body', array( 'display' => 'grid' ) ),
			array( '.cr-play .pk-card.cr-cabinet .pk-caption', array( 'background' => 'var(--cr-russian-violet)', 'color' => 'var(--cr-printer-ivory)', 'border-bottom' => '2px solid var(--cr-sky-blue)' ) ),
			array( '.cr-play .pk-card.cr-cabinet .pk-title', array( 'margin' => '0', 'font-family' => 'var(--cr-font-accent)', 'font-weight' => 'var(--cr-weight-bold)', 'line-height' => 'var(--cr-leading-tight)', 'color' => 'var(--cr-printer-ivory)', 'text-wrap' => 'balance', 'overflow-wrap' => 'anywhere', 'font-size' => 'max( var(--cr-text-md), var(--cr-text-floor) )' ) ),
			array( '.cr-play .pk-card.cr-cabinet .pk-title a', array( 'color' => 'var(--cr-printer-ivory)', 'text-decoration' => 'none' ) ),
			array( '.cr-play .pk-card.cr-cabinet .pk-title a::after', array( 'content' => '""', 'position' => 'absolute', 'inset' => '0' ) ),
			array( '.cr-play .pk-card.cr-cabinet .cr-cabinet__screen', array( 'aspect-ratio' => '4 / 3', 'border' => '2px solid var(--cr-sky-blue)', 'background' => 'var(--cr-russian-violet)', 'display' => 'grid', 'place-items' => 'center', 'overflow' => 'hidden' ) ),
			array( '.cr-play .pk-card.cr-cabinet .cr-cabinet__image', array( 'width' => '100%', 'height' => '100%', 'max-width' => '100%', 'object-fit' => 'contain' ) ),
			array( '.cr-cabinet__glyph', array( 'display' => 'none', 'color' => 'var(--cr-sky-blue)', 'width' => '40%' ) ),
			array( '.cr-play .pk-card.cr-cabinet .cr-cabinet__score', array( 'background' => 'var(--cr-russian-violet)', 'color' => 'var(--cr-selective-yellow)', 'border' => '2px solid var(--cr-sky-blue)', 'font-family' => 'var(--cr-font-mono)', 'letter-spacing' => 'var(--cr-tracking-wide)', 'font-size' => 'max( var(--cr-text-sm), var(--cr-text-floor) )' ) ),
			array( '.cr-cabinet__panel', array( 'display' => 'flex', 'align-items' => 'center', 'background' => 'var(--cr-russian-violet-soft)' ) ),
			array( '.cr-cabinet__stick', array( 'border-radius' => 'var(--cr-radius-full)', 'background' => 'var(--cr-ut-orange)' ) ),
			array( '.cr-cabinet__button', array( 'border-radius' => 'var(--cr-radius-full)', 'background' => 'var(--cr-sky-blue)' ) ),
			array( '.cr-cabinet__button--alt', array( 'background' => 'var(--cr-ut-orange)' ) ),
			array( '.cr-cabinet__coins', array( 'margin-inline-start' => 'auto', 'background' => 'var(--cr-selective-yellow)', 'border-radius' => 'var(--cr-radius-xs)' ) ),
			array( '.cr-play .pk-card.cr-cabinet--locked', array( 'background' => 'var(--cr-russian-violet)' ) ),
			array( '.cr-play .pk-card.cr-cabinet:focus-within', array( 'outline' => '3px solid var(--cr-selective-yellow)', 'outline-offset' => '4px', 'box-shadow' => 'var(--cr-cabinet-shadow), 0 0 0 7px var(--cr-ink)' ) ),
			array( '.cr-play .pk-card.cr-cabinet:focus-within .pk-title a:focus', array( 'outline' => 'none' ) ),
			array( '.cr-play .pk-card.cr-cabinet:hover', array( 'transform' => 'translateY(-1px)' ) ),
			array( 'body.cr-video-single .cr-cabinet--single', array( '--cr-cabinet-edge' => 'var(--cr-russian-violet)', '--cr-cabinet-shadow' => 'var(--cr-shadow-hard)', 'display' => 'grid', 'grid-template-columns' => 'minmax(0, 1fr)' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__body', array( 'border' => '2px solid var(--cr-cabinet-edge)', 'background' => 'var(--cr-prussian-blue)', 'box-shadow' => 'var(--cr-cabinet-shadow)', 'color' => 'var(--cr-printer-ivory)' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__marquee', array( 'background' => 'var(--cr-russian-violet)', 'border-bottom' => '2px solid var(--cr-sky-blue)' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__title', array( 'margin' => '0', 'font-family' => 'var(--cr-font-accent)', 'font-weight' => 'var(--cr-weight-bold)', 'color' => 'var(--cr-printer-ivory)', 'text-wrap' => 'balance', 'overflow-wrap' => 'anywhere', 'font-size' => 'max( var(--cr-text-2xl), var(--cr-text-floor) )' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__label', array( 'background' => 'var(--cr-russian-violet)', 'color' => 'var(--cr-selective-yellow)', 'border' => '2px solid var(--cr-sky-blue)', 'font-family' => 'var(--cr-font-mono)', 'letter-spacing' => 'var(--cr-tracking-widest)', 'text-align' => 'center' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__record', array( 'background' => 'var(--cr-printer-ivory)', 'color' => 'var(--cr-russian-violet)', 'border' => '2px solid var(--cr-cabinet-edge)', 'box-shadow' => 'var(--cr-cabinet-shadow)' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__record dt', array( 'font-weight' => 'var(--cr-weight-bold)', 'font-size' => 'max( var(--cr-text-sm), var(--cr-text-floor) )' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__record dd', array( 'margin' => '0' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__link', array( 'color' => 'var(--cr-cerulean)', 'text-decoration' => 'underline' ) ),
			array( 'body.cr-video-single .cr-cabinet--single .cr-cabinet__review-title', array( 'font-family' => 'var(--cr-font-accent)', 'color' => 'var(--cr-russian-violet)' ) ),
			array( '.pk-card.cr-cartridge', array( '--cr-cartridge-edge' => 'var(--cr-russian-violet)', '--cr-cartridge-shadow' => 'var(--cr-shadow-hard)', 'position' => 'relative', 'box-sizing' => 'border-box', 'width' => '100%', 'max-width' => '100%', 'border' => '2px solid var(--cr-cartridge-edge)', 'background' => 'var(--cr-glaucous-dark)', 'box-shadow' => 'var(--cr-cartridge-shadow)', 'color' => 'var(--cr-printer-ivory)' ) ),
			array( '.pk-card.cr-cartridge .pk-kindlabel', array( 'color' => 'var(--cr-printer-ivory)', 'font-family' => 'var(--cr-font-mono)', 'letter-spacing' => 'var(--cr-tracking-eyebrow)', 'text-transform' => 'uppercase', 'font-size' => 'max( var(--cr-text-xs), var(--cr-text-floor) )' ) ),
			array( '.pk-card.cr-cartridge .pk-caption', array( 'background' => 'var(--cr-surface-elevated)', 'color' => 'var(--cr-ink)', 'border' => '2px solid var(--cr-cartridge-edge)' ) ),
			array( '.pk-card.cr-cartridge .pk-title', array( 'margin' => '0', 'font-family' => 'var(--cr-font-accent)', 'font-weight' => 'var(--cr-weight-bold)', 'color' => 'var(--cr-ink)', 'overflow-wrap' => 'anywhere', 'font-size' => 'max( var(--cr-text-md), var(--cr-text-floor) )' ) ),
			array( '.pk-card.cr-cartridge .pk-title a', array( 'color' => 'var(--cr-ink)', 'text-decoration' => 'none' ) ),
			array( '.pk-card.cr-cartridge .pk-title a::after', array( 'content' => '""', 'position' => 'absolute', 'inset' => '0' ) ),
			array( '.pk-card.cr-cartridge .pk-title::before', array( 'content' => 'none' ) ),
			array( '.pk-card.cr-cartridge .cr-cartridge__platform', array( 'margin' => '0', 'color' => 'var(--cr-ink-soft)', 'font-family' => 'var(--cr-font-mono)', 'font-size' => 'max( var(--cr-text-sm), var(--cr-text-floor) )' ) ),
			array( '.pk-card.cr-cartridge .cr-cartridge__label', array( 'aspect-ratio' => '4 / 3', 'border' => '2px solid var(--cr-sky-blue)', 'background' => 'var(--cr-russian-violet)', 'display' => 'grid', 'place-items' => 'center', 'overflow' => 'hidden' ) ),
			array( '.pk-card.cr-cartridge .cr-cartridge__image', array( 'width' => '100%', 'height' => '100%', 'max-width' => '100%', 'object-fit' => 'contain' ) ),
			array( '.pk-card.cr-cartridge::before', array( 'content' => '""', 'position' => 'absolute', 'background' => 'var(--cr-tape)', 'transform' => 'rotate(var(--cr-rotate-neg))' ) ),
			array( '.pk-card.cr-cartridge:focus-within', array( 'outline' => '3px solid var(--cr-selective-yellow)', 'outline-offset' => '4px', 'box-shadow' => 'var(--cr-cartridge-shadow), 0 0 0 7px var(--cr-ink)' ) ),
		);
		foreach ( $checks as $check ) {
			if ( ! has_decls( $rules, $check[0], $check[1] ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'archive panel owns its grid area',
	static function () use ( $rules ): bool {
		return has_decls( $rules, '.cr-play .pk-card.cr-cabinet .cr-cabinet__panel', array( 'grid-area' => 'panel' ) );
	}
);

check_case(
	'hover lift requires motion preference',
	static function () use ( $css ): bool {
		return (bool) preg_match( '/@media\s*\(\s*hover\s*:\s*hover\s*\)\s*and\s*\(\s*prefers-reduced-motion\s*:\s*no-preference\s*\)\s*\{[\s\S]*?\.cr-play\s+\.pk-card\.cr-cabinet:hover\s*\{[\s\S]*?transform\s*:\s*translateY\(-1px\)\s*;/i', $css );
	}
);

check_case(
	'contrast pairs pass',
	static function () use ( $tokens ): bool {
		$defs = css_var_defs( $tokens );
		$pairs = array(
			array( '--cr-printer-ivory', '--cr-prussian-blue', 4.5 ),
			array( '--cr-printer-ivory', '--cr-russian-violet', 4.5 ),
			array( '--cr-selective-yellow', '--cr-russian-violet', 4.5 ),
			array( '--cr-russian-violet', '--cr-printer-ivory', 4.5 ),
			array( '--cr-cerulean', '--cr-printer-ivory', 4.5 ),
			array( '--cr-printer-ivory', '--cr-glaucous-dark', 4.5 ),
			array( '--cr-sky-blue', '--cr-russian-violet', 3.0 ),
			array( '--cr-periwinkle', '--cr-photocopier-blk', 3.0 ),
			array( '--cr-selective-yellow', '--cr-photocopier-blk', 3.0 ),
		);
		foreach ( $pairs as $pair ) {
			if ( contrast_ratio( resolve_hex( $pair[0], $defs ), resolve_hex( $pair[1], $defs ) ) < $pair[2] ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'printed classes are styled',
	static function () use ( $php, $css ): bool {
		preg_match_all( '/cr-(?:cabinet|cartridge)__[a-z0-9]+(?:-[a-z0-9]+)*/', $php, $classes );
		foreach ( array_unique( $classes[0] ) as $class ) {
			if ( 'cr-cabinet__entry' === $class ) {
				continue;
			}
			if ( false === strpos( $css, '.' . $class ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'translations use courtneyr-child',
	static function () use ( $php ): bool {
		preg_match_all( '/\b(?:__|esc_html__|esc_attr__)\s*\((.*?)\)/s', $php, $calls );
		foreach ( $calls[1] as $args ) {
			if ( false === strpos( $args, "'courtneyr-child'" ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'every function and const has a docblock',
	static function () use ( $php ): bool {
		preg_match_all( '/(?:^|\n)(function\s+[a-z0-9_]+\s*\(|const\s+[A-Z0-9_]+\s*=)/', $php, $matches, PREG_OFFSET_CAPTURE );
		foreach ( $matches[1] as $match ) {
			$before = substr( $php, 0, (int) $match[1] );
			if ( ! preg_match( '/\/\*\*[\s\S]*?\*\/\s*$/', $before ) ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : '11 passed', "\n";
exit( count( $failures ) ? 1 : 0 );
