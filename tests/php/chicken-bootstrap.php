<?php
/**
 * Shared standalone support for the chicken archive, single and Stream tests.
 *
 * Run a test with `php tests/php/chicken-test.php` (also `--no-plugin`). It
 * needs a WordPress checkout for the HTML API: CR_WP_CORE, or ~/.wp-tests/wordpress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_CHICKEN_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_chk_hooks']    = array();
	$GLOBALS['cr_chk_enqueued'] = array();
	$GLOBALS['cr_chk_failures'] = array();
	$GLOBALS['cr_chk_request']  = array();
	$GLOBALS['cr_chk_posts']    = array();
	$GLOBALS['cr_chk_facts']    = array();
	$GLOBALS['cr_chk_blocks']   = array();
	$GLOBALS['cr_chk_no_plugin'] = in_array( '--no-plugin', $argv ?? array(), true );

	class WP_Post {
		public int $ID;
		public string $post_title;

		/** Build a small post stand-in. */
		public function __construct( int $id = 42, string $title = 'Crossing the path' ) {
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

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_chk_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_chk_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Run recorded filter callbacks. */
	function apply_filters( $hook, $value, ...$args ) {
		return cr_chk_fire( (string) $hook, $value, ...$args );
	}

	/** Stub: block styles are irrelevant here. */
	function register_block_style( $block_name, $args ): void {}

	/** Current admin flag. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_chk_request']['admin'] );
	}

	/** Current taxonomy flag. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_chk_request']['kind_archive'] ?? '' );
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Current singular flag. */
	function is_singular( $post_type = '' ): bool {
		if ( ! empty( $post_type ) && 'post' !== $post_type ) {
			return false;
		}
		return ! empty( $GLOBALS['cr_chk_request']['single'] );
	}

	/** Kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = cr_chk_post_id( $post );
		$kinds = $GLOBALS['cr_chk_request']['post_kinds'][ $id ] ?? array( 'chicken' );
		return in_array( (string) $term, array_map( 'strval', (array) $kinds ), true );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['cr_chk_request']['queried_id'] ?? 42 );
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) ? (int) $post : get_queried_object_id();
		return $GLOBALS['cr_chk_posts'][ $id ] ?? null;
	}

	/** Current loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_chk_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Raw post field. */
	function get_post_field( $field, $post = null, $context = 'display' ): string {
		$found = get_post( $post );
		return $found instanceof WP_Post && 'post_title' === $field ? $found->post_title : '';
	}

	/** Test date. */
	function get_the_date( $format = '', $post = null ): string {
		return 'September 24, 2026';
	}

	/** Test time. */
	function get_the_time( $format = '', $post = null ): string {
		return '9:05 am';
	}

	/** Password protection flag. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_chk_request']['protected'][ cr_chk_post_id( $post ) ] );
	}

	/** Record an enqueued style. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_chk_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	/** Render a synthetic Core block from test fixtures. */
	function render_block( array $block ): string {
		$name = (string) ( $block['blockName'] ?? '' );
		$out  = $GLOBALS['cr_chk_blocks'][ $name ] ?? '';
		return is_callable( $out ) ? (string) $out( $block ) : (string) $out;
	}

	/** Escape text. */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape an attribute. */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape a URL. */
	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}

	/** Translate text. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Test permalink. */
	function get_permalink( $post = 0 ): string {
		return 'https://example.test/chicken/' . cr_chk_post_id( $post ) . '/';
	}

	/** Reset request state; returns the default chicken post. */
	function cr_chk_reset(): WP_Post {
		$post                       = new WP_Post();
		$GLOBALS['cr_chk_request']  = array(
			'kind_archive' => '',
			'single'       => false,
			'stream'       => false,
			'admin'        => false,
			'post_kinds'   => array( $post->ID => array( 'chicken' ) ),
			'protected'    => array(),
			'the_id'       => $post->ID,
			'queried_id'   => $post->ID,
		);
		$GLOBALS['cr_chk_posts']    = array( $post->ID => $post );
		$GLOBALS['cr_chk_facts']    = array( $post->ID => cr_chk_default_facts() );
		$GLOBALS['cr_chk_blocks']   = array();
		$GLOBALS['cr_chk_enqueued'] = array();
		return $post;
	}

	/** Default chicken facts: a titled post whose picture is the featured image. */
	function cr_chk_default_facts(): array {
		return array(
			'name'                => 'Crossing the path',
			'named'               => true,
			'kind_label'          => 'Chicken',
			'published'           => '2026-09-24T13:05:00+00:00',
			'url'                 => 'https://example.test/chicken/42/',
			'picture'             => array( 'source' => 'featured', 'attachment_id' => 7, 'url' => 'https://example.test/hen.jpg', 'alt' => 'A hen', 'remote' => false, 'suppress_featured' => true ),
			'featured_in_content' => false,
		);
	}

	/** Resolve a post ID from any accepted post value. */
	function cr_chk_post_id( $post = null ): int {
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		return is_numeric( $post ) && (int) $post > 0 ? (int) $post : get_queried_object_id();
	}

	/** Run one named group of checks. */
	function cr_chk_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_chk_failures'] );
		try {
			$checks();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_chk_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_chk_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Record an assertion. */
	function cr_chk_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_chk_failures'][] = $message;
		}
	}

	/** Assert strict equality. */
	function cr_chk_same( $expected, $actual, string $message ): void {
		cr_chk_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/** Assert a substring is present. */
	function cr_chk_contains( string $needle, string $haystack, string $message ): void {
		cr_chk_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	/** Assert a substring is absent. */
	function cr_chk_lacks( string $needle, string $haystack, string $message ): void {
		cr_chk_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Run callbacks registered to one hook, in priority order. */
	function cr_chk_fire( string $hook, $value = null, ...$args ) {
		$registered = array_values( array_filter( $GLOBALS['cr_chk_hooks'], static fn( array $entry ): bool => $entry[0] === $hook ) );
		usort( $registered, static fn( array $a, array $b ): int => $a[2] <=> $b[2] );
		$result = $value;
		foreach ( $registered as $entry ) {
			$params = array_slice( array_merge( array( $result ), $args ), 0, $entry[3] );
			$result = call_user_func_array( $entry[1], $params );
		}
		return $result;
	}

	/** Priorities a callback is registered at for one hook. */
	function cr_chk_priorities( string $hook, string $callback ): array {
		$found = array();
		foreach ( $GLOBALS['cr_chk_hooks'] as $entry ) {
			if ( $entry[0] === $hook && $entry[1] === $callback ) {
				$found[] = $entry[2];
			}
		}
		return $found;
	}

	/** Finish with a summary and exit code. */
	function cr_chk_finish(): void {
		if ( $GLOBALS['cr_chk_failures'] ) {
			foreach ( $GLOBALS['cr_chk_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			echo count( $GLOBALS['cr_chk_failures'] ) . " failed\n";
			exit( 1 );
		}
		echo "all passed\n";
		exit( 0 );
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

	cr_chk_reset();
}

namespace PKIW {
	if ( empty( $GLOBALS['cr_chk_no_plugin'] ) ) {
		/** Plugin facts stand-in. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_chk_facts'][ $post_id ] ?? array();
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Current stream surface flag. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_chk_request']['stream'] );
	}
}

namespace {
	require CR_CHICKEN_THEME_DIR . '/inc/stamps.php';
	require CR_CHICKEN_THEME_DIR . '/inc/stream-read.php';
	require CR_CHICKEN_THEME_DIR . '/inc/chicken.php';
	require CR_CHICKEN_THEME_DIR . '/inc/single-chicken.php';
	require CR_CHICKEN_THEME_DIR . '/inc/stream-chicken.php';
}
