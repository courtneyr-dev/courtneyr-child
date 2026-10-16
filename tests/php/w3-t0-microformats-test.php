<?php
/**
 * `php tests/php/w3-t0-microformats-test.php`: checks W3-T0 untitled single
 * H1 kinds and cited-title heading detection without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	$no_plugin              = in_array( '--no-plugin', $argv, true );
	$GLOBALS['no_plugin']  = $no_plugin;
	$GLOBALS['child_out']  = '';
	$GLOBALS['child_err']  = '';
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

	$GLOBALS['cr_hooks']        = array();
	$GLOBALS['cr_request']      = array();
	$GLOBALS['cr_posts']        = array();
	$GLOBALS['cr_terms']        = array();
	$GLOBALS['cr_dates']        = array();
	$GLOBALS['cr_passwords']    = array();
	$GLOBALS['cr_current_post'] = 0;
	$GLOBALS['cr_kind_facts']   = array();

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

	/** Register a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Register an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/** Remove a filter callback. */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['cr_hooks'][ $hook ][ (int) $priority ] ) ) {
			return false;
		}
		$before = count( $GLOBALS['cr_hooks'][ $hook ][ (int) $priority ] );
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ] = array_values(
			array_filter(
				$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ],
				static function ( array $registered ) use ( $callback ): bool {
					return $registered[0] !== $callback;
				}
			)
		);
		return count( $GLOBALS['cr_hooks'][ $hook ][ (int) $priority ] ) !== $before;
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

	/** Run filters with priority order and live removal. */
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = array_keys( $GLOBALS['cr_hooks'][ $hook ] ?? array() );
		sort( $priorities );
		foreach ( $priorities as $priority ) {
			$index = 0;
			while ( isset( $GLOBALS['cr_hooks'][ $hook ][ $priority ][ $index ] ) ) {
				$registered = $GLOBALS['cr_hooks'][ $hook ][ $priority ][ $index ];
				$all        = array_merge( array( $value ), $args );
				$value      = call_user_func_array( $registered[0], array_slice( $all, 0, $registered[1] ) );
				++$index;
			}
		}
		return $value;
	}

	/** Singular request flag. */
	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Current post ID. */
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}

	/** Queried post ID. */
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	/** Return raw post fields. */
	function get_post_field( $field, $post_id, $context = 'display' ) {
		$post = $GLOBALS['cr_posts'][ (int) $post_id ] ?? null;
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		return 'post_title' === $field ? $post->post_title : '';
	}

	/** Post title, filtered like Core. */
	function get_the_title( $post = 0 ) {
		$id    = $post instanceof WP_Post ? $post->ID : ( 0 === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		$title = (string) ( $GLOBALS['cr_posts'][ $id ]->post_title ?? '' );
		return apply_filters( 'the_title', $title, $id );
	}

	/** Term membership. */
	function has_term( $term, $taxonomy, $post ) {
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

	/** Return a post. */
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}

	/** Display date. */
	function get_the_date( $format = '', $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return (string) ( $GLOBALS['cr_dates'][ $id ] ?? 'October 11, 2026' );
	}

	/** Permalink. */
	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : ( 0 === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return 'https://example.test/p/' . $id;
	}

	/** Password-protected flag. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $text ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $text ) ); }
	function __( $text ) { return $text; }
	function get_the_author_meta() { return 'Courtney Robertson'; }
	function get_site_icon_url() { return ''; }
	function get_bloginfo() { return 'courtneyr.dev'; }
	function home_url( $path = '/' ) { return 'https://example.test' . $path; }

	function wp_has_noncharacters( $text ) { return false; }
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace PKIW {
	if ( ! $GLOBALS['no_plugin'] ) {
		/** Untitled fallback. */
		function untitled_name( \WP_Post $post ): string {
			return 'Stub kind label ' . $post->ID;
		}

		/** Kind facts. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_kind_facts'][ $post_id ] ?? array();
		}
	}
}

namespace Courtneyr\Child\Like {
	/** Like facts stub. */
	function like_facts( int $post_id ): array {
		return $GLOBALS['cr_kind_facts'][ $post_id ] ?? array( 'cited_title' => 'Stub kind label ' . $post_id );
	}
}

namespace {
	$core  = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api   = $core . '/wp-includes/html-api/';
	$files = array(
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-token.php',
		'class-wp-html-tag-processor.php',
	);
	foreach ( $files as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}

	require CR_W3_T0_THEME_DIR . '/inc/microformats.php';

	add_filter(
		'render_block_core/post-title',
		static function ( string $content ): string {
			$p = new WP_HTML_Tag_Processor( $content );
			if ( $p->next_tag( array( 'class_name' => 'wp-block-post-title' ) ) ) {
				$p->add_class( 'p-name' );
				return $p->get_updated_html();
			}
			return $content;
		},
		11,
		1
	);
}

