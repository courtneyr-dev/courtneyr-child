<?php
/**
 * Wrapped single presentation and shared Wrapped block styles.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Wrapped;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STYLE_HANDLE = 'courtneyr-wrapped';
const STYLE_FILE   = 'cr-wrapped.css';

/**
 * Read display-ready Wrapped facts from the plugin privacy boundary.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( $post_id < 1 || ! function_exists( '\\PKIW\\wrapped_facts' ) ) {
		return array();
	}
	$facts = \PKIW\wrapped_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/** Whether the queried post is a public Wrapped single. */
function is_wrapped_single(): bool {
	return is_singular( 'post' ) && array() !== facts( (int) get_queried_object_id() );
}

/**
 * Turn a Wrapped post title into the existing cut-paper heading.
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed post-title block.
 */
function cutout_wrapped_title( string $html, array $block = array() ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	$is_main = 1 === (int) ( $block['attrs']['level'] ?? 0 ) && in_array( 'single-post__title', $classes, true );
	if ( ! $is_main || ! is_wrapped_single() || ! function_exists( '\\Courtneyr\\Child\\Archives\\cutout_heading' ) ) {
		return $html;
	}
	return \Courtneyr\Child\Archives\cutout_heading( $html );
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\cutout_wrapped_title', 10, 2 );

/**
 * Load the cutout sheet on a public Wrapped single.
 *
 * The Wrapped block loads its own sheet through wp_enqueue_block_style().
 */
function enqueue_cutout_on_wrapped(): void {
	if ( is_admin() || ! is_wrapped_single() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-cutout',
		COURTNEYR_CHILD_URI . '/assets/css/cr-cutout.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_cutout_on_wrapped' );

/** Attach the shared sheet to both plugin-rendered Wrapped blocks. */
function register_block_styles(): void {
	foreach ( array( 'post-kinds-indieweb/wrapped', 'post-kinds-indieweb/wrapped-strip' ) as $block_name ) {
		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => STYLE_HANDLE,
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/' . STYLE_FILE,
				'deps'   => array(),
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Record whether rendered markup contains one of the three Wrapped surfaces.
 *
 * Block styles enqueue while a dynamic block renders even when its callback
 * returns an empty string. Recording the output lets the late enqueue pass
 * remove the shared sheet from empty strips and non-listable Wrapped posts.
 *
 * @param string|null $html Rendered block markup, or null to read state.
 */
function rendered_surface( ?string $html = null ): bool {
	static $rendered = false;
	if ( null !== $html && ( str_contains( $html, 'pkiw-wrapped' ) || str_contains( $html, 'cr-wrapped-card' ) ) ) {
		$rendered = true;
	}
	return $rendered;
}

/**
 * Record final block markup. Core fires render_block before render_block_{name}, so a Stream card is recorded through its parent post-template block.
 *
 * @param string $html Rendered block markup.
 */
function record_rendered_surface( string $html ): string {
	rendered_surface( $html );
	return $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\record_rendered_surface', 9999 );

/** Remove the shared sheet when no Wrapped surface produced markup. */
function prune_unrendered_style(): void {
	if ( ! is_admin() && ! rendered_surface() ) {
		wp_dequeue_style( STYLE_HANDLE );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\prune_unrendered_style', 9999 );
