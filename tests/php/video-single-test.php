<?php
/**
 * Standalone tests for the video single tape deck.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'REST_REQUEST', '1' === (string) getenv( 'CR_VIDEO_SINGLE_REST' ) );

	$GLOBALS['cr_single_actions']   = array();
	$GLOBALS['cr_single_filters']   = array();
	$GLOBALS['cr_single_failures']  = array();
	$GLOBALS['cr_single_checks']    = 0;
	$GLOBALS['cr_single_posts']     = array();
	$GLOBALS['cr_single_facts']     = array();
	$GLOBALS['cr_single_kinds']     = array();
	$GLOBALS['cr_single_protected'] = array();
	$GLOBALS['cr_single_current']   = 0;
	$GLOBALS['cr_single_singular']  = true;
	$GLOBALS['cr_single_feed']      = false;
	$GLOBALS['cr_single_embed']     = false;
	$GLOBALS['cr_single_query']     = array();

	class WP_Post {
		public int $ID;
		public string $post_title;

		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_arguments = 1 ): void {
		$GLOBALS['cr_single_actions'][] = array( $hook, $callback, $priority, $accepted_arguments );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_arguments = 1 ): void {
		$GLOBALS['cr_single_filters'][] = array( $hook, $callback, $priority, $accepted_arguments );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function is_singular( $post_type = '' ): bool {
		return $GLOBALS['cr_single_singular'] && 'post' === $post_type;
	}

	function is_feed(): bool {
		return (bool) $GLOBALS['cr_single_feed'];
	}

	function is_embed(): bool {
		return (bool) $GLOBALS['cr_single_embed'];
	}

	function get_query_var( $name, $default = '' ) {
		return $GLOBALS['cr_single_query'][ (string) $name ] ?? $default;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_single_current'];
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? get_queried_object_id() : (int) $post;
		return $GLOBALS['cr_single_posts'][ $id ] ?? null;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'video' === (string) $term && ! empty( $GLOBALS['cr_single_kinds'][ $id ] );
	}

	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}

	function post_password_required( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! empty( $GLOBALS['cr_single_protected'][ $id ] );
	}

	function cr_single_assert( bool $condition, string $label ): void {
		++$GLOBALS['cr_single_checks'];
		if ( ! $condition ) {
			$GLOBALS['cr_single_failures'][] = $label;
		}
	}

	function cr_single_group( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_single_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_single_failures'][] = $name . ' threw ' . $error->getMessage();
		}
		echo count( $GLOBALS['cr_single_failures'] ) === $before ? 'PASS ' : 'FAIL ', $name, "\n";
	}

	function cr_single_done(): void {
		foreach ( $GLOBALS['cr_single_failures'] as $failure ) {
			echo '  - ', $failure, "\n";
		}
		echo 'TOTAL ', $GLOBALS['cr_single_checks'], ' checks, ', count( $GLOBALS['cr_single_failures'] ), " failed\n";
		exit( empty( $GLOBALS['cr_single_failures'] ) ? 0 : 1 );
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		echo "SKIP: set CR_WP_CORE to a WordPress checkout\n";
		exit( 0 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_single_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\Nav {
	function kind_date_name( \WP_Post $post ): string {
		return 'Video, December 31, 2025 at 11:30 pm';
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/single-video.php';

	use Courtneyr\Child\SingleVideo as Single;

	/** Video facts fixture. */
	function cr_single_facts( array $changes = array() ): array {
		return array_merge(
			array(
				'has_player'     => true,
				'primary_marker' => 'data-pkiw-primary-media',
				'name'           => 'Seed Orchard Walk',
				'name_is_title'  => true,
				'date_iso'       => '2026-09-18',
				'date_display'   => 'September 18, 2026',
				'runtime_seconds'=> 134,
				'runtime_iso'    => 'PT2M14S',
				'runtime_clock'  => '2:14',
				'runtime_human'  => '2 minutes, 14 seconds',
			),
			$changes
		);
	}

	/** Register and select one post fixture. */
	function cr_single_post( int $id, array $facts, bool $video = true, bool $protected = false ): WP_Post {
		$post                                      = new WP_Post( $id, (string) ( $facts['name'] ?? '' ) );
		$GLOBALS['cr_single_posts'][ $id ]         = $post;
		$GLOBALS['cr_single_facts'][ $id ]         = $facts;
		$GLOBALS['cr_single_kinds'][ $id ]         = $video;
		$GLOBALS['cr_single_protected'][ $id ]     = $protected;
		$GLOBALS['cr_single_current']              = $id;
		$GLOBALS['cr_single_singular']             = true;
		$GLOBALS['cr_single_feed']                 = false;
		$GLOBALS['cr_single_embed']                = false;
		$GLOBALS['cr_single_query']                = array();
		return $post;
	}

	/** Marked player fixture. */
	function cr_single_player( string $inside = '<video controls></video>' ): string {
		return '<figure class="wp-block-video" data-pkiw-primary-media="1">' . $inside . '</figure>';
	}

	if ( REST_REQUEST ) {
		cr_single_post( 900, cr_single_facts() );
		$player = cr_single_player();
		exit( Single\deck( $player, array() ) === $player ? 0 : 1 );
	}

	cr_single_group(
		'H1 ownership follows readable player facts',
		static function (): void {
			$post = cr_single_post( 1, cr_single_facts() );
			cr_single_assert( Single\holds_h1( false, $post ), 'player single owns the H1' );
			cr_single_assert( Single\holds_h1( true, $post ), 'prior ownership remains true' );

			$protected = cr_single_post( 2, cr_single_facts(), true, true );
			cr_single_assert( ! Single\holds_h1( false, $protected ), 'protected post keeps the template H1' );
			$empty = cr_single_post( 3, cr_single_facts( array( 'has_player' => false ) ) );
			cr_single_assert( ! Single\holds_h1( false, $empty ), 'player-less post keeps the template H1' );
			$multiple = cr_single_post( 4, array() );
			cr_single_assert( ! Single\holds_h1( false, $multiple ), 'multi-kind empty facts keep the template H1' );
			$other = cr_single_post( 5, cr_single_facts(), false );
			cr_single_assert( ! Single\holds_h1( false, $other ), 'non-video post keeps the template H1' );
		}
	);

	cr_single_group(
		'the marked player becomes one labeled deck',
		static function (): void {
			cr_single_post( 10, cr_single_facts() );
			$player = cr_single_player();
			$out    = Single\deck( $player, array() );
			cr_single_assert( 1 === substr_count( $out, 'class="cr-tape-deck"' ), 'one deck wrapper' );
			cr_single_assert( str_contains( $out, '<h1 class="cr-tape-label__title p-name">Seed Orchard Walk</h1>' ), 'real title is the p-name H1' );
			cr_single_assert( str_contains( $out, 'class="dt-published cr-tape-label__date" datetime="2026-09-18">September 18, 2026</time>' ), 'stored day prints with its ISO date' );
			cr_single_assert( str_contains( $out, 'class="cr-tape-label__runtime" datetime="PT2M14S">2:14<span class="screen-reader-text"> (2 minutes, 14 seconds)</span>' ), 'runtime has ISO, clock and human text' );
			cr_single_assert( strpos( $out, 'cr-tape-label' ) < strpos( $out, 'cr-tape-deck__frame' ), 'label precedes the player frame' );
			cr_single_assert( str_contains( $out, '<div class="cr-tape-deck__frame">' . str_replace( '<video controls>', '<video data-heading-level="2" controls>', $player ) . '</div>' ), 'player stays inside the frame with only the heading level added' );
			cr_single_assert( 1 === substr_count( $out, 'data-heading-level="2"' ), 'one video gets one heading level' );
			cr_single_assert( $player === Single\deck( $player, array() ), 'a second render for the post stays unwrapped' );

			cr_single_post( 11, cr_single_facts() );
			$plain = '<figure class="wp-block-video"><video controls></video></figure>';
			cr_single_assert( $plain === Single\deck( $plain, array() ), 'unmarked media stays unchanged' );
		}
	);

	cr_single_group(
		'Able Player heading level comes from the deck',
		static function (): void {
			cr_single_post( 17, cr_single_facts() );
			$authored = Single\deck( cr_single_player( '<video data-able-player data-heading-level="3"></video>' ), array() );
			cr_single_assert( str_contains( $authored, 'data-heading-level="3"' ) && ! str_contains( $authored, 'data-heading-level="2"' ), 'an authored heading level stays' );
			cr_single_post( 18, cr_single_facts() );
			$embed = cr_single_player( '<iframe src="https://example.test/embed"></iframe>' );
			cr_single_assert( str_contains( Single\deck( $embed, array() ), '<div class="cr-tape-deck__frame">' . $embed . '</div>' ), 'a player with no video element stays byte-identical' );
			cr_single_post( 19, cr_single_facts() );
			cr_single_assert( str_contains( Single\deck( cr_single_player( '<video data-able-player><track></video>' ), array() ), '<video data-heading-level="2" data-able-player><track>' ), 'an Able Player video gets level 2' );
		}
	);

	cr_single_group(
		'untitled and no-runtime labels follow the facts contract',
		static function (): void {
			cr_single_post( 12, cr_single_facts( array( 'name' => 'Video, December 31, 2025', 'name_is_title' => false ) ) );
			$out = Single\deck( cr_single_player(), array() );
			cr_single_assert( str_contains( $out, '<h1 class="cr-tape-label__title">Video, December 31, 2025 at 11:30 pm</h1>' ), 'untitled post uses the theme label' );
			cr_single_assert( ! str_contains( $out, 'cr-tape-label__title p-name' ), 'untitled H1 has no p-name' );

			foreach ( array( 13, 14, 15 ) as $id ) {
				cr_single_post( $id, cr_single_facts( array( 'runtime_seconds' => 0, 'runtime_iso' => '', 'runtime_clock' => '', 'runtime_human' => '' ) ) );
				cr_single_assert( ! str_contains( Single\deck( cr_single_player(), array() ), 'cr-tape-label__runtime' ), 'case ' . $id . ' has no runtime element' );
			}
		}
	);

	cr_single_group(
		'existing player content stays inside the frame',
		static function (): void {
			cr_single_post( 16, cr_single_facts( array( 'runtime_seconds' => 0 ) ) );
			$player = cr_single_player( '<video data-able-player><button type="button">Play</button></video><figcaption>Captions and transcript on YouTube</figcaption>' );
			$out    = Single\deck( $player, array() );
			$frame  = (string) strstr( $out, '<div class="cr-tape-deck__frame">' );
			cr_single_assert( str_contains( $frame, '<figcaption>Captions and transcript on YouTube</figcaption>' ), 'figcaption remains inside the frame' );
			cr_single_assert( 1 === substr_count( $out, '<button' ) && str_contains( $frame, '<button' ), 'the only button is the passed player control' );
		}
	);

	cr_single_group(
		'machine and alternate renders stay unchanged',
		static function (): void {
			$cases = array(
				'feed'             => array( 'feed', true ),
				'embed'            => array( 'embed', true ),
				'atproto'          => array( 'atproto', '1' ),
				'markdown request' => array( 'markdown_request', 1 ),
				'markdown format'  => array( 'format', 'markdown' ),
			);
			$id = 20;
			foreach ( $cases as $label => $case ) {
				cr_single_post( ++$id, cr_single_facts() );
				if ( 'feed' === $case[0] ) {
					$GLOBALS['cr_single_feed'] = true;
				} elseif ( 'embed' === $case[0] ) {
					$GLOBALS['cr_single_embed'] = true;
				} else {
					$GLOBALS['cr_single_query'][ $case[0] ] = $case[1];
				}
				$player = cr_single_player();
				cr_single_assert( $player === Single\deck( $player, array() ), $label . ' stays unchanged' );
			}

			putenv( 'CR_VIDEO_SINGLE_REST=1' );
			$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ );
			exec( $command, $output, $status );
			putenv( 'CR_VIDEO_SINGLE_REST' );
			cr_single_assert( 0 === $status, 'REST render stays unchanged' );
		}
	);

	cr_single_group(
		'body class, hooks and source policy are exact',
		static function (): void {
			cr_single_post( 30, cr_single_facts() );
			cr_single_assert( array( 'base', 'cr-tape-single' ) === Single\body_class( array( 'base' ) ), 'single adds its body class' );
			cr_single_assert( array( 'cr-tape-single' ) === Single\body_class( array( 'cr-tape-single' ) ), 'body class is not duplicated' );
			cr_single_post( 31, cr_single_facts(), false );
			cr_single_assert( array( 'base' ) === Single\body_class( array( 'base' ) ), 'other post gets no body class' );

			$video_hook = array_values( array_filter( $GLOBALS['cr_single_filters'], static fn( array $hook ): bool => 'render_block_core/video' === $hook[0] && 250 === $hook[2] && 2 === $hook[3] ) );
			$embed_hook = array_values( array_filter( $GLOBALS['cr_single_filters'], static fn( array $hook ): bool => 'render_block_core/embed' === $hook[0] && 250 === $hook[2] && 2 === $hook[3] ) );
			$h1_hook    = array_values( array_filter( $GLOBALS['cr_single_filters'], static fn( array $hook ): bool => 'courtneyr_child_single_object_holds_h1' === $hook[0] && 10 === $hook[2] && 2 === $hook[3] ) );
			cr_single_assert( 1 === count( $video_hook ) && 1 === count( $embed_hook ), 'both player hooks use priority 250 and two arguments' );
			cr_single_assert( 1 === count( $h1_hook ), 'H1 hook uses priority 10 and two arguments' );

			$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/single-video.php' );
			cr_single_assert( 0 === preg_match( '/attrs|get_post_meta|parse_blocks|untitled_name|kind_picture/', $source ), 'facts-only source scan is empty' );
		}
	);

	cr_single_done();
}
