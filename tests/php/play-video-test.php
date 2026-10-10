<?php
/**
 * `php tests/php/play-video-test.php`: checks video play objects without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_PLAY_VIDEO_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']          = array();
	$GLOBALS['cr_request']        = array();
	$GLOBALS['cr_posts']          = array();
	$GLOBALS['cr_terms']          = array();
	$GLOBALS['cr_titles']         = array();
	$GLOBALS['cr_dates']          = array();
	$GLOBALS['cr_times']          = array();
	$GLOBALS['cr_permalinks']     = array();
	$GLOBALS['cr_passwords']      = array();
	$GLOBALS['cr_current_post']   = 0;
	$GLOBALS['cr_styles']         = array();
	$GLOBALS['cr_stream_surface'] = false;
	$GLOBALS['cr_find_blocks']    = array();
	$GLOBALS['cr_play_groups']    = array();
	$GLOBALS['cr_kind_facts']     = array();
	$GLOBALS['cr_kind_pictures']  = array();
	$GLOBALS['cr_attachment_log'] = array();

	/** Stand-in post. */
	class WP_Post {
		/** @var int Post ID. */
		public $ID;

		/** @var string Post title. */
		public $post_title = '';

		/** @var string Post content. */
		public $post_content = '';

		/**
		 * Construct the post.
		 *
		 * @param int    $id      Post ID.
		 * @param string $title   Post title.
		 * @param string $content Post content.
		 */
		public function __construct( int $id, string $title = '', string $content = '' ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
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

	/** Register a callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Register an action. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Run registered filters. */
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

	/** Current kind archive. */
	function is_tax( $taxonomy = '', $term = '' ) {
		$kind = (string) ( $GLOBALS['cr_request']['kind'] ?? '' );
		return 'kind' === $taxonomy && '' !== $kind && in_array( $kind, (array) $term, true );
	}

	/** Current single request. */
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Queried post ID. */
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	/** Admin flag. */
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	/** Term membership. */
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'play' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}

	/** Return a post. */
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}

	/** Current post ID. */
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}

	/** Post title. */
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : ( 0 === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_titles'][ $id ] ?? ( $GLOBALS['cr_posts'][ $id ]->post_title ?? '' ) );
	}

	/** Display date. */
	function get_the_date( $format = '', $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_dates'][ $id ] ?? 'September 22, 2026' );
	}

	/** Machine post time. */
	function get_post_time( $format = 'c', $gmt = false, $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_times'][ $id ] ?? '2026-09-22T10:00:00+00:00' );
	}

	/** Permalink. */
	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : ( 0 === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_permalinks'][ $id ] ?? 'https://example.test/p/' . $id );
	}

	/** Password-protected flag. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	/** Strip tags. */
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}

	/** Allow stored review HTML in tests. */
	function wp_kses_post( $html ) {
		return (string) $html;
	}

	/** Enqueue style. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $deps, $version );
	}

	/** Escape HTML. */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape attr. */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape URL. */
	function esc_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || preg_match( '/^javascript:/i', $url ) ) {
			return '';
		}
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}

	/** Translate. */
	function __( $text ) {
		return $text;
	}

	/** Translate and escape. */
	function esc_html__( $text ) {
		return esc_html( $text );
	}

	/** Attachment image stub. */
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$GLOBALS['cr_attachment_log'][] = array( $id, $size, $icon, $attr );
		if ( ! empty( $GLOBALS['cr_attachment_empty'][ $id ] ) ) {
			return '';
		}
		$bits = '';
		foreach ( $attr as $name => $value ) {
			$bits .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return '<img data-attachment-id="' . esc_attr( (string) $id ) . '" data-size="' . esc_attr( (string) $size ) . '"' . $bits . ' />';
	}

	/** Core HTML API helper. */
	function wp_has_noncharacters( $text ) {
		return false;
	}

	/** Core HTML API URI attributes. */
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Test Stream state. */
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream_surface'];
	}
}

namespace Courtneyr\Child\StreamMedia {
	/** Test block finder. */
	function find_block( \WP_Post $post, string $name ): ?array {
		return $GLOBALS['cr_find_blocks'][ $post->ID ][ $name ] ?? null;
	}
}

namespace Courtneyr\Child\Microformats {
	/** Test author markup. */
	function entry_author_html( int $post_id ): string {
		return '<span class="p-author h-card" hidden><a class="u-url p-name" href="https://example.test/">Courtney</a></span>';
	}
}

