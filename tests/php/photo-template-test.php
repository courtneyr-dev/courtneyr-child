<?php
/**
 * `php tests/php/photo-template-test.php`: static checks for the photo
 * archive template and contact-sheet loop pattern (issue 241).
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
	return array_values( array_filter( $tokens, static fn( array $token ): bool => ! $token['closing'] && $name === $token['name'] ) );
}

/** Return block ranges for nesting checks. */
function block_ranges( array $tokens ): array {
	$ranges = array();
	$stack  = array();
	foreach ( $tokens as $token ) {
		if ( $token['closing'] ) {
			$open     = array_pop( $stack );
			$ranges[] = array( 'name' => $open['name'], 'attrs' => $open['attrs'], 'start' => $open['offset'], 'end' => $token['offset'] );
			continue;
		}
		if ( $token['self'] ) {
			$ranges[] = array( 'name' => $token['name'], 'attrs' => $token['attrs'], 'start' => $token['offset'], 'end' => $token['offset'] );
			continue;
		}
		$stack[] = $token;
	}
	return $ranges;
}

/** Whether a token sits inside a range. */
function inside( array $child, array $parent ): bool {
	return $child['offset'] >= $parent['start'] && $child['offset'] <= $parent['end'];
}

/** Whether a className has every expected token. */
function class_tokens( ?string $class_name, array $tokens ): bool {
	$have = preg_split( '/\s+/', trim( (string) $class_name ) );
	foreach ( $tokens as $token ) {
		if ( ! in_array( $token, $have, true ) ) {
			return false;
		}
	}
	return true;
}

