<?php
/**
 * Shared standalone support for the jam archive, single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_JAM_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_jam_hooks']        = array();
	$GLOBALS['cr_jam_enqueued']     = array();
	$GLOBALS['cr_jam_failures']     = array();
	$GLOBALS['cr_jam_request']      = array();
	$GLOBALS['cr_jam_posts']        = array();
	$GLOBALS['cr_jam_facts']        = array();
	$GLOBALS['cr_jam_block_styles'] = array();
	$GLOBALS['cr_jam_no_plugin']    = in_array( '--no-plugin', $argv ?? array(), true );

	/** Post stand-in. */
	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;

		/** Build a small post stand-in. */
		public function __construct( int $id = 42, string $title = 'Electric Morning', string $content = '' ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
		}
	}

	/** Block instance stand-in. */
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
		$GLOBALS['cr_jam_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_jam_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Run registered callbacks in priority order. */
	function apply_filters( string $hook, $value = null, ...$args ) {
		$registered = array_values(
			array_filter(
				$GLOBALS['cr_jam_hooks'],
				static fn( array $entry ): bool => $entry[0] === $hook
			)
		);
		usort( $registered, static fn( array $a, array $b ): int => $a[2] <=> $b[2] );
		foreach ( $registered as $entry ) {
			$params = array_slice( array_merge( array( $value ), $args ), 0, $entry[3] );
			$value  = call_user_func_array( $entry[1], $params );
		}
		return $value;
	}

	/** Record an accidental block-style registration. */
	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_jam_block_styles'][] = array( $block_name, $args );
	}

	/** Admin flag. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_jam_request']['admin'] );
	}

	/** Taxonomy archive flag. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_jam_request']['kind_archive'] ?? '' );
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Singular flag. */
	function is_singular( $post_type = '' ): bool {
		if ( '' !== $post_type && 'post' !== $post_type ) {
			return false;
		}
		return ! empty( $GLOBALS['cr_jam_request']['single'] );
	}

	/** Kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = cr_jam_post_id( $post );
		$kinds = $GLOBALS['cr_jam_request']['post_kinds'][ $id ] ?? array( 'jam' );
		return (bool) array_intersect( array_map( 'strval', (array) $term ), array_map( 'strval', (array) $kinds ) );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['cr_jam_request']['queried_id'] ?? 0 );
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) && (int) $post > 0 ? (int) $post : (int) ( $GLOBALS['cr_jam_request']['the_id'] ?? get_queried_object_id() );
		return $GLOBALS['cr_jam_posts'][ $id ] ?? null;
	}

	/** Current loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_jam_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Password protection flag. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_jam_request']['protected'][ cr_jam_post_id( $post ) ] );
	}

	/** Query variable stand-in. */
	function get_query_var( $key, $default = '' ) {
		return $GLOBALS['cr_jam_request']['query_vars'][ (string) $key ] ?? $default;
	}

	/** Record an enqueued stylesheet. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_jam_enqueued'][] = array( $handle, $src, $deps, $version );
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

	/** Translate and escape text. */
	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
	}

	/** Translate and print escaped text. */
	function esc_html_e( $text, $domain = null ): void {
		echo esc_html__( $text, $domain );
	}

	/** Strip tags. */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	/** Attachment markup from the image values the theme supplies. */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		if ( ! empty( $GLOBALS['cr_jam_request']['missing_attachments'][ (int) $attachment_id ] ) ) {
			return '';
		}
		$attributes = array_merge(
			array( 'src' => 'https://example.test/uploads/attachment-' . (int) $attachment_id . '-' . $size . '.jpg' ),
			(array) $attr
		);
		$out        = '<img';
		foreach ( $attributes as $name => $value ) {
			$out .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out . ' />';
	}

	/** Test permalink. */
	function get_permalink( $post = 0 ): string {
		return 'https://example.test/jam/' . cr_jam_post_id( $post ) . '/';
	}

	/** Resolve a post ID from any accepted post value. */
	function cr_jam_post_id( $post = null ): int {
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		if ( is_numeric( $post ) && (int) $post > 0 ) {
			return (int) $post;
		}
		return (int) ( $GLOBALS['cr_jam_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Default facts for a complete jam card. */
	function cr_jam_facts( array $overrides = array() ): array {
		return array_replace_recursive(
			array(
				'has_card'           => true,
				'title'              => 'Electric Morning',
				'artist'             => 'The Paper Satellites',
				'album'              => 'Signal Bloom',
				'url'                => 'https://music.example/track',
				'url_source'         => 'card',
				'note'               => 'Bright synths for an early start.',
				'rel'                => 'enclosure',
				'cover_alt'          => 'Blue mountain album cover',
				'cover_alt_fallback' => 'Electric Morning by The Paper Satellites',
				'picture'            => array(
					'source'            => 'cover',
					'attachment_id'     => 901,
					'url'               => 'https://example.test/uploads/cover.jpg',
					'alt'               => 'Blue mountain album cover',
					'remote'            => false,
					'suppress_featured' => false,
				),
				'jammed'             => '2026-09-27',
				'jammed_display'     => 'September 27, 2026',
				'date'               => '2026-09-27',
				'date_display'       => 'September 27, 2026',
				'date_is_stored'     => true,
				'name'               => 'Electric Morning',
				'name_source'        => 'track',
				'name_is_title'      => false,
				'track_is_h1'        => false,
			),
			$overrides
		);
	}

	/** Reset request state and register one default jam post. */
	function cr_jam_reset( int $post_id = 42, string $title = 'Jam journal entry' ): WP_Post {
		$post                         = new WP_Post( $post_id, $title );
		$GLOBALS['cr_jam_request']    = array(
			'kind_archive'       => '',
			'single'             => false,
			'stream'             => false,
			'stream_rendering'   => false,
			'admin'              => false,
			'post_kinds'         => array( $post_id => array( 'jam' ) ),
			'protected'          => array(),
			'missing_attachments' => array(),
			'query_vars'         => array( 'paged' => 1 ),
			'the_id'             => $post_id,
			'queried_id'         => $post_id,
		);
		$GLOBALS['cr_jam_posts']      = array( $post_id => $post );
		$GLOBALS['cr_jam_facts']      = array( $post_id => cr_jam_facts() );
		$GLOBALS['cr_jam_enqueued']   = array();
		\Courtneyr\Child\Jam\reset_page_state();
		return $post;
	}

	/** Print one grouped result. */
	function cr_jam_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_jam_failures'] );
		try {
			$checks();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_jam_failures'][] = $name . ': ' . $error->getMessage();
		}
		$ok = $before === count( $GLOBALS['cr_jam_failures'] );
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Record an assertion. */
	function cr_jam_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_jam_failures'][] = $message;
		}
	}

	/** Assert strict equality. */
	function cr_jam_same( $expected, $actual, string $message ): void {
		cr_jam_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/** Assert a string contains a substring. */
	function cr_jam_contains( string $needle, string $haystack, string $message ): void {
		cr_jam_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	/** Assert a string does not contain a substring. */
	function cr_jam_not_contains( string $needle, string $haystack, string $message ): void {
		cr_jam_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Whether a hook has the requested registration. */
	function cr_jam_hooked( string $hook, string $callback, int $priority = 10, int $args = 1 ): bool {
		foreach ( $GLOBALS['cr_jam_hooks'] as $entry ) {
			if ( $entry[0] === $hook && $entry[1] === $callback && $entry[2] === $priority && $entry[3] === $args ) {
				return true;
			}
		}
		return false;
	}

	/** Anchors with href outside a hidden subtree. */
	function cr_jam_rendered_anchors( string $html ): int {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );
		return $xpath->query( '//a[@href][not(ancestor-or-self::*[@hidden])]' )->length;
	}

	/** Negative-tabindex anchors outside a hidden subtree. */
	function cr_jam_visible_negative_tabindex( string $html ): int {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );
		return $xpath->query( "//a[@tabindex='-1'][not(ancestor-or-self::*[@hidden])]" )->length;
	}

	/** Finish with a summary and exit code. */
	function cr_jam_finish(): void {
		if ( $GLOBALS['cr_jam_failures'] ) {
			foreach ( $GLOBALS['cr_jam_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			echo count( $GLOBALS['cr_jam_failures'] ) . " failed\n";
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
}

namespace PKIW {
	if ( empty( $GLOBALS['cr_jam_no_plugin'] ) ) {
		/** Plugin kind facts stand-in. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_jam_facts'][ $post_id ] ?? array();
		}

		/** Plugin Stream render-depth stand-in. */
		function is_rendering_stream_card(): bool {
			return ! empty( $GLOBALS['cr_jam_request']['stream_rendering'] );
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Current Stream-surface flag. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_jam_request']['stream'] );
	}
}

namespace {
	require CR_JAM_THEME_DIR . '/inc/stamps.php';
	require CR_JAM_THEME_DIR . '/inc/jam.php';
	require CR_JAM_THEME_DIR . '/inc/single-jam.php';

	cr_jam_reset();
}
