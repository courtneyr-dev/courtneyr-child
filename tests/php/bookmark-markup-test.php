<?php
/**
 * `php tests/php/bookmark-markup-test.php`: static checks for bookmark files.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$failures  = array();
$case_count = 0;

/** Print one named check. */
function check_case( string $name, callable $test ): void {
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

/** Normalize whitespace. */
function cr_bm_norm( string $value ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
}

/** Parse Gutenberg comment tokens and JSON attributes. */
function cr_bm_block_tokens( string $markup ): array {
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
				'offset'  => (int) $comment[1],
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
			'offset'  => (int) $comment[1],
		);
	}
	return $tokens;
}

/** Return opening tokens for a block. */
function cr_bm_openings( array $tokens, string $name ): array {
	return array_values(
		array_filter(
			$tokens,
			static function ( array $token ) use ( $name ): bool {
				return ! $token['closing'] && $name === $token['name'];
			}
		)
	);
}

/** Return one file's contents or an empty string when missing. */
function cr_bm_file( string $path ): string {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

/** Parse CSS into media-aware rules. */
function cr_bm_css_rules( string $css ): array {
	$css   = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
	$len   = strlen( $css );
	$rules = array();
	$stack = array();
	$i     = 0;
	while ( $i < $len ) {
		while ( $i < $len && ctype_space( $css[ $i ] ) ) {
			++$i;
		}
		if ( $i >= $len ) {
			break;
		}
		if ( '}' === $css[ $i ] ) {
			array_pop( $stack );
			++$i;
			continue;
		}
		$start = $i;
		while ( $i < $len && '{' !== $css[ $i ] && '}' !== $css[ $i ] ) {
			++$i;
		}
		$prelude = trim( substr( $css, $start, $i - $start ) );
		if ( '' === $prelude || $i >= $len || '}' === $css[ $i ] ) {
			continue;
		}
		++$i;
		$body_start = $i;
		$depth      = 1;
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
			}
			++$i;
		}
		$body = substr( $css, $body_start, $i - $body_start - 1 );
		if ( str_starts_with( $prelude, '@' ) ) {
			$inner = cr_bm_css_rules( $body );
			foreach ( $inner as $rule ) {
				array_unshift( $rule['at'], $prelude );
				$rules[] = $rule;
			}
			continue;
		}
		if ( str_contains( $body, '{' ) ) {
			continue;
		}
		$decls = array();
		foreach ( explode( ';', $body ) as $decl ) {
			if ( ! str_contains( $decl, ':' ) ) {
				continue;
			}
			list( $property, $value ) = array_map( 'trim', explode( ':', $decl, 2 ) );
			$decls[ strtolower( $property ) ] = cr_bm_norm( $value );
		}
		$rules[] = array(
			'at'        => $stack,
			'selectors' => array_map( 'cr_bm_norm', explode( ',', $prelude ) ),
			'decls'     => $decls,
		);
	}
	return $rules;
}

/** Find declarations for one selector and optional at-rule. */
function cr_bm_rule( array $rules, string $selector, string $at = '' ): ?array {
	$selector = cr_bm_norm( $selector );
	foreach ( $rules as $rule ) {
		if ( ! in_array( $selector, $rule['selectors'], true ) ) {
			continue;
		}
		if ( '' !== $at && ! in_array( $at, $rule['at'], true ) ) {
			continue;
		}
		if ( '' === $at && ! empty( $rule['at'] ) ) {
			continue;
		}
		return $rule['decls'];
	}
	return null;
}

/** Assert selector declarations. */
function cr_bm_has_decls( array $rules, string $selector, array $expected, string $at = '' ): bool {
	$decls = cr_bm_rule( $rules, $selector, $at );
	if ( null === $decls ) {
		return false;
	}
	foreach ( $expected as $property => $value ) {
		if ( ! isset( $decls[ $property ] ) || cr_bm_norm( (string) $value ) !== $decls[ $property ] ) {
			return false;
		}
	}
	return true;
}

