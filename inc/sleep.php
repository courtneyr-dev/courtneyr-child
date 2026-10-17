<?php
/**
 * Sleep archive, single and Stream wiring (PKIW issue 247).
 *
 * The plugin owns the public sleep facts and markup. This file loads the
 * theme paint where that markup can appear and supplies the moon drawings.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Sleep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'sleep';
const STYLE_HANDLE = 'courtneyr-sleep';

/**
 * Moon phase slugs the plugin may ask the theme to draw.
 */
const MOON_PHASES = array(
	'new',
	'waxing-crescent',
	'first-quarter',
	'waxing-gibbous',
	'full',
	'waning-gibbous',
	'last-quarter',
	'waning-crescent',
);

/** Whether this request is the sleep kind archive. */
function is_sleep_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single sleep post. */
function is_sleep_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Enqueue the sleep paint where sleep markup can appear.
 *
 * The plugin's neutral sheet is an optional dependency, so the theme sheet
 * follows it when the plugin has registered the handle and still works when
 * Post Kinds is inactive or older.
 */
function enqueue_styles(): void {
	$stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();

	if ( ! is_admin() && ! is_sleep_archive() && ! is_sleep_single() && ! $stream ) {
		return;
	}

	$dependencies = wp_style_is( 'pkiw-sleep-night', 'registered' )
		? array( 'pkiw-sleep-night' )
		: array();

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-sleep.css',
		$dependencies,
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Body classes for the sleep archive and single.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_sleep_archive() && ! in_array( 'cr-sleep-archive', $classes, true ) ) {
		$classes[] = 'cr-sleep-archive';
	}
	if ( is_sleep_single() && ! in_array( 'cr-sleep-single', $classes, true ) ) {
		$classes[] = 'cr-sleep-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Supply the theme's inline drawing for a known moon phase.
 *
 * @param string $html    Incoming drawing markup.
 * @param string $phase   Moon phase slug.
 * @param int    $post_id Post asking for the drawing (unused).
 * @return string
 */
function moon_svg( string $html, string $phase, int $post_id ): string {
	unset( $post_id );

	if ( ! in_array( $phase, MOON_PHASES, true ) ) {
		return $html;
	}

	/**
	 * SVG markup cached by moon phase.
	 *
	 * @var array<string, string|null> $cache
	 */
	static $cache = array();
	if ( ! array_key_exists( $phase, $cache ) ) {
		$file = COURTNEYR_CHILD_DIR . '/assets/img/cr-moon-' . $phase . '.svg';
		if ( ! is_readable( $file ) ) {
			$cache[ $phase ] = null;
		} else {
			$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-bundled data file.
			if ( false === $svg ) {
				$cache[ $phase ] = null;
			} else {
				$svg             = preg_replace( '/<\?xml[^?]*\?>/i', '', $svg );
				$svg             = is_string( $svg ) ? preg_replace( '/<!--.*?-->/s', '', $svg ) : null;
				$cache[ $phase ] = is_string( $svg ) ? trim( $svg ) : null;
			}
		}
	}

	return null === $cache[ $phase ] || '' === $cache[ $phase ] ? $html : $cache[ $phase ];
}
add_filter( 'pkiw_sleep_moon_html', __NAMESPACE__ . '\\moon_svg', 10, 3 );
