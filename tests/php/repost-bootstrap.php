<?php
/**
 * Shared standalone support for the repost archive, single and Stream tests.
 *
 * No WordPress: the globals below stand in for it. The plugin's real repost
 * card renderer and Stream card helpers load from tests/php/fixtures, and
 * WordPress's HTML API loads from the core checkout, so the adapters run
 * against the markup they meet on the site.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_RP_THEME_DIR', dirname( __DIR__, 2 ) );

	/** Mutable request and site state, reset by cr_rp_reset(). */
	$GLOBALS['cr_rp'] = array();

	/** Stand-in post. */
	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_title;
		/** @var string */
		public $post_content = '';

		/** @param int $id Post ID. @param string $title Post title. */
		public function __construct( int $id, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	/** Stand-in block instance. */
	class WP_Block {
		/** @var array<string, mixed> */
		public $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	/** Hooks, in the order registered. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_rp_hooks'][ $hook ][] = array( $callback, (int) $priority, (int) $accepted_args );
		return true;
	}

	/** Actions are hooks. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Run the filters registered on a hook, lowest priority first. */
	function cr_rp_filter( string $hook, $value, ...$args ) {
		$hooks = $GLOBALS['cr_rp_hooks'][ $hook ] ?? array();
		usort( $hooks, static fn( array $a, array $b ): int => $a[1] <=> $b[1] );
		foreach ( $hooks as $hook_entry ) {
			$all   = array_merge( array( $value ), $args );
			$value = call_user_func_array( $hook_entry[0], array_slice( $all, 0, $hook_entry[2] ) );
		}
		return $value;
	}

	/** Filters with the WordPress signature. */
	function apply_filters( $hook, $value, ...$args ) {
		return cr_rp_filter( (string) $hook, $value, ...$args );
	}

	/** Priority a callback is registered at, or false. */
	function cr_rp_priority( string $hook, string $callback ) {
		foreach ( $GLOBALS['cr_rp_hooks'][ $hook ] ?? array() as $entry ) {
			if ( $entry[0] === $callback ) {
				return $entry[1];
			}
		}
		return false;
	}

	function register_block_style( $block, $style ) {
		$GLOBALS['cr_rp']['styles'][] = array( $block, $style );
	}

	function wp_style_is( $handle, $status = 'enqueued' ) {
		return in_array( $handle, $GLOBALS['cr_rp']['registered_styles'], true );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_rp']['enqueued'][ $handle ] = array( $src, $deps, $version );
	}

	function is_admin() {
		return ! empty( $GLOBALS['cr_rp']['admin'] );
	}

	function is_tax( $taxonomy = '', $term = '' ) {
		return ! empty( $GLOBALS['cr_rp']['tax'] ) && 'kind' === $taxonomy && 'repost' === $term;
	}

	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_rp']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function is_feed() {
		return ! empty( $GLOBALS['cr_rp']['feed'] );
	}

	function in_the_loop() {
		return ! empty( $GLOBALS['cr_rp']['loop'] );
	}

	function doing_filter( $hook = null ) {
		return $hook === ( $GLOBALS['cr_rp']['doing'] ?? null );
	}

	function get_query_var( $name, $default = '' ) {
		return $GLOBALS['cr_rp']['query_vars'][ $name ] ?? $default;
	}

	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_rp']['queried'] ?? 0 );
	}

	function get_the_ID() {
		return (int) ( $GLOBALS['cr_rp']['global_post'] ?? 0 );
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? get_the_ID() : (int) $post;
		return $GLOBALS['cr_rp']['posts'][ $id ] ?? null;
	}

	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_rp']['posts'][ $id ]->post_title ?? '' );
	}

	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/repost/' . $id . '/';
	}

	function get_the_date( $format = '', $post = null ) {
		return 'October 14, 2026';
	}

	function get_post_time( $format = 'U', $gmt = false, $post = null ) {
		return '2026-10-14T12:00:00+00:00';
	}

	function has_term( $term, $taxonomy, $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'repost' === $term && ! empty( $GLOBALS['cr_rp']['reposts'][ $id ] );
	}

	function has_block( $block_name, $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return false !== strpos( (string) ( $GLOBALS['cr_rp']['posts'][ $id ]->post_content ?? '' ), 'wp:' . $block_name );
	}

	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_rp']['protected'][ $id ] );
	}

	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $text ) {
		return preg_match( '#^https?://#', (string) $text ) ? htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ) : '';
	}

	function esc_url_raw( $text ) {
		return preg_match( '#^https?://#', (string) $text ) ? (string) $text : '';
	}

	function __( $text, $domain = '' ) {
		return $text;
	}

	function esc_html__( $text, $domain = '' ) {
		return esc_html( $text );
	}

	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ) {
		$GLOBALS['cr_rp']['attachment_calls'][] = array( $attachment_id, $size, $attr );
		if ( ! empty( $GLOBALS['cr_rp']['no_attachment_image'] ) ) {
			return '';
		}
		$html = '<img src="https://example.test/uploads/' . (int) $attachment_id . '.png"';
		foreach ( $attr as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return $html . '>';
	}

	function get_block_wrapper_attributes( $extra = array() ) {
		return 'class="' . esc_attr( ( $extra['class'] ?? '' ) . ' wp-block-post-kinds-indieweb-repost-card' ) . '"';
	}

	/** The plugin's real repost card, rendered from its attributes. */
	function cr_rp_card_html( array $attributes ): string {
		ob_start();
		include CR_RP_THEME_DIR . '/tests/php/fixtures/repost-card-render.php';
		return (string) ob_get_clean();
	}

	/** Core's render_block(): the context takes the global post, then the block's filters run. */
	function render_block( $parsed_block ) {
		$GLOBALS['cr_rp']['rendered'][] = $parsed_block;
		$html                           = cr_rp_card_html( (array) ( $parsed_block['attrs'] ?? array() ) );
		$instance                       = new WP_Block( get_the_ID() ? array( 'postId' => get_the_ID() ) : array() );
		return cr_rp_filter( 'render_block_' . $parsed_block['blockName'], $html, $parsed_block, $instance );
	}

	/** WordPress's HTML API, from the core checkout the other theme tests use. */
	$cr_rp_core = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	foreach ( array( 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-token.php', 'class-wp-html-tag-processor.php' ) as $cr_rp_file ) {
		if ( ! is_file( $cr_rp_core . '/wp-includes/html-api/' . $cr_rp_file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $cr_rp_core . '/wp-includes/html-api/' . $cr_rp_file . "\n" );
			exit( 2 );
		}
		require_once $cr_rp_core . '/wp-includes/html-api/' . $cr_rp_file;
	}
	function wp_has_noncharacters( $text ) {
		return false;
	}
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_rp']['stream'] );
	}
}

