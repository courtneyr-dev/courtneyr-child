<?php
/**
 * `php tests/php/repost-css-test.php`: checks the repost stylesheet contract
 * (tokens only, the allowed filters, contrast on paper, the layout, the focus
 * ring, forced colors, reduced motion) without a browser.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir   = dirname( __DIR__, 2 );
$failures    = array();
$case_count  = 0;
$css_path    = $theme_dir . '/assets/css/cr-repost.css';
$tokens_path = $theme_dir . '/assets/css/tokens.css';

/** Run one named check. */
function repost_css_case( string $name, callable $test ): void {
	global $failures, $case_count;
	++$case_count;
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

/** The stylesheet without comments. */
function repost_css(): string {
	global $css_path;
	return is_file( $css_path ) ? (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $css_path ) ) : '';
}

/** Every declaration value for one property across the stylesheet. */
function repost_values( string $css, string $property ): array {
	preg_match_all( '/(?<![\w-])' . preg_quote( $property, '/' ) . '\s*:\s*([^;}]+)[;}]/i', $css, $matches );
	return array_map( 'trim', $matches[1] );
}

/** The body of the first rule whose selector list holds this exact selector. */
function repost_rule( string $css, string $selector ): string {
	$want = (string) preg_replace( '/\s+/', ' ', trim( $selector ) );
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		foreach ( $rules as $rule ) {
			foreach ( (array) preg_split( '/,(?![^(]*\))/', $rule[1] ) as $candidate ) {
				if ( (string) preg_replace( '/\s+/', ' ', trim( (string) $candidate ) ) === $want ) {
					return $rule[2];
				}
			}
		}
	}
	return '';
}

/** One declaration from a rule body. */
function repost_decl( string $rule, string $property ): string {
	return preg_match( '/(?<![\w-])' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+);/i', $rule, $m ) ? trim( $m[1] ) : '';
}

/** One declaration from any rule that holds this exact selector. */
function repost_find( string $css, string $selector, string $property ): string {
	$want = (string) preg_replace( '/\s+/', ' ', trim( $selector ) );
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		foreach ( $rules as $rule ) {
			foreach ( (array) preg_split( '/,(?![^(]*\))/', $rule[1] ) as $candidate ) {
				if ( (string) preg_replace( '/\s+/', ' ', trim( (string) $candidate ) ) === $want ) {
					$value = repost_decl( $rule[2], $property );
					if ( '' !== $value ) {
						return $value;
					}
				}
			}
		}
	}
	return '';
}

/** Every block opened by a needle, braces balanced. */
function repost_blocks( string $css, string $needle ): array {
	$blocks = array();
	$offset = 0;
	while ( false !== ( $start = strpos( $css, $needle, $offset ) ) ) {
		$open = strpos( $css, '{', $start );
		if ( false === $open ) {
			break;
		}
		$depth = 0;
		$end   = strlen( $css );
		for ( $i = $open; $i < strlen( $css ); ++$i ) {
			$depth += '{' === $css[ $i ] ? 1 : ( '}' === $css[ $i ] ? -1 : 0 );
			if ( 0 === $depth ) {
				$end = $i;
				break;
			}
		}
		$blocks[] = substr( $css, $open + 1, $end - $open - 1 );
		$offset   = $end + 1;
	}
	return $blocks;
}

/** Token map from the first :root block of tokens.css. */
function repost_root_tokens(): array {
	global $tokens_path;
	$tokens = (string) file_get_contents( $tokens_path );
	$roots  = repost_blocks( $tokens, ':root {' );
	$map    = array();
	if ( preg_match_all( '/(--[\w-]+)\s*:\s*([^;]+);/', $roots[0] ?? '', $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $decl ) {
			$map[ $decl[1] ] = trim( $decl[2] );
		}
	}
	return $map;
}

/** Resolve a token to a hex value through var() hops. */
function repost_hex( array $map, string $name ): string {
	for ( $i = 0; $i < 4; ++$i ) {
		$value = $map[ $name ] ?? '';
		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return $value;
		}
		if ( ! preg_match( '/^var\(\s*(--[\w-]+)\s*\)$/', $value, $next ) ) {
			return '';
		}
		$name = $next[1];
	}
	return '';
}

/** WCAG contrast of two hex colours. */
function repost_contrast( string $a, string $b ): float {
	$lum = static function ( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$sum = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c    = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$sum += $weight * ( $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4 );
		}
		return $sum;
	};
	$l1 = $lum( $a );
	$l2 = $lum( $b );
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

