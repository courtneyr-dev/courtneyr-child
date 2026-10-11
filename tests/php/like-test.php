<?php
/**
 * `php tests/php/like-test.php`: checks the like adapters without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_LIKE_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']          = array();
	$GLOBALS['cr_registered']     = array();
	$GLOBALS['cr_request']        = array();
	$GLOBALS['cr_posts']          = array();
	$GLOBALS['cr_terms']          = array();
	$GLOBALS['cr_titles']         = array();
	$GLOBALS['cr_dates']          = array();
	$GLOBALS['cr_current_post']   = 0;
	$GLOBALS['cr_styles']         = array();
	$GLOBALS['cr_stream_surface'] = false;
	$GLOBALS['cr_kind_facts']     = array();
	$GLOBALS['cr_kind_pictures']  = array();
	$GLOBALS['cr_groups']         = array();
	$GLOBALS['cr_passwords']      = array();

	/** Stand-in post. */
	class WP_Post {
		/** @var int Post ID. */
		public $ID;

		/** @param int $id Post ID. */
		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	/** Stand-in block instance. */
	class WP_Block {
		/** @var array<string, mixed> Block context. */
		public $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	/** Register a callback in the test hook registry. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Register an action in the test hook registry. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Run filters with WordPress-style accepted argument limits. */
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$all   = array_merge( array( $value ), $args );
				$value = call_user_func_array( $registered[0], array_slice( $all, 0, $registered[1] ) );
			}
		}
		return $value;
	}

	/** Run an action from the test hook registry. */
	function do_action( string $hook ): void {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				call_user_func( $registered[0] );
			}
		}
	}

	/** Record a block style registration. */
	function register_block_style( $block, $style ) {
		$GLOBALS['cr_registered'][] = array( $block, $style );
		return true;
	}

	/** Stub the current kind archive. */
	function is_tax( $taxonomy = '', $term = '' ) {
		$kind = (string) ( $GLOBALS['cr_request']['kind'] ?? '' );
		return 'kind' === $taxonomy && '' !== $kind && in_array( $kind, (array) $term, true );
	}

	/** Stub a single request. */
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Return the queried post ID. */
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	/** Stub the editor request flag. */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/** Stub term membership by post ID. */
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'like' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}

	/** Stub protected post state. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	/** Return a stand-in post. */
	function get_post( $post = null ) {
		$id = null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}

	/** Return the current loop post ID. */
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}

	/** Return a stored test title. */
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_titles'][ $id ] ?? '' );
	}

	/** Strip all tags from test text. */
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}

	/** Return the test post date. */
	function get_the_date( $format = '', $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) ( $post ?: $GLOBALS['cr_current_post'] );
		return (string) ( $GLOBALS['cr_dates'][ $id ] ?? 'September 20, 2026' );
	}

	/** Return a query variable. */
	function get_query_var( $name, $default = '' ) {
		return $GLOBALS['cr_request'][ $name ] ?? $default;
	}

	/** Record enqueued styles by handle. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $deps, $version );
	}

	/** Escape HTML. */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape an attribute. */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape a URL. */
	function esc_url( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Translate and escape HTML. */
	function esc_html__( $text ) {
		return esc_html( $text );
	}

	/** Translate text. */
	function __( $text ) {
		return $text;
	}

	/** Core HTML API helper stub. */
	function wp_has_noncharacters( $text ) {
		return false;
	}

	/** Core HTML API URI attribute list stub. */
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Return the test request's Stream state. */
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream_surface'];
	}
}

namespace Courtneyr\Child\MediaShelf {
	/** Cut every matching element from a fragment. */
	function cut_elements( string $html, string $tag, string $class_name ): string {
		$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
		while ( preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
			$start = (int) $m[0][1];
			$pos   = $start + strlen( $m[0][0] );
			$depth = 1;
			while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
				$pos    = (int) $t[0][1] + strlen( $t[0][0] );
				$depth += '/' === $t[1][0] ? -1 : 1;
			}
			if ( $depth > 0 ) {
				return $html;
			}
			$html = substr( $html, 0, $start ) . substr( $html, $pos );
		}
		return $html;
	}
}

