<?php
/**
 * `php tests/php/wish-stream-test.php`: standalone wish Stream checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_posts'] = array();
	$GLOBALS['cr_terms'] = array();
	$GLOBALS['cr_facts'] = array();
	$GLOBALS['cr_stream'] = false;
	$GLOBALS['cr_current_post'] = 0;

	class WP_Post {
		public $ID;
		public function __construct( int $id ) {
			$this->ID = $id;
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
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}
	function get_post( $post = null ) {
		$id = null === $post ? get_the_ID() : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'wish' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}
	function wp_has_noncharacters( $text ) {
		return false;
	}
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream'];
	}
}

namespace Courtneyr\Child\Wish {
	function is_archive_item( array $block ): bool {
		return false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-cr-wish-listing' );
	}
}

namespace Courtneyr\Child\MediaShelf {
	function cut_elements( string $html, string $tag, string $class_name ): string {
		$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
		while ( preg_match( $open, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$start = (int) $match[0][1];
			$pos = $start + strlen( $match[0][0] );
			$depth = 1;
			while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $tag_match, PREG_OFFSET_CAPTURE, $pos ) ) {
				$pos = (int) $tag_match[0][1] + strlen( $tag_match[0][0] );
				$depth += '/' === $tag_match[1][0] ? -1 : 1;
			}
			if ( $depth > 0 ) {
				return $html;
			}
			$html = substr( $html, 0, $start ) . substr( $html, $pos );
		}
		return $html;
	}
}

namespace Courtneyr\Child\StreamRead {
	function take_element( string &$html, string $tag, string $class_name ): string {
		$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
		if ( ! preg_match( $open, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}
		$start = (int) $match[0][1];
		$pos = $start + strlen( $match[0][0] );
		$depth = 1;
		while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $tag_match, PREG_OFFSET_CAPTURE, $pos ) ) {
			$pos = (int) $tag_match[0][1] + strlen( $tag_match[0][0] );
			$depth += '/' === $tag_match[1][0] ? -1 : 1;
		}
		if ( $depth > 0 ) {
			return '';
		}
		$out = substr( $html, $start, $pos - $start );
		$html = substr( $html, 0, $start ) . substr( $html, $pos );
		return $out;
	}
}

namespace Courtneyr\Child\Stamps {
	function seed( string $value ): int {
		return (int) $value;
	}
	function pick( int $seed, int $offset, int $count ): int {
		return ( $seed + $offset ) % $count;
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
	}
}

namespace {
	$core = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api = $core . '/wp-includes/html-api/';
	foreach ( array( 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}
	require dirname( __DIR__, 2 ) . '/inc/stream-wish.php';

	$failures = array();

	function cr_wish_stream_check( string $name, callable $test ): void {
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

	function cr_wish_stream_post( int $id, array $facts = array() ): void {
		$GLOBALS['cr_posts'][ $id ] = new WP_Post( $id );
		$GLOBALS['cr_terms'][ $id ] = true;
		$GLOBALS['cr_facts'][ $id ] = $facts;
		$GLOBALS['cr_current_post'] = $id;
		$GLOBALS['cr_stream'] = true;
	}

	function cr_wish_card_fixture( bool $media = true ): string {
		return '<article class="wp-block-post-kinds-indieweb-wish-card pk-card k-wish h-cite pk-wish pk-wish--has-photo" data-priority="high">'
			. '<div class="pk-badge"><svg aria-hidden="true"></svg></div><div class="pk-body"><span class="pk-kindlabel">Wish</span><div class="pk-caption">'
			. '<data class="u-url u-wish-of" value="https://shop.example.com/item" hidden></data><h3 class="pk-title p-name"><a href="https://example.test/wish-one/">Fictional Lantern</a></h3>'
			. '<p class="pk-sub pk-stream-date"><time datetime="2026-10-18T12:00:00+00:00">October 18, 2026</time></p>'
			. '<p class="pk-sub"><span class="pk-wish-chip">Book</span><span class="pk-wish-chip">High priority</span><span class="pk-wish-chip">$129</span></p></div>'
			. ( $media ? '<div class="pk-media pk-wish-photo"><img class="pk-thumb u-photo" alt="" loading="lazy" src="https://images.example.org/item.png"></div>' : '' )
			. '</div></article><span class="pk-entry-props" hidden><data class="dt-published" value="2026-10-18"></data></span>';
	}

	function cr_wish_generic_fixture( bool $media = true ): string {
		return '<article class="pk-card pk-card--stream k-wish h-entry"><div class="pk-badge"><svg aria-hidden="true"></svg></div><div class="pk-body"><span class="pk-kindlabel">Wish</span>'
			. '<div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/wish-two/">Train journey</a></h3><p class="pk-sub pk-stream-date"><time datetime="2026-10-18">October 18, 2026</time></p></div>'
			. ( $media ? '<div class="pk-media pk-media--stream"><img class="u-photo" src="https://example.test/item.png" alt=""><a class="pk-media__thumb-more-link" href="https://example.test/wish-two/#more">+2</a></div>' : '' )
			. '<p class="pk-excerpt">Excerpt</p><span class="u-wish-of" hidden><data class="u-url" value="https://tickets.example.net/train"></data></span>'
			. '<div class="pk-meta"><a href="https://example.test/wish-two/">Read more</a></div></div></article>';
	}

	function cr_wish_render_stream( string $html, int $id, array $attrs = array() ): string {
		return apply_filters( 'render_block_post-kinds-indieweb/stream-card', $html, array( 'attrs' => $attrs ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_wish_stream_check(
		'card-only wish gains clipping, one tilt, tape, chip classes and moved date',
		static function (): bool {
			cr_wish_stream_post( 41, array( 'priority' => 'high' ) );
			$out = cr_wish_render_stream( cr_wish_card_fixture(), 41 );
			return str_contains( $out, 'cr-wish-clipping' )
				&& 1 === preg_match_all( '/cr-wish-clipping--tilt-[0-3]/', $out )
				&& 1 === substr_count( $out, 'cr-wish-clipping__tape' )
				&& ! str_contains( $out, 'pk-badge' )
				&& str_contains( $out, 'pk-wish-chip pk-wish-chip--type' )
				&& str_contains( $out, 'pk-wish-chip pk-wish-chip--priority' )
				&& str_contains( $out, 'pk-wish-chip pk-wish-chip--price' )
				&& strpos( $out, 'pk-wish-chip--price' ) < strpos( $out, 'pk-stream-date' )
				&& 1 === preg_match_all( '/<a\b[^>]*href=/i', $out )
				&& str_contains( $out, '<data class="u-url u-wish-of" value="https://shop.example.com/item" hidden>')
				&& str_contains( $out, '<span class="pk-entry-props" hidden>')
				&& str_contains( $out, 'data-cr-cover-fallback="true"' );
		}
	);

	cr_wish_stream_check(
		'generic card loses excerpt and meta while keeping one title link',
		static function (): bool {
			cr_wish_stream_post( 42, array( 'priority' => 'medium' ) );
			$out = cr_wish_render_stream( cr_wish_generic_fixture(), 42 );
			return str_contains( $out, 'cr-wish-clipping' )
				&& str_contains( $out, 'data-priority="medium"' )
				&& ! str_contains( $out, 'pk-meta' )
				&& ! str_contains( $out, 'pk-excerpt' )
				&& ! str_contains( $out, 'pk-media' )
				&& ! str_contains( $out, 'pk-media__thumb-more-link' )
				&& 1 === preg_match_all( '/<a\b[^>]*href=/i', $out )
				&& str_contains( $out, '<span class="u-wish-of" hidden>');
		}
	);

	cr_wish_stream_check(
		'protected, archive and non-Stream cards pass through unchanged',
		static function (): bool {
			cr_wish_stream_post( 43, array( 'priority' => 'high' ) );
			$protected = '<article class="pk-card pk-card--stream pk-card--protected k-wish h-entry"><h3><a href="https://example.test/p/">Protected</a></h3></article>';
			$protected_out = cr_wish_render_stream( $protected, 43 );
			$archive = cr_wish_card_fixture();
			$archive_out = cr_wish_render_stream( $archive, 43, array( 'className' => 'is-style-cr-wish-listing' ) );
			$GLOBALS['cr_stream'] = false;
			$plain = cr_wish_generic_fixture();
			$plain_out = cr_wish_render_stream( $plain, 43 );
			return $protected === $protected_out && $archive === $archive_out && $plain === $plain_out;
		}
	);

	cr_wish_stream_check(
		'facts-empty recognized cards still gain classes and no-media cards gain no frame marker',
		static function (): bool {
			cr_wish_stream_post( 44, array() );
			$out = cr_wish_render_stream( cr_wish_generic_fixture( false ), 44 );
			return str_contains( $out, 'cr-wish-clipping' )
				&& ! str_contains( $out, 'data-priority=' )
				&& ! str_contains( $out, 'data-cr-cover-fallback' )
				&& ! str_contains( $out, 'pk-media' );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
