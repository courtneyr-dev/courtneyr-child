<?php
/**
 * `php tests/php/play-board-markup-test.php`: checks static board-game adapter files.
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

/** Extract CSS rule selectors and bodies. */
function css_rules( string $css ): array {
	$clean = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	preg_match_all( '/([^{}@][^{}]*)\{([^{}]*)\}/s', $clean, $matches, PREG_SET_ORDER );
	$rules = array();
	foreach ( $matches as $match ) {
		$rules[] = array( trim( $match[1] ), trim( $match[2] ) );
	}
	return $rules;
}

/** Map custom property names to raw values. */
function token_values( string $css ): array {
	preg_match_all( '/(--cr-[a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER );
	$tokens = array();
	foreach ( $matches as $match ) {
		$tokens[ $match[1] ] = trim( $match[2] );
	}
	return $tokens;
}

/** Resolve a token value to a hex color through var references. */
function resolve_color( string $token, array $tokens, array $seen = array() ): string {
	if ( isset( $seen[ $token ] ) || ! isset( $tokens[ $token ] ) ) {
		return '';
	}
	$value = $tokens[ $token ];
	if ( preg_match( '/#[0-9a-fA-F]{6}\b/', $value, $match ) ) {
		return strtolower( $match[0] );
	}
	if ( preg_match( '/var\(\s*(--cr-[a-z0-9-]+)\s*\)/', $value, $match ) ) {
		$seen[ $token ] = true;
		return resolve_color( $match[1], $tokens, $seen );
	}
	return '';
}

/** Relative luminance for a six-digit hex color. */
function luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	$parts = array(
		hexdec( substr( $hex, 0, 2 ) ) / 255,
		hexdec( substr( $hex, 2, 2 ) ) / 255,
		hexdec( substr( $hex, 4, 2 ) ) / 255,
	);
	$linear = array_map(
		static function ( float $channel ): float {
			return $channel <= 0.03928 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
		},
		$parts
	);
	return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

/** WCAG contrast ratio. */
function contrast( string $a, string $b ): float {
	$one = luminance( $a );
	$two = luminance( $b );
	$light = max( $one, $two );
	$dark  = min( $one, $two );
	return ( $light + 0.05 ) / ( $dark + 0.05 );
}

/** Extract the PAPERS constant from the PHP file. */
function paper_constant( string $php ): array {
	if ( ! preg_match( '/const\s+PAPERS\s*=\s*array\((.*?)\);/s', $php, $match ) ) {
		return array();
	}
	preg_match_all( "/'([^']+)'/", $match[1], $papers );
	return $papers[1];
}

check_case(
	'stylesheet uses allowed paint, tokens and type values',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		$tokens = (string) file_get_contents( $theme_dir . '/assets/css/tokens.css' );
		$bad = array(
			'/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'/\brgba?\s*\(/i',
			'/\bhsl\s*\(/i',
			'/gradient\s*\(/i',
			'/blur\s*\(/i',
			'/(?:^|[;{])\s*filter\s*:/im',
			'/text-shadow\s*:/i',
			'/backdrop-filter\s*:/i',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}

		preg_match_all( '/font-size\s*:\s*([^;]+);/i', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			$size = trim( $size );
			if ( ! preg_match( '/^(?:var\(--cr-text-(?:xs|sm|base|md|lg|xl|2xl|3xl|4xl|5xl)\)|max\(\s*var\(--cr-text-floor\)\s*,)/', $size ) ) {
				return false;
			}
		}

		foreach ( array( 'font-family', 'font-weight', 'line-height', 'letter-spacing' ) as $property ) {
			preg_match_all( '/' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+);/i', $css, $values );
			foreach ( $values[1] as $value ) {
				if ( ! preg_match( '/^var\(--cr-/', trim( $value ) ) ) {
					return false;
				}
			}
		}

		preg_match_all( '/var\(\s*(--cr-[a-z0-9-]+)/i', $css, $used );
		preg_match_all( '/(--cr-[a-z0-9-]+)\s*:/i', $tokens, $defined );
		$known = array_fill_keys( $defined[1], true );
		foreach ( array( '--cr-paper', '--cr-paper-ink', '--cr-paper-side', '--cr-board-side' ) as $custom ) {
			$known[ $custom ] = true;
		}
		foreach ( array_unique( $used[1] ) as $token ) {
			if ( ! isset( $known[ $token ] ) ) {
				return false;
			}
		}

		preg_match_all( '/(?:^|[;{])\s*transform\s*:\s*([^;]+);/im', $css, $transforms );
		foreach ( $transforms[1] as $value ) {
			$value = trim( $value );
			if ( 'none' !== $value && ! preg_match( '/^rotate\(var\(--cr-rotate-[a-z0-9-]+\)\)$/', $value ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'paper rules and slip color pairs meet contrast',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		$tokens = token_values( (string) file_get_contents( $theme_dir . '/assets/css/tokens.css' ) );
		preg_match_all( '/\.cr-paper--([a-z0-9-]+)\s*\{([^}]+)\}/', $css, $matches, PREG_SET_ORDER );
		$seen = array();
		foreach ( $matches as $match ) {
			$name = $match[1];
			$body = $match[2];
			if ( 'glaucous' === $name || isset( $seen[ $name ] ) ) {
				return false;
			}
			$seen[ $name ] = true;
			if ( ! preg_match( '/--cr-paper:\s*var\((--cr-[a-z0-9-]+)\)/', $body, $paper ) || ! preg_match( '/--cr-paper-ink:\s*var\((--cr-[a-z0-9-]+)\)/', $body, $ink ) ) {
				return false;
			}
			$paper_hex = resolve_color( $paper[1], $tokens );
			$ink_hex = resolve_color( $ink[1], $tokens );
			if ( '' === $paper_hex || '' === $ink_hex || contrast( $paper_hex, $ink_hex ) < 4.5 ) {
				return false;
			}
		}
		$ivory = resolve_color( '--cr-printer-ivory', $tokens );
		$cerulean = resolve_color( '--cr-cerulean', $tokens );
		$violet = resolve_color( '--cr-russian-violet', $tokens );
		return 9 === count( $seen )
			&& contrast( $cerulean, $ivory ) >= 4.5
			&& contrast( $violet, $ivory ) >= 4.5;
	}
);

check_case(
	'dark-mode object colors stay on raw palette tokens',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		if ( false !== strpos( $css, 'prefers-color-scheme' ) || false !== strpos( $css, 'data-theme' ) ) {
			return false;
		}
		foreach ( css_rules( $css ) as $rule ) {
			$selector = $rule[0];
			$body = $rule[1];
			$is_object = false !== strpos( $selector, '.cr-spine' )
				|| false !== strpos( $selector, '.cr-scorepad' )
				|| false !== strpos( $selector, '.pk-card--tabletop' )
				|| false !== strpos( $selector, '.pkiw-staff-picks__box' );
			if ( ! $is_object ) {
				continue;
			}
			preg_match_all( '/(?:^|;)\s*(color|background|background-color)\s*:\s*([^;]+);/i', $body, $values, PREG_SET_ORDER );
			foreach ( $values as $value ) {
				if ( preg_match( '/var\(--cr-(?:ink|ink-inverse|ink-soft|surface[a-z-]*)\)/', $value[2] ) ) {
					return false;
				}
			}
			$is_inner = 1 === preg_match( '/(?:\.pk-box|\.pk-facts|\.pk-scorepad|\.pk-links|\.pkiw-staff-picks__box|\.cr-scorepad__thumb|\.cr-scorepad[^,{]*(?:::before|::after))/', $selector );
			if ( ! $is_inner ) {
				continue;
			}
			preg_match_all( '/(?:^|;)\s*((?:-webkit-)?(?:border[a-z-]*|outline[a-z-]*|background-color|mask-color))\s*:\s*([^;]+);/i', $body, $inner_values, PREG_SET_ORDER );
			foreach ( $inner_values as $value ) {
				if ( preg_match( '/var\(--cr-(?:ink|ink-inverse|ink-soft|surface[a-z-]*|rule|border[a-z-]*|focus-halo)\)/', $value[2] ) ) {
					return false;
				}
			}
		}
		return true;
	}
);

check_case(
	'layout hooks and selector constraints are present',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		$slip_root = '';
		foreach ( css_rules( $css ) as $rule ) {
			if ( '.cr-scorepad.pk-card.k-play' === $rule[0] ) {
				$slip_root = $rule[1];
			}
		}
		return false !== strpos( $css, '@media (min-width: 40rem)' )
			&& false !== strpos( $css, '@media (min-width: 64rem)' )
			&& false !== strpos( $css, '@media (forced-colors: active)' )
			&& false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' )
			&& 0 === preg_match( '/(?:^|[;{])\s*order\s*:/im', $css )
			&& false === strpos( $css, '.cr-stream-page' )
			&& '' !== $slip_root
			&& 0 === preg_match( '/transform\s*:/i', $slip_root );
	}
);

check_case(
	'scorepad thumb rule beats child grid placement',
	static function () use ( $theme_dir ): bool {
		$rules = css_rules( (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' ) );
		$thumb_index = null;
		foreach ( $rules as $index => $rule ) {
			if ( '.cr-scorepad.pk-card.k-play > .cr-scorepad__thumb' === $rule[0] ) {
				$thumb_index = $index;
				if ( 1 !== preg_match( '/(?:^|;)\s*grid-column\s*:\s*2\s*(?:;|$)/i', $rule[1] ) ) {
					return false;
				}
				break;
			}
		}
		if ( null === $thumb_index ) {
			return false;
		}
		foreach ( array_slice( $rules, $thumb_index + 1 ) as $rule ) {
			if ( '.cr-scorepad.pk-card.k-play > *' === $rule[0] && 1 === preg_match( '/(?:^|;)\s*grid-column\s*:/i', $rule[1] ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'lane files contain no hex notation and PHP papers match CSS',
	static function () use ( $theme_dir ): bool {
		$files = array(
			'inc/play-board.php',
			'assets/css/cr-play-board.css',
			'tests/php/play-board-test.php',
			'tests/php/play-board-markup-test.php',
		);
		foreach ( $files as $file ) {
			$content = (string) file_get_contents( $theme_dir . '/' . $file );
			if ( preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $content ) ) {
				return false;
			}
		}
		$php = (string) file_get_contents( $theme_dir . '/inc/play-board.php' );
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		preg_match_all( '/\.cr-paper--([a-z0-9-]+)\s*\{/', $css, $matches );
		$papers = paper_constant( $php );
		sort( $papers );
		$suffixes = $matches[1];
		sort( $suffixes );
		return $papers === $suffixes;
	}
);

check_case(
	'hook names match the frozen adapter contract',
	static function () use ( $theme_dir ): bool {
		$php = (string) file_get_contents( $theme_dir . '/inc/play-board.php' );
		foreach ( array( 'courtneyr_child_play_item', 'courtneyr_child_play_stream_card', 'render_block_post-kinds-indieweb/staff-picks', 'render_block', 'enqueue_block_assets' ) as $hook ) {
			if ( false === strpos( $php, "'" . $hook . "'" ) ) {
				return false;
			}
		}
		return false === strpos( $php, 'render_block_post-kinds-indieweb/stream-card' );
	}
);

check_case(
	'board adapter uses only fact APIs and no tape-label block style',
	static function () use ( $theme_dir ): bool {
		$php = (string) file_get_contents( $theme_dir . '/inc/play-board.php' );
		foreach ( array( 'get_post_meta', 'get_post_field', 'get_post_custom', 'get_metadata', 'get_post_metadata', 'parse_blocks', 'is-style-cr-tape-label' ) as $forbidden ) {
			if ( false !== strpos( $php, $forbidden ) ) {
				return false;
			}
		}
		return 0 === preg_match( '/\b(?:metadata_exists|update_post_meta|delete_post_meta|add_post_meta)\b/', $php );
	}
);

check_case(
	'board archive and Staff Picks headings use tape pseudo-elements',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play-board.css' );
		$targets = array(
			'.cr-play .pkiw-staff-picks__heading',
			'.cr-play .pkiw-group[data-pkiw-group="board"] > .pkiw-group__heading',
		);
		foreach ( $targets as $target ) {
			$has_base = false;
			$has_tape = false;
			foreach ( css_rules( $css ) as $rule ) {
				$selector = $rule[0];
				$body = $rule[1];
				if ( false !== strpos( $selector, $target ) && false === strpos( $selector, '::before' ) ) {
					$has_base = true;
				}
				if ( false !== strpos( $selector, $target . '::before' ) && false !== strpos( $body, 'background: var(--cr-tape)' ) ) {
					$has_tape = true;
				}
			}
			if ( ! $has_base || ! $has_tape ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : '9 passed', "\n";
exit( count( $failures ) ? 1 : 0 );