namespace PKIW {
	/** Return configured like facts. */
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_kind_facts'][ $post_id ] ?? array() );
	}

	/** Return configured picture facts. */
	function kind_picture( int $post_id ): array {
		return (array) ( $GLOBALS['cr_kind_pictures'][ $post_id ] ?? array() );
	}

	/** Grouped archive stand-in. */
	class Grouped_Archive {
		/** Return the configured group object. */
		public static function group_of_post( string $kind, int $post_id ) {
			if ( 'like' !== $kind || ! array_key_exists( $post_id, $GLOBALS['cr_groups'] ) ) {
				return null;
			}
			return new class( (string) $GLOBALS['cr_groups'][ $post_id ] ) {
				/** @var string Group key. */
				private $key;

				/** @param string $key Group key. */
				public function __construct( string $key ) {
					$this->key = $key;
				}

				/** Return the group key. */
				public function key(): string {
					return $this->key;
				}
			};
		}

		/** Sectioning flag stub. */
		public static function is_sectioning(): bool {
			return true;
		}

		/** Current group stub. */
		public static function current_group() {
			return null;
		}
	}
}

namespace {
	$core = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api/';
	$files = array(
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-token.php',
		'class-wp-html-tag-processor.php',
	);
	foreach ( $files as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}

	require CR_LIKE_THEME_DIR . '/inc/stamps.php';
	if ( is_file( CR_LIKE_THEME_DIR . '/inc/like.php' ) ) {
		require CR_LIKE_THEME_DIR . '/inc/like.php';
	}
}

namespace Courtneyr\Child\Like\Tests {
	$failures = array();

