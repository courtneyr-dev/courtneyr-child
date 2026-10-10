<?php
/**
 * `php tests/php/play-board-test.php`: checks the board-game adapters without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_PLAY_BOARD_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']              = array();
	$GLOBALS['cr_styles']             = array();
	$GLOBALS['cr_posts']              = array();
	$GLOBALS['cr_titles']             = array();
	$GLOBALS['cr_facts']              = array();
	$GLOBALS['cr_pictures']           = array();
	$GLOBALS['cr_permalink']          = array();
	$GLOBALS['cr_passwords']          = array();
	$GLOBALS['cr_request']            = array();
	$GLOBALS['cr_url_to_postid']      = array();
	$GLOBALS['cr_attachment_calls']   = array();
	$GLOBALS['cr_attachment_returns'] = array();
	$GLOBALS['cr_play_groups']        = array();
	$GLOBALS['cr_stream_surface']     = false;

	/** Stand-in post. */
	class WP_Post {
		/** @var int Post ID. */
		public $ID;

		/** @var string Post title. */
		public $post_title = '';

		/** @param int $id Post ID. */
		public function __construct( int $id ) {
			$this->ID         = $id;
			$this->post_title = (string) ( $GLOBALS['cr_titles'][ $id ] ?? '' );
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

	/** Return the test title. */
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_titles'][ $id ] ?? '' );
	}

	/** Strip tags. */
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}

	/** Return the test permalink. */
	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return (string) ( $GLOBALS['cr_permalink'][ $id ] ?? 'https://example.test/post-' . $id . '/' );
	}

	/** Return a date string or ISO date. */
	function get_post_time( $format, $gmt = false, $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		if ( 'Y-m-d' === $format ) {
			return '2026-09-' . str_pad( (string) ( ( $id % 28 ) + 1 ), 2, '0', STR_PAD_LEFT );
		}
		return '2026-09-' . str_pad( (string) ( ( $id % 28 ) + 1 ), 2, '0', STR_PAD_LEFT ) . 'T12:34:56+00:00';
	}

	/** Return the visible date. */
	function get_the_date( $format = '', $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'Sep ' . ( ( $id % 28 ) + 1 ) . ', 2026';
	}

	/** Password protection stub. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	/** Escape HTML. */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape attributes. */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape URLs for tests. */
	function esc_url( $url ) {
		$url = trim( (string) $url );
		return preg_match( '#^https?://#', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
	}

	/** Translate text. */
	function __( $text ) {
		return $text;
	}

	/** Record image helper calls. */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ) {
		$GLOBALS['cr_attachment_calls'][] = array( $attachment_id, $size, $icon, $attr );
		return (string) ( $GLOBALS['cr_attachment_returns'][ $attachment_id ] ?? '' );
	}

	/** Singular request stub. */
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Admin request stub. */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/** Queried object stub. */
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	/** Record style enqueues. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $deps, $version );
	}

	/** Map permalink to post ID. */
	function url_to_postid( $url ) {
		return (int) ( $GLOBALS['cr_url_to_postid'][ (string) $url ] ?? 0 );
	}

	/** Core HTML API helper stub. */
	function wp_has_noncharacters( $text ) {
		return false;
	}

	/** Core HTML API URI attributes. */
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Stream surface stub. */
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream_surface'];
	}
}

namespace Courtneyr\Child\Play {
	if ( ! in_array( '--no-play', $GLOBALS['argv'], true ) ) {
		/** Play archive stub. */
		function is_play_archive(): bool {
			return ! empty( $GLOBALS['cr_request']['archive'] );
		}

		/** Play single stub. */
		function is_play_single(): bool {
			return ! empty( $GLOBALS['cr_request']['play_single'] );
		}
	}
}

