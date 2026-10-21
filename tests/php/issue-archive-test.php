<?php
/**
 * `php tests/php/issue-archive-test.php`: standalone issue archive checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_posts'] = array();
	$GLOBALS['cr_terms'] = array();
	$GLOBALS['cr_facts'] = array();
	$GLOBALS['cr_request'] = array();
	$GLOBALS['cr_current_post'] = 0;
	$GLOBALS['cr_style_calls'] = array();

	class WP_Post {
		public $ID;
		public $post_title;
		public function __construct( int $id, string $title ) {
			$this->ID = $id;
			$this->post_title = $title;
		}
	}

	class WP_Block {
		public $context;
		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$value = call_user_func_array( $registered[0], array_slice( array_merge( array( $value ), $args ), 0, $registered[1] ) );
			}
		}
		return $value;
	}
	function do_action( $hook ) {
		apply_filters( $hook, null );
	}
	function register_block_style( $block, $style ) {
		$GLOBALS['cr_style_calls']['block'][] = array( $block, $style );
		return true;
	}
	function wp_register_style( $handle, $src, $deps, $version ) {
		$GLOBALS['cr_style_calls']['register'][] = array( $handle, $src, $deps, $version );
		$GLOBALS['cr_registered'][ $handle ] = true;
		return true;
	}
	function wp_style_add_data( $handle, $key, $value ) {
		$GLOBALS['cr_style_calls']['data'][ $handle ][ $key ] = $value;
		return true;
	}
	function wp_style_is( $handle, $status ) {
		return 'registered' === $status && ! empty( $GLOBALS['cr_registered'][ $handle ] );
	}
	function wp_enqueue_style( $handle, $src, $deps, $version ) {
		$GLOBALS['cr_style_calls']['enqueue'][ $handle ] = array( $src, $deps, $version );
	}
	function is_tax( $taxonomy = '', $term = '' ) {
		return 'kind' === $taxonomy && 'issue' === $term && ! empty( $GLOBALS['cr_request']['archive'] );
	}
	function is_singular( $type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $type || 'post' === $type );
	}
	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried'] ?? 0 );
	}
	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}
	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : ( null === $post ? get_the_ID() : (int) $post );
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'issue' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}
	function post_password_required( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_passwords'][ $id ] );
	}
	function get_the_title( $post = 0 ) {
		$post = get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}
	function get_permalink( $post = 0 ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://example.test/issue-' . $id . '/';
	}
	function get_the_date( $format = '', $post = null ) {
		return 'c' === $format ? '2026-10-21T10:00:00+00:00' : 'October 21, 2026';
	}
	function __( $text ) {
		return $text;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function wp_has_noncharacters( $text ) {
		return false;
	}
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_request']['stream'] );
	}
}

namespace Courtneyr\Child\Stamps {
	function seeded_paper( int $post_id, array $papers ): string {
		return $papers[ $post_id % count( $papers ) ];
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
	}
	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card">Author ' . $post->ID . '</span>';
	}
	class Issue_Archive {
		public static function cite( array $facts, \WP_Post $post ): string {
			return empty( $facts['repo_label'] ) ? '' : '<span class="u-in-reply-to h-cite"><span class="p-name">' . \esc_html( $facts['repo_label'] ) . '</span></span>';
		}
	}
	class Grouped_Archive {
		public static function current_page_ids(): array {
			return (array) ( $GLOBALS['cr_page_ids'] ?? array() );
		}
	}
}

namespace {
	$core = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api/';
	foreach ( array( 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}
	require dirname( __DIR__, 2 ) . '/inc/issue.php';

	use Courtneyr\Child\Issue;

	$failures = array();

	function cr_issue_archive_check( string $name, callable $test ): void {
		global $failures;
		$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
		try {
			$ok = (bool) $test();
		} catch ( Throwable $error ) {
			$ok = false;
			$name .= ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	function cr_issue_post( int $id, string $title, bool $issue = true ): WP_Post {
		$post = new WP_Post( $id, $title );
		$GLOBALS['cr_posts'][ $id ] = $post;
		$GLOBALS['cr_terms'][ $id ] = $issue;
		$GLOBALS['cr_current_post'] = $id;
		return $post;
	}

	function cr_issue_facts( string $name = 'Login fails', string $status = 'open', string $number = '#42' ): array {
		return array(
			'name' => $name,
			'name_is_title' => true,
			'kind_label' => 'Issue',
			'repo_name' => 'courtneyr/site',
			'repo_url' => 'https://github.com/courtneyr/site',
			'repo_label' => 'courtneyr/site',
			'number_text' => $number,
			'status' => $status,
			'status_label' => ucfirst( $status ),
			'date_iso' => '2026-10-21T10:00:00+00:00',
			'date_display' => 'October 21, 2026',
			'has_facts' => true,
		);
	}

	function cr_issue_ticket( int $id, string $html = '<article><h4 class="pk-title">Old</h4></article>', string $classes = 'is-style-cr-claim-ticket' ): string {
		return Issue\ticket( $html, array( 'attrs' => array( 'className' => $classes ) ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_issue_archive_check(
		'style and block variation register exactly once with the frozen dependencies',
		static function (): bool {
			do_action( 'init' );
			do_action( 'init' );
			$issue_calls = array_values(
				array_filter(
					$GLOBALS['cr_style_calls']['register'] ?? array(),
					static fn( array $call ): bool => 'courtneyr-issue' === $call[0]
				)
			);
			$nav_calls   = array_filter(
				$GLOBALS['cr_style_calls']['register'] ?? array(),
				static fn( array $call ): bool => 'courtneyr-nav' === $call[0]
			);
			return 1 === count( $issue_calls )
				&& 1 === count( $nav_calls )
				&& 1 === count( $GLOBALS['cr_style_calls']['block'] ?? array() )
				&& array( 'courtneyr-paper', 'courtneyr-nav' ) === ( $issue_calls[0][2] ?? null )
				&& 'courtneyr-issue' === ( $GLOBALS['cr_style_calls']['block'][0][1]['style_handle'] ?? '' )
				&& 'cr-claim-ticket' === ( $GLOBALS['cr_style_calls']['block'][0][1]['name'] ?? '' );
		}
	);

	cr_issue_archive_check(
		'paper selection is stable per ID and independent of status facts',
		static function (): bool {
			$post = cr_issue_post( 10, 'Stable paper' );
			$one  = Issue\paper_for( $post );
			$GLOBALS['cr_facts'][10] = cr_issue_facts( 'Stable paper', 'closed' );
			return $one === Issue\paper_for( $post ) && in_array( $one, array_keys( Issue\PAPERS ), true );
		}
	);

	cr_issue_archive_check(
		'ticket keeps only its title link, number and hidden properties',
		static function (): bool {
			cr_issue_post( 20, 'Login fails' );
			$GLOBALS['cr_facts'][20] = cr_issue_facts();
			$out = cr_issue_ticket( 20, '<article><h3 class="pk-title p-name">Old</h3><p>Excerpt</p></article>' );
			return str_contains( $out, '<h3 class="cr-claim-ticket__title">' )
				&& 1 === substr_count( $out, '<a ' )
				&& str_contains( $out, 'id="cr-claim-number-20">#42</span>' )
				&& str_contains( $out, 'class="dt-published"' )
				&& str_contains( $out, 'class="p-author h-card"' )
				&& str_contains( $out, 'class="u-in-reply-to h-cite"' )
				&& ! str_contains( $out, 'cr-claim-ticket__repo' )
				&& ! str_contains( $out, 'cr-claim-ticket__status' )
				&& ! str_contains( $out, 'October 21, 2026' )
				&& ! str_contains( $out, 'Excerpt' );
		}
	);

	cr_issue_archive_check(
		'non-issue, protected and classless non-archive cards remain byte-identical',
		static function (): bool {
			$html = '<article><h4 class="pk-title">Original</h4></article>';
			cr_issue_post( 30, 'Not issue', false );
			$non_issue = cr_issue_ticket( 30, $html );
			cr_issue_post( 31, 'Protected' );
			$GLOBALS['cr_passwords'][31] = true;
			$protected = cr_issue_ticket( 31, $html );
			cr_issue_post( 32, 'Classless' );
			$classless = cr_issue_ticket( 32, $html, '' );
			return $html === $non_issue && $html === $protected && $html === $classless;
		}
	);

	cr_issue_archive_check(
		'facts-empty fallback prints one stored-title link and no visible facts',
		static function (): bool {
			cr_issue_post( 40, 'Stored title' );
			$GLOBALS['cr_facts'][40] = array();
			$out = cr_issue_ticket( 40 );
			return str_contains( $out, '>Stored title</a>' )
				&& str_contains( $out, 'class="u-url p-name"' )
				&& 1 === substr_count( $out, '<a ' )
				&& ! str_contains( $out, 'cr-claim-ticket__number' );
		}
	);

	cr_issue_archive_check(
		'duplicate titles gain ticket, number and repository labels in one post-template pass',
		static function (): bool {
			cr_issue_post( 50, 'Same title' );
			cr_issue_post( 51, 'Same title' );
			$GLOBALS['cr_facts'][50] = cr_issue_facts( 'Same title', 'open', '#50' );
			$GLOBALS['cr_facts'][51] = cr_issue_facts( 'Same title', 'closed', '#51' );
			$GLOBALS['cr_page_ids'] = array( 50, 51 );
			$ticket = cr_issue_ticket( 50 );
			$rack   = '<section><h2 id="pkiw-group-site" class="pkiw-group__heading">courtneyr/site</h2>' . $ticket . '</section>';
			$out    = Issue\label_duplicates( $rack, array( 'attrs' => array( 'className' => 'cr-issue-rack' ) ) );
			return str_contains( $ticket, 'data-cr-claim-dup="cr-claim-number-50"' )
				&& str_contains( $out, 'aria-labelledby="cr-claim-title-50 cr-claim-number-50 pkiw-group-site"' )
				&& ! str_contains( $out, 'data-cr-claim-dup' );
		}
	);

	cr_issue_archive_check(
		'body, editor and unused-CSS registries add their issue values once',
		static function (): bool {
			$GLOBALS['cr_request'] = array( 'archive' => true );
			$body = Issue\body_class( Issue\body_class( array() ) );
			$kinds = Issue\register_editor_parity( Issue\register_editor_parity( array() ) );
			$css = Issue\perfmatters_rucss_exclusions( array( 'keep.css', 'cr-issue.css' ) );
			return 1 === count( array_keys( $body, 'cr-issue-archive', true ) )
				&& 1 === count( array_keys( $kinds['issue']['classes'], 'cr-issue-board', true ) )
				&& array( 'keep.css', 'cr-issue.css' ) === $css;
		}
	);

	cr_issue_archive_check(
		'a synthetic name carries no p-name',
		static function (): bool {
			cr_issue_post( 60, '' );
			$facts = cr_issue_facts( 'Issue, October 21' );
			$facts['name_is_title'] = false;
			$GLOBALS['cr_facts'][60] = $facts;
			$synthetic = cr_issue_ticket( 60 );
			cr_issue_post( 61, 'Real title' );
			$GLOBALS['cr_facts'][61] = cr_issue_facts( 'Real title' );
			$titled = cr_issue_ticket( 61 );
			return ! str_contains( $synthetic, 'u-url p-name' ) && str_contains( $synthetic, 'class="u-url"' ) && str_contains( $titled, 'u-url p-name' );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
