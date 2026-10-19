<?php
/**
 * Standalone checks for audio stylesheet gates and archive block markup.
 *
 * Run with `php tests/php/audio-enqueue-test.php`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_AUDIO_ENQUEUE_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_audio_enqueue_hooks']   = array();
	$GLOBALS['cr_audio_enqueue_request'] = array();
	$GLOBALS['cr_audio_enqueue_styles']  = array();

	/** Record an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_audio_enqueue_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Record an enqueued stylesheet. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_audio_enqueue_styles'][ (string) $handle ] = array(
			'src'     => (string) $src,
			'deps'    => (array) $deps,
			'version' => $version,
		);
	}

	/** Block style registration is tested by the parity script. */
	function register_block_style( $block_name, $args ): void {}

	/** Translate text unchanged. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Print escaped translated text. */
	function esc_html_e( $text, $domain = null ): void {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Encode a block-comment value. */
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	/** Current taxonomy request. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_audio_enqueue_request']['kind'] ?? '' );
		return 'kind' === $taxonomy && '' !== $current && ( '' === $term || $current === $term );
	}

	/** Current singular request. */
	function is_singular( $post_type = '' ): bool {
		return '' !== ( $GLOBALS['cr_audio_enqueue_request']['single_kind'] ?? '' ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Current single kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		return 'kind' === $taxonomy && in_array( $GLOBALS['cr_audio_enqueue_request']['single_kind'] ?? '', (array) $term, true );
	}

	/** Fixed queried object ID. */
	function get_queried_object_id(): int {
		return 42;
	}

	/** Current admin request. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_audio_enqueue_request']['admin'] );
	}

	class WP_Block_Type_Registry {
		/** Return the registry stand-in. */
		public static function get_instance(): self {
			return new self();
		}

		/** Both plugin blocks exist in the pattern fixture. */
		public function is_registered( string $name ): bool {
			return in_array( $name, array( 'post-kinds-indieweb/archive-sections', 'post-kinds-indieweb/stream-card' ), true );
		}
	}

	require CR_AUDIO_ENQUEUE_THEME_DIR . '/inc/audio.php';
}

namespace Courtneyr\Child\AudioEnqueueTests {

	$failures = array();

