<?php
/**
 * `php tests/php/wrapped-assets-test.php`: pattern, CSS and source contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/wrapped-bootstrap.php';

$pattern_path   = CR_WRAPPED_THEME_DIR . '/patterns/cr-wrapped-strip.php';
$pattern_source = (string) file_get_contents( $pattern_path );
$css            = (string) file_get_contents( CR_WRAPPED_THEME_DIR . '/assets/css/cr-wrapped.css' );
$functions      = (string) file_get_contents( CR_WRAPPED_THEME_DIR . '/functions.php' );
$wrapped        = (string) file_get_contents( CR_WRAPPED_THEME_DIR . '/inc/wrapped.php' );
$stream         = (string) file_get_contents( CR_WRAPPED_THEME_DIR . '/inc/stream-wrapped.php' );
ob_start();
require $pattern_path;
$pattern = (string) ob_get_clean();

cr_wrapped_contains( 'Slug: courtneyr-child/cr-wrapped-strip', $pattern_source, 'pattern slug is permanent' );
cr_wrapped_contains( 'Categories: cr-marks', $pattern_source, 'pattern uses cr-marks category' );
cr_wrapped_contains( 'Inserter: yes', $pattern_source, 'pattern is available in the inserter' );
cr_wrapped_contains( '<!-- wp:post-kinds-indieweb/wrapped-strip -->', $pattern, 'pattern has Wrapped strip wrapper block' );
cr_wrapped_contains( '<!-- wp:buttons -->', $pattern, 'pattern has core/buttons' );
cr_wrapped_contains( '<!-- wp:button {', $pattern, 'pattern has nested core/button' );
cr_wrapped_contains( '"text":{"source":"post-kinds/wrapped","args":{"key":"title"}}', $pattern, 'button text binds to title' );
cr_wrapped_contains( '"url":{"source":"post-kinds/wrapped","args":{"key":"url"}}', $pattern, 'button URL binds to url' );
cr_wrapped_contains( '"content":{"source":"post-kinds/wrapped","args":{"key":"count_line"}}', $pattern, 'paragraph binds to count line' );
cr_wrapped_not_contains( 'wp:heading', $pattern, 'pattern has no bound heading' );
foreach ( array( 'post-kinds-indieweb/wrapped-strip', 'group', 'buttons', 'button', 'paragraph' ) as $block_name ) {
	$opening = 'button' === $block_name ? '<!-- wp:button ' : '<!-- wp:' . $block_name;
	cr_wrapped_check( substr_count( $pattern, $opening ) === substr_count( $pattern, '<!-- /wp:' . $block_name . ' -->' ), $block_name . ' block comments are balanced' );
}

$core   = getenv( 'WP_CORE_DIR' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
$parser = $core . '/wp-includes/class-wp-block-parser.php';
if ( is_file( $parser ) ) {
	foreach ( array( 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php' ) as $parser_file ) {
		require_once $core . '/wp-includes/' . $parser_file;
	}
	$valid_blocks = static function ( array $blocks ) use ( &$valid_blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				if ( '' !== trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
					return false;
				}
				continue;
			}
			if ( ! $valid_blocks( (array) ( $block['innerBlocks'] ?? array() ) ) ) {
				return false;
			}
		}
		return true;
	};
	$content_blocks = static fn( array $blocks ): array => array_values(
		array_filter( $blocks, static fn( array $block ): bool => null !== ( $block['blockName'] ?? null ) )
	);
	$parsed         = ( new WP_Block_Parser() )->parse( $pattern );
	$top            = $content_blocks( $parsed );
	cr_wrapped_check( $valid_blocks( $parsed ), 'real block parser finds only balanced blocks and whitespace' );
	cr_wrapped_check( 1 === count( $top ) && 'post-kinds-indieweb/wrapped-strip' === ( $top[0]['blockName'] ?? null ), 'parsed pattern has one Wrapped wrapper block' );
	$wrapper_blocks = $content_blocks( (array) ( $top[0]['innerBlocks'] ?? array() ) );
	cr_wrapped_check( 1 === count( $wrapper_blocks ) && 'core/group' === ( $wrapper_blocks[0]['blockName'] ?? null ), 'Wrapped wrapper contains one core/group block' );
	cr_wrapped_check( 'cr-wrapped-strip' === ( $wrapper_blocks[0]['attrs']['className'] ?? null ), 'parsed group keeps the strip class attribute' );
	$group_blocks = $content_blocks( (array) ( $wrapper_blocks[0]['innerBlocks'] ?? array() ) );
	cr_wrapped_check( array( 'core/buttons', 'core/paragraph' ) === array_column( $group_blocks, 'blockName' ), 'parsed group contains buttons then paragraph' );
	$button_blocks = $content_blocks( (array) ( $group_blocks[0]['innerBlocks'] ?? array() ) );
	cr_wrapped_check( 1 === count( $button_blocks ) && 'core/button' === ( $button_blocks[0]['blockName'] ?? null ), 'parsed buttons block contains one core/button' );
	$button_bindings = $button_blocks[0]['attrs']['metadata']['bindings'] ?? null;
	cr_wrapped_check(
		array(
			'text' => array( 'source' => 'post-kinds/wrapped', 'args' => array( 'key' => 'title' ) ),
			'url'  => array( 'source' => 'post-kinds/wrapped', 'args' => array( 'key' => 'url' ) ),
		) === $button_bindings,
		'core/button JSON attributes decode to the exact text and URL bindings'
	);
	$paragraph_bindings = $group_blocks[1]['attrs']['metadata']['bindings'] ?? null;
	cr_wrapped_check(
		array( 'content' => array( 'source' => 'post-kinds/wrapped', 'args' => array( 'key' => 'count_line' ) ) ) === $paragraph_bindings,
		'core/paragraph JSON attributes decode to the exact count-line binding'
	);
} else {
	cr_wrapped_check( false, 'WordPress core block parser is available' );
}

cr_wrapped_contains( '.pkiw-wrapped-strip', $css, 'strip rules key on plugin class' );
cr_wrapped_not_contains( '!important', $css, 'CSS has no important declaration' );
cr_wrapped_not_contains( 'gradient(', $css, 'CSS has no literal gradient' );
cr_wrapped_check( 0 === preg_match( '/#[0-9a-f]{3,8}\b/i', $css ), 'CSS has no raw hex color' );
cr_wrapped_contains( '@media (forced-colors: active)', $css, 'CSS has forced-colors rules' );
cr_wrapped_contains( '@media (prefers-reduced-motion: reduce)', $css, 'CSS has reduced-motion rules' );
cr_wrapped_contains( ':root[data-theme="dark"]', $css, 'CSS has stored dark-mode rules' );
cr_wrapped_contains( '@media (prefers-color-scheme: dark)', $css, 'CSS has system dark-mode rules' );
cr_wrapped_contains( 'grid-template-columns: repeat(4, minmax(0, 1fr))', $css, 'metrics start four across' );
cr_wrapped_contains( 'grid-template-columns: repeat(2, minmax(0, 1fr))', $css, 'metrics become two across' );
cr_wrapped_contains( 'grid-template-columns: minmax(0, 1fr)', $css, 'metrics and top items become one across' );

$source = $wrapped . $stream;
cr_wrapped_check( 0 === preg_match( '/pkiw_read_|_pkiw_exercise|_pkiw_sleep/', $source ), 'theme reads no Wrapped number meta' );
cr_wrapped_not_contains( 'get_post_meta', $source, 'theme Wrapped PHP does not call get_post_meta' );
cr_wrapped_not_contains( 'get_metadata', $source, 'theme Wrapped PHP does not call get_metadata' );
cr_wrapped_not_contains( 'preg_', $source, 'production Wrapped PHP adds no preg operation on HTML' );
cr_wrapped_contains( "require_once __DIR__ . '/inc/wrapped.php';", $functions, 'functions.php requires wrapped.php' );
cr_wrapped_contains( "require_once __DIR__ . '/inc/stream-wrapped.php';", $functions, 'functions.php requires stream-wrapped.php' );

$base_archives = function_exists( 'shell_exec' ) ? shell_exec( 'git -C ' . escapeshellarg( CR_WRAPPED_THEME_DIR ) . ' show origin/main:inc/archives.php 2>/dev/null' ) : null;
if ( is_string( $base_archives ) && '' !== $base_archives ) {
	$current_archives = (string) file_get_contents( CR_WRAPPED_THEME_DIR . '/inc/archives.php' );
	cr_wrapped_check( $base_archives === $current_archives, 'inc/archives.php matches origin/main byte for byte' );
} else {
	echo "skip git comparison unavailable; inc/archives.php check deferred\n";
}

cr_wrapped_finish();
