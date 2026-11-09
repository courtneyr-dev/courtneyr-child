<?php
/**
 * `php tests/php/rsvp-single-test.php`: RSVP single ticket contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_RSVP_THEME_DIR', dirname( __DIR__, 2 ) );
	$GLOBALS['cr_rsvp_single_facts']    = array();
	$GLOBALS['cr_rsvp_single_posts']    = array();
	$GLOBALS['cr_rsvp_single_query_id'] = 0;
	$GLOBALS['cr_rsvp_single_singular'] = true;
	$GLOBALS['cr_rsvp_single_terms']    = array();
	$GLOBALS['cr_rsvp_single_protected'] = array();
	$GLOBALS['cr_rsvp_single_failures'] = array();

	class WP_Post {
		public int $ID;
		public string $post_title;
		public function __construct( int $id, string $title ) {
			$this->ID = $id;
			$this->post_title = $title;
		}
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;
		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
	function get_the_ID(): int { return (int) $GLOBALS['cr_rsvp_single_query_id']; }
	function get_queried_object_id(): int { return (int) $GLOBALS['cr_rsvp_single_query_id']; }
	function is_singular( $type = '' ): bool { return 'post' === $type && (bool) $GLOBALS['cr_rsvp_single_singular']; }
	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && 'kind' === $taxonomy && in_array( $term, $GLOBALS['cr_rsvp_single_terms'][ $post->ID ] ?? array(), true );
	}
	function post_password_required( $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && ! empty( $GLOBALS['cr_rsvp_single_protected'][ $post->ID ] );
	}
	function get_post( $post = null ): ?WP_Post { return $post instanceof WP_Post ? $post : ( $GLOBALS['cr_rsvp_single_posts'][ (int) $post ] ?? null ); }
	function get_the_title( $post = null ): string { $post = get_post( $post ); return $post instanceof WP_Post ? $post->post_title : ''; }
	function wp_strip_all_tags( $text ): string { return trim( strip_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) ); }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function __( $text, $domain = null ): string { return (string) $text; }
	function wp_kses_post( $html ): string { return strip_tags( (string) $html, '<p><a><em><strong>' ); }

	function cr_rsvp_single_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'name' => 'Fernwood Tech Talk', 'name_is_title' => false, 'url' => 'https://fernwood.example/talk', 'url_host' => 'fernwood.example',
				'status' => 'remote', 'status_label' => 'Attending Remotely', 'start_iso' => '2026-11-12T18:00:00-05:00', 'start_day' => '2026-11-12',
				'day_display' => 'November 12, 2026', 'time_display' => '6:00 pm – 8:00 pm', 'end_iso' => '2026-11-12T20:00:00-05:00',
				'location' => 'Example Hall, 1 Example Way', 'description' => 'A fictional technology talk.', 'note' => '<p>Bringing stickers.</p>',
				'rsvp_at_iso' => '2026-11-01T00:00:00-04:00', 'rsvp_at_display' => 'November 1, 2026', 'image' => 'https://images.example/poster.jpg',
				'image_alt' => 'Abstract event poster', 'date_iso' => '2026-11-01T04:00:00+00:00',
			),
			$overrides
		);
	}

	function cr_rsvp_single_post( int $id, string $title, array $facts = array() ): WP_Post {
		$post = new WP_Post( $id, $title );
		$GLOBALS['cr_rsvp_single_posts'][ $id ] = $post;
		$GLOBALS['cr_rsvp_single_facts'][ $id ] = cr_rsvp_single_facts( $facts );
		$GLOBALS['cr_rsvp_single_terms'][ $id ] = array( 'rsvp' );
		$GLOBALS['cr_rsvp_single_protected'][ $id ] = false;
		$GLOBALS['cr_rsvp_single_query_id'] = $id;
		return $post;
	}

	function cr_rsvp_single_render( int $id, string $html = '<article class="pk-card k-rsvp">plugin card</article>' ): string {
		$GLOBALS['cr_rsvp_single_query_id'] = $id;
		return \Courtneyr\Child\SingleRsvp\ticket( $html, array(), new WP_Block( array( 'postId' => $id ) ) );
	}

	function cr_rsvp_single_assert( bool $ok, string $message ): void {
		if ( ! $ok ) { $GLOBALS['cr_rsvp_single_failures'][] = $message; }
	}
	function cr_rsvp_single_contains( string $needle, string $html, string $message ): void { cr_rsvp_single_assert( str_contains( $html, $needle ), $message . ': missing ' . $needle ); }
	function cr_rsvp_single_not_contains( string $needle, string $html, string $message ): void { cr_rsvp_single_assert( ! str_contains( $html, $needle ), $message . ': found ' . $needle ); }
	function cr_rsvp_single_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_rsvp_single_failures'] );
		try { $test(); } catch ( \Throwable $error ) { $GLOBALS['cr_rsvp_single_failures'][] = $name . ': ' . $error->getMessage(); }
		echo ( $before === count( $GLOBALS['cr_rsvp_single_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Return the unique microformat class tokens in one fragment. */
	function cr_rsvp_single_mf2_classes( string $html ): array {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$classes = array();
		foreach ( ( new DOMXPath( $document ) )->query( '//*[@class]' ) as $node ) {
			foreach ( explode( ' ', (string) $node->getAttribute( 'class' ) ) as $class ) {
				if ( in_array( substr( $class, 0, 2 ), array( 'h-', 'p-', 'u-', 'e-' ), true ) || str_starts_with( $class, 'dt-' ) ) {
					$classes[] = $class;
				}
			}
		}
		$classes = array_values( array_unique( $classes ) );
		sort( $classes );
		return $classes;
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array { return $GLOBALS['cr_rsvp_single_facts'][ $post_id ] ?? array(); }
	function rsvp_status_labels(): array { return array( 'yes' => 'Going', 'no' => 'Not Going', 'maybe' => 'Maybe', 'interested' => 'Interested', 'remote' => 'Attending Remotely' ); }
	function get_kind_label( string $label, string $kind, string $context = '' ): string { return 'rsvp' === $kind && 'rsvp-card' === $context ? ( $GLOBALS['cr_rsvp_single_kind_label'] ?? $label ) : 'wrong kind context'; }
	function pkiw_new_tab_label_attr( string $visible ): string { return ' aria-label="' . htmlspecialchars( $visible . ' (opens in a new tab)', ENT_QUOTES, 'UTF-8' ) . '"'; }
}

