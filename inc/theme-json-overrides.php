<?php

/**
 * Theme JSON merge overrides.
 *
 * Two-stage filter strategy because WP's update_with() merges palette
 * entries by slug rather than replacing the array wholesale.
 *
 * @package CourtneyrChild
 */

declare(strict_types=1);

namespace Courtneyr\Child\ThemeJsonOverrides;

if (! defined('ABSPATH')) {
	exit;
}

function read_canonical(): array
{
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}

	$path = COURTNEYR_CHILD_DIR . '/theme.json';
	if (! is_readable($path)) {
		$cache = array('palette' => array(), 'gradients' => array());
		return $cache;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	$cache   = array(
		'palette'   => $decoded['settings']['color']['palette']   ?? array(),
		'gradients' => $decoded['settings']['color']['gradients'] ?? array(),
	);
	return $cache;
}

function strip_core_defaults(\WP_Theme_JSON_Data $theme_json): \WP_Theme_JSON_Data
{
	$data = $theme_json->get_data();
	if (! isset($data['settings']['color'])) {
		return $theme_json;
	}
	$data['settings']['color']['palette']   = array();
	$data['settings']['color']['gradients'] = array();
	$data['settings']['color']['duotone']   = array();
	$data['version']                        = 3;
	return $theme_json->update_with($data);
}
add_filter('wp_theme_json_data_default', __NAMESPACE__ . '\\strip_core_defaults');

/**
 * Replace the 'theme' origin palette + gradients with ONLY ours.
 *
 * update_with() merges palette entries by slug. To replace the entire
 * array, we use Reflection to swap the underlying WP_Theme_JSON object.
 */
function override_theme_palette(\WP_Theme_JSON_Data $theme_json): \WP_Theme_JSON_Data
{
	$canonical = read_canonical();
	$data      = $theme_json->get_data();

	if (! isset($data['settings'])) {
		$data['settings'] = array();
	}
	if (! isset($data['settings']['color'])) {
		$data['settings']['color'] = array();
	}

	$data['settings']['color']['palette']          = $canonical['palette'];
	$data['settings']['color']['gradients']        = $canonical['gradients'];
	$data['settings']['color']['defaultPalette']   = false;
	$data['settings']['color']['defaultGradients'] = false;
	$data['settings']['color']['defaultDuotone']   = false;
	$data['version']                               = 3;

	try {
		$reflection          = new \ReflectionClass($theme_json);
		$fresh               = new \WP_Theme_JSON($data, 'theme');
		$theme_json_property = $reflection->getProperty('theme_json');
		$theme_json_property->setAccessible(true);
		$theme_json_property->setValue($theme_json, $fresh);
	} catch (\Throwable $e) {
		return $theme_json->update_with($data);
	}

	return $theme_json;
}
add_filter('wp_theme_json_data_theme', __NAMESPACE__ . '\\override_theme_palette', 999);
