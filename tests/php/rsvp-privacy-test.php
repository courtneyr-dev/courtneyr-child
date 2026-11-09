<?php
/**
 * `php tests/php/rsvp-privacy-test.php`: RSVP theme privacy sentinel.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_RSVP_THEME_DIR', dirname( __DIR__, 2 ) );
	const CR_RSVP_SENTINEL = 'Rv251 Sentinel Hall, 1 Sentinel Way';
	$GLOBALS['cr_rsvp_privacy_on_stream'] = true;

	class WP_Post { public int $ID = 251; public string $post_title = 'Private RSVP'; }
	class WP_Term { public string $name = 'RSVP'; }
	class WP_Block { public array $context = array( 'postId' => 251 ); }
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
	function apply_filters( $hook, $value, ...$args ) { return $value; }
	function is_singular( $type = '' ): bool { return true; }
	function get_queried_object_id(): int { return 251; }
	function get_the_ID(): int { return 251; }
	function get_post( $post = null ): WP_Post { return new WP_Post(); }
	function has_term( $term, $taxonomy, $post = null ): bool { return true; }
	function post_password_required( $post = null ): bool { return false; }
	function get_the_title( $post = null ): string { return 'Private RSVP'; }
	function get_permalink( $post = null ): string { return 'https://example.test/private-rsvp/'; }
	function get_term_by( $field, $value, $taxonomy ): WP_Term { return new WP_Term(); }
	function wp_strip_all_tags( $text ): string { return strip_tags( (string) $text ); }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function __( $text, $domain = null ): string { return (string) $text; }

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
	function is_stream_surface(): bool { return true; }
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return array(
			'name' => 'Private RSVP', 'name_is_title' => true, 'url' => 'https://events.example/private', 'url_host' => 'events.example',
			'status' => 'yes', 'status_label' => 'Going', 'start_iso' => '2026-11-12T18:00:00-05:00', 'start_day' => '2026-11-12',
			'day_display' => 'November 12, 2026', 'time_display' => '6:00 pm', 'end_iso' => '', 'location' => '', 'description' => '', 'note' => '',
			'rsvp_at_iso' => '', 'rsvp_at_display' => '', 'image' => '', 'image_alt' => '', 'date_iso' => '2026-11-01T00:00:00+00:00',
		);
	}
	function rsvp_status_labels(): array { return array( 'yes' => 'Going', 'no' => 'Not Going', 'maybe' => 'Maybe', 'interested' => 'Interested', 'remote' => 'Attending Remotely' ); }
	function pkiw_new_tab_label_attr( string $visible ): string { return ''; }
	function entry_author_html( \WP_Post $post ): string { return ''; }
	function untitled_name( \WP_Post $post, bool $with_kind = true ): string { return 'Untitled RSVP'; }
}

namespace {
	require CR_RSVP_THEME_DIR . '/inc/stamps.php';
	require CR_RSVP_THEME_DIR . '/inc/rsvp.php';
	require CR_RSVP_THEME_DIR . '/inc/single-rsvp.php';
	require CR_RSVP_THEME_DIR . '/inc/stream-rsvp.php';

	$block = array(
		'attrs' => array(
			'className' => 'is-style-cr-rsvp-wall',
			'eventLocation' => CR_RSVP_SENTINEL,
			'locationVisibility' => 'private',
		),
	);
	$card = '<article class="pk-card k-rsvp" data-private-location="' . CR_RSVP_SENTINEL . '">plugin card</article>';
	$ticket = \Courtneyr\Child\SingleRsvp\ticket( $card, $block, new WP_Block() );
	$stream = \Courtneyr\Child\StreamRsvp\stub_card( $card, $block, new WP_Block() );

	$failures = array();
	foreach ( array( 'ticket' => $ticket, 'stream' => $stream ) as $surface => $html ) {
		if ( str_contains( $html, CR_RSVP_SENTINEL ) ) {
			$failures[] = $surface . ' leaked the private block-attribute sentinel';
		}
		if ( str_contains( $html, 'p-location' ) || str_contains( $html, '>Location<' ) ) {
			$failures[] = $surface . ' emitted location markup';
		}
		if ( ! str_contains( $html, 'Private RSVP' ) ) {
			$failures[] = $surface . ' omitted the allowed event name control';
		}
	}

	$entry = \Courtneyr\Child\Rsvp\entry_paint(
		'<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past h-entry"><a class="u-url">Allowed event</a></article>',
		$block,
		new WP_Block()
	);
	if ( str_contains( $entry, CR_RSVP_SENTINEL ) || ! str_contains( $entry, 'Allowed event' ) || ! str_contains( $entry, '--cr-rsvp-paper' ) ) {
		$failures[] = 'archive entry privacy control failed';
	}

	if ( $failures ) {
		foreach ( $failures as $failure ) { fwrite( STDERR, '  ' . $failure . "\n" ); }
		exit( 1 );
	}
	echo "ok   private block attributes never cross the facts boundary\nall passed\n";
}