namespace {
	require CR_RSVP_THEME_DIR . '/inc/single-rsvp.php';

	cr_rsvp_single_check(
		'ticket order, public facts and microformat properties match the card contract',
		static function (): void {
			cr_rsvp_single_post( 101, 'RSVP: Fernwood Tech Talk' );
			$html = cr_rsvp_single_render( 101 );
			foreach ( array( 'cr-rsvp-ticket__poster', 'cr-rsvp-ticket__event-heading', '>Date<', '>Time<', '>Location<', 'p-summary', 'cr-rsvp-ticket__stub', 'p-content', 'dt-published', 'u-in-reply-to' ) as $token ) {
				cr_rsvp_single_contains( $token, $html, 'ticket contract ' . $token );
			}
			$positions = array_map( static fn( string $token ): int|false => strpos( $html, $token ), array( 'cr-rsvp-ticket__poster', 'cr-rsvp-ticket__event-heading', '>Date<', '>Time<', '>Location<', 'p-summary' ) );
			$sorted = $positions;
			sort( $sorted );
			cr_rsvp_single_assert( $positions === $sorted, 'front DOM follows image, name, date, time, location, description' );
			$expected_mf2 = array( 'dt-end', 'dt-published', 'dt-start', 'h-entry', 'h-event', 'p-content', 'p-in-reply-to', 'p-location', 'p-name', 'p-rsvp', 'p-summary', 'u-in-reply-to', 'u-photo', 'u-url' );
			sort( $expected_mf2 );
			cr_rsvp_single_assert( $expected_mf2 === cr_rsvp_single_mf2_classes( $html ), 'ticket mf2 property set equals the card contract' );
		}
	);

	cr_rsvp_single_check(
		'name branches, optional image and the five-punch row are exact',
		static function (): void {
			cr_rsvp_single_post( 102, 'Different post title', array( 'image' => '', 'image_alt' => '' ) );
			$different = cr_rsvp_single_render( 102 );
			cr_rsvp_single_contains( '>Fernwood Tech Talk</a>', $different, 'different name is visible link text' );
			cr_rsvp_single_not_contains( 'cr-rsvp-ticket__poster', $different, 'no empty poster panel' );
			cr_rsvp_single_assert( 5 === substr_count( $different, '<span class="cr-rsvp-ticket__punch' ), 'five punch positions' );
			cr_rsvp_single_assert( 1 === substr_count( $different, 'is-punched' ), 'one punched position' );
			cr_rsvp_single_contains( 'aria-hidden="true"', $different, 'punch row hidden' );

			cr_rsvp_single_post( 103, 'Fernwood Tech Talk', array( 'image' => '' ) );
			$same = cr_rsvp_single_render( 103 );
			cr_rsvp_single_contains( '>fernwood.example</a>', $same, 'equal name uses provider host' );
			cr_rsvp_single_contains( '<data class="p-name" value="Fernwood Tech Talk" hidden>', $same, 'equal name keeps h-event name' );
		}
	);

