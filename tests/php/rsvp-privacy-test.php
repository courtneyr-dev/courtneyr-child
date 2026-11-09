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
	class WP_HTML_Tag_Processor {
		private string $html;
		public function __construct( string $html ) { $this->html = $html; }
		public function next_tag( $query = null ): bool { return false; }
		public function get_updated_html(): string { return $this->html; }
	}
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
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
	function wp_kses_post( $html ): string { return strip_tags( (string) $html, '<p>' ); }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function __( $text, $domain = null ): string { return (string) $text; }
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool { return true; }
}

namespace Courtneyr\Child\Rsvp {
	function paper( int $post_id ): string { return 'periwinkle'; }
}

namespace Courtneyr\Child\Stamps {
	const INKS = array( '#241c4a' );
	const TILTS = 4;
	function seed( string ...$parts ): int { return 1; }
	function pick( int $seed, int $shift, int $mod ): int { return 0; }
	function render( array $spec ): string { return '<span class="cr-stamp-mark">' . htmlspecialchars( (string) $spec['big'], ENT_QUOTES, 'UTF-8' ) . '</span>'; }
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
}

namespace {
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

	$entry = '<article class="pkiw-rsvp-entry pkiw-rsvp-entry--past h-entry">Allowed event</article>';
	if ( str_contains( $entry, CR_RSVP_SENTINEL ) || ! str_contains( $entry, 'Allowed event' ) ) {
		$failures[] = 'archive entry privacy control failed';
	}

	if ( $failures ) {
		foreach ( $failures as $failure ) { fwrite( STDERR, '  ' . $failure . "\n" ); }
		exit( 1 );
	}
	echo "ok   private block attributes never cross the facts boundary\nall passed\n";
}
