<?php
/**
 * `php tests/php/question-test.php`: question presentation contracts for issue 256.
 *
 * Needs the HTML API from a WordPress checkout: CR_WP_CORE, or
 * ~/.wp-tests/wordpress. Exits 2 when it cannot load it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );

	class WP_Block {
		/** @var array<string, mixed> */
		public $context;

		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	$GLOBALS['cr_hooks']     = array();
	$GLOBALS['cr_kind']      = array();
	$GLOBALS['cr_password']  = array();
	$GLOBALS['cr_request']   = array();
	$GLOBALS['cr_styles']    = array();
	$GLOBALS['cr_blockstyle'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function __( $text, $domain = '' ) {
		return $text;
	}

	function register_block_style( $block, $properties ) {
		$GLOBALS['cr_blockstyle'][ $block ] = $properties;
		return true;
	}

	function is_tax( $taxonomy = '', $term = '' ) {
		return 'kind' === $taxonomy && ( $GLOBALS['cr_request']['archive'] ?? '' ) === $term;
	}

	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['post_id'] ?? 0 );
	}

	function get_the_ID() {
		return (int) ( $GLOBALS['cr_request']['post_id'] ?? 0 );
	}

	function has_term( $term, $taxonomy, $post ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return 'kind' === $taxonomy && ( $GLOBALS['cr_kind'][ $id ] ?? '' ) === $term;
	}

	function post_password_required( $post = null ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_password'][ $id ] );
	}

	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	function wp_enqueue_style( $handle, $src = '', $dependencies = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $dependencies, $version );
	}

	function load_html_api(): bool {
		$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
		$api  = $core . '/wp-includes/html-api';
		if ( ! is_dir( $api ) ) {
			return false;
		}
		require_once $core . '/wp-includes/compat.php';
		require_once $core . '/wp-includes/utf8.php';
		require_once $core . '/wp-includes/kses.php';
		foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
			require_once $api . '/' . $file;
		}
		return class_exists( '\\WP_HTML_Tag_Processor' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_request']['stream'] );
	}
}

namespace Courtneyr\Child\QuestionTests {
	if ( ! \load_html_api() ) {
		echo "FAIL the HTML API could not load: set CR_WP_CORE to a WordPress checkout\n";
		exit( 2 );
	}

	$root = dirname( __DIR__, 2 );
	require_once $root . '/inc/stamps.php';
	require_once $root . '/inc/question.php';
	require_once $root . '/inc/stream-question.php';

	$failed = 0;

	function check( bool $ok, string $message ): void {
		global $failed;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $message . "\n";
		if ( ! $ok ) {
			++$failed;
		}
	}

