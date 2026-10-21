<?php
/**
 * `php tests/php/issue-stream-test.php`: standalone issue Stream checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_posts'] = array();
	$GLOBALS['cr_terms'] = array();
	$GLOBALS['cr_facts'] = array();
	$GLOBALS['cr_current_post'] = 0;
	$GLOBALS['cr_stream'] = false;

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
	class WP_Term {
		public $name;
		public function __construct( string $name ) {
			$this->name = $name;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $args );
		return true;
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
	function get_term_by( $field, $value, $taxonomy ) {
		return 'slug' === $field && 'issue' === $value && 'kind' === $taxonomy ? new WP_Term( 'Issue' ) : false;
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
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream'];
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
	}
	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card">Author</span>';
	}
	class Issue_Archive {
		public static function cite( array $facts, \WP_Post $post ): string {
			return empty( $facts['repo_label'] ) ? '' : '<span class="u-in-reply-to h-cite">Repository</span>';
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
	require dirname( __DIR__, 2 ) . '/inc/stream-issue.php';

	use Courtneyr\Child\StreamIssue;

	$failures = array();

	function cr_issue_stream_check( string $name, callable $test ): void {
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

	function cr_issue_stream_post( int $id, string $title = 'Broken sign-in', bool $issue = true, array $facts = array() ): void {
		$GLOBALS['cr_posts'][ $id ] = new WP_Post( $id, $title );
		$GLOBALS['cr_terms'][ $id ] = $issue;
		$GLOBALS['cr_facts'][ $id ] = $facts;
		$GLOBALS['cr_current_post'] = $id;
		$GLOBALS['cr_stream'] = true;
	}

	function cr_issue_stream_facts(): array {
		return array(
			'name' => 'Broken sign-in',
			'kind_label' => 'Issue',
			'repo_name' => 'courtneyr/site',
			'repo_url' => 'https://github.com/courtneyr/site',
			'repo_label' => 'courtneyr/site',
			'number_text' => '#81',
			'status' => 'open',
			'status_label' => 'Open',
			'date_iso' => '2026-10-21T10:00:00+00:00',
			'date_display' => 'October 21, 2026',
			'has_facts' => true,
		);
	}

	function cr_issue_stream_render( int $id, string $html = '<article><h3 class="pk-title">Old</h3><p>Excerpt</p></article>' ): string {
		return StreamIssue\stub_card( $html, array(), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_issue_stream_check(
		'Stream issue renders ordered kind, repository, title, status, date, properties and tear',
		static function (): bool {
			cr_issue_stream_post( 81, 'Broken sign-in', true, cr_issue_stream_facts() );
			$out = cr_issue_stream_render( 81 );
			return str_contains( $out, 'pk-card pk-card--stream cr-claim-stub h-entry' )
				&& strpos( $out, 'cr-claim-stub__kind' ) < strpos( $out, 'cr-claim-stub__repo' )
				&& strpos( $out, 'cr-claim-stub__repo' ) < strpos( $out, 'cr-claim-stub__title' )
				&& strpos( $out, 'cr-claim-stub__title' ) < strpos( $out, 'cr-claim-stub__status' )
				&& strpos( $out, 'cr-claim-stub__status' ) < strpos( $out, 'dt-published' )
				&& str_contains( $out, 'courtneyr/site #81' )
				&& str_contains( $out, 'cr-claim-stub__status--open">Open' )
				&& str_contains( $out, 'class="p-author h-card"' )
				&& str_contains( $out, 'class="u-in-reply-to h-cite"' )
				&& str_contains( $out, '<span class="cr-claim-stub__tear" aria-hidden="true" role="presentation"></span>' )
				&& 1 === substr_count( $out, '<a ' )
				&& ! str_contains( $out, 'Excerpt' );
		}
	);

	cr_issue_stream_check(
		'no repository omits the repository line and its otherwise-set number',
		static function (): bool {
			$facts = cr_issue_stream_facts();
			$facts['repo_name'] = '';
			$facts['repo_url'] = '';
			$facts['repo_label'] = '';
			cr_issue_stream_post( 82, 'No repository', true, $facts );
			$out = cr_issue_stream_render( 82 );
			return ! str_contains( $out, 'cr-claim-stub__repo' ) && ! str_contains( $out, '#81' );
		}
	);

	cr_issue_stream_check(
		'unset number and status omit their text and sticker',
		static function (): bool {
			$facts = cr_issue_stream_facts();
			$facts['number_text'] = '';
			$facts['status'] = '';
			$facts['status_label'] = '';
			cr_issue_stream_post( 83, 'No workflow', true, $facts );
			$out = cr_issue_stream_render( 83 );
			return str_contains( $out, '>courtneyr/site</p>' ) && ! str_contains( $out, 'cr-claim-stub__status' );
		}
	);

	cr_issue_stream_check(
		'facts-empty fallback keeps the title, kind and WordPress date',
		static function (): bool {
			cr_issue_stream_post( 84, 'Stored title', true, array() );
			$out = cr_issue_stream_render( 84, '<article><h4 class="pk-title">Old</h4></article>' );
			return str_contains( $out, '<h4 class="cr-claim-stub__title">' )
				&& str_contains( $out, '>Stored title</a>' )
				&& str_contains( $out, '>Issue</p>' )
				&& str_contains( $out, '>October 21, 2026</time>' )
				&& ! str_contains( $out, 'cr-claim-stub__repo' )
				&& ! str_contains( $out, 'cr-claim-stub__status' );
		}
	);

	cr_issue_stream_check(
		'non-Stream, non-issue, protected and archive-ticket cards stay byte-identical',
		static function (): bool {
			$html = '<article><h2 class="pk-title">Original</h2></article>';
			cr_issue_stream_post( 85, 'Non issue', false );
			$non_issue = cr_issue_stream_render( 85, $html );
			cr_issue_stream_post( 86, 'Protected', true );
			$GLOBALS['cr_passwords'][86] = true;
			$protected = cr_issue_stream_render( 86, $html );
			cr_issue_stream_post( 87, 'Not Stream', true );
			$GLOBALS['cr_stream'] = false;
			$non_stream = cr_issue_stream_render( 87, $html );
			$GLOBALS['cr_stream'] = true;
			$ticket = '<article class="cr-claim-ticket"><h2>Ticket</h2></article>';
			$archive = cr_issue_stream_render( 87, $ticket );
			return $html === $non_issue && $html === $protected && $html === $non_stream && $ticket === $archive;
		}
	);

	cr_issue_stream_check(
		'a synthetic name carries no p-name',
		static function (): bool {
			$facts = cr_issue_stream_facts();
			$facts['name_is_title'] = false;
			cr_issue_stream_post( 88, '', true, $facts );
			$synthetic = cr_issue_stream_render( 88 );
			$facts['name_is_title'] = true;
			cr_issue_stream_post( 89, 'Broken sign-in', true, $facts );
			$titled = cr_issue_stream_render( 89 );
			return ! str_contains( $synthetic, 'u-url p-name' ) && str_contains( $synthetic, 'class="u-url"' ) && str_contains( $titled, 'u-url p-name' );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
