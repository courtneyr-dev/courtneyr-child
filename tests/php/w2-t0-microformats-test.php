<?php
/**
 * `php tests/php/w2-t0-microformats-test.php`: checks W2-T0 shared-kind
 * microformats behavior without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	$no_plugin             = in_array( '--no-plugin', $argv, true );
	$GLOBALS['no_plugin'] = $no_plugin;
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
		$child_out  = stream_get_contents( $pipes[1] );
		$child_err  = stream_get_contents( $pipes[2] );
		$child_code = proc_close( $child );
		if ( 0 !== $child_code ) {
			echo $child_out;
			fwrite( STDERR, $child_err );
			exit( 1 );
		}
	}

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_W2_T0_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']        = array();
	$GLOBALS['cr_request']      = array();
	$GLOBALS['cr_posts']        = array();
	$GLOBALS['cr_terms']        = array();
	$GLOBALS['cr_dates']        = array();
	$GLOBALS['cr_permalinks']   = array();
	$GLOBALS['cr_passwords']    = array();
	$GLOBALS['cr_current_post'] = 0;

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
				$index++;
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
		return (string) ( $GLOBALS['cr_permalinks'][ $id ] ?? 'https://example.test/p/' . $id );
	}

	/** Password-protected flag. */
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post );
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}

	/** Escape HTML. */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape attr. */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape URL. */
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}

	/** Translate. */
	function __( $text ) {
		return $text;
	}

	/** Core HTML API helper. */
	function wp_has_noncharacters( $text ) {
		return false;
	}

	/** Core HTML API URI attributes. */
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace PKIW {
	if ( ! $GLOBALS['no_plugin'] ) {
		/** Untitled fallback. */
		function untitled_name( \WP_Post $post ): string {
			return $GLOBALS['cr_untitled_override'] ?? 'Stub kind label ' . $post->ID;
		}

		/** Kind-and-date fallback name. */
		function untitled_fallback_name( \WP_Post $post ): array {
			return array( 'name' => 'Like, ' . get_the_date( '', $post ) );
		}
	}
}

