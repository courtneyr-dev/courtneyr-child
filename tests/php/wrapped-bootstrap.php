<?php
/**
 * Mocked WordPress bootstrap for Wrapped theme tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
define( 'COURTNEYR_CHILD_VERSION', 'test' );
define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
define( 'CR_WRAPPED_THEME_DIR', COURTNEYR_CHILD_DIR );

$GLOBALS['cr_wrapped_failures'] = array();
$GLOBALS['cr_wrapped_request']  = array();
$GLOBALS['cr_wrapped_styles']   = array();
$GLOBALS['cr_wrapped_blocks']   = array();
$GLOBALS['cr_wrapped_hooks']    = array();
$GLOBALS['cr_wrapped_posts']    = array();

class WP_Post {
	public int $ID;
	public string $post_title;
	public int $post_author = 1;

	public function __construct( int $id, string $title = 'Read 2026 Wrapped' ) {
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

/** Minimal heading processor used by the real cutout implementation. */
class WP_HTML_Processor {
	private string $html;
	private string $tag = '';
	private string $text = '';
	private int $token = 0;
	private bool $closer = false;

	private function __construct( string $html ) {
		$this->html = $html;
	}

	public static function create_fragment( string $html ): self {
		return new self( $html );
	}

	public function next_tag(): bool {
		if ( 0 !== $this->token || ! preg_match( '/<([a-z0-9]+)/i', $this->html, $match ) ) {
			return false;
		}
		$this->tag = strtoupper( $match[1] );
		return true;
	}

	public function get_tag(): string {
		return $this->tag;
	}

	public function is_tag_closer(): bool {
		return $this->closer;
	}

	/** @return string[] */
	public function get_attribute_names_with_prefix( string $prefix ): array {
		$names = array();
		if ( preg_match_all( '/\s([a-z:-]+)(?:="[^"]*")?/i', strstr( $this->html, '>', true ) ?: '', $matches ) ) {
			$names = $matches[1];
		}
		return $names;
	}

	public function get_attribute( string $name ): ?string {
		return preg_match( '/\s' . preg_quote( $name, '/' ) . '="([^"]*)"/i', $this->html, $match ) ? $match[1] : null;
	}

	public function next_token(): bool {
		++$this->token;
		if ( 1 === $this->token ) {
			$this->text = trim( strip_tags( $this->html ) );
			return true;
		}
		if ( 2 === $this->token ) {
			$this->closer = true;
			return true;
		}
		return false;
	}

	public function get_token_type(): string {
		return 1 === $this->token ? '#text' : '#tag';
	}

	public function get_modifiable_text(): string {
		return $this->text;
	}

	public function get_last_error(): ?string {
		return null;
	}
}

/** Minimal tag processor for Stream heading-level detection. */
class WP_HTML_Tag_Processor {
	private string $html;
	private int $offset = 0;
	private string $tag = '';
	private string $classes = '';

	public function __construct( string $html ) {
		$this->html = $html;
	}

	public function next_tag(): bool {
		if ( ! preg_match( '/<([a-z0-9]+)([^>]*)>/i', $this->html, $match, PREG_OFFSET_CAPTURE, $this->offset ) ) {
			return false;
		}
		$this->tag     = strtoupper( $match[1][0] );
		$this->classes = preg_match( '/class="([^"]*)"/i', $match[2][0], $class ) ? $class[1] : '';
		$this->offset  = $match[0][1] + strlen( $match[0][0] );
		return true;
	}

	public function get_tag(): string {
		return $this->tag;
	}

	public function has_class( string $class ): bool {
		return in_array( $class, preg_split( '/\s+/', $this->classes, -1, PREG_SPLIT_NO_EMPTY ), true );
	}
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
	$GLOBALS['cr_wrapped_hooks'][] = array( $hook, $callback, $priority, $accepted_args );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
	$GLOBALS['cr_wrapped_hooks'][] = array( $hook, $callback, $priority, $accepted_args );
	return true;
}

function is_singular( $post_type = '' ): bool {
	return ! empty( $GLOBALS['cr_wrapped_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
}

function get_queried_object_id(): int {
	return (int) ( $GLOBALS['cr_wrapped_request']['post_id'] ?? 0 );
}

function is_admin(): bool {
	return ! empty( $GLOBALS['cr_wrapped_request']['admin'] );
}

function wp_is_serving_rest_request(): bool {
	return false;
}

function is_archive(): bool {
	return false;
}

function is_search(): bool {
	return false;
}

function is_home(): bool {
	return false;
}

function is_front_page(): bool {
	return false;
}

function is_page( $page = '' ): bool {
	return false;
}

function is_date(): bool {
	return false;
}

function is_author(): bool {
	return false;
}

function get_query_var( $name, $default = '' ) {
	return $default;
}

function get_queried_object() {
	return null;
}

function get_the_ID(): int {
	return (int) ( $GLOBALS['cr_wrapped_request']['post_id'] ?? 0 );
}

function get_post( $post_id ) {
	return $GLOBALS['cr_wrapped_posts'][ (int) $post_id ] ?? null;
}

function get_the_title( $post ): string {
	if ( $post instanceof WP_Post ) {
		return $post->post_title;
	}
	$found = get_post( (int) $post );
	return $found instanceof WP_Post ? $found->post_title : '';
}

function get_permalink( $post ): string {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	return 'https://example.test/wrapped-' . $id . '/';
}

function get_post_time( $format, $gmt = false, $post = null ): string {
	return '2026-12-31T12:00:00+00:00';
}

function get_the_date( $format = '', $post = null ): string {
	return 'December 31, 2026';
}

function sanitize_key( $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) );
}

function esc_attr( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_html( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_url( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function wp_date( $format ): string {
	return '2026';
}

function register_block_type( $path ): void {}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
	$GLOBALS['cr_wrapped_styles'][ (string) $handle ] = array( $src, $deps, $version );
}

function wp_dequeue_style( $handle ): void {
	unset( $GLOBALS['cr_wrapped_styles'][ (string) $handle ] );
}

function wp_enqueue_block_style( $block, $args ): void {
	$GLOBALS['cr_wrapped_blocks'][] = array( $block, $args );
}

function cr_wrapped_check( bool $ok, string $message ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
	if ( ! $ok ) {
		$GLOBALS['cr_wrapped_failures'][] = $message;
	}
}

function cr_wrapped_contains( string $needle, string $haystack, string $message ): void {
	cr_wrapped_check( str_contains( $haystack, $needle ), $message );
}

function cr_wrapped_not_contains( string $needle, string $haystack, string $message ): void {
	cr_wrapped_check( ! str_contains( $haystack, $needle ), $message );
}

function cr_wrapped_finish(): void {
	if ( array() !== $GLOBALS['cr_wrapped_failures'] ) {
		echo "\n", count( $GLOBALS['cr_wrapped_failures'] ), " failure(s)\n";
		exit( 1 );
	}
	echo "\nAll Wrapped checks passed.\n";
}