check_case(
	'template has the bookmark archive block sequence',
	static function () use ( $theme_dir ): bool {
		$path = $theme_dir . '/templates/taxonomy-kind-bookmark.html';
		if ( ! is_file( $path ) ) {
			return false;
		}
		$template = (string) file_get_contents( $path );
		$needles  = array(
			'wp:template-part {"slug":"header"',
			'wp:courtneyr/archive-identity',
			'wp:query-title',
			'"showPrefix":false',
			'wp:term-description',
			'is-style-cr-marker-bar-short',
			'wp:pattern {"slug":"courtneyr-child/cr-bookmark-drawers-loop"}',
			'wp:pattern {"slug":"courtneyr-child/cr-browse-all"}',
			'wp:template-part {"slug":"footer"',
		);
		$offset = -1;
		foreach ( $needles as $needle ) {
			$found = strpos( $template, $needle );
			if ( false === $found || $found <= $offset ) {
				return false;
			}
			$offset = $found;
		}
		return 1 === preg_match_all( '/<main\b/', $template )
			&& str_contains( $template, '"name":"Bookmark archive"' )
			&& str_contains( $template, 'cr-archive--bookmark' )
			&& ! str_contains( $template, 'SAMPLE' )
			&& ! str_contains( $template, 'Drawers use' );
	}
);

check_case(
	'pattern header matches bookmark drawers loop contract',
	static function () use ( $theme_dir ): bool {
		$source = cr_bm_file( $theme_dir . '/patterns/cr-bookmark-drawers-loop.php' );
		return '' !== $source
			&& 1 === preg_match( '/^ \* Slug: courtneyr-child\/cr-bookmark-drawers-loop$/m', $source )
			&& 1 === preg_match( '/^ \* Inserter: false$/m', $source )
			&& 1 === preg_match( '/^ \* Block Types: core\/query$/m', $source )
			&& 1 === preg_match( '/^ \* Viewport Width: 1200$/m', $source );
	}
);

check_case(
	'pattern query and post template attributes match the contract',
	static function () use ( $theme_dir ): bool {
		$source = cr_bm_file( $theme_dir . '/patterns/cr-bookmark-drawers-loop.php' );
		if ( '' === $source ) {
			return false;
		}
		$tokens  = cr_bm_block_tokens( $source );
		$groups  = cr_bm_openings( $tokens, 'group' );
		$queries = cr_bm_openings( $tokens, 'query' );
		$posts   = cr_bm_openings( $tokens, 'post-template' );
		return 1 === count( $groups )
			&& str_contains( (string) ( $groups[0]['attrs']['className'] ?? '' ), 'cr-bookmark-drawers' )
			&& 1 === count( $queries )
			&& 258 === ( $queries[0]['attrs']['queryId'] ?? null )
			&& true === ( $queries[0]['attrs']['query']['inherit'] ?? null )
			&& ! array_key_exists( 'perPage', (array) ( $queries[0]['attrs']['query'] ?? array() ) )
			&& str_contains( (string) ( $queries[0]['attrs']['className'] ?? '' ), 'cr-bookmark-drawers__query' )
			&& 1 === count( $posts )
			&& str_contains( (string) ( $posts[0]['attrs']['className'] ?? '' ), 'cr-bookmark-drawers__list' );
	}
);

check_case(
	'pattern guards archive sections and stream card inside the post template',
	static function () use ( $theme_dir ): bool {
		$source = cr_bm_file( $theme_dir . '/patterns/cr-bookmark-drawers-loop.php' );
		if ( '' === $source ) {
			return false;
		}
		$tokens  = cr_bm_block_tokens( $source );
		$section = cr_bm_openings( $tokens, 'post-kinds-indieweb/archive-sections' );
		$card    = cr_bm_openings( $tokens, 'post-kinds-indieweb/stream-card' );
		$open    = strpos( $source, '<!-- wp:post-template' );
		$close   = strpos( $source, '<!-- /wp:post-template -->' );
		return 1 === count( $section )
			&& 1 === count( $card )
			&& array( 'linesPerPage' => 12, 'headingLevel' => 2 ) === $section[0]['attrs']
			&& array( 'headingLevel' => 3, 'className' => 'is-style-cr-bookmark-card' ) === $card[0]['attrs']
			&& $section[0]['offset'] < $card[0]['offset']
			&& false !== $open
			&& false !== $close
			&& $open < $section[0]['offset']
			&& $card[0]['offset'] < $close
			&& str_contains( $source, "is_registered( 'post-kinds-indieweb/archive-sections' )" )
			&& str_contains( $source, "is_registered( 'post-kinds-indieweb/stream-card' )" );
	}
);

