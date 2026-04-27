<?php
/**
 * Theme JSON merge overrides.
 *
 * Ollie Pro (`olpo\Helper::filter_theme_json_data`) registers a filter on
 * `wp_theme_json_data_theme` at priority 10 that wholesale-replaces the
 * theme palette with whatever Ollie Pro style variation is currently
 * configured. This drops our courtneyr-child theme.json palette entirely.
 *
 * Two filters here, working together:
 *
 *   1. wp_theme_json_data_default — empties WP core's default palette
 *      (cyan-bluish-gray, vivid-purple, etc.) so they don't clutter
 *      the editor color picker.
 *
 *   2. wp_theme_json_data_theme at priority 999 — runs AFTER Ollie Pro
 *      and replaces the entire theme palette with our 16 brand entries.
 *      Priority 999 ensures we run last in the wp_filter callback chain.
 *
 * Discovered via diagnostic mu-plugin: Ollie Pro's filter was the cause
 * of every theme.json palette merge issue we saw today. Without this
 * override, the editor color picker shows Ollie Pro's selected style
 * variation colors instead of our brand palette.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ThemeJsonOverrides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read our canonical palette + gradients from theme.json on disk.
 * Static-cached for the duration of the request.
 */
function read_canonical(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	$path = COURTNEYR_CHILD_DIR . '/theme.json';
	if ( ! is_readable( $path ) ) {
		$cache = array( 'palette' => array(), 'gradients' => array() );
		return $cache;
	}

	$decoded = json_decode( (string) file_get_contents( $path ), true );
	$cache   = array(
		'palette'   => $decoded['settings']['color']['palette']   ?? array(),
		'gradients' => $decoded['settings']['color']['gradients'] ?? array(),
	);
	return $cache;
}

/**
 * Empty WP core's default palette, gradients, duotone presets.
 * Layer: wp_theme_json_data_default (origin: default)
 */
function strip_core_defaults( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
	$data = $theme_json->get_data();

	if ( ! isset( $data['settings']['color'] ) ) {
		return $theme_json;
	}

	$data['settings']['color']['palette']   = array();
	$data['settings']['color']['gradients'] = array();
	$data['settings']['color']['duotone']   = array();
	$data['version']                        = 3;

	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_default', __NAMESPACE__ . '\\strip_core_defaults' );

/**
 * Override the theme-origin palette with ONLY ours.
 * Runs at priority 999 to execute AFTER Ollie Pro (priority 10).
 *
 * Layer: wp_theme_json_data_theme (origin: theme)
 *
 * Strategy: pull current merged data, replace palette/gradients arrays
 * wholesale with our canonical values, call update_with(). Because
 * update_with() constructs a fresh WP_Theme_JSON from the data passed,
 * this effectively replaces (not merges) the palette for this layer.
 */
function override_theme_palette( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
	$canonical = read_canonical();
	$data      = $theme_json->get_data();

	if ( ! isset( $data['settings'] ) ) {
		$data['settings'] = array();
	}
	if ( ! isset( $data['settings']['color'] ) ) {
		$data['settings']['color'] = array();
	}

	$data['settings']['color']['palette']          = $canonical['palette'];
	$data['settings']['color']['gradients']        = $canonical['gradients'];
	$data['settings']['color']['defaultPalette']   = false;
	$data['settings']['color']['defaultGradients'] = false;
	$data['settings']['color']['defaultDuotone']   = false;
	$data['version']                               = 3;

	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_theme', __NAMESPACE__ . '\\override_theme_palette', 999 );
