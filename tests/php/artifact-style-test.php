<?php
/**
 * Exercise theme artifact registration and kind lookup.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

use Courtneyr\Child\ArtifactStyle;

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['artifact_init'] = array();
$GLOBALS['artifact_styles'] = array();
$GLOBALS['artifact_term_reads'] = array();

final class WP_Block_Type_Registry {
	public static function get_instance(): self {
		return new self();
	}

	public function is_registered( string $block ): bool {
		return 'example/first-card' === $block;
	}
}

final class WP_Post {
	public function __construct( public int $ID ) {}
}

function add_action( string $hook, callable $callback, int $priority ): void {
	$GLOBALS['artifact_init'][] = array( $hook, $callback, $priority );
}

function register_block_style( string $block, array $style ): void {
	$GLOBALS['artifact_styles'][ $block ] = $style;
}

function get_the_terms( int $post_id, string $taxonomy ): array|false {
	$GLOBALS['artifact_term_reads'][] = array( $post_id, $taxonomy );
	return 5 === $post_id ? array( (object) array( 'slug' => 'second' ), (object) array( 'slug' => 'first' ) ) : false;
}

function check_artifact_style( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL ' . $message . PHP_EOL );
		exit( 1 );
	}
	echo 'PASS ', $message, PHP_EOL;
}

require dirname( __DIR__, 2 ) . '/inc/artifact-style.php';

ArtifactStyle\register( 'example/first-card', 'first-style', 'First', 'first' );
ArtifactStyle\register( 'example/second-card', 'second-style', 'Second', 'second' );

check_artifact_style( 1 === count( $GLOBALS['artifact_init'] ) && 20 === $GLOBALS['artifact_init'][0][2], 'register both cards on one late init hook' );
ArtifactStyle\register_styles();
check_artifact_style( array( 'example/first-card' => array( 'name' => 'first-style', 'label' => 'First' ) ) === $GLOBALS['artifact_styles'], 'register a style only for an existing block' );

check_artifact_style( ArtifactStyle\applies( new WP_Post( 5 ), 'first-style' ) && ArtifactStyle\applies( 5, 'second-style' ), 'both style memberships hold on a multi-kind post' );
check_artifact_style( 'second-style' === ArtifactStyle\resolve( 5 ), 'resolve follows term order even when registration order differs' );
check_artifact_style( ! ArtifactStyle\applies( 5, 'unknown-style' ) && '' === ArtifactStyle\resolve( 9 ), 'unknown styles and missing terms resolve to no style' );
check_artifact_style( array( array( 5, 'kind' ), array( 9, 'kind' ) ) === $GLOBALS['artifact_term_reads'], 'read kind terms once per post' );
