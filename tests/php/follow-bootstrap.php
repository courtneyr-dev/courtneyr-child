<?php
/**
 * Shared WordPress stubs for the follow (#260) theme tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_FOLLOW_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_FOLLOW_THEME_DIR );

	$GLOBALS['cr_follow_hooks']      = array();
	$GLOBALS['cr_follow_styles']     = array();
	$GLOBALS['cr_follow_enqueued']   = array();
	$GLOBALS['cr_follow_failures']   = array();
	$GLOBALS['cr_follow_facts']      = array();
	$GLOBALS['cr_follow_posts']      = array();
	$GLOBALS['cr_follow_kind']       = array();
	$GLOBALS['cr_follow_queried']    = 0;
	$GLOBALS['cr_follow_singular']   = false;
	$GLOBALS['cr_follow_stream']     = false;
	$GLOBALS['cr_follow_archive']    = false;
	$GLOBALS['cr_follow_registered'] = array(
		'post-kinds-indieweb/follow-entry' => true,
		'xfn/blogroll'                     => true,
		'xfn/relationship-badge'           => true,
	);

	class WP_Post {
		public int $ID;
		public string $post_status = 'publish';
		public string $post_password = '';
		public string $post_content = '';
		public string $post_title = '';

		/** @param int $id Post ID. */
		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	class WP_Term {
		public string $slug;
		public string $name;

		/**
		 * @param string $slug Slug.
		 * @param string $name Name.
		 */
		public function __construct( string $slug, string $name ) {
			$this->slug = $slug;
			$this->name = $name;
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
			return ! empty( $GLOBALS['cr_follow_registered'][ $name ] );
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_follow_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_follow_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_follow_styles'][] = array( (string) $block, $args );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_follow_enqueued'][] = array( (string) $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_follow_enqueued'][] = array( (string) $handle, compact( 'src', 'deps', 'version' ) );
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return ! empty( $GLOBALS['cr_follow_archive'] ) && 'kind' === $taxonomy && 'follow' === $term;
	}

	function is_singular( $type = '' ): bool {
		return ! empty( $GLOBALS['cr_follow_singular'] ) && ( '' === $type || 'post' === $type );
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && ( $GLOBALS['cr_follow_kind'][ $id ] ?? 'follow' ) === $term;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_follow_queried'];
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_follow_queried'];
	}

	function get_post( $id = null ) {
		$id = $id instanceof WP_Post ? $id->ID : (int) $id;
		return $id > 0 ? ( $GLOBALS['cr_follow_posts'][ $id ] ?? new WP_Post( $id ) ) : null;
	}

	function get_permalink( $post = 0 ): string {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/seed-260-f' . $id . '/';
	}

	function get_term_by( $field, $value, $taxonomy ) {
		return 'slug' === $field && 'follow' === $value && 'kind' === $taxonomy ? new WP_Term( 'follow', 'Follow' ) : false;
	}

	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		$class = isset( $attr['class'] ) ? ' class="' . esc_attr( $attr['class'] ) . '"' : '';
		return '<img src="https://example.test/wp-content/uploads/' . (int) $id . '-' . esc_attr( (string) $size ) . '.jpg"' . $class . ' alt="' . esc_attr( $attr['alt'] ?? '' ) . '">';
	}

	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ): string {
		return 'https://example.test/wp-content/uploads/' . (int) $id . '-' . esc_attr( (string) $size ) . '.jpg';
	}

	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
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
		return (string) $text;
	}

	function esc_html__( $text, $domain = null ): string {
		return esc_html( __( $text, $domain ) );
	}

	function esc_html_e( $text, $domain = null ): void {
		echo esc_html__( $text, $domain );
	}

	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	/** Standard eligible facts for one follow. */
	function cr_follow_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'post_id'     => 1,
				'name'        => 'Amara Okafor',
				'name_source' => 'stored',
				'url'         => 'https://amara.example/',
				'host'        => 'amara.example',
				'initial'     => 'A',
				'title'       => 'Following Amara',
				'title_empty' => false,
				'photo_id'    => 0,
				'photo_alt'   => '',
				'date_iso'    => '2026-11-15T14:00:00+00:00',
				'day'         => '2026-11-15',
				'day_display' => 'November 15, 2026',
			),
			$overrides
		);
	}

	function cr_follow_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_follow_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_follow_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_follow_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_follow_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_follow_failures'][] = $message;
		}
	}

	function cr_follow_same( $expected, $actual, string $message ): void {
		cr_follow_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_follow_contains( string $needle, string $haystack, string $message ): void {
		cr_follow_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_follow_not_contains( string $needle, string $haystack, string $message ): void {
		cr_follow_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_follow_in_order( array $needles, string $haystack, string $message ): void {
		$at = -1;
		foreach ( $needles as $needle ) {
			$next = strpos( $haystack, $needle, $at + 1 );
			cr_follow_assert( false !== $next && $next > $at, $message . ': ' . $needle . ' out of order' );
			$at = false === $next ? $at : $next;
		}
	}

	function cr_follow_hook( string $hook, string $callback ): ?array {
		foreach ( $GLOBALS['cr_follow_hooks'] as $row ) {
			if ( $hook === $row[0] && $callback === $row[1] ) {
				return $row;
			}
		}
		return null;
	}

	function cr_follow_finish(): void {
		if ( $GLOBALS['cr_follow_failures'] ) {
			fwrite( STDERR, implode( "\n", $GLOBALS['cr_follow_failures'] ) . "\n" );
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
	require_once $core . '/wp-includes/class-wp-token-map.php';
	require_once $api . '/html5-named-character-references.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
	require_once $core . '/wp-includes/class-wp-block-parser-block.php';
	require_once $core . '/wp-includes/class-wp-block-parser-frame.php';
	require_once $core . '/wp-includes/class-wp-block-parser.php';

	function parse_blocks( string $content ): array {
		return ( new WP_Block_Parser() )->parse( $content );
	}
}

namespace PKIW {
	/** Plugin privacy-boundary stub. */
	final class Follow_Archive {
		/** @return array<string, mixed> */
		public static function facts( int $post_id ): array {
			return $GLOBALS['cr_follow_facts'][ $post_id ] ?? array();
		}

		/** Identify the generated paragraph in test fixtures. */
		public static function is_generated_paragraph( array $block ): bool {
			return ! empty( $block['crGenerated'] );
		}
	}

	/** Hidden author h-card with no anchor. */
	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card"><span class="p-name">Site Author</span><data class="u-url" value="https://example.test/"></data></span>';
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Stream surface stub. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_follow_stream'] );
	}
}
