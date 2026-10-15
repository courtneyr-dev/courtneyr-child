<?php
/**
 * Shared standalone support for the favorite archive, single and Stream tests.
 *
 * It stubs the few WordPress and plugin functions inc/favorite.php,
 * inc/single-favorite.php and inc/stream-favorite.php call, loads those files,
 * and offers a tiny assertion harness. No WordPress install is needed.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_FAV_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_fav_filters']  = array();
	$GLOBALS['cr_fav_actions']  = array();
	$GLOBALS['cr_fav_failures'] = array();
	$GLOBALS['cr_fav_count']    = 0;
	$GLOBALS['cr_fav_posts']    = array();
	$GLOBALS['cr_fav_facts']    = array();
	$GLOBALS['cr_fav_kinds']    = array();
	$GLOBALS['cr_fav_locked']   = array();
	$GLOBALS['cr_fav_queried']  = 0;
	$GLOBALS['cr_fav_single']   = false;
	$GLOBALS['cr_fav_tax']      = false;
	$GLOBALS['cr_fav_admin']    = false;
	$GLOBALS['cr_fav_stream']   = false;
	$GLOBALS['cr_fav_styles']   = array();
	$GLOBALS['cr_fav_enqueued'] = array();
	$GLOBALS['cr_fav_months']   = array();
	$GLOBALS['wp_query']        = null;

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content = '';
		public string $post_date    = '2026-08-28 10:00:00';

		public function __construct( int $id, string $title = '' ) {
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

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_fav_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_fav_filters'][] = array( $hook, $callback, $priority, $accepted_args );
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_fav_styles'][] = array( $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_fav_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ): string {
		$url = trim( (string) $url );
		return preg_match( '#^https?://#i', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
	}

	function wp_strip_all_tags( $text ): string {
		return trim( strip_tags( (string) $text ) );
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}

	function is_admin(): bool {
		return (bool) $GLOBALS['cr_fav_admin'];
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return (bool) $GLOBALS['cr_fav_tax'] && 'kind' === $taxonomy && 'favorite' === $term;
	}

	function is_singular( $post_type = '' ): bool {
		return (bool) $GLOBALS['cr_fav_single'] && 'post' === $post_type;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_fav_queried'];
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_fav_queried'];
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return $GLOBALS['cr_fav_posts'][ (int) ( $post ?? $GLOBALS['cr_fav_queried'] ) ] ?? null;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'favorite' === (string) $term && ! empty( $GLOBALS['cr_fav_kinds'][ $id ] );
	}

	function post_password_required( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) ( $post ?? $GLOBALS['cr_fav_queried'] );
		return ! empty( $GLOBALS['cr_fav_locked'][ $id ] );
	}

	function get_permalink( $post = 0 ): string {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/favorite-' . $id . '/';
	}

	function get_post_time( $format = 'U', $gmt = false, $post = null ): string {
		return '2026-08-28T10:00:00+00:00';
	}

	function get_the_date( $format = '', $post = null ): string {
		return 'August 28, 2026';
	}

	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		$parts = '';
		foreach ( $attr as $name => $value ) {
			if ( false === $value ) {
				continue;
			}
			$parts .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return '<img src="https://example.test/uploads/' . (int) $id . '.png"' . $parts . '>';
	}

	function cr_fav_assert( bool $condition, string $label ): void {
		++$GLOBALS['cr_fav_count'];
		if ( ! $condition ) {
			$GLOBALS['cr_fav_failures'][] = $label;
		}
	}

	function cr_fav_same( $expected, $actual, string $label ): void {
		cr_fav_assert( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
	}

	/** Run a named group of checks and print its result. */
	function cr_fav_group( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_fav_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_fav_failures'][] = $name . ' threw ' . $error->getMessage();
		}
		echo count( $GLOBALS['cr_fav_failures'] ) === $before ? 'PASS ' : 'FAIL ', $name, "\n";
	}

	/** Print the summary and exit non-zero on failure. */
	function cr_fav_done(): void {
		foreach ( $GLOBALS['cr_fav_failures'] as $failure ) {
			echo '  - ', $failure, "\n";
		}
		echo 'TOTAL ', $GLOBALS['cr_fav_count'], ' checks, ', count( $GLOBALS['cr_fav_failures'] ), " failed\n";
		exit( empty( $GLOBALS['cr_fav_failures'] ) ? 0 : 1 );
	}

	/** Count regex matches in a string. */
	function cr_fav_count( string $pattern, string $html ): int {
		return (int) preg_match_all( $pattern, $html );
	}

	/** Reset the request state and register one favorite post. */
	function cr_fav_post( int $id, string $title, array $facts, bool $locked = false ): WP_Post {
		$post                                = new WP_Post( $id, $title );
		$GLOBALS['cr_fav_posts'][ $id ]      = $post;
		$GLOBALS['cr_fav_kinds'][ $id ]      = true;
		$GLOBALS['cr_fav_facts'][ $id ]      = $facts;
		$GLOBALS['cr_fav_locked'][ $id ]     = $locked;
		return $post;
	}

	/**
	 * Facts as the plugin's reader returns them.
	 *
	 * @param array<string, mixed> $over Keys to override.
	 * @return array<string, mixed>
	 */
	function cr_fav_facts( array $over = array() ): array {
		return array_merge(
			array(
				'has_card'      => true,
				'cited_title'   => 'Field Guide to Quiet Software',
				'url'           => 'https://notes.example.com/quiet-software',
				'host'          => 'notes.example.com',
				'author'        => 'Rowan Vale',
				'summary'       => 'A short guide.',
				'rel'           => 'nofollow',
				'favorited'     => array( '2026-08-14', 'August 14, 2026' ),
				'picture'       => array(
					'source'            => '',
					'attachment_id'     => 0,
					'url'               => '',
					'alt'               => '',
					'remote'            => false,
					'suppress_featured' => false,
				),
				'name'          => 'Field Guide to Quiet Software',
				'name_is_title' => true,
			),
			$over
		);
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_fav_stream'];
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_fav_facts'][ $post_id ] ?? array();
	}

	function get_kind_label( string $label, string $kind, string $context ): string {
		return $label;
	}

	function pkiw_new_tab_label_attr( string $visible_text ): string {
		$visible_text = trim( $visible_text );
		return '' === $visible_text ? '' : ' aria-label="' . htmlspecialchars( $visible_text . ' (opens in a new tab)', ENT_QUOTES ) . '"';
	}

	class Archive_Group_Stub {
		public function __construct( private string $slug ) {}

		public function slug(): string {
			return $this->slug;
		}
	}

	class Grouped_Archive {
		public static function group_of_post( string $kind, int $post_id ) {
			$slug = $GLOBALS['cr_fav_months'][ $post_id ] ?? '';
			return '' === $slug ? null : new Archive_Group_Stub( $slug );
		}
	}
}

namespace {
	require CR_FAV_THEME_DIR . '/inc/stamps.php';
	require CR_FAV_THEME_DIR . '/inc/favorite.php';
	require CR_FAV_THEME_DIR . '/inc/single-favorite.php';
	require CR_FAV_THEME_DIR . '/inc/stream-favorite.php';
}
