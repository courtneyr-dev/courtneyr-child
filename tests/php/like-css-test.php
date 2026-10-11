<?php
/**
 * `php tests/php/like-css-test.php`: checks the like stylesheet contract.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$css_path   = $theme_dir . '/assets/css/cr-like.css';
$tokens_path = $theme_dir . '/assets/css/tokens.css';
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
	global $css_path, $theme_dir;
	$sticker_path = $theme_dir . '/assets/css/cr-sticker.css';
	$sticker      = is_file( $sticker_path ) ? "\n" . (string) file_get_contents( $sticker_path ) : '';
	return ( is_file( $css_path ) ? (string) file_get_contents( $css_path ) : '' ) . $sticker;
}

/** Read the token stylesheet when it exists. */
function token_css(): string {
	global $tokens_path;
	return is_file( $tokens_path ) ? (string) file_get_contents( $tokens_path ) : '';
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

/** Extract declarations for one exact selector. */
function exact_rule( string $css, string $selector ): string {
	$pattern = '/(?:^|})\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/s';
	return preg_match( $pattern, $css, $rule ) ? $rule[1] : '';
}

/** Extract every block opened by a CSS selector or at-rule. */
function css_blocks( string $css, string $needle ): array {
	$blocks = array();
	$offset = 0;
	$length = strlen( $css );
	while ( false !== ( $start = strpos( $css, $needle, $offset ) ) ) {
		$open = strpos( $css, '{', $start );
		if ( false === $open ) {
			$offset = $start + strlen( $needle );
			continue;
		}
		$depth = 0;
		for ( $i = $open; $i < $length; ++$i ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					$blocks[] = substr( $css, $open + 1, $i - $open - 1 );
					$offset   = $i + 1;
					continue 2;
				}
			}
		}
		$offset = $open + 1;
	}
	return $blocks;
}

/** Whether a CSS fragment assigns a custom property. */
function assigns_token( string $css, string $name ): bool {
	return (bool) preg_match( '/(?<![\w-])' . preg_quote( $name, '/' ) . '\s*:/', $css );
}

/** Whether dark-mode branches in the like stylesheet assign a custom property. */
function like_dark_assigns_token( string $css, string $name ): bool {
	if ( assigns_token( implode( "\n", css_blocks( $css, '@media (prefers-color-scheme: dark)' ) ), $name ) ) {
		return true;
	}
	return assigns_token( implode( "\n", css_blocks( $css, ':root[data-theme="dark"]' ) ), $name );
}

/** Parse custom property declarations from one rule body. */
function token_map( string $rule ): array {
	$map = array();
	if ( preg_match_all( '/(?<![\w-])(--[\w-]+)\s*:\s*([^;]+);/', $rule, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$map[ $match[1] ] = trim( $match[2] );
		}
	}
	return $map;
}

/** Resolve a custom property to a literal hex value through bounded var() hops. */
function resolve_token_hex( array $tokens, string $name ): string {
	for ( $i = 0; $i < 4; ++$i ) {
		$value = trim( (string) ( $tokens[ $name ] ?? '' ) );
		if ( preg_match( '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value ) ) {
			return $value;
		}
		if ( ! preg_match( '/^var\(\s*(--[\w-]+)\s*\)$/', $value, $next ) ) {
			return '';
		}
		$name = $next[1];
	}
	return '';
}

/** Return a WCAG relative luminance value for a literal hex colour. */
function relative_luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$channels = array(
		hexdec( substr( $hex, 0, 2 ) ) / 255,
		hexdec( substr( $hex, 2, 2 ) ) / 255,
		hexdec( substr( $hex, 4, 2 ) ) / 255,
	);
	foreach ( $channels as $index => $channel ) {
		$channels[ $index ] = $channel <= 0.03928 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
	}
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/** Return the contrast ratio between two literal hex colours. */
function contrast_ratio( string $a, string $b ): float {
	$l1 = relative_luminance( $a );
	$l2 = relative_luminance( $b );
	if ( $l2 > $l1 ) {
		$tmp = $l1;
		$l1  = $l2;
		$l2  = $tmp;
	}
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}

/** Whether a selector rule declares all named property values. */
function rule_has_declarations( string $css, string $selector, array $expected ): bool {
	$rule = exact_rule( $css, $selector );
	if ( '' === $rule ) {
		return false;
	}
	foreach ( $expected as $property => $value ) {
		if ( $value !== declaration_value( $css, $selector, (string) $property ) ) {
			return false;
		}
	}
	return true;
}

/** Whether any selector starts with the bare Stream sticker class. */
function has_bare_stream_selector( string $css ): bool {
	preg_match_all( '/(^|})\s*([^{@}][^{]*)\{/m', $css, $matches );
	foreach ( $matches[2] as $selector_list ) {
		foreach ( explode( ',', $selector_list ) as $selector ) {
			if ( 0 === strpos( trim( $selector ), '.cr-like-sticker--stream' ) ) {
				return true;
			}
		}
	}
	return false;
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
			'/(?:^|[;{])\s*filter\s*:(?!\s*drop-shadow\(var\(--cr-shadow-hard)/im',
			'/\bbackdrop-filter\s*:/i',
			'/text-overflow\s*:\s*ellipsis/i',
			'/--cr-black\b/',
			'/(?<!border: solid 0\.4rem var\()--cr-white\b/',
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
	'sticker paint has stacking contexts and paper defaults',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		return false === strpos( $css, 'var(--cr-white,' )
			&& 'var(--cr-light-gray)' === declaration_value( $css, ':where(.cr-like-sticker, .cr-sticker)', '--cr-paper' )
			&& 'var(--cr-russian-violet)' === declaration_value( $css, ':where(.cr-like-sticker, .cr-sticker)', '--cr-paper-ink' )
			&& 'isolate' === declaration_value( $css, '.cr-like article.pk-card.cr-like-sticker', 'isolation' )
			&& 'isolate' === declaration_value( $css, 'article.pk-card.cr-like-sticker--stream', 'isolation' );
	}
);