namespace PKIW {
	if ( ! in_array( '--no-plugin', $GLOBALS['argv'], true ) ) {
		/** Return configured kind facts. */
		function kind_facts( int $post_id ): array {
			return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
		}

		/** Return configured picture. */
		function kind_picture( int $post_id ): array {
			return (array) ( $GLOBALS['cr_pictures'][ $post_id ] ?? array( 'url' => '', 'attachment_id' => 0, 'suppress_featured' => false ) );
		}

		/** Return configured play group. */
		function play_group( int $post_id ): string {
			return (string) ( $GLOBALS['cr_play_groups'][ $post_id ] ?? '' );
		}

		/** Fallback title. */
		function untitled_name( \WP_Post $post ): string {
			return 'Untitled stand-in ' . $post->ID;
		}

		/** Kind label. */
		function get_kind_label( string $label, string $kind, string $context ): string {
			return $label;
		}

		/** Hidden play citation. */
		function stream_card_kind_cite( \WP_Post $post ): string {
			$facts = kind_facts( $post->ID );
			$inner = '';
			$title = is_scalar( $facts['title'] ?? null ) ? trim( (string) $facts['title'] ) : '';
			if ( '' !== $title ) {
				$inner .= '<data class="p-name" value="' . \esc_attr( $title ) . '"></data>';
			}
			$url = is_scalar( $facts['game_url'] ?? null ) ? trim( (string) $facts['game_url'] ) : '';
			if ( '' !== $url ) {
				$inner .= '<data class="u-url" value="' . \esc_url( $url ) . '"></data>';
			}
			$bgg = is_scalar( $facts['bgg_id'] ?? null ) ? trim( (string) $facts['bgg_id'] ) : '';
			if ( '' !== $bgg ) {
				$inner .= '<data class="u-uid" value="' . \esc_url( 'https://boardgamegeek.com/boardgame/' . $bgg ) . '"></data>';
			}
			return '' === $inner ? '' : '<span class="pk-kind-cite h-cite u-play-of" hidden>' . $inner . '</span>';
		}

		/** Hidden author. */
		function entry_author_html( \WP_Post $post ): string {
			return '<span class="p-author h-card"><a class="u-url p-name" href="https://example.test/author/courtney/" tabindex="-1">Courtney</a></span>';
		}
	}
}

namespace {
	$core  = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api   = $core . '/wp-includes/html-api/';
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

	require CR_PLAY_BOARD_THEME_DIR . '/inc/stamps.php';
	require CR_PLAY_BOARD_THEME_DIR . '/inc/play-board.php';

	if ( in_array( '--no-plugin', $argv, true ) ) {
		$post = new WP_Post( 91 );
		$html = '<article class="pk-card">Input</article>';
		$ids  = array( 'title' => 't91', 'date' => 'd91', 'repeats' => false );
		$ok   = $html === \Courtneyr\Child\PlayBoard\spine( $html, 'board', $post, $ids )
			&& $html === \Courtneyr\Child\PlayBoard\slip( $html, 'board', $post )
			&& false !== strpos( \Courtneyr\Child\PlayBoard\picks( '<li class="pkiw-staff-picks__item"><div class="pkiw-staff-picks__box"></div><h3 class="pkiw-staff-picks__title"><a class="pkiw-staff-picks__link" href="https://example.test/unmapped/">No Map</a></h3><p class="pkiw-staff-picks__rating">Rated 5 of 5</p></li>' ), 'data-cr-cover-fallback' );
		echo $ok ? "NO_PLUGIN_OK\n" : "NO_PLUGIN_FAIL\n";
		exit( $ok ? 0 : 1 );
	}

	if ( in_array( '--no-play', $argv, true ) ) {
		$GLOBALS['cr_request'] = array( 'archive' => true );
		\Courtneyr\Child\PlayBoard\enqueue_styles();
		$ok = array() === $GLOBALS['cr_styles'];
		echo $ok ? "NO_PLAY_OK\n" : "NO_PLAY_FAIL\n";
		exit( $ok ? 0 : 1 );
	}
}