namespace PKIW {
	if ( ! in_array( '--no-plugin', $GLOBALS['argv'], true ) ) {
		/** Play group. */
		function play_group( int $post_id ): string {
			return (string) ( $GLOBALS['cr_play_groups'][ $post_id ] ?? '' );
		}

		/** Play facts. */
		function kind_facts( int $post_id ): array {
			return (array) ( $GLOBALS['cr_kind_facts'][ $post_id ] ?? array() );
		}

		/** Play picture. */
		function kind_picture( int $post_id ): array {
			return (array) ( $GLOBALS['cr_kind_pictures'][ $post_id ] ?? array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '', 'remote' => false, 'suppress_featured' => false ) );
		}

		/** Rating markup. */
		function card_rating_html( $rating, int $best = 5, bool $decorative = false ): string {
			if ( (float) $rating <= 0 ) {
				return '';
			}
			return '<div class="pk-stars"' . ( $decorative ? ' aria-hidden="true"' : ' role="img" aria-label="Rated ' . (float) $rating . ' of ' . $best . '"' ) . '>stars</div><data class="p-rating" value="' . (float) $rating . '" hidden></data>';
		}

		/** Rating label. */
		function card_rating_label( $rating ): string {
			return (float) $rating > 0 ? 'Rated ' . (int) $rating . ' of 5' : '';
		}

		/** Kind icon. */
		function get_kind_icon_svg( string $kind ): string {
			return '<svg aria-hidden="true" class="icon-' . $kind . '"></svg>';
		}

		/** New-tab hint. */
		function pkiw_new_tab_hint(): string {
			return '<span class="pk-sr-only"> (opens in a new tab)</span>';
		}

		/** Untitled fallback. */
		function untitled_name( \WP_Post $post ): string {
			return 'Untitled ' . $post->ID;
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

	require CR_PLAY_VIDEO_THEME_DIR . '/inc/media-shelf.php';
	require CR_PLAY_VIDEO_THEME_DIR . '/inc/play.php';
	$GLOBALS['cr_hooks'] = array();
	require CR_PLAY_VIDEO_THEME_DIR . '/inc/play-video.php';

	if ( in_array( '--no-plugin', $argv, true ) ) {
		$post                       = new WP_Post( 91, 'Title' );
		$GLOBALS['cr_posts'][91]    = $post;
		$GLOBALS['cr_terms'][91]    = true;
		$GLOBALS['cr_current_post'] = 91;
		$html                       = '<article class="pk-card"><div class="pk-caption"><h3 class="pk-title"><a href="/play">Play</a></h3></div></article>';
		$item                       = \Courtneyr\Child\PlayVideo\filter_item( $html, 'video', $post, array() );
		$stream                     = \Courtneyr\Child\PlayVideo\filter_stream_card( $html, 'video', $post );
		echo $item === $html && $stream === $html ? "NO_PLUGIN_OK\n" : "NO_PLUGIN_FAIL\n";
		exit( $item === $html && $stream === $html ? 0 : 1 );
	}
}

namespace Courtneyr\Child\PlayVideo\Tests {

	use function Courtneyr\Child\Play\dispatch;
	use function Courtneyr\Child\PlayVideo\body_class;
	use function Courtneyr\Child\PlayVideo\drop_template_header;
	use function Courtneyr\Child\PlayVideo\element_span;
	use function Courtneyr\Child\PlayVideo\enqueue_styles;
	use function Courtneyr\Child\PlayVideo\filter_item;
	use function Courtneyr\Child\PlayVideo\filter_stream_card;
	use function Courtneyr\Child\PlayVideo\is_video_single;
	use function Courtneyr\Child\PlayVideo\single_cabinet;

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

	/** Reset mutable test state. */
	function reset_state(): void {
		$GLOBALS['cr_request']        = array();
		$GLOBALS['cr_posts']          = array();
		$GLOBALS['cr_terms']          = array();
		$GLOBALS['cr_titles']         = array();
		$GLOBALS['cr_dates']          = array();
		$GLOBALS['cr_times']          = array();
		$GLOBALS['cr_permalinks']     = array();
		$GLOBALS['cr_passwords']      = array();
		$GLOBALS['cr_current_post']   = 0;
		$GLOBALS['cr_styles']         = array();
		$GLOBALS['cr_stream_surface'] = false;
		$GLOBALS['cr_find_blocks']    = array();
		$GLOBALS['cr_play_groups']    = array();
		$GLOBALS['cr_kind_facts']     = array();
		$GLOBALS['cr_kind_pictures']  = array();
		$GLOBALS['cr_attachment_log'] = array();
		unset( $GLOBALS['wp_query'] );
	}

