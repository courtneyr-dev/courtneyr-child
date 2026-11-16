<?php
/**
 * Shared WordPress stubs for the tag (#261) theme tests. Not a test itself:
 * each `tests/php/tag-*-test.php` requires it, sets `$GLOBALS['cr_tag_*']`
 * state, then loads the theme files it checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_TAG_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_TAG_THEME_DIR );

	$GLOBALS['cr_tag_hooks']       = array();
	$GLOBALS['cr_tag_styles']      = array();
	$GLOBALS['cr_tag_enqueued']    = array();
	$GLOBALS['cr_tag_failures']    = array();
	$GLOBALS['cr_tag_facts']       = array();
	$GLOBALS['cr_tag_posts']       = array();
	$GLOBALS['cr_tag_kind']        = array();
	$GLOBALS['cr_tag_queried']     = 0;
	$GLOBALS['cr_tag_singular']    = false;
	$GLOBALS['cr_tag_stream']      = false;
	$GLOBALS['cr_tag_archive']     = false;
	$GLOBALS['cr_tag_registered']  = true;
	$GLOBALS['cr_tag_terms']       = array();
	$GLOBALS['cr_tag_term_links']  = array();
	$GLOBALS['cr_tag_translated']  = array();

	class WP_Post {
		public int $ID;
		public string $post_status = 'publish';
		public string $post_password = '';
		public string $post_content = '';

		/** @param int $id Post ID. */
		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	class WP_Term {
		public int $term_id;
		public string $slug;
		public string $name;

		/**
		 * @param int    $term_id Term ID.
		 * @param string $slug    Slug.
		 * @param string $name    Name.
		 */
		public function __construct( int $term_id, string $slug, string $name ) {
			$this->term_id = $term_id;
			$this->slug    = $slug;
			$this->name    = $name;
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

	class WP_Block_Type_Registry {
		private static ?self $instance = null;

		public static function get_instance(): self {
			self::$instance ??= new self();
			return self::$instance;
		}

		public function is_registered( string $name ): bool {
			return ! empty( $GLOBALS['cr_tag_registered'] ) && 'post-kinds-indieweb/tag-entry' === $name;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_tag_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_tag_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_tag_styles'][] = array( $block, $args );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_tag_enqueued'][] = array( $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_tag_enqueued'][] = array( $handle, compact( 'src', 'deps', 'version' ) );
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return ! empty( $GLOBALS['cr_tag_archive'] ) && 'kind' === $taxonomy && 'tag' === $term;
	}

	function is_singular( $type = '' ): bool {
		return ! empty( $GLOBALS['cr_tag_singular'] );
	}

	function is_admin(): bool {
		return false;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && ( $GLOBALS['cr_tag_kind'][ $id ] ?? 'tag' ) === $term;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_tag_queried'];
	}

	function get_the_ID(): int {
		return 0;
	}

	function get_post( $id = null ) {
		$id = $id instanceof WP_Post ? $id->ID : (int) $id;
		if ( $id < 1 ) {
			return null;
		}
		return $GLOBALS['cr_tag_posts'][ $id ] ?? new WP_Post( $id );
	}

	function get_permalink( $post = 0 ): string {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/tag-' . $id . '/';
	}

	function get_term_by( $field, $value, $taxonomy ) {
		return 'tag' === $value ? new WP_Term( 7, 'tag', 'Tag' ) : false;
	}

	function get_the_terms( $post, $taxonomy ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return $GLOBALS['cr_tag_terms'][ $taxonomy ][ $id ] ?? false;
	}

	function get_term_link( $term, $taxonomy = '' ) {
		return $GLOBALS['cr_tag_term_links'][ $term->slug ] ?? 'https://example.test/' . $taxonomy . '/' . $term->slug . '/';
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}

	function trailingslashit( $value ): string {
		return rtrim( (string) $value, '/\\' ) . '/';
	}

	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		$class = isset( $attr['class'] ) ? ' class="' . esc_attr( $attr['class'] ) . '"' : '';
		return '<img src="https://example.test/uploads/' . (int) $id . '-' . $size . '.png"' . $class . ' alt="' . esc_attr( $attr['alt'] ?? '' ) . '">';
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8', false );
	}

	function __( $text, $domain = null ): string {
		$GLOBALS['cr_tag_translated'][ (string) $text ] = (string) $domain;
		return (string) $text;
	}

	function esc_html__( $text, $domain = null ): string {
		return esc_html( __( $text, $domain ) );
	}

	function esc_html_e( $text, $domain = null ): void {
		echo esc_html__( $text, $domain );
	}

	function _n( $single, $plural, $number, $domain = null ): string {
		return 1 === (int) $number ? __( $single, $domain ) : __( $plural, $domain );
	}

	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	function cr_tag_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_tag_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_tag_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_tag_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_tag_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_tag_failures'][] = $message;
		}
	}

	function cr_tag_same( $expected, $actual, string $message ): void {
		cr_tag_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_tag_contains( string $needle, string $haystack, string $message ): void {
		cr_tag_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_tag_not_contains( string $needle, string $haystack, string $message ): void {
		cr_tag_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Positions of the given substrings, in order of appearance. */
	function cr_tag_in_order( array $needles, string $haystack, string $message ): void {
		$at = -1;
		foreach ( $needles as $needle ) {
			$next = strpos( $haystack, $needle, $at + 1 );
			cr_tag_assert( false !== $next && $next > $at, $message . ': ' . $needle . ' out of order' );
			$at = false === $next ? $at : $next;
		}
	}

	/** Find a registered hook. */
	function cr_tag_hook( string $hook, string $callback ): ?array {
		foreach ( $GLOBALS['cr_tag_hooks'] as $row ) {
			if ( $hook === $row[0] && $callback === $row[1] ) {
				return $row;
			}
		}
		return null;
	}

	/** Standard eligible facts for a post, overridable per test. */
	function cr_tag_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'target_type'     => 'content',
				'url'             => 'https://guides.example.com/alt-text/',
				'url_host'        => 'guides.example.com',
				'title'           => 'Alt text decision guide',
				'display_title'   => 'Alt text decision guide',
				'title_is_target' => true,
				'labels'          => array( 'Accessibility', 'Images', 'Writing' ),
				'label'           => 'Accessibility',
				'description'     => 'A guide to deciding when images need descriptions.',
				'image'           => 'https://example.test/uploads/11-medium.png',
				'image_id'        => 11,
				'image_alt'       => 'A decision tree drawn on paper.',
				'initial'         => '',
				'tagged_iso'      => '2026-11-16',
				'tagged_display'  => 'Nov 16',
				'date_iso'        => '2026-11-16T12:00:00+00:00',
				'has_card'        => true,
			),
			$overrides
		);
	}

	/** Finish a test file. */
	function cr_tag_finish(): void {
		if ( $GLOBALS['cr_tag_failures'] ) {
			fwrite( STDERR, implode( "\n", $GLOBALS['cr_tag_failures'] ) . "\n" );
			exit( 1 );
		}
		echo "PASS\n";
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		fwrite( STDERR, "FAIL: set CR_WP_CORE to a WordPress checkout\n" );
		exit( 1 );
	}
	require_once $core . '/wp-includes/compat.php';
	if ( is_file( $core . '/wp-includes/utf8.php' ) ) {
		require_once $core . '/wp-includes/utf8.php';
	}
	require_once $core . '/wp-includes/kses.php';
	// Text decoding (`get_modifiable_text()`) reads the named character reference map.
	require_once $core . '/wp-includes/class-wp-token-map.php';
	require_once $api . '/html5-named-character-references.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace PKIW {
	/**
	 * Facts stub: `[]` unless the test seeded public facts for this ID.
	 *
	 * @param int $post_id Post ID.
	 */
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_tag_facts'][ $post_id ] ?? array();
	}

	/**
	 * Author stub.
	 *
	 * @param \WP_Post $post Post.
	 */
	function entry_author_html( \WP_Post $post ): string {
		return '<a class="p-author h-card" href="https://example.test/">Site Author</a>';
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Stream surface stub. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_tag_stream'] );
	}
}
