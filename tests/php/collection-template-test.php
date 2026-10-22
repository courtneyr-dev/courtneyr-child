<?php
/**
 * Standalone collection template and pattern checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

function __( $text, $domain = '' ) { return $text; }
function esc_html_e( $text, $domain = '' ) { echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }

class WP_Block_Type_Registry {
	private static $instance;
	public static function get_instance() { return self::$instance ??= new self(); }
	public function is_registered( $name ) { return true; }
}

$failed = 0;
function cr_collection_template_check( bool $condition, string $message ): void {
	global $failed;
	echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
	$failed += $condition ? 0 : 1;
}

$root = dirname( __DIR__, 2 );
$template = (string) file_get_contents( $root . '/templates/taxonomy-kind-collection.html' );
ob_start();
include $root . '/patterns/cr-cabinet-hall-loop.php';
$pattern = (string) ob_get_clean();

cr_collection_template_check( 1 === substr_count( $template, 'wp:courtneyr/archive-identity' ), 'template has one archive identity block' );
cr_collection_template_check( 1 === substr_count( $template, 'wp:query-title' ) && str_contains( $template, '"showPrefix":false' ), 'template has one prefix-free archive title' );
cr_collection_template_check( 1 === substr_count( $template, 'wp:term-description' ), 'template has one term description' );
cr_collection_template_check( str_contains( $template, 'is-style-cr-marker-bar-short' ), 'template has the marker-bar separator' );
cr_collection_template_check( str_contains( $template, 'courtneyr-child/cr-cabinet-hall-loop' ) && str_contains( $template, 'courtneyr-child/cr-browse-all' ), 'template delegates to both collection patterns' );
cr_collection_template_check( str_contains( $template, '"name":"Collection archive"' ), 'template metadata names the Collection archive' );

cr_collection_template_check( str_contains( $pattern, '"inherit":true' ) && str_contains( $pattern, '"perPage":6' ), 'query inherits and declares six posts' );
cr_collection_template_check( 2 === substr_count( $pattern, 'wp:post-template' ) && str_contains( $pattern, '"className":"is-style-cr-cabinet-hall"' ), 'pattern has one cabinet-hall post template' );
cr_collection_template_check( str_contains( $pattern, 'post-kinds-indieweb/stream-card {"headingLevel":2,"className":"cr-cabinet-plaque"}' ), 'pattern requests an H2 collection stream card' );
cr_collection_template_check( ! str_contains( $pattern, 'archive-sections' ), 'pattern has no archive-sections block' );
cr_collection_template_check( str_contains( $pattern, 'No collections yet.' ), 'pattern includes the collection empty state' );
cr_collection_template_check( str_contains( $pattern, '"label":"Previous"' ) && str_contains( $pattern, '"label":"Next"' ), 'pagination has Previous and Next labels' );

$post_close = strpos( $pattern, '<!-- /wp:post-template -->' );
$pager_open = strpos( $pattern, '<!-- wp:query-pagination ', $post_close ?: 0 );
$no_results = strpos( $pattern, '<!-- wp:query-no-results -->' );
cr_collection_template_check( false !== $post_close && false !== $pager_open && false !== $no_results && $post_close < $pager_open && $pager_open < $no_results, 'pagination follows the grid immediately before the empty state' );

if ( is_dir( $root . '/.git' ) && function_exists( 'shell_exec' ) ) {
	$diff = (string) shell_exec( 'cd ' . escapeshellarg( $root ) . ' && git diff origin/main -- functions.php 2>/dev/null' );
	$added = array_values( array_filter( explode( "\n", $diff ), static fn( string $line ): bool => str_starts_with( $line, '+' ) && ! str_starts_with( $line, '+++' ) ) );
	$removed = array_values( array_filter( explode( "\n", $diff ), static fn( string $line ): bool => str_starts_with( $line, '-' ) && ! str_starts_with( $line, '---' ) ) );
	cr_collection_template_check(
		array(
			"+require_once __DIR__ . '/inc/collection.php';",
			"+require_once __DIR__ . '/inc/stream-collection.php';",
		) === $added && array() === $removed,
		'functions.php differs from origin/main by exactly two require lines'
	);
} else {
	echo "SKIP functions.php diff: git or shell_exec unavailable\n";
}

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
