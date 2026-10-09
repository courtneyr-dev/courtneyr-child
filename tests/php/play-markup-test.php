<?php
/**
 * `php tests/php/play-markup-test.php`: checks the static play scaffold.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

class WP_Block_Type_Registry {
	/** @var bool Whether the optional block is registered. */
	public static $registered = true;

	/** Return the test registry. */
	public static function get_instance(): self {
		return new self();
	}

	/** Report the switchable registration state. */
	public function is_registered( $name ): bool {
		return self::$registered && 'post-kinds-indieweb/staff-picks' === $name;
	}
}

/** Translate text for pattern evaluation. */
function __( $text ) {
	return $text;
}

/** Translate, escape and print text for pattern evaluation. */
function esc_html_e( $text ) {
	echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/** Encode block attributes for pattern evaluation. */
function wp_json_encode( $value ) {
	return json_encode( $value );
}

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

/** Parse Gutenberg comment tokens and their JSON attributes. */
function block_tokens( string $markup ): array {
	preg_match_all( '/<!--\s*(.*?)\s*-->/s', $markup, $comments, PREG_OFFSET_CAPTURE );
	$tokens = array();
	foreach ( $comments[1] as $comment ) {
		$body = trim( $comment[0] );
		if ( 0 === strpos( $body, '/wp:' ) ) {
			$tokens[] = array(
				'name'    => substr( $body, 4 ),
				'closing' => true,
				'self'    => false,
				'attrs'   => null,
				'offset'  => $comment[1],
			);
			continue;
		}
		if ( 0 !== strpos( $body, 'wp:' ) ) {
			continue;
		}
		$self = '/' === substr( $body, -1 );
		if ( $self ) {
			$body = rtrim( substr( $body, 0, -1 ) );
		}
		if ( ! preg_match( '/^wp:([^\s]+)(?:\s+(.+))?$/s', $body, $match ) ) {
			throw new RuntimeException( 'Could not parse block comment: ' . $body );
		}
		$attrs = null;
		if ( isset( $match[2] ) && '' !== trim( $match[2] ) ) {
			$attrs = json_decode( trim( $match[2] ), true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new RuntimeException( 'Invalid block JSON for ' . $match[1] );
			}
		}
		$tokens[] = array(
			'name'    => $match[1],
			'closing' => false,
			'self'    => $self,
			'attrs'   => $attrs,
			'offset'  => $comment[1],
		);
	}
	return $tokens;
}

/** Confirm all non-self-closing Gutenberg comments nest and close. */
function delimiters_balance( array $tokens ): bool {
	$stack = array();
	foreach ( $tokens as $token ) {
		if ( $token['closing'] ) {
			if ( empty( $stack ) || array_pop( $stack ) !== $token['name'] ) {
				return false;
			}
		} elseif ( ! $token['self'] ) {
			$stack[] = $token['name'];
		}
	}
	return empty( $stack );
}

/** Return opening tokens for one block name. */
function openings( array $tokens, string $name ): array {
	return array_values(
		array_filter(
			$tokens,
			static function ( array $token ) use ( $name ): bool {
				return ! $token['closing'] && $name === $token['name'];
			}
		)
	);
}

/** Evaluate the pattern with the optional block registered or absent. */
function render_pattern( string $path, bool $registered ): string {
	WP_Block_Type_Registry::$registered = $registered;
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

check_case(
	'template has the frozen play header, patterns and balanced block comments',
	static function () use ( $theme_dir ): bool {
		$template = (string) file_get_contents( $theme_dir . '/templates/taxonomy-kind-play.html' );
		$tokens   = block_tokens( $template );
		$patterns = openings( $tokens, 'pattern' );
		$slugs    = array_map( static function ( array $token ): string { return (string) ( $token['attrs']['slug'] ?? '' ); }, $patterns );
		$counts   = array(
			'query-title'                => count( openings( $tokens, 'query-title' ) ),
			'courtneyr/archive-identity' => count( openings( $tokens, 'courtneyr/archive-identity' ) ),
			'term-description'           => count( openings( $tokens, 'term-description' ) ),
			'separator'                  => count( openings( $tokens, 'separator' ) ),
		);
		$footer = openings( $tokens, 'template-part' );
		$footer = array_values( array_filter( $footer, static function ( array $token ): bool { return 'footer' === ( $token['attrs']['slug'] ?? '' ); } ) );
		return false !== strpos( $template, '"name":"Play archive"' )
			&& false !== strpos( $template, 'cr-archive--play' )
			&& array( 'courtneyr-child/cr-play-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& ! empty( $footer )
			&& $patterns[1]['offset'] < $footer[0]['offset']
			&& array( 'query-title' => 1, 'courtneyr/archive-identity' => 1, 'term-description' => 1, 'separator' => 1 ) === $counts
			&& false !== strpos( $template, 'is-style-cr-marker-bar-short' )
			&& delimiters_balance( $tokens );
	}
);

check_case(
	'pattern conditionally prints Staff Picks before the query',
	static function () use ( $theme_dir ): bool {
		$path = $theme_dir . '/patterns/cr-play-loop.php';
		$with = render_pattern( $path, true );
		$none = render_pattern( $path, false );
		return false !== strpos( $with, 'wp:post-kinds-indieweb/staff-picks' )
			&& strpos( $with, 'wp:post-kinds-indieweb/staff-picks' ) < strpos( $with, 'wp:query ' )
			&& false === strpos( $none, 'staff-picks' );
	}
);

check_case(
	'pattern block tree and attributes match the frozen archive contract',
	static function () use ( $theme_dir ): bool {
		$path    = $theme_dir . '/patterns/cr-play-loop.php';
		$source  = (string) file_get_contents( $path );
		$markup  = render_pattern( $path, true );
		$tokens  = block_tokens( $markup );
		$groups  = openings( $tokens, 'group' );
		$queries = openings( $tokens, 'query' );
		$posts   = openings( $tokens, 'post-template' );
		$section = openings( $tokens, 'post-kinds-indieweb/archive-sections' );
		$card    = openings( $tokens, 'post-kinds-indieweb/stream-card' );
		$empty   = openings( $tokens, 'query-no-results' );
		$pager   = openings( $tokens, 'query-pagination' );
		$expect_query = array(
			'queryId'   => 232,
			'query'     => array(
				'pages'    => 0,
				'offset'   => 0,
				'postType' => 'post',
				'order'    => 'desc',
				'orderBy'  => 'date',
				'author'   => '',
				'search'   => '',
				'exclude'  => array(),
				'sticky'   => '',
				'inherit'  => true,
			),
			'className' => 'cr-archive-stream__query cr-play__query',
		);
		$expect_section = array(
			'linesPerPage' => 12,
			'headingLevel' => 2,
			'emptyLabel'   => 'Play',
			'groupLabels'  => array( 'video' => 'Video games', 'board' => 'Game Night' ),
		);
		return 1 === count( $groups )
			&& 'cr-archive-stream cr-play cr-shelf-boards' === ( $groups[0]['attrs']['className'] ?? '' )
			&& false === strpos( $markup, 'contentOnly' )
			&& false === strpos( $markup, 'cr-stream-page' )
			&& 1 === count( $queries )
			&& $expect_query === $queries[0]['attrs']
			&& ! array_key_exists( 'perPage', $queries[0]['attrs']['query'] )
			&& 1 === count( $posts )
			&& null === $posts[0]['attrs']
			&& 1 === count( $section )
			&& 1 === count( $card )
			&& $section[0]['offset'] < $card[0]['offset']
			&& $expect_section === $section[0]['attrs']
			&& array( 'headingLevel' => 3, 'className' => 'is-style-cr-play-item' ) === $card[0]['attrs']
			&& 1 === count( $empty )
			&& 1 === count( $pager )
			&& $empty[0]['offset'] < $pager[0]['offset']
			&& false !== strpos( $markup, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' )
			&& delimiters_balance( $tokens )
			&& 1 === preg_match( '/^ \* Slug: courtneyr-child\/cr-play-loop$/m', $source )
			&& 1 === preg_match( '/^ \* Inserter: false$/m', $source );
	}
);

check_case(
	'stylesheet uses only allowed paint, tokens and responsive column values',
	static function () use ( $theme_dir ): bool {
		$css    = (string) file_get_contents( $theme_dir . '/assets/css/cr-play.css' );
		$tokens = (string) file_get_contents( $theme_dir . '/assets/css/tokens.css' );
		$bad    = array(
			'/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'/\brgba?\s*\(/i',
			'/\bhsl\s*\(/i',
			'/gradient\s*\(/i',
			'/blur\s*\(/i',
			'/(?:^|[;{])\s*filter\s*:/im',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		preg_match_all( '/(?:transform|transition)\s*:\s*([^;]+);/i', $css, $motion_values );
		foreach ( $motion_values[1] as $value ) {
			if ( 'none' !== trim( $value ) ) {
				return false;
			}
		}
		preg_match_all( '/font-size\s*:\s*([^;]+);/i', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			if ( false === strpos( $size, '--cr-text-floor' ) ) {
				return false;
			}
		}
		preg_match_all( '/var\(\s*(--cr-[a-z0-9-]+)/i', $css, $used );
		preg_match_all( '/(--cr-[a-z0-9-]+)\s*:/i', $tokens, $defined );
		$known = array_fill_keys( $defined[1], true );
		$known['--cr-play-columns'] = true;
		foreach ( array_unique( $used[1] ) as $token ) {
			if ( ! isset( $known[ $token ] ) ) {
				return false;
			}
		}
		$at_64 = strpos( $css, '@media (max-width: 63.98rem)' );
		$at_40 = strpos( $css, '@media (max-width: 39.98rem)' );
		$at_reduced = strpos( $css, '@media (prefers-reduced-motion: reduce)' );
		$at_forced  = strpos( $css, '@media (forced-colors: active)' );
		$segment_64 = false !== $at_64 && false !== $at_40 ? substr( $css, $at_64, $at_40 - $at_64 ) : '';
		$segment_40 = false !== $at_40 && false !== $at_reduced ? substr( $css, $at_40, $at_reduced - $at_40 ) : '';
		return false !== strpos( $css, '--cr-play-columns: 4' )
			&& 1 === preg_match( '/data-pkiw-group="video"[^}]+--cr-play-columns:\s*3/s', $css )
			&& false !== strpos( $segment_64, '--cr-play-columns: 2' )
			&& false !== strpos( $segment_40, '--cr-play-columns: 1' )
			&& false !== $at_reduced
			&& false !== $at_forced
			&& false === strpos( $css, '.cr-stream-page' );
	}
);

check_case(
	'stylesheet scopes every play item paint selector to ungrouped cards',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-play.css' );
		$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
		preg_match_all( '/([^{}]+)\{/', $css, $rules );
		foreach ( $rules[1] as $selector ) {
			if ( 0 === strpos( trim( $selector ), '@' ) ) {
				continue;
			}
			if ( preg_match( '/cr-play-item(?!--ungrouped)/', $selector ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'new-file comments and mask data contain no literal hex-color notation',
	static function () use ( $theme_dir ): bool {
		$files = array(
			'templates/taxonomy-kind-play.html',
			'patterns/cr-play-loop.php',
			'inc/play.php',
			'assets/css/cr-play.css',
			'tests/php/play-test.php',
			'tests/php/play-markup-test.php',
		);
		foreach ( $files as $file ) {
			$content = (string) file_get_contents( $theme_dir . '/' . $file );
			if ( preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $content ) ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : '6 passed', "\n";
exit( count( $failures ) ? 1 : 0 );
