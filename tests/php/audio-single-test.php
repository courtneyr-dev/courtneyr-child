<?php
/**
 * Standalone checks for the audio single adapter.
 *
 * Run with `php tests/php/audio-single-test.php`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_AUDIO_SINGLE_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_audio_single_hooks']   = array();
	$GLOBALS['cr_audio_single_request'] = array();
	$GLOBALS['cr_audio_single_facts']   = array();
	$GLOBALS['cr_audio_single_able']    = '';
	$GLOBALS['cr_audio_single_able_ids'] = array();

	/** Record an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_audio_single_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Current singular request flag. */
	function is_singular( $post_type = '' ): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['singular'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	/** Current main-query flag. */
	function is_main_query(): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['main_query'] );
	}

	/** Current loop flag. */
	function in_the_loop(): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['in_loop'] );
	}

	/** Filters running now, for the automatic excerpt cases. */
	function doing_filter( $hook = null ): bool {
		return in_array( $hook, $GLOBALS['cr_audio_single_request']['doing'] ?? array(), true );
	}

	/** Current feed flag. */
	function is_feed(): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['feed'] );
	}

	/** Current REST flag. */
	function wp_is_rest_endpoint(): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['rest'] );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['cr_audio_single_request']['queried_id'] ?? 42 );
	}

	/** Loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_audio_single_request']['the_id'] ?? 42 );
	}

	/** Kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		return 'kind' === $taxonomy
			&& 'audio' === ( $GLOBALS['cr_audio_single_request']['kind'] ?? '' )
			&& in_array( 'audio', (array) $term, true );
	}

	/** Password gate. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_audio_single_request']['protected'] );
	}

	/** Query variable fixture. */
	function get_query_var( $name, $default = '' ) {
		$query = $GLOBALS['cr_audio_single_request']['query'] ?? array();
		return array_key_exists( $name, $query ) ? $query[ $name ] : $default;
	}

	/** Strip markup from transcript text. */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	/** Escape an attribute. */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape visible text. */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape a supported URL. */
	function esc_url( $url ): string {
		$url = trim( (string) $url );
		return 1 === preg_match( '#^https?://#i', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
	}

	/** Translate text unchanged. */
	function __( $text, $domain = null ): string {
		return (string) $text;
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
}

namespace PKIW {
	/** Return fixture facts. */
	function kind_facts( int $post_id ): array {
		$GLOBALS['cr_audio_single_facts_calls'] = ( $GLOBALS['cr_audio_single_facts_calls'] ?? 0 ) + 1;
		return $GLOBALS['cr_audio_single_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\PostKinds {
	/** Return fixture Able Player markup. */
	function ableplayer_audio( int $attachment_id ): string {
		$GLOBALS['cr_audio_single_able_ids'][] = $attachment_id;
		return (string) $GLOBALS['cr_audio_single_able'];
	}
}

namespace {
	require CR_AUDIO_SINGLE_THEME_DIR . '/inc/single-audio.php';
}

namespace Courtneyr\Child\AudioSingleTests {

	use function Courtneyr\Child\SingleAudio\begin;
	use function Courtneyr\Child\SingleAudio\body_class;
	use function Courtneyr\Child\SingleAudio\insert;
	use function Courtneyr\Child\SingleAudio\lift_player;
	use function Courtneyr\Child\SingleAudio\lift_transcript;
	use function Courtneyr\Child\SingleAudio\machine_html;
	use function Courtneyr\Child\SingleAudio\repeat_kinds;
	use function Courtneyr\Child\SingleAudio\sheet_html;

	$failures = array();

	/** Record one assertion. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Default facts for one attachment-backed player. */
	function default_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'player_count'        => 1,
				'player_block'        => 'core/audio',
				'audio_attachment_id' => 7,
				'has_transcript'      => false,
				'primary_marker'      => 'data-pkiw-primary-media',
			),
			$overrides
		);
	}

	/** Reset one valid audio request and its per-content-pass state. */
	function setup( array $facts = array() ): void {
		$GLOBALS['cr_audio_single_request'] = array(
			'singular'   => true,
			'main_query' => true,
			'in_loop'    => true,
			'feed'       => false,
			'rest'       => false,
			'queried_id' => 42,
			'the_id'     => 42,
			'kind'       => 'audio',
			'protected'  => false,
			'query'      => array(),
		);
		$GLOBALS['cr_audio_single_facts']    = array( 42 => $facts ?: default_facts() );
		$GLOBALS['cr_audio_single_able']     = '<div class="ableplayer">Able Player</div>';
		$GLOBALS['cr_audio_single_able_ids'] = array();
		begin( 'stored content' );
	}

	/** Find hook registrations by callback. */
	function registration( string $hook, string $callback ): array {
		return array_values(
			array_filter(
				$GLOBALS['cr_audio_single_hooks'],
				static fn( array $entry ): bool => $hook === $entry[0] && $callback === $entry[1]
			)
		);
	}

	$expected_hooks = array(
		array( 'the_content', 'Courtneyr\\Child\\SingleAudio\\begin', 8, 1 ),
		array( 'render_block_core/audio', 'Courtneyr\\Child\\SingleAudio\\lift_player', 250, 2 ),
		array( 'render_block_core/embed', 'Courtneyr\\Child\\SingleAudio\\lift_player', 250, 2 ),
		array( 'render_block_core/paragraph', 'Courtneyr\\Child\\SingleAudio\\lift_player', 250, 2 ),
		array( 'render_block_core/group', 'Courtneyr\\Child\\SingleAudio\\lift_transcript', 250, 2 ),
		array( 'the_content', 'Courtneyr\\Child\\SingleAudio\\insert', 97, 1 ),
	);
	foreach ( $expected_hooks as $expected ) {
		check( array( $expected ) === registration( $expected[0], $expected[1] ), $expected[1] . ' has the required hook contract' );
	}
	check( 1 === count( registration( 'body_class', 'Courtneyr\\Child\\SingleAudio\\body_class' ) ), 'body_class registers once' );
	check( 1 === count( registration( 'courtneyr_child_featured_repeat_kinds', 'Courtneyr\\Child\\SingleAudio\\repeat_kinds' ) ), 'repeat_kinds registers once' );

	$source = (string) file_get_contents( CR_AUDIO_SINGLE_THEME_DIR . '/inc/single-audio.php' );
	foreach ( array( 'attrs', 'parse_blocks', 'get_post_meta', 'untitled_name' ) as $forbidden ) {
		check( false === strpos( $source, $forbidden ), 'single-audio.php contains no ' . $forbidden );
	}

	setup();
	$GLOBALS['cr_audio_single_facts_calls'] = 0;
	lift_player( '<p>One.</p>', array( 'blockName' => 'core/paragraph' ) );
	lift_player( '<p>Two.</p>', array( 'blockName' => 'core/paragraph' ) );
	lift_transcript( '<div class="wp-block-group"><p>Three.</p></div>', array( 'blockName' => 'core/group' ) );
	insert( '<p>Body.</p>' );
	check( 1 === $GLOBALS['cr_audio_single_facts_calls'], 'the plugin facts reader runs once per content pass, not once per block' );
	begin( 'next pass' );
	lift_player( '<p>Next.</p>', array( 'blockName' => 'core/paragraph' ) );
	check( 2 === $GLOBALS['cr_audio_single_facts_calls'], 'the next content pass reads the facts again' );

	setup();
	$audio = '<figure class="wp-block-audio" data-pkiw-primary-media="1"><audio controls preload="metadata" src="https://example.test/a01.mp3"></audio></figure>';
	check( '' === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'the marked core/audio block lifts out of the flow' );
	$out = insert( '<p>After player.</p>' );
	check( false !== strpos( $out, '<span class="cr-audio-machine__counter" aria-hidden="true">01</span>' ), 'one player prints counter 01' );
	check( 1 === substr_count( $out, 'cr-audio-machine__player' ) && 1 === substr_count( $out, 'ableplayer' ), 'the machine holds one Able Player' );
	check( array( 7 ) === $GLOBALS['cr_audio_single_able_ids'], 'the attachment ID reaches the Able Player helper' );

	setup( default_facts( array( 'player_count' => 2 ) ) );
	$second = '<figure class="wp-block-audio"><audio controls preload="metadata" src="https://example.test/a06-second.mp3"></audio></figure>';
	check( '' === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'A06 lifts its marked first player' );
	check( $second === lift_player( $second, array( 'blockName' => 'core/audio' ) ), 'A06 leaves its unmarked second player in the flow' );
	$out = insert( $second );
	check( false !== strpos( $out, '>02</span>' ) && 1 === substr_count( $out, 'cr-audio-machine__player' ) && false !== strpos( $out, 'a06-second.mp3' ), 'A06 prints counter 02, one machine player and the second audio after it' );

	setup();
	check( '' === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'B07 lifts a marked player even when Core renders it inside a group' );

	setup( default_facts( array( 'player_block' => 'core/paragraph', 'audio_attachment_id' => 0 ) ) );
	$GLOBALS['cr_audio_single_able'] = '<div class="ableplayer">must not print</div>';
	$paragraph = '<p data-pkiw-primary-media="1"><a class="u-audio" href="https://audio.example.org/clip-two.mp3">https://audio.example.org/clip-two.mp3</a></p>';
	check( $paragraph === lift_player( $paragraph, array( 'blockName' => 'core/paragraph' ) ), 'D02 keeps its authored paragraph in the flow' );
	$out = insert( $paragraph );
	check( false !== strpos( $out, '<audio controls preload="metadata" src="https://audio.example.org/clip-two.mp3"></audio>' ), 'D02 builds a metadata-preloaded native player from the u-audio URL' );
	check( false !== strpos( $out, $paragraph ) && 1 === substr_count( $out, '<audio' ) && false === strpos( $out, 'ableplayer' ), 'D02 keeps the link and uses no Able Player without an attachment' );

	setup( default_facts( array( 'player_block' => 'core/paragraph', 'audio_attachment_id' => 0 ) ) );
	$unsafe = '<p data-pkiw-primary-media="1"><a class="u-audio" href="javascript:alert(1)">bad source</a></p>';
	check( $unsafe === lift_player( $unsafe, array( 'blockName' => 'core/paragraph' ) ), 'an invalid Micropub source remains authored text' );
	check( false === strpos( insert( $unsafe ), '<audio' ), 'an invalid Micropub source builds no native player' );

	setup( default_facts( array( 'player_block' => 'core/paragraph', 'audio_attachment_id' => 19 ) ) );
	check( $paragraph === lift_player( $paragraph, array( 'blockName' => 'core/paragraph' ) ), 'an attachment-backed paragraph still remains in the flow' );
	$out = insert( $paragraph );
	check( 1 === substr_count( $out, 'ableplayer' ) && array( 19 ) === $GLOBALS['cr_audio_single_able_ids'], 'an attachment-backed paragraph prefers Able Player' );

	setup( default_facts( array( 'player_block' => 'core/audio', 'audio_attachment_id' => 0 ) ) );
	$GLOBALS['cr_audio_single_able'] = '<div class="ableplayer">must not print</div>';
	check( '' === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'A03 lifts its marked URL-only player' );
	$out = insert( '<p>A03 body.</p>' );
	check( false !== strpos( $out, '<audio controls preload="metadata"' ) && false === strpos( $out, 'ableplayer' ) && array() === $GLOBALS['cr_audio_single_able_ids'], 'A03 keeps the native player and never calls Able Player' );

	setup( default_facts( array( 'player_block' => 'core/embed', 'audio_attachment_id' => 0 ) ) );
	$embed = '<figure class="wp-block-embed" data-pkiw-primary-media="1"><div><iframe src="https://soundcloud.com/example-user/example-set"></iframe></div></figure>';
	check( '' === lift_player( $embed, array( 'blockName' => 'core/embed' ) ), 'A04 lifts its marked embed' );
	check( false !== strpos( insert( '<p>A04 body.</p>' ), '<iframe src="https://soundcloud.com/example-user/example-set">' ), 'A04 keeps its iframe player' );

	setup( default_facts( array( 'audio_attachment_id' => 0 ) ) );
	$x04 = '<figure class="wp-block-audio" data-pkiw-primary-media="1"><audio controls preload="metadata" src="https://example.test/x04.mp3"></audio></figure>';
	lift_player( $x04, array( 'blockName' => 'core/audio' ) );
	check( false === strpos( insert( '<p>X04 body.</p>' ), 'autoplay' ), 'X04 output contains no autoplay' );

	setup();
	$unmarked = str_replace( ' data-pkiw-primary-media="1"', '', $audio );
	check( $unmarked === lift_player( $unmarked, array( 'blockName' => 'core/audio' ) ), 'an unmarked player stays in the flow' );
	check( $audio === lift_player( $audio, array( 'blockName' => 'core/embed' ) ), 'a block name that differs from the facts stays in the flow' );

	setup( default_facts( array( 'has_transcript' => true ) ) );
	$empty_transcript  = '<div class="wp-block-group pkiw-transcript"><p>&nbsp; </p></div>';
	$filled_transcript = '<div class="wp-block-group pkiw-transcript"><p>[00:00] Welcome.</p></div>';
	$later_transcript  = '<div class="wp-block-group pkiw-transcript"><p>Later copy.</p></div>';
	check( $empty_transcript === lift_transcript( $empty_transcript, array( 'blockName' => 'core/group' ) ), 'B08 leaves its empty transcript group in the flow' );
	check( '' === lift_transcript( $filled_transcript, array( 'blockName' => 'core/group' ) ), 'B08 lifts its first filled transcript group' );
	check( $later_transcript === lift_transcript( $later_transcript, array( 'blockName' => 'core/group' ) ), 'a later transcript group remains in the flow' );
	$out = insert( $empty_transcript . $later_transcript );
	check( 1 === substr_count( $out, '<h2 id="cr-audio-sheet-title"' ) && false !== strpos( $out, '<time class="cr-audio-sheet__stamp" datetime="PT0S">[00:00]</time> Welcome.' ), 'the lifted transcript prints once under one Transcript H2, its leading timestamp wrapped in a time element' );
	check( false !== strpos( $out, '<section class="cr-audio-sheet" aria-labelledby="cr-audio-sheet-title">' ), 'the transcript heading ID matches aria-labelledby' );

	setup( default_facts( array( 'has_transcript' => false ) ) );
	check( $filled_transcript === lift_transcript( $filled_transcript, array( 'blockName' => 'core/group' ) ), 'C01 lifts no transcript when facts say none exists' );
	check( false === strpos( insert( '<p>C01 body.</p>' ), 'cr-audio-sheet' ), 'C01 prints no transcript sheet' );

	setup();
	$out = insert( '<p>A02 body.</p>' );
	check( false === strpos( $out, 'cr-audio-sheet' ) && false === strpos( $out, 'Transcript' ), 'A02 prints no transcript placeholder or heading' );

	setup( default_facts( array( 'player_count' => 0, 'player_block' => '', 'audio_attachment_id' => 0 ) ) );
	$out = insert( '<p>No player.</p>' );
	check( false !== strpos( $out, '>00</span>' ) && false === strpos( $out, 'cr-audio-machine__player' ), 'a post without a player still prints machine counter 00 and no player box' );
	check( false !== strpos( machine_html( array( 'player_count' => 2 ), '' ), '>02</span>' ), 'machine_html prints counter 02' );
	check( false !== strpos( machine_html( array(), '' ), '>00</span>' ), 'machine_html defaults to counter 00' );
	check( '' === sheet_html( '' ), 'sheet_html returns no placeholder for an empty transcript' );

	$invalid_cases = array(
		'protected post'       => array( 'protected' => true ),
		'feed'                 => array( 'feed' => true ),
		'REST request'         => array( 'rest' => true ),
		'different loop post'  => array( 'the_id' => 99 ),
		'outside the loop'     => array( 'in_loop' => false ),
		'non-main query'       => array( 'main_query' => false ),
	);
	foreach ( $invalid_cases as $label => $changes ) {
		setup( default_facts( array( 'has_transcript' => true ) ) );
		$GLOBALS['cr_audio_single_request'] = array_merge( $GLOBALS['cr_audio_single_request'], $changes );
		check( $audio === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), $label . ' leaves the player in place' );
		check( $filled_transcript === lift_transcript( $filled_transcript, array( 'blockName' => 'core/group' ) ), $label . ' leaves the transcript in place' );
		check( '<p>Stored.</p>' === insert( '<p>Stored.</p>' ), $label . ' leaves content unchanged' );
	}

	foreach ( array( 'atproto' => array( 'atproto' => '1' ), 'markdown request' => array( 'markdown_request' => '1' ), 'markdown format' => array( 'format' => 'markdown' ) ) as $label => $query ) {
		setup();
		$GLOBALS['cr_audio_single_request']['query'] = $query;
		check( $audio === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), $label . ' leaves the player in place' );
		check( '<p>Stored.</p>' === insert( '<p>Stored.</p>' ), $label . ' leaves content unchanged' );
	}

	foreach ( array( 'get_the_excerpt', 'wp_trim_excerpt' ) as $running ) {
		setup();
		$GLOBALS['cr_audio_single_request']['doing'] = array( $running );
		check( $audio === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'inside ' . $running . ' the player stays in the flow' );
		$excerpt = insert( '<p>Stored words.</p>' );
		check( '<p>Stored words.</p>' === $excerpt, 'inside ' . $running . ' the content passes unchanged' );
		check( 'Stored words.' === trim( strip_tags( $excerpt ) ), 'an automatic excerpt of an audio single holds no machine counter or text' );
	}

	setup();
	$once = insert( '<p>Body.</p>' );
	check( $once === insert( $once ), 'insert adds the wrapper only once' );

	setup();
	$GLOBALS['cr_audio_single_facts'][42] = array();
	check( '<p>Body.</p>' === insert( '<p>Body.</p>' ), 'empty facts leave content unchanged' );

	$without_marker = default_facts();
	unset( $without_marker['primary_marker'] );
	setup( $without_marker );
	check( $audio === lift_player( $audio, array( 'blockName' => 'core/audio' ) ), 'a missing primary marker key lifts nothing' );

	setup();
	check( array( 'existing', 'cr-single--audio' ) === body_class( body_class( array( 'existing' ) ) ), 'body_class adds cr-single--audio once' );
	$GLOBALS['cr_audio_single_request']['kind'] = 'note';
	check( array( 'existing' ) === body_class( array( 'existing' ) ), 'body_class leaves another kind untouched' );
	check( array( 'photo', 'audio' ) === repeat_kinds( repeat_kinds( array( 'photo' ) ) ), 'repeat_kinds appends audio once' );
	check( 'not-an-array' === repeat_kinds( 'not-an-array' ), 'repeat_kinds preserves a non-array value' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
