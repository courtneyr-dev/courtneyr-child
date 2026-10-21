<?php
/**
 * `php tests/php/issue-css-test.php`: issue CSS and block scaffold checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

function __( $text ) {
	return $text;
}

function esc_html_e( $text ) {
	echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

$theme_dir = dirname( __DIR__, 2 );
$failures  = array();

function cr_issue_css_check( string $name, callable $test ): void {
	global $failures;
	$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
	try {
		$ok = (bool) $test();
	} catch ( Throwable $error ) {
		$ok = false;
		$name .= ' (' . $error->getMessage() . ')';
	}
	echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
	if ( ! $ok ) {
		$failures[] = $name;
	}
}

function cr_issue_block_tokens( string $markup ): array {
	preg_match_all( '/<!--\s*(.*?)\s*-->/s', $markup, $comments, PREG_OFFSET_CAPTURE );
	$tokens = array();
	foreach ( $comments[1] as $comment ) {
		$body = trim( $comment[0] );
		if ( str_starts_with( $body, '/wp:' ) ) {
			$tokens[] = array( 'name' => substr( $body, 4 ), 'closing' => true, 'self' => false, 'attrs' => null, 'offset' => $comment[1] );
			continue;
		}
		if ( ! str_starts_with( $body, 'wp:' ) ) {
			continue;
		}
		$self = str_ends_with( $body, '/' );
		if ( $self ) {
			$body = rtrim( substr( $body, 0, -1 ) );
		}
		if ( ! preg_match( '/^wp:([^\s]+)(?:\s+(.+))?$/s', $body, $match ) ) {
			throw new RuntimeException( 'Could not parse block comment.' );
		}
		$attrs = null;
		if ( isset( $match[2] ) && '' !== trim( $match[2] ) ) {
			$attrs = json_decode( trim( $match[2] ), true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new RuntimeException( 'Invalid block JSON for ' . $match[1] );
			}
		}
		$tokens[] = array( 'name' => $match[1], 'closing' => false, 'self' => $self, 'attrs' => $attrs, 'offset' => $comment[1] );
	}
	return $tokens;
}

function cr_issue_openings( array $tokens, string $name ): array {
	return array_values( array_filter( $tokens, static fn( array $token ): bool => ! $token['closing'] && $name === $token['name'] ) );
}

function cr_issue_balanced( array $tokens ): bool {
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

function cr_issue_render_pattern( string $path ): string {
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

cr_issue_css_check(
	'CSS uses tokens and omits prohibited paint operations',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-issue.css' );
		return 0 === preg_match( '/#[0-9a-f]{3,8}\b/i', $css )
			&& ! str_contains( $css, 'gradient' )
			&& ! str_contains( $css, 'blur(' )
			&& ! str_contains( $css, 'filter:' )
			&& str_contains( $css, 'background-image: var(--cr-fiber)' );
	}
);

cr_issue_css_check(
	'box shadows, movement and rotations stay inside the frozen allowlists',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-issue.css' );
		preg_match_all( '/box-shadow:\s*([^;]+);/', $css, $shadows );
		foreach ( $shadows[1] as $shadow ) {
			if ( ! in_array( trim( $shadow ), array( 'var(--cr-shadow-hard)', 'var(--cr-shadow-hard-sm)', '0 0 0 7px var(--cr-focus-halo)', 'none' ), true ) ) {
				return false;
			}
		}
		preg_match_all( '/\brotate:\s*([^;]+);/', $css, $rotations );
		foreach ( $rotations[1] as $rotation ) {
			if ( ! in_array( trim( $rotation ), array( 'var(--cr-rotate-1)', 'var(--cr-rotate-2)', 'var(--cr-rotate-neg)', 'var(--cr-rotate-neg2)' ), true ) ) {
				return false;
			}
		}
		$motion = strpos( $css, '@media (prefers-reduced-motion: no-preference)' );
		preg_match_all( '/(?:^|[;{])\s*transform:\s*([^;]+);/m', $css, $moves, PREG_OFFSET_CAPTURE );
		$move = $moves[0][0][1] ?? false;
		return 1 === count( $moves[0] ) && false !== $motion && false !== $move && $move > $motion;
	}
);

cr_issue_css_check(
	'CSS contains archive breakpoints, object focus, dark and forced-color contracts',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-issue.css' );
		$needles = array(
			'@media (min-width: 40rem)',
			'@media (min-width: 64rem)',
			'@media (min-width: 80rem)',
			':not(:has([data-pkiw-status="open"]))::before',
			':not(:has([data-pkiw-status="closed"]))::after',
			'outline: 3px solid var(--cr-focus-ring)',
			'box-shadow: 0 0 0 7px var(--cr-focus-halo)',
			'@media (prefers-color-scheme: dark)',
			':root[data-theme="dark"]',
			'@media (forced-colors: active)',
			'border: 2px solid CanvasText',
		);
		foreach ( $needles as $needle ) {
			if ( ! str_contains( $css, $needle ) ) {
				return false;
			}
		}
		return true;
	}
);

cr_issue_css_check(
	'font sizes never set a literal below the text floor',
	static function () use ( $theme_dir ): bool {
		$css = (string) file_get_contents( $theme_dir . '/assets/css/cr-issue.css' );
		preg_match_all( '/font-size:\s*([0-9.]+)rem\s*;/', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			if ( (float) $size < 0.75 ) {
				return false;
			}
		}
		return str_contains( $css, 'font-size: var(--cr-text-floor)' );
	}
);

cr_issue_css_check(
	'template delegates to the issue rack and browse patterns with balanced blocks',
	static function () use ( $theme_dir ): bool {
		$source = (string) file_get_contents( $theme_dir . '/templates/taxonomy-kind-issue.html' );
		$tokens = cr_issue_block_tokens( $source );
		$slugs  = array_map( static fn( array $token ): string => (string) ( $token['attrs']['slug'] ?? '' ), cr_issue_openings( $tokens, 'pattern' ) );
		return array( 'courtneyr-child/cr-issue-rack-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& 1 === count( cr_issue_openings( $tokens, 'courtneyr/archive-identity' ) )
			&& 0 === count( cr_issue_openings( $tokens, 'query' ) )
			&& str_contains( $source, '"name":"Issue archive"' )
			&& str_contains( $source, 'cr-archive--issue' )
			&& cr_issue_balanced( $tokens );
	}
);

cr_issue_css_check(
	'pattern carries the grouped contract and keeps its pager outside the rack',
	static function () use ( $theme_dir ): bool {
		$path    = $theme_dir . '/patterns/cr-issue-rack-loop.php';
		$source  = (string) file_get_contents( $path );
		$markup  = cr_issue_render_pattern( $path );
		$tokens  = cr_issue_block_tokens( $markup );
		$query   = cr_issue_openings( $tokens, 'query' )[0] ?? array();
		$post    = cr_issue_openings( $tokens, 'post-template' )[0] ?? array();
		$section = cr_issue_openings( $tokens, 'post-kinds-indieweb/archive-sections' )[0] ?? array();
		$card    = cr_issue_openings( $tokens, 'post-kinds-indieweb/stream-card' )[0] ?? array();
		$pager   = cr_issue_openings( $tokens, 'query-pagination' )[0] ?? array();
		$prev    = cr_issue_openings( $tokens, 'query-pagination-previous' )[0] ?? array();
		$next    = cr_issue_openings( $tokens, 'query-pagination-next' )[0] ?? array();
		return 264 === ( $query['attrs']['queryId'] ?? null )
			&& true === ( $query['attrs']['query']['inherit'] ?? null )
			&& 'cr-issue-rack' === ( $post['attrs']['className'] ?? '' )
			&& array( 'linesPerPage' => 12, 'headingLevel' => 2, 'emptyLabel' => 'Issue', 'groupLabels' => array( 'open' => 'Waiting — Open', 'closed' => 'Picked up — Closed' ) ) === ( $section['attrs'] ?? null )
			&& array( 'headingLevel' => 4, 'className' => 'is-style-cr-claim-ticket' ) === ( $card['attrs'] ?? null )
			&& 'Previous' === ( $prev['attrs']['label'] ?? '' )
			&& 'Next' === ( $next['attrs']['label'] ?? '' )
			&& str_contains( $markup, 'No issues yet.' )
			&& ( $post['offset'] ?? PHP_INT_MAX ) < ( $pager['offset'] ?? 0 )
			&& cr_issue_balanced( $tokens )
			&& str_contains( $source, ' * Slug: courtneyr-child/cr-issue-rack-loop' )
			&& str_contains( $source, ' * Inserter: false' );
	}
);

cr_issue_css_check(
	'owned PHP adds no regular-expression calls and banned copy stays absent',
	static function () use ( $theme_dir ): bool {
		$files = array( '/inc/issue.php', '/inc/single-issue.php', '/inc/stream-issue.php' );
		$source = '';
		foreach ( $files as $file ) {
			$source .= (string) file_get_contents( $theme_dir . $file );
		}
		$banned = array( 'Updated', 'SAMPLE CONTENT', 'No closed issues', 'REPAIR SHOP', 'POST KINDS ISSUE', 'NO. box', 'Notes heading', 'saved post data', 'View issue post' );
		foreach ( $banned as $text ) {
			if ( str_contains( $source, $text ) ) {
				return false;
			}
		}
		return ! str_contains( $source, 'preg_' );
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