check_case(
	'pattern pagination follows no-results after the post-template',
	static function () use ( $theme_dir ): bool {
		$source = cr_bm_file( $theme_dir . '/patterns/cr-bookmark-drawers-loop.php' );
		if ( '' === $source ) {
			return false;
		}
		$tokens = cr_bm_block_tokens( $source );
		$posts  = cr_bm_openings( $tokens, 'post-template' );
		$empty  = cr_bm_openings( $tokens, 'query-no-results' );
		$pager  = cr_bm_openings( $tokens, 'query-pagination' );
		return 1 === count( $posts )
			&& 1 === count( $empty )
			&& 1 === count( $pager )
			&& $posts[0]['offset'] < $empty[0]['offset']
			&& $empty[0]['offset'] < $pager[0]['offset']
			&& 'arrow' === ( $pager[0]['attrs']['paginationArrow'] ?? '' )
			&& 'center' === ( $pager[0]['attrs']['layout']['justifyContent'] ?? '' )
			&& str_contains( $source, 'wp:query-pagination-previous' )
			&& str_contains( $source, 'wp:query-pagination-numbers' )
			&& str_contains( $source, 'wp:query-pagination-next' );
	}
);

check_case(
	'bookmark adapter stays out of the archive page-size registry',
	static function () use ( $theme_dir ): bool {
		$path = $theme_dir . '/inc/bookmark.php';
		return is_file( $path ) && ! str_contains( (string) file_get_contents( $path ), 'courtneyr_child_kind_archive_page_sizes' );
	}
);

$css_path = $theme_dir . '/assets/css/cr-bookmark.css';
$css      = cr_bm_file( $css_path );
$rules    = cr_bm_css_rules( $css );

check_case(
	'bookmark stylesheet obeys paint and motion prohibitions',
	static function () use ( $css, $rules ): bool {
		if ( '' === $css ) {
			return false;
		}
		$bad = array(
			'/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'/\brgba?\s*\(/i',
			'/\bhsl\s*\(/i',
			'/(?:^|[;{])\s*filter\s*:/im',
			'/(?:^|[;{])\s*(?:transition|animation)\s*:/im',
			'/font-size\s*:\s*0(?:[;\s]|$)/i',
		);
		foreach ( $bad as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return false;
			}
		}
		foreach ( $rules as $rule ) {
			$selector = implode( ',', $rule['selectors'] );
			if ( preg_match( '/\.pkiw-group|\.pkiw-group__heading|\.cr-index-card|\.wp-block-query-pagination/', $selector ) ) {
				if ( preg_match( '/rotate\s*\(/i', implode( ';', $rule['decls'] ) ) || isset( $rule['decls']['rotate'] ) ) {
					return false;
				}
			}
			if ( isset( $rule['decls']['font-size'] ) ) {
				$value = $rule['decls']['font-size'];
				if ( preg_match( '/^([0-9.]+)(rem|px)$/', $value, $match ) ) {
					$floor = 'px' === $match[2] ? 12.0 : 0.75;
					if ( (float) $match[1] < $floor ) {
						return false;
					}
				}
			}
		}
		return true;
	}
);

