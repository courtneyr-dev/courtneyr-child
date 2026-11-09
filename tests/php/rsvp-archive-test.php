<?php
/**
 * `php tests/php/rsvp-archive-test.php`: RSVP archive theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_RSVP_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_RSVP_THEME_DIR );

	$GLOBALS['cr_rsvp_hooks']    = array();
	$GLOBALS['cr_rsvp_styles']   = array();
	$GLOBALS['cr_rsvp_enqueued'] = array();
	$GLOBALS['cr_rsvp_registered_styles'] = array();
	$GLOBALS['cr_rsvp_failures'] = array();

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
			return $this->registered && 'post-kinds-indieweb/rsvp-entry' === $name;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_rsvp_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_rsvp_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_rsvp_styles'][] = array( $block, $args );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_rsvp_enqueued'][] = array( $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_rsvp_enqueued'][] = array( $handle, compact( 'src', 'deps', 'version' ) );
	}

	function wp_style_is( $handle, $status = 'enqueued' ): bool {
		return 'registered' === $status && isset( $GLOBALS['cr_rsvp_registered_styles'][ $handle ] );
	}

	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_rsvp_registered_styles'][ $handle ] = compact( 'src', 'deps', 'version' );
	}

	function wp_style_add_data( $handle, $key, $value ): void {
		$GLOBALS['cr_rsvp_registered_styles'][ $handle ][ $key ] = $value;
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && 'rsvp' === $term;
	}

	function is_singular( $type = '' ): bool {
		return false;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		return 'rsvp' === $term && 'kind' === $taxonomy;
	}

	function get_queried_object_id(): int {
		return 0;
	}

	function get_the_ID(): int {
		return 42;
	}

	function is_admin(): bool {
		return false;
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $text ): string {
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

	function cr_rsvp_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_rsvp_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_rsvp_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_rsvp_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_rsvp_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_rsvp_failures'][] = $message;
		}
	}

	function cr_rsvp_same( $expected, $actual, string $message ): void {
		cr_rsvp_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_rsvp_contains( string $needle, string $haystack, string $message ): void {
		cr_rsvp_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_rsvp_not_contains( string $needle, string $haystack, string $message ): void {
		cr_rsvp_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_rsvp_class_specificity( string $selector ): int {
		return substr_count( $selector, '.' ) + substr_count( $selector, ':nth-child' );
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
		return false;
	}
}

namespace {
	require CR_RSVP_THEME_DIR . '/inc/stamps.php';
	require CR_RSVP_THEME_DIR . '/inc/rsvp.php';

	cr_rsvp_check(
		'the style registration and editor sheet target the RSVP entry block',
		static function (): void {
			\Courtneyr\Child\Rsvp\register_block_styles();
			cr_rsvp_same( 'post-kinds-indieweb/rsvp-entry', $GLOBALS['cr_rsvp_styles'][0][0] ?? '', 'block style target' );
			cr_rsvp_same( 'cr-rsvp-wall', $GLOBALS['cr_rsvp_styles'][0][1]['name'] ?? '', 'block style slug' );
			cr_rsvp_same( 'courtneyr-rsvp', $GLOBALS['cr_rsvp_enqueued'][0][1]['handle'] ?? '', 'editor handle' );
			cr_rsvp_assert( ! array_key_exists( 'path', $GLOBALS['cr_rsvp_enqueued'][0][1] ?? array() ), 'block stylesheet has no path' );
			cr_rsvp_same( array( 'courtneyr-paper', 'courtneyr-nav' ), $GLOBALS['cr_rsvp_enqueued'][0][1]['deps'] ?? array(), 'unused ticket dependency stays out' );
			cr_rsvp_contains( '/assets/css/cr-nav.css', $GLOBALS['cr_rsvp_registered_styles']['courtneyr-nav']['src'] ?? '', 'nav dependency registers before RSVP style' );
			cr_rsvp_contains( '/assets/css/cr-nav.css', $GLOBALS['cr_rsvp_registered_styles']['courtneyr-nav']['path'] ?? '', 'nav dependency carries an inline path' );
		}
	);

	cr_rsvp_check(
		'entry paint uses seeded paper, protects locked entries and varies adjacent poster tilts',
		static function (): void {
			$previous = '';
			foreach ( range( 1, 50 ) as $id ) {
				$paper = \Courtneyr\Child\Rsvp\paper( $id );
				cr_rsvp_assert( in_array( $paper, \Courtneyr\Child\Rsvp\PAPERS, true ), 'paper belongs to PAPERS for ' . $id );
				cr_rsvp_same( \Courtneyr\Child\Stamps\seeded_paper( $id, \Courtneyr\Child\Rsvp\PAPERS ), $paper, 'paper matches seeded_paper for ' . $id );
				$html = \Courtneyr\Child\Rsvp\entry_paint(
					'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--upcoming h-entry">Upcoming</article>',
					array( 'attrs' => array( 'className' => 'is-style-cr-rsvp-wall' ) ),
					new WP_Block( array( 'postId' => $id ) )
				);
				$tilt = '';
				foreach ( range( 0, 3 ) as $candidate ) {
					if ( str_contains( $html, 'cr-rsvp-tilt-' . $candidate ) ) {
						$tilt = (string) $candidate;
					}
				}
				cr_rsvp_assert( '' !== $tilt, 'entry_paint adds a tilt for ' . $id );
				cr_rsvp_assert( $previous !== $tilt, 'entry_paint changes adjacent tilt for ' . $id );
				$previous = $tilt;
			}

			$html = \Courtneyr\Child\Rsvp\entry_paint(
				'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past h-entry">Past</article>',
				array( 'attrs' => array( 'className' => 'is-style-cr-rsvp-wall' ) ),
				new WP_Block( array( 'postId' => 42 ) )
			);
			cr_rsvp_contains( '--cr-rsvp-paper:var(--cr-' . \Courtneyr\Child\Stamps\seeded_paper( 42, \Courtneyr\Child\Rsvp\PAPERS ) . ')', $html, 'paper custom property' );

			$protected = \Courtneyr\Child\Rsvp\entry_paint(
				'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past pkiw-rsvp-entry--protected h-entry">Locked</article>',
				array( 'attrs' => array( 'className' => 'is-style-cr-rsvp-wall' ) ),
				new WP_Block( array( 'postId' => 43 ) )
			);
			cr_rsvp_contains( '--cr-rsvp-paper:var(--cr-light-gray)', $protected, 'protected paper is always light gray' );
			cr_rsvp_not_contains( 'cr-rsvp-tilt-', $protected, 'protected entry has no tilt' );
		}
	);

	cr_rsvp_check(
		'entry paint keeps each status sticker distinct from its seeded paper',
		static function (): void {
			$conflicts = array(
				array( 11, 'no', 'periwinkle' ),
				array( 11, 'maybe', 'periwinkle' ),
				array( 2, 'interested', 'blue-green' ),
				array( 10, 'remote', 'ut-orange' ),
			);
			foreach ( $conflicts as $conflict ) {
				list( $id, $status, $sticker_paper ) = $conflict;
				cr_rsvp_assert( $sticker_paper !== \Courtneyr\Child\Rsvp\paper( $id, $status ), $status . ' sticker differs from paper for ' . $id );
			}

			$remote = \Courtneyr\Child\Rsvp\entry_paint(
				'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past h-entry" data-pkiw-rsvp-status="remote">Remote</article>',
				array( 'attrs' => array( 'className' => 'is-style-cr-rsvp-wall' ) ),
				new WP_Block( array( 'postId' => 10 ) )
			);
			cr_rsvp_not_contains( '--cr-rsvp-paper:var(--cr-ut-orange)', $remote, 'entry paint avoids the remote sticker paper' );
		}
	);

	cr_rsvp_check(
		'template and pattern keep the query, fallback, pager and no-results contracts',
		static function (): void {
			$template = (string) file_get_contents( CR_RSVP_THEME_DIR . '/templates/taxonomy-kind-rsvp.html' );
			$parser = new WP_Block_Parser();
			$template_blocks = $parser->parse( $template );
			cr_rsvp_same( 'core/group', $template_blocks[0]['blockName'] ?? null, 'template starts with the archive header group' );
			cr_rsvp_contains( '"metadata":{"name":"RSVP archive"}', $template, 'template metadata' );
			cr_rsvp_contains( 'cr-archive cr-archive--stream cr-archive--rsvp', $template, 'template class' );
			cr_rsvp_contains( 'courtneyr-child/cr-rsvp-wall-loop', $template, 'template pattern' );
			cr_rsvp_contains( 'courtneyr-child/cr-browse-all', $template, 'browse pattern' );

			ob_start();
			require CR_RSVP_THEME_DIR . '/patterns/cr-rsvp-wall-loop.php';
			$registered = (string) ob_get_clean();
			$registered_blocks = $parser->parse( $registered );
			cr_rsvp_same( 'core/query', $registered_blocks[0]['blockName'] ?? null, 'registered pattern starts with Query' );
			cr_rsvp_contains( '"queryId":251', $registered, 'query id' );
			cr_rsvp_contains( '"perPage":9', $registered, 'page size' );
			cr_rsvp_contains( '"align":"wide"', $registered, 'wide alignment belongs to the Query' );
			cr_rsvp_contains( 'wp-block-query alignwide cr-rsvp-wall__query', $registered, 'Query wrapper carries alignwide' );
			cr_rsvp_contains( 'post-kinds-indieweb/rsvp-entry', $registered, 'entry block' );
			cr_rsvp_contains( 'is-style-cr-rsvp-wall', $registered, 'entry style' );
			cr_rsvp_contains( 'wp:query-pagination', $registered, 'pager' );
			cr_rsvp_contains( 'wp:query-no-results', $registered, 'no results' );

			WP_Block_Type_Registry::get_instance()->registered = false;
			ob_start();
			require CR_RSVP_THEME_DIR . '/patterns/cr-rsvp-wall-loop.php';
			$fallback = (string) ob_get_clean();
			$fallback_blocks = $parser->parse( $fallback );
			cr_rsvp_same( 'core/pattern', $fallback_blocks[0]['blockName'] ?? null, 'fallback parses as a pattern block' );
			cr_rsvp_contains( 'courtneyr-child/cr-archive-stream-loop', $fallback, 'fallback pattern' );
		}
	);

	cr_rsvp_check(
		'CSS source holds the cascade, contrast, shadow and focus contracts',
		static function (): void {
			$css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-rsvp.css' );
			$shared_css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-post-kinds.css' );
			$reset = '.cr-stream-page .pk-card';
			$reset_tilt = '.cr-stream-page .wp-block-post-template > li:nth-child(4n+1) .pk-card';
			$stub = '.pk-card.pk-card--stream.cr-rsvp-stub';
			$stub_tilt = '.cr-stream-page .pk-card.pk-card--stream.cr-rsvp-stub.cr-rsvp-tilt-0';
			cr_rsvp_contains( $reset . ' {', $shared_css, 'shared Stream reset remains the cascade opponent' );
			cr_rsvp_contains( $reset_tilt, $shared_css, 'shared Stream tilt remains the cascade opponent' );
			cr_rsvp_assert( cr_rsvp_class_specificity( $stub ) > cr_rsvp_class_specificity( $reset ), 'stub base specificity outranks the shared reset' );
			cr_rsvp_assert( cr_rsvp_class_specificity( $stub_tilt ) > cr_rsvp_class_specificity( $reset_tilt ), 'stub tilt specificity outranks the shared Stream tilt' );
			cr_rsvp_contains( '.pk-card.pk-card--stream.cr-rsvp-stub {', $css, 'stub base outranks the shared Stream reset' );
			cr_rsvp_contains( $stub_tilt . ' {', $css, 'stub tilt selector outranks the shared Stream tilt' );
			cr_rsvp_not_contains( "\n.cr-rsvp-stub {", $css, 'no low-specificity stub base remains' );
			cr_rsvp_contains( 'li:has(> .pk-card.pk-card--stream.cr-rsvp-stub)', $css, 'unclipped list item owns the drop shadow' );
			cr_rsvp_contains( '.cr-archive--rsvp .cr-archive__header {', $css, 'RSVP header gets a scoped lockup' );
			cr_rsvp_contains( '--cr-cutout-max: 5.75rem;', $css, 'RSVP title enlarges through the cutout variable' );
			cr_rsvp_contains( '.cr-archive--rsvp .cr-archive__description {', $css, 'RSVP description gets the accent-heading role' );
			cr_rsvp_contains( ':root:not([data-theme="light"]) .cr-archive--rsvp .cr-archive__description {', $css, 'system dark mode keeps the RSVP description legible' );
			cr_rsvp_contains( ':root[data-theme="dark"] .cr-archive--rsvp .cr-archive__description {', $css, 'manual dark mode keeps the RSVP description legible' );
			cr_rsvp_contains( '.cr-archive--rsvp .cr-archive__header hr.is-style-cr-marker-bar-short {', $css, 'RSVP marker keeps the short component width' );
			cr_rsvp_contains( '.cr-archive--rsvp .cr-rsvp-wall__query {', $css, 'RSVP Query owns the wide wall width' );
			cr_rsvp_contains( 'grid-template-rows: minmax(0, 1fr) auto minmax(0, 1fr);', $css, 'upcoming poster distributes its three text levels' );
			cr_rsvp_contains( 'font-size: var(--cr-text-3xl);', $css, 'upcoming title uses the larger accent step' );
			cr_rsvp_contains( ".cr-rsvp-wall .wp-block-post::after {\n\tcontent: none", $css, 'RSVP cards suppress shared archive marker bars' );
			cr_rsvp_contains( ".cr-rsvp-wall .pkiw-rsvp-entry__image {\n\tdisplay: none;", $css, 'archive poster images stay hidden' );
			cr_rsvp_not_contains( '.pkiw-rsvp-entry__poster::after', $css, 'poster has no image-cover band' );
			cr_rsvp_contains( "[data-pkiw-group=\"past\"] ul.pkiw-rsvp-section__items {\n\talign-items: stretch;", $css, 'past rows stretch every list item' );
			cr_rsvp_contains( ".cr-rsvp-wall .pkiw-rsvp-entry--past {\n\tblock-size: 100%;", $css, 'past stubs fill the stretched row' );
			cr_rsvp_contains( 'overflow-wrap: normal;', $css, 'past titles keep words intact' );
			cr_rsvp_contains( 'hyphens: manual;', $css, 'past titles do not add automatic hyphens' );
			cr_rsvp_not_contains( '-webkit-line-clamp', $css, 'past titles are not clamped' );
			cr_rsvp_not_contains( 'clip-path: polygon(1% 0, 99% 1%, 100% 98%, 98% 100%, 1% 99%, 0 2%);', $css, 'poster shadow is not clipped' );
			cr_rsvp_same( 1, substr_count( $css, 'filter: drop-shadow(' ), 'only the unclipped list item owns a drop shadow' );
			cr_rsvp_not_contains( ".pkiw-rsvp-entry__name {\n\tposition: relative;", $css, 'heading does not become the focus overlay containing block' );
			cr_rsvp_contains( 'inset: 0;', $css, 'focus overlay covers the full positioned card' );
			cr_rsvp_contains( 'border: 1px solid var(--cr-russian-violet);', $css, 'status sticker keeps an edge on matching paper' );
			cr_rsvp_contains( '.cr-stamp text', $css, 'dark stamp ink is overridden at the painted SVG nodes' );
			cr_rsvp_contains( '@media (prefers-reduced-motion: no-preference)', $css, 'hover motion is opt-in when motion is allowed' );
			cr_rsvp_contains( 'background: Canvas;', $css, 'forced-color status gets system paper' );
			cr_rsvp_contains( 'color: CanvasText;', $css, 'forced-color status keeps system text' );
			cr_rsvp_contains( 'border: 1px solid CanvasText;', $css, 'forced-color status keeps its edge' );
			cr_rsvp_not_contains( ".pkiw-rsvp-entry--past::after,\n\t.cr-rsvp-wall .pkiw-rsvp-entry__status,", $css, 'forced colors do not hide status text' );
		}
	);

	cr_rsvp_check(
		'RUCSS and HTML-processing guardrails are explicit',
		static function (): void {
			cr_rsvp_same( array( 'existing.css', 'cr-rsvp.css' ), \Courtneyr\Child\Rsvp\rucss_exclusions( array( 'existing.css' ) ), 'RUCSS exclusion' );
			$pruning = (string) file_get_contents( CR_RSVP_THEME_DIR . '/inc/stream-style-pruning.php' );
			cr_rsvp_contains( "'courtneyr-rsvp'", $pruning, 'inline handle' );
			foreach ( array( 'rsvp.php', 'single-rsvp.php', 'stream-rsvp.php' ) as $file ) {
				cr_rsvp_assert( ! str_contains( (string) file_get_contents( CR_RSVP_THEME_DIR . '/inc/' . $file ), 'preg_' ), $file . ' adds no preg_' );
			}
			$css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-rsvp.css' );
			cr_rsvp_assert( strlen( $css ) <= 30 * 1024, 'cr-rsvp.css stays within 30 KB' );

			$seed_path = (string) getenv( 'CR_RSVP_SEED' );
			if ( '' !== $seed_path ) {
				$seed = (string) file_get_contents( $seed_path );
				cr_rsvp_contains( 'wp eval-file seed-251.php seed set=archive', $seed, 'seed usage uses WP-CLI positional tokens' );
				cr_rsvp_contains( "'set_object_terms'", $seed, 'backfill unhooks term callbacks' );
				cr_rsvp_contains( 'delete_post_meta( $id, \'_pingme\' );', $seed, 'seed removes deferred ping markers' );
				cr_rsvp_contains( '$protected ? $post->post_date_gmt', $seed, 'expect files protected posts by post date' );
				cr_rsvp_contains( 'Reused ', $seed, 'seed reuses marked fixtures on a rerun' );
				cr_rsvp_contains( 'function seed251_assert_backfill_shape', $seed, 'new and reused backfill fixtures share one shape assertion' );
				cr_rsvp_assert( 3 <= substr_count( $seed, 'seed251_assert_backfill_shape(' ), 'backfill shape assertion covers its definition, new fixtures and reused fixtures' );
			}
		}
	);

	if ( $GLOBALS['cr_rsvp_failures'] ) {
		foreach ( $GLOBALS['cr_rsvp_failures'] as $failure ) {
			fwrite( STDERR, '  ' . $failure . "\n" );
		}
		exit( 1 );
	}
	echo "all passed\n";
}
