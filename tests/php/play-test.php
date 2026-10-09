<?php
/**
 * `php tests/php/play-test.php`: checks the play adapter without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_PLAY_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']          = array();
	$GLOBALS['cr_registered']     = array();
	$GLOBALS['cr_request']        = array();
	$GLOBALS['cr_posts']          = array();
	$GLOBALS['cr_terms']          = array();
	$GLOBALS['cr_titles']         = array();
	$GLOBALS['cr_current_post']   = 0;
	$GLOBALS['cr_styles']         = array();
	$GLOBALS['cr_stream_surface'] = false;
	$GLOBALS['cr_play_groups']    = array();
	$GLOBALS['cr_kind_facts']     = array();

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
		return 'play' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
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

	/** Date stub required by the direct dependency load. */
	function get_the_date() {
		return 'September 20, 2026';
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

namespace PKIW {
	if ( ! in_array( '--no-plugin', $GLOBALS['argv'], true ) ) {
		/** Return the configured play group. */
		function play_group( int $post_id ): string {
			return (string) ( $GLOBALS['cr_play_groups'][ $post_id ] ?? '' );
		}

		/** Return the configured kind facts. */
		function kind_facts( int $post_id ): array {
			return (array) ( $GLOBALS['cr_kind_facts'][ $post_id ] ?? array() );
		}

		/** Return a fallback name. */
		function untitled_name( \WP_Post $post ): string {
			return 'Untitled ' . $post->ID;
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

	require CR_PLAY_THEME_DIR . '/inc/media-shelf.php';
	$GLOBALS['cr_hooks'] = array();
	require CR_PLAY_THEME_DIR . '/inc/play.php';

	if ( in_array( '--no-plugin', $argv, true ) ) {
		$post                            = new WP_Post( 91 );
		$GLOBALS['cr_posts'][91]         = $post;
		$GLOBALS['cr_terms'][91]         = true;
		$GLOBALS['cr_current_post']      = 91;
		$html                            = '<article class="pk-card"><h3 class="pk-title"><a href="/play">Play</a></h3></article>';
		$result                          = \Courtneyr\Child\Play\dispatch( $html, array( 'attrs' => array( 'className' => 'is-style-cr-play-item' ) ), new WP_Block( array( 'postId' => 91 ) ) );
		echo $result === $html ? "NO_PLUGIN_OK\n" : "NO_PLUGIN_FAIL\n";
		exit( $result === $html ? 0 : 1 );
	}
}

namespace Courtneyr\Child\Play\Tests {

	use function Courtneyr\Child\Play\dispatch;
	use function Courtneyr\Child\Play\enqueue_styles;
	use function Courtneyr\Child\Play\is_archive_item;
	use function Courtneyr\Child\Play\label_ids;

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

	/** Reset request and adapter-filter state between cases. */
	function reset_state(): void {
		$GLOBALS['cr_request']        = array();
		$GLOBALS['cr_posts']          = array();
		$GLOBALS['cr_terms']          = array();
		$GLOBALS['cr_titles']         = array();
		$GLOBALS['cr_current_post']   = 0;
		$GLOBALS['cr_stream_surface'] = false;
		$GLOBALS['cr_play_groups']    = array();
		$GLOBALS['cr_kind_facts']     = array();
		$GLOBALS['cr_styles']         = array();
		unset( $GLOBALS['wp_query'] );
		unset( $GLOBALS['cr_hooks']['courtneyr_child_play_item'], $GLOBALS['cr_hooks']['courtneyr_child_play_stream_card'] );
	}

	/** Add one playable test post. */
	function play_post( int $id, string $group = '' ): \WP_Post {
		$post                            = new \WP_Post( $id );
		$GLOBALS['cr_posts'][ $id ]      = $post;
		$GLOBALS['cr_terms'][ $id ]      = true;
		$GLOBALS['cr_play_groups'][ $id ] = $group;
		$GLOBALS['cr_current_post']      = $id;
		return $post;
	}

	/** Render one card through the adapter. */
	function render_card( string $html, int $id, array $attrs = array() ): string {
		return dispatch( $html, array( 'attrs' => $attrs ), new \WP_Block( array( 'postId' => $id ) ) );
	}

	check_case(
		'load registers only the three play hooks and the play item style',
		static function (): bool {
			$hooks = $GLOBALS['cr_hooks'];
			$total = 0;
			foreach ( $hooks as $priorities ) {
				foreach ( $priorities as $callbacks ) {
					$total += count( $callbacks );
				}
			}
			$render = $hooks['render_block_post-kinds-indieweb/stream-card'][10][0] ?? null;
			$init   = $hooks['init'][10][0] ?? null;
			$assets = $hooks['enqueue_block_assets'][10][0] ?? null;
			if ( 3 !== $total || 3 !== ( $render[1] ?? 0 ) || 1 !== ( $init[1] ?? 0 ) || 1 !== ( $assets[1] ?? 0 ) ) {
				return false;
			}
			call_user_func( $init[0] );
			$style = $GLOBALS['cr_registered'][0] ?? array();
			return 'post-kinds-indieweb/stream-card' === ( $style[0] ?? '' ) && 'cr-play-item' === ( $style[1]['name'] ?? '' );
		}
	);

	check_case(
		'archive detection separates block style, taxonomy archive, Stream and unrelated surfaces',
		static function (): bool {
			reset_state();
			play_post( 1 );
			$html = '<article class="pk-card"><h3 class="pk-title"><a href="/one">One</a></h3></article>';
			if ( ! is_archive_item( array( 'attrs' => array( 'className' => 'other is-style-cr-play-item' ) ) ) ) {
				return false;
			}
			$GLOBALS['cr_request']['kind'] = 'play';
			if ( ! is_archive_item( array() ) ) {
				return false;
			}
			$GLOBALS['cr_request']        = array();
			$GLOBALS['cr_stream_surface'] = true;
			$stream_calls                 = 0;
			add_filter( 'courtneyr_child_play_stream_card', static function ( $value ) use ( &$stream_calls ) { ++$stream_calls; return $value; }, 10, 1 );
			$result = render_card( $html, 1 );
			if ( $html !== $result || 1 !== $stream_calls || is_archive_item( array() ) ) {
				return false;
			}
			$GLOBALS['cr_stream_surface'] = false;
			$stream_calls                 = 0;
			return $html === render_card( $html, 1 ) && 0 === $stream_calls;
		}
	);

	check_case(
		'archive style wins over Stream surface routing',
		static function (): bool {
			reset_state();
			play_post( 2 );
			$GLOBALS['cr_stream_surface'] = true;
			$item_calls                   = 0;
			$stream_calls                 = 0;
			add_filter( 'courtneyr_child_play_item', static function ( $value ) use ( &$item_calls ) { ++$item_calls; return $value; }, 10, 1 );
			add_filter( 'courtneyr_child_play_stream_card', static function ( $value ) use ( &$stream_calls ) { ++$stream_calls; return $value; }, 10, 1 );
			render_card( '<article class="pk-card"></article>', 2, array( 'className' => 'is-style-cr-play-item' ) );
			return 1 === $item_calls && 0 === $stream_calls;
		}
	);

	check_case(
		'empty HTML, missing and non-play posts, and absent plugin functions are no-ops',
		static function (): bool {
			reset_state();
			$post                        = new \WP_Post( 3 );
			$GLOBALS['cr_posts'][3]       = $post;
			$GLOBALS['cr_current_post']   = 3;
			$input                       = '<article class="pk-card">Untouched</article>';
			$calls                       = 0;
			add_filter( 'courtneyr_child_play_item', static function ( $value ) use ( &$calls ) { ++$calls; return $value; }, 10, 1 );
			$non_play = render_card( $input, 3, array( 'className' => 'is-style-cr-play-item' ) );
			$missing  = render_card( $input, 999, array( 'className' => 'is-style-cr-play-item' ) );
			$empty    = render_card( '', 3, array( 'className' => 'is-style-cr-play-item' ) );
			$output   = array();
			$status   = 0;
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin 2>&1', $output, $status );
			return $input === $non_play && $input === $missing && '' === $empty && 0 === $calls && 0 === $status && array( 'NO_PLUGIN_OK' ) === $output;
		}
	);

	check_case(
		'empty-group generic cards keep only the title, cover and hidden properties',
		static function (): bool {
			reset_state();
			play_post( 4 );
			$html = '<article class="pk-card pk-card--stream k-play h-entry"><div class="pk-badge">svg</div><div class="pk-body"><span class="pk-kindlabel">Play</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="/four">Title</a></h3><p class="pk-sub pk-stream-date"><time>September 20, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="cover.jpg" alt=""></div><p class="pk-excerpt p-summary">Excerpt</p><data class="p-rating" value="4"></data><span class="pk-entry-props" hidden>Entry</span><data class="u-uid" value="four"></data><div class="pk-meta"><a class="pk-link" href="/four">Read more</a></div></div></article>';
			$out  = render_card( $html, 4, array( 'className' => 'is-style-cr-play-item' ) );
			$gone = array( 'pk-kindlabel', 'pk-stream-date', 'pk-excerpt', 'pk-meta', 'pk-badge', 'pk-sub', 'pk-stars', 'pk-note' );
			foreach ( $gone as $class_name ) {
				if ( false !== strpos( $out, $class_name ) ) {
					return false;
				}
			}
			$kept = 1 === substr_count( $out, '<a ' ) && false !== strpos( $out, 'href="/four"' ) && false !== strpos( $out, 'pk-media' ) && false !== strpos( $out, 'data-cr-cover-fallback' ) && false !== strpos( $out, 'cr-play-item--ungrouped' ) && false !== strpos( $out, 'has-cover' ) && false !== strpos( $out, 'p-rating' ) && false !== strpos( $out, 'pk-entry-props' ) && false !== strpos( $out, 'u-uid' );
			$plain = '<article class="pk-card"><h3 class="pk-title"><a href="/four">Title</a></h3></article>';
			$bare  = render_card( $plain, 4, array( 'className' => 'is-style-cr-play-item' ) );
			return $kept && false !== strpos( $bare, 'cr-play-item--ungrouped' ) && false === strpos( $bare, 'has-cover' ) && false === strpos( $bare, 'data-cr-cover-fallback' );
		}
	);

	check_case(
		'empty-group generic cards remove media extras without changing grouped cards',
		static function (): bool {
			reset_state();
			play_post( 12 );
			$media_extras = '<p class="pk-media__caption" aria-live="polite">Photo by <a href="https://x.example/">Sam</a></p><ul class="pk-media__thumbs"><li class="pk-media__thumb"><button type="button" class="pk-media__thumb-button" aria-label="Show image 1 of 2"><img src="t.jpg" alt=""/></button></li><li class="pk-media__thumb pk-media__thumb--more"><a class="pk-media__thumb-more-link" href="/p/11">+3<span class="pk-sr-only"> more images on this post</span></a></li></ul>';
			$html         = '<article class="pk-card"><div class="pk-body"><h3 class="pk-title"><a href="/twelve">Twelve</a></h3><div class="pk-media"><img src="cover.jpg" alt="">' . $media_extras . '</div></div></article>';
			$out          = render_card( $html, 12, array( 'className' => 'is-style-cr-play-item' ) );
			$gone         = array( 'pk-media__caption', 'pk-media__thumbs', 'pk-media__thumb' );
			foreach ( $gone as $class_name ) {
				if ( false !== strpos( $out, $class_name ) ) {
					return false;
				}
			}
			$ungrouped = 1 === substr_count( $out, '<a ' )
				&& false !== strpos( $out, 'href="/twelve"' )
				&& 0 === substr_count( $out, '<button' )
				&& false !== strpos( $out, 'class="pk-media"' )
				&& false !== strpos( $out, '<img src="cover.jpg" alt="">' )
				&& false !== strpos( $out, 'data-cr-cover-fallback' )
				&& false !== strpos( $out, 'has-cover' )
				&& false !== strpos( $out, 'cr-play-item--ungrouped' )
				&& preg_match_all( '/<div\b/', $out ) === substr_count( $out, '</div>' );

			play_post( 13, 'video' );
			$video = render_card( $html, 13, array( 'className' => 'is-style-cr-play-item' ) );
			return $ungrouped
				&& false !== strpos( $video, $media_extras )
				&& 3 === substr_count( $video, '<a ' )
				&& 1 === substr_count( $video, '<button' );
		}
	);

	check_case(
		'empty-group play cards remove nested notes and every metadata link cleanly',
		static function (): bool {
			reset_state();
			play_post( 5 );
			$html = '<article class="pk-card"><div class="pk-body"><h3 class="pk-title"><a href="/five">Five</a></h3><p class="pk-sub">Status</p><div class="pk-stars">Stars</div><data class="p-rating" value="5"></data><div class="pk-note"><div class="inside-note">Nested</div></div><div class="pk-media"><img src="five.jpg" alt=""></div><div class="pk-meta"><a href="https://one.test">One</a><a href="https://two.test">Two</a><time>Today</time></div></div></article>';
			$out  = render_card( $html, 5, array( 'className' => 'is-style-cr-play-item' ) );
			return 1 === substr_count( $out, '<a ' ) && false !== strpos( $out, 'cr-play-item--ungrouped' ) && false === strpos( $out, 'inside-note' ) && preg_match_all( '/<div\b/', $out ) === substr_count( $out, '</div>' );
		}
	);

	check_case(
		'video and board archive items add one class and pass the full item-filter contract',
		static function (): bool {
			reset_state();
			play_post( 6, 'video' );
			play_post( 7, 'board' );
			$seen_three = array();
			$seen_four  = array();
			add_filter( 'courtneyr_child_play_item', static function ( $value, $group, $post ) use ( &$seen_three ) { $seen_three[] = array( $group, $post->ID ); return $value; }, 10, 3 );
			add_filter( 'courtneyr_child_play_item', static function ( $value, $group, $post, $ids ) use ( &$seen_four ) { $seen_four[] = array( $group, $post->ID, $ids ); return $value; }, 11, 4 );
			$input = '<article class="pk-card"><div class="pk-meta">Keep me</div></article>';
			$out_v = render_card( $input, 6, array( 'className' => 'is-style-cr-play-item' ) );
			$out_b = render_card( $input, 7, array( 'className' => 'is-style-cr-play-item' ) );
			$clean = static function ( string $value ): string { return str_replace( ' cr-play-item', '', $value ); };
			return false === strpos( $out_v, 'cr-play-item--ungrouped' ) && false === strpos( $out_b, 'cr-play-item--ungrouped' ) && $input === $clean( $out_v ) && $input === $clean( $out_b ) && array( array( 'video', 6 ), array( 'board', 7 ) ) === $seen_three && 'cr-play-title-6' === $seen_four[0][2]['title'] && 'cr-play-date-7' === $seen_four[1][2]['date'];
		}
	);

	check_case(
		'Stream cards for every group remain byte-identical and use only the Stream filter',
		static function (): bool {
			reset_state();
			$item_calls = 0;
			$seen       = array();
			add_filter( 'courtneyr_child_play_item', static function ( $value ) use ( &$item_calls ) { ++$item_calls; return $value; }, 10, 1 );
			add_filter( 'courtneyr_child_play_stream_card', static function ( $value, $group, $post ) use ( &$seen ) { $seen[] = array( $value, $group, $post->ID ); return $value; }, 10, 3 );
			$GLOBALS['cr_stream_surface'] = true;
			$input                        = '<article class="pk-card"><div class="pk-meta">Keep all</div></article>';
			foreach ( array( '' => 8, 'video' => 9, 'board' => 10 ) as $group => $id ) {
				play_post( $id, $group );
				if ( $input !== render_card( $input, $id ) ) {
					return false;
				}
			}
			foreach ( $seen as $call ) {
				if ( false !== strpos( $call[0], 'cr-play-item--ungrouped' ) ) {
					return false;
				}
			}
			return 0 === $item_calls && array( '', 'video', 'board' ) === array_column( $seen, 1 ) && array( 8, 9, 10 ) === array_column( $seen, 2 );
		}
	);

	check_case(
		'a non-string adapter filter result preserves the pre-filter HTML string',
		static function (): bool {
			reset_state();
			play_post( 11, 'video' );
			add_filter( 'courtneyr_child_play_item', static function () { return array( 'not', 'html' ); }, 10, 1 );
			$out = render_card( '<article class="pk-card">Eleven</article>', 11, array( 'className' => 'is-style-cr-play-item' ) );
			return is_string( $out ) && false !== strpos( $out, 'Eleven' ) && false !== strpos( $out, 'cr-play-item' );
		}
	);

	check_case(
		'label IDs detect normalized repeats, empty keys, query changes and non-play exclusions',
		static function (): bool {
			reset_state();
			foreach ( range( 20, 27 ) as $id ) {
				play_post( $id );
			}
			$GLOBALS['cr_kind_facts'] = array(
				20 => array( 'title' => 'Forest Paths' ),
				21 => array( 'title' => "  forest\t paths  " ),
				22 => array( 'title' => 'Orbit Table' ),
				23 => array( 'title' => '' ),
				24 => array( 'title' => 'Forest Paths' ),
				25 => array( 'title' => '' ),
				26 => array( 'title' => '' ),
				27 => array( 'title' => 'Forest Paths' ),
			);
			$GLOBALS['cr_titles'][23] = '';
			$GLOBALS['cr_titles'][25] = '';
			$GLOBALS['cr_titles'][26] = '';
			$GLOBALS['cr_terms'][27]  = false;
			$GLOBALS['wp_query']      = (object) array( 'posts' => array( $GLOBALS['cr_posts'][20], $GLOBALS['cr_posts'][21], $GLOBALS['cr_posts'][22], $GLOBALS['cr_posts'][23], $GLOBALS['cr_posts'][25] ) );
			$a = label_ids( 20 );
			$b = label_ids( 21 );
			$c = label_ids( 22 );
			$d = label_ids( 23 );
			$e = label_ids( 24 );
			if ( ! $a['repeats'] || ! $b['repeats'] || $c['repeats'] || $d['repeats'] || $e['repeats'] || 'cr-play-title-20' !== $a['title'] || 'cr-play-date-20' !== $a['date'] ) {
				return false;
			}
			$GLOBALS['wp_query'] = (object) array( 'posts' => array( $GLOBALS['cr_posts'][20], $GLOBALS['cr_posts'][22] ) );
			if ( label_ids( 20 )['repeats'] ) {
				return false;
			}
			$GLOBALS['wp_query'] = (object) array( 'posts' => array( $GLOBALS['cr_posts'][20], $GLOBALS['cr_posts'][27] ) );
			return ! label_ids( 20 )['repeats'];
		}
	);

	check_case(
		'play archive, play single, Stream and admin enqueue once while other requests do not',
		static function (): bool {
			$expected = array( COURTNEYR_CHILD_URI . '/assets/css/cr-play.css', array(), COURTNEYR_CHILD_VERSION );
			$run      = static function ( array $request, bool $surface = false ): array {
				$GLOBALS['cr_request']        = $request;
				$GLOBALS['cr_stream_surface'] = $surface;
				$GLOBALS['cr_styles']         = array();
				enqueue_styles();
				return $GLOBALS['cr_styles'];
			};
			reset_state();
			play_post( 30 );
			$archive = $run( array( 'kind' => 'play' ) );
			$single  = $run( array( 'single' => true, 'queried_id' => 30 ) );
			$stream  = $run( array(), true );
			$admin   = $run( array( 'admin' => true ) );
			$none    = array(
				$run( array( 'single' => true, 'queried_id' => 31 ) ),
				$run( array( 'kind' => 'listen' ) ),
				$run( array( 'kind' => 'watch' ) ),
			);
			$GLOBALS['cr_request'] = array( 'kind' => 'play' );
			$GLOBALS['cr_styles']  = array();
			enqueue_styles();
			enqueue_styles();
			return $expected === ( $archive['courtneyr-play'] ?? null ) && $expected === ( $single['courtneyr-play'] ?? null ) && $expected === ( $stream['courtneyr-play'] ?? null ) && $expected === ( $admin['courtneyr-play'] ?? null ) && array( array(), array(), array() ) === $none && 1 === count( $GLOBALS['cr_styles'] );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : $GLOBALS['cr_case_count'] . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