	/** Record one assertion. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Fire one action in priority order. */
	function fire( string $hook ): void {
		$priorities = $GLOBALS['cr_audio_enqueue_hooks'][ $hook ] ?? array();
		ksort( $priorities, SORT_NUMERIC );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callback[0]();
			}
		}
	}

	/** Enqueued styles for one request. */
	function styles_for( array $request ): array {
		$GLOBALS['cr_audio_enqueue_request'] = $request;
		$GLOBALS['cr_audio_enqueue_styles']  = array();
		fire( 'enqueue_block_assets' );
		return $GLOBALS['cr_audio_enqueue_styles'];
	}

	/** Render the PHP pattern with both plugin blocks registered. */
	function render_pattern( string $path ): string {
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/** Verify every parsed node is either a block or whitespace. */
	function valid_blocks( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				if ( '' !== trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
					return false;
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) && ! valid_blocks( $block['innerBlocks'] ) ) {
				return false;
			}
		}
		return true;
	}

	$wanted = array(
		'audio archive' => array( 'kind' => 'audio' ),
		'audio single'  => array( 'single_kind' => 'audio' ),
		'admin canvas'  => array( 'admin' => true ),
	);
	foreach ( $wanted as $label => $request ) {
		$styles = styles_for( $request );
		check( isset( $styles['courtneyr-audio'] ), $label . ' enqueues courtneyr-audio' );
		check( COURTNEYR_CHILD_URI . '/assets/css/cr-audio.css' === ( $styles['courtneyr-audio']['src'] ?? '' ), $label . ' uses the cr-audio.css source string' );
		check( array() === ( $styles['courtneyr-audio']['deps'] ?? null ), $label . ' declares no stylesheet dependencies' );
		check( COURTNEYR_CHILD_VERSION === ( $styles['courtneyr-audio']['version'] ?? null ), $label . ' uses the theme version' );
	}

	$unwanted = array(
		'other kind archive' => array( 'kind' => 'note' ),
		'other kind single'  => array( 'single_kind' => 'note' ),
		'home'               => array( 'home' => true ),
		'search'             => array( 'search' => true ),
		'Stream'             => array( 'stream' => true ),
	);
	foreach ( $unwanted as $label => $request ) {
		check( array() === styles_for( $request ), $label . ' does not enqueue cr-audio.css' );
	}

	$template_path = CR_AUDIO_ENQUEUE_THEME_DIR . '/templates/taxonomy-kind-audio.html';
	$pattern_path  = CR_AUDIO_ENQUEUE_THEME_DIR . '/patterns/cr-audio-log-loop.php';
	$template      = is_file( $template_path ) ? (string) file_get_contents( $template_path ) : '';
	$pattern       = is_file( $pattern_path ) ? render_pattern( $pattern_path ) : '';
	check( '' !== $template, 'the audio taxonomy template exists' );
	check( '' !== $pattern, 'the audio log pattern exists' );

	$core   = getenv( 'WP_CORE_DIR' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$parser = $core . '/wp-includes/class-wp-block-parser.php';
	if ( is_file( $parser ) ) {
		foreach ( array( 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php' ) as $file ) {
			require_once $core . '/wp-includes/' . $file;
		}
		check( valid_blocks( ( new \WP_Block_Parser() )->parse( $template ) ), 'the real block parser accepts the audio template' );
		check( valid_blocks( ( new \WP_Block_Parser() )->parse( $pattern ) ), 'the real block parser accepts the audio pattern' );
	} else {
		check( false, 'the WordPress block parser is available' );
	}

	check( 1 === substr_count( $pattern, '<!-- wp:query ' ), 'the pattern contains exactly one core/query block' );
	check( false !== strpos( $pattern, '"queryId":243' ) && false !== strpos( $pattern, '"perPage":12' ) && false !== strpos( $pattern, '"inherit":true' ), 'the query inherits with ID 243 and 12 posts per page' );
	check( false === (bool) preg_match( '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\b/', $template . $pattern ), 'the template and pattern contain no hard-coded month text' );
	check( false !== strpos( $template, '"metadata":{"name":"Audio archive"}' ) && false !== strpos( $template, 'cr-archive cr-archive--stream cr-archive--audio' ), 'the template identifies the audio archive shell' );
	check( 1 === substr_count( $template, 'courtneyr/archive-identity' ) && 1 === substr_count( $template, 'wp:query-title' ) && 1 === substr_count( $template, 'wp:term-description' ), 'the template keeps the unchanged archive header blocks' );
	check( false !== strpos( $template, 'courtneyr-child/cr-audio-log-loop' ) && false !== strpos( $template, 'courtneyr-child/cr-browse-all' ), 'the template places the audio loop and browse-all patterns' );
	check( false !== strpos( $pattern, 'cr-audio-log' ) && false !== strpos( $pattern, 'cr-audio-log-card' ), 'the pattern carries the audio log classes' );
	check( false !== strpos( $pattern, 'post-kinds-indieweb/archive-sections {"linesPerPage":12,"emptyLabel":"","headingLevel":2}' ), 'archive-sections receives the required marker values' );
	check( false !== strpos( $pattern, 'post-kinds-indieweb/stream-card {"headingLevel":3,"className":"cr-audio-log-card"}' ), 'the stream card receives heading level 3 and the audio card class' );
	$post_close = strpos( $pattern, '<!-- /wp:post-template -->' );
	$pager_open = strpos( $pattern, '<!-- wp:query-pagination ' );
	$query_end  = strrpos( $pattern, '<!-- /wp:query -->' );
	check( false !== $post_close && false !== $pager_open && false !== $query_end && $pager_open > $post_close && $pager_open < $query_end, 'pagination follows the post template, outside it and inside the query' );
	check( false === strpos( $pattern, 'aria-label' ), 'the pattern leaves the Pagination label to Core' );

	$enqueue_source = (string) file_get_contents( CR_AUDIO_ENQUEUE_THEME_DIR . '/inc/enqueue.php' );
	check( false !== strpos( $enqueue_source, "'cr-audio.css'" ), 'the Remove Unused CSS list contains cr-audio.css' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
