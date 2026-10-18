<?php
/**
 * `php tests/php/aside-scrap-test.php`: the Stream Aside scrap renders one
 * link (W3-G2) with u-url on the Read more chip, without WordPress.
 *
 * Needs the HTML API from a WordPress checkout: CR_WP_CORE, or
 * ~/.wp-tests/wordpress. Exits 2 when it can't load it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_title;
		/** @var string */
		public $post_content;

		public function __construct( int $id, string $title, string $content ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
		}
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public $context;

		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	$GLOBALS['cr_posts'] = array();

	function add_filter( ...$args ) {
		return true;
	}
	function add_action( ...$args ) {
		return true;
	}
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
	function get_post( $id ) {
		return $GLOBALS['cr_posts'][ (int) $id ] ?? null;
	}
	function get_the_ID() {
		return 0;
	}
	function get_post_format( $post ) {
		return 'aside';
	}
	function get_the_terms( $post, $taxonomy ) {
		return array( (object) array( 'slug' => 'note' ) );
	}
	function is_wp_error( $thing ) {
		return false;
	}
	function get_the_title( $post ) {
		return $post->post_title;
	}
	function strip_shortcodes( $text ) {
		return $text;
	}
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return trim( strip_tags( (string) $text ) );
	}
	function esc_html__( $text, $domain = '' ) {
		return $text;
	}
	function is_singular( ...$args ) {
		return false;
	}

	function load_html_api(): bool {
		$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
		$api  = $core . '/wp-includes/html-api';
		if ( ! is_dir( $api ) ) {
			return false;
		}
		require_once $core . '/wp-includes/compat.php';
		require_once $core . '/wp-includes/utf8.php';
		require_once $core . '/wp-includes/kses.php';
		foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
			require_once $api . '/' . $file;
		}
		return class_exists( '\\WP_HTML_Tag_Processor' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return true;
	}
}

namespace Courtneyr\Child\AsideScrapTests {
	if ( ! \load_html_api() ) {
		echo "FAIL the HTML API could not load: set CR_WP_CORE to a WordPress checkout\n";
		exit( 2 );
	}

	$root = dirname( __DIR__, 2 );
	require_once $root . '/inc/stamps.php';
	require_once $root . '/inc/sticky-note.php';
	require_once $root . '/inc/aside-scrap.php';

	$failed = 0;

	function check( bool $ok, string $message ): void {
		global $failed;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $message . "\n";
		if ( ! $ok ) {
			++$failed;
		}
	}

	/** Rendered a[href] outside hidden subtrees. */
	function rendered_links( string $html ): int {
		$visible = (string) preg_replace( '#<(\w+)[^>]*\shidden[^>]*>.*?</\1>#s', '', $html );
		return preg_match_all( '#<a\b[^>]*\shref=#', $visible );
	}

	function card( int $id, string $title, string $heading_class ): string {
		return '<article class="pk-card pk-card--stream k-note h-entry"><span class="pk-kindlabel">Note</span>'
			. '<h2 class="' . $heading_class . '"><a class="u-url" href="https://example.test/p/' . $id . '">' . $title . '</a></h2>'
			. '<p class="pk-excerpt">Body text for post ' . $id . '.</p>'
			. '<div class="pk-meta"><a class="pk-link" href="https://example.test/p/' . $id . '">Read more</a></div>'
			. '<span class="pk-entry-props" hidden><span class="p-author h-card"><a class="u-url p-name" href="https://example.test/" tabindex="-1">Site</a></span></span></article>';
	}

	$cases = array(
		'none' => array( 11, '', 'Note, October 11, 2026', 'pk-title', 'Aside' ),
		'real' => array( 12, 'Margin thought', 'Margin thought', 'pk-title p-name', 'Margin thought' ),
		'dup'  => array( 13, 'Body text', 'Body text', 'pk-title p-name', 'Body text' ),
	);
	foreach ( $cases as $state => $case ) {
		list( $id, $stored, $printed, $heading_class, $heading_text ) = $case;
		$GLOBALS['cr_posts'][ $id ] = new \WP_Post( $id, $stored, 'Body text for post ' . $id . '.' );
		$out = \Courtneyr\Child\AsideScrap\stream_card( card( $id, $printed, $heading_class ), array(), new \WP_Block( array( 'postId' => $id ) ) );

		check( false !== strpos( $out, 'cr-scrap--' . $state ), "$state scrap gets its state class" );
		check( 1 === rendered_links( $out ), "$state scrap renders exactly one link" );
		check( 1 === preg_match( '#<a class="pk-link u-url" href="https://example\.test/p/' . $id . '">#', $out ), "$state scrap's Read more chip carries u-url" );
		check( 1 === preg_match( '#<h2 class="' . preg_quote( $heading_class, '#' ) . '">' . preg_quote( $heading_text, '#' ) . '</h2>#', $out ), "$state scrap heading reads '$heading_text' with no anchor" );
		check( false !== strpos( $out, 'tabindex="-1">Site</a>' ), "$state scrap keeps the hidden author h-card" );
	}

	$other = '<article class="pk-card pk-card--stream k-photo h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/p/20">Photo</a></h2></article>';
	check( $other === \Courtneyr\Child\AsideScrap\stream_card( $other, array(), new \WP_Block( array( 'postId' => 20 ) ) ), 'a non-text kind card is unchanged' );

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