/** Evaluate the pattern with the plugin's archive-sections block registered or absent. */
function render_pattern( string $path, bool $sections ): string {
	WP_Block_Type_Registry::$registered = array( 'post-kinds-indieweb/archive-sections' => $sections );
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

check_case(
	'template matches the comics archive shell and places the photo loop and the browse-all pattern',
	static function () use ( $theme_dir ): bool {
		$path = $theme_dir . '/templates/taxonomy-kind-photo.html';
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$template = (string) file_get_contents( $path );
		$tokens   = block_tokens( $template );
		$slugs    = array_map( static fn( array $token ): string => (string) ( $token['attrs']['slug'] ?? '' ), openings( $tokens, 'pattern' ) );
		$main     = array_values( array_filter( openings( $tokens, 'group' ), static fn( array $token ): bool => 'main' === ( $token['attrs']['tagName'] ?? '' ) ) );
		$parts    = array_map( static fn( array $token ): string => (string) ( $token['attrs']['slug'] ?? '' ), openings( $tokens, 'template-part' ) );
		$title    = openings( $tokens, 'query-title' );
		return delimiters_balance( $tokens )
			&& 1 === count( $main )
			&& 'Photo archive' === ( $main[0]['attrs']['metadata']['name'] ?? '' )
			&& class_tokens( $main[0]['attrs']['className'] ?? '', array( 'cr-archive', 'cr-archive--stream', 'cr-archive--photo' ) )
			&& false !== strpos( $template, '<main class="wp-block-group alignfull cr-archive cr-archive--stream cr-archive--photo"' )
			&& 1 === count( openings( $tokens, 'courtneyr/archive-identity' ) )
			&& 1 === count( openings( $tokens, 'term-description' ) )
			&& 1 === count( $title )
			&& 'archive' === ( $title[0]['attrs']['type'] ?? '' )
			&& false === ( $title[0]['attrs']['showPrefix'] ?? true )
			&& 1 === count( openings( $tokens, 'separator' ) )
			&& false !== strpos( $template, 'is-style-cr-marker-bar-short' )
			&& array( 'courtneyr-child/cr-photo-contact-sheet-loop', 'courtneyr-child/cr-browse-all' ) === $slugs
			&& in_array( 'header', $parts, true )
			&& in_array( 'footer', $parts, true )
			&& 0 === count( openings( $tokens, 'query' ) )
			&& 0 === count( openings( $tokens, 'heading' ) );
	}
);

foreach ( array( 'archive-sections registered' => true, 'archive-sections absent' => false ) as $label => $sections ) {
	check_case(
		'pattern contract with ' . $label,
		static function () use ( $theme_dir, $sections ): bool {
			$path = $theme_dir . '/patterns/cr-photo-contact-sheet-loop.php';
			if ( ! file_exists( $path ) ) {
				return false;
			}
			$markup = render_pattern( $path, $sections );
			$tokens = block_tokens( $markup );
			$ranges = block_ranges( $tokens );
			$query  = openings( $tokens, 'query' );
			$post   = openings( $tokens, 'post-template' );
			$cards  = openings( $tokens, 'post-kinds-indieweb/stream-card' );
			$marker = openings( $tokens, 'post-kinds-indieweb/archive-sections' );
			$empty  = openings( $tokens, 'query-no-results' );
			$pager  = openings( $tokens, 'query-pagination' );
			if ( ! delimiters_balance( $tokens ) || 1 !== count( $query ) || 1 !== count( $post ) || 1 !== count( $cards ) || 1 !== count( $empty ) || 1 !== count( $pager ) ) {
				return false;
			}
			$query_range = array_values( array_filter( $ranges, static fn( array $range ): bool => 'query' === $range['name'] ) )[0];
			$post_range  = array_values( array_filter( $ranges, static fn( array $range ): bool => 'post-template' === $range['name'] ) )[0];
			$paragraphs  = openings( $tokens, 'paragraph' );
			$previous    = openings( $tokens, 'query-pagination-previous' );
			$next        = openings( $tokens, 'query-pagination-next' );
			return 241 === ( $query[0]['attrs']['queryId'] ?? 0 )
				&& true === ( $query[0]['attrs']['query']['inherit'] ?? false )
				&& class_tokens( $post[0]['attrs']['className'] ?? '', array( 'cr-photo-sheets' ) )
				&& 3 === ( $cards[0]['attrs']['headingLevel'] ?? 0 )
				&& 'is-style-cr-photo-frame' === ( $cards[0]['attrs']['className'] ?? '' )
				&& inside( $cards[0], $post_range )
				&& 1 === count( $paragraphs )
				&& false !== strpos( $markup, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' )
				&& 'arrow' === ( $pager[0]['attrs']['paginationArrow'] ?? '' )
				&& 'center' === ( $pager[0]['attrs']['layout']['justifyContent'] ?? '' )
				&& 'Previous' === ( $previous[0]['attrs']['label'] ?? '' )
				&& 'Next' === ( $next[0]['attrs']['label'] ?? '' )
				&& 1 === count( openings( $tokens, 'query-pagination-numbers' ) )
				&& inside( $pager[0], $query_range )
				&& $pager[0]['offset'] > $post_range['end']
				&& false === strpos( $markup, 'SAMPLE CONTENT' )
				&& ( $sections
					? 1 === count( $marker ) && 12 === ( $marker[0]['attrs']['linesPerPage'] ?? 0 ) && 2 === ( $marker[0]['attrs']['headingLevel'] ?? 0 ) && inside( $marker[0], $post_range ) && $marker[0]['offset'] < $cards[0]['offset']
					: 0 === count( $marker ) );
		}
	);
}

check_case(
	'no per-page entry: the marker sets 12 and the template names no page-size filter',
	static function () use ( $theme_dir ): bool {
		$markup = render_pattern( $theme_dir . '/patterns/cr-photo-contact-sheet-loop.php', true );
		return false === strpos( $markup, 'courtneyr_child_kind_archive_page_sizes' )
			&& false === strpos( (string) file_get_contents( $theme_dir . '/inc/photo.php' ), 'courtneyr_child_kind_archive_page_sizes' );
	}
);

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
		foreach ( array( '/templates/taxonomy-kind-photo.html', '/patterns/cr-photo-contact-sheet-loop.php' ) as $relative ) {
			$path = $theme_dir . $relative;
			if ( ! file_exists( $path ) ) {
				return false;
			}
			$source = str_ends_with( $relative, '.php' ) ? render_pattern( $path, true ) : (string) file_get_contents( $path );
			$blocks = ( new WP_Block_Parser() )->parse( $source );
			if ( empty( $blocks ) ) {
				return false;
			}
			$walk = static function ( array $items ) use ( &$walk ): bool {
				foreach ( $items as $item ) {
					if ( null === ( $item['blockName'] ?? null ) && '' === trim( (string) ( $item['innerHTML'] ?? '' ) ) ) {
						continue;
					}
					if ( null === ( $item['blockName'] ?? null ) ) {
						return false;
					}
					if ( ! empty( $item['innerBlocks'] ) && ! $walk( $item['innerBlocks'] ) ) {
						return false;
					}
				}
				return true;
			};
			if ( ! $walk( $blocks ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'owned files contain no hex-looking tokens in comments or markup',
	static function () use ( $theme_dir ): bool {
		foreach ( array(
			'/templates/taxonomy-kind-photo.html',
			'/patterns/cr-photo-contact-sheet-loop.php',
			'/inc/photo.php',
			'/inc/single-photo.php',
			'/inc/stream-photo.php',
			'/inc/stream-gallery.php',
			'/inc/sloc-map-alt.php',
			'/assets/css/cr-photo.css',
			'/tests/php/photo-bootstrap.php',
			'/tests/php/photo-template-test.php',
			'/tests/php/photo-archive-test.php',
			'/tests/php/photo-single-test.php',
			'/tests/php/photo-stream-test.php',
			'/tests/php/photo-css-test.php',
		) as $relative ) {
			$path = $theme_dir . $relative;
			if ( ! file_exists( $path ) ) {
				return false;
			}
			if ( preg_match( '/(?<![A-Fa-f0-9])#[A-Fa-f0-9]{3}(?:[A-Fa-f0-9]{1,5})?(?![A-Fa-f0-9])/', (string) file_get_contents( $path ) ) ) {
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
