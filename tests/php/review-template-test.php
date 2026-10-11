<?php
/**
 * Static checks for the review template and loop pattern.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

class WP_Block_Type_Registry {
	/** @var array<string, bool> Registered block names. */
	public static array $registered = array();

	/** Return the test registry. */
	public static function get_instance(): self {
		return new self();
	}

	/** Report the switchable registration state. */
	public function is_registered( $name ): bool {
		return ! empty( self::$registered[ (string) $name ] );
	}
}

/** Translate text for pattern evaluation. */
function __( $text, $domain = null ): string {
	return (string) $text;
}

/** Translate, escape and print text for pattern evaluation. */
function esc_html_e( $text, $domain = null ): void {
	echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/** Encode block attributes for pattern evaluation. */
function wp_json_encode( $value ): string {
	return (string) json_encode( $value );
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
			$tokens[] = array( 'name' => substr( $body, 4 ), 'closing' => true, 'self' => false, 'attrs' => null, 'offset' => $comment[1] );
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
		$tokens[] = array( 'name' => $match[1], 'closing' => false, 'self' => $self, 'attrs' => $attrs, 'offset' => $comment[1] );
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
			static fn( array $token ): bool => ! $token['closing'] && $name === $token['name']
		)
	);
}

/** Return block ranges for nesting checks. */
function block_ranges( array $tokens ): array {
	$ranges = array();
	$stack  = array();
	foreach ( $tokens as $i => $token ) {
		if ( $token['closing'] ) {
			$open            = array_pop( $stack );
			$ranges[]        = array(
				'name'   => $open['name'],
				'attrs'  => $open['attrs'],
				'start'  => $open['offset'],
				'end'    => $token['offset'],
				'offset' => $open['offset'],
			);
			continue;
		}
		if ( $token['self'] ) {
			$ranges[] = array(
				'name'   => $token['name'],
				'attrs'  => $token['attrs'],
				'start'  => $token['offset'],
				'end'    => $token['offset'],
				'offset' => $token['offset'],
			);
			continue;
		}
		$stack[] = $token;
	}
	return $ranges;
}

/** Find whether a token sits inside a named range. */
function inside( array $child, array $parent ): bool {
	return $child['offset'] >= $parent['start'] && $child['offset'] <= $parent['end'];
}

/** Token className has all expected tokens. */
function class_tokens( ?string $class_name, array $tokens ): bool {
	$have = preg_split( '/\s+/', trim( (string) $class_name ) );
	foreach ( $tokens as $token ) {
		if ( ! in_array( $token, $have, true ) ) {
			return false;
		}
	}
	return true;
}

