<?php
/**
 * Theme block bindings source: `courtneyr/current-year`.
 *
 * The footer colophon binds its copyright paragraph to this source so the year
 * follows the site's clock (site timezone) instead of a literal saved in the
 * part. An optional `prefix` argument is prepended to the year; the colophon
 * passes "© ". Core runs the value through wp_kses_post() before it replaces
 * the paragraph content. The editor script registers the same source on the
 * client so the canvas shows the year instead of the source label.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\BlockBindings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value for a `courtneyr/current-year` binding.
 *
 * @param array<string, mixed> $source_args Binding arguments (optional `prefix`).
 * @return string
 */
function current_year_value( array $source_args ): string {
	$prefix = isset( $source_args['prefix'] ) && is_string( $source_args['prefix'] ) ? $source_args['prefix'] : '';
	return $prefix . wp_date( 'Y' );
}

/**
 * Register the source.
 */
function register_sources(): void {
	register_block_bindings_source(
		'courtneyr/current-year',
		array(
			'label'              => __( 'Current year', 'courtneyr-child' ),
			'get_value_callback' => __NAMESPACE__ . '\\current_year_value',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_sources' );

/**
 * Register the source's editor values.
 */
function enqueue_editor_source(): void {
	wp_enqueue_script(
		'courtneyr-current-year-binding',
		COURTNEYR_CHILD_URI . '/assets/js/current-year-binding.js',
		array( 'wp-blocks' ),
		COURTNEYR_CHILD_VERSION,
		true
	);
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_source' );
