<?php
/**
 * Theme JSON merge overrides.
 *
 * The child-theme inheritance model in WordPress merges parent and child
 * theme.json *additively* for arrays like settings.color.palette, even
 * when both parent and child set defaultPalette:false. The child cannot
 * say "drop the parent's array entirely" through theme.json alone.
 *
 * This file uses the wp_theme_json_data_theme filter (priority 20, after
 * Ollie Pro at priority 10) to:
 *
 *   1. Replace the merged palette with ONLY courtneyr-child's 15 colors
 *   2. Replace the merged gradients with ONLY courtneyr-child's 2
 *   3. Re-assert defaultPalette:false (kills WordPress core defaults
 *      that leak through despite settings.color.defaultPalette:false in
 *      child theme.json — same root cause)
 *
 * Without this, the block editor color picker shows ~30 colors
 * (15 ours + Ollie's 11 + WP core 8) and authors have to scroll past
 * unrelated brand colors to pick the right one. With this filter,
 * authors only see our 15.
 *
 * Why a separate file vs. dropping it in theme-supports.php: this is
 * a focused override that may need to be removed in v0.2 when patterns
 * land. Easier to comment out one require_once than to surgically
 * remove a function from a multi-purpose file.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ThemeJsonOverrides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the merged theme.json color palette with ours only.
 *
 * Runs at priority 20 so it executes AFTER plugins (Ollie Pro at 10)
 * have contributed their palette additions. The set_data() call replaces
 * the entire color sub-object, removing both Ollie's slugs and WP core's
 * leaked defaults in one pass.
 */
function override_palette( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
	$child_theme_json_path = COURTNEYR_CHILD_DIR . '/theme.json';

	if ( ! is_readable( $child_theme_json_path ) ) {
		return $theme_json;
	}

	$child_data = json_decode( file_get_contents( $child_theme_json_path ), true );
	if ( ! is_array( $child_data ) ) {
		return $theme_json;
	}

	// Pull the canonical palette + gradients from theme.json on disk.
	$our_palette   = $child_data['settings']['color']['palette']   ?? array();
	$our_gradients = $child_data['settings']['color']['gradients'] ?? array();

	// Get the current merged data, modify only the color section, re-set.
	$current = $theme_json->get_data();

	if ( ! isset( $current['settings'] ) ) {
		$current['settings'] = array();
	}
	if ( ! isset( $current['settings']['color'] ) ) {
		$current['settings']['color'] = array();
	}

	$current['settings']['color']['palette']        = $our_palette;
	$current['settings']['color']['gradients']      = $our_gradients;
	$current['settings']['color']['defaultPalette'] = false;
	$current['settings']['color']['defaultGradients']    = false;
	$current['settings']['color']['defaultDuotone']      = false;

	$theme_json->update_with( $current );

	return $theme_json;
}
add_filter( 'wp_theme_json_data_theme', __NAMESPACE__ . '\\override_palette', 20 );
