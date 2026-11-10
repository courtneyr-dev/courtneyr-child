<?php
/**
 * `php tests/php/presentation-test.php`: presentation theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_PRESENTATION_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_PRESENTATION_THEME_DIR );

	$GLOBALS['cr_presentation_hooks']      = array();
	$GLOBALS['cr_presentation_styles']     = array();
	$GLOBALS['cr_presentation_enqueued']   = array();
	$GLOBALS['cr_presentation_registered'] = array();
	$GLOBALS['cr_presentation_posts']      = array();
	$GLOBALS['cr_presentation_facts']      = array();
	$GLOBALS['cr_presentation_terms']      = array();
	$GLOBALS['cr_presentation_passwords']  = array();
	$GLOBALS['cr_presentation_query_id']   = 262;
	$GLOBALS['cr_presentation_singular']   = true;
	$GLOBALS['cr_presentation_stream']     = true;
	$GLOBALS['cr_presentation_failures']   = array();

	class WP_Post {
		public int $ID;
		public string $post_title;
		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	class WP_Term {
		public string $name = 'Presentation';
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;
		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	class WP_Block_Type_Registry {
		private static ?self $instance = null;
		public bool $registered = true;
		public static function get_instance(): self {
			self::$instance ??= new self();
			return self::$instance;
		}
		public function is_registered( string $name ): bool {
			return $this->registered && 'post-kinds-indieweb/presentation-entry' === $name;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_presentation_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_presentation_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_presentation_styles'][] = array( $block, $args );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_presentation_enqueued'][] = array( $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_presentation_enqueued'][] = array( $handle, compact( 'src', 'deps', 'version' ) );
	}

	function wp_style_is( $handle, $status = 'enqueued' ): bool {
		return 'registered' === $status && isset( $GLOBALS['cr_presentation_registered'][ $handle ] );
	}

	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_presentation_registered'][ $handle ] = compact( 'src', 'deps', 'version' );
	}

	function wp_style_add_data( $handle, $key, $value ): void {
		$GLOBALS['cr_presentation_registered'][ $handle ][ $key ] = $value;
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && 'presentation' === $term;
	}

	function is_singular( $type = '' ): bool {
		return 'post' === $type && (bool) $GLOBALS['cr_presentation_singular'];
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && 'kind' === $taxonomy && in_array( $term, $GLOBALS['cr_presentation_terms'][ $post->ID ] ?? array(), true );
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_presentation_query_id'];
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_presentation_query_id'];
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? get_queried_object_id() : (int) $post;
		return $GLOBALS['cr_presentation_posts'][ $id ] ?? null;
	}

	function post_password_required( $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && ! empty( $GLOBALS['cr_presentation_passwords'][ $post->ID ] );
	}

	function get_the_title( $post = null ): string {
		$post = get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}

	function get_permalink( $post = null ): string {
		$post = get_post( $post );
		return 'https://example.test/presentation/' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '/';
	}

	function get_the_date( $format = '', $post = null ): string {
		return 'c' === $format ? '2026-11-09T10:00:00-05:00' : 'November 9, 2026';
	}

	function get_term_by( $field, $value, $taxonomy ): WP_Term {
		return new WP_Term();
	}

	function is_admin(): bool {
		return false;
	}

	function sanitize_key( $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ?? '' );
	}

	function wp_strip_all_tags( $text ): string {
		return trim( strip_tags( (string) $text ) );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_url( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function esc_html_e( $text, $domain = null ): void {
		echo esc_html( $text );
	}

	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	function cr_presentation_post( int $id, array $facts = array() ): WP_Post {
		$post = new WP_Post( $id, 'Building accessible block patterns' );
		$GLOBALS['cr_presentation_posts'][ $id ] = $post;
		$GLOBALS['cr_presentation_terms'][ $id ] = array( 'presentation' );
		$GLOBALS['cr_presentation_passwords'][ $id ] = false;
		$GLOBALS['cr_presentation_facts'][ $id ] = array_merge(
			array(
				'title' => $post->post_title,
				'event_name' => 'Example Web Camp',
				'event_type' => 'talk',
				'event_type_label' => 'Talk',
				'date_iso' => '2026-11-09T10:00:00-05:00',
				'date_display' => 'November 9, 2026',
				'location' => 'Sentinel Hall, 1 Sentinel Way',
				'slides_url' => 'https://sentinel-slides.example/locked',
			),
			$facts
		);
		$GLOBALS['cr_presentation_query_id'] = $id;
		return $post;
	}

	function cr_presentation_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_presentation_failures'][] = $message;
		}
	}

	function cr_presentation_same( $expected, $actual, string $message ): void {
		cr_presentation_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_presentation_contains( string $needle, string $haystack, string $message ): void {
		cr_presentation_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_presentation_not_contains( string $needle, string $haystack, string $message ): void {
		cr_presentation_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_presentation_has_hook( string $hook, string $callback, int $priority, int $accepted_args ): bool {
		foreach ( $GLOBALS['cr_presentation_hooks'] as $registered ) {
			if ( array( $hook, $callback, $priority, $accepted_args ) === $registered ) {
				return true;
			}
		}
		return false;
	}

	function cr_presentation_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_presentation_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_presentation_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_presentation_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		fwrite( STDERR, "FAIL: set CR_WP_CORE to a WordPress checkout\n" );
		exit( 1 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	require_once $core . '/wp-includes/class-wp-block-parser-block.php';
	require_once $core . '/wp-includes/class-wp-block-parser-frame.php';
	require_once $core . '/wp-includes/class-wp-block-parser.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_presentation_stream'];
	}
}

namespace PKIW {
	final class Presentation {}
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_presentation_facts'][ $post_id ] ?? array();
	}
	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card">Author</span>';
	}
	function untitled_name( \WP_Post $post, bool $with_kind = true ): string {
		return 'Untitled presentation';
	}
}

namespace {
	require CR_PRESENTATION_THEME_DIR . '/inc/presentation.php';
	require CR_PRESENTATION_THEME_DIR . '/inc/stream-presentation.php';

	cr_presentation_check(
		'presentation hooks keep their required priorities and accepted arguments',
		static function (): void {
			$expected = array(
				array( 'init', 'Courtneyr\\Child\\Presentation\\register_block_styles', 20, 1 ),
				array( 'courtneyr_child_single_object_holds_h1', 'Courtneyr\\Child\\Presentation\\keep_protected_template_header', PHP_INT_MAX, 2 ),
				array( 'pkiw_presentation_record_args', 'Courtneyr\\Child\\Presentation\\record_args', 10, 2 ),
				array( 'render_block_core/post-title', 'Courtneyr\\Child\\Presentation\\drop_post_title', 10, 3 ),
				array( 'render_block_core/post-date', 'Courtneyr\\Child\\Presentation\\protected_date', 5, 3 ),
				array( 'render_block_core/embed', 'Courtneyr\\Child\\Presentation\\frame_embed', 20, 2 ),
				array( 'enqueue_block_assets', 'Courtneyr\\Child\\Presentation\\enqueue', 10, 1 ),
				array( 'perfmatters_rucss_excluded_stylesheets', 'Courtneyr\\Child\\Presentation\\rucss_exclusions', 20, 1 ),
				array( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamPresentation\\badge_card', 10, 3 ),
			);
			foreach ( $expected as $hook ) {
				cr_presentation_assert( cr_presentation_has_hook( ...$hook ), 'missing hook ' . $hook[0] . ' at priority ' . $hook[2] );
			}
		}
	);

	cr_presentation_check(
		'the presentation entry style and editor sheet register on the plugin block',
		static function (): void {
			\Courtneyr\Child\Presentation\register_block_styles();
			cr_presentation_same( 'post-kinds-indieweb/presentation-entry', $GLOBALS['cr_presentation_styles'][0][0] ?? '', 'block style target' );
			cr_presentation_same( 'cr-badge-wall', $GLOBALS['cr_presentation_styles'][0][1]['name'] ?? '', 'block style slug' );
			cr_presentation_same( 'courtneyr-presentation', $GLOBALS['cr_presentation_enqueued'][0][1]['handle'] ?? '', 'editor handle' );
		}
	);

	cr_presentation_check(
		'the queried presentation record owns the H1 and public date',
		static function (): void {
			$post = cr_presentation_post( 262 );
			$args = \Courtneyr\Child\Presentation\record_args( array( 'title_level' => 0, 'show_date' => false, 'class' => '' ), $post );
			cr_presentation_same( 1, $args['title_level'], 'record title level' );
			cr_presentation_same( true, $args['show_date'], 'record date' );
			cr_presentation_same( 'cr-badge cr-badge--single', $args['class'], 'record class' );
			$queried_title = new WP_Block( array( 'postId' => 262 ) );
			cr_presentation_same( '', \Courtneyr\Child\Presentation\drop_post_title( '<h1>Template title</h1>', array(), $queried_title ), 'queried template title drops' );
			cr_presentation_contains( 'Related title', \Courtneyr\Child\Presentation\drop_post_title( '<h3>Related title</h3>', array(), new WP_Block( array( 'postId' => 999, 'queryId' => 7 ) ) ), 'related-post title remains' );
			cr_presentation_contains( 'Queried loop title', \Courtneyr\Child\Presentation\drop_post_title( '<h3>Queried loop title</h3>', array(), new WP_Block( array( 'postId' => 262, 'queryId' => 7 ) ) ), 'queried post title inside Query Loop remains' );
			cr_presentation_contains( 'Context-free title', \Courtneyr\Child\Presentation\drop_post_title( '<h2>Context-free title</h2>', array(), null ), 'title without block context remains' );

			$GLOBALS['cr_presentation_passwords'][262] = true;
			cr_presentation_same( false, \Courtneyr\Child\Presentation\keep_protected_template_header( true, $post ), 'protected presentation keeps the template header and its date' );
			cr_presentation_contains( 'Template title', \Courtneyr\Child\Presentation\drop_post_title( '<h1>Template title</h1>', array(), $queried_title ), 'protected title remains' );
			$date_block = array( 'attrs' => array( 'format' => 'F j, Y', 'className' => 'single-post__date cr-dt-published' ) );
			$date       = \Courtneyr\Child\Presentation\protected_date( '', $date_block, $queried_title );
			cr_presentation_contains( 'class="single-post__date cr-dt-published wp-block-post-date"', $date, 'protected presentation prints the lede date core leaves empty' );
			cr_presentation_contains( '<time datetime="2026-11-09T10:00:00-05:00">November 9, 2026</time>', $date, 'protected date carries the stored day' );
			cr_presentation_same( '<div>kept</div>', \Courtneyr\Child\Presentation\protected_date( '<div>kept</div>', $date_block, $queried_title ), 'a rendered date is left alone' );
			cr_presentation_same( '', \Courtneyr\Child\Presentation\protected_date( '', array( 'attrs' => array( 'className' => 'related-post__date' ) ), $queried_title ), 'other date blocks stay empty' );
			cr_presentation_same( '', \Courtneyr\Child\Presentation\protected_date( '', $date_block, new WP_Block( array( 'postId' => 999 ) ) ), 'another post gets no date' );
			$GLOBALS['cr_presentation_passwords'][262] = false;
			cr_presentation_same( '', \Courtneyr\Child\Presentation\protected_date( '', $date_block, $queried_title ), 'public presentation gets no fallback date' );
			cr_presentation_same( true, \Courtneyr\Child\Presentation\keep_protected_template_header( true, $post ), 'public presentation preserves another H1 owner' );
			$GLOBALS['cr_presentation_terms'][262] = array( 'note' );
			cr_presentation_contains( 'Template title', \Courtneyr\Child\Presentation\drop_post_title( '<h1>Template title</h1>', array(), $queried_title ), 'other-kind title remains' );
		}
	);

	cr_presentation_check(
		'supported deck and recording embeds gain presentation chrome and accessible iframe titles',
		static function (): void {
			cr_presentation_post( 263 );
			$iframe = '<figure><iframe src="https://example.test/embed"></iframe></figure>';
			$deck = \Courtneyr\Child\Presentation\frame_embed( $iframe, array( 'attrs' => array( 'providerNameSlug' => 'speaker-deck' ) ) );
			cr_presentation_contains( 'class="cr-presentation-frame"', $deck, 'deck frame class' );
			cr_presentation_contains( 'title="Slides: Building accessible block patterns"', $deck, 'deck iframe title' );
			cr_presentation_contains( 'loading="lazy"', $deck, 'deck iframe loading' );

			$recording = \Courtneyr\Child\Presentation\frame_embed( $iframe, array( 'attrs' => array( 'providerNameSlug' => 'youtube' ) ) );
			cr_presentation_contains( 'title="Recording: Building accessible block patterns"', $recording, 'recording iframe title' );
			$authored = '<figure><iframe src="https://example.test/embed" title="Authored frame title" loading="lazy"></iframe></figure>';
			$preserved = \Courtneyr\Child\Presentation\frame_embed( $authored, array( 'attrs' => array( 'providerNameSlug' => 'speaker-deck' ) ) );
			cr_presentation_contains( 'title="Authored frame title"', $preserved, 'authored iframe title remains' );
			cr_presentation_same( 1, substr_count( $preserved, 'title=' ), 'authored iframe gets no duplicate title' );
			$wordpress_tv = \Courtneyr\Child\Presentation\frame_embed( $iframe, array( 'attrs' => array( 'providerNameSlug' => 'wordpress-tv' ) ) );
			cr_presentation_contains( 'title="Recording: Building accessible block patterns"', $wordpress_tv, 'WordPress.tv iframe title' );
			$able = '<div class="able-player"><video controls></video></div>';
			cr_presentation_contains( 'cr-presentation-frame', \Courtneyr\Child\Presentation\frame_embed( $able, array( 'attrs' => array( 'providerNameSlug' => 'youtube' ) ) ), 'Able Player root gains frame class' );
			$able_media = \Courtneyr\Child\Presentation\frame_embed( '<figure class="cr-media"><video data-able-player data-youtube-id="sample00001"></video></figure>', array( 'attrs' => array( 'providerNameSlug' => 'youtube' ) ) );
			cr_presentation_contains( 'data-heading-level="2"', $able_media, 'Able Player heading sits under the record H1' );
			$able_set = \Courtneyr\Child\Presentation\frame_embed( '<figure class="cr-media"><video data-able-player data-heading-level="0"></video></figure>', array( 'attrs' => array( 'providerNameSlug' => 'youtube' ) ) );
			cr_presentation_contains( 'data-heading-level="0"', $able_set, 'an authored Able Player heading level remains' );
			cr_presentation_not_contains( 'data-heading-level="2"', $able_set, 'authored heading level is not replaced' );
			cr_presentation_same( $iframe, \Courtneyr\Child\Presentation\frame_embed( $iframe, array( 'attrs' => array( 'providerNameSlug' => 'soundcloud' ) ) ), 'unsupported embed unchanged' );

			$GLOBALS['cr_presentation_singular'] = false;
			cr_presentation_same( $iframe, \Courtneyr\Child\Presentation\frame_embed( $iframe, array( 'attrs' => array( 'providerNameSlug' => 'youtube' ) ) ), 'archive embed unchanged' );
			$GLOBALS['cr_presentation_singular'] = true;
		}
	);

	cr_presentation_check(
		'the Stream adapter prints one public destination and omits sentinel-bearing fields',
		static function (): void {
			cr_presentation_post( 264 );
			$GLOBALS['cr_presentation_facts'][264]['locked_sentinel'] = 'Sentinel Locked Summit';
			$GLOBALS['cr_presentation_facts'][264]['private_sentinel'] = 'Sentinel Private Summit';
			$card = '<article class="pk-card"><h3 class="pk-title">Old</h3><iframe>sentinel</iframe></article>';
			$html = \Courtneyr\Child\StreamPresentation\badge_card( $card, array(), new WP_Block( array( 'postId' => 264 ) ) );
			foreach ( array( 'cr-badge--stream', 'pk-kindlabel', 'cr-badge__strap', 'cr-badge__title', 'cr-badge__event', 'cr-badge__date', 'cr-badge__type', 'Example Web Camp' ) as $allowed ) {
				cr_presentation_contains( $allowed, $html, 'Stream output ' . $allowed );
			}
			cr_presentation_same( 1, substr_count( $html, '<a ' ), 'Stream link count' );
			foreach ( array( 'Sentinel Hall, 1 Sentinel Way', 'https://sentinel-slides.example/locked', 'Sentinel Locked Summit', 'Sentinel Private Summit', '<iframe' ) as $sentinel ) {
				cr_presentation_not_contains( $sentinel, $html, 'Stream sentinel' );
			}

			$GLOBALS['cr_presentation_facts'][264]['event_name'] = '';
			$GLOBALS['cr_presentation_facts'][264]['event_type'] = '';
			$GLOBALS['cr_presentation_facts'][264]['event_type_label'] = '';
			$minimal = \Courtneyr\Child\StreamPresentation\badge_card( $card, array(), new WP_Block( array( 'postId' => 264 ) ) );
			cr_presentation_not_contains( 'cr-badge__event', $minimal, 'empty event row omitted' );
			cr_presentation_not_contains( 'cr-badge__type', $minimal, 'empty type row omitted' );

			$protected = '<article class="pk-card pk-card--protected">Protected</article>';
			cr_presentation_same( $protected, \Courtneyr\Child\StreamPresentation\badge_card( $protected, array(), new WP_Block( array( 'postId' => 264 ) ) ), 'protected card passes through' );

			$GLOBALS['cr_presentation_passwords'][264] = true;
			cr_presentation_same( $card, \Courtneyr\Child\StreamPresentation\badge_card( $card, array(), new WP_Block( array( 'postId' => 264 ) ) ), 'password-required post passes through without relying on a protected class' );
			$GLOBALS['cr_presentation_passwords'][264] = false;
			$GLOBALS['cr_presentation_facts'][264] = array();
			cr_presentation_same( $card, \Courtneyr\Child\StreamPresentation\badge_card( $card, array(), new WP_Block( array( 'postId' => 264 ) ) ), 'empty facts pass through' );
			$GLOBALS['cr_presentation_stream'] = false;
			cr_presentation_same( $card, \Courtneyr\Child\StreamPresentation\badge_card( $card, array(), new WP_Block( array( 'postId' => 264 ) ) ), 'non-Stream surface passes through' );
			$GLOBALS['cr_presentation_stream'] = true;
		}
	);

	cr_presentation_check(
		'the template and pattern parse with query, fallback, pager and no-results contracts',
		static function (): void {
			$parser = new WP_Block_Parser();
			$template = (string) file_get_contents( CR_PRESENTATION_THEME_DIR . '/templates/taxonomy-kind-presentation.html' );
			$blocks = $parser->parse( $template );
			cr_presentation_same( 'core/group', $blocks[0]['blockName'] ?? null, 'template starts with header group' );
			cr_presentation_contains( 'cr-archive cr-archive--presentation', $template, 'template archive class' );
			cr_presentation_contains( 'courtneyr-child/cr-presentation-wall-loop', $template, 'template pattern' );
			cr_presentation_contains( 'courtneyr-child/cr-browse-all', $template, 'browse pattern' );

			ob_start();
			require CR_PRESENTATION_THEME_DIR . '/patterns/cr-presentation-wall-loop.php';
			$pattern = (string) ob_get_clean();
			$pattern_blocks = $parser->parse( $pattern );
			cr_presentation_same( 'core/query', $pattern_blocks[0]['blockName'] ?? null, 'pattern starts with Query' );
			foreach ( array( '"queryId":262', '"perPage":12', 'post-kinds-indieweb/presentation-entry', '"headingLevel":2', '"sectionOrder":"desc"', 'is-style-cr-badge-wall', 'wp:query-pagination', 'wp:query-no-results' ) as $token ) {
				cr_presentation_contains( $token, $pattern, 'pattern token' );
			}

			WP_Block_Type_Registry::get_instance()->registered = false;
			ob_start();
			require CR_PRESENTATION_THEME_DIR . '/patterns/cr-presentation-wall-loop.php';
			$fallback = (string) ob_get_clean();
			cr_presentation_same( 'core/pattern', $parser->parse( $fallback )[0]['blockName'] ?? null, 'fallback parses as pattern block' );
			cr_presentation_contains( 'courtneyr-child/cr-archive-stream-loop', $fallback, 'fallback pattern' );
		}
	);

	cr_presentation_check(
		'RUCSS, CSS budget, dark selectors and HTML-processing guardrails are explicit',
		static function (): void {
			cr_presentation_same( array( 'existing.css', 'cr-presentation.css', 'presentation.css' ), \Courtneyr\Child\Presentation\rucss_exclusions( array( 'existing.css' ) ), 'RUCSS exclusions' );
			foreach ( array( 'presentation.php', 'stream-presentation.php' ) as $file ) {
				cr_presentation_assert( ! str_contains( (string) file_get_contents( CR_PRESENTATION_THEME_DIR . '/inc/' . $file ), 'preg_' ), $file . ' adds no preg_' );
			}
			$css = (string) file_get_contents( CR_PRESENTATION_THEME_DIR . '/assets/css/cr-presentation.css' );
			cr_presentation_assert( strlen( $css ) <= 30 * 1024, 'cr-presentation.css stays within 30 KB' );
			cr_presentation_contains( ':root:not([data-theme="light"]) .cr-badge-wall .pkiw-presentation-badge', $css, 'system dark selector' );
			cr_presentation_contains( ':root[data-theme="dark"] .cr-badge-wall .pkiw-presentation-badge', $css, 'manual dark selector' );
			cr_presentation_not_contains( ':root:not([data-theme="light"]) [data-pkiw-event-type="talk"]', $css, 'system dark talk band keeps its light hue (art r2 dark comp; fidelity ruling 2026-11-09)' );
			cr_presentation_not_contains( ':root[data-theme="dark"] [data-pkiw-event-type="keynote"]', $css, 'manual dark keynote band keeps its light hue (art r2 dark comp; fidelity ruling 2026-11-09)' );
			cr_presentation_contains( 'box-shadow: inset 0 2rem 0 var(--cr-badge-band), var(--cr-shadow-hard), var(--cr-badge-focus-halo, 0 0 0 0 transparent);', $css, 'badges have a type-colored top band that keeps the focus halo' );
			cr_presentation_contains( ':where(.cr-badge-wall .pkiw-presentation-badge', $css, 'band default has zero specificity so type bands win' );
			cr_presentation_contains( '--cr-badge-focus-halo: 0 0 0 7px var(--cr-focus-halo);', $css, 'focus sets the halo property, not the whole shadow' );
			cr_presentation_contains( 'gap: 0 var(--cr-space-2xl);', $css, 'single grid uses a column gap only' );
			cr_presentation_not_contains( 'gap: var(--cr-space-2xl);', $css, 'single grid has no implicit-row gap' );
			cr_presentation_contains( '@supports selector(:has(a:focus-visible))', $css, ':has focus ring is progressive enhancement' );
			cr_presentation_contains( '.cr-badge__title a:focus-visible', $css, 'badge link keeps a fallback focus ring' );
			cr_presentation_contains( 'min-block-size: 2.75rem;', $css, 'record links meet the 44px target contract' );
			cr_presentation_contains( 'body.single-post.kind-presentation:has(.pkiw-presentation-record)', $css, 'only singles with a record hide the lede date' );
			cr_presentation_contains( '.cr-badge--stream::before', $css, 'Stream badge has one tape piece' );
			cr_presentation_contains( 'inline-size: 100%;', $css, 'Stream badge matches its collage column width' );
			cr_presentation_contains( 'inset 0 0 0 2px var(--cr-badge-border)', $css, 'Stream badge keeps its two-pixel border inside that width' );
			cr_presentation_contains( '@media (forced-colors: active)', $css, 'forced colors' );
			cr_presentation_contains( '@media (prefers-reduced-motion: reduce)', $css, 'reduced motion' );
			$pruning = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/stream-style-pruning.php' );
			cr_presentation_contains( "'pkiw-presentation',", $pruning, 'plugin presentation sheet is inlined, not a blocking link (review N2)' );
			$stream = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/stream-presentation.php' );
			cr_presentation_contains( '\\PKIW\\get_kind_label( $fallback, \'presentation\', \'stream-card\' )', $stream, 'Stream kind label comes from the plugin card-label source (review N8)' );
		}
	);

	if ( $GLOBALS['cr_presentation_failures'] ) {
		foreach ( $GLOBALS['cr_presentation_failures'] as $failure ) {
			fwrite( STDERR, '  ' . $failure . "\n" );
		}
		exit( 1 );
	}
	echo "all passed\n";
}
