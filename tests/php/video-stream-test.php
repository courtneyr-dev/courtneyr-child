<?php
/**
 * Standalone tests for the video Stream tape spine.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_stream_filters']  = array();
	$GLOBALS['cr_stream_failures'] = array();
	$GLOBALS['cr_stream_checks']   = 0;
	$GLOBALS['cr_stream_facts']    = array();
	$GLOBALS['cr_stream_kinds']    = array();
	$GLOBALS['cr_stream_current']  = 0;
	$GLOBALS['cr_stream_surface']  = true;

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_arguments = 1 ): void {
		$GLOBALS['cr_stream_filters'][] = array( $hook, $callback, $priority, $accepted_arguments );
	}

	function apply_filters( $hook, $value, ...$arguments ) {
		return $value;
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_stream_current'];
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		return 'kind' === $taxonomy && 'video' === (string) $term && ! empty( $GLOBALS['cr_stream_kinds'][ (int) $post ] );
	}

	function cr_stream_assert( bool $condition, string $label ): void {
		++$GLOBALS['cr_stream_checks'];
		if ( ! $condition ) {
			$GLOBALS['cr_stream_failures'][] = $label;
		}
	}

	function cr_stream_group( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_stream_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_stream_failures'][] = $name . ' threw ' . $error->getMessage();
		}
		echo count( $GLOBALS['cr_stream_failures'] ) === $before ? 'PASS ' : 'FAIL ', $name, "\n";
	}

	function cr_stream_done(): void {
		foreach ( $GLOBALS['cr_stream_failures'] as $failure ) {
			echo '  - ', $failure, "\n";
		}
		echo 'TOTAL ', $GLOBALS['cr_stream_checks'], ' checks, ', count( $GLOBALS['cr_stream_failures'] ), " failed\n";
		exit( empty( $GLOBALS['cr_stream_failures'] ) ? 0 : 1 );
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		echo "SKIP: set CR_WP_CORE to a WordPress checkout\n";
		exit( 0 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_stream_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream_surface'];
	}
}

namespace Courtneyr\Child\MediaShelf {
	/** Remove every element of one tag carrying one class. */
	function cut_elements( string $html, string $tag, string $class_name ): string {
		$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
		while ( preg_match( $open, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$start = (int) $match[0][1];
			$pos   = $start + strlen( $match[0][0] );
			$depth = 1;
			while ( 0 < $depth && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $tag_match, PREG_OFFSET_CAPTURE, $pos ) ) {
				$pos    = (int) $tag_match[0][1] + strlen( $tag_match[0][0] );
				$depth += '/' === $tag_match[1][0] ? -1 : 1;
			}
			if ( 0 < $depth ) {
				return $html;
			}
			$html = substr( $html, 0, $start ) . substr( $html, $pos );
		}
		return $html;
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/stream-video.php';

	use Courtneyr\Child\StreamVideo as Stream;

	/** Plugin-shaped generic Stream card. */
	function cr_stream_fixture( string $title = 'Seed Spine Local', bool $named = true ): string {
		$title_class = $named ? 'pk-title p-name' : 'pk-title';
		return '<article class="pk-card pk-card--stream k-video h-entry"><div class="pk-badge">badge</div><div class="pk-body">'
			. '<span class="pk-kindlabel">Video</span><div class="pk-caption"><h3 class="' . $title_class . '"><a class="u-url" href="https://example.test/video">' . esc_html( $title ) . '</a></h3>'
			. '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-18T10:00:00+00:00">September 18, 2026</time></p></div>'
			. '<div class="pk-media"><img src="https://example.test/poster.jpg" alt=""></div><p class="pk-excerpt">Excerpt text.</p>'
			. '<span class="pk-kind-cite" hidden><data value="citation"></data></span><div class="pk-meta"><a class="pk-link" href="https://example.test/more">Read more →</a></div></div>'
			. '<span class="pk-entry-props" hidden><data class="u-url" value="https://example.test/video"></data><data class="dt-published" value="2026-09-18T10:00:00+00:00"></data><data class="u-video" value="https://example.test/media.mp4"></data></span></article>';
	}

	/** Plugin-shaped protected Stream card. */
	function cr_stream_protected(): string {
		return '<article class="pk-card pk-card--stream pk-card--protected k-video h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/locked">Protected: Seed Locked Reel</a></h3></article>';
	}

	/** Runtime facts fixture. */
	function cr_stream_facts( array $changes = array() ): array {
		return array_merge(
			array(
				'runtime_seconds' => 134,
				'runtime_iso'     => 'PT2M14S',
				'runtime_clock'   => '2:14',
				'runtime_human'   => '2 minutes, 14 seconds',
			),
			$changes
		);
	}

	/** Register and select one Stream fixture. */
	function cr_stream_post( int $id, array $facts, bool $video = true ): void {
		$GLOBALS['cr_stream_current']     = $id;
		$GLOBALS['cr_stream_facts'][ $id ] = $facts;
		$GLOBALS['cr_stream_kinds'][ $id ] = $video;
		$GLOBALS['cr_stream_surface']      = true;
	}

	/** Render one card with a post context. */
	function cr_stream_render( int $id, string $html ): string {
		return Stream\spine_card( $html, array(), new WP_Block( array( 'postId' => $id ) ) );
	}

	/** Parse one HTML fragment. */
	function cr_stream_xpath( string $html ): \DOMXPath {
		$prior = libxml_use_internal_errors( true );
		$doc   = new \DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="scope">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prior );
		return new \DOMXPath( $doc );
	}

	cr_stream_group(
		'local video keeps semantic text and becomes one spine',
		static function (): void {
			cr_stream_post( 1, cr_stream_facts() );
			$out   = cr_stream_render( 1, cr_stream_fixture() );
			$xpath = cr_stream_xpath( $out );
			cr_stream_assert( 1 === $xpath->query( '//article[contains(concat(" ", normalize-space(@class), " "), " cr-tape-spine ")]' )->length, 'article has the spine class' );
			cr_stream_assert( 1 === $xpath->query( '//article/*[1][self::span and contains(concat(" ", normalize-space(@class), " "), " cr-tape-spine__strip ") and @aria-hidden="true"]' )->length, 'one hidden strip is first' );
			cr_stream_assert( str_contains( $out, '<span class="pk-kindlabel">Video</span>' ), 'kind label remains' );
			cr_stream_assert( str_contains( $out, 'Seed Spine Local' ) && str_contains( $out, 'pk-stream-date' ), 'title and date remain' );
			cr_stream_assert( str_contains( $out, '<time class="cr-tape-spine__runtime" datetime="PT2M14S">2:14<span class="pk-sr-only"> (2 minutes, 14 seconds)</span></time>' ), 'runtime follows the date' );
			cr_stream_assert( strpos( $out, 'pk-stream-date' ) < strpos( $out, 'cr-tape-spine__runtime' ), 'runtime order is after the date' );
			cr_stream_assert( str_contains( $out, 'pk-entry-props' ) && str_contains( $out, 'class="u-video"' ), 'hidden entry properties remain' );
			cr_stream_assert( 1 === $xpath->query( '//a[@href and not(ancestor-or-self::*[@hidden])]' )->length, 'one rendered link remains' );
			foreach ( array( '<img', 'Excerpt text.', 'pk-meta', 'Read more', '→', '<video', '<iframe', '<button', 'able' ) as $forbidden ) {
				cr_stream_assert( ! str_contains( $out, $forbidden ), 'output omits ' . $forbidden );
			}
		}
	);

	cr_stream_group(
		'embed, missing runtime and untitled cases preserve plugin choices',
		static function (): void {
			foreach ( array( 2, 3 ) as $id ) {
				cr_stream_post( $id, cr_stream_facts( array( 'runtime_seconds' => 0, 'runtime_iso' => '', 'runtime_clock' => '', 'runtime_human' => '' ) ) );
				cr_stream_assert( ! str_contains( cr_stream_render( $id, cr_stream_fixture( 'No runtime' ) ), 'cr-tape-spine__runtime' ), 'case ' . $id . ' has no runtime element' );
			}

			cr_stream_post( 4, cr_stream_facts() );
			$untitled = cr_stream_render( 4, cr_stream_fixture( 'Video<span class="pk-sr-only">, September 18, 2026</span>', false ) );
			cr_stream_assert( str_contains( $untitled, '<h3 class="pk-title">' ) && ! str_contains( $untitled, 'pk-title p-name' ), 'untitled heading stays without p-name' );

			$title = str_repeat( 'Long title ', 6 );
			$title = substr( $title, 0, 60 );
			cr_stream_post( 5, cr_stream_facts() );
			$long = cr_stream_render( 5, cr_stream_fixture( $title ) );
			cr_stream_assert( 1 === substr_count( $long, $title ) && 1 === substr_count( $long, '<a class="u-url"' ), '60-character title stays inside one link' );
		}
	);

	cr_stream_group(
		'protected card receives only the spine class',
		static function (): void {
			cr_stream_post( 6, array() );
			$out = cr_stream_render( 6, cr_stream_protected() );
			cr_stream_assert( str_contains( $out, 'pk-card--protected' ) && str_contains( $out, 'cr-tape-spine' ), 'protected card gets the spine class' );
			cr_stream_assert( 1 === substr_count( $out, '<a ' ) && str_contains( $out, 'Protected: Seed Locked Reel' ), 'protected title link remains' );
			cr_stream_assert( ! str_contains( $out, 'cr-tape-spine__strip' ) && ! str_contains( $out, 'cr-tape-spine__runtime' ), 'protected card gets no strip or runtime' );
		}
	);

	cr_stream_group(
		'surface, kind and archive ownership gates return byte-identical HTML',
		static function (): void {
			cr_stream_post( 7, cr_stream_facts() );
			$input = cr_stream_fixture( 'Gate' );
			$GLOBALS['cr_stream_surface'] = false;
			cr_stream_assert( $input === cr_stream_render( 7, $input ), 'off Stream stays unchanged' );

			cr_stream_post( 8, cr_stream_facts(), false );
			cr_stream_assert( $input === cr_stream_render( 8, $input ), 'non-video stays unchanged' );

			cr_stream_post( 9, cr_stream_facts() );
			$tape = str_replace( 'k-video', 'k-video cr-shelf-tape', $input );
			cr_stream_assert( $tape === cr_stream_render( 9, $tape ), 'a card the archive tape already shaped stays unchanged' );

			cr_stream_post( 10, cr_stream_facts() );
			$GLOBALS['cr_stream_surface'] = true;
			cr_stream_assert( str_contains( cr_stream_render( 10, $input ), 'cr-tape-spine' ), 'editor preview Stream surface receives the spine' );
		}
	);

	cr_stream_group(
		'hook and source policy are exact',
		static function (): void {
			$hook = array_values( array_filter( $GLOBALS['cr_stream_filters'], static fn( array $item ): bool => 'render_block_post-kinds-indieweb/stream-card' === $item[0] && 11 === $item[2] && 3 === $item[3] ) );
			cr_stream_assert( 1 === count( $hook ), 'Stream hook uses priority 11 and three arguments' );
			$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/stream-video.php' );
			cr_stream_assert( 0 === preg_match( '/attrs|get_post_meta|parse_blocks|untitled_name|kind_picture/', $source ), 'facts-only source scan is empty' );
		}
	);

	cr_stream_done();
}