	/** Add one play post. */
	function play_post( int $id, string $group = 'video', string $title = 'Post Title' ): \WP_Post {
		$post                              = new \WP_Post( $id, $title, '<!-- wp:post-kinds-indieweb/play-card /-->' );
		$GLOBALS['cr_posts'][ $id ]        = $post;
		$GLOBALS['cr_terms'][ $id ]        = true;
		$GLOBALS['cr_titles'][ $id ]       = $title;
		$GLOBALS['cr_permalinks'][ $id ]   = 'https://example.test/play/' . $id;
		$GLOBALS['cr_current_post']        = $id;
		$GLOBALS['cr_play_groups'][ $id ]  = $group;
		$GLOBALS['cr_find_blocks'][ $id ]  = array( 'post-kinds-indieweb/play-card' => array( 'blockName' => 'post-kinds-indieweb/play-card' ) );
		$GLOBALS['cr_kind_facts'][ $id ]   = base_facts();
		$GLOBALS['cr_kind_pictures'][ $id ] = base_picture();
		return $post;
	}

	/** Default facts. */
	function base_facts(): array {
		return array(
			'title'          => 'Game Title',
			'platform'       => 'PC',
			'status'         => 'completed',
			'status_label'   => 'Completed',
			'hours'          => 42.0,
			'hours_label'    => '42 hours played',
			'rating'         => 4.0,
			'rating_label'   => 'Rated 4 of 5',
			'review'         => '<p>Review with a <a href="https://example.test/x">link</a>.</p>',
			'game_url'       => 'https://rawg.example/games/x',
			'game_url_label' => 'rawg.example',
			'official_url'   => 'https://game.example/',
			'purchase_url'   => 'https://store.example/game',
			'bgg_id'         => '100',
			'rawg_id'        => '900001',
			'steam_id'       => '1234',
			'group'          => 'video',
			'played_at'      => array( '2026-09-22', 'September 22, 2026' ),
			'cover_alt'      => 'Custom cover alt',
		);
	}

	/** Default picture. */
	function base_picture(): array {
		return array( 'source' => 'featured', 'attachment_id' => 77, 'url' => 'https://img.example/cover.jpg', 'alt' => 'Stored alt', 'remote' => false, 'suppress_featured' => false );
	}

	/** Generic Stream card shape. */
	function shape_b(): string {
		return '<article class="pk-card pk-card--stream k-play h-entry"><div class="pk-badge">SVG</div><div class="pk-body"><span class="pk-kindlabel">Play</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="PERMALINK">Title</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="ISO">September 22, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="URL" alt=""><p class="pk-media__caption" aria-live="polite">Caption</p></div><p class="pk-excerpt p-summary">Excerpt</p><span class="pk-kind-cite h-cite u-play-of" hidden><data class="p-name" value="Title"></data><data class="u-uid" value="https://rawg.io/games/900001"></data></span><div class="pk-meta"><a class="pk-link" href="PERMALINK">Read more<span class="pk-sr-only">: Title</span></a></div></div></article>';
	}

	/** Card-only Stream card shape. */
	function shape_a(): string {
		return '<article class="pk-card k-play h-cite u-play-of"><div class="pk-badge">SVG</div><div class="pk-body"><span class="pk-kindlabel">Play</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="PERMALINK">Title</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="ISO">September 22, 2026</time></p><p class="pk-sub"><span class="pk-play-status">Completed</span> &mdash; <span class="pk-play-platform">PC</span> &bull; <span class="pk-play-hours">42 hours played</span></p></div><div class="pk-stars" role="img" aria-label="Rated 4 of 5">SVGS</div><data class="p-rating" value="4" hidden></data><div class="pk-media"><img class="pk-thumb--poster u-photo" src="URL" alt="Box art for Title" loading="lazy"></div><div class="pk-note p-content"><p>Review with a <a href="https://example.test/x">link</a>.</p><div class="inner">nested div</div></div><div class="pk-meta"><a class="pk-link" href="https://rawg.example/games/x" target="_blank" rel="noopener noreferrer">rawg.example<span class="pk-sr-only"> (opens in a new tab)</span></a><span class="pk-dot"></span><time class="dt-published" datetime="2026-09-22">September 22, 2026</time></div></div><data class="u-uid" value="https://rawg.io/games/900001" hidden></data><span class="pk-entry-props" hidden><data class="u-url" value="PERMALINK"></data></span></article>';
	}