check_case(
	'card shell resets and Stream selectors outrank global card styles',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		$archive = rule_has_declarations(
			$css,
			'.cr-like article.pk-card.cr-like-sticker',
			array(
				'display'               => 'block',
				'grid-template-columns' => 'none',
				'gap'                   => '0',
				'max-width'             => '100%',
				'border'                => '0',
				'border-radius'         => '0',
				'background'            => 'transparent',
				'box-shadow'            => 'none',
			)
		);
		$stream = rule_has_declarations(
			$css,
			'article.pk-card.cr-like-sticker--stream',
			array(
				'display'               => 'block',
				'grid-template-columns' => 'none',
				'gap'                   => '0',
				'border'                => '0',
				'background'            => 'transparent',
				'box-shadow'            => 'none',
			)
		);
		$before = exact_rule( $css, 'article.pk-card.cr-like-sticker--stream::before' );
		return $archive
			&& $stream
			&& false === has_bare_stream_selector( $css )
			&& false !== strpos( $before, 'content: ""' )
			&& false !== strpos( $before, 'var(--cr-white)' );
	}
);

check_case(
	'badge and media placement rules keep stickers visible',
	static function (): bool {
		$css = like_css();
		if ( '' === $css ) {
			return false;
		}
		return '2rem' === declaration_value( $css, 'article.pk-card.cr-like-sticker--stream .pk-badge', 'width' )
			&& '2rem' === declaration_value( $css, 'article.pk-card.cr-like-sticker--stream .pk-badge', 'height' )
			&& '100%' === declaration_value( $css, 'article.pk-card.cr-like-sticker--stream .pk-badge svg', 'width' )
			&& '100%' === declaration_value( $css, 'article.pk-card.cr-like-sticker--stream .pk-badge svg', 'height' )
			&& 'var(--cr-space-sm)' === declaration_value( $css, '.cr-like article.pk-card.cr-like-sticker .pk-embed', 'margin-block-start' )
			&& 'absolute' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-badge', 'position' )
			&& 'calc(-1 * var(--cr-space-2xl))' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-badge', 'inset-block-start' )
			&& 'calc(-1 * var(--cr-space-xs))' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-badge', 'inset-inline-end' )
			&& '100%' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-badge svg', 'width' )
			&& '100%' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-badge svg', 'height' )
			&& 'var(--cr-space-xs)' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping .pk-body', 'padding-block-start' )
			&& 'block' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping', 'display' )
			&& 'none' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping', 'grid-template-columns' )
			&& '0' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping', 'gap' )
			&& 'var(--cr-space-lg)' === declaration_value( $css, 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping', 'margin-block-start' );
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

check_case(
	'clipping link colour holds on ivory in both schemes',
	static function (): bool {
		$css    = like_css();
		$tokens = token_css();
		if ( '' === $css || '' === $tokens ) {
			return false;
		}
		$selector = 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping a';
		$rule     = exact_rule( $css, $selector );
		$value    = '' === $rule ? '' : declaration_value( $css, $selector, 'color' );
		if ( ! preg_match( '/^var\(\s*(--[\w-]+)\s*\)$/', $value, $match ) ) {
			return false;
		}
		$link_name      = $match[1];
		$dark_media     = css_blocks( $tokens, '@media (prefers-color-scheme: dark)' );
		$dark_attribute = css_blocks( $tokens, ':root[data-theme="dark"]' );
		if ( empty( $dark_media ) || empty( $dark_attribute ) ) {
			return false;
		}
		$dark_media_css     = implode( "\n", $dark_media );
		$dark_attribute_css = implode( "\n", $dark_attribute );
		if ( assigns_token( $dark_media_css, $link_name ) || assigns_token( $dark_attribute_css, $link_name ) || like_dark_assigns_token( $css, $link_name ) ) {
			return false;
		}
		$roots       = css_blocks( $tokens, ':root' );
		$root        = token_map( $roots[0] ?? '' );
		$link_hex    = resolve_token_hex( $root, $link_name );
		$surface_hex = resolve_token_hex( $root, '--cr-printer-ivory' );
		if ( '' === $link_hex || '' === $surface_hex ) {
			return false;
		}
		if ( assigns_token( $dark_media_css, '--cr-printer-ivory' ) || assigns_token( $dark_attribute_css, '--cr-printer-ivory' ) ) {
			return false;
		}
		return contrast_ratio( $link_hex, $surface_hex ) >= 4.5;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