namespace Courtneyr\Child\Microformats {
	function cited_title_is_h1( int $post_id ): bool {
		return ! empty( $GLOBALS['cr_rp']['h1'][ $post_id ] );
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_rp']['facts'][ $post_id ] ?? array() );
	}

	function get_kind_icon_svg( string $kind ): string {
		return '<svg class="pk-icon"></svg>';
	}

	function get_kind_label( string $label, string $kind, string $context ): string {
		return $GLOBALS['cr_rp']['labels'][ $context ] ?? $label;
	}

	function card_name_is_url( string $name, string $url ): bool {
		return '' !== trim( $name ) && trim( $name ) === trim( $url );
	}

	function url_host( string $url ): string {
		$host = (string) parse_url( $url, PHP_URL_HOST );
		return strtolower( (string) preg_replace( '/^www\./i', '', $host ) );
	}

	function card_wall_clock( string $value ): array {
		return '' === $value ? array( '', '' ) : array( $value, 'Oct 14, 2026' );
	}

	function pkiw_new_tab_label_attr( string $text ): string {
		return ' aria-label="' . esc_attr( $text . ' (opens in a new tab)' ) . '"';
	}

	/** The facts-backed hidden citation, as the plugin's repost cite builds it. */
	function stream_card_kind_cite( \WP_Post $post ): string {
		$facts = kind_facts( $post->ID );
		$url   = isset( $facts['url'] ) ? trim( (string) $facts['url'] ) : '';
		if ( '' === $url ) {
			return '';
		}
		return hidden_cite_html(
			'u-repost-of',
			array(
				'p-name'   => (string) ( $facts['cited_title'] ?? '' ),
				'p-author' => (string) ( $facts['author'] ?? '' ),
				'u-url'    => $url,
			)
		);
	}

	/** Grouped archive stand-in: page ids, current group and a group per post. */
	class Grouped_Archive {
		/** @return int[] */
		public static function current_page_ids(): array {
			return (array) ( $GLOBALS['cr_rp']['page_ids'] ?? array() );
		}

		public static function current_group() {
			$slug = $GLOBALS['cr_rp']['group_slug'] ?? null;
			return null === $slug ? null : new Test_Group( (string) $slug );
		}

		public static function group_of_post( string $kind, int $post_id ) {
			$key = $GLOBALS['cr_rp']['groups'][ $post_id ] ?? null;
			return null === $key ? null : new Test_Group( (string) $key );
		}
	}

	/** A group with a key and a slug. */
	class Test_Group {
		/** @var string */
		private $key;

		/** @param string $key Group key. */
		public function __construct( string $key ) {
			$this->key = $key;
		}

		public function key(): string {
			return $this->key;
		}

