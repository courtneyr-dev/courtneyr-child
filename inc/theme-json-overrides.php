<?php
/**
 * Theme JSON merge overrides.
 *
 * The child-theme inheritance model in WordPress merges parent and child
 * theme.json palette arrays additively. Parent palette (Ollie + Ollie
 * Pro) and WP core defaults all leak through despite defaultPalette:false
 * because that flag only affects the 'default' origin layer, not 'theme'.
 *
 * The wp_theme_json_data_default filter empties WP core's color
 * contributions. The wp_theme_json_data_theme filter has to manipulate
 * the data structure directly: update_with() is purely additive (it
 * cannot remove parent contributions), so we read the current state,
 * replace the palette + gradients with our canonical values, and write
 * it back via update_with() with the version field so WP accepts it.
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
 * Cached for the duration of the request.
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
 * Empty WP core's default palette, gradients, and duotone presets.
 * Runs against the 'default' origin layer (WP core).
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
 * Replace the 'theme' origin palette + gradients with ONLY ours.
 *
 * KEY MECHANIC: get_data() returns the current merged state. We mutate
 * the palette + gradients arrays directly (replacing whatever Ollie +
 * Ollie Pro merged in) then pass the entire mutated structure back
 * through update_with(). update_with() is additive on its OWN call,
 * but because we're passing back a structure where palette/gradients
 * are arrays of OUR entries (not deltas), the result is just our values.
 *
 * Runs at priority 999 to execute AFTER all other plugin filters.
 */
function override_theme_palette( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
	$canonical = read_canonical();
	$data      = $theme_json->get_data();

	if ( ! isset( $data['settings']['color'] ) ) {
		$data['settings']['color'] = array();
	}

	// Wholesale replacement of the merged arrays, not deltas.
	$data['settings']['color']['palette']          = $canonical['palette'];
	$data['settings']['color']['gradients']        = $canonical['gradients'];
	$data['settings']['color']['defaultPalette']   = false;
	$data['settings']['color']['defaultGradients'] = false;
	$data['settings']['color']['defaultDuotone']   = false;
	$data['version']                               = 3;

	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_theme', __NAMESPACE__ . '\\override_theme_palette', 999 );
