<?php
/**
 * Editor parity: mirror front-end kind classes and kind template parts in the editor.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\EditorParity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HANDLE       = 'courtneyr-editor-parity';
const KINDS_FILTER = 'courtneyr_child_editor_parity_kinds';

/**
 * Editor parity registry keyed by kind slug.
 *
 * Kind lanes can use the `courtneyr_child_editor_parity_kinds` filter to add an
 * entry for a kind, or append to an existing entry's `classes`.
 *
 * @return array<string, array{classes: array<int, string>, part: string}>
 */
function kinds(): array {
	$base = array();
	foreach ( \Courtneyr\Child\KindParts\kind_slugs() as $slug ) {
		$base[ $slug ] = array(
			'classes' => array( 'single-post', 'kind-' . $slug ),
		);
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- KINDS_FILTER stores the prefixed hook name.
	$filtered = apply_filters( KINDS_FILTER, $base );
	if ( ! is_array( $filtered ) ) {
		$filtered = $base;
	}

	$registered = array();
	foreach ( $filtered as $key => $entry ) {
		$slug = sanitize_key( (string) $key );
		if ( '' === $slug || ! is_array( $entry ) ) {
			continue;
		}

		$classes = array();
		$raw     = isset( $entry['classes'] ) && is_array( $entry['classes'] ) ? $entry['classes'] : array();
		foreach ( $raw as $class ) {
			if ( ! is_string( $class ) ) {
				continue;
			}
			$class = sanitize_html_class( $class );
			if ( '' === $class || in_array( $class, $classes, true ) ) {
				continue;
			}
			$classes[] = $class;
		}

		$registered[ $slug ] = array(
			'classes' => $classes,
			'part'    => \Courtneyr\Child\KindParts\part_slug_for_kind( $slug ),
		);
	}

	return $registered;
}

/**
 * Canvas body classes for a kind.
 *
 * @param string $kind Kind slug.
 * @return array<int, string> Classes for the editor iframe body.
 */
function canvas_classes( string $kind ): array {
	$registered = kinds();
	$slug       = sanitize_key( $kind );
	return $registered[ $slug ]['classes'] ?? array();
}

/**
 * Data passed to the editor script.
 *
 * @return array<string, mixed> Editor parity data.
 */
function editor_data(): array {
	return array(
		'kinds'      => kinds(),
		'routerSlug' => \Courtneyr\Child\KindParts\ROUTER_SLUG,
	);
}

/**
 * Inline editor parity data.
 *
 * @return string Inline JavaScript.
 */
function inline_script(): string {
	$data          = editor_data();
	$data['kinds'] = (object) $data['kinds'];
	$json          = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP );
	if ( false === $json ) {
		$json = '{}';
	}
	return 'window.courtneyrEditorParity = ' . $json . ';';
}

/**
 * Enqueue editor parity assets for post editing surfaces.
 *
 * @return void
 */
function enqueue(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! is_object( $screen ) ) {
		return;
	}

	$is_post_editor = 'post' === ( $screen->base ?? '' ) && 'post' === ( $screen->post_type ?? '' );
	$is_site_editor = 'site-editor' === ( $screen->base ?? '' );
	if ( ! $is_post_editor && ! $is_site_editor ) {
		return;
	}

	wp_register_script(
		HANDLE,
		COURTNEYR_CHILD_URI . '/assets/js/editor-parity.js',
		array( 'wp-data', 'wp-hooks', 'wp-element', 'wp-core-data', 'wp-compose', 'wp-editor' ),
		COURTNEYR_CHILD_VERSION,
		array( 'in_footer' => true )
	);
	wp_add_inline_script( HANDLE, inline_script(), 'before' );
	wp_enqueue_script( HANDLE );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue' );