namespace Courtneyr\Child\Like {
	/** Like facts: a real cited title unless a case stores its own facts. */
	function like_facts( int $post_id ): array {
		if ( $GLOBALS['no_plugin'] ) {
			return array();
		}
		return $GLOBALS['cr_like_facts'][ $post_id ] ?? array( 'cited_title' => \PKIW\untitled_name( $GLOBALS['cr_posts'][ $post_id ] ) );
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

	require CR_W2_T0_THEME_DIR . '/inc/microformats.php';

	/** IndieBlocks stand-in: add p-name to post title blocks. */
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

namespace Courtneyr\Child\W2T0MicroformatsTests {

	use function Courtneyr\Child\Microformats\post_title_u_url;

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Reset request and post state. */
	function reset_state(): void {
		$GLOBALS['cr_request']                     = array();
		$GLOBALS['cr_posts']                       = array();
		$GLOBALS['cr_terms']                       = array();
		$GLOBALS['cr_dates']                       = array();
		$GLOBALS['cr_permalinks']                  = array();
		$GLOBALS['cr_passwords']                   = array();
		$GLOBALS['cr_current_post']                = 0;
		$GLOBALS['pkiw_stream_card_root_seen']     = array();
		$GLOBALS['cr_hooks']['the_title']          = array();
		unset( $GLOBALS['cr_untitled_override'], $GLOBALS['cr_like_facts'] );
	}

	/** Add a post fixture. */
	function post_fixture( int $id, string $kind, string $title = '', bool $single = true, bool $password = false ): void {
		$GLOBALS['cr_posts'][ $id ]        = new \WP_Post( $id, $title );
		$GLOBALS['cr_terms'][ $id ]        = array( $kind );
		$GLOBALS['cr_dates'][ $id ]        = 'October ' . $id . ', 2026';
		$GLOBALS['cr_passwords'][ $id ]    = $password;
		$GLOBALS['cr_current_post']        = $id;
		$GLOBALS['cr_request']             = $single ? array( 'single' => true, 'queried_id' => $id ) : array();
	}

	/** Simulate Core's post-title render and the hooked filters. */
	function render_title_pipeline( array $block ): string {
		$block   = apply_filters( 'render_block_data', $block, array(), null );
		$level   = (int) ( $block['attrs']['level'] ?? 2 );
		$title   = get_the_title();
		$content = '' === $title ? '' : sprintf(
			'<h%1$d class="wp-block-post-title %2$s">%3$s</h%1$d>',
			$level,
			esc_attr( (string) ( $block['attrs']['className'] ?? '' ) ),
			$title
		);
		return apply_filters( 'render_block_core/post-title', $content, $block );
	}

	/** Post title block fixture. */
	function title_block( int $level = 1, string $class = 'cr-flank cr-flank--blue single-post__title' ): array {
		return array(
			'blockName' => 'core/post-title',
			'attrs'    => array(
				'level'     => $level,
				'className' => $class,
			),
			'context'  => array( 'postId' => (int) $GLOBALS['cr_current_post'] ),
		);
	}

	/** Assert no temporary title callback remains. */
	function no_title_filter_left( string $message ): void {
		check( false === has_filter( 'the_title' ), $message );
	}

	reset_state();
	$GLOBALS['cr_posts'][9]                    = new \WP_Post( 9, '' );
	$GLOBALS['cr_terms'][9]                    = array( 'review' );
	$GLOBALS['pkiw_stream_card_root_seen'][11] = true;
	$GLOBALS['cr_posts'][11]                   = new \WP_Post( 11, '' );
	$GLOBALS['cr_terms'][11]                   = array( 'note' );
	$GLOBALS['cr_posts'][12]                   = new \WP_Post( 12, '' );
	$GLOBALS['cr_terms'][12]                   = array( 'watch' );
	check( array( 'post-9', 'h-review' ) === apply_filters( 'post_class', array( 'post-9', 'h-entry', 'hentry', 'h-review' ), array(), 9 ), 'review post_class drops h-entry and hentry on singular' );
	$GLOBALS['cr_request'] = array();
	check( array( 'post-9', 'h-review' ) === apply_filters( 'post_class', array( 'post-9', 'h-entry', 'hentry', 'h-review' ), array(), 9 ), 'review post_class drops h-entry and hentry off singular' );
	check( array( 'h-entry', 'h-review' ) === apply_filters( 'post_class', array( 'h-entry', 'h-review' ), array(), 12 ), 'watch h-review keeps h-entry' );
	check( array( 'post-11' ) === apply_filters( 'post_class', array( 'post-11', 'h-entry', 'hentry' ), array(), 11 ), 'stream-card root still drops h-entry and hentry' );
	check( array( 'post-9', 'h-review' ) === apply_filters( 'post_class', array( 'post-9', 'h-review' ), array(), 9 ), 'review with no h-entry classes is unchanged' );

	foreach ( array( 'review', 'reply', 'like', 'bookmark' ) as $index => $kind ) {
		reset_state();
		$id = 20 + $index;
		post_fixture( $id, $kind );
		$html = render_title_pipeline( title_block() );
		$expected_label = $GLOBALS['no_plugin'] ? 'Entry from October ' . $id . ', 2026' : 'Stub kind label ' . $id;
		check( 1 === substr_count( $html, '<h1' ), "$kind untitled single prints one H1" );
		check( false !== strpos( $html, $expected_label ), "$kind untitled single uses expected label" );
		check( false === strpos( $html, 'p-name' ), "$kind untitled single strips p-name" );
		check( false !== strpos( $html, 'cr-untitled-title' ), "$kind untitled single adds cr-untitled-title" );
		check( false === strpos( $html, '<a' ), "$kind untitled single title is not a link" );
		check( '' === get_the_title( $id ), "$kind title filter does not leak to later title reads" );
		no_title_filter_left( "$kind leaves no the_title filter behind" );
	}

	reset_state();
	post_fixture( 90, 'reply' );
	if ( $GLOBALS['no_plugin'] ) {
		$GLOBALS['cr_dates'][90] = '<img src=x onerror=alert(1)>';
	} else {
		$GLOBALS['cr_untitled_override'] = '<img src=x onerror=alert(1)>';
	}
	$html = render_title_pipeline( title_block() );
	check( false !== strpos( $html, '&lt;img' ), 'untitled single label is escaped in the H1' );
	check( false === strpos( $html, '<img' ), 'untitled single label does not render markup in the H1' );
	check( 1 === substr_count( $html, '<h1' ), 'untitled single escaped label still prints one H1' );
	no_title_filter_left( 'untitled single escaped label leaves no the_title filter behind' );

	$negative = array(
		'titled review'               => array( 40, 'review', 'Has title', true, 1, false, false ),
		'untitled note'               => array( 41, 'note', '', true, 1, false, false ),
		'untitled review non-single'  => array( 42, 'review', '', false, 1, false, false ),
		'level two review'            => array( 43, 'review', '', true, 2, false, false ),
		'wrong queried object review' => array( 44, 'review', '', true, 1, false, true ),
		'password review'             => array( 45, 'review', '', true, 1, true, false ),
	);
	foreach ( $negative as $label => $case ) {
		reset_state();
		list( $id, $kind, $title, $single, $level, $password, $wrong_query ) = $case;
		post_fixture( $id, $kind, $title, $single, $password );
		if ( $wrong_query ) {
			$GLOBALS['cr_request']['queried_id'] = $id + 1;
		}
		$before = '' === $title ? '' : '<h' . $level . ' class="wp-block-post-title cr-flank cr-flank--blue single-post__title">' . esc_html( $title ) . '</h' . $level . '>';
		$html   = render_title_pipeline( title_block( $level ) );
		check( $before === $html || ( '' !== $before && false !== strpos( $html, $title ) ), "$label leaves content unchanged" );
		no_title_filter_left( "$label leaves no the_title filter behind" );
	}

	reset_state();
	post_fixture( 46, 'review', '   ' );
	$html = render_title_pipeline( title_block() );
	$spaces_label = $GLOBALS['no_plugin'] ? 'Entry from October 46, 2026' : 'Stub kind label 46';
	check( false !== strpos( $html, $spaces_label ), 'spaces-only title counts as untitled' );
	no_title_filter_left( 'spaces-only title leaves no the_title filter behind' );

	reset_state();
	post_fixture( 60, 'note' );
	$block = title_block( 2, 'cr-u-url p-name' );
	$html  = post_title_u_url( '', $block );
	check( false !== strpos( $html, 'Entry from October 60, 2026' ), 'post_title_u_url still prints Entry from date' );
	check( false !== strpos( $html, 'class="u-url"' ), 'post_title_u_url keeps u-url link' );
	check( false === strpos( $html, 'p-name' ), 'post_title_u_url fallback has no p-name' );

	reset_state();
	post_fixture( 70, 'like' );
	check( true === apply_filters( 'pkiw_like_card_title_is_heading', false, $GLOBALS['cr_posts'][70] ), 'untitled like single can promote its card title heading' );
	post_fixture( 71, 'like', 'Titled like' );
	check( false === apply_filters( 'pkiw_like_card_title_is_heading', false, $GLOBALS['cr_posts'][71] ), 'titled like returns incoming heading flag' );
	post_fixture( 72, 'reply' );
	check( false === apply_filters( 'pkiw_like_card_title_is_heading', false, $GLOBALS['cr_posts'][72] ), 'untitled reply returns incoming heading flag' );
	post_fixture( 73, 'like', '', false );
	check( false === apply_filters( 'pkiw_like_card_title_is_heading', false, $GLOBALS['cr_posts'][73] ), 'non-singular like returns incoming false heading flag' );

	// F8 and F17: an untitled like whose card has no real cited title names its host in the clipping, so the H1 is its kind and date.
	reset_state();
	post_fixture( 74, 'like' );
	$GLOBALS['cr_untitled_override'] = 'zine.example.org';
	$GLOBALS['cr_like_facts'][74]    = array(
		'url'         => 'https://zine.example.org/issue-13',
		'host'        => 'zine.example.org',
		'cited_title' => '',
	);
	$html     = render_title_pipeline( title_block() );
	$f8_label = $GLOBALS['no_plugin'] ? 'Entry from October 74, 2026' : 'Like, October 74, 2026';
	check( 1 === substr_count( $html, '<h1' ), 'untitled url-only like prints one H1' );
	check( false !== strpos( $html, $f8_label ), 'untitled url-only like H1 is its kind and date' );
	check( false === strpos( $html, 'zine.example.org' ), 'untitled url-only like H1 is not the host' );
	check( false === strpos( $html, 'p-name' ), 'untitled url-only like H1 has no p-name' );
	no_title_filter_left( 'untitled url-only like leaves no the_title filter behind' );

	// F1: a real cited title stays the H1.
	reset_state();
	post_fixture( 75, 'like' );
	$GLOBALS['cr_untitled_override'] = 'Field notes on paper sites';
	$GLOBALS['cr_like_facts'][75]    = array(
		'url'         => 'https://notes.example.com/paper-sites',
		'host'        => 'notes.example.com',
		'cited_title' => 'Field notes on paper sites',
	);
	$html     = render_title_pipeline( title_block() );
	$f1_label = $GLOBALS['no_plugin'] ? 'Entry from October 75, 2026' : 'Field notes on paper sites';
	check( false !== strpos( $html, $f1_label ), 'untitled like with a cited title keeps it as the H1' );
	no_title_filter_left( 'untitled cited like leaves no the_title filter behind' );

	if ( $GLOBALS['no_plugin'] ) {
		reset_state();
		post_fixture( 80, 'review' );
		$html = render_title_pipeline( title_block() );
		check( false !== strpos( $html, 'Entry from October 80, 2026' ), 'no-plugin fallback label uses Entry from date' );
	}

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
