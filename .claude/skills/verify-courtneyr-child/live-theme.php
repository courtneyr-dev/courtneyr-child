<?php
/**
 * Plugin Name: Verify courtneyr-child live theme
 * Description: Adds an X-Verify-Live header that names the site and the theme directory this PHP worker renders from. The verify-courtneyr-child skill installs it into its own Studio sites only.
 *
 * @package Courtneyr\Child
 */

defined( 'ABSPATH' ) || exit;

/*
 * PHP resolves every theme path through a realpath cache that each worker keeps
 * for realpath_cache_ttl seconds, so a worker can render a directory the theme
 * link no longer points at.
 */
add_action(
	'wp_loaded',
	static function () {
		$link  = get_stylesheet_directory();
		$roots = array( (string) realpath( $link ) => true );
		foreach ( realpath_cache_get() as $path => $entry ) {
			if ( ! str_starts_with( $path, $link . '/' ) ) {
				continue;
			}
			$inside = substr( $path, strlen( $link ) );
			$real   = $entry['realpath'];
			$roots[ str_ends_with( $real, $inside ) ? substr( $real, 0, -strlen( $inside ) ) : $real ] = true;
		}
		header(
			'X-Verify-Live: ' . wp_json_encode(
				array(
					'site'  => realpath( ABSPATH ),
					'theme' => array_keys( $roots ),
				)
			)
		);
	},
	PHP_INT_MAX
);
