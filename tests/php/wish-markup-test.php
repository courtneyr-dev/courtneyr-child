<?php
/**
 * `php tests/php/wish-markup-test.php`: standalone wish archive checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_posts'] = array();
	$GLOBALS['cr_terms'] = array();
	$GLOBALS['cr_titles'] = array();
	$GLOBALS['cr_dates'] = array();
	$GLOBALS['cr_facts'] = array();
	$GLOBALS['cr_request'] = array();
	$GLOBALS['cr_current_post'] = 0;
	$GLOBALS['cr_block_styles'] = array();
	$GLOBALS['cr_enqueued'] = array();

	class WP_Post {
		public $ID;
		public $post_title;
		public function __construct( int $id, string $title = '' ) {
			$this->ID = $id;
			$this->post_title = $title;
		}
	}
	class WP_Block {
		public $context;
		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$value = call_user_func_array( $registered[0], array_slice( array_merge( array( $value ), $args ), 0, $registered[1] ) );
			}
		}
		return $value;
	}
	function do_action( string $hook ): void {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				call_user_func( $registered[0] );
			}
		}
	}
	function register_block_style( $block, $style ) {
		$GLOBALS['cr_block_styles'][] = array( $block, $style );
		return true;
	}
	function wp_enqueue_block_style( $block, $style ) {
		$GLOBALS['cr_block_styles'][] = array( $block, $style );
	}
	function wp_style_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status && isset( $GLOBALS['cr_registered_styles'][ $handle ] );
	}
	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_registered_styles'][ $handle ] = array( $src, $deps, $version );
		return true;
	}
	function wp_style_add_data( $handle, $key, $value ) {
		$GLOBALS['cr_style_data'][ $handle ][ $key ] = $value;
		return true;
	}
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_enqueued'][ $handle ] = array( $src, $deps, $version );
	}
	function is_tax( $taxonomy = '', $term = '' ) {
		return 'kind' === $taxonomy && 'wish' === $term && 'wish' === ( $GLOBALS['cr_request']['kind'] ?? '' );
	}
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}
	function get_query_var( $name, $default = '' ) {
		return $GLOBALS['cr_request'][ $name ] ?? $default;
	}
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}
	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? get_the_ID() : (int) $post );
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'wish' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_request']['protected'][ $id ] );
	}
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_titles'][ $id ] ?? '' );
	}
	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/wish-' . $id . '/';
	}
	function get_the_date( $format = '', $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_dates'][ $id ] ?? 'October 18, 2026' );
	}
	function wp_get_attachment_image_src( $id, $size ) {
		return array( 'https://example.test/media-' . (int) $id . '.jpg', 320, 240 );
	}
	function __( $text ) {
		return $text;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $url ) {
		$url = (string) $url;
		return preg_match( '#^https?://#i', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_request']['stream'] );
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/wish.php';
	use Courtneyr\Child\Wish;

	$failures = array();

	function cr_wish_markup_check( string $name, callable $test ): void {
		global $failures;
		$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
		try {
			$ok = (bool) $test();
		} catch ( Throwable $error ) {
			$ok = false;
			$name .= ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	function cr_wish_post( int $id, string $title = 'Fictional Lantern' ): WP_Post {
		$post = new WP_Post( $id, $title );
		$GLOBALS['cr_posts'][ $id ] = $post;
		$GLOBALS['cr_terms'][ $id ] = true;
		$GLOBALS['cr_titles'][ $id ] = $title;
		$GLOBALS['cr_dates'][ $id ] = 'October 18, 2026';
		$GLOBALS['cr_current_post'] = $id;
		return $post;
	}

	function cr_wish_facts( string $name = 'Fictional Lantern', string $priority = 'high', string $type = 'book', string $url = 'https://shop.example.com/item', string $picture = 'https://images.example.org/item.png', bool $remote = false ): array {
		return array(
			'name' => $name,
			'name_kind' => 'title',
			'type_key' => $type,
			'priority' => $priority,
			'priority_text' => ucfirst( $priority ) . ' priority',
			'url' => $url,
			'picture' => array( 'url' => $picture, 'attachment_id' => $remote ? 0 : 91, 'remote' => $remote ),
		);
	}

	function cr_wish_visible_text( string $html ): string {
		$html = preg_replace( '/<(?:data|span)[^>]*\bhidden\b[^>]*>.*?<\/(?:data|span)>/s', '', $html );
		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $html ) ) );
	}

	cr_wish_markup_check(
		'hooks, item style, card paint and explicit enqueue use the frozen handle',
		static function (): bool {
			do_action( 'init' );
			$GLOBALS['cr_request'] = array( 'kind' => 'wish' );
			do_action( 'enqueue_block_assets' );
			$item = array_values( array_filter( $GLOBALS['cr_block_styles'], static fn( array $row ): bool => 'post-kinds-indieweb/stream-card' === $row[0] ) )[0] ?? array();
			$card = array_values( array_filter( $GLOBALS['cr_block_styles'], static fn( array $row ): bool => 'post-kinds-indieweb/wish-card' === $row[0] ) )[0] ?? array();
			return 'cr-wish-listing' === ( $item[1]['name'] ?? '' )
				&& 'courtneyr-wish' === ( $card[1]['handle'] ?? '' )
				&& array( 'courtneyr-paper', 'courtneyr-nav' ) === ( $card[1]['deps'] ?? null )
				&& array( 'courtneyr-paper', 'courtneyr-nav' ) === ( $GLOBALS['cr_enqueued']['courtneyr-wish'][1] ?? null )
				&& isset( $GLOBALS['cr_enqueued']['courtneyr-wish'] );
		}
	);

	cr_wish_markup_check(
		'the nav dependency registers only on wish views and carries the nav module path data',
		static function (): bool {
			$GLOBALS['cr_registered_styles'] = array();
			$GLOBALS['cr_style_data']        = array();
			$GLOBALS['cr_enqueued']          = array();
			$GLOBALS['cr_request']           = array( 'kind' => 'other' );
			do_action( 'enqueue_block_assets' );
			$untouched = ! isset( $GLOBALS['cr_registered_styles']['courtneyr-nav'] ) && ! isset( $GLOBALS['cr_enqueued']['courtneyr-wish'] );
			$GLOBALS['cr_request'] = array( 'kind' => 'wish' );
			do_action( 'enqueue_block_assets' );
			return $untouched
				&& isset( $GLOBALS['cr_registered_styles']['courtneyr-nav'] )
				&& str_ends_with( (string) ( $GLOBALS['cr_style_data']['courtneyr-nav']['path'] ?? '' ), '/assets/css/cr-nav.css' )
				&& isset( $GLOBALS['cr_enqueued']['courtneyr-wish'] );
		}
	);

	cr_wish_markup_check(
		'archive listing has one link, empty alt, priority text and no forbidden visible fields',
		static function (): bool {
			foreach ( array( 'high', 'medium', 'low' ) as $at => $priority ) {
				$post = cr_wish_post( 100 + $at, ucfirst( $priority ) . ' wish' );
				$html = Wish\archive_item( $post, cr_wish_facts( ucfirst( $priority ) . ' wish', $priority ), 3 );
				$text = cr_wish_visible_text( $html );
				if ( 1 !== preg_match_all( '/<a\b[^>]*\bhref=/i', $html )
					|| ! str_contains( $html, 'alt=""' )
					|| ! str_contains( $html, 'data-priority="' . $priority . '"' )
					|| ! preg_match( '/^<article class="cr-wish-listing pk-card--stream"/', $html )
					|| ! str_contains( $text, ucfirst( $priority ) . ' priority' )
					|| preg_match( '/\b(?:Reason|Price|January|February|March|April|May|June|July|August|September|October|November|December)\b|\d/', $text )
				) {
					return false;
				}
			}
			return true;
		}
	);

	cr_wish_markup_check(
		'hidden wish data appears only for a safe facts URL',
		static function (): bool {
			$post = cr_wish_post( 110 );
			$yes = Wish\archive_item( $post, cr_wish_facts(), 3 );
			$no = Wish\archive_item( $post, cr_wish_facts( 'Fictional Lantern', 'high', 'book', '' ), 3 );
			$unsafe = Wish\archive_item( $post, cr_wish_facts( 'Fictional Lantern', 'high', 'book', 'javascript:alert(1)' ), 3 );
			return 1 === substr_count( $yes, 'class="u-wish-of"' )
				&& ! str_contains( $no, 'u-wish-of' )
				&& ! str_contains( $unsafe, 'javascript:' )
				&& ! str_contains( $unsafe, '<data' );
		}
	);

	cr_wish_markup_check(
		'eager loading marks the first listing of each first-row section on every page',
		static function (): bool {
			$runs = array();
			foreach ( array( 1, 2 ) as $paged ) {
				Wish\eager_state( true );
				$GLOBALS['cr_request'] = array( 'paged' => $paged );
				$loads = array();
				foreach ( array( 'book', 'book', 'food', 'game', 'tech' ) as $at => $type ) {
					$facts = cr_wish_facts( 'Fictional Lantern', 'high', $type );
					if ( 'food' === $type ) {
						$facts['picture'] = array();
					}
					$html    = Wish\archive_item( cr_wish_post( 120 + $at ), $facts, 3 );
					$loads[] = str_contains( $html, 'loading="eager"' ) && str_contains( $html, 'fetchpriority="high"' );
				}
				$runs[] = $loads;
			}
			return array( array( true, false, false, true, false ), array( true, false, false, true, false ) ) === $runs;
		}
	);

	cr_wish_markup_check(
		'no picture uses a typographic tile and untitled facts omit p-name',
		static function (): bool {
			$post = cr_wish_post( 140, '' );
			$facts = cr_wish_facts( 'Fictional Lantern', 'medium', 'other', '', '' );
			$facts['name_kind'] = 'untitled';
			$html = Wish\archive_item( $post, $facts, 3 );
			return str_contains( $html, 'cr-wish-listing__media--tile' )
				&& str_contains( $html, '>Fictional Lantern</a>' )
				&& ! str_contains( $html, 'p-name' );
		}
	);

	cr_wish_markup_check(
		'facts-missing fallback keeps one non-empty title link',
		static function (): bool {
			$titled = Wish\archive_item( cr_wish_post( 150, 'Stored title' ), array(), 3 );
			$empty = Wish\archive_item( cr_wish_post( 151, '' ), array(), 3 );
			return str_contains( $titled, '>Stored title</a>' )
				&& str_contains( $titled, 'u-url p-name' )
				&& str_contains( $empty, '>Wish, October 18, 2026</a>' )
				&& ! str_contains( $empty, 'u-url p-name' )
				&& 1 === preg_match_all( '/<a\b[^>]*\bhref=/i', $empty );
		}
	);

	cr_wish_markup_check(
		'user text escapes and remote privacy is source-specific',
		static function (): bool {
			$post = cr_wish_post( 160 );
			$remote = Wish\archive_item( $post, cr_wish_facts( '<b>Ampersand & Co</b>', 'low', 'product', '', 'https://images.example.org/bad.png', true ), 3 );
			$local = Wish\archive_item( $post, cr_wish_facts(), 3 );
			return str_contains( $remote, '&lt;b&gt;Ampersand &amp; Co&lt;/b&gt;' )
				&& ! str_contains( $remote, '<b>Ampersand' )
				&& str_contains( $remote, 'referrerpolicy="no-referrer"' )
				&& ! str_contains( $local, 'referrerpolicy=' )
				&& str_contains( $local, 'width="320" height="240"' );
		}
	);

	cr_wish_markup_check(
		'dispatch preserves protected markup byte for byte',
		static function (): bool {
			$post = cr_wish_post( 170 );
			$GLOBALS['cr_facts'][170] = cr_wish_facts();
			$GLOBALS['cr_request'] = array( 'kind' => 'wish' );
			$html = '<article class="pk-card pk-card--protected k-wish"><h3 class="pk-title">Protected</h3></article>';
			$out = apply_filters( 'render_block_post-kinds-indieweb/stream-card', $html, array( 'attrs' => array( 'className' => 'is-style-cr-wish-listing' ) ), new WP_Block( array( 'postId' => 170 ) ) );
			return $html === $out;
		}
	);

	cr_wish_markup_check(
		'wish CSS and shared enqueue satisfy the frozen static gates',
		static function (): bool {
			$root = dirname( __DIR__, 2 );
			$css  = (string) file_get_contents( $root . '/assets/css/cr-wish.css' );
			$enqueue = (string) file_get_contents( $root . '/inc/enqueue.php' );
			$wish = (string) file_get_contents( $root . '/inc/wish.php' );
			$required = array( '@media (min-width: 48rem)', '@media (min-width: 64rem)', '@media (prefers-color-scheme: dark)', ':root[data-theme="dark"]', '@media (prefers-reduced-motion: reduce)', '@media (forced-colors: active)', 'nth-child(3n + 1):nth-last-child(2)', 'nth-child(3n + 1):last-child' );
			foreach ( $required as $needle ) {
				if ( ! str_contains( $css, $needle ) ) {
					return false;
				}
			}
			// The house paper tear (cr-favorite.css, the wish heading bands) is two hard-stop mask gradients, not a colour blend: allow those two strings and nothing else.
			$tearless = str_replace( array( 'linear-gradient(45deg, var(--cr-black) 25%, transparent 25%)', 'linear-gradient(315deg, var(--cr-black) 25%, transparent 25%)' ), '', $css );
			return 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgba?\s*\(/i', $css )
				&& 0 === preg_match( '/\b(?:filter|gradient|blur)\b|opacity\s*:\s*0\.75/i', $tearless )
				&& ! str_contains( $css, 'rotate(8deg)' )
				&& 0 === preg_match( '/font-size\s*:\s*(?:0\.[0-9]+|[0-9]+px)/i', $css )
				&& str_contains( $wish, "const STYLE_HANDLE   = 'courtneyr-wish'" )
				&& str_contains( $wish, "wp_enqueue_block_style(" )
				&& str_contains( $enqueue, "'cr-wish.css'" );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
