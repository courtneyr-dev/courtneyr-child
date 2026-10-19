<?php
/**
 * Static checks for podcast archive, single and Stream CSS.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$root       = dirname( __DIR__, 2 );
$css        = (string) file_get_contents( $root . '/assets/css/cr-podcast.css' );
$tokens_css = (string) file_get_contents( $root . '/assets/css/tokens.css' );
$nav_css    = (string) file_get_contents( $root . '/assets/css/cr-nav.css' );
$code       = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
$failures   = array();

function cr_podcast_css_check( bool $ok, string $label ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		$failures[] = $label;
	}
}

function cr_podcast_css_contains( string $needle, string $haystack, string $label ): void {
	cr_podcast_css_check( str_contains( $haystack, $needle ), $label );
}

/**
 * Split a comma-separated shadow list without splitting function arguments.
 *
 * @return string[]
 */
function cr_podcast_css_shadow_parts( string $value ): array {
	$parts = preg_split( '/,(?![^()]*\))/', $value );
	return is_array( $parts ) ? array_map( 'trim', $parts ) : array();
}

function cr_podcast_css_zero_blur( string $css ): bool {
	preg_match_all( '/box-shadow\s*:\s*([^;]+);/s', $css, $matches );
	foreach ( $matches[1] as $declaration ) {
		foreach ( cr_podcast_css_shadow_parts( $declaration ) as $shadow ) {
			if ( 'none' === $shadow || preg_match( '/^var\(--cr-shadow-[a-z0-9-]+\)$/', $shadow ) ) {
				continue;
			}
			preg_match_all( '/(?<![a-z0-9_.-])(-?(?:\d*\.)?\d+)(?:px|rem|em)?\b/i', $shadow, $lengths );
			if ( isset( $lengths[1][2] ) && 0.0 !== (float) $lengths[1][2] ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * First declaration of each token is the light-mode value.
 *
 * @return array<string, string>
 */
function cr_podcast_css_tokens( string $source, bool $first_only = true ): array {
	preg_match_all( '/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $source, $matches, PREG_SET_ORDER );
	$tokens = array();
	foreach ( $matches as $match ) {
		if ( $first_only && isset( $tokens[ $match[1] ] ) ) {
			continue;
		}
		$tokens[ $match[1] ] = trim( $match[2] );
	}
	return $tokens;
}

function cr_podcast_css_token_hex( array $tokens, string $name, array $seen = array() ): string {
	if ( isset( $seen[ $name ] ) || ! isset( $tokens[ $name ] ) ) {
		return '';
	}
	$value = trim( $tokens[ $name ] );
	if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
		return $value;
	}
	if ( preg_match( '/^var\(--([a-z0-9-]+)(?:,[^)]+)?\)$/i', $value, $match ) ) {
		$seen[ $name ] = true;
		return cr_podcast_css_token_hex( $tokens, $match[1], $seen );
	}
	return '';
}

function cr_podcast_css_luminance( string $hex ): float {
	$rgb = array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
	$linear = array_map(
		static function ( int $channel ): float {
			$value = $channel / 255;
			return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		},
		$rgb
	);
	return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function cr_podcast_css_contrast( string $foreground, string $background ): float {
	$one = cr_podcast_css_luminance( $foreground );
	$two = cr_podcast_css_luminance( $background );
	return ( max( $one, $two ) + 0.05 ) / ( min( $one, $two ) + 0.05 );
}

function cr_podcast_css_rule( string $css, string $selector ): string {
	if ( preg_match( '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/s', $css, $match ) ) {
		return trim( preg_replace( '/\s+/', ' ', $match[1] ) ?? '' );
	}
	return '';
}

function cr_podcast_css_font_floor( string $css, array $tokens ): bool {
	preg_match_all( '/font-size\s*:\s*([^;]+);/', $css, $matches );
	foreach ( $matches[1] as $value ) {
		$value = trim( $value );
		if ( preg_match( '/^var\(--([a-z0-9-]+)\)$/i', $value, $token ) ) {
			$value = $tokens[ $token[1] ] ?? '';
		}
		if ( ! preg_match( '/^([0-9]*\.?[0-9]+)(px|rem|em)$/', $value, $size ) ) {
			return false;
		}
		$pixels = 'px' === $size[2] ? (float) $size[1] : (float) $size[1] * 16;
		if ( $pixels < 12 ) {
			return false;
		}
	}
	return true;
}

cr_podcast_css_check( 0 === preg_match( '/#[0-9a-f]{3}(?:[0-9a-f]{3})?\b/i', $code ), 'no raw hex colors' );
cr_podcast_css_check( 0 === preg_match( '/\b(?:rgb|rgba|hsl|hsla)\s*\(/i', $code ), 'no raw functional colors' );
foreach ( array( 'filter', 'blur(', 'drop-shadow', 'text-shadow', 'gradient(', 'mix-blend-mode', 'animation', '@keyframes', 'transition' ) as $forbidden ) {
	cr_podcast_css_check( ! str_contains( strtolower( $code ), $forbidden ), $forbidden . ' is absent' );
}
cr_podcast_css_check( cr_podcast_css_zero_blur( $code ), 'every literal box shadow has a zero blur radius' );

$light_tokens = cr_podcast_css_tokens( $tokens_css );
cr_podcast_css_check( cr_podcast_css_font_floor( $code, $light_tokens ), 'every font size resolves to at least 12px' );

preg_match_all( '/(?:^|[;{])\s*rotate\s*:\s*([^;]+);/m', $code, $rotations );
$allowed_rotation = true;
foreach ( $rotations[1] as $rotation ) {
	$rotation = trim( $rotation );
	if ( 'none' !== $rotation && ! preg_match( '/^var\(--cr-rotate-(?:1|2|neg|neg2)\)$/', $rotation ) ) {
		$allowed_rotation = false;
	}
}
cr_podcast_css_check( $allowed_rotation, 'every rotate property uses an allowed token or resets to none' );
preg_match_all( '/transform\s*:\s*rotate\(\s*([^)]*)\)/', $code, $transforms );
$allowed_transform = true;
foreach ( $transforms[1] as $rotation ) {
	if ( ! preg_match( '/^var\(--cr-rotate-(?:1|2|neg|neg2)\)$/', trim( $rotation ) ) ) {
		$allowed_transform = false;
	}
}
cr_podcast_css_check( $allowed_transform, 'every transform rotation uses an allowed token' );

preg_match_all( '/(?:^|[;{])\s*content\s*:\s*([^;]+);/m', $code, $contents );
$allowed_content = true;
foreach ( $contents[1] as $content ) {
	if ( ! in_array( trim( $content ), array( '""', 'none' ), true ) ) {
		$allowed_content = false;
	}
}
cr_podcast_css_check( $allowed_content, 'generated content is empty or none' );

$dark_pairs = array(
	array(
		':root[data-theme="dark"] .cr-archive--podcast .pkiw-lineup',
		':root:not( [data-theme="light"] ) .cr-archive--podcast .pkiw-lineup',
		'chassis border dark parity',
	),
	array(
		':root[data-theme="dark"] body.cr-single--podcast .cr-podcast-tuner',
		':root:not( [data-theme="light"] ) body.cr-single--podcast .cr-podcast-tuner',
		'tuner border dark parity',
	),
	array(
		':root[data-theme="dark"] .cr-archive--podcast .pkiw-episode__date',
		':root:not( [data-theme="light"] ) .cr-archive--podcast .pkiw-episode__date',
		'date chip dark parity',
	),
	array(
		':root[data-theme="dark"] .cr-stream-page .wp-block-post-template > li .cr-podcast-card .pk-stream-date time',
		':root:not( [data-theme="light"] ) .cr-stream-page .wp-block-post-template > li .cr-podcast-card .pk-stream-date time',
		'card date dark parity',
	),
);
foreach ( $dark_pairs as $pair ) {
	$manual = cr_podcast_css_rule( $code, $pair[0] );
	$system = cr_podcast_css_rule( $code, $pair[1] );
	cr_podcast_css_check( '' !== $manual && $manual === $system, $pair[2] );
}

$forced_start = strpos( $code, '@media ( forced-colors: active )' );
$forced = false === $forced_start ? '' : substr( $code, $forced_start );
cr_podcast_css_check( '' !== $forced, 'forced-colors block is present' );
foreach ( array( '.pkiw-lineup', '.cr-podcast-single', '.cr-podcast-card', '.wp-block-post-kinds-indieweb-listen-types' ) as $surface ) {
	cr_podcast_css_contains( $surface, $forced, 'forced colors covers ' . $surface );
}
cr_podcast_css_contains( '.pkiw-lineup__cover:not(.pkiw-lineup__cover--none, .cr-cover--failed)', $forced, 'forced colors restore real covers after the plugin hide rule' );

cr_podcast_css_contains( '40rem', $code, '40rem breakpoint is present' );
foreach ( array( '--cr-prussian-blue', '--cr-cerulean', '--cr-ut-orange', '--cr-accent-2-bg', '--cr-accent-2-ink', '--cr-shadow-hard', '--cr-focus-ring', '--cr-focus-halo', '--cr-tape' ) as $token ) {
	cr_podcast_css_contains( $token, $code, 'required token ' . $token );
}
cr_podcast_css_check( str_contains( $code, '--cr-text-floor' ) || str_contains( $code, '--cr-text-' ), 'text-size token is present' );

foreach ( array( '000070', '000088', '0070f8', '210188', 'c0d8f8', 'f85000', '78a8d8' ) as $drawn ) {
	cr_podcast_css_check( ! str_contains( strtolower( $code ), $drawn ), 'drawn mockup value ' . $drawn . ' is absent' );
}
cr_podcast_css_contains( 'overflow-wrap: anywhere', $code, 'long text wraps anywhere' );
cr_podcast_css_check( ! str_contains( $code, 'text-overflow' ), 'text-overflow is absent' );
cr_podcast_css_check( ! str_contains( strtolower( $code ), 'ellipsis' ), 'ellipsis is absent' );

$target_rules = array(
	'.wp-block-post-kinds-indieweb-listen-types .pk-listen-types__link' => 'type-nav chip has a 44px minimum',
	'.cr-archive--podcast .pkiw-lineup__episodes > li.wp-block-post'   => 'episode line has a 44px minimum',
	'body.cr-single--podcast .cr-podcast-sources .cr-listen-sources__link a' => 'source link has a 44px minimum',
);
foreach ( $target_rules as $selector => $label ) {
	$rule = cr_podcast_css_rule( $code, $selector );
	cr_podcast_css_check( str_contains( $rule, 'min-height: 2.75rem' ) || str_contains( $rule, 'min-block-size: 2.75rem' ), $label );
}

$type_nav_rule = cr_podcast_css_rule( $code, '.wp-block-post-kinds-indieweb-listen-types .pk-listen-types__link' );
cr_podcast_css_check( str_contains( $type_nav_rule, 'border: var(--cr-border-thick)' ), 'type-nav chip mirrors the nav thick border' );
cr_podcast_css_check( str_contains( $type_nav_rule, 'box-shadow: var(--cr-shadow-hard-sm)' ), 'type-nav chip mirrors the nav small hard shadow' );
cr_podcast_css_check( str_contains( $nav_css, 'min-height: 2.75rem' ) && str_contains( $nav_css, 'var(--cr-border-thick)' ) && str_contains( $nav_css, 'var(--cr-shadow-hard-sm)' ), 'pager retains the shared nav values' );

preg_match( '/:root\[data-theme="dark"\]\s*\{(.*?)\n\}/s', $tokens_css, $dark_block );
$dark_tokens = array_replace( $light_tokens, cr_podcast_css_tokens( $dark_block[1] ?? '', false ) );
$contrast_pairs = array(
	array( $light_tokens, 'cr-ink', 'cr-surface-elevated', 4.5, 'light ink on elevated surface' ),
	array( $dark_tokens, 'cr-ink', 'cr-surface-elevated', 4.5, 'dark ink on elevated surface' ),
	array( $light_tokens, 'cr-accent-2-ink', 'cr-accent-2-bg', 4.5, 'light accent-2 pair' ),
	array( $dark_tokens, 'cr-accent-2-ink', 'cr-accent-2-bg', 4.5, 'dark accent-2 pair' ),
	array( $light_tokens, 'cr-russian-violet-soft', 'cr-surface-elevated', 4.5, 'light kind label on elevated surface' ),
	array( $light_tokens, 'cr-cerulean', 'cr-surface-elevated', 4.5, 'light date on elevated surface' ),
	array( $dark_tokens, 'cr-ink-muted', 'cr-surface-elevated', 4.5, 'dark muted ink on elevated surface' ),
	array( $light_tokens, 'cr-ink-inverse', 'cr-ink', 4.5, 'light inverse ink pair' ),
	array( $dark_tokens, 'cr-ink-inverse', 'cr-ink', 4.5, 'dark inverse ink pair' ),
	array( $light_tokens, 'cr-printer-ivory', 'cr-photocopier-blk', 3.0, 'chassis edge on dark page' ),
);
foreach ( $contrast_pairs as $pair ) {
	$foreground = cr_podcast_css_token_hex( $pair[0], $pair[1] );
	$background = cr_podcast_css_token_hex( $pair[0], $pair[2] );
	cr_podcast_css_check( '' !== $foreground && '' !== $background && cr_podcast_css_contrast( $foreground, $background ) >= $pair[3], $pair[4] . ' meets ' . $pair[3] . ':1' );
}

foreach ( array( '.cr-archive--podcast', 'body.cr-single--podcast', '.cr-podcast-card', '.pkiw-lineup__cover--none', '.pkiw-lineup__cover.cr-cover--failed', '.cr-podcast-card__art--knob', '.cr-podcast-sr' ) as $selector ) {
	cr_podcast_css_contains( $selector, $code, 'required selector ' . $selector );
}
for ( $position = 1; $position <= 7; ++$position ) {
	cr_podcast_css_contains( '.cr-podcast-tuner--pos-' . $position, $code, 'required tuner position ' . $position );
}

cr_podcast_css_contains( '.wp-block-post-kinds-indieweb-listen-types', $code, 'unscoped type-nav selector is present' );
cr_podcast_css_check( ! str_contains( $code, '.cr-archive--podcast .wp-block-post-kinds-indieweb-listen-types' ), 'type-nav selector is not archive-scoped' );
preg_match_all( '/([^{}]+)\{/', $code, $selector_groups );
$surfaces_scoped = true;
foreach ( $selector_groups[1] as $selector_group ) {
	if ( str_contains( $selector_group, '.pkiw-' ) && ! str_contains( $selector_group, '.cr-archive--podcast' ) ) {
		$surfaces_scoped = false;
	}
}
cr_podcast_css_check( $surfaces_scoped, 'lineup selectors stay under the podcast archive scope' );
cr_podcast_css_check( str_contains( $code, 'body.cr-single--podcast .cr-podcast-single' ), 'single rules use the podcast body and component scopes' );
cr_podcast_css_check( str_contains( $code, '.cr-podcast-card__text' ) && str_contains( $code, '.cr-podcast-card__art' ), 'Stream rules stay in the podcast-card component namespace' );

if ( $failures ) {
	exit( 1 );
}

echo "all passed\n";
