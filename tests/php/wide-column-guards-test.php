<?php
/**
 * Guards, memo key and hooks of the wide-column decision.
 *
 * The browser harness only renders the main loop, so it cannot show that a
 * secondary loop, a feed or a password-protected post gets no decision, or that
 * each page of a split post gets its own. These checks do. The hook stand-ins
 * below keep what add_filter registers and call it from apply_filters and
 * do_action, so the layout filter, the header marker and the stylesheet loader
 * run through the hooks the file registers, not by direct call only.
 *
 * Needs the HTML API from a WordPress checkout for the header marker:
 * CR_WP_CORE, or ~/.wp-tests/wordpress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://theme.test' );
	define( 'COURTNEYR_CHILD_VERSION', '9.9.9' );

	class WP_Post {
		public int $ID;

		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	$GLOBALS['t'] = array(
		'singular' => true,
		'main'     => true,
		'loop'     => true,
		'id'       => 7,
		'queried'  => 7,
		'password' => false,
		'content'  => 'page one',
		'kind'     => 'read',
		'result'   => 'wide',
		'calls'    => 0,
	);
	$GLOBALS['page']     = 1;
	$GLOBALS['hooks']    = array();
	$GLOBALS['enqueued'] = array();

	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['hooks'][ $hook ][] = array( $priority, $callback, $accepted_args );
		return true;
	}
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
	function apply_filters( string $hook, $value, ...$args ) {
		$hooked = $GLOBALS['hooks'][ $hook ] ?? array();
		usort(
			$hooked,
			static function ( array $a, array $b ): int {
				return $a[0] <=> $b[0];
			}
		);
		foreach ( $hooked as list( , $callback, $accepted ) ) {
			$value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted ) );
		}
		return $value;
	}
	function do_action( string $hook, ...$args ): void {
		apply_filters( $hook, null, ...$args );
	}
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false ): void {
		$GLOBALS['enqueued'][ $handle ] = $src . '?ver=' . $ver;
	}
	function is_singular( $t = '' ): bool {
		return $GLOBALS['t']['singular'];
	}
	function is_main_query(): bool {
		return $GLOBALS['t']['main'];
	}
	function in_the_loop(): bool {
		return $GLOBALS['t']['loop'];
	}
	function get_post() {
		return new WP_Post( $GLOBALS['t']['id'] );
	}
	function get_queried_object_id(): int {
		return $GLOBALS['t']['queried'];
	}
	function post_password_required( $p = null ): bool {
		return $GLOBALS['t']['password'];
	}
	function get_the_content(): string {
		return $GLOBALS['t']['content'];
	}
	/** Registered shortcodes for the walker checks: [gallery] is one, [not-a-code] is not. */
	function strip_shortcodes( string $text ): string {
		return str_replace( '[gallery]', '', $text );
	}
	/** Blocks for the helper checks: content names a fixture in $GLOBALS['blocks']. */
	function parse_blocks( string $content ): array {
		return $GLOBALS['blocks'][ $content ] ?? array();
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

namespace Courtneyr\Child\Enqueue {
	function primary_kind_slug( int $id ): string {
		return $GLOBALS['t']['kind'];
	}
}

namespace Courtneyr\Child\SingleRead {
	/** The read resolver stand-in; $GLOBALS['t']['result'] picks the answer it gives. */
	function page_record( $post, string $content ): ?array {
		++$GLOBALS['t']['calls'];
		$data = $post->ID . ':' . $content;
		switch ( $GLOBALS['t']['result'] ) {
			case 'narrow':
				return array(
					'wide' => false,
					'data' => $data,
				);
			case 'no-wide-key':
				return array( 'data' => $data );
			case 'none':
				return null;
			default:
				return array(
					'wide' => true,
					'data' => $data,
				);
		}
	}
}

namespace {
	if ( ! load_html_api() ) {
		echo "FAIL the HTML API could not load: set CR_WP_CORE to a WordPress checkout\n";
		exit( 2 );
	}

	require dirname( __DIR__, 2 ) . '/inc/single-wide-column.php';
	require dirname( __DIR__, 2 ) . '/inc/wide-column-card.php';

	use function Courtneyr\Child\SingleWideColumn\applies;
	use function Courtneyr\Child\SingleWideColumn\column_used;
	use function Courtneyr\Child\SingleWideColumn\data_for;
	use function Courtneyr\Child\SingleWideColumn\resolve;

	$fail = 0;
	function check( string $name, bool $ok ): void {
		global $fail;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
		$fail += $ok ? 0 : 1;
	}

	/** Each scenario gets its own content, so the memo cannot hand it another scenario's answer. */
	function scenario( string $content, array $set = array() ): void {
		$GLOBALS['t'] = array_merge(
			$GLOBALS['t'],
			array(
				'id'      => 7,
				'queried' => 7,
				'kind'    => 'read',
				'result'  => 'wide',
			),
			$set,
			array( 'content' => $content )
		);
	}

	/** @return array<string, mixed> */
	function content_block( string $class_name = 'single-post__content' ): array {
		return array(
			'blockName' => 'core/post-content',
			'attrs'     => array(
				'className' => $class_name,
				'layout'    => array( 'type' => 'constrained' ),
			),
		);
	}

	/** @return array<string, mixed> */
	function header_block( string $class_name = 'single-post__header' ): array {
		return array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'className' => $class_name,
				'layout'    => array( 'type' => 'constrained' ),
			),
		);
	}

	/** @return array<int, array{0: int, 1: string, 2: int}> Priority, callback name, accepted args. */
	function hooked( string $hook ): array {
		return array_map(
			static function ( array $h ): array {
				return array( $h[0], is_string( $h[1] ) ? $h[1] : '(closure)', $h[2] );
			},
			$GLOBALS['hooks'][ $hook ] ?? array()
		);
	}

	$ns = 'Courtneyr\\Child\\SingleWideColumn\\';

	// The hooks the file registers.
	check( 'layout filter and card marking start are on render_block_data', array( array( 10, $ns . 'widen', 1 ), array( 10, $ns . 'start_marking', 1 ) ) === hooked( 'render_block_data' ) );
	check( 'card marks are released on render_block at 30, after the kind filters', array( array( 30, $ns . 'release', 2 ) ) === hooked( 'render_block' ) );
	check( 'header marker is on render_block_core/group with two arguments', array( array( 10, $ns . 'mark_header', 2 ) ) === hooked( 'render_block_core/group' ) );
	check( 'stylesheet loader is on wp_enqueue_scripts at 20', array( array( 20, $ns . 'enqueue_styles', 1 ) ) === hooked( 'wp_enqueue_scripts' ) );
	check( 'read, favorite and repost resolvers are registered', array( 'read' => '\\Courtneyr\\Child\\SingleRead\\page_record', 'favorite' => '\\Courtneyr\\Child\\SingleFavorite\\page_sheet', 'repost' => '\\Courtneyr\\Child\\Repost\\page_clipping' ) === \Courtneyr\Child\SingleWideColumn\KINDS );

	// Nothing took the column yet: no sheet.
	check( 'column not used before a block widens', false === column_used() );
	do_action( 'wp_enqueue_scripts' );
	check( 'no stylesheet when no block took the column', array() === $GLOBALS['enqueued'] );

	// The original guard and memo checks.
	check( 'main loop of a post decides', applies() && '7:page one' === data_for( 'read' ) );
	check( 'another kind gets no data', null === data_for( 'watch' ) );

	$GLOBALS['t']['loop'] = false;
	check( 'outside the loop: no decision', ! applies() && null === data_for( 'read' ) );
	$GLOBALS['t']['loop'] = true;

	$GLOBALS['t']['main'] = false;
	check( 'secondary query: no decision', ! applies() && null === data_for( 'read' ) );
	$GLOBALS['t']['main'] = true;

	$GLOBALS['t']['queried'] = 99;
	check( 'loop post is not the queried post: no decision', ! applies() );
	$GLOBALS['t']['queried'] = 7;

	$GLOBALS['t']['singular'] = false;
	check( 'not a single post: no decision', ! applies() );
	$GLOBALS['t']['singular'] = true;

	$GLOBALS['t']['password'] = true;
	check( 'password-protected: no decision', ! applies() );
	$GLOBALS['t']['password'] = false;

	scenario( 'fresh' );
	$GLOBALS['t']['calls'] = 0;
	applies();
	applies();
	check( 'same post, page and content: resolver runs once', 1 === $GLOBALS['t']['calls'] );

	$GLOBALS['page'] = 2;
	check( 'next page of a split post decides again', '7:fresh' === data_for( 'read' ) && 2 === $GLOBALS['t']['calls'] );
	$GLOBALS['page'] = 1;

	scenario( 'edited' );
	check( 'changed content (autosave preview) decides again', '7:edited' === data_for( 'read' ) );

	// Two posts with the same page and content each get their own answer.
	scenario( 'same words', array( 'id' => 7, 'queried' => 7 ) );
	$GLOBALS['t']['calls'] = 0;
	$first                 = data_for( 'read' );
	scenario( 'same words', array( 'id' => 8, 'queried' => 8 ) );
	$second = data_for( 'read' );
	check( 'two posts, same page and content: each post decides', '7:same words' === $first && '8:same words' === $second && 2 === $GLOBALS['t']['calls'] );

	// The resolver's own answer about width.
	scenario( 'narrow page', array( 'result' => 'narrow' ) );
	check( 'resolver says not wide: column does not apply, data still reaches the renderer', ! applies() && '7:narrow page' === data_for( 'read' ) );

	scenario( 'no wide key', array( 'result' => 'no-wide-key' ) );
	check( 'resolver omits wide: not wide', ! applies() && '7:no wide key' === data_for( 'read' ) );

	scenario( 'no card', array( 'result' => 'none' ) );
	check( 'resolver returns null: no decision', ! applies() && null === data_for( 'read' ) );

	// The extension filter.
	add_filter(
		'courtneyr_child_wide_column_kinds',
		static function ( array $kinds ): array {
			$kinds['favorite'] = static function ( $post, string $content ): array {
				return array(
					'wide' => true,
					'data' => 'fav:' . $post->ID . ':' . $content,
				);
			};
			$kinds['watch']    = 'no_such_resolver_function';
			$kinds['note']     = static function (): bool {
				return false;
			};
			unset( $kinds['read'] );
			return $kinds;
		}
	);
	scenario( 'filtered favorite', array( 'kind' => 'favorite' ) );
	check( 'filter adds a kind: its resolver decides', applies() && 'fav:7:filtered favorite' === data_for( 'favorite' ) && null === data_for( 'read' ) );

	scenario( 'filtered read' );
	check( 'filter removes a kind: no decision', ! applies() && null === data_for( 'read' ) );

	scenario( 'not callable', array( 'kind' => 'watch' ) );
	try {
		$ok = ! applies() && null === data_for( 'watch' );
	} catch ( \Throwable $e ) {
		$ok = false;
	}
	check( 'a resolver that is not callable: no decision, no error', $ok );

	scenario( 'resolver says false', array( 'kind' => 'note' ) );
	check( 'a resolver that answers something other than an array: no decision', null === resolve() );
	$GLOBALS['hooks']['courtneyr_child_wide_column_kinds'] = array();

	// Where a page's first block of a type sits.
	$card  = array( 'blockName' => 'x/card', 'innerBlocks' => array() );
	$para  = array( 'blockName' => 'core/paragraph', 'innerBlocks' => array(), 'innerHTML' => '<p>Text.</p>' );
	$ref   = array( 'blockName' => 'core/block', 'attrs' => array( 'ref' => 5 ), 'innerBlocks' => array() );
	$group = static function ( array ...$inner ): array {
		return array( 'blockName' => 'core/group', 'innerBlocks' => $inner );
	};
	$named = static function ( string $name ): array {
		return array( 'blockName' => $name, 'innerBlocks' => array() );
	};
	$GLOBALS['blocks'] = array(
		'top'             => array( $para, $card ),
		'nested'          => array( $para, $group( $para, $card ) ),
		'deep'            => array( $group( $group( $card ) ) ),
		'nested-first'    => array( $group( $card ), $card ),
		'top-first'       => array( $card, $group( $card ) ),
		'none'            => array( $para, $group( $para ) ),
		'synced-only'     => array( $ref ),
		'synced-first'    => array( $ref, $card ),
		'synced-in-group' => array( $group( $ref ), $card ),
		'synced-after'    => array( $card, $ref ),
		'nested-then-ref' => array( $group( $card ), $ref ),
		'shortcode'       => array( array( 'blockName' => null, 'innerBlocks' => array(), 'innerHTML' => "\n[gallery]\n" ), $card ),
		'not-a-shortcode' => array( array( 'blockName' => 'core/paragraph', 'innerBlocks' => array(), 'innerHTML' => '<p>[not-a-code]</p>' ), $card ),
	);
	foreach ( \Courtneyr\Child\SingleWideColumn\OPAQUE_BLOCKS as $opaque ) {
		$GLOBALS['blocks'][ 'opaque ' . $opaque ] = array( $named( $opaque ), $card );
	}
	$place = static function ( string $content ): string {
		return \Courtneyr\Child\SingleWideColumn\first_block_place( $content, 'x/card' );
	};
	check( 'first block at the top level', 'top' === $place( 'top' ) );
	check( 'first block inside a group', 'nested' === $place( 'nested' ) && 'nested' === $place( 'deep' ) );
	check( 'the first block in document order decides', 'nested' === $place( 'nested-first' ) && 'top' === $place( 'top-first' ) );
	check( 'no such block', 'none' === $place( 'none' ) && 'none' === $place( 'unknown' ) );
	check( 'a synced pattern alone: unknown, the card may be inside it', 'unknown' === $place( 'synced-only' ) );
	check( 'a synced pattern before the card, at any depth: unknown', 'unknown' === $place( 'synced-first' ) && 'unknown' === $place( 'synced-in-group' ) );
	check( 'a synced pattern after the first card does not change where that card sits', 'top' === $place( 'synced-after' ) && 'nested' === $place( 'nested-then-ref' ) );
	check( 'a registered shortcode before the card: unknown', 'unknown' === $place( 'shortcode' ) );
	check( 'brackets that are not a registered shortcode: the card is found', 'top' === $place( 'not-a-shortcode' ) );
	$opaque_ok = true;
	foreach ( \Courtneyr\Child\SingleWideColumn\OPAQUE_BLOCKS as $opaque ) {
		$opaque_ok = $opaque_ok && 'unknown' === $place( 'opaque ' . $opaque );
	}
	check( 'every block whose output the page cannot show comes back unknown (' . count( \Courtneyr\Child\SingleWideColumn\OPAQUE_BLOCKS ) . ')', $opaque_ok && in_array( 'core/block', \Courtneyr\Child\SingleWideColumn\OPAQUE_BLOCKS, true ) && in_array( 'core/pattern', \Courtneyr\Child\SingleWideColumn\OPAQUE_BLOCKS, true ) );

	// The card a wide page cuts at: found by its mark, at the top level, with balanced tags.
	$mark   = \Courtneyr\Child\SingleWideColumn\card_mark();
	$marked = '<article data-cr-wide-card="' . $mark . '" class="k-x"><div>Card<img src="x"><svg><path d="M0"/></svg></div></article>';
	$at     = static function ( string $html ): ?array {
		return \Courtneyr\Child\SingleWideColumn\top_level_card( $html );
	};
	$slice  = static function ( string $html ) use ( $at ): ?string {
		$found = $at( $html );
		return null === $found ? null : substr( $html, $found[0], $found[1] - $found[0] );
	};
	check( 'the token is 16 hex characters, the same all request', 1 === preg_match( '/^[0-9a-f]{16}$/', $mark ) && $mark === \Courtneyr\Child\SingleWideColumn\card_mark() );
	check( 'a marked top-level card is found, start to end', $marked === $slice( '<p>Before.</p>' . $marked . '<p>After.</p>' ) );
	check( 'card offsets are byte offsets in the slice', array( 14, 14 + strlen( $marked ) ) === $at( '<p>Before.</p>' . $marked . '<p>After.</p>' ) );
	$variants = array(
		'the class name as text in a code block'          => '<article id="review-authored-article"><div><code>k-x data-cr-wide-card</code></div></article>',
		'the class name in a comment'                     => '<!-- <article data-cr-wide-card="' . $mark . '" class="k-x"></article> -->',
		'the class name in another attribute'             => '<div title="k-x" data-note="article class=k-x"><a href="#k-x">x</a></div>',
		'the class name in a data attribute'              => '<article data-card="k-x" class="k-x"><p>authored</p></article>',
		'the token attribute with another value'          => '<article data-cr-wide-card="0000000000000000" class="k-x"><p>authored</p></article>',
		'an escaped copy of the marked card in a pre'     => '<pre><code>' . htmlspecialchars( $marked ) . '</code></pre>',
		'a script that holds the marked card as a string' => '<script>var c = ' . json_encode( $marked ) . ';</script>',
	);
	foreach ( $variants as $label => $before ) {
		check( "authored $label before the card: the real card is found", $marked === $slice( '<p>Intro.</p>' . $before . $marked . '<p>After.</p>' ) );
		check( "authored $label and no card: nothing is found", null === $at( '<p>Intro.</p>' . $before . '<p>After.</p>' ) );
	}
	check( 'the first marked card inside a group: none', null === $at( '<div class="wp-block-group"><p>a</p>' . $marked . '</div>' . $marked ) );
	check( 'two marked cards at the top level: the first', array( 0, strlen( $marked ) ) === $at( $marked . '<p>x</p>' . $marked ) );
	check( 'an unclosed tag before the card: none', null === $at( '<p>Before.' . $marked . '<p>After.</p>' ) );
	check( 'a stray closing tag after the card: none', null === $at( $marked . '</div>' ) );
	check( 'an unclosed tag after the card: none', null === $at( $marked . '<div><p>x</p>' ) );
	check( 'a card that never closes: none', null === $at( '<p>a</p><article data-cr-wide-card="' . $mark . '" class="k-x"><div>x</div>' ) );
	check( 'an article nested in the card does not end it early', '<article data-cr-wide-card="' . $mark . '"><article>in</article><p>still</p></article>' === $slice( '<article data-cr-wide-card="' . $mark . '"><article>in</article><p>still</p></article><p>after</p>' ) );

	// The layout filter, through render_block_data.
	scenario( 'layout narrow', array( 'result' => 'narrow' ) );
	check( 'not wide: content block unchanged', content_block() === apply_filters( 'render_block_data', content_block() ) );
	check( 'not wide: header unchanged', header_block() === apply_filters( 'render_block_data', header_block() ) );
	check( 'not wide: column still unused', false === column_used() );

	scenario( 'layout wide' );
	$other = array(
		'blockName' => 'core/paragraph',
		'attrs'     => array( 'className' => 'single-post__content' ),
	);
	check( 'wide: another block with the content class unchanged', $other === apply_filters( 'render_block_data', $other ) );
	check( 'wide: post content without the template class unchanged', content_block( 'is-style-plain' ) === apply_filters( 'render_block_data', content_block( 'is-style-plain' ) ) );
	check( 'wide: post content with a class that only contains the name unchanged', content_block( 'single-post__content-extra' ) === apply_filters( 'render_block_data', content_block( 'single-post__content-extra' ) ) );
	check( 'wide: a group without the header class unchanged', header_block( 'single-post__footer' ) === apply_filters( 'render_block_data', header_block( 'single-post__footer' ) ) );
	check( 'wide: column still unused when only other blocks rendered', false === column_used() );

	$header = apply_filters( 'render_block_data', header_block() );
	check(
		'wide: header keeps the reading width and its class',
		array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'className' => 'single-post__header',
				'layout'    => array(
					'type'        => 'constrained',
					'contentSize' => 'var(--wp--style--global--content-size)',
				),
			),
		) === $header
	);
	check( 'wide: the header alone does not load the sheet', false === column_used() );

	$content = apply_filters( 'render_block_data', content_block( 'single-post__content extra' ) );
	check(
		'wide: content block takes align wide, a 100% inner layout and the marker class',
		array(
			'blockName' => 'core/post-content',
			'attrs'     => array(
				'className' => 'single-post__content extra cr-wide-column',
				'layout'    => array(
					'type'        => 'constrained',
					'contentSize' => '100%',
				),
				'align'     => 'wide',
			),
		) === $content
	);
	check( 'wide: the content block marks the column used', true === column_used() );
	column_used( false );
	check( 'column stays used after a later false', true === column_used() );

	do_action( 'wp_enqueue_scripts' );
	check( 'stylesheet enqueued once the column rendered', array( 'courtneyr-wide-column' => 'https://theme.test/assets/css/cr-wide-column.css?ver=9.9.9' ) === $GLOBALS['enqueued'] );

	// The header marker, through render_block_core/group.
	$html = '<div class="wp-block-group single-post__header is-layout-constrained"><h1>T</h1></div>';
	scenario( 'marker wide' );
	$marked = apply_filters( 'render_block_core/group', $html, header_block() );
	check( 'wide: header group gets cr-wide-column on its own tag only', '<div class="wp-block-group single-post__header is-layout-constrained cr-wide-column"><h1>T</h1></div>' === $marked );
	check( 'wide: another group is not marked', $html === apply_filters( 'render_block_core/group', $html, header_block( 'single-post__footer' ) ) );

	scenario( 'marker narrow', array( 'result' => 'narrow' ) );
	check( 'not wide: header group is not marked', $html === apply_filters( 'render_block_core/group', $html, header_block() ) );

	// Marking runs only while a wide page's post content renders, and every mark comes off.
	$card_html = "\n<article class=\"pk-card k-x\">x</article>";
	\Courtneyr\Child\SingleWideColumn\marking( false ); // The layout checks above opened a wide post content and never rendered it.
	check( 'marking is off before the post content renders', false === \Courtneyr\Child\SingleWideColumn\marking() && $card_html === \Courtneyr\Child\SingleWideColumn\mark_card( $card_html, 'k-x' ) );
	scenario( 'marking narrow', array( 'result' => 'narrow' ) );
	apply_filters( 'render_block_data', content_block() );
	check( 'a narrow page does not start marking', false === \Courtneyr\Child\SingleWideColumn\marking() );
	scenario( 'marking wide' );
	apply_filters( 'render_block_data', header_block() );
	check( 'the header alone does not start marking', false === \Courtneyr\Child\SingleWideColumn\marking() );
	apply_filters( 'render_block_data', content_block( 'single-post__content-extra' ) );
	check( 'a post content whose class only contains the name does not start marking', false === \Courtneyr\Child\SingleWideColumn\marking() );
	apply_filters( 'render_block_data', content_block() );
	check( 'a wide page starts marking at its post content', true === \Courtneyr\Child\SingleWideColumn\marking() );
	$marked_card = \Courtneyr\Child\SingleWideColumn\mark_card( $card_html, 'k-x' );
	check( 'while marking, the card article gets the token after its tag name', "\n<article data-cr-wide-card=\"" . $mark . '" class="pk-card k-x">x</article>' === $marked_card );
	check( 'while marking, an article without the class is untouched', '<article class="other">x</article>' === \Courtneyr\Child\SingleWideColumn\mark_card( '<article class="other">x</article>', 'k-x' ) );
	check( 'another block\'s render does not release', $marked_card === apply_filters( 'render_block', $marked_card, header_block() ) && true === \Courtneyr\Child\SingleWideColumn\marking() );
	$released = apply_filters( 'render_block', '<div>' . $marked_card . $marked_card . '</div>', content_block() );
	check( 'the post content releases: every mark comes off byte for byte, marking stops', '<div>' . $card_html . $card_html . '</div>' === $released && false === \Courtneyr\Child\SingleWideColumn\marking() );

	exit( $fail ? 1 : 0 );
}