namespace Courtneyr\Child\W3T0MicroformatsTests {

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
		$GLOBALS['cr_request']      = array();
		$GLOBALS['cr_posts']        = array();
		$GLOBALS['cr_terms']        = array();
		$GLOBALS['cr_dates']        = array();
		$GLOBALS['cr_passwords']    = array();
		$GLOBALS['cr_current_post'] = 0;
		$GLOBALS['cr_kind_facts']   = array();
		$GLOBALS['cr_hooks']['the_title'] = array();
		unset( $GLOBALS['cr_untitled_override'] );
	}

	/** Add a post fixture. */
	function post_fixture( int $id, string $kind, string $title = '', bool $single = true ): void {
		$GLOBALS['cr_posts'][ $id ]     = new \WP_Post( $id, $title );
		$GLOBALS['cr_terms'][ $id ]     = array( $kind );
		$GLOBALS['cr_dates'][ $id ]     = 'October ' . $id . ', 2026';
		$GLOBALS['cr_current_post']     = $id;
		$GLOBALS['cr_request']          = $single ? array( 'single' => true, 'queried_id' => $id ) : array();
	}

	/** Simulate Core's post-title render and the hooked filters. */
	function render_title_pipeline( array $block ): string {
		$block   = \apply_filters( 'render_block_data', $block, array(), null );
		$level   = (int) ( $block['attrs']['level'] ?? 2 );
		$title   = \get_the_title();
		$content = '' === $title ? '' : sprintf(
			'<h%1$d class="wp-block-post-title %2$s">%3$s</h%1$d>',
			$level,
			\esc_attr( (string) ( $block['attrs']['className'] ?? '' ) ),
			$title
		);
		return \apply_filters( 'render_block_core/post-title', $content, $block );
	}

	/** Post title block fixture. */
	function title_block(): array {
		return array(
			'blockName' => 'core/post-title',
			'attrs'    => array(
				'level'     => 1,
				'className' => 'cr-flank cr-flank--blue single-post__title',
			),
			'context'  => array( 'postId' => (int) $GLOBALS['cr_current_post'] ),
		);
	}

	/** Call cited_title_is_h1 if it exists. */
	function call_cited_title_is_h1( int $post_id ) {
		return function_exists( '\\Courtneyr\\Child\\Microformats\\cited_title_is_h1' )
			? \Courtneyr\Child\Microformats\cited_title_is_h1( $post_id )
			: '__missing__';
	}

	check( array( 'review', 'reply', 'like', 'bookmark', 'photo', 'acquisition' ) === array_slice( \Courtneyr\Child\Microformats\UNTITLED_SINGLE_KINDS, 0, 6 ), 'UNTITLED_SINGLE_KINDS starts with the frozen W3 list in order' );

	foreach ( array( 'photo', 'acquisition' ) as $index => $kind ) {
		reset_state();
		$id = 50 + $index;
		post_fixture( $id, $kind );
		$html           = render_title_pipeline( title_block() );
		$expected_label = $GLOBALS['no_plugin'] ? 'Entry from October ' . $id . ', 2026' : 'Stub kind label ' . $id;
		check( 1 === substr_count( $html, '<h1' ), "$kind untitled single prints one H1" );
		check( false !== strpos( $html, $expected_label ), "$kind untitled single uses the visible fallback label" );
		check( false === strpos( $html, 'p-name' ), "$kind untitled single strips p-name from the fallback H1" );
		check( false !== strpos( $html, 'cr-untitled-title' ), "$kind untitled single adds cr-untitled-title" );
	}

	check( function_exists( '\\Courtneyr\\Child\\Microformats\\cited_title_is_h1' ), 'cited_title_is_h1 exists' );

	reset_state();
	check( false === call_cited_title_is_h1( 90 ), 'cited_title_is_h1 returns false when get_post is not a post' );

	reset_state();
	post_fixture( 91, 'like', '   ' );
	check( true === call_cited_title_is_h1( 91 ), 'cited_title_is_h1 returns true for raw empty titles' );

	reset_state();
	post_fixture( 100, 'bookmark', '<span> </span>' );
	check( true === call_cited_title_is_h1( 100 ), 'cited_title_is_h1 returns true for tags-only titles' );

	reset_state();
	post_fixture( 92, 'bookmark', 'Field Notes on Paper Sites' );
	$GLOBALS['cr_kind_facts'][92] = array( 'cited_title' => ' field notes on paper sites ' );
	if ( ! $GLOBALS['no_plugin'] ) {
		check( true === call_cited_title_is_h1( 92 ), 'cited_title_is_h1 matches cited titles case-insensitively after trimming' );
	}

	reset_state();
	post_fixture( 93, 'photo', 'Photo title' );
	$GLOBALS['cr_kind_facts'][93] = array( 'cited_title' => '' );
	check( false === call_cited_title_is_h1( 93 ), 'cited_title_is_h1 returns false for empty cited titles on titled posts' );

	reset_state();
	post_fixture( 94, 'acquisition', 'Local title' );
	$GLOBALS['cr_kind_facts'][94] = array( 'cited_title' => 'Different title' );
	check( false === call_cited_title_is_h1( 94 ), 'cited_title_is_h1 returns false when the cited title differs' );

	if ( $GLOBALS['no_plugin'] ) {
		reset_state();
		post_fixture( 95, 'bookmark', 'Local title' );
		check( false === call_cited_title_is_h1( 95 ), 'no-plugin cited_title_is_h1 returns false for titled posts without facts' );
	}

	if ( ! $GLOBALS['no_plugin'] ) {
		reset_state();
		post_fixture( 96, 'repost', 'quiet.example.com' );
		$GLOBALS['cr_kind_facts'][96] = array( 'cited_title' => '', 'host' => 'quiet.example.com' );
		check( true === call_cited_title_is_h1( 96 ), 'host fallback true when cited title is empty and host matches' );

		reset_state();
		post_fixture( 97, 'repost', 'other.example.com' );
		$GLOBALS['cr_kind_facts'][97] = array( 'cited_title' => '', 'host' => 'quiet.example.com' );
		check( false === call_cited_title_is_h1( 97 ), 'host fallback false when cited title is empty and host differs' );

		reset_state();
		post_fixture( 98, 'repost', 'quiet.example.com' );
		$GLOBALS['cr_kind_facts'][98] = array( 'cited_title' => 'Something else', 'host' => 'quiet.example.com' );
		check( false === call_cited_title_is_h1( 98 ), 'host ignored when a cited title exists' );

		reset_state();
		post_fixture( 99, 'repost', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][99] = array( 'cited_title' => '<span>Quiet Web</span>' );
		check( true === call_cited_title_is_h1( 99 ), 'tag normalization matches cited title markup' );

		reset_state();
		post_fixture( 101, 'repost', '<em>Quiet</em> Web' );
		$GLOBALS['cr_kind_facts'][101] = array( 'cited_title' => 'quiet web' );
		check( true === call_cited_title_is_h1( 101 ), 'tag normalization matches post title markup' );

		reset_state();
		post_fixture( 102, 'repost', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][102] = array( 'cited_title' => 'Quiet Web' );
		check( '' === render_title_pipeline( title_block() ), 'suppression drops repost single title when cited title is the H1' );

		reset_state();
		post_fixture( 103, 'repost', 'Local Title' );
		$GLOBALS['cr_kind_facts'][103] = array( 'cited_title' => 'Cited Title' );
		$html = render_title_pipeline( title_block() );
		check( 1 === substr_count( $html, '<h1' ) && false !== strpos( $html, 'Local Title' ), 'suppression keeps repost title when cited title differs' );

		reset_state();
		post_fixture( 104, 'repost', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][104] = array( 'cited_title' => 'Quiet Web' );
		$level_2 = title_block();
		$level_2['attrs']['level'] = 2;
		$html = render_title_pipeline( $level_2 );
		check( 1 === substr_count( $html, '<h2' ) && false !== strpos( $html, 'Quiet Web' ), 'suppression keeps level-2 repost title blocks' );

		reset_state();
		post_fixture( 105, 'repost', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][105] = array( 'cited_title' => 'Quiet Web' );
		$GLOBALS['cr_passwords'][105] = true;
		$html = render_title_pipeline( title_block() );
		check( 1 === substr_count( $html, '<h1' ) && false !== strpos( $html, 'Quiet Web' ), 'suppression keeps password-required repost titles' );

		reset_state();
		post_fixture( 106, 'bookmark', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][106] = array( 'cited_title' => 'Quiet Web' );
		$html = render_title_pipeline( title_block() );
		check( 1 === substr_count( $html, '<h1' ) && false !== strpos( $html, 'Quiet Web' ), 'suppression keeps non-repost titles' );

		reset_state();
		post_fixture( 107, 'repost', 'Quiet Web' );
		$GLOBALS['cr_kind_facts'][107] = array( 'cited_title' => 'Quiet Web' );
		$GLOBALS['cr_request']['queried_id'] = 999;
		$html = render_title_pipeline( title_block() );
		check( 1 === substr_count( $html, '<h1' ) && false !== strpos( $html, 'Quiet Web' ), 'suppression keeps repost title when post is not queried object' );

		reset_state();
		post_fixture( 108, 'repost', '' );
		$cr_u_url = title_block();
		$cr_u_url['attrs']['className'] .= ' cr-u-url';
		check( '' === render_title_pipeline( $cr_u_url ), 'suppression drops untitled cr-u-url repost fallback title after pipeline' );

		check( 30 === \has_filter( 'render_block_core/post-title', 'Courtneyr\\Child\\Microformats\\suppress_cited_title_post_title' ), 'hook order registers suppress_cited_title_post_title at priority 30' );

		echo $GLOBALS['child_out'];
		if ( '' !== $GLOBALS['child_err'] ) {
			fwrite( STDERR, $GLOBALS['child_err'] );
		}
		check( 0 === $GLOBALS['child_code'], 'no-plugin child cases passed' );
	}

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
