<?php
/**
 * `php tests/php/play-video-mf2-test.php`: checks video play microformats.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	$mf2_autoload = getenv( 'MF2_AUTOLOAD' );
	$mf2_default  = getenv( 'HOME' ) . '/projects/post-kinds-for-indieweb/vendor/autoload.php';
	$mf2_file     = is_string( $mf2_autoload ) && '' !== $mf2_autoload && is_file( $mf2_autoload ) ? $mf2_autoload : '';
	if ( '' === $mf2_file && is_file( $mf2_default ) ) {
		$mf2_file = $mf2_default;
	}
	if ( '' === $mf2_file ) {
		echo "SKIP php-mf2 not found\n";
		exit( 0 );
	}
	require_once $mf2_file;

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
}

namespace Courtneyr\Child\PlayVideo\Mf2Tests {

	use function Courtneyr\Child\PlayVideo\filter_item;
	use function Courtneyr\Child\PlayVideo\filter_stream_card;
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

	/** Parse a fragment with php-mf2. */
	function parse_fragment( string $html ): array {
		return \Mf2\parse( $html, 'https://example.test/p/50' );
	}

	/** Find the first item with a microformat type. */
	function first_item( array $parsed, string $type ): ?array {
		return first_item_in( $parsed['items'] ?? array(), $type );
	}

	/** Find the first item with a microformat type in a list. */
	function first_item_in( array $items, string $type ): ?array {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && in_array( $type, $item['type'] ?? array(), true ) ) {
				return $item;
			}
			if ( is_array( $item ) ) {
				foreach ( $item['properties'] ?? array() as $values ) {
					$found = first_item_in( array_filter( $values, 'is_array' ), $type );
					if ( null !== $found ) {
						return $found;
					}
				}
			}
		}
		return null;
	}

	/** Return one property array. */
	function prop( array $item, string $name ): array {
		return $item['properties'][ $name ] ?? array();
	}

	/** Return string values from a property array. */
	function string_values( array $values ): array {
		return array_values(
			array_map(
				static function ( $value ): string {
					if ( is_array( $value ) ) {
						return (string) ( $value['value'] ?? '' );
					}
					return (string) $value;
				},
				$values
			)
		);
	}

	/** Whether a property has the exact string value. */
	function has_value( array $item, string $name, string $value ): bool {
		return in_array( $value, string_values( prop( $item, $name ) ), true );
	}

	/** Return the first nested typed property. */
	function nested_prop( array $item, string $property, string $type ): ?array {
		foreach ( prop( $item, $property ) as $value ) {
			if ( is_array( $value ) && in_array( $type, $value['type'] ?? array(), true ) ) {
				return $value;
			}
		}
		return null;
	}

	/** Whether the cite has the expected uid. */
	function cite_has_rawg_uid( array $cite ): bool {
		return has_value( $cite, 'uid', 'https://rawg.io/games/900001' );
	}

	/** Whether the cite has all single-cabinet provider uids. */
	function cite_has_provider_uids( array $cite ): bool {
		$uids = string_values( prop( $cite, 'uid' ) );
		foreach ( array( 'https://boardgamegeek.com/boardgame/100', 'https://rawg.io/games/900001', 'https://store.steampowered.com/app/1234' ) as $uid ) {
			if ( ! in_array( $uid, $uids, true ) ) {
				return false;
			}
		}
		return true;
	}

	/** Wrapper for plugin-repaired card-only archive entries. */
	function archive_entry_wrapper( string $html ): string {
		return '<li class="h-entry">' . entry_props_html( 1 ) . $html . '</li>';
	}

	/** Hidden entry properties supplied by the plugin repair. */
	function entry_props_html( int $post_id ): string {
		return '<span class="pk-entry-props" hidden><data class="u-url" value="https://example.test/p/' . $post_id . '"></data><data class="dt-published" value="2026-09-22T10:00:00+00:00"></data><span class="p-author h-card"><data class="p-name" value="Courtney"></data></span></span>';
	}

	/** Real post-content block shape after the plugin wraps singular content. */
	function single_content_shape( int $post_id ): string {
		return '<div class="entry-content wp-block-post-content single-post__content"><div class="h-entry kind-play pkiw-singular-entry"><p>Intro</p><article class="pk-card k-play h-cite u-play-of"><h2>Old</h2></article><a class="u-url" href="https://example.test/play/' . $post_id . '" hidden></a><time class="dt-published" datetime="2026-09-22T10:00:00+00:00" hidden></time></div></div>';
	}

	check_case(
		'single cabinet mf2',
		static function (): bool {
			reset_state();
			play_post( 50, 'video', 'Post Title' );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 50 );
			$html   = single_cabinet( single_content_shape( 50 ), array( 'blockName' => 'core/post-content' ) );
			$parsed = parse_fragment( $html );
			$items  = $parsed['items'] ?? array();
			if ( 1 !== count( $items ) || ! is_array( $items[0] ?? null ) || ! in_array( 'h-entry', $items[0]['type'] ?? array(), true ) ) {
				return false;
			}
			$entry  = $items[0];
			$cite   = nested_prop( $entry, 'play-of', 'h-cite' );
			$author = nested_prop( $entry, 'author', 'h-card' );
			return array( 'Post Title' ) === string_values( prop( $entry, 'name' ) )
				&& 1 === count( prop( $entry, 'name' ) )
				&& has_value( $entry, 'published', '2026-09-22T10:00:00+00:00' )
				&& has_value( $entry, 'url', 'https://example.test/play/50' )
				&& null !== $author
				&& has_value( $author, 'name', 'Courtney' )
				&& null !== $cite
				&& has_value( $cite, 'name', 'Game Title' )
				&& has_value( $cite, 'rating', '4' )
				&& cite_has_provider_uids( $cite )
				&& false === strpos( $html, 'Old' );
		}
	);

	check_case(
		'single cabinet synthetic title has no entry name',
		static function (): bool {
			reset_state();
			play_post( 51, 'video', '' );
			$GLOBALS['cr_kind_facts'][51]['title'] = '';
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 51 );
			$html   = single_cabinet( single_content_shape( 51 ), array( 'blockName' => 'core/post-content' ) );
			$parsed = parse_fragment( $html );
			$items  = $parsed['items'] ?? array();
			if ( 1 !== count( $items ) || ! is_array( $items[0] ?? null ) || ! in_array( 'h-entry', $items[0]['type'] ?? array(), true ) ) {
				return false;
			}
			$entry = $items[0];
			return false !== strpos( $html, '<h1 class="cr-cabinet__title">Untitled 51</h1>' )
				&& array() === prop( $entry, 'name' );
		}
	);

	check_case(
		'single cabinet fallback without wrapper',
		static function (): bool {
			reset_state();
			play_post( 52, 'video', 'Post Title' );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 52 );
			$html = single_cabinet( '<p>Intro</p><article class="pk-card k-play h-cite u-play-of"><h2>Old</h2></article>', array( 'blockName' => 'core/post-content' ) );
			return str_starts_with( $html, '<span class="cr-cabinet__entry" hidden>' )
				&& 1 === substr_count( $html, '<article class="cr-cabinet cr-cabinet--single h-cite u-play-of"' )
				&& false === strpos( $html, 'Old' );
		}
	);

	check_case(
		'single cabinet lands inside the wrapper',
		static function (): bool {
			reset_state();
			play_post( 53, 'video', 'Post Title' );
			$GLOBALS['cr_request'] = array( 'single' => true, 'queried_id' => 53 );
			$html         = single_cabinet( single_content_shape( 53 ), array( 'blockName' => 'core/post-content' ) );
			$wrapper_open = '<div class="h-entry kind-play pkiw-singular-entry">';
			$open_pos     = strpos( $html, $wrapper_open );
			$span_pos     = strpos( $html, '<span class="cr-cabinet__entry"' );
			$url_pos      = strpos( $html, '<a class="u-url" href="https://example.test/play/53" hidden></a>' );
			$url_close    = false === $url_pos ? false : strpos( $html, '</a>', $url_pos );
			return false !== $open_pos
				&& false !== $span_pos
				&& false !== $url_pos
				&& false !== $url_close
				&& $span_pos >= $open_pos + strlen( $wrapper_open )
				&& $span_pos < $url_close
				&& ! str_starts_with( $html, '<span class="cr-cabinet__entry" hidden>' );
		}
	);

	check_case(
		'archive card-only cabinet mf2',
		static function (): bool {
			reset_state();
			$post  = play_post( 1 );
			$html  = filter_item( shape_a(), 'video', $post, array( 'repeats' => false ) );
			$entry = first_item( parse_fragment( archive_entry_wrapper( $html ) ), 'h-entry' );
			$cite  = null === $entry ? null : nested_prop( $entry, 'play-of', 'h-cite' );
			return null !== $entry
				&& has_value( $entry, 'url', 'https://example.test/p/1' )
				&& has_value( $entry, 'published', '2026-09-22T10:00:00+00:00' )
				&& null !== nested_prop( $entry, 'author', 'h-card' )
				&& null !== $cite
				&& has_value( $cite, 'name', 'Title' )
				&& cite_has_rawg_uid( $cite );
		}
	);

	check_case(
		'archive generic cabinet mf2',
		static function (): bool {
			reset_state();
			$post  = play_post( 2 );
			$shape = str_replace( '<div class="pk-body">', entry_props_html( 2 ) . '<div class="pk-body">', shape_b() );
			$html  = filter_item( $shape, 'video', $post, array( 'repeats' => false ) );
			$entry = first_item( parse_fragment( $html ), 'h-entry' );
			$cite  = null === $entry ? null : nested_prop( $entry, 'play-of', 'h-cite' );
			return null !== $entry
				&& has_value( $entry, 'url', 'https://example.test/p/2' )
				&& has_value( $entry, 'published', '2026-09-22T10:00:00+00:00' )
				&& null !== nested_prop( $entry, 'author', 'h-card' )
				&& null !== $cite
				&& has_value( $cite, 'name', 'Title' )
				&& cite_has_rawg_uid( $cite )
				&& 1 === substr_count( $html, '<a ' );
		}
	);

	check_case(
		'stream cartridge mf2',
		static function (): bool {
			reset_state();
			$post = play_post( 3 );
			$a    = filter_stream_card( shape_a(), 'video', $post );
			$b    = filter_stream_card( shape_b(), 'video', $post );
			$entry_a = first_item( parse_fragment( '<div class="h-entry">' . $a . '</div>' ), 'h-entry' );
			$entry_b = first_item( parse_fragment( $b ), 'h-entry' );
			$cite_a  = null === $entry_a ? null : nested_prop( $entry_a, 'play-of', 'h-cite' );
			$cite_b  = null === $entry_b ? null : nested_prop( $entry_b, 'play-of', 'h-cite' );
			return null !== $cite_a
				&& null !== $cite_b
				&& has_value( $cite_a, 'name', 'Title' )
				&& has_value( $cite_b, 'name', 'Title' )
				&& cite_has_rawg_uid( $cite_a )
				&& cite_has_rawg_uid( $cite_b )
				&& 1 === substr_count( $a, '<a ' )
				&& 1 === substr_count( $b, '<a ' );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
