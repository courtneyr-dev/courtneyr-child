<?php
/**
 * `php tests/php/repost-template-test.php`: checks the repost archive template
 * and the zine loop pattern without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	/** Stand-in block type registry. */
	class WP_Block_Type_Registry {
		/** @var array<string, bool> */
		public static $registered = array();

		/** @var WP_Block_Type_Registry|null */
		private static $instance;

		public static function get_instance(): WP_Block_Type_Registry {
			return self::$instance ?? ( self::$instance = new self() );
		}

		public function is_registered( string $name ): bool {
			return ! empty( self::$registered[ $name ] );
		}
	}

	function esc_html_e( $text, $domain = '' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function __( $text, $domain = '' ) {
		return $text;
	}

	function wp_json_encode( $value ) {
		return json_encode( $value );
	}

	/** Render a pattern file the way core does: include it and keep the output. */
	function repost_pattern_html( array $registered, string $pattern = 'cr-repost-zine-loop' ): string {
		WP_Block_Type_Registry::$registered = $registered;
		ob_start();
		include dirname( __DIR__, 2 ) . '/patterns/' . $pattern . '.php';
		return (string) ob_get_clean();
	}

	$failures   = array();
	$case_count = 0;

	/** Run one named check. */
	function repost_template_case( string $name, callable $test ): void {
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

	/** The block-comment lines of a file, trimmed, in order. */
	function repost_block_lines( string $markup ): array {
		preg_match_all( '/<!--\s*\/?wp:[^>]*-->/', $markup, $matches );
		return array_map( 'trim', $matches[0] );
	}

	$dir      = dirname( __DIR__, 2 );
	$template = (string) file_get_contents( $dir . '/templates/taxonomy-kind-repost.html' );
	$generic  = (string) file_get_contents( $dir . '/templates/taxonomy-kind.html' );
	$all      = array(
		'post-kinds-indieweb/archive-sections' => true,
		'post-kinds-indieweb/stream-card'      => true,
	);

	repost_template_case(
		'the template keeps the generic header and swaps only the loop pattern',
		static function () use ( $template, $generic ): bool {
			$head = static function ( string $markup ): string {
				return substr( $markup, 0, (int) strpos( $markup, '<!-- wp:pattern' ) );
			};
			$tail = static function ( string $markup ): string {
				return substr( $markup, (int) strpos( $markup, '<!-- wp:pattern {"slug":"courtneyr-child/cr-browse-all"' ) );
			};
			return '' !== $template
				&& $head( $template ) === $head( $generic )
				&& $tail( $template ) === $tail( $generic )
				&& 1 === substr_count( $template, '<!-- wp:pattern {"slug":"courtneyr-child/cr-repost-zine-loop"} /-->' )
				&& false === strpos( $template, 'cr-archive-stream-loop' )
				&& false !== strpos( $template, 'courtneyr/archive-identity' )
				&& false !== strpos( $template, 'wp:query-title' )
				&& false !== strpos( $template, 'wp:term-description' )
				&& false !== strpos( $template, 'is-style-cr-marker-bar-short' );
		}
	);

	repost_template_case(
		'the template has no sample content and no Stream tape',
		static function () use ( $template ): bool {
			return false === stripos( $template, 'sample content' ) && false === strpos( $template, 'Stream ·' ) && false === strpos( $template, 'cr-tape-label' );
		}
	);

	repost_template_case(
		'the pattern is one inheriting Query with a month marker, a clipping card and the shipped copy',
		static function () use ( $all ): bool {
			$html = repost_pattern_html( $all );
			return 1 === substr_count( $html, '<!-- wp:query {"queryId":255,' )
				&& false !== strpos( $html, '"inherit":true' )
				&& false !== strpos( $html, '<!-- wp:post-template {"className":"cr-repost-zine__spreads"} -->' )
				&& false !== strpos( $html, '<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->' )
				&& false !== strpos( $html, '<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-repost-clipping"} /-->' )
				&& false !== strpos( $html, 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' )
				&& false === strpos( $html, 'wp:post-title' );
		}
	);

	repost_template_case(
		'the zine group carries neither the Stream collage class nor the archive-stream class',
		static function () use ( $all ): bool {
			$html = repost_pattern_html( $all );
			return false === strpos( $html, 'cr-stream-page' )
				&& false === strpos( $html, 'cr-archive-stream cr-' )
				&& false === strpos( $html, '"className":"cr-archive-stream ' )
				&& false !== strpos( $html, '<div class="wp-block-group alignwide cr-repost-zine">' )
				&& false !== strpos( $html, 'cr-repost-zine__query' );
		}
	);

	repost_template_case(
		'the pager and the empty state use the markup of the shipped archive loop',
		static function () use ( $all ): bool {
			$html    = repost_pattern_html( $all );
			$shipped = repost_pattern_html( array(), 'cr-archive-stream-loop' );
			$pager   = static function ( string $markup ): string {
				preg_match( '/<!-- wp:query-pagination .*?<!-- \/wp:query-pagination -->/s', $markup, $m );
				return preg_replace( '/\s+/', ' ', $m[0] ?? '' );
			};
			$empty   = static function ( string $markup ): string {
				preg_match( '/<!-- wp:query-no-results -->.*?<!-- \/wp:query-no-results -->/s', $markup, $m );
				return preg_replace( '/\s+/', ' ', $m[0] ?? '' );
			};
			$pager_html = $pager( $html );
			return '' !== $pager_html
				&& $pager_html === $pager( $shipped )
				&& false !== strpos( $pager_html, '"paginationArrow":"arrow"' )
				&& false !== strpos( $pager_html, '"justifyContent":"center"' )
				&& $empty( $html ) === $empty( $shipped )
				&& strpos( $html, 'wp:query-pagination' ) > strpos( $html, 'wp:query-no-results' );
		}
	);

	repost_template_case(
		'without the plugin blocks the pattern prints the post title and date instead',
		static function (): bool {
			foreach ( array( array(), array( 'post-kinds-indieweb/archive-sections' => true ), array( 'post-kinds-indieweb/stream-card' => true ) ) as $registered ) {
				$html = repost_pattern_html( $registered );
				if ( false !== strpos( $html, 'post-kinds-indieweb/' ) || false === strpos( $html, '<!-- wp:post-title {"level":3,"isLink":true} /-->' ) || false === strpos( $html, '<!-- wp:post-date /-->' ) ) {
					return false;
				}
			}
			return true;
		}
	);

	repost_template_case(
		'the template and the pattern use balanced block comments',
		static function () use ( $template, $all ): bool {
			foreach ( array( $template, repost_pattern_html( $all ) ) as $markup ) {
				$depth = 0;
				foreach ( repost_block_lines( $markup ) as $line ) {
					if ( 1 === preg_match( '/^<!--\s*\//', $line ) ) {
						--$depth;
					} elseif ( 1 !== preg_match( '/\/-->$/', $line ) ) {
						++$depth;
					}
					if ( $depth < 0 ) {
						return false;
					}
				}
				if ( 0 !== $depth ) {
					return false;
				}
			}
			return true;
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : $case_count . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
