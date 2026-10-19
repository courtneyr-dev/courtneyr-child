<?php
/**
 * `php tests/php/wish-template-test.php`: checks the static wish scaffold.
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

function cr_wish_template_check( string $name, callable $test ): void {
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

function cr_wish_block_tokens( string $markup ): array {
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

function cr_wish_openings( array $tokens, string $name ): array {
	return array_values( array_filter( $tokens, static fn( array $token ): bool => ! $token['closing'] && $name === $token['name'] ) );
}

function cr_wish_balanced( array $tokens ): bool {
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

function cr_wish_child_of( array $tokens, int $offset, string $parent ): bool {
	$stack = array();
	foreach ( $tokens as $token ) {
		if ( $token['offset'] > $offset ) {
			break;
		}
		if ( $token['closing'] ) {
			array_pop( $stack );
		} elseif ( ! $token['self'] ) {
			$stack[] = $token['name'];
		}
	}
	return in_array( $parent, $stack, true );
}

function cr_wish_render_pattern( string $path ): string {
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

cr_wish_template_check(
	'template delegates to the two frozen patterns and has no inline query',
	static function () use ( $theme_dir ): bool {
		$source = (string) file_get_contents( $theme_dir . '/templates/taxonomy-kind-wish.html' );
		$tokens = cr_wish_block_tokens( $source );
		$slugs  = array_map( static fn( array $token ): string => (string) ( $token['attrs']['slug'] ?? '' ), cr_wish_openings( $tokens, 'pattern' ) );
		return 1 === count( cr_wish_openings( $tokens, 'courtneyr/archive-identity' ) )
			&& array( 'courtneyr-child/cr-wish-catalog-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& 0 === count( cr_wish_openings( $tokens, 'query' ) )
			&& str_contains( $source, '"name":"Wish archive"' )
			&& str_contains( $source, 'cr-archive--wish' )
			&& cr_wish_balanced( $tokens );
	}
);

cr_wish_template_check(
	'pattern carries grouped marker, listing style, empty copy and native pager in order',
	static function () use ( $theme_dir ): bool {
		$path    = $theme_dir . '/patterns/cr-wish-catalog-loop.php';
		$source  = (string) file_get_contents( $path );
		$markup  = cr_wish_render_pattern( $path );
		$tokens  = cr_wish_block_tokens( $markup );
		$query   = cr_wish_openings( $tokens, 'query' )[0] ?? array();
		$post    = cr_wish_openings( $tokens, 'post-template' )[0] ?? array();
		$section = cr_wish_openings( $tokens, 'post-kinds-indieweb/archive-sections' )[0] ?? array();
		$card    = cr_wish_openings( $tokens, 'post-kinds-indieweb/stream-card' )[0] ?? array();
		$pager   = cr_wish_openings( $tokens, 'query-pagination' )[0] ?? array();
		$prev    = cr_wish_openings( $tokens, 'query-pagination-previous' )[0] ?? array();
		$next    = cr_wish_openings( $tokens, 'query-pagination-next' )[0] ?? array();
		return 238 === ( $query['attrs']['queryId'] ?? null )
			&& true === ( $query['attrs']['query']['inherit'] ?? null )
			&& ! array_key_exists( 'perPage', $query['attrs']['query'] ?? array() )
			&& 'cr-wish-catalog' === ( $post['attrs']['className'] ?? '' )
			&& array( 'linesPerPage' => 12, 'headingLevel' => 2, 'emptyLabel' => 'Other' ) === ( $section['attrs'] ?? null )
			&& array( 'headingLevel' => 3, 'className' => 'is-style-cr-wish-listing' ) === ( $card['attrs'] ?? null )
			&& str_contains( $markup, 'Nothing wished for yet.' )
			&& 'Previous' === ( $prev['attrs']['label'] ?? '' )
			&& 'Next' === ( $next['attrs']['label'] ?? '' )
			&& $post['offset'] < $pager['offset']
			&& cr_wish_child_of( $tokens, $pager['offset'], 'query' )
			&& ! cr_wish_child_of( $tokens, $pager['offset'], 'post-template' )
			&& cr_wish_balanced( $tokens )
			&& 1 === preg_match( '/^ \* Title: Wish catalog loop$/m', $source )
			&& 1 === preg_match( '/^ \* Slug: courtneyr-child\/cr-wish-catalog-loop$/m', $source )
			&& 1 === preg_match( '/^ \* Inserter: false$/m', $source );
	}
);

cr_wish_template_check(
	'wish page size stays owned by the archive-sections marker',
	static function () use ( $theme_dir ): bool {
		$source = (string) file_get_contents( $theme_dir . '/inc/theme-supports.php' );
		return 0 === preg_match( '/[\'\"]wish[\'\"]\s*=>\s*12/', $source );
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