	cr_rsvp_single_check(
		'private location and banned display strings never enter the ticket',
		static function (): void {
			cr_rsvp_single_post( 104, 'Private RSVP', array( 'location' => '', 'image' => '', 'description' => '', 'note' => '', 'rsvp_at_iso' => '' ) );
			$html = cr_rsvp_single_render( 104 );
			foreach ( array( 'Location', 'p-location', "You're invited", 'Admit one', 'RSVP details', 'Response recorded', 'Location hidden', 'cr-stamp' ) as $banned ) {
				cr_rsvp_single_not_contains( $banned, $html, 'banned string ' . $banned );
			}
		}
	);

	cr_rsvp_single_check(
		'the stub leads with one visible plugin kind label, never a heading',
		static function (): void {
			cr_rsvp_single_post( 111, 'Kind label RSVP' );
			$html = cr_rsvp_single_render( 111 );
			cr_rsvp_single_assert( 1 === substr_count( $html, 'class="pk-kindlabel' ), 'exactly one ticket kind label' );
			cr_rsvp_single_contains( '<aside class="cr-rsvp-ticket__stub"><span class="pk-kindlabel cr-rsvp-ticket__kind">RSVP</span><div class="cr-rsvp-ticket__punches"', $html, 'kind label opens the stub' );
			cr_rsvp_single_assert( 0 === preg_match( '#<h[1-6][^>]*>\s*RSVP\s*</h[1-6]>#', $html ), 'kind label is not a heading' );
			cr_rsvp_single_contains( '<span class="cr-rsvp-ticket__perf" aria-hidden="true"></span></aside>', $html, 'perforation rail is decorative' );

			$GLOBALS['cr_rsvp_single_kind_label'] = 'Répondez';
			cr_rsvp_single_post( 112, 'Filtered kind label' );
			cr_rsvp_single_contains( '<span class="pk-kindlabel cr-rsvp-ticket__kind">Répondez</span>', cr_rsvp_single_render( 112 ), 'kind label goes through pkiw_kind_label' );
			unset( $GLOBALS['cr_rsvp_single_kind_label'] );
		}
	);

	cr_rsvp_single_check(
		'only the first RSVP card for a queried post is replaced',
		static function (): void {
			cr_rsvp_single_post( 105, 'First card only' );
			$card = '<article class="pk-card k-rsvp">plugin card</article>';
			$first = cr_rsvp_single_render( 105, $card );
			$second = cr_rsvp_single_render( 105, $card );
			cr_rsvp_single_contains( 'cr-rsvp-ticket', $first, 'first card replaced' );
			cr_rsvp_single_assert( $card === $second, 'second card passes through' );
		}
	);

	cr_rsvp_single_check(
		'protected, non-RSVP, off-query and non-singular cards pass through',
		static function (): void {
			$card = '<article class="pk-card k-rsvp">plugin card</article>';

			cr_rsvp_single_post( 106, 'Protected RSVP' );
			$GLOBALS['cr_rsvp_single_protected'][106] = true;
			cr_rsvp_single_assert( $card === cr_rsvp_single_render( 106, $card ), 'protected card passes through' );

			cr_rsvp_single_post( 107, 'Note post' );
			$GLOBALS['cr_rsvp_single_terms'][107] = array( 'note' );
			cr_rsvp_single_assert( $card === cr_rsvp_single_render( 107, $card ), 'non-RSVP card passes through' );

			cr_rsvp_single_post( 108, 'Card post' );
			cr_rsvp_single_post( 109, 'Queried post' );
			$GLOBALS['cr_rsvp_single_query_id'] = 109;
			cr_rsvp_single_assert( $card === \Courtneyr\Child\SingleRsvp\ticket( $card, array(), new WP_Block( array( 'postId' => 108 ) ) ), 'card from another post passes through' );

			cr_rsvp_single_post( 110, 'Archive render' );
			$GLOBALS['cr_rsvp_single_singular'] = false;
			cr_rsvp_single_assert( $card === cr_rsvp_single_render( 110, $card ), 'non-singular render passes through' );
			$GLOBALS['cr_rsvp_single_singular'] = true;
		}
	);

	if ( $GLOBALS['cr_rsvp_single_failures'] ) {
		foreach ( $GLOBALS['cr_rsvp_single_failures'] as $failure ) { fwrite( STDERR, '  ' . $failure . "\n" ); }
		exit( 1 );
	}
	echo "all passed\n";
}