namespace Courtneyr\Child\PlayBoard\Tests {
	use const Courtneyr\Child\PlayBoard\PAPERS;
	use function Courtneyr\Child\PlayBoard\drop_featured_image;
	use function Courtneyr\Child\PlayBoard\enqueue_styles;
	use function Courtneyr\Child\PlayBoard\paper_for;
	use function Courtneyr\Child\PlayBoard\picks;
	use function Courtneyr\Child\PlayBoard\slip;
	use function Courtneyr\Child\PlayBoard\spine;

	$failures = array();

	/** Print one named test case. */
	function check_case( string $name, callable $test ): void {
		global $failures;
		$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
		try {
			$result = $test();
			if ( is_string( $result ) && 0 === strpos( $result, 'SKIP ' ) ) {
				$GLOBALS['cr_skipped_count'] = ( $GLOBALS['cr_skipped_count'] ?? 0 ) + 1;
				echo $result, "\n";
				return;
			}
			$ok = (bool) $result;
		} catch ( \Throwable $error ) {
			$ok   = false;
			$name = $name . ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	/** Reset mutable state. */
	function reset_state(): void {
		$GLOBALS['cr_styles']             = array();
		$GLOBALS['cr_posts']              = array();
		$GLOBALS['cr_titles']             = array();
		$GLOBALS['cr_facts']              = array();
		$GLOBALS['cr_pictures']           = array();
		$GLOBALS['cr_permalink']          = array();
		$GLOBALS['cr_passwords']          = array();
		$GLOBALS['cr_request']            = array();
		$GLOBALS['cr_url_to_postid']      = array();
		$GLOBALS['cr_attachment_calls']   = array();
		$GLOBALS['cr_attachment_returns'] = array();
		$GLOBALS['cr_play_groups']        = array();
		$GLOBALS['cr_stream_surface']     = false;
	}

	/** Create a configured board post. */
	function board_post( int $id, array $facts = array() ): \WP_Post {
		$GLOBALS['cr_titles'][ $id ] = (string) ( $facts['post_title'] ?? 'Post ' . $id );
		unset( $facts['post_title'] );
		$post                         = new \WP_Post( $id );
		$GLOBALS['cr_posts'][ $id ]    = $post;
		$GLOBALS['cr_facts'][ $id ]    = $facts ? $facts : array(
			'title'        => 'Forest Paths',
			'platform'     => 'Board Game',
			'rating_label' => 'Rated 5 of 5',
			'game_url'     => 'https://example.test/games/forest-paths',
			'bgg_id'       => '9990001',
		);
		$GLOBALS['cr_permalink'][ $id ] = 'https://example.test/play/forest-paths-' . $id . '/';
		return $post;
	}

	/** Count visible anchors, skipping anything under hidden. */
	function visible_anchor_count( string $html ): int {
		$doc = new \DOMDocument();
		$old = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $old );
		$count = 0;
		foreach ( $doc->getElementsByTagName( 'a' ) as $anchor ) {
			$hidden = false;
			for ( $node = $anchor; $node instanceof \DOMElement; $node = $node->parentNode ) {
				if ( $node->hasAttribute( 'hidden' ) ) {
					$hidden = true;
					break;
				}
			}
			if ( ! $hidden ) {
				++$count;
			}
		}
		return $count;
	}

	/** Whether the output contains forbidden plugin structure classes. */
	function has_forbidden_class( string $html ): bool {
		foreach ( array( 'pk-box', 'pk-facts', 'pk-links', 'pk-scorepad', 'pk-kindlabel', 'pk-sub', 'pk-stars', 'pk-meta', 'pk-stream-date', 'pk-media', 'pk-badge', 'pk-note', 'pk-excerpt' ) as $class ) {
			if ( false !== strpos( $html, $class ) ) {
				return true;
			}
		}
		return false;
	}

	check_case(
		'load registers the five board hooks',
		static function (): bool {
			$expected = array(
				'courtneyr_child_play_item'                    => 4,
				'courtneyr_child_play_stream_card'             => 3,
				'render_block_post-kinds-indieweb/staff-picks' => 1,
				'render_block'                                 => 2,
				'enqueue_block_assets'                         => 0,
			);
			foreach ( $expected as $hook => $accepted ) {
				$registered = $GLOBALS['cr_hooks'][ $hook ][10][0] ?? null;
				if ( ! is_array( $registered ) || $accepted !== $registered[1] ) {
					return false;
				}
			}
			$total = 0;
			foreach ( $GLOBALS['cr_hooks'] as $priorities ) {
				foreach ( $priorities as $callbacks ) {
					$total += count( $callbacks );
				}
			}
			return 5 === $total;
		}
	);

	check_case(
		'no-plugin subprocess leaves adapters safe',
		static function (): bool {
			$output = array();
			$status = 0;
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin 2>&1', $output, $status );
			return 0 === $status && array( 'NO_PLUGIN_OK' ) === $output;
		}
	);

	check_case(
		'spine prints only the archive board spine',
		static function (): bool {
			reset_state();
			$post = board_post( 101 );
			$out  = spine( '<article><h3 class="pk-title">Old</h3><div class="pk-box">Box</div></article>', 'board', $post, array( 'title' => 'cr-play-title-101', 'date' => 'cr-play-date-101', 'repeats' => false ) );
			return 1 === visible_anchor_count( $out )
				&& false !== strpos( $out, 'class="pk-title p-name"' )
				&& false !== strpos( $out, 'cr-spine' )
				&& 1 === preg_match( '/cr-paper--(?:' . implode( '|', array_map( 'preg_quote', PAPERS ) ) . ')/', $out )
				&& false === strpos( $out, 'glaucous' )
				&& preg_match( '/<time id="cr-play-date-101" class="dt-published" datetime="\d{4}-\d{2}-\d{2}">Sep 18, 2026<\/time>/', $out )
				&& false === has_forbidden_class( $out )
				&& false === strpos( $out, 'Board Game' )
				&& false === strpos( $out, 'Rated' )
				&& false === strpos( $out, 'href="https://example.test/games/forest-paths"' )
				&& false !== strpos( $out, 'https://boardgamegeek.com/boardgame/9990001' );
		}
	);

	check_case(
		'spine duplicate titles and headings are accessible',
		static function (): bool {
			reset_state();
			$a = board_post( 102 );
			$b = board_post( 103 );
			$one = spine( '<h2 class="pk-title">Old</h2>', 'board', $a, array( 'title' => 't102', 'date' => 'd102', 'repeats' => true ) );
			$two = spine( '<h2 class="pk-title">Old</h2>', 'board', $b, array( 'title' => 't103', 'date' => 'd103', 'repeats' => true ) );
			$plain = spine( '<article>None</article>', 'board', $a, array( 'title' => 't102', 'date' => 'd102', 'repeats' => false ) );
			return false !== strpos( $one, '<h2' )
				&& false !== strpos( $one, 'class="pk-title p-name"' )
				&& false !== strpos( $plain, '<h3' )
				&& false !== strpos( $plain, 'class="pk-title p-name"' )
				&& false !== strpos( $one, 'aria-labelledby="t102 d102"' )
				&& false !== strpos( $two, 'aria-labelledby="t103 d103"' )
				&& false === strpos( $plain, 'aria-labelledby' )
				&& false !== strpos( $one, 'id="d102"' )
				&& false !== strpos( $two, 'id="d103"' )
				&& false !== strpos( $one, 'Sep 19, 2026' )
				&& false !== strpos( $two, 'Sep 20, 2026' );
		}
	);

	check_case(
		'spine and slip untitled omit heading and hidden data names',
		static function (): bool {
			reset_state();
			$post = board_post( 104, array( 'title' => '', 'game_url' => 'https://example.test/games/untitled', 'bgg_id' => '9990001', 'post_title' => '' ) );
			$spine = spine( '<h3 class="pk-title">Old</h3>', 'board', $post, array( 'title' => 't104', 'date' => 'd104', 'repeats' => false ) );
			$slip  = slip( '<h2 class="pk-title">Old</h2>', 'board', $post );
			return false !== strpos( $spine, 'Untitled stand-in 104' )
				&& false !== strpos( $slip, 'Untitled stand-in 104' )
				&& 0 === preg_match( '/<h[2-6]\b[^>]*class="[^"]*\bp-name\b[^"]*"/', $spine )
				&& 0 === preg_match( '/<h[2-6]\b[^>]*class="[^"]*\bp-name\b[^"]*"/', $slip )
				&& false === strpos( $spine, '<data class="p-name"' )
				&& false === strpos( $slip, '<data class="p-name"' );
		}
	);

	check_case(
		'spine omits empty ids and repeated-title labels',
		static function (): bool {
			reset_state();
			$post = board_post( 1041 );
			$out  = spine( '<h3 class="pk-title">Old</h3>', 'board', $post, array() );
			return false === strpos( $out, 'id=""' )
				&& false === strpos( $out, 'aria-labelledby' );
		}
	);

	check_case(
		'spine and slip pass through non-board, protected, empty-facts and empty-html cases',
		static function (): bool {
			reset_state();
			$post = board_post( 105 );
			$html = '<article class="pk-card">Keep</article>';
			$GLOBALS['cr_passwords'][105] = true;
			$protected = spine( $html, 'board', $post, array() );
			$GLOBALS['cr_passwords'] = array();
			$empty_post = board_post( 106, array() );
			$GLOBALS['cr_facts'][106] = array();
			return '' === spine( '', 'board', $post, array() )
				&& $html === spine( $html, 'video', $post, array() )
				&& $html === spine( $html, '', $post, array() )
				&& $html === $protected
				&& $html === spine( $html, 'board', $empty_post, array() )
				&& $html === slip( $html, 'video', $post )
				&& $html === slip( $html, 'board', $empty_post );
		}
	);

	check_case(
		'paper selection is deterministic and covers every paper',
		static function (): bool {
			$seen = array();
			foreach ( range( 1, 500 ) as $id ) {
				$paper = paper_for( $id );
				if ( ! in_array( $paper, PAPERS, true ) || \Courtneyr\Child\Stamps\seeded_paper( $id, PAPERS ) !== $paper ) {
					return false;
				}
				$seen[ $paper ] = true;
			}
			return count( $seen ) === count( PAPERS );
		}
	);

	check_case(
		'slip prints the Stream score pad contract',
		static function (): bool {
			reset_state();
			$post = board_post( 107, array( 'title' => 'Forest Paths', 'platform' => 'Tabletop', 'rating_label' => 'Rated 4 of 5', 'game_url' => 'https://example.test/games/forest-paths', 'bgg_id' => '9990001' ) );
			$GLOBALS['cr_pictures'][107] = array( 'url' => 'https://example.test/cover.jpg', 'attachment_id' => 0, 'suppress_featured' => false );
			$out = slip( '<h2 class="pk-title">Old</h2><div class="pk-meta">Old</div>', 'board', $post );
			$platformless = slip( '<h2 class="pk-title">Old</h2>', 'board', board_post( 108, array( 'title' => 'No Meta', 'platform' => '', 'rating_label' => '', 'game_url' => 'https://example.test/games/no-meta', 'bgg_id' => '9990001' ) ) );
			return 1 === visible_anchor_count( $out )
				&& false !== strpos( $out, 'Play' )
				&& strpos( $out, 'cr-scorepad__label' ) < strpos( $out, 'cr-scorepad__title' )
				&& strpos( $out, 'cr-scorepad__title' ) < strpos( $out, 'cr-scorepad__platform' )
				&& strpos( $out, 'cr-scorepad__platform' ) < strpos( $out, 'cr-scorepad__date' )
				&& strpos( $out, 'cr-scorepad__date' ) < strpos( $out, 'Rated 4 of 5' )
				&& false === has_forbidden_class( $out )
				&& false === strpos( $out, 'View on BGG' )
				&& false === strpos( $out, 'Buy' )
				&& false !== strpos( $out, 'datetime="2026-09-24T12:34:56+00:00"' )
				&& false !== strpos( $out, 'data-cr-cover-fallback aria-hidden="true"><img class="cr-scorepad__image" src="https://example.test/cover.jpg" alt="" loading="lazy">' )
				&& false !== strpos( $out, '<data class="u-url" value="https://example.test/games/forest-paths">' )
				&& false !== strpos( $out, '<data class="u-uid" value="https://boardgamegeek.com/boardgame/9990001">' )
				&& false === strpos( $platformless, 'cr-scorepad__platform' )
				&& false === strpos( $platformless, 'cr-scorepad__rating' )
				&& false !== strpos( $platformless, 'cr-scorepad__thumb--meeple' )
				&& false === strpos( $platformless, '<img' );
		}
	);

	check_case(
		'slip uses featured picture helper when possible',
		static function (): bool {
			reset_state();
			$post = board_post( 109 );
			$GLOBALS['cr_pictures'][109] = array( 'url' => 'https://example.test/featured.jpg', 'attachment_id' => 333, 'suppress_featured' => true );
			$GLOBALS['cr_attachment_returns'][333] = '<img class="cr-scorepad__image" src="https://example.test/generated.jpg" alt="" loading="lazy" />';
			$out = slip( '<h2 class="pk-title">Old</h2>', 'board', $post );
			$call = $GLOBALS['cr_attachment_calls'][0] ?? array();
			return false !== strpos( $out, 'generated.jpg' )
				&& 333 === ( $call[0] ?? 0 )
				&& 'medium' === ( $call[1] ?? '' )
				&& array( 'alt' => '', 'loading' => 'lazy', 'class' => 'cr-scorepad__image' ) === ( $call[3] ?? array() );
		}
	);

	check_case(
		'picks add paper, failed-cover data and title spans',
		static function (): bool {
			reset_state();
			$GLOBALS['cr_url_to_postid'] = array(
				'https://example.test/pick-one/' => 201,
				'https://example.test/pick-two/' => 202,
				'https://example.test/pick-text/' => 203,
			);
			$html = '<section class="wp-block-post-kinds-indieweb-staff-picks pkiw-staff-picks" aria-labelledby="p"><h2 id="p" class="pkiw-staff-picks__heading">Staff Picks</h2><ul class="pkiw-staff-picks__list">'
				. '<li class="pkiw-staff-picks__item"><div class="pkiw-staff-picks__box"><img class="pkiw-staff-picks__cover" src="one.jpg" alt="" decoding="async" loading="eager" fetchpriority="high" /></div><h3 class="pkiw-staff-picks__title"><a class="pkiw-staff-picks__link" href="https://example.test/pick-one/">One</a></h3><p class="pkiw-staff-picks__rating">Rated 5 of 5</p></li>'
				. '<li class="pkiw-staff-picks__item"><div class="pkiw-staff-picks__box"><img class="pkiw-staff-picks__cover" src="two.jpg" alt="" decoding="async" loading="lazy" /></div><h3 class="pkiw-staff-picks__title"><a class="pkiw-staff-picks__link" href="https://example.test/pick-two/">Two &amp; More</a></h3><p class="pkiw-staff-picks__rating">Rated 4 of 5</p></li>'
				. '<li class="pkiw-staff-picks__item"><div class="pkiw-staff-picks__box pkiw-staff-picks__box--text" aria-hidden="true">Text</div><h3 class="pkiw-staff-picks__title"><a class="pkiw-staff-picks__link" href="https://example.test/pick-text/">Text</a></h3><p class="pkiw-staff-picks__rating">Rated 3 of 5</p></li>'
				. '<li class="pkiw-staff-picks__item"><div class="pkiw-staff-picks__box"><img class="pkiw-staff-picks__cover" src="zero.jpg" alt="" /></div><h3 class="pkiw-staff-picks__title"><a class="pkiw-staff-picks__link" href="https://example.test/unmapped/">Zero</a></h3><p class="pkiw-staff-picks__rating">Rated 2 of 5</p></li>'
				. '</ul></section>';
			$out = picks( $html );
			return false !== strpos( $out, 'cr-paper--' . paper_for( 201 ) )
				&& false !== strpos( $out, 'cr-paper--' . paper_for( 202 ) )
				&& false !== strpos( $out, 'cr-paper--' . paper_for( 203 ) )
				&& 3 === substr_count( $out, 'data-cr-cover-fallback' )
				&& 3 === substr_count( $out, 'class="cr-box__title" aria-hidden="true"' )
				&& false !== strpos( $out, '<span class="cr-box__title" aria-hidden="true">Two &amp; More</span></div>' )
				&& false !== strpos( $out, 'src="one.jpg" alt="" decoding="async" loading="eager" fetchpriority="high"' )
				&& false !== strpos( $out, 'Rated 3 of 5' )
				&& false === strpos( substr( $out, strpos( $out, 'https://example.test/unmapped/' ) - 160, 160 ), 'cr-paper--' )
				&& '<section><p>No picks</p></section>' === picks( '<section><p>No picks</p></section>' );
		}
	);

	check_case(
		'drop_featured_image only drops duplicate board featured images',
		static function (): bool {
			reset_state();
			$html = '<figure>Featured</figure>';
			$block = array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'alignwide single-post__featured' ) );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 301 );
			$GLOBALS['cr_play_groups'][301] = 'board';
			$GLOBALS['cr_pictures'][301] = array( 'url' => 'https://example.test/cover.jpg', 'attachment_id' => 1, 'suppress_featured' => true );
			$drop = drop_featured_image( $html, $block );
			$GLOBALS['cr_pictures'][301]['suppress_featured'] = false;
			$keep_picture = drop_featured_image( $html, $block );
			$GLOBALS['cr_play_groups'][301] = 'video';
			$keep_group = drop_featured_image( $html, $block );
			$GLOBALS['cr_request'] = array( 'single' => false, 'queried_id' => 301 );
			$keep_surface = drop_featured_image( $html, $block );
			return '' === $drop
				&& $html === $keep_picture
				&& $html === $keep_group
				&& $html === $keep_surface
				&& $html === drop_featured_image( $html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__header' ) ) )
				&& $html === drop_featured_image( $html, array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'other' ) ) );
		}
	);

	check_case(
		'enqueue_styles follows the Play and Stream surfaces',
		static function (): bool {
			$expected = array( COURTNEYR_CHILD_URI . '/assets/css/cr-play-board.css', array(), COURTNEYR_CHILD_VERSION );
			$run = static function ( array $request, bool $stream = false ): array {
				$GLOBALS['cr_request'] = $request;
				$GLOBALS['cr_stream_surface'] = $stream;
				$GLOBALS['cr_styles'] = array();
				enqueue_styles();
				enqueue_styles();
				return $GLOBALS['cr_styles'];
			};
			$archive = $run( array( 'archive' => true ) );
			$single = $run( array( 'play_single' => true ) );
			$stream = $run( array(), true );
			$admin = $run( array( 'admin' => true ) );
			$none = $run( array() );
			$output = array();
			$status = 0;
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-play 2>&1', $output, $status );
			return $expected === ( $archive['courtneyr-play-board'] ?? null )
				&& $expected === ( $single['courtneyr-play-board'] ?? null )
				&& $expected === ( $stream['courtneyr-play-board'] ?? null )
				&& $expected === ( $admin['courtneyr-play-board'] ?? null )
				&& array() === $none
				&& 1 === count( $archive )
				&& 0 === $status
				&& array( 'NO_PLAY_OK' ) === $output;
		}
	);

	check_case(
		'mf2 parses the spine and slip nested play citation',
		static function () {
			$home = (string) getenv( 'HOME' );
			$candidates = array_filter(
				array(
					getenv( 'MF2_PARSER_FILE' ) ?: '',
					$home . '/projects/post-kinds-for-indieweb/vendor/mf2/mf2/Mf2/Parser.php',
					$home . '/Developer/pkiw-w1-pcard-fu/vendor/mf2/mf2/Mf2/Parser.php',
				)
			);
			$parser = '';
			foreach ( $candidates as $candidate ) {
				if ( is_file( $candidate ) ) {
					$parser = $candidate;
					break;
				}
			}
			if ( '' === $parser ) {
				return 'SKIP mf2 (parser not found)';
			}
			$old = error_reporting( E_ALL & ~E_DEPRECATED );
			require_once $parser;
			reset_state();
			$post = board_post( 401 );
			$spine = '<ul><li class="wp-block-post h-entry">' . spine( '<h3 class="pk-title">Old</h3>', 'board', $post, array( 'title' => 't401', 'date' => 'd401', 'repeats' => true ) ) . '</li></ul>';
			$slip = '<ul><li class="wp-block-post h-entry">' . slip( '<h2 class="pk-title">Old</h2>', 'board', $post ) . '</li></ul>';
			$a = ( new \Mf2\Parser( $spine, 'https://example.test/' ) )->parse();
			$b = ( new \Mf2\Parser( $slip, 'https://example.test/' ) )->parse();
			error_reporting( $old );
			$entry_a = $a['items'][0] ?? array();
			$entry_b = $b['items'][0] ?? array();
			$play_of_a = $entry_a['properties']['play-of'][0] ?? array();
			$uid_a = $entry_a['properties']['play-of'][0]['properties']['uid'][0] ?? '';
			$uid_b = $entry_b['properties']['play-of'][0]['properties']['uid'][0] ?? '';
			return 1 === count( $a['items'] ?? array() )
				&& isset( $entry_a['properties']['url'], $entry_a['properties']['published'], $entry_a['properties']['author'][0]['type'] )
				&& in_array( 'h-card', $entry_a['properties']['author'][0]['type'], true )
				&& in_array( 'h-cite', $play_of_a['type'] ?? array(), true )
				&& 'https://boardgamegeek.com/boardgame/9990001' === $uid_a
				&& 'https://boardgamegeek.com/boardgame/9990001' === $uid_b
				&& ! empty( $entry_b['properties']['url'] )
				&& ! empty( $entry_b['properties']['published'] );
		}
	);

	check_case(
		'no PCARD structure survives full tabletop input',
		static function (): bool {
			reset_state();
			$post = board_post( 501 );
			$input = '<article class="pk-card k-play h-cite u-play-of pk-card--tabletop" data-pkiw-play-group="board"><h2 class="pk-title p-name">Forest Paths</h2><figure class="pk-box"><img></figure><dl class="pk-facts"></dl><div class="pk-links"></div><section class="pk-scorepad"></section><span class="pk-kindlabel"></span><p class="pk-sub"></p><div class="pk-stars"></div><div class="pk-meta"></div><p class="pk-stream-date"></p><div class="pk-media"></div><div class="pk-badge"></div><div class="pk-note"></div><p class="pk-excerpt"></p></article>';
			return false === has_forbidden_class( spine( $input, 'board', $post, array( 'title' => 't501', 'date' => 'd501', 'repeats' => false ) ) )
				&& false === has_forbidden_class( slip( $input, 'board', $post ) );
		}
	);

	$skipped = (int) ( $GLOBALS['cr_skipped_count'] ?? 0 );
	$passed  = (int) ( $GLOBALS['cr_case_count'] ?? 0 ) - $skipped - count( $failures );
	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : $passed . ' passed' . ( $skipped ? ', ' . $skipped . ' skipped' : '' ), "\n";
	exit( count( $failures ) ? 1 : 0 );
}
