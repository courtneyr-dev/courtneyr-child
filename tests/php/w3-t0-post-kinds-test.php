<?php
/**
 * `php tests/php/w3-t0-post-kinds-test.php`: checks W3-T0 shared
 * single-object header and repeated featured-image filters without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	$no_plugin             = in_array( '--no-plugin', $argv, true );
	$GLOBALS['no_plugin'] = $no_plugin;
	$GLOBALS['child_out'] = '';
	$GLOBALS['child_err'] = '';
	$GLOBALS['child_code'] = 0;
	if ( ! $no_plugin ) {
		$child = proc_open(
			array( PHP_BINARY, __FILE__, '--no-plugin' ),
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		if ( ! is_resource( $child ) ) {
			fwrite( STDERR, "Could not launch no-plugin child\n" );
			exit( 1 );
		}
		$GLOBALS['child_out']  = stream_get_contents( $pipes[1] );
		$GLOBALS['child_err']  = stream_get_contents( $pipes[2] );
		$GLOBALS['child_code'] = proc_close( $child );
	}

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_W3_T0_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']             = array();
	$GLOBALS['cr_request']           = array();
	$GLOBALS['cr_posts']             = array();
	$GLOBALS['cr_terms']             = array();
	$GLOBALS['cr_passwords']         = array();
	$GLOBALS['cr_current_post']      = 0;
	$GLOBALS['cr_find_block']        = array();
	$GLOBALS['cr_kind_facts']        = array();
	$GLOBALS['cr_featured_called']   = 0;
	$GLOBALS['cr_last_has_term_arg'] = null;

	/** Stand-in post. */
	class WP_Post {
		/** @var int Post ID. */
		public $ID;

		/** @var string Post title. */
		public $post_title = '';

		/**
		 * Construct a post.
		 *
		 * @param int    $id    Post ID.
		 * @param string $title Post title.
		 */
		public function __construct( int $id, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	/** Register a hook callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Register an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Check whether a filter has callbacks. */
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['cr_hooks'][ $hook ] ) ) {
			return false;
		}
		foreach ( $GLOBALS['cr_hooks'][ $hook ] as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( false === $callback || $registered[0] === $callback ) {
					return (int) $priority;
				}
			}
		}
		return false;
	}

	/** Return a registered callback's accepted arg count. */
	function accepted_args_for( string $hook, string $callback, int $priority = 10 ): int {
		foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] ?? array() as $registered ) {
			if ( $registered[0] === $callback ) {
				return (int) $registered[1];
			}
		}
		return 0;
	}

	/** Run filters with priority order. */
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = array_keys( $GLOBALS['cr_hooks'][ $hook ] ?? array() );
		sort( $priorities );
		foreach ( $priorities as $priority ) {
			foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] as $registered ) {
				$all   = array_merge( array( $value ), $args );
				$value = call_user_func_array( $registered[0], array_slice( $all, 0, $registered[1] ) );
			}
		}
		return $value;
	}

	/** Singular request flag. */
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Current queried object. */
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? $GLOBALS['cr_current_post'] );
	}

	/** Return a post. */
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}

	/** Term membership. */
	function has_term( $term, $taxonomy, $post ) {
		$GLOBALS['cr_last_has_term_arg'] = $term;
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = $post instanceof WP_Post ? $post->ID : (int) $post;
		$terms = (array) ( $GLOBALS['cr_terms'][ $id ] ?? array() );
		foreach ( (array) $term as $one ) {
			if ( in_array( $one, $terms, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Password-protected flag. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	function is_admin() { return false; }
	function shortcode_exists() { return false; }
	function do_shortcode( $text ) { return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function get_posts() { return array(); }
	function wp_is_json_request() { return false; }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : ( 0 === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_posts'][ $id ]->post_title ?? '' );
	}
	function get_term_by() { return null; }
	function __( $text ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $text ) { return $text; }
}

namespace PKIW {
	if ( ! $GLOBALS['no_plugin'] ) {
		/** Return stored kind facts. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_kind_facts'][ $post_id ] ?? array();
		}

		/** This legacy API must not be called by W3-T0. */
		function featured_in_content(): bool {
			++$GLOBALS['cr_featured_called'];
			return true;
		}
	}
}

namespace Courtneyr\Child\CheckinMap {
	/** Stub map slip. */
	function slip(): string {
		return '';
	}
}

namespace Courtneyr\Child\StreamMedia {
	/** Stub block finder. */
	function find_block( \WP_Post $post, string $block_name ) {
		return $GLOBALS['cr_find_block'][ $post->ID ][ $block_name ] ?? null;
	}
}

namespace {
	require CR_W3_T0_THEME_DIR . '/inc/post-kinds.php';
	require CR_W3_T0_THEME_DIR . '/inc/single-eat-drink.php';
	$GLOBALS['cr_base_hooks'] = $GLOBALS['cr_hooks'];
}

namespace Courtneyr\Child\W3T0PostKindsTests {

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'PASS ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Reset request and post state. */
	function reset_state(): void {
		$GLOBALS['cr_request']           = array();
		$GLOBALS['cr_posts']             = array();
		$GLOBALS['cr_terms']             = array();
		$GLOBALS['cr_passwords']         = array();
		$GLOBALS['cr_current_post']      = 0;
		$GLOBALS['cr_find_block']        = array();
		$GLOBALS['cr_kind_facts']        = array();
		$GLOBALS['cr_featured_called']   = 0;
		$GLOBALS['cr_last_has_term_arg'] = null;
		$GLOBALS['cr_hooks']             = $GLOBALS['cr_base_hooks'];
	}

	/** Add a post fixture. */
	function post_fixture( int $id, string $kind = 'photo', bool $single = true ): \WP_Post {
		$post                              = new \WP_Post( $id, 'Fixture ' . $id );
		$GLOBALS['cr_posts'][ $id ]        = $post;
		$GLOBALS['cr_terms'][ $id ]        = array( $kind );
		$GLOBALS['cr_current_post']        = $id;
		$GLOBALS['cr_request']             = $single ? array( 'single' => true, 'queried_id' => $id ) : array();
		return $post;
	}

	/** Generic block fixture. */
	function block( string $name, string $class = '' ): array {
		return array(
			'blockName' => $name,
			'attrs'    => array( 'className' => $class ),
		);
	}

	/** Call the shared header filter if it exists. */
	function call_header_filter( string $html, array $block ) {
		return function_exists( '\\Courtneyr\\Child\\PostKinds\\drop_template_header' )
			? \Courtneyr\Child\PostKinds\drop_template_header( $html, $block )
			: '__missing__';
	}

	/** Call the shared repeated-featured-image filter if it exists. */
	function call_repeat_filter( string $html, array $block ) {
		return function_exists( '\\Courtneyr\\Child\\PostKinds\\suppress_repeated_featured_image' )
			? \Courtneyr\Child\PostKinds\suppress_repeated_featured_image( $html, $block )
			: '__missing__';
	}

	$header_callback = 'Courtneyr\\Child\\PostKinds\\drop_template_header';
	check( function_exists( $header_callback ), 'shared template-header filter exists in PostKinds' );
	check( 10 === \has_filter( 'render_block', $header_callback ), 'shared template-header filter is hooked on render_block at priority 10' );
	check( 2 === \accepted_args_for( 'render_block', $header_callback ), 'shared template-header filter accepts 2 args' );

	reset_state();
	$post = post_fixture( 10, 'eat' );
	\add_filter(
		'courtneyr_child_single_object_holds_h1',
		static function ( $holds, $seen ) use ( $post ) {
			return $seen === $post ? true : $holds;
		},
		1,
		2
	);
	check( '' === call_header_filter( '<header>Header</header>', block( 'core/group', 'layout single-post__header extra' ) ), 'shared template-header filter drops the single post header group when the object holds the H1' );
	check( '' === call_header_filter( '<figure>Image</figure>', block( 'core/post-featured-image', 'single-post__featured size-large' ) ), 'shared template-header filter drops the template featured image when the object holds the H1' );
	check( '<p>Body</p>' === call_header_filter( '<p>Body</p>', block( 'core/paragraph', 'single-post__header' ) ), 'shared template-header filter leaves unrelated blocks unchanged' );

	reset_state();
	post_fixture( 11, 'eat', false );
	\add_filter( 'courtneyr_child_single_object_holds_h1', static fn() => true, 1, 2 );
	check( '<header>Header</header>' === call_header_filter( '<header>Header</header>', block( 'core/group', 'single-post__header' ) ), 'shared template-header filter leaves non-singular requests unchanged' );

	reset_state();
	$GLOBALS['cr_current_post'] = 12;
	$GLOBALS['cr_request']      = array( 'single' => true, 'queried_id' => 12 );
	\add_filter( 'courtneyr_child_single_object_holds_h1', static fn() => true, 1, 2 );
	check( '<header>Header</header>' === call_header_filter( '<header>Header</header>', block( 'core/group', 'single-post__header' ) ), 'shared template-header filter leaves missing posts unchanged' );

	reset_state();
	post_fixture( 13, 'eat' );
	check( '<header>Header</header>' === call_header_filter( '<header>Header</header>', block( 'core/group', 'single-post__header' ) ), 'shared template-header filter leaves false filter results unchanged' );
	\add_filter( 'courtneyr_child_single_object_holds_h1', static fn() => 1, 1, 2 );
	check( '<header>Header</header>' === call_header_filter( '<header>Header</header>', block( 'core/group', 'single-post__header' ) ), 'shared template-header filter requires exactly true from the H1 filter' );

	$order_drop_callback = 'Courtneyr\\Child\\SingleOrder\\drop_template_header';
	$holds_callback      = 'Courtneyr\\Child\\SingleOrder\\holds_h1';
	check( ! function_exists( $order_drop_callback ), 'SingleOrder no longer defines drop_template_header' );
	check( false === \has_filter( 'render_block', $order_drop_callback ), 'SingleOrder no longer hooks render_block for template header drops' );
	check( function_exists( $holds_callback ), 'SingleOrder defines holds_h1' );
	check( 10 === \has_filter( 'courtneyr_child_single_object_holds_h1', $holds_callback ), 'SingleOrder hooks holds_h1 on the H1 ownership filter at priority 10' );
	check( 2 === \accepted_args_for( 'courtneyr_child_single_object_holds_h1', $holds_callback ), 'SingleOrder holds_h1 accepts 2 args' );
	if ( function_exists( $holds_callback ) ) {
		reset_state();
		$post = post_fixture( 20, 'eat' );
		check( true === \Courtneyr\Child\SingleOrder\holds_h1( true, $post ), 'SingleOrder holds_h1 preserves an incoming true value' );
		check( false === \Courtneyr\Child\SingleOrder\holds_h1( false, $post ), 'SingleOrder holds_h1 returns false when this is not an order' );
		$GLOBALS['cr_find_block'][20]['post-kinds-indieweb/eat-card'] = array( 'blockName' => 'post-kinds-indieweb/eat-card' );
		check( true === \Courtneyr\Child\SingleOrder\holds_h1( false, $post ), 'SingleOrder holds_h1 returns true for order singles' );
	}

	$old_repeat_callback = 'Courtneyr\\Child\\PostKinds\\suppress_featured_image_on_photo_single';
	$repeat_callback     = 'Courtneyr\\Child\\PostKinds\\suppress_repeated_featured_image';
	check( ! function_exists( $old_repeat_callback ), 'photo-only featured-image suppressor is removed' );
	check( function_exists( $repeat_callback ), 'shared repeated featured-image suppressor exists' );
	check( 10 === \has_filter( 'render_block', $repeat_callback ), 'shared repeated featured-image suppressor is hooked on render_block at priority 10' );
	check( 2 === \accepted_args_for( 'render_block', $repeat_callback ), 'shared repeated featured-image suppressor accepts 2 args' );

	reset_state();
	post_fixture( 30, 'photo' );
	check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/image' ) ), 'repeat suppressor ignores non-featured-image blocks' );

	reset_state();
	post_fixture( 31, 'photo', false );
	check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor leaves non-singular requests unchanged' );

	reset_state();
	$GLOBALS['cr_current_post'] = 32;
	$GLOBALS['cr_request']      = array( 'single' => true, 'queried_id' => 32 );
	check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor leaves missing posts unchanged' );

	reset_state();
	post_fixture( 33, 'note' );
	check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor leaves unlisted kinds unchanged' );

	reset_state();
	post_fixture( 34, 'photo' );
	$GLOBALS['cr_passwords'][34] = true;
	check( '' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor drops listed password-protected posts' );

	reset_state();
	post_fixture( 35, 'photo' );
	$GLOBALS['cr_kind_facts'][35] = array( 'featured_in_content' => true );
	if ( ! $GLOBALS['no_plugin'] ) {
		check( '' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor reads the featured_in_content fact' );
	} else {
		check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'no-plugin repeat suppressor cannot read facts and leaves the image' );
	}

	reset_state();
	post_fixture( 36, 'repost' );
	\add_filter( 'courtneyr_child_featured_repeats', static fn() => true, 1, 2 );
	check( '' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor drops listed posts when the repeat predicate returns exactly true' );

	reset_state();
	post_fixture( 37, 'photo' );
	\add_filter( 'courtneyr_child_featured_repeats', static fn() => 1, 1, 2 );
	check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor requires exactly true from the repeat predicate' );

	if ( ! $GLOBALS['no_plugin'] ) {
		reset_state();
		post_fixture( 38, 'photo' );
		check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor does not call the legacy featured_in_content API' );
		check( 0 === $GLOBALS['cr_featured_called'], 'legacy featured_in_content API was not called' );

		reset_state();
		post_fixture( 39, 'chicken' );
		$GLOBALS['cr_kind_facts'][39] = array( 'featured_in_content' => true );
		\add_filter(
			'courtneyr_child_featured_repeat_kinds',
			static fn() => array( 'photo', '', 'chicken', 'photo', 7, 'repost' ),
			1,
			1
		);
		check( '' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'repeat suppressor honors filtered listed kinds' );
		check( array( 'photo', 'chicken', 'repost' ) === $GLOBALS['cr_last_has_term_arg'], 'repeat suppressor sanitizes listed kinds to unique non-empty strings' );
	} else {
		reset_state();
		post_fixture( 40, 'photo' );
		check( '<figure>Image</figure>' === call_repeat_filter( '<figure>Image</figure>', block( 'core/post-featured-image' ) ), 'no-plugin repeat suppressor leaves listed posts unchanged when no repeat signal exists' );
	}

	if ( ! $GLOBALS['no_plugin'] ) {
		echo $GLOBALS['child_out'];
		if ( '' !== $GLOBALS['child_err'] ) {
			fwrite( STDERR, $GLOBALS['child_err'] );
		}
		check( 0 === $GLOBALS['child_code'], 'no-plugin child cases passed' );
	}

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
