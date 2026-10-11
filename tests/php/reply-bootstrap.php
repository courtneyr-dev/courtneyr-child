<?php
/**
 * Shared standalone support for the reply archive, single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_reply_actions']       = array();
	$GLOBALS['cr_reply_filters']       = array();
	$GLOBALS['cr_reply_failures']      = array();
	$GLOBALS['cr_reply_post']          = null;
	$GLOBALS['cr_reply_posts']         = array();
	$GLOBALS['cr_reply_facts']         = array();
	$GLOBALS['cr_reply_dates']         = array();
	$GLOBALS['cr_reply_excerpt']       = array();
	$GLOBALS['cr_reply_protected']     = array();
	$GLOBALS['cr_reply_has_term']      = true;
	$GLOBALS['cr_reply_kind']          = 'reply';
	$GLOBALS['cr_reply_tax']           = false;
	$GLOBALS['cr_reply_single']        = false;
	$GLOBALS['cr_reply_admin']         = false;
	$GLOBALS['cr_reply_stream']        = false;
	$GLOBALS['cr_reply_enqueued']      = array();
	$GLOBALS['cr_reply_block_styles']  = array();
	$GLOBALS['cr_reply_authors']       = array( 7 => 'Courtney Test' );
	$GLOBALS['cr_reply_unique']        = 0;

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;
		public int $post_author;
		public string $post_name;

		public function __construct( int $id = 42, string $title = 'Letter title', string $content = 'Letter body.', int $author = 7 ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
			$this->post_author  = $author;
			$this->post_name    = sanitize_title( '' !== $title ? $title : 'untitled-' . $id );
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
		$GLOBALS['cr_reply_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_reply_filters'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function cr_reply_run_filter( string $hook, $value, ...$args ) {
		$filters = array_filter(
			$GLOBALS['cr_reply_filters'],
			static fn( array $filter ): bool => $hook === $filter[0]
		);
		usort(
			$filters,
			static fn( array $a, array $b ): int => $a[2] <=> $b[2]
		);
		foreach ( $filters as $filter ) {
			$value = call_user_func_array( $filter[1], array_merge( array( $value ), array_slice( $args, 0, (int) $filter[3] - 1 ) ) );
		}
		return $value;
	}

	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_reply_block_styles'][] = array( $block_name, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_reply_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	function is_admin(): bool {
		return (bool) $GLOBALS['cr_reply_admin'];
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return (bool) $GLOBALS['cr_reply_tax'] && 'kind' === $taxonomy && 'reply' === $term;
	}

	function is_singular( $post_type = '' ): bool {
		return (bool) $GLOBALS['cr_reply_single'] && 'post' === $post_type;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		return (bool) $GLOBALS['cr_reply_has_term'] && 'kind' === $taxonomy && (string) $term === (string) $GLOBALS['cr_reply_kind'];
	}

	function post_password_required( $post = null ): bool {
		$p = $post instanceof WP_Post ? $post : get_post( $post );
		return $p instanceof WP_Post && ! empty( $GLOBALS['cr_reply_protected'][ $p->ID ] );
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		if ( is_numeric( $post ) ) {
			return $GLOBALS['cr_reply_posts'][ (int) $post ] ?? null;
		}
		return $GLOBALS['cr_reply_post'];
	}

	function get_the_ID(): int {
		return $GLOBALS['cr_reply_post'] instanceof WP_Post ? $GLOBALS['cr_reply_post']->ID : 0;
	}

	function get_queried_object_id(): int {
		return get_the_ID();
	}

	function get_permalink( $post = 0 ): string {
		$p = get_post( $post );
		return 'https://example.test/reply/' . ( $p instanceof WP_Post ? $p->ID : 0 );
	}

	function get_the_title( $post = 0 ): string {
		$p = get_post( $post );
		if ( ! $p instanceof WP_Post ) {
			return '';
		}
		return post_password_required( $p ) ? 'Protected: ' . $p->post_title : $p->post_title;
	}

	function get_the_date( $format = '', $post = null ): string {
		$p = get_post( $post );
		if ( ! $p instanceof WP_Post ) {
			return '';
		}
		$date = $GLOBALS['cr_reply_dates'][ $p->ID ]['ts'] ?? strtotime( '2026-10-11 12:00:00 UTC' );
		return '' === (string) $format ? gmdate( 'F j, Y', (int) $date ) : gmdate( (string) $format, (int) $date );
	}

	function get_post_time( $format = 'U', $gmt = false, $post = null ) {
		$p = get_post( $post );
		if ( ! $p instanceof WP_Post ) {
			return '';
		}
		return $GLOBALS['cr_reply_dates'][ $p->ID ]['iso'] ?? '2026-10-11T12:00:00+00:00';
	}

	function get_the_excerpt( $post = null ): string {
		$p = get_post( $post );
		if ( ! $p instanceof WP_Post ) {
			return '';
		}
		return $GLOBALS['cr_reply_excerpt'][ $p->ID ] ?? $p->post_content;
	}

	function get_the_author_meta( $field, $user_id = false ): string {
		return 'display_name' === $field ? (string) ( $GLOBALS['cr_reply_authors'][ (int) $user_id ] ?? '' ) : '';
	}

	function wp_trim_words( $text, $num_words = 55, $more = null ): string {
		$words = preg_split( '/\s+/', trim( wp_strip_all_tags( (string) $text ) ) );
		$words = false === $words ? array() : array_values( array_filter( $words, static fn( string $word ): bool => '' !== $word ) );
		if ( count( $words ) <= (int) $num_words ) {
			return implode( ' ', $words );
		}
		return implode( ' ', array_slice( $words, 0, (int) $num_words ) ) . ( null === $more ? '...' : (string) $more );
	}

	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	function wp_unique_id( $prefix = '' ): string {
		++$GLOBALS['cr_reply_unique'];
		return (string) $prefix . $GLOBALS['cr_reply_unique'];
	}

	function sanitize_title( $title ): string {
		return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ) ), '-' );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function esc_html_e( $text, $domain = null ): void {
		echo esc_html( $text );
	}

	function wp_json_encode( $value, $flags = 0, $depth = 512 ): string {
		return (string) json_encode( $value, (int) $flags, (int) $depth );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ): string {
		$url    = trim( (string) $url );
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		if ( is_string( $scheme ) && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return str_replace(
			array( '&', '"', "'", '<', '>' ),
			array( '&' . '#038;', '&' . '#034;', '&' . '#039;', '&lt;', '&gt;' ),
			$url
		);
	}

	function cr_reply_reset(): WP_Post {
		$GLOBALS['cr_reply_unique']       = 0;
		$GLOBALS['cr_reply_facts']        = array();
		$GLOBALS['cr_reply_dates']        = array();
		$GLOBALS['cr_reply_excerpt']      = array();
		$GLOBALS['cr_reply_protected']    = array();
		$GLOBALS['cr_reply_has_term']     = true;
		$GLOBALS['cr_reply_kind']         = 'reply';
		$GLOBALS['cr_reply_tax']          = false;
		$GLOBALS['cr_reply_single']       = false;
		$GLOBALS['cr_reply_admin']        = false;
		$GLOBALS['cr_reply_stream']       = false;
		$GLOBALS['cr_reply_enqueued']     = array();
		$GLOBALS['cr_reply_block_styles'] = array();
		$post                             = new WP_Post();
		$GLOBALS['cr_reply_post']         = $post;
		$GLOBALS['cr_reply_posts']        = array( $post->ID => $post );
		return $post;
	}

	function cr_reply_post( int $id, string $title, string $content = 'Reply body.', int $author = 7 ): WP_Post {
		$post                         = new WP_Post( $id, $title, $content, $author );
		$GLOBALS['cr_reply_posts'][ $id ] = $post;
		return $post;
	}

	function cr_reply_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_reply_failures'] );
		$checks();
		$ok = $before === count( $GLOBALS['cr_reply_failures'] );
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_reply_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_reply_failures'][] = $message;
		}
	}

	function cr_reply_same( $expected, $actual, string $message ): void {
		cr_reply_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_reply_contains( string $needle, string $haystack, string $message ): void {
		cr_reply_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_reply_not_contains( string $needle, string $haystack, string $message ): void {
		cr_reply_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_reply_finish(): void {
		if ( $GLOBALS['cr_reply_failures'] ) {
			foreach ( $GLOBALS['cr_reply_failures'] as $failure ) {
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
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_reply_facts'][ $post_id ] ?? array();
	}

	function get_kind_label( string $fallback, string $kind, string $context ): string {
		return 'reply' === $kind && 'stream-card' === $context ? 'Letter' : $fallback;
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_reply_stream'];
	}
}

namespace {
	cr_reply_reset();
	require dirname( __DIR__, 2 ) . '/inc/stamps.php';
	require dirname( __DIR__, 2 ) . '/inc/letters.php';
	require dirname( __DIR__, 2 ) . '/inc/single-reply.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-reply.php';
}
