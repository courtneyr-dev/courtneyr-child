<?php
/**
 * `php tests/php/rsvp-stream-test.php`: RSVP Stream stub contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_RSVP_THEME_DIR', dirname( __DIR__, 2 ) );
	$GLOBALS['cr_rsvp_stream_on']       = true;
	$GLOBALS['cr_rsvp_stream_facts']    = array();
	$GLOBALS['cr_rsvp_stream_posts']    = array();
	$GLOBALS['cr_rsvp_stream_failures'] = array();

	class WP_Post { public int $ID; public string $post_title; public function __construct( int $id ) { $this->ID = $id; $this->post_title = 'Event ' . $id; } }
	class WP_Term { public string $name = 'RSVP'; }
	class WP_Block { public array $context; public function __construct( array $context ) { $this->context = $context; } }
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
	function get_the_ID(): int { return 0; }
	function get_post( $post = null ): ?WP_Post { return $post instanceof WP_Post ? $post : ( $GLOBALS['cr_rsvp_stream_posts'][ (int) $post ] ?? null ); }
	function has_term( $term, $taxonomy, $post = null ): bool { return 'rsvp' === $term && 'kind' === $taxonomy; }
	function post_password_required( $post = null ): bool { return false; }
	function get_term_by( $field, $value, $taxonomy ): WP_Term { return new WP_Term(); }
	function get_the_title( $post = null ): string { $post = get_post( $post ); return $post instanceof WP_Post ? $post->post_title : ''; }
	function get_permalink( $post = null ): string { $post = get_post( $post ); return 'https://example.test/rsvp/' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '/'; }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function __( $text, $domain = null ): string { return (string) $text; }
	function cr_rsvp_stream_post( int $id, string $status, string $label ): void {
		$GLOBALS['cr_rsvp_stream_posts'][ $id ] = new WP_Post( $id );
		$GLOBALS['cr_rsvp_stream_facts'][ $id ] = array(
			'name' => 'Event ' . $id, 'name_is_title' => true, 'status' => $status, 'status_label' => $label,
			'start_day' => '2026-11-12', 'day_display' => 'November 12, 2026', 'date_iso' => '2026-11-01T00:00:00+00:00',
			'url' => 'https://events.example/' . $id, 'location' => 'Rv251 Sentinel Hall',
		);
	}
	function cr_rsvp_stream_render( int $id, string $card = '<article class="pk-card"><h3 class="pk-title">Old</h3></article>' ): string {
		return \Courtneyr\Child\StreamRsvp\stub_card( $card, array(), new WP_Block( array( 'postId' => $id ) ) );
	}
	function cr_rsvp_stream_assert( bool $ok, string $message ): void { if ( ! $ok ) { $GLOBALS['cr_rsvp_stream_failures'][] = $message; } }
	function cr_rsvp_stream_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_rsvp_stream_failures'] );
		try { $test(); } catch ( \Throwable $error ) { $GLOBALS['cr_rsvp_stream_failures'][] = $name . ': ' . $error->getMessage(); }
		echo ( $before === count( $GLOBALS['cr_rsvp_stream_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool { return (bool) $GLOBALS['cr_rsvp_stream_on']; }
}

namespace Courtneyr\Child\Rsvp {
	function paper( int $post_id ): string { return array( 'periwinkle', 'sky-blue', 'light-orange' )[ $post_id % 3 ]; }
}

namespace PKIW {
	function kind_facts( int $post_id ): array { return $GLOBALS['cr_rsvp_stream_facts'][ $post_id ] ?? array(); }
	function entry_author_html( \WP_Post $post ): string { return '<span class="p-author h-card">Author</span>'; }
}

namespace {
	require CR_RSVP_THEME_DIR . '/inc/stamps.php';
	require CR_RSVP_THEME_DIR . '/inc/stream-rsvp.php';

	cr_rsvp_stream_check(
		'all five statuses render the C16 one-link stub and matching stamp line',
		static function (): void {
			$labels = array( 'yes' => 'Going', 'no' => 'Not Going', 'maybe' => 'Maybe', 'interested' => 'Interested', 'remote' => 'Attending Remotely' );
			foreach ( $labels as $index => $label ) {
				$id = 201 + array_search( $index, array_keys( $labels ), true );
				cr_rsvp_stream_post( $id, $index, $label );
				$html = cr_rsvp_stream_render( $id );
				foreach ( array( 'cr-rsvp-stub', 'cr-rsvp-stub__kind', 'cr-rsvp-stub__name', 'cr-rsvp-stub__day', 'cr-rsvp-stub__status', 'cr-rsvp-stub__tear', 'p-rsvp', 'dt-published', 'p-author h-card', 'u-in-reply-to' ) as $token ) {
					cr_rsvp_stream_assert( str_contains( $html, $token ), $label . ' missing ' . $token );
				}
				cr_rsvp_stream_assert( 1 === substr_count( $html, '<a ' ), $label . ' has one anchor' );
				cr_rsvp_stream_assert( str_contains( $html, '>' . mb_strtoupper( $label ) . '</text>' ), $label . ' stamp big line' );
				cr_rsvp_stream_assert( ! str_contains( $html, 'Rv251 Sentinel Hall' ) && ! str_contains( $html, 'p-location' ), $label . ' has no location' );
				if ( 'remote' === $index ) {
					cr_rsvp_stream_assert( str_contains( $html, 'textLength="150" lengthAdjust="spacingAndGlyphs">ATTENDING REMOTELY</text>' ), 'remote stamp fits' );
				}
			}
		}
	);

	cr_rsvp_stream_check(
		'protected, non-Stream and already-adapted cards pass through',
		static function (): void {
			cr_rsvp_stream_post( 220, 'yes', 'Going' );
			$protected = '<article class="pk-card pk-card--protected">Protected</article>';
			cr_rsvp_stream_assert( $protected === cr_rsvp_stream_render( 220, $protected ), 'protected card unchanged' );
			$GLOBALS['cr_rsvp_stream_on'] = false;
			$plain = '<article class="pk-card">Plain</article>';
			cr_rsvp_stream_assert( $plain === cr_rsvp_stream_render( 220, $plain ), 'non-Stream card unchanged' );
			$GLOBALS['cr_rsvp_stream_on'] = true;
			$stub = '<article class="cr-rsvp-stub">Already</article>';
			cr_rsvp_stream_assert( $stub === cr_rsvp_stream_render( 220, $stub ), 'stub is not nested' );
		}
	);

	if ( $GLOBALS['cr_rsvp_stream_failures'] ) {
		foreach ( $GLOBALS['cr_rsvp_stream_failures'] as $failure ) { fwrite( STDERR, '  ' . $failure . "\n" ); }
		exit( 1 );
	}
	echo "all passed\n";
}
