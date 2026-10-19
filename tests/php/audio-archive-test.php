<?php
/**
 * Standalone checks for the audio archive card adapter.
 *
 * Run with `php tests/php/audio-archive-test.php`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_AUDIO_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_audio_hooks']   = array();
	$GLOBALS['cr_audio_request'] = array( 'kind' => 'audio' );
	$GLOBALS['cr_audio_styles']  = array();

	/** Record an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_audio_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Record a block style. */
	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_audio_styles'][] = array( (string) $block_name, (array) $args );
	}

	/** Translate text unchanged. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Current taxonomy request. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_audio_request']['kind'] ?? '' );
		return 'kind' === $taxonomy && ( '' === $term || $current === $term ) && '' !== $current;
	}

	/** This script exercises no singular request. */
	function is_singular( $post_type = '' ): bool {
		return false;
	}

	/** This script exercises no single kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		return false;
	}

	/** Return a fixed queried object. */
	function get_queried_object_id(): int {
		return 0;
	}

	/** Front-end request. */
	function is_admin(): bool {
		return false;
	}

	/** Stylesheet output is outside this script. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {}

	/** Format in a non-UTC site timezone. */
	function wp_date( $format, $timestamp = null ): string {
		$date = new \DateTimeImmutable( '@' . (int) $timestamp );
		return $date->setTimezone( new \DateTimeZone( 'Pacific/Kiritimati' ) )->format( (string) $format );
	}

	/** The controlled fixtures contain no invalid Unicode code points. */
	function wp_has_noncharacters( $text ): bool {
		return false;
	}

	/** URL-bearing attributes known to WordPress. */
	function wp_kses_uri_attributes(): array {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}

	$core  = getenv( 'WP_CORE_DIR' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api   = $core . '/wp-includes/html-api/';
	$files = array(
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-token.php',
		'class-wp-html-tag-processor.php',
	);
	foreach ( $files as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}
	require_once $core . '/wp-includes/class-wp-token-map.php';
	require_once $api . 'html5-named-character-references.php';

	require CR_AUDIO_THEME_DIR . '/inc/audio.php';
}

namespace Courtneyr\Child\AudioArchiveTests {

	use const Courtneyr\Child\Audio\CARD_CLASS;
	use function Courtneyr\Child\Audio\log_item;
	use function Courtneyr\Child\Audio\register_styles;

	$failures = array();

	/** Record one assertion. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Find registrations for one hook and callback. */
	function registrations( string $hook, string $callback ): array {
		return array_values(
			array_filter(
				$GLOBALS['cr_audio_hooks'],
				static fn( array $entry ): bool => $hook === $entry[0] && $callback === $entry[1]
			)
		);
	}

	/** Return the first element carrying a class. */
	function element( string $html, string $tag, string $class ): ?\DOMElement {
		$document = new \DOMDocument();
		@$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		foreach ( $document->getElementsByTagName( $tag ) as $candidate ) {
			if ( preg_match( '/(?:^|\s)' . preg_quote( $class, '/' ) . '(?:\s|$)/', (string) $candidate->getAttribute( 'class' ) ) ) {
				return $candidate;
			}
		}
		return null;
	}

	/** Count rendered anchors that do not sit in hidden data. */
	function visible_anchor_count( string $html ): int {
		$document = new \DOMDocument();
		@$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		$xpath = new \DOMXPath( $document );
		return $xpath->query( '//a[@href and not(ancestor::*[@hidden])]' )->length;
	}

	/** Adapt a card for the current request. */
	function adapt( string $html, array $block = array() ): string {
		return log_item( $html, $block, null );
	}