/** Evaluate the pattern with optional plugin blocks registered or absent. */
function render_pattern( string $path, bool $four_stars, bool $review_entry ): string {
	WP_Block_Type_Registry::$registered = array(
		'post-kinds-indieweb/four-stars'   => $four_stars,
		'post-kinds-indieweb/review-entry' => $review_entry,
	);
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

check_case(
	'template exists and matches the frozen review archive shell',
	static function () use ( $theme_dir ): bool {
		$path = $theme_dir . '/templates/taxonomy-kind-review.html';
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$template = (string) file_get_contents( $path );
		$tokens   = block_tokens( $template );
		$patterns = openings( $tokens, 'pattern' );
		$slugs    = array_map( static fn( array $token ): string => (string) ( $token['attrs']['slug'] ?? '' ), $patterns );
		$main     = openings( $tokens, 'group' );
		$main     = array_values( array_filter( $main, static fn( array $token ): bool => 'main' === ( $token['attrs']['tagName'] ?? '' ) ) );
		$parts    = openings( $tokens, 'template-part' );
		$header   = array_values( array_filter( $parts, static fn( array $token ): bool => 'header' === ( $token['attrs']['slug'] ?? '' ) ) );
		$footer   = array_values( array_filter( $parts, static fn( array $token ): bool => 'footer' === ( $token['attrs']['slug'] ?? '' ) ) );
		return delimiters_balance( $tokens )
			&& 1 === count( $main )
			&& 'Review archive' === ( $main[0]['attrs']['metadata']['name'] ?? '' )
			&& class_tokens( $main[0]['attrs']['className'] ?? '', array( 'cr-archive', 'cr-archive--stream', 'cr-archive--review' ) )
			&& false !== strpos( $template, '<main class="wp-block-group alignfull cr-archive cr-archive--stream cr-archive--review"' )
			&& 1 === count( openings( $tokens, 'courtneyr/archive-identity' ) )
			&& 1 === count( openings( $tokens, 'term-description' ) )
			&& 1 === count( openings( $tokens, 'separator' ) )
			&& 1 === count( openings( $tokens, 'query-title' ) )
			&& 'archive' === ( openings( $tokens, 'query-title' )[0]['attrs']['type'] ?? '' )
			&& false === ( openings( $tokens, 'query-title' )[0]['attrs']['showPrefix'] ?? true )
			&& false !== strpos( $template, 'is-style-cr-marker-bar-short' )
			&& array( 'courtneyr-child/cr-review-columns-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& ! empty( $header )
			&& ! empty( $footer )
			&& 0 === count( openings( $tokens, 'heading' ) )
			&& ! preg_match( '/sample content/i', $template );
	}
);

foreach ( array(
	'both plugin blocks registered' => array( true, true ),
	'four-stars only registered'    => array( true, false ),
	'review-entry only registered'  => array( false, true ),
	'no plugin blocks registered'   => array( false, false ),
) as $label => $state ) {
	check_case(
		'pattern contract with ' . $label,
		static function () use ( $theme_dir, $state ): bool {
			$path = $theme_dir . '/patterns/cr-review-columns-loop.php';
			if ( ! file_exists( $path ) ) {
				return false;
			}
			$markup = render_pattern( $path, $state[0], $state[1] );
			$tokens = block_tokens( $markup );
			$ranges = block_ranges( $tokens );
			$groups = openings( $tokens, 'group' );
			$query  = openings( $tokens, 'query' );
			$post   = openings( $tokens, 'post-template' );
			$empty  = openings( $tokens, 'query-no-results' );
			$pager  = openings( $tokens, 'query-pagination' );
			if ( ! delimiters_balance( $tokens ) || 1 !== count( $groups ) || 1 !== count( $query ) || 1 !== count( $post ) || 1 !== count( $empty ) || 1 !== count( $pager ) ) {
				return false;
			}
			$query_range = array_values( array_filter( $ranges, static fn( array $range ): bool => 'query' === $range['name'] ) )[0];
			$post_range  = array_values( array_filter( $ranges, static fn( array $range ): bool => 'post-template' === $range['name'] ) )[0];
			$paragraphs  = openings( $tokens, 'paragraph' );
			$previous    = openings( $tokens, 'query-pagination-previous' );
			$numbers     = openings( $tokens, 'query-pagination-numbers' );
			$next        = openings( $tokens, 'query-pagination-next' );
			$four        = openings( $tokens, 'post-kinds-indieweb/four-stars' );
			$entry       = openings( $tokens, 'post-kinds-indieweb/review-entry' );
			$title       = openings( $tokens, 'post-title' );
			return class_tokens( $groups[0]['attrs']['className'] ?? '', array( 'cr-archive-stream', 'cr-review', 'cr-review-columns' ) )
				&& 'wide' === ( $groups[0]['attrs']['align'] ?? '' )
				&& 252 === ( $query[0]['attrs']['queryId'] ?? 0 )
				&& true === ( $query[0]['attrs']['query']['inherit'] ?? false )
				&& 9 === ( $query[0]['attrs']['query']['perPage'] ?? 0 )
				&& class_tokens( $query[0]['attrs']['className'] ?? '', array( 'cr-archive-stream__query', 'cr-review__query' ) )
				&& class_tokens( $post[0]['attrs']['className'] ?? '', array( 'cr-archive-stream__list', 'cr-review__list' ) )
				&& 1 === count( $paragraphs )
				&& 'cr-archive-stream__empty' === ( $paragraphs[0]['attrs']['className'] ?? '' )
				&& false !== strpos( $markup, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' )
				&& 'arrow' === ( $pager[0]['attrs']['paginationArrow'] ?? '' )
				&& 'center' === ( $pager[0]['attrs']['layout']['justifyContent'] ?? '' )
				&& 1 === count( $previous )
				&& 'Previous' === ( $previous[0]['attrs']['label'] ?? '' )
				&& 1 === count( $numbers )
				&& 1 === count( $next )
				&& 'Next' === ( $next[0]['attrs']['label'] ?? '' )
				&& inside( $pager[0], $query_range )
				&& $pager[0]['offset'] > $post_range['end']
				&& false === strpos( $markup, 'post-kinds-indieweb/staff-picks' )
				&& false === strpos( $markup, 'SAMPLE CONTENT' )
				&& ( $state[0]
					? 1 === count( $four )
						&& 'review' === ( $four[0]['attrs']['kind'] ?? '' )
						&& class_tokens( $four[0]['attrs']['className'] ?? '', array( 'cr-review__four-stars', 'cr-four-stars', 'cr-paper-sheet', 'cr-paper--light-orange', 'cr-paper-sheet--torn' ) )
						&& $four[0]['offset'] < $query[0]['offset']
						&& ! inside( $four[0], $query_range )
					: 0 === count( $four ) )
				&& ( $state[1]
					? 1 === count( $entry ) && 9 === ( $entry[0]['attrs']['linesPerPage'] ?? 0 ) && inside( $entry[0], $post_range ) && 0 === count( $title )
					: 0 === count( $entry ) && 1 === count( $title ) && 3 === ( $title[0]['attrs']['level'] ?? 0 ) && true === ( $title[0]['attrs']['isLink'] ?? false ) && inside( $title[0], $post_range ) );
		}
	);
}

check_case(
	'real block parser accepts the template and pattern when available',
	static function () use ( $theme_dir ): bool {
		$core   = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
		$parser = $core . '/wp-includes/class-wp-block-parser.php';
		if ( ! file_exists( $parser ) ) {
			return true;
		}
		foreach ( array( 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php' ) as $parser_file ) {
			require_once $core . '/wp-includes/' . $parser_file;
		}
		foreach ( array( '/templates/taxonomy-kind-review.html', '/patterns/cr-review-columns-loop.php' ) as $relative ) {
			$path = $theme_dir . $relative;
			if ( ! file_exists( $path ) ) {
				return false;
			}
			$source = str_ends_with( $relative, '.php' ) ? render_pattern( $path, true, true ) : (string) file_get_contents( $path );
			$blocks = ( new WP_Block_Parser() )->parse( $source );
			if ( empty( $blocks ) ) {
				return false;
			}
			$flat = array();
			$walk = static function ( array $items ) use ( &$walk, &$flat ): void {
				foreach ( $items as $item ) {
					if ( null === ( $item['blockName'] ?? null ) && '' === trim( (string) ( $item['innerHTML'] ?? '' ) ) ) {
						continue;
					}
					$flat[] = $item['blockName'] ?? null;
					if ( ! empty( $item['innerBlocks'] ) ) {
						$walk( $item['innerBlocks'] );
					}
				}
			};
			$walk( $blocks );
			if ( in_array( null, $flat, true ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'owned files contain no issue-style hex-looking comment tokens',
	static function () use ( $theme_dir ): bool {
		foreach ( array(
			'/templates/taxonomy-kind-review.html',
			'/patterns/cr-review-columns-loop.php',
			'/inc/review.php',
			'/inc/single-review.php',
			'/inc/stream-review.php',
			'/assets/css/cr-review.css',
			'/tests/php/review-bootstrap.php',
			'/tests/php/review-template-test.php',
			'/tests/php/review-archive-test.php',
			'/tests/php/review-single-test.php',
			'/tests/php/review-stream-test.php',
			'/tests/php/review-css-test.php',
		) as $relative ) {
			$path = $theme_dir . $relative;
			if ( ! file_exists( $path ) ) {
				return false;
			}
			if ( preg_match( '/(?<![A-Fa-f0-9])#[A-Fa-f0-9]{3}(?:[A-Fa-f0-9]{3})?(?![A-Fa-f0-9])/', (string) file_get_contents( $path ) ) ) {
				return false;
			}
		}
		return true;
	}
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '  ' . $failure . "\n" );
	}
	echo count( $failures ) . " failed\n";
	exit( 1 );
}

echo "all passed\n";
