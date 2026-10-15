<?php
/**
 * Shared standalone support for the acquisition archive, single and Stream tests.
 *
 * Loads inc/acquisition.php, inc/single-acquisition.php and inc/stream-acquisition.php
 * against small WordPress and plugin stand-ins. Set CR_WP_CORE to a WordPress
 * checkout (default ~/.wp-tests/wordpress) for the HTML API.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_ACQ_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_acq_hooks']       = array();
	$GLOBALS['cr_acq_enqueued']    = array();
	$GLOBALS['cr_acq_failures']    = array();
	$GLOBALS['cr_acq_request']     = array();
	$GLOBALS['cr_acq_posts']       = array();
	$GLOBALS['cr_acq_facts']       = array();
	$GLOBALS['cr_acq_attachments'] = array();
	$GLOBALS['cr_acq_group']       = '';
	$GLOBALS['cr_acq_no_plugin']   = in_array( '--no-plugin', $argv ?? array(), true );

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content = '';
		public string $post_status  = 'publish';
		public string $date_iso     = '2026-08-28';
		public string $date_display = 'August 28, 2026';

		/** Build a small post stand-in. */
		public function __construct( int $id = 42, string $title = 'Field Recorder' ) {
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
		$GLOBALS['cr_acq_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_acq_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a block style registration. */
	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_acq_styles'][] = array( $block_name, $args );
	}

	/** Current admin flag. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_acq_request']['admin'] );
	}

	/** Current taxonomy flag. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_acq_request']['kind_archive'] ?? '' );
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
		return ! empty( $GLOBALS['cr_acq_request']['single'] );
	}

	/** Current post kinds. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = cr_acq_post_id( $post );
		$kinds = $GLOBALS['cr_acq_request']['kinds'][ $id ] ?? array( 'acquisition' );
		return in_array( (string) $term, array_map( 'strval', (array) $kinds ), true );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['cr_acq_request']['queried_id'] ?? 0 );
	}

	/** Loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_acq_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) ? (int) $post : get_queried_object_id();
		return $GLOBALS['cr_acq_posts'][ $id ] ?? null;
	}

	/** Resolve a post ID from any accepted value. */
	function cr_acq_post_id( $post = null ): int {
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		return is_numeric( $post ) && (int) $post > 0 ? (int) $post : get_queried_object_id();
	}

	/** Password protection flag. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_acq_request']['protected'][ cr_acq_post_id( $post ) ] );
	}

	/** Query var. */
	function get_query_var( $name ) {
		return $GLOBALS['cr_acq_request'][ $name ] ?? 0;
	}

	/** Record an enqueued style. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_acq_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	/** Escape text. */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	/** Escape an attribute. */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	/** Escape a URL. */
	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8', false );
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
		return trim( strip_tags( (string) $text ) );
	}

	/** Parse a URL. */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}

	/** Test permalink. */
	function get_permalink( $post = 0 ): string {
		return 'https://example.test/acquisition/' . cr_acq_post_id( $post ) . '/';
	}

	/** Test title, with core's protected prefix. */
	function get_the_title( $post = 0 ): string {
		$post = get_post( cr_acq_post_id( $post ) );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		return ( post_password_required( $post ) ? 'Protected: ' : '' ) . $post->post_title;
	}

	/** Test date: '' is the site format. */
	function get_the_date( $format = '', $post = 0 ): string {
		$post = get_post( cr_acq_post_id( $post ) );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		return '' === $format ? $post->date_display : $post->date_iso;
	}

	/** Return attachment HTML from the attrs the theme supplied. */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		if ( isset( $GLOBALS['cr_acq_attachments'][ (int) $attachment_id ] ) && '' === $GLOBALS['cr_acq_attachments'][ (int) $attachment_id ] ) {
			return '';
		}
		$attrs = array_merge(
			array(
				'src'    => 'https://example.test/uploads/attachment-' . (int) $attachment_id . '.jpg',
				'width'  => '800',
				'height' => '600',
			),
			(array) $attr
		);
		$out   = '<img';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out . ' />';
	}

	/** Filters are pass-through in these tests. */
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	/** Run the callbacks registered to a filter hook. */
	function cr_acq_fire_hook( string $hook, $value = null, ...$args ) {
		$result = $value;
		foreach ( $GLOBALS['cr_acq_hooks'] as $registered ) {
			if ( $registered[0] !== $hook ) {
				continue;
			}
			$params = array_slice( array_merge( array( $result ), $args ), 0, (int) $registered[3] );
			$result = call_user_func_array( $registered[1], $params );
		}
		return $result;
	}

	/** Whether a hook is registered with a callback, priority and argument count. */
	function cr_acq_has_hook( string $hook, string $callback, int $priority, int $args ): bool {
		foreach ( $GLOBALS['cr_acq_hooks'] as $registered ) {
			if ( $registered[0] === $hook && $registered[1] === $callback && $registered[2] === $priority && $registered[3] === $args ) {
				return true;
			}
		}
		return false;
	}

	/** Default reader facts for an acquisition card. */
	function cr_acq_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'has_card'         => true,
				'title'            => 'Field Recorder',
				'type'             => 'purchase',
				'type_label'       => 'Purchase',
				'where'            => 'Riverside Audio',
				'where_url'        => 'https://shop.example.com/field-recorder',
				'notes'            => 'Records the morning birds.',
				'acquired_at'      => '2026-08-01',
				'acquired_display' => 'August 1, 2026',
				'cost'             => '',
				'picture'          => array(
					'source'            => 'cover',
					'attachment_id'     => 7,
					'url'               => 'https://example.test/uploads/attachment-7.jpg',
					'alt'               => 'A handheld recorder with two microphones',
					'remote'            => false,
					'suppress_featured' => false,
					'width'             => 800,
					'height'            => 600,
				),
				'name'             => 'Field Recorder',
				'name_is_title'    => true,
			),
			$overrides
		);
	}

	/** Reset request state and register one post with default facts. */
	function cr_acq_reset( int $id = 42, string $title = 'Field Recorder' ): WP_Post {
		$post                          = new WP_Post( $id, $title );
		$GLOBALS['cr_acq_request']     = array(
			'kind_archive' => '',
			'single'       => false,
			'admin'        => false,
			'kinds'        => array(),
			'protected'    => array(),
			'paged'        => 0,
			'the_id'       => $id,
			'queried_id'   => $id,
		);
		$GLOBALS['cr_acq_posts']       = array( $id => $post );
		$GLOBALS['cr_acq_facts']       = array( $id => cr_acq_facts() );
		$GLOBALS['cr_acq_attachments'] = array();
		$GLOBALS['cr_acq_enqueued']    = array();
		$GLOBALS['cr_acq_group']       = '';
		$GLOBALS['wp_query']           = (object) array( 'posts' => array( $post ) );
		\Courtneyr\Child\Acquisition\reset_page_state();
		return $post;
	}

	/** Register one more post with its own facts. */
	function cr_acq_add_post( int $id, string $title, array $facts_overrides = array() ): WP_Post {
		$post                                = new WP_Post( $id, $title );
		$GLOBALS['cr_acq_posts'][ $id ]      = $post;
		$GLOBALS['cr_acq_facts'][ $id ]      = cr_acq_facts( array_merge( array( 'title' => $title, 'name' => $title ), $facts_overrides ) );
		return $post;
	}

	/** Print one grouped result. */
	function cr_acq_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_acq_failures'] );
		try {
			$checks();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_acq_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_acq_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Record an assertion. */
	function cr_acq_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_acq_failures'][] = $message;
		}
	}

	/** Assert strict equality. */
	function cr_acq_same( $expected, $actual, string $message ): void {
		cr_acq_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/** Assert a string contains a substring. */
	function cr_acq_contains( string $needle, string $haystack, string $message ): void {
		cr_acq_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	/** Assert a string does not contain a substring. */
	function cr_acq_not_contains( string $needle, string $haystack, string $message ): void {
		cr_acq_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Count regex matches. */
	function cr_acq_count( string $pattern, string $haystack ): int {
		return (int) preg_match_all( $pattern, $haystack );
	}

	/** Finish with a summary and exit code. */
	function cr_acq_finish(): void {
		if ( $GLOBALS['cr_acq_failures'] ) {
			foreach ( $GLOBALS['cr_acq_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			echo count( $GLOBALS['cr_acq_failures'] ) . " failed\n";
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

namespace Courtneyr\Child\HomeSections {
	/** Stream surface stand-in. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_acq_request']['stream'] );
	}
}

namespace PKIW {
	if ( empty( $GLOBALS['cr_acq_no_plugin'] ) ) {
		/** Plugin facts stand-in. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_acq_facts'][ $post_id ] ?? array();
		}

		/** Plugin hidden citation stand-in: the reader's stream cite, p-name only. */
		function stream_card_kind_cite( \WP_Post $post ): string {
			$facts = $GLOBALS['cr_acq_facts'][ $post->ID ] ?? array();
			$title = trim( (string) ( $facts['title'] ?? '' ) );
			return '' === $title ? '' : '<span class="pk-kind-cite h-cite u-acquisition-of" hidden><data class="p-name" value="' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '"></data></span>';
		}

		/** Plugin new-tab label stand-in. */
		function pkiw_new_tab_label_attr( string $visible_text ): string {
			return ' aria-label="' . htmlspecialchars( $visible_text . ' (opens in a new tab)', ENT_QUOTES, 'UTF-8' ) . '"';
		}

		/** Section stand-in for the archive engine. */
		class Archive_Group {
			/** @var string */
			private string $slug;

			/** Build a group. */
			public function __construct( string $slug ) {
				$this->slug = $slug;
			}

			/** The group slug. */
			public function slug(): string {
				return $this->slug;
			}
		}

		/** Archive engine stand-in. */
		class Grouped_Archive {
			/** The section being rendered, or null. */
			public static function current_group(): ?Archive_Group {
				return '' === $GLOBALS['cr_acq_group'] ? null : new Archive_Group( (string) $GLOBALS['cr_acq_group'] );
			}
		}
	}
}

namespace {
	foreach ( array( 'acquisition.php', 'single-acquisition.php', 'stream-acquisition.php' ) as $cr_acq_file ) {
		require CR_ACQ_THEME_DIR . '/inc/' . $cr_acq_file;
	}
	cr_acq_reset();
}