		public function slug(): string {
			return $this->key;
		}
	}

	require CR_RP_THEME_DIR . '/tests/php/fixtures/pkiw-stream-card-helpers.php';
}

namespace {
	require CR_RP_THEME_DIR . '/inc/stamps.php';
	require CR_RP_THEME_DIR . '/inc/repost.php';
	require CR_RP_THEME_DIR . '/inc/stream-repost.php';

	/** Reset the request and site state to a logged-out, empty site. */
	function cr_rp_reset(): void {
		$GLOBALS['cr_rp'] = array(
			'styles'            => array(),
			'registered_styles' => array(),
			'enqueued'          => array(),
			'attachment_calls'  => array(),
			'rendered'          => array(),
			'posts'             => array(),
			'reposts'           => array(),
			'facts'             => array(),
			'protected'         => array(),
			'h1'                => array(),
			'groups'            => array(),
			'page_ids'          => array(),
			'labels'            => array(),
			'query_vars'        => array(),
		);
	}

	/** Add a repost to the stand-in site. */
	function cr_rp_post( int $id, string $title = '', array $facts = array(), bool $repost = true ): WP_Post {
		$post                                  = new WP_Post( $id, $title );
		$GLOBALS['cr_rp']['posts'][ $id ]      = $post;
		$GLOBALS['cr_rp']['reposts'][ $id ]    = $repost;
		$GLOBALS['cr_rp']['facts'][ $id ]      = $facts;
		return $post;
	}

	/** Facts as the plugin's reader prints them, with the keys the theme reads. */
	function cr_rp_facts( array $override = array() ): array {
		return array_merge(
			array(
				'url'           => 'https://blog.example.net/paper-trails',
				'host'          => 'blog.example.net',
				'cited_title'   => 'Paper Trails for the Web',
				'author'        => 'Jo Example',
				'summary'       => 'A short summary of the cited post.',
				'picture'       => array(),
				'card_image'    => '',
				'name'          => 'Paper Trails for the Web',
				'name_is_title' => false,
			),
			$override
		);
	}

	/** The block Query Loop context for a post. */
	function cr_rp_instance( int $post_id ): WP_Block {
		return new WP_Block( array( 'postId' => $post_id ) );
	}

	$GLOBALS['cr_rp_failures'] = array();
	$GLOBALS['cr_rp_checks']   = 0;

	/** Run one named group of checks. */
	function cr_rp_group( string $name, callable $test ): void {
		cr_rp_reset();
		$before = count( $GLOBALS['cr_rp_failures'] );
		try {
			$test();
		} catch ( Throwable $error ) {
			$GLOBALS['cr_rp_failures'][] = $name . ' threw ' . $error->getMessage();
		}
		echo count( $GLOBALS['cr_rp_failures'] ) === $before ? 'PASS ' : 'FAIL ', $name, "\n";
	}

	/** Record a failed assertion. */
	function cr_rp_assert( bool $ok, string $message ): void {
		++$GLOBALS['cr_rp_checks'];
		if ( ! $ok ) {
			$GLOBALS['cr_rp_failures'][] = $message;
			echo '  - ', $message, "\n";
		}
	}

	/** Assert two values are identical. */
	function cr_rp_same( $expected, $actual, string $message ): void {
		cr_rp_assert( $expected === $actual, $message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
	}

	/** Assert a fragment contains a needle. */
	function cr_rp_contains( string $needle, string $haystack, string $message ): void {
		cr_rp_assert( false !== strpos( $haystack, $needle ), $message . ' (missing ' . $needle . ')' );
	}

	/** Assert a fragment lacks a needle. */
	function cr_rp_lacks( string $needle, string $haystack, string $message ): void {
		cr_rp_assert( false === strpos( $haystack, $needle ), $message . ' (found ' . $needle . ')' );
	}

	/** Assert one needle comes before another. */
	function cr_rp_before( string $first, string $second, string $haystack, string $message ): void {
		$a = strpos( $haystack, $first );
		$b = strpos( $haystack, $second );
		cr_rp_assert( false !== $a && false !== $b && $a < $b, $message . ' (' . $first . ' before ' . $second . ')' );
	}

	/** Count the anchors with an href that sit outside any hidden element. */
	function cr_rp_visible_links( string $html ): int {
		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return ( new DOMXPath( $doc ) )->query( '//a[@href][not(ancestor-or-self::*[@hidden])]' )->length;
	}

	/** Finish the run. */
	function cr_rp_finish(): void {
		$failed = count( $GLOBALS['cr_rp_failures'] );
		echo "\n", $failed ? $failed . ' failed' : $GLOBALS['cr_rp_checks'] . ' checks passed', "\n";
		exit( $failed ? 1 : 0 );
	}
}
