<?php
/**
 * Craft single integration (PKIW issue 249).
 *
 * The H1 stays in the template header, and this file adds no single render
 * filter. The craft layout belongs to the stylesheet added in the next lane.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleCraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark craft singles for their stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if (
		function_exists( '\\Courtneyr\\Child\\Craft\\is_craft_single' )
		&& \Courtneyr\Child\Craft\is_craft_single()
		&& ! in_array( 'cr-craft-single', $classes, true )
	) {
		$classes[] = 'cr-craft-single';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Let the shared featured-image filter suppress repeated craft covers.
 *
 * @param mixed $kinds Kind slugs registered so far.
 * @return mixed
 */
function repeat_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	if ( ! in_array( 'craft', $kinds, true ) ) {
		$kinds[] = 'craft';
	}

	return $kinds;
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );
