<?php
/**
 * Shared standalone support for the photo archive, single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_PHOTO_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_photo_hooks']       = array();
	$GLOBALS['cr_photo_enqueued']    = array();
	$GLOBALS['cr_photo_failures']    = array();
	$GLOBALS['cr_photo_request']     = array();
	$GLOBALS['cr_photo_posts']       = array();
	$GLOBALS['cr_photo_facts']       = array();
	$GLOBALS['cr_photo_styles']      = array();
	$GLOBALS['cr_photo_meta']        = array();
	$GLOBALS['cr_photo_visible']     = array();
	$GLOBALS['cr_photo_no_plugin']   = in_array( '--no-plugin', $argv ?? array(), true );

	/** Post stand-in. */
	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;
		public string $post_status = 'publish';

		/** Build a small post stand-in. */
		public function __construct( int $id = 42, string $title = 'Blue Hour at Example Lake', string $content = '' ) {
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
		$GLOBALS['cr_photo_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_photo_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Run the callbacks registered to a filter, lowest priority first. */
	function apply_filters( string $hook, $value = null, ...$args ) {
		$registered = array_values(
			array_filter(
				$GLOBALS['cr_photo_hooks'],
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

	/** Record a block style registration. */
	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_photo_styles'][] = array( (string) $block_name, (array) $args );
	}

	/** Admin flag. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_photo_request']['admin'] );
	}

	/** Taxonomy archive flag. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_photo_request']['kind_archive'] ?? '' );
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Singular flag. */
	function is_singular( $post_type = '' ): bool {
		if ( ! empty( $post_type ) && 'post' !== $post_type ) {
			return false;
		}
		return ! empty( $GLOBALS['cr_photo_request']['single'] );
	}

	/** Kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = cr_photo_post_id( $post );
		$kinds = $GLOBALS['cr_photo_request']['post_kinds'][ $id ] ?? array( 'photo' );
		return (bool) array_intersect( array_map( 'strval', (array) $term ), array_map( 'strval', (array) $kinds ) );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['cr_photo_request']['queried_id'] ?? 0 );
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) && (int) $post > 0 ? (int) $post : (int) ( $GLOBALS['cr_photo_request']['the_id'] ?? get_queried_object_id() );
		return $GLOBALS['cr_photo_posts'][ $id ] ?? null;
	}

	/** Current loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_photo_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Password protection flag. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_photo_request']['protected'][ cr_photo_post_id( $post ) ] );
	}

	/** Post format. */
	function get_post_format( $post = null ) {
		return $GLOBALS['cr_photo_request']['format'][ cr_photo_post_id( $post ) ] ?? false;
	}

	/** Post meta. */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['cr_photo_meta'][ (int) $post_id ][ (string) $key ] ?? '';
	}

	/** Record an enqueued style. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_photo_enqueued'][] = array( $handle, $src, $deps, $version );
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

	/** Strip tags. */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	/** Sanitize a class token. */
	function sanitize_html_class( $text ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $text );
	}

	/** Attachment markup from the attrs the theme supplies. */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		if ( ! empty( $GLOBALS['cr_photo_request']['missing_attachments'][ (int) $attachment_id ] ) ) {
			return '';
		}
		$attrs = array_merge(
			array(
				'src'    => 'https://example.test/uploads/attachment-' . (int) $attachment_id . '-' . $size . '.jpg',
				'srcset' => 'https://example.test/uploads/attachment-' . (int) $attachment_id . '-small.jpg 300w',
			),
			(array) $attr
		);
		$out   = '<img';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out . ' />';
	}

	/** Test permalink. */
	function get_permalink( $post = 0 ): string {
		return 'https://example.test/photo/' . cr_photo_post_id( $post ) . '/';
	}

	/** Resolve a post ID from any accepted post value. */
	function cr_photo_post_id( $post = null ): int {
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		if ( is_numeric( $post ) && (int) $post > 0 ) {
			return (int) $post;
		}
		return (int) ( $GLOBALS['cr_photo_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Reset request state and register one default photo post. */
	function cr_photo_reset( int $post_id = 42, string $title = 'Blue Hour at Example Lake' ): WP_Post {
		$post                           = new WP_Post( $post_id, $title );
		$GLOBALS['cr_photo_request']    = array(
			'kind_archive'        => '',
			'single'              => false,
			'stream'              => false,
			'admin'               => false,
			'post_kinds'          => array( $post_id => array( 'photo' ) ),
			'protected'           => array(),
			'format'              => array(),
			'missing_attachments' => array(),
			'the_id'              => $post_id,
			'queried_id'          => $post_id,
		);
		$GLOBALS['cr_photo_posts']      = array( $post_id => $post );
		$GLOBALS['cr_photo_facts']      = array( $post_id => cr_photo_facts( $title ) );
		$GLOBALS['cr_photo_enqueued']   = array();
		$GLOBALS['cr_photo_meta']       = array();
		$GLOBALS['cr_photo_visible']    = array();
		\Courtneyr\Child\Photo\reset_page_state();
		return $post;
	}

	/** Default photo facts for a titled post with a local lead image. */
	function cr_photo_facts( string $title = 'Blue Hour at Example Lake' ): array {
		return array(
			'has_title'           => '' !== $title,
			'title'               => $title,
			'name'                => '' !== $title ? $title : 'Photo, September 8, 2026',
			'date'                => 'September 8, 2026',
			'date_iso'            => '2026-09-08T14:00:00+00:00',
			'lead'                => array(
				'attachment_id' => 901,
				'alt'           => 'A dock at dusk',
				'caption'       => 'The dock after sunset.',
				'source'        => 'block',
			),
			'featured_in_content' => false,
		);
	}

	/** Print one grouped result. */
	function cr_photo_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_photo_failures'] );
		try {
			$checks();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_photo_failures'][] = $name . ': ' . $error->getMessage();
		}
		$ok = $before === count( $GLOBALS['cr_photo_failures'] );
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Record an assertion. */
	function cr_photo_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_photo_failures'][] = $message;
		}
	}

	/** Assert strict equality. */
	function cr_photo_same( $expected, $actual, string $message ): void {
		cr_photo_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/** Assert a string contains a substring. */
	function cr_photo_contains( string $needle, string $haystack, string $message ): void {
		cr_photo_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	/** Assert a string does not contain a substring. */
	function cr_photo_not_contains( string $needle, string $haystack, string $message ): void {
		cr_photo_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Whether a hook is registered with the given callback and priority. */
	function cr_photo_hooked( string $hook, string $callback, int $priority = 10, int $args = 1 ): bool {
		foreach ( $GLOBALS['cr_photo_hooks'] as $entry ) {
			if ( $entry[0] === $hook && $entry[1] === $callback && $entry[2] === $priority && $entry[3] === $args ) {
				return true;
			}
		}
		return false;
	}

	/** Anchors with an href that sit outside any [hidden] subtree (W3-G2). */
	function cr_photo_rendered_anchors( string $html ): int {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );
		return $xpath->query( '//a[@href][not(ancestor-or-self::*[@hidden])]' )->length;
	}

	/** Anchors with tabindex -1 that sit outside any [hidden] subtree (W3-G2: must be 0). */
	function cr_photo_visible_negative_tabindex( string $html ): int {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );
		return $xpath->query( "//a[@tabindex='-1'][not(ancestor-or-self::*[@hidden])]" )->length;
	}

	/** Finish with a summary and exit code. */
	function cr_photo_finish(): void {
		if ( $GLOBALS['cr_photo_failures'] ) {
			foreach ( $GLOBALS['cr_photo_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			echo count( $GLOBALS['cr_photo_failures'] ) . " failed\n";
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
	require_once $core . '/wp-includes/class-wp-token-map.php';
	require_once $api . '/html5-named-character-references.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace PKIW {
	if ( empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
		/** Plugin kind facts stand-in. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_photo_facts'][ $post_id ] ?? array();
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Current stream surface flag. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_photo_request']['stream'] );
	}
}

namespace {
	/** Plugin location rule stand-in. */
	if ( empty( $GLOBALS['cr_photo_no_plugin'] ) ) {
		function pkiw_get_visible_location_fields( int $post_id ): array {
			return $GLOBALS['cr_photo_visible'][ $post_id ] ?? array(
				'name'        => false,
				'locality'    => false,
				'region'      => false,
				'country'     => false,
				'street'      => false,
				'coordinates' => false,
				'map'         => false,
			);
		}
	}

	require CR_PHOTO_THEME_DIR . '/inc/stamps.php';
	require CR_PHOTO_THEME_DIR . '/inc/photo.php';
	require CR_PHOTO_THEME_DIR . '/inc/single-photo.php';
	require CR_PHOTO_THEME_DIR . '/inc/stream-photo.php';
	require CR_PHOTO_THEME_DIR . '/inc/stream-gallery.php';
	require CR_PHOTO_THEME_DIR . '/inc/sloc-map-alt.php';

	cr_photo_reset();
}
