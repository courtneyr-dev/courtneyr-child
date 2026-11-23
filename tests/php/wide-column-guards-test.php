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
	check( 'layout filter is on render_block_data', array( array( 10, $ns . 'widen', 1 ) ) === hooked( 'render_block_data' ) );
	check( 'header marker is on render_block_core/group with two arguments', array( array( 10, $ns . 'mark_header', 2 ) ) === hooked( 'render_block_core/group' ) );
	check( 'stylesheet loader is on wp_enqueue_scripts at 20', array( array( 20, $ns . 'enqueue_styles', 1 ) ) === hooked( 'wp_enqueue_scripts' ) );

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

	exit( $fail ? 1 : 0 );
}
