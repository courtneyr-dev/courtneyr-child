<?php
/**
 * Shared standalone support for the podcast single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_podcast_failures'] = array();
	$GLOBALS['cr_podcast_filters']  = array();
	$GLOBALS['cr_podcast_posts']    = array();
	$GLOBALS['cr_podcast_post']     = null;
	$GLOBALS['cr_podcast_facts']    = array();
	$GLOBALS['cr_podcast_kinds']    = array();
	$GLOBALS['cr_podcast_locked']   = array();
	$GLOBALS['cr_podcast_single']   = true;
	$GLOBALS['cr_podcast_loop']     = true;
	$GLOBALS['cr_podcast_main']     = true;
	$GLOBALS['cr_podcast_feed']     = false;
	$GLOBALS['cr_podcast_queried']  = 0;
	$GLOBALS['cr_podcast_stream']   = true;

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;

		public function __construct( int $id = 236, string $title = 'Listened to Harbor Signals', string $content = '' ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
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

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['cr_podcast_filters'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
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
			array( '&#038;', '&#034;', '&#039;', '&lt;', '&gt;' ),
			$url
		);
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, (int) $component );
	}

	function is_singular( $post_type = '' ): bool {
		return (bool) $GLOBALS['cr_podcast_single'] && ( '' === $post_type || 'post' === $post_type );
	}

	function in_the_loop(): bool {
		return (bool) $GLOBALS['cr_podcast_loop'];
	}

	function is_main_query(): bool {
		return (bool) $GLOBALS['cr_podcast_main'];
	}

	function is_feed(): bool {
		return (bool) $GLOBALS['cr_podcast_feed'];
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		if ( is_numeric( $post ) ) {
			return $GLOBALS['cr_podcast_posts'][ (int) $post ] ?? null;
		}

		return $GLOBALS['cr_podcast_post'];
	}

	function get_post_field( $field, $post = null, $context = 'display' ): string {
		$found = get_post( $post );

		return $found instanceof WP_Post && 'post_title' === $field ? $found->post_title : '';
	}

	function get_the_ID(): int {
		return $GLOBALS['cr_podcast_post'] instanceof WP_Post ? $GLOBALS['cr_podcast_post']->ID : 0;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_podcast_queried'];
	}

	function get_permalink( $post = 0 ): string {
		$post = get_post( $post );
		return 'https://example.test/podcast/' . ( $post instanceof WP_Post ? $post->ID : 0 );
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post
			&& 'kind' === $taxonomy
			&& (string) $term === (string) ( $GLOBALS['cr_podcast_kinds'][ $post->ID ] ?? '' );
	}

	function post_password_required( $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && ! empty( $GLOBALS['cr_podcast_locked'][ $post->ID ] );
	}

	/**
	 * Return deterministic core-like attachment markup.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param string|int[]         $size          Image size.
	 * @param bool                 $icon          Whether to use an icon.
	 * @param array<string, mixed> $attr          Image attributes.
	 * @return string
	 */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		unset( $size, $icon );
		$html = '<img src="https://example.test/media/' . (int) $attachment_id . '.jpg"';
		foreach ( $attr as $name => $value ) {
			$html .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $html . '>';
	}

	function parse_blocks( $content ): array {
		unset( $content );
		return array();
	}

	function cr_podcast_post( int $id, string $title = 'Listened to Harbor Signals', string $content = '' ): WP_Post {
		$post                              = new WP_Post( $id, $title, $content );
		$GLOBALS['cr_podcast_posts'][ $id ] = $post;
		$GLOBALS['cr_podcast_kinds'][ $id ] = 'listen';
		return $post;
	}

	function cr_podcast_reset( int $id = 236 ): WP_Post {
		$GLOBALS['cr_podcast_facts']   = array();
		$GLOBALS['cr_podcast_kinds']   = array();
		$GLOBALS['cr_podcast_locked']  = array();
		$GLOBALS['cr_podcast_single']  = true;
		$GLOBALS['cr_podcast_loop']    = true;
		$GLOBALS['cr_podcast_main']    = true;
		$GLOBALS['cr_podcast_feed']    = false;
		$GLOBALS['cr_podcast_stream']  = true;
		$post                           = cr_podcast_post( $id );
		$GLOBALS['cr_podcast_posts']   = array( $id => $post );
		$GLOBALS['cr_podcast_post']    = $post;
		$GLOBALS['cr_podcast_queried'] = $id;
		return $post;
	}

	function cr_podcast_check( bool $ok, string $label ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_podcast_failures'][] = $label;
		}
	}

	function cr_podcast_contains( string $needle, string $haystack, string $label ): void {
		cr_podcast_check( str_contains( $haystack, $needle ), $label );
	}

	function cr_podcast_not_contains( string $needle, string $haystack, string $label ): void {
		cr_podcast_check( ! str_contains( $haystack, $needle ), $label );
	}

	function cr_podcast_same( $expected, $actual, string $label ): void {
		cr_podcast_check( $expected === $actual, $label );
	}

	function cr_podcast_finish(): void {
		if ( $GLOBALS['cr_podcast_failures'] ) {
			exit( 1 );
		}
		echo "all passed\n";
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_podcast_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\Stamps {
	function seed( string ...$parts ): int {
		return crc32( implode( '|', $parts ) );
	}

	function pick( int $seed, int $shift, int $mod ): int {
		return ( ( $seed >> $shift ) & 0x7fffffff ) % max( 1, $mod );
	}
}

namespace Courtneyr\Child\MediaShelf {
	const SHELF_CARD_STYLE = 'is-style-cr-shelf-case';

	function shelf_kind(): string {
		return '';
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_podcast_stream'];
	}
}

namespace {
	cr_podcast_reset();
	require dirname( __DIR__, 2 ) . '/inc/stream-media.php';
	require dirname( __DIR__, 2 ) . '/inc/single-podcast.php';
}
