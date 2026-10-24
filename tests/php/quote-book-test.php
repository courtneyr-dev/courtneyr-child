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

	/** Return one quote block with any saved paper style, or one paragraph block. */
	function parse_blocks( string $content ): array {
		$class_name = '';
		if ( preg_match( '/is-style-cr-quote-paper-[a-z]+/', $content, $found ) ) {
			$class_name = $found[0];
		}
		$html = trim( (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/', '', $content ) );
		if ( ! str_contains( $html, '<blockquote' ) ) {
			return array( array( 'blockName' => 'core/paragraph', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $html ) );
		}
		return array(
			array(
				'blockName'   => str_contains( $content, 'wp:pullquote' ) ? 'core/pullquote' : 'core/quote',
				'attrs'       => array( 'className' => $class_name ),
				'innerBlocks' => array(),
				'innerHTML'   => $html,
			),
		);
	}

	function render_block( array $block ): string {
		return (string) ( $block['innerHTML'] ?? '' );
	}

	function is_post_publicly_viewable( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return empty( $GLOBALS['cr_qb_request']['private'][ $id ] );
	}

	function wp_strip_all_tags( $text, $remove_breaks = false ): string {
		return trim( strip_tags( (string) $text ) );
	}

	function strip_shortcodes( $content ): string {
		return (string) $content;
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}

	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES );
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
	use function Courtneyr\Child\QuoteBook\entry_notecard;
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
	check( hooked( 'render_block_post-kinds-indieweb/quote-entry', 'Courtneyr\\Child\\QuoteBook\\entry_notecard', 11, 3 ), 'quote entry notecard hooks at 11, after the paper, with three arguments' );
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
	check( array() === $hexes[0], 'quote CSS contains no raw hex colour' );
	check( 2 === substr_count( $css, '--cr-rule: var(--cr-paper-rule);' ), 'both dark blocks pin the rule to the light-paper token' );
	check( 0 === preg_match( '/\b(?:rgb|rgba|hsl|hsla)\(/i', $css ), 'quote CSS contains no literal colour function' );
	// The notecard section paints the single's sheet (gradient paper, pin shadow, handwriting); the rest of the book keeps hard token paint.
	$card_start = strpos( $css, '/* Notecards. */' );
	$card_end   = strpos( $css, '/* Responsive book layout. */' );
	check( false !== $card_start && false !== $card_end && $card_start < $card_end, 'quote CSS has one notecard section' );
	$card_css = substr( $css, (int) $card_start, (int) $card_end - (int) $card_start );
	$book_css = substr( $css, 0, (int) $card_start ) . substr( $css, (int) $card_end );
	check( 0 === preg_match( '/\bfilter\s*:/i', $book_css ) && ! str_contains( $book_css, 'blur(' ) && ! str_contains( $book_css, 'gradient' ), 'quote CSS outside the notecards contains no filter, blur or gradient' );
	check( ! str_contains( $book_css, '--cr-font-display' ), 'quote CSS outside the notecards never uses the display face' );
	check( ! str_contains( $card_css, 'blur(' ) && 1 === preg_match_all( '/\bfilter\s*:/i', $card_css ), 'the notecard uses one filter (the pin shadow) and no blur' );
	preg_match_all( '/font-size\s*:\s*(\d*\.?\d+)rem\b/', $css, $rem_sizes );
	check( array() === array_filter( $rem_sizes[1], static fn( $size ) => (float) $size < 0.75 ), 'quote CSS has no font size below the 0.75rem text floor' );

	// --- Notecards: entry_notecard() --------------------------------------------------------
	$GLOBALS['cr_qb_request'] = array();
	$highlight = '<span style="background-image: linear-gradient(transparent 60%, rgba(188, 181, 227, 0.5) 60%)" class="sme-highlighter">even if your voice shakes.</span>';
	$hl_post   = new \WP_Post( 201, 'Even if your voice shakes', '<!-- wp:quote {"className":"is-style-cr-quote-paper-lavender"} --><blockquote class="wp-block-quote is-style-cr-quote-paper-lavender is-layout-flow"><p class="wp-block-paragraph"><em>Speak your mind&#8211;' . $highlight . ' Well-aimed <a href="https://example.org/sling">slingshots</a> topple giants.</em></p><cite><em><a href="https://example.org/kuhn" target="_blank">Maggie Kuhn</a></em></cite></blockquote><!-- /wp:quote -->' );
	$long_post = new \WP_Post( 202, 'Long', '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . str_repeat( 'word ', 130 ) . '</p></blockquote><!-- /wp:quote -->' );
	$pull_post = new \WP_Post( 203, 'Pull', '<!-- wp:pullquote --><figure class="wp-block-pullquote"><blockquote><p>A pullquote thought.</p><cite>Pull Notes</cite></blockquote></figure><!-- /wp:pullquote -->' );
	$bare_post = new \WP_Post( 204, 'Bare', '<!-- wp:paragraph --><p>No quote block here.</p><!-- /wp:paragraph -->' );
	$nocite    = new \WP_Post( 205, 'No cite', '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Uncited words.</p></blockquote><!-- /wp:quote -->' );
	$GLOBALS['cr_qb_posts'] = array( 201 => $hl_post, 202 => $long_post, 203 => $pull_post, 204 => $bare_post, 205 => $nocite );
	$GLOBALS['cr_qb_posts'][206] = new \WP_Post( 206, $nocite->post_title, $nocite->post_content );
	$GLOBALS['cr_qb_posts'][207] = new \WP_Post( 207, $hl_post->post_title, $hl_post->post_content );

	/** The plugin's quote-entry markup for one post, after entry_paper(). */
	function plugin_entry( int $id, string $cite = 'Maggie Kuhn', string $extra_class = '' ): string {
		$labelled = "pkiw-quote-$id-text" . ( '' !== $cite ? " pkiw-quote-$id-cite" : '' );
		return '<div style="--cr-quote-paper:var(--cr-printer-ivory)" class="pkiw-quote-entry h-entry is-style-cr-commonplace' . $extra_class . '">'
			. '<blockquote class="pkiw-quote-entry__text p-summary" id="pkiw-quote-' . $id . '-text"><p>Plain excerpt…</p></blockquote>'
			. ( '' !== $cite ? '<cite class="pkiw-quote-entry__cite" id="pkiw-quote-' . $id . '-cite">' . $cite . '</cite>' : '' )
			. '<a class="u-url pkiw-quote-entry__link" href="https://example.test/q' . $id . '/" aria-labelledby="' . $labelled . '"></a>'
			. '<time class="dt-published" datetime="2026-09-14T01:20:28+00:00" hidden></time>'
			. '<span hidden><span class="p-author h-card"><span class="p-name">Courtney</span><data class="u-url" value="https://example.test/"></data></span></span>'
			. '<span class="u-quotation-of h-cite" hidden><span class="p-name">Work</span><span class="p-author">Maggie Kuhn</span></span>'
			. '</div>';
	}
	/** The plugin entry's hidden tail: dt-published, author and h-cite. */
	function plugin_tail( string $entry ): string {
		$start = strpos( $entry, '<time' );
		return substr( $entry, (int) $start, strrpos( $entry, '</div>' ) - (int) $start );
	}
	/** Count of tags with one attribute. */
	function tags_with( string $html, string $attribute, string $tag = '' ): int {
		$tags = new \WP_HTML_Tag_Processor( $html );
		$n    = 0;
		while ( $tags->next_tag( '' !== $tag ? $tag : null ) ) {
			$n += null !== $tags->get_attribute( $attribute ) ? 1 : 0;
		}
		return $n;
	}

	$styled  = array( 'attrs' => array( 'className' => 'is-style-cr-commonplace' ) );
	$entry   = plugin_entry( 201 );
	$card    = entry_notecard( $entry, $styled, new \WP_Block( array( 'postId' => 201 ) ) );
	$card_class = attribute( $card, 'pkiw-quote-entry', 'class' );
	$q_open  = strpos( $card, '<blockquote' );
	$q_close = strpos( $card, '</blockquote>' );
	$c_open  = strpos( $card, '<cite' );
	check( $card !== $entry && str_contains( $card_class, 'cr-note-artifact' ) && str_contains( $card_class, 'cr-note-artifact--book' ) && str_contains( $card_class, 'h-entry' ) && str_contains( $card_class, 'is-style-cr-commonplace' ), 'notecard: the entry root becomes the artifact and stays the h-entry' );
	check( str_contains( $card, $highlight ), 'notecard: the quote block highlight span and its inline background reach the archive byte for byte' );
	check( str_contains( $card, '<em>Speak your mind' ), 'notecard: the quote keeps its emphasis' );
	check( 1 === substr_count( $card, 'class="cr-note__pin"' ) && 1 === substr_count( $card, 'class="cr-note__mark"' ) && 1 === substr_count( $card, 'class="cr-note cr-note--book"' ), 'notecard: one pin, one mark, one paper' );
	check( str_contains( $card, \Courtneyr\Child\QuoteNote\pin_svg() ) && str_contains( $card, \Courtneyr\Child\QuoteNote\mark() ), 'notecard: pin and mark are the single\'s own markup' );
	$note_props = substr( \Courtneyr\Child\QuoteNote\note_style( \Courtneyr\Child\Stamps\seed( '201', 'Even if your voice shakes' ) ), 7, -1 );
	check( str_ends_with( attribute( $card, 'pkiw-quote-entry', 'style' ), ';' . $note_props ), 'notecard: pin colours and tilt equal the single\'s note_style()' );
	check( 'var(--cr-printer-ivory)' === property( $card, 'pkiw-quote-entry', '--cr-quote-paper' ), 'notecard: the paper set by entry_paper() is kept' );
	$text_class = attribute( $card, 'pkiw-quote-entry__text', 'class' );
	check( 'pkiw-quote-201-text' === attribute( $card, 'pkiw-quote-entry__text', 'id' ) && str_contains( $text_class, 'wp-block-quote' ) && str_contains( $text_class, 'is-style-cr-quote-paper-lavender' ) && str_contains( $text_class, 'e-content' ) && ! str_contains( $text_class, 'p-summary' ), 'notecard: the block\'s blockquote carries the plugin text id and e-content' );
	check( ! str_contains( $card, 'Plain excerpt' ), 'notecard: the plain-text excerpt is gone' );
	check( false !== $q_close && false !== $c_open && $q_close < $c_open, 'notecard: the citation sits after the blockquote, outside the clamp' );
	check( 'pkiw-quote-201-cite' === attribute( $card, 'pkiw-quote-entry__cite', 'id' ) && 1 === preg_match( '#<cite [^>]*><em><a\s*>Maggie Kuhn</a></em></cite>#', substr( $card, (int) $c_open ) ), 'notecard: the block\'s own citation markup fills the plugin cite slot' );
	check( 1 === tags_with( $card, 'href' ) && 1 === tags_with( $card, 'href', 'A' ) && 0 === tags_with( $card, 'tabindex' ) && 0 === tags_with( $card, 'target' ), 'notecard: one link, the overlay; quote and cite anchors lose href and target' );
	check( 'https://example.test/q201/' === attribute( $card, 'pkiw-quote-entry__link', 'href' ) && 'pkiw-quote-201-text pkiw-quote-201-cite' === attribute( $card, 'pkiw-quote-entry__link', 'aria-labelledby' ), 'notecard: the overlay keeps its destination and is named by the visible quote and cite' );
	foreach ( array( 'pkiw-quote-201-text', 'pkiw-quote-201-cite' ) as $id ) {
		check( 1 === substr_count( $card, 'id="' . $id . '"' ), "notecard: $id exists exactly once" );
	}
	check( str_contains( $card, plugin_tail( $entry ) ), 'notecard: dt-published, the author h-card and the h-cite pass through byte for byte' );
	check( str_contains( $card_class, 'cr-hand-short' ), 'notecard: a short quote is handwritten, as on its single' );
	check( 1 === substr_count( $card, '<div class="cr-note cr-note--book">' ) && str_ends_with( $card, '</div></div>' ) && substr_count( $card, '<div' ) === substr_count( $card, '</div>' ), 'notecard: divs balance' );
	check( $card === entry_notecard( $entry, $styled, new \WP_Block( array( 'postId' => 201 ) ) ), 'notecard: output is stable on repeated calls' );

	$long = entry_notecard( plugin_entry( 202, 'Long' ), $styled, new \WP_Block( array( 'postId' => 202 ) ) );
	check( str_contains( $long, 'word word' ) && ! str_contains( attribute( $long, 'pkiw-quote-entry', 'class' ), 'cr-hand-short' ), 'notecard: a quote past HAND_MAX_WORDS stays typeset and keeps every word in the DOM' );
	$pull = entry_notecard( plugin_entry( 203, 'Pull Notes' ), $styled, new \WP_Block( array( 'postId' => 203 ) ) );
	check( str_contains( $pull, 'A pullquote thought.' ) && ! str_contains( $pull, '<figure' ) && str_contains( $pull, '>Pull Notes</cite>' ), 'notecard: a pullquote prints its blockquote without the figure, as the single does' );
	$plugin_cite = entry_notecard( plugin_entry( 205, 'Work &amp; Title' ), $styled, new \WP_Block( array( 'postId' => 205 ) ) );
	check( str_contains( $plugin_cite, '<cite class="pkiw-quote-entry__cite" id="pkiw-quote-205-cite">Work &amp; Title</cite>' ), 'notecard: with no block citation the plugin cite text stays' );
	$uncited = entry_notecard( plugin_entry( 206, '' ), $styled, new \WP_Block( array( 'postId' => 206 ) ) );
	check( ! str_contains( $uncited, '<cite' ) && 'pkiw-quote-206-text' === attribute( $uncited, 'pkiw-quote-entry__link', 'aria-labelledby' ), 'notecard: with no citation at all the name points at the quote alone' );

	$bare_entry = plugin_entry( 204 );
	check( $bare_entry === entry_notecard( $bare_entry, $styled, new \WP_Block( array( 'postId' => 204 ) ) ), 'notecard: a post with no quote block keeps the plugin card' );
	$protected_entry = '<div class="pkiw-quote-entry pkiw-quote-entry--protected h-entry is-style-cr-commonplace"><a class="u-url" href="https://example.test/q207/">Locked</a><time class="dt-published" hidden></time></div>';
	check( $protected_entry === entry_notecard( $protected_entry, $styled, new \WP_Block( array( 'postId' => 207 ) ) ), 'notecard: the protected plugin card is byte-identical' );
	$GLOBALS['cr_qb_request']['protected'][207] = true;
	check( plugin_entry( 207 ) === entry_notecard( plugin_entry( 207 ), $styled, new \WP_Block( array( 'postId' => 207 ) ) ), 'notecard: a password-protected post never prints its quote' );
	$GLOBALS['cr_qb_request'] = array( 'private' => array( 207 => true ) );
	check( plugin_entry( 207 ) === entry_notecard( plugin_entry( 207 ), $styled, new \WP_Block( array( 'postId' => 207 ) ) ), 'notecard: a post the visitor may not view never prints its quote' );
	$GLOBALS['cr_qb_request'] = array();
	check( $continued === entry_notecard( $continued, $styled, new \WP_Block( array( 'postId' => 201 ) ) ), 'notecard: the continued placeholder is byte-identical' );
	check( $entry === entry_notecard( $entry, $plain_block, new \WP_Block( array( 'postId' => 201 ) ) ), 'notecard: an entry without the commonplace style is byte-identical' );
	check( '' === entry_notecard( '', $styled, null ), 'notecard: empty markup stays empty' );

	// --- Notecard paint equals the single's sheet (cr-post-kinds.css) at phone width --------
	/** Rules as [ selectors, declarations, media ] from a stylesheet. */
	function css_rules( string $css ): array {
		$css   = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$rules = array();
		$stack = array();
		$start = 0;
		$len   = strlen( $css );
		for ( $i = 0; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				$stack[] = trim( substr( $css, $start, $i - $start ) );
				$start   = $i + 1;
			} elseif ( '}' === $css[ $i ] ) {
				$prelude = array_pop( $stack );
				$body    = substr( $css, $start, $i - $start );
				if ( null !== $prelude && ! str_starts_with( $prelude, '@' ) && ! str_contains( $body, '{' ) ) {
					$selectors = array_map( 'trim', preg_split( '/,(?![^()]*\))/', $prelude ) );
					$decls     = array();
					foreach ( preg_split( '/;(?![^()]*\))/', $body ) as $decl ) {
						$at = strpos( $decl, ':' );
						if ( false !== $at ) {
							$decls[ trim( substr( $decl, 0, $at ) ) ] = trim( (string) preg_replace( '/\s+/', ' ', substr( $decl, $at + 1 ) ) );
						}
					}
					$rules[] = array( $selectors, $decls, implode( ' ', array_filter( $stack, static fn( $p ) => str_starts_with( (string) $p, '@' ) ) ) );
				}
				$start = $i + 1;
			}
		}
		return $rules;
	}
	/** Cascaded declarations for selectors ending in $tail, light mode, at a phone or desktop width. */
	function cascade( array $rules, string $tail, string $scope, bool $phone, string $exclude = '' ): array {
		$out = array();
		foreach ( $rules as [ $selectors, $decls, $media ] ) {
			if ( '' !== $media && ! ( $phone && str_contains( $media, 'max-width: 47.99rem' ) ) ) {
				continue;
			}
			foreach ( $selectors as $selector ) {
				if ( str_ends_with( $selector, $tail ) && str_contains( $selector, $scope ) && ! str_contains( $selector, 'data-theme' ) && ( '' === $exclude || ! str_contains( $selector, $exclude ) ) ) {
					$out = array_merge( $out, $decls );
					break;
				}
			}
		}
		return $out;
	}
	/** The single's literal colours and var() fallbacks, written as the tokens the archive uses. */
	function tokenized( string $value ): string {
		$value = (string) preg_replace_callback( '/rgba\(36, 28, 74, (0?\.\d+)\)/', static fn( $m ) => 'color-mix(in srgb, var(--cr-russian-violet) ' . round( 100 * (float) $m[1] ) . '%, transparent)', $value );
		$value = str_replace( array( '#241c4a', '#126782' ), array( 'var(--cr-russian-violet)', 'var(--cr-cerulean)' ), $value );
		do {
			$before = $value;
			$value  = (string) preg_replace( '/var\((--[a-z0-9-]+),(?:[^()]|\([^()]*\))*\)/', 'var($1)', $value );
		} while ( $before !== $value );
		return $value;
	}

	$single_rules = css_rules( (string) file_get_contents( $root . '/assets/css/cr-post-kinds.css' ) );
	$book_rules   = css_rules( $css );
	$scope        = 'body.single-post.kind-quote';
	$pairs        = array(
		'pin'        => array( '.cr-note-artifact .cr-note__pin', '.cr-note-artifact .cr-note__pin', array( 'position', 'top', 'left', 'width', 'height', 'transform', 'z-index', 'filter', 'pointer-events' ) ),
		'artifact'   => array( '.cr-note-artifact', '.pkiw-quote-entry.cr-note-artifact', array( 'position', 'isolation', 'box-sizing', 'padding-top', 'overflow' ) ),
		'paper'      => array( '.cr-note.cr-note--single', '.cr-note.cr-note--book', array( 'position', 'box-sizing', 'width', 'margin', 'padding', 'border', 'background', 'transform', 'color' ) ),
		'paper face' => array( '.cr-note.cr-note--single::before', '.cr-note.cr-note--book::before', array( 'content', 'display', 'position', 'inset', 'z-index', 'border', 'pointer-events', 'background', 'background-position', 'box-shadow' ) ),
		'mark'       => array( '.cr-note__mark', '.cr-note--book .cr-note__mark', array( 'position', 'top', 'left', 'z-index', 'color', 'font-family', 'font-size', 'line-height', 'pointer-events' ) ),
		'quote'      => array( '.cr-note--single .wp-block-quote', '.cr-note--book blockquote.pkiw-quote-entry__text', array( 'position', 'margin', 'padding', 'border', 'background', 'box-shadow', 'color', 'font-family', 'line-height' ) ),
		'paragraph'  => array( '.cr-note--single blockquote p', '.cr-note--book blockquote.pkiw-quote-entry__text p', array( 'margin', 'padding', 'color', 'font' ) ),
		'cite'       => array( '.cr-note--single blockquote cite', '.cr-note--book .pkiw-quote-entry__cite', array( 'display', 'margin', 'color', 'font-family', 'font-size', 'font-style', 'font-weight', 'line-height' ) ),
		'cite dash'  => array( '.cr-note--single blockquote cite::before', '.cr-note--book .pkiw-quote-entry__cite::before', array( 'content' ) ),
		'cite link'  => array( '.cr-note--single blockquote cite a', '.cr-note--book .pkiw-quote-entry__cite a', array( 'color', 'text-decoration', 'text-underline-offset' ) ),
		'hand'       => array( '.cr-note--single blockquote p', '.cr-hand-short .cr-note--book blockquote.pkiw-quote-entry__text p', array( 'margin', 'font-family', 'line-height', 'letter-spacing', 'overflow-wrap' ) ),
	);
	foreach ( $pairs as $label => [ $single_tail, $book_tail, $properties ] ) {
		$single_scope = 'hand' === $label ? 'cr-hand-short' : ( in_array( $label, array( 'pin', 'artifact', 'mark' ), true ) ? 'kind-quote' : $scope );
		$exclude = 'hand' === $label ? '' : 'cr-hand-short';
		$single  = cascade( $single_rules, $single_tail, $single_scope, true, $exclude );
		if ( 'mark' === $label ) {
			$single = array_merge( $single, cascade( $single_rules, '.cr-note--single .cr-note__mark', $scope, true ) );
		}
		if ( 'quote' === $label ) {
			$single = array_merge( cascade( $single_rules, '.cr-note--single blockquote', $scope, true ), $single );
		}
		$book = cascade( $book_rules, $book_tail, '.cr-quote-book', true, $exclude );
		$diff = array();
		foreach ( $properties as $property ) {
			$want = tokenized( (string) ( $single[ $property ] ?? '(unset)' ) );
			$got  = tokenized( (string) ( $book[ $property ] ?? "(unset)" ) );
			if ( $want !== $got ) {
				$diff[] = "$property: single «{$want}» archive «{$got}»";
			}
		}
		check( array() === $diff, "notecard $label paint equals the single's phone-width sheet" . ( $diff ? ' — ' . implode( '; ', $diff ) : '' ) );
	}
	// Scaled to the card: the single's clamp() floors, the size it renders on a phone.
	$floor = static fn( string $value ): string => preg_match( '/^clamp\(\s*([^,]+),/', $value, $m ) ? trim( $m[1] ) : $value;
	check( $floor( (string) ( cascade( $single_rules, '.cr-note--single .wp-block-quote', $scope, true )['font-size'] ?? '' ) ) === ( cascade( $book_rules, '.cr-note--book blockquote.pkiw-quote-entry__text', '.cr-quote-book', true )['font-size'] ?? '' ), 'notecard quote size is the single\'s clamp floor' );
	check( $floor( (string) ( cascade( $single_rules, '.cr-note--single blockquote p', 'cr-hand-short', true )['font-size'] ?? '' ) ) === ( cascade( $book_rules, '.cr-hand-short .cr-note--book blockquote.pkiw-quote-entry__text p', '.cr-quote-book', true )['font-size'] ?? '' ), 'notecard handwriting size is the single\'s clamp floor' );
	check( $floor( (string) ( cascade( $single_rules, '.cr-note-artifact--single', $scope, true )['--pin-h'] ?? '' ) ) === ( cascade( $book_rules, '.pkiw-quote-entry.cr-note-artifact', '.cr-quote-book', true )['--pin-h'] ?? '' ), 'notecard pin height is the single\'s clamp floor' );
	check( '58%' === ( cascade( $book_rules, '.pkiw-quote-entry.cr-note-artifact', '.cr-quote-book', true )['--pin-x'] ?? '' ) && '58%' === ( cascade( $single_rules, '.cr-note-artifact--single', $scope, true )['--pin-x'] ?? '' ), 'notecard pin sits at the single\'s 58%' );
	check( str_contains( $card_css, '-webkit-line-clamp: 9;' ) && ! str_contains( (string) file_get_contents( $root . '/inc/quote-book.php' ), 'wp_trim_words' ), 'a long quote is previewed by a nine-line CSS clamp, not by cutting markup' );

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
