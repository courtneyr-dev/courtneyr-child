<?php
/**
 * Theme JSON merge overrides.
 *
 * The child-theme inheritance model in WordPress merges parent and child
 * theme.json palette arrays additively. Parent palette (Ollie + Ollie
 * Pro) and WP core defaults all leak through despite defaultPalette:false
 * because that flag only affects the 'default' origin layer, not 'theme'.
 *
 * WordPress exposes four filter layers, each with its own origin:
 *   - wp_theme_json_data_default   (origin: default — WP core)
 *   - wp_theme_json_data_blocks    (origin: blocks  — block.json files)
 *   - wp_theme_json_data_theme     (origin: theme   — theme.json + parent + filters)
 *   - wp_theme_json_data_user      (origin: custom  — Site Editor saved styles)
 *
 * The merged palette structure is:
 *   palette = {
 *     default: [...]   // WP core (cyan-bluish-gray, vivid-purple, etc.)
 *     theme:   [...]   // theme.json from parent + child + plugin filters
 *     custom:  [...]   // user customizations
 *   }
 *
 * To get JUST our 15 colors we need to:
 *   1. Empty the 'default' palette via wp_theme_json_data_default
 *   2. Replace the 'theme' palette with only our entries via
 *      wp_theme_json_data_theme (priority 999 to run after Ollie Pro)
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

	// Replace WP core's color contributions with empty arrays.
	$data['settings']['color']['palette']   = array();
	$data['settings']['color']['gradients'] = array();
	$data['settings']['color']['duotone']   = array();

	// update_with() requires a version field. Match the layer's version.
	$data['version'] = 3;

	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_default', __NAMESPACE__ . '\\strip_core_defaults' );

/**
 * Replace the 'theme' origin palette + gradients with ONLY ours.
 * Runs at priority 999 to execute AFTER all other plugin filters
 * (Ollie Pro registers theirs at default priority 10).
 */
function override_theme_palette( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
	$canonical = read_canonical();

	$new_data = array(
		'version'  => 3,
		'settings' => array(
			'color' => array(
				'palette'          => $canonical['palette'],
				'gradients'        => $canonical['gradients'],
				'defaultPalette'   => false,
				'defaultGradients' => false,
				'defaultDuotone'   => false,
			),
		),
	);

	return $theme_json->update_with( $new_data );
}
add_filter( 'wp_theme_json_data_theme', __NAMESPACE__ . '\\override_theme_palette', 999 );
