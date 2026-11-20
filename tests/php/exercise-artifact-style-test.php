<?php
/**
 * Standalone Exercise style and Stream privacy checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return $GLOBALS['exercise_stream'];
	}
}

namespace PKIW {
	function kind_facts( int $id ): array {
		return array(
			'name'          => 'Parity ride',
			'name_is_title' => true,
			'activity'      => 'Cycling',
			'distance'      => '987.65',
			'distance_label' => 17 === $id ? '987.65 km' : '',
			'duration'      => 'PT7H43M21S',
			'splits'        => 'PT13M57S',
			'pb_category'   => 'N7 Xq245 PB Sentinel',
		);
	}
}

namespace {
	use function Courtneyr\Child\StreamExercise\bib_corner;

	define( 'ABSPATH', __DIR__ . '/' );

	final class WP_Block_Type_Registry {
		public static function get_instance(): self {
			return new self();
		}

		public function is_registered( string $block ): bool {
			return 'post-kinds-indieweb/exercise-card' === $block;
		}
	}

	final class WP_Post {
		public function __construct( public int $ID ) {}
	}

	final class WP_Block {
		public function __construct( public array $context ) {}
	}

	function add_action( string $hook, callable $callback, int $priority = 10 ): void {
		$GLOBALS['exercise_hooks'][ $hook ][ $priority ][] = $callback;
	}

	function add_filter(): void {}

	function register_block_style( string $block, array $style ): void {
		$GLOBALS['exercise_registration'][ $block ] = $style;
	}

	function get_the_terms( int $id, string $taxonomy ): array|false {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$slugs = 18 === $id ? array( 'checkin' ) : array( 'checkin', 'exercise' );
		return array_map( static fn( string $slug ): object => (object) array( 'slug' => $slug ), $slugs );
	}

	function get_post( int $id ): WP_Post {
		return new WP_Post( $id );
	}

	function post_password_required( WP_Post $post ): bool {
		return 20 === $post->ID;
	}

	function get_permalink( WP_Post $post ): string {
		return 'https://example.test/?p=' . $post->ID;
	}

	function __( string $text ): string {
		return $text;
	}

	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( string $text ): string {
		return esc_html( $text );
	}

	function esc_url( string $url ): string {
		return esc_html( $url );
	}

	function check_exercise_style( bool $pass, string $message ): void {
		if ( ! $pass ) {
			fwrite( STDERR, 'FAIL ' . $message . PHP_EOL );
			exit( 1 );
		}
		echo 'PASS ', $message, PHP_EOL;
	}

	require dirname( __DIR__, 2 ) . '/inc/artifact-style.php';
	require dirname( __DIR__, 2 ) . '/inc/exercise.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-exercise.php';

	$GLOBALS['exercise_hooks']['after_setup_theme'][10][0]();
	$GLOBALS['exercise_hooks']['init'][20][0]();
	$expected = array( 'post-kinds-indieweb/exercise-card' => array( 'name' => 'cr-race-bib', 'label' => 'Race bib' ) );
	check_exercise_style( $expected === $GLOBALS['exercise_registration'], 'register Race bib on the Exercise card' );

	$GLOBALS['exercise_stream'] = true;
	$input = '<article class="pk-card"></article>';
	$open  = bib_corner( $input, array(), new WP_Block( array( 'postId' => 17 ) ) );
	$closed = bib_corner( $input, array(), new WP_Block( array( 'postId' => 19 ) ) );
	check_exercise_style( str_contains( $open, 'cr-bib-corner is-style-cr-race-bib' ), 'style the bib when Exercise is the second kind' );
	check_exercise_style( str_contains( $open, '987.65 km' ) && str_contains( $open, 'Cycling' ), 'print the opted-in distance and public activity' );
	foreach ( array( 'PT7H43M21S', 'PT13M57S', 'N7 Xq245 PB Sentinel' ) as $sentinel ) {
		check_exercise_style( ! str_contains( $open . $closed, $sentinel ), 'omit Stream sentinel ' . $sentinel );
	}
	check_exercise_style( ! str_contains( $closed, '987.65' ) && str_contains( $closed, 'Cycling' ), 'omit the opted-out distance while keeping the public activity' );
	check_exercise_style( $input === bib_corner( $input, array(), new WP_Block( array( 'postId' => 18 ) ) ), 'leave non-Exercise cards alone' );
	check_exercise_style( $input === bib_corner( $input, array(), new WP_Block( array( 'postId' => 20 ) ) ), 'leave the locked card alone' );
	$GLOBALS['exercise_stream'] = false;
	check_exercise_style( $input === bib_corner( $input, array(), new WP_Block( array( 'postId' => 17 ) ) ), 'preserve rendering outside Stream' );
}
