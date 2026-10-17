<?php
/**
 * `php tests/php/w4-t0-nav-test.php`: checks W4-T0 untitled names without
 * WordPress. courtneyr_child_untitled_name_shows_time drops the time for
 * exercise and sleep in the Previous/Next link names and the untitled single
 * H1, and the sleep Blog-card label reads Post Kinds' facts name.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_kinds'] = array(
		1 => 'exercise',
		2 => 'sleep',
		3 => 'weather',
		4 => 'craft',
		5 => 'note',
		6 => 'reply',
	);

	/** Stand-in post. */
	class WP_Post {
		/** @var int Post ID. */
		public $ID;

		/** @var string Post title. */
		public $post_title = '';

		/**
		 * Construct a post.
		 *
		 * @param int $id Post ID.
		 */
		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	/** Stand-in kind term. */
	class Kind_Term {
		/** @var string Term name. */
		public $name;

		/**
		 * Construct a term.
		 *
		 * @param string $name Term name.
		 */
		public function __construct( string $name ) {
			$this->name = $name;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

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

	function __( $text ) {
		return $text;
	}

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function get_the_terms( $post ) {
		$slug = $GLOBALS['cr_kinds'][ is_object( $post ) ? $post->ID : (int) $post ] ?? '';
		return '' === $slug ? false : array( new Kind_Term( ucfirst( $slug ) ) );
	}

	function has_term( $terms, $taxonomy, $post ) {
		$slug = $GLOBALS['cr_kinds'][ is_object( $post ) ? $post->ID : (int) $post ] ?? '';
		return in_array( $slug, (array) $terms, true );
	}

	function get_the_date( $format, $post ) {
		return 'August ' . ( is_object( $post ) ? $post->ID : (int) $post ) . ', 2026';
	}

	function get_the_time( $format, $post ) {
		return '3:05 pm';
	}

	function get_the_title( $post ) {
		return '';
	}

	function get_post( $id ) {
		return new WP_Post( (int) $id );
	}

	function post_password_required( $post = null ) {
		return false;
	}

	function get_adjacent_post() {
		return $GLOBALS['cr_adjacent'];
	}

	function esc_url( $url ) {
		return $url;
	}

	function get_permalink() {
		return 'https://example.test/p/';
	}

	function esc_attr( $text ) {
		return esc_html( $text );
	}

	require dirname( __DIR__, 2 ) . '/inc/nav.php';
	require dirname( __DIR__, 2 ) . '/inc/microformats.php';
}

namespace PKIW {
	/** Stub: Post Kinds' public-tier facts. */
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\W4T0NavTests {

	use function Courtneyr\Child\Microformats\untitled_entry_label;
	use function Courtneyr\Child\Microformats\untitled_single_label;
	use function Courtneyr\Child\Nav\kind_date_name;
	use function Courtneyr\Child\Nav\name_untitled;

	$failures = array();

	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'PASS ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	$with_time = array(
		3 => 'Weather, August 3, 2026 at 3:05 pm',
		4 => 'Craft, August 4, 2026 at 3:05 pm',
		5 => 'Note, August 5, 2026 at 3:05 pm',
		6 => 'Reply, August 6, 2026 at 3:05 pm',
	);
	$no_time   = array(
		1 => 'Exercise, August 1, 2026',
		2 => 'Sleep, August 2, 2026',
	);

	foreach ( $no_time as $id => $expected ) {
		check( kind_date_name( new \WP_Post( $id ) ) === $expected, "kind_date_name prints '$expected'" );
		check( false === strpos( kind_date_name( new \WP_Post( $id ) ), ' at ' ), "post $id name has no ' at '" );
	}
	foreach ( $with_time as $id => $expected ) {
		check( kind_date_name( new \WP_Post( $id ) ) === $expected, "kind_date_name keeps the time: '$expected'" );
	}

	// name_untitled(): the post-nav link name, through a stand-in adjacent post.
	foreach ( $no_time + $with_time as $id => $expected ) {
		$GLOBALS['cr_adjacent'] = new \WP_Post( $id );
		$out                    = name_untitled( '<span class="post-navigation-link__title">Next Post</span>', array( 'attrs' => array( 'type' => 'next' ) ) );
		check( '<span class="post-navigation-link__title">' . $expected . '</span>' === $out, "name_untitled names post $id '$expected'" );
	}

	// The filter is the one switch: a later callback can turn the time back on.
	add_filter(
		'courtneyr_child_untitled_name_shows_time',
		static function ( bool $show, \WP_Post $post ): bool {
			return 2 === $post->ID ? true : $show;
		},
		20,
		2
	);
	check( kind_date_name( new \WP_Post( 2 ) ) === 'Sleep, August 2, 2026 at 3:05 pm', 'a later filter callback can show the time for sleep' );
	check( kind_date_name( new \WP_Post( 1 ) ) === 'Exercise, August 1, 2026', 'the same callback leaves exercise without it' );
	$GLOBALS['cr_hooks']['courtneyr_child_untitled_name_shows_time'][20] = array();

	// The untitled single H1 label.
	check( in_array( 'weather', \Courtneyr\Child\Microformats\UNTITLED_SINGLE_KINDS, true ), 'weather joins the untitled single H1 kinds' );
	foreach ( array( 'craft', 'exercise', 'sleep' ) as $kind ) {
		check( in_array( $kind, \Courtneyr\Child\Microformats\UNTITLED_SINGLE_KINDS, true ), "$kind joins the untitled single H1 kinds" );
	}
	check( in_array( 'mood', \Courtneyr\Child\Microformats\UNTITLED_SINGLE_KINDS, true ), 'mood joins the untitled single H1 kinds (decision 248, Single: one H1)' );
	check( false === in_array( 'mood', \Courtneyr\Child\Microformats\KIND_DATE_NAME_KINDS, true ), 'mood names itself through PKIW untitled_name(), date only' );
	check( false === in_array( 'note', \Courtneyr\Child\Microformats\UNTITLED_SINGLE_KINDS, true ), 'note stays out of the untitled single H1 kinds' );
	foreach ( array( 1, 2, 3, 4, 6 ) as $id ) {
		check( untitled_single_label( $id ) === ( $no_time + $with_time )[ $id ], "single H1 label for post $id matches its post-nav name" );
	}

	// The Blog-card label.
	$GLOBALS['cr_facts'] = array( 2 => array( 'name' => 'Sleep, August 2, 2026' ) );
	check( untitled_entry_label( 2 ) === 'Sleep, August 2, 2026', 'sleep Blog card reads the facts name' );
	check( untitled_entry_label( 5 ) === 'Entry from August 5, 2026', 'a note keeps Entry from <date>' );
	check( untitled_entry_label( 1 ) === 'Entry from August 1, 2026', 'exercise keeps Entry from <date> on cards' );
	$GLOBALS['cr_facts'] = array( 2 => array( 'name' => '' ) );
	check( untitled_entry_label( 2 ) === 'Entry from August 2, 2026', 'sleep with an empty facts name falls back to Entry from <date>' );
	$GLOBALS['cr_facts'] = array();
	check( untitled_entry_label( 2 ) === 'Entry from August 2, 2026', 'sleep with no facts falls back to Entry from <date>' );

	// The sleep card keeps u-url and prints no p-name.
	$GLOBALS['cr_facts'] = array( 2 => array( 'name' => 'Sleep, August 2, 2026' ) );
	$html                = \Courtneyr\Child\Microformats\post_title_u_url(
		'',
		array(
			'attrs'   => array( 'className' => 'cr-u-url p-name' ),
			'context' => array( 'postId' => 2 ),
		)
	);
	check( false !== strpos( $html, '<a class="u-url" href="https://example.test/p/">Sleep, August 2, 2026</a>' ), 'sleep Blog card link has u-url and the facts name' );
	check( false === strpos( $html, 'p-name' ), 'sleep Blog card carries no p-name' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
