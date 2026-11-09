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

	$GLOBALS['cr_rsvp_hooks']    = array();
	$GLOBALS['cr_rsvp_styles']   = array();
	$GLOBALS['cr_rsvp_enqueued'] = array();
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

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		echo "SKIP: set CR_WP_CORE to a WordPress checkout\n";
		exit( 0 );
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
		}
	);

	cr_rsvp_check(
		'entry paint uses seeded paper and never repeats adjacent poster tilts',
		static function (): void {
			foreach ( range( 1, 50 ) as $run ) {
				$id = abs( crc32( 'rsvp-tilt-' . $run ) );
				$paper = \Courtneyr\Child\Rsvp\paper( $id );
				cr_rsvp_assert( in_array( $paper, \Courtneyr\Child\Rsvp\PAPERS, true ), 'paper belongs to PAPERS for ' . $id );
				cr_rsvp_same( \Courtneyr\Child\Stamps\seeded_paper( $id, \Courtneyr\Child\Rsvp\PAPERS ), $paper, 'paper matches seeded_paper for ' . $id );
				$first  = \Courtneyr\Child\Rsvp\poster_tilt( $id );
				$second = \Courtneyr\Child\Rsvp\poster_tilt( $id, $first );
				cr_rsvp_assert( $first !== $second, 'adjacent poster tilt differs for ' . $id );
			}

			$html = \Courtneyr\Child\Rsvp\entry_paint(
				'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past h-entry">Past</article>',
				array( 'attrs' => array( 'className' => 'is-style-cr-rsvp-wall' ) ),
				new WP_Block( array( 'postId' => 42 ) )
			);
			cr_rsvp_contains( '--cr-rsvp-paper:var(--cr-' . \Courtneyr\Child\Stamps\seeded_paper( 42, \Courtneyr\Child\Rsvp\PAPERS ) . ')', $html, 'paper custom property' );
		}
	);

	cr_rsvp_check(
		'template and pattern keep the query, fallback, pager and no-results contracts',
		static function (): void {
			$template = (string) file_get_contents( CR_RSVP_THEME_DIR . '/templates/taxonomy-kind-rsvp.html' );
			$parser = new WP_Block_Parser();
			cr_rsvp_assert( array() !== $parser->parse( $template ), 'template parses into blocks' );
			cr_rsvp_contains( '"metadata":{"name":"RSVP archive"}', $template, 'template metadata' );
			cr_rsvp_contains( 'cr-archive cr-archive--stream cr-archive--rsvp', $template, 'template class' );
			cr_rsvp_contains( 'courtneyr-child/cr-rsvp-wall-loop', $template, 'template pattern' );
			cr_rsvp_contains( 'courtneyr-child/cr-browse-all', $template, 'browse pattern' );

			ob_start();
			require CR_RSVP_THEME_DIR . '/patterns/cr-rsvp-wall-loop.php';
			$registered = (string) ob_get_clean();
			cr_rsvp_assert( array() !== $parser->parse( $registered ), 'registered pattern parses into blocks' );
			cr_rsvp_contains( '"queryId":251', $registered, 'query id' );
			cr_rsvp_contains( '"perPage":9', $registered, 'page size' );
			cr_rsvp_contains( 'post-kinds-indieweb/rsvp-entry', $registered, 'entry block' );
			cr_rsvp_contains( 'is-style-cr-rsvp-wall', $registered, 'entry style' );
			cr_rsvp_contains( 'wp:query-pagination', $registered, 'pager' );
			cr_rsvp_contains( 'wp:query-no-results', $registered, 'no results' );

			WP_Block_Type_Registry::get_instance()->registered = false;
			ob_start();
			require CR_RSVP_THEME_DIR . '/patterns/cr-rsvp-wall-loop.php';
			$fallback = (string) ob_get_clean();
			cr_rsvp_contains( 'courtneyr-child/cr-archive-stream-loop', $fallback, 'fallback pattern' );
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
