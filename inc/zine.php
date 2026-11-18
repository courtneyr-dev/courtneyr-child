<?php
/**
 * Printable zine theme paint and single-print visibility.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Zine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Load the zine paint only on a source or print-view request. */
function enqueue_styles(): void {
	if ( ! class_exists( '\\PKIW\\Zine_Request' ) || is_admin() ) {
		return;
	}
	if ( ! is_tax( 'kind' ) && ! is_page( 'stream' ) && ! \PKIW\Zine_Request::is_print() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-zine',
		COURTNEYR_CHILD_URI . '/assets/css/cr-zine.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Reuse the archive cut-paper heading on the booklet cover.
 *
 * @param string $html  Rendered cover heading.
 * @param string $title Exact source title.
 */
function cover_title( string $html, string $title ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The filter contract includes the title.
	if ( ! function_exists( '\\Courtneyr\\Child\\Archives\\cutout_heading' ) ) {
		return $html;
	}
	return \Courtneyr\Child\Archives\cutout_heading( $html );
}
add_filter( 'pkiw_zine_cover_title_html', __NAMESPACE__ . '\\cover_title', 10, 2 );

/** Hide print-only single blocks on front-end screens. */
function enqueue_print_only_visibility(): void {
	if ( is_admin() || ! is_singular() ) {
		return;
	}
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only handle has no external resource to version.
	wp_register_style( 'courtneyr-print-only', false );
	wp_enqueue_style( 'courtneyr-print-only' );
	wp_add_inline_style( 'courtneyr-print-only', '@media screen{.cr-print-only{display:none}}' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_print_only_visibility' );
