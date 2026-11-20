<?php
/**
 * Standalone checks for Mood's artifact style registration and kind membership.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

class WP_Block_Type_Registry {
	public static function get_instance(): self {
		return new self();
	}

	public function is_registered( string $block ): bool {
		return 'post-kinds-indieweb/mood-card' === $block;
	}
}

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
	$GLOBALS['mood_artifact_hooks'][ $hook ][ $priority ] = $callback;
}

function add_filter(): void {}

function __( string $text ): string {
	return $text;
}

function register_block_style( string $block, array $style ): void {
	$GLOBALS['mood_artifact_registration'] = array( $block, $style );
}

function get_the_terms( int $post_id, string $taxonomy ): array|false {
	if ( 'kind' !== $taxonomy ) {
		return false;
	}
	$slugs = 17 === $post_id ? array( 'checkin', 'mood' ) : array( 'checkin' );
	return array_map( static fn( string $slug ): object => (object) array( 'slug' => $slug ), $slugs );
}

require dirname( __DIR__, 2 ) . '/inc/artifact-style.php';
require dirname( __DIR__, 2 ) . '/inc/mood-pin.php';

$GLOBALS['mood_artifact_hooks']['after_setup_theme'][10]();
$GLOBALS['mood_artifact_hooks']['init'][20]();
$expected = array( 'post-kinds-indieweb/mood-card', array( 'name' => 'cr-pin-button', 'label' => 'Pin-back button' ) );
$slug     = 'cr-pin-button';

if ( ( $GLOBALS['mood_artifact_registration'] ?? null ) !== $expected ||
	! \Courtneyr\Child\ArtifactStyle\applies( 17, $slug ) || \Courtneyr\Child\ArtifactStyle\applies( 18, $slug ) ) {
	fwrite( STDERR, "FAIL Mood style registration or kind membership\n" );
	exit( 1 );
}

echo "PASS Mood style registration and second-kind membership\n";