	/** Whether one callback is registered at the frozen hook contract. */
	function hooked( string $hook, string $callback, int $priority, int $accepted_args ): bool {
		foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] ?? array() as $registered ) {
			if ( array( $callback, $accepted_args ) === $registered ) {
				return true;
			}
		}
		return false;
	}

	/** Strip class values so a mutation comparison covers every other byte. */
	function without_classes( string $html ): string {
		return (string) preg_replace( '/\sclass="[^"]*"/', '', $html );
	}

	/** The paper modifier on decorated markup, or the base-paper name. */
	function paper_from_html( string $html ): string {
		if ( preg_match( '/\bcr-paper--(printer-ivory|light-orange|periwinkle|sky-blue)\b/', $html, $found ) ) {
			return $found[1];
		}
		return 'light-gray';
	}

	check( hooked( 'render_block_post-kinds-indieweb/question-entry', 'Courtneyr\Child\Question\paint_entry', 10, 3 ), 'question entry filter uses priority 10 and three arguments' );
	check( hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\Child\StreamQuestion\paint_stream', 10, 3 ), 'Stream filter uses priority 10 and three arguments' );
	check( hooked( 'enqueue_block_assets', 'Courtneyr\Child\Question\enqueue_styles', 10, 1 ), 'question stylesheet hooks enqueue_block_assets' );
	check( hooked( 'init', 'Courtneyr\Child\Question\register_salutation_style', 10, 1 ), 'salutation style hooks init' );

	$GLOBALS['cr_kind'][101] = 'question';
	$GLOBALS['cr_kind'][102] = 'question';
	$entry = '<div class="pkiw-question-entry"><h3 class="pkiw-question-entry__title p-name"><a class="u-url" href="https://example.test/q/101">How should this work?</a></h3><data class="dt-published" value="2026-10-18" hidden></data><span hidden><span class="p-author h-card">Courtney</span></span></div>';
	$out   = \Courtneyr\Child\Question\paint_entry( $entry, array(), new \WP_Block( array( 'postId' => 101 ) ) );
	$out2  = \Courtneyr\Child\Question\paint_entry( $entry, array(), new \WP_Block( array( 'postId' => 101 ) ) );
	$other = \Courtneyr\Child\Question\paint_entry( $entry, array(), new \WP_Block( array( 'postId' => 102 ) ) );

	check( false !== strpos( $out, 'cr-question-slip' ) && false !== strpos( $out, 'cr-paper-sheet' ) && false !== strpos( $out, 'cr-paper-sheet--torn' ), 'archive entry receives the three exact slip classes' );
	check( paper_from_html( $out ) === paper_from_html( $out2 ), 'the same post ID receives a stable paper' );
	check( paper_from_html( $other ) === \Courtneyr\Child\Stamps\seeded_paper( 102, \Courtneyr\Child\Question\PAPERS ), 'a second post ID receives its deterministic paper' );
	check( substr_count( $entry, '<a ' ) === substr_count( $out, '<a ' ), 'archive painting preserves link count' );
	check( substr_count( $entry, ' hidden' ) === substr_count( $out, ' hidden' ), 'archive painting preserves hidden properties' );
	check( strip_tags( $entry ) === strip_tags( $out ), 'archive painting preserves rendered text' );
	check( without_classes( $entry ) === without_classes( $out ), 'archive painting changes only class values' );

	$continued = '<div class="pkiw-question-entry pkiw-question-entry--continued" hidden>Continued</div>';
	$plain     = '<div class="other-entry">Other</div>';
	check( $continued === \Courtneyr\Child\Question\paint_entry( $continued, array(), new \WP_Block( array( 'postId' => 101 ) ) ), 'continuation placeholder is byte-identical' );
	check( $plain === \Courtneyr\Child\Question\paint_entry( $plain, array(), new \WP_Block( array( 'postId' => 101 ) ) ), 'markup without an entry div is byte-identical' );
	check( '' === \Courtneyr\Child\Question\paint_entry( '', array(), null ), 'empty entry markup is byte-identical' );

	$paper_css = file_get_contents( $root . '/assets/css/cr-paper.css' );
	$seen      = array();
	for ( $id = 1; $id <= 300; $id++ ) {
		$paper          = \Courtneyr\Child\Stamps\seeded_paper( $id, \Courtneyr\Child\Question\PAPERS );
		$seen[ $paper ] = \Courtneyr\Child\Question\paper_class( $id );
	}
	foreach ( \Courtneyr\Child\Question\PAPERS as $paper ) {
		$expected = 'light-gray' === $paper ? '' : 'cr-paper--' . $paper;
		check( isset( $seen[ $paper ] ) && $expected === $seen[ $paper ], "$paper resolves to its expected modifier" );
		if ( '' !== $expected ) {
			check( false !== strpos( (string) $paper_css, '.' . $expected ), "$expected exists in cr-paper.css" );
		}
	}

	$stream = '<article class="pk-card pk-card--stream k-question h-entry"><div class="pk-body"><span class="pk-kindlabel">Question</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/q/101">How should this work?</a></h2><p class="pk-sub pk-stream-date"><time class="dt-published">Oct 18</time></p><p class="pk-sub pk-answer-count">2 answers</p></div><p class="pk-excerpt p-summary">A short question.</p></div></article>';
	$GLOBALS['cr_request'] = array( 'stream' => true );
	$stream_out            = \Courtneyr\Child\StreamQuestion\paint_stream( $stream, array(), new \WP_Block( array( 'postId' => 101 ) ) );
	check( false !== strpos( $stream_out, 'cr-question-slip' ), 'question Stream card receives slip classes on a Stream surface' );
	check( paper_from_html( $stream_out ) === paper_from_html( $out ), 'archive and Stream use the same paper for the same post ID' );
	check( 1 === preg_match( '/\bcr-question-tilt-[0-3]\b/', $stream_out ), 'Stream card receives one allowed tilt class' );
	check( strip_tags( $stream ) === strip_tags( $stream_out ), 'Stream painting preserves rendered text' );
	check( without_classes( $stream ) === without_classes( $stream_out ), 'Stream painting changes only class values' );

	$GLOBALS['cr_request'] = array();
	check( $stream === \Courtneyr\Child\StreamQuestion\paint_stream( $stream, array(), new \WP_Block( array( 'postId' => 101 ) ) ), 'question card outside Stream is byte-identical' );
	$GLOBALS['cr_request'] = array( 'stream' => true );
	$GLOBALS['cr_kind'][103] = 'note';
	check( $stream === \Courtneyr\Child\StreamQuestion\paint_stream( $stream, array(), new \WP_Block( array( 'postId' => 103 ) ) ), 'non-question post is byte-identical' );
	$protected = str_replace( 'pk-card--stream', 'pk-card--stream pk-card--protected', $stream );
	check( $protected === \Courtneyr\Child\StreamQuestion\paint_stream( $protected, array(), new \WP_Block( array( 'postId' => 101 ) ) ), 'protected card markup is byte-identical' );
	$GLOBALS['cr_password'][101] = true;
	check( $stream === \Courtneyr\Child\StreamQuestion\paint_stream( $stream, array(), new \WP_Block( array( 'postId' => 101 ) ) ), 'password-required question is byte-identical' );
	$GLOBALS['cr_password'][101] = false;

	$template = file_get_contents( $root . '/templates/taxonomy-kind-question.html' );
	$pattern  = file_get_contents( $root . '/patterns/cr-question-slips-loop.php' );
	$question = file_get_contents( $root . '/inc/question.php' );
	$stream_php = file_get_contents( $root . '/inc/stream-question.php' );
	$css      = file_get_contents( $root . '/assets/css/cr-question.css' );

	check( false !== strpos( (string) $template, 'courtneyr-child/cr-question-slips-loop' ) && false === strpos( (string) $template, 'cr-browse-all' ), 'template uses the question pattern and omits browse-all' );
	check( false !== strpos( (string) $pattern, "is_registered( 'post-kinds-indieweb/question-entry' )" ), 'pattern guards the plugin entry block' );
	check( false === strpos( (string) $pattern, 'query-no-results' ) && false === strpos( (string) $pattern, 'templateLock' ), 'pattern has no empty-state block or template lock' );
	// X16 allows one block-attribute read: a className gate.
	$question_no_gate = str_replace( "\$block['attrs']['className']", '', (string) $question );
	foreach ( array( 'attrs', 'get_post_meta', 'parse_blocks', 'untitled_name', 'kind_picture' ) as $forbidden ) {
		check( false === strpos( $question_no_gate, $forbidden ) && false === strpos( (string) $stream_php, $forbidden ), "PHP adapters omit $forbidden" );
	}

	check( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', (string) $css ), 'question CSS contains no hex literal' );
	check( 0 === preg_match( '/\b(?:rgb|rgba|hsl)\(/i', (string) $css ), 'question CSS contains no literal color function' );
	check( false === stripos( (string) $css, 'blur(' ) && 0 === preg_match( '/\bfilter\s*:/i', (string) $css ), 'question CSS contains no blur or filter' );
	check( false === stripos( (string) $css, 'radial-gradient' ), 'question CSS contains no radial gradient' );
	$without_masks = (string) preg_replace( '/(?:-webkit-)?mask\s*:[^;]+;/s', '', (string) $css );
	check( false === stripos( $without_masks, 'linear-gradient' ), 'linear gradients appear only in mask declarations' );
	check( 0 === preg_match( '/font-size\s*:\s*(?:0(?:\.\d+)?|0\.[0-6]\d*)rem\b/', (string) $css ), 'question CSS has no font size below the text floor' );
	check( false !== strpos( (string) $css, '[data-pkiw-group="answered"]' ) && false !== strpos( (string) $css, '[data-pkiw-group="waiting"]' ), 'marker strokes key on both group data values' );
	check( false === strpos( (string) $css, 'Answered' ) && false === strpos( (string) $css, 'Waiting' ), 'marker strokes never key on editable heading text' );
	check( false !== strpos( (string) $css, 'min-width: 48rem' ) && false !== strpos( (string) $css, 'min-width: 64rem' ) && false !== strpos( (string) $css, 'repeat(3, minmax(0, 1fr))' ), 'question CSS contains the responsive column rules' );
	foreach ( range( 0, 3 ) as $tilt ) {
		check( false !== strpos( (string) $css, '.cr-question-tilt-' . $tilt ), "question CSS maps tilt $tilt" );
	}
	check( false !== strpos( (string) $css, ':root[data-theme="dark"]' ) && false !== strpos( (string) $css, ':root:not([data-theme="light"])' ), 'question CSS contains both dark-mode mechanisms' );
	check( false !== strpos( (string) $css, 'forced-colors: active' ), 'question CSS contains forced-colors rules' );
	check( false === strpos( (string) $css, 'transition' ), 'question CSS defines no transition' );

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
