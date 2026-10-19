<?php
/**
 * `php tests/php/quote-archive-test.php`: the Quote archive prints each quote
 * as the single's pinned sheet, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_q_hooks']   = array();
	$GLOBALS['cr_q_request'] = array();
	$GLOBALS['cr_q_fail']    = array();
	$GLOBALS['cr_q_count']   = 0;

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

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ): void {
		$GLOBALS['cr_q_hooks'][] = array( $hook, $callback, $priority, $args );
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
	}
	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && 'quote' === $term && ! empty( $GLOBALS['cr_q_request']['archive'] );
	}
	function is_singular( $type = '' ): bool {
		return ! empty( $GLOBALS['cr_q_request']['single'] );
	}
	function get_queried_object_id(): int {
		return ! empty( $GLOBALS['cr_q_request']['single'] ) ? 30 : 0;
	}
	function get_post_time( $format = 'U', $gmt = false, $post = null ) {
		return 1789215600;
	}
	function wp_date( $format, $timestamp = null ): string {
		return gmdate( 'c' === $format ? 'c' : 'F j, Y', (int) $timestamp );
	}
	function get_option( $name, $default = false ) {
		return 'date_format' === $name ? 'F j, Y' : $default;
	}
	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && ( $GLOBALS['cr_q_request']['kinds'][ $id ] ?? '' ) === $term;
	}
	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post && ! empty( $GLOBALS['cr_q_request']['single'] ) ? 30 : (int) $post );
		return $GLOBALS['cr_q_request']['posts'][ $id ] ?? null;
	}
	function get_the_ID(): int {
		return 0;
	}
	function post_password_required( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_q_request']['protected'][ $id ] );
	}
	/** The quote block of a post body: a core/quote with its inner HTML. */
	function parse_blocks( string $content ): array {
		if ( isset( $GLOBALS['cr_q_request']['blocks'] ) ) {
			return $GLOBALS['cr_q_request']['blocks'];
		}
		if ( ! preg_match( '/<!-- wp:quote( (\{.*?\}))? -->(.*?)<!-- \/wp:quote -->/s', $content, $m ) ) {
			return array( array( 'blockName' => 'core/paragraph', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $content ) );
		}
		$attrs = '' !== $m[2] ? (array) json_decode( $m[2], true ) : array();
		return array( array( 'blockName' => 'core/quote', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => trim( $m[3] ) ) );
	}
	function render_block( array $block ): string {
		return (string) ( $block['innerHTML'] ?? '' );
	}
	function wp_strip_all_tags( $text, $remove_breaks = false ): string {
		return trim( strip_tags( (string) $text ) );
	}
	function strip_shortcodes( $text ): string {
		return (string) $text;
	}
	function wp_allowed_protocols(): array {
		return array( 'http', 'https' );
	}
	function register_block_style( $block, $style ): void {
		$GLOBALS['cr_q_styles'][] = array( $block, $style );
	}
	function __( $text, $domain = null ): string {
		return (string) $text;
	}
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
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
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace Courtneyr\Child\Journal {
	function margin_lines( int $seed, string $kind ): array {
		return array( 'Words worth keeping.', 'Say it plainly.' );
	}
	function aside( string $line, int $n, string $class ): string {
		return '';
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_q_request']['stream'] );
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/stamps.php';
	require dirname( __DIR__, 2 ) . '/inc/quote-note.php';

	use function Courtneyr\Child\QuoteNote\archive_sheet;
	use function Courtneyr\Child\QuoteNote\stream_card;
	use const Courtneyr\Child\QuoteNote\PAPERS;
	use function Courtneyr\Child\QuoteNote\note_page;
	use function Courtneyr\Child\QuoteNote\paper_for;

	const CR_Q_QUOTE = '<blockquote class="wp-block-quote is-style-side-rule-editorial"><p>When the risk to remain tight in a bud was more painful than the risk it took to blossom.</p><p>Well, I know which risk I would rather take.</p><cite>Ted Lasso</cite></blockquote>';

	function cr_q_card( int $id = 30, string $title = 'Risking embarrassment' ): string {
		return '<article class="pk-card pk-card--stream k-quote h-entry"><div class="pk-badge"><svg></svg></div><div class="pk-body"><span class="pk-kindlabel">Quote</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/q/' . $id . '/">' . $title . '</a></h2><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-12T11:20:00+00:00">September 12, 2026</time></p></div><p class="pk-excerpt p-summary">When the risk to remain tight [&hellip;]</p><div class="pk-meta"><a class="pk-link" href="https://example.test/q/' . $id . '/">Read more<span class="pk-sr-only">: ' . $title . '</span></a></div></div></article>';
	}

	function cr_q_reset( string $content = '', bool $archive = true ): void {
		$content                 = '' === $content ? '<!-- wp:quote -->' . CR_Q_QUOTE . '<!-- /wp:quote -->' : $content;
		$GLOBALS['cr_q_request'] = array(
			'archive'   => $archive,
			'stream'    => false,
			'posts'     => array( 30 => new WP_Post( 30, 'Risking embarrassment', $content ) ),
			'kinds'     => array( 30 => 'quote' ),
			'protected' => array(),
		);
	}

	function cr_q_sheet( ?string $html = null ): string {
		return archive_sheet( $html ?? cr_q_card(), array(), new WP_Block( array( 'postId' => 30 ) ) );
	}

	function cr_q_check( string $name, callable $test ): void {
		++$GLOBALS['cr_q_count'];
		try {
			$ok = (bool) $test();
		} catch ( \Throwable $e ) {
			$ok    = false;
			$name .= ' (' . $e->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_q_fail'][] = $name;
		}
	}

	cr_q_check(
		'the archive sheet is hooked on the stream card at 10',
		static function (): bool {
			foreach ( $GLOBALS['cr_q_hooks'] as $hook ) {
				if ( 'render_block_post-kinds-indieweb/stream-card' === $hook[0] && 'Courtneyr\\Child\\QuoteNote\\archive_sheet' === $hook[1] && 10 === $hook[2] && 3 === $hook[3] ) {
					return true;
				}
			}
			return false;
		}
	);

	cr_q_check(
		'on the Quote archive a quote card becomes the pinned sheet with the whole quote, its cite and the date',
		static function (): bool {
			cr_q_reset();
			$html = cr_q_sheet();
			return 1 === preg_match( '/^<div class="cr-note-artifact cr-note-artifact--single cr-note-artifact--archive cr-hand-short" style="--cr-pin:/', $html )
				&& str_contains( $html, '<svg class="cr-note__pin"' )
				&& str_contains( $html, '<article class="pk-card k-quote h-entry cr-note cr-note--single">' )
				&& str_contains( $html, 'cr-note__mark' )
				&& str_contains( $html, '<p class="cr-note__label cr-sr-only">Quote</p>' )
				&& str_contains( $html, '<h2 class="pk-title p-name"><a class="u-url" href="https://example.test/q/30/">Risking embarrassment</a></h2>' )
				&& str_contains( $html, 'Well, I know which risk I would rather take.' )
				&& str_contains( $html, '<cite>Ted Lasso</cite>' )
				&& 1 === preg_match( '/<blockquote\b[^>]*\bclass="[^"]*\be-content\b/', $html )
				&& str_contains( $html, '<p class="cr-note__date"><time class="dt-published" datetime="2026-09-12T11:20:00+00:00">September 12, 2026</time></p>' )
				&& 1 === substr_count( $html, '<a ' )
				&& ! str_contains( $html, 'pk-excerpt' )
				&& ! str_contains( $html, 'Read more' )
				&& ! str_contains( $html, 'pk-badge' )
				&& str_ends_with( $html, '</article></div>' );
		}
	);

	cr_q_check(
		'the plugin still reads the sheet as the rooted h-entry card',
		static function (): bool {
			cr_q_reset();
			return 1 === preg_match( '/<article\b[^>]*\bclass="[^"]*\bpk-card\b[^"]*\bh-entry\b/i', cr_q_sheet() );
		}
	);

	cr_q_check(
		'a quote over the handwriting limit is typeset',
		static function (): bool {
			cr_q_reset( '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . str_repeat( 'word ', 130 ) . '</p></blockquote><!-- /wp:quote -->' );
			$html = cr_q_sheet();
			return str_contains( $html, 'cr-note-artifact--archive' ) && ! str_contains( $html, 'cr-hand-short' );
		}
	);

	cr_q_check(
		'unchanged cases return the card byte for byte',
		static function (): bool {
			$card = cr_q_card();
			cr_q_reset( '', false );
			$not_archive = cr_q_sheet();
			cr_q_reset();
			$GLOBALS['cr_q_request']['stream'] = true;
			$GLOBALS['cr_q_request']['archive'] = false;
			$stream = cr_q_sheet();
			cr_q_reset();
			$GLOBALS['cr_q_request']['protected'][30] = true;
			$protected = cr_q_sheet();
			cr_q_reset();
			$protected_card = '<article class="pk-card pk-card--stream pk-card--protected k-quote h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/q/30/">Protected: Risking</a></h2></article>';
			$protected_card_out = cr_q_sheet( $protected_card );
			cr_q_reset();
			$GLOBALS['cr_q_request']['kinds'][30] = 'note';
			$note = cr_q_sheet();
			cr_q_reset( '<!-- wp:paragraph --><p>No quote block here.</p><!-- /wp:paragraph -->' );
			$no_quote = cr_q_sheet();
			cr_q_reset();
			return $card === $not_archive && $card === $stream && $card === $protected && $protected_card === $protected_card_out
				&& $card === $note && $card === $no_quote && '' === cr_q_sheet( '' );
		}
	);

	cr_q_check(
		'the single sheet rules reach the Quote archive, which is one column with its paper reset and no collage doodles',
		static function (): bool {
			$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/cr-post-kinds.css' );
			$both = ':is(body.single-post.kind-quote .single-post__content, body.tax-kind.term-quote .cr-archive-stream)';
			$main = (string) shell_exec( 'git -C ' . escapeshellarg( dirname( __DIR__, 2 ) ) . ' show origin/main:assets/css/cr-post-kinds.css 2>/dev/null' );
			$old  = 'body.single-post.kind-quote .single-post__content ';
			// Every single-sheet rule on main is widened to the archive, line for line.
			foreach ( explode( "\n", $main ) as $line ) {
				if ( str_contains( $line, $old ) && ( str_contains( $line, '.cr-note--single' ) || str_contains( $line, '.cr-note-artifact--single' ) ) && ! str_contains( $line, 'cr-journal' ) ) {
					if ( ! str_contains( $css, str_replace( $old, $both . ' ', $line ) ) ) {
						return false;
					}
				}
			}
			return '' !== $main
				&& str_contains( $css, 'body.tax-kind.term-quote .cr-archive-stream .cr-note--single .pk-title a:focus-visible { outline: 3px solid var(--cr-russian-violet);' )
				&& str_contains( $css, $both . ' .cr-note.cr-note--single::before {' )
				&& str_contains( $css, $both . ' .cr-note--single blockquote,' )
				&& str_contains( $css, $both . ' .cr-note-artifact--single {' )
				&& str_contains( $css, 'body.tax-kind.term-quote .cr-archive-stream .wp-block-post-template { columns: auto; max-width: 46rem; }' )
				&& str_contains( $css, 'body.tax-kind.term-quote .cr-archive-stream .cr-note.cr-note--single::before { width: auto; height: auto; margin: 0; border-radius: 0; opacity: 1; transform: none; }' )
				&& str_contains( $css, "body.tax-kind.term-quote .cr-archive-stream .wp-block-post-template::after { content: none; }" )
				&& str_contains( $css, 'body.tax-kind.term-quote .cr-archive-stream .cr-note-artifact--archive.cr-hand-short .cr-note--single blockquote p {' )
				&& ! str_contains( $css, "\nbody.single-post.kind-quote .single-post__content .cr-note--single blockquote {" );
		}
	);

	/** The --cr-quote-paper value an artifact carries, or ''. */
	function cr_q_paper( string $html ): string {
		return preg_match( '/--cr-quote-paper:([^;"]+)/', $html, $m ) ? $m[1] : '';
	}

	function cr_q_preview(): string {
		$GLOBALS['cr_q_request']['stream']  = true;
		$GLOBALS['cr_q_request']['archive'] = false;
		$out = stream_card( cr_q_card(), array(), new WP_Block( array( 'postId' => 30 ) ) );
		$GLOBALS['cr_q_request']['stream']  = false;
		$GLOBALS['cr_q_request']['archive'] = true;
		return $out;
	}

	cr_q_check(
		'five quote papers are block styles on core/quote, each a light palette paper',
		static function (): bool {
			$GLOBALS['cr_q_styles'] = array();
			foreach ( $GLOBALS['cr_q_hooks'] as $hook ) {
				if ( 'init' === $hook[0] && 'Courtneyr\\Child\\QuoteNote\\register_paper_styles' === $hook[1] ) {
					( $hook[1] )();
				}
			}
			$names = array_map( static fn( array $s ): string => $s[0] . ' ' . $s[1]['name'], $GLOBALS['cr_q_styles'] );
			$want  = array_map( static fn( string $k ): string => 'core/quote cr-quote-paper-' . $k, array_keys( PAPERS ) );
			return array( 'lavender', 'ivory', 'peach', 'sky', 'butter' ) === array_keys( PAPERS ) && $want === $names
				&& 0 === count( array_filter( PAPERS, static fn( array $p ): bool => ! str_starts_with( $p[1], 'var(--cr-' ) && ! str_starts_with( $p[1], 'color-mix(in srgb, var(--cr-' ) ) );
		}
	);

	cr_q_check(
		'one quote, one paper: the archive sheet and the Stream preview carry the same seeded paper',
		static function (): bool {
			$seen = array();
			foreach ( array( 30, 31, 32, 33, 34, 35, 36, 37, 38, 39 ) as $id ) {
				cr_q_reset();
				$GLOBALS['cr_q_request']['posts'][ $id ] = new WP_Post( $id, 'Quote ' . $id, $GLOBALS['cr_q_request']['posts'][30]->post_content );
				$GLOBALS['cr_q_request']['kinds'][ $id ] = 'quote';
				$sheet   = archive_sheet( cr_q_card( $id ), array(), new WP_Block( array( 'postId' => $id ) ) );
				$GLOBALS['cr_q_request']['stream']  = true;
				$GLOBALS['cr_q_request']['archive'] = false;
				$preview = stream_card( cr_q_card( $id ), array(), new WP_Block( array( 'postId' => $id ) ) );
				$paper   = cr_q_paper( $sheet );
				if ( '' === $paper || $paper !== cr_q_paper( $preview ) || ! in_array( $paper, array_column( PAPERS, 1 ), true ) ) {
					return false;
				}
				$seen[ $paper ] = true;
			}
			return count( $seen ) >= 3;
		}
	);

	cr_q_check(
		'a paper picked as the quote block style wins on every surface',
		static function (): bool {
			cr_q_reset( '<!-- wp:quote {"className":"is-style-cr-quote-paper-sky"} -->' . CR_Q_QUOTE . '<!-- /wp:quote -->' );
			$sheet = cr_q_sheet();
			$preview = cr_q_preview();
			cr_q_reset( '<!-- wp:quote {"className":"is-style-cr-quote-paper-nope"} -->' . CR_Q_QUOTE . '<!-- /wp:quote -->' );
			$unknown = cr_q_paper( cr_q_sheet() );
			return PAPERS['sky'][1] === cr_q_paper( $sheet ) && PAPERS['sky'][1] === cr_q_paper( $preview ) && in_array( $unknown, array_column( PAPERS, 1 ), true );
		}
	);

	cr_q_check(
		'every quote paper surface reads the one custom property',
		static function (): bool {
			$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/cr-post-kinds.css' );
			return 2 === substr_count( $css, 'var(--cr-quote-paper, var(--cr-printer-ivory));' )
				&& ! str_contains( $css, 'transform: none; background-color: #f0eef8; }' );
		}
	);

	cr_q_check(
		'the single sheet carries the same paper as the archive and the Stream',
		static function (): bool {
			cr_q_reset( '<!-- wp:quote {"className":"is-style-cr-quote-paper-butter"} -->' . CR_Q_QUOTE . '<!-- /wp:quote -->' );
			$archive = cr_q_paper( cr_q_sheet() );
			$GLOBALS['cr_q_request']['archive'] = false;
			$GLOBALS['cr_q_request']['single']  = true;
			$single = note_page( '<div class="entry-content">' . CR_Q_QUOTE . '</div>', array( 'blockName' => 'core/post-content' ) );
			$GLOBALS['cr_q_request']['single'] = false;
			cr_q_reset();
			$GLOBALS['cr_q_request']['archive'] = false;
			$GLOBALS['cr_q_request']['single']  = true;
			$seeded_single = cr_q_paper( note_page( '<div>' . CR_Q_QUOTE . '</div>', array( 'blockName' => 'core/post-content' ) ) );
			$GLOBALS['cr_q_request']['single'] = false;
			$GLOBALS['cr_q_request']['archive'] = true;
			return PAPERS['butter'][1] === $archive && $archive === cr_q_paper( $single ) && str_contains( $single, 'cr-note-artifact--single' )
				&& cr_q_paper( cr_q_sheet() ) === $seeded_single;
		}
	);

	cr_q_check(
		'a styled quote nested in a group still sets the paper',
		static function (): bool {
			cr_q_reset();
			$GLOBALS['cr_q_request']['blocks'] = array(
				array(
					'blockName'   => 'core/group',
					'attrs'       => array(),
					'innerBlocks' => array( array( 'blockName' => 'core/quote', 'attrs' => array( 'className' => 'extra is-style-cr-quote-paper-sky' ), 'innerBlocks' => array(), 'innerHTML' => CR_Q_QUOTE ) ),
					'innerHTML'   => '',
				),
			);
			$paper = paper_for( $GLOBALS['cr_q_request']['posts'][30] );
			unset( $GLOBALS['cr_q_request']['blocks'] );
			return PAPERS['sky'][1] === $paper;
		}
	);

	cr_q_check(
		'the paper block style sets its own ink and cite colours, so it reads in dark mode outside the sheet',
		static function (): bool {
			$GLOBALS['cr_q_styles'] = array();
			\Courtneyr\Child\QuoteNote\register_paper_styles();
			foreach ( $GLOBALS['cr_q_styles'] as $style ) {
				$css = (string) ( $style[1]['inline_style'] ?? '' );
				if ( ! str_contains( $css, 'color:var(--cr-russian-violet)' ) || ! str_contains( $css, ' cite{color:var(--cr-cerulean)}' ) ) {
					return false;
				}
			}
			return 5 === count( $GLOBALS['cr_q_styles'] );
		}
	);

	echo "\n", count( $GLOBALS['cr_q_fail'] ) ? count( $GLOBALS['cr_q_fail'] ) . ' failed' : $GLOBALS['cr_q_count'] . ' passed', "\n";
	exit( count( $GLOBALS['cr_q_fail'] ) ? 1 : 0 );
}