repost_css_case(
	'the stylesheet exists and holds no raw colour, fill ramp, clip or soft shadow',
	static function (): bool {
		$css = repost_css();
		if ( '' === $css ) {
			return false;
		}
		$bad = array(
			'/#[0-9a-fA-F]{3,8}\b/',
			'/\brgba?\s*\(/i',
			'/\bhsla?\s*\(/i',
			'/(?:linear|radial|conic)-gradient\s*\(/i',
			'/\bclip-path\s*:/i',
			'/\bbackdrop-filter\s*:/i',
			'/blur\s*\(/i',
			'/text-overflow\s*:\s*ellipsis/i',
			'/--cr-glaucous/',
			'/--cr-white\b/',
			'/--cr-black\b/',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		return true;
	}
);

repost_css_case(
	'the only filters are the hard shadow and the archive clipping grayscale',
	static function (): bool {
		$css = repost_css();
		foreach ( repost_values( $css, 'filter' ) as $value ) {
			if ( ! in_array( $value, array( 'none', 'grayscale(1)', 'drop-shadow(var(--cr-shadow-hard))' ), true ) ) {
				return false;
			}
		}
		$gray = repost_rule( $css, '.cr-repost-zine .cr-repost-clip__frame img' );
		if ( 'grayscale(1)' !== repost_decl( $gray, 'filter' ) ) {
			return false;
		}
		// Grayscale appears once outside forced colors, and nowhere on the single or the Stream.
		return 1 === preg_match_all( '/filter\s*:\s*grayscale\(1\)/', $css )
			&& 'none' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .cr-repost-clip--single .pk-embed img' ), 'filter' );
	}
);

repost_css_case(
	'rotations use the rotate tokens, shadows use the hard-shadow tokens, text keeps the floor',
	static function (): bool {
		$css = repost_css();
		foreach ( repost_values( $css, 'transform' ) as $value ) {
			if ( 'none' === $value || preg_match( '/^translate\(-?\d+px, -?\d+px\)$/', $value ) ) {
				continue;
			}
			if ( ! preg_match( '/^rotate\(var\(--cr-rotate-(?:1|2|neg|neg2)\)\)$/', $value ) ) {
				return false;
			}
		}
		foreach ( repost_values( $css, 'box-shadow' ) as $value ) {
			if ( 'none' === $value ) {
				continue;
			}
			if ( ! preg_match( '/^(?:var\(--cr-shadow-hard(?:-2)?\)(?:, )?)?(?:0 0 0 7px var\(--cr-focus-halo\))?$/', $value ) ) {
				return false;
			}
		}
		foreach ( repost_values( $css, 'font-size' ) as $value ) {
			if ( ! preg_match( '/^max\(var\(--cr-text-(?:xs|sm|md|xl)\), var\(--cr-text-floor\)\)$/', $value ) && 'var(--cr-text-2xl)' !== $value ) {
				return false;
			}
		}
		return true;
	}
);

repost_css_case(
	'text on paper is violet or cerulean and never the soft ink',
	static function (): bool {
		$css = repost_css();
		if ( false !== strpos( $css, '--cr-ink-soft' ) ) {
			return false;
		}
		foreach ( repost_values( $css, 'color' ) as $value ) {
			if ( ! in_array( $value, array( 'var(--cr-russian-violet)', 'var(--cr-cerulean)', 'inherit', 'CanvasText' ), true ) ) {
				return false;
			}
		}
		return true;
	}
);

repost_css_case(
	'paper and ink hold 4.5:1 and keep their fill in dark mode',
	static function (): bool {
		global $tokens_path;
		$map   = repost_root_tokens();
		$paper = array( '--cr-light-gray', '--cr-printer-ivory', '--cr-periwinkle', '--cr-sky-blue', '--cr-light-orange' );
		foreach ( $paper as $name ) {
			if ( repost_contrast( repost_hex( $map, '--cr-russian-violet' ), repost_hex( $map, $name ) ) < 4.5 ) {
				return false;
			}
		}
		if ( repost_contrast( repost_hex( $map, '--cr-cerulean' ), repost_hex( $map, '--cr-light-gray' ) ) < 4.5 ) {
			return false;
		}
		$dark = implode( "\n", array_merge( repost_blocks( (string) file_get_contents( $tokens_path ), '@media (prefers-color-scheme: dark)' ), repost_blocks( (string) file_get_contents( $tokens_path ), ':root[data-theme="dark"]' ) ) );
		foreach ( array_merge( $paper, array( '--cr-russian-violet', '--cr-cerulean' ) ) as $name ) {
			if ( preg_match( '/(?<![\w-])' . preg_quote( $name, '/' ) . '\s*:/', $dark ) ) {
				return false;
			}
		}
		return true;
	}
);

repost_css_case(
	'the archive item resets undo the blog-grid separators, hover lift and transition',
	static function (): bool {
		$css  = repost_css();
		$item = repost_rule( $css, '.cr-archive .cr-repost-zine .wp-block-post' );
		return 'none' === repost_decl( $item, 'transition' )
			&& '0' === repost_decl( $item, 'padding' )
			&& 'drop-shadow(var(--cr-shadow-hard))' === repost_decl( $item, 'filter' )
			&& 'none' === repost_decl( repost_rule( $css, '.cr-archive .cr-repost-zine .wp-block-post:hover' ), 'transform' )
			&& '""' !== repost_decl( repost_rule( $css, '.cr-archive .cr-repost-zine .wp-block-post:not(:last-child)::after' ), 'content' )
			&& 'none' === repost_decl( repost_rule( $css, '.cr-archive .cr-repost-zine .wp-block-post:not(:last-child)::after' ), 'content' );
	}
);

repost_css_case(
	'the sheets run 1, 2 and 4 across with a fold from 40rem and a fixed 1.91 to 1 frame',
	static function (): bool {
		$css = repost_css();
		return false !== strpos( $css, '@media (min-width: 40rem)' )
			&& false !== strpos( $css, '@media (min-width: 64rem)' )
			&& 'minmax(0, 1fr)' === repost_decl( repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group__items' ), 'grid-template-columns' )
			&& 1 === preg_match( '/@media \(min-width: 40rem\)\s*\{[^@]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\)/s', $css )
			&& 1 === preg_match( '/@media \(min-width: 64rem\)\s*\{[^@]*grid-template-columns:\s*repeat\(4, minmax\(0, 1fr\)\)/s', $css )
			&& 1 === preg_match( '/\.pkiw-group__items::before\s*\{[^}]*inset-inline-start:\s*50%/s', $css )
			&& '1.91 / 1' === repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip__frame' ), 'aspect-ratio' )
			&& 'cover' === repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip__frame img' ), 'object-fit' )
			&& 'none' === repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip__frame.cr-cover--failed' ), 'display' );
	}
);

repost_css_case(
	'month headings are upright violet slab text on a tilted strip in the section order',
	static function (): bool {
		$css     = repost_css();
		$heading = repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group__heading' );
		return 'var(--cr-russian-violet)' === repost_decl( $heading, 'color' )
			&& 'var(--cr-font-accent)' === repost_decl( $heading, 'font-family' )
			&& '' === repost_decl( $heading, 'transform' )
			&& 'var(--cr-spread-strip)' === repost_decl( repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group__heading::before' ), 'background' )
			&& 'var(--cr-periwinkle)' === repost_decl( repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group' ), '--cr-spread-strip' )
			&& 'var(--cr-sky-blue)' === repost_decl( repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group:nth-child(3n+2)' ), '--cr-spread-strip' )
			&& 'var(--cr-light-orange)' === repost_decl( repost_rule( $css, '.cr-repost-zine__spreads .pkiw-group:nth-child(3n+3)' ), '--cr-spread-strip' );
	}
);

repost_css_case(
	'the clipping wraps its title, covers the paper with the link and rings the whole object',
	static function (): bool {
		$css   = repost_css();
		$title = repost_rule( $css, '.cr-repost-zine .cr-repost-clip__title' );
		$ring  = repost_rule( $css, '.cr-repost-zine .cr-repost-clip:has(a:focus-visible)' );
		return 'anywhere' === repost_decl( $title, 'overflow-wrap' )
			&& '' === repost_decl( $title, '-webkit-line-clamp' )
			&& 0 === strpos( repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip__title a::after' ), 'inset' ), '0' )
			&& '3px solid var(--cr-focus-ring)' === repost_decl( $ring, 'outline' )
			&& '0 0 0 7px var(--cr-focus-halo)' === repost_decl( $ring, 'box-shadow' )
			&& 'var(--cr-tape)' === repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip::before' ), 'background' )
			&& 'none' === repost_decl( repost_rule( $css, '.cr-repost-zine .cr-repost-clip::before' ), 'pointer-events' );
	}
);

repost_css_case(
	'a focused title link draws no ring or halo of its own, archive item and protected card alike',
	static function (): bool {
		$css = repost_css();
		foreach ( array( '.cr-repost-zine .cr-repost-clip__title a:focus-visible', '.cr-repost-clip--protected .pk-title a:focus-visible' ) as $selector ) {
			$rule = repost_rule( $css, $selector );
			if ( '0' !== repost_decl( $rule, 'outline' ) || 'none' !== repost_decl( $rule, 'box-shadow' ) ) {
				return false;
			}
		}
		return true;
	}
);

repost_css_case(
	'the single is a two-column clipping from 48rem, with unfiltered art and mono, slab and Barlow voices',
	static function (): bool {
		$css = repost_css();
		return 1 === preg_match( '/@media \(min-width: 48rem\)\s*\{[^@]*repeat\(2, minmax\(0, 1fr\)\)/s', $css )
			&& 'var(--cr-font-mono)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .cr-repost-clip--single .pk-sub.cr-repost-clip__from' ), 'font-family' )
			&& 'var(--cr-font-accent)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .cr-repost-clip--single .pk-title' ), 'font-family' )
			&& 'var(--cr-font-body)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .cr-repost-clip--single .pk-note' ), 'font-family' )
			&& 'var(--cr-font-body)' === repost_find( $css, 'body.single-post.kind-repost .single-post__content', 'font-family' )
			&& 'var(--cr-font-mono)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__date' ), 'font-family' )
			&& 'none' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .cr-repost-clip--single .pk-meta[hidden]' ), 'display' )
			&& 'var(--cr-tape)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .pk-card.k-repost.cr-repost-clip--single::before' ), 'background' )
			&& 'var(--cr-tape)' === repost_decl( repost_rule( $css, 'body.single-post.kind-repost .single-post__content .pk-card.k-repost.cr-repost-clip--single .pk-body::before' ), 'background' );
	}
);

