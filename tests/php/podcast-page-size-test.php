<?php
/**
 * `php tests/php/podcast-page-size-test.php`: checks the podcast archive page
 * size exception without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_hooks'] = array();

	class WP_Query {
		private $kind;
		private $vars;
		private $main;
		private $feed;

		public function __construct( string $kind, array $vars = array(), bool $main = true, bool $feed = false ) {
			$this->kind = $kind;
			$this->vars = $vars;
			$this->main = $main;
			$this->feed = $feed;
		}

		public function is_tax( $taxonomy, $term = '' ): bool {
			return 'kind' === $taxonomy && '' !== $this->kind && ( '' === $term || in_array( $this->kind, (array) $term, true ) );
		}

		public function get( $var ) {
			return $this->vars[ $var ] ?? '';
		}

		public function set( $var, $value ): void {
			$this->vars[ $var ] = $value;
		}

		public function is_main_query(): bool {
			return $this->main;
		}

		public function is_feed(): bool {
			return $this->feed;
		}

		public function is_home(): bool {
			return false;
		}

		public function is_archive(): bool {
			return '' !== $this->kind;
		}

		public function is_search(): bool {
			return false;
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

	function do_action( $hook, ...$args ): void {
		$priorities = array_keys( $GLOBALS['cr_hooks'][ $hook ] ?? array() );
		sort( $priorities );
		foreach ( $priorities as $priority ) {
			foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] as $registered ) {
				call_user_func_array( $registered[0], array_slice( $args, 0, $registered[1] ) );
			}
		}
	}

	function is_admin() {
		return false;
	}

	function wp_is_rest_endpoint() {
		return false;
	}

	require dirname( __DIR__, 2 ) . '/inc/theme-supports.php';

	add_filter(
		'courtneyr_child_kind_archive_page_sizes',
		static function (): array {
			return array( 'listen' => 5, 'watch' => 5 );
		}
	);
	add_action(
		'pre_get_posts',
		static function ( WP_Query $query ): void {
			if ( $query->is_tax( 'kind', 'listen' ) && 'podcast' === $query->get( 'pkiw_listen_type' ) ) {
				$query->set( 'posts_per_page', 12 );
			}
		},
		10,
		1
	);
}

namespace Courtneyr\Child\PodcastPageSizeTests {

	use function Courtneyr\Child\ThemeSupports\kind_archive_page_size;
	use function Courtneyr\Child\ThemeSupports\size_kind_archive;

	$failures = array();

	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	$podcast = new \WP_Query( 'listen', array( 'pkiw_listen_type' => 'podcast' ) );
	$listen  = new \WP_Query( 'listen' );
	$watch   = new \WP_Query( 'watch' );
	$note    = new \WP_Query( 'note' );
	check( 0 === kind_archive_page_size( $podcast ), 'podcast listen view has no theme page size' );
	check( 5 === kind_archive_page_size( $listen ), 'plain listen keeps its five-item shelf size' );
	check( 5 === kind_archive_page_size( $watch ), 'watch keeps its five-item shelf size' );
	check( 0 === kind_archive_page_size( $note ), 'unregistered kind has no theme page size' );

	do_action( 'pre_get_posts', $podcast );
	do_action( 'pre_get_posts', $listen );
	do_action( 'pre_get_posts', $watch );
	check( 12 === $podcast->get( 'posts_per_page' ), 'priority-10 plugin size survives priority-11 theme callback' );
	check( 5 === $listen->get( 'posts_per_page' ), 'plain listen pipeline ends at five' );
	check( 5 === $watch->get( 'posts_per_page' ), 'watch pipeline ends at five' );

	foreach ( array( array(), array( 'pkiw_listen_type' => 'podcast' ) ) as $vars ) {
		$feed = new \WP_Query( 'listen', $vars, true, true );
		$feed->set( 'posts_per_page', 19 );
		size_kind_archive( $feed );
		check( 19 === $feed->get( 'posts_per_page' ), 'listen feed is not resized by size_kind_archive' );
	}

	$no_plugin = new \WP_Query( 'listen' );
	check( 5 === kind_archive_page_size( $no_plugin ), 'without a plugin query var, registry behavior is unchanged' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
