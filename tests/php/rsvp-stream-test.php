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
	$GLOBALS['cr_rsvp_stream_terms']    = array();
	$GLOBALS['cr_rsvp_stream_protected'] = array();
	$GLOBALS['cr_rsvp_stream_failures'] = array();

	class WP_Post { public int $ID; public string $post_title; public function __construct( int $id ) { $this->ID = $id; $this->post_title = 'Event ' . $id; } }
	class WP_Term { public string $name = 'RSVP'; }
	class WP_Block { public array $context; public function __construct( array $context ) { $this->context = $context; } }
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
	function get_the_ID(): int { return 0; }
	function get_post( $post = null ): ?WP_Post { return $post instanceof WP_Post ? $post : ( $GLOBALS['cr_rsvp_stream_posts'][ (int) $post ] ?? null ); }
	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && 'kind' === $taxonomy && in_array( $term, $GLOBALS['cr_rsvp_stream_terms'][ $post->ID ] ?? array(), true );
	}
	function post_password_required( $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && ! empty( $GLOBALS['cr_rsvp_stream_protected'][ $post->ID ] );
	}
	function get_term_by( $field, $value, $taxonomy ): WP_Term { return new WP_Term(); }
	function get_the_title( $post = null ): string { $post = get_post( $post ); return $post instanceof WP_Post ? $post->post_title : ''; }
	function get_permalink( $post = null ): string { $post = get_post( $post ); return 'https://example.test/rsvp/' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '/'; }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function __( $text, $domain = null ): string { return (string) $text; }
	function cr_rsvp_stream_post( int $id, string $status, string $label ): void {
		$GLOBALS['cr_rsvp_stream_posts'][ $id ] = new WP_Post( $id );
		$GLOBALS['cr_rsvp_stream_terms'][ $id ] = array( 'rsvp' );
		$GLOBALS['cr_rsvp_stream_protected'][ $id ] = false;
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

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		fwrite( STDERR, "FAIL: set CR_WP_CORE to a WordPress checkout\n" );
		exit( 1 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
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
	function untitled_name( \WP_Post $post, bool $with_kind = true ): string { return 'Untitled RSVP, November 1, 2026'; }
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

	cr_rsvp_stream_check(
		'heading level, kind, password and untitled paths use the incoming card safely',
		static function (): void {
			cr_rsvp_stream_post( 221, 'yes', 'Going' );
			$heading = cr_rsvp_stream_render( 221, '<article class="pk-card"><h4 class="pk-title">Old</h4></article>' );
			cr_rsvp_stream_assert( str_contains( $heading, '<h4 class="cr-rsvp-stub__name">' ), 'incoming h4 stays h4' );

			cr_rsvp_stream_post( 222, 'yes', 'Going' );
			$GLOBALS['cr_rsvp_stream_terms'][222] = array( 'note' );
			$card = '<article class="pk-card"><h3 class="pk-title">Note</h3></article>';
			cr_rsvp_stream_assert( $card === cr_rsvp_stream_render( 222, $card ), 'non-RSVP card passes through' );

			cr_rsvp_stream_post( 223, 'yes', 'Going' );
			$GLOBALS['cr_rsvp_stream_protected'][223] = true;
			cr_rsvp_stream_assert( $card === cr_rsvp_stream_render( 223, $card ), 'password-protected card passes through' );

			cr_rsvp_stream_post( 224, 'yes', 'Going' );
			$GLOBALS['cr_rsvp_stream_posts'][224]->post_title = '';
			$GLOBALS['cr_rsvp_stream_facts'][224]['name'] = '';
			$untitled = cr_rsvp_stream_render( 224 );
			cr_rsvp_stream_assert( str_contains( $untitled, '>Untitled RSVP, November 1, 2026</a>' ), 'untitled fallback gives the link an accessible name' );
		}
	);

	cr_rsvp_stream_check(
		'CSS keeps compact status rows and complete dark outlines',
		static function (): void {
			$css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-rsvp.css' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__status data {\n\tflex: 1 1 auto;" ), 'status uses an auto content basis' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__status .cr-stamp-mark {\n\tflex: 0 1 8.4rem;\n\tmax-inline-size: 8.4rem;" ), 'stamp uses the shared 8.4rem size' );
			cr_rsvp_stream_assert( str_contains( $css, ":root:not([data-theme=\"light\"]) .pk-card.pk-card--stream.cr-rsvp-stub {\n\t\tborder: 2px solid var(--cr-printer-ivory);" ), 'system dark Stream stub has a complete ivory border' );
			cr_rsvp_stream_assert( str_contains( $css, ":root[data-theme=\"dark\"] .pk-card.pk-card--stream.cr-rsvp-stub {\n\tborder: 2px solid var(--cr-printer-ivory);" ), 'manual dark Stream stub has a complete ivory border' );
			cr_rsvp_stream_assert( str_contains( $css, ":root:not([data-theme=\"light\"]) .cr-rsvp-ticket__stub {\n\t\tborder-color: var(--cr-printer-ivory);\n\t\tbackground-color: var(--cr-night-elevated);\n\t\tcolor: var(--cr-printer-ivory);" ), 'system dark ticket stub uses the dark palette' );
			cr_rsvp_stream_assert( str_contains( $css, ":root[data-theme=\"dark\"] .cr-rsvp-ticket__stub {\n\tborder-color: var(--cr-printer-ivory);\n\tbackground-color: var(--cr-night-elevated);\n\tcolor: var(--cr-printer-ivory);" ), 'manual dark ticket stub uses the dark palette' );
			cr_rsvp_stream_assert( str_contains( $css, ":root:not([data-theme=\"light\"]) .cr-rsvp-ticket__punch {\n\t\tborder-color: var(--cr-printer-ivory);" ), 'system dark punch outlines use ivory' );
			cr_rsvp_stream_assert( str_contains( $css, ":root[data-theme=\"dark\"] .cr-rsvp-ticket__punch {\n\tborder-color: var(--cr-printer-ivory);" ), 'manual dark punch outlines use ivory' );
			cr_rsvp_stream_assert( 1 === substr_count( $css, ".cr-rsvp-ticket__punch.is-punched {\n\tbackground-color: var(--cr-surface);" ), 'punched hole still exposes the page surface' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__status data::before,\n.pk-card.pk-card--stream.cr-rsvp-stub .cr-rsvp-stub__status data::after {\n\tcontent: \"\";\n\tposition: absolute;\n\tborder: 0 dashed var(--cr-russian-violet);" ), 'perforation is a dashed ink rule, not an alpha' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__status data::before {\n\tinset-block-start: calc(100% + var(--cr-space-md) / 2 - 1px);\n\tinset-inline: 0;\n\tborder-block-start-width: 2px;" ), 'stacked stamp gets a horizontal perforation' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__status data::after {\n\tinset-block: 0;\n\tinset-inline-start: calc(100% + var(--cr-space-md) / 2 - 1px);\n\tborder-inline-start-width: 2px;" ), 'side-by-side stamp gets a vertical perforation' );
			cr_rsvp_stream_assert( str_contains( $css, "\tgap: var(--cr-space-md);\n\tmargin: calc(-1 * var(--cr-space-2xs)) calc(-1 * var(--cr-space-3xs));\n\tpadding: var(--cr-space-2xs) var(--cr-space-3xs);\n\toverflow: clip;" ), 'status row clips the off-axis perforation' );
			cr_rsvp_stream_assert( (bool) preg_match( '/@media \(forced-colors: active\) \{[^@]*\.cr-rsvp-stub__status data::before,\n\t\.pk-card\.pk-card--stream\.cr-rsvp-stub \.cr-rsvp-stub__status data::after,[^}]*display: none;/s', $css ), 'forced colors drop the perforation with the tear and stamp' );
		}
	);

	cr_rsvp_stream_check(
		'Stream event names use the ink underline treatment',
		static function (): void {
			$css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-rsvp.css' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__name a {\n\tcolor: inherit;\n\ttext-decoration: underline;\n\ttext-decoration-thickness: 1px;\n\ttext-underline-offset: 0.2em;" ), 'event name is missing the ink underline treatment' );
		}
	);

	cr_rsvp_stream_check(
		'Dark Stream stubs draw a two-pixel ivory torn edge',
		static function (): void {
			$css = (string) file_get_contents( CR_RSVP_THEME_DIR . '/assets/css/cr-rsvp.css' );
			cr_rsvp_stream_assert( str_contains( $css, ".cr-rsvp-stub__tear::before {\n\tcontent: \"\";\n\tposition: absolute;\n\tinset: 0;\n\tbackground-color: var(--cr-rsvp-paper, var(--cr-periwinkle));\n\tclip-path: inherit;\n\ttransform: translateY(-2px);" ), 'tear is missing its two-pixel paper inset' );
			cr_rsvp_stream_assert( (bool) preg_match( '/:root:not\(\[data-theme="light"\]\) \.pk-card\.pk-card--stream\.cr-rsvp-stub \{[^}]*--cr-rsvp-tear-edge: var\(--cr-printer-ivory\);/s', $css ), 'system dark Stream stub is missing its ivory torn edge' );
			cr_rsvp_stream_assert( (bool) preg_match( '/:root\[data-theme="dark"\] \.pk-card\.pk-card--stream\.cr-rsvp-stub \{[^}]*--cr-rsvp-tear-edge: var\(--cr-printer-ivory\);/s', $css ), 'manual dark Stream stub is missing its ivory torn edge' );
		}
	);

	if ( $GLOBALS['cr_rsvp_stream_failures'] ) {
		foreach ( $GLOBALS['cr_rsvp_stream_failures'] as $failure ) { fwrite( STDERR, '  ' . $failure . "\n" ); }
		exit( 1 );
	}
	echo "all passed\n";
}
