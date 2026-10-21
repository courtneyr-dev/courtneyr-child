<?php
/**
 * `php tests/php/issue-single-test.php`: standalone issue single checks.
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
	$GLOBALS['cr_request'] = array();

	class WP_Post {
		public $ID;
		public $post_title;
		public function __construct( int $id, string $title ) {
			$this->ID = $id;
			$this->post_title = $title;
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
	function is_singular( $type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $type || 'post' === $type );
	}
	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried'] ?? 0 );
	}
	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
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
	function in_the_loop() {
		return ! empty( $GLOBALS['cr_request']['loop'] );
	}
	function get_the_title( $post = 0 ) {
		$post = get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}
	function __( $text ) {
		return $text;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function wp_has_noncharacters( $text ) {
		return false;
	}
	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
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
	require dirname( __DIR__, 2 ) . '/inc/single-issue.php';

	use Courtneyr\Child\SingleIssue;

	$failures = array();

	function cr_issue_single_check( string $name, callable $test ): void {
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

	function cr_issue_single_post( int $id = 70, array $facts = array() ): WP_Post {
		$post = new WP_Post( $id, 'Broken sign-in' );
		$GLOBALS['cr_posts'][ $id ] = $post;
		$GLOBALS['cr_terms'][ $id ] = true;
		$GLOBALS['cr_facts'][ $id ] = $facts;
		$GLOBALS['cr_request'] = array( 'single' => true, 'queried' => $id, 'loop' => true );
		return $post;
	}

	function cr_issue_single_facts( string $number = '#70', bool $has_facts = true ): array {
		return array( 'name' => 'Broken sign-in', 'kind_label' => 'Issue', 'number_text' => $number, 'has_facts' => $has_facts );
	}

	function cr_issue_single_render( string $html ): string {
		return SingleIssue\ticket_head( $html, array( 'blockName' => 'core/post-content' ) );
	}

	cr_issue_single_check(
		'facts-bearing queried issues hold the h1 while existing true stays true',
		static function (): bool {
			$post = cr_issue_single_post( 70, cr_issue_single_facts() );
			$yes = SingleIssue\holds_h1( false, $post );
			$GLOBALS['cr_facts'][70] = array();
			return $yes && ! SingleIssue\holds_h1( false, $post ) && SingleIssue\holds_h1( true, $post );
		}
	);

	cr_issue_single_check(
		'head has one h1, an aria-hidden number stub and dressed plugin regions',
		static function (): bool {
			cr_issue_single_post( 71, cr_issue_single_facts( '#71' ) );
			$html = '<article class="pkiw-singular-entry h-entry"><div class="pkiw-issue-facts"><dl></dl></div><div class="pkiw-issue-body"><p>Body</p></div></article>';
			$out = cr_issue_single_render( $html );
			return 1 === substr_count( $out, '<h1 ' )
				&& str_contains( $out, '<span class="cr-claim-ticket-head__stub" aria-hidden="true" role="presentation">#71</span>' )
				&& str_contains( $out, 'pkiw-singular-entry h-entry cr-claim-sheet' )
				&& str_contains( $out, 'pkiw-issue-facts cr-claim-facts' )
				&& str_contains( $out, 'pkiw-issue-body cr-claim-body' )
				&& ! str_contains( $out, '<h1 class="cr-claim-ticket-head__title p-name"' );
		}
	);

	cr_issue_single_check(
		'numberless issue prints an empty paper stub',
		static function (): bool {
			cr_issue_single_post( 72, cr_issue_single_facts( '' ) );
			$out = cr_issue_single_render( '<div class="pkiw-singular-entry"><p>Body</p></div>' );
			return str_contains( $out, '<span class="cr-claim-ticket-head__stub" aria-hidden="true" role="presentation"></span>' );
		}
	);

	cr_issue_single_check(
		'no-facts and has-facts-false posts keep content unchanged',
		static function (): bool {
			$html = '<div class="pkiw-singular-entry"><p>Body</p></div>';
			cr_issue_single_post( 73, array() );
			$empty = cr_issue_single_render( $html );
			cr_issue_single_post( 74, cr_issue_single_facts( '#74', false ) );
			$false = cr_issue_single_render( $html );
			return $html === $empty && $html === $false;
		}
	);

	cr_issue_single_check(
		'ticket head is idempotent and falls back to the first content element',
		static function (): bool {
			cr_issue_single_post( 75, cr_issue_single_facts( '#75' ) );
			$once = cr_issue_single_render( '<div class="plain"><p>Body</p></div>' );
			$twice = cr_issue_single_render( $once );
			return $once === $twice && str_contains( $once, '<div class="plain cr-claim-sheet">' );
		}
	);

	cr_issue_single_check(
		'protected and out-of-loop issue content stays unchanged',
		static function (): bool {
			$html = '<div class="pkiw-singular-entry"><p>Body</p></div>';
			cr_issue_single_post( 76, cr_issue_single_facts( '#76' ) );
			$GLOBALS['cr_passwords'][76] = true;
			$protected = cr_issue_single_render( $html );
			$GLOBALS['cr_passwords'][76] = false;
			$GLOBALS['cr_request']['loop'] = false;
			$outside = cr_issue_single_render( $html );
			return $html === $protected && $html === $outside;
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
