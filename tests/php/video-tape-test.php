<?php
/**
 * Standalone tests for the video archive tape adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_video_actions']   = array();
	$GLOBALS['cr_video_filters']   = array();
	$GLOBALS['cr_video_failures']  = array();
	$GLOBALS['cr_video_checks']    = 0;
	$GLOBALS['cr_video_posts']     = array();
	$GLOBALS['cr_video_facts']     = array();
	$GLOBALS['cr_video_kinds']     = array();
	$GLOBALS['cr_video_protected'] = array();
	$GLOBALS['cr_video_page_ids']  = array();
	$GLOBALS['cr_video_current']   = 0;
	$GLOBALS['cr_video_stream']    = false;

	class WP_Post {
		public int $ID;
		public string $post_title;

		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_video_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_video_filters'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function register_block_style( $block_name, $arguments ): void {
	}

	function __( $text, $domain = 'default' ): string {
		return (string) $text;
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}

	function is_admin(): bool {
		return false;
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && 'video' === $term;
	}

	function is_singular( $post_type = '' ): bool {
		return 'post' === $post_type;
	}

	function wp_enqueue_style( $handle, $source = '', $dependencies = array(), $version = false ): void {
	}

	function apply_filters( $hook, $value, ...$arguments ) {
		return $value;
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_video_current'];
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = (int) ( $post ?? $GLOBALS['cr_video_current'] );
		return $GLOBALS['cr_video_posts'][ $id ] ?? null;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'video' === (string) $term && ! empty( $GLOBALS['cr_video_kinds'][ $id ] );
	}

	function post_password_required( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_video_protected'][ $id ] );
	}

	function get_permalink( $post = 0 ): string {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/video-' . $id . '/';
	}

	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attributes = array() ): string {
		$html = '<img src="https://example.test/uploads/' . (int) $id . '.jpg"';
		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		return $html . '>';
	}

	/** Record one assertion. */
	function cr_video_assert( bool $condition, string $label ): void {
		++$GLOBALS['cr_video_checks'];
		if ( ! $condition ) {
			$GLOBALS['cr_video_failures'][] = $label;
		}
	}

	/** Run one named group. */
	function cr_video_group( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_video_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_video_failures'][] = $name . ' threw ' . $error->getMessage();
		}
		echo count( $GLOBALS['cr_video_failures'] ) === $before ? 'PASS ' : 'FAIL ', $name, "\n";
	}

	/** Finish the test process. */
	function cr_video_done(): void {
		foreach ( $GLOBALS['cr_video_failures'] as $failure ) {
			echo '  - ', $failure, "\n";
		}
		echo 'TOTAL ', $GLOBALS['cr_video_checks'], ' checks, ', count( $GLOBALS['cr_video_failures'] ), " failed\n";
		exit( empty( $GLOBALS['cr_video_failures'] ) ? 0 : 1 );
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
		return $GLOBALS['cr_video_facts'][ $post_id ] ?? array();
	}

	final class Grouped_Archive {
		/** @return int[] */
		public static function current_page_ids(): array {
			return $GLOBALS['cr_video_page_ids'];
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_video_stream'];
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
	require dirname( __DIR__, 2 ) . '/inc/video.php';

	use Courtneyr\Child\Video as Video;

	/** Plugin-shaped generic Stream card. */
	function cr_video_fixture( string $title = 'Original title' ): string {
		return '<article class="pk-card pk-card--stream k-video h-entry"><div class="pk-badge">badge</div><div class="pk-body">'
			. '<span class="pk-kindlabel">Video</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/original">' . esc_html( $title ) . '</a></h3>'
			. '<p class="pk-sub pk-stream-date"><time class="dt-published">September 18, 2026</time></p></div>'
			. '<div class="pk-media"><video autoplay><button>Play</button></video></div><p class="pk-excerpt">Excerpt</p>'
			. '<span class="pk-kind-cite" hidden><data class="p-name" value="Citation"></data></span><div class="pk-meta"><a class="pk-link" href="https://example.test/more">Read more</a></div></div>'
			. '<span class="pk-entry-props" hidden><time class="dt-published" datetime="2026-09-18T10:00:00+00:00"></time><data class="u-video" value="https://example.test/video.mp4"></data>'
			. '<a class="u-author" href="https://example.test/author" tabindex="-1">Author</a></span></article>';
	}

	/** Plugin-shaped protected Stream card. */
	function cr_video_protected_fixture( string $title = 'Private video' ): string {
		return '<article class="pk-card pk-card--stream pk-card--protected k-video h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/private">' . esc_html( $title ) . '</a></h3></article>';
	}

	/** Video facts fixture. */
	function cr_video_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'picture'         => array(
					'source'        => 'featured',
					'attachment_id' => 81,
					'url'           => 'https://example.test/uploads/81.jpg',
					'alt'           => 'Ignored alt',
				),
				'runtime_seconds' => 134,
				'runtime_iso'     => 'PT2M14S',
				'runtime_clock'   => '2:14',
				'runtime_human'   => '2 minutes, 14 seconds',
				'name'            => 'Tape One',
				'name_is_title'   => true,
				'date_display'    => 'September 18, 2026',
			),
			$overrides
		);
	}

	/** Reset one simulated request. */
	function cr_video_reset(): void {
		$GLOBALS['cr_video_posts']     = array();
		$GLOBALS['cr_video_facts']     = array();
		$GLOBALS['cr_video_kinds']     = array();
		$GLOBALS['cr_video_protected'] = array();
		$GLOBALS['cr_video_page_ids']  = array();
		$GLOBALS['cr_video_current']   = 0;
		Video\reset_page_state();
	}

	/** Register one fixture post. */
	function cr_video_post( int $id, string $title, array $facts, bool $protected = false, bool $is_video = true ): WP_Post {
		$post                                  = new WP_Post( $id, $title );
		$GLOBALS['cr_video_posts'][ $id ]      = $post;
		$GLOBALS['cr_video_facts'][ $id ]      = $facts;
		$GLOBALS['cr_video_kinds'][ $id ]      = $is_video;
		$GLOBALS['cr_video_protected'][ $id ]  = $protected;
		$GLOBALS['cr_video_page_ids'][]        = $id;
		$GLOBALS['cr_video_current']           = $id;
		return $post;
	}

	/** Render one styled fixture. */
	function cr_video_render( int $id, string $html ): string {
		$GLOBALS['cr_video_current'] = $id;
		return Video\tape_card( $html, array( 'attrs' => array( 'className' => 'is-style-cr-shelf-tape' ) ), new WP_Block( array( 'postId' => $id ) ) );
	}

	/** Parse a fragment into XPath. */
	function cr_video_xpath( string $html ): DOMXPath {
		$prior = libxml_use_internal_errors( true );
		$doc   = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="scope">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prior );
		return new DOMXPath( $doc );
	}

	cr_video_group(
		'full tape markup and semantics',
		static function (): void {
			cr_video_reset();
			cr_video_post( 1, 'Tape One', cr_video_facts() );
			$out   = cr_video_render( 1, cr_video_fixture() );
			$xpath = cr_video_xpath( $out );
			cr_video_assert( 1 === $xpath->query( '//article[contains(concat(" ", normalize-space(@class), " "), " cr-shelf-tape ")]' )->length, 'one tape article' );
			foreach ( array( 'pk-card', 'pk-card--stream', 'k-video', 'h-entry' ) as $class_name ) {
				cr_video_assert( str_contains( $out, $class_name ), 'article keeps ' . $class_name );
			}
			cr_video_assert( 1 === $xpath->query( '//a[@href and not(ancestor-or-self::*[@hidden])]' )->length, 'one rendered link' );
			cr_video_assert( 0 === $xpath->query( '//a[@tabindex="-1" and not(ancestor::*[@hidden])]' )->length, 'hidden links stay under hidden' );
			cr_video_assert( 1 === substr_count( $out, 'Tape One' ), 'title text appears once' );
			cr_video_assert( 1 === $xpath->query( '//time[@datetime="PT2M14S" and contains(., "2 minutes, 14 seconds")]' )->length, 'runtime has ISO and human text' );
			cr_video_assert( 1 === $xpath->query( '//img[@alt="" and @loading="eager" and @fetchpriority="high" and @data-skip-lazy="1"]' )->length, 'first tape picture is eager and prioritized' );
			cr_video_assert( 0 === $xpath->query( '//img[contains(concat(" ", normalize-space(@class), " "), " u-photo ")]' )->length, 'picture is not u-photo' );
			cr_video_assert( str_contains( $out, 'pk-kind-cite' ) && str_contains( $out, 'pk-entry-props' ), 'hidden plugin properties remain' );
			foreach ( array( '<video', '<iframe', '<button', 'autoplay', 'able', 'Mini DV', 'Hi8', 'DIGITAL VIDEO CASSETTE', '60 SP', '120' ) as $forbidden ) {
				cr_video_assert( ! str_contains( $out, $forbidden ), 'output omits ' . $forbidden );
			}
		}
	);

	cr_video_group(
		'picture loading counts every tape',
		static function (): void {
			cr_video_reset();
			$output = array();
			for ( $id = 1; $id <= 4; ++$id ) {
				cr_video_post( $id, 'Tape ' . $id, cr_video_facts( array( 'name' => 'Tape ' . $id ) ) );
			}
			for ( $id = 1; $id <= 4; ++$id ) {
				$output[ $id ] = cr_video_render( $id, cr_video_fixture( 'Tape ' . $id ) );
			}
			for ( $id = 1; $id <= 3; ++$id ) {
				cr_video_assert( str_contains( $output[ $id ], 'loading="eager"' ) && str_contains( $output[ $id ], 'fetchpriority="high"' ), 'tape ' . $id . ' is eager' );
			}
			cr_video_assert( str_contains( $output[4], 'loading="lazy"' ), 'fourth tape is lazy' );
			cr_video_assert( ! str_contains( $output[4], 'fetchpriority' ), 'fourth tape has no priority hint' );
		}
	);

	cr_video_group(
		'empty picture and runtime states',
		static function (): void {
			cr_video_reset();
			$no_picture = cr_video_facts( array( 'picture' => array( 'source' => '', 'attachment_id' => 0 ) ) );
			cr_video_post( 3, 'No picture', $no_picture );
			$out   = cr_video_render( 3, cr_video_fixture( 'No picture' ) );
			$xpath = cr_video_xpath( $out );
			cr_video_assert( 0 === $xpath->query( '//img' )->length, 'no picture prints no image' );
			cr_video_assert( 0 === $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " cr-shelf-tape__strip ")]' )->length, 'no picture prints no strip' );
			cr_video_assert( 1 === $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " cr-shelf-tape__window ") and not(*) and normalize-space(.)=""]' )->length, 'empty window has no placeholder' );

			foreach ( array( 4, 5, 6 ) as $id ) {
				cr_video_reset();
				cr_video_post( $id, 'No runtime', cr_video_facts( array( 'runtime_seconds' => 0, 'runtime_iso' => '', 'runtime_clock' => '', 'runtime_human' => '' ) ) );
				$without = cr_video_render( $id, cr_video_fixture( 'No runtime' ) );
				cr_video_assert( ! str_contains( $without, 'cr-shelf-tape__runtime' ) && ! str_contains( $without, '<time class="cr-shelf-tape' ), 'case ' . $id . ' has no runtime slot' );
			}
		}
	);

	cr_video_group(
		'duplicate and untitled names',
		static function (): void {
			cr_video_reset();
			cr_video_post( 9, 'Same title', cr_video_facts( array( 'name' => 'Same title', 'date_display' => 'September 9, 2026' ) ) );
			cr_video_post( 10, 'Same title', cr_video_facts( array( 'name' => 'Same title', 'date_display' => 'September 10, 2026' ) ) );
			cr_video_post( 11, 'Unique title', cr_video_facts( array( 'name' => 'Unique title', 'date_display' => 'September 11, 2026' ) ) );
			$nine   = cr_video_render( 9, cr_video_fixture( 'Same title' ) );
			$ten    = cr_video_render( 10, cr_video_fixture( 'Same title' ) );
			$unique = cr_video_render( 11, cr_video_fixture( 'Unique title' ) );
			cr_video_assert( str_contains( $nine, '<span class="pk-sr-only">, September 9, 2026</span>' ), 'first duplicate carries its date' );
			cr_video_assert( str_contains( $ten, '<span class="pk-sr-only">, September 10, 2026</span>' ), 'second duplicate carries its date' );
			cr_video_assert( ! str_contains( $unique, '<span class="pk-sr-only">, September 11, 2026</span>' ), 'unique name omits date suffix' );

			cr_video_reset();
			cr_video_post( 8, '', cr_video_facts( array( 'name' => 'Video, September 8, 2026', 'name_is_title' => false ) ) );
			$untitled = cr_video_render( 8, cr_video_fixture( 'Video' ) );
			cr_video_assert( str_contains( $untitled, '<h3 class="pk-title">' ), 'untitled heading remains' );
			cr_video_assert( ! str_contains( $untitled, 'pk-title p-name' ), 'untitled heading omits p-name' );
		}
	);

	cr_video_group(
		'protected and empty facts',
		static function (): void {
			cr_video_reset();
			cr_video_post( 20, 'Private video', array(), true );
			$protected = cr_video_render( 20, cr_video_protected_fixture() );
			cr_video_assert( str_contains( $protected, 'cr-shelf-tape cr-shelf-tape--protected' ), 'protected tape classes' );
			cr_video_assert( 1 === substr_count( $protected, '<a ' ) && str_contains( $protected, 'Private video' ), 'protected tape keeps its title link' );
			foreach ( array( '<img', 'cr-shelf-tape__strip', 'cr-shelf-tape__runtime' ) as $forbidden ) {
				cr_video_assert( ! str_contains( $protected, $forbidden ), 'protected tape omits ' . $forbidden );
			}

			cr_video_reset();
			cr_video_post( 21, 'Plugin heading', array() );
			$empty = cr_video_render( 21, cr_video_fixture( 'Plugin heading' ) );
			cr_video_assert( str_contains( $empty, '<h3 class="pk-title p-name"><a class="u-url" href="https://example.test/original">Plugin heading</a></h3>' ), 'empty facts preserve plugin heading' );
			cr_video_assert( ! str_contains( $empty, '<img' ) && ! str_contains( $empty, 'cr-shelf-tape__runtime' ), 'empty facts add no picture or runtime' );
			cr_video_assert( ! str_contains( $empty, 'cr-shelf-tape--protected' ), 'empty facts are not marked protected' );
		}
	);

	cr_video_group(
		'gates and facts-only source policy',
		static function (): void {
			cr_video_reset();
			cr_video_post( 30, 'Gate', cr_video_facts() );
			$input = cr_video_fixture( 'Gate' );
			$plain = Video\tape_card( $input, array(), new WP_Block( array( 'postId' => 30 ) ) );
			cr_video_assert( $input === $plain, 'unstyled card is byte-identical' );
			$GLOBALS['cr_video_kinds'][30] = false;
			$other = cr_video_render( 30, $input );
			cr_video_assert( $input === $other, 'non-video card is byte-identical' );

			$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/video.php' );
			preg_match_all( '/attrs|get_post_meta|parse_blocks|untitled_name|kind_picture/', $source, $matches, PREG_OFFSET_CAPTURE );
			cr_video_assert( 1 === count( $matches[0] ), 'source policy finds one permitted term' );
			if ( isset( $matches[0][0] ) ) {
				$line_start = strrpos( substr( $source, 0, (int) $matches[0][0][1] ), "\n" );
				$line_end   = strpos( $source, "\n", (int) $matches[0][0][1] );
				$line       = substr( $source, false === $line_start ? 0 : $line_start + 1, ( false === $line_end ? strlen( $source ) : $line_end ) - ( false === $line_start ? 0 : $line_start + 1 ) );
				cr_video_assert( 'attrs' === $matches[0][0][0] && str_contains( $line, 'className' ), 'the one permitted term is the className gate' );
			}
		}
	);

	cr_video_done();
}
