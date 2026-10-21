<?php
/**
 * Standalone collection cabinet render checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
define( 'COURTNEYR_CHILD_VERSION', 'test' );

$GLOBALS['cr_collection_hooks'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['cr_collection_hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback, $priority, $accepted_args ); }
function apply_filters( $hook, $value ) { return $value; }
function __( $text, $domain = '' ) { return $text; }

$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
$api  = $core . '/wp-includes/html-api';
if ( ! is_dir( $api ) ) {
	echo "SKIP HTML API unavailable; set CR_WP_CORE\n";
	exit( 2 );
}
require_once $core . '/wp-includes/compat.php';
require_once $core . '/wp-includes/utf8.php';
require_once $core . '/wp-includes/kses.php';
foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
	require_once $api . '/' . $file;
}

require dirname( __DIR__, 2 ) . '/inc/collection.php';

$failed = 0;
function cr_collection_render_check( bool $condition, string $message ): void {
	global $failed;
	echo ( $condition ? 'ok ' : 'not ok ' ) . $message . "\n";
	$failed += $condition ? 0 : 1;
}

$cabinet = '<div class="wp-block-group pkiw-collection"><p>Visible item</p></div>';
$styled  = '<div class="wp-block-group pkiw-collection is-style-cr-paper"><p>Visible item</p></div>';
$plain   = '<div class="wp-block-group"><p>Plain</p></div>';

$output = \Courtneyr\Child\Collection\cabinet_default( $cabinet, array( 'attrs' => array( 'className' => 'pkiw-collection extra' ) ) );
cr_collection_render_check( str_contains( $output, 'is-style-cr-cabinet' ), 'cabinet_default adds the seeded cabinet style' );
cr_collection_render_check( $styled === \Courtneyr\Child\Collection\cabinet_default( $styled, array( 'attrs' => array( 'className' => 'pkiw-collection is-style-cr-paper' ) ) ), 'author style remains byte-identical' );
cr_collection_render_check( $plain === \Courtneyr\Child\Collection\cabinet_default( $plain, array( 'attrs' => array( 'className' => 'plain' ) ) ), 'plain group remains byte-identical' );
cr_collection_render_check( '<p>Not a group</p>' === \Courtneyr\Child\Collection\cabinet_default( '<p>Not a group</p>', array( 'attrs' => array( 'className' => 'pkiw-collection' ) ) ), 'first tag without cabinet class remains byte-identical' );
cr_collection_render_check( '' === \Courtneyr\Child\Collection\cabinet_default( '', array( 'attrs' => array( 'className' => 'pkiw-collection' ) ) ), 'empty render stays empty' );

$preg_count = null;
if ( function_exists( 'shell_exec' ) && file_exists( dirname( __DIR__, 2 ) . '/.git' ) ) {
	$command    = 'cd ' . escapeshellarg( dirname( __DIR__, 2 ) ) . ' && git grep -c preg_ -- inc/ 2>/dev/null';
	$counts     = (string) shell_exec( $command );
	$preg_count = array_sum( array_map( static fn( string $line ): int => (int) substr( $line, strrpos( $line, ':' ) + 1 ), array_filter( explode( "\n", trim( $counts ) ) ) ) );
	cr_collection_render_check( 303 === $preg_count, 'inc preg_ count remains 303' );
} else {
	echo "SKIP preg_ count: git or shell_exec unavailable\n";
}

exit( $failed > 0 ? 1 : 0 );