	/** Print one named test case. */
	function check_case( string $name, callable $test ): void {
		global $failures;
		$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
		try {
			$ok = (bool) $test();
		} catch ( \Throwable $error ) {
			$ok   = false;
			$name = $name . ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	/** Reset request and adapter state between cases. */
	function reset_state(): void {
		$GLOBALS['cr_request']        = array();
		$GLOBALS['cr_posts']          = array();
		$GLOBALS['cr_terms']          = array();
		$GLOBALS['cr_titles']         = array();
		$GLOBALS['cr_dates']          = array();
		$GLOBALS['cr_current_post']   = 0;
		$GLOBALS['cr_stream_surface'] = false;
		$GLOBALS['cr_kind_facts']     = array();
		$GLOBALS['cr_kind_pictures']  = array();
		$GLOBALS['cr_groups']         = array();
		$GLOBALS['cr_passwords']      = array();
		$GLOBALS['cr_styles']         = array();
		unset( $GLOBALS['wp_query'] );
	}

	/** Add one like test post. */
	function like_post( int $id, string $group = 'notes.example.com', string $title = 'Field notes on paper sites', string $date = 'October 1, 2026' ): \WP_Post {
		$post                         = new \WP_Post( $id );
		$GLOBALS['cr_posts'][ $id ]   = $post;
		$GLOBALS['cr_terms'][ $id ]   = true;
		$GLOBALS['cr_groups'][ $id ]  = $group;
		$GLOBALS['cr_titles'][ $id ]  = $title;
		$GLOBALS['cr_dates'][ $id ]   = $date;
		$GLOBALS['cr_current_post']   = $id;
		$GLOBALS['cr_kind_facts'][ $id ] = array(
			'url'         => 'https://notes.example.com/paper-sites',
			'host'        => $group,
			'cited_title' => $title,
			'picture'     => array(
				'source'        => 'featured',
				'attachment_id' => 91,
				'url'           => 'https://notes.example.com/cover-' . $id . '.jpg',
				'alt'           => 'Ignored alt',
				'remote'        => false,
			),
		);
		$GLOBALS['cr_kind_pictures'][ $id ] = $GLOBALS['cr_kind_facts'][ $id ]['picture'];
		return $post;
	}

	/** Render a stream card through registered filters. */
	function render_stream_card( string $html, int $id, array $attrs = array() ): string {
		return \apply_filters( 'render_block_post-kinds-indieweb/stream-card', $html, array( 'attrs' => $attrs ), new \WP_Block( array( 'postId' => $id ) ) );
	}

	/** Render a like card through registered filters. */
	function render_like_card( string $html, int $id, array $attrs = array() ): string {
		return \apply_filters( 'render_block_post-kinds-indieweb/like-card', $html, array( 'attrs' => $attrs ), new \WP_Block( array( 'postId' => $id ) ) );
	}

	/** Return the main plugin like-card fixture. */
	function main_fixture(): string {
		return '<article class="wp-block-post-kinds-indieweb-like-card pk-card k-like h-cite u-like-of"><div class="pk-badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M0 0"/></svg></div><div class="pk-body"><span class="pk-kindlabel">Liked</span><div class="pk-caption"><data class="u-url" value="https://notes.example.com/paper-sites" hidden></data><h3 class="pk-title p-name"><a href="https://courtneyr.dev/2026/10/field-notes/">Field notes on paper sites</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-10-01T12:00:00+00:00">October 1, 2026</time></p><p class="pk-sub"><span class="p-author h-card"><span class="p-name">Rowan Vale</span></span></p></div><p class="pk-note p-content">A short description of the page.</p><div class="pk-embed pk-embed--photo"><img class="u-photo" src="https://notes.example.com/paper.jpg" alt="" loading="lazy" /></div><div class="pk-meta"><time class="dt-published" datetime="2026-09-01">September 1, 2026</time></div></div></article>';
	}

	/** Return the generic stream-card fixture. */
	function generic_fixture(): string {
		return '<article class="pk-card pk-card--stream k-like h-entry"><div class="pk-badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M0 0"/></svg></div><div class="pk-body"><span class="pk-kindlabel">Liked</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://courtneyr.dev/2026/10/field-notes/">Field notes on paper sites</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-10-01T12:00:00+00:00">October 1, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="https://courtneyr.dev/wp-content/uploads/a.jpg" alt="" loading="lazy" /></div><p class="pk-excerpt p-summary">An excerpt.</p><span class="pk-kind-cite h-cite u-like-of" hidden><data class="p-name" value="Field notes on paper sites"></data><data class="u-url" value="https://notes.example.com/paper-sites"></data></span><div class="pk-meta"><a class="pk-link" href="https://courtneyr.dev/2026/10/field-notes/">Read more<span class="pk-sr-only">: Field notes on paper sites</span></a></div></div></article>';
	}

	/** Return a protected fixture. */
	function protected_fixture(): string {
		return '<article class="pk-card pk-card--stream pk-card--protected k-like h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://courtneyr.dev/2026/10/field-notes/">Protected: Field notes</a></h3></article>';
	}

	/** Count anchors in a fragment. */
	function anchor_count( string $html ): int {
		return preg_match_all( '/<a\b/i', $html );
	}

	/** Extract the article class attribute. */
	function article_class( string $html ): string {
		return preg_match( '/<article\b[^>]*\bclass="([^"]*)"/', $html, $m ) ? $m[1] : '';
	}

	/** Whether the text contains none of the listed strings. */
	function contains_none( string $html, array $needles ): bool {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $html, $needle ) ) {
				return false;
			}
		}
		return true;
	}

	/** Check hook registration shape. */
	function hook_registered( string $hook, int $priority, int $accepted_args ): bool {
		$callbacks = $GLOBALS['cr_hooks'][ $hook ][ $priority ] ?? array();
		if ( 1 !== count( $callbacks ) ) {
			return false;
		}
		return $accepted_args === ( $callbacks[0][1] ?? null );
	}

	check_case(
		'like implementation file exists and loads',
		static function (): bool {
			return is_file( CR_LIKE_THEME_DIR . '/inc/like.php' ) && function_exists( 'Courtneyr\\Child\\Like\\dispatch' );
		}
	);

	check_case(
		'load registers exactly the frozen like hooks and block style',
		static function (): bool {
			$expected = array(
				'init'                                         => 1,
				'pkiw_kind_label'                              => 3,
				'render_block_post-kinds-indieweb/stream-card' => 3,
				'render_block_post-kinds-indieweb/like-card'   => 3,
				'enqueue_block_assets'                         => 1,
			);
			foreach ( $expected as $hook => $args ) {
				if ( ! hook_registered( $hook, 10, $args ) ) {
					return false;
				}
			}
			$featured = hook_registered( 'render_block_core/post-featured-image', 10, 2 ) || hook_registered( 'render_block', 10, 2 );
			if ( ! $featured ) {
				return false;
			}
			foreach ( array( 'courtneyr_child_kind_archive_page_sizes', 'pkiw_kind_archive_preview_per_page', 'render_block_core/post-title', 'the_title', 'pkiw_like_card_title_is_heading', 'body_class' ) as $forbidden ) {
				if ( isset( $GLOBALS['cr_hooks'][ $forbidden ] ) ) {
					return false;
				}
			}
			\do_action( 'init' );
			$style = $GLOBALS['cr_registered'][0] ?? array();
			return 'post-kinds-indieweb/stream-card' === ( $style[0] ?? '' ) && 'cr-sticker' === ( $style[1]['name'] ?? '' ) && 'Sticker' === ( $style[1]['label'] ?? '' );
		}
	);

	check_case(
		'kind label filter uses Liked only for like-card and stream-card contexts',
		static function (): bool {
			return 'Liked' === \apply_filters( 'pkiw_kind_label', 'Like', 'like', 'like-card' )
				&& 'Liked' === \apply_filters( 'pkiw_kind_label', 'Like', 'like', 'stream-card' )
				&& 'Like' === \apply_filters( 'pkiw_kind_label', 'Like', 'like', 'archive' )
				&& 'Watch' === \apply_filters( 'pkiw_kind_label', 'Watch', 'watch', 'stream-card' );
		}
	);

	check_case(
		'archive card cuts metadata, keeps like microformats and inserts the selected picture',
		static function (): bool {
			reset_state();
			like_post( 101 );
			$GLOBALS['cr_request']['kind'] = 'like';
			$GLOBALS['wp_query']           = (object) array( 'posts' => array( $GLOBALS['cr_posts'][101] ), 'current_post' => 0 );
			$out                           = render_stream_card( main_fixture(), 101, array( 'className' => 'is-style-cr-sticker' ) );
			$class                         = article_class( $out );
			return contains_none( $out, array( 'pk-badge', 'pk-kindlabel', 'pk-sub', 'pk-note', 'pk-meta', 'pk-media', 'pk-excerpt', 'Rowan Vale', 'A short description' ) )
				&& false !== strpos( $out, 'value="https://notes.example.com/paper-sites"' )
				&& false !== strpos( $out, 'h-cite u-like-of' )
				&& 1 === anchor_count( $out )
				&& false !== strpos( $out, 'href="https://courtneyr.dev/2026/10/field-notes/"' )
				&& false !== strpos( $class, 'cr-like-sticker' )
				&& preg_match( '/\bcr-paper--(?:sky-blue|periwinkle|light-orange|selective-yellow|light-gray|printer-ivory)\b/', $class )
				&& preg_match( '/\bcr-like-sticker--shape-[0-3]\b/', $class )
				&& 1 === substr_count( $out, 'data-cr-cover-fallback' )
				&& false !== strpos( $out, 'src="https://notes.example.com/cover-101.jpg"' )
				&& false !== strpos( $out, 'alt=""' )
				&& false !== strpos( $out, 'loading="eager"' )
				&& false !== strpos( $out, 'fetchpriority="high"' );
		}
	);

	check_case(
		'archive picture loading switches after the first three items and on paged views',
		static function (): bool {
			reset_state();
			like_post( 102 );
			$GLOBALS['cr_request']['kind'] = 'like';
			$GLOBALS['wp_query']           = (object) array( 'posts' => array( $GLOBALS['cr_posts'][102] ), 'current_post' => 3 );
			$later                         = render_stream_card( main_fixture(), 102, array( 'className' => 'is-style-cr-sticker' ) );
			$GLOBALS['wp_query']->current_post = 0;
			$GLOBALS['cr_request']['paged']    = 2;
			$paged                         = render_stream_card( main_fixture(), 102, array( 'className' => 'is-style-cr-sticker' ) );
			return false !== strpos( $later, 'data-cr-cover-fallback' )
				&& false !== strpos( $later, 'src="https://notes.example.com/cover-102.jpg"' )
				&& false !== strpos( $later, 'loading="lazy"' )
				&& false === strpos( $later, 'fetchpriority=' )
				&& false !== strpos( $paged, 'data-cr-cover-fallback' )
				&& false !== strpos( $paged, 'src="https://notes.example.com/cover-102.jpg"' )
				&& false !== strpos( $paged, 'loading="lazy"' )
				&& false === strpos( $paged, 'fetchpriority=' );
		}
	);

	check_case(
		'archive generic and protected cards keep the allowed minimal surfaces',
		static function (): bool {
			reset_state();
			like_post( 103 );
			$GLOBALS['cr_request']['kind'] = 'like';
			$GLOBALS['wp_query']           = (object) array( 'posts' => array( $GLOBALS['cr_posts'][103] ), 'current_post' => 0 );
			$generic                       = render_stream_card( generic_fixture(), 103, array( 'className' => 'is-style-cr-sticker' ) );
			$GLOBALS['cr_kind_facts'][103]['picture']['url'] = '';
			$plain                         = render_stream_card( main_fixture(), 103, array( 'className' => 'is-style-cr-sticker' ) );
			$protected_in                  = protected_fixture();
			$protected                     = render_stream_card( $protected_in, 103, array( 'className' => 'is-style-cr-sticker' ) );
			return 1 === anchor_count( $generic )
				&& false !== strpos( $generic, 'pk-kind-cite h-cite u-like-of' )
				&& contains_none( $generic, array( 'pk-badge', 'pk-kindlabel', 'pk-sub', 'pk-note', 'pk-meta', 'pk-media', 'pk-excerpt' ) )
				&& false === strpos( $plain, 'pk-embed' )
				&& false !== strpos( $protected, 'cr-like-sticker' )
				&& false !== strpos( $protected, 'Protected: Field notes' )
				&& false === strpos( $protected, 'data-cr-cover-fallback' );
		}
	);

	check_case(
		'archive duplicate labels are scoped to repeated titles in one group',
		static function (): bool {
			reset_state();
			like_post( 111, 'notes.example.com', 'Same Title', 'October 1, 2026' );
			like_post( 112, 'notes.example.com', 'Same Title', 'October 2, 2026' );
			like_post( 113, 'other.example.com', 'Same Title', 'October 3, 2026' );
			$GLOBALS['cr_request']['kind'] = 'like';
			$GLOBALS['wp_query']           = (object) array( 'posts' => array( $GLOBALS['cr_posts'][111], $GLOBALS['cr_posts'][112], $GLOBALS['cr_posts'][113] ), 'current_post' => 0 );
			$a = render_stream_card( main_fixture(), 111, array( 'className' => 'is-style-cr-sticker' ) );
			$b = render_stream_card( main_fixture(), 112, array( 'className' => 'is-style-cr-sticker' ) );
			$c = render_stream_card( main_fixture(), 113, array( 'className' => 'is-style-cr-sticker' ) );
			return false !== strpos( $a, 'aria-labelledby="cr-like-title-111 cr-like-date-111"' )
				&& false !== strpos( $a, 'id="cr-like-title-111"' )
				&& false !== strpos( $a, 'id="cr-like-date-111" class="screen-reader-text">, October 1, 2026</span>' )
				&& false !== strpos( $b, 'aria-labelledby="cr-like-title-112 cr-like-date-112"' )
				&& false !== strpos( $b, 'October 2, 2026' )
				&& false === strpos( $c, 'aria-labelledby=' )
				&& false === strpos( $c, 'cr-like-title-113' );
		}
	);

	check_case(
		'archive classes are deterministic and cover the full paper set across post IDs',
		static function (): bool {
			reset_state();
			$fills = array();
			for ( $id = 1; $id <= 200; ++$id ) {
				like_post( $id );
				$GLOBALS['cr_request']['kind'] = 'like';
				$GLOBALS['wp_query']           = (object) array( 'posts' => array( $GLOBALS['cr_posts'][ $id ] ), 'current_post' => 0 );
				$a = article_class( render_stream_card( main_fixture(), $id, array( 'className' => 'is-style-cr-sticker' ) ) );
				$b = article_class( render_stream_card( main_fixture(), $id, array( 'className' => 'is-style-cr-sticker' ) ) );
				if ( $a !== $b ) {
					return false;
				}
				if ( preg_match( '/\bcr-paper--([a-z-]+)\b/', $a, $m ) ) {
					$fills[ $m[1] ] = true;
				}
			}
			ksort( $fills );
			return array_keys( $fills ) === array( 'light-gray', 'light-orange', 'periwinkle', 'printer-ivory', 'selective-yellow', 'sky-blue' );
		}
	);

	check_case(
		'non-like and non-archive non-stream cards are unchanged',
		static function (): bool {
			reset_state();
			$post                       = new \WP_Post( 121 );
			$GLOBALS['cr_posts'][121]   = $post;
			$GLOBALS['cr_current_post'] = 121;
			$html                       = main_fixture();
			$non_like                   = render_stream_card( $html, 121, array( 'className' => 'is-style-cr-sticker' ) );
			$GLOBALS['cr_terms'][121]   = true;
			$plain                      = render_stream_card( $html, 121 );
			return $html === $non_like && $html === $plain;
		}
	);

	check_case(
		'stream cards keep the visible sticker parts and one permalink',
		static function (): bool {
			reset_state();
			like_post( 131 );
			$GLOBALS['cr_stream_surface'] = true;
			$out                          = render_stream_card( main_fixture(), 131 );
			$generic                      = render_stream_card( generic_fixture(), 131 );
			$class                        = article_class( $out );
			return false !== strpos( $out, 'pk-badge' )
				&& false !== strpos( $out, 'aria-hidden="true"' )
				&& false !== strpos( $out, '<span class="pk-kindlabel">Liked</span>' )
				&& false !== strpos( $out, 'pk-stream-date' )
				&& false !== strpos( $out, 'data class="u-url" value="https://notes.example.com/paper-sites"' )
				&& contains_none( $out, array( 'Rowan Vale', 'pk-note', 'pk-embed', 'pk-media', 'pk-excerpt', 'pk-meta' ) )
				&& 1 === anchor_count( $out )
				&& false !== strpos( $class, 'cr-like-sticker' )
				&& false !== strpos( $class, 'cr-like-sticker--stream' )
				&& false !== strpos( $class, 'cr-paper--selective-yellow' )
				&& false !== strpos( $generic, 'pk-kind-cite h-cite u-like-of' )
				&& 1 === anchor_count( $generic )
				&& substr_count( $out, 'p-name' ) <= substr_count( main_fixture(), 'p-name' )
				&& protected_fixture() === render_stream_card( protected_fixture(), 131 );
		}
	);

	check_case(
		'single clipping changes only presentation and uses the featured picture when available',
		static function (): bool {
			reset_state();
			like_post( 141 );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 141 );
			$GLOBALS['cr_kind_pictures'][141] = array( 'source' => 'featured', 'url' => 'https://notes.example.com/featured.jpg', 'alt' => 'Featured alt' );
			$single = '<article class="wp-block-post-kinds-indieweb-like-card pk-card k-like h-cite u-like-of"><div class="pk-body"><span class="pk-kindlabel">Liked</span><div class="pk-caption"><data class="u-url" value="https://notes.example.com/paper-sites" hidden></data><h2 class="pk-title p-name"><a href="https://courtneyr.dev/2026/10/field-notes/">Field notes</a></h2></div><div class="pk-embed pk-embed--photo"><img class="u-photo" src="https://notes.example.com/old.jpg" alt="Keep this" loading="lazy"></div><p class="pk-original"><a class="u-url" href="https://notes.example.com/paper-sites">Read the original</a></p></div></article>';
			$out    = render_like_card( $single, 141 );
			$no_img = '<article class="pk-card k-like"><div class="pk-body"><div class="pk-caption"><h2 class="pk-title p-name"><a href="https://courtneyr.dev/p/141/">Field notes</a></h2></div><p class="pk-original"><a class="u-url" href="https://notes.example.com/paper-sites">Read the original</a></p></div></article>';
			$added  = render_like_card( $no_img, 141 );
			return false !== strpos( article_class( $out ), 'cr-like-clipping' )
				&& false !== strpos( article_class( $out ), 'cr-paper--printer-ivory' )
				&& false !== strpos( $out, 'data-cr-cover-fallback' )
				&& false !== strpos( $out, 'src="https://notes.example.com/featured.jpg"' )
				&& false !== strpos( $out, 'alt="Keep this"' )
				&& anchor_count( $single ) === anchor_count( $out )
				&& substr_count( $single, '<data ' ) === substr_count( $out, '<data ' )
				&& false !== strpos( $out, '<p class="pk-original"><a class="u-url"' )
				&& false !== strpos( $added, 'src="https://notes.example.com/featured.jpg"' )
				&& false !== strpos( $added, 'alt="Featured alt"' );
		}
	);

	check_case(
		'single clipping and featured image removal are guarded to the like single',
		static function (): bool {
			reset_state();
			like_post( 151 );
			$html = main_fixture();
			if ( $html !== render_like_card( $html, 151 ) ) {
				return false;
			}
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 999 );
			if ( $html !== render_like_card( $html, 151 ) ) {
				return false;
			}
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 151 );
			$GLOBALS['cr_kind_pictures'][151]['suppress_featured'] = true;
			$featured = '<figure class="single-post__featured"><img src="featured.jpg" alt=""></figure>';
			$dropped  = \apply_filters( 'render_block_core/post-featured-image', $featured, array( 'attrs' => array( 'className' => 'single-post__featured' ) ) );
			if ( $featured === $dropped ) {
				$dropped = \apply_filters( 'render_block', $featured, array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'single-post__featured' ) ) );
			}
			$other = \apply_filters( 'render_block_core/post-featured-image', $featured, array( 'attrs' => array( 'className' => 'other' ) ) );
			return '' === $dropped && $featured === $other;
		}
	);

	check_case(
		'enqueues the like stylesheet only on like archive, like single, Stream and admin',
		static function (): bool {
			$expected = array( COURTNEYR_CHILD_URI . '/assets/css/cr-like.css', array( 'courtneyr-paper' ), COURTNEYR_CHILD_VERSION );
			$run      = static function ( array $request, bool $surface = false ): array {
				$GLOBALS['cr_request']        = $request;
				$GLOBALS['cr_stream_surface'] = $surface;
				$GLOBALS['cr_styles']         = array();
				\do_action( 'enqueue_block_assets' );
				return $GLOBALS['cr_styles'];
			};
			reset_state();
			like_post( 161 );
			$archive = $run( array( 'kind' => 'like' ) );
			$single  = $run( array( 'single' => true, 'queried_id' => 161 ) );
			$stream  = $run( array(), true );
			$admin   = $run( array( 'admin' => true ) );
			$none    = array(
				$run( array( 'single' => true, 'queried_id' => 162 ) ),
				$run( array( 'kind' => 'read' ) ),
				$run( array( 'page' => true ) ),
			);
			return $expected === ( $archive['courtneyr-like'] ?? null )
				&& $expected === ( $single['courtneyr-like'] ?? null )
				&& $expected === ( $stream['courtneyr-like'] ?? null )
				&& $expected === ( $admin['courtneyr-like'] ?? null )
				&& array( array(), array(), array() ) === $none;
		}
	);

	check_case(
		'source guards keep like.php fact-only and synthetic-name safe',
		static function (): bool {
			$source = is_file( CR_LIKE_THEME_DIR . '/inc/like.php' ) ? (string) file_get_contents( CR_LIKE_THEME_DIR . '/inc/like.php' ) : '';
			if ( '' === $source ) {
				return false;
			}
			foreach ( array( 'get_post_meta', 'get_metadata', 'update_post_meta', 'add_post_meta', 'wp_set_object_terms', 'p-name' ) as $forbidden ) {
				if ( false !== strpos( $source, $forbidden ) ) {
					return false;
				}
			}
			return 0 === preg_match( '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $source );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