check_case(
	'drawer grid CSS mechanisms are present',
	static function () use ( $rules ): bool {
		return cr_bm_has_decls(
			$rules,
			'.cr-bookmark-drawers .pkiw-grouped',
			array(
				'--cr-bookmark-gap'     => 'var(--cr-space-lg)',
				'--cr-bookmark-columns' => '1',
				'display'               => 'flex',
				'flex-wrap'             => 'wrap',
				'justify-content'       => 'center',
				'align-items'           => 'flex-start',
				'gap'                   => 'var(--cr-bookmark-gap)',
			)
		)
			&& cr_bm_has_decls( $rules, '.cr-bookmark-drawers .pkiw-grouped', array( '--cr-bookmark-columns' => '2' ), '@media (min-width: 600px)' )
			&& cr_bm_has_decls( $rules, '.cr-bookmark-drawers .pkiw-grouped', array( '--cr-bookmark-columns' => '4' ), '@media (min-width: 1200px)' )
			&& ( $group = cr_bm_rule( $rules, '.cr-bookmark-drawers .pkiw-group' ) )
			&& str_contains( $group['flex'] ?? '', 'var(--cr-bookmark-columns)' )
			&& '2px solid var(--cr-ink)' === ( $group['border'] ?? '' )
			&& 'var(--cr-shadow-hard)' === ( $group['box-shadow'] ?? '' )
			&& ! array_filter( $rules, static fn( array $rule ): bool => isset( $rule['decls']['order'] ) || isset( $rule['decls']['grid-auto-flow'] ) );
	}
);

check_case(
	'index card, failed picture and Stream scrap CSS mechanisms are present',
	static function () use ( $css, $rules ): bool {
		return cr_bm_has_decls( $rules, '.cr-index-card__title a::after', array( 'content' => '""', 'position' => 'absolute', 'inset' => '0' ) )
			&& cr_bm_has_decls( $rules, '.cr-index-card__picture.cr-cover--failed', array( 'display' => 'none' ) )
			&& cr_bm_has_decls( $rules, '.cr-bookmark-stream__stamp', array( 'pointer-events' => 'none' ) )
			&& cr_bm_has_decls( $rules, '.cr-stream-page .pk-card.k-bookmark', array( 'border' => '2px solid var(--cr-ink)', 'box-shadow' => 'var(--cr-shadow-hard)' ) )
			&& cr_bm_has_decls( $rules, '.cr-stream-page .pk-card.k-bookmark::after', array( 'border-color' => 'transparent var(--cr-sky-blue) transparent transparent' ) )
			&& ! preg_match( '/--pk-kind\s*:\s*(?!var\()/i', $css );
	}
);

check_case(
	'forced-colors fallbacks remove decorative paint',
	static function () use ( $rules ): bool {
		$at = '@media (forced-colors: active)';
		return cr_bm_has_decls( $rules, '.cr-bookmark-drawers .pkiw-group', array( 'border' => '2px solid CanvasText', 'box-shadow' => 'none', 'background-image' => 'none' ), $at )
			&& cr_bm_has_decls( $rules, '.cr-bookmark-drawers .pkiw-group__heading', array( 'border' => '2px solid CanvasText', 'box-shadow' => 'none', 'background-image' => 'none' ), $at )
			&& cr_bm_has_decls( $rules, '.cr-index-card', array( 'border' => '2px solid CanvasText', 'box-shadow' => 'none', 'background-image' => 'none' ), $at )
			&& (bool) array_filter(
				$rules,
				static fn( array $rule ): bool => in_array( $at, $rule['at'], true ) && isset( $rule['decls']['color'] ) && 'LinkText' === $rule['decls']['color'] && (bool) preg_grep( '/\ba\b/', $rule['selectors'] )
			)
			&& (bool) array_filter(
				$rules,
				static fn( array $rule ): bool => in_array( $at, $rule['at'], true ) && isset( $rule['decls']['display'] ) && 'none' === $rule['decls']['display'] && in_array( '.cr-bookmark-stream__stamp', $rule['selectors'], true )
			);
	}
);

check_case(
	'stylesheet has no transitions',
	static function () use ( $css ): bool {
		return '' !== $css && ! preg_match( '/(?:^|[;{])\s*transition\s*:/im', $css );
	}
);

check_case(
	'index card paper colors stay on palette constants',
	static function () use ( $css, $rules ): bool {
		return cr_bm_has_decls( $rules, '.cr-index-card', array( 'background-color' => 'var(--cr-printer-ivory)', 'color' => 'var(--cr-russian-violet)' ) )
			&& ! str_contains( $css, 'var(--cr-link)' )
			&& ! str_contains( $css, 'var(--cr-surface)' );
	}
);

printf( "TOTAL %d PASS %d FAIL %d\n", $case_count, $case_count - count( $failures ), count( $failures ) );
exit( empty( $failures ) ? 0 : 1 );
