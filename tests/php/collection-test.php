<?php
/**
 * Standalone registration and source-contract checks for issue 240.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
define( 'COURTNEYR_CHILD_VERSION', 'test' );

$GLOBALS['cr_collection_hooks'] = array();
$GLOBALS['cr_collection_styles'] = array();
$GLOBALS['cr_collection_block_styles'] = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['cr_collection_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback, $priority, $accepted_args ); }
function __( $text, $domain = '' ) { return $text; }
function register_block_style( $block, $properties ) { $GLOBALS['cr_collection_block_styles'][ $block ][] = $properties; }
function wp_enqueue_block_style( $block, $properties ) { $GLOBALS['cr_collection_styles'][ $block ] = $properties; }
$GLOBALS['cr_collection_registered'] = array();
$GLOBALS['cr_collection_enqueued'] = array();
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['cr_collection_registered'][ $handle ] = array( 'src' => $src, 'deps' => $deps ); }
function wp_style_add_data( $handle, $key, $value ) { $GLOBALS['cr_collection_registered'][ $handle ][ $key ] = $value; }
function wp_enqueue_style( $handle ) { $GLOBALS['cr_collection_enqueued'][] = $handle; }

require dirname( __DIR__, 2 ) . '/inc/collection.php';

$failed = 0;
function cr_collection_check( bool $condition, string $message ): void {
	global $failed;
	echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
	$failed += $condition ? 0 : 1;
}

\Courtneyr\Child\Collection\register_styles();
\Courtneyr\Child\Collection\register_card_style();

cr_collection_check( 'cr-cabinet' === ( $GLOBALS['cr_collection_block_styles']['core/group'][0]['name'] ?? '' ), 'core/group registers the cabinet style' );
cr_collection_check( 'cr-cabinet-hall' === ( $GLOBALS['cr_collection_block_styles']['core/post-template'][0]['name'] ?? '' ), 'core/post-template registers the cabinet hall style' );
cr_collection_check( 6 === \Courtneyr\Child\Collection\page_size( array() )['collection'], 'collection page size is six' );

$parity = \Courtneyr\Child\Collection\register_editor_parity( array() );
cr_collection_check( array( 'single-post', 'kind-collection' ) === $parity['collection']['classes'], 'editor parity adds both collection classes' );

$rucss = \Courtneyr\Child\Collection\rucss( array( 'existing.css', 'cr-collection.css' ) );
cr_collection_check( array( 'existing.css', 'cr-collection.css', 'collection-item.css' ) === $rucss, 'RUCSS values stay ordered and unique' );

$style = $GLOBALS['cr_collection_styles']['post-kinds-indieweb/collection-item'] ?? array();
cr_collection_check( 'cr-collection' === ( $style['handle'] ?? '' ), 'collection-item uses the collection style handle' );
cr_collection_check( array( 'courtneyr-paper', 'courtneyr-nav' ) === ( $style['deps'] ?? array() ), 'collection-item uses the paper and nav dependencies' );
cr_collection_check( ! isset( $GLOBALS['cr_collection_styles']['post-kinds-indieweb/stream-card'] ), 'stream-card blocks do not attach the collection sheet' );
$registered = $GLOBALS['cr_collection_registered']['cr-collection'] ?? array();
cr_collection_check( array( 'courtneyr-paper', 'courtneyr-nav' ) === ( $registered['deps'] ?? array() ) && str_ends_with( (string) ( $registered['path'] ?? '' ), '/assets/css/cr-collection.css' ), 'the collection sheet is registered with its deps and path' );

$plain = '<article class="pk-card pk-card--stream pk-card--watch k-watch h-entry">Film</article>';
cr_collection_check( $plain === \Courtneyr\Child\Collection\enqueue_for_collection_card( $plain ) && array() === $GLOBALS['cr_collection_enqueued'], 'a non-collection Stream card leaves the collection sheet unloaded' );
$card = '<article class="pk-card pk-card--stream pk-card--collection pk-card--collection-stream k-collection h-entry">Cabinet</article>';
cr_collection_check( $card === \Courtneyr\Child\Collection\enqueue_for_collection_card( $card ) && array( 'cr-collection' ) === $GLOBALS['cr_collection_enqueued'], 'a collection Stream card loads the collection sheet' );
$GLOBALS['cr_collection_enqueued'] = array();
$group = '<div class="wp-block-group pkiw-collection is-style-cr-cabinet"></div>';
\Courtneyr\Child\Collection\cabinet_default( $group, array( 'attrs' => array( 'className' => 'pkiw-collection is-style-cr-cabinet' ) ) );
cr_collection_check( array( 'cr-collection' ) === $GLOBALS['cr_collection_enqueued'], 'a collection cabinet group loads the collection sheet' );
$GLOBALS['cr_collection_enqueued'] = array();
$filters = $GLOBALS['cr_collection_hooks']['render_block_post-kinds-indieweb/stream-card'][10] ?? array();
cr_collection_check( in_array( 'Courtneyr\\Child\\Collection\\enqueue_for_collection_card', array_column( $filters, 0 ), true ), 'the Stream card render filter is hooked' );

$root = dirname( __DIR__, 2 );
$owned = array(
	$root . '/inc/collection.php',
	$root . '/inc/stream-collection.php',
	$root . '/assets/css/cr-collection.css',
	$root . '/patterns/cr-cabinet-hall-loop.php',
	$root . '/templates/taxonomy-kind-collection.html',
);
$source = '';
foreach ( $owned as $path ) {
	$source .= "\n" . (string) file_get_contents( $path );
}
$php = (string) file_get_contents( $root . '/inc/collection.php' ) . (string) file_get_contents( $root . '/inc/stream-collection.php' );
foreach ( array( '\\PKIW', 'pkiw_', 'Collection_Items' ) as $forbidden ) {
	cr_collection_check( ! str_contains( $php, $forbidden ), 'theme PHP excludes ' . $forbidden );
}
// Acceptance T-6 allows glass dividers drawn with the line technique: 2px glass-token stops on transparent.
$line_stop = '(?:transparent|var\(--cr-cabinet-glass\))(?:\s+calc\(\d+% [-+] 1px\)){1,2}';
$line_technique = '/linear-gradient\(\s*90deg(?:\s*,\s*' . $line_stop . ')+\s*\)/';
$line_count = preg_match_all( $line_technique, $source );
cr_collection_check( $line_count === substr_count( $source, 'linear-gradient(' ), 'every linear-gradient is a glass-token line technique' );
$source = (string) preg_replace( $line_technique, '', $source );
foreach ( array( 'preg_', 'linear-gradient', 'radial-gradient', 'conic-gradient', 'repeating-', 'backdrop-filter', 'blur(', 'Rock Salt', 'content: attr(', 'url(' ) as $forbidden ) {
	cr_collection_check( false === stripos( $source, $forbidden ), 'owned source excludes ' . $forbidden );
}
cr_collection_check( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $source ), 'owned source has no raw hex colors' );

$css = (string) file_get_contents( $root . '/assets/css/cr-collection.css' );
foreach ( array( ':root[data-theme="dark"]', ':root:not([data-theme="light"])', 'forced-colors: active', 'prefers-reduced-motion', '.pk-entry-props[hidden]' ) as $required ) {
	cr_collection_check( str_contains( $css, $required ), 'collection CSS includes ' . $required );
}

$depth = 0;
$inside_no_preference = false;
$media_depth = -1;
$transition_outside = false;
foreach ( explode( "\n", $css ) as $line ) {
	if ( str_contains( $line, '@media (prefers-reduced-motion: no-preference)' ) ) {
		$inside_no_preference = true;
		$media_depth = $depth;
	}
	if ( str_contains( $line, 'transition:' ) && ! $inside_no_preference ) {
		$transition_outside = true;
	}
	$depth += substr_count( $line, '{' ) - substr_count( $line, '}' );
	if ( $inside_no_preference && $depth <= $media_depth ) {
		$inside_no_preference = false;
	}
}
cr_collection_check( ! $transition_outside, 'transitions appear only in no-preference motion rules' );

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
