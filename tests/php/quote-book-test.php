<?php
/**
 * `php tests/php/quote-book-test.php`: quote commonplace-book contracts.
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

	/** Post stand-in. */
	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;

		/** Build a post. */
		public function __construct( int $id, string $title, string $content ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
		}
	}

	/** Block instance stand-in. */
	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	/** Block registry stand-in. */
	class WP_Block_Type_Registry {
		/** @var array<string, bool> */
		public array $registered = array();
		private static ?self $instance = null;

		/** Return the shared registry. */
		public static function get_instance(): self {
			self::$instance ??= new self();
			return self::$instance;
		}

		/** Whether a block is registered. */
		public function is_registered( string $name ): bool {
			return ! empty( $this->registered[ $name ] );
		}
	}

	$GLOBALS['cr_qb_hooks']   = array();
	$GLOBALS['cr_qb_posts']   = array();
	$GLOBALS['cr_qb_request'] = array();
	$GLOBALS['cr_qb_styles']  = array();
	$GLOBALS['cr_qb_enqueued'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_qb_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
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
		$GLOBALS['cr_qb_styles'][] = array( $block, $properties );
		return true;
	}

	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_qb_request']['post_id'] ?? 0 );
	}

	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return $GLOBALS['cr_qb_posts'][ $id ] ?? null;
	}

	function post_password_required( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_qb_request']['protected'][ $id ] );
	}

	/** Return one quote block with any saved paper style. */
	function parse_blocks( string $content ): array {
		$class_name = '';
		if ( preg_match( '/is-style-cr-quote-paper-[a-z]+/', $content, $found ) ) {
			$class_name = $found[0];
		}
		return array(
			array(
				'blockName'   => 'core/quote',
				'attrs'       => array( 'className' => $class_name ),
				'innerBlocks' => array(),
				'innerHTML'   => '<blockquote><p>Quote</p></blockquote>',
			),
		);
	}

	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_qb_request']['admin'] );
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && ( $GLOBALS['cr_qb_request']['archive'] ?? '' ) === $term;
	}

	function wp_enqueue_style( $handle, $src = '', $dependencies = array(), $version = false ): void {
		$GLOBALS['cr_qb_enqueued'][ $handle ] = array( $src, $dependencies, $version );
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

namespace Courtneyr\Child\QuoteBookTests {
	if ( ! \load_html_api() ) {
		echo "FAIL the HTML API could not load: set CR_WP_CORE to a WordPress checkout\n";
		exit( 2 );
	}

	$root = dirname( __DIR__, 2 );
	require_once $root . '/inc/stamps.php';
	require_once $root . '/inc/quote-note.php';
	require_once $root . '/inc/quote-book.php';

	use const Courtneyr\Child\QuoteBook\EMPTY_STRIP;
	use const Courtneyr\Child\QuoteBook\STRIPS;
	use function Courtneyr\Child\QuoteBook\enqueue_styles;
	use function Courtneyr\Child\QuoteBook\entry_paper;
	use function Courtneyr\Child\QuoteBook\register_block_styles;
	use function Courtneyr\Child\QuoteBook\rucss_exclusions;
	use function Courtneyr\Child\QuoteBook\strip_seeds;
	use function Courtneyr\Child\QuoteBook\strip_for;

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
		foreach ( $GLOBALS['cr_qb_hooks'][ $hook ][ $priority ] ?? array() as $registered ) {
			if ( array( $callback, $accepted_args ) === $registered ) {
				return true;
			}
		}
		return false;
	}

	/** Return one attribute from the first matching tag. */
	function attribute( string $html, string $class_name, string $attribute ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag( array( 'class_name' => $class_name ) ) ) {
			return '';
		}
		return (string) $tags->get_attribute( $attribute );
	}

	/** Return one custom-property value from inline style. */
	function property( string $html, string $class_name, string $property ): string {
		$style = attribute( $html, $class_name, 'style' );
		return preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . ':([^;]+)/', $style, $found ) ? trim( $found[1] ) : '';
	}

	/** Remove only the two attributes this adapter may change. */
	function without_paint_attributes( string $html ): string {
		return (string) preg_replace( '/\s(?:class|style)="[^"]*"/', '', $html );
	}

	/** Check that static block comments nest and close. */
	function comments_balance( string $markup ): bool {
		preg_match_all( '/<!--\s*(\/?)wp:([^\s]+)(.*?)-->/s', $markup, $comments, PREG_SET_ORDER );
		$stack = array();
		foreach ( $comments as $comment ) {
			$closing = '/' === $comment[1];
			$self    = ! $closing && str_ends_with( trim( $comment[3] ), '/' );
			if ( $closing ) {
				if ( array_pop( $stack ) !== $comment[2] ) {
					return false;
				}
			} elseif ( ! $self ) {
				$stack[] = $comment[2];
			}
		}
		return array() === $stack;
	}

	check( hooked( 'init', 'Courtneyr\\Child\\QuoteBook\\register_block_styles', 20, 1 ), 'commonplace style hooks init at 20 with one argument' );
	check( hooked( 'render_block_post-kinds-indieweb/quote-entry', 'Courtneyr\\Child\\QuoteBook\\entry_paper', 10, 3 ), 'quote entry paper hooks at 10 with three arguments' );
	check( hooked( 'render_block_core/post-template', 'Courtneyr\\Child\\QuoteBook\\strip_seeds', 10, 2 ), 'post-template strip seeds hook at 10 with two arguments' );
	check( hooked( 'render_block_post-kinds-indieweb/quote-works', 'Courtneyr\\Child\\QuoteBook\\strip_seeds', 10, 2 ), 'quote works strip seeds hook at 10 with two arguments' );
	check( hooked( 'enqueue_block_assets', 'Courtneyr\\Child\\QuoteBook\\enqueue_styles', 10, 1 ), 'book stylesheet hooks enqueue_block_assets' );
	check( hooked( 'perfmatters_rucss_excluded_stylesheets', 'Courtneyr\\Child\\QuoteBook\\rucss_exclusions', 20, 1 ), 'RUCSS exclusions hook at 20 with one argument' );

	$registry             = \WP_Block_Type_Registry::get_instance();
	$registry->registered = array();
	$GLOBALS['cr_qb_styles'] = array();
	register_block_styles();
	check( array() === $GLOBALS['cr_qb_styles'], 'commonplace style stays unregistered without the plugin block' );
	$registry->registered['post-kinds-indieweb/quote-entry'] = true;
	register_block_styles();
	check(
		array( 'post-kinds-indieweb/quote-entry', array( 'name' => 'cr-commonplace', 'label' => 'Commonplace book' ) ) === ( $GLOBALS['cr_qb_styles'][0] ?? array() ),
		'commonplace style registers on quote-entry only when the block exists'
	);

	// This lane never edits inc/quote-note.php. The two hashes are 228d851 and main after #202 (role="presentation" on the pin, mark and dash); add the next one when another PR changes the file.
	check(
		in_array(
			hash_file( 'sha256', $root . '/inc/quote-note.php' ),
			array(
				'9c011e174a541bb03a8d9867bd1176b6da0d3bd93afc93a3711b75bb387e320c',
				'3bedadac4f0e566e73a1c2795e9a14fd523ade7945e1e3ddd24fe005b92dfcfb',
			),
			true
		),
		'inc/quote-note.php is byte-identical to a known base (228d851 or main after #202)'
	);

	$picked = new \WP_Post( 101, 'Picked paper', '<!-- wp:quote {"className":"is-style-cr-quote-paper-sky"} --><blockquote><p>Quote</p></blockquote><!-- /wp:quote -->' );
	$seeded = new \WP_Post( 102, 'Seeded paper', '<!-- wp:quote --><blockquote><p>Quote</p></blockquote><!-- /wp:quote -->' );
	$locked = new \WP_Post( 103, 'Locked paper', '<!-- wp:quote {"className":"is-style-cr-quote-paper-sky"} --><blockquote><p>Quote</p></blockquote><!-- /wp:quote -->' );
	$GLOBALS['cr_qb_posts'] = array( 101 => $picked, 102 => $seeded, 103 => $locked );
	$GLOBALS['cr_qb_request']['protected'][103] = true;
	$entry = '<div class="pkiw-quote-entry h-entry" data-proof="kept"><blockquote class="pkiw-quote-entry__text e-content" id="quote-text"><p>Readable quote.</p></blockquote><cite class="pkiw-quote-entry__cite" id="quote-cite">Source</cite><a class="u-url pkiw-quote-entry__link" href="https://example.test/quote" aria-labelledby="quote-text quote-cite"></a><time class="dt-published" hidden></time><span hidden>Author</span></div>';
	$styled_block = array( 'attrs' => array( 'className' => "extra\tis-style-cr-commonplace saved" ) );
	$picked_out = entry_paper( $entry, $styled_block, new \WP_Block( array( 'postId' => 101 ) ) );
	$seeded_out = entry_paper( str_replace( 'data-proof="kept"', 'style="display:block" data-proof="kept"', $entry ), $styled_block, new \WP_Block( array( 'postId' => 102 ) ) );
	$locked_out = entry_paper( $entry, $styled_block, new \WP_Block( array( 'postId' => 103 ) ) );

	check( \Courtneyr\Child\QuoteNote\paper_for( $picked ) === property( $picked_out, 'pkiw-quote-entry', '--cr-quote-paper' ), 'picked quote paper equals QuoteNote paper_for' );
	check( \Courtneyr\Child\QuoteNote\paper_for( $seeded ) === property( $seeded_out, 'pkiw-quote-entry', '--cr-quote-paper' ), 'seeded quote paper equals QuoteNote paper_for' );
	check( 'var(--cr-printer-ivory)' === property( $locked_out, 'pkiw-quote-entry', '--cr-quote-paper' ), 'protected quote uses printer ivory' );
	check( str_contains( attribute( $picked_out, 'pkiw-quote-entry', 'class' ), 'is-style-cr-commonplace' ), 'entry filter prints the saved block style on the card' );
	check( str_contains( attribute( $seeded_out, 'pkiw-quote-entry', 'style' ), 'display:block;--cr-quote-paper:' ), 'entry filter preserves and appends to existing style' );
	check( without_paint_attributes( $entry ) === without_paint_attributes( $picked_out ), 'entry filter changes only class and style attributes' );
	check( substr_count( $entry, '<a ' ) === substr_count( $picked_out, '<a ' ), 'entry filter preserves link count' );
	check( substr_count( $entry, ' hidden' ) === substr_count( $picked_out, ' hidden' ), 'entry filter preserves hidden count' );
	check( strip_tags( $entry ) === strip_tags( $picked_out ), 'entry filter preserves rendered text' );

	$plain_block = array( 'attrs' => array( 'className' => 'is-style-cr-commonplace-extra' ) );
	$continued   = '<div class="pkiw-quote-entry pkiw-quote-entry--continued" hidden></div>';
	$other       = '<div class="other-entry">Other</div>';
	check( $entry === entry_paper( $entry, $plain_block, new \WP_Block( array( 'postId' => 101 ) ) ), 'entry without the exact style token is byte-identical' );
	check( $continued === entry_paper( $continued, $styled_block, new \WP_Block( array( 'postId' => 101 ) ) ), 'continued entry is byte-identical' );
	check( $other === entry_paper( $other, $styled_block, new \WP_Block( array( 'postId' => 101 ) ) ), 'markup without a quote entry is byte-identical' );
	check( '' === entry_paper( '', $styled_block, null ), 'empty entry markup is byte-identical' );

	$sections = '<div class="wp-block-post-template cr-quote-book__entries pkiw-quote--sectioned"><section class="pkiw-quote-section" data-pkiw-group="devon-reed"><ul></ul></section><section class="pkiw-quote-section" data-pkiw-group="" style="break-inside:avoid"><ul></ul></section></div>';
	$sections_out = strip_seeds( $sections, array( 'blockName' => 'core/post-template' ) );
	$sections_again = strip_seeds( $sections, array( 'blockName' => 'core/post-template' ) );
	check( $sections_out === $sections_again, 'author strip seed is stable on repeated calls' );
	check( strip_for( 'devon-reed' ) === property( $sections_out, 'pkiw-quote-section', '--cr-strip' ), 'author section receives the strip selected from its slug' );
	check( str_contains( $sections_out, 'break-inside:avoid;--cr-strip:' . EMPTY_STRIP ), 'Other keeps existing style and receives the empty strip' );
	$unsectioned = '<div class="wp-block-post-template"><section data-pkiw-group="devon-reed"></section></div>';
	check( $unsectioned === strip_seeds( $unsectioned, array( 'blockName' => 'core/post-template' ) ), 'unsectioned post-template is byte-identical' );

	$works = '<section class="pkiw-quote-works"><ul><li class="pkiw-quote-work" data-pkiw-work="margins">Margins</li><li class="pkiw-quote-work" data-pkiw-work="small-fires" style="display:grid">Fires</li></ul></section>';
	$works_out = strip_seeds( $works, array( 'blockName' => 'post-kinds-indieweb/quote-works' ) );
	check( 2 === substr_count( $works_out, '--cr-strip:' ), 'each source-work item receives one strip' );
	check( str_contains( $works_out, 'display:grid;--cr-strip:' ), 'source-work strip preserves existing style' );
	check( $works === strip_seeds( $works, array( 'blockName' => 'core/group' ) ), 'other blocks pass through byte-identically' );

	$seen = array();
	$all_strips_allowed = true;
	foreach ( range( 1, 200 ) as $number ) {
		$strip = strip_for( 'stored-slug-' . $number );
		$all_strips_allowed = $all_strips_allowed && ( in_array( $strip, STRIPS, true ) || EMPTY_STRIP === $strip );
		$seen[ $strip ] = true;
	}
	check( $all_strips_allowed, 'two hundred slugs stay inside the frozen strip palette' );
	check( count( STRIPS ) === count( $seen ), 'two hundred slugs exercise all seven strips' );

	$GLOBALS['cr_qb_enqueued'] = array();
	$GLOBALS['cr_qb_request']  = array( 'archive' => 'quote' );
	enqueue_styles();
	check( isset( $GLOBALS['cr_qb_enqueued']['courtneyr-quote-book'] ), 'quote archive enqueues the book stylesheet' );
	$GLOBALS['cr_qb_enqueued'] = array();
	$GLOBALS['cr_qb_request']  = array( 'admin' => true );
	enqueue_styles();
	check( isset( $GLOBALS['cr_qb_enqueued']['courtneyr-quote-book'] ), 'admin enqueues the book stylesheet' );
	foreach ( array( array( 'archive' => 'note' ), array( 'single' => true ) ) as $request ) {
		$GLOBALS['cr_qb_enqueued'] = array();
		$GLOBALS['cr_qb_request']  = $request;
		enqueue_styles();
		check( array() === $GLOBALS['cr_qb_enqueued'], 'non-quote front-end request does not enqueue the book stylesheet' );
	}

	$excluded = rucss_exclusions( array( 'existing.css', 'cr-quote-book.css' ) );
	check( array( 'existing.css', 'cr-quote-book.css', 'quote-archive.css' ) === $excluded, 'RUCSS exclusions add both quote files exactly once' );
	check( array( 'cr-quote-book.css', 'quote-archive.css' ) === rucss_exclusions( 'not-an-array' ), 'RUCSS exclusions tolerate non-array input' );

	$php      = (string) file_get_contents( $root . '/inc/quote-book.php' );
	$template = (string) file_get_contents( $root . '/templates/taxonomy-kind-quote.html' );
	$pattern  = (string) file_get_contents( $root . '/patterns/cr-quote-book-loop.php' );
	$css      = (string) file_get_contents( $root . '/assets/css/cr-quote-book.css' );

	check( ! str_contains( $php, 'preg_' ), 'quote-book PHP contains no preg function' );
	check( str_contains( $template, 'courtneyr-child/cr-quote-book-loop' ) && str_contains( $template, 'courtneyr-child/cr-browse-all' ), 'quote template names the book and browse-all patterns' );
	check( str_contains( $template, 'cr-archive cr-archive--quote' ), 'quote template uses the quote archive class' );
	check( comments_balance( $template ), 'quote template block comments balance' );
	check( str_contains( $pattern, "is_registered( 'post-kinds-indieweb/quote-entry' )" ), 'quote pattern guards the plugin entry block' );
	foreach ( array( 'post-kinds-indieweb/quote-authors', 'post-kinds-indieweb/quote-entry', 'post-kinds-indieweb/quote-works' ) as $block_name ) {
		check( str_contains( $pattern, $block_name ), "quote pattern names $block_name" );
	}
	check( str_contains( $pattern, '"queryId":257' ) && str_contains( $pattern, '"perPage":8' ), 'quote pattern pins query 257 to eight posts per page' );
	check( str_contains( $pattern, 'is-style-cr-commonplace' ), 'quote pattern saves the commonplace block style' );
	$book_close = strpos( $pattern, "\t\t<!-- /wp:group -->" );
	$pagination = strpos( $pattern, '<!-- wp:query-pagination ' );
	$query_close = strrpos( $pattern, '<!-- /wp:query -->' );
	check( false !== $book_close && $book_close < $pagination && $pagination < $query_close, 'query pagination follows the book group and stays inside the query' );
	check( ! str_contains( $pattern, 'cr-archive-stream cr-quote' ), 'quote pattern does not opt into Stream archive rules' );
	check( str_contains( $pattern, 'courtneyr-child/cr-archive-stream-loop' ), 'quote pattern falls back to the existing Stream loop' );
	check( comments_balance( $pattern ), 'quote pattern block comments balance' );

	$hexes = array();
	preg_match_all( '/#[0-9a-fA-F]{3,8}\b/', $css, $hexes );
	check( array( '#d8d3c3', '#d8d3c3' ) === $hexes[0], 'quote CSS contains only the two allowed dark rule literals' );
	check( 0 === preg_match( '/\b(?:rgb|rgba|hsl|hsla)\(/i', $css ), 'quote CSS contains no literal colour function' );
	check( 0 === preg_match( '/\bfilter\s*:/i', $css ) && ! str_contains( $css, 'blur(' ) && ! str_contains( $css, 'gradient' ), 'quote CSS contains no filter, blur or gradient' );
	check( 0 === preg_match( '/font-size\s*:\s*(?:0(?:\.\d+)?|0\.[0-6]\d*)rem\b/', $css ), 'quote CSS has no font size below the text floor' );
	check( ! str_contains( $css, '--cr-font-display' ), 'quote CSS never uses the display face' );
	check( str_contains( $css, ':root[data-theme="dark"]' ) && str_contains( $css, ':root:not([data-theme="light"])' ), 'quote CSS contains both dark-mode mechanisms' );
	foreach ( array( 'forced-colors: active', 'prefers-reduced-motion: no-preference', 'min-width: 64rem', 'min-width: 40rem', ':has(', 'var(--cr-strip', 'var(--cr-quote-paper' ) as $required ) {
		check( str_contains( $css, $required ), "quote CSS contains $required" );
	}
	$motion_start = strpos( $css, '@media (prefers-reduced-motion: no-preference)' );
	$forced_start = strpos( $css, '@media (forced-colors: active)' );
	$before_motion = false !== $motion_start ? substr( $css, 0, $motion_start ) : $css;
	$motion_block  = false !== $motion_start && false !== $forced_start ? substr( $css, $motion_start, $forced_start - $motion_start ) : '';
	$after_motion  = false !== $forced_start ? substr( $css, $forced_start ) : $css;
	check( ! str_contains( $before_motion, 'transition' ) && str_contains( $motion_block, 'transition' ) && ! str_contains( $after_motion, 'transition' ), 'every quote CSS transition sits inside the no-preference motion block' );
	check( ! str_contains( $css, '.cr-archive-stream' ), 'quote CSS contains no Stream archive selector' );

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