	/** Protected card shape. */
	function protected_shape(): string {
		return '<article class="pk-card pk-card--stream pk-card--protected k-play h-entry"><h3 class="pk-title p-name"><a class="u-url" href="PERMALINK">Protected: Title</a></h3></article>';
	}

	/** Count open and close tags. */
	function balanced( string $html, string $tag ): bool {
		return preg_match_all( '/<' . preg_quote( $tag, '/' ) . '\\b/', $html ) === substr_count( $html, '</' . $tag . '>' );
	}

	/** Basic cabinet expectations. */
	function cabinet_ok( string $out, bool $expects_rating = true, bool $expects_entry_props = true ): bool {
		foreach ( array( 'pk-kindlabel', 'pk-badge', 'pk-sub', 'pk-stream-date', 'pk-stars', 'pk-note', 'pk-meta', 'pk-excerpt', 'pk-media' ) as $gone ) {
			if ( false !== strpos( $out, $gone ) ) {
				return false;
			}
		}
		return false !== strpos( $out, 'cr-cabinet' )
			&& 1 === substr_count( $out, '<a ' )
			&& false !== strpos( $out, 'href="PERMALINK"' )
			&& false !== strpos( $out, 'data-cr-cover-fallback' )
			&& false !== strpos( $out, 'alt=""' )
			&& false !== strpos( $out, 'u-photo' )
			&& false !== strpos( $out, '42 hours played' )
			&& false !== strpos( $out, 'cr-cabinet__panel" aria-hidden="true"' )
			&& false !== strpos( $out, 'u-uid' )
			&& ( $expects_entry_props ? false !== strpos( $out, 'pk-entry-props' ) : true )
			&& ( $expects_rating ? false !== strpos( $out, 'p-rating' ) : true )
			&& balanced( $out, 'div' )
			&& balanced( $out, 'span' )
			&& balanced( $out, 'p' );
	}

	/** Basic cartridge expectations. */
	function cartridge_ok( string $out, bool $platform = true ): bool {
		foreach ( array( 'pk-badge', 'pk-stars', 'pk-note', 'pk-meta', 'pk-excerpt', 'pk-media' ) as $gone ) {
			if ( false !== strpos( $out, $gone ) ) {
				return false;
			}
		}
		return false !== strpos( $out, 'cr-cartridge' )
			&& false !== strpos( $out, 'pk-card' )
			&& false !== strpos( $out, 'k-play' )
			&& 1 === substr_count( $out, '<a ' )
			&& false !== strpos( $out, 'pk-kindlabel' )
			&& false !== strpos( $out, 'cr-cartridge__date' )
			&& false === strpos( $out, 'pk-sub pk-stream-date' )
			&& ( $platform ? false !== strpos( $out, 'cr-cartridge__platform">PC' ) : false === strpos( $out, 'cr-cartridge__platform' ) )
			&& false !== strpos( $out, 'cr-cartridge__label' )
			&& false !== strpos( $out, 'loading="lazy"' )
			&& false !== strpos( $out, 'alt=""' )
			&& false !== strpos( $out, 'u-uid' )
			&& balanced( $out, 'div' )
			&& balanced( $out, 'span' )
			&& balanced( $out, 'p' );
	}

	check_case(
		'Load: exactly the six hooks',
		static function (): bool {
			$expected = array(
				'courtneyr_child_play_item'        => array( 10, 4, __NAMESPACE__ . '\\..' ),
				'courtneyr_child_play_stream_card' => array( 10, 3, __NAMESPACE__ . '\\..' ),
				'render_block'                     => array( 10, 2, __NAMESPACE__ . '\\..' ),
				'body_class'                       => array( 10, 1, __NAMESPACE__ . '\\..' ),
				'enqueue_block_assets'             => array( 11, 1, __NAMESPACE__ . '\\..' ),
			);
			$total = 0;
			foreach ( $GLOBALS['cr_hooks'] as $hook => $priorities ) {
				foreach ( $priorities as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						++$total;
					}
				}
			}
			return 6 === $total
				&& isset( $GLOBALS['cr_hooks']['courtneyr_child_play_item'][10][0] )
				&& 4 === $GLOBALS['cr_hooks']['courtneyr_child_play_item'][10][0][1]
				&& 3 === $GLOBALS['cr_hooks']['courtneyr_child_play_stream_card'][10][0][1]
				&& 1 === count( $GLOBALS['cr_hooks']['render_block'][10] )
				&& 2 === $GLOBALS['cr_hooks']['render_block'][10][0][1]
				&& 2 === $GLOBALS['cr_hooks']['render_block'][20][0][1]
				&& 1 === $GLOBALS['cr_hooks']['body_class'][10][0][1]
				&& 1 === $GLOBALS['cr_hooks']['enqueue_block_assets'][11][0][1]
				&& ! empty( $expected );
		}
	);

