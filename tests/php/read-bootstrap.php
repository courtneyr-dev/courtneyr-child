<?php
/**
 * Shared standalone support for the read single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_read_actions']     = array();
	$GLOBALS['cr_read_filters']     = array();
	$GLOBALS['cr_read_meta']        = array();
	$GLOBALS['cr_read_pictures']    = array();
	$GLOBALS['cr_read_attachments'] = array();
	$GLOBALS['cr_read_blocks']      = array();
	$GLOBALS['cr_read_stream']      = true;
	$GLOBALS['cr_read_post']        = null;
	$GLOBALS['cr_read_failures']    = array();
	$GLOBALS['cr_read_has_term']    = true;
	$GLOBALS['cr_read_protected']   = array();
	$GLOBALS['cr_read_dates']       = array();
	$GLOBALS['cr_read_render']      = null;
	$GLOBALS['cr_read_enqueued']    = array();

	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;
		public string $post_name;

		public function __construct( int $id = 1, string $title = 'Test Book', string $content = '' ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
			$this->post_name    = sanitize_title( $title );
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
		$GLOBALS['cr_read_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_read_filters'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function register_block_style( $block_name, $args ): void {
	}

	function is_admin(): bool {
		return false;
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && 'read' === $term;
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_read_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
	}

	function esc_attr__( $text, $domain = null ): string {
		return esc_attr( $text );
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

	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	function get_option( $name, $default = false ) {
		return 'date_format' === $name ? 'F j, Y' : $default;
	}

	function wp_date( $format, $timestamp = null, $timezone = null ): string {
		$zone = $timezone instanceof \DateTimeZone ? $timezone : new \DateTimeZone( 'America/Chicago' );
		$date = new \DateTimeImmutable( '@' . (string) ( $timestamp ?? time() ) );
		return $date->setTimezone( $zone )->format( (string) $format );
	}

	function get_the_title( $post = 0 ): string {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post ? $p->post_title : '';
	}

	function get_permalink( $post = 0 ): string {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return 'https://example.test/read/' . ( $p instanceof WP_Post ? $p->ID : 0 );
	}

	function get_the_date( $format = '', $post = null ): string {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post ? ( $GLOBALS['cr_read_dates'][ $p->ID ]['text'] ?? 'October 9, 2026' ) : 'October 9, 2026';
	}

	function get_post_time( $format = 'U', $gmt = false, $post = null ) {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post ? ( $GLOBALS['cr_read_dates'][ $p->ID ]['iso'] ?? '2026-10-09T12:00:00+00:00' ) : '';
	}

	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['cr_read_meta'][ (int) $post_id ][ (string) $key ] ?? '';
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		return (bool) $GLOBALS['cr_read_has_term'] && 'read' === $term && 'kind' === $taxonomy;
	}

	function post_password_required( $post = null ): bool {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post && ! empty( $GLOBALS['cr_read_protected'][ $p->ID ] );
	}

	function has_post_thumbnail( $post = null ): bool {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post && isset( $GLOBALS['cr_read_pictures'][ $p->ID ] ) && 'featured' === ( $GLOBALS['cr_read_pictures'][ $p->ID ]['source'] ?? '' );
	}

	function get_post_thumbnail_id( $post = null ): int {
		$p = $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
		return $p instanceof WP_Post ? (int) ( $GLOBALS['cr_read_pictures'][ $p->ID ]['attachment_id'] ?? 0 ) : 0;
	}

	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		$src   = $GLOBALS['cr_read_attachments'][ (int) $attachment_id ] ?? 'https://example.test/attachment-' . (int) $attachment_id . '.jpg';
		$attrs = array_merge( array( 'src' => $src ), (array) $attr );
		$out   = '<img';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		return $out . '>';
	}

	function get_the_post_thumbnail( $post, $size = 'post-thumbnail', $attr = array() ): string {
		return wp_get_attachment_image( get_post_thumbnail_id( $post ), $size, false, $attr );
	}

	function is_rtl(): bool {
		return false;
	}

	function number_format_i18n( $number, $decimals = 0 ): string {
		return number_format( (float) $number, (int) $decimals, '.', ',' );
	}

	function sanitize_title( $title ): string {
		return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ) ), '-' );
	}

	function wp_unique_id( $prefix = '' ): string {
		static $id = 0;
		return (string) $prefix . ++$id;
	}

	function is_singular( $post_type = '' ): bool {
		return 'post' === $post_type;
	}

	function get_queried_object_id(): int {
		return $GLOBALS['cr_read_post'] instanceof WP_Post ? $GLOBALS['cr_read_post']->ID : 0;
	}

	function get_post( $post = null ): ?WP_Post {
		return $post instanceof WP_Post ? $post : $GLOBALS['cr_read_post'];
	}

	function get_the_ID(): int {
		return get_queried_object_id();
	}

	function parse_blocks( $content ): array {
		return $GLOBALS['cr_read_blocks'];
	}

	function render_block( $block ): string {
		if ( is_callable( $GLOBALS['cr_read_render'] ) ) {
			return (string) call_user_func( $GLOBALS['cr_read_render'], $block );
		}
		return (string) ( $block['rendered'] ?? '' );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function get_bloginfo( $show = '' ): string {
		return 'Courtney Test';
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}

	function cr_read_reset(): WP_Post {
		$post                            = new WP_Post( 42, 'Fixture Book', '' );
		$GLOBALS['cr_read_meta']        = array();
		$GLOBALS['cr_read_pictures']    = array();
		$GLOBALS['cr_read_attachments'] = array();
		$GLOBALS['cr_read_blocks']      = array();
		$GLOBALS['cr_read_stream']      = true;
		$GLOBALS['cr_read_post']        = $post;
		$GLOBALS['cr_read_has_term']    = true;
		$GLOBALS['cr_read_protected']   = array();
		$GLOBALS['cr_read_dates']       = array();
		$GLOBALS['cr_read_render']      = null;
		return $post;
	}

	function cr_read_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_read_failures'] );
		$checks();
		$ok = $before === count( $GLOBALS['cr_read_failures'] );
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_read_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_read_failures'][] = $message;
		}
	}

	function cr_read_same( $expected, $actual, string $message ): void {
		cr_read_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_read_contains( string $needle, string $haystack, string $message ): void {
		cr_read_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_read_not_contains( string $needle, string $haystack, string $message ): void {
		cr_read_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_read_finish(): void {
		if ( $GLOBALS['cr_read_failures'] ) {
			foreach ( $GLOBALS['cr_read_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			exit( 1 );
		}
		echo "all passed\n";
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
	final class Card_Meta_Sync {
		public const ATTR_META_MAP = array(
			'post-kinds-indieweb/read-card' => array(
				'bookTitle' => 'read_title',
				'authorName' => 'read_author',
				'isbn' => 'read_isbn',
				'bookUrl' => 'read_url',
				'readStatus' => 'read_status',
				'rating' => 'read_rating',
			),
			'post-kinds-indieweb/watch-card' => array(
				'rating' => 'watch_rating',
			),
		);
	}

	final class Shelf_Group {
		public function __construct( private string $key, private string $slug ) {
		}

		public function key(): string {
			return $this->key;
		}

		public function slug(): string {
			return $this->slug;
		}
	}

	final class Grouped_Archive {
		public static bool $sectioning = false;
		public static bool $preview = false;
		public static ?Shelf_Group $group = null;

		public static function is_sectioning(): bool {
			return self::$sectioning;
		}

		public static function current_group(): ?Shelf_Group {
			return self::$group;
		}

		public static function is_block_preview(): bool {
			return self::$preview;
		}

		public static function group_of_post( string $kind, int $post_id ): ?Shelf_Group {
			return 'read' === $kind ? self::$group : null;
		}
	}

	function read_status_labels(): array {
		return array( 'reading' => 'Currently Reading', 'to-read' => 'To Read', 'finished' => 'Finished', 'abandoned' => 'Abandoned' );
	}

	function card_calendar_date( string $raw ): array {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', trim( $raw ), $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return array( '', '' );
		}
		$ts = gmmktime( 12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] );
		return array( \wp_date( 'Y-m-d', $ts, new \DateTimeZone( 'UTC' ) ), \wp_date( (string) \get_option( 'date_format' ), $ts, new \DateTimeZone( 'UTC' ) ) );
	}

	function card_star_counts( float $rating, int $best = 5 ): array {
		$value = max( 0.0, min( (float) $best, $rating ) );
		$full  = (int) floor( $value );
		$half  = ( $value - $full ) >= 0.5;
		return array( 'value' => $value, 'best' => $best, 'full' => $full, 'half' => $half, 'empty' => max( 0, $best - $full - ( $half ? 1 : 0 ) ) );
	}

	function card_rating_label( float $rating, int $best = 5 ): string {
		$value = card_star_counts( $rating, $best )['value'];
		return $value > 0 ? 'Rated ' . rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . ' of ' . $best : '';
	}

	function kind_picture( int $post_id ): array {
		return $GLOBALS['cr_read_pictures'][ $post_id ] ?? array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '' );
	}

	function link_title_to_post( string $html, \WP_Post $post ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag( array( 'class_name' => 'pk-title' ) ) && $tags->next_tag( 'a' ) ) {
			$tags->set_attribute( 'href', \get_permalink( $post ) );
		}
		return $tags->get_updated_html();
	}

	function untitled_name( \WP_Post $post ): string {
		return 'Untitled';
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_read_stream'];
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/stamps.php';
	require dirname( __DIR__, 2 ) . '/inc/journal.php';
	require dirname( __DIR__, 2 ) . '/inc/media-shelf.php';
	require dirname( __DIR__, 2 ) . '/inc/single-read.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-read.php';
}
