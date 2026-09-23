<?php
/**
 * Outpost composer appearance (0.7.46, issue 17): the /post shell follows the
 * site's stored light/dark/system preference. Needs Outpost's shell handle
 * filters (1.0.15+); on older Outpost the hooks simply never fire.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\OutpostAppearance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HANDLE = 'courtneyr-outpost-appearance';

/**
 * Register the bridge script early enough for the shell (template_redirect, priority 1).
 */
function register_bridge(): void {
	wp_register_script(
		HANDLE,
		COURTNEYR_CHILD_URI . '/assets/js/outpost-appearance-bridge.js',
		array(),
		COURTNEYR_CHILD_VERSION,
		false
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_bridge' );

/**
 * @param string[] $handles Script handles the shell prints.
 * @return string[]
 */
function add_bridge_script( array $handles ): array {
	$handles[] = HANDLE;
	return $handles;
}
add_filter( 'outpost_shell_script_handles', __NAMESPACE__ . '\\add_bridge_script' );