	check_case(
		'element_span: nested tags and class tokens match cut_elements',
		static function (): bool {
			$html = '<div class="wrap"><div class="pk-media"><div class="inner">Nested</div></div><div class="pk-media--stream">No</div></div>';
			$span = element_span( $html, 'div', 'pk-media' );
			if ( null === $span ) {
				return false;
			}
			$cut = \Courtneyr\Child\MediaShelf\cut_elements( $html, 'div', 'pk-media' );
			if ( $cut !== substr( $html, 0, $span[0] ) . substr( $html, $span[1] ) ) {
				return false;
			}
			return null === element_span( $html, 'p', 'pk-media' )
				&& null === element_span( '<div class="pk-media"><div></div>', 'div', 'pk-media' )
				&& null === element_span( '<div class="pk-media--stream"></div>', 'div', 'pk-media' );
		}
	);

	check_case(
		'Cabinet, shape B and shape A',
		static function (): bool {
			reset_state();
			$post = play_post( 1 );
			return cabinet_ok( filter_item( shape_b(), 'video', $post, array( 'repeats' => false ) ), false, false )
				&& cabinet_ok( filter_item( shape_a(), 'video', $post, array( 'repeats' => false ) ) )
				&& 2 === count( $GLOBALS['cr_attachment_log'] );
		}
	);