	check( 'cr-audio-log-card' === CARD_CLASS, 'CARD_CLASS names the pattern card' );
	check(
		array( array( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Audio\\log_item', 10, 3 ) )
		=== registrations( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Audio\\log_item' ),
		'log_item registers at priority 10 with three arguments'
	);
	check(
		array( array( 'init', 'Courtneyr\\Child\\Audio\\register_styles', 10, 1 ) )
		=== registrations( 'init', 'Courtneyr\\Child\\Audio\\register_styles' ),
		'register_styles registers once on init'
	);

	register_styles();
	check(
		array(
			array(
				'core/post-template',
				array(
					'name'  => 'cr-audio-log',
					'label' => 'Audio log',
				),
			),
		) === $GLOBALS['cr_audio_styles'],
		'the audio log block style registers once'
	);

	$titled = '<article class="pk-card pk-card--stream k-audio h-entry"><div class="pk-body"><span class="pk-kindlabel">Audio</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/a01/">Field recording</a></h3><p class="pk-sub pk-audio-duration"><time class="pk-duration" datetime="PT4M12S"><span aria-hidden="true">4:12</span><span class="pk-sr-only">4 minutes, 12 seconds</span></time></p><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-21T23:30:00+00:00">September 21, 2026</time></p></div></div></article>';
	$out    = adapt( $titled );
	$card   = element( $out, 'article', 'pk-card' );
	$meta   = element( $out, 'div', 'cr-audio-log__meta' );
	check( null !== $card && ! str_contains( ' ' . $card->getAttribute( 'class' ) . ' ', ' h-entry ' ) && str_contains( ' ' . $card->getAttribute( 'class' ) . ' ', ' cr-cassette--log ' ), 'the titled article loses h-entry and gains the log cassette class' );
	check( false === strpos( $out, 'pk-kindlabel' ), 'the kind label is removed from the card' );
	check( null !== $meta && str_contains( $out, '</article><div class="cr-audio-log__meta">' ), 'the meta row follows the article' );
	check( null !== $meta && 2 === $meta->getElementsByTagName( 'time' )->length, 'the meta row holds the date and duration' );
	if ( null !== $meta ) {
		$times = $meta->getElementsByTagName( 'time' );
		check( 'dt-published' === $times->item( 0 )->getAttribute( 'class' ) && '2026-09-22' === $times->item( 0 )->getAttribute( 'datetime' ), 'the date comes first and uses the local calendar day' );
		check( 'pk-duration' === $times->item( 1 )->getAttribute( 'class' ) && 'PT4M12S' === $times->item( 1 )->getAttribute( 'datetime' ), 'the duration follows unchanged' );
	}
	check( 1 === visible_anchor_count( $out ), 'the titled log item has one visible link' );
	check( false !== strpos( $out, '>Field recording</a>' ), 'the title anchor text stays unchanged' );
	check( ! preg_match( '/<article\b[^>]*\bclass="[^"]*\bpk-card\b[^"]*\bh-entry\b/i', $out ), 'the plugin root-regex sees no rooted article' );
	check( $out === adapt( $out ), 'the archive transformation is idempotent' );

	$without_duration = '<article class="pk-card pk-card--stream k-audio h-entry"><div class="pk-body"><span class="pk-kindlabel">Audio</span><div class="pk-caption"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/a03/">URL audio</a></h3><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-20T12:00:00+00:00">September 20, 2026</time></p></div></div></article>';
	$out              = adapt( $without_duration );
	$meta             = element( $out, 'div', 'cr-audio-log__meta' );
	check( null !== $meta && 1 === $meta->getElementsByTagName( 'time' )->length && false === strpos( $out, 'pk-audio-duration' ), 'a card without duration gets only the date meta' );

	$untitled = '<article class="pk-card pk-card--stream k-audio h-entry"><div class="pk-body"><span class="pk-kindlabel">Audio</span><div class="pk-caption"><h3 class="pk-title"><a class="u-url" href="https://example.test/a05/">Audio<span class="pk-sr-only">, September 3, 2026</span></a></h3><p class="pk-sub pk-audio-duration"><time class="pk-duration" datetime="PT45S"><span aria-hidden="true">0:45</span><span class="pk-sr-only">0 minutes, 45 seconds</span></time></p><p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-03T12:00:00+00:00">September 3, 2026</time></p></div></div></article>';
	$out      = adapt( $untitled );
	check( false !== strpos( $out, '>Audio<span class="pk-sr-only">, September 3, 2026</span></a>' ), 'the untitled link keeps its visible and screen-reader text' );
	check( false === strpos( $out, 'pk-title p-name' ), 'the untitled heading does not gain p-name' );
	check( 1 === visible_anchor_count( $out ), 'the untitled log item has one visible link' );

	$protected = '<article class="pk-card pk-card--stream pk-card--protected k-audio h-entry"><h3 class="pk-title"><a class="u-url" href="https://example.test/locked/">Protected: Audio</a></h3></article>';
	$listen    = str_replace( 'k-audio', 'k-listen', $without_duration );
	$no_date   = str_replace( '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-20T12:00:00+00:00">September 20, 2026</time></p>', '', $without_duration );
	check( $protected === adapt( $protected ), 'a protected card stays byte-identical' );
	check( $listen === adapt( $listen ), 'a non-audio card stays byte-identical' );
	check( $no_date === adapt( $no_date ), 'an audio card without a date stays byte-identical' );

	$GLOBALS['cr_audio_request'] = array( 'kind' => 'note' );
	check( $without_duration === adapt( $without_duration ), 'an off-archive card without the pattern class stays unchanged' );
	check( $without_duration !== adapt( $without_duration, array( 'attrs' => array( 'className' => 'other ' . CARD_CLASS ) ) ), 'the pattern class enables the adapter off the archive' );
	$GLOBALS['cr_audio_request'] = array( 'stream' => true );
	check( $without_duration === adapt( $without_duration ), 'the Stream card stays unchanged' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