repost_css_case(
	'the Stream clipping is 80% wide, tilted by a token, taped flat and clamped to three lines',
	static function (): bool {
		$css  = repost_css();
		$card = repost_rule( $css, ':is(.cr-stream-page, .cr-home-stream) .pk-card.pk-card--stream.cr-repost-clip--stream' );
		$tape = repost_rule( $css, ':is(.cr-stream-page, .cr-home-stream) .pk-card.pk-card--stream.cr-repost-clip--stream::before' );
		$note = repost_rule( $css, ':is(.cr-stream-page, .cr-home-stream) .pk-card.pk-card--stream.cr-repost-clip--stream .pk-note' );
		return '80%' === repost_decl( $card, 'inline-size' )
			&& 'var(--cr-light-gray)' === repost_decl( $card, 'background-color' )
			&& 'var(--cr-shadow-hard)' === repost_decl( $card, 'box-shadow' )
			&& 'var(--cr-tape)' === repost_decl( $tape, 'background' )
			&& 'none' === repost_decl( $tape, 'box-shadow' )
			&& '3' === repost_decl( $note, '-webkit-line-clamp' )
			&& 4 === preg_match_all( '/li\.wp-block-post \.pk-card\.pk-card--stream\.cr-repost-clip--stream\.cr-repost-clip--tilt-[0-3]\s*\{[^}]*rotate\(var\(--cr-rotate-/', $css )
			&& 1 === preg_match( '/(?:^|\s):is\(\.cr-stream-page, \.cr-home-stream\)/m', $css );
	}
);

repost_css_case(
	'reduced motion keeps the hover lift, and forced colors drop the paper, tape and filters',
	static function (): bool {
		$css     = repost_css();
		$no_pref = implode( "\n", repost_blocks( $css, '@media (prefers-reduced-motion: no-preference)' ) );
		$forced  = implode( "\n", repost_blocks( $css, '@media (forced-colors: active)' ) );
		return false !== strpos( $no_pref, 'translate(-2px, -2px)' )
			&& false !== strpos( $no_pref, 'transition: transform' )
			&& false === strpos( preg_replace( '/@media \(prefers-reduced-motion: no-preference\)\s*\{.*?\n\}\n/s', '', $css ), 'translate(-2px, -2px)' )
			&& false !== strpos( $forced, '2px solid CanvasText' )
			&& false !== strpos( $forced, 'display: none' )
			&& false !== strpos( $forced, 'filter: none' )
			&& false !== strpos( $forced, 'outline-color: Highlight' );
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : $case_count . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