	check_case(
		'Cabinet art fallbacks',
		static function (): bool {
			reset_state();
			$post = play_post( 2 );
			$GLOBALS['cr_kind_pictures'][2] = array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '', 'remote' => false, 'suppress_featured' => false );
			$empty = filter_item( shape_b(), 'video', $post, array() );
			$GLOBALS['cr_kind_pictures'][2] = array( 'source' => 'remote', 'attachment_id' => 0, 'url' => 'https://img.example/remote.jpg?x=1', 'alt' => '', 'remote' => true, 'suppress_featured' => false );
			$remote = filter_item( shape_b(), 'video', $post, array() );
			return false !== strpos( $empty, 'cr-cabinet--no-art' )
				&& false !== strpos( $empty, 'cr-cabinet__screen--empty' )
				&& false !== strpos( $empty, 'cr-cabinet__glyph' )
				&& false === strpos( $empty, '<img' )
				&& false === strpos( $empty, 'data-cr-cover-fallback' )
				&& false !== strpos( $remote, '<img class="cr-cabinet__image u-photo" src="https://img.example/remote.jpg?x=1"' );
		}
	);

	check_case(
		'First-row loading',
		static function (): bool {
			reset_state();
			$posts = array();
			foreach ( array( 10, 11, 12, 13, 14 ) as $id ) {
				$posts[] = play_post( $id, 'video' );
			}
			$board = play_post( 15, 'board' );
			$GLOBALS['wp_query'] = (object) array( 'posts' => array( $posts[0], $board, $posts[1], $posts[2], $posts[3], $posts[4] ) );
			$outs = array();
			foreach ( $posts as $post ) {
				$outs[] = filter_item( shape_b(), 'video', $post, array() );
			}
			unset( $GLOBALS['wp_query'] );
			$missing = play_post( 16, 'video' );
			$lazy    = filter_item( shape_b(), 'video', $missing, array() );
			return false !== strpos( $outs[0], 'loading="eager"' ) && false !== strpos( $outs[0], 'fetchpriority="high"' )
				&& false !== strpos( $outs[1], 'loading="eager"' ) && false !== strpos( $outs[2], 'loading="eager"' )
				&& false !== strpos( $outs[3], 'loading="lazy"' ) && false === strpos( $outs[3], 'fetchpriority="high"' )
				&& false !== strpos( $outs[4], 'loading="lazy"' )
				&& false !== strpos( $lazy, 'loading="lazy"' );
		}
	);

	check_case(
		'Duplicate titles',
		static function (): bool {
			reset_state();
			$post = play_post( 20 );
			$yes  = filter_item( shape_b(), 'video', $post, array( 'title' => 'cr-play-title-20', 'date' => 'cr-play-date-20', 'repeats' => true ) );
			$no   = filter_item( shape_b(), 'video', $post, array( 'title' => 'cr-play-title-20', 'date' => 'cr-play-date-20', 'repeats' => false ) );
			return false !== strpos( $yes, 'id="cr-play-title-20"' )
				&& false !== strpos( $yes, 'aria-labelledby="cr-play-title-20 cr-play-date-20"' )
				&& false !== strpos( $yes, 'id="cr-play-date-20" hidden>September 22, 2026</span>' )
				&& false === strpos( $no, 'cr-play-title-20' )
				&& false === strpos( $no, 'cr-play-date-20' )
				&& false === strpos( $no, 'aria-labelledby' );
		}
	);

	check_case(
		'Score',
		static function (): bool {
			reset_state();
			$post = play_post( 21 );
			$GLOBALS['cr_kind_facts'][21]['hours_label'] = '';
			$none = filter_item( shape_b(), 'video', $post, array() );
			$GLOBALS['cr_kind_facts'][21]['hours_label'] = '1 hour played';
			$one = filter_item( shape_b(), 'video', $post, array() );
			return false === strpos( $none, 'cr-cabinet__score' ) && false !== strpos( $one, '1 hour played' );
		}
	);

	check_case(
		'Gates',
		static function (): bool {
			reset_state();
			$post  = play_post( 22, 'board' );
			$html  = shape_b();
			$child = array();
			$status = 0;
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin 2>&1', $child, $status );
			return $html === filter_item( $html, 'board', $post, array() )
				&& $html === filter_item( $html, '', $post, array() )
				&& $html === filter_stream_card( $html, 'board', $post )
				&& $html === filter_stream_card( $html, '', $post )
				&& $html === filter_item( $html, 'video', new \stdClass(), array() )
				&& '' === filter_item( '', 'video', $post, array() )
				&& 0 === $status
				&& array( 'NO_PLUGIN_OK' ) === $child;
		}
	);

	check_case(
		'Locked',
		static function (): bool {
			reset_state();
			$post = play_post( 23 );
			$GLOBALS['cr_kind_facts'][23] = array();
			$empty = filter_item( shape_b(), 'video', $post, array() );
			$stream_empty = filter_stream_card( shape_b(), 'video', $post );
			$GLOBALS['cr_kind_facts'][23] = base_facts();
			$protected = filter_item( protected_shape(), 'video', $post, array() );
			$stream_protected = filter_stream_card( protected_shape(), 'video', $post );
			return false !== strpos( $empty, 'cr-cabinet--locked' )
				&& false !== strpos( $protected, 'cr-cabinet--locked' )
				&& false === strpos( $empty, 'cr-cabinet__screen' )
				&& false === strpos( $protected, 'cr-cabinet__screen' )
				&& shape_b() === $stream_empty
				&& protected_shape() === $stream_protected;
		}
	);

	check_case(
		'Cartridge, shape B and shape A',
		static function (): bool {
			reset_state();
			$post = play_post( 24 );
			$a = filter_stream_card( shape_a(), 'video', $post );
			$b = filter_stream_card( shape_b(), 'video', $post );
			$GLOBALS['cr_kind_facts'][24]['platform'] = '';
			$no_platform = filter_stream_card( shape_b(), 'video', $post );
			return cartridge_ok( $a ) && cartridge_ok( $b ) && cartridge_ok( $no_platform, false ) && false !== strpos( $a, 'h-cite' ) && false !== strpos( $b, 'h-entry' );
		}
	);

	check_case(
		'Integration through TPLAY',
		static function (): bool {
			reset_state();
			$post = play_post( 25 );
			$GLOBALS['cr_request']['kind'] = 'play';
			$archive = dispatch( shape_b(), array( 'attrs' => array( 'className' => 'is-style-cr-play-item' ) ), new \WP_Block( array( 'postId' => 25 ) ) );
			$GLOBALS['cr_request']        = array();
			$GLOBALS['cr_stream_surface'] = true;
			$stream = dispatch( shape_b(), array( 'attrs' => array() ), new \WP_Block( array( 'postId' => 25 ) ) );
			$GLOBALS['cr_play_groups'][25] = 'board';
			$board = dispatch( shape_b(), array( 'attrs' => array() ), new \WP_Block( array( 'postId' => 25 ) ) );
			return false !== strpos( $archive, 'cr-play-item' )
				&& false !== strpos( $archive, 'cr-cabinet' )
				&& false !== strpos( $stream, 'cr-cartridge' )
				&& shape_b() === $board;
		}
	);

	check_case(
		'Single gate matrix',
		static function (): bool {
			reset_state();
			$post = play_post( 30 );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 30 );
			if ( ! is_video_single( true ) ) {
				return false;
			}
			$checks = array();
			$GLOBALS['cr_request'] = array( 'queried_id' => 30 );
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 999 );
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 30 );
			$GLOBALS['cr_terms'][30] = false;
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_terms'][30] = true;
			$GLOBALS['cr_play_groups'][30] = 'board';
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_play_groups'][30] = 'video';
			$GLOBALS['cr_passwords'][30] = true;
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_passwords'][30] = false;
			$GLOBALS['cr_kind_facts'][30] = array();
			$checks[] = ! is_video_single( true );
			$GLOBALS['cr_kind_facts'][30] = base_facts();
			$GLOBALS['cr_find_blocks'][30] = array();
			$checks[] = ! is_video_single( true );
			return ! in_array( false, $checks, true );
		}
	);

	check_case(
		'drop_template_header',
		static function (): bool {
			reset_state();
			play_post( 31 );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 31 );
			$group = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__header' ) );
			$image = array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'single-post__featured' ) );
			$other = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'elsewhere' ) );
			$drop = '' === drop_template_header( 'x', $group ) && '' === drop_template_header( 'x', $image ) && 'x' === drop_template_header( 'x', $other );
			$GLOBALS['cr_request'] = array();
			return $drop && 'x' === drop_template_header( 'x', $group );
		}
	);

	check_case(
		'single_cabinet',
		static function (): bool {
			reset_state();
			$post = play_post( 32, 'video', 'Post Title' );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 32 );
			$html = '<article class="pk-card k-play h-cite u-play-of"><h2>Old</h2></article><p class="after">After <a href="/after">link</a>.</p>';
			$out  = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$statuses = array( 'backlog' => 'INSERT COIN', 'wishlist' => 'INSERT COIN', 'playing' => 'CONTINUE', 'completed' => 'GAME OVER', 'abandoned' => 'GAME OVER' );
			$status_ok = true;
			foreach ( $statuses as $status => $label ) {
				$GLOBALS['cr_kind_facts'][32]['status'] = $status;
				$status_ok = $status_ok && false !== strpos( single_cabinet( $html, array( 'blockName' => 'core/post-content' ) ), '>' . $label . '</p>' );
			}
			$GLOBALS['cr_kind_facts'][32]['status'] = 'unknown';
			$unknown = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][32] = base_facts();
			$GLOBALS['cr_kind_facts'][32]['hours_label'] = '';
			$no_hours = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][32] = base_facts();
			$GLOBALS['cr_kind_facts'][32]['platform'] = '';
			$no_platform = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][32] = base_facts();
			$GLOBALS['cr_kind_facts'][32]['played_at'] = array( '', '' );
			$no_played = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][32] = base_facts();
			$GLOBALS['cr_kind_facts'][32]['review'] = '<p> </p>';
			$no_review = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][32] = base_facts();
			$GLOBALS['cr_kind_pictures'][32] = array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '', 'remote' => false, 'suppress_featured' => false );
			$no_picture = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			return 1 === substr_count( $out, '<h1' )
				&& false !== strpos( $out, '<h1 class="cr-cabinet__title">Post Title</h1>' )
				&& strpos( $out, 'cr-cabinet__entry' ) < strpos( $out, '<article class="cr-cabinet' )
				&& false !== strpos( $out, 'data class="p-name" value="Post Title"' )
				&& false !== strpos( $out, 'data class="u-url" value="https://example.test/play/32"' )
				&& false !== strpos( $out, 'data class="dt-published" value="2026-09-22T10:00:00+00:00"' )
				&& false !== strpos( $out, 'p-author h-card' )
				&& strpos( $out, 'cr-cabinet__fact--game' ) < strpos( $out, 'cr-cabinet__fact--platform' )
				&& strpos( $out, 'cr-cabinet__fact--platform' ) < strpos( $out, 'cr-cabinet__fact--status' )
				&& strpos( $out, 'cr-cabinet__fact--status' ) < strpos( $out, 'cr-cabinet__fact--rating' )
				&& strpos( $out, 'cr-cabinet__fact--rating' ) < strpos( $out, 'cr-cabinet__fact--played' )
				&& 1 === substr_count( $out, '42 hours played' )
				&& 1 === substr_count( $out, 'Rated 4 of 5' )
				&& $status_ok
				&& false === strpos( $unknown, 'cr-cabinet__label' )
				&& false === strpos( $no_hours, 'cr-cabinet__score' )
				&& false === strpos( $no_platform, 'Platform</dt>' )
				&& false === strpos( $no_played, 'Played</dt>' )
				&& false !== strpos( $out, 'target="_blank" rel="noopener noreferrer"' )
				&& strpos( $out, 'rawg.example' ) < strpos( $out, 'Official Site' )
				&& strpos( $out, 'Official Site' ) < strpos( $out, 'Buy' )
				&& false !== strpos( $out, 'cr-cabinet__link u-url' )
				&& false !== strpos( $out, '(opens in a new tab)' )
				&& false !== strpos( $out, 'cr-cabinet__review' )
				&& false === strpos( $no_review, 'cr-cabinet__review' )
				&& false !== strpos( $out, 'alt="Custom cover alt"' )
				&& false !== strpos( $no_picture, 'cr-cabinet__screen--empty' )
				&& strpos( $out, 'boardgamegeek.com/boardgame/100' ) < strpos( $out, 'rawg.io/games/900001' )
				&& strpos( $out, 'rawg.io/games/900001' ) < strpos( $out, 'store.steampowered.com/app/1234' )
				&& false !== strpos( $out, '<p class="after">After <a href="/after">link</a>.</p>' )
				&& false === strpos( $out, 'pk-card' )
				&& false !== strpos( single_cabinet( '<p>Intro</p>', array( 'blockName' => 'core/post-content' ) ), '<article class="cr-cabinet' )
				&& '<p>x</p>' === single_cabinet( '<p>x</p>', array( 'blockName' => 'core/paragraph' ) );
		}
	);

	check_case(
		'Names',
		static function (): bool {
			reset_state();
			play_post( 40, 'video', '' );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 40 );
			$html = '<article class="pk-card k-play"></article>';
			$card = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_kind_facts'][40]['title'] = '';
			$synthetic = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			play_post( 41, 'video', "  GAME\tTITLE " );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 41 );
			$GLOBALS['cr_kind_facts'][41]['title'] = 'game title';
			$equal = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			play_post( 42, 'video', '<Bad "Title">');
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 42 );
			$GLOBALS['cr_kind_facts'][42]['platform'] = '<PC>';
			$GLOBALS['cr_kind_facts'][42]['review'] = '<p>Review <a href="https://example.test/?x=&quot;y">link</a></p>';
			$escaped = single_cabinet( $html, array( 'blockName' => 'core/post-content' ) );
			return false !== strpos( $card, '<h1 class="cr-cabinet__title p-name">Game Title</h1>' )
				&& false !== strpos( $card, 'data class="p-name" value="Game Title"' )
				&& false !== strpos( $synthetic, '<h1 class="cr-cabinet__title">Untitled 40</h1>' )
				&& false === strpos( $synthetic, 'data class="p-name"' )
				&& false === strpos( $equal, 'cr-cabinet__fact--game' )
				&& false !== strpos( $escaped, '&lt;Bad &quot;Title&quot;&gt;' )
				&& false !== strpos( $escaped, '&lt;PC&gt;' )
				&& false !== strpos( $escaped, 'https://example.test/?x=&quot;y' );
		}
	);

	check_case(
		'body_class and enqueue_styles',
		static function (): bool {
			reset_state();
			play_post( 50 );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 50 );
			$classes = body_class( array( 'base' ) );
			$run = static function ( array $request, bool $surface = false ): array {
				$GLOBALS['cr_request']        = $request;
				$GLOBALS['cr_stream_surface'] = $surface;
				$GLOBALS['cr_styles']         = array();
				enqueue_styles();
				return $GLOBALS['cr_styles'];
			};
			$expected = array( COURTNEYR_CHILD_URI . '/assets/css/cr-play-video.css', array(), COURTNEYR_CHILD_VERSION );
			$admin = $run( array( 'admin' => true ) );
			$single = $run( array( 'single' => true, 'queried_id' => 50 ) );
			$archive = $run( array( 'kind' => 'play' ) );
			$stream = $run( array(), true );
			play_post( 51, 'board' );
			$board = $run( array( 'single' => true, 'queried_id' => 51 ) );
			$watch = $run( array( 'kind' => 'watch' ) );
			return in_array( 'cr-video-single', $classes, true )
				&& $expected === ( $admin['courtneyr-play-video'] ?? null )
				&& $expected === ( $single['courtneyr-play-video'] ?? null )
				&& $expected === ( $archive['courtneyr-play-video'] ?? null )
				&& $expected === ( $stream['courtneyr-play-video'] ?? null )
				&& array() === $board
				&& array() === $watch;
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : $GLOBALS['cr_case_count'] . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
