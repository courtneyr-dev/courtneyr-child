<?php
/**
 * `php tests/php/w3-t0-css-test.php`: checks W3-T0 shared paint and filter
 * CSS contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir      = dirname( __DIR__, 2 );
$post_kinds_css = $theme_dir . '/assets/css/cr-post-kinds.css';
$home_css       = $theme_dir . '/assets/css/cr-home-sections.css';
$eat_drink_css  = $theme_dir . '/assets/css/cr-eat-drink.css';
$ticket_css     = $theme_dir . '/assets/css/cr-ticket.css';
$like_css       = $theme_dir . '/assets/css/cr-like.css';
$sticker_css    = $theme_dir . '/assets/css/cr-sticker.css';
$article_rules  = $theme_dir . '/tests/php/fixtures/w3-t0-article-rules.json';
$failures       = array();

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

/** Read a stylesheet when it exists. */
function css_file( string $path ): string {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

/** Count matching lines, grep -c style. */
function matching_line_count( string $css, string $pattern ): int {
	$count = 0;
	foreach ( preg_split( '/\R/', $css ) as $line ) {
		if ( preg_match( $pattern, $line ) ) {
			++$count;
		}
	}
	return $count;
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

/** Whether a CSS fragment contains a raw hex colour. */
function has_raw_hex_colour( string $css ): bool {
	return (bool) preg_match( '/#[0-9a-f]{3,8}\b/i', $css );
}

/** Whether a CSS fragment contains an RGB literal. */
function has_rgb_literal( string $css ): bool {
	return (bool) preg_match( '/\brgba?\s*\(/i', $css );
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

/** Whether every font-size declaration in a CSS fragment is allowed. */
function all_font_sizes_allowed( string $css ): bool {
	preg_match_all( '/font-size\s*:\s*([^;]+);/i', $css, $sizes );
	foreach ( $sizes[1] as $size ) {
		if ( ! font_size_allowed( $size ) ) {
			return false;
		}
	}
	return true;
}

/** Split a CSS list on a top-level delimiter. */
function split_top_level( string $text, string $delimiter ): array {
	$parts  = array();
	$start  = 0;
	$depth  = 0;
	$length = strlen( $text );
	for ( $i = 0; $i < $length; ++$i ) {
		$char = $text[ $i ];
		if ( '(' === $char || '[' === $char ) {
			++$depth;
		} elseif ( ')' === $char || ']' === $char ) {
			$depth = max( 0, $depth - 1 );
		} elseif ( $delimiter === $char && 0 === $depth ) {
			$parts[] = substr( $text, $start, $i - $start );
			$start   = $i + 1;
		}
	}
	$parts[] = substr( $text, $start );
	return $parts;
}

/** Find the closing brace for a block. */
function matching_brace( string $css, int $open ): int {
	$depth  = 0;
	$length = strlen( $css );
	for ( $i = $open; $i < $length; ++$i ) {
		if ( '{' === $css[ $i ] ) {
			++$depth;
		} elseif ( '}' === $css[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return $i;
			}
		}
	}
	return -1;
}

/** Normalize one selector. */
function normalize_selector( string $selector ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', $selector ) );
}

/** Normalize one declaration. */
function normalize_declaration( string $declaration ): string {
	$declaration = trim( preg_replace( '/\/\*.*?\*\//s', '', $declaration ) );
	if ( '' === $declaration || ! str_contains( $declaration, ':' ) ) {
		return '';
	}
	list( $property, $value ) = explode( ':', $declaration, 2 );
	$property = strtolower( trim( $property ) );
	$value    = trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	$value    = preg_replace( '/\s*([(),:\/])\s*/', '$1', $value );
	$value    = preg_replace( '/\s*!\s*important/i', '!important', $value );
	return $property . ':' . $value;
}

/** Normalize declarations from a rule body. */
function normalize_declarations( string $body ): array {
	$body  = preg_replace( '/\/\*.*?\*\//s', '', $body );
	$decls = array();
	foreach ( split_top_level( (string) $body, ';' ) as $piece ) {
		$decl = normalize_declaration( $piece );
		if ( '' !== $decl ) {
			$decls[] = $decl;
		}
	}
	sort( $decls );
	return $decls;
}

/** Parse CSS rules with their at-rule context. */
function parsed_css_rules( string $css, int $start = 0, ?int $end = null, array $contexts = array() ): array {
	$rules = array();
	$end   = $end ?? strlen( $css );
	$pos   = $start;
	while ( $pos < $end && false !== ( $open = strpos( $css, '{', $pos ) ) && $open < $end ) {
		$prelude_start = $pos;
		$previous      = max( strrpos( substr( $css, 0, $open ), '}' ) ?: 0, $pos );
		if ( $previous > $pos ) {
			$prelude_start = $previous + 1;
		}
		$prelude = trim( substr( $css, $prelude_start, $open - $prelude_start ) );
		$close   = matching_brace( $css, $open );
		if ( $close < 0 || $close > $end ) {
			break;
		}
		$body = substr( $css, $open + 1, $close - $open - 1 );
		if ( '' !== $prelude && '@' === $prelude[0] ) {
			$context = trim( (string) preg_replace( '/\s+/', ' ', $prelude ) );
			$rules   = array_merge( $rules, parsed_css_rules( $css, $open + 1, $close, array_merge( $contexts, array( $context ) ) ) );
		} elseif ( '' !== $prelude ) {
			$selectors = array_map( 'normalize_selector', split_top_level( $prelude, ',' ) );
			$rules[]   = array(
				'context'      => implode( ' > ', $contexts ),
				'selectors'    => $selectors,
				'declarations' => normalize_declarations( $body ),
			);
		}
		$pos = $close + 1;
	}
	return $rules;
}

/** Whether the parsed CSS contains a matching article baseline rule. */
function has_article_rule( array $rules, array $fixture ): bool {
	$expected = array_map( 'normalize_declaration', $fixture['declarations'] );
	sort( $expected );
	foreach ( $rules as $rule ) {
		if ( (string) $fixture['context'] !== $rule['context'] ) {
			continue;
		}
		if ( $expected !== $rule['declarations'] ) {
			continue;
		}
		foreach ( $rule['selectors'] as $selector ) {
			if ( str_contains( $selector, '.k-article' ) ) {
				return true;
			}
		}
	}
	return false;
}

/** Whether a stylesheet contains a selector fragment and declaration fragment in one rule. */
function rule_contains( string $css, string $selector_part, string $declaration_part ): bool {
	$pattern = '/' . preg_quote( $selector_part, '/' ) . '[^{]*\{[^}]*' . preg_quote( $declaration_part, '/' ) . '/s';
	return (bool) preg_match( $pattern, $css );
}

check_case(
	'cr-post-kinds.css has no photo-kind selectors',
	static function () use ( $post_kinds_css ): bool {
		return 0 === matching_line_count( css_file( $post_kinds_css ), '/kind-photo|k-photo/' );
	}
);

check_case(
	'cr-home-sections.css removes k-photo while keeping gallery cards',
	static function () use ( $home_css ): bool {
		$css = css_file( $home_css );
		return 0 === matching_line_count( $css, '/k-photo/' )
			&& matching_line_count( $css, '/pk-card--gallery/' ) >= 1;
	}
);

check_case(
	'favorite, repost and acquisition token rows appear exactly once',
	static function () use ( $post_kinds_css ): bool {
		$css = css_file( $post_kinds_css );
		return 1 === matching_line_count( $css, '/k-favorite/' )
			&& 1 === matching_line_count( $css, '/k-repost/' )
			&& 1 === matching_line_count( $css, '/acquisition/' );
	}
);

check_case(
	'acquisition and favorite token rows use palette tokens only',
	static function () use ( $post_kinds_css ): bool {
		$css = css_file( $post_kinds_css );
		return '' !== exact_rule( $css, '.pk-card.k-acquisition' )
			&& '' !== exact_rule( $css, '.pk-card.k-favorite' )
			&& ! has_raw_hex_colour( exact_rule( $css, '.pk-card.k-acquisition' ) )
			&& ! has_raw_hex_colour( exact_rule( $css, '.pk-card.k-favorite' ) )
			&& false !== strpos( exact_rule( $css, '.pk-card.k-acquisition' ), 'var(--cr-' )
			&& false !== strpos( exact_rule( $css, '.pk-card.k-favorite' ), 'var(--cr-' );
	}
);

check_case(
	'repost token row keeps its literal colour values',
	static function () use ( $post_kinds_css ): bool {
		$row = exact_rule( css_file( $post_kinds_css ), '.pk-card.k-repost' );
		return '' !== $row && has_raw_hex_colour( $row );
	}
);

check_case(
	'article stream rules keep their baseline declarations',
	static function () use ( $post_kinds_css, $article_rules ): bool {
		$fixture = json_decode( css_file( $article_rules ), true );
		if ( ! is_array( $fixture ) ) {
			return false;
		}
		$rules = parsed_css_rules( css_file( $post_kinds_css ) );
		foreach ( $fixture as $entry ) {
			if ( ! has_article_rule( $rules, $entry ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'cr-ticket.css exists with shared ticket selectors',
	static function () use ( $ticket_css ): bool {
		$css = css_file( $ticket_css );
		foreach ( array( '.cr-ticket', '.cr-ticket--zigzag', '.cr-ticket--punch', '.cr-ticket__facts', '.cr-ticket__fact', '.cr-placemat .cr-order--ticket', '.cr-placemat .cr-order__facts', '.cr-placemat .cr-order__fact' ) as $needle ) {
			if ( false === strpos( $css, $needle ) ) {
				return false;
			}
		}
		return false !== strpos( $css, '--ticket-tear' );
	}
);

check_case(
	'cr-ticket.css uses tokens and allowed font sizes',
	static function () use ( $ticket_css ): bool {
		$css = css_file( $ticket_css );
		return '' !== $css && ! has_raw_hex_colour( $css ) && ! has_rgb_literal( $css ) && all_font_sizes_allowed( $css );
	}
);

check_case(
	'cr-eat-drink.css hands shared ticket facts to cr-ticket.css',
	static function () use ( $eat_drink_css ): bool {
		$css = css_file( $eat_drink_css );
		return false === strpos( $css, '--ticket-tear:' )
			&& '' === exact_rule( $css, '.cr-placemat .cr-order__facts' )
			&& '' === exact_rule( $css, '.cr-placemat .cr-order__fact' )
			&& false !== strpos( $css, '.cr-placemat .cr-order--ticket::after' )
			&& false !== strpos( $css, '.cr-placemat .cr-order--coaster .cr-order__facts' );
	}
);

check_case(
	'cr-sticker.css exists with shared sticker selectors and defaults',
	static function () use ( $sticker_css ): bool {
		$css      = css_file( $sticker_css );
		$defaults = exact_rule( $css, ':where(.cr-like-sticker, .cr-sticker)' );
		return false !== strpos( $css, '.cr-sticker' )
			&& false !== strpos( $css, '.cr-sticker::before' )
			&& false !== strpos( $css, '.cr-sticker:focus-within' )
			&& false !== strpos( $css, '.cr-sticker:has(:focus-visible)' )
			&& false !== strpos( $defaults, '--cr-paper' )
			&& false !== strpos( $defaults, '--cr-paper-ink' );
	}
);

check_case(
	'cr-sticker.css owns moved like focus rules',
	static function () use ( $sticker_css ): bool {
		$css = css_file( $sticker_css );
		foreach ( array( '.cr-like article.pk-card.cr-like-sticker', 'article.pk-card.cr-like-sticker--stream', 'body.single-post.kind-like .single-post__content .pk-card.k-like.cr-like-clipping' ) as $selector ) {
			if ( ! rule_contains( $css, $selector . ':focus-within', 'box-shadow' ) || ! rule_contains( $css, $selector . ':has(:focus-visible)', 'box-shadow' ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'cr-sticker.css uses tokens, forced colors and mirrored dark-mode branches',
	static function () use ( $sticker_css ): bool {
		$css            = css_file( $sticker_css );
		$dark_media     = implode( "\n", css_blocks( $css, '@media (prefers-color-scheme: dark)' ) );
		$dark_attribute = false !== strpos( $css, ':root[data-theme="dark"] .cr-sticker::before' );
		return '' !== $css
			&& ! has_raw_hex_colour( $css )
			&& false !== strpos( $css, '@media (forced-colors: active)' )
			&& false !== strpos( $css, 'CanvasText' )
			&& false !== strpos( $dark_media, ':root:not([data-theme="light"])' )
			&& false !== strpos( $dark_media, '.cr-sticker' )
			&& $dark_attribute;
	}
);

check_case(
	'cr-like.css no longer owns shared sticker defaults or focus rules',
	static function () use ( $like_css ): bool {
		$css = css_file( $like_css );
		if ( false !== strpos( $css, ':where(.cr-like-sticker) {' ) ) {
			return false;
		}
		foreach ( array( '.cr-like article.pk-card.cr-like-sticker', 'article.pk-card.cr-like-sticker--stream', 'body.single-post.kind-like' ) as $selector ) {
			$pattern = '/' . preg_quote( $selector, '/' ) . '[^{]*(?::has\(:focus-visible\)|:focus-within)[^{]*\{/s';
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
exit( count( $failures ) ? 1 : 0 );
