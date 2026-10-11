<?php
/**
 * `php tests/php/like-markup-test.php`: checks the static like scaffold.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

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

/** Read a file or return an empty string. */
function read_file( string $path ): string {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

/** Evaluate a PHP pattern. */
function render_pattern( string $path ): string {
	if ( ! is_file( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** Return true when one block opens after another and closes before that block closes. */
function is_child_block( array $tokens, int $child_offset, string $parent_name ): bool {
	$stack = array();
	foreach ( $tokens as $token ) {
		if ( $token['offset'] > $child_offset ) {
			break;
		}
		if ( $token['closing'] ) {
			array_pop( $stack );
		} elseif ( ! $token['self'] ) {
			$stack[] = $token['name'];
		}
	}
	return in_array( $parent_name, $stack, true );
}

/** Extract comments from PHP, CSS or HTML for the local no-hex-comment rule. */
function file_comments( string $source ): string {
	$out = '';
	if ( preg_match_all( '/\/\*\*?[\s\S]*?\*\/|\/\/[^\n\r]*|<!--[\s\S]*?-->/', $source, $matches ) ) {
		$out = implode( "\n", $matches[0] );
	}
	return $out;
}

check_case(
	'template file exists',
	static function () use ( $theme_dir ): bool {
		return is_file( $theme_dir . '/templates/taxonomy-kind-like.html' );
	}
);

check_case(
	'template has the frozen like archive structure',
	static function () use ( $theme_dir ): bool {
		$template = read_file( $theme_dir . '/templates/taxonomy-kind-like.html' );
		if ( '' === $template ) {
			return false;
		}
		$tokens   = block_tokens( $template );
		$patterns = openings( $tokens, 'pattern' );
		$slugs    = array_map( static function ( array $token ): string { return (string) ( $token['attrs']['slug'] ?? '' ); }, $patterns );
		$main     = openings( $tokens, 'group' );
		$main     = array_values(
			array_filter(
				$main,
				static function ( array $token ): bool {
					return 'main' === ( $token['attrs']['tagName'] ?? '' );
				}
			)
		);
		$query_titles = openings( $tokens, 'query-title' );
		$separator    = openings( $tokens, 'separator' );
		$parts        = openings( $tokens, 'template-part' );
		$part_slugs   = array_map( static function ( array $token ): string { return (string) ( $token['attrs']['slug'] ?? '' ); }, $parts );
		return 1 === count( $main )
			&& 'Like archive' === ( $main[0]['attrs']['metadata']['name'] ?? '' )
			&& false !== strpos( (string) ( $main[0]['attrs']['className'] ?? '' ), 'cr-archive--like' )
			&& 1 === count( openings( $tokens, 'courtneyr/archive-identity' ) )
			&& 1 === count( $query_titles )
			&& 'archive' === ( $query_titles[0]['attrs']['type'] ?? '' )
			&& false === ( $query_titles[0]['attrs']['showPrefix'] ?? null )
			&& 1 === count( openings( $tokens, 'term-description' ) )
			&& 1 === count( $separator )
			&& false !== strpos( (string) ( $separator[0]['attrs']['className'] ?? '' ), 'is-style-cr-marker-bar-short' )
			&& array( 'courtneyr-child/cr-like-sticker-book-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& in_array( 'header', $part_slugs, true )
			&& in_array( 'footer', $part_slugs, true )
			&& false === strpos( $template, 'Sample content' )
			&& delimiters_balance( $tokens );
	}
);

check_case(
	'pattern file exists',
	static function () use ( $theme_dir ): bool {
		return is_file( $theme_dir . '/patterns/cr-like-sticker-book-loop.php' );
	}
);

check_case(
	'pattern block tree and attributes match the like sticker book contract',
	static function () use ( $theme_dir ): bool {
		$path   = $theme_dir . '/patterns/cr-like-sticker-book-loop.php';
		$source = read_file( $path );
		$markup = render_pattern( $path );
		if ( '' === $source || '' === $markup ) {
			return false;
		}
		$tokens  = block_tokens( $markup );
		$groups  = openings( $tokens, 'group' );
		$queries = openings( $tokens, 'query' );
		$posts   = openings( $tokens, 'post-template' );
		$section = openings( $tokens, 'post-kinds-indieweb/archive-sections' );
		$card    = openings( $tokens, 'post-kinds-indieweb/stream-card' );
		$empty   = openings( $tokens, 'query-no-results' );
		$pager   = openings( $tokens, 'query-pagination' );
		if ( 1 !== count( $groups ) || 1 !== count( $queries ) || 1 !== count( $posts ) || 1 !== count( $section ) || 1 !== count( $card ) || 1 !== count( $empty ) || 1 !== count( $pager ) ) {
			return false;
		}
		$query = $queries[0]['attrs']['query'] ?? array();
		return false !== strpos( (string) ( $groups[0]['attrs']['className'] ?? '' ), 'cr-like' )
			&& true === ( $query['inherit'] ?? null )
			&& ! array_key_exists( 'perPage', $query )
			&& false !== strpos( (string) ( $posts[0]['attrs']['className'] ?? '' ), 'cr-sticker-book' )
			&& array( 'linesPerPage' => 12, 'headingLevel' => 2, 'emptyLabel' => 'Other' ) === $section[0]['attrs']
			&& $section[0]['offset'] < $card[0]['offset']
			&& true === is_child_block( $tokens, $card[0]['offset'], 'post-template' )
			&& array( 'headingLevel' => 3, 'className' => 'is-style-cr-sticker' ) === $card[0]['attrs']
			&& $posts[0]['offset'] < $empty[0]['offset']
			&& $posts[0]['offset'] < $pager[0]['offset']
			&& false === is_child_block( $tokens, $pager[0]['offset'], 'post-template' )
			&& false === strpos( $markup, 'staff-picks' )
			&& false !== strpos( $markup, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' )
			&& delimiters_balance( $tokens )
			&& 1 === preg_match( '/^ \* Title: Like sticker book loop$/m', $source )
			&& 1 === preg_match( '/^ \* Slug: courtneyr-child\/cr-like-sticker-book-loop$/m', $source )
			&& 1 === preg_match( '/^ \* Categories: cr-zine, cr-loops$/m', $source )
			&& 1 === preg_match( '/^ \* Block Types: core\/query$/m', $source )
			&& 1 === preg_match( '/^ \* Inserter: false$/m', $source );
	}
);

check_case(
	'new-file comments contain no forbidden short hex markers',
	static function () use ( $theme_dir ): bool {
		$files = array(
			'/templates/taxonomy-kind-like.html',
			'/patterns/cr-like-sticker-book-loop.php',
			'/inc/like.php',
			'/assets/css/cr-like.css',
			'/tests/php/like-markup-test.php',
			'/tests/php/like-test.php',
			'/tests/php/like-css-test.php',
		);
		foreach ( $files as $file ) {
			$path = $theme_dir . $file;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$comments = file_comments( (string) file_get_contents( $path ) );
			if ( preg_match( '/#[0-9a-fA-F]{3}(?![0-9a-fA-F])|#[0-9a-fA-F]{6}(?![0-9a-fA-F])/', $comments ) ) {
				return false;
			}
		}
		return true;
	}
);

echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
exit( count( $failures ) ? 1 : 0 );
