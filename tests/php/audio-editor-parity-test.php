<?php
/**
 * Standalone checks for audio editor parity and block-style registration.
 *
 * Run with `php tests/php/audio-editor-parity-test.php`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_AUDIO_PARITY_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_audio_parity_hooks']  = array();
	$GLOBALS['cr_audio_block_styles']  = array();

	/** Record an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_audio_parity_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Record a block style. */
	function register_block_style( $block_name, $args ): void {
		$GLOBALS['cr_audio_block_styles'][] = array( (string) $block_name, (array) $args );
	}

	/** Translate text unchanged. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Request stubs unused by these direct checks. */
	function is_tax( $taxonomy = '', $term = '' ): bool { return false; }
	function is_singular( $post_type = '' ): bool { return false; }
	function has_term( $term, $taxonomy, $post = null ): bool { return false; }
	function get_queried_object_id(): int { return 0; }
	function is_admin(): bool { return false; }
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {}

	require CR_AUDIO_PARITY_THEME_DIR . '/inc/audio.php';
}

namespace Courtneyr\Child\AudioEditorParityTests {

	use function Courtneyr\Child\Audio\register_editor_parity;
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

	$input = array(
		'note'  => array( 'classes' => array( 'keep-note' ) ),
		'audio' => array( 'classes' => array( 'keep-audio', 'single-post', 'keep-audio' ) ),
	);
	$out   = register_editor_parity( $input );
	check( array( 'keep-note' ) === $out['note']['classes'], 'another kind stays untouched' );
	check( in_array( 'keep-audio', $out['audio']['classes'], true ), 'the audio entry keeps earlier classes' );
	check( in_array( 'single-post', $out['audio']['classes'], true ), 'the audio entry includes single-post' );
	check( in_array( 'kind-audio', $out['audio']['classes'], true ), 'the audio entry includes kind-audio' );
	check( in_array( 'cr-single--audio', $out['audio']['classes'], true ), 'the audio entry includes cr-single--audio' );
	check( count( $out['audio']['classes'] ) === count( array_unique( $out['audio']['classes'] ) ), 'the audio classes contain no duplicates' );

	$fresh = register_editor_parity( array( 'note' => array( 'classes' => array( 'keep-note' ) ) ) );
	check( array( 'single-post', 'kind-audio', 'cr-single--audio' ) === $fresh['audio']['classes'], 'a new audio entry receives the three parity classes in order' );

	$filter_hooks = array_values(
		array_filter(
			$GLOBALS['cr_audio_parity_hooks'],
			static fn( array $entry ): bool => 'courtneyr_child_editor_parity_kinds' === $entry[0]
		)
	);
	check( array( array( 'courtneyr_child_editor_parity_kinds', 'Courtneyr\\Child\\Audio\\register_editor_parity', 10, 1 ) ) === $filter_hooks, 'editor parity registers once on the shared filter' );

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
		) === $GLOBALS['cr_audio_block_styles'],
		'the audio log block style registers exactly once'
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
